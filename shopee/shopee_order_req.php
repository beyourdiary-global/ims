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
$allowed_ext = array("png", "jpg", "jpeg", "svg", "pdf");

// Redirect directly to role page to avoid extra router history entries.
$redirect_page = $SITEURL . '/shopee/shopee_processing_order.php';
if (in_array('130', GlobalPin)) {
    $redirect_page = $SITEURL . '/shopee/shopee_order_req_table.php';
} else if (in_array('129', GlobalPin)) {
    $redirect_page = $SITEURL . '/shopee/shopee_verify.php';
}
$redirectLink = ("<script>location.replace('$redirect_page');</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

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

if (!($dataID) && !($act)) {
    echo '<script>
    alert("Invalid action.");
    window.location.replace("' . $redirect_page . '");
    </script>';
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $scr_username = $_POST['scr_username']; 
    $scr_pic = $_POST['scr_pic_hidden'];
    $scr_country = $_POST['scr_country_hidden'];
    $scr_brand = $_POST['scr_brand_hidden'];
    $scr_series = $_POST['scr_series_hidden'];
    $duplicate_check_query = "SELECT * FROM shopee_customer_info WHERE buyer_username = '$scr_username'";
    $duplicate_result = mysqli_query($finance_connect, $duplicate_check_query);
    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    if( $scr_username != ''){
        if (mysqli_num_rows($duplicate_result) > 0) {
            echo "<script>alert('Error: Duplicate Customer ID found!');</script>";
        } else {
           $insert_query = "INSERT INTO ".SHOPEE_CUST_INFO." (buyer_username, pic, country, brand, series,create_by,create_date,create_time) 
                             VALUES ('$scr_username', '$scr_pic', '$scr_country', '$scr_brand', '$scr_series','" . USER_ID . "',curdate(),curtime())";
    
    
            if (mysqli_query($finance_connect, $insert_query)) {
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
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $insert_query,
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                $log['newval'] = implodeWithComma($newvalarr);
                $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                
                audit_log($log);
             }
        }
    }
}

if (post('updateStatusBtn')) {
    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
    $newStatus = post('updateStatusBtn'); // Will receive 'SP' (Processing) or 'OC' (Order Received)

    if (in_array($newStatus, ['SP', 'OC'])) {
        try {
            // 1. Get old data before update
            $getOldQuery = "SELECT order_status FROM " . $tblName . " WHERE id = " . intval($dataID);
            $oldResult = mysqli_query($finance_connect, $getOldQuery);
            $oldRow = mysqli_fetch_assoc($oldResult);

            $oldStatus = $oldRow['order_status'];

            // 2. Perform update
            $queryStatusUpdate = "UPDATE " . $tblName . " SET order_status='$newStatus' WHERE id = " . intval($dataID);
            $returnData = mysqli_query($finance_connect, $queryStatusUpdate);

            // 3. Only log if update was successful
            if ($returnData) {
                // Prepare audit log details
                array_push($datafield, 'order_status');
                array_push($oldvalarr, $oldStatus);
                array_push($chgvalarr, $newStatus);

                $statusLabel = ($newStatus === 'SP') ? "Processing" : "Order Received";

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
                    'oldval'       => implodeWithComma($oldvalarr),
                    'changes'      => implodeWithComma($chgvalarr),
                    'act_msg'      => actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, 'edit', '')
                ];

                audit_log($log);

                echo '<script>
                    alert("Order status updated to ' . $statusLabel . '.");
                    window.location.replace("' . $redirect_page . '");
                </script>';
                exit; // Stop executing the rest of the page so it redirects cleanly
            } else {
                throw new Exception("Failed to update order status.");
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            echo '<script>alert("Error: ' . addslashes($errorMsg) . '");</script>';
        }
    }
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
    $sor_fees = postSpaceFilter('sor_fees');
    $sor_final = postSpaceFilter('sor_final');
    $sor_remark = postSpaceFilter('sor_remark');

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':
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
            if (isset($error)) {
                break;
            }
            if ($action == 'addRecord') {
                try {
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

                    $query = "INSERT INTO " . $tblName . " (shopee_acc,currency,orderID,date,time,package,brand,buyer,buyer_pay_meth,pic,price,voucher,act_shipping_fee,service_fee,trans_fee,ams_fee,fees,final_amt,remark,create_by,create_date,create_time) VALUES ('$sor_acc','$sor_curr','$sor_order','$sor_date','$sor_time','$sor_pkg','$sor_brand','$sor_user','$sor_pay','$sor_pic','$sor_price','$sor_voucher','$sor_shipping','$sor_serv','$sor_trans','$sor_ams','$sor_fees','$sor_final','$sor_remark','" . USER_ID . "',curdate(),curtime())";
                    // Execute the query
                    $returnData = mysqli_query($finance_connect, $query);
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
                        $query .= "brand = '$sor_brand', ";
                        $query .= "buyer = '$sor_user', ";
                        $query .= "buyer_pay_meth = '$sor_pay', ";
                        $query .= "pic = '$sor_pic', ";
                        $query .= "price = '$sor_price', ";
                        $query .= "voucher = '$sor_voucher', ";
                        $query .= "act_shipping_fee = '$sor_shipping', ";
                        $query .= "service_fee = '$sor_serv', ";
                        $query .= "trans_fee = '$sor_trans', ";
                        $query .= "ams_fee = '$sor_ams', ";
                        $query .= "fees = '$sor_fees', ";
                        $query .= "final_amt = '$sor_final', ";
                        $query .= "remark = '$sor_remark', ";
                        $query .= "update_by = '" . USER_ID . "', ";
                        $query .= "update_date = curdate(), ";
                        $query .= "update_time = curtime() ";
                        $query .= "WHERE id = '$dataID'"; // Specify your condition here

                        $returnData = mysqli_query($finance_connect, $query);

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
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">

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
                                echo $row['time'];
                            } else if (isset($sor_time)) {
                                echo $sor_time;
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
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_user_lbl" for="sor_user">Shopee Buyer
                                Username<span class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            if (isset($row['buyer']))
                                $echoVal = $row['buyer'];

                            if (isset($echoVal)) {
                                $user_rst = getData('*', "id = '$echoVal'", '', SHOPEE_CUST_INFO, $finance_connect);
                                $user_row = $user_rst ? $user_rst->fetch_assoc() : [];
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_user" id="sor_user" <?php if ($act == '')
                                echo 'disabled' ?>
                                    value="<?php echo !empty($echoVal) ? $user_row['buyer_username'] : '' ?>">
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
                <form id="myForm" method="POST">
                <div id="new_customer_section" style="display: none;">

                <div class="row">
                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_username">Shopee Buyer Username<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_username" name="scr_username">
                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_pic">Sales Person In Charge<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_pic" name="scr_pic">                        
                        <input class="form-control" type="hidden" id="scr_pic_hidden" name="scr_pic_hidden">

                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_country">Country<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_country" name="scr_country">
                        <input class="form-control" type="hidden" id="scr_country_hidden" name="scr_country_hidden">
                    </div>
                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_brand">Brand<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_brand" name="scr_brand"> <input class="form-control" type="hidden" id="scr_brand_hidden" name="scr_brand_hidden">
                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_series">Series<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_series" name="scr_series"><input class="form-control" type="hidden" id="scr_series_hidden" name="scr_series_hidden">
                    </div>
                </div>
                <input type="submit" name="submit" value="Submit">
                    </form>
                </div>
                <?php }?>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_pic_lbl" for="sor_pic">Person In
                                Charge<span class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);

                            if (isset($row['pic']))
                                $echoVal = $row['pic'];

                            if (isset($echoVal)) {
                                $user_rst = getData('name', "id = '$echoVal'", '', USR_USER, $connect);
                                $user_row = $user_rst ? $user_rst->fetch_assoc() : [];
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_pic" id="sor_pic" <?php if ($act == '')
                                echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $user_row['name'] : '' ?>">
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
                            <input class="form-control" type="number" step="1" name="sor_voucher" id="sor_voucher"
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
                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php
                        if (isset($row['order_status'])) {
                            // Clean the status string for matching
                            $statusKey = preg_replace('/[^a-z]/', '', strtolower(trim((string) $row['order_status'])));
                            
                            $isPendingToPack = ($statusKey === 'p' || $statusKey === 'pendingto' || $statusKey === 'pendingtopack');
                            $isProcessing = ($statusKey === 'sp' || $statusKey === 'processing');

                            // If status is 'P', show "UPDATE TO PROCESSING" and pass 'SP'
                            if ($isPendingToPack) {
                                echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" id="updateStatusBtn" value="SP" formnovalidate>UPDATE TO PROCESSING</button>';
                            } 
                            // If status is 'SP', show "UPDATE TO ORDER RECEIVED" and pass 'OC'
                            else if ($isProcessing) {
                                echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" id="updateStatusBtn" value="OC" formnovalidate>UPDATE TO ORDER RECEIVED</button>';
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
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="actionBtn" id="actionBtn"
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
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
    }
    ?>
    <script>
    $(document).ready(function() {
    $('#myForm').on('submit', function(event) {
        event.preventDefault(); // Prevent the form from submitting the traditional way

        var formData = $(this).serialize(); // Serialize the form data

        $.ajax({
            url: 'shopee_order_req.php', // The URL to your PHP script
            type: 'post',
            data: formData,
            success: function(response) {
                var responseObject = JSON.parse(response);
            },
            error: function(xhr, status, error) {
                $('#responseMessage').html('<p>An error occurred: ' + error + '</p>');
            }
        });
    });
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