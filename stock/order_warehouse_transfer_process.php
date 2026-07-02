<?php
include_once '../init.php';
include_once ROOT . '/include/common.php';
include_once '../checkCurrentPagePin.php';

$orderWarehouseTransferPageUrl = rtrim((string) $SITEURL, '/') . '/stock/order_warehouse_transfer.php';

if (empty($_SESSION['userid'])) {
    header('Location: ' . $SITEURL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $orderWarehouseTransferPageUrl);
    exit;
}

if (!function_exists('owtpSetFlash')) {
    function owtpSetFlash($type, $message)
    {
        $_SESSION['order_warehouse_transfer_flash'] = array(
            'type' => (string) $type,
            'message' => (string) $message,
        );
    }
}

if (!function_exists('owtpRedirectToPage')) {
    function owtpRedirectToPage($baseUrl, $orderCode = '', $platform = 'all')
    {
        $orderCode = trim((string) $orderCode);
        $platform = shopeeOmsNormalizePlatformKey($platform, true);
        if ($platform === '') {
            $platform = 'all';
        }

        $query = array();
        if ($orderCode !== '') {
            $query['order_code'] = $orderCode;
        }
        if ($platform !== '') {
            $query['platform'] = $platform;
        }



        $targetUrl = $baseUrl;
        if (!empty($query)) {
            $targetUrl .= '?' . http_build_query($query);
        }

        header('Location: ' . $targetUrl);
        exit;
    }
}

if (!function_exists('owtpBuildQualifiedTableName')) {
    function owtpBuildQualifiedTableName($dbName, $tblName)
    {
        return '`' . str_replace('`', '``', (string) $dbName) . '`.`' . str_replace('`', '``', (string) $tblName) . '`';
    }
}


if (!function_exists('owtpGetTransferLogTableName')) {
    function owtpGetTransferLogTableName()
    {
        return defined('ORDER_WAREHOUSE_TRANSFER_LOG') ? ORDER_WAREHOUSE_TRANSFER_LOG : 'order_warehouse_transfer_log';
    }
}

if (!function_exists('owtpOpenFinanceTransactionConnection')) {
    function owtpOpenFinanceTransactionConnection()
    {
        $dbHost = (string) dbhost;
        $dbPort = 3306;
        if (strpos($dbHost, ':') !== false) {
            list($dbHost, $dbPort) = explode(':', $dbHost, 2);
            $dbPort = (int) $dbPort > 0 ? (int) $dbPort : 3306;
        }

        $txnConnect = @new mysqli($dbHost, dbuser, dbpwd, '', $dbPort);
        if ($txnConnect->connect_error) {
            throw new Exception('Unable to connect to the transfer database.');
        }

        if (!$txnConnect->select_db(dbFinance)) {
            throw new Exception('Unable to select the financial database.');
        }

        return $txnConnect;
    }
}

if (!function_exists('owtpLoadTransferOrderRow')) {
    function owtpLoadTransferOrderRow($cmsConnect, $financeConnect, $orderId, $platform)
    {
        $platform = shopeeOmsNormalizePlatformKey($platform);
        if ($platform === '') {
            return array();
        }

        $sourceConfig = shopeeOmsGetOrderSourceConfig($platform);
        $sourceConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
        $orderRow = shopeeOmsLoadOrder($sourceConnect, (int) $orderId, $sourceConfig);

        if (empty($orderRow) || !isset($orderRow['status']) || (string) $orderRow['status'] !== 'A') {
            return array();
        }

        return $orderRow;
    }
}

if (!function_exists('owtpBuildProductLabelMap')) {
    function owtpBuildProductLabelMap($productSummary)
    {
        $labelMap = array();
        $rows = isset($productSummary['product_summary_rows']) && is_array($productSummary['product_summary_rows'])
            ? $productSummary['product_summary_rows']
            : array();

        foreach ($rows as $row) {
            $productId = isset($row['product_id']) ? (int) $row['product_id'] : 0;
            if ($productId > 0) {
                $labelMap[$productId] = isset($row['label']) ? (string) $row['label'] : ('product #' . $productId);
            }
        }

        return $labelMap;
    }
}

