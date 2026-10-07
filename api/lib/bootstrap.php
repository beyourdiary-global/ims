<?php
/**
 * Beyourdiary CMS - REST API v1 bootstrap
 * ------------------------------------------------------------------
 * Shared by every api/*.php endpoint. Endpoints must define CMS_API_ENTRY
 * before requiring this file.
 *
 * Responsibilities:
 *   1. Force a JSON response and stop stray HTML output from corrupting it.
 *   2. Load the CMS runtime (init.php -> $connect / $finance_connect).
 *      init.php only opens database connections - it does NOT require a
 *      login session, which is exactly what an API needs.
 *   3. Authenticate the caller against the `api_key` table.
 *   4. Small helpers for query params, paging and JSON output.
 *
 * Target runtime: ea-php74 (production). PHP 7.4 syntax only.
 */

if (!defined('CMS_API_ENTRY')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"ok":false,"error":{"code":"direct_access_forbidden","message":"This file cannot be requested directly."}}';
    exit;
}

if (!defined('CMS_API_VERSION')) {
    define('CMS_API_VERSION', 'v1');
}

if (!defined('CMS_API_KEY_TABLE')) {
    define('CMS_API_KEY_TABLE', 'api_key');
}

// A PHP notice echoed into the body would break the JSON contract.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Referrer-Policy: no-referrer');
}

$GLOBALS['cms_api_response_sent'] = false;

if (!function_exists('cmsApiEncode')) {
    function cmsApiEncode($payload)
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            return '{"ok":false,"error":{"code":"encode_failed","message":"The response could not be encoded as JSON."}}';
        }

        return $json;
    }
}

if (!function_exists('cmsApiSend')) {
    function cmsApiSend($httpStatus, $payload)
    {
        if (!empty($GLOBALS['cms_api_response_sent'])) {
            return;
        }
        $GLOBALS['cms_api_response_sent'] = true;

        if (!headers_sent()) {
            http_response_code((int) $httpStatus);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo cmsApiEncode($payload);
        exit;
    }
}

if (!function_exists('cmsApiOk')) {
    function cmsApiOk($data, $meta = array())
    {
        $envelope = array('ok' => true, 'data' => $data);
        if (is_array($meta) && !empty($meta)) {
            $envelope['meta'] = $meta;
        }
        cmsApiSend(200, $envelope);
    }
}

if (!function_exists('cmsApiFail')) {
    function cmsApiFail($httpStatus, $code, $message, $extra = array())
    {
        $error = array(
            'code' => (string) $code,
            'message' => (string) $message,
        );

        if (is_array($extra) && !empty($extra)) {
            $error['details'] = $extra;
        }

        cmsApiSend((int) $httpStatus, array('ok' => false, 'error' => $error));
    }
}

// A fatal error must still answer with JSON instead of a blank 200 page.
register_shutdown_function(function () {
    $last = error_get_last();
    if (!is_array($last)) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($last['type'], $fatalTypes, true)) {
        return;
    }

    if (!empty($GLOBALS['cms_api_response_sent'])) {
        return;
    }

    cmsApiSend(500, array(
        'ok' => false,
        'error' => array(
            'code' => 'internal_error',
            'message' => 'The API stopped on a fatal error.',
            'details' => array(
                'hint' => basename((string) $last['file']) . ':' . (int) $last['line'],
            ),
        ),
    ));
});

// ------------------------------------------------------------------
// CMS runtime
// ------------------------------------------------------------------
// init.php opens both database connections. On PHP 7.4 a failed connect just
// returns false; on newer runtimes mysqli throws instead. Catch either way so
// a database outage always answers with JSON, never a blank page.
$cmsApiPreviousErrorReporting = error_reporting(0);
try {
    require_once dirname(__DIR__) . '/init.php';
    error_reporting($cmsApiPreviousErrorReporting);
} catch (Throwable $cmsApiBootstrapError) {
    error_reporting($cmsApiPreviousErrorReporting);
    cmsApiFail(503, 'db_unavailable', 'The CMS runtime could not be initialised.');
}

