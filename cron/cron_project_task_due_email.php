<?php
// Expected cron:
// 30 8 * * * IMS_CRON_HTTP_HOST=cms.beyourdiary.com /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron/cron_project_task_due_email.php

if (PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST'])) {
    $cliHost = '';
    if (!empty($argv) && is_array($argv)) {
        foreach ($argv as $arg) {
            $arg = trim((string) $arg);
            if (strpos($arg, '--http-host=') === 0) {
                $cliHost = substr($arg, strlen('--http-host='));
                break;
            }
        }
    }

    if ($cliHost === '') {
        $cliHost = (string) getenv('IMS_CRON_HTTP_HOST');
    }

    if ($cliHost !== '') {
        $_SERVER['HTTP_HOST'] = $cliHost;
    }
}

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/system_alert_common.php';
include_once ROOT . '/task/common_task.php';

if (!function_exists('taskCronReadCliOption')) {
    function taskCronReadCliOption($name)
    {
        if (PHP_SAPI !== 'cli') {
            return '';
        }

        global $argv;
        $prefixes = array(
            '--' . $name . '=',
            $name . '=',
        );

        foreach ((array) $argv as $arg) {
            $arg = trim((string) $arg);
            foreach ($prefixes as $prefix) {
                if (strpos($arg, $prefix) === 0) {
                    return trim((string) substr($arg, strlen($prefix)));
                }
            }
        }

        return '';
    }
}

if (!function_exists('taskCronFlagEnabled')) {
    function taskCronFlagEnabled($value)
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, array('1', 'true', 'yes', 'y', 'manual'), true);
    }
}

if (!function_exists('taskCronManualTriggerRequested')) {
    function taskCronManualTriggerRequested()
    {
        if (input('manual_trigger') !== '' && taskCronFlagEnabled(input('manual_trigger'))) {
            return true;
        }

        if (post('manual_trigger') !== '' && taskCronFlagEnabled(post('manual_trigger'))) {
            return true;
        }

        if (PHP_SAPI === 'cli') {
            global $argv;

            foreach ((array) $argv as $arg) {
                $arg = trim((string) $arg);
                if ($arg === '--manual' || $arg === 'manual_trigger=1' || $arg === '--manual=1') {
                    return true;
                }

                if (strpos($arg, 'manual_trigger=') === 0) {
                    $value = substr($arg, strlen('manual_trigger='));
                    if (taskCronFlagEnabled($value)) {
                        return true;
                    }
                }

                if (strpos($arg, '--manual=') === 0) {
                    $value = substr($arg, strlen('--manual='));
                    if (taskCronFlagEnabled($value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

if (!function_exists('taskCronResolveReferenceDate')) {
    function taskCronResolveReferenceDate()
    {
        $candidates = array(
            input('reference_date'),
            input('date'),
            post('reference_date'),
            post('date'),
            taskCronReadCliOption('reference_date'),
            taskCronReadCliOption('date'),
        );

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
                return $candidate;
            }
        }

        return date('Y-m-d');
    }
}

if (!function_exists('taskCronSendMail')) {
    function taskCronSendMail($toEmail, $subject, $message, $fromEmail = '')
    {
        global $connect;

        return commonSendSystemEmail($connect, $toEmail, $subject, $message, array(
            'from_email' => $fromEmail,
            'auto_submitted' => true,
        ));
    }
}

$referenceDate = taskCronResolveReferenceDate();
$isScheduledHour = date('H') === '08';
$isManualTrigger = !$isScheduledHour && taskCronManualTriggerRequested();
$manualTriggerLimit = 3;
$manualTriggerCount = 0;
$manualTriggerSettingKey = 'project_task_due_email_manual_trigger_count_' . str_replace('-', '', $referenceDate);

if (!$isScheduledHour && !$isManualTrigger) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Project task due email cron skipped.\n";
    echo "Current server time: " . date('Y-m-d H:i:s') . "\n";
    echo "Reference date: " . $referenceDate . "\n";
    echo "This script only sends during the 08:00 AM hour.\n";
    echo "Manual fallback: add ?manual_trigger=1 or run with --manual (max 3 times per reference date).\n";
    exit;
}

$runMode = 'scheduled';
if ($isManualTrigger) {
    $runMode = 'manual';
    $manualTriggerCount = (int) shopeeOmsGetSetting($connect, $manualTriggerSettingKey, '0');

    if ($manualTriggerCount >= $manualTriggerLimit) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Project task due email manual trigger skipped.\n";
        echo "Current server time: " . date('Y-m-d H:i:s') . "\n";
        echo "Reference date: " . $referenceDate . "\n";
        echo "Manual trigger limit reached: " . $manualTriggerLimit . "\n";
        exit;
    }
}

$digest = taskBuildDueDigestEmailJobs($connect, $referenceDate);
$jobs = isset($digest['jobs']) && is_array($digest['jobs']) ? $digest['jobs'] : array();

$systemMailFrom = '';
$projectSettings = getData('*', "id = '1'", '', PROJ, $connect);
if ($projectSettings != false) {
    $projectRow = $projectSettings->fetch_assoc();
    if (isset($projectRow['company_email']) && filter_var((string) $projectRow['company_email'], FILTER_VALIDATE_EMAIL)) {
        $systemMailFrom = (string) $projectRow['company_email'];
    }
}

$emailCount = 0;
foreach ($jobs as $job) {
    $recipientEmail = trim((string) (isset($job['assignee_email']) ? $job['assignee_email'] : ''));
    $subject = isset($job['subject']) ? (string) $job['subject'] : 'Project Task Due Reminder';
    $message = taskBuildDueDigestEmailHtml($job);
    if ($recipientEmail === '' || $message === '') {
        continue;
    }

    if (taskCronSendMail($recipientEmail, $subject, $message, $systemMailFrom)) {
        $emailCount++;
    }
}

if ($runMode === 'manual') {
    $manualTriggerCount++;
    shopeeOmsSetSetting(
        $connect,
        $manualTriggerSettingKey,
        (string) $manualTriggerCount,
        'Project task due email manual trigger count for reference date ' . $referenceDate
    );
}

header('Content-Type: text/plain; charset=utf-8');
echo "Project task due email cron completed.\n";
echo "Run mode: " . $runMode . "\n";
echo "Reference date: " . (string) (isset($digest['reference_date']) ? $digest['reference_date'] : $referenceDate) . "\n";
if ($runMode === 'manual') {
    echo "Manual trigger used: " . $manualTriggerCount . "/" . $manualTriggerLimit . "\n";
}
echo "HTTP host: " . (isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '') . "\n";
echo "Site URL: " . (defined('SITEURL') ? (string) SITEURL : '') . "\n";
echo "Matched items: " . (int) (isset($digest['matched_item_count']) ? $digest['matched_item_count'] : 0) . "\n";
echo "Eligible items: " . (int) (isset($digest['eligible_item_count']) ? $digest['eligible_item_count'] : 0) . "\n";
echo "Skipped items: " . (int) (isset($digest['skipped_item_count']) ? $digest['skipped_item_count'] : 0) . "\n";
echo "Email jobs: " . count($jobs) . "\n";
echo "Emails sent: " . $emailCount . "\n";
