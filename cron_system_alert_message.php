<?php
// Expected cron:
// 0 7 * * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron_system_alert_message.php
// TODO: Add a secure cron-key wrapper if this project standardizes protected cron entry points.

include_once 'init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/system_alert_common.php';

$generatedCount = 0;
$userCount = 0;
$generatedCount += systemAlertGenerateDailyFlowSupervisorAlerts($connect, $finance_connect, date('Y-m-d'));
$sql = "SELECT `id` FROM `" . USR_USER . "` WHERE `status` = 'A' ORDER BY `id` ASC";
$result = mysqli_query($connect, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $userId = isset($row['id']) ? (int) $row['id'] : 0;
        if ($userId <= 0) {
            continue;
        }

        $generatedCount += systemAlertGenerateForUser($connect, $finance_connect, $userId);
        $userCount++;
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "System alert generation completed.\n";
echo "Users checked: " . (int) $userCount . "\n";
echo "Alerts generated or synced: " . (int) $generatedCount . "\n";