if (!isset($connect) || !($connect instanceof mysqli)) {
    cmsApiFail(503, 'db_unavailable', 'The CMS database connection is not available.');
}

if (!isset($finance_connect) || !($finance_connect instanceof mysqli)) {
    cmsApiFail(503, 'db_unavailable', 'The finance database connection is not available.');
}

if (!function_exists('cmsApiLoadCommon')) {
    function cmsApiLoadCommon()
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        require_once ROOT . '/include/common.php';
        $loaded = true;
    }
}

cmsApiLoadCommon();

// ------------------------------------------------------------------
// API key authentication
// ------------------------------------------------------------------
if (!function_exists('cmsApiReadProvidedKey')) {
    /**
     * Accept the key from, in order of preference:
     *   1. X-API-Key header              (recommended)
     *   2. Authorization: Bearer <key>   (standard)
     *   3. ?api_key=<key>                (handy for quick tests, easiest to leak)
     */
    function cmsApiReadProvidedKey()
    {
        if (isset($_SERVER['HTTP_X_API_KEY']) && is_string($_SERVER['HTTP_X_API_KEY'])) {
            $value = trim($_SERVER['HTTP_X_API_KEY']);
            if ($value !== '') {
                return $value;
            }
        }

        $authorization = '';
        foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $serverKey) {
            if (isset($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
                $candidate = trim($_SERVER[$serverKey]);
                if ($candidate !== '') {
                    $authorization = $candidate;
                    break;
                }
            }
        }

        if ($authorization === '' && function_exists('apache_request_headers')) {
            $headers = @apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $headerName => $headerValue) {
                    if (strtolower((string) $headerName) === 'authorization') {
                        $authorization = trim((string) $headerValue);
                        break;
                    }
                }
            }
        }

        if ($authorization !== '' && stripos($authorization, 'bearer ') === 0) {
            $value = trim(substr($authorization, 7));
            if ($value !== '') {
                return $value;
            }
        }

        if (isset($_GET['api_key']) && is_string($_GET['api_key'])) {
            return trim($_GET['api_key']);
        }

        return '';
    }
}

