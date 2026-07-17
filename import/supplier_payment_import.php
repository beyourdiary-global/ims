<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$currentPagePin = 169;
$parentPageTitle = 'Supplier Payment';

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/import_pdf_common.php';

$resolvedParentPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedParentPageTitle !== '') {
    $parentPageTitle = $resolvedParentPageTitle;
}

$pageTitle = $parentPageTitle . ' Import';
$redirectPage = $SITEURL . '/import/common_import.php';
$supplierPaymentRedirectPage = $SITEURL . '/finance/supplier_payment_table.php';
$pinAccess = checkPinByGroupId($connect, $currentPagePin);

if (!is_array($pinAccess) || !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = ' . json_encode($SITEURL . '/dashboard.php') . ';</script>';
    exit;
}

function supplierPaymentImportNormalizeText($value)
{
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(array("\xC2\xA0", "\r\n", "\r"), array(' ', ' ', ' '), $value);
    return trim((string) preg_replace('/[ \t]+/u', ' ', $value));
}

function supplierPaymentImportNormalizeAmount($value)
{
    $value = strtr((string) $value, array('O' => '0', 'o' => '0', 'I' => '1', 'l' => '1'));
    $value = str_replace(',', '', trim((string) preg_replace('/\s+/u', '', $value)));
    return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '';
}

function supplierPaymentImportNormalizeDescription($value)
{
    $value = supplierPaymentImportNormalizeText($value);
    $value = preg_replace('/\s*,\s*/', ', ', $value);
    $value = preg_replace('/\s*\/\s*/', '/', $value);
    $value = preg_replace('/\s*[-–—]\s*/u', '-', $value);
    return trim((string) $value);
}

function supplierPaymentImportFindMoney($text, $labelPattern)
{
    $moneyPattern = '([0-9OolI,]+(?:\s*\.\s*[0-9OolI]{2}))';
    $valueWindow = $labelPattern === 'AM[O0]UNT'
        ? '(?:(?!\bADD\s*SST\b|\bT[O0]TAL\b).){0,100}?'
        : '.{0,100}?';
    $pattern = '/\b' . $labelPattern . $valueWindow . 'M\s*Y\s*R\s*' . $moneyPattern . '/is';
    if (preg_match($pattern, (string) $text, $matches)) {
        return supplierPaymentImportNormalizeAmount($matches[1]);
    }
    return '';
}

function supplierPaymentImportFindTesseract()
{
    $configuredPath = defined('TESSERACT_BIN') ? trim((string) TESSERACT_BIN) : '';
    if ($configuredPath !== '' && is_file($configuredPath)) {
        return $configuredPath;
    }

    if (!function_exists('shell_exec')) {
        return '';
    }

    $disabledFunctions = array_filter(array_map('trim', explode(',', strtolower((string) ini_get('disable_functions')))));
    if (in_array('shell_exec', $disabledFunctions, true)) {
        return '';
    }

    $command = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where tesseract 2>NUL' : 'command -v tesseract 2>/dev/null';
    $path = trim((string) shell_exec($command));
    if (strpos($path, "\n") !== false) {
        $path = trim((string) preg_split('/\r?\n/', $path)[0]);
    }
    return $path;
}

function supplierPaymentImportExtractJpegStreams($pdfContent)
{
    $images = array();
    $offset = 0;
    $contentLength = strlen((string) $pdfContent);

    while ($offset < $contentLength && preg_match('/\/Subtype\s*\/Image/', $pdfContent, $imageMatch, PREG_OFFSET_CAPTURE, $offset)) {
        $imageOffset = (int) $imageMatch[0][1];
        $streamOffset = strpos($pdfContent, 'stream', $imageOffset);
        if ($streamOffset === false) {
            break;
        }

        $dictionary = substr($pdfContent, $imageOffset, $streamOffset - $imageOffset);
        if (stripos($dictionary, 'DCTDecode') === false) {
            $offset = $streamOffset + 6;
            continue;
        }

        $streamStart = $streamOffset + 6;
        while ($streamStart < $contentLength && ($pdfContent[$streamStart] === "\r" || $pdfContent[$streamStart] === "\n")) {
            $streamStart++;
        }

        $streamLength = 0;
        if (preg_match('/\/Length\s+(\d+)/', $dictionary, $lengthMatch)) {
            $streamLength = (int) $lengthMatch[1];
        }
        if ($streamLength > 0) {
            $image = substr($pdfContent, $streamStart, $streamLength);
        } else {
            $streamEnd = strpos($pdfContent, 'endstream', $streamStart);
            if ($streamEnd === false) {
                break;
            }
            $image = rtrim(substr($pdfContent, $streamStart, $streamEnd - $streamStart), "\r\n");
        }

        if (strncmp($image, "\xFF\xD8\xFF", 3) === 0) {
            $images[] = $image;
        }
        $offset = $streamStart + max(1, $streamLength);
    }

    return $images;
}

