<?php
$currentPagePin = 90;
$pageTitle = "User";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = USR_USER;

//Current Page Action And Data ID
$dataId = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirectPage = $SITEURL . '/user_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataId) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

//Get The Data From Database
$result = getData('*', "id = '$dataId'", '', $tblName, $connect);

//Checking Data Error When Retrieved From Database
if (($act != 'I') && (!$result || !($row = $result->fetch_assoc()))) {
    $errorExist = 1;
    $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}

$supervisorOptions = array();
$supervisorNameMap = array();
$supervisorSql = "SELECT id, name FROM " . $tblName . " WHERE status='A' ORDER BY name ASC";
$supervisorRst = mysqli_query($connect, $supervisorSql);
if ($supervisorRst) {
    while ($supRow = mysqli_fetch_assoc($supervisorRst)) {
        $supId = (int) $supRow['id'];
        if ($dataId && $supId === (int) $dataId) {
            continue;
        }
        $supName = isset($supRow['name']) ? (string) $supRow['name'] : '';
        $supervisorOptions[] = array('id' => $supId, 'name' => $supName);
        $supervisorNameMap[$supId] = $supName;
    }
}

$hasMainReportSupervisorCol = false;
$hasSecondReportSupervisorCol = false;
$mainColRst = mysqli_query($connect, "SHOW COLUMNS FROM `" . $tblName . "` LIKE 'main_report_supervisor'");
if ($mainColRst && mysqli_num_rows($mainColRst) > 0) {
    $hasMainReportSupervisorCol = true;
}
$secondColRst = mysqli_query($connect, "SHOW COLUMNS FROM `" . $tblName . "` LIKE 'second_report_supervisor'");
if ($secondColRst && mysqli_num_rows($secondColRst) > 0) {
    $hasSecondReportSupervisorCol = true;
}
$hasSupervisorColumns = ($hasMainReportSupervisorCol && $hasSecondReportSupervisorCol);