if (!function_exists('owtpSummarizeUsageQtyByProduct')) {
    function owtpSummarizeUsageQtyByProduct($usageRows)
    {
        $qtyMap = array();
        foreach ((array) $usageRows as $usageRow) {
            $productId = isset($usageRow['product_id']) ? (int) $usageRow['product_id'] : 0;
            $usedQty = isset($usageRow['used_quantity']) ? (int) $usageRow['used_quantity'] : 0;
            if ($productId <= 0 || $usedQty <= 0) {
                continue;
            }
            if (!isset($qtyMap[$productId])) {
                $qtyMap[$productId] = 0;
            }
            $qtyMap[$productId] += $usedQty;
        }

        ksort($qtyMap);
        return $qtyMap;
    }
}

if (!function_exists('owtpNormalizeQtyMap')) {
    function owtpNormalizeQtyMap($qtyMap)
    {
        $normalized = array();
        foreach ((array) $qtyMap as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($productId > 0 && $qty > 0) {
                $normalized[$productId] = $qty;
            }
        }

        ksort($normalized);
        return $normalized;
    }
}

if (!function_exists('owtpInsertAuditLogRecord')) {
    function owtpInsertAuditLogRecord($txnConnect, $message, $queryTable, $queryRecord, $oldValue, $newValue)
    {
        $txnConnect = $txnConnect instanceof mysqli ? $txnConnect : null;
        if (!$txnConnect) {
            throw new Exception('Audit log connection is unavailable.');
        }

        $actionId = get_allowed_audit_actions('edit');
        if ($actionId === null) {
            return;
        }

        $safeMessage = mysqli_real_escape_string($txnConnect, (string) $message);
        $safeQueryTable = mysqli_real_escape_string($txnConnect, (string) $queryTable);
        $safeQueryRecord = mysqli_real_escape_string($txnConnect, (string) $queryRecord);
        $safeOldValue = mysqli_real_escape_string($txnConnect, (string) $oldValue);
        $safeNewValue = mysqli_real_escape_string($txnConnect, (string) $newValue);
        $safePage = mysqli_real_escape_string($txnConnect, 'Order Warehouse Transfer');
        $safeUserId = mysqli_real_escape_string($txnConnect, (string) USER_ID);

        $auditTable = owtpBuildQualifiedTableName(dbname, AUDIT_LOG);
        $auditSql = "INSERT INTO " . $auditTable . "
            (`log_action`, `screen_type`, `user_id`, `action_message`, `query_record`, `query_table`, `old_value`, `changes`, `create_date`, `create_time`, `create_by`)
            VALUES
            (" . (int) $actionId . ", '" . $safePage . "', '" . $safeUserId . "', '" . $safeMessage . "', '" . $safeQueryRecord . "', '" . $safeQueryTable . "', '" . $safeOldValue . "', '" . $safeNewValue . "', CURDATE(), CURTIME(), '" . $safeUserId . "')";

        if (!mysqli_query($txnConnect, $auditSql)) {
            throw new Exception('Failed to save audit log.');
        }
    }
}

