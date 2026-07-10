<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$currentPagePin = 167;
$parentPageTitle = 'Supplier Invoice';

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/import_pdf_common.php';

$resolvedParentPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedParentPageTitle !== '') {
    $parentPageTitle = $resolvedParentPageTitle;
}

$breadcrumbTitle = $parentPageTitle . ' Import';
$pageTitle = $breadcrumbTitle;
$pageHeading = $breadcrumbTitle;
$pinAccess = checkPinByGroupId($connect, $currentPagePin);
$redirectPage = $SITEURL . '/import/common_import.php';
$supplierInvoiceRedirectPage = $SITEURL . '/finance/supplier_invoice_table.php';

if (!is_array($pinAccess) || !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = ' . json_encode($SITEURL . '/dashboard.php') . ';</script>';
    exit;
}

function supplierInvoiceImportNormalizeText($value)
{
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(array("\xC2\xA0", "\r\n", "\r", "\n"), array(' ', ' ', ' ', ' '), $value);
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function supplierInvoiceImportNormalizeLookup($value)
{
    return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', supplierInvoiceImportNormalizeText($value)));
}

function supplierInvoiceImportLoadPackages($connect)
{
    $packages = array();
    $query = "SELECT id, name, item_code, item_description FROM `" . PKG . "` WHERE status = 'A' ORDER BY id DESC";
    $result = mysqli_query($connect, $query);
    if (!$result) {
        return $packages;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $packages[] = array(
            'id' => (int) ($row['id'] ?? 0),
            'name' => trim((string) ($row['name'] ?? '')),
            'item_code' => trim((string) ($row['item_code'] ?? '')),
            'item_description' => trim((string) ($row['item_description'] ?? '')),
        );
    }

    return $packages;
}

function supplierInvoiceImportFormatPackageRemark($packageName)
{
    $packageName = supplierInvoiceImportNormalizeText($packageName);
    return trim((string) preg_replace('/\s*\((?:MY|SG|WEB)\)\s*$/i', '', $packageName));
}

function supplierInvoiceImportExtractPackageSignals($description)
{
    $source = strtoupper(str_replace('⁺', '+', supplierInvoiceImportNormalizeText($description)));
    $signals = array(
        'category' => '',
        'product' => '',
        'total_boxes' => 0,
        'currency' => '',
        'is_urbanism' => stripos($source, 'URBANISM') !== false,
        'is_version_3' => preg_match('/\b3\.0\b/', $source) === 1,
    );

    if (preg_match('/\b(C\d+)\b/i', $source, $matches)) {
        $signals['category'] = strtoupper($matches[1]);
    }
    if (preg_match('/\bFULL\s+(MOONZ|SUNZ)\b/i', $source, $matches)) {
        $signals['product'] = strtoupper($matches[1]);
    }
    if (preg_match('/\bx\s*(\d+)\s*BOX(?:ES)?\b/i', $source, $matches)) {
        $signals['total_boxes'] = (int) $matches[1];
    }
    if (preg_match('/\b(?:TOTAL\s*)?\(\s*(RM|MYR|SGD|USD)\s*\)/i', $source, $matches)) {
        $signals['currency'] = strtoupper($matches[1]);
    }

    return $signals;
}

function supplierInvoiceImportMatchPackage($description, $packages, $currency = '')
{
    $signals = supplierInvoiceImportExtractPackageSignals($description);
    if ($signals['category'] === '' || $signals['product'] === '' || $signals['total_boxes'] <= 0) {
        return array();
    }

    $product = $signals['product'];
    $productToken = $product === 'MOONZ' ? 'M' : 'S';
    $expectedCodePart = (string) $signals['total_boxes'] . $productToken;
    $ranked = array();

    foreach ((array) $packages as $package) {
        $packageName = strtoupper(supplierInvoiceImportNormalizeText($package['name'] ?? ''));
        $itemCode = strtoupper(supplierInvoiceImportNormalizeText($package['item_code'] ?? ''));
        $itemDescription = strtoupper(supplierInvoiceImportNormalizeText($package['item_description'] ?? ''));
        $score = 0;

        if (strpos($itemCode, $signals['category']) !== false) {
            $score += 45;
        }
        if (preg_match('/(?:^|[-_])' . preg_quote($expectedCodePart, '/') . '(?:[-_]|$)/', $itemCode)) {
            $score += 45;
        }
        if (strpos($packageName, 'FULL ' . $product) !== false) {
            $score += 28;
        }
        if (strpos($itemDescription, $product) !== false) {
            $score += 24;
        }
        if (preg_match('/\b' . max(1, $signals['total_boxes'] - 1) . '\s+BOX(?:ES)?\s+' . $product . '\b/', $itemDescription)) {
            $score += 18;
        }
        if (strpos($itemDescription, (string) $signals['total_boxes'] . ' BOX') !== false) {
            $score += 8;
        }
        if ($signals['is_urbanism'] && strpos($packageName, 'URBANISM') !== false) {
            $score += 8;
        }
        if ($signals['is_version_3'] && strpos($packageName, '3.0') !== false) {
            $score += 10;
        }

        $packageCurrency = '';
        if (preg_match('/\((MY|SG|WEB)\)\s*$/', $packageName, $currencyMatches)) {
            $packageCurrency = strtoupper($currencyMatches[1]);
        }
        if ($currency === 'RM' || $currency === 'MYR') {
            $score += $packageCurrency === 'MY' ? 22 : 0;
            $score += strpos($itemCode, 'MY-SP') !== false ? 10 : 0;
            $score -= $packageCurrency === 'SG' ? 30 : 0;
            $score -= $packageCurrency === 'WEB' ? 10 : 0;
        } elseif ($currency === 'SGD') {
            $score += $packageCurrency === 'SG' ? 22 : 0;
            $score -= $packageCurrency === 'MY' ? 20 : 0;
        }

        if ($score > 0) {
            $ranked[] = array('score' => $score, 'id' => (int) ($package['id'] ?? 0), 'package' => $package);
        }
    }

    usort($ranked, function ($left, $right) {
        if ($left['score'] === $right['score']) {
            return $right['id'] <=> $left['id'];
        }
        return $right['score'] <=> $left['score'];
    });

    if (empty($ranked) || $ranked[0]['score'] < 90) {
        return array();
    }

    $matchedPackage = $ranked[0]['package'];
    $matchedPackage['remark'] = supplierInvoiceImportFormatPackageRemark($matchedPackage['name']);
    $matchedPackage['match_score'] = $ranked[0]['score'];
    return $matchedPackage;
}

function supplierInvoiceImportExtractInvoiceData($text, $packages)
{
    $text = supplierInvoiceImportNormalizeText($text);
    $text = supplierInvoiceImportRepairPdfTokenSpacing($text);
    $data = array(
        'doc_no' => '',
        'doc_date' => '',
        'description' => '',
        'remark' => '',
        'odr' => '',
        'amount' => '',
        'merchant_name' => '',
        'control_account' => '',
        'code' => '',
        'package_name' => '',
        'package_id' => '',
    );

    if (preg_match('/\bINVOICE\s*:\s*([A-Z0-9][A-Z0-9\-\/]+)/i', $text, $matches)) {
        $data['doc_no'] = trim($matches[1]);
    } elseif (preg_match('/\b(?:E-?INV|INV)\s*\d{4,}\/\d+\b/i', $text, $matches)) {
        $data['doc_no'] = preg_replace('/\s+/', '', trim($matches[0]));
    }
    if (preg_match('/\bDate\s*:\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/i', $text, $matches)) {
        $data['doc_date'] = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
    } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $text, $matches)) {
        $data['doc_date'] = sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
    }
    if (preg_match('/\b(MIX\s*&\s*MATCH\b.*?)(?=\s+\d+(?:\.\d+)?\s*PACKAGE\b)/i', $text, $matches)) {
        $candidateDescription = supplierInvoiceImportNormalizeText($matches[1]);
        if (preg_match('/\bx\s*\d+\s*BOX(?:ES)?\b/i', $candidateDescription)) {
            $data['description'] = $candidateDescription;
        }
    }
    if (preg_match('/\bODR\s*[A-Z0-9\-\/]+/i', $text, $matches)) {
        $data['odr'] = strtoupper(preg_replace('/\s+/', '', $matches[0]));
    }
    if ($data['description'] === '') {
        $data['description'] = supplierInvoiceImportBuildDescriptionFromSignals($text, $data['odr']);
    }
    if ($data['description'] !== '' && $data['odr'] !== '' && preg_match('/\bFOR$/i', $data['description'])) {
        $data['description'] .= ' ' . $data['odr'];
    }
    if (preg_match('/\bTOTAL\s*\(\s*(RM|MYR|SGD|USD)\s*\)\s*([\d,]+\.\d{2})/i', $text, $matches)) {
        $data['amount'] = number_format((float) str_replace(',', '', $matches[2]), 2, '.', '');
        $currency = strtoupper($matches[1]);
    } elseif (preg_match_all('/\b\d+(?:,\d{3})*\.\d{2}\b/', $text, $amountMatches) && !empty($amountMatches[0])) {
        $lastAmount = end($amountMatches[0]);
        $data['amount'] = number_format((float) str_replace(',', '', $lastAmount), 2, '.', '');
        $currency = '';
    } else {
        $currency = '';
    }

    if ($currency === '' && preg_match('/\b(?:RINGGIT\s+MALAYSIA|MYR|RM)\b/i', $text)) {
        $currency = 'RM';
    }

    if ($data['description'] !== '') {
        $matchedPackage = supplierInvoiceImportMatchPackage($data['description'], $packages, $currency);
        if (!empty($matchedPackage)) {
            $data['remark'] = $matchedPackage['remark'];
            $data['package_name'] = $matchedPackage['name'];
            $data['package_id'] = (string) $matchedPackage['id'];
        }
    }

    return $data;
}

