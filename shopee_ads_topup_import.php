<?php
$currentPagePin = 0;
if (!defined('IMPORT_FORCE_MODULE')) {
    define('IMPORT_FORCE_MODULE', 'shopee_ads_topup');
}

$parentPagePinGroupId = 77;
$parentPageTitle = "Shopee Ads Top Up Transaction";
$pageTitle = '';

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$resolvedParentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($resolvedParentPageTitle !== '') {
    $parentPageTitle = $resolvedParentPageTitle;
}
$breadcrumbTitle = $parentPageTitle . ' Import';
$pageTitle = $breadcrumbTitle;
$pageHeading = $parentPageTitle . ' Import';

$pinAccess = checkPinByGroupId($connect, $parentPagePinGroupId);
if (!is_array($pinAccess) || count($pinAccess) === 0 || !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
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

$module = 'shopee_ads_topup';
$redirect_page = $SITEURL . '/common_import.php';
$shopeeRedirectPage = $SITEURL . '/shopee/shopee_ads_topup_trans_table.php';

$action = post('actionBtn');
$allowedActions = ['parseShopeeAdsTopup', 'insertShopeeAdsTopup'];
if ($action !== '' && !in_array($action, $allowedActions, true)) {
    $action = '';
}
if ($action !== '' && !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}
$importErrors = [];
$importWarnings = [];
$previewData = [];


$shopeeAccounts = getImportOptionList(SHOPEE_ACC, 'name', $finance_connect);
$currencyUnits = getImportOptionList(CUR_UNIT, 'unit', $connect);
$paymentMethods = getImportOptionList(FIN_PAY_METH, 'name', $finance_connect);
if ($action === 'parseShopeeAdsTopup') {
    $module = 'shopee_ads_topup';

    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        $importErrors[] = 'Please choose a Shopee HTML, PDF, or ZIP file.';
    } else {
        $uploadedName = isset($_FILES['import_file']['name']) ? (string) $_FILES['import_file']['name'] : '';
        $extension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));

        if ($extension === 'html' || $extension === 'htm') {
            $html = file_get_contents($_FILES['import_file']['tmp_name']);

            if ($html === false || trim($html) === '') {
                $importErrors[] = 'The uploaded HTML file could not be read.';
            } else {
                $parseResult = parseShopeeAdsTopupHtml($html, $shopeeAccounts, $currencyUnits, $paymentMethods);
                $previewData = $parseResult['data'];
                $previewData['source_file_name'] = sanitizeImportFilename($uploadedName);
                $previewData['source_attachment'] = saveShopeeImportAttachmentBinary($html, $uploadedName, basename(__FILE__, '.php'));
                $importWarnings = $parseResult['warnings'];

                if (!empty($parseResult['errors'])) {
                    $importErrors = array_merge($importErrors, $parseResult['errors']);
                }
            }
        } else {
            $sourceFiles = collectShopeeImportSourceFiles($_FILES['import_file'], $importErrors, $importWarnings);
            if (empty($importErrors) && !empty($sourceFiles)) {
                if (count($sourceFiles) > 1) {
                    $importWarnings[] = 'Multiple PDFs detected. Preview currently loads the first matched file only.';
                }

                $source = $sourceFiles[0];
                $parseResult = parseShopeeAdsTopupPdf($source['pdf_content'], $source['original_name'], $shopeeAccounts, $currencyUnits, $paymentMethods);
                $previewData = $parseResult['data'];
                $previewData['source_file_name'] = isset($source['original_name']) ? (string) $source['original_name'] : '';
                $previewData['source_attachment'] = isset($source['attachment_path']) ? (string) $source['attachment_path'] : '';
                $importWarnings = array_merge($importWarnings, $parseResult['warnings']);

                if (!empty($parseResult['errors'])) {
                    $importErrors = array_merge($importErrors, $parseResult['errors']);
                }
            }
        }
    }
} else if ($action === 'insertShopeeAdsTopup') {
    $module = 'shopee_ads_topup';
    $previewData = getShopeeAdsPreviewFromPost();
    $importWarnings = array_filter(post('importWarnings') ? explode("\n", post('importWarnings')) : []);

    validateShopeeAdsPreview($previewData, $importErrors, $shopeeAccounts, $currencyUnits, $paymentMethods, $finance_connect, $connect);

    if (empty($importErrors)) {
        $paymentDate = formatImportDatetime($previewData['payment_date']);
        $remark = mysqli_real_escape_string($finance_connect, $previewData['remark']);
        $orderId = mysqli_real_escape_string($finance_connect, $previewData['order_id']);
        $attachmentPath = mysqli_real_escape_string($finance_connect, isset($previewData['source_attachment']) ? (string) $previewData['source_attachment'] : '');
        $query = "INSERT INTO " . SHOPEE_ADS_TOPUP . " (shopee_acc, orderID, payment_date, currency, topup_amt, subtotal, gst, pay_meth, attachment, remark, create_by, create_date, create_time) VALUES ('" . mysqli_real_escape_string($finance_connect, $previewData['shopee_acc']) . "', '$orderId', '$paymentDate', '" . mysqli_real_escape_string($connect, $previewData['currency']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['topup_amt']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['subtotal']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['gst']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['pay_meth']) . "', '" . $attachmentPath . "', '$remark', '" . USER_ID . "', curdate(), curtime())";

        $returnData = mysqli_query($finance_connect, $query);

        if ($returnData) {
            $dataID = mysqli_insert_id($finance_connect);
            $newvalarr = [
                getImportLabelById($shopeeAccounts, $previewData['shopee_acc']),
                $previewData['order_id'],
                $paymentDate,
                getImportLabelById($currencyUnits, $previewData['currency']),
                $previewData['topup_amt'],
                $previewData['subtotal'],
                $previewData['gst'],
                getImportLabelById($paymentMethods, $previewData['pay_meth']),
                $previewData['remark'] === '' ? 'Empty Value' : $previewData['remark'],
            ];

            $log = [
                'log_act' => 'Import',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $query,
                'query_table' => SHOPEE_ADS_TOPUP,
                'newval' => implodeWithComma($newvalarr),
                'act_msg' => USER_NAME . " imported the data [ <b> ID = " . $dataID . " </b> ] from <b><i>" . SHOPEE_ADS_TOPUP . " Table</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo '<script>alert("Shopee Ads top up transaction imported successfully.");window.location.href="' . $shopeeRedirectPage . '";</script>';
            exit;
        }

        $importErrors[] = 'Unable to insert the import record. Please try again.';
    }
}
function getImportOptionList($tableName, $labelField, $dbConnect)
{
    $list = [];
    $tableName = mysqli_real_escape_string($dbConnect, $tableName);
    $labelField = mysqli_real_escape_string($dbConnect, $labelField);
    $query = "SELECT id, `$labelField` AS option_label FROM `$tableName` WHERE status = 'A' ORDER BY `$labelField` ASC";
    $result = mysqli_query($dbConnect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $list[$row['id']] = $row['option_label'];
        }
    }

    return $list;
}

