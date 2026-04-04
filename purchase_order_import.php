<?php
$currentPagePin = 135;
$pageTitle = '';

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = PURCHASE_ORDER;
$companyTbl = COMPANY;
$redirect_page = $SITEURL . '/purchase_order_table.php';
$shortcut_page = $SITEURL . '/common_import.php';
$parentPagePinGroupId = 135;
$parentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($parentPageTitle === '') {
    $parentPageTitle = 'Purchase Order';
}
$breadcrumbTitle = $parentPageTitle . ' Import';
$pageTitle = $breadcrumbTitle;
$pageHeading = 'Import & Bulk Edit ' . $parentPageTitle;

$pinAccess = checkPinByGroupId($connect, $parentPagePinGroupId);
if (!is_array($pinAccess)) {
    $pinAccess = array();
}
if (!isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import this page.");location.href = "' . $redirect_page . '";</script>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $log = [
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page " . $safeAuditPageTitle . ".",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);
}

$action = post('actionBtn');
$importErrors = array();
$importWarnings = array();
$insertCount = 0;
$updateCount = 0;
$previewData = array();

function poImpEsc($conn, $value)
{
    return mysqli_real_escape_string($conn, (string) $value);
}

function parse_xlsx_purchase_order($filepath)
{
    $rows = array();

    $ssXml = false;
    $sheetXml = false;

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (preg_match('/xl\/worksheets\/sheet\d+\.xml/i', (string) $filename)) {
                    $sheetXml = $zip->getFromName($filename);
                    break;
                }
            }
            $zip->close();
        }
    }

    if ($sheetXml === false) {
        return array('error' => 'Unable to read worksheet data from uploaded .xlsx file.');
    }

    $loadXmlSafely = function ($xmlString) {
        if ($xmlString === false || $xmlString === null || $xmlString === '') {
            return false;
        }
        $prevUseInternalErrors = libxml_use_internal_errors(true);
        $prevDisableEntityLoader = null;
        if (function_exists('libxml_disable_entity_loader')) {
            $prevDisableEntityLoader = libxml_disable_entity_loader(true);
        }
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NONET);
        if (function_exists('libxml_disable_entity_loader') && $prevDisableEntityLoader !== null) {
            libxml_disable_entity_loader($prevDisableEntityLoader);
        }
        libxml_use_internal_errors($prevUseInternalErrors);
        return $xml;
    };

    $sharedStrings = array();
    if ($ssXml !== false) {
        $xml = $loadXmlSafely($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $val) {
                $str = '';
                if (isset($val->t)) {
                    $str .= (string) $val->t;
                } elseif (isset($val->r)) {
                    foreach ($val->r as $r) {
                        if (isset($r->t)) {
                            $str .= (string) $r->t;
                        }
                    }
                }
                $sharedStrings[] = $str;
            }
        }
    }

    $xml = $loadXmlSafely($sheetXml);
    if (!$xml || !isset($xml->sheetData->row)) {
        return array('error' => 'Worksheet is empty or corrupted.');
    }

    foreach ($xml->sheetData->row as $row) {
        $rowData = array();
        $colIndex = 0;
        foreach ($row->c as $c) {
            $r = (string) $c['r'];
            if ($r) {
                $letter = preg_replace('/[0-9]/', '', $r);
                $idx = 0;
                $len = strlen($letter);
                for ($i = 0; $i < $len; $i++) {
                    $idx = $idx * 26 + (ord($letter[$i]) - 64);
                }
                $idx -= 1;
            } else {
                $idx = $colIndex;
            }

            while ($colIndex < $idx) {
                $rowData[$colIndex] = '';
                $colIndex++;
            }

            $v = (string) $c->v;
            if (isset($c['t']) && (string) $c['t'] == 's') {
                $v = isset($sharedStrings[$v]) ? $sharedStrings[$v] : '';
            } elseif (isset($c['t']) && (string) $c['t'] == 'inlineStr') {
                $v = isset($c->is->t) ? (string) $c->is->t : '';
            }

            $rowData[$colIndex] = $v;
            $colIndex++;
        }
        $rows[] = $rowData;
    }

    return $rows;
}

function poImpNormalizeKey($key)
{
    $key = strtolower(trim((string) $key));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    return trim((string) $key, '_');
}

