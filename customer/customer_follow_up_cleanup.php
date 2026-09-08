<?php
// Review page for follow-up data created before the one-case-per-customer rule.
//
// Nothing here is automatic: it lists what looks inconsistent, and only what the reviewer
// confirms is changed. A confirmed merge leaves the customer with a single case, which is
// what then shows on the Customer Follow-Up page.

$currentPagePin = 151;
$pageTitle = 'Follow-Up Cleanup';
$displayPageTitle = 'Follow-Up Cleanup';
$disablePinGroupPageTitleSync = true;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/customer_follow_up_common.php';

$pageAccess = checkPinByGroupId($connect, $currentPagePin);
// Merging rewrites other people's cases, so this page is admin-only rather than following
// the follow-up list's per-assignee rules.
$canUseCleanup = isActionAllowed('Edit', $pageAccess) && customerFollowUpIsAdminUser(defined('USER_GROUP') ? USER_GROUP : null);
if (!$canUseCleanup) {
    renderNotificationScript('You do not have permission to use Follow-Up Cleanup.', 'error', 'customer_follow_up_list.php', 1200, true);
    exit;
}

if (empty($_SESSION['customer_follow_up_cleanup_csrf'])) {
    $_SESSION['customer_follow_up_cleanup_csrf'] = bin2hex(random_bytes(32));
}

$statusMessage = '';
$statusType = 'success';
$schemaReady = customerFollowUpSupportsRoundSuperseded($connect);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedToken = (string) post('cleanup_csrf');
    if (!hash_equals((string) $_SESSION['customer_follow_up_cleanup_csrf'], $postedToken)) {
        $statusType = 'error';
        $statusMessage = 'Invalid session token. Please reload the page and try again.';
    } else if (!$schemaReady) {
        $statusType = 'error';
        $statusMessage = 'customer_follow_up_round.superseded is missing. Run insert_table.php first.';
    } else {
        $cleanupAction = trim((string) post('cleanup_action'));

        if ($cleanupAction === 'merge_cases') {
            $platform = customerFollowUpNormalizePlatform(post('platform'));
            $customerId = (int) post('customer_id');
            $survivorId = (int) post('keep_follow_up_id');

            if ($survivorId <= 0) {
                $statusType = 'error';
                $statusMessage = 'Choose which follow-up case to keep before confirming.';
            } else {
                $mergeResult = customerFollowUpMergeCasesIntoSurvivor($connect, $platform, $customerId, $survivorId, (int) USER_ID);
                if (!empty($mergeResult['success'])) {
                    $statusMessage = 'Kept case #' . $survivorId . ' and merged ' . (int) $mergeResult['cases_merged'] . ' other case(s) into it.';
                } else {
                    $statusType = 'error';
                    $statusMessage = trim((string) $mergeResult['message']) !== '' ? $mergeResult['message'] : 'Unable to merge these follow-up cases.';
                }
            }
        } else if ($cleanupAction === 'link_log') {
            $logId = (int) post('log_id');
            $caseId = (int) post('follow_up_id');
            $roundId = (int) post('round_id');
            $roundNo = (int) post('round_no');

            if (customerFollowUpLinkUserRecordLogToCase($connect, $logId, $caseId, $roundId, $roundNo, (int) USER_ID)) {
                $statusMessage = 'Linked user record log #' . $logId . ' to follow-up case #' . $caseId . '.';
            } else {
                $statusType = 'error';
                $statusMessage = 'Unable to link that user record log entry.';
            }
        }
    }
}

$duplicateGroups = $schemaReady ? customerFollowUpFindDuplicateCaseGroups($connect) : array();
$unlinkedLogs = $schemaReady ? customerFollowUpFindUnlinkedFollowUpLogs($connect) : array();

$customerLabelMapByPlatform = array();
foreach ($duplicateGroups as $group) {
    $customerLabelMapByPlatform[$group['platform']][$group['customer_id']] = $group['customer_id'];
}

