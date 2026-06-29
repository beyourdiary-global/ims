<?php
// Expected cron:
// */15 * * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron/cron_lucky_draw_release.php

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$released = luckyDrawReleaseExpiredReservations($connect, 200);
echo "Lucky Draw reservation release summary\n";
echo "Generated at: " . date('Y-m-d H:i:s') . "\n";
echo "Physical released: " . (int) ($released['physical'] ?? 0) . "\n";
echo "Voucher released: " . (int) ($released['voucher'] ?? 0) . "\n";
echo "Email locks reset: " . (int) ($released['stale_email_locks'] ?? 0) . "\n";
