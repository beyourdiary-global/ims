<?php
include_once '../init.php';
include_once '../include/campaign_common.php';

// Check selected packages
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

echo "<h2>Campaign Package Matching Debug</h2>";
echo "Campaign ID: " . $campaignId . "<br>";
echo "Current Date/Time: " . date('Y-m-d H:i:s') . "<br><br>";

// Get campaign details
$campaign = campaignFetchCampaign($conn, $campaignId);
if (!$campaign) {
    die("Campaign not found");
}

echo "<h3>Campaign Details:</h3>";
echo "Campaign Name: " . htmlspecialchars($campaign['campaign_name']) . "<br>";
echo "Period Start: " . htmlspecialchars($campaign['period_start_date'] ?? '') . "<br>";
echo "Period End: " . htmlspecialchars($campaign['period_end_date'] ?? '') . "<br>";

// Get campaign selected packages
$campaignPackageIds = campaignFetchCampaignPackageIds($conn, $campaignId);
echo "Selected Package IDs: " . json_encode($campaignPackageIds) . "<br>";

// Get package names
echo "<h3>Campaign Selected Packages:</h3>";
if (!empty($campaignPackageIds)) {
    $result = $conn->query("SELECT id, name FROM `" . PKG . "` WHERE id IN (" . implode(',', array_map('intval', $campaignPackageIds)) . ") ORDER BY id");
    if ($result) {
        echo "<table border='1' cellpadding='5' style='margin-bottom: 20px;'>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars($row['name']) . "</td></tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>No selected packages for this campaign</p>";
}

// First, list all tables in finance database
echo "<h3>Finance Database Tables:</h3>";
$tablesResult = $financeConn->query("SHOW TABLES");
$tables = array();
if ($tablesResult) {
    while ($row = $tablesResult->fetch_row()) {
        $tables[] = $row[0];
    }
}
echo "<p>Available tables: " . (empty($tables) ? "(none)" : implode(', ', array_map('htmlspecialchars', $tables))) . "</p>";

// Get sample orders from finance DB within campaign date range
echo "<h3>Finance Database Orders (Sample):</h3>";
$dateCondition = "";
if (!empty($campaign['period_start_date']) && !empty($campaign['period_end_date'])) {
    $dateCondition = " AND DATE(order_date) >= '" . $financeConn->real_escape_string($campaign['period_start_date']) . "' AND DATE(order_date) <= '" . $financeConn->real_escape_string($campaign['period_end_date']) . "'";
}

$sql = "SELECT id, order_no, package, amount, order_date FROM `t_order_header`" . ($dateCondition ? " WHERE 1=1 " . $dateCondition : "") . " ORDER BY id DESC LIMIT 20";
echo "<p style='background: #f0f0f0; padding: 10px; margin-bottom: 10px; font-size: 12px;'><strong>Query:</strong> " . htmlspecialchars($sql) . "</p>";

$result = $financeConn->query($sql);
if (!$result) {
    echo "Query failed: " . htmlspecialchars($financeConn->error);
} else {
    echo "<p>Total rows returned: " . $result->num_rows . "</p>";
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5' style='width: 100%;'>";
        echo "<tr><th>Order ID</th><th>Order No</th><th>Package</th><th>Extracted IDs</th><th>Match Campaign?</th><th>Amount</th><th>Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $packageText = $row['package'];
            $extractedIds = campaignPurchaseExtractPackageIds($packageText, $conn);
            $matchingIds = !empty($extractedIds) ? array_intersect($extractedIds, $campaignPackageIds) : array();
            $matchStatus = !empty($matchingIds) ? "✓ YES (" . implode(',', $matchingIds) . ")" : "✗ NO";

            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['order_no']) . "</td>";
            echo "<td>" . htmlspecialchars($packageText) . "</td>";
            echo "<td><code>" . json_encode($extractedIds) . "</code></td>";
            echo "<td style='background: " . (empty($matchingIds) ? "#ffcccc" : "#ccffcc") . ";'>" . $matchStatus . "</td>";
            echo "<td>" . htmlspecialchars($row['amount']) . "</td>";
            echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='background: #fff3cd; padding: 10px;'><strong>No orders found in date range</strong></p>";
    }
}

// Also check customers
echo "<h3>Campaign Customers:</h3>";
$customerResult = $conn->query("SELECT id, customer_name, email, phone, platform FROM `" . campaignTableName(CAMPAIGN_CUSTOMER) . "` WHERE campaign_id=" . (int)$campaignId . " AND status='A' LIMIT 10");
if ($customerResult && $customerResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Platform</th></tr>";
    while ($row = $customerResult->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars($row['customer_name']) . "</td><td>" . htmlspecialchars($row['email']) . "</td><td>" . htmlspecialchars($row['phone']) . "</td><td>" . htmlspecialchars($row['platform']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No customers found for this campaign</p>";
}

$conn->close();
$financeConn->close();
?>
