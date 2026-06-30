<?php
$pageTitle = 'Lucky Draw Voucher Import';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';

$redirectPage = $SITEURL . '/lucky_draw/prizes_table.php';
$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'Import', $pinAccess);

if (!function_exists('luckyDrawVoucherImportLoadRows')) {
    function luckyDrawVoucherImportLoadRows($file)
    {
        if (!is_array($file) || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return array('rows' => array(), 'error' => 'Please choose a valid Excel (.xlsx) file to upload.');
        }

        $fileName = (string) ($file['name'] ?? '');
        if (strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION)) !== 'xlsx') {
            return array('rows' => array(), 'error' => 'Invalid format. Please upload an Excel (.xlsx) file.');
        }

        if (!function_exists('siParseExcelLikeRows')) {
            return array('rows' => array(), 'error' => 'Excel parser is unavailable right now.');
        }

        $rows = siParseExcelLikeRows((string) $file['tmp_name'], $fileName);
        if (!is_array($rows) || count($rows) === 0) {
            return array('rows' => array(), 'error' => 'No rows found in uploaded file.');
        }

        return array('rows' => $rows, 'error' => '');
    }
}

if (!function_exists('luckyDrawVoucherImportPrizeMaps')) {
    function luckyDrawVoucherImportPrizeMaps($connect)
    {
        $rowsById = array();
        $idByName = array();
        $result = mysqli_query($connect, "SELECT id, prize_name, prize_type, voucher_code, weight, display_order, total_stock, price
            FROM `" . LUCKY_DRAW_PRIZE . "`
            WHERE status = 'A'
              AND prize_type = 'voucher'
            ORDER BY id ASC");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $prizeId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($prizeId <= 0) {
                    continue;
                }

                $prizeName = trim((string) ($row['prize_name'] ?? ''));
                $rowsById[$prizeId] = array(
                    'id' => $prizeId,
                    'prize_name' => $prizeName,
                    'prize_type' => 'voucher',
                    'voucher_code' => (string) ($row['voucher_code'] ?? ''),
                    'weight' => number_format((float) ($row['weight'] ?? 1), 4, '.', ''),
                    'display_order' => (string) (int) ($row['display_order'] ?? 0),
                    'total_stock' => (string) (int) ($row['total_stock'] ?? 0),
                    'price' => number_format((float) ($row['price'] ?? 0), 2, '.', ''),
                );
                if ($prizeName !== '') {
                    $idByName[strtolower($prizeName)] = $prizeId;
                }
            }
        }

        return array($rowsById, $idByName);
    }
}

if (!function_exists('luckyDrawVoucherImportRowMap')) {
    function luckyDrawVoucherImportRowMap($row)
    {
        $map = array();
        if (!is_array($row)) {
            return $map;
        }

        foreach ($row as $key => $value) {
            $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) strtoupper(trim((string) $key))));
            if ($normalizedKey === '') {
                continue;
            }

            $map[$normalizedKey] = trim((string) $value);
        }

        return $map;
    }
}

if (!function_exists('luckyDrawVoucherImportGetCell')) {
    function luckyDrawVoucherImportGetCell($rowMap, $keys, $fallback = '')
    {
        $keys = is_array($keys) ? $keys : array($keys);
        foreach ($keys as $key) {
            $normalizedKey = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) strtoupper(trim((string) $key))));
            if ($normalizedKey !== '' && isset($rowMap[$normalizedKey])) {
                return trim((string) $rowMap[$normalizedKey]);
            }
        }

        return trim((string) $fallback);
    }
}

if (!function_exists('luckyDrawVoucherImportNormalizeVoucherType')) {
    function luckyDrawVoucherImportNormalizeVoucherType($value)
    {
        $value = strtolower(trim((string) $value));
        return $value === '' ? 'voucher' : $value;
    }
}

if (!function_exists('luckyDrawVoucherImportNormalizeWeight')) {
    function luckyDrawVoucherImportNormalizeWeight($value)
    {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $weight = round((float) $value, 4);
        return $weight >= 0 ? number_format($weight, 4, '.', '') : '';
    }
}

