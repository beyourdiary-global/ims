<?php
$currentPagePin = 20;
$pageTitle = '';
include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$redirect_page = $SITEURL . '/product_table.php';
$shortcut_page = $SITEURL . '/common_import.php';
$parentPagePinGroupId = 20;
$parentPageTitle = getPinGroupNameById($connect, $parentPagePinGroupId);
if ($parentPageTitle === '') {
    $parentPageTitle = 'Product';
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
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);
}

$action = post('actionBtn');
$importErrors = array();
$previewData = array();

if (!function_exists('parse_xlsx')) {
    function parse_xlsx($filepath)
    {
        // Helper to safely load XML without allowing external entity expansion or network access
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

        $ssXml = false;
        $sheetXml = false;
        $sharedStrings = array();
        $rows = array();

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($filepath) === true) {
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (preg_match('/xl\/worksheets\/sheet\d+\.xml/i', $filename)) {
                        $sheetXml = $zip->getFromName($filename);
                        break;
                    }
                }
                $zip->close();
            }
        }

        if (!$sheetXml) {
            // Pure-PHP fallback using PharData when ZipArchive is unavailable.
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xlsx_' . uniqid();
            if (@mkdir($tempDir)) {
                if (class_exists('PharData')) {
                    try {
                        // Copy to a temporary .zip extension so PharData recognizes the archive format
                        $tempZip = $tempDir . DIRECTORY_SEPARATOR . 'temp.zip';
                        copy($filepath, $tempZip);
                        $phar = new \PharData($tempZip);
                        $phar->extractTo($tempDir, null, true);
                        @unlink($tempZip);
                    } catch (Exception $e) {
                        // Extraction failed
                    }
                }

                $ssPath = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
                if (file_exists($ssPath)) {
                    $ssXml = @file_get_contents($ssPath);
                }

                $worksheetsDir = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR;
                if (is_dir($worksheetsDir)) {
                    $files = @scandir($worksheetsDir);
                    if (is_array($files)) {
                        foreach ($files as $file) {
                            if (preg_match('/^sheet\d+\.xml$/i', (string) $file)) {
                                $sheetXml = @file_get_contents($worksheetsDir . $file);
                                break;
                            }
                        }
                    }
                }

                $deleteDir = function ($dir) use (&$deleteDir) {
                    if (!is_dir($dir)) return;
                    $items = @scandir($dir);
                    if (!is_array($items)) return;
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $path = $dir . DIRECTORY_SEPARATOR . $item;
                        if (is_dir($path)) {
                            $deleteDir($path);
                        } else {
                            @unlink($path);
                        }
                    }
                    @rmdir($dir);
                };
                $deleteDir($tempDir);
            }

            if (!$sheetXml) {
                return $rows;
            }
        }

        if (!$sheetXml) {
            return array('error' => 'Unable to extract Excel file.');
        }

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
            return array('error' => 'Worksheet data is empty or corrupted.');
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
}

if (!function_exists('getReverseMapping')) {
    function getReverseMapping($connect, $table, $idCol, $nameCol)
    {
        $map = array();
        $result = mysqli_query($connect, "SELECT `$idCol`, `$nameCol` FROM `$table`");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $map[strtolower(trim((string) $row[$nameCol]))] = $row[$idCol];
            }
        }
        return $map;
    }
}

if (!function_exists('resolveForeignId')) {
    function resolveForeignId($value, $reverseMap)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (ctype_digit($value)) {
            return $value;
        }
        $key = strtolower($value);
        return isset($reverseMap[$key]) ? (string) $reverseMap[$key] : '';
    }
}

