<?php
ob_start();
$pageTitle = 'Stock In';
$currentPagePin = 125;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';

$formPage = $SITEURL . '/stock/warehouse_stock_in.php';
$importPage = $SITEURL . '/import/warehouse_stock_in_import.php';
$tablePage = $SITEURL . '/stock/warehouse_stock_in_table.php';

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$packages = siLoadPackages($connect);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);
list($packageNameMap, $packageNameToId) = siBuildNameMaps($packages);
$listPageSkipSessionReset = true;
$listPageSkipNumbering = true;


include_once '../include/list_page_header.php';
$msg = isset($_GET['msg']) ? trim((string) $_GET['msg']) : '';
$err = isset($_GET['err']) ? trim((string) $_GET['err']) : '';

if (!function_exists('siFetchAssocRows')) {
    function siFetchAssocRows($financeConnect, $cmsConnect, $orderTable, $itemTable, $warehouseNameMap, $productNameMap, $packageNameMap, $selectedOrderIds = array())
    {
        $orderCols = array();
        $itemCols = array();
        $userMap = array();

        $rstUsers = mysqli_query($cmsConnect, "SELECT id, name FROM " . USR_USER . " WHERE status='A'");
        if ($rstUsers) {
            while ($usr = mysqli_fetch_assoc($rstUsers)) {
                $userMap[(int) $usr['id']] = (string) $usr['name'];
            }
        }

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
            if (strtolower($col) === 'status') {
                continue;
            }
            $selectParts[] = "o.`" . $col . "` AS `order_" . $col . "`";
        }
        foreach ($itemCols as $col) {
            if (strtolower($col) === 'status') {
                continue;
            }
            $selectParts[] = "i.`" . $col . "` AS `item_" . $col . "`";
        }

        if (empty($selectParts)) {
            return array();
        }

        $where = "WHERE o.status='A' AND i.status='A' AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') <> 'Stock Out'";
        if (!empty($selectedOrderIds)) {
            $ids = array_filter(array_map('intval', $selectedOrderIds), function ($v) {
                return $v > 0;
            });
            if (!empty($ids)) {
                $where .= " AND o.id IN (" . implode(',', $ids) . ")";
            }
        }

        $sql = "SELECT " . implode(', ', $selectParts) . "
                FROM `" . $orderTable . "` o
                INNER JOIN `" . $itemTable . "` i ON i.stock_in_order_id=o.id
                " . $where . "
                ORDER BY o.id DESC, i.id ASC";

        $rows = array();
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (isset($row['order_warehouse_id'])) {
                    $wid = (int) $row['order_warehouse_id'];
                    if (isset($warehouseNameMap[$wid])) {
                        $row['order_warehouse_id'] = (string) $warehouseNameMap[$wid];
                    }
                }
                if (isset($row['item_product_id'])) {
                    $rawProductIds = (string) $row['item_product_id'];
                     $productIdParts = array_map('trim', explode(',', $rawProductIds));
                     $productNames = array();
                     foreach ($productIdParts as $productIdPart) {
                         if ($productIdPart === '') {
                             continue;
                         }
                         $pid = (int) $productIdPart;
                         if (isset($productNameMap[$pid])) {
                             $productNames[] = (string) $productNameMap[$pid];
                         } else {
                             // Fallback to the original ID token if no name is found.
                             $productNames[] = $productIdPart;
                         }
                     }
                     if (!empty($productNames)) {
                         $row['item_product_id'] = implode(', ', $productNames);
                     } else {
                         // If nothing could be resolved, keep the original value.
                         $row['item_product_id'] = $rawProductIds;
                     }
                }
                if (isset($row['item_package_id'])) {
                    $rawPackageIds = (string) $row['item_package_id'];
                    $packageIdParts = array_map('trim', explode(',', $rawPackageIds));
                    $packageNames = array();
                    foreach ($packageIdParts as $packageIdPart) {
                        if ($packageIdPart === '') {
                            continue;
                        }
                        $pkgId = (int) $packageIdPart;
                        if (isset($packageNameMap[$pkgId])) {
                            $packageNames[] = (string) $packageNameMap[$pkgId];
                        } else {
                            $packageNames[] = $packageIdPart;
                        }
                    }
                    if (!empty($packageNames)) {
                        $row['item_package_id'] = implode(', ', $packageNames);
                    } else {
                        $row['item_package_id'] = $rawPackageIds;
                    }
                }

                foreach ($row as $key => $value) {
                    $normalizedKey = strtolower((string) $key);
                    if ($normalizedKey === 'order_create_by' || $normalizedKey === 'order_update_by' || $normalizedKey === 'item_create_by' || $normalizedKey === 'item_update_by') {
                        $uid = (int) $value;
                        if (isset($userMap[$uid])) {
                            $row[$key] = (string) $userMap[$uid];
                        }
                    }
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('siExportAssocExcel')) {
    function siExportAssocExcel($rows, $filePrefix, $savePath = '')
    {
        if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
            include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
        }

        if (empty($rows)) {
            return false;
        }

        $exportHeaders = array();
        $displayHeaders = array();
        foreach (array_keys($rows[0]) as $header) {
            $headerLower = strtolower((string) $header);

            // Internal IDs are not part of import form and should not be exported.
            if ($headerLower === 'item_id' || $headerLower === 'item_stock_in_order_id') {
                continue;
            }

            // Keep only one audit column set in export.
            // Skip order-level audit fields and keep the later item-level fields.
            if (
                $headerLower === 'order_create_by' ||
                $headerLower === 'order_create_date' ||
                $headerLower === 'order_create_time' ||
                $headerLower === 'order_update_by' ||
                $headerLower === 'order_update_date' ||
                $headerLower === 'order_update_time'
            ) {
                continue;
            }

            $exportHeaders[] = $header;
            if ($headerLower === 'order_id') {
                $displayHeaders[] = 'S/N';
            } else if ($headerLower === 'order_warehouse_id') {
                $displayHeaders[] = 'WAREHOUSE';
            } else if ($headerLower === 'item_product_id') {
                $displayHeaders[] = 'PRODUCT NAME';
            } else if ($headerLower === 'item_package_id') {
                $displayHeaders[] = 'PACKAGE NAME';
            } else {
                $clean = str_replace(array('order_', 'item_'), '', (string) $header);
                if ($clean === 'id' && strpos((string) $header, 'order_') === 0) {
                    $displayHeaders[] = 'ORDER ID';
                } else {
                    $displayHeaders[] = strtoupper(str_replace('_', ' ', $clean));
                }
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
        if ($savePath !== '') {
            $xlsx->saveAs($savePath);
            return $savePath;
        }
        $xlsx->downloadAs($fileName);
        exit;
    }
}

if (!function_exists('siResolveAttachmentAbsPath')) {
    function siResolveAttachmentAbsPath($rawPath)
    {
        $path = trim(str_replace('\\', '/', (string) $rawPath));
        if ($path === '') {
            return '';
        }

        $normalizedImgServer = trim(str_replace('\\', '/', (string) img_server), '/');
        $normalizedPath = ltrim($path, '/');

        $root = rtrim((string) ROOT, '/\\');
        $imgBase = trim((string) img_server, '/\\');
        $baseName = basename($normalizedPath);

        $candidates = array();
        if (strpos($normalizedPath, $normalizedImgServer . '/') === 0) {
            $candidates[] = $root . '/' . $normalizedPath;
        }
        if (strpos($normalizedPath, 'attachment/') === 0 || strpos($normalizedPath, 'finance/stock_in/') === 0) {
            $candidates[] = $root . '/' . $imgBase . '/' . $normalizedPath;
            $candidates[] = $root . '/' . $normalizedPath;
        }

        // Common legacy variants where only filename was stored.
        $candidates[] = $root . '/' . $imgBase . '/finance/stock_in/' . $baseName;
        $candidates[] = $root . '/' . $imgBase . '/' . $baseName;
        $candidates[] = $root . '/finance/stock_in/' . $baseName;

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        // Return best-guess path for caller logging/diagnostics.
        return $root . '/' . $imgBase . '/finance/stock_in/' . $baseName;
    }
}

if (!function_exists('siBuildAttachmentRelPathInZip')) {
    function siBuildAttachmentRelPathInZip($rawPath)
    {
        $path = trim(str_replace('\\', '/', (string) $rawPath), '/');
        if ($path === '') {
            return '';
        }

        if (strpos($path, '/') !== false) {
            return $path;
        }

        return 'finance/stock_in/' . basename($path);
    }
}

if (!function_exists('siBuildStockInExportZip')) {
    function siBuildStockInExportZip($rows, $excelPath, $zipPath)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFile($excelPath, basename($excelPath));
        $zip->addEmptyDir('attachment');

        $added = array();
        foreach ($rows as $row) {
            $attachmentRaw = isset($row['order_attachment']) ? (string) $row['order_attachment'] : '';
            if ($attachmentRaw === '') {
                continue;
            }

            $parts = siAttachmentDecodeList($attachmentRaw);

            foreach ($parts as $part) {
                $absPath = siResolveAttachmentAbsPath($part);
                if ($absPath === '' || !file_exists($absPath)) {
                    continue;
                }

                $zipRel = 'attachment/' . siBuildAttachmentRelPathInZip($part);
                if (isset($added[$zipRel])) {
                    continue;
                }

                $zip->addFile($absPath, $zipRel);
                $added[$zipRel] = true;
            }
        }

        $zip->close();
        return true;
    }
}

if (!function_exists('siAuditExportAction')) {
    function siAuditExportAction($connect, $pageTitle, $targetTable, $idsText, $cdate, $ctime)
    {
        $log = array(
            'log_act' => 'Export',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'Export IDs: ' . (string) $idsText,
            'query_table' => (string) $targetTable,
            'act_msg' => USER_NAME . ' exported stock in data [<b>ID = ' . htmlspecialchars((string) $idsText, ENT_QUOTES, 'UTF-8') . '</b>] from <b><i>' . $targetTable . ' Table</i></b>.',
            'page' => $pageTitle,
            'connect' => $connect,
        );
        audit_log($log);
    }
}

$exportIdsParam = isset($_GET['ids']) ? preg_replace('/[^0-9,]/', '', (string) $_GET['ids']) : '';
$exportIds = array();
if ($exportIdsParam !== '') {
    $exportIds = array_filter(array_map('intval', explode(',', $exportIdsParam)), function ($v) {
        return $v > 0;
    });
    $exportIds = array_values(array_unique($exportIds));
}

if (input('export') === 'excel') {
    if (!isActionAllowed("Export", $pinAccess)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $tablePage . "';</script>";
        exit;
    }
    if (!empty($exportIds)) {
        $rows = siFetchAssocRows($finance_connect, $connect, $stockInOrderTable, $stockInItemTable, $warehouseNameMap, $productNameMap, $packageNameMap, $exportIds);
        if (empty($rows)) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo "<script>alert('No selected stock-in rows found to export.'); location.href='" . $tablePage . "';</script>";
            exit;
        }
        siAuditExportAction($connect, $pageTitle, $stockInItemTable, implode(',', $exportIds), $cdate, $ctime);
    } else {
        $rows = siFetchAssocRows($finance_connect, $connect, $stockInOrderTable, $stockInItemTable, $warehouseNameMap, $productNameMap, $packageNameMap);
        siAuditExportAction($connect, $pageTitle, $stockInItemTable, 'ALL', $cdate, $ctime);
    }

    $tempDir = rtrim((string) ROOT, '/\\') . '/temp/stock_in_export/';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }

    $stamp = date('Ymd_His');
    $excelPath = $tempDir . 'stock_in_export_' . $stamp . '.xlsx';
    $zipPath = $tempDir . 'stock_in_export_' . $stamp . '.zip';

    siExportAssocExcel($rows, 'stock_in_export', $excelPath);

    if (siBuildStockInExportZip($rows, $excelPath, $zipPath)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zipPath);

        @unlink($excelPath);
        @unlink($zipPath);
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    siExportAssocExcel($rows, 'stock_in_export');
}

