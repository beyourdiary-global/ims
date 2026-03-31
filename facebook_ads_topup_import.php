<?php
if (!defined('IMPORT_FORCE_MODULE')) {
    define('IMPORT_FORCE_MODULE', 'fb_ads_topup');
}

$pageTitle = "Facebook Ads Topup Import";
$parentPagePinGroupId = 50;
$parentPageTitle = "Facebook Ads Top Up Transaction";

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

$resolvedParentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($resolvedParentPageTitle !== '') {
    $parentPageTitle = $resolvedParentPageTitle;
}
$breadcrumbTitle = $parentPageTitle . ' Import';

$pinAccess = checkPinByGroupId($connect, $parentPagePinGroupId);
if (!is_array($pinAccess) || count($pinAccess) === 0 || !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

$module = 'fb_ads_topup';
$redirect_page = $SITEURL . '/common_import.php';
$facebookRedirectPage = $SITEURL . '/finance/fb_ads_topup_trans_table.php';

$action = post('actionBtn');
$allowedActions = ['parseFacebookAdsTopup', 'insertFacebookAdsTopup'];
if ($action !== '' && !in_array($action, $allowedActions, true)) {
    $action = '';
}
if ($action !== '' && !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}
$importErrors = [];
$importWarnings = [];
$facebookPreviewRecords = [];
$facebookImportSummary = [
    'processed_files' => 0,
    'preview_records' => 0,
    'skipped_files' => 0,
];


$metaAccounts = getMetaAdsAccountOptions($finance_connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
if ($action === 'parseFacebookAdsTopup') {
    $module = 'fb_ads_topup';

    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        $importErrors[] = 'Please choose a Facebook Ads PDF receipt or ZIP file.';
    } else {
        $sourceFiles = collectFacebookImportSourceFiles($_FILES['import_file'], $importErrors, $importWarnings);

        if (!empty($sourceFiles)) {
            $parseResult = parseFacebookImportFiles($sourceFiles, $metaAccounts);
            $facebookPreviewRecords = $parseResult['records'];
            $facebookImportSummary = $parseResult['summary'];
            $importWarnings = array_merge($importWarnings, $parseResult['warnings']);
            $importErrors = array_merge($importErrors, $parseResult['errors']);

            if (empty($facebookPreviewRecords) && empty($importErrors)) {
                $importErrors[] = 'No paid Facebook Ads receipt was ready for preview.';
            }
        }
    }
} else if ($action === 'insertFacebookAdsTopup') {
    $module = 'fb_ads_topup';
    $facebookPreviewRecords = getFacebookPreviewRecordsFromPost();
    $importWarnings = array_filter(post('importWarnings') ? explode("\n", post('importWarnings')) : []);
    $facebookImportSummary = getFacebookImportSummaryFromPost();

    validateFacebookPreviewRecords($facebookPreviewRecords, $importErrors, $metaAccounts, $userOptions, $finance_connect);

    if (empty($facebookImportSummary['preview_records'])) {
        $facebookImportSummary['preview_records'] = count($facebookPreviewRecords);
    }

    if (empty($importErrors)) {
        $insertedCount = 0;
        mysqli_begin_transaction($finance_connect);

        try {
            foreach ($facebookPreviewRecords as $index => $record) {
                $transactionId = mysqli_real_escape_string($finance_connect, $record['transaction_id']);
                $paymentDate = formatImportDateOnly($record['payment_date']);
                $remark = mysqli_real_escape_string($finance_connect, $record['remark']);
                $attachmentPath = mysqli_real_escape_string($finance_connect, isset($record['source_attachment']) ? (string) $record['source_attachment'] : '');
                $query = "INSERT INTO " . FB_ADS_TOPUP . " (meta_acc, transactionID, payment_date, pic, topup_amt, attachment, remark, create_by, create_date, create_time) VALUES ('" . mysqli_real_escape_string($finance_connect, $record['meta_acc']) . "', '$transactionId', '$paymentDate', '" . mysqli_real_escape_string($finance_connect, $record['pic']) . "', '" . mysqli_real_escape_string($finance_connect, $record['topup_amt']) . "', '" . $attachmentPath . "', '$remark', '" . USER_ID . "', curdate(), curtime())";
                $returnData = mysqli_query($finance_connect, $query);

                if (!$returnData) {
                    throw new Exception('Unable to insert Facebook Ads import record #' . ($index + 1) . '.');
                }

                $dataID = mysqli_insert_id($finance_connect);
                $newvalarr = [
                    getMetaAdsAccountLabelById($metaAccounts, $record['meta_acc']),
                    $record['transaction_id'],
                    $paymentDate,
                    getImportLabelById($userOptions, $record['pic']),
                    $record['topup_amt'],
                    isset($record['source_attachment']) && $record['source_attachment'] !== '' ? $record['source_attachment'] : 'No Attachment',
                    $record['remark'] === '' ? 'Empty Value' : $record['remark'],
                ];

                $log = [
                    'log_act' => 'Import',
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => FB_ADS_TOPUP,
                    'newval' => implodeWithComma($newvalarr),
                    'act_msg' => USER_NAME . " imported the data [ <b> ID = " . $dataID . " </b> ] from <b><i>" . FB_ADS_TOPUP . " Table</i></b>.",
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                audit_log($log);

                $insertedCount++;
            }

            mysqli_commit($finance_connect);
            echo '<script>alert("Imported ' . $insertedCount . ' Facebook Ads top up transaction(s) successfully.");window.location.href="' . $facebookRedirectPage . '";</script>';
            exit;
        } catch (Exception $exception) {
            mysqli_rollback($finance_connect);
            $importErrors[] = $exception->getMessage();
        }
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

function getMetaAdsAccountOptions($dbConnect)
{
    $list = [];
    $query = "SELECT id, accID, accName FROM " . META_ADS_ACC . " WHERE status = 'A' ORDER BY accName ASC";
    $result = mysqli_query($dbConnect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $list[$row['id']] = [
                'acc_id' => $row['accID'],
                'acc_name' => $row['accName'],
                'label' => trim($row['accName'] . ($row['accID'] !== '' ? ' (' . $row['accID'] . ')' : '')),
            ];
        }
    }

    return $list;
}

function getMetaAdsAccountLabelById($options, $id)
{
    return isset($options[$id]['label']) ? $options[$id]['label'] : '';
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

function normalizeDigitsOnly($text)
{
    return preg_replace('/\D+/', '', (string) $text);
}

function parseFacebookReceiptDate($value)
{
    $value = normalizeImportText(str_replace(' at ', ', ', $value));
    $formats = ['j M Y, H:i', 'j M Y, H:i:s', 'd M Y, H:i', 'd M Y, H:i:s', 'Y-m-d'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

function formatImportDateOnly($value)
{
    $parsed = parseFacebookReceiptDate($value);
    return $parsed !== '' ? $parsed : date('Y-m-d');
}

function resolveMetaAdsAccountId($rawValue, $options)
{
    $normalizedLookup = normalizeImportLookup($rawValue);
    $normalizedDigits = normalizeDigitsOnly($rawValue);

    foreach ($options as $id => $option) {
        $optionLookup = normalizeImportLookup($option['acc_id']);
        $optionDigits = normalizeDigitsOnly($option['acc_id']);
        $optionNameLookup = normalizeImportLookup($option['acc_name']);

        if ($normalizedLookup !== '' && ($optionLookup === $normalizedLookup || $optionNameLookup === $normalizedLookup)) {
            return $id;
        }

        if ($normalizedDigits !== '' && ($optionDigits === $normalizedDigits || substr($optionDigits, -strlen($normalizedDigits)) === $normalizedDigits)) {
            return $id;
        }
    }

    return '';
}

function getImportLabelById($options, $id)
{
    return isset($options[$id]) ? $options[$id] : '';
}

function sanitizeImportFilename($filename)
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) $filename));
    return $filename !== '' ? $filename : ('import_' . uniqid() . '.pdf');
}

function saveImportAttachmentBinary($binaryContent, $originalName, $pageName)
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

    $relDir = 'attachment/sqlaccount/' . date('Y') . '/' . date('m') . '/' . $safePage . '/';
    $absDir = ROOT . img_server . $relDir;
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

function collectFacebookImportSourceFiles($fileInfo, &$errors, &$warnings)
{
    $sourceFiles = [];
    $originalName = isset($fileInfo['name']) ? $fileInfo['name'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === 'pdf') {
        $pdfContent = @file_get_contents($fileInfo['tmp_name']);
        if ($pdfContent === false || $pdfContent === '') {
            $errors[] = 'Unable to read the uploaded Facebook Ads PDF file.';
            return [];
        }

        $sourceFiles[] = [
            'pdf_content' => $pdfContent,
            'original_name' => sanitizeImportFilename($originalName),
            'attachment_path' => saveImportAttachmentBinary($pdfContent, $originalName, basename(__FILE__, '.php')),
        ];
        return $sourceFiles;
    }

    if ($extension !== 'zip') {
        $errors[] = 'Only PDF or ZIP files are supported for Facebook Ads import.';
        return [];
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($fileInfo['tmp_name']) !== true) {
            $errors[] = 'The uploaded ZIP file could not be opened.';
            return [];
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (substr($entryName, -1) === '/') {
                continue;
            }

            if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $pdfContent = $zip->getFromIndex($index);
            if ($pdfContent === false || $pdfContent === '') {
                $warnings[] = 'Unable to read PDF entry from ZIP: ' . $entryName;
                continue;
            }

            $sourceFiles[] = [
                'pdf_content' => $pdfContent,
                'original_name' => sanitizeImportFilename($entryName),
                'attachment_path' => saveImportAttachmentBinary($pdfContent, (string) basename($entryName), basename(__FILE__, '.php')),
            ];
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

                $sourceFiles[] = [
                    'pdf_content' => $pdfContent,
                    'original_name' => sanitizeImportFilename($entryName),
                    'attachment_path' => saveImportAttachmentBinary($pdfContent, (string) basename($entryName), basename(__FILE__, '.php')),
                ];
            }
        } catch (Exception $exception) {
            $errors[] = 'The uploaded ZIP file could not be opened.';
            return [];
        }
    } else {
        $errors[] = 'ZIP import requires PHP ZipArchive support in the current web runtime.';
        return [];
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

    if (strlen($stream) > 6) {
        $decoded = @gzinflate(substr($stream, 2));
        if ($decoded !== false) {
            return $decoded;
        }
    }

    return false;
}

function cleanPdfTextOperand($text)
{
    $text = str_replace("\x00", "", $text);
    $text = strtr($text, [
        '\\n' => ' ',
        '\\r' => ' ',
        '\\t' => ' ',
        '\\(' => '(',
        '\\)' => ')',
        '\\\\' => '\\',
    ]);
    return normalizeImportText(preg_replace('/[^[:print:] ]/', ' ', $text));
}

function extractTextFromPdfContent($content)
{
    if ($content === '') {
        return '';
    }

    preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $streamMatches);
    $lines = [];

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

function extractPdfValueAfterLabel($text, $label)
{
    if (preg_match('/' . preg_quote($label, '/') . '\s*:?\s*(.+)/i', $text, $matches)) {
        return normalizeImportText($matches[1]);
    }

    return '';
}

function getPdfTextLines($text)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);
    $normalizedLines = [];

    foreach ($lines as $line) {
        $line = normalizeImportText($line);
        if ($line !== '') {
            $normalizedLines[] = $line;
        }
    }

    return $normalizedLines;
}

