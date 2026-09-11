<?php
$pageTitle = "Campaign Report";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';


if ($finance_connect instanceof mysqli) {
    @mysqli_set_charset($finance_connect, 'utf8mb4');
}

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$campaignId = (int) input('campaign_id');
if ($campaignId <= 0) {
    $campaignId = (int) post('campaign_id');
}

$campaign = campaignFetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    echo '<script>location.href = "' . $SITEURL . '/campaign/campaign_table.php";</script>';
    exit();
}

$canRefresh = isActionAllowed('Edit', $pinAccess);
$csrfToken = campaignCsrfToken('campaign_report');
$pageUrl = $SITEURL . '/campaign/campaign_report.php?campaign_id=' . (int) $campaignId;
$backUrl = $SITEURL . '/campaign/campaign.php?id=' . (int) $campaignId;

$reportPackageIds = campaignFetchCampaignPackageIds($connect, $campaignId);
$reportPackageNames = array();
if (!empty($reportPackageIds) && defined('PKG') && campaignTableExists($connect, PKG)) {
    $safePackageIds = array_map('intval', $reportPackageIds);
    $packageNameResult = mysqli_query($connect, "SELECT `name` FROM `" . PKG . "` WHERE `id` IN (" . implode(',', $safePackageIds) . ") ORDER BY `name` ASC");
    if ($packageNameResult) {
        while ($packageNameRow = $packageNameResult->fetch_assoc()) {
            $reportPackageNames[] = trim((string) ($packageNameRow['name'] ?? ''));
        }
    }
}

