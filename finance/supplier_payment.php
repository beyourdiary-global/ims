<?php
$currentPagePin = 169;
$pageTitle = 'Supplier Payment';

include '../menuHeader.php';
include '../checkCurrentPagePin.php';

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedPageTitle !== '') {
    $pageTitle = $resolvedPageTitle;
}

$tblName = SUPPLIER_PAYMENT;
$dataId = (int) (!empty(input('id')) ? input('id') : post('id'));
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';
$pageAction = getPageAction($act);
$pinAccess = checkCurrentPin($connect, $pageTitle);
$redirectPage = $SITEURL . '/finance/supplier_payment_table.php';
$redirectLink = "<script>location.href = '" . $redirectPage . "';</script>";
$clearLocalStorage = '<script>localStorage.clear();</script>';

function supplierPaymentNormalizeCode($value)
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

function supplierPaymentValidDate($value)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
        && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
}

if ((!$dataId && !$act) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
    exit;
}

$row = array();
if ($dataId) {
    $result = getData('*', "id = '" . $dataId . "'", 'LIMIT 1', $tblName, $finance_connect);
    if ($result && ($loadedRow = $result->fetch_assoc())) {
        $row = $loadedRow;
    } else {
        $_SESSION['tempValConfirmBox'] = true;
        $act = 'F';
        $pageAction = getPageAction($act);
    }
}

