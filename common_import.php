<?php
$pageTitle = "Import Shortcut";

include_once 'menuHeader.php';

$module = input('module');
$redirect_page = $SITEURL . '/common_import.php';
$shopeeRedirectPage = $SITEURL . '/finance/shopee_ads_topup_trans_table.php';
$facebookRedirectPage = $SITEURL . '/finance/fb_ads_topup_trans_table.php';
$shopeeOrderRedirectPage = $SITEURL . '/finance/shopee_order_req_table.php'; // NEW

$action = post('actionBtn');
$importErrors = [];
$importWarnings = [];
$previewData = [];
$facebookPreviewRecords = [];
$facebookImportSummary = [
    'processed_files' => 0,
    'preview_records' => 0,
    'skipped_files' => 0,
];


$shopeeAccounts = getImportOptionList(SHOPEE_ACC, 'name', $finance_connect);
$currencyUnits = getImportOptionList(CUR_UNIT, 'unit', $connect);
$paymentMethods = getImportOptionList(FIN_PAY_METH, 'name', $finance_connect);
$metaAccounts = getMetaAdsAccountOptions($finance_connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
$shopeePayMethods = getImportOptionList(PAY_MTHD_SHOPEE, 'name', $finance_connect);
$brandOptions = getImportOptionList(BRAND, 'name', $connect);
$pkgOptions = getImportOptionList(PKG, 'name', $connect);
$shopeeBuyers = getImportOptionList(SHOPEE_CUST_INFO, 'buyer_username', $finance_connect);
if ($action === 'parseShopeeAdsTopup') {
    $module = 'shopee_ads_topup';

    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        $importErrors[] = 'Please choose a Shopee Seller Centre HTML file.';
    } else {
        $html = file_get_contents($_FILES['import_file']['tmp_name']);

        if ($html === false || trim($html) === '') {
            $importErrors[] = 'The uploaded file could not be read.';
        } else {
            $parseResult = parseShopeeAdsTopupHtml($html, $shopeeAccounts, $currencyUnits, $paymentMethods);
            $previewData = $parseResult['data'];
            $importWarnings = $parseResult['warnings'];

            if (!empty($parseResult['errors'])) {
                $importErrors = array_merge($importErrors, $parseResult['errors']);
            }
        }
    }
} else if ($action === 'insertShopeeAdsTopup') {
    $module = 'shopee_ads_topup';
    $previewData = getShopeeAdsPreviewFromPost();
    $importWarnings = array_filter(post('importWarnings') ? explode("\n", post('importWarnings')) : []);

    validateShopeeAdsPreview($previewData, $importErrors, $shopeeAccounts, $currencyUnits, $paymentMethods, $finance_connect, $connect);

    if (empty($importErrors)) {
        $paymentDate = formatImportDatetime($previewData['payment_date']);
        $remark = mysqli_real_escape_string($finance_connect, $previewData['remark']);
        $orderId = mysqli_real_escape_string($finance_connect, $previewData['order_id']);
        $query = "INSERT INTO " . SHOPEE_ADS_TOPUP . " (shopee_acc, orderID, payment_date, currency, topup_amt, subtotal, gst, pay_meth, remark, create_by, create_date, create_time) VALUES ('" . mysqli_real_escape_string($finance_connect, $previewData['shopee_acc']) . "', '$orderId', '$paymentDate', '" . mysqli_real_escape_string($connect, $previewData['currency']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['topup_amt']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['subtotal']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['gst']) . "', '" . mysqli_real_escape_string($finance_connect, $previewData['pay_meth']) . "', '$remark', '" . USER_ID . "', curdate(), curtime())";

        $returnData = mysqli_query($finance_connect, $query);

        if ($returnData) {
            $dataID = mysqli_insert_id($finance_connect);
            $newvalarr = [
                getImportLabelById($shopeeAccounts, $previewData['shopee_acc']),
                $previewData['order_id'],
                $paymentDate,
                getImportLabelById($currencyUnits, $previewData['currency']),
                $previewData['topup_amt'],
                $previewData['subtotal'],
                $previewData['gst'],
                getImportLabelById($paymentMethods, $previewData['pay_meth']),
                $previewData['remark'] === '' ? 'Empty Value' : $previewData['remark'],
            ];

            $log = [
                'log_act' => 'Import',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $query,
                'query_table' => SHOPEE_ADS_TOPUP,
                'newval' => implodeWithComma($newvalarr),
                'act_msg' => USER_NAME . " imported the data [ <b> ID = " . $dataID . " </b> ] from <b><i>" . SHOPEE_ADS_TOPUP . " Table</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo '<script>alert("Shopee Ads top up transaction imported successfully.");window.location.href="' . $shopeeRedirectPage . '";</script>';
            exit;
        }

        $importErrors[] = 'Unable to insert the import record. Please try again.';
    }
} else if ($action === 'parseFacebookAdsTopup') {
    $module = 'fb_ads_topup';

    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        $importErrors[] = 'Please choose a Facebook Ads PDF receipt or ZIP file.';
    } else {
        $sourceFiles = collectFacebookImportSourceFiles($_FILES['import_file'], $importErrors, $importWarnings);

        if (!empty($sourceFiles)) {
            $parseResult = parseFacebookImportFiles($sourceFiles, $metaAccounts);
            $facebookPreviewRecords = $parseResult['records'];
            $facebookImportSummary = $parseResult['summary'];
            $importWarnings = array_merge($importWarnings, $parseResult['warnings']);
            $importErrors = array_merge($importErrors, $parseResult['errors']);

            if (empty($facebookPreviewRecords) && empty($importErrors)) {
                $importErrors[] = 'No paid Facebook Ads receipt was ready for preview.';
            }
        }
    }
} else if ($action === 'insertFacebookAdsTopup') {
    $module = 'fb_ads_topup';
    $facebookPreviewRecords = getFacebookPreviewRecordsFromPost();
    $importWarnings = array_filter(post('importWarnings') ? explode("\n", post('importWarnings')) : []);
    $facebookImportSummary = getFacebookImportSummaryFromPost();

    validateFacebookPreviewRecords($facebookPreviewRecords, $importErrors, $metaAccounts, $userOptions, $finance_connect);

    if (empty($facebookImportSummary['preview_records'])) {
        $facebookImportSummary['preview_records'] = count($facebookPreviewRecords);
    }

    if (empty($importErrors)) {
        $insertedCount = 0;
        mysqli_begin_transaction($finance_connect);

        try {
            foreach ($facebookPreviewRecords as $index => $record) {
                $transactionId = mysqli_real_escape_string($finance_connect, $record['transaction_id']);
                $paymentDate = formatImportDateOnly($record['payment_date']);
                $remark = mysqli_real_escape_string($finance_connect, $record['remark']);
                $query = "INSERT INTO " . FB_ADS_TOPUP . " (meta_acc, transactionID, payment_date, pic, topup_amt, attachment, remark, create_by, create_date, create_time) VALUES ('" . mysqli_real_escape_string($finance_connect, $record['meta_acc']) . "', '$transactionId', '$paymentDate', '" . mysqli_real_escape_string($finance_connect, $record['pic']) . "', '" . mysqli_real_escape_string($finance_connect, $record['topup_amt']) . "', '', '$remark', '" . USER_ID . "', curdate(), curtime())";
                $returnData = mysqli_query($finance_connect, $query);

                if (!$returnData) {
                    throw new Exception('Unable to insert Facebook Ads import record #' . ($index + 1) . '.');
                }

                $dataID = mysqli_insert_id($finance_connect);
                $newvalarr = [
                    getMetaAdsAccountLabelById($metaAccounts, $record['meta_acc']),
                    $record['transaction_id'],
                    $paymentDate,
                    getImportLabelById($userOptions, $record['pic']),
                    $record['topup_amt'],
                    'No Attachment (Import Preview Only)',
                    $record['remark'] === '' ? 'Empty Value' : $record['remark'],
                ];

                $log = [
                    'log_act' => 'Import',
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => FB_ADS_TOPUP,
                    'newval' => implodeWithComma($newvalarr),
                    'act_msg' => USER_NAME . " imported the data [ <b> ID = " . $dataID . " </b> ] from <b><i>" . FB_ADS_TOPUP . " Table</i></b>.",
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                audit_log($log);

                $insertedCount++;
            }

            mysqli_commit($finance_connect);
            echo '<script>alert("Imported ' . $insertedCount . ' Facebook Ads top up transaction(s) successfully.");window.location.href="' . $facebookRedirectPage . '";</script>';
            exit;
        } catch (Exception $exception) {
            mysqli_rollback($finance_connect);
            $importErrors[] = $exception->getMessage();
        }
    }
} else if ($action === 'parseShopeeOrderReq') { // NEW: Shopee Order HTML Parsing
    $module = 'shopee_order_req';

    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Shopee Order HTML file.';
    } else if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . $_FILES['import_file']['error'];
    } else {
        $html = file_get_contents($_FILES['import_file']['tmp_name']);

        if ($html === false || trim($html) === '') {
            $importErrors[] = 'The uploaded file could not be read.';
        } else {
            $cleanText = normalizeImportText(strip_tags(str_replace(['<', '>'], [' <', '> '], $html)));

            $order_id = preg_match('/(?:Order ID|Order SN|Order No)[^A-Z0-9]*([A-Z0-9]{12,24})/i', $cleanText, $m) ? strtoupper(trim($m[1])) : '';
            if ($order_id === '') {
                $order_id = preg_match('/sale\/order\/([A-Z0-9]{12,24})/i', $html, $m) ? strtoupper(trim($m[1])) : '';
            }

            $sku = preg_match('/(?:SKU|Item Code)[^A-Za-z0-9]*([A-Za-z0-9\-_]+)/i', $cleanText, $m) ? trim($m[1]) : '';

            $product_price = parseShopeeOrderAmountByLabels($cleanText, ['Product Price', 'Deal Price']);
            $voucher = parseShopeeOrderAmountByLabels($cleanText, ['Shopee Voucher', 'Seller Voucher']);
            $actShippingFee = parseShopeeOrderAmountByLabels($cleanText, ['Estimated Shipping Subtotal', 'Estimated Shipping Fee Charged by Logistic Provider']);
            $serviceFee = parseShopeeOrderAmountByLabels($cleanText, ['Service Fee']);
            $transactionFee = parseShopeeOrderAmountByLabels($cleanText, ['Transaction Fee']);
            $amsFee = parseShopeeOrderAmountByLabels($cleanText, ['Commission Fee']);
            $fees = parseShopeeOrderAmountByLabels($cleanText, ['Fees & Charges']);
            $finalAmt = parseShopeeOrderAmountByLabels($cleanText, ['Final Amount', 'Estimated Order Income']);

            $detectedCurrency = detectShopeeOrderCurrency($cleanText);
            $currencyFallbacks = $detectedCurrency === 'RM' ? ['MYR'] : [];
            $currencyId = resolveImportOptionId($detectedCurrency, $currencyUnits, $currencyFallbacks);

            $buyerUsername = extractShopeeBuyerUsername($html, $cleanText);
            $buyerId = resolveImportOptionId($buyerUsername, $shopeeBuyers);

            if ($order_id === '') {
                $importErrors[] = 'Order ID could not be detected. Please ensure you uploaded the correct Shopee Order Details HTML file.';
            }

            $pkg_id = '';
            $missing_sku = false;
            
            // Safely Validate the SKU from the CMS Package DB
            if (!empty($sku)) {
                // Escape SKU to prevent SQL injection when building the condition string
                $safeSku = mysqli_real_escape_string($connect, $sku);
                // Search by item_code OR name (Removed 'barcode' as it does not exist in package table)
                $pkgResult = getData('*', "item_code='$safeSku' OR name='$safeSku'", '', PKG, $connect); 
                if ($pkgResult && $pkgResult->num_rows > 0) {
                    $pkg_row = $pkgResult->fetch_assoc();
                    $pkg_id = $pkg_row['id'];
                } else {
                    $missing_sku = true;
                }
            } else {
                $missing_sku = true;
            }

            $previewData = [
                'order_id' => $order_id,
                'sku' => $sku,
                'package_id' => $pkg_id,
                'product_price' => $product_price !== '' ? $product_price : '0.00',
                'order_status' => 'Pending To Pack',
                'order_status_val' => 'P',
                'missing_sku' => $missing_sku,
                'shopee_acc' => '',
                'currency' => $currencyId,
                'source_currency' => $detectedCurrency,
                'brand' => '',
                'buyer' => $buyerId,
                'source_buyer_username' => $buyerUsername,
                'buyer_pay_meth' => '',
                'pic' => (string) USER_ID,
                'voucher' => $voucher,
                'act_shipping_fee' => $actShippingFee,
                'service_fee' => $serviceFee,
                'trans_fee' => $transactionFee,
                'ams_fee' => $amsFee,
                'fees' => $fees,
                'final_amt' => $finalAmt,
                'remark' => $order_id !== '' ? ('Imported from Shopee Order HTML (' . $order_id . ')') : 'Imported from Shopee Order HTML',
            ];

            if ($currencyId === '') {
                $importWarnings[] = 'Currency could not be matched automatically. Please select the correct currency before inserting.';
            }
            if ($buyerUsername !== '' && $buyerId === '') {
                $importWarnings[] = 'Buyer username was detected as ' . $buyerUsername . ' but not matched in Customer Info. Please select buyer manually.';
            }
        }
    }
} else if ($action === 'insertShopeeOrderReq') { // NEW: Shopee Order Insert
    $module = 'shopee_order_req';
    
    $previewData = [
        'order_id' => postSpaceFilter('order_id'),
        'package_id' => postSpaceFilter('package_id'),
        'product_price' => postSpaceFilter('product_price'),
        'order_status' => 'P',
        'order_status_val' => 'P',
        'sku' => isset($_POST['sku']) ? $_POST['sku'] : '',
        'missing_sku' => postSpaceFilter('package_id') === '',
        'shopee_acc' => postSpaceFilter('shopee_acc'),
        'currency' => postSpaceFilter('currency'),
        'brand' => postSpaceFilter('brand'),
        'buyer' => postSpaceFilter('buyer'),
        'buyer_pay_meth' => postSpaceFilter('buyer_pay_meth'),
        'pic' => postSpaceFilter('pic'),
        'voucher' => postSpaceFilter('voucher'),
        'act_shipping_fee' => postSpaceFilter('act_shipping_fee'),
        'service_fee' => postSpaceFilter('service_fee'),
        'trans_fee' => postSpaceFilter('trans_fee'),
        'ams_fee' => postSpaceFilter('ams_fee'),
        'fees' => postSpaceFilter('fees'),
        'final_amt' => postSpaceFilter('final_amt'),
        'remark' => postSpaceFilter('remark'),
    ];

    if ($previewData['package_id'] === '') $importErrors[] = 'Package ID is missing. Please add the package first.';
    if ($previewData['order_id'] === '') $importErrors[] = 'Order ID is required.';
    if ($previewData['shopee_acc'] === '') $importErrors[] = 'Shopee Account is required.';
    if ($previewData['currency'] === '') $importErrors[] = 'Currency is required.';
    if ($previewData['brand'] === '') $importErrors[] = 'Brand is required.';
    if ($previewData['pic'] === '') $importErrors[] = 'Person In Charge is required.';

    $previewData['voucher'] = $previewData['voucher'] !== '' ? $previewData['voucher'] : '0.00';
    $previewData['act_shipping_fee'] = $previewData['act_shipping_fee'] !== '' ? $previewData['act_shipping_fee'] : '0.00';
    $previewData['service_fee'] = $previewData['service_fee'] !== '' ? $previewData['service_fee'] : '0.00';
    $previewData['trans_fee'] = $previewData['trans_fee'] !== '' ? $previewData['trans_fee'] : '0.00';
    $previewData['ams_fee'] = $previewData['ams_fee'] !== '' ? $previewData['ams_fee'] : '0.00';
    $previewData['fees'] = $previewData['fees'] !== '' ? $previewData['fees'] : '0.00';
    $previewData['final_amt'] = $previewData['final_amt'] !== '' ? $previewData['final_amt'] : '0.00';

    if (empty($importErrors)) {
        $orderId = mysqli_real_escape_string($finance_connect, $previewData['order_id']);
        $pkgId = mysqli_real_escape_string($connect, $previewData['package_id']);
        $price = mysqli_real_escape_string($finance_connect, $previewData['product_price']);
        $status = mysqli_real_escape_string($finance_connect, $previewData['order_status']);
        $acc = mysqli_real_escape_string($finance_connect, $previewData['shopee_acc']);
        $curr = mysqli_real_escape_string($connect, $previewData['currency']);
        $brand = mysqli_real_escape_string($connect, $previewData['brand']);
        $buyer = mysqli_real_escape_string($finance_connect, $previewData['buyer']);
        $payMeth = mysqli_real_escape_string($finance_connect, $previewData['buyer_pay_meth']);
        $pic = mysqli_real_escape_string($connect, $previewData['pic']);
        $voucher = mysqli_real_escape_string($finance_connect, $previewData['voucher']);
        $actShippingFee = mysqli_real_escape_string($finance_connect, $previewData['act_shipping_fee']);
        $serviceFee = mysqli_real_escape_string($finance_connect, $previewData['service_fee']);
        $transFee = mysqli_real_escape_string($finance_connect, $previewData['trans_fee']);
        $amsFee = mysqli_real_escape_string($finance_connect, $previewData['ams_fee']);
        $fees = mysqli_real_escape_string($finance_connect, $previewData['fees']);
        $finalAmt = mysqli_real_escape_string($finance_connect, $previewData['final_amt']);
        $remark = mysqli_real_escape_string($finance_connect, $previewData['remark']);
        
        $query = "INSERT INTO " . SHOPEE_SG_ORDER_REQ . " 
            (orderID, package, price, voucher, act_shipping_fee, service_fee, trans_fee, ams_fee, fees, final_amt, order_status, shopee_acc, currency, brand, buyer, buyer_pay_meth, pic, remark, date, time, create_by, create_date, create_time) 
            VALUES ('$orderId', '$pkgId', '$price', '$voucher', '$actShippingFee', '$serviceFee', '$transFee', '$amsFee', '$fees', '$finalAmt', '$status', '$acc', '$curr', '$brand', '$buyer', '$payMeth', '$pic', '$remark', curdate(), curtime(), '" . USER_ID . "', curdate(), curtime())";
        
        $returnData = mysqli_query($finance_connect, $query);

        if ($returnData) {
            echo '<script>alert("Shopee Order Request imported successfully.");window.location.href="' . $shopeeOrderRedirectPage . '";</script>';
            exit;
        } else {
            $importErrors[] = 'Database Error: ' . mysqli_error($finance_connect);
        }
    }
}

