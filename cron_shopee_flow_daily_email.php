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

        $host = (string) parse_url(SITEURL, PHP_URL_HOST);
        $baseHost = preg_replace('/^www\./i', '', $host);
        $fallbackSender = 'noreply@' . ($baseHost !== '' ? $baseHost : 'beyourdiary.com');

        $fromEmail = trim((string) $fromEmail);
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $fallbackSender;
        }

        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
        $headers[] = 'From: BeYourDiary <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'Return-Path: ' . $fromEmail;
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'Message-ID: <' . md5(uniqid((string) mt_rand(), true)) . '@' . ($baseHost !== '' ? $baseHost : 'beyourdiary.com') . '>';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headerStr = implode("\r\n", $headers);
        $sent = @mail($toEmail, $subject, $message, $headerStr);
        if (!$sent) {
            $sent = @mail($toEmail, $subject, $message, $headerStr, '-f' . $fromEmail);
        }

        return $sent;
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
$reportData = shopeeOmsGetDailyFlowReport($connect, $finance_connect, $dateFrom, $dateTo);
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
if (!empty($recipientEmails)) {
    $recipientEmails = array_values(array_unique($recipientEmails));
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

$message = '
    <html>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=3.0">
        <head>
            <title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
        </head>
        <body style="margin:0;background-color:#FFF0E3;font-family:sans-serif;">
            <div style="display:grid;gap:12px;min-width:350px;margin:20px auto;width:550px;">
                <table style="border-spacing:0;width:100%;background-color:#FFFFFF;border-radius:18px;">
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="font-size:22px;font-weight:bold;margin:0 0 18px;">Shopee OMS Daily Flow Report</p>
                            <p style="font-size:14px;margin:0 0 12px;">Report Date: <b>' . htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') . '</b></p>
                            <p style="font-size:14px;margin:0 0 12px;">Transition Summary:</p>
                            <div style="font-size:13px;line-height:1.6;margin:0 0 18px;">' . nl2br(htmlspecialchars(implode("\n", $summaryLines), ENT_QUOTES, 'UTF-8')) . '</div>
                            <p style="font-size:14px;margin:0;">
                                <a href="' . htmlspecialchars($reportUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#000000;">View Full Report</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </body>
    </html>
';

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
