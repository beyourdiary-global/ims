<?php
$currentPagePin = 143;
$pageTitle = "Customer Repeat";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if (!empty($resolvedPageTitle)) {
    $pageTitle = $resolvedPageTitle;
}

$tblName = CUS_REPEAT;

$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirect_page = $SITEURL . '/cus_repeat_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if (!($dataID) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);

if ($act != 'I' && (!$rst || !($row = $rst->fetch_assoc()))) {
    $errorExist = 1;
    $act = "F";
}

if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
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

            $currentDataName = postSpaceFilter('currentDataName');
            $colorSegmentation =  postSpaceFilter('segmentationColor');
            $orderFrequencyFrom = postSpaceFilter('orderFrequencyFrom');
            $orderFrequencyUntil = postSpaceFilter('orderFrequencyUntil');
            $dataRemark = postSpaceFilter('currentDataRemark');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataID)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                $errorCount  = 1;
            }

            if (isDuplicateRecord("colorCode", $colorSegmentation, $tblName, $connect, $dataID)) {
                $err2 = "Duplicate record found for " . $pageTitle . " color code.";
                $errorCount  = 1;
            }

            if (isset($errorCount)) {
                break;
            }

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName) {
                        array_push($newvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    if ($colorSegmentation) {
                        array_push($newvalarr, $colorSegmentation);
                        array_push($datafield, 'colorCode');
                    }

                    if ($orderFrequencyFrom !== '') {
                        array_push($newvalarr, $orderFrequencyFrom);
                        array_push($datafield, 'orderFrequencyFrom');
                    }

                    if ($orderFrequencyUntil !== '') {
                        array_push($newvalarr, $orderFrequencyUntil);
                        array_push($datafield, 'orderFrequencyUntil');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,colorCode,remark,orderFrequencyFrom,orderFrequencyUntil,create_by,create_date,create_time) VALUES ('$currentDataName','$colorSegmentation','$dataRemark','$orderFrequencyFrom','$orderFrequencyUntil','" . USER_ID . "',curdate(),curtime())";

                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;
                    if ($returnData) {
                        $act = 'I';
                    } else {
                        $act = 'F';
                    }
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

                    if ($row['colorCode'] != $colorSegmentation) {
                        array_push($oldvalarr, $row['colorCode']);
                        array_push($chgvalarr, $colorSegmentation);
                        array_push($datafield, 'colorCode');
                    }

                    if ($row['orderFrequencyFrom'] != $orderFrequencyFrom) {
                        array_push($oldvalarr, $row['orderFrequencyFrom']);
                        array_push($chgvalarr, $orderFrequencyFrom);
                        array_push($datafield, 'orderFrequencyFrom');
                    }

                    if ($row['orderFrequencyUntil'] != $orderFrequencyUntil) {
                        array_push($oldvalarr, $row['orderFrequencyUntil']);
                        array_push($chgvalarr, $orderFrequencyUntil);
                        array_push($datafield, 'orderFrequencyUntil');
                    }

                    if ($row['remark'] != $dataRemark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $dataRemark == '' ? 'Empty Value' : $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='$currentDataName', colorCode = '$colorSegmentation' , orderFrequencyFrom='$orderFrequencyFrom', orderFrequencyUntil='$orderFrequencyUntil', remark ='$dataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
                        $returnData = mysqli_query($connect, $query);
                        if ($returnData) {
                            $act = 'E';
                        } else {
                            $act = 'F';
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
    

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
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

                    <div class="form-group">
                        <div class="row">
                            <div class="col-sm  mb-3">
                                <label class="form-label form_lbl" for="currentDataName"><?php echo $pageTitle ?> Name*</label>
                                <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?php if (isset($row['name'])) echo $row['name'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err)) echo $err; ?></span>
                                </div>
                            </div>

                            <div class="col-sm mb-3">
                                <label class=" form-label form_lbl" for="segmentationColor"><?php echo $pageTitle ?> Color</label><br>
                                <div class="col d-flex justify-content-start align-items-center">
                                    <input type="color" name="segmentationColor" id="segmentationColor" <?php if ($act == '') echo 'disabled ' ?> value="<?php if (isset($row['colorCode'])) echo $row['colorCode'] ?>" class="form-control" style="height: 40px;">
                                    <span id="color-display"><?php if (isset($row['colorCode'])) echo $row['colorCode']; ?></span>
                                </div>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($err2)) echo $err2; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-sm mb-3">
                                <label class="form-label form_lbl" for="orderFrequencyFrom">Order Frequency From*</label>
                                <input class="form-control" type="text" name="orderFrequencyFrom" id="orderFrequencyFrom" value="<?php if (isset($row['orderFrequencyFrom'])) echo $row['orderFrequencyFrom'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off" oninput="validateNumericInput(this, 'orderFrequencyFromErrorMsg', 'orderFrequencyUntilErrorMsg')">
                                <div id="orderFrequencyFromErrorMsg" class="error-message">
                                    <span class="mt-n1"></span>
                                </div>
                            </div>
                            <div class="col-sm mb-3">
                                <label class="form-label form_lbl" for="orderFrequencyUntil">Order Frequency Until*</label>
                                <input class="form-control" type="text" name="orderFrequencyUntil" id="orderFrequencyUntil" value="<?php if (isset($row['orderFrequencyUntil'])) echo $row['orderFrequencyUntil'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off" oninput="validateNumericInput(this, 'orderFrequencyUntilErrorMsg', 'orderFrequencyFromErrorMsg')">
                                <div id="orderFrequencyUntilErrorMsg" class="error-message">
                                    <span class="mt-n1"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="currentDataRemark"><?php echo $pageTitle ?> Remark</label>
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
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ''; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
        
        <?php include "js/cus_repeat.js" ?>
    </script>

</body>

</html>
