<?php
$currentPagePin = 127;
$pageTitle = "Company";

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = COMPANY;
$sqlAccountTbl = SQL_ACC;

function cmpEsc($conn, $value)
{
    return mysqli_real_escape_string($conn, (string) $value);
}

function cmpIdTypeOptions()
{
    return array(
        '0' => 'Empty',
        '1' => 'Reg No(New)',
        '2' => 'NRIC',
        '3' => 'Passport',
        '4' => 'ARMY',
        '5' => 'Reg No(Old)',
    );
}

function cmpSqlAccountOptions($connect, $tblName)
{
    $options = array();
    $result = mysqli_query($connect, "SELECT id, name FROM " . $tblName . " WHERE status='A' ORDER BY name ASC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int) (isset($row['id']) ? $row['id'] : 0);
            if ($id > 0) {
                $options[$id] = (string) (isset($row['name']) ? $row['name'] : '');
            }
        }
    }
    return $options;
}

// Current Page Action And Data ID
$dataId = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

// Page Redirect Link , Clean LocalStorage
$redirectPage = $SITEURL . '/settings/company_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if ((!$dataId && !$act) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
}

$sqlAccountOptions = cmpSqlAccountOptions($connect, $sqlAccountTbl);
$idTypeOptions = cmpIdTypeOptions();

// Get data row
$row = array();
$result = false;
if ($dataId) {
    $result = getData('*', "id = '" . (int) $dataId . "'", '', $tblName, $connect);
}

if ($dataId && (!$result || !($row = $result->fetch_assoc())) && $act != 'I') {
    $errorExist = 1;
    $act = "F";
}

