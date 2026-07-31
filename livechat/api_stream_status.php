<?php
// Server-Sent Events for real-time online status updates

header('Cache-Control: no-cache');
header('Content-Type: text/event-stream');
header('Access-Control-Allow-Origin: *');

// Start session before any output
session_start();

// Early session check
$currentUserId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : (isset($_SESSION['usr_id']) ? (int)$_SESSION['usr_id'] : 0);
if ($currentUserId <= 0) {
    http_response_code(401);
    exit;
}

// Direct database connection (avoid init.php session issues)
$connect = @mysqli_connect('127.0.0.1:3306', 'beyourdi_cms', 'Byd1234@Global', 'beyourdi_cms-uat');
if (!$connect) {
    http_response_code(500);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

include_once __DIR__ . '/livechat_common.php';

$currentUserId = (int)(isset($_SESSION['userid']) ? $_SESSION['userid'] : $_SESSION['usr_id']);

// Send initial connection message
echo "data: " . json_encode(array('type' => 'connected', 'message' => 'Status stream connected')) . "\n\n";
flush();

$lastStatusJson = '';
$maxInactivity = 300; // 5 minutes
$startTime = time();
$checkCount = 0;

while (true) {
    // Check if client disconnected
    if (connection_status() !== CONNECTION_NORMAL) {
        break;
    }

    // Send heartbeat every 30 seconds
    if ($checkCount % 10 === 0) {
        echo "data: " . json_encode(array('type' => 'heartbeat', 'timestamp' => date('Y-m-d H:i:s'))) . "\n\n";
        flush();
    }
    $checkCount++;

    // Get all users except current
    $query = "SELECT id, name FROM usr_user WHERE id != $currentUserId ORDER BY name ASC";
    $result = mysqli_query($connect, $query);
    $currentUserIds = array();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $currentUserIds[] = (int)$row['id'];
        }
    }

    // Get current status map
    $statusMap = livechatGetUserStatus($connect, $currentUserIds);
    $statusJson = json_encode($statusMap);

    // Check if status changed (compare JSON strings)
    if ($statusJson !== $lastStatusJson) {
        echo "data: " . json_encode(array(
            'type' => 'status_update',
            'status_map' => $statusMap,
            'timestamp' => date('Y-m-d H:i:s')
        )) . "\n\n";
        flush();
        error_log("[LiveChat] Status changed for user $currentUserId: " . $statusJson);
        $lastStatusJson = $statusJson;
    }

    // Sleep for 3 seconds before checking again
    sleep(3);
}
?>
