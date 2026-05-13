<?php
$currentPagePin = 3;
$pageTitle = "User Group";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = USR_GRP;

//Current Page Action And Data ID
$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirect_page = $SITEURL . '/user_group_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataID) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

//Get The Data From Database
$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);

//Checking Data Error When Retrieved From Database
if (($act != 'I') && (!$rst || !($row = $rst->fetch_assoc()))) {
    $errorExist = 1;
    $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}

//Get Pin and Pin Group Data
$pinResult = getData('*', '', '', PIN, $connect);
$pinGrpResult = getData('*', '', '', PIN_GRP, $connect);

if (!$pinResult || !$pinGrpResult) {
    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
    echo $redirectLink;
}

$pin_arr = array();
$permission_grp = array();
$permission_grp_keys = array();
$permission_grp_count = 0;
$pinActionList = array();
$pinGroupList = array();

if ($pinResult) {
    while ($pinRow = mysqli_fetch_assoc($pinResult)) {
        $pinActionList[] = array(
            'id' => (string) $pinRow['id'],
            'name' => isset($pinRow['name']) ? (string) $pinRow['name'] : '',
        );
        $pin_arr[] = (string) $pinRow['id'];
    }
}

if ($pinGrpResult) {
    while ($pinGrpRow = mysqli_fetch_assoc($pinGrpResult)) {
        $pinCsv = isset($pinGrpRow['pins']) ? (string) $pinGrpRow['pins'] : '';
        $pinIds = array();
        foreach (explode(',', $pinCsv) as $pinIdRaw) {
            $pinId = trim((string) $pinIdRaw);
            if ($pinId !== '') {
                $pinIds[] = $pinId;
            }
        }
        $pinGroupList[] = array(
            'id' => (int) $pinGrpRow['id'],
            'name' => isset($pinGrpRow['name']) ? (string) $pinGrpRow['name'] : '',
            'pins' => $pinIds,
        );
    }
}

