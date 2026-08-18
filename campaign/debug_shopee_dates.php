<?php
include_once '../init.php';

$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
if ($financeConn->connect_error) {
    die('Connection failed: ' . $financeConn->connect_error);
}
$financeConn->set_charset('utf8mb4');

echo "<h2>Shopee Orders - Date Range Analysis</h2>";

$shopeeTable = 'shopee_sg_order_request';

// Get the date column info
echo "<h3>Date Column Information:</h3>";
$infoResult = $financeConn->query("SHOW COLUMNS FROM `" . $shopeeTable . "` LIKE 'date'");
if ($infoResult) {
    while ($row = $infoResult->fetch_assoc()) {
        echo "<pre>" . json_encode($row, JSON_PRETTY_PRINT) . "</pre>";
    }
}

// Get min/max dates
echo "<h3>Date Range in shopee_sg_order_request:</h3>";
$dateResult = $financeConn->query("SELECT MIN(date) as min_date, MAX(date) as max_date, COUNT(*) as total_orders FROM `" . $shopeeTable . "`");
if ($dateResult) {
    $row = $dateResult->fetch_assoc();
    echo "<p><strong>Min Date:</strong> " . htmlspecialchars($row['min_date']) . "</p>";
    echo "<p><strong>Max Date:</strong> " . htmlspecialchars($row['max_date']) . "</p>";
    echo "<p><strong>Total Orders:</strong> " . htmlspecialchars($row['total_orders']) . "</p>";
}

// Get sample dates
echo "<h3>Sample Orders (Last 20):</h3>";
$sampleResult = $financeConn->query("SELECT id, orderID, date, package FROM `" . $shopeeTable . "` ORDER BY id DESC LIMIT 20");
if ($sampleResult && $sampleResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>OrderID</th><th>Date</th><th>Date Type</th><th>Package</th></tr>";
    while ($row = $sampleResult->fetch_assoc()) {
        $dateValue = $row['date'];
        $dateType = gettype($dateValue);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['orderID']) . "</td>";
        echo "<td>" . htmlspecialchars($dateValue) . "</td>";
        echo "<td>" . $dateType . "</td>";
        echo "<td>" . htmlspecialchars($row['package']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check orders in campaign date range with different date formats
echo "<h3>Campaign Date Range Check (2026-07-22 to 2026-08-08):</h3>";

$queries = array(
    "Direct string match" => "SELECT COUNT(*) as cnt FROM `" . $shopeeTable . "` WHERE date >= '2026-07-22' AND date <= '2026-08-08'",
    "Using DATE() function" => "SELECT COUNT(*) as cnt FROM `" . $shopeeTable . "` WHERE DATE(date) >= '2026-07-22' AND DATE(date) <= '2026-08-08'",
    "LIKE pattern (08-)" => "SELECT COUNT(*) as cnt FROM `" . $shopeeTable . "` WHERE date LIKE '2026-08-%'",
    "LIKE pattern (07-)" => "SELECT COUNT(*) as cnt FROM `" . $shopeeTable . "` WHERE date LIKE '2026-07-%'",
    "Starts with 2026-0" => "SELECT COUNT(*) as cnt FROM `" . $shopeeTable . "` WHERE date LIKE '2026-0%'",
);

foreach ($queries as $label => $sql) {
    $result = $financeConn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p><strong>" . htmlspecialchars($label) . ":</strong> " . $row['cnt'] . " orders</p>";
    } else {
        echo "<p style='color: red;'><strong>" . htmlspecialchars($label) . ":</strong> <em>Query failed: " . htmlspecialchars($financeConn->error) . "</em></p>";
    }
}

// Show all distinct dates
echo "<h3>All Distinct Dates in Table:</h3>";
$distinctResult = $financeConn->query("SELECT DISTINCT date FROM `" . $shopeeTable . "` ORDER BY date DESC LIMIT 50");
if ($distinctResult && $distinctResult->num_rows > 0) {
    $dates = array();
    while ($row = $distinctResult->fetch_assoc()) {
        $dates[] = $row['date'];
    }
    echo "<pre>" . htmlspecialchars(json_encode($dates, JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<p>No dates found</p>";
}

$financeConn->close();
?>