if ((post('act') === 'D' && post('id')) || (input('act') === 'D' && (input('order_id') || input('item_id')))) {
    $orderId = post('id') ? (int) post('id') : (int) input('order_id');
    if ($orderId <= 0 && input('item_id')) {
        $itemIdForOrder = (int) input('item_id');
        $orderRst = mysqli_query($finance_connect, "SELECT stock_in_order_id FROM `" . $stockInItemTable . "` WHERE id='" . $itemIdForOrder . "' LIMIT 1");
        if ($orderRst && ($orderRow = mysqli_fetch_assoc($orderRst))) {
            $orderId = (int) $orderRow['stock_in_order_id'];
        }
    }

    $deleteOrderQuery = "UPDATE `" . $stockInOrderTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $orderId . "'";
    $deleteItemsQuery = "UPDATE `" . $stockInItemTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE stock_in_order_id='" . $orderId . "'";

    if ($orderId > 0 && mysqli_query($finance_connect, $deleteOrderQuery) && mysqli_query($finance_connect, $deleteItemsQuery)) {
        if (post('act') === 'D') {
            echo 'OK';
        } else {
            echo "<script>confirmationDialog('', '', '" . addslashes($pageTitle) . "', '', '" . $tablePage . "', 'D');</script>";
        }
    } else {
        // Log detailed database error server-side and show a generic message to the user
         error_log('Stock in delete failed for order ID ' . $orderId . ': ' . mysqli_error($finance_connect));
         if (post('act') === 'D') {
            echo 'Failed to delete row. Please try again later.';
         } else {
            echo "<script>alert('Failed to delete row. Please try again later.'); location.href='" . $tablePage . "';</script>";
         }
    }
    exit;
}

