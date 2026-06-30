<?php
$pageTitle = 'Lucky Draw - Prize Management';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';

$redirectPage = $SITEURL . '/lucky_draw/prizes.php';
$deleteRedirectPage = $SITEURL . '/lucky_draw/prizes_table.php';
$voucherImportPage = $SITEURL . '/import/lucky_draw_voucher_import.php';
$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'View', $pinAccess);

$canViewPrize = isActionAllowed('View', $pinAccess);
$canAddPrize = isActionAllowed('Add', $pinAccess);
$canEditPrize = isActionAllowed('Edit', $pinAccess);
$canDeletePrize = isActionAllowed('Delete', $pinAccess);
$canImportVouchers = isActionAllowed('Import', $pinAccess);
$canExportVouchers = isActionAllowed('Export', $pinAccess);
$showActionColumn = $canViewPrize || $canEditPrize || $canDeletePrize;

$checkboxValues = isset($_COOKIE['rowID']) ? (string) $_COOKIE['rowID'] : '';
if ($checkboxValues !== '') {
    $checkboxValues = preg_replace('/[^0-9,]/', '', $checkboxValues);
    $selectedIds = array_filter(array_map('intval', explode(',', $checkboxValues)), function ($value) {
        return $value > 0;
    });
    $checkboxValues = implode(',', $selectedIds);
} else {
    $selectedIds = array();
}

if ($checkboxValues !== '') {
    if (!$canExportVouchers) {
        setcookie('rowID', '', time() - 3600, '/');
        echo "<script>alert('You do not have permission to export voucher data.'); location.href='" . $SITEURL . "/lucky_draw/prizes_table.php';</script>";
        exit;
    }

    if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
        include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
    }

    $selectedPrizeRows = array();
    $userMap = array();
    $userResult = mysqli_query($connect, "SELECT id, name FROM `" . USR_USER . "` WHERE status = 'A'");
    if ($userResult) {
        while ($userRow = mysqli_fetch_assoc($userResult)) {
            $userMap[(int) ($userRow['id'] ?? 0)] = (string) ($userRow['name'] ?? '');
        }
    }

    $selectedPrizeResult = mysqli_query($connect, "SELECT id, prize_name, prize_type, voucher_code, weight, display_order, total_stock, price, create_by, create_date, create_time, update_by, update_date, update_time
        FROM `" . LUCKY_DRAW_PRIZE . "`
        WHERE status = 'A'
          AND prize_type = 'voucher'
        AND id IN (" . $checkboxValues . ")
        ORDER BY id ASC");
    if ($selectedPrizeResult) {
        while ($row = mysqli_fetch_assoc($selectedPrizeResult)) {
            $selectedPrizeRows[(int) $row['id']] = array(
                'id' => (int) $row['id'],
                'prize_name' => (string) ($row['prize_name'] ?? ''),
                'prize_type' => (string) ($row['prize_type'] ?? 'voucher'),
                'voucher_code' => (string) ($row['voucher_code'] ?? ''),
                'weight' => (string) ($row['weight'] ?? '1.0000'),
                'display_order' => (string) ($row['display_order'] ?? '0'),
                'total_stock' => (string) ($row['total_stock'] ?? '0'),
                'price' => (string) ($row['price'] ?? '0.00'),
                'create_by' => isset($userMap[(int) ($row['create_by'] ?? 0)]) ? $userMap[(int) ($row['create_by'] ?? 0)] : (string) ($row['create_by'] ?? ''),
                'create_date' => (string) ($row['create_date'] ?? ''),
                'create_time' => (string) ($row['create_time'] ?? ''),
                'update_by' => isset($userMap[(int) ($row['update_by'] ?? 0)]) ? $userMap[(int) ($row['update_by'] ?? 0)] : (string) ($row['update_by'] ?? ''),
                'update_date' => (string) ($row['update_date'] ?? ''),
                'update_time' => (string) ($row['update_time'] ?? ''),
            );
        }
    }

    if (empty($selectedPrizeRows)) {
        setcookie('rowID', '', time() - 3600, '/');
        echo "<script>alert('No voucher prize data found to export.'); location.href='" . $SITEURL . "/lucky_draw/prizes_table.php';</script>";
        exit;
    }

    $excelData = array();
    $excelData[] = array('S/N', 'PRIZE NAME', 'PRIZE TYPE', 'VOUCHER CODE', 'WEIGHT', 'DISPLAY ORDER', 'TOTAL STOCK', 'PRICE', 'CREATE BY', 'CREATE DATE', 'CREATE TIME', 'UPDATE BY', 'UPDATE DATE', 'UPDATE TIME');
    foreach ($selectedPrizeRows as $selectedPrize) {
        $excelData[] = array(
            (string) $selectedPrize['id'],
            (string) $selectedPrize['prize_name'],
            strtoupper((string) $selectedPrize['prize_type']),
            (string) $selectedPrize['voucher_code'],
            (string) $selectedPrize['weight'],
            (string) $selectedPrize['display_order'],
            (string) $selectedPrize['total_stock'],
            (string) $selectedPrize['price'],
            (string) $selectedPrize['create_by'],
            (string) $selectedPrize['create_date'],
            (string) $selectedPrize['create_time'],
            (string) $selectedPrize['update_by'],
            (string) $selectedPrize['update_date'],
            (string) $selectedPrize['update_time'],
        );
    }

    setcookie('rowID', '', time() - 3600, '/');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    luckyDrawInsertAdminLog($connect, 'export_vouchers', LUCKY_DRAW_PRIZE, 0, 'Exported voucher prize workbook for prize ID(s): ' . implode(', ', array_keys($selectedPrizeRows)), USER_ID, array(
        'page_title' => $pageTitle,
        'audit_action' => 'export',
        'entity_label' => 'prize',
    ));

    $filename = 'lucky_draw_voucher_prize_data_' . date('Y-m-d') . '.xlsx';
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData, 'Lucky Draw Voucher Prize');
    $xlsx->downloadAs($filename);
    exit;
}

