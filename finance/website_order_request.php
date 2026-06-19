<?php
$currentPagePin = 92;
$pageTitle = "Website Order Request";

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = WEB_ORDER_REQ;

$orderDeleteApprovalModuleKey = 'website_order_request';
$orderDeleteApprovalState = orderDeleteApprovalInitPageState();
$orderDeleteApprovalMode = !empty($orderDeleteApprovalState['approval_mode']);
$orderDeleteApprovalRequestId = isset($orderDeleteApprovalState['request_id']) ? (int) $orderDeleteApprovalState['request_id'] : 0;
$dataId = isset($orderDeleteApprovalState['data_id']) ? $orderDeleteApprovalState['data_id'] : '';
$act = isset($orderDeleteApprovalState['act']) ? $orderDeleteApprovalState['act'] : '';
$orderDeleteApprovalPanelHtml = isset($orderDeleteApprovalState['panel_html']) ? (string) $orderDeleteApprovalState['panel_html'] : '';
$pageAction = getPageAction($act);


$redirectPage = $SITEURL . '/finance/website_order_request_table.php';
$back_redirect_page = commonResolveBackUrl($redirectPage);
$websiteCurrentRequestPath = isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$websiteBackRedirectPath = (string) parse_url((string) $back_redirect_page, PHP_URL_PATH);
if ($websiteBackRedirectPath === '' || ($websiteCurrentRequestPath !== '' && $websiteBackRedirectPath === $websiteCurrentRequestPath)) {
    $back_redirect_page = $redirectPage;
}
$redirectLink = '<script>location.href=' . json_encode($redirectPage) . ';</script>';
$clearLocalStorage = '<script>localStorage.clear();</script>';
$pendingStatusUpdate = shopeeOmsNormalizeStatusCode(post('updateStatusBtn'));
$worShouldSaveBeforeStatusUpdate = $pendingStatusUpdate !== '' && $act === 'E';
$worTriggerStatusTransitionAfterSave = false;
$worHandleStatusTransition = function ($newStatus) use ($connect, $finance_connect, $dataId, $pageTitle, $cdate, $ctime, $tblName, $redirectPage) {
    $newStatus = shopeeOmsNormalizeStatusCode($newStatus);
    $transitionRemark = 'Order Status Update to ' . shopeeOmsGetStatusLabel($newStatus);
    $transitionResult = shopeeOmsExecuteTransition($connect, $finance_connect, (int) $dataId, $newStatus, array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => $transitionRemark,
        'platform' => 'website',
    ));

    if (!empty($transitionResult['success'])) {
        $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
        $newStatusCode = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : '';
        audit_log(array(
            'log_act' => 'edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'OMS transition ' . $oldStatus . ' -> ' . $newStatusCode,
            'query_table' => $tblName,
            'page' => $pageTitle,
            'connect' => $connect,
            'oldval' => 'order_status: ' . $oldStatus,
            'changes' => 'order_status: ' . $newStatusCode,
            'act_msg' => USER_NAME . " updated Website order #" . (int) $dataId . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
        ));
        echo '<script>alert(' . json_encode((string) $transitionRemark) . '); window.location.replace(' . json_encode((string) $redirectPage) . ');</script>';
        exit;
    }
    return array(
        'success' => false,
        'message' => (string) (isset($transitionResult['message']) ? $transitionResult['message'] : 'Unable to update order status.'),
    );
};
generateDBData(WEB_CUST_RCD, $connect);
$worStatusOptions = shopeeOmsGetEditableStatusOptions();
$worWarehouseRows = shopeeOmsLoadActiveWarehouses($connect);
$worDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect, $worWarehouseRows);
$worWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect, true);
$worWarehouseOptionMap = array();
foreach ($worWarehouseRows as $worWarehouseRow) {
    $worWarehouseId = isset($worWarehouseRow['id']) ? (int) $worWarehouseRow['id'] : 0;
    if ($worWarehouseId > 0) {
        $worWarehouseOptionMap[$worWarehouseId] = isset($worWarehouseRow['name']) ? (string) $worWarehouseRow['name'] : ('Warehouse #' . $worWarehouseId);
    }
}
$worPopupErrorMessage = '';

// to display data to input
if ($dataId) { //edit/remove/view
    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $finance_connect);

    if ($result != false && $result->num_rows > 0) {
        $dataExisted = 1;
        $row = $result->fetch_assoc();
    } else {
        // If $result is false or no data found ($act==null)
        $errorExist = 1;
        $_SESSION['tempValConfirmBox'] = true;
        $act = "F";
    }
}

if ($pendingStatusUpdate !== '' && !$worShouldSaveBeforeStatusUpdate) {
    $worTransitionResult = $worHandleStatusTransition($pendingStatusUpdate);
    if (is_array($worTransitionResult) && empty($worTransitionResult['success'])) {
        $transitionErrorState = shopeeOmsResolveStatusTransitionErrorState(
            $pendingStatusUpdate,
            isset($worTransitionResult['message']) ? $worTransitionResult['message'] : '',
            'Unable to update order status.'
        );
        if ($transitionErrorState['stock_out_warehouse_err'] !== '') {
            $stock_out_warehouse_err = $transitionErrorState['stock_out_warehouse_err'];
        }
        $worPopupErrorMessage = $transitionErrorState['popup_error_message'];
    }
}

if (!($dataId) && !($act)) {
    renderNotificationScript('Invalid action.', 'error', $redirectPage);
    exit;
}

$worExecuteDeleteOrder = orderDeleteApprovalBuildStandardDeleteCallback(array(
    'data_connect' => $finance_connect,
    'audit_connect' => $connect,
    'table_name' => $tblName,
    'page_title' => $pageTitle,
    'fallback_data_id' => (int) $dataId,
    'label_field' => 'order_id',
));

$orderDeleteApprovalPanelHtml = orderDeleteApprovalHandlePageFlow(array(
    'connect' => $connect,
    'request_id' => $orderDeleteApprovalRequestId,
    'module_key' => $orderDeleteApprovalModuleKey,
    'data_id' => (int) $dataId,
    'current_user_id' => (int) USER_ID,
    'page_title' => $pageTitle,
    'redirect_page' => $redirectPage,
    'clear_local_storage' => $clearLocalStorage,
    'approval_mode' => $orderDeleteApprovalMode,
    'delete_callback' => $worExecuteDeleteOrder,
));

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $customer_id = postSpaceFilter('customer_id');
    $customer_name = postSpaceFilter('customer_name');
    $customer_email = postSpaceFilter('customer_email');
    $customer_birthday = postSpaceFilter('customer_birthday');
    $brand = postSpaceFilter('brand_hidden');
    $series = postSpaceFilter('series_hidden');
    $shipping_name = postSpaceFilter('shipping_name');
    $shipping_address = postSpaceFilter('shipping_address');
    $shipping_contact = postSpaceFilter('shipping_contact');

    $requiredNewCustomerFields = array(
        $customer_id,
        $customer_name,
        $customer_email,
        $customer_birthday,
        $brand,
        $series,
        $shipping_name,
        $shipping_address,
        $shipping_contact,
    );
    $hasMissingNewCustomerField = false;
    foreach ($requiredNewCustomerFields as $requiredValue) {
        if (trim((string) $requiredValue) === '') {
            $hasMissingNewCustomerField = true;
            break;
        }
    }

    if ($hasMissingNewCustomerField) {
        echo "<script>alert('Please fill in all required fields for the new customer record.');</script>";
    } else {
        $query = "INSERT INTO " . WEB_CUST_RCD . " (cust_id,name,cust_email,cust_birthday,brand,series,ship_rec_name,ship_rec_add,ship_rec_contact,create_by,create_date,create_time) VALUES ('$customer_id','$customer_name','$customer_email','$customer_birthday','$brand','$series','$shipping_name','$shipping_address','$shipping_contact','" . USER_ID . "',curdate(),curtime())";
        
        $submit_result = mysqli_query($connect, $query);
        if (!$submit_result) {
            echo "<script>alert('Error: " . addslashes(mysqli_error($connect)) . "');</script>";
        } else {
            generateDBData(WEB_CUST_RCD, $connect);
            echo "<script>alert('New Customer Successfully Created!');</script>";
        }
    }
}

