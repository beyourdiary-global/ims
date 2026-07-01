<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$currentPagePin = 0;
$parentPageTitle = 'Lazada Order Request';
$pageTitle = '';

include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/../checkCurrentPagePin.php';
include_once ROOT . '/include/import_pdf_common.php';

$lazadaOrderPinAccess = checkPinByGroupId($connect, 93);
$resolvedParentPageTitle = getPinGroupNameById($connect, 93);
if ($resolvedParentPageTitle !== '') {
    $parentPageTitle = $resolvedParentPageTitle;
}

$breadcrumbTitle = $parentPageTitle . ' Import';
$pageTitle = $breadcrumbTitle;
$pageHeading = $parentPageTitle . ' Import';

if (!is_array($lazadaOrderPinAccess) || count($lazadaOrderPinAccess) === 0 || !isActionAllowed('Import', $lazadaOrderPinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$redirectPage = $SITEURL . '/import/common_import.php';
$lazadaOrderRedirectPage = $SITEURL . '/finance/lazada_order_req_table.php';
$action = post('actionBtn');
$allowedActions = array('parseLazadaOrderReq', 'insertLazadaOrderReq');
if ($action !== '' && !in_array($action, $allowedActions, true)) {
    $action = '';
}

if (isset($_POST['cancelImportBtn']) || $action === 'cancelImport') {
    echo '<script>location.href = "' . $SITEURL . '/import/lazada_order_import.php";</script>';
    exit;
}

$importErrors = array();
$importWarnings = array();
$previewData = array();
$orderNumberFieldError = '';
$stockOutWarehouseError = '';
$allowedAttachmentExt = array('png', 'jpg', 'jpeg', 'pdf');
$airbillAttachmentBaseUrl = rtrim((string) $SITEURL, '/') . '/';

$countryOptions = getImportOptionList(COUNTRIES, 'name', $connect);
$userOptions = getImportOptionList(USR_USER, 'name', $connect);
$currencyOptions = getImportOptionList(CUR_UNIT, 'unit', $connect);
$brandOptions = getImportOptionList(BRAND, 'name', $connect);
$seriesOptions = getImportOptionList(BRD_SERIES, 'name', $connect);
$customerOptions = lazadaImportLoadCustomerOptions($connect);
$customerNames = array();
foreach ($customerOptions as $customerOptionId => $customerOptionRow) {
    $customerNames[$customerOptionId] = isset($customerOptionRow['name']) ? (string) $customerOptionRow['name'] : '';
}
$packageMeta = lazadaImportLoadPackageMeta($connect);
$packageNameOptions = array();
foreach ($packageMeta as $packageId => $packageRow) {
    $packageNameOptions[$packageId] = isset($packageRow['name']) ? (string) $packageRow['name'] : '';
}

$warehouseRows = shopeeOmsLoadActiveWarehouses($connect);
$warehouseOptionMap = array();
foreach ($warehouseRows as $warehouseRow) {
    $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0;
    if ($warehouseId > 0) {
        $warehouseOptionMap[$warehouseId] = isset($warehouseRow['name']) ? (string) $warehouseRow['name'] : ('Warehouse #' . $warehouseId);
    }
}
$defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect, $warehouseRows);
$defaultCountryId = resolveImportOptionId('Malaysia', $countryOptions, array('Malaysia', 'MY'));
$defaultCurrencyId = resolveImportOptionId('MYR', $currencyOptions, array('RM', 'Malaysian Ringgit'));
$defaultStatusCode = 'P';
$defaultStatusLabel = shopeeOmsGetStatusLabel($defaultStatusCode);
$initialStatusOptions = function_exists('shopeeOmsGetEditableStatusOptions') ? shopeeOmsGetEditableStatusOptions() : array();
if (empty($initialStatusOptions)) {
    $initialStatusOptions = array($defaultStatusCode => $defaultStatusLabel);
}
if (!isset($initialStatusOptions[$defaultStatusCode])) {
    $initialStatusOptions = array($defaultStatusCode => $defaultStatusLabel) + $initialStatusOptions;
}

if ($action === 'parseLazadaOrderReq') {
    if (!isset($_FILES['import_file'])) {
        $importErrors[] = 'Please choose a Lazada Order PDF file.';
    } else if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'File upload failed. Error Code: ' . $_FILES['import_file']['error'];
    } else if ($_FILES['import_file']['size'] > 8 * 1024 * 1024) {
        $importErrors[] = 'The uploaded file exceeds the maximum allowed size of 8MB.';
    } else {
        $uploadedName = isset($_FILES['import_file']['name']) ? (string) $_FILES['import_file']['name'] : '';
        $extension = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            $importErrors[] = 'Only PDF files are supported for Lazada Order Import.';
        } else {
            $rawContent = @file_get_contents($_FILES['import_file']['tmp_name']);
            if ($rawContent === false || (string) $rawContent === '') {
                $importErrors[] = 'The uploaded PDF could not be read.';
            } else if (strncmp($rawContent, '%PDF-', 5) !== 0) {
                $importErrors[] = 'The uploaded file is not a valid PDF document.';
            } else {
                $clientPdfText = trim((string) post('client_pdf_text'));
                if ($clientPdfText !== '') {
                    $clientPdfText = mb_strcut($clientPdfText, 0, 512 * 1024, 'UTF-8');
                }

                $serverPdfText = (string) extractTextFromPdfContent($rawContent);
                $rawText = trim($serverPdfText . "\n" . $clientPdfText);
                $sourceText = lazadaImportNormalizeSourceText($rawText);

                if ($sourceText === '') {
                    $importErrors[] = 'Unable to extract text from the uploaded PDF file.';
                } else {
                    $orderNumber = lazadaImportExtractOrderNumber($sourceText);
                    $customerName = lazadaImportExtractCustomerName($sourceText);
                    $shippingAddress = lazadaImportExtractShippingAddress($sourceText);
                    $sku = lazadaImportExtractSellerSku($sourceText);
                    $price = lazadaImportExtractItemPrice($sourceText, $sku);
                    $voucher = lazadaImportExtractVoucher($sourceText);
                    $paidPrice = lazadaImportExtractPaidPrice($sourceText, $sku);
                    $paymentMethod = lazadaImportExtractPaymentMethod($sourceText);
                    if ($orderNumber === '') {
                        $importErrors[] = 'Order Number could not be detected from the uploaded Lazada invoice PDF.';
                    } else if (lazadaImportIsDuplicateOrderNumber($orderNumber, $connect)) {
                        $orderNumberFieldError = 'Duplicate Order Number found in Lazada Order Request records.';
                    }

                    $customerId = resolveImportOptionId($customerName, $customerNames);
                    $packageId = lazadaImportResolvePackageIdFromSku($sku, $packageMeta);
                    $selectedPackage = $packageId !== '' && isset($packageMeta[(int) $packageId]) ? $packageMeta[(int) $packageId] : array();
                    $brandId = isset($selectedPackage['brand_id']) ? (string) $selectedPackage['brand_id'] : '';
                    $seriesId = isset($selectedPackage['series_id']) ? (string) $selectedPackage['series_id'] : '';
                    $brandName = $brandId !== '' && isset($brandOptions[(int) $brandId]) ? (string) $brandOptions[(int) $brandId] : '';
                    $seriesName = $seriesId !== '' && isset($seriesOptions[(int) $seriesId]) ? (string) $seriesOptions[(int) $seriesId] : '';
                    $packageName = $packageId !== '' && isset($packageNameOptions[(int) $packageId]) ? (string) $packageNameOptions[(int) $packageId] : '';
                    $price = lazadaImportNormalizeMoney($price);
                    $voucher = lazadaImportNormalizeMoney($voucher);
                    $paidPrice = lazadaImportNormalizeMoney($paidPrice);

                    $previewData = array(
                        'order_number' => $orderNumber,
                        'customer_id' => (string) $customerId,
                        'customer_name' => $customerName,
                        'source_customer_name' => $customerName,
                        'shipping_address' => $shippingAddress,
                        'sku' => $packageId !== '' ? $sku : '',
                        'detected_sku' => $sku,
                        'sku_matched' => $packageId !== '' ? 'yes' : 'no',
                        'package_id' => (string) $packageId,
                        'package_name' => $packageName,
                        'brand_id' => $brandId,
                        'brand_name' => $brandName,
                        'series_id' => $seriesId,
                        'series_name' => $seriesName,
                        'price' => $price !== '' ? $price : '0.00',
                        'voucher' => $voucher !== '' ? $voucher : '0.00',
                        'paid_price' => $paidPrice !== '' ? $paidPrice : '0.00',
                        'country_id' => (string) $defaultCountryId,
                        'country_name' => $defaultCountryId !== '' && isset($countryOptions[(int) $defaultCountryId]) ? (string) $countryOptions[(int) $defaultCountryId] : 'Malaysia',
                        'sales_pic' => (string) USER_NAME,
                        'payment_method' => $paymentMethod,
                        'order_status' => $defaultStatusLabel,
                        'order_status_val' => $defaultStatusCode,
                        'stock_out_warehouse_id' => (int) $defaultWarehouseId,
                        'update_airbill' => 'yes',
                        'airbill_no' => '',
                        'airbill_attachment' => '',
                        'remark' => $orderNumber !== '' ? ('Imported from Lazada Invoice PDF (' . $orderNumber . ')') : 'Imported from Lazada Invoice PDF',
                    );

                    if ($customerName !== '' && $customerId === '') {
                        $importWarnings[] = 'Customer Name "' . $customerName . '" was detected but not matched in Lazada Customer records. It will be auto-created during insert if you keep this customer name.';
                    }
                    if ($sku !== '' && $packageId === '') {
                        $importWarnings[] = 'SKU "' . $sku . '" was detected but not matched to any active package. Please select the correct package manually.';
                    }
                    if ($seriesId === '' && $packageId !== '') {
                        $importWarnings[] = 'Series could not be auto-resolved from the matched package. Please confirm the correct series before inserting.';
                    }
                }
            }
        }
    }
} else if ($action === 'insertLazadaOrderReq') {
    $customerNameInput = trim((string) postSpaceFilter('customer_name'));
    $customerHidden = trim((string) postSpaceFilter('customer_hidden'));
    $packageNameInput = trim((string) postSpaceFilter('package_name'));
    $packageHidden = trim((string) postSpaceFilter('package_hidden'));
    $brandNameInput = trim((string) postSpaceFilter('brand_name'));
    $brandHidden = trim((string) postSpaceFilter('brand_hidden'));
    $seriesNameInput = trim((string) postSpaceFilter('series_name'));
    $seriesHidden = trim((string) postSpaceFilter('series_hidden'));
    $countryNameInput = trim((string) postSpaceFilter('country_name'));
    $countryHidden = trim((string) postSpaceFilter('country_hidden'));
    $updateAirbill = strtolower(trim((string) postSpaceFilter('update_airbill')));
    if ($updateAirbill === '') {
        $updateAirbill = 'yes';
    }

    $resolvedCustomerId = $customerHidden !== '' ? $customerHidden : resolveImportOptionId($customerNameInput, $customerNames);
    $resolvedPackageId = $packageHidden !== '' ? $packageHidden : resolveImportOptionId($packageNameInput, $packageNameOptions);
    $selectedPackage = $resolvedPackageId !== '' && isset($packageMeta[(int) $resolvedPackageId]) ? $packageMeta[(int) $resolvedPackageId] : array();

    $resolvedBrandId = $brandHidden !== '' ? $brandHidden : resolveImportOptionId($brandNameInput, $brandOptions);
    $resolvedSeriesId = $seriesHidden !== '' ? $seriesHidden : resolveImportOptionId($seriesNameInput, $seriesOptions);

    if (!empty($selectedPackage)) {
        if ($resolvedBrandId === '' && !empty($selectedPackage['brand_id'])) {
            $resolvedBrandId = (string) $selectedPackage['brand_id'];
            $brandNameInput = isset($selectedPackage['brand_name']) ? (string) $selectedPackage['brand_name'] : $brandNameInput;
        }
        if ($resolvedSeriesId === '' && !empty($selectedPackage['series_id'])) {
            $resolvedSeriesId = (string) $selectedPackage['series_id'];
            $seriesNameInput = isset($selectedPackage['series_name']) ? (string) $selectedPackage['series_name'] : $seriesNameInput;
        }
    }

    if ($brandNameInput === '' && $resolvedBrandId !== '' && isset($brandOptions[(int) $resolvedBrandId])) {
        $brandNameInput = (string) $brandOptions[(int) $resolvedBrandId];
    }
    if ($seriesNameInput === '' && $resolvedSeriesId !== '' && isset($seriesOptions[(int) $resolvedSeriesId])) {
        $seriesNameInput = (string) $seriesOptions[(int) $resolvedSeriesId];
    }

    $resolvedCountryId = $countryHidden !== '' ? $countryHidden : resolveImportOptionId($countryNameInput, $countryOptions, array('Malaysia', 'MY'));
    if ($resolvedCountryId === '') {
        $resolvedCountryId = (string) $defaultCountryId;
    }
    if ($countryNameInput === '' && $resolvedCountryId !== '' && isset($countryOptions[(int) $resolvedCountryId])) {
        $countryNameInput = (string) $countryOptions[(int) $resolvedCountryId];
    }

    $stockOutWarehouseId = shopeeOmsNormalizeWarehouseId(postSpaceFilter('stock_out_warehouse_id'));
    if ($stockOutWarehouseId <= 0) {
        $stockOutWarehouseId = (int) $defaultWarehouseId;
    }
    $selectedOrderStatusCode = shopeeOmsNormalizeStatusCode(postSpaceFilter('order_status_val'));
    if ($selectedOrderStatusCode === '' || !isset($initialStatusOptions[$selectedOrderStatusCode])) {
        $selectedOrderStatusCode = $defaultStatusCode;
    }
    $selectedOrderStatusLabel = isset($initialStatusOptions[$selectedOrderStatusCode]) ? (string) $initialStatusOptions[$selectedOrderStatusCode] : $defaultStatusLabel;

    $previewData = array(
        'order_number' => trim((string) postSpaceFilter('order_number')),
        'customer_id' => (string) $resolvedCustomerId,
        'customer_name' => $customerNameInput,
        'source_customer_name' => trim((string) postSpaceFilter('source_customer_name')),
        'shipping_address' => trim((string) postSpaceFilter('shipping_address')),
        'sku' => trim((string) postSpaceFilter('sku')),
        'detected_sku' => trim((string) postSpaceFilter('detected_sku')),
        'sku_matched' => postSpaceFilter('sku_matched') === 'yes' ? 'yes' : 'no',
        'package_id' => (string) $resolvedPackageId,
        'package_name' => $packageNameInput,
        'brand_id' => (string) $resolvedBrandId,
        'brand_name' => $brandNameInput,
        'series_id' => (string) $resolvedSeriesId,
        'series_name' => $seriesNameInput,
        'price' => trim((string) postSpaceFilter('price')),
        'voucher' => trim((string) postSpaceFilter('voucher')),
        'paid_price' => trim((string) postSpaceFilter('paid_price')),
        'country_id' => (string) $resolvedCountryId,
        'country_name' => $countryNameInput,
        'sales_pic' => (string) USER_NAME,
        'payment_method' => trim((string) postSpaceFilter('payment_method')),
        'order_status' => $selectedOrderStatusLabel,
        'order_status_val' => $selectedOrderStatusCode,
        'stock_out_warehouse_id' => (int) $stockOutWarehouseId,
        'update_airbill' => $updateAirbill === 'yes' ? 'yes' : 'no',
        'airbill_no' => trim((string) postSpaceFilter('airbill_no')),
        'airbill_attachment' => isset($_FILES['airbill_attachment']) && isset($_FILES['airbill_attachment']['size']) && (int) $_FILES['airbill_attachment']['size'] > 0
            ? (string) $_FILES['airbill_attachment']['name']
            : trim((string) postSpaceFilter('airbill_attachment_value')),
        'remark' => trim((string) postSpaceFilter('remark')),
    );

    foreach (array('price', 'voucher', 'paid_price') as $moneyField) {
        $normalizedMoney = lazadaImportNormalizeMoney(isset($previewData[$moneyField]) ? $previewData[$moneyField] : '');
        $previewData[$moneyField] = $normalizedMoney !== '' ? $normalizedMoney : '0.00';
    }

    if ($previewData['order_number'] === '') {
        $importErrors[] = 'Order Number is required.';
    } else if (lazadaImportIsDuplicateOrderNumber($previewData['order_number'], $connect)) {
        $orderNumberFieldError = 'Duplicate Order Number found in Lazada Order Request records.';
    }
    if ($previewData['customer_name'] === '') {
        $importErrors[] = 'Customer Name is required.';
    }
    if ($previewData['shipping_address'] === '') {
        $importErrors[] = 'Shipping Address is required.';
    }
    if ($previewData['package_id'] === '') {
        $importErrors[] = 'Package is required.';
    }
    if ($previewData['brand_id'] === '') {
        $importErrors[] = 'Brand is required.';
    }
    if ($previewData['series_id'] === '') {
        $importErrors[] = 'Series is required.';
    }
    if ($previewData['country_id'] === '') {
        $importErrors[] = 'Country is required.';
    }
    if ((int) $previewData['stock_out_warehouse_id'] <= 0 || !isset($warehouseOptionMap[(int) $previewData['stock_out_warehouse_id']])) {
        $stockOutWarehouseError = 'Please select a valid active Stock Out Warehouse.';
    }

    if ($previewData['update_airbill'] === 'no') {
        $previewData['airbill_no'] = '';
        $previewData['airbill_attachment'] = '';
    } else {
        $statusValidation = shopeeOmsValidateInitialStatusAndAirbill($previewData['order_status_val'], $previewData['airbill_no']);
        if (!$statusValidation['valid']) {
            $importErrors[] = isset($statusValidation['message']) ? (string) $statusValidation['message'] : 'Invalid order status or airbill.';
        }
        if ($previewData['airbill_no'] === '') {
            $importErrors[] = 'Airbill No is required when Update Airbill is enabled.';
        }
        if ($previewData['airbill_attachment'] === '') {
            $importErrors[] = 'Airbill Attachment is required when Update Airbill is enabled.';
        }
    }

    $customerRow = array();
    if ($previewData['customer_id'] !== '' && ctype_digit((string) $previewData['customer_id'])) {
        $customerRow = lazadaImportLoadCustomerRowById($connect, (int) $previewData['customer_id']);
    }

    if (empty($importErrors) && trim((string) $previewData['customer_name']) !== '') {
        if (trim((string) $previewData['customer_id']) === '') {
            $existingCustomerRow = lazadaImportLoadCustomerRowByName($connect, $previewData['customer_name']);
            if (!empty($existingCustomerRow) && isset($existingCustomerRow['id'])) {
                $previewData['customer_id'] = (string) ((int) $existingCustomerRow['id']);
                $customerRow = $existingCustomerRow;
            } else if ($orderNumberFieldError === '' && $stockOutWarehouseError === '') {
                $generatedCustomerCode = lazadaImportGenerateCustomerCode($connect, $previewData['order_number']);
                $newCustomerSql = "INSERT INTO `" . LAZADA_CUST_RCD . "`
                    (lcr_id, name, email, phone, sales_pic, country, brand, series, ship_rec_name, ship_rec_add, ship_rec_contact, remark, create_by, create_date, create_time)
                    VALUES
                    (
                        '" . mysqli_real_escape_string($connect, $generatedCustomerCode) . "',
                        '" . mysqli_real_escape_string($connect, $previewData['customer_name']) . "',
                        '',
                        '',
                        '" . mysqli_real_escape_string($connect, $previewData['sales_pic']) . "',
                        " . ((int) $previewData['country_id'] > 0 ? (int) $previewData['country_id'] : 'NULL') . ",
                        " . ((int) $previewData['brand_id'] > 0 ? (int) $previewData['brand_id'] : 'NULL') . ",
                        " . ((int) $previewData['series_id'] > 0 ? (int) $previewData['series_id'] : 'NULL') . ",
                        '" . mysqli_real_escape_string($connect, $previewData['customer_name']) . "',
                        '" . mysqli_real_escape_string($connect, $previewData['shipping_address']) . "',
                        '',
                        '" . mysqli_real_escape_string($connect, $previewData['remark']) . "',
                        '" . USER_ID . "',
                        CURDATE(),
                        CURTIME()
                    )";

                if (!mysqli_query($connect, $newCustomerSql)) {
                    $importErrors[] = 'Failed to auto create Lazada Customer record: ' . mysqli_error($connect);
                } else {
                    $newCustomerId = (int) mysqli_insert_id($connect);
                    $previewData['customer_id'] = (string) $newCustomerId;
                    $customerRow = lazadaImportLoadCustomerRowById($connect, $newCustomerId);

                    audit_log(array(
                        'log_act' => 'Import',
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => $newCustomerSql,
                        'query_table' => LAZADA_CUST_RCD,
                        'newval' => 'name=' . $previewData['customer_name'],
                        'act_msg' => USER_NAME . " created a Lazada customer record [ <b>ID = " . $newCustomerId . "</b> ] during Lazada Order Import.",
                        'page' => $pageTitle,
                        'connect' => $connect,
                    ));
                }
            }
        }
    }

    if ($previewData['update_airbill'] === 'yes' && isset($_FILES['airbill_attachment']) && isset($_FILES['airbill_attachment']['size']) && (int) $_FILES['airbill_attachment']['size'] > 0) {
        $uploadResult = shopeeOmsStoreAirbillAttachmentUpload(
            $_FILES['airbill_attachment'],
            $connect,
            isset($previewData['brand_id']) ? $previewData['brand_id'] : '',
            isset($previewData['package_id']) ? $previewData['package_id'] : '',
            'lazada_order_request',
            $allowedAttachmentExt
        );
        if (!empty($uploadResult['success'])) {
            $previewData['airbill_attachment'] = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
        } else {
            $importErrors[] = isset($uploadResult['message']) ? (string) $uploadResult['message'] : 'Failed to upload the airbill attachment.';
        }
    }

    if (empty($importErrors) && $orderNumberFieldError === '' && $stockOutWarehouseError === '') {
        $currencyId = $defaultCurrencyId !== '' ? (int) $defaultCurrencyId : 0;
        $lazadaCountryId = (int) $previewData['country_id'];
        $customerId = (int) $previewData['customer_id'];
        $packageId = (int) $previewData['package_id'];
        $brandId = (int) $previewData['brand_id'];
        $seriesId = (int) $previewData['series_id'];
        $stockWarehouseId = (int) $previewData['stock_out_warehouse_id'];

        $custEmail = isset($customerRow['email']) ? (string) $customerRow['email'] : '';
        $custPhone = isset($customerRow['phone']) ? (string) $customerRow['phone'] : '';
        $shippingContact = isset($customerRow['ship_rec_contact']) ? (string) $customerRow['ship_rec_contact'] : '';

        $insertSql = "INSERT INTO `" . LAZADA_ORDER_REQ . "`
            (
                lazada_acc,
                curr_unit,
                lzd_country,
                cust_id,
                cust_name,
                cust_email,
                cust_phone,
                country,
                oder_number,
                sales_pic,
                ship_rec_name,
                ship_rec_address,
                ship_rec_contact,
                brand,
                series,
                pkg,
                item_price_credit,
                commision,
                other_discount,
                pay_fee,
                final_income,
                pay_meth,
                remark,
                order_status,
                stock_out_warehouse_id,
                airbill_no,
                airbill_attachment,
                create_by,
                create_date,
                create_time
            )
            VALUES
            (
                NULL,
                " . ($currencyId > 0 ? $currencyId : 'NULL') . ",
                " . ($lazadaCountryId > 0 ? $lazadaCountryId : 'NULL') . ",
                " . ($customerId > 0 ? $customerId : 'NULL') . ",
                '" . mysqli_real_escape_string($connect, $previewData['customer_name']) . "',
                '" . mysqli_real_escape_string($connect, $custEmail) . "',
                '" . mysqli_real_escape_string($connect, $custPhone) . "',
                " . ($lazadaCountryId > 0 ? $lazadaCountryId : 'NULL') . ",
                '" . mysqli_real_escape_string($connect, $previewData['order_number']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['sales_pic']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['customer_name']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['shipping_address']) . "',
                '" . mysqli_real_escape_string($connect, $shippingContact) . "',
                " . ($brandId > 0 ? $brandId : 'NULL') . ",
                " . ($seriesId > 0 ? $seriesId : 'NULL') . ",
                " . ($packageId > 0 ? $packageId : 'NULL') . ",
                '" . mysqli_real_escape_string($connect, $previewData['price']) . "',
                '0.00',
                '" . mysqli_real_escape_string($connect, $previewData['voucher']) . "',
                '0.00',
                '" . mysqli_real_escape_string($connect, $previewData['paid_price']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['payment_method']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['remark']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['order_status_val']) . "',
                " . ($stockWarehouseId > 0 ? $stockWarehouseId : 'NULL') . ",
                '" . mysqli_real_escape_string($connect, $previewData['airbill_no']) . "',
                '" . mysqli_real_escape_string($connect, $previewData['airbill_attachment']) . "',
                '" . USER_ID . "',
                CURDATE(),
                CURTIME()
            )";

        if (!mysqli_query($connect, $insertSql)) {
            $importErrors[] = 'Database Error: ' . mysqli_error($connect);
        } else {
            $newOrderId = (int) mysqli_insert_id($connect);
            if (function_exists('shopeeOmsRememberWarehouseDeliveryInfo')) {
                shopeeOmsRememberWarehouseDeliveryInfo('lazada', $newOrderId, array(
                    'customer_name' => $previewData['customer_name'],
                    'customer_address' => $previewData['shipping_address'],
                ));
            }

            audit_log(array(
                'log_act' => 'Import',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $insertSql,
                'query_table' => LAZADA_ORDER_REQ,
                'newval' => 'OrderNumber=' . $previewData['order_number'],
                'act_msg' => USER_NAME . " imported the data [ <b>ID = " . $newOrderId . "</b> ] into <b><i>" . LAZADA_ORDER_REQ . "</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ));

            echo '<script>alert("Lazada Order Request imported successfully.");window.location.replace("' . $lazadaOrderRedirectPage . '");</script>';
            exit;
        }
    }
}

function lazadaImportLoadCustomerOptions($connect)
{
    $rows = array();
    if (!($connect instanceof mysqli)) {
        return $rows;
    }

    $sql = "SELECT id, lcr_id, name, email, phone, ship_rec_name, ship_rec_add, ship_rec_contact
        FROM `" . LAZADA_CUST_RCD . "`
        WHERE status = 'A'
        ORDER BY name ASC, id ASC";
    $result = mysqli_query($connect, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($rowId <= 0) {
                continue;
            }
            $rows[$rowId] = array(
                'id' => $rowId,
                'lcr_id' => isset($row['lcr_id']) ? (string) $row['lcr_id'] : '',
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'email' => isset($row['email']) ? (string) $row['email'] : '',
                'phone' => isset($row['phone']) ? (string) $row['phone'] : '',
                'ship_rec_name' => isset($row['ship_rec_name']) ? (string) $row['ship_rec_name'] : '',
                'ship_rec_add' => isset($row['ship_rec_add']) ? (string) $row['ship_rec_add'] : '',
                'ship_rec_contact' => isset($row['ship_rec_contact']) ? (string) $row['ship_rec_contact'] : '',
            );
        }
    }

    return $rows;
}

function lazadaImportLoadCustomerRowById($connect, $customerId)
{
    $customerId = (int) $customerId;
    if (!($connect instanceof mysqli) || $customerId <= 0) {
        return array();
    }

    $sql = "SELECT * FROM `" . LAZADA_CUST_RCD . "` WHERE id = " . $customerId . " LIMIT 1";
    $result = mysqli_query($connect, $sql);
    if (!$result || mysqli_num_rows($result) === 0) {
        return array();
    }

    $row = mysqli_fetch_assoc($result);
    return is_array($row) ? $row : array();
}

function lazadaImportLoadCustomerRowByName($connect, $customerName)
{
    $customerName = trim((string) $customerName);
    if (!($connect instanceof mysqli) || $customerName === '') {
        return array();
    }

    $safeCustomerLookup = mysqli_real_escape_string($connect, strtolower($customerName));
    $sql = "SELECT * FROM `" . LAZADA_CUST_RCD . "`
        WHERE LOWER(TRIM(name)) = '" . $safeCustomerLookup . "'
        AND status = 'A'
        LIMIT 1";
    $result = mysqli_query($connect, $sql);
    if (!$result || mysqli_num_rows($result) === 0) {
        return array();
    }

    $row = mysqli_fetch_assoc($result);
    return is_array($row) ? $row : array();
}

function lazadaImportGenerateCustomerCode($connect, $orderNumber)
{
    $orderNumber = preg_replace('/[^A-Za-z0-9]+/', '', (string) $orderNumber);
    $baseCode = $orderNumber !== '' ? ('LZD-' . $orderNumber) : ('LZD-' . date('YmdHis'));
    $candidate = $baseCode;
    $suffix = 2;

    while (lazadaImportCustomerCodeExists($connect, $candidate)) {
        $candidate = $baseCode . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
}

function lazadaImportLoadPackageMeta($connect)
{
    $meta = array();
    if (!($connect instanceof mysqli)) {
        return $meta;
    }

    $seriesByBrand = array();
    $seriesResult = mysqli_query($connect, "SELECT id, name, brand FROM `" . BRD_SERIES . "` WHERE status = 'A' ORDER BY name ASC");
    if ($seriesResult) {
        while ($seriesRow = mysqli_fetch_assoc($seriesResult)) {
            $brandId = isset($seriesRow['brand']) ? (int) $seriesRow['brand'] : 0;
            $seriesId = isset($seriesRow['id']) ? (int) $seriesRow['id'] : 0;
            if ($brandId <= 0 || $seriesId <= 0) {
                continue;
            }
            if (!isset($seriesByBrand[$brandId])) {
                $seriesByBrand[$brandId] = array();
            }
            $seriesByBrand[$brandId][] = array(
                'id' => $seriesId,
                'name' => isset($seriesRow['name']) ? (string) $seriesRow['name'] : '',
            );
        }
    }

    $sql = "SELECT p.id, p.name, p.item_code, p.item_description, p.brand, b.name AS brand_name
        FROM `" . PKG . "` p
        LEFT JOIN `" . BRAND . "` b ON b.id = p.brand
        WHERE p.status = 'A'
        ORDER BY p.name ASC";
    $result = mysqli_query($connect, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $packageId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($packageId <= 0) {
                continue;
            }

            $brandId = isset($row['brand']) ? (int) $row['brand'] : 0;
            $seriesMeta = lazadaImportResolveSeriesMetaForPackage($row, isset($seriesByBrand[$brandId]) ? $seriesByBrand[$brandId] : array());

            $meta[$packageId] = array(
                'id' => $packageId,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'item_code' => isset($row['item_code']) ? (string) $row['item_code'] : '',
                'item_description' => isset($row['item_description']) ? (string) $row['item_description'] : '',
                'brand_id' => $brandId > 0 ? $brandId : '',
                'brand_name' => isset($row['brand_name']) ? (string) $row['brand_name'] : '',
                'series_id' => isset($seriesMeta['id']) ? (string) $seriesMeta['id'] : '',
                'series_name' => isset($seriesMeta['name']) ? (string) $seriesMeta['name'] : '',
            );
        }
    }

    return $meta;
}

function lazadaImportResolveSeriesMetaForPackage($packageRow, $seriesRows)
{
    $seriesRows = is_array($seriesRows) ? $seriesRows : array();
    if (empty($seriesRows)) {
        return array('id' => '', 'name' => '');
    }
    if (count($seriesRows) === 1) {
        return $seriesRows[0];
    }

    $haystack = normalizeImportLookup(
        (isset($packageRow['name']) ? $packageRow['name'] : '') . ' ' .
        (isset($packageRow['item_code']) ? $packageRow['item_code'] : '') . ' ' .
        (isset($packageRow['item_description']) ? $packageRow['item_description'] : '')
    );

    foreach ($seriesRows as $seriesRow) {
        $seriesKey = normalizeImportLookup(isset($seriesRow['name']) ? $seriesRow['name'] : '');
        if ($seriesKey !== '' && $haystack !== '' && strpos($haystack, $seriesKey) !== false) {
            return $seriesRow;
        }
    }

    return array('id' => '', 'name' => '');
}

function lazadaImportNormalizeSourceText($text)
{
    $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace("/\n{2,}/u", "\n", $text);
    return trim((string) $text);
}

function lazadaImportNormalizeMoney($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = str_ireplace(array('RM', 'MYR', '+RM', '/RM', '+/RM', '$'), '', $value);
    $value = preg_replace('/[^0-9\.\-]/', '', (string) $value);
    if ($value === '' || !is_numeric($value)) {
        return '';
    }

    return number_format(abs((float) $value), 2, '.', '');
}

function lazadaImportExtractFieldByLabels($text, $labels, $stopLabels = array())
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '' || empty($labels)) {
        return '';
    }

    $stopPattern = '';
    $stopParts = array();
    foreach ((array) $stopLabels as $stopLabel) {
        $stopLabel = trim((string) $stopLabel);
        if ($stopLabel !== '') {
            $stopParts[] = preg_quote($stopLabel, '/');
        }
    }
    if (!empty($stopParts)) {
        $stopPattern = '(?=(?:' . implode('|', $stopParts) . ')\b|$)';
    } else {
        $stopPattern = '$';
    }

    foreach ((array) $labels as $label) {
        $label = trim((string) $label);
        if ($label === '') {
            continue;
        }
        $pattern = '/' . preg_quote($label, '/') . '\s*:?\s*(.*?)\s*' . $stopPattern . '/is';
        if (preg_match($pattern, $text, $matches)) {
            $value = trim((string) $matches[1]);
            if ($value !== '') {
                return normalizeImportText($value);
            }
        }
    }

    return '';
}