function extractPdfFieldValue($text, $label)
{
    $lines = getPdfTextLines($text);
    $labelLookup = normalizeImportLookup($label);

    foreach ($lines as $index => $line) {
        $lineLookup = normalizeImportLookup($line);
        if ($lineLookup === '') {
            continue;
        }

        if (strpos($lineLookup, $labelLookup) === false) {
            continue;
        }

        $value = extractPdfValueAfterLabel($line, $label);
        if ($value !== '' && normalizeImportLookup($value) !== $labelLookup) {
            return $value;
        }

        if (isset($lines[$index + 1])) {
            return $lines[$index + 1];
        }
    }

    return '';
}

function extractPdfPaymentStatus($text)
{
    foreach (getPdfTextLines($text) as $line) {
        $normalizedLine = strtolower(trim($line, " \t\n\r\0\x0B.:;,-_()[]{}"));

        if ($normalizedLine === 'paid') {
            return 'Paid';
        }
        if ($normalizedLine === 'unpaid') {
            return 'Unpaid';
        }
        if ($normalizedLine === 'pending') {
            return 'Pending';
        }
        if ($normalizedLine === 'failed') {
            return 'Failed';
        }
    }

    if (preg_match('/(?:^|\R)\s*(Paid|Unpaid|Pending|Failed)\s*(?:\R|$)/i', $text, $matches)) {
        return ucfirst(strtolower($matches[1]));
    }

    $normalizedText = normalizeImportLookup($text);
    if (strpos($normalizedText, 'unpaid') !== false) {
        return 'Unpaid';
    }
    if (strpos($normalizedText, 'pending') !== false) {
        return 'Pending';
    }
    if (strpos($normalizedText, 'failed') !== false) {
        return 'Failed';
    }
    if (strpos($normalizedText, 'paid') !== false) {
        return 'Paid';
    }

    return '';
}

