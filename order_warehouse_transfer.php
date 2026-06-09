<?php
$currentPagePin = 152;
$pageTitle = 'Order Warehouse Transfer';

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$pinGroupTitle = getPinGroupNameById($connect, $currentPagePin);
if (trim((string) $pinGroupTitle) !== '') {
    $pageTitle = $pinGroupTitle;
}

if (!function_exists('owtGenerateSecureHex')) {
    function owtGenerateSecureHex($byteLength = 32)
    {
        $byteLength = (int) $byteLength;
        if ($byteLength <= 0) {
            $byteLength = 32;
        }

        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($byteLength));
        }

        return sha1(uniqid((string) mt_rand(), true) . microtime(true));
    }
}

if (empty($_SESSION['order_warehouse_transfer_search_csrf'])) {
    $_SESSION['order_warehouse_transfer_search_csrf'] = owtGenerateSecureHex(32);
}
if (empty($_SESSION['order_warehouse_transfer_submit_csrf'])) {
    $_SESSION['order_warehouse_transfer_submit_csrf'] = owtGenerateSecureHex(32);
}

$pinAccess = checkCurrentPin($connect, $pageTitle);
if (!is_array($pinAccess)) {
    $pinAccess = array();
}

$canSearch = isActionAllowed('View', $pinAccess);
$canTransfer = isActionAllowed('Transfer', $pinAccess);

if (!$canSearch) {
    echo "<script>location.href = '" . $SITEURL . "/dashboard.php';</script>";
    exit;
}

if (!function_exists('owtEsc')) {
    function owtEsc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('owtRenderNumberedLines')) {
    function owtRenderNumberedLines($value)
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $value = trim((string) $value);
            if ($value === '' || $value === '-') {
                return owtEsc($value === '' ? '-' : $value);
            }

            $items = preg_split('/\s*,\s*/', $value);
        }

        $items = array_values(array_filter(array_map('trim', $items), function ($item) {
            return $item !== '';
        }));

        if (empty($items)) {
            return '-';
        }

        if (count($items) === 1) {
            return owtEsc($items[0]);
        }

        $html = '<div class="owt-numbered-lines">';
        foreach ($items as $index => $item) {
            $html .= '<div>' . ($index + 1) . '. ' . owtEsc($item) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('owtBuildTransferIdempotencyKey')) {
    function owtBuildTransferIdempotencyKey()
    {
        return owtGenerateSecureHex(32);
    }
}

if (!function_exists('owtBuildCustomerDisplayName')) {
    function owtBuildCustomerDisplayName($cmsConnect, $financeConnect, $platform, $orderRow, $sourceConfig)
    {
        $orderRow = is_array($orderRow) ? $orderRow : array();
        $sourceConfig = is_array($sourceConfig) ? $sourceConfig : array();
        $platform = shopeeOmsNormalizePlatformKey($platform, true);

        $customerField = isset($sourceConfig['customer_name_field']) ? (string) $sourceConfig['customer_name_field'] : '';
        if ($customerField === '' && isset($sourceConfig['customer_field'])) {
            $customerField = (string) $sourceConfig['customer_field'];
        }

        $customerValue = $customerField !== '' && isset($orderRow[$customerField])
            ? trim((string) $orderRow[$customerField])
            : '';

        if ($customerValue === '') {
            foreach (array('customer_name', 'cust_name', 'buyer', 'cust_id', 'name', 'shipping_name', 'ship_rec_name') as $fallbackField) {
                if (isset($orderRow[$fallbackField]) && trim((string) $orderRow[$fallbackField]) !== '') {
                    $customerValue = trim((string) $orderRow[$fallbackField]);
                    break;
                }
            }
        }

        if ($customerValue === '') {
            return '-';
        }

        if ($platform === 'shopee' && ctype_digit($customerValue) && defined('SHOPEE_CUST_INFO') && $financeConnect instanceof mysqli) {
            $safeCustomerId = (int) $customerValue;
            $customerRst = mysqli_query(
                $financeConnect,
                "SELECT `buyer_username` FROM `" . SHOPEE_CUST_INFO . "` WHERE `id` = '" . $safeCustomerId . "' AND `status` = 'A' LIMIT 1"
            );

            if ($customerRst && mysqli_num_rows($customerRst) > 0) {
                $customerRow = mysqli_fetch_assoc($customerRst);
                if (isset($customerRow['buyer_username']) && trim((string) $customerRow['buyer_username']) !== '') {
                    return trim((string) $customerRow['buyer_username']);
                }
            }
        }

        if (($platform === 'lazada' || $platform === 'website') && ctype_digit($customerValue) && $cmsConnect instanceof mysqli) {
            $customerTable = $platform === 'lazada'
                ? (defined('LAZADA_CUST_RCD') ? LAZADA_CUST_RCD : '')
                : (defined('WEB_CUST_RCD') ? WEB_CUST_RCD : '');

            if ($customerTable !== '') {
                $safeCustomerTable = str_replace('`', '``', $customerTable);
                $customerRst = mysqli_query(
                    $cmsConnect,
                    "SELECT `name`, `ship_rec_name` FROM `" . $safeCustomerTable . "` WHERE `id` = '" . (int) $customerValue . "' AND `status` = 'A' LIMIT 1"
                );

                if ($customerRst && mysqli_num_rows($customerRst) > 0) {
                    $customerRow = mysqli_fetch_assoc($customerRst);
                    if (isset($customerRow['name']) && trim((string) $customerRow['name']) !== '') {
                        return trim((string) $customerRow['name']);
                    }
                    if (isset($customerRow['ship_rec_name']) && trim((string) $customerRow['ship_rec_name']) !== '') {
                        return trim((string) $customerRow['ship_rec_name']);
                    }
                }
            }
        }

        return $customerValue;
    }
}


