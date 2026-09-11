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
if ($campaignId <= 0) {
    echo json_encode(['success' => false, 'message' => '缺少campaign_id参数']);
    exit();
}

$campaign = campaign2FetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    echo json_encode(['success' => false, 'message' => 'Campaign不存在']);
    exit();
}

// 获取follow-up tasks
$stmt = $connect->prepare("SELECT
    f.`id`, f.`campaign2_customer_id`, f.`follow_up_date`, f.`follow_up_status`,
    f.`message_title`, f.`attachment_path`, f.`remark`, f.`completed_date`,
    c.`customer_name`, c.`platform`
    FROM `" . CAMPAIGN2_FOLLOW_UP . "` f
    LEFT JOIN `" . CAMPAIGN2_CUSTOMER . "` c ON c.`id`=f.`campaign2_customer_id`
    WHERE f.`campaign_id`=? AND f.`status`='A'
    ORDER BY f.`follow_up_date` ASC, f.`id` ASC");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param('i', $campaignId);
$stmt->execute();
$result = $stmt->get_result();
$followups = array();
while ($row = $result->fetch_assoc()) {
    $followups[] = $row;
}
$stmt->close();

// 构建HTML
$html = '';
if (empty($followups)) {
    $html .= '<p class="text-muted">尚无Follow-up Tasks</p>';
} else {
    $today = date('Y-m-d');
    $html .= '<table class="table table-striped">';
    $html .= '<thead><tr><th>Customer</th><th>Date</th><th>Status</th><th>Message</th><th>Actions</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($followups as $followup) {
        $followupDate = trim((string) $followup['follow_up_date']);
        $status = trim((string) $followup['follow_up_status']);
        $isOverdue = $status === 'Pending' && $followupDate < $today;

        $html .= '<tr>';
        $html .= '<td>' . campaign2H($followup['customer_name']) . '</td>';
        $html .= '<td>' . campaign2H($followupDate) . '</td>';
        $html .= '<td>';
        if ($status === 'Completed') {
            $html .= '<span class="badge bg-success">Completed</span>';
        } elseif ($status === 'Failed') {
            $html .= '<span class="badge bg-danger">Failed</span>';
        } elseif ($isOverdue) {
            $html .= '<span class="badge bg-warning">Overdue</span>';
        } else {
            $html .= '<span class="badge bg-secondary">Pending</span>';
        }
        $html .= '</td>';
        $html .= '<td>' . campaign2H($followup['message_title'] ?? '') . '</td>';
        $html .= '<td>';
        if ($status === 'Pending') {
            $html .= '<button class="btn btn-sm btn-primary" onclick="completeFollowup(' . (int) $followup['id'] . ')">Complete</button>';
        } else {
            $html .= '<button class="btn btn-sm btn-secondary" onclick="viewFollowupDetail(' . (int) $followup['id'] . ')">View</button>';
        }
        $html .= '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody>';
    $html .= '</table>';
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'count' => count($followups)
]);
