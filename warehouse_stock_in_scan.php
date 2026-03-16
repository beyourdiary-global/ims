<?php
include_once 'init.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
siEnsureSchema($finance_connect, $stockInOrderTable, $stockInItemTable);

if (!function_exists('scanGetAllowedCountries')) {
    function scanGetAllowedCountries()
    {
        $raw = trim((string) getenv('SOR_QR_ALLOWED_COUNTRIES'));
        if ($raw === '') {
            $raw = 'MY';
        }
        $parts = explode(',', $raw);
        $list = array();
        foreach ($parts as $part) {
            $code = strtoupper(trim((string) $part));
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $list[$code] = true;
            }
        }
        return array_keys($list);
    }
}

if (!function_exists('scanGetClientIp')) {
    function scanGetClientIp()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        if ($ip === '') {
            return '';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}

if (!function_exists('scanIsPrivateOrReservedIp')) {
    function scanIsPrivateOrReservedIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

if (!function_exists('scanLookupCountryCode')) {
    function scanLookupCountryCode($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        // Do not attempt lookups for private or reserved IP ranges.
        if (scanIsPrivateOrReservedIp($ip)) {
            return '';
        }

        // Simple per-request cache to avoid repeated lookups for the same IP.
        static $cache = array();
        if (array_key_exists($ip, $cache)) {
            return $cache[$ip];
        }

        $code = '';

        // Prefer a local GeoIP database if available to avoid external calls.
        if (function_exists('geoip_country_code_by_name')) {
            $geoipCode = @geoip_country_code_by_name($ip);
            if (is_string($geoipCode)) {
                $geoipCode = strtoupper(trim($geoipCode));
                if (preg_match('/^[A-Z]{2}$/', $geoipCode)) {
                    $code = $geoipCode;
                }
            }
        }

        // Fallback to external service only if local lookup failed.
        if ($code === '') {
            $url = 'https://ipapi.co/' . rawurlencode($ip) . '/country/';
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 3,
                    'ignore_errors' => true,
                ),
                'ssl' => array(
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ),
            ));

            $resp = @file_get_contents($url, false, $context);
            if ($resp !== false) {
                $respCode = strtoupper(trim((string) $resp));
                if (preg_match('/^[A-Z]{2}$/', $respCode)) {
                    $code = $respCode;
                }
            }
        }

        // Cache even empty result to avoid repeated external lookups.
        $cache[$ip] = $code;
        return $code;
    }
}

