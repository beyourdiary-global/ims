<?php
$pageTitle = 'Stock List';
$currentPagePin = 120;

include_once 'include/connection.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
$stockOutBatchUsageTable = STOCK_OUT_BATCH_USAGE;
$stockFormPage = $SITEURL . '/stock_list.php';
$listPageSkipSessionReset = true;
$listPageSkipNumbering = true;


include_once 'include/list_page_header.php';

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$userNameMap = siLoadUserNameMap($connect);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

$stockOutRows = siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable, 'Stock Out');
$stockListCurrentPageUrl = function_exists('shopeeOmsGetCurrentPageUrl')
    ? shopeeOmsGetCurrentPageUrl()
    : (rtrim((string) $SITEURL, '/') . (isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/stock_list_table.php'));
$orderIds = array();
$orderNumbers = array();
$warehouseIds = array();
$groupedRows = array();

foreach ($stockOutRows as $row) {
    $orderId = isset($row['order_id']) ? (int) $row['order_id'] : 0;
    if ($orderId <= 0) {
        continue;
    }

    $orderIds[$orderId] = $orderId;
    $orderNumber = isset($row['order_number']) ? trim((string) $row['order_number']) : '';
    if ($orderNumber !== '') {
        $orderNumbers[$orderNumber] = $orderNumber;
    }
    $warehouseId = isset($row['warehouse_id']) ? (int) $row['warehouse_id'] : 0;
    if ($warehouseId > 0) {
        $warehouseIds[$warehouseId] = $warehouseId;
    }

    if (!isset($groupedRows[$orderId])) {
        $groupedRows[$orderId] = array(
            'order_id' => $orderId,
            'warehouse_id' => $warehouseId,
            'stock_out_date' => isset($row['stock_in_date']) ? (string) $row['stock_in_date'] : '',
            'order_number' => $orderNumber,
            'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
            'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
            'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            'items' => array(),
            'usage_rows' => array(),
        );
    }

    $groupedRows[$orderId]['items'][] = array(
        'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : 0,
        'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : 0,
        'package_id' => isset($row['package_id']) ? (int) $row['package_id'] : 0,
        'qty' => isset($row['product_quantity']) ? (int) $row['product_quantity'] : 0,
    );
}

$usageRows = siGetStockOutUsageRowsByOrderIds($finance_connect, array_values($orderIds), $stockOutBatchUsageTable);
foreach ($usageRows as $usageRow) {
    $stockOutOrderId = isset($usageRow['stock_out_order_id']) ? (int) $usageRow['stock_out_order_id'] : 0;
    if ($stockOutOrderId > 0 && isset($groupedRows[$stockOutOrderId])) {
        $groupedRows[$stockOutOrderId]['usage_rows'][] = $usageRow;
    }
}

$sourceOrderLinkMap = siBuildSourceOrderLinkMap($connect, $finance_connect, array_values($orderNumbers));
$transferLogMap = array();
if (!empty($orderNumbers) && !empty($warehouseIds)) {
    $safeOrderCodes = array();
    foreach ($orderNumbers as $orderNumber) {
        $safeOrderCodes[] = "'" . mysqli_real_escape_string($finance_connect, (string) $orderNumber) . "'";
    }

    $transferLogTable = defined('ORDER_WAREHOUSE_TRANSFER_LOG') ? ORDER_WAREHOUSE_TRANSFER_LOG : 'order_warehouse_transfer_log';
    $transferLogSql = "SELECT order_code, old_warehouse_id, new_warehouse_id, create_date, create_time
        FROM `" . str_replace('`', '``', $transferLogTable) . "`
        WHERE status = 'A'
          AND order_code IN (" . implode(',', $safeOrderCodes) . ")
          AND old_warehouse_id IN (" . implode(',', array_values($warehouseIds)) . ")
        ORDER BY create_date DESC, create_time DESC, id DESC";
    $transferLogResult = mysqli_query($finance_connect, $transferLogSql);
    if ($transferLogResult) {
        while ($transferLogRow = mysqli_fetch_assoc($transferLogResult)) {
            $mapKey = trim((string) (isset($transferLogRow['order_code']) ? $transferLogRow['order_code'] : '')) . '|' . (int) (isset($transferLogRow['old_warehouse_id']) ? $transferLogRow['old_warehouse_id'] : 0);
            if ($mapKey === '|' || isset($transferLogMap[$mapKey])) {
                continue;
            }
            $transferLogMap[$mapKey] = $transferLogRow;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    preloader(300);

    $(document).ready(() => {
        if ($('#stockMovementTable').length) {
            $('#stockMovementTable').DataTable({
                "order": [[5, 'desc']],
                "columnDefs": [
                    { "orderable": false, "targets": [2] }
                ],
                "autoWidth": false
            });
            datatableAlignment('stockMovementTable');
        }
    });
</script>

<style>
    
    
    .cell-lines div {
        margin-bottom: 4px;
    }
    .cell-lines div:last-child {
        margin-bottom: 0;
    }
</style>

<body>
    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">

                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= siEsc($pageTitle) ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= siEsc($pageTitle) ?></h2>
                            <div class="mt-auto mb-auto"></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($groupedRows)) { ?>
                    <table class="table table-striped" id="stockMovementTable">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" width="90px">Action</th>
                                <th scope="col">Warehouse</th>
                                <th scope="col">Product + Quantity</th>
                                <th scope="col">Stock Out Date</th>
                                <th scope="col">Stock Out Order Number</th>
                                <th scope="col">Source Stock In Order Number</th>
                                <th scope="col">Source Stock In Date</th>
                                <th scope="col">Created By</th>
                                <th scope="col">Created Date / Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $sn = 1;
                            foreach ($groupedRows as $row) {
                                $warehouseName = isset($warehouseNameMap[(int) $row['warehouse_id']]) ? $warehouseNameMap[(int) $row['warehouse_id']] : '';
                                $productLines = siBuildProductQtyLines(isset($row['items']) ? $row['items'] : array(), $productNameMap);
                                if (empty($productLines)) {
                                    $productLines = array('Not recorded');
                                }

                                $usageLinkRows = array();
                                $usageDateLines = array();
                                if (!empty($row['usage_rows'])) {
                                    foreach ($row['usage_rows'] as $usageRow) {
                                        $sourceStockInOrderId = isset($usageRow['stock_in_order_id']) ? (int) $usageRow['stock_in_order_id'] : 0;
                                        $sourceStockInOrderNumber = trim((string) (isset($usageRow['stock_in_order_number']) ? $usageRow['stock_in_order_number'] : ''));
                                        if ($sourceStockInOrderNumber === '') {
                                            $sourceStockInOrderNumber = $sourceStockInOrderId > 0 ? (string) $sourceStockInOrderId : '-';
                                        }
                                        $usageLinkRows[] = array(
                                            'label' => $sourceStockInOrderNumber,
                                            'url' => $sourceStockInOrderId > 0 ? ($stockFormPage . '?order_id=' . $sourceStockInOrderId) : '',
                                        );
                                        $usageDateLines[] = trim((string) (isset($usageRow['stock_in_order_date']) ? $usageRow['stock_in_order_date'] : ''));
                                    }
                                } else {
$transferOrderNumber = isset($row['order_number']) ? trim((string) $row['order_number']) : '';
$transferMapKey = $transferOrderNumber . '|' . (int) $row['warehouse_id'];
$transferLogRow = isset($transferLogMap[$transferMapKey]) ? $transferLogMap[$transferMapKey] : null;
                                    if (is_array($transferLogRow)) {
                                        $transferWarehouseId = isset($transferLogRow['new_warehouse_id']) ? (int) $transferLogRow['new_warehouse_id'] : 0;
                                        $transferWarehouseName = isset($warehouseNameMap[$transferWarehouseId]) ? $warehouseNameMap[$transferWarehouseId] : ('Warehouse #' . $transferWarehouseId);
                                        $transferDate = trim((string) (isset($transferLogRow['create_date']) ? $transferLogRow['create_date'] : ''));
                                        $usageLinkRows[] = array(
                                            'label' => 'Stock has already been transferred to ' . $transferWarehouseName,
                                            'url' => '',
                                        );
                                        $usageDateLines[] = $transferDate !== '' ? ('Stock Transfer Date: ' . $transferDate) : 'Stock Transfer Date: -';
                                    } else {
                                        $usageLinkRows[] = array(
                                            'label' => 'Not recorded',
                                            'url' => '',
                                        );
                                        $usageDateLines[] = '-';
                                    }
                                }

                                $createdByKey = isset($row['create_by']) ? (string) $row['create_by'] : '';
                                $createdByName = $createdByKey !== '' && isset($userNameMap[$createdByKey]) ? $userNameMap[$createdByKey] : $createdByKey;
                                $createdDateTime = trim(((string) $row['create_date']) . ' ' . ((string) $row['create_time']));
                                if ($createdDateTime === '') {
                                    $createdDateTime = '-';
                                }

                                $orderNumber = isset($row['order_number']) ? (string) $row['order_number'] : '';
                                $orderLinkMeta = isset($sourceOrderLinkMap[$orderNumber]) ? $sourceOrderLinkMap[$orderNumber] : array('url' => '');
                                $orderViewUrl = isset($orderLinkMeta['url']) ? trim((string) $orderLinkMeta['url']) : '';
                                $orderPlatform = isset($orderLinkMeta['platform']) ? (string) $orderLinkMeta['platform'] : '';
                                $sourceOrderId = isset($orderLinkMeta['order_id']) ? (int) $orderLinkMeta['order_id'] : 0;
                                if ($orderPlatform !== '' && $sourceOrderId > 0) {
                                    $orderViewUrl = (string) shopeeOmsGetOrderSourceViewUrl($orderPlatform, $sourceOrderId, array());
                                }
                                ?>
                                <tr>
                                    <td class="hideColumn"><?= (int) $row['order_id'] ?></td>
                                    <th scope="row"><?= $sn++ ?></th>
                                    <td class="btn-container">
                                        <?php if (isActionAllowed('View', $pinAccess)) { ?>
                                            <a class="btn btn-sm btn-rounded btn-primary" href="<?= $stockFormPage ?>?order_id=<?= (int) $row['order_id'] ?>" title="View"><i class="fa-solid fa-eye"></i></a>
                                        <?php } else { ?>
                                            <span>-</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= siEsc($warehouseName !== '' ? $warehouseName : '-') ?></td>
                                    <td class="cell-lines">
                                        <?php foreach ($productLines as $line) { ?>
                                            <div><?= siEsc($line) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td><?= siEsc(isset($row['stock_out_date']) ? $row['stock_out_date'] : '') ?></td>
                                    <td>
                                        <?php if ($orderViewUrl !== '') { ?>
                                            <a href="<?= siEsc($orderViewUrl) ?>"><?= siEsc($orderNumber) ?></a>
                                        <?php } else { ?>
                                            <?= siEsc($orderNumber !== '' ? $orderNumber : '-') ?>
                                        <?php } ?>
                                    </td>
                                    <td class="cell-lines">
                                        <?php foreach ($usageLinkRows as $usageLinkRow) { ?>
                                            <div>
                                                <?php if (isset($usageLinkRow['url']) && $usageLinkRow['url'] !== '') { ?>
                                                    <a href="<?= siEsc($usageLinkRow['url']) ?>"><?= siEsc(isset($usageLinkRow['label']) ? $usageLinkRow['label'] : '-') ?></a>
                                                <?php } else { ?>
                                                    <?= siEsc(isset($usageLinkRow['label']) ? $usageLinkRow['label'] : '-') ?>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td class="cell-lines">
                                        <?php foreach ($usageDateLines as $line) { ?>
                                            <div><?= siEsc($line !== '' ? $line : '-') ?></div>
                                        <?php } ?>
                                    </td>
                                    <td><?= siEsc($createdByName !== '' ? $createdByName : '-') ?></td>
                                    <td><?= siEsc($createdDateTime) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" width="90px">Action</th>
                                <th scope="col">Warehouse</th>
                                <th scope="col">Product + Quantity</th>
                                <th scope="col">Stock Out Date</th>
                                <th scope="col">Stock Out Order Number</th>
                                <th scope="col">Source Stock In Order Number</th>
                                <th scope="col">Source Stock In Date</th>
                                <th scope="col">Created By</th>
                                <th scope="col">Created Date / Time</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } else { ?>
                    <div class="text-center"><h4>No records found</h4></div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        var page = <?= json_encode($pageTitle) ?>;
        var action = '';

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        setButtonColor();
    </script>

</body>

</html>
