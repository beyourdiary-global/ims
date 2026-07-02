<?php
ob_start();

$currentPagePin = 91;
$pageTitle = "Lazada Customer Record (Deals)";

include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/../checkCurrentPagePin.php';
include_once ROOT . '/include/customer_tag.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
$pinAccess = checkCurrentPin($connect, $pageTitle);
include_once ROOT . '/include/user_record_log.php';

$tblName = LAZADA_CUST_RCD;

$dataId = input('id');
$act = input('act');
$pageAction = getPageAction($act);

if (!function_exists('resolveLookupValue')) {
    function resolveLookupValue($tblName, $rawValue, $displayField, $connect, $altDisplayField = '')
    {
        $rawValue = trim((string) $rawValue);
        $resolved = [
            'id' => '',
            'display' => '',
        ];

        if ($rawValue === '' || $rawValue === '0') {
            return $resolved;
        }

        $escapedValue = mysqli_real_escape_string($connect, (string) $rawValue);
        $result = getData("id,$displayField", "id = '$escapedValue'", 'LIMIT 1', $tblName, $connect);

        if ((!$result || $result->num_rows === 0) && $altDisplayField !== '') {
            $result = getData("id,$displayField", "$altDisplayField = '$escapedValue'", 'LIMIT 1', $tblName, $connect);
        }

        if ((!$result || $result->num_rows === 0) && $displayField !== $altDisplayField) {
            $result = getData("id,$displayField", "$displayField = '$escapedValue'", 'LIMIT 1', $tblName, $connect);
        }

        if ($result && $result->num_rows > 0) {
            $lookupRow = $result->fetch_assoc();
            $resolved['id'] = $lookupRow['id'];
            $resolved['display'] = $lookupRow[$displayField];
        } else {
            $resolved['id'] = $rawValue;
            $resolved['display'] = $rawValue;
        }

        return $resolved;
    }
}

$redirectPage = $SITEURL . '/finance/lazada_cust_rcd_table.php';
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

