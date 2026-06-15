<?php
$currentPagePin = 135;
$pageTitle = "Purchase Order";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = PURCHASE_ORDER;
$companyTbl = COMPANY;
$sqlAccountTbl = SQL_ACC;

function poEsc($conn, $value)
{
    return mysqli_real_escape_string($conn, (string) $value);
}

function poFieldValue($row, $field)
{
    return isset($row[$field]) ? (string) $row[$field] : '';
}

$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirect_page = $SITEURL . '/purchase_order_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if ((!$dataID && !$act) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
}

$companyOptions = array();
$companyMap = array();
$sqlAccountNameMap = array();

$sqlAccRst = mysqli_query($connect, "SELECT id, name FROM " . $sqlAccountTbl . " WHERE status='A' ORDER BY name ASC");
if ($sqlAccRst) {
    while ($acc = mysqli_fetch_assoc($sqlAccRst)) {
        $sqlAccountNameMap[(int) $acc['id']] = (string) $acc['name'];
    }
}

$companyRst = mysqli_query($connect, "SELECT * FROM " . $companyTbl . " WHERE status='A' ORDER BY name ASC");
if ($companyRst) {
    while ($cmp = mysqli_fetch_assoc($companyRst)) {
        $cmpName = trim((string) (isset($cmp['name']) ? $cmp['name'] : ''));
        if ($cmpName === '') {
            continue;
        }
        $companyOptions[] = $cmpName;
        $companyMap[$cmpName] = array(
            'code' => (string) (isset($cmp['code']) ? $cmp['code'] : ''),
            'id_no' => (string) (isset($cmp['id_no']) ? $cmp['id_no'] : ''),
            'address1' => (string) (isset($cmp['address1']) ? $cmp['address1'] : ''),
            'address2' => (string) (isset($cmp['address2']) ? $cmp['address2'] : ''),
            'address3' => (string) (isset($cmp['address3']) ? $cmp['address3'] : ''),
            'address4' => (string) (isset($cmp['address4']) ? $cmp['address4'] : ''),
            'postcode' => (string) (isset($cmp['postcode']) ? $cmp['postcode'] : ''),
            'city' => (string) (isset($cmp['city']) ? $cmp['city'] : ''),
            'state' => (string) (isset($cmp['state']) ? $cmp['state'] : ''),
            'country' => (string) (isset($cmp['country']) ? $cmp['country'] : ''),
            'phone1' => (string) (isset($cmp['phone1']) ? $cmp['phone1'] : ''),
            'sales_tax_no' => (string) (isset($cmp['sales_tax_no']) ? $cmp['sales_tax_no'] : ''),
            'service_tax_no' => (string) (isset($cmp['service_tax_no']) ? $cmp['service_tax_no'] : ''),
            'tin' => (string) (isset($cmp['tin']) ? $cmp['tin'] : ''),
            'id_type' => (string) (isset($cmp['id_type']) ? $cmp['id_type'] : ''),
            'tourism_no' => (string) (isset($cmp['tourism_no']) ? $cmp['tourism_no'] : ''),
            'sic' => (string) (isset($cmp['sic']) ? $cmp['sic'] : ''),
            'income' => (string) (isset($cmp['income']) ? $cmp['income'] : ''),
            'submission_type' => (string) (isset($cmp['submission_type']) ? $cmp['submission_type'] : ''),
            'irbm_classification' => (string) (isset($cmp['irbm_classification']) ? $cmp['irbm_classification'] : ''),
            'tax_exemption_reason' => (string) (isset($cmp['tax_exemption_reason']) ? $cmp['tax_exemption_reason'] : ''),
            'remark' => (string) (isset($cmp['remark']) ? $cmp['remark'] : ''),
            'sql_account_id' => (string) (isset($cmp['sql_account_id']) ? $cmp['sql_account_id'] : ''),
            'sql_account_name' => isset($sqlAccountNameMap[(int) (isset($cmp['sql_account_id']) ? $cmp['sql_account_id'] : 0)])
                ? $sqlAccountNameMap[(int) $cmp['sql_account_id']]
                : '',
        );
    }
}

