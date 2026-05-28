<?php
$currentPagePin = 132;
$pageTitle = "SQL Account";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = SQL_ACC;

if (function_exists('isStatusFieldAvailable') && !isStatusFieldAvailable($tblName, $connect)) {
    @mysqli_query($connect, "ALTER TABLE `" . $tblName . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A'");
}

$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirect_page = $SITEURL . '/sql_account_table.php';
$redirectLink = "<script>location.href = '$redirect_page';</script>";
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if ((!$dataID && !$act) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
}

$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);

if ((!$rst || !($row = $rst->fetch_assoc())) && $act !== 'I') {
    $errorExist = 1;
    $act = 'F';
}

if ($act === 'D') {
    $safeDeleteId = (int) $dataID;
    $deleteName = isset($row['name']) ? $row['name'] : '';
    $deleteQuery = "UPDATE " . $tblName . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $safeDeleteId . "'";
    mysqli_query($connect, $deleteQuery);

    $deleteLog = [
        'log_act' => 'delete',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $deleteQuery,
        'query_table' => $tblName,
        'act_msg' => USER_NAME . " deleted the data [<b> ID = " . $safeDeleteId . "</b> ] <b>" . $deleteName . "</b> from <b><i>$tblName Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($deleteLog);
    $_SESSION['delChk'] = 1;
}

if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':
            $currentDataName = postSpaceFilter('currentDataName');
            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if ($currentDataName === '') {
                $err = 'Name is required.';
                break;
            }

            if (isDuplicateRecord('name', $currentDataName, $tblName, $connect, $dataID)) {
                $err = 'Duplicate record found for SQL Account name.';
                break;
            }

            if ($action === 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName !== '') {
                        $newvalarr[] = $currentDataName;
                        $datafield[] = 'name';
                    }
                    
                    // Sanitize input before inserting into database
                    $safeName = mysqli_real_escape_string($connect, $currentDataName);
                    $query = "INSERT INTO " . $tblName . "(name,create_by,create_date,create_time,update_by,update_date,update_time,status) VALUES ('$safeName','" . USER_ID . "',CURDATE(),CURTIME(),'" . USER_ID . "',CURDATE(),CURTIME(),'A')";
                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = 'F';
                }
            } else {
                try {
                    if ($row['name'] != $currentDataName) {
                        $oldvalarr[] = $row['name'];
                        $chgvalarr[] = $currentDataName;
                        $datafield[] = 'name';
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if (!empty($oldvalarr) && !empty($chgvalarr)) {
                        // Sanitize input before updating database
                        $safeName = mysqli_real_escape_string($connect, $currentDataName);
                        $query = "UPDATE " . $tblName . " SET name ='$safeName', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id = '$dataID' AND status='A'";
                        $returnData = mysqli_query($connect, $query);
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = 'F';
                }
            }

            if (isset($query)) {
                $log = [
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
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
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2><?= $pageActionTitle ?></h2>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataName">SQL Account Name*</label>
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?= isset($row['name']) ? htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') : '' ?>" <?= ($act == '') ? 'readonly' : '' ?> required autocomplete="off">
                        <div id="err_msg">
                            <span class="mt-n1" id="errorSpan"><?= isset($err) ? htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') : '' ?></span>
                        </div>
                    </div>

                    <?= commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?= ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var page = "<?= $pageTitle ?>";
        var action = "<?= isset($act) ? $act : '' ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
    </script>
</body>

</html>