if ($act == 'D') {
    deleteRecord($tblName, '', $dataId, isset($row['name']) ? $row['name'] : '', $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

if ($dataId && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . (isset($row['name']) ? $row['name'] : '') . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = array(
        'log_act' => $pageAction,
        'cdate'   => $cdate,
        'ctime'   => $ctime,
        'uid'     => USER_ID,
        'cby'     => USER_ID,
        'act_msg' => $viewActMsg,
        'page'    => $pageTitle,
        'connect' => $connect,
    );
    audit_log($log);
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':
            $fields = array(
                'name' => postSpaceFilter('currentDataName'),
                'code' => postSpaceFilter('currentDataCode'),
                'id_no' => postSpaceFilter('currentIdNo'),
                'address1' => postSpaceFilter('address1'),
                'address2' => postSpaceFilter('address2'),
                'address3' => postSpaceFilter('address3'),
                'address4' => postSpaceFilter('address4'),
                'postcode' => postSpaceFilter('postcode'),
                'city' => postSpaceFilter('city'),
                'state' => postSpaceFilter('state'),
                'country' => strtoupper(postSpaceFilter('country')),
                'phone1' => postSpaceFilter('phone1'),
                'sales_tax_no' => postSpaceFilter('salesTaxNo'),
                'service_tax_no' => postSpaceFilter('serviceTaxNo'),
                'tin' => postSpaceFilter('tin'),
                'id_type' => postSpaceFilter('idType'),
                'tourism_no' => postSpaceFilter('tourismNo'),
                'sic' => postSpaceFilter('sic'),
                'income' => postSpaceFilter('income'),
                'submission_type' => postSpaceFilter('submissionType'),
                'irbm_classification' => postSpaceFilter('irbmClassification'),
                'tax_exemption_reason' => postSpaceFilter('taxExemptionReason'),
                'sql_account_id' => postSpaceFilter('sqlAccountId'),
                'remark' => postSpaceFilter('currentDataRemark'),
            );

            $requiredFields = array(
                'name', 'code', 'id_no', 'address1', 'address2', 'address3', 'address4',
                'postcode', 'city', 'state', 'country', 'phone1', 'tin', 'id_type',
                'submission_type', 'irbm_classification', 'sql_account_id'
            );

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $fields['name'], $tblName, $connect, $dataId)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                break;
            }

            if (isDuplicateRecord("code", $fields['code'], $tblName, $connect, $dataId)) {
                $err = "Duplicate record found for " . $pageTitle . " code.";
                break;
            }

            foreach ($requiredFields as $requiredField) {
                if (!isset($fields[$requiredField]) || trim((string) $fields[$requiredField]) === '') {
                    $err = "Please fill in all required fields.";
                    break;
                }
            }
            if (isset($err)) break;

            if (strlen($fields['country']) !== 2) {
                $err = "Country must be a 2-character code.";
                break;
            }

            if (!array_key_exists((string) $fields['id_type'], $idTypeOptions)) {
                $err = "Invalid ID Type selected.";
                break;
            }

            $sqlAccId = (int) $fields['sql_account_id'];
            if ($sqlAccId <= 0 || !isset($sqlAccountOptions[$sqlAccId])) {
                $err = "Please select a valid SQL Account.";
                break;
            }
            $fields['sql_account_id'] = (string) $sqlAccId;

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    foreach ($fields as $k => $v) {
                        if ($v !== '') {
                            $newvalarr[] = $v;
                            $datafield[] = $k;
                        }
                    }

                    $query = "INSERT INTO " . $tblName . " (
                        name, code, id_no, address1, address2, address3, address4,
                        postcode, city, state, country, phone1, sales_tax_no, service_tax_no,
                        tin, id_type, tourism_no, sic, income, submission_type,
                        irbm_classification, tax_exemption_reason, sql_account_id, remark,
                        create_by, create_date, create_time
                    ) VALUES (
                        '" . cmpEsc($connect, $fields['name']) . "',
                        '" . cmpEsc($connect, $fields['code']) . "',
                        '" . cmpEsc($connect, $fields['id_no']) . "',
                        '" . cmpEsc($connect, $fields['address1']) . "',
                        '" . cmpEsc($connect, $fields['address2']) . "',
                        '" . cmpEsc($connect, $fields['address3']) . "',
                        '" . cmpEsc($connect, $fields['address4']) . "',
                        '" . cmpEsc($connect, $fields['postcode']) . "',
                        '" . cmpEsc($connect, $fields['city']) . "',
                        '" . cmpEsc($connect, $fields['state']) . "',
                        '" . cmpEsc($connect, $fields['country']) . "',
                        '" . cmpEsc($connect, $fields['phone1']) . "',
                        '" . cmpEsc($connect, $fields['sales_tax_no']) . "',
                        '" . cmpEsc($connect, $fields['service_tax_no']) . "',
                        '" . cmpEsc($connect, $fields['tin']) . "',
                        '" . cmpEsc($connect, $fields['id_type']) . "',
                        '" . cmpEsc($connect, $fields['tourism_no']) . "',
                        '" . cmpEsc($connect, $fields['sic']) . "',
                        '" . cmpEsc($connect, $fields['income']) . "',
                        '" . cmpEsc($connect, $fields['submission_type']) . "',
                        '" . cmpEsc($connect, $fields['irbm_classification']) . "',
                        '" . cmpEsc($connect, $fields['tax_exemption_reason']) . "',
                        '" . cmpEsc($connect, $fields['sql_account_id']) . "',
                        '" . cmpEsc($connect, $fields['remark']) . "',
                        '" . USER_ID . "',
                        curdate(), curtime()
                    )";

                    $returnData = mysqli_query($connect, $query);
                    $dataId = $connect->insert_id;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    $compareFields = array(
                        'name', 'code', 'id_no', 'address1', 'address2', 'address3', 'address4',
                        'postcode', 'city', 'state', 'country', 'phone1', 'sales_tax_no',
                        'service_tax_no', 'tin', 'id_type', 'tourism_no', 'sic', 'income',
                        'submission_type', 'irbm_classification', 'tax_exemption_reason',
                        'sql_account_id', 'remark'
                    );

                    foreach ($compareFields as $cmpField) {
                        $oldVal = isset($row[$cmpField]) ? trim((string) $row[$cmpField]) : '';
                        $newVal = isset($fields[$cmpField]) ? trim((string) $fields[$cmpField]) : '';
                        if ($oldVal !== $newVal) {
                            $oldvalarr[] = ($oldVal === '' ? 'Empty Value' : $oldVal);
                            $chgvalarr[] = ($newVal === '' ? 'Empty Value' : $newVal);
                            $datafield[] = $cmpField;
                        }
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $safeID = (int) $dataId;
                        $query = "UPDATE " . $tblName . " SET
                            name ='" . cmpEsc($connect, $fields['name']) . "',
                            code ='" . cmpEsc($connect, $fields['code']) . "',
                            id_no ='" . cmpEsc($connect, $fields['id_no']) . "',
                            address1 ='" . cmpEsc($connect, $fields['address1']) . "',
                            address2 ='" . cmpEsc($connect, $fields['address2']) . "',
                            address3 ='" . cmpEsc($connect, $fields['address3']) . "',
                            address4 ='" . cmpEsc($connect, $fields['address4']) . "',
                            postcode ='" . cmpEsc($connect, $fields['postcode']) . "',
                            city ='" . cmpEsc($connect, $fields['city']) . "',
                            state ='" . cmpEsc($connect, $fields['state']) . "',
                            country ='" . cmpEsc($connect, $fields['country']) . "',
                            phone1 ='" . cmpEsc($connect, $fields['phone1']) . "',
                            sales_tax_no ='" . cmpEsc($connect, $fields['sales_tax_no']) . "',
                            service_tax_no ='" . cmpEsc($connect, $fields['service_tax_no']) . "',
                            tin ='" . cmpEsc($connect, $fields['tin']) . "',
                            id_type ='" . cmpEsc($connect, $fields['id_type']) . "',
                            tourism_no ='" . cmpEsc($connect, $fields['tourism_no']) . "',
                            sic ='" . cmpEsc($connect, $fields['sic']) . "',
                            income ='" . cmpEsc($connect, $fields['income']) . "',
                            submission_type ='" . cmpEsc($connect, $fields['submission_type']) . "',
                            irbm_classification ='" . cmpEsc($connect, $fields['irbm_classification']) . "',
                            tax_exemption_reason ='" . cmpEsc($connect, $fields['tax_exemption_reason']) . "',
                            sql_account_id ='" . cmpEsc($connect, $fields['sql_account_id']) . "',
                            remark ='" . cmpEsc($connect, $fields['remark']) . "',
                            update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "'
                            WHERE id = '" . $safeID . "'";

                        $returnData = mysqli_query($connect, $query);
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            if (isset($query)) {
                $log = array(
                    'log_act'      => $pageAction,
                    'cdate'        => $cdate,
                    'ctime'        => $ctime,
                    'uid'          => USER_ID,
                    'cby'          => USER_ID,
                    'query_rec'    => $query,
                    'query_table'  => $tblName,
                    'page'         => $pageTitle,
                    'connect'      => $connect,
                );

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : (isset($errorMsg) ? $errorMsg : '')));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : (isset($errorMsg) ? $errorMsg : '')));
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