function lazadaImportExtractOrderNumber($text)
{
    $value = lazadaImportExtractFieldByLabels($text, array('Order Number'), array('Order Date', 'Invoice To', 'Invoice Date'));
    if ($value !== '' && preg_match('/([0-9]{8,30})/', $value, $matches)) {
        return trim((string) $matches[1]);
    }

    if (preg_match('/Order\s*Number\s*:?\s*([0-9]{8,30})/i', (string) $text, $matches)) {
        return trim((string) $matches[1]);
    }

    return '';
}

function lazadaImportExtractCustomerName($text)
{
    $value = lazadaImportExtractFieldByLabels($text, array('Invoice To'), array('Invoice Date', 'SHIPPING ADDRESS', 'BILLING ADDRESS'));
    if ($value !== '') {
        return trim((string) $value);
    }

    if (preg_match('/Invoice\s*To\s*:?\s*([A-Za-z][^\n\r]{1,120}?)(?=\s+Invoice\s*Date|\s+SHIPPING\s+ADDRESS|\s+BILLING\s+ADDRESS|$)/i', (string) $text, $matches)) {
        return normalizeImportText($matches[1]);
    }

    return '';
}

function lazadaImportExtractShippingAddress($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $upperText = strtoupper($text);
    $shippingLabelPos = strpos($upperText, 'SHIPPING ADDRESS');
    if ($shippingLabelPos !== false) {
        $segmentStart = $shippingLabelPos + strlen('SHIPPING ADDRESS');
        $segment = substr($text, $segmentStart);
        if ($segment !== false && $segment !== '') {
            $stopOffsets = array();
            foreach (array('Contact Phone', 'Payment Method', 'Your ordered items for', 'BILLING ADDRESS') as $stopLabel) {
                $stopPos = stripos($segment, $stopLabel);
                if ($stopPos !== false) {
                    $stopOffsets[] = (int) $stopPos;
                }
            }
            if (!empty($stopOffsets)) {
                $segment = substr($segment, 0, min($stopOffsets));
            }

            $value = lazadaImportCleanupAddress($segment);
            if ($value !== '') {
                return $value;
            }
        }
    }

    $value = lazadaImportExtractFieldByLabels(
        $text,
        array('SHIPPING ADDRESS'),
        array('Contact Phone', 'Payment Method', 'Your ordered items for', 'BILLING ADDRESS')
    );
    if ($value !== '') {
        return lazadaImportCleanupAddress($value);
    }

    if (preg_match('/SHIPPING\s+ADDRESS\s*(.*?)\s*Contact\s*Phone/is', (string) $text, $matches)) {
        return lazadaImportCleanupAddress($matches[1]);
    }

    return '';
}

