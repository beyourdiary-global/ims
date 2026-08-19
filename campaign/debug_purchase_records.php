<?php
include_once '../init.php';

$conn = new mysqli(dbhost, dbuser, dbpwd, dbname);
$conn->set_charset('utf8mb4');

$campaignId = 2;

echo "<h2>CAMPAIGN_PURCHASE_RECORD Debug</h2>";

// Check table exists
$tableCheckResult = $conn->query("SHOW TABLES LIKE 'campaign_purchase_record'");
if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ Table campaign_purchase_record NOT FOUND</strong></p>";
    exit;
}

// Count all records for this campaign
$countSql = "SELECT COUNT(*) as total FROM campaign_purchase_record WHERE campaign_id='" . (int)$campaignId . "' AND status='A'";
$countResult = $conn->query($countSql);
if ($countResult) {
    $countRow = $countResult->fetch_assoc();
    $total = (int)($countRow['total'] ?? 0);
    echo "<p><strong>Active Records in Campaign $campaignId:</strong> $total</p>";

    if ($total === 0) {
        echo "<p style='background: #ffcccc; padding: 10px;'>✗ NO RECORDS FOUND - Orders may not have been inserted</p>";
    } else {
        echo "<p style='background: #ccffcc; padding: 10px;'>✓ Found $total records</p>";
    }
}

// Show details of records
echo "<h3>All Active Records:</h3>";
$detailSql = "SELECT id, campaign_customer_id, order_no, order_amount, order_date, status, create_date, create_time FROM campaign_purchase_record WHERE campaign_id='" . (int)$campaignId . "' AND status='A' ORDER BY id DESC LIMIT 50";
$detailResult = $conn->query($detailSql);

if ($detailResult && $detailResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Customer ID</th><th>Order No</th><th>Amount</th><th>Date</th><th>Status</th><th>Created</th></tr>";
    while ($row = $detailResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['campaign_customer_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_amount']) . "</td>";
        echo "<td>" . htmlspecialchars($row['order_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['create_date'] . ' ' . $row['create_time']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p><em>No records to display</em></p>";
}

// Check table structure
echo "<h3>Table Structure:</h3>";
$structureResult = $conn->query("SHOW COLUMNS FROM campaign_purchase_record");
if ($structureResult) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $structureResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>
