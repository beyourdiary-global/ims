<?php
// Shared OMS housekeeping cron.
include_once 'init.php';
include_once ROOT . '/include/common.php';

$postponedCount = shopeeOmsRunOverduePostponedAutoMove($connect, $finance_connect);
$movedCount = shopeeOmsRunFourteenDayAutoMove($connect, $finance_connect);

header('Content-Type: text/plain; charset=utf-8');
echo "OMS housekeeping completed.\n";
echo "Waiting Receive -> Postponed moved: " . (int) $postponedCount . "\n";
echo "Parcel Received -> WAFC moved: " . (int) $movedCount . "\n";