function sanitizeImportFilename($filename)
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) $filename));
    return $filename !== '' ? $filename : ('import_' . uniqid() . '.pdf');
}

function saveShopeeImportAttachmentBinary($binaryContent, $originalName, $pageName)
{
    $binaryContent = (string) $binaryContent;
    $originalName = trim((string) $originalName);
    if ($binaryContent === '' || $originalName === '') {
        return '';
    }

    $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    if ($safeBase === '') {
        $safeBase = 'import_file';
    }

    $safePage = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $pageName);
    if ($safePage === '') {
        $safePage = 'import_page';
    }

    $relDir = 'attachment/' . substr((string) comYMD, 0, 4) . '/' . substr((string) comYMD, 4, 2) . '/' . $safePage . '/';
    $absDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . ltrim($relDir, '/\\');
    if (!is_dir($absDir)) {
        @mkdir($absDir, 0777, true);
    }
    if (!is_dir($absDir)) {
        return '';
    }

    $newFile = $safeBase . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . ($ext !== '' ? '.' . $ext : '');
    $absPath = $absDir . $newFile;
    if (@file_put_contents($absPath, $binaryContent) !== false) {
        return $relDir . $newFile;
    }

    return '';
}

function collectShopeeImportSourceFiles($fileInfo, &$errors, &$warnings)
{
    $sourceFiles = array();
    $originalName = isset($fileInfo['name']) ? (string) $fileInfo['name'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === 'pdf') {
        $pdfContent = @file_get_contents($fileInfo['tmp_name']);
        if ($pdfContent === false || $pdfContent === '') {
            $errors[] = 'Unable to read the uploaded PDF file.';
            return array();
        }

        $sourceFiles[] = array(
            'pdf_content' => $pdfContent,
            'original_name' => sanitizeImportFilename($originalName),
            'attachment_path' => saveShopeeImportAttachmentBinary($pdfContent, $originalName, basename(__FILE__, '.php')),
        );
        return $sourceFiles;
    }

    if ($extension !== 'zip') {
        $errors[] = 'Only HTML, PDF, or ZIP files are supported.';
        return array();
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($fileInfo['tmp_name']) !== true) {
            $errors[] = 'The uploaded ZIP file could not be opened.';
            return array();
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (substr((string) $entryName, -1) === '/') {
                continue;
            }

            if (strtolower(pathinfo((string) $entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $pdfContent = $zip->getFromIndex($index);
            if ($pdfContent === false || $pdfContent === '') {
                $warnings[] = 'Unable to read PDF entry from ZIP: ' . $entryName;
                continue;
            }

            $sourceFiles[] = array(
                'pdf_content' => $pdfContent,
                'original_name' => sanitizeImportFilename($entryName),
                'attachment_path' => saveShopeeImportAttachmentBinary($pdfContent, (string) basename($entryName), basename(__FILE__, '.php')),
            );
        }

        $zip->close();
    } else if (class_exists('PharData')) {
        try {
            $zipArchive = new PharData($fileInfo['tmp_name']);

            foreach (new RecursiveIteratorIterator($zipArchive) as $entry) {
                if (!($entry instanceof SplFileInfo) || !$entry->isFile()) {
                    continue;
                }

                $entryName = $entry->getFilename();
                if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                    continue;
                }

                $pdfContent = @file_get_contents($entry->getPathname());
                if ($pdfContent === false || $pdfContent === '') {
                    $warnings[] = 'Unable to read PDF entry from ZIP: ' . $entryName;
                    continue;
                }

                $sourceFiles[] = array(
                    'pdf_content' => $pdfContent,
                    'original_name' => sanitizeImportFilename($entryName),
                    'attachment_path' => saveShopeeImportAttachmentBinary($pdfContent, (string) basename($entryName), basename(__FILE__, '.php')),
                );
            }
        } catch (Exception $exception) {
            $errors[] = 'The uploaded ZIP file could not be opened.';
            return array();
        }
    } else {
        $errors[] = 'ZIP import requires PHP ZipArchive support in the current web runtime.';
        return array();
    }

    if (empty($sourceFiles)) {
        $errors[] = 'No PDF file was found inside the uploaded ZIP archive.';
    }

    return $sourceFiles;
}

function decodePdfStream($stream)
{
    $decoded = @gzuncompress($stream);
    if ($decoded !== false) {
        return $decoded;
    }

    $decoded = @gzinflate($stream);
    if ($decoded !== false) {
        return $decoded;
    }

    if (strlen((string) $stream) > 6) {
        $decoded = @gzinflate(substr((string) $stream, 2));
        if ($decoded !== false) {
            return $decoded;
        }
    }

    return false;
}

function cleanPdfTextOperand($text)
{
    $text = str_replace("\x00", '', (string) $text);
    $text = strtr($text, array(
        '\\n' => ' ',
        '\\r' => ' ',
        '\\t' => ' ',
        '\\(' => '(',
        '\\)' => ')',
        '\\\\' => '\\',
    ));

    return normalizeImportText(preg_replace('/[^[:print:] ]/', ' ', $text));
}

function extractTextFromPdfContent($content)
{
    if ((string) $content === '') {
        return '';
    }

    preg_match_all('/stream\r?\n(.*?)endstream/s', (string) $content, $streamMatches);
    $lines = array();

    foreach ($streamMatches[1] as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false) {
            continue;
        }

        if (preg_match_all('/\(([^\)]{1,500})\)\s*Tj/s', $decoded, $textMatches)) {
            foreach ($textMatches[1] as $match) {
                $cleanLine = cleanPdfTextOperand($match);
                if ($cleanLine !== '') {
                    $lines[] = $cleanLine;
                }
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
            foreach ($arrayMatches[1] as $chunk) {
                preg_match_all('/\(([^\)]*)\)/', $chunk, $innerMatches);
                $cleanLine = cleanPdfTextOperand(implode('', $innerMatches[1]));
                if ($cleanLine !== '') {
                    $lines[] = $cleanLine;
                }
            }
        }
    }

    return implode("\n", $lines);
}

function getPdfTextLines($text)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);
    $normalizedLines = array();

    foreach ($lines as $line) {
        $line = normalizeImportText($line);
        if ($line !== '') {
            $normalizedLines[] = $line;
        }
    }

    return $normalizedLines;
}

