<?php
$stockMovementViewOnly = !empty($stockMovementViewOnly);
$currentPagePin = isset($currentPagePin) ? (int) $currentPagePin : 125;
$pageTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? (string) $pageTitle : 'Stock In';
$stockMovementUsePinTitle = !isset($stockMovementUsePinTitle) || $stockMovementUsePinTitle;
if (!$stockMovementUsePinTitle) {
    $disablePinGroupPageTitleSync = true;
}

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
if ($stockMovementUsePinTitle) {
    $pageTitle = getPinGroupNameById($connect, $currentPagePin);
}
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
$tblName = $stockInOrderTable;

$redirectTable = isset($stockMovementRedirectTable) && trim((string) $stockMovementRedirectTable) !== ''
    ? (string) $stockMovementRedirectTable
    : $SITEURL . '/warehouse_stock_in_table.php';
$redirectLink = "<script>location.href='" . $redirectTable . "';</script>";
$clearLocalStorage = '<script>localStorage.clear();</script>';
$backButtonTitle = isset($stockMovementBackButtonTitle) && trim((string) $stockMovementBackButtonTitle) !== ''
    ? (string) $stockMovementBackButtonTitle
    : 'Back to Stock In';

$pinAccess = checkCurrentPin($connect, $pageTitle);
if (!is_array($pinAccess)) {
    $pinAccess = array();
}

$legacyItemId = !empty(input('item_id')) ? (int) input('item_id') : 0;
$dataID = !empty(input('order_id')) ? (int) input('order_id') : (!empty(input('id')) ? (int) input('id') : ((int) post('order_id') > 0 ? (int) post('order_id') : (int) post('id')));
$act = !empty(input('act')) ? strtoupper(trim((string) input('act'))) : strtoupper(trim((string) post('act')));
$token = trim((string) input('t'));

if ($stockMovementViewOnly) {
    $act = '';
    $token = '';
}

if ($act === 'V') {
    $act = '';
}
if ($token !== '' && $act === '') {
    $act = 'I';
}

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . ' ' . $pageTitle;
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

if ((!$dataID && !$act && $token === '') || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
    exit;
}

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$packages = siLoadPackages($connect);
$packageProductMap = siBuildPackageProductMap($packages);

list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

if (!function_exists('siAttachmentDirRel')) {
    function siAttachmentDirRel()
    {
        return rtrim((string) img_server, '/') . '/finance/stock_in/';
    }
}

if (!function_exists('siAttachmentDirAbs')) {
    function siAttachmentDirAbs()
    {
        $rel = ltrim((string) siAttachmentDirRel(), '/\\');
        return rtrim((string) ROOT, '/\\') . '/' . $rel;
    }
}

if (!function_exists('siEnsureAttachmentDir')) {
    function siEnsureAttachmentDir()
    {
        $dir = siAttachmentDirAbs();
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log('Failed to create attachment directory: ' . $dir);
                return false;
            }
        }
        return is_dir($dir);
    }
}

if (!function_exists('siUploadAttachmentFiles')) {
    function siUploadAttachmentFiles($fileField)
    {
        if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
            return array(false, array(), 'Attachment file is missing.');
        }

        $file = $_FILES[$fileField];
        $names = isset($file['name']) ? $file['name'] : array();
        $tmpNames = isset($file['tmp_name']) ? $file['tmp_name'] : array();
        $errors = isset($file['error']) ? $file['error'] : array();

        if (!is_array($names)) {
            $names = array($names);
            $tmpNames = array($tmpNames);
            $errors = array($errors);
        }

        $allowed = array('png', 'jpg', 'jpeg', 'webp');

        if (!siEnsureAttachmentDir()) {
            return array(false, array(), 'Attachment folder is not ready.');
        }

        $saved = array();
        $hasAnyFile = false;
        $validFiles = array();

        // Pass 1: Validate all files first
        for ($idx = 0; $idx < count($names); $idx++) {
            $origName = isset($names[$idx]) ? (string) $names[$idx] : '';
            $tmpName = isset($tmpNames[$idx]) ? (string) $tmpNames[$idx] : '';
            $errCode = isset($errors[$idx]) ? (int) $errors[$idx] : UPLOAD_ERR_NO_FILE;

            if ($errCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $hasAnyFile = true;
            if ($errCode !== UPLOAD_ERR_OK) {
                return array(false, array(), 'Attachment upload failed.');
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                return array(false, array(), 'Attachment must be png, jpg, jpeg or webp.');
            }
            
            $validFiles[] = array('tmpName' => $tmpName, 'ext' => $ext);
        }

        if (!$hasAnyFile || count($validFiles) === 0) {
            return array(false, array(), 'Attachment is required.');
        }

        // Pass 2: Move files safely
        foreach ($validFiles as $idx => $f) {
            $newName = 'stock_in_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '_' . $idx . '.' . $f['ext'];
            $absPath = siAttachmentDirAbs() . $newName;
            $relPath = siAttachmentDirRel() . $newName;

            if (!@move_uploaded_file($f['tmpName'], $absPath)) {
                // Rollback previously saved files to avoid orphans
                foreach ($saved as $savedRelPath) {
                    $savedAbsPath = rtrim((string) ROOT, '/\\') . '/' . ltrim((string) $savedRelPath, '/\\');
                    @unlink($savedAbsPath);
                }
                return array(false, array(), 'Failed to save attachment file.');
            }

            $saved[] = $relPath;
        }

        return array(true, $saved, '');
    }
}

