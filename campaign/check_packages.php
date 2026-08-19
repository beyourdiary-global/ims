<?php
include_once '../init.php';

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

echo "<h2>Package Check</h2>";

// 1. Check selected packages in IMS DB
echo "<h3>1. Selected Campaign Packages (IMS DB):</h3>";
$selectedIds = array(419, 407, 402, 400, 406);
echo "<p>Package IDs: " . implode(', ', $selectedIds) . "</p>";

// Try to find these packages in PACKAGE table
$result = $conn->query("SELECT id, pkg_name, status FROM PACKAGE WHERE id IN (419, 407, 402, 400, 406) ORDER BY id DESC");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Package Name</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['pkg_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ Packages not found in PACKAGE table!</strong></p>";

    // Check if they're in CAMPAIGN_PACKAGE table instead
    echo "<p><strong>Checking CAMPAIGN_PACKAGE table...</strong></p>";
    $result = $conn->query("SELECT DISTINCT package_id FROM CAMPAIGN_PACKAGE WHERE package_id IN (419, 407, 402, 400, 406)");
    if ($result && $result->num_rows > 0) {
        echo "<p style='background: #ccffcc; padding: 10px;'>✓ Found in CAMPAIGN_PACKAGE table (linked to campaigns)</p>";
    }

    // Check which tables contain these IDs
    echo "<p><strong>Tables in IMS DB containing these IDs:</strong></p>";
    $tables = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . dbname . "'");
    $foundTables = array();
    if ($tables) {
        while ($tableRow = $tables->fetch_assoc()) {
            $tableName = $tableRow['TABLE_NAME'];
            // Check if table has an id column
            $colCheck = $conn->query("SHOW COLUMNS FROM " . $tableName . " LIKE 'id'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $checkResult = $conn->query("SELECT COUNT(*) as cnt FROM `" . $tableName . "` WHERE id IN (419, 407, 402, 400, 406)");
                if ($checkResult) {
                    $row = $checkResult->fetch_assoc();
                    if ($row['cnt'] > 0) {
                        $foundTables[] = $tableName . " (" . $row['cnt'] . " rows)";
                    }
                }
            }
        }
    }
    if (!empty($foundTables)) {
        echo "<ul>";
        foreach ($foundTables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><em>These IDs don't appear to exist in IMS DB at all.</em></p>";
    }
}

// 2. Check Finance DB for orders with these package IDs
echo "<h3>2. Orders in Finance DB with selected package IDs:</h3>";
$packageConditions = array();
foreach ($selectedIds as $id) {
    $packageConditions[] = "`package` LIKE '%" . $financeConn->real_escape_string($id) . "%'";
}
$sql = "SELECT id, orderID, date, package, customer_name FROM shopee_sg_order_request
        WHERE " . implode(' OR ', $packageConditions) . "
        ORDER BY date DESC LIMIT 10";

$result = $financeConn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<p style='background: #ccffcc; padding: 10px;'><strong>✓ Found " . $result->num_rows . " orders with these packages</strong></p>";
    echo "<table border='1' cellpadding='5' style='width: 100%; font-size: 12px;'>";
    echo "<tr><th>Order ID</th><th>Order No</th><th>Date</th><th>Package Text</th><th>Customer</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['orderID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
        echo "<td style='max-width: 200px;'>" . htmlspecialchars($row['package']) . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ NO ORDERS FOUND with these package IDs in Finance DB!</strong></p>";
}

// 3. Show most common packages in Finance DB
echo "<h3>3. Most Common Package IDs in Finance DB (from last 200 orders):</h3>";
$result = $financeConn->query("SELECT package FROM shopee_sg_order_request ORDER BY id DESC LIMIT 200");
if ($result) {
    $packageCounts = array();
    while ($row = $result->fetch_assoc()) {
        $pkg = $row['package'];
        // Extract IDs from package string
        preg_match_all('/\b(\d+)\b/', $pkg, $matches);
        foreach ($matches[1] as $id) {
            if (!isset($packageCounts[$id])) {
                $packageCounts[$id] = 0;
            }
            $packageCounts[$id]++;
        }
    }
    arsort($packageCounts);

    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>Package ID</th><th>Count</th><th>In Campaign?</th><th>Package Name (if exists)</th></tr>";

    $count = 0;
    foreach ($packageCounts as $id => $cnt) {
        if ($count >= 20) break;
        $count++;

        $inCampaign = in_array($id, $selectedIds) ? "✓ YES" : "✗ NO";

        // Get package name
        $pkgResult = $conn->query("SELECT pkg_name FROM PACKAGE WHERE id=" . (int)$id);
        $pkgName = '';
        if ($pkgResult && $row = $pkgResult->fetch_assoc()) {
            $pkgName = htmlspecialchars($row['pkg_name']);
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($id) . "</td>";
        echo "<td>" . $cnt . "</td>";
        echo "<td>" . $inCampaign . "</td>";
        echo "<td>" . $pkgName . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
$financeConn->close();
?>
