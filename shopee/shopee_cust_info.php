<?php
$pageTitle = "Shopee Customer Record";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/user_record_log.php';

$tblName = SHOPEE_CUST_INFO;

if (!function_exists('scrEsc')) {
    function scrEsc($conn, $val)
    {
        return mysqli_real_escape_string($conn, (string) $val);
    }
}

//Current Page Action And Data ID
$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addRecord' : 'updRecord';

if (!function_exists('formatAmountRm')) {
    function formatAmountRm($val)
    {
        $num = is_numeric($val) ? (float) $val : 0;
        return number_format($num, 2, '.', '');
    }
}

if (!function_exists('resolvePackageNamesFromCsv')) {
    function resolvePackageNamesFromCsv($packageCsv, $connect)
    {
        $packageCsv = trim((string) $packageCsv);
        if ($packageCsv === '') {
            return '';
        }

        $packageIds = array_filter(array_map('trim', explode(',', $packageCsv)), function ($v) {
            return $v !== '';
        });

        // Collect numeric IDs and ensure uniqueness for the batched query
        $numericIds = array();
        foreach ($packageIds as $id) {
            if (ctype_digit((string) $id)) {
                $numericIds[] = (int) $id;
            }
        }
        $numericIds = array_values(array_unique($numericIds));

        if (empty($numericIds)) {
            // No valid numeric IDs; mirror previous behavior by returning the original CSV
            return $packageCsv; 
        }

        // Build a single batched query to fetch all package names
        $idList = implode(',', $numericIds);
        $sql = "SELECT id, name FROM " . PKG . " WHERE id IN (" . $idList . ")";
        $result = mysqli_query($connect, $sql);

        $idToName = array();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                if (isset($row['id'])) {
                    $idToName[(int) $row['id']] = isset($row['name']) ? $row['name'] : '';
                }
            }
        }

        $names = array();
        // Preserve the original order (and duplicates) of IDs when building the name list
        foreach ($packageIds as $id) {
            if (!ctype_digit((string) $id)) {
                continue;
            }
            $intId = (int) $id;
            if (isset($idToName[$intId]) && $idToName[$intId] !== '') {
                $names[] = $idToName[$intId];
            }
        }

        if (empty($names)) {
            return $packageCsv;
        }
        return implode(', ', $names);
    }
}

