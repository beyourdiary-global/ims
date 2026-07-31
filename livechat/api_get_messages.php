<?php
header('Content-Type: application/json');

session_start();

$currentUserId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : (isset($_SESSION['usr_id']) ? (int)$_SESSION['usr_id'] : 0);

if ($currentUserId <= 0) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

// Direct database connection (avoid init.php session issues)
$connect = @mysqli_connect('127.0.0.1:3306', 'beyourdi_cms', 'Byd1234@Global', 'beyourdi_cms-uat');
if (!$connect) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'Database connection failed'));
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

include_once __DIR__ . '/livechat_common.php';
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
