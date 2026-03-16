<?php
$pageTitle = 'Stock Order Request PDF Import';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/header/phpqrcode/qrlib.php';

$permissionPage = 'Stock Order Request';
$pinAccess = checkPin($connect, $permissionPage);
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $pinAccess = checkPin($connect, 'Stock List');
}

$tablePage = $SITEURL . '/finance/stock_order_request_table.php';
$shortcutPage = $SITEURL . '/common_import.php';
$productPage = $SITEURL . '/product.php';
$packagePage = $SITEURL . '/package.php';

// ============================================================
//  DB LOOKUPS
// ============================================================

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
$brandCompanyMap = array();
$brandRst = mysqli_query($connect, "SELECT id, name, company FROM " . BRAND . " WHERE status='A' ORDER BY name ASC");
if ($brandRst) {
    while ($b = mysqli_fetch_assoc($brandRst)) {
        $brandId = (int) $b['id'];
        $brands[$brandId] = (string) $b['name'];
        $brandCompanyMap[$brandId] = isset($b['company']) ? (int) $b['company'] : 0;
    }
}

$companies = array();
$companyRst = mysqli_query($connect, "SELECT id, name FROM " . COMPANY . " WHERE status='A' ORDER BY name ASC");
if ($companyRst) {
    while ($c = mysqli_fetch_assoc($companyRst)) {
        $companies[(int) $c['id']] = (string) $c['name'];
    }
}

$products = array();
$productNameMap = array();
$productNameToId = array();
$productBrandMap = array();
$prodRst = mysqli_query($connect, "SELECT id, name, brand FROM " . PROD . " WHERE status='A' ORDER BY name ASC");
if ($prodRst) {
    while ($p = mysqli_fetch_assoc($prodRst)) {
        $pid = (int) $p['id'];
        $pname = (string) $p['name'];
        $products[$pid] = $pname;
        $productNameMap[$pid] = $pname;
        $productNameToId[strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($pname)))] = $pid;
        if (isset($p['brand']) && (int) $p['brand'] > 0) {
            $productBrandMap[$pid] = (int) $p['brand'];
        }
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

// ============================================================
//  HELPER FUNCTIONS
// ============================================================

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

// ============================================================
//  NATIVE PDF TEXT EXTRACTION  (for text-based PDFs)
// ============================================================

if (!function_exists('sorImpCleanPdfTextOperand')) {
    function sorImpCleanPdfTextOperand($text)
    {
        $text = str_replace("\x00", '', (string) $text);
        $text = strtr($text, array(
            '\\n' => ' ', '\\r' => ' ', '\\t' => ' ',
            '\\(' => '(', '\\)' => ')',
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

        if (strlen($bin) >= 2) {
            $bom = substr($bin, 0, 2);
            if ($bom === "\xFE\xFF" || $bom === "\xFF\xFE") {
                if (function_exists('mb_convert_encoding')) {
                    $converted = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16');
                    if ($converted !== false && $converted !== '') return sorImpNorm($converted);
                }
            }
            if (strpos($bin, "\x00") !== false && function_exists('mb_convert_encoding')) {
                $convertedBe = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
                if ($convertedBe !== false && $convertedBe !== '') return sorImpNorm($convertedBe);
            }
        }
        return sorImpCleanPdfTextOperand($bin);
    }
}

if (!function_exists('sorImpExtractPdfTextNative')) {
    /**
     * Try to extract text from a text-based PDF by parsing stream objects
     * and extracting Tj/TJ text-rendering operators.
     */
    function sorImpExtractPdfTextNative($pdfContent)
    {
        if ($pdfContent === '') return '';
        $lines = array();

        $parts = explode('stream', $pdfContent);
        for ($i = 1; $i < count($parts); $i++) {
            $part = $parts[$i];
            $endPos = strpos($part, 'endstream');
            if ($endPos === false) continue;

            $streamData = substr($part, 0, $endPos);
            $streamData = ltrim($streamData, "\x00..\x20");
            $streamData = rtrim($streamData, "\x00..\x20");

            $decoded = @gzuncompress($streamData);
            if ($decoded === false) $decoded = @gzinflate($streamData);
            if ($decoded === false) $decoded = @gzinflate(substr($streamData, 2));
            if ($decoded === false) $decoded = $streamData;

            if (preg_match_all('/\(([^\)]*)\)\s*(?:Tj|TJ|\'|\")/s', $decoded, $matches)) {
                foreach ($matches[1] as $match) {
                    $lines[] = sorImpCleanPdfTextOperand($match);
                }
            }
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $matches)) {
                foreach ($matches[1] as $chunk) {
                    if (preg_match_all('/\(([^\)]*)\)/', $chunk, $inner)) {
                        $lines[] = sorImpCleanPdfTextOperand(implode('', $inner[1]));
                    }
                    if (preg_match_all('/<([0-9A-Fa-f\s]+)>/', $chunk, $hexParts)) {
                        foreach ($hexParts[1] as $hexPart) {
                            $lines[] = sorImpDecodePdfHexString($hexPart);
                        }
                    }
                }
            }
            if (preg_match_all('/<([0-9A-Fa-f\s]{4,})>\s*(?:Tj|TJ)/s', $decoded, $hexMatches)) {
                foreach ($hexMatches[1] as $hex) {
                    $lines[] = sorImpDecodePdfHexString($hex);
                }
            }
        }

        if (count($lines) > 0) {
            return implode("\n", array_filter($lines));
        }
        return '';
    }
}

// ============================================================
//  TEXT EXTRACTION  (native text + browser OCR text)
// ============================================================

if (!function_exists('sorImpExtractPdfText')) {
    function sorImpExtractPdfText($pdfContent, &$warnings, $clientOcrText = '')
    {
        $text = sorImpExtractPdfTextNative($pdfContent);
        if (preg_replace('/[^a-zA-Z0-9]/', '', $text) !== '') {
            return $text;
        }

        $clientOcrText = trim((string) $clientOcrText);
        if ($clientOcrText !== '' && strlen(preg_replace('/[^a-zA-Z0-9]/', '', $clientOcrText)) > 20) {
            return $clientOcrText;
        }

        $warnings[] = 'Unable to extract text from this PDF. For image-based PDFs, please wait a moment after selecting the file before clicking Load And Analyze.';
        return '';
    }
}

// ============================================================
//  FILE COLLECTION  (single PDF or ZIP of PDFs)
// ============================================================

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
            $files[] = array('name' => basename($originalName), 'content' => $content);
            return $files;
        }

        if ($ext !== 'zip') {
            $errors[] = 'Only PDF or ZIP files are supported.';
            return $files;
        }

        // Try ZipArchive first; fall back to PharData (Phar extension, always available by default)
        $zipOpened = false;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($upload['tmp_name']) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = (string) $zip->getNameIndex($i);
                    if (substr($entryName, -1) === '/') continue;
                    if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') continue;
                    $content = $zip->getFromIndex($i);
                    if ($content === false || $content === '') {
                        $warnings[] = 'Unable to read PDF entry: ' . $entryName;
                        continue;
                    }
                    $files[] = array('name' => basename($entryName), 'content' => $content);
                }
                $zip->close();
                $zipOpened = true;
            }
        }

        if (!$zipOpened && class_exists('PharData')) {
            try {
                $phar = new PharData($upload['tmp_name']);
                foreach (new RecursiveIteratorIterator($phar) as $entry) {
                    $entryName = $entry->getFilename();
                    if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') continue;
                    $content = file_get_contents($entry->getPathname());
                    if ($content === false || $content === '') {
                        $warnings[] = 'Unable to read PDF entry: ' . $entryName;
                        continue;
                    }
                    $files[] = array('name' => $entryName, 'content' => $content);
                }
                $zipOpened = true;
            } catch (\Exception $e) {
                $errors[] = 'Unable to open ZIP file: ' . $e->getMessage();
                return $files;
            }
        }

        if (!$zipOpened) {
            $errors[] = 'ZIP import requires ZipArchive or Phar extension. Please enable one in your PHP configuration.';
            return $files;
        }

        if (count($files) === 0) {
            $errors[] = 'No PDF files found in ZIP.';
        }
        return $files;
    }
}

// ============================================================
//  TEXT LINE UTILITIES
// ============================================================

if (!function_exists('sorImpGetPdfTextLines')) {
    function sorImpGetPdfTextLines($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $out = array();
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line !== '') $out[] = $line;
        }
        return $out;
    }
}

// ============================================================
//  DATE CONVERSION
// ============================================================