function supplierInvoiceImportRepairPdfTokenSpacing($text)
{
    $text = supplierInvoiceImportNormalizeText($text);
    $text = preg_replace('/U\s*R\s*B\s*A\s*N\s*I\s*S\s*M\s*/i', 'URBANISM ', $text);
    $text = preg_replace('/F\s*U\s*L\s*L\s+(?=(?:MOONZ|SUNZ)\b)/i', 'FULL ', $text);
    $text = preg_replace('/M\s*O\s*O\s*N\s*Z\b/i', 'MOONZ', $text);
    $text = preg_replace('/S\s*U\s*N\s*Z\b/i', 'SUNZ', $text);
    return supplierInvoiceImportNormalizeText($text);
}

function supplierInvoiceImportBuildDescriptionFromSignals($text, $odr)
{
    $signals = supplierInvoiceImportExtractPackageSignals($text);
    if (stripos($text, 'MIX & MATCH') === false || $signals['category'] === '' || $signals['product'] === '' || $signals['total_boxes'] <= 0) {
        return '';
    }

    $brand = $signals['is_urbanism'] ? 'URBANISM ' : '';
    $description = 'MIX & MATCH ' . $signals['category'] . ' "' . $brand . 'FULL ' . $signals['product'] . '⁺ x ' . $signals['total_boxes'] . ' BOXES"';
    if ($odr !== '') {
        $description .= ' FOR ' . $odr;
    }

    return $description;
}

