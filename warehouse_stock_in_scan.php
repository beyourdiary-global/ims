<?php
include_once 'init.php';
include_once 'checkCurrentPagePin.php';
include_once ROOT . '/include/common.php';

if (!function_exists('scanAttachmentDirRel')) {
    function scanAttachmentDirRel()
    {
        $base = defined('img_server') ? (string) constant('img_server') : '/images_server/';
        $base = '/' . trim($base, '/');
        return $base . '/finance/stock_in/';
    }
}

if (!function_exists('scanAttachmentDirAbs')) {
    function scanAttachmentDirAbs()
    {
        $rel = ltrim((string) scanAttachmentDirRel(), '/\\');
        return rtrim((string) ROOT, '/\\') . '/' . $rel;
    }
}

if (!function_exists('scanEnsureAttachmentDir')) {
    function scanEnsureAttachmentDir()
    {
        $dir = scanAttachmentDirAbs();
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log('Failed to create attachment directory: ' . $dir);
                return false;
            }
        }
        return is_dir($dir);
    }
}

if (!function_exists('scanUploadAttachmentFiles')) {
    function scanUploadAttachmentFiles($fileField)
    {
        if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
            return array(false, array(), 'Attachment file is missing.');
        }

        $file = $_FILES[$fileField];
        $names = isset($file['name']) ? $file['name'] : array();
        $tmpNames = isset($file['tmp_name']) ? $file['tmp_name'] : array();
        $errors = isset($file['error']) ? $file['error'] : array();

        if (!is_array($names)) {
            $names = array($names);
            $tmpNames = array($tmpNames);
            $errors = array($errors);
        }

        $allowed = array('png', 'jpg', 'jpeg', 'webp');

        if (!scanEnsureAttachmentDir()) {
            return array(false, array(), 'Attachment folder is not ready.');
        }

        $saved = array();
        $hasAnyFile = false;
        $validFiles = array();

        for ($idx = 0; $idx < count($names); $idx++) {
            $origName = isset($names[$idx]) ? (string) $names[$idx] : '';
            $tmpName = isset($tmpNames[$idx]) ? (string) $tmpNames[$idx] : '';
            $errCode = isset($errors[$idx]) ? (int) $errors[$idx] : UPLOAD_ERR_NO_FILE;

            if ($errCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $hasAnyFile = true;
            if ($errCode !== UPLOAD_ERR_OK) {
                return array(false, array(), 'Attachment upload failed.');
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                return array(false, array(), 'Attachment must be png, jpg, jpeg or webp.');
            }

            $validFiles[] = array('tmpName' => $tmpName, 'ext' => $ext);
        }

        if (!$hasAnyFile || count($validFiles) === 0) {
            return array(false, array(), 'Attachment is required.');
        }

        foreach ($validFiles as $idx => $f) {
            $newName = 'stock_in_scan_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '_' . $idx . '.' . $f['ext'];
            $absPath = scanAttachmentDirAbs() . $newName;
            $relPath = scanAttachmentDirRel() . $newName;

            if (!@move_uploaded_file($f['tmpName'], $absPath)) {
                foreach ($saved as $savedRelPath) {
                    $savedAbsPath = rtrim((string) ROOT, '/\\') . '/' . ltrim((string) $savedRelPath, '/\\');
                    @unlink($savedAbsPath);
                }
                return array(false, array(), 'Failed to save attachment file.');
            }

            $saved[] = $relPath;
        }

        return array(true, $saved, '');
    }
}

