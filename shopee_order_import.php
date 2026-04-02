<?php
$currentPagePin = 0;
if (!defined('IMPORT_FORCE_MODULE')) {
    define('IMPORT_FORCE_MODULE', 'shopee_order_req');
}

$parentPageTitle = "Shopee Order Request";
$pageTitle = '';
$shopeeOrderPinGroupIds = array(130, 129, 128);

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$pinAccess = array();
$parentPagePinGroupId = 0;
$resolvedParentPageTitle = '';
$fallbackParentPageTitle = '';
foreach ($shopeeOrderPinGroupIds as $pinGroupId) {
    $candidateName = getPinGroupNameById($connect, $pinGroupId);
    if ($fallbackParentPageTitle === '' && $candidateName !== '') {
        $fallbackParentPageTitle = $candidateName;
    }

    $candidateAccess = checkPinByGroupId($connect, $pinGroupId);
    if (is_array($candidateAccess) && isActionAllowed('Import', $candidateAccess)) {
        $pinAccess = $candidateAccess;
        $parentPagePinGroupId = (int) $pinGroupId;
        if ($candidateName !== '') {
            $resolvedParentPageTitle = $candidateName;
        }
        break;
    }
}

if ($resolvedParentPageTitle === '' && $fallbackParentPageTitle !== '') {
    $resolvedParentPageTitle = $fallbackParentPageTitle;
}
if ($resolvedParentPageTitle !== '') {
    $parentPageTitle = $resolvedParentPageTitle;
}
$breadcrumbTitle = $parentPageTitle . ' Import';
$pageTitle = $breadcrumbTitle;
$pageHeading = $parentPageTitle . ' Import';

