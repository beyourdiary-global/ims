<?php
$pageTitle = 'Stock Order Request PDF Import';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

sorEnsureSchema($finance_connect);

$permissionPage = 'Stock Order Request';
$pinAccess = checkPin($connect, $permissionPage);
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $pinAccess = checkPin($connect, 'Stock List');
}

$tablePage = $SITEURL . '/finance/stock_order_request_table.php';
$shortcutPage = $SITEURL . '/common_import.php';

$warehouses = array();
$warehouseRst = mysqli_query($connect, "SELECT id, name FROM " . WHSE . " WHERE status='A' ORDER BY name ASC");
if ($warehouseRst) {
    while ($w = mysqli_fetch_assoc($warehouseRst)) {
        $warehouses[] = array('id' => (int) $w['id'], 'name' => (string) $w['name']);
    }
}

$warehouseNameMap = array();
foreach ($warehouses as $w) {
    $warehouseNameMap[(int) $w['id']] = (string) $w['name'];
}

$brands = array();
$brandRst = mysqli_query($connect, "SELECT id, name FROM " . BRAND . " WHERE status='A' ORDER BY name ASC");
if ($brandRst) {
    while ($b = mysqli_fetch_assoc($brandRst)) {
        $brands[(int) $b['id']] = (string) $b['name'];
    }
}

$products = array();
$productNameMap = array();
$productNameToId = array();
$prodRst = mysqli_query($connect, "SELECT id, name FROM " . PROD . " WHERE status='A' ORDER BY name ASC");
if ($prodRst) {
    while ($p = mysqli_fetch_assoc($prodRst)) {
        $pid = (int) $p['id'];
        $pname = (string) $p['name'];
        $products[$pid] = $pname;
        $productNameMap[$pid] = $pname;
        // Function sorImpLookup is declared later; use safe inline normalization here.
        $productNameToId[strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($pname)))] = $pid;
    }
}

$packages = array();
$packageOptions = array();
$pkgRst = mysqli_query($connect, "SELECT id, name, item_description, product, brand, price FROM " . PKG . " WHERE status='A' ORDER BY name ASC");
if ($pkgRst) {
    while ($p = mysqli_fetch_assoc($pkgRst)) {
        $productIds = array();
        $productCsv = isset($p['product']) ? trim((string) $p['product']) : '';
        if ($productCsv !== '') {
            foreach (explode(',', $productCsv) as $raw) {
                $pid = (int) trim((string) $raw);
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }
        }

        $packages[] = array(
            'id' => (int) $p['id'],
            'name' => (string) $p['name'],
            'item_description' => (string) $p['item_description'],
            'product_ids' => array_values(array_unique($productIds)),
            'brand_id' => isset($p['brand']) ? (int) $p['brand'] : 0,
            'price' => isset($p['price']) ? (float) $p['price'] : 0,
        );

        $packageOptions[(int) $p['id']] = (string) $p['name'];
    }
}

$packageMap = array();
foreach ($packages as $pkg) {
    $packageMap[(int) $pkg['id']] = $pkg;
}

if (!function_exists('sorImpNorm')) {
    function sorImpNorm($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('sorImpLookup')) {
    function sorImpLookup($text)
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', sorImpNorm($text)));
    }
}

if (!function_exists('sorImpDecodePdfStream')) {
    function sorImpDecodePdfStream($stream)
    {
        $decoded = @gzuncompress($stream);
        if ($decoded !== false) return $decoded;

        $decoded = @gzinflate($stream);
        if ($decoded !== false) return $decoded;

        if (strlen($stream) > 6) {
            $decoded = @gzinflate(substr($stream, 2));
            if ($decoded !== false) return $decoded;
        }

        // Fallback to raw stream for PDFs that store plain text without compression.
        return $stream;
    }
}

if (!function_exists('sorImpCleanPdfTextOperand')) {
    function sorImpCleanPdfTextOperand($text)
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
        return sorImpNorm(preg_replace('/[^[:print:] ]/', ' ', $text));
    }
}

