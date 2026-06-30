<?php
$pageTitle = 'Lucky Draw - Virtual Board';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';
$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'View', $pinAccess);

$boardRowId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = strtoupper(trim((string) (isset($_GET['act']) ? $_GET['act'] : '')));
if ($mode === 'A') {
    $mode = 'I';
}
$resultDialogAct = strtoupper(trim((string) ($_GET['result_act'] ?? '')));
if (!in_array($resultDialogAct, array('I', 'E', 'NC'), true)) {
    $resultDialogAct = '';
}

$isAdd = $mode === 'I';
$isEdit = $mode === 'E';
$isView = !$isAdd && !$isEdit;

if (!$isAdd && $boardRowId <= 0) {
    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(), 'warning', 'Please select a valid virtual board action.');
}

$editingRow = array();
if ($boardRowId > 0) {
    $rowResult = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_VIRTUAL_WINNER . "` WHERE id = " . $boardRowId . " AND status = 'A' LIMIT 1");
    if ($rowResult && ($rowData = mysqli_fetch_assoc($rowResult))) {
        $editingRow = (array) $rowData;
    }
}

if ($isAdd) {
    luckyDrawRequireAdminAction($connect, 'Add', $pinAccess);
} elseif ($isEdit) {
    luckyDrawRequireAdminAction($connect, 'Edit', $pinAccess);
    if (empty($editingRow)) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(), 'danger', 'Virtual board row not found.');
    }
} else {
    luckyDrawRequireAdminAction($connect, 'View', $pinAccess);
    if (empty($editingRow)) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE, array(), 'danger', 'Virtual board row not found.');
    }
}

if ($isView && !empty($editingRow)) {
    luckyDrawInsertAdminLog($connect, 'view_virtual_board', LUCKY_DRAW_VIRTUAL_WINNER, $boardRowId, (string) ($editingRow['display_prize'] ?? ''), USER_ID, array(
        'page_title' => $pageTitle,
        'audit_action' => 'view',
        'entity_label' => 'virtual board',
        'use_standard_crud_message' => true,
    ));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_board_row'])) {
    $postRowId = isset($_POST['board_row_id']) ? (int) $_POST['board_row_id'] : 0;
    $isEditSave = $postRowId > 0;
    $formMode = strtoupper(trim((string) (isset($_POST['form_mode']) ? $_POST['form_mode'] : ($isEditSave ? 'E' : 'I'))));
    luckyDrawRequireAdminAction($connect, $postRowId > 0 ? 'Edit' : 'Add', $pinAccess);

    $displayName = luckyDrawSafePublicText(isset($_POST['display_name']) ? $_POST['display_name'] : '', 190);
    $displayPrize = luckyDrawSafePublicText(isset($_POST['display_prize']) ? $_POST['display_prize'] : '', 190);
    $isEnabled = luckyDrawNormalizeFlag(isset($_POST['is_enabled']) ? $_POST['is_enabled'] : 'N', 'N');
    $safeActor = mysqli_real_escape_string($connect, (string) USER_ID);

    if ($displayName === '' || $displayPrize === '') {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array(
            'act' => $formMode,
            'id' => $postRowId > 0 ? $postRowId : null,
        ), 'danger', 'Display name and prize text are required.');
    }

    if ($postRowId > 0) {
        $normalizedCurrentRow = array(
            'display_name' => trim((string) ($editingRow['display_name'] ?? '')),
            'display_prize' => trim((string) ($editingRow['display_prize'] ?? '')),
            'is_enabled' => luckyDrawNormalizeFlag($editingRow['is_enabled'] ?? 'N', 'N'),
        );
        $normalizedSubmittedRow = array(
            'display_name' => $displayName,
            'display_prize' => $displayPrize,
            'is_enabled' => $isEnabled,
        );

        if ($normalizedCurrentRow === $normalizedSubmittedRow) {
            luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array(
                'act' => 'E',
                'id' => $postRowId,
                'result_act' => 'NC',
            ));
        }

        $updateResult = mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
            SET display_name = '" . mysqli_real_escape_string($connect, $displayName) . "',
                display_prize = '" . mysqli_real_escape_string($connect, $displayPrize) . "',
                is_enabled = '" . mysqli_real_escape_string($connect, $isEnabled) . "',
                update_by = '" . $safeActor . "',
                update_date = CURDATE(),
                update_time = CURTIME()
            WHERE id = " . $postRowId . "
              AND status = 'A'
            LIMIT 1");
        if (!$updateResult) {
            luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array('act' => 'E', 'id' => $postRowId), 'danger', 'Unable to update this virtual board row.');
        }
        $boardRowId = $postRowId;
    } else {
        $insertResult = mysqli_query($connect, "INSERT INTO `" . LUCKY_DRAW_VIRTUAL_WINNER . "`
            (display_name, display_prize, is_enabled, create_by, create_date, create_time, status)
            VALUES
            ('" . mysqli_real_escape_string($connect, $displayName) . "', '" . mysqli_real_escape_string($connect, $displayPrize) . "', '" . mysqli_real_escape_string($connect, $isEnabled) . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')");
        if (!$insertResult) {
            luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array('act' => 'I'), 'danger', 'Unable to add this virtual board row.');
        }
        $boardRowId = (int) mysqli_insert_id($connect);
        if ($boardRowId <= 0) {
            luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array('act' => 'I'), 'danger', 'Unable to add this virtual board row.');
        }
    }

    luckyDrawInsertAdminLog($connect, 'save_virtual_board', LUCKY_DRAW_VIRTUAL_WINNER, $boardRowId, $displayPrize, USER_ID, array(
        'page_title' => $pageTitle,
        'audit_action' => $isEditSave ? 'edit' : 'add',
        'entity_label' => 'virtual board',
        'use_standard_crud_message' => true,
    ));
    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD, array(
        'act' => $isEditSave ? 'E' : 'I',
        'id' => $isEditSave ? $boardRowId : null,
        'result_act' => $isEditSave ? 'E' : 'I',
    ));
}

