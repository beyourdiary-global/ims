<?php

$rootDir = dirname(__DIR__);

include_once $rootDir . '/init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/campaign_common.php';

if (!isset($connect) || !($connect instanceof mysqli)) {
    echo "Campaign cron failed: CMS database connection is not available.\n";
    exit(1);
}
if (!isset($finance_connect) || !($finance_connect instanceof mysqli)) {
    $finance_connect = $connect;
}

@mysqli_set_charset($connect, 'utf8mb4');
if ($finance_connect instanceof mysqli) {
    @mysqli_set_charset($finance_connect, 'utf8mb4');
}

$startedAt = date('Y-m-d H:i:s');
$results = array(
    'started_at' => $startedAt,
    'rule_generation' => array(),
    'purchase_check' => array(),
    'finalize' => array(),
);

function campaignCronLine($message)
{
    echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . "<br>\n";
}

function campaignCronPlainLine($message)
{
    echo (string) $message . "\n";
}

function campaignCronOutput($results)
{
    $isCli = (PHP_SAPI === 'cli');
    $line = $isCli ? 'campaignCronPlainLine' : 'campaignCronLine';
    if (!$isCli) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Campaign Cron</title></head><body style="margin:0; padding:24px; background:#111827; color:#f9fafb; font-family:Consolas, Monaco, monospace; line-height:1.7;"><h2 style="margin:0 0 16px; font:600 22px/1.4 Arial, sans-serif; color:#ffffff;">Campaign Cron Result</h2><div style="background:#1f2937; border:1px solid #374151; border-radius:10px; padding:18px 20px;">';
    }

    $line('Started: ' . ($results['started_at'] ?? ''));
    $line('Finished: ' . date('Y-m-d H:i:s'));
    $line('');
    $line('Rule generation:');
    if (empty($results['rule_generation'])) {
        $line('- No rule generated.');
    } else {
        foreach ($results['rule_generation'] as $item) {
            $line('- Rule #' . ($item['rule_id'] ?? '') . ': ' . ($item['message'] ?? '') . ' Campaign ID: ' . ($item['campaign_id'] ?? 0));
        }
    }

    $line('');
    $line('Purchase checks:');
    if (empty($results['purchase_check'])) {
        $line('- No active campaign purchase check.');
    } else {
        foreach ($results['purchase_check'] as $item) {
            $line('- Campaign #' . ($item['campaign_id'] ?? '') . ': checked ' . ($item['checked_customers'] ?? 0) . ', inserted ' . ($item['records_inserted'] ?? 0));
        }
    }

    $line('');
    $line('Finalize ended campaigns:');
    if (empty($results['finalize'])) {
        $line('- No campaign finalized.');
    } else {
        foreach ($results['finalize'] as $item) {
            $line('- Campaign #' . ($item['campaign_id'] ?? '') . ': ' . ($item['message'] ?? ''));
        }
    }

    if (!$isCli) {
        echo '</div></body></html>';
    }
}

// 1. Auto generate campaigns from active rules.
if (campaignTableExists($connect, CAMPAIGN_RULE_SETTING)) {
    $ruleSql = "SELECT * FROM " . campaignTableName(CAMPAIGN_RULE_SETTING) . " WHERE `status`='A' AND `rule_status`='Active' ORDER BY `id` ASC";
    $ruleResult = mysqli_query($connect, $ruleSql);
    if ($ruleResult) {
        while ($rule = $ruleResult->fetch_assoc()) {
            $note = '';
            if (!campaignRuleShouldRunToday($rule, $note)) {
                if ($note !== '') {
                    $results['rule_generation'][] = array('rule_id' => (int) ($rule['id'] ?? 0), 'message' => $note, 'campaign_id' => 0);
                }
                continue;
            }

            $generation = campaignRuleGenerateCampaign($connect, $finance_connect, (int) ($rule['id'] ?? 0), false);
            $generation['rule_id'] = (int) ($rule['id'] ?? 0);
            $results['rule_generation'][] = $generation;
        }
    }
}

// 2. Run purchase check for active campaigns.
$activeCampaignWhere = array("`status`='A'");
if (campaignColumnExists($connect, CAMPAIGN, 'campaign_status')) {
    $activeCampaignWhere[] = "IFNULL(`campaign_status`, '') IN ('Active','Draft','Paused')";
}
$activeCampaignSql = "SELECT `id` FROM " . campaignTableName(CAMPAIGN) . " WHERE " . implode(' AND ', $activeCampaignWhere) . " ORDER BY `id` ASC";
$activeCampaignResult = mysqli_query($connect, $activeCampaignSql);
if ($activeCampaignResult) {
    while ($campaignRow = $activeCampaignResult->fetch_assoc()) {
        $campaignId = (int) ($campaignRow['id'] ?? 0);
        if ($campaignId <= 0) {
            continue;
        }
        $summary = campaignRunPurchaseCheck($connect, $finance_connect, $campaignId);
        $summary['campaign_id'] = $campaignId;
        $results['purchase_check'][] = $summary;
    }
}

// 3. Finalize ended campaigns.
$endedCampaignWhere = array("`status`='A'", "`period_end_date` < CURDATE()");
if (campaignColumnExists($connect, CAMPAIGN, 'campaign_status')) {
    $endedCampaignWhere[] = "IFNULL(`campaign_status`, '') <> 'Completed'";
}
$endedCampaignSql = "SELECT `id` FROM " . campaignTableName(CAMPAIGN) . " WHERE " . implode(' AND ', $endedCampaignWhere) . " ORDER BY `id` ASC";
$endedCampaignResult = mysqli_query($connect, $endedCampaignSql);
if ($endedCampaignResult) {
    while ($campaignRow = $endedCampaignResult->fetch_assoc()) {
        $campaignId = (int) ($campaignRow['id'] ?? 0);
        if ($campaignId <= 0) {
            continue;
        }
        $finalize = campaignFinalizeEndedCampaign($connect, $finance_connect, $campaignId);
        $finalize['campaign_id'] = $campaignId;
        $results['finalize'][] = $finalize;
    }
}

campaignCronOutput($results);
