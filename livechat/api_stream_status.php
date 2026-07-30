<?php
// Server-Sent Events for real-time online status updates

header('Cache-Control: no-cache');
header('Content-Type: text/event-stream');
header('Access-Control-Allow-Origin: *');

session_start();
include_once __DIR__ . '/../include/connection.php';
include_once __DIR__ . '/livechat_common.php';

if (!isset($_SESSION['userid']) && !isset($_SESSION['usr_id'])) {
    http_response_code(401);
    exit;
}

$currentUserId = (int)(isset($_SESSION['userid']) ? $_SESSION['userid'] : $_SESSION['usr_id']);

// Send initial connection message
echo "data: " . json_encode(array('type' => 'connected', 'message' => 'Status stream connected')) . "\n\n";
flush();

$lastStatusMap = array();
$maxInactivity = 300; // 5 minutes
$startTime = time();

while (true) {
    // Check if client disconnected
    if (connection_status() !== CONNECTION_NORMAL) {
        break;
    }

    // Check for timeout
    if (time() - $startTime > $maxInactivity) {
        echo "data: " . json_encode(array('type' => 'heartbeat')) . "\n\n";
        flush();
        $startTime = time();
    }

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

    // Check if status changed
    if ($statusMap !== $lastStatusMap) {
        echo "data: " . json_encode(array(
            'type' => 'status_update',
            'status_map' => $statusMap,
            'timestamp' => date('Y-m-d H:i:s')
        )) . "\n\n";
        flush();
        $lastStatusMap = $statusMap;
    }

    // Sleep for 3 seconds before checking again
    sleep(3);
}
?>