function ensureImportDirectory($directory)
{
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
}

function getImportOptionList($tableName, $labelField, $dbConnect)
{
    $list = [];
    $tableName = mysqli_real_escape_string($dbConnect, $tableName);
    $labelField = mysqli_real_escape_string($dbConnect, $labelField);
    $query = "SELECT id, `$labelField` AS option_label FROM `$tableName` WHERE status = 'A' ORDER BY `$labelField` ASC";
    $result = mysqli_query($dbConnect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $list[$row['id']] = $row['option_label'];
        }
    }

    return $list;
}

function getMetaAdsAccountOptions($dbConnect)
{
    $list = [];
    $query = "SELECT id, accID, accName FROM " . META_ADS_ACC . " WHERE status = 'A' ORDER BY accName ASC";
    $result = mysqli_query($dbConnect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $list[$row['id']] = [
                'acc_id' => $row['accID'],
                'acc_name' => $row['accName'],
                'label' => trim($row['accName'] . ($row['accID'] !== '' ? ' (' . $row['accID'] . ')' : '')),
            ];
        }
    }

    return $list;
}

function getMetaAdsAccountLabelById($options, $id)
{
    return isset($options[$id]['label']) ? $options[$id]['label'] : '';
}

function normalizeImportText($text)
{
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

function normalizeImportLookup($text)
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', normalizeImportText($text)));
}

