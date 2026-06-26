<?php

if (!defined('LUCKY_DRAW_ADMIN_PIN_GROUP_ID')) {
    define('LUCKY_DRAW_ADMIN_PIN_GROUP_ID', 159);
}

if (!defined('LUCKY_DRAW_RATE_LIMIT_WINDOW_SEC')) {
    define('LUCKY_DRAW_RATE_LIMIT_WINDOW_SEC', 600);
}

if (!defined('LUCKY_DRAW_RATE_LIMIT_MAX_PER_IP')) {
    define('LUCKY_DRAW_RATE_LIMIT_MAX_PER_IP', 20);
}

if (!defined('LUCKY_DRAW_RATE_LIMIT_MAX_PER_MEMBER')) {
    define('LUCKY_DRAW_RATE_LIMIT_MAX_PER_MEMBER', 5);
}

if (!defined('LUCKY_DRAW_CLAIM_EXPIRY_HOURS')) {
    define('LUCKY_DRAW_CLAIM_EXPIRY_HOURS', 24);
}

if (!defined('LUCKY_DRAW_VOUCHER_RESERVATION_EXPIRY_MINUTES')) {
    define('LUCKY_DRAW_VOUCHER_RESERVATION_EXPIRY_MINUTES', 60);
}

if (!function_exists('luckyDrawGetEnvValue')) {
    function luckyDrawGetEnvValue($key)
    {
        $key = trim((string) $key);
        if ($key === '') {
            return '';
        }

        if (function_exists('commonMailGetEnvValue')) {
            return commonMailGetEnvValue($key);
        }

        $value = getenv($key);
        return ($value !== false && !is_array($value)) ? trim((string) $value) : '';
    }
}

if (!function_exists('luckyDrawResolveConfigValue')) {
    function luckyDrawResolveConfigValue($configuredValue, $fallbackEnvKey = '')
    {
        $configuredValue = trim((string) $configuredValue);
        $fallbackEnvKey = trim((string) $fallbackEnvKey);

        if ($configuredValue !== '') {
            $resolvedEnvValue = luckyDrawGetEnvValue($configuredValue);
            if ($resolvedEnvValue !== '') {
                return $resolvedEnvValue;
            }

            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $configuredValue)) {
                return $configuredValue;
            }
        }

        if ($fallbackEnvKey !== '') {
            return luckyDrawGetEnvValue($fallbackEnvKey);
        }

        return '';
    }
}

if (!function_exists('luckyDrawGetRecaptchaSiteKey')) {
    function luckyDrawGetRecaptchaSiteKey()
    {
        return luckyDrawResolveConfigValue(
            defined('LUCKY_DRAW_RECAPTCHA_SITE_KEY_ENV') ? LUCKY_DRAW_RECAPTCHA_SITE_KEY_ENV : '',
            'LUCKY_DRAW_RECAPTCHA_SITE_KEY'
        );
    }
}

if (!function_exists('luckyDrawGetRecaptchaSecretKey')) {
    function luckyDrawGetRecaptchaSecretKey()
    {
        return luckyDrawResolveConfigValue(
            defined('LUCKY_DRAW_RECAPTCHA_SECRET_KEY_ENV') ? LUCKY_DRAW_RECAPTCHA_SECRET_KEY_ENV : '',
            'LUCKY_DRAW_RECAPTCHA_SECRET_KEY'
        );
    }
}

if (!function_exists('luckyDrawNormalizeFullId')) {
    function luckyDrawNormalizeFullId($value)
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return '';
        }

        return preg_replace('/[^A-Z0-9]/', '', $value);
    }
}

if (!function_exists('luckyDrawSafePublicText')) {
    function luckyDrawSafePublicText($value, $maxLength = 255)
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        if ($maxLength > 0) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }
}

if (!function_exists('luckyDrawComputeSha256')) {
    function luckyDrawComputeSha256($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return hash('sha256', $value);
    }
}

if (!function_exists('luckyDrawMemberIdHmac')) {
    function luckyDrawMemberIdHmac($normalizedId)
    {
        return luckyDrawComputeSha256(luckyDrawNormalizeFullId($normalizedId));
    }
}

if (!function_exists('luckyDrawIpHmac')) {
    function luckyDrawIpHmac($ipAddress)
    {
        return luckyDrawComputeSha256(trim((string) $ipAddress));
    }
}

if (!function_exists('luckyDrawExtractYymmddFromId')) {
    function luckyDrawExtractYymmddFromId($normalizedId)
    {
        $normalizedId = luckyDrawNormalizeFullId($normalizedId);
        if (preg_match('/^\d{6}/', $normalizedId, $matches)) {
            return (string) $matches[0];
        }

        return '';
    }
}

if (!function_exists('luckyDrawBirthdayMdFromYymmdd')) {
    function luckyDrawBirthdayMdFromYymmdd($yymmdd)
    {
        $yymmdd = trim((string) $yymmdd);
        return preg_match('/^\d{6}$/', $yymmdd) ? substr($yymmdd, 2, 4) : '';
    }
}

if (!function_exists('luckyDrawGetRemoteIp')) {
    function luckyDrawGetRemoteIp()
    {
        $candidates = array(
            isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : '',
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : '',
            isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
        );

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('luckyDrawJsonResponse')) {
    function luckyDrawJsonResponse($payload, $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code((int) $statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('luckyDrawGetCsrfToken')) {
    function luckyDrawGetCsrfToken($sessionKey = 'lucky_draw_csrf_token')
    {
        $sessionKey = trim((string) $sessionKey);
        if ($sessionKey === '') {
            $sessionKey = 'lucky_draw_csrf_token';
        }

        if (empty($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[$sessionKey];
    }
}

if (!function_exists('luckyDrawValidateCsrfToken')) {
    function luckyDrawValidateCsrfToken($token, $sessionKey = 'lucky_draw_csrf_token')
    {
        $sessionToken = isset($_SESSION[$sessionKey]) ? (string) $_SESSION[$sessionKey] : '';
        $token = (string) $token;
        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('luckyDrawStoragePath')) {
    function luckyDrawStoragePath($suffix = '')
    {
        $basePath = rtrim(ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'lucky_draw';
        $suffix = trim((string) $suffix, '/\\');
        return $suffix === '' ? $basePath : ($basePath . DIRECTORY_SEPARATOR . $suffix);
    }
}

if (!function_exists('luckyDrawEnsureDirectory')) {
    function luckyDrawEnsureDirectory($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return false;
        }

        if (!is_dir($path)) {
            return @mkdir($path, 0777, true);
        }

        return true;
    }
}

if (!function_exists('luckyDrawPublicAssetUrl')) {
    function luckyDrawPublicAssetUrl($relativePath)
    {
        $relativePath = ltrim((string) $relativePath, '/');
        return siteUrlPath('uploads/lucky_draw/' . $relativePath);
    }
}

if (!function_exists('luckyDrawStorePrizeImageUpload')) {
    function luckyDrawStorePrizeImageUpload($file)
    {
        if (!is_array($file) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'path' => '', 'message' => 'Please choose a valid image file.');
        }

        $allowedExtensions = array('png', 'jpg', 'jpeg', 'webp', 'gif');
        $originalName = isset($file['name']) ? (string) $file['name'] : '';
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return array('success' => false, 'path' => '', 'message' => 'Only PNG, JPG, JPEG, WEBP, or GIF images are allowed.');
        }

        $targetDir = luckyDrawStoragePath('prizes');
        if (!luckyDrawEnsureDirectory($targetDir)) {
            return array('success' => false, 'path' => '', 'message' => 'Unable to prepare the prize image folder.');
        }

        $fileName = 'prize_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            return array('success' => false, 'path' => '', 'message' => 'Unable to upload the prize image.');
        }

        return array(
            'success' => true,
            'path' => 'prizes/' . $fileName,
            'message' => '',
        );
    }
}

if (!function_exists('luckyDrawAdminPinAccess')) {
    function luckyDrawAdminPinAccess($connect)
    {
        return checkPinByGroupId($connect, LUCKY_DRAW_ADMIN_PIN_GROUP_ID);
    }
}

if (!function_exists('luckyDrawRequireAdminAction')) {
    function luckyDrawRequireAdminAction($connect, $action = 'View', $pinAccess = null)
    {
        if (!is_array($pinAccess)) {
            $pinAccess = luckyDrawAdminPinAccess($connect);
        }

        if (!isActionAllowed($action, $pinAccess)) {
            renderNotificationScript('You do not have permission to access Lucky Draw.', 'error', siteUrlPath(ROUTE_DASHBOARD), 1200, true);
            exit;
        }

        return $pinAccess;
    }
}

if (!function_exists('luckyDrawNormalizeFlag')) {
    function luckyDrawNormalizeFlag($value, $defaultValue = 'N')
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            $value = strtoupper(trim((string) $defaultValue));
        }
        return $value === 'Y' ? 'Y' : 'N';
    }
}

if (!function_exists('luckyDrawNormalizePositiveInt')) {
    function luckyDrawNormalizePositiveInt($value, $defaultValue = 0)
    {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return (int) $defaultValue;
        }

        $value = (int) round((float) $value);
        return $value >= 0 ? $value : (int) $defaultValue;
    }
}

