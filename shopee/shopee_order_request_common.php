<?php
$currentPagePin = 0;
include_once '../../menuHeader.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) post('id') > 0) {
    $orderId = (int) post('id');

    // Update the order status
    $updateSql = "UPDATE " . SHOPEE_SG_ORDER_REQ . " SET order_status = 'C' WHERE id = $orderId";
    $updateResult = mysqli_query($finance_connect, $updateSql);

    if ($updateResult) {
        // Verify the update
        $checkSql = "SELECT order_status FROM " . SHOPEE_SG_ORDER_REQ . " WHERE id = $orderId AND order_status = 'C'";
        $checkResult = mysqli_query($finance_connect, $checkSql);

        if ($row = mysqli_fetch_assoc($checkResult)) {
            if ($row['order_status'] === 'C') {
                echo "success";
            } else {
                echo "status_mismatch";
            }
        } else {
            echo "status_mismatch";
        }
    } else {
        echo "error";
    }
}
?>