if (!function_exists('sorImpDecodePdfHexString')) {
    function sorImpDecodePdfHexString($hex)
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', (string) $hex);
        if ($hex === '') return '';
        if (strlen($hex) % 2 !== 0) $hex .= '0';

        $bin = @hex2bin($hex);
        if ($bin === false || $bin === '') return '';

        // Common for generated PDFs: UTF-16BE text chunks.
        if (strlen($bin) >= 2) {
            $bom = substr($bin, 0, 2);
            if ($bom === "\xFE\xFF" || $bom === "\xFF\xFE") {
                if (function_exists('mb_convert_encoding')) {
                    $converted = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16');
                    if ($converted !== false && $converted !== '') return sorImpNorm($converted);
                }
            }

            // Heuristic UTF-16BE without BOM.
            if (strpos($bin, "\x00") !== false) {
                if (function_exists('mb_convert_encoding')) {
                    $convertedBe = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
                    if ($convertedBe !== false && $convertedBe !== '') return sorImpNorm($convertedBe);
                }
            }
        }

        return sorImpCleanPdfTextOperand($bin);
    }
}

if (!function_exists('sorImpExtractPdfText')) {
    function sorImpExtractPdfText($pdfContent)
    {
        if ($pdfContent === '') return '';

        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdfContent, $streamMatches);
        $lines = array();

        foreach ($streamMatches[1] as $stream) {
            $decoded = sorImpDecodePdfStream($stream);
            if ($decoded === false) continue;

            if (preg_match_all('/\(([^\)]{1,500})\)\s*Tj/s', $decoded, $textMatches)) {
                foreach ($textMatches[1] as $match) {
                    $line = sorImpCleanPdfTextOperand($match);
                    if ($line !== '') $lines[] = $line;
                }
            }

            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
                foreach ($arrayMatches[1] as $chunk) {
                    preg_match_all('/\(([^\)]*)\)/', $chunk, $inner);
                    $line = sorImpCleanPdfTextOperand(implode('', $inner[1]));
                    if ($line !== '') $lines[] = $line;

                    // Handle hex string arrays in TJ operators: [<0041><0042> -20 <0043>] TJ
                    preg_match_all('/<([0-9A-Fa-f\s]+)>/', $chunk, $hexParts);
                    if (!empty($hexParts[1])) {
                        $hexLine = '';
                        foreach ($hexParts[1] as $hexPart) {
                            $hexLine .= ' ' . sorImpDecodePdfHexString($hexPart);
                        }
                        $hexLine = sorImpNorm($hexLine);
                        if ($hexLine !== '') $lines[] = $hexLine;
                    }
                }
            }

            // Handle standalone hex text operators: <....> Tj
            if (preg_match_all('/<([0-9A-Fa-f\s]{4,})>\s*Tj/s', $decoded, $hexTextMatches)) {
                foreach ($hexTextMatches[1] as $hexText) {
                    $line = sorImpDecodePdfHexString($hexText);
                    if ($line !== '') $lines[] = $line;
                }
            }
        }

        if (count($lines) > 0) {
            return implode("\n", $lines);
        }

        // Last fallback for difficult PDFs: scan printable raw content.
        $raw = preg_replace('/[^[:print:]\r\n\t ]/', ' ', (string) $pdfContent);
        return sorImpNorm($raw);
    }
}

if (!function_exists('sorImpCollectPdfFiles')) {
    function sorImpCollectPdfFiles($upload, &$errors, &$warnings)
    {
        $files = array();
        if (!isset($upload['name']) || !isset($upload['tmp_name'])) {
            $errors[] = 'Invalid upload payload.';
            return $files;
        }

        $originalName = (string) $upload['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $content = @file_get_contents($upload['tmp_name']);
            if ($content === false || $content === '') {
                $errors[] = 'Unable to read uploaded PDF.';
                return $files;
            }

            $files[] = array(
                'name' => basename($originalName),
                'content' => $content,
            );
            return $files;
        }

        if ($ext !== 'zip') {
            $errors[] = 'Only PDF or ZIP files are supported.';
            return $files;
        }

        if (!class_exists('ZipArchive')) {
            $errors[] = 'ZIP import requires ZipArchive support.';
            return $files;
        }

        $zip = new ZipArchive();
        if ($zip->open($upload['tmp_name']) !== true) {
            $errors[] = 'Unable to open ZIP file.';
            return $files;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (substr((string) $entryName, -1) === '/') continue;
            if (strtolower(pathinfo((string) $entryName, PATHINFO_EXTENSION)) !== 'pdf') continue;

            $content = $zip->getFromIndex($i);
            if ($content === false || $content === '') {
                $warnings[] = 'Unable to read PDF entry: ' . $entryName;
                continue;
            }

            $files[] = array(
                'name' => basename((string) $entryName),
                'content' => $content,
            );
        }

        $zip->close();

        if (count($files) === 0) {
            $errors[] = 'No PDF files found in ZIP.';
        }

        return $files;
    }
}