if (!function_exists('owtGetTransferLogTableName')) {
    function owtGetTransferLogTableName()
    {
        return defined('ORDER_WAREHOUSE_TRANSFER_LOG') ? ORDER_WAREHOUSE_TRANSFER_LOG : 'order_warehouse_transfer_log';
    }
}

if (!function_exists('owtTableExists')) {
    function owtTableExists($dbConnect, $tableName)
    {
        if (!($dbConnect instanceof mysqli)) {
            return false;
        }

        $safeTable = mysqli_real_escape_string($dbConnect, (string) $tableName);
        $rst = mysqli_query($dbConnect, "SHOW TABLES LIKE '" . $safeTable . "'");
        return $rst && mysqli_num_rows($rst) > 0;
    }
}

if (!function_exists('owtGetPlatformLabel')) {
    function owtGetPlatformLabel($platform)
    {
        $platform = shopeeOmsNormalizePlatformKey($platform, true);
        if ($platform === '') {
            return '-';
        }

        $sourceConfig = shopeeOmsGetOrderSourceConfig($platform);
        if (!empty($sourceConfig) && isset($sourceConfig['label']) && trim((string) $sourceConfig['label']) !== '') {
            return (string) $sourceConfig['label'];
        }

        return ucfirst($platform);
    }
}

if (!function_exists('owtGetTransferLogRows')) {
    function owtGetTransferLogRows($cmsConnect, $financeConnect)
    {
        $rows = array();
        $logTable = owtGetTransferLogTableName();

        if (!owtTableExists($financeConnect, $logTable)) {
            return $rows;
        }

        $defaultWarehouseId = function_exists('shopeeOmsGetDefaultWarehouseId') ? shopeeOmsGetDefaultWarehouseId($cmsConnect) : 0;
        $warehouseNameMap = function_exists('shopeeOmsLoadWarehouseNameMap') ? shopeeOmsLoadWarehouseNameMap($cmsConnect, true) : array();

        $safeLogTable = str_replace('`', '``', $logTable);
        $sql = "SELECT `id`, `platform`, `order_table`, `order_id`, `order_code`, `old_warehouse_id`, `new_warehouse_id`, `create_by`, `create_date`, `create_time`
            FROM `" . $safeLogTable . "`
            WHERE `status` = 'A'
            ORDER BY `id` DESC";

        $rst = mysqli_query($financeConnect, $sql);
        if (!$rst) {
            return $rows;
        }

        while ($row = mysqli_fetch_assoc($rst)) {
            $platform = isset($row['platform']) ? shopeeOmsNormalizePlatformKey($row['platform'], true) : '';
            $orderId = isset($row['order_id']) ? (int) $row['order_id'] : 0;
            $detailUrl = '';
            if ($platform !== '' && $orderId > 0) {
                $detailUrl = shopeeOmsGetOrderSourceViewUrl($platform, $orderId);
            }

            $createdBy = isset($row['create_by']) ? trim((string) $row['create_by']) : '';
            $createdByName = $createdBy;
            if ($createdBy !== '' && function_exists('commonResolveUserDisplayName')) {
                $createdByName = commonResolveUserDisplayName($cmsConnect, $createdBy);
            }

            $oldWarehouseId = isset($row['old_warehouse_id']) ? (int) $row['old_warehouse_id'] : 0;
            $newWarehouseId = isset($row['new_warehouse_id']) ? (int) $row['new_warehouse_id'] : 0;

            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'platform' => $platform,
                'platform_label' => owtGetPlatformLabel($platform),
                'order_code' => isset($row['order_code']) ? (string) $row['order_code'] : '',
                'order_id' => $orderId,
                'old_warehouse_name' => shopeeOmsResolveWarehouseNameById($cmsConnect, $oldWarehouseId, $defaultWarehouseId, $warehouseNameMap),
                'new_warehouse_name' => shopeeOmsResolveWarehouseNameById($cmsConnect, $newWarehouseId, $defaultWarehouseId, $warehouseNameMap),
                'created_by_name' => $createdByName !== '' ? $createdByName : '-',
                'created_at' => trim((string) (isset($row['create_date']) ? $row['create_date'] : '') . ' ' . (isset($row['create_time']) ? $row['create_time'] : '')),
                'detail_url' => $detailUrl,
            );
        }

        return $rows;
    }
}