function lazadaImportCleanupAddress($address)
{
    $address = normalizeImportText((string) $address);
    if ($address === '') {
        return '';
    }

    $address = preg_replace('/\s*,\s*/', ', ', $address);
    $address = preg_replace('/,\s*,+/u', ', ', (string) $address);
    $address = preg_replace('/,{2,}/', ',', (string) $address);
    $address = preg_replace('/\s{2,}/', ' ', (string) $address);
    $address = trim((string) $address, " ,");

    if (preg_match('/^(.{12,}?)\s+\1$/u', $address, $duplicateMatches)) {
        return trim((string) $duplicateMatches[1], " ,");
    }

    if (preg_match('/^(.+?\bMalaysia\b)\s+(.+)$/iu', $address, $countryMatches)) {
        $firstAddress = trim((string) $countryMatches[1], " ,");
        $remainingAddress = trim((string) $countryMatches[2], " ,");
        if ($firstAddress !== '' && $remainingAddress !== '' && normalizeImportLookup($firstAddress) === normalizeImportLookup($remainingAddress)) {
            return $firstAddress;
        }
    }

    $parts = preg_split('/,\s*/', $address, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($parts) && count($parts) >= 2) {
        $half = (int) (count($parts) / 2);
        if ($half > 0 && count($parts) % 2 === 0) {
            $firstHalf = array_map('trim', array_slice($parts, 0, $half));
            $secondHalf = array_map('trim', array_slice($parts, $half));
            if ($firstHalf === $secondHalf) {
                $parts = $firstHalf;
            }
        }
    }

    return trim(implode(', ', is_array($parts) ? $parts : array($address)), " ,");
}