if (!function_exists('resolveDisplayValue')) {
    function resolveDisplayValue($rawValue, $reverseMap, $idToNameMap = array())
    {
        $rawValue = trim((string) $rawValue);
        $resolvedId = resolveForeignId($rawValue, $reverseMap);

        if ($rawValue === '') {
            return array('id' => '', 'display' => '', 'error' => '');
        }

        if ($resolvedId === '') {
            return array('id' => '', 'display' => $rawValue, 'error' => 'Value not found in database.');
        }

        if (isset($idToNameMap[(int) $resolvedId])) {
            return array('id' => (string) $resolvedId, 'display' => (string) $idToNameMap[(int) $resolvedId], 'error' => '');
        }

        return array('id' => (string) $resolvedId, 'display' => $rawValue, 'error' => '');
    }
}

if (!function_exists('normalizeImportExcelDate')) {
    function normalizeImportExcelDate($value)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (is_numeric($raw)) {
            $serial = (float) $raw;
            if ($serial > 0 && $serial < 2958465) {
                $timestamp = (int) round(($serial - 25569) * 86400);
                if ($timestamp > 0) {
                    return gmdate('Y-m-d', $timestamp);
                }
            }
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return $raw;
    }
}

if (!function_exists('normalizeLinkedId')) {
    function normalizeLinkedId($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0') {
            return 0;
        }
        return (int) $value;
    }
}

if (!function_exists('normalizeNumeric')) {
    function normalizeNumeric($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = str_replace(',', '', $value);
        if (!is_numeric($value)) {
            return $value;
        }
        return (string) (float) $value;
    }
}

if (!function_exists('normalizeBarcodeStatus')) {
    function normalizeBarcodeStatus($value)
    {
        $v = strtolower(trim((string) $value));
        if ($v === 'yes' || $v === 'y' || $v === '1') {
            return 'Yes';
        }
        return 'No';
    }
}

$brandRevMap = getReverseMapping($connect, BRAND, 'id', 'name');
$weightRevMap = getReverseMapping($connect, WGT_UNIT, 'id', 'unit');
$currencyRevMap = getReverseMapping($connect, CUR_UNIT, 'id', 'unit');
$categoryRevMap = getReverseMapping($connect, PROD_CATEGORY, 'id', 'name');
$parentRevMap = getReverseMapping($connect, PROD, 'id', 'name');

$brandNameMap = array();
$weightNameMap = array();
$currencyNameMap = array();
$categoryNameMap = array();
$parentNameMap = array();

$rstBrand = mysqli_query($connect, "SELECT id, name FROM " . BRAND . " WHERE status='A'");
if ($rstBrand) while ($r = mysqli_fetch_assoc($rstBrand)) $brandNameMap[(int)$r['id']] = (string)$r['name'];
$rstWeight = mysqli_query($connect, "SELECT id, unit FROM " . WGT_UNIT . " WHERE status='A'");
if ($rstWeight) while ($r = mysqli_fetch_assoc($rstWeight)) $weightNameMap[(int)$r['id']] = (string)$r['unit'];
$rstCurrency = mysqli_query($connect, "SELECT id, unit FROM " . CUR_UNIT . " WHERE status='A'");
if ($rstCurrency) while ($r = mysqli_fetch_assoc($rstCurrency)) $currencyNameMap[(int)$r['id']] = (string)$r['unit'];
$rstCategory = mysqli_query($connect, "SELECT id, name FROM " . PROD_CATEGORY . " WHERE status='A'");
if ($rstCategory) while ($r = mysqli_fetch_assoc($rstCategory)) $categoryNameMap[(int)$r['id']] = (string)$r['name'];
$rstParent = mysqli_query($connect, "SELECT id, name FROM " . PROD . " WHERE status='A'");
if ($rstParent) while ($r = mysqli_fetch_assoc($rstParent)) $parentNameMap[(int)$r['id']] = (string)$r['name'];