if (!function_exists('luckyDrawVoucherImportNormalizeInt')) {
    function luckyDrawVoucherImportNormalizeInt($value)
    {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $number = (int) round((float) $value);
        return $number >= 0 ? (string) $number : '';
    }
}

if (!function_exists('luckyDrawVoucherImportNormalizePrice')) {
    function luckyDrawVoucherImportNormalizePrice($value)
    {
        $value = trim((string) $value);
        if ($value === '' || !is_numeric(str_replace(',', '', $value))) {
            return '';
        }

        $number = round((float) str_replace(',', '', $value), 2);
        return $number >= 0 ? number_format($number, 2, '.', '') : '';
    }
}

if (!function_exists('luckyDrawVoucherImportResolvePrize')) {
    function luckyDrawVoucherImportResolvePrize($prizeIdRaw, $prizeNameRaw, $rowsById, $idByName)
    {
        $prizeIdRaw = trim((string) $prizeIdRaw);
        $prizeNameRaw = trim((string) $prizeNameRaw);

        if ($prizeIdRaw !== '' && is_numeric($prizeIdRaw)) {
            $prizeId = (int) round((float) $prizeIdRaw);
            if (isset($rowsById[$prizeId])) {
                return $rowsById[$prizeId];
            }
        }

        if ($prizeNameRaw !== '') {
            $lookupKey = strtolower($prizeNameRaw);
            if (isset($idByName[$lookupKey]) && isset($rowsById[(int) $idByName[$lookupKey]])) {
                return $rowsById[(int) $idByName[$lookupKey]];
            }
        }

        return array();
    }
}