if ($dataId && numberInput('open_order_id') !== '') {
    $openOrderId = (int) numberInput('open_order_id');
    if ($openOrderId > 0) {
        $customerRowId = (int) $dataId;
        $customerCode = (isset($row['lcr_id']) ? trim((string) $row['lcr_id']) : '');
        $orderWhere = "id='" . $openOrderId . "' AND status='A' AND (cust_id='" . $customerRowId . "'";
        if ($customerCode !== '') {
            $orderWhere .= " OR cust_id='" . mysqli_real_escape_string($connect, $customerCode) . "'";
        }
        $orderWhere .= ")";

        $orderRst = getData('id,oder_number', $orderWhere, 'LIMIT 1', LAZADA_ORDER_REQ, $connect);
        if ($orderRst && $orderRst->num_rows > 0) {
            $orderRow = $orderRst->fetch_assoc();
            $orderNo = isset($orderRow['oder_number']) ? $orderRow['oder_number'] : ('#' . $openOrderId);
            $log = [
                'log_act' => 'View',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => "order_id=" . $openOrderId,
                'query_table' => LAZADA_ORDER_REQ,
                'act_msg' => USER_NAME . " opened Lazada order detail [<b>" . $orderNo . "</b>] from <b><i>" . $pageTitle . "</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo "<script>location.href='" . $SITEURL . "/finance/lazada_order_req.php?id=" . $openOrderId . "&act=E';</script>";
            exit;
        }
    }
}

$lazadaCustomerTagPlatform = 'lazada';
$lazadaCustomerTagCustomerId = (isset($row['id']) ? (int) $row['id'] : 0);
$lazadaCustomerTagDisplayName = isset($row['name']) ? trim((string) $row['name']) : '';
$lazadaCustomerTagDraftToken = customerTagResolveDraftToken($act);
$lazadaCustomerFreshAddPage = ($act === 'I' && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'GET' && !customerTagIsAjaxRequest());
customerTagResetDraftOnFreshAddPage($lazadaCustomerTagPlatform, $act, $lazadaCustomerTagDraftToken);
$lazadaCustomerTagState = array();
if ($lazadaCustomerFreshAddPage) {
    customerTagClearDraftTags($lazadaCustomerTagPlatform, $lazadaCustomerTagDraftToken);
}
$lazadaCustomerTagState = customerTagHandlePost($connect, $lazadaCustomerTagPlatform, $lazadaCustomerTagCustomerId, $pageTitle, $lazadaCustomerTagDisplayName, $lazadaCustomerTagDraftToken);
$lazadaCustomerActiveTags = $lazadaCustomerFreshAddPage ? array() : customerTagGetDisplayTags($connect, $lazadaCustomerTagPlatform, $lazadaCustomerTagCustomerId, $lazadaCustomerTagDraftToken);
$lazadaCustomerDraftTagIds = customerTagExtractTagIds($lazadaCustomerActiveTags);

$lazadaCustomerLabelMeta = array();
$lazadaCustomerLabelDisplayHtml = '';
if (isset($dataExisted) && !empty($dataId) && $act !== 'I' && isset($row['id']) && (int) $row['id'] > 0) {
    $lazadaCustomerLabelMap = customerLabelGetCustomerLabelMap($connect, 'lazada', array((int) $row['id']));
    $lazadaCustomerLabelMeta = isset($lazadaCustomerLabelMap[(int) $row['id']]) ? $lazadaCustomerLabelMap[(int) $row['id']] : array();
    $lazadaCustomerLabelDisplayHtml = customerLabelRenderPageHeader($lazadaCustomerLabelMeta);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('actionBtn');
    if ($action === '' || $action === null) {
        $action = post('actionBtnHidden');
    }
    if (($action === '' || $action === null) && ($act === 'I' || $act === 'E')) {
        $action = ($act === 'I') ? 'addRecord' : 'updRecord';
    }

    if ($action === '' || $action === null) {
        $action = '';
    }

    if ($action !== '') {

    $lcr_id = postSpaceFilter('lcr_id');
    $lcr_name = postSpaceFilter('lcr_name');
    $lcr_email = postSpaceFilter('lcr_email');
    $lcr_phone = postSpaceFilter('lcr_phone');
    $lcr_pic = postSpaceFilter('lcr_pic_hidden');
    $lcr_country = postSpaceFilter('lcr_country_hidden');
    $lcr_brand = postSpaceFilter('lcr_brand_hidden');
    $lcr_series = postSpaceFilter('lcr_series_hidden');
    $lcr_pic_text = postSpaceFilter('lcr_pic');
    $lcr_country_text = postSpaceFilter('lcr_country');
    $lcr_brand_text = postSpaceFilter('lcr_brand');
    $lcr_series_text = postSpaceFilter('lcr_series');
    $lcr_rec_name = postSpaceFilter('lcr_rec_name');
    $lcr_rec_ctc = postSpaceFilter('lcr_rec_ctc');
    $lcr_rec_add = postSpaceFilter('lcr_rec_add');
    $lcr_remark = postSpaceFilter('lcr_remark');

    if ($lcr_pic === '' || $lcr_pic === '0') {
        $resolvedPic = resolveLookupValue(USR_USER, $lcr_pic_text, 'name', $connect);
        $lcr_pic = (string) $resolvedPic['id'];
    }
    if ($lcr_country === '' || $lcr_country === '0') {
        $resolvedCountry = resolveLookupValue(COUNTRIES, $lcr_country_text, 'nicename', $connect, 'name');
        $lcr_country = (string) $resolvedCountry['id'];
    }
    if ($lcr_brand === '' || $lcr_brand === '0') {
        $resolvedBrand = resolveLookupValue(BRAND, $lcr_brand_text, 'name', $connect);
        $lcr_brand = (string) $resolvedBrand['id'];
    }
    if ($lcr_series === '' || $lcr_series === '0') {
        $resolvedSeries = resolveLookupValue(BRD_SERIES, $lcr_series_text, 'name', $connect);
        $lcr_series = (string) $resolvedSeries['id'];
    }

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':

        

            if (!$lcr_id) {
                $lcr_id_err = "Customer ID cannot be empty.";
                break;
            } else if (!$lcr_name) {
                $name_err = "Name cannot be empty.";
                break;
            } else if (!$lcr_email) {
                $email_err = "Email cannot be empty.";
                break;
            } else if (!$lcr_phone) {
                $phone_err = "Phone cannot be empty.";
                break;
            } else if (!$lcr_pic && $lcr_pic < 1) {
                $pic_err = "Sales Person-In-Charge cannot be empty.";
                break;
            } else if (!$lcr_country && $lcr_country < 1) {
                $country_err = "Country cannot be empty.";
                break;
            } else if (!$lcr_brand && $lcr_brand < 1) {
                $brand_err = "Brand cannot be empty.";
                break;
            } else if (!$lcr_series && $lcr_series < 1) {
                $series_err = "Series cannot be empty.";
                break;
            } else if (!$lcr_rec_name) {
                $rec_name_err = "Receiver Name cannot be empty.";
                break;
            } else if (!$lcr_rec_ctc) {
                $rec_ctc_err = "Receiver Contact cannot be empty.";
                break;
            } else if (!$lcr_rec_add) {
                $rec_add_err = "Receiver Address cannot be empty.";
                break;
            } else if ($action == 'addRecord') {
                try {
                    //check values
                    if ($lcr_id) {
                        array_push($newvalarr, $lcr_name);
                        array_push($datafield, 'lcr_id');
                    }

                    if ($lcr_name) {
                        array_push($newvalarr, $lcr_name);
                        array_push($datafield, 'name');
                    }

                    if ($lcr_email) {
                        array_push($newvalarr, $lcr_email);
                        array_push($datafield, 'email');
                    }

                    if ($lcr_phone) {
                        array_push($newvalarr, $lcr_phone);
                        array_push($datafield, 'phone');
                    }

                    if ($lcr_pic) {
                        array_push($newvalarr, $lcr_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($lcr_country) {
                        array_push($newvalarr, $lcr_country);
                        array_push($datafield, 'country');
                    }

                    if ($lcr_brand) {
                        array_push($newvalarr, $lcr_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($lcr_series) {
                        array_push($newvalarr, $lcr_series);
                        array_push($datafield, 'series');
                    }

                    if ($lcr_rec_name) {
                        array_push($newvalarr, $lcr_rec_name);
                        array_push($datafield, 'receiver name');
                    }

                    if ($lcr_rec_ctc) {
                        array_push($newvalarr, $lcr_rec_ctc);
                        array_push($datafield, 'receiver contact');
                    }

                    if ($lcr_rec_add) {
                        array_push($newvalarr, $lcr_rec_add);
                        array_push($datafield, 'receiver address');
                    }

                    if ($lcr_remark) {
                        array_push($newvalarr, $lcr_remark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(lcr_id,name,email,phone,sales_pic,country,brand,series,ship_rec_name,ship_rec_add,ship_rec_contact,remark,create_by,create_date,create_time) VALUES ('$lcr_id','$lcr_name','$lcr_email','$lcr_phone','$lcr_pic','$lcr_country','$lcr_brand','$lcr_series','$lcr_rec_name','$lcr_rec_add','$lcr_rec_ctc','$lcr_remark','" . USER_ID . "',curdate(),curtime())";
                    // Execute the query
                    $returnData = mysqli_query($connect, $query);
                    if ($returnData) {
                        $dataId = $connect->insert_id;
                        customerTagApplyDraftTagsToCustomer($connect, $lazadaCustomerTagPlatform, $dataId, $pageTitle, $lcr_name, customerTagGetPostedDraftTagIds(), $lazadaCustomerTagDraftToken);
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
                    if ($row['lcr_id'] != $lcr_id) {
                        array_push($oldvalarr, $row['lcr_id']);
                        array_push($chgvalarr, $lcr_id);
                        array_push($datafield, 'lcr_id');
                    }

                    if ($row['name'] != $lcr_name) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $lcr_name);
                        array_push($datafield, 'name');
                    }

                    if ($row['email'] != $lcr_email) {
                        array_push($oldvalarr, $row['email']);
                        array_push($chgvalarr, $lcr_email);
                        array_push($datafield, 'email');
                    }

                    if ($row['phone'] != $lcr_phone) {
                        array_push($oldvalarr, $row['phone']);
                        array_push($chgvalarr, $lcr_phone);
                        array_push($datafield, 'phone');
                    }

                    if ($row['sales_pic'] != $lcr_pic) {
                        array_push($oldvalarr, $row['sales_pic']);
                        array_push($chgvalarr, $lcr_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['country'] != $lcr_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $lcr_country);
                        array_push($datafield, 'country');
                    }

                    if ($row['brand'] != $lcr_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $lcr_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['series'] != $lcr_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $lcr_series);
                        array_push($datafield, 'series');
                    }

                    if ($row['ship_rec_name'] != $lcr_rec_name) {
                        array_push($oldvalarr, $row['ship_rec_name']);
                        array_push($chgvalarr, $lcr_rec_name);
                        array_push($datafield, 'shipping receiver name');
                    }

                    if ($row['ship_rec_contact'] != $lcr_rec_ctc) {
                        array_push($oldvalarr, $row['ship_rec_contact']);
                        array_push($chgvalarr, $lcr_rec_ctc);
                        array_push($datafield, 'shipping receiver contact');
                    }

                    if ($row['ship_rec_add'] != $lcr_rec_add) {
                        array_push($oldvalarr, $row['ship_rec_add']);
                        array_push($chgvalarr, $lcr_rec_add);
                        array_push($datafield, 'shipping receiver address');
                    }

                    if ($row['remark'] != $lcr_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $lcr_remark == '' ? 'Empty Value' : $lcr_remark);
                        array_push($datafield, 'remark');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET lcr_id = '$lcr_id', name = '$lcr_name', email = '$lcr_email', phone = '$lcr_phone', sales_pic = '$lcr_pic', country = '$lcr_country', brand = '$lcr_brand', series = '$lcr_series', ship_rec_name = '$lcr_rec_name', ship_rec_add = '$lcr_rec_add', ship_rec_contact = '$lcr_rec_ctc', remark ='$lcr_remark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
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
                    <input type="hidden" name="customerTagDraftIds" class="customer-tag-draft-input" data-platform="<?= htmlspecialchars($lazadaCustomerTagPlatform, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(implode(',', $lazadaCustomerDraftTagIds), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="customerTagDraftToken" value="<?= htmlspecialchars($lazadaCustomerTagDraftToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group mb-5">
                        <?php $lazadaCustomerPageActionTitle = displayPageAction($act, $pageTitle); ?>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <h2 class="mb-0 customer-tag-page-title" data-base-title="<?= htmlspecialchars($lazadaCustomerPageActionTitle, ENT_QUOTES, 'UTF-8') ?>">
                                <?php
                                echo customerTagRenderTitle($lazadaCustomerPageActionTitle, $lazadaCustomerActiveTags);
                                ?>
                            </h2>
                            <?php echo customerTagRenderManageButton($lazadaCustomerTagPlatform, $lazadaCustomerTagCustomerId, isActionAllowed('Edit', $pinAccess) && $act !== 'I'); ?>
                        </div>
                        <?php echo $lazadaCustomerLabelDisplayHtml; ?>
                    </div>

                    <div id="err_msg" class="mb-3">
                        <span class="mt-n2" style="font-size: 21px;">
                            <?php if (isset($err1))
                                echo $err1; ?>
                        </span>
                    </div>

                    <div class="form-group">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label form_lbl" id="lcr_id_lbl" for="lcr_id">Customer ID<span class="requireRed">*</span></label>
            <?php 
            unset($echoVal);
            if (isset($row['lcr_id']))
            $echoVal = $row['lcr_id'];
            ?>
            <input class="form-control" type="text" name="lcr_id" id="lcr_id" value="<?php echo !empty($echoVal) ? $row['lcr_id'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>       
            <?php if (isset($lcr_id_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $lcr_id_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label form_lbl" id="lcr_name_lbl" for="lcr_name">Name<span class="requireRed">*</span></label>
            <?php 
            unset($echoVal);
            // FIX: Changed $row['lcr_name'] to $row['name'] to match the database column
            if (isset($row['name']))
            $echoVal = $row['name'];
            ?>
            <div class="customer-field-with-label">
                <input class="form-control" type="text" name="lcr_name" id="lcr_name" value="<?php echo !empty($echoVal) ? $row['name'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>
                <?php echo customerLabelRenderInlineSegmentationBadge($lazadaCustomerLabelMeta); ?>
            </div>
            <?php if (isset($name_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $name_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="form-group">
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label form_lbl" id="lcr_email_lbl" for="lcr_email">Customer Email<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="lcr_email" id="lcr_email" value="<?php
                if (isset($dataExisted) && isset($row['email']) && !isset($email)) {
                    echo $row['email'];
                } else if (isset($dataExisted) && isset($row['email']) && isset($lcr_email)) {
                    echo $lcr_email;
                } else {
                    echo '';
                }
                ?>" <?php if ($act == '') echo 'readonly' ?>>
            <?php if (isset($email_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $email_err; ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label form_lbl" id="lcr_phone_lbl" for="lcr_phone">Customer Phone<span class="requireRed">*</span></label>
            <?php 
            unset($echoVal);
            if (isset($row['phone']))
            $echoVal = $row['phone'];
            ?>
            <input class="form-control" type="text" name="lcr_phone" id="lcr_phone" value="<?php echo !empty($echoVal) ? $row['phone'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>      
            <?php if (isset($phone_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $phone_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
        
<div class="form-group">
    <div class="row">
    <div class="col-md-3 mb-3 autocomplete">
    <label class="form-label form_lbl" id="lcr_pic_lbl" for="lcr_pic">Sales Person In Charge<span class="requireRed">*</span></label>
    <?php
    $picData = ['id' => '', 'display' => ''];
    if (isset($lcr_pic_text) || isset($lcr_pic)) {
        $picData['id'] = isset($lcr_pic) ? $lcr_pic : '';
        $picData['display'] = isset($lcr_pic_text) ? $lcr_pic_text : '';
    } else if ($act == 'I') {
        $picData = resolveLookupValue(USR_USER, USER_ID, 'name', $connect);
    } else if (isset($row['sales_pic']) && $row['sales_pic'] !== '') {
        $picData = resolveLookupValue(USR_USER, $row['sales_pic'], 'name', $connect);
    }
    ?>
    <input class="form-control" type="text" name="lcr_pic" id="lcr_pic" <?php if ($act == '') echo 'disabled' ?> value="<?php echo htmlspecialchars((string) $picData['display'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="lcr_pic_hidden" id="lcr_pic_hidden" value="<?php echo htmlspecialchars((string) $picData['id'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (isset($pic_err)) { ?>
        <div id="err_msg">
            <span class="mt-n1">
                <?php echo $pic_err; ?>
            </span>
        </div>
    <?php } ?>
</div>

        <div class="col-md-3 mb-3 autocomplete country-autocomplete">
            <label class="form-label form_lbl" id="lcr_country_lbl" for="lcr_country">Country<span class="requireRed">*</span></label>
            <?php
            $countryData = ['id' => '', 'display' => ''];
            if (isset($lcr_country_text) || isset($lcr_country)) {
                $countryData['id'] = isset($lcr_country) ? $lcr_country : '';
                $countryData['display'] = isset($lcr_country_text) ? $lcr_country_text : '';
            } else if (isset($row['country']) && $row['country'] !== '') {
                $countryData = resolveLookupValue(COUNTRIES, $row['country'], 'nicename', $connect, 'name');
            }
            ?>
            <input class="form-control" type="text" name="lcr_country" id="lcr_country" <?php if ($act == '') echo 'disabled' ?> value="<?php echo htmlspecialchars((string) $countryData['display'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="lcr_country_hidden" id="lcr_country_hidden" value="<?php echo htmlspecialchars((string) $countryData['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php if (isset($country_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1">
                        <?php echo $country_err; ?>
                    </span>
                </div>
            <?php } ?>
        </div>

        <div class="col-md-3 mb-3 autocomplete">
            <label class="form-label form_lbl" id="lcr_brand_lbl" for="lcr_brand">Brand<span class="requireRed">*</span></label>
            <?php
            $brandData = ['id' => '', 'display' => ''];
            if (isset($lcr_brand_text) || isset($lcr_brand)) {
                $brandData['id'] = isset($lcr_brand) ? $lcr_brand : '';
                $brandData['display'] = isset($lcr_brand_text) ? $lcr_brand_text : '';
            } else if (isset($row['brand']) && $row['brand'] !== '') {
                $brandData = resolveLookupValue(BRAND, $row['brand'], 'name', $connect);
            }
            ?>
            <input class="form-control" type="text" name="lcr_brand" id="lcr_brand" <?php if ($act == '') echo 'disabled' ?> value="<?php echo htmlspecialchars((string) $brandData['display'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="lcr_brand_hidden" id="lcr_brand_hidden" value="<?php echo htmlspecialchars((string) $brandData['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php if (isset($brand_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1">
                        <?php echo $brand_err; ?>
                    </span>
                </div>
            <?php } ?>
        </div>
        <div class="col-md-3 mb-3 autocomplete">
        <label class="form-label form_lbl" id="lcr_series_lbl" for="lcr_series">Series<span class="requireRed">*</span></label>
            <?php
            $seriesData = ['id' => '', 'display' => ''];
            if (isset($lcr_series_text) || isset($lcr_series)) {
                $seriesData['id'] = isset($lcr_series) ? $lcr_series : '';
                $seriesData['display'] = isset($lcr_series_text) ? $lcr_series_text : '';
            } else if (isset($row['series']) && $row['series'] !== '') {
                $seriesData = resolveLookupValue(BRD_SERIES, $row['series'], 'name', $connect);
            }
            ?>
            <input class="form-control" type="text" name="lcr_series" id="lcr_series" <?php if ($act == '') echo 'disabled' ?> value="<?php echo htmlspecialchars((string) $seriesData['display'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="lcr_series_hidden" id="lcr_series_hidden" value="<?php echo htmlspecialchars((string) $seriesData['id'], ENT_QUOTES, 'UTF-8'); ?>">

            <?php if (isset($lcr_series_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $lcr_series_err; ?></span>
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
                                        <label class="form-label form_lbl" id="lcr_rec_name_lbl"
                                            for="lcr_rec_name">Receiver
                                            Name<span class="requireRed">*</span></label>
                                            <?php 
                                            unset($echoVal);
                                            if (isset($row['ship_rec_name']))
                                            $echoVal = $row['ship_rec_name'];
                                            ?>
                                            <input class="form-control" type="text" name="lcr_rec_name" id="lcr_rec_name" value="<?php echo !empty($echoVal) ? $row['ship_rec_name'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>       
                                        <?php if (isset($rec_name_err)) { ?>
                                            <div id="err_msg">
                                                <span class="mt-n1">
                                                    <?php echo $rec_name_err; ?>
                                                </span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" id="lcr_rec_ctc_lbl"
                                            for="lcr_rec_ctc">Receiver
                                            Contact<span class="requireRed">*</span></label>
                                            <?php 
                                            unset($echoVal);
                                            if (isset($row['ship_rec_contact']))
                                            $echoVal = $row['ship_rec_contact'];
                                            ?>
                                            <input class="form-control" type="text" name="lcr_rec_ctc" id="lcr_rec_ctc" value="<?php echo !empty($echoVal) ? $row['ship_rec_contact'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>       
                                        <?php if (isset($rec_ctc_err)) { ?>
                                            <div id="err_msg">
                                                <span class="mt-n1">
                                                    <?php echo $rec_ctc_err; ?>
                                                </span>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label form_lbl" id="lcr_rec_add_lbl"
                                            for="lcr_rec_add">Receiver
                                            Address<span class="requireRed">*</span></label>
                                            <?php 
                                            unset($echoVal);
                                            if (isset($row['ship_rec_add']))
                                            $echoVal = $row['ship_rec_add'];
                                            ?>
                                            <input class="form-control" type="text" name="lcr_rec_add" id="lcr_rec_add" value="<?php echo !empty($echoVal) ? $row['ship_rec_add'] : '' ?>" <?php if ($act == '')echo 'disabled' ?>>       
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
                            <label class="form-label form_lbl" id="lcr_remark_lbl" for="lcr_remark">Remark</label>
                            <textarea class="form-control" name="lcr_remark" id="lcr_remark" rows="3" <?php if ($act == '')
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
                                $customerCode = isset($row['lcr_id']) ? trim((string) $row['lcr_id']) : '';

                                $orderWhere = "status='A' AND (cust_id='" . $customerRowId . "'";
                                if ($customerCode !== '') {
                                    $orderWhere .= " OR cust_id='" . mysqli_real_escape_string($connect, $customerCode) . "'";
                                }
                                $orderWhere .= ")";

                                $orderSql = "SELECT * FROM " . LAZADA_ORDER_REQ . " WHERE " . $orderWhere . " ORDER BY id DESC";
                                $orderRst = mysqli_query($connect, $orderSql);
                                if ($orderRst && $orderRst->num_rows > 0) {
                                    while ($orderRow = $orderRst->fetch_assoc()) {
                                        $orderRows[] = $orderRow;
                                        $sumFinalAmount += (float) (isset($orderRow['final_income']) ? $orderRow['final_income'] : 0);
                                    }

                                    foreach ($orderRows as $orderRow) {
                                        $pkgCsv = isset($orderRow['pkg']) ? trim((string) $orderRow['pkg']) : '';
                                        if (!isset($orderPackageCache[$pkgCsv])) {
                                            $orderPackageCache[$pkgCsv] = commonResolvePackageNamesFromCsv($pkgCsv, $connect);
                                        }

                                        $payMethodId = isset($orderRow['pay_meth']) ? trim((string) $orderRow['pay_meth']) : '';
                                        if (!isset($orderPayMethodCache[$payMethodId])) {
                                            $orderPayMethodCache[$payMethodId] = commonResolvePaymentMethodName($payMethodId, $finance_connect);
                                        }
                                    }
                                } else if (!$orderRst) {
                                    error_log("Lazada order list query failed: " . mysqli_error($connect) . " SQL: " . $orderSql);
                                }
                            ?>
                            <div class="form-group mt-3">
                                <h5 class="mb-3">Order Records</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered mb-0" id="lcr_order_tbl">
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
                                                    $orderNo = isset($orderRow['oder_number']) ? $orderRow['oder_number'] : '';
                                                    $orderDate = isset($orderRow['create_date']) ? $orderRow['create_date'] : '';
                                                    $receivedDate = isset($orderRow['received_date']) ? $orderRow['received_date'] : '';
                                                    $pkgCsv = isset($orderRow['pkg']) ? trim((string) $orderRow['pkg']) : '';
                                                    $payMethodId = isset($orderRow['pay_meth']) ? trim((string) $orderRow['pay_meth']) : '';
                                                    $orderPackage = isset($orderPackageCache[$pkgCsv]) ? $orderPackageCache[$pkgCsv] : '';
                                                    $buyerPayMethod = isset($orderPayMethodCache[$payMethodId]) ? $orderPayMethodCache[$payMethodId] : '';
                                                    $orderFees = isset($orderRow['pay_fee']) ? $orderRow['pay_fee'] : '0.00';
                                                    $finalAmount = isset($orderRow['final_income']) ? $orderRow['final_income'] : '0.00';
                                                    ?>
                                                    <tr>
                                                        <td><?= $orderSN++ ?></td>
                                                        <td>
                                                            <a class="btn btn-sm btn-rounded btn-primary" style="white-space:nowrap;"
                                                               href="<?= $SITEURL . '/finance/lazada_cust_rcd.php?id=' . (int) $dataId . '&act=' . $act_2 . '&open_order_id=' . $orderId ?>">
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
                    $customerLogReturnUrl = $SITEURL . '/finance/lazada_cust_rcd.php?id=' . (int) $dataId;
                    if ($act !== '') {
                        $customerLogReturnUrl .= '&act=' . urlencode((string) $act);
                    }

                    $customerLogContext = urlResolveUserRecordLogContext($connect, $connect, array(
                        'customer_id' => (int) $dataId,
                        'customer_column' => 'lazada_cust_id',
                        'customer_label' => isset($row['name']) ? $row['name'] : '',
                        'return_url' => $customerLogReturnUrl,
                        'ajax_url' => $SITEURL . '/users/user_record_log.php',
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
        $lazadaCustomerTagPlatform,
        $lazadaCustomerTagCustomerId,
        $pageTitle,
        $lazadaCustomerTagDisplayName,
        array(
            'allow_manage' => isActionAllowed('Edit', $pinAccess) && $act !== 'I',
            'ui_state' => $lazadaCustomerTagState,
            'active_tags' => $lazadaCustomerActiveTags,
            'reset_draft_on_load' => ($act === 'I' && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'GET'),
            'draft_token' => $lazadaCustomerTagDraftToken,
        )
    );
    ?>
    <script>
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ' '; ?>";
        window.lazadaCustomerRecordConfig = {
            siteURL: "<?= $SITEURL ?>",
            tables: {
                user: "<?= USR_USER ?>",
                countries: "<?= COUNTRIES ?>",
                brand: "<?= BRAND ?>",
                series: "<?= BRD_SERIES ?>"
            }
        };

        checkCurrentPage(page, action);
        setButtonColor();
        preloader(300, action);

    </script>
    <script src="<?= $SITEURL ?>/js/lazada_cust_rcd.js"></script>

</body>

</html>
