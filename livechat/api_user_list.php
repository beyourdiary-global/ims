<?php
header('Content-Type: application/json');

session_start();
include_once __DIR__ . '/../include/connection.php';
include_once __DIR__ . '/livechat_common.php';

if (!isset($_SESSION['userid']) && !isset($_SESSION['usr_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

$currentUserId = (int)(isset($_SESSION['userid']) ? $_SESSION['userid'] : $_SESSION['usr_id']);

// Mark current user as online
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

foreach ($users as $userId => &$user) {
    if (isset($statusMap[$userId])) {
        $user['is_online'] = (bool)$statusMap[$userId]['is_online'];
        $user['last_seen'] = $statusMap[$userId]['last_seen'];
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
    'current_user_id' => $currentUserId
));
?>