function extractPdfFieldByLabels($text, $labels)
{
    $lines = getPdfTextLines($text);

    foreach ($lines as $index => $line) {
        $lineLookup = normalizeImportLookup($line);
        if ($lineLookup === '') {
            continue;
        }

        foreach ($labels as $label) {
            $labelLookup = normalizeImportLookup($label);
            if ($labelLookup === '' || strpos($lineLookup, $labelLookup) === false) {
                continue;
            }

            if (preg_match('/' . preg_quote($label, '/') . '\s*:?\s*(.+)/i', $line, $matches)) {
                $value = normalizeImportText($matches[1]);
                if ($value !== '' && normalizeImportLookup($value) !== $labelLookup) {
                    return $value;
                }
            }

            if (isset($lines[$index + 1])) {
                return normalizeImportText($lines[$index + 1]);
            }
        }
    }

    return '';
}

function extractPdfMoneyByLabels($text, $labels)
{
    $lines = getPdfTextLines($text);
    foreach ($lines as $line) {
        foreach ($labels as $label) {
            if (stripos($line, $label) === false) {
                continue;
            }

            $money = extractMoneyDetails($line);
            if ($money['amount'] !== '') {
                return $money;
            }
        }
    }

    return array('currency' => '', 'amount' => '');
}

function parseShopeeAdsTopupPdf($pdfContent, $fileName, $shopeeAccounts, $currencyUnits, $paymentMethods)
{
    $data = array(
        'source_shop_name' => '',
        'source_currency' => '',
        'source_payment_method' => '',
        'shopee_acc' => '',
        'order_id' => '',
        'payment_date' => '',
        'currency' => '',
        'topup_amt' => '',
        'subtotal' => '',
        'gst' => '',
        'pay_meth' => '',
        'remark' => '',
    );
    $errors = array();
    $warnings = array();

    $text = extractTextFromPdfContent($pdfContent);
    if ($text === '') {
        return array(
            'data' => $data,
            'errors' => array('Unable to extract text from PDF receipt: ' . $fileName),
            'warnings' => $warnings,
        );
    }

    $data['source_shop_name'] = extractPdfFieldByLabels($text, array('Shop Name', 'Shopee Account', 'Shop'));
    $data['order_id'] = extractPdfFieldByLabels($text, array('Order ID', 'Order No', 'Order Number'));
    $data['payment_date'] = parseShopeeDatetime(extractPdfFieldByLabels($text, array('Completed', 'Payment Date', 'DateTime', 'Invoice Date')));
    $data['source_payment_method'] = extractPdfFieldByLabels($text, array('Payment Method'));

    $paymentTotal = extractPdfMoneyByLabels($text, array('Payment Total', 'Top-up Amount', 'Top Up Amount', 'Amount'));
    $subtotal = extractPdfMoneyByLabels($text, array('Subtotal'));
    $taxValue = extractPdfMoneyByLabels($text, array('GST', 'SST', 'Tax'));

    $data['source_currency'] = $paymentTotal['currency'] !== '' ? $paymentTotal['currency'] : $subtotal['currency'];
    $data['topup_amt'] = $paymentTotal['amount'];
    $data['subtotal'] = $subtotal['amount'];
    $data['gst'] = $taxValue['amount'];
    $data['remark'] = 'Imported from Shopee text-based PDF';

    if ($data['order_id'] !== '') {
        $data['remark'] .= ' (' . $data['order_id'] . ')';
    }

    $currencyFallbacks = array();
    if ($data['source_currency'] === 'RM') {
        $currencyFallbacks = array('MYR');
    } else if ($data['source_currency'] === 'S$') {
        $currencyFallbacks = array('SGD');
    }

    $data['shopee_acc'] = resolveImportOptionId($data['source_shop_name'], $shopeeAccounts);
    $data['currency'] = resolveImportOptionId($data['source_currency'], $currencyUnits, $currencyFallbacks);
    $data['pay_meth'] = resolveImportOptionId($data['source_payment_method'], $paymentMethods);

    if ($data['source_shop_name'] === '') {
        $errors[] = 'Shop name could not be detected from the PDF file.';
    }

    if ($data['order_id'] === '') {
        $errors[] = 'Order ID could not be detected from the PDF file.';
    }

    if ($data['payment_date'] === '') {
        $errors[] = 'Payment date could not be detected from the PDF file.';
    }

    if ($data['topup_amt'] === '') {
        $errors[] = 'Payment total could not be detected from the PDF file.';
    }

    if ($data['subtotal'] === '') {
        $errors[] = 'Subtotal could not be detected from the PDF file.';
    }

    if ($data['gst'] === '') {
        $warnings[] = 'GST amount could not be detected from the PDF file. Please verify before insert.';
    }

    if ($data['shopee_acc'] === '') {
        $warnings[] = 'Shopee account was not matched automatically. Please choose the correct account before inserting.';
    }

    if ($data['currency'] === '') {
        $warnings[] = 'Currency unit was not matched automatically. Please choose the correct currency before inserting.';
    }

    if ($data['pay_meth'] === '') {
        $warnings[] = 'Payment method was not matched automatically. Please choose the correct payment method before inserting.';
    }

    return array(
        'data' => $data,
        'errors' => $errors,
        'warnings' => $warnings,
    );
}

