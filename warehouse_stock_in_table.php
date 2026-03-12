<?php
$pageTitle = 'Stock In';

include_once 'include/connection.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
siEnsureSchema($finance_connect, $stockInOrderTable, $stockInItemTable);

$formPage = $SITEURL . '/warehouse_stock_in.php';
$importPage = $SITEURL . '/warehouse_stock_in_import.php';
$tablePage = $SITEURL . '/warehouse_stock_in_table.php';

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

$msg = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$err = isset($_GET['err']) ? trim((string) $_GET['err']) : '';

if (input('export') === 'excel') {
    $rows = siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    siExportExcel($rows, $warehouseNameMap, $productNameMap);
}

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$pinAccess = checkCurrentPin($connect, 'Stock In');
if (!is_array($pinAccess)) {
    $pinAccess = array();
}

if (input('act') === 'D' && input('item_id')) {
    $itemId = (int) input('item_id');
    $deleteQuery = "UPDATE `" . $stockInItemTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $itemId . "'";
    
    if (mysqli_query($finance_connect, $deleteQuery)) {
        echo "<script>location.href='" . $tablePage . "?msg=" . urlencode('Row deleted successfully.') . "';</script>";
    } else {
        echo "<script>location.href='" . $tablePage . "?err=" . urlencode('Failed to delete row: ' . mysqli_error($finance_connect)) . "';</script>";
    }
    exit;
}

$listRows = siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="./css/main.css">
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= siEsc($pageTitle) ?></p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2><?= siEsc($pageTitle) ?></h2>
                        <div class="mt-auto mb-auto d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $formPage ?>">Add Stock In</a>
                            <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $importPage ?>">Import</a>
                            <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $tablePage ?>?export=excel">Export</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($msg !== '') { ?>
                <div class="alert alert-success"><?= siEsc($msg) ?></div>
            <?php } ?>
            <?php if ($err !== '') { ?>
                <div class="alert alert-danger"><?= siEsc($err) ?></div>
            <?php } ?>

            <div class="table-responsive">
                <table class="table table-striped" id="stockInListTable">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Action</th>
                            <th>Warehouse</th>
                            <th>Product Name</th>
                            <th>Product Quantity</th>
                            <th>Stock In Date</th>
                            <th>Order Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($listRows as $row) {
                            $warehouseName = isset($warehouseNameMap[(int) $row['warehouse_id']]) ? $warehouseNameMap[(int) $row['warehouse_id']] : '';
                            $productName = isset($productNameMap[(int) $row['product_id']]) ? $productNameMap[(int) $row['product_id']] : '';
                        ?>
                            <tr>
                                <td><?= $sn++ ?></td>
                                <td class="btn-container">
                                    <a class="btn btn-sm btn-rounded btn-primary" href="<?= $formPage ?>?act=V&item_id=<?= (int) $row['item_id'] ?>" title="View"><i class="fa-solid fa-eye"></i></a>
                                    <a class="btn btn-sm btn-rounded btn-warning" href="<?= $formPage ?>?act=E&item_id=<?= (int) $row['item_id'] ?>" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a class="btn btn-sm btn-rounded btn-danger" href="<?= $tablePage ?>?act=D&item_id=<?= (int) $row['item_id'] ?>" onclick="return confirm('Delete this row?');" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                </td>
                                <td><?= siEsc($warehouseName) ?></td>
                                <td><?= siEsc($productName) ?></td>
                                <td><?= (int) $row['product_quantity'] ?></td>
                                <td><?= siEsc($row['stock_in_date']) ?></td>
                                <td><?= siEsc($row['order_number']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    var page = <?= json_encode($pageTitle) ?>;
    var action = '';
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    datatableAlignment('stockInListTable');
    setButtonColor();
    preloader(300);
</script>
</body>
</html>