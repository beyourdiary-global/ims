<?php
// Campaign2 Follow-up Daily Cron Job
// 自动标记逾期未完成的follow-up任务为Failed，并打上Fail tag

include_once 'init.php';
include_once ROOT . '/include/campaign2_common.php';
include_once ROOT . '/include/customer_tag.php';

// 防止直接浏览器访问
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && PHP_SAPI !== 'cli') {
    die('Access denied');
}

$output = array();
$output[] = date('Y-m-d H:i:s') . ' - Campaign2 Follow-up Cron Started';

// 获取所有Pending且follow_up_date已过的follow-up tasks
$today = date('Y-m-d');

$sql = "SELECT
    f.`id`, f.`campaign_id`, f.`campaign2_customer_id`, f.`follow_up_date`,
    c.`platform`, c.`platform_customer_id`,
    cmp.`campaign_name`
    FROM `" . CAMPAIGN2_FOLLOW_UP . "` f
    LEFT JOIN `" . CAMPAIGN2_CUSTOMER . "` c ON c.`id`=f.`campaign2_customer_id`
    LEFT JOIN `" . CAMPAIGN2 . "` cmp ON cmp.`id`=f.`campaign_id`
    WHERE f.`status`='A'
    AND f.`follow_up_status`='Pending'
    AND DATE(f.`follow_up_date`) < '" . $connect->real_escape_string($today) . "'
    AND (f.`attachment_path` IS NULL OR f.`attachment_path`='')";

$result = $connect->query($sql);
if (!$result) {
    $output[] = 'ERROR: Failed to query follow-ups: ' . $connect->error;
} else {
    $processedCount = 0;
    $failedCount = 0;

    while ($row = $result->fetch_assoc()) {
        $followupId = (int) ($row['id'] ?? 0);
        $campaignId = (int) ($row['campaign_id'] ?? 0);
        $customerId = (int) ($row['campaign2_customer_id'] ?? 0);
        $platform = trim((string) ($row['platform'] ?? ''));
        $platformCustomerId = trim((string) ($row['platform_customer_id'] ?? ''));
        $campaignName = trim((string) ($row['campaign_name'] ?? 'N/A'));
        $followupDate = trim((string) ($row['follow_up_date'] ?? ''));

        if ($followupId <= 0 || $campaignId <= 0 || $customerId <= 0) {
            $output[] = 'SKIP: Invalid follow-up record id=' . $followupId;
            continue;
        }

        // 更新follow-up状态为Failed
        $updateStmt = $connect->prepare("UPDATE `" . CAMPAIGN2_FOLLOW_UP . "` SET `follow_up_status`='Failed', `failed_date`=CURDATE(), `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=?");
        if (!$updateStmt) {
            $output[] = 'ERROR: Failed to prepare update statement for follow-up id=' . $followupId;
            $failedCount++;
            continue;
        }

        $systemUserId = 'system';
        $updateStmt->bind_param('si', $systemUserId, $followupId);
        if (!$updateStmt->execute()) {
            $output[] = 'ERROR: Failed to update follow-up id=' . $followupId . ': ' . $updateStmt->error;
            $updateStmt->close();
            $failedCount++;
            continue;
        }
        $updateStmt->close();

        // 打Fail tag
        if ($platform !== '' && $platformCustomerId !== '') {
            $tagSuccess = campaign2EnsureAndAssignTag($connect, $platform, (int) $platformCustomerId, $campaignName, $followupDate, 'Fail');
            if (!$tagSuccess) {
                $output[] = 'WARNING: Failed to assign Fail tag for platform=' . $platform . ' customer_id=' . $platformCustomerId;
            }
        }

        // 写入USER_RECORD_LOG
        if ($platform !== '') {
            $customerLogColumn = customerTagGetUserRecordLogCustomerColumn($platform);
            if ($customerLogColumn !== '') {
                $messageHtml = 'Follow-up task marked as failed (overdue, no attachment uploaded)';
                customerTagWriteUserRecordLog($connect, $platform, (int) $platformCustomerId, 'Campaign2 Follow-up Failed', $messageHtml);
            }
        }

        // 审计日志
        audit_log(array(
            'log_act' => 'auto_mark_failed',
            'cdate' => date_dis,
            'ctime' => time_dis,
            'uid' => 'system',
            'cby' => 'system',
            'act_msg' => 'Auto-marked follow-up id=' . $followupId . ' as Failed (overdue, no attachment)',
            'query_rec' => 'UPDATE ' . CAMPAIGN2_FOLLOW_UP . ' id=' . $followupId,
            'query_table' => CAMPAIGN2_FOLLOW_UP,
            'page' => 'Campaign2 Cron',
            'connect' => $connect,
        ));

        $processedCount++;
    }

    $output[] = 'SUMMARY: Processed ' . $processedCount . ' follow-ups, ' . $failedCount . ' errors';
}

$output[] = date('Y-m-d H:i:s') . ' - Campaign2 Follow-up Cron Completed';

if (PHP_SAPI === 'cli') {
    echo implode("\n", $output) . "\n";
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="background:#f0f0f0; padding:10px; font-family:monospace;">';
    foreach ($output as $line) {
        if (strpos($line, 'ERROR') !== false) {
            echo '<span style="color:red;">' . htmlspecialchars($line) . '</span>' . "\n";
        } elseif (strpos($line, 'WARNING') !== false) {
            echo '<span style="color:orange;">' . htmlspecialchars($line) . '</span>' . "\n";
        } else {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo '</pre>';
}
