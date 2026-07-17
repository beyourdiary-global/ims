<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$currentPagePin = 0;
$websiteOrderPinGroupId = 92;
$parentPageTitle = 'Website Order Request';

include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/../checkCurrentPagePin.php';
include_once ROOT . '/include/import_pdf_common.php';
include_once ROOT . '/include/website_order_import_common.php';

$websiteOrderPinAccess = checkPinByGroupId($connect, $websiteOrderPinGroupId);
$parentPageTitleFromDb = getPinGroupNameById($connect, $websiteOrderPinGroupId);
if ($parentPageTitleFromDb !== '') {
    $parentPageTitle = $parentPageTitleFromDb;
}
$pageTitle = $parentPageTitle . ' Import';
$redirectPage = $SITEURL . '/finance/website_order_request_table.php';
$importPageUrl = $SITEURL . '/import/website_order_import.php';

if (!is_array($websiteOrderPinAccess) || !isActionAllowed('Import', $websiteOrderPinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8') . ' viewed the page <b>' . htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') . '</b>.',
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

function websiteOrderImportResolveOptionId($rawValue, $options)
{
    $lookup = normalizeImportLookup($rawValue);
    if ($lookup === '') {
        return 0;
    }

    foreach ((array) $options as $optionId => $optionLabel) {
        if (normalizeImportLookup($optionLabel) === $lookup) {
            return (int) $optionId;
        }
    }

    return 0;
}

function websiteOrderImportResolveCurrencyId($options)
{
    foreach ((array) $options as $optionId => $optionLabel) {
        $lookup = normalizeImportLookup($optionLabel);
        if ($lookup === 'myr' || $lookup === 'rm' || strpos($lookup, 'myr') === 0 || strpos($lookup, 'rm') === 0) {
            return (int) $optionId;
        }
    }

    return 0;
}

function websiteOrderImportGetOptionLabel($optionId, $options)
{
    $optionId = (int) $optionId;
    return $optionId > 0 && isset($options[$optionId]) ? (string) $options[$optionId] : '';
}

function websiteOrderImportLoadSeriesByBrand($connect)
{
    $seriesByBrand = array();
    $result = mysqli_query($connect, "SELECT id, name, brand FROM `" . BRD_SERIES . "` WHERE status = 'A' ORDER BY id ASC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $brandId = isset($row['brand']) ? (int) $row['brand'] : 0;
            $seriesId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($brandId <= 0 || $seriesId <= 0) {
                continue;
            }
            if (!isset($seriesByBrand[$brandId])) {
                $seriesByBrand[$brandId] = array();
            }
            $seriesByBrand[$brandId][] = array(
                'id' => $seriesId,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
            );
        }
    }

    return $seriesByBrand;
}

function websiteOrderImportGetSeriesIdForBrand($brandId, $seriesByBrand)
{
    $brandId = (int) $brandId;
    if ($brandId > 0 && isset($seriesByBrand[$brandId]) && !empty($seriesByBrand[$brandId][0]['id'])) {
        return (int) $seriesByBrand[$brandId][0]['id'];
    }

    return 0;
}

function websiteOrderImportLoadPackageOptions($connect)
{
    $rows = array();
    $result = mysqli_query($connect, "SELECT id, name, item_code, item_description, brand, price, currency_unit FROM `" . PKG . "` WHERE status = 'A' ORDER BY name ASC");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[(int) $row['id']] = $row;
        }
    }
    return $rows;
}

function websiteOrderImportCustomerColumnsUseUtf8mb4($connect)
{
    static $useUtf8mb4 = null;
    if ($useUtf8mb4 !== null) {
        return $useUtf8mb4;
    }

    $dbName = defined('dbname') ? dbname : '';
    $safeDbName = mysqli_real_escape_string($connect, $dbName);
    $safeTableName = mysqli_real_escape_string($connect, WEB_CUST_RCD);
    $result = mysqli_query($connect, "SELECT COUNT(*) AS utf8_column_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$safeDbName' AND TABLE_NAME = '$safeTableName' AND COLUMN_NAME IN ('name', 'ship_rec_name') AND CHARACTER_SET_NAME = 'utf8mb4'");
    $row = $result ? mysqli_fetch_assoc($result) : array();
    $useUtf8mb4 = ((int) ($row['utf8_column_count'] ?? 0)) >= 2;
    return $useUtf8mb4;
}

