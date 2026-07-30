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
$isOnline = isset($_POST['is_online']) ? (int)$_POST['is_online'] : 0;

// Initialize if not exists
livechatInitUserStatus($connect, $currentUserId);

// Update status
$updateResult = livechatUpdateUserStatus($connect, $currentUserId, $isOnline);

error_log("[LiveChat] Status update: userId=$currentUserId, isOnline=$isOnline, result=$updateResult");

http_response_code(200);
echo json_encode(array(
    'success' => true,
    'user_id' => $currentUserId,
    'is_online' => $isOnline
));
?>