$deleteRequested = post('act') === 'D' || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('delete_prize_id') !== '');
if ($deleteRequested) {
    luckyDrawRequireAdminAction($connect, 'Delete', $pinAccess);
    $deletePrizeId = 0;
    if (post('act') === 'D') {
        $deletePrizeId = (int) post('id');
    } elseif (post('delete_prize_id') !== '') {
        $deletePrizeId = (int) post('delete_prize_id');
    }
    $deleteSucceeded = false;
    if ($deletePrizeId > 0) {
        mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
            SET status = 'D',
                update_by = '" . mysqli_real_escape_string($connect, (string) USER_ID) . "',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . $deletePrizeId . "
              AND status = 'A'
            LIMIT 1");
        $deleteSucceeded = mysqli_affected_rows($connect) > 0;
        if ($deleteSucceeded) {
            luckyDrawInsertAdminLog($connect, 'delete_prize', LUCKY_DRAW_PRIZE, $deletePrizeId, 'Archived prize row', USER_ID, array(
                'page_title' => $pageTitle,
                'audit_action' => 'delete',
                'entity_label' => 'prize',
                'use_standard_crud_message' => true,
            ));
        }
    }

    if ($deleteSucceeded) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE, array(), 'success', 'Lucky Draw prize archived.');
    }

    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE, array(), 'error', 'Unable to archive this Lucky Draw prize.');
}