if (!function_exists('luckyDrawVoucherImportBuildPreviewRows')) {
    function luckyDrawVoucherImportBuildPreviewRows($parsedRows, $rowsById, $idByName)
    {
        $previewRows = array();
        $errors = array();

        for ($rowIndex = 0; $rowIndex < count($parsedRows); $rowIndex++) {
            $sourceRow = isset($parsedRows[$rowIndex]) && is_array($parsedRows[$rowIndex]) ? $parsedRows[$rowIndex] : array();
            $rowMap = luckyDrawVoucherImportRowMap($sourceRow);

            $prizeIdRaw = luckyDrawVoucherImportGetCell($rowMap, array('S/N', 'SN', 'ID', 'PRIZE ID'), '');
            $prizeNameRaw = luckyDrawVoucherImportGetCell($rowMap, array('PRIZE NAME'), '');
            $prizeTypeRaw = luckyDrawVoucherImportGetCell($rowMap, array('PRIZE TYPE'), 'VOUCHER');
            $voucherCodeRaw = luckyDrawSafePublicText(luckyDrawVoucherImportGetCell($rowMap, array('VOUCHER CODE'), ''), 255);
            $weightRaw = luckyDrawVoucherImportGetCell($rowMap, array('WEIGHT'), '');
            $displayOrderRaw = luckyDrawVoucherImportGetCell($rowMap, array('DISPLAY ORDER'), '');
            $totalStockRaw = luckyDrawVoucherImportGetCell($rowMap, array('TOTAL STOCK'), '');
            $priceRaw = luckyDrawVoucherImportGetCell($rowMap, array('PRICE'), '');

            if ($prizeIdRaw === '' && $prizeNameRaw === '' && $voucherCodeRaw === '' && $weightRaw === '' && $displayOrderRaw === '' && $totalStockRaw === '' && $priceRaw === '') {
                continue;
            }

            $existingPrize = luckyDrawVoucherImportResolvePrize($prizeIdRaw, $prizeNameRaw, $rowsById, $idByName);
            $resolvedPrizeId = isset($existingPrize['id']) ? (int) $existingPrize['id'] : 0;
            $isNew = $resolvedPrizeId <= 0;
            $normalizedType = luckyDrawVoucherImportNormalizeVoucherType($prizeTypeRaw);
            $normalizedWeight = luckyDrawVoucherImportNormalizeWeight($weightRaw);
            $normalizedDisplayOrder = luckyDrawVoucherImportNormalizeInt($displayOrderRaw);
            $normalizedTotalStock = luckyDrawVoucherImportNormalizeInt($totalStockRaw);
            $normalizedPrice = luckyDrawVoucherImportNormalizePrice($priceRaw);

            $fieldErrors = array();
            if (trim((string) $prizeNameRaw) === '') {
                $fieldErrors['prize_name'] = 'Prize name is required.';
            }
            if ($normalizedType !== 'voucher') {
                $fieldErrors['prize_type'] = 'Only voucher prize rows can be imported here.';
            }
            if ($voucherCodeRaw === '') {
                $fieldErrors['voucher_code'] = 'Voucher code is required.';
            }
            if ($normalizedWeight === '') {
                $fieldErrors['weight'] = 'Weight must be numeric and 0 or greater.';
            }
            if ($normalizedDisplayOrder === '') {
                $fieldErrors['display_order'] = 'Display order must be 0 or greater.';
            }
            if ($normalizedTotalStock === '') {
                $fieldErrors['total_stock'] = 'Total stock must be 0 or greater.';
            }
            if ($normalizedPrice === '') {
                $fieldErrors['price'] = 'Price must be numeric and 0 or greater.';
            }

            $changes = array();
            if (!$isNew) {
                if ((string) $existingPrize['prize_name'] !== trim((string) $prizeNameRaw)) {
                    $changes['prize_name'] = true;
                }
                if ((string) $existingPrize['voucher_code'] !== $voucherCodeRaw) {
                    $changes['voucher_code'] = true;
                }
                if ((string) $existingPrize['weight'] !== ($normalizedWeight !== '' ? $normalizedWeight : trim((string) $weightRaw))) {
                    $changes['weight'] = true;
                }
                if ((string) $existingPrize['display_order'] !== ($normalizedDisplayOrder !== '' ? $normalizedDisplayOrder : trim((string) $displayOrderRaw))) {
                    $changes['display_order'] = true;
                }
                if ((string) $existingPrize['total_stock'] !== ($normalizedTotalStock !== '' ? $normalizedTotalStock : trim((string) $totalStockRaw))) {
                    $changes['total_stock'] = true;
                }
                if ((string) $existingPrize['price'] !== ($normalizedPrice !== '' ? $normalizedPrice : trim((string) $priceRaw))) {
                    $changes['price'] = true;
                }
            }

            if ($isNew || !empty($fieldErrors) || !empty($changes)) {
                $previewRows[] = array(
                    'prize_id' => $resolvedPrizeId > 0 ? (string) $resolvedPrizeId : '',
                    'prize_name' => trim((string) $prizeNameRaw),
                    'prize_type' => strtoupper($normalizedType),
                    'voucher_code' => $voucherCodeRaw,
                    'weight' => $normalizedWeight !== '' ? $normalizedWeight : trim((string) $weightRaw),
                    'display_order' => $normalizedDisplayOrder !== '' ? $normalizedDisplayOrder : trim((string) $displayOrderRaw),
                    'total_stock' => $normalizedTotalStock !== '' ? $normalizedTotalStock : trim((string) $totalStockRaw),
                    'price' => $normalizedPrice !== '' ? $normalizedPrice : trim((string) $priceRaw),
                    'is_new' => $isNew ? '1' : '0',
                    'changes' => $changes,
                    'field_errors' => $fieldErrors,
                );
            }
        }

        if (empty($previewRows) && empty($errors)) {
            $errors[] = 'No new voucher rows or changes were detected in the uploaded file.';
        }

        return array($previewRows, $errors);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $log = array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . ' viewed the page <b>' . $safeAuditPageTitle . '</b>.',
        'page' => $pageTitle,
        'connect' => $connect,
    );
    audit_log($log);
}

list($voucherPrizeRowsById, $voucherPrizeIdByName) = luckyDrawVoucherImportPrizeMaps($connect);

$action = post('actionBtn');
$importErrors = array();
$previewData = array();

