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

echo "<h2>Package Name Matching Debug</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";
echo "<p>Selected Package IDs: " . implode(', ', $campaignPackageIds) . "</p><br>";

// Get package names from PACKAGE table
echo "<h3>Packages in PACKAGE Table:</h3>";
$packageData = array();
if (!empty($campaignPackageIds)) {
    $result = $conn->query("SELECT id, name FROM `" . PKG . "` WHERE id IN (" . implode(',', array_map('intval', $campaignPackageIds)) . ") ORDER BY id");
    if ($result) {
        echo "<table border='1' cellpadding='5' style='margin-bottom: 20px;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Name Hex</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $name = $row['name'];
            $packageData[$id] = $name;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($id) . "</td>";
            echo "<td>" . htmlspecialchars($name) . "</td>";
            echo "<td style='font-size:10px; font-family:monospace;'>" . bin2hex($name) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Get package values from Shopee orders
echo "<h3>Package Values in Shopee Orders (First 50):</h3>";
$shopeeTable = defined('SHOPEE_ORDER_REQ') ? SHOPEE_ORDER_REQ : 'shopee_order_request';

$sql = "SELECT DISTINCT package FROM `" . $shopeeTable . "` WHERE package IS NOT NULL AND package != '' ORDER BY package LIMIT 50";
$result = $financeConn->query($sql);
if (!$result) {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>Error:</strong> " . htmlspecialchars($financeConn->error) . "</p>";
} else {
    echo "<p>Found " . $result->num_rows . " distinct package values</p>";
    echo "<table border='1' cellpadding='5' style='width: 100%;'>";
    echo "<tr><th>Package Value</th><th>Hex</th><th>Match?</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $packageValue = $row['package'];
        $match = "✗ NO";

        // Check direct match
        if (in_array($packageValue, $packageData)) {
            $match = "✓ Direct Match";
        } else {
            // Check if it's a numeric ID that matches
            if (ctype_digit($packageValue) && in_array((int)$packageValue, $campaignPackageIds)) {
                $match = "✓ Numeric ID Match";
            }
            // Check case-insensitive match
            else {
                foreach ($packageData as $id => $name) {
                    if (trim($packageValue) === trim($name)) {
                        $match = "✓ Trim Match (ID: " . $id . ")";
                        break;
                    }
                    if (mb_strtolower($packageValue) === mb_strtolower($name)) {
                        $match = "✓ Case-Insensitive Match (ID: " . $id . ")";
                        break;
                    }
                }
            }
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($packageValue) . "</td>";
        echo "<td style='font-size:10px; font-family:monospace;'>" . bin2hex($packageValue) . "</td>";
        echo "<td style='background: " . (strpos($match, 'Match') !== false ? '#ccffcc' : '#ffcccc') . ";'>" . $match . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Also check in date range for campaign
echo "<h3>Packages in Campaign Date Range (" . htmlspecialchars($campaign['period_start_date']) . " to " . htmlspecialchars($campaign['period_end_date']) . "):</h3>";

$dateFrom = $financeConn->real_escape_string($campaign['period_start_date']);
$dateTo = $financeConn->real_escape_string($campaign['period_end_date']);

$sql = "SELECT DISTINCT package FROM `" . $shopeeTable . "`
    WHERE package IS NOT NULL AND package != ''
    AND DATE(order_date) >= '" . $dateFrom . "'
    AND DATE(order_date) <= '" . $dateTo . "'
    ORDER BY package
    LIMIT 50";

$result = $financeConn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " distinct package values in this period:</p>";
    $periodPackages = array();
    while ($row = $result->fetch_assoc()) {
        $periodPackages[] = $row['package'];
    }
    echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>" . htmlspecialchars(json_encode($periodPackages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<p style='background: #fff3cd; padding: 10px;'>No orders found in this date range</p>";
}

$conn->close();
$financeConn->close();
?>