function parseFacebookReceiptPdf($fileInfo, $metaAccounts)
{
    $data = [
        'source_file_name' => $fileInfo['original_name'],
        'source_attachment' => isset($fileInfo['attachment_path']) ? (string) $fileInfo['attachment_path'] : '',
        'source_account_id' => '',
        'source_payment_method' => '',
        'source_reference_number' => '',
        'source_status' => '',
        'meta_acc' => '',
        'transaction_id' => '',
        'payment_date' => '',
        'pic' => (string) USER_ID,
        'topup_amt' => '',
        'remark' => '',
    ];
    $errors = [];
    $warnings = [];
    $skip = false;

    $text = extractTextFromPdfContent($fileInfo['pdf_content']);

    if ($text === '') {
        return [
            'data' => $data,
            'errors' => ['Unable to extract text from PDF receipt: ' . $fileInfo['original_name']],
            'warnings' => $warnings,
            'skip' => false,
        ];
    }

    $data['source_account_id'] = extractPdfFieldValue($text, 'Account ID');
    $data['source_payment_method'] = extractPdfFieldValue($text, 'Payment method');
    $data['source_reference_number'] = extractPdfFieldValue($text, 'Reference number');
    $data['payment_date'] = parseFacebookReceiptDate(extractPdfFieldValue($text, 'Invoice/payment date'));
    $data['transaction_id'] = extractPdfFieldValue($text, 'Transaction ID');
    $data['source_status'] = extractPdfPaymentStatus($text);

    if (preg_match('/\bPaid\b\s*([A-Z]{3}|RM|SGD|USD|EUR|GBP)\s*([0-9][0-9,]*\.?[0-9]*)/is', $text, $matches)) {
        $data['topup_amt'] = number_format((float) str_replace(',', '', $matches[2]), 2, '.', '');
    }

    $data['meta_acc'] = resolveMetaAdsAccountId($data['source_account_id'], $metaAccounts);

    $remarkParts = ['Imported from Facebook Ads receipt'];
    if ($data['transaction_id'] !== '') {
        $remarkParts[] = $data['transaction_id'];
    }
    if ($data['source_reference_number'] !== '') {
        $remarkParts[] = 'Ref ' . $data['source_reference_number'];
    }
    $data['remark'] = implode(' | ', $remarkParts);

    if ($data['source_status'] !== 'Paid') {
        $skip = true;
        $warnings[] = $fileInfo['original_name'] . ' was skipped because payment status is not Paid.';
        return [
            'data' => $data,
            'errors' => $errors,
            'warnings' => $warnings,
            'skip' => $skip,
        ];
    }

    if ($data['source_account_id'] === '') {
        $errors[] = 'Meta account ID could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['transaction_id'] === '') {
        $errors[] = 'Transaction ID could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['payment_date'] === '') {
        $errors[] = 'Invoice/payment date could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['topup_amt'] === '') {
        $errors[] = 'Paid amount could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['meta_acc'] === '') {
        $warnings[] = 'Meta account was not matched automatically for ' . $fileInfo['original_name'] . '. Please choose the correct account before inserting.';
    }

    return [
        'data' => $data,
        'errors' => $errors,
        'warnings' => $warnings,
        'skip' => $skip,
    ];
}

