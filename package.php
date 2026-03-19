<?php
$pageTitle = "Package";
$currentPagePin = 21;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include ROOT.'/include/access.php';

$tblName = PKG;

//Current Page Action And Data ID
$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirect_page = $SITEURL . '/package_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);

$pageActionTitle = $pageAction . " " . $pageTitle;

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataID) && !($act) )
    echo $redirectLink;

//Get The Data From Database
$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);


//Checking Data Error When Retrieved From Database
if ($act != 'I' && (!$rst || !($row = $rst->fetch_assoc()))) {
    $errorExist = 1;
    $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}
//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

//View Data
if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {

    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate'   => $cdate,
        'ctime'   => $ctime,
        'uid'     => USER_ID,
        'cby'     => USER_ID,
        'act_msg' => $viewActMsg,
        'page'    => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($_SESSION);
}

//Edit And Add Data
if (post('actionBtn')) {

    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':

            $currentDataName = postSpaceFilter('currentDataName');
            $item_code = postSpaceFilter('item_code'); // NEW
            $item_description = postSpaceFilter('item_description'); // NEW
            $pkg_price = postSpaceFilter('price');
            $cur_unit = postSpaceFilter('cur_unit_hidden');
            
            $brand = postSpaceFilter('brand_hidden');
            $brand_text = postSpaceFilter('brand'); 
            $brand_exists = false;

            // 1. Check if the hidden ID exists in the BRAND table
            if (!empty($brand) && ctype_digit($brand)) {
                $safe_brand_id = (int)$brand;
                $check_brand = getData('id', "id = $safe_brand_id", '', BRAND, $connect);
                if ($check_brand && $check_brand->num_rows > 0) {
                    $brand_exists = true;
                }
            }

            // 2. If ID is empty but they typed text, verify if the exact text exists in the database
            if (!$brand_exists && !empty($brand_text)) {
                $safe_brand_text = mysqli_real_escape_string($connect, $brand_text);
                $check_brand_text = getData('id', "name = '$safe_brand_text'", '', BRAND, $connect);
                if ($check_brand_text && $check_brand_text->num_rows > 0) {
                    $brand_exists = true;
                    // It exists! Automatically grab the correct ID for the database
                    $brand_row_data = $check_brand_text->fetch_assoc();
                    $brand = $brand_row_data['id']; 
                }
            }

            // 3. Block submission if brand is empty or not found
            $error = 0;
            if (empty($brand_text)) {
                $brand_err = "Brand is required!";
                $error = 1;
            } else if (!$brand_exists) {
                $brand_err = "Brand does not exist! Please select a valid brand from the list.";
                $error = 1;
            }
            
            if ($error == 1) {
                break; // Stops the save but allows specific errors to display below fields
            }
            
            $cost = postSpaceFilter('package_cost');
            $cost_curr = postSpaceFilter('cost_curr_hidden');
            $agent_cost = postSpaceFilter('agent_cost');
            $agent_cost_err = postSpaceFilter('agent_cost_err');

            // middle
            $prod_list = post('prod_val');
            $prod_list = implode(',', array_filter($prod_list));


            $barcode_slot_total = postSpaceFilter('barcode_slot_total_hidden');
            $dataRemark = postSpaceFilter('currentDataRemark');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataID)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                break;
            }

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName) {
                        array_push($newvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }
                    if ($item_code) {
                        array_push($newvalarr, $item_code);
                        array_push($datafield, 'item_code');
                    }
                    if ($item_description) {
                        array_push($newvalarr, $item_description);
                        array_push($datafield, 'item_description');
                    }

                    if ($pkg_price) {
                        array_push($newvalarr, $pkg_price);
                        array_push($datafield, 'price');
                    }

                    if ($cur_unit) {
                        array_push($newvalarr, $cur_unit);
                        array_push($datafield, 'currency_unit');
                    }
                    if ($brand) {
                        array_push($newvalarr, $brand);
                        array_push($datafield, 'brand');
                    }
                    if ($cost) {
                        array_push($newvalarr, $cost);
                        array_push($datafield, 'cost');
                    }
                    if ($cost_curr) {
                        array_push($newvalarr, $cost_curr);
                        array_push($datafield, 'cost_curr');
                    }
                    if ($agent_cost) {
                        array_push($newvalarr, $agent_cost);
                        array_push($datafield, 'agent cost');
                    }
                    if ($prod_list) {
                        array_push($newvalarr, $prod_list);
                        array_push($datafield, 'product');
                    }

                    if ($barcode_slot_total) {
                        array_push($newvalarr, $barcode_slot_total);
                        array_push($datafield, 'barcode_slot_total');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,item_code,item_description,brand,cost,cost_curr,agent_cost,price,currency_unit,product,barcode_slot_total,remark,create_by,create_date,create_time) VALUES ('$currentDataName','$item_code','$item_description','$brand','$cost', '$cost_curr','$agent_cost','$pkg_price','$cur_unit','$prod_list','$barcode_slot_total','$dataRemark','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    if ($row['name'] != $currentDataName) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }
                    if ($row['item_code'] != $item_code) {
                        array_push($oldvalarr, $row['item_code']);
                        array_push($chgvalarr, $item_code);
                        array_push($datafield, 'item_code');
                    }
                    if ($row['item_description'] != $item_description) {
                        array_push($oldvalarr, $row['item_description']);
                        array_push($chgvalarr, $item_description);
                        array_push($datafield, 'item_description');
                    }

                    if ($row['brand'] != $brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['cost'] != $cost) {
                        array_push($oldvalarr, $row['cost']);
                        array_push($chgvalarr, $cost);
                        array_push($datafield, 'cost');
                    }

                    if ($row['cost_curr'] != $cost_curr) {
                        array_push($oldvalarr, $row['cost_curr']);
                        array_push($chgvalarr, $cost_curr);
                        array_push($datafield, 'cost_curr');
                    }
                    
                    if ($row['agent_cost'] != $agent_cost) {
                        array_push($oldvalarr, $row['agent_cost']);
                        array_push($chgvalarr, $agent_cost);
                        array_push($datafield, 'agent cost');
                    }

                    if ($row['agent_cost_err'] != $agent_cost_err) {
                        array_push($oldvalarr, $row['agent_cost_err']);
                        array_push($chgvalarr, $agent_cost_err);
                        array_push($datafield, 'cost_curr');
                    }

                    if ($row['price'] != $pkg_price) {
                        array_push($oldvalarr, $row['price']);
                        array_push($chgvalarr, $pkg_price);
                        array_push($datafield, 'price');
                    }

                    if ($row['currency_unit'] != $cur_unit) {
                        array_push($oldvalarr, $row['currency_unit']);
                        array_push($chgvalarr, $cur_unit);
                        array_push($datafield, 'currency_unit');
                    }

                    if ($row['product'] != $prod_list) {
                        array_push($oldvalarr, $row['product']);
                        array_push($chgvalarr, $prod_list);
                        array_push($datafield, 'product');
                    }

                    if ($row['barcode_slot_total'] != $barcode_slot_total) {
                        array_push($oldvalarr, $row['barcode_slot_total']);
                        array_push($chgvalarr, $barcode_slot_total);
                        array_push($datafield, 'barcode_slot_total');
                    }

                    if ($row['remark'] != $dataRemark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $dataRemark == '' ? 'Empty Value' : $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='$currentDataName', item_code='$item_code', item_description='$item_description', brand='$brand',cost='$cost',cost_curr='$cost_curr',agent_cost='$agent_cost',price ='$pkg_price', currency_unit ='$cur_unit', product ='$prod_list', barcode_slot_total ='$barcode_slot_total', remark ='$dataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
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
                    'log_act'      => $pageAction,
                    'cdate'        => $cdate,
                    'ctime'        => $ctime,
                    'uid'          => USER_ID,
                    'cby'          => USER_ID,
                    'query_rec'    => $query,
                    'query_table'  => $tblName,
                    'page'         => $pageTitle,
                    'connect'      => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
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

//Function(title, subtitle, page name, ajax url path, redirect path, action)
//To show action dialog after finish certain action (eg. edit)

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <link rel="stylesheet" href="./css/package.css">
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">

        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php echo $pageActionTitle ?>
            </p>
        </div>
        <div id="formContainer" class="container-fluid mt-2">
            <div class="col-12 col-md-12 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2>
                            <?php echo $pageActionTitle ?>
                        </h2>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" for="currentDataName"><?php echo $pageTitle ?>
                                    Name<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="currentDataName" id="currentDataName"
                                    value="<?php echo isset($_POST['currentDataName']) ? htmlspecialchars($_POST['currentDataName']) : (isset($row['name']) ? htmlspecialchars($row['name']) : ''); ?>"
                                    <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err)) echo $err; ?></span>
                                </div>
                            </div>

                        </div>

                        <div class="col-12 col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label form_lbl" id="item_code_lbl" for="item_code">Item Code (SKU)<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" name="item_code" id="item_code" 
                                            value="<?php echo isset($_POST['item_code']) ? htmlspecialchars($_POST['item_code']) : ((isset($row['item_code'])) ? htmlspecialchars($row['item_code']) : ''); ?>" 
                                            <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label form_lbl" id="item_description_lbl" for="item_description">Item Description<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" name="item_description" id="item_description" 
                                            value="<?php echo isset($_POST['item_description']) ? htmlspecialchars($_POST['item_description']) : ((isset($row['item_description'])) ? htmlspecialchars($row['item_description']) : ''); ?>" 
                                            <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                    </div>
                                </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" id="price_lbl" for="price">Selling Price<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="number" name="price" id="price"
                                    value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ((isset($row['price'])) ? htmlspecialchars($row['price']) : ''); ?>"
                                    <?php if ($act == '') echo 'readonly' ?> required>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($err2)) echo $err2; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group autocomplete mb-3">
                                <label class="form-label form_lbl" id="cur_unit_lbl" for="cur_unit">Currency
                                    Unit<span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);
                                $curUnitName = '';
                        
                                if (isset($row['currency_unit']))
                                    $echoVal = $row['currency_unit'];
                        
                                if (isset($echoVal) && $echoVal > 0) {
                                    $product_info_result = getData('unit', "id = '$echoVal'", '', CUR_UNIT, $connect);
                                    if ($product_info_result && $product_info_result->num_rows > 0) {
                                        $product_info_row = $product_info_result->fetch_assoc();
                                        $curUnitName = $product_info_row['unit'];
                                    }
                                }
                                if (isset($_POST['cur_unit'])) {
                                    $curUnitName = $_POST['cur_unit'];
                                }
                                ?>
                                <input class="form-control" type="text" name="cur_unit" id="cur_unit"
                                    value="<?php echo htmlspecialchars($curUnitName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="cur_unit_hidden" id="cur_unit_hidden"
                                    value="<?php echo isset($_POST['cur_unit_hidden']) ? htmlspecialchars($_POST['cur_unit_hidden']) : ((isset($row['currency_unit'])) ? htmlspecialchars($row['currency_unit']) : ''); ?>">
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($err3)) echo $err3; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                       <div class="col-12 col-md-4">
                            <div class="form-group autocomplete mb-3">
                                <label class="form-label form_lbl" id="brand_lbl" for="brand">Brand
                                    <span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);
                                $brandName = '';
                        
                                if (isset($row['brand'])) {
                                    $echoVal = $row['brand'];
                                }
                        
                                if (isset($echoVal) && $echoVal > 0) {
                                    $brand_result = getData('name', "id = '$echoVal'", '', BRAND, $connect);
                                    if ($brand_result && $brand_result->num_rows > 0) {
                                        $brand_row = $brand_result->fetch_assoc();
                                        $brandName = $brand_row['name'];
                                    }
                                }
                                
                                // --- FIX: Retain user input if validation fails ---
                                if (isset($_POST['brand'])) {
                                    $brandName = $_POST['brand'];
                                }
                                ?>
                                <input class="form-control" type="text" name="brand" id="brand"
                                    value="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="brand_hidden" id="brand_hidden"
                                    value="<?php echo isset($_POST['brand_hidden']) ? htmlspecialchars($_POST['brand_hidden'], ENT_QUOTES, 'UTF-8') : (isset($row['brand']) ? htmlspecialchars($row['brand'], ENT_QUOTES, 'UTF-8') : ''); ?>">
                                
                                <?php if (isset($brand_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $brand_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" id="cost_lbl" for="package_cost">Cost<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="number" required step="0.01" name="package_cost" id="package_cost"
                                    value="<?php echo isset($_POST['package_cost']) ? htmlspecialchars($_POST['package_cost']) : ((isset($row['cost'])) ? htmlspecialchars($row['cost']) : ''); ?>"
                                    <?php if ($act == '') echo 'readonly' ?>>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($cost_err)) echo $cost_err; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group autocomplete mb-3">
                                <label class="form-label form_lbl" id="cost_curr_lbl" for="cost_curr">Cost Currency Unit
                                    <span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);
                                $costUnitName = '';
                        
                                if (isset($row['cost_curr'])) {
                                    $echoVal = $row['cost_curr'];
                                }
                        
                                if (isset($echoVal) && $echoVal > 0) {
                                    $cost_curr_result = getData('unit', "id = '$echoVal'", '', CUR_UNIT, $connect);
                                    if ($cost_curr_result && $cost_curr_result->num_rows > 0) {
                                        $cost_curr_row = $cost_curr_result->fetch_assoc();
                                        $costUnitName = $cost_curr_row['unit'];
                                    }
                                }
                                if (isset($_POST['cost_curr'])) {
                                    $costUnitName = $_POST['cost_curr'];
                                }
                                ?>
                                <input class="form-control" type="text" name="cost_curr" id="cost_curr"
                                    value="<?php echo htmlspecialchars($costUnitName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="cost_curr_hidden" id="cost_curr_hidden"
                                    value="<?php echo isset($_POST['cost_curr_hidden']) ? htmlspecialchars($_POST['cost_curr_hidden']) : (isset($row['cost_curr']) ? htmlspecialchars($row['cost_curr']) : ''); ?>">
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($cost_curr_err)) echo $cost_curr_err; ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label form_lbl" for="agent_cost">Agent Cost (RM)<span class="requireRed">*</span></label>
                            <input class="form-control" type="number" name="agent_cost" id="agent_cost" step="0.01"
                                value="<?php echo isset($_POST['agent_cost']) ? htmlspecialchars($_POST['agent_cost']) : ((isset($row['agent_cost'])) ? htmlspecialchars($row['agent_cost']) : ''); ?>"
                                <?php if ($act == '') echo 'readonly' ?> required>
                            <div id="err_msg">
                                <span class="mt-n1"><?php if (isset($agent_cost_err)) echo $agent_cost_err; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="table-responsive mb-3">
                            <table class="table table-striped" id="productList">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Product</th>
                                        <th scope="col">Weight</th>
                                        <th scope="col">Weight Unit</th>
                                        <th scope="col">Barcode Status</th>
                                        <th scope="col">Barcode Slot</th>
                                        <th scope="col" id="action_col"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                // check act
                                $readonly = ($act != '') ? '' : ' readonly';
                                
                                // get value
                                unset($echoVal);
                                
                                if (isset($row['product'])) {
                                    $echoVal = $row['product'];
                                }

                                if (isset($_POST['prod_name']) && is_array($_POST['prod_name'])) {
                                    $postedProdNames = $_POST['prod_name'];
                                    $postedProdVals = isset($_POST['prod_val']) && is_array($_POST['prod_val']) ? $_POST['prod_val'] : array();
                                    $rowCount = max(count($postedProdNames), count($postedProdVals));
                                    if ($rowCount < 1) {
                                        $rowCount = 1;
                                    }

                                    // Preload product and weight unit data to avoid N+1 queries
                                    $productsById = array();
                                    $weightUnitsById = array();
                                    
                                    if (!empty($postedProdVals) && is_array($postedProdVals)) {
                                        $prodIdSet = array();
                                        foreach ($postedProdVals as $val) {
                                            if (trim($val) !== '') {
                                                $intId = (int)$val;
                                                if ($intId > 0) {
                                                    $prodIdSet[$intId] = true;
                                                }
                                            }
                                        }
                                        
                                        if (!empty($prodIdSet)) {
                                            $idList = implode(',', array_keys($prodIdSet));
                                            $product_info_result = getData('*', "id IN ($idList)", '', PROD, $connect);
                                            
                                            $weightUnitIds = array();
                                            
                                            if ($product_info_result && $product_info_result->num_rows > 0) {
                                                while ($product_info_row = $product_info_result->fetch_assoc()) {
                                                    $prodIdKey = (int)$product_info_row['id'];
                                                    $productsById[$prodIdKey] = $product_info_row;
                                                    
                                                    if (!empty($product_info_row['weight_unit'])) {
                                                        $wuId = (int)$product_info_row['weight_unit'];
                                                        if ($wuId > 0) {
                                                            $weightUnitIds[$wuId] = true;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($weightUnitIds)) {
                                                $wuIdList = implode(',', array_keys($weightUnitIds));
                                                $wgt_unit_result = getData('id, unit', "id IN ($wuIdList)", '', WGT_UNIT, $connect);
                                                if ($wgt_unit_result && $wgt_unit_result->num_rows > 0) {
                                                    while ($wgt_unit_row = $wgt_unit_result->fetch_assoc()) {
                                                        $wuKey = (int)$wgt_unit_row['id'];
                                                        $weightUnitsById[$wuKey] = $wgt_unit_row;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    for ($i = 0; $i < $rowCount; $i++) {
                                        $num = $i + 1;
                                        $pn = isset($postedProdNames[$i]) ? $postedProdNames[$i] : '';
                                        
                                        // Cast to integer to prevent SQL injection & ensure safe lookups
                                        $pid = isset($postedProdVals[$i]) && ctype_digit((string)$postedProdVals[$i]) ? (int)$postedProdVals[$i] : 0;

                                        $pw = '';
                                        $pwu = '';
                                        $pwun = '';
                                        $ps = '';
                                        $pslot = '';

                                        if ($pid > 0 && isset($productsById[$pid])) {
                                            $product_info_row = $productsById[$pid];
                                            $pw = isset($product_info_row['weight']) ? $product_info_row['weight'] : '';
                                            $pwu = isset($product_info_row['weight_unit']) ? $product_info_row['weight_unit'] : '';
                                            $ps = isset($product_info_row['barcode_status']) ? $product_info_row['barcode_status'] : '';
                                            $pslot = isset($product_info_row['barcode_slot']) ? $product_info_row['barcode_slot'] : '';
                                            
                                            $pwuInt = (int)$pwu;
                                            if ($pwuInt > 0 && isset($weightUnitsById[$pwuInt])) {
                                                $pwun = $weightUnitsById[$pwuInt]['unit'];
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?= $num ?></td>
                                            <td class="autocomplete">
                                                <input type="text" name="prod_name[]" id="prod_name_<?= $num ?>" value="<?= htmlspecialchars($pn) ?>" onkeyup="prodInfo(this)"<?= $readonly ?>>
                                                <input type="hidden" name="prod_val[]" id="prod_val_<?= $num ?>" value="<?= htmlspecialchars($pid) ?>" oninput="prodInfoAutoFill(this)">
                                                <div id="err_msg"><span class="mt-n1"><?php if (isset($err4)) echo $err4; ?></span></div>
                                            </td>
                                            <td><input class="readonlyInput" type="text" name="wgt[]" id="wgt_<?= $num ?>" value="<?= htmlspecialchars($pw) ?>" readonly></td>
                                            <td>
                                                <input class="readonlyInput" type="text" name="wgt_unit[]" id="wgt_unit_<?= $num ?>" value="<?= htmlspecialchars($pwun) ?>" readonly>
                                                <input type="hidden" name="wgt_unit_val[]" id="wgt_unit_val_<?= $num ?>" value="<?= htmlspecialchars($pwu) ?>" readonly>
                                            </td>
                                            <td><input class="readonlyInput" type="text" name="barcode_status[]" id="barcode_status_<?= $num ?>" value="<?= htmlspecialchars($ps) ?>" readonly></td>
                                            <td><input class="readonlyInput" type="text" name="barcode_slot[]" id="barcode_slot_<?= $num ?>" value="<?= htmlspecialchars($pslot) ?>" readonly></td>
                                            <?php if ($act != ''): ?>
                                                <td>
                                                    <?php if ($num == 1): ?>
                                                        <button class="mt-1" id="action_menu_btn" type="button" onclick="Add()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                    <?php else: ?>
                                                        <button class="mt-1" id="action_menu_btn" type="button" onclick="Remove(this)"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000" value="Remove"></i></button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php
                                    }
                                } else if (!empty($echoVal)) {
                                    $num = 1; // numbering
                                    $echoVal = explode(',', $echoVal);
                                
                                    foreach ($echoVal as $prod_id) {
                                        // product info
                                        $product_info_result = getData('*', "id = '$prod_id'", '', PROD, $connect);
                                        if ($product_info_result && $product_info_result->num_rows > 0) {
                                            $product_info_row = $product_info_result->fetch_assoc();
                                
                                            $pid = $product_info_row['id'];
                                            $pn = $product_info_row['name'];
                                            $pw = $product_info_row['weight'];
                                            $pwu = $product_info_row['weight_unit'];
                                            $ps = $product_info_row['barcode_status'];
                                            $pslot = $product_info_row['barcode_slot'];
                                
                                            // get weight unit info
                                            $wgt_unit_result = getData('unit', "id = '$pwu'", '', WGT_UNIT, $connect);
                                            $pwun = '';
                                            if ($wgt_unit_result && $wgt_unit_result->num_rows > 0) {
                                                $product_info_row = $wgt_unit_result->fetch_assoc();
                                                $pwun = $product_info_row['unit'];
                                            }
                                
                                            ?>
                                            <tr>
                                                <td><?= $num ?></td>
                                                <td class="autocomplete">
                                                    <input type="text" name="prod_name[]" id="prod_name_<?= $num ?>" value="<?= htmlspecialchars($pn) ?>" onkeyup="prodInfo(this)"<?= $readonly ?>>
                                                    <input type="hidden" name="prod_val[]" id="prod_val_<?= $num ?>" value="<?= $pid ?>" oninput="prodInfoAutoFill(this)">
                                                    <div id="err_msg"><span class="mt-n1"><?php if (isset($err4)) echo $err4; ?></span></div>
                                                </td>
                                                <td><input class="readonlyInput" type="text" name="wgt[]" id="wgt_<?= $num ?>" value="<?= $pw ?>" readonly></td>
                                                <td>
                                                    <input class="readonlyInput" type="text" name="wgt_unit[]" id="wgt_unit_<?= $num ?>" value="<?= htmlspecialchars($pwun) ?>" readonly>
                                                    <input type="hidden" name="wgt_unit_val[]" id="wgt_unit_val_<?= $num ?>" value="<?= $pwu ?>" readonly>
                                                </td>
                                                <td><input class="readonlyInput" type="text" name="barcode_status[]" id="barcode_status_<?= $num ?>" value="<?= $ps ?>" readonly></td>
                                                <td><input class="readonlyInput" type="text" name="barcode_slot[]" id="barcode_slot_<?= $num ?>" value="<?= $pslot ?>" readonly></td>
                                                <?php if ($act != ''): ?>
                                                    <td>
                                                        <?php if ($num == 1): ?>
                                                            <button class="mt-1" id="action_menu_btn" type="button" onclick="Add()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                        <?php else: ?>
                                                            <button class="mt-1" id="action_menu_btn" type="button" onclick="Remove(this)"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000" value="Remove"></i></button>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php
                                            $num++;
                                        }
                                    }
                                } else {
                                    ?>
                                    <tr>
                                        <td>1</td>
                                        <td class="autocomplete">
                                            <input type="text" name="prod_name[]" id="prod_name_1" value="" onkeyup="prodInfo(this)"<?= $readonly ?>>
                                            <input type="hidden" name="prod_val[]" id="prod_val_1" value="" oninput="prodInfoAutoFill(this)">
                                            <div id="err_msg"><span class="mt-n1"><?php if (isset($err4)) echo $err4; ?></span></div>
                                        </td>
                                        <td><input class="readonlyInput" type="text" name="wgt[]" id="wgt_1" value="" readonly></td>
                                        <td>
                                            <input class="readonlyInput" type="text" name="wgt_unit[]" id="wgt_unit_1" value="" readonly>
                                            <input type="hidden" name="wgt_unit_val[]" id="wgt_unit_val_1" value="" readonly>
                                        </td>
                                        <td><input class="readonlyInput" type="text" name="barcode_status[]" id="barcode_status_1" value="" readonly></td>
                                        <td><input class="readonlyInput" type="text" name="barcode_slot[]" id="barcode_slot_1" value="" readonly></td>
                                        <?php if ($act != ''): ?>
                                            <td><button class="mt-1" id="action_menu_btn" type="button" onclick="Add()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button></td>
                                        <?php else: ?>
                                            <td></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php } ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <td scope="col" colspan="5" style="text-align:right">Total Barcode</td>
                                        <td scope="col" id="barcode_slot_total" style="text-align:center">
                                            <?php
                                            if (isset($barcode_slot_total) && $barcode_slot_total != '')
                                                echo $barcode_slot_total;
                                            else {
                                                if (isset($_POST['barcode_slot_total_hidden']) && $_POST['barcode_slot_total_hidden'] !== '')
                                                    echo htmlspecialchars($_POST['barcode_slot_total_hidden']);
                                                else if (isset($dataExisted) && isset($row['barcode_slot_total']))
                                                    echo $row['barcode_slot_total'];
                                                else echo '0';
                                            }
                                            ?><input name="barcode_slot_total_hidden" id="barcode_slot_total_hidden"
                                                type="hidden"
                                                value="<?php echo isset($_POST['barcode_slot_total_hidden']) ? htmlspecialchars($_POST['barcode_slot_total_hidden']) : ((isset($row['barcode_slot_total'])) ? htmlspecialchars($row['barcode_slot_total']) : ''); ?>">
                                        </td>
                                        <td scope="col"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>


                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataRemark"><?php echo $pageTitle ?> Remark</label>
                        <textarea class="form-control" name="currentDataRemark" id="currentDataRemark" rows="3"
                            <?php if ($act == '') echo 'readonly' ?>><?php echo isset($_POST['currentDataRemark']) ? htmlspecialchars($_POST['currentDataRemark']) : (isset($row['remark']) ? htmlspecialchars($row['remark']) : ''); ?></textarea>
                    </div>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn"
                            value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    //Initial Page And Action Value
    var page = "<?= $pageTitle ?>";
    var action = "<?php echo isset($act) ? $act : ''; ?>";

    checkCurrentPage(page, action);
    setButtonColor();
            preloader(300, action);

    </script>

</body>

<script>
<?php include './js/package.js'; ?>
</script>

</html>