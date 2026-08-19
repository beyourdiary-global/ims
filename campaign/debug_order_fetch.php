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

echo "<h2>Testing Order Fetch for Auto-Discovered Customers</h2>";

// Get auto-discovered customers
$customers = campaignAutoDiscoverCustomersForPackages($conn, $financeConn, $campaign, $campaignPackageIds, $periodStart, $periodEnd);

echo "<p>Found " . count($customers) . " auto-discovered customers:</p>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Customer Name</th><th>Platform</th><th>ID</th></tr>";

foreach ($customers as $customer) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($customer['customer_name']) . "</td>";
    echo "<td>" . htmlspecialchars($customer['platform']) . "</td>";
    echo "<td>" . htmlspecialchars($customer['id']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Now try to fetch orders for each customer
echo "<h3>Fetching Orders for Each Customer:</h3>";

foreach ($customers as $customer) {
    echo "<h4>Customer: " . htmlspecialchars($customer['customer_name']) . " (Platform: " . $customer['platform'] . ")</h4>";

    $fetchReason = '';
    $orders = campaignPurchaseFetchOrdersForCustomer($conn, $financeConn, $campaign, $customer, $periodStart, $periodEnd, $fetchReason);

    echo "<p><strong>Fetch Reason:</strong> " . htmlspecialchars($fetchReason) . "</p>";
    echo "<p><strong>Orders Found:</strong> " . count($orders) . "</p>";

    if (!empty($orders)) {
        echo "<table border='1' cellpadding='5' style='font-size: 12px;'>";
        echo "<tr><th>Order ID</th><th>Order No</th><th>Date</th><th>Amount</th></tr>";
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($order['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($order['order_no'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($order['order_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($order['order_amount'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else if (!empty($fetchReason)) {
        echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ " . htmlspecialchars($fetchReason) . "</strong></p>";
    }
    echo "<hr>";
}

$conn->close();
$financeConn->close();
?>