function parseFacebookImportFiles($sourceFiles, $metaAccounts)
{
    $records = [];
    $warnings = [];
    $errors = [];
    $summary = [
        'processed_files' => count($sourceFiles),
        'preview_records' => 0,
        'skipped_files' => 0,
    ];

    foreach ($sourceFiles as $fileInfo) {
        $result = parseFacebookReceiptPdf($fileInfo, $metaAccounts);
        $warnings = array_merge($warnings, $result['warnings']);
        $errors = array_merge($errors, $result['errors']);

        if ($result['skip']) {
            $summary['skipped_files']++;
            continue;
        }

        if (empty($result['errors'])) {
            $records[] = $result['data'];
        }
    }

    $summary['preview_records'] = count($records);
    return [
        'records' => $records,
        'warnings' => $warnings,
        'errors' => $errors,
        'summary' => $summary,
    ];
}

function getFacebookPreviewRecordsFromPost()
{
    $records = [];
    $postedRecords = isset($_POST['fb_records']) && is_array($_POST['fb_records']) ? $_POST['fb_records'] : [];

    foreach ($postedRecords as $record) {
        $records[] = [
            'source_file_name' => normalizeImportText(isset($record['source_file_name']) ? $record['source_file_name'] : ''),
            'source_attachment' => normalizeImportText(isset($record['source_attachment']) ? $record['source_attachment'] : ''),
            'source_account_id' => normalizeImportText(isset($record['source_account_id']) ? $record['source_account_id'] : ''),
            'source_payment_method' => normalizeImportText(isset($record['source_payment_method']) ? $record['source_payment_method'] : ''),
            'source_reference_number' => normalizeImportText(isset($record['source_reference_number']) ? $record['source_reference_number'] : ''),
            'source_status' => normalizeImportText(isset($record['source_status']) ? $record['source_status'] : ''),
            'meta_acc' => normalizeImportText(isset($record['meta_acc']) ? $record['meta_acc'] : ''),
            'transaction_id' => normalizeImportText(isset($record['transaction_id']) ? $record['transaction_id'] : ''),
            'payment_date' => normalizeImportText(isset($record['payment_date']) ? $record['payment_date'] : ''),
            'pic' => normalizeImportText(isset($record['pic']) ? $record['pic'] : ''),
            'topup_amt' => normalizeImportText(isset($record['topup_amt']) ? $record['topup_amt'] : ''),
            'remark' => normalizeImportText(isset($record['remark']) ? $record['remark'] : ''),
        ];
    }

    return $records;
}

