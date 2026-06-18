<?php
$currentPagePin = 69;
$pageTitle = "Facebook Order Request";

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = FB_ORDER_REQ;

$dataID = input('id');
$act = input('act');
$pageAction = getPageAction($act);
$allowed_ext = array("png", "jpg", "jpeg", "svg", "pdf");


$redirect_page = $SITEURL . '/finance/fb_order_req_table.php';
$back_redirect_page = commonResolveBackUrl($redirect_page);
$redirectLink = '<script>location.href=' . json_encode($redirect_page) . ';</script>';
$clearLocalStorage = '<script>localStorage.clear();</script>';
$pendingStatusUpdate = shopeeOmsNormalizeStatusCode(post('updateStatusBtn'));
$forShouldSaveBeforeStatusUpdate = $pendingStatusUpdate !== '' && $act === 'E';
$forTriggerStatusTransitionAfterSave = false;
$forHandleStatusTransition = function ($newStatus) use ($connect, $finance_connect, $dataID, $pageTitle, $cdate, $ctime, $tblName, $redirect_page) {
    $newStatus = shopeeOmsNormalizeStatusCode($newStatus);
    $transitionRemark = 'Order Status Update to ' . shopeeOmsGetStatusLabel($newStatus);
    $transitionResult = shopeeOmsExecuteTransition($connect, $finance_connect, (int) $dataID, $newStatus, array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => $transitionRemark,
        'platform' => 'facebook',
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
            'act_msg' => USER_NAME . " updated Facebook order #" . (int) $dataID . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
        ));
        echo '<script>alert(' . json_encode((string) $transitionRemark) . '); window.location.replace(' . json_encode((string) $redirect_page) . ');</script>';
        exit;
    }

    echo '<script>alert(' . json_encode((string) (isset($transitionResult['message']) ? $transitionResult['message'] : 'Unable to update order status.')) . ');</script>';
    exit;
};

$img_path = '../' . img_server . 'finance/fb_order_req/';
if (!file_exists($img_path)) {
    mkdir($img_path, 0777, true);
}
$forStatusOptions = shopeeOmsGetEditableStatusOptions();
$forWarehouseRows = shopeeOmsLoadActiveWarehouses($connect);
$forDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect, $forWarehouseRows);
$forWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect, true);
$forWarehouseOptionMap = array();
foreach ($forWarehouseRows as $forWarehouseRow) {
    $forWarehouseId = isset($forWarehouseRow['id']) ? (int) $forWarehouseRow['id'] : 0;
    if ($forWarehouseId > 0) {
        $forWarehouseOptionMap[$forWarehouseId] = isset($forWarehouseRow['name']) ? (string) $forWarehouseRow['name'] : ('Warehouse #' . $forWarehouseId);
    }
}

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

if ($pendingStatusUpdate !== '' && !$forShouldSaveBeforeStatusUpdate) {
    $forHandleStatusTransition($pendingStatusUpdate);
}

if (!($dataID) && !($act)) {
    renderNotificationScript('Invalid action.', 'error', $redirect_page);

}