if (!function_exists('owtBuildOrderSearchResults')) {
    function owtBuildOrderSearchResults($cmsConnect, $financeConnect, $searchOrderCode, $platformFilter, $canTransfer)
    {
        $results = array();
        $searchOrderCode = trim((string) $searchOrderCode);
        $platformFilter = shopeeOmsNormalizePlatformKey($platformFilter, true);
        if ($searchOrderCode === '') {
            return $results;
        }

        $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($cmsConnect);
        $warehouseRows = shopeeOmsLoadActiveWarehouses($cmsConnect);
        $warehouseNameMap = shopeeOmsLoadWarehouseNameMap($cmsConnect, true);

        foreach (shopeeOmsGetOrderSourceConfigs() as $platformKey => $sourceConfig) {
            if ($platformFilter !== '' && $platformFilter !== 'all' && $platformFilter !== $platformKey) {
                continue;
            }

            $sourceConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
            $orderRow = shopeeOmsLoadOrderByCode($sourceConnect, $searchOrderCode, $sourceConfig);
            if (empty($orderRow) || !isset($orderRow['status']) || (string) $orderRow['status'] !== 'A') {
                continue;
            }

            $productSummary = shopeeOmsBuildOrderProductSummaryBySource($cmsConnect, $orderRow, $sourceConfig);
            $productQtyMap = isset($productSummary['product_qty_map']) && is_array($productSummary['product_qty_map']) ? $productSummary['product_qty_map'] : array();
            $currentWarehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, $defaultWarehouseId);
            $availableWarehouses = array();

            foreach ($warehouseRows as $warehouseRow) {
                $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0;
                if ($warehouseId <= 0 || $warehouseId === $currentWarehouseId) {
                    continue;
                }
                $availableWarehouses[] = $warehouseRow;
            }

            $platformLabel = isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platformKey);
            $orderCode = shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig);
            $detailUrl = shopeeOmsGetOrderSourceViewUrl($platformKey, isset($orderRow['id']) ? (int) $orderRow['id'] : 0);

            $results[] = array(
                'platform' => $platformKey,
                'platform_label' => $platformLabel,
                'source_config' => $sourceConfig,
                'order_row' => $orderRow,
                'order_id' => isset($orderRow['id']) ? (int) $orderRow['id'] : 0,
                'order_code' => $orderCode !== '' ? $orderCode : ('Order #' . (int) $orderRow['id']),
                'customer_name' => owtBuildCustomerDisplayName($cmsConnect, $financeConnect, $platformKey, $orderRow, $sourceConfig),
                'status_label' => shopeeOmsGetStatusLabel(isset($orderRow['order_status']) ? $orderRow['order_status'] : ''),
                'current_warehouse_id' => $currentWarehouseId,
                'current_warehouse_name' => shopeeOmsResolveWarehouseNameById($cmsConnect, $currentWarehouseId, $defaultWarehouseId, $warehouseNameMap),
                'airbill_no' => isset($sourceConfig['airbill_no_field']) && isset($orderRow[$sourceConfig['airbill_no_field']]) && trim((string) $orderRow[$sourceConfig['airbill_no_field']]) !== ''
                    ? trim((string) $orderRow[$sourceConfig['airbill_no_field']])
                    : '-',
                'package_summary' => isset($productSummary['package_lines']) && is_array($productSummary['package_lines']) && !empty($productSummary['package_lines'])
                    ? $productSummary['package_lines']
                    : (isset($productSummary['package_summary']) && trim((string) $productSummary['package_summary']) !== ''
                        ? (string) $productSummary['package_summary']
                        : '-'),
                'product_summary' => !empty($productSummary['product_lines'])
                    ? $productSummary['product_lines']
                    : '-',
                'product_qty_map' => $productQtyMap,
                'transfer_block_reason' => empty($productQtyMap)
                    ? 'Product or package quantity could not be resolved for this order.'
                    : (!$canTransfer
                        ? 'You have search access only. Transfer permission is required to continue.'
                        : (empty($availableWarehouses) ? 'No other active warehouse is available for transfer.' : '')),
                'available_warehouses' => $availableWarehouses,
                'detail_url' => $detailUrl,
                'idempotency_key' => owtBuildTransferIdempotencyKey(),
            );
        }

        return $results;
    }
}