function supplierInvoiceImportDocNoExists($docNo, $financeConnect)
{
    $safeDocNo = mysqli_real_escape_string($financeConnect, (string) $docNo);
    $result = getData('id', "doc_no = '" . $safeDocNo . "'", 'LIMIT 1', SUPPLIER_INVOICE, $financeConnect);
    return $result && $result->num_rows > 0;
}

function supplierInvoiceImportValue($data, $key)
{
    return isset($data[$key]) ? supplierInvoiceImportNormalizeText($data[$key]) : '';
}

function supplierInvoiceImportNormalizeMerchantValue($value)
{
    $value = strtoupper(trim((string) $value));
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);
    $prefix = substr(preg_replace('/\D+/', '', $value), 0, 3);
    $suffixSource = substr($value, strlen($prefix));
    $suffix = substr(preg_replace('/[^A-Z0-9]+/', '', $suffixSource), 0, 5);

    if ($prefix === '') return $suffix;
    if ($suffix === '') return $prefix;
    return $prefix . '-' . $suffix;
}

$action = post('actionBtn');
if (!in_array($action, array('parseSupplierInvoice', 'insertSupplierInvoice'), true)) {
    $action = '';
}
if (post('cancelImportBtn') !== '' || $action === 'cancelImport') {
    echo '<script>location.href = ' . json_encode($redirectPage) . ';</script>';
    exit;
}

$importErrors = array();
$previewData = array();
$packages = supplierInvoiceImportLoadPackages($connect);
$uploadedFileName = '';

