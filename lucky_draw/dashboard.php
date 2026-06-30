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
$isLuckyDrawSuperAdmin = (int) USER_GROUP === 1;
$hiddenReadinessKeys = array(
    'table_' . LUCKY_DRAW_PRIZE,
    'table_' . LUCKY_DRAW_DRAW_LOG,
    'table_' . LUCKY_DRAW_VIRTUAL_WINNER,
    'table_' . LUCKY_DRAW_REQUEST_LOG,
    'facebook_order_request_engine',
    'identity_hashing',
    'recaptcha_keys',
);

$visibleReadinessItems = array();
foreach ((array) ($readiness['items'] ?? array()) as $readinessItem) {
    $readinessKey = isset($readinessItem['key']) ? (string) $readinessItem['key'] : '';

    if (!$isLuckyDrawSuperAdmin && in_array($readinessKey, $hiddenReadinessKeys, true)) {
        continue;
    }

    if ($readinessKey === 'urban_customer_source') {
        $readinessItem['label'] = 'URBANISM Member IC';
        $readinessItem['detail'] = preg_replace('/\s+with IC found\.$/i', ' found.', (string) ($readinessItem['detail'] ?? ''));
    }

    $visibleReadinessItems[] = $readinessItem;
}

$visibleReadinessSuccess = true;
foreach ($visibleReadinessItems as $readinessItem) {
    if (empty($readinessItem['success'])) {
        $visibleReadinessSuccess = false;
        break;
    }
}

$stats = array(
    'members' => luckyDrawUrbanRegisteredCount($connect),
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
        <small class="text-uppercase text-muted fw-bold">URBAN IC Rows</small>
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

<div class="lucky-draw-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Readiness</h4>
            <p class="text-muted mb-0">Public draw opens automatically when all checks pass.</p>
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
