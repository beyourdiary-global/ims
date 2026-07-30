<?php
header('Content-Type: application/json');

session_start();
include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/livechat_common.php';

if (!isset($_SESSION['userid']) && !isset($_SESSION['usr_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

$currentUserId = (int)(isset($_SESSION['userid']) ? $_SESSION['userid'] : $_SESSION['usr_id']);
$otherUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

if ($otherUserId <= 0) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Invalid user'));
    exit;
}

$messages = livechatGetMessages($connect, $currentUserId, $otherUserId, $limit, $offset);

http_response_code(200);
echo json_encode(array(
    'success' => true,
    'messages' => $messages,
    'total' => count($messages)
));
?>