if ($dataID) {
    $userGroupResult = getData('*', "id = '$dataID'", '', USR_GRP, $connect);

    if ($userGroupResult) {
        $row = $userGroupResult->fetch_assoc();
        $permission_grp = array();

        // get pin group and pin
        $pins = explode("+", $row['pins']);
        for ($i = 0; $i < count($pins); $i++) {
            $pins[$i] = str_replace("[", "", $pins[$i]);
            $pins[$i] = str_replace("]", "", $pins[$i]);
        }

        foreach ($pins as $x) {
            $colonpos = stripos($x, ":");
            $tmp_pingrp = substr($x, 0, $colonpos);
            $tmp_pin = substr($x, $colonpos);
            $tmp_pin = str_replace(":", "", $tmp_pin);
            $tmp_pin = explode(",", $tmp_pin);
            $permission_grp[$tmp_pingrp] = $tmp_pin;
        }
        $permission_grp_keys = array_keys($permission_grp);
        $permission_grp_count = count($permission_grp);
    } else {
        echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
        echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
    }
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

//View Data
if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {

    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
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

//Edit And Add Data
if (post('actionBtn')) {

    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':

            $currentDataName = postSpaceFilter('currentDataName');
            $badgeColor = postSpaceFilter('badgeColor');
            $badgeIconClass = postSpaceFilter('badgeIconClass');
            $dataRemark = postSpaceFilter('currentDataRemark');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataID)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                break;
            }

            $arr = post('user_grp_chkbox_val');
            $storevalue = array();

            // convert all array into string
            if ($arr) {
                // get pin group
                $keys = implode(",", array_keys($arr));
                $keys_arr = explode(",", $keys);

                foreach ($keys_arr as $x) {
                    $value = implode(",", $arr[$x]);
                    $temp = "[" . $x . ":" . $value . "]";  // ex. [<pingrp>:<permission>]
                    array_push($storevalue, $temp);
                }

                $permission_grp = implode("+", $storevalue);
            }

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName) {
                        array_push($newvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    if ($permission_grp) {
                        array_push($newvalarr, $permission_grp);
                        array_push($datafield, 'pins');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    if ($badgeColor) {
                        array_push($newvalarr, $badgeColor);
                        array_push($datafield, 'badge_color');
                    }

                    if ($badgeIconClass) {
                        array_push($newvalarr, $badgeIconClass);
                        array_push($datafield, 'badge_icon_class');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,badge_color,badge_icon_class,pins,remark,create_by,create_date,create_time) VALUES (?,?,?,?,?,?,curdate(),curtime())";
                    $stmt = mysqli_prepare($connect, $query);
                    mysqli_stmt_bind_param($stmt, "ssssss", $currentDataName, $badgeColor, $badgeIconClass, $permission_grp, $dataRemark, USER_ID);
                    $returnData = mysqli_stmt_execute($stmt);
                    $dataID = $connect->insert_id;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    if ($row['name'] != $currentDataName) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    if ($row['pins'] != $permission_grp) {
                        $parsePinGroupMap = function ($pinsExpr) {
                            $map = array();
                            foreach (explode('+', (string) $pinsExpr) as $part) {
                                $part = trim((string) $part);
                                if ($part === '') {
                                    continue;
                                }

                                $part = trim($part, '[]');
                                if ($part === '' || strpos($part, ':') === false) {
                                    continue;
                                }

                                list($grp, $pins) = explode(':', $part, 2);
                                $grp = trim((string) $grp);
                                if ($grp === '') {
                                    continue;
                                }

                                $pinList = array();
                                foreach (explode(',', (string) $pins) as $pinVal) {
                                    $pinVal = trim((string) $pinVal);
                                    if ($pinVal !== '') {
                                        $pinList[] = $pinVal;
                                    }
                                }
                                $map[$grp] = $pinList;
                            }

                            return $map;
                        };

                        $formatPinBlock = function ($groupId, $pins) {
                            $pins = is_array($pins) ? $pins : array();
                            if (empty($pins)) {
                                return '[]';
                            }
                            return '[' . $groupId . ':' . implode(',', $pins) . ']';
                        };

                        $oldMap = $parsePinGroupMap($row['pins']);
                        $newMap = $parsePinGroupMap($permission_grp);

                        $changedOldBlocks = array();
                        $changedNewBlocks = array();
                        $addedBlocks = array();

                        $allGroups = array_values(array_unique(array_merge(array_keys($oldMap), array_keys($newMap))));
                        sort($allGroups, SORT_NATURAL);

                        foreach ($allGroups as $groupId) {
                            $oldPins = isset($oldMap[$groupId]) ? $oldMap[$groupId] : array();
                            $newPins = isset($newMap[$groupId]) ? $newMap[$groupId] : array();

                            if (implode(',', $oldPins) === implode(',', $newPins)) {
                                continue;
                            }

                            if (empty($oldPins) && !empty($newPins)) {
                                $addedBlocks[] = '[' . $groupId . ':' . implode(',', $newPins) . ']';
                                continue;
                            }

                            $changedOldBlocks[] = '[' . $groupId . ':' . implode(',', $oldPins) . ']';
                            $changedNewBlocks[] = $formatPinBlock($groupId, $newPins);
                        }

                        if (empty($changedOldBlocks) && !empty($addedBlocks)) {
                            $oldPinAudit = 'added ' . implode(',', $addedBlocks) . '.';
                            $newPinAudit = '';
                        } else {
                            foreach ($addedBlocks as $addedBlock) {
                                $changedOldBlocks[] = '[]';
                                $changedNewBlocks[] = $addedBlock;
                            }

                            $oldPinAudit = implode(',', $changedOldBlocks);
                            $newPinAudit = implode(',', $changedNewBlocks);
                        }

                        array_push($oldvalarr, $oldPinAudit);
                        array_push($chgvalarr, $newPinAudit);
                        array_push($datafield, 'pins');
                    }

                    if ($row['remark'] != $dataRemark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $dataRemark == '' ? 'Empty Value' : $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    if ((string) (isset($row['badge_color']) ? $row['badge_color'] : '') != $badgeColor) {
                        array_push($oldvalarr, (isset($row['badge_color']) && $row['badge_color'] !== '') ? $row['badge_color'] : 'Empty Value');
                        array_push($chgvalarr, $badgeColor !== '' ? $badgeColor : 'Empty Value');
                        array_push($datafield, 'badge_color');
                    }

                    if ((string) (isset($row['badge_icon_class']) ? $row['badge_icon_class'] : '') != $badgeIconClass) {
                        array_push($oldvalarr, (isset($row['badge_icon_class']) && $row['badge_icon_class'] !== '') ? $row['badge_icon_class'] : 'Empty Value');
                        array_push($chgvalarr, $badgeIconClass !== '' ? $badgeIconClass : 'Empty Value');
                        array_push($datafield, 'badge_icon_class');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $effectiveBadgeColor = $badgeColor !== '' ? $badgeColor : (isset($row['badge_color']) ? (string) $row['badge_color'] : '');
                        $effectiveBadgeIconClass = $badgeIconClass !== '' ? $badgeIconClass : (isset($row['badge_icon_class']) ? (string) $row['badge_icon_class'] : '');

                        $query = "UPDATE " . $tblName . " SET name = ?, badge_color = ?, badge_icon_class = ?, pins = ?, remark = ?, update_date = curdate(), update_time = curtime(), update_by = ? WHERE id = ?";
                        $stmt = mysqli_prepare($connect, $query);
                        mysqli_stmt_bind_param($stmt, "ssssssi", $currentDataName, $effectiveBadgeColor, $effectiveBadgeIconClass, $permission_grp, $dataRemark, USER_ID, $dataID);
                        $returnData = mysqli_stmt_execute($stmt);
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            // audit log
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

//Function(title, subtitle, page name, ajax url path, redirect path, action)
//To show action dialog after finish certain action (eg. edit)

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
}

$defaultBadgeIconClass = 'fa-solid fa-user-group';
$selectedBadgeIconClass = $defaultBadgeIconClass;
if (isset($_POST['badgeIconClass'])) {
    $selectedBadgeIconClass = trim((string) $_POST['badgeIconClass']);
} else if (isset($row['badge_icon_class']) && trim((string) $row['badge_icon_class']) !== '') {
    $selectedBadgeIconClass = trim((string) $row['badge_icon_class']);
}
if ($selectedBadgeIconClass === '') {
    $selectedBadgeIconClass = $defaultBadgeIconClass;
}

$fontAwesomeIconOptions = array();
$fontAwesomeSpriteFiles = array(
    'fa-solid' => __DIR__ . '/header/fontawesome-free-6.0.0-web/sprites/solid.svg',
    'fa-regular' => __DIR__ . '/header/fontawesome-free-6.0.0-web/sprites/regular.svg',
    'fa-brands' => __DIR__ . '/header/fontawesome-free-6.0.0-web/sprites/brands.svg',
);
$seenIconOptionValues = array();

foreach ($fontAwesomeSpriteFiles as $iconStyle => $spriteFilePath) {
    if (!is_file($spriteFilePath) || !is_readable($spriteFilePath)) {
        continue;
    }

    $spriteMarkup = file_get_contents($spriteFilePath);
    if ($spriteMarkup === false) {
        continue;
    }

    if (!preg_match_all('/<symbol[^>]*id="([^"]+)"/i', $spriteMarkup, $matches)) {
        continue;
    }

    foreach ($matches[1] as $iconNameRaw) {
        $iconName = strtolower(trim((string) $iconNameRaw));
        if ($iconName === '') {
            continue;
        }

        $iconValue = $iconStyle . ' fa-' . $iconName;
        if (isset($seenIconOptionValues[$iconValue])) {
            continue;
        }

        $seenIconOptionValues[$iconValue] = true;
        $fontAwesomeIconOptions[] = array(
            'value' => $iconValue,
            'label' => $iconName,
            'style' => $iconStyle,
        );
    }
}

usort($fontAwesomeIconOptions, function ($left, $right) {
    return strnatcasecmp((string) $left['label'], (string) $right['label']);
});

$selectedBadgeIconExists = false;
foreach ($fontAwesomeIconOptions as $iconOption) {
    if ((string) $iconOption['value'] === $selectedBadgeIconClass) {
        $selectedBadgeIconExists = true;
        break;
    }
}

if (!$selectedBadgeIconExists) {
    $selectedBadgeIconLabel = preg_replace('/^fa-(solid|regular|brands)\s+fa-/i', '', $selectedBadgeIconClass);
    $selectedBadgeIconLabel = trim((string) $selectedBadgeIconLabel);
    if ($selectedBadgeIconLabel === '') {
        $selectedBadgeIconLabel = 'custom-icon';
    }

    array_unshift($fontAwesomeIconOptions, array(
        'value' => $selectedBadgeIconClass,
        'label' => $selectedBadgeIconLabel,
        'style' => (stripos($selectedBadgeIconClass, 'fa-brands') !== false) ? 'fa-brands' : 'fa-solid',
    ));
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .permission-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .permission-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .permission-search {
            min-width: 240px;
            max-width: 360px;
            width: 100%;
            margin-left: auto;
        }

        .search-field-input {
            padding-right: 35px;
            font-size: 14px;
            font-weight: 400;
        }

        .search-field-clear-btn {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            z-index: 10;
            color: #999;
            border: none;
            background: transparent;
            display: none;
        }

        .badge-color-input {
            width: 100% !important;
            min-width: 0;
            height: 48px;
        }

        .badge-icon-picker {
            position: relative;
        }

        .badge-icon-search-wrap {
            position: relative;
        }

        .badge-icon-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 99;
            display: none;
            max-height: 280px;
            overflow-y: auto;
            border: 1px solid #000000;
            border-radius: 4px;
            background: #fff;
            box-sizing: border-box;
            padding: 0;
        }

        .badge-icon-dropdown.show {
            display: block;
        }

        .badge-icon-option {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 4px;
            border: none;
            background: #f2f3f4;
            text-align: left;
            font-size: 14px;
            font-weight: 400;
            color: #000000;
            margin-bottom: 1px;
        }

        .badge-icon-option:nth-child(even) {
            background: #e5e7e9;
            color: #000000;
        }

        .badge-icon-option:hover,
        .badge-icon-option.active {
            background: #cacfd2;
        }

        .badge-icon-option i {
            width: 20px;
            text-align: center;
            color: #344767;
        }

        .badge-icon-option-label {
            flex: 1 1 auto;
        }

        .badge-icon-option-class {
            color: inherit;
            font-size: 12px;
        }

        .badge-icon-empty {
            padding: 4px;
            background: #f2f3f4;
            color: #000000;
            font-size: 14px;
        }

        .badge-icon-preview {
            display: none;
        }

        .permission-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .permission-card {
            border: 1px solid #e2e2e2;
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }

        .permission-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: #f7f7f9;
            cursor: pointer;
        }

        .permission-card-title {
            font-weight: 600;
            margin: 0;
        }

        .permission-card-body {
            padding: 12px 14px;
            border-top: 1px solid #ececec;
            display: none;
        }

        .permission-card.open .permission-card-body {
            display: block;
        }

        .permission-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
        }

        .permission-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 130px;
            white-space: nowrap;
        }

        .permission-arrow {
            transition: transform .2s ease;
        }

        .permission-card.open .permission-arrow {
            transform: rotate(180deg);
        }

        @media (max-width: 1199px) {
            .permission-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991px) {
            .permission-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .permission-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .permission-actions {
                justify-content: flex-start;
            }

            .permission-search {
                max-width: none;
                min-width: 0;
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php echo $pageActionTitle ?>
            </p>
        </div>

        <div id="formContainer" class="container-fluid d-flex justify-content-center">
            <div class="col-12 col-xl-10 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2>
                            <?php echo $pageActionTitle ?>
                        </h2>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataName"><?php echo $pageTitle ?> Name</label>
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?php if (isset($row['name'])) echo $row['name'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                        <div id="err_msg">
                            <span class="mt-n1" id="errorSpan"><?php if (isset($err)) echo $err; ?></span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-4 mb-3">
                            <label class="form-label" for="badgeColor">Badge Color</label>
                            <input class="form-control form-control-color badge-color-input" type="color" name="badgeColor" id="badgeColor" value="<?php
                                if (isset($_POST['badgeColor'])) {
                                    echo htmlspecialchars($_POST['badgeColor']);
                                } else if (isset($row['badge_color']) && trim((string) $row['badge_color']) !== '') {
                                    echo htmlspecialchars($row['badge_color']);
                                } else {
                                    echo '#6c757d';
                                }
                            ?>" <?php if ($act == '') echo 'disabled' ?>>
                        </div>
                        <div class="col-12 col-md-8 mb-3">
                            <label class="form-label" for="badgeIconClass">Badge Icon Class</label>
                            <div class="badge-icon-picker">
                                <input type="hidden" name="badgeIconClass" id="badgeIconClass" value="<?= htmlspecialchars($selectedBadgeIconClass, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="badge-icon-search-wrap">
                                    <input class="form-control search-field-input" type="text" id="badgeIconSearch" value="<?= htmlspecialchars($selectedBadgeIconClass, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search Font Awesome icon..." autocomplete="off" <?php if ($act == '') echo 'readonly' ?>>
                                    <button class="btn shadow-none search-field-clear-btn" type="button" id="clearBadgeIconSearchBtn" title="Clear icon search" <?php if ($act == '') echo 'disabled' ?>>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="badge-icon-dropdown" id="badgeIconDropdown">
                                    <?php foreach ($fontAwesomeIconOptions as $iconOption) { ?>
                                        <button
                                            class="badge-icon-option"
                                            type="button"
                                            data-icon-value="<?= htmlspecialchars($iconOption['value'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-icon-label="<?= htmlspecialchars(strtolower((string) $iconOption['label']), ENT_QUOTES, 'UTF-8') ?>"
                                            data-icon-class="<?= htmlspecialchars(strtolower((string) $iconOption['value']), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <i class="<?= htmlspecialchars($iconOption['value'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <span class="badge-icon-option-label"><?= htmlspecialchars($iconOption['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="badge-icon-option-class"><?= htmlspecialchars($iconOption['value'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </button>
                                    <?php } ?>
                                    <div class="badge-icon-empty" id="badgeIconEmptyState" style="display: none;">No matching Font Awesome icons found.</div>
                                </div>
                                <div class="badge-icon-preview" id="badgeIconPreview">
                                    <i class="<?= htmlspecialchars($selectedBadgeIconClass, ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <span id="badgeIconPreviewText"><?= htmlspecialchars($selectedBadgeIconClass, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" id="permission_table_lbl" for="permissionSearch">Permissions</label>
                        <div class="permission-toolbar">
                            <div class="permission-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" id="toggleAllPermissionsBtn">Toggle All</button>
                                <button class="btn btn-sm btn-outline-primary" type="button" id="tickAllPinsBtn" <?php if ($act == '') echo 'disabled'; ?>>Tick All Pins</button>
                            </div>
                            <div class="permission-search position-relative">
                                <input class="form-control search-field-input" type="text" id="permissionSearch" placeholder="Search page permissions..." autocomplete="off">
                                <button class="btn shadow-none search-field-clear-btn" type="button" id="clearSearchBtn" title="Clear Search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="permission-grid" id="permissionGrid">
                            <?php foreach ($pinGroupList as $groupIndex => $pinGroup) {
                                $groupId = (int) $pinGroup['id'];
                                $groupName = (string) $pinGroup['name'];
                                $groupPins = isset($pinGroup['pins']) && is_array($pinGroup['pins']) ? $pinGroup['pins'] : array();
                                $cardId = 'permission_card_' . $groupId;
                                $bodyId = 'permission_body_' . $groupId;
                            ?>
                                <div class="permission-card" id="<?= $cardId ?>" data-permission-group="<?= htmlspecialchars(strtolower($groupName), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="permission-card-header" data-target="<?= $bodyId ?>">
                                        <h6 class="permission-card-title mb-0"><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h6>
                                        <i class="fa-solid fa-chevron-down permission-arrow"></i>
                                    </div>
                                    <div class="permission-card-body" id="<?= $bodyId ?>">
                                        <div class="permission-list">
                                            <?php
                                            $hasPermissionItem = false;
                                            foreach ($pinActionList as $actionItem) {
                                                $actionId = (string) $actionItem['id'];
                                                $actionName = (string) $actionItem['name'];
                                                if (!in_array($actionId, $groupPins, true)) {
                                                    continue;
                                                }
                                                $hasPermissionItem = true;
                                                $isChecked = '';
                                                if ((isset($act)) && ($act != 'I') && isset($permission_grp[$groupId]) && is_array($permission_grp[$groupId])) {
                                                    foreach ($permission_grp[$groupId] as $savedPinVal) {
                                                        if ((string) $savedPinVal === $actionId) {
                                                            $isChecked = ' checked';
                                                            break;
                                                        }
                                                    }
                                                }
                                                $readonly = ($act == '') ? ' disabled' : '';
                                                $checkId = 'perm_' . $groupId . '_' . $actionId . '_' . $groupIndex;
                                            ?>
                                                <label class="permission-item" data-permission-item="<?= htmlspecialchars(strtolower($actionName), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input class="form-check-input" type="checkbox" id="<?= $checkId ?>" name="user_grp_chkbox_val[<?= $groupId ?>][]" value="<?= htmlspecialchars($actionId, ENT_QUOTES, 'UTF-8') ?>"<?= $isChecked . $readonly ?>>
                                                    <span><?= htmlspecialchars($actionName, ENT_QUOTES, 'UTF-8') ?></span>
                                                </label>
                                            <?php } ?>
                                            <?php if (!$hasPermissionItem) { ?>
                                                <span class="text-muted">No available actions.</span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataRemark"><?php echo $pageTitle ?> Remark</label>
                        <textarea class="form-control" name="currentDataRemark" id="currentDataRemark" rows="3" <?php if ($act == '') echo 'readonly' ?>><?php if (isset($row['remark'])) echo $row['remark'] ?></textarea>
                    </div>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        //Initial Page And Action Value
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ''; ?>";

        checkCurrentPage(page, action);
        setButtonColor();
        preloader(300, action);

        var permissionHeaders = document.querySelectorAll('.permission-card-header');
        permissionHeaders.forEach(function (header) {
            header.addEventListener('click', function () {
                var card = header.closest('.permission-card');
                if (!card) return;
                card.classList.toggle('open');
            });
        });

        var toggleAllBtn = document.getElementById('toggleAllPermissionsBtn');
        if (toggleAllBtn) {
            toggleAllBtn.addEventListener('click', function () {
                var cards = document.querySelectorAll('.permission-card');
                var hasClosed = Array.prototype.some.call(cards, function (card) {
                    return !card.classList.contains('open');
                });
                cards.forEach(function (card) {
                    if (hasClosed) {
                        card.classList.add('open');
                    } else {
                        card.classList.remove('open');
                    }
                });
            });
        }

        var tickAllPinsBtn = document.getElementById('tickAllPinsBtn');
        var permissionCheckboxes = function () {
            return document.querySelectorAll('input[name^="user_grp_chkbox_val"]:not([disabled])');
        };

        function refreshTickAllButtonState() {
            if (!tickAllPinsBtn) return;
            var boxes = permissionCheckboxes();
            if (!boxes || boxes.length === 0) {
                tickAllPinsBtn.textContent = 'Tick All Pins';
                return;
            }
            var allChecked = Array.prototype.every.call(boxes, function (checkbox) {
                return checkbox.checked;
            });
            tickAllPinsBtn.textContent = allChecked ? 'Untick All Pins' : 'Tick All Pins';
        }

        if (tickAllPinsBtn) {
            tickAllPinsBtn.addEventListener('click', function () {
                var boxes = permissionCheckboxes();
                var shouldTickAll = tickAllPinsBtn.textContent !== 'Untick All Pins';
                boxes.forEach(function (checkbox) {
                    checkbox.checked = shouldTickAll;
                });
                refreshTickAllButtonState();
            });
        }

        permissionCheckboxes().forEach(function (checkbox) {
            checkbox.addEventListener('change', refreshTickAllButtonState);
        });
        refreshTickAllButtonState();

        var badgeIconInput = document.getElementById('badgeIconClass');
        var badgeIconSearch = document.getElementById('badgeIconSearch');
        var badgeIconDropdown = document.getElementById('badgeIconDropdown');
        var badgeIconOptions = badgeIconDropdown ? badgeIconDropdown.querySelectorAll('.badge-icon-option') : [];
        var badgeIconEmptyState = document.getElementById('badgeIconEmptyState');
        var badgeIconPreview = document.getElementById('badgeIconPreview');
        var badgeIconPreviewText = document.getElementById('badgeIconPreviewText');
        var clearBadgeIconSearchBtn = document.getElementById('clearBadgeIconSearchBtn');
        var badgeIconReadonly = badgeIconSearch ? badgeIconSearch.hasAttribute('readonly') : true;

        function updateBadgeIconPreview(iconClass) {
            if (!badgeIconPreview || !badgeIconPreviewText) return;
            var previewIcon = badgeIconPreview.querySelector('i');
            if (previewIcon) {
                previewIcon.className = iconClass || '';
            }
            badgeIconPreviewText.textContent = iconClass || '';
        }

        function showBadgeIconDropdown() {
            if (!badgeIconDropdown || badgeIconReadonly) return;
            badgeIconDropdown.classList.add('show');
        }

        function hideBadgeIconDropdown() {
            if (!badgeIconDropdown) return;
            badgeIconDropdown.classList.remove('show');
        }

        function toggleBadgeIconClearButton() {
            if (!clearBadgeIconSearchBtn || badgeIconReadonly) return;
            clearBadgeIconSearchBtn.style.display = String(badgeIconSearch.value || '').trim() !== '' ? '' : 'none';
        }

        function setActiveBadgeIconOption(selectedButton) {
            if (!badgeIconOptions) return;
            badgeIconOptions.forEach(function (optionButton) {
                optionButton.classList.toggle('active', optionButton === selectedButton);
            });
        }

        function filterBadgeIconOptions() {
            if (!badgeIconSearch || !badgeIconDropdown) return;

            var keyword = String(badgeIconSearch.value || '').toLowerCase().trim();
            var visibleCount = 0;

            badgeIconOptions.forEach(function (optionButton) {
                var label = String(optionButton.getAttribute('data-icon-label') || '');
                var iconClass = String(optionButton.getAttribute('data-icon-class') || '');
                var matched = keyword === '' || label.indexOf(keyword) !== -1 || iconClass.indexOf(keyword) !== -1;
                optionButton.style.display = matched ? '' : 'none';
                if (matched) {
                    visibleCount++;
                }
            });

            if (badgeIconEmptyState) {
                badgeIconEmptyState.style.display = visibleCount === 0 ? '' : 'none';
            }

            toggleBadgeIconClearButton();
        }

        function selectBadgeIcon(iconClass, optionButton) {
            if (!badgeIconInput || !badgeIconSearch) return;
            badgeIconInput.value = iconClass;
            badgeIconSearch.value = iconClass;
            updateBadgeIconPreview(iconClass);
            setActiveBadgeIconOption(optionButton || null);
            filterBadgeIconOptions();
            hideBadgeIconDropdown();
        }

        if (badgeIconSearch && badgeIconDropdown) {
            badgeIconSearch.addEventListener('focus', function () {
                filterBadgeIconOptions();
                showBadgeIconDropdown();
            });

            badgeIconSearch.addEventListener('input', function () {
                if (badgeIconReadonly) return;
                showBadgeIconDropdown();
                filterBadgeIconOptions();
            });

            badgeIconOptions.forEach(function (optionButton) {
                optionButton.addEventListener('click', function () {
                    selectBadgeIcon(String(optionButton.getAttribute('data-icon-value') || ''), optionButton);
                });
            });

            if (clearBadgeIconSearchBtn) {
                clearBadgeIconSearchBtn.addEventListener('click', function () {
                    if (badgeIconReadonly) return;
                    badgeIconSearch.value = '';
                    filterBadgeIconOptions();
                    showBadgeIconDropdown();
                    badgeIconSearch.focus();
                });
            }

            document.addEventListener('click', function (event) {
                var picker = document.querySelector('.badge-icon-picker');
                if (picker && !picker.contains(event.target)) {
                    hideBadgeIconDropdown();
                }
            });

            var selectedOption = null;
            badgeIconOptions.forEach(function (optionButton) {
                if (String(optionButton.getAttribute('data-icon-value') || '') === String(badgeIconInput.value || '')) {
                    selectedOption = optionButton;
                }
            });
            setActiveBadgeIconOption(selectedOption);
            updateBadgeIconPreview(String(badgeIconInput.value || ''));
            filterBadgeIconOptions();
        }

        var permissionSearch = document.getElementById('permissionSearch');
        if (permissionSearch) {
            var clearSearchBtn = document.getElementById('clearSearchBtn');

            function toggleClearSearchButton() {
                if (!clearSearchBtn) return;
                var hasKeyword = String(permissionSearch.value || '').trim() !== '';
                clearSearchBtn.style.display = hasKeyword ? '' : 'none';
            }

            permissionSearch.addEventListener('input', function () {
                var keyword = String(permissionSearch.value || '').toLowerCase().trim();
                document.querySelectorAll('.permission-card').forEach(function (card) {
                    var groupName = String(card.getAttribute('data-permission-group') || '');
                    var itemText = Array.prototype.map.call(card.querySelectorAll('[data-permission-item]'), function (el) {
                        return String(el.getAttribute('data-permission-item') || '');
                    }).join(' ');
                    var matched = keyword === '' || groupName.indexOf(keyword) !== -1 || itemText.indexOf(keyword) !== -1;
                    card.style.display = matched ? '' : 'none';
                });

                toggleClearSearchButton();
            });

            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function () {
                    permissionSearch.value = ''; // Empty the text
                    permissionSearch.dispatchEvent(new Event('input')); // Reset the UI cards
                    permissionSearch.focus(); // Put cursor back in the box
                });
            }

            toggleClearSearchButton();
        }
    </script>

</body>

</html>
