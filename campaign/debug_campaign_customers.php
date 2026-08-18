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

echo "<h2>Campaign Customers & Package Matching</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";
echo "<p>Period: " . htmlspecialchars($campaign['period_start_date']) . " to " . htmlspecialchars($campaign['period_end_date']) . "</p>";
echo "<p>Selected Packages: " . implode(', ', $campaignPackageIds) . "</p><br>";

// Get customers
echo "<h3>Customers in Campaign:</h3>";
$customerResult = $conn->query("SELECT id, customer_name, email, phone, platform FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE campaign_id=" . (int)$campaignId . " AND status='A' ORDER BY id");
$customers = array();
if ($customerResult) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

echo "<p>Total: " . count($customers) . " customers</p>";

if (empty($customers)) {
    echo "<p style='background: #fff3cd; padding: 10px;'><strong>⚠️ No customers in this campaign!</strong></p>";
} else {
    echo "<table border='1' cellpadding='5' style='margin-bottom: 30px; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Platform</th></tr>";
    foreach ($customers as $customer) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($customer['id']) . "</td>";
        echo "<td>" . htmlspecialchars($customer['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($customer['email']) . "</td>";
        echo "<td>" . htmlspecialchars($customer['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($customer['platform']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // For Shopee customers, check their orders in Finance DB
    echo "<h3>Checking Shopee Customer Orders:</h3>";

    $shopeeCustomers = array_filter($customers, function($c) { return strtolower($c['platform']) === 'shopee'; });
    echo "<p>Shopee customers: " . count($shopeeCustomers) . "</p>";

    if (!empty($shopeeCustomers)) {
        $shopeeTable = 'shopee_sg_order_request';
        $dateFrom = $campaign['period_start_date'];
        $dateTo = $campaign['period_end_date'];

        echo "<h4>Sample Customer Analysis:</h4>";

        // Check first 5 customers
        $sampleCount = 0;
        foreach ($shopeeCustomers as $customer) {
            if ($sampleCount >= 5) break;
            $sampleCount++;

            $custName = htmlspecialchars($customer['customer_name']);
            $custEmail = htmlspecialchars($customer['email']);
            $custPhone = htmlspecialchars($customer['phone']);

            echo "<div style='border: 1px solid #ddd; padding: 10px; margin-bottom: 15px;'>";
            echo "<p><strong>Customer ID " . $customer['id'] . ": " . $custName . " (" . $custEmail . " / " . $custPhone . ")</strong></p>";

            // Search by name
            $safeNName = $financeConn->real_escape_string($customer['customer_name']);
            $sql = "SELECT id, orderID, date, package FROM `" . $shopeeTable . "`
                    WHERE (customer_name LIKE '%" . $safeNName . "%' OR buyer_username LIKE '%" . $safeNName . "%')
                    AND DATE(date) >= '" . $financeConn->real_escape_string($dateFrom) . "'
                    AND DATE(date) <= '" . $financeConn->real_escape_string($dateTo) . "'
                    LIMIT 10";

            $result = $financeConn->query($sql);
            if (!$result) {
                echo "<p style='color: red;'>Query failed: " . htmlspecialchars($financeConn->error) . "</p>";
            } else if ($result->num_rows === 0) {
                echo "<p style='background: #ffcccc; padding: 5px;'>❌ No orders found for this customer in date range</p>";
            } else {
                echo "<p style='background: #ccffcc; padding: 5px;'>✓ Found " . $result->num_rows . " orders:</p>";
                echo "<table border='1' cellpadding='3' style='font-size: 12px;'>";
                echo "<tr><th>Order ID</th><th>Date</th><th>Package</th><th>Match?</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    $packageText = $row['package'];
                    $packageIds = campaignPurchaseExtractPackageIds($packageText, $conn);
                    $matchingIds = array_intersect($packageIds, $campaignPackageIds);
                    $match = empty($matchingIds) ? "✗ NO" : "✓ YES (" . implode(',', $matchingIds) . ")";

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['orderID']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                    echo "<td>" . htmlspecialchars($packageText) . "</td>";
                    echo "<td style='background: " . (empty($matchingIds) ? "#ffcccc" : "#ccffcc") . ";'>" . $match . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            echo "</div>";
        }
    } else {
        echo "<p style='background: #fff3cd; padding: 10px;'><strong>No Shopee customers in this campaign</strong></p>";
    }
}

$conn->close();
$financeConn->close();
?>