$row = array();
$rst = false;
if ($dataID) {
    $rst = getData('*', "id = '" . (int) $dataID . "'", '', $tblName, $connect);
}

if ($dataID && (!$rst || !($row = $rst->fetch_assoc())) && $act != 'I') {
    $errorExist = 1;
    $act = "F";
}

if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, isset($row['doc_no']) ? $row['doc_no'] : '', $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . (isset($row['doc_no']) ? $row['doc_no'] : '') . "</b> from <b><i>$tblName Table</i></b>.";
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
                'doc_date' => postSpaceFilter('doc_date'),
                'doc_no' => postSpaceFilter('doc_no'),
                'code' => strtoupper(postSpaceFilter('code')),
                'company_name' => postSpaceFilter('company_name'),
                'description_hdr' => postSpaceFilter('description_hdr'),
                'seq' => postSpaceFilter('seq'),
                'account' => postSpaceFilter('account'),
                'item_code' => postSpaceFilter('item_code'),
                'description_dtl' => postSpaceFilter('description_dtl'),
                'qty' => postSpaceFilter('qty'),
                'uom' => postSpaceFilter('uom'),
                'unit_price' => postSpaceFilter('unit_price'),
                'amount' => postSpaceFilter('amount'),
                'remark' => postSpaceFilter('remark'),
            );

            $requiredFields = array('doc_date', 'doc_no', 'code', 'company_name', 'seq', 'item_code', 'qty', 'uom', 'unit_price');
            foreach ($requiredFields as $requiredField) {
                if (!isset($fields[$requiredField]) || trim((string) $fields[$requiredField]) === '') {
                    $err = "Please fill in all required fields.";
                    break;
                }
            }
            if (isset($err)) {
                break;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fields['doc_date'])) {
                $err = 'Document date must be in YYYY-MM-DD format.';
                break;
            }

            if (!is_numeric($fields['seq']) || (int) $fields['seq'] <= 0) {
                $err = 'SEQ must be a positive number.';
                break;
            }

            if (!is_numeric($fields['qty']) || (float) $fields['qty'] < 0) {
                $err = 'QTY must be a non-negative number.';
                break;
            }

            if (!is_numeric($fields['unit_price']) || (float) $fields['unit_price'] < 0) {
                $err = 'Unit Price must be a non-negative number.';
                break;
            }

            if ($fields['amount'] === '' || !is_numeric($fields['amount'])) {
                $fields['amount'] = (float) $fields['qty'] * (float) $fields['unit_price'];
            }

            $safeCompanyName = poEsc($connect, $fields['company_name']);
            $companyQ = "SELECT * FROM " . $companyTbl . " WHERE name='" . $safeCompanyName . "' AND status='A' LIMIT 1";
            $companyRs = mysqli_query($connect, $companyQ);
            $companyRow = ($companyRs && mysqli_num_rows($companyRs) === 1) ? mysqli_fetch_assoc($companyRs) : null;
            if (!$companyRow) {
                $err = 'Selected company is invalid or inactive.';
                break;
            }

            $fields['sql_account_id'] = (int) (isset($companyRow['sql_account_id']) ? $companyRow['sql_account_id'] : 0);
            if ($fields['sql_account_id'] <= 0) {
                $err = 'Selected company has invalid SQL account mapping.';
                break;
            }

            $docNoEsc = poEsc($connect, $fields['doc_no']);
            $itemCodeEsc = poEsc($connect, $fields['item_code']);
            $seqNo = (int) $fields['seq'];
            $dupSql = "SELECT id FROM " . $tblName . " WHERE status='A' AND doc_no='" . $docNoEsc . "' AND seq='" . $seqNo . "' AND item_code='" . $itemCodeEsc . "'";
            if ($action === 'updData') {
                $dupSql .= " AND id != '" . (int) $dataID . "'";
            }
            $dupRst = mysqli_query($connect, $dupSql);
            if ($dupRst && mysqli_num_rows($dupRst) > 0) {
                $err = 'Duplicate PO row found for same DOC NO + SEQ + ITEM CODE.';
                break;
            }

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if ($action === 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    $query = "INSERT INTO " . $tblName . " (
                        doc_date, doc_no, code, company_name, description_hdr, seq, account,
                        item_code, description_dtl, qty, uom, unit_price, amount, sql_account_id,
                        remark, create_by, create_date, create_time
                    ) VALUES (
                        '" . poEsc($connect, $fields['doc_date']) . "',
                        '" . poEsc($connect, $fields['doc_no']) . "',
                        '" . poEsc($connect, $fields['code']) . "',
                        '" . poEsc($connect, $fields['company_name']) . "',
                        '" . poEsc($connect, $fields['description_hdr']) . "',
                        '" . (int) $fields['seq'] . "',
                        '" . poEsc($connect, $fields['account']) . "',
                        '" . poEsc($connect, $fields['item_code']) . "',
                        '" . poEsc($connect, $fields['description_dtl']) . "',
                        '" . number_format((float) $fields['qty'], 2, '.', '') . "',
                        '" . poEsc($connect, $fields['uom']) . "',
                        '" . number_format((float) $fields['unit_price'], 2, '.', '') . "',
                        '" . number_format((float) $fields['amount'], 2, '.', '') . "',
                        '" . (int) $fields['sql_account_id'] . "',
                        '" . poEsc($connect, $fields['remark']) . "',
                        '" . USER_ID . "',
                        curdate(), curtime()
                    )";
                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;

                    foreach ($fields as $k => $v) {
                        if ((string) $v !== '') {
                            $datafield[] = $k;
                            $newvalarr[] = (string) $v;
                        }
                    }
                    $datafield[] = 'sql_account_id';
                    $newvalarr[] = (string) $fields['sql_account_id'];
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    $compareFields = array(
                        'doc_date', 'doc_no', 'code', 'company_name', 'description_hdr', 'seq', 'account',
                        'item_code', 'description_dtl', 'qty', 'uom', 'unit_price', 'amount', 'sql_account_id', 'remark'
                    );

                    foreach ($compareFields as $cmpField) {
                        $newVal = isset($fields[$cmpField]) ? (string) $fields[$cmpField] : '';
                        if ($cmpField === 'sql_account_id') {
                            $newVal = (string) $fields['sql_account_id'];
                        }
                        $oldVal = isset($row[$cmpField]) ? (string) $row[$cmpField] : '';
                        if ($oldVal !== $newVal) {
                            $datafield[] = $cmpField;
                            $oldvalarr[] = ($oldVal === '' ? 'Empty Value' : $oldVal);
                            $chgvalarr[] = ($newVal === '' ? 'Empty Value' : $newVal);
                        }
                    }

                    if (!empty($chgvalarr)) {
                        $query = "UPDATE " . $tblName . " SET
                            doc_date='" . poEsc($connect, $fields['doc_date']) . "',
                            doc_no='" . poEsc($connect, $fields['doc_no']) . "',
                            code='" . poEsc($connect, $fields['code']) . "',
                            company_name='" . poEsc($connect, $fields['company_name']) . "',
                            description_hdr='" . poEsc($connect, $fields['description_hdr']) . "',
                            seq='" . (int) $fields['seq'] . "',
                            account='" . poEsc($connect, $fields['account']) . "',
                            item_code='" . poEsc($connect, $fields['item_code']) . "',
                            description_dtl='" . poEsc($connect, $fields['description_dtl']) . "',
                            qty='" . number_format((float) $fields['qty'], 2, '.', '') . "',
                            uom='" . poEsc($connect, $fields['uom']) . "',
                            unit_price='" . number_format((float) $fields['unit_price'], 2, '.', '') . "',
                            amount='" . number_format((float) $fields['amount'], 2, '.', '') . "',
                            sql_account_id='" . (int) $fields['sql_account_id'] . "',
                            remark='" . poEsc($connect, $fields['remark']) . "',
                            update_date=curdate(), update_time=curtime(), update_by='" . USER_ID . "'
                            WHERE id='" . (int) $dataID . "'";
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
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : (isset($errorMsg) ? $errorMsg : '')));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : (isset($errorMsg) ? $errorMsg : '')));
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

