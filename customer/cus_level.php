<?php
$currentPagePin = 142;
$pageTitle = "Customer Level";

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if (!empty($resolvedPageTitle)) {
    $pageTitle = $resolvedPageTitle;
}

$tblName = CUS_LEVEL;
$currency_list_result = getData('*', '', '', CUR_UNIT, $connect);

$dataId = !empty(input('id')) ? (int) input('id') : (int) post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirectPage = $SITEURL . '/customer/cus_level_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if (!($dataId) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

$result = getData('*', "id = '$dataId'", '', $tblName, $connect);

if ($act != 'I' && (!$result || !($row = $result->fetch_assoc()))) {
    $errorExist = 1;
    $act = "F";
}

if ($act == 'D') {
    deleteRecord($tblName, '', $dataId, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

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

if (post('actionBtn')) {

    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':

            $currentDataName = postSpaceFilter('currentDataName');
            $colorSegmentation =  postSpaceFilter('segmentationColor');
            $purchaseAmountFrom = postSpaceFilter('purchaseAmountFrom');
            $purchaseAmountUntil = postSpaceFilter('purchaseAmountUntil');
            $currency = (int) postSpaceFilter('currency_hidden');
            $dataRemark = postSpaceFilter('currentDataRemark');
            $sqlCurrentDataName = mysqli_real_escape_string($connect, trim((string) $currentDataName));
            $sqlColorSegmentation = mysqli_real_escape_string($connect, trim((string) $colorSegmentation));
            $sqlPurchaseAmountFrom = mysqli_real_escape_string($connect, trim((string) $purchaseAmountFrom));
            $sqlPurchaseAmountUntil = mysqli_real_escape_string($connect, trim((string) $purchaseAmountUntil));
            $sqlDataRemark = mysqli_real_escape_string($connect, trim((string) $dataRemark));

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataId)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                $errorCount  = 1;
            }

            if (isDuplicateRecord("colorCode", $colorSegmentation, $tblName, $connect, $dataId)) {
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

                    if ($purchaseAmountFrom !== '') {
                        array_push($newvalarr, $purchaseAmountFrom);
                        array_push($datafield, 'purchaseAmountFrom');
                    }

                    if ($purchaseAmountUntil !== '') {
                        array_push($newvalarr, $purchaseAmountUntil);
                        array_push($datafield, 'purchaseAmountUntil');
                    }

                    if ($currency !== '') {
                        array_push($newvalarr, $currency);
                        array_push($datafield, 'currency');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,colorCode,remark,purchaseAmountFrom,purchaseAmountUntil,currency,create_by,create_date,create_time) VALUES ('$sqlCurrentDataName','$sqlColorSegmentation','$sqlDataRemark','$sqlPurchaseAmountFrom','$sqlPurchaseAmountUntil','$currency','" . USER_ID . "',curdate(),curtime())";

                    $returnData = mysqli_query($connect, $query);
                    $dataId = $connect->insert_id;
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

                    if ($row['purchaseAmountFrom'] != $purchaseAmountFrom) {
                        array_push($oldvalarr, $row['purchaseAmountFrom']);
                        array_push($chgvalarr, $purchaseAmountFrom);
                        array_push($datafield, 'purchaseAmountFrom');
                    }

                    if ($row['purchaseAmountUntil'] != $purchaseAmountUntil) {
                        array_push($oldvalarr, $row['purchaseAmountUntil']);
                        array_push($chgvalarr, $purchaseAmountUntil);
                        array_push($datafield, 'purchaseAmountUntil');
                    }

                    if ($row['currency'] != $currency) {
                        array_push($oldvalarr, $row['currency']);
                        array_push($chgvalarr, $currency);
                        array_push($datafield, 'currency');
                    }

                    if ($row['remark'] != $dataRemark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $dataRemark == '' ? 'Empty Value' : $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='$sqlCurrentDataName', colorCode = '$sqlColorSegmentation' , purchaseAmountFrom='$sqlPurchaseAmountFrom', purchaseAmountUntil='$sqlPurchaseAmountUntil', currency='$currency', remark ='$sqlDataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
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
                        <div class="row cus-level-range-row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="purchaseAmountFrom">Purchase Amount From*</label>
                                <input class="form-control" type="text" name="purchaseAmountFrom" id="purchaseAmountFrom" value="<?php if (isset($row['purchaseAmountFrom'])) echo $row['purchaseAmountFrom'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off" oninput="validateNumericInput(this, 'purchaseAmountFromErrorMsg', 'purchaseAmountUntilErrorMsg')">
                                <div id="purchaseAmountFromErrorMsg" class="error-message">
                                    <span class="mt-n1"></span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="purchaseAmountUntil">Purchase Amount Until*</label>
                                <input class="form-control" type="text" name="purchaseAmountUntil" id="purchaseAmountUntil" value="<?php if (isset($row['purchaseAmountUntil'])) echo $row['purchaseAmountUntil'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off" oninput="validateNumericInput(this, 'purchaseAmountUntilErrorMsg', 'purchaseAmountFromErrorMsg')">
                                <div id="purchaseAmountUntilErrorMsg" class="error-message">
                                    <span class="mt-n1"></span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="currency">Currency</label>
                                <?php
                                unset($echoVal);

                                if (isset($row['currency'])) {
                                    $echoVal = $row['currency'];
                                }

                                if (isset($echoVal) && $echoVal !== '') {
                                    $currency_rst = getData('unit', "id = '$echoVal'", '', CUR_UNIT, $connect);
                                    $currency_row = ($currency_rst && $currency_rst->num_rows > 0) ? $currency_rst->fetch_assoc() : array();
                                }
                                ?>
                                <div class="autocomplete">
                                    <input class="form-control" type="text" name="currency"
                                        id="currency" <?php if ($act == '') echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? ($currency_row['unit'] ?? '') : '' ?>">
                                    <input type="hidden" name="currency_hidden" id="currency_hidden"
                                        value="<?php echo (isset($row['currency'])) ? $row['currency'] : ''; ?>">
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
        
        <?php include "js/cus_level.js" ?>
    </script>

</body>

</html>
