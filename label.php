<?php
$currentPagePin = 145;
$pageTitle = "Label";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = LABEL;

$rawDataID = !empty(input('id')) ? input('id') : post('id');
$dataID = '';
if ($rawDataID !== '' && ctype_digit((string) $rawDataID)) {
    $dataID = (string) ((int) $rawDataID);
}

$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirect_page = $SITEURL . '/label_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if (($rawDataID !== '' && $dataID === '') || (!($dataID) && !($act)) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
}

$row = array();
$rst = false;
if ($dataID !== '') {
    $rst = getData('*', "id = '$dataID'", '', $tblName, $connect);
}

if ($dataID !== '' && (!$rst || !($row = $rst->fetch_assoc())) && $act != 'I') {
    $errorExist = 1;
    $act = "F";
}

$parentLabelOptions = array();
$parentLabelResult = mysqli_query($connect, "SELECT id, name FROM " . $tblName . " WHERE status = 'A' ORDER BY name ASC");
if ($parentLabelResult) {
    while ($parentLabelRow = $parentLabelResult->fetch_assoc()) {
        $parentLabelOptions[] = $parentLabelRow;
    }
}

$retainFormInput = ($_SERVER['REQUEST_METHOD'] === 'POST' && post('actionBtn') !== 'back');
$labelNameValue = $retainFormInput ? trim((string) postSpaceFilter('label_name')) : (isset($row['name']) ? (string) $row['name'] : '');
$labelRemarkValue = $retainFormInput ? trim((string) postSpaceFilter('label_remark')) : (isset($row['remark']) ? (string) $row['remark'] : '');
$selectedParentLabelId = $retainFormInput ? trim((string) postSpaceFilter('parent_label')) : (isset($row['parent_label']) ? (string) $row['parent_label'] : '');
$useSelfParentFallback = ($act === 'I' && empty($parentLabelOptions));
$selectedParentLabelName = '';

if ($selectedParentLabelId !== '') {
    foreach ($parentLabelOptions as $parentLabelOption) {
        if ((string) $parentLabelOption['id'] === (string) $selectedParentLabelId) {
            $selectedParentLabelName = (string) $parentLabelOption['name'];
            break;
        }
    }
}

if ($retainFormInput && !$useSelfParentFallback) {
    $postedParentLabelName = trim((string) postSpaceFilter('parent_label_name'));
    if ($postedParentLabelName !== '') {
        $selectedParentLabelName = $postedParentLabelName;
    }
}

