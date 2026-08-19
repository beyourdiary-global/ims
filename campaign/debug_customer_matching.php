<?php
include_once '../init.php';
include_once '../include/campaign_common.php';

$campaignId = 2;

$conn = new mysqli(dbhost, dbuser, dbpwd, dbname);
$conn->set_charset('utf8mb4');

$financeConn = new mysqli(dbhost, dbuser, dbpwd, dbFinance);
$financeConn->set_charset('utf8mb4');

$campaign = campaignFetchCampaign($conn, $campaignId);
$campaignPackageIds = campaignFetchCampaignPackageIds($conn, $campaignId);
$periodStart = $campaign['period_start_date'];
$periodEnd = $campaign['period_end_date'];

echo "<h2>Customer Matching Debug</h2>";

// Get auto-discovered customers
$autoCustomers = campaignAutoDiscoverCustomersForPackages($conn, $financeConn, $campaign, $campaignPackageIds, $periodStart, $periodEnd);

echo "<h3>Auto-Discovered Customers:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Name</th><th>Platform</th><th>ID</th></tr>";
foreach ($autoCustomers as $customer) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($customer['customer_name']) . "</td>";
    echo "<td>" . htmlspecialchars($customer['platform']) . "</td>";
    echo "<td>" . htmlspecialchars($customer['id']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Get Finance DB customer list for the period and packages
echo "<h3>Finance DB Customers (in selected packages & period):</h3>";

$packageConditions = [];
foreach ($campaignPackageIds as $id) {
    $packageConditions[] = "`package` LIKE '%" . $financeConn->real_escape_string($id) . "%'";
}

$sql = "SELECT DISTINCT `customer_name` FROM shopee_sg_order_request
        WHERE (" . implode(' OR ', $packageConditions) . ")
        AND `date` >= '" . $periodStart . " 00:00:00'
        AND `date` <= '" . $periodEnd . " 23:59:59'
        ORDER BY `customer_name` ASC";

echo "<p><strong>SQL:</strong></p>";
echo "<pre style='background: #f0f0f0; padding: 10px; font-size: 11px;'>" . htmlspecialchars($sql) . "</pre>";

$result = $financeConn->query($sql);
$financeCustomers = [];
if ($result && $result->num_rows > 0) {
    echo "<p style='background: #ccffcc; padding: 10px;'><strong>✓ Found " . $result->num_rows . " unique customers</strong></p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Finance DB Customer Name</th><th>In Auto-Discovered?</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $financeCustomerName = trim((string) ($row['customer_name'] ?? ''));
        $financeCustomers[] = $financeCustomerName;

        $found = false;
        foreach ($autoCustomers as $autoCust) {
            if (trim((string) ($autoCust['customer_name'] ?? '')) === $financeCustomerName) {
                $found = true;
                break;
            }
        }

        $foundText = $found ? "✓ YES" : "✗ NO";
        echo "<tr>";
        echo "<td>" . htmlspecialchars($financeCustomerName) . "</td>";
        echo "<td style='" . ($found ? "background: #ccffcc" : "background: #ffcccc") . "'>" . $foundText . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ NO CUSTOMERS FOUND IN FINANCE DB</strong></p>";
}

// Show mismatch summary
echo "<h3>Mismatch Analysis:</h3>";
$notDiscovered = [];
foreach ($financeCustomers as $financeCust) {
    $found = false;
    foreach ($autoCustomers as $autoCust) {
        if (trim((string) ($autoCust['customer_name'] ?? '')) === $financeCust) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $notDiscovered[] = $financeCust;
    }
}

if (empty($notDiscovered)) {
    echo "<p style='background: #ccffcc; padding: 10px;'>✓ All Finance DB customers were auto-discovered</p>";
} else {
    echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ Found " . count($notDiscovered) . " customers in Finance DB but NOT auto-discovered:</strong></p>";
    echo "<ul>";
    foreach ($notDiscovered as $name) {
        echo "<li>" . htmlspecialchars($name) . "</li>";
    }
    echo "</ul>";
}

$conn->close();
$financeConn->close();
?>
