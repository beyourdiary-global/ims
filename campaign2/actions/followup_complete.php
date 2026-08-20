<?php
include_once '../../init.php';
include_once ROOT . '/include/campaign2_common.php';
include_once ROOT . '/include/customer_tag.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未授权']);
    exit();
}

$followupId = (int) post('followup_id');
$remark = campaign2NormalizeTextValue(trim((string) post('remark')), 5000);

if ($followupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '缺少followup_id参数']);
    exit();
}

// 获取follow-up信息
$stmt = $connect->prepare("SELECT f.`id`, f.`campaign_id`, f.`campaign2_customer_id`, f.`follow_up_date`, f.`follow_up_status`, c.`platform`, c.`platform_customer_id`, c.`customer_name`, cmp.`campaign_name` FROM `" . CAMPAIGN2_FOLLOW_UP . "` f LEFT JOIN `" . CAMPAIGN2_CUSTOMER . "` c ON c.`id`=f.`campaign2_customer_id` LEFT JOIN `" . CAMPAIGN2 . "` cmp ON cmp.`id`=f.`campaign_id` WHERE f.`id`=? AND f.`status`='A' LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param('i', $followupId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Follow-up task not found']);
    $stmt->close();
    exit();
}

$followupRow = $result->fetch_assoc();
$stmt->close();

$campaignId = (int) ($followupRow['campaign_id'] ?? 0);
$customerId = (int) ($followupRow['campaign2_customer_id'] ?? 0);
$platform = trim((string) ($followupRow['platform'] ?? ''));
$platformCustomerId = trim((string) ($followupRow['platform_customer_id'] ?? ''));
$customerName = trim((string) ($followupRow['customer_name'] ?? ''));
$campaignName = trim((string) ($followupRow['campaign_name'] ?? ''));
$followupDate = trim((string) ($followupRow['follow_up_date'] ?? ''));
$currentStatus = trim((string) ($followupRow['follow_up_status'] ?? ''));

if ($currentStatus !== 'Pending') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => '该Follow-up task已处理']);
    exit();
}

// 处理attachment上传
$attachmentPath = '';
if (isset($_FILES['attachment'])) {
    $attachmentPath = campaign2UploadAttachment('attachment', $campaignName, $followupDate, $customerName);
    if ($attachmentPath === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Attachment upload failed']);
        exit();
    }
}

if ($attachmentPath === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必须上传attachment']);
    exit();
}

// 开始事务
$connect->begin_transaction();

try {
    // 更新follow-up状态为Completed
    $completedBy = (string) USER_ID;
    $stmt = $connect->prepare("UPDATE `" . CAMPAIGN2_FOLLOW_UP . "` SET `follow_up_status`='Completed', `attachment_path`=?, `remark`=?, `completed_by`=?, `completed_date`=CURDATE(), `completed_time`=CURTIME(), `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=?");
    if (!$stmt) {
        throw new Exception('Database error');
    }

    $updateBy = (string) USER_ID;
    $stmt->bind_param('ssssi', $attachmentPath, $remark, $completedBy, $updateBy, $followupId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update follow-up: ' . $stmt->error);
    }
    $stmt->close();

    // 打Notify tag
    if ($platform !== '' && $platformCustomerId !== '') {
        $tagSuccess = campaign2EnsureAndAssignTag($connect, $platform, (int) $platformCustomerId, $campaignName, $followupDate, 'Notify');
        if (!$tagSuccess) {
            throw new Exception('Failed to assign Notify tag');
        }
    }

    // 写入USER_RECORD_LOG
    if ($platform !== '') {
        $messageHtml = 'Follow-up task completed<br>Remark: ' . (!empty($remark) ? htmlspecialchars($remark) : 'N/A');
        customerTagWriteUserRecordLog($connect, $platform, (int) $platformCustomerId, 'Campaign2 Follow-up Completed', $messageHtml);
    }

    // 审计日志
    campaign2Audit($connect, 'Campaign2', 'complete_followup', USER_NAME . ' completed follow-up ID=' . $followupId . ' with attachment=' . $attachmentPath, 'UPDATE ' . CAMPAIGN2_FOLLOW_UP);

    $connect->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Follow-up completed successfully',
        'attachment_path' => $attachmentPath
    ]);

} catch (Exception $e) {
    $connect->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
