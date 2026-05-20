<?php
$currentPagePin = 0;
$pageTitle = "Shopee Order Request";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$tblName = SHOPEE_SG_ORDER_REQ;

$dataID = input('id');
$act = input('act');
$pageAction = getPageAction($act);
$allowed_ext = array("png", "jpg", "jpeg", "pdf");

// Redirect directly to role page to avoid extra router history entries.
$redirect_page = $SITEURL . '/shopee/shopee_processing_order.php';
if (in_array('130', GlobalPin)) {
    $redirect_page = $SITEURL . '/shopee/shopee_order_req_table.php';
} else if (in_array('129', GlobalPin)) {
    $redirect_page = $SITEURL . '/shopee/shopee_verify.php';
}
$redirectLink = ("<script>location.replace('$redirect_page');</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';
$sorStatusOptions = function_exists('shopeeOmsGetEditableStatusOptions') ? shopeeOmsGetEditableStatusOptions() : array('P' => 'To Ship', 'TP' => 'To Pack', 'SP' => 'Shipped', 'WAERD' => 'Waiting Assign Estimate Received Date');
$sorAirbillAttachmentPath = img_server . 'shopee_airbill_attachment/';
$sorAirbillAttachmentUrl = rtrim((string) $SITEURL, '/') . '/' . trim((string) $sorAirbillAttachmentPath, '/\\') . '/';
$sorLocalTelegramFailureMessage = '';
$sorIsLiveSite = isset($siteOrlocalMode) ? (bool) $siteOrlocalMode : true;
$sorBuildLocalTelegramFailureMessage = function ($notifyResult) use ($sorIsLiveSite) {
    if ($sorIsLiveSite || !is_array($notifyResult) || !empty($notifyResult['sent'])) {
        return '';
    }

    $reason = trim((string) (isset($notifyResult['message']) ? $notifyResult['message'] : ''));
    if ($reason === '') {
        $reason = 'Unknown Telegram send failure.';
    }

    return "Telegram message failed to send.\nReason: " . $reason;
};

// to display data to input
if ($dataID) { //edit/remove/view
    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $finance_connect);

    if ($rst != false && $rst->num_rows > 0) {
        $dataExisted = 1;
        $row = $rst->fetch_assoc();
    } else {
        // If $rst is false or no data found ($act==null)
        $errorExist = 1;
        $_SESSION['tempValConfirmBox'] = true;
        $act = "F";
    }
}

$sorBuyerSegmentationBadgeHtml = '';
if (isset($row['buyer']) && trim((string) $row['buyer']) !== '') {
    $sorBuyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $finance_connect, $row['buyer']);
    if (isset($sorBuyerMeta['label_meta']['segmentation'])) {
        $sorBuyerSegmentationBadgeHtml = customerLabelRenderBadge($sorBuyerMeta['label_meta']['segmentation']);
    }
}

if (!($dataID) && !($act)) {
    echo '<script>
    alert("Invalid action.");
    window.location.replace("' . $redirect_page . '");
    </script>';
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $scr_username = trim((string) $_POST['scr_username']);
    $scr_pic_name = trim((string) $_POST['scr_pic']);
    $scr_country_name = trim((string) $_POST['scr_country']);
    $scr_brand_name = trim((string) $_POST['scr_brand']);
    $scr_series_name = trim((string) $_POST['scr_series']);
    $scr_resolve_lookup_id = function ($rawId, $displayValue, $tableName, $columnName) use ($connect) {
        $rawId = trim((string) $rawId);
        if ($rawId !== '' && ctype_digit($rawId) && (int) $rawId > 0) {
            return $rawId;
        }

        $displayValue = trim((string) $displayValue);
        if ($displayValue === '') {
            return '';
        }

        $safeDisplayValue = mysqli_real_escape_string($connect, $displayValue);
        $lookupRst = getData('id', $columnName . " = '$safeDisplayValue'", 'LIMIT 1', $tableName, $connect);
        if ($lookupRst && $lookupRst->num_rows > 0) {
            $lookupRow = $lookupRst->fetch_assoc();
            return isset($lookupRow['id']) ? trim((string) $lookupRow['id']) : '';
        }

        return '';
    };
    $scr_pic = $scr_resolve_lookup_id(isset($_POST['scr_pic_hidden']) ? $_POST['scr_pic_hidden'] : '', $scr_pic_name, USR_USER, 'name');
    $scr_country = $scr_resolve_lookup_id(isset($_POST['scr_country_hidden']) ? $_POST['scr_country_hidden'] : '', $scr_country_name, COUNTRIES, 'nicename');
    $scr_brand = $scr_resolve_lookup_id(isset($_POST['scr_brand_hidden']) ? $_POST['scr_brand_hidden'] : '', $scr_brand_name, BRAND, 'name');
    $scr_series = $scr_resolve_lookup_id(isset($_POST['scr_series_hidden']) ? $_POST['scr_series_hidden'] : '', $scr_series_name, BRD_SERIES, 'name');
    $duplicate_check_query = "SELECT * FROM shopee_customer_info WHERE buyer_username = '$scr_username'";
    $duplicate_result = mysqli_query($finance_connect, $duplicate_check_query);
    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
    $newCustomerRequiredFields = array(
        'Shopee Buyer Username' => $scr_username,
        'Sales Person In Charge' => $scr_pic,
        'Country' => $scr_country,
        'Brand' => $scr_brand,
        'Series' => $scr_series,
    );

    if (!in_array('', $newCustomerRequiredFields, true)) {
        if (mysqli_num_rows($duplicate_result) > 0) {
            echo "<script>alert('Error: Duplicate Customer ID found!');</script>";
        } else {
           $insert_query = "INSERT INTO ".SHOPEE_CUST_INFO." (buyer_username, pic, country, brand, series,create_by,create_date,create_time) 
                             VALUES ('$scr_username', '$scr_pic', '$scr_country', '$scr_brand', '$scr_series','" . USER_ID . "',curdate(),curtime())";
            $insertCustomerId = 0;

            if (mysqli_query($finance_connect, $insert_query)) {
                $insertCustomerId = (int) mysqli_insert_id($finance_connect);
                echo "<script>alert('New record created successfully');</script>";
                generateDBData(SHOPEE_CUST_INFO, $finance_connect);
            } else {
                echo "<script>alert('Error: " . $insert_query . "<br>" . mysqli_error($connect) . "');</script>";
            }
            
           
            //check values
            if ($scr_username) {
                array_push($newvalarr, $scr_username);
                array_push($datafield, 'Shopee Buyer Username');
            }
            if ($scr_pic) {
                array_push($newvalarr, $scr_pic);
                array_push($datafield, 'Sales Person In Charge');
            }
            if ($scr_country) {
                array_push($newvalarr, $scr_country);
                array_push($datafield, 'Country');
            }
            if ($scr_brand) {
                array_push($newvalarr, $scr_brand);
                array_push($datafield, 'Brand');
            }
            if ($scr_series) {
                array_push($newvalarr, $scr_series);
                array_push($datafield, 'Series');
            }

             if (isset($insert_query)) {
                $log = [
                    'log_act' => 'add',
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $insert_query,
                    'query_table' => SHOPEE_CUST_INFO,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                $log['newval'] = implodeWithComma($newvalarr);
                $log['act_msg'] = actMsgLog($insertCustomerId, $datafield, $newvalarr, '', '', SHOPEE_CUST_INFO, 'add', ($insertCustomerId > 0 ? '' : 'Failed to create Shopee customer record.'));
                
                audit_log($log);
             }
        }
    } else {
        echo "<script>alert('Please fill in all required fields for the new customer record.');</script>";
    }
}

if (post('updateStatusBtn')) {
    $newStatus = post('updateStatusBtn');
    $transitionResult = shopeeOmsExecuteTransition($connect, $finance_connect, (int) $dataID, $newStatus, array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => '',
    ));

    if (!empty($transitionResult['success'])) {
        if (isset($transitionResult['new_status']) && (string) $transitionResult['new_status'] === 'TP') {
            $notifyResult = isset($transitionResult['step_a_result']['notify_result']) && is_array($transitionResult['step_a_result']['notify_result'])
                ? $transitionResult['step_a_result']['notify_result']
                : array();
            $localTelegramFailureMessage = $sorBuildLocalTelegramFailureMessage($notifyResult);
            if ($localTelegramFailureMessage !== '') {
                $transitionResult['message'] .= "\n\n" . $localTelegramFailureMessage;
            }
        }

        $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
        $newStatusCode = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : '';
        $queryStatusUpdate = "OMS transition " . $oldStatus . " -> " . $newStatusCode;
        $log = [
            'log_act'      => 'edit',
            'cdate'        => $cdate,
            'ctime'        => $ctime,
            'uid'          => USER_ID,
            'cby'          => USER_ID,
            'query_rec'    => $queryStatusUpdate,
            'query_table'  => $tblName,
            'page'         => $pageTitle,
            'connect'      => $connect,
            'oldval'       => 'order_status: ' . $oldStatus,
            'changes'      => 'order_status: ' . $newStatusCode,
            'act_msg'      => USER_NAME . " updated Shopee order #" . (int) $dataID . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
        ];
        audit_log($log);
        echo '<script>alert("' . addslashes($transitionResult['message']) . '"); window.location.replace("' . $redirect_page . '");</script>';
        exit;
    }

    echo '<script>alert("' . addslashes(isset($transitionResult['message']) ? $transitionResult['message'] : 'Unable to update order status.') . '");</script>';
}

