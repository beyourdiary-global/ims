<?php
$currentPagePin = 0;

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
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
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

function satPdfHexToUtf8($hex)
{
    $hex = preg_replace('/[^0-9A-Fa-f]/', '', (string) $hex);
    if ($hex === '') {
        return '';
    }

    if ((strlen($hex) % 2) === 1) {
        $hex .= '0';
    }

    $bin = @hex2bin($hex);
    if ($bin === false) {
        return '';
    }

    if (strlen($hex) <= 2) {
        return cleanPdfTextOperand($bin);
    }

    if ((strlen($hex) % 4) === 0 && function_exists('mb_convert_encoding')) {
        $text = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
        if (is_string($text) && $text !== '') {
            return $text;
        }
    }

    return cleanPdfTextOperand($bin);
}

function satPdfIncrementHex($hex, $step)
{
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string) $hex));
    $step = (int) $step;
    if ($hex === '' || $step < 0) {
        return '';
    }

    $width = strlen($hex);
    if ($width > 8) {
        return '';
    }

    $value = hexdec($hex);
    $next = $value + $step;
    if ($next < 0) {
        return '';
    }

    return strtoupper(str_pad(dechex($next), $width, '0', STR_PAD_LEFT));
}

function satBuildPdfUnicodeMapFromContent($content)
{
    $map = array();
    $codeLengths = array();

    preg_match_all('/stream\s*\r?\n(.*?)\r?\n?endstream/s', (string) $content, $streamMatches);
    $streams = isset($streamMatches[1]) && is_array($streamMatches[1]) ? $streamMatches[1] : array();

    foreach ($streams as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false || $decoded === '') {
            continue;
        }

        if (stripos($decoded, 'beginbfchar') === false && stripos($decoded, 'beginbfrange') === false) {
            continue;
        }

        if (preg_match_all('/beginbfchar(.*?)endbfchar/si', $decoded, $bfCharBlocks)) {
            foreach ($bfCharBlocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $pair) {
                        $src = strtoupper($pair[1]);
                        $dst = satPdfHexToUtf8($pair[2]);
                        if ($src === '' || $dst === '') {
                            continue;
                        }
                        $map[$src] = $dst;
                        $codeLengths[strlen($src)] = true;
                    }
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/si', $decoded, $bfRangeBlocks)) {
            foreach ($bfRangeBlocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $rangeMatches, PREG_SET_ORDER)) {
                    foreach ($rangeMatches as $rangeMatch) {
                        $start = strtoupper($rangeMatch[1]);
                        $end = strtoupper($rangeMatch[2]);
                        $destStart = strtoupper($rangeMatch[3]);

                        if ($start === '' || $end === '' || $destStart === '' || strlen($start) !== strlen($end)) {
                            continue;
                        }

                        $startVal = hexdec($start);
                        $endVal = hexdec($end);
                        if ($endVal < $startVal) {
                            continue;
                        }

                        $total = $endVal - $startVal;
                        if ($total > 1024) {
                            continue;
                        }

                        for ($offset = 0; $offset <= $total; $offset++) {
                            $src = satPdfIncrementHex($start, $offset);
                            $dstHex = satPdfIncrementHex($destStart, $offset);
                            $dst = satPdfHexToUtf8($dstHex);

                            if ($src === '' || $dst === '') {
                                continue;
                            }

                            $map[$src] = $dst;
                            $codeLengths[strlen($src)] = true;
                        }
                    }
                }
            }
        }
    }

    $lengths = array_map('intval', array_keys($codeLengths));
    rsort($lengths, SORT_NUMERIC);

    return array(
        'map' => $map,
        'code_lengths' => $lengths,
    );
}

function satDecodePdfHexTokenWithUnicodeMap($hex, $unicodeMap)
{
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string) $hex));
    if ($hex === '') {
        return '';
    }

    $map = isset($unicodeMap['map']) && is_array($unicodeMap['map']) ? $unicodeMap['map'] : array();
    $lengths = isset($unicodeMap['code_lengths']) && is_array($unicodeMap['code_lengths']) ? $unicodeMap['code_lengths'] : array();

    if (empty($map) || empty($lengths)) {
        return '';
    }

    foreach ($lengths as $codeLen) {
        $codeLen = (int) $codeLen;
        if ($codeLen <= 0 || (strlen($hex) % $codeLen) !== 0) {
            continue;
        }

        $parts = str_split($hex, $codeLen);
        $hits = 0;
        $out = '';

        foreach ($parts as $part) {
            if (isset($map[$part])) {
                $out .= $map[$part];
                $hits++;
            }
        }

        if ($hits > 0 && $hits >= (int) ceil(count($parts) * 0.6)) {
            return cleanPdfTextOperand($out);
        }
    }

    return '';
}

