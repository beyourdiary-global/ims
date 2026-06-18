<?php
// Expected cron:
// 15 1 * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron/cron_customer_follow_up_lost.php
// TODO: Add a secure cron-key wrapper if this project standardizes protected cron entry points.

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/customer_follow_up_common.php';

$summary = customerFollowUpProcessLostCases($connect, $finance_connect, date('Y-m-d'));

header('Content-Type: text/plain; charset=utf-8');
echo "Customer follow-up lost customer cron completed.\n";
echo "Checked count: " . (int) (isset($summary['checked_count']) ? $summary['checked_count'] : 0) . "\n";
echo "Lost tagged count: " . (int) (isset($summary['lost_tagged_count']) ? $summary['lost_tagged_count'] : 0) . "\n";
echo "Skipped repurchased count: " . (int) (isset($summary['skipped_repurchased_count']) ? $summary['skipped_repurchased_count'] : 0) . "\n";
echo "Skipped duplicate count: " . (int) (isset($summary['skipped_duplicate_count']) ? $summary['skipped_duplicate_count'] : 0) . "\n";
