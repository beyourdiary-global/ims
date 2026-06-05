<?php
// Expected cron:
// 5 0 * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron_customer_follow_up_missed.php
// TODO: Add a secure cron-key wrapper if this project standardizes protected cron entry points.

include_once 'init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/customer_follow_up_common.php';

$summary = customerFollowUpProcessMissedRounds($connect, $finance_connect, date('Y-m-d'));

header('Content-Type: text/plain; charset=utf-8');
echo "Customer follow-up missed cron completed.\n";
echo "Processed count: " . (int) (isset($summary['processed_count']) ? $summary['processed_count'] : 0) . "\n";
echo "Skipped count: " . (int) (isset($summary['skipped_count']) ? $summary['skipped_count'] : 0) . "\n";
echo "Notification count: " . (int) (isset($summary['notification_count']) ? $summary['notification_count'] : 0) . "\n";
