<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
if (isset($_POST['cancelImportBtn']) || $action === 'cancelImport') {
    echo '<script>location.href = "' . $SITEURL . '/shopee_order_import.php";</script>';
    exit;
}
if ($action !== '' && !isActionAllowed('Import', $pinAccess)) {
    echo '<script>alert("You do not have permission to import.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}
$importErrors = [];
$importWarnings = [];
$previewData = [];
$orderIdFieldError = '';
$importLocalTelegramFailureMessage = '';
$allowedAttachmentExt = array("png", "jpg", "jpeg", "pdf");
$sorAirbillAttachmentPath = img_server . 'shopee_airbill_attachment/';
$sorAirbillAttachmentUrl = rtrim((string) $SITEURL, '/') . '/' . trim((string) $sorAirbillAttachmentPath, '/\\') . '/';
$importIsLiveSite = isset($siteOrlocalMode) ? (bool) $siteOrlocalMode : true;
$importBuildLocalTelegramFailureMessage = function ($notifyResult) use ($importIsLiveSite) {
    if ($importIsLiveSite || !is_array($notifyResult) || !empty($notifyResult['sent'])) {
        return '';
    }

    $reason = trim((string) (isset($notifyResult['message']) ? $notifyResult['message'] : ''));
    if ($reason === '') {
        $reason = 'Unknown Telegram send failure.';
    }

    return "Telegram message failed to send.\nReason: " . $reason;
};

$shopeeAccounts = getImportOptionList(SHOPEE_ACC, 'name', $finance_connect);
$currencyUnits = getImportOptionList(CUR_UNIT, 'unit', $connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
$shopeePayMethods = getImportOptionList(PAY_MTHD_SHOPEE, 'name', $finance_connect);
$brandOptions = getImportOptionList(BRAND, 'name', $connect);
$pkgOptions = getImportOptionList(PKG, 'name', $connect);
$shopeeBuyers = getImportOptionList(SHOPEE_CUST_INFO, 'buyer_username', $finance_connect);

$sorWarehouseRows = shopeeOmsLoadActiveWarehouses($connect);
$sorWarehouseOptionMap = array();

foreach ($sorWarehouseRows as $warehouseRow) {
    $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0;
    if ($warehouseId > 0) {
        $sorWarehouseOptionMap[$warehouseId] = isset($warehouseRow['name']) ? (string) $warehouseRow['name'] : ('Warehouse #' . $warehouseId);
    }
}

$sorDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect, $sorWarehouseRows);
$GLOBALS['sor_pdf_unicode_map'] = [];

if ($action === 'parseShopeeOrderReq') { // Shopee Order HTML/PDF Parsing
    $module = 'shopee_order_req';

    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Shopee Order HTML or PDF file.';
    } else if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . $_FILES['import_file']['error'];
    } else if ($_FILES['import_file']['size'] > 5 * 1024 * 1024) {
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
                $rawPdfText = '';
                $decodedPdfText = '';
                $decodedPdfTextDigitsPreserved = '';
                $xpath = null;
                $sourceTypeLabel = 'HTML';

                if ($extension === 'pdf') {
                    $sourceTypeLabel = 'PDF';
                    $pdfUnicodeMapBundle = buildPdfUnicodeMapFromContent($rawContent);
                    $GLOBALS['sor_pdf_unicode_map'] = $pdfUnicodeMapBundle;
                    $clientPdfText = isset($_POST['client_pdf_text']) ? trim((string) $_POST['client_pdf_text']) : '';

                    $rawPdfText = (string) extractTextFromPdfContent($rawContent);

                    $commandPdfText = extractTextFromPdfViaCommand((string) $_FILES['import_file']['tmp_name']);

                    if ($commandPdfText !== '') {
                        $rawPdfText = trim($rawPdfText . "\n" . $commandPdfText);
                    }

                    $decodedPdfText = decodeLikelyShopeePdfText($rawPdfText);
                    $decodedPdfTextDigitsPreserved = decodeShopeePdfShiftedGlyphText($rawPdfText, false);
                    if ($decodedPdfText !== '') {
                        $rawPdfText = trim($rawPdfText . "\n" . $decodedPdfText);
                    }
                    if ($decodedPdfTextDigitsPreserved !== '') {
                        $rawPdfText = trim($rawPdfText . "\n" . $decodedPdfTextDigitsPreserved);
                    }
                    if ($clientPdfText !== '') {
                        $rawPdfText = trim($rawPdfText . "\n" . $clientPdfText);
                    }

                    $cleanText = normalizeImportText($rawPdfText);
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
            $pdfSourceText = $extension === 'pdf' ? (isset($rawPdfText) ? (string) $rawPdfText : '') : '';
            $order_id = extractShopeeOrderId($cleanText, $html, $pdfSourceText, $extension === 'pdf' ? (string) $rawContent : '');

            $skuSourceText = $pdfSourceText !== '' ? $pdfSourceText : $cleanText;
            $sku = extractShopeeSkuFromText($skuSourceText);
            if ($sku === '') {
                $sku = extractShopeeSkuFromText($cleanText);
            }
            if ($sku === '' && $extension === 'pdf') {
                $sku = extractShopeeSkuFromPdfBinary((string) $rawContent);
            }

            // Extract one or more product names for package matching.
            $productNameCandidates = extractShopeeProductNameCandidates($xpath, $cleanText);

            $paymentInfoPairs = [];
            $buyerPaymentPairs = [];
            $paymentSectionText = '';
            if ($xpath instanceof DOMXPath) {
                $paymentInfoPairs = collectShopeeOrderAmountPairsFromDom($xpath, "//*[@data-testid='odp-order-payment']//*[contains(@class,'income-item')]");
                $buyerPaymentPairs = collectShopeeOrderAmountPairsFromDom($xpath, "//*[@data-testid='odp-buyer-payment']//*[contains(@class,'income-item')]");
                $paymentSectionText = extractShopeeOrderPaymentSectionText($xpath);

                if (empty($paymentInfoPairs)) {
                    $paymentInfoPairs = collectShopeeOrderAmountPairsFromDom($xpath);
                }
                if (empty($buyerPaymentPairs)) {
                    $buyerPaymentPairs = $paymentInfoPairs;
                }
            }

            $amountFallbackText = $extension === 'pdf'
                ? $cleanText
                : ($paymentSectionText !== '' ? $paymentSectionText : $cleanText);
            $serviceFeeLabels = ['Service Fee', 'Service Fee (Incl. GST)', 'Service Fee (Incl. Gst)', 'Service Fee Incl GST', 'Service Fee Incl Gst'];
            $transactionFeeLabels = ['Transaction Fee', 'Transaction Fee (Incl. GST)', 'Transaction Fee (Incl. Gst)', 'Transaction Fee Incl GST', 'Transaction Fee Incl Gst'];

            $product_price = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Product Price', 'Deal Price', 'Merchandise Subtotal']);
            if ($product_price === '') {
                $product_price = parseShopeeOrderAmountFromPairs($buyerPaymentPairs, ['Product Price', 'Merchandise Subtotal']);
            }
            if ($product_price === '' && $extension !== 'pdf') {
                $product_price = extractShopeeHtmlIncomeAmountByLabels($html, ['Product Price', 'Deal Price', 'Merchandise Subtotal']);
            }
            if ($product_price === '') {
                $product_price = parseShopeeOrderAmountByLabels($amountFallbackText, ['Product Price', 'Deal Price', 'Merchandise Subtotal']);
            }

            $voucherLabels = array(
                'Seller Voucher',
                'Seller Voucher Paid by Seller',
                'Shop Voucher',
                'Shop voucher',
                'Shop voucher paid by seller',
                'Seller voucher paid by seller'
            );

            $voucher = '';

            if ($extension !== 'pdf') {
                $voucher = parseShopeeOrderAmountFromPairs($buyerPaymentPairs, $voucherLabels);
                if ($voucher === '') {
                    $voucher = parseShopeeOrderAmountFromPairs($paymentInfoPairs, $voucherLabels);
                }

                if ($voucher === '') {
                    $voucher = extractShopeeHtmlIncomeAmountByLabels($html, $voucherLabels);
                }
            }

            // Actual shipping must follow Shipping Subtotal (buyer shipping subtotal), not logistic provider fee.
            $actShippingFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Shipping Subtotal', 'Estimated Shipping Subtotal']);
            if ($actShippingFee === '' && $extension !== 'pdf') {
                $actShippingFee = extractShopeeHtmlIncomeAmountByLabels($html, ['Shipping Subtotal', 'Estimated Shipping Subtotal']);
            }
            if ($actShippingFee === '') {
                $actShippingFee = parseShopeeOrderAmountByLabels($amountFallbackText, ['Shipping Subtotal', 'Estimated Shipping Subtotal']);
            }

            // Required mapping:
            // service_fee <- Service Fee
            $serviceFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, $serviceFeeLabels);
            if ($serviceFee === '' && $extension !== 'pdf') {
                $serviceFee = extractShopeeHtmlIncomeAmountByLabels($html, $serviceFeeLabels);
            }
            if ($serviceFee === '' && $extension !== 'pdf') {
                $serviceFee = parseShopeeOrderAmountByLabels($amountFallbackText, $serviceFeeLabels);
            }

            // Required mapping:
            // trans_fee <- Transaction Fee
            $transactionFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, $transactionFeeLabels);
            if ($transactionFee === '' && $extension !== 'pdf') {
                $transactionFee = extractShopeeHtmlIncomeAmountByLabels($html, $transactionFeeLabels);
            }
            if ($transactionFee === '' && $extension !== 'pdf') {
                $transactionFee = parseShopeeOrderAmountByLabels($amountFallbackText, $transactionFeeLabels);
            }

            // Required mapping:
            // ams_fee <- Commission Fee
            $amsFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Commission Fee']);
            if ($amsFee === '' && $extension !== 'pdf') {
                $amsFee = extractShopeeHtmlIncomeAmountByLabels($html, ['Commission Fee']);
            }
            if ($amsFee === '' && $extension !== 'pdf') {
                $amsFee = parseShopeeOrderAmountByLabels($amountFallbackText, ['Commission Fee']);
            }

            $saverProgramFee = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Saver Programme Fee', 'Saver Program Fee']);
            if ($saverProgramFee === '') {
                if ($extension === 'pdf') {
                    $saverProgramFee = parseShopeeOrderAmountByLabels($cleanText, ['Saver Programme Fee', 'Saver Program Fee']);
                } else if ($paymentSectionText !== '') {
                    $saverProgramFee = parseShopeeOrderAmountByLabels($paymentSectionText, ['Saver Programme Fee', 'Saver Program Fee']);
                }
            }

            // Required mapping:
            // fees <- Fees & Charges
            $fees = '';
            if ($extension !== 'pdf') {
                $fees = extractShopeeHtmlIncomeAmountByLabels($html, ['Fees & Charges']);
            }
            if ($fees === '') {
                $fees = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Fees & Charges']);
            }
            if ($fees === '') {
                $fees = parseShopeeOrderAmountByLabels($amountFallbackText, ['Fees & Charges']);
            }

            $finalAmt = '';
            if ($extension !== 'pdf') {
                $finalAmt = extractShopeeHtmlIncomeAmountByLabels($html, ['Estimated Order Income', 'Order Income', 'Final Amount']);
            }
            if ($finalAmt === '') {
                $finalAmt = parseShopeeOrderAmountFromPairs($paymentInfoPairs, ['Estimated Order Income', 'Order Income', 'Final Amount']);
            }
            if ($finalAmt === '') {
                $finalAmt = parseShopeeOrderAmountByLabels($amountFallbackText, ['Order Income', 'Estimated Order Income', 'Final Amount']);
            }

            if ($extension === 'pdf') {
                $pdfMoneySource = $pdfSourceText !== '' ? $pdfSourceText : $cleanText;
                $pdfMoney = extractShopeePdfMonetaryValues($pdfMoneySource);

                if (is_array($pdfMoney)) {
                    if (array_key_exists('product_price', $pdfMoney) && (string) $pdfMoney['product_price'] !== '') {
                        $product_price = (string) $pdfMoney['product_price'];
                    }
                    if (array_key_exists('voucher', $pdfMoney) && (string) $pdfMoney['voucher'] !== '') {
                        $voucher = (string) $pdfMoney['voucher'];
                    }
                    if (array_key_exists('act_shipping_fee', $pdfMoney) && (string) $pdfMoney['act_shipping_fee'] !== '') {
                        $actShippingFee = (string) $pdfMoney['act_shipping_fee'];
                    }
                    if (array_key_exists('service_fee', $pdfMoney) && (string) $pdfMoney['service_fee'] !== '') {
                        $serviceFee = (string) $pdfMoney['service_fee'];
                    }
                    if (array_key_exists('trans_fee', $pdfMoney) && (string) $pdfMoney['trans_fee'] !== '') {
                        $transactionFee = (string) $pdfMoney['trans_fee'];
                    }
                    if (array_key_exists('ams_fee', $pdfMoney) && (string) $pdfMoney['ams_fee'] !== '') {
                        $amsFee = (string) $pdfMoney['ams_fee'];
                    }
                    if (array_key_exists('saver_program_fee', $pdfMoney) && (string) $pdfMoney['saver_program_fee'] !== '') {
                        $saverProgramFee = (string) $pdfMoney['saver_program_fee'];
                    }
                }
            }

            $detectedCurrency = detectShopeeOrderCurrency($cleanText);
            $currencyFallbacks = $detectedCurrency === 'RM' ? ['MYR'] : [];
            $currencyId = resolveImportOptionId($detectedCurrency, $currencyUnits, $currencyFallbacks);

            $buyerSourceText = $extension === 'pdf' ? ($pdfSourceText !== '' ? $pdfSourceText : $cleanText) : $cleanText;
            $buyerUsername = extractShopeeBuyerUsername($html, $buyerSourceText);
            $buyerFallbacks = $extension === 'pdf' ? getShopeeBuyerUsernameOcrVariants($buyerUsername) : array();
            $buyerId = resolveImportOptionId($buyerUsername, $shopeeBuyers, $buyerFallbacks);
            if ($buyerId !== '' && isset($shopeeBuyers[(int) $buyerId])) {
                $buyerUsername = (string) $shopeeBuyers[(int) $buyerId];
            }

            // Extract buyer payment method.
            $detectedPayMethod = '';
            if ($xpath instanceof DOMXPath) {
                $payMethodNode = extractValueFromTableLabel($xpath, ['Payment Method']);
                if ($payMethodNode !== '') {
                    $detectedPayMethod = $payMethodNode;
                }
            }
            if ($detectedPayMethod === '' && $extension === 'pdf') {
                $detectedPayMethod = extractPdfFieldByLabels($pdfSourceText !== '' ? $pdfSourceText : $cleanText, ['Payment Method', 'Payment Type']);
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
                $detectedShopName = extractPdfFieldByLabels($pdfSourceText !== '' ? $pdfSourceText : $cleanText, ['Shop Name', 'Shopee Account']);
                if (!isLikelyShopeeAccountName($detectedShopName)) {
                    $detectedShopName = extractShopeePdfAccountName($pdfSourceText !== '' ? $pdfSourceText : $cleanText);
                }
                if (!isLikelyShopeeAccountName($detectedShopName)) {
                    $detectedShopName = extractShopeePdfAccountNameFromBinary((string) $rawContent);
                }
            }
            if ($detectedShopName === '') {
                if (preg_match('/Shop\s*Name\s*[:\s]+([^\n,]+)/i', $cleanText, $sn)) {
                    $detectedShopName = normalizeImportText($sn[1]);
                }
            }
            if (!isLikelyShopeeAccountName($detectedShopName)) {
                $detectedShopName = '';
            }
            $shopeeAccId = '';
            if ($detectedShopName !== '') {
                $shopeeAccId = resolveImportOptionId($detectedShopName, $shopeeAccounts);
            }

            // Detect order status with strict mapping:
            // To Ship -> P, To Receive -> SP, Completed -> OC.
            $pdfStatusSourceText = $pdfSourceText !== '' ? $pdfSourceText : $cleanText;
            $statusInfo = $xpath instanceof DOMXPath
                ? detectShopeeOrderStatusFromHtml($xpath, $cleanText)
                : (($extension === 'pdf')
                    ? detectShopeeOrderStatusFromPdfText($pdfStatusSourceText)
                    : detectShopeeOrderStatusFromText($cleanText, true));

            $detectedOrderStatus = $statusInfo['code'];
            $detectedOrderStatusLabel = $statusInfo['label'];

            if (
                $extension === 'pdf'
                && isset($pdfMoney)
                && !empty($pdfMoney['delivered_hint'])
                && $detectedOrderStatus === 'P'
                && !pdfTextHasPendingShipmentSignals($pdfStatusSourceText)
                && !pdfTextHasProcessingShipmentSignals($pdfStatusSourceText)
            ) {
                $detectedOrderStatus = 'OC';
                $detectedOrderStatusLabel = 'Order Received (admin checking)';
            }

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
            $saverProgramFee = normalizeImportAmount($saverProgramFee);
            if ($extension === 'pdf') {
                if ($serviceFee === '' && extractShopeePdfOcrServiceFeeDirect($pdfStatusSourceText) !== '') {
                    $serviceFee = '0.00';
                }

                if ($transactionFee === '') {
                    $directTransactionFee = extractShopeePdfOcrTransactionFeeDirect($pdfStatusSourceText);
                    if ($directTransactionFee !== '') {
                        $transactionFee = $directTransactionFee;
                    }
                }

                if ($transactionFee === '' && $fees !== '' && $amsFee !== '' && $saverProgramFee !== '') {
                    $baseServiceFee = $serviceFee !== '' ? $serviceFee : '0.00';
                    $derivedTransactionFee = number_format(
                        max(
                            0,
                            (float) normalizeImportAmount($fees)
                            - (float) normalizeImportAmount($amsFee)
                            - (float) normalizeImportAmount($saverProgramFee)
                            - (float) normalizeImportAmount($baseServiceFee)
                        ),
                        2,
                        '.',
                        ''
                    );

                    if ((float) $derivedTransactionFee > 0) {
                        $transactionFee = $derivedTransactionFee;
                    }
                }
            }
            $serviceFeeDetected = $serviceFee !== '';
            $transactionFeeDetected = $transactionFee !== '';
            $amsFeeDetected = $amsFee !== '';
            $feesDetected = $fees !== '';
            $finalAmtDetected = $finalAmt !== '';
            $fees = normalizeImportAmount($fees);
            $finalAmt = normalizeImportAmount($finalAmt);

            $product_price = $product_price !== '' ? $product_price : '0.00';
            $voucherDetected = $voucher !== '';
            $voucher = $voucher !== '' ? $voucher : '0.00';
            $actShippingFee = $actShippingFee !== '' ? $actShippingFee : '0.00';
            $serviceFee = $serviceFee !== '' ? $serviceFee : '0.00';
            $transactionFee = $transactionFee !== '' ? $transactionFee : '0.00';
            $amsFee = $amsFee !== '' ? $amsFee : '0.00';
            $saverProgramFee = $saverProgramFee !== '' ? $saverProgramFee : '0.00';
            $fees = resolveShopeeImportFeesAmount($serviceFee, $transactionFee, $amsFee, $saverProgramFee, $fees);
            $finalAmt = $finalAmt !== '' ? $finalAmt : calculateShopeeImportFinalAmount($product_price, $voucher, $actShippingFee, $fees);

            $mappedInitialStatus = shopeeOmsGetImportDefaultStatus($detectedOrderStatus);
            $previewData = [
                'order_id' => $order_id,
                'sku' => $sku,
                'package_id' => $pkg_id,
                'product_price' => $product_price !== '' ? $product_price : '0.00',
                'order_status' => shopeeOmsGetStatusLabel($mappedInitialStatus),
                'order_status_val' => $mappedInitialStatus,
                'stock_out_warehouse_id' => $sorDefaultWarehouseId,
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
                'voucher_detected' => $voucherDetected ? 'yes' : 'no',
                'act_shipping_fee' => $actShippingFee,
                'service_fee' => $serviceFee,
                'trans_fee' => $transactionFee,
                'ams_fee' => $amsFee,
                'service_fee_detected' => $serviceFeeDetected ? 'yes' : 'no',
                'trans_fee_detected' => $transactionFeeDetected ? 'yes' : 'no',
                'ams_fee_detected' => $amsFeeDetected ? 'yes' : 'no',
                'saver_program_fee' => $saverProgramFee,
                'fees' => $fees,
                'fees_detected' => $feesDetected ? 'yes' : 'no',
                'final_amt' => $finalAmt,
                'final_amt_detected' => $finalAmtDetected ? 'yes' : 'no',
                'remark' => $order_id !== '' ? ('Imported from Shopee Order ' . $sourceTypeLabel . ' (' . $order_id . ')') : ('Imported from Shopee Order ' . $sourceTypeLabel),
                'update_airbill' => 'yes',
                'airbill_no' => '',
                'airbill_attachment' => '',
                'customer_address' => '',
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
    
    $orderStatusVal = shopeeOmsNormalizeStatusCode(postSpaceFilter('order_status_val'));
    if ($orderStatusVal === '') $orderStatusVal = 'P';
    $stockOutWarehouseId = shopeeOmsNormalizeWarehouseId(postSpaceFilter('stock_out_warehouse_id'));
    if ($stockOutWarehouseId <= 0) {
        $stockOutWarehouseId = $sorDefaultWarehouseId;
    }
    $updateAirbill = strtolower(trim((string) postSpaceFilter('update_airbill')));
    if ($updateAirbill === '') $updateAirbill = 'yes';
    $airbillNo = postSpaceFilter('airbill_no');
    $airbillAttachment = null;
    if (isset($_FILES["airbill_attachment"]) && $_FILES["airbill_attachment"]["size"] != 0) {
        $airbillAttachment = $_FILES["airbill_attachment"]["name"];
    } elseif (isset($_POST['airbill_attachment_value'])) {
        $airbillAttachment = $_POST['airbill_attachment_value'];
    }
    $buyerInput = trim((string) postSpaceFilter('buyer'));
    $buyerHidden = postSpaceFilter('buyer_hidden');
    $resolvedBuyerId = trim((string) $buyerHidden) !== '' ? $buyerHidden : resolveImportOptionId($buyerInput, $shopeeBuyers);
    $customerAddress = postSpaceFilter('customer_address');
    
    $previewData = [
        'order_id' => postSpaceFilter('order_id'),
        'package_id' => $packageIdsStr,
        'product_price' => postSpaceFilter('product_price'),
        'order_status' => $orderStatusVal,
        'order_status_val' => $orderStatusVal,
        'stock_out_warehouse_id' => $stockOutWarehouseId,
        'sku' => isset($_POST['sku']) ? $_POST['sku'] : '',
        'missing_sku' => empty($packageIdsStr),
        'shopee_acc' => postSpaceFilter('shopee_acc'),
        'currency' => postSpaceFilter('currency'),
        'brand' => $brandIdsStr,
        'buyer' => $resolvedBuyerId,
        'buyer_name' => $buyerInput,
        'buyer_pay_meth' => postSpaceFilter('buyer_pay_meth'),
        'pic' => postSpaceFilter('pic'),
        'voucher' => postSpaceFilter('voucher'),
        'voucher_detected' => postSpaceFilter('voucher_detected'),
        'act_shipping_fee' => postSpaceFilter('act_shipping_fee'),
        'service_fee' => postSpaceFilter('service_fee'),
        'trans_fee' => postSpaceFilter('trans_fee'),
        'ams_fee' => postSpaceFilter('ams_fee'),
        'service_fee_detected' => postSpaceFilter('service_fee_detected'),
        'trans_fee_detected' => postSpaceFilter('trans_fee_detected'),
        'ams_fee_detected' => postSpaceFilter('ams_fee_detected'),
        'saver_program_fee' => postSpaceFilter('saver_program_fee'),
        'fees' => postSpaceFilter('fees'),
        'fees_detected' => postSpaceFilter('fees_detected'),
        'final_amt' => postSpaceFilter('final_amt'),
        'final_amt_detected' => postSpaceFilter('final_amt_detected'),
        'remark' => postSpaceFilter('remark'),
        'update_airbill' => $updateAirbill,
        'airbill_no' => $airbillNo,
        'airbill_attachment' => $airbillAttachment,
        'customer_address' => $customerAddress,
    ];

    if ($previewData['order_id'] === '') $importErrors[] = 'Order ID is required.';
    if ($previewData['shopee_acc'] === '') $importErrors[] = 'Shopee Account is required.';
    if ($previewData['currency'] === '') $importErrors[] = 'Currency is required.';
    if ($previewData['brand'] === '') $importErrors[] = 'Brand is required.';
    if ($previewData['pic'] === '') $importErrors[] = 'Person In Charge is required.';
    if (trim((string) $previewData['buyer_name']) === '') {
        $importErrors[] = 'Shopee Buyer Username is required.';
    }
    if ((int) $previewData['stock_out_warehouse_id'] <= 0) {
        $importErrors[] = 'Stock Out Warehouse cannot be empty.';
    } else if (!isset($sorWarehouseOptionMap[(int) $previewData['stock_out_warehouse_id']])) {
        $importErrors[] = 'Please select a valid active Stock Out Warehouse.';
    }
    if ($previewData['update_airbill'] === 'no') {
        $previewData['airbill_no'] = '';
        $previewData['airbill_attachment'] = '';
        $previewData['customer_address'] = '';
    }
    $statusValidation = shopeeOmsValidateInitialStatusAndAirbill($previewData['order_status_val'], $previewData['airbill_no']);
    if (!$statusValidation['valid']) $importErrors[] = $statusValidation['message'];
    if ($previewData['update_airbill'] === 'yes') {
        if (trim((string) $previewData['airbill_no']) === '') $importErrors[] = 'Airbill No is required when Update Airbill is enabled.';
        if (trim((string) $previewData['customer_address']) === '') $importErrors[] = 'Customer Address is required when Update Airbill is enabled.';
        if (trim((string) $previewData['airbill_attachment']) === '') $importErrors[] = 'Airbill Attachment is required when Update Airbill is enabled.';
    }

    if ($previewData['order_id'] !== '' && isShopeeOrderIdDuplicated($previewData['order_id'], $finance_connect)) {
        $orderIdFieldError = 'Duplicate Order ID found in Shopee Order Request records.';
    }

    $amountFields = ['product_price', 'voucher', 'act_shipping_fee', 'service_fee', 'trans_fee', 'ams_fee', 'saver_program_fee', 'fees', 'final_amt'];
    foreach ($amountFields as $amountField) {
        if (isset($previewData[$amountField]) && $previewData[$amountField] !== '') {
            $normalizedAmount = normalizeImportAmount($previewData[$amountField]);
            if ($normalizedAmount !== '') {
                $previewData[$amountField] = $normalizedAmount;
            }
        }
    }

    $previewData['voucher_detected'] = $previewData['voucher_detected'] === 'yes' ? 'yes' : 'no';
    $previewData['voucher'] = $previewData['voucher'] !== '' ? $previewData['voucher'] : '0.00';
    $previewData['act_shipping_fee'] = $previewData['act_shipping_fee'] !== '' ? $previewData['act_shipping_fee'] : '0.00';
    $previewData['service_fee'] = $previewData['service_fee'] !== '' ? $previewData['service_fee'] : '0.00';
    $previewData['trans_fee'] = $previewData['trans_fee'] !== '' ? $previewData['trans_fee'] : '0.00';
    $previewData['ams_fee'] = $previewData['ams_fee'] !== '' ? $previewData['ams_fee'] : '0.00';
    $previewData['service_fee_detected'] = $previewData['service_fee_detected'] === 'yes' ? 'yes' : 'no';
    $previewData['trans_fee_detected'] = $previewData['trans_fee_detected'] === 'yes' ? 'yes' : 'no';
    $previewData['ams_fee_detected'] = $previewData['ams_fee_detected'] === 'yes' ? 'yes' : 'no';
    $previewData['saver_program_fee'] = $previewData['saver_program_fee'] !== '' ? $previewData['saver_program_fee'] : '0.00';
    $previewData['fees_detected'] = $previewData['fees_detected'] === 'yes' ? 'yes' : 'no';
    $previewData['fees'] = $previewData['fees_detected'] === 'yes'
        ? ($previewData['fees'] !== '' ? $previewData['fees'] : resolveShopeeImportFeesAmount($previewData['service_fee'], $previewData['trans_fee'], $previewData['ams_fee'], $previewData['saver_program_fee']))
        : resolveShopeeImportFeesAmount($previewData['service_fee'], $previewData['trans_fee'], $previewData['ams_fee'], $previewData['saver_program_fee']);
    
    if (empty($importErrors) && trim((string) $previewData['buyer_name']) !== '') {
        if (trim((string) $previewData['buyer']) === '') {
            $safeBuyerUsername = mysqli_real_escape_string($finance_connect, trim((string) $previewData['buyer_name']));
            $safeBuyerLookup = mysqli_real_escape_string($finance_connect, strtolower(trim((string) $previewData['buyer_name'])));

            $existingBuyerSql = "SELECT id FROM `" . SHOPEE_CUST_INFO . "` 
                WHERE LOWER(TRIM(buyer_username)) = '$safeBuyerLookup' 
                AND status = 'A' 
                LIMIT 1";
            $existingBuyerResult = mysqli_query($finance_connect, $existingBuyerSql);

            if ($existingBuyerResult && mysqli_num_rows($existingBuyerResult) > 0) {
                $existingBuyerRow = mysqli_fetch_assoc($existingBuyerResult);
                $previewData['buyer'] = isset($existingBuyerRow['id']) ? (int) $existingBuyerRow['id'] : '';
            } else {
                $buyerPic = (int) $previewData['pic'];
                $buyerUsernameForLog = trim((string) $previewData['buyer_name']);

                $buyerBrand = 'NULL';
                $brandParts = array_filter(array_map('trim', explode(',', (string) $previewData['brand'])));
                if (!empty($brandParts) && ctype_digit((string) reset($brandParts))) {
                    $buyerBrand = "'" . (int) reset($brandParts) . "'";
                }

                $insertBuyerSql = "INSERT INTO `" . SHOPEE_CUST_INFO . "` 
                    (buyer_username, pic, brand, create_by, create_date, create_time, status)
                    VALUES 
                    ('$safeBuyerUsername', '$buyerPic', $buyerBrand, '" . USER_ID . "', curdate(), curtime(), 'A')";

                if (mysqli_query($finance_connect, $insertBuyerSql)) {
                    $newBuyerId = mysqli_insert_id($finance_connect);
                    $previewData['buyer'] = $newBuyerId;

                    $safeBuyerUsernameForLog = htmlspecialchars($buyerUsernameForLog, ENT_QUOTES, 'UTF-8');
                    $buyerAuditLog = [
                        'log_act' => 'Add',
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => $insertBuyerSql,
                        'query_table' => SHOPEE_CUST_INFO,
                        'newval' => 'buyer_username=' . $buyerUsernameForLog,
                        'act_msg' => USER_NAME . " add the data [ <b> ID = " . (int) $newBuyerId . " </b> ] to <b><i>" . SHOPEE_CUST_INFO . " Table</i></b> for imported buyer username <b>" . $safeBuyerUsernameForLog . "</b>.",
                        'page' => $pageTitle,
                        'connect' => $connect,
                    ];
                    audit_log($buyerAuditLog);
                } else {
                    $importErrors[] = 'Failed to auto create Shopee Buyer Username: ' . mysqli_error($finance_connect);
                }
            }
        }
    }
    // Server-side recomputation to prevent tampering
    $previewData['final_amt_detected'] = $previewData['final_amt_detected'] === 'yes' ? 'yes' : 'no';
    $calculatedFinalAmt = calculateShopeeImportFinalAmount($previewData['product_price'], $previewData['voucher'], $previewData['act_shipping_fee'], $previewData['fees']);
    $previewData['final_amt'] = ($previewData['final_amt_detected'] === 'yes' && $previewData['final_amt'] !== '')
        ? $previewData['final_amt']
        : $calculatedFinalAmt;
    $packageQtySnapshot = shopeeOmsBuildPackageQtySnapshotFromInputs(
        isset($_POST['sor_pkg_hidden']) ? $_POST['sor_pkg_hidden'] : array(),
        isset($_POST['sor_pkg']) ? $_POST['sor_pkg'] : array(),
        $connect
    );
    $previewData['package_qty_json'] = !empty($packageQtySnapshot) ? json_encode($packageQtySnapshot) : '';

    if ($previewData['update_airbill'] === 'yes' && isset($_FILES["airbill_attachment"]) && $_FILES["airbill_attachment"]["size"] != 0) {
        $uploadResult = shopeeOmsStoreAirbillAttachmentUpload(
            $_FILES["airbill_attachment"],
            $connect,
            isset($previewData['brand']) ? $previewData['brand'] : '',
            isset($previewData['package_id']) ? $previewData['package_id'] : '',
            'shopee_order_request',
            $allowedAttachmentExt
        );
        if (!empty($uploadResult['success'])) {
            $previewData['airbill_attachment'] = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
        } else {
            $importErrors[] = isset($uploadResult['message']) ? (string) $uploadResult['message'] : 'Failed to upload the airbill attachment.';
        }
    }

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
        $packageQtyJson = mysqli_real_escape_string($finance_connect, isset($previewData['package_qty_json']) ? $previewData['package_qty_json'] : '');
        $airbillNoSafe = mysqli_real_escape_string($finance_connect, isset($previewData['airbill_no']) ? $previewData['airbill_no'] : '');
        $airbillAttachmentSafe = mysqli_real_escape_string($finance_connect, isset($previewData['airbill_attachment']) ? $previewData['airbill_attachment'] : '');
        $customerAddressSafe = mysqli_real_escape_string($finance_connect, isset($previewData['customer_address']) ? $previewData['customer_address'] : '');
        $stockOutWarehouseIdSafe = (int) $previewData['stock_out_warehouse_id'];

        $query = "INSERT INTO " . SHOPEE_SG_ORDER_REQ . " 
        (orderID, package, package_qty_json, price, voucher, act_shipping_fee, service_fee, trans_fee, ams_fee, fees, final_amt, order_status, shopee_acc, currency, brand, buyer, buyer_pay_meth, pic, customer_address, airbill_no, airbill_attachment, stock_out_warehouse_id, remark, latest_transition_at, date, time, create_by, create_date, create_time) 
        VALUES ('$orderId', '$pkgId', '$packageQtyJson', '$price', '$voucher', '$actShippingFee', '$serviceFee', '$transFee', '$amsFee', '$fees', '$finalAmt', '$status', '$acc', '$curr', '$brand', '$buyer', '$payMeth', '$pic', '$customerAddressSafe', '$airbillNoSafe', '$airbillAttachmentSafe', '$stockOutWarehouseIdSafe', '$remark', NOW(), curdate(), curtime(), '" . USER_ID . "', curdate(), curtime())";
        
        $requiresInitialShippedAutoMove = (shopeeOmsNormalizeStatusCode($previewData['order_status']) === 'SP');
        $startedFinanceTransaction = false;

        try {
            if ($requiresInitialShippedAutoMove) {
                mysqli_begin_transaction($finance_connect);
                $startedFinanceTransaction = true;
            }

            $returnData = mysqli_query($finance_connect, $query);
            if (!$returnData) {
                throw new Exception('Database Error: ' . mysqli_error($finance_connect));
            }

            $dataID = mysqli_insert_id($finance_connect);
            shopeeOmsLogTransition($finance_connect, array(
                'order_id' => (int) $dataID,
                'order_code' => $previewData['order_id'],
                'from_status' => '',
                'to_status' => $previewData['order_status'],
                'transition_action' => 'pdf_import',
                'user_id' => USER_ID,
                'user_group_id' => USER_GROUP,
                'remark' => 'Imported from Shopee Order Preview.',
                'source_page' => $pageTitle,
            ));

            if (shopeeOmsNormalizeStatusCode($previewData['order_status']) === 'TP') {
                $freshOrderRow = shopeeOmsLoadOrder($finance_connect, (int) $dataID);
                $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $freshOrderRow, USER_ID);
                if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                    $notifyResult = shopeeOmsSendWarehouseNotification($connect, $finance_connect, $tokenResult['token_row'], $tokenResult['notification'], $parentPageTitle);
                    $importLocalTelegramFailureMessage = $importBuildLocalTelegramFailureMessage($notifyResult);
                    if (!empty($notifyResult['sent'])) {
                        mysqli_query($finance_connect, "UPDATE `" . SHOPEE_SG_ORDER_REQ . "` SET `step_a_sent_at` = NOW() WHERE id = " . (int) $dataID . " LIMIT 1");
                    }
                }
            } else if ($requiresInitialShippedAutoMove) {
                $initialShippedResult = shopeeOmsFinalizeInitialShippedOrder($connect, $finance_connect, (int) $dataID, USER_ID, USER_GROUP, $pageTitle);
                if (empty($initialShippedResult['success'])) {
                    throw new Exception(isset($initialShippedResult['message']) ? $initialShippedResult['message'] : 'Unable to process initial Shipped status.');
                }
            }

            if ($startedFinanceTransaction) {
                mysqli_commit($finance_connect);
                $startedFinanceTransaction = false;
            }

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

            $importSuccessMessage = "Shopee Order Request imported successfully.";
            if ($importLocalTelegramFailureMessage !== '') {
                $importSuccessMessage .= "\n\n" . $importLocalTelegramFailureMessage;
            }
            echo '<script>alert(' . json_encode($importSuccessMessage) . ');window.location.replace("' . $shopeeOrderRedirectPage . '");</script>';
            exit;
        } catch (Exception $exception) {
            if ($startedFinanceTransaction) {
                mysqli_rollback($finance_connect);
            }
            $importErrors[] = $exception->getMessage();
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

function extractShopeeHtmlIncomeAmountByLabels($html, $labels)
{
    $html = (string) $html;
    if ($html === '' || empty($labels)) {
        return '';
    }

    foreach ((array) $labels as $label) {
        $normalizedLabel = normalizeImportText($label);
        if ($normalizedLabel === '') {
            continue;
        }

        $labelPattern = preg_quote($normalizedLabel, '/');
        $pattern = '/income-label-text[^>]*>\s*' . $labelPattern . '\s*(?:<!--.*?-->\s*)?<\/div>.*?income-value[^>]*>.*?([\-]?\s*(?:RM|MYR|SGD|USD|\$)\s*[0-9][0-9,]*\.[0-9]{2})/is';
        if (preg_match($pattern, html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }
    }

    return '';
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

        // 1. Create an ASCII-safe normalized term for the EXACT equality check
        $safeProductExact = normalizeImportText($productName);
        $safeProductExact = preg_replace('/[^\x20-\x7E]+/', ' ', $safeProductExact);
        $safeProductExact = trim(preg_replace('/\s+/', ' ', $safeProductExact));

        if ($safeProductExact === '') {
            continue;
        }

        // 2. Add slashes to the exact term ONLY for the LIKE check
        $safeProductLike = addcslashes($safeProductExact, '%_\\');

        $escapedProductExact = mysqli_real_escape_string($connect, $safeProductExact);
        $escapedProductLike = mysqli_real_escape_string($connect, $safeProductLike);

        $found = false;
        try {
            // Use the unescaped exact term for equality matching
            $pkgResult = getData('id', "name = '$escapedProductExact' OR item_code = '$escapedProductExact'", 'LIMIT 1', PKG, $connect);
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

function pdfHexToUtf8($hex)
{
    $hex = preg_replace('/[^0-9A-Fa-f]/', '', (string) $hex);
    if ($hex === '') {
        return '';
    }

    if ((strlen($hex) % 2) === 1) {
        $hex .= '0';
    }

    $bin = @hex2bin($hex);
    if ($bin === false) {
        return '';
    }

    if (strlen($hex) <= 2) {
        return cleanPdfTextOperand($bin);
    }

    if ((strlen($hex) % 4) === 0 && function_exists('mb_convert_encoding')) {
        $text = @mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
        if (is_string($text) && $text !== '') {
            return $text;
        }
    }

    return cleanPdfTextOperand($bin);
}

function pdfIncrementHex($hex, $step)
{
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string) $hex));
    $step = (int) $step;
    if ($hex === '' || $step < 0) {
        return '';
    }

    $width = strlen($hex);
    if ($width > 8) {
        return '';
    }

    $value = hexdec($hex);
    $next = $value + $step;
    if ($next < 0) {
        return '';
    }

    return strtoupper(str_pad(dechex($next), $width, '0', STR_PAD_LEFT));
}

function buildPdfUnicodeMapFromContent($content)
{
    $map = array();
    $codeLengths = array();

    preg_match_all('/stream\s*\r?\n(.*?)\r?\n?endstream/s', (string) $content, $streamMatches);
    $streams = isset($streamMatches[1]) && is_array($streamMatches[1]) ? $streamMatches[1] : array();

    foreach ($streams as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false || $decoded === '') {
            continue;
        }

        if (stripos($decoded, 'beginbfchar') === false && stripos($decoded, 'beginbfrange') === false) {
            continue;
        }

        if (preg_match_all('/beginbfchar(.*?)endbfchar/si', $decoded, $bfCharBlocks)) {
            foreach ($bfCharBlocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $pair) {
                        $src = strtoupper($pair[1]);
                        $dst = pdfHexToUtf8($pair[2]);
                        if ($src === '' || $dst === '') {
                            continue;
                        }
                        $map[$src] = $dst;
                        $codeLengths[strlen($src)] = true;
                    }
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/si', $decoded, $bfRangeBlocks)) {
            foreach ($bfRangeBlocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $rangeMatches, PREG_SET_ORDER)) {
                    foreach ($rangeMatches as $rangeMatch) {
                        $start = strtoupper($rangeMatch[1]);
                        $end = strtoupper($rangeMatch[2]);
                        $destStart = strtoupper($rangeMatch[3]);

                        if ($start === '' || $end === '' || $destStart === '' || strlen($start) !== strlen($end)) {
                            continue;
                        }

                        $startVal = hexdec($start);
                        $endVal = hexdec($end);
                        if ($endVal < $startVal) {
                            continue;
                        }

                        $total = $endVal - $startVal;
                        if ($total > 1024) {
                            continue;
                        }

                        for ($offset = 0; $offset <= $total; $offset++) {
                            $src = pdfIncrementHex($start, $offset);
                            $dstHex = pdfIncrementHex($destStart, $offset);
                            $dst = pdfHexToUtf8($dstHex);

                            if ($src === '' || $dst === '') {
                                continue;
                            }

                            $map[$src] = $dst;
                            $codeLengths[strlen($src)] = true;
                        }
                    }
                }

                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $block, $arrayRangeMatches, PREG_SET_ORDER)) {
                    foreach ($arrayRangeMatches as $rangeMatch) {
                        $start = strtoupper($rangeMatch[1]);
                        $end = strtoupper($rangeMatch[2]);
                        $arrayBlock = $rangeMatch[3];

                        if ($start === '' || $end === '' || strlen($start) !== strlen($end)) {
                            continue;
                        }

                        $startVal = hexdec($start);
                        $endVal = hexdec($end);
                        if ($endVal < $startVal) {
                            continue;
                        }

                        $destList = array();
                        if (preg_match_all('/<([0-9A-Fa-f]+)>/', $arrayBlock, $destMatches)) {
                            $destList = $destMatches[1];
                        }

                        $total = min(($endVal - $startVal), count($destList) - 1);
                        if ($total > 1024) {
                            $total = 1024;
                        }

                        for ($offset = 0; $offset <= $total; $offset++) {
                            $src = pdfIncrementHex($start, $offset);
                            $dst = pdfHexToUtf8($destList[$offset]);
                            if ($src === '' || $dst === '') {
                                continue;
                            }

                            $map[$src] = $dst;
                            $codeLengths[strlen($src)] = true;
                        }
                    }
                }
            }
        }
    }

    $lengths = array_map('intval', array_keys($codeLengths));
    rsort($lengths, SORT_NUMERIC);

    return array(
        'map' => $map,
        'code_lengths' => $lengths,
    );
}