function campaignReportBuildData($connect, $campaignId, $campaign = array(), $packageIds = array())
{
    $data = array(
        'metrics' => array(
            'participants' => 0,
            'purchase_rate' => 0,
            'purchased_customers' => 0,
            'saved_customers' => 0,
            'saved_purchased' => 0,
            'saved_not_purchased' => 0,
            'new_customers' => 0,
            'total_sales' => 0,
            'new_customer_amount' => 0,
            'return_customer_amount' => 0,
            'new_customer_sales' => 0,
            'return_customer_sales' => 0,
            'avg_spend_per_customer' => 0,
        ),
        'follow_up_rows' => array(),
        'package_rows' => array(),
        'customer_rows' => array(),
        'platform_rows' => array(),
        'trend_rows' => array(),
        'repeat_distribution' => array('1' => 0, '2' => 0, '3+' => 0),
        'has_data' => false,
    );

    $periodStart = campaignDateValue($campaign['period_start_date'] ?? '');
    $periodEnd = campaignDateValue($campaign['period_end_date'] ?? '');
    $periodWhere = '';
    if ($periodStart !== '' && $periodEnd !== '') {
        $periodWhere = " AND DATE(`order_date`) >= '" . $connect->real_escape_string($periodStart) . "' AND DATE(`order_date`) <= '" . $connect->real_escape_string($periodEnd) . "'";
    }

    $packageFilter = '';
    if (!empty($packageIds)) {
        $safePackageIds = array_map('intval', $packageIds);
        $packageFilter = " AND `package_id` IN (" . implode(',', $safePackageIds) . ")";
    }

    $buyerIdentitySql = campaignBuyerIdentitySql($connect);

    // Counted after the period and package filters exist. This ran before they were built,
    // so the participant figure was taken across every record ever stored for the campaign
    // rather than the campaign's own period and packages.
    $participantResult = mysqli_query($connect, "SELECT COUNT(DISTINCT " . $buyerIdentitySql . ") AS cnt FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $packageFilter . $periodWhere);
    if ($participantResult && $participantResult->num_rows > 0) {
        $participantRow = $participantResult->fetch_assoc();
        $data['metrics']['participants'] = (int) ($participantRow['cnt'] ?? 0);
    }

    // Sales split by whether the buyer was on the campaign's saved list, counting buyers
    // individually. Grouping by campaign_customer_id put every new customer in one group,
    // so the new-customer figure could only ever be 1.
    $purchaseSql = "SELECT " . $buyerIdentitySql . " AS buyer_key, MAX(`customer_type`) AS customer_type, SUM(IFNULL(`order_amount`,0)) AS sales
        FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . "
        WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $packageFilter . $periodWhere . "
        GROUP BY buyer_key";
    $purchaseResult = mysqli_query($connect, $purchaseSql);
    if ($purchaseResult) {
        while ($purchaseRow = $purchaseResult->fetch_assoc()) {
            $customerType = trim((string) ($purchaseRow['customer_type'] ?? ''));
            $sales = is_numeric($purchaseRow['sales'] ?? null) ? (float) $purchaseRow['sales'] : 0;
            $data['metrics']['total_sales'] += $sales;
            if ($customerType === 'Return Customer') {
                $data['metrics']['return_customer_amount']++;
                $data['metrics']['return_customer_sales'] += $sales;
            } else {
                $data['metrics']['new_customer_amount']++;
                $data['metrics']['new_customer_sales'] += $sales;
            }
        }
    }

    // How many customers the campaign was set up with, and how many of them ordered. The
    // purchase rate is that ratio; it used to be hardcoded to 100%, which said nothing.
    // Read once and kept, because the Customer Detail List below needs these names too.
    $savedCustomerMap = array();
    $savedCustomerResult = mysqli_query($connect, "SELECT `id`, `customer_name`, `customer_contact`, `platform`
        FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . "
        WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'");
    if ($savedCustomerResult) {
        while ($savedCustomerRow = $savedCustomerResult->fetch_assoc()) {
            $savedCustomerMap[(int) ($savedCustomerRow['id'] ?? 0)] = $savedCustomerRow;
        }
    }
    $savedCustomerTotal = count($savedCustomerMap);

    $savedPurchasedTotal = 0;
    $savedPurchasedResult = mysqli_query($connect, "SELECT COUNT(DISTINCT `campaign_customer_id`) AS cnt FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A' AND `campaign_customer_id` > 0" . $packageFilter . $periodWhere);
    if ($savedPurchasedResult && $savedPurchasedResult->num_rows > 0) {
        $savedPurchasedRow = $savedPurchasedResult->fetch_assoc();
        $savedPurchasedTotal = (int) ($savedPurchasedRow['cnt'] ?? 0);
    }

    $data['metrics']['saved_customers'] = $savedCustomerTotal;
    $data['metrics']['saved_purchased'] = $savedPurchasedTotal;
    $data['metrics']['saved_not_purchased'] = max(0, $savedCustomerTotal - $savedPurchasedTotal);
    $data['metrics']['new_customers'] = max(0, $data['metrics']['participants'] - $savedPurchasedTotal);
    $data['metrics']['purchased_customers'] = $data['metrics']['participants'];
    $data['metrics']['purchase_rate'] = $savedCustomerTotal > 0 ? round(($savedPurchasedTotal / $savedCustomerTotal) * 100, 2) : 0;
    $data['metrics']['avg_spend_per_customer'] = $data['metrics']['purchased_customers'] > 0 ? round($data['metrics']['total_sales'] / $data['metrics']['purchased_customers'], 2) : 0;

    $followSql = "SELECT
            cm.`message_title`,
            COUNT(cf.`id`) AS total_assigned,
            SUM(CASE WHEN cf.`follow_up_status`='Completed' THEN 1 ELSE 0 END) AS followed_up
        FROM " . campaignTableName(CAMPAIGN_MESSAGE) . " cm
        LEFT JOIN " . campaignTableName(CAMPAIGN_FOLLOW_UP) . " cf ON cf.`campaign_message_id`=cm.`id` AND cf.`status`='A'
        WHERE cm.`campaign_id`='" . (int) $campaignId . "' AND cm.`status`='A'
        GROUP BY cm.`id`, cm.`message_title`
        HAVING total_assigned > 0
        ORDER BY cm.`sequence_no` ASC, cm.`id` ASC";
    $followResult = mysqli_query($connect, $followSql);
    if ($followResult) {
        while ($followRow = $followResult->fetch_assoc()) {
            $totalAssigned = (int) ($followRow['total_assigned'] ?? 0);
            $followedUp = (int) ($followRow['followed_up'] ?? 0);
            $data['follow_up_rows'][] = array(
                'message_title' => trim((string) ($followRow['message_title'] ?? '')),
                'total_assigned' => $totalAssigned,
                'followed_up' => $followedUp,
                'rate' => $totalAssigned > 0 ? round(($followedUp / $totalAssigned) * 100, 2) : 0,
            );
        }
    }

    $packageMap = array();
    $packageSql = "SELECT `package_text`, `order_detail`, `order_amount` FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $packageFilter . $periodWhere;
    $packageResult = mysqli_query($connect, $packageSql);
    if ($packageResult) {
        while ($packageRow = $packageResult->fetch_assoc()) {
            $packageText = trim((string) ($packageRow['package_text'] ?? ''));
            if ($packageText === '') {
                $packageText = trim((string) ($packageRow['order_detail'] ?? ''));
            }
            $packageText = campaignPurchaseResolvePackageDisplayName($connect, $packageText);
            if ($packageText === '') {
                $packageText = 'Unknown Package';
            }
            $packageText = preg_replace('/\s+/', ' ', $packageText);
            $amount = is_numeric($packageRow['order_amount'] ?? null) ? (float) $packageRow['order_amount'] : 0;
            if (!isset($packageMap[$packageText])) {
                $packageMap[$packageText] = array('package' => $packageText, 'purchase_amount' => 0, 'purchase_sales' => 0);
            }
            $packageMap[$packageText]['purchase_amount']++;
            $packageMap[$packageText]['purchase_sales'] += $amount;
        }
    }
    $packageRows = array_values($packageMap);
    usort($packageRows, function ($a, $b) {
        return $b['purchase_sales'] <=> $a['purchase_sales'];
    });
    $data['package_rows'] = $packageRows;

    $platformSql = "SELECT `platform`, COUNT(*) AS order_count, COUNT(DISTINCT " . $buyerIdentitySql . ") AS customer_count, SUM(IFNULL(`order_amount`,0)) AS total_sales FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $packageFilter . $periodWhere . " GROUP BY `platform` ORDER BY total_sales DESC";
    $platformResult = mysqli_query($connect, $platformSql);
    if ($platformResult) {
        while ($platformRow = $platformResult->fetch_assoc()) {
            $platformName = trim((string) ($platformRow['platform'] ?? ''));
            if ($platformName === '') {
                $platformName = 'Unknown';
            }
            $data['platform_rows'][] = array(
                'platform' => $platformName,
                'order_count' => (int) ($platformRow['order_count'] ?? 0),
                'customer_count' => (int) ($platformRow['customer_count'] ?? 0),
                'total_sales' => is_numeric($platformRow['total_sales'] ?? null) ? (float) $platformRow['total_sales'] : 0,
            );
        }
    }

    $trendSql = "SELECT DATE(`order_date`) AS order_day, COUNT(*) AS order_count, SUM(IFNULL(`order_amount`,0)) AS total_sales FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A' AND `order_date` IS NOT NULL" . $packageFilter . $periodWhere . " GROUP BY DATE(`order_date`) ORDER BY order_day ASC";
    $trendResult = mysqli_query($connect, $trendSql);
    if ($trendResult) {
        while ($trendRow = $trendResult->fetch_assoc()) {
            $data['trend_rows'][] = array(
                'date' => (string) ($trendRow['order_day'] ?? ''),
                'order_count' => (int) ($trendRow['order_count'] ?? 0),
                'total_sales' => is_numeric($trendRow['total_sales'] ?? null) ? (float) $trendRow['total_sales'] : 0,
            );
        }
    }

    // The Customer Detail List is the campaign's buyers: whoever ordered inside the period
    // and bought one of the campaign's packages. It is built from the orders themselves and
    // grouped per buyer, so a customer who did not order simply does not appear.
    //
    // Replaces a UNION of "every saved customer, LEFT JOINed to their orders" and "the new
    // customers", which listed saved customers with no orders at all, and then ran a second
    // query per row to fetch that row's orders.
    $hasBuyerColumns = campaignColumnExists($connect, CAMPAIGN_PURCHASE_RECORD, 'buyer_platform_id');
    $buyerSelectColumns = $hasBuyerColumns ? ", `buyer_platform_id`, `buyer_name`" : "";

    $orderSql = "SELECT `id`, `campaign_customer_id`, `platform`, `order_no`, `package_text`,
            `order_amount`, `order_date`, `order_status`" . $buyerSelectColumns . "
        FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . "
        WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $packageFilter . $periodWhere . "
        ORDER BY `order_date` DESC, `id` DESC";

    // campaignPurchaseResolvePackageDisplayName() queries PKG on every call and caches
    // nothing, so the same package text is resolved once here rather than once per order.
    $packageNameCache = array();

    $buyerGroups = array();
    $orderResult = mysqli_query($connect, $orderSql);
    if ($orderResult) {
        while ($orderRow = $orderResult->fetch_assoc()) {
            $rowCustomerId = (int) ($orderRow['campaign_customer_id'] ?? 0);
            $rowPlatform = trim((string) ($orderRow['platform'] ?? ''));
            $rowBuyerId = $hasBuyerColumns ? trim((string) ($orderRow['buyer_platform_id'] ?? '')) : '';

            // Same identity rule as campaignBuyerIdentitySql(), so the row count here agrees
            // with the participant figure above.
            $buyerKey = $rowCustomerId > 0
                ? 'C' . $rowCustomerId
                : 'B' . $rowPlatform . '|' . $rowBuyerId;

            if (!isset($buyerGroups[$buyerKey])) {
                $isSaved = $rowCustomerId > 0 && isset($savedCustomerMap[$rowCustomerId]);
                $savedCustomer = $isSaved ? $savedCustomerMap[$rowCustomerId] : array();

                if ($isSaved) {
                    $displayName = trim((string) ($savedCustomer['customer_name'] ?? ''));
                    $displayContact = trim((string) ($savedCustomer['customer_contact'] ?? ''));
                } else {
                    $displayName = $hasBuyerColumns ? trim((string) ($orderRow['buyer_name'] ?? '')) : '';
                    $displayContact = '';
                    if ($displayName === '') {
                        $displayName = $rowBuyerId !== '' ? 'New customer ' . $rowBuyerId : 'New customer';
                    }
                }

                $buyerGroups[$buyerKey] = array(
                    'customer_id' => $rowCustomerId,
                    'is_new_customer' => !$isSaved,
                    'customer_name' => $displayName,
                    'customer_contact' => $displayContact,
                    'platform' => $rowPlatform,
                    'order_count' => 0,
                    'total_amount' => 0.0,
                    'last_order_date' => '',
                    'orders' => array(),
                );
            }

            $orderAmount = is_numeric($orderRow['order_amount'] ?? null) ? (float) $orderRow['order_amount'] : 0;
            $orderDate = (string) ($orderRow['order_date'] ?? '');

            $rawPackageText = trim((string) ($orderRow['package_text'] ?? ''));
            if (!array_key_exists($rawPackageText, $packageNameCache)) {
                $packageNameCache[$rawPackageText] = campaignPurchaseResolvePackageDisplayName($connect, $rawPackageText);
            }

            $buyerGroups[$buyerKey]['order_count']++;
            $buyerGroups[$buyerKey]['total_amount'] += $orderAmount;
            if ($orderDate !== '' && $orderDate > $buyerGroups[$buyerKey]['last_order_date']) {
                $buyerGroups[$buyerKey]['last_order_date'] = $orderDate;
            }
            $buyerGroups[$buyerKey]['orders'][] = array(
                'order_no' => trim((string) ($orderRow['order_no'] ?? '')),
                'package_text' => $packageNameCache[$rawPackageText],
                'order_amount' => $orderAmount,
                'order_date' => $orderDate,
                'order_status' => trim((string) ($orderRow['order_status'] ?? '')),
                'platform' => trim((string) ($orderRow['platform'] ?? '')),
            );
        }
    }

    $customerRows = array_values($buyerGroups);
    usort($customerRows, function ($a, $b) {
        if ($a['total_amount'] === $b['total_amount']) {
            return strcasecmp($a['customer_name'], $b['customer_name']);
        }

        return $b['total_amount'] <=> $a['total_amount'];
    });
    $data['customer_rows'] = $customerRows;

    foreach ($customerRows as $customerRow) {
        $orderCount = (int) $customerRow['order_count'];
        if ($orderCount === 1) {
            $data['repeat_distribution']['1']++;
        } elseif ($orderCount === 2) {
            $data['repeat_distribution']['2']++;
        } elseif ($orderCount >= 3) {
            $data['repeat_distribution']['3+']++;
        }
    }

    $data['has_data'] = $data['metrics']['participants'] > 0 || !empty($data['follow_up_rows']) || !empty($data['package_rows']);
    return $data;
}

if (post('actionBtn') === 'refreshReport') {
    if (!campaignVerifyCsrf('campaign_report', post('csrf_token')) || !$canRefresh) {
        campaignSetPopup('Unable to refresh Campaign Report.', $pageUrl, 'ErrMO');
        echo '<script>location.href = "' . $pageUrl . '";</script>';
        exit();
    }

    $summary = campaignRunPurchaseCheck($connect, $finance_connect, $campaignId);
    campaignAudit($connect, $pageTitle, 'edit', USER_NAME . ' refreshed Campaign Report. Purchase records inserted: ' . (int) $summary['records_inserted'] . '.', '', CAMPAIGN_PURCHASE_RECORD);
    $refreshSummaryMessage = sprintf(
        'Campaign Report refreshed. Checked %d customer(s), %d purchased / %d not purchased, %d order(s) found (%d new, %d updated).',
        (int) $summary['checked_customers'],
        (int) $summary['customers_purchased'],
        (int) $summary['customers_not_purchased'],
        (int) $summary['orders_found'],
        (int) $summary['records_inserted'],
        (int) $summary['records_updated']
    );
    if (!empty($summary['skip_reasons']) && is_array($summary['skip_reasons'])) {
        $reasonParts = array();
        foreach ($summary['skip_reasons'] as $reasonKey => $reasonCount) {
            $reasonParts[] = $reasonKey . '=' . $reasonCount;
        }
        $refreshSummaryMessage .= ' Not-purchased breakdown: ' . implode(', ', $reasonParts) . '.';
    }
    if (!empty($summary['debug_info']) && is_array($summary['debug_info'])) {
        $refreshSummaryMessage .= ' [DEBUG: ' . implode(' | ', $summary['debug_info']) . ']';
    }
    campaignSetPopup($refreshSummaryMessage, $pageUrl, 'ErrMO');
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$reportData = campaignReportBuildData($connect, $campaignId, $campaign, $reportPackageIds);
$metrics = $reportData['metrics'];

if (input('export') === '1') {
    $filename = 'campaign_report_' . (int) $campaignId . '_' . date('Ymd_His') . '.csv';
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Campaign Report'));
    fputcsv($output, array('Campaign', $campaign['campaign_name'] ?? ''));
    fputcsv($output, array());
    fputcsv($output, array('Metric', 'Value'));
    fputcsv($output, array('Customer Total Participant', $metrics['participants']));
    fputcsv($output, array('Saved Customer List', $metrics['saved_customers']));
    fputcsv($output, array('Saved Customer Purchased', $metrics['saved_purchased']));
    fputcsv($output, array('Saved Customer Not Purchased', $metrics['saved_not_purchased']));
    fputcsv($output, array('Purchase Rate', $metrics['purchase_rate'] . '%'));
    fputcsv($output, array('Total Sales', number_format((float) $metrics['total_sales'], 2, '.', '')));
    fputcsv($output, array('New Customer Amount', $metrics['new_customer_amount']));
    fputcsv($output, array('Return Customer Amount', $metrics['return_customer_amount']));
    fputcsv($output, array('New Customer Sales', number_format((float) $metrics['new_customer_sales'], 2, '.', '')));
    fputcsv($output, array('Return Customer Sales', number_format((float) $metrics['return_customer_sales'], 2, '.', '')));
    fputcsv($output, array('Avg. Spend per Purchasing Customer', number_format((float) $metrics['avg_spend_per_customer'], 2, '.', '')));
    fputcsv($output, array());
    fputcsv($output, array('Message Shortcut', 'Total Assigned', 'Followed Up', 'Follow-Up Rate'));
    foreach ($reportData['follow_up_rows'] as $row) {
        fputcsv($output, array($row['message_title'], $row['total_assigned'], $row['followed_up'], $row['rate'] . '%'));
    }
    fputcsv($output, array());
    fputcsv($output, array('Package', 'Purchase Amount', 'Purchase Sales'));
    foreach ($reportData['package_rows'] as $row) {
        fputcsv($output, array($row['package'], $row['purchase_amount'], number_format((float) $row['purchase_sales'], 2, '.', '')));
    }
    fputcsv($output, array());
    fputcsv($output, array('Platform', 'Order Count', 'Customer Count', 'Total Sales'));
    foreach ($reportData['platform_rows'] as $row) {
        fputcsv($output, array($row['platform'], $row['order_count'], $row['customer_count'], number_format((float) $row['total_sales'], 2, '.', '')));
    }
    fputcsv($output, array());
    fputcsv($output, array('Date', 'Order Count', 'Total Sales'));
    foreach ($reportData['trend_rows'] as $row) {
        fputcsv($output, array($row['date'], $row['order_count'], number_format((float) $row['total_sales'], 2, '.', '')));
    }
    fputcsv($output, array());
    fputcsv($output, array('Repeat Purchase Count', 'Customers'));
    foreach ($reportData['repeat_distribution'] as $bucket => $count) {
        fputcsv($output, array($bucket . ' order(s)', $count));
    }
    fputcsv($output, array());
    fputcsv($output, array('Customer Name', 'Contact', 'Platform', 'Customer Type', 'Order Count', 'Total Amount', 'Last Order Date'));
    foreach ($reportData['customer_rows'] as $row) {
        fputcsv($output, array($row['customer_name'], $row['customer_contact'], $row['platform'], empty($row['is_new_customer']) ? 'Saved Customer' : 'New Customer', $row['order_count'], number_format((float) $row['total_amount'], 2, '.', ''), $row['last_order_date']));
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    
    $(document).ready(function () {
        if ($('#campaign_report_follow_up_table').length) {
            createSortingTable('campaign_report_follow_up_table', { searching: false, order: [[0, 'asc']] });
        }
        if ($('#campaign_report_package_table').length) {
            createSortingTable('campaign_report_package_table', { searching: false, order: [] });
        }
        if ($('#campaign_report_platform_table').length) {
            createSortingTable('campaign_report_platform_table', { searching: false, order: [] });
        }
        if ($('#campaign_report_customer_table').length) {
            createSortingTable('campaign_report_customer_table', { searching: true, order: [[5, 'desc']] });
        }
    });
</script>

<body>
    <div class="page-load-cover">
        <div class="container-fluid px-4">
            <div class="row mt-3">
                <div class="col-12">
                    <p>
                        <a href="<?= $SITEURL ?>/campaign/campaign_table.php">Campaign</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        Campaign Report
                    </p>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                <div>
                    <h1>Campaign Report</h1>
                    <?php campaignRenderBadge($campaign); ?>
                    <?php if (!empty($reportPackageNames)): ?>
                        <div class="text-muted small mt-1">
                            Scoped to package(s): <?= campaignH(implode(', ', $reportPackageNames)) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small mt-1">
                            Not scoped to a specific package - counts purchases of any package. Set Package(s) on the campaign to narrow this down.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <a class="btn btn-sm btn-rounded btn-outline-primary" href="<?= campaignH($pageUrl . '&export=1') ?>">
                        <i class="fa-solid fa-file-export"></i> Export CSV
                    </a>
                    <?php if ($canRefresh): ?>
                        <form method="post" action="<?= campaignH($pageUrl) ?>" class="d-inline">
                            <input type="hidden" name="campaign_id" value="<?= (int) $campaignId ?>">
                            <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
                            <button class="btn btn-sm btn-rounded btn-primary" type="submit" name="actionBtn" value="refreshReport">
                                <i class="fa-solid fa-arrows-rotate"></i> Refresh Report
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$reportData['has_data']): ?>
                <div class="alert alert-secondary">No report data available</div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <?php
                    $metricCards = array(
                        'Customer Total Participant' => $metrics['participants'],
                        'Saved Customer List' => $metrics['saved_customers'],
                        'Saved Customer Purchased' => $metrics['saved_purchased'],
                        'Saved Customer Not Purchased' => $metrics['saved_not_purchased'],
                        'Purchase Rate' => $metrics['purchase_rate'] . '%',
                        'Total Sales' => number_format((float) $metrics['total_sales'], 2),
                        'New Customer Amount' => $metrics['new_customer_amount'],
                        'Return Customer Amount' => $metrics['return_customer_amount'],
                        'New Customer Sales' => number_format((float) $metrics['new_customer_sales'], 2),
                        'Return Customer Sales' => number_format((float) $metrics['return_customer_sales'], 2),
                        'Avg. Spend per Purchasing Customer' => number_format((float) $metrics['avg_spend_per_customer'], 2),
                    );
                    ?>
                    <?php foreach ($metricCards as $label => $value): ?>
                        <div class="col-xl-3 col-md-4 col-sm-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="text-muted small"><?= campaignH($label) ?></div>
                                    <h4 class="mb-0"><?= campaignH($value) ?></h4>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($reportData['trend_rows'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Sales Trend</strong></div>
                        <div class="card-body">
                            <canvas id="campaign_report_trend_chart" height="90"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <?php if (!empty($reportData['platform_rows'])): ?>
                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header bg-white"><strong>Platform / Channel Breakdown</strong></div>
                                <div class="card-body table-responsive">
                                    <table id="campaign_report_platform_table" class="table table-striped w-100">
                                        <thead>
                                            <tr>
                                                <th>Platform</th>
                                                <th>Order Count</th>
                                                <th>Customer Count</th>
                                                <th>Total Sales</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['platform_rows'] as $row): ?>
                                                <tr>
                                                    <td><?= campaignH($row['platform']) ?></td>
                                                    <td><?= (int) $row['order_count'] ?></td>
                                                    <td><?= (int) $row['customer_count'] ?></td>
                                                    <td><?= number_format((float) $row['total_sales'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-lg-5">
                        <div class="card h-100">
                            <div class="card-header bg-white"><strong>Customer Repeat Purchase Distribution</strong></div>
                            <div class="card-body table-responsive">
                                <table class="table table-striped w-100">
                                    <thead>
                                        <tr>
                                            <th>Orders in Period</th>
                                            <th>Customers</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>1 order</td>
                                            <td><?= (int) $reportData['repeat_distribution']['1'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>2 orders</td>
                                            <td><?= (int) $reportData['repeat_distribution']['2'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>3+ orders</td>
                                            <td><?= (int) $reportData['repeat_distribution']['3+'] ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($reportData['follow_up_rows'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Message Follow-Up Rate</strong></div>
                        <div class="card-body table-responsive">
                            <table id="campaign_report_follow_up_table" class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Message Shortcut</th>
                                        <th>Total Assigned</th>
                                        <th>Followed Up</th>
                                        <th>Follow-Up Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['follow_up_rows'] as $row): ?>
                                        <tr>
                                            <td><?= campaignH($row['message_title']) ?></td>
                                            <td><?= (int) $row['total_assigned'] ?></td>
                                            <td><?= (int) $row['followed_up'] ?></td>
                                            <td><?= campaignH($row['rate']) ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($reportData['package_rows'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Each Package Purchase</strong> <span class="text-muted small">(ranked by sales, highest first)</span></div>
                        <div class="card-body table-responsive">
                            <table id="campaign_report_package_table" class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Package</th>
                                        <th>Purchase Amount</th>
                                        <th>Purchase Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['package_rows'] as $packageIndex => $row): ?>
                                        <tr>
                                            <td>
                                                <?= campaignH($row['package']) ?>
                                                <?php if ($packageIndex === 0): ?>
                                                    <span class="badge bg-success ms-1">Top Package</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= (int) $row['purchase_amount'] ?></td>
                                            <td><?= number_format((float) $row['purchase_sales'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($reportData['customer_rows'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Customer Detail List</strong></div>
                        <div class="card-body table-responsive">
                            <table id="campaign_report_customer_table" class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Contact</th>
                                        <th>Platform</th>
                                        <th>Customer Type</th>
                                        <th>Order Count</th>
                                        <th>Total Amount</th>
                                        <th>Last Order Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['customer_rows'] as $row): ?>
                                        <tr data-customer-orders="<?= campaignH(json_encode($row['orders'] ?? array())) ?>">
                                            <td>
                                                <a href="javascript:void(0)" class="campaign-customer-detail-link" data-customer-id="<?= (int) $row['customer_id'] ?>" data-customer-name="<?= campaignH($row['customer_name']) ?>" style="color: inherit; text-decoration: none; cursor: pointer;">
                                                    <?= campaignH($row['customer_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= campaignH($row['customer_contact']) ?></td>
                                            <td><?= campaignH($row['platform']) ?></td>
                                            <td>
                                                <?php if (!empty($row['is_new_customer'])): ?>
                                                    <span class="badge bg-info">New Customer</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">Saved Customer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= (int) $row['order_count'] ?></td>
                                            <td><?= number_format((float) $row['total_amount'], 2) ?></td>
                                            <td><?= campaignH($row['last_order_date']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Customer Detail Modal -->
                    <div class="modal fade" id="customerDetailModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Order Details - <span id="modalCustomerName"></span></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped" id="customerOrderTable">
                                            <thead>
                                                <tr>
                                                    <th>Order No</th>
                                                    <th>Package</th>
                                                    <th>Amount</th>
                                                    <th>Order Date</th>
                                                    <th>Status</th>
                                                    <th>Platform</th>
                                                </tr>
                                            </thead>
                                            <tbody id="customerOrderTableBody">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php campaignRenderBackButton($backUrl); ?>
        </div>
    </div>

    <?php if (!empty($reportData['trend_rows'])): ?>
        <script src="<?= campaignH(CHART_JS_LOCAL_PATH) ?>"></script>
    <?php endif; ?>
    <script>
        checkCurrentPage('<?= campaignH($pageTitle) ?>', 'View');
        dropdownMenuDispFix();
        datatableAlignment('campaign_report_follow_up_table');
        datatableAlignment('campaign_report_package_table');
        datatableAlignment('campaign_report_platform_table');
        datatableAlignment('campaign_report_customer_table');
        setButtonColor();

        document.addEventListener('click', function (e) {
            var link = e.target.closest ? e.target.closest('.campaign-customer-detail-link') : null;
            if (link) {
                e.preventDefault();
                var customerName = link.getAttribute('data-customer-name');
                var row = link.closest('tr');
                var ordersJson = row.getAttribute('data-customer-orders');
                var orders = [];
                try {
                    orders = JSON.parse(ordersJson || '[]');
                } catch (err) {
                    console.error('Failed to parse orders JSON', err);
                }
                document.getElementById('modalCustomerName').textContent = customerName;
                var tbody = document.getElementById('customerOrderTableBody');
                tbody.innerHTML = '';
                if (orders.length > 0) {
                    orders.forEach(function (order) {
                        var row = document.createElement('tr');
                        row.innerHTML = '<td>' + (order.order_no || '') + '</td>' +
                            '<td>' + (order.package_text || '') + '</td>' +
                            '<td>' + (parseFloat(order.order_amount || 0).toFixed(2)) + '</td>' +
                            '<td>' + (order.order_date || '') + '</td>' +
                            '<td>' + (order.order_status || '') + '</td>' +
                            '<td>' + (order.platform || '') + '</td>';
                        tbody.appendChild(row);
                    });
                }
                var modal = new bootstrap.Modal(document.getElementById('customerDetailModal'));
                modal.show();
            }
        });

        <?php if (!empty($reportData['trend_rows'])): ?>
            (function () {
                var trendCanvas = document.getElementById('campaign_report_trend_chart');
                if (!trendCanvas || typeof Chart === 'undefined') {
                    return;
                }
                var trendLabels = <?= json_encode(array_column($reportData['trend_rows'], 'date')) ?>;
                var trendSales = <?= json_encode(array_map(function ($row) {
                    return round((float) $row['total_sales'], 2);
                }, $reportData['trend_rows'])) ?>;
                var trendOrders = <?= json_encode(array_column($reportData['trend_rows'], 'order_count')) ?>;
                new Chart(trendCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: trendLabels,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Total Sales',
                                data: trendSales,
                                borderColor: '#0d6efd',
                                backgroundColor: '#0d6efd',
                                yAxisID: 'ySales',
                                tension: 0.25,
                                fill: false,
                            },
                            {
                                type: 'bar',
                                label: 'Order Count',
                                data: trendOrders,
                                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                                yAxisID: 'yOrders',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            ySales: { type: 'linear', position: 'left', beginAtZero: true, title: { display: true, text: 'Sales' } },
                            yOrders: { type: 'linear', position: 'right', beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Orders' }, grid: { drawOnChartArea: false } },
                        },
                    },
                });
            })();
        <?php endif; ?>
    </script>
    <?php campaignRenderPopupScript($pageTitle, $pageUrl); ?>

    <!-- DEBUG CONSOLE -->
    <script>
    (function() {
        const debugBtn = document.createElement('button');
        debugBtn.textContent = '🔧 Debug Console';
        debugBtn.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:10px 15px;background:#333;color:#fff;border:none;cursor:pointer;z-index:9999;border-radius:5px;';
        debugBtn.onclick = function() {
            const modal = document.getElementById('debugModal');
            modal.style.display = modal.style.display === 'block' ? 'none' : 'block';
        };
        document.body.appendChild(debugBtn);

        const modal = document.createElement('div');
        modal.id = 'debugModal';
        modal.style.cssText = 'position:fixed;bottom:70px;right:20px;width:500px;max-height:400px;background:#1e1e1e;color:#00ff00;border:1px solid #00ff00;border-radius:5px;padding:15px;font-family:monospace;font-size:12px;overflow-y:auto;z-index:9998;display:none;';
        modal.innerHTML = '<div style="margin-bottom:10px;font-weight:bold;color:#ffff00;">📊 Debug Information</div>' +
                         '<div id="debugContent">Loading...</div>';
        document.body.appendChild(modal);
    })();
    </script>

    <?php
    // Render debug info as JSON data for the console
    $debugData = array(
        'campaign_id' => $campaignId,
        'campaign_name' => $campaign['campaign_name'] ?? '',
        'period_start' => $campaign['period_start_date'] ?? '',
        'period_end' => $campaign['period_end_date'] ?? '',
    );

    // Get campaign package IDs
    $campaignPackageIds = campaignFetchCampaignPackageIds($connect, $campaignId);
    $debugData['selected_package_ids'] = $campaignPackageIds;

    // Get package names from PACKAGE table
    $packageNames = array();
    if (!empty($campaignPackageIds) && defined('PKG') && campaignTableExists($connect, PKG)) {
        $escapedIds = array_map(function($id) use ($connect) { return (int)$id; }, $campaignPackageIds);
        $sql = "SELECT id, name FROM `" . PKG . "` WHERE id IN (" . implode(',', $escapedIds) . ") ORDER BY id";
        $result = $connect->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $packageNames[$row['id']] = $row['name'];
            }
        }
    }
    $debugData['package_names'] = $packageNames;

    // Get sample orders from finance database
    $sampleOrders = array();
    $financeDbInfo = array();
    if ($finance_connect instanceof mysqli) {
        // Get platforms used by campaign customers (manual + auto-discovered)
        $platformsUsed = array();

        // First, check manual customers
        $platformResult = $connect->query("SELECT DISTINCT platform FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE campaign_id=" . (int)$campaignId . " AND status='A'");
        if ($platformResult) {
            while ($row = $platformResult->fetch_assoc()) {
                $platform = trim((string)($row['platform'] ?? ''));
                if ($platform !== '') {
                    $platformsUsed[] = $platform;
                }
            }
        }

        // If no manual customers, get platforms from auto-discovered customers
        if (empty($platformsUsed) && !empty($campaignPackageIds)) {
            $configs = campaignPurchasePlatformConfigs($connect, $finance_connect);
            // Try each platform to find if any have matching packages
            foreach ($configs as $platformName => $config) {
                if (empty($config['table']) || !($config['conn'] instanceof mysqli)) {
                    continue;
                }
                $table = (string) $config['table'];
                if (!campaignTableExists($config['conn'], $table)) {
                    continue;
                }
                // Check if this platform has any orders for campaign packages
                $packageCol = campaignGetFirstExistingColumn($config['conn'], $table, isset($config['package_cols']) ? $config['package_cols'] : array());
                if ($packageCol !== '') {
                    $packageConditions = array();
                    foreach ($campaignPackageIds as $pkgId) {
                        $escapedId = $config['conn']->real_escape_string((string)$pkgId);
                        $packageConditions[] = campaignPurchaseQuoteColumn($packageCol) . " LIKE '%" . $escapedId . "%'";
                    }
                    $checkSql = "SELECT COUNT(*) as cnt FROM `" . $table . "` WHERE " . implode(' OR ', $packageConditions) . " LIMIT 1";
                    $checkResult = $config['conn']->query($checkSql);
                    if ($checkResult) {
                        $checkRow = $checkResult->fetch_assoc();
                        if ($checkRow['cnt'] > 0) {
                            $platformsUsed[] = strtolower($platformName);
                        }
                    }
                }
            }
        }

        $financeDbInfo['platforms_in_campaign'] = $platformsUsed;

        // Get platform configs
        $configs = campaignPurchasePlatformConfigs($connect, $finance_connect);
        $financeDbInfo['available_platforms'] = array_keys($configs);

        // Try to get sample orders from each platform table
        $allOrders = array();
        foreach ($platformsUsed as $platform) {
            $platformKey = ucwords(strtolower(trim((string) $platform)));
            if (!isset($configs[$platformKey])) {
                continue;
            }

            $config = $configs[$platformKey];
            $table = (string)($config['table'] ?? '');
            if ($table === '') {
                continue;
            }

            // Check if table exists
            $tableCheckResult = $finance_connect->query("SHOW TABLES LIKE '" . $finance_connect->real_escape_string($table) . "'");
            if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
                $financeDbInfo['errors'][] = "Table '" . htmlspecialchars($table) . "' not found for platform " . htmlspecialchars($platform);
                continue;
            }

            // Get sample orders from this platform
            $packageCol = 'package';
            $orderNoCol = 'order_no';
            $dateCol = 'order_date';
            $amountCol = 'amount';

            // Try more flexible columns
            $orderNoCols = isset($config['order_no_cols']) ? $config['order_no_cols'] : array();
            $orderNoCol = campaignGetFirstExistingColumn($finance_connect, $table, $orderNoCols);
            $dateCol = campaignGetFirstExistingColumn($finance_connect, $table, isset($config['date_cols']) ? $config['date_cols'] : array());
            $amountCol = campaignGetFirstExistingColumn($finance_connect, $table, isset($config['amount_cols']) ? $config['amount_cols'] : array());
            $packageCol = campaignGetFirstExistingColumn($finance_connect, $table, isset($config['package_cols']) ? $config['package_cols'] : array());

            if ($dateCol === '') {
                $financeDbInfo['errors'][] = "Could not find date column for platform " . htmlspecialchars($platform);
                continue;
            }

            $sql = "SELECT id, " . campaignPurchaseQuoteColumn($orderNoCol) . " as order_no, " . campaignPurchaseQuoteColumn($packageCol) . " as package_text, " . campaignPurchaseQuoteColumn($amountCol) . " as amount, " . campaignPurchaseQuoteColumn($dateCol) . " as order_date FROM `" . $table . "` ORDER BY id DESC LIMIT 10";
            $result = $finance_connect->query($sql);
            if (!$result) {
                $financeDbInfo['errors'][] = "Query failed for " . htmlspecialchars($platform) . ": " . htmlspecialchars($finance_connect->error);
                continue;
            }

            while ($row = $result->fetch_assoc()) {
                $packageText = $row['package_text'] ?? '';
                $packageIds = campaignPurchaseExtractPackageIds($packageText, $connect);
                $matchingIds = !empty($packageIds) ? array_intersect($packageIds, $campaignPackageIds) : array();

                $allOrders[] = array(
                    'platform' => $platform,
                    'order_id' => $row['id'],
                    'order_no' => $row['order_no'],
                    'package_text' => $packageText,
                    'extracted_ids' => $packageIds,
                    'matching_campaign_packages' => $matchingIds,
                );
            }
        }

        $sampleOrders = array_slice($allOrders, 0, 5);
        $financeDbInfo['total_sample_orders'] = count($allOrders);
        $financeDbInfo['displayed_sample_orders'] = count($sampleOrders);
    }
    $debugData['sample_orders'] = $sampleOrders;
    $debugData['finance_db_info'] = $financeDbInfo;

    echo '<script>';
    echo 'document.getElementById("debugContent").innerHTML = "<pre>" + JSON.stringify(' . json_encode($debugData) . ', null, 2) + "</pre>";';
    echo '</script>';
    ?>
</body>

</html>