if (!function_exists('scanHasUploadedFiles')) {
    function scanHasUploadedFiles($fileField)
    {
        if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
            return false;
        }

        $errors = isset($_FILES[$fileField]['error']) ? $_FILES[$fileField]['error'] : array();
        if (!is_array($errors)) {
            $errors = array($errors);
        }

        foreach ($errors as $errCode) {
            if ((int) $errCode !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('scanNormalizeAttachmentPath')) {
    function scanNormalizeAttachmentPath($path)
    {
        $path = trim((string) $path);
        if ($path === '' || strpos($path, "\0") !== false) {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        return preg_replace('#/+#', '/', $path);
    }
}

if (!function_exists('scanIsTrustedAttachmentPath')) {
    function scanIsTrustedAttachmentPath($path)
    {
        $normalizedPath = scanNormalizeAttachmentPath($path);
        if ($normalizedPath === '') {
            return false;
        }

        $allowedPrefix = rtrim(str_replace('\\', '/', (string) scanAttachmentDirRel()), '/') . '/';
        if (strpos($normalizedPath, $allowedPrefix) !== 0) {
            return false;
        }

        $fileName = basename($normalizedPath);
        if (!preg_match('/^stock_in_scan_\d{8}_\d{6}_\d{4}_\d+\.(png|jpg|jpeg|webp)$/i', $fileName)) {
            return false;
        }

        $attachmentDir = realpath(scanAttachmentDirAbs());
        $absolutePath = rtrim(str_replace('\\', '/', (string) ROOT), '/') . '/' . ltrim($normalizedPath, '/');
        $realPath = realpath($absolutePath);
        if ($attachmentDir === false || $realPath === false || !is_file($realPath)) {
            return false;
        }

        $normalizedDir = rtrim(str_replace('\\', '/', $attachmentDir), '/') . '/';
        $normalizedRealPath = str_replace('\\', '/', $realPath);
        return strpos($normalizedRealPath, $normalizedDir) === 0;
    }
}

if (!function_exists('scanFilterTrustedAttachmentList')) {
    function scanFilterTrustedAttachmentList($rawValue)
    {
        $filtered = array();
        foreach (siAttachmentDecodeList($rawValue) as $path) {
            $normalizedPath = scanNormalizeAttachmentPath($path);
            if ($normalizedPath !== '' && scanIsTrustedAttachmentPath($normalizedPath)) {
                $filtered[$normalizedPath] = true;
            }
        }

        return array_keys($filtered);
    }
}

if (!function_exists('scanResolveSubmittedAttachments')) {
    function scanResolveSubmittedAttachments($fileField, $currentField = 'current_attachment')
    {
        $currentRaw = trim((string) postSpaceFilter($currentField));
        $attachmentList = scanFilterTrustedAttachmentList($currentRaw);

        if (scanHasUploadedFiles($fileField)) {
            $uploadResult = scanUploadAttachmentFiles($fileField);
            if (!$uploadResult[0]) {
                return array(false, $attachmentList, (string) $uploadResult[2]);
            }
            $attachmentList = array_merge($attachmentList, (array) $uploadResult[1]);
        }

        $attachmentList = siAttachmentDecodeList(siAttachmentEncodeList($attachmentList));
        if (count($attachmentList) === 0) {
            return array(false, $attachmentList, 'Attachment is required.');
        }

        return array(true, $attachmentList, '');
    }
}

if (!function_exists('shopeeOmsFormatWarehousePackageDisplayLabel')) {
    function shopeeOmsFormatWarehousePackageDisplayLabel($label, $index)
    {
        $label = trim((string) $label);
        if ($label === '') {
            return '';
        }

        $displayName = $label;
        $qty = 1;
        if (preg_match('/^(.*)\sx(\d+)$/i', $label, $matches)) {
            $displayName = trim((string) $matches[1]);
            $qty = max(1, (int) $matches[2]);
        }

        return ((int) $index + 1) . ') ' . $displayName . ' - ' . $qty . ' SET';
    }
}

$submittedOmsToken = isset($_POST['scan_token']) ? trim((string) $_POST['scan_token']) : '';
$omsToken = $submittedOmsToken !== '' ? $submittedOmsToken : (isset($_GET['t']) ? trim((string) $_GET['t']) : '');
if ($omsToken !== '' && preg_match('/^[A-Za-z0-9\-_\.=%]+$/', $omsToken)) {
    $safeOmsToken = mysqli_real_escape_string($finance_connect, $omsToken);
    $omsTokenSql = "SELECT * FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE token = '" . $safeOmsToken . "' AND token_type = 'stock_out' AND status = 'A' ORDER BY id DESC LIMIT 1";
    $omsTokenRst = mysqli_query($finance_connect, $omsTokenSql);
    if ($omsTokenRst && mysqli_num_rows($omsTokenRst) > 0) {
        $omsTokenRow = mysqli_fetch_assoc($omsTokenRst);
        $omsSourceConfig = null;
        $omsOrderRow = shopeeOmsLoadOrderFromTokenRow($connect, $finance_connect, $omsTokenRow, $omsSourceConfig, 'shopee');
        $omsPlatform = isset($omsSourceConfig['platform']) ? (string) $omsSourceConfig['platform'] : shopeeOmsGetOrderSourcePlatform($omsOrderRow, 'shopee');
        $omsPlatformLabel = isset($omsSourceConfig['label']) ? (string) $omsSourceConfig['label'] : ucfirst($omsPlatform);
        $omsOrderCode = !empty($omsOrderRow)
            ? shopeeOmsGetOrderCodeValue($omsOrderRow, $omsSourceConfig)
            : trim((string) (isset($omsTokenRow['order_code']) ? $omsTokenRow['order_code'] : ''));
        $omsClientIp = shopeeOmsGetClientIp();
        $omsCountryCode = '';
        if ($omsClientIp !== '') {
            if (shopeeOmsIsPrivateOrReservedIp($omsClientIp)) {
                $omsCountryCode = 'PRIVATE';
            } else {
                $omsCountryCode = shopeeOmsLookupCountryCode($omsClientIp);
            }
        }
        shopeeOmsAuditLog('security_pass', 'Location policy passed.', array(
            'order_id' => (int) (isset($omsTokenRow['order_id']) ? $omsTokenRow['order_id'] : 0),
            'ip' => ($omsClientIp === '' ? 'Unknown' : $omsClientIp),
            'country' => ($omsCountryCode === '' ? 'Unknown' : $omsCountryCode),
        ));
        $omsPersistedAttachments = scanFilterTrustedAttachmentList(trim((string) postSpaceFilter('current_attachment')));
        $omsScanSubmit = (strtolower((string) $_SERVER['REQUEST_METHOD']) === 'post' && isset($_POST['actionBtn']) && (string) $_POST['actionBtn'] === 'submitOmsStockOut');
        $omsStatusTitle = 'Warehouse Stock-out Ready';
        $omsStatusClass = 'warning';
        $omsMessage = 'Review the order details below, then submit the warehouse stock-out scan to move this order to Shipped.';

        if ($omsScanSubmit) {
            $attachmentResult = scanResolveSubmittedAttachments('stock_in_attachment');
            $omsPersistedAttachments = isset($attachmentResult[1]) ? (array) $attachmentResult[1] : array();
            if (!$attachmentResult[0]) {
                $omsStatusClass = 'danger';
                $omsStatusTitle = 'Attachment Required';
                $omsMessage = isset($attachmentResult[2]) ? (string) $attachmentResult[2] : 'Please upload at least 1 attachment photo to submit stock out.';
                shopeeOmsAuditLog('submit_failed', $omsMessage, array(
                    'order_id' => (int) (isset($omsTokenRow['order_id']) ? $omsTokenRow['order_id'] : 0),
                    'order_code' => $omsOrderCode,
                ));
            } else {
                $omsScanResult = shopeeOmsProcessWarehouseScanByToken(
                    $connect,
                    $finance_connect,
                    $omsToken,
                    defined('USER_ID') && USER_ID !== '' ? USER_ID : 'QR_PUBLIC',
                    defined('USER_GROUP') ? (int) USER_GROUP : 0,
                    'Warehouse Stock-out Scan',
                    $omsPersistedAttachments
                );
                $omsStatusClass = !empty($omsScanResult['success']) ? 'success' : 'danger';
                $omsStatusTitle = !empty($omsScanResult['success']) ? 'Warehouse Stock-out Completed' : 'Warehouse Stock-out Failed';
                $omsMessage = isset($omsScanResult['message']) ? (string) $omsScanResult['message'] : 'Unable to process warehouse stock-out scan.';
                if (!empty($omsScanResult['success'])) {
                    shopeeOmsAuditLog('submit_success', $omsMessage, array(
                        'order_id' => (int) (isset($omsTokenRow['order_id']) ? $omsTokenRow['order_id'] : 0),
                        'order_code' => $omsOrderCode,
                        'status' => 'shipped',
                    ));
                } else {
                    shopeeOmsAuditLog('submit_failed', $omsMessage, array(
                        'order_id' => (int) (isset($omsTokenRow['order_id']) ? $omsTokenRow['order_id'] : 0),
                        'order_code' => $omsOrderCode,
                    ));
                }
            }
            $omsTokenRst = mysqli_query($finance_connect, $omsTokenSql);
            if ($omsTokenRst && mysqli_num_rows($omsTokenRst) > 0) {
                $omsTokenRow = mysqli_fetch_assoc($omsTokenRst);
            }
            $omsSourceConfig = null;
            $omsOrderRow = shopeeOmsLoadOrderFromTokenRow($connect, $finance_connect, $omsTokenRow, $omsSourceConfig, 'shopee');
            $omsPlatform = isset($omsSourceConfig['platform']) ? (string) $omsSourceConfig['platform'] : shopeeOmsGetOrderSourcePlatform($omsOrderRow, 'shopee');
            $omsPlatformLabel = isset($omsSourceConfig['label']) ? (string) $omsSourceConfig['label'] : ucfirst($omsPlatform);
            $omsOrderCode = !empty($omsOrderRow)
                ? shopeeOmsGetOrderCodeValue($omsOrderRow, $omsSourceConfig)
                : trim((string) (isset($omsTokenRow['order_code']) ? $omsTokenRow['order_code'] : ''));
        } else if (!empty($omsTokenRow['used_at'])) {
            $omsStatusTitle = 'Warehouse Stock-out Already Completed';
            $omsStatusClass = 'success';
            $omsMessage = 'This warehouse stock-out link has already been used.';
        }

        $omsSourceConfig = is_array($omsSourceConfig) ? $omsSourceConfig : array();
        $omsSummary = !empty($omsOrderRow) ? shopeeOmsBuildOrderProductSummaryBySource($connect, $omsOrderRow, $omsSourceConfig) : array();
        $omsPackagePinAccess = checkPinByGroupId($connect, 21);
        $omsProductPinAccess = checkPinByGroupId($connect, 20);
        $omsCanEditPackage = isActionAllowed('Edit', $omsPackagePinAccess);
        $omsCanEditProduct = isActionAllowed('Edit', $omsProductPinAccess);
        $omsDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect);
        $omsStockOutWarehouseName = !empty($omsOrderRow)
            ? shopeeOmsResolveStockOutWarehouseName($connect, $omsOrderRow, $omsDefaultWarehouseId)
            : '';
        $omsCustomerDisplayName = !empty($omsOrderRow)
            ? shopeeOmsGetOrderCustomerNameText($connect, $finance_connect, $omsOrderRow, $omsSourceConfig)
            : trim((string) (isset($omsTokenRow['customer_name']) ? $omsTokenRow['customer_name'] : ''));
        $omsAddressField = isset($omsSourceConfig['address_field']) ? (string) $omsSourceConfig['address_field'] : 'customer_address';
        $omsAirbillField = isset($omsSourceConfig['airbill_no_field']) ? (string) $omsSourceConfig['airbill_no_field'] : 'airbill_no';
        $omsAirbillAttachmentField = isset($omsSourceConfig['airbill_attachment_field']) ? (string) $omsSourceConfig['airbill_attachment_field'] : 'airbill_attachment';
        $omsAddressValue = isset($omsOrderRow[$omsAddressField]) && trim((string) $omsOrderRow[$omsAddressField]) !== ''
            ? (string) $omsOrderRow[$omsAddressField]
            : '-';
        $omsAirbillValue = isset($omsOrderRow[$omsAirbillField]) && trim((string) $omsOrderRow[$omsAirbillField]) !== ''
            ? (string) $omsOrderRow[$omsAirbillField]
            : '-';
        $omsAirbillAttachmentUrl = '';
        $omsAirbillAttachmentName = '';
        $omsAirbillAttachmentExt = '';
        if (!empty($omsOrderRow[$omsAirbillAttachmentField])) {
            $storedAttachment = trim(str_replace('\\', '/', (string) $omsOrderRow[$omsAirbillAttachmentField]), '/');
            $omsAirbillAttachmentName = basename($storedAttachment);
            $omsAirbillAttachmentExt = strtolower((string) pathinfo($omsAirbillAttachmentName, PATHINFO_EXTENSION));
            $omsAirbillAttachmentUrl = shopeeOmsBuildAirbillAttachmentUrl($storedAttachment);
            if ($omsAirbillAttachmentUrl === '') {
                $imgServerBase = defined('img_server') ? (string) constant('img_server') : '/images_server/';
                $omsAirbillAttachmentUrl = rtrim((string) $SITEURL, '/') . '/' . trim($imgServerBase, '/\\') . '/shopee_airbill_attachment/' . rawurlencode($omsAirbillAttachmentName);
            }
        }
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($omsStatusTitle) ?></title>
            <link href="<?= $SITEURL ?>/header/fontawesome-free-6.0.0-web/css/all.min.css" rel="stylesheet">
            <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
            <style>
                body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; margin: 0; background: linear-gradient(135deg, #f4f8fb 0%, #eaf2f9 100%); color: #1f2d3d; }
                .container { max-width: 900px; margin: 32px auto; background: #fff; border-radius: 14px; box-shadow: 0 14px 40px rgba(22, 56, 89, 0.12); padding: 24px; }
                .title { margin: 0 0 8px 0; font-size: 28px; }
                .subtitle { margin: 0 0 16px 0; color: #5f7185; }
                .alert { border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; border: 1px solid transparent; }
                .alert-success { background: #edf9f0; border-color: #b8e0c1; color: #1a6b2f; }
                .alert-warning { background: #fff8e9; border-color: #f5d28b; color: #7a5600; }
                .alert-danger { background: #ffeef0; border-color: #f3bdc5; color: #8a2230; }
                .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 18px; }
                .card { border: 1px solid #e2ebf3; border-radius: 10px; padding: 12px; background: #fbfdff; }
                .card h4 { margin: 0 0 10px 0; font-size: 16px; }
                .k { color: #5f7185; }
                .v { font-weight: 600; }
                .v a { color: inherit; text-decoration: none; transition: color 0.2s ease; }
                .v a:hover { color: #1f6fb2; text-decoration: none; }
                .attachment-wrap { margin-top: 18px; }
                .attachment-preview-card { border: 1px solid #e2ebf3; border-radius: 10px; padding: 14px; background: #fbfdff; }
                .attachment-preview-media { margin-top: 12px; }
                .attachment-preview-media img { max-width: 100%; max-height: 360px; border: 1px solid #d8e3ee; border-radius: 8px; background: #fff; display: block; }
                .attachment-preview-media iframe { width: 100%; height: 420px; border: 1px solid #d8e3ee; border-radius: 8px; background: #fff; }
                .attachment-link { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; text-decoration: none; }
                .scan-attachment-input-row { display:flex; gap:8px; align-items:center; }
                .scan-attachment-input { display:block; flex:1; padding:8px; border:1px solid #ccd9e6; border-radius:8px; background:#fff; }
                .small { font-size: 12px; color: #617487; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #d8e3ee; padding: 8px; text-align: left; }
                th { background: #f3f8fd; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1 class="title"><?= htmlspecialchars($omsStatusTitle) ?></h1>
                <p class="subtitle"><?= htmlspecialchars($omsPlatformLabel) ?> OMS Warehouse Stock-out Scan</p>
                <div class="alert alert-<?= htmlspecialchars($omsStatusClass) ?>"><?= htmlspecialchars($omsMessage) ?></div>

                <?php if (!empty($omsOrderRow)) { ?>
                    <div class="grid">
                        <div class="card">
                            <h4>Order Details</h4>
                            <div><span class="k">Order ID:</span> <span class="v"><?= htmlspecialchars($omsOrderCode !== '' ? $omsOrderCode : '-') ?></span></div>
                            <div><span class="k">Customer Name:</span> <span class="v"><?= htmlspecialchars($omsCustomerDisplayName !== '' ? $omsCustomerDisplayName : '-') ?></span></div>
                            <div><span class="k">Address:</span> <span class="v"><?= nl2br(htmlspecialchars($omsAddressValue)) ?></span></div>
                            <div><span class="k">Airbill:</span> <span class="v"><?= htmlspecialchars($omsAirbillValue) ?></span></div>
                            <div><span class="k">Airbill Attachment:</span> <span class="v"><?= htmlspecialchars($omsAirbillAttachmentName !== '' ? $omsAirbillAttachmentName : '-') ?></span></div>
                            <div><span class="k">Current Status:</span> <span class="v"><?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($omsOrderRow['order_status']) ? $omsOrderRow['order_status'] : '')) ?></span></div>
                        </div>
                        <div class="card">
                            <h4>Warehouse Package</h4>
                            <div><span class="k">Package:</span></div>
                            <div><span class="v"><?php
                                $omsPackageSummaryRows = isset($omsSummary['package_summary_rows']) && is_array($omsSummary['package_summary_rows']) ? $omsSummary['package_summary_rows'] : array();
                                if (!empty($omsPackageSummaryRows)) {
                                    $omsPackageParts = array();
                                    foreach ($omsPackageSummaryRows as $omsPackageIndex => $omsPackageRow) {
                                        $omsPackageLabel = isset($omsPackageRow['label']) ? (string) $omsPackageRow['label'] : '';
                                        $omsPackageId = isset($omsPackageRow['package_id']) ? (int) $omsPackageRow['package_id'] : 0;
                                        $omsPackageDisplayLabel = shopeeOmsFormatWarehousePackageDisplayLabel($omsPackageLabel, $omsPackageIndex);
                                        if ($omsPackageDisplayLabel === '') {
                                            continue;
                                        }
                                        if ($omsCanEditPackage && $omsPackageId > 0) {
                                            $omsPackageParts[] = '<a href="' . htmlspecialchars($SITEURL . '/package.php?id=' . $omsPackageId . '&act=E', ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($omsPackageDisplayLabel, ENT_QUOTES, 'UTF-8') . '</a>';
                                        } else {
                                            $omsPackageParts[] = htmlspecialchars($omsPackageDisplayLabel, ENT_QUOTES, 'UTF-8');
                                        }
                                    }
                                    echo implode('<br>', $omsPackageParts);
                                } else {
                                    echo htmlspecialchars(!empty($omsSummary['bundle_name']) ? $omsSummary['bundle_name'] : '-', ENT_QUOTES, 'UTF-8');
                                }
                            ?></span></div>
                            <div><span class="k">Products:</span> <span class="v"><?php
                                $omsProductSummaryRows = isset($omsSummary['product_summary_rows']) && is_array($omsSummary['product_summary_rows']) ? $omsSummary['product_summary_rows'] : array();
                                if (!empty($omsProductSummaryRows)) {
                                    $omsProductParts = array();
                                    foreach ($omsProductSummaryRows as $omsProductRow) {
                                        $omsProductLabel = isset($omsProductRow['label']) ? (string) $omsProductRow['label'] : '';
                                        $omsProductId = isset($omsProductRow['product_id']) ? (int) $omsProductRow['product_id'] : 0;
                                        if ($omsCanEditProduct && $omsProductId > 0) {
                                            $omsProductParts[] = '<a href="' . htmlspecialchars($SITEURL . '/product.php?id=' . $omsProductId . '&act=E', ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($omsProductLabel, ENT_QUOTES, 'UTF-8') . '</a>';
                                        } else {
                                            $omsProductParts[] = htmlspecialchars($omsProductLabel, ENT_QUOTES, 'UTF-8');
                                        }
                                    }
                                    echo implode(', ', $omsProductParts);
                                } else {
                                    echo htmlspecialchars(!empty($omsSummary['product_lines']) ? implode(', ', $omsSummary['product_lines']) : '-', ENT_QUOTES, 'UTF-8');
                                }
                            ?></span></div>
                            <div><span class="k">Stock Out Warehouse:</span> <span class="v"><?= htmlspecialchars($omsStockOutWarehouseName !== '' ? $omsStockOutWarehouseName : '-') ?></span></div>
                        </div>
                    </div>

                    <?php if ($omsAirbillAttachmentUrl !== '') { ?>
                        <div class="attachment-wrap">
                            <div class="attachment-preview-card">
                                <h4>Airbill Attachment</h4>
                                <a class="attachment-link" href="<?= htmlspecialchars($omsAirbillAttachmentUrl) ?>" target="_blank" rel="noopener noreferrer" download="<?= htmlspecialchars($omsAirbillAttachmentName) ?>">
                                    <i class="fa-solid fa-download"></i>
                                    <span>Download Attachment</span>
                                </a>
                                <div class="attachment-preview-media">
                                    <?php if (in_array($omsAirbillAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                                        <img src="<?= htmlspecialchars($omsAirbillAttachmentUrl) ?>" alt="Airbill Attachment Preview">
                                    <?php } else if ($omsAirbillAttachmentExt === 'pdf') { ?>
                                        <iframe src="<?= htmlspecialchars($omsAirbillAttachmentUrl) ?>" title="Airbill Attachment Preview"></iframe>
                                    <?php } else { ?>
                                        <div class="k">Preview is not available for this file type. Use the download link above.</div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <?php if (!empty($omsSummary['product_lines'])) { ?>
                        <h3>Product Details</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th>Product</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $omsProductDetailRows = isset($omsSummary['product_summary_rows']) && is_array($omsSummary['product_summary_rows']) && !empty($omsSummary['product_summary_rows'])
                                    ? $omsSummary['product_summary_rows']
                                    : array_map(function ($productLine) {
                                        return array(
                                            'product_id' => 0,
                                            'label' => (string) $productLine,
                                        );
                                    }, $omsSummary['product_lines']);
                                foreach ($omsProductDetailRows as $idx => $productRow) {
                                    $productLine = isset($productRow['label']) ? (string) $productRow['label'] : '';
                                    $productId = isset($productRow['product_id']) ? (int) $productRow['product_id'] : 0;
                                ?>
                                    <tr>
                                        <td><?= (int) ($idx + 1) ?></td>
                                        <td><?php if ($omsCanEditProduct && $productId > 0) { ?>
                                                <a href="<?= htmlspecialchars($SITEURL . '/product.php?id=' . $productId . '&act=E') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($productLine) ?></a>
                                            <?php } else { ?>
                                                <?= htmlspecialchars($productLine) ?>
                                            <?php } ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>

                    <?php if (empty($omsTokenRow['used_at'])) { ?>
                        <h3 style="margin-top: 20px;">Upload Attachment</h3>
                        <form method="post" enctype="multipart/form-data" style="margin-top: 10px;">
                            <input type="hidden" name="scan_token" value="<?= htmlspecialchars($omsToken) ?>">
                            <input type="hidden" name="current_attachment" value="<?= htmlspecialchars((string) siAttachmentEncodeList($omsPersistedAttachments)) ?>">
                            <div style="max-width: 560px;">
                                <label for="stock_in_attachment" style="display:block;margin-bottom:8px;font-weight:600;">Attachment Photo <span style="color:#d00000;">*</span></label>
                                <div id="stock_in_attachment_inputs" style="display:flex;flex-direction:column;gap:8px;">
                                    <div class="scan-attachment-input-row">
                                        <input id="stock_in_attachment" class="scan-attachment-input" name="stock_in_attachment[]" type="file" accept=".png,.jpg,.jpeg,.webp"<?= count($omsPersistedAttachments) === 0 ? ' required' : '' ?>>
                                        <button type="button" class="mt-1" id="action_menu_btn" data-attach-action="add" title="Add another attachment"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                    </div>
                                </div>
                                <?php if (count($omsPersistedAttachments) > 0) { ?>
                                    <div style="margin-top:10px;padding:10px;border:1px solid #d8e3ee;border-radius:8px;background:#fff;max-width:420px;">
                                        <div class="small" style="margin-bottom:8px;">Uploaded attachment kept after the failed submit:</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <?php foreach ($omsPersistedAttachments as $attachIdx => $attachPath) {
                                                $attachUrl = rtrim((string) $SITEURL, '/') . '/' . ltrim((string) $attachPath, '/');
                                                $attachExt = strtolower((string) pathinfo((string) $attachPath, PATHINFO_EXTENSION));
                                            ?>
                                                <div style="display:flex;flex-direction:column;gap:6px;">
                                                    <?php if (in_array($attachExt, array('png', 'jpg', 'jpeg', 'webp', 'gif'), true)) { ?>
                                                        <a href="<?= htmlspecialchars($attachUrl) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= htmlspecialchars($attachUrl) ?>" alt="Attachment <?= (int) ($attachIdx + 1) ?>" style="max-width:120px;max-height:120px;object-fit:cover;border-radius:6px;border:1px solid #d8e3ee;"></a>
                                                    <?php } ?>
                                                    <a href="<?= htmlspecialchars($attachUrl) ?>" target="_blank" rel="noopener noreferrer" class="small">View Attachment <?= (int) ($attachIdx + 1) ?></a>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div id="stock_in_attachment_preview" style="margin-top:10px;padding:10px;border:1px dashed #c7d7e8;border-radius:8px;background:#f8fbff;max-width:420px;">
                                    <div id="stock_in_attachment_img_list" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
                                    <span id="stock_in_attachment_placeholder" class="small">Image preview</span>
                                </div>
                                <div class="small" style="margin-top:6px;">Required: upload at least one photo to complete stock out. Click + to add more attachments.</div>
                                <button type="submit" name="actionBtn" value="submitOmsStockOut" style="margin-top:12px;padding:10px 16px;border:0;border-radius:8px;background:#1f6fd5;color:#fff;font-weight:600;">Submit Warehouse Stock-out</button>
                            </div>
                        </form>
                    <?php } ?>
                <?php } else { ?>
                    <div class="alert alert-danger">Order linked to this warehouse stock-out token could not be found.</div>
                <?php } ?>
                <p class="small" style="margin-top:16px;">If you are blocked unexpectedly, please contact administrator.</p>
            </div>
            <script>
            (function () {
                var inputWrap = document.getElementById('stock_in_attachment_inputs');
                var listWrap = document.getElementById('stock_in_attachment_img_list');
                var placeholder = document.getElementById('stock_in_attachment_placeholder');
                if (!inputWrap || !listWrap || !placeholder) {
                    return;
                }

                function refreshPreview() {
                    listWrap.innerHTML = '';

                    var hasImage = false;
                    var hasFiles = false;
                    var inputs = inputWrap.querySelectorAll('.scan-attachment-input');
                    inputs.forEach(function (input) {
                        if (!input.files || input.files.length === 0) {
                            return;
                        }

                        hasFiles = true;
                        Array.prototype.forEach.call(input.files, function (file) {
                            if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                                return;
                            }

                            hasImage = true;
                            var objectUrl = URL.createObjectURL(file);
                            var img = document.createElement('img');
                            img.src = objectUrl;
                            img.alt = 'Attachment Preview';
                            img.style.maxWidth = '120px';
                            img.style.maxHeight = '120px';
                            img.style.objectFit = 'cover';
                            img.style.borderRadius = '6px';
                            img.onload = function () {
                                URL.revokeObjectURL(objectUrl);
                            };
                            listWrap.appendChild(img);
                        });
                    });

                    placeholder.style.display = hasImage ? 'none' : 'inline';
                    placeholder.textContent = hasFiles && !hasImage ? 'Selected file is not an image preview.' : 'Image preview';
                }

                inputWrap.addEventListener('change', function (e) {
                    if (e.target && e.target.classList.contains('scan-attachment-input')) {
                        refreshPreview();
                    }
                });

                inputWrap.addEventListener('click', function (e) {
                    var target = e.target;
                    if (!target) {
                        return;
                    }

                    var addBtn = target.closest('[data-attach-action="add"]');
                    var removeBtn = target.closest('[data-attach-action="remove"]');

                    if (addBtn) {
                        var row = document.createElement('div');
                        row.className = 'scan-attachment-input-row';
                        row.innerHTML = '<input class="scan-attachment-input" name="stock_in_attachment[]" type="file" accept=".png,.jpg,.jpeg,.webp">' +
                            '<button type="button" class="mt-1" id="action_menu_btn" data-attach-action="remove" title="Remove attachment row"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>';
                        inputWrap.appendChild(row);
                        return;
                    }

                    if (removeBtn) {
                        var rows = inputWrap.querySelectorAll('.scan-attachment-input-row');
                        if (rows.length <= 1) {
                            var onlyInput = rows[0] ? rows[0].querySelector('.scan-attachment-input') : null;
                            if (onlyInput) {
                                onlyInput.value = '';
                            }
                        } else {
                            var rowToRemove = removeBtn.closest('.scan-attachment-input-row');
                            if (rowToRemove) {
                                rowToRemove.remove();
                            }
                        }
                        refreshPreview();
                    }
                });
            })();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';

if (!function_exists('scanAttachmentDirAbs')) {
    function scanAttachmentDirAbs()
    {
        $rel = ltrim((string) scanAttachmentDirRel(), '/\\');
        return rtrim((string) ROOT, '/\\') . '/' . $rel;
    }
}

if (!function_exists('scanEnsureAttachmentDir')) {
    function scanEnsureAttachmentDir()
    {
        $dir = scanAttachmentDirAbs();
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                error_log('Failed to create attachment directory: ' . $dir);
                return false;
            }
        }
        return is_dir($dir);
    }
}

if (!function_exists('scanUploadAttachmentFiles')) {
    function scanUploadAttachmentFiles($fileField)
    {
        if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
            return array(false, array(), 'Attachment file is missing.');
        }

        $file = $_FILES[$fileField];
        $names = isset($file['name']) ? $file['name'] : array();
        $tmpNames = isset($file['tmp_name']) ? $file['tmp_name'] : array();
        $errors = isset($file['error']) ? $file['error'] : array();

        if (!is_array($names)) {
            $names = array($names);
            $tmpNames = array($tmpNames);
            $errors = array($errors);
        }

        $allowed = array('png', 'jpg', 'jpeg', 'webp');

        if (!scanEnsureAttachmentDir()) {
            return array(false, array(), 'Attachment folder is not ready.');
        }

        $saved = array();
        $hasAnyFile = false;
        $validFiles = array();

        // Pass 1: Validate all files first
        for ($idx = 0; $idx < count($names); $idx++) {
            $origName = isset($names[$idx]) ? (string) $names[$idx] : '';
            $tmpName = isset($tmpNames[$idx]) ? (string) $tmpNames[$idx] : '';
            $errCode = isset($errors[$idx]) ? (int) $errors[$idx] : UPLOAD_ERR_NO_FILE;

            if ($errCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $hasAnyFile = true;
            if ($errCode !== UPLOAD_ERR_OK) {
                return array(false, array(), 'Attachment upload failed.');
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                return array(false, array(), 'Attachment must be png, jpg, jpeg or webp.');
            }
            
            $validFiles[] = array('tmpName' => $tmpName, 'ext' => $ext);
        }

        if (!$hasAnyFile || count($validFiles) === 0) {
            return array(false, array(), 'Attachment is required.');
        }

        // Pass 2: Move files safely
        foreach ($validFiles as $idx => $f) {
            $newName = 'stock_in_scan_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '_' . $idx . '.' . $f['ext'];
            $absPath = scanAttachmentDirAbs() . $newName;
            $relPath = scanAttachmentDirRel() . $newName;

            if (!@move_uploaded_file($f['tmpName'], $absPath)) {
                // Rollback previously saved files to avoid orphans
                foreach ($saved as $savedRelPath) {
                    $savedAbsPath = rtrim((string) ROOT, '/\\') . '/' . ltrim((string) $savedRelPath, '/\\');
                    @unlink($savedAbsPath);
                }
                return array(false, array(), 'Failed to save attachment file.');
            }

            $saved[] = $relPath;
        }

        return array(true, $saved, '');
    }
}

if (!function_exists('scanGetAllowedCountries')) {
    function scanGetAllowedCountries()
    {
        $raw = trim((string) getenv('SOR_QR_ALLOWED_COUNTRIES'));
        if ($raw === '') {
            $raw = 'MY';
        }
        $parts = explode(',', $raw);
        $list = array();
        foreach ($parts as $part) {
            $code = strtoupper(trim((string) $part));
            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $list[$code] = true;
            }
        }
        return array_keys($list);
    }
}

if (!function_exists('scanGetClientIp')) {
    function scanGetClientIp()
    {
        $candidates = array();
        $headerKeys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );

        foreach ($headerKeys as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }
            $raw = trim((string) $_SERVER[$key]);
            if ($raw === '') {
                continue;
            }

            $parts = explode(',', $raw);
            foreach ($parts as $part) {
                $ip = trim((string) $part);
                if ($ip !== '') {
                    $candidates[] = $ip;
                }
            }
        }

        $firstValid = '';
        foreach ($candidates as $candidate) {
            if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                continue;
            }
            if ($firstValid === '') {
                $firstValid = $candidate;
            }
            if (!scanIsPrivateOrReservedIp($candidate)) {
                return $candidate;
            }
        }

        return $firstValid;
    }
}

if (!function_exists('scanAllowPrivateIpFallback')) {
    function scanAllowPrivateIpFallback()
    {
        $raw = strtolower(trim((string) getenv('SOR_QR_ALLOW_PRIVATE_IP')));
        return in_array($raw, array('1', 'true', 'yes', 'on'), true);
    }
}

if (!function_exists('scanIsPrivateOrReservedIp')) {
    function scanIsPrivateOrReservedIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

if (!function_exists('scanLookupCountryCode')) {
    function scanLookupCountryCode($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        // Do not attempt lookups for private or reserved IP ranges.
        if (scanIsPrivateOrReservedIp($ip)) {
            return '';
        }

        // Simple per-request cache to avoid repeated lookups for the same IP.
        static $cache = array();
        if (array_key_exists($ip, $cache)) {
            return $cache[$ip];
        }

        $code = '';

        // Prefer a local GeoIP database if available to avoid external calls.
        if (function_exists('geoip_country_code_by_name')) {
            $geoipCode = @geoip_country_code_by_name($ip);
            if (is_string($geoipCode)) {
                $geoipCode = strtoupper(trim($geoipCode));
                if (preg_match('/^[A-Z]{2}$/', $geoipCode)) {
                    $code = $geoipCode;
                }
            }
        }

        // Fallback to external service only if local lookup failed.
        if ($code === '') {
            $url = 'https://ipapi.co/' . rawurlencode($ip) . '/country/';
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 3,
                    'ignore_errors' => true,
                ),
                'ssl' => array(
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ),
            ));

            $resp = @file_get_contents($url, false, $context);
            if ($resp !== false) {
                $respCode = strtoupper(trim((string) $resp));
                if (preg_match('/^[A-Z]{2}$/', $respCode)) {
                    $code = $respCode;
                }
            }
        }

        // Cache even empty result to avoid repeated external lookups.
        $cache[$ip] = $code;
        return $code;
    }
}

if (!function_exists('scanSaveOrderSecure')) {
    function scanSaveOrderSecure($db, $orderTable, $itemTable, $warehouseId, $orderNumber, $items, $actor, $attachments)
    {
        $warehouseId = (int) $warehouseId;
        $orderNumber = trim((string) $orderNumber);
        $actor = trim((string) $actor);
        $attachmentValue = siAttachmentEncodeList($attachments);
        $attachmentList = siAttachmentDecodeList($attachmentValue);

        if ($warehouseId <= 0 || $orderNumber === '' || count($attachmentList) === 0 || count($items) === 0) {
            return array(false, 'Missing required stock in data.', 0, false);
        }

        if ($actor === '') {
            $actor = 'QR_PUBLIC';
        }

        mysqli_begin_transaction($db);

        $stockInOrderId = 0;
        $createdNewOrder = false;

        try {
            $checkSql = "SELECT id FROM `" . $orderTable . "` WHERE warehouse_id=? AND order_number=? AND status='A' AND COALESCE(NULLIF(TRIM(stock_type), ''), 'Stock In')='Stock In' LIMIT 1";
            $checkStmt = mysqli_prepare($db, $checkSql);
            if (!$checkStmt) {
                throw new Exception('Failed to prepare duplicate check.');
            }
            mysqli_stmt_bind_param($checkStmt, 'is', $warehouseId, $orderNumber);
            mysqli_stmt_execute($checkStmt);
            $checkRst = mysqli_stmt_get_result($checkStmt);
            if ($checkRst && ($existing = mysqli_fetch_assoc($checkRst))) {
                $stockInOrderId = (int) $existing['id'];
                mysqli_stmt_close($checkStmt);

                $countSql = "SELECT COUNT(1) AS item_count FROM `" . $itemTable . "` WHERE stock_in_order_id=? AND status='A'";
                $countStmt = mysqli_prepare($db, $countSql);
                if (!$countStmt) {
                    throw new Exception('Failed to prepare stock in item count check.');
                }
                mysqli_stmt_bind_param($countStmt, 'i', $stockInOrderId);
                mysqli_stmt_execute($countStmt);
                $countRst = mysqli_stmt_get_result($countStmt);
                $existingItemCount = 0;
                if ($countRst && ($countRow = mysqli_fetch_assoc($countRst))) {
                    $existingItemCount = isset($countRow['item_count']) ? (int) $countRow['item_count'] : 0;
                }
                mysqli_stmt_close($countStmt);

                $existingAttachment = '';
                $attachmentSql = "SELECT attachment FROM `" . $orderTable . "` WHERE id=? LIMIT 1";
                $attachmentStmt = mysqli_prepare($db, $attachmentSql);
                if ($attachmentStmt) {
                    mysqli_stmt_bind_param($attachmentStmt, 'i', $stockInOrderId);
                    mysqli_stmt_execute($attachmentStmt);
                    $attachmentRst = mysqli_stmt_get_result($attachmentStmt);
                    if ($attachmentRst && ($attachmentRow = mysqli_fetch_assoc($attachmentRst))) {
                        $existingAttachment = isset($attachmentRow['attachment']) ? trim((string) $attachmentRow['attachment']) : '';
                    }
                    mysqli_stmt_close($attachmentStmt);
                }

                if ($existingItemCount > 0) {
                    $mergedAttachment = siAttachmentEncodeList(array_merge(siAttachmentDecodeList($existingAttachment), $attachmentList));
                    if ($mergedAttachment !== siAttachmentEncodeList(siAttachmentDecodeList($existingAttachment))) {
                        $safeAttachment = mysqli_real_escape_string($db, $mergedAttachment);
                        mysqli_query($db, "UPDATE `" . $orderTable . "` SET attachment='" . $safeAttachment . "', update_by='" . mysqli_real_escape_string($db, $actor) . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . (int) $stockInOrderId . "' LIMIT 1");
                    }
                    mysqli_commit($db);
                    return array(true, 'Stock In already exists for this order number.', $stockInOrderId, true);
                }

                // Repair mode: existing order found without items.
                $touchOrderSql = "UPDATE `" . $orderTable . "` SET stock_in_date=NOW(), attachment=? WHERE id=? LIMIT 1";
                $touchOrderStmt = mysqli_prepare($db, $touchOrderSql);
                if (!$touchOrderStmt) {
                    throw new Exception('Failed to prepare stock in order timestamp update.');
                }
                mysqli_stmt_bind_param($touchOrderStmt, 'si', $attachmentValue, $stockInOrderId);
                if (!mysqli_stmt_execute($touchOrderStmt)) {
                    mysqli_stmt_close($touchOrderStmt);
                    throw new Exception('Failed to update stock in date time.');
                }
                mysqli_stmt_close($touchOrderStmt);
            } else {
                mysqli_stmt_close($checkStmt);

                $insertOrderSql = "INSERT INTO `" . $orderTable . "`
                    (warehouse_id, order_number, stock_in_date, attachment, stock_type, create_by, create_date, create_time, status)
                    VALUES
                    (?, ?, NOW(), ?, 'Stock In', ?, CURDATE(), CURTIME(), 'A')";
                $insertOrderStmt = mysqli_prepare($db, $insertOrderSql);
                if (!$insertOrderStmt) {
                    throw new Exception('Failed to prepare stock in order insert.');
                }
                mysqli_stmt_bind_param($insertOrderStmt, 'isss', $warehouseId, $orderNumber, $attachmentValue, $actor);
                if (!mysqli_stmt_execute($insertOrderStmt)) {
                    mysqli_stmt_close($insertOrderStmt);
                    throw new Exception('Failed to save stock in order.');
                }
                mysqli_stmt_close($insertOrderStmt);

                $stockInOrderId = (int) mysqli_insert_id($db);
                $createdNewOrder = true;
            }

            if ($stockInOrderId <= 0) {
                throw new Exception('Failed to resolve stock in order id.');
            }

            $insertItemSql = "INSERT INTO `" . $itemTable . "`
                (stock_in_order_id, product_id, package_id, product_quantity, create_by, create_date, create_time, status)
                VALUES
                (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'A')";
            $insertItemStmt = mysqli_prepare($db, $insertItemSql);
            if (!$insertItemStmt) {
                throw new Exception('Failed to prepare stock in item insert.');
            }

            $insertedCount = 0;
            foreach ($items as $item) {
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                $packageId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                mysqli_stmt_bind_param($insertItemStmt, 'iiiis', $stockInOrderId, $productId, $packageId, $qty, $actor);
                if (!mysqli_stmt_execute($insertItemStmt)) {
                    mysqli_stmt_close($insertItemStmt);
                    throw new Exception('Failed to save stock in item.');
                }
                $insertedCount++;
            }
            mysqli_stmt_close($insertItemStmt);

            if ($insertedCount <= 0) {
                throw new Exception('No valid item row to save.');
            }

            mysqli_commit($db);
            return array(true, 'Stock In saved successfully.', $stockInOrderId, false);
        } catch (Exception $ex) {
            mysqli_rollback($db);

            // Fallback cleanup for non-transactional table engines.
            if ($createdNewOrder && $stockInOrderId > 0) {
                mysqli_query($db, "DELETE FROM `" . $itemTable . "` WHERE stock_in_order_id='" . (int) $stockInOrderId . "'");
                mysqli_query($db, "DELETE FROM `" . $orderTable . "` WHERE id='" . (int) $stockInOrderId . "'");
            }

            return array(false, $ex->getMessage(), 0, false);
        }
    }
}

if (!function_exists('scanAuditLog')) {
    function scanAuditLog($event, $message, $context = array())
    {
        global $connect, $cdate, $ctime;

        $safeEvent = trim((string) $event);
        $safeMessage = trim((string) $message);
        if ($safeEvent === '') {
            $safeEvent = 'scan';
        }
        if ($safeMessage === '') {
            $safeMessage = 'No message.';
        }

        $ctxText = '';
        if (is_array($context) && count($context) > 0) {
            $pairs = array();
            foreach ($context as $k => $v) {
                $pairs[] = (string) $k . '=' . (is_array($v) ? implode(',', $v) : (string) $v);
            }
            $ctxText = ' [' . implode('; ', $pairs) . ']';
        }

        $auditConn = null;
        if (isset($connect) && ($connect instanceof mysqli)) {
            $auditConn = $connect;
        } else {
            $auditConn = @mysqli_connect(dbhost, dbuser, dbpwd, dbname);
        }
        if (!($auditConn instanceof mysqli)) {
            return;
        }

        $auditMessage = $safeEvent . ': ' . $safeMessage . $ctxText;
        $userId = (USER_ID !== '' ? USER_ID : 'QR_PUBLIC');
        $logDate = !empty($cdate) ? $cdate : date('Y-m-d');
        $logTime = !empty($ctime) ? $ctime : date('H:i:s');
        $screenType = 'Stock In QR Scan';
        $logAction = 9; // check

        try {
            $sql = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, user_id, action_message, create_date, create_time, create_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($auditConn, $sql);
            if (!$stmt) {
                return;
            }
            mysqli_stmt_bind_param($stmt, 'issssss', $logAction, $screenType, $userId, $auditMessage, $logDate, $logTime, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (Throwable $th) {
            // Keep QR stock-in flow alive even if audit logging fails.
            return;
        }
    }
}

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$packages = siLoadPackages($connect);
$packageProductMap = siBuildPackageProductMap($packages);
list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

$isSubmit = (strtolower((string) $_SERVER['REQUEST_METHOD']) === 'post' && isset($_POST['actionBtn']) && (string) $_POST['actionBtn'] === 'submitStockIn');
$submittedToken = isset($_POST['scan_token']) ? trim((string) $_POST['scan_token']) : '';
$token = $submittedToken !== '' ? $submittedToken : (isset($_GET['t']) ? trim((string) $_GET['t']) : '');
$persistedAttachments = scanFilterTrustedAttachmentList(trim((string) postSpaceFilter('current_attachment')));
$statusClass = 'danger';
$statusTitle = 'Stock In Failed';
$message = '';
$requestInfo = null;
$requestItems = array();
$stockInInfo = null;
$stockInItems = array();
$countryCode = '';
$clientIp = scanGetClientIp();
$allowedCountries = scanGetAllowedCountries();
$uploadedAttachments = array();

if ($token === '') {
    $message = 'Missing stock in token.';
    scanAuditLog('token_invalid', $message);
} else if (strlen($token) > 200 || !preg_match('/^[A-Za-z0-9\-_\.=%]+$/', $token)) {
    $message = 'Invalid stock in token format.';
    scanAuditLog('token_invalid', $message, array('token_len' => strlen($token)));
} else {
    $requestId = sorDecodeToken($token);
    if ($requestId <= 0) {
        $message = 'Invalid token.';
        scanAuditLog('token_invalid', $message);
    } else {
        $reqSql = "SELECT id,
                          warehouse_id,
                          request_date,
                          tracking_no,
                          remark,
                          COALESCE(NULLIF(TRIM(invoice_no), ''), CONCAT('SOR-', id)) AS order_number
                   FROM " . STOCK_ORDER_REQ . "
                   WHERE id=? AND status='A'
                   LIMIT 1";
        $reqStmt = mysqli_prepare($finance_connect, $reqSql);
        if (!$reqStmt) {
            $message = 'Unable to validate stock order request.';
        } else {
            mysqli_stmt_bind_param($reqStmt, 'i', $requestId);
            mysqli_stmt_execute($reqStmt);
            $reqRst = mysqli_stmt_get_result($reqStmt);
            if ($reqRst) {
                $requestInfo = mysqli_fetch_assoc($reqRst);
            }
            mysqli_stmt_close($reqStmt);

            if (!is_array($requestInfo) || !isset($requestInfo['id'])) {
                $message = 'Invalid or inactive stock order request token.';
                scanAuditLog('request_invalid', $message, array('request_id' => (int) $requestId));
            } else {
                $itemSql = "SELECT product_id,
                                   package_id,
                                   IFNULL(productQty, IFNULL(packageQty, 1)) AS qty
                            FROM " . STOCK_ORDER_REQ_ITEM . "
                            WHERE request_id=? AND status='A'";
                $itemStmt = mysqli_prepare($finance_connect, $itemSql);
                if (!$itemStmt) {
                    $message = 'Unable to load order items.';
                } else {
                    mysqli_stmt_bind_param($itemStmt, 'i', $requestId);
                    mysqli_stmt_execute($itemStmt);
                    $itemRst = mysqli_stmt_get_result($itemStmt);
                    if ($itemRst) {
                        while ($it = mysqli_fetch_assoc($itemRst)) {
                            $prodId = isset($it['product_id']) ? (int) $it['product_id'] : 0;
                            $pkgId = isset($it['package_id']) ? (int) $it['package_id'] : 0;
                            $qty = isset($it['qty']) ? (int) $it['qty'] : 0;

                            if ($prodId <= 0) {
                                $prodId = siResolveProductIdFromPackage($packageProductMap, $pkgId);
                            }
                            if ($prodId <= 0 || $qty <= 0) {
                                continue;
                            }

                            $requestItems[] = array(
                                'product_id' => $prodId,
                                'package_id' => $pkgId,
                                'qty' => $qty,
                                'product_name' => isset($productNameMap[$prodId]) ? $productNameMap[$prodId] : ('Product #' . $prodId),
                            );
                        }
                    }
                    mysqli_stmt_close($itemStmt);
                }

                if ($message === '' && count($requestItems) === 0) {
                    $message = 'No valid item found in this stock order request.';
                    scanAuditLog('request_invalid', $message, array('request_id' => (int) $requestInfo['id']));
                }

                if ($message === '') {
                    // QR stock-in must be accessible from any scanner network.
                    // Keep IP/country only for audit diagnostics; do not block submission.
                    $ipAllowed = true;
                    if ($clientIp !== '') {
                        if (scanIsPrivateOrReservedIp($clientIp)) {
                            $countryCode = 'PRIVATE';
                        } else {
                            $countryCode = scanLookupCountryCode($clientIp);
                        }
                    }

                    if (!$ipAllowed) {
                        scanAuditLog('security_block', 'Location policy blocked stock-in submission.', array(
                            'request_id' => (int) $requestInfo['id'],
                            'ip' => ($clientIp === '' ? 'Unknown' : $clientIp),
                            'country' => ($countryCode === '' ? 'Unknown' : $countryCode),
                            'allowed' => implode(',', $allowedCountries),
                        ));
                        $message = 'Access denied. Please contact administrator.';
                    } else {
                        scanAuditLog('security_pass', 'Location policy passed.', array(
                            'request_id' => (int) $requestInfo['id'],
                            'ip' => ($clientIp === '' ? 'Unknown' : $clientIp),
                            'country' => ($countryCode === '' ? 'Unknown' : $countryCode),
                        ));

                        if (!$isSubmit) {
                            $statusClass = 'warning';
                            $statusTitle = 'Attachment Required';
                            $message = 'Please upload at least 1 attachment photo to submit stock in.';
                        } else {
                            $attachmentResult = scanResolveSubmittedAttachments('stock_in_attachment');
                            $persistedAttachments = isset($attachmentResult[1]) ? (array) $attachmentResult[1] : array();
                            if (!$attachmentResult[0]) {
                                $statusClass = 'danger';
                                $statusTitle = 'Attachment Required';
                                $message = (string) $attachmentResult[2];
                                scanAuditLog('submit_failed', $message, array(
                                    'request_id' => (int) $requestInfo['id'],
                                ));
                            } else {
                                $saveResult = scanSaveOrderSecure(
                                    $finance_connect,
                                    $stockInOrderTable,
                                    $stockInItemTable,
                                    (int) $requestInfo['warehouse_id'],
                                    (string) $requestInfo['order_number'],
                                    $requestItems,
                                    (string) (USER_ID !== '' ? USER_ID : 'QR_PUBLIC'),
                                    $persistedAttachments
                                );

                                $saveOk = isset($saveResult[0]) ? (bool) $saveResult[0] : false;
                                $saveMsg = isset($saveResult[1]) ? (string) $saveResult[1] : 'Unable to save stock in.';
                                $saveOrderId = isset($saveResult[2]) ? (int) $saveResult[2] : 0;
                                $alreadyExists = isset($saveResult[3]) ? (bool) $saveResult[3] : false;

                                if ($saveOk) {
                                    $statusClass = $alreadyExists ? 'warning' : 'success';
                                    $statusTitle = $alreadyExists ? 'Stock In Already Submitted' : 'Stock In Submitted Successfully';
                                    $message = $saveMsg;
                                    scanAuditLog('submit_success', $saveMsg, array(
                                        'request_id' => (int) $requestInfo['id'],
                                        'stock_in_id' => $saveOrderId,
                                        'status' => ($alreadyExists ? 'already_exists' : 'created'),
                                    ));

                                    if ($saveOrderId > 0) {
                                        $orderSql = "SELECT id, warehouse_id, order_number, stock_in_date, attachment, create_date, create_time
                                                     FROM `" . $stockInOrderTable . "`
                                                     WHERE id=? AND status='A' LIMIT 1";
                                        $orderStmt = mysqli_prepare($finance_connect, $orderSql);
                                        if ($orderStmt) {
                                            mysqli_stmt_bind_param($orderStmt, 'i', $saveOrderId);
                                            mysqli_stmt_execute($orderStmt);
                                            $orderRst = mysqli_stmt_get_result($orderStmt);
                                            if ($orderRst) {
                                                $stockInInfo = mysqli_fetch_assoc($orderRst);
                                            }
                                            mysqli_stmt_close($orderStmt);
                                        }

                                        $itemDetailSql = "SELECT product_id, product_quantity
                                                          FROM `" . $stockInItemTable . "`
                                                          WHERE stock_in_order_id=? AND status='A'
                                                          ORDER BY id ASC";
                                        $itemDetailStmt = mysqli_prepare($finance_connect, $itemDetailSql);
                                        if ($itemDetailStmt) {
                                            mysqli_stmt_bind_param($itemDetailStmt, 'i', $saveOrderId);
                                            mysqli_stmt_execute($itemDetailStmt);
                                            $itemDetailRst = mysqli_stmt_get_result($itemDetailStmt);
                                            if ($itemDetailRst) {
                                                while ($row = mysqli_fetch_assoc($itemDetailRst)) {
                                                    $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
                                                    $stockInItems[] = array(
                                                        'product_name' => isset($productNameMap[$pid]) ? $productNameMap[$pid] : ('Product #' . $pid),
                                                        'qty' => isset($row['product_quantity']) ? (int) $row['product_quantity'] : 0,
                                                    );
                                                }
                                            }
                                            mysqli_stmt_close($itemDetailStmt);
                                        }
                                    }
                                } else {
                                    $message = $saveMsg;
                                    scanAuditLog('submit_failed', $saveMsg, array(
                                        'request_id' => (int) $requestInfo['id'],
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock In Scan Result</title>
    <link href="<?= $SITEURL ?>/header/fontawesome-free-6.0.0-web/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; margin: 0; background: linear-gradient(135deg, #f4f8fb 0%, #eaf2f9 100%); color: #1f2d3d; }
        .container { max-width: 900px; margin: 32px auto; background: #fff; border-radius: 14px; box-shadow: 0 14px 40px rgba(22, 56, 89, 0.12); padding: 24px; }
        .title { margin: 0 0 8px 0; font-size: 28px; }
        .subtitle { margin: 0 0 16px 0; color: #5f7185; }
        .alert { border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; border: 1px solid transparent; }
        .alert-success { background: #edf9f0; border-color: #b8e0c1; color: #1a6b2f; }
        .alert-warning { background: #fff8e9; border-color: #f5d28b; color: #7a5600; }
        .alert-danger { background: #ffeef0; border-color: #f3bdc5; color: #8a2230; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .card { border: 1px solid #e2ebf3; border-radius: 10px; padding: 12px; background: #fbfdff; }
        .card h4 { margin: 0 0 10px 0; font-size: 16px; }
        .k { color: #5f7185; }
        .v { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d8e3ee; padding: 8px; text-align: left; }
        th { background: #f3f8fd; }
        .small { font-size: 12px; color: #617487; }
        .scan-attachment-input-row { display:flex; gap:8px; align-items:center; }
        .scan-attachment-input { display:block; flex:1; padding:8px; border:1px solid #ccd9e6; border-radius:8px; background:#fff; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title"><?= siEsc($statusTitle) ?></h1>
        <p class="subtitle">Stock Order Request QR Scan</p>

        <div class="alert alert-<?= siEsc($statusClass) ?>"><?= siEsc($message) ?></div>

        <div class="grid">
            <?php if (is_array($requestInfo)) { ?>
            <div class="card">
                <h4>Stock Order Details</h4>
                <div><span class="k">Request ID:</span> <span class="v"><?= (int) $requestInfo['id'] ?></span></div>
                <div><span class="k">Order Number:</span> <span class="v"><?= siEsc((string) $requestInfo['order_number']) ?></span></div>
                <div><span class="k">Warehouse:</span> <span class="v"><?= siEsc(isset($warehouseNameMap[(int) $requestInfo['warehouse_id']]) ? $warehouseNameMap[(int) $requestInfo['warehouse_id']] : ('Warehouse #' . (int) $requestInfo['warehouse_id'])) ?></span></div>
                <div><span class="k">Request Date:</span> <span class="v"><?= siEsc((string) $requestInfo['request_date']) ?></span></div>
                <div><span class="k">Tracking No:</span> <span class="v"><?= siEsc((string) (isset($requestInfo['tracking_no']) ? $requestInfo['tracking_no'] : '')) ?></span></div>
            </div>
            <?php } ?>
        </div>

        <?php if (count($requestItems) > 0) { ?>
            <h3>Requested Items</h3>
            <table>
                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Product</th>
                        <th width="140">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requestItems as $idx => $it) { ?>
                        <tr>
                            <td><?= (int) ($idx + 1) ?></td>
                            <td><?= siEsc((string) $it['product_name']) ?></td>
                            <td><?= (int) $it['qty'] ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

        <?php if (is_array($requestInfo) && count($requestItems) > 0 && !is_array($stockInInfo)) { ?>
            <h3 style="margin-top: 20px;">Upload Attachment</h3>
            <form method="post" enctype="multipart/form-data" style="margin-top: 10px;">
                <input type="hidden" name="scan_token" value="<?= siEsc($token) ?>">
                <input type="hidden" name="current_attachment" value="<?= siEsc((string) siAttachmentEncodeList($persistedAttachments)) ?>">
                <div style="max-width: 560px;">
                    <label for="stock_in_attachment" style="display:block;margin-bottom:8px;font-weight:600;">Attachment Photo <span style="color:#d00000;">*</span></label>
                    <div id="stock_in_attachment_inputs" style="display:flex;flex-direction:column;gap:8px;">
                        <div class="scan-attachment-input-row">
                            <input id="stock_in_attachment" class="scan-attachment-input" name="stock_in_attachment[]" type="file" accept=".png,.jpg,.jpeg,.webp"<?= count($persistedAttachments) === 0 ? ' required' : '' ?>>
                            <button type="button" class="mt-1" id="action_menu_btn" data-attach-action="add" title="Add another attachment"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                        </div>
                    </div>
                    <?php if (count($persistedAttachments) > 0) { ?>
                        <div style="margin-top:10px;padding:10px;border:1px solid #d8e3ee;border-radius:8px;background:#fff;max-width:420px;">
                            <div class="small" style="margin-bottom:8px;">Uploaded attachment kept after the failed submit:</div>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                <?php foreach ($persistedAttachments as $attachIdx => $attachPath) {
                                    $attachUrl = rtrim((string) $SITEURL, '/') . '/' . ltrim((string) $attachPath, '/');
                                    $attachExt = strtolower((string) pathinfo((string) $attachPath, PATHINFO_EXTENSION));
                                ?>
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <?php if (in_array($attachExt, array('png', 'jpg', 'jpeg', 'webp', 'gif'), true)) { ?>
                                            <a href="<?= siEsc($attachUrl) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= siEsc($attachUrl) ?>" alt="Attachment <?= (int) ($attachIdx + 1) ?>" style="max-width:120px;max-height:120px;object-fit:cover;border-radius:6px;border:1px solid #d8e3ee;"></a>
                                        <?php } ?>
                                        <a href="<?= siEsc($attachUrl) ?>" target="_blank" rel="noopener noreferrer" class="small">View Attachment <?= (int) ($attachIdx + 1) ?></a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <div id="stock_in_attachment_preview" style="margin-top:10px;padding:10px;border:1px dashed #c7d7e8;border-radius:8px;background:#f8fbff;max-width:420px;">
                        <div id="stock_in_attachment_img_list" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
                        <span id="stock_in_attachment_placeholder" class="small">Image preview</span>
                    </div>
                    <div class="small" style="margin-top:6px;">Required: upload at least one photo to complete stock in. Click + to add more attachments.</div>
                    <button type="submit" name="actionBtn" value="submitStockIn" style="margin-top:12px;padding:10px 16px;border:0;border-radius:8px;background:#1f6fd5;color:#fff;font-weight:600;">Submit Stock In</button>
                </div>
            </form>
        <?php } ?>

        <?php if (is_array($stockInInfo)) { ?>
            <h3 style="margin-top: 20px;">Stock In Submission</h3>
            <div class="grid">
                <div class="card">
                    <div><span class="k">Stock In ID:</span> <span class="v"><?= (int) $stockInInfo['id'] ?></span></div>
                    <div><span class="k">Order Number:</span> <span class="v"><?= siEsc((string) $stockInInfo['order_number']) ?></span></div>
                    <div><span class="k">Stock In Date:</span> <span class="v"><?= siEsc((string) $stockInInfo['stock_in_date']) ?></span></div>
                    <?php $scanAttachments = siAttachmentDecodeList(isset($stockInInfo['attachment']) ? (string) $stockInInfo['attachment'] : ''); ?>
                    <?php if (count($scanAttachments) > 0) { ?>
                        <div><span class="k">Attachments:</span>
                            <span class="v">
                                <?php foreach ($scanAttachments as $idx => $scanAttachment) { ?>
                                    <div><a href="<?= siEsc(rtrim((string) $SITEURL, '/') . '/' . ltrim((string) $scanAttachment, '/')) ?>" target="_blank">View Attachment <?= (int) ($idx + 1) ?></a></div>
                                <?php } ?>
                            </span>
                        </div>
                    <?php } ?>
                    <div><span class="k">Created Date:</span> <span class="v"><?= siEsc((string) $stockInInfo['create_date']) ?> <?= siEsc((string) $stockInInfo['create_time']) ?></span></div>
                </div>
            </div>

            <?php if (count($stockInItems) > 0) { ?>
                <table>
                    <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>Product</th>
                            <th width="140">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockInItems as $idx => $it) { ?>
                            <tr>
                                <td><?= (int) ($idx + 1) ?></td>
                                <td><?= siEsc((string) $it['product_name']) ?></td>
                                <td><?= (int) $it['qty'] ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        <?php } ?>

        <p class="small" style="margin-top:16px;">If you are blocked unexpectedly, please contact administrator.</p>
    </div>

    <script>
    (function () {
        var inputWrap = document.getElementById('stock_in_attachment_inputs');
        var listWrap = document.getElementById('stock_in_attachment_img_list');
        var placeholder = document.getElementById('stock_in_attachment_placeholder');
        if (!inputWrap || !listWrap || !placeholder) {
            return;
        }

        function refreshPreview() {
            listWrap.innerHTML = '';

            var hasImage = false;
            var hasFiles = false; // Add this line
            var inputs = inputWrap.querySelectorAll('.scan-attachment-input');
            inputs.forEach(function (input) {
                if (!input.files || input.files.length === 0) {
                    return;
                }
                
                hasFiles = true; // Add this line

                Array.prototype.forEach.call(input.files, function (file) {
                    if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                        return;
                    }

                    hasImage = true;
                    var objectUrl = URL.createObjectURL(file);
                    var img = document.createElement('img');
                    img.src = objectUrl;
                    img.alt = 'Attachment Preview';
                    img.style.maxWidth = '120px';
                    img.style.maxHeight = '120px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '6px';
                    img.onload = function () {
                        URL.revokeObjectURL(objectUrl);
                    };
                    listWrap.appendChild(img);
                });
            });

            placeholder.style.display = hasImage ? 'none' : 'inline';
            if (hasFiles && !hasImage) {
                placeholder.textContent = 'Selected file is not an image preview.';
            } else {
                placeholder.textContent = 'Image preview';
            }
        }

        inputWrap.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('scan-attachment-input')) {
                refreshPreview();
            }
        });

        inputWrap.addEventListener('click', function (e) {
            var target = e.target;
            if (!target) {
                return;
            }

            var addBtn = target.closest('[data-attach-action="add"]');
            var removeBtn = target.closest('[data-attach-action="remove"]');

            if (addBtn) {
                var row = document.createElement('div');
                row.className = 'scan-attachment-input-row';
                row.innerHTML = '<input class="scan-attachment-input" name="stock_in_attachment[]" type="file" accept=".png,.jpg,.jpeg,.webp">' +
                    '<button type="button" class="mt-1" id="action_menu_btn" data-attach-action="remove" title="Remove attachment row"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>';
                inputWrap.appendChild(row);
                return;
            }

            if (removeBtn) {
                var rows = inputWrap.querySelectorAll('.scan-attachment-input-row');
                if (rows.length <= 1) {
                    var onlyInput = rows[0] ? rows[0].querySelector('.scan-attachment-input') : null;
                    if (onlyInput) {
                        onlyInput.value = '';
                    }
                } else {
                    var rowToRemove = removeBtn.closest('.scan-attachment-input-row');
                    if (rowToRemove) {
                        rowToRemove.remove();
                    }
                }
                refreshPreview();
            }
        });
    })();
    </script>
</body>
</html>
