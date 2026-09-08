<?php
// ONE-OFF maintenance script - do NOT schedule this in cron.
//
// Brings follow-up data created before the one-case-per-customer rule into line with it:
//   1. a customer holding several open cases keeps the most recent one, the rest are
//      merged into it and soft-deleted;
//   2. the newest user record log entry that carries a follow-up date but no case link is
//      attached to that customer's case and current round.
//
// It reports what it would do and changes nothing unless it is told to apply:
//   CLI : php cron/one_off_customer_follow_up_backfill.php            (dry run)
//         php cron/one_off_customer_follow_up_backfill.php apply      (writes)
//   Web : /cron/one_off_customer_follow_up_backfill.php               (dry run)
//         /cron/one_off_customer_follow_up_backfill.php?apply=1       (writes)
//
// Run insert_table.php first: without customer_follow_up_round.superseded the script
// refuses to run rather than leaving cases half-merged.
//
// Running it again is safe. The first pass only sees customers who still hold several
// open cases, and the second only entries that are still unlinked, so a second run has
// nothing left to do.

include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/customer_follow_up_common.php';

$isCli = (php_sapi_name() === 'cli');

$apply = false;
if ($isCli) {
    $apply = isset($argv[1]) && strtolower(trim((string) $argv[1])) === 'apply';
} else {
    $apply = isset($_GET['apply']) && (string) $_GET['apply'] === '1';
}

$summary = customerFollowUpBackfillExistingData($connect, $finance_connect, array(
    'apply' => $apply,
));

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo ($apply ? "APPLIED" : "DRY RUN - nothing was changed") . "\n";
echo str_repeat('-', 60) . "\n";
echo "Customers holding duplicate open cases: " . (int) $summary['customers_with_duplicates'] . "\n";
echo "Cases merged:                           " . (int) $summary['cases_merged'] . "\n";
echo "Rounds marked superseded:               " . (int) $summary['rounds_superseded'] . "\n";
echo "User record log entries linked:         " . (int) $summary['log_entries_linked'] . "\n";

if (!empty($summary['actions'])) {
    echo "\nPlanned/applied actions:\n";
    foreach ($summary['actions'] as $action) {
        echo '  - ' . $action . "\n";
    }
}

if (!empty($summary['errors'])) {
    echo "\nErrors:\n";
    foreach ($summary['errors'] as $error) {
        echo '  ! ' . $error . "\n";
    }
}

if (!$apply) {
    echo "\nRe-run with " . ($isCli ? "'apply'" : "?apply=1") . " to make these changes.\n";
}
