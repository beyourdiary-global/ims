<?php
// Expected cron:
// 5 1 1 * * /usr/local/bin/php -q /home/USERNAME/public_html/ims/cron/cron_member_point_tier_bonus.php

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';

$requestedBonusMonth = '';
if (isset($_GET['bonus_month'])) {
    $requestedBonusMonth = trim((string) $_GET['bonus_month']);
} elseif (isset($argv) && is_array($argv) && !empty($argv[1])) {
    $requestedBonusMonth = trim((string) $argv[1]);
}

$cronResult = memberPointRunMonthlyTierCron($connect, $finance_connect, array(
    'bonus_month' => $requestedBonusMonth,
));

header('Content-Type: text/plain; charset=utf-8');
echo "Member point tier cron completed.\n";
echo "Bonus month: " . (string) ($cronResult['bonus_month'] ?? '') . "\n";
echo "As of date: " . (string) ($cronResult['as_of_date'] ?? '') . "\n";
echo "Members checked: " . (int) ($cronResult['members_checked'] ?? 0) . "\n";
echo "Tier upgrades: " . (int) ($cronResult['upgrades'] ?? 0) . "\n";
echo "Bonus created: " . (int) ($cronResult['bonus_created'] ?? 0) . "\n";
echo "Bonus updated: " . (int) ($cronResult['bonus_updated'] ?? 0) . "\n";

$errors = isset($cronResult['errors']) && is_array($cronResult['errors']) ? $cronResult['errors'] : array();
if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $errorMessage) {
        echo "- " . (string) $errorMessage . "\n";
    }
}