$isReadonly = ($act == '');
$currentCompanyName = poFieldValue($row, 'company_name');
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .autofill-field {
            background-color: #e9ecef !important;
            color: #495057;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
        

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i><?= $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-11 col-md-10 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-4">
                        <h2><?= $pageActionTitle ?></h2>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label form_lbl" for="doc_date">Doc Date<span class="requireRed">*</span></label>
                            <input class="form-control" type="date" name="doc_date" id="doc_date" value="<?= htmlspecialchars(poFieldValue($row, 'doc_date'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form_lbl" for="doc_no">Doc No<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="20" name="doc_no" id="doc_no" value="<?= htmlspecialchars(poFieldValue($row, 'doc_no'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form_lbl" for="code">Code<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="10" name="code" id="code" value="<?= htmlspecialchars(poFieldValue($row, 'code'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="company_name">Company Name<span class="requireRed">*</span></label>
                            <select class="form-control" name="company_name" id="company_name" <?= $isReadonly ? 'disabled' : '' ?> required>
                                <option value="">Select Company</option>
                                <?php foreach ($companyOptions as $cmpName) { ?>
                                    <option value="<?= htmlspecialchars($cmpName, ENT_QUOTES, 'UTF-8') ?>" <?= ($currentCompanyName === $cmpName) ? 'selected' : '' ?>><?= htmlspecialchars($cmpName, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="description_hdr">Description Header</label>
                            <input class="form-control" type="text" maxlength="200" name="description_hdr" id="description_hdr" value="<?= htmlspecialchars(poFieldValue($row, 'description_hdr'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label form_lbl" for="seq">SEQ<span class="requireRed">*</span></label>
                            <input class="form-control" type="number" min="1" name="seq" id="seq" value="<?= htmlspecialchars(poFieldValue($row, 'seq') !== '' ? poFieldValue($row, 'seq') : '1', ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form_lbl" for="account">Account</label>
                            <input class="form-control" type="text" maxlength="10" name="account" id="account" value="<?= htmlspecialchars(poFieldValue($row, 'account'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form_lbl" for="item_code">Item Code<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="30" name="item_code" id="item_code" value="<?= htmlspecialchars(poFieldValue($row, 'item_code'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form_lbl" for="uom">UOM<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" maxlength="10" name="uom" id="uom" value="<?= htmlspecialchars(poFieldValue($row, 'uom'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required autocomplete="off">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="description_dtl">Description Detail</label>
                            <input class="form-control" type="text" maxlength="200" name="description_dtl" id="description_dtl" value="<?= htmlspecialchars(poFieldValue($row, 'description_dtl'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form_lbl" for="qty">Qty<span class="requireRed">*</span></label>
                            <input class="form-control" type="number" step="0.01" min="0" name="qty" id="qty" value="<?= htmlspecialchars(poFieldValue($row, 'qty'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form_lbl" for="unit_price">Unit Price<span class="requireRed">*</span></label>
                            <input class="form-control" type="number" step="0.01" min="0" name="unit_price" id="unit_price" value="<?= htmlspecialchars(poFieldValue($row, 'unit_price'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?> required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form_lbl" for="amount">Amount</label>
                            <input class="form-control" type="number" step="0.01" min="0" name="amount" id="amount" value="<?= htmlspecialchars(poFieldValue($row, 'amount'), ENT_QUOTES, 'UTF-8') ?>" <?= $isReadonly ? 'readonly' : '' ?>>
                        </div>

                        <div class="col-md-12 mt-4">
                            <h5 class="mb-2">Auto-filled Company Information</h5>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_code">Company Code</label>
                            <input class="form-control autofill-field" type="text" id="company_code" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_id_no">Company ID No</label>
                            <input class="form-control autofill-field" type="text" id="company_id_no" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_phone1">Company Phone</label>
                            <input class="form-control autofill-field" type="text" id="company_phone1" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="company_sql_account_name">Company SQL Account</label>
                            <input class="form-control autofill-field" type="text" id="company_sql_account_name" readonly>
                            <input type="hidden" id="company_sql_account_id" name="company_sql_account_id" value="<?= htmlspecialchars(poFieldValue($row, 'sql_account_id'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="company_postcode">Postcode</label>
                            <input class="form-control autofill-field" type="text" id="company_postcode" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_city">City</label>
                            <input class="form-control autofill-field" type="text" id="company_city" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_state">State</label>
                            <input class="form-control autofill-field" type="text" id="company_state" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_country">Country</label>
                            <input class="form-control autofill-field" type="text" id="company_country" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="company_address1">Address 1</label>
                            <input class="form-control autofill-field" type="text" id="company_address1" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_address2">Address 2</label>
                            <input class="form-control autofill-field" type="text" id="company_address2" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_address3">Address 3</label>
                            <input class="form-control autofill-field" type="text" id="company_address3" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_address4">Address 4</label>
                            <input class="form-control autofill-field" type="text" id="company_address4" readonly>
                        </div>

                        <div class="col-md-12 mt-3">
                            <h6 class="mb-2">Tax and Compliance</h6>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_sales_tax_no">Sales Tax No</label>
                            <input class="form-control autofill-field" type="text" id="company_sales_tax_no" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_service_tax_no">Service Tax No</label>
                            <input class="form-control autofill-field" type="text" id="company_service_tax_no" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_tin">TIN</label>
                            <input class="form-control autofill-field" type="text" id="company_tin" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_id_type">ID Type</label>
                            <input class="form-control autofill-field" type="text" id="company_id_type" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="company_tourism_no">Tourism No</label>
                            <input class="form-control autofill-field" type="text" id="company_tourism_no" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_sic">SIC</label>
                            <input class="form-control autofill-field" type="text" id="company_sic" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_income">Income</label>
                            <input class="form-control autofill-field" type="text" id="company_income" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="company_submission_type">Submission Type</label>
                            <input class="form-control autofill-field" type="text" id="company_submission_type" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="company_irbm_classification">IRBM Classification</label>
                            <input class="form-control autofill-field" type="text" id="company_irbm_classification" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_tax_exemption_reason">Tax Exemption Reason</label>
                            <input class="form-control autofill-field" type="text" id="company_tax_exemption_reason" readonly>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label" for="company_remark">Company Remark</label>
                            <input class="form-control autofill-field" type="text" id="company_remark" readonly>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label form_lbl" for="remark">Remark</label>
                            <textarea class="form-control" name="remark" id="remark" rows="3" <?= $isReadonly ? 'readonly' : '' ?>><?= htmlspecialchars(poFieldValue($row, 'remark'), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    </div>

                    <div id="err_msg" class="mt-2">
                        <span class="mt-n1" id="errorSpan"><?= isset($err) ? htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>

                    <div class="form-group mt-4 d-flex justify-content-center flex-md-row flex-column">
                        <?php if ($act) { ?>
                            <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="<?= htmlspecialchars($actionBtnValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pageActionTitle, ENT_QUOTES, 'UTF-8') ?></button>
                        <?php } ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.__PURCHASE_ORDER_CONFIG = {
            page: "<?= $pageTitle ?>",
            action: "<?= isset($act) ? $act : '' ?>",
            companyMap: <?= json_encode($companyMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };
    </script>
    <script src="<?= $SITEURL ?>/js/purchase_order.js"></script>
</body>
</html>
