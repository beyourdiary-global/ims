<?php
include_once '../init.php';

$campaignId = isset($_GET['campaign_id']) ? (int) $_GET['campaign_id'] : 2;

$conn = new mysqli(dbhost, dbuser, dbpwd, dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$campaign = campaignFetchCampaign($conn, $campaignId);
if (!$campaign) {
    die("Campaign not found");
}

echo "<h2>Campaign Customer Cleanup</h2>";
echo "<p>Campaign: " . htmlspecialchars($campaign['campaign_name']) . "</p>";

// Count current customers
$countResult = $conn->query("SELECT COUNT(*) as cnt FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE campaign_id=" . (int)$campaignId);
$countRow = $countResult->fetch_assoc();
$currentCount = $countRow['cnt'];

echo "<p>Current customers in campaign: <strong>" . $currentCount . "</strong></p>";

if ($currentCount === 0) {
    echo "<p style='background: #90EE90; padding: 10px;'><strong>✓ No customers to clean up</strong></p>";
} else {
    echo "<p style='background: #fff3cd; padding: 10px;'><strong>⚠️ Found " . $currentCount . " customers</strong></p>";

    echo "<h3>Action Required:</h3>";
    echo "<p>If you want to enable auto-discovery, you need to delete these manual customers.</p>";
    echo "<p>This will delete ALL customers for this campaign and allow auto-discovery to run on next refresh.</p>";

    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Delete customers
        $deleteResult = $conn->query("DELETE FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE campaign_id=" . (int)$campaignId);
        if ($deleteResult) {
            echo "<p style='background: #90EE90; padding: 10px;'><strong>✓ Deleted " . $conn->affected_rows . " customers</strong></p>";
            echo "<p>Auto-discovery is now enabled. Go back to Campaign Report and click 'Refresh Report'.</p>";
        } else {
            echo "<p style='background: #ffcccc; padding: 10px;'><strong>✗ Delete failed: " . htmlspecialchars($conn->error) . "</strong></p>";
        }
    } else {
        echo "<p><strong><a href='?campaign_id=" . (int)$campaignId . "&confirm=yes' style='background: #ff6b6b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>🗑️ DELETE ALL " . $currentCount . " CUSTOMERS</a></strong></p>";
        echo "<p style='color: #666; font-size: 12px;'>Click the button above to permanently delete all customers for this campaign.</p>";
    }
}

$conn->close();
?>
