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

$periodEnded = campaign2IsPeriodEnded($campaign);

// 获取已分配的客户列表
$stmt = $connect->prepare("SELECT `id`, `platform`, `platform_customer_id`, `customer_name`, `customer_contact`, `assign_date` FROM `" . CAMPAIGN2_CUSTOMER . "` WHERE `campaign_id`=? AND `status`='A' ORDER BY `assign_date` DESC");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param('i', $campaignId);
$stmt->execute();
$result = $stmt->get_result();
$customers = array();
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
$stmt->close();

// 获取tag信息
$platformCustomerIds = array();
foreach ($customers as $customer) {
    $platform = strtolower(trim((string) $customer['platform']));
    $customerId = (int) $customer['platform_customer_id'];
    if ($platform !== '' && $customerId > 0) {
        if (!isset($platformCustomerIds[$platform])) {
            $platformCustomerIds[$platform] = array();
        }
        $platformCustomerIds[$platform][] = $customerId;
    }
}

$tagMaps = array();
foreach ($platformCustomerIds as $platform => $customerIds) {
    $tagMap = customerTagGetCustomerTagMap($connect, $platform, $customerIds);
    $tagMaps[$platform] = $tagMap ?? array();
}

// 构建HTML
$html = '';
if (empty($customers)) {
    $html .= '<p class="text-muted">尚未分配任何客户</p>';
} else {
    $html .= '<table class="table table-striped">';
    $html .= '<thead><tr><th>Customer</th><th>Platform</th><th>Contact</th><th>Tags</th><th>Actions</th></tr></thead>';
    $html .= '<tbody>';

    foreach ($customers as $customer) {
        $platform = strtolower(trim((string) $customer['platform']));
        $customerId = (int) $customer['platform_customer_id'];
        $tags = isset($tagMaps[$platform][$customerId]) ? $tagMaps[$platform][$customerId] : array();

        $html .= '<tr>';
        $html .= '<td>' . campaign2H($customer['customer_name']) . '</td>';
        $html .= '<td>' . campaign2H($customer['platform']) . '</td>';
        $html .= '<td>' . campaign2H($customer['customer_contact']) . '</td>';
        $html .= '<td>';
        if (!empty($tags)) {
            $html .= customerTagRenderBadges($tags, '', 'badge bg-info me-1');
        } else {
            $html .= '<span class="text-muted">无</span>';
        }
        $html .= '</td>';
        $html .= '<td>';
        if (!$periodEnded) {
            $html .= '<button class="btn btn-sm btn-danger" onclick="removeCustomer(' . (int) $customer['id'] . ')">Unassign</button>';
        }
        $html .= '<button class="btn btn-sm btn-info ms-2" onclick="viewFollowups(' . (int) $customer['id'] . ')">Follow-ups</button>';
        $html .= '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody>';
    $html .= '</table>';
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'count' => count($customers)
]);
