<?php
$pageTitle = 'Stock In Import';

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
siEnsureSchema($finance_connect, $stockInOrderTable, $stockInItemTable);

$tablePage = $SITEURL . '/warehouse_stock_in_table.php';

$pinAccess = checkCurrentPin($connect, 'Stock In');
if (!is_array($pinAccess)) {
    $pinAccess = array();
}
if (!isActionAllowed('Import', $pinAccess)) {
    echo "<script>alert('You do not have permission to import this page.');location.href='" . $tablePage . "';</script>";
    exit;
}

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

$msg = '';
$err = '';

if (!function_exists('siNormalizeImportedDate')) {
    function siNormalizeImportedDate($value)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        // Excel date serial (days since 1899-12-30, including Excel leap-year bug behavior).
        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial > 0 && $serial < 2958465) {
                $timestamp = (int) round(($serial - 25569) * 86400);
                if ($timestamp > 0) {
                    return gmdate('Y-m-d', $timestamp);
                }
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return $raw;
    }
}

if (!function_exists('siDateForDisplay')) {
    function siDateForDisplay($value)
    {
        $normalized = siNormalizeImportedDate($value);
        if ($normalized === '') {
            return '';
        }
        $ts = strtotime($normalized);
        if ($ts === false) {
            return $normalized;
        }
        return date('Y-m-d', $ts);
    }
}

if (post('actionBtn') === 'cancelImport') {
    unset($_SESSION['si_import_preview']);
    echo "<script>location.href='" . $tablePage . "';</script>";
    exit;
}

if (post('actionBtn') === 'confirmImport') {
    $previewData = isset($_SESSION['si_import_preview']) ? $_SESSION['si_import_preview'] : null;
    if (!$previewData || !isset($previewData['entries']) || !is_array($previewData['entries'])) {
        $err = 'No import preview to confirm.';
    } else {
        mysqli_begin_transaction($finance_connect);
        $updated = 0;
        $inserted = 0;
        $skippedError = 0; // NEW: Track skipped error rows

        try {
            foreach ($previewData['entries'] as $entry) {
                $type = isset($entry['type']) ? $entry['type'] : '';
                if ($type === 'error') {
                    $skippedError++; // NEW: Increment skipped count
                    continue;
                }

                $after = isset($entry['after']) && is_array($entry['after']) ? $entry['after'] : array();
                $warehouseId = isset($after['warehouse_id']) ? (int) $after['warehouse_id'] : 0;
                $stockInDate = isset($after['stock_in_date']) ? trim((string) $after['stock_in_date']) : '';
                $orderNumber = isset($after['order_number']) ? trim((string) $after['order_number']) : '';
                $productId = isset($after['product_id']) ? (int) $after['product_id'] : 0;
                $qty = isset($after['product_quantity']) ? (int) $after['product_quantity'] : 0;

                if ($warehouseId <= 0 || $stockInDate === '' || $orderNumber === '' || $productId <= 0 || $qty <= 0) {
                    continue;
                }

                if ($type === 'modified') {
                    $itemId = isset($entry['item_id']) ? (int) $entry['item_id'] : 0;
                    $orderId = isset($entry['order_id']) ? (int) $entry['order_id'] : 0;
                    if ($itemId <= 0 || $orderId <= 0) {
                        continue;
                    }

                    $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
                    $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);
                    $uOrder = "UPDATE `" . $stockInOrderTable . "` SET warehouse_id='" . $warehouseId . "', stock_in_date='" . $safeDate . "', order_number='" . $safeOrderNo . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $orderId . "'";
                    mysqli_query($finance_connect, $uOrder);

                    $uItem = "UPDATE `" . $stockInItemTable . "` SET product_id='" . $productId . "', package_id='0', product_quantity='" . $qty . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $itemId . "'";
                    mysqli_query($finance_connect, $uItem);
                    $updated++;
                }

                if ($type === 'new') {
                    $orderId = siFindOrderIdByFields($finance_connect, $stockInOrderTable, $warehouseId, $stockInDate, $orderNumber);
                    if ($orderId <= 0) {
                        $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
                        $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);
                        $iOrder = "INSERT INTO `" . $stockInOrderTable . "` (warehouse_id, order_number, stock_in_date, create_by, create_date, create_time, status) VALUES ('" . $warehouseId . "', '" . $safeOrderNo . "', '" . $safeDate . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                        mysqli_query($finance_connect, $iOrder);
                        $orderId = (int) mysqli_insert_id($finance_connect);
                    }

                    $iItem = "INSERT INTO `" . $stockInItemTable . "` (stock_in_order_id, product_id, package_id, product_quantity, create_by, create_date, create_time, status) VALUES ('" . $orderId . "', '" . $productId . "', '0', '" . $qty . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                    mysqli_query($finance_connect, $iItem);
                    $inserted++;
                }
            }

            mysqli_commit($finance_connect);
            unset($_SESSION['si_import_preview']);
            
            // Build the final feedback message
            $finalMsg = 'Import completed. Updated: ' . $updated . ', New Added: ' . $inserted;
            if ($skippedError > 0) {
                $finalMsg .= ', Skipped Errors: ' . $skippedError;
            }
            
            echo "<script>location.href='" . $tablePage . "?msg=" . urlencode($finalMsg) . "';</script>";
            exit;
        } catch (Exception $ex) {
            mysqli_rollback($finance_connect);
            $err = 'Import failed: ' . $ex->getMessage();
        }
    }
}

