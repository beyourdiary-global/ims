<?php
include_once dirname(__DIR__) . '/include/connection.php';

$requestId = (int) (!empty(input('id')) ? input('id') : post('id'));
$redirectUrl = rtrim((string) $SITEURL, '/') . '/finance/fb_order_req_table.php';
if ($requestId > 0) {
    $redirectUrl = rtrim((string) $SITEURL, '/') . '/finance/fb_order_req.php?id=' . $requestId;
}

header('Location: ' . $redirectUrl);
exit;