function lazadaImportExtractSellerSku($text)
{
    $value = lazadaImportExtractFieldByLabels($text, array('Seller SKU'), array('Shop SKU', 'Price', 'Paid Price', 'Subtotal'));
    $value = preg_replace('/\s+/', '', (string) $value);
    $value = trim((string) $value);
    if ($value !== '' && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value)) {
        return $value;
    }

    if (preg_match('/Seller\s*SKU\s*([A-Za-z0-9\-\/_]{4,60})\s+Shop\s*SKU/i', (string) $text, $matches)) {
        return trim((string) $matches[1]);
    }

    if (preg_match('/Price\s+Paid\s*Price\s+(.*?)(?:Upon receipt|$)/is', (string) $text, $matches)) {
        $section = trim((string) $matches[1]);
        if ($section !== '') {
            $sectionTail = substr($section, -260);
            if (preg_match('/([A-Z0-9]{2,}(?:[\s\-\/_]+[A-Z0-9]{1,}){2,})\s+[0-9]{6,}[A-Za-z0-9\-_ ]{4,40}\s+[0-9][0-9,]*(?:\.\d{2})?\s+[0-9][0-9,]*(?:\.\d{2})?\s*$/', $sectionTail, $tailMatches)) {
                $value = preg_replace('/\s+/', '', (string) $tailMatches[1]);
                $value = trim((string) $value, '-_ ');
                if ($value !== '' && preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value)) {
                    return $value;
                }
            }
        }
    }

    return '';
}