if ($action === 'parseSupplierInvoice') {
    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Supplier Invoice PDF file.';
    } elseif ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . (int) $_FILES['import_file']['error'];
    } elseif ($_FILES['import_file']['size'] > 8 * 1024 * 1024) {
        $importErrors[] = 'The uploaded file exceeds the maximum allowed size of 8MB.';
    } else {
        $uploadedFileName = isset($_FILES['import_file']['name']) ? (string) $_FILES['import_file']['name'] : '';
        $extension = strtolower(pathinfo($uploadedFileName, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $importErrors[] = 'Only PDF files are supported for Supplier Invoice Import.';
        } else {
            $rawContent = @file_get_contents($_FILES['import_file']['tmp_name']);
            if ($rawContent === false || $rawContent === '') {
                $importErrors[] = 'The uploaded PDF could not be read.';
            } elseif (strncmp($rawContent, '%PDF-', 5) !== 0) {
                $importErrors[] = 'The uploaded file is not a valid PDF document.';
            } else {
                // Prefer layout-preserving text extraction for text-based PDFs.
                // Fall back to the shared binary/content extractor when pdftotext
                // is unavailable in the current environment.
                $commandPdfText = extractTextFromPdfViaCommand((string) $_FILES['import_file']['tmp_name']);
                $rawPdfText = $commandPdfText !== ''
                    ? trim($commandPdfText)
                    : trim((string) extractTextFromPdfContent($rawContent));
                if ($rawPdfText === '') {
                    $importErrors[] = 'Unable to extract text from the uploaded Supplier Invoice PDF.';
                } else {
                    $previewData = supplierInvoiceImportExtractInvoiceData($rawPdfText, $packages);
                    if ($previewData['doc_no'] === '') {
                        $importErrors[] = 'Invoice No could not be detected from the PDF.';
                    } elseif (supplierInvoiceImportDocNoExists($previewData['doc_no'], $finance_connect)) {
                        $importErrors[] = 'This DocNo already exists in Supplier Invoice records.';
                    }
                    if ($previewData['doc_date'] === '') {
                        $importErrors[] = 'Invoice Date could not be detected from the PDF.';
                    }
                    if ($previewData['description'] === '') {
                        $importErrors[] = 'Invoice Description could not be detected from the PDF.';
                    }
                    if ($previewData['odr'] === '') {
                        $importErrors[] = 'ODR could not be detected from the PDF.';
                    }
                    if ($previewData['amount'] === '') {
                        $importErrors[] = 'Invoice Amount could not be detected from the PDF.';
                    }
                    if ($previewData['remark'] === '') {
                        $importErrors[] = 'The PDF description could not be matched to an active Package in the system database.';
                    }
                }
            }
        }
    }
}

if ($action === 'parseSupplierInvoice') {
    $action = !empty($previewData) ? 'preview' : '';
}

