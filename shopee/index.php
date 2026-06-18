<?php
$currentPagePin = 0;
$pageTitle = 'Shopee Router';
include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

// 1. Check if they are a Superadmin (Pin 130)
if (is_array(checkPin($connect, 'Shopee All Orders'))) {
    echo "<script>location.replace('shopee_order_req_table.php');</script>";
    exit;
}

// 2. Check if they are an Admin (Pin 129)
if (is_array(checkPin($connect, 'Shopee Verify Order'))) {
    echo "<script>location.replace('shopee_verify.php');</script>";
    exit;
}

// 3. Check if they are a Basic User (Pin 128)
if (is_array(checkPin($connect, 'Shopee Processing Order'))) {
    echo "<script>location.replace('shopee_processing_order.php');</script>";
    exit;
}

// 4. Fallback if they have no access
renderNotificationScript('You do not have permission to view Shopee Orders.', 'error', '../dashboard.php', 1200, true);
exit;
?>