function websiteOrderImportFindCustomer($connect, $customerName)
{
    $customerName = trim((string) $customerName);
    if ($customerName === '') {
        return array();
    }

    $safeName = mysqli_real_escape_string($connect, $customerName);
    $customerColumnsUseUtf8mb4 = websiteOrderImportCustomerColumnsUseUtf8mb4($connect);
    if ($customerColumnsUseUtf8mb4) {
        $customerNameCondition = "(`name` = '$safeName' OR `ship_rec_name` = '$safeName')";
    } else {
        // Keep the legacy fallback indexed and collation-safe. Chinese names
        // cannot be matched in a latin1 column, but the lookup will not fail;
        // insert_table.php upgrades the table before Chinese records are saved.
        if (preg_match('/[^\x00-\x7F]/', $customerName)) {
            return array();
        }
        $customerNameCondition = "(`name` = CONVERT('$safeName' USING latin1) OR `ship_rec_name` = CONVERT('$safeName' USING latin1))";
    }
    $result = mysqli_query($connect, "SELECT * FROM `" . WEB_CUST_RCD . "` WHERE status = 'A' AND $customerNameCondition ORDER BY id ASC LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return is_array($row) ? $row : array();
    }

    return array();
}

function websiteOrderImportBuildCustomerCode($connect, $orderId)
{
    $baseCode = 'WEB-' . preg_replace('/[^A-Za-z0-9\-]/', '', strtoupper((string) $orderId));
    if ($baseCode === 'WEB-') {
        $baseCode = 'WEB-' . date('YmdHis');
    }

    $candidate = $baseCode;
    $suffix = 1;
    while (true) {
        $safeCandidate = mysqli_real_escape_string($connect, $candidate);
        $result = mysqli_query($connect, "SELECT id FROM `" . WEB_CUST_RCD . "` WHERE cust_id = '$safeCandidate' LIMIT 1");
        if (!$result || mysqli_num_rows($result) === 0) {
            return $candidate;
        }
        $candidate = $baseCode . '-' . $suffix++;
    }
}

$packageOptions = websiteOrderImportLoadPackageOptions($connect);
$countryOptions = getImportOptionList(COUNTRIES, 'nicename', $connect);
$currencyOptions = getImportOptionList(CUR_UNIT, 'unit', $connect);
$paymentOptions = getImportOptionList(FIN_PAY_METH, 'name', $finance_connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
$brandOptions = getImportOptionList(BRAND, 'name', $connect);
$seriesOptions = getImportOptionList(BRD_SERIES, 'name', $connect);
$seriesByBrand = websiteOrderImportLoadSeriesByBrand($connect);

$action = (string) post('actionBtn');
$allowedActions = array('parseWebsiteOrderReq', 'insertWebsiteOrderReq');
if ($action !== '' && !in_array($action, $allowedActions, true)) {
    $action = '';
}
if (post('cancelImportBtn') !== '' || $action === 'cancelImport') {
    echo '<script>location.href = ' . json_encode($importPageUrl) . ';</script>';
    exit;
}

$importErrors = array();
$importWarnings = array();
$previewData = array();

if ($action === 'parseWebsiteOrderReq') {
    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Website Order PDF file.';
    } else if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . (int) $_FILES['import_file']['error'];
    } else if ((int) $_FILES['import_file']['size'] > 5 * 1024 * 1024) {
        $importErrors[] = 'The uploaded file exceeds the maximum allowed size of 5MB.';
    } else {
        $extension = strtolower(pathinfo((string) $_FILES['import_file']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $importErrors[] = 'Only PDF files are supported for Website Order import.';
        } else {
            $rawContent = @file_get_contents($_FILES['import_file']['tmp_name']);
            if (!is_string($rawContent) || strncmp($rawContent, '%PDF-', 5) !== 0) {
                $importErrors[] = 'The uploaded file is not a valid PDF document.';
            } else {
                $pdfText = extractTextFromPdfContent($rawContent);
                if (trim((string) $pdfText) === '') {
                    $pdfText = extractTextFromPdfViaCommand($_FILES['import_file']['tmp_name']);
                }
                if (trim((string) $pdfText) === '') {
                    $importErrors[] = 'Unable to extract text from the uploaded PDF file.';
                } else {
                    $parsed = websiteOrderImportExtractPdfFields($pdfText, array_values($countryOptions), $rawContent);
                    $matchedPackage = websiteOrderImportResolvePackage($parsed['package_name'], $packageOptions, $parsed['country_name']);
                    $matchedCustomer = websiteOrderImportFindCustomer($connect, $parsed['shipping_name'] !== '' ? $parsed['shipping_name'] : $parsed['customer_name']);
                    $currencyId = websiteOrderImportResolveCurrencyId($currencyOptions);
                    $countryId = websiteOrderImportResolveOptionId($parsed['country_name'], $countryOptions);
                    $paymentId = websiteOrderImportResolveOptionId($parsed['payment_method_name'], $paymentOptions);
                    $brandId = isset($matchedPackage['brand']) ? (int) $matchedPackage['brand'] : 0;
                    $seriesId = websiteOrderImportGetSeriesIdForBrand($brandId, $seriesByBrand);
                    $total = $parsed['total'] !== '' ? $parsed['total'] : websiteOrderImportNormalizeAmount((float) $parsed['price'] + (float) $parsed['shipping_fee'] - (float) $parsed['discount_price']);

                    $previewData = array(
                        'order_id' => $parsed['order_id'],
                        'package_name' => $parsed['package_name'],
                        'package_id' => isset($matchedPackage['id']) ? (int) $matchedPackage['id'] : 0,
                        'customer_id' => isset($matchedCustomer['id']) ? (int) $matchedCustomer['id'] : 0,
                        'customer_name' => $parsed['shipping_name'] !== '' ? $parsed['shipping_name'] : $parsed['customer_name'],
                        'customer_email' => $parsed['customer_email'],
                        'customer_contact' => $parsed['customer_contact'],
                        'shipping_name' => $parsed['shipping_name'],
                        'shipping_address' => $parsed['shipping_address'],
                        'country' => $countryId,
                        'currency' => $currencyId,
                        'price' => $parsed['price'] !== '' ? $parsed['price'] : '0.00',
                        'shipping_fee' => $parsed['shipping_fee'] !== '' ? $parsed['shipping_fee'] : '0.00',
                        'discount_price' => $parsed['discount_price'] !== '' ? $parsed['discount_price'] : '0.00',
                        'total' => $total !== '' ? $total : '0.00',
                        'brand' => $brandId,
                        'series' => $seriesId,
                        'payment_method' => $paymentId,
                        'pic' => (int) USER_ID,
                        'customer_birthday' => '',
                    );

                    if ($previewData['order_id'] === '') $importWarnings[] = 'Order ID could not be detected. Please enter it manually.';
                    if ($previewData['package_id'] <= 0) $importWarnings[] = 'Package could not be matched automatically. Please select it manually.';
                    if ($previewData['country'] <= 0) $importWarnings[] = 'Country could not be matched automatically. Please select it manually.';
                    if ($previewData['customer_email'] === '') $importWarnings[] = 'Customer Email could not be detected. Please enter it manually.';
                    if ($previewData['customer_contact'] === '') $importWarnings[] = 'Customer Contact could not be detected. Please enter it manually.';
                }
            }
        }
    }
}