$formData = array(
    'order_id' => 0,
    'item_id' => 0,
    'warehouse_id' => 0,
    'stock_type' => 'Stock In',
    'stock_in_date' => '',
    'order_number' => '',
    'attachment' => '',
    'create_by' => '',
    'create_date' => '',
    'create_time' => '',
    'update_by' => '',
    'update_date' => '',
    'update_time' => '',
    'product_id' => 0,
    'product_name' => '',
    'product_quantity' => '',
);

$formRows = array(
    array(
        'product_id' => (int) $formData['product_id'],
        'product_name' => (string) $formData['product_name'],
        'product_quantity' => (string) $formData['product_quantity'],
    )
);

$err = '';
$invalidProductRows = array();
$invalidProductMessage = '';

$orderById = array();
foreach (siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable) as $row) {
    $orderId = (int) $row['order_id'];
    if (!isset($orderById[$orderId])) {
        $orderById[$orderId] = array(
            'order_id' => $orderId,
            'warehouse_id' => (int) $row['warehouse_id'],
            'stock_type' => isset($row['stock_type']) ? (string) $row['stock_type'] : 'Stock In',
            'stock_in_date' => (string) $row['stock_in_date'],
            'order_number' => (string) $row['order_number'],
            'attachment' => (string) (isset($row['attachment']) ? $row['attachment'] : ''),
            'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
            'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
            'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            'update_by' => isset($row['update_by']) ? (string) $row['update_by'] : '',
            'update_date' => isset($row['update_date']) ? (string) $row['update_date'] : '',
            'update_time' => isset($row['update_time']) ? (string) $row['update_time'] : '',
            'items' => array(),
        );
    }

    $orderById[$orderId]['items'][] = array(
        'item_id' => (int) $row['item_id'],
        'product_id' => (int) $row['product_id'],
        'product_quantity' => (int) $row['product_quantity'],
    );

    if ($dataID <= 0 && $legacyItemId > 0 && (int) $row['item_id'] === $legacyItemId) {
        $dataID = $orderId;
    }
}

if ($dataID > 0 && isset($orderById[$dataID])) {
    $selectedStockType = siNormalizeStockType(isset($orderById[$dataID]['stock_type']) ? $orderById[$dataID]['stock_type'] : 'Stock In');
    if ($selectedStockType === 'Stock Out') {
        $redirectTable = $SITEURL . '/stock_list_table.php';
        $redirectLink = "<script>location.href='" . $redirectTable . "';</script>";
        $backButtonTitle = 'Back to Stock List';
    }
}

if ($dataID > 0 && $act !== 'I') {
    if (!isset($orderById[$dataID])) {
        $err = 'Stock In row not found.';
        $act = 'F';
        $pageAction = getPageAction($act);
        $pageActionTitle = $pageAction . ' ' . $pageTitle;
    } else {
        $r = $orderById[$dataID];
        $formData['order_id'] = (int) $r['order_id'];
        $formData['item_id'] = isset($r['items'][0]['item_id']) ? (int) $r['items'][0]['item_id'] : 0;
        $formData['warehouse_id'] = (int) $r['warehouse_id'];
        $formData['stock_type'] = isset($r['stock_type']) ? (string) $r['stock_type'] : 'Stock In';
        $formData['stock_in_date'] = (string) $r['stock_in_date'];
        $formData['order_number'] = (string) $r['order_number'];
        $formData['attachment'] = (string) (isset($r['attachment']) ? $r['attachment'] : '');
        $formData['create_by'] = isset($r['create_by']) ? (string) $r['create_by'] : '';
        $formData['create_date'] = isset($r['create_date']) ? (string) $r['create_date'] : '';
        $formData['create_time'] = isset($r['create_time']) ? (string) $r['create_time'] : '';
        $formData['update_by'] = isset($r['update_by']) ? (string) $r['update_by'] : '';
        $formData['update_date'] = isset($r['update_date']) ? (string) $r['update_date'] : '';
        $formData['update_time'] = isset($r['update_time']) ? (string) $r['update_time'] : '';
        $formData['product_id'] = isset($r['items'][0]['product_id']) ? (int) $r['items'][0]['product_id'] : 0;
        $formData['product_name'] = isset($productNameMap[(int) $formData['product_id']]) ? (string) $productNameMap[(int) $formData['product_id']] : '';
        $formData['product_quantity'] = isset($r['items'][0]['product_quantity']) ? (string) ((int) $r['items'][0]['product_quantity']) : '';

        $formRows = array();
        foreach ($r['items'] as $itemRow) {
            $formRows[] = array(
                'product_id' => (int) $itemRow['product_id'],
                'product_name' => isset($productNameMap[(int) $itemRow['product_id']]) ? (string) $productNameMap[(int) $itemRow['product_id']] : '',
                'product_quantity' => (string) ((int) $itemRow['product_quantity']),
            );
        }

        if (count($formRows) === 0) {
            $formRows[] = array(
                'product_id' => (int) $formData['product_id'],
                'product_name' => (string) $formData['product_name'],
                'product_quantity' => (string) $formData['product_quantity'],
            );
        }
    }
}