$flash = luckyDrawAdminFlashGet();
$prizeRows = luckyDrawFetchPrizeRows($connect, false);
$voucherAvailableCounts = luckyDrawVoucherAvailableCounts($connect);
$voucherSummary = luckyDrawVoucherStateCounts($connect);
include_once '../menuHeader.php';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<script src="<?= $SITEURL ?>/js/lucky_draw_prizes_table.js"></script>
<body>
    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p>
                            <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            <a href="<?= htmlspecialchars(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_DASHBOARD), ENT_QUOTES, 'UTF-8') ?>">Lucky Draw</a>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap gap-2">
                            <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="mt-auto mb-auto d-flex gap-2 flex-wrap">
                                <?php if ($canAddPrize) { ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= htmlspecialchars(siteUrlWithQuery(ROUTE_LUCKY_DRAW_ADMIN_PRIZES, array('act' => 'I')), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-plus"></i> Add Prize
                                    </a>
                                <?php } ?>
                                <?php if ($canImportVouchers) { ?>
                                    <a class="btn btn-sm btn-rounded btn-info text-white" id="addBtn" href="<?= htmlspecialchars($voucherImportPage, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-file-import"></i> Import Voucher
                                    </a>
                                <?php } ?>
                                <?php if ($canExportVouchers) { ?>
                                    <a class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" name="exportBtn" href="<?= htmlspecialchars(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-file-export"></i> Export Voucher
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>




                <?php if (empty($prizeRows)) { ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php } else { ?>
                    <table class="table table-striped" id="lucky_draw_prizes_table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <?php if ($canExportVouchers) { ?>
                                    <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                                <?php } ?>
                                <th scope="col">S/N</th>
                                <?php if ($showActionColumn) { ?>
                                    <th scope="col" id="action_col">Action</th>
                                <?php } ?>
                                <th scope="col" width="110px">Prize Image</th>
                                <th scope="col">Prize Name</th>
                                <th scope="col">Label Color</th>
                                <th scope="col">Type</th>
                                <th scope="col">Voucher Code</th>
                                <th scope="col">Weight</th>
                                <th scope="col">Availability</th>
                                <th scope="col">Reserved</th>
                                <th scope="col">Assigned</th>
                                <th scope="col">Display Order</th>
                                <th scope="col">Enabled Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $num = 1; ?>
                            <?php foreach ($prizeRows as $row) { ?>
                                <?php
                                $prizeId = (int) ($row['id'] ?? 0);
                                $prizeType = strtolower((string) ($row['prize_type'] ?? ''));
                                if ($prizeType === 'voucher') {
                                    $reservedCount = (int) ($voucherSummary[$prizeId]['reserved'] ?? 0);
                                    $assignedCount = (int) (($voucherSummary[$prizeId]['assigned'] ?? 0) + ($voucherSummary[$prizeId]['sent'] ?? 0));
                                } else {
                                    $reservedCount = (int) ($row['reserved_stock'] ?? 0);
                                    $assignedCount = (int) ($row['assigned_stock'] ?? 0);
                                }
                                $availableCount = luckyDrawPrizeAvailableUnits($row, (int) ($voucherAvailableCounts[$prizeId] ?? 0), $reservedCount, $assignedCount);
                                $imageUrl = luckyDrawPrizeImageUrl((string) ($row['prize_image'] ?? ''));
                                ?>
                                <tr>
                                    <th class="hideColumn ld-id-cell" scope="row"><?= $prizeId ?></th>
                                    <?php if ($canExportVouchers) { ?>
                                        <td class="text-center">
                                            <?php if ($prizeType === 'voucher') { ?>
                                                <input type="checkbox" class="export" value="<?= $prizeId ?>">
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                    <th scope="row" class="ld-sn-cell"><?= (int) $num++ ?></th>
                                    <?php if ($showActionColumn) { ?>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton('View', $redirectPage, $row, $pinAccess); ?>
                                            <?php renderViewEditButton('Edit', $redirectPage, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $prizeId, isset($row['prize_name']) ? $row['prize_name'] : '', isset($row['remark']) ? $row['remark'] : '', $pageTitle, $deleteRedirectPage, $deleteRedirectPage); ?>
                                        </td>
                                    <?php } ?>
                                    <td>
                                        <?php if ($imageUrl !== '') { ?>
                                            <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Prize Image" style="width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #e5e5e5;">
                                        <?php } else { ?>
                                            <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($row['prize_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?php if (!empty($row['label_color'])) { ?><input type="color" value="<?= htmlspecialchars((string) $row['label_color'], ENT_QUOTES, 'UTF-8') ?>" disabled><?php } ?></td>
                                    <td><?= htmlspecialchars(strtoupper((string) ($row['prize_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($prizeType === 'voucher' && trim((string) ($row['voucher_code'] ?? '')) !== '') { ?>
                                            <?= htmlspecialchars((string) ($row['voucher_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        <?php } else { ?>
                                            <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                    <td><?= htmlspecialchars(number_format((float) ($row['weight'] ?? 0), 4, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) $availableCount ?></td>
                                    <td><?= (int) $reservedCount ?></td>
                                    <td><?= (int) $assignedCount ?></td>
                                    <td><?= (int) ($row['display_order'] ?? 0) ?></td>
                                    <td><?= luckyDrawNormalizeFlag($row['is_enabled'] ?? 'N') === 'Y' ? 'Enabled' : 'Disabled' ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <?php if ($canExportVouchers) { ?>
                                    <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                                <?php } ?>
                                <th scope="col">S/N</th>
                                <?php if ($showActionColumn) { ?>
                                    <th scope="col" id="action_col">Action</th>
                                <?php } ?>
                                <th scope="col" width="110px">Prize Image</th>
                                <th scope="col">Prize Name</th>
                                <th scope="col">Label Color</th>
                                <th scope="col">Type</th>
                                <th scope="col">Voucher Code</th>
                                <th scope="col">Weight</th>
                                <th scope="col">Availability</th>
                                <th scope="col">Reserved</th>
                                <th scope="col">Assigned</th>
                                <th scope="col">Display Order</th>
                                <th scope="col">Enabled Status</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        function syncLuckyDrawPrizeSerialNumbers() {
            if (typeof window.jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
                return;
            }

            const tableElement = document.getElementById('lucky_draw_prizes_table');
            if (!tableElement || !jQuery.fn.DataTable.isDataTable(tableElement)) {
                return;
            }

            const tableApi = jQuery(tableElement).DataTable();
            const pageInfo = tableApi.page.info();
            let serialNumber = pageInfo.start + 1;

            tableApi.rows({ order: 'applied', search: 'applied', page: 'current' }).every(function () {
                const rowNode = this.node();
                if (!rowNode) {
                    return;
                }

                const serialCell = rowNode.querySelector('.ld-sn-cell');
                if (serialCell) {
                    serialCell.textContent = String(serialNumber);
                }

                serialNumber += 1;
            });
        }

        const page = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const action = " ";
        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        if (document.getElementById('lucky_draw_prizes_table')) {
            createSortingTable('lucky_draw_prizes_table', { searching: true });
            datatableAlignment('lucky_draw_prizes_table');
            if (typeof window.jQuery !== 'undefined') {
                jQuery(document).on('draw.dt', function (event, settings) {
                    const tableElement = document.getElementById('lucky_draw_prizes_table');
                    if (tableElement && settings && settings.nTable === tableElement) {
                        syncLuckyDrawPrizeSerialNumbers();
                    }
                });

                window.setTimeout(syncLuckyDrawPrizeSerialNumbers, 0);
            }
        }
        setButtonColor();
    </script>
</body>
</html>