if (!function_exists('cmsApiEnsureKeyTable')) {
    /**
     * The key table is created on first use so the API works without a
     * separate migration step. The CREATE is idempotent.
     */
    function cmsApiEnsureKeyTable($connect)
    {
        $table = CMS_API_KEY_TABLE;

        $probe = @mysqli_query($connect, 'SELECT 1 FROM `' . $table . '` LIMIT 1');
        if ($probe !== false) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `" . $table . "` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(190) NOT NULL DEFAULT '',
            `key_hash` CHAR(64) NOT NULL,
            `key_prefix` VARCHAR(24) NOT NULL DEFAULT '',
            `scopes` VARCHAR(255) NOT NULL DEFAULT 'read',
            `status` CHAR(1) NOT NULL DEFAULT 'A',
            `created_at` DATETIME DEFAULT NULL,
            `created_by` VARCHAR(190) DEFAULT NULL,
            `last_used_at` DATETIME DEFAULT NULL,
            `last_used_ip` VARCHAR(64) DEFAULT NULL,
            `request_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_api_key_hash` (`key_hash`),
            KEY `idx_api_key_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        return (bool) @mysqli_query($connect, $sql);
    }
}

if (!function_exists('cmsApiAuthenticate')) {
    /**
     * Validate the presented key and return its row.
     * Terminates the request with a JSON error when the key is missing,
     * unknown, revoked or lacking the requested scope.
     */
    function cmsApiAuthenticate($connect, $requiredScope = 'read')
    {
        if (!cmsApiEnsureKeyTable($connect)) {
            cmsApiFail(503, 'key_store_unavailable', 'The API key store could not be initialised.');
        }

        $provided = cmsApiReadProvidedKey();
        if ($provided === '') {
            cmsApiFail(
                401,
                'missing_api_key',
                'No API key was supplied. Send it in the "X-API-Key" header, as "Authorization: Bearer <key>", or as "?api_key=<key>".'
            );
        }

        if (strlen($provided) < 16 || strlen($provided) > 200) {
            cmsApiFail(401, 'invalid_api_key', 'The supplied API key is not valid.');
        }

        $table = CMS_API_KEY_TABLE;
        $safeHash = mysqli_real_escape_string($connect, hash('sha256', $provided));
        $result = mysqli_query(
            $connect,
            "SELECT * FROM `" . $table . "` WHERE `key_hash` = '" . $safeHash . "' AND `status` = 'A' LIMIT 1"
        );

        if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
            cmsApiFail(401, 'invalid_api_key', 'The supplied API key is not valid or has been revoked.');
        }

        $keyRow = $result->fetch_assoc();

        $scopes = array();
        foreach (explode(',', strtolower((string) $keyRow['scopes'])) as $scope) {
            $scope = trim($scope);
            if ($scope !== '') {
                $scopes[] = $scope;
            }
        }

        $requiredScope = strtolower(trim((string) $requiredScope));
        if ($requiredScope !== '' && !in_array($requiredScope, $scopes, true) && !in_array('*', $scopes, true)) {
            cmsApiFail(403, 'insufficient_scope', 'This API key does not carry the "' . $requiredScope . '" scope.');
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 64) : '';
        $safeIp = mysqli_real_escape_string($connect, $ip);
        $keyId = (int) $keyRow['id'];

        @mysqli_query(
            $connect,
            "UPDATE `" . $table . "`
                SET `last_used_at` = NOW(),
                    `last_used_ip` = '" . $safeIp . "',
                    `request_count` = `request_count` + 1
              WHERE `id` = " . $keyId
        );

        $keyRow['scopes_list'] = $scopes;
        $keyRow['client_ip'] = $ip;

        return $keyRow;
    }
}

// ------------------------------------------------------------------
// Request helpers
// ------------------------------------------------------------------
if (!function_exists('cmsApiQueryValue')) {
    function cmsApiQueryValue($key, $maxLength = 256)
    {
        if (!isset($_GET[$key]) || is_array($_GET[$key])) {
            return '';
        }

        $value = trim((string) $_GET[$key]);
        if ($value === '' || strlen($value) > $maxLength) {
            return '';
        }

        return $value;
    }
}

if (!function_exists('cmsApiQueryInt')) {
    function cmsApiQueryInt($key, $default = 0, $min = null, $max = null)
    {
        if (!isset($_GET[$key]) || is_array($_GET[$key])) {
            return $default;
        }

        $raw = trim((string) $_GET[$key]);
        if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
            return $default;
        }

        $value = (int) $raw;
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }
}

if (!function_exists('cmsApiPaging')) {
    function cmsApiPaging($defaultLimit = 50, $maxLimit = 500)
    {
        $limit = cmsApiQueryInt('limit', $defaultLimit, 1, $maxLimit);
        $offset = cmsApiQueryInt('offset', 0, 0, null);

        return array('limit' => $limit, 'offset' => $offset);
    }
}

if (!function_exists('cmsApiPage')) {
    function cmsApiPage($rows, $limit, $offset)
    {
        $rows = array_values((array) $rows);
        $total = count($rows);

        return array(
            'total' => $total,
            'count' => max(0, min($limit, $total - $offset)),
            'limit' => (int) $limit,
            'offset' => (int) $offset,
            'has_more' => ($offset + $limit) < $total,
            'rows' => array_slice($rows, $offset, $limit),
        );
    }
}

if (!function_exists('cmsApiMeta')) {
    function cmsApiMeta($endpoint, $keyRow = array(), $extra = array())
    {
        $meta = array(
            'endpoint' => (string) $endpoint,
            'api_version' => CMS_API_VERSION,
            'generated_at' => date('c'),
        );

        if (is_array($keyRow) && !empty($keyRow)) {
            $meta['key_name'] = isset($keyRow['name']) ? (string) $keyRow['name'] : '';
            $meta['scopes'] = isset($keyRow['scopes_list']) ? $keyRow['scopes_list'] : array();
        }

        if (is_array($extra) && !empty($extra)) {
            $meta = array_merge($meta, $extra);
        }

        return $meta;
    }
}