function lazadaImportExtractOrderTableAmounts($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return array();
    }

    $sections = array();
    if (preg_match('/Price\s+Paid\s*Price\s+(.*?)(?:Upon receipt|$)/is', $text, $matches)) {
        $sections[] = isset($matches[1]) ? (string) $matches[1] : '';
    }
    if (preg_match('/Seller\s+SKU\s+Shop\s+SKU\s+Price\s+Paid\s*Price\s+(.*?)(?:Upon receipt|$)/is', $text, $matches)) {
        $sections[] = isset($matches[1]) ? (string) $matches[1] : '';
    }

    foreach ($sections as $section) {
        $section = trim((string) $section);
        if ($section === '') {
            continue;
        }

        $amounts = array();
        if (preg_match_all('/[0-9][0-9,]*(?:\.\d{2})?/', $section, $amountMatches)) {
            foreach ((array) $amountMatches[0] as $candidate) {
                $normalizedCandidate = trim((string) str_replace(',', '', $candidate));
                if ($normalizedCandidate === '') {
                    continue;
                }

                if (strpos($normalizedCandidate, '.') === false) {
                    $digitsOnly = preg_replace('/\D/', '', $normalizedCandidate);
                    if ($digitsOnly === '' || strlen($digitsOnly) > 6 || (int) $digitsOnly <= 9) {
                        continue;
                    }
                }

                $amount = lazadaImportNormalizeMoney($normalizedCandidate);
                if ($amount !== '' && (float) $amount > 0) {
                    $amounts[] = $amount;
                }
            }
        }

        if (count($amounts) >= 2) {
            return array_slice($amounts, -2);
        }
        if (!empty($amounts)) {
            return $amounts;
        }
    }

    return array();
}

function lazadaImportExtractItemPrice($text, $sku = '')
{
    $text = lazadaImportNormalizeSourceText($text);
    $sku = trim((string) $sku);
    if ($sku !== '') {
        $skuPattern = preg_quote($sku, '/');
        if (preg_match('/' . $skuPattern . '(.*?)(?:Subtotal|Less\s*:|Total\s*:|Net\s*paid\s*:)/is', $text, $matches)) {
            if (preg_match_all('/[0-9][0-9,]*(?:\.\d{2})?/', (string) $matches[1], $amountMatches)) {
                foreach ((array) $amountMatches[0] as $candidate) {
                    $normalizedCandidate = trim((string) str_replace(',', '', $candidate));
                    if ($normalizedCandidate === '') {
                        continue;
                    }

                    if (strpos($normalizedCandidate, '.') === false && strlen(preg_replace('/\D/', '', $normalizedCandidate)) > 6) {
                        continue;
                    }

                    $amount = lazadaImportNormalizeMoney($normalizedCandidate);
                    if ($amount !== '' && (float) $amount > 0) {
                        return $amount;
                    }
                }
            }
        }
    }

    $orderTableAmounts = lazadaImportExtractOrderTableAmounts($text);
    if (!empty($orderTableAmounts)) {
        return isset($orderTableAmounts[0]) ? (string) $orderTableAmounts[0] : '';
    }

    $orderedItemSection = lazadaImportExtractFieldByLabels($text, array('Your ordered items for'), array('Subtotal', 'Less', 'Total', 'Net paid'));
    if ($orderedItemSection !== '') {
        if (preg_match_all('/(?:^|\s)([0-9][0-9,]*(?:\.\d{2})?)(?=\s|$)/', $orderedItemSection, $matches)) {
            foreach ((array) $matches[1] as $candidate) {
                $normalizedCandidate = trim((string) str_replace(',', '', $candidate));
                if ($normalizedCandidate === '') {
                    continue;
                }

                if (strpos($normalizedCandidate, '.') === false && strlen(preg_replace('/\D/', '', $normalizedCandidate)) > 6) {
                    continue;
                }

                $amount = lazadaImportNormalizeMoney($normalizedCandidate);
                if ($amount !== '' && (float) $amount > 0) {
                    return $amount;
                }
            }
        }
    }

    return '';
}

function lazadaImportExtractVoucher($text)
{
    $value = lazadaImportExtractFieldByLabels($text, array('Voucher applied', 'Other discount'), array('Total', 'Shipping', 'Net paid'));
    if ($value !== '') {
        if (preg_match('/-?\s*(?:RM|MYR)?\s*[0-9][0-9,]*(?:\.\d{2})?/', $value, $matches)) {
            return lazadaImportNormalizeMoney($matches[0]);
        }
    }

    if (preg_match('/Voucher\s*applied\s*:?\s*(?:RM|MYR)?\s*(-?[0-9][0-9,]*(?:\.\d{2})?)/i', (string) $text, $matches)) {
        return lazadaImportNormalizeMoney($matches[1]);
    }

    return '';
}

function lazadaImportExtractPaidPrice($text, $sku = '')
{
    $text = lazadaImportNormalizeSourceText($text);
    $sku = trim((string) $sku);
    if ($sku !== '') {
        $skuPattern = preg_quote($sku, '/');
        if (preg_match('/' . $skuPattern . '\s+[A-Za-z0-9\-_]+\s+([0-9][0-9,]*(?:\.\d{2})?)\s+([0-9][0-9,]*(?:\.\d{2})?)/i', $text, $matches)) {
            return lazadaImportNormalizeMoney($matches[2]);
        }
    }

    $orderTableAmounts = lazadaImportExtractOrderTableAmounts($text);
    if (count($orderTableAmounts) >= 2) {
        return isset($orderTableAmounts[1]) ? (string) $orderTableAmounts[1] : '';
    }

    $value = lazadaImportExtractFieldByLabels($text, array('Net paid', 'Total', 'Paid Price'), array('Upon receipt', 'Shipping'));
    if ($value !== '') {
        if (preg_match('/(?:RM|MYR)?\s*[0-9][0-9,]*(?:\.\d{2})?/', $value, $matches)) {
            return lazadaImportNormalizeMoney($matches[0]);
        }
    }

    if (preg_match('/Net\s*paid\s*:?\s*(?:RM|MYR)?\s*([0-9][0-9,]*(?:\.\d{2})?)/i', (string) $text, $matches)) {
        return lazadaImportNormalizeMoney($matches[1]);
    }

    return '';
}

function lazadaImportExtractPaymentMethod($text)
{
    return lazadaImportExtractFieldByLabels($text, array('Payment Method'), array('Your ordered items for', 'Subtotal', 'BILLING ADDRESS'));
}