if ($act === 'D') {
    if ($dataID <= 0 || !isset($orderById[$dataID])) {
        if (post('act') === 'D') {
            echo 'Invalid stock in order.';
        } else {
            echo "<script>alert('Invalid stock in order.'); location.href='" . $redirectTable . "';</script>";
        }
        exit;
    }

    $orderNo = (string) $orderById[$dataID]['order_number'];
    $orderStockType = siNormalizeStockType(isset($orderById[$dataID]['stock_type']) ? $orderById[$dataID]['stock_type'] : 'Stock In');
    $deleteOrderQuery = "UPDATE `" . $stockInOrderTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . (int) $dataID . "' AND status='A'";
    $deleteItemsQuery = "UPDATE `" . $stockInItemTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE stock_in_order_id='" . (int) $dataID . "' AND status='A'";

    mysqli_begin_transaction($finance_connect);
    try {
        if ($orderStockType === 'Stock Out') {
            if (!siDeactivateStockOutBatchUsageRowsByOrder($finance_connect, (int) $dataID)) {
                throw new Exception('Failed to remove stock out batch usage.');
            }
        } else {
            $usedQtyMap = siGetStockInUsedQtyMap($finance_connect, (int) $dataID);
            if (!empty($usedQtyMap)) {
                throw new Exception('This Stock In batch is already used by Stock Out records and cannot be deleted.');
            }
        }

        $okOrder = mysqli_query($finance_connect, $deleteOrderQuery);
        if (!$okOrder) {
            throw new Exception(mysqli_error($finance_connect));
        }

        $okItems = mysqli_query($finance_connect, $deleteItemsQuery);
        if (!$okItems) {
            throw new Exception(mysqli_error($finance_connect));
        }

        mysqli_commit($finance_connect);

        $log = [
            'log_act' => getPageAction('D'),
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'OrderID=' . (int) $dataID,
            'query_table' => $tblName,
            'act_msg' => USER_NAME . " deleted stock in data [ <b>Order No = " . htmlspecialchars($orderNo, ENT_QUOTES, 'UTF-8') . "</b> ] under <b><i>" . $stockInItemTable . " Table</i></b>.",
            'page' => $pageTitle,
            'connect' => $connect,
        ];
        audit_log($log);

        if (post('act') === 'D') {
            echo 'OK';
        } else {
            echo "<script>confirmationDialog('', '', '" . addslashes($pageTitle) . "', '', '" . $redirectTable . "', 'D');</script>";
        }
    } catch (Exception $ex) {
        mysqli_rollback($finance_connect);
        error_log('Stock in delete failed for order ID ' . (int) $dataID . ': ' . $ex->getMessage());
        if (post('act') === 'D') {
            echo 'Failed to delete row. Please try again later.';
        } else {
            echo "<script>alert('Failed to delete row. Please try again later.'); location.href='" . $redirectTable . "';</script>";
        }
    }

    exit;
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($stockMovementViewOnly && $action !== 'back') {
        echo $redirectLink;
        exit;
    }

    switch ($action) {
        case 'addData':
        case 'updData':
            $warehouseId = (int) postSpaceFilter('warehouse_id');
            $stockInDate = trim((string) postSpaceFilter('stock_in_date'));
            $orderNumber = trim((string) postSpaceFilter('order_number'));
            $existingAttachment = trim((string) postSpaceFilter('current_attachment'));
            $attachmentList = siAttachmentDecodeList($existingAttachment);

            if (isset($_FILES['stock_in_attachment']) && is_array($_FILES['stock_in_attachment'])) {
                $uploadResult = siUploadAttachmentFiles('stock_in_attachment');
                if (!$uploadResult[0]) {
                    $uploadErr = (string) $uploadResult[2];
                    if ($uploadErr !== 'Attachment is required.') {
                        $err = $uploadErr;
                    }
                } else {
                    $attachmentList = array_merge($attachmentList, (array) $uploadResult[1]);
                }
            }

            $attachmentPath = siAttachmentEncodeList($attachmentList);

            $productIds = isset($_POST['product_id']) ? postSpaceFilter('product_id') : array();
            $productNames = isset($_POST['product_name']) ? postSpaceFilter('product_name') : array();
            $quantities = isset($_POST['product_quantity']) ? postSpaceFilter('product_quantity') : array();

            if (!is_array($productIds)) {
                $productIds = array();
            }
            if (!is_array($productNames)) {
                $productNames = array();
            }
            if (!is_array($quantities)) {
                $quantities = array();
            }

            $formData['order_id'] = (int) postSpaceFilter('order_id');
            $formData['warehouse_id'] = $warehouseId;
            $formData['stock_in_date'] = $stockInDate;
            $formData['order_number'] = $orderNumber;
            $formData['attachment'] = $attachmentPath;

            $items = array();
            $formRows = array();
            $invalidProduct = false;
            $invalidProductRows = array();
            $invalidProductMessage = '';
            $max = max(count($productIds), count($productNames), count($quantities));

            for ($i = 0; $i < $max; $i++) {
                $prodId = isset($productIds[$i]) ? (int) $productIds[$i] : 0;
                $prodName = isset($productNames[$i]) ? trim((string) $productNames[$i]) : '';
                $qty = isset($quantities[$i]) ? trim((string) $quantities[$i]) : '';
                $qtyInt = (int) $qty;

                if ($prodId <= 0 && $prodName !== '') {
                    $prodKey = strtolower(trim($prodName));
                    if (isset($productNameToId[$prodKey])) {
                        $prodId = (int) $productNameToId[$prodKey];
                    }
                }

                $formRows[] = array(
                    'product_id' => $prodId,
                    'product_name' => $prodName,
                    'product_quantity' => $qty,
                );

                $hasAnyValue = ($prodId > 0 || $qtyInt > 0 || $prodName !== '');
                if (!$hasAnyValue) {
                    continue;
                }

                if ($prodId <= 0) {
                    $invalidProduct = true;
                    $invalidProductRows[] = $i;
                    continue;
                }

                if ($qtyInt <= 0) {
                    continue;
                }

                $items[] = array(
                    'product_id' => $prodId,
                    'package_id' => 0,
                    'qty' => $qtyInt,
                );
            }

            if (count($formRows) === 0) {
                $formRows[] = array(
                    'product_id' => 0,
                    'product_name' => '',
                    'product_quantity' => '',
                );
            }

            if ($err !== '') {
                // keep upload error from previous step
            } else if ($warehouseId <= 0) {
                $err = 'Warehouse cannot be empty.';
            } else if ($stockInDate === '') {
                $err = 'Stock In Date cannot be empty.';
            } else if ($orderNumber === '') {
                $err = 'Order Number cannot be empty.';
            } else if (count(siAttachmentDecodeList($attachmentPath)) === 0) {
                $err = 'At least 1 attachment is required.';
            } else if ($invalidProduct) {
                $invalidProductMessage = 'Please select valid product name from the dropdown list.';
            } else if (count($items) === 0) {
                $err = 'Please add at least one valid product row.';
            } else if ($action === 'addData') {
                $saved = siSaveOrder(
                    $finance_connect,
                    $stockInOrderTable,
                    $stockInItemTable,
                    $warehouseId,
                    $stockInDate,
                    $orderNumber,
                    $items,
                    $attachmentPath
                );

                if ($saved[0]) {
                    $summaryRows = array();
                    foreach ($items as $it) {
                        $summaryRows[] = 'ProductID=' . (int) $it['product_id'] . ', Qty=' . (int) $it['qty'];
                    }

                    $log = [
                        'log_act' => getPageAction('I'),
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => 'WarehouseID=' . (int) $warehouseId . '; StockInDate=' . $stockInDate . '; OrderNo=' . $orderNumber,
                        'query_table' => $tblName,
                        'newval' => implode(' | ', $summaryRows),
                        'act_msg' => USER_NAME . " added stock in data [ <b>Order No = " . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . "</b> ] under <b><i>" . $stockInItemTable . " Table</i></b>.",
                        'page' => $pageTitle,
                        'connect' => $connect,
                    ];
                    audit_log($log);

                    $_SESSION['tempValConfirmBox'] = true;
                    $act = 'I';
                    break;
                }

                $err = $saved[1];
            } else {
                $orderId = (int) postSpaceFilter('order_id');
                if ($orderId <= 0) {
                    $orderId = (int) $dataID;
                }

                if ($orderId <= 0) {
                    $err = 'Invalid row for update.';
                    break;
                }

                $oldWarehouseId = 0;
                $oldStockInDate = '';
                $oldOrderNumber = '';
                $oldStockType = 'Stock In';
                $oldAttachment = '';
                $oldAttachmentNorm = '';
                $oldSummaryRows = array();
                $oldItems = array();

                $oldOrderSql = "SELECT warehouse_id, stock_in_date, order_number, COALESCE(NULLIF(TRIM(stock_type), ''), 'Stock In') AS stock_type, attachment FROM `" . $stockInOrderTable . "` WHERE id='" . $orderId . "' AND status='A' LIMIT 1";
                $oldOrderRst = mysqli_query($finance_connect, $oldOrderSql);
                if ($oldOrderRst && ($oldOrderRow = mysqli_fetch_assoc($oldOrderRst))) {
                    $oldWarehouseId = (int) $oldOrderRow['warehouse_id'];
                    $oldStockInDate = (string) $oldOrderRow['stock_in_date'];
                    $oldOrderNumber = (string) $oldOrderRow['order_number'];
                    $oldStockType = siNormalizeStockType(isset($oldOrderRow['stock_type']) ? $oldOrderRow['stock_type'] : 'Stock In');
                    $oldAttachment = (string) (isset($oldOrderRow['attachment']) ? $oldOrderRow['attachment'] : '');
                    $oldAttachmentNorm = siAttachmentEncodeList(siAttachmentDecodeList($oldAttachment));
                }

                $newAttachmentNorm = siAttachmentEncodeList(siAttachmentDecodeList($attachmentPath));

                $oldItemsSql = "SELECT product_id, product_quantity FROM `" . $stockInItemTable . "` WHERE stock_in_order_id='" . $orderId . "' AND status='A' ORDER BY id ASC";
                $oldItemsRst = mysqli_query($finance_connect, $oldItemsSql);
                if ($oldItemsRst) {
                    while ($oldItem = mysqli_fetch_assoc($oldItemsRst)) {
                        $oldItems[] = array(
                            'product_id' => isset($oldItem['product_id']) ? (int) $oldItem['product_id'] : 0,
                            'qty' => isset($oldItem['product_quantity']) ? (int) $oldItem['product_quantity'] : 0,
                        );
                        $oldSummaryRows[] = 'ProductID=' . (int) $oldItem['product_id'] . ', Qty=' . (int) $oldItem['product_quantity'];
                    }
                }

                $newSummaryRows = array();
                foreach ($items as $it) {
                    $newSummaryRows[] = 'ProductID=' . (int) $it['product_id'] . ', Qty=' . (int) $it['qty'];
                }

                $hasNoChanges = ((int) $oldWarehouseId === (int) $warehouseId)
                    && ($oldStockInDate === $stockInDate)
                    && ($oldOrderNumber === $orderNumber)
                    && ($oldAttachmentNorm === $newAttachmentNorm)
                    && (implode(' | ', $oldSummaryRows) === implode(' | ', $newSummaryRows));

                if ($hasNoChanges) {
                    $_SESSION['tempValConfirmBox'] = true;
                    $act = 'NC';
                    break;
                }

                $hasProtectedStockInUsage = false;
                $reuseExistingStockInItems = false;
                if ($oldStockType !== 'Stock Out') {
                    $activeUsageMap = siGetStockInUsedQtyMap($finance_connect, $orderId);
                    $hasProtectedStockInUsage = !empty($activeUsageMap);
                    $itemShapeChanged = (implode(' | ', $oldSummaryRows) !== implode(' | ', $newSummaryRows));
                    $criticalStockInChange = ((int) $oldWarehouseId !== (int) $warehouseId)
                        || ($oldStockInDate !== $stockInDate)
                        || $itemShapeChanged;
                    if ($hasProtectedStockInUsage && $criticalStockInChange) {
                        $err = 'This Stock In batch is already used by Stock Out records. Only order number or attachment can be updated.';
                        break;
                    }
                    if ($hasProtectedStockInUsage && !$itemShapeChanged) {
                        $reuseExistingStockInItems = true;
                    }
                }

                $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
                $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);
                $safeAttachment = mysqli_real_escape_string($finance_connect, $newAttachmentNorm);

                $uOrder = "UPDATE `" . $stockInOrderTable . "` SET warehouse_id='" . (int) $warehouseId . "', stock_in_date='" . $safeDate . "', order_number='" . $safeOrderNo . "', attachment='" . $safeAttachment . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $orderId . "' AND status='A'";
                $deactivateItems = "UPDATE `" . $stockInItemTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE stock_in_order_id='" . $orderId . "' AND status='A'";

                mysqli_begin_transaction($finance_connect);
                try {
                    if ($oldStockType === 'Stock Out') {
                        if (!siEnsureStockOutBatchUsageTable($finance_connect)) {
                            throw new Exception('Failed to prepare stock out batch usage table.');
                        }
                        if (!siDeactivateStockOutBatchUsageRowsByOrder($finance_connect, $orderId)) {
                            throw new Exception('Failed to rebuild stock out batch usage.');
                        }
                    }

                    $okOrder = mysqli_query($finance_connect, $uOrder);
                    if (!$okOrder) {
                        throw new Exception(mysqli_error($finance_connect));
                    }

                    $newStockOutItems = array();
                    if (!$reuseExistingStockInItems) {
                        $okDeactivate = mysqli_query($finance_connect, $deactivateItems);
                        if (!$okDeactivate) {
                            throw new Exception(mysqli_error($finance_connect));
                        }

                        foreach ($items as $item) {
                            $productId = (int) $item['product_id'];
                            $packageId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                            $qty = (int) $item['qty'];
                            $iItem = "INSERT INTO `" . $stockInItemTable . "` (stock_in_order_id, product_id, package_id, product_quantity, create_by, create_date, create_time, status) VALUES ('" . $orderId . "', '" . $productId . "', '" . $packageId . "', '" . $qty . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                            $okItem = mysqli_query($finance_connect, $iItem);
                            if (!$okItem) {
                                throw new Exception(mysqli_error($finance_connect));
                            }

                            if ($oldStockType === 'Stock Out') {
                                $newStockOutItems[] = array(
                                    'item_id' => (int) mysqli_insert_id($finance_connect),
                                    'product_id' => $productId,
                                    'package_id' => $packageId,
                                    'qty' => $qty,
                                );
                            }
                        }
                    }

                    if ($oldStockType === 'Stock Out') {
                        $stockOutAllocations = array();
                        $warehouseLabel = isset($warehouseNameMap[(int) $warehouseId]) ? (string) $warehouseNameMap[(int) $warehouseId] : '';
                        foreach ($newStockOutItems as $stockOutItem) {
                            $productId = (int) $stockOutItem['product_id'];
                            $qty = (int) $stockOutItem['qty'];
                            if ($productId <= 0 || $qty <= 0) {
                                continue;
                            }

                            $productLabel = isset($productNameMap[$productId]) ? (string) $productNameMap[$productId] : ('product #' . $productId);
                            $stockOutAllocations[(int) $stockOutItem['item_id']] = siAllocateStockOutQuantityAcrossFifoBatches(
                                $finance_connect,
                                (int) $warehouseId,
                                $productId,
                                $qty,
                                isset($stockOutItem['package_id']) ? (int) $stockOutItem['package_id'] : 0,
                                $orderId,
                                $productLabel,
                                $warehouseLabel
                            );
                        }

                        foreach ($newStockOutItems as $stockOutItem) {
                            $stockOutItemId = isset($stockOutItem['item_id']) ? (int) $stockOutItem['item_id'] : 0;
                            if ($stockOutItemId <= 0) {
                                continue;
                            }

                            siInsertStockOutBatchUsageRows(
                                $finance_connect,
                                $orderId,
                                $stockOutItemId,
                                isset($stockOutAllocations[$stockOutItemId]) ? $stockOutAllocations[$stockOutItemId] : array(),
                                USER_ID
                            );
                        }
                    }

                    mysqli_commit($finance_connect);

                    $summaryRows = array();
                    foreach ($items as $it) {
                        $summaryRows[] = 'ProductID=' . (int) $it['product_id'] . ', Qty=' . (int) $it['qty'];
                    }

                    $oldChanges = implode(' | ', $oldSummaryRows);
                    $changes = implode(' | ', $summaryRows);

                    $oldVals = array();
                    $newVals = array();
                    $msgChanges = array();

                    if ((int) $oldWarehouseId !== (int) $warehouseId) {
                        $oldVals[] = 'WarehouseID=' . $oldWarehouseId;
                        $newVals[] = 'WarehouseID=' . $warehouseId;
                        $msgChanges[] = "[ <b>WarehouseID</b> : <b>'" . $oldWarehouseId . "'</b> to <b>'" . $warehouseId . "'</b> ]";
                    }
                    if ($oldStockInDate !== $stockInDate) {
                        $oldVals[] = 'StockInDate=' . $oldStockInDate;
                        $newVals[] = 'StockInDate=' . $stockInDate;
                        $msgChanges[] = "[ <b>StockInDate</b> : <b>'" . $oldStockInDate . "'</b> to <b>'" . $stockInDate . "'</b> ]";
                    }
                    if ($oldOrderNumber !== $orderNumber) {
                        $oldVals[] = 'OrderNo=' . $oldOrderNumber;
                        $newVals[] = 'OrderNo=' . $orderNumber;
                        $msgChanges[] = "[ <b>OrderNo</b> : <b>'" . htmlspecialchars($oldOrderNumber, ENT_QUOTES, 'UTF-8') . "'</b> to <b>'" . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . "'</b> ]";
                    }
                    if ($oldAttachmentNorm !== $newAttachmentNorm) {
                        $oldCount = count(siAttachmentDecodeList($oldAttachmentNorm));
                        $newCount = count(siAttachmentDecodeList($newAttachmentNorm));
                        $oldVals[] = 'AttachmentCount=' . $oldCount;
                        $newVals[] = 'AttachmentCount=' . $newCount;
                        $msgChanges[] = "[ <b>Attachment Count</b> : <b>'" . $oldCount . "'</b> to <b>'" . $newCount . "'</b> ]";
                    }
                    if ($oldChanges !== $changes) {
                        $oldItemsStr = $oldChanges !== '' ? $oldChanges : '-';
                        $newItemsStr = $changes !== '' ? $changes : '-';
                        $oldVals[] = 'Items={' . $oldItemsStr . '}';
                        $newVals[] = 'Items={' . $newItemsStr . '}';
                        $msgChanges[] = "[ <b>Items</b> : <b>'" . htmlspecialchars($oldItemsStr, ENT_QUOTES, 'UTF-8') . "'</b> to <b>'" . htmlspecialchars($newItemsStr, ENT_QUOTES, 'UTF-8') . "'</b> ]";
                    }

                    if (count($msgChanges) > 0) {
                        $log = [
                            'log_act' => getPageAction('E'),
                            'cdate' => $cdate,
                            'ctime' => $ctime,
                            'uid' => USER_ID,
                            'cby' => USER_ID,
                            'query_rec' => 'OrderID=' . $orderId,
                            'query_table' => $tblName,
                            'oldval' => implode(' ; ', $oldVals),
                            'changes' => implode(' ; ', $newVals),
                            'act_msg' => USER_NAME . " edited stock in data [ <b>Order ID = " . (int) $orderId . "</b> ] " . implode(' ', $msgChanges) . " under <b><i>" . $stockInItemTable . " Table</i></b>.",
                            'page' => $pageTitle,
                            'connect' => $connect,
                        ];
                        audit_log($log);
                    }

                    $_SESSION['tempValConfirmBox'] = true;
                    $act = 'E';
                    break;
                } catch (Exception $ex) {
                    mysqli_rollback($finance_connect);
                    $err = 'Update failed: ' . $ex->getMessage();
                }
            }
            break;

        case 'back':
            echo $clearLocalStorage . ' ' . $redirectLink;
            exit;
    }
}

