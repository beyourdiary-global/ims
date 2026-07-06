<?php
$currentPagePin = 162;
$pageTitle = 'Member Point';
$displayPageTitle = $pageTitle;
$disablePinGroupPageTitleSync = true;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

if (!function_exists('memberPointPageSanitizeReturnUrl')) {
    function memberPointPageSanitizeReturnUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $siteUrl = rtrim((string) SITEURL, '/');
        return strpos($url, $siteUrl . '/') === 0 ? $url : '';
    }
}

if (!function_exists('memberPointPageFormatDate')) {
    function memberPointPageFormatDate($date)
    {
        $date = trim((string) $date);
        if ($date === '') {
            return '-';
        }

        $timestamp = strtotime($date);
        return $timestamp !== false ? date('d M Y', $timestamp) : htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('memberPointPageFormatRatio')) {
    function memberPointPageFormatRatio($value)
    {
        $value = max(0, (float) $value);
        if (abs($value - round($value)) < 0.00001) {
            return number_format($value, 0, '.', '') . '%';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
    }
}

$pinGroupPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($pinGroupPageTitle !== '') {
    $pageTitle = $pinGroupPageTitle;
    $displayPageTitle = $pinGroupPageTitle;
}

$reportAccess = checkPinByGroupId($connect, $currentPagePin);
if (!isActionAllowed('View', $reportAccess)) {
    renderNotificationScript('You do not have permission to view Member Point.', 'error', $SITEURL . '/dashboard.php', 1200, true);
    exit;
}

$platform = memberPointNormalizePlatform(input('platform'));
$customerId = (int) input('customer_id');
$returnUrl = memberPointPageSanitizeReturnUrl(input('return_url'));

if ($platform === '' || $customerId <= 0) {
    renderNotificationScript('Invalid member point request.', 'error', ($returnUrl !== '' ? $returnUrl : $SITEURL . '/dashboard.php'), 1200, true);
    exit;
}

$snapshot = memberPointBuildCustomerSnapshot($connect, $finance_connect, $platform, $customerId, array(), array('sync_ledger' => true));
$platformConfigs = memberPointGetPlatformConfigs();
$platformConfig = isset($platformConfigs[$platform]) ? $platformConfigs[$platform] : array();

if (empty($snapshot['customer_row'])) {
    $fallbackUrl = $returnUrl !== '' ? $returnUrl : $SITEURL . '/dashboard.php';
    renderNotificationScript('Customer record not found for Member Point.', 'error', $fallbackUrl, 1200, true);
    exit;
}

if ($returnUrl === '' && !empty($platformConfig['record_url'])) {
    $returnUrl = rtrim((string) SITEURL, '/') . (string) $platformConfig['record_url'] . '?id=' . $customerId;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $safeCustomerLabel = htmlspecialchars((string) ($snapshot['customer_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => 'platform=' . $platform . '&customer_id=' . $customerId,
        'query_table' => MEMBER_POINT_LEDGER,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b> for <b>" . $safeCustomerLabel . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$summary = isset($snapshot['summary']) ? $snapshot['summary'] : array();
$rows = isset($snapshot['rows']) && is_array($snapshot['rows']) ? $snapshot['rows'] : array();
$transactionRows = memberPointFetchTransactionHistory($connect, $platform, $customerId);
$luckyDrawRows = luckyDrawFetchHistoryByMemberName($connect, isset($snapshot['customer_display_name']) ? (string) $snapshot['customer_display_name'] : '');
$customerLabel = isset($snapshot['customer_label']) ? (string) $snapshot['customer_label'] : ('Customer #' . $customerId);
$platformLabel = isset($platformConfig['label']) ? (string) $platformConfig['label'] : ucfirst($platform);
$memberTierLabel = isset($summary['member_tier']) ? (string) $summary['member_tier'] : 'Normal Member';
$memberTierKey = isset($summary['member_tier_key']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $summary['member_tier_key']) : 'normal';
$eligibleStartDate = memberPointGetStartDate();
$redeemableRows = memberRedeemGetEligibleRows($connect, (int) ($summary['active_points'] ?? 0), array(
    'marketplace_order_count' => (int) ($summary['marketplace_order_count'] ?? 0),
    'private_order_count' => (int) ($summary['private_order_count'] ?? 0),
));
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .member-point-card {
            border: 1px solid #e4e8ef;
            border-radius: 16px;
            box-shadow: 0 .5rem 1rem rgba(15, 23, 42, .05);
        }

        .member-point-kpi {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
        }

        .member-point-subtitle {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: #6b7280;
        }

        .member-point-tier-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .member-point-tier-normal {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #3730a3;
        }

        .member-point-tier-silver {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #475569;
        }

        .member-point-tier-gold {
            background: #fff7d6;
            border-color: #f6d36a;
            color: #9a6700;
        }

        .member-point-tier-vip {
            background: #fff1f2;
            border-color: #fda4af;
            color: #be123c;
        }
    </style>
</head>

<body>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p>
                <a href="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($platformLabel, ENT_QUOTES, 'UTF-8') ?></a>
                <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <div class="container pb-5">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-10">
                    <div class="mb-4">
                        <h2 class="mb-2"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="member-point-subtitle">
                            <span><?= htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <span>|</span>
                            <span><?= htmlspecialchars($platformLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <span>|</span>
                            <span class="member-point-tier-badge member-point-tier-<?= htmlspecialchars($memberTierKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($memberTierLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="text-muted small mt-2">Orders from <?= htmlspecialchars(memberPointPageFormatDate($eligibleStartDate), ENT_QUOTES, 'UTF-8') ?> onward are included. Shopee/Lazada marketplace orders stay as the Shopee/Lazada ratio frozen points, linked Facebook private orders use the private ratio only, and monthly tier bonus is released into private points by cron.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="member-point-card h-100 p-3 bg-white">
                                <div class="text-muted mb-2">Active Points</div>
                                <div class="member-point-kpi"><?= number_format((int) ($summary['active_points'] ?? 0)) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="member-point-card h-100 p-3 bg-white">
                                <div class="text-muted mb-2">Accumulated Points</div>
                                <div class="member-point-kpi"><?= number_format((int) ($summary['lifetime_points'] ?? 0)) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="member-point-card h-100 p-3 bg-white">
                                <div class="text-muted mb-2">Next Expiry</div>
                                <div class="member-point-kpi" style="font-size:24px;"><?= htmlspecialchars(memberPointPageFormatDate($summary['next_expiry'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="member-point-card h-100 p-3 bg-white">
                                <div class="text-muted mb-2">Eligible Orders</div>
                                <div class="member-point-kpi"><?= number_format((int) ($summary['order_count'] ?? 0)) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="member-point-card bg-white p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h5 class="mb-0">Customer Orders</h5>
                            <div class="text-muted small">Each row shows how many points came from that order or monthly bonus.</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0" id="member_point_orders_table">
                                <thead>
                                    <tr>
                                        <th width="60">S/N</th>
                                        <th width="150">Type</th>
                                        <th>Reference</th>
                                        <th width="130">Order Date</th>
                                        <th width="130">Amount</th>
                                        <th width="150">Rule</th>
                                        <th width="110">Points</th>
                                        <th width="130">Status</th>
                                        <th width="130">Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($rows)) { ?>
                                        <?php foreach ($rows as $index => $rowData) { ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars(($rowData['record_type'] ?? '') === 'monthly_bonus' ? 'Monthly Bonus' : 'Order Point', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <?php if (!empty($rowData['view_url']) && ($rowData['record_type'] ?? '') === 'order') { ?>
                                                        <a href="<?= htmlspecialchars((string) $rowData['view_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($rowData['reference_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a>
                                                    <?php } else { ?>
                                                        <?= htmlspecialchars((string) ($rowData['reference_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                                    <?php } ?>
                                                </td>
                                                <td><?= htmlspecialchars(memberPointPageFormatDate($rowData['order_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= commonFormatAmountRm($rowData['order_amount'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars((string) ($rowData['rule_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format((int) ($rowData['total_points'] ?? 0)) ?></td>
                                                <td><?= htmlspecialchars((string) ($rowData['point_status_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(memberPointPageFormatDate($rowData['expiry_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No eligible orders found from <?= htmlspecialchars(memberPointPageFormatDate($eligibleStartDate), ENT_QUOTES, 'UTF-8') ?> onward.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="member-point-card bg-white p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h5 class="mb-0">Point Transaction Record</h5>
                            <div class="text-muted small">Earned points and redeemed gifts share the same wallet history.</div>
                        </div>
                        <?php if (!empty($transactionRows)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered mb-0" id="member_point_transactions_table">
                                    <thead>
                                        <tr>
                                            <th width="60">S/N</th>
                                            <th width="120">Type</th>
                                            <th>Reference</th>
                                            <th width="180">Source</th>
                                            <th width="130">Date</th>
                                            <th width="120">Points</th>
                                            <th width="120">Status</th>
                                            <th width="130">Expiry Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactionRows as $transactionIndex => $transactionRow) { ?>
                                            <tr>
                                                <td><?= $transactionIndex + 1 ?></td>
                                                <td><?= htmlspecialchars((string) ($transactionRow['transaction_type_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <?php if (!empty($transactionRow['view_url'])) { ?>
                                                        <a href="<?= htmlspecialchars((string) $transactionRow['view_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($transactionRow['reference_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></a>
                                                    <?php } else { ?>
                                                        <?= htmlspecialchars((string) ($transactionRow['reference_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                                                    <?php } ?>
                                                </td>
                                                <td><?= htmlspecialchars((string) ($transactionRow['source_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(memberPointPageFormatDate($transactionRow['event_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format((int) ($transactionRow['points_change'] ?? 0)) ?></td>
                                                <td><?= htmlspecialchars((string) ($transactionRow['status_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(memberPointPageFormatDate($transactionRow['expiry_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="text-muted">No point transaction record yet.</div>
                        <?php } ?>
                    </div>

                    <div class="member-point-card bg-white p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h5 class="mb-0">Redeem Suggestion List</h5>
                            <div class="text-muted small">Only gifts that match the current active point balance and redeem order conditions are shown here.</div>
                        </div>
                        <?php if (!empty($redeemableRows)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered mb-0" id="member_point_redeem_table">
                                    <thead>
                                        <tr>
                                            <th width="70">S/N</th>
                                            <th width="110">Point Tier</th>
                                            <th>Redeemable Gift</th>
                                            <th width="130">Price</th>
                                            <th width="140">Selling Price</th>
                                            <th width="120">Cost Ratio</th>
                                            <th>Remark</th>
                                            <th width="250">Redeem Strategy</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($redeemableRows as $redeemIndex => $redeemRow) { ?>
                                            <tr>
                                                <td><?= $redeemIndex + 1 ?></td>
                                                <td><?= number_format((int) ($redeemRow['point_tier'] ?? 0)) ?></td>
                                                <td><?= htmlspecialchars((string) ($redeemRow['redeemable_gift'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= commonFormatAmountRm($redeemRow['price'] ?? 0) ?></td>
                                                <td><?= commonFormatAmountRm($redeemRow['selling_price'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars(memberPointPageFormatRatio($redeemRow['cost_ratio'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) ($redeemRow['remark'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars(memberRedeemBuildStrategyText($redeemRow), ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="text-muted">No Redeemable Gift</div>
                        <?php } ?>
                    </div>

                    <div class="member-point-card bg-white p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h5 class="mb-0">Lucky Draw History</h5>
                            <div class="text-muted small">Birthday Lucky Draw participation record for the matched Urbanism member.</div>
                        </div>
                        <?php if (!empty($luckyDrawRows)) { ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered mb-0" id="member_point_lucky_draw_table">
                                    <thead>
                                        <tr>
                                            <th width="60">S/N</th>
                                            <th width="160">Reference</th>
                                            <th>Prize</th>
                                            <th width="130">Claim</th>
                                            <th width="130">Email</th>
                                            <th width="170">Facebook Order</th>
                                            <th width="150">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($luckyDrawRows as $luckyDrawIndex => $luckyDrawRow) { ?>
                                            <tr>
                                                <td><?= $luckyDrawIndex + 1 ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($luckyDrawRow['redeem_reference'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars((string) ($luckyDrawRow['draw_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars((string) ($luckyDrawRow['prize_name_snapshot'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                                    <small class="text-muted text-uppercase"><?= htmlspecialchars((string) ($luckyDrawRow['prize_type_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars((string) ($luckyDrawRow['claim_state'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars((string) ($luckyDrawRow['email_state'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars((string) ($luckyDrawRow['claim_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                    <?php if (trim((string) ($luckyDrawRow['failure_message'] ?? '')) !== '') { ?>
                                                        <div><small class="text-danger"><?= htmlspecialchars((string) $luckyDrawRow['failure_message'], ENT_QUOTES, 'UTF-8') ?></small></div>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($luckyDrawRow['view_url']) && (int) ($luckyDrawRow['facebook_order_request_id'] ?? 0) > 0) { ?>
                                                        <a href="<?= htmlspecialchars((string) $luckyDrawRow['view_url'], ENT_QUOTES, 'UTF-8') ?>">FB Order #<?= (int) $luckyDrawRow['facebook_order_request_id'] ?></a>
                                                    <?php } else { ?>
                                                        -
                                                    <?php } ?>
                                                </td>
                                                <td><?= htmlspecialchars(memberPointPageFormatDate($luckyDrawRow['create_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="text-muted">No Result</div>
                        <?php } ?>
                    </div>

                    <div class="d-flex justify-content-center">
                        <button type="button" class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="actionBtn" id="actionBtn" value="back"
                            onclick="window.location.href = <?= htmlspecialchars(json_encode($returnUrl), ENT_QUOTES, 'UTF-8') ?>;">Back</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function initMemberPointTable(tableId) {
                var tableElement = document.getElementById(tableId);
                if (!tableElement) {
                    return;
                }

                var rowCount = getValidDataTableRowCount(tableElement);
                if (rowCount === 0) {
                    return;
                }

                var enableControls = rowCount > 10;
                new DataTable('#' + tableId, {
                    paging: enableControls,
                    info: enableControls,
                    searching: enableControls,
                    ordering: true,
                    lengthChange: enableControls,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    autoWidth: false,
                    order: []
                });
                datatableAlignment(tableId);
            }

            initMemberPointTable('member_point_orders_table');
            initMemberPointTable('member_point_transactions_table');
            initMemberPointTable('member_point_redeem_table');
            initMemberPointTable('member_point_lucky_draw_table');
        })();
    </script>
</body>

</html>
