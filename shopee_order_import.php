<?php
$currentPagePin = 0;

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
$orderIdFieldError = '';


$shopeeAccounts = getImportOptionList(SHOPEE_ACC, 'name', $finance_connect);
$currencyUnits = getImportOptionList(CUR_UNIT, 'unit', $connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
$shopeePayMethods = getImportOptionList(PAY_MTHD_SHOPEE, 'name', $finance_connect);
$brandOptions = getImportOptionList(BRAND, 'name', $connect);
$pkgOptions = getImportOptionList(PKG, 'name', $connect);
$shopeeBuyers = getImportOptionList(SHOPEE_CUST_INFO, 'buyer_username', $finance_connect);
if ($action === 'parseShopeeOrderReq') { // Shopee Order HTML/PDF Parsing
    $module = 'shopee_order_req';

    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Shopee Order HTML or PDF file.';
    } else if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . $_FILES['import_file']['error'];
    } else if ($_FILES['import_file']['size'] > 5 * 1024 * 1024) { // 5MB limit
        $importErrors[] = 'The uploaded file exceeds the maximum allowed size of 5MB.';
    } else {
        $uploadedName = isset($_FILES['import_file']['name']) ? (string) $_FILES['import_file']['name'] : '';
        $extension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['html', 'htm', 'pdf'], true)) {
            $importErrors[] = 'Only HTML or text-based PDF files are supported.';
        } else {
            $rawContent = @file_get_contents($_FILES['import_file']['tmp_name']);

            if ($rawContent === false || (string) $rawContent === '') {
                $importErrors[] = 'The uploaded file could not be read.';
            } else if ($extension === 'pdf' && strncmp($rawContent, '%PDF-', 5) !== 0) {
                $importErrors[] = 'The uploaded file is not a valid PDF document.';
            } else if (in_array($extension, ['html', 'htm'], true) && stripos($rawContent, '<html') === false && stripos($rawContent, '<!DOCTYPE') === false && stripos($rawContent, '<body') === false) {
                $importErrors[] = 'The uploaded file is not a valid HTML document.';
            } else {
                $html = '';
                $cleanText = '';
                $xpath = null;
                $sourceTypeLabel = 'HTML';

                if ($extension === 'pdf') {
                    $sourceTypeLabel = 'PDF';
                    $cleanText = normalizeImportText(extractTextFromPdfContent($rawContent));
                    if ($cleanText === '') {
                        $importErrors[] = 'Unable to extract text from the uploaded PDF file.';
                    }
                } else {
                    $html = (string) $rawContent;
                    $cleanText = normalizeImportText(strip_tags(str_replace(['<', '>'], [' <', '> '], $html)));

                    libxml_use_internal_errors(true);
                    $dom = new DOMDocument();
                    $loaded = $dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                    libxml_clear_errors();

                    if (!$loaded) {
                        $importErrors[] = 'The uploaded file is not a valid HTML document.';
                    } else {
                        $xpath = new DOMXPath($dom);
                    }
                }
            }
        }

        if (!empty($importErrors)) {
            // Parsing pre-check failed. Errors already populated.
        } else {
            $order_id = '';
            if ($extension === 'pdf') {
                $order_id = extractPdfFieldByLabels($cleanText, ['Order ID', 'Order SN', 'Order No', 'Order Number']);
            }

            if ($order_id === '') {
                $order_id = preg_match('/(?:Order ID|Order SN|Order No|Order Number)[^A-Z0-9]*([A-Z0-9]{12,24})/i', $cleanText, $m) ? strtoupper(trim($m[1])) : '';
            }
            if ($order_id === '' && $html !== '') {
                $order_id = preg_match('/sale\/order\/([A-Z0-9]{12,24})/i', $html, $m) ? strtoupper(trim($m[1])) : '';
            }

            $sku = preg_match('/(?:SKU|Item Code)[^A-Za-z0-9]*([A-Za-z0-9\-_]+)/i', $cleanText, $m) ? trim($m[1]) : '';

            // Extract one or more product names for package matching.
            $productNameCandidates = extractShopeeProductNameCandidates($xpath, $cleanText);

            $paymentInfoPairs = [];
            $buyerPaymentPairs = [];
            if ($xpath instanceof DOMXPath) {
                $paymentInfoPairs = collectShopeeOrderAmountPairsFromDom($xpath, "//*[@data-testid='odp-order-payment']//*[contains(@class,'income-item')]");
                $buyerPaymentPairs = collectShopeeOrderAmountPairsFromDom($xpath, "//*[@data-testid='odp-buyer-payment']//*[contains(@class,'income-item')]");

                if (empty($paymentInfoPairs)) {
                    $paymentInfoPairs = collectShopeeOrderAmountPairsFromDom($xpath);
                }
                if (empty($buyerPaymentPairs)) {
                    $buyerPaymentPairs = $paymentInfoPairs;
                }
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

            // Required mapping:
            // service_fee <- Service Fee
            $serviceFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Service Fee']);
            if ($serviceFee === '') {
                $serviceFee = parseShopeeOrderAmountByLabels($cleanText, ['Service Fee']);
            }

            // Required mapping:
            // trans_fee <- Transaction Fee
            $transactionFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Transaction Fee']);
            if ($transactionFee === '') {
                $transactionFee = parseShopeeOrderAmountByLabels($cleanText, ['Transaction Fee']);
            }

            // Required mapping:
            // ams_fee <- Commission Fee
            $amsFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Commission Fee']);
            if ($amsFee === '') {
                $amsFee = parseShopeeOrderAmountByLabels($cleanText, ['Commission Fee']);
            }

            // Required mapping:
            // fees <- Fees & Charges
            $fees = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Fees & Charges']);
            if ($fees === '') {
                $fees = parseShopeeOrderAmountByLabels($cleanText, ['Fees & Charges']);
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

            // Extract buyer payment method.
            $detectedPayMethod = '';
            if ($xpath instanceof DOMXPath) {
                $payMethodNode = extractValueFromTableLabel($xpath, ['Payment Method']);
                if ($payMethodNode !== '') {
                    $detectedPayMethod = $payMethodNode;
                }
            }
            if ($detectedPayMethod === '' && $extension === 'pdf') {
                $detectedPayMethod = extractPdfFieldByLabels($cleanText, ['Payment Method', 'Payment Type']);
            }
            if ($detectedPayMethod === '') {
                if (preg_match('/Payment\s*Method\s*[:\s]*([A-Za-z0-9\s\-\.\/]+?)(?:\s*(?:Shipping|Voucher|Fees|Order|Merchandise|$))/i', $cleanText, $pm)) {
                    $detectedPayMethod = normalizeImportText($pm[1]);
                }
            }
            $payMethodId = '';
            if ($detectedPayMethod !== '') {
                $payMethodId = resolveImportOptionId($detectedPayMethod, $shopeePayMethods);
            }

            // Extract shop name / Shopee account.
            $detectedShopName = '';
            if ($xpath instanceof DOMXPath) {
                $shopHeaderNode = getNodeText($xpath, "//div[contains(@class,'order-detail-info')]//div[contains(@class,'header')]");
                if ($shopHeaderNode !== '') {
                    $detectedShopName = extractValueAfterColon($shopHeaderNode);
                }
            }
            if ($detectedShopName === '' && $extension === 'pdf') {
                $detectedShopName = extractPdfFieldByLabels($cleanText, ['Shop Name', 'Shopee Account', 'Shop']);
            }
            if ($detectedShopName === '') {
                if (preg_match('/Shop\s*(?:Name)?\s*[:\s]+([^\n,]+)/i', $cleanText, $sn)) {
                    $detectedShopName = normalizeImportText($sn[1]);
                }
            }
            $shopeeAccId = '';
            if ($detectedShopName !== '') {
                $shopeeAccId = resolveImportOptionId($detectedShopName, $shopeeAccounts);
            }

            // Detect order status with strict mapping:
            // To Ship -> P, To Receive -> SP, Completed -> OC.
            $statusInfo = $xpath instanceof DOMXPath
                ? detectShopeeOrderStatusFromHtml($xpath, $cleanText)
                : detectShopeeOrderStatusFromText($cleanText, true);

            $detectedOrderStatus = $statusInfo['code'];
            $detectedOrderStatusLabel = $statusInfo['label'];

            if ($order_id === '') {
                $importErrors[] = 'Order ID could not be detected. Please ensure you uploaded the correct Shopee Order Details HTML/PDF file.';
            } else if (isShopeeOrderIdDuplicated($order_id, $finance_connect)) {
                $orderIdFieldError = 'Duplicate Order ID found in Shopee Order Request records.';
            }

            $pkgIds = resolvePackageIdsFromDetectedData($sku, $productNameCandidates, $connect);
            $pkg_id = implode(',', $pkgIds);
            $missing_sku = empty($pkgIds);
            $brandIds = resolveBrandIdsByPackageIds($pkgIds, $connect);

            $product_price = normalizeImportAmount($product_price);
            $voucher = normalizeImportAmount($voucher);
            $actShippingFee = normalizeImportAmount($actShippingFee);
            $serviceFee = normalizeImportAmount($serviceFee);
            $transactionFee = normalizeImportAmount($transactionFee);
            $amsFee = normalizeImportAmount($amsFee);
            $fees = normalizeImportAmount($fees);
            $finalAmt = normalizeImportAmount($finalAmt);

            $product_price = $product_price !== '' ? $product_price : '0.00';
            $voucher = $voucher !== '' ? $voucher : '0.00';
            $actShippingFee = $actShippingFee !== '' ? $actShippingFee : '0.00';
            $serviceFee = $serviceFee !== '' ? $serviceFee : '0.00';
            $transactionFee = $transactionFee !== '' ? $transactionFee : '0.00';
            $amsFee = $amsFee !== '' ? $amsFee : '0.00';
            $fees = number_format(((float) $serviceFee + (float) $transactionFee + (float) $amsFee), 2, '.', '');
            $finalAmt = $finalAmt !== '' ? $finalAmt : '0.00';

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
                'brand' => implode(',', $brandIds),
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
                'remark' => $order_id !== '' ? ('Imported from Shopee Order ' . $sourceTypeLabel . ' (' . $order_id . ')') : ('Imported from Shopee Order ' . $sourceTypeLabel),
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

    if ($previewData['order_id'] !== '' && isShopeeOrderIdDuplicated($previewData['order_id'], $finance_connect)) {
        $orderIdFieldError = 'Duplicate Order ID found in Shopee Order Request records.';
    }

    $amountFields = ['product_price', 'voucher', 'act_shipping_fee', 'service_fee', 'trans_fee', 'ams_fee', 'fees', 'final_amt'];
    foreach ($amountFields as $amountField) {
        if (isset($previewData[$amountField]) && $previewData[$amountField] !== '') {
            $normalizedAmount = normalizeImportAmount($previewData[$amountField]);
            if ($normalizedAmount !== '') {
                $previewData[$amountField] = $normalizedAmount;
            }
        }
    }

    $previewData['voucher'] = $previewData['voucher'] !== '' ? $previewData['voucher'] : '0.00';
    $previewData['act_shipping_fee'] = $previewData['act_shipping_fee'] !== '' ? $previewData['act_shipping_fee'] : '0.00';
    $previewData['service_fee'] = $previewData['service_fee'] !== '' ? $previewData['service_fee'] : '0.00';
    $previewData['trans_fee'] = $previewData['trans_fee'] !== '' ? $previewData['trans_fee'] : '0.00';
    $previewData['ams_fee'] = $previewData['ams_fee'] !== '' ? $previewData['ams_fee'] : '0.00';
    $previewData['fees'] = number_format(((float) $previewData['service_fee'] + (float) $previewData['trans_fee'] + (float) $previewData['ams_fee']), 2, '.', '');
    
    // Server-side recomputation to prevent tampering
    $calculatedFinalAmt = (float) $previewData['product_price'] - (float) $previewData['voucher'] - (float) $previewData['act_shipping_fee'] - (float) $previewData['fees'];
    $previewData['final_amt'] = number_format($calculatedFinalAmt, 2, '.', '');

    if (empty($importErrors) && $orderIdFieldError === '') {
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

function extractShopeeProductNameCandidates($xpath, $cleanText)
{
    $candidates = array();

    if ($xpath instanceof DOMXPath) {
        $nodes = $xpath->query("//*[contains(@class,'product-name') or contains(@class,'item-name') or contains(@class,'product-title')]");
        if ($nodes) {
            foreach ($nodes as $node) {
                $name = normalizeImportText($node->textContent);
                if ($name !== '') {
                    $candidates[] = $name;
                }
            }
        }
    }

    if (preg_match_all('/(?:Product\(s\)|Product Name|Item)\s*[:\-]?\s*(.+?)(?:SKU|Item Code|Variation|Qty|Quantity|Payment|Order|$)/is', (string) $cleanText, $matches)) {
        foreach ($matches[1] as $match) {
            $name = normalizeImportText($match);
            if ($name !== '') {
                $candidates[] = $name;
            }
        }
    }

    $normalized = array();
    foreach ($candidates as $candidate) {
        $lookup = normalizeImportLookup($candidate);
        if ($lookup === '' || isset($normalized[$lookup])) {
            continue;
        }
        $normalized[$lookup] = $candidate;
    }

    return array_values($normalized);
}

function resolvePackageIdsFromDetectedData($sku, $productNameCandidates, $connect)
{
    $resolvedIds = array();

    $pushId = function ($id) use (&$resolvedIds) {
        $id = (int) $id;
        if ($id > 0) {
            $resolvedIds[] = $id;
        }
    };

    $sku = trim((string) $sku);
    if ($sku !== '') {
        $safeSku = mysqli_real_escape_string($connect, $sku);
        $pkgResult = getData('id', "item_code='$safeSku' OR name='$safeSku'", 'LIMIT 1', PKG, $connect);
        if ($pkgResult && $pkgResult->num_rows > 0) {
            $pkgRow = $pkgResult->fetch_assoc();
            $pushId($pkgRow['id']);
        } else {
            $safeSkuLike = sanitizeImportLikeTerm($sku);
            if ($safeSkuLike !== '') {
                $escapedSkuLike = mysqli_real_escape_string($connect, $safeSkuLike);
                try {
                    $pkgResult = getData('id', "item_code LIKE '%$escapedSkuLike%' OR name LIKE '%$escapedSkuLike%'", 'LIMIT 1', PKG, $connect);
                } catch (Throwable $throwable) {
                    $pkgResult = false;
                }

                if ($pkgResult && $pkgResult->num_rows > 0) {
                    $pkgRow = $pkgResult->fetch_assoc();
                    $pushId($pkgRow['id']);
                }
            }
        }
    }

    if (!is_array($productNameCandidates)) {
        $productNameCandidates = array();
    }

    foreach ($productNameCandidates as $productName) {
        $productName = trim((string) $productName);
        if ($productName === '') {
            continue;
        }

        $safeProductLike = sanitizeImportLikeTerm($productName);
        if ($safeProductLike === '') {
            continue;
        }

        $escapedProductLike = mysqli_real_escape_string($connect, $safeProductLike);

        $found = false;
        try {
            $pkgResult = getData('id', "name = '$escapedProductLike' OR item_code = '$escapedProductLike'", 'LIMIT 1', PKG, $connect);
        } catch (Throwable $throwable) {
            $pkgResult = false;
        }

        if ($pkgResult && $pkgResult->num_rows > 0) {
            $pkgRow = $pkgResult->fetch_assoc();
            $pushId($pkgRow['id']);
            $found = true;
        }

        if ($found) {
            continue;
        }

        try {
            $pkgResult = getData('id', "name LIKE '%$escapedProductLike%'", 'LIMIT 1', PKG, $connect);
        } catch (Throwable $throwable) {
            $pkgResult = false;
        }

        if ($pkgResult && $pkgResult->num_rows > 0) {
            $pkgRow = $pkgResult->fetch_assoc();
            $pushId($pkgRow['id']);
        }
    }

    $resolvedIds = array_values(array_unique(array_map('intval', $resolvedIds)));
    return $resolvedIds;
}

function sanitizeImportLikeTerm($value)
{
    $value = normalizeImportText((string) $value);
    if ($value === '') {
        return '';
    }

    // Convert to ASCII-safe LIKE term to avoid collation conflicts on legacy latin1 columns.
    $value = preg_replace('/[^\x20-\x7E]+/', ' ', $value);
    $value = trim(preg_replace('/\s+/', ' ', $value));
    
    // Escape LIKE metacharacters (% and _) to prevent wildcard injection
    $value = addcslashes($value, '%_\\');
    
    return $value;
}

function resolveBrandIdsByPackageIds($packageIds, $connect)
{
    $brandIds = array();
    if (!is_array($packageIds)) {
        $packageIds = array();
    }

    foreach ($packageIds as $packageId) {
        $packageId = (int) $packageId;
        if ($packageId <= 0) {
            continue;
        }

        $pkgResult = getData('brand', "id='$packageId'", 'LIMIT 1', PKG, $connect);
        if (!$pkgResult || $pkgResult->num_rows === 0) {
            continue;
        }

        $pkgRow = $pkgResult->fetch_assoc();
        $rawBrands = isset($pkgRow['brand']) ? (string) $pkgRow['brand'] : '';
        foreach (explode(',', $rawBrands) as $brandId) {
            $brandId = (int) trim((string) $brandId);
            if ($brandId > 0) {
                $brandIds[] = $brandId;
            }
        }
    }

    return array_values(array_unique($brandIds));
}

function cleanPdfTextOperand($text)
{
    $text = str_replace("\x00", '', (string) $text);
    $text = strtr($text, array(
        '\\n' => ' ',
        '\\r' => ' ',
        '\\t' => ' ',
        '\\(' => '(',
        '\\)' => ')',
        '\\\\' => '\\',
    ));

    return normalizeImportText(preg_replace('/[^[:print:] ]/', ' ', $text));
}

function extractTextFromPdfContent($content)
{
    if ((string) $content === '') {
        return '';
    }

    preg_match_all('/stream\r?\n(.*?)endstream/s', (string) $content, $streamMatches);
    $lines = array();

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

function getPdfTextLines($text)
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);
    $normalizedLines = array();

    foreach ($lines as $line) {
        $line = normalizeImportText($line);
        if ($line !== '') {
            $normalizedLines[] = $line;
        }
    }

    return $normalizedLines;
}

function extractPdfFieldByLabels($text, $labels)
{
    $lines = getPdfTextLines($text);

    foreach ($lines as $index => $line) {
        $lineLookup = normalizeImportLookup($line);
        if ($lineLookup === '') {
            continue;
        }

        foreach ($labels as $label) {
            $labelLookup = normalizeImportLookup($label);
            if ($labelLookup === '' || strpos($lineLookup, $labelLookup) === false) {
                continue;
            }

            if (preg_match('/' . preg_quote($label, '/') . '\s*:?\s*(.+)/i', $line, $matches)) {
                $value = normalizeImportText($matches[1]);
                if ($value !== '' && normalizeImportLookup($value) !== $labelLookup) {
                    return $value;
                }
            }

            if (isset($lines[$index + 1])) {
                return normalizeImportText($lines[$index + 1]);
            }
        }
    }

    return '';
}

function getShopeeOrderStatusInfoByKeyword($keyword)
{
    $normalizedKeyword = normalizeImportLookup($keyword);

    if ($normalizedKeyword === 'toreceive' || $normalizedKeyword === 'shipped' || $normalizedKeyword === 'shipping') {
        return [
            'code' => 'SP',
            'label' => 'SHIP PROCESSING (Warehouse)',
        ];
    }

    if ($normalizedKeyword === 'completed' || $normalizedKeyword === 'delivered' || $normalizedKeyword === 'orderreceived') {
        return [
            'code' => 'OC',
            'label' => 'Order Received (admin checking)',
        ];
    }

    return [
        'code' => 'P',
        'label' => 'Pending To Pack',
    ];
}

function detectShopeeOrderStatusFromText($text, $allowLooseMatch = false)
{
    $normalizedText = normalizeImportText($text);
    if ($normalizedText === '') {
        return getShopeeOrderStatusInfoByKeyword('to ship');
    }

    if (preg_match('/\bOrder\s*Status\b[^A-Za-z]*(To\s*Ship|To\s*Receive|Completed|Delivered|Order\s*Received)\b/i', $normalizedText, $matches)) {
        return getShopeeOrderStatusInfoByKeyword($matches[1]);
    }

    if ($allowLooseMatch && preg_match('/\b(To\s*Ship|To\s*Receive|Completed|Delivered|Order\s*Received)\b/i', $normalizedText, $matches)) {
        return getShopeeOrderStatusInfoByKeyword($matches[1]);
    }

    return getShopeeOrderStatusInfoByKeyword('to ship');
}

function detectShopeeOrderStatusFromHtml($xpath, $cleanText)
{
    $statusNodeQueries = [
        "//*[contains(@class,'order-status') and (contains(@class,'active') or contains(@class,'current'))]",
        "//*[contains(@class,'status') and (contains(@class,'active') or contains(@class,'current'))]",
        "//*[contains(@class,'timeline-item') and contains(@class,'active')]//*[contains(@class,'title')]",
        "//*[contains(@class,'timeline-item') and contains(@class,'current')]//*[contains(@class,'title')]",
        "//*[contains(@class,'order-detail-status') or contains(@class,'order_status')]",
    ];

    foreach ($statusNodeQueries as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            continue;
        }

        foreach ($nodes as $node) {
            $statusText = normalizeImportText($node->textContent);
            if ($statusText !== '' && preg_match('/\b(To\s*Ship|To\s*Receive|Completed|Delivered|Order\s*Received)\b/i', $statusText)) {
                return detectShopeeOrderStatusFromText($statusText, true);
            }
        }
    }

    return detectShopeeOrderStatusFromText($cleanText, false);
}

function normalizeImportAmount($rawAmount)
{
    $value = trim((string) $rawAmount);
    if ($value === '') {
        return '';
    }

    $value = str_replace([',', ' '], '', $value);
    $value = str_replace(['RM', 'MYR', 'USD', 'SGD'], '', strtoupper($value));
    $numeric = preg_replace('/[^0-9.]+/', '', $value);

    if ($numeric === '' || !is_numeric($numeric)) {
        return '';
    }

    $amount = abs((float) $numeric);
    return number_format($amount, 2, '.', '');
}

function isShopeeOrderIdDuplicated($orderId, $financeConnect)
{
    $orderId = trim((string) $orderId);
    if ($orderId === '') {
        return false;
    }

    if (function_exists('isDuplicateRecord')) {
        return isDuplicateRecord('orderID', $orderId, SHOPEE_SG_ORDER_REQ, $financeConnect, '');
    }

    $safeOrderId = mysqli_real_escape_string($financeConnect, $orderId);
    $result = mysqli_query($financeConnect, "SELECT id FROM " . SHOPEE_SG_ORDER_REQ . " WHERE orderID = '$safeOrderId' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
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

            if ($normalizedLabel === $normalizedTarget) {
                return isset($pair['amount']) ? $pair['amount'] : '';
            }
        }

        foreach ($pairs as $pair) {
            $normalizedLabel = normalizeImportLookup(isset($pair['label']) ? $pair['label'] : '');
            if ($normalizedLabel === '') {
                continue;
            }

            if (strpos($normalizedLabel, $normalizedTarget) !== false || strpos($normalizedTarget, $normalizedLabel) !== false) {
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
                            <h5 class="card-title mb-3">Step 1: Upload Shopee Order HTML/PDF</h5>
                            <form method="post" enctype="multipart/form-data">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Shopee Order Details HTML/PDF File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".html,.htm,.pdf" required>
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
                                            <?php if ($orderIdFieldError !== '') { ?>
                                                <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($orderIdFieldError) ?></small>
                                            <?php } ?>
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
                                            if (count($selectedPkgIds) <= 1 && count($pkgRows) > 1) {
                                                $pkgRows = array($pkgRows[0]);
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
                                            if (count($pkgRows) <= 1 && count($brandRows) > 1) {
                                                $brandRows = array($brandRows[0]);
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
                                            <input class="form-control" type="number" step="0.01" id="fees" name="fees" value="<?= htmlspecialchars(isset($previewData['fees']) ? $previewData['fees'] : '0.00') ?>" readonly>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="final_amt">Final Amount</label>
                                            <input class="form-control" type="number" step="0.01" id="final_amt" name="final_amt" value="<?= htmlspecialchars(isset($previewData['final_amt']) ? $previewData['final_amt'] : '0.00') ?>" readonly>
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