if (!function_exists('sorImpDateToYmd')) {
    function sorImpDateToYmd($text)
    {
        $text = sorImpNorm($text);
        if ($text === '') return '';

        if (preg_match('/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        $ts = strtotime($text);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return '';
    }
}

if (!function_exists('sorImpMoney')) {
    function sorImpMoney($text)
    {
        $text = sorImpNorm($text);
        if ($text === '') return '';
        if (preg_match('/-?[0-9][0-9,]*\.?[0-9]*/', $text, $m)) {
            return number_format((float) str_replace(',', '', $m[0]), 2, '.', '');
        }
        return '';
    }
}

if (!function_exists('sorImpFindInvoiceNo')) {
    function sorImpFindInvoiceNo($text, $fileName)
    {
        if (preg_match('/\bINV[-#]?[A-Z0-9-]{4,}\b/i', $text, $m)) {
            return strtoupper((string) $m[0]);
        }

        if (preg_match('/(?:Invoice\s*(?:No|#|Number)?\s*[:#-]?\s*)([A-Z0-9-]{4,})/i', $text, $m)) {
            return strtoupper(trim((string) $m[1]));
        }

        return strtoupper(pathinfo((string) $fileName, PATHINFO_FILENAME));
    }
}

if (!function_exists('sorImpFindInvoiceDate')) {
    function sorImpFindInvoiceDate($text)
    {
        if (preg_match('/(?:Invoice\s*Date|Date)\s*[:#-]?\s*([0-9A-Za-z\-\/. ,]+)/i', $text, $m)) {
            $d = sorImpDateToYmd($m[1]);
            if ($d !== '') return $d;
        }

        if (preg_match('/\b\d{4}-\d{1,2}-\d{1,2}\b/', $text, $m)) {
            return sorImpDateToYmd($m[0]);
        }

        return '';
    }
}

if (!function_exists('sorImpFindTotalPrice')) {
    function sorImpFindTotalPrice($text)
    {
        if (preg_match('/(?:Grand\s*Total|Total\s*Amount|Total)\s*[:]?\s*(?:RM|MYR|SGD|USD)?\s*([0-9][0-9,]*\.?[0-9]*)/i', $text, $m)) {
            return number_format((float) str_replace(',', '', $m[1]), 2, '.', '');
        }

        if (preg_match_all('/(?:RM|MYR|SGD|USD)\s*([0-9][0-9,]*\.?[0-9]*)/i', $text, $m) && !empty($m[1])) {
            $last = end($m[1]);
            return number_format((float) str_replace(',', '', (string) $last), 2, '.', '');
        }

        return '0.00';
    }
}

if (!function_exists('sorImpFindQuantityNearLine')) {
    function sorImpFindQuantityNearLine($text, $label)
    {
        $label = trim((string) $label);
        if ($label === '') return 0;

        $pattern = '/' . preg_quote($label, '/') . '[^\n]{0,80}(?:qty|quantity|x)\s*[:x]?\s*([0-9]{1,4})/i';
        if (preg_match($pattern, $text, $m)) {
            return max(1, (int) $m[1]);
        }

        $pattern2 = '/' . preg_quote($label, '/') . '[^\n]{0,80}\b([0-9]{1,4})\b\s*(?:pcs|pc|unit|units|x)\b/i';
        if (preg_match($pattern2, $text, $m)) {
            return max(1, (int) $m[1]);
        }

        if (preg_match('/(?:qty|quantity)\s*[:x]?\s*([0-9]{1,4})/i', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        return 0;
    }
}

if (!function_exists('sorImpParsePdfToRows')) {
    function sorImpParsePdfToRows($pdfFile, $packages, $productNameMap)
    {
        $rows = array();
        $warnings = array();

        $text = sorImpExtractPdfText($pdfFile['content']);
        if ($text === '') {
            return array('rows' => array(), 'warnings' => array('Unable to extract text from ' . $pdfFile['name']));
        }

        $invoiceNo = sorImpFindInvoiceNo($text, $pdfFile['name']);
        $invoiceDate = sorImpFindInvoiceDate($text);
        $totalPrice = sorImpFindTotalPrice($text);

        $lookupText = sorImpLookup($text);

        $matched = array();
        $matchedPkgIds = array();
        foreach ($packages as $pkg) {
            $desc = trim((string) $pkg['item_description']);
            $name = trim((string) $pkg['name']);
            $keyDesc = sorImpLookup($desc);
            $keyName = sorImpLookup($name);

            if ($keyDesc !== '' && strpos($lookupText, $keyDesc) !== false) {
                $matched[] = array('pkg' => $pkg, 'label' => $desc !== '' ? $desc : $name, 'score' => strlen($keyDesc));
                $matchedPkgIds[(int) $pkg['id']] = true;
                continue;
            }

            if ($keyName !== '' && strlen($keyName) >= 8 && strpos($lookupText, $keyName) !== false) {
                $matched[] = array('pkg' => $pkg, 'label' => $desc !== '' ? $desc : $name, 'score' => strlen($keyName));
                $matchedPkgIds[(int) $pkg['id']] = true;
            }
        }

        usort($matched, function ($a, $b) {
            return (int) $b['score'] - (int) $a['score'];
        });

        $seenPkg = array();
        foreach ($matched as $m) {
            $pkg = $m['pkg'];
            $pkgId = (int) $pkg['id'];
            if (isset($seenPkg[$pkgId])) continue;
            $seenPkg[$pkgId] = true;

            $packageLabel = (string) $m['label'];
            $packageQty = sorImpFindQuantityNearLine($text, $packageLabel);

            $pkgProductIds = isset($pkg['product_ids']) && is_array($pkg['product_ids']) ? $pkg['product_ids'] : array();
            $pkgProductCount = count($pkgProductIds);

            if ($pkgProductCount === 0) {
                $rows[] = array(
                    'source_file' => (string) $pdfFile['name'],
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $invoiceDate,
                    'total_price' => $totalPrice,
                    'warehouse_id' => '',
                    'product_id' => 0,
                    'product_name' => '',
                    'package_id' => $pkgId,
                    'qty' => max(1, $packageQty),
                    'brand_id' => isset($pkg['brand_id']) ? (int) $pkg['brand_id'] : 0,
                    'company_id' => '',
                    'warning' => 'Package has no linked product in DB. Please update package mapping first.',
                );
                continue;
            }

            foreach ($pkgProductIds as $pid) {
                $pid = (int) $pid;
                $productName = isset($productNameMap[$pid]) ? (string) $productNameMap[$pid] : '';
                $productQty = sorImpFindQuantityNearLine($text, $productName);
                $warning = '';

                if ($productQty <= 0) {
                    if ($packageQty > 0) {
                        $productQty = $packageQty;
                        $warning = 'Product qty not found in PDF. Using package qty: ' . $packageQty . '.';
                    } else {
                        $productQty = 1;
                        $warning = 'Product qty not found in PDF. Package has ' . $pkgProductCount . ' product(s); defaulted to 1 per product.';
                    }
                }

                $rows[] = array(
                    'source_file' => (string) $pdfFile['name'],
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $invoiceDate,
                    'total_price' => $totalPrice,
                    'warehouse_id' => '',
                    'product_id' => $pid,
                    'product_name' => $productName,
                    'package_id' => $pkgId,
                    'qty' => max(1, (int) $productQty),
                    'brand_id' => isset($pkg['brand_id']) ? (int) $pkg['brand_id'] : 0,
                    'company_id' => '',
                    'warning' => $warning,
                );
            }
        }

        // Fallback: when package label is not found, try product-name hit and infer package.
        if (count($rows) === 0) {
            foreach ($packages as $pkg) {
                $pkgId = (int) $pkg['id'];
                $pkgName = trim((string) $pkg['name']);
                $pkgDesc = trim((string) $pkg['item_description']);
                $packageQty = sorImpFindQuantityNearLine($text, $pkgDesc !== '' ? $pkgDesc : $pkgName);

                $pkgProductIds = isset($pkg['product_ids']) && is_array($pkg['product_ids']) ? $pkg['product_ids'] : array();
                $pkgProductCount = count($pkgProductIds);
                if ($pkgProductCount === 0) {
                    continue;
                }

                $hasProductHit = false;
                foreach ($pkgProductIds as $pid) {
                    $pid = (int) $pid;
                    $productName = isset($productNameMap[$pid]) ? (string) $productNameMap[$pid] : '';
                    if ($productName === '') continue;

                    $productLookup = sorImpLookup($productName);
                    if ($productLookup === '' || strpos($lookupText, $productLookup) === false) {
                        continue;
                    }

                    $hasProductHit = true;
                    $productQty = sorImpFindQuantityNearLine($text, $productName);
                    $warning = 'Package inferred from product name match.';

                    if ($productQty <= 0) {
                        if ($packageQty > 0) {
                            $productQty = $packageQty;
                            $warning .= ' Product qty missing, using package qty: ' . $packageQty . '.';
                        } else {
                            $productQty = 1;
                            $warning .= ' Product qty missing, defaulted to 1 (package products: ' . $pkgProductCount . ').';
                        }
                    }

                    $rows[] = array(
                        'source_file' => (string) $pdfFile['name'],
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $invoiceDate,
                        'total_price' => $totalPrice,
                        'warehouse_id' => '',
                        'product_id' => $pid,
                        'product_name' => $productName,
                        'package_id' => $pkgId,
                        'qty' => max(1, (int) $productQty),
                        'brand_id' => isset($pkg['brand_id']) ? (int) $pkg['brand_id'] : 0,
                        'company_id' => '',
                        'warning' => $warning,
                    );
                }

                if ($hasProductHit) {
                    $matchedPkgIds[$pkgId] = true;
                }
            }
        }

        if (count($rows) === 0) {
            $rows[] = array(
                'source_file' => (string) $pdfFile['name'],
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'total_price' => $totalPrice,
                'warehouse_id' => '',
                'product_id' => 0,
                'product_name' => '',
                'package_id' => '',
                'qty' => 1,
                'brand_id' => 0,
                'company_id' => '',
                'warning' => 'Package match failed. Please select package manually.',
            );
        }

        return array('rows' => $rows, 'warnings' => $warnings);
    }
}

$action = post('actionBtn');
$importErrors = array();
$importWarnings = array();

if ($action === 'cancelImport') {
    unset($_SESSION['sor_pdf_import_preview']);
    echo "<script>location.href='" . $tablePage . "';</script>";
    exit;
}

if ($action === 'parseStockOrderPdf') {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please choose a PDF or ZIP file.';
    } else {
        $sourceFiles = sorImpCollectPdfFiles($_FILES['import_file'], $importErrors, $importWarnings);
        $previewRows = array();

        foreach ($sourceFiles as $src) {
            $parsed = sorImpParsePdfToRows($src, $packages, $productNameMap);
            if (!empty($parsed['warnings'])) {
                $importWarnings = array_merge($importWarnings, $parsed['warnings']);
            }
            if (!empty($parsed['rows'])) {
                $previewRows = array_merge($previewRows, $parsed['rows']);
            }
        }

        if (count($previewRows) === 0 && count($importErrors) === 0) {
            $importErrors[] = 'No importable rows found.';
        }

        if (count($previewRows) > 0) {
            $_SESSION['sor_pdf_import_preview'] = array(
                'rows' => $previewRows,
                'summary' => array(
                    'file_count' => count($sourceFiles),
                    'row_count' => count($previewRows),
                ),
            );
        }
    }
}

if ($action === 'insertStockOrderPdf') {
    $postedRows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : array();

    if (count($postedRows) === 0) {
        $importErrors[] = 'No preview rows to insert.';
    } else {
        $grouped = array();

        foreach ($postedRows as $idx => $r) {
            $rowNo = $idx + 1;
            $warehouseId = isset($r['warehouse_id']) ? (int) $r['warehouse_id'] : 0;
            $invoiceNo = trim((string) (isset($r['invoice_no']) ? $r['invoice_no'] : ''));
            $invoiceDate = sorImpDateToYmd(isset($r['invoice_date']) ? $r['invoice_date'] : '');
            $productName = trim((string) (isset($r['product_name']) ? $r['product_name'] : ''));
            $productId = isset($r['product_id']) ? (int) $r['product_id'] : 0;
            $packageId = isset($r['package_id']) ? (int) $r['package_id'] : 0;
            $qty = isset($r['qty']) ? (int) $r['qty'] : 0;
            $totalPrice = isset($r['total_price']) ? (float) $r['total_price'] : 0;

            if ($productId <= 0 && $productName !== '') {
                $key = sorImpLookup($productName);
                if (isset($productNameToId[$key])) {
                    $productId = (int) $productNameToId[$key];
                }
            }

            if ($warehouseId <= 0) $importErrors[] = 'Row #' . $rowNo . ': Warehouse is required.';
            if ($invoiceNo === '') $importErrors[] = 'Row #' . $rowNo . ': Invoice is required.';
            if ($invoiceDate === '') $importErrors[] = 'Row #' . $rowNo . ': Invoice Date is required.';
            if ($packageId <= 0 || !isset($packageMap[$packageId])) $importErrors[] = 'Row #' . $rowNo . ': Valid package is required.';
            if ($productId <= 0) $importErrors[] = 'Row #' . $rowNo . ': Valid product is required.';
            if ($qty <= 0) $importErrors[] = 'Row #' . $rowNo . ': Quantity must be more than 0.';

            if ($packageId > 0 && isset($packageMap[$packageId])) {
                $pkg = $packageMap[$packageId];
                $productIds = isset($pkg['product_ids']) ? $pkg['product_ids'] : array();
                if (is_array($productIds) && count($productIds) > 0 && $productId > 0 && !in_array($productId, $productIds, true)) {
                    $importErrors[] = 'Row #' . $rowNo . ': Selected product does not belong to selected package.';
                }
            }

            $groupKey = $warehouseId . '|' . $invoiceNo . '|' . $invoiceDate;
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = array(
                    'warehouse_id' => $warehouseId,
                    'company_id' => $warehouseId,
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $invoiceDate,
                    'request_date' => $invoiceDate,
                    'total_price' => $totalPrice,
                    'brand_id' => 0,
                    'items' => array(),
                    'source_file' => isset($r['source_file']) ? (string) $r['source_file'] : '',
                );
            }

            if ($totalPrice > 0) {
                $grouped[$groupKey]['total_price'] = $totalPrice;
            }

            if ($packageId > 0 && isset($packageMap[$packageId])) {
                $pkg = $packageMap[$packageId];
                $brandId = (int) $pkg['brand_id'];
                if ($brandId > 0 && (int) $grouped[$groupKey]['brand_id'] <= 0) {
                    $grouped[$groupKey]['brand_id'] = $brandId;
                }
                $grouped[$groupKey]['items'][] = array(
                    'product_id' => $productId,
                    'package_id' => $packageId,
                    'package_desc' => isset($pkg['item_description']) && trim((string) $pkg['item_description']) !== '' ? (string) $pkg['item_description'] : $productName,
                    'qty' => $qty,
                );
            }
        }

        if (count($importErrors) === 0) {
            mysqli_begin_transaction($finance_connect);
            $inserted = 0;

            try {
                foreach ($grouped as $g) {
                    if (!isset($g['items']) || count($g['items']) === 0) {
                        continue;
                    }

                    $requestNo = sorGenerateRequestNo($finance_connect);
                    $safeRequestNo = mysqli_real_escape_string($finance_connect, $requestNo);
                    $safeInvoiceNo = mysqli_real_escape_string($finance_connect, $g['invoice_no']);
                    $safeInvoiceDate = mysqli_real_escape_string($finance_connect, $g['invoice_date']);
                    $safeRequestDate = mysqli_real_escape_string($finance_connect, $g['request_date']);
                    $safeRemark = mysqli_real_escape_string($finance_connect, 'Imported from PDF: ' . $g['source_file']);

                    $qMain = "INSERT INTO " . STOCK_ORDER_REQ . " (request_no, warehouse_id, company_id, brand_id, invoice_no, invoice_date, request_date, request_by, total_price, remark, create_by, create_date, create_time, status) VALUES ('" . $safeRequestNo . "', '" . (int) $g['warehouse_id'] . "', '" . (int) $g['company_id'] . "', '" . (int) $g['brand_id'] . "', '" . $safeInvoiceNo . "', '" . $safeInvoiceDate . "', '" . $safeRequestDate . "', '" . USER_ID . "', '" . number_format((float) $g['total_price'], 2, '.', '') . "', '" . $safeRemark . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";

                    if (!mysqli_query($finance_connect, $qMain)) {
                        throw new Exception('Failed to insert request: ' . mysqli_error($finance_connect));
                    }

                    $requestId = (int) mysqli_insert_id($finance_connect);

                    foreach ($g['items'] as $it) {
                        $safeDesc = mysqli_real_escape_string($finance_connect, (string) $it['package_desc']);
                        $qItem = "INSERT INTO " . STOCK_ORDER_REQ_ITEM . " (request_id, product_id, package_id, package_desc, qty, create_by, create_date, create_time, status) VALUES ('" . $requestId . "', '" . (int) $it['product_id'] . "', '" . (int) $it['package_id'] . "', '" . $safeDesc . "', '" . (int) $it['qty'] . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                        if (!mysqli_query($finance_connect, $qItem)) {
                            throw new Exception('Failed to insert item: ' . mysqli_error($finance_connect));
                        }
                    }

                    $inserted++;
                }

                mysqli_commit($finance_connect);
                unset($_SESSION['sor_pdf_import_preview']);
                echo "<script>alert('Imported " . $inserted . " stock order request(s) successfully.');location.href='" . $tablePage . "';</script>";
                exit;
            } catch (Exception $ex) {
                mysqli_rollback($finance_connect);
                $importErrors[] = $ex->getMessage();
            }
        }
    }
}

$previewBundle = isset($_SESSION['sor_pdf_import_preview']) ? $_SESSION['sor_pdf_import_preview'] : null;
$previewRows = ($previewBundle && isset($previewBundle['rows']) && is_array($previewBundle['rows'])) ? $previewBundle['rows'] : array();
$previewSummary = ($previewBundle && isset($previewBundle['summary']) && is_array($previewBundle['summary'])) ? $previewBundle['summary'] : array('file_count' => 0, 'row_count' => 0);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .sor-import .card { border: 0; box-shadow: 0 .125rem .5rem rgba(0,0,0,.08); }
        .sor-import .preview-table { min-width: 1400px; }
        .sor-import .preview-table thead th { background: #2f2a2a; color: #fff; }
        .sor-import .warn-badge { background: #facc15; color: #111827; border-radius: .4rem; padding: .2rem .5rem; font-size: .75rem; }
        .sor-import .required::after { content: ' *'; color: #dc2626; }
    </style>
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid mt-3 mb-5 d-flex justify-content-center sor-import">
        <div class="col-12 col-md-11">
            <div class="row mb-3">
                <p>
                    <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    <a href="<?= $tablePage ?>">Stock Order Request</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    PDF Import
                </p>
            </div>

            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                    <h2>Stock Order Request PDF Import</h2>
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

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Step 1: Upload PDF Or ZIP</h5>
                    <form method="post" enctype="multipart/form-data">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="import_file">Invoice PDF File (or ZIP for bulk import)</label>
                                <input class="form-control" type="file" name="import_file" id="import_file" accept=".pdf,.zip" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseStockOrderPdf">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($previewRows)) { ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Preview, Edit, And Insert</h5>
                        <p class="mb-2">Detected Files: <strong><?= (int) $previewSummary['file_count'] ?></strong> | Parsed Rows: <strong><?= (int) $previewSummary['row_count'] ?></strong></p>

                        <form method="post">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle preview-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Source File</th>
                                            <th>Invoice</th>
                                            <th>Invoices Date</th>
                                            <th>Warehouse</th>
                                            <th>Product Name</th>
                                            <th>Package</th>
                                            <th>Quantity</th>
                                            <th>Total Price</th>
                                            <th>Brand (ID)</th>
                                            <th>Company (ID)</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($previewRows as $idx => $row) {
                                            $pkgId = isset($row['package_id']) ? (int) $row['package_id'] : 0;
                                            $brandId = ($pkgId > 0 && isset($packageMap[$pkgId])) ? (int) $packageMap[$pkgId]['brand_id'] : (isset($row['brand_id']) ? (int) $row['brand_id'] : 0);
                                            $companyId = isset($row['warehouse_id']) ? (int) $row['warehouse_id'] : 0;
                                        ?>
                                            <tr>
                                                <td><?= (int) ($idx + 1) ?></td>
                                                <td>
                                                    <input type="text" class="form-control" name="rows[<?= $idx ?>][source_file]" value="<?= htmlspecialchars((string) (isset($row['source_file']) ? $row['source_file'] : ''), ENT_QUOTES, 'UTF-8') ?>" readonly>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="rows[<?= $idx ?>][invoice_no]" value="<?= htmlspecialchars((string) (isset($row['invoice_no']) ? $row['invoice_no'] : ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control" name="rows[<?= $idx ?>][invoice_date]" value="<?= htmlspecialchars((string) (isset($row['invoice_date']) ? $row['invoice_date'] : ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                                </td>
                                                <td>
                                                    <select class="form-select sor-wh-select" name="rows[<?= $idx ?>][warehouse_id]" required>
                                                        <option value="">Select Warehouse</option>
                                                        <?php foreach ($warehouses as $w) { ?>
                                                            <option value="<?= (int) $w['id'] ?>" <?= ((int) (isset($row['warehouse_id']) ? $row['warehouse_id'] : 0) === (int) $w['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $w['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="hidden" name="rows[<?= $idx ?>][product_id]" value="<?= (int) (isset($row['product_id']) ? $row['product_id'] : 0) ?>">
                                                    <input type="text" class="form-control" name="rows[<?= $idx ?>][product_name]" value="<?= htmlspecialchars((string) (isset($row['product_name']) ? $row['product_name'] : ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                                </td>
                                                <td>
                                                    <select class="form-select sor-pkg-select" name="rows[<?= $idx ?>][package_id]" data-brand-target="brand_<?= $idx ?>" required>
                                                        <option value="">Select Package</option>
                                                        <?php foreach ($packages as $pkg) { ?>
                                                            <?php
                                                            $optLabel = (string) $pkg['name'];
                                                            if (trim((string) $pkg['item_description']) !== '') {
                                                                $optLabel .= ' - ' . (string) $pkg['item_description'];
                                                            }
                                                            ?>
                                                            <option value="<?= (int) $pkg['id'] ?>" data-brand-id="<?= (int) $pkg['brand_id'] ?>" <?= $pkgId === (int) $pkg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" min="1" name="rows[<?= $idx ?>][qty]" value="<?= (int) (isset($row['qty']) ? $row['qty'] : 1) ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" step="0.01" min="0" name="rows[<?= $idx ?>][total_price]" value="<?= htmlspecialchars((string) (isset($row['total_price']) ? $row['total_price'] : '0.00'), ENT_QUOTES, 'UTF-8') ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" id="brand_<?= $idx ?>" value="<?= $brandId > 0 ? (int) $brandId . ' - ' . htmlspecialchars(isset($brands[$brandId]) ? $brands[$brandId] : '', ENT_QUOTES, 'UTF-8') : '' ?>" readonly>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control sor-company-id" value="<?= $companyId > 0 ? (int) $companyId : '' ?>" readonly>
                                                </td>
                                                <td>
                                                    <?php if (isset($row['warning']) && trim((string) $row['warning']) !== '') { ?>
                                                        <span class="warn-badge"><?= htmlspecialchars((string) $row['warning'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertStockOrderPdf">
                                    <i class="fa-solid fa-database"></i> Insert
                                </button>
                                <button class="btn btn-lg btn-rounded btn-secondary px-4" type="submit" name="actionBtn" value="cancelImport">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    var page = "Stock Order Request";
    var action = "";
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);

    document.querySelectorAll('.sor-pkg-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var targetId = sel.getAttribute('data-brand-target');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;

            var option = sel.options[sel.selectedIndex];
            var brandId = option ? (option.getAttribute('data-brand-id') || '') : '';
            target.value = brandId !== '' ? brandId : '';
        });
    });

    document.querySelectorAll('.sor-wh-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var tr = sel.closest('tr');
            if (!tr) return;
            var companyField = tr.querySelector('.sor-company-id');
            if (companyField) companyField.value = sel.value || '';
        });
    });
</script>
</body>
</html>
