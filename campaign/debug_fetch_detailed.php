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

echo "<h2>Detailed Order Fetch Debug</h2>";

// Get auto-discovered customers
$customers = campaignAutoDiscoverCustomersForPackages($conn, $financeConn, $campaign, $campaignPackageIds, $periodStart, $periodEnd);

echo "<h3>Processing Each Customer:</h3>";

foreach ($customers as $customer) {
    echo "<h4>Customer: " . htmlspecialchars($customer['customer_name']) . "</h4>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";

    $fetchReason = '';
    $orders = campaignPurchaseFetchOrdersForCustomer($conn, $financeConn, $campaign, $customer, $periodStart, $periodEnd, $fetchReason);

    echo "Fetch Reason: " . ($fetchReason ?: "(empty)") . "\n";
    echo "Orders returned: " . count($orders) . "\n";

    if (!empty($orders)) {
        foreach ($orders as $idx => $order) {
            echo "\nOrder " . ($idx + 1) . ":\n";
            echo "  - order_no: " . ($order['order_no'] ?? 'NULL') . "\n";
            echo "  - platform: " . ($order['platform'] ?? 'NULL') . "\n";
            echo "  - package_id: " . ($order['package_id'] ?? 'NULL') . "\n";
            echo "  - order_amount: " . ($order['order_amount'] ?? 'NULL') . "\n";
        }
    } else {
        echo "No orders returned\n";
    }

    echo "</pre>";
}

// Also check what's in CAMPAIGN_PURCHASE_RECORD for auto-discovered (campaign_customer_id=0)
echo "<h3>Current Auto-Discovered Records in DB (campaign_customer_id=0):</h3>";
$countSql = "SELECT COUNT(*) as cnt FROM campaign_purchase_record WHERE campaign_id='" . (int)$campaignId . "' AND campaign_customer_id=0 AND status='A'";
$countResult = $conn->query($countSql);
if ($countResult) {
    $row = $countResult->fetch_assoc();
    echo "<p>Count: " . ($row['cnt'] ?? 0) . "</p>";
}

$detailSql = "SELECT id, order_no, order_amount, order_date FROM campaign_purchase_record WHERE campaign_id='" . (int)$campaignId . "' AND campaign_customer_id=0 AND status='A' ORDER BY id DESC LIMIT 10";
$detailResult = $conn->query($detailSql);
if ($detailResult) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Order No</th><th>Amount</th><th>Date</th></tr>";
    while ($row = $detailResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_amount']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
$financeConn->close();
?>