if ($action === 'insertSupplierInvoice') {
    $postedData = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : array();
    $docNo = supplierInvoiceImportValue($postedData, 'doc_no');
    $docDate = supplierInvoiceImportValue($postedData, 'doc_date');
    $description = supplierInvoiceImportValue($postedData, 'description');
    $remark = supplierInvoiceImportValue($postedData, 'remark');
    $odr = supplierInvoiceImportValue($postedData, 'odr');
    $amount = supplierInvoiceImportValue($postedData, 'amount');
    $qrUrl = supplierInvoiceImportValue($postedData, 'qr_url');
    $merchantName = supplierInvoiceImportValue($postedData, 'merchant_name');
    $controlAccount = supplierInvoiceImportNormalizeMerchantValue(supplierInvoiceImportValue($postedData, 'control_account'));
    $code = supplierInvoiceImportNormalizeMerchantValue(supplierInvoiceImportValue($postedData, 'code'));
    $packageName = supplierInvoiceImportValue($postedData, 'package_name');
    $previewData = array(
        'doc_no' => $docNo,
        'doc_date' => $docDate,
        'description' => $description,
        'remark' => $remark,
        'odr' => $odr,
        'amount' => $amount,
        'qr_url' => $qrUrl,
        'merchant_name' => $merchantName,
        'control_account' => $controlAccount,
        'code' => $code,
        'package_name' => $packageName,
        'package_id' => supplierInvoiceImportValue($postedData, 'package_id'),
    );

    if ($docNo === '' || $docDate === '' || $description === '' || $remark === '' || $odr === '' || $amount === '') {
        $importErrors[] = 'DocNo, DocDate, Description, Remark, ODR, and Amount are required.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate) || !checkdate((int) substr($docDate, 5, 2), (int) substr($docDate, 8, 2), (int) substr($docDate, 0, 4))) {
        $importErrors[] = 'DocDate is invalid.';
    } elseif (!is_numeric(str_replace(',', '', $amount))) {
        $importErrors[] = 'Amount is invalid.';
    } elseif ($controlAccount !== '' && !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $controlAccount)) {
        $importErrors[] = 'Control A/C format must be like 123-ABC01.';
    } elseif ($code !== '' && !preg_match('/^\d{3}-[A-Z0-9]{5}$/', $code)) {
        $importErrors[] = 'Code format must be like 123-ABC01.';
    } elseif ($qrUrl !== '' && (!filter_var($qrUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $qrUrl))) {
        $importErrors[] = 'The scanned QR value must be a valid http or https URL.';
    } elseif (supplierInvoiceImportDocNoExists($docNo, $finance_connect)) {
        $importErrors[] = 'This DocNo already exists in Supplier Invoice records.';
    }

    if (empty($importErrors)) {
        $amount = number_format((float) str_replace(',', '', $amount), 2, '.', '');
        $userId = (string) USER_ID;
        $insertedId = 0;
        mysqli_begin_transaction($finance_connect);

        $stmt = mysqli_prepare($finance_connect, "INSERT INTO " . SUPPLIER_INVOICE . " (doc_no, doc_date, description, control_account, code, amount, odr, remark, create_by, create_date, create_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())");
        if (!$stmt) {
            $importErrors[] = 'Unable to prepare the Supplier Invoice insert query.';
        } else {
            mysqli_stmt_bind_param($stmt, 'sssssssss', $docNo, $docDate, $description, $controlAccount, $code, $amount, $odr, $remark, $userId);
            if (!mysqli_stmt_execute($stmt)) {
                $importErrors[] = 'Unable to save the Supplier Invoice: ' . mysqli_stmt_error($stmt);
            } else {
                $insertedId = (int) mysqli_stmt_insert_id($stmt);
            }
            mysqli_stmt_close($stmt);
        }

        if (empty($importErrors) && $qrUrl !== '') {
            $qrStmt = mysqli_prepare($finance_connect, "INSERT INTO " . SUPPLIER_INVOICE_QR . " (supplier_invoice_id, qr_url, create_by, create_date, create_time) VALUES (?, ?, ?, CURDATE(), CURTIME())");
            if (!$qrStmt) {
                $importErrors[] = 'Unable to prepare the Supplier Invoice QR insert query.';
            } else {
                mysqli_stmt_bind_param($qrStmt, 'iss', $insertedId, $qrUrl, $userId);
                if (!mysqli_stmt_execute($qrStmt)) {
                    $importErrors[] = 'Unable to save the Supplier Invoice QR URL: ' . mysqli_stmt_error($qrStmt);
                }
                mysqli_stmt_close($qrStmt);
            }
        }

        if (empty($importErrors)) {
            mysqli_commit($finance_connect);
            audit_log(array(
                'log_act' => 'Import',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => 'Supplier Invoice PDF import',
                'query_table' => SUPPLIER_INVOICE,
                'newval' => implodeWithComma(array($docNo, $docDate, $description, $controlAccount, $code, $amount, $odr, $remark, $qrUrl)),
                'act_msg' => actMsgLog($insertedId, array('doc_no', 'doc_date', 'description', 'control_account', 'code', 'amount', 'odr', 'remark', 'qr_url'), array($docNo, $docDate, $description, $controlAccount, $code, $amount, $odr, $remark, $qrUrl), '', '', SUPPLIER_INVOICE, 'Import', ''),
                'page' => $pageTitle,
                'connect' => $connect,
            ));
            echo '<script>alert(' . json_encode('Supplier Invoice imported successfully.') . ');location.href = ' . json_encode($supplierInvoiceRedirectPage) . ';</script>';
            exit;
        }

        mysqli_rollback($finance_connect);
    }
    $action = 'preview';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => USER_NAME . ' viewed the page <b>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</b>.',
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .supplier-invoice-import-source { white-space: pre-wrap; }
        .supplier-invoice-import-preview textarea { min-height: 100px; }
    </style>