function decodePdfHexTokenWithUnicodeMap($hex)
{
    $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string) $hex));
    if ($hex === '') {
        return '';
    }

    $bundle = isset($GLOBALS['sor_pdf_unicode_map']) && is_array($GLOBALS['sor_pdf_unicode_map'])
        ? $GLOBALS['sor_pdf_unicode_map']
        : array();
    $map = isset($bundle['map']) && is_array($bundle['map']) ? $bundle['map'] : array();
    $lengths = isset($bundle['code_lengths']) && is_array($bundle['code_lengths']) ? $bundle['code_lengths'] : array();

    if (empty($map) || empty($lengths)) {
        return '';
    }

    foreach ($lengths as $codeLen) {
        $codeLen = (int) $codeLen;
        if ($codeLen <= 0 || (strlen($hex) % $codeLen) !== 0) {
            continue;
        }

        $parts = str_split($hex, $codeLen);
        $hits = 0;
        $out = '';

        foreach ($parts as $part) {
            if (isset($map[$part])) {
                $out .= $map[$part];
                $hits++;
            }
        }

        if ($hits > 0 && $hits >= (int) ceil(count($parts) * 0.6)) {
            return cleanPdfTextOperand($out);
        }
    }

    return '';
}

function decodePdfLiteralStringToken($token)
{
    $token = (string) $token;
    if ($token === '') {
        return '';
    }

    if ($token[0] === '<' && substr($token, -1) === '>') {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($token, 1, -1));
        if ($hex !== '') {
            $decodedWithMap = decodePdfHexTokenWithUnicodeMap($hex);
            if ($decodedWithMap !== '') {
                return $decodedWithMap;
            }

            if ((strlen($hex) % 2) === 1) {
                $hex .= '0';
            }
            $decodedHex = @hex2bin($hex);
            if ($decodedHex !== false) {
                return cleanPdfTextOperand($decodedHex);
            }
        }
        return '';
    }

    if ($token[0] === '(' && substr($token, -1) === ')') {
        $inner = substr($token, 1, -1);
    } else {
        $inner = $token;
    }

    $inner = preg_replace_callback(
        '/\\\\([0-7]{1,3})/',
        function ($m) {
            return chr(octdec($m[1]));
        },
        $inner,
    );

    $inner = str_replace(array(
        '\\n',
        '\\r',
        '\\t',
        '\\b',
        '\\f',
        '\\(',
        '\\)',
        '\\\\',
    ), array(
        "\n",
        "\r",
        "\t",
        "\b",
        "\f",
        "(",
        ")",
        "\\",
    ), $inner);

    $inner = preg_replace('/\\\\\r\n|\\\\\n|\\\\\r/', '', $inner);

    return cleanPdfTextOperand($inner);
}

