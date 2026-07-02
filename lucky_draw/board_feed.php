<?php
include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';

$rows = luckyDrawBoardFeedRows($connect, 12);
$payloadRows = array();
foreach ($rows as $row) {
    $payloadRows[] = array(
        'name' => isset($row['display_name']) ? (string) $row['display_name'] : 'Lucky Member',
        'prize' => isset($row['display_prize']) ? (string) $row['display_prize'] : 'Prize',
        'source' => isset($row['source']) ? (string) $row['source'] : 'real',
    );
}

luckyDrawJsonResponse(array(
    'success' => true,
    'rows' => $payloadRows,
));