if (!is_array($pinAccess) || count($pinAccess) === 0 || !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

$module = 'shopee_order_req';
$redirect_page = $SITEURL . '/common_import.php';
$shopeeOrderRedirectPage = $SITEURL . '/shopee/shopee_processing_order.php';
if ($parentPagePinGroupId === 130) {
    $shopeeOrderRedirectPage = $SITEURL . '/shopee/shopee_order_req_table.php';
} else if ($parentPagePinGroupId === 129) {
    $shopeeOrderRedirectPage = $SITEURL . '/shopee/shopee_verify.php';
}

$action = post('actionBtn');
$allowedActions = ['parseShopeeOrderReq', 'insertShopeeOrderReq'];
if ($action !== '' && !in_array($action, $allowedActions, true)) {
    $action = '';
}
if ($action !== '' && !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}
$importErrors = [];
$importWarnings = [];
$previewData = [];


$shopeeAccounts = getImportOptionList(SHOPEE_ACC, 'name', $finance_connect);
$currencyUnits = getImportOptionList(CUR_UNIT, 'unit', $connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
$shopeePayMethods = getImportOptionList(PAY_MTHD_SHOPEE, 'name', $finance_connect);
$brandOptions = getImportOptionList(BRAND, 'name', $connect);
$pkgOptions = getImportOptionList(PKG, 'name', $connect);
$shopeeBuyers = getImportOptionList(SHOPEE_CUST_INFO, 'buyer_username', $finance_connect);
if ($action === 'parseShopeeOrderReq') { // NEW: Shopee Order HTML Parsing
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

            // Use DOM parsing for better extraction
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);

            $order_id = preg_match('/(?:Order ID|Order SN|Order No)[^A-Z0-9]*([A-Z0-9]{12,24})/i', $cleanText, $m) ? strtoupper(trim($m[1])) : '';
            if ($order_id === '') {
                $order_id = preg_match('/sale\/order\/([A-Z0-9]{12,24})/i', $html, $m) ? strtoupper(trim($m[1])) : '';
            }

            $sku = preg_match('/(?:SKU|Item Code)[^A-Za-z0-9]*([A-Za-z0-9\-_]+)/i', $cleanText, $m) ? trim($m[1]) : '';

            // Extract product name from HTML for better package matching
            $productName = '';
            // Try to get product name from product title elements common in Shopee order pages
            $productNameNode = getNodeText($xpath, "//*[contains(@class,'product-name') or contains(@class,'item-name') or contains(@class,'product-title')]");
            if ($productNameNode === '') {
                // Fallback: try to extract from text near SKU
                if (preg_match('/Product\(s\)[^:]*:?\s*(.+?)(?:SKU|Item Code|Variation)/is', $cleanText, $pnm)) {
                    $productName = normalizeImportText($pnm[1]);
                }
            } else {
                $productName = $productNameNode;
            }

            $paymentInfoPairs = collectShopeeOrderAmountPairsFromDom($xpath, "//*[@data-testid='odp-order-payment']//*[contains(@class,'income-item')]");
            $buyerPaymentPairs = collectShopeeOrderAmountPairsFromDom($xpath, "//*[@data-testid='odp-buyer-payment']//*[contains(@class,'income-item')]");

            // Fallback to full-page scan if section-specific selectors are not available.
            if (empty($paymentInfoPairs)) {
                $paymentInfoPairs = collectShopeeOrderAmountPairsFromDom($xpath);
            }
            if (empty($buyerPaymentPairs)) {
                $buyerPaymentPairs = $paymentInfoPairs;
            }

            $product_price = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Product Price', 'Deal Price', 'Merchandise Subtotal']);
            if ($product_price === '') {
                $product_price = parseShopeeOrderAmountFromPairs($buyerPaymentPairs, ['Product Price', 'Merchandise Subtotal']);
            }
            if ($product_price === '') {
                $product_price = parseShopeeOrderAmountByLabels($cleanText, ['Product Price', 'Deal Price', 'Merchandise Subtotal']);
            }

            $voucher = parseShopeeOrderAmountFromPairs($buyerPaymentPairs, ['Vouchers & Rebates', 'Shopee Voucher', 'Seller Voucher', 'Shop voucher']);
            if ($voucher === '') {
                $voucher = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Vouchers & Rebates', 'Shopee Voucher', 'Seller Voucher', 'Shop voucher']);
            }
            if ($voucher === '') {
                $voucher = parseShopeeOrderAmountByLabels($cleanText, ['Vouchers & Rebates', 'Shopee Voucher', 'Seller Voucher', 'Shop voucher']);
            }

            $actShippingFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Estimated Shipping Subtotal', 'Shipping Subtotal', 'Estimated Shipping Fee Charged by Logistic Provider', 'Shipping Fee Paid by Buyer']);
            if ($actShippingFee === '') {
                $actShippingFee = parseShopeeOrderAmountByLabels($cleanText, ['Shipping Subtotal', 'Estimated Shipping Subtotal', 'Estimated Shipping Fee Charged by Logistic Provider', 'Shipping Fee Paid by Buyer']);
            }

            // CMS mapping requirement:
            // service_fee <- Commission Fee (Incl.SST)
            $serviceFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Commission Fee', 'Service Fee']);
            if ($serviceFee === '') {
                $serviceFee = parseShopeeOrderAmountByLabels($cleanText, ['Commission Fee', 'Service Fee']);
            }

            // CMS mapping requirement:
            // trans_fee <- Service Fee
            $transactionFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Service Fee', 'Transaction Fee']);
            if ($transactionFee === '') {
                $transactionFee = parseShopeeOrderAmountByLabels($cleanText, ['Service Fee', 'Transaction Fee']);
            }

            // CMS mapping requirement:
            // ams_fee <- Fees & Charges
            $amsFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Fees & Charges', 'Commission Fee', 'Saver Programme Fee']);
            if ($amsFee === '') {
                $amsFee = parseShopeeOrderAmountByLabels($cleanText, ['Fees & Charges', 'Commission Fee', 'Saver Programme Fee']);
            }

            // CMS mapping requirement:
            // fees <- Seller Paid Shipping Fee SST
            $fees = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Seller Paid Shipping Fee SST', 'Fees & Charges']);
            if ($fees === '') {
                $fees = parseShopeeOrderAmountByLabels($cleanText, ['Seller Paid Shipping Fee SST', 'Fees & Charges']);
            }

            $finalAmt = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Estimated Order Income', 'Order Income', 'Final Amount']);
            if ($finalAmt === '') {
                $finalAmt = parseShopeeOrderAmountByLabels($cleanText, ['Order Income', 'Estimated Order Income', 'Final Amount']);
            }

            $detectedCurrency = detectShopeeOrderCurrency($cleanText);
            $currencyFallbacks = $detectedCurrency === 'RM' ? ['MYR'] : [];
            $currencyId = resolveImportOptionId($detectedCurrency, $currencyUnits, $currencyFallbacks);

            $buyerUsername = extractShopeeBuyerUsername($html, $cleanText);
            $buyerId = resolveImportOptionId($buyerUsername, $shopeeBuyers);

            // Extract buyer payment method from HTML
            $detectedPayMethod = '';
            $payMethodNode = extractValueFromTableLabel($xpath, ['Payment Method']);
            if ($payMethodNode === '') {
                // Fallback: regex from clean text
                if (preg_match('/Payment\s*Method\s*[:\s]*([A-Za-z0-9\s\-\.\/]+?)(?:\s*(?:Shipping|Voucher|Fees|Order|Merchandise|$))/i', $cleanText, $pm)) {
                    $detectedPayMethod = normalizeImportText($pm[1]);
                }
            } else {
                $detectedPayMethod = $payMethodNode;
            }
            $payMethodId = '';
            if ($detectedPayMethod !== '') {
                $payMethodId = resolveImportOptionId($detectedPayMethod, $shopeePayMethods);
            }

            // Extract shop name / Shopee account from HTML
            $detectedShopName = '';
            $shopHeaderNode = getNodeText($xpath, "//div[contains(@class,'order-detail-info')]//div[contains(@class,'header')]");
            if ($shopHeaderNode !== '') {
                $detectedShopName = extractValueAfterColon($shopHeaderNode);
            }
            if ($detectedShopName === '') {
                // Fallback: try to find shop name in text
                if (preg_match('/Shop\s*(?:Name)?\s*[:\s]+([^\n,]+)/i', $cleanText, $sn)) {
                    $detectedShopName = normalizeImportText($sn[1]);
                }
            }
            $shopeeAccId = '';
            if ($detectedShopName !== '') {
                $shopeeAccId = resolveImportOptionId($detectedShopName, $shopeeAccounts);
            }

            // Detect order status from HTML (completed orders should show as Completed)
            $detectedOrderStatus = 'P'; // Default: Pending To Pack
            $detectedOrderStatusLabel = 'Pending To Pack';
            // Check for Completed timeline
            $completedNode = getNodeText($xpath, "//div[contains(@class,'timeline-item')][.//div[contains(@class,'title') and normalize-space()='Completed']]");
            if ($completedNode !== '') {
                $detectedOrderStatus = 'C';
                $detectedOrderStatusLabel = 'Completed';
            } else {
                // Fallback: check text for status indicators
                if (preg_match('/\bOrder\s+Status\s*[:\s]*(Completed|Shipped|Delivered|To\s*Ship|To\s*Receive|Processing)/i', $cleanText, $osm)) {
                    $statusText = strtolower(trim($osm[1]));
                    if ($statusText === 'completed' || $statusText === 'delivered') {
                        $detectedOrderStatus = 'C';
                        $detectedOrderStatusLabel = 'Completed';
                    } else if ($statusText === 'shipped' || $statusText === 'to receive') {
                        $detectedOrderStatus = 'SP';
                        $detectedOrderStatusLabel = 'SHIP PROCESSING (Warehouse)';
                    }
                }
                // Also check for "Order Income" label which indicates a completed order
                if ($detectedOrderStatus === 'P' && preg_match('/\bOrder\s+Income\b/i', $cleanText)) {
                    $detectedOrderStatus = 'C';
                    $detectedOrderStatusLabel = 'Completed';
                }
            }

            if ($order_id === '') {
                $importErrors[] = 'Order ID could not be detected. Please ensure you uploaded the correct Shopee Order Details HTML file.';
            }

            $pkg_id = '';
            $missing_sku = false;
            
            // Safely Validate the SKU from the CMS Package DB
            if (!empty($sku)) {
                // Escape SKU to prevent SQL injection when building the condition string
                $safeSku = mysqli_real_escape_string($connect, $sku);
                // Search by item_code OR name
                $pkgResult = getData('*', "item_code='$safeSku' OR name='$safeSku'", '', PKG, $connect); 
                if ($pkgResult && $pkgResult->num_rows > 0) {
                    $pkg_row = $pkgResult->fetch_assoc();
                    $pkg_id = $pkg_row['id'];
                } else {
                    // Try partial/fuzzy match: item_code LIKE or name LIKE
                    $pkgResult = getData('*', "item_code LIKE '%$safeSku%' OR name LIKE '%$safeSku%'", 'LIMIT 1', PKG, $connect);
                    if ($pkgResult && $pkgResult->num_rows > 0) {
                        $pkg_row = $pkgResult->fetch_assoc();
                        $pkg_id = $pkg_row['id'];
                    } else {
                        $missing_sku = true;
                    }
                }
            } else {
                $missing_sku = true;
            }

            // If still missing, try matching using product name
            if ($missing_sku && !empty($productName)) {
                $safeProductName = mysqli_real_escape_string($connect, $productName);
                $pkgResult = getData('*', "name LIKE '%$safeProductName%'", 'LIMIT 1', PKG, $connect);
                if ($pkgResult && $pkgResult->num_rows > 0) {
                    $pkg_row = $pkgResult->fetch_assoc();
                    $pkg_id = $pkg_row['id'];
                    $missing_sku = false;
                }
            }

            $previewData = [
                'order_id' => $order_id,
                'sku' => $sku,
                'package_id' => $pkg_id,
                'product_price' => $product_price !== '' ? $product_price : '0.00',
                'order_status' => $detectedOrderStatusLabel,
                'order_status_val' => $detectedOrderStatus,
                'missing_sku' => $missing_sku,
                'shopee_acc' => $shopeeAccId,
                'source_shopee_acc' => $detectedShopName,
                'currency' => $currencyId,
                'source_currency' => $detectedCurrency,
                'brand' => '',
                'buyer' => $buyerId,
                'source_buyer_username' => $buyerUsername,
                'buyer_pay_meth' => $payMethodId,
                'source_buyer_pay_meth' => $detectedPayMethod,
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
            if ($detectedShopName !== '' && $shopeeAccId === '') {
                $importWarnings[] = 'Shopee Account detected as "' . $detectedShopName . '" but not matched. Please select manually.';
            }
            if ($detectedPayMethod !== '' && $payMethodId === '') {
                $importWarnings[] = 'Buyer Payment Method detected as "' . $detectedPayMethod . '" but not matched. Please select manually.';
            }
        }
    }
} else if ($action === 'insertShopeeOrderReq') { // NEW: Shopee Order Insert
    $module = 'shopee_order_req';

    $resolveMultiIds = function ($hiddenInput, $nameInput, $tableName) use ($connect) {
        $resolved = array();
        if (!is_array($hiddenInput)) $hiddenInput = explode(',', (string) $hiddenInput);
        foreach ($hiddenInput as $idVal) {
            $idVal = trim((string) $idVal);
            if ($idVal !== '' && ctype_digit($idVal) && (int) $idVal > 0) {
                $resolved[] = (string) ((int) $idVal);
            }
        }
        if (!is_array($nameInput)) $nameInput = array($nameInput);
        foreach ($nameInput as $nameVal) {
            $nameVal = trim((string) $nameVal);
            if ($nameVal === '') continue;
            $escapedName = mysqli_real_escape_string($connect, $nameVal);
            $nameRst = getData('id', "name = '$escapedName'", 'LIMIT 1', $tableName, $connect);
            if ($nameRst && $nameRst->num_rows > 0) {
                $nameRow = $nameRst->fetch_assoc();
                $resolvedId = (int) $nameRow['id'];
                if ($resolvedId > 0) $resolved[] = (string) $resolvedId;
            }
        }
        return implode(',', array_values(array_unique($resolved)));
    };

    $packageIdsStr = $resolveMultiIds(
        isset($_POST['sor_pkg_hidden']) ? $_POST['sor_pkg_hidden'] : array(),
        isset($_POST['sor_pkg']) ? $_POST['sor_pkg'] : array(),
        PKG
    );

    $brandIdsStr = $resolveMultiIds(
        isset($_POST['sor_brand_hidden']) ? $_POST['sor_brand_hidden'] : array(),
        isset($_POST['sor_brand']) ? $_POST['sor_brand'] : array(),
        BRAND
    );
    
    $orderStatusVal = postSpaceFilter('order_status_val');
    if ($orderStatusVal === '') $orderStatusVal = 'P';
    
    $previewData = [
        'order_id' => postSpaceFilter('order_id'),
        'package_id' => $packageIdsStr,
        'product_price' => postSpaceFilter('product_price'),
        'order_status' => $orderStatusVal,
        'order_status_val' => $orderStatusVal,
        'sku' => isset($_POST['sku']) ? $_POST['sku'] : '',
        'missing_sku' => empty($packageIdsStr),
        'shopee_acc' => postSpaceFilter('shopee_acc'),
        'currency' => postSpaceFilter('currency'),
        'brand' => $brandIdsStr,
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
            $dataID = mysqli_insert_id($finance_connect);
            $log = [
                'log_act' => 'Import',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $query,
                'query_table' => SHOPEE_SG_ORDER_REQ,
                'newval' => 'OrderID=' . $previewData['order_id'],
                'act_msg' => USER_NAME . " imported the data [ <b> ID = " . (int) $dataID . " </b> ] from <b><i>" . SHOPEE_SG_ORDER_REQ . " Table</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo '<script>alert("Shopee Order Request imported successfully.");window.location.replace("' . $shopeeOrderRedirectPage . '");</script>';
            exit;
        } else {
            $importErrors[] = 'Database Error: ' . mysqli_error($finance_connect);
        }
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

function normalizeImportText($text)
{
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text));
}

