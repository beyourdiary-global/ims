<?php
header('Content-Type: application/json');

session_start();

// Check session early to prevent connection.php redirect
if (!isset($_SESSION['userid']) && !isset($_SESSION['usr_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

// Initialize database without triggering connection.php redirect
require_once dirname(__DIR__) . '/init.php';
include_once __DIR__ . '/livechat_common.php';

$currentUserId = (int)(isset($_SESSION['userid']) ? $_SESSION['userid'] : $_SESSION['usr_id']);

// Initialize and mark current user as online
livechatInitUserStatus($connect, $currentUserId);
livechatUpdateUserStatus($connect, $currentUserId, true);

// Get all users except current user
$query = "
    SELECT u.id, u.name, u.email
    FROM " . USR_USER . " u
    WHERE u.id != $currentUserId
    ORDER BY u.name ASC
";

$result = mysqli_query($connect, $query);
$users = array();
$userIds = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $userId = (int)$row['id'];
        $userIds[] = $userId;
        // Initialize user status if not exists
        livechatInitUserStatus($connect, $userId);
        $users[$userId] = array(
            'id' => $userId,
            'name' => $row['name'],
            'email' => $row['email'],
            'is_online' => false,
            'unread_count' => 0
        );
    }
}

// Get online status
$statusMap = livechatGetUserStatus($connect, $userIds);

error_log("[LiveChat] Status map: " . json_encode($statusMap));

foreach ($users as $userId => &$user) {
    if (isset($statusMap[$userId])) {
        $user['is_online'] = (bool)$statusMap[$userId]['is_online'];
        $user['last_seen'] = $statusMap[$userId]['last_seen'];
        error_log("[LiveChat] User $userId is_online: " . $user['is_online']);
    } else {
        error_log("[LiveChat] User $userId not found in status map");
    }

    // Get unread messages from this user
    $unreadQuery = "
        SELECT COUNT(*) as count
        FROM livechat_messages
        WHERE sender_id = $userId
        AND recipient_id = $currentUserId
        AND is_read = 0
    ";
    $unreadResult = mysqli_query($connect, $unreadQuery);
    if ($unreadResult) {
        $unreadRow = $unreadResult->fetch_assoc();
        $user['unread_count'] = (int)$unreadRow['count'];
    }
}
unset($user);

http_response_code(200);
echo json_encode(array(
    'success' => true,
    'users' => array_values($users),
    'current_user_id' => $currentUserId,
    'debug' => array(
        'user_count' => count($users),
        'status_map' => $statusMap
    )
));
?>
