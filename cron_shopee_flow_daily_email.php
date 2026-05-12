<?php
include_once 'init.php';
include_once ROOT . '/include/common.php';

shopeeOmsRunFourteenDayAutoMove($connect, $finance_connect);

$dateFrom = date('Y-m-d', strtotime('-1 day'));
$dateTo = $dateFrom;
$reportData = shopeeOmsGetDailyFlowReport($finance_connect, $dateFrom, $dateTo);
$summaryRows = isset($reportData['summary']) ? $reportData['summary'] : array();

$mainSupervisorUserId = (int) shopeeOmsGetSetting($connect, 'shopee_oms_daily_report_main_supervisor_user_id', '0');
$secondSupervisorUserId = (int) shopeeOmsGetSetting($connect, 'shopee_oms_daily_report_second_supervisor_user_id', '0');
$recipientUserIds = array();
if ($mainSupervisorUserId > 0) {
    $recipientUserIds[$mainSupervisorUserId] = $mainSupervisorUserId;
}
if ($secondSupervisorUserId > 0) {
    $recipientUserIds[$secondSupervisorUserId] = $secondSupervisorUserId;
}

$recipientEmails = array();
if (!empty($recipientUserIds)) {
    $sql = "SELECT id, email FROM `" . USR_USER . "` WHERE id IN (" . implode(',', $recipientUserIds) . ") AND status = 'A'";
    $result = mysqli_query($connect, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $email = trim((string) (isset($row['email']) ? $row['email'] : ''));
            if ($email !== '' && isEmail($email)) {
                $recipientEmails[] = $email;
            }
        }
    }
}

$reportUrl = rtrim((string) SITEURL, '/') . '/shopee/shopee_flow_report.php?date_from=' . rawurlencode($dateFrom) . '&date_to=' . rawurlencode($dateTo);
$subject = 'Shopee OMS Daily Flow Report - ' . $dateFrom;
$summaryLines = array();
if (!empty($summaryRows)) {
    foreach ($summaryRows as $summaryRow) {
        $summaryLines[] = (string) $summaryRow['from_label'] . ' -> ' . (string) $summaryRow['to_label'] . ': ' . (int) $summaryRow['total_count'];
    }
} else {
    $summaryLines[] = 'No status transition recorded for the previous day.';
}

$message = "Shopee OMS Daily Flow Report\n";
$message .= "Report Date: " . $dateFrom . "\n\n";
$message .= "Transition Summary:\n";
$message .= implode("\n", $summaryLines) . "\n\n";
$message .= "Full Report: " . $reportUrl . "\n";

$sentCount = 0;
if (!empty($recipientEmails)) {
    foreach ($recipientEmails as $recipientEmail) {
        if (@mail($recipientEmail, $subject, $message)) {
            $sentCount++;
        }
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "Shopee OMS daily email report completed.\n";
echo "Report date: " . $dateFrom . "\n";
echo "Recipients configured: " . count($recipientEmails) . "\n";
echo "Emails sent: " . (int) $sentCount . "\n";
echo "Report URL: " . $reportUrl . "\n";