function supplierPaymentImportRunOcr($imagePath, $tesseractPath)
{
    if ($imagePath === '' || !is_file($imagePath) || $tesseractPath === '' || !function_exists('shell_exec')) {
        return '';
    }

    $command = escapeshellarg($tesseractPath) . ' ' . escapeshellarg($imagePath) . ' stdout -l eng --psm 6';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command .= ' 2>NUL';
    } else {
        $command .= ' 2>/dev/null';
    }
    return supplierPaymentImportNormalizeText((string) shell_exec($command));
}

function supplierPaymentImportExtractOcrText($pdfContent, $pdfPath)
{
    $tesseractPath = supplierPaymentImportFindTesseract();
    if ($tesseractPath === '') {
        return '';
    }

    $ocrText = array();
    foreach (supplierPaymentImportExtractJpegStreams($pdfContent) as $image) {
        $imagePath = tempnam(sys_get_temp_dir(), 'supplier_payment_ocr_');
        if ($imagePath === false) {
            continue;
        }
        $jpgPath = $imagePath . '.jpg';
        @rename($imagePath, $jpgPath);
        @file_put_contents($jpgPath, $image);
        $text = supplierPaymentImportRunOcr($jpgPath, $tesseractPath);
        @unlink($jpgPath);
        if ($text !== '') {
            $ocrText[] = $text;
        }
    }

    if (!empty($ocrText)) {
        return implode("\n", $ocrText);
    }

    if ($pdfPath !== '' && is_file($pdfPath) && function_exists('shell_exec')) {
        $prefix = tempnam(sys_get_temp_dir(), 'supplier_payment_pdf_');
        if ($prefix !== false) {
            @unlink($prefix);
            $command = 'pdftoppm -jpeg -f 1 -singlefile ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($prefix);
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $command .= ' 2>NUL';
            } else {
                $command .= ' 2>/dev/null';
            }
            shell_exec($command);
            $renderedPath = $prefix . '.jpg';
            $text = supplierPaymentImportRunOcr($renderedPath, $tesseractPath);
            @unlink($renderedPath);
            if ($text !== '') {
                return $text;
            }
        }
    }

    return '';
}