function satDecodePdfLiteralToken($token, $unicodeMap = array())
{
    $token = trim((string) $token);
    if ($token === '') {
        return '';
    }

    if ($token[0] === '<' && substr($token, -1) === '>') {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($token, 1, -1));
        if ($hex === '') {
            return '';
        }
        $decodedWithMap = satDecodePdfHexTokenWithUnicodeMap($hex, $unicodeMap);
        if ($decodedWithMap !== '') {
            return $decodedWithMap;
        }
        if ((strlen($hex) % 2) === 1) {
            $hex .= '0';
        }
        $bin = @hex2bin($hex);
        return $bin === false ? '' : cleanPdfTextOperand($bin);
    }

    if ($token[0] === '(' && substr($token, -1) === ')') {
        $inner = substr($token, 1, -1);
    } else {
        $inner = $token;
    }

    $inner = preg_replace_callback(
        '/\\\\([0-7]{1,3})/',
        function ($m) {
            return chr(octdec($m[1]));
        },
        $inner,
    );

    $inner = str_replace(array('\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)', '\\\\'), array("\n", "\r", "\t", "\b", "\f", "(", ")", "\\"), $inner);
    $inner = preg_replace('/\\\\\r\n|\\\\\n|\\\\\r/', '', $inner);

    return cleanPdfTextOperand($inner);
}