if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {
    $_SESSION['viewChk'] = 1;

    $safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeDataID = htmlspecialchars((string) $dataID, ENT_QUOTES, 'UTF-8');
    $safeTblName = htmlspecialchars((string) $tblName, ENT_QUOTES, 'UTF-8');

    if (isset($errorExist)) {
        $viewActMsg = $safeUserName . " fail to viewed the data [<b> ID = " . $safeDataID . "</b> ] from <b><i>" . $safeTblName . " Table</i></b>.";
    } else {
        $safeRowName = htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8');
        $viewActMsg = $safeUserName . " viewed the data [<b> ID = " . $safeDataID . "</b> ] <b>" . $safeRowName . "</b> from <b><i>" . $safeTblName . " Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate'   => $cdate,
        'ctime'   => $ctime,
        'uid'     => USER_ID,
        'cby'     => USER_ID,
        'act_msg' => $viewActMsg,
        'page'    => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':
            $labelName = trim((string) postSpaceFilter('label_name'));
            $labelRemark = trim((string) postSpaceFilter('label_remark'));
            $parentLabelId = trim((string) postSpaceFilter('parent_label'));
            $parentLabelName = trim((string) postSpaceFilter('parent_label_name'));
            $hasValidationError = false;
            $resolvedParentLabelId = '';

            $labelNameValue = $labelName;
            $labelRemarkValue = $labelRemark;
            $selectedParentLabelId = $parentLabelId;
            $selectedParentLabelName = $parentLabelName;

            if ($labelName === '') {
                $name_err = 'Label Name is required!';
                $hasValidationError = true;
            }

            if ($labelRemark === '') {
                $remark_err = 'Remark is required!';
                $hasValidationError = true;
            }

            if ($useSelfParentFallback) {
                $resolvedParentLabelId = 'SELF_ON_CREATE';
            } else if ($parentLabelId === '') {
                $parent_err = 'Parent Label is required!';
                $hasValidationError = true;
            } else {
                foreach ($parentLabelOptions as $parentLabelOption) {
                    if ((string) $parentLabelOption['id'] === $parentLabelId) {
                        $resolvedParentLabelId = $parentLabelId;
                        $selectedParentLabelName = (string) $parentLabelOption['name'];
                        break;
                    }
                }

                if ($resolvedParentLabelId === '') {
                    $parent_err = 'Please select a valid Parent Label.';
                    $hasValidationError = true;
                }
            }

            if ($hasValidationError) {
                break;
            }

            $safeLabelName = mysqli_real_escape_string($connect, $labelName);
            $safeLabelRemark = mysqli_real_escape_string($connect, $labelRemark);
            $duplicateWhere = "name = '$safeLabelName' AND status = 'A'";

            if ($resolvedParentLabelId === 'SELF_ON_CREATE') {
                $duplicateWhere .= " AND parent_label IS NULL";
            } else {
                $safeResolvedParentLabelId = (int) $resolvedParentLabelId;
                $duplicateWhere .= " AND parent_label = '" . $safeResolvedParentLabelId . "'";
            }

            if ($dataID !== '') {
                $duplicateWhere .= " AND id != '" . (int) $dataID . "'";
            }

            $duplicateResult = getData('id', $duplicateWhere, 'LIMIT 1', $tblName, $connect);
            if ($duplicateResult && $duplicateResult->num_rows > 0) {
                $err = "Duplicate record found for current " . $pageTitle . ".";
                break;
            }

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    array_push($newvalarr, $labelName);
                    array_push($datafield, 'name');

                    array_push($newvalarr, $resolvedParentLabelId === 'SELF_ON_CREATE' ? 'Self' : $resolvedParentLabelId);
                    array_push($datafield, 'parent_label');

                    array_push($newvalarr, $labelRemark);
                    array_push($datafield, 'remark');

                    $insertParentValue = $resolvedParentLabelId === 'SELF_ON_CREATE' ? "NULL" : "'" . (int) $resolvedParentLabelId . "'";
                    $query = "INSERT INTO " . $tblName . " (name, parent_label, remark, create_by, create_date, create_time) VALUES ('" . $safeLabelName . "', " . $insertParentValue . ", '" . $safeLabelRemark . "', '" . USER_ID . "', CURDATE(), CURTIME())";
                    $returnData = mysqli_query($connect, $query);

                    if ($returnData) {
                        $dataID = (string) $connect->insert_id;
                        if ($resolvedParentLabelId === 'SELF_ON_CREATE') {
                            $selfParentQuery = "UPDATE " . $tblName . " SET parent_label = '" . (int) $dataID . "' WHERE id = '" . (int) $dataID . "'";
                            mysqli_query($connect, $selfParentQuery);
                            $query = $query . "; " . $selfParentQuery;
                        }
                        generateDBData($tblName, $connect);
                    } else {
                        $errorMsg = mysqli_error($connect);
                        $act = "F";
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    $resolvedParentLabelId = (string) (int) $resolvedParentLabelId;

                    if ((string) $row['name'] !== $labelName) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $labelName);
                        array_push($datafield, 'name');
                    }

                    if ((string) $row['parent_label'] !== $resolvedParentLabelId) {
                        array_push($oldvalarr, (string) $row['parent_label']);
                        array_push($chgvalarr, $resolvedParentLabelId);
                        array_push($datafield, 'parent_label');
                    }

                    if ((string) $row['remark'] !== $labelRemark) {
                        array_push($oldvalarr, $row['remark'] === '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $labelRemark === '' ? 'Empty Value' : $labelRemark);
                        array_push($datafield, 'remark');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='" . $safeLabelName . "', parent_label = '" . (int) $resolvedParentLabelId . "', remark ='" . $safeLabelRemark . "', update_date = CURDATE(), update_time = CURTIME(), update_by ='" . USER_ID . "' WHERE id = '" . (int) $dataID . "'";
                        $returnData = mysqli_query($connect, $query);

                        if ($returnData) {
                            generateDBData($tblName, $connect);
                        } else {
                            $errorMsg = mysqli_error($connect);
                            $act = "F";
                        }
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            if (isset($query)) {
                $log = [
                    'log_act'      => $pageAction,
                    'cdate'        => $cdate,
                    'ctime'        => $ctime,
                    'uid'          => USER_ID,
                    'cby'          => USER_ID,
                    'query_rec'    => $query,
                    'query_table'  => $tblName,
                    'page'         => $pageTitle,
                    'connect'      => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }
            break;

        case 'back':
            echo $clearLocalStorage . ' ' . $redirectLink;
            break;
    }
}

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2><?php echo $pageActionTitle ?></h2>
                    </div>

                    <?php if (isset($err) && $err !== '') : ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="label_name">Label Name<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" name="label_name" id="label_name" value="<?= htmlspecialchars($labelNameValue, ENT_QUOTES, 'UTF-8') ?>" <?php if ($act == '') echo 'readonly' ?> required data-skip-common-required="1" autocomplete="off">
                        <div id="err_msg">
                            <span class="mt-n1" id="labelNameError"><?php if (isset($name_err)) echo $name_err; ?></span>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="parent_label">Parent Label<span class="requireRed">*</span></label>
                        <?php if ($useSelfParentFallback) : ?>
                            <input class="form-control" type="text" value="Current label will be used as parent for the first record" readonly>
                        <?php else : ?>
                            <div class="autocomplete">
                                <input class="form-control" type="text" name="parent_label_name" id="parent_label_name" value="<?= htmlspecialchars($selectedParentLabelName, ENT_QUOTES, 'UTF-8') ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off" required data-skip-common-required="1">
                                <input type="hidden" name="parent_label" id="parent_label" value="<?= htmlspecialchars($selectedParentLabelId, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        <?php endif; ?>
                        <div id="err_msg">
                            <span class="mt-n1" id="parentLabelError"><?php if (isset($parent_err)) echo $parent_err; ?></span>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="label_remark">Remark<span class="requireRed">*</span></label>
                        <textarea class="form-control" name="label_remark" id="label_remark" rows="3" <?php if ($act == '') echo 'readonly' ?> required data-skip-common-required="1"><?= htmlspecialchars($labelRemarkValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div id="err_msg">
                            <span class="mt-n1" id="labelRemarkError"><?php if (isset($remark_err)) echo $remark_err; ?></span>
                        </div>
                    </div>

                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back" formnovalidate>Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.labelPageConfig = {
            pageTitle: <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            action: <?= json_encode(isset($act) ? $act : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            siteUrl: <?= json_encode($SITEURL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            dbTable: <?= json_encode(LABEL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            useSelfParentFallback: <?= $useSelfParentFallback ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= $SITEURL ?>/js/label.js"></script>
</body>

</html>