if (!function_exists('luckyDrawNormalizePositiveFloat')) {
    function luckyDrawNormalizePositiveFloat($value, $defaultValue = 0.0)
    {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return (float) $defaultValue;
        }

        $value = round((float) $value, 6);
        return $value >= 0 ? $value : (float) $defaultValue;
    }
}

if (!function_exists('luckyDrawTableEngine')) {
    function luckyDrawTableEngine($connect, $dbName, $tableName)
    {
        if (!($connect instanceof mysqli)) {
            return '';
        }

        $safeDb = mysqli_real_escape_string($connect, (string) $dbName);
        $safeTable = mysqli_real_escape_string($connect, (string) $tableName);
        $sql = "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $safeDb . "' AND TABLE_NAME = '" . $safeTable . "' LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return strtoupper(trim((string) (isset($row['ENGINE']) ? $row['ENGINE'] : '')));
        }

        return '';
    }
}

if (!function_exists('luckyDrawRequiredTableList')) {
    function luckyDrawRequiredTableList()
    {
        return array(
            LUCKY_DRAW_PRIZE,
            LUCKY_DRAW_VOUCHER_CODE,
            LUCKY_DRAW_DRAW_LOG,
            LUCKY_DRAW_VIRTUAL_WINNER,
            LUCKY_DRAW_REQUEST_LOG,
        );
    }
}

if (!function_exists('luckyDrawCountRows')) {
    function luckyDrawCountRows($financeConnect, $tableName, $whereSql = "status = 'A'")
    {
        if (!($financeConnect instanceof mysqli) || trim((string) $tableName) === '') {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS total_count FROM `" . $tableName . "` WHERE " . $whereSql;
        $result = mysqli_query($financeConnect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return isset($row['total_count']) ? (int) $row['total_count'] : 0;
        }

        return 0;
    }
}

if (!function_exists('luckyDrawPrizeImageUrl')) {
    function luckyDrawPrizeImageUrl($relativePath)
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return '';
        }

        return luckyDrawPublicAssetUrl($relativePath);
    }
}

if (!function_exists('luckyDrawVoucherAvailableCounts')) {
    function luckyDrawVoucherAvailableCounts($connect)
    {
        $counts = array();
        if (!($connect instanceof mysqli)) {
            return $counts;
        }

        $sql = "SELECT prize_id, COUNT(id) AS available_count
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE status = 'A'
              AND code_state = 'available'
            GROUP BY prize_id";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $counts[(int) $row['prize_id']] = (int) $row['available_count'];
            }
        }

        return $counts;
    }
}

if (!function_exists('luckyDrawVoucherStateCounts')) {
    function luckyDrawVoucherStateCounts($connect, $prizeIds = array(), $lockRows = false)
    {
        $counts = array();
        if (!($connect instanceof mysqli)) {
            return $counts;
        }

        $sanitizedPrizeIds = array();
        foreach ((array) $prizeIds as $prizeId) {
            $prizeId = (int) $prizeId;
            if ($prizeId > 0) {
                $sanitizedPrizeIds[] = $prizeId;
            }
        }
        $sanitizedPrizeIds = array_values(array_unique($sanitizedPrizeIds));

        $sql = "SELECT prize_id, code_state, COUNT(id) AS total_count
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE status = 'A'";
        if (!empty($sanitizedPrizeIds)) {
            $sql .= " AND prize_id IN (" . implode(',', $sanitizedPrizeIds) . ")";
        }
        $sql .= " GROUP BY prize_id, code_state";
        if ($lockRows) {
            $sql .= " FOR UPDATE";
        }

        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $counts[(int) $row['prize_id']][(string) $row['code_state']] = (int) $row['total_count'];
            }
        }

        return $counts;
    }
}

if (!function_exists('luckyDrawFetchPrizeRows')) {
    function luckyDrawFetchPrizeRows($connect, $enabledOnly = false)
    {
        if (!($connect instanceof mysqli)) {
            return array();
        }

        $conditions = array("status = 'A'");
        if ($enabledOnly) {
            $conditions[] = "is_enabled = 'Y'";
        }

        $rows = array();
        $result = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_PRIZE . "` WHERE " . implode(' AND ', $conditions) . " ORDER BY display_order ASC, id ASC");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = (array) $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('luckyDrawPrizeAvailableUnits')) {
    function luckyDrawPrizeAvailableUnits($prizeRow, $voucherAvailableCount = 0, $voucherReservedCount = 0, $voucherAssignedCount = 0)
    {
        $prizeType = strtolower(trim((string) (isset($prizeRow['prize_type']) ? $prizeRow['prize_type'] : '')));
        if ($prizeType === 'voucher') {
            $voucherAvailableCount = max(0, (int) $voucherAvailableCount);
            $voucherReservedCount = max(0, (int) $voucherReservedCount);
            $voucherAssignedCount = max(0, (int) $voucherAssignedCount);
            $totalStock = isset($prizeRow['total_stock']) ? (int) $prizeRow['total_stock'] : 0;

            if ($voucherAvailableCount > 0) {
                if ($totalStock > 0) {
                    $remainingVoucherStock = max(0, $totalStock - $voucherReservedCount - $voucherAssignedCount);
                    return min($voucherAvailableCount, $remainingVoucherStock);
                }

                return $voucherAvailableCount;
            }

            $reservedStock = isset($prizeRow['reserved_stock']) ? (int) $prizeRow['reserved_stock'] : 0;
            $assignedStock = isset($prizeRow['assigned_stock']) ? (int) $prizeRow['assigned_stock'] : 0;

            return $totalStock > 0 ? max(0, $totalStock - $reservedStock - $assignedStock) : 0;
        }

        $totalStock = isset($prizeRow['total_stock']) ? (int) $prizeRow['total_stock'] : 0;
        $reservedStock = isset($prizeRow['reserved_stock']) ? (int) $prizeRow['reserved_stock'] : 0;
        $assignedStock = isset($prizeRow['assigned_stock']) ? (int) $prizeRow['assigned_stock'] : 0;
        return max(0, $totalStock - $reservedStock - $assignedStock);
    }
}

if (!function_exists('luckyDrawValidatePrizeDefaults')) {
    function luckyDrawValidatePrizeDefaults($prizeRow)
    {
        $prizeType = strtolower(trim((string) (isset($prizeRow['prize_type']) ? $prizeRow['prize_type'] : '')));
        if ($prizeType !== 'physical') {
            return array('success' => true, 'message' => '');
        }

        if ((int) (isset($prizeRow['package_id']) ? $prizeRow['package_id'] : 0) <= 0) {
            return array('success' => false, 'message' => 'Package is required for physical prizes.');
        }

        return array('success' => true, 'message' => '');
    }
}

if (!function_exists('luckyDrawBuildStockValidationRow')) {
    function luckyDrawBuildStockValidationRow($prizeRow)
    {
        return array(
            'package' => (string) (int) (isset($prizeRow['package_id']) ? $prizeRow['package_id'] : 0),
            'stock_out_warehouse_id' => (int) (isset($prizeRow['stock_out_warehouse_id']) ? $prizeRow['stock_out_warehouse_id'] : 0),
            '__oms_platform' => 'facebook',
        );
    }
}

if (!function_exists('luckyDrawValidatePhysicalPrizeStock')) {
    function luckyDrawValidatePhysicalPrizeStock($connect, $financeConnect, $prizeRow)
    {
        $defaultsCheck = luckyDrawValidatePrizeDefaults($prizeRow);
        if (empty($defaultsCheck['success'])) {
            return $defaultsCheck;
        }

        $packageId = (int) (isset($prizeRow['package_id']) ? $prizeRow['package_id'] : 0);
        $warehouseId = (int) (isset($prizeRow['stock_out_warehouse_id']) ? $prizeRow['stock_out_warehouse_id'] : 0);
        if ($packageId <= 0 || $warehouseId <= 0) {
            return array('success' => true, 'message' => '');
        }

        $validationRow = luckyDrawBuildStockValidationRow($prizeRow);
        return shopeeOmsValidateWarehouseStockForOrder($connect, $financeConnect, $validationRow, array(
            'platform' => 'facebook',
            'warehouse_id' => (int) $validationRow['stock_out_warehouse_id'],
        ));
    }
}

