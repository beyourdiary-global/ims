<?php
include_once dirname(__DIR__) . '/include/connection.php';

$requestId = (int) (!empty(input('id')) ? input('id') : post('id'));
$redirectUrl = rtrim((string) $SITEURL, '/') . '/finance/website_order_request_table.php';
if ($requestId > 0) {
    $redirectUrl = rtrim((string) $SITEURL, '/') . '/finance/website_order_request.php?id=' . $requestId;
}

header('Location: ' . $redirectUrl);
exit;
