<?php
$currentPagePin = 88;
$pageTitle = '';

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$parentPagePinGroupId = 88;
$parentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($parentPageTitle === '') {
    $parentPageTitle = 'J&T Transaction Backup Record';
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

$tblName = 'jt_transaction_backup';

$importPage = $SITEURL . '/import/j&t_trans_backup_import.php';
$tablePage = $SITEURL . '/finance/j&t_trans_backup_table.php';
$shortcutPage = $SITEURL . '/import/common_import.php';
$itemTable = JT_TRANS_ITEM;
$gstTable = JT_TRANS_GST;

$importErrors = array();
$importWarnings = array();
$inlineCurrencyErrors = array();

$currencyOptions = array();
$currencyOptionsMap = array();
$currencyRst = getData('id, unit', '', '', CUR_UNIT, $connect);
if ($currencyRst && $currencyRst->num_rows > 0) {
    while ($currencyRow = $currencyRst->fetch_assoc()) {
        $currencyUnit = strtoupper(trim((string) $currencyRow['unit']));
        if ($currencyUnit !== '') {
            $currencyOptions[] = $currencyUnit;
            $currencyOptionsMap[$currencyUnit] = true;
        }
    }
}

if (!function_exists('jtImpToAmount')) {
    function jtImpToAmount($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace(' ', '', $value);
        if (strpos($value, ',') !== false && strpos($value, '.') === false) {
            $value = str_replace(',', '.', $value);
        } else if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace(',', '', $value);
        }
        $value = preg_replace('/[^0-9\.\-]/', '', $value);
        if ($value === '' || $value === '-' || $value === '.') {
            return 0.0;
        }

        return (float) $value;
    }
}

if (!function_exists('jtImpSaveUploadedImportFile')) {
    function jtImpSaveUploadedImportFile($upload, $pageName)
    {
        if (!isset($upload['tmp_name']) || !isset($upload['name'])) {
            return '';
        }
        $tmpName = (string) $upload['tmp_name'];
        $originalName = trim((string) $upload['name']);
        if ($tmpName === '' || $originalName === '' || !is_file($tmpName)) {
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

        $newFile = $safeBase . '_' . date('Ymd_His') . ($ext !== '' ? '.' . $ext : '');
        $absPath = $absDir . $newFile;
        if (@copy($tmpName, $absPath)) {
            return $relDir . $newFile;
        }
        return '';
    }
}

if (!function_exists('jtImpStoreAttachmentBinary')) {
    function jtImpStoreAttachmentBinary($binaryContent, $originalName, $pageName)
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
}

if (!function_exists('jtImpBuildSourceAttachmentMap')) {
    function jtImpBuildSourceAttachmentMap($upload, $pageName, &$warnings)
    {
        $map = array();
        if (!isset($upload['tmp_name']) || !isset($upload['name'])) {
            return $map;
        }

        $tmpName = (string) $upload['tmp_name'];
        $originalName = (string) $upload['name'];
        if ($tmpName === '' || $originalName === '' || !is_file($tmpName)) {
            return $map;
        }

        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $content = @file_get_contents($tmpName);
            if ($content !== false && $content !== '') {
                $savedPath = jtImpStoreAttachmentBinary($content, $originalName, $pageName);
                if ($savedPath !== '') {
                    $map[strtolower((string) basename($originalName))] = $savedPath;
                }
            }
            return $map;
        }

        if ($ext !== 'zip') {
            return $map;
        }

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpName) === true) {
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entryName = (string) $zip->getNameIndex($index);
                    if ($entryName === '' || substr($entryName, -1) === '/') {
                        continue;
                    }
                    if (strtolower((string) pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                        continue;
                    }

                    $pdfContent = $zip->getFromIndex($index);
                    if ($pdfContent === false || $pdfContent === '') {
                        $warnings[] = 'Unable to read PDF entry for attachment save: ' . $entryName;
                        continue;
                    }

                    $baseName = (string) basename($entryName);
                    $savedPath = jtImpStoreAttachmentBinary($pdfContent, $baseName, $pageName);
                    if ($savedPath !== '') {
                        $map[strtolower($baseName)] = $savedPath;
                    }
                }
                $zip->close();
            }
        }

        return $map;
    }
}

if (!function_exists('jtImpNormalizeDate')) {
    function jtImpNormalizeDate($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $formats = array('Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y', 'm-d-Y');
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $value);
            if ($dt && $dt->format($fmt) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        $ts = strtotime($value);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return '';
    }
}