function lazadaImportResolvePackageIdFromSku($sku, $packageMeta)
{
    $sku = trim((string) $sku);
    if ($sku === '' || !is_array($packageMeta) || empty($packageMeta)) {
        return '';
    }

    $skuLookup = normalizeImportLookup($sku);
    foreach ($packageMeta as $packageId => $packageRow) {
        $itemCode = isset($packageRow['item_code']) ? (string) $packageRow['item_code'] : '';
        if ($itemCode !== '' && strcasecmp($itemCode, $sku) === 0) {
            return (string) $packageId;
        }
        if ($skuLookup !== '' && normalizeImportLookup($itemCode) === $skuLookup) {
            return (string) $packageId;
        }
    }

    foreach ($packageMeta as $packageId => $packageRow) {
        $packageNameKey = normalizeImportLookup(isset($packageRow['name']) ? $packageRow['name'] : '');
        $itemCodeKey = normalizeImportLookup(isset($packageRow['item_code']) ? $packageRow['item_code'] : '');
        if ($skuLookup !== '' && (
            ($itemCodeKey !== '' && (strpos($itemCodeKey, $skuLookup) !== false || strpos($skuLookup, $itemCodeKey) !== false))
            || ($packageNameKey !== '' && strpos($packageNameKey, $skuLookup) !== false)
        )) {
            return (string) $packageId;
        }
    }

    return '';
}

