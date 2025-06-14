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
if (!$rst || !($row = $rst->fetch_assoc()) && $act != 'I') {
    $errorExist = 1;
    // $_SESSION['tempValConfirmBox'] = true;
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
            $pkg_price = postSpaceFilter('price');
            $cur_unit = postSpaceFilter('cur_unit_hidden');
            $brand = postSpaceFilter('brand_hidden');
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

                    $query = "INSERT INTO " . $tblName . "(name,brand,cost,cost_curr,agent_cost,price,currency_unit,product,barcode_slot_total,remark,create_by,create_date,create_time) VALUES ('$currentDataName','$brand','$cost', '$cost_curr','$agent_cost','$pkg_price','$cur_unit','$prod_list','$barcode_slot_total','$dataRemark','" . USER_ID . "',curdate(),curtime())";
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
                        $query = "UPDATE " . $tblName . " SET name ='$currentDataName',brand='$brand',cost='$cost',cost_curr='$cost_curr',agent_cost='$agent_cost',price ='$pkg_price', currency_unit ='$cur_unit', product ='$prod_list', barcode_slot_total ='$barcode_slot_total', remark ='$dataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
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
                                    value="<?php if (isset($row['name'])) echo $row['name'] ?>"
                                    <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                <div id="err_msg">
                                    <span class="mt-n1" id="errorSpan"><?php if (isset($err)) echo $err; ?></span>
                                </div>
                            </div>

                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" id="price_lbl" for="price">Selling Price<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="number" name="price" id="price"
                                    value="<?php echo (isset($row['price'])) ? $row['price'] : ''; ?>"
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
                                ?>
                                <input class="form-control" type="text" name="cur_unit" id="cur_unit"
                                    value="<?php echo htmlspecialchars($curUnitName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="cur_unit_hidden" id="cur_unit_hidden"
                                    value="<?php echo (isset($row['currency_unit'])) ? htmlspecialchars($row['currency_unit']) : ''; ?>">
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
                                ?>
                                <input class="form-control" type="text" name="brand" id="brand"
                                    value="<?php echo htmlspecialchars($brandName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="brand_hidden" id="brand_hidden"
                                    value="<?php echo isset($row['brand']) ? htmlspecialchars($row['brand']) : ''; ?>">
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($brand_err)) echo $brand_err; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" id="cost_lbl" for="package_cost">Cost<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="number" required step="0.01" name="package_cost" id="package_cost"
                                    value="<?php echo (isset($row['cost'])) ? $row['cost'] : ''; ?>"
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
                                ?>
                                <input class="form-control" type="text" name="cost_curr" id="cost_curr"
                                    value="<?php echo htmlspecialchars($costUnitName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="cost_curr_hidden" id="cost_curr_hidden"
                                    value="<?php echo isset($row['cost_curr']) ? htmlspecialchars($row['cost_curr']) : ''; ?>">
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
                                value="<?php echo (isset($row['agent_cost'])) ? $row['agent_cost'] : ''; ?>"
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
                                
                                if (!empty($echoVal) ) {
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
                                            <input type="text" name="prod_name[]" id="prod_name_1" value="" onkeyup="prodInfo(this)">
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
                                        <td><button class="mt-1" id="action_menu_btn" type="button" onclick="Add()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button></td>
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
                                                if (isset($dataExisted) && isset($row['barcode_slot_total']))
                                                    echo $row['barcode_slot_total'];
                                                else echo '0';
                                            }
                                            ?><input name="barcode_slot_total_hidden" id="barcode_slot_total_hidden"
                                                type="hidden"
                                                value="<?php echo (isset($row['barcode_slot_total'])) ? $row['barcode_slot_total'] : ''; ?>">
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
                            <?php if ($act == '') echo 'readonly' ?>><?php if (isset($row['remark'])) echo $row['remark'] ?></textarea>
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