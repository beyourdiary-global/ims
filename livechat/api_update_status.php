<?php
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['userid']) && !isset($_SESSION['usr_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

require_once dirname(__DIR__) . '/init.php';
include_once __DIR__ . '/livechat_common.php';

$currentUserId = (int)(isset($_SESSION['userid']) ? $_SESSION['userid'] : $_SESSION['usr_id']);
$isOnline = isset($_POST['is_online']) ? (int)$_POST['is_online'] : 0;

// First, check if record exists
$checkResult = mysqli_query($connect, "SELECT user_id, is_online FROM livechat_user_status WHERE user_id = $currentUserId");
$existsMsg = ($checkResult && $checkResult->num_rows > 0) ? "exists" : "not found";
if ($checkResult && $checkResult->num_rows > 0) {
    $oldRow = $checkResult->fetch_assoc();
    $oldStatus = $oldRow['is_online'];
} else {
    $oldStatus = null;
}

// Initialize if not exists
livechatInitUserStatus($connect, $currentUserId);

// Update status directly with custom query for better logging
$updateQuery = "UPDATE livechat_user_status SET is_online = $isOnline, last_activity = NOW() WHERE user_id = $currentUserId";
$updateResult = mysqli_query($connect, $updateQuery);
$affectedRows = mysqli_affected_rows($connect);

error_log("[LiveChat] Status update: userId=$currentUserId, isOnline=$isOnline, recordExisted=$existsMsg, oldStatus=$oldStatus, affectedRows=$affectedRows");

// Verify the update worked
$verifyResult = mysqli_query($connect, "SELECT is_online FROM livechat_user_status WHERE user_id = $currentUserId");
$verifyStatus = null;
if ($verifyResult && $verifyResult->num_rows > 0) {
    $row = $verifyResult->fetch_assoc();
    $verifyStatus = (int)$row['is_online'];
}

http_response_code(200);
echo json_encode(array(
    'success' => true,
    'user_id' => $currentUserId,
    'is_online' => $isOnline,
    'affected_rows' => $affectedRows,
    'verified_status' => $verifyStatus,
    'debug_msg' => "Updated from $oldStatus to $isOnline, record $existsMsg"
));
?>