function normalizeDigitsOnly($text)
{
    return preg_replace('/\D+/', '', (string) $text);
}

function getNodeText($xpath, $query, $contextNode = null)
{
    $nodes = $xpath->query($query, $contextNode);
    if ($nodes && $nodes->length > 0) {
        return normalizeImportText($nodes->item(0)->textContent);
    }

    return '';
}

function extractValueAfterColon($text)
{
    $parts = explode(':', $text, 2);
    return isset($parts[1]) ? normalizeImportText($parts[1]) : normalizeImportText($text);
}

function extractMoneyDetails($text)
{
    $normalized = normalizeImportText($text);

    if (preg_match('/([A-Z]{1,5}|RM|SGD|USD|MYR|EUR|GBP)\s*([0-9][0-9,]*\.?[0-9]*)/i', $normalized, $matches)) {
        return [
            'currency' => strtoupper($matches[1]),
            'amount' => number_format((float) str_replace(',', '', $matches[2]), 2, '.', ''),
        ];
    }

    return [
        'currency' => '',
        'amount' => '',
    ];
}

function parseShopeeDatetime($value)
{
    $value = normalizeImportText($value);
    $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
}

function parseFacebookReceiptDate($value)
{
    $value = normalizeImportText(str_replace(' at ', ', ', $value));
    $formats = ['j M Y, H:i', 'j M Y, H:i:s', 'd M Y, H:i', 'd M Y, H:i:s', 'Y-m-d'];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

function formatImportDatetime($value)
{
    $parsed = parseShopeeDatetime($value);
    return $parsed !== '' ? $parsed : date('Y-m-d H:i:s');
}

function formatImportDateOnly($value)
{
    $parsed = parseFacebookReceiptDate($value);
    return $parsed !== '' ? $parsed : date('Y-m-d');
}

function formatDatetimeLocalValue($value)
{
    $parsed = parseShopeeDatetime($value);
    return $parsed !== '' ? date('Y-m-d\TH:i', strtotime($parsed)) : date('Y-m-d\TH:i');
}

function normalizeImportAmount($rawAmount)
{
    $value = trim((string) $rawAmount);
    if ($value === '') {
        return '';
    }

    $value = str_replace([',', ' '], '', $value);
    $value = str_replace(['RM', 'MYR', 'USD', 'SGD'], '', strtoupper($value));
    $isNegative = strpos($value, '-') === 0;
    $numeric = preg_replace('/[^0-9.]+/', '', $value);

    if ($numeric === '' || !is_numeric($numeric)) {
        return '';
    }

    $amount = (float) $numeric;
    if ($isNegative) {
        $amount *= -1;
    }
    // Remove abs() so negative values are preserved
    return number_format($amount, 2, '.', '');
}

function parseShopeeOrderAmountByLabels($text, $labels)
{
    foreach ($labels as $label) {
        $pattern = '/' . preg_quote($label, '/') . '.{0,120}?(-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.?[0-9]*)/i';
        if (preg_match($pattern, $text, $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }
    }

    return '0.00';
}

function detectShopeeOrderCurrency($text)
{
    if (preg_match('/\bRM\s*[0-9]/i', $text)) {
        return 'RM';
    }
    if (preg_match('/\bMYR\s*[0-9]/i', $text)) {
        return 'MYR';
    }
    if (preg_match('/\bSGD\s*[0-9]/i', $text)) {
        return 'SGD';
    }
    if (preg_match('/\bUSD\s*[0-9]/i', $text)) {
        return 'USD';
    }

    return '';
}

function extractShopeeBuyerUsername($html, $text)
{
    if (preg_match('/class="username\s+text-overflow"[^>]*>([^<]+)/i', $html, $matches)) {
        return normalizeImportText($matches[1]);
    }

    if (preg_match('/\bBuyer\s*Username\b[^A-Za-z0-9]*([A-Za-z0-9._\-]+)/i', $text, $matches)) {
        return normalizeImportText($matches[1]);
    }

    return '';
}

function extractValueFromTableLabel($xpath, $labels)
{
    foreach ($xpath->query('//tr') as $row) {
        $cells = $xpath->query('./th|./td', $row);
        if ($cells->length < 2) {
            continue;
        }

        $labelText = normalizeImportText($cells->item(0)->textContent);
        foreach ($labels as $label) {
            if (stripos($labelText, $label) !== false) {
                return normalizeImportText($cells->item(1)->textContent);
            }
        }
    }

    return '';
}

function resolveImportOptionId($rawValue, $options, $fallbacks = [])
{
    $candidates = array_merge([(string) $rawValue], $fallbacks);

    foreach ($candidates as $candidate) {
        $normalizedCandidate = normalizeImportLookup($candidate);
        if ($normalizedCandidate === '') {
            continue;
        }

        foreach ($options as $id => $label) {
            $normalizedLabel = normalizeImportLookup($label);
            if ($normalizedLabel === $normalizedCandidate) {
                return $id;
            }
        }

        foreach ($options as $id => $label) {
            $normalizedLabel = normalizeImportLookup($label);
            if ($normalizedLabel !== '' && (strpos($normalizedLabel, $normalizedCandidate) !== false || strpos($normalizedCandidate, $normalizedLabel) !== false)) {
                return $id;
            }
        }
    }

    return '';
}

function resolveMetaAdsAccountId($rawValue, $options)
{
    $normalizedLookup = normalizeImportLookup($rawValue);
    $normalizedDigits = normalizeDigitsOnly($rawValue);

    foreach ($options as $id => $option) {
        $optionLookup = normalizeImportLookup($option['acc_id']);
        $optionDigits = normalizeDigitsOnly($option['acc_id']);
        $optionNameLookup = normalizeImportLookup($option['acc_name']);

        if ($normalizedLookup !== '' && ($optionLookup === $normalizedLookup || $optionNameLookup === $normalizedLookup)) {
            return $id;
        }

        if ($normalizedDigits !== '' && ($optionDigits === $normalizedDigits || substr($optionDigits, -strlen($normalizedDigits)) === $normalizedDigits)) {
            return $id;
        }
    }

    return '';
}

function getImportLabelById($options, $id)
{
    return isset($options[$id]) ? $options[$id] : '';
}

function parseShopeeAdsTopupHtml($html, $shopeeAccounts, $currencyUnits, $paymentMethods)
{
    $data = [
        'source_shop_name' => '',
        'source_currency' => '',
        'source_payment_method' => '',
        'shopee_acc' => '',
        'order_id' => '',
        'payment_date' => '',
        'currency' => '',
        'topup_amt' => '',
        'subtotal' => '',
        'gst' => '',
        'pay_meth' => '',
        'remark' => '',
    ];
    $errors = [];
    $warnings = [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = $dom->loadHTML($html);
    libxml_clear_errors();

    if (!$loaded) {
        return [
            'data' => $data,
            'errors' => ['The uploaded file is not a valid HTML document.'],
            'warnings' => $warnings,
        ];
    }

    $xpath = new DOMXPath($dom);

    $shopHeader = getNodeText($xpath, "//div[contains(@class,'order-detail-info')]//div[contains(@class,'header')]");
    $data['source_shop_name'] = extractValueAfterColon($shopHeader);
    $data['order_id'] = extractValueAfterColon(getNodeText($xpath, "//*[contains(@class,'purchase-info__order-sn') or contains(text(),'Order ID:')][1]"));

    $completedTime = getNodeText($xpath, "//div[contains(@class,'timeline-item')][.//div[contains(@class,'title') and normalize-space()='Completed']]//div[contains(@class,'time')]");
    if ($completedTime === '') {
        $completedTime = getNodeText($xpath, "//div[contains(@class,'service-mess')]//span[contains(@class,'time')]");
    }
    $data['payment_date'] = parseShopeeDatetime($completedTime);

    $paymentTotal = extractMoneyDetails(extractValueFromTableLabel($xpath, ['Payment Total']));
    $subtotal = extractMoneyDetails(extractValueFromTableLabel($xpath, ['Subtotal']));
    $taxValue = extractMoneyDetails(extractValueFromTableLabel($xpath, ['SST', 'GST', 'Tax']));
    $paymentMethod = extractValueFromTableLabel($xpath, ['Payment Method']);

    if ($paymentTotal['amount'] === '') {
        $paymentTotal = extractMoneyDetails(getNodeText($xpath, "//*[contains(text(),'Payment Total')][1]"));
    }

    $data['source_currency'] = $paymentTotal['currency'] !== '' ? $paymentTotal['currency'] : $subtotal['currency'];
    $data['source_payment_method'] = $paymentMethod;
    $data['topup_amt'] = $paymentTotal['amount'];
    $data['subtotal'] = $subtotal['amount'];
    $data['gst'] = $taxValue['amount'];
    $data['remark'] = 'Imported from Shopee Seller Centre HTML';

    if ($data['order_id'] !== '') {
        $data['remark'] .= ' (' . $data['order_id'] . ')';
    }

    $currencyFallbacks = [];
    if ($data['source_currency'] === 'RM') {
        $currencyFallbacks = ['MYR'];
    } else if ($data['source_currency'] === 'S$') {
        $currencyFallbacks = ['SGD'];
    }

    $data['shopee_acc'] = resolveImportOptionId($data['source_shop_name'], $shopeeAccounts);
    $data['currency'] = resolveImportOptionId($data['source_currency'], $currencyUnits, $currencyFallbacks);
    $data['pay_meth'] = resolveImportOptionId($data['source_payment_method'], $paymentMethods);

    if ($data['source_shop_name'] === '') {
        $errors[] = 'Shop name could not be detected from the HTML file.';
    }

    if ($data['order_id'] === '') {
        $errors[] = 'Order ID could not be detected from the HTML file.';
    }

    if ($data['payment_date'] === '') {
        $errors[] = 'Payment date could not be detected from the HTML file.';
    }

    if ($data['topup_amt'] === '') {
        $errors[] = 'Payment total could not be detected from the HTML file.';
    }

    if ($data['subtotal'] === '') {
        $errors[] = 'Subtotal could not be detected from the HTML file.';
    }

    if ($data['gst'] === '') {
        $errors[] = 'Tax amount could not be detected from the HTML file.';
    }

    if ($data['shopee_acc'] === '') {
        $warnings[] = 'Shopee account was not matched automatically. Please choose the correct account before inserting.';
    }

    if ($data['currency'] === '') {
        $warnings[] = 'Currency unit was not matched automatically. Please choose the correct currency before inserting.';
    }

    if ($data['pay_meth'] === '') {
        $warnings[] = 'Payment method was not matched automatically. Please choose the correct payment method before inserting.';
    }

    return [
        'data' => $data,
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

function getShopeeAdsPreviewFromPost()
{
    return [
        'source_shop_name' => postSpaceFilter('source_shop_name'),
        'source_currency' => postSpaceFilter('source_currency'),
        'source_payment_method' => postSpaceFilter('source_payment_method'),
        'shopee_acc' => postSpaceFilter('shopee_acc'),
        'order_id' => postSpaceFilter('order_id'),
        'payment_date' => postSpaceFilter('payment_date'),
        'currency' => postSpaceFilter('currency'),
        'topup_amt' => postSpaceFilter('topup_amt'),
        'subtotal' => postSpaceFilter('subtotal'),
        'gst' => postSpaceFilter('gst'),
        'pay_meth' => postSpaceFilter('pay_meth'),
        'remark' => postSpaceFilter('remark'),
    ];
}

function validateShopeeAdsPreview($previewData, &$importErrors, $shopeeAccounts, $currencyUnits, $paymentMethods, $finance_connect, $connect)
{
    if ($previewData['shopee_acc'] === '' || !isset($shopeeAccounts[$previewData['shopee_acc']])) {
        $importErrors[] = 'Shopee Account is required.';
    }

    if ($previewData['order_id'] === '') {
        $importErrors[] = 'Order ID is required.';
    } else if (isDuplicateRecord('orderID', $previewData['order_id'], SHOPEE_ADS_TOPUP, $finance_connect, '')) {
        $importErrors[] = 'Duplicate Order ID found in Shopee Ads Top Up Transaction.';
    }

    if ($previewData['payment_date'] === '' || parseShopeeDatetime($previewData['payment_date']) === '') {
        $importErrors[] = 'Payment date is invalid.';
    }

    if ($previewData['currency'] === '' || !isset($currencyUnits[$previewData['currency']])) {
        $importErrors[] = 'Currency is required.';
    }

    if ($previewData['topup_amt'] === '' || !is_numeric($previewData['topup_amt'])) {
        $importErrors[] = 'Top-up Amount must be a valid number.';
    }

    if ($previewData['subtotal'] === '' || !is_numeric($previewData['subtotal'])) {
        $importErrors[] = 'Subtotal must be a valid number.';
    }

    if ($previewData['gst'] === '' || !is_numeric($previewData['gst'])) {
        $importErrors[] = 'GST must be a valid number.';
    }

    if ($previewData['pay_meth'] === '' || !isset($paymentMethods[$previewData['pay_meth']])) {
        $importErrors[] = 'Payment Method is required.';
    }
}

function sanitizeImportFilename($filename)
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename((string) $filename));
    return $filename !== '' ? $filename : ('import_' . uniqid() . '.pdf');
}

function collectFacebookImportSourceFiles($fileInfo, &$errors, &$warnings)
{
    $sourceFiles = [];
    $originalName = isset($fileInfo['name']) ? $fileInfo['name'] : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if ($extension === 'pdf') {
        $pdfContent = @file_get_contents($fileInfo['tmp_name']);
        if ($pdfContent === false || $pdfContent === '') {
            $errors[] = 'Unable to read the uploaded Facebook Ads PDF file.';
            return [];
        }

        $sourceFiles[] = [
            'pdf_content' => $pdfContent,
            'original_name' => sanitizeImportFilename($originalName),
        ];
        return $sourceFiles;
    }

    if ($extension !== 'zip') {
        $errors[] = 'Only PDF or ZIP files are supported for Facebook Ads import.';
        return [];
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($fileInfo['tmp_name']) !== true) {
            $errors[] = 'The uploaded ZIP file could not be opened.';
            return [];
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);
            if (substr($entryName, -1) === '/') {
                continue;
            }

            if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $pdfContent = $zip->getFromIndex($index);
            if ($pdfContent === false || $pdfContent === '') {
                $warnings[] = 'Unable to read PDF entry from ZIP: ' . $entryName;
                continue;
            }

            $sourceFiles[] = [
                'pdf_content' => $pdfContent,
                'original_name' => sanitizeImportFilename($entryName),
            ];
        }

        $zip->close();
    } else if (class_exists('PharData')) {
        try {
            $zipArchive = new PharData($fileInfo['tmp_name']);

            foreach (new RecursiveIteratorIterator($zipArchive) as $entry) {
                if (!($entry instanceof SplFileInfo) || !$entry->isFile()) {
                    continue;
                }

                $entryName = $entry->getFilename();
                if (strtolower(pathinfo($entryName, PATHINFO_EXTENSION)) !== 'pdf') {
                    continue;
                }

                $pdfContent = @file_get_contents($entry->getPathname());
                if ($pdfContent === false || $pdfContent === '') {
                    $warnings[] = 'Unable to read PDF entry from ZIP: ' . $entryName;
                    continue;
                }

                $sourceFiles[] = [
                    'pdf_content' => $pdfContent,
                    'original_name' => sanitizeImportFilename($entryName),
                ];
            }
        } catch (Exception $exception) {
            $errors[] = 'The uploaded ZIP file could not be opened.';
            return [];
        }
    } else {
        $errors[] = 'ZIP import requires PHP ZipArchive support in the current web runtime.';
        return [];
    }

    if (empty($sourceFiles)) {
        $errors[] = 'No PDF file was found inside the uploaded ZIP archive.';
    }

    return $sourceFiles;
}

function decodePdfStream($stream)
{
    $decoded = @gzuncompress($stream);
    if ($decoded !== false) {
        return $decoded;
    }

    $decoded = @gzinflate($stream);
    if ($decoded !== false) {
        return $decoded;
    }

    if (strlen($stream) > 6) {
        $decoded = @gzinflate(substr($stream, 2));
        if ($decoded !== false) {
            return $decoded;
        }
    }

    return false;
}

function cleanPdfTextOperand($text)
{
    $text = str_replace("\x00", "", $text);
    $text = strtr($text, [
        '\\n' => ' ',
        '\\r' => ' ',
        '\\t' => ' ',
        '\\(' => '(',
        '\\)' => ')',
        '\\\\' => '\\',
    ]);
    return normalizeImportText(preg_replace('/[^[:print:] ]/', ' ', $text));
}

function extractTextFromPdfContent($content)
{
    if ($content === '') {
        return '';
    }

    preg_match_all('/stream\r?\n(.*?)endstream/s', $content, $streamMatches);
    $lines = [];

    foreach ($streamMatches[1] as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false) {
            continue;
        }

        if (preg_match_all('/\(([^\)]{1,500})\)\s*Tj/s', $decoded, $textMatches)) {
            foreach ($textMatches[1] as $match) {
                $cleanLine = cleanPdfTextOperand($match);
                if ($cleanLine !== '') {
                    $lines[] = $cleanLine;
                }
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
            foreach ($arrayMatches[1] as $chunk) {
                preg_match_all('/\(([^\)]*)\)/', $chunk, $innerMatches);
                $cleanLine = cleanPdfTextOperand(implode('', $innerMatches[1]));
                if ($cleanLine !== '') {
                    $lines[] = $cleanLine;
                }
            }
        }
    }

    return implode("\n", $lines);
}

function extractPdfValueAfterLabel($text, $label)
{
    if (preg_match('/' . preg_quote($label, '/') . '\s*:?\s*(.+)/i', $text, $matches)) {
        return normalizeImportText($matches[1]);
    }

    return '';
}

function getPdfTextLines($text)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);
    $normalizedLines = [];

    foreach ($lines as $line) {
        $line = normalizeImportText($line);
        if ($line !== '') {
            $normalizedLines[] = $line;
        }
    }

    return $normalizedLines;
}