function extractPdfTextTokensFromDecodedStream($decoded)
{
    $decoded = (string) $decoded;
    if ($decoded === '') {
        return array();
    }

    $lines = array();

    $singleTokenPattern = '/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*Tj/s';
    if (preg_match_all($singleTokenPattern, $decoded, $matches)) {
        foreach ($matches[1] as $token) {
            $cleanLine = decodePdfLiteralStringToken($token);
            if ($cleanLine !== '') {
                $lines[] = $cleanLine;
            }
        }
    }

    $apostrophePattern = '/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*\'/s';
    if (preg_match_all($apostrophePattern, $decoded, $matches)) {
        foreach ($matches[1] as $token) {
            $cleanLine = decodePdfLiteralStringToken($token);
            if ($cleanLine !== '') {
                $lines[] = $cleanLine;
            }
        }
    }

    $quotePattern = '/[-+0-9.\s]+[-+0-9.\s]+(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*"/s';
    if (preg_match_all($quotePattern, $decoded, $matches)) {
        foreach ($matches[1] as $token) {
            $cleanLine = decodePdfLiteralStringToken($token);
            if ($cleanLine !== '') {
                $lines[] = $cleanLine;
            }
        }
    }

    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
        foreach ($arrayMatches[1] as $chunk) {
            if (preg_match_all('/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)/s', $chunk, $innerMatches)) {
                $pieces = array();
                foreach ($innerMatches[1] as $token) {
                    $part = decodePdfLiteralStringToken($token);
                    if ($part !== '') {
                        $pieces[] = $part;
                    }
                }

                $cleanLine = cleanPdfTextOperand(implode('', $pieces));
                if ($cleanLine !== '') {
                    $lines[] = $cleanLine;
                }
            }
        }
    }

    return $lines;
}

function extractTextFromPdfViaCommand($filePath)
{
    // 1. Config Gate: Allow disabling via environment/config constant
    if (defined('DISABLE_PDFTOTEXT_EXEC') && DISABLE_PDFTOTEXT_EXEC) {
        return '';
    }

    $filePath = trim((string) $filePath);
    if ($filePath === '' || !is_file($filePath)) {
        return '';
    }

    if (!function_exists('shell_exec')) {
        return '';
    }

    $disabled = (string) ini_get('disable_functions');
    if ($disabled !== '') {
        $disabledFunctions = array_map('trim', explode(',', strtolower($disabled)));
        if (in_array('shell_exec', $disabledFunctions, true)) {
            return '';
        }
    }

    $escapedFile = escapeshellarg($filePath);
    
    // 2. Resource Limit: Prevent hangs by wrapping the command with a 15-second timeout (Unix/Linux environments)
    $timeoutPrefix = '';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $timeoutPrefix = 'timeout 15 ';
    }

    $commands = [
        $timeoutPrefix . 'pdftotext -enc UTF-8 -layout ' . $escapedFile . ' - 2>/dev/null',
        $timeoutPrefix . 'pdftotext -enc UTF-8 ' . $escapedFile . ' - 2>/dev/null',
    ];

    foreach ($commands as $command) {
        $output = @shell_exec($command);
        $output = is_string($output) ? trim($output) : '';
        if ($output !== '') {
            return $output;
        }
    }

    return '';
}