if (!function_exists('scanSaveOrderSecure')) {
    function scanSaveOrderSecure($db, $orderTable, $itemTable, $warehouseId, $orderNumber, $items, $stockOrderRequestId, $actor)
    {
        $warehouseId = (int) $warehouseId;
        $stockOrderRequestId = (int) $stockOrderRequestId;
        $orderNumber = trim((string) $orderNumber);
        $actor = trim((string) $actor);

        if ($warehouseId <= 0 || $stockOrderRequestId <= 0 || $orderNumber === '' || count($items) === 0) {
            return array(false, 'Missing required stock in data.', 0, false);
        }

        if ($actor === '') {
            $actor = 'QR_PUBLIC';
        }

        mysqli_begin_transaction($db);

        $stockInOrderId = 0;
        $createdNewOrder = false;

        try {
            $checkSql = "SELECT id FROM `" . $orderTable . "` WHERE stock_order_request_id=? AND status='A' LIMIT 1";
            $checkStmt = mysqli_prepare($db, $checkSql);
            if (!$checkStmt) {
                throw new Exception('Failed to prepare duplicate check.');
            }
            mysqli_stmt_bind_param($checkStmt, 'i', $stockOrderRequestId);
            mysqli_stmt_execute($checkStmt);
            $checkRst = mysqli_stmt_get_result($checkStmt);
            if ($checkRst && ($existing = mysqli_fetch_assoc($checkRst))) {
                $stockInOrderId = (int) $existing['id'];
                mysqli_stmt_close($checkStmt);

                $countSql = "SELECT COUNT(1) AS item_count FROM `" . $itemTable . "` WHERE stock_in_order_id=? AND status='A'";
                $countStmt = mysqli_prepare($db, $countSql);
                if (!$countStmt) {
                    throw new Exception('Failed to prepare stock in item count check.');
                }
                mysqli_stmt_bind_param($countStmt, 'i', $stockInOrderId);
                mysqli_stmt_execute($countStmt);
                $countRst = mysqli_stmt_get_result($countStmt);
                $existingItemCount = 0;
                if ($countRst && ($countRow = mysqli_fetch_assoc($countRst))) {
                    $existingItemCount = isset($countRow['item_count']) ? (int) $countRow['item_count'] : 0;
                }
                mysqli_stmt_close($countStmt);

                if ($existingItemCount > 0) {
                    mysqli_commit($db);
                    return array(true, 'Stock In already exists for this stock order request.', $stockInOrderId, true);
                }

                // Repair mode: existing order found without items.
                $touchOrderSql = "UPDATE `" . $orderTable . "` SET stock_in_date=NOW() WHERE id=? LIMIT 1";
                $touchOrderStmt = mysqli_prepare($db, $touchOrderSql);
                if (!$touchOrderStmt) {
                    throw new Exception('Failed to prepare stock in order timestamp update.');
                }
                mysqli_stmt_bind_param($touchOrderStmt, 'i', $stockInOrderId);
                if (!mysqli_stmt_execute($touchOrderStmt)) {
                    mysqli_stmt_close($touchOrderStmt);
                    throw new Exception('Failed to update stock in date time.');
                }
                mysqli_stmt_close($touchOrderStmt);
            } else {
                mysqli_stmt_close($checkStmt);

                $insertOrderSql = "INSERT INTO `" . $orderTable . "`
                    (stock_order_request_id, warehouse_id, order_number, stock_in_date, create_by, create_date, create_time, status)
                    VALUES
                    (?, ?, ?, NOW(), ?, CURDATE(), CURTIME(), 'A')";
                $insertOrderStmt = mysqli_prepare($db, $insertOrderSql);
                if (!$insertOrderStmt) {
                    throw new Exception('Failed to prepare stock in order insert.');
                }
                mysqli_stmt_bind_param($insertOrderStmt, 'iiss', $stockOrderRequestId, $warehouseId, $orderNumber, $actor);
                if (!mysqli_stmt_execute($insertOrderStmt)) {
                    mysqli_stmt_close($insertOrderStmt);
                    throw new Exception('Failed to save stock in order.');
                }
                mysqli_stmt_close($insertOrderStmt);

                $stockInOrderId = (int) mysqli_insert_id($db);
                $createdNewOrder = true;
            }

            if ($stockInOrderId <= 0) {
                throw new Exception('Failed to resolve stock in order id.');
            }

            $insertItemSql = "INSERT INTO `" . $itemTable . "`
                (stock_in_order_id, product_id, package_id, product_quantity, create_by, create_date, create_time, status)
                VALUES
                (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'A')";
            $insertItemStmt = mysqli_prepare($db, $insertItemSql);
            if (!$insertItemStmt) {
                throw new Exception('Failed to prepare stock in item insert.');
            }

            $insertedCount = 0;
            foreach ($items as $item) {
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                $packageId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                mysqli_stmt_bind_param($insertItemStmt, 'iiiis', $stockInOrderId, $productId, $packageId, $qty, $actor);
                if (!mysqli_stmt_execute($insertItemStmt)) {
                    mysqli_stmt_close($insertItemStmt);
                    throw new Exception('Failed to save stock in item.');
                }
                $insertedCount++;
            }
            mysqli_stmt_close($insertItemStmt);

            if ($insertedCount <= 0) {
                throw new Exception('No valid item row to save.');
            }

            mysqli_commit($db);
            return array(true, 'Stock In saved successfully.', $stockInOrderId, false);
        } catch (Exception $ex) {
            mysqli_rollback($db);

            // Fallback cleanup for non-transactional table engines.
            if ($createdNewOrder && $stockInOrderId > 0) {
                mysqli_query($db, "DELETE FROM `" . $itemTable . "` WHERE stock_in_order_id='" . (int) $stockInOrderId . "'");
                mysqli_query($db, "DELETE FROM `" . $orderTable . "` WHERE id='" . (int) $stockInOrderId . "'");
            }

            return array(false, $ex->getMessage(), 0, false);
        }
    }
}

