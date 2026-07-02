<?php
$pageTitle = 'Lucky Draw - Virtual Board';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';

$redirectPage = $SITEURL . '/lucky_draw/virtual_board.php';
$deleteRedirectPage = $SITEURL . '/lucky_draw/virtual_board_table.php';
$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'View', $pinAccess);

$canViewRow = isActionAllowed('View', $pinAccess);
$canAddRow = isActionAllowed('Add', $pinAccess);
$canEditRow = isActionAllowed('Edit', $pinAccess);
$canDeleteRow = isActionAllowed('Delete', $pinAccess);
$showActionColumn = $canViewRow || $canEditRow || $canDeleteRow;
$canBulkEnable = $canEditRow;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('enable_selected') !== '') {
    luckyDrawRequireAdminAction($connect, 'Edit', $pinAccess);
    $selectedRowIds = (array) post('selected_row_ids') ?: array();
    $selectedRowIds = array_values(array_unique(array_filter(array_map('intval', $selectedRowIds), function ($value) {
            return $value > 0;
        })));

    if (empty($selectedRowIds)) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(
            'result_act' => 'ErrMO',
            'result_message' => 'Please select at least one disabled virtual board row.',
        ));
    }

    $idListSql = implode(',', $selectedRowIds);
    $rowsToEnable = array();
    $enableResult = mysqli_query($connect, "SELECT id, display_prize
        FROM `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
        WHERE status = 'A'
          AND is_enabled = 'N'
          AND id IN (" . $idListSql . ")");
    if ($enableResult) {
        while ($enableRow = mysqli_fetch_assoc($enableResult)) {
            $rowsToEnable[] = (array) $enableRow;
        }
    }

    if (empty($rowsToEnable)) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(
            'result_act' => 'NC',
            'result_message' => 'Selected virtual board rows are already enabled.',
        ));
    }

    mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
        SET is_enabled = 'Y',
            update_by = '" . mysqli_real_escape_string($connect, (string) USER_ID) . "',
            update_date = CURDATE(),
            update_time = CURTIME()
        WHERE status = 'A'
          AND is_enabled = 'N'
          AND id IN (" . $idListSql . ")");

    foreach ($rowsToEnable as $enableRow) {
        luckyDrawInsertAdminLog($connect, 'enable_virtual_board', LUCKY_DRAW_VIRTUAL_WINNER, (int) ($enableRow['id'] ?? 0), (string) ($enableRow['display_prize'] ?? ''), USER_ID, array(
            'page_title' => $pageTitle,
            'audit_action' => 'edit',
            'entity_label' => 'virtual board',
            'use_standard_crud_message' => true,
        ));
    }

    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(
        'result_act' => 'PC',
        'result_message' => count($rowsToEnable) . ' virtual board row(s) enabled.',
    ));
}

$deleteRequested = post('act') === 'D' || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('delete_board_row_id') !== '');
if ($deleteRequested) {
    luckyDrawRequireAdminAction($connect, 'Delete', $pinAccess);
    $boardRowId = 0;
    if (post('act') === 'D') {
        $boardRowId = (int) post('id');
    } elseif (post('delete_board_row_id') !== '') {
        $boardRowId = (int) post('delete_board_row_id');
    }
    $deleteSucceeded = false;
    if ($boardRowId > 0) {
        mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
            SET status = 'D',
                update_by = '" . mysqli_real_escape_string($connect, (string) USER_ID) . "',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . $boardRowId . "
              AND status = 'A'
            LIMIT 1");
        $deleteSucceeded = mysqli_affected_rows($connect) > 0;
        if ($deleteSucceeded) {
            luckyDrawInsertAdminLog($connect, 'delete_virtual_board', LUCKY_DRAW_VIRTUAL_WINNER, $boardRowId, 'Archived virtual board row', USER_ID, array(
                'page_title' => $pageTitle,
                'audit_action' => 'delete',
                'entity_label' => 'virtual board',
                'use_standard_crud_message' => true,
            ));
        }
    }

    if ($deleteSucceeded) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(), 'success', 'Virtual board row archived.');
    }

    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(), 'error', 'Unable to archive this virtual board row.');
}

$flash = luckyDrawAdminFlashGet();
$resultDialogAct = strtoupper(trim((string) input('result_act')));
if (!in_array($resultDialogAct, array('PC', 'NC', 'ERRMO'), true)) {
    $resultDialogAct = '';
}
$resultDialogMessage = trim((string) input('result_message'));
if ($resultDialogAct === '' && !empty($flash['message']) && strtolower((string) ($flash['type'] ?? '')) === 'warning') {
    $resultDialogAct = 'ErrMO';
    $resultDialogMessage = (string) $flash['message'];
}
$boardRows = array();
$result = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_VIRTUAL_WINNER . "` WHERE status = 'A' ORDER BY is_enabled DESC, id DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $boardRows[] = (array) $row;
    }
}
include_once '../menuHeader.php';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<script src="<?= $SITEURL ?>/js/list_page_common.js"></script>
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
                            <div class="mt-auto mb-auto d-flex gap-2">
                                <?php if ($canBulkEnable && !empty($boardRows)) { ?>
                                    <button class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" type="submit" name="enable_selected" form="bulkEnableForm">
                                        <i class="fa-solid fa-check"></i> Enable Selected
                                    </button>
                                <?php } ?>
                                <?php if ($canAddRow) { ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= htmlspecialchars(siteUrlWithQuery(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array('act' => 'I')), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-plus"></i> Add Virtual Board Row
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($flash['message']) && !in_array(strtolower((string) ($flash['type'] ?? '')), array('success', 'info', 'warning'), true)) { ?>
                    <div class="alert alert-<?= htmlspecialchars((string) ($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php } ?>

                <?php if (empty($boardRows)) { ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php } else { ?>
                    <form method="post" id="bulkEnableForm">
                        <table class="table table-striped" id="lucky_draw_virtual_board_table">
                            <thead>
                                <tr>
                                    <th class="hideColumn" scope="col">ID</th>
                                    <?php if ($canBulkEnable) { ?>
                                        <th class="text-center" scope="col"><input type="checkbox" class="bulk-enable-all"></th>
                                    <?php } ?>
                                    <th scope="col">S/N</th>
                                    <?php if ($showActionColumn) { ?>
                                        <th scope="col" id="action_col">Action</th>
                                    <?php } ?>
                                    <th scope="col">Display Name</th>
                                    <th scope="col">Display Prize</th>
                                    <th scope="col">Enabled Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $num = 1; ?>
                                <?php foreach ($boardRows as $row) { ?>
                                    <?php $isEnabled = luckyDrawNormalizeFlag($row['is_enabled'] ?? 'Y', 'Y'); ?>
                                    <tr>
                                        <th class="hideColumn" scope="row"><?= (int) ($row['id'] ?? 0) ?></th>
                                        <?php if ($canBulkEnable) { ?>
                                            <td class="text-center">
                                                <?php if ($isEnabled === 'N') { ?>
                                                    <input type="checkbox" class="bulk-enable-row" name="selected_row_ids[]" value="<?= (int) ($row['id'] ?? 0) ?>">
                                                <?php } ?>
                                            </td>
                                        <?php } ?>
                                        <th scope="row"><?= (int) $num++ ?></th>
                                        <?php if ($showActionColumn) { ?>
                                            <td scope="row" class="btn-container">
                                                <?php renderViewEditButton('View', $redirectPage, $row, $pinAccess); ?>
                                                <?php renderViewEditButton('Edit', $redirectPage, $row, $pinAccess, $act_2); ?>
                                                <?php renderDeleteButton($pinAccess, isset($row['id']) ? (int) $row['id'] : 0, isset($row['display_name']) ? $row['display_name'] : '', isset($row['display_prize']) ? $row['display_prize'] : '', $pageTitle, $deleteRedirectPage, $deleteRedirectPage); ?>
                                            </td>
                                        <?php } ?>
                                        <td><?= htmlspecialchars((string) ($row['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($row['display_prize'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $isEnabled === 'Y' ? 'Enabled' : 'Disabled' ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="hideColumn" scope="col">ID</th>
                                    <?php if ($canBulkEnable) { ?>
                                        <th class="text-center" scope="col"><input type="checkbox" class="bulk-enable-all"></th>
                                    <?php } ?>
                                    <th scope="col">S/N</th>
                                    <?php if ($showActionColumn) { ?>
                                        <th scope="col" id="action_col">Action</th>
                                    <?php } ?>
                                    <th scope="col">Display Name</th>
                                    <th scope="col">Display Prize</th>
                                    <th scope="col">Enabled Status</th>
                                </tr>
                            </tfoot>
                        </table>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        const page = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const action = " ";
        const resultDialogAct = <?= json_encode($resultDialogAct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const resultDialogMessage = <?= json_encode($resultDialogMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const resultDialogReturnUrl = <?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        if (document.getElementById('lucky_draw_virtual_board_table')) {
            createSortingTable('lucky_draw_virtual_board_table', { searching: true });
            datatableAlignment('lucky_draw_virtual_board_table');
        }
        setButtonColor();
        if (resultDialogAct !== '' && typeof confirmationDialog === 'function') {
            window.setTimeout(() => {
                confirmationDialog("", resultDialogMessage, page, "", resultDialogReturnUrl, resultDialogAct);
            }, 0);
        }

        const bulkEnableRows = Array.from(document.querySelectorAll('.bulk-enable-row'));
        const bulkEnableMasters = Array.from(document.querySelectorAll('.bulk-enable-all'));
        const bulkEnableForm = document.getElementById('bulkEnableForm');

        function syncBulkEnableMasters() {
            const checkedCount = bulkEnableRows.filter((checkbox) => checkbox.checked).length;
            const allChecked = bulkEnableRows.length > 0 && checkedCount === bulkEnableRows.length;
            bulkEnableMasters.forEach((checkbox) => {
                checkbox.checked = allChecked;
                checkbox.indeterminate = checkedCount > 0 && !allChecked;
            });
        }

        bulkEnableMasters.forEach((masterCheckbox) => {
            masterCheckbox.addEventListener('change', () => {
                bulkEnableRows.forEach((rowCheckbox) => {
                    rowCheckbox.checked = masterCheckbox.checked;
                });
                syncBulkEnableMasters();
            });
        });

        bulkEnableRows.forEach((rowCheckbox) => {
            rowCheckbox.addEventListener('change', syncBulkEnableMasters);
        });

        if (bulkEnableForm) {
            bulkEnableForm.addEventListener('submit', (event) => {
                const checkedCount = bulkEnableRows.filter((checkbox) => checkbox.checked).length;
                if (checkedCount > 0) {
                    return;
                }

                event.preventDefault();
                if (typeof confirmationDialog === 'function') {
                    confirmationDialog("", "Please select at least one disabled virtual board row.", page, "", resultDialogReturnUrl, "ErrMO");
                }
            });
        }

        syncBulkEnableMasters();
    </script>
</body>
</html>
