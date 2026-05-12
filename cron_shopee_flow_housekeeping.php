<?php
include_once 'init.php';
include_once ROOT . '/include/common.php';

$movedCount = shopeeOmsRunFourteenDayAutoMove($connect, $finance_connect);

header('Content-Type: text/plain; charset=utf-8');
echo "Shopee OMS housekeeping completed.\n";
echo "Parcel Received -> WAFC moved: " . (int) $movedCount . "\n";
