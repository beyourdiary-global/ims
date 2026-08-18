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

echo "<h2>Find Orders Matching Campaign Packages</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";
echo "<p>Period: " . htmlspecialchars($campaign['period_start_date']) . " to " . htmlspecialchars($campaign['period_end_date']) . "</p>";
echo "<p>Selected Package IDs: " . implode(', ', $campaignPackageIds) . "</p><br>";

// Search for orders with matching package IDs in Shopee
echo "<h3>Searching Shopee Orders for Packages: " . implode(', ', $campaignPackageIds) . "</h3>";

$shopeeTable = defined('SHOPEE_ORDER_REQ') ? SHOPEE_ORDER_REQ : 'shopee_order_request';
echo "<p style='background: #f0f0f0; padding: 10px; margin-bottom: 10px;'><strong>Table:</strong> " . htmlspecialchars($shopeeTable) . "</p>";

$dateFrom = $financeConn->real_escape_string($campaign['period_start_date']);
$dateTo = $financeConn->real_escape_string($campaign['period_end_date']);

// Search using LIKE for each package ID
$conditions = array();
foreach ($campaignPackageIds as $pkgId) {
    $escapedId = $financeConn->real_escape_string((string)$pkgId);
    // Search for package field containing this ID
    $conditions[] = "package LIKE '%" . $escapedId . "%'";
}

$sql = "SELECT id, order_no, package, amount, order_date FROM `" . $shopeeTable . "`
    WHERE (" . implode(' OR ', $conditions) . ")
    AND DATE(order_date) >= '" . $dateFrom . "'
    AND DATE(order_date) <= '" . $dateTo . "'
    ORDER BY order_date DESC
    LIMIT 20";

echo "<p style='background: #f0f0f0; padding: 10px; margin-bottom: 20px;'><strong>SQL Query:</strong><br>" . htmlspecialchars($sql) . "</p>";

$result = $financeConn->query($sql);
if (!$result) {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>Error:</strong> " . htmlspecialchars($financeConn->error) . "</p>";
} else {
    echo "<p>Found " . $result->num_rows . " orders with these package IDs</p>";
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5' style='width: 100%;'>";
        echo "<tr><th>Order ID</th><th>Order No</th><th>Package</th><th>Extracted IDs</th><th>Match?</th><th>Amount</th><th>Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $packageText = $row['package'];
            $extractedIds = campaignPurchaseExtractPackageIds($packageText, $conn);
            $matchingIds = array_intersect($extractedIds, $campaignPackageIds);

            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['order_no']) . "</td>";
            echo "<td>" . htmlspecialchars($packageText) . "</td>";
            echo "<td><code>" . json_encode($extractedIds) . "</code></td>";
            echo "<td style='background: " . (empty($matchingIds) ? "#ffcccc" : "#ccffcc") . ";'>" . implode(',', $matchingIds) . "</td>";
            echo "<td>" . htmlspecialchars($row['amount']) . "</td>";
            echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='background: #fff3cd; padding: 10px;'><strong>⚠️ No orders found matching these package IDs in the date range!</strong></p>";

        // Show what packages DO exist in the date range
        echo "<h3>Available Orders in This Date Range:</h3>";
        $allSql = "SELECT DISTINCT package FROM `" . $shopeeTable . "`
            WHERE DATE(order_date) >= '" . $dateFrom . "'
            AND DATE(order_date) <= '" . $dateTo . "'
            ORDER BY package
            LIMIT 50";
        $allResult = $financeConn->query($allSql);
        if ($allResult && $allResult->num_rows > 0) {
            echo "<p>Packages found in this period:</p>";
            $packages = array();
            while ($row = $allResult->fetch_assoc()) {
                $packages[] = $row['package'];
            }
            echo "<pre>" . htmlspecialchars(json_encode($packages, JSON_PRETTY_PRINT)) . "</pre>";
        }
    }
}

$conn->close();
$financeConn->close();
?>