function extractPdfFieldValue($text, $label)
{
    $lines = getPdfTextLines($text);
    $labelLookup = normalizeImportLookup($label);

    foreach ($lines as $index => $line) {
        $lineLookup = normalizeImportLookup($line);
        if ($lineLookup === '') {
            continue;
        }

        if (strpos($lineLookup, $labelLookup) === false) {
            continue;
        }

        $value = extractPdfValueAfterLabel($line, $label);
        if ($value !== '' && normalizeImportLookup($value) !== $labelLookup) {
            return $value;
        }

        if (isset($lines[$index + 1])) {
            return $lines[$index + 1];
        }
    }

    return '';
}

function extractPdfPaymentStatus($text)
{
    foreach (getPdfTextLines($text) as $line) {
        $normalizedLine = strtolower(trim($line, " \t\n\r\0\x0B.:;,-_()[]{}"));

        if ($normalizedLine === 'paid') {
            return 'Paid';
        }
        if ($normalizedLine === 'unpaid') {
            return 'Unpaid';
        }
        if ($normalizedLine === 'pending') {
            return 'Pending';
        }
        if ($normalizedLine === 'failed') {
            return 'Failed';
        }
    }

    if (preg_match('/(?:^|\R)\s*(Paid|Unpaid|Pending|Failed)\s*(?:\R|$)/i', $text, $matches)) {
        return ucfirst(strtolower($matches[1]));
    }

    $normalizedText = normalizeImportLookup($text);
    if (strpos($normalizedText, 'unpaid') !== false) {
        return 'Unpaid';
    }
    if (strpos($normalizedText, 'pending') !== false) {
        return 'Pending';
    }
    if (strpos($normalizedText, 'failed') !== false) {
        return 'Failed';
    }
    if (strpos($normalizedText, 'paid') !== false) {
        return 'Paid';
    }

    return '';
}