function normalizeImportLookup($text)
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', normalizeImportText($text)));
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
        $currencyPattern = '/' . preg_quote($label, '/') . '.{0,220}?(-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*)(?!\s*%)/i';
        if (preg_match($currencyPattern, $text, $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }

        $numericPattern = '/' . preg_quote($label, '/') . '.{0,220}?(-?\s*[0-9][0-9,]*\.?[0-9]*)(?!\s*%)/i';
        if (preg_match($numericPattern, $text, $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }
    }

    return '0.00';
}

function parseShopeeOrderAmountToken($text)
{
    $normalized = normalizeImportText($text);
    if ($normalized === '') {
        return '';
    }

    if (preg_match_all('/-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*(?!\s*%)/i', $normalized, $matches) && !empty($matches[0])) {
        $candidate = end($matches[0]);
        $amount = normalizeImportAmount($candidate);
        if ($amount !== '') {
            return $amount;
        }
    }

    if (preg_match_all('/-?\s*[0-9][0-9,]*\.?[0-9]*(?!\s*%)/', $normalized, $matches) && !empty($matches[0])) {
        $candidate = end($matches[0]);
        $amount = normalizeImportAmount($candidate);
        if ($amount !== '') {
            return $amount;
        }
    }

    return '';
}

function collectShopeeOrderAmountPairsFromDom($xpath, $itemQuery = "//*[contains(@class,'income-item')]")
{
    $pairs = [];
    $items = $xpath->query($itemQuery);

    if (!$items) {
        return $pairs;
    }

    foreach ($items as $item) {
        $label = getNodeText($xpath, ".//*[contains(@class,'income-label-text')]", $item);
        if ($label === '') {
            continue;
        }

        $value = getNodeText($xpath, ".//*[contains(@class,'income-value')]", $item);
        $amount = parseShopeeOrderAmountToken($value);

        if ($amount === '') {
            $amount = parseShopeeOrderAmountToken($item->textContent);
        }

        if ($amount !== '') {
            $pairs[] = [
                'label' => $label,
                'amount' => $amount,
            ];
        }
    }

    return $pairs;
}