function getFacebookImportSummaryFromPost()
{
    return [
        'processed_files' => (int) (isset($_POST['fb_import_summary']['processed_files']) ? $_POST['fb_import_summary']['processed_files'] : 0),
        'preview_records' => (int) (isset($_POST['fb_import_summary']['preview_records']) ? $_POST['fb_import_summary']['preview_records'] : 0),
        'skipped_files' => (int) (isset($_POST['fb_import_summary']['skipped_files']) ? $_POST['fb_import_summary']['skipped_files'] : 0),
    ];
}

function validateFacebookPreviewRecords($records, &$errors, $metaAccounts, $userOptions, $financeConnect)
{
    if (empty($records)) {
        $errors[] = 'No Facebook Ads receipt is available for insert.';
        return;
    }

    $transactionIds = [];

    foreach ($records as $index => $record) {
        $sourceFile = isset($record['source_file_name']) ? trim((string) $record['source_file_name']) : '';
        $rowLabel = 'Receipt ' . ($index + 1) . ': ' . ($sourceFile !== '' ? $sourceFile : ('Facebook receipt #' . ($index + 1)));

        if ($record['source_status'] !== 'Paid') {
            $errors[] = $rowLabel . ' is not marked as Paid.';
        }

        if ($record['meta_acc'] === '' || !isset($metaAccounts[$record['meta_acc']])) {
            $errors[] = $rowLabel . ': Meta Account is required.';
        }

        if ($record['transaction_id'] === '') {
            $errors[] = $rowLabel . ': Transaction ID is required.';
        } else {
            if (isset($transactionIds[$record['transaction_id']])) {
                $errors[] = $rowLabel . ': Duplicate Transaction ID found in the current import batch.';
            }
            $transactionIds[$record['transaction_id']] = true;

            if (isDuplicateRecord('transactionID', $record['transaction_id'], FB_ADS_TOPUP, $financeConnect, '')) {
                $errors[] = $rowLabel . ': Duplicate Transaction ID found in Facebook Ads Top Up Transaction.';
            }
        }

        if ($record['payment_date'] === '' || parseFacebookReceiptDate($record['payment_date']) === '') {
            $errors[] = $rowLabel . ': Payment date is invalid.';
        }

        if ($record['pic'] === '' || !isset($userOptions[$record['pic']])) {
            $errors[] = $rowLabel . ': Person In Charge is required.';
        }

        if ($record['topup_amt'] === '' || !is_numeric($record['topup_amt'])) {
            $errors[] = $rowLabel . ': Amount must be a valid number.';
        }

    }
}

