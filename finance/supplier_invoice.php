<?php
$currentPagePin = 167;
$pageTitle = "Supplier Invoice";

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedPageTitle !== '') {
    $pageTitle = $resolvedPageTitle;
}

$tblName = SUPPLIER_INVOICE;
$dataId = (int) (!empty(input('id')) ? input('id') : post('id'));
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';
$pageAction = getPageAction($act);
$pinAccess = checkCurrentPin($connect, $pageTitle);

$redirectPage = $SITEURL . '/finance/supplier_invoice_table.php';
$redirectLink = "<script>location.href = '" . $redirectPage . "';</script>";
$clearLocalStorage = '<script>localStorage.clear();</script>';

function supplierInvoiceNormalizeFormattedValue($value)
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
        $errorExist = 1;
        $_SESSION['tempValConfirmBox'] = true;
        $act = 'F';
        $pageAction = getPageAction($act);
    }
}

$existingSupplierQrUrl = '';
if ($dataId) {
    $qrResult = getData('qr_url', "supplier_invoice_id = '" . $dataId . "'", 'LIMIT 1', SUPPLIER_INVOICE_QR, $finance_connect);
    if ($qrResult && ($qrRow = $qrResult->fetch_assoc())) {
        $existingSupplierQrUrl = (string) ($qrRow['qr_url'] ?? '');
    }
}

if ($act === 'D' && $dataId && !empty($row)) {
    deleteRecord($tblName, '', $dataId, $row['doc_no'], $finance_connect, $connect, $cdate, $ctime, $pageTitle);
    $safeDataId = (int) $dataId;
    mysqli_query($finance_connect, "UPDATE " . SUPPLIER_INVOICE_QR . " SET status = 'D', update_by = '" . mysqli_real_escape_string($finance_connect, (string) USER_ID) . "', update_date = CURDATE(), update_time = CURTIME() WHERE supplier_invoice_id = '" . $safeDataId . "'");
    $_SESSION['delChk'] = 1;
}

