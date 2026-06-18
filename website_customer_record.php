<?php
ob_start();

$currentPagePin = 84;
$pageTitle = "Website Customer Record (Deals)";

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
include_once ROOT . '/include/customer_tag.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
$pinAccess = checkCurrentPin($connect, $pageTitle);
include_once ROOT . '/include/user_record_log.php';

$tblName = WEB_CUST_RCD;

$dataId = input('id');
$act = input('act');
$pageAction = getPageAction($act);


$redirectPage = $SITEURL . '/website_customer_record_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>clearLocalStoragePreservingCustomerRecordFilters();</script>';

// to display data to input
if ($dataId) { //edit/remove/view
    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $connect);

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

if (!($dataId) && !($act)) {
    renderNotificationScript('Invalid action.', 'error', $redirectPage);

}

if ($dataId && isset($_GET['open_order_id'])) {
    $openOrderId = (int) $_GET['open_order_id'];
    if ($openOrderId > 0) {
        $customerRowId = (int) $dataId;
        $customerCode = (isset($row['cust_id']) ? trim((string) $row['cust_id']) : '');
        $orderWhere = "id='" . $openOrderId . "' AND status='A' AND (cust_id='" . $customerRowId . "'";
        if ($customerCode !== '') {
            $orderWhere .= " OR cust_id='" . mysqli_real_escape_string($finance_connect, $customerCode) . "'";
        }
        $orderWhere .= ")";

        $orderRst = getData('id,order_id', $orderWhere, 'LIMIT 1', WEB_ORDER_REQ, $finance_connect);
        if ($orderRst && $orderRst->num_rows > 0) {
            $orderRow = $orderRst->fetch_assoc();
            $orderNo = isset($orderRow['order_id']) ? $orderRow['order_id'] : ('#' . $openOrderId);
            $log = [
                'log_act' => 'View',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => "order_id=" . $openOrderId,
                'query_table' => WEB_ORDER_REQ,
                'act_msg' => USER_NAME . " opened Website order detail [<b>" . $orderNo . "</b>] from <b><i>" . $pageTitle . "</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo "<script>location.href='" . $SITEURL . "/finance/website_order_request.php?id=" . $openOrderId . "&act=E';</script>";
            exit;
        }
    }
}

$websiteCustomerTagPlatform = 'website';
$websiteCustomerTagCustomerId = (isset($row['id']) ? (int) $row['id'] : 0);
$websiteCustomerTagDisplayName = isset($row['name']) ? trim((string) $row['name']) : '';
$websiteCustomerTagDraftToken = customerTagResolveDraftToken($act);
$websiteCustomerFreshAddPage = ($act === 'I' && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'GET' && !customerTagIsAjaxRequest());
customerTagResetDraftOnFreshAddPage($websiteCustomerTagPlatform, $act, $websiteCustomerTagDraftToken);
$websiteCustomerTagState = array();
if ($websiteCustomerFreshAddPage) {
    customerTagClearDraftTags($websiteCustomerTagPlatform, $websiteCustomerTagDraftToken);
}
$websiteCustomerTagState = customerTagHandlePost($connect, $websiteCustomerTagPlatform, $websiteCustomerTagCustomerId, $pageTitle, $websiteCustomerTagDisplayName, $websiteCustomerTagDraftToken);
$websiteCustomerActiveTags = $websiteCustomerFreshAddPage ? array() : customerTagGetDisplayTags($connect, $websiteCustomerTagPlatform, $websiteCustomerTagCustomerId, $websiteCustomerTagDraftToken);
$websiteCustomerDraftTagIds = customerTagExtractTagIds($websiteCustomerActiveTags);

$websiteCustomerLabelMeta = array();
$websiteCustomerLabelDisplayHtml = '';
if (isset($dataExisted) && !empty($dataId) && $act !== 'I' && isset($row['id']) && (int) $row['id'] > 0) {
    $websiteCustomerLabelMap = customerLabelGetCustomerLabelMap($connect, 'website', array((int) $row['id']));
    $websiteCustomerLabelMeta = isset($websiteCustomerLabelMap[(int) $row['id']]) ? $websiteCustomerLabelMap[(int) $row['id']] : array();
    $websiteCustomerLabelDisplayHtml = customerLabelRenderPageHeader($websiteCustomerLabelMeta);
}

$series_list_result = getData('*', '', '', BRD_SERIES, $connect);

if (post('actionBtn')) {
    $action = post('actionBtn');

    $wcr_cust_id = postSpaceFilter('wcr_cust_id');
    $wcr_name = postSpaceFilter('wcr_name');
    $wcr_ctc = postSpaceFilter('wcr_contact');
    $wcr_cust_email = postSpaceFilter('wcr_cust_email');
    $wcr_cust_birthday = postSpaceFilter('wcr_cust_birthday');
    $wcr_pic = postSpaceFilter('wcr_pic_hidden');
    $wcr_country = postSpaceFilter('wcr_country_hidden');
    $wcr_brand = postSpaceFilter('wcr_brand_hidden');
    $wcr_series = postSpaceFilter('wcr_series_hidden');
    $wcr_rec_name = postSpaceFilter('wcr_rec_name');
    $wcr_rec_ctc = postSpaceFilter('wcr_rec_ctc');
    $wcr_rec_add = postSpaceFilter('wcr_rec_add');
    $wcr_remark = postSpaceFilter('wcr_remark');

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':

            if ($wcr_cust_email && !isEmail($wcr_cust_email)) {
                $cust_email_err = "Wrong email format!";
                $error = 1;
                break;
            }

            if (!$wcr_cust_id) {
                $cust_id_err = "Customer ID cannot be empty.";
                break;            
            } else if (!$wcr_name) {
                $name_err = "Name cannot be empty.";
                break;
            } else if (!$wcr_ctc) {
                $contact_err = "Contact cannot be empty.";
                break;
            } else if (!$wcr_cust_email) {
                $cust_email_err = "Customer Email cannot be empty.";
                break;
            } else if (!$wcr_cust_birthday) {
                $cust_birthday_err = "Customer Birthday cannot be empty.";
                break;
            } else if (!$wcr_pic && $wcr_pic < 1) {
                $pic_err = "Sales Person-In-Charge cannot be empty.";
                break;
            } else if (!$wcr_country && $wcr_country < 1) {
                $country_err = "Country cannot be empty.";
                break;
            } else if (!$wcr_brand && $wcr_brand < 1) {
                $brand_err = "Brand cannot be empty.";
                break;
            } else if (!$wcr_series && $wcr_series < 1) {
                $series_err = "Series cannot be empty.";
                break;
            } else if (!$wcr_rec_name) {
                $rec_name_err = "Receiver Name cannot be empty.";
                break;
            } else if (!$wcr_rec_ctc) {
                $rec_ctc_err = "Receiver Contact cannot be empty.";
                break;
            } else if (!$wcr_rec_add) {
                $rec_add_err = "Receiver Address cannot be empty.";
                break;
            } else if ($action == 'addRecord') {
                try {
                    //check values
                    if ($wcr_cust_id) {
                        array_push($newvalarr, $wcr_cust_id);
                        array_push($datafield, 'cust_id');
                    }

                    if ($wcr_name) {
                        array_push($newvalarr, $wcr_name);
                        array_push($datafield, 'name');
                    }

                    if ($wcr_ctc) {
                        array_push($newvalarr, $wcr_ctc);
                        array_push($datafield, 'contact');
                    }

                    if ($wcr_cust_email) {
                        array_push($newvalarr, $wcr_cust_email);
                        array_push($datafield, 'cust_email');
                    }

                    if ($wcr_cust_birthday) {
                        array_push($newvalarr, $wcr_cust_birthday);
                        array_push($datafield, 'cust_birthday');
                    }

                    if ($wcr_pic) {
                        array_push($newvalarr, $wcr_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($wcr_country) {
                        array_push($newvalarr, $wcr_country);
                        array_push($datafield, 'country');
                    }

                    if ($wcr_brand) {
                        array_push($newvalarr, $wcr_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($wcr_series) {
                        array_push($newvalarr, $wcr_series);
                        array_push($datafield, 'series');
                    }

                    if ($wcr_rec_name) {
                        array_push($newvalarr, $wcr_rec_name);
                        array_push($datafield, 'receiver name');
                    }

                    if ($wcr_rec_ctc) {
                        array_push($newvalarr, $wcr_rec_ctc);
                        array_push($datafield, 'receiver contact');
                    }

                    if ($wcr_rec_add) {
                        array_push($newvalarr, $wcr_rec_add);
                        array_push($datafield, 'receiver address');
                    }

                    if ($wcr_remark) {
                        array_push($newvalarr, $wcr_remark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(cust_id,name,contact,cust_email,cust_birthday,sales_pic,country,brand,series,ship_rec_name,ship_rec_add,ship_rec_contact,remark,create_by,create_date,create_time) VALUES ('$wcr_cust_id','$wcr_name','$wcr_ctc','$wcr_cust_email','$wcr_cust_birthday','$wcr_pic','$wcr_country','$wcr_brand','$wcr_series','$wcr_rec_name','$wcr_rec_add','$wcr_rec_ctc','$wcr_remark','" . USER_ID . "',curdate(),curtime())";
                    // Execute the query
                    $returnData = mysqli_query($connect, $query);
                    if ($returnData) {
                        $dataId = $connect->insert_id;
                        customerTagApplyDraftTagsToCustomer($connect, $websiteCustomerTagPlatform, $dataId, $pageTitle, $wcr_name, customerTagGetPostedDraftTagIds(), $websiteCustomerTagDraftToken);
                        $_SESSION['tempValConfirmBox'] = true;
                    } else {
                        $errorMsg = mysqli_error($connect);
                        $err1 = "Failed to add record: " . $errorMsg;
                        $act = "F";
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $connect);
                    $row = $result->fetch_assoc();

                    // check value
                    if ($row['cust_id'] != $wcr_cust_id) {
                        array_push($oldvalarr, $row['cust_id']);
                        array_push($chgvalarr, $wcr_cust_id);
                        array_push($datafield, 'cust_id');
                    }

                    if ($row['name'] != $wcr_name) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $wcr_name);
                        array_push($datafield, 'name');
                    }

                    if ($row['contact'] != $wcr_ctc) {
                        array_push($oldvalarr, $row['contact']);
                        array_push($chgvalarr, $wcr_ctc);
                        array_push($datafield, 'contact');
                    }

                    if ($row['cust_email'] != $wcr_cust_email) {
                        array_push($oldvalarr, $row['cust_email']);
                        array_push($chgvalarr, $wcr_cust_email);
                        array_push($datafield, 'cust_email');
                    }

                    if ($row['cust_birthday'] != $wcr_cust_birthday) {
                        array_push($oldvalarr, $row['cust_birthday']);
                        array_push($chgvalarr, $wcr_cust_birthday);
                        array_push($datafield, 'cust_birthday');
                    }

                    if ($row['sales_pic'] != $wcr_pic) {
                        array_push($oldvalarr, $row['sales_pic']);
                        array_push($chgvalarr, $wcr_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['country'] != $wcr_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $wcr_country);
                        array_push($datafield, 'country');
                    }

                    if ($row['brand'] != $wcr_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $wcr_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['series'] != $wcr_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $wcr_series);
                        array_push($datafield, 'series');
                    }

                    if ($row['ship_rec_name'] != $wcr_rec_name) {
                        array_push($oldvalarr, $row['ship_rec_name']);
                        array_push($chgvalarr, $wcr_rec_name);
                        array_push($datafield, 'shipping receiver name');
                    }

                    if ($row['ship_rec_contact'] != $wcr_rec_ctc) {
                        array_push($oldvalarr, $row['ship_rec_contact']);
                        array_push($chgvalarr, $wcr_rec_ctc);
                        array_push($datafield, 'shipping receiver contact');
                    }

                    if ($row['ship_rec_add'] != $wcr_rec_add) {
                        array_push($oldvalarr, $row['ship_rec_add']);
                        array_push($chgvalarr, $wcr_rec_add);
                        array_push($datafield, 'shipping receiver address');
                    }

                    if ($row['remark'] != $wcr_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $wcr_remark == '' ? 'Empty Value' : $wcr_remark);
                        array_push($datafield, 'remark');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET cust_id = '$wcr_cust_id', name = '$wcr_name', contact = '$wcr_ctc', cust_email = '$wcr_cust_email', cust_birthday = '$wcr_cust_birthday', sales_pic = '$wcr_pic', country = '$wcr_country', brand = '$wcr_brand', series = '$wcr_series', ship_rec_name = '$wcr_rec_name', ship_rec_add = '$wcr_rec_add', ship_rec_contact = '$wcr_rec_ctc', remark ='$wcr_remark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
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
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
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
            $result = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $connect);
            $row = $result->fetch_assoc();

            $dataId = $row['id'];

            //SET the record status to 'D'
            deleteRecord($tblName, '', $dataId, $fcb_name, $connect, $connect, $cdate, $ctime, $pageTitle);
            $_SESSION['delChk'] = 1;
        } catch (Exception $e) {
            echo 'Message: ' . $e->getMessage();
        }
    }
}

//view
if (($dataId) && !($act) && (USER_ID != '') && ($_SESSION['viewChk'] != 1) && ($_SESSION['delChk'] != 1)) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
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
    
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>">
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
                    <input type="hidden" name="customerTagDraftIds" class="customer-tag-draft-input" data-platform="<?= htmlspecialchars($websiteCustomerTagPlatform, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(implode(',', $websiteCustomerDraftTagIds), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="customerTagDraftToken" value="<?= htmlspecialchars($websiteCustomerTagDraftToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group mb-5">
                        <?php $websiteCustomerPageActionTitle = displayPageAction($act, $pageTitle); ?>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <h2 class="mb-0 customer-tag-page-title" data-base-title="<?= htmlspecialchars($websiteCustomerPageActionTitle, ENT_QUOTES, 'UTF-8') ?>">
                                <?php
                                echo customerTagRenderTitle($websiteCustomerPageActionTitle, $websiteCustomerActiveTags);
                                ?>
                            </h2>
                            <?php echo customerTagRenderManageButton($websiteCustomerTagPlatform, $websiteCustomerTagCustomerId, isActionAllowed('Edit', $pinAccess) && $act !== 'I'); ?>
                        </div>
                        <?php echo $websiteCustomerLabelDisplayHtml; ?>
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
        <label class="form-label form_lbl" id="wcr_cust_id_lbl" for="wcr_cust_id">Customer ID<span class="requireRed">*</span></label>
        <input class="form-control" type="text" name="wcr_cust_id" id="wcr_cust_id" value="<?php
        if (isset($dataExisted) && isset($row['cust_id']) && !isset($wcr_cust_id)) {
            echo $row['cust_id'];
        } else if (isset($wcr_cust_id)) {
            echo $wcr_cust_id;
        }
        ?>" <?php if ($act == '') echo 'disabled' ?>>
        <?php if (isset($cust_id_err)) { ?>
            <div id="err_msg">
                <span class="mt-n1">
                    <?php echo $cust_id_err; ?>
                </span>
            </div>
        <?php } ?>
    </div>

        <div class="col-md-4 mb-3">
            <label class="form-label form_lbl" id="wcr_name_lbl" for="wcr_name">Name<span class="requireRed">*</span></label>
            <div class="customer-field-with-label">
                <input class="form-control" type="text" name="wcr_name" id="wcr_name" value="<?php
                if (isset($dataExisted) && isset($row['name']) && !isset($wcr_name)) {
                    echo $row['name'];
                } else if (isset($wcr_name)) {
                    echo $wcr_name;
                }
                ?>" <?php if ($act == '') echo 'disabled' ?>>
                <?php echo customerLabelRenderInlineSegmentationBadge($websiteCustomerLabelMeta); ?>
            </div>
            <?php if (isset($name_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1">
                        <?php echo $name_err; ?>
                    </span>
                </div>
            <?php } ?>
        </div>


    <div class="col-md-4 mb-3">
        <label class="form-label form_lbl" id="wcr_contact_lbl" for="wcr_contact">Contact<span class="requireRed">*</span></label>
        <input class="form-control" type="number" name="wcr_contact" id="wcr_contact" value="<?php
        if (isset($dataExisted) && isset($row['contact']) && !isset($wcr_contact)) {
            echo $row['contact'];
        } else if (isset($wcr_contact)) {
            echo $wcr_contact;
        }
        ?>" <?php if ($act == '') echo 'disabled' ?>>
        <?php if (isset($contact_err)) { ?>
            <div id="err_msg">
                <span class="mt-n1">
                    <?php echo $contact_err; ?>
                </span>
            </div>
        <?php } ?>
    </div>
    
    <div class="col-md-6 mb-3">
        <label class="form-label form_lbl" id="wcr_cust_email_lbl" for="wcr_cust_email">Customer Email<span class="requireRed">*</span></label>
        <input class="form-control" type="text" name="wcr_cust_email" id="wcr_cust_email" value="<?php
        if (isset($dataExisted) && isset($row['cust_email']) && !isset($wcr_cust_email)) {
            echo $row['cust_email'];
        } else if (isset($wcr_cust_email)) {
            echo $wcr_cust_email;
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
            <label class="form-label form_lbl" id="wcr_cust_birthday_label" for="wcr_cust_birthday">Customer Birthday<span class="requireRed">*</span></label>
            <input class="form-control" type="date" name="wcr_cust_birthday" id="wcr_cust_birthday" value="<?php
                if (isset($dataExisted) && isset($row['cust_birthday']) && !isset($wcr_cust_birthday)) {
                    echo $row['cust_birthday'];
                } else if (isset($wcr_cust_birthday)) {
                    echo $wcr_cust_birthday;
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


<div class="form-group">
    <div class="row">
    <div class="col-md-3 mb-3 autocomplete">
    <label class="form-label form_lbl" id="wcr_pic_lbl" for="wcr_pic">Sales Person In Charge<span class="requireRed">*</span></label>
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
    <input class="form-control" type="text" name="wcr_pic" id="wcr_pic" <?php if ($act == '') echo 'disabled' ?> value="<?php echo $defaultUser ?>">
    <input type="hidden" name="wcr_pic_hidden" id="wcr_pic_hidden" value="<?php echo $loggedInUserId ?>">
    <?php } ?>
                            <?php
                                 if(($act == 'E'|| $act == '')){
                                unset($echoVal);

                                if (isset($row['sales_pic']))
                                    $echoVal = $row['sales_pic'];

                                if (isset($echoVal)) {
                                    
                                    $pic_rst = getData('name', "id = '$echoVal'", '', USR_USER, $connect);
                                    if (!$pic_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $pic_row = ($pic_rst && $pic_rst->num_rows > 0) ? $pic_rst->fetch_assoc() : array();
                           
                                }
                                ?>

                                <input class="form-control" type="text" name="wcr_pic" id="wcr_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? ($pic_row['name'] ?? '') : '' ?>">

                                <input type="hidden" name="wcr_pic_hidden" id="wcr_pic_hidden"
                                    value="<?php echo (isset($row['sales_pic'])) ? $row['sales_pic'] : ''; ?>">
                                <?php } ?>
        <?php if (isset($pic_err)) { ?>
        <div id="err_msg">
            <span class="mt-n1">
                <?php echo $pic_err; ?>
            </span>
        </div>
    <?php } ?>
</div>

        <div class="col-md-3 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wcr_country_lbl" for="wcr_country">Country<span class="requireRed">*</span></label>
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
            <input class="form-control" type="text" name="wcr_country" id="wcr_country" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? ($country_row['nicename'] ?? '') : '' ?>">
            <input type="hidden" name="wcr_country_hidden" id="wcr_country_hidden" value="<?php echo (isset($row['country'])) ? $row['country'] : ''; ?>">
            <?php if (isset($country_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1">
                        <?php echo $country_err; ?>
                    </span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-3 mb-3 autocomplete">
            <label class="form-label form_lbl" id="wcr_brand_lbl" for="wcr_brand">Brand<span class="requireRed">*</span></label>
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
            <input class="form-control" type="text" name="wcr_brand" id="wcr_brand" <?php if ($act == '') echo 'disabled' ?> value="<?php echo !empty($echoVal) ? ($brand_row['name'] ?? '') : '' ?>">
            <input type="hidden" name="wcr_brand_hidden" id="wcr_brand_hidden" value="<?php echo (isset($row['brand'])) ? $row['brand'] : ''; ?>">
            <?php if (isset($brand_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1">
                        <?php echo $brand_err; ?>
                    </span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-3 mb-3 autocomplete">
        <label class="form-label form_lbl" id="wcr_series_lbl" for="wcr_series">Series<span class="requireRed">*</span></label>
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

        <input class="form-control" type="text" name="wcr_series" id="wcr_series" <?php if ($act == '')
            echo 'disabled' ?> value="<?php echo !empty($echoVal) ? ($series_row['name'] ?? '') : '' ?>">

        <input type="hidden" name="wcr_series_hidden" id="wcr_series_hidden" value="<?php echo (isset($row['series'])) ? $row['series'] : ''; ?>">
            <?php if (isset($wcr_series_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $wcr_series_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

                        <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                            <legend class="float-none w-auto p-2">Shipping Receiver Details</legend>
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" id="wcr_rec_name_lbl"
                                            for="wcr_rec_name">Receiver
                                            Name<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" name="wcr_rec_name" id="wcr_rec_name"
                                            value="<?php
                                            if (isset($dataExisted) && isset($row['ship_rec_name']) && !isset($wcr_rec_name)) {
                                                echo $row['ship_rec_name'];
                                            } else if (isset($wcr_rec_name)) {
                                                echo $wcr_rec_name;
                                            }
                                            ?>" <?php if ($act == '')
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
                                        <label class="form-label form_lbl" id="wcr_rec_ctc_lbl"
                                            for="wcr_rec_ctc">Receiver
                                            Contact<span class="requireRed">*</span></label>
                                        <input class="form-control" type="number" name="wcr_rec_ctc" id="wcr_rec_ctc"
                                            value="<?php
                                            if (isset($dataExisted) && isset($row['ship_rec_contact']) && !isset($wcr_rec_ctc)) {
                                                echo $row['ship_rec_contact'];
                                            } else if (isset($wcr_rec_ctc)) {
                                                echo $wcr_rec_ctc;
                                            }
                                            ?>" <?php if ($act == '')
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
                                        <label class="form-label form_lbl" id="wcr_rec_add_lbl"
                                            for="wcr_rec_add">Receiver
                                            Address<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" name="wcr_rec_add" id="wcr_rec_add"
                                            value="<?php
                                            if (isset($dataExisted) && isset($row['ship_rec_add']) && !isset($wcr_rec_add)) {
                                                echo $row['ship_rec_add'];
                                            } else if (isset($wcr_rec_add)) {
                                                echo $wcr_rec_add;
                                            }
                                            ?>" <?php if ($act == '')
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
                            <label class="form-label form_lbl" id="wcr_remark_lbl" for="wcr_remark">Remark</label>
                            <textarea class="form-control" name="wcr_remark" id="wcr_remark" rows="3" <?php if ($act == '')
                                echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                                echo $row['remark'] ?></textarea>
                            <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                            </div>

                            <?php
                            if ($dataId) {
                                $orderRows = array();
                                $sumFinalAmount = 0.00;
                                $orderPackageCache = array();
                                $orderPayMethodCache = array();
                                $customerRowId = (int) $dataId;
                                $customerCode = isset($row['cust_id']) ? trim((string) $row['cust_id']) : '';

                                $orderWhere = "status='A' AND (cust_id='" . $customerRowId . "'";
                                if ($customerCode !== '') {
                                    $orderWhere .= " OR cust_id='" . mysqli_real_escape_string($finance_connect, $customerCode) . "'";
                                }
                                $orderWhere .= ")";

                                $orderSql = "SELECT * FROM " . WEB_ORDER_REQ . " WHERE " . $orderWhere . " ORDER BY id DESC";
                                $orderRst = mysqli_query($finance_connect, $orderSql);
                                if ($orderRst && $orderRst->num_rows > 0) {
                                    while ($orderRow = $orderRst->fetch_assoc()) {
                                        $orderRows[] = $orderRow;
                                        $sumFinalAmount += (float) (isset($orderRow['total']) ? $orderRow['total'] : 0);
                                    }

                                    foreach ($orderRows as $orderRow) {
                                        $pkgCsv = isset($orderRow['pkg']) ? trim((string) $orderRow['pkg']) : '';
                                        if (!isset($orderPackageCache[$pkgCsv])) {
                                            $orderPackageCache[$pkgCsv] = commonResolvePackageNamesFromCsv($pkgCsv, $connect);
                                        }

                                        $payMethodId = isset($orderRow['pay_method']) ? trim((string) $orderRow['pay_method']) : '';
                                        if (!isset($orderPayMethodCache[$payMethodId])) {
                                            $orderPayMethodCache[$payMethodId] = commonResolvePaymentMethodName($payMethodId, $finance_connect);
                                        }
                                    }
                                } else if (!$orderRst) {
                                    error_log("Website order list query failed: " . mysqli_error($finance_connect) . " SQL: " . $orderSql);
                                }
                            ?>
                            <div class="form-group mt-3">
                                <h5 class="mb-3">Order Records</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered mb-0" id="wcr_order_tbl">
                                        <thead>
                                            <tr>
                                                <th width="60">S/N</th>
                                                <th width="200">Action</th>
                                                <th>Order ID</th>
                                                <th>Purchase Date</th>
                                                <th>Received Date</th>
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
                                                    $orderNo = isset($orderRow['order_id']) ? $orderRow['order_id'] : '';
                                                    $orderDate = isset($orderRow['create_date']) ? $orderRow['create_date'] : '';
                                                    $receivedDate = isset($orderRow['received_date']) ? $orderRow['received_date'] : '';
                                                    $pkgCsv = isset($orderRow['pkg']) ? trim((string) $orderRow['pkg']) : '';
                                                    $payMethodId = isset($orderRow['pay_method']) ? trim((string) $orderRow['pay_method']) : '';
                                                    $orderPackage = isset($orderPackageCache[$pkgCsv]) ? $orderPackageCache[$pkgCsv] : '';
                                                    $buyerPayMethod = isset($orderPayMethodCache[$payMethodId]) ? $orderPayMethodCache[$payMethodId] : '';
                                                    $orderFees = isset($orderRow['shipping']) ? $orderRow['shipping'] : '0.00';
                                                    $finalAmount = isset($orderRow['total']) ? $orderRow['total'] : '0.00';
                                                    ?>
                                                    <tr>
                                                        <td><?= $orderSN++ ?></td>
                                                        <td>
                                                            <a class="btn btn-sm btn-rounded btn-primary" style="white-space:nowrap;"
                                                               href="<?= $SITEURL . '/website_customer_record.php?id=' . (int) $dataId . '&act=' . $act_2 . '&open_order_id=' . $orderId ?>">
                                                                Show Order Detail
                                                            </a>
                                                        </td>
                                                        <td><?= htmlspecialchars((string) $orderNo, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $receivedDate, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $orderPackage, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= htmlspecialchars((string) $buyerPayMethod, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td><?= commonFormatAmountRm($orderFees) ?></td>
                                                        <td><?= commonFormatAmountRm($finalAmount) ?></td>
                                                    </tr>
                                                <?php }
                                            } else { ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">No order records found.</td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="8" class="text-end">Sub-Total (RM)</th>
                                                <th><?= commonFormatAmountRm($sumFinalAmount) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <?php } ?>

                </form>

                <?php
                if ($dataId) {
                    $customerLogReturnUrl = $SITEURL . '/website_customer_record.php?id=' . (int) $dataId;
                    if ($act !== '') {
                        $customerLogReturnUrl .= '&act=' . urlencode((string) $act);
                    }

                    $customerLogContext = urlResolveUserRecordLogContext($connect, $connect, array(
                        'customer_id' => (int) $dataId,
                        'customer_column' => 'website_cust_id',
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

                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column mobile-sticky-form-actions-target">
                    <?php
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" form="FORForm" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" form="FORForm" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    ?>
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" form="FORForm" name="actionBtn" id="actionBtn" value="back">Back</button>
                </div>
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
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
    }
    ?>
    <?php
    echo customerTagRenderManager(
        $connect,
        $websiteCustomerTagPlatform,
        $websiteCustomerTagCustomerId,
        $pageTitle,
        $websiteCustomerTagDisplayName,
        array(
            'allow_manage' => isActionAllowed('Edit', $pinAccess) && $act !== 'I',
            'ui_state' => $websiteCustomerTagState,
            'active_tags' => $websiteCustomerActiveTags,
            'reset_draft_on_load' => ($act === 'I' && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'GET'),
            'draft_token' => $websiteCustomerTagDraftToken,
        )
    );
    ?>
    <script>
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        setButtonColor();
        preloader(300, action);

        <?php
        include "./js/website_customer_record.js"
        ?>
    </script>

</body>

</html>
