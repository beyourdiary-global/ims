<?php
$currentPagePin = 127;
$pageTitle = '';

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = COMPANY;
$sqlAccountTbl = SQL_ACC;
$redirect_page = $SITEURL . '/company_table.php';
$shortcut_page = $SITEURL . '/common_import.php';
$parentPagePinGroupId = 127;
$parentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($parentPageTitle === '') {
    $parentPageTitle = 'Company';
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
    $log = [
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => USER_NAME . " viewed the page <b>" . $pageTitle . "</b>.",
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

function cmpImpEsc($conn, $value)
{
    return mysqli_real_escape_string($conn, (string) $value);
}

function parse_xlsx_company($filepath)
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

function cmpImpNormalizeKey($key)
{
    $key = strtolower(trim((string) $key));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    return trim((string) $key, '_');
}

function cmpImpAliasToField($headerKey)
{
    $map = array(
        'name' => 'name',
        'company_name' => 'name',
        'code' => 'code',
        'company_code' => 'code',
        'id_no' => 'id_no',
        'idno' => 'id_no',
        'reg_no' => 'id_no',
        'address1' => 'address1',
        'address2' => 'address2',
        'address3' => 'address3',
        'address4' => 'address4',
        'postcode' => 'postcode',
        'city' => 'city',
        'state' => 'state',
        'country' => 'country',
        'phone1' => 'phone1',
        'sales_tax_no' => 'sales_tax_no',
        'salestaxno' => 'sales_tax_no',
        'service_tax_no' => 'service_tax_no',
        'servicetaxno' => 'service_tax_no',
        'tin' => 'tin',
        'id_type' => 'id_type',
        'idtype' => 'id_type',
        'tourism_no' => 'tourism_no',
        'tourismno' => 'tourism_no',
        'sic' => 'sic',
        'income' => 'income',
        'submission_type' => 'submission_type',
        'submissiontype' => 'submission_type',
        'irbm_classification' => 'irbm_classification',
        'irbmclassification' => 'irbm_classification',
        'tax_exemption_reason' => 'tax_exemption_reason',
        'taxexemptionreason' => 'tax_exemption_reason',
        'sql_account_id' => 'sql_account_id',
        'sql_account' => 'sql_account_id',
        'sql_account_name' => 'sql_account_id',
        'remark' => 'remark',
    );

    return isset($map[$headerKey]) ? $map[$headerKey] : '';
}

$companyFields = array(
    'name', 'code', 'id_no', 'address1', 'address2', 'address3', 'address4',
    'postcode', 'city', 'state', 'country', 'phone1', 'sales_tax_no', 'service_tax_no',
    'tin', 'id_type', 'tourism_no', 'sic', 'income', 'submission_type',
    'irbm_classification', 'tax_exemption_reason', 'sql_account_id', 'remark'
);

$requiredFields = array(
    'name', 'code', 'id_no', 'address1', 'address2', 'address3', 'address4',
    'postcode', 'city', 'state', 'country', 'phone1', 'tin', 'id_type',
    'submission_type', 'irbm_classification', 'sql_account_id'
);

$sqlAccountMapByName = array();
$sqlAccRst = mysqli_query($connect, "SELECT id, name FROM " . $sqlAccountTbl . " WHERE status='A'");
if ($sqlAccRst) {
    while ($acc = mysqli_fetch_assoc($sqlAccRst)) {
        $accName = strtolower(trim((string) (isset($acc['name']) ? $acc['name'] : '')));
        if ($accName !== '') {
            $sqlAccountMapByName[$accName] = (int) $acc['id'];
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
            $parsedRows = parse_xlsx_company($fileTmpPath);
            if (is_array($parsedRows) && isset($parsedRows['error'])) {
                $importErrors[] = $parsedRows['error'];
            } else if (!is_array($parsedRows) || count($parsedRows) < 2) {
                $importErrors[] = 'No rows found in uploaded file.';
            } else {
                $existingByCode = array();
                $exRst = mysqli_query($connect, "SELECT * FROM " . $tblName . " WHERE status='A'");
                if ($exRst) {
                    while ($exRow = mysqli_fetch_assoc($exRst)) {
                        $codeKey = trim((string) (isset($exRow['code']) ? $exRow['code'] : ''));
                        if ($codeKey !== '') {
                            $existingByCode[$codeKey] = $exRow;
                        }
                    }
                }

                $headers = isset($parsedRows[0]) ? $parsedRows[0] : array();
                $indexMap = array();
                foreach ($headers as $idx => $headerName) {
                    $alias = cmpImpAliasToField(cmpImpNormalizeKey($headerName));
                    if ($alias !== '') {
                        $indexMap[$alias] = (int) $idx;
                    }
                }

                for ($r = 1; $r < count($parsedRows); $r++) {
                    $raw = isset($parsedRows[$r]) ? $parsedRows[$r] : array();
                    $rowData = array_fill_keys($companyFields, '');
                    foreach ($indexMap as $field => $idx) {
                        if (!array_key_exists($field, $rowData)) {
                            continue;
                        }
                        $val = isset($raw[$idx]) ? trim((string) $raw[$idx]) : '';
                        if ($field === 'country') {
                            $val = strtoupper($val);
                        }
                        $rowData[$field] = $val;
                    }

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

                    if ($rowData['country'] !== '' && strlen($rowData['country']) !== 2) {
                        $rowErrors[] = 'COUNTRY must be exactly 2 characters.';
                    }

                    $idType = (string) $rowData['id_type'];
                    if ($idType !== '' && !in_array($idType, array('0', '1', '2', '3', '4', '5'), true)) {
                        $rowErrors[] = 'ID_TYPE must be one of 0,1,2,3,4,5.';
                    }

                    $sqlAccRaw = trim((string) $rowData['sql_account_id']);
                    $sqlAccId = 0;
                    if ($sqlAccRaw !== '') {
                        if (ctype_digit($sqlAccRaw)) {
                            $sqlAccId = (int) $sqlAccRaw;
                        } else {
                            $sqlAccKey = strtolower($sqlAccRaw);
                            $sqlAccId = isset($sqlAccountMapByName[$sqlAccKey]) ? (int) $sqlAccountMapByName[$sqlAccKey] : 0;
                        }
                    }
                    if ($sqlAccId <= 0) {
                        $rowErrors[] = 'SQL_ACCOUNT is invalid or not found.';
                    }
                    $rowData['sql_account_id'] = (string) $sqlAccId;

                    $codeKey = trim((string) $rowData['code']);
                    $existing = isset($existingByCode[$codeKey]) ? $existingByCode[$codeKey] : null;

                    if (!empty($rowErrors)) {
                        $importErrors[] = 'Row ' . $rowNo . ': ' . implode(' ', $rowErrors);
                        continue;
                    }

                    $changes = array();
                    if ($existing) {
                        foreach ($companyFields as $field) {
                            $newVal = trim((string) $rowData[$field]);
                            $oldVal = trim((string) (isset($existing[$field]) ? $existing[$field] : ''));
                            if ($field === 'country') {
                                $oldVal = strtoupper($oldVal);
                            }
                            if ($newVal !== $oldVal) {
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
}
else if ($action === 'update') {
    $postData = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : array();
    if (empty($postData)) {
        $importErrors[] = 'No preview data found to update. Please scan and preview your file again.';
    } else {
        foreach ($postData as $row) {
            $rowData = array_fill_keys($companyFields, '');
            foreach ($companyFields as $field) {
                $rowData[$field] = isset($row[$field]) ? trim((string) $row[$field]) : '';
            }
            $rowData['country'] = strtoupper($rowData['country']);
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $isNew = isset($row['is_new']) && (string) $row['is_new'] === '1';

            if ($isNew) {
                $insSql = "INSERT INTO " . $tblName . " (
                    name, code, id_no, address1, address2, address3, address4,
                    postcode, city, state, country, phone1, sales_tax_no, service_tax_no,
                    tin, id_type, tourism_no, sic, income, submission_type,
                    irbm_classification, tax_exemption_reason, sql_account_id, remark,
                    create_by, create_date, create_time, status
                ) VALUES (
                    '" . cmpImpEsc($connect, $rowData['name']) . "',
                    '" . cmpImpEsc($connect, $rowData['code']) . "',
                    '" . cmpImpEsc($connect, $rowData['id_no']) . "',
                    '" . cmpImpEsc($connect, $rowData['address1']) . "',
                    '" . cmpImpEsc($connect, $rowData['address2']) . "',
                    '" . cmpImpEsc($connect, $rowData['address3']) . "',
                    '" . cmpImpEsc($connect, $rowData['address4']) . "',
                    '" . cmpImpEsc($connect, $rowData['postcode']) . "',
                    '" . cmpImpEsc($connect, $rowData['city']) . "',
                    '" . cmpImpEsc($connect, $rowData['state']) . "',
                    '" . cmpImpEsc($connect, $rowData['country']) . "',
                    '" . cmpImpEsc($connect, $rowData['phone1']) . "',
                    '" . cmpImpEsc($connect, $rowData['sales_tax_no']) . "',
                    '" . cmpImpEsc($connect, $rowData['service_tax_no']) . "',
                    '" . cmpImpEsc($connect, $rowData['tin']) . "',
                    '" . cmpImpEsc($connect, $rowData['id_type']) . "',
                    '" . cmpImpEsc($connect, $rowData['tourism_no']) . "',
                    '" . cmpImpEsc($connect, $rowData['sic']) . "',
                    '" . cmpImpEsc($connect, $rowData['income']) . "',
                    '" . cmpImpEsc($connect, $rowData['submission_type']) . "',
                    '" . cmpImpEsc($connect, $rowData['irbm_classification']) . "',
                    '" . cmpImpEsc($connect, $rowData['tax_exemption_reason']) . "',
                    '" . cmpImpEsc($connect, $rowData['sql_account_id']) . "',
                    '" . cmpImpEsc($connect, $rowData['remark']) . "',
                    '" . USER_ID . "', curdate(), curtime(), 'A'
                )";
                if (mysqli_query($connect, $insSql)) {
                    $insertCount++;
                }
            } else {
                $updSql = "UPDATE " . $tblName . " SET
                    name='" . cmpImpEsc($connect, $rowData['name']) . "',
                    code='" . cmpImpEsc($connect, $rowData['code']) . "',
                    id_no='" . cmpImpEsc($connect, $rowData['id_no']) . "',
                    address1='" . cmpImpEsc($connect, $rowData['address1']) . "',
                    address2='" . cmpImpEsc($connect, $rowData['address2']) . "',
                    address3='" . cmpImpEsc($connect, $rowData['address3']) . "',
                    address4='" . cmpImpEsc($connect, $rowData['address4']) . "',
                    postcode='" . cmpImpEsc($connect, $rowData['postcode']) . "',
                    city='" . cmpImpEsc($connect, $rowData['city']) . "',
                    state='" . cmpImpEsc($connect, $rowData['state']) . "',
                    country='" . cmpImpEsc($connect, $rowData['country']) . "',
                    phone1='" . cmpImpEsc($connect, $rowData['phone1']) . "',
                    sales_tax_no='" . cmpImpEsc($connect, $rowData['sales_tax_no']) . "',
                    service_tax_no='" . cmpImpEsc($connect, $rowData['service_tax_no']) . "',
                    tin='" . cmpImpEsc($connect, $rowData['tin']) . "',
                    id_type='" . cmpImpEsc($connect, $rowData['id_type']) . "',
                    tourism_no='" . cmpImpEsc($connect, $rowData['tourism_no']) . "',
                    sic='" . cmpImpEsc($connect, $rowData['sic']) . "',
                    income='" . cmpImpEsc($connect, $rowData['income']) . "',
                    submission_type='" . cmpImpEsc($connect, $rowData['submission_type']) . "',
                    irbm_classification='" . cmpImpEsc($connect, $rowData['irbm_classification']) . "',
                    tax_exemption_reason='" . cmpImpEsc($connect, $rowData['tax_exemption_reason']) . "',
                    sql_account_id='" . cmpImpEsc($connect, $rowData['sql_account_id']) . "',
                    remark='" . cmpImpEsc($connect, $rowData['remark']) . "',
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
                'act_msg' => USER_NAME . " imported company data [ <b>New Added = " . (int) $insertCount . ", Updated = " . (int) $updateCount . "</b> ] into <b><i>" . $tblName . " Table</i></b>.",
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
                        <p class="text-muted"><span class="badge bg-success">Green</span> rows will be inserted as new companies. <span class="badge bg-warning text-dark">Yellow</span> fields are changed values.</p>
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
                                            <?php foreach ($companyFields as $field) { ?>
                                                <div class="col-12 col-md-3">
                                                    <label class="form-label"><?= strtoupper(str_replace('_', ' ', $field)) ?></label>
                                                    <input type="text" class="form-control <?= isset($chg[$field]) ? 'highlight-change' : '' ?>" name="data[<?= $idx ?>][<?= $field ?>]" value="<?= htmlspecialchars(isset($d[$field]) ? (string) $d[$field] : '', ENT_QUOTES, 'UTF-8') ?>" <?= in_array($field, $requiredFields) ? 'required' : '' ?>>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        <input type="hidden" name="data[<?= $idx ?>][id]" value="<?= (int) (isset($pRow['id']) ? $pRow['id'] : 0) ?>">
                                        <input type="hidden" name="data[<?= $idx ?>][is_new]" value="<?= !empty($pRow['is_new']) ? '1' : '0' ?>">
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                <a href="company_import.php" class="btn btn-sm btn-rounded btn-secondary">Cancel</a>
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

<script src="<?= $SITEURL ?>/js/company_import.js"></script>
<script>
    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
</body>
</html>