$flash = null;
$flashPopupMessage = '';
$flashPopupAct = '';
$flashPopupPage = $pageTitle;

if (isset($_SESSION['order_warehouse_transfer_flash']) && is_array($_SESSION['order_warehouse_transfer_flash'])) {
    $flash = $_SESSION['order_warehouse_transfer_flash'];
    unset($_SESSION['order_warehouse_transfer_flash']);

    $flashPopupMessage = isset($flash['message']) ? trim((string) $flash['message']) : '';
    $flashType = isset($flash['type']) ? trim((string) $flash['type']) : '';

    if ($flashPopupMessage !== '') {
        if ($flashType === 'success') {
            $flashPopupAct = 'E';
        } else {
            $flashPopupAct = 'ErrMO';
        }
    }
}

$searchOrderCode = isset($_GET['order_code']) ? trim((string) $_GET['order_code']) : '';
$searchPlatform = isset($_GET['platform']) ? shopeeOmsNormalizePlatformKey($_GET['platform'], true) : 'all';
if ($searchPlatform === '') {
    $searchPlatform = 'all';
}

$searchResults = array();
if ($searchOrderCode !== '') {
    $searchResults = owtBuildOrderSearchResults($connect, $finance_connect, $searchOrderCode, $searchPlatform, $canTransfer);
}

$searchNoResult = ($searchOrderCode !== '' && empty($searchResults));
$shouldOpenSearchModal = false;

if (isset($_SESSION['order_warehouse_transfer_show_search_modal']) && $_SESSION['order_warehouse_transfer_show_search_modal'] === '1') {
    $shouldOpenSearchModal = ($searchOrderCode !== '' && !empty($searchResults));
    unset($_SESSION['order_warehouse_transfer_show_search_modal']);
}

$transferLogRows = owtGetTransferLogRows($connect, $finance_connect);
?>


<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    preloader(300);
</script>

<body>
<div class="pre-load-center"><div class="preloader"></div></div>