if (!$hasSupervisorColumns) {
    $supervisorOptions = array();
    $supervisorNameMap = array();
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataId, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

//View Data
if ($dataId && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {

    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
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
            $dataUsername = postSpaceFilter('dataUsername');
            $userGroup = postSpaceFilter('userGroup');
            $userEmail = postSpaceFilter('currentUserEmail');
            $userPassword = postSpaceFilter('password');
            $userConfirmPassword = postSpaceFilter('confirmPassword');
            $mainReportSupervisor = (int) postSpaceFilter('mainReportSupervisor');
            $secondReportSupervisor = (int) postSpaceFilter('secondReportSupervisor');

            $isSuperAdminGroup = false;
            if ((int) $userGroup > 0) {
                $grpRst = getData('name', "id = '" . (int) $userGroup . "'", 'LIMIT 1', USR_GRP, $connect);
                if ($grpRst && $grpRst->num_rows > 0) {
                    $grpRow = $grpRst->fetch_assoc();
                    $isSuperAdminGroup = (strtolower(trim((string) $grpRow['name'])) === 'superadmin');
                }
            }

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataId)) {
                $err1 = "Duplicate record found for user name.";
                $errCount = 1;
            }

            if (isDuplicateRecord("username", $dataUsername, $tblName, $connect, $dataId)) {
                $err2 = "Duplicate record found for username.";
                $errCount = 1;
            }

            if (isDuplicateRecord("email", $userEmail, $tblName, $connect, $dataId)) {
                $err3 = "Duplicate record found for user email.";
                $errCount = 1;
            }

            if ($userPassword !== $userConfirmPassword) {
                $err5 = "Password and confirm password do not match.";
                $errCount = 1;
            }

            if ($hasSupervisorColumns && !$isSuperAdminGroup) {
                if ($mainReportSupervisor <= 0) {
                    $err7 = "Main Report Supervisor is required.";
                    $errCount = 1;
                }

                if ($secondReportSupervisor <= 0) {
                    $err8 = "Second Report Supervisor is required.";
                    $errCount = 1;
                }

                if ($mainReportSupervisor > 0 && $secondReportSupervisor > 0 && $mainReportSupervisor === $secondReportSupervisor) {
                    $err8 = "Main and Second Report Supervisor cannot be the same user.";
                    $errCount = 1;
                }
            } else {
                $mainReportSupervisor = 0;
                $secondReportSupervisor = 0;
            }

            if ($hasSupervisorColumns && $dataId && (($mainReportSupervisor > 0 && $mainReportSupervisor === (int) $dataId) || ($secondReportSupervisor > 0 && $secondReportSupervisor === (int) $dataId))) {
                $err7 = "Supervisor cannot be the same as the current user.";
                $errCount = 1;
            }

            if ($hasSupervisorColumns && $mainReportSupervisor > 0 && !isset($supervisorNameMap[$mainReportSupervisor])) {
                $err7 = "Please select a valid Main Report Supervisor.";
                $errCount = 1;
            }

            if ($hasSupervisorColumns && $secondReportSupervisor > 0 && !isset($supervisorNameMap[$secondReportSupervisor])) {
                $err8 = "Please select a valid Second Report Supervisor.";
                $errCount = 1;
            }

            if (isset($errCount)) {
                break;
            }

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName) {
                        array_push($newvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    if ($dataUsername) {
                        array_push($newvalarr, $dataUsername);
                        array_push($datafield, 'username');
                    }

                    if ($userEmail) {
                        array_push($newvalarr, $userEmail);
                        array_push($datafield, 'email');
                    }

                    if ($userGroup) {
                        array_push($newvalarr, $userGroup);
                        array_push($datafield, 'access_id');
                    }

                    if ($hasSupervisorColumns && $mainReportSupervisor > 0) {
                        array_push($newvalarr, $mainReportSupervisor);
                        array_push($datafield, 'main_report_supervisor');
                    }

                    if ($hasSupervisorColumns && $secondReportSupervisor > 0) {
                        array_push($newvalarr, $secondReportSupervisor);
                        array_push($datafield, 'second_report_supervisor');
                    }

                    if ($userPassword) {
                        array_push($newvalarr, $userPassword);
                        array_push($datafield, 'password');
                    }

                    // Temporarily using md5 to maintain compatibility with the existing login.php flow
                    $hashedPassword = md5($userPassword);
                    if ($hasSupervisorColumns) {
                        $mainSupervisorSql = ($mainReportSupervisor > 0) ? "'" . (int) $mainReportSupervisor . "'" : "NULL";
                        $secondSupervisorSql = ($secondReportSupervisor > 0) ? "'" . (int) $secondReportSupervisor . "'" : "NULL";
                        $query = "INSERT INTO " . $tblName . "(name,username,password,password_alt,email,access_id,main_report_supervisor,second_report_supervisor,create_by,create_date,create_time) VALUES ('$currentDataName','$dataUsername','$userPassword','$hashedPassword','$userEmail','$userGroup',$mainSupervisorSql,$secondSupervisorSql,'" . USER_ID . "',curdate(),curtime())";
                    } else {
                        $query = "INSERT INTO " . $tblName . "(name,username,password,password_alt,email,access_id,create_by,create_date,create_time) VALUES ('$currentDataName','$dataUsername','$userPassword','$hashedPassword','$userEmail','$userGroup','" . USER_ID . "',curdate(),curtime())";
                    }
                    $returnData = mysqli_query($connect, $query);
                    $dataId = $connect->insert_id;
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

                    if ($row['username'] != $dataUsername) {
                        array_push($oldvalarr, $row['username']);
                        array_push($chgvalarr, $dataUsername);
                        array_push($datafield, 'username');
                    }

                    if ($row['email'] != $userEmail) {
                        array_push($oldvalarr, $row['email']);
                        array_push($chgvalarr, $userEmail);
                        array_push($datafield, 'email');
                    }

                    if ($row['access_id'] != $userGroup) {
                        array_push($oldvalarr, $row['access_id']);
                        array_push($chgvalarr, $userGroup);
                        array_push($datafield, 'access_id');
                    }

                    if ($row['password'] != $userPassword) {
                        array_push($oldvalarr, $row['password']);
                        array_push($chgvalarr, $userPassword);
                        array_push($datafield, 'password');
                    }

                    if ($hasSupervisorColumns && (int) (isset($row['main_report_supervisor']) ? $row['main_report_supervisor'] : 0) !== (int) $mainReportSupervisor) {
                        array_push($oldvalarr, (string) (isset($row['main_report_supervisor']) ? $row['main_report_supervisor'] : ''));
                        array_push($chgvalarr, (string) $mainReportSupervisor);
                        array_push($datafield, 'main_report_supervisor');
                    }

                    if ($hasSupervisorColumns && (int) (isset($row['second_report_supervisor']) ? $row['second_report_supervisor'] : 0) !== (int) $secondReportSupervisor) {
                        array_push($oldvalarr, (string) (isset($row['second_report_supervisor']) ? $row['second_report_supervisor'] : ''));
                        array_push($chgvalarr, (string) $secondReportSupervisor);
                        array_push($datafield, 'second_report_supervisor');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        if ($hasSupervisorColumns) {
                            $mainSupervisorSql = ($mainReportSupervisor > 0) ? "'" . (int) $mainReportSupervisor . "'" : "NULL";
                            $secondSupervisorSql = ($secondReportSupervisor > 0) ? "'" . (int) $secondReportSupervisor . "'" : "NULL";
                            $query = "UPDATE " . $tblName . " SET name ='$currentDataName', username ='$dataUsername',email ='$userEmail', access_id ='$userGroup', main_report_supervisor = $mainSupervisorSql, second_report_supervisor = $secondSupervisorSql, update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
                        } else {
                            $query = "UPDATE " . $tblName . " SET name ='$currentDataName', username ='$dataUsername',email ='$userEmail', access_id ='$userGroup', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
                        }
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
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
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
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php echo $pageActionTitle ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2>
                            <?php echo $pageActionTitle ?>
                        </h2>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="currentDataName">Name</label>
                                <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?php if (isset($row['name'])) echo $row['name'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err1)) echo $err1; ?></span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 ">
                                <label class="form-label" for="dataUsername">Username</label>
                                <input class="form-control" type="text" name="dataUsername" id="dataUsername" value="<?php if (isset($row['username'])) echo $row['username'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err2)) echo $err2; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="currentUserEmail">Email</label>
                                <input class="form-control" type="text" name="currentUserEmail" id="currentUserEmail" value="<?php if (isset($row['email'])) echo $row['email'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err3)) echo $err3; ?></span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="currentUsername">User Group</label>
                                <select class="form-select" id="userGroup" name="userGroup" <?php if ($act == '') echo "disabled" ?> required>
                                    <option value="" disabled style="display:off;">Select User Group</option>
                                    <?php
                                    $user_grp_list = getData('id,name', '', '', USR_GRP, $connect);
                                    if ($user_grp_list) {
                                        while ($row2 = $user_grp_list->fetch_assoc()) {
                                            $selected = '';
                                            $id = $row2['id'];
                                            $grpname = $row2['name'];

                                            if (isset($userGroup)) {
                                                if ($userGroup == $id)
                                                    $selected = ' selected';
                                            } else if (isset($row['access_id'])) {
                                                if ($row['access_id'] == $id)
                                                    $selected = ' selected';
                                            }

                                            echo "<option value=\"$id\" $selected>$grpname</option>";
                                        }
                                    } else {
                                        echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                                        echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                                    }
                                    ?>
                                </select>
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err4)) echo $err4; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    $selectedMainSupervisor = isset($mainReportSupervisor) ? (int) $mainReportSupervisor : (isset($row['main_report_supervisor']) ? (int) $row['main_report_supervisor'] : 0);
                    $selectedSecondSupervisor = isset($secondReportSupervisor) ? (int) $secondReportSupervisor : (isset($row['second_report_supervisor']) ? (int) $row['second_report_supervisor'] : 0);
                    ?>
                    <?php if (!$hasSupervisorColumns) { ?>
                        <div class="alert alert-warning py-2">Supervisor columns are not available yet. Please run insert_table.php to enable this feature.</div>
                    <?php } ?>
                    <div class="form-group mb-3" id="supervisorSectionWrap">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="mainReportSupervisor">Main Report Supervisor</label>
                                <select class="form-select" id="mainReportSupervisor" name="mainReportSupervisor" <?php if ($act == '' || !$hasSupervisorColumns) echo "disabled"; ?> autocomplete="off">
                                    <option value="">Select Main Report Supervisor</option>
                                    <?php foreach ($supervisorOptions as $supOpt) {
                                        $supId = (int) $supOpt['id'];
                                        $selected = ($selectedMainSupervisor === $supId) ? ' selected' : '';
                                        echo '<option value="' . $supId . '"' . $selected . '>' . htmlspecialchars((string) $supOpt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    } ?>
                                </select>
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err7)) echo $err7; ?></span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="secondReportSupervisor">Second Report Supervisor</label>
                                <select class="form-select" id="secondReportSupervisor" name="secondReportSupervisor" <?php if ($act == '' || !$hasSupervisorColumns) echo "disabled"; ?> autocomplete="off">
                                    <option value="">Select Second Report Supervisor</option>
                                    <?php foreach ($supervisorOptions as $supOpt) {
                                        $supId = (int) $supOpt['id'];
                                        $selected = ($selectedSecondSupervisor === $supId) ? ' selected' : '';
                                        echo '<option value="' . $supId . '"' . $selected . '>' . htmlspecialchars((string) $supOpt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    } ?>
                                </select>
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err8)) echo $err8; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label" id="passwordlbl" for="password">Password</label>
                            <div id="row-password-input">
                                <div class="d-flex justify-content-end">
                                    <i class="fa fa-eye-slash hide_icon" id="showPassword" onclick="togglePassword('password')"></i>
                                </div>
                                <input class="form-control" type="password" name="password" id="password" value="<?php if (isset($row['password'])) echo $row['password'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err5)) echo $err5; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" id="confirmpassword_lbl" for="password">Confirm Password</label>
                            <div id="row-password-input">
                                <div class="d-flex justify-content-end">
                                    <i class="fa fa-eye-slash hide_icon" id="showConfirmPassword" onclick="togglePassword('confirmPassword')"></i>
                                </div>
                                <input class="form-control" type="password" name="confirmPassword" id="confirmPassword" value="<?php if (isset($row['password'])) echo $row['password'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err6)) echo $err6; ?></span>
                                </div>
                            </div>
                        </div>
                        </div>
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

    function toggleSupervisorRequirement() {
        var userGroupEl = document.getElementById('userGroup');
        var mainEl = document.getElementById('mainReportSupervisor');
        var secondEl = document.getElementById('secondReportSupervisor');
        var supervisorWrap = document.getElementById('supervisorSectionWrap');
        if (!userGroupEl || !mainEl || !secondEl || !supervisorWrap) return;
        var hasSupervisorColumns = <?= $hasSupervisorColumns ? 'true' : 'false' ?>;

        if (!hasSupervisorColumns) {
            mainEl.required = false;
            secondEl.required = false;
            supervisorWrap.style.display = 'none';
            return;
        }

        var selectedText = '';
        if (userGroupEl.selectedIndex >= 0) {
            selectedText = String(userGroupEl.options[userGroupEl.selectedIndex].text || '').toLowerCase();
        }
        var isSuperAdmin = selectedText === 'superadmin';

        supervisorWrap.style.display = isSuperAdmin ? 'none' : '';
        mainEl.required = !isSuperAdmin;
        secondEl.required = !isSuperAdmin;

        if (isSuperAdmin) {
            mainEl.value = '';
            secondEl.value = '';
        }
    }
    

        //Initial Page And Action Value
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ''; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        var userGroupEl = document.getElementById('userGroup');
        if (userGroupEl) {
            userGroupEl.addEventListener('change', toggleSupervisorRequirement);
        }
        toggleSupervisorRequirement();
    </script>

</body>

</html>
