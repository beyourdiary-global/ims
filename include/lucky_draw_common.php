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

if (!function_exists('luckyDrawRenderAdminNav')) {
    function luckyDrawRenderAdminNav($activeKey = '')
    {
        return;
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

if (!function_exists('luckyDrawVoucherCodeTableReady')) {
    function luckyDrawVoucherCodeTableReady($connect)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        $ready = false;
        if (!($connect instanceof mysqli)) {
            return $ready;
        }

        try {
            $tableName = mysqli_real_escape_string($connect, LUCKY_DRAW_VOUCHER_CODE);
            $result = mysqli_query($connect, "SHOW TABLES LIKE '" . $tableName . "'");
            $ready = (bool) ($result && mysqli_num_rows($result) > 0);
        } catch (Exception $exception) {
            $ready = false;
        }

        return $ready;
    }
}

if (!function_exists('luckyDrawVoucherPoolCounts')) {
    /**
     * The voucher code pool is the single source of truth for voucher stock.
     * Returns [prizeId => ['available' => n, 'reserved' => n, 'assigned' => n, 'total' => n]].
     */
    function luckyDrawVoucherPoolCounts($connect, $prizeIds = array())
    {
        $counts = array();
        if (!($connect instanceof mysqli) || !luckyDrawVoucherCodeTableReady($connect)) {
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

        $sql = "SELECT prize_id, code_state, COUNT(*) AS state_count
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE status = 'A'";
        if (!empty($sanitizedPrizeIds)) {
            $sql .= " AND prize_id IN (" . implode(',', $sanitizedPrizeIds) . ")";
        }
        $sql .= " GROUP BY prize_id, code_state";

        try {
            $result = mysqli_query($connect, $sql);
        } catch (Exception $exception) {
            return $counts;
        }

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $prizeId = isset($row['prize_id']) ? (int) $row['prize_id'] : 0;
                if ($prizeId <= 0) {
                    continue;
                }
                if (!isset($counts[$prizeId])) {
                    $counts[$prizeId] = array('available' => 0, 'reserved' => 0, 'assigned' => 0, 'total' => 0);
                }

                $stateName = strtolower(trim((string) (isset($row['code_state']) ? $row['code_state'] : '')));
                $stateCount = isset($row['state_count']) ? (int) $row['state_count'] : 0;
                if ($stateName === 'reserved') {
                    $counts[$prizeId]['reserved'] += $stateCount;
                } elseif ($stateName === 'assigned') {
                    $counts[$prizeId]['assigned'] += $stateCount;
                } else {
                    $counts[$prizeId]['available'] += $stateCount;
                }
                $counts[$prizeId]['total'] += $stateCount;
            }
        }

        return $counts;
    }
}

if (!function_exists('luckyDrawVoucherRefreshPrizeStockCounters')) {
    /**
     * Keeps the denormalised total/reserved/assigned columns on the prize row in step with
     * the pool, so the existing admin list and import screens keep showing sane numbers.
     */
    function luckyDrawVoucherRefreshPrizeStockCounters($connect, $prizeId)
    {
        $prizeId = (int) $prizeId;
        if (!($connect instanceof mysqli) || $prizeId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return false;
        }

        $poolCounts = luckyDrawVoucherPoolCounts($connect, array($prizeId));
        $poolCount = isset($poolCounts[$prizeId])
            ? $poolCounts[$prizeId]
            : array('available' => 0, 'reserved' => 0, 'assigned' => 0, 'total' => 0);

        $sql = "UPDATE `" . LUCKY_DRAW_PRIZE . "`
            SET total_stock = " . (int) $poolCount['total'] . ",
                reserved_stock = " . (int) $poolCount['reserved'] . ",
                assigned_stock = " . (int) $poolCount['assigned'] . ",
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . $prizeId . "
              AND status = 'A'
            LIMIT 1";

        return (bool) mysqli_query($connect, $sql);
    }
}

