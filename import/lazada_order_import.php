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
                    $sku = lazadaImportExtractSellerSku($sourceText, $packageMeta);
                    $price = lazadaImportExtractItemPrice($sourceText, $sku);
                    $voucher = lazadaImportExtractVoucher($sourceText);
                    $paidPrice = lazadaImportExtractPaidPrice($sourceText, $sku);
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
                        'payment_method' => '',
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
        'payment_method' => '',
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
                NULL,
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

function lazadaImportNormalizeOrderCandidate($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $digitCount = strlen(preg_replace('/\D+/', '', $raw));
    if ($digitCount < 10) {
        return '';
    }

    $normalized = strtoupper($raw);
    $normalized = strtr($normalized, array(
        'O' => '0',
        'Q' => '0',
        'D' => '0',
        'I' => '1',
        'L' => '1',
        '|' => '1',
        'S' => '5',
        'B' => '8',
    ));
    $normalized = preg_replace('/\D+/', '', $normalized);

    return strlen($normalized) >= 12 && strlen($normalized) <= 30 ? $normalized : '';
}

function lazadaImportExtractOrderNumber($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $priorityPatterns = array(
        '/Order\s+Detail\s*#?\s*([0-9\s]{12,50})/i',
        '/Order\s+Number\s*:?\s*([0-9\s]{12,50})/i',
        '/Your\s+ordered\s+items\s+for\s*([0-9\s]{12,50})/i',
        '/Print\s+Time\s*:?[^0-9]{0,40}([0-9\s]{12,50})/i',
    );

    foreach ($priorityPatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $candidate = lazadaImportNormalizeOrderCandidate($matches[1]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    $value = lazadaImportExtractFieldByLabels($text, array('Order Number'), array('Order Date', 'Invoice To', 'Invoice Date'));
    $candidate = lazadaImportNormalizeOrderCandidate($value);
    if ($candidate !== '') {
        return $candidate;
    }

    $candidates = array();
    if (preg_match_all('/(?:^|[^0-9])((?:[0-9][\s\-]*){12,30})(?=[^0-9]|$)/', $text, $matches)) {
        foreach ((array) $matches[1] as $match) {
            $candidate = lazadaImportNormalizeOrderCandidate($match);
            if ($candidate === '') {
                continue;
            }
            if (!isset($candidates[$candidate])) {
                $candidates[$candidate] = 0;
            }
            $candidates[$candidate]++;
        }
    }

    if (!empty($candidates)) {
        arsort($candidates);
        return (string) array_key_first($candidates);
    }

    return '';
}

function lazadaImportExtractSpecialField($text, $fieldName)
{
    $fieldName = strtoupper(preg_replace('/[^A-Z0-9_]+/i', '', (string) $fieldName));
    if ($fieldName === '') {
        return '';
    }

    $pattern = '/__LZD_FIELD_' . preg_quote($fieldName, '/') . '__\|\|\|([^\n\r]+)/i';
    if (preg_match_all($pattern, (string) $text, $matches)) {
        for ($index = count($matches[1]) - 1; $index >= 0; $index--) {
            $value = normalizeImportText($matches[1][$index]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function lazadaImportCleanCustomerName($name)
{
    $name = normalizeImportText((string) $name);
    if ($name === '') {
        return '';
    }

    $name = preg_replace('/\b(?:Invoice\s*Number|Order\s*Number|Order\s*Date|Invoice\s*To|Invoice\s*Date|Customer\s*Name|Receiver\s*Name)\s*:?\s*/i', ' ', $name);
    $name = preg_replace('/\b\d{1,2}\s*[\/\-\s]\s*\d{1,2}\s*[\/\-\s]\s*\d{2,4}\b/', ' ', (string) $name);
    $name = preg_replace('/\b\d{8,30}\b/', ' ', (string) $name);
    $name = preg_replace('/\b(?:Billing\s*Address|Shipping\s*Address|Contact\s*Phone|Payment\s*Method|Product\s*Name|Seller\s*SKU|Shop\s*SKU|Price|Paid\s*Price|Subtotal|Total|Voucher|Net\s*Paid)\b.*$/i', '', (string) $name);
    $name = preg_replace("/[^A-Za-z\.\-'\s]/", ' ', (string) $name);
    $name = preg_replace('/\s{2,}/', ' ', (string) $name);

    return trim((string) $name);
}

function lazadaImportLooksLikeCustomerNameCandidate($name)
{
    $name = lazadaImportCleanCustomerName($name);
    if ($name === '') {
        return false;
    }

    if (preg_match('/\d/', $name)) {
        return false;
    }

    if (preg_match('/[,\/\\@#:_\|]/', $name)) {
        return false;
    }

    $lettersOnly = preg_replace('/[^A-Za-z]/', '', $name);
    if (strlen((string) $lettersOnly) < 5) {
        return false;
    }

    if (strlen($name) > 100) {
        return false;
    }

    $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($words) || empty($words)) {
        return false;
    }

    $genericDocumentWords = array(
        'invoice', 'order', 'date', 'number', 'address', 'billing', 'shipping',
        'contact', 'phone', 'payment', 'method', 'product', 'seller', 'shop',
        'sku', 'price', 'paid', 'total', 'subtotal', 'voucher', 'barcode',
        'customer', 'receiver', 'item', 'items', 'email', 'warehouse', 'package'
    );

    foreach ($words as $word) {
        $wordKey = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $word));
        if ($wordKey !== '' && in_array($wordKey, $genericDocumentWords, true)) {
            return false;
        }
    }

    return true;
}

function lazadaImportGetTopInvoiceBlock($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $startPositions = array();
    foreach (array('Invoice Number', 'Order Number', 'Order Date', 'Invoice To') as $startLabel) {
        $startPos = stripos($text, $startLabel);
        if ($startPos !== false) {
            $startPositions[] = (int) $startPos;
        }
    }

    $start = !empty($startPositions) ? min($startPositions) : 0;
    $topBlock = substr($text, $start);

    $stopPositions = array();
    foreach (array('BILLING ADDRESS', 'SHIPPING ADDRESS', 'Your ordered items', 'Product name', 'Seller SKU', 'Ready To Ship') as $stopLabel) {
        $stopPos = stripos($topBlock, $stopLabel);
        if ($stopPos !== false) {
            $stopPositions[] = (int) $stopPos;
        }
    }

    if (!empty($stopPositions)) {
        return substr($topBlock, 0, min($stopPositions));
    }

    return substr($topBlock, 0, 1800);
}

function lazadaImportExtractCustomerNameFromSpecialField($text)
{
    $candidate = lazadaImportCleanCustomerName(lazadaImportExtractSpecialField($text, 'CUSTOMER_NAME'));
    return lazadaImportLooksLikeCustomerNameCandidate($candidate) ? $candidate : '';
}

function lazadaImportExtractCustomerNameFromInvoiceToRow($text)
{
    $topBlock = lazadaImportGetTopInvoiceBlock($text);
    if ($topBlock === '') {
        return '';
    }

    $patterns = array(
        '/Invoice\s*To\s*:?\s*([^\n\r]{2,160}?)(?=\s+Invoice\s*Date\b|\s+BILLING\s+ADDRESS\b|\s+SHIPPING\s+ADDRESS\b|$)/is',
        '/Invoice\s*To\s*:?\s*([A-Za-z][A-Za-z\s\.\-\']{4,100}?)(?:\||$)/is',
        '/Order\s*Date\s*:?\s*\d{1,2}\s*[\/\-\s]\s*\d{1,2}\s*[\/\-\s]\s*\d{2,4}\s+([A-Za-z][A-Za-z\s\.\-\']{4,100}?)\s+(?:Invoice\s*Date\s*:?\s*)?\d{1,2}\s*[\/\-\s]\s*\d{1,2}\s*[\/\-\s]\s*\d{2,4}/is'
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $topBlock, $matches)) {
            $candidate = lazadaImportCleanCustomerName($matches[1]);
            if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
                return $candidate;
            }
        }
    }

    $lines = getPdfTextLines($topBlock);
    foreach ($lines as $index => $line) {
        $line = normalizeImportText($line);
        if (stripos($line, 'Invoice To') === false) {
            continue;
        }

        $value = preg_replace('/^.*?Invoice\s*To\s*:?\s*/i', '', $line);
        $valueParts = preg_split('/\b(?:Invoice\s*Date|Billing\s*Address|Shipping\s*Address)\b/i', (string) $value);
        $candidate = lazadaImportCleanCustomerName(isset($valueParts[0]) ? $valueParts[0] : '');
        if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
            return $candidate;
        }

        for ($offset = 1; $offset <= 10; $offset++) {
            if (!isset($lines[$index + $offset])) {
                break;
            }
            $nextLine = normalizeImportText($lines[$index + $offset]);
            if (preg_match('/\b(?:Billing\s*Address|Shipping\s*Address|Your\s+ordered\s+items)\b/i', $nextLine)) {
                break;
            }
            if (preg_match('/\bInvoice\s*Date\b/i', $nextLine)) {
                continue;
            }
            $candidate = lazadaImportCleanCustomerName($nextLine);
            if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
                return $candidate;
            }
        }
    }

    if (preg_match_all('/__LZD_OCR_ROW__\|\|\|([^\n\r]+)/i', $topBlock, $rowMatches)) {
        foreach ((array) $rowMatches[1] as $rowText) {
            $rowText = normalizeImportText($rowText);
            if (stripos($rowText, 'Invoice To') === false) {
                continue;
            }
            $value = preg_replace('/^.*?Invoice\s*To\s*:?\s*/i', '', $rowText);
            $valueParts = preg_split('/\b(?:Invoice\s*Date|Billing\s*Address|Shipping\s*Address)\b/i', (string) $value);
            $candidate = lazadaImportCleanCustomerName(isset($valueParts[0]) ? $valueParts[0] : '');
            if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
                return $candidate;
            }
        }
    }

    return '';
}