function parseFacebookReceiptPdf($fileInfo, $metaAccounts)
{
    $data = [
        'source_file_name' => $fileInfo['original_name'],
        'source_account_id' => '',
        'source_payment_method' => '',
        'source_reference_number' => '',
        'source_status' => '',
        'meta_acc' => '',
        'transaction_id' => '',
        'payment_date' => '',
        'pic' => (string) USER_ID,
        'topup_amt' => '',
        'remark' => '',
    ];
    $errors = [];
    $warnings = [];
    $skip = false;

    $text = extractTextFromPdfContent($fileInfo['pdf_content']);

    if ($text === '') {
        return [
            'data' => $data,
            'errors' => ['Unable to extract text from PDF receipt: ' . $fileInfo['original_name']],
            'warnings' => $warnings,
            'skip' => false,
        ];
    }

    $data['source_account_id'] = extractPdfFieldValue($text, 'Account ID');
    $data['source_payment_method'] = extractPdfFieldValue($text, 'Payment method');
    $data['source_reference_number'] = extractPdfFieldValue($text, 'Reference number');
    $data['payment_date'] = parseFacebookReceiptDate(extractPdfFieldValue($text, 'Invoice/payment date'));
    $data['transaction_id'] = extractPdfFieldValue($text, 'Transaction ID');
    $data['source_status'] = extractPdfPaymentStatus($text);

    if (preg_match('/\bPaid\b\s*([A-Z]{3}|RM|SGD|USD|EUR|GBP)\s*([0-9][0-9,]*\.?[0-9]*)/is', $text, $matches)) {
        $data['topup_amt'] = number_format((float) str_replace(',', '', $matches[2]), 2, '.', '');
    }

    $data['meta_acc'] = resolveMetaAdsAccountId($data['source_account_id'], $metaAccounts);

    $remarkParts = ['Imported from Facebook Ads receipt'];
    if ($data['transaction_id'] !== '') {
        $remarkParts[] = $data['transaction_id'];
    }
    if ($data['source_reference_number'] !== '') {
        $remarkParts[] = 'Ref ' . $data['source_reference_number'];
    }
    $data['remark'] = implode(' | ', $remarkParts);

    if ($data['source_status'] !== 'Paid') {
        $skip = true;
        $warnings[] = $fileInfo['original_name'] . ' was skipped because payment status is not Paid.';
        return [
            'data' => $data,
            'errors' => $errors,
            'warnings' => $warnings,
            'skip' => $skip,
        ];
    }

    if ($data['source_account_id'] === '') {
        $errors[] = 'Meta account ID could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['transaction_id'] === '') {
        $errors[] = 'Transaction ID could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['payment_date'] === '') {
        $errors[] = 'Invoice/payment date could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['topup_amt'] === '') {
        $errors[] = 'Paid amount could not be detected from ' . $fileInfo['original_name'] . '.';
    }

    if ($data['meta_acc'] === '') {
        $warnings[] = 'Meta account was not matched automatically for ' . $fileInfo['original_name'] . '. Please choose the correct account before inserting.';
    }

    return [
        'data' => $data,
        'errors' => $errors,
        'warnings' => $warnings,
        'skip' => $skip,
    ];
}

