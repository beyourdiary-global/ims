<?php
ob_start();
$pageTitle = 'Lucky Draw - Logs';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';

$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'View', $pinAccess);
$canExportLogs = isActionAllowed('Export', $pinAccess);
$canResendEmail = isActionAllowed('Edit', $pinAccess);

$libPath = ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
if (is_readable($libPath)) {
    require_once $libPath;
}

$checkboxValues = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';
if (!empty($checkboxValues)) {
    $checkboxValues = preg_replace('/[^0-9,]/', '', (string) $checkboxValues);
    $ids = array_filter(explode(',', $checkboxValues), 'strlen');
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($value) {
        return $value > 0;
    });
    $checkboxValues = implode(',', $ids);
}

if (!empty($checkboxValues)) {
    if (!isActionAllowed('Export', $pinAccess)) {
        setcookie('rowID', '', time() - 3600, '/');
        ob_end_clean();
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/lucky_draw/logs.php';</script>";
        exit;
    }

    $excelData = array();
    $excelData[] = array(
        'S/N',
        'Reference',
        'Prize',
        'Type',
        'Draw State',
        'Claim State',
        'Email State',
        'Claim Email',
        'FB Order ID',
        'Sent At',
        'Created At',
        'Failure Message',
    );

    $exportResult = mysqli_query($connect, "SELECT id, redeem_reference, prize_name_snapshot, prize_type_snapshot, draw_state, claim_state, email_state, claim_email, facebook_order_request_id, sent_at, create_date, create_time, failure_message
        FROM `" . LUCKY_DRAW_DRAW_LOG . "`
        WHERE status = 'A'
          AND id IN (" . $checkboxValues . ")
        ORDER BY id ASC");
    if ($exportResult) {
        $serial = 1;
        while ($row = mysqli_fetch_assoc($exportResult)) {
            $excelData[] = array(
                $serial++,
                isset($row['redeem_reference']) ? (string) $row['redeem_reference'] : '',
                isset($row['prize_name_snapshot']) ? (string) $row['prize_name_snapshot'] : '',
                isset($row['prize_type_snapshot']) ? strtoupper((string) $row['prize_type_snapshot']) : '',
                isset($row['draw_state']) ? (string) $row['draw_state'] : '',
                isset($row['claim_state']) ? (string) $row['claim_state'] : '',
                isset($row['email_state']) ? (string) $row['email_state'] : '',
                isset($row['claim_email']) ? (string) $row['claim_email'] : '',
                isset($row['facebook_order_request_id']) ? (string) $row['facebook_order_request_id'] : '',
                isset($row['sent_at']) ? (string) $row['sent_at'] : '',
                trim((string) ($row['create_date'] ?? '') . ' ' . ($row['create_time'] ?? '')),
                isset($row['failure_message']) ? (string) $row['failure_message'] : '',
            );
        }
    }

    setcookie('rowID', '', time() - 3600, '/');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (count($excelData) <= 1) {
        echo "<script>alert('No selected Lucky Draw log data found to export.');window.location.href='logs.php';</script>";
        exit;
    }

    if (!class_exists('\CodexWorld\PhpXlsxGenerator')) {
        echo "<script>alert('The export library is not available. Please contact the administrator.');window.location.href='logs.php';</script>";
        exit;
    }

    luckyDrawInsertAdminLog($connect, 'export_logs', LUCKY_DRAW_DRAW_LOG, 0, 'Exported ' . (count($excelData) - 1) . ' Lucky Draw log row(s)', USER_ID, array(
        'page_title' => $pageTitle,
    ));

    $filename = 'lucky_draw_logs_' . date('Y-m-d') . '.xlsx';
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('resend_draw_log_id') !== '') {
    luckyDrawRequireAdminAction($connect, 'Edit', $pinAccess);
    $drawLogId = (int) post('resend_draw_log_id');
    if ($drawLogId > 0) {
        $resendResult = luckyDrawResendVoucherEmailNow($connect, $finance_connect, $drawLogId, (string) USER_ID);
        luckyDrawInsertAdminLog($connect, 'resend_email', LUCKY_DRAW_DRAW_LOG, $drawLogId, (string) ($resendResult['message'] ?? 'Voucher email resend attempted.'), USER_ID, array(
            'page_title' => $pageTitle,
            'audit_action' => 'edit',
            'act_msg' => !empty($resendResult['success'])
                ? ('Admin User resent voucher email for draw log [ <b>ID = ' . $drawLogId . ' </b> ] under <b><i>' . LUCKY_DRAW_DRAW_LOG . ' Table</i></b>.')
                : ('Admin User attempted to resend voucher email for draw log [ <b>ID = ' . $drawLogId . ' </b> ] under <b><i>' . LUCKY_DRAW_DRAW_LOG . ' Table</i></b>.'),
        ));
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_LOGS, array(), !empty($resendResult['success']) ? 'success' : 'error', isset($resendResult['message']) ? (string) $resendResult['message'] : 'Unable to resend the voucher email.');
    }
    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_LOGS, array(), 'error', 'Unable to resend the voucher email.');
}

$drawRows = array();
$requestRows = array();

$drawSql = "SELECT * FROM `" . LUCKY_DRAW_DRAW_LOG . "`
    WHERE status = 'A'
    ORDER BY id DESC
    LIMIT 250";
$drawResult = mysqli_query($connect, $drawSql);
if ($drawResult) {
    while ($row = mysqli_fetch_assoc($drawResult)) {
        $drawRows[] = (array) $row;
    }
}