if ($token !== '') {
    $requestId = sorDecodeToken($token);
    if ($requestId > 0) {
        $reqSql = "SELECT id,
                          COALESCE(NULLIF(TRIM(invoice_no), ''), CONCAT('SOR-', id)) AS order_number,
                          warehouse_id,
                          request_date
                   FROM " . STOCK_ORDER_REQ . "
                   WHERE id='" . (int) $requestId . "' AND status='A' LIMIT 1";
        $reqRst = mysqli_query($finance_connect, $reqSql);
        if ($reqRst && ($req = mysqli_fetch_assoc($reqRst))) {
            $scanUrl = $SITEURL . '/warehouse_stock_in_scan.php?t=' . urlencode($token);
            echo "<script>location.href='" . $scanUrl . "';</script>";
            exit;
        }
        $err = 'Invalid or inactive stock order request token.';
    } else {
        $err = 'Invalid token.';
    }
}

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . addslashes($pageTitle) . '","","' . $redirectTable . '","' . addslashes($act) . '");</script>';
}

$isViewMode = ($act === '' && $dataID > 0 && isset($orderById[$dataID]));
$isEditMode = ($act === 'E' && $dataID > 0 && isset($orderById[$dataID]));
$isAddMode = ($act === 'I');

$warehouseDisabledAttr = $isViewMode ? ' disabled' : '';
$inputReadonlyAttr = $isViewMode ? ' readonly' : '';