if (!function_exists('luckyDrawUrbanRegisteredCount')) {
    function luckyDrawUrbanRegisteredCount($connect)
    {
        if (!($connect instanceof mysqli)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS total_count FROM `" . URBAN_CUST_REG . "` WHERE ic IS NOT NULL AND TRIM(ic) <> ''";
        $result = mysqli_query($connect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return (int) (isset($row['total_count']) ? $row['total_count'] : 0);
        }

        return 0;
    }
}

if (!function_exists('luckyDrawReadiness')) {
    function luckyDrawReadiness($connect, $financeConnect)
    {
        $items = array();
        $hasErrors = false;

        foreach (luckyDrawRequiredTableList() as $tableName) {
            $engine = luckyDrawTableEngine($connect, dbname, $tableName);
            $tableReady = $engine === 'INNODB';
            if (!$tableReady) {
                $hasErrors = true;
            }
            $items[] = array(
                'key' => 'table_' . $tableName,
                'label' => $tableName . ' engine',
                'success' => $tableReady,
                'detail' => $tableReady ? 'InnoDB ready.' : ('Current engine: ' . ($engine !== '' ? $engine : 'missing')),
            );
        }

        $fbEngine = luckyDrawTableEngine($financeConnect, dbFinance, FB_ORDER_REQ);
        $fbReady = $fbEngine === 'INNODB';
        if (!$fbReady) {
            $hasErrors = true;
        }
        $items[] = array(
            'key' => 'facebook_order_request_engine',
            'label' => FB_ORDER_REQ . ' engine',
            'success' => $fbReady,
            'detail' => $fbReady ? 'InnoDB ready.' : ('Current engine: ' . ($fbEngine !== '' ? $fbEngine : 'missing')),
        );

        $siteKeyReady = luckyDrawGetRecaptchaSiteKey() !== '';
        $secretReady = luckyDrawGetRecaptchaSecretKey() !== '';
        if (!$siteKeyReady || !$secretReady) {
            $hasErrors = true;
        }

        $items[] = array(
            'key' => 'identity_hashing',
            'label' => 'SHA-256 identity hashing',
            'success' => true,
            'detail' => 'Lucky Draw now hashes member identity and IP with SHA-256.',
        );
        $items[] = array(
            'key' => 'recaptcha_keys',
            'label' => 'reCAPTCHA keys configured',
            'success' => ($siteKeyReady && $secretReady),
            'detail' => ($siteKeyReady && $secretReady) ? 'Site key and secret key found.' : 'Missing reCAPTCHA env config.',
        );

        $registeredCount = luckyDrawUrbanRegisteredCount($connect);
        if ($registeredCount <= 0) {
            $hasErrors = true;
        }
        $items[] = array(
            'key' => 'urban_customer_source',
            'label' => 'URBAN customer IC source',
            'success' => $registeredCount > 0,
            'detail' => $registeredCount > 0 ? ($registeredCount . ' row(s) with IC found.') : 'No URBAN customer IC rows found.',
        );

        $prizeRows = luckyDrawFetchPrizeRows($connect, true);
        $voucherCounts = luckyDrawVoucherAvailableCounts($connect);
        $voucherStateCounts = luckyDrawVoucherStateCounts($connect);
        $readyPrizeCount = 0;
        foreach ($prizeRows as $prizeRow) {
            $prizeId = isset($prizeRow['id']) ? (int) $prizeRow['id'] : 0;
            $voucherReservedCount = (int) ($voucherStateCounts[$prizeId]['reserved'] ?? 0);
            $voucherAssignedCount = (int) (($voucherStateCounts[$prizeId]['assigned'] ?? 0) + ($voucherStateCounts[$prizeId]['sent'] ?? 0));
            $availability = luckyDrawPrizeAvailableUnits(
                $prizeRow,
                isset($voucherCounts[$prizeId]) ? (int) $voucherCounts[$prizeId] : 0,
                $voucherReservedCount,
                $voucherAssignedCount
            );
            $defaultsCheck = luckyDrawValidatePrizeDefaults($prizeRow);
            $stockCheck = array('success' => true, 'message' => '');
            if (strtolower((string) ($prizeRow['prize_type'] ?? '')) === 'physical') {
                $stockCheck = luckyDrawValidatePhysicalPrizeStock($connect, $financeConnect, $prizeRow);
            }

            $prizeReady = !empty($defaultsCheck['success']) && !empty($stockCheck['success']) && $availability > 0;
            if ($prizeReady) {
                $readyPrizeCount++;
            } else {
                $hasErrors = true;
            }

            $detailParts = array('Availability: ' . $availability);
            if (strtolower((string) ($prizeRow['prize_type'] ?? '')) === 'voucher' && $availability <= 0) {
                $detailParts[] = 'Voucher availability uses the lower of remaining Total Stock and imported available voucher codes.';
            }
            if (empty($defaultsCheck['success'])) {
                $detailParts[] = $defaultsCheck['message'];
            }
            if (empty($stockCheck['success'])) {
                $detailParts[] = $stockCheck['message'];
            }

            $items[] = array(
                'key' => 'prize_' . $prizeId,
                'label' => 'Prize: ' . (isset($prizeRow['prize_name']) ? $prizeRow['prize_name'] : ('#' . $prizeId)),
                'success' => $prizeReady,
                'detail' => implode(' | ', $detailParts),
            );
        }

        $items[] = array(
            'key' => 'active_prizes',
            'label' => 'Active prize pool',
            'success' => $readyPrizeCount > 0,
            'detail' => $readyPrizeCount > 0 ? ($readyPrizeCount . ' prize(s) ready.') : 'No active ready prize found.',
        );

        if ($readyPrizeCount <= 0) {
            $hasErrors = true;
        }

        return array(
            'success' => !$hasErrors,
            'items' => $items,
        );
    }
}

if (!function_exists('luckyDrawValidateRecaptchaToken')) {
    function luckyDrawValidateRecaptchaToken($token, $remoteIp = '')
    {
        $secretKey = luckyDrawGetRecaptchaSecretKey();
        $token = trim((string) $token);
        if ($secretKey === '' || $token === '') {
            return array('success' => false, 'message' => 'Human verification is not available right now.');
        }

        $postFields = http_build_query(array(
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => trim((string) $remoteIp),
        ));

        $responseBody = '';
        if (function_exists('curl_init')) {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $responseBody = (string) curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $postFields,
                    'timeout' => 12,
                ),
            ));
            $responseBody = (string) @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        }

        if ($responseBody === '') {
            return array('success' => false, 'message' => 'Human verification failed. Please try again.');
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return array('success' => false, 'message' => 'Human verification failed. Please refresh and try again.');
        }

        return array('success' => true, 'message' => '');
    }
}

if (!function_exists('luckyDrawRecordRequestLog')) {
    function luckyDrawRecordRequestLog($connect, $requestType, $memberHmac, $ipHmac, $requestState)
    {
        if (!($connect instanceof mysqli)) {
            return false;
        }

        $requestType = mysqli_real_escape_string($connect, trim((string) $requestType));
        $memberHmac = mysqli_real_escape_string($connect, trim((string) $memberHmac));
        $ipHmac = mysqli_real_escape_string($connect, trim((string) $ipHmac));
        $requestState = mysqli_real_escape_string($connect, trim((string) $requestState));

        $sql = "INSERT INTO `" . LUCKY_DRAW_REQUEST_LOG . "`
            (`request_type`, `member_id_hmac`, `ip_hmac`, `request_state`, `created_at`, `status`)
            VALUES
            ('" . $requestType . "', '" . $memberHmac . "', '" . $ipHmac . "', '" . $requestState . "', NOW(), 'A')";

        return (bool) mysqli_query($connect, $sql);
    }
}

