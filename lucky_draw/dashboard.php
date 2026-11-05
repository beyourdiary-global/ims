<?php
$pageTitle = 'Lucky Draw - Dashboard';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';
$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'View', $pinAccess);

$readiness = luckyDrawReadiness($connect, $finance_connect);

// The public draw is gated on the same resolver the wheel uses, so when it returns nothing the
// campaign is effectively closed. Surface that as an actionable restock list instead of leaving
// the operator to work it out from the readiness table.
$dashboardDrawPool = luckyDrawResolveDrawPool($connect, $finance_connect, luckyDrawFetchPrizeRows($connect, true));
$drawablePrizeCount = count($dashboardDrawPool['drawable']);
$restockPrizeRows = array();
foreach ($dashboardDrawPool['blocked'] as $blockedEntry) {
    if (($blockedEntry['reason'] ?? '') === 'warehouse_stock') {
        $restockPrizeRows[] = $blockedEntry;
    }
}

$isLuckyDrawSuperAdmin = (int) USER_GROUP === 1;
$hiddenReadinessKeys = array(
    'table_' . LUCKY_DRAW_PRIZE,
    'table_' . LUCKY_DRAW_DRAW_LOG,
    'table_' . LUCKY_DRAW_VIRTUAL_WINNER,
    'table_' . LUCKY_DRAW_REQUEST_LOG,
    'facebook_order_request_engine',
    'identity_hashing',
    'bot_protection',
);

$visibleReadinessItems = array();
foreach ((array) ($readiness['items'] ?? array()) as $readinessItem) {
    $readinessKey = isset($readinessItem['key']) ? (string) $readinessItem['key'] : '';

    if (!$isLuckyDrawSuperAdmin && in_array($readinessKey, $hiddenReadinessKeys, true)) {
        continue;
    }

    if ($readinessKey === 'customer_birthday_source') {
        $readinessItem['label'] = 'Customer Birthday Records';
        $readinessItem['detail'] = preg_replace('/^No customer birthday rows found\.$/', 'No customer birthday record found.', (string) ($readinessItem['detail'] ?? ''));
    }

    $visibleReadinessItems[] = $readinessItem;
}

// The public draw only depends on the overall readiness result. Individual prize
// warnings (for example a prize with zero stock) are shown below but never lock the page.
$visibleReadinessSuccess = !empty($readiness['success']);

$stats = array(
    'members' => luckyDrawCustomerBirthdayCount($connect),
    'prizes' => luckyDrawCountRows($connect, LUCKY_DRAW_PRIZE, "status = 'A'"),
    'draws' => luckyDrawCountRows($connect, LUCKY_DRAW_DRAW_LOG, "status = 'A'"),
    'pending_claims' => luckyDrawCountRows($connect, LUCKY_DRAW_DRAW_LOG, "status = 'A' AND claim_state = 'awaiting_claim'"),
    'pending_email' => luckyDrawCountRows($connect, LUCKY_DRAW_DRAW_LOG, "status = 'A' AND prize_type_snapshot = 'voucher' AND email_state IN ('pending', 'failed', 'sending')"),
    'virtual_rows' => luckyDrawCountRows($connect, LUCKY_DRAW_VIRTUAL_WINNER, "status = 'A'"),
);

$recentRows = array();
$recentSql = "SELECT redeem_reference, prize_name_snapshot, prize_type_snapshot, claim_state, email_state, facebook_order_request_id, create_date, create_time
    FROM `" . LUCKY_DRAW_DRAW_LOG . "`
    WHERE status = 'A'
    ORDER BY id DESC
    LIMIT 12";
$recentResult = mysqli_query($connect, $recentSql);
if ($recentResult) {
    while ($row = mysqli_fetch_assoc($recentResult)) {
        $recentRows[] = (array) $row;
    }
}

include_once '../menuHeader.php';
luckyDrawAdminRenderPageStart($pageTitle, 'dashboard');
?>
<div class="lucky-draw-admin-grid mb-4">
    <div class="lucky-draw-stat-card">
        <small class="text-uppercase text-muted fw-bold">Customers With Birthday</small>
        <h3><?= (int) $stats['members'] ?></h3>
    </div>
    <div class="lucky-draw-stat-card">
        <small class="text-uppercase text-muted fw-bold">Prize Rows</small>
        <h3><?= (int) $stats['prizes'] ?></h3>
    </div>
    <div class="lucky-draw-stat-card">
        <small class="text-uppercase text-muted fw-bold">Total Draws</small>
        <h3><?= (int) $stats['draws'] ?></h3>
    </div>
    <div class="lucky-draw-stat-card">
        <small class="text-uppercase text-muted fw-bold">Awaiting Claim</small>
        <h3><?= (int) $stats['pending_claims'] ?></h3>
    </div>
    <div class="lucky-draw-stat-card">
        <small class="text-uppercase text-muted fw-bold">Voucher Email Queue</small>
        <h3><?= (int) $stats['pending_email'] ?></h3>
    </div>
    <div class="lucky-draw-stat-card">
        <small class="text-uppercase text-muted fw-bold">Virtual Board Rows</small>
        <h3><?= (int) $stats['virtual_rows'] ?></h3>
    </div>
</div>