$requestResult = mysqli_query($connect, "SELECT request_type, request_state, created_at FROM `" . LUCKY_DRAW_REQUEST_LOG . "` WHERE status = 'A' ORDER BY id DESC LIMIT 250");
if ($requestResult) {
    while ($row = mysqli_fetch_assoc($requestResult)) {
        $requestRows[] = (array) $row;
    }
}

include_once '../menuHeader.php';
luckyDrawAdminRenderPageStart($pageTitle, 'logs');
?>
<div class="row g-4">
    <div class="col-12">
        <div class="lucky-draw-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h4 class="mb-1">Participation & Fulfillment Logs</h4>
                    <p class="text-muted mb-0">Showing the latest 250 draw records.</p>
                </div>
                <?php if ($canExportLogs && !empty($drawRows)) { ?>
                    <a class="btn btn-sm btn-rounded btn-success text-white" id="exportBtn" name="exportBtn" href="logs.php">
                        <i class="fa-solid fa-file-export"></i> Export
                    </a>
                <?php } ?>
            </div>
            <?php if (empty($drawRows)) { ?>
                <div class="text-center py-4"><h4>No Result!</h4></div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-striped lucky-draw-admin-table align-middle mb-0" id="table" style="width:100%">
                        <thead>
                            <tr>
                                <th class="hideColumn">ID</th>
                                <?php if ($canExportLogs) { ?>
                                    <th class="text-center"><input type="checkbox" class="exportAll"></th>
                                <?php } ?>
                                <th>S/N</th>
                                <?php if ($canResendEmail) { ?>
                                    <th>Action</th>
                                <?php } ?>
                                <th>Reference</th>
                                <th>Prize</th>
                                <th>Claim</th>
                                <th>Email</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $num = 1; ?>
                            <?php foreach ($drawRows as $row) { ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= (int) ($row['id'] ?? 0) ?></th>
                                    <?php if ($canExportLogs) { ?>
                                        <td class="text-center"><input type="checkbox" class="export" value="<?= (int) ($row['id'] ?? 0) ?>"></td>
                                    <?php } ?>
                                    <th scope="row"><?= (int) $num++ ?></th>
                                    <?php if ($canResendEmail) { ?>
                                        <td class="btn-container">
                                            <?php if (
                                                strtolower((string) ($row['prize_type_snapshot'] ?? '')) === 'voucher'
                                                && strtolower((string) ($row['claim_state'] ?? '')) === 'claimed'
                                                && trim((string) ($row['claim_email'] ?? '')) !== ''
                                            ) { ?>
                                                <form method="post">
                                                    <input type="hidden" name="resend_draw_log_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Resend Email</button>
                                                </form>
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($row['redeem_reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars((string) ($row['draw_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars((string) ($row['prize_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <small class="text-muted text-uppercase"><?= htmlspecialchars((string) ($row['prize_type_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars((string) ($row['claim_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if ((int) ($row['facebook_order_request_id'] ?? 0) > 0) { ?>
                                            <small><a href="<?= htmlspecialchars(siteUrlWithQuery(ROUTE_FINANCE_FB_ORDER_REQ, array('id' => (int) $row['facebook_order_request_id'])), ENT_QUOTES, 'UTF-8') ?>">FB Order #<?= (int) $row['facebook_order_request_id'] ?></a></small>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars((string) ($row['email_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars((string) ($row['claim_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php if (trim((string) ($row['failure_message'] ?? '')) !== '') { ?>
                                            <div><small class="text-danger"><?= htmlspecialchars((string) $row['failure_message'], ENT_QUOTES, 'UTF-8') ?></small></div>
                                        <?php } ?>
                                    </td>
                                    <td><?= htmlspecialchars(trim((string) ($row['create_date'] ?? '') . ' ' . ($row['create_time'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn">ID</th>
                                <?php if ($canExportLogs) { ?>
                                    <th class="text-center"><input type="checkbox" class="exportAll"></th>
                                <?php } ?>
                                <th>S/N</th>
                                <?php if ($canResendEmail) { ?>
                                    <th>Action</th>
                                <?php } ?>
                                <th>Reference</th>
                                <th>Prize</th>
                                <th>Claim</th>
                                <th>Email</th>
                                <th>Created</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="col-12">
        <div class="lucky-draw-card h-100">
            <h4 class="mb-3">Recent Request Throttle Log</h4>
            <div class="table-responsive">
                <table class="table table-striped lucky-draw-admin-table align-middle mb-0" id="requestThrottleTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Request Type</th>
                            <th>State</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $requestNum = 1; ?>
                        <?php foreach ($requestRows as $row) { ?>
                            <tr>
                                <th scope="row"><?= (int) $requestNum++ ?></th>
                                <td><?= htmlspecialchars((string) ($row['request_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['request_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>S/N</th>
                            <th>Request Type</th>
                            <th>State</th>
                            <th>Created At</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<?php luckyDrawAdminRenderPageEnd(); ?>
<script src="<?= $SITEURL ?>/js/list_page_common.js"></script>
<script src="<?= $SITEURL ?>/js/lucky_draw_logs.js"></script>
<script>
    const page = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const action = ' ';

    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    if (document.getElementById('table')) {
        datatableAlignment('table');
    }

    if (document.getElementById('requestThrottleTable') && typeof $.fn.DataTable === 'function') {
        $('#requestThrottleTable').DataTable({
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, 'All']
            ],
            order: [[3, 'desc']],
            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    searchable: false
                }
            ]
        });
    }

    setButtonColor();
</script>
</body>
</html>