function supplierPaymentImportExtractData($text)
{
    $text = supplierPaymentImportNormalizeText($text);
    $text = preg_replace('/\bN0\b/i', 'NO', $text);
    $text = preg_replace('/\bAM0UNT\b/i', 'AMOUNT', $text);
    $text = preg_replace('/\bT0TAL\b/i', 'TOTAL', $text);
    $text = preg_replace('/\bPAID\s+0N\b/i', 'PAID ON', $text);
    $text = preg_replace('/\bCREATION\s+T1ME\b/i', 'CREATION TIME', $text);
    $data = array(
        'doc_date' => '',
        'bill_no' => '',
        'description' => '',
        'quantity' => '',
        'amount' => '',
        'add_sst' => '0.00',
        'total' => '',
        'code' => '',
        'remark' => '',
    );

    if (preg_match('/\bBILL\s*(?:N[O0]\.?\s*#?|#)\s*(\d{8,})/i', $text, $matches)) {
        $data['bill_no'] = $matches[1];
    }
    if (preg_match('/\bPAID\s+[O0]N\s+(\d{4}-\d{2}-\d{2})/i', $text, $matches)) {
        $data['doc_date'] = $matches[1];
    } elseif (preg_match('/\bCREATION\s+TIME\s+(\d{4}-\d{2}-\d{2})/i', $text, $matches)) {
        $data['doc_date'] = $matches[1];
    }

    if (preg_match('/\b(transaction\s+fee\s+bill\b\s*,?\s*\d{1,2}\s*\/\s*\d{1,2}\s*\/\s*\d{4}\s*[-–—]\s*\d{1,2}\s*\/\s*\d{1,2}\s*\/\s*\d{4})/iu', $text, $matches)) {
        $data['description'] = supplierPaymentImportNormalizeDescription($matches[1]);
    } elseif (preg_match('/\b(transaction\s+fee\s+bill\b.*?)(?=\s+\d+(?:\.\d+)?\s+M\s*Y\s*R\s*[\d,]+\.\d{2}\b)/i', $text, $matches)) {
        $data['description'] = supplierPaymentImportNormalizeDescription($matches[1]);
    }
    if (preg_match('/\btransaction\s+fee\s+bill\b.*?\s+(\d+(?:\.\d+)?)\s*M\s*Y\s*R\s*[\d,]+\.\d{2}\b/i', $text, $matches)) {
        $data['quantity'] = number_format((float) $matches[1], 3, '.', '');
    }
    if ($data['quantity'] === '' && preg_match('/\btransaction\s+fee\s+bill\b.*?\s+([Il])\s*M\s*Y\s*R/i', $text, $matches)) {
        $data['quantity'] = '1.000';
    }

    if ($data['quantity'] === '' && preg_match('/\bQUANTITY\b\s*[:.]?\s*(\d+(?:\.\d+)?)/i', $text, $matches)) {
        $data['quantity'] = number_format((float) $matches[1], 3, '.', '');
    }
    $amount = supplierPaymentImportFindMoney($text, 'AM[O0]UNT');
    if ($amount !== '') {
        $data['amount'] = $amount;
    } elseif (preg_match('/\bAM[O0]UNT\s*[:.]?(?:(?!\bADD\s*SST\b|\bT[O0]TAL\b).){0,100}?([0-9OolI,]+\s*\.\s*[0-9OolI]{2})/is', $text, $matches)) {
        $data['amount'] = supplierPaymentImportNormalizeAmount($matches[1]);
    }
    $sstAmount = supplierPaymentImportFindMoney($text, 'ADD\s*SST\s*@\s*8\s*%');
    if ($sstAmount !== '') {
        $data['add_sst'] = $sstAmount;
    } elseif (preg_match('/\bADD\s*SST\s*@\s*8\s*%\s*[:.]?.{0,100}?([0-9OolI,]+\s*\.\s*[0-9OolI]{2})/is', $text, $matches)) {
        $data['add_sst'] = supplierPaymentImportNormalizeAmount($matches[1]);
    }
    $total = supplierPaymentImportFindMoney($text, 'T[O0]TAL');
    if ($total !== '') {
        $data['total'] = $total;
    } elseif (preg_match('/\bT[O0]TAL\s*[:.]?.{0,100}?([0-9OolI,]+\s*\.\s*[0-9OolI]{2})/is', $text, $matches)) {
        $data['total'] = supplierPaymentImportNormalizeAmount($matches[1]);
    }

    if ($data['amount'] !== '' && $sstAmount !== '' && abs((float) $data['amount'] - (float) $sstAmount) < 0.005) {
        $data['amount'] = '';
    }

    $moneyValues = array();
    if (preg_match_all('/M\s*Y\s*R\s*([0-9OolI,]+\s*\.\s*[0-9OolI]{2})/i', $text, $moneyMatches)) {
        foreach ($moneyMatches[1] as $moneyValue) {
            $normalizedMoney = supplierPaymentImportNormalizeAmount($moneyValue);
            if ($normalizedMoney !== '') {
                $moneyValues[] = $normalizedMoney;
            }
        }
    }
    if ($data['total'] === '' && !empty($moneyValues)) {
        $data['total'] = number_format(max(array_map('floatval', $moneyValues)), 2, '.', '');
    }
    if ($data['amount'] !== '' && $data['total'] !== '' && (float) $data['add_sst'] > 0 && (float) $data['amount'] >= (float) $data['total']) {
        $data['amount'] = number_format(round((float) $data['total'] - (float) $data['add_sst'], 2), 2, '.', '');
    }
    if ($data['amount'] === '') {
        foreach ($moneyValues as $moneyValue) {
            if ((float) $moneyValue > 0
                && ($sstAmount === '' || abs((float) $moneyValue - (float) $sstAmount) >= 0.005)
                && ($data['total'] === '' || abs((float) $moneyValue - (float) $data['total']) >= 0.005)) {
                $data['amount'] = $moneyValue;
                break;
            }
        }
    }
    if ($data['amount'] === '' && $data['total'] !== '' && (float) $data['add_sst'] > 0) {
        $data['amount'] = number_format(round((float) $data['total'] - (float) $data['add_sst'], 2), 2, '.', '');
    }
    if ($data['quantity'] === '' && $data['amount'] !== '' && $data['total'] !== '') {
        $netAmount = (float) $data['total'] - (float) $data['add_sst'];
        if ($netAmount > 0 && (float) $data['amount'] > 0) {
            $derivedQuantity = round($netAmount / (float) $data['amount'], 3);
            if ($derivedQuantity > 0) {
                $data['quantity'] = number_format($derivedQuantity, 3, '.', '');
            }
        }
    }

    if ($data['total'] === '' && $data['quantity'] !== '' && $data['amount'] !== '') {
        $data['total'] = number_format(round((float) $data['quantity'] * (float) $data['amount'] + (float) $data['add_sst'], 2), 2, '.', '');
    }

    return $data;
}

function supplierPaymentImportDataScore($data)
{
    $fields = array('doc_date', 'bill_no', 'description', 'quantity', 'amount', 'total');
    $score = 0;
    foreach ($fields as $field) {
        if (isset($data[$field]) && trim((string) $data[$field]) !== '') {
            $score++;
        }
    }
    return $score;
}

function supplierPaymentImportFieldErrors($errors, $field)
{
    $keywords = array(
        'file' => array('Please choose', 'File upload', 'exceeds the maximum', 'Only PDF', 'not a valid PDF', 'could not be read', 'Unable to extract'),
        'doc_date' => array('DocDate'),
        'bill_no' => array('Bill No'),
        'description' => array('Description'),
        'quantity' => array('Quantity'),
        'amount' => array('Amount'),
        'add_sst' => array('Add SST'),
        'total' => array('Total'),
        'code' => array('merchant Code', 'Code is required', 'Code is invalid'),
    );
    $fieldKeywords = isset($keywords[$field]) ? $keywords[$field] : array();
    $fieldErrors = array();
    foreach ((array) $errors as $error) {
        foreach ($fieldKeywords as $keyword) {
            if (stripos((string) $error, $keyword) !== false) {
                $fieldErrors[] = (string) $error;
                break;
            }
        }
    }
    return array_values(array_unique($fieldErrors));
}