if (!function_exists('luckyDrawVoucherReserveCode')) {
    /**
     * Locks one unused code from the prize pool for the given draw log.
     * Returns the reserved code, or '' when the pool has nothing left to give.
     */
    function luckyDrawVoucherReserveCode($connect, $prizeId, $drawLogId)
    {
        $prizeId = (int) $prizeId;
        $drawLogId = (int) $drawLogId;
        if (!($connect instanceof mysqli) || $prizeId <= 0 || $drawLogId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return '';
        }

        $result = mysqli_query($connect, "SELECT id, voucher_code
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE prize_id = " . $prizeId . "
              AND code_state = 'available'
              AND status = 'A'
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE");
        if (!$result || !($codeRow = mysqli_fetch_assoc($result))) {
            return '';
        }

        $codeId = (int) $codeRow['id'];
        if (!mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
            SET code_state = 'reserved',
                draw_log_id = " . $drawLogId . ",
                reserved_at = NOW(),
                assigned_at = NULL,
                update_by = 'PUBLIC',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . $codeId . "
              AND code_state = 'available'
              AND status = 'A'
            LIMIT 1")) {
            return '';
        }

        if (mysqli_affected_rows($connect) <= 0) {
            return '';
        }

        luckyDrawVoucherRefreshPrizeStockCounters($connect, $prizeId);
        return trim((string) $codeRow['voucher_code']);
    }
}

if (!function_exists('luckyDrawVoucherAssignCode')) {
    function luckyDrawVoucherAssignCode($connect, $drawLogId)
    {
        $drawLogId = (int) $drawLogId;
        if (!($connect instanceof mysqli) || $drawLogId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return '';
        }

        $result = mysqli_query($connect, "SELECT id, prize_id, voucher_code
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE draw_log_id = " . $drawLogId . "
              AND status = 'A'
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE");
        if (!$result || !($codeRow = mysqli_fetch_assoc($result))) {
            return '';
        }

        mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
            SET code_state = 'assigned',
                assigned_at = NOW(),
                update_by = 'PUBLIC',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . (int) $codeRow['id'] . "
              AND code_state = 'reserved'
              AND status = 'A'
            LIMIT 1");

        luckyDrawVoucherRefreshPrizeStockCounters($connect, (int) $codeRow['prize_id']);
        return trim((string) $codeRow['voucher_code']);
    }
}

if (!function_exists('luckyDrawVoucherReleaseCode')) {
    function luckyDrawVoucherReleaseCode($connect, $drawLogId)
    {
        $drawLogId = (int) $drawLogId;
        if (!($connect instanceof mysqli) || $drawLogId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return false;
        }

        $result = mysqli_query($connect, "SELECT id, prize_id
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE draw_log_id = " . $drawLogId . "
              AND code_state = 'reserved'
              AND status = 'A'
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE");
        if (!$result || !($codeRow = mysqli_fetch_assoc($result))) {
            return false;
        }

        mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VOUCHER_CODE . "`
            SET code_state = 'available',
                draw_log_id = NULL,
                reserved_at = NULL,
                assigned_at = NULL,
                update_by = 'SYSTEM',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . (int) $codeRow['id'] . "
              AND code_state = 'reserved'
              AND status = 'A'
            LIMIT 1");

        luckyDrawVoucherRefreshPrizeStockCounters($connect, (int) $codeRow['prize_id']);
        return true;
    }
}

if (!function_exists('luckyDrawVoucherCodeByDrawLog')) {
    function luckyDrawVoucherCodeByDrawLog($connect, $drawLogId)
    {
        $drawLogId = (int) $drawLogId;
        if (!($connect instanceof mysqli) || $drawLogId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return '';
        }

        $result = mysqli_query($connect, "SELECT voucher_code
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE draw_log_id = " . $drawLogId . "
              AND status = 'A'
            ORDER BY id ASC
            LIMIT 1");

        return ($result && ($row = mysqli_fetch_assoc($result))) ? trim((string) $row['voucher_code']) : '';
    }
}

if (!function_exists('luckyDrawVoucherParseCodeList')) {
    /** Splits a pasted block into a de-duplicated list of codes (one per line). */
    function luckyDrawVoucherParseCodeList($rawText)
    {
        $codes = array();
        $seen = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $rawText);
        foreach ((array) $lines as $line) {
            $code = luckyDrawSafePublicText($line, 255);
            if ($code === '') {
                continue;
            }
            $fingerprint = strtolower($code);
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;
            $codes[] = $code;
        }

        return $codes;
    }
}

if (!function_exists('luckyDrawVoucherAvailableCodeList')) {
    function luckyDrawVoucherAvailableCodeList($connect, $prizeId)
    {
        $codes = array();
        $prizeId = (int) $prizeId;
        if (!($connect instanceof mysqli) || $prizeId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return $codes;
        }

        $result = mysqli_query($connect, "SELECT voucher_code
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE prize_id = " . $prizeId . "
              AND code_state = 'available'
              AND status = 'A'
            ORDER BY id ASC");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $codes[] = (string) $row['voucher_code'];
            }
        }

        return $codes;
    }
}

if (!function_exists('luckyDrawVoucherSyncAvailableCodes')) {
    /**
     * Reconciles the AVAILABLE part of a prize pool with the given list.
     * Reserved / assigned codes are never touched, so a live draw can never lose its code
     * while the operator is editing the pool.
     * Returns ['added' => n, 'removed' => n, 'available' => n, 'in_use' => n, 'conflicts' => []].
     */
    function luckyDrawVoucherSyncAvailableCodes($connect, $prizeId, $codeList, $actorUserId = 'SYSTEM')
    {
        $summary = array('added' => 0, 'removed' => 0, 'available' => 0, 'in_use' => 0, 'conflicts' => array());
        $prizeId = (int) $prizeId;
        if (!($connect instanceof mysqli) || $prizeId <= 0 || !luckyDrawVoucherCodeTableReady($connect)) {
            return $summary;
        }

        $desired = array();
        foreach ((array) $codeList as $code) {
            $code = luckyDrawSafePublicText($code, 255);
            if ($code !== '') {
                $desired[strtolower($code)] = $code;
            }
        }

        $existing = array();
        $result = mysqli_query($connect, "SELECT id, voucher_code, code_state
            FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
            WHERE prize_id = " . $prizeId . "
              AND status = 'A'");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $existingCode = trim((string) $row['voucher_code']);
                $existing[] = array(
                    'id' => (int) $row['id'],
                    'fingerprint' => strtolower($existingCode),
                    'state' => strtolower(trim((string) $row['code_state'])),
                );
            }
        }

        $existingFingerprints = array();
        foreach ($existing as $existingRow) {
            $existingFingerprints[$existingRow['fingerprint']] = true;
            if ($existingRow['state'] !== 'available') {
                continue;
            }
            if (isset($desired[$existingRow['fingerprint']])) {
                continue;
            }

            // Only untouched codes can be withdrawn from the pool.
            if (mysqli_query($connect, "DELETE FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
                WHERE id = " . $existingRow['id'] . "
                  AND code_state = 'available'
                  AND status = 'A'
                LIMIT 1")) {
                $summary['removed'] += (int) mysqli_affected_rows($connect);
            }
        }

        $safeActor = mysqli_real_escape_string($connect, luckyDrawSafePublicText($actorUserId, 30));
        foreach ($desired as $fingerprint => $code) {
            if (isset($existingFingerprints[$fingerprint])) {
                continue;
            }

            // Codes are globally unique. A code already used by another prize is reported
            // instead of aborting the whole save.
            $safeCode = mysqli_real_escape_string($connect, $code);
            $conflictResult = mysqli_query($connect, "SELECT id FROM `" . LUCKY_DRAW_VOUCHER_CODE . "`
                WHERE voucher_code = '" . $safeCode . "'
                  AND status = 'A'
                LIMIT 1");
            if ($conflictResult && mysqli_num_rows($conflictResult) > 0) {
                $summary['conflicts'][] = $code;
                continue;
            }

            if (mysqli_query($connect, "INSERT INTO `" . LUCKY_DRAW_VOUCHER_CODE . "`
                (prize_id, voucher_code, code_state, create_by, create_date, create_time, status)
                VALUES
                (" . $prizeId . ", '" . $safeCode . "', 'available', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')")) {
                $summary['added']++;
            }
        }

        luckyDrawVoucherRefreshPrizeStockCounters($connect, $prizeId);

        $poolCounts = luckyDrawVoucherPoolCounts($connect, array($prizeId));
        if (isset($poolCounts[$prizeId])) {
            $summary['available'] = (int) $poolCounts[$prizeId]['available'];
            $summary['in_use'] = (int) $poolCounts[$prizeId]['reserved'] + (int) $poolCounts[$prizeId]['assigned'];
        }

        return $summary;
    }
}

if (!function_exists('luckyDrawVoucherAvailableCounts')) {
    /**
     * Available voucher stock == unused codes in that prize pool. Every voucher prize gets an
     * explicit entry (0 when the pool is empty) so a stale mirror column can never resurrect it.
     */
    function luckyDrawVoucherAvailableCounts($connect)
    {
        $counts = array();
        if (!($connect instanceof mysqli) || !luckyDrawVoucherCodeTableReady($connect)) {
            return $counts;
        }

        $voucherPrizeIds = array();
        $result = mysqli_query($connect, "SELECT id
            FROM `" . LUCKY_DRAW_PRIZE . "`
            WHERE status = 'A'
              AND prize_type = 'voucher'");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $prizeId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($prizeId > 0) {
                    $voucherPrizeIds[] = $prizeId;
                    $counts[$prizeId] = 0;
                }
            }
        }

        $poolCounts = luckyDrawVoucherPoolCounts($connect, $voucherPrizeIds);
        foreach ($poolCounts as $prizeId => $poolCount) {
            $counts[(int) $prizeId] = (int) $poolCount['available'];
        }

        return $counts;
    }
}

if (!function_exists('luckyDrawVoucherStateCounts')) {
    function luckyDrawVoucherStateCounts($connect, $prizeIds = array(), $lockRows = false)
    {
        $counts = array();
        if (!($connect instanceof mysqli) || !luckyDrawVoucherCodeTableReady($connect)) {
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

        // Callers that need a stable snapshot already lock the prize row FOR UPDATE before
        // reading these counts, and a reservation locks its own code row, so no extra row
        // lock is needed here -- $lockRows is kept for call-site compatibility.
        $poolCounts = luckyDrawVoucherPoolCounts($connect, $sanitizedPrizeIds);
        foreach ($poolCounts as $prizeId => $poolCount) {
            $counts[(int) $prizeId] = array(
                'available' => (int) $poolCount['available'],
                'reserved' => (int) $poolCount['reserved'],
                'assigned' => (int) $poolCount['assigned'],
                'sent' => 0,
            );
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
            $totalStock = isset($prizeRow['total_stock']) ? (int) $prizeRow['total_stock'] : 0;
            $reservedStock = $voucherReservedCount > 0
                ? max(0, (int) $voucherReservedCount)
                : (isset($prizeRow['reserved_stock']) ? (int) $prizeRow['reserved_stock'] : 0);
            $assignedStock = $voucherAssignedCount > 0
                ? max(0, (int) $voucherAssignedCount)
                : (isset($prizeRow['assigned_stock']) ? (int) $prizeRow['assigned_stock'] : 0);

            // The pool is authoritative: available voucher stock is exactly the number of
            // unused codes, so the mirror columns must never clamp it down.
            if ($voucherAvailableCount > 0) {
                return max(0, (int) $voucherAvailableCount);
            }

            return 0;
        }

        $totalStock = isset($prizeRow['total_stock']) ? (int) $prizeRow['total_stock'] : 0;
        $reservedStock = isset($prizeRow['reserved_stock']) ? (int) $prizeRow['reserved_stock'] : 0;
        $assignedStock = isset($prizeRow['assigned_stock']) ? (int) $prizeRow['assigned_stock'] : 0;
        return max(0, $totalStock - $reservedStock - $assignedStock);
    }
}

if (!function_exists('luckyDrawValidateVoucherPrizeStockConfig')) {
    function luckyDrawValidateVoucherPrizeStockConfig($prizeRow)
    {
        $prizeType = strtolower(trim((string) (isset($prizeRow['prize_type']) ? $prizeRow['prize_type'] : '')));
        if ($prizeType !== 'voucher') {
            return array('success' => true, 'message' => '');
        }

        // Voucher stock now comes from the code pool, so an empty pool simply means the prize
        // does not exist. Availability is gated separately by luckyDrawPrizeAvailableUnits(),
        // and the mirrored total_stock column must never turn a sold-out prize into a scary
        // 'misconfigured' error for the customer.
        return array('success' => true, 'message' => '');
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
            // A missing table genuinely breaks the draw, so it stays a hard error. A table that
            // exists but is not InnoDB is a deployment-hygiene warning only: the draw is written
            // to be safe without row locks (conditional UPDATE + affected_rows checks), so a
            // storage-engine choice must never black out a live campaign.
            $tableMissing = ($engine === '');
            if ($tableMissing) {
                $hasErrors = true;
            }
            $items[] = array(
                'key' => 'table_' . $tableName,
                'label' => $tableName . ' engine',
                'success' => ($engine === 'INNODB'),
                'detail' => ($engine === 'INNODB')
                    ? 'InnoDB ready.'
                    : ($tableMissing
                        ? 'Table is missing. Run insert_table.php to migrate.'
                        : ('Current engine: ' . $engine . '. The draw still works; converting to InnoDB is recommended for row-level locking.')),
            );
        }

        $fbEngine = luckyDrawTableEngine($financeConnect, dbFinance, FB_ORDER_REQ);
        // The finance order table is only touched when a physical prize is claimed, and that flow
        // reports its own error to the customer. It must not black out the public draw either.
        $items[] = array(
            'key' => 'facebook_order_request_engine',
            'label' => FB_ORDER_REQ . ' engine',
            'success' => ($fbEngine === 'INNODB'),
            'detail' => ($fbEngine === 'INNODB')
                ? 'InnoDB ready.'
                : ('Current engine: ' . ($fbEngine !== '' ? $fbEngine : 'missing') . '. Physical prize claims need this table; the public draw is unaffected.'),
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

        // Informational only: a missing birthday source never locks the public page.
        // Customers without a birthday simply get a clear rejection at draw time.
        $birthdayColumnsReady = luckyDrawCustomerBirthdayColumnsReady($connect);
        $birthdayCount = $birthdayColumnsReady ? luckyDrawCustomerBirthdayCount($connect) : 0;
        $items[] = array(
            'key' => 'customer_birthday_source',
            'label' => 'Customer birthday source',
            'success' => ($birthdayColumnsReady && $birthdayCount > 0),
            'detail' => !$birthdayColumnsReady
                ? ('customer_info is missing the birthday_year / birthday_month / birthday_day columns. Run insert_table.php to migrate.')
                : ($birthdayCount > 0
                    ? ($birthdayCount . ' customer(s) with birthday found.')
                    : 'No customer has a birthday on file yet.'),
        );

        $voucherPoolReady = luckyDrawVoucherCodeTableReady($connect);
        $voucherPoolSummary = array('available' => 0, 'reserved' => 0, 'assigned' => 0, 'total' => 0);
        if ($voucherPoolReady) {
            foreach (luckyDrawVoucherPoolCounts($connect) as $poolCount) {
                $voucherPoolSummary['available'] += (int) $poolCount['available'];
                $voucherPoolSummary['reserved'] += (int) $poolCount['reserved'];
                $voucherPoolSummary['assigned'] += (int) $poolCount['assigned'];
                $voucherPoolSummary['total'] += (int) $poolCount['total'];
            }
        }
        $items[] = array(
            'key' => 'voucher_code_pool',
            'label' => 'Voucher code pool',
            'success' => $voucherPoolReady,
            'detail' => !$voucherPoolReady
                ? (LUCKY_DRAW_VOUCHER_CODE . ' is missing. Run insert_table.php to migrate, otherwise voucher prizes have no stock.')
                : ($voucherPoolSummary['total'] . ' code(s): ' . $voucherPoolSummary['available'] . ' available, ' . $voucherPoolSummary['reserved'] . ' reserved, ' . $voucherPoolSummary['assigned'] . ' assigned. Every winner is issued their own code.'),
        );

        $prizeRows = luckyDrawFetchPrizeRows($connect, true);
        $voucherCounts = luckyDrawVoucherAvailableCounts($connect);
        $voucherStateCounts = luckyDrawVoucherStateCounts($connect);
        $readyPrizeCount = 0;
        $skippedPrizeCount = 0;
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

            // Zero available stock means the prize does not exist. It is neither usable
            // nor an error, so it must never block the rest of the draw.
            if ($availability <= 0) {
                $skippedPrizeCount++;
                continue;
            }

            $defaultsCheck = luckyDrawValidatePrizeDefaults($prizeRow);
            $stockCheck = array('success' => true, 'message' => '');
            $voucherConfigCheck = luckyDrawValidateVoucherPrizeStockConfig($prizeRow);
            if (strtolower((string) ($prizeRow['prize_type'] ?? '')) === 'physical') {
                $stockCheck = luckyDrawValidatePhysicalPrizeStock($connect, $financeConnect, $prizeRow);
            }

            $prizeReady = !empty($defaultsCheck['success']) && !empty($stockCheck['success']) && !empty($voucherConfigCheck['success']);
            if ($prizeReady) {
                $readyPrizeCount++;
            }

            $detailParts = array('Availability: ' . $availability);
            if (empty($voucherConfigCheck['success'])) {
                $detailParts[] = $voucherConfigCheck['message'];
            }
            if (empty($defaultsCheck['success'])) {
                $detailParts[] = $defaultsCheck['message'];
            }
            if (empty($stockCheck['success'])) {
                $detailParts[] = $stockCheck['message'];
            }
            if (!$prizeReady) {
                $detailParts[] = 'This prize is skipped in the draw until it is fixed.';
            }

            $items[] = array(
                'key' => 'prize_' . $prizeId,
                'label' => 'Prize: ' . (isset($prizeRow['prize_name']) ? $prizeRow['prize_name'] : ('#' . $prizeId)),
                'success' => $prizeReady,
                'detail' => implode(' | ', $detailParts),
            );
        }

        if ($skippedPrizeCount > 0) {
            $items[] = array(
                'key' => 'excluded_prizes',
                'label' => 'Prizes excluded (zero stock)',
                'success' => true,
                'detail' => $skippedPrizeCount . ' prize(s) have 0 available stock, so they are not part of the draw pool.',
            );
        }

        $items[] = array(
            'key' => 'active_prizes',
            'label' => 'Active prize pool',
            'success' => $readyPrizeCount > 0,
            'detail' => $readyPrizeCount > 0
                ? ($readyPrizeCount . ' prize(s) ready.')
                : 'No prize with stock is configured. Add stock to at least one prize to open the public draw.',
        );

        // Prize availability is a content state, not a system fault: a prize with zero stock
        // simply does not exist. An empty pool must therefore never lock the public page --
        // it is surfaced through `draw_available` so the operator still sees it at a glance.
        return array(
            'success' => !$hasErrors,
            'draw_available' => ($readyPrizeCount > 0),
            'ready_prize_count' => $readyPrizeCount,
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

if (!function_exists('luckyDrawSessionParticipationKey')) {
    function luckyDrawSessionParticipationKey()
    {
        return 'lucky_draw_participation_state';
    }
}

if (!function_exists('luckyDrawRememberParticipationSession')) {
    function luckyDrawRememberParticipationSession($claimToken, $claimUrl, $prizeRow = array(), $message = '')
    {
        $claimToken = trim((string) $claimToken);
        $claimUrl = trim((string) $claimUrl);
        $prizeRow = is_array($prizeRow) ? $prizeRow : array();

        $_SESSION[luckyDrawSessionParticipationKey()] = array(
            'participated' => true,
            'claim_token' => $claimToken,
            'claim_url' => $claimUrl,
            'message' => luckyDrawSafePublicText($message, 255),
            'saved_at' => date('Y-m-d H:i:s'),
            'prize' => array(
                'id' => isset($prizeRow['id']) ? (int) $prizeRow['id'] : 0,
                'name' => isset($prizeRow['prize_name']) ? (string) $prizeRow['prize_name'] : 'Prize',
                'type' => isset($prizeRow['prize_type']) ? (string) $prizeRow['prize_type'] : '',
                'image' => luckyDrawPrizeImageUrl(isset($prizeRow['prize_image']) ? (string) $prizeRow['prize_image'] : ''),
            ),
        );

        return $_SESSION[luckyDrawSessionParticipationKey()];
    }
}

if (!function_exists('luckyDrawForgetParticipationSession')) {
    function luckyDrawForgetParticipationSession()
    {
        unset($_SESSION[luckyDrawSessionParticipationKey()]);
    }
}

if (!function_exists('luckyDrawGetParticipationSessionState')) {
    function luckyDrawGetParticipationSessionState($connect = null)
    {
        $storedState = isset($_SESSION[luckyDrawSessionParticipationKey()]) && is_array($_SESSION[luckyDrawSessionParticipationKey()])
            ? $_SESSION[luckyDrawSessionParticipationKey()]
            : array();

        if (empty($storedState['participated'])) {
            return array(
                'participated' => false,
                'can_claim' => false,
                'claim_url' => '',
                'message' => '',
                'status_note' => '',
                'prize' => array(),
            );
        }

        $claimToken = trim((string) ($storedState['claim_token'] ?? ''));
        $claimUrl = trim((string) ($storedState['claim_url'] ?? ''));
        $storedPrize = isset($storedState['prize']) && is_array($storedState['prize']) ? $storedState['prize'] : array();
        $claimRow = array();

        if ($connect instanceof mysqli && $claimToken !== '') {
            $claimRow = luckyDrawFindClaimByToken($connect, $claimToken);
        }

        $claimState = trim((string) ($claimRow['claim_state'] ?? ''));
        $claimExpired = !empty($claimRow['reservation_expires_at']) && strtotime((string) $claimRow['reservation_expires_at']) < time();
        $canClaim = !empty($claimRow) && !$claimExpired && $claimState === 'awaiting_claim';

        $prizePayload = array(
            'id' => isset($storedPrize['id']) ? (int) $storedPrize['id'] : 0,
            'name' => isset($storedPrize['name']) ? (string) $storedPrize['name'] : 'Prize',
            'type' => isset($storedPrize['type']) ? (string) $storedPrize['type'] : '',
            'image' => isset($storedPrize['image']) ? (string) $storedPrize['image'] : '',
        );

        if (!empty($claimRow)) {
            $prizePayload = array(
                'id' => isset($claimRow['prize_id']) ? (int) $claimRow['prize_id'] : (isset($storedPrize['id']) ? (int) $storedPrize['id'] : 0),
                'name' => isset($claimRow['prize_name']) ? (string) $claimRow['prize_name'] : (isset($storedPrize['name']) ? (string) $storedPrize['name'] : 'Prize'),
                'type' => isset($claimRow['prize_type']) ? (string) $claimRow['prize_type'] : (isset($storedPrize['type']) ? (string) $storedPrize['type'] : ''),
                'image' => luckyDrawPrizeImageUrl(isset($claimRow['prize_image']) ? (string) $claimRow['prize_image'] : ''),
            );
            $claimUrl = $claimToken !== '' ? luckyDrawBuildClaimUrl($claimToken) : $claimUrl;
        }

        $message = trim((string) ($storedState['message'] ?? ''));
        if ($canClaim) {
            $message = 'Your previous draw is still available. Complete your claim now.';
        } elseif (!empty($claimRow) && $claimExpired) {
            $message = 'Your previous draw was found, but the claim link has expired.';
        } elseif (!empty($claimRow) && $claimState !== '' && $claimState !== 'awaiting_claim') {
            $message = 'Your claim has already been submitted.';
        } elseif ($message === '') {
            $message = 'You already participated the lucky draw.';
        }

        return array(
            'participated' => true,
            'can_claim' => $canClaim,
            'claim_url' => $canClaim ? $claimUrl : '',
            'message' => $message,
            'status_note' => 'You already participated the lucky draw.',
            'claim_state' => $claimState,
            'claim_expired' => $claimExpired,
            'prize' => $prizePayload,
        );
    }
}

if (!function_exists('luckyDrawUsernameHmac')) {
    function luckyDrawUsernameHmac($username)
    {
        $username = strtolower(trim((string) $username));
        if ($username === '') {
            return '';
        }

        return hash('sha256', $username);
    }
}

if (!function_exists('luckyDrawResolveCustomerBirthdayParts')) {
    function luckyDrawResolveCustomerBirthdayParts($customerRow)
    {
        $customerRow = is_array($customerRow) ? $customerRow : array();
        $year = isset($customerRow['birthday_year']) ? (int) $customerRow['birthday_year'] : 0;
        $month = isset($customerRow['birthday_month']) ? (int) $customerRow['birthday_month'] : 0;
        $day = isset($customerRow['birthday_day']) ? (int) $customerRow['birthday_day'] : 0;

        if ($year <= 0 || $month <= 0) {
            // Fallback: legacy birthday(DATE) column.
            $legacy = trim((string) (isset($customerRow['birthday']) ? $customerRow['birthday'] : ''));
            if ($legacy !== '' && substr($legacy, 0, 4) !== '0000' && strpos($legacy, '-') !== false) {
                $parts = explode('-', $legacy);
                if (count($parts) === 3) {
                    $year = (int) $parts[0];
                    $month = (int) $parts[1];
                    $day = (int) $parts[2];
                }
            }
        }

        return array('year' => $year, 'month' => $month, 'day' => $day);
    }
}

if (!function_exists('luckyDrawCustomerBirthdayColumnsReady')) {
    function luckyDrawCustomerBirthdayColumnsReady($connect)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        $ready = false;
        if (!($connect instanceof mysqli)) {
            return $ready;
        }

        try {
            $result = mysqli_query($connect, "SHOW COLUMNS FROM `" . CUS_INFO . "` LIKE 'birthday_%'");
            if ($result) {
                $found = array();
                while ($row = mysqli_fetch_assoc($result)) {
                    $found[strtolower((string) (isset($row['Field']) ? $row['Field'] : ''))] = true;
                }
                $ready = isset($found['birthday_year']) && isset($found['birthday_month']) && isset($found['birthday_day']);
            }
        } catch (Throwable $e) {
            // Missing table / missing columns: degrade to "not ready" instead of a fatal.
            $ready = false;
        }

        return $ready;
    }
}

if (!function_exists('luckyDrawCustomerBirthdayCount')) {
    function luckyDrawCustomerBirthdayCount($connect)
    {
        if (!($connect instanceof mysqli)) {
            return 0;
        }

        if (!luckyDrawCustomerBirthdayColumnsReady($connect)) {
            return 0;
        }

        try {
            $sql = "SELECT COUNT(*) AS total_count FROM `" . CUS_INFO . "`
                WHERE status = 'A'
                  AND birthday_year IS NOT NULL AND birthday_year > 0
                  AND birthday_month IS NOT NULL AND birthday_month > 0";
            $result = mysqli_query($connect, $sql);
            if ($result && ($row = mysqli_fetch_assoc($result))) {
                return (int) (isset($row['total_count']) ? $row['total_count'] : 0);
            }
        } catch (Throwable $e) {
            return 0;
        }

        return 0;
    }
}

if (!function_exists('luckyDrawFindCustomerInfoByName')) {
    function luckyDrawFindCustomerInfoByName($connect, $customerName)
    {
        $customerName = trim((string) $customerName);
        if (!($connect instanceof mysqli) || $customerName === '') {
            return null;
        }

        $safeName = mysqli_real_escape_string($connect, $customerName);
        $sql = "SELECT * FROM `" . CUS_INFO . "`
            WHERE LOWER(TRIM(`name`)) = LOWER('" . $safeName . "')
              AND `status` = 'A'
            ORDER BY `id` DESC
            LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return (array) $row;
        }

        return null;
    }
}

if (!function_exists('luckyDrawLookupCustomerByUsername')) {
    function luckyDrawLookupCustomerByUsername($connect, $financeConnect, $username)
    {
        $username = trim((string) $username);
        if (!($connect instanceof mysqli) || $username === '') {
            return array('success' => false, 'message' => 'Please enter your username.', 'member' => array());
        }

        $row = null;
        $source = '';

        // 1) The username typed is the Customer Info name.
        $row = luckyDrawFindCustomerInfoByName($connect, $username);
        if ($row !== null) {
            $source = 'customer_info';
        }

        // 2) The username typed is a Shopee buyer username. Resolve it to the Shopee
        //    customer's display name first, then match Customer Info on that name.
        if ($row === null && ($financeConnect instanceof mysqli)) {
            $safeUsernameFinance = mysqli_real_escape_string($financeConnect, $username);
            $shopeeSql = "SELECT * FROM `" . SHOPEE_CUST_INFO . "`
                WHERE LOWER(TRIM(`buyer_username`)) = LOWER('" . $safeUsernameFinance . "')
                  AND `status` = 'A'
                LIMIT 1";
            $shopeeResult = mysqli_query($financeConnect, $shopeeSql);
            if ($shopeeResult && ($shopeeRow = mysqli_fetch_assoc($shopeeResult))) {
                $nameCandidates = array();
                foreach (array('customer_name', 'name', 'buyer_username') as $nameColumn) {
                    if (isset($shopeeRow[$nameColumn])) {
                        $nameCandidates[] = trim((string) $shopeeRow[$nameColumn]);
                    }
                }

                foreach ($nameCandidates as $candidateName) {
                    if ($candidateName === '') {
                        continue;
                    }
                    $matchedRow = luckyDrawFindCustomerInfoByName($connect, $candidateName);
                    if ($matchedRow !== null) {
                        $row = $matchedRow;
                        $source = 'shopee';
                        break;
                    }
                }
            }
        }

        if ($row === null) {
            return array('success' => false, 'message' => 'We could not find this username.', 'member' => array());
        }

        $birthday = luckyDrawResolveCustomerBirthdayParts($row);
        if ($birthday['year'] <= 0 || $birthday['month'] <= 0) {
            return array('success' => false, 'message' => 'This customer does not have a birthday record yet.', 'member' => array());
        }

        $displayName = trim((string) (isset($row['name']) ? $row['name'] : ''));
        if ($displayName === '') {
            $displayName = $username;
        }

        // The draw key is the canonical Customer Info name, so a member who types their
        // Shopee username one day and their name the next still only gets one draw.
        return array(
            'success' => true,
            'message' => '',
            'source' => $source,
            'member' => array(
                'source_id' => (int) (isset($row['id']) ? $row['id'] : 0),
                'customer_username' => luckyDrawSafePublicText($username, 190),
                'member_name' => luckyDrawSafePublicText($displayName, 190),
                'member_id_hmac' => luckyDrawUsernameHmac($displayName),
                'birthday_year' => (int) $birthday['year'],
                'birthday_month' => (int) $birthday['month'],
                'birthday_day' => (int) $birthday['day'],
            ),
        );
    }
}

if (!function_exists('luckyDrawValidateBirthdayYearMonth')) {
    function luckyDrawValidateBirthdayYearMonth($memberRow, $inputYear, $inputMonth)
    {
        if (empty($memberRow)) {
            return array('success' => false, 'message' => 'This member is not eligible for the birthday draw.');
        }

        $inputYear = (int) $inputYear;
        $inputMonth = (int) $inputMonth;
        if ($inputYear < 1900 || $inputYear > 2200 || $inputMonth < 1 || $inputMonth > 12) {
            return array('success' => false, 'message' => 'Please select a valid birth year and month.');
        }

        $storedYear = (int) (isset($memberRow['birthday_year']) ? $memberRow['birthday_year'] : 0);
        $storedMonth = (int) (isset($memberRow['birthday_month']) ? $memberRow['birthday_month'] : 0);
        if ($storedYear !== $inputYear || $storedMonth !== $inputMonth) {
            return array('success' => false, 'message' => 'The birth year and month do not match our record.');
        }

        // The draw only opens during the member's birthday month.
        if ($storedMonth !== (int) date('n')) {
            return array('success' => false, 'message' => 'This Lucky Draw is only available during your birthday month.');
        }

        return array('success' => true, 'message' => '');
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

if (!function_exists('luckyDrawFindUrbanMemberByDisplayName')) {
    function luckyDrawFindUrbanMemberByDisplayName($connect, $displayName)
    {
        $displayName = strtolower(trim((string) $displayName));
        if (!($connect instanceof mysqli) || $displayName === '') {
            return array();
        }

        $safeDisplayName = mysqli_real_escape_string($connect, $displayName);
        $sql = "SELECT * FROM `" . URBAN_CUST_REG . "`
            WHERE LOWER(TRIM(name)) = '" . $safeDisplayName . "'
            LIMIT 1";
        $result = mysqli_query($connect, $sql);
        return ($result && ($row = mysqli_fetch_assoc($result))) ? (array) $row : array();
    }
}

if (!function_exists('luckyDrawFetchHistoryByMemberName')) {
    function luckyDrawFetchHistoryByMemberName($connect, $displayName)
    {
        $displayName = trim((string) $displayName);
        if ($displayName === '') {
            return array();
        }

        // Draw records are keyed by the username typed at draw time. Keep the legacy
        // IC-derived hash as a fallback so older rows still resolve.
        $memberRow = luckyDrawFindUrbanMemberByDisplayName($connect, $displayName);
        $hmacCandidates = array();
        if (!empty($memberRow)) {
            $legacyHmac = luckyDrawMemberIdHmac(luckyDrawNormalizeFullId(isset($memberRow['ic']) ? $memberRow['ic'] : ''));
            if ($legacyHmac !== '') {
                $hmacCandidates[] = mysqli_real_escape_string($connect, $legacyHmac);
            }
        }
        $usernameHmac = luckyDrawUsernameHmac($displayName);
        if ($usernameHmac !== '') {
            $hmacCandidates[] = mysqli_real_escape_string($connect, $usernameHmac);
        }

        $safeDisplayName = mysqli_real_escape_string($connect, $displayName);
        $hmacList = !empty($hmacCandidates) ? ("'" . implode("', '", $hmacCandidates) . "'") : "''";
        $sql = "SELECT *
            FROM `" . LUCKY_DRAW_DRAW_LOG . "`
            WHERE (
                    `member_id_hmac` IN (" . $hmacList . ")
                    OR LOWER(TRIM(`customer_username`)) = LOWER('" . $safeDisplayName . "')
                    OR LOWER(TRIM(`member_display_name`)) = LOWER('" . $safeDisplayName . "')
                )
              AND `status` = 'A'
            ORDER BY `id` DESC";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return array();
        }

        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $facebookOrderRequestId = isset($row['facebook_order_request_id']) ? (int) $row['facebook_order_request_id'] : 0;
            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'redeem_reference' => isset($row['redeem_reference']) ? (string) $row['redeem_reference'] : '',
                'prize_name_snapshot' => isset($row['prize_name_snapshot']) ? (string) $row['prize_name_snapshot'] : '',
                'prize_type_snapshot' => isset($row['prize_type_snapshot']) ? (string) $row['prize_type_snapshot'] : '',
                'draw_state' => isset($row['draw_state']) ? (string) $row['draw_state'] : '',
                'claim_state' => isset($row['claim_state']) ? (string) $row['claim_state'] : '',
                'email_state' => isset($row['email_state']) ? (string) $row['email_state'] : '',
                'claim_email' => isset($row['claim_email']) ? (string) $row['claim_email'] : '',
                'failure_message' => isset($row['failure_message']) ? (string) $row['failure_message'] : '',
                'facebook_order_request_id' => $facebookOrderRequestId,
                'view_url' => $facebookOrderRequestId > 0 && function_exists('memberPointBuildOrderViewUrl')
                    ? memberPointBuildOrderViewUrl('facebook', $facebookOrderRequestId)
                    : '',
                'created_at' => trim((string) (isset($row['create_date']) ? $row['create_date'] : '') . ' ' . (isset($row['create_time']) ? $row['create_time'] : '')),
                'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
                'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            );
        }

        return $rows;
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
    function luckyDrawCreateReservation($connect, $financeConnect, $memberRow, $memberHmac, $ipHmac)
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
                throw new Exception('You already participated the lucky draw.');
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

                // Zero available stock means the prize does not exist, so it is skipped silently
                // instead of surfacing as a broken configuration to the customer.
                if ((float) ($prizeRow['weight'] ?? 0) <= 0 || $availableUnits <= 0) {
                    continue;
                }

                $defaultsCheck = luckyDrawValidatePrizeDefaults($prizeRow);
                $voucherConfigCheck = luckyDrawValidateVoucherPrizeStockConfig($prizeRow);
                if (empty($defaultsCheck['success']) || empty($voucherConfigCheck['success'])) {
                    continue;
                }

                $eligiblePrizeRows[] = $prizeRow;
            }

            if (empty($eligiblePrizeRows)) {
                throw new Exception('No prize is available right now. Please try again later.');
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
            $safeCustomerUsername = mysqli_real_escape_string($connect, luckyDrawSafePublicText(isset($memberRow['customer_username']) ? $memberRow['customer_username'] : '', 190));
            $birthdayYearValue = isset($memberRow['birthday_year']) ? (int) $memberRow['birthday_year'] : 0;
            $birthdayMonthValue = isset($memberRow['birthday_month']) ? (int) $memberRow['birthday_month'] : 0;
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
                (`member_id_hmac`, `member_display_name`, `customer_username`, `birthday_year`, `birthday_month`, `ip_hmac`, `prize_id`, `prize_name_snapshot`, `prize_type_snapshot`, `redeem_reference`, `draw_state`, `claim_state`, `email_state`, `claim_token_hash`, `reservation_expires_at`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                ('" . $safeMemberHmac . "', '" . $safeMemberName . "', '" . $safeCustomerUsername . "', " . $birthdayYearValue . ", " . $birthdayMonthValue . ", '" . $safeIpHmac . "', " . $prizeId . ", '" . $safePrizeName . "', '" . $safePrizeType . "', '" . $safeRedeemReference . "', 'won', '" . $safeClaimState . "', '" . $safeEmailState . "', '" . $safeClaimTokenHash . "', '" . $safeReservationExpiry . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($connect, $insertSql)) {
                throw new Exception('Unable to save the draw result.');
            }

            $drawLogId = (int) mysqli_insert_id($connect);

            if ($prizeType === 'voucher') {
                // One winner, one code: lock a single unused code from the prize pool instead of
                // handing the same shared voucher code to everybody who wins this prize.
                $reservedVoucherCode = luckyDrawVoucherReserveCode($connect, $prizeId, $drawLogId);
                if ($reservedVoucherCode === '') {
                    throw new Exception('The selected voucher prize is no longer available.');
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
                'message' => 'Congratulations! Your draw result is ready.',
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
        $sql = "SELECT dl.*, p.prize_name, p.prize_image, p.prize_type, p.voucher_code, p.package_id, p.country_id, p.brand_id, p.series_id,
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
if (!function_exists('luckyDrawCreatePhysicalFacebookOrderRequest')) {
    function luckyDrawCreatePhysicalFacebookOrderRequest($financeConnect, $drawRow, $claimData, $claimEmail)
    {
        if (!($financeConnect instanceof mysqli) || !is_array($drawRow)) {
            return 0;
        }

        $intOrNull = function ($value) {
            $intValue = (int) $value;
            return $intValue > 0 ? (string) $intValue : 'NULL';
        };

        $winnerName = luckyDrawSafePublicText(isset($drawRow['member_display_name']) ? $drawRow['member_display_name'] : '', 100);
        if ($winnerName === '') {
            $winnerName = 'Lucky Draw Winner';
        }

        $receiverName = luckyDrawSafePublicText(isset($claimData['receiver_name']) ? $claimData['receiver_name'] : $winnerName, 100);
        if ($receiverName === '') {
            $receiverName = $winnerName;
        }

        $receiverPhone = luckyDrawSafePublicText(isset($claimData['phone']) ? $claimData['phone'] : '', 50);
        $receiverAddress = luckyDrawSafePublicText(isset($claimData['address']) ? $claimData['address'] : '', 255);
        $redeemReference = luckyDrawSafePublicText(isset($drawRow['redeem_reference']) ? $drawRow['redeem_reference'] : '', 60);
        $prizeName = luckyDrawSafePublicText(isset($drawRow['prize_name_snapshot']) ? $drawRow['prize_name_snapshot'] : (isset($drawRow['prize_name']) ? $drawRow['prize_name'] : ''), 120);
        $remark = luckyDrawSafePublicText('Lucky Draw Claim' . ($redeemReference !== '' ? (' | Ref: ' . $redeemReference) : '') . ($claimEmail !== '' ? (' | Email: ' . $claimEmail) : '') . ($prizeName !== '' ? (' | Prize: ' . $prizeName) : ''), 255);

        $safeName = mysqli_real_escape_string($financeConnect, $winnerName);
        $safeContact = mysqli_real_escape_string($financeConnect, $receiverPhone);
        $safeReceiverName = mysqli_real_escape_string($financeConnect, $receiverName);
        $safeReceiverAddress = mysqli_real_escape_string($financeConnect, $receiverAddress);
        $safeReceiverContact = mysqli_real_escape_string($financeConnect, $receiverPhone);
        $safeRemark = mysqli_real_escape_string($financeConnect, $remark);
        $safeActor = mysqli_real_escape_string($financeConnect, 'PUBLIC');
        $price = number_format(max(0, (float) (isset($drawRow['price']) ? $drawRow['price'] : 0)), 2, '.', '');

        $sql = "INSERT INTO `" . FB_ORDER_REQ . "`
            (name, fb_link, contact, sales_pic, country, brand, series, package, fb_page, channel, price, pay_method, ship_rec_name, ship_rec_add, ship_rec_contact, remark, attachment, order_status, stock_out_warehouse_id, create_by, create_date, create_time, status)
            VALUES
            ('" . $safeName . "', '', '" . $safeContact . "', " . $intOrNull(isset($drawRow['sales_pic_user_id']) ? $drawRow['sales_pic_user_id'] : 0) . ", " . $intOrNull(isset($drawRow['country_id']) ? $drawRow['country_id'] : 0) . ", " . $intOrNull(isset($drawRow['brand_id']) ? $drawRow['brand_id'] : 0) . ", " . $intOrNull(isset($drawRow['series_id']) ? $drawRow['series_id'] : 0) . ", " . $intOrNull(isset($drawRow['package_id']) ? $drawRow['package_id'] : 0) . ", " . $intOrNull(isset($drawRow['fb_page_id']) ? $drawRow['fb_page_id'] : 0) . ", " . $intOrNull(isset($drawRow['channel_id']) ? $drawRow['channel_id'] : 0) . ", '" . $price . "', " . $intOrNull(isset($drawRow['pay_method_id']) ? $drawRow['pay_method_id'] : 0) . ", '" . $safeReceiverName . "', '" . $safeReceiverAddress . "', '" . $safeReceiverContact . "', '" . $safeRemark . "', '', 'P', " . $intOrNull(isset($drawRow['stock_out_warehouse_id']) ? $drawRow['stock_out_warehouse_id'] : 0) . ", '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";

        if (!mysqli_query($financeConnect, $sql)) {
            throw new Exception('Unable to create the Facebook order request: ' . mysqli_error($financeConnect));
        }

        return (int) mysqli_insert_id($financeConnect);
    }
}


if (!function_exists('luckyDrawSubmitClaim')) {
    function luckyDrawSubmitClaim($connect, $financeConnect, $rawToken, $claimData)
    {
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return array('success' => false, 'message' => 'Lucky Draw is unavailable right now.', 'field_errors' => array());
        }

        $claimData = is_array($claimData) ? $claimData : array();
        $rawToken = trim((string) $rawToken);
        if ($rawToken === '') {
            return array('success' => false, 'message' => 'The claim link is invalid.', 'field_errors' => array());
        }

        $email = luckyDrawSafePublicText(isset($claimData['email']) ? $claimData['email'] : '', 190);
        if ($email === '') {
            return array(
                'success' => false,
                'message' => 'Please check the highlighted field and try again.',
                'field_errors' => array(
                    'email' => 'Invalid email.',
                ),
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array(
                'success' => false,
                'message' => 'Please check the highlighted field and try again.',
                'field_errors' => array(
                    'email' => 'Invalid email format.',
                ),
            );
        }

        mysqli_begin_transaction($connect);
        try {
            $tokenHash = hash('sha256', $rawToken);
            $safeTokenHash = mysqli_real_escape_string($connect, $tokenHash);
            $sql = "SELECT dl.*, p.prize_name, p.prize_type, p.voucher_code, p.package_id, p.country_id, p.brand_id, p.series_id,
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

            $prizeType = strtolower(trim((string) (isset($drawRow['prize_type']) ? $drawRow['prize_type'] : '')));
            $safeActor = mysqli_real_escape_string($connect, 'PUBLIC');
            $safeEmail = mysqli_real_escape_string($connect, $email);

            if ($prizeType === 'voucher') {
                // The code this winner reserved at draw time is now permanently theirs.
                luckyDrawVoucherAssignCode($connect, (int) $drawRow['id']);

                mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                    SET claim_email = '" . $safeEmail . "',
                        claim_state = 'claimed',
                        email_state = 'pending',
                        email_locked_at = NULL,
                        email_lock_token = '',
                        sent_at = NULL,
                        failure_message = '',
                        update_by = '" . $safeActor . "',
                        update_date = CURDATE(),
                        update_time = CURTIME()
                    WHERE id = " . (int) $drawRow['id'] . "
                    LIMIT 1");

                mysqli_commit($connect);
                $emailSendResult = luckyDrawSendClaimEmailNow($connect, (int) $drawRow['id'], 'PUBLIC');
                return array(
                    'success' => true,
                    'message' => !empty($emailSendResult['success'])
                        ? 'Your voucher claim has been submitted and the email has been sent.'
                        : 'Your voucher claim has been submitted, but we could not send the email right now. Please contact support if you do not receive it.',
                    'type' => 'voucher',
                    'email_sent' => !empty($emailSendResult['success']),
                );
            }

            $stockCheck = luckyDrawValidatePhysicalPrizeStock($connect, $financeConnect, $drawRow);
            if (empty($stockCheck['success'])) {
                throw new Exception(isset($stockCheck['message']) ? (string) $stockCheck['message'] : 'Warehouse stock is not available for this prize.');
            }

            $facebookOrderRequestId = (int) (isset($drawRow['facebook_order_request_id']) ? $drawRow['facebook_order_request_id'] : 0);
            if ($facebookOrderRequestId <= 0) {
                $facebookOrderRequestId = luckyDrawCreatePhysicalFacebookOrderRequest($financeConnect, $drawRow, $claimData, $email);
            }

            if ($facebookOrderRequestId <= 0) {
                throw new Exception('Unable to create the Facebook order request.');
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
                    email_state = 'pending',
                    email_locked_at = NULL,
                    email_lock_token = '',
                    sent_at = NULL,
                    failure_message = '',
                    facebook_order_request_id = " . $facebookOrderRequestId . ",
                    update_by = '" . $safeActor . "',
                    update_date = CURDATE(),
                    update_time = CURTIME()
                WHERE id = " . (int) $drawRow['id'] . "
                LIMIT 1");

            mysqli_commit($connect);
            $emailSendResult = luckyDrawSendClaimEmailNow($connect, (int) $drawRow['id'], 'PUBLIC');
            return array(
                'success' => true,
                'message' => !empty($emailSendResult['success'])
                    ? 'Your claim has been submitted successfully and the email has been sent.'
                    : 'Your claim has been submitted successfully, but we could not send the email right now. Please contact support if you do not receive it.',
                'type' => 'physical',
                'facebook_order_request_id' => $facebookOrderRequestId,
                'email_sent' => !empty($emailSendResult['success']),
            );
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => $exception->getMessage(), 'field_errors' => array());
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
                $sql = "SELECT dl.*, p.prize_name, p.prize_type, vc.voucher_code
                    FROM `" . LUCKY_DRAW_DRAW_LOG . "` dl
                    INNER JOIN `" . LUCKY_DRAW_PRIZE . "` p ON p.id = dl.prize_id AND p.status = 'A'
                    LEFT JOIN `" . LUCKY_DRAW_VOUCHER_CODE . "` vc ON vc.draw_log_id = dl.id AND vc.status = 'A'
                    WHERE dl.status = 'A'
                      AND dl.prize_type_snapshot = 'voucher'
                      AND dl.claim_state = 'claimed'
                      AND dl.email_state IN ('pending', 'failed', 'sending')
                      AND (
                          dl.email_state IN ('pending', 'failed')
                          OR dl.email_locked_at IS NULL
                          OR dl.email_locked_at < '" . mysqli_real_escape_string($connect, $staleBoundary) . "'
                      )
                    ORDER BY dl.id ASC
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

if (!function_exists('luckyDrawBuildPhysicalEmailContent')) {
    function luckyDrawBuildPhysicalEmailContent($drawRow)
    {
        $prizeName = luckyDrawSafePublicText(isset($drawRow['prize_name_snapshot']) ? $drawRow['prize_name_snapshot'] : 'Your Prize', 255);
        $redeemReference = luckyDrawSafePublicText(isset($drawRow['redeem_reference']) ? $drawRow['redeem_reference'] : '', 60);
        $subject = 'Lucky Draw Claim Confirmation - ' . $prizeName;
        $message = '<html><body style="font-family:Arial,sans-serif;background:#f6f3ea;padding:24px;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;padding:28px;">'
            . '<h2 style="margin-top:0;">Claim Received</h2>'
            . '<p>Your Lucky Draw claim has been submitted successfully.</p>'
            . '<p><strong>Prize:</strong> ' . htmlspecialchars($prizeName, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Reference:</strong> ' . htmlspecialchars($redeemReference, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Our team will follow up on the prize fulfillment using this email if needed.</p>'
            . '<p>Please keep this email for your records.</p>'
            . '</div></body></html>';

        return array(
            'subject' => $subject,
            'message' => $message,
        );
    }
}

if (!function_exists('luckyDrawSendClaimEmailNow')) {
    function luckyDrawSendClaimEmailNow($connect, $drawLogId, $actorUserId = 'SYSTEM', $allowedPrizeTypes = array())
    {
        $drawLogId = (int) $drawLogId;
        $allowedPrizeTypes = is_array($allowedPrizeTypes) ? $allowedPrizeTypes : array();
        if (!($connect instanceof mysqli) || $drawLogId <= 0) {
            return array('success' => false, 'message' => 'Unable to send the Lucky Draw email right now.');
        }

        $normalizedAllowedPrizeTypes = array();
        foreach ($allowedPrizeTypes as $allowedPrizeType) {
            $allowedPrizeType = strtolower(trim((string) $allowedPrizeType));
            if ($allowedPrizeType !== '') {
                $normalizedAllowedPrizeTypes[] = $allowedPrizeType;
            }
        }

        mysqli_begin_transaction($connect);

        try {
            $lockToken = bin2hex(random_bytes(16));
            $sql = "SELECT dl.*, p.prize_name, p.prize_type, p.voucher_code
                FROM `" . LUCKY_DRAW_DRAW_LOG . "` dl
                INNER JOIN `" . LUCKY_DRAW_PRIZE . "` p ON p.id = dl.prize_id AND p.status = 'A'
                WHERE dl.id = " . $drawLogId . "
                  AND dl.status = 'A'
                LIMIT 1
                FOR UPDATE";
            $result = mysqli_query($connect, $sql);
            if (!$result || !($drawRow = mysqli_fetch_assoc($result))) {
                throw new Exception('Lucky Draw email record not found.');
            }

            $prizeType = strtolower(trim((string) ($drawRow['prize_type_snapshot'] ?? $drawRow['prize_type'] ?? '')));
            if (!in_array($prizeType, array('voucher', 'physical'), true)) {
                throw new Exception('This Lucky Draw prize type does not support claim email sending.');
            }

            if (!empty($normalizedAllowedPrizeTypes) && !in_array($prizeType, $normalizedAllowedPrizeTypes, true)) {
                throw new Exception('This Lucky Draw email action is not allowed for the selected prize type.');
            }

            if (trim((string) ($drawRow['claim_state'] ?? '')) !== 'claimed') {
                throw new Exception('Only claimed Lucky Draw records can send email.');
            }

            $claimEmail = trim((string) ($drawRow['claim_email'] ?? ''));
            if (!filter_var($claimEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('A valid claim email is required before sending.');
            }

            $emailState = strtolower(trim((string) ($drawRow['email_state'] ?? '')));
            $emailLockedAt = trim((string) ($drawRow['email_locked_at'] ?? ''));
            $sendingLockIsFresh = $emailState === 'sending'
                && $emailLockedAt !== ''
                && strtotime($emailLockedAt) >= strtotime('-15 minutes');
            if ($sendingLockIsFresh) {
                throw new Exception('This Lucky Draw email is already being sent.');
            }

            $safeActor = mysqli_real_escape_string($connect, luckyDrawSafePublicText($actorUserId, 30));
            $safeLockToken = mysqli_real_escape_string($connect, $lockToken);
            if (!mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_DRAW_LOG . "`
                SET email_state = 'sending',
                    email_locked_at = NOW(),
                    email_lock_token = '" . $safeLockToken . "',
                    failure_message = '',
                    update_by = '" . $safeActor . "',
                    update_date = CURDATE(),
                    update_time = CURTIME()
                WHERE id = " . $drawLogId . "
                LIMIT 1")) {
                throw new Exception('Unable to lock this Lucky Draw email send.');
            }

            mysqli_commit($connect);

            if ($prizeType === 'voucher') {
                $voucherCode = luckyDrawSafePublicText(luckyDrawVoucherCodeByDrawLog($connect, $drawLogId), 255);
                if ($voucherCode === '') {
                    luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, false, 'Voucher code is missing for this prize.');
                    return array(
                        'success' => false,
                        'message' => 'Voucher code is missing for this prize.',
                        'type' => 'voucher',
                        'email' => $claimEmail,
                    );
                }

                $emailContent = luckyDrawBuildVoucherEmailContent($drawRow, $voucherCode);
            } else {
                $emailContent = luckyDrawBuildPhysicalEmailContent($drawRow);
            }

            $sent = commonSendSystemEmail($connect, $claimEmail, $emailContent['subject'], $emailContent['message'], array(
                'auto_submitted' => true,
            ));

            $failureMessage = $sent ? '' : ('Failed to send ' . $prizeType . ' claim email.');
            luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, $sent, $failureMessage);

            return array(
                'success' => $sent,
                'message' => $sent ? 'Lucky Draw email sent successfully.' : 'Failed to send the Lucky Draw email.',
                'type' => $prizeType,
                'email' => $claimEmail,
            );
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            return array(
                'success' => false,
                'message' => $exception->getMessage(),
            );
        }
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
            if ($drawLogId <= 0 || $lockToken === '' || !filter_var($claimEmail, FILTER_VALIDATE_EMAIL)) {
                luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, false, 'Missing email or voucher prize data.');
                $processed['failed']++;
                continue;
            }

            $voucherCode = luckyDrawSafePublicText(isset($queueRow['voucher_code']) ? $queueRow['voucher_code'] : '', 255);
            if ($voucherCode === '') {
                luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, false, 'Voucher code is missing for this prize.');
                $processed['failed']++;
                continue;
            }

            $emailContent = luckyDrawBuildVoucherEmailContent($queueRow, $voucherCode);
            $sent = commonSendSystemEmail($connect, $claimEmail, $emailContent['subject'], $emailContent['message'], array(
                'auto_submitted' => true,
            ));
            luckyDrawMarkEmailSendResult($connect, $drawLogId, $lockToken, $sent, $sent ? '' : 'Failed to send voucher email.');
            if ($sent) {
                $processed['sent']++;
            } else {
                $processed['failed']++;
            }
        }

        return $processed;
    }
}

if (!function_exists('luckyDrawResendVoucherEmailNow')) {
    function luckyDrawResendVoucherEmailNow($connect, $financeConnect, $drawLogId, $actorUserId = 'SYSTEM')
    {
        $drawLogId = (int) $drawLogId;
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli) || $drawLogId <= 0) {
            return array('success' => false, 'message' => 'Unable to resend the voucher email right now.');
        }

        $sendResult = luckyDrawSendClaimEmailNow($connect, $drawLogId, $actorUserId, array('voucher'));
        return array(
            'success' => !empty($sendResult['success']),
            'message' => !empty($sendResult['success']) ? 'Voucher email resent successfully.' : (isset($sendResult['message']) ? (string) $sendResult['message'] : 'Failed to resend the voucher email.'),
            'email' => isset($sendResult['email']) ? (string) $sendResult['email'] : '',
        );
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
                        // Put the untouched code back so the next birthday member can win it.
                        luckyDrawVoucherReleaseCode($connect, (int) $drawRow['id']);
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

        $virtualSql = "SELECT display_name, display_prize, id
            FROM `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
            WHERE status = 'A'
              AND is_enabled = 'Y'
            ORDER BY id DESC
            LIMIT " . $limit;
        $virtualResult = mysqli_query($connect, $virtualSql);
        if ($virtualResult) {
            while ($row = mysqli_fetch_assoc($virtualResult)) {
                $rows[] = array(
                    'display_name' => luckyDrawMaskDisplayName(isset($row['display_name']) ? (string) $row['display_name'] : 'Lucky Member'),
                    'display_prize' => isset($row['display_prize']) ? (string) $row['display_prize'] : 'Prize',
                    'source' => 'virtual',
                    'sort_stamp' => 'virtual-' . (isset($row['id']) ? (int) $row['id'] : 0),
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