function extractTextFromPdfContent($content)
{
    if ((string) $content === '') {
        return '';
    }

    preg_match_all('/stream\s*\r?\n(.*?)\r?\n?endstream/s', (string) $content, $streamMatches);
    $lines = array();

    foreach ($streamMatches[1] as $stream) {
        $decoded = decodePdfStream($stream);
        if ($decoded === false) {
            $decoded = (string) $stream;
        }

        $extractedLines = extractPdfTextTokensFromDecodedStream($decoded);
        if (!empty($extractedLines)) {
            $lines = array_merge($lines, $extractedLines);
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

function normalizeShopeeSkuCandidate($value)
{
    $value = strtoupper(trim((string) $value));
    if ($value === '') {
        return '';
    }

    // Repair common encoded glyph substitutions seen in Shopee PDF text extraction.
    $value = strtr($value, array(
        '&' => 'B',
        '%' => 'A',
        '-' => 'I',
        '=' => 'Y',
        ':' => 'V',
        ';' => 'W',
        ',' => 'X',
    ));

    $value = preg_replace('/\b(?:VIEW|TRANSACTION|HISTORY|UNIT|PRICE|MERCHANDISE|SUBTOTAL|PAYMENT|INFO|INFORMATION)\b.*$/i', '', $value);
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);

    if ($value === null || strlen($value) < 4 || strlen($value) > 32) {
        return '';
    }

    $invalidTokens = array(
        'TOTAL',
        'SUBTOTAL',
        'MERCHANDISE',
        'SHIPPING',
        'VOUCHER',
        'COMMISSION',
        'SERVICE',
        'TRANSACTION',
        'PAYMENT',
        'ORDERINCOME',
    );

    foreach ($invalidTokens as $token) {
        if (strpos($value, $token) !== false) {
            return '';
        }
    }

    if (!preg_match('/[A-Z]{3,}/', $value)) {
        return '';
    }

    if (!preg_match('/\d{2,}/', $value)) {
        return '';
    }

    return $value;
}

function scoreShopeeSkuCandidate($candidate)
{
    $candidate = strtoupper(trim((string) $candidate));
    if ($candidate === '') {
        return -999;
    }

    $score = 0;
    $len = strlen($candidate);

    if (preg_match('/[A-Z]/', $candidate)) {
        $score += 4;
    }
    if (preg_match('/\d/', $candidate)) {
        $score += 4;
    }
    if (preg_match('/\d{2,}$/', $candidate)) {
        $score += 3;
    }
    if ($len >= 8 && $len <= 16) {
        $score += 2;
    }

    $blacklist = array(
        'TOTAL',
        'SUBTOTAL',
        'PRODUCT',
        'PRICE',
        'SHIPPING',
        'VOUCHER',
        'MERCHANDISE',
        'TRANSACTION',
        'COMMISSION',
        'SERVICE',
    );

    foreach ($blacklist as $token) {
        if (strpos($candidate, $token) !== false) {
            $score -= 20;
        }
    }

    if (preg_match('/^(RM|MYR|USD|SGD)/', $candidate)) {
        $score -= 6;
    }

    return $score;
}

function extractShopeeSkuFromPdfBinary($rawContent)
{
    $rawContent = (string) $rawContent;
    if ($rawContent === '') {
        return '';
    }

    if (preg_match_all('/(?:SKU|ITEM\s*CODE)\s*[:=]?\s*([A-Za-z0-9\-_]{4,40})/i', $rawContent, $matches)) {
        $bestSku = '';
        $bestScore = -999;

        foreach ($matches[1] as $rawCandidate) {
            $candidate = normalizeShopeeSkuCandidate($rawCandidate);
            if ($candidate === '') {
                continue;
            }

            $score = scoreShopeeSkuCandidate($candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSku = $candidate;
            }
        }

        if ($bestScore >= 5) {
            return $bestSku;
        }
    }

    return '';
}

function extractShopeeSkuFromText($text)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $lines = getPdfTextLines($text);
    $bestSku = '';
    $bestScore = -999;

    $patterns = array(
        '/(?:SKU|S\/?K\/?U|ITEM\s*CODE)\s*[:\-]?\s*([A-Za-z0-9&%:=;,\-\/\\_\s]{4,60})/i',
        '/\b([A-Za-z0-9&%:=;,\-\/\\_]{6,40})\b\s*(?:SKU|ITEM\s*CODE)/i',
    );

    foreach ($lines as $index => $line) {
        $candidateLineTexts = array($line);
        if (isset($lines[$index + 1])) {
            $candidateLineTexts[] = $line . ' ' . $lines[$index + 1];
        }

        foreach ($candidateLineTexts as $lineText) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $lineText, $matches)) {
                    foreach ($matches[1] as $rawCandidate) {
                        $rawCandidate = preg_replace('/\b(?:VIEW|TRANSACTION|HISTORY|UNIT|PRICE|MERCHANDISE|SUBTOTAL|PAYMENT|INFO|INFORMATION|DELIVERY|ADDRESS|LOGISTIC)\b.*$/i', '', (string) $rawCandidate);
                        $rawCandidate = preg_replace('/\s+[0-9]{1,4}(?:\.[0-9]{2})?.*$/', '', (string) $rawCandidate);
                        $candidate = normalizeShopeeSkuCandidate($rawCandidate);
                        if ($candidate === '') {
                            continue;
                        }

                        $score = scoreShopeeSkuCandidate($candidate);
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestSku = $candidate;
                        }
                    }
                }
            }
        }
    }

    if ($bestScore >= 5) {
        return $bestSku;
    }

    return '';
}

