<?php

include '../init.php';
include ROOT . '/include/connection.php';
include ROOT . '/include/common.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(array('success' => false, 'message' => 'Unauthorized.'));
    exit;
}

$requestId = input('id');
$message = '';
$ok = sorRefreshTrackingStatus($finance_connect, $requestId, $message, $connect);

$latestStatus = '';
$latestSync = '';
$safeId = (int) $requestId;
if ($safeId > 0) {
    $rst = mysqli_query($finance_connect, "SELECT tracking_status, tracking_last_sync FROM " . STOCK_ORDER_REQ . " WHERE id='" . $safeId . "' LIMIT 1");
    if ($rst && ($row = mysqli_fetch_assoc($rst))) {
        $latestStatus = isset($row['tracking_status']) ? (string) $row['tracking_status'] : '';
        $latestSync = isset($row['tracking_last_sync']) ? (string) $row['tracking_last_sync'] : '';
    }
}

echo json_encode(array(
    'success' => $ok,
    'message' => $message,
    'tracking_status' => $latestStatus,
    'tracking_last_sync' => $latestSync,
));