if (!function_exists('owtpProcessTransfer')) {
    function owtpProcessTransfer($cmsConnect, $financeConnect, $orderId, $platform, $newWarehouseId, $transferNote, $idempotencyKey)
    {
        $platform = shopeeOmsNormalizePlatformKey($platform);
        if ($platform === '') {
            throw new Exception('Please select a valid platform.');
        }

        $sourceConfig = shopeeOmsGetOrderSourceConfig($platform);
        if (empty($sourceConfig)) {
            throw new Exception('The selected platform is not supported.');
        }

        $orderRow = owtpLoadTransferOrderRow($cmsConnect, $financeConnect, $orderId, $platform);
        if (empty($orderRow)) {
            throw new Exception('Order not found.');
        }

        $productSummary = shopeeOmsBuildOrderProductSummaryBySource($cmsConnect, $orderRow, $sourceConfig);
        $productQtyMap = owtpNormalizeQtyMap(isset($productSummary['product_qty_map']) ? $productSummary['product_qty_map'] : array());
        if (empty($productQtyMap)) {
            throw new Exception('Unable to resolve product or package quantity for this order.');
        }

        $activeWarehouseRows = shopeeOmsLoadActiveWarehouses($cmsConnect);
        $activeWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($cmsConnect, true);
        $activeWarehouseIds = array();
        foreach ($activeWarehouseRows as $warehouseRow) {
            $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0;
            if ($warehouseId > 0) {
                $activeWarehouseIds[$warehouseId] = true;
            }
        }

        if (!isset($activeWarehouseIds[$newWarehouseId])) {
            throw new Exception('Please select a valid active warehouse.');
        }

        $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($cmsConnect);
        $oldWarehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, $defaultWarehouseId);
        if ($oldWarehouseId <= 0) {
            throw new Exception('The current stock-out warehouse is not available.');
        }
        if ($oldWarehouseId === $newWarehouseId) {
            throw new Exception('The new warehouse must be different from the current warehouse.');
        }

        $oldWarehouseName = shopeeOmsResolveWarehouseNameById($cmsConnect, $oldWarehouseId, $defaultWarehouseId, $activeWarehouseNameMap);
        $newWarehouseName = shopeeOmsResolveWarehouseNameById($cmsConnect, $newWarehouseId, $defaultWarehouseId, $activeWarehouseNameMap);
        $productLabelMap = owtpBuildProductLabelMap($productSummary);
        $orderCode = shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig);
        $sourceQualifiedTable = shopeeOmsBuildQualifiedTableName($sourceConfig);
        $warehouseField = isset($sourceConfig['warehouse_field']) && trim((string) $sourceConfig['warehouse_field']) !== ''
            ? (string) $sourceConfig['warehouse_field']
            : 'stock_out_warehouse_id';

        $txnConnect = owtpOpenFinanceTransactionConnection();

        try {
            $safeIdempotencyKey = mysqli_real_escape_string($txnConnect, $idempotencyKey);
            $safeTransferLogTable = str_replace('`', '``', owtpGetTransferLogTableName());
            $duplicateSql = "SELECT `id`
                FROM `" . $safeTransferLogTable . "`
                WHERE `idempotency_key` = '" . $safeIdempotencyKey . "'
                  AND `status` = 'A'
                LIMIT 1";
            $duplicateResult = mysqli_query($txnConnect, $duplicateSql);
            if ($duplicateResult && mysqli_num_rows($duplicateResult) > 0) {
                return array(
                    'success' => true,
                    'message' => 'This transfer request was already processed.',
                    'duplicate' => true,
                );
            }

            mysqli_begin_transaction($txnConnect);

            $lockedOrderResult = mysqli_query(
                $txnConnect,
                "SELECT * FROM " . $sourceQualifiedTable . " WHERE `id` = " . (int) $orderId . " AND `status` = 'A' LIMIT 1 FOR UPDATE"
            );
            if (!$lockedOrderResult || mysqli_num_rows($lockedOrderResult) === 0) {
                throw new Exception('Order not found during transfer.');
            }
            $lockedOrderRow = mysqli_fetch_assoc($lockedOrderResult);
            $lockedCurrentWarehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $lockedOrderRow, $defaultWarehouseId);
            if ($lockedCurrentWarehouseId === $newWarehouseId) {
                throw new Exception('The new warehouse must be different from the current warehouse.');
            }

            $stockOutOrderSql = "SELECT `id`
                FROM `stock_in_order`
                WHERE `status` = 'A'
                  AND COALESCE(NULLIF(TRIM(`stock_type`), ''), 'Stock In') = 'Stock Out'
                  AND `order_number` = '" . mysqli_real_escape_string($txnConnect, $orderCode) . "'
                ORDER BY `id` ASC
                FOR UPDATE";
            $stockOutOrderResult = mysqli_query($txnConnect, $stockOutOrderSql);
            $stockOutOrderIds = array();
            if ($stockOutOrderResult) {
                while ($stockOutOrderRow = mysqli_fetch_assoc($stockOutOrderResult)) {
                    $stockOutOrderIds[] = isset($stockOutOrderRow['id']) ? (int) $stockOutOrderRow['id'] : 0;
                }
            }
            $stockOutOrderIds = array_values(array_filter($stockOutOrderIds));
            if (empty($stockOutOrderIds)) {
                throw new Exception('No warehouse stock-out record was found for this order.');
            }

            $lockUsageSql = "SELECT `id`
                FROM `" . STOCK_OUT_BATCH_USAGE . "`
                WHERE `stock_out_order_id` IN (" . implode(',', $stockOutOrderIds) . ")
                  AND `status` = 'A'
                FOR UPDATE";
            mysqli_query($txnConnect, $lockUsageSql);

            $oldUsageRows = siGetStockOutUsageRowsByOrderIds($txnConnect, $stockOutOrderIds);
            if (empty($oldUsageRows)) {
                throw new Exception('No active stock-out batch usage was found for this order.');
            }

            $usageQtyMap = owtpSummarizeUsageQtyByProduct($oldUsageRows);
            if ($usageQtyMap !== $productQtyMap) {
                throw new Exception('Current stock-out batch usage does not match the recalculated product quantity for this order.');
            }

            $allocationsByProduct = array();
            foreach ($productQtyMap as $productId => $qty) {
                $allocationsByProduct[$productId] = siAllocateStockOutQuantityAcrossFifoBatches(
                    $txnConnect,
                    $newWarehouseId,
                    $productId,
                    $qty,
                    0,
                    0,
                    isset($productLabelMap[$productId]) ? $productLabelMap[$productId] : ('product #' . $productId),
                    $newWarehouseName
                );
            }

            $deactivateUsageSql = "UPDATE `" . STOCK_OUT_BATCH_USAGE . "`
                SET `status` = 'D'
                WHERE `stock_out_order_id` IN (" . implode(',', $stockOutOrderIds) . ")
                  AND `status` = 'A'";
            if (!mysqli_query($txnConnect, $deactivateUsageSql)) {
                throw new Exception('Failed to restore stock to the current warehouse.');
            }

            $safeActor = mysqli_real_escape_string($txnConnect, (string) USER_ID);
            $safeOrderCode = mysqli_real_escape_string($txnConnect, $orderCode);
            $insertStockOutOrderSql = "INSERT INTO `stock_in_order`
                (`warehouse_id`, `order_number`, `stock_in_date`, `attachment`, `stock_type`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . (int) $newWarehouseId . ", '" . $safeOrderCode . "', CURDATE(), '', 'Stock Out', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($txnConnect, $insertStockOutOrderSql)) {
                throw new Exception('Failed to create the new warehouse stock-out record.');
            }

            $newStockOutOrderId = (int) mysqli_insert_id($txnConnect);
            $newBatchUsageRows = array();
            foreach ($productQtyMap as $productId => $qty) {
                $insertStockOutItemSql = "INSERT INTO `stock_in_order_item`
                    (`stock_in_order_id`, `product_id`, `package_id`, `product_quantity`, `create_by`, `create_date`, `create_time`, `status`)
                    VALUES
                    (" . $newStockOutOrderId . ", " . (int) $productId . ", 0, " . (int) $qty . ", '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
                if (!mysqli_query($txnConnect, $insertStockOutItemSql)) {
                    throw new Exception('Failed to create the new warehouse stock-out item.');
                }

                $newStockOutItemId = (int) mysqli_insert_id($txnConnect);
                $allocations = isset($allocationsByProduct[$productId]) ? $allocationsByProduct[$productId] : array();
                siInsertStockOutBatchUsageRows($txnConnect, $newStockOutOrderId, $newStockOutItemId, $allocations, USER_ID);

                foreach ($allocations as $allocation) {
                    $newBatchUsageRows[] = array(
                        'stock_out_order_id' => $newStockOutOrderId,
                        'stock_out_item_id' => $newStockOutItemId,
                        'stock_in_order_id' => isset($allocation['stock_in_order_id']) ? (int) $allocation['stock_in_order_id'] : 0,
                        'stock_in_item_id' => isset($allocation['stock_in_item_id']) ? (int) $allocation['stock_in_item_id'] : 0,
                        'stock_in_order_number' => isset($allocation['stock_in_order_number']) ? (string) $allocation['stock_in_order_number'] : '',
                        'stock_in_date' => isset($allocation['stock_in_date']) ? (string) $allocation['stock_in_date'] : '',
                        'product_id' => (int) $productId,
                        'product_label' => isset($productLabelMap[$productId]) ? (string) $productLabelMap[$productId] : ('product #' . $productId),
                        'used_quantity' => isset($allocation['used_quantity']) ? (int) $allocation['used_quantity'] : 0,
                    );
                }
            }

            $orderUpdateClauses = array("`" . str_replace('`', '``', $warehouseField) . "` = " . (int) $newWarehouseId);
            if (shopeeOmsSourceHasColumn($cmsConnect, $financeConnect, $sourceConfig, 'update_by')) {
                $orderUpdateClauses[] = "`update_by` = '" . $safeActor . "'";
            }
            if (shopeeOmsSourceHasColumn($cmsConnect, $financeConnect, $sourceConfig, 'update_date')) {
                $orderUpdateClauses[] = "`update_date` = CURDATE()";
            }
            if (shopeeOmsSourceHasColumn($cmsConnect, $financeConnect, $sourceConfig, 'update_time')) {
                $orderUpdateClauses[] = "`update_time` = CURTIME()";
            }

            $orderUpdateSql = "UPDATE " . $sourceQualifiedTable . "
                SET " . implode(', ', $orderUpdateClauses) . "
                WHERE `id` = " . (int) $orderId . "
                  AND `status` = 'A'
                LIMIT 1";
            if (!mysqli_query($txnConnect, $orderUpdateSql) || mysqli_affected_rows($txnConnect) < 1) {
                throw new Exception('Failed to update the order warehouse.');
            }

            $productQtyPayload = array(
                'product_qty_map' => $productQtyMap,
                'product_summary_rows' => isset($productSummary['product_summary_rows']) ? $productSummary['product_summary_rows'] : array(),
                'package_summary_rows' => isset($productSummary['package_summary_rows']) ? $productSummary['package_summary_rows'] : array(),
            );

            $safePlatform = mysqli_real_escape_string($txnConnect, $platform);
            $safeOrderTable = mysqli_real_escape_string($txnConnect, isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '');
            $safeProductQtyJson = mysqli_real_escape_string($txnConnect, json_encode($productQtyPayload));
            $safeOldBatchJson = mysqli_real_escape_string($txnConnect, json_encode($oldUsageRows));
            $safeNewBatchJson = mysqli_real_escape_string($txnConnect, json_encode($newBatchUsageRows));
            $logSql = "INSERT INTO `" . $safeTransferLogTable . "`
                (`platform`, `order_table`, `order_id`, `order_code`, `old_warehouse_id`, `new_warehouse_id`, `product_qty_json`, `old_batch_usage_json`, `new_batch_usage_json`, `idempotency_key`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                ('" . $safePlatform . "', '" . $safeOrderTable . "', " . (int) $orderId . ", '" . $safeOrderCode . "', " . (int) $oldWarehouseId . ", " . (int) $newWarehouseId . ", '" . $safeProductQtyJson . "', '" . $safeOldBatchJson . "', '" . $safeNewBatchJson . "', '" . $safeIdempotencyKey . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($txnConnect, $logSql)) {
                if ((int) mysqli_errno($txnConnect) === 1062) {
                    throw new Exception('This transfer request was already processed.');
                }
                throw new Exception('Failed to save the warehouse transfer log.');
            }

            $platformLabel = isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platform);
            $auditMessage = htmlspecialchars(USER_NAME . ' transferred ' . $platformLabel . ' order ' . $orderCode . ' warehouse from ' . $oldWarehouseName . ' to ' . $newWarehouseName . '.', ENT_QUOTES, 'UTF-8');
            owtpInsertAuditLogRecord($txnConnect, $auditMessage, isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '', $orderUpdateSql, $oldWarehouseName, $newWarehouseName);

            mysqli_commit($txnConnect);

            return array(
                'success' => true,
                'message' => 'Warehouse transfer completed successfully.',
            );
        } catch (Exception $exception) {
            if ($txnConnect instanceof mysqli) {
                mysqli_rollback($txnConnect);
            }
            throw $exception;
        } finally {
            if ($txnConnect instanceof mysqli) {
                mysqli_close($txnConnect);
            }
        }
    }
}