function normalizeImportText($text)
{
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

function normalizeImportLookup($text)
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', normalizeImportText($text)));
}

function getNodeText($xpath, $query, $contextNode = null)
{
    $nodes = $xpath->query($query, $contextNode);
    if ($nodes && $nodes->length > 0) {
        return normalizeImportText($nodes->item(0)->textContent);
    }

    return '';
}

function extractValueAfterColon($text)
{
    $parts = explode(':', $text, 2);
    return isset($parts[1]) ? normalizeImportText($parts[1]) : normalizeImportText($text);
}

function extractMoneyDetails($text)
{
    $normalized = normalizeImportText($text);

    if (preg_match('/([A-Z]{1,5}|RM|SGD|USD|MYR|EUR|GBP)\s*([0-9][0-9,]*\.?[0-9]*)/i', $normalized, $matches)) {
        return [
            'currency' => strtoupper($matches[1]),
            'amount' => number_format((float) str_replace(',', '', $matches[2]), 2, '.', ''),
        ];
    }

    return [
        'currency' => '',
        'amount' => '',
    ];
}

function parseShopeeDatetime($value)
{
    $value = normalizeImportText($value);
    $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
}

function formatImportDatetime($value)
{
    $parsed = parseShopeeDatetime($value);
    return $parsed !== '' ? $parsed : date('Y-m-d H:i:s');
}