if ($action === 'preview') {
    $loadResult = luckyDrawVoucherImportLoadRows(isset($_FILES['import_file']) ? $_FILES['import_file'] : array());
    if ($loadResult['error'] !== '') {
        $importErrors[] = $loadResult['error'];
    } else {
        list($previewData, $previewErrors) = luckyDrawVoucherImportBuildPreviewRows($loadResult['rows'], $voucherPrizeRowsById, $voucherPrizeIdByName);
        $importErrors = array_merge($importErrors, $previewErrors);
    }
} elseif ($action === 'update') {
    $postRows = isset($_POST['data']) && is_array($_POST['data']) ? $_POST['data'] : array();
    $hasValidationError = false;

    foreach ($postRows as $row) {
        $prizeId = isset($row['prize_id']) ? (int) $row['prize_id'] : 0;
        $isNew = isset($row['is_new']) && (string) $row['is_new'] === '1';
        $prizeName = luckyDrawSafePublicText(isset($row['prize_name']) ? $row['prize_name'] : '', 190);
        $prizeType = strtoupper(luckyDrawVoucherImportNormalizeVoucherType(isset($row['prize_type']) ? $row['prize_type'] : 'voucher'));
        $voucherCode = luckyDrawSafePublicText(isset($row['voucher_code']) ? $row['voucher_code'] : '', 255);
        $weight = luckyDrawVoucherImportNormalizeWeight(isset($row['weight']) ? $row['weight'] : '');
        $displayOrder = luckyDrawVoucherImportNormalizeInt(isset($row['display_order']) ? $row['display_order'] : '');
        $totalStock = luckyDrawVoucherImportNormalizeInt(isset($row['total_stock']) ? $row['total_stock'] : '');
        $price = luckyDrawVoucherImportNormalizePrice(isset($row['price']) ? $row['price'] : '');

        $fieldErrors = array();
        if (!$isNew && ($prizeId <= 0 || !isset($voucherPrizeRowsById[$prizeId]))) {
            $fieldErrors['prize_id'] = 'A valid voucher prize row is required.';
        }
        if ($prizeName === '') {
            $fieldErrors['prize_name'] = 'Prize name is required.';
        }
        if ($prizeType !== 'VOUCHER') {
            $fieldErrors['prize_type'] = 'Only voucher prize rows can be updated here.';
        }
        if ($voucherCode === '') {
            $fieldErrors['voucher_code'] = 'Voucher code is required.';
        }
        if ($weight === '') {
            $fieldErrors['weight'] = 'Weight must be numeric and 0 or greater.';
        }
        if ($displayOrder === '') {
            $fieldErrors['display_order'] = 'Display order must be 0 or greater.';
        }
        if ($totalStock === '') {
            $fieldErrors['total_stock'] = 'Total stock must be 0 or greater.';
        }
        if ($price === '') {
            $fieldErrors['price'] = 'Price must be numeric and 0 or greater.';
        }

        if (!empty($fieldErrors)) {
            $hasValidationError = true;
        }

        $previewData[] = array(
            'prize_id' => (string) $prizeId,
            'prize_name' => $prizeName,
            'prize_type' => $prizeType,
            'voucher_code' => $voucherCode,
            'weight' => $weight !== '' ? $weight : trim((string) ($row['weight'] ?? '')),
            'display_order' => $displayOrder !== '' ? $displayOrder : trim((string) ($row['display_order'] ?? '')),
            'total_stock' => $totalStock !== '' ? $totalStock : trim((string) ($row['total_stock'] ?? '')),
            'price' => $price !== '' ? $price : trim((string) ($row['price'] ?? '')),
            'is_new' => $isNew ? '1' : '0',
            'changes' => isset($row['changes']) && is_array($row['changes']) ? $row['changes'] : array(),
            'field_errors' => $fieldErrors,
        );
    }

    if ($hasValidationError) {
        $importErrors[] = 'Please correct the highlighted field errors before update.';
        $action = 'preview';
    } else {
        $insertedCount = 0;
        $updatedCount = 0;
        $safeActor = mysqli_real_escape_string($connect, (string) USER_ID);

        foreach ($previewData as $row) {
            $prizeId = (int) ($row['prize_id'] ?? 0);
            $isNew = isset($row['is_new']) && (string) $row['is_new'] === '1';

            if ($isNew) {
                $query = "INSERT INTO `" . LUCKY_DRAW_PRIZE . "`
                    (prize_name, prize_type, voucher_code, weight, display_order, total_stock, price, is_enabled, create_by, create_date, create_time, status)
                    VALUES (
                        '" . mysqli_real_escape_string($connect, (string) ($row['prize_name'] ?? '')) . "',
                        'voucher',
                        '" . mysqli_real_escape_string($connect, (string) ($row['voucher_code'] ?? '')) . "',
                        " . number_format((float) ($row['weight'] ?? 1), 4, '.', '') . ",
                        " . (int) ($row['display_order'] ?? 0) . ",
                        " . (int) ($row['total_stock'] ?? 0) . ",
                        " . number_format((float) ($row['price'] ?? 0), 2, '.', '') . ",
                        'Y',
                        '" . $safeActor . "',
                        CURDATE(),
                        CURTIME(),
                        'A'
                    )";
                if (mysqli_query($connect, $query)) {
                    $insertedCount++;
                }
            } else if ($prizeId > 0) {
                $query = "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                    SET prize_name = '" . mysqli_real_escape_string($connect, (string) ($row['prize_name'] ?? '')) . "',
                        prize_type = 'voucher',
                        voucher_code = '" . mysqli_real_escape_string($connect, (string) ($row['voucher_code'] ?? '')) . "',
                        weight = " . number_format((float) ($row['weight'] ?? 1), 4, '.', '') . ",
                        display_order = " . (int) ($row['display_order'] ?? 0) . ",
                        total_stock = " . (int) ($row['total_stock'] ?? 0) . ",
                        price = " . number_format((float) ($row['price'] ?? 0), 2, '.', '') . ",
                        update_by = '" . $safeActor . "',
                        update_date = CURDATE(),
                        update_time = CURTIME()
                    WHERE id = " . $prizeId . "
                      AND status = 'A'
                      AND prize_type = 'voucher'
                    LIMIT 1";
                if (mysqli_query($connect, $query) && mysqli_affected_rows($connect) >= 0) {
                    $updatedCount++;
                }
            }
        }

        luckyDrawInsertAdminLog($connect, 'import_vouchers', LUCKY_DRAW_PRIZE, 0, 'Inserted ' . $insertedCount . ' and updated ' . $updatedCount . ' voucher prize row(s) from import workbook', USER_ID, array(
            'page_title' => $pageTitle,
            'audit_action' => 'import',
            'entity_label' => 'prize',
        ));

        echo '<script>alert(' . json_encode('Import complete! Added ' . $insertedCount . ' new voucher prize row(s) and updated ' . $updatedCount . ' existing voucher prize row(s).') . ');window.location.href=' . json_encode($redirectPage) . ';</script>';
        exit;
    }
}

