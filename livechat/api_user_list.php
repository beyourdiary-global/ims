<?php
header('Content-Type: application/json');

// Don't use init.php which changes session.save_path
// Instead, just start session and connect database directly
session_start();

// Get userId from session
$currentUserId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : (isset($_SESSION['usr_id']) ? (int)$_SESSION['usr_id'] : 0);

// Check session
if ($currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

// Connect to database directly (avoiding init.php session issues)
$connect = @mysqli_connect('127.0.0.1:3306', 'beyourdi_cms', 'Byd1234@Global', 'beyourdi_cms-uat');
if (!$connect) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'Database connection failed'));
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

include_once __DIR__ . '/livechat_common.php';

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