if (post('actionBtn') || $worShouldSaveBeforeStatusUpdate) {
    $action = post('actionBtn');
    if ($action === '' && $worShouldSaveBeforeStatusUpdate) {
        $action = 'updRecord';
    }

    $wor_order_id = postSpaceFilter('wor_order_id');
    $wor_brand = postSpaceFilter('wor_brand');
    $wor_series = postSpaceFilter('wor_series');
    $wor_pkg = postSpaceFilter('wor_pkg_hidden');
    $wor_country = postSpaceFilter('wor_country_hidden');
    $wor_currency = postSpaceFilter('wor_currency_hidden');
    $wor_price = postSpaceFilter('wor_price');
    $wor_shipping = postSpaceFilter('wor_shipping');
    $wor_discount = postSpaceFilter('wor_discount');
    $wor_total = postSpaceFilter('wor_total');
    $wor_pay = postSpaceFilter('wor_pay_hidden');
    $wor_pic = postSpaceFilter('wor_pic');
    $wor_cust_id = postSpaceFilter('wor_cust_id_hidden');
    $wor_customer_id = postSpaceFilter('wor_customer_id');
    $wor_cust_brand = postSpaceFilter('wor_cust_brand_hidden');
    $wor_cust_series = postSpaceFilter('wor_cust_series_hidden');
    $wor_cust_ship_name = postSpaceFilter('wor_cust_ship_name');
    $wor_cust_ship_address = postSpaceFilter('wor_cust_ship_address');
    $wor_cust_ship_contact = postSpaceFilter('wor_cust_ship_contact');
    $wor_cust_name = postSpaceFilter('wor_cust_name');
    $wor_cust_email = postSpaceFilter('wor_cust_email');
    $wor_cust_birthday = postSpaceFilter('wor_cust_birthday');
    $wor_shipping_name = postSpaceFilter('wor_shipping_name');
    $wor_shipping_address = postSpaceFilter('wor_shipping_address');
    $wor_shipping_contact = postSpaceFilter('wor_shipping_contact');
    $wor_remark = postSpaceFilter('wor_remark');
    $wor_order_status = shopeeOmsNormalizeStatusCode(postSpaceFilter('wor_order_status'));
    if ($wor_order_status === '') {
        $wor_order_status = isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P';
    }
    $worCurrentEffectiveWarehouseId = isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $worDefaultWarehouseId) : $worDefaultWarehouseId;
    $wor_stock_out_warehouse_id = shopeeOmsNormalizeWarehouseId(postSpaceFilter('wor_stock_out_warehouse_id'));
    if ($wor_stock_out_warehouse_id <= 0) {
        $wor_stock_out_warehouse_id = $worDefaultWarehouseId;
    }
    $worStockOutWarehouseEditable = $action === 'addRecord'
        ? true
        : shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : '');
    if (!$worStockOutWarehouseEditable && $action === 'updRecord') {
        $wor_stock_out_warehouse_id = $worCurrentEffectiveWarehouseId;
    }
    $wor_update_airbill = strtolower(trim((string) postSpaceFilter('wor_update_airbill')));
    if ($wor_update_airbill === '') {
        $wor_update_airbill = 'yes';
    }
    $wor_airbill_no = postSpaceFilter('wor_airbill_no');
    $wor_airbill_attachment = postSpaceFilter('wor_airbill_attachment_value');

    $wor_pkg_text = postSpaceFilter('wor_pkg');
    $wor_country_text = postSpaceFilter('wor_country');
    $wor_currency_text = postSpaceFilter('wor_currency');
    $wor_pay_text = postSpaceFilter('wor_pay');
    $wor_cust_id_text = postSpaceFilter('wor_cust_id');

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':

            $error = 0;

            if ($wor_update_airbill === 'yes' && isset($_FILES['wor_airbill_attachment']) && isset($_FILES['wor_airbill_attachment']['size']) && (int) $_FILES['wor_airbill_attachment']['size'] > 0) {
                $worAirbillUploadResult = shopeeOmsStoreAirbillAttachmentUpload(
                    $_FILES['wor_airbill_attachment'],
                    $connect,
                    $wor_brand,
                    $wor_pkg,
                    'website_order_request'
                );
                if (!empty($worAirbillUploadResult['success'])) {
                    $wor_airbill_attachment = isset($worAirbillUploadResult['path']) ? (string) $worAirbillUploadResult['path'] : '';
                } else {
                    $airbill_attachment_err = isset($worAirbillUploadResult['message']) ? (string) $worAirbillUploadResult['message'] : 'Failed to upload the airbill attachment.';
                    $error = 1;
                }
            }

            if ($wor_update_airbill !== 'yes') {
                if ($action === 'updRecord') {
                    $wor_airbill_no = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                    $wor_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                } else {
                    $wor_airbill_no = '';
                    $wor_airbill_attachment = '';
                }
            }

            if (!$wor_order_id) {
                $order_id_err = "Order ID is required!";
                $error = 1;
            }
            if (!$wor_brand) {
                $brand_err = "Brand is required!";
                $error = 1;
            }
            if (!$wor_series) {
                $series_err = "Series is required!";
                $error = 1;
            }
            if ($wor_pkg_text === '') {
                $wor_pkg = '';
            }
            if (empty($wor_pkg) || $wor_pkg < 1) {
                $pkg_err = "Package is required!";
                $error = 1;
            }
            if ($wor_country_text === '') {
                $wor_country = '';
            }
            if (empty($wor_country) || $wor_country < 1) {
                $country_err = "Country is required!";
                $error = 1;
            }
            if ($wor_currency_text === '') {
                $wor_currency = '';
            }
            if (empty($wor_currency) || $wor_currency < 1) {
                $currency_err = "Currency is required!";
                $error = 1;
            }
            if ($wor_price == '' && $wor_price !== '0') {
                $price_err = "Price is required!";
                $error = 1;
            }
            if ($wor_shipping == '' && $wor_shipping !== '0') {
                $shipping_err = "Shipping is required!";
                $error = 1;
            }
            if ($wor_discount == '' && $wor_discount !== '0') {
                $discount_err = "Discount Price is required!";
                $error = 1;
            }
            if ($wor_total == '' && $wor_total !== '0') {
                $total_err = "Total is required!";
                $error = 1;
            }
            if ($wor_pay_text === '') {
                $wor_pay = '';
            }
            if (!$wor_pay) {
                $pay_err = "Payment Method is required!";
                $error = 1;
            }
            if (!$wor_pic) {
                $pic_err = "Person In Charge is required!";
                $error = 1;
            }
            if ($wor_cust_id_text === '') {
                $wor_cust_id = '';
            }
            if (!$wor_cust_id) {
                $cust_id_err = "Customer ID is required!";
                $error = 1;
            }
            if (!$wor_cust_name) {
                $cust_name_err = "Customer Name is required!";
                $error = 1;
            }
            if (!$wor_cust_email) {
                $cust_email_err = "Customer Email is required!";
                $error = 1;
            }
            if (!$wor_cust_birthday) {
                $cust_birthday_err = "Customer Birthday is required!";
                $error = 1;
            }
            if (!$wor_shipping_name) {
                $shipping_name_err = "Shipping Name is required!";
                $error = 1;
            }
            if (!$wor_shipping_address) {
                $shipping_address_err = "Shipping Address is required!";
                $error = 1;
            }
            if (!$wor_shipping_contact) {
                $shipping_contact_err = "Shipping Contact is required!";
                $error = 1;
            }
            if ($worStockOutWarehouseEditable) {
                if ($wor_stock_out_warehouse_id <= 0) {
                    $stock_out_warehouse_err = "Stock Out Warehouse is required!";
                    $error = 1;
                } else if (!isset($worWarehouseOptionMap[$wor_stock_out_warehouse_id])) {
                    $stock_out_warehouse_err = "Please select a valid active Stock Out Warehouse.";
                    $error = 1;
                }
            }

            $worEffectiveAirbill = $wor_airbill_no;
            if ($action === 'updRecord' && $wor_update_airbill !== 'yes') {
                $worEffectiveAirbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
            }

            $worStatusValidation = shopeeOmsValidateInitialStatusAndAirbill($wor_order_status, $worEffectiveAirbill);
            if (!$worStatusValidation['valid']) {
                $airbill_err = isset($worStatusValidation['message']) ? (string) $worStatusValidation['message'] : 'Invalid order status or airbill.';
                $error = 1;
            }

            if ($wor_update_airbill === 'yes') {
                if (trim((string) $wor_airbill_no) === '') {
                    $airbill_err = 'Airbill No cannot be empty when Update Airbill is enabled.';
                    $error = 1;
                }
                if (trim((string) $wor_airbill_attachment) === '') {
                    $airbill_attachment_err = 'Airbill Attachment cannot be empty when Update Airbill is enabled.';
                    $error = 1;
                }
            }

            if ($error) {
                break;
            }

            if ($action === 'addRecord' && $wor_order_status === 'TP') {
                $worWarehouseStockValidation = shopeeOmsValidateWarehouseStockForOrder($connect, $finance_connect, array(
                    'pkg' => $wor_pkg,
                    'stock_out_warehouse_id' => $wor_stock_out_warehouse_id,
                ), array(
                    'platform' => 'website',
                ));
                if (empty($worWarehouseStockValidation['success'])) {
                    $stock_out_warehouse_err = isset($worWarehouseStockValidation['message']) ? (string) $worWarehouseStockValidation['message'] : 'Selected warehouse does not have enough stock.';
                    $error = 1;
                    break;
                }
            }

            if ($action == 'addRecord') {
                try {
                    //check values

                    if ($wor_order_id) {
                        array_push($newvalarr, $wor_order_id);
                        array_push($datafield, 'order_id');
                    }

                    if ($wor_brand) {
                        array_push($newvalarr, $wor_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($wor_series) {
                        array_push($newvalarr, $wor_series);
                        array_push($datafield, 'series');
                    }

                    if ($wor_pkg) {
                        array_push($newvalarr, $wor_pkg);
                        array_push($datafield, 'pkg');
                    }

                    if ($wor_country) {
                        array_push($newvalarr, $wor_country);
                        array_push($datafield, 'country');
                    }

                    if ($wor_currency) {
                        array_push($newvalarr, $wor_currency);
                        array_push($datafield, 'currency');
                    }
                  
                    if ($wor_price) {
                        array_push($newvalarr, $wor_price);
                        array_push($datafield, 'price');
                    }

                    if ($wor_shipping) {
                        array_push($newvalarr, $wor_shipping);
                        array_push($datafield, 'shipping');
                    }

                    if ($wor_discount) {
                        array_push($newvalarr, $wor_discount);
                        array_push($datafield, 'discount');
                    }

                    if ($wor_total) {
                        array_push($newvalarr, $wor_total);
                        array_push($datafield, 'total');
                    }

                    if ($wor_pay) {
                        array_push($newvalarr, $wor_pay);
                        array_push($datafield, 'pay_method');
                    }

                    if ($wor_pic) {
                        array_push($newvalarr, $wor_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($wor_cust_id) {
                        array_push($newvalarr, $wor_cust_id);
                        array_push($datafield, 'cust_id');
                    }

                    if ($wor_cust_name) {
                        array_push($newvalarr, $wor_cust_name);
                        array_push($datafield, 'cust_name');
                    }

                    if ($wor_cust_email) {
                        array_push($newvalarr, $wor_cust_email);
                        array_push($datafield, 'cust_email');
                    }

                    if ($wor_cust_birthday) {
                        array_push($newvalarr, $wor_cust_birthday);
                        array_push($datafield, 'cust_birthday');
                    }

                    if ($wor_shipping_name) {
                        array_push($newvalarr, $wor_shipping_name);
                        array_push($datafield, 'shipping_name');
                    }

                    if ($wor_shipping_address) {
                        array_push($newvalarr, $wor_shipping_address);
                        array_push($datafield, 'shipping_address');
                    }

                    if ($wor_shipping_contact) {
                        array_push($newvalarr, $wor_shipping_contact);
                        array_push($datafield, 'shipping_contact');
                    }

                    if ($wor_remark) {
                        array_push($newvalarr, $wor_remark);
                        array_push($datafield, 'remark');
                    }
                    if ($wor_order_status) {
                        array_push($newvalarr, $wor_order_status);
                        array_push($datafield, 'order_status');
                    }
                    if ($wor_stock_out_warehouse_id > 0) {
                        array_push($newvalarr, isset($worWarehouseOptionMap[$wor_stock_out_warehouse_id]) ? $worWarehouseOptionMap[$wor_stock_out_warehouse_id] : ('Warehouse #' . $wor_stock_out_warehouse_id));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }
                    if ($wor_airbill_no !== '') {
                        array_push($newvalarr, $wor_airbill_no);
                        array_push($datafield, 'airbill_no');
                    }
                    if ($wor_airbill_attachment !== '') {
                        array_push($newvalarr, $wor_airbill_attachment);
                        array_push($datafield, 'airbill_attachment');
                    }

                    $query = "INSERT INTO " . $tblName . " (order_id,brand,series,pkg,country,currency,price,shipping,discount,total,pay_method,pic,cust_id,cust_name,cust_email,cust_birthday,shipping_name,shipping_address,shipping_contact,remark,order_status,stock_out_warehouse_id,airbill_no,airbill_attachment,create_by,create_date,create_time) VALUES ('$wor_order_id','$wor_brand','$wor_series','$wor_pkg','$wor_country','$wor_currency','$wor_price','$wor_shipping','$wor_discount','$wor_total','$wor_pay','$wor_pic','$wor_cust_id','$wor_cust_name','$wor_cust_email','$wor_cust_birthday','$wor_shipping_name','$wor_shipping_address','$wor_shipping_contact','$wor_remark','$wor_order_status'," . ($wor_stock_out_warehouse_id > 0 ? $wor_stock_out_warehouse_id : 'NULL') . ",'$wor_airbill_no','" . mysqli_real_escape_string($finance_connect, $wor_airbill_attachment) . "','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($finance_connect, $query);
                    if (!$returnData) { throw new Exception(mysqli_error($finance_connect)); }
                    $dataId = $finance_connect->insert_id;
                    if ($wor_order_status === 'TP' && $dataId > 0) {
                        $freshOrderRow = shopeeOmsLoadOrder($finance_connect, $dataId, 'website');
                        $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $freshOrderRow, USER_ID, 'website');
                        if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                            $notifyResult = shopeeOmsSendWarehouseNotification($connect, $finance_connect, $tokenResult['token_row'], $tokenResult['notification'], $pageTitle);
                            if (!empty($notifyResult['sent']) && shopeeOmsTableHasColumn($finance_connect, dbFinance, $tblName, 'step_a_sent_at')) {
                                mysqli_query($finance_connect, "UPDATE `" . $tblName . "` SET `step_a_sent_at` = NOW() WHERE id = " . $dataId . " LIMIT 1");
                            }
                        }
                    }
                    // Automatically save new customer to CMS DB if selected
                    if ($wor_cust_id == 'Create New Customer ID') {
                        $tblName2 = WEB_CUST_RCD;
                        $query2 = "INSERT INTO " . $tblName2 . " (name, cust_email, cust_birthday, sales_pic, country, brand, series, ship_rec_name, ship_rec_add, ship_rec_contact, remark, create_by, create_date, create_time) VALUES ('$wor_cust_name', '$wor_cust_email', '$wor_cust_birthday', '$wor_pic', '$wor_country', '$wor_brand', '$wor_series', '$wor_shipping_name', '$wor_shipping_address', '$wor_shipping_contact', '$wor_remark', '" . USER_ID . "', curdate(), curtime())";
                        mysqli_query($connect, $query2);
                        generateDBData(WEB_CUST_RCD, $connect);
                    }

                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {

                    // take old value
                    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $result->fetch_assoc();

                    // check value
                    if ($row['order_id'] != $wor_order_id) {
                        array_push($oldvalarr, $row['order_id']);
                        array_push($chgvalarr, $wor_order_id);
                        array_push($datafield, 'order_id');
                    }

                    if ($row['brand'] != $wor_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $wor_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['series'] != $wor_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $wor_series);
                        array_push($datafield, 'series');
                    }

                    if ($row['pkg'] != $wor_pkg) {
                        array_push($oldvalarr, $row['pkg']);
                        array_push($chgvalarr, $wor_pkg);
                        array_push($datafield, 'pkg');
                    }

                    if ($row['country'] != $wor_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $wor_country);
                        array_push($datafield, 'country');
                    }

                    if ($row['currency'] != $wor_currency) {
                        array_push($oldvalarr, $row['currency']);
                        array_push($chgvalarr, $wor_currency);
                        array_push($datafield, 'currency');
                    }

                    if ($row['price'] != $wor_price) {
                        array_push($oldvalarr, $row['price']);
                        array_push($chgvalarr, $wor_price);
                        array_push($datafield, 'price');
                    }

                    if ($row['shipping'] != $wor_shipping) {
                        array_push($oldvalarr, $row['shipping']);
                        array_push($chgvalarr, $wor_shipping);
                        array_push($datafield, 'shipping');
                    }

                    if ($row['discount'] != $wor_discount) {
                        array_push($oldvalarr, $row['discount']);
                        array_push($chgvalarr, $wor_discount);
                        array_push($datafield, 'discount');
                    }

                    if ($row['total'] != $wor_total) {
                        array_push($oldvalarr, $row['total']);
                        array_push($chgvalarr, $wor_total);
                        array_push($datafield, 'total');
                    }

                    if ($row['pay_method'] != $wor_pay) {
                        array_push($oldvalarr, $row['pay_method']);
                        array_push($chgvalarr, $wor_pay);
                        array_push($datafield, 'pay_method');
                    }

                    if ($row['pic'] != $wor_pic) {
                        array_push($oldvalarr, $row['pic']);
                        array_push($chgvalarr, $wor_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['cust_id'] != $wor_cust_id) {
                        array_push($oldvalarr, $row['cust_id']);
                        array_push($chgvalarr, $wor_cust_id);
                        array_push($datafield, 'cust_id');
                    }

                    if ($row['cust_name'] != $wor_cust_name) {
                        array_push($oldvalarr, $row['cust_name']);
                        array_push($chgvalarr, $wor_cust_name);
                        array_push($datafield, 'cust_name');
                    }
                    
                    if ($row['cust_email'] != $wor_cust_email) {
                        array_push($oldvalarr, $row['cust_email']);
                        array_push($chgvalarr, $wor_cust_email);
                        array_push($datafield, 'cust_email');
                    }

                    if ($row['cust_birthday'] != $wor_cust_birthday) {
                        array_push($oldvalarr, $row['cust_birthday']);
                        array_push($chgvalarr, $wor_cust_birthday);
                        array_push($datafield, 'cust_birthday');
                    }

                    if ($row['shipping_name'] != $wor_shipping_name) {
                        array_push($oldvalarr, $row['shipping_name']);
                        array_push($chgvalarr, $wor_shipping_name);
                        array_push($datafield, 'shipping_name');
                    }
                
                    if ($row['shipping_address'] != $wor_shipping_address) {
                    array_push($oldvalarr, $row['shipping_address']);
                    array_push($chgvalarr, $wor_shipping_address);
                    array_push($datafield, 'shipping_address');
                    }

                    if ($row['shipping_contact'] != $wor_shipping_contact) {
                        array_push($oldvalarr, $row['shipping_contact']);
                        array_push($chgvalarr, $wor_shipping_contact);
                        array_push($datafield, 'shipping_contact');
                        }

                    if ($row['remark'] != $wor_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $wor_remark == '' ? 'Empty Value' : $wor_remark);
                        array_push($datafield, 'remark');
                    }
                    if (shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '') !== $wor_order_status) {
                        array_push($oldvalarr, isset($row['order_status']) && $row['order_status'] !== '' ? shopeeOmsGetStatusLabel($row['order_status']) : 'Empty Value');
                        array_push($chgvalarr, $wor_order_status !== '' ? shopeeOmsGetStatusLabel($wor_order_status) : 'Empty Value');
                        array_push($datafield, 'order_status');
                    }
                    if ((int) (isset($row['stock_out_warehouse_id']) ? $row['stock_out_warehouse_id'] : 0) !== (int) $wor_stock_out_warehouse_id) {
                        array_push($oldvalarr, shopeeOmsResolveWarehouseNameById($connect, isset($row['stock_out_warehouse_id']) ? $row['stock_out_warehouse_id'] : 0, $worDefaultWarehouseId, $worWarehouseNameMap));
                        array_push($chgvalarr, shopeeOmsResolveWarehouseNameById($connect, $wor_stock_out_warehouse_id, $worDefaultWarehouseId, $worWarehouseNameMap));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }
                    if ((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '') !== (string) $wor_airbill_no) {
                        array_push($oldvalarr, isset($row['airbill_no']) && $row['airbill_no'] !== '' ? $row['airbill_no'] : 'Empty Value');
                        array_push($chgvalarr, $wor_airbill_no !== '' ? $wor_airbill_no : 'Empty Value');
                        array_push($datafield, 'airbill_no');
                    }
                    if ((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '') !== (string) $wor_airbill_attachment) {
                        array_push($oldvalarr, isset($row['airbill_attachment']) && $row['airbill_attachment'] !== '' ? $row['airbill_attachment'] : 'Empty Value');
                        array_push($chgvalarr, $wor_airbill_attachment !== '' ? $wor_airbill_attachment : 'Empty Value');
                        array_push($datafield, 'airbill_attachment');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET order_id = '$wor_order_id', brand = '$wor_brand', series = '$wor_series', pkg = '$wor_pkg', country = '$wor_country', currency = '$wor_currency', price = '$wor_price', shipping = '$wor_shipping', discount = '$wor_discount', total = '$wor_total', pay_method = '$wor_pay', pic = '$wor_pic', cust_id = '$wor_cust_id', cust_name = '$wor_cust_name', cust_email = '$wor_cust_email', cust_birthday = '$wor_cust_birthday', shipping_name = '$wor_shipping_name', shipping_address = '$wor_shipping_address', shipping_contact = '$wor_shipping_contact', remark ='$wor_remark', order_status = '$wor_order_status', stock_out_warehouse_id = " . ($wor_stock_out_warehouse_id > 0 ? $wor_stock_out_warehouse_id : 'NULL') . ", airbill_no = '$wor_airbill_no', airbill_attachment = '" . mysqli_real_escape_string($finance_connect, $wor_airbill_attachment) . "', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
                        $returnData = mysqli_query($finance_connect, $query);

                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            if ($action === 'updRecord' && $worShouldSaveBeforeStatusUpdate && (($act === 'NC') || !empty($returnData))) {
                $worTriggerStatusTransitionAfterSave = true;
            }

            // audit log
            if (isset($query)) {

                $log = [
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }

            if ($action === 'updRecord' && $worShouldSaveBeforeStatusUpdate) {
                if ($worTriggerStatusTransitionAfterSave) {
                    unset($_SESSION['tempValConfirmBox']);
                    $worTransitionResult = $worHandleStatusTransition($pendingStatusUpdate);
                    if (is_array($worTransitionResult) && empty($worTransitionResult['success'])) {
                        $transitionErrorState = shopeeOmsResolveStatusTransitionErrorState(
                            $pendingStatusUpdate,
                            isset($worTransitionResult['message']) ? $worTransitionResult['message'] : '',
                            'Unable to update order status.'
                        );
                        if ($act === 'NC') {
                            $act = 'E';
                        }
                        if ($transitionErrorState['stock_out_warehouse_err'] !== '') {
                            $stock_out_warehouse_err = $transitionErrorState['stock_out_warehouse_err'];
                        }
                        $worPopupErrorMessage = $transitionErrorState['popup_error_message'];
                        break;
                    }
                }

                $worSaveErrorMessage = trim((string) $errorMsg) !== '' ? trim((string) $errorMsg) : 'Unable to save edited order details.';
                echo '<script>alert(' . json_encode($worSaveErrorMessage) . ');</script>';
                exit;
            }

            break;
    }
}


if (post('act') == 'D') {
    $id = post('id');
    if ($id) {
        try {
            $result = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $finance_connect);
            if (!$result || $result->num_rows === 0) {
                renderNotificationScript('Order record was not found.', 'error', $redirectPage, 1200, true);
                exit;
            }

            $row = $result->fetch_assoc();
            $dataId = (int) $row['id'];
            $deleteLabel = isset($row['order_id']) ? trim((string) $row['order_id']) : '';
            if ($deleteLabel === '') {
                $deleteLabel = 'Order #' . $dataId;
            }

            $deleteApprovalResult = orderDeleteApprovalRequestDelete($connect, $orderDeleteApprovalModuleKey, $dataId, $deleteLabel, $pageTitle);
            if (!empty($deleteApprovalResult['direct_delete'])) {
                $deleteResult = $worExecuteDeleteOrder(array(
                    'source_order_id' => $dataId,
                    'source_order_label' => $deleteLabel,
                ));
                renderNotificationScript(
                    $deleteResult['message'],
                    !empty($deleteResult['success']) ? 'success' : 'error',
                    $redirectPage,
                    1200,
                    true
                );
                exit;
            }

            renderNotificationScript(
                $deleteApprovalResult['message'],
                isset($deleteApprovalResult['notification_type']) ? $deleteApprovalResult['notification_type'] : (!empty($deleteApprovalResult['success']) ? 'success' : 'error'),
                $redirectPage,
                1200,
                true
            );
            exit;
        } catch (Exception $e) {
            renderNotificationScript($e->getMessage(), 'error', $redirectPage, 1200, true);
            exit;
        }
    }
}

//view
if (($dataId) && !($act) && (USER_ID != '') && empty($_SESSION['viewChk']) && empty($_SESSION['delChk'])) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . $row['order_id'] . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

$urbanismBadgeSeedName = '';
if (isset($row['cust_name']) && trim((string) $row['cust_name']) !== '') {
    $urbanismBadgeSeedName = trim((string) $row['cust_name']);
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('wor_cust_name') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('wor_cust_name'));
}