if (post('actionBtn') === 'importPreview') {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Please choose an Excel file first.';
    } else {
        $name = strtolower((string) $_FILES['import_file']['name']);
        // Strictly only allow .xlsx and .xls extensions
        if (!(substr($name, -5) === '.xlsx' || substr($name, -4) === '.xls')) {
            $err = 'Unsupported file type. Please upload a valid .xlsx or .xls Excel file.';
        } else {
            $importRows = siParseExcelLikeRows($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name']);
            if (count($importRows) === 0) {
                $err = 'No rows found in uploaded file.';
            } else {
                $currentRows = siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable);
                $currentByItemId = array();
                foreach ($currentRows as $r) {
                    $currentByItemId[(int) $r['item_id']] = $r;
                }

                $entries = array();
                $changedCount = 0;
                $newCount = 0;
                $unchangedCount = 0;
                $errorCount = 0;

                foreach ($importRows as $r) {
                    $itemId = isset($r['item id']) ? (int) $r['item id'] : (isset($r['item_id']) ? (int) $r['item_id'] : 0);
                    $warehouseName = trim((string) (isset($r['warehouse']) ? $r['warehouse'] : ''));
                    $stockInDateRaw = trim((string) (isset($r['stock in date']) ? $r['stock in date'] : (isset($r['stock_in_date']) ? $r['stock_in_date'] : '')));
                    $stockInDate = siNormalizeImportedDate($stockInDateRaw);
                    $orderNumber = trim((string) (isset($r['order number']) ? $r['order number'] : (isset($r['order_number']) ? $r['order_number'] : '')));
                    $productName = trim((string) (isset($r['product name']) ? $r['product name'] : (isset($r['product_name']) ? $r['product_name'] : '')));
                    $qty = (int) (isset($r['product quantity']) ? $r['product quantity'] : (isset($r['product_quantity']) ? $r['product_quantity'] : 0));

                    if ($warehouseName === '' && $stockInDate === '' && $orderNumber === '' && $productName === '' && $qty <= 0) {
                        continue;
                    }

                    $warehouseId = isset($warehouseNameToId[strtolower($warehouseName)]) ? (int) $warehouseNameToId[strtolower($warehouseName)] : 0;
                    $productId = isset($productNameToId[strtolower($productName)]) ? (int) $productNameToId[strtolower($productName)] : 0;

                    $notes = array();
                    if ($warehouseId <= 0) $notes[] = 'Invalid warehouse';
                    if ($productId <= 0) $notes[] = 'Invalid product';
                    if ($qty <= 0) $notes[] = 'Invalid quantity';
                    if ($stockInDate === '') $notes[] = 'Missing stock in date';
                    if ($orderNumber === '') $notes[] = 'Missing order number';

                    $after = array(
                        'warehouse_id' => $warehouseId,
                        'stock_in_date' => $stockInDate,
                        'order_number' => $orderNumber,
                        'product_id' => $productId,
                        'package_id' => 0,
                        'product_quantity' => $qty,
                    );

                    if ($itemId > 0 && isset($currentByItemId[$itemId])) {
                        $old = $currentByItemId[$itemId];
                        $oldStockInDate = siNormalizeImportedDate(isset($old['stock_in_date']) ? $old['stock_in_date'] : '');

                        $changed = (
                            (int) $old['warehouse_id'] !== (int) $after['warehouse_id'] ||
                            (string) $oldStockInDate !== (string) $after['stock_in_date'] ||
                            (string) $old['order_number'] !== (string) $after['order_number'] ||
                            (int) $old['product_id'] !== (int) $after['product_id'] ||
                            (int) $old['product_quantity'] !== (int) $after['product_quantity']
                        );

                        if (count($notes) > 0) {
                            $entries[] = array(
                                'type' => 'error',
                                'item_id' => $itemId,
                                'order_id' => (int) $old['order_id'],
                                'before' => $old,
                                'after' => $after,
                                'notes' => $notes,
                            );
                            $errorCount++;
                        } else if ($changed) {
                            $entries[] = array(
                                'type' => 'modified',
                                'item_id' => $itemId,
                                'order_id' => (int) $old['order_id'],
                                'before' => $old,
                                'after' => $after,
                                'notes' => array('Modified data'),
                            );
                            $changedCount++;
                        } else {
                            $unchangedCount++;
                        }
                    } else {
                        if (count($notes) > 0) {
                            $entries[] = array(
                                'type' => 'error',
                                'item_id' => 0,
                                'order_id' => 0,
                                'before' => null,
                                'after' => $after,
                                'notes' => $notes,
                            );
                            $errorCount++;
                        } else {
                            $entries[] = array(
                                'type' => 'new',
                                'item_id' => 0,
                                'order_id' => 0,
                                'before' => null,
                                'after' => $after,
                                'notes' => array('New added'),
                            );
                            $newCount++;
                        }
                    }
                }

                if ($changedCount === 0 && $newCount === 0 && $errorCount === 0) {
                    unset($_SESSION['si_import_preview']);
                    $msg = 'No need import. Uploaded file has no changes.';
                } else {
                    $_SESSION['si_import_preview'] = array(
                        'entries' => $entries,
                        'stats' => array(
                            'modified' => $changedCount,
                            'new' => $newCount,
                            'error' => $errorCount,
                            'unchanged' => $unchangedCount,
                        ),
                    );
                    $msg = 'Import preview generated. Please review and confirm update.';
                }
            }
        }
    }
}

