<?php
$pageTitle = "Package Import";
include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

$redirect_page = $SITEURL . '/package_table.php';
$pinAccess = checkCurrentPin($connect, 'Package');
if (!is_array($pinAccess)) {
    $pinAccess = array();
}
if (!isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import this page.");location.href = "' . $redirect_page . '";</script>';
    exit;
}

$action = post('actionBtn');
$importErrors = [];
$previewData = [];

// Advanced Native XLSX Reader with Windows OS Bypass (PRESERVED)
function parse_xlsx($filepath) {
    $ssXml = false;
    $sheetXml = false;
    $sharedStrings = [];
    $rows = [];

    // Attempt 1: Standard PHP Engine
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

    // Attempt 2: OS-Level Windows Bypass
    if (!$sheetXml) {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xlsx_' . uniqid();
        if (mkdir($tempDir)) {
            $cmd = 'tar -xf ' . escapeshellarg($filepath) . ' -C ' . escapeshellarg($tempDir) . ' 2>&1';
            shell_exec($cmd);

            $ssPath = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
            if (file_exists($ssPath)) $ssXml = file_get_contents($ssPath);
            
            $worksheetsDir = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR;
            if (is_dir($worksheetsDir)) {
                $files = scandir($worksheetsDir);
                foreach ($files as $file) {
                    if (preg_match('/^sheet\d+\.xml$/i', $file)) {
                        $sheetXml = file_get_contents($worksheetsDir . $file);
                        break;
                    }
                }
            }

            $deleteDir = function($dir) use (&$deleteDir) {
                if (!is_dir($dir)) return;
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item == '.' || $item == '..') continue;
                    $path = $dir . DIRECTORY_SEPARATOR . $item;
                    if (is_dir($path)) $deleteDir($path); else @unlink($path);
                }
                @rmdir($dir);
            };
            $deleteDir($tempDir);
        }
    }

    if (!$sheetXml) return ['error' => 'CRITICAL ERROR: PHP could not extract the Excel file. Please save your file as CSV instead.']; 
    
    if ($ssXml !== false) {
        $xml = simplexml_load_string($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $val) {
                $str = '';
                if (isset($val->t)) $str .= (string)$val->t;
                elseif (isset($val->r)) foreach ($val->r as $r) if(isset($r->t)) $str .= (string)$r->t;
                $sharedStrings[] = $str;
            }
        }
    }
    
    $xml = simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData->row)) return ['error' => 'Worksheet data is empty or corrupted.']; 
    
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $colIndex = 0;
        foreach ($row->c as $c) {
            $r = (string)$c['r'];
            if ($r) {
                $letter = preg_replace('/[0-9]/', '', $r);
                $idx = 0;
                $len = strlen($letter);
                for($i=0; $i<$len; $i++) { $idx = $idx * 26 + (ord($letter[$i]) - 64); }
                $idx -= 1;
            } else {
                $idx = $colIndex;
            }
            while ($colIndex < $idx) { $rowData[$colIndex] = ''; $colIndex++; }
            $v = (string)$c->v;
            if (isset($c['t']) && (string)$c['t'] == 's') $v = isset($sharedStrings[$v]) ? $sharedStrings[$v] : '';
            elseif (isset($c['t']) && (string)$c['t'] == 'inlineStr') $v = isset($c->is->t) ? (string)$c->is->t : '';
            $rowData[$colIndex] = $v;
            $colIndex++;
        }
        $rows[] = $rowData;
    }
    return $rows;
}

// Reverse Maps
function getReverseMapping($connect, $table, $idCol, $nameCol) {
    $map = [];
    $result = mysqli_query($connect, "SELECT `$idCol`, `$nameCol` FROM `$table`");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $map[strtolower(trim((string)$row[$nameCol]))] = $row[$idCol];
        }
    }
    return $map;
}