$listRows = siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable, 'Stock In');
$groupedRows = array();
foreach ($listRows as $row) {
    $orderId = (int) $row['order_id'];
    if (!isset($groupedRows[$orderId])) {
        $groupedRows[$orderId] = array(
            'order_id' => $orderId,
            'item_id' => (int) $row['item_id'],
            'warehouse_id' => (int) $row['warehouse_id'],
            'stock_in_date' => (string) $row['stock_in_date'],
            'order_number' => (string) $row['order_number'],
            'stock_type' => isset($row['stock_type']) ? (string) $row['stock_type'] : 'Stock In',
            'items' => array(),
        );
    }

    $groupedRows[$orderId]['items'][] = array(
        'product_id' => (int) $row['product_id'],
        'package_id' => (int) $row['package_id'],
        'qty' => (int) $row['product_quantity'],
    );
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="./css/main.css">
</head>
<body>
    
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
                            <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $formPage ?>?act=I">Add Stock In</a>
                            <?php endif; ?>
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

            <?php if (!empty($groupedRows)) { ?>
            <div class="table-responsive">
                <table class="table table-striped" id="stockInListTable">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" width="100px">Action</th>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Product + Quantity</th>
                            <th scope="col">Stock In Date</th>
                            <th scope="col">Order Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sn = 1; foreach ($groupedRows as $row) {
                            $warehouseName = isset($warehouseNameMap[(int) $row['warehouse_id']]) ? $warehouseNameMap[(int) $row['warehouse_id']] : '';
                            $productLines = siBuildProductQtyLines(isset($row['items']) ? $row['items'] : array(), $productNameMap);
                        ?>
                            <tr>
                                <td class="hideColumn"><?= (int) $row['order_id'] ?></td>
                                <td class="text-center"><input type="checkbox" class="export" value="<?= (int) $row['order_id'] ?>"></td>
                                <th scope="row"><?= $sn++ ?></th>
                                <td class="btn-container">
                                    <?php if (isActionAllowed('View', $pinAccess)) { ?>
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $formPage ?>?order_id=<?= (int) $row['order_id'] ?>" title="View"><i class="fa-solid fa-eye"></i></a>
                                    <?php } ?>
                                    <?php if (isActionAllowed('Edit', $pinAccess)) { ?>
                                        <a class="btn btn-sm btn-rounded btn-warning" href="<?= $formPage ?>?act=E&order_id=<?= (int) $row['order_id'] ?>" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <?php } ?>
                                    <?php if (isActionAllowed('Delete', $pinAccess)) { ?>
                                        <a class="btn btn-sm btn-rounded btn-danger" onclick="confirmationDialog('<?= (int) $row['order_id'] ?>',['<?= siEsc($row['order_number']) ?>','<?= siEsc(implode(', ', $productLines)) ?>'],'<?= siEsc($pageTitle) ?>','<?= $formPage ?>?act=D','<?= $tablePage ?>','D')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                    <?php } ?>
                                </td>
                                <td><?= siEsc($warehouseName) ?></td>
                                <td>
                                    <?php foreach ($productLines as $line) { ?>
                                        <div><?= siEsc($line) ?></div>
                                    <?php } ?>
                                </td>
                                <td><?= siEsc($row['stock_in_date']) ?></td>
                                <td><?= siEsc($row['order_number']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Product + Quantity</th>
                            <th scope="col">Stock In Date</th>
                            <th scope="col">Order Number</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php } else { ?>
                <div class="text-center"><h4>No records found</h4></div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    const page = <?= json_encode($pageTitle) ?>;
    const action = '';
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    // Bypass the custom wrapper and initialize DataTables directly so options apply correctly
    if ($('#stockInListTable').length) {
        $('#stockInListTable').DataTable({
            "order": [[6, 'desc']],
            "columnDefs": [
                { "orderable": false, "targets": [1, 3] }
            ],
            "autoWidth": false
        });
        datatableAlignment('stockInListTable');
    }
    setButtonColor();
    
</script>
<script src="<?= $SITEURL ?>/js/warehouse_stock_in_table.js"></script>
</body>
</html>