function parseFacebookImportFiles($sourceFiles, $metaAccounts)
{
    $records = [];
    $warnings = [];
    $errors = [];
    $summary = [
        'processed_files' => count($sourceFiles),
        'preview_records' => 0,
        'skipped_files' => 0,
    ];

    foreach ($sourceFiles as $fileInfo) {
        $result = parseFacebookReceiptPdf($fileInfo, $metaAccounts);
        $warnings = array_merge($warnings, $result['warnings']);
        $errors = array_merge($errors, $result['errors']);

        if ($result['skip']) {
            $summary['skipped_files']++;
            continue;
        }

        if (empty($result['errors'])) {
            $records[] = $result['data'];
        }
    }

    $summary['preview_records'] = count($records);
    return [
        'records' => $records,
        'warnings' => $warnings,
        'errors' => $errors,
        'summary' => $summary,
    ];
}

function getFacebookPreviewRecordsFromPost()
{
    $records = [];
    $postedRecords = isset($_POST['fb_records']) && is_array($_POST['fb_records']) ? $_POST['fb_records'] : [];

    foreach ($postedRecords as $record) {
        $records[] = [
            'source_file_name' => normalizeImportText(isset($record['source_file_name']) ? $record['source_file_name'] : ''),
            'source_account_id' => normalizeImportText(isset($record['source_account_id']) ? $record['source_account_id'] : ''),
            'source_payment_method' => normalizeImportText(isset($record['source_payment_method']) ? $record['source_payment_method'] : ''),
            'source_reference_number' => normalizeImportText(isset($record['source_reference_number']) ? $record['source_reference_number'] : ''),
            'source_status' => normalizeImportText(isset($record['source_status']) ? $record['source_status'] : ''),
            'meta_acc' => normalizeImportText(isset($record['meta_acc']) ? $record['meta_acc'] : ''),
            'transaction_id' => normalizeImportText(isset($record['transaction_id']) ? $record['transaction_id'] : ''),
            'payment_date' => normalizeImportText(isset($record['payment_date']) ? $record['payment_date'] : ''),
            'pic' => normalizeImportText(isset($record['pic']) ? $record['pic'] : ''),
            'topup_amt' => normalizeImportText(isset($record['topup_amt']) ? $record['topup_amt'] : ''),
            'remark' => normalizeImportText(isset($record['remark']) ? $record['remark'] : ''),
        ];
    }

    return $records;
}

function getFacebookImportSummaryFromPost()
{
    return [
        'processed_files' => (int) (isset($_POST['fb_import_summary']['processed_files']) ? $_POST['fb_import_summary']['processed_files'] : 0),
        'preview_records' => (int) (isset($_POST['fb_import_summary']['preview_records']) ? $_POST['fb_import_summary']['preview_records'] : 0),
        'skipped_files' => (int) (isset($_POST['fb_import_summary']['skipped_files']) ? $_POST['fb_import_summary']['skipped_files'] : 0),
    ];
}