$pinAccess = checkPinByGroupId($connect, 152);
if (!is_array($pinAccess)) {
    $pinAccess = array();
}

$action = trim((string) post('action'));
$searchOrderCode = trim((string) post('search_order_code')) !== '' ? trim((string) post('search_order_code')) : trim((string) post('order_code'));
$searchPlatform = post('search_platform') !== '' ? shopeeOmsNormalizePlatformKey(post('search_platform'), true) : (post('platform') !== '' ? shopeeOmsNormalizePlatformKey(post('platform'), true) : 'all');
if ($searchPlatform === '') {
    $searchPlatform = 'all';
}

switch ($action) {
    case 'search_order':
        if (!isActionAllowed('View', $pinAccess)) {
            owtpSetFlash('danger', 'You do not have permission to search orders.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl);
        }

        $postedSearchCsrf = (string) post('search_csrf');
        if (
            empty($_SESSION['order_warehouse_transfer_search_csrf'])
            || !hash_equals((string) $_SESSION['order_warehouse_transfer_search_csrf'], $postedSearchCsrf)
        ) {
            owtpSetFlash('danger', 'Invalid search session token. Please refresh and try again.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl);
        }

        $orderCode = trim((string) post('order_code'));
        $platform = post('platform') !== '' ? shopeeOmsNormalizePlatformKey(post('platform'), true) : 'all';
        if ($platform === '') {
            $platform = 'all';
        }

        if ($orderCode === '') {
            owtpSetFlash('danger', 'Please enter a valid order number.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl);
        }

        $_SESSION['order_warehouse_transfer_show_search_modal'] = '1';
        owtpRedirectToPage($orderWarehouseTransferPageUrl, $orderCode, $platform);
        break;

    case 'transfer_warehouse':
        if (!isActionAllowed('Transfer', $pinAccess)) {
            owtpSetFlash('danger', 'You do not have permission to transfer the warehouse.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        }

        $postedTransferCsrf = (string) post('transfer_csrf');
        if (
            empty($_SESSION['order_warehouse_transfer_submit_csrf'])
            || !hash_equals((string) $_SESSION['order_warehouse_transfer_submit_csrf'], $postedTransferCsrf)
        ) {
            owtpSetFlash('danger', 'Invalid transfer session token. Please refresh and try again.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        }

        $orderId = (int) post('order_id');
        $platform = post('platform') !== '' ? shopeeOmsNormalizePlatformKey(post('platform')) : '';
        $newWarehouseId = (int) post('new_warehouse_id');
        $transferNote = '';
        $idempotencyKey = trim((string) post('idempotency_key'));

        if ($orderId <= 0) {
            owtpSetFlash('danger', 'Invalid order selected for transfer.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        }
        if ($platform === '') {
            owtpSetFlash('danger', 'Invalid platform selected for transfer.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        }
        if ($newWarehouseId <= 0) {
            owtpSetFlash('danger', 'Please select a new warehouse.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            owtpSetFlash('danger', 'The transfer request key is invalid. Please refresh and try again.');
            owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        }

        try {
            $transferResult = owtpProcessTransfer($connect, $finance_connect, $orderId, $platform, $newWarehouseId, $transferNote, $idempotencyKey);
            owtpSetFlash(
                !empty($transferResult['duplicate']) ? 'warning' : 'success',
                isset($transferResult['message']) ? $transferResult['message'] : 'Warehouse transfer completed successfully.'
            );
        } catch (Exception $exception) {
            owtpSetFlash('danger', $exception->getMessage());
        }

        owtpRedirectToPage($orderWarehouseTransferPageUrl, $searchOrderCode, $searchPlatform);
        break;

    default:
        owtpSetFlash('danger', 'Invalid warehouse transfer action.');
        owtpRedirectToPage($orderWarehouseTransferPageUrl);
        break;
}