function lazadaImportIsDuplicateOrderNumber($orderNumber, $connect)
{
    $orderNumber = trim((string) $orderNumber);
    if (!($connect instanceof mysqli) || $orderNumber === '') {
        return false;
    }

    $safeOrderNumber = mysqli_real_escape_string($connect, $orderNumber);
    $sql = "SELECT id FROM `" . LAZADA_ORDER_REQ . "` WHERE oder_number = '" . $safeOrderNumber . "' LIMIT 1";
    $result = mysqli_query($connect, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function lazadaImportCustomerCodeExists($connect, $customerCode)
{
    $customerCode = trim((string) $customerCode);
    if (!($connect instanceof mysqli) || $customerCode === '') {
        return false;
    }

    $safeCustomerCode = mysqli_real_escape_string($connect, $customerCode);
    $sql = "SELECT id FROM `" . LAZADA_CUST_RCD . "` WHERE lcr_id = '" . $safeCustomerCode . "' LIMIT 1";
    $result = mysqli_query($connect, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function resolveImportOptionId($rawValue, $options, $fallbacks = array())
{
    $candidates = array_merge(array((string) $rawValue), is_array($fallbacks) ? $fallbacks : array());

    foreach ($candidates as $candidate) {
        $normalizedCandidate = normalizeImportLookup($candidate);
        if ($normalizedCandidate === '') {
            continue;
        }

        foreach ($options as $id => $label) {
            $normalizedLabel = normalizeImportLookup($label);
            if ($normalizedLabel === $normalizedCandidate) {
                return (string) $id;
            }
        }

        foreach ($options as $id => $label) {
            $normalizedLabel = normalizeImportLookup($label);
            if ($normalizedLabel !== '' && (strpos($normalizedLabel, $normalizedCandidate) !== false || strpos($normalizedCandidate, $normalizedLabel) !== false)) {
                return (string) $id;
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
    <script src="<?= $SITEURL ?>/finance/header/js/pdf.min.js"></script>
    <script src="<?= $SITEURL ?>/js/pdf_airbill_parser.js"></script>
    <script src="<?= $SITEURL ?>/finance/header/js/tesseract.min.js"></script>
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

        .shopee-airbill-extract-status {
            display: block;
            min-height: 18px;
            margin-top: 6px;
            color: #198754;
        }

        .shopee-airbill-extract-status.is-error {
            color: #dc3545;
        }

        .shopee-airbill-preview-media {
            width: 100%;
            max-width: 520px;
        }

        .shopee-airbill-preview-media img,
        .shopee-airbill-preview-media iframe {
            width: 100%;
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            background: #fff;
        }

        .shopee-airbill-preview-media img {
            height: auto;
            display: block;
        }

        .shopee-airbill-preview-media iframe {
            min-height: 520px;
        }

        .lzd-import-note {
            font-size: 12px;
        }

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
                        <?= htmlspecialchars($breadcrumbTitle, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <div class="row mb-4">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                        <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $lazadaOrderRedirectPage ?>">Back To Lazada Order Page</a>
                            <a class="btn btn-lg btn-rounded btn-primary px-4" href="<?= $redirectPage ?>">Back To Shortcuts</a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($importErrors)) { ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($importErrors as $error) { ?>
                            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <?php if (!empty($importWarnings)) { ?>
                    <div class="alert alert-warning" role="alert">
                        <?php foreach ($importWarnings as $warning) { ?>
                            <div><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Step 1: Upload Lazada Order PDF</h5>
                        <form method="post" enctype="multipart/form-data" autocomplete="off" id="lzdUploadForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-8">
                                    <label class="form-label" for="import_file">Lazada Invoice PDF File</label>
                                    <input class="form-control" type="file" name="import_file" id="import_file" accept=".pdf,application/pdf" required>
                                    <input type="hidden" name="actionBtn" value="parseLazadaOrderReq">
                                    <input type="hidden" name="client_pdf_text" id="client_pdf_text" value="">
                                    <small class="text-muted d-block mt-2" id="lzd_pdf_extract_status"></small>
                                </div>
                                <div class="col-12 col-md-4">
                                    <button class="btn btn-lg btn-rounded btn-primary w-100 px-4" type="submit" id="lzdSubmitBtn">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> Load And Analyze
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($previewData) && !empty($previewData['order_number'])) { ?>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Step 2: Preview And Edit Before Insert</h5>
                            <form method="post" enctype="multipart/form-data" autocomplete="off" data-lazada-import-preview="1" novalidate>
                                <div class="row mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="order_number">Order Number<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" id="order_number" name="order_number" value="<?= htmlspecialchars(isset($previewData['order_number']) ? $previewData['order_number'] : '', ENT_QUOTES, 'UTF-8') ?>" required>
                                        <?php if ($orderNumberFieldError !== '') { ?>
                                            <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($orderNumberFieldError, ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php } ?>
                                    </div>
                                    <div class="col-12 col-md-6 autocomplete">
                                        <label class="form-label" for="customer_name">Customer Name<span class="requireRed">*</span></label>
                                        <input class="form-control <?= empty($previewData['customer_id']) ? 'border-warning' : '' ?>" type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars(isset($previewData['customer_name']) ? $previewData['customer_name'] : '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required>
                                        <input type="hidden" id="customer_hidden" name="customer_hidden" value="<?= htmlspecialchars(isset($previewData['customer_id']) ? $previewData['customer_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" id="source_customer_name" name="source_customer_name" value="<?= htmlspecialchars(isset($previewData['source_customer_name']) ? $previewData['source_customer_name'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                        <?php $sourceCustomerName = isset($previewData['source_customer_name']) ? trim((string) $previewData['source_customer_name']) : ''; ?>
                                        <?php if ($sourceCustomerName !== '') { ?>
                                            <?php $detectedCustomerMissingInDb = empty($previewData['customer_id']); ?>
                                            <div class="d-inline-flex align-items-center gap-2 mt-1">
                                                <small class="text-muted mb-0">Detected: <?= htmlspecialchars($sourceCustomerName, ENT_QUOTES, 'UTF-8') ?><?= $detectedCustomerMissingInDb ? ' (Not in database)' : '' ?></small>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary btn-sm py-0 px-2"
                                                    id="use_detected_customer_btn"
                                                    data-detected-customer="<?= htmlspecialchars($sourceCustomerName, ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Use detected customer name"
                                                    aria-label="Use detected customer name">
                                                    <i class="fa-solid fa-arrow-right"></i>
                                                </button>
                                            </div>
                                        <?php } else if (!empty($previewData['customer_id'])) { ?>
                                            <small class="text-success lzd-import-note">Matched existing customer record.</small>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="shipping_address">Shipping Address<span class="requireRed">*</span></label>
                                        <textarea class="form-control" id="shipping_address" name="shipping_address" rows="2" required><?= htmlspecialchars(isset($previewData['shipping_address']) ? $previewData['shipping_address'] : '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="sku_display">SKU / Item Code</label>
                                        <input class="form-control <?= empty($previewData['sku']) ? 'border-warning' : '' ?>" type="text" id="sku_display" value="<?= htmlspecialchars(isset($previewData['sku']) ? $previewData['sku'] : '', ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        <input type="hidden" id="sku" name="sku" value="<?= htmlspecialchars(isset($previewData['sku']) ? $previewData['sku'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" id="detected_sku" name="detected_sku" value="<?= htmlspecialchars(isset($previewData['detected_sku']) ? $previewData['detected_sku'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" id="sku_matched" name="sku_matched" value="<?= htmlspecialchars(isset($previewData['sku_matched']) ? $previewData['sku_matched'] : 'no', ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (!empty($previewData['detected_sku'])) { ?>
                                            <small class="<?= !empty($previewData['package_id']) ? 'text-success' : 'text-danger fw-bold' ?>">
                                                Detected SKU: <?= htmlspecialchars($previewData['detected_sku'], ENT_QUOTES, 'UTF-8') ?><?= !empty($previewData['package_id']) ? ' - package auto-selected.' : '' ?>
                                            </small>
                                        <?php } else { ?>
                                            <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> Detected SKU: Not detected.</small>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-4 autocomplete">
                                        <label class="form-label" for="package_name">Package<span class="requireRed">*</span></label>
                                        <input class="form-control <?= empty($previewData['package_id']) ? 'border-danger' : '' ?>" type="text" id="package_name" name="package_name" value="<?= htmlspecialchars(isset($previewData['package_name']) ? $previewData['package_name'] : '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required>
                                        <input type="hidden" id="package_hidden" name="package_hidden" value="<?= htmlspecialchars(isset($previewData['package_id']) ? $previewData['package_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                        <?php if (empty($previewData['package_id'])) { ?>
                                            <small class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation"></i> Auto-match failed. Please select manually.</small>
                                        <?php } else { ?>
                                            <small class="text-success lzd-import-note">Auto-matched from detected SKU.</small>
                                        <?php } ?>
                                        <a href="<?= $SITEURL ?>/product/package.php?act=I" target="_blank" class="btn btn-sm btn-outline-danger mt-1">Add New Package</a>
                                    </div>
                                    <div class="col-12 col-md-4 autocomplete">
                                        <label class="form-label" for="brand_name">Brand<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" id="brand_name" name="brand_name" value="<?= htmlspecialchars(isset($previewData['brand_name']) ? $previewData['brand_name'] : '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required>
                                        <input type="hidden" id="brand_hidden" name="brand_hidden" value="<?= htmlspecialchars(isset($previewData['brand_id']) ? $previewData['brand_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-12 col-md-4 autocomplete">
                                        <label class="form-label" for="series_name">Series<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" id="series_name" name="series_name" value="<?= htmlspecialchars(isset($previewData['series_name']) ? $previewData['series_name'] : '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required>
                                        <input type="hidden" id="series_hidden" name="series_hidden" value="<?= htmlspecialchars(isset($previewData['series_id']) ? $previewData['series_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="price">Price<span class="requireRed">*</span></label>
                                        <input class="form-control" type="number" step="0.01" id="price" name="price" value="<?= htmlspecialchars(isset($previewData['price']) ? $previewData['price'] : '0.00', ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="voucher">Voucher<span class="requireRed">*</span></label>
                                        <input class="form-control" type="number" step="0.01" id="voucher" name="voucher" value="<?= htmlspecialchars(isset($previewData['voucher']) ? $previewData['voucher'] : '0.00', ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="paid_price">Paid Price<span class="requireRed">*</span></label>
                                        <input class="form-control" type="number" step="0.01" id="paid_price" name="paid_price" value="<?= htmlspecialchars(isset($previewData['paid_price']) ? $previewData['paid_price'] : '0.00', ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-4 autocomplete">
                                        <label class="form-label" for="country_name">Country<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" id="country_name" name="country_name" value="<?= htmlspecialchars(isset($previewData['country_name']) ? $previewData['country_name'] : 'Malaysia', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" required>
                                        <input type="hidden" id="country_hidden" name="country_hidden" value="<?= htmlspecialchars(isset($previewData['country_id']) ? $previewData['country_id'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="order_status_val">Initial Order Status<span class="requireRed">*</span></label>
                                        <select class="form-select" id="order_status_val" name="order_status_val" required>
                                            <?php $currentOrderStatusCode = isset($previewData['order_status_val']) ? (string) $previewData['order_status_val'] : $defaultStatusCode; ?>
                                            <?php foreach ($initialStatusOptions as $statusCode => $statusLabel) { ?>
                                                <option value="<?= htmlspecialchars((string) $statusCode, ENT_QUOTES, 'UTF-8') ?>" <?= $currentOrderStatusCode === (string) $statusCode ? 'selected' : '' ?>><?= htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="stock_out_warehouse_id">Stock Out Warehouse<span class="requireRed">*</span></label>
                                        <?php $currentWarehouseId = isset($previewData['stock_out_warehouse_id']) ? (int) $previewData['stock_out_warehouse_id'] : (int) $defaultWarehouseId; ?>
                                        <select class="form-select" id="stock_out_warehouse_id" name="stock_out_warehouse_id" required>
                                            <?php foreach ($warehouseRows as $warehouseRow) { ?>
                                                <?php $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0; ?>
                                                <option value="<?= $warehouseId ?>" <?= $warehouseId === $currentWarehouseId ? 'selected' : '' ?>><?= htmlspecialchars((string) $warehouseRow['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php if ($stockOutWarehouseError !== '') { ?>
                                            <div id="err_msg">
                                                <span class="mt-n1"><?= htmlspecialchars($stockOutWarehouseError, ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="row mb-3 shopee-airbill-row">
                                    <div class="col-12 col-md-2 shopee-airbill-toggle-col">
                                        <?php $previewUpdateAirbillValue = (isset($previewData['update_airbill']) ? $previewData['update_airbill'] : 'yes') === 'yes' ? 'yes' : 'no'; ?>
                                        <input type="hidden" id="update_airbill" name="update_airbill" value="<?= htmlspecialchars($previewUpdateAirbillValue, ENT_QUOTES, 'UTF-8') ?>">
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
                                        <input class="form-control" type="text" id="airbill_no" name="airbill_no" value="<?= htmlspecialchars(isset($previewData['airbill_no']) ? $previewData['airbill_no'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="airbill_attachment">Airbill Attachment<span class="requireRed">*</span></label>
                                        <input class="form-control" type="file" id="airbill_attachment" name="airbill_attachment" accept=".png,.jpg,.jpeg,.pdf,application/pdf,image/png,image/jpeg">
                                        <small id="airbill_extract_status" class="shopee-airbill-extract-status"></small>
                                        <?php if (!empty($previewData['airbill_attachment'])) { ?>
                                            <small class="text-danger d-block mt-1">Current Attachment: <?= htmlspecialchars($previewData['airbill_attachment'], ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php } ?>
                                        <input type="hidden" id="airbill_attachment_value" name="airbill_attachment_value" value="<?= htmlspecialchars(isset($previewData['airbill_attachment']) ? $previewData['airbill_attachment'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-6 offset-md-6">
                                        <?php
                                        $previewAttachmentSrc = '';
                                        $previewAttachmentExt = '';
                                        if (!empty($previewData['airbill_attachment'])) {
                                            $storedAttachment = trim(str_replace('\\', '/', (string) $previewData['airbill_attachment']), '/');
                                            $previewAttachmentSrc = $airbillAttachmentBaseUrl . $storedAttachment;
                                            $previewAttachmentPath = parse_url($previewAttachmentSrc, PHP_URL_PATH);
                                            $previewAttachmentExt = strtolower(pathinfo((string) $previewAttachmentPath, PATHINFO_EXTENSION));
                                        }
                                        ?>
                                        <div class="d-flex justify-content-center justify-content-md-end px-4">
                                            <?php if ($previewAttachmentSrc !== '') { ?>
                                                <div id="airbill_attachment_preview_wrap" class="shopee-airbill-preview-media">
                                                    <?php if (in_array($previewAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                                                        <img id="airbill_attachment_preview_img" src="<?= htmlspecialchars($previewAttachmentSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Airbill Attachment Preview">
                                                    <?php } else if ($previewAttachmentExt === 'pdf') { ?>
                                                        <iframe id="airbill_attachment_preview_pdf" src="<?= htmlspecialchars($previewAttachmentSrc, ENT_QUOTES, 'UTF-8') ?>" title="Airbill Attachment Preview"></iframe>
                                                    <?php } ?>
                                                </div>
                                            <?php } else { ?>
                                                <div id="airbill_attachment_preview_wrap" class="shopee-airbill-preview-media" style="display:none;"></div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="remark">Remark</label>
                                        <input class="form-control" type="text" id="remark" name="remark" value="<?= htmlspecialchars(isset($previewData['remark']) ? $previewData['remark'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="payment_method" value="<?= htmlspecialchars(isset($previewData['payment_method']) ? $previewData['payment_method'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-center flex-wrap mt-4">
                                    <div class="d-flex justify-content-center gap-2 flex-wrap w-100">
                                        <button class="btn btn-lg btn-rounded btn-primary px-4" type="submit" name="actionBtn" value="insertLazadaOrderReq">
                                            <i class="fa-solid fa-database"></i> INSERT
                                        </button>
                                        <button class="btn btn-lg btn-rounded btn-secondary px-4" type="button" onclick="window.location.href='<?= $SITEURL ?>/import/lazada_order_import.php'">
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
    (function resetLazadaImportStoredValues() {
        try {
            localStorage.setItem('page', 'invalid');
            localStorage.setItem('action', '');
            document.querySelectorAll('input[id], textarea[id], select[id]').forEach(function (field) {
                if (field.id) {
                    localStorage.removeItem(field.id);
                }
            });
        } catch (error) {
            // Ignore local storage access issues.
        }
    })();

    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    preloader(0, '');
    if (typeof setButtonColor === 'function') {
        setButtonColor();
    }
</script>

<script>
    <?php
    $airbillInlineScripts = array();
    if (function_exists('shopeeOmsRenderAirbillAttachmentPreviewScript')) {
        $airbillInlineScripts[] = shopeeOmsRenderAirbillAttachmentPreviewScript();
    }
    if (function_exists('shopeeOmsRenderAirbillPdfAutofillScript')) {
        $airbillInlineScripts[] = shopeeOmsRenderAirbillPdfAutofillScript();
    }
    foreach ($airbillInlineScripts as $airbillInlineScript) {
        $airbillInlineScript = trim((string) $airbillInlineScript);
        $airbillInlineScript = preg_replace('/^\s*<script\b[^>]*>/i', '', $airbillInlineScript);
        $airbillInlineScript = preg_replace('/<\/script>\s*$/i', '', $airbillInlineScript);
        if ($airbillInlineScript !== '') {
            echo $airbillInlineScript . "\n";
        }
    }
    ?>
</script>

<script>
    (function syncLazadaImportPreviewForm() {
        var previewForm = document.querySelector('form[data-lazada-import-preview="1"]');
        if (!previewForm) return;

        var customerOptions = <?= json_encode($customerNames, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var packageMeta = <?= json_encode($packageMeta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var brandOptions = <?= json_encode($brandOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var seriesOptions = <?= json_encode($seriesOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var countryOptions = <?= json_encode($countryOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function normalizeLookup(value) {
            return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
        }

        function clearInlineError(field) {
            if (!field) return;
            field.classList.remove('shopee-inline-invalid');
            var next = field.nextElementSibling;
            while (next && next.classList.contains('shopee-inline-error')) {
                var removable = next;
                next = next.nextElementSibling;
                removable.remove();
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

        function getInlineErrorMessage(field) {
            var customMessage = field && field.getAttribute('data-required-message');
            if (customMessage) return customMessage;
            if (field && field.id) {
                var label = previewForm.querySelector('label[for="' + field.id + '"]');
                if (label) {
                    var labelText = (label.textContent || '').replace(/\*/g, '').trim();
                    if (labelText) return labelText + ' is required.';
                }
            }
            return 'This field is required.';
        }

        function syncHiddenByMap(inputSelector, hiddenSelector, mapObject) {
            var input = previewForm.querySelector(inputSelector);
            var hidden = previewForm.querySelector(hiddenSelector);
            if (!input || !hidden) return;

            var normalizedValue = normalizeLookup(input.value);
            hidden.value = '';
            if (!normalizedValue) return;

            Object.keys(mapObject).some(function (id) {
                if (normalizeLookup(mapObject[id]) === normalizedValue) {
                    hidden.value = id;
                    input.value = mapObject[id];
                    return true;
                }
                return false;
            });
        }

        function bindSearch(inputSelector, hiddenSelector, searchType, dbTable, mapObject) {
            var input = previewForm.querySelector(inputSelector);
            if (!input) return;

            input.addEventListener('keyup', function () {
                if (typeof searchInput === 'function') {
                    searchInput({
                        search: input.value,
                        searchType: searchType,
                        elementID: input.id,
                        hiddenElementID: hiddenSelector.replace('#', ''),
                        dbTable: dbTable
                    }, '<?= $SITEURL ?>');
                }
                if (mapObject) {
                    setTimeout(function () {
                        syncHiddenByMap(inputSelector, hiddenSelector, mapObject);
                    }, 100);
                }
            });

            input.addEventListener('blur', function () {
                if (mapObject) {
                    syncHiddenByMap(inputSelector, hiddenSelector, mapObject);
                }
            });

            input.addEventListener('input', function () {
                clearInlineError(input);
            });
        }

        function applyPackageMeta() {
            var packageHidden = previewForm.querySelector('#package_hidden');
            var packageInput = previewForm.querySelector('#package_name');
            var brandInput = previewForm.querySelector('#brand_name');
            var brandHidden = previewForm.querySelector('#brand_hidden');
            var seriesInput = previewForm.querySelector('#series_name');
            var seriesHidden = previewForm.querySelector('#series_hidden');
            if (!packageHidden || !packageInput || !brandInput || !brandHidden || !seriesInput || !seriesHidden) {
                return;
            }

            var packageId = String(packageHidden.value || '').trim();
            if (!packageId || !packageMeta[packageId]) {
                if (!packageId) {
                    brandHidden.value = '';
                    seriesHidden.value = '';
                    brandInput.value = '';
                    seriesInput.value = '';
                }
                return;
            }

            var packageRow = packageMeta[packageId];
            if (packageRow.name) {
                packageInput.value = packageRow.name;
            }
            if (packageRow.brand_id) {
                brandHidden.value = packageRow.brand_id;
                brandInput.value = packageRow.brand_name || (brandOptions[packageRow.brand_id] || '');
            }
            if (packageRow.series_id) {
                seriesHidden.value = packageRow.series_id;
                seriesInput.value = packageRow.series_name || (seriesOptions[packageRow.series_id] || '');
            } else if (!seriesHidden.value) {
                seriesInput.value = '';
            }
        }

        function toggleAirbillFields() {
            var updateAirbill = previewForm.querySelector('#update_airbill');
            var updateAirbillToggle = previewForm.querySelector('#update_airbill_toggle');
            var airbillNo = previewForm.querySelector('#airbill_no');
            var airbillAttachment = previewForm.querySelector('#airbill_attachment');
            var existingAttachment = previewForm.querySelector('#airbill_attachment_value');
            if (!updateAirbill || !updateAirbillToggle || !airbillNo || !airbillAttachment) return;

            updateAirbill.value = updateAirbillToggle.checked ? 'yes' : 'no';
            var enabled = updateAirbillToggle.checked;
            airbillNo.disabled = !enabled;
            airbillAttachment.disabled = !enabled;
            airbillNo.required = enabled;
            airbillAttachment.required = enabled && (!existingAttachment || existingAttachment.value.trim() === '');
            [airbillNo, airbillAttachment].forEach(clearInlineError);
        }

        function validatePreviewForm() {
            var firstInvalid = null;
            var requiredFields = previewForm.querySelectorAll('input[required], select[required], textarea[required]');
            requiredFields.forEach(function (field) {
                if (field.disabled) return;
                clearInlineError(field);

                var empty = false;
                if (field.type === 'file') {
                    var existingAttachment = previewForm.querySelector('#airbill_attachment_value');
                    empty = field.files.length === 0 && (!existingAttachment || existingAttachment.value.trim() === '');
                } else {
                    empty = String(field.value || '').trim() === '';
                }

                if (empty) {
                    showInlineError(field, getInlineErrorMessage(field));
                    if (!firstInvalid) {
                        firstInvalid = field;
                    }
                }
            });

            if (firstInvalid) {
                firstInvalid.focus();
                return false;
            }

            return true;
        }

        bindSearch('#customer_name', '#customer_hidden', 'name', '<?= LAZADA_CUST_RCD ?>', customerOptions);
        bindSearch('#package_name', '#package_hidden', 'name', '<?= PKG ?>', <?= json_encode($packageNameOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        bindSearch('#brand_name', '#brand_hidden', 'name', '<?= BRAND ?>', brandOptions);
        bindSearch('#series_name', '#series_hidden', 'name', '<?= BRD_SERIES ?>', seriesOptions);
        bindSearch('#country_name', '#country_hidden', 'nicename', '<?= COUNTRIES ?>', countryOptions);

        function syncCustomerHiddenField() {
            syncHiddenByMap('#customer_name', '#customer_hidden', customerOptions);
        }

        var packageInput = previewForm.querySelector('#package_name');
        var packageHidden = previewForm.querySelector('#package_hidden');
        if (packageInput && packageHidden) {
            packageInput.addEventListener('change', applyPackageMeta);
            packageInput.addEventListener('blur', function () {
                setTimeout(applyPackageMeta, 100);
            });
        }
        applyPackageMeta();

        var customerInput = previewForm.querySelector('#customer_name');
        if (customerInput) {
            customerInput.addEventListener('change', syncCustomerHiddenField);
            customerInput.addEventListener('blur', syncCustomerHiddenField);
            syncCustomerHiddenField();
        }

        var useDetectedCustomerBtn = previewForm.querySelector('#use_detected_customer_btn');
        if (customerInput && useDetectedCustomerBtn) {
            useDetectedCustomerBtn.addEventListener('click', function () {
                var detectedCustomer = String(useDetectedCustomerBtn.getAttribute('data-detected-customer') || '').trim();
                if (detectedCustomer === '') {
                    return;
                }

                customerInput.value = detectedCustomer;
                syncCustomerHiddenField();
                clearInlineError(customerInput);
                customerInput.focus();
            });
        }

        toggleAirbillFields();

        if (window.shopeeOmsAirbillAttachmentPreview) {
            window.shopeeOmsAirbillAttachmentPreview.bind({
                fileInputSelector: '#airbill_attachment',
                previewWrapSelector: '#airbill_attachment_preview_wrap'
            });
        }

        if (window.shopeeOmsAirbillPdfAutofill) {
            window.shopeeOmsAirbillPdfAutofill.bind({
                fileInputSelector: '#airbill_attachment',
                airbillNoSelector: '#airbill_no',
                statusSelector: '#airbill_extract_status',
                workerSrc: '<?= $SITEURL ?>/finance/header/js/pdf.worker.min.js',
                errorClass: 'is-error'
            });
        }

        var updateAirbillToggle = previewForm.querySelector('#update_airbill_toggle');
        if (updateAirbillToggle) {
            updateAirbillToggle.addEventListener('change', toggleAirbillFields);
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
                if (String(field.value || '').trim() !== '') {
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
    (function setupLazadaImportPdfReader() {
        var uploadForm = document.getElementById('lzdUploadForm');
        var fileInput = document.getElementById('import_file');
        var clientPdfTextField = document.getElementById('client_pdf_text');
        var submitBtn = document.getElementById('lzdSubmitBtn');
        var statusNode = document.getElementById('lzd_pdf_extract_status');
        if (!uploadForm || !fileInput || !clientPdfTextField || !submitBtn || typeof pdfjsLib === 'undefined') {
            return;
        }

        pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= $SITEURL ?>/finance/header/js/pdf.worker.min.js';
        var clientPdfSubmitReady = false;

        function setStatus(message, isError) {
            if (!statusNode) return;
            statusNode.textContent = message || '';
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
                return pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
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
                return pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
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
                                        pageTexts.push(result && result.data && result.data.text ? String(result.data.text).trim() : '');
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
                clientPdfTextField.value = String(text || '').slice(0, 512 * 1024);
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