$defaultRow = array(
    'id' => 0,
    'display_name' => '',
    'display_prize' => '',
    'is_enabled' => 'Y',
);
$editingRow = !empty($editingRow) ? array_merge($defaultRow, $editingRow) : $defaultRow;
$pageHeading = $isAdd ? 'Add Virtual Winner' : ($isEdit ? 'Edit Virtual Winner' : 'View Virtual Winner');
$flash = luckyDrawAdminFlashGet();
$readonlyAttr = $isView ? 'readonly' : '';
$disabledAttr = $isView ? 'disabled' : '';

include_once '../menuHeader.php';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p>
                <a href="<?= htmlspecialchars(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE), ENT_QUOTES, 'UTF-8') ?>">Virtual Board</a>
                <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-12 col-md-8 formWidthAdjust">
                <?php if (!empty($flash['message'])) { ?>
                    <div class="alert alert-<?= htmlspecialchars((string) ($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php } ?>

                <form method="post">
                    <input type="hidden" name="form_mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="board_row_id" value="<?= (int) ($editingRow['id'] ?? 0) ?>">

                    <div class="form-group mb-4">
                        <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl">Display Name*</label>
                                <input type="text" class="form-control" name="display_name" value="<?= htmlspecialchars((string) ($editingRow['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $readonlyAttr ?> required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl">Prize Text*</label>
                                <input type="text" class="form-control" name="display_prize" value="<?= htmlspecialchars((string) ($editingRow['display_prize'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $readonlyAttr ?> required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl d-block">Enabled Status*</label>
                                <input type="hidden" name="is_enabled" value="N">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="Y" name="is_enabled" id="is_enabled" <?= luckyDrawNormalizeFlag($editingRow['is_enabled'] ?? 'Y', 'Y') === 'Y' ? 'checked' : '' ?> <?= $disabledAttr ?>>
                                    <label class="form-check-label" for="is_enabled">Enable this virtual board row</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php echo commonRenderCreateUpdateInfo($editingRow, $connect, $mode); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php if (!$isView) { ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" name="save_board_row" id="actionBtn"><?= $isAdd ? 'Add Virtual Winner' : 'Edit Virtual Winner' ?></button>
                        <?php } ?>
                        <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" id="backBtn" onclick='location.href=<?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE)) ?>'>Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const page = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const action = <?= json_encode($mode === '' ? ' ' : $mode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const resultDialogAct = <?= json_encode($resultDialogAct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const resultDialogReturnUrl = <?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_VIRTUAL_BOARD_TABLE), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        checkCurrentPage(page, action);
        centerAlignment('formContainer');
        setButtonColor();
        preloader(300, action);
        if (resultDialogAct !== '' && typeof confirmationDialog === 'function') {
            window.setTimeout(() => {
                confirmationDialog("", "", page, "", resultDialogReturnUrl, resultDialogAct);
            }, 0);
        }
    </script>
</body>
</html>