if (!function_exists('sorImpDateToYmd')) {
    function sorImpDateToYmd($text)
    {
        $text = trim(str_replace(array('"', "'", ','), '', (string) $text));
        if ($text === '') return '';

        if (preg_match('/(\d{1,2})[\/.\-\s]+(\d{1,2})[\/.\-\s]+(\d{4})/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/(\d{4})[\/.\-\s]+(\d{1,2})[\/.\-\s]+(\d{1,2})/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        $ts = strtotime(str_replace('/', '-', $text));
        if ($ts !== false) return date('Y-m-d', $ts);
        return '';
    }
}

// ============================================================
//  INVOICE FIELD EXTRACTION
// ============================================================

if (!function_exists('sorImpFindInvoiceNo')) {
    function sorImpFindInvoiceNo($text, $fileName)
    {
        // Try explicit Invoice # or Invoice No patterns in text
        if (preg_match('/Invoice\s*#\s*(INV[-\s]?[A-Z0-9-]+)/i', $text, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }
        if (preg_match('/Invoice\s*(?:No|Number)[.:\s]*\s*(INV[-\s]?[A-Z0-9-]+)/i', $text, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }

        // Look for INV-XXXXXXX pattern anywhere
        if (preg_match('/\b(INV[-\s]?[A-Z0-9-]{4,})\b/i', $text, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }

        // Extract from filename: "Invoice #INV-202603008.pdf" -> "INV-202603008"
        $base = pathinfo((string) $fileName, PATHINFO_FILENAME);
        if (preg_match('/(INV[-\s]?[A-Z0-9-]{4,})/i', $base, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }

        return strtoupper(preg_replace('/[^A-Z0-9-]/i', '', $base));
    }
}

if (!function_exists('sorImpFindInvoiceDate')) {
    function sorImpFindInvoiceDate($text)
    {
        // Normalize: keep letters, digits, slashes, dots, dashes
        $clean = preg_replace('/[^a-zA-Z0-9\/.\-]/', ' ', $text);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        // Date regex tolerating spaces around separators
        $d = '(\d{1,2}\s*[\/.\-]\s*\d{1,2}\s*[\/.\-]\s*\d{4})';

        // Priority 1: "Invoices Date" / "Invoice Date"
        if (preg_match('/invoices?\s*date\s*:?\s*' . $d . '/i', $clean, $m)) {
            $v = sorImpDateToYmd(str_replace(' ', '', $m[1]));
            if ($v !== '') return $v;
        }

        // Priority 2: "Ordered Date"
        if (preg_match('/ordered\s*date\s*:?\s*' . $d . '/i', $clean, $m)) {
            $v = sorImpDateToYmd(str_replace(' ', '', $m[1]));
            if ($v !== '') return $v;
        }

        // Priority 3: "Date" (generic)
        if (preg_match('/\bdate\s*:?\s*' . $d . '/i', $clean, $m)) {
            $v = sorImpDateToYmd(str_replace(' ', '', $m[1]));
            if ($v !== '') return $v;
        }

        // Priority 4: first date-like pattern in entire text
        if (preg_match('/' . $d . '/', $clean, $m)) {
            $v = sorImpDateToYmd(str_replace(' ', '', $m[1]));
            if ($v !== '') return $v;
        }

        // Priority 5: yyyy-mm-dd format
        if (preg_match('/(\d{4}[\/.\-]\d{1,2}[\/.\-]\d{1,2})/', $clean, $m)) {
            $v = sorImpDateToYmd($m[1]);
            if ($v !== '') return $v;
        }

        return '';
    }
}

if (!function_exists('sorImpFindTotalPrice')) {
    function sorImpFindTotalPrice($text)
    {
        // Normalize commas in thousands: 1,000.00 → 1000.00
        $text = preg_replace('/(\d),(\d{3})/', '$1$2', $text);
        $clean = preg_replace('/[^a-zA-Z0-9.]/', ' ', $text);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        // Try patterns in order of specificity
        $patterns = array(
            '/sub\s*total\s*:?\s*(?:RM|MYR|SGD|USD)?\s*(\d+(?:\.\d{1,2}))/i',
            '/grand\s*total\s*:?\s*(?:RM|MYR|SGD|USD)?\s*(\d+(?:\.\d{1,2}))/i',
            '/total\s*amount\s*:?\s*(?:RM|MYR|SGD|USD)?\s*(\d+(?:\.\d{1,2}))/i',
            '/total\s*:?\s*(?:RM|MYR|SGD|USD)?\s*(\d+(?:\.\d{1,2}))/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $clean, $m)) {
                return number_format((float) $m[1], 2, '.', '');
            }
        }

        // Also try original text (before cleaning) for patterns like "Subtotal: RM768.00"
        $origClean = preg_replace('/(\d),(\d{3})/', '$1$2', $text);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origClean, $m)) {
                return number_format((float) $m[1], 2, '.', '');
            }
        }

        // Fallback: collect all RM amounts, pick the largest as likely subtotal
        if (preg_match_all('/(?:RM|MYR)\s*(\d+(?:\.\d{1,2}))/i', $origClean, $matches)) {
            $amounts = array_map('floatval', $matches[1]);
            rsort($amounts);
            if (!empty($amounts)) {
                return number_format($amounts[0], 2, '.', '');
            }
        }

        return '';
    }
}

// ============================================================
//  PACKAGE / PRODUCT MATCHING
// ============================================================

if (!function_exists('sorImpBuildPackageIndexes')) {
    function sorImpBuildPackageIndexes($packages)
    {
        $nameMap = array();
        $descMap = array();
        foreach ($packages as $pkg) {
            $nameKey = sorImpLookup(isset($pkg['name']) ? $pkg['name'] : '');
            $descKey = sorImpLookup(isset($pkg['item_description']) ? $pkg['item_description'] : '');
            if ($nameKey !== '') $nameMap[$nameKey] = $pkg;
            if ($descKey !== '') $descMap[$descKey] = $pkg;
        }
        return array($nameMap, $descMap);
    }
}

if (!function_exists('sorImpResolvePackageFromText')) {
    function sorImpResolvePackageFromText($line, $packages, $nameMap, $descMap)
    {
        $lineNorm = sorImpLookup($line);
        if ($lineNorm === '') return null;

        // Exact match on name or description
        if (isset($nameMap[$lineNorm])) return $nameMap[$lineNorm];
        if (isset($descMap[$lineNorm])) return $descMap[$lineNorm];

        // Substring match: line contains package name or vice versa
        foreach ($packages as $pkg) {
            $n = sorImpLookup(isset($pkg['name']) ? $pkg['name'] : '');
            $d = sorImpLookup(isset($pkg['item_description']) ? $pkg['item_description'] : '');

            if ($n !== '' && strlen($n) >= 5) {
                if (strpos($lineNorm, $n) !== false || strpos($n, $lineNorm) !== false) {
                    return $pkg;
                }
            }
            if ($d !== '' && strlen($d) >= 5) {
                if (strpos($lineNorm, $d) !== false || strpos($d, $lineNorm) !== false) {
                    return $pkg;
                }
            }
        }

        return null;
    }
}

if (!function_exists('sorImpResolveProductFromText')) {
    function sorImpResolveProductFromText($name, $productKeyToId)
    {
        $key = sorImpLookup($name);
        if ($key === '') return 0;

        // Exact normalized match
        if (isset($productKeyToId[$key])) return (int) $productKeyToId[$key];

        // Substring match: find the product whose key is contained in the line or vice versa
        foreach ($productKeyToId as $pKey => $pId) {
            if (strlen($pKey) >= 5 && (strpos($key, $pKey) !== false || strpos($pKey, $key) !== false)) {
                return (int) $pId;
            }
        }

        return 0;
    }
}

if (!function_exists('sorImpParseNumberedInvoiceRow')) {
    /**
     * Parse numbered invoice row and extract item text + row qty + row total.
     * Example: "2 CNY2026D - Carb Zero x 2 RM 336.00 RM 0.00 1 RM168.00"
     */
    function sorImpParseNumberedInvoiceRow($line)
    {
        $line = trim((string) $line);
        if ($line === '') return null;

        if (preg_match('/^\s*(\d{1,3})\s+(.+?)\s+(\d{1,4})\s+(?:RM|MYR|SGD|USD)?\s*([\d,]+(?:\.\d{1,2})?)\s*$/i', $line, $m)) {
            return array(
                'index' => (int) $m[1],
                'text' => trim((string) $m[2]),
                'qty' => (int) $m[3],
                'line_total' => (float) str_replace(',', '', (string) $m[4]),
            );
        }

        if (preg_match('/^\s*(\d{1,3})\s+(.+?)(?:\s+RM\s|\s+MYR\s|\s+SGD\s|\s+USD\s|\s+\d{1,3}(?:,\d{3})*\.\d{2})/i', $line, $m)) {
            return array(
                'index' => (int) $m[1],
                'text' => trim((string) $m[2]),
                'qty' => 1,
                'line_total' => 0.00,
            );
        }

        if (preg_match('/^\s*(\d{1,3})\s+([A-Za-z].{2,})$/', $line, $m)) {
            return array(
                'index' => (int) $m[1],
                'text' => trim((string) $m[2]),
                'qty' => 1,
                'line_total' => 0.00,
            );
        }

        return null;
    }
}

if (!function_exists('sorImpSanitizeExtractedName')) {
    function sorImpSanitizeExtractedName($text)
    {
        $text = trim((string) $text);
        if ($text === '') return '';

        // Remove OCR marker symbols often attached to names.
        $text = str_replace(array('*', '+', '•', '·'), '', $text);
        // Remove dangling currency tokens at end: "... RM" / "... RM RM".
        $text = preg_replace('/(?:\s+(?:RM|MYR|SGD|USD))+\s*$/i', '', $text);
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    }
}

// ============================================================
//  NOISE LINE DETECTION
// ============================================================

if (!function_exists('sorImpIsNoiseLine')) {
    function sorImpIsNoiseLine($line)
    {
        $line = strtolower(trim($line));
        if ($line === '') return true;

        // Section headers in invoices
        if (preg_match('/^(choose\s+any|free\s+item|quantity|qty|#|product|price|discount|total|action)(\s|$)/i', $line)) return true;

        // Pure price lines
        if (preg_match('/^(rm|myr|sgd|usd)\s*[\d,.]+$/i', $line)) return true;

        // "(Member)" or "Member" lines
        if (preg_match('/^\(?\s*member\s*\)?$/i', $line)) return true;

        // Lines that are just numbers (page numbers, etc.)
        if (preg_match('/^\d{1,3}$/', $line)) return true;

        return false;
    }
}

if (!function_exists('sorImpIsHeaderLine')) {
    /**
     * Detect the product table header row so we know where line items start.
     * e.g. "No Product Name Product Price Tax Qty Total Price"
     */
    function sorImpIsHeaderLine($line)
    {
        $lower = strtolower(trim($line));
        // Must contain both "product" and ("qty" or "quantity" or "price")
        if (strpos($lower, 'product') !== false && (strpos($lower, 'qty') !== false || strpos($lower, 'quantity') !== false || strpos($lower, 'price') !== false)) {
            return true;
        }
        // Header pattern: "No Product Name"
        if (preg_match('/^no\s+product\s+name/i', $lower)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('sorImpIsCustomerInfoLine')) {
    /**
     * Detect lines that belong to the customer/shipping/store header section
     * so they are NOT mistakenly parsed as product rows.
     */
    function sorImpIsCustomerInfoLine($line)
    {
        $lower = strtolower(trim($line));
        // Known header labels
        $headerPatterns = array(
            '/\b(customer\s*info|shipping\s*address|billing\s*address)\b/i',
            '/\b(invoice\s*#|invoice\s*no|order\s*#|order\s*no)\b/i',
            '/\b(invoices?\s*date|ordered?\s*date)\b/i',
            '/\b(phone|email|wechat|name\s*:|shipping\s*method)\b/i',
            '/\b(jalan|taman|lorong|no\.\s*\d|malaysia|kuala\s*lumpur|johor|selangor|penang|sabah|sarawak|pontian|skudai)\b/i',
            '/\b(\d{5}\s+\w)/',  // Postcode pattern like "81300 Skudai"
            '/\(\+?\d{1,3}\)\s*\d/',  // Phone number like (+60) 133343267
            '/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/',  // Email
            '/^(ums|store|sdn|bhd)/i',  // Company names
            '/\bcourier\b/i',
        );
        foreach ($headerPatterns as $pattern) {
            if (preg_match($pattern, $line)) return true;
        }
        return false;
    }
}

// ============================================================
//  MAIN PARSER: PDF TEXT → STRUCTURED ROWS
// ============================================================

if (!function_exists('sorImpParsePdfToRows')) {
    function sorImpParsePdfToRows($pdfFile, $packages, $packageMap, $productNameMap, $productNameToId, $productBrandMap, $brandCompanyMap, $clientOcrText = '')
    {
        $warnings = array();
        $ocrWarnings = array();

        $text = sorImpExtractPdfText($pdfFile['content'], $ocrWarnings, $clientOcrText);
        $warnings = array_merge($warnings, $ocrWarnings);

        if ($text === '') {
            return array('rows' => array(), 'warnings' => array_merge($warnings, array('Unable to extract any text from ' . $pdfFile['name'] . '.')), 'ocr_text' => '');
        }

        // ---- Extract metadata ----
        $invoiceNo = sorImpFindInvoiceNo($text, $pdfFile['name']);
        $invoiceDate = sorImpFindInvoiceDate($text);
        $totalPrice = sorImpFindTotalPrice($text);

        if ($invoiceDate === '') {
            $warnings[] = 'Could not extract Invoices Date from "' . $pdfFile['name'] . '". Please fill manually.';
        }
        if ($totalPrice === '') {
            $warnings[] = 'Could not extract Total Price from "' . $pdfFile['name'] . '". Please fill manually.';
        }

        // ---- Build indexes for matching ----
        $productKeyToId = array();
        foreach ($productNameMap as $pid => $pname) {
            $pKey = sorImpLookup($pname);
            if ($pKey !== '') $productKeyToId[$pKey] = (int) $pid;
        }

        list($pkgNameMap, $pkgDescMap) = sorImpBuildPackageIndexes($packages);

        // ---- Parse line items from text ----
        $lines = sorImpGetPdfTextLines($text);
        $lineItems = array();
        $currentItem = null;

        // Step 1: Find where the product table starts.
        // Look for a header row like "No Product Name Product Price Tax Qty Total Price".
        // Everything before this header is customer/store info and must be skipped.
        $productTableStarted = false;
        $productLines = array();

        foreach ($lines as $line) {
            if (!$productTableStarted) {
                if (sorImpIsHeaderLine($line)) {
                    $productTableStarted = true;
                }
                continue; // Skip all lines before the product table header
            }
            $productLines[] = $line;
        }

        // If no explicit header was found, fall back to skipping known customer-info lines
        if (!$productTableStarted) {
            $productLines = array();
            $pastCustomerInfo = false;
            foreach ($lines as $line) {
                if (!$pastCustomerInfo) {
                    if (sorImpIsCustomerInfoLine($line) || sorImpIsNoiseLine($line)) {
                        continue;
                    }
                    // First non-customer, non-noise line with a number prefix = start of products
                    if (preg_match('/^\s*\d{1,3}\s+/', $line)) {
                        $pastCustomerInfo = true;
                    } else {
                        continue;
                    }
                }
                $productLines[] = $line;
            }
            // Ultimate fallback: use all lines
            if (empty($productLines)) {
                $productLines = $lines;
            }
        }

        // Step 2: Parse product lines into structured items
        foreach ($productLines as $line) {
            // Skip customer info lines that leaked through
            if (sorImpIsCustomerInfoLine($line)) continue;

            // Detect numbered invoice row and capture row qty/line total from the row itself.
            $rowHeader = sorImpParseNumberedInvoiceRow($line);
            if ($rowHeader !== null) {
                if ($currentItem !== null) {
                    $lineItems[] = $currentItem;
                }
                $pkgName = trim((string) $rowHeader['text']);
                // Remove trailing price fragments that OCR might have joined
                $pkgName = preg_replace('/\s+RM\s*[\d,.]*$/', '', $pkgName);
                $pkgName = preg_replace('/\s+\d+\.\d{2}$/', '', $pkgName);
                $pkgName = sorImpSanitizeExtractedName($pkgName);

                $currentItem = array(
                    'index' => (int) $rowHeader['index'],
                    'package_text' => $pkgName,
                    'products' => array(),
                    'row_qty' => max(1, (int) $rowHeader['qty']),
                    'line_total_price' => isset($rowHeader['line_total']) ? (float) $rowHeader['line_total'] : 0.00,
                    'has_section_marker' => false,
                );
                continue;
            }

            if ($currentItem !== null && preg_match('/^(choose\s+any|free\s+item)\b/i', trim((string) $line))) {
                $currentItem['has_section_marker'] = true;
            }

            // Skip noise lines
            if (sorImpIsNoiseLine($line)) continue;

            // Detect product line with qty. Try multiple OCR output formats:
            // Format 1 (full invoice row): "Name RM<price> <tax>% <qty> RM<total>"
            //   e.g. "Urbansim Candy Sunz+ RM24.00 0% 4 RM96.00"
            // Format 2 (partial): "Name <qty> RM<total>"
            //   e.g. "Urbansim Candy Sunz+ 4 RM96.00"
            // Format 3 (simple, no price): "Name <qty>"
            //   e.g. "Urbansim Candy Sunz+ 4"
            // Format 4 (with trailing label): "Name <qty> (Member)"
            //   e.g. "Urbansim Candy Sunz* 4 (Member)"
            if ($currentItem !== null) {
                // Pre-process: strip trailing parenthetical labels like "(Member)", "(Free)", etc.
                $cleanLine = preg_replace('/\s*\([^)]{1,30}\)\s*$/', '', $line);
                $cleanLine = trim((string) $cleanLine);

                $prodName = '';
                $qty = 0;

                // Format 1: Name RM<price> [tax%] <qty> RM<total>
                if (preg_match(
                    '/^(.+?)\s+RM\s*[\d,]+\.?\d*(?:\s+\d+(?:\.\d+)?%\s+|\s+)(\d{1,4})\s+RM\s*[\d,]+\.?\d*\s*$/i',
                    $cleanLine, $m
                )) {
                    $prodName = trim((string) $m[1]);
                    $qty = (int) $m[2];
                }
                // Format 2: Name <qty> RM<total>
                elseif (preg_match(
                    '/^(.+?)\s+(\d{1,4})\s+RM\s*[\d,]+\.?\d*\s*$/i',
                    $cleanLine, $m
                )) {
                    $prodName = trim((string) $m[1]);
                    $qty = (int) $m[2];
                }
                // Format 3 & 4: Name <qty>  (simple, ends with bare number — after stripping trailing labels)
                elseif (preg_match('/^(.+?)\s+(\d{1,4})\s*$/', $cleanLine, $m)) {
                    $prodName = trim((string) $m[1]);
                    $qty = (int) $m[2];
                }

                if ($prodName !== '' && $qty > 0) {
                    $prodName = sorImpSanitizeExtractedName($prodName);
                    // Skip if the "product name" is actually noise
                    if (sorImpIsNoiseLine($prodName)) continue;
                    // Skip if it looks like a price line
                    if (preg_match('/^(RM|MYR|SGD|USD)\s/i', $prodName)) continue;
                    // Skip if it looks like customer info
                    if (sorImpIsCustomerInfoLine($prodName)) continue;

                    $currentItem['products'][] = array(
                        'name' => $prodName,
                        'qty' => $qty,
                    );
                }
            }
        }

        if ($currentItem !== null) {
            $lineItems[] = $currentItem;
        }

        // Keep each numbered package line independent, even if package names repeat.
        $lineItems = array_values($lineItems);

        // ---- Build output rows ----
        $rows = array();

        foreach ($lineItems as $itemIdx => $item) {
            $packageText = $item['package_text'];
            $itemGroupKey = 'pkg_line_' . (int) ($itemIdx + 1);

            // Try to match package in DB
            $pkgHit = sorImpResolvePackageFromText($packageText, $packages, $pkgNameMap, $pkgDescMap);
            $pkgId = $pkgHit ? (int) $pkgHit['id'] : 0;
            $pkgBrandId = ($pkgHit && isset($pkgHit['brand_id'])) ? (int) $pkgHit['brand_id'] : 0;
            $pkgItemDesc = ($pkgHit && isset($pkgHit['item_description'])) ? (string) $pkgHit['item_description'] : '';
            $pkgCompanyId = ($pkgBrandId > 0 && isset($brandCompanyMap[$pkgBrandId])) ? (int) $brandCompanyMap[$pkgBrandId] : 0;

            $lineTotalPrice = isset($item['line_total_price']) ? (float) $item['line_total_price'] : 0.00;
            $rowQty = isset($item['row_qty']) ? (int) $item['row_qty'] : 1;
            $hasSectionMarker = !empty($item['has_section_marker']);
            $isStandalone = (count($item['products']) === 0 && !$hasSectionMarker);

            if ($isStandalone) {
                $standaloneName = sorImpSanitizeExtractedName($packageText);
                $standaloneProductId = sorImpResolveProductFromText($standaloneName, $productKeyToId);
                $standaloneBrandId = ($standaloneProductId > 0 && isset($productBrandMap[$standaloneProductId])) ? (int) $productBrandMap[$standaloneProductId] : 0;
                $standaloneCompanyId = ($standaloneBrandId > 0 && isset($brandCompanyMap[$standaloneBrandId])) ? (int) $brandCompanyMap[$standaloneBrandId] : 0;

                $rows[] = array(
                    'source_file' => (string) $pdfFile['name'],
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $invoiceDate,
                    'total_price' => $totalPrice,
                    'warehouse_id' => '',
                    'product_id' => $standaloneProductId,
                    'product_name' => $standaloneName,
                    'pdf_product_name' => $standaloneName,
                    'package_id' => 0,
                    'package_name' => '',
                    'pdf_package_name' => '',
                    'package_group_key' => $itemGroupKey,
                    'line_type' => 'standalone_product',
                    'line_total_price' => $lineTotalPrice,
                    'package_qty' => max(1, $rowQty),
                    'item_description' => $standaloneName,
                    'qty' => max(1, $rowQty),
                    'brand_id' => $standaloneBrandId,
                    'company_id' => $standaloneCompanyId,
                    'warning' => '',
                );
            } else if (count($item['products']) > 0) {
                // This package has product sub-items in the PDF
                foreach ($item['products'] as $prod) {
                    $productId = sorImpResolveProductFromText($prod['name'], $productKeyToId);

                    $rows[] = array(
                        'source_file' => (string) $pdfFile['name'],
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $invoiceDate,
                        'total_price' => $totalPrice,
                        'warehouse_id' => '',
                        'product_id' => $productId,
                        'product_name' => $prod['name'],
                        'pdf_product_name' => $prod['name'],
                        'package_id' => $pkgId,
                        'package_name' => $packageText,
                        'pdf_package_name' => $packageText,
                        'package_group_key' => $itemGroupKey,
                        'line_type' => 'package',
                        'line_total_price' => $lineTotalPrice,
                        'package_qty' => max(1, $rowQty),
                        'item_description' => $pkgItemDesc,
                        'qty' => max(1, $prod['qty']),
                        'brand_id' => $pkgBrandId,
                        'company_id' => $pkgCompanyId,
                        'warning' => '',
                    );
                }
            } else {
                // No product lines found under this package in PDF.
                // If package matched in DB and has linked products, expand them.
                if ($pkgId > 0 && isset($packageMap[$pkgId])) {
                    $pkgProductIds = isset($packageMap[$pkgId]['product_ids']) ? $packageMap[$pkgId]['product_ids'] : array();
                    if (count($pkgProductIds) > 0) {
                        foreach ($pkgProductIds as $pid) {
                            $pid = (int) $pid;
                            $rows[] = array(
                                'source_file' => (string) $pdfFile['name'],
                                'invoice_no' => $invoiceNo,
                                'invoice_date' => $invoiceDate,
                                'total_price' => $totalPrice,
                                'warehouse_id' => '',
                                'product_id' => $pid,
                                'product_name' => isset($productNameMap[$pid]) ? (string) $productNameMap[$pid] : '',
                                'pdf_product_name' => '',
                                'package_id' => $pkgId,
                                'package_name' => $packageText,
                                'pdf_package_name' => $packageText,
                                'package_group_key' => $itemGroupKey,
                                'line_type' => 'package',
                                'line_total_price' => $lineTotalPrice,
                                'package_qty' => max(1, $rowQty),
                                'item_description' => $pkgItemDesc,
                                'qty' => 1,
                                'brand_id' => $pkgBrandId,
                                'company_id' => $pkgCompanyId,
                                'warning' => 'Product qty not found in PDF. Expanded from package. Please verify qty.',
                            );
                        }
                    } else {
                        // Package exists but no products linked
                        $rows[] = array(
                            'source_file' => (string) $pdfFile['name'],
                            'invoice_no' => $invoiceNo,
                            'invoice_date' => $invoiceDate,
                            'total_price' => $totalPrice,
                            'warehouse_id' => '',
                            'product_id' => 0,
                            'product_name' => '',
                            'pdf_product_name' => '',
                            'package_id' => $pkgId,
                            'package_name' => $packageText,
                            'pdf_package_name' => $packageText,
                            'package_group_key' => $itemGroupKey,
                            'line_type' => 'package',
                            'line_total_price' => $lineTotalPrice,
                            'package_qty' => max(1, $rowQty),
                            'item_description' => $pkgItemDesc,
                            'qty' => 1,
                            'brand_id' => $pkgBrandId,
                            'company_id' => $pkgCompanyId,
                            'warning' => 'Package has no linked products. Please add products to this package.',
                        );
                    }
                } else {
                    // Package not in DB
                    $rows[] = array(
                        'source_file' => (string) $pdfFile['name'],
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $invoiceDate,
                        'total_price' => $totalPrice,
                        'warehouse_id' => '',
                        'product_id' => 0,
                        'product_name' => '',
                        'pdf_product_name' => '',
                        'package_id' => 0,
                        'package_name' => $packageText,
                        'pdf_package_name' => $packageText,
                        'package_group_key' => $itemGroupKey,
                        'line_type' => 'package',
                        'line_total_price' => $lineTotalPrice,
                        'package_qty' => max(1, $rowQty),
                        'item_description' => '',
                        'qty' => 1,
                        'brand_id' => 0,
                        'company_id' => 0,
                        'warning' => '',
                    );
                }
            }
        }

        // If parsing yielded no rows, create a single placeholder row
        if (count($rows) === 0) {
            $rows[] = array(
                'source_file' => (string) $pdfFile['name'],
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'total_price' => $totalPrice,
                'warehouse_id' => '',
                'product_id' => 0,
                'product_name' => '',
                'pdf_product_name' => '',
                'package_id' => 0,
                'package_name' => '',
                'pdf_package_name' => '',
                'package_group_key' => 'pkg_line_1',
                'line_type' => 'standalone_product',
                'line_total_price' => 0.00,
                'package_qty' => 1,
                'item_description' => '',
                'qty' => 1,
                'brand_id' => 0,
                'company_id' => 0,
                'warning' => 'Could not parse any line items. Please fill in manually.',
            );
        }

        return array('rows' => $rows, 'warnings' => $warnings);
    }
}

// ============================================================
//  ACTION HANDLERS
// ============================================================

$action = post('actionBtn');
$importErrors = array();
$importWarnings = array();

if ($action === 'cancelImport') {
    unset($_SESSION['sor_pdf_import_preview']);
    echo "<script>location.href='" . htmlspecialchars($tablePage, ENT_QUOTES, 'UTF-8') . "';</script>";
    exit;
}

if ($action === 'parseStockOrderPdf') {
    $clientOcrText = isset($_POST['client_ocr_text']) ? trim((string) $_POST['client_ocr_text']) : '';
    $clientOcrMapJson = isset($_POST['client_ocr_map']) ? trim((string) $_POST['client_ocr_map']) : '';
    $clientOcrMap = array();
    if ($clientOcrMapJson !== '') {
        $decodedMap = @json_decode($clientOcrMapJson, true);
        if (is_array($decodedMap)) {
            $clientOcrMap = $decodedMap;
        }
    }

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please choose a PDF or ZIP file.';
    } else {
        $sourceFiles = sorImpCollectPdfFiles($_FILES['import_file'], $importErrors, $importWarnings);
        $previewRows = array();

        foreach ($sourceFiles as $src) {
            $srcName = isset($src['name']) ? (string) $src['name'] : '';
            $srcNameKey = strtolower($srcName);
            $srcBaseKey = strtolower((string) basename($srcName));

            $ocrTextForSrc = $clientOcrText;
            if (isset($clientOcrMap[$srcNameKey]) && is_string($clientOcrMap[$srcNameKey])) {
                $ocrTextForSrc = (string) $clientOcrMap[$srcNameKey];
            } else if (isset($clientOcrMap[$srcBaseKey]) && is_string($clientOcrMap[$srcBaseKey])) {
                $ocrTextForSrc = (string) $clientOcrMap[$srcBaseKey];
            }

            $parsed = sorImpParsePdfToRows($src, $packages, $packageMap, $productNameMap, $productNameToId, $productBrandMap, $brandCompanyMap, $ocrTextForSrc);
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
            $lineType = isset($r['line_type']) ? trim((string) $r['line_type']) : 'package';
            $isStandalone = ($lineType === 'standalone_product');
            $warehouseId = isset($r['warehouse_id']) ? (int) $r['warehouse_id'] : 0;
            $invoiceNo = trim((string) (isset($r['invoice_no']) ? $r['invoice_no'] : ''));
            $invoiceDate = sorImpDateToYmd(isset($r['invoice_date']) ? $r['invoice_date'] : '');
            $productName = trim((string) (isset($r['product_name']) ? $r['product_name'] : ''));
            $productId = isset($r['product_id']) ? (int) $r['product_id'] : 0;
            $packageId = isset($r['package_id']) ? (int) $r['package_id'] : 0;
            $itemDescription = trim((string) (isset($r['item_description']) ? $r['item_description'] : ''));
            $packageQty = isset($r['package_qty']) ? (int) $r['package_qty'] : 0;
            $productQty = isset($r['product_qty']) ? (int) $r['product_qty'] : (isset($r['qty']) ? (int) $r['qty'] : 0);
            $totalPrice = isset($r['total_price']) ? (float) $r['total_price'] : 0;
            $rowBrandId = isset($r['brand_id']) ? (int) $r['brand_id'] : 0;
            $rowCompanyId = isset($r['company_id']) ? (int) $r['company_id'] : 0;

            if ($isStandalone && $packageQty <= 0) {
                $packageQty = 1;
            }

            if ($productId <= 0 && $productName !== '') {
                $key = sorImpLookup($productName);
                if (isset($productNameToId[$key])) {
                    $productId = (int) $productNameToId[$key];
                }
            }

            if ($warehouseId <= 0) $importErrors[] = 'Row #' . $rowNo . ': Warehouse is required.';
            if ($invoiceNo === '') $importErrors[] = 'Row #' . $rowNo . ': Invoice is required.';
            if ($invoiceDate === '') $importErrors[] = 'Row #' . $rowNo . ': Invoice Date is required.';
            if (!$isStandalone && ($packageId <= 0 || !isset($packageMap[$packageId]))) $importErrors[] = 'Row #' . $rowNo . ': Valid package is required.';
            if ($productId <= 0) $importErrors[] = 'Row #' . $rowNo . ': Valid product is required.';
            if ($packageQty <= 0) $importErrors[] = 'Row #' . $rowNo . ': Package quantity must be more than 0.';
            if ($productQty <= 0) $importErrors[] = 'Row #' . $rowNo . ': Product quantity must be more than 0.';

            if (!$isStandalone && $packageId > 0 && isset($packageMap[$packageId])) {
                $pkg = $packageMap[$packageId];
                $productIds = isset($pkg['product_ids']) ? $pkg['product_ids'] : array();
                if (is_array($productIds) && count($productIds) > 0 && $productId > 0 && !in_array($productId, $productIds, true)) {
                    $importErrors[] = 'Row #' . $rowNo . ': Selected product does not belong to selected package.';
                }
            }

            if (!$isStandalone && $packageId > 0 && isset($packageMap[$packageId])) {
                $pkg = $packageMap[$packageId];
                $pkgBrandId = isset($pkg['brand_id']) ? (int) $pkg['brand_id'] : 0;
                if ($pkgBrandId > 0) {
                    $rowBrandId = $pkgBrandId;
                }
            }

            // If still no brand from package, fall back to the product's own brand
            if ($rowBrandId <= 0 && $productId > 0 && isset($productBrandMap[$productId])) {
                $rowBrandId = $productBrandMap[$productId];
            }

            if ($rowBrandId > 0 && isset($brandCompanyMap[$rowBrandId])) {
                $rowCompanyId = (int) $brandCompanyMap[$rowBrandId];
            }

            if ($rowCompanyId <= 0) {
                $importWarnings[] = 'Row #' . $rowNo . ': Company could not be resolved from brand — it will be left blank. Please set the brand on the package or product.';
            }

            $groupKey = $warehouseId . '|' . $invoiceNo . '|' . $invoiceDate . '|' . $rowCompanyId;
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = array(
                    'warehouse_id' => $warehouseId,
                    'company_id' => $rowCompanyId,
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $invoiceDate,
                    'request_date' => $invoiceDate,
                    'extracted_total_price' => $totalPrice,
                    'brand_ids' => array(),
                    'items' => array(),
                    'source_file' => isset($r['source_file']) ? (string) $r['source_file'] : '',
                );
            } else if ($totalPrice > 0) {
                $grouped[$groupKey]['extracted_total_price'] = $totalPrice;
            }

            $brandId = (int) $rowBrandId;
            if ($brandId > 0) {
                $grouped[$groupKey]['brand_ids'][$brandId] = true;
            }

            if (!$isStandalone && $packageId > 0 && isset($packageMap[$packageId])) {
                $pkg = $packageMap[$packageId];
                $resolvedDesc = $itemDescription !== '' ? $itemDescription : (isset($pkg['item_description']) && trim((string) $pkg['item_description']) !== '' ? (string) $pkg['item_description'] : $productName);
                $grouped[$groupKey]['items'][] = array(
                    'product_id' => $productId,
                    'package_id' => $packageId,
                    'package_desc' => $resolvedDesc,
                    'packageQty' => $packageQty,
                    'productQty' => $productQty,
                    'brand_id' => $brandId,
                    'company_id' => $rowCompanyId,
                );
            } else {
                $resolvedDesc = $itemDescription !== '' ? $itemDescription : $productName;
                $grouped[$groupKey]['items'][] = array(
                    'product_id' => $productId,
                    'package_id' => 0,
                    'package_desc' => $resolvedDesc,
                    'packageQty' => 1,
                    'productQty' => $productQty,
                    'brand_id' => $brandId,
                    'company_id' => $rowCompanyId,
                );
            }
        }

        if (count($importErrors) === 0) {
            mysqli_begin_transaction($finance_connect);
            $inserted = 0;

            try {
                foreach ($grouped as $g) {
                    if (!isset($g['items']) || count($g['items']) === 0) continue;

                    $resolvedBrandIds = array_keys(isset($g['brand_ids']) ? $g['brand_ids'] : array());
                    $mainBrandId = count($resolvedBrandIds) === 1 ? (int) $resolvedBrandIds[0] : 0;

                    $safeInvoiceNo = mysqli_real_escape_string($finance_connect, $g['invoice_no']);
                    $safeInvoiceDate = mysqli_real_escape_string($finance_connect, $g['invoice_date']);
                    $safeRequestDate = mysqli_real_escape_string($finance_connect, $g['request_date']);
                    $safeRemark = mysqli_real_escape_string($finance_connect, 'Imported from PDF: ' . $g['source_file']);
                    $finalTotalPrice = (float) (isset($g['extracted_total_price']) ? $g['extracted_total_price'] : 0);
                    if ($finalTotalPrice <= 0) {
                        throw new Exception('Extracted total price is missing for invoice: ' . $g['invoice_no']);
                    }

                    $qMain = "INSERT INTO " . STOCK_ORDER_REQ . " (warehouse_id, company_id, brand_id, invoice_no, invoice_date, request_date, total_price, remark, create_by, create_date, create_time, status) VALUES ('" . (int) $g['warehouse_id'] . "', '" . (int) $g['company_id'] . "', '" . $mainBrandId . "', '" . $safeInvoiceNo . "', '" . $safeInvoiceDate . "', '" . $safeRequestDate . "', '" . number_format($finalTotalPrice, 2, '.', '') . "', '" . $safeRemark . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";

                    if (!mysqli_query($finance_connect, $qMain)) {
                        throw new Exception('Failed to insert request: ' . mysqli_error($finance_connect));
                    }

                    $requestId = (int) mysqli_insert_id($finance_connect);

                    foreach ($g['items'] as $it) {
                        $safeDesc = mysqli_real_escape_string($finance_connect, (string) $it['package_desc']);
                        $qItem = "INSERT INTO " . STOCK_ORDER_REQ_ITEM . " (request_id, product_id, brand_id, company_id, package_id, package_desc, packageQty, productQty, create_by, create_date, create_time, status) VALUES ('" . $requestId . "', '" . (int) $it['product_id'] . "', '" . (int) $it['brand_id'] . "', '" . (int) $it['company_id'] . "', '" . (int) $it['package_id'] . "', '" . $safeDesc . "', '" . (int) $it['packageQty'] . "', '" . (int) $it['productQty'] . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                        if (!mysqli_query($finance_connect, $qItem)) {
                            throw new Exception('Failed to insert item: ' . mysqli_error($finance_connect));
                        }
                    }

                    // Generate QR/token same as manual Add Request flow.
                    $token = sorEncodeToken($requestId);
                    $orderLink = $SITEURL . '/warehouse_stock_in_scan.php?t=' . urlencode($token);

                    $qrDir = ROOT . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'stock_order_request' . DIRECTORY_SEPARATOR;
                    if (!file_exists($qrDir)) {
                        mkdir($qrDir, 0777, true);
                    }
                    $qrFileName = 'sor_' . (int) $requestId . '.png';
                    $qrFsPath = $qrDir . $qrFileName;
                    $qrWebPath = '';
                    if (function_exists('imagecreate')) {
                        QRcode::png($orderLink, $qrFsPath, 'H', 6, 2);
                        if (file_exists($qrFsPath)) {
                            $qrWebPath = 'temp/stock_order_request/' . $qrFileName;
                        }
                    }
                    if ($qrWebPath === '') {
                        $qrWebPath = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($orderLink);
                    }

                    $safeToken = mysqli_real_escape_string($finance_connect, $token);
                    $safeQr = mysqli_real_escape_string($finance_connect, $qrWebPath);
                    mysqli_query($finance_connect, "UPDATE " . STOCK_ORDER_REQ . " SET order_link_token='$safeToken', qr_image='$safeQr' WHERE id='" . (int) $requestId . "'");

                    $inserted++;
                }

                mysqli_commit($finance_connect);
                unset($_SESSION['sor_pdf_import_preview']);
                echo "<script>alert('Imported " . $inserted . " stock order request(s) successfully.');location.href='" . htmlspecialchars($tablePage, ENT_QUOTES, 'UTF-8') . "';</script>";
                exit;
            } catch (Exception $ex) {
                mysqli_rollback($finance_connect);
                $importErrors[] = $ex->getMessage();
            }
        }
    }
}

// ============================================================
//  PREPARE PREVIEW DATA FOR UI
// ============================================================

$previewBundle = isset($_SESSION['sor_pdf_import_preview']) ? $_SESSION['sor_pdf_import_preview'] : null;
$previewRows = ($previewBundle && isset($previewBundle['rows']) && is_array($previewBundle['rows'])) ? $previewBundle['rows'] : array();
$previewSummary = ($previewBundle && isset($previewBundle['summary']) && is_array($previewBundle['summary'])) ? $previewBundle['summary'] : array('file_count' => 0, 'row_count' => 0);
$previewHasMissingProduct = false;
$previewHasMissingPackage = false;
$rowsBySource = array();
foreach ($previewRows as $idx => $rowCheck) {
    $rid = isset($rowCheck['product_id']) ? (int) $rowCheck['product_id'] : 0;
    $pid = isset($rowCheck['package_id']) ? (int) $rowCheck['package_id'] : 0;
    $lineType = isset($rowCheck['line_type']) ? (string) $rowCheck['line_type'] : 'package';
    if ($rid <= 0) $previewHasMissingProduct = true;
    if ($lineType !== 'standalone_product' && $pid <= 0) $previewHasMissingPackage = true;

    $source = isset($rowCheck['source_file']) && trim((string) $rowCheck['source_file']) !== '' ? (string) $rowCheck['source_file'] : 'Unknown Source';
    if (!isset($rowsBySource[$source])) {
        $rowsBySource[$source] = array();
    }
    $rowsBySource[$source][] = array('idx' => $idx, 'row' => $rowCheck);
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <style>
        .sor-import .card { border: 0; box-shadow: 0 .125rem .5rem rgba(0,0,0,.08); }
        .sor-import .preview-file-card { border: 1px solid #d1d5db; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; background: #fff; }
        .sor-import .preview-item-card { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .85rem; margin-bottom: .85rem; background: #fafafa; }
        .sor-import .warn-badge { background: #facc15; color: #111827; border-radius: .4rem; padding: .2rem .5rem; font-size: .75rem; }
        .sor-import .required::after { content: ' *'; color: #dc2626; }
        .sor-import .meta-muted { color: #6b7280; font-size: .9rem; }
        .sor-import .err-missing { color: #dc2626; font-size: .82rem; margin-top: .25rem; }
        .sor-import .err-missing a { color: #dc2626; text-decoration: underline; font-weight: 600; }
        .sor-import .preview-package-row .package-product-placeholder { background: #f3f4f6; }
        .sor-import .preview-product-row .product-desc-placeholder,
        .sor-import .preview-product-row .product-total-placeholder { background: #f8fafc; }
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
                    <form method="post" enctype="multipart/form-data" id="sorUploadForm">
                        <input type="hidden" name="client_ocr_text" id="client_ocr_text" value="">
                        <input type="hidden" name="client_ocr_map" id="client_ocr_map" value="">
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="import_file">Invoice PDF File (or ZIP for bulk import)</label>
                                <input class="form-control" type="file" name="import_file" id="import_file" accept=".pdf,.zip" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseStockOrderPdf" id="sorSubmitBtn">
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
                        <h5 class="card-title mb-2">Step 2: Preview And Edit Before Insert</h5>
                        <p class="mb-2">Detected Files: <strong><?= (int) $previewSummary['file_count'] ?></strong> | Parsed Rows: <strong><?= (int) $previewSummary['row_count'] ?></strong></p>

                        <?php if ($previewHasMissingProduct || $previewHasMissingPackage) { ?>
                            <div class="alert alert-danger" role="alert">
                                <div><strong>Missing product or package detected.</strong></div>
                                <?php if ($previewHasMissingProduct) { ?>
                                    <div>Please add product and package first. <a href="<?= $productPage ?>" target="_blank">Add Product</a> | <a href="<?= $packagePage ?>" target="_blank">Add Package</a></div>
                                <?php } else if ($previewHasMissingPackage) { ?>
                                    <div>Please add package first. <a href="<?= $packagePage ?>" target="_blank">Add Package</a></div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <form method="post" id="sorImportPreviewForm">
                            <?php $sourceNo = 1; foreach ($rowsBySource as $sourceFile => $rowSet) {
                                $firstRow = $rowSet[0]['row'];
                                $receiptKey = 'r' . (int) $sourceNo;
                                $invoiceVal = isset($firstRow['invoice_no']) ? (string) $firstRow['invoice_no'] : '';
                                $invoiceDateVal = sorImpDateToYmd(isset($firstRow['invoice_date']) ? (string) $firstRow['invoice_date'] : '');
                                $warehouseVal = isset($firstRow['warehouse_id']) ? (int) $firstRow['warehouse_id'] : 0;
                                $totalVal = isset($firstRow['total_price']) ? (string) $firstRow['total_price'] : '0.00';
                                $totalValNum = (float) $totalVal;
                            ?>
                                <div class="preview-file-card">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                        <strong>Receipt <?= (int) $sourceNo ?>: <?= htmlspecialchars((string) $sourceFile, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="meta-muted">Rows: <?= count($rowSet) ?></span>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label form_lbl required">Warehouse</label>
                                            <select class="form-select receipt-sync" data-receipt="<?= $receiptKey ?>" data-field="warehouse_id" required>
                                                <option value="">Select Warehouse</option>
                                                <?php foreach ($warehouses as $w) { ?>
                                                    <option value="<?= (int) $w['id'] ?>" <?= ($warehouseVal === (int) $w['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $w['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label form_lbl required">Invoice</label>
                                            <textarea class="form-control receipt-sync" rows="1" data-receipt="<?= $receiptKey ?>" data-field="invoice_no" required><?= htmlspecialchars($invoiceVal, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label form_lbl required">Invoices Date</label>
                                            <input class="form-control receipt-sync" type="date" data-receipt="<?= $receiptKey ?>" data-field="invoice_date" value="<?= htmlspecialchars($invoiceDateVal, ENT_QUOTES, 'UTF-8') ?>" required>
                                            <?php if ($invoiceDateVal === '') { ?>
                                                <div class="err-missing">Unable to extract invoices date from PDF. Please fill manually.</div>
                                            <?php } ?>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label form_lbl required">Total Price</label>
                                            <input class="form-control receipt-sync" type="number" step="0.01" min="0" data-receipt="<?= $receiptKey ?>" data-field="total_price" value="<?= htmlspecialchars($totalVal, ENT_QUOTES, 'UTF-8') ?>" required>
                                            <?php if ($totalValNum <= 0) { ?>
                                                <div class="err-missing">Unable to extract total price from PDF. Please fill manually.</div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th width="50">#</th>
                                                        <th>Package Name</th>
                                                        <th>Product Name</th>
                                                        <th>Item Description</th>
                                                        <th width="120">Quantity</th>
                                                        <th width="140">Total Price</th>
                                                        <th width="100">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $pkgPrevKey = '__none__';
                                                    $pkgGroupNo = 0;
                                                    $groupItemNo = 0;
                                                    foreach ($rowSet as $rowMeta) {
                                                        $idx = (int) $rowMeta['idx'];
                                                        $row = $rowMeta['row'];
                                                        $pkgId = isset($row['package_id']) ? (int) $row['package_id'] : 0;
                                                        $brandId = ($pkgId > 0 && isset($packageMap[$pkgId])) ? (int) $packageMap[$pkgId]['brand_id'] : (isset($row['brand_id']) ? (int) $row['brand_id'] : 0);
                                                        $companyId = ($brandId > 0 && isset($brandCompanyMap[$brandId])) ? (int) $brandCompanyMap[$brandId] : (isset($row['company_id']) ? (int) $row['company_id'] : 0);
                                                        $itemDescription = isset($row['item_description']) ? (string) $row['item_description'] : (($pkgId > 0 && isset($packageMap[$pkgId])) ? (string) $packageMap[$pkgId]['item_description'] : '');
                                                        $packageNameText = isset($row['package_name']) ? (string) $row['package_name'] : (($pkgId > 0 && isset($packageMap[$pkgId])) ? (string) $packageMap[$pkgId]['name'] : '');

                                                        $isMissingProduct = ((int) (isset($row['product_id']) ? $row['product_id'] : 0) <= 0);
                                                        $lineType = isset($row['line_type']) ? (string) $row['line_type'] : 'package';
                                                        $isStandaloneRow = ($lineType === 'standalone_product');
                                                        $isMissingPackage = (!$isStandaloneRow && $pkgId <= 0);

                                                        $pdfProductName = isset($row['pdf_product_name']) ? (string) $row['pdf_product_name'] : (isset($row['product_name']) ? (string) $row['product_name'] : '');
                                                        $pdfPackageName = isset($row['pdf_package_name']) ? (string) $row['pdf_package_name'] : $packageNameText;
                                                        $displayProductName = $pdfProductName !== '' ? $pdfProductName : (isset($row['product_name']) ? (string) $row['product_name'] : '');
                                                        $lineTotalPrice = isset($row['line_total_price']) ? (float) $row['line_total_price'] : 0;
                                                        $rowPackageQty = (int) (isset($row['package_qty']) ? $row['package_qty'] : (isset($row['qty']) ? $row['qty'] : 1));
                                                        if ($rowPackageQty <= 0) $rowPackageQty = 1;
                                                        $rowProductQty = (int) (isset($row['product_qty']) ? $row['product_qty'] : (isset($row['qty']) ? $row['qty'] : $rowPackageQty));
                                                        if ($rowProductQty <= 0) $rowProductQty = $rowPackageQty;
                                                        $rowProductBaseQty = (int) max(1, round($rowProductQty / max(1, $rowPackageQty)));

                                                        $pkgKey = isset($row['package_group_key']) && trim((string) $row['package_group_key']) !== ''
                                                            ? trim((string) $row['package_group_key'])
                                                            : ('pkg_fallback_' . (int) $idx);
                                                        $isGroupFirstRow = ($pkgKey !== $pkgPrevKey);
                                                        if ($isGroupFirstRow) {
                                                            $pkgGroupNo++;
                                                            $pkgPrevKey = $pkgKey;
                                                            $groupItemNo = 0;
                                                        }
                                                        $pkgGroupKey = 'receipt_' . $receiptKey . '_pkg_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $pkgKey);

                                                        if ($isGroupFirstRow && !$isStandaloneRow) {
                                                    ?>
                                                        <tr class="preview-row preview-package-row" data-receipt="<?= $receiptKey ?>" data-package-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>">
                                                            <td class="row-no"></td>
                                                            <td>
                                                                <input class="form-control mb-2" type="text" value="<?= htmlspecialchars($pdfPackageName, ENT_QUOTES, 'UTF-8') ?>" readonly disabled style="background:#f3f4f6;">

                                                                <?php if ($isMissingPackage) { ?>
                                                                    <select class="form-select sor-pkg-select" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" data-brand-target="brand_<?= $idx ?>" data-desc-target="desc_<?= $idx ?>" required>
                                                                        <option value="">Select Package</option>
                                                                        <?php foreach ($packages as $pkg) {
                                                                            $optLabel = (string) $pkg['name'];
                                                                            if (trim((string) $pkg['item_description']) !== '') {
                                                                                $optLabel .= ' - ' . (string) $pkg['item_description'];
                                                                            }
                                                                        ?>
                                                                            <option value="<?= (int) $pkg['id'] ?>" data-brand-id="<?= (int) $pkg['brand_id'] ?>" data-item-desc="<?= htmlspecialchars((string) $pkg['item_description'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                                        <?php } ?>
                                                                    </select>
                                                                <?php } ?>

                                                                <?php if ($isMissingPackage && $pdfPackageName !== '') { ?>
                                                                    <div class="err-missing">
                                                                        Package "<strong><?= htmlspecialchars($pdfPackageName, ENT_QUOTES, 'UTF-8') ?></strong>" does not exist in DB.
                                                                        Please <a href="<?= $packagePage ?>" target="_blank">add package</a> first.
                                                                    </div>
                                                                <?php } else if ($isMissingPackage) { ?>
                                                                    <div class="err-missing">
                                                                        No package matched. Please <a href="<?= $packagePage ?>" target="_blank">add package</a> first.
                                                                    </div>
                                                                <?php } ?>
                                                                <?php if (isset($row['warning']) && trim((string) $row['warning']) !== '') { ?>
                                                                    <div class="text-warning small mt-1"><?= htmlspecialchars((string) $row['warning'], ENT_QUOTES, 'UTF-8') ?></div>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <input class="form-control package-product-placeholder" type="text" value="" readonly disabled>
                                                            </td>
                                                            <td>
                                                                <input class="form-control group-desc-field" type="text" id="desc_<?= $idx ?>" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $itemDescription, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                                            </td>
                                                            <td>
                                                                <input class="form-control group-qty-field" type="number" min="1" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= (int) $rowPackageQty ?>" required>
                                                            </td>
                                                            <td>
                                                                <input class="form-control" type="text" value="<?= $lineTotalPrice > 0 ? htmlspecialchars(number_format($lineTotalPrice, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>" readonly disabled style="background:#f3f4f6;">
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-rounded btn-primary remove-preview-row" data-remove-scope="package">Remove Package</button>
                                                            </td>
                                                        </tr>
                                                    <?php
                                                        }

                                                        $displayRowNo = $isStandaloneRow ? 1 : (++$groupItemNo);
                                                    ?>
                                                        <tr class="preview-row preview-product-row" data-receipt="<?= $receiptKey ?>" data-package-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>">
                                                            <td class="row-no"><?= (int) $displayRowNo ?></td>
                                                            <td>
                                                                <input type="hidden" name="rows[<?= $idx ?>][source_file]" value="<?= htmlspecialchars((string) (isset($row['source_file']) ? $row['source_file'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" class="receipt-hidden-invoice_no-<?= $receiptKey ?>" name="rows[<?= $idx ?>][invoice_no]" value="<?= htmlspecialchars($invoiceVal, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" class="receipt-hidden-invoice_date-<?= $receiptKey ?>" name="rows[<?= $idx ?>][invoice_date]" value="<?= htmlspecialchars($invoiceDateVal, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" class="receipt-hidden-warehouse_id-<?= $receiptKey ?>" name="rows[<?= $idx ?>][warehouse_id]" value="<?= (int) $warehouseVal ?>">
                                                                <input type="hidden" class="receipt-hidden-total_price-<?= $receiptKey ?>" name="rows[<?= $idx ?>][total_price]" value="<?= htmlspecialchars($totalVal, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="rows[<?= $idx ?>][product_id]" value="<?= (int) (isset($row['product_id']) ? $row['product_id'] : 0) ?>">
                                                                <input type="hidden" name="rows[<?= $idx ?>][line_type]" value="<?= htmlspecialchars($lineType, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" class="pkg-hidden-id" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][package_id]" value="<?= (int) $pkgId ?>">
                                                                <input type="hidden" class="pkg-hidden-name" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][package_name]" value="<?= htmlspecialchars($pdfPackageName, ENT_QUOTES, 'UTF-8') ?>">

                                                                <?php if ($isStandaloneRow) { ?>
                                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($pdfPackageName, ENT_QUOTES, 'UTF-8') ?>" readonly disabled style="background:#f3f4f6;">
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <input class="form-control" type="text" name="rows[<?= $idx ?>][product_name]" value="<?= htmlspecialchars($displayProductName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Product Name" required>
                                                                <?php if ($isMissingProduct && $displayProductName !== '') { ?>
                                                                    <div class="err-missing">
                                                                        Product "<strong><?= htmlspecialchars($displayProductName, ENT_QUOTES, 'UTF-8') ?></strong>" does not exist in DB.
                                                                        Please <a href="<?= $productPage ?>" target="_blank">add product</a><?= $isStandaloneRow ? '' : ' and <a href="' . $packagePage . '" target="_blank">add package</a>' ?> first.
                                                                    </div>
                                                                <?php } else if ($isMissingProduct) { ?>
                                                                    <div class="err-missing">
                                                                        No product detected. Please <a href="<?= $productPage ?>" target="_blank">add product</a><?= $isStandaloneRow ? '' : ' and <a href="' . $packagePage . '" target="_blank">add package</a>' ?> first.
                                                                    </div>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <input type="hidden" class="pkg-hidden-desc" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][item_description]" value="<?= htmlspecialchars((string) $itemDescription, ENT_QUOTES, 'UTF-8') ?>">
                                                                <?php if ($isStandaloneRow) { ?>
                                                                    <input class="form-control" type="text" value="<?= htmlspecialchars((string) $itemDescription, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                                                <?php } else { ?>
                                                                    <input class="form-control product-desc-placeholder" type="text" value="" readonly disabled>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <input type="hidden" class="group-package-qty" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][package_qty]" value="<?= (int) ($isStandaloneRow ? 1 : $rowPackageQty) ?>">
                                                                <input type="hidden" class="group-product-base-qty" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][product_base_qty]" value="<?= (int) ($isStandaloneRow ? $rowProductQty : $rowProductBaseQty) ?>">
                                                                <input class="form-control <?= $isStandaloneRow ? '' : 'group-product-qty' ?>" type="number" min="1" <?= $isStandaloneRow ? '' : 'data-group="' . htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') . '"' ?> name="rows[<?= $idx ?>][product_qty]" value="<?= (int) $rowProductQty ?>" required>
                                                            </td>
                                                            <td>
                                                                <?php if ($isStandaloneRow) { ?>
                                                                    <input class="form-control" type="text" value="<?= $lineTotalPrice > 0 ? htmlspecialchars(number_format($lineTotalPrice, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>" readonly disabled style="background:#f3f4f6;">
                                                                <?php } else { ?>
                                                                    <input class="form-control product-total-placeholder" type="text" value="" readonly disabled>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-rounded btn-primary remove-preview-row" data-remove-scope="product">Remove Product</button>

                                                                <input type="hidden" class="pkg-brand-hidden" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][brand_id]" id="brand_hidden_<?= $idx ?>" value="<?= (int) $brandId ?>">
                                                                <input type="hidden" class="pkg-company-hidden" data-group="<?= htmlspecialchars($pkgGroupKey, ENT_QUOTES, 'UTF-8') ?>" name="rows[<?= $idx ?>][company_id]" id="company_hidden_<?= $idx ?>" value="<?= (int) $companyId ?>">
                                                                <input type="hidden" id="brand_<?= $idx ?>" value="<?= (int) $brandId ?>">
                                                                <input type="hidden" id="company_<?= $idx ?>" value="<?= (int) $companyId ?>">
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                </div>
                            <?php $sourceNo++; } ?>

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
    var brandToCompanyMap = <?= json_encode($brandCompanyMap) ?>;
    var brandNameMap = <?= json_encode($brands) ?>;
    var companyNameMap = <?= json_encode($companies) ?>;

    var page = "Stock Order Request";
    var action = "";
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);

    function syncReceiptField(receiptKey, field, value) {
        document.querySelectorAll('.receipt-hidden-' + field + '-' + receiptKey).forEach(function(el) {
            el.value = value;
        });
    }

    document.querySelectorAll('.receipt-sync').forEach(function(el) {
        var receiptKey = el.getAttribute('data-receipt');
        var field = el.getAttribute('data-field');
        if (!receiptKey || !field) return;

        var pushValue = function() {
            syncReceiptField(receiptKey, field, el.value || '');
        };

        el.addEventListener('change', pushValue);
        el.addEventListener('input', pushValue);
        pushValue();
    });

    function reindexPreviewRows(tbody) {
        if (!tbody) return;
        var groupCounter = {};
        tbody.querySelectorAll('tr.preview-row').forEach(function(row) {
            var groupKey = row.getAttribute('data-package-group') || '';
            var isPackageRow = row.classList.contains('preview-package-row');
            var rowNoCell = row.querySelector('.row-no');
            if (!rowNoCell) return;

            if (isPackageRow) {
                rowNoCell.textContent = '';
                groupCounter[groupKey] = 0;
                return;
            }

            if (!groupCounter.hasOwnProperty(groupKey)) {
                groupCounter[groupKey] = 0;
            }
            groupCounter[groupKey] += 1;
            rowNoCell.textContent = String(groupCounter[groupKey]);
        });
    }

    function applyGroupQty(groupKey) {
        if (!groupKey) return;
        var qtyField = document.querySelector('.group-qty-field[data-group="' + groupKey + '"]');
        if (!qtyField) return;

        var packageQty = parseInt(qtyField.value || '0', 10);
        if (isNaN(packageQty) || packageQty <= 0) {
            packageQty = 1;
            qtyField.value = '1';
        }

        document.querySelectorAll('.group-package-qty[data-group="' + groupKey + '"]').forEach(function(el) {
            el.value = String(packageQty);
        });

        document.querySelectorAll('.group-product-qty[data-group="' + groupKey + '"]').forEach(function(qtyInput) {
            var row = qtyInput.closest('tr.preview-product-row');
            if (!row) return;
            var baseInput = row.querySelector('.group-product-base-qty[data-group="' + groupKey + '"]');
            var baseQty = baseInput ? parseInt(baseInput.value || '0', 10) : 1;
            if (isNaN(baseQty) || baseQty <= 0) baseQty = 1;
            qtyInput.value = String(baseQty * packageQty);
        });
    }

    document.querySelectorAll('.group-qty-field').forEach(function(el) {
        var groupKey = el.getAttribute('data-group') || '';
        var sync = function() {
            applyGroupQty(groupKey);
        };
        el.addEventListener('input', sync);
        el.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('.group-product-qty').forEach(function(el) {
        var groupKey = el.getAttribute('data-group') || '';
        var updateBaseQty = function() {
            var packageQtyField = document.querySelector('.group-qty-field[data-group="' + groupKey + '"]');
            var packageQty = packageQtyField ? parseInt(packageQtyField.value || '0', 10) : 1;
            if (isNaN(packageQty) || packageQty <= 0) packageQty = 1;

            var productQty = parseInt(el.value || '0', 10);
            if (isNaN(productQty) || productQty <= 0) productQty = packageQty;

            var row = el.closest('tr.preview-product-row');
            if (!row) return;
            var baseInput = row.querySelector('.group-product-base-qty[data-group="' + groupKey + '"]');
            if (baseInput) {
                baseInput.value = String(Math.max(1, Math.round(productQty / packageQty)));
            }
        };
        el.addEventListener('input', updateBaseQty);
        el.addEventListener('change', updateBaseQty);
    });

    document.querySelectorAll('.remove-preview-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('tr.preview-row');
            if (!row) return;
            var tbody = row.parentElement;
            var removeScope = btn.getAttribute('data-remove-scope') || 'product';
            var groupKey = row.getAttribute('data-package-group') || '';

            if (removeScope === 'package') {
                if (groupKey !== '') {
                    tbody.querySelectorAll('tr.preview-row[data-package-group="' + groupKey + '"]').forEach(function(gr) {
                        gr.remove();
                    });
                } else {
                    row.remove();
                }
            } else {
                row.remove();
                if (groupKey !== '') {
                    var remainingProducts = tbody.querySelectorAll('tr.preview-product-row[data-package-group="' + groupKey + '"]');
                    if (remainingProducts.length === 0) {
                        tbody.querySelectorAll('tr.preview-package-row[data-package-group="' + groupKey + '"]').forEach(function(headerRow) {
                            headerRow.remove();
                        });
                    }
                }
            }

            reindexPreviewRows(tbody);
        });
    });

    document.querySelectorAll('.sor-pkg-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var groupKey = sel.getAttribute('data-group') || '';
            var targetId = sel.getAttribute('data-brand-target');
            var target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;

            var option = sel.options[sel.selectedIndex];
            var brandId = option ? (option.getAttribute('data-brand-id') || '') : '';
            target.value = brandId !== '' ? brandId : '0';

            var idx = (targetId || '').replace('brand_', '');
            var companyField = document.getElementById('company_' + idx);
            var brandHidden = document.getElementById('brand_hidden_' + idx);
            var companyHidden = document.getElementById('company_hidden_' + idx);
            var descField = document.getElementById('desc_' + idx);

            if (brandHidden) {
                brandHidden.value = brandId !== '' ? brandId : '0';
            }

            var companyId = (brandId !== '' && brandToCompanyMap.hasOwnProperty(brandId)) ? String(brandToCompanyMap[brandId]) : '';
            if (companyHidden) {
                companyHidden.value = companyId !== '' ? companyId : '0';
            }
            if (companyField) {
                if (companyId !== '') {
                    var label = companyNameMap.hasOwnProperty(companyId) ? companyNameMap[companyId] : '';
                    companyField.value = companyId + (label ? (' - ' + label) : '');
                } else {
                    companyField.value = '';
                }
            }

            if (descField) {
                var itemDesc = option ? (option.getAttribute('data-item-desc') || '') : '';
                descField.value = itemDesc;

                if (groupKey !== '') {
                    document.querySelectorAll('.group-desc-field[data-group="' + groupKey + '"]').forEach(function(el) {
                        el.value = itemDesc;
                    });
                }
            }

            if (groupKey !== '') {
                var selectedPkgId = sel.value || '';
                var selectedPkgName = option ? (option.textContent || '').split(' - ')[0] : '';
                var selectedDesc = option ? (option.getAttribute('data-item-desc') || '') : '';

                document.querySelectorAll('.pkg-hidden-id[data-group="' + groupKey + '"]').forEach(function(el) {
                    el.value = selectedPkgId;
                });
                document.querySelectorAll('.pkg-hidden-name[data-group="' + groupKey + '"]').forEach(function(el) {
                    el.value = selectedPkgName;
                });
                document.querySelectorAll('.pkg-hidden-desc[data-group="' + groupKey + '"]').forEach(function(el) {
                    el.value = selectedDesc;
                });
                document.querySelectorAll('.pkg-brand-hidden[data-group="' + groupKey + '"]').forEach(function(el) {
                    el.value = brandId !== '' ? brandId : '0';
                });
                document.querySelectorAll('.pkg-company-hidden[data-group="' + groupKey + '"]').forEach(function(el) {
                    el.value = companyId !== '' ? companyId : '0';
                });
            }
        });
    });

    // Browser OCR (no server setup, no API key). No progress bar UI.
    (function() {
        if (typeof pdfjsLib === 'undefined' || typeof Tesseract === 'undefined') return;

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        var fileInput = document.getElementById('import_file');
        var form = document.getElementById('sorUploadForm');
        var ocrField = document.getElementById('client_ocr_text');
        var ocrMapField = document.getElementById('client_ocr_map');
        var submitBtn = document.getElementById('sorSubmitBtn');
        if (!fileInput || !form || !ocrField || !ocrMapField || !submitBtn) return;

        var ocrRunning = false;

        function setProcessingState(isProcessing, label) {
            ocrRunning = isProcessing;
            submitBtn.disabled = isProcessing;
            submitBtn.innerHTML = isProcessing
                ? '<i class="fa-solid fa-spinner fa-spin"></i> ' + label
                : '<i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze';
        }

        function readAsArrayBuffer(file) {
            return new Promise(function(resolve, reject) {
                var reader = new FileReader();
                reader.onload = function(e) { resolve(e.target.result); };
                reader.onerror = reject;
                reader.readAsArrayBuffer(file);
            });
        }

        function basenameLower(path) {
            return String(path || '').split('/').pop().split('\\\\').pop().toLowerCase();
        }

        function extractTextFromPdfBytes(pdfBytes) {
            return pdfjsLib.getDocument({ data: pdfBytes }).promise.then(function(pdfDoc) {
                var tasks = [];
                for (var i = 1; i <= pdfDoc.numPages; i++) {
                    tasks.push(
                        pdfDoc.getPage(i).then(function(page) {
                            var viewport = page.getViewport({ scale: 2.0 });
                            var canvas = document.createElement('canvas');
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            var ctx = canvas.getContext('2d');

                            return page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function() {
                                return Tesseract.recognize(canvas, 'eng').then(function(result) {
                                    return result && result.data && result.data.text ? result.data.text : '';
                                }).catch(function() {
                                    return '';
                                });
                            });
                        })
                    );
                }

                return Promise.all(tasks).then(function(texts) {
                    return texts.join('\n').trim();
                });
            }).catch(function() {
                return '';
            });
        }

        function processSinglePdf(file) {
            setProcessingState(true, 'Processing PDF...');
            readAsArrayBuffer(file)
                .then(function(buffer) {
                    return extractTextFromPdfBytes(new Uint8Array(buffer));
                })
                .then(function(text) {
                    ocrField.value = text;
                    ocrMapField.value = '';
                    setProcessingState(false, '');
                })
                .catch(function() {
                    ocrField.value = '';
                    ocrMapField.value = '';
                    setProcessingState(false, '');
                });
        }

        function processZip(file) {
            if (typeof JSZip === 'undefined') {
                ocrField.value = '';
                ocrMapField.value = '';
                return;
            }

            setProcessingState(true, 'Processing ZIP...');
            readAsArrayBuffer(file)
                .then(function(buffer) { return JSZip.loadAsync(buffer); })
                .then(function(zip) {
                    var entryNames = Object.keys(zip.files).filter(function(name) {
                        var zf = zip.files[name];
                        return zf && !zf.dir && /\.pdf$/i.test(name);
                    });

                    var ocrMap = {};
                    var chain = Promise.resolve();

                    entryNames.forEach(function(name, idx) {
                        chain = chain.then(function() {
                            setProcessingState(true, 'Processing ZIP (' + (idx + 1) + '/' + entryNames.length + ')...');
                            return zip.files[name].async('uint8array').then(function(pdfBytes) {
                                return extractTextFromPdfBytes(pdfBytes).then(function(text) {
                                    ocrMap[name.toLowerCase()] = text;
                                    ocrMap[basenameLower(name)] = text;
                                });
                            });
                        });
                    });

                    return chain.then(function() {
                        ocrField.value = '';
                        ocrMapField.value = JSON.stringify(ocrMap);
                        setProcessingState(false, '');
                    });
                })
                .catch(function() {
                    ocrField.value = '';
                    ocrMapField.value = '';
                    setProcessingState(false, '');
                });
        }

        fileInput.addEventListener('change', function() {
            ocrField.value = '';
            ocrMapField.value = '';
            setProcessingState(false, '');

            if (!fileInput.files || !fileInput.files[0]) return;

            var file = fileInput.files[0];
            var ext = (file.name || '').split('.').pop().toLowerCase();

            if (ext === 'pdf') {
                processSinglePdf(file);
                return;
            }

            if (ext === 'zip') {
                processZip(file);
            }
        });

        form.addEventListener('submit', function(e) {
            if (!ocrRunning) return;
            e.preventDefault();
            alert('Please wait. PDF text extraction is still processing.');
        });
    })();

</script>
</body>
</html>
