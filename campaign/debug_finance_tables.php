<?php
include_once '../init.php';

$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
if ($financeConn->connect_error) {
    die('Finance DB Connection failed: ' . $financeConn->connect_error);
}
$financeConn->set_charset('utf8mb4');

echo "<h2>Finance Database Tables</h2>";
echo "<p>Database: " . htmlspecialchars(dbFinance) . "</p>";
echo "<p>Host: " . htmlspecialchars(dbhost) . "</p><br>";

// List all tables
$result = $financeConn->query("SHOW TABLES");
if (!$result) {
    die("Query failed: " . htmlspecialchars($financeConn->error));
}

$tables = array();
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

echo "<h3>All Tables in Finance Database (" . count($tables) . "):</h3>";
echo "<table border='1' cellpadding='5' style='margin-bottom: 30px;'>";
echo "<tr><th>Table Name</th><th>Row Count</th><th>Columns</th></tr>";

foreach ($tables as $table) {
    $countResult = $financeConn->query("SELECT COUNT(*) as cnt FROM `" . $table . "`");
    $rowCount = 0;
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $rowCount = (int)$row['cnt'];
    }

    // Get columns
    $colResult = $financeConn->query("SHOW COLUMNS FROM `" . $table . "`");
    $columns = array();
    if ($colResult) {
        while ($col = $colResult->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
    }

    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($table) . "</strong></td>";
    echo "<td>" . number_format($rowCount) . "</td>";
    echo "<td style='font-size: 12px;'>" . implode(', ', array_map('htmlspecialchars', $columns)) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Look for order-related tables
echo "<h3>Order-Related Tables:</h3>";
$orderTables = array();
foreach ($tables as $table) {
    if (stripos($table, 'order') !== false || stripos($table, 'shopee') !== false || stripos($table, 'lazada') !== false) {
        $orderTables[] = $table;
    }
}

if (!empty($orderTables)) {
    echo "<p>Found " . count($orderTables) . " order-related tables:</p>";
    echo "<ul>";
    foreach ($orderTables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='background: #fff3cd; padding: 10px;'>No order-related tables found</p>";
}

// Check for table with 'package' column
echo "<h3>Tables Containing 'package' Column:</h3>";
$packageTables = array();
foreach ($tables as $table) {
    $colResult = $financeConn->query("SHOW COLUMNS FROM `" . $table . "` LIKE 'package%'");
    if ($colResult && $colResult->num_rows > 0) {
        $packageTables[] = $table;
    }
}

if (!empty($packageTables)) {
    echo "<p>Found " . count($packageTables) . " tables with package columns:</p>";
    foreach ($packageTables as $table) {
        $colResult = $financeConn->query("SHOW COLUMNS FROM `" . $table . "`");
        $columns = array();
        if ($colResult) {
            while ($col = $colResult->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
        }
        echo "<p><strong>" . htmlspecialchars($table) . ":</strong></p>";
        echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>" . htmlspecialchars(json_encode($columns, JSON_PRETTY_PRINT)) . "</pre>";
    }
} else {
    echo "<p style='background: #fff3cd; padding: 10px;'>No tables with package columns found</p>";
}

$financeConn->close();
?>