$redirect_page = $SITEURL . '/shopee/shopee_cust_info_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if (!function_exists('resolveLookupValue')) {
    function resolveLookupValue($tableName, $rawValue, $displayField, $connect, $altDisplayField = '')
    {
        $rawValue = trim((string) $rawValue);
        $resolved = [
            'id' => '',
            'display' => '',
        ];

        if ($rawValue === '' || $rawValue === '0') {
            return $resolved;
        }

        // Escape the raw value before using it in SQL conditions to prevent SQL injection
        $escapedValue = mysqli_real_escape_string($connect, (string) $rawValue);

        $rst = getData("id,$displayField", "id = '$escapedValue'", 'LIMIT 1', $tableName, $connect);

        if ((!$rst || $rst->num_rows === 0) && $altDisplayField !== '') {
            $rst = getData("id,$displayField", "$altDisplayField = '$escapedValue'", 'LIMIT 1', $tableName, $connect);
        }

        if ((!$rst || $rst->num_rows === 0) && $displayField !== $altDisplayField) {
            $rst = getData("id,$displayField", "$displayField = '$escapedValue'", 'LIMIT 1', $tableName, $connect);
        }

        if ($rst && $rst->num_rows > 0) {
            $lookupRow = $rst->fetch_assoc();
            $resolved['id'] = $lookupRow['id'];
            $resolved['display'] = $lookupRow[$displayField];
        } else {
            // Keep original value for non-empty non-zero legacy text values.
            $resolved['id'] = $rawValue;
            $resolved['display'] = $rawValue;
        }

        return $resolved;
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

if (!($dataID) && !($act)) {
    echo '<script>
    alert("Invalid action.");
    window.location.href = "' . $redirect_page . '"; // Redirect to previous page
    </script>';
}

if ($dataID && isset($_GET['open_order_id'])) {
    $openOrderId = (int) $_GET['open_order_id'];
    if ($openOrderId > 0) {
        $orderRst = getData('id,orderID', "id='$openOrderId'", 'LIMIT 1', SHOPEE_SG_ORDER_REQ, $finance_connect);
        if ($orderRst && $orderRst->num_rows > 0) {
            $orderRow = $orderRst->fetch_assoc();
            $orderNo = isset($orderRow['orderID']) ? $orderRow['orderID'] : ('#' . $openOrderId);
            $log = [
                'log_act' => 'View',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => "order_id=" . $openOrderId,
                'query_table' => SHOPEE_SG_ORDER_REQ,
                'act_msg' => USER_NAME . " opened Shopee order detail [<b>" . $orderNo . "</b>] from <b><i>" . $pageTitle . "</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);
        }
        echo "<script>location.href='" . $SITEURL . "/shopee/shopee_order_req.php?id=" . $openOrderId . "';</script>";
        exit;
    }
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, (isset($row['buyer_username']) ? $row['buyer_username'] : ''), $finance_connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
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

    switch ($action) {
        case 'addRecord':
        case 'updRecord':

            $scr_username = postSpaceFilter("scr_username");
            $scr_pic = postSpaceFilter("scr_pic_hidden");
            $scr_country = postSpaceFilter("scr_country_hidden");
            $scr_brand = postSpaceFilter("scr_brand_hidden");
            $scr_series = postSpaceFilter("scr_series_hidden");
            $scr_contact = postSpaceFilter("scr_contact");
            $scr_remark = postSpaceFilter("scr_remark");
            $scr_pic_text = postSpaceFilter("scr_pic");
            $scr_country_text = postSpaceFilter("scr_country");
            $scr_brand_text = postSpaceFilter("scr_brand");
            $scr_series_text = postSpaceFilter("scr_series");

            // Normalize hidden lookup IDs. If hidden value is empty/0, resolve by typed text.
            if ($scr_pic === '' || $scr_pic === '0') {
                $resolvedPic = resolveLookupValue(USR_USER, $scr_pic_text, 'name', $connect);
                $scr_pic = (string) $resolvedPic['id'];
            }
            if ($scr_country === '' || $scr_country === '0') {
                $resolvedCountry = resolveLookupValue(COUNTRIES, $scr_country_text, 'nicename', $connect, 'name');
                $scr_country = (string) $resolvedCountry['id'];
            }
            if ($scr_brand === '' || $scr_brand === '0') {
                $resolvedBrand = resolveLookupValue(BRAND, $scr_brand_text, 'name', $connect);
                $scr_brand = (string) $resolvedBrand['id'];
            }
            if ($scr_series === '' || $scr_series === '0') {
                $resolvedSeries = resolveLookupValue(BRD_SERIES, $scr_series_text, 'name', $connect);
                $scr_series = (string) $resolvedSeries['id'];
            }

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (!$scr_username) {
                $username_err = "Shopee Buyer Username cannot be empty";
                break;
            } else if (!$scr_pic) {
                $pic_err = "Sales Person In Charge cannot be empty";
                break;
            } else if (!$scr_country) {
                $country_err = "Country cannot be empty";
                break;
            } else if (!$scr_brand) {
                $brand_err = "Brand cannot be empty";
                break;
            } else if (!$scr_series) {
                $series_err = "Series cannot be empty";
                break;
            } else if (!$scr_contact) {
                $contact_err = "Whatsapp / Contact Number cannot be empty";
                break;
            } else if ($action == 'addRecord') {
                try {

                    // check value

                    if ($scr_username) {
                        array_push($newvalarr, $scr_username);
                        array_push($datafield, 'buyer_username');
                    }

                    if ($scr_pic) {
                        array_push($newvalarr, $scr_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($scr_country) {
                        array_push($newvalarr, $scr_country);
                        array_push($datafield, 'country');
                    }

                    if ($scr_brand) {
                        array_push($newvalarr, $scr_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($scr_series) {
                        array_push($newvalarr, $scr_series);
                        array_push($datafield, 'series');
                    }

                    if ($scr_contact) {
                        array_push($newvalarr, $scr_contact);
                        array_push($datafield, 'contact_no');
                    }

                    if ($scr_remark) {
                        array_push($newvalarr, $scr_remark);
                        array_push($datafield, 'remark');
                    }


                    $query = "INSERT INTO " . $tblName . "(buyer_username,pic,country,brand,series,contact_no,remark,create_by,create_date,create_time) VALUES ('" . scrEsc($finance_connect, $scr_username) . "','" . scrEsc($finance_connect, $scr_pic) . "','" . scrEsc($finance_connect, $scr_country) . "','" . scrEsc($finance_connect, $scr_brand) . "','" . scrEsc($finance_connect, $scr_series) . "','" . scrEsc($finance_connect, $scr_contact) . "','" . scrEsc($finance_connect, $scr_remark) . "','" . USER_ID . "',curdate(),curtime())";

                    // Execute the query
                    $returnData = mysqli_query($finance_connect, $query);
                    if ($returnData) {
                        $dataID = $finance_connect->insert_id;
                        $_SESSION['tempValConfirmBox'] = true;
                    } else {
                        $errorMsg = mysqli_error($finance_connect);
                        $err1 = "Failed to add record: " . $errorMsg;
                        $act = "F";
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $err1 = "Failed to add record: " . $errorMsg;
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $rst->fetch_assoc();

                    // check value

                    if ($row['buyer_username'] != $scr_username) {
                        array_push($oldvalarr, $row['buyer_username']);
                        array_push($chgvalarr, $scr_username);
                        array_push($datafield, 'buyer_username');
                    }

                    if ($row['pic'] != $scr_pic) {
                        array_push($oldvalarr, $row['pic']);
                        array_push($chgvalarr, $scr_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['country'] != $scr_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $scr_country);
                        array_push($datafield, 'country');
                    }

                    if ($row['brand'] != $scr_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $scr_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['series'] != $scr_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $scr_series);
                        array_push($datafield, 'series');
                    }

                    if ((isset($row['contact_no']) ? $row['contact_no'] : '') != $scr_contact) {
                        array_push($oldvalarr, isset($row['contact_no']) ? $row['contact_no'] : '');
                        array_push($chgvalarr, $scr_contact);
                        array_push($datafield, 'contact_no');
                    }

                    if ($row['remark'] != $scr_remark) {
                        array_push($oldvalarr, $row['remark']);
                        array_push($chgvalarr, $scr_remark);
                        array_push($datafield, 'remark');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET buyer_username = '" . scrEsc($finance_connect, $scr_username) . "', pic = '" . scrEsc($finance_connect, $scr_pic) . "', country = '" . scrEsc($finance_connect, $scr_country) . "', brand = '" . scrEsc($finance_connect, $scr_brand) . "', series = '" . scrEsc($finance_connect, $scr_series) . "', contact_no = '" . scrEsc($finance_connect, $scr_contact) . "', remark = '" . scrEsc($finance_connect, $scr_remark) . "', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '" . (int) $dataID . "'";
                        $returnData = mysqli_query($finance_connect, $query);
                        if (!$returnData) {
                            $errorMsg = mysqli_error($finance_connect);
                            $err1 = "Failed to edit record: " . $errorMsg;
                            $act = "F";
                        }

                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $err1 = "Failed to edit record: " . $errorMsg;
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
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (!empty($returnData) ? '' : (isset($errorMsg) ? $errorMsg : '')));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (!empty($returnData) ? '' : (isset($errorMsg) ? $errorMsg : '')));
                }
                audit_log($log);
            }

            break;
        case 'back':
            if ($action == 'addRecord' || $action == 'updRecord') {
                echo $clearLocalStorage . ' ' . $redirectLink;
            } else {
                echo $redirectLink;
            }
            break;
    }
    }
}


if (post('act') == 'D') {
    try {
        // take name
        $rst = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $finance_connect);
        $row = $rst->fetch_assoc();

        $dataID = $row['id'];

    } catch (Exception $e) {
        echo 'Message: ' . $e->getMessage();
    }
}

//view
if (($dataID) && !($act) && (USER_ID != '') && ($_SESSION['viewChk'] != 1) && ($_SESSION['delChk'] != 1)) {
    $acc_name = isset($dataExisted) ? $row['buyer_username'] : '';
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $acc_name . "</b> from <b><i>$tblName Table</i></b>.";
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

    <div class="page-load-cover" style="display:block !important;">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>">
                    <?= $pageTitle ?>
                </a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php
                echo displayPageAction($act, $pageTitle);
                ?>
            </p>

        </div>

        <div id="SCRformContainer" class="container d-flex justify-content-center">
            <div class="col-11 col-md-10 formWidthAdjust">
                <form id="SCRForm" method="post" action="" enctype="multipart/form-data">
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
                                <label class="form-label form_lbl" id="scr_username_lbl" for="scr_username">Shopee Buyer
                                    Username<span class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="scr_username" id="scr_username" value="<?php
                                if (isset($dataExisted) && isset($row['buyer_username']) && !isset($scr_username)) {
                                    echo $row['buyer_username'];
                                } else if (isset($dataExisted) && isset($row['buyer_username']) && isset($scr_username)) {
                                    echo $scr_username;
                                } else {
                                    echo '';
                                } ?>" <?php if ($act == '')
                                     echo 'disabled' ?>>

                                <?php if (isset($username_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $username_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="form-group autocomplete col-md-4 mb-3">
                                <label class="form-label form_lbl" id="scr_pic_lbl" for="scr_pic">Sales Person In
                                    Charge<span class="requireRed">*</span></label>
                                    <?php
                                if(($act == 'I')){
                                $loggedInUserId = USER_ID; // Assuming USER_ID contains the ID of the logged-in user
                                $defaultUser = '';

                                // Retrieve details of the logged-in user
                                $user_rst = getData('name', "id = '$loggedInUserId'", '', USR_USER, $connect);
                                if ($user_rst && $user_rst->num_rows > 0) {
                                    $user_row = $user_rst->fetch_assoc();
                                    $defaultUser = $user_row['name'];
                                }
                                ?>
                                <input class="form-control" type="text" name="scr_pic" id="scr_pic" <?php if ($act == '') echo 'disabled' ?> value="<?php echo $defaultUser ?>">
                                <input type="hidden" name="scr_pic_hidden" id="scr_pic_hidden" value="<?php echo $loggedInUserId ?>">
                                <?php } ?>
                                <?php
                                 if(($act == 'E' || $act == '')){
                                $picData = ['id' => '', 'display' => ''];
                                if (isset($row['pic']) && $row['pic'] !== '') {
                                    $picData = resolveLookupValue(USR_USER, $row['pic'], 'name', $connect);
                                }
                                ?>

                                <input class="form-control" type="text" name="scr_pic" id="scr_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo $picData['display']; ?>">

                                <input type="hidden" name="scr_pic_hidden" id="scr_pic_hidden"
                                    value="<?php echo $picData['id']; ?>">
                                <?php } ?>
                                <?php if (isset($pic_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pic_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-group autocomplete col-md-4 mb-3">
                                <label class="form-label form_lbl" id="scr_country_lbl" for="scr_country">Country<span
                                        class="requireRed">*</span></label>
                                <?php
                                $countryData = ['id' => '', 'display' => ''];
                                if (isset($row['country']) && $row['country'] !== '') {
                                    $countryData = resolveLookupValue(COUNTRIES, $row['country'], 'nicename', $connect, 'name');
                                }
                                ?>

                                <input class="form-control" type="text" name="scr_country" id="scr_country" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo $countryData['display']; ?>">

                                <input type="hidden" name="scr_country_hidden" id="scr_country_hidden"
                                    value="<?php echo $countryData['id']; ?>">

                                <?php if (isset($country_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $country_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                        <div class="row">
                            <div class="form-group autocomplete col-md-4 mb-3">
                                <label class="form-label form_lbl" id="scr_brand_lbl" for="scr_brand">Brand<span
                                        class="requireRed">*</span></label>
                                <?php
                                $brandData = ['id' => '', 'display' => ''];
                                if (isset($row['brand']) && $row['brand'] !== '') {
                                    $brandData = resolveLookupValue(BRAND, $row['brand'], 'name', $connect);
                                }
                                ?>

                                <input class="form-control" type="text" name="scr_brand" id="scr_brand" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo $brandData['display']; ?>">

                                <input type="hidden" name="scr_brand_hidden" id="scr_brand_hidden"
                                    value="<?php echo $brandData['id']; ?>">

                                <?php if (isset($brand_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $brand_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-group autocomplete col-md-4 mb-3">
                                <label class="form-label form_lbl" id="scr_series_lbl" for="scr_series">Series<span
                                        class="requireRed">*</span></label>
                                <?php
                                $seriesData = ['id' => '', 'display' => ''];
                                if (isset($row['series']) && $row['series'] !== '') {
                                    $seriesData = resolveLookupValue(BRD_SERIES, $row['series'], 'name', $connect);
                                }
                                ?>

                                <input class="form-control" type="text" name="scr_series" id="scr_series" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo $seriesData['display']; ?>">

                                <input type="hidden" name="scr_series_hidden" id="scr_series_hidden"
                                    value="<?php echo $seriesData['id']; ?>">

                                <?php if (isset($series_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $series_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-group col-md-4 mb-3">
                                <label class="form-label form_lbl" id="scr_contact_lbl" for="scr_contact">Whatsapp / Contact Number<span class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="scr_contact" id="scr_contact" value="<?php
                                if (isset($dataExisted) && isset($row['contact_no']) && !isset($scr_contact)) {
                                    echo htmlspecialchars((string) $row['contact_no'], ENT_QUOTES, 'UTF-8');
                                } else if (isset($scr_contact)) {
                                    echo htmlspecialchars((string) $scr_contact, ENT_QUOTES, 'UTF-8');
                                } else {
                                    echo '';
                                } ?>" <?php if ($act == '')
                                     echo 'disabled' ?>>
                                <?php if (isset($contact_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $contact_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" id="scr_remark_lbl" for="scr_remark">Remark</label>
                                <textarea class="form-control" name="scr_remark" id="scr_remark" rows="3" <?php if ($act == '')
                                    echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                                    echo $row['remark'] ?></textarea>
                                </div>

                            </div>
                        </div>

                    <?php
                    if ($dataID) {
                        $orderRows = array();
                        $sumFinalAmount = 0.00;
                        $buyerId = (int) $dataID;
                        $buyerUsername = isset($row['buyer_username']) ? mysqli_real_escape_string($finance_connect, (string) $row['buyer_username']) : '';

                        $orderWhere = "status='A' AND (buyer='" . $buyerId . "'";
                        if ($buyerUsername !== '') {
                            $orderWhere .= " OR buyer='" . $buyerUsername . "'";
                        }
                        $orderWhere .= ")";

                        $orderSql = "SELECT * FROM " . SHOPEE_SG_ORDER_REQ . " WHERE " . $orderWhere . " ORDER BY date DESC, time DESC, id DESC";
                        $orderRst = mysqli_query($finance_connect, $orderSql);
                        if ($orderRst && $orderRst->num_rows > 0) {
                            while ($orderRow = $orderRst->fetch_assoc()) {
                                $orderRows[] = $orderRow;
                                $sumFinalAmount += (float) (isset($orderRow['final_amt']) ? $orderRow['final_amt'] : 0);
                            }
                        } else if (!$orderRst) {
                            error_log("Shopee order list query failed: " . mysqli_error($finance_connect) . " SQL: " . $orderSql);
                        }
                    ?>
                    <div class="form-group mt-3">
                        <h5 class="mb-3">Order Records</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0" id="scr_order_tbl">
                                <thead>
                                    <tr>
                                        <th width="60">S/N</th>
                                        <th width="200">Action</th>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Package</th>
                                        <th>Buyer Payment Method</th>
                                        <th>Charges & Fees</th>
                                        <th>Final Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orderRows)) {
                                        $orderSN = 1;
                                        foreach ($orderRows as $orderRow) {
                                            $orderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;
                                            $orderNo = isset($orderRow['orderID']) ? $orderRow['orderID'] : '';
                                            $orderDate = isset($orderRow['date']) ? $orderRow['date'] : '';
                                            $orderPackage = resolvePackageNamesFromCsv(isset($orderRow['package']) ? $orderRow['package'] : '', $connect);
                                            $buyerPayMethod = isset($orderRow['buyer_pay_meth']) ? $orderRow['buyer_pay_meth'] : '';
                                            $orderFees = isset($orderRow['fees']) ? $orderRow['fees'] : '0.00';
                                            $finalAmount = isset($orderRow['final_amt']) ? $orderRow['final_amt'] : '0.00';
                                            ?>
                                            <tr>
                                                <td><?= $orderSN++ ?></td>
                                                <td>
                                                    <a class="btn btn-sm btn-rounded btn-primary"
                                                       href="<?= $SITEURL . '/shopee/shopee_cust_info.php?id=' . (int) $dataID . '&act=' . $act_2 . '&open_order_id=' . $orderId ?>">
                                                        Show Order Detail
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars((string) $orderNo, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $orderPackage, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $buyerPayMethod, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= formatAmountRm($orderFees) ?></td>
                                                <td><?= formatAmountRm($finalAmount) ?></td>
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
                                        <th><?= formatAmountRm($sumFinalAmount) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <?php } ?>
                </form>

                <?php
                if ($dataID) {
                    $customerLogReturnUrl = $SITEURL . '/shopee/shopee_cust_info.php?id=' . (int) $dataID;
                    if ($act !== '') {
                        $customerLogReturnUrl .= '&act=' . urlencode((string) $act);
                    }

                    $customerLogContext = urlResolveUserRecordLogContext($connect, $finance_connect, array(
                        'customer_id' => (int) $dataID,
                        'customer_label' => isset($row['buyer_username']) ? $row['buyer_username'] : '',
                        'return_url' => $customerLogReturnUrl,
                        'ajax_url' => $SITEURL . '/user_record_log.php',
                        'customer_only' => true,
                    ));

                    urlRenderUserRecordLogModule($connect, $finance_connect, array(
                        'table_name' => USER_RECORD_LOG,
                        'context' => $customerLogContext,
                        'section_heading' => 'User Record Log',
                        'show_scope_note' => true,
                    ));
                }
                ?>

                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                    <?php
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" form="SCRForm" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" form="SCRForm" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    ?>
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="submit" form="SCRForm" name="actionBtn"
                        id="actionBtn" value="back">Back</button>
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
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
    }
    ?>

    <script>
        // Always release all loaders, with multiple fallbacks.
        (function () {
            function releaseLoader() {
                var preloaders = document.querySelectorAll('.pre-load-center');
                var covers = document.querySelectorAll('.page-load-cover');

                for (var i = 0; i < preloaders.length; i++) {
                    preloaders[i].style.display = 'none';
                }
                for (var j = 0; j < covers.length; j++) {
                    covers[j].style.display = 'block';
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(releaseLoader, 10);
                });
            } else {
                setTimeout(releaseLoader, 10);
            }

            window.addEventListener('load', releaseLoader);
            setTimeout(releaseLoader, 1500);
        })();
    </script>

    <script>
        <?php include "../js/shopee_cust_info.js" ?>

        //Initial Page And Action Value
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ''; ?>";

        function setShopeeCustomerFormAutofocus(currentAction) {
            if (currentAction !== 'I' && currentAction !== 'E') {
                return;
            }

            var $firstInput = jQuery('#SCRForm')
                .find("input[type='text']:visible:enabled:not(:checkbox,:radio,:hidden,[readonly]), textarea:visible:enabled:not(:hidden,[readonly]), input[type='number']:visible:enabled:not(:hidden,[readonly])")
                .filter(function () {
                    return jQuery.trim(jQuery(this).val()) === '';
                })
                .first();

            if (!$firstInput.length) {
                $firstInput = jQuery('#SCRForm').find("input[type='text']:visible:enabled:not(:checkbox,:radio,:hidden,[readonly]), textarea:visible:enabled:not(:hidden,[readonly]), input[type='number']:visible:enabled:not(:hidden,[readonly])").first();
            }

            if ($firstInput.length) {
                $firstInput.trigger('focus');
            }

            window.scrollTo(0, 0);
        }

        if (typeof checkCurrentPage === 'function') checkCurrentPage(page, action);
        if (typeof centerAlignment === 'function') centerAlignment("SCRformContainer");
        setShopeeCustomerFormAutofocus(action);
        if (typeof setButtonColor === 'function') setButtonColor();
    </script>
</body>

</html>