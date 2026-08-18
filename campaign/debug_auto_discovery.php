<?php
include_once '../init.php';
include_once '../include/campaign_common.php';

$campaignId = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 2;

$conn = new mysqli(dbhost, dbuser, dbpwd, dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
if ($financeConn->connect_error) {
    die('Finance DB Connection failed: ' . $financeConn->connect_error);
}
$financeConn->set_charset('utf8mb4');

$campaign = campaignFetchCampaign($conn, $campaignId);
if (!$campaign) {
    die("Campaign not found");
}

$campaignPackageIds = campaignFetchCampaignPackageIds($conn, $campaignId);
$periodStart = $campaign['period_start_date'] ?? '';
$periodEnd = $campaign['period_end_date'] ?? '';

echo "<h2>Testing Auto-Discovery</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";
echo "<p>Period: " . htmlspecialchars($periodStart) . " to " . htmlspecialchars($periodEnd) . "</p>";
echo "<p>Selected Packages: " . implode(', ', $campaignPackageIds) . "</p><br>";

// Test: Call auto-discovery function
echo "<h3>Running Auto-Discovery Function:</h3>";

$discoveredCustomers = campaignAutoDiscoverCustomersForPackages($conn, $financeConn, $campaign, $campaignPackageIds, $periodStart, $periodEnd);

echo "<p>Result: Found " . count($discoveredCustomers) . " customers</p>";

if (empty($discoveredCustomers)) {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>❌ No customers found!</strong></p>";

    // Troubleshoot
    echo "<h3>Troubleshooting:</h3>";

    // Check if campaignPurchasePlatformConfigs works
    $configs = campaignPurchasePlatformConfigs($conn, $financeConn);
    echo "<p><strong>Available Platforms:</strong> " . implode(', ', array_keys($configs)) . "</p>";

    // Test Shopee table directly
    echo "<h3>Direct Shopee Query Test:</h3>";
    $shopeeTable = 'shopee_sg_order_request';

    $packageIds = $campaignPackageIds;
    $packageConditions = array();
    foreach ($packageIds as $pkgId) {
        $escapedId = $financeConn->real_escape_string((string)$pkgId);
        $packageConditions[] = "`package` LIKE '%" . $escapedId . "%'";
    }

    $sql = "SELECT COUNT(*) as cnt FROM `" . $shopeeTable . "`
            WHERE (" . implode(' OR ', $packageConditions) . ")
            AND DATE(date) >= '" . $financeConn->real_escape_string($periodStart) . "'
            AND DATE(date) <= '" . $financeConn->real_escape_string($periodEnd) . "'";

    echo "<p><strong>Query:</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>" . htmlspecialchars($sql) . "</pre>";

    $result = $financeConn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>Orders found:</strong> " . $row['cnt'] . "</p>";
    } else {
        echo "<p style='color: red;'><strong>Query failed:</strong> " . htmlspecialchars($financeConn->error) . "</p>";
    }

    // Test customer name query
    echo "<h3>Customer Names Query Test:</h3>";
    $sql2 = "SELECT DISTINCT customer_name FROM `" . $shopeeTable . "`
            WHERE (" . implode(' OR ', $packageConditions) . ")
            AND DATE(date) >= '" . $financeConn->real_escape_string($periodStart) . "'
            AND DATE(date) <= '" . $financeConn->real_escape_string($periodEnd) . "'
            ORDER BY customer_name
            LIMIT 10";

    echo "<p><strong>Query:</strong></p>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>" . htmlspecialchars($sql2) . "</pre>";

    $result2 = $financeConn->query($sql2);
    if ($result2) {
        echo "<p>Found " . $result2->num_rows . " distinct customer names:</p>";
        echo "<ul>";
        while ($row = $result2->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['customer_name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><strong>Query failed:</strong> " . htmlspecialchars($financeConn->error) . "</p>";
    }
} else {
    echo "<p style='background: #ccffcc; padding: 10px;'><strong>✓ Auto-discovery works!</strong></p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Customer Name</th><th>Platform</th><th>Auto-Discovered</th></tr>";
    foreach ($discoveredCustomers as $cust) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($cust['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($cust['platform']) . "</td>";
        echo "<td>" . ($cust['_auto_discovered'] ? "✓ Yes" : "✗ No") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
$financeConn->close();
?>
