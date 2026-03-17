<?php
$pageTitle = "Product Import";
include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

$redirect_page = $SITEURL . '/product_table.php';
$pinAccess = checkCurrentPin($connect, 'Product');
if (!is_array($pinAccess)) {
    $pinAccess = array();
}
if (!isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import this page.");location.href = "' . $redirect_page . '";</script>';
    exit;
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
            // Unable to read worksheet XML via ZipArchive; return empty result.
             // This avoids falling back to shelling out to external tools like `tar`,
             // which is unsafe for processing uploaded files.
             return $rows;
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

$brandRevMap = getReverseMapping($connect, BRAND, 'id', 'name');
$weightRevMap = getReverseMapping($connect, WGT_UNIT, 'id', 'unit');
$currencyRevMap = getReverseMapping($connect, CUR_UNIT, 'id', 'unit');
$categoryRevMap = getReverseMapping($connect, PROD_CATEGORY, 'id', 'name');
$parentRevMap = getReverseMapping($connect, PROD, 'id', 'name');

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

                    $brand = resolveForeignId($getCol($data, 'BRAND', ''), $brandRevMap);
                    $weightUnit = resolveForeignId($getCol($data, 'WEIGHT UNIT', ''), $weightRevMap);
                    $currencyUnit = resolveForeignId($getCol($data, 'CURRENCY UNIT', ''), $currencyRevMap);
                    $productCategory = resolveForeignId($getCol($data, 'PRODUCT CATEGORY', ''), $categoryRevMap);
                    $parentProduct = resolveForeignId($getCol($data, 'PARENT PRODUCT', ''), $parentRevMap);

                    $rowData = array(
                        'id' => $id,
                        'name' => $name,
                        'brand' => $brand,
                        'weight' => $getCol($data, 'WEIGHT', ''),
                        'weight_unit' => $weightUnit,
                        'cost' => $getCol($data, 'COST', '0.00'),
                        'currency_unit' => $currencyUnit,
                        'barcode_status' => $getCol($data, 'BARCODE STATUS', 'No') === 'Yes' ? 'Yes' : 'No',
                        'barcode_slot' => $getCol($data, 'BARCODE SLOT', ''),
                        'product_category' => $productCategory,
                        'expire_date' => $getCol($data, 'EXPIRE DATE', ''),
                        'parent_product' => $parentProduct,
                    );

                    $isNew = ($id === '' || !isset($existingProducts[$id]));
                    $changes = array();

                    if (!$isNew) {
                        $ex = $existingProducts[$id];
                        foreach ($rowData as $k => $v) {
                            if ($k === 'id') {
                                continue;
                            }
                            $exVal = isset($ex[$k]) ? (string) $ex[$k] : '';
                            if ((string) $exVal !== (string) $v) {
                                $changes[$k] = true;
                            }
                        }
                    }

                    if ($isNew || count($changes) > 0) {
                        $rowData['is_new'] = $isNew ? '1' : '0';
                        $rowData['changes'] = $changes;
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
    $successCount = 0;
    $insertCount = 0;

    foreach ($postData as $row) {
        $id = mysqli_real_escape_string($connect, isset($row['id']) ? $row['id'] : '');
        $is_new = (isset($row['is_new']) && $row['is_new'] == '1');

        $name = mysqli_real_escape_string($connect, isset($row['name']) ? $row['name'] : '');
        if ($name === '') {
            continue;
        }

        $brand = mysqli_real_escape_string($connect, isset($row['brand']) ? $row['brand'] : '');
        $weight = mysqli_real_escape_string($connect, isset($row['weight']) ? $row['weight'] : '');
        $weight_unit = mysqli_real_escape_string($connect, isset($row['weight_unit']) ? $row['weight_unit'] : '');
        $cost = mysqli_real_escape_string($connect, isset($row['cost']) && $row['cost'] !== '' ? $row['cost'] : '0.00');
        $currency_unit = mysqli_real_escape_string($connect, isset($row['currency_unit']) ? $row['currency_unit'] : '');
        $barcode_status = (isset($row['barcode_status']) && $row['barcode_status'] === 'Yes') ? 'Yes' : 'No';
        $barcode_slot = mysqli_real_escape_string($connect, isset($row['barcode_slot']) ? $row['barcode_slot'] : '');
        $product_category = mysqli_real_escape_string($connect, isset($row['product_category']) ? $row['product_category'] : '');
        $expire_date = mysqli_real_escape_string($connect, isset($row['expire_date']) ? $row['expire_date'] : '');
        $parent_product = mysqli_real_escape_string($connect, isset($row['parent_product']) ? $row['parent_product'] : '');

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
    echo "<script>alert('Import Complete! Successfully added " . $insertCount . " new products and updated " . $successCount . " existing products.'); window.location.href = '" . $redirect_page . "';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
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
                    <h2>Import & Bulk Edit Products</h2>
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
                        <form method="post">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle" style="min-width: 1400px;">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Status</th>
                                            <th>S/N</th>
                                            <th>Name</th>
                                            <th>Brand</th>
                                            <th>Weight</th>
                                            <th>Weight Unit</th>
                                            <th>Cost</th>
                                            <th>Currency Unit</th>
                                            <th>Barcode Status</th>
                                            <th>Barcode Slot</th>
                                            <th>Product Category</th>
                                            <th>Expire Date</th>
                                            <th>Parent Product</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($previewData as $index => $row) { ?>
                                            <tr class="<?= $row['is_new'] === '1' ? 'row-new' : 'row-update' ?>">
                                                <td><?= $row['is_new'] === '1' ? '<span class="badge bg-success">New</span>' : '<span class="badge bg-warning text-dark">Update</span>' ?></td>
                                                <td>
                                                    <input type="text" class="form-control" name="data[<?= $index ?>][id]" value="<?= htmlspecialchars($row['id']) ?>" <?= $row['is_new'] === '1' ? '' : 'readonly' ?>>
                                                    <input type="hidden" name="data[<?= $index ?>][is_new]" value="<?= htmlspecialchars($row['is_new']) ?>">
                                                </td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][name]" value="<?= htmlspecialchars($row['name']) ?>" required></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][brand]" value="<?= htmlspecialchars($row['brand']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][weight]" value="<?= htmlspecialchars($row['weight']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][weight_unit]" value="<?= htmlspecialchars($row['weight_unit']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][cost]" value="<?= htmlspecialchars($row['cost']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][currency_unit]" value="<?= htmlspecialchars($row['currency_unit']) ?>"></td>
                                                <td>
                                                    <select class="form-control" name="data[<?= $index ?>][barcode_status]">
                                                        <option value="No" <?= $row['barcode_status'] === 'No' ? 'selected' : '' ?>>No</option>
                                                        <option value="Yes" <?= $row['barcode_status'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                                    </select>
                                                </td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][barcode_slot]" value="<?= htmlspecialchars($row['barcode_slot']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][product_category]" value="<?= htmlspecialchars($row['product_category']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][expire_date]" value="<?= htmlspecialchars($row['expire_date']) ?>"></td>
                                                <td><input type="text" class="form-control" name="data[<?= $index ?>][parent_product]" value="<?= htmlspecialchars($row['parent_product']) ?>"></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
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