$existingProducts = array();
$dbQuery = mysqli_query($connect, "SELECT * FROM " . PROD);
if ($dbQuery) {
    while ($dbRow = mysqli_fetch_assoc($dbQuery)) {
        $existingProducts[$dbRow['id']] = $dbRow;
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
            $parsedRows = parse_xlsx($fileTmpPath);
            if (isset($parsedRows['error'])) {
                $importErrors[] = $parsedRows['error'];
            } elseif (is_array($parsedRows) && count($parsedRows) > 1) {
                $headers = array_map(function ($h) {
                    return strtoupper(trim((string) $h));
                }, isset($parsedRows[0]) ? $parsedRows[0] : array());
                $indexMap = array_flip($headers);

                $getCol = function ($row, $name, $fallback = '') use ($indexMap) {
                    $idx = isset($indexMap[$name]) ? $indexMap[$name] : -1;
                    if ($idx < 0 || !isset($row[$idx])) {
                        return $fallback;
                    }
                    return trim((string) $row[$idx]);
                };

                for ($rowIndex = 1; $rowIndex < count($parsedRows); $rowIndex++) {
                    $data = $parsedRows[$rowIndex];

                    $id = $getCol($data, 'S/N', $getCol($data, 'ID', ''));
                    $name = $getCol($data, 'NAME', '');
                    if ($id === '' && $name === '') {
                        continue;
                    }

                    $brandInfo = resolveDisplayValue($getCol($data, 'BRAND', ''), $brandRevMap, $brandNameMap);
                    $weightInfo = resolveDisplayValue($getCol($data, 'WEIGHT UNIT', ''), $weightRevMap, $weightNameMap);
                    $currencyInfo = resolveDisplayValue($getCol($data, 'CURRENCY UNIT', ''), $currencyRevMap, $currencyNameMap);
                    $categoryInfo = resolveDisplayValue($getCol($data, 'PRODUCT CATEGORY', ''), $categoryRevMap, $categoryNameMap);
                    $parentInfo = resolveDisplayValue($getCol($data, 'PARENT PRODUCT', ''), $parentRevMap, $parentNameMap);

                    $fieldErrors = array();
                    if ($brandInfo['error'] !== '') $fieldErrors['brand_display'] = 'Brand not found in database.';
                    if ($weightInfo['error'] !== '') $fieldErrors['weight_unit_display'] = 'Weight unit not found in database.';
                    if ($currencyInfo['error'] !== '') $fieldErrors['currency_unit_display'] = 'Currency unit not found in database.';
                    if ($categoryInfo['error'] !== '') $fieldErrors['product_category_display'] = 'Product category not found in database.';
                    if ($parentInfo['error'] !== '') $fieldErrors['parent_product_display'] = 'Parent product not found in database.';

                    $rowData = array(
                        'id' => $id,
                        'name' => $name,
                        'brand' => $brandInfo['id'],
                        'brand_display' => $brandInfo['display'],
                        'weight' => $getCol($data, 'WEIGHT', ''),
                        'weight_unit' => $weightInfo['id'],
                        'weight_unit_display' => $weightInfo['display'],
                        'cost' => $getCol($data, 'COST', '0.00'),
                        'currency_unit' => $currencyInfo['id'],
                        'currency_unit_display' => $currencyInfo['display'],
                        'barcode_status' => $getCol($data, 'BARCODE STATUS', 'No') === 'Yes' ? 'Yes' : 'No',
                        'barcode_slot' => $getCol($data, 'BARCODE SLOT', ''),
                        'product_category' => $categoryInfo['id'],
                        'product_category_display' => $categoryInfo['display'],
                        'expire_date' => normalizeImportExcelDate($getCol($data, 'EXPIRE DATE', '')),
                        'parent_product' => $parentInfo['id'],
                        'parent_product_display' => $parentInfo['display'],
                    );

                    $isNew = ($id === '' || !isset($existingProducts[$id]));
                    $changes = array();

                    if (!$isNew) {
                        $ex = $existingProducts[$id];
                        if (trim((string)($ex['name'] ?? '')) !== trim((string)$rowData['name'])) $changes['name'] = true;
                        if (normalizeLinkedId($ex['brand'] ?? '') !== normalizeLinkedId($rowData['brand'])) $changes['brand'] = true;
                        if (normalizeNumeric($ex['weight'] ?? '') !== normalizeNumeric($rowData['weight'])) $changes['weight'] = true;
                        if (normalizeLinkedId($ex['weight_unit'] ?? '') !== normalizeLinkedId($rowData['weight_unit'])) $changes['weight_unit'] = true;
                        if (normalizeNumeric($ex['cost'] ?? '') !== normalizeNumeric($rowData['cost'])) $changes['cost'] = true;
                        if (normalizeLinkedId($ex['currency_unit'] ?? '') !== normalizeLinkedId($rowData['currency_unit'])) $changes['currency_unit'] = true;
                        if (normalizeBarcodeStatus($ex['barcode_status'] ?? '') !== normalizeBarcodeStatus($rowData['barcode_status'])) $changes['barcode_status'] = true;
                        if (trim((string)($ex['barcode_slot'] ?? '')) !== trim((string)$rowData['barcode_slot'])) $changes['barcode_slot'] = true;
                        if (normalizeLinkedId($ex['product_category'] ?? '') !== normalizeLinkedId($rowData['product_category'])) $changes['product_category'] = true;
                        if (normalizeImportExcelDate($ex['expire_date'] ?? '') !== normalizeImportExcelDate($rowData['expire_date'])) $changes['expire_date'] = true;
                        if (normalizeLinkedId($ex['parent_product'] ?? '') !== normalizeLinkedId($rowData['parent_product'])) $changes['parent_product'] = true;
                    }

                    if ($isNew || count($changes) > 0) {
                        $rowData['is_new'] = $isNew ? '1' : '0';
                        $rowData['changes'] = $changes;
                        $rowData['field_errors'] = $fieldErrors;
                        $previewData[] = $rowData;
                    }
                }

                if (empty($previewData) && empty($importErrors)) {
                    $importErrors[] = 'No new records or changes detected.';
                }
            } else {
                $importErrors[] = 'No rows found in uploaded file.';
            }
        }
    }
} else if ($action === 'update') {
    $postData = isset($_POST['data']) ? $_POST['data'] : array();
    $previewData = array();
    $hasValidationError = false;

    foreach ($postData as $row) {
        $fieldErrors = array();
        $isNew = (isset($row['is_new']) && $row['is_new'] === '1') ? '1' : '0';

        $nameRaw = trim((string)(isset($row['name']) ? $row['name'] : ''));
        $weightRaw = trim((string)(isset($row['weight']) ? $row['weight'] : ''));
        $costRaw = trim((string)(isset($row['cost']) ? $row['cost'] : ''));
        $expireDateRaw = trim((string)(isset($row['expire_date']) ? $row['expire_date'] : ''));
        if ($nameRaw === '') {
            $fieldErrors['name'] = 'Product Name field is required!';
        }

        $brandDisplay = trim((string)(isset($row['brand_display']) ? $row['brand_display'] : ''));
        $weightUnitDisplay = trim((string)(isset($row['weight_unit_display']) ? $row['weight_unit_display'] : ''));
        $currencyDisplay = trim((string)(isset($row['currency_unit_display']) ? $row['currency_unit_display'] : ''));
        $categoryDisplay = trim((string)(isset($row['product_category_display']) ? $row['product_category_display'] : ''));
        $parentDisplay = trim((string)(isset($row['parent_product_display']) ? $row['parent_product_display'] : ''));

        $brandResolved = resolveForeignId($brandDisplay, $brandRevMap);
        $weightUnitResolved = resolveForeignId($weightUnitDisplay, $weightRevMap);
        $currencyResolved = resolveForeignId($currencyDisplay, $currencyRevMap);
        $categoryResolved = resolveForeignId($categoryDisplay, $categoryRevMap);
        $parentResolved = resolveForeignId($parentDisplay, $parentRevMap);

        if ($brandDisplay === '') {
            $fieldErrors['brand_display'] = 'Product Brand field is required!';
        } else if ($brandResolved === '') {
            $fieldErrors['brand_display'] = 'Brand not found in database.';
        }
        if ($weightRaw === '') {
            $fieldErrors['weight'] = 'Product Weight field is required!';
        }
        if ($weightUnitDisplay === '') {
            $fieldErrors['weight_unit_display'] = 'Product Weight Unit field is required!';
        } else if ($weightUnitResolved === '') {
            $fieldErrors['weight_unit_display'] = 'Weight unit not found in database.';
        }
        if ($costRaw === '') {
            $fieldErrors['cost'] = 'Product Cost field is required!';
        }
        if ($currencyDisplay === '') {
            $fieldErrors['currency_unit_display'] = 'Product Currency Unit field is required!';
        } else if ($currencyResolved === '') {
            $fieldErrors['currency_unit_display'] = 'Currency unit not found in database.';
        }
        if ($expireDateRaw === '') {
            $fieldErrors['expire_date'] = 'Product Expire Date field is required!';
        }
        if ($categoryDisplay !== '' && $categoryResolved === '') $fieldErrors['product_category_display'] = 'Product category not found in database.';
        if ($parentDisplay !== '' && $parentResolved === '') $fieldErrors['parent_product_display'] = 'Parent product not found in database.';

        if (count($fieldErrors) > 0) {
            $hasValidationError = true;
        }

        $row['is_new'] = $isNew;
        $row['field_errors'] = $fieldErrors;
        $row['changes'] = isset($row['changes']) && is_array($row['changes']) ? $row['changes'] : array();
        $previewData[] = $row;
    }

    if ($hasValidationError) {
        $importErrors[] = 'Please correct the highlighted field errors before update.';
        $action = 'preview';
    } else {
    $successCount = 0;
    $insertCount = 0;

    foreach ($postData as $row) {
        $id = mysqli_real_escape_string($connect, isset($row['id']) ? $row['id'] : '');
        $is_new = (isset($row['is_new']) && $row['is_new'] == '1');

        $name = mysqli_real_escape_string($connect, isset($row['name']) ? $row['name'] : '');
        if ($name === '') {
            continue;
        }

        $brandDisplay = isset($row['brand_display']) ? $row['brand_display'] : (isset($row['brand']) ? $row['brand'] : '');
        $brand = mysqli_real_escape_string($connect, resolveForeignId($brandDisplay, $brandRevMap));
        $weight = mysqli_real_escape_string($connect, isset($row['weight']) ? $row['weight'] : '');
        $weightUnitDisplay = isset($row['weight_unit_display']) ? $row['weight_unit_display'] : (isset($row['weight_unit']) ? $row['weight_unit'] : '');
        $weight_unit = mysqli_real_escape_string($connect, resolveForeignId($weightUnitDisplay, $weightRevMap));
        $cost = mysqli_real_escape_string($connect, isset($row['cost']) && $row['cost'] !== '' ? $row['cost'] : '0.00');
        $currencyDisplay = isset($row['currency_unit_display']) ? $row['currency_unit_display'] : (isset($row['currency_unit']) ? $row['currency_unit'] : '');
        $currency_unit = mysqli_real_escape_string($connect, resolveForeignId($currencyDisplay, $currencyRevMap));
        $barcode_status = (isset($row['barcode_status']) && $row['barcode_status'] === 'Yes') ? 'Yes' : 'No';
        $barcode_slot = mysqli_real_escape_string($connect, isset($row['barcode_slot']) ? $row['barcode_slot'] : '');
        $categoryDisplay = isset($row['product_category_display']) ? $row['product_category_display'] : (isset($row['product_category']) ? $row['product_category'] : '');
        $product_category = mysqli_real_escape_string($connect, resolveForeignId($categoryDisplay, $categoryRevMap));
        $expire_date = mysqli_real_escape_string($connect, normalizeImportExcelDate(isset($row['expire_date']) ? $row['expire_date'] : ''));
        $parentDisplay = isset($row['parent_product_display']) ? $row['parent_product_display'] : (isset($row['parent_product']) ? $row['parent_product'] : '');
        $parent_product = mysqli_real_escape_string($connect, resolveForeignId($parentDisplay, $parentRevMap));

        if ($is_new) {
            $query = "INSERT INTO " . PROD . " (name, brand, weight, weight_unit, cost, currency_unit, barcode_status, barcode_slot, product_category, expire_date, parent_product, create_by, create_date, create_time, status) VALUES ('" . $name . "', '" . $brand . "', '" . $weight . "', '" . $weight_unit . "', '" . $cost . "', '" . $currency_unit . "', '" . $barcode_status . "', '" . $barcode_slot . "', '" . $product_category . "', '" . $expire_date . "', '" . $parent_product . "', '" . USER_ID . "', curdate(), curtime(), 'A')";
            if (mysqli_query($connect, $query)) {
                $insertCount++;
            }
        } else {
            if ($id === '') {
                continue;
            }
            $query = "UPDATE " . PROD . " SET name='" . $name . "', brand='" . $brand . "', weight='" . $weight . "', weight_unit='" . $weight_unit . "', cost='" . $cost . "', currency_unit='" . $currency_unit . "', barcode_status='" . $barcode_status . "', barcode_slot='" . $barcode_slot . "', product_category='" . $product_category . "', expire_date='" . $expire_date . "', parent_product='" . $parent_product . "', update_by='" . USER_ID . "', update_date=curdate(), update_time=curtime() WHERE id='" . $id . "'";
            if (mysqli_query($connect, $query)) {
                $successCount++;
            }
        }
    }

    generateDBData(PROD, $connect);

    $log = [
        'log_act' => 'Import',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => 'Bulk import update',
        'query_table' => PROD,
        'newval' => 'Inserted=' . (int) $insertCount . ', Updated=' . (int) $successCount,
        'act_msg' => USER_NAME . " imported product data [ <b>New Added = " . (int) $insertCount . ", Updated = " . (int) $successCount . "</b> ] into <b><i>" . PROD . " Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);

    echo "<script>alert('Import Complete! Successfully added " . $insertCount . " new products and updated " . $successCount . " existing products.'); window.location.href = '" . $redirect_page . "';</script>";
    exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .highlight-change { background-color: #fff3cd !important; border-color: #ffecb5 !important; color: #664d03 !important; }
        .row-new { background-color: #d1e7dd !important; }
        .row-update { border-left: 4px solid #ffc107 !important; }
        .field-error { font-size: 12px; color: #dc3545; margin-top: 3px; }
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
                    <?php foreach ($importErrors as $error) { echo "<div>- " . htmlspecialchars($error) . "</div>"; } ?>
                </div>
            <?php } ?>

            <?php if ($action === 'preview' && !empty($previewData)) { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Preview Changes</h5>
                        <form method="post" autocomplete="off">
                            <?php foreach ($previewData as $index => $row) {
                                $chg = isset($row['changes']) && is_array($row['changes']) ? $row['changes'] : array();
                                $ferr = isset($row['field_errors']) && is_array($row['field_errors']) ? $row['field_errors'] : array();
                            ?>
                                <div class="card mb-3 <?= $row['is_new'] === '1' ? 'row-new' : 'row-update' ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <h6 class="mb-0">Record #<?= $index + 1 ?></h6>
                                            <?= $row['is_new'] === '1' ? '<span class="badge bg-success">NEW</span>' : '<span class="badge bg-warning text-dark">MODIFIED</span>' ?>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-2">
                                                <label class="form-label">ID</label>
                                                <input type="text" class="form-control" name="data[<?= $index ?>][id]" value="<?= htmlspecialchars($row['id']) ?>" <?= $row['is_new'] === '1' ? '' : 'readonly' ?>>
                                                <input type="hidden" name="data[<?= $index ?>][is_new]" value="<?= htmlspecialchars($row['is_new']) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Name*</label>
                                                <input type="text" class="form-control <?= isset($chg['name']) ? 'highlight-change' : '' ?> js-required-field" data-required-field="name" data-required-message="Product Name field is required!" name="data[<?= $index ?>][name]" value="<?= htmlspecialchars($row['name']) ?>" required>
                                                <?php if (isset($ferr['name'])) { ?><div class="field-error" data-field="name"><?= htmlspecialchars($ferr['name']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Brand*</label>
                                                <div class="autocomplete">
                                                    <input type="text" id="pi_brand_<?= $index ?>" class="form-control <?= isset($chg['brand']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search js-required-field" data-lookup-field="brand_display" data-required-field="brand_display" data-required-message="Product Brand field is required!" data-search-type="name" data-db-table="<?= BRAND ?>" name="data[<?= $index ?>][brand_display]" value="<?= htmlspecialchars($row['brand_display']) ?>" required>
                                                    <input type="hidden" id="pi_brand_<?= $index ?>_hidden" value="">
                                                </div>
                                                <?php if (isset($ferr['brand_display'])) { ?><div class="field-error" data-field="brand_display"><?= htmlspecialchars($ferr['brand_display']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Weight*</label>
                                                <input type="text" class="form-control <?= isset($chg['weight']) ? 'highlight-change' : '' ?> js-required-field" data-required-field="weight" data-required-message="Product Weight field is required!" name="data[<?= $index ?>][weight]" value="<?= htmlspecialchars($row['weight']) ?>" required>
                                                <?php if (isset($ferr['weight'])) { ?><div class="field-error" data-field="weight"><?= htmlspecialchars($ferr['weight']) ?></div><?php } ?>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Weight Unit*</label>
                                                <div class="autocomplete">
                                                    <input type="text" id="pi_weight_unit_<?= $index ?>" class="form-control <?= isset($chg['weight_unit']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search js-required-field" data-lookup-field="weight_unit_display" data-required-field="weight_unit_display" data-required-message="Product Weight Unit field is required!" data-search-type="unit" data-db-table="<?= WGT_UNIT ?>" name="data[<?= $index ?>][weight_unit_display]" value="<?= htmlspecialchars($row['weight_unit_display']) ?>" required>
                                                    <input type="hidden" id="pi_weight_unit_<?= $index ?>_hidden" value="">
                                                </div>
                                                <?php if (isset($ferr['weight_unit_display'])) { ?><div class="field-error" data-field="weight_unit_display"><?= htmlspecialchars($ferr['weight_unit_display']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Cost*</label>
                                                <input type="text" class="form-control <?= isset($chg['cost']) ? 'highlight-change' : '' ?> js-required-field" data-required-field="cost" data-required-message="Product Cost field is required!" name="data[<?= $index ?>][cost]" value="<?= htmlspecialchars($row['cost']) ?>" required>
                                                <?php if (isset($ferr['cost'])) { ?><div class="field-error" data-field="cost"><?= htmlspecialchars($ferr['cost']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Currency Unit*</label>
                                                <div class="autocomplete">
                                                    <input type="text" id="pi_currency_unit_<?= $index ?>" class="form-control <?= isset($chg['currency_unit']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search js-required-field" data-lookup-field="currency_unit_display" data-required-field="currency_unit_display" data-required-message="Product Currency Unit field is required!" data-search-type="unit" data-db-table="<?= CUR_UNIT ?>" name="data[<?= $index ?>][currency_unit_display]" value="<?= htmlspecialchars($row['currency_unit_display']) ?>" required>
                                                    <input type="hidden" id="pi_currency_unit_<?= $index ?>_hidden" value="">
                                                </div>
                                                <?php if (isset($ferr['currency_unit_display'])) { ?><div class="field-error" data-field="currency_unit_display"><?= htmlspecialchars($ferr['currency_unit_display']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Barcode Status</label>
                                                <select class="form-control <?= isset($chg['barcode_status']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][barcode_status]">
                                                    <option value="No" <?= $row['barcode_status'] === 'No' ? 'selected' : '' ?>>No</option>
                                                    <option value="Yes" <?= $row['barcode_status'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                                </select>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="form-label">Barcode Slot</label>
                                                <input type="text" class="form-control <?= isset($chg['barcode_slot']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][barcode_slot]" value="<?= htmlspecialchars($row['barcode_slot']) ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Product Category</label>
                                                <input type="text" class="form-control <?= isset($chg['product_category']) ? 'highlight-change' : '' ?> js-lookup-single" list="productImportCategoryList" data-lookup-field="product_category_display" name="data[<?= $index ?>][product_category_display]" value="<?= htmlspecialchars($row['product_category_display']) ?>">
                                                <?php if (isset($ferr['product_category_display'])) { ?><div class="field-error" data-field="product_category_display"><?= htmlspecialchars($ferr['product_category_display']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Expire Date*</label>
                                                <input type="date" class="form-control <?= isset($chg['expire_date']) ? 'highlight-change' : '' ?> js-required-field" data-required-field="expire_date" data-required-message="Product Expire Date field is required!" name="data[<?= $index ?>][expire_date]" value="<?= htmlspecialchars($row['expire_date']) ?>" required>
                                                <?php if (isset($ferr['expire_date'])) { ?><div class="field-error" data-field="expire_date"><?= htmlspecialchars($ferr['expire_date']) ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Parent Product</label>
                                                <div class="autocomplete">
                                                    <input type="text" id="pi_parent_product_<?= $index ?>" class="form-control <?= isset($chg['parent_product']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search" data-lookup-field="parent_product_display" data-search-type="name" data-db-table="<?= PROD ?>" name="data[<?= $index ?>][parent_product_display]" value="<?= htmlspecialchars($row['parent_product_display']) ?>">
                                                    <input type="hidden" id="pi_parent_product_<?= $index ?>_hidden" value="">
                                                </div>
                                                <?php if (isset($ferr['parent_product_display'])) { ?><div class="field-error" data-field="parent_product_display"><?= htmlspecialchars($ferr['parent_product_display']) ?></div><?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <datalist id="productImportCategoryList">
                                <?php foreach ($categoryNameMap as $optName) { ?>
                                    <option value="<?= htmlspecialchars($optName) ?>"></option>
                                <?php } ?>
                            </datalist>
                            <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                <a href="product_import.php" class="btn btn-lg btn-rounded btn-secondary px-4">Cancel</a>
                                <button class="btn btn-lg btn-rounded btn-success px-4" type="submit" name="actionBtn" value="update">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Execute Bulk Import & Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } else if ($action !== 'preview') { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 1: Upload Edited Excel File</h5>
                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-bold" for="import_file">Select Excel (.xlsx) File</label>
                                    <input class="form-control form-control-lg" type="file" name="import_file" id="import_file" accept=".xlsx" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="preview">
                                        <i class="fa-solid fa-magnifying-glass"></i> Scan & Preview File
                                    </button>
                                </div>
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

    // Pass PHP data to the external Javascript file
    window.__PRODUCT_IMPORT_CONFIG = {
        siteUrl: <?= json_encode($SITEURL) ?>,
        previewServerRows: <?= ($action === 'preview' && !empty($previewData)) ? json_encode($previewData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null' ?>,
        lookupMeta: {
            brand_display: {
                names: <?= json_encode(array_values($brandNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($brandNameMap))) ?>
            },
            weight_unit_display: {
                names: <?= json_encode(array_values($weightNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($weightNameMap))) ?>
            },
            currency_unit_display: {
                names: <?= json_encode(array_values($currencyNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($currencyNameMap))) ?>
            },
            product_category_display: {
                names: <?= json_encode(array_values($categoryNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($categoryNameMap))) ?>
            },
            parent_product_display: {
                names: <?= json_encode(array_values($parentNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($parentNameMap))) ?>
            }
        }
    };

    preloader(300, '');
    <?php include "js/product_import.js"; ?>
</script>
</html>