if ($action === 'insertWebsiteOrderReq') {
    $orderId = postSpaceFilter('order_id');
    $packageId = (int) postSpaceFilter('package_id');
    $customerName = postSpaceFilter('customer_name');
    $customerEmail = postSpaceFilter('customer_email');
    $customerContact = postSpaceFilter('customer_contact');
    $shippingName = postSpaceFilter('shipping_name');
    $shippingAddress = postSpaceFilter('shipping_address');
    $countryId = (int) postSpaceFilter('country');
    $currencyId = (int) postSpaceFilter('currency');
    $price = websiteOrderImportNormalizeAmount(postSpaceFilter('price'));
    $shippingFee = websiteOrderImportNormalizeAmount(postSpaceFilter('shipping_fee'));
    $discountPrice = websiteOrderImportNormalizeAmount(postSpaceFilter('discount_price'));
    $total = websiteOrderImportNormalizeAmount(postSpaceFilter('total'));
    $brandId = (int) postSpaceFilter('brand');
    $seriesId = (int) postSpaceFilter('series');
    $paymentId = (int) postSpaceFilter('payment_method');
    $picId = (int) postSpaceFilter('pic');
    $customerBirthday = postSpaceFilter('customer_birthday');

    $packageRow = isset($packageOptions[$packageId]) ? $packageOptions[$packageId] : array();
    if ($orderId === '') $importErrors[] = 'Order ID is required.';
    if (empty($packageRow)) $importErrors[] = 'Please select a valid active Package.';
    if ($customerName === '') $customerName = $shippingName;
    if ($customerName === '') $importErrors[] = 'Customer Name is required.';
    if ($customerEmail === '') $importErrors[] = 'Customer Email is required.';
    if ($customerContact === '') $importErrors[] = 'Customer Contact is required.';
    if ($shippingName === '') $importErrors[] = 'Shipping Name is required.';
    if ($shippingAddress === '') $importErrors[] = 'Shipping Address is required.';
    if ($countryId <= 0 || !isset($countryOptions[$countryId])) $importErrors[] = 'Please select a valid Country.';
    $currencyLookup = normalizeImportLookup(websiteOrderImportGetOptionLabel($currencyId, $currencyOptions));
    if ($currencyId <= 0 || !isset($currencyOptions[$currencyId]) || !($currencyLookup === 'myr' || $currencyLookup === 'rm' || strpos($currencyLookup, 'myr') === 0 || strpos($currencyLookup, 'rm') === 0)) $importErrors[] = 'Currency must be MYR or RM.';
    if ($price === '' || $shippingFee === '' || $discountPrice === '' || $total === '') $importErrors[] = 'Price, Shipping Fee, Discount Price, and Total are required.';
    if ($brandId <= 0 || !isset($brandOptions[$brandId])) $importErrors[] = 'Please select a valid Brand.';
    if ($seriesId <= 0 || !isset($seriesOptions[$seriesId])) $importErrors[] = 'Please select a valid Series.';
    if ($paymentId <= 0 || !isset($paymentOptions[$paymentId])) $importErrors[] = 'Please select a valid Payment Method.';
    if ($picId <= 0 || !isset($userOptions[$picId])) $importErrors[] = 'Please select a valid Person In Charge.';
    if (empty($importErrors)) {
        $brandId = isset($packageRow['brand']) ? (int) $packageRow['brand'] : $brandId;
        $seriesId = $seriesId > 0 ? $seriesId : 0;
        $picId = $picId > 0 ? $picId : (int) USER_ID;

        $safeOrderId = mysqli_real_escape_string($finance_connect, $orderId);
        $duplicateResult = mysqli_query($finance_connect, "SELECT id FROM `" . WEB_ORDER_REQ . "` WHERE order_id = '$safeOrderId' LIMIT 1");
        if ($duplicateResult && mysqli_num_rows($duplicateResult) > 0) {
            $importErrors[] = 'Duplicate Order ID found in Website Order Request records.';
        }
    }

    if (empty($importErrors)) {
        $customerRow = websiteOrderImportFindCustomer($connect, $shippingName);
        $customerRowId = isset($customerRow['id']) ? (int) $customerRow['id'] : 0;
        $customerCode = isset($customerRow['cust_id']) ? (string) $customerRow['cust_id'] : '';

        if ($customerRowId <= 0) {
            $customerCode = websiteOrderImportBuildCustomerCode($connect, $orderId);
            $safeCustomerCode = mysqli_real_escape_string($connect, $customerCode);
            $safeCustomerName = mysqli_real_escape_string($connect, $customerName);
            $safeCustomerContact = mysqli_real_escape_string($connect, $customerContact);
            $safeCustomerEmail = mysqli_real_escape_string($connect, $customerEmail);
            $safeBirthday = mysqli_real_escape_string($connect, $customerBirthday);
            $safeShippingName = mysqli_real_escape_string($connect, $shippingName);
            $safeShippingAddress = mysqli_real_escape_string($connect, $shippingAddress);
            $customerInsertSql = "INSERT INTO `" . WEB_CUST_RCD . "` (cust_id, name, contact, cust_email, cust_birthday, sales_pic, country, brand, series, ship_rec_name, ship_rec_add, ship_rec_contact, remark, create_by, create_date, create_time) VALUES ('$safeCustomerCode', '$safeCustomerName', '$safeCustomerContact', '$safeCustomerEmail', '$safeBirthday', " . ($picId > 0 ? $picId : 'NULL') . ", " . ($countryId > 0 ? $countryId : 'NULL') . ", " . ($brandId > 0 ? $brandId : 'NULL') . ", " . ($seriesId > 0 ? $seriesId : 'NULL') . ", '$safeShippingName', '$safeShippingAddress', '$safeCustomerContact', 'Imported from Website Order PDF', '" . (int) USER_ID . "', curdate(), curtime())";
            if (!mysqli_query($connect, $customerInsertSql)) {
                $importErrors[] = 'Failed to create the new customer record: ' . mysqli_error($connect);
            } else {
                $customerRowId = (int) mysqli_insert_id($connect);
                generateDBData(WEB_CUST_RCD, $connect);
            }
        }

        if (empty($importErrors) && $customerRowId > 0) {
            $safePackageId = (int) $packageId;
            $safePrice = mysqli_real_escape_string($finance_connect, $price);
            $safeShippingFee = mysqli_real_escape_string($finance_connect, $shippingFee);
            $safeDiscount = mysqli_real_escape_string($finance_connect, $discountPrice);
            $safeTotal = mysqli_real_escape_string($finance_connect, $total);
            $safeCustomerEmail = mysqli_real_escape_string($finance_connect, $customerEmail);
            $safeCustomerName = mysqli_real_escape_string($finance_connect, $customerName);
            $safeShippingName = mysqli_real_escape_string($finance_connect, $shippingName);
            $safeShippingAddress = mysqli_real_escape_string($finance_connect, $shippingAddress);
            $safeCustomerContact = mysqli_real_escape_string($finance_connect, $customerContact);
            $safeBirthday = mysqli_real_escape_string($finance_connect, $customerBirthday);
            $safeRemark = mysqli_real_escape_string($finance_connect, 'Imported from Website Order PDF (' . $orderId . ')');
            $seriesSql = $seriesId > 0 ? (string) $seriesId : 'NULL';
            $paymentSql = $paymentId > 0 ? (string) $paymentId : 'NULL';
            $picSql = $picId > 0 ? (string) $picId : 'NULL';
            $customerSql = (int) $customerRowId;
            $query = "INSERT INTO `" . WEB_ORDER_REQ . "` (order_id, brand, series, pkg, country, currency, price, shipping, discount, total, pay_method, pic, cust_id, cust_name, cust_email, cust_birthday, shipping_name, shipping_address, shipping_contact, remark, order_status, stock_out_warehouse_id, airbill_no, airbill_attachment, create_by, create_date, create_time) VALUES ('$safeOrderId', " . ($brandId > 0 ? $brandId : 'NULL') . ", $seriesSql, $safePackageId, $countryId, $currencyId, '$safePrice', '$safeShippingFee', '$safeDiscount', '$safeTotal', $paymentSql, $picSql, $customerSql, '$safeCustomerName', '$safeCustomerEmail', '$safeBirthday', '$safeShippingName', '$safeShippingAddress', '$safeCustomerContact', '$safeRemark', 'P', NULL, '', '', '" . (int) USER_ID . "', curdate(), curtime())";
            if (!mysqli_query($finance_connect, $query)) {
                $importErrors[] = 'Failed to insert the Website Order Request: ' . mysqli_error($finance_connect);
            } else {
                $dataId = (int) mysqli_insert_id($finance_connect);
                if (function_exists('shopeeOmsLogTransition')) {
                    shopeeOmsLogTransition($finance_connect, array(
                        'order_id' => $dataId,
                        'order_code' => $orderId,
                        'from_status' => '',
                        'to_status' => 'P',
                        'transition_action' => 'pdf_import',
                        'user_id' => USER_ID,
                        'user_group_id' => USER_GROUP,
                        'remark' => 'Imported from Website Order PDF.',
                        'source_page' => $pageTitle,
                    ));
                }
                audit_log(array(
                    'log_act' => 'Import',
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => WEB_ORDER_REQ,
                    'newval' => 'Order ID=' . $orderId,
                    'act_msg' => USER_NAME . ' imported the data [ <b>ID = ' . $dataId . '</b> ] from <b><i>' . WEB_ORDER_REQ . ' Table</i></b>.',
                    'page' => $pageTitle,
                    'connect' => $connect,
                ));
                echo '<script>alert(' . json_encode('Website Order Request imported successfully.') . ');window.location.replace(' . json_encode($redirectPage) . ');</script>';
                exit;
            }
        }
    }

    $previewData = array(
        'order_id' => $orderId,
        'package_id' => $packageId,
        'customer_id' => 0,
        'customer_name' => $customerName,
        'customer_email' => $customerEmail,
        'customer_contact' => $customerContact,
        'shipping_name' => $shippingName,
        'shipping_address' => $shippingAddress,
        'country' => $countryId,
        'currency' => $currencyId,
        'price' => $price,
        'shipping_fee' => $shippingFee,
        'discount_price' => $discountPrice,
        'total' => $total,
        'brand' => $brandId,
        'series' => $seriesId,
        'payment_method' => $paymentId,
        'pic' => $picId,
        'customer_birthday' => $customerBirthday,
    );
}

