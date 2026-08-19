<?php
include_once '../init.php';

$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
$financeConn->set_charset('utf8mb4');

$campaignId = 2;
$campaignPackageIds = [419, 407, 402, 400, 406];
$periodStart = '2026-07-22';
$periodEnd = '2026-08-08';

echo "<h2>Raw Shopee Orders in Finance DB</h2>";
echo "<p>Campaign packages: " . implode(', ', $campaignPackageIds) . "</p>";
echo "<p>Period: $periodStart to $periodEnd</p>";

// Query exact orders matching period and package
$packageConditions = [];
foreach ($campaignPackageIds as $id) {
    $packageConditions[] = "`package` LIKE '%" . $financeConn->real_escape_string($id) . "%'";
}

$sql = "SELECT * FROM shopee_sg_order_request
        WHERE (" . implode(' OR ', $packageConditions) . ")
        AND `date` >= '" . $periodStart . " 00:00:00'
        AND `date` <= '" . $periodEnd . " 23:59:59'
        ORDER BY `date` DESC";

echo "<p><strong>SQL:</strong></p>";
echo "<pre style='background: #f0f0f0; padding: 10px; font-size: 11px;'>" . htmlspecialchars($sql) . "</pre>";

$result = $financeConn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<p style='background: #ccffcc; padding: 10px;'><strong>✓ Found " . $result->num_rows . " orders</strong></p>";
    echo "<table border='1' cellpadding='5' style='width: 100%; font-size: 11px;'>";
    echo "<tr>";

    // Get all column names first
    $row = $result->fetch_assoc();
    foreach (array_keys($row) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";

    // Reset result
    $result->data_seek(0);

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $val) {
            if (is_null($val)) {
                echo "<td style='color: #999;'><em>NULL</em></td>";
            } else if (strlen($val) > 100) {
                echo "<td style='max-width: 200px; overflow: auto;'>" . htmlspecialchars(substr($val, 0, 100)) . "...</td>";
            } else {
                echo "<td>" . htmlspecialchars($val) . "</td>";
            }
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ NO ORDERS FOUND</strong></p>";
}

$financeConn->close();
?>