if (post('actionBtn') || $forShouldSaveBeforeStatusUpdate) {
    $action = post('actionBtn');
    if ($action === '' && $forShouldSaveBeforeStatusUpdate) {
        $action = 'updRecord';
    }

    $for_name = postSpaceFilter('for_name');
    $for_link = postSpaceFilter('for_link');
    $for_ctc = postSpaceFilter('for_contact');
    $for_pic = postSpaceFilter('for_pic_hidden');
    $for_country = postSpaceFilter('for_country_hidden');
    $for_brand = postSpaceFilter('for_brand_hidden');
    $for_series = postSpaceFilter('for_series_hidden');
    $for_pkg = postSpaceFilter('for_pkg_hidden');
    $for_fbpage = postSpaceFilter('for_fbpage_hidden');
    $for_channel = postSpaceFilter('for_channel_hidden');
    $for_price = postSpaceFilter('for_price');
    $for_pay = postSpaceFilter('for_pay_meth_hidden');
    $for_rec_name = postSpaceFilter('for_rec_name');
    $for_rec_ctc = postSpaceFilter('for_rec_ctc');
    $for_rec_add = postSpaceFilter('for_rec_add');
    $for_remark = postSpaceFilter('for_remark');
    $for_order_status = shopeeOmsNormalizeStatusCode(postSpaceFilter('for_order_status'));
    if ($for_order_status === '') {
        $for_order_status = isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P';
    }
    $forCurrentEffectiveWarehouseId = isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $forDefaultWarehouseId) : $forDefaultWarehouseId;
    $for_stock_out_warehouse_id = shopeeOmsNormalizeWarehouseId(postSpaceFilter('for_stock_out_warehouse_id'));
    if ($for_stock_out_warehouse_id <= 0) {
        $for_stock_out_warehouse_id = $forDefaultWarehouseId;
    }
    $forStockOutWarehouseEditable = $action === 'addRecord'
        ? true
        : shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : '');
    if (!$forStockOutWarehouseEditable && $action === 'updRecord') {
        $for_stock_out_warehouse_id = $forCurrentEffectiveWarehouseId;
    }
    $for_update_airbill = strtolower(trim((string) postSpaceFilter('for_update_airbill')));
    if ($for_update_airbill === '') {
        $for_update_airbill = 'yes';
    }
    $for_airbill_no = postSpaceFilter('for_airbill_no');
    $for_airbill_attachment = postSpaceFilter('for_airbill_attachment_value');
    $for_order_status_sql = mysqli_real_escape_string($finance_connect, $for_order_status);
    $for_airbill_no_sql = mysqli_real_escape_string($finance_connect, $for_airbill_no);

    $for_attach = null;
    if (isset($_FILES["for_attach"]) && $_FILES["for_attach"]["size"] != 0) {
        $for_attach = $_FILES["for_attach"]["name"];
    } elseif (isset($_POST['existing_attachment'])) {
        $for_attach = $_POST['existing_attachment'];
    }

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':
            $error = 0;

            if ($_FILES["for_attach"]["size"] != 0) {
                // move file
                $for_file_name = $_FILES["for_attach"]["name"];
                $for_file_tmp_name = $_FILES["for_attach"]["tmp_name"];
                $img_ext = pathinfo($for_file_name, PATHINFO_EXTENSION);
                $img_ext_lc = strtolower($img_ext);

                if (in_array($img_ext_lc, $allowed_ext)) {
                    // Get the original file name without the extension
                    $base_name = pathinfo($for_file_name, PATHINFO_FILENAME);
                    $highestNumber = 0;
                    
                    // Check if files with this exact name already exist
                    $files = glob($img_path . $base_name . '_*.' . $img_ext);
                    foreach ($files as $file) {
                        $filename = basename($file);
                        if (preg_match('/^' . preg_quote($base_name, '/') . '_(\d+)\.' . preg_quote($img_ext, '/') . '$/', $filename, $matches)) {
                            $number = (int) $matches[1];
                            $highestNumber = max($highestNumber, $number);
                        }
                    }

                    // Use the original name, but append _1, _2 etc. only if it already exists
                    if (file_exists($img_path . $for_file_name) || $highestNumber > 0) {
                        $unique_id = $highestNumber + 1;
                        $new_file_name = $base_name . '_' . $unique_id . '.' . $img_ext_lc;
                    } else {
                        $new_file_name = $for_file_name;
                    }

                    // Move the uploaded file
                    if (move_uploaded_file($for_file_tmp_name, $img_path . $new_file_name)) {
                        $for_attach = $new_file_name; // Update $for_attach with the new filename
                    } else {
                        $err2 = "Failed to upload the file.";
                    }
                } else
                    $err2 = "Only allow PNG, JPG, JPEG, SVG or PDF file";
            }

            if ($for_update_airbill === 'yes' && isset($_FILES['for_airbill_attachment']) && isset($_FILES['for_airbill_attachment']['size']) && (int) $_FILES['for_airbill_attachment']['size'] > 0) {
                $forAirbillUploadResult = shopeeOmsStoreAirbillAttachmentUpload(
                    $_FILES['for_airbill_attachment'],
                    $connect,
                    $for_brand,
                    $for_pkg,
                    'fb_order_req'
                );
                if (!empty($forAirbillUploadResult['success'])) {
                    $for_airbill_attachment = isset($forAirbillUploadResult['path']) ? (string) $forAirbillUploadResult['path'] : '';
                } else {
                    $airbill_attachment_err = isset($forAirbillUploadResult['message']) ? (string) $forAirbillUploadResult['message'] : 'Failed to upload the airbill attachment.';
                    $error = 1;
                }
            }

            if ($for_update_airbill !== 'yes') {
                if ($action === 'updRecord') {
                    $for_airbill_no = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                    $for_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                } else {
                    $for_airbill_no = '';
                    $for_airbill_attachment = '';
                }
            }

            if (!$for_name) {
                $name_err = "Name cannot be empty.";
                break;
            } else if (!$for_link) {
                $link_err = "Facebook Link cannot be empty.";
                break;
            } else if (!$for_ctc) {
                $contact_err = "Contact cannot be empty.";
                break;
            } else if (!$for_pic && $for_pic < 1) {
                $pic_err = "Sales Person-In-Charge cannot be empty.";
                break;
            } else if (!$for_country && $for_country < 1) {
                $country_err = "Country cannot be empty.";
                break;
            } else if (!$for_brand && $for_brand < 1) {
                $brand_err = "Brand cannot be empty.";
                break;
            } else if (!$for_series && $for_series < 1) {
                $series_err = "Series cannot be empty.";
                break;
            } else if (!$for_pkg && $for_pkg < 1) {
                $pkg_err = "Package cannot be empty.";
                break;
            } else if (!$for_fbpage && $for_fbpage < 1) {
                $fbpage_err = "Facebook Page cannot be empty.";
                break;
            } else if (!$for_channel && $for_channel < 1) {
                $channel_err = "Channel cannot be empty.";
                break;
            } else if (!$for_price) {
                $price_err = "Price cannot be empty.";
                break;
            } else if (!$for_pay && $for_pay < 1) {
                $pay_err = "Payment Method cannot be empty.";
                break;
            } else if (!$for_rec_name) {
                $rec_name_err = "Receiver Name cannot be empty.";
                break;
            } else if (!$for_rec_ctc) {
                $rec_ctc_err = "Receiver Contact cannot be empty.";
                break;
            } else if (!$for_rec_add) {
                $rec_add_err = "Receiver Address cannot be empty.";
                break;
            } else if (!$for_attach) {
                $desc_err = "Attachment cannot be empty.";
                break;
            }

            if ($forStockOutWarehouseEditable) {
                if ($for_stock_out_warehouse_id <= 0) {
                    $stock_out_warehouse_err = "Stock Out Warehouse is required.";
                    $error = 1;
                } else if (!isset($forWarehouseOptionMap[$for_stock_out_warehouse_id])) {
                    $stock_out_warehouse_err = "Please select a valid active Stock Out Warehouse.";
                    $error = 1;
                }
            }

            $forEffectiveAirbill = $for_airbill_no;
            if ($action === 'updRecord' && $for_update_airbill !== 'yes') {
                $forEffectiveAirbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
            }

            $forStatusValidation = shopeeOmsValidateInitialStatusAndAirbill($for_order_status, $forEffectiveAirbill);
            if (!$forStatusValidation['valid']) {
                $airbill_err = isset($forStatusValidation['message']) ? (string) $forStatusValidation['message'] : 'Invalid order status or airbill.';
                $error = 1;
            }

            if ($for_update_airbill === 'yes') {
                if (trim((string) $for_airbill_no) === '') {
                    $airbill_err = 'Airbill No cannot be empty when Update Airbill is enabled.';
                    $error = 1;
                }
                if (trim((string) $for_airbill_attachment) === '') {
                    $airbill_attachment_err = 'Airbill Attachment cannot be empty when Update Airbill is enabled.';
                    $error = 1;
                }
            }

            if ($error) {
                break;
            }

            if ($action == 'addRecord') {
                try {
                    //check values
                    if ($for_name) {
                        array_push($newvalarr, $for_name);
                        array_push($datafield, 'name');
                    }
                    if ($for_link) {
                        array_push($newvalarr, $for_link);
                        array_push($datafield, 'facebook link');
                    }

                    if ($for_ctc) {
                        array_push($newvalarr, $for_ctc);
                        array_push($datafield, 'contact');
                    }

                    if ($for_pic) {
                        array_push($newvalarr, $for_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($for_country) {
                        array_push($newvalarr, $for_country);
                        array_push($datafield, 'country');
                    }

                    if ($for_brand) {
                        array_push($newvalarr, $for_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($for_series) {
                        array_push($newvalarr, $for_series);
                        array_push($datafield, 'series');
                    }

                    if ($for_pkg) {
                        array_push($newvalarr, $for_pkg);
                        array_push($datafield, 'package');
                    }

                    if ($for_fbpage) {
                        array_push($newvalarr, $for_fbpage);
                        array_push($datafield, 'fb page');
                    }

                    if ($for_channel) {
                        array_push($newvalarr, $for_channel);
                        array_push($datafield, 'channel');
                    }

                    if ($for_price) {
                        array_push($newvalarr, $for_price);
                        array_push($datafield, 'price');
                    }

                    if ($for_pay) {
                        array_push($newvalarr, $for_pay);
                        array_push($datafield, 'payment method');
                    }

                    if ($for_rec_name) {
                        array_push($newvalarr, $for_rec_name);
                        array_push($datafield, 'receiver name');
                    }

                    if ($for_rec_ctc) {
                        array_push($newvalarr, $for_rec_ctc);
                        array_push($datafield, 'receiver contact');
                    }

                    if ($for_rec_add) {
                        array_push($newvalarr, $for_rec_add);
                        array_push($datafield, 'receiver address');
                    }

                    if ($for_attach) {
                        array_push($newvalarr, $for_attach);
                        array_push($datafield, 'attachment');
                    }

                    if ($for_remark) {
                        array_push($newvalarr, $for_remark);
                        array_push($datafield, 'remark');
                    }
                    if ($for_order_status) {
                        array_push($newvalarr, $for_order_status);
                        array_push($datafield, 'order_status');
                    }
                    if ($for_stock_out_warehouse_id > 0) {
                        array_push($newvalarr, isset($forWarehouseOptionMap[$for_stock_out_warehouse_id]) ? $forWarehouseOptionMap[$for_stock_out_warehouse_id] : ('Warehouse #' . $for_stock_out_warehouse_id));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }
                    if ($for_airbill_no !== '') {
                        array_push($newvalarr, $for_airbill_no);
                        array_push($datafield, 'airbill_no');
                    }
                    if ($for_airbill_attachment !== '') {
                        array_push($newvalarr, $for_airbill_attachment);
                        array_push($datafield, 'airbill_attachment');
                    }

                    $tblName2 = FB_CUST_DEALS;
                    $query = "INSERT INTO " . $tblName . " (name,fb_link,contact,sales_pic,country,brand,series,package,fb_page,channel,price,pay_method,ship_rec_name,ship_rec_add,ship_rec_contact,remark,attachment,order_status,stock_out_warehouse_id,airbill_no,airbill_attachment,create_by,create_date,create_time) VALUES ('$for_name','$for_link','$for_ctc','$for_pic','$for_country','$for_brand','$for_series','$for_pkg','$for_fbpage','$for_channel','$for_price','$for_pay','$for_rec_name','$for_rec_add','$for_rec_ctc','$for_remark','$for_attach','$for_order_status_sql'," . ($for_stock_out_warehouse_id > 0 ? $for_stock_out_warehouse_id : 'NULL') . ",'$for_airbill_no_sql','" . mysqli_real_escape_string($finance_connect, $for_airbill_attachment) . "','" . USER_ID . "',curdate(),curtime())";
                   
                    $result2 = getData('*', "name = '$for_name' AND fb_link = '$for_link'", '', $tblName2, $connect);
                    
                    if($result2->num_rows == 0){
                        $query2 = "INSERT INTO " . $tblName2 . " (name, fb_link, contact, sales_pic, country, brand, fb_page, channel, series,ship_rec_name, ship_rec_add, ship_rec_contact, remark, create_by, create_date,create_time)  VALUES ('$for_name','$for_link','$for_ctc','$for_pic','$for_country','$for_brand','$for_fbpage','$for_channel','$for_series','$for_rec_name','$for_rec_add','$for_rec_ctc','$for_remark','" . USER_ID . "',curdate(),curtime())";
                        $returnData2 = mysqli_query($connect, $query2);
                    }
                    // Execute the query
                    $returnData = mysqli_query($finance_connect, $query);
                    if ($returnData) {
                        $dataID = (int) mysqli_insert_id($finance_connect);
                        if ($for_order_status === 'TP' && $dataID > 0) {
                            $freshOrderRow = shopeeOmsLoadOrder($finance_connect, $dataID, 'facebook');
                            $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $freshOrderRow, USER_ID, 'facebook');
                            if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                                $notifyResult = shopeeOmsSendWarehouseNotification($connect, $finance_connect, $tokenResult['token_row'], $tokenResult['notification'], $pageTitle);
                                if (!empty($notifyResult['sent']) && shopeeOmsTableHasColumn($finance_connect, dbFinance, $tblName, 'step_a_sent_at')) {
                                    mysqli_query($finance_connect, "UPDATE `" . $tblName . "` SET `step_a_sent_at` = NOW() WHERE id = " . $dataID . " LIMIT 1");
                                }
                            }
                        }
                    }
                    
                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $rst->fetch_assoc();

                    // check value
                    if ($row['name'] != $for_name) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $for_name);
                        array_push($datafield, 'name');
                    }

                    if ($row['fb_link'] != $for_link) {
                        array_push($oldvalarr, $row['fb_link']);
                        array_push($chgvalarr, $for_link);
                        array_push($datafield, 'fb link');
                    }

                    if ($row['contact'] != $for_ctc) {
                        array_push($oldvalarr, $row['contact']);
                        array_push($chgvalarr, $for_ctc);
                        array_push($datafield, 'contact');
                    }

                    if ($row['sales_pic'] != $for_pic) {
                        array_push($oldvalarr, $row['sales_pic']);
                        array_push($chgvalarr, $for_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['country'] != $for_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $for_country);
                        array_push($datafield, 'country');
                    }

                    if ($row['brand'] != $for_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $for_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['series'] != $for_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $for_series);
                        array_push($datafield, 'series');
                    }

                    if ($row['package'] != $for_pkg) {
                        array_push($oldvalarr, $row['package']);
                        array_push($chgvalarr, $for_pkg);
                        array_push($datafield, 'package');
                    }

                    if ($row['fb_page'] != $for_fbpage) {
                        array_push($oldvalarr, $row['fb_page']);
                        array_push($chgvalarr, $for_fbpage);
                        array_push($datafield, 'fb_page');
                    }

                    if ($row['channel'] != $for_channel) {
                        array_push($oldvalarr, $row['channel']);
                        array_push($chgvalarr, $for_channel);
                        array_push($datafield, 'channel');
                    }

                    if ($row['price'] != $for_price) {
                        array_push($oldvalarr, $row['price']);
                        array_push($chgvalarr, $for_price);
                        array_push($datafield, 'price');
                    }

                    if ($row['pay_method'] != $for_pay) {
                        array_push($oldvalarr, $row['pay_method']);
                        array_push($chgvalarr, $for_pay);
                        array_push($datafield, 'payment method');
                    }

                    if ($row['ship_rec_name'] != $for_rec_name) {
                        array_push($oldvalarr, $row['ship_rec_name']);
                        array_push($chgvalarr, $for_rec_name);
                        array_push($datafield, 'shipping receiver name');
                    }

                    if ($row['ship_rec_contact'] != $for_rec_ctc) {
                        array_push($oldvalarr, $row['ship_rec_contact']);
                        array_push($chgvalarr, $for_rec_ctc);
                        array_push($datafield, 'shipping receiver contact');
                    }

                    if ($row['ship_rec_add'] != $for_rec_add) {
                        array_push($oldvalarr, $row['ship_rec_add']);
                        array_push($chgvalarr, $for_rec_add);
                        array_push($datafield, 'shipping receiver address');
                    }

                    $for_attach = isset($for_attach) ? $for_attach : '';
                    if (($row['attachment'] != $for_attach) && ($for_attach != '')) {
                        array_push($oldvalarr, $row['attachment']);
                        array_push($chgvalarr, $for_attach);
                        array_push($datafield, 'attachment');
                    }

                    if ($row['remark'] != $for_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $for_remark == '' ? 'Empty Value' : $for_remark);
                        array_push($datafield, 'remark');
                    }
                    if (shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '') !== $for_order_status) {
                        array_push($oldvalarr, isset($row['order_status']) && $row['order_status'] !== '' ? shopeeOmsGetStatusLabel($row['order_status']) : 'Empty Value');
                        array_push($chgvalarr, $for_order_status !== '' ? shopeeOmsGetStatusLabel($for_order_status) : 'Empty Value');
                        array_push($datafield, 'order_status');
                    }
                    if ((int) (isset($row['stock_out_warehouse_id']) ? $row['stock_out_warehouse_id'] : 0) !== (int) $for_stock_out_warehouse_id) {
                        array_push($oldvalarr, shopeeOmsResolveWarehouseNameById($connect, isset($row['stock_out_warehouse_id']) ? $row['stock_out_warehouse_id'] : 0, $forDefaultWarehouseId, $forWarehouseNameMap));
                        array_push($chgvalarr, shopeeOmsResolveWarehouseNameById($connect, $for_stock_out_warehouse_id, $forDefaultWarehouseId, $forWarehouseNameMap));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }
                    if ((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '') !== (string) $for_airbill_no) {
                        array_push($oldvalarr, isset($row['airbill_no']) && $row['airbill_no'] !== '' ? $row['airbill_no'] : 'Empty Value');
                        array_push($chgvalarr, $for_airbill_no !== '' ? $for_airbill_no : 'Empty Value');
                        array_push($datafield, 'airbill_no');
                    }
                    if ((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '') !== (string) $for_airbill_attachment) {
                        array_push($oldvalarr, isset($row['airbill_attachment']) && $row['airbill_attachment'] !== '' ? $row['airbill_attachment'] : 'Empty Value');
                        array_push($chgvalarr, $for_airbill_attachment !== '' ? $for_airbill_attachment : 'Empty Value');
                        array_push($datafield, 'airbill_attachment');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET name = '$for_name', fb_link = '$for_link', contact = '$for_ctc', sales_pic = '$for_pic', country = '$for_country', brand = '$for_brand', series = '$for_series', package = '$for_pkg', fb_page = '$for_fbpage', channel = '$for_channel', price = '$for_price', pay_method = '$for_pay', ship_rec_name = '$for_rec_name', ship_rec_add = '$for_rec_add', ship_rec_contact = '$for_rec_ctc', remark ='$for_remark', attachment ='$for_attach', order_status = '$for_order_status_sql', stock_out_warehouse_id = " . ($for_stock_out_warehouse_id > 0 ? $for_stock_out_warehouse_id : 'NULL') . ", airbill_no = '$for_airbill_no_sql', airbill_attachment = '" . mysqli_real_escape_string($finance_connect, $for_airbill_attachment) . "', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
                        $returnData = mysqli_query($finance_connect, $query);

                        // --- FIX: Delete the old attachment from the folder ---
                        if ($returnData && isset($row['attachment']) && $row['attachment'] != '' && $row['attachment'] != $for_attach) {
                            $old_file_path = $img_path . $row['attachment'];
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path); // Physically removes the file from the server
                            }
                        }

                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            if ($action === 'updRecord' && $forShouldSaveBeforeStatusUpdate && (($act === 'NC') || !empty($returnData))) {
                $forTriggerStatusTransitionAfterSave = true;
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

            if ($action === 'updRecord' && $forShouldSaveBeforeStatusUpdate) {
                if ($forTriggerStatusTransitionAfterSave) {
                    $forHandleStatusTransition($pendingStatusUpdate);
                }

                $forSaveErrorMessage = trim((string) $errorMsg) !== '' ? trim((string) $errorMsg) : 'Unable to save edited order details.';
                echo '<script>alert(' . json_encode($forSaveErrorMessage) . ');</script>';
                exit;
            }

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
            deleteRecord($tblName, '', $dataID, $for_name, $finance_connect, $connect, $cdate, $ctime, $pageTitle);
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
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
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
$urbanismBadgeSeedId = '';
$urbanismFbLink = '';

if (isset($row['name']) && trim((string) $row['name']) !== '') {
    $urbanismBadgeSeedName = trim((string) $row['name']);
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('for_name') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('for_name'));
}

