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

function campaignReportBuildData($connect, $campaignId, $campaign = array())
{
    $data = array(
        'metrics' => array(
            'participants' => 0,
            'purchase_rate' => 0,
            'purchased_customers' => 0,
            'total_sales' => 0,
            'new_customer_amount' => 0,
            'return_customer_amount' => 0,
            'new_customer_sales' => 0,
            'return_customer_sales' => 0,
        ),
        'follow_up_rows' => array(),
        'package_rows' => array(),
        'has_data' => false,
    );

    $participantResult = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'");
    if ($participantResult && $participantResult->num_rows > 0) {
        $participantRow = $participantResult->fetch_assoc();
        $data['metrics']['participants'] = (int) ($participantRow['cnt'] ?? 0);
    }

    $periodStart = campaignDateValue($campaign['period_start_date'] ?? '');
    $periodEnd = campaignDateValue($campaign['period_end_date'] ?? '');
    $periodWhere = '';
    if ($periodStart !== '' && $periodEnd !== '') {
        $periodWhere = " AND DATE(`order_date`) >= '" . $connect->real_escape_string($periodStart) . "' AND DATE(`order_date`) <= '" . $connect->real_escape_string($periodEnd) . "'";
    }

    $purchaseSql = "SELECT `campaign_customer_id`, `customer_type`, SUM(IFNULL(`order_amount`,0)) AS sales FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $periodWhere . " GROUP BY `campaign_customer_id`, `customer_type`";
    $purchaseResult = mysqli_query($connect, $purchaseSql);
    $purchasedCustomerIds = array();
    if ($purchaseResult) {
        while ($purchaseRow = $purchaseResult->fetch_assoc()) {
            $customerId = (int) ($purchaseRow['campaign_customer_id'] ?? 0);
            $customerType = trim((string) ($purchaseRow['customer_type'] ?? ''));
            $sales = is_numeric($purchaseRow['sales'] ?? null) ? (float) $purchaseRow['sales'] : 0;
            if ($customerId > 0) {
                $purchasedCustomerIds[$customerId] = true;
            }
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

    $data['metrics']['purchased_customers'] = count($purchasedCustomerIds);
    $data['metrics']['purchase_rate'] = $data['metrics']['participants'] > 0 ? round(($data['metrics']['purchased_customers'] / $data['metrics']['participants']) * 100, 2) : 0;

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
    $packageSql = "SELECT `package_text`, `order_detail`, `order_amount` FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A'" . $periodWhere;
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
    $data['package_rows'] = array_values($packageMap);

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
    campaignSetPopup($refreshSummaryMessage, $pageUrl, 'ErrMO');
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$reportData = campaignReportBuildData($connect, $campaignId, $campaign);
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
    fputcsv($output, array('Purchase Rate', $metrics['purchase_rate'] . '%'));
    fputcsv($output, array('Total Customer Purchase', $metrics['purchased_customers']));
    fputcsv($output, array('Total Sales', number_format((float) $metrics['total_sales'], 2, '.', '')));
    fputcsv($output, array('New Customer Amount', $metrics['new_customer_amount']));
    fputcsv($output, array('Return Customer Amount', $metrics['return_customer_amount']));
    fputcsv($output, array('New Customer Sales', number_format((float) $metrics['new_customer_sales'], 2, '.', '')));
    fputcsv($output, array('Return Customer Sales', number_format((float) $metrics['return_customer_sales'], 2, '.', '')));
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
            createSortingTable('campaign_report_package_table', { searching: false, order: [[0, 'asc']] });
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
                        'Purchase Rate' => $metrics['purchase_rate'] . '%',
                        'Total Customer Purchase' => $metrics['purchased_customers'],
                        'Total Sales' => number_format((float) $metrics['total_sales'], 2),
                        'New Customer Amount' => $metrics['new_customer_amount'],
                        'Return Customer Amount' => $metrics['return_customer_amount'],
                        'New Customer Sales' => number_format((float) $metrics['new_customer_sales'], 2),
                        'Return Customer Sales' => number_format((float) $metrics['return_customer_sales'], 2),
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
                        <div class="card-header bg-white"><strong>Each Package Purchase</strong></div>
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
                                    <?php foreach ($reportData['package_rows'] as $row): ?>
                                        <tr>
                                            <td><?= campaignH($row['package']) ?></td>
                                            <td><?= (int) $row['purchase_amount'] ?></td>
                                            <td><?= number_format((float) $row['purchase_sales'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php campaignRenderBackButton($backUrl); ?>
        </div>
    </div>

    <script>
        checkCurrentPage('<?= campaignH($pageTitle) ?>', 'View');
        dropdownMenuDispFix();
        datatableAlignment('campaign_report_follow_up_table');
        datatableAlignment('campaign_report_package_table');
        setButtonColor();
    </script>
    <?php campaignRenderPopupScript($pageTitle, $pageUrl); ?>
</body>

</html>