if ($dataId && !$act && USER_ID && empty($_SESSION['viewChk']) && empty($_SESSION['delChk']) && !empty($row)) {
    $_SESSION['viewChk'] = 1;
    $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . htmlspecialchars((string) $row['doc_no'], ENT_QUOTES, 'UTF-8') . "</b> from <b><i>$tblName Table</i></b>.";
    audit_log(array(
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($action === 'back') {
        echo $clearLocalStorage . $redirectLink;
    } elseif ($action === 'addData' || $action === 'updData') {
        $supplierDocNo = postSpaceFilter('supplier_doc_no');
        $supplierDocDate = postSpaceFilter('supplier_doc_date');
        $supplierDescription = postSpaceFilter('supplier_description');
        $supplierControlAccount = supplierInvoiceNormalizeFormattedValue(postSpaceFilter('supplier_control_account'));
        $supplierCode = supplierInvoiceNormalizeFormattedValue(postSpaceFilter('supplier_code'));
        $supplierAmountRaw = trim((string) post('supplier_amount'));
        $supplierAmount = number_format((float) str_replace(',', '', $supplierAmountRaw), 2, '.', '');
        $supplierOdr = postSpaceFilter('supplier_odr');
        $supplierQrUrl = trim((string) post('supplier_qr_url'));
        $supplierRemark = postSpaceFilter('supplier_remark');

        $datafield = array();
        $oldvalarr = array();
        $chgvalarr = array();
        $newvalarr = array();

        if ($supplierDocNo === '') {
            $docNoErr = 'Please specify the DocNo.';
            $error = 1;
        } elseif ($supplierDocDate === '') {
            $docDateErr = 'Please specify the DocDate.';
            $error = 1;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $supplierDocDate) || !checkdate((int) substr($supplierDocDate, 5, 2), (int) substr($supplierDocDate, 8, 2), (int) substr($supplierDocDate, 0, 4))) {
            $docDateErr = 'Please specify a valid DocDate.';
            $error = 1;
        } elseif ($supplierAmountRaw === '' || !is_numeric(str_replace(',', '', $supplierAmountRaw))) {
            $amountErr = 'Please specify a valid Amount.';
            $error = 1;
        } elseif ($supplierControlAccount !== '' && !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $supplierControlAccount)) {
            $controlAccountErr = 'Control A/C format must be like 123-ABC01.';
            $error = 1;
        } elseif ($supplierCode !== '' && !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $supplierCode)) {
            $codeErr = 'Code format must be like 123-ABC01.';
            $error = 1;
        } elseif ($supplierQrUrl !== '' && (!filter_var($supplierQrUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $supplierQrUrl))) {
            $qrUrlErr = 'The scanned QR value must be a valid http or https URL.';
            $error = 1;
        }

        if (!isset($error)) {
            $safe = function ($value) use ($finance_connect) {
                return mysqli_real_escape_string($finance_connect, (string) $value);
            };

            mysqli_begin_transaction($finance_connect);
            $returnData = false;
            $query = '';
            $qrQuery = '';
            $invoiceChanged = false;
            $qrChanged = $supplierQrUrl !== $existingSupplierQrUrl;

            if ($action === 'addData') {
                $query = "INSERT INTO " . $tblName . " (doc_no, doc_date, description, control_account, code, amount, odr, remark, create_by, create_date, create_time) VALUES ('" . $safe($supplierDocNo) . "', '" . $safe($supplierDocDate) . "', '" . $safe($supplierDescription) . "', '" . $safe($supplierControlAccount) . "', '" . $safe($supplierCode) . "', '" . $safe($supplierAmount) . "', '" . $safe($supplierOdr) . "', '" . $safe($supplierRemark) . "', '" . $safe(USER_ID) . "', CURDATE(), CURTIME())";
                $returnData = mysqli_query($finance_connect, $query);
                if ($returnData) {
                    $dataId = (int) $finance_connect->insert_id;
                    $invoiceChanged = true;
                    $newvalarr = array($supplierDocNo, $supplierDocDate, $supplierDescription, $supplierControlAccount, $supplierCode, $supplierAmount, $supplierOdr, $supplierRemark);
                    $datafield = array('doc_no', 'doc_date', 'description', 'control_account', 'code', 'amount', 'odr', 'remark');
                }
            } else {
                $currentValues = array(
                    'doc_no' => (string) ($row['doc_no'] ?? ''),
                    'doc_date' => (string) ($row['doc_date'] ?? ''),
                    'description' => (string) ($row['description'] ?? ''),
                    'control_account' => (string) ($row['control_account'] ?? ''),
                    'code' => (string) ($row['code'] ?? ''),
                    'amount' => number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                    'odr' => (string) ($row['odr'] ?? ''),
                    'remark' => (string) ($row['remark'] ?? ''),
                );
                $newValues = array(
                    'doc_no' => $supplierDocNo,
                    'doc_date' => $supplierDocDate,
                    'description' => $supplierDescription,
                    'control_account' => $supplierControlAccount,
                    'code' => $supplierCode,
                    'amount' => $supplierAmount,
                    'odr' => $supplierOdr,
                    'remark' => $supplierRemark,
                );

                foreach ($newValues as $field => $newValue) {
                    if ($currentValues[$field] !== $newValue) {
                        $oldvalarr[] = $currentValues[$field] === '' ? 'Empty Value' : $currentValues[$field];
                        $chgvalarr[] = $newValue === '' ? 'Empty Value' : $newValue;
                        $datafield[] = $field;
                    }
                }

                $invoiceChanged = !empty($datafield);
                if ($invoiceChanged) {
                    $query = "UPDATE " . $tblName . " SET doc_no = '" . $safe($supplierDocNo) . "', doc_date = '" . $safe($supplierDocDate) . "', description = '" . $safe($supplierDescription) . "', control_account = '" . $safe($supplierControlAccount) . "', code = '" . $safe($supplierCode) . "', amount = '" . $safe($supplierAmount) . "', odr = '" . $safe($supplierOdr) . "', remark = '" . $safe($supplierRemark) . "', update_by = '" . $safe(USER_ID) . "', update_date = CURDATE(), update_time = CURTIME() WHERE id = '" . (int) $dataId . "'";
                    $returnData = mysqli_query($finance_connect, $query);
                } elseif (!$qrChanged) {
                    $returnData = true;
                    $act = 'NC';
                } else {
                    $returnData = true;
                }
            }

            if ($returnData && $dataId && $qrChanged) {
                $qrQuery = "UPDATE " . SUPPLIER_INVOICE_QR . " SET status = 'D', update_by = '" . $safe(USER_ID) . "', update_date = CURDATE(), update_time = CURTIME() WHERE supplier_invoice_id = '" . (int) $dataId . "' AND status = 'A'";
                $qrUpdated = mysqli_query($finance_connect, $qrQuery);
                if ($qrUpdated && $supplierQrUrl !== '') {
                    $qrQuery = "INSERT INTO " . SUPPLIER_INVOICE_QR . " (supplier_invoice_id, qr_url, create_by, create_date, create_time) VALUES ('" . (int) $dataId . "', '" . $safe($supplierQrUrl) . "', '" . $safe(USER_ID) . "', CURDATE(), CURTIME())";
                    $qrUpdated = mysqli_query($finance_connect, $qrQuery);
                }
                if (!$qrUpdated) {
                    $returnData = false;
                }
            }

            if ($returnData) {
                if ($qrChanged) {
                    $datafield[] = 'qr_url';
                    if ($pageAction === 'Add') {
                        $newvalarr[] = $supplierQrUrl === '' ? 'Empty Value' : $supplierQrUrl;
                    } else {
                        $oldvalarr[] = $existingSupplierQrUrl === '' ? 'Empty Value' : $existingSupplierQrUrl;
                        $chgvalarr[] = $supplierQrUrl === '' ? 'Empty Value' : $supplierQrUrl;
                    }
                }
                mysqli_commit($finance_connect);
                $_SESSION['tempValConfirmBox'] = true;
            } else {
                mysqli_rollback($finance_connect);
                $errorMsg = mysqli_error($finance_connect);
                $act = 'F';
            }

            if ($returnData && ($invoiceChanged || $qrChanged)) {
                $log = array(
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => trim($query . ' ' . $qrQuery),
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                );
                if ($pageAction === 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, '');
                } else {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, '');
                }
                audit_log($log);
            }
        }
    }
}

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '","","' . $redirectPage . '","' . (isset($act) ? $act : '') . '");</script>';
}