function lazadaImportExtractCustomerNameFromInvoiceValueStack($text)
{
    $topBlock = lazadaImportGetTopInvoiceBlock($text);
    if ($topBlock === '') {
        return '';
    }

    if (preg_match('/\b\d{8,30}\b\s+\d{1,2}\s*[\/\-\s]\s*\d{1,2}\s*[\/\-\s]\s*\d{2,4}\s+([A-Za-z][A-Za-z\s\.\-\']{4,100}?)\s+\d{1,2}\s*[\/\-\s]\s*\d{1,2}\s*[\/\-\s]\s*\d{2,4}/is', $topBlock, $matches)) {
        $candidate = lazadaImportCleanCustomerName($matches[1]);
        if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
            return $candidate;
        }
    }

    $valueBlock = preg_replace('/\b(?:Invoice\s*Number|Order\s*Number|Order\s*Date|Invoice\s*To|Invoice\s*Date)\s*:?\s*/i', "\n", (string) $topBlock);
    $valueBlock = preg_replace('/\bINVOICE\b/i', "\n", (string) $valueBlock);
    $valueBlock = preg_replace('/\b\d{8,30}\b/', "\n", (string) $valueBlock);
    $valueBlock = preg_replace('/\b\d{1,2}\s*[\/\-\s]\s*\d{1,2}\s*[\/\-\s]\s*\d{2,4}\b/', "\n", (string) $valueBlock);

    $lines = getPdfTextLines($valueBlock);
    foreach ($lines as $line) {
        $pieces = preg_split('/\s{2,}|\t|\|/', $line);
        foreach ((array) $pieces as $piece) {
            $candidate = lazadaImportCleanCustomerName($piece);
            if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
                return $candidate;
            }
        }
    }

    return '';
}

function lazadaImportExtractCustomerNameNearInvoiceLabel($text)
{
    $name = lazadaImportExtractCustomerNameFromSpecialField($text);
    if ($name !== '') {
        return $name;
    }

    $topBlock = lazadaImportGetTopInvoiceBlock($text);
    if ($topBlock === '' || stripos($topBlock, 'Invoice To') === false) {
        return '';
    }

    $name = lazadaImportExtractCustomerNameFromInvoiceToRow($text);
    if ($name !== '') {
        return $name;
    }

    return lazadaImportExtractCustomerNameFromInvoiceValueStack($text);
}

function lazadaImportExtractCustomerNameFromTopInvoiceBlock($text)
{
    return lazadaImportExtractCustomerNameNearInvoiceLabel($text);
}

function lazadaImportCleanCustomerTableName($name)
{
    $name = lazadaImportCleanCustomerName($name);
    if ($name === '') {
        return '';
    }

    $lettersOnly = preg_replace('/[^A-Za-z]/', '', $name);
    if (strlen((string) $lettersOnly) < 2 || strlen((string) $lettersOnly) > 100) {
        return '';
    }

    if (preg_match('/\d|[,\/\\@#:_\|]/', $name)) {
        return '';
    }

    return $name;
}

