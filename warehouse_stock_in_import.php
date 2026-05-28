<?php
$currentPagePin = 125;
$pageTitle = '';

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';

$importPage = $SITEURL . '/warehouse_stock_in_import.php';
$tablePage = $SITEURL . '/warehouse_stock_in_table.php';
$shortcutPage = $SITEURL . '/common_import.php';

$parentPagePinGroupId = 125;
$parentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($parentPageTitle === '') {
    $parentPageTitle = 'Stock In';
}
$breadcrumbTitle = $parentPageTitle . ' Import';
$pageTitle = $breadcrumbTitle;
$pageHeading = 'Import & Bulk Edit ' . $parentPageTitle;

$pinAccess = checkPinByGroupId($connect, $parentPagePinGroupId);
if (!is_array($pinAccess)) {
    $pinAccess = array();
}
if (!isActionAllowed('Import', $pinAccess)) {
    echo "<script>alert('You do not have permission to import this page.');location.href='" . $tablePage . "';</script>";
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $log = [
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);
}

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$packages = siLoadPackages($connect);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);
$packageNameMap = array();
$packageNameToId = array();
if (is_array($packages)) {
    list($packageNameMap, $packageNameToId) = siBuildNameMaps($packages);
}
$warehouseOptions = array_values($warehouseNameMap);
$productOptions = array_values($productNameMap);

$importErrors = array();
$actionBtn = post('actionBtn');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    unset($_SESSION['si_import_preview']);
}


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

if ($actionBtn === 'cancelImport') {
    unset($_SESSION['si_import_preview']);
    echo "<script>location.href='" . $importPage . "';</script>";
    exit;
}

