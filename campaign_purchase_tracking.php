<?php
$pageTitle = "Campaign Purchase Tracking";
$currentPagePin = 153;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';

if ($connect instanceof mysqli) {
    @mysqli_set_charset($connect, 'utf8mb4');
}
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
    echo '<script>location.href = "' . $SITEURL . '/campaign_table.php";</script>';
    exit();
}

$canRefresh = isActionAllowed('Edit', $pinAccess);
$csrfToken = campaignCsrfToken('purchase_tracking');
$pageUrl = $SITEURL . '/campaign_purchase_tracking.php?campaign_id=' . (int) $campaignId;
$backUrl = $SITEURL . '/campaign.php?id=' . (int) $campaignId;

$filterPlatform = trim((string) input('platform'));
$filterPurchaseStatus = trim((string) input('purchase_status'));
$filterCustomerType = trim((string) input('customer_type'));
$filterPeriodStart = trim((string) input('period_start_date'));
$filterPeriodEnd = trim((string) input('period_end_date'));
$filterCustomer = trim((string) input('customer_keyword'));

function campaignPurchaseTrackingBuildRows($connect, $campaignId, $filters)
{
    $rows = array();
    $where = array("cc.`campaign_id`='" . (int) $campaignId . "'", "cc.`status`='A'");

    if (($filters['platform'] ?? '') !== '') {
        $where[] = "cc.`platform`='" . $connect->real_escape_string((string) $filters['platform']) . "'";
    }
    if (($filters['purchase_status'] ?? '') !== '') {
        $where[] = "cc.`purchase_status`='" . $connect->real_escape_string((string) $filters['purchase_status']) . "'";
    }
    if (($filters['customer_keyword'] ?? '') !== '') {
        $safeKeyword = $connect->real_escape_string((string) $filters['customer_keyword']);
        $where[] = "(cc.`customer_name` LIKE '%" . $safeKeyword . "%' OR cc.`customer_id` LIKE '%" . $safeKeyword . "%' OR cc.`customer_contact` LIKE '%" . $safeKeyword . "%')";
    }

    $customerSql = "SELECT cc.* FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " cc WHERE " . implode(' AND ', $where) . " ORDER BY cc.`id` ASC";
    $customerResult = mysqli_query($connect, $customerSql);
    if (!$customerResult) {
        return $rows;
    }

    while ($customer = $customerResult->fetch_assoc()) {
        $customerId = (int) ($customer['id'] ?? 0);
        if ($customerId <= 0) {
            continue;
        }

        $purchaseWhere = array("`campaign_id`='" . (int) $campaignId . "'", "`campaign_customer_id`='" . $customerId . "'", "`status`='A'");
        if (($filters['period_start_date'] ?? '') !== '') {
            $purchaseWhere[] = "DATE(`order_date`) >= '" . $connect->real_escape_string((string) $filters['period_start_date']) . "'";
        }
        if (($filters['period_end_date'] ?? '') !== '') {
            $purchaseWhere[] = "DATE(`order_date`) <= '" . $connect->real_escape_string((string) $filters['period_end_date']) . "'";
        }
        if (($filters['customer_type'] ?? '') !== '') {
            $purchaseWhere[] = "`customer_type`='" . $connect->real_escape_string((string) $filters['customer_type']) . "'";
        }

        $purchaseSql = "SELECT * FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE " . implode(' AND ', $purchaseWhere) . " ORDER BY `order_date` DESC, `id` DESC";
        $purchaseResult = mysqli_query($connect, $purchaseSql);
        $orders = array();
        $totalAmount = 0.00;
        $latestOrderDate = '';
        $customerType = '';

        if ($purchaseResult) {
            while ($order = $purchaseResult->fetch_assoc()) {
                $orders[] = $order;
                $totalAmount += is_numeric($order['order_amount'] ?? null) ? (float) $order['order_amount'] : 0;
                $orderDate = campaignDateValue($order['order_date'] ?? '');
                if ($orderDate !== '' && ($latestOrderDate === '' || $orderDate > $latestOrderDate)) {
                    $latestOrderDate = $orderDate;
                }
                if ($customerType === '' && trim((string) ($order['customer_type'] ?? '')) !== '') {
                    $customerType = trim((string) $order['customer_type']);
                }
            }
        }

        if (($filters['customer_type'] ?? '') !== '' && empty($orders)) {
            continue;
        }

        $purchaseStatus = !empty($orders) ? 'Purchased' : 'Not Purchased';
        if (($filters['purchase_status'] ?? '') !== '' && $filters['purchase_status'] !== $purchaseStatus) {
            continue;
        }

        if ($customerType === '') {
            $customerType = 'New Customer';
        }

        $rows[] = array(
            'customer' => $customer,
            'orders' => $orders,
            'purchase_status' => $purchaseStatus,
            'total_amount' => $totalAmount,
            'customer_type' => $customerType,
            'latest_order_date' => $latestOrderDate,
        );
    }

    return $rows;
}