$formDocNo = isset($supplierDocNo) ? $supplierDocNo : (string) ($row['doc_no'] ?? '');
$formDocDate = isset($supplierDocDate) ? $supplierDocDate : (string) ($row['doc_date'] ?? '');
$formDescription = isset($supplierDescription) ? $supplierDescription : (string) ($row['description'] ?? '');
$formControlAccount = isset($supplierControlAccount) ? $supplierControlAccount : (string) ($row['control_account'] ?? '');
$formCode = isset($supplierCode) ? $supplierCode : (string) ($row['code'] ?? '');
$formAmount = isset($supplierAmountRaw) ? $supplierAmountRaw : (string) ($row['amount'] ?? '');
$formOdr = isset($supplierOdr) ? $supplierOdr : (string) ($row['odr'] ?? '');
$formQrUrl = isset($supplierQrUrl) ? $supplierQrUrl : $existingSupplierQrUrl;
$formRemark = isset($supplierRemark) ? $supplierRemark : (string) ($row['remark'] ?? '');
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
                <form id="form" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="id" value="<?= (int) $dataId ?>">
                    <input type="hidden" name="act" value="<?= htmlspecialchars((string) $act, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="supplier_qr_url" id="supplier_qr_url" value="<?= htmlspecialchars($formQrUrl, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group mb-5"><h2><?= htmlspecialchars($pageAction . ' ' . $pageTitle, ENT_QUOTES, 'UTF-8') ?></h2></div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="supplier_doc_no">DocNo<span class="required-dot">*</span></label>
                                <input class="form-control" type="text" name="supplier_doc_no" id="supplier_doc_no" value="<?= htmlspecialchars($formDocNo, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required autocomplete="off">
                                <?php if (isset($docNoErr)) : ?><span class="error-message"><?= htmlspecialchars($docNoErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form_lbl" for="supplier_doc_date">DocDate<span class="required-dot">*</span></label>
                                <input class="form-control" type="date" name="supplier_doc_date" id="supplier_doc_date" value="<?= htmlspecialchars($formDocDate, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required>
                                <?php if (isset($docDateErr)) : ?><span class="error-message"><?= htmlspecialchars($docDateErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_description">Description</label>
                        <textarea class="form-control" name="supplier_description" id="supplier_description" rows="3" <?= $act === '' ? 'readonly' : '' ?>><?= htmlspecialchars($formDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_merchant_name">Merchant Name</label>
                        <div class="autocomplete">
                            <input class="form-control" type="text" id="supplier_merchant_name" value="" <?= $act === '' ? 'readonly' : '' ?> autocomplete="off">
                            <input type="hidden" id="supplier_merchant_hidden" value="">
                        </div>
                        <small class="text-muted">Select a merchant to auto-fill Control A/C and Code.</small>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="supplier_control_account">Control A/C</label>
                                <input class="form-control" type="text" name="supplier_control_account" id="supplier_control_account" maxlength="9" value="<?= htmlspecialchars($formControlAccount, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> autocomplete="off">
                                <?php if (isset($controlAccountErr)) : ?><span class="error-message"><?= htmlspecialchars($controlAccountErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form_lbl" for="supplier_code">Code</label>
                                <input class="form-control" type="text" name="supplier_code" id="supplier_code" maxlength="9" value="<?= htmlspecialchars($formCode, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> autocomplete="off">
                                <?php if (isset($codeErr)) : ?><span class="error-message"><?= htmlspecialchars($codeErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="supplier_amount">Amount<span class="required-dot">*</span></label>
                                <input class="form-control" type="number" name="supplier_amount" id="supplier_amount" min="0" step="0.01" value="<?= htmlspecialchars($formAmount, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> required>
                                <?php if (isset($amountErr)) : ?><span class="error-message"><?= htmlspecialchars($amountErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form_lbl" for="supplier_odr">ODR</label>
                                <input class="form-control" type="text" name="supplier_odr" id="supplier_odr" value="<?= htmlspecialchars($formOdr, ENT_QUOTES, 'UTF-8') ?>" <?= $act === '' ? 'readonly' : '' ?> autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_qr_image">QR Image</label>
                        <input class="form-control" type="file" id="supplier_qr_image" accept="image/*" <?= $act === '' ? 'disabled' : '' ?>>
                        <small class="text-muted">Select an image containing the QR code. Its URL will be saved separately from the invoice.</small>
                        <div id="supplier_qr_scan_status" class="mt-2 text-muted" aria-live="polite"></div>
                        <div id="supplier_qr_url_preview" class="mt-2"></div>
                        <?php if ($formQrUrl !== '') : ?>
                            <div class="mt-2">Current QR URL: <a href="<?= htmlspecialchars($formQrUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($formQrUrl, ENT_QUOTES, 'UTF-8') ?></a></div>
                        <?php endif; ?>
                        <?php if (isset($qrUrlErr)) : ?><span class="error-message"><?= htmlspecialchars($qrUrlErr, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="supplier_remark">Remark</label>
                        <textarea class="form-control" name="supplier_remark" id="supplier_remark" rows="3" <?= $act === '' ? 'readonly' : '' ?>><?= htmlspecialchars($formRemark, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <?= commonRenderCreateUpdateInfo($row, $connect, $act) ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php if ($act) : ?><button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="<?= htmlspecialchars($actionBtnValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pageAction . ' ' . $pageTitle, ENT_QUOTES, 'UTF-8') ?></button><?php endif; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back" formnovalidate>Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= $SITEURL ?>/header/js/jsQR.js"></script>
    <script>
        (function () {
            const form = document.getElementById('form');
            const qrInput = document.getElementById('supplier_qr_image');
            const qrUrlInput = document.getElementById('supplier_qr_url');
            const qrScanStatus = document.getElementById('supplier_qr_scan_status');
            const qrUrlPreview = document.getElementById('supplier_qr_url_preview');
            const merchantNameInput = document.getElementById('supplier_merchant_name');
            const merchantHiddenInput = document.getElementById('supplier_merchant_hidden');
            const controlAccountInput = document.getElementById('supplier_control_account');
            const codeInput = document.getElementById('supplier_code');
            let scannedQrFile = null;
            let qrScanPromise = null;

            function formatMerchantValue(value) {
                const cleaned = (value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
                const prefix = cleaned.replace(/\D/g, '').slice(0, 3);
                const suffix = cleaned.slice(prefix.length).replace(/[^A-Z0-9]/g, '').slice(0, 5);
                if (prefix === '') return suffix;
                if (suffix === '') return prefix;
                return prefix + '-' + suffix;
            }

            function clearSupplierMerchantSelection() {
                if (merchantHiddenInput) merchantHiddenInput.value = '';
            }

            function applySupplierMerchantValues(merchant) {
                if (!merchant) return;
                if (controlAccountInput && merchant.control_account !== undefined) controlAccountInput.value = formatMerchantValue(merchant.control_account);
                if (codeInput && merchant.code !== undefined) codeInput.value = formatMerchantValue(merchant.code);
            }

            function autofillSupplierMerchant(merchant) {
                if (merchant && merchant.id && merchantHiddenInput) merchantHiddenInput.value = merchant.id;
                applySupplierMerchantValues(merchant);
                if (!merchantHiddenInput || merchantHiddenInput.value === '') return;

                const param = {
                    search: merchantHiddenInput.value,
                    searchCol: 'id',
                    searchType: '*',
                    dbTable: '<?= MERCHANT ?>',
                    isFin: 1
                };

                retrieveDBData(param, '<?= $SITEURL ?>', function (result) {
                    if (!result || !result.length) return;
                    applySupplierMerchantValues(result[0]);
                });
            }

            if (merchantNameInput && !merchantNameInput.readOnly) {
                merchantNameInput.addEventListener('input', function () {
                    clearSupplierMerchantSelection();
                    searchInput({
                        search: merchantNameInput.value,
                        searchType: 'name',
                        elementID: 'supplier_merchant_name',
                        hiddenElementID: 'supplier_merchant_hidden',
                        dbTable: '<?= MERCHANT ?>',
                        onSelect: autofillSupplierMerchant
                    }, '<?= $SITEURL ?>');
                });
            }

            function readFileAsImage(file) {
                return new Promise(function (resolve, reject) {
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        const image = new Image();
                        image.onload = function () { resolve(image); };
                        image.onerror = function () { reject(new Error('Unable to load QR image.')); };
                        image.src = event.target.result;
                    };
                    reader.onerror = function () { reject(new Error('Unable to read QR image.')); };
                    reader.readAsDataURL(file);
                });
            }

            function getDarkPixelBounds(image) {
                const analysisCanvas = document.createElement('canvas');
                const maxAnalysisSize = 1200;
                const analysisScale = Math.min(1, maxAnalysisSize / Math.max(image.width, image.height));
                analysisCanvas.width = Math.max(1, Math.round(image.width * analysisScale));
                analysisCanvas.height = Math.max(1, Math.round(image.height * analysisScale));
                const analysisContext = analysisCanvas.getContext('2d', { willReadFrequently: true });
                if (!analysisContext) return null;

                analysisContext.drawImage(image, 0, 0, analysisCanvas.width, analysisCanvas.height);
                const pixels = analysisContext.getImageData(0, 0, analysisCanvas.width, analysisCanvas.height).data;
                const edgeMargin = Math.max(5, Math.round(Math.min(analysisCanvas.width, analysisCanvas.height) * 0.03));
                let minX = analysisCanvas.width;
                let minY = analysisCanvas.height;
                let maxX = -1;
                let maxY = -1;

                for (let y = edgeMargin; y < analysisCanvas.height - edgeMargin; y += 1) {
                    for (let x = edgeMargin; x < analysisCanvas.width - edgeMargin; x += 1) {
                        const pixelIndex = (y * analysisCanvas.width + x) * 4;
                        const red = pixels[pixelIndex];
                        const green = pixels[pixelIndex + 1];
                        const blue = pixels[pixelIndex + 2];
                        if (Math.max(red, green, blue) < 210) {
                            minX = Math.min(minX, x);
                            minY = Math.min(minY, y);
                            maxX = Math.max(maxX, x);
                            maxY = Math.max(maxY, y);
                        }
                    }
                }

                if (maxX < 0 || maxY < 0) return null;
                return {
                    x: minX / analysisScale,
                    y: minY / analysisScale,
                    width: (maxX - minX + 1) / analysisScale,
                    height: (maxY - minY + 1) / analysisScale
                };
            }

            function createQrCanvas(image, crop, scale, threshold, smoothing) {
                const canvas = document.createElement('canvas');
                const sourceX = crop ? crop.x : 0;
                const sourceY = crop ? crop.y : 0;
                const sourceWidth = crop ? crop.width : image.width;
                const sourceHeight = crop ? crop.height : image.height;
                canvas.width = Math.max(1, Math.round(sourceWidth * scale));
                canvas.height = Math.max(1, Math.round(sourceHeight * scale));
                const context = canvas.getContext('2d', { willReadFrequently: true });
                if (!context) return null;

                context.imageSmoothingEnabled = smoothing !== false;
                if (context.imageSmoothingEnabled) {
                    context.imageSmoothingQuality = 'high';
                }
                context.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);

                if (threshold !== null) {
                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                    for (let pixelIndex = 0; pixelIndex < imageData.data.length; pixelIndex += 4) {
                        const luminance = (imageData.data[pixelIndex] * 299 + imageData.data[pixelIndex + 1] * 587 + imageData.data[pixelIndex + 2] * 114) / 1000;
                        const value = luminance < threshold ? 0 : 255;
                        imageData.data[pixelIndex] = value;
                        imageData.data[pixelIndex + 1] = value;
                        imageData.data[pixelIndex + 2] = value;
                        imageData.data[pixelIndex + 3] = 255;
                    }
                    context.putImageData(imageData, 0, 0);
                }

                return canvas;
            }

            async function decodeQrCanvas(canvas) {
                if (!canvas) return '';
                const context = canvas.getContext('2d', { willReadFrequently: true });
                if (!context) return '';

                if (typeof BarcodeDetector !== 'undefined') {
                    try {
                        const supportedFormats = typeof BarcodeDetector.getSupportedFormats === 'function'
                            ? await BarcodeDetector.getSupportedFormats()
                            : ['qr_code'];
                        if (supportedFormats.indexOf('qr_code') !== -1) {
                            const detector = new BarcodeDetector({ formats: ['qr_code'] });
                            const detectedCodes = await detector.detect(canvas);
                            if (detectedCodes.length && detectedCodes[0].rawValue) {
                                return String(detectedCodes[0].rawValue).trim();
                            }
                        }
                    } catch (nativeScannerError) {
                        // Continue with jsQR when BarcodeDetector is unavailable or cannot decode this image.
                    }
                }

                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, canvas.width, canvas.height, { inversionAttempts: 'attemptBoth' });
                return code && code.data ? String(code.data).trim() : '';
            }

            async function decodeQrFromFile(file) {
                if (typeof jsQR === 'undefined') {
                    throw new Error('QR scanner is unavailable. Please refresh the page and try again.');
                }

                const image = await readFileAsImage(file);
                const attempts = [];
                const fullScale = Math.max(2, Math.min(4, 1200 / Math.max(image.width, image.height)));
                attempts.push({ crop: null, scale: fullScale, threshold: null, smoothing: true });
                attempts.push({ crop: null, scale: fullScale, threshold: 180, smoothing: true });
                attempts.push({ crop: null, scale: fullScale, threshold: null, smoothing: false });

                const darkBounds = getDarkPixelBounds(image);
                if (darkBounds) {
                    const padding = Math.max(12, Math.round(Math.max(darkBounds.width, darkBounds.height) * 0.25));
                    const cropSize = Math.max(darkBounds.width, darkBounds.height) + (padding * 2);
                    const centerX = darkBounds.x + (darkBounds.width / 2);
                    const centerY = darkBounds.y + (darkBounds.height / 2);
                    const cropX = Math.max(0, Math.min(image.width - cropSize, centerX - (cropSize / 2)));
                    const cropY = Math.max(0, Math.min(image.height - cropSize, centerY - (cropSize / 2)));
                    const crop = {
                        x: cropX,
                        y: cropY,
                        width: Math.min(cropSize, image.width - cropX),
                        height: Math.min(cropSize, image.height - cropY)
                    };
                    const cropScale = Math.max(4, Math.min(8, 800 / Math.max(crop.width, crop.height)));
                    attempts.push({ crop: crop, scale: cropScale, threshold: null, smoothing: true });
                    attempts.push({ crop: crop, scale: cropScale, threshold: 128, smoothing: true });
                    attempts.push({ crop: crop, scale: cropScale, threshold: 180, smoothing: true });
                    attempts.push({ crop: crop, scale: cropScale, threshold: 220, smoothing: true });
                    attempts.push({ crop: crop, scale: cropScale, threshold: null, smoothing: false });
                }

                for (const attempt of attempts) {
                    const decodedValue = await decodeQrCanvas(createQrCanvas(image, attempt.crop, attempt.scale, attempt.threshold, attempt.smoothing));
                    if (decodedValue) return decodedValue;
                }

                throw new Error('No QR code was detected in the selected image. Try a clearer or closer QR image.');
            }

            function setQrScanStatus(message, className) {
                if (!qrScanStatus) return;
                qrScanStatus.className = 'mt-2 ' + className;
                qrScanStatus.textContent = message;
            }

            function showScannedQrUrl(url) {
                if (!qrUrlPreview) return;
                qrUrlPreview.textContent = '';
                const label = document.createElement('span');
                label.textContent = 'Scanned web URL: ';
                const link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = url;
                qrUrlPreview.appendChild(label);
                qrUrlPreview.appendChild(link);
            }

            function scanSelectedQrImage(file) {
                if (!file) return Promise.resolve(false);

                setQrScanStatus('Scanning QR code...', 'mt-2 text-info');
                if (qrUrlPreview) qrUrlPreview.textContent = '';
                qrScanPromise = decodeQrFromFile(file).then(function (decodedUrl) {
                    if (!/^https?:\/\//i.test(decodedUrl)) {
                        throw new Error('The QR code does not contain an http or https web URL.');
                    }
                    qrUrlInput.value = decodedUrl;
                    scannedQrFile = file;
                    showScannedQrUrl(decodedUrl);
                    setQrScanStatus('QR code scanned successfully. The URL will be saved when the invoice is submitted.', 'mt-2 text-success');
                    return true;
                }).catch(function (error) {
                    scannedQrFile = null;
                    qrUrlInput.value = '';
                    if (qrUrlPreview) qrUrlPreview.textContent = '';
                    setQrScanStatus(error && error.message ? error.message : 'Unable to scan the QR image.', 'mt-2 text-danger');
                    return false;
                }).finally(function () {
                    qrScanPromise = null;
                });

                return qrScanPromise;
            }

            if (!form) return;
            [controlAccountInput, codeInput].forEach(function (input) {
                if (input) input.addEventListener('input', function () { input.value = formatMerchantValue(input.value); });
            });

            if (qrInput) {
                qrInput.addEventListener('change', function () {
                    const file = qrInput.files && qrInput.files.length ? qrInput.files[0] : null;
                    if (!file) return;
                    scannedQrFile = null;
                    qrUrlInput.value = '';
                    scanSelectedQrImage(file);
                });
            }

            form.addEventListener('submit', async function (event) {
                if (controlAccountInput) controlAccountInput.value = formatMerchantValue(controlAccountInput.value);
                if (codeInput) codeInput.value = formatMerchantValue(codeInput.value);

                if (!qrInput || !qrInput.files || !qrInput.files.length || (event.submitter && event.submitter.value === 'back')) return;

                const selectedFile = qrInput.files[0];
                if (selectedFile === scannedQrFile && qrUrlInput.value) return;

                event.preventDefault();
                const scanResult = qrScanPromise || scanSelectedQrImage(selectedFile);
                const scanSucceeded = await scanResult;
                if (scanSucceeded) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(event.submitter);
                    } else {
                        form.submit();
                    }
                }
            });
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
