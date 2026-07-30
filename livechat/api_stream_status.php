<?php
// Server-Sent Events for real-time online status updates

header('Cache-Control: no-cache');
header('Content-Type: text/event-stream');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['userid']) && !isset($_SESSION['usr_id'])) {
    http_response_code(401);
    exit;
}

require_once dirname(__DIR__) . '/init.php';
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