<?php if ($drawablePrizeCount <= 0 || !empty($restockPrizeRows)) { ?>
<div class="lucky-draw-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><?= $drawablePrizeCount <= 0 ? 'The draw is closed until a prize is restocked' : 'Some prizes cannot be shipped yet' ?></h4>
            <p class="text-muted mb-0">
                <?php if ($drawablePrizeCount <= 0) { ?>
                    No prize can be drawn right now, so visitors see the restock notice instead of the entry form. Add stock to at least one prize to reopen the draw.
                <?php } else { ?>
                    The draw still runs on the <?= (int) $drawablePrizeCount ?> prize(s) with stock. The prizes below are held out until their warehouse has stock.
                <?php } ?>
            </p>
        </div>
        <span class="badge bg-warning"><?= $drawablePrizeCount <= 0 ? 'Closed' : 'Restock needed' ?></span>
    </div>
    <?php if (!empty($restockPrizeRows)) { ?>
        <div class="table-responsive">
            <table class="table table-sm lucky-draw-admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Prize</th>
                        <th>Warehouse</th>
                        <th>Shortage</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($restockPrizeRows as $restockEntry) { ?>
                        <?php
                        $restockPrize = isset($restockEntry['prize']) && is_array($restockEntry['prize']) ? $restockEntry['prize'] : array();
                        $restockPrizeId = (int) ($restockPrize['id'] ?? 0);
                        $restockShortageParts = array();
                        foreach ((array) ($restockEntry['shortages'] ?? array()) as $restockShortage) {
                            if (!is_array($restockShortage)) {
                                continue;
                            }
                            $restockLabel = trim((string) ($restockShortage['product_label'] ?? ''));
                            if ($restockLabel === '') {
                                $restockLabel = 'Product #' . (int) ($restockShortage['product_id'] ?? 0);
                            }
                            $restockShortageParts[] = $restockLabel
                                . ' (need ' . (int) ($restockShortage['required_qty'] ?? 0)
                                . ', have ' . (int) ($restockShortage['available_qty'] ?? 0) . ')';
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars((string) ($restockPrize['prize_name'] ?? ('#' . $restockPrizeId)), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted">Campaign stock: <?= (int) ($restockEntry['available_units'] ?? 0) ?></small>
                            </td>
                            <td><?= htmlspecialchars(trim((string) ($restockEntry['warehouse_name'] ?? '')) !== '' ? (string) $restockEntry['warehouse_name'] : 'Not configured', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(!empty($restockShortageParts) ? implode('; ', $restockShortageParts) : (string) ($restockEntry['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($restockPrizeId > 0) { ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(siteUrlWithQuery(ROUTE_LUCKY_DRAW_ADMIN_PRIZES, array('id' => $restockPrizeId, 'act' => 'E')), ENT_QUOTES, 'UTF-8') ?>">Open prize</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<?php } ?>

<div class="lucky-draw-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Readiness</h4>
            <p class="text-muted mb-0">The public draw opens as soon as at least one prize has stock. Prizes with zero stock are treated as not existing.</p>
        </div>
        <span class="badge bg-<?= !empty($visibleReadinessSuccess) ? 'success' : 'warning' ?>"><?= !empty($visibleReadinessSuccess) ? 'Ready' : 'Needs Attention' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm lucky-draw-admin-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Item</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visibleReadinessItems as $item) { ?>
                    <tr>
                        <td><span class="badge bg-<?= !empty($item['success']) ? 'success' : 'danger' ?>"><?= !empty($item['success']) ? 'OK' : 'Issue' ?></span></td>
                        <td><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="lucky-draw-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Recent Draw Activity</h4>
            <p class="text-muted mb-0">Latest reservations, claims, and fulfillment records.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped w-100" id="recentActivityTable" style="width:100%">
            <thead>
                <tr>
                    <th scope="col">S/N</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Prize</th>
                    <th scope="col">Type</th>
                    <th scope="col">Claim</th>
                    <th scope="col">Email</th>
                    <th scope="col">Created Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRows as $index => $row) { ?>
                    <tr>
                        <td><?= (int) ($index + 1) ?></td>
                        <td><?= htmlspecialchars((string) $row['redeem_reference'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) $row['prize_name_snapshot'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(strtoupper((string) $row['prize_type_snapshot']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div><?= htmlspecialchars((string) $row['claim_state'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if ((int) $row['facebook_order_request_id'] > 0) { ?>
                                <small><a href="<?= htmlspecialchars(siteUrlWithQuery(ROUTE_FINANCE_FB_ORDER_REQ, array('id' => (int) $row['facebook_order_request_id'], 'act' => '')), ENT_QUOTES, 'UTF-8') ?>">FB Order #<?= (int) $row['facebook_order_request_id'] ?></a></small>
                            <?php } ?>
                        </td>
                        <td><?= htmlspecialchars((string) $row['email_state'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(trim((string) $row['create_date'] . ' ' . $row['create_time']), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col">S/N</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Prize</th>
                    <th scope="col">Type</th>
                    <th scope="col">Claim</th>
                    <th scope="col">Email</th>
                    <th scope="col">Created Date</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php luckyDrawAdminRenderPageEnd(); ?>
<script>
    const page = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const action = ' ';

    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    setButtonColor();

    $(document).ready(function () {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#recentActivityTable')) {
            $('#recentActivityTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[6, 'desc']],
                columnDefs: [
                    {
                        targets: 0,
                        orderable: false,
                        searchable: false
                    }
                ],
                language: {
                    emptyTable: 'No draw activity yet.'
                }
            });
        }
    });
</script>
</body>
</html>
