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
$platform = trim((string) post('platform'));
$platformCustomerId = trim((string) post('platform_customer_id'));
$customerName = trim((string) post('customer_name'));
$customerContact = trim((string) post('customer_contact'));

if ($campaignId <= 0 || $platform === '' || $platformCustomerId === '') {
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
    echo json_encode(['success' => false, 'message' => 'Campaign period已结束，无法添加客户']);
    exit();
}

// 检查客户是否已存在
$checkStmt = $connect->prepare("SELECT `id` FROM `" . CAMPAIGN2_CUSTOMER . "` WHERE `campaign_id`=? AND `platform`=? AND `platform_customer_id`=? AND `status`='A' LIMIT 1");
if (!$checkStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$checkStmt->bind_param('iss', $campaignId, $platform, $platformCustomerId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult && $checkResult->num_rows > 0) {
    $checkStmt->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => '该客户已分配给此Campaign']);
    exit();
}
$checkStmt->close();

// 插入新客户
$stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN2_CUSTOMER . "` (`campaign_id`, `platform`, `platform_customer_id`, `customer_name`, `customer_contact`, `assign_by`, `assign_date`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, CURDATE(), CURTIME(), 'A')");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$assignBy = (string) USER_ID;
$createBy = (string) USER_ID;
$stmt->bind_param('issssss', $campaignId, $platform, $platformCustomerId, $customerName, $customerContact, $assignBy, $createBy);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to assign customer: ' . $stmt->error]);
    $stmt->close();
    exit();
}

$customerId = (int) $connect->insert_id;
$stmt->close();

// 审计日志
campaign2Audit($connect, 'Campaign2', 'assign_customer', USER_NAME . ' assigned customer to Campaign2 ID=' . $campaignId . ', platform=' . $platform . ', customer_id=' . $platformCustomerId, 'INSERT INTO ' . CAMPAIGN2_CUSTOMER);

echo json_encode([
    'success' => true,
    'message' => 'Customer assigned successfully',
    'customer_id' => $customerId
]);