function getIdNameMapping($connect, $table, $idCol, $nameCol) {
    $map = [];
    $result = mysqli_query($connect, "SELECT `$idCol`, `$nameCol` FROM `$table`");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $map[(int)$row[$idCol]] = (string)$row[$nameCol];
        }
    }
    return $map;
}

function resolveMapValue($raw, $reverseMap) {
    $raw = trim((string) $raw);
    if ($raw === '') return '';
    if (ctype_digit($raw)) return $raw;
    $k = strtolower($raw);
    return isset($reverseMap[$k]) ? (string) $reverseMap[$k] : '';
}

$brandRevMap = getReverseMapping($connect, BRAND, 'id', 'name');
$currencyRevMap = getReverseMapping($connect, CUR_UNIT, 'id', 'unit');
$productRevMap = getReverseMapping($connect, PROD, 'id', 'name');
$brandNameMap = getIdNameMapping($connect, BRAND, 'id', 'name');
$currencyNameMap = getIdNameMapping($connect, CUR_UNIT, 'id', 'unit');
$productNameMap = getIdNameMapping($connect, PROD, 'id', 'name');

// Fetch Current Database State for Comparison
$existingPackages = [];
$dbQuery = mysqli_query($connect, "SELECT * FROM " . PKG);
if ($dbQuery) {
    while ($dbRow = mysqli_fetch_assoc($dbQuery)) {
        $existingPackages[$dbRow['id']] = $dbRow;
    }
}

