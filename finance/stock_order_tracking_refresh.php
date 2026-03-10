<?php
$isFinance = 1;
include '../init.php';
include ROOT . '/include/connection.php';
include ROOT . '/include/common.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(array('success' => false, 'message' => 'Unauthorized.'));
    exit;
}

sorEnsureSchema($finance_connect);

$requestId = input('id');
$message = '';
$ok = sorRefreshTrackingStatus($finance_connect, $requestId, $message, $connect);

echo json_encode(array(
    'success' => $ok,
    'message' => $message,
));
