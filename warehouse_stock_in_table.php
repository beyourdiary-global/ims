<?php
ob_start();
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

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$pinAccess = checkCurrentPin($connect, 'Stock In');
if (!is_array($pinAccess)) {
    $pinAccess = array();
}

$msg = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$err = isset($_GET['err']) ? trim((string) $_GET['err']) : '';

if (!function_exists('siFetchAssocRows')) {
    function siFetchAssocRows($financeConnect, $orderTable, $itemTable, $selectedItemIds = array())
    {
        $orderCols = array();
        $itemCols = array();

        $rstOrderCols = mysqli_query($financeConnect, "SHOW COLUMNS FROM `" . $orderTable . "`");
        if ($rstOrderCols) {
            while ($row = mysqli_fetch_assoc($rstOrderCols)) {
                $orderCols[] = (string) $row['Field'];
            }
        }

        $rstItemCols = mysqli_query($financeConnect, "SHOW COLUMNS FROM `" . $itemTable . "`");
        if ($rstItemCols) {
            while ($row = mysqli_fetch_assoc($rstItemCols)) {
                $itemCols[] = (string) $row['Field'];
            }
        }

        $selectParts = array();
        foreach ($orderCols as $col) {
            $selectParts[] = "o.`" . $col . "` AS `order_" . $col . "`";
        }
        foreach ($itemCols as $col) {
            $selectParts[] = "i.`" . $col . "` AS `item_" . $col . "`";
        }

        if (empty($selectParts)) {
            return array();
        }

        $where = "WHERE o.status='A' AND i.status='A'";
        if (!empty($selectedItemIds)) {
            $ids = array_filter(array_map('intval', $selectedItemIds), function ($v) {
                return $v > 0;
            });
            if (!empty($ids)) {
                $where .= " AND i.id IN (" . implode(',', $ids) . ")";
            }
        }

        $sql = "SELECT " . implode(', ', $selectParts) . "
                FROM `" . $orderTable . "` o
                INNER JOIN `" . $itemTable . "` i ON i.stock_in_order_id=o.id
                " . $where . "
                ORDER BY o.id DESC, i.id ASC";

        $rows = array();
        $rst = mysqli_query($financeConnect, $sql);
        if ($rst) {
            while ($row = mysqli_fetch_assoc($rst)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('siExportAssocExcel')) {
    function siExportAssocExcel($rows, $filePrefix)
    {
        if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
            include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
        }

        if (empty($rows)) {
            return false;
        }

        $headers = array_keys($rows[0]);
        $exportHeaders = array();
        $displayHeaders = array();

        foreach ($headers as $header) {
            $headerLower = strtolower((string) $header);
            if (substr($headerLower, -7) === '_status') {
                continue;
            }

            $exportHeaders[] = $header;
            if ($headerLower === 'order_id') {
                $displayHeaders[] = 'ORDER S/N';
            } elseif ($headerLower === 'item_id') {
                $displayHeaders[] = 'S/N';
            } else {
                $displayHeaders[] = strtoupper(str_replace('_', ' ', (string) $header));
            }
        }

        $excelData = array();
        $excelData[] = $displayHeaders;

        foreach ($rows as $row) {
            $line = array();
            foreach ($exportHeaders as $header) {
                $line[] = isset($row[$header]) && $row[$header] !== null ? (string) $row[$header] : '';
            }
            $excelData[] = $line;
        }

        $fileName = $filePrefix . '_' . date('Ymd_His') . '.xlsx';
        $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData, 'Stock In');
        $xlsx->downloadAs($fileName);
        exit;
    }
}

$checkboxValues = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';
if (!empty($checkboxValues)) {
    $checkboxValues = preg_replace('/[^0-9,]/', '', (string) $checkboxValues);
    $ids = array_filter(explode(',', $checkboxValues), 'strlen');
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($v) {
        return $v > 0;
    });
    $checkboxValues = implode(',', $ids);
}

if (input('export') === 'excel') {
    if (!isActionAllowed("Export", $pinAccess)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $tablePage . "';</script>";
        exit;
    }
    $rows = siFetchAssocRows($finance_connect, $stockInOrderTable, $stockInItemTable);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    siExportAssocExcel($rows, 'stock_in_export');
}

if (!empty($checkboxValues)) {
    if (!isActionAllowed("Export", $pinAccess)) {
        setcookie('rowID', '', time() - 3600, '/');
        ob_end_clean();
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $tablePage . "';</script>";
        exit;
    }

    $rows = siFetchAssocRows($finance_connect, $stockInOrderTable, $stockInItemTable, explode(',', $checkboxValues));

    setcookie('rowID', '', time() - 3600, '/');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (empty($rows)) {
        echo "<script>alert('No selected stock-in rows found to export.'); location.href='" . $tablePage . "';</script>";
        exit;
    }

    siExportAssocExcel($rows, 'stock_in_export');
}

if (input('act') === 'D' && input('item_id')) {
    $itemId = (int) input('item_id');
    $deleteQuery = "UPDATE `" . $stockInItemTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $itemId . "'";
    
    if (mysqli_query($finance_connect, $deleteQuery)) {
        // FIX: Add JS alert and remove ?msg from URL
        echo "<script>alert('Row deleted successfully.'); location.href='" . $tablePage . "';</script>";
    } else {
        // Log detailed database error server-side and show a generic message to the user
         error_log('Stock in delete failed for item ID ' . $itemId . ': ' . mysqli_error($finance_connect));
         echo "<script>alert('Failed to delete row. Please try again later.'); location.href='" . $tablePage . "';</script>";
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
                            <?php if (isActionAllowed("Import", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $importPage ?>">Import</a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Export", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" name="exportBtn" href="<?= $tablePage ?>">Export</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($msg !== '') { ?>
                <script>alert(<?= json_encode($msg) ?>);</script>
            <?php } ?>
            <?php if ($err !== '') { ?>
                <script>alert(<?= json_encode($err) ?>);</script>
            <?php } ?>

            <div class="table-responsive">
                <table class="table table-striped" id="stockInListTable">
                    <thead>
                        <tr>
                            <th class="hideColumn">ID</th>
                            <th class="text-center"><input type="checkbox" class="exportAll"></th>
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
                                <td class="hideColumn"><?= (int) $row['item_id'] ?></td>
                                <td class="text-center"><input type="checkbox" class="export" value="<?= (int) $row['item_id'] ?>"></td>
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
                    <tfoot>
                        <tr>
                            <th class="hideColumn">ID</th>
                            <th class="text-center"><input type="checkbox" class="exportAll"></th>
                            <th>S/N</th>
                            <th>Action</th>
                            <th>Warehouse</th>
                            <th>Product Name</th>
                            <th>Product Quantity</th>
                            <th>Stock In Date</th>
                            <th>Order Number</th>
                        </tr>
                    </tfoot>
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
<script src="<?= $SITEURL ?>/js/warehouse_stock_in_table.js"></script>
</body>
</html>