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

echo "<h2>All Customers Buying Campaign Packages</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";
echo "<p>Period: " . htmlspecialchars($campaign['period_start_date']) . " to " . htmlspecialchars($campaign['period_end_date']) . "</p>";
echo "<p>Selected Packages: " . implode(', ', $campaignPackageIds) . "</p><br>";

// Get campaign customers
$addedCustomers = array();
$addedCustomerEmails = array();
$customerResult = $conn->query("SELECT id, customer_name, email, phone, platform FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE campaign_id=" . (int)$campaignId . " AND status='A'");
if ($customerResult) {
    while ($row = $customerResult->fetch_assoc()) {
        $addedCustomers[$row['email']] = $row;
        $addedCustomerEmails[] = $row['email'];
    }
}

echo "<h3>Finding All Customers Who Bought Campaign Packages:</h3>";

// Query Shopee orders for campaign packages in date range
$shopeeTable = 'shopee_sg_order_request';
$dateFrom = $campaign['period_start_date'];
$dateTo = $campaign['period_end_date'];

// Build package search conditions
$packageConditions = array();
foreach ($campaignPackageIds as $pkgId) {
    $escapedId = $financeConn->real_escape_string((string)$pkgId);
    $packageConditions[] = "package LIKE '%" . $escapedId . "%'";
}

$sql = "SELECT DISTINCT buyer_username, customer_name FROM `" . $shopeeTable . "`
    WHERE (" . implode(' OR ', $packageConditions) . ")
    AND DATE(date) >= '" . $financeConn->real_escape_string($dateFrom) . "'
    AND DATE(date) <= '" . $financeConn->real_escape_string($dateTo) . "'
    ORDER BY customer_name";

$result = $financeConn->query($sql);
if (!$result) {
    die("Query failed: " . htmlspecialchars($financeConn->error));
}

$allOrderingCustomers = array();
while ($row = $result->fetch_assoc()) {
    $allOrderingCustomers[] = array(
        'buyer_username' => $row['buyer_username'],
        'customer_name' => $row['customer_name'],
    );
}

echo "<p><strong>Found " . count($allOrderingCustomers) . " customers who bought these packages:</strong></p>";

if (count($allOrderingCustomers) === 0) {
    echo "<p style='background: #fff3cd; padding: 10px;'>No customers found for these packages in this period</p>";
} else {
    echo "<table border='1' cellpadding='8' style='width: 100%; margin-bottom: 20px;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Status</th>";
    echo "<th>Customer Name</th>";
    echo "<th>Buyer Username</th>";
    echo "<th>Orders in Period</th>";
    echo "<th>Packages Purchased</th>";
    echo "</tr>";

    foreach ($allOrderingCustomers as $customer) {
        $custName = htmlspecialchars($customer['customer_name']);
        $buyerUsername = htmlspecialchars($customer['buyer_username']);

        // Check if already added
        $isAdded = in_array($custName, array_column($addedCustomers, 'customer_name'));
        $statusBadge = $isAdded ?
            "<span style='background: #90EE90; padding: 3px 8px; border-radius: 3px; font-weight: bold;'>✓ ADDED</span>" :
            "<span style='background: #FFB6C6; padding: 3px 8px; border-radius: 3px; font-weight: bold;'>+ NEW</span>";

        // Get orders for this customer
        $safeName = $financeConn->real_escape_string($customer['customer_name']);
        $orderSql = "SELECT id, orderID, date, package FROM `" . $shopeeTable . "`
                    WHERE customer_name = '" . $safeName . "'
                    AND (" . implode(' OR ', $packageConditions) . ")
                    AND DATE(date) >= '" . $financeConn->real_escape_string($dateFrom) . "'
                    AND DATE(date) <= '" . $financeConn->real_escape_string($dateTo) . "'
                    ORDER BY date DESC";

        $orderResult = $financeConn->query($orderSql);
        $orderCount = 0;
        $packagesStr = '';
        $packages = array();

        if ($orderResult) {
            $orderCount = $orderResult->num_rows;
            while ($order = $orderResult->fetch_assoc()) {
                $pkgIds = campaignPurchaseExtractPackageIds($order['package'], $conn);
                $matchingIds = array_intersect($pkgIds, $campaignPackageIds);
                if (!empty($matchingIds)) {
                    foreach ($matchingIds as $pid) {
                        if (!in_array($pid, $packages)) {
                            $packages[] = $pid;
                        }
                    }
                }
            }
        }
        $packagesStr = implode(', ', $packages);

        echo "<tr>";
        echo "<td>" . $statusBadge . "</td>";
        echo "<td>" . $custName . "</td>";
        echo "<td>" . $buyerUsername . "</td>";
        echo "<td style='text-align: center;'>" . $orderCount . "</td>";
        echo "<td>" . $packagesStr . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Summary
    $addedCount = 0;
    $newCount = 0;
    foreach ($allOrderingCustomers as $customer) {
        $isAdded = in_array($customer['customer_name'], array_column($addedCustomers, 'customer_name'));
        if ($isAdded) {
            $addedCount++;
        } else {
            $newCount++;
        }
    }

    echo "<h3>Summary:</h3>";
    echo "<p><span style='background: #90EE90; padding: 5px 10px; border-radius: 3px;'><strong>✓ Already Added: " . $addedCount . "</strong></span></p>";
    echo "<p><span style='background: #FFB6C6; padding: 5px 10px; border-radius: 3px;'><strong>+ New Customers: " . $newCount . "</strong></span></p>";
}

$conn->close();
$financeConn->close();
?>