function validateFacebookPreviewRecords($records, &$errors, $metaAccounts, $userOptions, $financeConnect)
{
    if (empty($records)) {
        $errors[] = 'No Facebook Ads receipt is available for insert.';
        return;
    }

    $transactionIds = [];

    foreach ($records as $index => $record) {
        $rowLabel = 'Facebook receipt #' . ($index + 1);

        if ($record['source_status'] !== 'Paid') {
            $errors[] = $rowLabel . ' is not marked as Paid.';
        }

        if ($record['meta_acc'] === '' || !isset($metaAccounts[$record['meta_acc']])) {
            $errors[] = $rowLabel . ': Meta Account is required.';
        }

        if ($record['transaction_id'] === '') {
            $errors[] = $rowLabel . ': Transaction ID is required.';
        } else {
            if (isset($transactionIds[$record['transaction_id']])) {
                $errors[] = $rowLabel . ': Duplicate Transaction ID found in the current import batch.';
            }
            $transactionIds[$record['transaction_id']] = true;

            if (isDuplicateRecord('transactionID', $record['transaction_id'], FB_ADS_TOPUP, $financeConnect, '')) {
                $errors[] = $rowLabel . ': Duplicate Transaction ID found in Facebook Ads Top Up Transaction.';
            }
        }

        if ($record['payment_date'] === '' || parseFacebookReceiptDate($record['payment_date']) === '') {
            $errors[] = $rowLabel . ': Payment date is invalid.';
        }

        if ($record['pic'] === '' || !isset($userOptions[$record['pic']])) {
            $errors[] = $rowLabel . ': Person In Charge is required.';
        }

        if ($record['topup_amt'] === '' || !is_numeric($record['topup_amt'])) {
            $errors[] = $rowLabel . ': Amount must be a valid number.';
        }

    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>
    <div class="page-load-cover">
        <div class="container-fluid mt-3 mb-5 d-flex justify-content-center">
            <div class="col-12 col-md-11">
                <div class="row mb-3">
                    <p>
                        <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <a href="<?= $redirect_page ?>"><?= $pageTitle ?></a>
                        <?php if ($module === 'shopee_ads_topup') { ?>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            Shopee Ads Top Up Import
                        <?php } else if ($module === 'fb_ads_topup') { ?>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            Facebook Ads Top Up Import
                        <?php } else if ($module === 'shopee_order_req') { ?>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            Shopee Order Request Import
                        <?php } ?>
                    </p>
                </div>

                <?php if ($module === 'shopee_ads_topup') { ?>
                    <div class="row mb-4">
                        <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                            <h2>Shopee Ads Top Up Import</h2>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shopeeRedirectPage ?>">Back To Transaction Table</a>
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>">Back To Shortcuts</a>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($importErrors)) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($importErrors as $error) { ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php if (!empty($importWarnings)) { ?>
                        <div class="alert alert-warning" role="alert">
                            <?php foreach ($importWarnings as $warning) { ?>
                                <div><?= htmlspecialchars($warning) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 1: Upload Shopee HTML</h5>
                            <form method="post" enctype="multipart/form-data">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Shopee Seller Centre HTML File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".html,.htm" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseShopeeAdsTopup">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($previewData) && !empty($previewData['order_id'])) { ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Step 2: Preview And Edit Before Insert</h5>
                                <form method="post">
                                    <input type="hidden" name="source_shop_name" value="<?= htmlspecialchars($previewData['source_shop_name']) ?>">
                                    <input type="hidden" name="source_currency" value="<?= htmlspecialchars($previewData['source_currency']) ?>">
                                    <input type="hidden" name="source_payment_method" value="<?= htmlspecialchars($previewData['source_payment_method']) ?>">
                                    <input type="hidden" name="importWarnings" value="<?= htmlspecialchars(implode("\n", $importWarnings)) ?>">

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="order_id">Order ID<span class="requireRed">*</span></label>
                                            <input class="form-control" type="text" id="order_id" name="order_id" value="<?= htmlspecialchars($previewData['order_id']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="payment_date">Payment Date<span class="requireRed">*</span></label>
                                            <input class="form-control" type="datetime-local" id="payment_date" name="payment_date" value="<?= htmlspecialchars(formatDatetimeLocalValue($previewData['payment_date'])) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="shopee_acc">Shopee Account<span class="requireRed">*</span></label>
                                            <select class="form-select" id="shopee_acc" name="shopee_acc" required>
                                                <option value="">Select Account</option>
                                                <?php foreach ($shopeeAccounts as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['shopee_acc'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="currency">Currency<span class="requireRed">*</span></label>
                                            <select class="form-select" id="currency" name="currency" required>
                                                <option value="">Select Currency</option>
                                                <?php foreach ($currencyUnits as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['currency'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="pay_meth">Payment Method<span class="requireRed">*</span></label>
                                            <select class="form-select" id="pay_meth" name="pay_meth" required>
                                                <option value="">Select Payment Method</option>
                                                <?php foreach ($paymentMethods as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['pay_meth'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="topup_amt">Top-up Amount<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="topup_amt" name="topup_amt" value="<?= htmlspecialchars($previewData['topup_amt']) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="subtotal">Subtotal<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="subtotal" name="subtotal" value="<?= htmlspecialchars($previewData['subtotal']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="gst">GST / Tax<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="gst" name="gst" value="<?= htmlspecialchars($previewData['gst']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="remark">Remark</label>
                                            <textarea class="form-control" id="remark" name="remark" rows="2"><?= htmlspecialchars($previewData['remark']) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                        <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertShopeeAdsTopup">
                                            <i class="fa-solid fa-database"></i> Insert
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else if ($module === 'fb_ads_topup') { ?>
                    <div class="row mb-4">
                        <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                            <h2>Facebook Ads Top Up Import</h2>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $facebookRedirectPage ?>">Back To Transaction Table</a>
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>">Back To Shortcuts</a>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($importErrors)) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($importErrors as $error) { ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <?php if (!empty($importWarnings)) { ?>
                        <div class="alert alert-warning" role="alert">
                            <?php foreach ($importWarnings as $warning) { ?>
                                <div><?= htmlspecialchars($warning) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 1: Upload Facebook Ads PDF Or ZIP</h5>
                            <p class="text-muted mb-3">Upload a single PDF receipt or a ZIP file containing multiple PDF receipts. Only receipts with payment status Paid will be prepared for import.</p>
                            <form method="post" enctype="multipart/form-data">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Facebook Ads Receipt File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".pdf,.zip" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseFacebookAdsTopup">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($facebookPreviewRecords)) { ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                    <h5 class="card-title mb-0">Step 2: Preview And Edit Before Insert</h5>
                                    <div class="text-muted">
                                        Files processed: <?= (int) $facebookImportSummary['processed_files'] ?> |
                                        Ready to import: <?= (int) $facebookImportSummary['preview_records'] ?> |
                                        Skipped: <?= (int) $facebookImportSummary['skipped_files'] ?>
                                    </div>
                                </div>

                                <form method="post">
                                    <input type="hidden" name="importWarnings" value="<?= htmlspecialchars(implode("\n", $importWarnings)) ?>">
                                    <input type="hidden" name="fb_import_summary[processed_files]" value="<?= (int) $facebookImportSummary['processed_files'] ?>">
                                    <input type="hidden" name="fb_import_summary[preview_records]" value="<?= (int) $facebookImportSummary['preview_records'] ?>">
                                    <input type="hidden" name="fb_import_summary[skipped_files]" value="<?= (int) $facebookImportSummary['skipped_files'] ?>">

                                    <?php foreach ($facebookPreviewRecords as $index => $record) { ?>
                                        <div class="border rounded p-3 mb-4">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                                <h6 class="mb-0">Receipt <?= $index + 1 ?>: <?= htmlspecialchars($record['source_file_name']) ?></h6>
                                                <span class="badge bg-success">Paid</span>
                                            </div>

                                            <input type="hidden" name="fb_records[<?= $index ?>][source_file_name]" value="<?= htmlspecialchars($record['source_file_name']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_account_id]" value="<?= htmlspecialchars($record['source_account_id']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_payment_method]" value="<?= htmlspecialchars($record['source_payment_method']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_reference_number]" value="<?= htmlspecialchars($record['source_reference_number']) ?>">
                                            <input type="hidden" name="fb_records[<?= $index ?>][source_status]" value="<?= htmlspecialchars($record['source_status']) ?>">

                                            <div class="row mb-3">
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label">Detected Meta Account ID</label>
                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($record['source_account_id']) ?>" readonly>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label">Detected Payment Method</label>
                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($record['source_payment_method']) ?>" readonly>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label">Detected Reference Number</label>
                                                    <input class="form-control" type="text" value="<?= htmlspecialchars($record['source_reference_number']) ?>" readonly>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="fb_meta_acc_<?= $index ?>">Meta Account<span class="requireRed">*</span></label>
                                                    <select class="form-select <?= $record['meta_acc'] === '' ? 'warning_input' : '' ?>" id="fb_meta_acc_<?= $index ?>" name="fb_records[<?= $index ?>][meta_acc]" required>
                                                        <option value="">Select Meta Account</option>
                                                        <?php foreach ($metaAccounts as $id => $option) { ?>
                                                            <option value="<?= htmlspecialchars($id) ?>" <?= $record['meta_acc'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label" for="fb_transaction_id_<?= $index ?>">Transaction ID<span class="requireRed">*</span></label>
                                                    <input class="form-control" type="text" id="fb_transaction_id_<?= $index ?>" name="fb_records[<?= $index ?>][transaction_id]" value="<?= htmlspecialchars($record['transaction_id']) ?>" required>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label" for="fb_payment_date_<?= $index ?>">Invoice / Payment Date<span class="requireRed">*</span></label>
                                                    <input class="form-control" type="date" id="fb_payment_date_<?= $index ?>" name="fb_records[<?= $index ?>][payment_date]" value="<?= htmlspecialchars(formatImportDateOnly($record['payment_date'])) ?>" required>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label" for="fb_pic_<?= $index ?>">Person In Charge<span class="requireRed">*</span></label>
                                                    <select class="form-select" id="fb_pic_<?= $index ?>" name="fb_records[<?= $index ?>][pic]" required>
                                                        <option value="">Select Person In Charge</option>
                                                        <?php foreach ($userOptions as $id => $name) { ?>
                                                            <option value="<?= htmlspecialchars($id) ?>" <?= $record['pic'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4">
                                                    <label class="form-label" for="fb_topup_amt_<?= $index ?>">Amount<span class="requireRed">*</span></label>
                                                    <input class="form-control" type="number" step="0.01" id="fb_topup_amt_<?= $index ?>" name="fb_records[<?= $index ?>][topup_amt]" value="<?= htmlspecialchars($record['topup_amt']) ?>" required>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <label class="form-label" for="fb_remark_<?= $index ?>">Remark</label>
                                                    <textarea class="form-control" id="fb_remark_<?= $index ?>" name="fb_records[<?= $index ?>][remark]" rows="3"><?= htmlspecialchars($record['remark']) ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                        <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertFacebookAdsTopup">
                                            <i class="fa-solid fa-database"></i> Insert All
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php } ?>
                
                <?php } else if ($module === 'shopee_order_req') { ?>
                    <div class="row mb-4">
                        <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                            <h2>Shopee Order Request Import</h2>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shopeeOrderRedirectPage ?>">Back To Transaction Table</a>
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>">Back To Shortcuts</a>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($importErrors)) { ?>
                        <div class="alert alert-danger" role="alert">
                            <?php foreach ($importErrors as $error) { ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 1: Upload Shopee Order HTML</h5>
                            <form method="post" enctype="multipart/form-data">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Shopee Order Details HTML File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".html,.htm" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" name="actionBtn" value="parseShopeeOrderReq">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!empty($previewData) && !empty($previewData['order_id'])) { ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Step 2: Preview And Edit Before Insert</h5>
                                <form method="post">
                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="order_id">Order ID<span class="requireRed">*</span></label>
                                            <input class="form-control" type="text" id="order_id" name="order_id" value="<?= htmlspecialchars($previewData['order_id']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Detected SKU / Item Code</label>
                                            <input class="form-control" type="text" value="<?= htmlspecialchars($previewData['sku']) ?>" readonly>
                                            <input type="hidden" name="sku" value="<?= htmlspecialchars($previewData['sku']) ?>">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="package_id">Package<span class="requireRed">*</span></label>
                                            <?php if ($previewData['missing_sku']) { ?>
                                                <div class="text-danger fw-bold mb-1" style="font-size:12px;"><i class="fa-solid fa-circle-exclamation"></i> Auto-match failed. Please select manually.</div>
                                            <?php } ?>
                                            <select class="form-select <?= $previewData['missing_sku'] ? 'border-danger' : '' ?>" id="package_id" name="package_id" required>
                                                <option value="">Select Package</option>
                                                <?php foreach ($pkgOptions as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= $previewData['package_id'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php if ($previewData['missing_sku']) { ?>
                                                <a href="<?= $SITEURL ?>/package.php?act=I" target="_blank" class="btn btn-sm btn-outline-danger mt-2">Add New Package</a>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="shopee_acc">Shopee Account<span class="requireRed">*</span></label>
                                            <select class="form-select" id="shopee_acc" name="shopee_acc" required>
                                                <option value="">Select Account</option>
                                                <?php foreach ($shopeeAccounts as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['shopee_acc']) && $previewData['shopee_acc'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="currency">Currency<span class="requireRed">*</span></label>
                                            <select class="form-select" id="currency" name="currency" required>
                                                <option value="">Select Currency</option>
                                                <?php foreach ($currencyUnits as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['currency']) && $previewData['currency'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php if (!empty($previewData['source_currency'])) { ?>
                                                <small class="text-muted">Detected: <?= htmlspecialchars($previewData['source_currency']) ?></small>
                                            <?php } ?>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="brand">Brand<span class="requireRed">*</span></label>
                                            <select class="form-select" id="brand" name="brand" required>
                                                <option value="">Select Brand</option>
                                                <?php foreach ($brandOptions as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['brand']) && $previewData['brand'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="buyer">Shopee Buyer Username</label>
                                            <select class="form-select" id="buyer" name="buyer">
                                                <option value="">Select Buyer (Optional)</option>
                                                <?php foreach ($shopeeBuyers as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['buyer']) && $previewData['buyer'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php if (!empty($previewData['source_buyer_username'])) { ?>
                                                <small class="text-muted">Detected: <?= htmlspecialchars($previewData['source_buyer_username']) ?></small>
                                            <?php } ?>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="buyer_pay_meth">Buyer Payment Method</label>
                                            <select class="form-select" id="buyer_pay_meth" name="buyer_pay_meth">
                                                <option value="">Select Payment Method</option>
                                                <?php foreach ($shopeePayMethods as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['buyer_pay_meth']) && $previewData['buyer_pay_meth'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="pic">Person In Charge<span class="requireRed">*</span></label>
                                            <select class="form-select" id="pic" name="pic" required>
                                                <option value="">Select PIC</option>
                                                <?php foreach ($userOptions as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= (isset($previewData['pic']) ? $previewData['pic'] : USER_ID) == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="product_price">Product Price (RM)<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="product_price" name="product_price" value="<?= htmlspecialchars($previewData['product_price']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="voucher">Voucher</label>
                                            <input class="form-control" type="number" step="0.01" id="voucher" name="voucher" value="<?= htmlspecialchars(isset($previewData['voucher']) ? $previewData['voucher'] : '0.00') ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="act_shipping_fee">Actual Shipping Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="act_shipping_fee" name="act_shipping_fee" value="<?= htmlspecialchars(isset($previewData['act_shipping_fee']) ? $previewData['act_shipping_fee'] : '0.00') ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="service_fee">Service Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="service_fee" name="service_fee" value="<?= htmlspecialchars(isset($previewData['service_fee']) ? $previewData['service_fee'] : '0.00') ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="trans_fee">Transaction Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="trans_fee" name="trans_fee" value="<?= htmlspecialchars(isset($previewData['trans_fee']) ? $previewData['trans_fee'] : '0.00') ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="ams_fee">AMS / Commission Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="ams_fee" name="ams_fee" value="<?= htmlspecialchars(isset($previewData['ams_fee']) ? $previewData['ams_fee'] : '0.00') ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="fees">Fees & Charges</label>
                                            <input class="form-control" type="number" step="0.01" id="fees" name="fees" value="<?= htmlspecialchars(isset($previewData['fees']) ? $previewData['fees'] : '0.00') ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="final_amt">Final Amount</label>
                                            <input class="form-control" type="number" step="0.01" id="final_amt" name="final_amt" value="<?= htmlspecialchars(isset($previewData['final_amt']) ? $previewData['final_amt'] : '0.00') ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-8">
                                            <label class="form-label" for="remark">Remark</label>
                                            <input class="form-control" type="text" id="remark" name="remark" value="<?= htmlspecialchars(isset($previewData['remark']) ? $previewData['remark'] : '') ?>">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Order Status</label>
                                            <input class="form-control text-primary fw-bold" type="text" value="<?= htmlspecialchars(getOrderStatusLabel(isset($previewData['order_status_val']) ? $previewData['order_status_val'] : 'P')) ?>" readonly>
                                            <input type="hidden" name="order_status_val" value="<?= htmlspecialchars($previewData['order_status_val']) ?>">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center gap-2 flex-wrap mt-4">
                                        <?php if ($previewData['missing_sku']) { ?>
                                            <div class="alert alert-danger mb-0">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Cannot proceed. Please add the missing Package to the CMS, then re-upload the HTML file.
                                            </div>
                                        <?php } else { ?>
                                            <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertShopeeOrderReq">
                                                <i class="fa-solid fa-database"></i> Insert
                                            </button>
                                        <?php } ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php } ?>

                <?php } else { ?>
                    <div class="row mb-4">
                        <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                            <h2><?= $pageTitle ?></h2>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">Shopee Ads Top Up Import</h5>
                                    <p class="card-text">Upload a Shopee Seller Centre order detail HTML file, review the parsed values, correct anything that does not match, then insert the transaction into the CMS.</p>
                                    <div class="mt-auto">
                                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>?module=shopee_ads_topup">
                                            <i class="fa-solid fa-file-import"></i> Open Import
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">Facebook Ads Top Up Import</h5>
                                    <p class="card-text">Upload a Facebook Ads PDF receipt or a ZIP of receipts, preview the parsed records, fix anything that needs adjustment, and insert only Paid transactions into the CMS.</p>
                                    <div class="mt-auto">
                                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>?module=fb_ads_topup">
                                            <i class="fa-solid fa-file-import"></i> Open Import
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">Shopee Order Import</h5>
                                    <p class="card-text">Upload a Shopee Order HTML page to automatically map Order details and SKU item codes. Edit parameters manually before confirming.</p>
                                    <div class="mt-auto">
                                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirect_page ?>?module=shopee_order_req">
                                            <i class="fa-solid fa-file-import"></i> Open Import
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">Stock Order Request PDF Import</h5>
                                    <p class="card-text">Upload one invoice PDF or a ZIP of PDFs, auto-analyze invoice fields and package matches, review/edit all rows, then insert requests in bulk.</p>
                                    <div class="mt-auto">
                                        <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $SITEURL ?>/finance/stock_order_request_import.php">
                                            <i class="fa-solid fa-file-import"></i> Open Import
                                        </a>
                                    </div>
                                </div>
                            </div>
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