<?php

include_once 'include/connection.php';
include_once 'include/common.php';
include_once 'include/common_variable.php';
include_once ROOT . '/include/system_alert_common.php';

if (!function_exists('systemAlertActionRedirect')) {
function systemAlertActionRedirect($targetUrl)
{
    $defaultUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') . '/dashboard.php' : 'dashboard.php';

    $targetUrl = trim((string) $targetUrl);
    if ($targetUrl === '') {
        $targetUrl = $defaultUrl;
    }

    $parsed = @parse_url($targetUrl);
    $scheme = is_array($parsed) && isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
    $host = is_array($parsed) && isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';
    $siteHost = defined('SITEURL') ? strtolower((string) parse_url((string) SITEURL, PHP_URL_HOST)) : '';

    if (
        $parsed === false
        || strpos($targetUrl, '//') === 0
        || ($scheme !== '' && !in_array($scheme, array('http', 'https'), true))
        || ($host !== '' && ($siteHost === '' || strcasecmp($host, $siteHost) !== 0))
    ) {
        $targetUrl = $defaultUrl;
    }

    echo '<script>location.replace(' . json_encode($targetUrl) . ');</script>';
    exit;
}
}

if (!function_exists('systemAlertActionMarkFollowUpNotificationRead')) {
    function systemAlertActionMarkFollowUpNotificationRead($connect, $notificationId, $userId)
    {
        $notificationId = (int) $notificationId;
        $userId = (int) $userId;
        if (!($connect instanceof mysqli) || $notificationId <= 0 || $userId <= 0 || !defined('CUSTOMER_FOLLOW_UP_NOTIFICATION')) {
            return false;
        }

        $sql = "UPDATE `" . CUSTOMER_FOLLOW_UP_NOTIFICATION . "`
                SET `is_read` = 'Y',
                    `read_date` = CURDATE(),
                    `read_time` = CURTIME()
                WHERE `id` = " . $notificationId . "
                  AND `notify_user_id` = " . $userId . "
                  AND `status` = 'A'
                LIMIT 1";

        return mysqli_query($connect, $sql) ? true : false;
    }
}

if (!defined('USER_ID') || !(int) USER_ID) {
    systemAlertActionRedirect((defined('SITEURL') ? rtrim((string) SITEURL, '/') : '') . '/index.php');
}

$userId = (int) USER_ID;
$action = trim((string) input('action'));
$alertId = (int) input('id');
$redirectParam = trim((string) input('redirect'));
$fallbackUrl = $redirectParam !== ''
    ? $redirectParam
    : (isset($_SERVER['HTTP_REFERER']) && trim((string) $_SERVER['HTTP_REFERER']) !== ''
        ? (string) $_SERVER['HTTP_REFERER']
        : ((defined('SITEURL') ? rtrim((string) SITEURL, '/') : '') . '/dashboard.php'));

if ($action === 'mark_all') {
    systemAlertMarkAllRead($connect, $userId);
    if (defined('CUSTOMER_FOLLOW_UP_NOTIFICATION')) {
        mysqli_query(
            $connect,
            "UPDATE `" . CUSTOMER_FOLLOW_UP_NOTIFICATION . "`
             SET `is_read` = 'Y',
                 `read_date` = CURDATE(),
                 `read_time` = CURTIME()
             WHERE `notify_user_id` = " . $userId . "
               AND `is_read` = 'N'
               AND `status` = 'A'"
        );
    }

    systemAlertActionRedirect($fallbackUrl);
}

if ($action === 'mark_read') {
    if ($alertId > 0) {
        systemAlertMarkRead($connect, $alertId, $userId);
        if (defined('CUSTOMER_FOLLOW_UP_NOTIFICATION')) {
            $alertRow = systemAlertReadRow($connect, $alertId, $userId);
            if (
                !empty($alertRow)
                && isset($alertRow['related_table'])
                && (string) $alertRow['related_table'] === CUSTOMER_FOLLOW_UP_NOTIFICATION
                && (int) (isset($alertRow['related_id']) ? $alertRow['related_id'] : 0) > 0
            ) {
                systemAlertActionMarkFollowUpNotificationRead($connect, (int) $alertRow['related_id'], $userId);
            }
        }
    }

    systemAlertActionRedirect($fallbackUrl);
}

$alertRow = systemAlertReadRow($connect, $alertId, $userId);
if (empty($alertRow)) {
    systemAlertActionRedirect($fallbackUrl);
}

systemAlertMarkRead($connect, $alertId, $userId);
if (
    defined('CUSTOMER_FOLLOW_UP_NOTIFICATION')
    && isset($alertRow['related_table'])
    && (string) $alertRow['related_table'] === CUSTOMER_FOLLOW_UP_NOTIFICATION
    && (int) (isset($alertRow['related_id']) ? $alertRow['related_id'] : 0) > 0
) {
    systemAlertActionMarkFollowUpNotificationRead($connect, (int) $alertRow['related_id'], $userId);
}

$targetUrl = trim((string) (isset($alertRow['action_url']) ? $alertRow['action_url'] : ''));
systemAlertActionRedirect($targetUrl !== '' ? $targetUrl : $fallbackUrl);