function campaignPurchaseTrackingResolveCustomerDisplay($connect, $financeConnect, $customer)
{
    $customerName = trim((string) ($customer['customer_name'] ?? ''));
    $customerId = trim((string) ($customer['customer_id'] ?? ''));
    $platform = strtolower(trim((string) ($customer['platform'] ?? '')));

    if ($platform === 'shopee' && function_exists('customerLabelResolveShopeeCustomerMeta')) {
        $buyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $customerName, $customerId);
        if (!empty($buyerMeta) && isset($buyerMeta['buyer_username']) && trim((string) $buyerMeta['buyer_username']) !== '') {
            return trim((string) $buyerMeta['buyer_username']);
        }
    }

    if ($customerName !== '' && !ctype_digit($customerName)) {
        return $customerName;
    }

    if ($customerId !== '' && $platform === 'shopee' && function_exists('customerLabelResolveShopeeCustomerMeta')) {
        $buyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $customerId, $customerName);
        if (!empty($buyerMeta) && isset($buyerMeta['buyer_username']) && trim((string) $buyerMeta['buyer_username']) !== '') {
            return trim((string) $buyerMeta['buyer_username']);
        }
    }

    if ($customerName !== '') {
        return $customerName;
    }

    return $customerId;
}

if (post('actionBtn') === 'refreshPurchase') {
    if (!campaignVerifyCsrf('purchase_tracking', post('csrf_token')) || !$canRefresh) {
        campaignSetPopup('Unable to refresh purchase check.', $pageUrl, 'ErrMO');
        echo '<script>location.href = "' . $pageUrl . '";</script>';
        exit();
    }

    $summary = campaignRunPurchaseCheck($connect, $finance_connect, $campaignId);
    campaignAudit(
        $connect,
        $pageTitle,
        'edit',
        USER_NAME . ' refreshed campaign purchase check. Checked customers: ' . (int) $summary['checked_customers'] . ', records inserted: ' . (int) $summary['records_inserted'] . '.',
        '',
        CAMPAIGN_PURCHASE_RECORD
    );
    campaignSetPopup('Purchase check refreshed successfully.', $pageUrl, 'ErrMO');
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$filters = array(
    'platform' => $filterPlatform,
    'purchase_status' => $filterPurchaseStatus,
    'customer_type' => $filterCustomerType,
    'period_start_date' => $filterPeriodStart,
    'period_end_date' => $filterPeriodEnd,
    'customer_keyword' => $filterCustomer,
);
$trackingRows = campaignPurchaseTrackingBuildRows($connect, $campaignId, $filters);

if (input('export') === '1') {
    $filename = 'campaign_purchase_tracking_' . (int) $campaignId . '_' . date('Ymd_His') . '.csv';
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Customer', 'Platform', 'Purchase Status', 'Order Summary', 'Total Purchase Amount', 'Customer Type', 'Latest Order Date'));
    foreach ($trackingRows as $row) {
        $customer = $row['customer'];
        $displayCustomerName = campaignPurchaseTrackingResolveCustomerDisplay($connect, $finance_connect, $customer);
        $orderSummary = array();
        foreach ($row['orders'] as $order) {
            $orderSummary[] = trim((string) ($order['order_no'] ?? '')) . ' | ' . trim(preg_replace('/\s+/', ' ', (string) ($order['order_detail'] ?? ''))) . ' | ' . trim((string) ($order['order_status'] ?? '')) . ' | ' . number_format((float) ($order['order_amount'] ?? 0), 2, '.', '');
        }
        fputcsv($output, array(
            $displayCustomerName,
            trim((string) ($customer['platform'] ?? '')),
            $row['purchase_status'],
            implode("\n", $orderSummary),
            number_format((float) $row['total_amount'], 2, '.', ''),
            $row['customer_type'],
            $row['latest_order_date'],
        ));
    }
    fclose($output);
    exit();
}

$platformOptions = array('Shopee', 'Lazada', 'Website', 'Facebook');
$statusOptions = array('Purchased', 'Not Purchased');
$customerTypeOptions = array('New Customer', 'Return Customer');
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    preloader(300);
    $(document).ready(function () {
        createSortingTable('campaign_purchase_tracking_table', { searching: true, order: [[1, 'asc']] });
    });
</script>

<body>
    <div class="page-load-cover">
        <div class="container-fluid px-4">
            <div class="row mt-3">
                <div class="col-12">
                    <p>
                        <a href="<?= $SITEURL ?>/campaign_table.php">Campaign</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        Purchase Tracking
                    </p>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                <div>
                    <h1>Campaign Purchase Tracking</h1>
                    <?php campaignRenderBadge($campaign); ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <input type="hidden" name="campaign_id" value="<?= (int) $campaignId ?>">
                        <div class="col-md-2">
                            <label class="form-label">Platform</label>
                            <select class="form-select" name="platform">
                                <option value="">All Platform</option>
                                <?php foreach ($platformOptions as $option): ?>
                                    <option value="<?= campaignH($option) ?>" <?= $filterPlatform === $option ? 'selected' : '' ?>><?= campaignH($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Purchase Status</label>
                            <select class="form-select" name="purchase_status">
                                <option value="">All Status</option>
                                <?php foreach ($statusOptions as $option): ?>
                                    <option value="<?= campaignH($option) ?>" <?= $filterPurchaseStatus === $option ? 'selected' : '' ?>><?= campaignH($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Customer Type</label>
                            <select class="form-select" name="customer_type">
                                <option value="">All Customer Type</option>
                                <?php foreach ($customerTypeOptions as $option): ?>
                                    <option value="<?= campaignH($option) ?>" <?= $filterCustomerType === $option ? 'selected' : '' ?>><?= campaignH($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Period Start</label>
                            <input type="date" class="form-control" name="period_start_date" value="<?= campaignH($filterPeriodStart) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Period End</label>
                            <input type="date" class="form-control" name="period_end_date" value="<?= campaignH($filterPeriodEnd) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Search Customer</label>
                            <input type="text" class="form-control" name="customer_keyword" value="<?= campaignH($filterCustomer) ?>">
                        </div>
                        <div class="col-md-12 d-flex align-items-center gap-3 flex-wrap">
                            <button type="submit" class="btn btn-outline-primary filter-reset">Search</button>
                            <a class="btn btn-outline-danger filter-reset" href="<?= campaignH($pageUrl) ?>">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive">
                    <table id="campaign_purchase_tracking_table" class="table table-striped w-100">
                        <thead>
                            <tr>
                                <th class="hideColumn">ID</th>
                                <th>S/N</th>
                                <th>Customer</th>
                                <th>Platform</th>
                                <th>Purchase Status</th>
                                <th>Order Summary</th>
                                <th>Total Purchase Amount</th>
                                <th>Customer Type</th>
                                <th>Latest Order Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sn = 1; ?>
                            <?php foreach ($trackingRows as $row): ?>
                                <?php
                                $customer = $row['customer'];
                                $customerDbId = (int) ($customer['id'] ?? 0);
                                $displayCustomerName = campaignPurchaseTrackingResolveCustomerDisplay($connect, $finance_connect, $customer);

                                ?>
                                <tr>
                                    <td class="hideColumn"><?= $customerDbId ?></td>
                                    <td><?= $sn++; ?></td>
                                    <td>
                                        <strong><?= campaignH($displayCustomerName !== '' ? $displayCustomerName : '-') ?></strong>
                                    </td>
                                    <td><?= campaignH($customer['platform'] ?? '') ?></td>
                                    <td><span class="badge <?= $row['purchase_status'] === 'Purchased' ? 'bg-success' : 'bg-secondary' ?>"><?= campaignH($row['purchase_status']) ?></span></td>
                                    <td class="p-0">
                                        <?php if (empty($row['orders'])): ?>
                                            <div class="p-3">
                                                <span class="text-muted">No order found</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Order ID</th>
                                                            <th>Package Name</th>
                                                            <th>Order Status</th>
                                                            <th>Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($row['orders'] as $order): ?>
                                                            <?php
                                                            $orderPackageName = campaignPurchaseResolvePackageDisplayName(
                                                                $connect,
                                                                ($order['package_text'] ?? '') !== '' ? ($order['package_text'] ?? '') : ($order['order_detail'] ?? '')
                                                            );
                                                            $orderStatusName = campaignPurchaseResolveOrderStatusDisplayName($order['order_status'] ?? '');
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <?php
                                                                    $orderUrl = campaignBuildOrderViewUrl(
                                                                        $SITEURL,
                                                                        $customer['platform'] ?? '',
                                                                        $order['order_id'] ?? ''
                                                                    );
                                                                    ?>
                                                                    <?php if ($orderUrl !== ''): ?>
                                                                        <a href="<?= campaignH($orderUrl) ?>" target="_blank" class="fw-bold">
                                                                            <?= campaignH($order['order_no'] ?? '') ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <strong><?= campaignH($order['order_no'] ?? '') ?></strong>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?= campaignH($orderPackageName) ?>
                                                                </td>
                                                                <td>
                                                                    <?= campaignH($orderStatusName) ?>
                                                                </td>
                                                                <td>
                                                                    <?= number_format((float) ($order['order_amount'] ?? 0), 2) ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format((float) $row['total_amount'], 2) ?></td>
                                    <td><?= campaignH($row['customer_type']) ?></td>
                                    <td><?= campaignH($row['latest_order_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php campaignRenderBackButton($backUrl); ?>
        </div>
    </div>

    <script>
        checkCurrentPage('<?= campaignH($pageTitle) ?>', 'View');
        dropdownMenuDispFix();
        datatableAlignment('campaign_purchase_tracking_table');
        setButtonColor();
    </script>
    <?php campaignRenderPopupScript($pageTitle, $pageUrl); ?>
</body>

</html>