if (post('returnActionBtn')) {
    $returnType = postSpaceFilter('return_type');
    $returnRemark = postSpaceFilter('return_remark');
    $returnResult = shopeeOmsHandleReturn($connect, $finance_connect, (int) $dataID, $returnType, $returnRemark, USER_ID, USER_GROUP, $pageTitle);
    if (!empty($returnResult['success'])) {
        $log = [
            'log_act' => 'edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'OMS return ' . $returnType,
            'query_table' => $tblName,
            'page' => $pageTitle,
            'connect' => $connect,
            'changes' => 'return_type: ' . $returnType,
            'act_msg' => USER_NAME . " marked Shopee order #" . (int) $dataID . " as returned (" . htmlspecialchars($returnType, ENT_QUOTES, 'UTF-8') . ").",
        ];
        audit_log($log);
        echo '<script>alert("' . addslashes($returnResult['message']) . '"); window.location.replace("' . $redirect_page . '");</script>';
        exit;
    }

    echo '<script>alert("' . addslashes(isset($returnResult['message']) ? $returnResult['message'] : 'Unable to save return action.') . '");</script>';
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    $resolveMultiIds = function ($hiddenInput, $nameInput, $tableName) use ($connect) {
        $resolved = array();

        if (!is_array($hiddenInput)) {
            $hiddenInput = explode(',', (string) $hiddenInput);
        }

        foreach ($hiddenInput as $idVal) {
            $idVal = trim((string) $idVal);
            if ($idVal !== '' && ctype_digit($idVal) && (int) $idVal > 0) {
                $resolved[] = (string) ((int) $idVal);
            }
        }

        if (!is_array($nameInput)) {
            $nameInput = array($nameInput);
        }

        foreach ($nameInput as $nameVal) {
            $nameVal = trim((string) $nameVal);
            if ($nameVal === '') {
                continue;
            }

            $escapedName = mysqli_real_escape_string($connect, $nameVal);
            $nameRst = getData('id', "name = '$escapedName'", 'LIMIT 1', $tableName, $connect);
            if ($nameRst && $nameRst->num_rows > 0) {
                $nameRow = $nameRst->fetch_assoc();
                $resolvedId = (int) $nameRow['id'];
                if ($resolvedId > 0) {
                    $resolved[] = (string) $resolvedId;
                }
            }
        }

        $resolved = array_values(array_unique($resolved));
        return implode(',', $resolved);
    };
    $normalizeAmount = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $numericValue = (float) $value;
        if (abs($numericValue) < 0.00001) {
            $numericValue = 0.0;
        }

        return number_format($numericValue, 2, '.', '');
    };

    $sor_acc = postSpaceFilter('sor_acc');
    $sor_curr = postSpaceFilter('sor_curr_hidden');
    $sor_order = postSpaceFilter('sor_order');
    $sor_date = postSpaceFilter('sor_date');
    $sor_time = postSpaceFilter('sor_time');
    $sor_pkg = $resolveMultiIds(
        isset($_POST['sor_pkg_hidden']) ? $_POST['sor_pkg_hidden'] : array(),
        isset($_POST['sor_pkg']) ? $_POST['sor_pkg'] : array(),
        PKG
    );

    $sor_brand = $resolveMultiIds(
        isset($_POST['sor_brand_hidden']) ? $_POST['sor_brand_hidden'] : array(),
        isset($_POST['sor_brand']) ? $_POST['sor_brand'] : array(),
        BRAND
    );
    $sor_user = postSpaceFilter('sor_user_hidden');
    $sor_pay = postSpaceFilter('sor_pay');
    $sor_pic = postSpaceFilter('sor_pic_hidden');
    $sor_price = postSpaceFilter('sor_price');
    $sor_voucher = postSpaceFilter('sor_voucher');
    $sor_shipping = postSpaceFilter('sor_shipping');
    $sor_serv = postSpaceFilter('sor_serv');
    $sor_trans = postSpaceFilter('sor_trans');
    $sor_ams = postSpaceFilter('sor_ams');
    $postedSorFees = postSpaceFilter('sor_fees');
    $postedSorFinal = postSpaceFilter('sor_final');
    $sor_price = $normalizeAmount($sor_price);
    $sor_voucher = $normalizeAmount($sor_voucher);
    $sor_shipping = $normalizeAmount($sor_shipping);
    $sor_serv = $normalizeAmount($sor_serv);
    $sor_trans = $normalizeAmount($sor_trans);
    $sor_ams = $normalizeAmount($sor_ams);
    $computedSorFees = (float) ($sor_serv === '' ? 0 : $sor_serv)
        + (float) ($sor_trans === '' ? 0 : $sor_trans)
        + (float) ($sor_ams === '' ? 0 : $sor_ams);
    $normalizedPostedSorFees = $normalizeAmount($postedSorFees);
    $sor_fees = $normalizedPostedSorFees === ''
        ? number_format($computedSorFees, 2, '.', '')
        : $normalizedPostedSorFees;
    $computedSorFinal = (float) ($sor_price === '' ? 0 : $sor_price)
        - (float) ($sor_voucher === '' ? 0 : $sor_voucher)
        - (float) ($sor_shipping === '' ? 0 : $sor_shipping)
        - $computedSorFees;
    $normalizedPostedSorFinal = $normalizeAmount($postedSorFinal);
    $sor_final = $normalizedPostedSorFinal === ''
        ? number_format($computedSorFinal, 2, '.', '')
        : $normalizedPostedSorFinal;
    $sor_remark = postSpaceFilter('sor_remark');
    $sor_order_status = shopeeOmsNormalizeStatusCode(postSpaceFilter('sor_order_status'));
    if ($sor_order_status === '') {
        $sor_order_status = isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P';
    }
    $sor_update_airbill = strtolower(trim((string) postSpaceFilter('sor_update_airbill')));
    if ($sor_update_airbill === '') {
        $sor_update_airbill = 'yes';
    }
    $sor_airbill = postSpaceFilter('sor_airbill');
    $sor_customer_address = postSpaceFilter('sor_customer_address');
    $sor_airbill_attachment = null;
    if (isset($_FILES["sor_airbill_attachment"]) && $_FILES["sor_airbill_attachment"]["size"] != 0) {
        $sor_airbill_attachment = $_FILES["sor_airbill_attachment"]["name"];
    } elseif (isset($_POST['sor_airbill_attachment_value'])) {
        $sor_airbill_attachment = $_POST['sor_airbill_attachment_value'];
    }
    $packageQtySnapshot = shopeeOmsBuildPackageQtySnapshotFromInputs(
        isset($_POST['sor_pkg_hidden']) ? $_POST['sor_pkg_hidden'] : array(),
        isset($_POST['sor_pkg']) ? $_POST['sor_pkg'] : array(),
        $connect
    );
    $packageQtySnapshotJson = !empty($packageQtySnapshot) ? json_encode($packageQtySnapshot) : '';

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':
            if ($sor_update_airbill === 'yes' && isset($_FILES["sor_airbill_attachment"]) && $_FILES["sor_airbill_attachment"]["size"] != 0) {
                $uploadResult = shopeeOmsStoreAirbillAttachmentUpload(
                    $_FILES["sor_airbill_attachment"],
                    $connect,
                    $sor_brand,
                    $sor_pkg,
                    'shopee_order_request',
                    $allowed_ext
                );
                if (!empty($uploadResult['success'])) {
                    $sor_airbill_attachment = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
                } else {
                    $airbill_attachment_err = isset($uploadResult['message']) ? (string) $uploadResult['message'] : "Failed to upload the airbill attachment.";
                    $error = 1;
                }
            }

            if ($sor_update_airbill !== 'yes') {
                if ($action === 'updRecord') {
                    $sor_airbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                    $sor_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                    $sor_customer_address = isset($row['customer_address']) ? (string) $row['customer_address'] : '';
                } else {
                    $sor_airbill = '';
                    $sor_airbill_attachment = '';
                    $sor_customer_address = '';
                }
            }

            if (!$sor_acc) {
                $acc_err = "Shopee Account cannot be empty.";
                $error = 1;
            }
            if (!$sor_curr) {
                $curr_err = "Currency cannot be empty.";
                $error = 1;
            }
            if (!$sor_order) {
                $order_err = "Order ID cannot be empty.";
                $error = 1;
            }
            if (!$sor_date) {
                $date_err = "Date cannot be empty.";
                $error = 1;
            }
            if (!$sor_time) {
                $time_err = "Time cannot be empty.";
                $error = 1;
            }
            if (!$sor_pkg) {
                $pkg_err = "Package cannot be empty.";
                $error = 1;
            }
            if (!$sor_brand) {
                $brand_err = "Brand cannot be empty.";
                $error = 1;
            }
            if (!$sor_user) {
                $user_err = "Shopee Buyer Username cannot be empty.";
                $error = 1;
            }
            if (!$sor_pay) {
                $pay_err = "Buyer Payment Method cannot be empty.";
                $error = 1;
            }
            if (!$sor_pic) {
                $pic_err = "Person In Charge cannot be empty.";
                $error = 1;
            }
            if (!$sor_price) {
                $price_err = "Product Price cannot be empty.";
                $error = 1;
            }

            $effectiveAirbill = $sor_airbill;
            if ($action === 'updRecord' && $sor_update_airbill !== 'yes') {
                $effectiveAirbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
            }
            $statusValidation = shopeeOmsValidateInitialStatusAndAirbill($sor_order_status, $effectiveAirbill);
            if (!$statusValidation['valid']) {
                $airbill_err = $statusValidation['message'];
                $error = 1;
            }
            if ($sor_update_airbill === 'yes') {
                if (trim((string) $sor_airbill) === '') {
                    $airbill_err = "Airbill No cannot be empty when Update Airbill is enabled.";
                    $error = 1;
                }
                if (trim((string) $sor_customer_address) === '') {
                    $customer_address_err = "Customer Address cannot be empty when Update Airbill is enabled.";
                    $error = 1;
                }
                if (trim((string) $sor_airbill_attachment) === '') {
                    $airbill_attachment_err = "Airbill Attachment cannot be empty when Update Airbill is enabled.";
                    $error = 1;
                }
            }

            if (isset($error)) {
                break;
            }
            if ($action == 'addRecord') {
                try {
                    $requiresInitialShippedAutoMove = ($sor_order_status === 'SP');
                    $startedFinanceTransaction = false;
                    if ($requiresInitialShippedAutoMove) {
                        mysqli_begin_transaction($finance_connect);
                        $startedFinanceTransaction = true;
                    }

                    //check values
                    if ($sor_acc) {
                        array_push($newvalarr, $sor_acc);
                        array_push($datafield, 'shopee account');
                    }
                    if ($sor_curr) {
                        array_push($newvalarr, $sor_curr);
                        array_push($datafield, 'currency');
                    }

                    if ($sor_order) {
                        array_push($newvalarr, $sor_order);
                        array_push($datafield, 'order ID');
                    }

                    if ($sor_date) {
                        array_push($newvalarr, $sor_date);
                        array_push($datafield, 'date');
                    }

                    if ($sor_time) {
                        array_push($newvalarr, $sor_time);
                        array_push($datafield, 'time');
                    }

                    if ($sor_pkg) {
                        array_push($newvalarr, $sor_pkg);
                        array_push($datafield, 'package');
                    }

                    if ($sor_brand) {
                        array_push($newvalarr, $sor_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($sor_user) {
                        array_push($newvalarr, $sor_user);
                        array_push($datafield, 'buyer username');
                    }

                    if ($sor_pay) {
                        array_push($newvalarr, $sor_pay);
                        array_push($datafield, 'buyer payment method');
                    }

                    if ($sor_pic) {
                        array_push($newvalarr, $sor_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($sor_price) {
                        array_push($newvalarr, $sor_price);
                        array_push($datafield, 'price');
                    }

                    if ($sor_voucher) {
                        array_push($newvalarr, $sor_voucher);
                        array_push($datafield, 'voucher');
                    }

                    if ($sor_shipping) {
                        array_push($newvalarr, $sor_shipping);
                        array_push($datafield, 'actual shipping');
                    }

                    if ($sor_serv) {
                        array_push($newvalarr, $sor_serv);
                        array_push($datafield, 'service fee');
                    }

                    if ($sor_trans) {
                        array_push($newvalarr, $sor_trans);
                        array_push($datafield, 'transaction fee');
                    }

                    if ($sor_ams) {
                        array_push($newvalarr, $sor_ams);
                        array_push($datafield, 'AMS fee');
                    }

                    if ($sor_fees) {
                        array_push($newvalarr, $sor_fees);
                        array_push($datafield, 'fees and charges');
                    }

                    if ($sor_final) {
                        array_push($newvalarr, $sor_final);
                        array_push($datafield, 'final amount');
                    }

                    if ($sor_remark) {
                        array_push($newvalarr, $sor_remark);
                        array_push($datafield, 'remark');
                    }
                    if ($sor_order_status) {
                        array_push($newvalarr, $sor_order_status);
                        array_push($datafield, 'order_status');
                    }
                    if ($effectiveAirbill !== '') {
                        array_push($newvalarr, $effectiveAirbill);
                        array_push($datafield, 'airbill_no');
                    }
                    if ($sor_customer_address !== '') {
                        array_push($newvalarr, $sor_customer_address);
                        array_push($datafield, 'customer_address');
                    }

                    $safeSorAcc = mysqli_real_escape_string($finance_connect, $sor_acc);
                    $safeSorCurr = mysqli_real_escape_string($finance_connect, $sor_curr);
                    $safeSorOrder = mysqli_real_escape_string($finance_connect, $sor_order);
                    $safeSorDate = mysqli_real_escape_string($finance_connect, $sor_date);
                    $safeSorTime = mysqli_real_escape_string($finance_connect, $sor_time);
                    $safeSorPkg = mysqli_real_escape_string($finance_connect, $sor_pkg);
                    $safePackageQtySnapshotJson = mysqli_real_escape_string($finance_connect, $packageQtySnapshotJson);
                    $safeSorBrand = mysqli_real_escape_string($finance_connect, $sor_brand);
                    $safeSorUser = mysqli_real_escape_string($finance_connect, $sor_user);
                    $safeSorPay = mysqli_real_escape_string($finance_connect, $sor_pay);
                    $safeSorPic = mysqli_real_escape_string($finance_connect, $sor_pic);
                    $safeSorPrice = mysqli_real_escape_string($finance_connect, $sor_price);
                    $safeSorVoucher = mysqli_real_escape_string($finance_connect, $sor_voucher);
                    $safeSorShipping = mysqli_real_escape_string($finance_connect, $sor_shipping);
                    $safeSorServ = mysqli_real_escape_string($finance_connect, $sor_serv);
                    $safeSorTrans = mysqli_real_escape_string($finance_connect, $sor_trans);
                    $safeSorAms = mysqli_real_escape_string($finance_connect, $sor_ams);
                    $safeSorFees = mysqli_real_escape_string($finance_connect, $sor_fees);
                    $safeSorFinal = mysqli_real_escape_string($finance_connect, $sor_final);
                    $safeSorRemark = mysqli_real_escape_string($finance_connect, $sor_remark);
                    $safeSorStatus = mysqli_real_escape_string($finance_connect, $sor_order_status);
                    $safeAirbill = mysqli_real_escape_string($finance_connect, $effectiveAirbill);
                    $safeAirbillAttachment = mysqli_real_escape_string($finance_connect, $sor_airbill_attachment);
                    $safeCustomerAddress = mysqli_real_escape_string($finance_connect, $sor_customer_address);

                    $query = "INSERT INTO " . $tblName . " (shopee_acc,currency,orderID,date,time,package,package_qty_json,brand,buyer,buyer_pay_meth,pic,customer_address,price,voucher,act_shipping_fee,service_fee,trans_fee,ams_fee,fees,final_amt,airbill_no,airbill_attachment,remark,order_status,latest_transition_at,create_by,create_date,create_time) VALUES ('$safeSorAcc','$safeSorCurr','$safeSorOrder','$safeSorDate','$safeSorTime','$safeSorPkg','$safePackageQtySnapshotJson','$safeSorBrand','$safeSorUser','$safeSorPay','$safeSorPic','$safeCustomerAddress','$safeSorPrice','$safeSorVoucher','$safeSorShipping','$safeSorServ','$safeSorTrans','$safeSorAms','$safeSorFees','$safeSorFinal','$safeAirbill','$safeAirbillAttachment','$safeSorRemark','$safeSorStatus',NOW(),'" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($finance_connect, $query);
                    if (!$returnData) {
                        throw new Exception('Database Error: ' . mysqli_error($finance_connect));
                    }

                    if ($returnData) {
                        $dataID = (int) mysqli_insert_id($finance_connect);
                        shopeeOmsLogTransition($finance_connect, array(
                            'order_id' => $dataID,
                            'order_code' => $sor_order,
                            'from_status' => '',
                            'to_status' => $sor_order_status,
                            'transition_action' => 'manual_add',
                            'user_id' => USER_ID,
                            'user_group_id' => USER_GROUP,
                            'remark' => 'Manual add with initial status.',
                            'source_page' => $pageTitle,
                        ));

                        if ($sor_order_status === 'TP') {
                            $freshOrderRow = shopeeOmsLoadOrder($finance_connect, $dataID);
                            $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $freshOrderRow, USER_ID);
                            if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                                $notifyResult = shopeeOmsSendWarehouseNotification($connect, $finance_connect, $tokenResult['token_row'], $tokenResult['notification'], $pageTitle);
                                $sorLocalTelegramFailureMessage = $sorBuildLocalTelegramFailureMessage($notifyResult);
                                if (!empty($notifyResult['sent'])) {
                                    mysqli_query($finance_connect, "UPDATE `" . $tblName . "` SET `step_a_sent_at` = NOW() WHERE id = " . $dataID . " LIMIT 1");
                                }
                            }
                        } else if ($requiresInitialShippedAutoMove) {
                            $initialShippedResult = shopeeOmsFinalizeInitialShippedOrder($connect, $finance_connect, $dataID, USER_ID, USER_GROUP, $pageTitle);
                            if (empty($initialShippedResult['success'])) {
                                throw new Exception(isset($initialShippedResult['message']) ? $initialShippedResult['message'] : 'Unable to process initial Shipped status.');
                            }
                        }

                        if ($startedFinanceTransaction) {
                            mysqli_commit($finance_connect);
                            $startedFinanceTransaction = false;
                        }
                    }
                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    if (isset($startedFinanceTransaction) && $startedFinanceTransaction) {
                        mysqli_rollback($finance_connect);
                    }
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $rst->fetch_assoc();

                    if ($sor_update_airbill !== 'yes') {
                        $sor_airbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                        $sor_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                    }

                    // check value
                    if ($row['shopee_acc'] != $sor_acc) {
                        array_push($oldvalarr, $row['shopee_acc']);
                        array_push($chgvalarr, $sor_acc);
                        array_push($datafield, 'shopee_acc');
                    }
                    if ($row['currency'] != $sor_curr) {
                        array_push($oldvalarr, $row['currency']);
                        array_push($chgvalarr, $sor_curr);
                        array_push($datafield, 'currency');
                    }

                    if ($row['orderID'] != $sor_order) {
                        array_push($oldvalarr, $row['orderID']);
                        array_push($chgvalarr, $sor_order);
                        array_push($datafield, 'orderID');
                    }

                    if ($row['date'] != $sor_date) {
                        array_push($oldvalarr, $row['date']);
                        array_push($chgvalarr, $sor_date);
                        array_push($datafield, 'date');
                    }

                    if ($row['time'] != $sor_time) {
                        array_push($oldvalarr, $row['time']);
                        array_push($chgvalarr, $sor_time);
                        array_push($datafield, 'time');
                    }

                    if ($row['package'] != $sor_pkg) {
                        array_push($oldvalarr, $row['package']);
                        array_push($chgvalarr, $sor_pkg);
                        array_push($datafield, 'package');
                    }

                    if ($row['brand'] != $sor_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $sor_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['buyer'] != $sor_user) {
                        array_push($oldvalarr, $row['buyer']);
                        array_push($chgvalarr, $sor_user);
                        array_push($datafield, 'buyer');
                    }

                    if ($row['buyer_pay_meth'] != $sor_pay) {
                        array_push($oldvalarr, $row['buyer_pay_meth']);
                        array_push($chgvalarr, $sor_pay);
                        array_push($datafield, 'buyer_pay_meth');
                    }

                    if ($row['pic'] != $sor_pic) {
                        array_push($oldvalarr, $row['pic']);
                        array_push($chgvalarr, $sor_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['price'] != $sor_price) {
                        array_push($oldvalarr, $row['price']);
                        array_push($chgvalarr, $sor_price);
                        array_push($datafield, 'price');
                    }

                    if ($row['voucher'] != $sor_voucher) {
                        array_push($oldvalarr, $row['voucher']);
                        array_push($chgvalarr, $sor_voucher);
                        array_push($datafield, 'voucher');
                    }

                    if ($row['act_shipping_fee'] != $sor_shipping) {
                        array_push($oldvalarr, $row['act_shipping_fee']);
                        array_push($chgvalarr, $sor_shipping);
                        array_push($datafield, 'act_shipping_fee');
                    }

                    if ($row['service_fee'] != $sor_serv) {
                        array_push($oldvalarr, $row['service_fee']);
                        array_push($chgvalarr, $sor_serv);
                        array_push($datafield, 'service fee');
                    }

                    if ($row['trans_fee'] != $sor_trans) {
                        array_push($oldvalarr, $row['trans_fee']);
                        array_push($chgvalarr, $sor_trans);
                        array_push($datafield, 'transaction fee');
                    }

                    if ($row['ams_fee'] != $sor_ams) {
                        array_push($oldvalarr, $row['ams_fee']);
                        array_push($chgvalarr, $sor_ams);
                        array_push($datafield, 'ams_fee');
                    }

                    if ($row['fees'] != $sor_fees) {
                        array_push($oldvalarr, $row['fees']);
                        array_push($chgvalarr, $sor_fees);
                        array_push($datafield, 'fees n charges');
                    }

                    if ($row['final_amt'] != $sor_final) {
                        array_push($oldvalarr, $row['final_amt']);
                        array_push($chgvalarr, $sor_final);
                        array_push($datafield, 'final amount');
                    }

                    if ($row['remark'] != $sor_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $sor_remark == '' ? 'Empty Value' : $sor_remark);
                        array_push($datafield, 'remark');
                    }

                    if ((string) (isset($row['package_qty_json']) ? $row['package_qty_json'] : '') !== (string) $packageQtySnapshotJson) {
                        array_push($oldvalarr, trim((string) (isset($row['package_qty_json']) ? $row['package_qty_json'] : '')) !== '' ? 'Package snapshot updated' : 'Empty Value');
                        array_push($chgvalarr, trim((string) $packageQtySnapshotJson) !== '' ? 'Package snapshot updated' : 'Empty Value');
                        array_push($datafield, 'package_qty_json');
                    }

                    if ((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '') !== (string) $sor_airbill) {
                        array_push($oldvalarr, trim((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '')) !== '' ? $row['airbill_no'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_airbill) !== '' ? $sor_airbill : 'Empty Value');
                        array_push($datafield, 'airbill_no');
                    }

                    if ((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '') !== (string) $sor_airbill_attachment) {
                        array_push($oldvalarr, trim((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '')) !== '' ? $row['airbill_attachment'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_airbill_attachment) !== '' ? $sor_airbill_attachment : 'Empty Value');
                        array_push($datafield, 'airbill_attachment');
                    }

                    if ((string) (isset($row['customer_address']) ? $row['customer_address'] : '') !== (string) $sor_customer_address) {
                        array_push($oldvalarr, trim((string) (isset($row['customer_address']) ? $row['customer_address'] : '')) !== '' ? $row['customer_address'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_customer_address) !== '' ? $sor_customer_address : 'Empty Value');
                        array_push($datafield, 'customer_address');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET ";
                        $query .= "shopee_acc = '$sor_acc', ";
                        $query .= "currency = '$sor_curr', ";
                        $query .= "orderID = '$sor_order', ";
                        $query .= "date = '$sor_date', ";
                        $query .= "time = '$sor_time', ";
                        $query .= "package = '$sor_pkg', ";
                        $query .= "package_qty_json = '" . mysqli_real_escape_string($finance_connect, $packageQtySnapshotJson) . "', ";
                        $query .= "brand = '$sor_brand', ";
                        $query .= "buyer = '$sor_user', ";
                        $query .= "buyer_pay_meth = '$sor_pay', ";
                        $query .= "pic = '$sor_pic', ";
                        $query .= "customer_address = '" . mysqli_real_escape_string($finance_connect, $sor_customer_address) . "', ";
                        $query .= "price = '$sor_price', ";
                        $query .= "voucher = '$sor_voucher', ";
                        $query .= "act_shipping_fee = '$sor_shipping', ";
                        $query .= "service_fee = '$sor_serv', ";
                        $query .= "trans_fee = '$sor_trans', ";
                        $query .= "ams_fee = '$sor_ams', ";
                        $query .= "fees = '$sor_fees', ";
                        $query .= "final_amt = '$sor_final', ";
                        $query .= "airbill_no = '" . mysqli_real_escape_string($finance_connect, $sor_airbill) . "', ";
                        $query .= "airbill_attachment = '" . mysqli_real_escape_string($finance_connect, $sor_airbill_attachment) . "', ";
                        $query .= "remark = '$sor_remark', ";
                        $query .= "update_by = '" . USER_ID . "', ";
                        $query .= "update_date = curdate(), ";
                        $query .= "update_time = curtime() ";
                        $query .= "WHERE id = '$dataID'"; // Specify your condition here

                        $returnData = mysqli_query($finance_connect, $query);
                        if ($returnData) {
                            $newValuesForHistory = array(
                                'orderID' => $sor_order,
                                'customer_address' => $sor_customer_address,
                                'package' => $sor_pkg,
                                'package_qty_json' => $packageQtySnapshotJson,
                                'brand' => $sor_brand,
                                'buyer' => $sor_user,
                                'buyer_pay_meth' => $sor_pay,
                                'price' => $sor_price,
                                'airbill_no' => $sor_airbill,
                                'airbill_attachment' => $sor_airbill_attachment,
                                'remark' => $sor_remark,
                                'estimated_received_date' => isset($row['estimated_received_date']) ? $row['estimated_received_date'] : '',
                                'delay_remark' => isset($row['delay_remark']) ? $row['delay_remark'] : '',
                                'order_status' => isset($row['order_status']) ? $row['order_status'] : '',
                            );
                            $orderChanges = shopeeOmsDetectOrderChanges($connect, $row, $newValuesForHistory);
                            shopeeOmsLogOrderEditHistory($finance_connect, (int) $dataID, $sor_order, $orderChanges, USER_ID, USER_GROUP, $pageTitle);
                        }

                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
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
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }

            break;

        case 'back':
            echo $clearLocalStorage . ' ' . $redirectLink;
            break;
    }
}


if (post('act') == 'D') {
    $id = post('id');
    if ($id) {
        try {
            // take name
            $rst = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $finance_connect);
            $row = $rst->fetch_assoc();

            $dataID = $row['id'];

            //SET the record status to 'D'
            deleteRecord($tblName, '', $dataID, $sor_name, $finance_connect, $connect, $cdate, $ctime, $pageTitle);
            $_SESSION['delChk'] = 1;
        } catch (Exception $e) {
            echo 'Message: ' . $e->getMessage();
        }
    }
}

//view
if (($dataID) && !($act) && (USER_ID != '') && ($_SESSION['viewChk'] != 1) && ($_SESSION['delChk'] != 1)) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . (isset($row['orderID']) ? $row['orderID'] : $dataID) . "</b> from <b><i>$tblName Table</i></b>.";
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
if (isset($row['buyer']) && trim((string) $row['buyer']) !== '') {
    $urbanismBadgeSeedName = trim((string) $row['buyer']);
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('sor_user_hidden') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('sor_user_hidden'));
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('sor_user') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('sor_user'));
}