$urbanismBadgeAction = getUrbanismMemberActionData(
    $connect,
    '',
    $urbanismBadgeSeedName,
    $redirectPage,
    $pageTitle
);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <script src="header/js/pdf.min.js"></script>
    <script src="../js/pdf_airbill_parser.js"></script>
    <style>
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
            font-size: 14px;
        }

        .shopee-inline-invalid {
            border-color: #dc3545 !important;
            box-shadow: none !important;
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
    </style>

</head>

<body>
<form id="myForm" method="POST" action="">
        <input type="hidden" name="act" value="<?php echo $act; ?>">
        <input type="hidden" name="id" value="<?php echo $dataId; ?>">
    </form>
    <div class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= htmlspecialchars((string) $back_redirect_page, ENT_QUOTES, 'UTF-8') ?>">
                <?= $pageTitle ?>
            </a> <i class="fa-solid fa-chevron-right fa-xs"></i>
            <?php
            echo displayPageAction($act, $pageTitle);
            ?>
        </p>

    </div>

    <div id="formContainer" class="container d-flex justify-content-center">
        <div class="col-6 col-md-6 formWidthAdjust">
            <form id="FORForm" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars((string) $back_redirect_page, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group mb-5">
                    <div class="order-title-row">
                        <h2 class="mb-0"><?php echo displayPageAction($act, $pageTitle); ?></h2>
                    </div>
                    <div class="order-badge-row text-end mt-2">
                        <a
                            class="btn btn-sm <?= $urbanismBadgeAction['is_member'] ? 'btn-success' : 'btn-outline-secondary' ?> <?= $urbanismBadgeAction['disabled'] ? 'disabled' : '' ?>"
                            href="<?= htmlspecialchars($urbanismBadgeAction['url'], ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars($urbanismBadgeAction['title'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $urbanismBadgeAction['disabled'] ? 'onclick="return false;" aria-disabled="true"' : '' ?>><i class="fa-solid fa-id-badge"></i></a>
                    </div>
                </div>

                <div id="err_msg" class="mb-3">
                    <span class="mt-n2" style="font-size: 21px;">
                        <?php if (isset($err1))
                            echo $err1; ?>
                    </span>
                </div>

                <?php echo $orderDeleteApprovalPanelHtml; ?>

                <div class="form-group">
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label form_lbl" id="wor_order_id_lbl" for="wor_order_id">Order ID<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="wor_order_id" id="wor_order_id" value="<?php
            if (isset($dataExisted) && isset($row['order_id']) && !isset($wor_order_id)) {
                echo $row['order_id'];
            } else if (isset($wor_order_id)) {
                echo $wor_order_id;
            }
            ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($order_id_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $order_id_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-4 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_brand_lbl" for="wor_brand">Brand<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="wor_brand" id="wor_brand" value="<?php
            if (isset($dataExisted) && isset($row['brand']) && !isset($wor_brand)) {
                echo $row['brand'];
            } else if (isset($wor_brand)) {
                echo $wor_brand;
            }
            ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($brand_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $brand_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-4 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_series_lbl" for="wor_series">Series<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="wor_series" id="wor_series" value="<?php
            if (isset($dataExisted) && isset($row['series']) && !isset($wor_series)) {
                echo $row['series'];
            } else if (isset($wor_series)) {
                echo $wor_series;
            }
            ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($series_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $series_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>


                        <div class="form-group">
    <div class="row">
        <div class="col-md-4 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_pkg_lbl" for="wor_pkg">Package<span class="requireRed">*</span></label>
            <?php
            unset($echoVal);

            if (isset($row['pkg']))
                $echoVal = $row['pkg'];

            if (isset($echoVal)) {
                $pkg_rst = getData('name', "id = '$echoVal'", '', PKG, $connect);
                if (!$pkg_rst) {
                    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $pkg_row = $pkg_rst->fetch_assoc();
            }
            ?>
            <input class="form-control" type="text" name="wor_pkg" id="wor_pkg" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $pkg_row['name'] : '' ?>">
            <input type="hidden" name="wor_pkg_hidden" id="wor_pkg_hidden" value="<?php echo (isset($row['pkg'])) ? $row['pkg'] : ''; ?>">
            <?php if (isset($pkg_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $pkg_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-4 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_country_lbl" for="wor_country">Country<span class="requireRed">*</span></label>
            <?php
            unset($echoVal);

            if (isset($row['country']))
                $echoVal = $row['country'];

            if (isset($echoVal)) {
                $country_rst = getData('nicename', "id = '$echoVal'", '', COUNTRIES, $connect);
                if (!$country_rst) {
                    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $country_row = $country_rst->fetch_assoc();
            }
            ?>
            <input class="form-control" type="text" name="wor_country" id="wor_country" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $country_row['nicename'] : '' ?>">
            <input type="hidden" name="wor_country_hidden" id="wor_country_hidden" value="<?php echo (isset($row['country'])) ? $row['country'] : ''; ?>">
            <?php if (isset($country_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $country_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-4 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_currency_lbl" for="wor_currency">Currency<span class="requireRed">*</span></label>
            <?php
            unset($echoVal);

            if (isset($row['currency']))
                $echoVal = $row['currency'];

            if (isset($echoVal)) {
                $currency_rst = getData('unit', "id = '$echoVal'", '', CUR_UNIT, $connect);
                if (!$currency_rst) {
                    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $currency_row = $currency_rst->fetch_assoc();
            }
            ?>
            <input class="form-control" type="text" name="wor_currency" id="wor_currency" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $currency_row['unit'] : '' ?>">
            <input type="hidden" name="wor_currency_hidden" id="wor_currency_hidden" value="<?php echo (isset($row['currency'])) ? $row['currency'] : ''; ?>">
            <?php if (isset($currency_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $currency_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>


                           
    <div class="form-group">
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label form_lbl" id="wor_price_lbl" for="wor_price">Price<span class="requireRed">*</span></label>
            <input class="form-control" type="number" name="wor_price" id="wor_price" value="<?php if (isset($dataExisted) && isset($row['price']) && !isset($wor_price)) { echo $row['price']; } else if (isset($wor_price)) { echo $wor_price; } ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($price_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $price_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label form_lbl" id="wor_shipping_lbl" for="wor_shipping">Shipping<span class="requireRed">*</span></label>
            <input class="form-control" type="number" name="wor_shipping" id="wor_shipping" value="<?php if (isset($dataExisted) && isset($row['shipping']) && !isset($wor_shipping)) { echo $row['shipping']; } else if (isset($wor_shipping)) { echo $wor_shipping; } ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($shipping_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $shipping_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label form_lbl" id="wor_discount_lbl" for="wor_discount">Discount Price<span class="requireRed">*</span></label>
            <input class="form-control" type="number" name="wor_discount" id="wor_discount" value="<?php if (isset($dataExisted) && isset($row['discount']) && !isset($wor_discount)) { echo $row['discount']; } else if (isset($wor_discount)) { echo $wor_discount; } ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($discount_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $discount_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-3 mb-3">
            <label class="form-label form_lbl" id="wor_total_lbl" for="wor_total">Total<span class="requireRed">*</span></label>
            <input class="form-control" type="number" name="wor_total" id="wor_total" value="<?php if (isset($dataExisted) && isset($row['total']) && !isset($wor_total)) { echo $row['total']; } else if (isset($wor_total)) { echo $wor_total; } ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($total_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $total_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>


<div class="form-group">
    <div class="row">
        <div class="col-md-6 mb-3 autocomplete">
        <label class="form-label form_lbl" id="wor_pay_lbl" for="wor_pay">Payment Method<span class="requireRed">*</span></label>
            <?php
            unset($echoVal);

            if (isset($row['pay_method']))
                $echoVal = $row['pay_method'];

            if (isset($echoVal)) {
                $pay_rst = getData('name', "id = '$echoVal'", '', FIN_PAY_METH, $finance_connect);
                if (!$pay_rst) {
                    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $pay_row = $pay_rst->fetch_assoc();
            }
            ?>
            <input class="form-control" type="text" name="wor_pay" id="wor_pay" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $pay_row['name'] : '' ?>">
            <input type="hidden" name="wor_pay_hidden" id="wor_pay_hidden" value="<?php echo (isset($row['pay_method'])) ? $row['pay_method'] : ''; ?>">
            <?php if (isset($pay_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $pay_err; ?></span>
                </div>
            <?php } ?>
        </div>

                    

        <div class="col-md-6 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_pic_lbl" for="wor_pic">Person In Charge<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="wor_pic" id="wor_pic" value="<?php if (isset($dataExisted) && isset($row['pic']) && !isset($wor_pic)) { echo $row['pic']; } else if (isset($wor_pic)) { echo $wor_pic; } ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($pic_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $pic_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="form-group mb-3">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label form_lbl" for="wor_order_status">Initial Order Status<span class="requireRed">*</span></label>
                <?php
                $worCurrentOrderStatusValue = isset($wor_order_status) && trim((string) $wor_order_status) !== ''
                    ? $wor_order_status
                    : (isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P');
                ?>
                <?php if ($act === 'I') { ?>
                    <select class="form-select" id="wor_order_status" name="wor_order_status">
                        <?php foreach ($worStatusOptions as $statusCode => $statusLabel) { ?>
                            <option value="<?= htmlspecialchars($statusCode) ?>" <?= $worCurrentOrderStatusValue === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                        <?php } ?>
                    </select>
                <?php } else { ?>
                    <input class="form-control" type="text" value="<?= htmlspecialchars(shopeeOmsGetStatusLabel($worCurrentOrderStatusValue)) ?>" readonly>
                    <input type="hidden" id="wor_order_status" name="wor_order_status" value="<?= htmlspecialchars($worCurrentOrderStatusValue) ?>">
                <?php } ?>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label form_lbl" for="wor_stock_out_warehouse_id">Stock Out Warehouse<span class="requireRed">*</span></label>
                <?php
                $worCurrentStockOutWarehouseId = isset($wor_stock_out_warehouse_id) && (int) $wor_stock_out_warehouse_id > 0
                    ? (int) $wor_stock_out_warehouse_id
                    : (isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $worDefaultWarehouseId) : $worDefaultWarehouseId);
                if ($worCurrentStockOutWarehouseId <= 0 && !empty($worWarehouseRows)) {
                    $worCurrentStockOutWarehouseId = (int) $worWarehouseRows[0]['id'];
                }
                $worCurrentStockOutWarehouseName = shopeeOmsResolveWarehouseNameById($connect, $worCurrentStockOutWarehouseId, $worDefaultWarehouseId, $worWarehouseNameMap);
                $worIsStockOutWarehouseEditableForForm = $act !== '' && ($act === 'I' || shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : ''));
                ?>
                <?php if ($worIsStockOutWarehouseEditableForForm) { ?>
                    <select class="form-select" id="wor_stock_out_warehouse_id" name="wor_stock_out_warehouse_id">
                        <?php foreach ($worWarehouseRows as $worWarehouseRow) { ?>
                            <?php $worWarehouseId = isset($worWarehouseRow['id']) ? (int) $worWarehouseRow['id'] : 0; ?>
                            <option value="<?= $worWarehouseId ?>" <?= $worCurrentStockOutWarehouseId === $worWarehouseId ? 'selected' : '' ?>><?= htmlspecialchars((string) $worWarehouseRow['name']) ?></option>
                        <?php } ?>
                    </select>
                <?php } else { ?>
                    <input class="form-control" type="text" value="<?= htmlspecialchars($worCurrentStockOutWarehouseName) ?>" readonly>
                    <input type="hidden" id="wor_stock_out_warehouse_id" name="wor_stock_out_warehouse_id" value="<?= (int) $worCurrentStockOutWarehouseId ?>">
                <?php } ?>
                <?php if (isset($stock_out_warehouse_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1"><?php echo $stock_out_warehouse_err; ?></span>
                    </div>
                <?php } ?>
            </div>

            <div class="col-md-2 mb-3 shopee-airbill-toggle-col">
                <?php
                $worHasSavedAirbillData = false;
                if (isset($row['airbill_no']) && trim((string) $row['airbill_no']) !== '') {
                    $worHasSavedAirbillData = true;
                }
                if (isset($row['airbill_attachment']) && trim((string) $row['airbill_attachment']) !== '') {
                    $worHasSavedAirbillData = true;
                }
                $worUpdateAirbillValue = isset($wor_update_airbill) && trim((string) $wor_update_airbill) !== ''
                    ? strtolower(trim((string) $wor_update_airbill))
                    : ($worHasSavedAirbillData ? 'yes' : ($act === 'I' ? 'yes' : 'no'));
                if ($worUpdateAirbillValue !== 'yes' && $worHasSavedAirbillData) {
                    $worUpdateAirbillValue = 'yes';
                } else if ($worUpdateAirbillValue !== 'yes') {
                    $worUpdateAirbillValue = 'no';
                }
                ?>
                <input type="hidden" id="wor_update_airbill" name="wor_update_airbill" value="<?= htmlspecialchars($worUpdateAirbillValue) ?>">
                <label class="form-label form_lbl shopee-airbill-toggle-label" for="wor_update_airbill_toggle">Update Airbill?</label>
                <div class="shopee-airbill-toggle-field">
                    <label class="shopee-airbill-toggle mb-0" for="wor_update_airbill_toggle">
                        <input type="checkbox" id="wor_update_airbill_toggle" <?= $worUpdateAirbillValue === 'yes' ? 'checked' : '' ?> <?= $act == '' ? 'disabled' : '' ?>>
                        <span class="shopee-airbill-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <label class="form-label form_lbl" for="wor_airbill_no">Airbill No</label>
                <input class="form-control" type="text" name="wor_airbill_no" id="wor_airbill_no" value="<?php
                if (isset($wor_airbill_no)) {
                    echo htmlspecialchars($wor_airbill_no);
                } else if (isset($row['airbill_no'])) {
                    echo htmlspecialchars((string) $row['airbill_no']);
                }
                ?>" <?php if ($act == '') echo 'disabled' ?>>
                <?php if (isset($airbill_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1"><?php echo $airbill_err; ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label form_lbl" for="wor_airbill_attachment">Airbill Attachment</label>
                <input class="form-control" type="file" name="wor_airbill_attachment" id="wor_airbill_attachment" <?php if ($act == '') echo 'disabled' ?>>
                <small id="wor_airbill_extract_status" class="shopee-airbill-extract-status"></small>
                <?php
                $worCurrentAirbillAttachmentValue = isset($wor_airbill_attachment) && trim((string) $wor_airbill_attachment) !== ''
                    ? trim((string) $wor_airbill_attachment)
                    : (isset($row['airbill_attachment']) ? trim((string) $row['airbill_attachment']) : '');
                $worCurrentAirbillAttachmentUrl = $worCurrentAirbillAttachmentValue !== '' ? shopeeOmsBuildAirbillAttachmentUrl($worCurrentAirbillAttachmentValue) : '';
                $worCurrentAirbillAttachmentExt = $worCurrentAirbillAttachmentUrl !== ''
                    ? strtolower(pathinfo((string) parse_url($worCurrentAirbillAttachmentUrl, PHP_URL_PATH), PATHINFO_EXTENSION))
                    : '';
                ?>
                <?php if ($worCurrentAirbillAttachmentValue !== '') { ?>
                    <div class="mt-2 small">
                        Current Attachment:
                        <?php if ($worCurrentAirbillAttachmentUrl !== '') { ?>
                            <a href="<?= htmlspecialchars($worCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= htmlspecialchars($worCurrentAirbillAttachmentValue) ?></a>
                        <?php } else { ?>
                            <span><?= htmlspecialchars($worCurrentAirbillAttachmentValue) ?></span>
                        <?php } ?>
                    </div>
                <?php } ?>
                <input type="hidden" name="wor_airbill_attachment_value" id="wor_airbill_attachment_value" value="<?= htmlspecialchars($worCurrentAirbillAttachmentValue, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (isset($airbill_attachment_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1"><?php echo $airbill_attachment_err; ?></span>
                    </div>
                <?php } ?>
            </div>
            <div class="col-md-6 mb-3 d-flex justify-content-center justify-content-md-end">
                <?php if ($worCurrentAirbillAttachmentUrl !== '') { ?>
                    <div id="wor_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media">
                        <?php if (in_array($worCurrentAirbillAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                            <img src="<?= htmlspecialchars($worCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Airbill Attachment Preview">
                        <?php } else if ($worCurrentAirbillAttachmentExt === 'pdf') { ?>
                            <iframe src="<?= htmlspecialchars($worCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" title="Airbill Attachment Preview"></iframe>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div id="wor_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media" style="display:none;"></div>
                <?php } ?>
            </div>
        </div>
</div>

<fieldset class="border p-2 mb-3" style="border-radius: 3px;">
    <legend class="float-none w-auto p-2">Customer Info</legend>
    <div class="form-group">
    <div class="row">
        <div class="col-md-6 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wor_cust_id_lbl" for="wor_cust_id">Customer ID<span class="requireRed">*</span></label>
            <?php
            unset($echoVal);

            if (isset($row['cust_id']))
                $echoVal = $row['cust_id'];

            if (isset($echoVal)) {
                $cust_id_rst = getData('cust_id', "id = '$echoVal'", '', WEB_CUST_RCD, $connect);
                if (!$cust_id_rst) {
                    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $cust_id_row = $cust_id_rst->fetch_assoc();
            }
            ?>
            <input class="form-control" type="text" name="wor_cust_id" id="wor_cust_id" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $cust_id_row['cust_id'] : '' ?>">
            <input type="hidden" name="wor_cust_id_hidden" id="wor_cust_id_hidden" value="<?php echo (isset($row['cust_id'])) ? $row['cust_id'] : ''; ?>">
            <?php if (isset($cust_id_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $cust_id_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label form_lbl" id="wor_cust_name_lbl" for="wor_cust_name">Customer Name<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="wor_cust_name" id="wor_cust_name" value="<?php
            if (isset($dataExisted) && isset($row['cust_name']) && !isset($wor_cust_name)) {
                echo $row['cust_name'];
            } else if (isset($wor_cust_name)) {
                echo $wor_cust_name;
            }
            ?>" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($cust_name_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1">
                        <?php echo $cust_name_err; ?>
                    </span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>


        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label form_lbl" id="wor_cust_email_lbl" for="wor_cust_email">Customer Email<span class="requireRed">*</span></label>
                <input class="form-control" type="text" name="wor_cust_email" id="wor_cust_email" value="<?php
                if (isset($dataExisted) && isset($row['cust_email']) && !isset($wor_cust_email)) {
                    echo $row['cust_email'];
                } else if (isset($wor_cust_email)) {
                    echo $wor_cust_email;
                }
                ?>" <?php if ($act == '') echo 'disabled' ?>>
                <?php if (isset($cust_email_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1">
                            <?php echo $cust_email_err; ?>
                        </span>
                    </div>
                <?php } ?>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label class="form-label form_lbl" id="wor_cust_birthday_label" for="wor_cust_birthday">Customer Birthday<span class="requireRed">*</span></label>
                    <input class="form-control" type="date" name="wor_cust_birthday" id="wor_cust_birthday" value="<?php
                        if (isset($dataExisted) && isset($row['cust_birthday']) && !isset($wor_cust_birthday)) {
                            echo $row['cust_birthday'];
                        } else if (isset($wor_cust_birthday)) {
                            echo $wor_cust_birthday;
                        } else {
                            echo date('Y-m-d');
                        }
                    ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" <?php if ($act == '') echo 'disabled' ?>>
                    <?php if (isset($cust_birthday_err)) { ?>
                        <div id="err_msg">
                            <span class="mt-n1"><?php echo $cust_birthday_err; ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        
        <?php if ($act != ''){ ?>
        <div class="col-md-4 mb-3">
            <button type="button" onclick="toggleNewCustomerSection()">Create New Customer ID</button>
        </div>
        
        <div id="new_customer_section" style="display: none;">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="customer_id">Customer ID</label>
                    <input class="form-control" type="text" id="customer_id" name="customer_id" data-new-customer-required="1" form="myForm">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="customer_name">Customer Name</label>
                    <input class="form-control" type="text" id="customer_name" name="customer_name" data-new-customer-required="1" form="myForm">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="customer_email">Customer Email</label>
                    <input class="form-control" type="email" id="customer_email" name="customer_email" data-new-customer-required="1" form="myForm">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="customer_birthday">Customer Birthday</label>
                    <input class="form-control" type="date" id="customer_birthday" name="customer_birthday" data-new-customer-required="1" form="myForm">
                </div>

                <div class="col-md-4 mb-3 autocomplete">
                    <label class="form-label form_lbl" id="brand_lbl" for="brand">Brand<span class="requireRed">*</span></label>
                    <input class="form-control" type="text" name="brand" id="brand" <?php if ($act == '') echo 'disabled' ?> value="" data-new-customer-required="1" form="myForm">
                    <input type="hidden" name="brand_hidden" id="brand_hidden" value="" form="myForm">
                </div>

                <div class="col-md-4 mb-3 autocomplete">
                    <label class="form-label form_lbl" id="series_lbl" for="series">Series<span class="requireRed">*</span></label>
                    <input class="form-control" type="text" name="series" id="series" <?php if ($act == '') echo 'disabled' ?> value="" data-new-customer-required="1" form="myForm">
                    <input type="hidden" name="series_hidden" id="series_hidden" value="" form="myForm">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="shipping_name">Shipping Name</label>
                    <input class="form-control" type="text" id="shipping_name" name="shipping_name" data-new-customer-required="1" form="myForm">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="shipping_address">Shipping Address</label>
                    <input class="form-control" type="text" id="shipping_address" name="shipping_address" data-new-customer-required="1" form="myForm">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label form_lbl" for="shipping_contact">Shipping Contact</label>
                    <input class="form-control" type="number" id="shipping_contact" name="shipping_contact" data-new-customer-required="1" form="myForm">
                </div>
            </div>
            
            <button type="button" id="website_new_customer_submit_btn">Submit</button>
        </div>
        <?php }?>
</fieldset>

<fieldset class="border p-2 mb-3" style="border-radius: 3px;">
    <legend class="float-none w-auto p-2">Shipping Address</legend>
    <div class="form-group">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label form_lbl" id="wor_shipping_name_lbl" for="wor_shipping_name">Shipping Name<span class="requireRed">*</span></label>
                <input class="form-control" type="text" name="wor_shipping_name" id="wor_shipping_name" value="<?php
                if (isset($dataExisted) && isset($row['shipping_name']) && !isset($wor_shipping_name)) {
                    echo $row['shipping_name'];
                } else if (isset($wor_shipping_name)) {
                    echo $wor_shipping_name;
                }
                ?>" <?php if ($act == '') echo 'disabled' ?>>
                <?php if (isset($shipping_name_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1">
                            <?php echo $shipping_name_err; ?>
                        </span>
                    </div>
                <?php } ?>
            </div>

            <div class="col-md-6 mb-3">
                <label class="form-label form_lbl" id="wor_shipping_address_lbl" for="wor_shipping_address">Shipping Address<span class="requireRed">*</span></label>
                <input class="form-control" type="text" name="wor_shipping_address" id="wor_shipping_address" value="<?php
                if (isset($dataExisted) && isset($row['shipping_address']) && !isset($wor_shipping_address)) {
                    echo $row['shipping_address'];
                } else if (isset($wor_shipping_address)) {
                    echo $wor_shipping_address;
                }
                ?>" <?php if ($act == '') echo 'disabled' ?>>
                <?php if (isset($shipping_address_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1">
                            <?php echo $shipping_address_err; ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label form_lbl" id="wor_shipping_contact_lbl" for="wor_shipping_contact">Shipping Contact<span class="requireRed">*</span></label>
                <input class="form-control" type="number" name="wor_shipping_contact" id="wor_shipping_contact" value="<?php
                if (isset($dataExisted) && isset($row['shipping_contact']) && !isset($wor_shipping_contact)) {
                    echo $row['shipping_contact'];
                } else if (isset($wor_shipping_contact)) {
                    echo $wor_shipping_contact;
                }
                ?>" <?php if ($act == '') echo 'disabled' ?>>
                <?php if (isset($shipping_contact_err)) { ?>
                    <div id="err_msg">
                        <span class="mt-n1">
                            <?php echo $shipping_contact_err; ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</fieldset>

                <div class="form-group mb-3">
                    <label class="form-label form_lbl" id="wor_remark_lbl" for="wor_remark">Remark</label>
                    <textarea class="form-control" name="wor_remark" id="wor_remark" rows="3" <?php if ($act == '')
                        echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                        echo $row['remark'] ?></textarea>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    </div>
                
                <?php
                 if(isset($row['order_status'])){
                if($row['order_status'] == 'SP'){
                ?>
                <div class="form-group mb-4">
                    <h3>
                        Tracking Details
                    </h3>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_courier_lbl" for="sor_courier">Courier</label>
                            <?php
                           
                            if (isset($row['order_id']))
                            $echoVal = $row['order_id'];
                            $courier_rst2 = getData('courier_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);

                            if (!$courier_rst2) {
                                echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                                echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                            }
                            $courier_row2 = $courier_rst2->fetch_assoc();
                            if ($courier_row2['courier_id'])
                            $echoVal2 = $courier_row2['courier_id'];
                       
                            $courier_rst = getData('name', "id = '$echoVal2'", '', COURIER, $connect);
                            $courier_row = $courier_rst->fetch_assoc();
                      
                            if (isset($courier_row['name'])) {
                                $courier_name = $courier_row['name'];
                            } else {
                                $courier_name = '';
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_courier" id="sor_courier" value="<?php echo !empty($echoVal2) ? $courier_name : ''; ?>" disabled ?>

                            <?php if (isset($courier_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $courier_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_track_lbl" for="sor_track">Tracking Number</label>
                            
                            <?php
                             $tracking_rst = getData('tracking_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);
                             if (!$tracking_rst) {
                                echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                                echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                            }
                            $tracking_row = $tracking_rst->fetch_assoc();
                            if (isset($tracking_row['tracking_id'])) {
                                $tracking_id = $tracking_row['tracking_id'];
                            } else {
                                $tracking_id = '';
                            }
                             ?>
                             <input class="form-control" type="text"  name="sor_track" id="sor_track" value="<?php echo !empty($echoVal) ? $tracking_id : ''; ?>" disabled ?>
                            <?php if (isset($tracking_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $tracking_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-4 d-flex align-items-end">
                            <label>&nbsp;</label><br>
                            <?php
                   
                            $tracking_rst2 = getData('tracking_link', "id = '$echoVal2'", '', COURIER, $connect);
                            if (!$tracking_rst2) {
                                echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                                echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                            }
                            $track_row = $tracking_rst2->fetch_assoc();
                      
                            if (isset($track_row['tracking_link'])) {
                                $tracking_link = $track_row['tracking_link'];
                                
                            } else {
                                $tracking_link = '';
                            }
                            ?>
                            
                            <a href="<?php echo $tracking_link; ?>" id="trackOrderBtn" class="track-order-btn" data-tracking-id="<?php echo $tracking_id; ?>" target="_blank">Track Order</a>
                            
                        </div>
                    </div>
                </div>
                <?php }} ?>
                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                    <?php
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    if ($act === 'E' && isset($row['order_status'])) {
                        $statusCode = shopeeOmsNormalizeStatusCode($row['order_status']);
                        $canMoveToPack = shopeeOmsHasTransitionPermission($connect, $statusCode, 'TP', USER_GROUP, $row, USER_ID);
                        if ($statusCode === 'P' && $canMoveToPack) {
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="TP" formnovalidate>MOVE TO TO PACK</button>';
                        }
                    }
                    ?>
                    <button type="button" class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="backBtn" id="backBtn"
                        onclick="location.href = <?= htmlspecialchars(json_encode($back_redirect_page), ENT_QUOTES, 'UTF-8') ?>;">Back</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    /*
        oufei 20231014
        common.fun.js
        function(title, subtitle, page name, ajax url path, redirect path, action)
        to show action dialog after finish certain action (eg. edit)
    */
    if (isset($_SESSION['tempValConfirmBox'])) {
        unset($_SESSION['tempValConfirmBox']);
        echo $clearLocalStorage;
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
    }
    if ($worPopupErrorMessage !== '') {
        echo '<script>document.addEventListener("DOMContentLoaded", function () { confirmationDialog("", ' . json_encode($worPopupErrorMessage) . ', ' . json_encode((string) $pageTitle) . ', "", "", "ErrMO"); });</script>';
    }
    ?>
    <script>
        <?php echo shopeeOmsRenderAirbillAttachmentPreviewScript(); ?>

        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        <?php include "../js/website_order_request.js" ?>

        document.addEventListener('DOMContentLoaded', function () {
            function toggleWebsiteAirbillFields() {
                var updateAirbill = document.getElementById('wor_update_airbill');
                var updateAirbillToggle = document.getElementById('wor_update_airbill_toggle');
                var airbillNo = document.getElementById('wor_airbill_no');
                var airbillAttachment = document.getElementById('wor_airbill_attachment');
                var existingAttachment = document.getElementById('wor_airbill_attachment_value');
                if (!updateAirbill || !updateAirbillToggle || !airbillNo || !airbillAttachment) {
                    return;
                }

                updateAirbill.value = updateAirbillToggle.checked ? 'yes' : 'no';
                var enabled = updateAirbillToggle.checked;
                var readOnlyMode = "<?= $act ?>" === '';
                airbillNo.disabled = readOnlyMode || !enabled;
                airbillAttachment.disabled = readOnlyMode || !enabled;
                airbillNo.required = enabled;
                airbillAttachment.required = enabled && (!existingAttachment || existingAttachment.value.trim() === '');
            }

            if (window.shopeeOmsAirbillAttachmentPreview) {
                window.shopeeOmsAirbillAttachmentPreview.bind({
                    fileInputSelector: '#wor_airbill_attachment',
                    previewWrapSelector: '#wor_airbill_attachment_preview_wrap'
                });
            }

            if (window.shopeeOmsAirbillPdfAutofill) {
                window.shopeeOmsAirbillPdfAutofill.bind({
                    fileInputSelector: '#wor_airbill_attachment',
                    airbillNoSelector: '#wor_airbill_no',
                    customerAddressSelector: '#wor_shipping_address',
                    statusSelector: '#wor_airbill_extract_status',
                    workerSrc: 'header/js/pdf.worker.min.js',
                    errorClass: 'is-error'
                });
            }

            toggleWebsiteAirbillFields();

            var websiteUpdateAirbillToggle = document.getElementById('wor_update_airbill_toggle');
            if (websiteUpdateAirbillToggle) {
                websiteUpdateAirbillToggle.addEventListener('change', toggleWebsiteAirbillFields);
            }

            var websiteNewCustomerForm = document.getElementById('myForm');
            var websiteNewCustomerSection = document.getElementById('new_customer_section');
            var websiteNewCustomerSubmitBtn = document.getElementById('website_new_customer_submit_btn');
            var websiteNewCustomerFields = websiteNewCustomerSection
                ? websiteNewCustomerSection.querySelectorAll('[data-new-customer-required="1"]')
                : [];
            var websiteNewCustomerLookupFields = [
                { textId: 'brand', hiddenId: 'brand_hidden', label: 'Brand' },
                { textId: 'series', hiddenId: 'series_hidden', label: 'Series' }
            ];

            function validateWebsiteNewCustomerForm() {
                var hasError = false;

                websiteNewCustomerFields.forEach(function (field) {
                    clearNewCustomerInlineError(field);
                    if (String(field.value || '').trim() === '') {
                        showNewCustomerInlineError(field, 'This field is required.');
                        hasError = true;
                    }
                });

                websiteNewCustomerLookupFields.forEach(function (config) {
                    var textField = document.getElementById(config.textId);
                    var hiddenField = document.getElementById(config.hiddenId);
                    if (textField && hiddenField && String(textField.value || '').trim() !== '' && String(hiddenField.value || '').trim() === '') {
                        showNewCustomerInlineError(textField, 'Please select a valid ' + config.label + ' from the list.');
                        hasError = true;
                    }
                });

                return !hasError;
            }

            websiteNewCustomerFields.forEach(function (field) {
                field.addEventListener('input', function () {
                    clearNewCustomerInlineError(field);
                });
                field.addEventListener('change', function () {
                    clearNewCustomerInlineError(field);
                });
            });

            websiteNewCustomerLookupFields.forEach(function (config) {
                var textField = document.getElementById(config.textId);
                var hiddenField = document.getElementById(config.hiddenId);
                if (!textField || !hiddenField) {
                    return;
                }
                textField.addEventListener('input', function () {
                    hiddenField.value = '';
                    clearNewCustomerInlineError(textField);
                });
                hiddenField.addEventListener('change', function () {
                    clearNewCustomerInlineError(textField);
                });
            });

            if (websiteNewCustomerSubmitBtn && websiteNewCustomerForm) {
                websiteNewCustomerSubmitBtn.addEventListener('click', function () {
                    if (!validateWebsiteNewCustomerForm()) {
                        return;
                    }

                    var existingSubmitFlag = websiteNewCustomerForm.querySelector('input[data-new-customer-submit="1"]');
                    if (existingSubmitFlag) {
                        existingSubmitFlag.remove();
                    }

                    var submitFlag = document.createElement('input');
                    submitFlag.type = 'hidden';
                    submitFlag.name = 'submit';
                    submitFlag.value = 'Submit';
                    submitFlag.setAttribute('data-new-customer-submit', '1');
                    websiteNewCustomerForm.appendChild(submitFlag);

                    HTMLFormElement.prototype.submit.call(websiteNewCustomerForm);
                });
            }
        });

        // FIX: Add missing frontend validation for Shipping, Discount, and Total
        $(document).ready(function() {
            $('.submitBtn').on('click', function(e) {
                var extraFields = ['wor_shipping', 'wor_discount', 'wor_total'];
                var formHasError = false;

                extraFields.forEach(function(field) {
                    var inputVal = $('#' + field).val();
                    
                    // If the field is empty, trigger the error
                    if (inputVal === '') {
                        // Check if error msg already exists to avoid duplicates
                        if ($('#' + field).siblings('#err_msg').length === 0) {
                            var labelText = $('#' + field + '_lbl').text().replace('*', '').trim();
                            $('#' + field).after('<div id="err_msg"><span class="mt-n1">' + labelText + ' is required!</span></div>');
                        }
                        formHasError = true;
                    } else {
                        // Remove error if the user filled it in
                        $('#' + field).siblings('#err_msg').remove();
                    }
                });

                // Stop the form from submitting if these fields are empty
                if (formHasError) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>

</html>
