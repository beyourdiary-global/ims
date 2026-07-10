<?php
$currentPagePin = 36;
$pageTitle = "Merchant";

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = MERCHANT;

function normalizeMerchantFormattedValue($value)
{
    $value = strtoupper(trim((string) $value));
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);

    $prefix = substr(preg_replace('/\D+/', '', $value), 0, 3);
    $suffixSource = substr($value, strlen($prefix));
    $suffix = substr(preg_replace('/[^A-Z0-9]+/', '', $suffixSource), 0, 5);

    if ($prefix === '') {
        return $suffix;
    }

    if ($suffix === '') {
        return $prefix;
    }

    return $prefix . '-' . $suffix;
}

//Current Page Action And Data ID
$dataId = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirectPage = $SITEURL . '/finance/merchant_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';
$errorMsgAlert = "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataId) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

//Get The Data From Database
$result = getData('*', "id = '$dataId'", '', $tblName,  $finance_connect);

//Checking Data Error When Retrieved From Database
if ($act != 'I' && (!$result || !($row = $result->fetch_assoc()))) {
    $errorExist = 1;
    $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '',$dataId, $row['name'],  $finance_connect, $connect, $cdate, $ctime, $pageTitle);
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
            $mrcht_business_no = postSpaceFilter("mrcht_business_no");
            $mrcht_control_account = normalizeMerchantFormattedValue(postSpaceFilter('mrcht_control_account'));
            $mrcht_code = normalizeMerchantFormattedValue(postSpaceFilter('mrcht_code'));
            $mrcht_email = postSpaceFilter("mrcht_email");
            $mrcht_contact = postSpaceFilter('mrcht_contact');
            $mrcht_address = postSpaceFilter('mrcht_address');
            $mrcht_pic = postSpaceFilter('mrcht_pic');
            $mrcht_pic_contact = postSpaceFilter('mrcht_pic_contact');
            $dataRemark = postSpaceFilter('currentDataRemark');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if ($mrcht_email && !isEmail($mrcht_email)) {
                $email_err = "Wrong email format!";
                $error = 1;
            }

            if ($mrcht_contact && !preg_match('/^[0-9]+$/', $mrcht_contact)) {
                $contact_err = "Contact must contain numbers only. Please remove any dashes or special characters.";
                $error = 1;
            }

            if ($mrcht_pic_contact && !preg_match('/^[0-9]+$/', $mrcht_pic_contact)) {
                $pic_contact_err = "Person In Charge Contact must contain numbers only. Please remove any dashes or special characters.";
                $error = 1;
            }

            if ($mrcht_control_account !== '' && !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $mrcht_control_account)) {
                $control_account_err = "Control A/C format must be like 123-ABC01.";
                $error = 1;
            }

            if ($mrcht_code !== '' && !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $mrcht_code)) {
                $code_err = "Code format must be like 123-ABC01.";
                $error = 1;
            }

            try {
                $query = "SELECT COUNT(*) as count FROM `$tblName` WHERE `name` = '$currentDataName'";
                if ($dataId) {
                    $query .= " AND id != '$dataId'";
                }
                $result = mysqli_query($finance_connect, $query);
                if ($result) {
                    $rowDup = mysqli_fetch_assoc($result);
                    if ($rowDup['count'] > 0) {
                        $name_err = "Duplicate record found for " . $pageTitle . " name.";
                        $error = 1;
                    }
                }
            } catch (Exception $e) {
                // Ignore or handle
            }

            if (isset($error)) {
                break;
            }

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName) {
                        array_push($newvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    if ($mrcht_business_no) {
                        array_push($newvalarr, $mrcht_business_no);
                        array_push($datafield, 'business_no');
                    }

                    if ($mrcht_control_account) {
                        array_push($newvalarr, $mrcht_control_account);
                        array_push($datafield, 'control_account');
                    }

                    if ($mrcht_code) {
                        array_push($newvalarr, $mrcht_code);
                        array_push($datafield, 'code');
                    }

                    if ($mrcht_email) {
                        array_push($newvalarr, $mrcht_email);
                        array_push($datafield, 'email');
                    }

                    if ($mrcht_contact) {
                        array_push($newvalarr, $mrcht_contact);
                        array_push($datafield, 'contact');
                    }

                    if ($mrcht_address) {
                        array_push($newvalarr, $mrcht_address);
                        array_push($datafield, 'address');
                    }

                    if ($mrcht_pic) {
                        array_push($newvalarr, $mrcht_pic);
                        array_push($datafield, 'person_in_charges');
                    }

                    if ($mrcht_pic_contact) {
                        array_push($newvalarr, $mrcht_pic_contact);
                        array_push($datafield, 'person_in_charges_contact');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,business_no,control_account,code,contact,email,address,person_in_charges,person_in_charges_contact,remark,create_by,create_date,create_time) VALUES ('$currentDataName','$mrcht_business_no','$mrcht_control_account','$mrcht_code','$mrcht_contact','$mrcht_email','$mrcht_address','$mrcht_pic','$mrcht_pic_contact','$dataRemark','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($finance_connect, $query);
                    $dataId = $finance_connect->insert_id;
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

                    if ($row['business_no'] != $mrcht_business_no) {
                        array_push($oldvalarr, $row['business_no']);
                        array_push($chgvalarr, $mrcht_business_no);
                        array_push($datafield, 'business_no');
                    }

                    if (($row['control_account'] ?? '') != $mrcht_control_account) {
                        array_push($oldvalarr, ($row['control_account'] ?? '') === '' ? 'Empty Value' : $row['control_account']);
                        array_push($chgvalarr, $mrcht_control_account === '' ? 'Empty Value' : $mrcht_control_account);
                        array_push($datafield, 'control_account');
                    }

                    if (($row['code'] ?? '') != $mrcht_code) {
                        array_push($oldvalarr, ($row['code'] ?? '') === '' ? 'Empty Value' : $row['code']);
                        array_push($chgvalarr, $mrcht_code === '' ? 'Empty Value' : $mrcht_code);
                        array_push($datafield, 'code');
                    }

                    if ($row['contact'] != $mrcht_contact) {
                        array_push($oldvalarr, $row['contact']);
                        array_push($chgvalarr, $mrcht_contact);
                        array_push($datafield, 'contact');
                    }

                    if ($row['email'] != $mrcht_email) {
                        array_push($oldvalarr, $row['email']);
                        array_push($chgvalarr, $mrcht_email);
                        array_push($datafield, 'email');
                    }

                    if ($row['address'] != $mrcht_address) {
                        array_push($oldvalarr, $row['address']);
                        array_push($chgvalarr, $mrcht_address);
                        array_push($datafield, 'address');
                    }

                    if ($row['person_in_charges'] != $mrcht_pic) {
                        array_push($oldvalarr, $row['person_in_charges']);
                        array_push($chgvalarr, $mrcht_pic);
                        array_push($datafield, 'person_in_charges');
                    }

                    if ($row['person_in_charges_contact'] != $mrcht_pic_contact) {
                        array_push($oldvalarr, $row['person_in_charges_contact']);
                        array_push($chgvalarr, $mrcht_pic_contact);
                        array_push($datafield, 'person_in_charges_contact');
                    }

                    if ($row['remark'] != $dataRemark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $dataRemark == '' ? 'Empty Value' : $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='$currentDataName',business_no = '$mrcht_business_no',control_account = '$mrcht_control_account',code = '$mrcht_code',email = '$mrcht_email', contact = '$mrcht_contact', address ='$mrcht_address', person_in_charges ='$mrcht_pic', person_in_charges_contact ='$mrcht_pic_contact', remark ='$dataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
                        $returnData = mysqli_query($finance_connect, $query);
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

$currentMerchantControlAccount = isset($mrcht_control_account)
    ? $mrcht_control_account
    : (isset($row['control_account']) ? (string) $row['control_account'] : '');
$currentMerchantCode = isset($mrcht_code)
    ? $mrcht_code
    : (isset($row['code']) ? (string) $row['code'] : '');
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .required-dot {
    color: red;
    }
    </style>
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
                            <div class="col-md-6 mb-3">

                                <label class="form-label form_lbl" for="currentDataName"><?php echo $pageTitle ?> Name<span class="required-dot">*</span></label>
                                <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?php if (isset($row['name'])) echo $row['name'] ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off">
                          
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form_lbl" id="mrcht_business_no_lbl" for="mrcht_business_no"><?php echo $pageTitle ?> Business No</label>
                                <input class="form-control" type="text" name="mrcht_business_no" id="mrcht_business_no" value="<?php
                                                                                                                                if (isset($dataExisted) && isset($row['business_no']) && !isset($mrcht_business_no)) {
                                                                                                                                    echo $row['business_no'];
                                                                                                                                } else if (isset($dataExisted) && isset($row['business_no']) && isset($mrcht_business_no)) {
                                                                                                                                    echo $mrcht_business_no;
                                                                                                                                } else {
                                                                                                                                    echo '';
                                                                                                                                } ?>" <?php if ($act == '') echo 'readonly' ?>>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="mrcht_control_account">Control A/C</label>
                                <input class="form-control" type="text" name="mrcht_control_account" id="mrcht_control_account" maxlength="9" value="<?php echo htmlspecialchars($currentMerchantControlAccount, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off">
                                <?php if (isset($control_account_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $control_account_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form_lbl" for="mrcht_code">Code</label>
                                <input class="form-control" type="text" name="mrcht_code" id="mrcht_code" maxlength="9" value="<?php echo htmlspecialchars($currentMerchantCode, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off">
                                <?php if (isset($code_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $code_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">

                                <label class="form-label form_lbl" id="mrcht_contact_lbl" for="mrcht_contact"><?php echo $pageTitle ?> Contact</label>
                                <input class="form-control" type="text" name="mrcht_contact" id="mrcht_contact" value="<?php
                                                                                                                                    if (isset($mrcht_contact)) {
                                                                                                                                        echo htmlspecialchars($mrcht_contact, ENT_QUOTES, 'UTF-8');
                                                                                                                                    } else if (isset($dataExisted) && isset($row['contact'])) {
                                                                                                                                        echo htmlspecialchars($row['contact'], ENT_QUOTES, 'UTF-8');
                                                                                                                                    } else {
                                                                                                                                        echo '';
                                                                                                                                    } ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off">
                                <?php if (isset($contact_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $contact_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                            <label class="form-label form_lbl" id="mrcht_email_lbl" for="mrcht_email"><?php echo $pageTitle ?> Email<span class="required-dot">*</span></label>
                                <input class="form-control" type="text" name="mrcht_email" id="mrcht_email" value="<?php
                                                                                                                    if (isset($dataExisted) && isset($row['email']) && !isset($mrcht_email)) {
                                                                                                                        echo $row['email'];
                                                                                                                    } else if (isset($dataExisted) && isset($row['email']) && isset($mrcht_email)) {
                                                                                                                        echo $mrcht_email;
                                                                                                                    } else {
                                                                                                                        echo '';
                                                                                                                    }
                                                                                                                    ?>" <?php if ($act == '') echo 'readonly'  ?>>
                                <?php if (isset($email_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $email_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" id="mrcht_address_lbl" for="mrcht_address"><?php echo $pageTitle ?> Address</label>
                        <input class="form-control" type="text" name="mrcht_address" id="mrcht_address" value="<?php
                                                                                                                if (isset($dataExisted) && isset($row['address']) && !isset($mrcht_address)) {
                                                                                                                    echo $row['address'];
                                                                                                                } else if (isset($dataExisted) && isset($row['address']) && isset($mrcht_address)) {
                                                                                                                    echo $mrcht_address;
                                                                                                                } else {
                                                                                                                    echo '';
                                                                                                                } ?>" <?php if ($act == '') echo 'readonly' ?>>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="mrcht_pic_lbl" for="mrcht_pic">Person In Charge<span class="required-dot">*</span></label>
                                <input class="form-control" type="text" name="mrcht_pic" id="mrcht_pic" value="<?php
                                                                                                                if (isset($dataExisted) && isset($row['person_in_charges']) && !isset($mrcht_pic)) {
                                                                                                                    echo $row['person_in_charges'];
                                                                                                                } else if (isset($dataExisted) && isset($row['person_in_charges']) && isset($mrcht_pic)) {
                                                                                                                    echo $mrcht_pic;
                                                                                                                } else {
                                                                                                                    echo '';
                                                                                                                }
                                                                                                                ?>" <?php if ($act == '') echo 'readonly' ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form_lbl" id="mrcht_pic_contact_lbl" for="mrcht_pic_contact">Person In Charge Contact</label>
                                <input class="form-control" type="text" name="mrcht_pic_contact" id="mrcht_pic_contact" value="<?php
                                                                                                                                            if (isset($mrcht_pic_contact)) {
                                                                                                                                                echo htmlspecialchars($mrcht_pic_contact, ENT_QUOTES, 'UTF-8');
                                                                                                                                            } else if (isset($dataExisted) && isset($row['person_in_charges_contact'])) {
                                                                                                                                                echo htmlspecialchars($row['person_in_charges_contact'], ENT_QUOTES, 'UTF-8');
                                                                                                                                            } else {
                                                                                                                                                echo '';
                                                                                                                                            }
                                                                                                                                            ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off">
                                <?php if (isset($pic_contact_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $pic_contact_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="currentDataRemark"><?php echo $pageTitle ?> Remark</label>
                        <textarea class="form-control" name="currentDataRemark" id="currentDataRemark" rows="3" <?php if ($act == '') echo 'readonly' ?>><?php if (isset($row['remark'])) echo $row['remark'] ?></textarea>
                    </div>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        <?php
        include "../js/merchant.js"
            ?>
        //Initial Page And Action Value
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ''; ?>";

        checkCurrentPage(page, action);
        setButtonColor();
        setAutofocus(action);
        preloader(300, action);

       
    </script>

</body>

</html>