$isReadonly = ($act == '');

function cmpFieldValue($row, $field)
{
    return isset($row[$field]) ? (string) $row[$field] : '';
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
            <p><a href="<?= $redirectPage ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i><?= $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-11 col-md-10 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-4">
                        <h2><?= $pageActionTitle ?></h2>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="currentDataName">Company Name<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="255" name="currentDataName" id="currentDataName" value="<?= htmlspecialchars(cmpFieldValue($row, 'name'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="currentDataCode">Company Code<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="100" name="currentDataCode" id="currentDataCode" value="<?= htmlspecialchars(cmpFieldValue($row, 'code'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="currentIdNo">ID No<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="20" name="currentIdNo" id="currentIdNo" value="<?= htmlspecialchars(cmpFieldValue($row, 'id_no'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="idType">ID Type<span class="requireRed">*</span></label>
                            <select class="form-select" name="idType" id="idType" <?= $isReadonly ? 'disabled' : '' ?> required>
                                <option value="">Select ID Type</option>
                                <?php foreach ($idTypeOptions as $v => $label) { ?>
                                    <option value="<?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?>" <?= (cmpFieldValue($row, 'id_type') === (string) $v) ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="sqlAccountId">SQL Account<span class="requireRed">*</span></label>
                            <select class="form-select" name="sqlAccountId" id="sqlAccountId" <?= $isReadonly ? 'disabled' : '' ?> required>
                                <option value="">Select SQL Account</option>
                                <?php foreach ($sqlAccountOptions as $accId => $accName) { ?>
                                    <option value="<?= (int) $accId ?>" <?= ((int) cmpFieldValue($row, 'sql_account_id') === (int) $accId) ? 'selected' : '' ?>><?= htmlspecialchars((string) $accName, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="tin">TIN<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="14" name="tin" id="tin" value="<?= htmlspecialchars(cmpFieldValue($row, 'tin'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="submissionType">Submission Type<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="100" name="submissionType" id="submissionType" value="<?= htmlspecialchars(cmpFieldValue($row, 'submission_type'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="irbmClassification">IRBM Classification<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="3" name="irbmClassification" id="irbmClassification" value="<?= htmlspecialchars(cmpFieldValue($row, 'irbm_classification'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="phone1">Phone 1<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="200" name="phone1" id="phone1" value="<?= htmlspecialchars(cmpFieldValue($row, 'phone1'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="country">Country (2 chars)<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="2" name="country" id="country" value="<?= htmlspecialchars(cmpFieldValue($row, 'country'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="postcode">Postcode<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="10" name="postcode" id="postcode" value="<?= htmlspecialchars(cmpFieldValue($row, 'postcode'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="city">City<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="50" name="city" id="city" value="<?= htmlspecialchars(cmpFieldValue($row, 'city'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="state">State<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="50" name="state" id="state" value="<?= htmlspecialchars(cmpFieldValue($row, 'state'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="address1">Address 1<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="60" name="address1" id="address1" value="<?= htmlspecialchars(cmpFieldValue($row, 'address1'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="address2">Address 2<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="60" name="address2" id="address2" value="<?= htmlspecialchars(cmpFieldValue($row, 'address2'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="address3">Address 3<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="60" name="address3" id="address3" value="<?= htmlspecialchars(cmpFieldValue($row, 'address3'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="address4">Address 4<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="60" name="address4" id="address4" value="<?= htmlspecialchars(cmpFieldValue($row, 'address4'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="salesTaxNo">Sales Tax No</label>
                            <input class="form-control" type="text" maxlength="25" name="salesTaxNo" id="salesTaxNo" value="<?= htmlspecialchars(cmpFieldValue($row, 'sales_tax_no'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="serviceTaxNo">Service Tax No</label>
                            <input class="form-control" type="text" maxlength="25" name="serviceTaxNo" id="serviceTaxNo" value="<?= htmlspecialchars(cmpFieldValue($row, 'service_tax_no'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="tourismNo">Tourism No</label>
                            <input class="form-control" type="text" maxlength="17" name="tourismNo" id="tourismNo" value="<?= htmlspecialchars(cmpFieldValue($row, 'tourism_no'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="sic">SIC</label>
                            <input class="form-control" type="text" maxlength="10" name="sic" id="sic" value="<?= htmlspecialchars(cmpFieldValue($row, 'sic'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="income">Income</label>
                            <input class="form-control" type="text" maxlength="3" name="income" id="income" value="<?= htmlspecialchars(cmpFieldValue($row, 'income'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label form_lbl" for="taxExemptionReason">Tax Exemption Reason</label>
                            <input class="form-control" type="text" maxlength="300" name="taxExemptionReason" id="taxExemptionReason" value="<?= htmlspecialchars(cmpFieldValue($row, 'tax_exemption_reason'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label form_lbl" for="currentDataRemark">Remark</label>
                            <textarea class="form-control" name="currentDataRemark" id="currentDataRemark" rows="3" <?= $isReadonly ? 'readonly' : '' ?>><?= htmlspecialchars(cmpFieldValue($row, 'remark'), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    </div>

                    <div id="err_msg" class="mt-2">
                        <span class="mt-n1" id="errorSpan"><?= isset($err) ? htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>

                    <div class="form-group mt-4 d-flex justify-content-center flex-md-row flex-column">
                        <?= ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= $pageTitle ?>";
        const action = "<?= isset($act) ? $act : '' ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
    </script>
</body>
</html>