function supplierPaymentImportValidDate($value)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)
        && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
}

function supplierPaymentImportNormalizeCode($value)
{
    $value = strtoupper(trim((string) $value));
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);
    $prefix = substr(preg_replace('/\D+/', '', $value), 0, 3);
    $suffix = substr(preg_replace('/[^A-Z0-9]+/', '', substr($value, strlen($prefix))), 0, 5);
    if ($prefix === '') return $suffix;
    if ($suffix === '') return $prefix;
    return $prefix . '-' . $suffix;
}

function supplierPaymentImportBillExists($billNo, $financeConnect)
{
    $safeBillNo = mysqli_real_escape_string($financeConnect, (string) $billNo);
    $result = getData('id', "bill_no = '" . $safeBillNo . "' AND status = 'A'", 'LIMIT 1', SUPPLIER_PAYMENT, $financeConnect);
    return $result && $result->num_rows > 0;
}

$action = post('actionBtn');
$importErrors = array();
$previewData = array();

if (post('cancelImportBtn') !== '' || $action === 'cancelImport') {
    echo '<script>location.href = ' . json_encode($redirectPage) . ';</script>';
    exit;
}

if ($action === 'parseSupplierPayment') {
    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Supplier Payment PDF file.';
    } elseif ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . (int) $_FILES['import_file']['error'];
    } elseif ($_FILES['import_file']['size'] > 8 * 1024 * 1024) {
        $importErrors[] = 'The uploaded file exceeds the maximum allowed size of 8MB.';
    } else {
        $uploadedFileName = (string) ($_FILES['import_file']['name'] ?? '');
        if (strtolower(pathinfo($uploadedFileName, PATHINFO_EXTENSION)) !== 'pdf') {
            $importErrors[] = 'Only PDF files are supported for Supplier Payment Import.';
        } else {
            $rawContent = @file_get_contents($_FILES['import_file']['tmp_name']);
            if ($rawContent === false || strncmp($rawContent, '%PDF-', 5) !== 0) {
                $importErrors[] = 'The uploaded file is not a valid PDF document.';
            } else {
                $textCandidates = array();
                $clientOcrText = trim((string) ($_POST['client_ocr_text'] ?? ''));
                if ($clientOcrText !== '') {
                    $textCandidates[] = $clientOcrText;
                }
                $commandText = trim((string) extractTextFromPdfViaCommand((string) $_FILES['import_file']['tmp_name']));
                if ($commandText !== '') {
                    $textCandidates[] = $commandText;
                }
                $contentText = trim((string) extractTextFromPdfContent($rawContent));
                if ($contentText !== '') {
                    $textCandidates[] = $contentText;
                }
                $ocrText = trim((string) supplierPaymentImportExtractOcrText($rawContent, (string) $_FILES['import_file']['tmp_name']));
                if ($ocrText !== '') {
                    $textCandidates[] = $ocrText;
                }

                $rawText = '';
                $previewData = supplierPaymentImportExtractData('');
                $bestScore = -1;
                foreach ($textCandidates as $candidateText) {
                    $candidateData = supplierPaymentImportExtractData($candidateText);
                    $candidateScore = supplierPaymentImportDataScore($candidateData);
                    if ($candidateScore > $bestScore) {
                        $bestScore = $candidateScore;
                        $rawText = $candidateText;
                        $previewData = $candidateData;
                    }
                }
                if ($previewData['bill_no'] === '') $importErrors[] = 'Bill No could not be detected from the PDF.';
                if ($previewData['doc_date'] === '') $importErrors[] = 'DocDate could not be detected from the PDF.';
                if ($previewData['description'] === '') $importErrors[] = 'Description could not be detected from the PDF.';
                if ($previewData['quantity'] === '') $importErrors[] = 'Quantity could not be detected from the PDF.';
                if ($previewData['amount'] === '') $importErrors[] = 'Amount could not be detected from the PDF.';
                if ($previewData['total'] === '') $importErrors[] = 'Total could not be detected from the PDF.';
                if ($previewData['bill_no'] !== '' && supplierPaymentImportBillExists($previewData['bill_no'], $finance_connect)) {
                    $importErrors[] = 'This Bill No already exists in Supplier Payment records.';
                }
            }
        }
    }
    if (!empty($previewData)) {
        $action = 'preview';
    }
}

