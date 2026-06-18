<?php
// Expected cron:
// 0 8 * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron/cron_customer_follow_up_due.php
// TODO: Add a secure cron-key wrapper if this project standardizes protected cron entry points.

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/customer_follow_up_common.php';

$summary = customerFollowUpProcessDueNotifications($connect, $finance_connect, date('Y-m-d'));

header('Content-Type: text/plain; charset=utf-8');
echo "Customer follow-up due notification cron completed.\n";
echo "Due count: " . (int) (isset($summary['due_count']) ? $summary['due_count'] : 0) . "\n";
echo "Notification count: " . (int) (isset($summary['notification_count']) ? $summary['notification_count'] : 0) . "\n";
echo "Skipped duplicate count: " . (int) (isset($summary['skipped_duplicate_count']) ? $summary['skipped_duplicate_count'] : 0) . "\n";