function satExtractPdfTextTokensFromDecodedStream($decoded, $unicodeMap = array())
{
    $decoded = (string) $decoded;
    if ($decoded === '') {
        return array();
    }

    $lines = array();
    $singleTokenPattern = '/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*Tj/s';
    if (preg_match_all($singleTokenPattern, $decoded, $matches)) {
        foreach ($matches[1] as $token) {
            $line = satDecodePdfLiteralToken($token, $unicodeMap);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    $apostrophePattern = '/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*\'/s';
    if (preg_match_all($apostrophePattern, $decoded, $matches)) {
        foreach ($matches[1] as $token) {
            $line = satDecodePdfLiteralToken($token, $unicodeMap);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    $quotePattern = '/[-+0-9.\s]+[-+0-9.\s]+(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*"/s';
    if (preg_match_all($quotePattern, $decoded, $matches)) {
        foreach ($matches[1] as $token) {
            $line = satDecodePdfLiteralToken($token, $unicodeMap);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
        foreach ($arrayMatches[1] as $chunk) {
            if (preg_match_all('/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)/s', $chunk, $innerMatches)) {
                $parts = array();
                foreach ($innerMatches[1] as $token) {
                    $part = satDecodePdfLiteralToken($token, $unicodeMap);
                    if ($part !== '') {
                        $parts[] = $part;
                    }
                }
                $line = cleanPdfTextOperand(implode('', $parts));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
    }

    return $lines;
}

function satExtractTextFromPdfViaCommand($filePath)
{
    $filePath = trim((string) $filePath);
    if ($filePath === '' || !is_file($filePath) || !function_exists('shell_exec')) {
        return '';
    }

    $escapedFile = escapeshellarg($filePath);
    $commands = array(
        'pdftotext -enc UTF-8 -layout ' . $escapedFile . ' - 2>NUL',
        'pdftotext -enc UTF-8 ' . $escapedFile . ' - 2>NUL',
    );

    foreach ($commands as $command) {
        $output = @shell_exec($command);
        $output = is_string($output) ? trim($output) : '';
        if ($output !== '') {
            return $output;
        }
    }

    return '';
}

function extractTextFromPdfContent($content)
{
    if ((string) $content === '') {
        return '';
    }

    $unicodeMap = satBuildPdfUnicodeMapFromContent($content);
    preg_match_all('/stream\s*\r?\n(.*?)\r?\n?endstream/s', (string) $content, $streamMatches);
    $lines = array();

    foreach ($streamMatches[1] as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false) {
            $decoded = (string) $stream;
        }
        $streamLines = satExtractPdfTextTokensFromDecodedStream($decoded, $unicodeMap);
        if (!empty($streamLines)) {
            foreach ($streamLines as $line) {
                $lines[] = $line;
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

function satRepairSplitPdfWords($text)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    // Repair OCR-like token splits: "Or der", "P a yment", "W allet"
    do {
        $text = preg_replace('/\b([A-Za-z]{1,2})\s+([A-Za-z]{2,})\b/u', '$1$2', $text, -1, $count);
    } while ($count > 0);

    return $text;
}

function scoreShopeeAdsPdfReadability($text)
{
    $text = normalizeImportLookup($text);
    if ($text === '') {
        return 0;
    }

    $keywords = array(
        'completed',
        'shopeeads',
        'orderid',
        'paymenttotal',
        'subtotal',
        'sst',
        'gst',
        'paymentmethod',
        'shopeewallet',
        'shopname',
        'orderdetails',
        'topuphistory',
    );

    $score = 0;
    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $score++;
        }
    }

    return $score;
}

function decodeShopeeAdsPdfShiftedGlyphText($text, $mapDigits = true)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $decoded = '';
    $length = strlen($text);

    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];
        $ord = ord($char);

        if ($ord >= 69 && $ord <= 94) {
            $decoded .= chr($ord - 4);
            continue;
        }

        if ($mapDigits && $ord >= 39 && $ord <= 63) {
            $decoded .= chr($ord + 28);
            continue;
        }

        $decoded .= $char;
    }

    return $decoded;
}

function satSelectBestShopeeAdsPdfText($text)
{
    $source = (string) $text;
    $candidates = array();

    $candidates[] = $source;
    $candidates[] = satRepairSplitPdfWords($source);

    $decodedWithDigits = decodeShopeeAdsPdfShiftedGlyphText($source, true);
    $decodedNoDigits = decodeShopeeAdsPdfShiftedGlyphText($source, false);

    $candidates[] = $decodedWithDigits;
    $candidates[] = satRepairSplitPdfWords($decodedWithDigits);
    $candidates[] = $decodedNoDigits;
    $candidates[] = satRepairSplitPdfWords($decodedNoDigits);

    $bestText = $source;
    $bestScore = -1;

    foreach ($candidates as $candidate) {
        $candidate = (string) $candidate;
        $candidate = str_replace("\r", '', $candidate);
        $candidate = preg_replace('/[^[:print:]\n\t]/', ' ', $candidate);
        $candidate = preg_replace('/[ \t]+/', ' ', $candidate);
        $candidate = preg_replace('/\n{3,}/', "\n\n", $candidate);
        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }

        $score = scoreShopeeAdsPdfReadability($candidate);
        if (preg_match('/Order\s*ID\s*:\s*[0-9]{12,20}/i', $candidate)) {
            $score += 2;
        }
        if (preg_match('/(?:Payment\s*Total|Subtotal|SST|GST)\s*:\s*(RM|MYR|S\$|SGD|USD)?\s*[0-9]/i', $candidate)) {
            $score += 2;
        }
        if (preg_match('/\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}/', $candidate)) {
            $score += 1;
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestText = $candidate;
        }
    }

    return array(
        'text' => $bestText,
        'score' => $bestScore,
        'source_score' => scoreShopeeAdsPdfReadability($source),
    );
}

function satBuildFlexibleLabelRegex($label)
{
    $chars = preg_replace('/[^A-Za-z0-9]/', '', strtoupper((string) $label));
    if ($chars === '') {
        return '';
    }

    return implode('\\W*', str_split($chars));
}

function satExtractRawPdfSearchText($content)
{
    $chunks = array();
    preg_match_all('/stream\s*\r?\n(.*?)\r?\n?endstream/s', (string) $content, $streamMatches);
    $streams = isset($streamMatches[1]) && is_array($streamMatches[1]) ? $streamMatches[1] : array();

    foreach ($streams as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false) {
            $decoded = (string) $stream;
        }
        $chunks[] = (string) $decoded;
    }

    $text = implode("\n", $chunks);
    $text = str_replace("\r", '', $text);
    $text = preg_replace('/[^[:print:]\n\t]/', ' ', $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    return trim((string) $text);
}

function satExtractMoneyNearLabel($text, $label)
{
    $labelRegex = satBuildFlexibleLabelRegex($label);
    if ($labelRegex === '') {
        return array('currency' => '', 'amount' => '');
    }

    $moneyRegex = '/(?:RM|MYR|S\$|SGD|USD)?\s*([0-9][0-9,]*\.?[0-9]*)/i';
    if (preg_match('/' . $labelRegex . '(.{0,80})/is', (string) $text, $windowMatch)) {
        $window = (string) $windowMatch[1];
        if (preg_match($moneyRegex, $window, $moneyMatch)) {
            $currency = '';
            if (preg_match('/(RM|MYR|S\$|SGD|USD)/i', $window, $currencyMatch)) {
                $currency = strtoupper((string) $currencyMatch[1]);
            }
            return array(
                'currency' => $currency,
                'amount' => number_format((float) str_replace(',', '', (string) $moneyMatch[1]), 2, '.', ''),
            );
        }
    }

    return array('currency' => '', 'amount' => '');
}

function satIsGenericShopeeAccountCandidate($value)
{
    $lookup = normalizeImportLookup((string) $value);
    if ($lookup === '') {
        return true;
    }

    $generic = array(
        'shopee',
        'shopeeads',
        'shopeewallet',
        'wallet',
        'shopname',
        'orderdetails',
    );

    return in_array($lookup, $generic, true);
}

function satResolveBestShopeeAccountFromText($textLines, $shopeeAccounts)
{
    $bestId = '';
    $bestScore = 0;

    foreach ($shopeeAccounts as $accId => $accName) {
        $label = (string) $accName;
        $normalizedLabel = normalizeImportLookup($label);
        if ($normalizedLabel === '') {
            continue;
        }

        $variants = array(
            $label,
            str_replace(array('.', '_', '-'), ' ', $label),
            str_replace(array('.', '_', '-'), '', $label),
        );
        $scoreBase = strlen($normalizedLabel);

        foreach ($textLines as $line) {
            $lineNorm = normalizeImportLookup($line);
            if ($lineNorm === '') {
                continue;
            }

            $score = 0;
            if (strpos($lineNorm, $normalizedLabel) !== false) {
                $score = $scoreBase + 20;
            } else {
                foreach ($variants as $variant) {
                    $variant = trim((string) $variant);
                    if ($variant !== '' && stripos((string) $line, $variant) !== false) {
                        $score = max($score, $scoreBase + 10);
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (string) $accId;
            }
        }
    }

    return array(
        'id' => $bestId,
        'score' => $bestScore,
    );
}

function satResolveBestOptionIdFromText($text, $options)
{
    $bestId = '';
    $bestScore = 0;
    $textLookup = normalizeImportLookup((string) $text);
    if ($textLookup === '') {
        return '';
    }

    foreach ($options as $optionId => $optionLabel) {
        $labelLookup = normalizeImportLookup((string) $optionLabel);
        if ($labelLookup === '') {
            continue;
        }

        if (strpos($textLookup, $labelLookup) !== false) {
            $score = strlen($labelLookup);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (string) $optionId;
            }
        }
    }

    return $bestId;
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
                if ($value !== '' && preg_match('/[A-Za-z0-9]/', $value) && normalizeImportLookup($value) !== $labelLookup) {
                    return $value;
                }
            }

            if (isset($lines[$index + 1])) {
                $nextValue = normalizeImportText($lines[$index + 1]);
                if ($nextValue !== '' && preg_match('/[A-Za-z0-9]/', $nextValue)) {
                    return $nextValue;
                }
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
        $tmpFile = tempnam(sys_get_temp_dir(), 'sat_pdf_');
        if ($tmpFile !== false) {
            @file_put_contents($tmpFile, $pdfContent);
            $commandText = satExtractTextFromPdfViaCommand($tmpFile);
            if ($commandText !== '') {
                $text = $commandText;
            }
            @unlink($tmpFile);
        }
    }

    if ($text === '') {
        return array(
            'data' => $data,
            'errors' => array('Unable to extract text from PDF receipt: ' . $fileName),
            'warnings' => $warnings,
        );
    }

    $bestTextBundle = satSelectBestShopeeAdsPdfText($text);
    $textForParse = isset($bestTextBundle['text']) ? (string) $bestTextBundle['text'] : satRepairSplitPdfWords($text);
    $rawCorpus = satExtractRawPdfSearchText($pdfContent);
    $parseCorpus = trim($textForParse . "\n" . satRepairSplitPdfWords($rawCorpus));
    $textLines = getPdfTextLines($textForParse);

    $data['source_shop_name'] = extractPdfFieldByLabels($parseCorpus, array('Shop Name', 'Shopee Account'));
    $data['order_id'] = extractPdfFieldByLabels($parseCorpus, array('Order ID', 'Order No', 'Order Number'));
    $data['payment_date'] = parseShopeeDatetime(extractPdfFieldByLabels($parseCorpus, array('Completed', 'Payment Date', 'DateTime', 'Invoice Date')));
    $data['source_payment_method'] = extractPdfFieldByLabels($parseCorpus, array('Payment Method'));

    if (satIsGenericShopeeAccountCandidate($data['source_shop_name'])) {
        $data['source_shop_name'] = '';
    }

    if ($data['source_shop_name'] === '' && preg_match('/Shop\s*Name\s*:?\s*([A-Za-z0-9 ._\-]{3,80})/i', $parseCorpus, $match)) {
        $data['source_shop_name'] = normalizeImportText($match[1]);
    }

    if ($data['order_id'] === '') {
        $orderLabelRegex = satBuildFlexibleLabelRegex('Order ID');
        if ($orderLabelRegex !== '' && preg_match('/' . $orderLabelRegex . '\W*([0-9\W]{12,40})/i', $parseCorpus, $match)) {
            $digits = preg_replace('/\D+/', '', (string) $match[1]);
            if (strlen($digits) >= 12 && strlen($digits) <= 20) {
                $data['order_id'] = $digits;
            }
        }
    }

    if ($data['order_id'] === '' && preg_match('/Order\s*ID\s*:\s*([0-9]{12,20})/i', $parseCorpus, $match)) {
        $data['order_id'] = $match[1];
    }

    if ($data['order_id'] === '' && preg_match('/Order\W*ID\W*([0-9\W]{12,40})/i', $parseCorpus, $match)) {
        $digits = preg_replace('/\D+/', '', (string) $match[1]);
        if (strlen($digits) >= 12 && strlen($digits) <= 20) {
            $data['order_id'] = $digits;
        }
    }

    if ($data['order_id'] === '' && preg_match('/\b([0-9]{12,20})\b/', $parseCorpus, $match)) {
        $data['order_id'] = $match[1];
    }

    if ($data['payment_date'] === '' && preg_match('/topped\s*up\s*at\s*(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?)/i', $parseCorpus, $match)) {
        $data['payment_date'] = parseShopeeDatetime($match[1]);
    }

    if ($data['payment_date'] === '' && preg_match('/order\s*is\s*completed\s*(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?)/i', $parseCorpus, $match)) {
        $data['payment_date'] = parseShopeeDatetime($match[1]);
    }

    if ($data['payment_date'] === '' && preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}(?::\d{2})?)\b/', $parseCorpus, $match)) {
        $data['payment_date'] = parseShopeeDatetime($match[1]);
    }

    if ($data['source_payment_method'] === '' && preg_match('/Payment\s*Method\s*:\s*([^\r\n]+)/i', $parseCorpus, $match)) {
        $data['source_payment_method'] = normalizeImportText($match[1]);
    }

    if ($data['source_payment_method'] !== '' && !preg_match('/[A-Za-z0-9]/', $data['source_payment_method'])) {
        $data['source_payment_method'] = '';
    }

    if ($data['source_payment_method'] === '') {
        $walletRegex = satBuildFlexibleLabelRegex('Shopee Wallet');
        if ($walletRegex !== '' && preg_match('/' . $walletRegex . '/i', $parseCorpus)) {
            $data['source_payment_method'] = 'Shopee Wallet';
        }
    }

    if ($data['source_payment_method'] === '' && preg_match('/Shopee\s*Wallet/i', $parseCorpus)) {
        $data['source_payment_method'] = 'Shopee Wallet';
    }

    $paymentTotal = extractPdfMoneyByLabels($parseCorpus, array('Payment Total', 'Top-up Amount', 'Top Up Amount', 'Amount'));
    $subtotal = extractPdfMoneyByLabels($parseCorpus, array('Subtotal'));
    $taxValue = extractPdfMoneyByLabels($parseCorpus, array('GST', 'SST', 'Tax'));

    if ($paymentTotal['amount'] === '') {
        $paymentTotal = satExtractMoneyNearLabel($parseCorpus, 'Payment Total');
    }
    if ($subtotal['amount'] === '') {
        $subtotal = satExtractMoneyNearLabel($parseCorpus, 'Subtotal');
    }
    if ($taxValue['amount'] === '') {
        $taxValue = satExtractMoneyNearLabel($parseCorpus, 'SST');
    }
    if ($taxValue['amount'] === '') {
        $taxValue = satExtractMoneyNearLabel($parseCorpus, 'GST');
    }

    if ($paymentTotal['amount'] === '' && preg_match('/Payment\s*Total\s*:\s*(RM|MYR|S\$|SGD|USD)?\s*([0-9][0-9,]*\.?[0-9]*)/i', $parseCorpus, $match)) {
        $paymentTotal = array(
            'currency' => strtoupper(isset($match[1]) ? $match[1] : ''),
            'amount' => number_format((float) str_replace(',', '', $match[2]), 2, '.', ''),
        );
    }

    if ($subtotal['amount'] === '' && preg_match('/Subtotal\s*:\s*(RM|MYR|S\$|SGD|USD)?\s*([0-9][0-9,]*\.?[0-9]*)/i', $parseCorpus, $match)) {
        $subtotal = array(
            'currency' => strtoupper(isset($match[1]) ? $match[1] : ''),
            'amount' => number_format((float) str_replace(',', '', $match[2]), 2, '.', ''),
        );
    }

    if ($taxValue['amount'] === '' && preg_match('/(?:SST|GST|Tax)\s*:\s*(RM|MYR|S\$|SGD|USD)?\s*([0-9][0-9,]*\.?[0-9]*)/i', $parseCorpus, $match)) {
        $taxValue = array(
            'currency' => strtoupper(isset($match[1]) ? $match[1] : ''),
            'amount' => number_format((float) str_replace(',', '', $match[2]), 2, '.', ''),
        );
    }

    $data['source_currency'] = $paymentTotal['currency'] !== '' ? $paymentTotal['currency'] : $subtotal['currency'];
    $data['topup_amt'] = $paymentTotal['amount'];
    $data['subtotal'] = $subtotal['amount'];
    $data['gst'] = $taxValue['amount'];

    // For Shopee ads receipts, GST/SST should reconcile to (Payment Total - Subtotal)
    if ($data['topup_amt'] !== '' && $data['subtotal'] !== '' && is_numeric($data['topup_amt']) && is_numeric($data['subtotal'])) {
        $calculatedTax = round(((float) $data['topup_amt']) - ((float) $data['subtotal']), 2);
        if ($calculatedTax >= 0) {
            if ($data['gst'] === '' || !is_numeric($data['gst']) || abs(((float) $data['gst']) - $calculatedTax) > 0.009) {
                $data['gst'] = number_format($calculatedTax, 2, '.', '');
            }
        }
    }

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
    $bestAccount = satResolveBestShopeeAccountFromText($textLines, $shopeeAccounts);
    if ($bestAccount['id'] !== '') {
        $currentLabel = getImportLabelById($shopeeAccounts, $data['shopee_acc']);
        $bestLabel = getImportLabelById($shopeeAccounts, $bestAccount['id']);

        if (
            $data['shopee_acc'] === '' ||
            satIsGenericShopeeAccountCandidate($data['source_shop_name']) ||
            satIsGenericShopeeAccountCandidate($currentLabel) ||
            strlen(normalizeImportLookup($bestLabel)) > strlen(normalizeImportLookup($currentLabel))
        ) {
            $data['shopee_acc'] = (string) $bestAccount['id'];
            $data['source_shop_name'] = (string) $bestLabel;
        }
    }

    $data['currency'] = resolveImportOptionId($data['source_currency'], $currencyUnits, $currencyFallbacks);
    $data['pay_meth'] = resolveImportOptionId($data['source_payment_method'], $paymentMethods);
    if ($data['pay_meth'] === '') {
        $bestPayMethodId = satResolveBestOptionIdFromText($parseCorpus, $paymentMethods);
        if ($bestPayMethodId !== '') {
            $data['pay_meth'] = $bestPayMethodId;
            $data['source_payment_method'] = (string) getImportLabelById($paymentMethods, $bestPayMethodId);
        }
    }

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
        $errors[] = 'Shopee account could not be matched from the PDF. Please ensure the account exists in database.';
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



