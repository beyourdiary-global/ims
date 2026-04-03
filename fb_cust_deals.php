<?php
$pageTitle = "Facebook Customer Record (Deals)";
$currentPagePin = 75;
$disablePinGroupPageTitleSync = true;

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
include_once ROOT . '/include/user_record_log.php';

$tblName = FB_CUST_DEALS;

$dataID = input('id');
$act = input('act');
$pageAction = getPageAction($act);


$redirect_page = $SITEURL . '/fb_cust_deals_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

// to display data to input
if ($dataID) { //edit/remove/view
    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $connect);

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
    window.location.href = "' . $redirect_page . '"; // Redirect to previous page
    </script>';
}

if ($dataID && isset($_GET['open_order_id'])) {
    $openOrderId = (int) $_GET['open_order_id'];
    if ($openOrderId > 0) {
        $orderRst = getData('id,name,fb_link', "id='" . $openOrderId . "'", 'LIMIT 1', FB_ORDER_REQ, $finance_connect);
        if ($orderRst && $orderRst->num_rows > 0) {
            $orderRow = $orderRst->fetch_assoc();
            $orderNo = '#'. $openOrderId;
            if (!empty($orderRow['name'])) {
                $orderNo .= ' - ' . $orderRow['name'];
            }
            $log = [
                'log_act' => 'View',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => "order_id=" . $openOrderId,
                'query_table' => FB_ORDER_REQ,
                'act_msg' => USER_NAME . " opened Facebook order detail [<b>" . $orderNo . "</b>] from <b><i>" . $pageTitle . "</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);
        }
        echo "<script>location.href='" . $SITEURL . "/finance/fb_order_req.php?id=" . $openOrderId . "&act=E';</script>";
        exit;
    }
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    $fcb_name = postSpaceFilter('fcb_name');
    $fcb_link = postSpaceFilter('fcb_link');
    $fcb_ctc = postSpaceFilter('fcb_contact');
    $fcb_pic = postSpaceFilter('fcb_pic_hidden');
    $fcb_country = postSpaceFilter('fcb_country_hidden');
    $fcb_brand = postSpaceFilter('fcb_brand_hidden');
    $fcb_series = postSpaceFilter('fcb_series_hidden');
    $fcb_fbpage = postSpaceFilter('fcb_fbpage_hidden');
    $fcb_channel = postSpaceFilter('fcb_channel_hidden');
    $fcb_rec_name = postSpaceFilter('fcb_rec_name');
    $fcb_rec_ctc = postSpaceFilter('fcb_rec_ctc');
    $fcb_rec_add = postSpaceFilter('fcb_rec_add');
    $fcb_remark = postSpaceFilter('fcb_remark');

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':

            if (!$fcb_name) {
                $name_err = "Name cannot be empty.";
                break;
            } else if (!$fcb_link) {
                $link_err = "Facebook Link cannot be empty.";
                break;
            } else if (!$fcb_ctc) {
                $contact_err = "Contact cannot be empty.";
                break;
            } else if (!$fcb_pic && $fcb_pic < 1) {
                $pic_err = "Sales Person-In-Charge cannot be empty.";
                break;
            } else if (!$fcb_country && $fcb_country < 1) {
                $country_err = "Country cannot be empty.";
                break;
            } else if (!$fcb_brand && $fcb_brand < 1) {
                $brand_err = "Brand cannot be empty.";
                break;
            } else if (!$fcb_series && $fcb_series < 1) {
                $series_err = "Series cannot be empty.";
                break;
            } else if (!$fcb_fbpage && $fcb_fbpage < 1) {
                $fbpage_err = "Facebook Page cannot be empty.";
                break;
            } else if (!$fcb_channel && $fcb_channel < 1) {
                $channel_err = "Channel cannot be empty.";
                break;
            } else if (!$fcb_rec_name) {
                $rec_name_err = "Receiver Name cannot be empty.";
                break;
            } else if (!$fcb_rec_ctc) {
                $rec_ctc_err = "Receiver Contact cannot be empty.";
                break;
            } else if (!$fcb_rec_add) {
                $rec_add_err = "Receiver Address cannot be empty.";
                break;
            } else if ($action == 'addRecord') {
                try {
                    //check values
                    if ($fcb_name) {
                        array_push($newvalarr, $fcb_name);
                        array_push($datafield, 'name');
                    }
                    if ($fcb_link) {
                        array_push($newvalarr, $fcb_link);
                        array_push($datafield, 'facebook link');
                    }

                    if ($fcb_ctc) {
                        array_push($newvalarr, $fcb_ctc);
                        array_push($datafield, 'contact');
                    }

                    if ($fcb_pic) {
                        array_push($newvalarr, $fcb_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($fcb_country) {
                        array_push($newvalarr, $fcb_country);
                        array_push($datafield, 'country');
                    }

                    if ($fcb_brand) {
                        array_push($newvalarr, $fcb_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($fcb_series) {
                        array_push($newvalarr, $fcb_series);
                        array_push($datafield, 'series');
                    }

                    if ($fcb_fbpage) {
                        array_push($newvalarr, $fcb_fbpage);
                        array_push($datafield, 'fb page');
                    }

                    if ($fcb_channel) {
                        array_push($newvalarr, $fcb_channel);
                        array_push($datafield, 'channel');
                    }

                    if ($fcb_rec_name) {
                        array_push($newvalarr, $fcb_rec_name);
                        array_push($datafield, 'receiver name');
                    }

                    if ($fcb_rec_ctc) {
                        array_push($newvalarr, $fcb_rec_ctc);
                        array_push($datafield, 'receiver contact');
                    }

                    if ($fcb_rec_add) {
                        array_push($newvalarr, $fcb_rec_add);
                        array_push($datafield, 'receiver address');
                    }

                    if ($fcb_remark) {
                        array_push($newvalarr, $fcb_remark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,fb_link,contact,sales_pic,country,brand,series,fb_page,channel,ship_rec_name,ship_rec_add,ship_rec_contact,remark,create_by,create_date,create_time) VALUES ('$fcb_name','$fcb_link','$fcb_ctc','$fcb_pic','$fcb_country','$fcb_brand','$fcb_series','$fcb_fbpage','$fcb_channel','$fcb_rec_name','$fcb_rec_add','$fcb_rec_ctc','$fcb_remark','" . USER_ID . "',curdate(),curtime())";
                    // Execute the query
                    $returnData = mysqli_query($connect, $query);
                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $connect);
                    $row = $rst->fetch_assoc();

                    // check value
                    if ($row['name'] != $fcb_name) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $fcb_name);
                        array_push($datafield, 'name');
                    }

                    if ($row['fb_link'] != $fcb_link) {
                        array_push($oldvalarr, $row['fb_link']);
                        array_push($chgvalarr, $fb_link);
                        array_push($datafield, 'fb link');
                    }

                    if ($row['contact'] != $fcb_ctc) {
                        array_push($oldvalarr, $row['contact']);
                        array_push($chgvalarr, $fcb_ctc);
                        array_push($datafield, 'contact');
                    }

                    if ($row['sales_pic'] != $fcb_pic) {
                        array_push($oldvalarr, $row['sales_pic']);
                        array_push($chgvalarr, $fcb_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['country'] != $fcb_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $fcb_country);
                        array_push($datafield, 'country');
                    }

                    if ($row['brand'] != $fcb_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $fcb_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['series'] != $fcb_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $fcb_series);
                        array_push($datafield, 'series');
                    }

                    if ($row['fb_page'] != $fcb_fbpage) {
                        array_push($oldvalarr, $row['fb_page']);
                        array_push($chgvalarr, $fcb_fbpage);
                        array_push($datafield, 'fb_page');
                    }

                    if ($row['channel'] != $fcb_channel) {
                        array_push($oldvalarr, $row['channel']);
                        array_push($chgvalarr, $fcb_channel);
                        array_push($datafield, 'channel');
                    }

                    if ($row['ship_rec_name'] != $fcb_rec_name) {
                        array_push($oldvalarr, $row['ship_rec_name']);
                        array_push($chgvalarr, $fcb_rec_name);
                        array_push($datafield, 'shipping receiver name');
                    }

                    if ($row['ship_rec_contact'] != $fcb_rec_ctc) {
                        array_push($oldvalarr, $row['ship_rec_contact']);
                        array_push($chgvalarr, $fcb_rec_ctc);
                        array_push($datafield, 'shipping receiver contact');
                    }

                    if ($row['ship_rec_add'] != $fcb_rec_add) {
                        array_push($oldvalarr, $row['ship_rec_add']);
                        array_push($chgvalarr, $fcb_rec_add);
                        array_push($datafield, 'shipping receiver address');
                    }

                    if ($row['remark'] != $fcb_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $fcb_remark == '' ? 'Empty Value' : $fcb_remark);
                        array_push($datafield, 'remark');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET name = '$fcb_name', fb_link = '$fcb_link', contact = '$fcb_ctc', sales_pic = '$fcb_pic', country = '$fcb_country', brand = '$fcb_brand', series = '$fcb_series', fb_page = '$fcb_fbpage', channel = '$fcb_channel', ship_rec_name = '$fcb_rec_name', ship_rec_add = '$fcb_rec_add', ship_rec_contact = '$fcb_rec_ctc', remark ='$fcb_remark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
                        $returnData = mysqli_query($connect, $query);

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
            $rst = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $connect);
            $row = $rst->fetch_assoc();

            $dataID = $row['id'];

            //SET the record status to 'D'
            deleteRecord($tblName, '', $dataID, $fcb_name, $connect, $connect, $cdate, $ctime, $pageTitle);
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
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">

</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
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
                        <h2>
                            <?php
                            echo displayPageAction($act, $pageTitle);
                            ?>
                        </h2>
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
                                <label class="form-label form_lbl" id="fcb_name_lbl" for="fcb_name">Name<span
                                        class="requireRed">*</span></label>
                                <?php 
                                unset($echoVal);
                                if (isset($row['name']))
                                    $echoVal = $row['name'];
                                ?>
                                <input class="form-control" type="text" name="fcb_name" id="fcb_name" value="<?php echo !empty($echoVal) ? $row['name'] : '' ?>" <?php if ($act == '')
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
                                <label class="form-label form_lbl" id="fcb_link_lbl" for="fcb_link">Facebook Link<span
                                        class="requireRed">*</span></label>
                                <?php 
                                unset($echoVal);
                                if (isset($row['fb_link']))
                                    $echoVal = $row['fb_link'];
                                ?>
                                <input class="form-control" type="text" name="fcb_link" id="fcb_link" value="<?php echo !empty($echoVal) ? $row['fb_link'] : '' ?>" <?php if ($act == '')
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
                                <label class="form-label form_lbl" id="fcb_contact_lbl" for="fcb_contact">Contact<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="number" name="fcb_contact" id="fcb_contact" value="<?php
                                if (isset($dataExisted) && isset($row['contact']) && !isset($fcb_contact)) {
                                    echo $row['contact'];
                                } else if (isset($fcb_contact)) {
                                    echo $fcb_contact;
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
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="fcb_pic_lbl" for="fcb_pic">Sales Person In
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
                                <input class="form-control" type="text" name="fcb_pic" id="fcb_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? ($user_row['name'] ?? '') : '' ?>">
                                <input type="hidden" name="fcb_pic_hidden" id="fcb_pic_hidden"
                                    value="<?php echo (isset($row['sales_pic'])) ? $row['sales_pic'] : ''; ?>">
                                <?php }?>
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
                                <input class="form-control" type="text" name="fcb_pic" id="fcb_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo $defaultUser ?>">
                                <input type="hidden" name="fcb_pic_hidden" id="fcb_pic_hidden"
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
                                <label class="form-label form_lbl" id="fcb_country_lbl" for="fcb_country">Country<span
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
                                <input class="form-control" type="text" name="fcb_country" id="fcb_country" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? ($country_row['nicename'] ?? '') : '' ?>">
                                <input type="hidden" name="fcb_country_hidden" id="fcb_country_hidden"
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
                                <label class="form-label form_lbl" id="fcb_brand_lbl" for="fcb_brand">Brand<span
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
                                <input class="form-control" type="text" name="fcb_brand" id="fcb_brand" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? ($brand_row['name'] ?? '') : '' ?>">
                                <input type="hidden" name="fcb_brand_hidden" id="fcb_brand_hidden"
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
                                <label class="form-label form_lbl" id="fcb_fb_page_lbl" for="fcb_fbpage">Facebook
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
                                <input class="form-control" type="text" name="fcb_fbpage" id="fcb_fbpage" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? $fbpage_row['name'] : '' ?>">
                                <input type="hidden" name="fcb_fbpage_hidden" id="fcb_fbpage_hidden"
                                    value="<?php echo (isset($row['fb_page'])) ? $row['fb_page'] : ''; ?>">


                                <?php if (isset($fbpage_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $fbpage_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="fcb_channel_lbl" for="fcb_channel">Channel<span
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
                                <input class="form-control" type="text" name="fcb_channel" id="fcb_channel" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $channel_row['name'] : '' ?>">
                           <input type="hidden" name="fcb_channel_hidden" id="fcb_channel_hidden"
                                value="<?php echo (isset($row['channel'])) ? $row['channel'] : (isset($channel_row) ? $channel_row['id'] : ''); ?>">



                                <?php if (isset($channel_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $channel_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="fcb_series_lbl" for="fcb_series">Series<span
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
                                <input class="form-control" type="text" name="fcb_series" id="fcb_series" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? $series_row['name'] : '' ?>">
                                <input type="hidden" name="fcb_series_hidden" id="fcb_series_hidden"
                                    value="<?php echo (isset($row['series'])) ? $row['series'] : ''; ?>">


                                <?php if (isset($series_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $series_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                        <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                            <legend class="float-none w-auto p-2">Shipping Receiver Details</legend>
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" id="fcb_rec_name_lbl"
                                            for="fcb_rec_name">Receiver
                                            Name<span class="requireRed">*</span></label>
                                            <?php 
                                            unset($echoVal);
                                            if (isset($row['ship_rec_name']))
                                            $echoVal = $row['ship_rec_name'];
                                            ?>
                                            <input class="form-control" type="text" name="fcb_rec_name" id="fcb_rec_name" value="<?php echo !empty($echoVal) ? $row['ship_rec_name'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>       
                                        <?php if (isset($rec_name_err)) { ?>
                                            <div id="err_msg">
                                                <span class="mt-n1">
                                                    <?php echo $rec_name_err; ?>
                                                </span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" id="fcb_rec_ctc_lbl"
                                            for="fcb_rec_ctc">Receiver
                                            Contact<span class="requireRed">*</span></label>
                                            <?php 
                                        unset($echoVal);

                                        if (isset($row['ship_rec_contact']))
                                            $echoVal = $row['ship_rec_contact'];
                                        ?>
                                        <input class="form-control" type="number" name="fcb_rec_ctc" id="fcb_rec_ctc" value="<?php echo !empty($echoVal) ? $row['ship_rec_contact'] : '' ?>" <?php if ($act == '')
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
                                        <label class="form-label form_lbl" id="fcb_rec_add_lbl"
                                            for="fcb_rec_add">Receiver
                                            Address<span class="requireRed">*</span></label>
                                            <?php 
                                        unset($echoVal);

                                        if (isset($row['ship_rec_add']))
                                            $echoVal = $row['ship_rec_add'];
                                        ?>
                                        <input class="form-control" type="text" name="fcb_rec_add" id="fcb_rec_add" value="<?php echo !empty($echoVal) ? $row['ship_rec_add'] : '' ?>" <?php if ($act == '')
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
                            <label class="form-label form_lbl" id="fcb_remark_lbl" for="fcb_remark">Remark</label>
                            <textarea class="form-control" name="fcb_remark" id="fcb_remark" rows="3" <?php if ($act == '')
                                echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                                echo $row['remark'] ?></textarea>
                            </div>

                            <?php
                            if ($dataID) {
                                $orderRows = array();
                                $sumFinalAmount = 0.00;
                                $customerName = isset($row['name']) ? trim((string) $row['name']) : '';
                                $customerFbLink = isset($row['fb_link']) ? trim((string) $row['fb_link']) : '';

                                if ($customerName !== '') {
                                    $orderWhere = "status='A' AND name='" . mysqli_real_escape_string($finance_connect, $customerName) . "'";
                                    if ($customerFbLink !== '') {
                                        $orderWhere .= " AND fb_link='" . mysqli_real_escape_string($finance_connect, $customerFbLink) . "'";
                                    }

                                    $orderSql = "SELECT * FROM " . FB_ORDER_REQ . " WHERE " . $orderWhere . " ORDER BY id DESC";
                                    $orderRst = mysqli_query($finance_connect, $orderSql);
                                    if ($orderRst && $orderRst->num_rows > 0) {
                                        while ($orderRow = $orderRst->fetch_assoc()) {
                                            $orderRows[] = $orderRow;
                                            $sumFinalAmount += (float) (isset($orderRow['price']) ? $orderRow['price'] : 0);
                                        }
                                    } else if (!$orderRst) {
                                        error_log("Facebook order list query failed: " . mysqli_error($finance_connect) . " SQL: " . $orderSql);
                                    }
                                }
                            ?>
                            <div class="form-group mt-3">
                                <h5 class="mb-3">Order Records</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered mb-0" id="fcb_order_tbl">
                                        <thead>
                                            <tr>
                                                <th width="60">S/N</th>
                                                <th width="200">Action</th>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Package</th>
                                                <th>Buyer Payment Method</th>
                                                <th>Charges &amp; Fees</th>
                                                <th>Final Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($orderRows)) {
                                                $orderSN = 1;
                                                foreach ($orderRows as $orderRow) {
                                                    $orderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;
                                                    $orderNo = 'FB-' . $orderId;
                                                    $orderDate = isset($orderRow['create_date']) ? $orderRow['create_date'] : '';
                                                    $orderPackage = commonResolvePackageNamesFromCsv(isset($orderRow['package']) ? $orderRow['package'] : '', $connect);
                                                    $buyerPayMethod = commonResolvePaymentMethodName(isset($orderRow['pay_method']) ? $orderRow['pay_method'] : '', $finance_connect);
                                                    $orderFees = '0.00';
                                                    $finalAmount = isset($orderRow['price']) ? $orderRow['price'] : '0.00';
                                                    ?>
                                                    <tr>
                                                        <td><?= $orderSN++ ?></td>
                                                        <td>
                                                            <a class="btn btn-sm btn-rounded btn-primary"
                                                               href="<?= $SITEURL . '/fb_cust_deals.php?id=' . (int) $dataID . '&act=' . $act_2 . '&open_order_id=' . $orderId ?>">
                                                                Show Order Detail
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars((string) $orderNo, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $orderPackage, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $buyerPayMethod, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= commonFormatAmountRm($orderFees) ?></td>
                                                        <td><?= commonFormatAmountRm($finalAmount) ?></td>
                                                    </tr>
                                                <?php }
                                            } else { ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">No order records found.</td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="7" class="text-end">Sub-Total (RM)</th>
                                                <th><?= commonFormatAmountRm($sumFinalAmount) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <?php } ?>

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
                            ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="actionBtn"
                                id="actionBtn" value="back">Back</button>
                        </div>
                </form>

                <?php
                if ($dataID) {
                    $customerLogReturnUrl = $SITEURL . '/fb_cust_deals.php?id=' . (int) $dataID;
                    if ($act !== '') {
                        $customerLogReturnUrl .= '&act=' . urlencode((string) $act);
                    }

                    $customerLogContext = urlResolveUserRecordLogContext($connect, $connect, array(
                        'customer_id' => (int) $dataID,
                        'customer_column' => 'facebook_cust_id',
                        'customer_label' => isset($row['name']) ? $row['name'] : '',
                        'return_url' => $customerLogReturnUrl,
                        'ajax_url' => $SITEURL . '/user_record_log.php',
                        'customer_only' => true,
                    ));

                    urlRenderUserRecordLogModule($connect, $connect, array(
                        'table_name' => USER_RECORD_LOG,
                        'context' => $customerLogContext,
                        'section_heading' => 'User Record Log',
                        'show_scope_note' => true,
                    ));
                }
                ?>
            </div>
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
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        setButtonColor();
        preloader(300, action);

        <?php
        include "./js/fb_cust_deals.js"
        ?>
    </script>

</body>

</html>