function formatDatetimeLocalValue($value)
{
    $parsed = parseShopeeDatetime($value);
    return $parsed !== '' ? date('Y-m-d\TH:i', strtotime($parsed)) : date('Y-m-d\TH:i');
}

function extractValueFromTableLabel($xpath, $labels)
{
    foreach ($xpath->query('//tr') as $row) {
        $cells = $xpath->query('./th|./td', $row);
        if ($cells->length < 2) {
            continue;
        }

        $labelText = normalizeImportText($cells->item(0)->textContent);
        foreach ($labels as $label) {
            if (stripos($labelText, $label) !== false) {
                return normalizeImportText($cells->item(1)->textContent);
            }
        }
    }

    return '';
}

function resolveImportOptionId($rawValue, $options, $fallbacks = [])
{
    $candidates = array_merge([(string) $rawValue], $fallbacks);

    foreach ($candidates as $candidate) {
        $normalizedCandidate = normalizeImportLookup($candidate);
        if ($normalizedCandidate === '') {
            continue;
        }

        foreach ($options as $id => $label) {
            $normalizedLabel = normalizeImportLookup($label);
            if ($normalizedLabel === $normalizedCandidate) {
                return $id;
            }
        }

        foreach ($options as $id => $label) {
            $normalizedLabel = normalizeImportLookup($label);
            if ($normalizedLabel !== '' && (strpos($normalizedLabel, $normalizedCandidate) !== false || strpos($normalizedCandidate, $normalizedLabel) !== false)) {
                return $id;
            }
        }
    }

    return '';
}