?>

<!DOCTYPE html>
<html>

<head>
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
                            <h2>Facebook Ads Top Up Import</h2>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $facebookRedirectPage ?>">Back To Facebook Ads Page</a>
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
                            <h5 class="card-title mb-3">Step 1: Upload Facebook Ads PDF Or ZIP</h5>
                            <p class="text-muted mb-3">Upload a single PDF receipt or a ZIP file containing multiple PDF receipts. Only receipts with payment status Paid will be prepared for import.</p>
                            <form method="post" enctype="multipart/form-data">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Facebook Ads Receipt File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".pdf,.zip" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseFacebookAdsTopup">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($facebookPreviewRecords)) { ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <h5 class="card-title mb-0">Step 2: Preview And Edit Before Insert</h5>
                                    <div class="text-muted">
                                        Files processed: <?= (int) $facebookImportSummary['processed_files'] ?> |
                                        Ready to import: <?= (int) $facebookImportSummary['preview_records'] ?> |
                                        Skipped: <?= (int) $facebookImportSummary['skipped_files'] ?>
                                    </div>
                                </div>

                                <form method="post">
                                    <input type="hidden" name="importWarnings" value="<?= htmlspecialchars(implode("\n", $importWarnings)) ?>">
                                    <input type="hidden" name="fb_import_summary[processed_files]" value="<?= (int) $facebookImportSummary['processed_files'] ?>">
                                    <input type="hidden" name="fb_import_summary[preview_records]" value="<?= (int) $facebookImportSummary['preview_records'] ?>">
                                    <input type="hidden" name="fb_import_summary[skipped_files]" value="<?= (int) $facebookImportSummary['skipped_files'] ?>">

                                    <?php foreach ($facebookPreviewRecords as $index => $record) { ?>
                                        <div class="border rounded p-3 mb-4 fb-receipt-card" data-receipt-index="<?= (int) $index ?>">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                                <h6 class="mb-0 fb-receipt-title">Receipt <?= $index + 1 ?>: <?= htmlspecialchars($record['source_file_name']) ?></h6>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <span class="badge bg-success">Paid</span>
                                                    <button type="button" class="btn btn-sm btn-rounded btn-danger js-remove-fb-receipt">Remove</button>
                                                </div>
                                            </div>

                                            <input type="hidden" name="fb_records[<?= $index ?>][source_file_name]" value="<?= htmlspecialchars($record['source_file_name']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_attachment]" value="<?= htmlspecialchars(isset($record['source_attachment']) ? $record['source_attachment'] : '') ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_account_id]" value="<?= htmlspecialchars($record['source_account_id']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_payment_method]" value="<?= htmlspecialchars($record['source_payment_method']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_reference_number]" value="<?= htmlspecialchars($record['source_reference_number']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_status]" value="<?= htmlspecialchars($record['source_status']) ?>">

                                            <div class="row mb-3">
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label">Detected Meta Account ID</label>
                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($record['source_account_id']) ?>" readonly>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label">Detected Payment Method</label>
                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($record['source_payment_method']) ?>" readonly>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label">Detected Reference Number</label>
                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($record['source_reference_number']) ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="fb_meta_acc_<?= $index ?>">Meta Account<span class="requireRed">*</span></label>
                                                    <select class="form-select <?= $record['meta_acc'] === '' ? 'warning_input' : '' ?>" id="fb_meta_acc_<?= $index ?>" name="fb_records[<?= $index ?>][meta_acc]" required>
                                                        <option value="">Select Meta Account</option>
                                                        <?php foreach ($metaAccounts as $id => $option) { ?>
                                                            <option value="<?= htmlspecialchars($id) ?>" <?= $record['meta_acc'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="fb_transaction_id_<?= $index ?>">Transaction ID<span class="requireRed">*</span></label>
                                                    <input class="form-control" type="text" id="fb_transaction_id_<?= $index ?>" name="fb_records[<?= $index ?>][transaction_id]" value="<?= htmlspecialchars($record['transaction_id']) ?>" required>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label" for="fb_payment_date_<?= $index ?>">Invoice / Payment Date<span class="requireRed">*</span></label>
                                                    <input class="form-control" type="date" id="fb_payment_date_<?= $index ?>" name="fb_records[<?= $index ?>][payment_date]" value="<?= htmlspecialchars(formatImportDateOnly($record['payment_date'])) ?>" required>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label" for="fb_pic_<?= $index ?>">Person In Charge<span class="requireRed">*</span></label>
                                                    <select class="form-select" id="fb_pic_<?= $index ?>" name="fb_records[<?= $index ?>][pic]" required>
                                                        <option value="">Select Person In Charge</option>
                                                        <?php foreach ($userOptions as $id => $name) { ?>
                                                            <option value="<?= htmlspecialchars($id) ?>" <?= $record['pic'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label" for="fb_topup_amt_<?= $index ?>">Amount<span class="requireRed">*</span></label>
                                                    <input class="form-control" type="number" step="0.01" id="fb_topup_amt_<?= $index ?>" name="fb_records[<?= $index ?>][topup_amt]" value="<?= htmlspecialchars($record['topup_amt']) ?>" required>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <label class="form-label" for="fb_remark_<?= $index ?>">Remark</label>
                                                    <textarea class="form-control" id="fb_remark_<?= $index ?>" name="fb_records[<?= $index ?>][remark]" rows="3"><?= htmlspecialchars($record['remark']) ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                        <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertFacebookAdsTopup" id="fbInsertAllBtn">
                                            <i class="fa-solid fa-database"></i> Insert All
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
    preloader(0, '');
    setButtonColor();
    <?php include "js/facebook_ads_topup_import.js"; ?>
</script>

</html>



