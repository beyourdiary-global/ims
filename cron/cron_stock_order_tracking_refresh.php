<?php
// Example cron:
// 0 */12 * * * /usr/bin/php /path/to/ims/cron/cron_stock_order_tracking_refresh.php

include dirname(__DIR__) . '/init.php';
include ROOT . '/include/common.php';

$query = "SELECT id FROM " . STOCK_ORDER_REQ . " WHERE status='A' AND tracking_no IS NOT NULL AND tracking_no <> '' ORDER BY id DESC";
$result = mysqli_query($finance_connect, $query);

if (!$result) {
    echo "Failed to fetch requests.\n";
    exit(1);
}

$okCount = 0;
$failCount = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $message = '';
    $ok = sorRefreshTrackingStatus($finance_connect, (int) $row['id'], $message, $connect);
    if ($ok) {
        $okCount++;
        echo "[OK] #" . (int) $row['id'] . " - $message\n";
    } else {
        $failCount++;
        echo "[FAIL] #" . (int) $row['id'] . " - $message\n";
    }
}

echo "Completed. Success: $okCount, Failed: $failCount\n";