function getImportLabelById($options, $id)
{
    return isset($options[$id]) ? $options[$id] : '';
}

function parseShopeeAdsTopupHtml($html, $shopeeAccounts, $currencyUnits, $paymentMethods)
{
    $data = [
        'source_file_name' => '',
        'source_attachment' => '',
        'source_shop_name' => '',
        'source_currency' => '',
        'source_payment_method' => '',
        'shopee_acc' => '',
        'order_id' => '',
        'payment_date' => '',
        'currency' => '',
        'topup_amt' => '',
        'subtotal' => '',
        'gst' => '',
        'pay_meth' => '',
        'remark' => '',
    ];
    $errors = [];
    $warnings = [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    if (!$loaded) {
        return [
            'data' => $data,
            'errors' => ['The uploaded file is not a valid HTML document.'],
            'warnings' => $warnings,
        ];
    }

    $xpath = new DOMXPath($dom);

    $shopHeader = getNodeText($xpath, "//div[contains(@class,'order-detail-info')]//div[contains(@class,'header')]");
    $data['source_shop_name'] = extractValueAfterColon($shopHeader);
    $data['order_id'] = extractValueAfterColon(getNodeText($xpath, "//*[contains(@class,'purchase-info__order-sn') or contains(text(),'Order ID:')][1]"));

    $completedTime = getNodeText($xpath, "//div[contains(@class,'timeline-item')][.//div[contains(@class,'title') and normalize-space()='Completed']]//div[contains(@class,'time')]");
    if ($completedTime === '') {
        $completedTime = getNodeText($xpath, "//div[contains(@class,'service-mess')]//span[contains(@class,'time')]");
    }
    $data['payment_date'] = parseShopeeDatetime($completedTime);

    $paymentTotal = extractMoneyDetails(extractValueFromTableLabel($xpath, ['Payment Total']));
    $subtotal = extractMoneyDetails(extractValueFromTableLabel($xpath, ['Subtotal']));
    $taxValue = extractMoneyDetails(extractValueFromTableLabel($xpath, ['SST', 'GST', 'Tax']));
    $paymentMethod = extractValueFromTableLabel($xpath, ['Payment Method']);

    if ($paymentTotal['amount'] === '') {
        $paymentTotal = extractMoneyDetails(getNodeText($xpath, "//*[contains(text(),'Payment Total')][1]"));
    }

    $data['source_currency'] = $paymentTotal['currency'] !== '' ? $paymentTotal['currency'] : $subtotal['currency'];
    $data['source_payment_method'] = $paymentMethod;
    $data['topup_amt'] = $paymentTotal['amount'];
    $data['subtotal'] = $subtotal['amount'];
    $data['gst'] = $taxValue['amount'];
    $data['remark'] = 'Imported from Shopee Seller Centre HTML';

    if ($data['order_id'] !== '') {
        $data['remark'] .= ' (' . $data['order_id'] . ')';
    }

    $currencyFallbacks = [];
    if ($data['source_currency'] === 'RM') {
        $currencyFallbacks = ['MYR'];
    } else if ($data['source_currency'] === 'S$') {
        $currencyFallbacks = ['SGD'];
    }

    $data['shopee_acc'] = resolveImportOptionId($data['source_shop_name'], $shopeeAccounts);
    $data['currency'] = resolveImportOptionId($data['source_currency'], $currencyUnits, $currencyFallbacks);
    $data['pay_meth'] = resolveImportOptionId($data['source_payment_method'], $paymentMethods);

    if ($data['source_shop_name'] === '') {
        $errors[] = 'Shop name could not be detected from the HTML file.';
    }

    if ($data['order_id'] === '') {
        $errors[] = 'Order ID could not be detected from the HTML file.';
    }

    if ($data['payment_date'] === '') {
        $errors[] = 'Payment date could not be detected from the HTML file.';
    }

    if ($data['topup_amt'] === '') {
        $errors[] = 'Payment total could not be detected from the HTML file.';
    }

    if ($data['subtotal'] === '') {
        $errors[] = 'Subtotal could not be detected from the HTML file.';
    }

    if ($data['gst'] === '') {
        $errors[] = 'Tax amount could not be detected from the HTML file.';
    }

    if ($data['shopee_acc'] === '') {
        $warnings[] = 'Shopee account was not matched automatically. Please choose the correct account before inserting.';
    }

    if ($data['currency'] === '') {
        $warnings[] = 'Currency unit was not matched automatically. Please choose the correct currency before inserting.';
    }

    if ($data['pay_meth'] === '') {
        $warnings[] = 'Payment method was not matched automatically. Please choose the correct payment method before inserting.';
    }

    return [
        'data' => $data,
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

function getShopeeAdsPreviewFromPost()
{
    return [
        'source_file_name' => postSpaceFilter('source_file_name'),
        'source_attachment' => postSpaceFilter('source_attachment'),
        'source_shop_name' => postSpaceFilter('source_shop_name'),
        'source_currency' => postSpaceFilter('source_currency'),
        'source_payment_method' => postSpaceFilter('source_payment_method'),
        'shopee_acc' => postSpaceFilter('shopee_acc'),
        'order_id' => postSpaceFilter('order_id'),
        'payment_date' => postSpaceFilter('payment_date'),
        'currency' => postSpaceFilter('currency'),
        'topup_amt' => postSpaceFilter('topup_amt'),
        'subtotal' => postSpaceFilter('subtotal'),
        'gst' => postSpaceFilter('gst'),
        'pay_meth' => postSpaceFilter('pay_meth'),
        'remark' => postSpaceFilter('remark'),
    ];
}

function validateShopeeAdsPreview($previewData, &$importErrors, $shopeeAccounts, $currencyUnits, $paymentMethods, $finance_connect, $connect)
{
    if ($previewData['shopee_acc'] === '' || !isset($shopeeAccounts[$previewData['shopee_acc']])) {
        $importErrors[] = 'Shopee Account is required.';
    }

    if ($previewData['order_id'] === '') {
        $importErrors[] = 'Order ID is required.';
    } else if (isDuplicateRecord('orderID', $previewData['order_id'], SHOPEE_ADS_TOPUP, $finance_connect, '')) {
        $importErrors[] = 'Duplicate Order ID found in Shopee Ads Top Up Transaction.';
    }

    if ($previewData['payment_date'] === '' || parseShopeeDatetime($previewData['payment_date']) === '') {
        $importErrors[] = 'Payment date is invalid.';
    }

    if ($previewData['currency'] === '' || !isset($currencyUnits[$previewData['currency']])) {
        $importErrors[] = 'Currency is required.';
    }

    if ($previewData['topup_amt'] === '' || !is_numeric($previewData['topup_amt'])) {
        $importErrors[] = 'Top-up Amount must be a valid number.';
    }

    if ($previewData['subtotal'] === '' || !is_numeric($previewData['subtotal'])) {
        $importErrors[] = 'Subtotal must be a valid number.';
    }

    if ($previewData['gst'] === '' || !is_numeric($previewData['gst'])) {
        $importErrors[] = 'GST must be a valid number.';
    }

    if ($previewData['pay_meth'] === '' || !isset($paymentMethods[$previewData['pay_meth']])) {
        $importErrors[] = 'Payment Method is required.';
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>
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
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shopeeRedirectPage ?>">Back To Shopee Ads Page</a>
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>">Back To Shortcuts</a>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($importErrors)) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($importErrors as $error) { ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php if (!empty($importWarnings)) { ?>
                        <div class="alert alert-warning" role="alert">
                            <?php foreach ($importWarnings as $warning) { ?>
                                <div><?= htmlspecialchars($warning) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 1: Upload Shopee HTML/PDF/ZIP</h5>
                            <form method="post" enctype="multipart/form-data">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Shopee Seller Centre HTML, PDF, or ZIP File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".html,.htm,.pdf,.zip" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseShopeeAdsTopup">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($previewData) && !empty($previewData['order_id'])) { ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Step 2: Preview And Edit Before Insert</h5>
                                <form method="post">
                                    <input type="hidden" name="source_shop_name" value="<?= htmlspecialchars($previewData['source_shop_name']) ?>">
                                    <input type="hidden" name="source_currency" value="<?= htmlspecialchars($previewData['source_currency']) ?>">
                                    <input type="hidden" name="source_payment_method" value="<?= htmlspecialchars($previewData['source_payment_method']) ?>">
                                    <input type="hidden" name="source_file_name" value="<?= htmlspecialchars(isset($previewData['source_file_name']) ? $previewData['source_file_name'] : '') ?>">
                                    <input type="hidden" name="source_attachment" value="<?= htmlspecialchars(isset($previewData['source_attachment']) ? $previewData['source_attachment'] : '') ?>">
                                    <input type="hidden" name="importWarnings" value="<?= htmlspecialchars(implode("\n", $importWarnings)) ?>">

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="order_id">Order ID<span class="requireRed">*</span></label>
                                            <input class="form-control" type="text" id="order_id" name="order_id" value="<?= htmlspecialchars($previewData['order_id']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="payment_date">Payment Date<span class="requireRed">*</span></label>
                                            <input class="form-control" type="datetime-local" id="payment_date" name="payment_date" value="<?= htmlspecialchars(formatDatetimeLocalValue($previewData['payment_date'])) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="shopee_acc">Shopee Account<span class="requireRed">*</span></label>
                                            <select class="form-select" id="shopee_acc" name="shopee_acc" required>
                                                <option value="">Select Account</option>
                                                <?php foreach ($shopeeAccounts as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['shopee_acc'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="currency">Currency<span class="requireRed">*</span></label>
                                            <select class="form-select" id="currency" name="currency" required>
                                                <option value="">Select Currency</option>
                                                <?php foreach ($currencyUnits as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['currency'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="pay_meth">Payment Method<span class="requireRed">*</span></label>
                                            <select class="form-select" id="pay_meth" name="pay_meth" required>
                                                <option value="">Select Payment Method</option>
                                                <?php foreach ($paymentMethods as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['pay_meth'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="topup_amt">Top-up Amount<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="topup_amt" name="topup_amt" value="<?= htmlspecialchars($previewData['topup_amt']) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="subtotal">Subtotal<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="subtotal" name="subtotal" value="<?= htmlspecialchars($previewData['subtotal']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="gst">GST / Tax<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="gst" name="gst" value="<?= htmlspecialchars($previewData['gst']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="remark">Remark</label>
                                            <textarea class="form-control" id="remark" name="remark" rows="2"><?= htmlspecialchars($previewData['remark']) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                        <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertShopeeAdsTopup">
                                            <i class="fa-solid fa-database"></i> Insert
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php } ?>
            </div>
        </div>
    </div>
</body>

<script>
    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    preloader(0, '');
    setButtonColor();
</script>

</html>