function normalizeShopeeOrderIdCandidate($value)
{
    $value = strtoupper(trim((string) $value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^A-Z0-9]+/', '', $value);
    if ($value === null) {
        return '';
    }

    $length = strlen($value);
    if ($length < 10 || $length > 30) {
        return '';
    }

    if (!preg_match('/\d/', $value)) {
        return '';
    }

    return $value;
}

function extractShopeeOrderIdFromText($text)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    if (preg_match_all('/(?:Order\s*(?:ID|SN|No|Number)\s*[:#\-]?\s*)([^\r\n]{6,80})/i', $text, $labelMatches)) {
        foreach ($labelMatches[1] as $rawCandidate) {
            if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9\-_]{8,40}/', (string) $rawCandidate, $tokenMatches)) {
                foreach ($tokenMatches[0] as $token) {
                    $candidate = normalizeShopeeOrderIdCandidate($token);
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }

            $candidate = normalizeShopeeOrderIdCandidate($rawCandidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    if (preg_match_all('/(?:Order\s*(?:ID|SN|No|Number))[\s:;#\-]*((?:[A-Za-z0-9][\s\-\/_]?){10,40})/i', $text, $spacedMatches)) {
        foreach ($spacedMatches[1] as $rawCandidate) {
            $candidate = normalizeShopeeOrderIdCandidate($rawCandidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    if (preg_match_all('/\b([A-Za-z0-9][A-Za-z0-9\-\/_]{9,35})\b/', $text, $genericMatches)) {
        foreach ($genericMatches[1] as $rawCandidate) {
            $candidate = normalizeShopeeOrderIdCandidate($rawCandidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

function extractShopeeOrderIdFromPdfBinary($rawContent)
{
    $rawContent = (string) $rawContent;
    if ($rawContent === '') {
        return '';
    }

    $directPatterns = [
        '/sale\/order\/([A-Za-z0-9\-_]{8,40})/i',
        '/order[_\-]?(?:id|sn|no|number)[^A-Za-z0-9]{0,20}([A-Za-z0-9\-_]{8,40})/i',
        '/(?:orderid|ordersn)=([A-Za-z0-9\-_]{8,40})/i',
    ];

    foreach ($directPatterns as $pattern) {
        if (preg_match($pattern, $rawContent, $m)) {
            $candidate = normalizeShopeeOrderIdCandidate($m[1]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    if (preg_match_all('/\/URI\s*\(([^\)]{1,600})\)/i', $rawContent, $uriMatches)) {
        foreach ($uriMatches[1] as $uri) {
            $uri = html_entity_decode((string) $uri, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (preg_match('/sale\/order\/([A-Za-z0-9\-_]{8,40})/i', $uri, $m)) {
                $candidate = normalizeShopeeOrderIdCandidate($m[1]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }

            if (preg_match('/(?:orderid|ordersn)=([A-Za-z0-9\-_]{8,40})/i', $uri, $m)) {
                $candidate = normalizeShopeeOrderIdCandidate($m[1]);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }
    }

    return '';
}

function extractShopeeOrderIdFromHtml($html)
{
    $html = (string) $html;
    if ($html === '') {
        return '';
    }

    $patterns = [
        '/data-testid\s*=\s*["\']odp-label-order-id["\'][\s\S]{0,2000}?class\s*=\s*["\'][^"\']*body[^"\']*["\']\s*>\s*([A-Za-z0-9\-_\s]{8,60})\s*</i',
        '/Order\s*ID\s*<[^>]*>[\s\S]{0,600}?class\s*=\s*["\'][^"\']*body[^"\']*["\']\s*>\s*([A-Za-z0-9\-_\s]{8,60})\s*</i',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $html, $m)) {
            continue;
        }

        $candidate = normalizeShopeeOrderIdCandidate((string) $m[1]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function extractShopeeOrderId($cleanText, $html, $pdfSourceText = '', $rawPdfContent = '')
{
    $orderId = '';

    if ((string) $pdfSourceText !== '') {
        $orderId = extractPdfFieldByLabels($pdfSourceText, ['Order ID', 'Order SN', 'Order No', 'Order Number']);
        $orderId = normalizeShopeeOrderIdCandidate($orderId);

        if ($orderId === '') {
            $orderId = extractShopeeOrderIdFromText($pdfSourceText);
        }
    }

    if ($orderId === '' && (string) $html !== '') {
        $orderId = extractShopeeOrderIdFromHtml((string) $html);
    }

    if ($orderId === '') {
        $orderId = extractShopeeOrderIdFromText((string) $cleanText);
    }

    if ($orderId === '' && (string) $html !== '') {
        if (preg_match('/sale\/order\/([A-Za-z0-9\-_]{8,40})/i', (string) $html, $m)) {
            $orderId = normalizeShopeeOrderIdCandidate($m[1]);
        }
    }

    if ($orderId === '' && (string) $rawPdfContent !== '') {
        $orderId = extractShopeeOrderIdFromPdfBinary($rawPdfContent);
    }

    return $orderId;
}

function scoreShopeePdfTextReadability($text)
{
    $text = strtolower((string) $text);
    if ($text === '') {
        return 0;
    }

    $keywords = array(
        'shopee',
        'order',
        'order id',
        'order sn',
        'payment',
        'shipping',
        'subtotal',
        'voucher',
        'buyer',
        'seller',
        'income',
    );

    $score = 0;
    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            $score++;
        }
    }

    return $score;
}

function decodeShopeePdfShiftedGlyphText($text, $mapDigits = true)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $decoded = '';
    $length = strlen($text);

    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];
        $ord = ord($char);

        if ($ord >= 69 && $ord <= 90) {
            $decoded .= chr($ord - 4);
            continue;
        }

        if ($mapDigits && $char >= '0' && $char <= '9') {
            $decoded .= chr(ord('L') + (int) $char);
            continue;
        }

        if ($char === '[') {
            $decoded .= 'W';
            continue;
        }

        if ($char === '\\') {
            $decoded .= 'X';
            continue;
        }

        if ($char === ']') {
            $decoded .= 'Y';
            continue;
        }

        if ($char === '^') {
            $decoded .= 'Z';
            continue;
        }

        $decoded .= $char;
    }

    return $decoded;
}

function decodeLikelyShopeePdfText($text)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $decoded = decodeShopeePdfShiftedGlyphText($text, true);
    if ($decoded === '') {
        return '';
    }

    $sourceScore = scoreShopeePdfTextReadability($text);
    $decodedScore = scoreShopeePdfTextReadability($decoded);

    if ($decodedScore >= max(2, $sourceScore + 2)) {
        return $decoded;
    }

    return '';
}

function getShopeeOrderStatusInfoByKeyword($keyword)
{
    $normalizedKeyword = normalizeImportLookup($keyword);

    $statusSPKeywords = array(
        'toreceive',
        'shipped',
        'shipprocessing',
        'shipprocessingwarehouse',
        'intransit',
        'outfordelivery',
        'parcelpickedup',
        'senderispreparingtoshipyourparcel',
    );
    if (in_array($normalizedKeyword, $statusSPKeywords, true)) {
        return [
            'code' => 'SP',
            'label' => 'SHIP PROCESSING (Warehouse)',
        ];
    }

    $statusOCKeywords = array(
        'completed',
        'delivered',
        'orderreceived',
        'received',
    );
    if (in_array($normalizedKeyword, $statusOCKeywords, true)) {
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

function textContainsAnyCompactPhrase($compactText, $phrases)
{
    $compactText = strtolower((string) $compactText);
    if ($compactText === '') {
        return false;
    }

    foreach ((array) $phrases as $phrase) {
        $phrase = strtolower((string) $phrase);
        if ($phrase !== '' && strpos($compactText, $phrase) !== false) {
            return true;
        }
    }

    return false;
}

function pdfTextHasPendingShipmentSignals($text)
{
    $compact = strtolower(preg_replace('/[^a-z]+/i', '', normalizeImportText($text)));
    if ($compact === '') {
        return false;
    }

    return textContainsAnyCompactPhrase($compact, array(
        'toship',
        'pendingtopack',
        'pendingtoship',
        'neworder',
        'waitingtoship',
        'waitingforcouriertoconfirmshipment',
        'successfullyarrangedshipment',
        'pleaseproceedtoshipouttheparcel',
        'pleaseproceedtoshipoutparcel',
        'readytoship',
    ));
}

function pdfTextHasProcessingShipmentSignals($text)
{
    $compact = strtolower(preg_replace('/[^a-z]+/i', '', normalizeImportText($text)));
    if ($compact === '') {
        return false;
    }

    return textContainsAnyCompactPhrase($compact, array(
        'toreceive',
        'shipprocessingwarehouse',
        'shipprocessing',
        'intransit',
        'outfordelivery',
        'shipped',
        'parcelpickedup',
        'senderispreparingtoshipyourparcel',
    ));
}

function pdfTextHasCompletedShipmentSignals($text)
{
    $compact = strtolower(preg_replace('/[^a-z]+/i', '', normalizeImportText($text)));
    if ($compact === '') {
        return false;
    }

    return textContainsAnyCompactPhrase($compact, array(
        'parcelhasbeendelivered',
        'deliveredtobuyer',
        'successfullydelivered',
        'completed',
    ));
}

function pdfTextHasGenericReceivedSignals($text)
{
    $compact = strtolower(preg_replace('/[^a-z]+/i', '', normalizeImportText($text)));
    if ($compact === '') {
        return false;
    }

    return textContainsAnyCompactPhrase($compact, array(
        'orderreceived',
        'received',
    ));
}

function detectShopeeOrderStatusFromPdfText($text)
{
    $normalizedText = normalizeImportText($text);
    if ($normalizedText === '') {
        return getShopeeOrderStatusInfoByKeyword('to ship');
    }

    if (pdfTextHasPendingShipmentSignals($normalizedText)) {
        return getShopeeOrderStatusInfoByKeyword('to ship');
    }

    if (pdfTextHasProcessingShipmentSignals($normalizedText)) {
        return getShopeeOrderStatusInfoByKeyword('to receive');
    }

    if (pdfTextHasCompletedShipmentSignals($normalizedText)) {
        return getShopeeOrderStatusInfoByKeyword('completed');
    }

    if (
        pdfTextHasGenericReceivedSignals($normalizedText)
        && !pdfTextHasPendingShipmentSignals($normalizedText)
        && !pdfTextHasProcessingShipmentSignals($normalizedText)
    ) {
        return getShopeeOrderStatusInfoByKeyword('completed');
    }

    return detectShopeeOrderStatusFromText($normalizedText, true);
}

function detectShopeeOrderStatusFromText($text, $allowLooseMatch = false)
{
    $normalizedText = normalizeImportText($text);
    if ($normalizedText === '') {
        return getShopeeOrderStatusInfoByKeyword('to ship');
    }

    $compact = strtolower(preg_replace('/[^a-z]+/i', '', $normalizedText));
    if ($compact !== '') {
        $ocCompactPhrases = array(
            'parcelhasbeendelivered',
            'deliveredtobuyer',
            'orderreceived',
            'completed',
            'successfullydelivered',
        );
        foreach ($ocCompactPhrases as $phrase) {
            if (strpos($compact, $phrase) !== false) {
                return getShopeeOrderStatusInfoByKeyword('completed');
            }
        }

        $spCompactPhrases = array(
            'toreceive',
            'shipprocessingwarehouse',
            'shipprocessing',
            'intransit',
            'outfordelivery',
            'shipped',
            'parcelpickedup',
            'senderispreparingtoshipyourparcel',
        );
        foreach ($spCompactPhrases as $phrase) {
            if (strpos($compact, $phrase) !== false) {
                return getShopeeOrderStatusInfoByKeyword('to receive');
            }
        }

        $pCompactPhrases = array(
            'toship',
            'pendingtopack',
            'pendingtoship',
            'neworder',
            'waitingtoship',
            'waitingforcouriertoconfirmshipment',
            'successfullyarrangedshipment',
            'pleaseproceedtoshipouttheparcel',
            'pleaseproceedtoshipoutparcel',
            'readytoship',
        );
        foreach ($pCompactPhrases as $phrase) {
            if (strpos($compact, $phrase) !== false) {
                return getShopeeOrderStatusInfoByKeyword('to ship');
            }
        }
    }

    if (preg_match('/\bOrder\s*Status\b[^A-Za-z]*(To\s*Ship|To\s*Receive|Ship\s*Processing(?:\s*\(\s*Warehouse\s*\))?|Pending\s*To\s*Pack|Completed|Delivered|Order\s*Received)\b/i', $normalizedText, $matches)) {
        return getShopeeOrderStatusInfoByKeyword($matches[1]);
    }

    if ($allowLooseMatch && preg_match('/\b(To\s*Ship|To\s*Receive|Ship\s*Processing(?:\s*\(\s*Warehouse\s*\))?|Pending\s*To\s*Pack|Completed|Delivered|Order\s*Received)\b/i', $normalizedText, $matches)) {
        return getShopeeOrderStatusInfoByKeyword($matches[1]);
    }

    return getShopeeOrderStatusInfoByKeyword('to ship');
}

function detectShopeeOrderStatusFromHtml($xpath, $cleanText)
{
    $statusNodeQueries = [
        "//*[@data-testid='odp-logistics-history']//*[contains(@class,'status')]",
        "//*[contains(@class,'status-log')]//*[contains(@class,'status')]",
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
            if ($statusText === '') {
                continue;
            }

            $statusInfo = detectShopeeOrderStatusFromText($statusText, true);
            $statusTextCompact = strtolower(preg_replace('/[^a-z]+/i', '', $statusText));
            if (
                $statusInfo['code'] !== 'P'
                || preg_match('/\b(To\s*Ship|Pending\s*To\s*Pack)\b/i', $statusText)
                || strpos($statusTextCompact, 'senderispreparingtoshipyourparcel') !== false
            ) {
                return $statusInfo;
            }
        }
    }

    $bodyText = getNodeText($xpath, '//body');
    if ($bodyText !== '') {
        return detectShopeeOrderStatusFromText($bodyText, false);
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

function resolveShopeeImportFeesAmount($serviceFee, $transactionFee, $amsFee, $saverProgramFee, $directFees = '')
{
    $normalizedDirectFees = normalizeImportAmount($directFees);
    if ($normalizedDirectFees !== '') {
        return $normalizedDirectFees;
    }

    return number_format(
        (float) normalizeImportAmount($serviceFee)
        + (float) normalizeImportAmount($transactionFee)
        + (float) normalizeImportAmount($amsFee)
        + (float) normalizeImportAmount($saverProgramFee),
        2,
        '.',
        ''
    );
}

function calculateShopeeImportFinalAmount($productPrice, $voucher, $actShippingFee, $fees)
{
    return number_format(
        (float) normalizeImportAmount($productPrice)
        - (float) normalizeImportAmount($voucher)
        - (float) normalizeImportAmount($actShippingFee)
        - (float) normalizeImportAmount($fees),
        2,
        '.',
        ''
    );
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

function buildLooseAmountLabelPattern($label)
{
    $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) $label));
    if ($normalized === '') {
        return '';
    }

    $chars = str_split($normalized);
    $parts = array();
    foreach ($chars as $char) {
        $parts[] = preg_quote($char, '/');
    }

    return implode('[^A-Z0-9]{0,4}', $parts);
}

function extractShopeeImportDebugLabelWindows($text, $labels, $radius = 220)
{
    $text = (string) $text;
    if ($text === '' || empty($labels)) {
        return array();
    }

    $source = strtoupper($text);
    $radius = (int) $radius;
    if ($radius < 80) {
        $radius = 80;
    }
    if ($radius > 1000) {
        $radius = 1000;
    }

    $results = array();
    foreach ((array) $labels as $label) {
        $pattern = buildLooseAmountLabelPattern($label);
        if ($pattern === '') {
            continue;
        }

        if (!preg_match('/' . $pattern . '/i', $source, $match, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        $offset = isset($match[0][1]) ? (int) $match[0][1] : -1;
        if ($offset < 0) {
            continue;
        }

        $start = max(0, $offset - $radius);
        $length = min(strlen($text) - $start, $radius * 2);
        $snippet = substr($text, $start, $length);
        $snippet = str_replace(array("\r\n", "\r"), "\n", (string) $snippet);
        $results[$label] = trim($snippet);
    }

    return $results;
}

function extractShopeeImportDebugLines($text, $labels, $lineWindow = 2)
{
    $lines = getPdfTextLines($text);
    if (empty($lines) || empty($labels)) {
        return array();
    }

    $patterns = array();
    foreach ((array) $labels as $label) {
        $pattern = buildLooseAmountLabelPattern($label);
        if ($pattern !== '') {
            $patterns[$label] = $pattern;
        }
    }

    if (empty($patterns)) {
        return array();
    }

    $lineWindow = max(0, min(4, (int) $lineWindow));
    $results = array();
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $lineUpper = strtoupper((string) $lines[$i]);

        foreach ($patterns as $label => $pattern) {
            if (isset($results[$label])) {
                continue;
            }

            if (preg_match('/' . $pattern . '/i', $lineUpper) !== 1) {
                continue;
            }

            $start = max(0, $i - $lineWindow);
            $end = min($lineCount - 1, $i + $lineWindow);
            $chunk = array();
            for ($j = $start; $j <= $end; $j++) {
                $chunk[] = $lines[$j];
            }
            $results[$label] = implode("\n", $chunk);
        }
    }

    return $results;
}

function buildShopeePdfCompactText($text)
{
    $text = strtoupper((string) $text);
    if ($text === '') {
        return '';
    }

    // Keep only alnum plus amount markers for deterministic label->amount matching.
    return preg_replace('/[^A-Z0-9\.\-]+/', '', $text);
}

function extractImportAmountsFromText($text)
{
    $text = (string) $text;
    if ($text === '') {
        return array();
    }

    $results = array();
    if (preg_match_all('/-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.[0-9]{2}/i', $text, $matches)) {
        foreach ($matches[0] as $raw) {
            $amount = normalizeImportAmount($raw);
            if ($amount !== '') {
                $results[] = $amount;
            }
        }
    }

    return $results;
}

function normalizeShopeePdfMoneyText($text)
{
    $normalized = strtoupper(normalizeImportText((string) $text));
    if ($normalized !== '') {
        return $normalized;
    }

    return strtoupper((string) $text);
}

function buildShopeePdfStrictLabelPattern($label)
{
    $label = strtoupper((string) $label);
    $parts = preg_split('/[^A-Z0-9]+/', $label, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($parts)) {
        return '';
    }

    $wordPatterns = array();
    foreach ($parts as $part) {
        $chars = str_split($part);
        $charPattern = array();
        foreach ($chars as $ch) {
            $charPattern[] = preg_quote($ch, '/');
        }
        $wordPatterns[] = implode('\s*', $charPattern);
    }

    return implode('\s+', $wordPatterns);
}

function extractImportAmountTokensWithSign($text)
{
    $text = (string) $text;
    if ($text === '') {
        return array();
    }

    $tokens = array();
    if (preg_match_all('/-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.[0-9]{2}/i', $text, $matches)) {
        foreach ($matches[0] as $raw) {
            $rawStr = trim((string) $raw);
            $amount = normalizeImportAmount($rawStr);
            if ($amount === '') {
                continue;
            }

            $tokens[] = array(
                'amount' => $amount,
                'negative' => preg_match('/^\s*\-/', $rawStr) === 1,
                'raw' => $rawStr,
            );
        }
    }

    return $tokens;
}

function chooseImportAmountToken($tokens, $preferNegative = null)
{
    if (empty($tokens)) {
        return '';
    }

    if (isset($tokens[0]['amount']) && (string) $tokens[0]['amount'] !== '' && (float) $tokens[0]['amount'] == 0.0) {
        return number_format(0, 2, '.', '');
    }

    $wantNegative = $preferNegative === true;
    $wantPositive = $preferNegative === false;

    foreach ($tokens as $token) {
        $value = isset($token['amount']) ? (string) $token['amount'] : '';
        $isNegative = !empty($token['negative']);
        if ($value === '') {
            continue;
        }

        if ($wantNegative && $isNegative) {
            return $value;
        }
        if ($wantPositive && !$isNegative) {
            return $value;
        }
    }

    foreach ($tokens as $token) {
        $value = isset($token['amount']) ? (string) $token['amount'] : '';
        if ($value !== '') {
            return $value;
        }
    }

    return isset($tokens[0]['amount']) ? (string) $tokens[0]['amount'] : '';
}

function getShopeePdfMoneyBoundaryLabels()
{
    return array(
        'Product Price',
        'Merchandise Subtotal',
        'Deal Price',
        'Shopee Voucher',
        'Shop Voucher',
        'Seller Voucher',
        'Coins Redeemed',
        'Shopee Coins',
        'Vouchers & Rebates',
        'Shipping Fee Charged by Logistic Provider',
        'Estimated Shipping Fee Charged by Logistic Provider',
        'Shipping Fee Paid by Buyer',
        'Shipping Subtotal',
        'Estimated Shipping Subtotal',
        'Service Fee',
        'Transaction Fee',
        'Commission Fee',
        'Fees & Charges',
        'Order Income',
        'Estimated Order Income',
        'Final Amount',
    );
}

function extractShopeePdfAmountByAnchoredLabels($text, $labels, $boundaryLabels = array(), $maxLen = 220)
{
    $source = normalizeShopeePdfMoneyText($text);
    if ($source === '' || empty($labels)) {
        return '';
    }

    $maxLen = (int) $maxLen;
    if ($maxLen < 80) {
        $maxLen = 80;
    }
    if ($maxLen > 600) {
        $maxLen = 600;
    }

    $boundaryPatterns = array();
    foreach ((array) $boundaryLabels as $boundaryLabel) {
        $boundaryPattern = buildShopeePdfStrictLabelPattern($boundaryLabel);
        if ($boundaryPattern !== '') {
            $boundaryPatterns[] = $boundaryPattern;
        }
    }

    foreach ((array) $labels as $label) {
        $labelPattern = buildShopeePdfStrictLabelPattern($label);
        if ($labelPattern === '') {
            continue;
        }

        if (!preg_match_all('/' . $labelPattern . '/i', $source, $labelMatches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($labelMatches[0] as $labelMatch) {
            $start = (int) $labelMatch[1];
            if ($start < 0) {
                continue;
            }

            $segment = substr($source, $start, $maxLen);
            if ($segment === '') {
                continue;
            }

            $cutLen = strlen($segment);
            foreach ($boundaryPatterns as $boundaryPattern) {
                if (preg_match('/' . $boundaryPattern . '/i', $segment, $boundaryMatch, PREG_OFFSET_CAPTURE)) {
                    $boundaryOffset = (int) $boundaryMatch[0][1];
                    if ($boundaryOffset > 0 && $boundaryOffset < $cutLen) {
                        $cutLen = $boundaryOffset;
                    }
                }
            }

            $segment = substr($segment, 0, $cutLen);
            $amounts = extractImportAmountsFromText($segment);
            if (!empty($amounts)) {
                return $amounts[0];
            }
        }
    }

    return '';
}

function extractShopeePdfAmountByStrictLabels($text, $labels, $boundaryLabels = array(), $maxLen = 220, $preferNegative = null)
{
    $source = normalizeShopeePdfMoneyText($text);
    if ($source === '' || empty($labels)) {
        return '';
    }

    $maxLen = (int) $maxLen;
    if ($maxLen < 80) {
        $maxLen = 80;
    }
    if ($maxLen > 600) {
        $maxLen = 600;
    }

    $boundaryPatterns = array();
    foreach ((array) $boundaryLabels as $boundaryLabel) {
        $pattern = buildShopeePdfStrictLabelPattern($boundaryLabel);
        if ($pattern !== '') {
            $boundaryPatterns[] = $pattern;
        }
    }

    foreach ((array) $labels as $label) {
        $labelPattern = buildShopeePdfStrictLabelPattern($label);
        if ($labelPattern === '') {
            continue;
        }

        if (!preg_match_all('/' . $labelPattern . '/i', $source, $labelMatches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($labelMatches[0] as $labelMatch) {
            $labelStart = (int) $labelMatch[1];
            $labelLen = strlen((string) $labelMatch[0]);
            if ($labelStart < 0) {
                continue;
            }

            $segment = substr($source, $labelStart + $labelLen, $maxLen);
            if ($segment === '') {
                continue;
            }

            $cutLen = strlen($segment);
            foreach ($boundaryPatterns as $boundaryPattern) {
                if (preg_match('/' . $boundaryPattern . '/i', $segment, $boundaryMatch, PREG_OFFSET_CAPTURE)) {
                    $boundaryOffset = (int) $boundaryMatch[0][1];
                    if ($boundaryOffset > 0 && $boundaryOffset < $cutLen) {
                        $cutLen = $boundaryOffset;
                    }
                }
            }

            $segment = substr($segment, 0, $cutLen);
            $tokens = extractImportAmountTokensWithSign($segment);
            $amount = chooseImportAmountToken($tokens, $preferNegative);
            if ($amount !== '') {
                return $amount;
            }
        }
    }

    return '';
}

function extractShopeePdfAmountByLineLabels($text, $labels, $lineWindow = 1)
{
    $lines = getPdfTextLines($text);
    if (empty($lines) || empty($labels)) {
        return '';
    }

    $lineWindow = (int) $lineWindow;
    if ($lineWindow < 0) {
        $lineWindow = 0;
    }
    if ($lineWindow > 3) {
        $lineWindow = 3;
    }

    $labelPatterns = array();
    foreach ((array) $labels as $label) {
        $pattern = buildLooseAmountLabelPattern($label);
        if ($pattern !== '') {
            $labelPatterns[] = $pattern;
        }
    }

    if (empty($labelPatterns)) {
        return '';
    }

    $lineCount = count($lines);
    for ($i = 0; $i < $lineCount; $i++) {
        $lineUpper = strtoupper($lines[$i]);
        $matched = false;

        foreach ($labelPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $lineUpper)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        $maxJ = min($lineCount - 1, $i + $lineWindow);
        for ($j = $i; $j <= $maxJ; $j++) {
            $tokens = extractImportAmountTokensWithSign($lines[$j]);
            $amount = chooseImportAmountToken($tokens, null);
            if ($amount !== '') {
                return $amount;
            }
        }
    }

    return '';
}

function extractShopeePdfOcrDigitsAmountByLineLabels($text, $labels, $lineWindow = 0)
{
    $lines = getPdfTextLines($text);
    if (empty($lines) || empty($labels)) {
        return '';
    }

    $lineWindow = max(0, min(2, (int) $lineWindow));
    $labelPatterns = array();
    foreach ((array) $labels as $label) {
        $pattern = buildLooseAmountLabelPattern($label);
        if ($pattern !== '') {
            $labelPatterns[] = $pattern;
        }
    }

    if (empty($labelPatterns)) {
        return '';
    }

    $lineCount = count($lines);
    for ($i = 0; $i < $lineCount; $i++) {
        $lineUpper = strtoupper((string) $lines[$i]);
        $matched = false;

        foreach ($labelPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $lineUpper) === 1) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        $maxJ = min($lineCount - 1, $i + $lineWindow);
        for ($j = $i; $j <= $maxJ; $j++) {
            if (preg_match('/(?:^|[^0-9])(?:[\$\x{00A9}]?\s*)?([0-9]{3,6})(?![0-9])/u', (string) $lines[$j], $matches) === 1) {
                $digits = isset($matches[1]) ? (string) $matches[1] : '';
                if ($digits === '') {
                    continue;
                }

                $numeric = (float) $digits;
                if ($numeric <= 0) {
                    continue;
                }

                return number_format($numeric / 100, 2, '.', '');
            }
        }
    }

    return '';
}

function extractShopeePdfOcrDigitsAmountNearLabels($text, $labels, $maxLen = 80)
{
    $text = strtoupper((string) $text);
    if ($text === '' || empty($labels)) {
        return '';
    }

    $maxLen = max(30, min(200, (int) $maxLen));

    foreach ((array) $labels as $label) {
        $pattern = buildLooseAmountLabelPattern($label);
        if ($pattern === '') {
            continue;
        }

        if (!preg_match_all('/' . $pattern . '/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $labelStart = isset($match[1]) ? (int) $match[1] : -1;
            $labelText = isset($match[0]) ? (string) $match[0] : '';
            if ($labelStart < 0 || $labelText === '') {
                continue;
            }

            $segment = substr($text, $labelStart + strlen($labelText), $maxLen);
            if ($segment === '') {
                continue;
            }

            if (preg_match('/(?:^|[^0-9])(?:[\$\x{00A9}]?\s*)?([0-9]{3,6})(?![0-9])/u', $segment, $amountMatch) === 1) {
                $digits = isset($amountMatch[1]) ? (string) $amountMatch[1] : '';
                if ($digits === '') {
                    continue;
                }

                $numeric = (float) $digits;
                if ($numeric <= 0) {
                    continue;
                }

                return number_format($numeric / 100, 2, '.', '');
            }
        }
    }

    return '';
}

function extractShopeePdfOcrTransactionFeeDirect($text)
{
    $text = strtoupper((string) $text);
    if ($text === '') {
        return '';
    }

    if (preg_match('/TRANSACTION\s*FEE.{0,100}?(?:[\$\x{00A9}]?\s*)?([0-9]{3,6})(?![0-9])/u', $text, $matches) === 1) {
        $digits = isset($matches[1]) ? (string) $matches[1] : '';
        if ($digits !== '' && ctype_digit($digits) && (int) $digits > 0) {
            return number_format(((float) $digits) / 100, 2, '.', '');
        }
    }

    return '';
}

function extractShopeePdfOcrServiceFeeDirect($text)
{
    $text = strtoupper((string) $text);
    if ($text === '') {
        return '';
    }

    if (preg_match('/SERVICE\s*FEE.{0,100}?(?:[\$\x{00A9}]?\s*)?(5000|0000|000|0\.00)(?![0-9])/u', $text) === 1) {
        return '0.00';
    }

    return '';
}

function shopeePdfServiceFeeLooksLikeZeroOcr($text, $labels)
{
    $lines = getPdfTextLines($text);
    if (empty($lines) || empty($labels)) {
        return false;
    }

    $labelPatterns = array();
    foreach ((array) $labels as $label) {
        $pattern = buildLooseAmountLabelPattern($label);
        if ($pattern !== '') {
            $labelPatterns[] = $pattern;
        }
    }

    if (empty($labelPatterns)) {
        return false;
    }

    foreach ($lines as $line) {
        $lineUpper = strtoupper((string) $line);
        $matched = false;
        foreach ($labelPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $lineUpper) === 1) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        if (preg_match('/(?:^|[^0-9])(000|0000)(?![0-9])/', $lineUpper) === 1) {
            return true;
        }
    }

    return false;
}

function extractShopeePdfAmountFromCompact($compactText, $labels)
{
    $compactText = strtoupper((string) $compactText);
    if ($compactText === '') {
        return '';
    }

    foreach ((array) $labels as $label) {
        $labelKey = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) $label));
        if ($labelKey === '') {
            continue;
        }

        $pattern = '/' . $labelKey . '[A-Z]{0,40}?((?:RM|MYR|SGD|USD)?-?[0-9]{1,6}\.[0-9]{2})/';
        if (preg_match_all($pattern, $compactText, $matches) && !empty($matches[1])) {
            foreach ($matches[1] as $rawAmount) {
                $amount = normalizeImportAmount($rawAmount);
                if ($amount !== '') {
                    return $amount;
                }
            }
        }
    }

    return '';
}

function textContainsLoosePhrase($text, $phrase)
{
    $pattern = buildLooseAmountLabelPattern($phrase);
    if ($pattern === '') {
        return false;
    }

    return preg_match('/' . $pattern . '/i', strtoupper((string) $text)) === 1;
}

function extractShopeePdfAmountByLooseLabels($text, $labels)
{
    $source = strtoupper((string) $text);
    if ($source === '') {
        return '';
    }

    foreach ((array) $labels as $label) {
        $labelPattern = buildLooseAmountLabelPattern($label);
        if ($labelPattern === '') {
            continue;
        }

        $pattern = '/' . $labelPattern . '.{0,220}?(-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.[0-9]{2})/i';
        if (preg_match($pattern, $source, $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }
    }

    return '';
}

function extractShopeePdfVoucherAmount($text)
{
    $text = (string) $text;
    if (trim($text) === '') {
        return '';
    }
    
    $normalizedText = normalizeImportText($text);

    $labelPattern = '(?:shop|seller)\s+voucher\s+paid\s+by\s+seller';

    // Best case: label and amount are close in the full text.
    if (preg_match('/' . $labelPattern . '.{0,180}?-\s*(?:RM|MYR|SGD|USD)?\s*([0-9][0-9,]*\.[0-9]{2})/i', $normalizedText, $matches)) {
        return normalizeImportAmount($matches[1]);
    }

    $lines = getPdfTextLines($text);
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $lineText = trim((string) $lines[$i]);
        if ($lineText === '') {
            continue;
        }

        if (!preg_match('/\b(?:shop|seller)\s+voucher\s+paid\s+by\s+seller\b/i', $lineText)) {
            continue;
        }

        // Some PDF text extraction splits label and amount into nearby lines.
        $nearText = $lineText;
        for ($j = 1; $j <= 3; $j++) {
            if (isset($lines[$i + $j])) {
                $nearText .= ' ' . trim((string) $lines[$i + $j]);
            }
        }

        if (preg_match_all('/-\s*(?:RM|MYR|SGD|USD)?\s*([0-9][0-9,]*\.[0-9]{2})/i', $nearText, $matches) && !empty($matches[1])) {
            $amount = end($matches[1]);
            return normalizeImportAmount($amount);
        }
    }

    return '';
}

function extractShopeePdfMonetaryValues($text)
{
    $text = (string) $text;
    $boundaries = getShopeePdfMoneyBoundaryLabels();
    $serviceFeeLabels = array(
        'Service Fee',
        'Service Fee (Incl. GST)',
        'Service Fee (Incl. Gst)',
        'Service Fee Incl GST',
        'Service Fee Incl Gst',
    );
    $transactionFeeLabels = array(
        'Transaction Fee',
        'Transaction Fee (Incl. GST)',
        'Transaction Fee (Incl. Gst)',
        'Transaction Fee Incl GST',
        'Transaction Fee Incl Gst',
    );

    $productPrice = extractShopeePdfAmountByStrictLabels($text, ['Product Price', 'Merchandise Subtotal', 'Deal Price'], $boundaries, 260, false);
    if ($productPrice === '') {
        $productPrice = extractShopeePdfAmountByLooseLabels($text, ['Product Price', 'Merchandise Subtotal', 'Deal Price']);
    }
    if ($productPrice === '') {
        $compact = buildShopeePdfCompactText($text);
        $productPrice = extractShopeePdfAmountFromCompact($compact, ['Product Price', 'Merchandise Subtotal', 'Deal Price']);
    }

    $voucher = extractShopeePdfVoucherAmount($text);

    // Actual shipping must be based on Shipping Subtotal (not logistic provider fee lines).
    $shippingFee = extractShopeePdfAmountByStrictLabels($text, [
        'Shipping Subtotal',
        'Estimated Shipping Subtotal',
    ], $boundaries, 260, false);
    if ($shippingFee === '') {
        $shippingFee = extractShopeePdfAmountByLooseLabels($text, [
            'Shipping Subtotal',
            'Estimated Shipping Subtotal',
        ]);
    }

    $serviceFee = extractShopeePdfAmountByStrictLabels($text, $serviceFeeLabels, $boundaries, 220, true);
    if ($serviceFee === '') {
        $serviceFee = extractShopeePdfAmountByLineLabels($text, $serviceFeeLabels, 2);
    }
    if ($serviceFee === '') {
        $serviceFee = extractShopeePdfAmountByLooseLabels($text, $serviceFeeLabels);
    }
    if ($serviceFee === '') {
        $serviceFee = extractShopeePdfOcrServiceFeeDirect($text);
    }

    $transactionFee = extractShopeePdfAmountByStrictLabels($text, $transactionFeeLabels, $boundaries, 220, true);
    if ($transactionFee === '') {
        $transactionFee = extractShopeePdfAmountByLineLabels($text, $transactionFeeLabels, 2);
    }
    if ($transactionFee === '') {
        $transactionFee = extractShopeePdfAmountByLooseLabels($text, $transactionFeeLabels);
    }
    if ($transactionFee === '') {
        $transactionFee = extractShopeePdfOcrDigitsAmountByLineLabels($text, $transactionFeeLabels, 1);
    }
    if ($transactionFee === '') {
        $transactionFee = extractShopeePdfOcrDigitsAmountNearLabels($text, $transactionFeeLabels, 80);
    }
    if ($transactionFee === '') {
        $transactionFee = extractShopeePdfOcrTransactionFeeDirect($text);
    }

    $commissionFee = extractShopeePdfAmountByStrictLabels($text, ['Commission Fee'], $boundaries, 220, true);
    if ($commissionFee === '') {
        $commissionFee = extractShopeePdfAmountByLooseLabels($text, ['Commission Fee']);
    }

    $saverProgramFee = extractShopeePdfAmountByStrictLabels($text, ['Saver Programme Fee', 'Saver Program Fee'], $boundaries, 220, true);
    if ($saverProgramFee === '') {
        $saverProgramFee = extractShopeePdfAmountByLooseLabels($text, ['Saver Programme Fee', 'Saver Program Fee']);
    }

    if ($serviceFee === '' && $transactionFee !== '' && $commissionFee !== '' && $saverProgramFee !== '') {
        $directFees = parseShopeeOrderAmountByLabels($text, ['Fees & Charges']);
        if ($directFees !== '') {
            $derivedServiceFee = number_format(
                max(
                    0,
                    (float) $directFees
                    - (float) normalizeImportAmount($transactionFee)
                    - (float) normalizeImportAmount($commissionFee)
                    - (float) normalizeImportAmount($saverProgramFee)
                ),
                2,
                '.',
                ''
            );

            if (shopeePdfServiceFeeLooksLikeZeroOcr($text, $serviceFeeLabels) || (float) $derivedServiceFee == 0.0) {
                $serviceFee = $derivedServiceFee;
            }
        }
    }

    if ($transactionFee === '' && $commissionFee !== '' && $saverProgramFee !== '') {
        $directFees = parseShopeeOrderAmountByLabels($text, ['Fees & Charges']);
        $baseServiceFee = $serviceFee;

        if ($baseServiceFee === '' && shopeePdfServiceFeeLooksLikeZeroOcr($text, $serviceFeeLabels)) {
            $baseServiceFee = '0.00';
        }

        if ($directFees !== '' && $baseServiceFee !== '') {
            $derivedTransactionFee = number_format(
                max(
                    0,
                    (float) $directFees
                    - (float) normalizeImportAmount($commissionFee)
                    - (float) normalizeImportAmount($saverProgramFee)
                    - (float) normalizeImportAmount($baseServiceFee)
                ),
                2,
                '.',
                ''
            );

            if ((float) $derivedTransactionFee > 0) {
                $transactionFee = $derivedTransactionFee;
            }
        }
    }

    return array(
        'product_price' => $productPrice,
        'voucher' => $voucher,
        'act_shipping_fee' => $shippingFee,
        'service_fee' => $serviceFee,
        'trans_fee' => $transactionFee,
        'ams_fee' => $commissionFee,
        'saver_program_fee' => $saverProgramFee,
        'delivered_hint' => (
            textContainsLoosePhrase($text, 'parcel has been delivered to buyer') ||
            textContainsLoosePhrase($text, 'delivered to buyer') ||
            textContainsLoosePhrase($text, 'successfully delivered')
        ),
    );
}

function parseShopeeOrderAmountByLabels($text, $labels)
{
    $text = (string) $text;

    foreach ($labels as $label) {
        $currencyPattern = '/' . preg_quote($label, '/') . '.{0,220}?(-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*)(?!\s*%)/i';
        if (preg_match($currencyPattern, $text, $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }

        $numericPattern = '/' . preg_quote($label, '/') . '.{0,220}?(-?\s*[0-9][0-9,]*\.[0-9]{2})(?!\s*%)/i';
        if (preg_match($numericPattern, $text, $matches)) {
            $amount = normalizeImportAmount($matches[1]);
            if ($amount !== '') {
                return $amount;
            }
        }

        $looseLabelPattern = buildLooseAmountLabelPattern($label);
        if ($looseLabelPattern !== '') {
            $looseCurrencyPattern = '/' . $looseLabelPattern . '.{0,320}?(-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*)(?!\s*%)/i';
            if (preg_match($looseCurrencyPattern, strtoupper($text), $matches)) {
                $amount = normalizeImportAmount($matches[1]);
                if ($amount !== '') {
                    return $amount;
                }
            }

            $looseNumericPattern = '/' . $looseLabelPattern . '.{0,320}?(-?\s*[0-9][0-9,]*\.[0-9]{2})(?!\s*%)/i';
            if (preg_match($looseNumericPattern, strtoupper($text), $matches)) {
                $amount = normalizeImportAmount($matches[1]);
                if ($amount !== '') {
                    return $amount;
                }
            }
        }
    }

    return '';
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

function extractShopeeAmountNearLabel($text, $label)
{
    $normalizedText = normalizeImportText($text);
    $normalizedLabel = normalizeImportText($label);

    if ($normalizedText === '' || $normalizedLabel === '') {
        return '';
    }

    $labelPos = stripos($normalizedText, $normalizedLabel);
    if ($labelPos === false) {
        return '';
    }

    $afterLabel = trim(substr($normalizedText, $labelPos + strlen($normalizedLabel)));
    if ($afterLabel === '') {
        return '';
    }

    if (preg_match('/-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*(?!\s*%)/i', $afterLabel, $matches)) {
        $amount = normalizeImportAmount($matches[0]);
        if ($amount !== '') {
            return $amount;
        }
    }

    if (preg_match('/-?\s*[0-9][0-9,]*\.[0-9]{2}(?!\s*%)/', $afterLabel, $matches)) {
        $amount = normalizeImportAmount($matches[0]);
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
            $amount = extractShopeeAmountNearLabel($item->textContent, $label);
        }

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

function extractShopeeOrderPaymentSectionText($xpath)
{
    if (!($xpath instanceof DOMXPath)) {
        return '';
    }

    $sectionQueries = array(
        "//*[@data-testid='odp-order-payment']",
        "//*[@data-testid='odp-buyer-payment']",
        "//*[contains(@class,'income-container')]",
    );

    foreach ($sectionQueries as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            continue;
        }

        foreach ($nodes as $node) {
            $text = normalizeImportText($node->textContent);
            if ($text !== '') {
                return $text;
            }
        }
    }

    return '';
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

function normalizeShopeeBuyerUsernameCandidate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/^[^A-Za-z0-9._\-]+|[^A-Za-z0-9._\-]+$/', '', $value);
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return '';
    }

    if (strlen($value) < 4 || strlen($value) > 40) {
        return '';
    }

    return $value;
}

function getShopeeBuyerUsernameOcrVariants($value)
{
    $value = normalizeShopeeBuyerUsernameCandidate($value);
    if ($value === '') {
        return array();
    }

    $variants = array();

    $addVariant = function ($candidate) use (&$variants) {
        $candidate = normalizeShopeeBuyerUsernameCandidate($candidate);
        if ($candidate !== '' && !in_array($candidate, $variants, true)) {
            $variants[] = $candidate;
        }
    };

    $targetedFixed = $value;
    $targetedFixed = preg_replace('/([._-]?[o0])l(?=[0-9]|$)/i', '${1}1', $targetedFixed);
    $targetedFixed = preg_replace('/([0-9])l(?=[0-9]|$)/i', '${1}1', $targetedFixed);

    // Add fixed version first, then original.
    $addVariant($targetedFixed);
    $addVariant($value);

    return $variants;
}

function resolveShopeeBuyerUsernameOcrCandidate($value)
{
    $bestCandidate = '';
    $bestScore = -999;

    foreach (getShopeeBuyerUsernameOcrVariants($value) as $candidate) {
        $score = scoreShopeeBuyerUsernameCandidate($candidate);

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCandidate = $candidate;
        }
    }

    return $bestScore > 0 ? $bestCandidate : '';
}

function scoreShopeeBuyerUsernameCandidate($candidate)
{
    $candidate = normalizeShopeeBuyerUsernameCandidate($candidate);
    if ($candidate === '') {
        return -999;
    }

    $blacklist = array(
        'buyer',
        'follow',
        'fo11ow',
        'fo1low',
        'fol1ow',
        'f0llow',
        'f011ow',
        'chat',
        'now',
        'cancel',
        'cance1',
        'order',
        'view',
        'shipping',
        'details',
        'expand',
        'delivered',
        'parcel',
        'payment',
        'information',
        'logistic',
        'delivery',
    );

    if (in_array($candidate, $blacklist, true)) {
        return -999;
    }

    $score = 0;
    if (strpos($candidate, '_') !== false) {
        $score += 6;
    }
    if (preg_match('/\d/', $candidate)) {
        $score += 4;
    }
    if (preg_match('/^[a-z0-9._\-]+$/', $candidate)) {
        $score += 2;
    }
    if (preg_match('/[a-z]/', $candidate)) {
        $score += 2;
    }
    if (preg_match('/(?:^|_)[a-z0-9]*1+[a-z0-9]*$/', $candidate)) {
        $score += 3;
    }

        // Prefer OCR-corrected numeric suffix like o11 over OCR mistake ol1.
    if (preg_match('/(?:^|_)[a-z0-9]*[o0]1+[a-z0-9]*$/', $candidate)) {
        $score += 4;
    }

    if (preg_match('/(?:^|_)[a-z0-9]*[o0]l[0-9]*$/', $candidate)) {
        $score -= 3;
    }

    return $score;
}

function extractShopeeBuyerUsernameFromPdfText($text)
{
    $text = (string) $text;
    if (trim($text) === '') {
        return '';
    }

    $normalized = normalizeImportText($text);

    if (preg_match('/\b([A-Za-z0-9._\-]{4,40})\b\s+F\s*O\s*L\s*L\s*O\s*W\s+C\s*H\s*A\s*T\s+N\s*O\s*W\b/i', $normalized, $matches)) {
        $candidate = resolveShopeeBuyerUsernameOcrCandidate($matches[1]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    if (preg_match('/\b([A-Za-z0-9._\-]{4,40})\b\s+(?:B\s*=\s*)?C\s*H\s*A\s*T\s+N\s*O\s*W\b/i', $normalized, $matches)) {
        $candidate = resolveShopeeBuyerUsernameOcrCandidate($matches[1]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    $lines = getPdfTextLines($text);

    foreach ($lines as $line) {
        $lineText = trim((string) $line);
        if ($lineText === '' || preg_match('/CHAT\s+NOW/i', $lineText) !== 1) {
            continue;
        }

        $beforeChat = preg_replace('/CHAT\s+NOW.*$/i', '', $lineText);
        $beforeChat = preg_replace('/\bF\s*O\s*L\s*L\s*O\s*W\b.*$/i', '', $beforeChat);
        $beforeChat = trim((string) $beforeChat);

        if (preg_match_all('/[A-Za-z0-9._\-]{4,40}/', $beforeChat, $matches)) {
            $candidates = array_reverse($matches[0]);

            foreach ($candidates as $rawCandidate) {
                $candidate = normalizeShopeeBuyerUsernameCandidate($rawCandidate);

                if (
                    $candidate === '' ||
                    in_array($candidate, array(
                        'cancel',
                        'cance1',
                        'order',
                        'view',
                        'shipping',
                        'details',
                        'follow',
                        'fo11ow',
                        'chat',
                        'now',
                        'what',
                        'next',
                    ), true)
                ) {
                    continue;
                }

                $resolvedCandidate = resolveShopeeBuyerUsernameOcrCandidate($candidate);
                if ($resolvedCandidate !== '') {
                    return $resolvedCandidate;
                }
            }
        }
    }

    return '';
}

function extractShopeeBuyerUsername($html, $text)
{
    $html = (string) $html;
    $text = (string) $text;

    if (preg_match('/class="username\s+text-overflow"[^>]*>([^<]+)/i', $html, $matches)) {
        return normalizeImportText($matches[1]);
    }

    if (preg_match('/\bBuyer\s*Username\b[^A-Za-z0-9]*([A-Za-z0-9._\-]+)/i', $text, $matches)) {
        $candidate = normalizeShopeeBuyerUsernameCandidate($matches[1]);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    $pdfCandidate = extractShopeeBuyerUsernameFromPdfText($text);
    if ($pdfCandidate !== '') {
        return $pdfCandidate;
    }

    return '';
}

function extractShopeePdfAccountName($text)
{
    $text = normalizeImportText((string) $text);
    if ($text === '') {
        return '';
    }

    $lines = getPdfTextLines($text);
    foreach ($lines as $line) {
        if (preg_match_all('/\b([a-z0-9][a-z0-9._\-]{2,40})\b/', $line, $matches)) {
            foreach ($matches[1] as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate === '') {
                    continue;
                }
                if (strpos($candidate, '.') === false) {
                    continue;
                }
                if (strpos($candidate, '@') !== false) {
                    continue;
                }
                if (preg_match('/^\d+$/', $candidate)) {
                    continue;
                }
                if (preg_match('/^(https?|seller|shopee|global|main)$/i', $candidate)) {
                    continue;
                }
                if (stripos($candidate, 'beyourdiary') !== false) {
                    continue;
                }

                return normalizeImportText($candidate);
            }
        }
    }

    return '';
}

function isLikelyShopeeAccountName($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    if (strlen($value) < 5 || strlen($value) > 50) {
        return false;
    }

    if (!preg_match('/[a-z]/i', $value)) {
        return false;
    }

    if (preg_match('/^(ee|ok|yes|no)$/i', $value)) {
        return false;
    }

    if (stripos($value, 'assistant module') !== false) {
        return false;
    }

    return true;
}

function extractShopeePdfAccountNameFromBinary($rawContent)
{
    $rawContent = (string) $rawContent;
    if ($rawContent === '') {
        return '';
    }

    if (preg_match_all('/\b([a-z0-9][a-z0-9._\-]{2,40}\.[a-z0-9][a-z0-9._\-]{1,20})\b/', $rawContent, $matches)) {
        foreach ($matches[1] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (stripos($candidate, 'beyourdiary') !== false) {
                continue;
            }
            if (preg_match('/^(https?|seller|shopee)$/i', $candidate)) {
                continue;
            }
            return normalizeImportText($candidate);
        }
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
    <script src="finance/header/js/pdf.min.js"></script>
    <script src="finance/header/js/tesseract.min.js"></script>
    <style>
        .shopee-airbill-row {
            align-items: flex-start;
        }

        .shopee-airbill-toggle-col {
            display: flex;
            flex-direction: column;
        }

        .shopee-airbill-toggle-field {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            margin-top: 0;
            padding: 0;
        }

        .shopee-airbill-toggle-label {
            margin: 0;
        }

        @media (max-width: 767px) {
            .shopee-airbill-toggle-col {
                margin-top: 0;
            }
        }

        .shopee-airbill-toggle {
            position: relative;
            width: 54px;
            height: 28px;
            display: inline-flex;
            align-items: center;
        }

        .shopee-airbill-toggle input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .shopee-airbill-toggle-slider {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
            border-radius: 999px;
            background: #31343a;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle-slider::before {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle-slider::after {
            content: "\f00d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #ffffff;
            font-size: 0.62rem;
            position: absolute;
            right: 10px;
            top: 8px;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider {
            background: #6f922f;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider::before {
            left: 29px;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider::after {
            content: "\f00c";
            right: 32px;
        }

        .shopee-inline-error {
            display: block;
            margin-top: 6px;
            color: #dc3545;
            font-size: 0.875rem;
        }

        .shopee-inline-invalid {
            border-color: #dc3545 !important;
        }
    </style>
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
                            <form method="post" enctype="multipart/form-data" autocomplete="off" id="sorUploadForm">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-8">
                                        <label class="form-label" for="import_file">Shopee Order Details HTML/PDF File</label>
                                        <input class="form-control" type="file" name="import_file" id="import_file" accept=".html,.htm,.pdf" required>
                                        <input type="hidden" name="actionBtn" value="parseShopeeOrderReq">
                                        <input type="hidden" name="client_pdf_text" id="client_pdf_text" value="">
                                        <small class="text-muted d-block mt-2" id="sor_pdf_extract_status"></small>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" id="sorSubmitBtn">
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
                                <form method="post" enctype="multipart/form-data" autocomplete="off" data-shopee-import-preview="1" novalidate>
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
                                            <label class="form-label" for="buyer">Shopee Buyer Username<span class="requireRed">*</span></label>
                                            <?php
                                            $buyerDisplayValue = '';
                                            if (isset($previewData['buyer_name']) && trim((string) $previewData['buyer_name']) !== '') {
                                                $buyerDisplayValue = trim((string) $previewData['buyer_name']);
                                            } else if (isset($previewData['buyer']) && (string) $previewData['buyer'] !== '' && isset($shopeeBuyers[(int) $previewData['buyer']])) {
                                                $buyerDisplayValue = (string) $shopeeBuyers[(int) $previewData['buyer']];
                                            }
                                            ?>
                                            <div class="autocomplete">
                                                <input class="form-control" type="text" id="buyer" name="buyer" value="<?= htmlspecialchars($buyerDisplayValue) ?>" autocomplete="off" required data-required-message="Shopee Buyer Username is required.">
                                                <input type="hidden" id="buyer_hidden" name="buyer_hidden" value="<?= htmlspecialchars(isset($previewData['buyer']) ? (string) $previewData['buyer'] : '') ?>">
                                            </div>
                                            <?php if (!empty($previewData['source_buyer_username'])) { ?>
                                                <?php $detectedBuyerMissingInDb = empty($previewData['buyer']); ?>
                                                <div class="d-inline-flex align-items-center gap-2 mt-1">
                                                    <small class="text-muted mb-0">Detected: <?= htmlspecialchars($previewData['source_buyer_username']) ?><?= $detectedBuyerMissingInDb ? ' (Not in database)' : '' ?></small>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary btn-sm py-0 px-2"
                                                        id="use_detected_buyer_btn"
                                                        data-detected-buyer="<?= htmlspecialchars((string) $previewData['source_buyer_username'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Use detected buyer name"
                                                        aria-label="Use detected buyer name">
                                                        <i class="fa-solid fa-arrow-right"></i>
                                                    </button>
                                                </div>
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
                                            <label class="form-label" for="product_price">Product Price<span class="requireRed">*</span></label>
                                            <input class="form-control" type="number" step="0.01" id="product_price" name="product_price" value="<?= htmlspecialchars($previewData['product_price']) ?>" required>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="voucher">Voucher</label>
                                            <input class="form-control" type="number" step="0.01" id="voucher" name="voucher" value="<?= htmlspecialchars(isset($previewData['voucher']) ? $previewData['voucher'] : '0.00') ?>">
                                            <input type="hidden" name="voucher_detected" value="<?= htmlspecialchars(isset($previewData['voucher_detected']) ? $previewData['voucher_detected'] : 'no') ?>">
                                            <?php if ((isset($previewData['voucher_detected']) ? $previewData['voucher_detected'] : 'yes') !== 'yes') { ?>
                                                <small class="text-muted d-block mt-1">Not detected</small>
                                            <?php } ?>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="act_shipping_fee">Actual Shipping Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="act_shipping_fee" name="act_shipping_fee" value="<?= htmlspecialchars(isset($previewData['act_shipping_fee']) ? $previewData['act_shipping_fee'] : '0.00') ?>">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="service_fee">Service Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="service_fee" name="service_fee" value="<?= htmlspecialchars(isset($previewData['service_fee']) ? $previewData['service_fee'] : '0.00') ?>">
                                            <input type="hidden" name="service_fee_detected" value="<?= htmlspecialchars(isset($previewData['service_fee_detected']) ? $previewData['service_fee_detected'] : 'no') ?>">
                                            <?php if ((isset($previewData['service_fee_detected']) ? $previewData['service_fee_detected'] : 'yes') !== 'yes') { ?>
                                                <small class="text-muted d-block mt-1">Not detected</small>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="trans_fee">Transaction Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="trans_fee" name="trans_fee" value="<?= htmlspecialchars(isset($previewData['trans_fee']) ? $previewData['trans_fee'] : '0.00') ?>">
                                            <input type="hidden" name="trans_fee_detected" value="<?= htmlspecialchars(isset($previewData['trans_fee_detected']) ? $previewData['trans_fee_detected'] : 'no') ?>">
                                            <?php if ((isset($previewData['trans_fee_detected']) ? $previewData['trans_fee_detected'] : 'yes') !== 'yes') { ?>
                                                <small class="text-muted d-block mt-1">Not detected</small>
                                            <?php } ?>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="ams_fee">AMS / Commission Fee</label>
                                            <input class="form-control" type="number" step="0.01" id="ams_fee" name="ams_fee" value="<?= htmlspecialchars(isset($previewData['ams_fee']) ? $previewData['ams_fee'] : '0.00') ?>">
                                            <input type="hidden" name="ams_fee_detected" value="<?= htmlspecialchars(isset($previewData['ams_fee_detected']) ? $previewData['ams_fee_detected'] : 'no') ?>">
                                            <?php if ((isset($previewData['ams_fee_detected']) ? $previewData['ams_fee_detected'] : 'yes') !== 'yes') { ?>
                                                <small class="text-muted d-block mt-1">Not detected</small>
                                            <?php } ?>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="fees">Fees & Charges</label>
                                            <input class="form-control" type="number" step="0.01" id="fees" name="fees" value="<?= htmlspecialchars(isset($previewData['fees']) ? $previewData['fees'] : '0.00') ?>" readonly>
                                            <input type="hidden" name="fees_detected" value="<?= htmlspecialchars(isset($previewData['fees_detected']) ? $previewData['fees_detected'] : 'no') ?>">
                                            <input type="hidden" id="saver_program_fee" name="saver_program_fee" value="<?= htmlspecialchars(isset($previewData['saver_program_fee']) ? $previewData['saver_program_fee'] : '0.00') ?>">
                                            <small id="saver_program_fee_hint" class="text-muted <?= ((float) (isset($previewData['saver_program_fee']) ? $previewData['saver_program_fee'] : 0)) > 0 ? '' : 'd-none' ?>">
                                                Includes Saver Programme Fee: <?= htmlspecialchars(isset($previewData['saver_program_fee']) ? $previewData['saver_program_fee'] : '0.00') ?>
                                            </small>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="final_amt">Final Amount</label>
                                            <input class="form-control" type="number" step="0.01" id="final_amt" name="final_amt" value="<?= htmlspecialchars(isset($previewData['final_amt']) ? $previewData['final_amt'] : '0.00') ?>" readonly>
                                            <input type="hidden" name="final_amt_detected" value="<?= htmlspecialchars(isset($previewData['final_amt_detected']) ? $previewData['final_amt_detected'] : 'no') ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="remark">Remark</label>
                                            <input class="form-control" type="text" id="remark" name="remark" value="<?= htmlspecialchars(isset($previewData['remark']) ? $previewData['remark'] : '') ?>">
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="order_status_val">Initial Order Status</label>
                                            <select class="form-select" id="order_status_val" name="order_status_val">
                                                <?php foreach (shopeeOmsGetEditableStatusOptions() as $statusCode => $statusLabel) { ?>
                                                    <option value="<?= htmlspecialchars($statusCode) ?>" <?= ((isset($previewData['order_status_val']) ? $previewData['order_status_val'] : 'P') === $statusCode) ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>

                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="stock_out_warehouse_id">Stock Out Warehouse<span class="requireRed">*</span></label>
                                            <?php
                                            $currentStockOutWarehouseId = isset($previewData['stock_out_warehouse_id']) && (int) $previewData['stock_out_warehouse_id'] > 0
                                                ? (int) $previewData['stock_out_warehouse_id']
                                                : (int) $sorDefaultWarehouseId;
                                            ?>
                                            <select class="form-select" id="stock_out_warehouse_id" name="stock_out_warehouse_id" required>
                                                <?php foreach ($sorWarehouseRows as $warehouseRow) { ?>
                                                    <?php $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0; ?>
                                                    <option value="<?= $warehouseId ?>" <?= $currentStockOutWarehouseId === $warehouseId ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars((string) $warehouseRow['name']) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3 shopee-airbill-row">
                                        <div class="col-12 col-md-2 shopee-airbill-toggle-col">
                                            <?php $previewUpdateAirbillValue = (isset($previewData['update_airbill']) ? $previewData['update_airbill'] : 'yes') === 'yes' ? 'yes' : 'no'; ?>
                                            <input type="hidden" id="update_airbill" name="update_airbill" value="<?= htmlspecialchars($previewUpdateAirbillValue) ?>">
                                            <label class="form-label shopee-airbill-toggle-label" for="update_airbill_toggle">Update Airbill?</label>
                                            <div class="shopee-airbill-toggle-field">
                                                <label class="shopee-airbill-toggle mb-0" for="update_airbill_toggle">
                                                    <input type="checkbox" id="update_airbill_toggle" <?= $previewUpdateAirbillValue === 'yes' ? 'checked' : '' ?>>
                                                    <span class="shopee-airbill-toggle-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label" for="airbill_no">Airbill No<span class="requireRed">*</span></label>
                                            <input class="form-control" type="text" id="airbill_no" name="airbill_no" value="<?= htmlspecialchars(isset($previewData['airbill_no']) ? $previewData['airbill_no'] : '') ?>">
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="airbill_attachment">Airbill Attachment<span class="requireRed">*</span></label>
                                            <input class="form-control" type="file" id="airbill_attachment" name="airbill_attachment">
                                            <small id="airbill_extract_status" class="d-block mt-1 text-muted"></small>
                                            <?php if (!empty($previewData['airbill_attachment'])) { ?>
                                                <small class="text-danger d-block mt-1">Current Attachment: <?= htmlspecialchars($previewData['airbill_attachment']) ?></small>
                                            <?php } ?>
                                            <input type="hidden" id="airbill_attachment_value" name="airbill_attachment_value" value="<?= htmlspecialchars(isset($previewData['airbill_attachment']) ? $previewData['airbill_attachment'] : '') ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-6 offset-md-6">
                                            <?php
                                            $previewAttachmentSrc = '';
                                            if (!empty($previewData['airbill_attachment'])) {
                                                $storedAttachment = trim(str_replace('\\', '/', (string) $previewData['airbill_attachment']), '/');
                                                if (strpos($storedAttachment, 'attachment/') === 0) {
                                                    $previewAttachmentSrc = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
                                                } else {
                                                    $previewAttachmentSrc = $sorAirbillAttachmentUrl . basename($storedAttachment);
                                                }
                                            }
                                            ?>
                                            <div class="d-flex justify-content-center justify-content-md-end px-4">
                                                <img id="airbill_attachment_preview" src="<?= htmlspecialchars($previewAttachmentSrc) ?>" class="img-thumbnail" alt="Airbill Attachment Preview">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label" for="customer_address">Customer Address<span class="requireRed">*</span></label>
                                            <textarea class="form-control" id="customer_address" name="customer_address" rows="2"><?= htmlspecialchars(isset($previewData['customer_address']) ? $previewData['customer_address'] : '') ?></textarea>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-center flex-wrap mt-4">
                                        <?php if ($previewData['missing_sku']) { ?>
                                            <div class="alert alert-warning mb-3 w-100" style="max-width: 975px;">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Package was not matched automatically. Please select the correct package manually before inserting.
                                            </div>
                                        <?php } ?>
                                        <div class="d-flex justify-content-center gap-2 flex-wrap w-100">
                                            <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertShopeeOrderReq">
                                                <i class="fa-solid fa-database"></i> INSERT
                                            </button>
                                            <button class="btn btn-lg btn-rounded btn-secondary px-4" type="button" onclick="window.location.href='<?= $SITEURL ?>/shopee_order_import.php'">
                                                CANCEL
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
    (function resetShopeeImportStoredValues() {
        try {
            localStorage.setItem('page', 'invalid');
            localStorage.setItem('action', '');
            document.querySelectorAll('input[id], textarea[id], select[id]').forEach(function (field) {
                if (field.id) {
                    localStorage.removeItem(field.id);
                }
            });
        } catch (error) {
            // Ignore storage access issues and continue rendering the page.
        }
    })();

    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    preloader(0, '');
    setButtonColor();
    <?php if ($module === 'shopee_order_req') { ?>
        var action = 'I'; // Fake action to satisfy the JS script's logic
        <?php include "js/shopee_order_req.js"; ?>
    <?php } ?>

    (function syncShopeeImportPreviewForm() {
        var previewForm = document.querySelector('form[data-shopee-import-preview="1"]');
        if (!previewForm) return;
        var shopeeBuyerOptions = <?= json_encode($shopeeBuyers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        previewForm.querySelectorAll('input').forEach(function (input) {
            input.setAttribute('autocomplete', 'off');
            if (input.type === 'file') return;

            var serverValue = input.getAttribute('value');
            if (serverValue !== null) {
                input.value = serverValue;
            }
        });

        previewForm.querySelectorAll('select').forEach(function (select) {
            select.setAttribute('autocomplete', 'off');

            var selectedOption = select.querySelector('option[selected]');
            if (selectedOption) {
                select.value = selectedOption.value;
                return;
            }

            if (select.options.length > 0) {
                select.selectedIndex = 0;
            }
        });

        function getInlineErrorMessage(field) {
            if (!field) return 'This field is required.';
            var customMessage = field.getAttribute('data-required-message');
            if (customMessage) return customMessage;

            var fieldId = field.id;
            if (fieldId) {
                var label = previewForm.querySelector('label[for="' + fieldId + '"]');
                if (label) {
                    var labelText = (label.textContent || '').replace(/\*/g, '').trim();
                    if (labelText !== '') {
                        return labelText + ' is required.';
                    }
                }
            }

            return 'This field is required.';
        }

        function normalizeLookup(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '');
        }

        function syncBuyerHiddenField() {
            var buyerInput = previewForm.querySelector('#buyer');
            var buyerHidden = previewForm.querySelector('#buyer_hidden');
            if (!buyerInput || !buyerHidden) return;

            var normalizedInput = normalizeLookup(buyerInput.value);
            buyerHidden.value = '';
            if (normalizedInput === '') {
                return;
            }

            Object.keys(shopeeBuyerOptions).some(function (id) {
                if (normalizeLookup(shopeeBuyerOptions[id]) === normalizedInput) {
                    buyerHidden.value = id;
                    buyerInput.value = shopeeBuyerOptions[id];
                    return true;
                }
                return false;
            });
        }

        function clearInlineError(field) {
            if (!field) return;
            field.classList.remove('shopee-inline-invalid');
            var next = field.nextElementSibling;
            if (next && next.classList.contains('shopee-inline-error')) {
                next.remove();
            }
        }

        function showInlineError(field, message) {
            if (!field) return;
            clearInlineError(field);
            field.classList.add('shopee-inline-invalid');
            var errorNode = document.createElement('small');
            errorNode.className = 'shopee-inline-error';
            errorNode.textContent = message;
            field.insertAdjacentElement('afterend', errorNode);
        }

        function validatePreviewForm() {
            var firstInvalidField = null;
            var requiredFields = previewForm.querySelectorAll('input[required], select[required], textarea[required]');
            requiredFields.forEach(function (field) {
                clearInlineError(field);
                if (field.disabled) {
                    return;
                }

                var isEmpty = false;
                if (field.type === 'file') {
                    var existingAttachment = previewForm.querySelector('#airbill_attachment_value');
                    var hasExistingAttachment = !!(existingAttachment && existingAttachment.value.trim() !== '');
                    isEmpty = field.files.length === 0 && !hasExistingAttachment;
                } else if (field.type === 'checkbox' || field.type === 'radio') {
                    isEmpty = !field.checked;
                } else {
                    isEmpty = field.value.trim() === '';
                }

                if (isEmpty) {
                    showInlineError(field, getInlineErrorMessage(field));
                    if (!firstInvalidField) {
                        firstInvalidField = field;
                    }
                }
            });

            if (firstInvalidField) {
                firstInvalidField.focus();
                return false;
            }

            return true;
        }

        function toggleAirbillFields() {
            var updateAirbill = previewForm.querySelector('#update_airbill');
            var updateAirbillToggle = previewForm.querySelector('#update_airbill_toggle');
            var airbillNo = previewForm.querySelector('#airbill_no');
            var airbillAttachment = previewForm.querySelector('#airbill_attachment');
            var customerAddress = previewForm.querySelector('#customer_address');
            var existingAttachment = previewForm.querySelector('#airbill_attachment_value');
            if (!updateAirbill || !updateAirbillToggle || !airbillNo || !airbillAttachment || !customerAddress) return;

            updateAirbill.value = updateAirbillToggle.checked ? 'yes' : 'no';
            var enabled = updateAirbillToggle.checked;
            airbillNo.disabled = !enabled;
            airbillAttachment.disabled = !enabled;
            customerAddress.disabled = !enabled;
            airbillNo.required = enabled;
            customerAddress.required = enabled;
            airbillAttachment.required = enabled && (!existingAttachment || existingAttachment.value.trim() === '');
            [airbillNo, airbillAttachment, customerAddress].forEach(clearInlineError);
        }

        <?= shopeeOmsRenderAirbillPdfAutofillScript() ?>

        toggleAirbillFields();
        if (window.shopeeOmsAirbillPdfAutofill) {
            window.shopeeOmsAirbillPdfAutofill.bind({
                fileInputSelector: '#airbill_attachment',
                airbillNoSelector: '#airbill_no',
                customerAddressSelector: '#customer_address',
                statusSelector: '#airbill_extract_status',
                workerSrc: 'finance/header/js/pdf.worker.min.js',
                errorClass: 'text-danger',
                normalClass: 'text-muted'
            });
        }
        var updateAirbillToggle = previewForm.querySelector('#update_airbill_toggle');
        if (updateAirbillToggle) {
            updateAirbillToggle.addEventListener('change', toggleAirbillFields);
        }

        function clearBuyerAutocompleteBox() {
        var buyerInput = previewForm.querySelector('#buyer');
        if (!buyerInput) return;

        var autocompleteBox = buyerInput.closest('.autocomplete');
        if (!autocompleteBox) return;

        Array.prototype.slice.call(autocompleteBox.children).forEach(function (child) {
            if (child === buyerInput || child.id === 'buyer' || child.id === 'buyer_hidden') {
                return;
            }

            child.remove();
        });
    }

    var buyerInput = previewForm.querySelector('#buyer');
    var useDetectedBuyerBtn = previewForm.querySelector('#use_detected_buyer_btn');
    if (buyerInput) {
        buyerInput.addEventListener('keyup', function () {
            var buyerHidden = previewForm.querySelector('#buyer_hidden');

            var param = {
                search: buyerInput.value,
                searchType: 'buyer_username',
                elementID: 'buyer',
                hiddenElementID: 'buyer_hidden',
                dbTable: '<?= SHOPEE_CUST_INFO ?>',
            };

            if (typeof searchInput === 'function') {
                searchInput(param, '<?= $SITEURL ?>');

                setTimeout(function () {
                    syncBuyerHiddenField();

                    if (!buyerHidden || buyerHidden.value.trim() === '') {
                        clearBuyerAutocompleteBox();
                    }
                }, 80);

                setTimeout(function () {
                    syncBuyerHiddenField();

                    if (!buyerHidden || buyerHidden.value.trim() === '') {
                        clearBuyerAutocompleteBox();
                    }
                }, 200);
            }

            syncBuyerHiddenField();
        });

        buyerInput.addEventListener('input', function () {
            var buyerHidden = previewForm.querySelector('#buyer_hidden');

            setTimeout(function () {
                syncBuyerHiddenField();

                if (!buyerHidden || buyerHidden.value.trim() === '') {
                    clearBuyerAutocompleteBox();
                }
            }, 100);
        });

        buyerInput.addEventListener('change', function () {
            syncBuyerHiddenField();
            clearBuyerAutocompleteBox();
        });

        buyerInput.addEventListener('blur', function () {
            syncBuyerHiddenField();
            setTimeout(clearBuyerAutocompleteBox, 80);
        });

        syncBuyerHiddenField();
    }

    if (buyerInput && useDetectedBuyerBtn) {
        useDetectedBuyerBtn.addEventListener('click', function () {
            var detectedBuyer = String(useDetectedBuyerBtn.getAttribute('data-detected-buyer') || '').trim();
            if (detectedBuyer === '') {
                return;
            }

            buyerInput.value = detectedBuyer;
            syncBuyerHiddenField();
            clearBuyerAutocompleteBox();
            clearInlineError(buyerInput);
            buyerInput.focus();
        });
    }

        var airbillAttachmentInput = previewForm.querySelector('#airbill_attachment');
        if (airbillAttachmentInput) {
            airbillAttachmentInput.addEventListener('change', function () {
                clearInlineError(this);
                if (typeof previewImage === 'function') {
                    previewImage(this, 'airbill_attachment_preview');
                }
            });
        }

        previewForm.querySelectorAll('input, select, textarea').forEach(function (field) {
            var eventName = field.tagName === 'SELECT' || field.type === 'file' ? 'change' : 'input';
            field.addEventListener(eventName, function () {
                if (field.disabled) {
                    clearInlineError(field);
                    return;
                }

                if (field.type === 'file') {
                    var existingAttachment = previewForm.querySelector('#airbill_attachment_value');
                    if (field.files.length > 0 || (existingAttachment && existingAttachment.value.trim() !== '')) {
                        clearInlineError(field);
                    }
                    return;
                }

                if (field.value.trim() !== '') {
                    clearInlineError(field);
                }
            });
        });

        previewForm.addEventListener('submit', function (event) {
            if (!validatePreviewForm()) {
                event.preventDefault();
            }
        });
    })();
</script>
<script>
    (function () {
        var uploadForm = document.getElementById('sorUploadForm');
        var fileInput = document.getElementById('import_file');
        var clientPdfTextField = document.getElementById('client_pdf_text');
        var submitBtn = document.getElementById('sorSubmitBtn');
        var statusNode = document.getElementById('sor_pdf_extract_status');
        if (!uploadForm || !fileInput || !clientPdfTextField || !submitBtn || typeof pdfjsLib === 'undefined') {
            return;
        }

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'finance/header/js/pdf.worker.min.js';

        var clientPdfSubmitReady = false;

        function setStatus(message, isError) {
            if (!statusNode) {
                return;
            }

            statusNode.textContent = message;
            statusNode.classList.toggle('text-danger', !!isError);
            statusNode.classList.toggle('text-muted', !isError);
        }

        function setSubmittingState(isSubmitting, label) {
            submitBtn.disabled = !!isSubmitting;
            submitBtn.innerHTML = isSubmitting
                ? '<i class="fa-solid fa-spinner fa-spin"></i> ' + label
                : '<i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze';
        }

        function readFileAsArrayBuffer(file) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onload = function (event) {
                    resolve(event.target.result);
                };
                reader.onerror = reject;
                reader.readAsArrayBuffer(file);
            });
        }

        function extractPdfTextViaTextLayer(file) {
            return readFileAsArrayBuffer(file).then(function (buffer) {
                return pdfjsLib.getDocument({
                    data: new Uint8Array(buffer)
                }).promise;
            }).then(function (pdfDoc) {
                var pageTasks = [];

                for (var pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                    pageTasks.push(
                        pdfDoc.getPage(pageNumber).then(function (page) {
                            return page.getTextContent().then(function (textContent) {
                                return (textContent.items || []).map(function (item) {
                                    return typeof item.str === 'string' ? item.str.trim() : '';
                                }).filter(function (text) {
                                    return text !== '';
                                }).join(' ');
                            }).catch(function () {
                                return '';
                            });
                        })
                    );
                }

                return Promise.all(pageTasks).then(function (pageTexts) {
                    return pageTexts.join('\n').trim();
                });
            });
        }

        function extractPdfTextViaOcr(file) {
            if (typeof Tesseract === 'undefined') {
                return Promise.resolve('');
            }

            return readFileAsArrayBuffer(file).then(function (buffer) {
                return pdfjsLib.getDocument({
                    data: new Uint8Array(buffer)
                }).promise;
            }).then(function (pdfDoc) {
                var pageTexts = [];
                var sequence = Promise.resolve();

                for (var pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                    (function (currentPageNumber) {
                        sequence = sequence.then(function () {
                            return pdfDoc.getPage(currentPageNumber).then(function (page) {
                                var viewport = page.getViewport({ scale: 2.0 });
                                var canvas = document.createElement('canvas');
                                var context = canvas.getContext('2d');
                                canvas.width = viewport.width;
                                canvas.height = viewport.height;

                                if (!context) {
                                    pageTexts.push('');
                                    return;
                                }

                                return page.render({
                                    canvasContext: context,
                                    viewport: viewport
                                }).promise.then(function () {
                                    return Tesseract.recognize(canvas, 'eng').then(function (result) {
                                        pageTexts.push(
                                            result && result.data && result.data.text
                                                ? String(result.data.text).trim()
                                                : ''
                                        );
                                    }).catch(function () {
                                        pageTexts.push('');
                                    });
                                }).catch(function () {
                                    pageTexts.push('');
                                });
                            }).catch(function () {
                                pageTexts.push('');
                            });
                        });
                    })(pageNumber);
                }

                return sequence.then(function () {
                    return pageTexts.join('\n').trim();
                });
            }).catch(function () {
                return '';
            });
        }

        function extractPdfTextViaBrowser(file) {
            return extractPdfTextViaTextLayer(file).then(function (text) {
                if (String(text || '').trim() !== '') {
                    return text;
                }

                setStatus('No embedded PDF text detected. Running OCR fallback...', false);
                setSubmittingState(true, 'Running OCR...');
                return extractPdfTextViaOcr(file);
            });
        }

        fileInput.addEventListener('change', function () {
            clientPdfSubmitReady = false;
            clientPdfTextField.value = '';
            setSubmittingState(false, '');
            setStatus('', false);
        });

        uploadForm.addEventListener('submit', function (event) {
            if (clientPdfSubmitReady) {
                return;
            }

            var selectedFile = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!selectedFile || !/\.pdf$/i.test(String(selectedFile.name || ''))) {
                return;
            }

            event.preventDefault();
            clientPdfTextField.value = '';
            setSubmittingState(true, 'Reading PDF...');
            setStatus('Extracting text from the PDF before upload...', false);

            extractPdfTextViaBrowser(selectedFile).then(function (text) {
                clientPdfTextField.value = text;
                clientPdfSubmitReady = true;
                setStatus(
                    text !== ''
                        ? 'PDF text extracted. Loading preview...'
                        : 'Browser extraction found no readable text. Trying the server parser...',
                    text === ''
                );
                uploadForm.submit();
            }).catch(function () {
                clientPdfSubmitReady = true;
                setStatus('Unable to read this PDF in the browser. Trying the server parser...', true);
                uploadForm.submit();
            });
        });
    })();
</script>

</html>
