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

echo "<h2>Campaign Package Debug</h2>";
echo "Campaign ID: " . $campaignId . "<br>";

// Get campaign selected packages
$campaignPackageIds = campaignFetchCampaignPackageIds($conn, $campaignId);
echo "Selected Package IDs: " . json_encode($campaignPackageIds) . "<br><br>";

// Get package names
echo "<h3>Package Names:</h3>";
$result = $conn->query("SELECT id, name FROM `" . PKG . "` WHERE id IN (" . implode(',', $campaignPackageIds) . ") ORDER BY id");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars($row['name']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Query failed: " . $conn->error;
}

// Get sample orders from Rosebady
echo "<h3>Sample Orders from Rosebady (last 10):</h3>";
$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
if ($financeConn->connect_error) {
    die('Finance DB Connection failed: ' . $financeConn->connect_error);
}
$financeConn->set_charset('utf8mb4');

$result = $financeConn->query("SELECT id, order_no, package, amount, order_date FROM `t_order_header` ORDER BY id DESC LIMIT 10");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Order ID</th><th>Order No</th><th>Package</th><th>Amount</th><th>Order Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $packageIds = campaignPurchaseExtractPackageIds($row['package'], $conn);
        $packageText = $row['package'] . " => IDs: " . json_encode($packageIds);
        echo "<tr><td>" . htmlspecialchars($row['id']) . "</td><td>" . htmlspecialchars($row['order_no']) . "</td><td>" . htmlspecialchars($packageText) . "</td><td>" . htmlspecialchars($row['amount']) . "</td><td>" . htmlspecialchars($row['order_date']) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Query failed: " . $financeConn->error;
}

$conn->close();
$financeConn->close();
?>