if (!function_exists('scanAuditLog')) {
    function scanAuditLog($event, $message, $context = array())
    {
        global $connect, $cdate, $ctime;

        $safeEvent = trim((string) $event);
        $safeMessage = trim((string) $message);
        if ($safeEvent === '') {
            $safeEvent = 'scan';
        }
        if ($safeMessage === '') {
            $safeMessage = 'No message.';
        }

        $ctxText = '';
        if (is_array($context) && count($context) > 0) {
            $pairs = array();
            foreach ($context as $k => $v) {
                $pairs[] = (string) $k . '=' . (is_array($v) ? implode(',', $v) : (string) $v);
            }
            $ctxText = ' [' . implode('; ', $pairs) . ']';
        }

        $auditConn = null;
        if (isset($connect) && ($connect instanceof mysqli)) {
            $auditConn = $connect;
        } else {
            $auditConn = @mysqli_connect(dbhost, dbuser, dbpwd, dbname);
        }
        if (!($auditConn instanceof mysqli)) {
            return;
        }

        $auditMessage = $safeEvent . ': ' . $safeMessage . $ctxText;
        $userId = (USER_ID !== '' ? USER_ID : 'QR_PUBLIC');
        $logDate = !empty($cdate) ? $cdate : date('Y-m-d');
        $logTime = !empty($ctime) ? $ctime : date('H:i:s');
        $screenType = 'Stock In QR Scan';
        $logAction = 9; // check

        try {
            $sql = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, user_id, action_message, create_date, create_time, create_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($auditConn, $sql);
            if (!$stmt) {
                return;
            }
            mysqli_stmt_bind_param($stmt, 'issssss', $logAction, $screenType, $userId, $auditMessage, $logDate, $logTime, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (Throwable $th) {
            // Keep QR stock-in flow alive even if audit logging fails.
            return;
        }
    }
}

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$packages = siLoadPackages($connect);
$packageProductMap = siBuildPackageProductMap($packages);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

$token = isset($_GET['t']) ? trim((string) $_GET['t']) : '';
$statusClass = 'danger';
$statusTitle = 'Stock In Failed';
$message = '';
$requestInfo = null;
$requestItems = array();
$stockInInfo = null;
$stockInItems = array();
$countryCode = '';
$clientIp = scanGetClientIp();
$allowedCountries = scanGetAllowedCountries();

if ($token === '') {
    $message = 'Missing stock in token.';
    scanAuditLog('token_invalid', $message);
} else if (strlen($token) > 200 || !preg_match('/^[A-Za-z0-9\-_\.=%]+$/', $token)) {
    $message = 'Invalid stock in token format.';
    scanAuditLog('token_invalid', $message, array('token_len' => strlen($token)));
} else {
    $requestId = sorDecodeToken($token);
    if ($requestId <= 0) {
        $message = 'Invalid token.';
        scanAuditLog('token_invalid', $message);
    } else {
        $reqSql = "SELECT id,
                          warehouse_id,
                          request_date,
                          tracking_no,
                          remark,
                          COALESCE(NULLIF(TRIM(invoice_no), ''), CONCAT('SOR-', id)) AS order_number
                   FROM " . STOCK_ORDER_REQ . "
                   WHERE id=? AND status='A'
                   LIMIT 1";
        $reqStmt = mysqli_prepare($finance_connect, $reqSql);
        if (!$reqStmt) {
            $message = 'Unable to validate stock order request.';
        } else {
            mysqli_stmt_bind_param($reqStmt, 'i', $requestId);
            mysqli_stmt_execute($reqStmt);
            $reqRst = mysqli_stmt_get_result($reqStmt);
            if ($reqRst) {
                $requestInfo = mysqli_fetch_assoc($reqRst);
            }
            mysqli_stmt_close($reqStmt);

            if (!is_array($requestInfo) || !isset($requestInfo['id'])) {
                $message = 'Invalid or inactive stock order request token.';
                scanAuditLog('request_invalid', $message, array('request_id' => (int) $requestId));
            } else {
                $itemSql = "SELECT product_id,
                                   package_id,
                                   IFNULL(productQty, IFNULL(packageQty, 1)) AS qty
                            FROM " . STOCK_ORDER_REQ_ITEM . "
                            WHERE request_id=? AND status='A'";
                $itemStmt = mysqli_prepare($finance_connect, $itemSql);
                if (!$itemStmt) {
                    $message = 'Unable to load order items.';
                } else {
                    mysqli_stmt_bind_param($itemStmt, 'i', $requestId);
                    mysqli_stmt_execute($itemStmt);
                    $itemRst = mysqli_stmt_get_result($itemStmt);
                    if ($itemRst) {
                        while ($it = mysqli_fetch_assoc($itemRst)) {
                            $prodId = isset($it['product_id']) ? (int) $it['product_id'] : 0;
                            $pkgId = isset($it['package_id']) ? (int) $it['package_id'] : 0;
                            $qty = isset($it['qty']) ? (int) $it['qty'] : 0;

                            if ($prodId <= 0) {
                                $prodId = siResolveProductIdFromPackage($packageProductMap, $pkgId);
                            }
                            if ($prodId <= 0 || $qty <= 0) {
                                continue;
                            }

                            $requestItems[] = array(
                                'product_id' => $prodId,
                                'package_id' => $pkgId,
                                'qty' => $qty,
                                'product_name' => isset($productNameMap[$prodId]) ? $productNameMap[$prodId] : ('Product #' . $prodId),
                            );
                        }
                    }
                    mysqli_stmt_close($itemStmt);
                }

                if ($message === '' && count($requestItems) === 0) {
                    $message = 'No valid item found in this stock order request.';
                    scanAuditLog('request_invalid', $message, array('request_id' => (int) $requestInfo['id']));
                }

                if ($message === '') {
                    $ipAllowed = false;
                    if ($clientIp !== '') {
                        if (scanIsPrivateOrReservedIp($clientIp)) {
                            // Treat private/reserved IPs as "unknown" for location policy purposes.
                            // Do not auto-allow; rely on explicit country checks for non-private IPs.
                            $countryCode = 'PRIVATE';
                        } else {
                            $countryCode = scanLookupCountryCode($clientIp);
                            if ($countryCode !== '' && in_array($countryCode, $allowedCountries, true)) {
                                $ipAllowed = true;
                            }
                        }
                    }

                    if (!$ipAllowed) {
                        scanAuditLog('security_block', 'Location policy blocked stock-in submission.', array(
                            'request_id' => (int) $requestInfo['id'],
                            'ip' => ($clientIp === '' ? 'Unknown' : $clientIp),
                            'country' => ($countryCode === '' ? 'Unknown' : $countryCode),
                            'allowed' => implode(',', $allowedCountries),
                        ));
                        $message = 'Access denied. Please contact administrator.';
                    } else {
                        scanAuditLog('security_pass', 'Location policy passed.', array(
                            'request_id' => (int) $requestInfo['id'],
                            'ip' => ($clientIp === '' ? 'Unknown' : $clientIp),
                            'country' => ($countryCode === '' ? 'Unknown' : $countryCode),
                        ));

                        $saveResult = scanSaveOrderSecure(
                            $finance_connect,
                            $stockInOrderTable,
                            $stockInItemTable,
                            (int) $requestInfo['warehouse_id'],
                            (string) $requestInfo['order_number'],
                            $requestItems,
                            (int) $requestInfo['id'],
                            (string) (USER_ID !== '' ? USER_ID : 'QR_PUBLIC')
                        );

                        $saveOk = isset($saveResult[0]) ? (bool) $saveResult[0] : false;
                        $saveMsg = isset($saveResult[1]) ? (string) $saveResult[1] : 'Unable to save stock in.';
                        $saveOrderId = isset($saveResult[2]) ? (int) $saveResult[2] : 0;
                        $alreadyExists = isset($saveResult[3]) ? (bool) $saveResult[3] : false;

                        if ($saveOk) {
                            $statusClass = $alreadyExists ? 'warning' : 'success';
                            $statusTitle = $alreadyExists ? 'Stock In Already Submitted' : 'Stock In Submitted Successfully';
                            $message = $saveMsg;
                            scanAuditLog('submit_success', $saveMsg, array(
                                'request_id' => (int) $requestInfo['id'],
                                'stock_in_id' => $saveOrderId,
                                'status' => ($alreadyExists ? 'already_exists' : 'created'),
                            ));

                            if ($saveOrderId > 0) {
                                $orderSql = "SELECT id, warehouse_id, order_number, stock_in_date, create_date, create_time
                                             FROM `" . $stockInOrderTable . "`
                                             WHERE id=? AND status='A' LIMIT 1";
                                $orderStmt = mysqli_prepare($finance_connect, $orderSql);
                                if ($orderStmt) {
                                    mysqli_stmt_bind_param($orderStmt, 'i', $saveOrderId);
                                    mysqli_stmt_execute($orderStmt);
                                    $orderRst = mysqli_stmt_get_result($orderStmt);
                                    if ($orderRst) {
                                        $stockInInfo = mysqli_fetch_assoc($orderRst);
                                    }
                                    mysqli_stmt_close($orderStmt);
                                }

                                $itemDetailSql = "SELECT product_id, product_quantity
                                                  FROM `" . $stockInItemTable . "`
                                                  WHERE stock_in_order_id=? AND status='A'
                                                  ORDER BY id ASC";
                                $itemDetailStmt = mysqli_prepare($finance_connect, $itemDetailSql);
                                if ($itemDetailStmt) {
                                    mysqli_stmt_bind_param($itemDetailStmt, 'i', $saveOrderId);
                                    mysqli_stmt_execute($itemDetailStmt);
                                    $itemDetailRst = mysqli_stmt_get_result($itemDetailStmt);
                                    if ($itemDetailRst) {
                                        while ($row = mysqli_fetch_assoc($itemDetailRst)) {
                                            $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                                            $stockInItems[] = array(
                                                'product_name' => isset($productNameMap[$pid]) ? $productNameMap[$pid] : ('Product #' . $pid),
                                                'qty' => isset($row['product_quantity']) ? (int) $row['product_quantity'] : 0,
                                            );
                                        }
                                    }
                                    mysqli_stmt_close($itemDetailStmt);
                                }
                            }
                        } else {
                            $message = $saveMsg;
                            scanAuditLog('submit_failed', $saveMsg, array(
                                'request_id' => (int) $requestInfo['id'],
                            ));
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock In Scan Result</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; margin: 0; background: linear-gradient(135deg, #f4f8fb 0%, #eaf2f9 100%); color: #1f2d3d; }
        .container { max-width: 900px; margin: 32px auto; background: #fff; border-radius: 14px; box-shadow: 0 14px 40px rgba(22, 56, 89, 0.12); padding: 24px; }
        .title { margin: 0 0 8px 0; font-size: 28px; }
        .subtitle { margin: 0 0 16px 0; color: #5f7185; }
        .alert { border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; border: 1px solid transparent; }
        .alert-success { background: #edf9f0; border-color: #b8e0c1; color: #1a6b2f; }
        .alert-warning { background: #fff8e9; border-color: #f5d28b; color: #7a5600; }
        .alert-danger { background: #ffeef0; border-color: #f3bdc5; color: #8a2230; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .card { border: 1px solid #e2ebf3; border-radius: 10px; padding: 12px; background: #fbfdff; }
        .card h4 { margin: 0 0 10px 0; font-size: 16px; }
        .k { color: #5f7185; }
        .v { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d8e3ee; padding: 8px; text-align: left; }
        th { background: #f3f8fd; }
        .small { font-size: 12px; color: #617487; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title"><?= siEsc($statusTitle) ?></h1>
        <p class="subtitle">Stock Order Request QR Scan</p>

        <div class="alert alert-<?= siEsc($statusClass) ?>"><?= siEsc($message) ?></div>

        <div class="grid">
            <?php if (is_array($requestInfo)) { ?>
            <div class="card">
                <h4>Stock Order Details</h4>
                <div><span class="k">Request ID:</span> <span class="v"><?= (int) $requestInfo['id'] ?></span></div>
                <div><span class="k">Order Number:</span> <span class="v"><?= siEsc((string) $requestInfo['order_number']) ?></span></div>
                <div><span class="k">Warehouse:</span> <span class="v"><?= siEsc(isset($warehouseNameMap[(int) $requestInfo['warehouse_id']]) ? $warehouseNameMap[(int) $requestInfo['warehouse_id']] : ('Warehouse #' . (int) $requestInfo['warehouse_id'])) ?></span></div>
                <div><span class="k">Request Date:</span> <span class="v"><?= siEsc((string) $requestInfo['request_date']) ?></span></div>
                <div><span class="k">Tracking No:</span> <span class="v"><?= siEsc((string) (isset($requestInfo['tracking_no']) ? $requestInfo['tracking_no'] : '')) ?></span></div>
            </div>
            <?php } ?>
        </div>

        <?php if (count($requestItems) > 0) { ?>
            <h3>Requested Items</h3>
            <table>
                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Product</th>
                        <th width="140">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requestItems as $idx => $it) { ?>
                        <tr>
                            <td><?= (int) ($idx + 1) ?></td>
                            <td><?= siEsc((string) $it['product_name']) ?></td>
                            <td><?= (int) $it['qty'] ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

        <?php if (is_array($stockInInfo)) { ?>
            <h3 style="margin-top: 20px;">Stock In Submission</h3>
            <div class="grid">
                <div class="card">
                    <div><span class="k">Stock In ID:</span> <span class="v"><?= (int) $stockInInfo['id'] ?></span></div>
                    <div><span class="k">Order Number:</span> <span class="v"><?= siEsc((string) $stockInInfo['order_number']) ?></span></div>
                    <div><span class="k">Stock In Date:</span> <span class="v"><?= siEsc((string) $stockInInfo['stock_in_date']) ?></span></div>
                    <div><span class="k">Created Date:</span> <span class="v"><?= siEsc((string) $stockInInfo['create_date']) ?> <?= siEsc((string) $stockInInfo['create_time']) ?></span></div>
                </div>
            </div>

            <?php if (count($stockInItems) > 0) { ?>
                <table>
                    <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>Product</th>
                            <th width="140">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockInItems as $idx => $it) { ?>
                            <tr>
                                <td><?= (int) ($idx + 1) ?></td>
                                <td><?= siEsc((string) $it['product_name']) ?></td>
                                <td><?= (int) $it['qty'] ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        <?php } ?>

        <p class="small" style="margin-top:16px;">If you are blocked unexpectedly, please contact administrator.</p>
    </div>
</body>
</html>