if ($isViewMode && $dataID > 0 && isset($orderById[$dataID])) {
    $viewRow = $orderById[$dataID];
    $log = [
        'log_act' => getPageAction(''),
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => 'OrderID=' . (int) $dataID,
        'query_table' => $tblName,
        'act_msg' => USER_NAME . " viewed stock in data [ <b>Order No = " . htmlspecialchars((string) $viewRow['order_number'], ENT_QUOTES, 'UTF-8') . "</b> ] from <b><i>" . $stockInOrderTable . " Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        #stockInItemTable .product_name {
            font-weight: normal !important;
        }

        #stockInForm .table-responsive {
            overflow-x: auto;
            overflow-y: visible;
        }

        #stockInItemTable {
            overflow: visible;
        }

        #stockInItemTable td,
        #stockInItemTable th,
        #stockInItemBody tr {
            overflow: visible;
        }

        #stockInItemTable .stock-in-product-autocomplete {
            width: 100%;
        }

        #stockInItemTable .stock-in-product-autocomplete .searchResult {
            width: 100% !important;
            max-height: 200px !important;
            overflow-y: auto !important;
            overflow-x: hidden;
            z-index: 9999 !important;
        }

        #stockInForm .table-responsive,
        #stockInItemTable,
        #stockInItemTable tbody,
        #stockInItemTable tr,
        #stockInItemTable td {
            overflow: visible !important;
        }

        .si-attach-wrap {
            border: 1px solid #e2e2e2;
            border-radius: 8px;
            padding: 12px;
        }

        .si-attach-preview {
            min-height: 180px;
            border: 1px dashed #d0d0d0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafafa;
            padding: 10px;
        }

        .si-attach-preview img {
            max-width: 100%;
            max-height: 260px;
            object-fit: contain;
        }

        .si-attachment-input-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .si-attachment-input-row .stock-in-attachment-input {
            flex: 1;
        }

    </style>
