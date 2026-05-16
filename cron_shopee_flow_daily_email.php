<?php
// Expected cPanel cron:
// 0 8 * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron_shopee_flow_daily_email.php

include_once 'init.php';
include_once ROOT . '/include/common.php';

if (!function_exists('shopeeOmsSendMail')) {
    function shopeeOmsSendMail($toEmail, $subject, $message, $fromEmail = '')
    {
        $toEmail = trim((string) $toEmail);
        $subject = (string) $subject;
        $message = (string) $message;

        $fromEmail = trim((string) $fromEmail);
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $host = (string) parse_url(SITEURL, PHP_URL_HOST);
        $baseHost = preg_replace('/^www\./i', '', $host);

        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/plain; charset=utf-8';
        $headers[] = 'From: BeYourDiary <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'Return-Path: ' . $fromEmail;
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'Message-ID: <' . md5(uniqid((string) mt_rand(), true)) . '@' . ($baseHost !== '' ? $baseHost : 'beyourdiary.com') . '>';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headerStr = implode("\r\n", $headers);
        return @mail($toEmail, $subject, $message, $headerStr, '-f' . $fromEmail);
    }
}

if (date('H') !== '08') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Shopee OMS daily email report skipped.\n";
    echo "Current server time: " . date('Y-m-d H:i:s') . "\n";
    echo "This script only sends during the 08:00 AM hour.\n";
    exit;
}

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

$systemMailFrom = '';
$projectSettings = getData('*', "id = '1'", '', PROJ, $connect);
if ($projectSettings != false) {
    $projectRow = $projectSettings->fetch_assoc();
    if (isset($projectRow['company_email']) && filter_var((string) $projectRow['company_email'], FILTER_VALIDATE_EMAIL)) {
        $systemMailFrom = (string) $projectRow['company_email'];
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
        if (shopeeOmsSendMail($recipientEmail, $subject, $message, $systemMailFrom)) {
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