if ($actionBtn === 'confirmImport') {
    $rawRows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : array();
    $postedRows = array();
    foreach ($rawRows as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $cleanRow = array();
        foreach ($row as $field => $value) {
            if (is_string($value)) {
                $cleanRow[$field] = trim($value);
            } else {
                $cleanRow[$field] = $value;
            }
        }
        $postedRows[$idx] = $cleanRow;
    }

        if (!is_array($postedRows) || count($postedRows) === 0) {
            $importErrors[] = 'No import preview to confirm.';
        } else {
            siEnsureStockTypeColumn($finance_connect, $stockInOrderTable);
            $currentRows = siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable);
            $currentByItemId = array();
            foreach ($currentRows as $r) {
                $currentByItemId[(int) $r['item_id']] = $r;
            }

        $rebuildEntries = array();
        $validationErrorCount = 0;
        $validationModifiedCount = 0;
        $validationNewCount = 0;
        foreach ($postedRows as $row) {
            $itemId = isset($row['item_id']) ? (int) $row['item_id'] : 0;
            $orderId = isset($row['order_id']) ? (int) $row['order_id'] : 0;

            $warehouseRaw = trim((string) (isset($row['warehouse']) ? $row['warehouse'] : ''));
            $stockInDateRaw = trim((string) (isset($row['stock_in_date']) ? $row['stock_in_date'] : ''));
            $stockInDate = siNormalizeImportedDate($stockInDateRaw);
            $orderNumber = trim((string) (isset($row['order_number']) ? $row['order_number'] : ''));
            $productRaw = trim((string) (isset($row['product_name']) ? $row['product_name'] : ''));
            $qtyRaw = trim((string) (isset($row['product_quantity']) ? $row['product_quantity'] : ''));
            $qty = (int) $qtyRaw;
            $packageNameRaw = trim((string) (isset($row['package_name']) ? $row['package_name'] : ''));

            $warehouseId = 0;
            if ($warehouseRaw !== '' && ctype_digit($warehouseRaw) && isset($warehouseNameMap[(int) $warehouseRaw])) {
                $warehouseId = (int) $warehouseRaw;
            } else if (isset($warehouseNameToId[strtolower($warehouseRaw)])) {
                $warehouseId = (int) $warehouseNameToId[strtolower($warehouseRaw)];
            }

            $productId = 0;
            if ($productRaw !== '' && ctype_digit($productRaw) && isset($productNameMap[(int) $productRaw])) {
                $productId = (int) $productRaw;
            } else if (isset($productNameToId[strtolower($productRaw)])) {
                $productId = (int) $productNameToId[strtolower($productRaw)];
            }

            $fieldErrors = array();
            if ($warehouseRaw === '') {
                $fieldErrors['warehouse'] = 'Warehouse field is required!';
            } else if ($warehouseId <= 0) {
                $fieldErrors['warehouse'] = 'Warehouse not found in database.';
            }
            if ($productRaw === '') {
                $fieldErrors['product_name'] = 'Product Name field is required!';
            } else if ($productId <= 0) {
                $fieldErrors['product_name'] = 'Product not found in database.';
            }
            if ($qtyRaw === '') {
                $fieldErrors['product_quantity'] = 'Product Quantity field is required!';
            } else if ($qty <= 0) {
                $fieldErrors['product_quantity'] = 'Quantity must be greater than 0.';
            }
            if ($stockInDateRaw === '') {
                $fieldErrors['stock_in_date'] = 'Stock In Date field is required!';
            } else if ($stockInDate === '') {
                $fieldErrors['stock_in_date'] = 'Stock In Date format is invalid.';
            }
            if ($orderNumber === '') {
                $fieldErrors['order_number'] = 'Order Number field is required!';
            }

            $before = ($itemId > 0 && isset($currentByItemId[$itemId])) ? $currentByItemId[$itemId] : null;
            $type = ($before ? 'modified' : 'new');

            $rebuildEntries[] = array(
                'type' => $type,
                'item_id' => $itemId,
                'order_id' => $orderId,
                'before' => $before,
                'after' => array(
                    'warehouse_id' => $warehouseId,
                    'warehouse_display' => $warehouseRaw,
                    'stock_in_date' => $stockInDate,
                    'order_number' => $orderNumber,
                    'product_id' => $productId,
                    'product_display' => $productRaw,
                    'package_display' => $packageNameRaw,
                    'product_quantity' => $qty,
                ),
                'field_errors' => $fieldErrors,
                'notes' => array(),
            );

            if (!empty($fieldErrors)) {
                $validationErrorCount++;
            } else if ($type === 'new') {
                $validationNewCount++;
            } else {
                $validationModifiedCount++;
            }
        }

        if ($validationErrorCount > 0) {
            $_SESSION['si_import_preview'] = array(
                'entries' => $rebuildEntries,
                'stats' => array(
                    'modified' => $validationModifiedCount,
                    'new' => $validationNewCount,
                    'error' => $validationErrorCount,
                    'unchanged' => 0,
                ),
            );
            $previewData = $_SESSION['si_import_preview'];
            $importErrors[] = 'Please correct the highlighted field errors before update.';
        } else {
            mysqli_begin_transaction($finance_connect);
            $updated = 0;
            $inserted = 0;

            try {
                foreach ($postedRows as $row) {
                $itemId = isset($row['item_id']) ? (int) $row['item_id'] : 0;
                $orderId = isset($row['order_id']) ? (int) $row['order_id'] : 0;

                $warehouseRaw = trim((string) (isset($row['warehouse']) ? $row['warehouse'] : ''));
                $stockInDate = siNormalizeImportedDate(isset($row['stock_in_date']) ? $row['stock_in_date'] : '');
                $orderNumber = trim((string) (isset($row['order_number']) ? $row['order_number'] : ''));
                $productRaw = trim((string) (isset($row['product_name']) ? $row['product_name'] : ''));
                $qty = isset($row['product_quantity']) ? (int) $row['product_quantity'] : 0;
                $packageNameRaw = trim((string) (isset($row['package_name']) ? $row['package_name'] : ''));

                $warehouseId = 0;
                if ($warehouseRaw !== '' && ctype_digit($warehouseRaw) && isset($warehouseNameMap[(int) $warehouseRaw])) {
                    $warehouseId = (int) $warehouseRaw;
                } else if (isset($warehouseNameToId[strtolower($warehouseRaw)])) {
                    $warehouseId = (int) $warehouseNameToId[strtolower($warehouseRaw)];
                }

                $productId = 0;
                if ($productRaw !== '' && ctype_digit($productRaw) && isset($productNameMap[(int) $productRaw])) {
                    $productId = (int) $productRaw;
                } else if (isset($productNameToId[strtolower($productRaw)])) {
                    $productId = (int) $productNameToId[strtolower($productRaw)];
                }

                if ($warehouseId <= 0 || $stockInDate === '' || $orderNumber === '' || $productId <= 0 || $qty <= 0) {
                    throw new Exception('Validation failed while confirming import.');
                }

                if ($itemId > 0 && isset($currentByItemId[$itemId])) {
                    $old = $currentByItemId[$itemId];
                    $oldStockInDate = siNormalizeImportedDate(isset($old['stock_in_date']) ? $old['stock_in_date'] : '');

                    $changed = (
                        (int) $old['warehouse_id'] !== (int) $warehouseId ||
                        (string) $oldStockInDate !== (string) $stockInDate ||
                        (string) $old['order_number'] !== (string) $orderNumber ||
                        (int) $old['product_id'] !== (int) $productId ||
                        (int) $old['product_quantity'] !== (int) $qty
                    );

                    if (!$changed) {
                        continue;
                    }

                    $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
                    $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);
                    $uOrder = "UPDATE `" . $stockInOrderTable . "` SET warehouse_id='" . $warehouseId . "', stock_in_date='" . $safeDate . "', order_number='" . $safeOrderNo . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $orderId . "'";
                    mysqli_query($finance_connect, $uOrder);

                    $uItem = "UPDATE `" . $stockInItemTable . "` SET product_id='" . $productId . "', product_quantity='" . $qty . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $itemId . "'";
                    mysqli_query($finance_connect, $uItem);
                    $updated++;
                } else {
                    $orderId = siFindOrderIdByFields($finance_connect, $stockInOrderTable, $warehouseId, $stockInDate, $orderNumber);
                    if ($orderId <= 0) {
                        $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
                        $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);
                        $iOrder = "INSERT INTO `" . $stockInOrderTable . "` (warehouse_id, order_number, stock_in_date, stock_type, create_by, create_date, create_time, status) VALUES ('" . $warehouseId . "', '" . $safeOrderNo . "', '" . $safeDate . "', 'Stock In', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
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

                    $log = [
                        'log_act' => 'Import',
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => 'New Added=' . (int) $inserted . '; Updated=' . (int) $updated,
                        'query_table' => 'stock_in_order_item',
                        'newval' => 'Import preview confirmed',
                        'act_msg' => USER_NAME . " imported stock in data [ <b>New Added = " . (int) $inserted . ", Updated = " . (int) $updated . "</b> ] into <b><i>stock_in_order_item Table</i></b>.",
                        'page' => $pageTitle,
                        'connect' => $connect,
                    ];
                    audit_log($log);
            
                    // Build the final feedback message
                    $finalMsg = 'Import completed. New Added: ' . $inserted . ', Updated: ' . $updated;
            
                    echo "<script>location.href='" . $tablePage . "?msg=" . urlencode($finalMsg) . "';</script>";
                    exit;
                } catch (Exception $ex) {
                    mysqli_rollback($finance_connect);
                    $importErrors[] = 'Import failed: ' . $ex->getMessage();
                }
        }
    }
}