if (isset($row['fb_link']) && trim((string) $row['fb_link']) !== '') {
    $urbanismFbLink = trim((string) $row['fb_link']);
}
if ($urbanismFbLink === '' && postSpaceFilter('for_link') !== '') {
    $urbanismFbLink = trim((string) postSpaceFilter('for_link'));
}

if ($urbanismBadgeSeedName !== '' && $urbanismFbLink !== '') {
    $safeFbName = mysqli_real_escape_string($connect, $urbanismBadgeSeedName);
    $safeFbLink = mysqli_real_escape_string($connect, $urbanismFbLink);
    $dealRst = getData('id', "name='" . $safeFbName . "' AND fb_link='" . $safeFbLink . "'", 'LIMIT 1', FB_CUST_DEALS, $connect);
    if ($dealRst && $dealRst->num_rows > 0) {
        $dealRow = $dealRst->fetch_assoc();
        $urbanismBadgeSeedId = (string) ((int) $dealRow['id']);
    }
}

$urbanismBadgeAction = getUrbanismMemberActionData(
    $connect,
    '',
    $urbanismBadgeSeedName,
    $redirect_page,
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
    
    <!-- <div class="page-load-cover"> -->
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

                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="for_name_lbl" for="for_name">Name<span
                                    class="requireRed">*</span></label>
                            <?php 
                             unset($echoVal);

                             if (isset($row['name']))
                                 $echoVal = $row['name'];
                            ?>
                            <input class="form-control" type="text" name="for_name" id="for_name" value="<?php echo !empty($echoVal) ? $row['name'] : '' ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($name_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $name_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="for_link_lbl" for="for_link">Facebook Link<span
                                    class="requireRed">*</span></label>
                                    <?php 
                             unset($echoVal);

                             if (isset($row['fb_link']))
                                 $echoVal = $row['fb_link'];
                            ?>
                            <input class="form-control" type="text" name="for_link" id="for_link" value="<?php echo !empty($echoVal) ? $row['fb_link'] : '' ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($link_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $link_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="for_contact_lbl" for="for_contact">Contact<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="number" step="0.01" name="for_contact" id="for_contact" value="<?php
                            if (isset($dataExisted) && isset($row['contact']) && !isset($for_contact)) {
                                echo $row['contact'];
                            } else if (isset($for_contact)) {
                                echo $for_contact;
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($contact_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $contact_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>


                    </div>

                </div>
                <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                    <legend class="float-none w-auto p-2">Order Request Details</legend>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_pic_lbl" for="for_pic">Sales Person In
                                    Charge<span class="requireRed">*</span></label>
                                <?php
                                   if(($act == 'E' || $act == '')){
                                unset($echoVal);

                                if (isset($row['sales_pic']))
                                    $echoVal = $row['sales_pic'];

                                if (isset($echoVal)) {
                                    $user_rst = getData('name', "id = '$echoVal'", '', USR_USER, $connect);
                                    if (!$user_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $user_row = ($user_rst && $user_rst->num_rows > 0) ? $user_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_pic" id="for_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? (isset($user_row['name']) ? $user_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_pic_hidden" id="for_pic_hidden"
                                    value="<?php echo (isset($row['sales_pic'])) ? $row['sales_pic'] : ''; ?>">
                                <?php } ?>
                                <?php
                                if(($act == 'I')){
                           
                                    $loggedInUserId = USER_ID; // Assuming USER_ID contains the ID of the logged-in user
                                    $defaultUser = '';
                                
                                    // Retrieve details of the logged-in user
                                    $user_rst = getData('name', "id = '$loggedInUserId'", '', USR_USER, $connect);
                                    if ($user_rst && $user_rst->num_rows > 0) {
                                        $user_row = ($user_rst && $user_rst->num_rows > 0) ? $user_rst->fetch_assoc() : array();
                                        $defaultUser = $user_row['name'];
                                    }
                                    
                                ?>
                                <input class="form-control" type="text" name="for_pic" id="for_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo $defaultUser ?>">
                                <input type="hidden" name="for_pic_hidden" id="for_pic_hidden"
                                    value="<?php echo $loggedInUserId ?>">
                                <?php }?>
                                <?php if (isset($pic_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pic_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_country_lbl" for="for_country">Country<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['country']))
                                    $echoVal = $row['country'];

                                if (isset($echoVal)) {
                                    $country_rst = getData('nicename', "id = '$echoVal'", '', COUNTRIES, $connect);
                                    if (!$country_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $country_row = ($country_rst && $country_rst->num_rows > 0) ? $country_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_country" id="for_country" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($country_row['nicename']) ? $country_row['nicename'] : '') : '' ?>">
                                <input type="hidden" name="for_country_hidden" id="for_country_hidden"
                                    value="<?php echo (isset($row['country'])) ? $row['country'] : ''; ?>">


                                <?php if (isset($country_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $country_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>

                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_brand_lbl" for="for_brand">Brand<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['brand']))
                                    $echoVal = $row['brand'];

                                if (isset($echoVal)) {
                                    $brand_rst = getData('name', "id = '$echoVal'", '', BRAND, $connect);
                                    if (!$brand_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $brand_row = ($brand_rst && $brand_rst->num_rows > 0) ? $brand_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_brand" id="for_brand" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($brand_row['name']) ? $brand_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_brand_hidden" id="for_brand_hidden"
                                    value="<?php echo (isset($row['brand'])) ? $row['brand'] : ''; ?>">


                                <?php if (isset($brand_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $brand_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_series_lbl" for="for_series">Series<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['series']))
                                    $echoVal = $row['series'];

                                if (isset($echoVal)) {
                                    $series_rst = getData('name', "id = '$echoVal'", '', BRD_SERIES, $connect);
                                    if (!$series_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $series_row = ($series_rst && $series_rst->num_rows > 0) ? $series_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_series" id="for_series" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($series_row['name']) ? $series_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_series_hidden" id="for_series_hidden"
                                    value="<?php echo (isset($row['series'])) ? $row['series'] : ''; ?>">


                                <?php if (isset($series_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $series_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_pkg_lbl" for="for_pkg">Package<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['package']))
                                    $echoVal = $row['package'];

                                if (isset($echoVal)) {
                                    $pkg_rst = getData('name', "id = '$echoVal'", '', PKG, $connect);
                                    if (!$pkg_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $pkg_row = ($pkg_rst && $pkg_rst->num_rows > 0) ? $pkg_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_pkg" id="for_pkg" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? (isset($pkg_row['name']) ? $pkg_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_pkg_hidden" id="for_pkg_hidden"
                                    value="<?php echo (isset($row['package'])) ? $row['package'] : ''; ?>">


                                <?php if (isset($pkg_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pkg_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_fb_page_lbl" for="for_fbpage">Facebook
                                    Page<span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['fb_page']))
                                    $echoVal = $row['fb_page'];

                                if (isset($echoVal)) {
                                    $fbpage_rst = getData('name', "id = '$echoVal'", '', FB_PAGE_ACC, $finance_connect);
                                    if (!$fbpage_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $fbpage_row = ($fbpage_rst && $fbpage_rst->num_rows > 0) ? $fbpage_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_fbpage" id="for_fbpage" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($fbpage_row['name']) ? $fbpage_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_fbpage_hidden" id="for_fbpage_hidden"
                                    value="<?php echo (isset($row['fb_page'])) ? $row['fb_page'] : ''; ?>">


                                <?php if (isset($fbpage_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $fbpage_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_channel_lbl" for="for_channel">Channel<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['channel']))
                                    $echoVal = $row['channel'];

                                if (isset($echoVal)) {
                                    $channel_rst = getData('*', "id = '$echoVal'", '', CHANEL_SC_MD, $finance_connect);
                                    if (!$channel_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $channel_row = ($channel_rst && $channel_rst->num_rows > 0) ? $channel_rst->fetch_assoc() : array();
                                }

                                ?>
                                <input class="form-control" type="text" name="for_channel" id="for_channel" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? (isset($channel_row['name']) ? $channel_row['name'] : '') : ''; ?>">
                                <input type="hidden" name="for_channel_hidden" id="for_channel_hidden"
                                value="<?php echo (isset($row['channel'])) ? $row['channel'] : (isset($channel_row) ? $channel_row['id'] : ''); ?>">



                                <?php if (isset($channel_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $channel_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" id="for_price_lbl" for="for_price">Price<span class="requireRed">*</span></label>
                                <?php 
                                unset($echoVal);

                                if (isset($row['price']))
                                    $echoVal = $row['price'];
                                ?>
                                <input class="form-control" type="text" name="for_price" id="for_price" value="<?php echo !empty($echoVal) ? $row['price'] : '' ?>" <?php if ($act == '') echo 'disabled' ?>>
                                <?php if (isset($price_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $price_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_pay_meth_lbl" for="for_pay_meth">Payment
                                    Method<span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['pay_method']))
                                    $echoVal = $row['pay_method'];

                                if (isset($echoVal)) {
                                    $pay_rst = getData('name', "id = '$echoVal'", '', FIN_PAY_METH, $finance_connect);
                                    if (!$pay_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $pay_row = ($pay_rst && $pay_rst->num_rows > 0) ? $pay_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_pay_meth" id="for_pay_meth" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($pay_row['name']) ? $pay_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_pay_meth_hidden" id="for_pay_meth_hidden"
                                    value="<?php echo (isset($row['pay_method'])) ? $row['pay_method'] : ''; ?>">


                                <?php if (isset($pay_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pay_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="for_order_status">Initial Order Status<span class="requireRed">*</span></label>
                                <?php
                                $forCurrentOrderStatusValue = isset($for_order_status) && trim((string) $for_order_status) !== ''
                                    ? $for_order_status
                                    : (isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P');
                                ?>
                                <?php if ($act === 'I') { ?>
                                    <select class="form-select" id="for_order_status" name="for_order_status">
                                        <?php foreach ($forStatusOptions as $statusCode => $statusLabel) { ?>
                                            <option value="<?= htmlspecialchars($statusCode) ?>" <?= $forCurrentOrderStatusValue === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <input class="form-control" type="text" value="<?= htmlspecialchars(shopeeOmsGetStatusLabel($forCurrentOrderStatusValue)) ?>" readonly>
                                    <input type="hidden" id="for_order_status" name="for_order_status" value="<?= htmlspecialchars($forCurrentOrderStatusValue) ?>">
                                <?php } ?>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="for_stock_out_warehouse_id">Stock Out Warehouse<span class="requireRed">*</span></label>
                                <?php
                                $forCurrentStockOutWarehouseId = isset($for_stock_out_warehouse_id) && (int) $for_stock_out_warehouse_id > 0
                                    ? (int) $for_stock_out_warehouse_id
                                    : (isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $forDefaultWarehouseId) : $forDefaultWarehouseId);
                                if ($forCurrentStockOutWarehouseId <= 0 && !empty($forWarehouseRows)) {
                                    $forCurrentStockOutWarehouseId = (int) $forWarehouseRows[0]['id'];
                                }
                                $forCurrentStockOutWarehouseName = shopeeOmsResolveWarehouseNameById($connect, $forCurrentStockOutWarehouseId, $forDefaultWarehouseId, $forWarehouseNameMap);
                                $forIsStockOutWarehouseEditableForForm = $act !== '' && ($act === 'I' || shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : ''));
                                ?>
                                <?php if ($forIsStockOutWarehouseEditableForForm) { ?>
                                    <select class="form-select" id="for_stock_out_warehouse_id" name="for_stock_out_warehouse_id">
                                        <?php foreach ($forWarehouseRows as $forWarehouseRow) { ?>
                                            <?php $forWarehouseId = isset($forWarehouseRow['id']) ? (int) $forWarehouseRow['id'] : 0; ?>
                                            <option value="<?= $forWarehouseId ?>" <?= $forCurrentStockOutWarehouseId === $forWarehouseId ? 'selected' : '' ?>><?= htmlspecialchars((string) $forWarehouseRow['name']) ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <input class="form-control" type="text" value="<?= htmlspecialchars($forCurrentStockOutWarehouseName) ?>" readonly>
                                    <input type="hidden" id="for_stock_out_warehouse_id" name="for_stock_out_warehouse_id" value="<?= (int) $forCurrentStockOutWarehouseId ?>">
                                <?php } ?>
                                <?php if (isset($stock_out_warehouse_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $stock_out_warehouse_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-2 mb-3 shopee-airbill-toggle-col">
                                <?php
                                $forHasSavedAirbillData = false;
                                if (isset($row['airbill_no']) && trim((string) $row['airbill_no']) !== '') {
                                    $forHasSavedAirbillData = true;
                                }
                                if (isset($row['airbill_attachment']) && trim((string) $row['airbill_attachment']) !== '') {
                                    $forHasSavedAirbillData = true;
                                }
                                $forUpdateAirbillValue = isset($for_update_airbill) && trim((string) $for_update_airbill) !== ''
                                    ? strtolower(trim((string) $for_update_airbill))
                                    : ($forHasSavedAirbillData ? 'yes' : ($act === 'I' ? 'yes' : 'no'));
                                if ($forUpdateAirbillValue !== 'yes' && $forHasSavedAirbillData) {
                                    $forUpdateAirbillValue = 'yes';
                                } else if ($forUpdateAirbillValue !== 'yes') {
                                    $forUpdateAirbillValue = 'no';
                                }
                                ?>
                                <input type="hidden" id="for_update_airbill" name="for_update_airbill" value="<?= htmlspecialchars($forUpdateAirbillValue) ?>">
                                <label class="form-label form_lbl shopee-airbill-toggle-label" for="for_update_airbill_toggle">Update Airbill?</label>
                                <div class="shopee-airbill-toggle-field">
                                    <label class="shopee-airbill-toggle mb-0" for="for_update_airbill_toggle">
                                        <input type="checkbox" id="for_update_airbill_toggle" <?= $forUpdateAirbillValue === 'yes' ? 'checked' : '' ?> <?= $act == '' ? 'disabled' : '' ?>>
                                        <span class="shopee-airbill-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="for_airbill_no">Airbill No</label>
                                <input class="form-control" type="text" name="for_airbill_no" id="for_airbill_no" value="<?php
                                if (isset($for_airbill_no)) {
                                    echo htmlspecialchars($for_airbill_no);
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

                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="for_airbill_attachment">Airbill Attachment</label>
                                <input class="form-control" type="file" name="for_airbill_attachment" id="for_airbill_attachment" <?php if ($act == '') echo 'disabled' ?>>
                                <small id="for_airbill_extract_status" class="shopee-airbill-extract-status"></small>
                                <?php
                                $forCurrentAirbillAttachmentValue = isset($for_airbill_attachment) && trim((string) $for_airbill_attachment) !== ''
                                    ? trim((string) $for_airbill_attachment)
                                    : (isset($row['airbill_attachment']) ? trim((string) $row['airbill_attachment']) : '');
                                $forCurrentAirbillAttachmentUrl = $forCurrentAirbillAttachmentValue !== '' ? shopeeOmsBuildAirbillAttachmentUrl($forCurrentAirbillAttachmentValue) : '';
                                $forCurrentAirbillAttachmentExt = $forCurrentAirbillAttachmentUrl !== ''
                                    ? strtolower(pathinfo((string) parse_url($forCurrentAirbillAttachmentUrl, PHP_URL_PATH), PATHINFO_EXTENSION))
                                    : '';
                                ?>
                                <?php if ($forCurrentAirbillAttachmentValue !== '') { ?>
                                    <div class="mt-2 small">
                                        Current Attachment:
                                        <?php if ($forCurrentAirbillAttachmentUrl !== '') { ?>
                                            <a href="<?= htmlspecialchars($forCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= htmlspecialchars($forCurrentAirbillAttachmentValue) ?></a>
                                        <?php } else { ?>
                                            <span><?= htmlspecialchars($forCurrentAirbillAttachmentValue) ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <input type="hidden" name="for_airbill_attachment_value" id="for_airbill_attachment_value" value="<?= htmlspecialchars($forCurrentAirbillAttachmentValue, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($airbill_attachment_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $airbill_attachment_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-6 mb-3 d-flex justify-content-center justify-content-md-end">
                                <?php if ($forCurrentAirbillAttachmentUrl !== '') { ?>
                                    <div id="for_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media">
                                        <?php if (in_array($forCurrentAirbillAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                                            <img src="<?= htmlspecialchars($forCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Airbill Attachment Preview">
                                        <?php } else if ($forCurrentAirbillAttachmentExt === 'pdf') { ?>
                                            <iframe src="<?= htmlspecialchars($forCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" title="Airbill Attachment Preview"></iframe>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div id="for_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media" style="display:none;"></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                    <legend class="float-none w-auto p-2">Shipping Receiver Details</legend>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="for_rec_name_lbl" for="for_rec_name">Receiver
                                    Name<span class="requireRed">*</span></label>
                                    <?php 
                                unset($echoVal);

                                if (isset($row['ship_rec_name']))
                                    $echoVal = $row['ship_rec_name'];
                                ?>
                                <input class="form-control" type="text" name="for_rec_name" id="for_rec_name" value="<?php echo !empty($echoVal) ? $row['ship_rec_name'] : '' ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($rec_name_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $rec_name_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="for_rec_ctc_lbl" for="for_rec_ctc">Receiver
                                    Contact<span class="requireRed">*</span></label>
                                    <?php 
                                unset($echoVal);

                                if (isset($row['ship_rec_contact']))
                                    $echoVal = $row['ship_rec_contact'];
                                ?>
                                <input class="form-control" type="number" name="for_rec_ctc" id="for_rec_ctc" value="<?php echo !empty($echoVal) ? $row['ship_rec_contact'] : '' ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($rec_ctc_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $rec_ctc_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label form_lbl" id="for_rec_add_lbl" for="for_rec_add">Receiver
                                    Address<span class="requireRed">*</span></label>
                                    <?php 
                                unset($echoVal);

                                if (isset($row['ship_rec_add']))
                                    $echoVal = $row['ship_rec_add'];
                                ?>
                                <input class="form-control" type="text" name="for_rec_add" id="for_rec_add" value="<?php echo !empty($echoVal) ? $row['ship_rec_add'] : '' ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($rec_add_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $rec_add_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
                </fieldset>

                <div class="form-group mb-3">
                    <label class="form-label form_lbl" id="for_remark_lbl" for="for_remark">Remark</label>
                    <textarea class="form-control" name="for_remark" id="for_remark" rows="3" <?php if ($act == '')
                        echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                        echo $row['remark'] ?></textarea>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="for_attach_lbl" for="for_attach">Attachment*</label>
                                <input class="form-control" type="file" name="for_attach" id="for_attach" <?php if ($act == '')
                        echo 'disabled' ?>>

                            <?php if (isset($for_attach) && $for_attach) { ?>
                                <div class="text-success mt-1">
                                    <span class="mt-n1">
                                        <?php echo "Uploaded Attachment: " . htmlspecialchars($for_attach); ?>
                                    </span>
                                </div>
                                <input type="hidden" name="existing_attachment"
                                    value="<?php echo htmlspecialchars($for_attach); ?>">
                            <?php } else if (isset($row['attachment']) && $row['attachment']) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo "Current Attachment: " . htmlspecialchars($row['attachment']); ?>
                                    </span>
                                </div>
                                <input type="hidden" name="existing_attachment"
                                    value="<?php echo htmlspecialchars($row['attachment']); ?>">
                            <?php } ?>

                            <?php if (isset($attach_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $attach_err; ?>
                                    </span>
                                </div>
                            <?php } ?>

                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="d-flex justify-content-center justify-content-md-end px-4">
                                <?php
                                    
                                unset($echoVal);
                                $attachmentSrc = '';
                                if (isset($row['attachment']))
                                    $echoVal = $row['attachment'];
                                    if(isset($echoVal)){
                                        
                                    if (isset($for_attach)) {
                                        $attachmentSrc = $img_path . $for_attach;
                                    }else{
                                        $attachmentSrc = $img_path . $echoVal;
                                    }
                                    }else{
                                        $attachmentSrc = '';
                                    }
                               
                                ?>
                                <img id="for_attach_preview" name="for_attach_preview"
                                    src="<?php echo !empty($echoVal) ? $attachmentSrc : '' ?>" class="img-thumbnail" alt="Attachment Preview">
                                <input type="hidden" name="for_attachmentValue" id="for_attachmentValue" value="<?php if (isset($row['attachment']))
                                    echo $row['attachment']; ?>">
                            </div>
                        </div>
                    </div>
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
                           
                            if (isset($row['id'])) {
                                $echoVal = $row['id'];
                            }
                            
                            $echoVal2 = ''; // Initialize safely
                            $courier_rst2 = getData('courier_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);

                            if ($courier_rst2 && $courier_rst2->num_rows > 0) {
                                $courier_row2 = $courier_rst2->fetch_assoc();
                                if (isset($courier_row2['courier_id'])) {
                                    $echoVal2 = $courier_row2['courier_id'];
                                }
                            }
                       
                            $courier_rst = getData('name', "id = '$echoVal2'", '', COURIER, $connect);
                            $courier_row = ($courier_rst && $courier_rst->num_rows > 0) ? $courier_rst->fetch_assoc() : array();
                      
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
                                // Graceful fallback: keep form usable even when lookup query is unavailable.
                            }
                            $tracking_row = ($tracking_rst && $tracking_rst->num_rows > 0) ? $tracking_rst->fetch_assoc() : array();
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
                   
                            $tracking_link = ''; // Initialize safely
                            $tracking_rst2 = getData('tracking_link', "id = '$echoVal2'", '', COURIER, $connect);
                            
                            if ($tracking_rst2 && $tracking_rst2->num_rows > 0) {
                                $track_row = $tracking_rst2->fetch_assoc();
                                if (isset($track_row['tracking_link'])) {
                                    $tracking_link = $track_row['tracking_link'];
                                }
                            }
                            ?>
                            
                            <a href="<?php echo $tracking_link; ?>" id="trackOrderBtn" class="track-order-btn" data-tracking-id="<?php echo $tracking_id; ?>" target="_blank">Track Order</a>
                            
                    </div>
                </div>
                <?php } }?>
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
                    <button type="button" class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="actionBtn" id="actionBtn"
                        onclick="if (window.history.length > 1) { window.history.back(); } else { location.href = <?= htmlspecialchars(json_encode($redirect_page), ENT_QUOTES, 'UTF-8') ?>; }">Back</button>
                </div>
            </form>
        </div>
    </div>
    <!-- </div> -->

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
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
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

        <?php
        include "../js/fb_order_req.js"
            ?>

        document.addEventListener('DOMContentLoaded', function () {
            function toggleFacebookAirbillFields() {
                var updateAirbill = document.getElementById('for_update_airbill');
                var updateAirbillToggle = document.getElementById('for_update_airbill_toggle');
                var airbillNo = document.getElementById('for_airbill_no');
                var airbillAttachment = document.getElementById('for_airbill_attachment');
                var existingAttachment = document.getElementById('for_airbill_attachment_value');
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
                    fileInputSelector: '#for_airbill_attachment',
                    previewWrapSelector: '#for_airbill_attachment_preview_wrap'
                });
            }

            if (window.shopeeOmsAirbillPdfAutofill) {
                window.shopeeOmsAirbillPdfAutofill.bind({
                    fileInputSelector: '#for_airbill_attachment',
                    airbillNoSelector: '#for_airbill_no',
                    customerAddressSelector: '#for_rec_add',
                    statusSelector: '#for_airbill_extract_status',
                    workerSrc: 'header/js/pdf.worker.min.js',
                    errorClass: 'is-error'
                });
            }

            toggleFacebookAirbillFields();

            var facebookUpdateAirbillToggle = document.getElementById('for_update_airbill_toggle');
            if (facebookUpdateAirbillToggle) {
                facebookUpdateAirbillToggle.addEventListener('change', toggleFacebookAirbillFields);
            }
        });
    </script>

</body>

</html>
