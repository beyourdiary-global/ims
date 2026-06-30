<?php
// Expected cron:
// */10 * * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron/cron_lucky_draw_voucher_email.php

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$processed = luckyDrawSendVoucherQueueBatch($connect, $finance_connect, 25, 15);
echo "Lucky Draw voucher email queue summary\n";
echo "Generated at: " . date('Y-m-d H:i:s') . "\n";
echo "Sent: " . (int) ($processed['sent'] ?? 0) . "\n";
echo "Failed: " . (int) ($processed['failed'] ?? 0) . "\n";
echo "Skipped: " . (int) ($processed['skipped'] ?? 0) . "\n";
