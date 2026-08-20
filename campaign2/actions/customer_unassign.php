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

if ($campaignId <= 0 || $customerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit();
}

$campaign = campaign2FetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Campaign不存在']);
    exit();
}

// 检查period是否已过
if (campaign2IsPeriodEnded($campaign)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Campaign period已结束，无法删除客户']);
    exit();
}

// 解除客户分配（soft delete）
$safeUserId = $connect->real_escape_string((string) USER_ID);
$stmt = $connect->prepare("UPDATE `" . CAMPAIGN2_CUSTOMER . "` SET `status`='D', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `campaign_id`=? AND `status`='A'");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$updateBy = (string) USER_ID;
$stmt->bind_param('sii', $updateBy, $customerId, $campaignId);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to unassign customer: ' . $stmt->error]);
    $stmt->close();
    exit();
}

$affectedRows = $stmt->affected_rows;
$stmt->close();

if ($affectedRows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit();
}

// 审计日志
campaign2Audit($connect, 'Campaign2', 'unassign_customer', USER_NAME . ' unassigned customer from Campaign2 ID=' . $campaignId . ', customer_id=' . $customerId, 'UPDATE ' . CAMPAIGN2_CUSTOMER);

echo json_encode([
    'success' => true,
    'message' => 'Customer unassigned successfully'
]);