include_once '../menuHeader.php';
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
<div class="page-load-cover">
    <div class="container-fluid mt-3 mb-5 d-flex justify-content-center">
        <div class="col-12 col-md-11">
            <div class="row mb-3">
                <p>
                    <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    <a href="<?= htmlspecialchars(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE), ENT_QUOTES, 'UTF-8') ?>">Lucky Draw - Prize Management</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>

            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                    <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                    <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= htmlspecialchars($redirectPage, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fa-solid fa-arrow-left"></i> Back To Prize Table
                    </a>
                </div>
            </div>

            <?php if (!empty($importErrors)) { ?>
                <div class="alert alert-danger shadow-sm" role="alert">
                    <?php foreach ($importErrors as $error) { ?>
                        <div>- <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if ($action === 'preview' && !empty($previewData)) { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Preview Voucher Prize Changes</h5>
                        <form method="post" autocomplete="off">
                            <?php foreach ($previewData as $index => $row) { ?>
                                <?php
                                $changes = isset($row['changes']) && is_array($row['changes']) ? $row['changes'] : array();
                                $fieldErrors = isset($row['field_errors']) && is_array($row['field_errors']) ? $row['field_errors'] : array();
                                $isNew = isset($row['is_new']) && (string) $row['is_new'] === '1';
                                ?>
                                <div class="card mb-3 <?= $isNew ? 'row-new' : 'row-update' ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-3">
                                            <h6 class="mb-0">Voucher Prize Row #<?= (int) ($index + 1) ?></h6>
                                            <?= $isNew ? '<span class="badge bg-success">NEW</span>' : '<span class="badge bg-warning text-dark">MODIFIED</span>' ?>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-2">
                                                <label class="form-label">S/N</label>
                                                <input type="text" class="form-control" name="data[<?= (int) $index ?>][prize_id]" value="<?= htmlspecialchars((string) ($row['prize_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $isNew ? '' : 'readonly' ?>>
                                                <input type="hidden" name="data[<?= (int) $index ?>][is_new]" value="<?= $isNew ? '1' : '0' ?>">
                                                <?php if (isset($fieldErrors['prize_id'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['prize_id'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Prize Name*</label>
                                                <input type="text" class="form-control <?= isset($changes['prize_name']) ? 'highlight-change' : '' ?>" name="data[<?= (int) $index ?>][prize_name]" value="<?= htmlspecialchars((string) ($row['prize_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                                <?php if (isset($fieldErrors['prize_name'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['prize_name'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Prize Type*</label>
                                                <input type="text" class="form-control" name="data[<?= (int) $index ?>][prize_type]" value="<?= htmlspecialchars((string) ($row['prize_type'] ?? 'VOUCHER'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                                                <?php if (isset($fieldErrors['prize_type'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['prize_type'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Voucher Code*</label>
                                                <input type="text" class="form-control <?= isset($changes['voucher_code']) ? 'highlight-change' : '' ?>" name="data[<?= (int) $index ?>][voucher_code]" value="<?= htmlspecialchars((string) ($row['voucher_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                                                <?php if (isset($fieldErrors['voucher_code'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['voucher_code'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Weight*</label>
                                                <input type="number" class="form-control <?= isset($changes['weight']) ? 'highlight-change' : '' ?>" name="data[<?= (int) $index ?>][weight]" value="<?= htmlspecialchars((string) ($row['weight'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" step="0.0001" min="0" required>
                                                <?php if (isset($fieldErrors['weight'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['weight'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Display Order*</label>
                                                <input type="number" class="form-control <?= isset($changes['display_order']) ? 'highlight-change' : '' ?>" name="data[<?= (int) $index ?>][display_order]" value="<?= htmlspecialchars((string) ($row['display_order'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" min="0" required>
                                                <?php if (isset($fieldErrors['display_order'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['display_order'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Total Stock*</label>
                                                <input type="number" class="form-control <?= isset($changes['total_stock']) ? 'highlight-change' : '' ?>" name="data[<?= (int) $index ?>][total_stock]" value="<?= htmlspecialchars((string) ($row['total_stock'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" min="0" required>
                                                <?php if (isset($fieldErrors['total_stock'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['total_stock'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Price*</label>
                                                <input type="number" class="form-control <?= isset($changes['price']) ? 'highlight-change' : '' ?>" name="data[<?= (int) $index ?>][price]" value="<?= htmlspecialchars((string) ($row['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" step="0.01" min="0" required>
                                                <?php if (isset($fieldErrors['price'])) { ?><div class="field-error"><?= htmlspecialchars((string) $fieldErrors['price'], ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <div class="import-preview-actions mt-4">
                                <button class="btn btn-lg btn-rounded btn-success px-4 import-preview-primary" type="submit" name="actionBtn" value="update">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Import Lucky Draw Voucher
                                </button>
                                <a href="lucky_draw_voucher_import.php" class="btn btn-lg btn-rounded btn-secondary px-4 import-preview-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php } elseif ($action !== 'preview') { ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 1: Upload Edited Voucher Prize Excel File</h5>
                        <div class="alert alert-info">
                            Export voucher prize rows from Lucky Draw - Prize Management, edit the Excel file, then upload it here to preview and apply new or modified voucher prize rows. Audit columns at the end of the file are for reference only and will be ignored during import.
                        </div>
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
</script>
</html>