// Step 1: Parse the Uploaded File & Compare
if ($action === 'preview') {
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please choose a valid Excel (.xlsx) file to upload.';
    } else {
        $fileTmpPath = $_FILES['import_file']['tmp_name'];
        $fileExtension = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));

        if ($fileExtension === 'xlsx') {
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

                foreach ($parsedRows as $rowIndex => $data) {
                    if ($rowIndex === 0) continue; // Skip header row

                    $id = $getCol($data, 'S/N', $getCol($data, 'ID', ''));
                    $name = $getCol($data, 'NAME', '');

                    // Only process rows that have at least a Name or an ID (Allows new records to be added at the bottom!)
                    if (!empty($id) || !empty($name)) { 
                        
                        $isNew = empty($id) || !isset($existingPackages[$id]);
                        
                        $csvBrand = $getCol($data, 'BRAND', '');
                        $csvPriceCurr = $getCol($data, 'CURRENCY UNIT', $getCol($data, 'PRICE CURR', ''));
                        $csvCostCurr = $getCol($data, 'COST CURR', $getCol($data, 'COST CURRENCY', ''));
                        $csvProducts = $getCol($data, 'PRODUCT', $getCol($data, 'PRODUCTS INCLUDED', ''));

                        $dbBrandId = resolveMapValue($csvBrand, $brandRevMap);
                        $dbPriceCurrId = resolveMapValue($csvPriceCurr, $currencyRevMap);
                        $dbCostCurrId = resolveMapValue($csvCostCurr, $currencyRevMap);
                        $fieldErrors = [];

                        if ($csvBrand !== '' && $dbBrandId === '') {
                            $fieldErrors['brand_name'] = 'Brand not found in database.';
                        }
                        if ($csvPriceCurr !== '' && $dbPriceCurrId === '') {
                            $fieldErrors['price_curr_name'] = 'Price currency not found in database.';
                        }
                        if ($csvCostCurr !== '' && $dbCostCurrId === '') {
                            $fieldErrors['cost_curr_name'] = 'Cost currency not found in database.';
                        }

                        $brandDisplay = $csvBrand;
                        if ($dbBrandId !== '' && isset($brandNameMap[(int)$dbBrandId])) {
                            $brandDisplay = (string) $brandNameMap[(int)$dbBrandId];
                        }
                        $priceCurrDisplay = $csvPriceCurr;
                        if ($dbPriceCurrId !== '' && isset($currencyNameMap[(int)$dbPriceCurrId])) {
                            $priceCurrDisplay = (string) $currencyNameMap[(int)$dbPriceCurrId];
                        }
                        $costCurrDisplay = $csvCostCurr;
                        if ($dbCostCurrId !== '' && isset($currencyNameMap[(int)$dbCostCurrId])) {
                            $costCurrDisplay = (string) $currencyNameMap[(int)$dbCostCurrId];
                        }

                        // Reverse lookup product IDs
                        $dbProductIds = [];
                        $productDisplayNames = [];
                        if (!empty($csvProducts)) {
                            $prodNames = array_map('trim', explode(',', $csvProducts));
                            foreach ($prodNames as $pn) {
                                if ($pn === '') {
                                    continue;
                                }
                                if (ctype_digit($pn)) {
                                    $pid = (int) $pn;
                                    $dbProductIds[] = $pid;
                                    $productDisplayNames[] = isset($productNameMap[$pid]) ? $productNameMap[$pid] : $pn;
                                } else if (isset($productRevMap[strtolower($pn)])) {
                                    $pid = (int) $productRevMap[strtolower($pn)];
                                    $dbProductIds[] = $pid;
                                    $productDisplayNames[] = isset($productNameMap[$pid]) ? $productNameMap[$pid] : $pn;
                                } else {
                                    $fieldErrors['product_names'] = "Product '$pn' not found in database.";
                                    $productDisplayNames[] = $pn;
                                }
                            }
                        }
                        $dbProductIds = array_values(array_unique($dbProductIds));
                        sort($dbProductIds); // Standardize array order for comparison
                        $dbProductString = implode(',', $dbProductIds);

                        // Variables to check
                        $item_code = $getCol($data, 'ITEM CODE', '');
                        $item_description = $getCol($data, 'ITEM DESCRIPTION', '');
                        $price = $getCol($data, 'PRICE', '0.00');
                        $cost = $getCol($data, 'COST', '0.00');
                        $agent_cost = $getCol($data, 'AGENT COST', '0.00');
                        $barcode_slot_total = $getCol($data, 'BARCODE SLOT TOTAL', '0');
                        $remark = $getCol($data, 'REMARK', '');

                        $price = str_replace(',', '', $price !== '' ? $price : '0.00');
                        $cost = str_replace(',', '', $cost !== '' ? $cost : '0.00');
                        $agent_cost = str_replace(',', '', $agent_cost !== '' ? $agent_cost : '0.00');

                        // ----- COMPARISON ENGINE -----
                        $changes = [];
                        if (!$isNew) {
                            $ex = $existingPackages[$id];
                            
                            if (trim((string)$ex['name']) !== $name) $changes['name'] = true;
                            if (trim((string)($ex['item_code'] ?? '')) !== $item_code) $changes['item_code'] = true;
                            if (trim((string)($ex['item_description'] ?? '')) !== $item_description) $changes['item_description'] = true;
                            if ((float)$ex['price'] !== (float)$price) $changes['price'] = true;
                            if ((float)$ex['cost'] !== (float)$cost) $changes['cost'] = true;
                            if ((float)$ex['agent_cost'] !== (float)$agent_cost) $changes['agent_cost'] = true;
                            if ((string)$ex['brand'] !== (string)$dbBrandId) $changes['brand'] = true;
                            if ((string)$ex['currency_unit'] !== (string)$dbPriceCurrId) $changes['price_curr'] = true;
                            if ((string)$ex['cost_curr'] !== (string)$dbCostCurrId) $changes['cost_curr'] = true;
                            
                            // Check Product changes safely
                            $exProducts = array_filter(explode(',', $ex['product'] ?? ''));
                            sort($exProducts);
                            if (implode(',', $exProducts) !== $dbProductString) $changes['products'] = true;

                            if ((string)$ex['barcode_slot_total'] !== (string)$barcode_slot_total) $changes['barcode_slot_total'] = true;
                            if (trim((string)$ex['remark']) !== $remark) $changes['remark'] = true;
                        }

                        // Only add to preview if it's NEW or if something actually CHANGED
                        if ($isNew || count($changes) > 0) {
                            $previewData[] = [
                                'is_new' => $isNew,
                                'changes' => $changes,
                                'field_errors' => $fieldErrors,
                                'id' => $id,
                                'name' => $name,
                                'item_code' => $item_code,
                                'item_description' => $item_description,
                                'price' => $price,
                                'brand_name' => $brandDisplay,
                                'brand_id' => $dbBrandId,
                                'cost' => $cost,
                                'cost_curr_name' => $costCurrDisplay,
                                'cost_curr_id' => $dbCostCurrId,
                                'price_curr_name' => $priceCurrDisplay,
                                'price_curr_id' => $dbPriceCurrId,
                                'agent_cost' => $agent_cost,
                                'product_names' => implode(', ', $productDisplayNames),
                                'product_ids' => $dbProductString,
                                'barcode_slot_total' => $barcode_slot_total,
                                'remark' => $remark
                            ];
                        }
                    }
                }
                
                if (empty($previewData) && empty($importErrors)) {
                    $importErrors[] = "No new records or changes detected. The database is already up to date with this Excel file!";
                }

            } elseif (is_array($parsedRows)) {
                $importErrors[] = "No rows found in uploaded file.";
            }
        } else {
            $importErrors[] = "Invalid format. Please upload an Excel (.xlsx) file.";
        }
    }
} 
// Step 2: Save the Data to the Database
else if ($action === 'update') {
    $postData = isset($_POST['data']) ? $_POST['data'] : [];
    $previewData = [];
    $hasValidationError = false;
    $successCount = 0;
    $insertCount = 0;

    foreach ($postData as $row) {
        $fieldErrors = [];
        $brandRaw = trim((string) (isset($row['brand_name']) ? $row['brand_name'] : ''));
        $priceCurrRaw = trim((string) (isset($row['price_curr_name']) ? $row['price_curr_name'] : ''));
        $costCurrRaw = trim((string) (isset($row['cost_curr_name']) ? $row['cost_curr_name'] : ''));
        $productsRaw = trim((string) (isset($row['product_names']) ? $row['product_names'] : ''));

        $brandResolved = resolveMapValue($brandRaw, $brandRevMap);
        $priceCurrResolved = resolveMapValue($priceCurrRaw, $currencyRevMap);
        $costCurrResolved = resolveMapValue($costCurrRaw, $currencyRevMap);

        if ($brandRaw !== '' && $brandResolved === '') {
            $fieldErrors['brand_name'] = 'Brand not found in database.';
        }
        if ($priceCurrRaw !== '' && $priceCurrResolved === '') {
            $fieldErrors['price_curr_name'] = 'Price currency not found in database.';
        }
        if ($costCurrRaw !== '' && $costCurrResolved === '') {
            $fieldErrors['cost_curr_name'] = 'Cost currency not found in database.';
        }

        if ($productsRaw !== '') {
            $parts = array_map('trim', explode(',', $productsRaw));
            foreach ($parts as $part) {
                if ($part === '') continue;
                if (ctype_digit($part)) {
                    $pid = (int) $part;
                    if (!isset($productNameMap[$pid])) {
                        $fieldErrors['product_names'] = "Product '$part' not found in database.";
                        break;
                    }
                } else if (!isset($productRevMap[strtolower($part)])) {
                    $fieldErrors['product_names'] = "Product '$part' not found in database.";
                    break;
                }
            }
        }

        if (!empty($fieldErrors)) {
            $hasValidationError = true;
        }

        $row['field_errors'] = $fieldErrors;
        $row['is_new'] = (isset($row['is_new']) && $row['is_new'] == '1') ? true : false;
        $row['changes'] = isset($row['changes']) && is_array($row['changes']) ? $row['changes'] : array();
        $previewData[] = $row;
    }

    if ($hasValidationError) {
        $importErrors[] = 'Please correct the highlighted field errors before update.';
        $action = 'preview';
    } else {

    foreach ($postData as $row) {
        $id = mysqli_real_escape_string($connect, $row['id']);
        $is_new = ($row['is_new'] == '1');
        
        $name = mysqli_real_escape_string($connect, $row['name']);
        $item_code = mysqli_real_escape_string($connect, $row['item_code']);
        $item_description = mysqli_real_escape_string($connect, $row['item_description']);
        
        $price = !empty($row['price']) ? mysqli_real_escape_string($connect, str_replace(',', '', (string) $row['price'])) : '0.00';
        $cost = !empty($row['cost']) ? mysqli_real_escape_string($connect, str_replace(',', '', (string) $row['cost'])) : '0.00';
        $agent_cost = !empty($row['agent_cost']) ? mysqli_real_escape_string($connect, $row['agent_cost']) : '0.00';
        $barcode_slot = !empty($row['barcode_slot_total']) ? mysqli_real_escape_string($connect, $row['barcode_slot_total']) : '0';

        $brandRaw = trim((string) (isset($row['brand_name']) ? $row['brand_name'] : ''));
        $priceCurrRaw = trim((string) (isset($row['price_curr_name']) ? $row['price_curr_name'] : ''));
        $costCurrRaw = trim((string) (isset($row['cost_curr_name']) ? $row['cost_curr_name'] : ''));
        $productsRaw = trim((string) (isset($row['product_names']) ? $row['product_names'] : ''));

        $brandRaw = resolveMapValue($brandRaw, $brandRevMap);
        $priceCurrRaw = resolveMapValue($priceCurrRaw, $currencyRevMap);
        $costCurrRaw = resolveMapValue($costCurrRaw, $currencyRevMap);

        if ($brandRaw !== '' && !ctype_digit($brandRaw)) {
            $brandRaw = '0';
        }
        if ($priceCurrRaw !== '' && !ctype_digit($priceCurrRaw)) {
            $priceCurrRaw = '0';
        }
        if ($costCurrRaw !== '' && !ctype_digit($costCurrRaw)) {
            $costCurrRaw = '0';
        }

        $brand_id = $brandRaw !== '' ? mysqli_real_escape_string($connect, $brandRaw) : '0';
        $price_curr_id = $priceCurrRaw !== '' ? mysqli_real_escape_string($connect, $priceCurrRaw) : '0';
        $cost_curr_id = $costCurrRaw !== '' ? mysqli_real_escape_string($connect, $costCurrRaw) : '0';

        $productIdsList = array();
        if ($productsRaw !== '') {
            $parts = array_map('trim', explode(',', $productsRaw));
            foreach ($parts as $part) {
                if ($part === '') continue;
                if (ctype_digit($part)) {
                    $productIdsList[] = (int) $part;
                } else if (isset($productRevMap[strtolower($part)])) {
                    $productIdsList[] = (int) $productRevMap[strtolower($part)];
                }
            }
        }
        $productIdsList = array_values(array_unique($productIdsList));
        sort($productIdsList);
        $product_ids = mysqli_real_escape_string($connect, implode(',', $productIdsList));

        $remark = mysqli_real_escape_string($connect, $row['remark']);

        if ($is_new) {
            // INSERT QUERY (Ignores provided ID to avoid conflicts)
            $query = "INSERT INTO " . PKG . " 
                      (name, item_code, item_description, price, currency_unit, brand, cost, cost_curr, agent_cost, product, barcode_slot_total, remark, create_by, create_date, create_time, status) 
                      VALUES 
                      ('$name', '$item_code', '$item_description', '$price', '$price_curr_id', '$brand_id', '$cost', '$cost_curr_id', '$agent_cost', '$product_ids', '$barcode_slot', '$remark', '" . USER_ID . "', curdate(), curtime(), 'A')";
            
            if (mysqli_query($connect, $query)) {
                $insertCount++;
            }
        } else {
            // UPDATE QUERY
            $query = "UPDATE " . PKG . " SET 
                      name='$name', 
                      item_code='$item_code', 
                      item_description='$item_description', 
                      price='$price', 
                      currency_unit='$price_curr_id', 
                      brand='$brand_id', 
                      cost='$cost', 
                      cost_curr='$cost_curr_id', 
                      agent_cost='$agent_cost', 
                      product='$product_ids',
                      barcode_slot_total='$barcode_slot',
                      remark='$remark', 
                      update_by='" . USER_ID . "', 
                      update_date=curdate(), 
                      update_time=curtime() 
                      WHERE id='$id'";

            if (mysqli_query($connect, $query)) {
                $successCount++;
            }
        }
    }

        $log = [
            'log_act' => 'Import',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'Bulk import update',
            'query_table' => PKG,
            'newval' => 'Inserted=' . (int) $insertCount . ', Updated=' . (int) $successCount,
            'act_msg' => USER_NAME . " imported package data [ <b>New Added = " . (int) $insertCount . ", Updated = " . (int) $successCount . "</b> ] into <b><i>" . PKG . " Table</i></b>.",
            'page' => $pageTitle,
            'connect' => $connect,
        ];
        audit_log($log);

        echo "<script>alert('Import Complete! Successfully added $insertCount new packages and updated $successCount existing packages.'); window.location.href = '$redirect_page';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .highlight-change { background-color: #fff3cd !important; border-color: #ffecb5 !important; color: #664d03 !important; font-weight: bold; }
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
                <div class="row mb-4">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                        <h2>Import & Bulk Edit Packages</h2>
                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>"><i class="fa-solid fa-arrow-left"></i> Back To Table</a>
                    </div>
                </div>

                <?php if (!empty($importErrors)) { ?>
                    <div class="alert alert-warning shadow-sm" role="alert">
                        <h5 class="alert-heading"><i class="fa-solid fa-circle-info"></i> Import Notice</h5>
                        <?php foreach ($importErrors as $error) { echo "<div>- " . htmlspecialchars($error) . "</div>"; } ?>
                    </div>
                <?php } ?>

                <?php if ($action === 'preview' && !empty($previewData)) { ?>
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 2: Preview Changes</h5>
                            <p class="text-muted"><span class="badge bg-success">Green</span> rows will be inserted as new packages. <span class="badge bg-warning text-dark">Yellow</span> fields show exactly what data was changed in Excel.</p>
                            
                            <form method="post">
                                <?php foreach ($previewData as $index => $row) {
                                    $chg = isset($row['changes']) ? $row['changes'] : array();
                                    $ferr = isset($row['field_errors']) ? $row['field_errors'] : array();
                                ?>
                                    <div class="card mb-3 <?= $row['is_new'] ? 'row-new' : 'row-update' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-3">
                                                <h6 class="mb-0">Record #<?= $index + 1 ?></h6>
                                                <?= $row['is_new'] ? '<span class="badge bg-success">NEW</span>' : '<span class="badge bg-warning text-dark">MODIFIED</span>' ?>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-2">
                                                    <label class="form-label">ID</label>
                                                    <input type="text" class="form-control" name="data[<?= $index ?>][id]" value="<?= htmlspecialchars($row['id'] ?: 'Auto') ?>" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Name*</label>
                                                    <input type="text" class="form-control <?= isset($chg['name']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][name]" value="<?= htmlspecialchars($row['name']) ?>" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Item Code</label>
                                                    <input type="text" class="form-control <?= isset($chg['item_code']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][item_code]" value="<?= htmlspecialchars($row['item_code']) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Item Description</label>
                                                    <input type="text" class="form-control <?= isset($chg['item_description']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][item_description]" value="<?= htmlspecialchars($row['item_description']) ?>">
                                                </div>

                                                <div class="col-md-3">
                                                    <label class="form-label">Brand</label>
                                                    <div class="autocomplete">
                                                        <input type="text" id="pkgi_brand_<?= $index ?>" class="form-control <?= isset($chg['brand']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search" data-lookup-field="brand_name" data-search-type="name" data-db-table="<?= BRAND ?>" name="data[<?= $index ?>][brand_name]" value="<?= htmlspecialchars($row['brand_name']) ?>">
                                                        <input type="hidden" id="pkgi_brand_<?= $index ?>_hidden" value="">
                                                    </div>
                                                    <?php if (isset($ferr['brand_name'])) { ?><div class="field-error" data-field="brand_name"><?= htmlspecialchars($ferr['brand_name']) ?></div><?php } ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Price Currency</label>
                                                    <div class="autocomplete">
                                                        <input type="text" id="pkgi_price_curr_<?= $index ?>" class="form-control <?= isset($chg['price_curr']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search" data-lookup-field="price_curr_name" data-search-type="unit" data-db-table="<?= CUR_UNIT ?>" name="data[<?= $index ?>][price_curr_name]" value="<?= htmlspecialchars($row['price_curr_name']) ?>">
                                                        <input type="hidden" id="pkgi_price_curr_<?= $index ?>_hidden" value="">
                                                    </div>
                                                    <?php if (isset($ferr['price_curr_name'])) { ?><div class="field-error" data-field="price_curr_name"><?= htmlspecialchars($ferr['price_curr_name']) ?></div><?php } ?>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Price</label>
                                                    <input type="number" step="0.01" class="form-control <?= isset($chg['price']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][price]" value="<?= htmlspecialchars($row['price']) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Cost Currency</label>
                                                    <div class="autocomplete">
                                                        <input type="text" id="pkgi_cost_curr_<?= $index ?>" class="form-control <?= isset($chg['cost_curr']) ? 'highlight-change' : '' ?> js-lookup-single js-live-search" data-lookup-field="cost_curr_name" data-search-type="unit" data-db-table="<?= CUR_UNIT ?>" name="data[<?= $index ?>][cost_curr_name]" value="<?= htmlspecialchars($row['cost_curr_name']) ?>">
                                                        <input type="hidden" id="pkgi_cost_curr_<?= $index ?>_hidden" value="">
                                                    </div>
                                                    <?php if (isset($ferr['cost_curr_name'])) { ?><div class="field-error" data-field="cost_curr_name"><?= htmlspecialchars($ferr['cost_curr_name']) ?></div><?php } ?>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Cost</label>
                                                    <input type="number" step="0.01" class="form-control <?= isset($chg['cost']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][cost]" value="<?= htmlspecialchars($row['cost']) ?>">
                                                </div>

                                                <div class="col-md-2">
                                                    <label class="form-label">Agent Cost</label>
                                                    <input type="number" step="0.01" class="form-control <?= isset($chg['agent_cost']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][agent_cost]" value="<?= htmlspecialchars($row['agent_cost']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Products Included</label>
                                                    <input type="text" class="form-control <?= isset($chg['products']) ? 'highlight-change' : '' ?> js-lookup-multi" list="packageImportProductList" data-lookup-field="product_names" name="data[<?= $index ?>][product_names]" value="<?= htmlspecialchars($row['product_names']) ?>">
                                                    <?php if (isset($ferr['product_names'])) { ?><div class="field-error" data-field="product_names"><?= htmlspecialchars($ferr['product_names']) ?></div><?php } ?>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Barcode Slot Total</label>
                                                    <input type="number" class="form-control <?= isset($chg['barcode_slot_total']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][barcode_slot_total]" value="<?= htmlspecialchars($row['barcode_slot_total']) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Remark</label>
                                                    <input type="text" class="form-control <?= isset($chg['remark']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][remark]" value="<?= htmlspecialchars($row['remark']) ?>">
                                                </div>
                                            </div>

                                            <input type="hidden" name="data[<?= $index ?>][is_new]" value="<?= $row['is_new'] ? '1' : '0' ?>">
                                        </div>
                                    </div>
                                <?php } ?>
                                <datalist id="packageImportProductList">
                                    <?php foreach ($productNameMap as $optName) { ?>
                                        <option value="<?= htmlspecialchars($optName) ?>"></option>
                                    <?php } ?>
                                </datalist>
                                <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                    <a href="package_import.php" class="btn btn-lg btn-rounded btn-secondary px-4">Cancel</a>
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
                            <form method="post" enctype="multipart/form-data">
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
    preloader(0, '');
    setButtonColor();

    (function () {
        var siteUrl = <?= json_encode($SITEURL) ?>;

        function clearSearchList(inputId) {
            var list = document.getElementById('searchResult_' + inputId);
            if (list) list.remove();
            var clear = document.getElementById('clear_' + inputId);
            if (clear) clear.remove();
        }

        document.querySelectorAll('.js-live-search[data-search-type][data-db-table]').forEach(function (el) {
            var hidden = document.getElementById(el.id + '_hidden');
            if (!hidden) return;

            el.addEventListener('keyup', function () {
                hidden.value = '';
                searchInput({
                    search: el.value,
                    searchType: el.getAttribute('data-search-type'),
                    elementID: el.id,
                    hiddenElementID: hidden.id,
                    dbTable: el.getAttribute('data-db-table')
                }, siteUrl);
            });

            el.addEventListener('change', function () {
                if (el.value.trim() === '') {
                    hidden.value = '';
                    clearSearchList(el.id);
                }
            });

            el.addEventListener('blur', function () {
                setTimeout(function () { clearSearchList(el.id); }, 120);
            });

            hidden.addEventListener('input', function () {
                clearError(el, singleValid(el.getAttribute('data-lookup-field'), el.value));
            });
            hidden.addEventListener('change', function () {
                clearError(el, singleValid(el.getAttribute('data-lookup-field'), el.value));
            });
        });

        function norm(v) {
            return String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();
        }

        var lookupMeta = {
            brand_name: {
                names: <?= json_encode(array_values($brandNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($brandNameMap))) ?>
            },
            price_curr_name: {
                names: <?= json_encode(array_values($currencyNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($currencyNameMap))) ?>
            },
            cost_curr_name: {
                names: <?= json_encode(array_values($currencyNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($currencyNameMap))) ?>
            },
            product_names: {
                names: <?= json_encode(array_values($productNameMap)) ?>,
                ids: <?= json_encode(array_map('strval', array_keys($productNameMap))) ?>
            }
        };

        function singleValid(field, raw) {
            var value = String(raw || '').trim();
            if (value === '') return true;
            var meta = lookupMeta[field];
            if (!meta) return true;

            var byName = {};
            var byId = {};
            (meta.names || []).forEach(function (name) { byName[norm(name)] = true; });
            (meta.ids || []).forEach(function (id) { byId[String(id)] = true; });

            if (byName[norm(value)]) return true;
            if (/^\d+$/.test(value) && byId[value]) return true;
            return false;
        }

        function multiValid(field, raw) {
            var value = String(raw || '').trim();
            if (value === '') return true;
            var parts = value.split(',');
            for (var i = 0; i < parts.length; i++) {
                if (!singleValid(field, parts[i])) return false;
            }
            return true;
        }

        function clearError(input, isValid) {
            if (!isValid) return;
            var field = input.getAttribute('data-lookup-field');
            var row = input.closest('.col-md-3, .col-md-4, .col-md-6, .col-md-2, .col-md-12') || input.parentElement;
            if (!row || !field) return;
            var err = row.querySelector('.field-error[data-field="' + field + '"]');
            if (err) err.style.display = 'none';
        }

        document.querySelectorAll('.js-lookup-single[data-lookup-field]').forEach(function (el) {
            var fn = function () {
                clearError(el, singleValid(el.getAttribute('data-lookup-field'), el.value));
            };
            el.addEventListener('input', fn);
            el.addEventListener('change', fn);
        });

        document.querySelectorAll('.js-lookup-multi[data-lookup-field]').forEach(function (el) {
            var fn = function () {
                clearError(el, multiValid(el.getAttribute('data-lookup-field'), el.value));
            };
            el.addEventListener('input', fn);
            el.addEventListener('change', fn);
        });
    })();
</script>
</html>