$previewData = isset($_SESSION['si_import_preview']) ? $_SESSION['si_import_preview'] : null;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="./css/main.css">
    <style>
        .si-import-wrap .card {
            border: 0;
            box-shadow: 0 .125rem .5rem rgba(0,0,0,.08);
        }
        .si-import-wrap .preview-table {
            min-width: 1100px;
        }
        .si-import-wrap .preview-table thead th {
            background: #2f2a2a;
            color: #fff;
            vertical-align: middle;
        }
        .si-import-wrap .badge-status {
            min-width: 92px;
            display: inline-block;
            text-align: center;
            padding: .5rem .75rem;
            border-radius: .45rem;
            font-weight: 700;
        }
        .si-import-wrap .badge-modified {
            background: #f0b429;
            color: #1f2933;
        }
        .si-import-wrap .badge-new {
            background: #17a34a;
            color: #fff;
        }
        .si-import-wrap .badge-error {
            background: #dc3545;
            color: #fff;
        }
        .si-import-wrap .row-new {
            background: #d1e7dd;
        }
        .si-import-wrap .row-modified {
            border-left: 4px solid #f0b429;
        }
        .si-import-wrap .row-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .si-import-wrap .changed-cell {
            background: #fff3cd;
            color: #664d03;
            font-weight: 700;
        }
        .si-import-wrap .stats-line {
            font-size: 1.05rem;
        }
    </style>
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid d-flex justify-content-center mt-3 si-import-wrap">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <a href="<?= $tablePage ?>">Stock In</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Import</p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2>Import &amp; Bulk Edit Stock In</h2>
                        <div class="mt-auto mb-auto">
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $tablePage ?>"><i class="fa-solid fa-arrow-left"></i> Back To Table</a>
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

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Step 1: Upload Edited Excel File</h5>
                    <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-bold">Select Excel File (.xlsx/.xls)</label>
                            <input class="form-control form-control-lg" type="file" name="import_file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <button class="btn btn-lg btn-rounded btn-primary w-100" name="actionBtn" value="importPreview"><i class="fa-solid fa-magnifying-glass"></i> Scan &amp; Preview File</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($previewData && isset($previewData['entries']) && is_array($previewData['entries'])) { ?>
                <?php $stats = isset($previewData['stats']) ? $previewData['stats'] : array(); ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Preview Changes</h5>
                        <p class="stats-line mb-3">
                            Modified: <strong><?= (int) (isset($stats['modified']) ? $stats['modified'] : 0) ?></strong> |
                            New Added: <strong><?= (int) (isset($stats['new']) ? $stats['new'] : 0) ?></strong> |
                            Unchanged: <strong><?= (int) (isset($stats['unchanged']) ? $stats['unchanged'] : 0) ?></strong> |
                            Error: <strong><?= (int) (isset($stats['error']) ? $stats['error'] : 0) ?></strong>
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle preview-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Item ID</th>
                                        <th>Warehouse</th>
                                        <th>Stock In Date</th>
                                        <th>Order Number</th>
                                        <th>Product Name</th>
                                        <th>Product Quantity</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($previewData['entries'] as $entry) {
                                    $type = isset($entry['type']) ? $entry['type'] : '';
                                    $before = isset($entry['before']) ? $entry['before'] : null;
                                    $after = isset($entry['after']) ? $entry['after'] : array();
                                    $notes = isset($entry['notes']) && is_array($entry['notes']) ? implode(', ', $entry['notes']) : '';

                                    $wBefore = $before ? (isset($warehouseNameMap[(int) $before['warehouse_id']]) ? $warehouseNameMap[(int) $before['warehouse_id']] : '') : '';
                                    $wAfter = isset($warehouseNameMap[(int) $after['warehouse_id']]) ? $warehouseNameMap[(int) $after['warehouse_id']] : '';

                                    $pBefore = $before ? (isset($productNameMap[(int) $before['product_id']]) ? $productNameMap[(int) $before['product_id']] : '') : '';
                                    $pAfter = isset($productNameMap[(int) $after['product_id']]) ? $productNameMap[(int) $after['product_id']] : '';

                                    $dBefore = $before ? siDateForDisplay((string) $before['stock_in_date']) : '';
                                    $dAfter = isset($after['stock_in_date']) ? siDateForDisplay((string) $after['stock_in_date']) : '';

                                    $oBefore = $before ? (string) $before['order_number'] : '';
                                    $oAfter = isset($after['order_number']) ? (string) $after['order_number'] : '';

                                    $qBefore = $before ? (int) $before['product_quantity'] : 0;
                                    $qAfter = isset($after['product_quantity']) ? (int) $after['product_quantity'] : 0;

                                    $isModified = ($type === 'modified');
                                    $isNew = ($type === 'new');
                                    $isError = ($type === 'error');

                                    $rowClass = '';
                                    if ($isError) {
                                        $rowClass = 'row-error';
                                    } else if ($isNew) {
                                        $rowClass = 'row-new';
                                    } else if ($isModified) {
                                        $rowClass = 'row-modified';
                                    }

                                    $chgWarehouse = ($before && (int) $before['warehouse_id'] !== (int) $after['warehouse_id']);
                                    $chgDate = ($before && siNormalizeImportedDate((string) $before['stock_in_date']) !== siNormalizeImportedDate((string) $after['stock_in_date']));
                                    $chgOrderNo = ($before && (string) $before['order_number'] !== (string) $after['order_number']);
                                    $chgProduct = ($before && (int) $before['product_id'] !== (int) $after['product_id']);
                                    $chgQty = ($before && (int) $before['product_quantity'] !== (int) $after['product_quantity']);
                                ?>
                                    <tr class="<?= siEsc($rowClass) ?>">
                                        <td>
                                            <?php if ($isModified) { ?>
                                                <span class="badge-status badge-modified">MODIFIED</span>
                                            <?php } else if ($isNew) { ?>
                                                <span class="badge-status badge-new">NEW</span>
                                            <?php } else if ($isError) { ?>
                                                <span class="badge-status badge-error">ERROR</span>
                                            <?php } else { ?>
                                                <span class="badge-status bg-secondary text-white"><?= siEsc(strtoupper($type)) ?></span>
                                            <?php } ?>
                                        </td>
                                        <td><?= (int) (isset($entry['item_id']) ? $entry['item_id'] : 0) ?></td>
                                        <td class="<?= $chgWarehouse ? 'changed-cell' : '' ?>"><?= siEsc($before ? ($wBefore . ' -> ' . $wAfter) : $wAfter) ?></td>
                                        <td class="<?= $chgDate ? 'changed-cell' : '' ?>"><?= siEsc($before ? ($dBefore . ' -> ' . $dAfter) : $dAfter) ?></td>
                                        <td class="<?= $chgOrderNo ? 'changed-cell' : '' ?>"><?= siEsc($before ? ($oBefore . ' -> ' . $oAfter) : $oAfter) ?></td>
                                        <td class="<?= $chgProduct ? 'changed-cell' : '' ?>"><?= siEsc($before ? ($pBefore . ' -> ' . $pAfter) : $pAfter) ?></td>
                                        <td class="<?= $chgQty ? 'changed-cell' : '' ?>"><?= siEsc($before ? ($qBefore . ' -> ' . $qAfter) : $qAfter) ?></td>
                                        <td><?= siEsc($notes) ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <form method="post" class="d-flex justify-content-center gap-2 flex-wrap mt-3">
                            <button class="btn btn-lg btn-rounded btn-primary px-4" name="actionBtn" value="confirmImport">Confirm Update</button>
                            <button class="btn btn-lg btn-rounded btn-secondary px-4" name="actionBtn" value="cancelImport">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    var page = <?= json_encode('Stock In') ?>;
    var action = '';
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);
</script>
</body>
</html>
