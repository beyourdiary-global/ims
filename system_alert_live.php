<?php
include_once 'include/connection.php';
include_once 'include/common.php';
include_once 'include/common_variable.php';
include_once ROOT . '/include/system_alert_common.php';

if (!function_exists('systemAlertLiveJsonResponse')) {
    function systemAlertLiveJsonResponse($payload, $statusCode = 200)
    {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('systemAlertLiveFormatDateLabel')) {
    function systemAlertLiveFormatDateLabel($alertRow)
    {
        $dateValue = trim((string) (isset($alertRow['display_date']) && $alertRow['display_date'] !== '' ? $alertRow['display_date'] : (isset($alertRow['create_date']) ? $alertRow['create_date'] : '')));
        $timeValue = trim((string) (isset($alertRow['create_time']) ? $alertRow['create_time'] : ''));
        if ($dateValue === '') {
            return '';
        }

        $timestamp = strtotime($dateValue . ($timeValue !== '' ? (' ' . $timeValue) : ''));
        if ($timestamp === false) {
            return $dateValue;
        }

        return date('d M Y', $timestamp) . ($timeValue !== '' ? (' ' . date('H:i', $timestamp)) : '');
    }
}

if (!defined('USER_ID') || !(int) USER_ID) {
    systemAlertLiveJsonResponse(array(
        'success' => false,
        'message' => 'Unauthenticated.',
    ), 401);
}

$userId = (int) USER_ID;
$currentUrl = trim((string) input('current_url'));
$limit = (int) input('limit');
if ($limit <= 0) {
    $limit = 10;
}
if ($limit > 20) {
    $limit = 20;
}

if ($currentUrl === '') {
    $currentUrl = isset($_SERVER['HTTP_REFERER']) && trim((string) $_SERVER['HTTP_REFERER']) !== ''
        ? (string) $_SERVER['HTTP_REFERER']
        : systemAlertBuildDefaultUrl();
}

$nowTs = time();
$lastSyncTs = isset($_SESSION['system_alert_last_sync_ts']) ? (int) $_SESSION['system_alert_last_sync_ts'] : 0;

if ($lastSyncTs <= 0 || ($nowTs - $lastSyncTs) >= 60) {
    $_SESSION['system_alert_last_sync_ts'] = $nowTs;

    if (function_exists('systemAlertGenerateForUser')) {
        systemAlertGenerateForUser($connect, isset($finance_connect) ? $finance_connect : $connect, $userId);
    } else if (function_exists('systemAlertSyncFollowUpNotificationsForUser')) {
        systemAlertSyncFollowUpNotificationsForUser($connect, $userId);
    }
}

$unreadCount = function_exists('systemAlertGetUnreadCount') ? (int) systemAlertGetUnreadCount($connect, $userId) : 0;
$totalCount = function_exists('systemAlertGetTotalCount') ? (int) systemAlertGetTotalCount($connect, $userId) : 0;
$rows = function_exists('systemAlertFetchForUser') ? systemAlertFetchForUser($connect, $userId, $limit) : array();

$items = array();
foreach ($rows as $row) {
    $alertId = isset($row['id']) ? (int) $row['id'] : 0;
    if ($alertId <= 0) {
        continue;
    }

    $items[] = array(
        'id' => $alertId,
        'title' => trim((string) (isset($row['title']) ? $row['title'] : 'Notification')),
        'message' => trim((string) (isset($row['message']) ? $row['message'] : '')),
        'is_unread' => strtoupper(trim((string) (isset($row['is_read']) ? $row['is_read'] : 'N'))) !== 'Y',
        'time_label' => systemAlertLiveFormatDateLabel($row),
        'link' => systemAlertBuildOpenUrl($alertId, $currentUrl),
    );
}

systemAlertLiveJsonResponse(array(
    'success' => true,
    'unread_count' => $unreadCount,
    'total_count' => $totalCount,
    'mark_all_url' => $unreadCount > 0
        ? systemAlertBuildMarkAllUrl($currentUrl)
        : '',
    'items' => $items,
));