$selectedPackageId = isset($previewData['package_id']) ? (int) $previewData['package_id'] : 0;
$selectedCustomerId = isset($previewData['customer_id']) ? (int) $previewData['customer_id'] : 0;
$selectedCountry = isset($previewData['country']) ? (int) $previewData['country'] : 0;
$selectedCurrency = isset($previewData['currency']) ? (int) $previewData['currency'] : websiteOrderImportResolveCurrencyId($currencyOptions);
$selectedPayment = isset($previewData['payment_method']) ? (int) $previewData['payment_method'] : 0;
$selectedPic = isset($previewData['pic']) ? (int) $previewData['pic'] : (int) USER_ID;
$selectedBrand = isset($previewData['brand']) ? (int) $previewData['brand'] : 0;
$selectedSeries = isset($previewData['series']) ? (int) $previewData['series'] : 0;
$selectedPackageLabel = '';
if ($selectedPackageId > 0 && isset($packageOptions[$selectedPackageId])) {
    $selectedPackageLabel = (string) $packageOptions[$selectedPackageId]['name'];
    if (!empty($packageOptions[$selectedPackageId]['item_code'])) {
        $selectedPackageLabel .= ' (' . (string) $packageOptions[$selectedPackageId]['item_code'] . ')';
    }
}
$selectedCountryLabel = websiteOrderImportGetOptionLabel($selectedCountry, $countryOptions);
$selectedCurrencyLabel = websiteOrderImportGetOptionLabel($selectedCurrency, $currencyOptions);
$selectedBrandLabel = websiteOrderImportGetOptionLabel($selectedBrand, $brandOptions);
$selectedSeriesLabel = websiteOrderImportGetOptionLabel($selectedSeries, $seriesOptions);
$selectedPaymentLabel = websiteOrderImportGetOptionLabel($selectedPayment, $paymentOptions);
$selectedPicLabel = websiteOrderImportGetOptionLabel($selectedPic, $userOptions);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .website-import-preview .form-label { font-weight: 600; }
        .website-import-preview .import-section-title { border-bottom: 1px solid #dee2e6; padding-bottom: .5rem; margin-bottom: 1rem; }
        .website-import-source-note { white-space: pre-line; }
        .website-import-field-error { color: #dc3545; font-size: .875rem; margin-top: .25rem; }
        .website-import-invalid { border-color: #dc3545 !important; }
    </style>
</head>
<body>
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row"><p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p></div>
                <div class="row"><div class="col-12 d-flex justify-content-between flex-wrap"><h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2></div></div>
            </div>

            <?php if (!empty($importErrors)) { ?>
                <div class="alert alert-danger" role="alert">
                    <?php foreach ($importErrors as $errorMessage) { ?><div><?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                </div>
            <?php } ?>
            <?php if (!empty($importWarnings)) { ?>
                <div class="alert alert-warning" role="alert">
                    <?php foreach ($importWarnings as $warningMessage) { ?><div><?= htmlspecialchars((string) $warningMessage, ENT_QUOTES, 'UTF-8') ?></div><?php } ?>
                </div>
            <?php } ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Step 1: Upload Website Order PDF</h5>
                    <form method="post" enctype="multipart/form-data" autocomplete="off" class="website-import-form" novalidate>
                        <div class="row align-items-end">
                            <div class="col-12 col-md-8 mb-3">
                                <label class="form-label" for="import_file">Website Order PDF File<span class="requireRed">*</span></label>
                                <input class="form-control" type="file" name="import_file" id="import_file" accept=".pdf,application/pdf" required>
                                <small class="text-muted">Upload the order details PDF exported from the website order page.</small>
                            </div>
                            <div class="col-12 col-md-4 mb-3 d-flex gap-2 flex-wrap">
                                <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="parseWebsiteOrderReq"><i class="fa-solid fa-wand-magic-sparkles"></i> Extract Details</button>
                                <button class="btn btn-lg btn-rounded btn-secondary px-4" type="submit" name="cancelImportBtn" value="1">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($previewData)) { ?>
                <div class="card website-import-preview mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 2: Verify Extracted Website Order Details</h5>
                        <form method="post" autocomplete="off" class="website-import-form" novalidate>
                            <input type="hidden" name="actionBtn" value="insertWebsiteOrderReq">
                            <div class="import-section-title"><h6>Order Details</h6></div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label class="form-label" for="order_id">Order ID<span class="requireRed">*</span></label><input class="form-control" id="order_id" name="order_id" value="<?= htmlspecialchars((string) ($previewData['order_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div class="col-md-4 mb-3"><label class="form-label" for="package_id">Package Name<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="package_id" value="<?= htmlspecialchars($selectedPackageLabel, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="package_id" id="package_id_hidden" value="<?= $selectedPackageId > 0 ? (int) $selectedPackageId : '' ?>"></div></div>
                                <div class="col-md-4 mb-3"><label class="form-label" for="country">Country<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="country" value="<?= htmlspecialchars($selectedCountryLabel, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="country" id="country_hidden" value="<?= $selectedCountry > 0 ? (int) $selectedCountry : '' ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3"><label class="form-label" for="currency">Currency<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="currency" value="<?= htmlspecialchars($selectedCurrencyLabel !== '' ? $selectedCurrencyLabel : 'MYR', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="currency" id="currency_hidden" value="<?= $selectedCurrency > 0 ? (int) $selectedCurrency : '' ?>"></div></div>
                                <div class="col-md-3 mb-3"><label class="form-label" for="price">Price<span class="requireRed">*</span></label><input class="form-control" type="number" step="0.01" id="price" name="price" value="<?= htmlspecialchars((string) ($previewData['price'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div class="col-md-3 mb-3"><label class="form-label" for="shipping_fee">Shipping Fee<span class="requireRed">*</span></label><input class="form-control" type="number" step="0.01" id="shipping_fee" name="shipping_fee" value="<?= htmlspecialchars((string) ($previewData['shipping_fee'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div class="col-md-3 mb-3"><label class="form-label" for="discount_price">Discount Price<span class="requireRed">*</span></label><input class="form-control" type="number" step="0.01" id="discount_price" name="discount_price" value="<?= htmlspecialchars((string) ($previewData['discount_price'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?>" required></div>
                            </div>
                            <div class="row"><div class="col-md-3 mb-3"><label class="form-label" for="total">Total<span class="requireRed">*</span></label><input class="form-control" type="number" step="0.01" id="total" name="total" value="<?= htmlspecialchars((string) ($previewData['total'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?>" required></div></div>

                            <div class="import-section-title mt-3"><h6>Customer and Shipping Details</h6></div>
                            <div class="row">
                                <div class="col-md-4 mb-3"><label class="form-label" for="customer_name">Customer Name<span class="requireRed">*</span></label><input class="form-control" id="customer_name" name="customer_name" value="<?= htmlspecialchars((string) ($previewData['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div class="col-md-4 mb-3"><label class="form-label" for="customer_email">Customer Email<span class="requireRed">*</span></label><input class="form-control" type="email" id="customer_email" name="customer_email" value="<?= htmlspecialchars((string) ($previewData['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                                <div class="col-md-4 mb-3"><label class="form-label" for="customer_contact">Customer Contact<span class="requireRed">*</span></label><input class="form-control" id="customer_contact" name="customer_contact" value="<?= htmlspecialchars((string) ($previewData['customer_contact'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                            </div>
                            <div class="row"><div class="col-md-4 mb-3"><label class="form-label" for="shipping_name">Shipping Name<span class="requireRed">*</span></label><input class="form-control" id="shipping_name" name="shipping_name" value="<?= htmlspecialchars((string) ($previewData['shipping_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required><?php if ($selectedCustomerId > 0) { ?><div class="text-success small mt-1">Existing customer matched by Shipping Name: <?= htmlspecialchars((string) ($previewData['shipping_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.</div><?php } else if ($action === 'parseWebsiteOrderReq') { ?><div class="text-muted small mt-1">No existing customer matched. A new customer will be created when inserted.</div><?php } ?></div><div class="col-md-8 mb-3"><label class="form-label" for="shipping_address">Shipping Address<span class="requireRed">*</span></label><textarea class="form-control" id="shipping_address" name="shipping_address" rows="2" required><?= htmlspecialchars((string) ($previewData['shipping_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></div></div>
                            <div class="row"><div class="col-md-4 mb-3"><label class="form-label" for="customer_birthday">Customer Birthday</label><input class="form-control" type="date" id="customer_birthday" name="customer_birthday" value="<?= htmlspecialchars((string) ($previewData['customer_birthday'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div></div>

                            <div class="import-section-title mt-3"><h6>Existing Website Order Fields</h6></div>
                            <div class="row">
                                <div class="col-md-3 mb-3"><label class="form-label" for="brand">Brand<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="brand" value="<?= htmlspecialchars($selectedBrandLabel, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="brand" id="brand_hidden" value="<?= $selectedBrand > 0 ? (int) $selectedBrand : '' ?>"></div></div>
                                <div class="col-md-3 mb-3"><label class="form-label" for="series">Series<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="series" value="<?= htmlspecialchars($selectedSeriesLabel, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="series" id="series_hidden" value="<?= $selectedSeries > 0 ? (int) $selectedSeries : '' ?>"></div></div>
                                <div class="col-md-3 mb-3"><label class="form-label" for="payment_method">Payment Method<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="payment_method" value="<?= htmlspecialchars($selectedPaymentLabel, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="payment_method" id="payment_method_hidden" value="<?= $selectedPayment > 0 ? (int) $selectedPayment : '' ?>"></div></div>
                                <div class="col-md-3 mb-3"><label class="form-label" for="pic">Person In Charge<span class="requireRed">*</span></label><div class="autocomplete"><input class="form-control" type="text" id="pic" value="<?= htmlspecialchars($selectedPicLabel, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required><input type="hidden" name="pic" id="pic_hidden" value="<?= $selectedPic > 0 ? (int) $selectedPic : '' ?>"></div></div>
                            </div>
                            <div class="d-flex justify-content-center flex-wrap gap-2 mt-3"><button class="btn btn-lg btn-rounded btn-primary px-4" type="submit"><i class="fa-solid fa-database"></i> Insert Website Order Request</button><button class="btn btn-lg btn-rounded btn-secondary px-4" type="submit" name="cancelImportBtn" value="1">Cancel</button></div>
                        </form>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php if (!empty($previewData)) { ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var autocompleteFields = [
                    { elementID: 'package_id', hiddenElementID: 'package_id_hidden', searchType: 'name', dbTable: <?= json_encode(PKG) ?> },
                    { elementID: 'country', hiddenElementID: 'country_hidden', searchType: 'nicename', dbTable: <?= json_encode(COUNTRIES) ?> },
                    { elementID: 'currency', hiddenElementID: 'currency_hidden', searchType: 'unit', dbTable: <?= json_encode(CUR_UNIT) ?> },
                    { elementID: 'brand', hiddenElementID: 'brand_hidden', searchType: 'name', dbTable: <?= json_encode(BRAND) ?>, onSelect: function (row) { applySeriesForBrand(row.id || row.val || row.value); } },
                    { elementID: 'series', hiddenElementID: 'series_hidden', searchType: 'name', dbTable: <?= json_encode(BRD_SERIES) ?> },
                    { elementID: 'payment_method', hiddenElementID: 'payment_method_hidden', searchType: 'name', dbTable: <?= json_encode(FIN_PAY_METH) ?> },
                    { elementID: 'pic', hiddenElementID: 'pic_hidden', searchType: 'name', dbTable: <?= json_encode(USR_USER) ?> }
                ];
                var seriesByBrand = <?= json_encode($seriesByBrand) ?>;

                function applySeriesForBrand(brandId) {
                    var seriesInput = document.getElementById('series');
                    var seriesHiddenInput = document.getElementById('series_hidden');
                    var brandSeriesRows = seriesByBrand[String(brandId)] || [];
                    if (!seriesInput || !seriesHiddenInput) {
                        return;
                    }

                    if (brandSeriesRows.length > 0) {
                        seriesInput.value = brandSeriesRows[0].name || '';
                        seriesHiddenInput.value = brandSeriesRows[0].id || '';
                    } else {
                        seriesInput.value = '';
                        seriesHiddenInput.value = '';
                    }
                    seriesInput.dispatchEvent(new Event('change', { bubbles: true }));
                }

                autocompleteFields.forEach(function (config) {
                    var input = document.getElementById(config.elementID);
                    var hiddenInput = document.getElementById(config.hiddenElementID);
                    if (!input || !hiddenInput) {
                        return;
                    }

                    input.addEventListener('input', function () {
                        hiddenInput.value = '';
                        if (config.elementID === 'brand') {
                            applySeriesForBrand('');
                        }
                        if (typeof searchInput === 'function') {
                            searchInput({
                                search: input.value,
                                searchType: config.searchType,
                                elementID: config.elementID,
                                hiddenElementID: config.hiddenElementID,
                                dbTable: config.dbTable,
                                onSelect: config.onSelect
                            }, <?= json_encode($SITEURL) ?>);
                        }
                    });
                });

                function clearFieldError(field) {
                    field.classList.remove('website-import-invalid');
                    var wrapper = field.closest('.autocomplete') || field.parentElement;
                    if (wrapper) {
                        var error = wrapper.querySelector('.website-import-field-error');
                        if (error) {
                            error.remove();
                        }
                    }
                }

                function showFieldError(field, message) {
                    clearFieldError(field);
                    field.classList.add('website-import-invalid');
                    var wrapper = field.closest('.autocomplete') || field.parentElement;
                    if (!wrapper) {
                        return;
                    }
                    var error = document.createElement('div');
                    error.className = 'website-import-field-error';
                    error.textContent = message;
                    wrapper.appendChild(error);
                }

                document.querySelectorAll('.website-import-form').forEach(function (form) {
                    var requiredFields = form.querySelectorAll('input[required], textarea[required]');
                    requiredFields.forEach(function (field) {
                        field.addEventListener('input', function () { clearFieldError(field); });
                        field.addEventListener('change', function () { clearFieldError(field); });
                    });

                    form.addEventListener('submit', function (event) {
                        if (event.submitter && event.submitter.name === 'cancelImportBtn') {
                            return;
                        }

                        var firstInvalidField = null;
                        requiredFields.forEach(function (field) {
                            clearFieldError(field);
                            var fieldLabel = form.querySelector('label[for="' + field.id + '"]');
                            var labelText = fieldLabel ? fieldLabel.textContent.replace(/\s*\*\s*$/, '').trim() : 'This field';
                            var message = '';
                            var hiddenField = document.getElementById(field.id + '_hidden');

                            if (!field.value.trim()) {
                                message = labelText + ' is required.';
                            } else if (hiddenField && !hiddenField.value.trim()) {
                                message = 'Please select a valid ' + labelText + '.';
                            } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim())) {
                                message = 'Please enter a valid ' + labelText + '.';
                            } else if (field.type === 'number' && !field.checkValidity()) {
                                message = 'Please enter a valid ' + labelText + '.';
                            }

                            if (message !== '') {
                                showFieldError(field, message);
                                if (!firstInvalidField) {
                                    firstInvalidField = field;
                                }
                            }
                        });

                        if (firstInvalidField) {
                            event.preventDefault();
                            firstInvalidField.focus();
                        }
                    });
                });
            });
        </script>
    <?php } ?>
</body>
</html>