function poImpAliasToField($headerKey)
{
    $map = array(
        'doc_date' => 'doc_date',
        'docdate' => 'doc_date',
        'docdate_20' => 'doc_date',
        'doc_no' => 'doc_no',
        'docno' => 'doc_no',
        'docno_20' => 'doc_no',
        'code' => 'code',
        'code_10' => 'code',
        'company_name' => 'company_name',
        'companyname_100' => 'company_name',
        'company' => 'company_name',
        'description_hdr' => 'description_hdr',
        'description_hdr_200' => 'description_hdr',
        'description_header' => 'description_hdr',
        'seq' => 'seq',
        'account' => 'account',
        'account_10' => 'account',
        'item_code' => 'item_code',
        'itemcode' => 'item_code',
        'itemcode_30' => 'item_code',
        'description_dtl' => 'description_dtl',
        'description_dtl_200' => 'description_dtl',
        'description_detail' => 'description_dtl',
        'qty' => 'qty',
        'uom' => 'uom',
        'uom_10' => 'uom',
        'unit_price' => 'unit_price',
        'unitprice' => 'unit_price',
        'amount' => 'amount',
        'remark' => 'remark',
    );

    return isset($map[$headerKey]) ? $map[$headerKey] : '';
}

function poImpToDbDate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (is_numeric($value)) {
        $serial = (float) $value;
        if ($serial > 0) {
            $unixTs = (int) round(($serial - 25569) * 86400);
            return gmdate('Y-m-d', $unixTs);
        }
    }

    $dt = DateTime::createFromFormat('d/m/Y', $value);
    if ($dt && $dt->format('d/m/Y') === $value) {
        return $dt->format('Y-m-d');
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt && $dt->format('Y-m-d') === $value) {
        return $dt->format('Y-m-d');
    }

    return '';
}

function poImpNormalizeNumeric($value, $decimals = 2)
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return $value;
    }
    return number_format((float) $value, (int) $decimals, '.', '');
}

function poImpNormalizeRowData($rowData)
{
    if (!is_array($rowData)) {
        return $rowData;
    }

    if (isset($rowData['doc_date'])) {
        $docDateDb = poImpToDbDate($rowData['doc_date']);
        if ($docDateDb !== '') {
            $rowData['doc_date'] = $docDateDb;
        }
    }

    if (isset($rowData['seq']) && is_numeric($rowData['seq'])) {
        $rowData['seq'] = (string) ((int) $rowData['seq']);
    }

    if (isset($rowData['qty'])) {
        $rowData['qty'] = poImpNormalizeNumeric($rowData['qty'], 2);
    }
    if (isset($rowData['unit_price'])) {
        $rowData['unit_price'] = poImpNormalizeNumeric($rowData['unit_price'], 2);
    }
    if (isset($rowData['amount'])) {
        $rowData['amount'] = poImpNormalizeNumeric($rowData['amount'], 2);
    }

    return $rowData;
}

$poFields = array(
    'doc_date', 'doc_no', 'code', 'company_name', 'description_hdr', 'seq', 'account',
    'item_code', 'description_dtl', 'qty', 'uom', 'unit_price', 'amount', 'sql_account_id', 'remark'
);

$requiredFields = array('doc_date', 'doc_no', 'code', 'company_name', 'seq', 'item_code', 'qty', 'uom', 'unit_price');

$companyMapByName = array();
$cmpRst = mysqli_query($connect, "SELECT * FROM " . $companyTbl . " WHERE status='A'");
if ($cmpRst) {
    while ($cmp = mysqli_fetch_assoc($cmpRst)) {
        $cmpKey = strtolower(trim((string) (isset($cmp['name']) ? $cmp['name'] : '')));
        if ($cmpKey !== '') {
            $companyMapByName[$cmpKey] = $cmp;
        }
    }
}