if ($action === 'insertSupplierPayment') {
    $postedData = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : array();
    $docDate = trim((string) ($postedData['doc_date'] ?? ''));
    $code = supplierPaymentImportNormalizeCode($postedData['code'] ?? '');
    $billNo = trim((string) ($postedData['bill_no'] ?? ''));
    $description = trim((string) ($postedData['description'] ?? ''));
    $quantityRaw = trim((string) ($postedData['quantity'] ?? ''));
    $amountRaw = trim((string) ($postedData['amount'] ?? ''));
    $addSst = trim((string) ($postedData['add_sst'] ?? '0'));
    $remark = trim((string) ($postedData['remark'] ?? ''));
    $quantityValue = str_replace(',', '', $quantityRaw);
    $amountValue = str_replace(',', '', $amountRaw);
    $previewData = array('doc_date' => $docDate, 'code' => $code, 'bill_no' => $billNo, 'description' => $description, 'quantity' => $quantityRaw, 'amount' => $amountRaw, 'add_sst' => $addSst, 'total' => '', 'remark' => $remark);

    if (!supplierPaymentImportValidDate($docDate)) $importErrors[] = 'DocDate is invalid.';
    if (!preg_match('/^\d{3}-[A-Z0-9]{5}$/', $code)) $importErrors[] = 'Please select a valid merchant Code.';
    if ($billNo === '') $importErrors[] = 'Bill No is required.';
    if ($description === '') $importErrors[] = 'Description is required.';
    if (!is_numeric($quantityValue) || (float) $quantityValue <= 0) $importErrors[] = 'Quantity is invalid.';
    if (!is_numeric($amountValue) || (float) $amountValue <= 0) $importErrors[] = 'Amount is invalid.';
    $addSstValue = str_replace(',', '', $addSst);
    if ($addSst === '' || !is_numeric($addSstValue) || (float) $addSstValue < 0) $importErrors[] = 'Add SST is invalid.';
    if (supplierPaymentImportBillExists($billNo, $finance_connect)) $importErrors[] = 'This Bill No already exists in Supplier Payment records.';

    $quantity = number_format((float) $quantityValue, 3, '.', '');
    $amount = number_format((float) $amountValue, 2, '.', '');
    $addSst = number_format((float) $addSstValue, 2, '.', '');
    $total = number_format(round((float) $quantity * (float) $amount + (float) $addSst, 2), 2, '.', '');
    $previewData['quantity'] = $quantity;
    $previewData['amount'] = $amount;
    $previewData['total'] = $total;

    if (empty($importErrors)) {
        mysqli_begin_transaction($finance_connect);
        $stmt = mysqli_prepare($finance_connect, 'INSERT INTO ' . SUPPLIER_PAYMENT . ' (doc_date, code, bill_no, description, quantity, amount, add_sst, total, remark, create_by, create_date, create_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())');
        if (!$stmt) {
            $importErrors[] = 'Unable to prepare the Supplier Payment insert query.';
        } else {
            $userId = (string) USER_ID;
            mysqli_stmt_bind_param($stmt, 'ssssddidss', $docDate, $code, $billNo, $description, $quantity, $amount, $addSst, $total, $remark, $userId);
            if (!mysqli_stmt_execute($stmt)) {
                $importErrors[] = 'Unable to save the Supplier Payment: ' . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        if (empty($importErrors)) {
            mysqli_commit($finance_connect);
            audit_log(array('log_act' => 'Import', 'cdate' => $cdate, 'ctime' => $ctime, 'uid' => USER_ID, 'cby' => USER_ID, 'query_rec' => 'Supplier Payment PDF import', 'query_table' => SUPPLIER_PAYMENT, 'newval' => implodeWithComma(array($docDate, $code, $billNo, $description, $quantity, $amount, $addSst, $total, $remark)), 'act_msg' => 'Supplier Payment PDF imported.', 'page' => $pageTitle, 'connect' => $connect));
            echo '<script>alert(' . json_encode('Supplier Payment imported successfully.') . ');location.href = ' . json_encode($supplierPaymentRedirectPage) . ';</script>';
            exit;
        }
        mysqli_rollback($finance_connect);
    }
    $action = 'preview';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>.supplier-payment-import-source { white-space: pre-wrap; }</style>
</head>
<body>
    <div class="page-load-cover">
        <div class="container-fluid mt-3 mb-5 d-flex justify-content-center">
            <div class="col-12 col-md-11">
                <div class="row mb-3"><p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p></div>
                <div class="row mb-4"><div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2"><h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2><div class="d-flex gap-2"><a class="btn btn-lg btn-rounded btn-primary" href="<?= $supplierPaymentRedirectPage ?>"><i class="fa-solid fa-arrow-left"></i> Back To Table</a><a class="btn btn-lg btn-rounded btn-primary" href="<?= $redirectPage ?>">BACK TO SHORTCUTS</a></div></div></div>
                <?php if ($action === 'preview' && !empty($previewData)) : ?>
                    <div class="card mb-4 shadow-sm border-0"><div class="card-body"><h5 class="card-title mb-3">Step 2: Preview Supplier Payment</h5><p class="text-muted">Code and Remark are intentionally left blank for selection.</p>
                        <form method="post" autocomplete="off">
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label fw-bold" for="supplier_import_doc_date">DocDate</label><input class="form-control" type="date" id="supplier_import_doc_date" name="data[doc_date]" value="<?= htmlspecialchars($previewData['doc_date'], ENT_QUOTES, 'UTF-8') ?>" required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'doc_date') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-md-4"><label class="form-label fw-bold" for="supplier_import_bill_no">Bill No.</label><input class="form-control" type="text" id="supplier_import_bill_no" name="data[bill_no]" value="<?= htmlspecialchars($previewData['bill_no'], ENT_QUOTES, 'UTF-8') ?>" required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'bill_no') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-md-4"><label class="form-label fw-bold" for="supplier_import_quantity">Quantity</label><input class="form-control" type="number" step="0.001" min="0.001" id="supplier_import_quantity" name="data[quantity]" value="<?= htmlspecialchars($previewData['quantity'], ENT_QUOTES, 'UTF-8') ?>" required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'quantity') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-md-6"><label class="form-label fw-bold" for="supplier_import_merchant_name">Code</label><div class="autocomplete"><input class="form-control" type="text" id="supplier_import_merchant_name" placeholder="Select merchant" autocomplete="off"><input type="hidden" id="supplier_import_merchant_hidden"></div><input class="form-control mt-2" type="text" id="supplier_import_code" name="data[code]" value="<?= htmlspecialchars($previewData['code'], ENT_QUOTES, 'UTF-8') ?>" readonly required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'code') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-md-6"><label class="form-label fw-bold" for="supplier_import_amount">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" id="supplier_import_amount" name="data[amount]" value="<?= htmlspecialchars($previewData['amount'], ENT_QUOTES, 'UTF-8') ?>" required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'amount') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-md-6"><label class="form-label fw-bold" for="supplier_import_add_sst">Add SST</label><input class="form-control" type="number" min="0" step="0.01" id="supplier_import_add_sst" name="data[add_sst]" value="<?= htmlspecialchars($previewData['add_sst'], ENT_QUOTES, 'UTF-8') ?>" required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'add_sst') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-md-6"><label class="form-label fw-bold" for="supplier_import_total">Total</label><input class="form-control" type="number" step="0.01" id="supplier_import_total" value="<?= htmlspecialchars($previewData['total'], ENT_QUOTES, 'UTF-8') ?>" readonly><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'total') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-12"><label class="form-label fw-bold" for="supplier_import_description">Description</label><textarea class="form-control supplier-payment-import-source" id="supplier_import_description" name="data[description]" required><?= htmlspecialchars($previewData['description'], ENT_QUOTES, 'UTF-8') ?></textarea><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'description') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
                                <div class="col-12"><label class="form-label fw-bold" for="supplier_import_remark">Remark</label><textarea class="form-control" id="supplier_import_remark" name="data[remark]" rows="2"><?= htmlspecialchars($previewData['remark'], ENT_QUOTES, 'UTF-8') ?></textarea></div>
                            </div>
                            <div class="import-preview-actions mt-4"><button class="btn btn-lg btn-rounded btn-success px-4 import-preview-primary" type="submit" name="actionBtn" value="insertSupplierPayment"><i class="fa-solid fa-cloud-arrow-up"></i> Import Supplier Payment</button><a href="<?= $SITEURL ?>/import/supplier_payment_import.php" class="btn btn-lg btn-rounded btn-secondary import-preview-cancel">Cancel</a></div>
                        </form>
                    </div></div>
                <?php else : ?>
                    <div class="card mb-4 shadow-sm border-0"><div class="card-body"><h5 class="card-title mb-3">Step 1: Upload Supplier Payment PDF</h5><form id="supplier_payment_import_upload_form" method="post" enctype="multipart/form-data" autocomplete="off"><input type="hidden" name="client_ocr_text" id="supplier_payment_client_ocr_text" value=""><div class="row g-3 align-items-end"><div class="col-12 col-md-8"><label class="form-label fw-bold" for="import_file">Select Supplier Payment PDF</label><input class="form-control form-control-lg" type="file" name="import_file" id="import_file" accept=".pdf,application/pdf" required><?php foreach (supplierPaymentImportFieldErrors($importErrors, 'file') as $error) : ?><span class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?><div id="supplier_payment_import_ocr_status" class="mt-2 text-muted" aria-live="polite"></div></div><div class="col-12 col-md-4"><button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseSupplierPayment"><i class="fa-solid fa-magnifying-glass"></i> Scan &amp; Preview PDF</button></div></div></form></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="<?= $SITEURL ?>/finance/header/js/pdf.min.js"></script>
    <script src="<?= $SITEURL ?>/finance/header/js/tesseract.min.js"></script>
    <script>
        (function () {
            const merchantName = document.getElementById('supplier_import_merchant_name');
            const merchantHidden = document.getElementById('supplier_import_merchant_hidden');
            const codeInput = document.getElementById('supplier_import_code');
            const quantityInput = document.getElementById('supplier_import_quantity');
            const amountInput = document.getElementById('supplier_import_amount');
            const addSstInput = document.getElementById('supplier_import_add_sst');
            const totalInput = document.getElementById('supplier_import_total');
            function formatCode(value) { const clean = String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); const prefix = clean.replace(/\D/g, '').slice(0, 3); const suffix = clean.slice(prefix.length).slice(0, 5); return prefix && suffix ? prefix + '-' + suffix : prefix || suffix; }
            function applyMerchant(merchant) { if (merchant && codeInput) codeInput.value = formatCode(merchant.code); if (merchant && merchantHidden) merchantHidden.value = merchant.id || ''; }
            if (merchantName) merchantName.addEventListener('input', function () { if (merchantHidden) merchantHidden.value = ''; searchInput({ search: merchantName.value, searchType: 'name', elementID: 'supplier_import_merchant_name', hiddenElementID: 'supplier_import_merchant_hidden', dbTable: '<?= MERCHANT ?>', onSelect: applyMerchant }, '<?= $SITEURL ?>'); });
            function calculateTotal() { if (!totalInput) return; const quantity = parseFloat(quantityInput.value) || 0; const amount = parseFloat(amountInput.value) || 0; const addSst = parseFloat(addSstInput.value) || 0; totalInput.value = (quantity * amount + addSst).toFixed(2); }
            [quantityInput, amountInput, addSstInput].forEach(function (input) { if (input) { input.addEventListener('input', calculateTotal); input.addEventListener('change', calculateTotal); } });
            calculateTotal();

            const uploadForm = document.getElementById('supplier_payment_import_upload_form');
            const uploadFile = document.getElementById('import_file');
            const clientOcrText = document.getElementById('supplier_payment_client_ocr_text');
            const ocrStatus = document.getElementById('supplier_payment_import_ocr_status');
            if (uploadForm && uploadFile && clientOcrText) {
                let clientPdfSubmitReady = false;
                function setOcrStatus(message, isError) {
                    if (!ocrStatus) return;
                    ocrStatus.textContent = message || '';
                    ocrStatus.classList.toggle('text-danger', !!isError);
                    ocrStatus.classList.toggle('text-muted', !isError);
                }
                function readSupplierPaymentFileAsArrayBuffer(file) {
                    if (file && typeof file.arrayBuffer === 'function') return file.arrayBuffer();
                    return new Promise(function (resolve, reject) {
                        const reader = new FileReader();
                        reader.onload = function () { resolve(reader.result); };
                        reader.onerror = reject;
                        reader.readAsArrayBuffer(file);
                    });
                }
                function recognizeSupplierPaymentCanvas(canvas) {
                    if (typeof Tesseract === 'undefined') {
                        return Promise.resolve('');
                    }

                    const languages = ['eng+chi_sim', 'chi_sim', 'eng'];
                    let languageIndex = 0;
                    function recognizeNextLanguage() {
                        if (languageIndex >= languages.length) return Promise.resolve('');
                        const language = languages[languageIndex++];
                        return Tesseract.recognize(canvas, language).then(function (result) {
                            const text = result && result.data && result.data.text ? String(result.data.text).trim() : '';
                            return text !== '' ? text : recognizeNextLanguage();
                        }).catch(function () {
                            return recognizeNextLanguage();
                        });
                    }
                    return recognizeNextLanguage();
                }
                function createSupplierPaymentCropCanvas(sourceCanvas, left, top, right, bottom, scale) {
                    const sourceWidth = sourceCanvas.width;
                    const sourceHeight = sourceCanvas.height;
                    const sourceX = Math.floor(sourceWidth * left);
                    const sourceY = Math.floor(sourceHeight * top);
                    const sourceCropWidth = Math.max(1, Math.floor(sourceWidth * (right - left)));
                    const sourceCropHeight = Math.max(1, Math.floor(sourceHeight * (bottom - top)));
                    const cropCanvas = document.createElement('canvas');
                    cropCanvas.width = Math.max(1, Math.floor(sourceCropWidth * scale));
                    cropCanvas.height = Math.max(1, Math.floor(sourceCropHeight * scale));
                    const cropContext = cropCanvas.getContext('2d');
                    if (!cropContext) return null;
                    cropContext.fillStyle = '#ffffff';
                    cropContext.fillRect(0, 0, cropCanvas.width, cropCanvas.height);
                    cropContext.drawImage(sourceCanvas, sourceX, sourceY, sourceCropWidth, sourceCropHeight, 0, 0, cropCanvas.width, cropCanvas.height);
                    return cropCanvas;
                }
                function recognizeSupplierPaymentPageCanvas(canvas) {
                    return recognizeSupplierPaymentCanvas(canvas).then(function (fullText) {
                        const text = String(fullText || '').trim();
                        const hasQuantity = /\bQUANTITY\b\s*[:.]?\s*[0-9Il]/i.test(text)
                            || /transaction\s+fee\s+bill.*?\s+[0-9Il](?:\.\d+)?\s*M\s*Y\s*R/i.test(text);
                        if (/transaction\s+fee\s+bill/i.test(text) && hasQuantity && /\b(?:amount|total)\b/i.test(text) && /M\s*Y\s*R/i.test(text)) {
                            return text;
                        }

                        const lowerCropCanvas = createSupplierPaymentCropCanvas(canvas, 0, 0.38, 1, 0.98, 1);
                        const quantityCropCanvas = createSupplierPaymentCropCanvas(canvas, 0.39, 0.35, 0.56, 0.50, 2);
                        if (!lowerCropCanvas && !quantityCropCanvas) return text;
                        const lowerTextPromise = lowerCropCanvas ? recognizeSupplierPaymentCanvas(lowerCropCanvas) : Promise.resolve('');
                        const quantityTextPromise = quantityCropCanvas ? recognizeSupplierPaymentCanvas(quantityCropCanvas) : Promise.resolve('');
                        return Promise.all([lowerTextPromise, quantityTextPromise]).then(function (results) {
                            if (lowerCropCanvas) {
                                lowerCropCanvas.width = 0;
                                lowerCropCanvas.height = 0;
                            }
                            if (quantityCropCanvas) {
                                quantityCropCanvas.width = 0;
                                quantityCropCanvas.height = 0;
                            }
                            const lowerText = String(results[0] || '').trim();
                            const quantityText = String(results[1] || '').trim();
                            const quantityHint = quantityText !== '' ? 'QUANTITY: ' + quantityText : '';
                            return [text, lowerText, quantityHint].filter(Boolean).join('\n');
                        });
                    });
                }
                function extractSupplierPaymentPdfClientText(file) {
                    if (!file) return Promise.resolve('');
                    if (typeof pdfjsLib === 'undefined') {
                        setOcrStatus('PDF reader is unavailable. Continuing with server-side extraction.', true);
                        return Promise.resolve('');
                    }
                    pdfjsLib.GlobalWorkerOptions.workerSrc = <?= json_encode($SITEURL . '/finance/header/js/pdf.worker.min.js') ?>;
                    return readSupplierPaymentFileAsArrayBuffer(file).then(function (buffer) {
                        return pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                    }).then(function (pdfDoc) {
                        const pages = [];
                        let pageNumber = 1;
                        function readNextPage() {
                            if (pageNumber > pdfDoc.numPages) return Promise.resolve(pages.join('\n').trim());
                            const currentPage = pageNumber++;
                            setOcrStatus('Reading PDF page ' + currentPage + ' of ' + pdfDoc.numPages + '...', false);
                            return pdfDoc.getPage(currentPage).then(function (page) {
                                return page.getTextContent().catch(function () { return { items: [] }; }).then(function (content) {
                                    const textLayer = (content.items || []).map(function (item) { return String(item.str || '').trim(); }).filter(Boolean).join(' ');
                                    if (textLayer !== '') {
                                        pages.push(textLayer);
                                        return '';
                                    }
                                    if (typeof Tesseract === 'undefined') {
                                        throw new Error('OCR engine is unavailable.');
                                    }
                                    const viewport = page.getViewport({ scale: 4.0 });
                                    const canvas = document.createElement('canvas');
                                    canvas.width = Math.ceil(viewport.width);
                                    canvas.height = Math.ceil(viewport.height);
                                    const context = canvas.getContext('2d');
                                    if (!context) throw new Error('Canvas is unavailable.');
                                    return page.render({ canvasContext: context, viewport: viewport }).promise.then(function () {
                                        setOcrStatus('Running OCR on PDF page ' + currentPage + ' of ' + pdfDoc.numPages + '...', false);
                                        return recognizeSupplierPaymentPageCanvas(canvas);
                                    }).then(function (text) {
                                        pages.push(String(text || '').trim());
                                        canvas.width = 0;
                                        canvas.height = 0;
                                        return '';
                                    });
                                });
                            }).then(readNextPage);
                        }
                        return readNextPage().then(function (text) {
                            if (typeof pdfDoc.destroy === 'function') pdfDoc.destroy();
                            return text;
                        });
                    }).catch(function (error) {
                        setOcrStatus(error && error.message ? error.message : 'Unable to read the PDF.', true);
                        return '';
                    });
                }
                uploadFile.addEventListener('change', function () {
                    clientPdfSubmitReady = false;
                    clientOcrText.value = '';
                    setOcrStatus('', false);
                });
                uploadForm.addEventListener('submit', function (event) {
                    if (clientPdfSubmitReady || !uploadFile.files.length) return;
                    event.preventDefault();
                    const submitButton = uploadForm.querySelector('button[type="submit"]');
                    if (submitButton) submitButton.disabled = true;
                    setOcrStatus('Preparing PDF text extraction...', false);
                    extractSupplierPaymentPdfClientText(uploadFile.files[0]).then(function (text) {
                        clientOcrText.value = String(text || '').trim();
                        if (clientOcrText.value === '') {
                            setOcrStatus('No text was returned by the PDF OCR. You may continue and enter the fields manually.', true);
                        } else {
                            setOcrStatus('PDF scanned. Opening the extracted preview...', false);
                        }
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'actionBtn';
                        actionInput.value = 'parseSupplierPayment';
                        uploadForm.appendChild(actionInput);
                        clientPdfSubmitReady = true;
                        HTMLFormElement.prototype.submit.call(uploadForm);
                    });
                });
            }
        })();
        document.title = <?= json_encode($pageTitle) ?>;
        checkCurrentPage(<?= json_encode($pageTitle) ?>, '');
        dropdownMenuDispFix();
        setButtonColor();
        preloader(300, '');
    </script>
</body>
</html>