</head>
<body>
    
<div class="page-load-cover">
    <div class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= $redirectTable ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
            <?= siEsc($pageActionTitle) ?>
        </p>
    </div>

    <div id="formContainer" class="container-fluid mt-2">
        <div class="col-12 col-md-12 formWidthAdjust">
            <?php if ($err !== '') { ?>
                <div class="alert alert-danger"><?= siEsc($err) ?></div>
            <?php } ?>

            <form method="post" id="stockInForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="act" value="<?= siEsc($act) ?>">
                <input type="hidden" id="stockInProductsJson" value="<?= siEsc(json_encode(array_values($products))) ?>">
                <input type="hidden" id="isViewModeFlag" value="<?= $isViewMode ? '1' : '0' ?>">
                <input type="hidden" name="current_attachment" id="current_attachment" value="<?= siEsc((string) $formData['attachment']) ?>">
                <?php if ($isEditMode || $isViewMode) { ?>
                    <input type="hidden" name="order_id" value="<?= (int) $formData['order_id'] ?>">
                <?php } ?>

                <div class="form-group mb-5">
                    <h2><?= siEsc($pageActionTitle) ?></h2>
                </div>

                <div class="row">
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label form_lbl">Warehouse<span class="requireRed">*</span></label>
                        <select class="form-select" name="warehouse_id" id="warehouse_id" required<?= $warehouseDisabledAttr ?>>
                            <option value="">Select Warehouse</option>
                            <?php foreach ($warehouses as $w) { ?>
                                <option value="<?= (int) $w['id'] ?>" <?= ((int) $formData['warehouse_id'] === (int) $w['id']) ? 'selected' : '' ?>><?= siEsc($w['name']) ?></option>
                            <?php } ?>
                        </select>
                        <?php if ($isViewMode) { ?>
                            <input type="hidden" name="warehouse_id" value="<?= (int) $formData['warehouse_id'] ?>">
                        <?php } ?>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label form_lbl">Stock In Date<span class="requireRed">*</span></label>
                        <input class="form-control" type="date" name="stock_in_date" id="stock_in_date" value="<?= siEsc($formData['stock_in_date']) ?>" required<?= $inputReadonlyAttr ?>>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label form_lbl">Order Number<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" name="order_number" id="order_number" value="<?= siEsc($formData['order_number']) ?>" required<?= $inputReadonlyAttr ?> autocomplete="off">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label form_lbl">Attachment<span class="requireRed">*</span></label>
                        <?php
                        $attachmentPath = trim((string) $formData['attachment']);
                        $attachmentUrl = $attachmentPath !== '' ? (rtrim((string) $SITEURL, '/') . '/' . ltrim($attachmentPath, '/')) : '';
                        $attachmentExt = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
                        $isImageAttachment = in_array($attachmentExt, array('png', 'jpg', 'jpeg', 'webp'), true);
                        ?>
                        <div class="si-attach-wrap">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-md-6">
                                    <?php if (!$isViewMode) { ?>
                                        <div id="stock_in_attachment_inputs">
                                            <div class="mb-2 si-attachment-input-row">
                                                <input class="form-control stock-in-attachment-input" type="file" name="stock_in_attachment[]" id="stock_in_attachment" accept=".png,.jpg,.jpeg,.webp">
                                                <button class="mt-1 add-stock-attachment-btn" id="action_menu_btn" type="button" title="Add another attachment"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                            </div>
                                        </div>
                                        <small class="text-muted">Upload at least 1 photo for every stock in. Click + to add more attachments.</small>
                                    <?php } ?>

                                                    <?php
                                                    $attachmentPaths = siAttachmentDecodeList((string) $formData['attachment']);
                                                    ?>
                                                    <?php if (count($attachmentPaths) > 0) { ?>
                                        <div class="mt-2" id="stock_in_attachment_preview">
                                                            <?php foreach ($attachmentPaths as $attachIdx => $attachPath) { ?>
                                                                <?php $attachUrl = rtrim((string) $SITEURL, '/') . '/' . ltrim((string) $attachPath, '/'); ?>
                                                                <div><a href="<?= siEsc($attachUrl) ?>" target="_blank">Open attachment <?= (int) ($attachIdx + 1) ?></a></div>
                                                            <?php } ?>
                                        </div>
                                    <?php } else { ?>
                                                        <div class="mt-2" id="stock_in_attachment_preview"></div>
                                    <?php } ?>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="si-attach-preview">
                                                        <div id="stock_in_attachment_img_list" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
                                                            <?php
                                                            $hasImage = false;
                                                            foreach ($attachmentPaths as $attachPath) {
                                                                $ext = strtolower(pathinfo((string) $attachPath, PATHINFO_EXTENSION));
                                                                if (!in_array($ext, array('png', 'jpg', 'jpeg', 'webp'), true)) {
                                                                    continue;
                                                                }
                                                                $hasImage = true;
                                                                $imgUrl = rtrim((string) $SITEURL, '/') . '/' . ltrim((string) $attachPath, '/');
                                                            ?>
                                                                <img src="<?= siEsc($imgUrl) ?>" alt="Attachment Preview" style="max-width:120px;max-height:120px;object-fit:cover;border-radius:6px;">
                                                            <?php } ?>
                                                        </div>
                                                        <span id="stock_in_attachment_placeholder" class="text-muted"<?= $hasImage ? ' style="display:none;"' : '' ?>>Image preview</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered" id="stockInItemTable">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th style="min-width: 250px;">Product Name*</th>
                                        <th width="150">Product Quantity*</th>
                                        <th width="100">Action</th>
                                    </tr>
                                </thead>
                            <tbody id="stockInItemBody">
                                <?php foreach ($formRows as $idx => $formRow) { ?>
                                <tr>
                                    <td class="row-no"><?= (int) ($idx + 1) ?></td>
                                    <td>
                                        <div class="autocomplete stock-in-product-autocomplete">
                                            <input type="text" class="form-control product_name" id="product_name_<?= (int) $idx ?>" name="product_name[]" placeholder="Type Product" value="<?= siEsc($formRow['product_name']) ?>" required<?= $inputReadonlyAttr ?> autocomplete="off">
                                            <input type="hidden" id="product_name_<?= (int) $idx ?>_hidden" name="product_id[]" class="product_id" value="<?= (int) $formRow['product_id'] ?>">
                                            <?php if ($invalidProductMessage !== '' && in_array((int) $idx, $invalidProductRows, true)) { ?>
                                                <div class="si-field-error" style="color:#ff0000; margin-top:4px; font-size:0.95rem;"><?= siEsc($invalidProductMessage) ?></div>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input class="form-control" type="number" name="product_quantity[]" min="1" value="<?= siEsc($formRow['product_quantity']) ?>" required<?= $inputReadonlyAttr ?>>
                                    </td>
                                    <td class="row-action-cell" style="text-align:center;">
                                        <?php if (!$isViewMode) { ?>
                                            <?php if ($idx === 0) { ?>
                                                <button class="mt-1" id="action_menu_btn" type="button" onclick="AddStockInRow()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                            <?php } else { ?>
                                                <button class="mt-1 remove-stock-row" id="action_menu_btn" type="button" onclick="RemoveStockInRow(this)"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!$isAddMode) { ?>
                    <?= commonRenderCreateUpdateInfo($formData, $connect, $act) ?>
                <?php } ?>

                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                    <?php if ($isAddMode) { ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="addData"><?= siEsc($pageActionTitle) ?></button>
                    <?php } else if ($isEditMode) { ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="updData"><?= siEsc($pageActionTitle) ?></button>
                    <?php } ?>
                    <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back" title="<?= siEsc($backButtonTitle) ?>" aria-label="<?= siEsc($backButtonTitle) ?>">Back</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const page = <?= json_encode($pageTitle) ?>;
    const action = <?= json_encode($act) ?>;
    var stockInSiteUrl = <?= json_encode($SITEURL) ?>;
    var stockInBackButtonTitle = <?= json_encode($backButtonTitle) ?>;
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    setButtonColor();
    function applyStockInBackButtonTitle() {
        var backButton = document.querySelector('button[name="actionBtn"][value="back"]');
        if (backButton && stockInBackButtonTitle) {
            backButton.setAttribute('title', stockInBackButtonTitle);
            backButton.setAttribute('aria-label', stockInBackButtonTitle);
        }
    }
    applyStockInBackButtonTitle();
    window.addEventListener('load', applyStockInBackButtonTitle);
    preloader(300, action);
</script>
</body>
<script>
<?php include './js/warehouse_stock_in.js'; ?>
</script>
</html>
