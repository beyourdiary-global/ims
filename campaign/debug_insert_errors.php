<?php
include_once '../init.php';
include_once '../include/campaign_common.php';

$campaignId = 2;

$conn = new mysqli(dbhost, dbuser, dbpwd, dbname);
$conn->set_charset('utf8mb4');

$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
$financeConn->set_charset('utf8mb4');

$campaign = campaignFetchCampaign($conn, $campaignId);
$campaignPackageIds = campaignFetchCampaignPackageIds($conn, $campaignId);
$periodStart = $campaign['period_start_date'];
$periodEnd = $campaign['period_end_date'];

echo "<h2>INSERT Statement Error Debug</h2>";

// Get auto-discovered customers
$customers = campaignAutoDiscoverCustomersForPackages($conn, $financeConn, $campaign, $campaignPackageIds, $periodStart, $periodEnd);

echo "<h3>Testing INSERT for Each Customer:</h3>";

$insertStmt = $conn->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " (`campaign_id`,`campaign_customer_id`,`package_id`,`platform`,`order_id`,`order_no`,`order_detail`,`order_status`,`order_amount`,`order_date`,`package_text`,`customer_type`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),CURTIME(),'A')");

if (!$insertStmt) {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ Failed to prepare INSERT statement:</strong></p>";
    echo "<pre>" . htmlspecialchars($conn->error) . "</pre>";
    exit;
}

foreach ($customers as $customer) {
    echo "<h4>Customer: " . htmlspecialchars($customer['customer_name']) . " (ID: " . htmlspecialchars($customer['id']) . ")</h4>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";

    $fetchReason = '';
    $orders = campaignPurchaseFetchOrdersForCustomer($conn, $financeConn, $campaign, $customer, $periodStart, $periodEnd, $fetchReason);

    foreach ($orders as $order) {
        echo "\nAttempting INSERT for order: " . ($order['order_no'] ?? 'N/A') . "\n";

        $campaignCustomerId = (int)($customer['id'] ?? 0);
        $platform = (string)($order['platform'] ?? ($customer['platform'] ?? ''));
        $orderId = (string)($order['order_id'] ?? '');
        $orderNo = (string)($order['order_no'] ?? '');
        $orderDetail = campaignNormalizeTextValue($order['order_detail'] ?? '', 65535);
        $orderStatus = campaignNormalizeTextValue($order['order_status'] ?? '', 100);
        $orderAmount = (float)($order['order_amount'] ?? 0);
        $orderDate = trim((string)($order['order_date'] ?? ''));
        $packageText = campaignNormalizeTextValue($order['package_text'] ?? '', 65535);
        $packageId = isset($order['package_id']) && $order['package_id'] !== null ? (int)$order['package_id'] : null;
        $customerType = 'New Customer';
        $userId = campaignCurrentUserId();

        echo "  Values: campaign_id=$campaignId, campaign_customer_id=$campaignCustomerId, package_id=" . ($packageId ?? 'NULL') . "\n";
        echo "  platform=$platform, order_no=$orderNo, order_amount=$orderAmount\n";

        $packageIdForBind = is_null($packageId) ? 0 : (int)$packageId;

        echo "  Types: campaignId=" . gettype($campaignId) . ", customerId=" . gettype($campaignCustomerId) . ", packageId=" . gettype($packageIdForBind) . "\n";
        echo "  Types: platform=" . gettype($platform) . ", orderAmount=" . gettype($orderAmount) . ", userId=" . gettype($userId) . "\n";

        $bindResult = $insertStmt->bind_param('iiisssssdssss',
            $campaignId,
            $campaignCustomerId,
            $packageIdForBind,
            $platform,
            $orderId,
            $orderNo,
            $orderDetail,
            $orderStatus,
            $orderAmount,
            $orderDate,
            $packageText,
            $customerType,
            $userId
        );

        if (!$bindResult) {
            echo "  ✗ bind_param FAILED: " . htmlspecialchars($insertStmt->error) . "\n";
            continue;
        }

        if ($insertStmt->execute()) {
            $newId = $conn->insert_id;
            echo "  ✓ INSERT successful (ID: $newId)\n";
        } else {
            echo "  ✗ execute() FAILED: " . htmlspecialchars($insertStmt->error) . "\n";
        }
    }
    echo "</pre>";
}

$insertStmt->close();
$conn->close();
$financeConn->close();
?>