function lazadaImportExtractCustomerNameFromCustomerTable($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    if (preg_match('/Customer\s+Name\s+Customer\s+ID.*?\n\s*([^\n\r]{1,160})/is', $text, $matches)) {
        $line = normalizeImportText($matches[1]);
        if (preg_match('/^(.+?)\s+\d{5,}\b/', $line, $nameMatches)) {
            $candidate = lazadaImportCleanCustomerTableName($nameMatches[1]);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

function lazadaImportLooksLikeDateLine($line)
{
    $line = strtoupper(normalizeImportText($line));
    if ($line === '') {
        return false;
    }

    $line = strtr($line, array(
        'O' => '0',
        'Q' => '0',
        'D' => '0',
        'I' => '1',
        'L' => '1',
        '|' => '1',
        'S' => '5',
        'B' => '8',
    ));
    $line = preg_replace('/\s+/', ' ', (string) $line);

    return (bool) preg_match('/^(?:\d{1,2}[\/\-. ]\d{1,2}[\/\-. ]\d{2,4}|\d{6,8})$/', $line);
}

function lazadaImportExtractCustomerNameFromDateStack($text)
{
    $topBlock = lazadaImportGetTopInvoiceBlock($text);
    $lines = getPdfTextLines($topBlock !== '' ? $topBlock : $text);
    if (empty($lines)) {
        return '';
    }

    for ($index = 0; $index < count($lines) - 2; $index++) {
        $firstLine = normalizeImportText($lines[$index]);
        $secondLine = normalizeImportText($lines[$index + 1]);
        $thirdLine = normalizeImportText($lines[$index + 2]);

        if (lazadaImportLooksLikeDateLine($firstLine) && lazadaImportLooksLikeDateLine($thirdLine)) {
            $candidate = lazadaImportCleanCustomerName($secondLine);
            if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
                return $candidate;
            }
        }
    }

    for ($index = 0; $index < count($lines) - 3; $index++) {
        $firstLine = normalizeImportText($lines[$index]);
        $secondLine = normalizeImportText($lines[$index + 1]);
        $thirdLine = normalizeImportText($lines[$index + 2]);
        $fourthLine = normalizeImportText($lines[$index + 3]);

        if (lazadaImportNormalizeOrderCandidate($firstLine) !== '' && lazadaImportLooksLikeDateLine($secondLine) && lazadaImportLooksLikeDateLine($fourthLine)) {
            $candidate = lazadaImportCleanCustomerName($thirdLine);
            if (lazadaImportLooksLikeCustomerNameCandidate($candidate)) {
                return $candidate;
            }
        }
    }

    return '';
}

function lazadaImportExtractCustomerName($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $labelAnchoredName = lazadaImportExtractCustomerNameNearInvoiceLabel($text);
    if ($labelAnchoredName !== '') {
        return $labelAnchoredName;
    }

    $value = lazadaImportExtractFieldByLabels($text, array('Invoice To'), array('Invoice Date', 'SHIPPING ADDRESS', 'BILLING ADDRESS'));
    if (lazadaImportLooksLikeCustomerNameCandidate($value)) {
        return lazadaImportCleanCustomerName($value);
    }

    $customerTableName = lazadaImportExtractCustomerNameFromCustomerTable($text);
    if ($customerTableName !== '') {
        return $customerTableName;
    }

    $stackName = lazadaImportExtractCustomerNameFromDateStack($text);
    if ($stackName !== '') {
        return $stackName;
    }

    return '';
}

function lazadaImportAddressLineLooksUsable($line)
{
    $line = normalizeImportText($line);
    if ($line === '') {
        return false;
    }

    if (preg_match('/^(?:BILLING\s+ADDRESS|SHIPPING\s+ADDRESS|Contact\s*Phone|Payment\s*Method|Your\s+ordered\s+items|Ready\s+To\s+Ship|#|Product\s+name|Seller\s+SKU|Shop\s+SKU)\b/i', $line)) {
        return false;
    }

    if (preg_match('/\b(?:Detail\s+Address|Receiver\s+Name|Receiver\s+Phone\s+Number|Show\s+Personal\s+Info)\b/i', $line)) {
        return false;
    }

    if (preg_match('/\b(?:Invoice\s*Number|Order\s*Number|Order\s*Date|Invoice\s*Date|Subtotal|Total|Voucher|Net\s*Paid)\b/i', $line)) {
        return false;
    }

    return (bool) preg_match('/[0-9A-Za-z\*].*(?:,|\*)/', $line);
}

function lazadaImportExtractShippingAddress($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $specialAddress = lazadaImportExtractSpecialField($text, 'SHIPPING_ADDRESS');
    if ($specialAddress !== '') {
        return lazadaImportCleanupAddress($specialAddress);
    }

    if (preg_match('/Shipping\s+Address(?:[^\n\r]{0,120})?\s+Detail\s+Address\s+Receiver\s+Name\s*([^\n\r]+?)(?=\s+Billing\s+Address|\s+Ready\s+To\s+Ship|\s+Receiver\s+Phone\s+Number|$)/is', $text, $matches)) {
        $detailAddress = lazadaImportCleanupAddress($matches[1]);
        if ($detailAddress !== '') {
            return $detailAddress;
        }
    }

    $value = lazadaImportExtractAddressBySideBySideLabel($text, 'SHIPPING ADDRESS');
    if ($value !== '') {
        return lazadaImportCleanupAddress($value);
    }

    $value = lazadaImportExtractFieldByLabels(
        $text,
        array('SHIPPING ADDRESS'),
        array('Contact Phone', 'Payment Method', 'Your ordered items for', 'BILLING ADDRESS')
    );
    if ($value !== '') {
        return lazadaImportCleanupAddress($value);
    }

    if (preg_match('/SHIPPING\s+ADDRESS\s*(.*?)(?:Contact\s*Phone|Payment\s*Method|Your\s+ordered\s+items|#\s*Product\s+name|$)/is', $text, $matches)) {
        return lazadaImportCleanupAddress($matches[1]);
    }

    return '';
}

function lazadaImportExtractAddressBySideBySideLabel($text, $targetLabel)
{
    $lines = getPdfTextLines($text);
    if (empty($lines)) {
        return '';
    }

    $targetLabel = strtoupper(trim((string) $targetLabel));
    $labelLineIndex = -1;
    $labelColumn = 0;

    foreach ($lines as $index => $line) {
        $normalizedLine = strtoupper(normalizeImportText($line));
        $labelPosition = strpos($normalizedLine, $targetLabel);
        if ($labelPosition === false) {
            continue;
        }

        $labelLineIndex = $index;
        $labelColumn = $labelPosition;
        break;
    }

    if ($labelLineIndex < 0) {
        return '';
    }

    $parts = array();
    for ($index = $labelLineIndex + 1; $index < count($lines); $index++) {
        $line = normalizeImportText($lines[$index]);
        if ($line === '') {
            continue;
        }

        if (preg_match('/^(?:Contact\s*Phone|Payment\s*Method|Your\s+ordered\s+items|Ready\s+To\s+Ship|#|Product\s+name|Seller\s+SKU|Shop\s+SKU)\b/i', $line)) {
            break;
        }

        $cleanLine = lazadaImportPickRightSideTableCell($line, $labelColumn);
        if ($cleanLine === '') {
            continue;
        }

        if (lazadaImportAddressLineLooksUsable($cleanLine)) {
            $parts[] = $cleanLine;
        }
    }

    if (count($parts) >= 2 && (int) $labelColumn <= 2) {
        return trim((string) $parts[count($parts) - 1]);
    }

    return trim(implode(' ', $parts));
}

function lazadaImportPickRightSideTableCell($line, $labelColumn)
{
    $line = normalizeImportText($line);
    if ($line === '') {
        return '';
    }

    $chunks = preg_split('/\s{2,}|\t|\|/', $line, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($chunks) && count($chunks) >= 2) {
        return trim((string) $chunks[count($chunks) - 1]);
    }

    if ((int) $labelColumn > 0 && strlen($line) > (int) $labelColumn) {
        $rightSide = trim(substr($line, (int) $labelColumn));
        if ($rightSide !== '') {
            return $rightSide;
        }
    }

    return $line;
}

function lazadaImportCleanupAddress($address)
{
    $address = normalizeImportText((string) $address);
    if ($address === '') {
        return '';
    }

    $address = preg_replace('/\b(?:SHIPPING\s+ADDRESS|BILLING\s+ADDRESS|Detail\s+Address)\b\s*:?\s*/i', ' ', $address);
    $address = preg_replace('/\bContact\s*Phone\s*:?.*$/i', '', (string) $address);
    $address = preg_replace('/\b(?:Payment\s*Method|Your\s+ordered\s+items|Product\s+name|Seller\s+SKU|Shop\s+SKU)\b.*$/i', '', (string) $address);
    $address = preg_replace('/\s*\|\s*/', ' ', (string) $address);

    $address = preg_replace('/^[^\p{L}\p{N}]+/u', '', (string) $address);
    $address = preg_replace('/^[A-Za-z]\s+(?=\d+\s*,)/u', '', (string) $address);

    $address = preg_replace('/\s*,\s*/', ', ', (string) $address);
    $address = preg_replace('/,\s*,+/u', ', ', (string) $address);
    $address = preg_replace('/,{2,}/', ',', (string) $address);
    $address = preg_replace('/\s{2,}/', ' ', (string) $address);
    $address = trim((string) $address, " ,");

    if (preg_match('/^(.{12,}?)\s+\1$/u', $address, $duplicateMatches)) {
        return trim((string) $duplicateMatches[1], " ,");
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

function lazadaImportNormalizeSkuForCompare($value)
{
    $value = strtoupper((string) $value);
    $value = preg_replace('/[^A-Z0-9]+/', '', $value);
    return strtr($value, array(
        'O' => '0',
        'Q' => '0',
        'I' => '1',
        'L' => '1',
        '|' => '1',
        '8' => 'S',
        '5' => 'S',
    ));
}

function lazadaImportCleanupSkuCandidate($value)
{
    $value = strtoupper(normalizeImportText((string) $value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\b(?:SELLER\s*SKU|SHOP\s*SKU|PRODUCT\s*NAME|PRICE|PAID\s*PRICE|RM|MYR|STANDARD|WAREHOUSE|DROPSHIPPING|READY|SHIP|ORDER\s+LINE\s+ID|ITEM\s+ID|SUBTOTAL|LESS|TOTAL|NET\s+PAID)\b\s*:?/i', ' ', $value);
    $value = preg_replace('/\s+(?:RM|MYR)?\s*[0-9][0-9,]*(?:\.\d{2})?.*$/i', '', (string) $value);
    $value = preg_replace('/[^A-Z0-9\-\/_\s]/', ' ', (string) $value);
    $value = preg_replace('/\s*[-\/_]\s*/', '-', (string) $value);
    $value = preg_replace('/\s+/', '', (string) $value);
    $value = preg_replace('/-+/', '-', (string) $value);
    $value = trim((string) $value, '-_/ ');

    return $value;
}

function lazadaImportIsLikelyMarketplaceSkuCode($sku)
{
    $sku = lazadaImportCleanupSkuCandidate($sku);
    if ($sku === '') {
        return false;
    }

    if (!preg_match('/[A-Z]/i', $sku) || !preg_match('/\d/', $sku)) {
        return false;
    }

    $plainSku = preg_replace('/[^A-Z0-9]/i', '', $sku);
    if (strlen((string) $plainSku) < 8) {
        return false;
    }

    $separatorCount = preg_match_all('/[-\/_]/', $sku);
    if ($separatorCount < 2) {
        return false;
    }

    if (preg_match('/[-\/_]$/', $sku)) {
        return false;
    }

    $segments = preg_split('/[-\/_]+/', $sku, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($segments) || count($segments) < 3) {
        return false;
    }

    foreach ($segments as $segment) {
        if (preg_match('/^[0-9]{6,}$/', $segment)) {
            return false;
        }
    }

    return true;
}

function lazadaImportSkuCandidateLooksUsable($sku)
{
    return lazadaImportIsLikelyMarketplaceSkuCode($sku);
}

function lazadaImportSkuCandidateScore($sku, $packageMeta)
{
    $sku = lazadaImportCleanupSkuCandidate($sku);
    if (!lazadaImportSkuCandidateLooksUsable($sku)) {
        return -1;
    }

    $plainSku = preg_replace('/[^A-Z0-9]/i', '', $sku);
    $separatorCount = preg_match_all('/[-\/_]/', $sku);
    $score = strlen((string) $plainSku) + ($separatorCount * 8);

    if (is_array($packageMeta) && !empty($packageMeta)) {
        $correctedSku = lazadaImportCorrectSkuFromPackageMeta($sku, $packageMeta);
        if ($correctedSku !== $sku && lazadaImportSkuCandidateLooksUsable($correctedSku)) {
            $score += 100;
        }

        foreach ($packageMeta as $packageRow) {
            $itemCode = isset($packageRow['item_code']) ? trim((string) $packageRow['item_code']) : '';
            if ($itemCode === '' || !lazadaImportIsLikelyMarketplaceSkuCode($itemCode)) {
                continue;
            }
            if (strcasecmp($itemCode, $sku) === 0 || lazadaImportNormalizeSkuForCompare($itemCode) === lazadaImportNormalizeSkuForCompare($sku)) {
                $score += 200;
                break;
            }
        }
    }

    return $score;
}

function lazadaImportAddSkuCandidate(&$candidates, $candidate)
{
    $candidate = lazadaImportCleanupSkuCandidate($candidate);
    if (!lazadaImportSkuCandidateLooksUsable($candidate)) {
        return;
    }

    $key = lazadaImportNormalizeSkuForCompare($candidate);
    if ($key === '') {
        return;
    }

    if (!isset($candidates[$key]) || strlen($candidate) > strlen($candidates[$key])) {
        $candidates[$key] = $candidate;
    }
}

function lazadaImportGetOrderedItemsSection($text)
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $startPositions = array();
    foreach (array('Your ordered items for', 'Ready To Ship Package', 'Seller SKU') as $startLabel) {
        $startPos = stripos($text, $startLabel);
        if ($startPos !== false) {
            $startPositions[] = (int) $startPos;
        }
    }

    if (empty($startPositions)) {
        return $text;
    }

    $section = substr($text, min($startPositions));
    $stopPositions = array();
    foreach (array('Subtotal:', 'Less:', 'Voucher applied', 'Total:', 'Shipping:', 'Net paid:', 'Upon receipt') as $stopLabel) {
        $stopPos = stripos($section, $stopLabel);
        if ($stopPos !== false) {
            $stopPositions[] = (int) $stopPos;
        }
    }

    if (!empty($stopPositions)) {
        $section = substr($section, 0, min($stopPositions));
    }

    return trim((string) $section);
}

function lazadaImportExtractSkuCandidatesFromWindow($window)
{
    $window = strtoupper(normalizeImportText((string) $window));
    if ($window === '') {
        return array();
    }

    $window = preg_replace('/\b(?:SELLER\s*SKU|SHOP\s*SKU|PRODUCT\s*NAME|PRICE|PAID\s*PRICE)\b\s*:?/i', ' ', $window);
    $candidates = array();

    if (preg_match_all('/\b([A-Z0-9]{1,12}(?:\s*[-\/_]\s*[A-Z0-9]{1,12}){2,8})\b/i', $window, $matches)) {
        foreach ((array) $matches[1] as $match) {
            $candidate = lazadaImportCleanupSkuCandidate($match);
            if (lazadaImportSkuCandidateLooksUsable($candidate)) {
                $candidates[] = $candidate;
            }
        }
    }

    return array_values(array_unique($candidates));
}

function lazadaImportExtractSplitSkuCandidates($text)
{
    $section = lazadaImportGetOrderedItemsSection($text);
    if ($section === '') {
        return array();
    }

    $lines = getPdfTextLines($section);
    $candidates = array();

    foreach ($lines as $index => $line) {
        $line = strtoupper(normalizeImportText($line));
        if ($line === '') {
            continue;
        }

        foreach (lazadaImportExtractSkuCandidatesFromWindow($line) as $candidate) {
            $candidates[] = $candidate;
        }

        if (preg_match_all('/\b([A-Z0-9]{1,12}(?:[-\/_][A-Z0-9]{1,12}){1,7}[-\/_])(?=\s|$)/i', $line, $prefixMatches)) {
            foreach ((array) $prefixMatches[1] as $prefix) {
                $prefix = lazadaImportCleanupSkuCandidate($prefix . 'X');
                $prefix = preg_replace('/X$/', '', $prefix);
                if ($prefix === '') {
                    continue;
                }

                for ($offset = 1; $offset <= 6; $offset++) {
                    if (!isset($lines[$index + $offset])) {
                        continue;
                    }
                    $nextLine = strtoupper(normalizeImportText($lines[$index + $offset]));
                    if ($nextLine === '') {
                        continue;
                    }

                    if (preg_match_all('/\b([A-Z0-9]{1,12}(?:[-\/_][A-Z0-9]{1,12}){1,5})\b/i', $nextLine, $suffixMatches)) {
                        foreach ((array) $suffixMatches[1] as $suffix) {
                            $combined = lazadaImportCleanupSkuCandidate($prefix . $suffix);
                            if (lazadaImportSkuCandidateLooksUsable($combined)) {
                                $candidates[] = $combined;
                            }
                        }
                    }
                }
            }
        }
    }

    return array_values(array_unique($candidates));
}

function lazadaImportRepairCommonSkuOcrErrors($sku)
{
    $sku = lazadaImportCleanupSkuCandidate($sku);
    if ($sku === '') {
        return '';
    }

    $segments = preg_split('/[-\/]+/', strtoupper($sku), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($segments) || count($segments) < 4) {
        return $sku;
    }

    foreach ($segments as $index => $segment) {
        if ($index < 3) {
            continue;
        }

        if (preg_match('/^([0-9])[85]([0-9][A-Z])$/', $segment, $matches)) {
            $segments[$index] = $matches[1] . 'S' . $matches[2];
            continue;
        }

        if (preg_match('/^([0-9])[85]([A-Z0-9]{1,4})$/', $segment, $matches) && preg_match('/[A-Z]/', $matches[2])) {
            $segments[$index] = $matches[1] . 'S' . $matches[2];
            continue;
        }
    }

    return implode('-', $segments);
}

function lazadaImportExtractBestSkuLikePattern($value)
{
    $value = strtoupper(normalizeImportText((string) $value));
    if ($value === '') {
        return '';
    }

    $matches = array();
    if (!preg_match_all('/\b([A-Z][A-Z0-9]{1,7}(?:\s*[-\/_]\s*[A-Z0-9]{1,8}){3,7})\b/i', $value, $matches)) {
        return '';
    }

    $best = '';
    $bestScore = -1;
    foreach ((array) $matches[1] as $match) {
        $candidate = lazadaImportCleanupSkuCandidate($match);
        if (!lazadaImportSkuCandidateLooksUsable($candidate)) {
            continue;
        }

        $plain = preg_replace('/[^A-Z0-9]/i', '', $candidate);
        $segments = preg_split('/[-\/]+/', $candidate, -1, PREG_SPLIT_NO_EMPTY);
        $score = strlen((string) $plain) + (is_array($segments) ? count($segments) * 10 : 0);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }

    return $best;
}

function lazadaImportCorrectSkuFromPackageMeta($sku, $packageMeta)
{
    $sku = lazadaImportRepairCommonSkuOcrErrors($sku);
    if ($sku === '' || !is_array($packageMeta) || empty($packageMeta)) {
        return $sku;
    }

    $skuKey = lazadaImportNormalizeSkuForCompare($sku);
    if ($skuKey === '') {
        return $sku;
    }

    $bestItemCode = '';
    $bestDistance = null;
    foreach ($packageMeta as $packageRow) {
        $itemCode = isset($packageRow['item_code']) ? trim((string) $packageRow['item_code']) : '';
        if ($itemCode === '' || !lazadaImportIsLikelyMarketplaceSkuCode($itemCode)) {
            continue;
        }

        $itemCodeKey = lazadaImportNormalizeSkuForCompare($itemCode);
        if ($itemCodeKey === '') {
            continue;
        }

        if ($itemCodeKey === $skuKey) {
            return $itemCode;
        }

        if (strlen($skuKey) >= 8 && (strpos($itemCodeKey, $skuKey) !== false || strpos($skuKey, $itemCodeKey) !== false)) {
            return $itemCode;
        }

        if (function_exists('levenshtein') && abs(strlen($itemCodeKey) - strlen($skuKey)) <= 3) {
            $distance = levenshtein($itemCodeKey, $skuKey);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestItemCode = $itemCode;
            }
        }
    }

    if ($bestItemCode !== '' && $bestDistance !== null && $bestDistance <= max(1, (int) floor(strlen($skuKey) * 0.18))) {
        return $bestItemCode;
    }

    return $sku;
}

function lazadaImportExtractSellerSkuFromSpecialField($text, $packageMeta = array())
{
    $candidate = lazadaImportRepairCommonSkuOcrErrors(lazadaImportExtractSpecialField($text, 'SELLER_SKU'));
    if ($candidate === '') {
        return '';
    }

    $candidate = lazadaImportCorrectSkuFromPackageMeta($candidate, $packageMeta);
    return lazadaImportSkuCandidateLooksUsable($candidate) ? $candidate : '';
}

function lazadaImportExtractSellerSkuFromPackageTokens($text, $packageMeta)
{
    $section = lazadaImportGetOrderedItemsSection($text);
    if ($section === '' || !is_array($packageMeta) || empty($packageMeta)) {
        return '';
    }

    $sectionKey = lazadaImportNormalizeSkuForCompare($section);
    if ($sectionKey === '') {
        return '';
    }

    $bestSku = '';
    $bestScore = -1;

    foreach ($packageMeta as $packageRow) {
        $itemCode = isset($packageRow['item_code']) ? trim((string) $packageRow['item_code']) : '';
        if ($itemCode === '' || !lazadaImportIsLikelyMarketplaceSkuCode($itemCode)) {
            continue;
        }

        $itemCodeKey = lazadaImportNormalizeSkuForCompare($itemCode);
        if ($itemCodeKey === '') {
            continue;
        }

        if (strpos($sectionKey, $itemCodeKey) !== false) {
            return $itemCode;
        }

        $tokens = preg_split('/[^A-Z0-9]+/i', strtoupper($itemCode), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || count($tokens) < 3) {
            continue;
        }

        $position = 0;
        $matchedCount = 0;
        $score = 0;
        foreach ($tokens as $token) {
            $tokenKey = lazadaImportNormalizeSkuForCompare($token);
            if ($tokenKey === '') {
                continue;
            }

            $foundPosition = strpos($sectionKey, $tokenKey, $position);
            if ($foundPosition === false) {
                continue;
            }

            $position = $foundPosition + strlen($tokenKey);
            $matchedCount++;
            $score += strlen($tokenKey) * 5;
        }

        if ($matchedCount === count($tokens)) {
            $score += count($tokens) * 30;
            $score += strlen($itemCodeKey);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSku = $itemCode;
            }
        }
    }

    return $bestSku;
}

function lazadaImportExtractSellerSku($text, $packageMeta = array())
{
    $text = lazadaImportNormalizeSourceText($text);
    if ($text === '') {
        return '';
    }

    $specialSku = lazadaImportExtractSellerSkuFromSpecialField($text, $packageMeta);
    if ($specialSku !== '') {
        return $specialSku;
    }

    $packageMatchedSku = lazadaImportExtractSellerSkuFromPackageTokens($text, $packageMeta);
    if ($packageMatchedSku !== '') {
        return $packageMatchedSku;
    }

    $candidates = array();
    foreach (lazadaImportExtractSplitSkuCandidates($text) as $candidate) {
        lazadaImportAddSkuCandidate($candidates, $candidate);
    }

    $section = lazadaImportGetOrderedItemsSection($text);
    foreach (lazadaImportExtractSkuCandidatesFromWindow($section) as $candidate) {
        lazadaImportAddSkuCandidate($candidates, $candidate);
    }

    $lines = getPdfTextLines($section !== '' ? $section : $text);
    foreach ($lines as $index => $line) {
        $windowParts = array(normalizeImportText($line));
        for ($offset = 1; $offset <= 6; $offset++) {
            if (isset($lines[$index + $offset])) {
                $windowParts[] = normalizeImportText($lines[$index + $offset]);
            }
        }
        $window = implode(' ', $windowParts);

        if (stripos($window, 'Seller SKU') !== false || preg_match('/[-\/_]/', $window)) {
            foreach (lazadaImportExtractSkuCandidatesFromWindow($window) as $candidate) {
                lazadaImportAddSkuCandidate($candidates, $candidate);
            }
        }
    }

    $bestSku = '';
    $bestScore = -1;
    foreach ($candidates as $candidate) {
        $candidate = lazadaImportCorrectSkuFromPackageMeta($candidate, $packageMeta);
        $score = lazadaImportSkuCandidateScore($candidate, $packageMeta);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSku = $candidate;
        }
    }

    $bestSku = lazadaImportRepairCommonSkuOcrErrors($bestSku);
    return lazadaImportSkuCandidateLooksUsable($bestSku) ? $bestSku : '';
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
    $sku = lazadaImportRepairCommonSkuOcrErrors(trim((string) $sku));
    if ($sku === '' || !lazadaImportIsLikelyMarketplaceSkuCode($sku) || !is_array($packageMeta) || empty($packageMeta)) {
        return '';
    }

    $skuLookup = normalizeImportLookup($sku);
    $skuOcrLookup = lazadaImportNormalizeSkuForCompare($sku);

    foreach ($packageMeta as $packageId => $packageRow) {
        $itemCode = isset($packageRow['item_code']) ? (string) $packageRow['item_code'] : '';
        if ($itemCode === '' || !lazadaImportIsLikelyMarketplaceSkuCode($itemCode)) {
            continue;
        }
        if (strcasecmp($itemCode, $sku) === 0) {
            return (string) $packageId;
        }
        if ($skuLookup !== '' && normalizeImportLookup($itemCode) === $skuLookup) {
            return (string) $packageId;
        }
        if ($skuOcrLookup !== '' && lazadaImportNormalizeSkuForCompare($itemCode) === $skuOcrLookup) {
            return (string) $packageId;
        }
    }

    foreach ($packageMeta as $packageId => $packageRow) {
        $itemCode = isset($packageRow['item_code']) ? (string) $packageRow['item_code'] : '';
        if ($itemCode === '' || !lazadaImportIsLikelyMarketplaceSkuCode($itemCode)) {
            continue;
        }

        $itemCodeKey = normalizeImportLookup($itemCode);
        $itemCodeOcrKey = lazadaImportNormalizeSkuForCompare($itemCode);

        if ($skuLookup !== '' && strlen($skuLookup) >= 10 && $itemCodeKey !== '' && (strpos($itemCodeKey, $skuLookup) !== false || strpos($skuLookup, $itemCodeKey) !== false)) {
            return (string) $packageId;
        }

        if ($skuOcrLookup !== '' && strlen($skuOcrLookup) >= 10 && $itemCodeOcrKey !== '' && (strpos($itemCodeOcrKey, $skuOcrLookup) !== false || strpos($skuOcrLookup, $itemCodeOcrKey) !== false)) {
            return (string) $packageId;
        }
    }

    $bestPackageId = '';
    $bestDistance = null;
    if (function_exists('levenshtein') && $skuOcrLookup !== '') {
        foreach ($packageMeta as $packageId => $packageRow) {
            $itemCode = isset($packageRow['item_code']) ? (string) $packageRow['item_code'] : '';
            if ($itemCode === '' || !lazadaImportIsLikelyMarketplaceSkuCode($itemCode)) {
                continue;
            }
            $itemCodeOcrKey = lazadaImportNormalizeSkuForCompare($itemCode);
            if ($itemCodeOcrKey === '' || abs(strlen($itemCodeOcrKey) - strlen($skuOcrLookup)) > 3) {
                continue;
            }

            $distance = levenshtein($itemCodeOcrKey, $skuOcrLookup);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestPackageId = (string) $packageId;
            }
        }
    }

    if ($bestPackageId !== '' && $bestDistance !== null && $bestDistance <= max(1, (int) floor(strlen($skuOcrLookup) * 0.18))) {
        return $bestPackageId;
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
                            return page.getTextContent({ normalizeWhitespace: true }).then(function (textContent) {
                                var items = [];

                                (textContent.items || []).forEach(function (item) {
                                    var text = typeof item.str === 'string' ? item.str.trim() : '';
                                    if (text === '' || !item.transform || item.transform.length < 6) {
                                        return;
                                    }

                                    items.push({
                                        text: text,
                                        x: Number(item.transform[4] || 0),
                                        y: Number(item.transform[5] || 0)
                                    });
                                });

                                if (items.length === 0) {
                                    return '';
                                }

                                items.sort(function (a, b) {
                                    if (Math.abs(b.y - a.y) > 3) {
                                        return b.y - a.y;
                                    }
                                    return a.x - b.x;
                                });

                                var rows = [];
                                items.forEach(function (item) {
                                    var lastRow = rows.length > 0 ? rows[rows.length - 1] : null;

                                    if (lastRow && Math.abs(lastRow.y - item.y) <= 3) {
                                        lastRow.items.push(item);
                                    } else {
                                        rows.push({
                                            y: item.y,
                                            items: [item]
                                        });
                                    }
                                });

                                return rows.map(function (row) {
                                    row.items.sort(function (a, b) {
                                        return a.x - b.x;
                                    });

                                    return row.items.map(function (item) {
                                        return item.text;
                                    }).join(' ');
                                }).join('\n');
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

        function buildLazadaOcrRows(result) {
            var words = result && result.data && Array.isArray(result.data.words) ? result.data.words : [];
            if (!words.length) {
                return '';
            }

            function normalizeLine(value) {
                return String(value || '').replace(/\s+/g, ' ').trim();
            }

            function cleanFieldValue(value) {
                return normalizeLine(value).replace(/^[:|\-\s]+/, '').replace(/[:|\-\s]+$/, '').trim();
            }

            function cleanSkuValue(value) {
                value = String(value || '').toUpperCase();
                value = value.replace(/SELLER\s*SKU|SHOP\s*SKU|PRODUCT\s*NAME|PRICE|PAID\s*PRICE/g, ' ');
                value = value.replace(/[^A-Z0-9\-\/_\s]/g, ' ');
                value = value.replace(/\s*[\-\/_]\s*/g, '-');
                value = value.replace(/\s+/g, '');
                value = value.replace(/\-+/g, '-');
                value = value.replace(/^[-_\/]+|[-_\/]+$/g, '');
                return value;
            }

            function repairSku(value) {
                value = cleanSkuValue(value);
                var parts = value.split('-').filter(Boolean);
                if (parts.length >= 4) {
                    for (var i = 3; i < parts.length; i++) {
                        parts[i] = parts[i].replace(/^([0-9])[85]([0-9][A-Z])$/, '$1S$2');
                        parts[i] = parts[i].replace(/^([0-9])[85]([A-Z0-9]{1,4})$/, '$1S$2');
                    }
                    value = parts.join('-');
                }
                return value;
            }

            function looksLikeSku(value) {
                value = repairSku(value);
                var plain = value.replace(/[^A-Z0-9]/g, '');
                var separators = (value.match(/[-\/_]/g) || []).length;
                return /[A-Z]/.test(value) && /\d/.test(value) && plain.length >= 8 && separators >= 2 && !/[-\/_]$/.test(value);
            }

            var normalizedWords = [];
            words.forEach(function (word) {
                var text = word && typeof word.text === 'string' ? word.text.trim() : '';
                if (!text || !word.bbox) {
                    return;
                }

                var x0 = Number(word.bbox.x0 || 0);
                var y0 = Number(word.bbox.y0 || 0);
                var x1 = Number(word.bbox.x1 || x0);
                var y1 = Number(word.bbox.y1 || y0);
                var height = Math.max(1, y1 - y0);
                var centerX = x0 + ((x1 - x0) / 2);
                var centerY = y0 + (height / 2);

                normalizedWords.push({
                    text: text,
                    x0: x0,
                    x1: x1,
                    y0: y0,
                    y1: y1,
                    centerX: centerX,
                    centerY: centerY,
                    height: height
                });
            });

            if (!normalizedWords.length) {
                return '';
            }

            normalizedWords.sort(function (a, b) {
                if (Math.abs(a.centerY - b.centerY) > Math.max(8, Math.min(a.height, b.height) * 0.65)) {
                    return a.centerY - b.centerY;
                }
                return a.x0 - b.x0;
            });

            var rows = [];
            normalizedWords.forEach(function (word) {
                var row = rows.length ? rows[rows.length - 1] : null;
                var tolerance = Math.max(10, word.height * 0.75);
                if (row && Math.abs(row.centerY - word.centerY) <= tolerance) {
                    row.words.push(word);
                    row.centerY = ((row.centerY * (row.words.length - 1)) + word.centerY) / row.words.length;
                } else {
                    rows.push({
                        centerY: word.centerY,
                        words: [word]
                    });
                }
            });

            rows.forEach(function (row) {
                row.words.sort(function (a, b) {
                    return a.x0 - b.x0;
                });
                row.text = normalizeLine(row.words.map(function (word) { return word.text; }).join(' '));
                row.upper = row.text.toUpperCase();
            });

            var fieldRows = [];
            var rowTexts = [];

            function pushField(name, value) {
                value = cleanFieldValue(value);
                if (value !== '') {
                    fieldRows.push('__LZD_FIELD_' + name + '__|||' + value);
                }
            }

            // Customer name from the row containing Invoice To.
            rows.some(function (row, rowIndex) {
                if (row.upper.indexOf('INVOICE TO') === -1) {
                    return false;
                }

                var labelEndX = 0;
                row.words.forEach(function (word, wordIndex) {
                    var current = String(word.text || '').toUpperCase().replace(/[^A-Z]/g, '');
                    var next = row.words[wordIndex + 1] ? String(row.words[wordIndex + 1].text || '').toUpperCase().replace(/[^A-Z]/g, '') : '';
                    if (current === 'INVOICE' && next === 'TO') {
                        labelEndX = Math.max(labelEndX, row.words[wordIndex + 1].x1);
                    }
                });

                var valueWords = row.words.filter(function (word) {
                    var clean = String(word.text || '').replace(/[^A-Za-z]/g, '');
                    return word.x0 > labelEndX + 8 && clean !== '' && !/^(Invoice|Date)$/i.test(clean);
                });
                var value = normalizeLine(valueWords.map(function (word) { return word.text; }).join(' '));
                value = value.replace(/\bInvoice\s*Date\b.*$/i, '').trim();

                if (!value && rows[rowIndex + 1]) {
                    value = rows[rowIndex + 1].text.replace(/\bInvoice\s*Date\b.*$/i, '').trim();
                }

                value = value.replace(/[^A-Za-z\.\-'\s]/g, ' ').replace(/\s{2,}/g, ' ').trim();
                if (value.replace(/[^A-Za-z]/g, '').length >= 5) {
                    pushField('CUSTOMER_NAME', value);
                    return true;
                }

                return false;
            });

            // Shipping address: use the column below SHIPPING ADDRESS, not the billing column.
            rows.some(function (row, rowIndex) {
                if (row.upper.indexOf('SHIPPING ADDRESS') === -1) {
                    return false;
                }

                var labelStartX = 0;
                var labelEndX = 0;
                row.words.forEach(function (word, wordIndex) {
                    var current = String(word.text || '').toUpperCase().replace(/[^A-Z]/g, '');
                    var next = row.words[wordIndex + 1] ? String(row.words[wordIndex + 1].text || '').toUpperCase().replace(/[^A-Z]/g, '') : '';
                    if (current === 'SHIPPING' && next === 'ADDRESS') {
                        labelStartX = word.x0;
                        labelEndX = row.words[wordIndex + 1].x1;
                    }
                });

                if (labelStartX <= 0) {
                    return false;
                }

                var addressParts = [];
                for (var i = rowIndex + 1; i < rows.length; i++) {
                    var nextRow = rows[i];
                    if (/^(CONTACT\s*PHONE|PAYMENT\s*METHOD|YOUR\s+ORDERED\s+ITEMS|#|PRODUCT\s+NAME|SELLER\s+SKU)/i.test(nextRow.text)) {
                        break;
                    }

                    var wordsInColumn = nextRow.words.filter(function (word) {
                        return word.centerX >= labelStartX - 20;
                    });
                    var line = normalizeLine(wordsInColumn.map(function (word) { return word.text; }).join(' '));
                    line = line.replace(/\bContact\s*Phone\b.*$/i, '').trim();
                    if (line && /[0-9A-Za-z\*]/.test(line) && /[,\*]/.test(line)) {
                        addressParts.push(line);
                    }
                }

                if (addressParts.length) {
                    pushField('SHIPPING_ADDRESS', addressParts.join(' '));
                    return true;
                }
                return false;
            });

            // Seller SKU: collect words inside the Seller SKU column under its header.
            rows.some(function (row, rowIndex) {
                if (row.upper.indexOf('SELLER') === -1 || row.upper.indexOf('SKU') === -1) {
                    return false;
                }

                var sellerStartX = 0;
                var sellerEndX = 0;
                var shopStartX = 0;

                row.words.forEach(function (word, wordIndex) {
                    var current = String(word.text || '').toUpperCase().replace(/[^A-Z]/g, '');
                    var next = row.words[wordIndex + 1] ? String(row.words[wordIndex + 1].text || '').toUpperCase().replace(/[^A-Z]/g, '') : '';
                    if (current === 'SELLER' && next === 'SKU') {
                        sellerStartX = word.x0;
                        sellerEndX = row.words[wordIndex + 1].x1;
                    }
                    if (current === 'SHOP' && next === 'SKU') {
                        shopStartX = word.x0;
                    }
                });

                if (sellerStartX <= 0) {
                    return false;
                }
                if (shopStartX <= sellerStartX) {
                    shopStartX = sellerEndX + 220;
                }

                var skuParts = [];
                for (var i = rowIndex + 1; i < rows.length; i++) {
                    var itemRow = rows[i];
                    if (/^(SUBTOTAL|LESS|TOTAL|SHIPPING|NET\s*PAID|UPON\s+RECEIPT)/i.test(itemRow.text)) {
                        break;
                    }

                    var sellerWords = itemRow.words.filter(function (word) {
                        return word.centerX >= sellerStartX - 35 && word.centerX < shopStartX - 10;
                    });
                    var part = normalizeLine(sellerWords.map(function (word) { return word.text; }).join(' '));
                    part = cleanSkuValue(part);
                    if (part) {
                        skuParts.push(part);
                    }
                }

                var joined = repairSku(skuParts.join('-'));
                if (looksLikeSku(joined)) {
                    pushField('SELLER_SKU', joined);
                    return true;
                }

                return false;
            });

            rows.forEach(function (row) {
                if (row.text) {
                    rowTexts.push(row.text);
                    rowTexts.push('__LZD_OCR_ROW__|||' + row.text);
                }
            });

            return fieldRows.concat(rowTexts).join('\n').trim();
        }

        function prepareLazadaOcrCanvas(sourceCanvas) {
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d', { willReadFrequently: true });
            canvas.width = sourceCanvas.width;
            canvas.height = sourceCanvas.height;

            if (!context) {
                return sourceCanvas;
            }

            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(sourceCanvas, 0, 0);

            try {
                var imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                var data = imageData.data;
                for (var i = 0; i < data.length; i += 4) {
                    var gray = (data[i] * 0.299) + (data[i + 1] * 0.587) + (data[i + 2] * 0.114);
                    var enhanced = gray < 210 ? Math.max(0, gray - 35) : 255;
                    data[i] = enhanced;
                    data[i + 1] = enhanced;
                    data[i + 2] = enhanced;
                    data[i + 3] = 255;
                }
                context.putImageData(imageData, 0, 0);
            } catch (error) {
                return sourceCanvas;
            }

            return canvas;
        }

        function cropLazadaCanvas(sourceCanvas, topRatio, bottomRatio) {
            var top = Math.max(0, Math.floor(sourceCanvas.height * topRatio));
            var bottom = Math.min(sourceCanvas.height, Math.ceil(sourceCanvas.height * bottomRatio));
            var height = Math.max(1, bottom - top);
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d');
            canvas.width = sourceCanvas.width;
            canvas.height = height;
            if (!context) {
                return sourceCanvas;
            }
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(sourceCanvas, 0, top, sourceCanvas.width, height, 0, 0, sourceCanvas.width, height);
            return canvas;
        }

        function cropLazadaCanvasRect(sourceCanvas, leftRatio, topRatio, rightRatio, bottomRatio) {
            var left = Math.max(0, Math.floor(sourceCanvas.width * leftRatio));
            var top = Math.max(0, Math.floor(sourceCanvas.height * topRatio));
            var right = Math.min(sourceCanvas.width, Math.ceil(sourceCanvas.width * rightRatio));
            var bottom = Math.min(sourceCanvas.height, Math.ceil(sourceCanvas.height * bottomRatio));
            var width = Math.max(1, right - left);
            var height = Math.max(1, bottom - top);
            var canvas = document.createElement('canvas');
            var context = canvas.getContext('2d');
            canvas.width = width;
            canvas.height = height;
            if (!context) {
                return sourceCanvas;
            }
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(sourceCanvas, left, top, width, height, 0, 0, width, height);
            return canvas;
        }

        function splitLazadaOcrLines(text) {
            return String(text || '')
                .replace(/\r/g, '\n')
                .split(/\n+/)
                .map(function (line) {
                    return String(line || '').replace(/\s+/g, ' ').trim();
                })
                .filter(function (line) {
                    return line !== '';
                });
        }

        function normalizeLazadaDateCandidate(value) {
            value = String(value || '').toUpperCase().replace(/[^0-9OQILD SB\/\-. ]+/g, ' ');
            value = value.replace(/[OQD]/g, '0').replace(/[IL|]/g, '1').replace(/S/g, '5').replace(/B/g, '8');
            value = value.replace(/\s+/g, ' ').trim();
            return value;
        }

        function looksLikeLazadaDateLine(value) {
            var normalized = normalizeLazadaDateCandidate(value);
            return /^(?:\d{1,2}[\/\-. ]\d{1,2}[\/\-. ]\d{2,4}|\d{6,8})$/.test(normalized);
        }

        function looksLikeLazadaOrderNumberLine(value) {
            var normalized = String(value || '').toUpperCase()
                .replace(/[OQD]/g, '0')
                .replace(/[IL|]/g, '1')
                .replace(/[^0-9]+/g, '');
            return normalized.length >= 12 && normalized.length <= 20;
        }

        function normalizeLazadaCustomerNameCandidate(value) {
            value = String(value || '')
                .replace(/\b(?:INVOICE|ORDER|NUMBER|DATE|INVOICE TO|INVOICE DATE)\b/gi, ' ')
                .replace(/[^A-Za-z.\-'\s]/g, ' ')
                .replace(/\s{2,}/g, ' ')
                .trim();
            return value;
        }

        function looksLikeLazadaCustomerNameLine(value) {
            var normalized = normalizeLazadaCustomerNameCandidate(value);
            if (!normalized) {
                return false;
            }

            if (/\d/.test(normalized) || /[,\/\\@#:_|]/.test(normalized)) {
                return false;
            }

            var words = normalized.split(/\s+/).filter(Boolean);
            if (!words.length) {
                return false;
            }

            var lettersOnly = normalized.replace(/[^A-Za-z]/g, '');
            if (lettersOnly.length < 5 || normalized.length > 80) {
                return false;
            }

            return words.every(function (word) {
                return /^[A-Za-z.\-']+$/.test(word);
            });
        }

        function extractLazadaCustomerNameFromStackText(text) {
            var lines = splitLazadaOcrLines(text);
            if (!lines.length) {
                return '';
            }

            for (var index = 0; index < lines.length; index++) {
                var currentLine = lines[index];
                if (looksLikeLazadaDateLine(currentLine) && lines[index + 1] && lines[index + 2]) {
                    var middleLine = lines[index + 1];
                    var nextLine = lines[index + 2];
                    if (looksLikeLazadaCustomerNameLine(middleLine) && looksLikeLazadaDateLine(nextLine)) {
                        return normalizeLazadaCustomerNameCandidate(middleLine);
                    }
                }
            }

            for (var i = 0; i < lines.length; i++) {
                if (!looksLikeLazadaOrderNumberLine(lines[i])) {
                    continue;
                }

                for (var offset = 1; offset <= 3; offset++) {
                    var dateLine = lines[i + offset];
                    var nameLine = lines[i + offset + 1];
                    var trailingDateLine = lines[i + offset + 2];
                    if (!dateLine || !nameLine) {
                        break;
                    }

                    if (looksLikeLazadaDateLine(dateLine) && looksLikeLazadaCustomerNameLine(nameLine)) {
                        if (!trailingDateLine || looksLikeLazadaDateLine(trailingDateLine)) {
                            return normalizeLazadaCustomerNameCandidate(nameLine);
                        }
                    }
                }
            }

            return '';
        }

        function recognizeLazadaCanvas(canvas, pageSegMode, options) {
            options = options || {};
            return Tesseract.recognize(canvas, 'eng', {
                tessedit_pageseg_mode: String(pageSegMode || '6'),
                preserve_interword_spaces: '1',
                tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_/.,:*()# RMrm| '
            }).then(function (result) {
                var text = result && result.data && result.data.text ? String(result.data.text).trim() : '';
                var rows = buildLazadaOcrRows(result);
                var parts = [];

                if (options.extractCustomerStack) {
                    var stackedCustomerName = extractLazadaCustomerNameFromStackText(text);
                    if (stackedCustomerName) {
                        parts.push('__LZD_FIELD_CUSTOMER_NAME__|||' + stackedCustomerName);
                    }
                }

                if (text) {
                    parts.push(text);
                }
                if (rows) {
                    parts.push(rows);
                }

                return parts.join('\n').trim();
            }).catch(function () {
                return '';
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
                                var viewport = page.getViewport({ scale: 3.5 });
                                var canvas = document.createElement('canvas');
                                var context = canvas.getContext('2d', { willReadFrequently: true });
                                canvas.width = viewport.width;
                                canvas.height = viewport.height;

                                if (!context) {
                                    pageTexts.push('');
                                    return;
                                }

                                context.fillStyle = '#ffffff';
                                context.fillRect(0, 0, canvas.width, canvas.height);

                                return page.render({
                                    canvasContext: context,
                                    viewport: viewport
                                }).promise.then(function () {
                                    var preparedCanvas = prepareLazadaOcrCanvas(canvas);
                                    var topCanvas = cropLazadaCanvas(preparedCanvas, 0.06, 0.33);
                                    var middleCanvas = cropLazadaCanvas(preparedCanvas, 0.30, 0.66);
                                    var invoiceDetailsCanvas = cropLazadaCanvasRect(preparedCanvas, 0.38, 0.09, 0.76, 0.30);
                                    var invoiceValueStackCanvas = cropLazadaCanvasRect(preparedCanvas, 0.48, 0.12, 0.72, 0.27);

                                    var tasks = [
                                        recognizeLazadaCanvas(invoiceDetailsCanvas, '6', { extractCustomerStack: true }),
                                        recognizeLazadaCanvas(invoiceDetailsCanvas, '11', { extractCustomerStack: true }),
                                        recognizeLazadaCanvas(invoiceValueStackCanvas, '6', { extractCustomerStack: true }),
                                        recognizeLazadaCanvas(invoiceValueStackCanvas, '11', { extractCustomerStack: true }),
                                        recognizeLazadaCanvas(topCanvas, '6', { extractCustomerStack: true }),
                                        recognizeLazadaCanvas(topCanvas, '4', { extractCustomerStack: true }),
                                        recognizeLazadaCanvas(middleCanvas, '6'),
                                        recognizeLazadaCanvas(middleCanvas, '4'),
                                        recognizeLazadaCanvas(preparedCanvas, '6')
                                    ];

                                    return Promise.all(tasks).then(function (parts) {
                                        pageTexts.push(parts.filter(function (part) {
                                            return String(part || '').trim() !== '';
                                        }).join('\n'));
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
            return extractPdfTextViaTextLayer(file).then(function (textLayerText) {
                textLayerText = String(textLayerText || '').trim();

                if (typeof Tesseract === 'undefined') {
                    return textLayerText;
                }

                setStatus('Reading visual PDF text with OCR...', false);
                setSubmittingState(true, 'Running OCR...');

                return extractPdfTextViaOcr(file).then(function (ocrText) {
                    ocrText = String(ocrText || '').trim();

                    var textParts = [];
                    if (textLayerText !== '') {
                        textParts.push(textLayerText);
                    }
                    if (ocrText !== '') {
                        textParts.push(ocrText);
                    }

                    return textParts.join('\n').trim();
                });
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