if ($actionBtn === 'importPreview') {
    unset($_SESSION['si_import_preview']);

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please choose an Excel file first.';
    } else {
        $name = strtolower((string) $_FILES['import_file']['name']);
        // Strictly only allow .xlsx and .xls extensions
        if (!(substr($name, -5) === '.xlsx' || substr($name, -4) === '.xls')) {
            $importErrors[] = 'Unsupported file type. Please upload a valid .xlsx or .xls Excel file.';
        } else {
            $importRows = siParseExcelLikeRows($_FILES['import_file']['tmp_name'], $_FILES['import_file']['name']);
            if (count($importRows) === 0) {
                $importErrors[] = 'No rows found in uploaded file.';
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

                $normalizeOrderNo = function ($value) {
                    $v = trim((string) $value);
                    if ($v === '') {
                        return '';
                    }
                    if (is_numeric($v)) {
                        return (string) ((float) $v);
                    }
                    return strtolower($v);
                };
                $buildMatchKey = function ($warehouseId, $stockInDate, $orderNumber, $productId, $qty) use ($normalizeOrderNo) {
                    if ((int) $warehouseId <= 0 || (int) $productId <= 0 || (int) $qty <= 0 || trim((string) $stockInDate) === '' || trim((string) $orderNumber) === '') {
                        return '';
                    }
                    return (int) $warehouseId . '|' . (string) $stockInDate . '|' . $normalizeOrderNo($orderNumber) . '|' . (int) $productId . '|' . (int) $qty;
                };
                $matchQueues = array();
                foreach ($currentRows as $existingRow) {
                    $mk = $buildMatchKey(
                        isset($existingRow['warehouse_id']) ? (int) $existingRow['warehouse_id'] : 0,
                        siNormalizeImportedDate(isset($existingRow['stock_in_date']) ? $existingRow['stock_in_date'] : ''),
                        isset($existingRow['order_number']) ? (string) $existingRow['order_number'] : '',
                        isset($existingRow['product_id']) ? (int) $existingRow['product_id'] : 0,
                        isset($existingRow['product_quantity']) ? (int) $existingRow['product_quantity'] : 0
                    );
                    if ($mk === '') {
                        continue;
                    }
                    if (!isset($matchQueues[$mk])) {
                        $matchQueues[$mk] = array();
                    }
                    $matchQueues[$mk][] = $existingRow;
                }

                foreach ($importRows as $r) {
                    $itemId = isset($r['item id']) ? (int) $r['item id'] : (isset($r['item_id']) ? (int) $r['item_id'] : (isset($r['item_item_id']) ? (int) $r['item_item_id'] : 0));
                    $orderId = isset($r['order id']) ? (int) $r['order id'] : (isset($r['order_id']) ? (int) $r['order_id'] : (isset($r['order_order_id']) ? (int) $r['order_order_id'] : (isset($r['s/n']) ? (int) $r['s/n'] : 0)));
                    $warehouseRaw = trim((string) (isset($r['warehouse']) ? $r['warehouse'] : (isset($r['warehouse id']) ? $r['warehouse id'] : (isset($r['order_warehouse_id']) ? $r['order_warehouse_id'] : ''))));
                    $stockInDateRaw = trim((string) (isset($r['stock in date']) ? $r['stock in date'] : (isset($r['stock_in_date']) ? $r['stock_in_date'] : (isset($r['order_stock_in_date']) ? $r['order_stock_in_date'] : ''))));
                    $stockInDate = siNormalizeImportedDate($stockInDateRaw);
                    $orderNumber = trim((string) (isset($r['order number']) ? $r['order number'] : (isset($r['number']) ? $r['number'] : (isset($r['order_number']) ? $r['order_number'] : (isset($r['order_order_number']) ? $r['order_order_number'] : '')))));
                    $productRaw = trim((string) (isset($r['product name']) ? $r['product name'] : (isset($r['product_name']) ? $r['product_name'] : (isset($r['product id']) ? $r['product id'] : (isset($r['item_product_id']) ? $r['item_product_id'] : '')))));
                    $packageRaw = trim((string) (isset($r['package name']) ? $r['package name'] : (isset($r['package_name']) ? $r['package_name'] : (isset($r['item_package_id']) ? $r['item_package_id'] : (isset($r['package id']) ? $r['package id'] : '')))));
                    $qtyRaw = trim((string) (isset($r['product quantity']) ? $r['product quantity'] : (isset($r['product_quantity']) ? $r['product_quantity'] : (isset($r['item_product_quantity']) ? $r['item_product_quantity'] : '0'))));
                    $qty = (int) round((float) str_replace(',', '', $qtyRaw));

                    if ($warehouseRaw === '' && $stockInDate === '' && $orderNumber === '' && $productRaw === '' && $qty <= 0) {
                        continue;
                    }

                    $warehouseId = 0;
                    if ($warehouseRaw !== '' && ctype_digit($warehouseRaw) && isset($warehouseNameMap[(int) $warehouseRaw])) {
                        $warehouseId = (int) $warehouseRaw;
                    } else if (isset($warehouseNameToId[strtolower($warehouseRaw)])) {
                        $warehouseId = (int) $warehouseNameToId[strtolower($warehouseRaw)];
                    }

                    $productId = 0;
                    if ($productRaw !== '' && ctype_digit($productRaw) && isset($productNameMap[(int) $productRaw])) {
                        $productId = (int) $productRaw;
                    } else if (isset($productNameToId[strtolower($productRaw)])) {
                        $productId = (int) $productNameToId[strtolower($productRaw)];
                    }

                    $notes = array();
                    $fieldErrors = array();
                    if ($warehouseRaw === '') {
                        $notes[] = 'Missing warehouse';
                        $fieldErrors['warehouse'] = 'Warehouse field is required!';
                    } else if ($warehouseId <= 0) {
                        $notes[] = 'Invalid warehouse';
                        $fieldErrors['warehouse'] = 'Warehouse not found in database.';
                    }
                    if ($productRaw === '') {
                        $notes[] = 'Missing product';
                        $fieldErrors['product_name'] = 'Product Name field is required!';
                    } else if ($productId <= 0) {
                        $notes[] = 'Invalid product';
                        $fieldErrors['product_name'] = 'Product not found in database.';
                    }
                    if ($qtyRaw === '') {
                        $notes[] = 'Missing quantity';
                        $fieldErrors['product_quantity'] = 'Product Quantity field is required!';
                    } else if ($qty <= 0) {
                        $notes[] = 'Invalid quantity';
                        $fieldErrors['product_quantity'] = 'Quantity must be greater than 0.';
                    }
                    if ($stockInDateRaw === '') {
                        $notes[] = 'Missing stock in date';
                        $fieldErrors['stock_in_date'] = 'Stock In Date field is required!';
                    } else if ($stockInDate === '') {
                        $notes[] = 'Invalid stock in date';
                        $fieldErrors['stock_in_date'] = 'Stock In Date format is invalid.';
                    }
                    if ($orderNumber === '') {
                        $notes[] = 'Missing order number';
                        $fieldErrors['order_number'] = 'Order Number field is required!';
                    }

                    $after = array(
                        'warehouse_id' => $warehouseId,
                        'warehouse_display' => $warehouseRaw,
                        'stock_in_date' => $stockInDate,
                        'order_number' => $orderNumber,
                        'product_id' => $productId,
                        'product_display' => $productRaw,
                        'package_display' => $packageRaw,
                        'product_quantity' => $qty,
                    );

                    if ($itemId <= 0) {
                        $matchKey = $buildMatchKey($after['warehouse_id'], $after['stock_in_date'], $after['order_number'], $after['product_id'], $after['product_quantity']);
                        if ($matchKey !== '' && isset($matchQueues[$matchKey]) && count($matchQueues[$matchKey]) > 0) {
                            $matched = array_shift($matchQueues[$matchKey]);
                            $itemId = isset($matched['item_id']) ? (int) $matched['item_id'] : 0;
                            $orderId = isset($matched['order_id']) ? (int) $matched['order_id'] : 0;
                        }
                    }

                    if ($itemId <= 0 && $orderId > 0) {
                        // Build an index of first item_id by order_id once, then reuse it
                        static $firstItemIdByOrderId = null;
                        if ($firstItemIdByOrderId === null) {
                            $firstItemIdByOrderId = array();
                            foreach ($currentRows as $scanRow) {
                                $scanOrderId = isset($scanRow['order_id']) ? (int) $scanRow['order_id'] : 0;
                                $scanItemId = isset($scanRow['item_id']) ? (int) $scanRow['item_id'] : 0;
                                if ($scanOrderId > 0 && $scanItemId > 0 && !isset($firstItemIdByOrderId[$scanOrderId])) {
                                    $firstItemIdByOrderId[$scanOrderId] = $scanItemId;
                                }
                            }
                        }
                        if (isset($firstItemIdByOrderId[$orderId])) {
                            $itemId = (int) $firstItemIdByOrderId[$orderId];
                        }
                    }

                    if ($itemId > 0 && isset($currentByItemId[$itemId])) {
                        $currentRow = $currentByItemId[$itemId];
                        $currentWarehouseName = isset($warehouseNameMap[(int) $currentRow['warehouse_id']]) ? strtolower(trim((string) $warehouseNameMap[(int) $currentRow['warehouse_id']])) : '';
                        $currentProductName = isset($productNameMap[(int) $currentRow['product_id']]) ? strtolower(trim((string) $productNameMap[(int) $currentRow['product_id']])) : '';

                        $warehouseRawNorm = strtolower(trim((string) $warehouseRaw));
                        $productRawNorm = strtolower(trim((string) $productRaw));

                        // Keep existing IDs when the imported text is the same display value.
                        // This avoids false "modified" rows when duplicate names exist in master data.
                        if ($warehouseRawNorm !== '' && $currentWarehouseName !== '' && $warehouseRawNorm === $currentWarehouseName) {
                            $warehouseId = (int) $currentRow['warehouse_id'];
                            $after['warehouse_id'] = $warehouseId;
                        }
                        if ($productRawNorm !== '' && $currentProductName !== '' && $productRawNorm === $currentProductName) {
                            $productId = (int) $currentRow['product_id'];
                            $after['product_id'] = $productId;
                        }
                    }

                    if ($itemId > 0 && isset($currentByItemId[$itemId])) {
                        $old = $currentByItemId[$itemId];
                        $oldStockInDate = siNormalizeImportedDate(isset($old['stock_in_date']) ? $old['stock_in_date'] : '');

                        $normText = function ($value) {
                            return strtolower(trim((string) $value));
                        };

                        $oldOrderNo = trim((string) (isset($old['order_number']) ? $old['order_number'] : ''));
                        $newOrderNo = trim((string) (isset($after['order_number']) ? $after['order_number'] : ''));

                        $orderNoChanged = false;
                        if ($oldOrderNo !== $newOrderNo) {
                            if (is_numeric($oldOrderNo) && is_numeric($newOrderNo)) {
                                $orderNoChanged = ((float) $oldOrderNo !== (float) $newOrderNo);
                            } else {
                                $orderNoChanged = true;
                            }
                        }

                        $changed = (
                            (int) $old['warehouse_id'] !== (int) $after['warehouse_id'] ||
                            (string) $oldStockInDate !== (string) $after['stock_in_date'] ||
                            $orderNoChanged ||
                            (int) $old['product_id'] !== (int) $after['product_id'] ||
                            (int) $old['product_quantity'] !== (int) $after['product_quantity']
                        );

                        // Fallback comparison for product name text to avoid false positives on name/ID formatting differences.
                        if (!$changed) {
                            $oldProductName = isset($productNameMap[(int) $old['product_id']]) ? (string) $productNameMap[(int) $old['product_id']] : '';
                            $newProductName = (string) $productRaw;
                            if ($oldProductName !== '' && $newProductName !== '' && $normText($oldProductName) !== $normText($newProductName)) {
                                $changed = true;
                            }
                        }

                        if (count($notes) > 0 || $changed) {
                            $entries[] = array(
                                'type' => 'modified',
                                'item_id' => $itemId,
                                'order_id' => (int) $old['order_id'],
                                'before' => $old,
                                'after' => $after,
                                'field_errors' => $fieldErrors,
                                'notes' => count($notes) > 0 ? $notes : array('Modified data'),
                            );
                            if (count($notes) > 0) {
                                $errorCount++;
                            } else {
                                $changedCount++;
                            }
                        } else {
                            $unchangedCount++;
                        }
                    } else {
                        $entries[] = array(
                            'type' => 'new',
                            'item_id' => 0,
                            'order_id' => 0,
                            'before' => null,
                            'after' => $after,
                            'field_errors' => $fieldErrors,
                            'notes' => count($notes) > 0 ? $notes : array('New added'),
                        );
                        if (count($notes) > 0) {
                            $errorCount++;
                        } else {
                            $newCount++;
                        }
                    }
                }

                if ($changedCount === 0 && $newCount === 0 && $errorCount === 0) {
                    unset($_SESSION['si_import_preview']);
                    $importErrors[] = 'No new records or changes detected.';
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
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .highlight-change { background-color: #fff3cd !important; border-color: #ffecb5 !important; color: #664d03 !important; }
        .row-new { background-color: #d1e7dd !important; }
        .row-update { border-left: 4px solid #ffc107 !important; }
        .row-error { border-left: 4px solid #dc3545 !important; }
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
        .si-import-wrap .lookup-input {
            min-width: 180px;
        }
        .si-import-wrap .field-error {
            font-size: 12px;
            color: #dc3545;
            margin-top: 4px;
        }
    </style>
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid mt-3 mb-5 d-flex justify-content-center si-import-wrap">
        <div class="col-12 col-md-11">
            <div class="row mb-3">
                <p>
                    <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    <?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                    <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $tablePage ?>"><i class="fa-solid fa-arrow-left"></i> Back To Table</a>
                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shortcutPage ?>">BACK TO SHORTCUTS</a>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($importErrors)) { ?>
                <div class="alert alert-danger shadow-sm" role="alert">
                    <?php foreach ($importErrors as $error) { echo '<div>- ' . siEsc($error) . '</div>'; } ?>
                </div>
            <?php } ?>
        
            <?php if ($previewData && isset($previewData['entries']) && is_array($previewData['entries'])) { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Preview Changes</h5>
                        <form method="post" autocomplete="off">
                            <?php foreach ($previewData['entries'] as $idx => $entry) {
                                $type = isset($entry['type']) ? $entry['type'] : '';
                                $before = isset($entry['before']) ? $entry['before'] : null;
                                $after = isset($entry['after']) ? $entry['after'] : array();
                                $fieldErrors = isset($entry['field_errors']) && is_array($entry['field_errors']) ? $entry['field_errors'] : array();

                                $wAfter = trim((string) (isset($after['warehouse_display']) ? $after['warehouse_display'] : ''));
                                if ($wAfter === '') {
                                    $wAfter = isset($warehouseNameMap[(int) $after['warehouse_id']]) ? $warehouseNameMap[(int) $after['warehouse_id']] : '';
                                }

                                $pAfter = trim((string) (isset($after['product_display']) ? $after['product_display'] : ''));
                                if ($pAfter === '') {
                                    $pAfter = isset($productNameMap[(int) $after['product_id']]) ? $productNameMap[(int) $after['product_id']] : '';
                                }
                                $oAfter = isset($after['order_number']) ? (string) $after['order_number'] : '';

                                $qAfter = isset($after['product_quantity']) ? (int) $after['product_quantity'] : 0;
                                $pkgAfter = trim((string) (isset($after['package_display']) ? $after['package_display'] : ''));
                                if ($pkgAfter === '' && $before && isset($before['package_id'])) {
                                    $pkgIdBefore = (int) $before['package_id'];
                                    if ($pkgIdBefore > 0 && isset($packageNameMap[$pkgIdBefore])) {
                                        $pkgAfter = (string) $packageNameMap[$pkgIdBefore];
                                    }
                                }

                                $isModified = ($type === 'modified');
                                $isNew = ($type === 'new');
                                $isError = ($type === 'error');

                                $rowClass = '';
                                if ($isError) {
                                    $rowClass = 'row-error';
                                } else if ($isNew) {
                                    $rowClass = 'row-new';
                                } else if ($isModified) {
                                    $rowClass = 'row-update';
                                }

                                $chgWarehouse = ($before && (int) $before['warehouse_id'] !== (int) $after['warehouse_id']);
                                $chgDate = ($before && siNormalizeImportedDate((string) $before['stock_in_date']) !== siNormalizeImportedDate((string) $after['stock_in_date']));
                                $chgOrderNo = ($before && (string) $before['order_number'] !== (string) $after['order_number']);
                                $chgProduct = ($before && (int) $before['product_id'] !== (int) $after['product_id']);
                                $chgQty = ($before && (int) $before['product_quantity'] !== (int) $after['product_quantity']);
                                $chgPkg = false;
                            ?>
                                <div class="card mb-3 <?= siEsc($rowClass) ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <h6 class="mb-0">Record #<?= $idx + 1 ?></h6>
                                            <?php if ($isModified) { ?>
                                                <span class="badge-status badge-modified">MODIFIED</span>
                                            <?php } else if ($isNew) { ?>
                                                <span class="badge-status badge-new">NEW</span>
                                            <?php } else if ($isError) { ?>
                                                <span class="badge-status badge-error">ERROR</span>
                                            <?php } else { ?>
                                                <span class="badge-status bg-secondary text-white"><?= siEsc(strtoupper($type)) ?></span>
                                            <?php } ?>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-2">
                                                <label class="form-label">Order ID</label>
                                                <input type="text" class="form-control" value="<?= (int) (isset($entry['order_id']) ? $entry['order_id'] : 0) ?>" readonly>
                                                <input type="hidden" name="rows[<?= $idx ?>][order_id]" value="<?= (int) (isset($entry['order_id']) ? $entry['order_id'] : 0) ?>">
                                                <input type="hidden" name="rows[<?= $idx ?>][type]" value="<?= siEsc($type) ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Item ID</label>
                                                <input type="text" class="form-control" value="<?= (int) (isset($entry['item_id']) ? $entry['item_id'] : 0) ?>" readonly>
                                                <input type="hidden" name="rows[<?= $idx ?>][item_id]" value="<?= (int) (isset($entry['item_id']) ? $entry['item_id'] : 0) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Warehouse*</label>
                                                <div class="autocomplete">
                                                    <input id="si_warehouse_<?= $idx ?>" class="form-control lookup-input js-stock-warehouse-input js-server-bound js-required-field <?= $chgWarehouse ? 'highlight-change' : '' ?>" autocomplete="new-password" data-server-value="<?= siEsc($wAfter) ?>" data-lookup-field="warehouse" data-required-field="warehouse" data-required-message="Warehouse field is required!" name="rows[<?= $idx ?>][warehouse]" value="<?= siEsc($wAfter) ?>" required>
                                                    <input type="hidden" id="si_warehouse_<?= $idx ?>_hidden" value="">
                                                </div>
                                                <?php if (isset($fieldErrors['warehouse'])) { ?><div class="field-error" data-field="warehouse"><?= siEsc($fieldErrors['warehouse']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Stock In Date*</label>
                                                <input type="date" class="form-control js-required-field <?= $chgDate ? 'highlight-change' : '' ?>" data-required-field="stock_in_date" data-required-message="Stock In Date field is required!" name="rows[<?= $idx ?>][stock_in_date]" value="<?= siEsc($after['stock_in_date']) ?>" required>
                                                <?php if (isset($fieldErrors['stock_in_date'])) { ?><div class="field-error" data-field="stock_in_date"><?= siEsc($fieldErrors['stock_in_date']) ?></div><?php } ?>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="form-label">Order Number*</label>
                                                <input type="text" class="form-control js-required-field <?= $chgOrderNo ? 'highlight-change' : '' ?>" data-required-field="order_number" data-required-message="Order Number field is required!" name="rows[<?= $idx ?>][order_number]" value="<?= siEsc($oAfter) ?>" required>
                                                <?php if (isset($fieldErrors['order_number'])) { ?><div class="field-error" data-field="order_number"><?= siEsc($fieldErrors['order_number']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Product Name*</label>
                                                <div class="autocomplete">
                                                    <input id="si_product_<?= $idx ?>" class="form-control lookup-input js-stock-live-search js-server-bound js-required-field <?= $chgProduct ? 'highlight-change' : '' ?>" autocomplete="new-password" data-server-value="<?= siEsc($pAfter) ?>" data-search-type="name" data-db-table="<?= PROD ?>" data-lookup-field="product_name" data-required-field="product_name" data-required-message="Product Name field is required!" name="rows[<?= $idx ?>][product_name]" value="<?= siEsc($pAfter) ?>" required>
                                                    <input type="hidden" id="si_product_<?= $idx ?>_hidden" value="">
                                                </div>
                                                <?php if (isset($fieldErrors['product_name'])) { ?><div class="field-error" data-field="product_name"><?= siEsc($fieldErrors['product_name']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Product Quantity*</label>
                                                <input type="number" class="form-control js-required-field <?= $chgQty ? 'highlight-change' : '' ?>" data-required-field="product_quantity" data-required-message="Product Quantity field is required!" min="1" name="rows[<?= $idx ?>][product_quantity]" value="<?= (int) $qAfter ?>" required>
                                                <?php if (isset($fieldErrors['product_quantity'])) { ?><div class="field-error" data-field="product_quantity"><?= siEsc($fieldErrors['product_quantity']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Package Name (Ignored)</label>
                                                <input type="text" class="form-control <?= $chgPkg ? 'highlight-change' : '' ?>" name="rows[<?= $idx ?>][package_name]" value="<?= siEsc($pkgAfter) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                <a class="btn btn-lg btn-rounded btn-secondary px-4" href="<?= $importPage ?>">Cancel</a>
                                <button class="btn btn-lg btn-rounded btn-success px-4" name="actionBtn" value="confirmImport" type="submit">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Execute Bulk Import &amp; Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } else { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 1: Upload Edited Excel File</h5>
                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-bold">Select Excel File (.xlsx/.xls)</label>
                                    <input class="form-control form-control-lg" type="file" name="import_file" accept=".xlsx, .xls" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" name="actionBtn" value="importPreview" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Scan &amp; Preview File</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    // Pass PHP data to the external Javascript file
    window.__WAREHOUSE_STOCK_IN_IMPORT_CONFIG = {
        siteUrl: <?php echo json_encode($SITEURL); ?>,
        warehouseList: <?php echo json_encode($warehouseOptions); ?>,
        productList: <?php echo json_encode($productOptions); ?>
    };

    setButtonColor();
    preloader(300, '');
    <?php include "js/warehouse_stock_in_import.js"; ?>
</script>
</body>
</html>
