<?php
include_once '../../init.php';
include_once ROOT . '/include/campaign2_common.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权']);
    exit();
}

$campaignId = (int) post('campaign_id');
$customerId = (int) post('customer_id');
$messageShortcutId = post('message_shortcut_id');
$followupDate = trim((string) post('followup_date'));
$picUserId = post('pic_user_id');

if ($campaignId <= 0 || $customerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit();
}

if ($followupDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followupDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid follow-up date']);
    exit();
}

// 获取campaign信息
$campaign = campaign2FetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Campaign not found']);
    exit();
}

// 获取客户信息
$stmt = $connect->prepare("SELECT `id` FROM `" . CAMPAIGN2_CUSTOMER . "` WHERE `id`=? AND `campaign_id`=? AND `status`='A' LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param('ii', $customerId, $campaignId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    $stmt->close();
    exit();
}
$stmt->close();

// 获取message shortcut内容（如果提供了ID）
$messageTitle = '';
$messagePreview = '';
if (!empty($messageShortcutId) && $messageShortcutId !== '0' && $messageShortcutId !== '') {
    $shortcutId = (int) $messageShortcutId;
    if (defined('MESSAGE_SHORTCUTS') && campaign2TableExists($connect, MESSAGE_SHORTCUTS)) {
        $shortcutStmt = $connect->prepare("SELECT `shortcuts_tag`, `shortcuts_message` FROM `" . MESSAGE_SHORTCUTS . "` WHERE `id`=? LIMIT 1");
        if ($shortcutStmt) {
            $shortcutStmt->bind_param('i', $shortcutId);
            $shortcutStmt->execute();
            $shortcutResult = $shortcutStmt->get_result();
            if ($shortcutResult && $shortcutResult->num_rows > 0) {
                $shortcutRow = $shortcutResult->fetch_assoc();
                $messageTitle = campaign2NormalizeTextValue($shortcutRow['shortcuts_tag'] ?? '', 255);
                $messagePreview = campaign2NormalizeTextValue($shortcutRow['shortcuts_message'] ?? '', 5000);
            }
            $shortcutStmt->close();
        }
    }
}

// 如果没有获取到message title，用默认值
if ($messageTitle === '') {
    $messageTitle = 'Follow-up Task - ' . $followupDate;
}

// 解析pic_user_id
$picUserIdInt = 0;
if (!empty($picUserId) && is_numeric($picUserId)) {
    $picUserIdInt = (int) $picUserId;
}

// 创建follow-up task
$stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN2_FOLLOW_UP . "` (`campaign_id`, `campaign2_customer_id`, `message_shortcut_id`, `message_title`, `message_preview`, `follow_up_date`, `pic_user_id`, `follow_up_status`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, CURDATE(), CURTIME(), 'A')");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$messageShortcutIdInt = empty($messageShortcutId) || $messageShortcutId === '0' ? null : (int) $messageShortcutId;
$createBy = (string) USER_ID;
$stmt->bind_param('iiisissss', $campaignId, $customerId, $messageShortcutIdInt, $messageTitle, $messagePreview, $followupDate, $picUserIdInt, $createBy);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create follow-up: ' . $stmt->error]);
    $stmt->close();
    exit();
}

$followupTaskId = (int) $connect->insert_id;
$stmt->close();

// 审计日志
campaign2Audit($connect, 'Campaign2', 'create_followup', USER_NAME . ' created follow-up task ID=' . $followupTaskId . ' for campaign=' . $campaignId, 'INSERT INTO ' . CAMPAIGN2_FOLLOW_UP);

echo json_encode([
    'success' => true,
    'message' => 'Follow-up task created successfully',
    'followup_id' => $followupTaskId
]);
