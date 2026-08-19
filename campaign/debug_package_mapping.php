<?php
include_once '../init.php';

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

echo "<h2>Package Mapping Debug</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";

$campaignPackageIds = campaignFetchCampaignPackageIds($conn, $campaignId);
echo "<h3>Selected Campaign Packages:</h3>";
echo "<p>IDs: " . implode(', ', $campaignPackageIds) . "</p>";

// Get package info from main DB
echo "<h3>Package Information (from IMS DB):</h3>";
echo "<table border='1' cellpadding='5' style='width: 100%;'>";
echo "<tr><th>Package ID</th><th>Package Name</th></tr>";
foreach ($campaignPackageIds as $pkgId) {
    $pkgResult = $conn->query("SELECT id, pkg_name FROM PACKAGE WHERE id=" . (int)$pkgId);
    if ($pkgResult && $row = $pkgResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . (int)$pkgId . "</td>";
        echo "<td>" . htmlspecialchars($row['pkg_name'] ?? '') . "</td>";
        echo "</tr>";
    } else {
        echo "<tr><td>" . (int)$pkgId . "</td><td style='color: red;'><em>Not found in PACKAGE table</em></td></tr>";
    }
}
echo "</table>";

// Check Finance DB for any orders with these package IDs
echo "<h3>Orders in Finance DB containing these Package IDs:</h3>";
$shopeeTable = 'shopee_sg_order_request';

$packageConditions = array();
foreach ($campaignPackageIds as $pkgId) {
    $escapedId = $financeConn->real_escape_string((string)$pkgId);
    $packageConditions[] = "`package` LIKE '%" . $escapedId . "%'";
}

$sql = "SELECT id, orderID, date, package, customer_name FROM `" . $shopeeTable . "`
        WHERE (" . implode(' OR ', $packageConditions) . ")
        ORDER BY date DESC
        LIMIT 10";

echo "<p><strong>Query:</strong></p>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>" . htmlspecialchars($sql) . "</pre>";

$result = $financeConn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<p style='background: #ccffcc; padding: 10px;'><strong>✓ Found " . $result->num_rows . " orders</strong></p>";
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>Order ID</th><th>Order No</th><th>Date</th><th>Package</th><th>Customer</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['orderID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['package']) . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ No orders found with these package IDs!</strong></p>";
}

// Also check: what are the most common package IDs in Finance DB?
echo "<h3>Most Common Package IDs in Finance DB (last 100 orders):</h3>";
$sql2 = "SELECT DISTINCT package FROM `" . $shopeeTable . "` ORDER BY id DESC LIMIT 100";
$result2 = $financeConn->query($sql2);
if ($result2) {
    $packages = array();
    while ($row = $result2->fetch_assoc()) {
        $pkg = $row['package'];
        $ids = campaignPurchaseExtractPackageIds($pkg, $conn);
        foreach ($ids as $id) {
            if (!isset($packages[$id])) {
                $packages[$id] = 0;
            }
            $packages[$id]++;
        }
    }
    arsort($packages);
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>Package ID</th><th>Occurrence</th><th>In Campaign?</th></tr>";
    $count = 0;
    foreach ($packages as $id => $cnt) {
        if ($count >= 20) break;
        $count++;
        $inCampaign = in_array($id, $campaignPackageIds) ? "✓ YES" : "✗ NO";
        echo "<tr><td>" . htmlspecialchars($id) . "</td><td>" . $cnt . "</td><td>" . $inCampaign . "</td></tr>";
    }
    echo "</table>";
}

$conn->close();
$financeConn->close();
?>