if (!function_exists('jtImpParseDeliveryLine')) {
    function jtImpParseDeliveryLine($line)
    {
        $line = trim((string) $line);
        if ($line === '') {
            return null;
        }

        if (stripos($line, 'service type') !== false || stripos($line, 'number of shipments') !== false) {
            return null;
        }

        if (!preg_match('/^([A-Za-z0-9\-\/\&\(\)\s]+?)\s+([0-9]+)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)\s+([0-9]+(?:[\.,][0-9]+)?)$/', $line, $m)) {
            return null;
        }

        $weightRaw = trim((string) $m[3]);
        $weightVal = jtImpToAmount($weightRaw);
        // OCR sometimes drops the decimal separator (e.g. 1.50 -> 150). Restore with a conservative heuristic.
        if (strpos($weightRaw, '.') === false && strpos($weightRaw, ',') === false && preg_match('/^\d{3,}$/', $weightRaw) && $weightVal >= 100) {
            $weightVal = $weightVal / 100;
        }

        return array(
            'service_type' => trim((string) $m[1]),
            'shipments_count' => (string) ((int) $m[2]),
            'total_weight_kg' => number_format($weightVal, 2, '.', ''),
            'standard_charge' => number_format(jtImpToAmount($m[4]), 2, '.', ''),
            'extra_charges' => number_format(jtImpToAmount($m[5]), 2, '.', ''),
            'nett_charge' => number_format(jtImpToAmount($m[6]), 2, '.', ''),
        );
    }
}

if (!function_exists('jtImpParseGstLine')) {
    function jtImpParseGstLine($line)
    {
        $line = trim((string) $line);
        if ($line === '') {
            return null;
        }

        if (stripos($line, 'analysis of gst') !== false || stripos($line, 'total of gst') !== false) {
            return null;
        }

        if (!preg_match('/^([A-Za-z0-9\-]+)\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9]+(?:\.[0-9]+)?)$/', $line, $m)) {
            return null;
        }

        return array(
            'gst_type' => trim((string) $m[1]),
            'gst_rate' => number_format(jtImpToAmount($m[2]), 2, '.', ''),
            'gst_amount' => number_format(jtImpToAmount($m[3]), 2, '.', ''),
            'gst_paid' => number_format(jtImpToAmount($m[4]), 2, '.', ''),
        );
    }
}

if (!function_exists('jtImpExtractSingleValue')) {
    function jtImpExtractSingleValue($text, $pattern)
    {
        if (preg_match($pattern, $text, $m)) {
            return trim((string) $m[1]);
        }
        return '';
    }
}