// Some Shopee orders persist buyer as SHOPEE_CUST_INFO.id instead of username.
// Resolve to buyer_username first so Urbanism member matching is stable.
if ($urbanismBadgeSeedName !== '' && ctype_digit($urbanismBadgeSeedName)) {
    $buyerId = (int) $urbanismBadgeSeedName;
    if ($buyerId > 0) {
        $buyerRst = getData('buyer_username', "id='" . $buyerId . "'", 'LIMIT 1', SHOPEE_CUST_INFO, $finance_connect);
        if ($buyerRst && $buyerRst->num_rows > 0) {
            $buyerRow = $buyerRst->fetch_assoc();
            if (isset($buyerRow['buyer_username']) && trim((string) $buyerRow['buyer_username']) !== '') {
                $urbanismBadgeSeedName = trim((string) $buyerRow['buyer_username']);
            }
        }
    }
}

$urbanismBadgeAction = getUrbanismMemberActionData(
    $connect,
    '',
    $urbanismBadgeSeedName,
    $redirect_page,
    $pageTitle
);

$transitionHistoryRows = array();
$editHistoryRows = array();
if (isset($row['id']) && (int) $row['id'] > 0) {
    $transitionHistoryRows = shopeeOmsFetchTransitionHistory($finance_connect, (int) $row['id']);
    $editHistoryRows = shopeeOmsFetchEditHistory($finance_connect, (int) $row['id']);
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../finance/header/js/pdf.min.js"></script>
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
            margin-top: 6px;
            font-size: 0.875rem;
            color: #6c757d;
        }

        .shopee-airbill-extract-status.is-error {
            color: #dc3545;
        }

    </style>
