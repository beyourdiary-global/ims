<?php
$pageTitle = "Campaign2 Report";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign2_common.php';
include_once ROOT . '/include/customer_tag.php';

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$campaignId = (int) input('campaign_id');
if ($campaignId <= 0) {
    $campaignId = (int) post('campaign_id');
}

$campaign = campaign2FetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    echo '<script>location.href = "' . $SITEURL . '/campaign2/campaign2_list.php";</script>';
    exit();
}

$pageUrl = $SITEURL . '/campaign2/campaign2_report.php?campaign_id=' . (int) $campaignId;
$backUrl = $SITEURL . '/campaign2/campaign2_form.php?id=' . (int) $campaignId . '&act=V';

function campaign2ReportBuildData($connect, $campaignId) {
    $data = array(
        'metrics' => array(
            'total_customers' => 0,
            'total_followups' => 0,
            'completed_followups' => 0,
            'failed_followups' => 0,
            'completion_rate' => 0,
        ),
        'customer_details' => array(),
        'tag_summary' => array(),
    );

    // 获取客户统计
    $customerStmt = $connect->prepare("SELECT COUNT(*) AS cnt FROM `" . CAMPAIGN2_CUSTOMER . "` WHERE `campaign_id`=? AND `status`='A'");
    if ($customerStmt) {
        $customerStmt->bind_param('i', $campaignId);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        if ($customerResult && $customerResult->num_rows > 0) {
            $row = $customerResult->fetch_assoc();
            $data['metrics']['total_customers'] = (int) ($row['cnt'] ?? 0);
        }
        $customerStmt->close();
    }

    // 获取follow-up统计
    $followupStmt = $connect->prepare("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN `follow_up_status`='Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN `follow_up_status`='Failed' THEN 1 ELSE 0 END) AS failed
        FROM `" . CAMPAIGN2_FOLLOW_UP . "` WHERE `campaign_id`=? AND `status`='A'");
    if ($followupStmt) {
        $followupStmt->bind_param('i', $campaignId);
        $followupStmt->execute();
        $followupResult = $followupStmt->get_result();
        if ($followupResult && $followupResult->num_rows > 0) {
            $row = $followupResult->fetch_assoc();
            $data['metrics']['total_followups'] = (int) ($row['total'] ?? 0);
            $data['metrics']['completed_followups'] = (int) ($row['completed'] ?? 0);
            $data['metrics']['failed_followups'] = (int) ($row['failed'] ?? 0);

            if ($data['metrics']['total_followups'] > 0) {
                $data['metrics']['completion_rate'] = round(($data['metrics']['completed_followups'] / $data['metrics']['total_followups']) * 100, 2);
            }
        }
        $followupStmt->close();
    }

    // 获取客户详情和tag
    $customerDetailStmt = $connect->prepare("SELECT `id`, `platform`, `platform_customer_id`, `customer_name`, `customer_contact` FROM `" . CAMPAIGN2_CUSTOMER . "` WHERE `campaign_id`=? AND `status`='A' ORDER BY `assign_date` DESC");
    if ($customerDetailStmt) {
        $customerDetailStmt->bind_param('i', $campaignId);
        $customerDetailStmt->execute();
        $customerDetailResult = $customerDetailStmt->get_result();

        while ($customerRow = $customerDetailResult->fetch_assoc()) {
            $platform = strtolower(trim((string) $customerRow['platform']));
            $platformCustomerId = (int) $customerRow['platform_customer_id'];

            // 获取该客户的follow-up统计
            $customerFollowupStmt = $connect->prepare("SELECT COUNT(*) AS cnt, SUM(CASE WHEN `follow_up_status`='Completed' THEN 1 ELSE 0 END) AS completed FROM `" . CAMPAIGN2_FOLLOW_UP . "` WHERE `campaign2_customer_id`=? AND `status`='A'");
            if ($customerFollowupStmt) {
                $customerId = (int) $customerRow['id'];
                $customerFollowupStmt->bind_param('i', $customerId);
                $customerFollowupStmt->execute();
                $customerFollowupResult = $customerFollowupStmt->get_result();
                $followupCounts = array('total' => 0, 'completed' => 0);
                if ($customerFollowupResult && $customerFollowupResult->num_rows > 0) {
                    $row = $customerFollowupResult->fetch_assoc();
                    $followupCounts['total'] = (int) ($row['cnt'] ?? 0);
                    $followupCounts['completed'] = (int) ($row['completed'] ?? 0);
                }
                $customerFollowupStmt->close();
            }

            // 获取tags
            $tags = customerTagGetCustomerTagMap($connect, $platform, array($platformCustomerId))[$platformCustomerId] ?? array();

            $data['customer_details'][] = array(
                'name' => $customerRow['customer_name'],
                'contact' => $customerRow['customer_contact'],
                'platform' => $customerRow['platform'],
                'followup_total' => $followupCounts['total'],
                'followup_completed' => $followupCounts['completed'],
                'tags' => $tags,
            );
        }
    }

    return $data;
}

$reportData = campaign2ReportBuildData($connect, $campaignId);
$metrics = $reportData['metrics'];

// 处理CSV导出
if (input('export') === '1') {
    $filename = 'campaign2_report_' . (int) $campaignId . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    fputcsv($output, array('Campaign2 Report'));
    fputcsv($output, array('Campaign', $campaign['campaign_name'] ?? ''));
    fputcsv($output, array('Period', ($campaign['period_start_date'] ?? '') . ' to ' . ($campaign['period_end_date'] ?? '')));
    fputcsv($output, array());
    fputcsv($output, array('Metric', 'Value'));
    fputcsv($output, array('Total Customers', $metrics['total_customers']));
    fputcsv($output, array('Total Follow-ups', $metrics['total_followups']));
    fputcsv($output, array('Completed Follow-ups', $metrics['completed_followups']));
    fputcsv($output, array('Failed Follow-ups', $metrics['failed_followups']));
    fputcsv($output, array('Completion Rate', $metrics['completion_rate'] . '%'));
    fputcsv($output, array());
    fputcsv($output, array('Customer', 'Contact', 'Platform', 'Follow-ups', 'Completed'));

    foreach ($reportData['customer_details'] as $customer) {
        fputcsv($output, array(
            $customer['name'],
            $customer['contact'],
            $customer['platform'],
            $customer['followup_total'],
            $customer['followup_completed']
        ));
    }

    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    <div class="page-load-cover">
        <div class="container-fluid px-4">
            <div class="row mt-3">
                <div class="col-12">
                    <p>
                        <a href="<?= $SITEURL ?>/campaign2/campaign2_list.php">Campaign2</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        Report
                    </p>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                <div>
                    <h1>Campaign2 Report</h1>
                    <div class="badge bg-secondary mt-2">
                        <?= campaign2H($campaign['campaign_name']) ?> |
                        <?= campaign2H($campaign['period_start_date']) ?> - <?= campaign2H($campaign['period_end_date']) ?>
                    </div>
                </div>
                <a href="<?= campaign2H($pageUrl . '&export=1') ?>" class="btn btn-primary">
                    <i class="fa-solid fa-download"></i> Export CSV
                </a>
            </div>

            <!-- 指标卡片 -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Total Customers</div>
                            <h4><?= (int) $metrics['total_customers'] ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Total Follow-ups</div>
                            <h4><?= (int) $metrics['total_followups'] ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Completion Rate</div>
                            <h4><?= (float) $metrics['completion_rate'] ?>%</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Completed Follow-ups</div>
                            <h4><span class="badge bg-success"><?= (int) $metrics['completed_followups'] ?></span></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Failed Follow-ups</div>
                            <h4><span class="badge bg-danger"><?= (int) $metrics['failed_followups'] ?></span></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="text-muted small">Pending Follow-ups</div>
                            <h4><?= ((int) $metrics['total_followups'] - (int) $metrics['completed_followups'] - (int) $metrics['failed_followups']) ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 客户详情表 -->
            <div class="card">
                <div class="card-header bg-white"><strong>Customer Details</strong></div>
                <div class="card-body table-responsive">
                    <table class="table table-striped w-100">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Contact</th>
                                <th>Platform</th>
                                <th>Follow-ups</th>
                                <th>Completed</th>
                                <th>Tags</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['customer_details'] as $customer): ?>
                                <tr>
                                    <td><?= campaign2H($customer['name']) ?></td>
                                    <td><?= campaign2H($customer['contact']) ?></td>
                                    <td><?= campaign2H($customer['platform']) ?></td>
                                    <td><?= (int) $customer['followup_total'] ?></td>
                                    <td><?= (int) $customer['followup_completed'] ?></td>
                                    <td>
                                        <?php if (!empty($customer['tags'])): ?>
                                            <?= customerTagRenderBadges($customer['tags'], '', 'badge bg-info me-1') ?>
                                        <?php else: ?>
                                            <span class="text-muted">无</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4 text-center">
                <a href="<?= campaign2H($backUrl) ?>" class="btn btn-secondary">Back to Campaign</a>
            </div>
        </div>
    </div>

    <script>
        checkCurrentPage('<?= campaign2H($pageTitle) ?>', 'View');
        setButtonColor();
    </script>
</body>
</html>