if (!function_exists('jtImpBuildRecordFromText')) {
    function jtImpBuildRecordFromText($text, $sourceFile = '', $sourceAttachment = '')
    {
        $text = str_replace("\r", "\n", (string) $text);
        $text = preg_replace('/\n+/', "\n", $text);
        $lines = preg_split('/\n/', $text);

        $invNo = '';
        foreach ($lines as $line) {
            $line = preg_replace('/\s+/', ' ', trim((string) $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/invoice[ \t]*(?:no\.?|number)[ \t]*[:#\-]?[ \t]*([A-Za-z0-9][A-Za-z0-9\-\/]{3,})/i', $line, $mInv)) {
                $candidateInv = trim((string) $mInv[1]);
                if (preg_match('/\d/', $candidateInv)) {
                    $invNo = $candidateInv;
                    break;
                }
            }
        }

        if ($invNo === '') {
            $invNo = jtImpExtractSingleValue($text, '/invoice[ \t]*(?:no\.?|number)[ \t]*[:#\-]?[ \t]*((?=[A-Za-z0-9\-\/]{4,}$)[A-Za-z0-9][A-Za-z0-9\-\/]*\d[A-Za-z0-9\-\/]*)/im');
        }

        $rawDate = '';
        foreach ($lines as $line) {
            $line = preg_replace('/\s+/', ' ', trim((string) $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/invoice[ \t]*date[ \t]*[:#\-]?[ \t]*([0-9]{4}[\.\/\-][0-9]{1,2}[\.\/\-][0-9]{1,2}|[0-9]{1,2}[\.\/\-][0-9]{1,2}[\.\/\-][0-9]{2,4})/i', $line, $mDate)) {
                $rawDate = trim((string) $mDate[1]);
                break;
            }
        }

        if ($rawDate === '') {
            $rawDate = jtImpExtractSingleValue($text, '/(?:invoice[ \t]*date|date)[ \t]*[:#\-]?[ \t]*([0-9]{4}[\.\/\-][0-9]{1,2}[\.\/\-][0-9]{1,2}|[0-9]{1,2}[\.\/\-][0-9]{1,2}[\.\/\-][0-9]{2,4})/i');
        }

        $invDate = jtImpNormalizeDate($rawDate);

        $currency = strtoupper(jtImpExtractSingleValue($text, '/(?:currency|invoice\s*currency)\s*[:#]?\s*([A-Za-z]{3})/i'));

        $deliveryRows = array();
        $gstRows = array();
        $totalGst = '';
        $totalAmount = '';

        foreach ($lines as $line) {
            $line = preg_replace('/\s+/', ' ', trim((string) $line));
            if ($line === '') {
                continue;
            }

            $delivery = jtImpParseDeliveryLine($line);
            if (is_array($delivery)) {
                $deliveryRows[] = $delivery;
                continue;
            }

            $gst = jtImpParseGstLine($line);
            if (is_array($gst)) {
                $gstRows[] = $gst;
                continue;
            }

            if ($totalGst === '' && preg_match('/total\s+of\s+gst[^0-9\-]*([0-9]+(?:\.[0-9]+)?)/i', $line, $mGst)) {
                $totalGst = number_format(jtImpToAmount($mGst[1]), 2, '.', '');
            }

            if ($totalAmount === '' && preg_match('/total\s+amount\s+payable[^0-9\-]*([0-9]+(?:\.[0-9]+)?)/i', $line, $mAmt)) {
                $totalAmount = number_format(jtImpToAmount($mAmt[1]), 2, '.', '');
            }
        }

        if (count($deliveryRows) === 0) {
            $deliveryRows[] = array(
                'service_type' => '',
                'shipments_count' => '',
                'total_weight_kg' => '',
                'standard_charge' => '0.00',
                'extra_charges' => '0.00',
                'nett_charge' => '0.00',
            );
        }

        if (count($gstRows) === 0) {
            $gstRows[] = array(
                'gst_type' => '',
                'gst_rate' => '0.00',
                'gst_amount' => '0.00',
                'gst_paid' => '0.00',
            );
        }

        if ($totalGst === '' || $totalAmount === '') {
            $computedTotalGst = 0.0;
            foreach ($gstRows as $gIdx => $gstRow) {
                $rate = isset($gstRow['gst_rate']) ? jtImpToAmount($gstRow['gst_rate']) : 0.0;
                $amount = isset($gstRow['gst_amount']) ? jtImpToAmount($gstRow['gst_amount']) : 0.0;
                $paid = ($rate > 0) ? ($amount * ($rate / 100)) : jtImpToAmount(isset($gstRow['gst_paid']) ? $gstRow['gst_paid'] : 0);
                $gstRows[$gIdx]['gst_paid'] = number_format($paid, 2, '.', '');
                $computedTotalGst += $paid;
            }

            $computedTotalAmount = 0.0;
            foreach ($deliveryRows as $dIdx => $deliveryRow) {
                $standardCharge = isset($deliveryRow['standard_charge']) ? jtImpToAmount($deliveryRow['standard_charge']) : 0.0;
                $rowGstPaid = isset($gstRows[$dIdx]) ? jtImpToAmount($gstRows[$dIdx]['gst_paid']) : 0.0;
                $nettCharge = $standardCharge + $rowGstPaid;
                $deliveryRows[$dIdx]['nett_charge'] = number_format($nettCharge, 2, '.', '');
                $computedTotalAmount += $nettCharge;
            }

            if ($totalGst === '') {
                $totalGst = number_format($computedTotalGst, 2, '.', '');
            }
            if ($totalAmount === '') {
                $totalAmount = number_format($computedTotalAmount, 2, '.', '');
            }
        }

        return array(
            'source_file' => (string) $sourceFile,
            'source_attachment' => (string) $sourceAttachment,
            'jt_inv_number' => (string) $invNo,
            'jt_inv_date' => (string) $invDate,
            'currency' => (string) $currency,
            'total_gst' => (string) ($totalGst === '' ? '0.00' : $totalGst),
            'total_amount' => (string) ($totalAmount === '' ? '0.00' : $totalAmount),
            'delivery' => $deliveryRows,
            'gst' => $gstRows,
        );
    }
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($action === 'cancelImport') {
        unset($_SESSION['jt_backup_import_preview']);
        echo "<script>location.href='" . $importPage . "';</script>";
        exit;
    }

    if ($action === 'parseJtBackupPdf') {
        $clientOcrText = isset($_POST['client_ocr_text']) ? trim((string) $_POST['client_ocr_text']) : '';
        $clientOcrMapJson = isset($_POST['client_ocr_map']) ? trim((string) $_POST['client_ocr_map']) : '';

        if (!isset($_FILES['import_file']) || (int) $_FILES['import_file']['size'] <= 0) {
            $importErrors[] = 'Please select a PDF or ZIP file.';
        } else {
            $sourceAttachmentMap = jtImpBuildSourceAttachmentMap($_FILES['import_file'], basename(__FILE__, '.php'), $importWarnings);
            $fileName = isset($_FILES['import_file']['name']) ? (string) $_FILES['import_file']['name'] : '';
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $records = array();

            if ($ext === 'pdf') {
                if ($clientOcrText === '') {
                    $importErrors[] = 'No OCR text found for the selected PDF.';
                } else {
                    $lookupKey = strtolower((string) basename($fileName));
                    $sourceAttachment = isset($sourceAttachmentMap[$lookupKey]) ? (string) $sourceAttachmentMap[$lookupKey] : '';
                    $records[] = jtImpBuildRecordFromText($clientOcrText, $fileName, $sourceAttachment);
                }
            } elseif ($ext === 'zip') {
                $decodedMap = json_decode($clientOcrMapJson, true);
                if (!is_array($decodedMap) || count($decodedMap) === 0) {
                    $importErrors[] = 'No OCR map found for the uploaded ZIP. Please re-select the ZIP file.';
                } else {
                    $seenSource = array();
                    foreach ($decodedMap as $mapKey => $ocrText) {
                        $key = strtolower(trim((string) $mapKey));
                        if ($key === '' || stripos($key, '.pdf') === false) {
                            continue;
                        }

                        $base = basename(str_replace('\\', '/', $key));
                        if (isset($seenSource[$base])) {
                            continue;
                        }

                        $seenSource[$base] = true;
                        $text = trim((string) $ocrText);
                        if ($text === '') {
                            $importWarnings[] = 'Skipped empty OCR text for ' . $base . '.';
                            continue;
                        }

                        $lookupKey = strtolower($base);
                        $sourceAttachment = isset($sourceAttachmentMap[$lookupKey]) ? (string) $sourceAttachmentMap[$lookupKey] : '';
                        $records[] = jtImpBuildRecordFromText($text, $base, $sourceAttachment);
                    }

                    if (count($records) === 0) {
                        $importErrors[] = 'No readable PDF records were extracted from ZIP OCR output.';
                    }
                }
            } else {
                $importErrors[] = 'Invalid file type. Please upload PDF or ZIP only.';
            }

            if (count($importErrors) === 0) {
                $_SESSION['jt_backup_import_preview'] = array(
                    'records' => $records,
                    'summary' => array(
                        'file_count' => count($records),
                        'record_count' => count($records),
                    ),
                );
                echo "<script>location.href='" . $importPage . "';</script>";
                exit;
            }
        }
    }

    if ($action === 'insertJtBackupPdf') {
        $postedRecords = isset($_POST['records']) && is_array($_POST['records']) ? $_POST['records'] : array();
        if (count($postedRecords) === 0) {
            $importErrors[] = 'No preview records available for import.';
        }

        foreach ($postedRecords as $recordIdx => $record) {
            $currency = strtoupper(trim((string) (isset($record['currency']) ? $record['currency'] : '')));
            $postedRecords[$recordIdx]['currency'] = $currency;

            if ($currency === '') {
                $inlineCurrencyErrors[$recordIdx] = 'Invoice Currency is required.';
            } elseif (!isset($currencyOptionsMap[$currency])) {
                $inlineCurrencyErrors[$recordIdx] = 'Invalid Invoice Currency. Please select a valid currency from the list.';
            }
        }

        if (count($inlineCurrencyErrors) > 0) {
            $importErrors[] = 'Please fix highlighted currency field(s).';
        }

        if (count($importErrors) === 0) {
            mysqli_begin_transaction($finance_connect);
            try {
                $inserted = 0;
                $importedIds = array(); // Added to track IDs for the audit log

                foreach ($postedRecords as $record) {
                    $invNo = trim((string) (isset($record['jt_inv_number']) ? $record['jt_inv_number'] : ''));
                    $invDate = jtImpNormalizeDate(isset($record['jt_inv_date']) ? $record['jt_inv_date'] : '');
                    $currency = strtoupper(trim((string) (isset($record['currency']) ? $record['currency'] : '')));
                    $sourceAttachment = trim((string) (isset($record['source_attachment']) ? $record['source_attachment'] : ''));
                    $deliveryRows = isset($record['delivery']) && is_array($record['delivery']) ? $record['delivery'] : array();
                    $gstRows = isset($record['gst']) && is_array($record['gst']) ? $record['gst'] : array();

                    if ($invNo === '') {
                        throw new Exception('Invoice Number is required for all records.');
                    }
                    if ($invDate === '') {
                        throw new Exception('Invoice Date is invalid or missing for Invoice ' . $invNo . '.');
                    }
                    if ($currency === '' || !isset($currencyOptionsMap[$currency])) {
                        throw new Exception('Invoice Currency is invalid for Invoice ' . $invNo . '.');
                    }

                    $safeInvNo = mysqli_real_escape_string($finance_connect, $invNo);
                    $safeInvDate = mysqli_real_escape_string($finance_connect, $invDate);
                    $safeAttachment = mysqli_real_escape_string($finance_connect, $sourceAttachment);
                    $dupSql = "SELECT id FROM `" . $tblName . "` WHERE number='" . $safeInvNo . "' AND date='" . $safeInvDate . "' AND status='A' LIMIT 1";
                    $dupRst = mysqli_query($finance_connect, $dupSql);
                    if ($dupRst && mysqli_num_rows($dupRst) > 0) {
                        throw new Exception('Duplicate record found for Invoice Number ' . $invNo . ' and date ' . $invDate . '.');
                    }

                    $computedTotalGst = 0.0;
                    foreach ($gstRows as $gIdx => $gstRow) {
                        $rate = isset($gstRow['gst_rate']) ? jtImpToAmount($gstRow['gst_rate']) : 0.0;
                        $amount = isset($gstRow['gst_amount']) ? jtImpToAmount($gstRow['gst_amount']) : 0.0;
                        $gstPaid = ($rate > 0) ? ($amount * ($rate / 100)) : jtImpToAmount(isset($gstRow['gst_paid']) ? $gstRow['gst_paid'] : 0);
                        $gstRows[$gIdx]['gst_paid'] = number_format($gstPaid, 2, '.', '');
                        $computedTotalGst += $gstPaid;
                    }

                    $computedTotalAmount = 0.0;
                    foreach ($deliveryRows as $dIdx => $deliveryRow) {
                        $standardCharge = isset($deliveryRow['standard_charge']) ? jtImpToAmount($deliveryRow['standard_charge']) : 0.0;
                        $rowGstPaid = isset($gstRows[$dIdx]) ? jtImpToAmount($gstRows[$dIdx]['gst_paid']) : 0.0;
                        $nettCharge = $standardCharge + $rowGstPaid;
                        $deliveryRows[$dIdx]['nett_charge'] = number_format($nettCharge, 2, '.', '');
                        $computedTotalAmount += $nettCharge;
                    }

                    $safeCurrency = mysqli_real_escape_string($finance_connect, $currency);
                    $qMain = "INSERT INTO `" . $tblName . "` (number, date, attachment, currency, total_gst, total_amount, create_by, create_date, create_time, status) VALUES ('" . $safeInvNo . "', '" . $safeInvDate . "', '" . $safeAttachment . "', '" . $safeCurrency . "', '" . number_format($computedTotalGst, 2, '.', '') . "', '" . number_format($computedTotalAmount, 2, '.', '') . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                    if (!mysqli_query($finance_connect, $qMain)) {
                        throw new Exception('Failed to insert transaction header: ' . mysqli_error($finance_connect));
                    }

                    $transactionId = (int) mysqli_insert_id($finance_connect);

                    foreach ($deliveryRows as $deliveryRow) {
                        $serviceType = isset($deliveryRow['service_type']) ? trim((string) $deliveryRow['service_type']) : '';
                        if ($serviceType === '') {
                            continue;
                        }

                        $shipmentsCount = isset($deliveryRow['shipments_count']) ? (int) $deliveryRow['shipments_count'] : 0;
                        $totalWeightKg = isset($deliveryRow['total_weight_kg']) ? jtImpToAmount($deliveryRow['total_weight_kg']) : 0;
                        $standardCharge = isset($deliveryRow['standard_charge']) ? jtImpToAmount($deliveryRow['standard_charge']) : 0;
                        $extraCharges = isset($deliveryRow['extra_charges']) ? jtImpToAmount($deliveryRow['extra_charges']) : 0;
                        $nettCharge = isset($deliveryRow['nett_charge']) ? jtImpToAmount($deliveryRow['nett_charge']) : 0;

                        $safeServiceType = mysqli_real_escape_string($finance_connect, $serviceType);
                        $qItem = "INSERT INTO `" . $itemTable . "` (transaction_id, service_type, shipments_count, total_weight_kg, standard_charge, extra_charges, nett_charge) VALUES ('" . $transactionId . "', '" . $safeServiceType . "', '" . $shipmentsCount . "', '" . $totalWeightKg . "', '" . $standardCharge . "', '" . $extraCharges . "', '" . $nettCharge . "')";
                        if (!mysqli_query($finance_connect, $qItem)) {
                            throw new Exception('Failed to insert delivery row: ' . mysqli_error($finance_connect));
                        }
                    }

                    foreach ($gstRows as $gstRow) {
                        $gstType = isset($gstRow['gst_type']) ? trim((string) $gstRow['gst_type']) : '';
                        if ($gstType === '') {
                            continue;
                        }

                        $gstRate = isset($gstRow['gst_rate']) ? jtImpToAmount($gstRow['gst_rate']) : 0;
                        $gstAmount = isset($gstRow['gst_amount']) ? jtImpToAmount($gstRow['gst_amount']) : 0;
                        $gstPaid = isset($gstRow['gst_paid']) ? jtImpToAmount($gstRow['gst_paid']) : 0;

                        $safeGstType = mysqli_real_escape_string($finance_connect, $gstType);
                        $qGst = "INSERT INTO `" . $gstTable . "` (transaction_id, type, rate, amount, gst_paid) VALUES ('" . $transactionId . "', '" . $safeGstType . "', '" . $gstRate . "', '" . $gstAmount . "', '" . $gstPaid . "')";
                        if (!mysqli_query($finance_connect, $qGst)) {
                            throw new Exception('Failed to insert GST row: ' . mysqli_error($finance_connect));
                        }
                    }

                    $importedIds[] = $transactionId; // Capture the inserted ID
                    $inserted++;
                }

                mysqli_commit($finance_connect);
                
                // Add the audit log if at least one record was inserted
                if ($inserted > 0) {
                    $log = [
                        'log_act' => 'import',
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => implode(', ', $importedIds),
                        'query_table' => $tblName,
                        'act_msg' => USER_NAME . " imported " . $inserted . " J&T transaction(s) under <b><i>$tblName Table</i></b>.",
                        'page' => $pageTitle,
                        'connect' => $connect,
                    ];
                    audit_log($log);
                }

                unset($_SESSION['jt_backup_import_preview']);
                echo "<script>alert('Imported " . $inserted . " transaction(s) successfully.');location.href='" . $tablePage . "';</script>";
                exit;
            } catch (Exception $ex) {
                mysqli_rollback($finance_connect);
                $importErrors[] = $ex->getMessage();
            }
        }

        if (count($importErrors) > 0) {
            $_SESSION['jt_backup_import_preview'] = array(
                'records' => $postedRecords,
                'summary' => array(
                    'file_count' => count($postedRecords),
                    'record_count' => count($postedRecords),
                ),
            );
        }
    }
}

$previewBundle = isset($_SESSION['jt_backup_import_preview']) ? $_SESSION['jt_backup_import_preview'] : null;
$previewRecords = ($previewBundle && isset($previewBundle['records']) && is_array($previewBundle['records'])) ? $previewBundle['records'] : array();
$previewSummary = ($previewBundle && isset($previewBundle['summary']) && is_array($previewBundle['summary'])) ? $previewBundle['summary'] : array('file_count' => 0, 'record_count' => 0);
?>
<!DOCTYPE html>
<html>

<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <script src="/finance/header/js/pdf.min.js"></script>
    <script src="/finance/header/js/tesseract.min.js"></script>
    <script src="/finance/header/js/jszip.min.js"></script>
    <style>
        .jt-import .record-card {
            border: 1px solid #d1d5db;
            border-radius: .5rem;
            background: #fff;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .jt-import .jt-auto-calc-field {
            background-color: #e9ecef;
        }

        .jt-import .jt-service-type-col {
            min-width: 280px;
            width: 280px;
        }

        .jt-import .jt-service-type-input {
            min-width: 260px;
        }

        .jt-import .jt-gst-type-col {
            min-width: 180px;
            width: 180px;
        }

        .jt-import .jt-gst-num-col {
            min-width: 130px;
        }

        .jt-import .jt-gst-type-input {
            min-width: 160px;
        }

        .jt-import .jt-gst-num-input {
            min-width: 110px;
        }

        @media (max-width: 767.98px) {
            .jt-import .preview-gst-table {
                min-width: 700px;
            }

            .jt-import .preview-gst-table th,
            .jt-import .preview-gst-table td {
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>
        
    <div class="page-load-cover">
        <div class="container-fluid mt-3 mb-5 d-flex justify-content-center jt-import">
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
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $tablePage ?>">Back To Table</a>
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shortcutPage ?>">Back To Shortcuts</a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($importErrors)) { ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($importErrors as $error) { ?>
                            <div><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if (!empty($importWarnings)) { ?>
                    <div class="alert alert-warning" role="alert">
                        <?php foreach ($importWarnings as $warning) { ?>
                            <div><?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 1: Upload J&T PDF / ZIP</h5>
                        <form method="post" enctype="multipart/form-data" autocomplete="off" id="jtUploadForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-bold" for="import_file">Select PDF or ZIP File</label>
                                    <input class="form-control form-control-lg" type="file" name="import_file" id="import_file" accept=".pdf,.zip" required>
                                    <input type="hidden" name="client_ocr_text" id="client_ocr_text" value="">
                                    <input type="hidden" name="client_ocr_map" id="client_ocr_map" value="">
                                </div>
                                <div class="col-12 col-md-4">
                                    <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseJtBackupPdf" id="jtSubmitBtn">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($previewRecords)) { ?>
                    <div class="alert alert-info" role="alert">
                        <strong>Preview ready:</strong>
                        <?= (int) $previewSummary['record_count'] ?> record(s) extracted from <?= (int) $previewSummary['file_count'] ?> file(s).
                    </div>

                    <form method="post" autocomplete="off" id="jtImportPreviewForm">
                        <?php foreach ($previewRecords as $recordIdx => $record) {
                            $delivery = isset($record['delivery']) && is_array($record['delivery']) ? $record['delivery'] : array();
                            $gst = isset($record['gst']) && is_array($record['gst']) ? $record['gst'] : array();
                            if (count($delivery) === 0) {
                                $delivery[] = array('service_type' => '', 'shipments_count' => '', 'total_weight_kg' => '', 'standard_charge' => '0.00', 'extra_charges' => '0.00', 'nett_charge' => '0.00');
                            }
                            if (count($gst) === 0) {
                                $gst[] = array('gst_type' => '', 'gst_rate' => '0.00', 'gst_amount' => '0.00', 'gst_paid' => '0.00');
                            }
                        ?>
                            <div class="record-card" data-record-idx="<?= (int) $recordIdx ?>">
                                <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
                                    <h5 class="mb-0">Record #<?= (int) ($recordIdx + 1) ?></h5>
                                    <small class="text-muted">Source: <?= htmlspecialchars((string) (isset($record['source_file']) ? $record['source_file'] : ''), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>

                                <input type="hidden" name="records[<?= (int) $recordIdx ?>][source_file]" value="<?= htmlspecialchars((string) (isset($record['source_file']) ? $record['source_file'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="records[<?= (int) $recordIdx ?>][source_attachment]" value="<?= htmlspecialchars((string) (isset($record['source_attachment']) ? $record['source_attachment'] : ''), ENT_QUOTES, 'UTF-8') ?>">

                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="form-label">Invoice Number<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" name="records[<?= (int) $recordIdx ?>][jt_inv_number]" value="<?= htmlspecialchars((string) (isset($record['jt_inv_number']) ? $record['jt_inv_number'] : ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label class="form-label">Invoice Date<span class="requireRed">*</span></label>
                                        <input class="form-control" type="date" name="records[<?= (int) $recordIdx ?>][jt_inv_date]" value="<?= htmlspecialchars((string) (isset($record['jt_inv_date']) ? $record['jt_inv_date'] : ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-4 mb-3 autocomplete">
                                        <label class="form-label">Invoice Currency<span class="requireRed">*</span></label>
                                        <?php
                                        $selectedCurrency = isset($record['currency']) ? strtoupper((string) $record['currency']) : '';
                                        ?>
                                        <input class="form-control js-import-currency" id="jt_import_currency_<?= (int) $recordIdx ?>" type="text" list="currencyOptionsList" name="records[<?= (int) $recordIdx ?>][currency]" value="<?= htmlspecialchars($selectedCurrency, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off" onkeyup="jtImportCurrencySearch(this)">
                                        <input type="hidden" id="jt_import_currency_hidden_<?= (int) $recordIdx ?>" value="">
                                        <?php if (isset($inlineCurrencyErrors[$recordIdx])) { ?>
                                            <div id="err_msg">
                                                <span class="mt-n1"><?= htmlspecialchars((string) $inlineCurrencyErrors[$recordIdx], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label class="form-label">Total GST</label>
                                        <input class="form-control jt-auto-calc-field preview-total-gst" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][total_gst]" value="<?= htmlspecialchars((string) (isset($record['total_gst']) ? $record['total_gst'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                                    </div>
                                    <div class="col-12 col-md-4 mb-3">
                                        <label class="form-label">Total Amount Payable</label>
                                        <input class="form-control jt-auto-calc-field preview-total-amount" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][total_amount]" value="<?= htmlspecialchars((string) (isset($record['total_amount']) ? $record['total_amount'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label">Delivery Services</label>
                                            <div class="table-responsive mb-2">
                                                <table class="table table-striped preview-delivery-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col" width="60">#</th>
                                                            <th class="jt-service-type-col">Service Type</th>
                                                            <th>Number of Shipments</th>
                                                            <th>Total Weight in Kgs</th>
                                                            <th>Standard Shipment Charge</th>
                                                            <th>Extra Charges</th>
                                                            <th>Nett Charge</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($delivery as $dIdx => $deliveryRow) { ?>
                                                        <tr>
                                                            <td><?= (int) ($dIdx + 1) ?></td>
                                                            <td><input class="form-control jt-service-type-input" type="text" name="records[<?= (int) $recordIdx ?>][delivery][<?= (int) $dIdx ?>][service_type]" value="<?= htmlspecialchars((string) (isset($deliveryRow['service_type']) ? $deliveryRow['service_type'] : ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control" type="number" name="records[<?= (int) $recordIdx ?>][delivery][<?= (int) $dIdx ?>][shipments_count]" value="<?= htmlspecialchars((string) (isset($deliveryRow['shipments_count']) ? $deliveryRow['shipments_count'] : ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][delivery][<?= (int) $dIdx ?>][total_weight_kg]" value="<?= htmlspecialchars((string) (isset($deliveryRow['total_weight_kg']) ? $deliveryRow['total_weight_kg'] : ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control preview-standard-charge" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][delivery][<?= (int) $dIdx ?>][standard_charge]" value="<?= htmlspecialchars((string) (isset($deliveryRow['standard_charge']) ? $deliveryRow['standard_charge'] : ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][delivery][<?= (int) $dIdx ?>][extra_charges]" value="<?= htmlspecialchars((string) (isset($deliveryRow['extra_charges']) ? $deliveryRow['extra_charges'] : ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control jt-auto-calc-field preview-nett-charge" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][delivery][<?= (int) $dIdx ?>][nett_charge]" value="<?= htmlspecialchars((string) (isset($deliveryRow['nett_charge']) ? $deliveryRow['nett_charge'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>" readonly></td>
                                                        </tr>
                                                    <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label class="form-label">Analysis of GST</label>
                                            <div class="table-responsive mb-2">
                                                <table class="table table-striped preview-gst-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col" width="60">#</th>
                                                            <th class="jt-gst-type-col">Type</th>
                                                            <th class="jt-gst-num-col">Rate</th>
                                                            <th class="jt-gst-num-col">Amount</th>
                                                            <th class="jt-gst-num-col">GST Paid</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($gst as $gIdx => $gstRow) { ?>
                                                        <tr>
                                                            <td><?= (int) ($gIdx + 1) ?></td>
                                                            <td><input class="form-control jt-gst-type-input" type="text" name="records[<?= (int) $recordIdx ?>][gst][<?= (int) $gIdx ?>][gst_type]" value="<?= htmlspecialchars((string) (isset($gstRow['gst_type']) ? $gstRow['gst_type'] : ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control jt-gst-num-input preview-gst-rate" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][gst][<?= (int) $gIdx ?>][gst_rate]" value="<?= htmlspecialchars((string) (isset($gstRow['gst_rate']) ? $gstRow['gst_rate'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control jt-gst-num-input preview-gst-amount" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][gst][<?= (int) $gIdx ?>][gst_amount]" value="<?= htmlspecialchars((string) (isset($gstRow['gst_amount']) ? $gstRow['gst_amount'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>"></td>
                                                            <td><input class="form-control jt-gst-num-input jt-auto-calc-field preview-gst-paid" type="number" step="0.01" name="records[<?= (int) $recordIdx ?>][gst][<?= (int) $gIdx ?>][gst_paid]" value="<?= htmlspecialchars((string) (isset($gstRow['gst_paid']) ? $gstRow['gst_paid'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>" readonly></td>
                                                        </tr>
                                                    <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <datalist id="currencyOptionsList">
                            <?php foreach ($currencyOptions as $currencyOption) { ?>
                                <option value="<?= htmlspecialchars((string) $currencyOption, ENT_QUOTES, 'UTF-8') ?>"></option>
                            <?php } ?>
                        </datalist>

                        <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                            <button class="btn btn-lg btn-rounded btn-secondary px-4" type="submit" name="actionBtn" value="cancelImport">Cancel</button>
                            <button class="btn btn-lg btn-rounded btn-success px-4" type="submit" name="actionBtn" value="insertJtBackupPdf">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Import Transactions
                            </button>
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        setButtonColor();
        preloader(300, '');
        <?php include "../js/j&t_trans_backup_import.js"; ?>
    </script>
</body>

</html>