<div class="page-load-cover">
    <div class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= owtEsc($pageTitle) ?></p>
    </div>

    <div id="formContainer" class="container mt-2" style="max-width:1400px;">
        <?php if (false && $flash && !empty($flash['message'])) { ?>
            <div class="alert alert-<?= owtEsc(isset($flash['type']) ? $flash['type'] : 'info') ?> alert-dismissible fade show" role="alert">
                <?= owtEsc($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>

        <div class="card owt-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h2 class="mb-0"><?= owtEsc($pageTitle) ?></h2>
                </div>

                <form class="row g-3 owt-search-form" method="post" action="<?= $SITEURL ?>/order_warehouse_transfer_process.php">
                    <input type="hidden" name="action" value="search_order">
                    <input type="hidden" name="search_csrf" value="<?= owtEsc($_SESSION['order_warehouse_transfer_search_csrf']) ?>">

                    <div class="col-12 col-md-4">
                        <label class="form-label form_lbl" for="order_code">Order Number</label>
                        <input class="form-control" type="text" name="order_code" id="order_code" value="<?= owtEsc($searchOrderCode) ?>" placeholder="Enter Order Number" required>
                        <div class="owt-field-message">
                            <?php if ($searchNoResult) { ?>
                                <span class="text-danger">No order found.</span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label form_lbl" for="platform">Platform</label>
                        <select class="form-select" name="platform" id="platform">
                            <option value="all" <?= $searchPlatform === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="shopee" <?= $searchPlatform === 'shopee' ? 'selected' : '' ?>>Shopee</option>
                            <option value="lazada" <?= $searchPlatform === 'lazada' ? 'selected' : '' ?>>Lazada</option>
                            <option value="facebook" <?= $searchPlatform === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                            <option value="website" <?= $searchPlatform === 'website' ? 'selected' : '' ?>>Website</option>
                        </select>
                        <div class="owt-field-message"></div>
                    </div>

                    <div class="col-12 col-md-auto owt-search-button-col">
                        <label class="form-label form_lbl owt-search-button-label d-none d-md-block">&nbsp;</label>
                        <button class="btn btn-rounded btn-primary owt-search-btn" type="submit" name="searchBtn" id="owtSearchBtn" value="search_order">Search Order</button>
                        <div class="owt-field-message"></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card owt-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h4 class="mb-0">Transfer Records</h4>
                </div>

                <div class="table-responsive">
                    <table id="owtTransferLogTable" class="table owt-transfer-record-table align-middle w-100">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Platform</th>
                                <th>Order Number</th>
                                <th>Old Warehouse</th>
                                <th>New Warehouse</th>
                                <th>Transferred By</th>
                                <th>Date Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sn = 1; ?>
                            <?php foreach ($transferLogRows as $logRow) { ?>
                                <tr>
                                    <td><?= $sn++ ?></td>
                                    <td><?= owtEsc($logRow['platform_label']) ?></td>
                                    <td>
                                        <?php if (trim((string) $logRow['detail_url']) !== '') { ?>
                                            <a href="<?= owtEsc($logRow['detail_url']) ?>"><?= owtEsc($logRow['order_code']) ?></a>
                                        <?php } else { ?>
                                            <?= owtEsc($logRow['order_code']) ?>
                                        <?php } ?>
                                    </td>
                                    <td><?= owtEsc($logRow['old_warehouse_name']) ?></td>
                                    <td><?= owtEsc($logRow['new_warehouse_name']) ?></td>
                                    <td><?= owtEsc($logRow['created_by_name']) ?></td>
                                    <td><?= owtEsc($logRow['created_at']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($transferLogRows)) { ?>
                    <div class="alert alert-secondary mb-0 mt-3">No transfer records found.</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="owtSearchResultModal" tabindex="-1" aria-labelledby="owtSearchResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="owtSearchResultModalLabel">Search Result</h5>
                    <button type="button" class="btn-close owt-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <?php if ($searchOrderCode !== '') { ?>
                        <?php if (empty($searchResults)) { ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                No active order was found for order number <?= owtEsc($searchOrderCode) ?>.
                            </div>
                        <?php } else { ?>
                            <?php foreach ($searchResults as $result) { ?>
                                <?php
                                $orderRow = isset($result['order_row']) && is_array($result['order_row']) ? $result['order_row'] : array();
                                $transferBlocked = trim((string) $result['transfer_block_reason']) !== '';
                                ?>
                                <div class="card owt-card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                            <div>
                                                <h4 class="mb-1"><?= owtEsc($result['platform_label']) ?> Order</h4>
                                                <div class="text-muted">Internal Record ID: <?= (int) $result['order_id'] ?></div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-12 col-md-4">
                                                <div class="owt-detail-label">Platform</div>
                                                <div class="owt-detail-value"><?= owtEsc($result['platform_label']) ?></div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="owt-detail-label">Order Number</div>
                                                <div class="owt-detail-value">
                                                    <?php if (trim((string) $result['detail_url']) !== '') { ?>
                                                        <a href="<?= owtEsc($result['detail_url']) ?>"><?= owtEsc($result['order_code']) ?></a>
                                                    <?php } else { ?>
                                                        <?= owtEsc($result['order_code']) ?>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="owt-detail-label">Customer / Buyer</div>
                                                <div class="owt-detail-value"><?= owtEsc($result['customer_name']) ?></div>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <div class="owt-detail-label">Status</div>
                                                <div class="owt-detail-value"><?= owtEsc($result['status_label']) ?></div>
                                            </div>

                                            <div class="col-12 col-md-4">
                                                <div class="owt-detail-label">Airbill No</div>
                                                <div class="owt-detail-value"><?= owtEsc($result['airbill_no']) ?></div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="owt-detail-label">Package Qty</div>
                                                <div class="owt-detail-value"><?= owtRenderNumberedLines($result['package_summary']) ?></div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div class="owt-detail-label">Product Qty</div>
                                                <div class="owt-detail-value"><?= owtRenderNumberedLines($result['product_summary']) ?></div>
                                            </div>
                                        </div>

                                        <?php if ($transferBlocked) { ?>
                                            <div class="alert alert-warning mt-4 mb-0" role="alert">
                                                <?= owtEsc($result['transfer_block_reason']) ?>
                                            </div>
                                        <?php } else { ?>
                                            <form class="mt-4 owt-actions" method="post" action="<?= $SITEURL ?>/order_warehouse_transfer_process.php">
                                                <input type="hidden" name="action" value="transfer_warehouse">
                                                <input type="hidden" name="transfer_csrf" value="<?= owtEsc($_SESSION['order_warehouse_transfer_submit_csrf']) ?>">
                                                <input type="hidden" name="idempotency_key" value="<?= owtEsc($result['idempotency_key']) ?>">
                                                <input type="hidden" name="platform" value="<?= owtEsc($result['platform']) ?>">
                                                <input type="hidden" name="order_id" value="<?= (int) $result['order_id'] ?>">
                                                <input type="hidden" name="search_order_code" value="<?= owtEsc($searchOrderCode) ?>">
                                                <input type="hidden" name="search_platform" value="<?= owtEsc($searchPlatform) ?>">

                                                <div class="row g-3 align-items-start owt-transfer-warehouse-row">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label form_lbl">Current Warehouse</label>
                                                        <div class="form-control bg-light">
                                                            <?= owtEsc($result['current_warehouse_name']) ?>
                                                        </div>
                                                        <div class="owt-field-message"></div>
                                                    </div>

                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label form_lbl" for="new_warehouse_id_<?= (int) $result['order_id'] ?>_<?= owtEsc($result['platform']) ?>">New Warehouse</label>
                                                        <select class="form-select" name="new_warehouse_id" id="new_warehouse_id_<?= (int) $result['order_id'] ?>_<?= owtEsc($result['platform']) ?>" required>
                                                            <option value="">Select New Warehouse</option>
                                                            <?php foreach ($result['available_warehouses'] as $warehouseRow) { ?>
                                                                <option value="<?= (int) $warehouseRow['id'] ?>"><?= owtEsc($warehouseRow['name']) ?></option>
                                                            <?php } ?>
                                                        </select>
                                                        <div class="owt-field-message"></div>
                                                    </div>

                                                    <div class="col-12 d-flex flex-wrap gap-2 justify-content-center">
                                                        <button class="btn btn-rounded btn-primary" type="submit" name="actionBtn" id="actionBtn" value="transfer_warehouse">Confirm Transfer</button>
                                                    </div>
                                                </div>
                                            </form>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var page = <?= json_encode($pageTitle) ?>;
    checkCurrentPage(page, '');
    dropdownMenuDispFix();
    setButtonColor();

    $(document).ready(function () {
        if ($.fn.DataTable) {
            $('#owtTransferLogTable').DataTable({
                pageLength: 10,
                order: [[6, 'desc']]
            });
        }

        var shouldOpenSearchModal = <?= json_encode($shouldOpenSearchModal) ?>;
        var modalElement = document.getElementById('owtSearchResultModal');

        function owtCleanupModalState() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                'overflow': '',
                'padding-right': ''
            });

            if (modalElement) {
                modalElement.style.pointerEvents = '';
            }
        }

        $('#owtSearchResultModal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
            owtCleanupModalState();
        });

        if (shouldOpenSearchModal && modalElement) {
            modalElement.style.pointerEvents = '';

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var searchModal = bootstrap.Modal.getOrCreateInstance(modalElement);
                searchModal.show();
            } else if ($.fn.modal) {
                $('#owtSearchResultModal').modal('show');
            }
        } else {
            owtCleanupModalState();
        }        

        var flashPopupMessage = <?= json_encode($flashPopupMessage) ?>;
        var flashPopupAct = <?= json_encode($flashPopupAct) ?>;
        var flashPopupPage = <?= json_encode($flashPopupPage) ?>;
        var flashPopupReturnUrl = <?= json_encode($SITEURL . '/order_warehouse_transfer.php') ?>;

        if (flashPopupMessage !== '' && flashPopupAct !== '') {
            if (flashPopupAct === 'E') {
                confirmationDialog('', 'Warehouse transfer successful', '', '', flashPopupReturnUrl, 'ErrMO');
            } else {
                confirmationDialog('', flashPopupMessage, '', '', flashPopupReturnUrl, 'ErrMO');
            }
        }
    });
</script>
</body>
</html>