if (!function_exists('luckyDrawCheckRateLimit')) {
    function luckyDrawCheckRateLimit($connect, $memberHmac, $ipHmac, $requestType = 'draw_attempt')
    {
        if (!($connect instanceof mysqli)) {
            return array('success' => false, 'message' => 'Rate limit validation is unavailable.');
        }

        $safeType = mysqli_real_escape_string($connect, trim((string) $requestType));
        $windowExpr = "DATE_SUB(NOW(), INTERVAL " . max(60, (int) LUCKY_DRAW_RATE_LIMIT_WINDOW_SEC) . " SECOND)";
        $maxPerIp = max(1, (int) LUCKY_DRAW_RATE_LIMIT_MAX_PER_IP);
        $maxPerMember = max(1, (int) LUCKY_DRAW_RATE_LIMIT_MAX_PER_MEMBER);

        $ipCount = 0;
        if ($ipHmac !== '') {
            $safeIp = mysqli_real_escape_string($connect, $ipHmac);
            $ipResult = mysqli_query($connect, "SELECT COUNT(*) AS total_count FROM `" . LUCKY_DRAW_REQUEST_LOG . "`
                WHERE status = 'A'
                  AND request_type = '" . $safeType . "'
                  AND ip_hmac = '" . $safeIp . "'
                  AND created_at >= " . $windowExpr);
            if ($ipResult && ($ipRow = mysqli_fetch_assoc($ipResult))) {
                $ipCount = (int) $ipRow['total_count'];
            }
        }

        if ($ipCount >= $maxPerIp) {
            return array('success' => false, 'message' => 'Too many attempts. Please try again later.');
        }

        $memberCount = 0;
        if ($memberHmac !== '') {
            $safeMember = mysqli_real_escape_string($connect, $memberHmac);
            $memberResult = mysqli_query($connect, "SELECT COUNT(*) AS total_count FROM `" . LUCKY_DRAW_REQUEST_LOG . "`
                WHERE status = 'A'
                  AND request_type = '" . $safeType . "'
                  AND member_id_hmac = '" . $safeMember . "'
                  AND created_at >= " . $windowExpr);
            if ($memberResult && ($memberRow = mysqli_fetch_assoc($memberResult))) {
                $memberCount = (int) $memberRow['total_count'];
            }
        }

        if ($memberCount >= $maxPerMember) {
            return array('success' => false, 'message' => 'This member has reached the retry limit for now.');
        }

        return array('success' => true, 'message' => '');
    }
}

if (!function_exists('luckyDrawPickWeightedPrize')) {
    function luckyDrawPickWeightedPrize($eligiblePrizeRows)
    {
        $weightedTotal = 0.0;
        foreach ((array) $eligiblePrizeRows as $prizeRow) {
            $weightedTotal += max(0, (float) (isset($prizeRow['weight']) ? $prizeRow['weight'] : 0));
        }

        if ($weightedTotal <= 0) {
            return array();
        }

        $randomFloat = mt_rand() / mt_getrandmax();
        $target = $randomFloat * $weightedTotal;
        $runningTotal = 0.0;
        foreach ((array) $eligiblePrizeRows as $prizeRow) {
            $runningTotal += max(0, (float) (isset($prizeRow['weight']) ? $prizeRow['weight'] : 0));
            if ($target <= $runningTotal) {
                return (array) $prizeRow;
            }
        }

        return (array) end($eligiblePrizeRows);
    }
}

if (!function_exists('luckyDrawCreateClaimToken')) {
    function luckyDrawCreateClaimToken()
    {
        return bin2hex(random_bytes(24));
    }
}

if (!function_exists('luckyDrawBuildClaimUrl')) {
    function luckyDrawBuildClaimUrl($token)
    {
        return siteUrlWithQuery(ROUTE_LUCKY_DRAW_CLAIM, array('token' => (string) $token));
    }
}

if (!function_exists('luckyDrawUrbanIcSqlExpression')) {
    function luckyDrawUrbanIcSqlExpression()
    {
        return "REPLACE(REPLACE(REPLACE(UPPER(TRIM(ic)), '-', ''), ' ', ''), '/', '')";
    }
}

if (!function_exists('luckyDrawLookupUrbanMemberByIdentity')) {
    function luckyDrawLookupUrbanMemberByIdentity($connect, $identityInput)
    {
        $identityInput = luckyDrawNormalizeFullId($identityInput);
        if (!($connect instanceof mysqli) || $identityInput === '') {
            return array('success' => false, 'message' => 'Please enter a valid full IC number.', 'member' => array());
        }

        if (strlen($identityInput) <= 6) {
            return array('success' => false, 'message' => 'Please enter your full IC number.', 'member' => array());
        }

        $sqlIc = luckyDrawUrbanIcSqlExpression();
        $safeIdentity = mysqli_real_escape_string($connect, $identityInput);
        $exactSql = "SELECT id, name, ic FROM `" . URBAN_CUST_REG . "`
            WHERE " . $sqlIc . " = '" . $safeIdentity . "'
            ORDER BY id DESC
            LIMIT 1";
        $exactResult = mysqli_query($connect, $exactSql);
        if ($exactResult && ($row = mysqli_fetch_assoc($exactResult))) {
            $normalizedId = luckyDrawNormalizeFullId(isset($row['ic']) ? $row['ic'] : '');
            $birthdayYymmdd = luckyDrawExtractYymmddFromId($normalizedId);
            if ($birthdayYymmdd !== '') {
                return array(
                    'success' => true,
                    'message' => '',
                    'member' => array(
                        'source_id' => (int) $row['id'],
                        'member_name' => luckyDrawSafePublicText(isset($row['name']) ? $row['name'] : 'Birthday Member', 190),
                        'member_id_hmac' => luckyDrawMemberIdHmac($normalizedId),
                        'birthday_yymmdd' => $birthdayYymmdd,
                        'birthday_md' => luckyDrawBirthdayMdFromYymmdd($birthdayYymmdd),
                    ),
                );
            }
        }

        if (luckyDrawExtractYymmddFromId($identityInput) === '') {
            return array('success' => false, 'message' => 'Please enter a valid full IC number.', 'member' => array());
        }

        return array('success' => false, 'message' => 'This IC number is not found in the birthday member source.', 'member' => array());
    }
}

if (!function_exists('luckyDrawValidateEligibility')) {
    function luckyDrawValidateEligibility($memberRow, $submittedYymmdd)
    {
        if (empty($memberRow)) {
            return array('success' => false, 'message' => 'This member is not eligible for the birthday draw.');
        }

        $submittedYymmdd = trim((string) $submittedYymmdd);
        $storedYymmdd = trim((string) (isset($memberRow['birthday_yymmdd']) ? $memberRow['birthday_yymmdd'] : ''));
        if ($submittedYymmdd === '' || $storedYymmdd === '' || $submittedYymmdd !== $storedYymmdd) {
            return array('success' => false, 'message' => 'The submitted ID does not match the birthday record.');
        }

        $storedMd = trim((string) (isset($memberRow['birthday_md']) ? $memberRow['birthday_md'] : luckyDrawBirthdayMdFromYymmdd($storedYymmdd)));
        $storedMonth = strlen($storedMd) >= 2 ? substr($storedMd, 0, 2) : '';
        if ($storedMonth === '' || $storedMonth !== date('m')) {
            return array('success' => false, 'message' => 'This Lucky Draw is only available during the member birthday month.');
        }

        return array('success' => true, 'message' => '');
    }
}

if (!function_exists('luckyDrawInsertAdminLog')) {
    function luckyDrawInsertAdminLog($connect, $actionType, $targetTable, $targetId, $detail, $actorUserId, $options = array())
    {
        if (!($connect instanceof mysqli)) {
            return false;
        }

        $options = is_array($options) ? $options : array();
        $targetId = (int) $targetId;
        $actionType = luckyDrawSafePublicText($actionType, 60);
        $targetTable = luckyDrawSafePublicText($targetTable, 60);
        $detail = luckyDrawSafePublicText($detail, 255);
        $actorUserId = luckyDrawSafePublicText($actorUserId, 30);

        if (function_exists('audit_log')) {
            $auditAction = '';
            if (!empty($options['audit_action'])) {
                $auditAction = strtolower(trim((string) $options['audit_action']));
            } elseif (strpos($actionType, 'import') !== false) {
                $auditAction = 'import';
            } elseif (strpos($actionType, 'export') !== false) {
                $auditAction = 'export';
            } elseif (strpos($actionType, 'delete') !== false) {
                $auditAction = 'delete';
            } elseif (strpos($actionType, 'view') !== false) {
                $auditAction = 'view';
            } elseif (strpos($actionType, 'create') !== false || strpos($actionType, 'add') !== false) {
                $auditAction = 'add';
            } elseif (strpos($actionType, 'save') !== false || strpos($actionType, 'resend') !== false || strpos($actionType, 'queue') !== false) {
                $auditAction = 'edit';
            }

            if ($auditAction !== '') {
                $pageTitle = luckyDrawSafePublicText(isset($options['page_title']) ? $options['page_title'] : 'Lucky Draw Admin', 120);
                $actorName = luckyDrawSafePublicText(defined('USER_NAME') ? USER_NAME : 'Admin User', 120);
                $entityLabel = luckyDrawSafePublicText(isset($options['entity_label']) ? $options['entity_label'] : '', 80);
                $actMsg = trim((string) (isset($options['act_msg']) ? $options['act_msg'] : ''));

                if ($entityLabel === '') {
                    if ($targetTable === LUCKY_DRAW_PRIZE) {
                        $entityLabel = 'prize';
                    } elseif ($targetTable === LUCKY_DRAW_VIRTUAL_WINNER) {
                        $entityLabel = 'virtual board';
                    } elseif ($targetTable === LUCKY_DRAW_VOUCHER_CODE) {
                        $entityLabel = 'voucher code';
                    } elseif ($targetTable === LUCKY_DRAW_DRAW_LOG) {
                        $entityLabel = 'draw log';
                    } else {
                        $entityLabel = 'record';
                    }
                }

                if ($actMsg === '') {
                    $useStandardCrudMessage = !empty($options['use_standard_crud_message']);
                    if ($useStandardCrudMessage && in_array($auditAction, array('add', 'edit', 'view', 'delete'), true)) {
                        $verbMap = array(
                            'add' => 'added',
                            'edit' => 'edited',
                            'view' => 'viewed',
                            'delete' => 'deleted',
                        );
                        $locationMap = array(
                            'add' => 'under',
                            'edit' => 'under',
                            'view' => 'from',
                            'delete' => 'from',
                        );
                        $recordLabel = $targetId > 0 ? (' [ <b>ID = ' . $targetId . ' </b> ]') : '';
                        $actMsg = trim($actorName . ' ' . $verbMap[$auditAction] . ' ' . $entityLabel . $recordLabel . ' ' . $locationMap[$auditAction] . ' <b><i>' . $targetTable . ' Table</i></b>.');
                    } else {
                        $actionLabel = ucwords(str_replace('_', ' ', $actionType));
                        $targetLabel = $targetTable !== '' ? $targetTable . ($targetId > 0 ? (' #' . $targetId) : '') : '';
                        $messageParts = array_filter(array($actionLabel, $targetLabel, $detail), function ($value) {
                            return trim((string) $value) !== '';
                        });
                        $actMsg = implode(' - ', $messageParts);
                    }
                }

                $auditData = array(
                    'log_act' => $auditAction,
                    'cdate' => date_dis,
                    'ctime' => time_dis,
                    'uid' => $actorUserId,
                    'cby' => $actorUserId,
                    'act_msg' => $actMsg,
                    'query_rec' => $targetId > 0 ? (string) $targetId : '',
                    'query_table' => $targetTable,
                    'page' => $pageTitle,
                    'connect' => $connect,
                );

                if ($auditAction === 'edit') {
                    $auditData['changes'] = $detail;
                } elseif (in_array($auditAction, array('add', 'import'), true)) {
                    $auditData['newval'] = $detail;
                }

                audit_log($auditData);
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('luckyDrawPrizeReservationTransactionReady')) {

    function luckyDrawPrizeReservationTransactionReady($financeConnect)
    {
        return luckyDrawTableEngine($financeConnect, dbFinance, FB_ORDER_REQ) === 'INNODB';
    }
}

if (!function_exists('luckyDrawCreateReservation')) {
    function luckyDrawCreateReservation($connect, $financeConnect, $memberRow, $memberHmac, $submittedYymmdd, $ipHmac)
    {
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli) || empty($memberRow)) {
            return array('success' => false, 'message' => 'Lucky Draw is unavailable right now.');
        }

        mysqli_begin_transaction($connect);
        try {
            $safeMemberHmac = mysqli_real_escape_string($connect, $memberHmac);
            $existingResult = mysqli_query($connect, "SELECT id FROM `" . LUCKY_DRAW_DRAW_LOG . "`
                WHERE member_id_hmac = '" . $safeMemberHmac . "'
                  AND status = 'A'
                LIMIT 1
                FOR UPDATE");
            if ($existingResult && mysqli_num_rows($existingResult) > 0) {
                throw new Exception('This member has already used the birthday draw.');
            }

            $prizeRows = array();
            $prizeResult = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_PRIZE . "`
                WHERE status = 'A'
                  AND is_enabled = 'Y'
                ORDER BY display_order ASC, id ASC
                FOR UPDATE");
            if ($prizeResult) {
                while ($prizeRow = mysqli_fetch_assoc($prizeResult)) {
                    $prizeRows[] = (array) $prizeRow;
                }
            }

            $voucherAvailableCounts = array();
            $voucherPrizeIds = array();
            foreach ($prizeRows as $prizeRow) {
                $prizeId = isset($prizeRow['id']) ? (int) $prizeRow['id'] : 0;
                if ($prizeId > 0 && strtolower((string) ($prizeRow['prize_type'] ?? '')) === 'voucher') {
                    $voucherPrizeIds[] = $prizeId;
                }
            }
            $voucherStateCounts = luckyDrawVoucherStateCounts($connect, $voucherPrizeIds, true);
            foreach ($voucherStateCounts as $prizeId => $stateCounts) {
                $voucherAvailableCounts[(int) $prizeId] = (int) ($stateCounts['available'] ?? 0);
            }

            $eligiblePrizeRows = array();
            foreach ($prizeRows as $prizeRow) {
                $prizeId = isset($prizeRow['id']) ? (int) $prizeRow['id'] : 0;
                $voucherReservedCount = (int) ($voucherStateCounts[$prizeId]['reserved'] ?? 0);
                $voucherAssignedCount = (int) (($voucherStateCounts[$prizeId]['assigned'] ?? 0) + ($voucherStateCounts[$prizeId]['sent'] ?? 0));
                $availableUnits = luckyDrawPrizeAvailableUnits(
                    $prizeRow,
                    isset($voucherAvailableCounts[$prizeId]) ? (int) $voucherAvailableCounts[$prizeId] : 0,
                    $voucherReservedCount,
                    $voucherAssignedCount
                );
                if ((float) ($prizeRow['weight'] ?? 0) <= 0 || $availableUnits <= 0) {
                    continue;
                }

                $defaultsCheck = luckyDrawValidatePrizeDefaults($prizeRow);
                if (empty($defaultsCheck['success'])) {
                    continue;
                }

                $eligiblePrizeRows[] = $prizeRow;
            }

            if (empty($eligiblePrizeRows)) {
                throw new Exception('No prize is available for the birthday draw.');
            }

            $selectedPrize = luckyDrawPickWeightedPrize($eligiblePrizeRows);
            if (empty($selectedPrize)) {
                throw new Exception('Unable to prepare the draw result.');
            }

            $prizeId = (int) $selectedPrize['id'];
            $prizeType = strtolower(trim((string) $selectedPrize['prize_type']));
            $claimToken = luckyDrawCreateClaimToken();
            $claimTokenHash = hash('sha256', $claimToken);
            $reservationExpiresAt = $prizeType === 'voucher'
                ? date('Y-m-d H:i:s', strtotime('+' . max(5, (int) LUCKY_DRAW_VOUCHER_RESERVATION_EXPIRY_MINUTES) . ' minutes'))
                : date('Y-m-d H:i:s', strtotime('+' . max(1, (int) LUCKY_DRAW_CLAIM_EXPIRY_HOURS) . ' hours'));
            $redeemReference = 'LD-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

            $safeMemberName = mysqli_real_escape_string($connect, luckyDrawSafePublicText(isset($memberRow['member_name']) ? $memberRow['member_name'] : '', 190));
            $safeBirthdayYymmdd = mysqli_real_escape_string($connect, trim((string) $submittedYymmdd));
            $safeIpHmac = mysqli_real_escape_string($connect, trim((string) $ipHmac));
            $safePrizeName = mysqli_real_escape_string($connect, luckyDrawSafePublicText(isset($selectedPrize['prize_name']) ? $selectedPrize['prize_name'] : '', 255));
            $safePrizeType = mysqli_real_escape_string($connect, $prizeType);
            $safeClaimTokenHash = mysqli_real_escape_string($connect, $claimTokenHash);
            $safeReservationExpiry = mysqli_real_escape_string($connect, $reservationExpiresAt);
            $safeRedeemReference = mysqli_real_escape_string($connect, $redeemReference);
            $safeActor = mysqli_real_escape_string($connect, 'PUBLIC');
            $safeClaimState = mysqli_real_escape_string($connect, 'awaiting_claim');
            $safeEmailState = mysqli_real_escape_string($connect, $prizeType === 'voucher' ? 'awaiting_claim' : 'not_applicable');

            $insertSql = "INSERT INTO `" . LUCKY_DRAW_DRAW_LOG . "`
                (`member_id_hmac`, `member_display_name`, `birthday_yymmdd`, `ip_hmac`, `prize_id`, `prize_name_snapshot`, `prize_type_snapshot`, `redeem_reference`, `draw_state`, `claim_state`, `email_state`, `claim_token_hash`, `reservation_expires_at`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                ('" . $safeMemberHmac . "', '" . $safeMemberName . "', '" . $safeBirthdayYymmdd . "', '" . $safeIpHmac . "', " . $prizeId . ", '" . $safePrizeName . "', '" . $safePrizeType . "', '" . $safeRedeemReference . "', 'won', '" . $safeClaimState . "', '" . $safeEmailState . "', '" . $safeClaimTokenHash . "', '" . $safeReservationExpiry . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($connect, $insertSql)) {
                throw new Exception('Unable to save the draw result.');
            }

            $drawLogId = (int) mysqli_insert_id($connect);

            if ($prizeType === 'voucher') {
                $codeResult = mysqli_query($connect, "SELECT id FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
                    WHERE status = 'A'
                      AND prize_id = " . $prizeId . "
                      AND code_state = 'available'
                    ORDER BY id ASC
                    LIMIT 1
                    FOR UPDATE");

                if ($codeResult && ($codeRow = mysqli_fetch_assoc($codeResult))) {
                    $voucherCodeId = (int) $codeRow['id'];
                    if (!mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
                        SET code_state = 'reserved',
                            reserved_draw_log_id = " . $drawLogId . ",
                            reservation_expires_at = '" . $safeReservationExpiry . "',
                            update_by = '" . $safeActor . "',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . $voucherCodeId . "
                          AND code_state = 'available'
                        LIMIT 1")) {
                        throw new Exception('Unable to reserve the voucher code.');
                    }

                    mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                        SET voucher_code_id = " . $voucherCodeId . ",
                            update_by = '" . $safeActor . "',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . $drawLogId . "
                        LIMIT 1");
                } else {
                    if (!mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                        SET reserved_stock = reserved_stock + 1,
                            update_by = '" . $safeActor . "',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . $prizeId . "
                          AND status = 'A'
                          AND is_enabled = 'Y'
                          AND total_stock > 0
                          AND reserved_stock + assigned_stock < total_stock
                        LIMIT 1")) {
                        throw new Exception('Unable to reserve the voucher prize.');
                    }

                    if (mysqli_affected_rows($connect) <= 0) {
                        throw new Exception('The selected voucher prize is no longer available.');
                    }
                }
            } else {
                if (!mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                    SET reserved_stock = reserved_stock + 1,
                        update_by = '" . $safeActor . "',
                        update_date = CURDATE(),
                        update_time = CURTIME()
                    WHERE id = " . $prizeId . "
                      AND status = 'A'
                    LIMIT 1")) {
                    throw new Exception('Unable to reserve the prize stock.');
                }
            }

            mysqli_commit($connect);

            return array(
                'success' => true,
                'message' => '',
                'draw_log_id' => $drawLogId,
                'claim_token' => $claimToken,
                'claim_url' => luckyDrawBuildClaimUrl($claimToken),
                'prize' => $selectedPrize,
            );
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => $exception->getMessage());
        }
    }
}

if (!function_exists('luckyDrawFindClaimByToken')) {
    function luckyDrawFindClaimByToken($connect, $rawToken)
    {
        $rawToken = trim((string) $rawToken);
        if (!($connect instanceof mysqli) || $rawToken === '') {
            return array();
        }

        $tokenHash = hash('sha256', $rawToken);
        $safeTokenHash = mysqli_real_escape_string($connect, $tokenHash);
        $sql = "SELECT dl.*, p.prize_name, p.prize_image, p.prize_type, p.package_id, p.country_id, p.brand_id, p.series_id,
                       p.fb_page_id, p.channel_id, p.pay_method_id, p.stock_out_warehouse_id, p.sales_pic_user_id, p.price
                FROM `" . LUCKY_DRAW_DRAW_LOG . "` dl
                INNER JOIN `" . LUCKY_DRAW_PRIZE . "` p ON p.id = dl.prize_id AND p.status = 'A'
                WHERE dl.status = 'A'
                  AND dl.claim_token_hash = '" . $safeTokenHash . "'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        return ($result && ($row = mysqli_fetch_assoc($result))) ? (array) $row : array();
    }
}

if (!function_exists('luckyDrawSubmitClaim')) {
    function luckyDrawSubmitClaim($connect, $financeConnect, $rawToken, $claimData)
    {
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return array('success' => false, 'message' => 'Lucky Draw is unavailable right now.');
        }

        $claimData = is_array($claimData) ? $claimData : array();
        $rawToken = trim((string) $rawToken);
        if ($rawToken === '') {
            return array('success' => false, 'message' => 'The claim link is invalid.');
        }

        mysqli_begin_transaction($connect);
        try {
            $tokenHash = hash('sha256', $rawToken);
            $safeTokenHash = mysqli_real_escape_string($connect, $tokenHash);
            $sql = "SELECT dl.*, p.prize_name, p.prize_type, p.package_id, p.country_id, p.brand_id, p.series_id,
                           p.fb_page_id, p.channel_id, p.pay_method_id, p.stock_out_warehouse_id, p.sales_pic_user_id, p.price
                    FROM `" . LUCKY_DRAW_DRAW_LOG . "` dl
                    INNER JOIN `" . LUCKY_DRAW_PRIZE . "` p ON p.id = dl.prize_id AND p.status = 'A'
                    WHERE dl.status = 'A'
                      AND dl.claim_token_hash = '" . $safeTokenHash . "'
                    LIMIT 1
                    FOR UPDATE";
            $result = mysqli_query($connect, $sql);
            if (!$result || !($drawRow = mysqli_fetch_assoc($result))) {
                throw new Exception('The claim link is invalid or has already expired.');
            }

            $claimState = trim((string) (isset($drawRow['claim_state']) ? $drawRow['claim_state'] : ''));
            if ($claimState !== 'awaiting_claim') {
                throw new Exception('This claim has already been completed or closed.');
            }

            $reservationExpiresAt = isset($drawRow['reservation_expires_at']) ? trim((string) $drawRow['reservation_expires_at']) : '';
            if ($reservationExpiresAt !== '' && strtotime($reservationExpiresAt) < time()) {
                throw new Exception('This claim link has expired.');
            }

            $email = luckyDrawSafePublicText(isset($claimData['email']) ? $claimData['email'] : '', 190);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }

            $prizeType = strtolower(trim((string) (isset($drawRow['prize_type']) ? $drawRow['prize_type'] : '')));
            $safeActor = mysqli_real_escape_string($connect, 'PUBLIC');
            $safeEmail = mysqli_real_escape_string($connect, $email);

            if ($prizeType === 'voucher') {
                $voucherCodeId = isset($drawRow['voucher_code_id']) ? (int) $drawRow['voucher_code_id'] : 0;

                if ($voucherCodeId > 0) {
                    $voucherResult = mysqli_query($connect, "SELECT id FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
                        WHERE id = " . $voucherCodeId . "
                          AND status = 'A'
                          AND code_state = 'reserved'
                        LIMIT 1
                        FOR UPDATE");
                    if (!$voucherResult || !mysqli_fetch_assoc($voucherResult)) {
                        throw new Exception('The reserved voucher is no longer available.');
                    }

                    mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
                        SET code_state = 'assigned',
                            assigned_draw_log_id = " . (int) $drawRow['id'] . ",
                            reservation_expires_at = NULL,
                            update_by = '" . $safeActor . "',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . $voucherCodeId . "
                        LIMIT 1");
                } else {
                    mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                        SET reserved_stock = GREATEST(reserved_stock - 1, 0),
                            assigned_stock = assigned_stock + 1,
                            update_by = '" . $safeActor . "',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . (int) $drawRow['prize_id'] . "
                        LIMIT 1");
                }

                mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                    SET claim_email = '" . $safeEmail . "',
                        claim_state = 'claimed',
                        email_state = 'pending',
                        email_locked_at = NULL,
                        email_lock_token = '',
                        update_by = '" . $safeActor . "',
                        update_date = CURDATE(),
                        update_time = CURTIME()
                    WHERE id = " . (int) $drawRow['id'] . "
                    LIMIT 1");

                mysqli_commit($connect);
                return array(
                    'success' => true,
                    'message' => $voucherCodeId > 0 ? 'Your voucher claim has been received. We will send the voucher code to your email shortly.' : 'Your voucher claim has been received. We will send the claim confirmation to your email shortly.',
                    'type' => 'voucher',
                );
            }

            $stockCheck = luckyDrawValidatePhysicalPrizeStock($connect, $financeConnect, $drawRow);
            if (empty($stockCheck['success'])) {
                throw new Exception(isset($stockCheck['message']) ? (string) $stockCheck['message'] : 'Warehouse stock is not available for this prize.');
            }
            mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                SET reserved_stock = CASE WHEN reserved_stock > 0 THEN reserved_stock - 1 ELSE 0 END,
                    assigned_stock = assigned_stock + 1,
                    update_by = '" . $safeActor . "',
                    update_date = CURDATE(),
                    update_time = CURTIME()
                WHERE id = " . (int) $drawRow['prize_id'] . "
                LIMIT 1");

            mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                SET claim_email = '" . $safeEmail . "',
                    claim_state = 'claimed',
                    email_state = 'not_applicable',
                    update_by = '" . $safeActor . "',
                    update_date = CURDATE(),
                    update_time = CURTIME()
                WHERE id = " . (int) $drawRow['id'] . "
                LIMIT 1");

            mysqli_commit($connect);
            return array(
                'success' => true,
                'message' => 'Your claim has been submitted successfully. We will follow up using your email address.',
                'type' => 'physical',
            );
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => $exception->getMessage());
        }
    }
}

if (!function_exists('luckyDrawQueueEmailSendLock')) {
    function luckyDrawQueueEmailSendLock($connect, $limit = 10, $lockMinutes = 15)
    {
        $rows = array();
        if (!($connect instanceof mysqli) || $limit <= 0) {
            return $rows;
        }

        for ($i = 0; $i < $limit; $i++) {
            mysqli_begin_transaction($connect);
            $lockToken = bin2hex(random_bytes(16));
            try {
                $staleBoundary = date('Y-m-d H:i:s', strtotime('-' . max(1, (int) $lockMinutes) . ' minutes'));
                $sql = "SELECT * FROM `" . LUCKY_DRAW_DRAW_LOG . "`
                    WHERE status = 'A'
                      AND prize_type_snapshot = 'voucher'
                      AND claim_state = 'claimed'
                      AND email_state IN ('pending', 'failed', 'sending')
                      AND (
                          email_state IN ('pending', 'failed')
                          OR email_locked_at IS NULL
                          OR email_locked_at < '" . mysqli_real_escape_string($connect, $staleBoundary) . "'
                      )
                    ORDER BY id ASC
                    LIMIT 1
                    FOR UPDATE";
                $result = mysqli_query($connect, $sql);
                if (!$result || !($row = mysqli_fetch_assoc($result))) {
                    mysqli_commit($connect);
                    break;
                }

                $safeLockToken = mysqli_real_escape_string($connect, $lockToken);
                if (!mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                    SET email_state = 'sending',
                        email_locked_at = NOW(),
                        email_lock_token = '" . $safeLockToken . "',
                        update_by = 'SYSTEM',
                        update_date = CURDATE(),
                        update_time = CURTIME()
                    WHERE id = " . (int) $row['id'] . "
                    LIMIT 1")) {
                    throw new Exception('Unable to lock the email queue row.');
                }

                mysqli_commit($connect);
                $row['email_lock_token'] = $lockToken;
                $rows[] = $row;
            } catch (Exception $exception) {
                mysqli_rollback($connect);
                break;
            }
        }

        return $rows;
    }
}

if (!function_exists('luckyDrawMarkEmailSendResult')) {
    function luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, $sentSuccessfully, $errorMessage = '')
    {
        if (!($connect instanceof mysqli) || (int) $drawLogId <= 0) {
            return false;
        }

        $safeLockToken = mysqli_real_escape_string($connect, trim((string) $lockToken));
        $safeError = mysqli_real_escape_string($connect, luckyDrawSafePublicText($errorMessage, 255));
        $newState = $sentSuccessfully ? 'sent' : 'failed';
        $sentAtSql = $sentSuccessfully ? "sent_at = NOW()," : '';

        $sql = "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
            SET email_state = '" . $newState . "',
                " . $sentAtSql . "
                failure_message = '" . $safeError . "',
                retry_count = CASE WHEN '" . $newState . "' = 'failed' THEN retry_count + 1 ELSE retry_count END,
                email_locked_at = NULL,
                email_lock_token = '',
                update_by = 'SYSTEM',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . (int) $drawLogId . "
              AND email_lock_token = '" . $safeLockToken . "'
            LIMIT 1";

        return (bool) mysqli_query($connect, $sql);
    }
}

if (!function_exists('luckyDrawBuildVoucherEmailContent')) {
    function luckyDrawBuildVoucherEmailContent($drawRow, $voucherCode)
    {
        $prizeName = luckyDrawSafePublicText(isset($drawRow['prize_name_snapshot']) ? $drawRow['prize_name_snapshot'] : 'Your Prize', 255);
        $redeemReference = luckyDrawSafePublicText(isset($drawRow['redeem_reference']) ? $drawRow['redeem_reference'] : '', 60);
        $subject = 'Lucky Draw Voucher - ' . $prizeName;
        $message = '<html><body style="font-family:Arial,sans-serif;background:#f6f3ea;padding:24px;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;padding:28px;">'
            . '<h2 style="margin-top:0;">Congratulations!</h2>'
            . '<p>Your Lucky Draw voucher is ready.</p>'
            . '<p><strong>Prize:</strong> ' . htmlspecialchars($prizeName, ENT_QUOTES, 'UTF-8') . '</p>'
            . (trim((string) $voucherCode) !== '' ? '<p><strong>Voucher Code:</strong> ' . htmlspecialchars($voucherCode, ENT_QUOTES, 'UTF-8') . '</p>' : '<p><strong>Voucher:</strong> Please use this email and redeem reference for follow-up.</p>')
            . '<p><strong>Reference:</strong> ' . htmlspecialchars($redeemReference, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Please keep this email for your records.</p>'
            . '</div></body></html>';

        return array(
            'subject' => $subject,
            'message' => $message,
        );
    }
}

if (!function_exists('luckyDrawSendVoucherQueueBatch')) {
    function luckyDrawSendVoucherQueueBatch($connect, $financeConnect, $limit = 10, $lockMinutes = 15)
    {
        $processed = array(
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        );

        $queueRows = luckyDrawQueueEmailSendLock($connect, $limit, $lockMinutes);
        foreach ($queueRows as $queueRow) {
            $drawLogId = isset($queueRow['id']) ? (int) $queueRow['id'] : 0;
            $lockToken = isset($queueRow['email_lock_token']) ? (string) $queueRow['email_lock_token'] : '';
            $claimEmail = trim((string) (isset($queueRow['claim_email']) ? $queueRow['claim_email'] : ''));
            $voucherCodeId = isset($queueRow['voucher_code_id']) ? (int) $queueRow['voucher_code_id'] : 0;

            if ($drawLogId <= 0 || $lockToken === '' || !filter_var($claimEmail, FILTER_VALIDATE_EMAIL)) {
                luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, false, 'Missing email or voucher reservation.');
                $processed['failed']++;
                continue;
            }

            $voucherCode = '';
            if ($voucherCodeId > 0) {
                $voucherResult = mysqli_query($connect, "SELECT voucher_code FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
                    WHERE id = " . $voucherCodeId . "
                      AND status = 'A'
                      AND code_state = 'assigned'
                    LIMIT 1");
                if (!$voucherResult || !($voucherRow = mysqli_fetch_assoc($voucherResult))) {
                    luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, false, 'Assigned voucher code not found.');
                    $processed['failed']++;
                    continue;
                }

                $voucherCode = isset($voucherRow['voucher_code']) ? (string) $voucherRow['voucher_code'] : '';
            }

            $emailContent = luckyDrawBuildVoucherEmailContent($queueRow, $voucherCode);
            $sent = commonSendSystemEmail($connect, $claimEmail, $emailContent['subject'], $emailContent['message'], array(
                'auto_submitted' => true,
            ));
            luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, $sent, $sent ? '' : 'Failed to send voucher email.');
            if ($sent) {
                if ($voucherCodeId > 0) {
                    mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
                        SET code_state = 'sent',
                            sent_at = NOW(),
                            update_by = 'SYSTEM',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . $voucherCodeId . "
                        LIMIT 1");
                }
                $processed['sent']++;
            } else {
                $processed['failed']++;
            }
        }

        return $processed;
    }
}

if (!function_exists('luckyDrawReleaseExpiredReservations')) {
    function luckyDrawReleaseExpiredReservations($connect, $limit = 50)
    {
        $released = array(
            'physical' => 0,
            'voucher' => 0,
            'stale_email_locks' => 0,
        );

        if (!($connect instanceof mysqli)) {
            return $released;
        }

        $expiredResult = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_DRAW_LOG . "`
            WHERE status = 'A'
              AND claim_state = 'awaiting_claim'
              AND reservation_expires_at IS NOT NULL
              AND reservation_expires_at <> ''
              AND reservation_expires_at < NOW()
            ORDER BY id ASC
            LIMIT " . max(1, (int) $limit));

        if ($expiredResult) {
            while ($expiredRow = mysqli_fetch_assoc($expiredResult)) {
                mysqli_begin_transaction($connect);
                try {
                    $lockResult = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_DRAW_LOG . "`
                        WHERE id = " . (int) $expiredRow['id'] . "
                          AND status = 'A'
                        LIMIT 1
                        FOR UPDATE");
                    if (!$lockResult || !($drawRow = mysqli_fetch_assoc($lockResult))) {
                        mysqli_commit($connect);
                        continue;
                    }

                    if (trim((string) $drawRow['claim_state']) !== 'awaiting_claim') {
                        mysqli_commit($connect);
                        continue;
                    }

                    $prizeType = strtolower(trim((string) (isset($drawRow['prize_type_snapshot']) ? $drawRow['prize_type_snapshot'] : '')));
                    if ($prizeType === 'voucher') {
                        $voucherCodeId = isset($drawRow['voucher_code_id']) ? (int) $drawRow['voucher_code_id'] : 0;
                        if ($voucherCodeId > 0) {
                            mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
                                SET code_state = 'available',
                                    reserved_draw_log_id = NULL,
                                    reservation_expires_at = NULL,
                                    update_by = 'SYSTEM',
                                    update_date = CURDATE(),
                                    update_time = CURTIME()
                                WHERE id = " . $voucherCodeId . "
                                  AND code_state = 'reserved'
                                LIMIT 1");
                        }
                        $released['voucher']++;
                    } else {
                        mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                            SET reserved_stock = CASE WHEN reserved_stock > 0 THEN reserved_stock - 1 ELSE 0 END,
                                update_by = 'SYSTEM',
                                update_date = CURDATE(),
                                update_time = CURTIME()
                            WHERE id = " . (int) $drawRow['prize_id'] . "
                            LIMIT 1");
                        $released['physical']++;
                    }

                    mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                        SET draw_state = 'expired',
                            claim_state = 'expired',
                            email_state = CASE WHEN prize_type_snapshot = 'voucher' THEN 'expired' ELSE email_state END,
                            failure_message = '',
                            update_by = 'SYSTEM',
                            update_date = CURDATE(),
                            update_time = CURTIME()
                        WHERE id = " . (int) $drawRow['id'] . "
                        LIMIT 1");

                    mysqli_commit($connect);
                } catch (Exception $exception) {
                    mysqli_rollback($connect);
                }
            }
        }

        $staleBoundary = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $staleSql = "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
            SET email_state = 'pending',
                email_locked_at = NULL,
                email_lock_token = '',
                update_by = 'SYSTEM',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE status = 'A'
              AND prize_type_snapshot = 'voucher'
              AND claim_state = 'claimed'
              AND email_state = 'sending'
              AND email_locked_at IS NOT NULL
              AND email_locked_at < '" . mysqli_real_escape_string($connect, $staleBoundary) . "'";
        if (mysqli_query($connect, $staleSql)) {
            $released['stale_email_locks'] = (int) mysqli_affected_rows($connect);
        }

        return $released;
    }
}

if (!function_exists('luckyDrawBoardFeedRows')) {
    function luckyDrawBoardFeedRows($connect, $limit = 12)
    {
        $limit = max(1, (int) $limit);
        $rows = array();
        if (!($connect instanceof mysqli)) {
            return $rows;
        }

        $realSql = "SELECT prize_name_snapshot, member_display_name, create_date, create_time
            FROM `" . LUCKY_DRAW_DRAW_LOG . "`
            WHERE status = 'A'
              AND draw_state = 'won'
            ORDER BY id DESC
            LIMIT " . $limit;
        $realResult = mysqli_query($connect, $realSql);
        if ($realResult) {
            while ($row = mysqli_fetch_assoc($realResult)) {
                $displayName = trim((string) (isset($row['member_display_name']) ? $row['member_display_name'] : ''));
                if ($displayName === '') {
                    $displayName = 'Birthday Member';
                }
                $rows[] = array(
                    'display_name' => luckyDrawMaskDisplayName($displayName),
                    'display_prize' => isset($row['prize_name_snapshot']) ? (string) $row['prize_name_snapshot'] : 'Prize',
                    'source' => 'real',
                    'sort_stamp' => trim((string) (isset($row['create_date']) ? $row['create_date'] : '') . ' ' . (isset($row['create_time']) ? $row['create_time'] : '')),
                );
            }
        }

        $virtualSql = "SELECT display_name, display_prize, sort_order
            FROM `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
            WHERE status = 'A'
            ORDER BY sort_order ASC, id DESC
            LIMIT " . $limit;
        $virtualResult = mysqli_query($connect, $virtualSql);
        if ($virtualResult) {
            while ($row = mysqli_fetch_assoc($virtualResult)) {
                $rows[] = array(
                    'display_name' => luckyDrawMaskDisplayName(isset($row['display_name']) ? (string) $row['display_name'] : 'Lucky Member'),
                    'display_prize' => isset($row['display_prize']) ? (string) $row['display_prize'] : 'Prize',
                    'source' => 'virtual',
                    'sort_stamp' => 'virtual-' . (isset($row['sort_order']) ? (int) $row['sort_order'] : 0),
                );
            }
        }

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('luckyDrawMaskDisplayName')) {
    function luckyDrawMaskDisplayName($value)
    {
        $value = luckyDrawSafePublicText($value, 80);
        if ($value === '') {
            return 'Lucky Member';
        }

        $length = mb_strlen($value);
        if ($length <= 2) {
            return mb_substr($value, 0, 1) . '*';
        }

        return mb_substr($value, 0, 1) . str_repeat('*', max(2, $length - 2)) . mb_substr($value, -1);
    }
}

if (!function_exists('luckyDrawReadImportRows')) {
    function luckyDrawReadImportRows($file)
    {
        $rows = array();
        if (!is_array($file) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return $rows;
        }

        $name = strtolower((string) (isset($file['name']) ? $file['name'] : ''));
        if (substr($name, -4) === '.csv') {
            $handle = @fopen($file['tmp_name'], 'r');
            if (!$handle) {
                return $rows;
            }

            $headers = array();
            while (($data = fgetcsv($handle)) !== false) {
                if (empty($headers)) {
                    foreach ((array) $data as $header) {
                        $headers[] = strtolower(trim((string) $header));
                    }
                    continue;
                }

                $row = array();
                foreach ($headers as $index => $header) {
                    $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
                }
                $rows[] = $row;
            }
            fclose($handle);
            return $rows;
        }

        if (substr($name, -5) === '.xlsx' || substr($name, -4) === '.xls') {
            if (function_exists('siParseExcelLikeRows')) {
                return siParseExcelLikeRows($file['tmp_name'], $file['name']);
            }
        }

        return $rows;
    }
}

if (!function_exists('luckyDrawExportCsv')) {
    function luckyDrawExportCsv($fileName, $headers, $rows)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename((string) $fileName) . '"');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, (array) $headers);
        foreach ((array) $rows as $row) {
            fputcsv($output, (array) $row);
        }
        fclose($output);
        exit;
    }
}