$h = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .cleanup-card {
            border: 1px solid #e3e9f2;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fff;
        }

        .cleanup-card-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .cleanup-choice {
            display: block;
            border: 1px solid #e3e9f2;
            border-radius: 6px;
            padding: 0.65rem 0.85rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }

        .cleanup-choice:hover {
            border-color: #6c8ebf;
            background: #f7faff;
        }

        .cleanup-choice input {
            margin-right: 0.5rem;
        }

        .cleanup-choice-meta {
            color: #6b7688;
            font-size: 0.88rem;
            margin-top: 0.2rem;
            margin-left: 1.5rem;
        }

        .cleanup-badge-keep {
            background: #e4f6ea;
            color: #1f6f43;
            border-radius: 4px;
            padding: 0.05rem 0.4rem;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .cleanup-empty {
            color: #6b7688;
            padding: 0.75rem 0;
        }

        .cleanup-note {
            color: #6b7688;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11 py-3">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p>
                        <a href="<?= $h($SITEURL) ?>/dashboard.php">Dashboard</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <a href="customer_follow_up_list.php">Customer Follow-Up</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <?= $h($displayPageTitle) ?>
                    </p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                        <div>
                            <h2 class="mb-1"><?= $h($displayPageTitle) ?></h2>
                            <div class="cleanup-note">
                                Follow-ups created before one case per customer. Nothing changes until you confirm,
                                and what you confirm is what shows on the Customer Follow-Up page.
                            </div>
                        </div>
                        <a class="btn btn-sm btn-rounded btn-primary" href="customer_follow_up_list.php">
                            <i class="fa-solid fa-list"></i> Customer Follow-Up
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!$schemaReady) { ?>
                <div class="alert alert-warning">
                    <strong>Run insert_table.php first.</strong>
                    <code>customer_follow_up_round.superseded</code> is missing, so cases cannot be merged safely yet.
                </div>
            <?php } ?>

            <div class="cleanup-card">
                <div class="cleanup-card-title">
                    Customers with more than one open follow-up case
                    <span class="badge bg-secondary"><?= count($duplicateGroups) ?></span>
                </div>

                <?php if (empty($duplicateGroups)) { ?>
                    <div class="cleanup-empty">Nothing to review. Every customer already has a single open case.</div>
                <?php } else { ?>
                    <div class="cleanup-note mb-3">
                        Choose the case to keep. The others are closed and recorded on the one you keep, which then
                        carries the furthest round any of them reached.
                    </div>

                    <?php foreach ($duplicateGroups as $group) {
                        $groupKey = $group['platform'] . '_' . $group['customer_id'];
                        $firstCase = $group['cases'][0];
                        $customerLabel = trim((string) (isset($firstCase['customer_username']) ? $firstCase['customer_username'] : ''));
                        if ($customerLabel === '') {
                            $customerLabel = trim((string) (isset($firstCase['customer_name']) ? $firstCase['customer_name'] : ''));
                        }
                        if ($customerLabel === '') {
                            $customerLabel = 'Customer #' . $group['customer_id'];
                        }
                        ?>
                        <form method="post" class="cleanup-card">
                            <input type="hidden" name="cleanup_csrf" value="<?= $h($_SESSION['customer_follow_up_cleanup_csrf']) ?>">
                            <input type="hidden" name="cleanup_action" value="merge_cases">
                            <input type="hidden" name="platform" value="<?= $h($group['platform']) ?>">
                            <input type="hidden" name="customer_id" value="<?= (int) $group['customer_id'] ?>">

                            <div class="cleanup-card-title">
                                <?= $h($customerLabel) ?>
                                <span class="text-muted">(<?= $h(ucfirst($group['platform'])) ?> &middot; <?= count($group['cases']) ?> open cases)</span>
                            </div>

                            <?php foreach ($group['cases'] as $caseIndex => $caseRow) {
                                $caseId = (int) $caseRow['id'];
                                $orderLabel = trim((string) (isset($caseRow['order_no']) ? $caseRow['order_no'] : ''));
                                if ($orderLabel === '') {
                                    $orderLabel = customerFollowUpIsOrderlessCase($caseRow) ? 'No order (customer follow-up)' : ('#' . (int) $caseRow['order_id']);
                                }
                                $roundRow = isset($caseRow['current_round']) ? $caseRow['current_round'] : array();
                                $nextDate = trim((string) (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : ''));
                                $assignedLabel = customerFollowUpGetUserDisplayName($connect, (int) $caseRow['assigned_user_id']);
                                ?>
                                <label class="cleanup-choice">
                                    <input type="radio" name="keep_follow_up_id" value="<?= $caseId ?>" <?= $caseIndex === 0 ? 'checked' : '' ?>>
                                    <strong>Case #<?= $caseId ?></strong>
                                    &middot; Order <?= $h($orderLabel) ?>
                                    <?php if ($caseIndex === 0) { ?>
                                        <span class="cleanup-badge-keep">most recent</span>
                                    <?php } ?>
                                    <div class="cleanup-choice-meta">
                                        Received <?= $h(trim((string) $caseRow['received_date']) !== '' ? $caseRow['received_date'] : '-') ?>
                                        &middot; Round <?= (int) $caseRow['current_round_no'] ?>
                                        &middot; Next follow-up <?= $h($nextDate !== '' ? $nextDate : '-') ?>
                                        &middot; Assigned <?= $h($assignedLabel !== '' ? $assignedLabel : '-') ?>
                                    </div>
                                </label>
                            <?php } ?>

                            <button class="btn btn-sm btn-rounded btn-primary mt-2" type="submit"
                                    onclick="return confirm('Keep the selected case and close the others for this customer?');"
                                    <?= $schemaReady ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-check"></i> Confirm &amp; Merge
                            </button>
                        </form>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="cleanup-card">
                <div class="cleanup-card-title">
                    Follow-up entries not yet linked to a case
                    <span class="badge bg-secondary"><?= count($unlinkedLogs) ?></span>
                </div>

                <?php if (empty($unlinkedLogs)) { ?>
                    <div class="cleanup-empty">Nothing to review. Every follow-up entry is already linked.</div>
                <?php } else { ?>
                    <div class="cleanup-note mb-3">
                        These entries carry a follow-up date but no case, so editing one adds another row instead of
                        updating it. Linking an entry lets a later edit reuse it.
                    </div>

                    <?php foreach ($unlinkedLogs as $item) {
                        $logRow = $item['log'];
                        $caseRow = $item['case'];
                        $customerLabel = trim((string) (isset($caseRow['customer_username']) ? $caseRow['customer_username'] : ''));
                        if ($customerLabel === '') {
                            $customerLabel = 'Customer #' . (int) $item['customer_id'];
                        }
                        $logExcerpt = function_exists('urlGetUserRecordLogContentPlainText')
                            ? urlGetUserRecordLogContentPlainText((string) $logRow['content'])
                            : strip_tags((string) $logRow['content']);
                        $logExcerpt = trim(preg_replace('/\s+/', ' ', (string) $logExcerpt));
                        if (function_exists('mb_substr') && mb_strlen($logExcerpt, 'UTF-8') > 90) {
                            $logExcerpt = mb_substr($logExcerpt, 0, 90, 'UTF-8') . '...';
                        }
                        ?>
                        <form method="post" class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                            <input type="hidden" name="cleanup_csrf" value="<?= $h($_SESSION['customer_follow_up_cleanup_csrf']) ?>">
                            <input type="hidden" name="cleanup_action" value="link_log">
                            <input type="hidden" name="log_id" value="<?= (int) $logRow['id'] ?>">
                            <input type="hidden" name="follow_up_id" value="<?= (int) $caseRow['id'] ?>">
                            <input type="hidden" name="round_id" value="<?= (int) $item['round']['id'] ?>">
                            <input type="hidden" name="round_no" value="<?= (int) $caseRow['current_round_no'] ?>">

                            <div class="flex-grow-1">
                                <strong><?= $h($customerLabel) ?></strong>
                                <span class="text-muted">(<?= $h(ucfirst($item['platform'])) ?>)</span>
                                <div class="cleanup-choice-meta" style="margin-left:0;">
                                    Entry #<?= (int) $logRow['id'] ?> &middot; <?= $h($logExcerpt !== '' ? $logExcerpt : '(no message)') ?>
                                    <br>
                                    Follow-up date <?= $h($logRow['next_follow_up_date']) ?>
                                    &rarr; link to case #<?= (int) $caseRow['id'] ?> round <?= (int) $caseRow['current_round_no'] ?>
                                </div>
                            </div>

                            <button class="btn btn-sm btn-rounded btn-primary" type="submit" <?= $schemaReady ? '' : 'disabled' ?>>
                                <i class="fa-solid fa-link"></i> Confirm &amp; Link
                            </button>
                        </form>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        (function () {
            <?php if ($statusMessage !== '') { ?>
            if (typeof showNotification === 'function') {
                showNotification(<?= json_encode($statusMessage) ?>, <?= json_encode($statusType) ?>);
            } else {
                window.alert(<?= json_encode($statusMessage) ?>);
            }
            <?php } ?>
        })();
    </script>
</body>
</html>
