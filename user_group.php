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

                    $query = "INSERT INTO " . $tblName . "(name,pins,remark,create_by,create_date,create_time) VALUES ('$currentDataName','$permission_grp','$dataRemark','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($connect, $query);
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

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='$currentDataName',pins = '$permission_grp', remark ='$dataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
                        $returnData = mysqli_query($connect, $query);
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

                    <div class="form-group mb-3">
                        <label class="form-label" id="permission_table_lbl" for="permissionSearch">Permissions</label>
                        <div class="permission-toolbar">
                            <div class="permission-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" id="toggleAllPermissionsBtn">Toggle All</button>
                                <button class="btn btn-sm btn-outline-primary" type="button" id="tickAllPinsBtn" <?php if ($act == '') echo 'disabled'; ?>>Tick All Pins</button>
                            </div>
                            <div class="permission-search position-relative">
                                <input class="form-control" type="text" id="permissionSearch" placeholder="Search page permissions..." autocomplete="off" style="padding-right: 35px;">
                                <button class="btn shadow-none" type="button" id="clearSearchBtn" title="Clear Search" style="position: absolute; right: 0; top: 0; bottom: 0; z-index: 10; color: #999; border: none; background: transparent; display: none;">
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