function parseShopeeOrderAmountFromPairs($pairs, $labels)
{
    if (empty($pairs) || empty($labels)) {
        return '';
    }

    foreach ($labels as $targetLabel) {
        $normalizedTarget = normalizeImportLookup($targetLabel);
        if ($normalizedTarget === '') {
            continue;
        }

        foreach ($pairs as $pair) {
            $normalizedLabel = normalizeImportLookup(isset($pair['label']) ? $pair['label'] : '');
            if ($normalizedLabel === '') {
                continue;
            }

            if ($normalizedLabel === $normalizedTarget || strpos($normalizedLabel, $normalizedTarget) === 0) {
                return isset($pair['amount']) ? $pair['amount'] : '';
            }
        }
    }

    return '';
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

?>

<!DOCTYPE html>
<html>

<head>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
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
                        <?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                    <div class="row mb-4">
                        <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                            <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $shopeeOrderRedirectPage ?>">Back To Shopee Order Page</a>
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
                                            <label class="form-label" id="sor_pkg_lbl">Package<span class="requireRed">*</span></label>
                                            <?php if ($previewData['missing_sku']) { ?>
                                                <div class="text-danger fw-bold mb-1" style="font-size:12px;"><i class="fa-solid fa-circle-exclamation"></i> Auto-match failed. Please add / select manually.</div>
                                            <?php } ?>
                                            <?php
                                            $selectedPkgIds = array_filter(array_map('trim', explode(',', (string) $previewData['package_id'])), 'strlen');
                                            $pkgRows = array();
                                            if (!empty($selectedPkgIds)) {
                                                foreach ($selectedPkgIds as $pkgId) {
                                                    $pkgIdInt = (int) $pkgId;
                                                    $pkgName = '';
                                                    if ($pkgIdInt > 0 && isset($pkgOptions[$pkgIdInt])) {
                                                        $pkgName = $pkgOptions[$pkgIdInt];
                                                    }
                                                    $pkgRows[] = array('id' => $pkgIdInt, 'name' => $pkgName);
                                                }
                                            }
                                            if (empty($pkgRows)) {
                                                $pkgRows[] = array('id' => '', 'name' => '');
                                            }
                                            ?>
                                            <div id="sor_pkg_container">
                                                <?php foreach ($pkgRows as $pkgIndex => $pkgRow) { ?>
                                                    <div class="input-group mb-2 sor-pkg-row autocomplete">
                                                        <input class="form-control sor-pkg-input <?= $previewData['missing_sku'] ? 'border-danger' : '' ?>" type="text" name="sor_pkg[]"
                                                            id="sor_pkg_<?php echo $pkgIndex; ?>"
                                                            data-hidden-target="sor_pkg_hidden_<?php echo $pkgIndex; ?>"
                                                            value="<?php echo htmlspecialchars($pkgRow['name']); ?>" required>
                                                        <input type="hidden" class="sor-pkg-hidden" name="sor_pkg_hidden[]"
                                                            id="sor_pkg_hidden_<?php echo $pkgIndex; ?>"
                                                            value="<?php echo htmlspecialchars((string) $pkgRow['id']); ?>">
                                                        <?php if ($pkgIndex > 0) { ?>
                                                            <button type="button" class="btn btn-outline-danger sor-remove-row-btn" data-row-type="pkg" title="Remove Package">
                                                                <i class="fa-solid fa-xmark"></i>
                                                            </button>
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="add_pkg_btn">+ Add Package</button>
                                            <?php if ($previewData['missing_sku']) { ?>
                                                <a href="<?= $SITEURL ?>/package.php?act=I" target="_blank" class="btn btn-sm btn-outline-danger mt-1">Add New Package</a>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="shopee_acc">Shopee Account<span class="requireRed">*</span></label>
                                            <select class="form-select <?= (isset($previewData['source_shopee_acc']) && $previewData['source_shopee_acc'] !== '' && (empty($previewData['shopee_acc']))) ? 'border-warning' : '' ?>" id="shopee_acc" name="shopee_acc" required>
                                                <option value="">Select Account</option>
                                                <?php foreach ($shopeeAccounts as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['shopee_acc']) && $previewData['shopee_acc'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php if (isset($previewData['source_shopee_acc']) && $previewData['source_shopee_acc'] !== '' && empty($previewData['shopee_acc'])) { ?>
                                                <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> Not Match (Detected: <?= htmlspecialchars($previewData['source_shopee_acc']) ?>). Please select manually.</small>
                                            <?php } else if (isset($previewData['source_shopee_acc']) && $previewData['source_shopee_acc'] !== '') { ?>
                                                <small class="text-muted">Detected: <?= htmlspecialchars($previewData['source_shopee_acc']) ?></small>
                                            <?php } ?>
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
                                            <label class="form-label" id="sor_brand_lbl">Brand<span class="requireRed">*</span></label>
                                            <?php
                                            $selectedBrandIds = array_filter(array_map('trim', explode(',', (string) $previewData['brand'])), 'strlen');
                                            $brandRows = array();
                                            if (!empty($selectedBrandIds)) {
                                                foreach ($selectedBrandIds as $brandId) {
                                                    $brandIdInt = (int) $brandId;
                                                    $brandName = '';
                                                    if ($brandIdInt > 0 && isset($brandOptions[$brandIdInt])) {
                                                        $brandName = $brandOptions[$brandIdInt];
                                                    }
                                                    $brandRows[] = array('id' => $brandIdInt, 'name' => $brandName);
                                                }
                                            }
                                            if (empty($brandRows)) {
                                                $brandRows[] = array('id' => '', 'name' => '');
                                            }
                                            ?>
                                            <div id="sor_brand_container">
                                                <?php foreach ($brandRows as $brandIndex => $brandRow) { ?>
                                                    <div class="input-group mb-2 sor-brand-row autocomplete">
                                                        <input class="form-control sor-brand-input" type="text" name="sor_brand[]"
                                                            id="sor_brand_<?php echo $brandIndex; ?>"
                                                            data-hidden-target="sor_brand_hidden_<?php echo $brandIndex; ?>"
                                                            value="<?php echo htmlspecialchars($brandRow['name']); ?>" required>
                                                        <input type="hidden" class="sor-brand-hidden" name="sor_brand_hidden[]"
                                                            id="sor_brand_hidden_<?php echo $brandIndex; ?>"
                                                            value="<?php echo htmlspecialchars((string) $brandRow['id']); ?>">
                                                        <?php if ($brandIndex > 0) { ?>
                                                            <button type="button" class="btn btn-outline-danger sor-remove-row-btn" data-row-type="brand" title="Remove Brand">
                                                                <i class="fa-solid fa-xmark"></i>
                                                            </button>
                                                        <?php } ?>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="add_brand_btn">+ Add Brand</button>
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
                                            <?php $payMethodNotMatched = empty($previewData['buyer_pay_meth']); ?>
                                            <select class="form-select <?= $payMethodNotMatched ? 'border-warning' : '' ?>" id="buyer_pay_meth" name="buyer_pay_meth">
                                                <option value="">Select Payment Method</option>
                                                <?php foreach ($shopeePayMethods as $id => $name) { ?>
                                                    <option value="<?= htmlspecialchars($id) ?>" <?= isset($previewData['buyer_pay_meth']) && $previewData['buyer_pay_meth'] == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php if ($payMethodNotMatched && isset($previewData['source_buyer_pay_meth']) && $previewData['source_buyer_pay_meth'] !== '') { ?>
                                                <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> Not Match (Detected: <?= htmlspecialchars($previewData['source_buyer_pay_meth']) ?>). Please select manually.</small>
                                            <?php } else if ($payMethodNotMatched) { ?>
                                                <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> Not Match (Detected: Not detected). Please select manually.</small>
                                            <?php } else if (isset($previewData['source_buyer_pay_meth']) && $previewData['source_buyer_pay_meth'] !== '') { ?>
                                                <small class="text-muted">Detected: <?= htmlspecialchars($previewData['source_buyer_pay_meth']) ?></small>
                                            <?php } ?>
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
                                            <div class="alert alert-warning mb-0">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Package was not matched automatically. Please select the correct package manually before inserting.
                                            </div>
                                        <?php } ?>
                                        <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertShopeeOrderReq">
                                            <i class="fa-solid fa-database"></i> Insert
                                        </button>
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
    <?php if ($module === 'shopee_order_req') { ?>
        var action = 'I'; // Fake action to satisfy the JS script's logic
        <?php include "js/shopee_order_req.js"; ?>
    <?php } ?>
</script>

</html>