if ($act === 'D' && $dataId && !empty($row)) {
    deleteRecord($tblName, '', $dataId, $row['bill_no'], $finance_connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
    echo $redirectLink;
    exit;
}

if ($dataId && !$act && USER_ID && empty($_SESSION['viewChk']) && empty($_SESSION['delChk']) && !empty($row)) {
    $_SESSION['viewChk'] = 1;
    audit_log(array(
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . htmlspecialchars((string) $row['bill_no'], ENT_QUOTES, 'UTF-8') . "</b> from <b><i>$tblName Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($action === 'back') {
        echo $clearLocalStorage . $redirectLink;
        exit;
    }

    if ($action === 'addData' || $action === 'updData') {
        $supplierDocDate = trim((string) postSpaceFilter('supplier_doc_date'));
        $supplierCode = supplierPaymentNormalizeCode(postSpaceFilter('supplier_code'));
        $supplierBillNo = trim((string) postSpaceFilter('supplier_bill_no'));
        $supplierDescription = trim((string) postSpaceFilter('supplier_description'));
        $supplierQuantityRaw = trim((string) post('supplier_quantity'));
        $supplierAmountRaw = trim((string) post('supplier_amount'));
        $supplierAddSstRaw = (string) post('supplier_add_sst');
        $supplierRemark = trim((string) postSpaceFilter('supplier_remark'));
        $error = false;

        if ($supplierDocDate === '' || !supplierPaymentValidDate($supplierDocDate)) {
            $docDateErr = 'Please specify a valid DocDate.';
            $error = true;
        }
        if ($supplierCode === '' || !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $supplierCode)) {
            $codeErr = 'Please select a valid merchant Code.';
            $error = true;
        }
        if ($supplierBillNo === '') {
            $billNoErr = 'Please specify the Bill No.';
            $error = true;
        }
        if ($supplierDescription === '') {
            $descriptionErr = 'Please specify the Description.';
            $error = true;
        }
        $quantityValue = str_replace(',', '', $supplierQuantityRaw);
        if ($supplierQuantityRaw === '' || !is_numeric($quantityValue) || (float) $quantityValue <= 0) {
            $quantityErr = 'Please specify a valid Quantity.';
            $error = true;
        }
        $amountValue = str_replace(',', '', $supplierAmountRaw);
        if ($supplierAmountRaw === '' || !is_numeric($amountValue) || (float) $amountValue <= 0) {
            $amountErr = 'Please specify a valid Amount.';
            $error = true;
        }
        $addSstValue = str_replace(',', '', $supplierAddSstRaw);
        if ($supplierAddSstRaw === '' || !is_numeric($addSstValue) || (float) $addSstValue < 0) {
            $addSstErr = 'Please specify a valid SST amount.';
            $error = true;
        }

        $supplierQuantity = number_format((float) $quantityValue, 3, '.', '');
        $supplierAmount = number_format((float) $amountValue, 2, '.', '');
        $supplierAddSst = number_format((float) $addSstValue, 2, '.', '');
        $supplierTotal = number_format(round((float) $supplierQuantity * (float) $supplierAmount + (float) $supplierAddSst, 2), 2, '.', '');

        if (!$error) {
            $safe = function ($value) use ($finance_connect) {
                return mysqli_real_escape_string($finance_connect, (string) $value);
            };
            $datafield = array();
            $oldvalarr = array();
            $chgvalarr = array();
            $newvalarr = array();
            $query = '';
            $returnData = false;
            $dataChanged = false;

            if ($action === 'addData') {
                $query = "INSERT INTO " . $tblName . " (doc_date, code, bill_no, description, quantity, amount, add_sst, total, remark, create_by, create_date, create_time) VALUES ('" . $safe($supplierDocDate) . "', '" . $safe($supplierCode) . "', '" . $safe($supplierBillNo) . "', '" . $safe($supplierDescription) . "', '" . $safe($supplierQuantity) . "', '" . $safe($supplierAmount) . "', '" . $safe($supplierAddSst) . "', '" . $safe($supplierTotal) . "', '" . $safe($supplierRemark) . "', '" . $safe(USER_ID) . "', CURDATE(), CURTIME())";
                $returnData = mysqli_query($finance_connect, $query);
                if ($returnData) {
                    $dataId = (int) $finance_connect->insert_id;
                    $dataChanged = true;
                    $datafield = array('doc_date', 'code', 'bill_no', 'description', 'quantity', 'amount', 'add_sst', 'total', 'remark');
                    $newvalarr = array($supplierDocDate, $supplierCode, $supplierBillNo, $supplierDescription, $supplierQuantity, $supplierAmount, $supplierAddSst, $supplierTotal, $supplierRemark);
                }
            } else {
                $currentValues = array(
                    'doc_date' => (string) ($row['doc_date'] ?? ''),
                    'code' => (string) ($row['code'] ?? ''),
                    'bill_no' => (string) ($row['bill_no'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'quantity' => number_format((float) ($row['quantity'] ?? 0), 3, '.', ''),
                    'amount' => number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                    'add_sst' => number_format((float) ($row['add_sst'] ?? 0), 2, '.', ''),
                    'total' => number_format((float) ($row['total'] ?? 0), 2, '.', ''),
                    'remark' => (string) ($row['remark'] ?? ''),
                );
                $newValues = array(
                    'doc_date' => $supplierDocDate,
                    'code' => $supplierCode,
                    'bill_no' => $supplierBillNo,
                    'description' => $supplierDescription,
                    'quantity' => $supplierQuantity,
                    'amount' => $supplierAmount,
                    'add_sst' => (string) $supplierAddSst,
                    'total' => $supplierTotal,
                    'remark' => $supplierRemark,
                );
                foreach ($newValues as $field => $newValue) {
                    if ($currentValues[$field] !== $newValue) {
                        $oldvalarr[] = $currentValues[$field] === '' ? 'Empty Value' : $currentValues[$field];
                        $chgvalarr[] = $newValue === '' ? 'Empty Value' : $newValue;
                        $datafield[] = $field;
                    }
                }
                $dataChanged = !empty($datafield);
                if ($dataChanged) {
                    $query = "UPDATE " . $tblName . " SET doc_date = '" . $safe($supplierDocDate) . "', code = '" . $safe($supplierCode) . "', bill_no = '" . $safe($supplierBillNo) . "', description = '" . $safe($supplierDescription) . "', quantity = '" . $safe($supplierQuantity) . "', amount = '" . $safe($supplierAmount) . "', add_sst = '" . $safe($supplierAddSst) . "', total = '" . $safe($supplierTotal) . "', remark = '" . $safe($supplierRemark) . "', update_by = '" . $safe(USER_ID) . "', update_date = CURDATE(), update_time = CURTIME() WHERE id = '" . (int) $dataId . "'";
                    $returnData = mysqli_query($finance_connect, $query);
                } else {
                    $returnData = true;
                    $act = 'NC';
                }
            }

            if ($returnData) {
                $_SESSION['tempValConfirmBox'] = true;
                if ($dataChanged) {
                    audit_log(array(
                        'log_act' => $pageAction,
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => $query,
                        'query_table' => $tblName,
                        'page' => $pageTitle,
                        'connect' => $connect,
                        'newval' => $pageAction === 'Add' ? implodeWithComma($newvalarr) : '',
                        'oldval' => $pageAction === 'Add' ? '' : implodeWithComma($oldvalarr),
                        'changes' => $pageAction === 'Add' ? '' : implodeWithComma($chgvalarr),
                        'act_msg' => actMsgLog($dataId, $datafield, $pageAction === 'Add' ? $newvalarr : '', $pageAction === 'Add' ? '' : $oldvalarr, $pageAction === 'Add' ? '' : $chgvalarr, $tblName, $pageAction, ''),
                    ));
                }
            } else {
                $errorMsg = mysqli_error($finance_connect);
                $act = 'F';
            }
        }
    }
}

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '","","' . $redirectPage . '","' . (isset($act) ? $act : '') . '");</script>';
}

$formDocDate = isset($supplierDocDate) ? $supplierDocDate : (string) ($row['doc_date'] ?? '');
$formCode = isset($supplierCode) ? $supplierCode : (string) ($row['code'] ?? '');
$formBillNo = isset($supplierBillNo) ? $supplierBillNo : (string) ($row['bill_no'] ?? '');
$formDescription = isset($supplierDescription) ? $supplierDescription : (string) ($row['description'] ?? '');
$formQuantity = isset($supplierQuantityRaw) ? $supplierQuantityRaw : (string) ($row['quantity'] ?? '');
$formAmount = isset($supplierAmountRaw) ? $supplierAmountRaw : (string) ($row['amount'] ?? '');
$formAddSst = isset($supplierAddSstRaw) && $supplierAddSstRaw !== '' ? $supplierAddSstRaw : number_format((float) ($row['add_sst'] ?? 0), 2, '.', '');
$formTotal = isset($supplierTotal) ? $supplierTotal : number_format((float) ($row['total'] ?? 0), 2, '.', '');
$formRemark = isset($supplierRemark) ? $supplierRemark : (string) ($row['remark'] ?? '');
$merchantName = '';
if ($formCode !== '') {
    $safeMerchantCode = mysqli_real_escape_string($finance_connect, $formCode);
    $merchantResult = getData('name', "code = '" . $safeMerchantCode . "'", 'LIMIT 1', MERCHANT, $finance_connect);
    if ($merchantResult && ($merchantRow = $merchantResult->fetch_assoc())) {
        $merchantName = (string) ($merchantRow['name'] ?? '');
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>.required-dot { color: red; }</style>
</head>
<body>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageAction . ' ' . $pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <input type="hidden" name="id" value="<?= (int) $dataId ?>">
                    <input type="hidden" name="act" value="<?= htmlspecialchars((string) $act, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group mb-5"><h2><?= htmlspecialchars($pageAction . ' ' . $pageTitle, ENT_QUOTES, 'UTF-8') ?></h2></div>

                    <div class="form-group mb-3"><div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="supplier_doc_date">DocDate<span class="required-dot">*</span></label>
                            <input class="form-control" type="date" name="supplier_doc_date" id="supplier_doc_date" value="<?= htmlspecialchars($formDocDate, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required>
                            <?php if (isset($docDateErr)) : ?><span class="error-message"><?= htmlspecialchars($docDateErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="supplier_merchant_name">Code<span class="required-dot">*</span></label>
                            <div class="autocomplete">
                                <input class="form-control" type="text" id="supplier_merchant_name" value="<?= htmlspecialchars($merchantName, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> autocomplete="off" placeholder="Select merchant">
                                <input type="hidden" id="supplier_merchant_hidden" value="">
                            </div>
                            <input class="form-control mt-2" type="text" name="supplier_code" id="supplier_code" value="<?= htmlspecialchars($formCode, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : 'readonly' ?> required autocomplete="off">
                            <?php if (isset($codeErr)) : ?><span class="error-message"><?= htmlspecialchars($codeErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                    </div></div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_bill_no">Bill No.<span class="required-dot">*</span></label>
                        <input class="form-control" type="text" name="supplier_bill_no" id="supplier_bill_no" value="<?= htmlspecialchars($formBillNo, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required autocomplete="off">
                        <?php if (isset($billNoErr)) : ?><span class="error-message"><?= htmlspecialchars($billNoErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_description">Description<span class="required-dot">*</span></label>
                        <textarea class="form-control" name="supplier_description" id="supplier_description" rows="3" <?= $act === '' ? 'readonly' : '' ?> required><?= htmlspecialchars($formDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php if (isset($descriptionErr)) : ?><span class="error-message"><?= htmlspecialchars($descriptionErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    </div>

                    <div class="form-group mb-3"><div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="supplier_quantity">Quantity<span class="required-dot">*</span></label>
                            <input class="form-control" type="number" name="supplier_quantity" id="supplier_quantity" min="0.001" step="0.001" value="<?= htmlspecialchars($formQuantity, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required>
                            <?php if (isset($quantityErr)) : ?><span class="error-message"><?= htmlspecialchars($quantityErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="supplier_amount">Amount<span class="required-dot">*</span></label>
                            <input class="form-control" type="number" name="supplier_amount" id="supplier_amount" min="0.01" step="0.01" value="<?= htmlspecialchars($formAmount, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required>
                            <?php if (isset($amountErr)) : ?><span class="error-message"><?= htmlspecialchars($amountErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                    </div></div>

                    <div class="form-group mb-3"><div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="supplier_add_sst">Add SST<span class="required-dot">*</span></label>
                            <input class="form-control" type="number" name="supplier_add_sst" id="supplier_add_sst" min="0" step="0.01" value="<?= htmlspecialchars($formAddSst, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required>
                            <?php if (isset($addSstErr)) : ?><span class="error-message"><?= htmlspecialchars($addSstErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form_lbl" for="supplier_total">Total<span class="required-dot">*</span></label>
                            <input class="form-control" type="number" name="supplier_total" id="supplier_total" value="<?= htmlspecialchars($formTotal, ENT_QUOTES, 'UTF-8') ?>" readonly required>
                        </div>
                    </div></div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_remark">Remark</label>
                        <textarea class="form-control" name="supplier_remark" id="supplier_remark" rows="3" <?= $act === '' ? 'readonly' : '' ?>><?= htmlspecialchars($formRemark, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <?= commonRenderCreateUpdateInfo($row, $connect, $act) ?>
                    <?php if ($errorMsg ?? '') : ?><div class="error-message mb-3"><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php if ($act) : ?><button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="<?= htmlspecialchars($actionBtnValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pageAction . ' ' . $pageTitle, ENT_QUOTES, 'UTF-8') ?></button><?php endif; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back" formnovalidate>Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const merchantNameInput = document.getElementById('supplier_merchant_name');
            const merchantHiddenInput = document.getElementById('supplier_merchant_hidden');
            const codeInput = document.getElementById('supplier_code');
            const quantityInput = document.getElementById('supplier_quantity');
            const amountInput = document.getElementById('supplier_amount');
            const addSstInput = document.getElementById('supplier_add_sst');
            const totalInput = document.getElementById('supplier_total');

            function formatMerchantValue(value) {
                const cleaned = (value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
                const prefix = cleaned.replace(/\D/g, '').slice(0, 3);
                const suffix = cleaned.slice(prefix.length).replace(/[^A-Z0-9]/g, '').slice(0, 5);
                if (prefix === '') return suffix;
                if (suffix === '') return prefix;
                return prefix + '-' + suffix;
            }

            function applyMerchant(merchant) {
                if (!merchant) return;
                if (merchantHiddenInput && merchant.id) merchantHiddenInput.value = merchant.id;
                if (codeInput && merchant.code !== undefined) codeInput.value = formatMerchantValue(merchant.code);
            }

            if (merchantNameInput && !merchantNameInput.readOnly) {
                merchantNameInput.addEventListener('input', function () {
                    if (merchantHiddenInput) merchantHiddenInput.value = '';
                    searchInput({
                        search: merchantNameInput.value,
                        searchType: 'name',
                        elementID: 'supplier_merchant_name',
                        hiddenElementID: 'supplier_merchant_hidden',
                        dbTable: '<?= MERCHANT ?>',
                        onSelect: applyMerchant
                    }, '<?= $SITEURL ?>');
                });
            }

            function calculateTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const amount = parseFloat(amountInput.value) || 0;
                const addSst = parseFloat(addSstInput.value) || 0;
                totalInput.value = (quantity * amount + addSst).toFixed(2);
            }

            [quantityInput, amountInput, addSstInput].forEach(function (input) {
                if (input) input.addEventListener('input', calculateTotal);
                if (input) input.addEventListener('change', calculateTotal);
            });
            calculateTotal();
        })();
        const page = <?= json_encode($pageTitle) ?>;
        const action = <?= json_encode(isset($act) ? $act : '') ?>;
        checkCurrentPage(page, action);
        setButtonColor();
        setAutofocus(action);
        preloader(300, action);
    </script>
</body>
</html>
