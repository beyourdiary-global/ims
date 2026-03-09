<?php
$pageTitle = "Package Import";
include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

$redirect_page = $SITEURL . '/package_table.php';
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

$brandRevMap = getReverseMapping($connect, BRAND, 'id', 'name');
$currencyRevMap = getReverseMapping($connect, CUR_UNIT, 'id', 'unit');
$productRevMap = getReverseMapping($connect, PROD, 'id', 'name');

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
            } elseif (is_array($parsedRows)) {
                foreach ($parsedRows as $rowIndex => $data) {
                    if ($rowIndex === 0) continue; // Skip header row
                    
                    $data = array_pad($data, 13, '');
                    $id = trim((string)$data[0]);
                    $name = trim((string)$data[1]);

                    // Only process rows that have at least a Name or an ID (Allows new records to be added at the bottom!)
                    if (!empty($id) || !empty($name)) { 
                        
                        $isNew = empty($id) || !isset($existingPackages[$id]);
                        
                        $csvBrand = trim((string)$data[6]);
                        $csvPriceCurr = trim((string)$data[5]);
                        $csvCostCurr = trim((string)$data[8]);
                        $csvProducts = trim((string)$data[10]);

                        $dbBrandId = isset($brandRevMap[strtolower($csvBrand)]) ? $brandRevMap[strtolower($csvBrand)] : '';
                        $dbPriceCurrId = isset($currencyRevMap[strtolower($csvPriceCurr)]) ? $currencyRevMap[strtolower($csvPriceCurr)] : '';
                        $dbCostCurrId = isset($currencyRevMap[strtolower($csvCostCurr)]) ? $currencyRevMap[strtolower($csvCostCurr)] : '';

                        // Reverse lookup product IDs
                        $dbProductIds = [];
                        if (!empty($csvProducts)) {
                            $prodNames = array_map('trim', explode(',', $csvProducts));
                            foreach ($prodNames as $pn) {
                                if (isset($productRevMap[strtolower($pn)])) {
                                    $dbProductIds[] = $productRevMap[strtolower($pn)];
                                } else {
                                    $importErrors[] = "Warning: Product '$pn' not found. It will be skipped for Package '$name'.";
                                }
                            }
                        }
                        sort($dbProductIds); // Standardize array order for comparison
                        $dbProductString = implode(',', $dbProductIds);

                        // Variables to check
                        $item_code = trim((string)$data[2]);
                        $item_description = trim((string)$data[3]);
                        $price = trim((string)$data[4]) ?: '0.00';
                        $cost = trim((string)$data[7]) ?: '0.00';
                        $agent_cost = trim((string)$data[9]) ?: '0.00';
                        $barcode_slot_total = trim((string)$data[11]) ?: '0';
                        $remark = trim((string)$data[12]);

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
                                'id' => $id,
                                'name' => $name,
                                'item_code' => $item_code,
                                'item_description' => $item_description,
                                'price' => $price,
                                'brand_name' => $csvBrand,
                                'brand_id' => $dbBrandId,
                                'cost' => $cost,
                                'cost_curr_name' => $csvCostCurr,
                                'cost_curr_id' => $dbCostCurrId,
                                'price_curr_name' => $csvPriceCurr,
                                'price_curr_id' => $dbPriceCurrId,
                                'agent_cost' => $agent_cost,
                                'product_names' => $csvProducts,
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

            }
        } else {
            $importErrors[] = "Invalid format. Please upload an Excel (.xlsx) file.";
        }
    }
} 
// Step 2: Save the Data to the Database
else if ($action === 'update') {
    $postData = isset($_POST['data']) ? $_POST['data'] : [];
    $successCount = 0;
    $insertCount = 0;

    foreach ($postData as $row) {
        $id = mysqli_real_escape_string($connect, $row['id']);
        $is_new = ($row['is_new'] == '1');
        
        $name = mysqli_real_escape_string($connect, $row['name']);
        $item_code = mysqli_real_escape_string($connect, $row['item_code']);
        $item_description = mysqli_real_escape_string($connect, $row['item_description']);
        
        $price = !empty($row['price']) ? mysqli_real_escape_string($connect, $row['price']) : '0.00';
        $brand_id = !empty($row['brand_id']) ? mysqli_real_escape_string($connect, $row['brand_id']) : '0';
        $cost = !empty($row['cost']) ? mysqli_real_escape_string($connect, $row['cost']) : '0.00';
        $cost_curr_id = !empty($row['cost_curr_id']) ? mysqli_real_escape_string($connect, $row['cost_curr_id']) : '0';
        $price_curr_id = !empty($row['price_curr_id']) ? mysqli_real_escape_string($connect, $row['price_curr_id']) : '0';
        $agent_cost = !empty($row['agent_cost']) ? mysqli_real_escape_string($connect, $row['agent_cost']) : '0.00';
        $barcode_slot = !empty($row['barcode_slot_total']) ? mysqli_real_escape_string($connect, $row['barcode_slot_total']) : '0';
        
        $product_ids = mysqli_real_escape_string($connect, $row['product_ids']);
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

    echo "<script>alert('Import Complete! Successfully added $insertCount new packages and updated $successCount existing packages.'); window.location.href = '$redirect_page';</script>";
    exit;
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
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle" style="min-width: 1300px;">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width: 100px;">Status</th>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Item Code (SKU)</th>
                                                <th>Brand</th>
                                                <th>Products Included</th>
                                                <th>Price</th>
                                                <th>Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($previewData as $index => $row) { 
                                                $chg = $row['changes'];
                                            ?>
                                                <tr class="<?= $row['is_new'] ? 'row-new' : 'row-update' ?>">
                                                    <td class="text-center">
                                                        <?php if ($row['is_new']): ?>
                                                            <span class="badge bg-success w-100 py-2">NEW</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark w-100 py-2">MODIFIED</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control-plaintext fw-bold <?= $row['is_new'] ? 'text-success' : '' ?>" name="data[<?= $index ?>][id]" value="<?= htmlspecialchars($row['id'] ?: 'Auto') ?>" readonly>
                                                    </td>
                                                    <td><input type="text" class="form-control <?= isset($chg['name']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][name]" value="<?= htmlspecialchars($row['name']) ?>" required></td>
                                                    <td><input type="text" class="form-control <?= isset($chg['item_code']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][item_code]" value="<?= htmlspecialchars($row['item_code']) ?>"></td>
                                                    
                                                    <td>
                                                        <input type="text" class="form-control bg-light <?= empty($row['brand_id']) && !empty($row['brand_name']) ? 'border-danger text-danger' : '' ?> <?= isset($chg['brand']) ? 'highlight-change' : '' ?>" value="<?= htmlspecialchars($row['brand_name']) ?>" readonly title="Cannot be changed here">
                                                        <?php if(empty($row['brand_id']) && !empty($row['brand_name'])) echo "<small class='text-danger fw-bold'>Database Match Failed</small>"; ?>
                                                    </td>
                                                    
                                                    <td>
                                                        <input type="text" class="form-control bg-light <?= isset($chg['products']) ? 'highlight-change' : '' ?>" value="<?= htmlspecialchars($row['product_names']) ?>" readonly title="Cannot be changed here">
                                                    </td>
                                                    
                                                    <td>
                                                        <div class="input-group">
                                                            <span class="input-group-text <?= isset($chg['price_curr']) ? 'highlight-change' : '' ?>"><?= htmlspecialchars($row['price_curr_name'] ?: 'N/A') ?></span>
                                                            <input type="number" step="0.01" class="form-control <?= isset($chg['price']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][price]" value="<?= htmlspecialchars($row['price']) ?>">
                                                        </div>
                                                    </td>
                                                    
                                                    <td>
                                                        <div class="input-group">
                                                            <span class="input-group-text <?= isset($chg['cost_curr']) ? 'highlight-change' : '' ?>"><?= htmlspecialchars($row['cost_curr_name'] ?: 'N/A') ?></span>
                                                            <input type="number" step="0.01" class="form-control <?= isset($chg['cost']) ? 'highlight-change' : '' ?>" name="data[<?= $index ?>][cost]" value="<?= htmlspecialchars($row['cost']) ?>">
                                                        </div>
                                                    </td>
                                                    
                                                    <input type="hidden" name="data[<?= $index ?>][is_new]" value="<?= $row['is_new'] ? '1' : '0' ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][brand_id]" value="<?= htmlspecialchars($row['brand_id']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][price_curr_id]" value="<?= htmlspecialchars($row['price_curr_id']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][cost_curr_id]" value="<?= htmlspecialchars($row['cost_curr_id']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][product_ids]" value="<?= htmlspecialchars($row['product_ids']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][item_description]" value="<?= htmlspecialchars($row['item_description']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][agent_cost]" value="<?= htmlspecialchars($row['agent_cost']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][barcode_slot_total]" value="<?= htmlspecialchars($row['barcode_slot_total']) ?>">
                                                    <input type="hidden" name="data[<?= $index ?>][remark]" value="<?= htmlspecialchars($row['remark']) ?>">
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
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
</script>
</html>