</head>
<body>
    <div class="page-load-cover">
        <div class="container-fluid mt-3 mb-5 d-flex justify-content-center">
            <div class="col-12 col-md-11">
                <div class="row mb-3">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="row mb-4">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                        <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $supplierInvoiceRedirectPage ?>"><i class="fa-solid fa-arrow-left"></i> Back To Table</a>
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirectPage ?>">BACK TO SHORTCUTS</a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($importErrors)) : ?>
                    <div class="alert alert-danger shadow-sm" role="alert">
                        <?php foreach ($importErrors as $error) : ?>
                            <div>- <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'preview' && !empty($previewData)) : ?>
                    <div class="card mb-4 shadow-sm border-0 supplier-invoice-import-preview">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 2: Preview Supplier Invoice</h5>
                            <p class="text-muted">The Description is preserved from the PDF. Remark is converted from the matching active Package record.</p>
                            <form method="post" autocomplete="off">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold" for="supplier_import_doc_no">DocNo</label>
                                        <input class="form-control" type="text" id="supplier_import_doc_no" name="data[doc_no]" value="<?= htmlspecialchars($previewData['doc_no'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold" for="supplier_import_doc_date">DocDate</label>
                                        <input class="form-control" type="date" id="supplier_import_doc_date" name="data[doc_date]" value="<?= htmlspecialchars($previewData['doc_date'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold" for="supplier_import_amount">Amount</label>
                                        <input class="form-control" type="number" step="0.01" id="supplier_import_amount" name="data[amount]" value="<?= htmlspecialchars($previewData['amount'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold" for="supplier_import_merchant_name">Merchant Name</label>
                                        <div class="autocomplete">
                                            <input class="form-control" type="text" id="supplier_import_merchant_name" name="data[merchant_name]" value="<?= htmlspecialchars($previewData['merchant_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                            <input type="hidden" id="supplier_import_merchant_hidden" value="">
                                        </div>
                                        <small class="text-muted">Select a merchant to auto-fill Control A/C and Code. The merchant ID is not saved.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold" for="supplier_import_control_account">Control A/C</label>
                                        <input class="form-control" type="text" id="supplier_import_control_account" name="data[control_account]" maxlength="9" value="<?= htmlspecialchars($previewData['control_account'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                        <?php if (!empty($importErrors) && preg_grep('/Control A\/C format/', $importErrors)) : ?><span class="error-message">Control A/C format must be like 123-ABC01.</span><?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold" for="supplier_import_code">Code</label>
                                        <input class="form-control" type="text" id="supplier_import_code" name="data[code]" maxlength="9" value="<?= htmlspecialchars($previewData['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                                        <?php if (!empty($importErrors) && preg_grep('/Code format/', $importErrors)) : ?><span class="error-message">Code format must be like 123-ABC01.</span><?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold" for="supplier_import_odr">ODR</label>
                                        <input class="form-control" type="text" id="supplier_import_odr" name="data[odr]" value="<?= htmlspecialchars($previewData['odr'], ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold" for="supplier_import_package">Matched Package</label>
                                        <input class="form-control" type="text" id="supplier_import_package" name="data[package_name]" value="<?= htmlspecialchars($previewData['package_name'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        <input type="hidden" name="data[package_id]" value="<?= htmlspecialchars($previewData['package_id'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold" for="supplier_import_description">Description</label>
                                        <textarea class="form-control supplier-invoice-import-source" id="supplier_import_description" name="data[description]" required><?= htmlspecialchars($previewData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold" for="supplier_import_remark">Remark</label>
                                        <textarea class="form-control" id="supplier_import_remark" name="data[remark]" required><?= htmlspecialchars($previewData['remark'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold" for="supplier_import_qr_image">QR Image</label>
                                        <input class="form-control" type="file" id="supplier_import_qr_image" accept="image/*">
                                        <input type="hidden" id="supplier_import_qr_url" name="data[qr_url]" value="<?= htmlspecialchars($previewData['qr_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <small class="text-muted">Select an image containing the QR code. Its URL will be saved separately from the invoice.</small>
                                        <div id="supplier_import_qr_scan_status" class="mt-2 text-muted" aria-live="polite"></div>
                                        <div id="supplier_import_qr_url_preview" class="mt-2"></div>
                                    </div>
                                </div>
                                <div class="import-preview-actions mt-4 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-lg btn-rounded btn-success px-4 import-preview-primary" type="submit" name="actionBtn" value="insertSupplierInvoice"><i class="fa-solid fa-cloud-arrow-up"></i> Import Supplier Invoice</button>
                                    <a href="<?= $SITEURL ?>/import/supplier_invoice_import.php" class="btn btn-lg btn-rounded btn-secondary import-preview-cancel">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 1: Upload Supplier Invoice PDF</h5>
                            <form method="post" enctype="multipart/form-data" autocomplete="off">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label fw-bold" for="import_file">Select Supplier Invoice PDF</label>
                                        <input class="form-control form-control-lg" type="file" name="import_file" id="import_file" accept=".pdf,application/pdf" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseSupplierInvoice"><i class="fa-solid fa-magnifying-glass"></i> Scan &amp; Preview PDF</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="<?= $SITEURL ?>/header/js/jsQR.js"></script>
    <script>
        document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        checkCurrentPage(<?= json_encode($pageTitle) ?>, '');
        dropdownMenuDispFix();
        setButtonColor();
        preloader(300, '');

        (function () {
            const merchantNameInput = document.getElementById('supplier_import_merchant_name');
            const merchantHiddenInput = document.getElementById('supplier_import_merchant_hidden');
            const controlAccountInput = document.getElementById('supplier_import_control_account');
            const codeInput = document.getElementById('supplier_import_code');
            const form = document.querySelector('.supplier-invoice-import-preview form');
            const qrInput = document.getElementById('supplier_import_qr_image');
            const qrUrlInput = document.getElementById('supplier_import_qr_url');
            const qrScanStatus = document.getElementById('supplier_import_qr_scan_status');
            const qrUrlPreview = document.getElementById('supplier_import_qr_url_preview');
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

            function applySupplierImportMerchantValues(merchant) {
                if (!merchant) return;
                if (controlAccountInput && merchant.control_account !== undefined) controlAccountInput.value = formatMerchantValue(merchant.control_account);
                if (codeInput && merchant.code !== undefined) codeInput.value = formatMerchantValue(merchant.code);
            }

            function autofillSupplierImportMerchant(merchant) {
                if (merchant && merchant.id && merchantHiddenInput) merchantHiddenInput.value = merchant.id;
                applySupplierImportMerchantValues(merchant);
                if (!merchantHiddenInput || merchantHiddenInput.value === '') return;

                retrieveDBData({
                    search: merchantHiddenInput.value,
                    searchCol: 'id',
                    searchType: '*',
                    dbTable: '<?= MERCHANT ?>',
                    isFin: 1
                }, '<?= $SITEURL ?>', function (result) {
                    if (!result || !result.length) return;
                    applySupplierImportMerchantValues(result[0]);
                });
            }

            if (merchantNameInput && merchantHiddenInput) {
                merchantNameInput.addEventListener('input', function () {
                    merchantHiddenInput.value = '';
                    searchInput({
                        search: merchantNameInput.value,
                        searchType: 'name',
                        elementID: 'supplier_import_merchant_name',
                        hiddenElementID: 'supplier_import_merchant_hidden',
                        dbTable: '<?= MERCHANT ?>',
                        onSelect: autofillSupplierImportMerchant
                    }, '<?= $SITEURL ?>');
                });
            }

            [controlAccountInput, codeInput].forEach(function (input) {
                if (input) input.addEventListener('input', function () { input.value = formatMerchantValue(input.value); });
            });

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
                const canvas = document.createElement('canvas');
                const scale = Math.min(1, 1200 / Math.max(image.width, image.height));
                canvas.width = Math.max(1, Math.round(image.width * scale));
                canvas.height = Math.max(1, Math.round(image.height * scale));
                const context = canvas.getContext('2d', { willReadFrequently: true });
                if (!context) return null;
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
                const edgeMargin = Math.max(5, Math.round(Math.min(canvas.width, canvas.height) * 0.03));
                let minX = canvas.width, minY = canvas.height, maxX = -1, maxY = -1;
                for (let y = edgeMargin; y < canvas.height - edgeMargin; y += 1) {
                    for (let x = edgeMargin; x < canvas.width - edgeMargin; x += 1) {
                        const index = (y * canvas.width + x) * 4;
                        if (Math.max(pixels[index], pixels[index + 1], pixels[index + 2]) < 210) {
                            minX = Math.min(minX, x); minY = Math.min(minY, y);
                            maxX = Math.max(maxX, x); maxY = Math.max(maxY, y);
                        }
                    }
                }
                if (maxX < 0 || maxY < 0) return null;
                return { x: minX / scale, y: minY / scale, width: (maxX - minX + 1) / scale, height: (maxY - minY + 1) / scale };
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
                if (context.imageSmoothingEnabled) context.imageSmoothingQuality = 'high';
                context.drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
                if (threshold !== null) {
                    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                    for (let index = 0; index < imageData.data.length; index += 4) {
                        const luminance = (imageData.data[index] * 299 + imageData.data[index + 1] * 587 + imageData.data[index + 2] * 114) / 1000;
                        const value = luminance < threshold ? 0 : 255;
                        imageData.data[index] = value;
                        imageData.data[index + 1] = value;
                        imageData.data[index + 2] = value;
                        imageData.data[index + 3] = 255;
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
                        const formats = typeof BarcodeDetector.getSupportedFormats === 'function' ? await BarcodeDetector.getSupportedFormats() : ['qr_code'];
                        if (formats.indexOf('qr_code') !== -1) {
                            const detected = await new BarcodeDetector({ formats: ['qr_code'] }).detect(canvas);
                            if (detected.length && detected[0].rawValue) return String(detected[0].rawValue).trim();
                        }
                    } catch (error) { /* Fall through to jsQR. */ }
                }
                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, canvas.width, canvas.height, { inversionAttempts: 'attemptBoth' });
                return code && code.data ? String(code.data).trim() : '';
            }

            async function decodeQrFromFile(file) {
                if (typeof jsQR === 'undefined') throw new Error('QR scanner is unavailable. Please refresh the page and try again.');
                const image = await readFileAsImage(file);
                const attempts = [];
                const fullScale = Math.max(2, Math.min(4, 1200 / Math.max(image.width, image.height)));
                attempts.push({ crop: null, scale: fullScale, threshold: null, smoothing: true });
                attempts.push({ crop: null, scale: fullScale, threshold: 180, smoothing: true });
                attempts.push({ crop: null, scale: fullScale, threshold: null, smoothing: false });
                const bounds = getDarkPixelBounds(image);
                if (bounds) {
                    const padding = Math.max(12, Math.round(Math.max(bounds.width, bounds.height) * 0.25));
                    const size = Math.max(bounds.width, bounds.height) + (padding * 2);
                    const centerX = bounds.x + bounds.width / 2;
                    const centerY = bounds.y + bounds.height / 2;
                    const x = Math.max(0, Math.min(image.width - size, centerX - size / 2));
                    const y = Math.max(0, Math.min(image.height - size, centerY - size / 2));
                    const crop = { x: x, y: y, width: Math.min(size, image.width - x), height: Math.min(size, image.height - y) };
                    const cropScale = Math.max(4, Math.min(8, 800 / Math.max(crop.width, crop.height)));
                    [null, 128, 180, 220].forEach(function (threshold) { attempts.push({ crop: crop, scale: cropScale, threshold: threshold, smoothing: true }); });
                    attempts.push({ crop: crop, scale: cropScale, threshold: null, smoothing: false });
                }
                for (const attempt of attempts) {
                    const value = await decodeQrCanvas(createQrCanvas(image, attempt.crop, attempt.scale, attempt.threshold, attempt.smoothing));
                    if (value) return value;
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
                link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = url;
                qrUrlPreview.appendChild(label); qrUrlPreview.appendChild(link);
            }

            function scanSelectedQrImage(file) {
                if (!file) return Promise.resolve(false);
                setQrScanStatus('Scanning QR code...', 'mt-2 text-info');
                if (qrUrlPreview) qrUrlPreview.textContent = '';
                qrScanPromise = decodeQrFromFile(file).then(function (url) {
                    if (!/^https?:\/\//i.test(url)) throw new Error('The QR code does not contain an http or https web URL.');
                    qrUrlInput.value = url;
                    scannedQrFile = file;
                    showScannedQrUrl(url);
                    setQrScanStatus('QR code scanned successfully. The URL will be saved with the invoice.', 'mt-2 text-success');
                    return true;
                }).catch(function (error) {
                    scannedQrFile = null;
                    qrUrlInput.value = '';
                    if (qrUrlPreview) qrUrlPreview.textContent = '';
                    setQrScanStatus(error && error.message ? error.message : 'Unable to scan the QR image.', 'mt-2 text-danger');
                    return false;
                }).finally(function () { qrScanPromise = null; });
                return qrScanPromise;
            }

            if (qrInput && qrUrlInput) {
                qrInput.addEventListener('change', function () {
                    const file = qrInput.files && qrInput.files.length ? qrInput.files[0] : null;
                    if (!file) return;
                    scannedQrFile = null;
                    qrUrlInput.value = '';
                    scanSelectedQrImage(file);
                });
            }

            if (form) {
                form.addEventListener('submit', async function (event) {
                    if (!qrInput || !qrInput.files || !qrInput.files.length || !qrUrlInput) return;
                    const file = qrInput.files[0];
                    if (file === scannedQrFile && qrUrlInput.value) return;
                    event.preventDefault();
                    const scanSucceeded = await (qrScanPromise || scanSelectedQrImage(file));
                    if (scanSucceeded) {
                        if (typeof form.requestSubmit === 'function') form.requestSubmit(event.submitter);
                        else form.submit();
                    }
                });
            }
        })();
    </script>
</body>
</html>