</head>

<body>
    <div id="shopeeOrderReqBreadcrumbWrap" class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= $redirect_page ?>">
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
                <div class="form-group mb-5">
                    <div class="order-title-row">
                        <h2 class="mb-0"><?php echo displayPageAction($act, $pageTitle); ?></h2>
                        <?php if ($act == 'E'): ?>
                            <span id="order-status">Order Status: <?= getOrderStatusLabel($row['order_status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="order-badge-row text-end mt-2">
                        <a
                            class="btn btn-sm <?= $urbanismBadgeAction['is_member'] ? 'btn-success' : 'btn-outline-secondary' ?> <?= $urbanismBadgeAction['disabled'] ? 'disabled' : '' ?>"
                            href="<?= htmlspecialchars($urbanismBadgeAction['url'], ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars($urbanismBadgeAction['title'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $urbanismBadgeAction['disabled'] ? 'onclick="return false;" aria-disabled="true"' : '' ?>><i class="<?= htmlspecialchars($urbanismBadgeAction['icon_class'], ENT_QUOTES, 'UTF-8') ?>"></i></a>
                    </div>
                  
                </div>

                <div id="err_msg" class="mb-3">
                    <span class="mt-n2" style="font-size: 21px;">
                        <?php if (isset($err1))
                            echo $err1; ?>
                    </span>
                </div>

                <div class="form-group">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_acc_label" for="sor_acc">Shopee Account
                                <span class="requireRed">*</span></label>
                            <select class="form-select" id="sor_acc" name="sor_acc" <?php if ($act == '')
                                echo 'disabled' ?>>
                                    <option value="0" disabled selected>Select Shopee Account</option>
                                    <?php
                            $query = "SELECT * FROM " . SHOPEE_ACC . " WHERE `status` = 'A' ORDER BY `name` ASC";
                            $acc_result = $finance_connect->query($query);
                            if ($acc_result->num_rows >= 1) {
                                $acc_result->data_seek(0);
                                while ($row3 = $acc_result->fetch_assoc()) {
                                    $selected = "";
                                    if (isset($dataExisted, $row['shopee_acc']) && !isset($sor_acc)) {
                                        $selected = $row['shopee_acc'] == $row3['id'] ? " selected" : "";
                                    } else if (isset($sor_acc)) {
                                        $selected = $sor_acc == $row3['id'] ? " selected" : "";
                                    }
                                    echo "<option value=\"" . $row3['id'] . "\"$selected>" . $row3['name'] . "</option>";
                                }
                            } else {
                                echo "<option value=\"0\">None</option>";
                            }

                            ?>
                            </select>
                            <?php if (isset($acc_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $acc_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_curr_lbl" for="sor_curr">Currency<span
                                    class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            if (isset($row['currency']))
                                $echoVal = $row['currency'];

                            if (isset($echoVal)) {
                                $curr_rst = getData('*', "id = '$echoVal'", '', CUR_UNIT, $connect);
                                $curr_row = $curr_rst ? $curr_rst->fetch_assoc() : [];
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_curr" id="sor_curr" disabled value="<?php echo !empty($echoVal) ? $curr_row['unit'] : '' ?>">
                            <input type="hidden" name="sor_curr_hidden" id="sor_curr_hidden" value="<?php echo (isset($row['currency'])) ? $row['currency'] : ''; ?>">
                            <?php if (isset($curr_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $curr_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_order_lbl" for="sor_order">Order ID<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="text" name="sor_order" id="sor_order" value="<?php
                            if (isset($dataExisted) && isset($row['orderID']) && !isset($sor_order)) {
                                echo $row['orderID'];
                            } else if (isset($sor_order)) {
                                echo $sor_order;
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($order_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $order_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_date_label" for="sor_date">Date<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="date" name="sor_date" id="sor_date" value="<?php
                            if (isset($dataExisted) && isset($row['date']) && !isset($sor_date)) {
                                echo $row['date'];
                            } else if (isset($sor_date)) {
                                echo $sor_date;
                            } else {
                                echo date('Y-m-d');
                            }
                            ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($date_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $date_err; ?>
                                    </span>
                                </div>
                            <?php } ?>

                        </div>
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_time_label" for="sor_time">Time<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="time" name="sor_time" id="sor_time" value="<?php
                            if (isset($dataExisted) && isset($row['time']) && !isset($sor_time)) {
                                echo !empty($row['time']) ? date('H:i', strtotime($row['time'])) : '';
                            } else if (isset($sor_time)) {
                                echo !empty($sor_time) ? date('H:i', strtotime($sor_time)) : '';
                            } else {
                                echo date('H:i');
                            }
                            ?>" placeholder="HH:MM" pattern="[0-9]{2}:[0-9]{2}" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($time_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $time_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_pkg_lbl" for="sor_pkg_hidden">Package<span
                                    class="requireRed">*</span></label>
                            <?php
                            $selectedPkgIds = array();
                            $postedPkgNames = (isset($_POST['sor_pkg']) && is_array($_POST['sor_pkg'])) ? $_POST['sor_pkg'] : array();
                            if (isset($sor_pkg) && $sor_pkg !== '') {
                                $selectedPkgIds = array_filter(array_map('trim', explode(',', $sor_pkg)), 'strlen');
                            } else if (isset($row['package']) && $row['package'] !== '') {
                                $selectedPkgIds = array_filter(array_map('trim', explode(',', $row['package'])), 'strlen');
                            }

                            $pkgRows = array();
                            if (!empty($selectedPkgIds)) {
                                foreach ($selectedPkgIds as $pkgId) {
                                    $pkgIdInt = (int) $pkgId;
                                    $pkgName = '';
                                    if ($pkgIdInt > 0) {
                                        $pkgRst = getData('name', "id = '$pkgIdInt'", 'LIMIT 1', PKG, $connect);
                                        if ($pkgRst && $pkgRst->num_rows > 0) {
                                            $pkgData = $pkgRst->fetch_assoc();
                                            $pkgName = $pkgData['name'];
                                        }
                                    }
                                    $pkgRows[] = array('id' => $pkgIdInt, 'name' => $pkgName);
                                }
                            } else if (!empty($postedPkgNames)) {
                                foreach ($postedPkgNames as $idx => $pkgName) {
                                    $postedPkgId = (isset($_POST['sor_pkg_hidden'][$idx])) ? (int) $_POST['sor_pkg_hidden'][$idx] : 0;
                                    $pkgRows[] = array('id' => $postedPkgId, 'name' => trim((string) $pkgName));
                                }
                            }

                            if (empty($pkgRows)) {
                                $pkgRows[] = array('id' => '', 'name' => '');
                            }
                            ?>
                            <div id="sor_pkg_container">
                                <?php foreach ($pkgRows as $pkgIndex => $pkgRow) { ?>
                                    <div class="input-group mb-2 sor-pkg-row autocomplete">
                                        <input class="form-control sor-pkg-input" type="text" name="sor_pkg[]"
                                            id="sor_pkg_<?php echo $pkgIndex; ?>"
                                            data-hidden-target="sor_pkg_hidden_<?php echo $pkgIndex; ?>"
                                            value="<?php echo htmlspecialchars($pkgRow['name']); ?>" <?php if ($act == '') echo 'disabled'; ?>>
                                        <input type="hidden" class="sor-pkg-hidden" name="sor_pkg_hidden[]"
                                            id="sor_pkg_hidden_<?php echo $pkgIndex; ?>"
                                            value="<?php echo htmlspecialchars((string) $pkgRow['id']); ?>">
                                        <?php if ($act != '' && $pkgIndex > 0) { ?>
                                            <button type="button" class="btn btn-outline-danger sor-remove-row-btn" data-row-type="pkg" title="Remove Package">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($act != '') { ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="add_pkg_btn">+ Add Package</button>
                            <?php } ?>
                            <?php if (isset($pkg_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $pkg_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_brand_lbl" for="sor_brand_hidden">Brand<span
                                    class="requireRed">*</span></label>
                            <?php
                            $selectedBrandIds = array();
                            $postedBrandNames = (isset($_POST['sor_brand']) && is_array($_POST['sor_brand'])) ? $_POST['sor_brand'] : array();
                            if (isset($sor_brand) && $sor_brand !== '') {
                                $selectedBrandIds = array_filter(array_map('trim', explode(',', $sor_brand)), 'strlen');
                            } else if (isset($row['brand']) && $row['brand'] !== '') {
                                $selectedBrandIds = array_filter(array_map('trim', explode(',', $row['brand'])), 'strlen');
                            }

                            $brandRows = array();
                            if (!empty($selectedBrandIds)) {
                                foreach ($selectedBrandIds as $brandId) {
                                    $brandIdInt = (int) $brandId;
                                    $brandName = '';
                                    if ($brandIdInt > 0) {
                                        $brandRst = getData('name', "id = '$brandIdInt'", 'LIMIT 1', BRAND, $connect);
                                        if ($brandRst && $brandRst->num_rows > 0) {
                                            $brandData = $brandRst->fetch_assoc();
                                            $brandName = $brandData['name'];
                                        }
                                    }
                                    $brandRows[] = array('id' => $brandIdInt, 'name' => $brandName);
                                }
                            } else if (!empty($postedBrandNames)) {
                                foreach ($postedBrandNames as $idx => $brandName) {
                                    $postedBrandId = (isset($_POST['sor_brand_hidden'][$idx])) ? (int) $_POST['sor_brand_hidden'][$idx] : 0;
                                    $brandRows[] = array('id' => $postedBrandId, 'name' => trim((string) $brandName));
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
                                            value="<?php echo htmlspecialchars($brandRow['name']); ?>" <?php if ($act == '') echo 'disabled'; ?>>
                                        <input type="hidden" class="sor-brand-hidden" name="sor_brand_hidden[]"
                                            id="sor_brand_hidden_<?php echo $brandIndex; ?>"
                                            value="<?php echo htmlspecialchars((string) $brandRow['id']); ?>">
                                        <?php if ($act != '' && $brandIndex > 0) { ?>
                                            <button type="button" class="btn btn-outline-danger sor-remove-row-btn" data-row-type="brand" title="Remove Brand">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($act != '') { ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="add_brand_btn">+ Add Brand</button>
                            <?php } ?>
                            <?php if (isset($brand_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $brand_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row shopee-airbill-row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_order_status">Initial Order Status<span class="requireRed">*</span></label>
                            <?php
                            $currentOrderStatusValue = isset($sor_order_status) && trim((string) $sor_order_status) !== ''
                                ? $sor_order_status
                                : (isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P');
                            ?>
                            <?php if ($act === 'I') { ?>
                                <select class="form-select" id="sor_order_status" name="sor_order_status">
                                    <?php foreach ($sorStatusOptions as $statusCode => $statusLabel) { ?>
                                        <option value="<?= htmlspecialchars($statusCode) ?>" <?= $currentOrderStatusValue === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                    <?php } ?>
                                </select>
                            <?php } else { ?>
                                <input class="form-control" type="text" value="<?= htmlspecialchars(shopeeOmsGetStatusLabel($currentOrderStatusValue)) ?>" readonly>
                                <input type="hidden" id="sor_order_status" name="sor_order_status" value="<?= htmlspecialchars($currentOrderStatusValue) ?>">
                            <?php } ?>
                        </div>
                        <div class="col-md-2 mb-3 shopee-airbill-toggle-col">
                            <?php
                            $hasSavedAirbillData = false;
                            if (isset($row['airbill_no']) && trim((string) $row['airbill_no']) !== '') {
                                $hasSavedAirbillData = true;
                            }
                            if (isset($row['airbill_attachment']) && trim((string) $row['airbill_attachment']) !== '') {
                                $hasSavedAirbillData = true;
                            }
                            $updateAirbillValue = isset($sor_update_airbill) && trim((string) $sor_update_airbill) !== ''
                                ? strtolower(trim((string) $sor_update_airbill))
                                : (
                                    isset($row['update_airbill']) && trim((string) $row['update_airbill']) !== ''
                                        ? strtolower(trim((string) $row['update_airbill']))
                                        : ($hasSavedAirbillData ? 'yes' : ($act === 'I' ? 'yes' : 'no'))
                                );
                            if ($updateAirbillValue !== 'yes' && $hasSavedAirbillData) {
                                $updateAirbillValue = 'yes';
                            } else if ($updateAirbillValue !== 'yes') {
                                $updateAirbillValue = 'no';
                            }
                            ?>
                            <input type="hidden" id="sor_update_airbill" name="sor_update_airbill" value="<?= htmlspecialchars($updateAirbillValue) ?>">
                            <label class="form-label form_lbl shopee-airbill-toggle-label" for="sor_update_airbill_toggle">Update Airbill?</label>
                            <div class="shopee-airbill-toggle-field">
                                <label class="shopee-airbill-toggle mb-0" for="sor_update_airbill_toggle">
                                    <input type="checkbox" id="sor_update_airbill_toggle" <?= $updateAirbillValue === 'yes' ? 'checked' : '' ?> <?= $act == '' ? 'disabled' : '' ?>>
                                    <span class="shopee-airbill-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="sor_airbill">Airbill No<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" name="sor_airbill" id="sor_airbill" value="<?php
                                if (isset($sor_airbill)) {
                                    echo htmlspecialchars($sor_airbill);
                                } else if (isset($row['airbill_no'])) {
                                    echo htmlspecialchars($row['airbill_no']);
                                }
                            ?>" <?= $act == '' ? 'disabled' : '' ?>>
                            <?php if (isset($airbill_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $airbill_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="sor_airbill_attachment">Airbill Attachment<span class="requireRed">*</span></label>
                            <input class="form-control" type="file" name="sor_airbill_attachment" id="sor_airbill_attachment" <?= $act == '' ? 'disabled' : '' ?>>
                            <small id="sor_airbill_extract_status" class="shopee-airbill-extract-status"></small>
                            <?php if (isset($row['airbill_attachment']) && $row['airbill_attachment']) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo "Current Attachment: " . htmlspecialchars($row['airbill_attachment']); ?>
                                    </span>
                                </div>
                            <?php } ?>
                            <?php if (isset($airbill_attachment_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $airbill_attachment_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-center justify-content-md-end px-4">
                                <?php
                                $sorAirbillAttachmentSrc = '';
                                if (isset($dataExisted) && isset($row['airbill_attachment']) && !isset($sor_airbill_attachment)) {
                                    if ($row['airbill_attachment'] == '' || $row['airbill_attachment'] == NULL) {
                                        $sorAirbillAttachmentSrc = '';
                                    } else {
                                        $storedAttachment = trim(str_replace('\\', '/', (string) $row['airbill_attachment']), '/');
                                        if (strpos($storedAttachment, 'attachment/') === 0) {
                                            $sorAirbillAttachmentSrc = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
                                        } else {
                                            $sorAirbillAttachmentSrc = $sorAirbillAttachmentUrl . basename($storedAttachment);
                                        }
                                    }
                                } else if (isset($sor_airbill_attachment)) {
                                    $storedAttachment = trim(str_replace('\\', '/', (string) $sor_airbill_attachment), '/');
                                    if ($storedAttachment !== '') {
                                        if (strpos($storedAttachment, 'attachment/') === 0) {
                                            $sorAirbillAttachmentSrc = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
                                        } else {
                                            $sorAirbillAttachmentSrc = $sorAirbillAttachmentUrl . basename($storedAttachment);
                                        }
                                    }
                                }
                                ?>
                                <img id="sor_airbill_attachment_preview" name="sor_airbill_attachment_preview" src="<?php echo $sorAirbillAttachmentSrc; ?>" class="img-thumbnail" alt="Airbill Attachment Preview">
                                <input type="hidden" name="sor_airbill_attachment_value" id="sor_airbill_attachment_value" value="<?php if (isset($row['airbill_attachment'])) echo htmlspecialchars($row['airbill_attachment']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="sor_customer_address">Customer Address<span class="requireRed">*</span></label>
                            <textarea class="form-control" name="sor_customer_address" id="sor_customer_address" rows="2" <?= $act == '' ? 'disabled' : '' ?> <?= isset($updateAirbillValue) && $updateAirbillValue === 'yes' ? 'required' : '' ?>><?php
                                if (isset($sor_customer_address)) {
                                    echo htmlspecialchars($sor_customer_address);
                                } else if (isset($row['customer_address'])) {
                                    echo htmlspecialchars($row['customer_address']);
                                }
                            ?></textarea>
                            <?php if (isset($customer_address_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $customer_address_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_user_lbl" for="sor_user">Shopee Buyer
                                Username<span class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            $buyerDisplayValue = '';
                            if (isset($row['buyer']))
                                $echoVal = $row['buyer'];

                            if (isset($echoVal)) {
                                $user_rst = getData('*', "id = '$echoVal'", '', SHOPEE_CUST_INFO, $finance_connect);
                                $user_row = $user_rst ? $user_rst->fetch_assoc() : [];
                                if (isset($user_row['buyer_username'])) {
                                    $buyerDisplayValue = $user_row['buyer_username'];
                                } else {
                                    $buyerDisplayValue = $echoVal;
                                }
                            }
                            ?>
                            <div class="d-flex align-items-center gap-2 flex-nowrap">
                                <input class="form-control" type="text" name="sor_user" id="sor_user" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? $buyerDisplayValue : '' ?>">
                                <?php if ($sorBuyerSegmentationBadgeHtml !== '') { ?>
                                    <div class="d-inline-flex align-items-center flex-nowrap"><?= $sorBuyerSegmentationBadgeHtml ?></div>
                                <?php } ?>
                            </div>
                            <input type="hidden" name="sor_user_hidden" id="sor_user_hidden"
                                value="<?php echo (isset($row['buyer'])) ? $row['buyer'] : ''; ?>">
                            <?php if (isset($user_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $user_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_pay_label" for="sor_pay">Buyer Payment Method
                                <span class="requireRed">*</span></label>
                            <select class="form-select" id="sor_pay" name="sor_pay" <?php if ($act == '')
                                echo 'disabled' ?>>
                                    <option value="0" disabled selected>Select Payment Method</option>
                                    <?php
                            $query = "SELECT * FROM " . PAY_MTHD_SHOPEE . " ORDER BY `name` ASC ";
                            $acc_result = $finance_connect->query($query);
                            if ($acc_result->num_rows >= 1) {
                                $acc_result->data_seek(0);
                                while ($row4 = $acc_result->fetch_assoc()) {
                                    $selected = "";
                                    if (isset($dataExisted, $row['buyer_pay_meth']) && !isset($sor_pay)) {
                                        $selected = $row['buyer_pay_meth'] == $row4['id'] ? " selected" : "";
                                    } else if (isset($sor_pay)) {
                                        $selected = $sor_pay == $row4['id'] ? " selected" : "";
                                    }
                                    echo "<option value=\"" . $row4['id'] . "\"$selected>" . $row4['name'] . "</option>";
                                }
                            } else {
                                echo "<option value=\"0\">None</option>";
                            }

                            ?>
                            </select>
                            <?php if (isset($pay_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $pay_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php if ($act != ''){ ?>
                <div class="col-md-4 mb-3">
                    <button type="button" onclick="toggleNewBuyer()">Create New Customer ID</button>
                </div>
                <div id="myForm" novalidate>
                <div id="new_customer_section" style="display: none;">

                <div class="row">
                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_username">Shopee Buyer Username<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_username" name="scr_username" data-new-customer-required="1">
                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_pic">Sales Person In Charge<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_pic" name="scr_pic" data-new-customer-required="1">                        
                        <input class="form-control" type="hidden" id="scr_pic_hidden" name="scr_pic_hidden">

                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_country">Country<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_country" name="scr_country" data-new-customer-required="1">
                        <input class="form-control" type="hidden" id="scr_country_hidden" name="scr_country_hidden">
                    </div>
                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_brand">Brand<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_brand" name="scr_brand" data-new-customer-required="1"> <input class="form-control" type="hidden" id="scr_brand_hidden" name="scr_brand_hidden">
                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_series">Series<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_series" name="scr_series" data-new-customer-required="1"><input class="form-control" type="hidden" id="scr_series_hidden" name="scr_series_hidden">
                    </div>
                </div>
                <button type="button" id="new_customer_submit_btn">Submit</button>
                    </div>
                </div>
                <?php }?>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_pic_lbl" for="sor_pic">Person In
                                Charge<span class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            $picDisplayValue = '';

                            if (isset($row['pic']))
                                $echoVal = $row['pic'];

                            if (isset($echoVal)) {
                                $user_rst = getData('name', "id = '$echoVal'", '', USR_USER, $connect);
                                $user_row = $user_rst ? $user_rst->fetch_assoc() : [];
                                if (isset($user_row['name'])) {
                                    $picDisplayValue = $user_row['name'];
                                } else {
                                    $picDisplayValue = $echoVal;
                                }
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_pic" id="sor_pic" <?php if ($act == '')
                                echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $picDisplayValue : '' ?>">
                            <input type="hidden" name="sor_pic_hidden" id="sor_pic_hidden"
                                value="<?php echo (isset($row['pic'])) ? $row['pic'] : ''; ?>">


                            <?php if (isset($pic_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $pic_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_price_lbl" for="sor_price">Price<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="number" step="0.01" name="sor_price" id="sor_price" value="<?php
                            if (isset($dataExisted) && isset($row['price']) && !isset($sor_price)) {
                                echo $row['price'];
                            } else if (isset($sor_price)) {
                                echo $sor_price;
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($price_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $price_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_voucher_lbl" for="sor_voucher">Voucher </label>
                            <input class="form-control" type="number" step="0.01" name="sor_voucher" id="sor_voucher"
                                value="<?php
                                if (isset($dataExisted) && isset($row['voucher']) && !isset($sor_voucher)) {
                                    echo $row['voucher'];
                                } else if (isset($sor_voucher)) {
                                    echo $sor_voucher;
                                }
                                ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                            <?php if (isset($voucher_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $voucher_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_shipping_lbl" for="sor_shipping">Actual Shipping
                            </label>
                            <input class="form-control" type="number" step="0.01" name="sor_shipping" id="sor_shipping"
                                value="<?php
                                if (isset($dataExisted) && isset($row['act_shipping_fee']) && !isset($sor_shipping)) {
                                    echo $row['act_shipping_fee'];
                                } else if (isset($sor_shipping)) {
                                    echo $sor_shipping;
                                } else {
                                    echo '0';
                                }
                                ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                            <?php if (isset($shipping_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $shipping_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="form-group mb-4">
                    <h3>
                        Commission Fees
                    </h3>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_serv_lbl" for="sor_serv">Service Fee
                                (incl. GST)</label>
                            <input class="form-control" type="number" step="0.01" name="sor_serv" id="sor_serv" value="<?php
                            if (isset($dataExisted) && isset($row['service_fee']) && !isset($sor_serv)) {
                                echo $row['service_fee'];
                            } else if (isset($sor_serv)) {
                                echo $sor_serv;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($service_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $service_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_trans_lbl" for="sor_trans">Transaction Fee
                                (incl. GST)</label>
                            <input class="form-control" type="number" step="0.01" name="sor_trans" id="sor_trans" value="<?php
                            if (isset($dataExisted) && isset($row['trans_fee']) && !isset($sor_trans)) {
                                echo $row['trans_fee'];
                            } else if (isset($sor_trans)) {
                                echo $sor_trans;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($trans_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $trans_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_ams_lbl" for="sor_ams">AMS Commission
                                Fee</label>
                            <input class="form-control" type="number" step="0.01" name="sor_ams" id="sor_ams" value="<?php
                            if (isset($dataExisted) && isset($row['ams_fee']) && !isset($sor_ams)) {
                                echo $row['ams_fee'];
                            } else if (isset($sor_ams)) {
                                echo $sor_ams;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($ams_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $ams_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>

                    </div>
                </div>
                <hr />
                <div class="form-group mb-3">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_fees_lbl" for="sor_fees">Charges &
                                Fees</label>
                            <input class="form-control" type="number" step="0.01" name="sor_fees" id="sor_fees" value="<?php
                            if (isset($dataExisted) && isset($row['fees']) && !isset($sor_fees)) {
                                echo $row['fees'];
                            } else if (isset($sor_fees)) {
                                echo $sor_fees;
                            } else {
                                echo '0';
                            }
                            ?>" readonly>
                            <?php if (isset($fees_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $fees_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_final_lbl" for="sor_final">Final
                                Amount</label>
                            <input class="form-control" type="number" step="0.01" name="sor_final" id="sor_final" value="<?php
                            if (isset($dataExisted) && isset($row['final_amt']) && !isset($sor_final)) {
                                echo $row['final_amt'];
                            } else if (isset($sor_final)) {
                                echo $sor_final;
                            } else {
                                echo '0';
                            }
                            ?>">
                            <?php if (isset($final_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $final_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label form_lbl" id="sor_remark_lbl" for="sor_remark">Remark</label>
                    <textarea class="form-control" name="sor_remark" id="sor_remark" rows="3" <?php if ($act == '')
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
                           
                            if (isset($row['orderID']))
                            $echoVal = $row['orderID'];
                            $echoVal2 = '';
                            $courier_rst2 = getData('courier_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);

                            $courier_row2 = $courier_rst2 ? $courier_rst2->fetch_assoc() : [];
                            if (!empty($courier_row2['courier_id']))
                            $echoVal2 = $courier_row2['courier_id'];
                       
                            $courier_rst = getData('name', "id = '$echoVal2'", '', COURIER, $connect);
                            $courier_row = $courier_rst ? $courier_rst->fetch_assoc() : [];
                      
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
                            $tracking_row = $tracking_rst ? $tracking_rst->fetch_assoc() : [];
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
                            $track_row = $tracking_rst2 ? $tracking_rst2->fetch_assoc() : [];
                      
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
                <?php if ($act !== 'I' && (!empty($transitionHistoryRows) || !empty($editHistoryRows))) { ?>
                <div class="form-group mb-4">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h3>Status Transition History</h3>
                            <?php if (!empty($transitionHistoryRows)) { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date Time</th>
                                                <th>Transition</th>
                                                <th>Action By</th>
                                                <th>Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transitionHistoryRows as $historyRow) { ?>
                                                <?php
                                                $historyUserDisplayName = commonResolveUserDisplayName(
                                                    $connect,
                                                    isset($historyRow['user_id']) ? (string) $historyRow['user_id'] : 'SYSTEM'
                                                );
                                                if (trim((string) $historyUserDisplayName) === '') {
                                                    $historyUserDisplayName = 'SYSTEM';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) (isset($historyRow['transition_at']) ? $historyRow['transition_at'] : '')) ?></td>
                                                    <td><?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($historyRow['from_status']) ? $historyRow['from_status'] : '')) ?> -> <?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($historyRow['to_status']) ? $historyRow['to_status'] : '')) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span><?= htmlspecialchars((string) $historyUserDisplayName) ?></span>
                                                            <?= shopeeOmsRenderUserGroupBadge($connect, isset($historyRow['user_group_id']) ? (int) $historyRow['user_group_id'] : 0) ?>
                                                        </div>
                                                    </td>
                                                    <td><?= nl2br(htmlspecialchars((string) (isset($historyRow['remark']) ? $historyRow['remark'] : ''))) ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-light border">No transition history found.</div>
                            <?php } ?>
                        </div>
                        <div class="col-12">
                            <h3>Modified Order History</h3>
                            <?php if (!empty($editHistoryRows)) { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date Time</th>
                                                <th>Field</th>
                                                <th>Updated By</th>
                                                <th>Change</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($editHistoryRows as $historyRow) { ?>
                                                <?php
                                                $editHistoryUserDisplayName = commonResolveUserDisplayName(
                                                    $connect,
                                                    isset($historyRow['user_id']) ? (string) $historyRow['user_id'] : 'SYSTEM'
                                                );
                                                if (trim((string) $editHistoryUserDisplayName) === '') {
                                                    $editHistoryUserDisplayName = 'SYSTEM';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) (isset($historyRow['change_at']) ? $historyRow['change_at'] : '')) ?></td>
                                                    <td><?= htmlspecialchars((string) (isset($historyRow['field_label']) && trim((string) $historyRow['field_label']) !== '' ? $historyRow['field_label'] : $historyRow['field_name'])) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span><?= htmlspecialchars((string) $editHistoryUserDisplayName) ?></span>
                                                            <?= shopeeOmsRenderUserGroupBadge($connect, isset($historyRow['user_group_id']) ? (int) $historyRow['user_group_id'] : 0) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="text-decoration: line-through; color: #9b1c1c;"><?= nl2br(htmlspecialchars((string) (isset($historyRow['old_value']) ? $historyRow['old_value'] : ''))) ?></div>
                                                        <div style="color: #198754; font-weight: 600;"><?= nl2br(htmlspecialchars((string) (isset($historyRow['new_value']) ? $historyRow['new_value'] : ''))) ?></div>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-light border">No modified order history found.</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php
                        if (isset($row['order_status'])) {
                            $statusCode = shopeeOmsNormalizeStatusCode($row['order_status']);
                            $canMoveToPack = shopeeOmsHasTransitionPermission($connect, $statusCode, 'TP', USER_GROUP, $row, USER_ID);
                            $canConfirmReceive = shopeeOmsHasTransitionPermission($connect, $statusCode, 'PR', USER_GROUP, $row, USER_ID);
                            $canVerify = shopeeOmsHasTransitionPermission($connect, $statusCode, 'V', USER_GROUP, $row, USER_ID);
                            $canComplete = shopeeOmsHasTransitionPermission($connect, $statusCode, 'C', USER_GROUP, $row, USER_ID);
                            $canReturn = shopeeOmsHasTransitionPermission($connect, $statusCode, 'CR', USER_GROUP, $row, USER_ID);

                            if ($statusCode === 'P' && $canMoveToPack) {
                                echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="TP" formnovalidate>MOVE TO TO PACK</button>';
                            } else if ($statusCode === 'WR' && $canConfirmReceive) {
                                echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="PR" formnovalidate>CONFIRM PARCEL RECEIVED</button>';
                            } else if ($statusCode === 'WAFC' && $canVerify) {
                                echo '<button class="btn btn-lg btn-rounded btn-success mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="V" formnovalidate>MOVE TO VERIFY</button>';
                            } else if ($statusCode === 'V' && $canComplete) {
                                echo '<button class="btn btn-lg btn-rounded btn-success mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="C" formnovalidate>FINALIZE COMPLETE</button>';
                            }

                            if ($statusCode === 'R' && $canReturn) {
                                echo '<button type="button" class="btn btn-lg btn-rounded btn-warning mx-2 mb-2 p-2" onclick="submitReturnAction(\'restock\')">RETURN RESTOCK</button>';
                                echo '<button type="button" class="btn btn-lg btn-rounded btn-danger mx-2 mb-2 p-2" onclick="submitReturnAction(\'damaged\')">RETURN DAMAGED</button>';
                            }
                        }
                        
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    ?>
                    <input type="hidden" name="return_type" id="return_type" value="">
                    <input type="hidden" name="return_remark" id="return_remark" value="">
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="actionBtn" id="actionBtn" formnovalidate
                        value="back">Back</button>
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
        if ($sorLocalTelegramFailureMessage !== '') {
            echo '<script>alert(' . json_encode($sorLocalTelegramFailureMessage) . ');</script>';
        }
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
    }
    ?>
    <script>
        function toggleAirbillFields() {
            var updateAirbill = document.getElementById('sor_update_airbill');
            var updateAirbillToggle = document.getElementById('sor_update_airbill_toggle');
            var airbillNo = document.getElementById('sor_airbill');
            var airbillAttachment = document.getElementById('sor_airbill_attachment');
            var customerAddress = document.getElementById('sor_customer_address');
            var existingAttachment = document.getElementById('sor_airbill_attachment_value');
            if (!updateAirbill || !updateAirbillToggle || !airbillNo || !airbillAttachment || !customerAddress) {
                return;
            }

            updateAirbill.value = updateAirbillToggle.checked ? 'yes' : 'no';
            var enabled = updateAirbillToggle.checked;
            var readOnlyMode = "<?= $act ?>" === '';
            airbillNo.disabled = readOnlyMode || !enabled;
            airbillAttachment.disabled = readOnlyMode || !enabled;
            customerAddress.disabled = readOnlyMode || !enabled;
            airbillNo.required = enabled;
            customerAddress.required = enabled;
            airbillAttachment.required = enabled && (!existingAttachment || existingAttachment.value.trim() === '');
        }

        function submitReturnAction(returnType) {
            var form = document.getElementById('FORForm');
            var returnTypeField = document.getElementById('return_type');
            var returnRemarkField = document.getElementById('return_remark');
            if (!form || !returnTypeField || !returnRemarkField) {
                return;
            }

            var remark = window.prompt('Return remark (' + returnType + '):', '');
            if (remark === null) {
                return;
            }

            returnTypeField.value = returnType;
            returnRemarkField.value = remark;
            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'returnActionBtn';
            actionInput.value = '1';
            form.appendChild(actionInput);
            form.submit();
        }

        <?= shopeeOmsRenderAirbillPdfAutofillScript() ?>

        document.addEventListener('DOMContentLoaded', function () {
            toggleAirbillFields();
            if (window.shopeeOmsAirbillPdfAutofill) {
                window.shopeeOmsAirbillPdfAutofill.bind({
                    fileInputSelector: '#sor_airbill_attachment',
                    airbillNoSelector: '#sor_airbill',
                    customerAddressSelector: '#sor_customer_address',
                    statusSelector: '#sor_airbill_extract_status',
                    workerSrc: '../finance/header/js/pdf.worker.min.js',
                    errorClass: 'is-error'
                });
            }
            var updateAirbillToggle = document.getElementById('sor_update_airbill_toggle');
            if (updateAirbillToggle) {
                updateAirbillToggle.addEventListener('change', toggleAirbillFields);
            }

                var newCustomerForm = document.getElementById('myForm');
                if (newCustomerForm) {
                    var outerOrderForm = document.getElementById('FORForm');
                    var newCustomerSubmitBtn = document.getElementById('new_customer_submit_btn');
                    var newCustomerFields = newCustomerForm.querySelectorAll('[data-new-customer-required="1"]');
                    var newCustomerLookupFields = [
                        { textId: 'scr_pic', hiddenId: 'scr_pic_hidden', label: 'Sales Person In Charge' },
                        { textId: 'scr_country', hiddenId: 'scr_country_hidden', label: 'Country' },
                        { textId: 'scr_brand', hiddenId: 'scr_brand_hidden', label: 'Brand' },
                        { textId: 'scr_series', hiddenId: 'scr_series_hidden', label: 'Series' }
                    ];

                function clearNewCustomerInlineError(field) {
                    if (!field) return;
                    field.classList.remove('shopee-inline-invalid');
                    var wrapper = field.parentElement;
                    if (!wrapper) return;
                    wrapper.querySelectorAll('.shopee-inline-error').forEach(function (node) {
                        node.remove();
                    });
                }

                function showNewCustomerInlineError(field, message) {
                    if (!field) return;
                    clearNewCustomerInlineError(field);
                    field.classList.add('shopee-inline-invalid');
                    var errorNode = document.createElement('small');
                    errorNode.className = 'shopee-inline-error';
                    errorNode.textContent = message;
                    field.parentElement.appendChild(errorNode);
                }

                function validateNewCustomerForm() {
                    var firstInvalidField = null;
                    newCustomerFields.forEach(function (field) {
                        clearNewCustomerInlineError(field);
                        if (field.disabled) {
                            return;
                        }

                        if (field.value.trim() === '') {
                            showNewCustomerInlineError(field, 'This field is required.');
                            if (!firstInvalidField) {
                                firstInvalidField = field;
                            }
                        }
                    });

                    newCustomerLookupFields.forEach(function (config) {
                        var textField = document.getElementById(config.textId);
                        var hiddenField = document.getElementById(config.hiddenId);
                        if (!textField || !hiddenField || textField.disabled) {
                            return;
                        }

                        if (textField.value.trim() !== '' && hiddenField.value.trim() === '') {
                            showNewCustomerInlineError(textField, config.label + ' must be selected from the suggestion list.');
                            if (!firstInvalidField) {
                                firstInvalidField = textField;
                            }
                        }
                    });

                    if (firstInvalidField) {
                        firstInvalidField.focus();
                        return false;
                    }

                    return true;
                }

                newCustomerFields.forEach(function (field) {
                    field.addEventListener('input', function () {
                        if (field.value.trim() !== '') {
                            clearNewCustomerInlineError(field);
                        }
                    });
                });

                newCustomerLookupFields.forEach(function (config) {
                    var textField = document.getElementById(config.textId);
                    var hiddenField = document.getElementById(config.hiddenId);
                    if (!textField || !hiddenField) {
                        return;
                    }

                    textField.addEventListener('input', function () {
                        hiddenField.value = '';
                    });
                });

                function submitNewCustomerForm() {
                    if (!validateNewCustomerForm()) {
                        return;
                    }
                    if (!outerOrderForm) {
                        return;
                    }
                    var existingSubmitMarker = outerOrderForm.querySelector('input[data-new-customer-submit="1"]');
                    if (existingSubmitMarker) {
                        existingSubmitMarker.remove();
                    }
                    var submitMarker = document.createElement('input');
                    submitMarker.type = 'hidden';
                    submitMarker.name = 'submit';
                    submitMarker.value = 'Submit';
                    submitMarker.setAttribute('data-new-customer-submit', '1');
                    outerOrderForm.appendChild(submitMarker);
                    HTMLFormElement.prototype.submit.call(outerOrderForm);
                }

                if (newCustomerSubmitBtn) {
                    newCustomerSubmitBtn.addEventListener('click', submitNewCustomerForm);
                }
            }
        });


        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        <?php
        include "../js/shopee_order_req.js"
            ?>
    </script>

</body>

</html>