if ($action === 'preview') {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please choose a valid Excel (.xlsx) file to upload.';
    } else {
        $fileTmpPath = $_FILES['import_file']['tmp_name'];
        $fileExtension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'xlsx') {
            $importErrors[] = 'Invalid format. Please upload an Excel (.xlsx) file.';
        } else {
            $parsedRows = parse_xlsx_purchase_order($fileTmpPath);
            if (is_array($parsedRows) && isset($parsedRows['error'])) {
                $importErrors[] = $parsedRows['error'];
            } else if (!is_array($parsedRows) || count($parsedRows) < 2) {
                $importErrors[] = 'No rows found in uploaded file.';
            } else {
                $existingByKey = array();
                $exRst = mysqli_query($connect, "SELECT * FROM " . $tblName . " WHERE status='A'");
                if ($exRst) {
                    while ($exRow = mysqli_fetch_assoc($exRst)) {
                        $rowKey = trim((string) $exRow['doc_no']) . '|' . (int) $exRow['seq'] . '|' . trim((string) $exRow['item_code']);
                        $existingByKey[$rowKey] = $exRow;
                    }
                }

                $headers = isset($parsedRows[0]) ? $parsedRows[0] : array();
                $indexMap = array();
                foreach ($headers as $idx => $headerName) {
                    $alias = poImpAliasToField(poImpNormalizeKey($headerName));
                    if ($alias !== '') {
                        $indexMap[$alias] = (int) $idx;
                    }
                }

                for ($r = 1; $r < count($parsedRows); $r++) {
                    $raw = isset($parsedRows[$r]) ? $parsedRows[$r] : array();
                    $rowData = array_fill_keys($poFields, '');
                    foreach ($indexMap as $field => $idx) {
                        if (!array_key_exists($field, $rowData)) {
                            continue;
                        }
                        $rowData[$field] = isset($raw[$idx]) ? trim((string) $raw[$idx]) : '';
                    }

                    $rowData = poImpNormalizeRowData($rowData);

                    $isBlankRow = true;
                    foreach ($rowData as $rv) {
                        if ((string) $rv !== '') {
                            $isBlankRow = false;
                            break;
                        }
                    }
                    if ($isBlankRow) {
                        continue;
                    }

                    $rowNo = $r + 1;
                    $rowErrors = array();

                    foreach ($requiredFields as $requiredField) {
                        if (trim((string) $rowData[$requiredField]) === '') {
                            $rowErrors[] = 'Missing required field: ' . strtoupper($requiredField);
                        }
                    }

                    if ($rowData['doc_date'] !== '') {
                        $docDateDb = poImpToDbDate($rowData['doc_date']);
                        if ($docDateDb === '') {
                            $rowErrors[] = 'DOC_DATE must be DD/MM/YYYY.';
                        } else {
                            $rowData['doc_date'] = $docDateDb;
                        }
                    }

                    if ($rowData['seq'] !== '' && (!is_numeric($rowData['seq']) || (int) $rowData['seq'] <= 0)) {
                        $rowErrors[] = 'SEQ must be a positive number.';
                    }

                    if ($rowData['qty'] !== '' && (!is_numeric($rowData['qty']) || (float) $rowData['qty'] < 0)) {
                        $rowErrors[] = 'QTY must be a non-negative number.';
                    }

                    if ($rowData['unit_price'] !== '' && (!is_numeric($rowData['unit_price']) || (float) $rowData['unit_price'] < 0)) {
                        $rowErrors[] = 'UNIT_PRICE must be a non-negative number.';
                    }

                    $cmpKey = strtolower(trim((string) $rowData['company_name']));
                    if ($cmpKey === '' || !isset($companyMapByName[$cmpKey])) {
                        $rowErrors[] = 'COMPANY_NAME not found in active company table.';
                    } else {
                        $rowData['company_name'] = (string) $companyMapByName[$cmpKey]['name'];
                        $rowData['sql_account_id'] = (string) (int) $companyMapByName[$cmpKey]['sql_account_id'];
                    }

                    if ($rowData['amount'] === '' || !is_numeric($rowData['amount'])) {
                        if (is_numeric($rowData['qty']) && is_numeric($rowData['unit_price'])) {
                            $rowData['amount'] = number_format(((float) $rowData['qty'] * (float) $rowData['unit_price']), 2, '.', '');
                        }
                    }

                    if (!empty($rowErrors)) {
                        $importErrors[] = 'Row ' . $rowNo . ': ' . implode(' ', $rowErrors);
                        continue;
                    }

                    $seqInt = (int) $rowData['seq'];
                    $key = trim((string) $rowData['doc_no']) . '|' . $seqInt . '|' . trim((string) $rowData['item_code']);
                    $existing = isset($existingByKey[$key]) ? $existingByKey[$key] : null;

                    $changes = array();
                    if ($existing) {
                        foreach ($poFields as $field) {
                            $oldVal = trim((string) (isset($existing[$field]) ? $existing[$field] : ''));
                            $newVal = trim((string) (isset($rowData[$field]) ? $rowData[$field] : ''));
                            if ($oldVal !== $newVal) {
                                $changes[$field] = true;
                            }
                        }
                    }

                    if (!$existing || !empty($changes)) {
                        $previewData[] = array(
                            'row_no' => $rowNo,
                            'id' => $existing ? (int) $existing['id'] : 0,
                            'is_new' => $existing ? false : true,
                            'changes' => $changes,
                            'data' => $rowData,
                        );
                    }
                }

                if (empty($previewData) && empty($importErrors)) {
                    $importErrors[] = 'No new records or changes detected. The database is already up to date with this Excel file!';
                }
            }
        }
    }
} else if ($action === 'update') {
    $postData = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : array();
    if (empty($postData)) {
        $importErrors[] = 'No preview data found to update. Please scan and preview your file again.';
    } else {
        foreach ($postData as $row) {
            $rowData = array_fill_keys($poFields, '');
            foreach ($poFields as $field) {
                $rowData[$field] = isset($row[$field]) ? trim((string) $row[$field]) : '';
            }

            $rowData = poImpNormalizeRowData($rowData);

            $docDateDb = poImpToDbDate($rowData['doc_date']);
            if ($docDateDb === '') {
                $importErrors[] = 'Invalid DOC_DATE for DOC NO ' . htmlspecialchars((string) $rowData['doc_no'], ENT_QUOTES, 'UTF-8') . '. Date must be DD/MM/YYYY.';
                continue;
            }
            $rowData['doc_date'] = $docDateDb;

            $cmpKey = strtolower(trim((string) $rowData['company_name']));
            if ($cmpKey === '' || !isset($companyMapByName[$cmpKey])) {
                $importErrors[] = 'Company not found for DOC NO ' . htmlspecialchars((string) $rowData['doc_no'], ENT_QUOTES, 'UTF-8') . '.';
                continue;
            }
            $rowData['company_name'] = (string) $companyMapByName[$cmpKey]['name'];
            $rowData['sql_account_id'] = (string) (int) $companyMapByName[$cmpKey]['sql_account_id'];

            if ($rowData['amount'] === '' || !is_numeric($rowData['amount'])) {
                $rowData['amount'] = number_format(((float) $rowData['qty'] * (float) $rowData['unit_price']), 2, '.', '');
            }

            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $isNew = isset($row['is_new']) && (string) $row['is_new'] === '1';

            if ($isNew) {
                $insSql = "INSERT INTO " . $tblName . " (
                    doc_date, doc_no, code, company_name, description_hdr, seq, account,
                    item_code, description_dtl, qty, uom, unit_price, amount, sql_account_id,
                    remark, create_by, create_date, create_time, status
                ) VALUES (
                    '" . poImpEsc($connect, $rowData['doc_date']) . "',
                    '" . poImpEsc($connect, $rowData['doc_no']) . "',
                    '" . poImpEsc($connect, strtoupper($rowData['code'])) . "',
                    '" . poImpEsc($connect, $rowData['company_name']) . "',
                    '" . poImpEsc($connect, $rowData['description_hdr']) . "',
                    '" . (int) $rowData['seq'] . "',
                    '" . poImpEsc($connect, $rowData['account']) . "',
                    '" . poImpEsc($connect, $rowData['item_code']) . "',
                    '" . poImpEsc($connect, $rowData['description_dtl']) . "',
                    '" . number_format((float) $rowData['qty'], 2, '.', '') . "',
                    '" . poImpEsc($connect, $rowData['uom']) . "',
                    '" . number_format((float) $rowData['unit_price'], 2, '.', '') . "',
                    '" . number_format((float) $rowData['amount'], 2, '.', '') . "',
                    '" . (int) $rowData['sql_account_id'] . "',
                    '" . poImpEsc($connect, $rowData['remark']) . "',
                    '" . USER_ID . "', curdate(), curtime(), 'A'
                )";
                if (mysqli_query($connect, $insSql)) {
                    $insertCount++;
                }
            } else {
                $updSql = "UPDATE " . $tblName . " SET
                    doc_date='" . poImpEsc($connect, $rowData['doc_date']) . "',
                    doc_no='" . poImpEsc($connect, $rowData['doc_no']) . "',
                    code='" . poImpEsc($connect, strtoupper($rowData['code'])) . "',
                    company_name='" . poImpEsc($connect, $rowData['company_name']) . "',
                    description_hdr='" . poImpEsc($connect, $rowData['description_hdr']) . "',
                    seq='" . (int) $rowData['seq'] . "',
                    account='" . poImpEsc($connect, $rowData['account']) . "',
                    item_code='" . poImpEsc($connect, $rowData['item_code']) . "',
                    description_dtl='" . poImpEsc($connect, $rowData['description_dtl']) . "',
                    qty='" . number_format((float) $rowData['qty'], 2, '.', '') . "',
                    uom='" . poImpEsc($connect, $rowData['uom']) . "',
                    unit_price='" . number_format((float) $rowData['unit_price'], 2, '.', '') . "',
                    amount='" . number_format((float) $rowData['amount'], 2, '.', '') . "',
                    sql_account_id='" . (int) $rowData['sql_account_id'] . "',
                    remark='" . poImpEsc($connect, $rowData['remark']) . "',
                    update_by='" . USER_ID . "', update_date=curdate(), update_time=curtime()
                    WHERE id='" . (int) $rowId . "'";
                if (mysqli_query($connect, $updSql)) {
                    $updateCount++;
                }
            }
        }

        if (empty($importErrors)) {
            $log = array(
                'log_act' => 'Import',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => 'Bulk import update',
                'query_table' => $tblName,
                'newval' => 'Inserted=' . (int) $insertCount . ', Updated=' . (int) $updateCount,
                'act_msg' => USER_NAME . " imported purchase order data [ <b>New Added = " . (int) $insertCount . ", Updated = " . (int) $updateCount . "</b> ] into <b><i>" . $tblName . " Table</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            );
            audit_log($log);

            echo "<script>alert('Import complete. New added: " . (int) $insertCount . ", Updated: " . (int) $updateCount . "');window.location.href='" . $redirect_page . "';</script>";
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .highlight-change { background-color: #fff3cd !important; border-color: #ffecb5 !important; color: #664d03 !important; font-weight: bold; }
        .row-new { background-color: #d1e7dd !important; }
        .row-update { border-left: 4px solid #ffc107 !important; }
    </style>
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid mt-3 mb-5 d-flex justify-content-center">
        <div class="col-12 col-md-11">
            <div class="row mb-3">
                <p>
                    <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    <?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                    <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>"><i class="fa-solid fa-arrow-left"></i> Back To Table</a>
                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shortcut_page ?>">BACK TO SHORTCUTS</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($importErrors)) { ?>
                <div class="alert alert-danger shadow-sm" role="alert">
                    <?php foreach ($importErrors as $error) { ?>
                        <div>- <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (!empty($importWarnings)) { ?>
                <div class="alert alert-warning shadow-sm" role="alert">
                    <?php foreach ($importWarnings as $warning) { ?>
                        <div>- <?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($action === 'preview' && !empty($previewData)) { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Preview Changes</h5>
                        <p class="text-muted"><span class="badge bg-success">Green</span> rows will be inserted as new PO records. <span class="badge bg-warning text-dark">Yellow</span> fields are changed values.</p>
                        <form method="post" autocomplete="off">
                            <?php foreach ($previewData as $idx => $pRow) {
                                $chg = isset($pRow['changes']) ? $pRow['changes'] : array();
                                $d = isset($pRow['data']) ? $pRow['data'] : array();
                            ?>
                                <div class="card mb-3 <?= !empty($pRow['is_new']) ? 'row-new' : 'row-update' ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <h6 class="mb-0">Row #<?= (int) (isset($pRow['row_no']) ? $pRow['row_no'] : ($idx + 1)) ?></h6>
                                            <?= !empty($pRow['is_new']) ? '<span class="badge bg-success">NEW</span>' : '<span class="badge bg-warning text-dark">MODIFIED</span>' ?>
                                        </div>
                                        <div class="row g-3">
                                            <?php foreach ($poFields as $field) { ?>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label"><?= strtoupper(str_replace('_', ' ', $field)) ?></label>
                                                    <input type="text" class="form-control <?= isset($chg[$field]) ? 'highlight-change' : '' ?>" name="data[<?= $idx ?>][<?= $field ?>]" value="<?= htmlspecialchars(isset($d[$field]) ? (string) $d[$field] : '', ENT_QUOTES, 'UTF-8') ?>" <?= in_array($field, $requiredFields) ? 'required' : '' ?> <?= $field === 'sql_account_id' ? 'readonly' : '' ?>>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        <input type="hidden" name="data[<?= $idx ?>][id]" value="<?= (int) (isset($pRow['id']) ? $pRow['id'] : 0) ?>">
                                        <input type="hidden" name="data[<?= $idx ?>][is_new]" value="<?= !empty($pRow['is_new']) ? '1' : '0' ?>">
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                <a href="purchase_order_import.php" class="btn btn-sm btn-rounded btn-secondary">Cancel</a>
                                <button class="btn btn-sm btn-rounded btn-success" type="submit" name="actionBtn" value="update"><i class="fa-solid fa-cloud-arrow-up"></i> Execute Bulk Import & Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } else { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 1: Upload Edited Excel File</h5>
                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label" for="import_file"><b>Select Excel (.xlsx) File</b></label>
                                    <input class="form-control" type="file" name="import_file" id="import_file" accept=".xlsx" required>
                                </div>
                                <div class="col-12 col-md-4 d-grid">
                                    <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="preview"><i class="fa-solid fa-magnifying-glass"></i> Scan & Preview File</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script src="<?= $SITEURL ?>/js/purchase_order_import.js"></script>
<script>
    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
</body>
</html>