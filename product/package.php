<?php
$pageTitle = "Package";
$currentPagePin = 21;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
include ROOT.'/include/access.php';

$tblName = PKG;

if (!function_exists('packageNormalizePlatformItemIdCsv')) {
    function packageNormalizePlatformItemIdCsv($value)
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $value = str_replace(array("\r\n", "\r", "\n"), ',', (string) $value);
        $parts = array_filter(array_map('trim', explode(',', $value)), 'strlen');

        $clean = array();
        foreach ($parts as $part) {
            if (!in_array($part, $clean, true)) {
                $clean[] = $part;
            }
        }

        return implode(',', $clean);
    }
}

if (!function_exists('packageFindActivePackageByReference')) {
    function packageFindActivePackageByReference($connect, $referenceValue)
    {
        $referenceValue = trim((string) $referenceValue);
        if (!($connect instanceof mysqli) || $referenceValue === '') {
            return array();
        }

        $safeReferenceValue = mysqli_real_escape_string($connect, $referenceValue);
        $result = mysqli_query(
            $connect,
            "SELECT id, name, item_code, parent_package_id, status
             FROM `" . PKG . "`
             WHERE status = 'A' AND (name = '" . $safeReferenceValue . "' OR item_code = '" . $safeReferenceValue . "')
             ORDER BY id DESC
             LIMIT 1"
        );

        if (!$result || mysqli_num_rows($result) === 0) {
            return array();
        }

        $row = mysqli_fetch_assoc($result);
        return is_array($row) ? $row : array();
    }
}

if (!function_exists('packageFormatRelationValue')) {
    function packageFormatRelationValue($packageRow)
    {
        if (!is_array($packageRow)) {
            return 'Empty Value';
        }

        $packageName = trim((string) (isset($packageRow['name']) ? $packageRow['name'] : ''));
        $itemCode = trim((string) (isset($packageRow['item_code']) ? $packageRow['item_code'] : ''));
        if ($packageName === '' && $itemCode === '') {
            return 'Empty Value';
        }

        return $itemCode !== '' ? $itemCode : $packageName;
    }
}

if (!function_exists('packageFormatRelationDisplayName')) {
    function packageFormatRelationDisplayName($packageRow)
    {
        if (!is_array($packageRow)) {
            return '';
        }

        $packageName = trim((string) (isset($packageRow['name']) ? $packageRow['name'] : ''));
        $itemCode = trim((string) (isset($packageRow['item_code']) ? $packageRow['item_code'] : ''));

        return $packageName !== '' ? $packageName : $itemCode;
    }
}

if (!function_exists('packageBuildUniqueCopyValue')) {
    /**
     * Builds a value that does not clash with an existing active package, by appending
     * the given suffix to the original ("Set A" -> "Set A (Copy)" -> "Set A (Copy 2)").
     * Used when duplicating a package so the prefilled form can be saved straight away
     * instead of failing the duplicate check on the value it copied.
     */
    function packageBuildUniqueCopyValue($connect, $columnName, $originalValue, $suffix)
    {
        $originalValue = trim((string) $originalValue);
        if (!($connect instanceof mysqli) || $originalValue === '') {
            return $originalValue;
        }

        $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $columnName);
        if ($columnName === '') {
            return $originalValue;
        }

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            $candidate = $originalValue . ' ' . $suffix . ($attempt > 1 ? (' ' . $attempt) : '');
            $candidate = trim(preg_replace('/\s+/', ' ', $candidate));

            $result = mysqli_query(
                $connect,
                "SELECT id FROM `" . PKG . "`
                 WHERE status = 'A' AND `" . $columnName . "` = '" . mysqli_real_escape_string($connect, $candidate) . "'
                 LIMIT 1"
            );

            if (!$result) {
                return $candidate;
            }

            if (mysqli_num_rows($result) === 0) {
                return $candidate;
            }
        }

        return $originalValue . ' ' . $suffix . ' ' . time();
    }
}

if (!function_exists('packageResolveActiveProductId')) {
    function packageResolveActiveProductId($connect, $productId, $productName = '')
    {
        if (!($connect instanceof mysqli)) {
            return 0;
        }

        $productId = trim((string) $productId);
        if ($productId !== '' && ctype_digit($productId) && (int) $productId > 0) {
            $safeProductId = (int) $productId;
            $productResult = mysqli_query(
                $connect,
                "SELECT id FROM `" . PROD . "` WHERE id = " . $safeProductId . " AND status = 'A' LIMIT 1"
            );

            if ($productResult && mysqli_num_rows($productResult) > 0) {
                return $safeProductId;
            }
        }

        $productName = trim((string) $productName);
        if ($productName === '') {
            return 0;
        }

        $safeProductName = mysqli_real_escape_string($connect, $productName);
        $productResult = mysqli_query(
            $connect,
            "SELECT id FROM `" . PROD . "`
             WHERE status = 'A'
               AND name = '" . $safeProductName . "'
             ORDER BY id DESC
             LIMIT 1"
        );

        if ($productResult && mysqli_num_rows($productResult) > 0) {
            $productRow = mysqli_fetch_assoc($productResult);
            return isset($productRow['id']) ? (int) $productRow['id'] : 0;
        }

        return 0;
    }
}

if (!function_exists('packageLoadProductDisplayRowsByIds')) {
    function packageLoadProductDisplayRowsByIds($connect, $productIds)
    {
        $rows = array();

        if (!($connect instanceof mysqli)) {
            return $rows;
        }

        $safeIds = array();
        foreach ((array) $productIds as $productId) {
            $productId = trim((string) $productId);
            if ($productId !== '' && ctype_digit($productId) && (int) $productId > 0) {
                $safeIds[(int) $productId] = (int) $productId;
            }
        }

        if (empty($safeIds)) {
            return $rows;
        }

        $productResult = mysqli_query(
            $connect,
            "SELECT
                p.id,
                p.name,
                p.weight,
                p.weight_unit,
                p.barcode_status,
                p.barcode_slot,
                p.status,
                wu.unit AS weight_unit_name
             FROM `" . PROD . "` p
             LEFT JOIN `" . WGT_UNIT . "` wu
                ON wu.id = p.weight_unit
               AND wu.status = 'A'
             WHERE p.id IN (" . implode(',', $safeIds) . ")"
        );

        if ($productResult) {
            while ($productRow = mysqli_fetch_assoc($productResult)) {
                $productId = isset($productRow['id']) ? (int) $productRow['id'] : 0;
                if ($productId > 0) {
                    $rows[$productId] = $productRow;
                }
            }
        }

        return $rows;
    }
}

//Current Page Action And Data ID
$dataId = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirectPage = $SITEURL . '/product/package_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);

$pageActionTitle = $pageAction . " " . $pageTitle;

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataId) && !($act) )
    echo $redirectLink;

//Get The Data From Database
$result = getData('*', "id = '$dataId'", '', $tblName, $connect);


//Checking Data Error When Retrieved From Database
if ($act != 'I' && (!$result || !($row = $result->fetch_assoc()))) {
    $errorExist = 1;
    $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}

// Copy Data: `act=I&copy_from=<id>` prefills the Add form from an existing package so the
// user can adjust the details and save it as a new package. $dataId stays empty, so the
// form still submits as an insert and the package being copied is never touched. Name and
// Item Code get a "(Copy)" suffix because both identify a package elsewhere in the system.
$copySourceRow = array();
$copySourceLabel = '';
$copyFromPackageId = 0;
if ($act === 'I' && empty($dataId)) {
    $requestedCopyFromId = trim((string) (!empty(input('copy_from')) ? input('copy_from') : post('copy_from')));
    if ($requestedCopyFromId !== '' && ctype_digit($requestedCopyFromId) && (int) $requestedCopyFromId > 0) {
        $copyFromPackageId = (int) $requestedCopyFromId;
        $copyResult = getData('*', "id = '" . $copyFromPackageId . "' AND status = 'A'", '', $tblName, $connect);
        $copyRow = ($copyResult && $copyResult->num_rows > 0) ? $copyResult->fetch_assoc() : null;

        if (!is_array($copyRow)) {
            $copyFromPackageId = 0;
            $copySourceError = 'The package you tried to copy was not found or is no longer active.';
        } else {
            $copySourceLabel = packageFormatRelationDisplayName($copyRow);
            $copySourceRow = $copyRow;
            // The new package is its own record: drop the source identity and audit trail.
            unset($copySourceRow['id'], $copySourceRow['create_by'], $copySourceRow['create_date'], $copySourceRow['create_time'], $copySourceRow['update_by'], $copySourceRow['update_date'], $copySourceRow['update_time']);
            $copySourceRow['name'] = packageBuildUniqueCopyValue($connect, 'name', isset($copyRow['name']) ? $copyRow['name'] : '', '(Copy)');
            $copySourceRow['item_code'] = packageBuildUniqueCopyValue($connect, 'item_code', isset($copyRow['item_code']) ? $copyRow['item_code'] : '', '(Copy)');

            // Only prefill on the first load; once the form is posted the submitted values win.
            if (!post('actionBtn')) {
                $row = $copySourceRow;
            }
        }
    }
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataId, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

//View Data
if ($dataId && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {

    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
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
            $platform_item_id = packageNormalizePlatformItemIdCsv(postSpaceFilter('platform_item_id'));
            $item_description = postSpaceFilter('item_description'); // NEW
            $pkg_price = postSpaceFilter('price');
            $cur_unit = postSpaceFilter('cur_unit_hidden');
            $parent_package_id = postSpaceFilter('parent_package_id');
            $parent_package_name = postSpaceFilter('parent_package_name');
            $parentPackageRow = array();
            $parentPackageAuditValue = 'Empty Value';
            
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

            if ($parent_package_id !== '' && ctype_digit((string) $parent_package_id)) {
                $parentPackageRows = commonPackageLoadRowsByIds($connect, array((int) $parent_package_id), true);
                if (isset($parentPackageRows[(int) $parent_package_id]) && strtoupper(trim((string) $parentPackageRows[(int) $parent_package_id]['status'])) === 'A') {
                    $parentPackageRow = $parentPackageRows[(int) $parent_package_id];
                }
            }

            if (empty($parentPackageRow) && $parent_package_name !== '') {
                $parentPackageRow = packageFindActivePackageByReference($connect, $parent_package_name);
            }

            $parent_package_id = !empty($parentPackageRow) && isset($parentPackageRow['id']) ? (int) $parentPackageRow['id'] : 0;
            if (!empty($parentPackageRow)) {
                $parent_package_name = packageFormatRelationValue($parentPackageRow);
                $parentPackageAuditValue = packageFormatRelationValue($parentPackageRow);
            }

            if ($parent_package_name !== '' || $parent_package_id > 0) {
                if (empty($parentPackageRow)) {
                    $parent_package_err = "Parent SKU package was not found or is not active.";
                    $error = 1;
                } else {
                    $parentValidation = commonValidatePackageParentRelation($connect, (int) $dataId, $parent_package_id);
                    if (empty($parentValidation['success'])) {
                        $parent_package_err = isset($parentValidation['message']) ? (string) $parentValidation['message'] : "Invalid Parent SKU package.";
                        $error = 1;
                    } else if (!empty($parentValidation['parent_row'])) {
                        $parentPackageRow = $parentValidation['parent_row'];
                        $parentPackageAuditValue = packageFormatRelationValue($parentPackageRow);
                        $parent_package_name = packageFormatRelationValue($parentPackageRow);
                    }
                }
            }
            
            $prodListInput = post('prod_val');
            $prodNameInput = postSpaceFilter('prod_name');

            if (!is_array($prodListInput)) {
                $prodListInput = array();
            }

            if (!is_array($prodNameInput)) {
                $prodNameInput = array();
            }

            $prodListIds = array();
            $productRowCount = max(count($prodListInput), count($prodNameInput));

            for ($productIndex = 0; $productIndex < $productRowCount; $productIndex++) {
                $postedProductId = isset($prodListInput[$productIndex]) ? trim((string) $prodListInput[$productIndex]) : '';
                $postedProductName = isset($prodNameInput[$productIndex]) ? trim((string) $prodNameInput[$productIndex]) : '';

                $resolvedProductId = packageResolveActiveProductId($connect, $postedProductId, $postedProductName);
                if ($resolvedProductId > 0) {
                    $prodListIds[] = (string) $resolvedProductId;
                }
            }

            $prod_list = implode(',', $prodListIds);

            if ($prod_list === '') {
                $err4 = "Product is required. Please select a valid product from the list.";
                $error = 1;
            }

            if ($error == 1) {
                break; // Stops the save but allows specific errors to display below fields
            }
            
            $cost = postSpaceFilter('package_cost');
            $cost_curr = postSpaceFilter('cost_curr_hidden');
            $agent_cost = postSpaceFilter('agent_cost');
            $agent_cost_err = postSpaceFilter('agent_cost_err');


            $barcode_slot_total = postSpaceFilter('barcode_slot_total_hidden');
            $dataRemark = postSpaceFilter('currentDataRemark');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
            $returnData = null;
            $errorMsg = '';

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataId)) {
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
                    if ($platform_item_id) {
                        array_push($newvalarr, $platform_item_id);
                        array_push($datafield, 'platform_item_id');
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
                    if ($parent_package_id > 0) {
                        array_push($newvalarr, $parentPackageAuditValue);
                        array_push($datafield, 'parent sku');
                    }

                    if ($barcode_slot_total) {
                        array_push($newvalarr, $barcode_slot_total);
                        array_push($datafield, 'barcode_slot_total');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $safeCurrentDataName = mysqli_real_escape_string($connect, (string) $currentDataName);
                    $safeItemCode = mysqli_real_escape_string($connect, (string) $item_code);
                    $safePlatformItemId = mysqli_real_escape_string($connect, (string) $platform_item_id);
                    $safeItemDescription = mysqli_real_escape_string($connect, (string) $item_description);
                    $safeBrand = mysqli_real_escape_string($connect, (string) $brand);
                    $safeCost = mysqli_real_escape_string($connect, (string) $cost);
                    $safeCostCurr = mysqli_real_escape_string($connect, (string) $cost_curr);
                    $safeAgentCost = mysqli_real_escape_string($connect, (string) $agent_cost);
                    $safePkgPrice = mysqli_real_escape_string($connect, (string) $pkg_price);
                    $safeCurUnit = mysqli_real_escape_string($connect, (string) $cur_unit);
                    $safeProdList = mysqli_real_escape_string($connect, (string) $prod_list);
                    $safeParentPackageValue = $parent_package_id > 0 ? (string) ((int) $parent_package_id) : 'NULL';
                    $safeBarcodeSlotTotal = mysqli_real_escape_string($connect, (string) $barcode_slot_total);
                    $safeDataRemark = mysqli_real_escape_string($connect, (string) $dataRemark);

                    $query = "INSERT INTO " . $tblName . "(name,item_code,platform_item_id,item_description,brand,cost,cost_curr,agent_cost,price,currency_unit,product,parent_package_id,barcode_slot_total,remark,create_by,create_date,create_time) VALUES ('$safeCurrentDataName','$safeItemCode','$safePlatformItemId','$safeItemDescription','$safeBrand','$safeCost', '$safeCostCurr','$safeAgentCost','$safePkgPrice','$safeCurUnit','$safeProdList'," . $safeParentPackageValue . ",'$safeBarcodeSlotTotal','$safeDataRemark','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($connect, $query);
                    if ($returnData) {
                        $dataId = $connect->insert_id;
                        generateDBData($tblName, $connect);
                    } else {
                        $errorMsg = mysqli_error($connect);
                        $act = "F";
                    }
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

                    if ((isset($row['platform_item_id']) ? (string) $row['platform_item_id'] : '') != $platform_item_id) {
                        array_push($oldvalarr, isset($row['platform_item_id']) && $row['platform_item_id'] != '' ? $row['platform_item_id'] : 'Empty Value');
                        array_push($chgvalarr, $platform_item_id != '' ? $platform_item_id : 'Empty Value');
                        array_push($datafield, 'platform_item_id');
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

                    $currentParentPackageId = isset($row['parent_package_id']) ? (int) $row['parent_package_id'] : 0;
                    if ($currentParentPackageId !== (int) $parent_package_id) {
                        $currentParentRows = $currentParentPackageId > 0 ? commonPackageLoadRowsByIds($connect, array($currentParentPackageId), true) : array();
                        $currentParentAuditValue = ($currentParentPackageId > 0 && isset($currentParentRows[$currentParentPackageId]))
                            ? packageFormatRelationValue($currentParentRows[$currentParentPackageId])
                            : 'Empty Value';
                        array_push($oldvalarr, $currentParentAuditValue);
                        array_push($chgvalarr, $parent_package_id > 0 ? $parentPackageAuditValue : 'Empty Value');
                        array_push($datafield, 'parent sku');
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
                        $safeCurrentDataName = mysqli_real_escape_string($connect, (string) $currentDataName);
                        $safeItemCode = mysqli_real_escape_string($connect, (string) $item_code);
                        $safePlatformItemId = mysqli_real_escape_string($connect, (string) $platform_item_id);
                        $safeItemDescription = mysqli_real_escape_string($connect, (string) $item_description);
                        $safeBrand = mysqli_real_escape_string($connect, (string) $brand);
                        $safeCost = mysqli_real_escape_string($connect, (string) $cost);
                        $safeCostCurr = mysqli_real_escape_string($connect, (string) $cost_curr);
                        $safeAgentCost = mysqli_real_escape_string($connect, (string) $agent_cost);
                        $safePkgPrice = mysqli_real_escape_string($connect, (string) $pkg_price);
                        $safeCurUnit = mysqli_real_escape_string($connect, (string) $cur_unit);
                        $safeProdList = mysqli_real_escape_string($connect, (string) $prod_list);
                        $safeParentPackageValue = $parent_package_id > 0 ? (string) ((int) $parent_package_id) : 'NULL';
                        $safeBarcodeSlotTotal = mysqli_real_escape_string($connect, (string) $barcode_slot_total);
                        $safeDataRemark = mysqli_real_escape_string($connect, (string) $dataRemark);
                        $safeDataId = (int) $dataId;

                        $query = "UPDATE " . $tblName . " SET name ='$safeCurrentDataName', item_code='$safeItemCode', platform_item_id='$safePlatformItemId', item_description='$safeItemDescription', brand='$safeBrand',cost='$safeCost',cost_curr='$safeCostCurr',agent_cost='$safeAgentCost',price ='$safePkgPrice', currency_unit ='$safeCurUnit', product ='$safeProdList', parent_package_id =" . $safeParentPackageValue . ", barcode_slot_total ='$safeBarcodeSlotTotal', remark ='$safeDataRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$safeDataId'";
                        $returnData = mysqli_query($connect, $query);
                        if (!$returnData) {
                            $errorMsg = mysqli_error($connect);
                            $act = "F";
                        } else {
                            generateDBData($tblName, $connect);
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
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, $returnData ? '' : $errorMsg);
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, $returnData ? '' : $errorMsg);
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
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
}

$parentPackageDisplayName = trim((string) post('parent_package_name'));
$parentPackageDisplayId = trim((string) post('parent_package_id'));
if (isset($parent_package_name)) {
    $parentPackageDisplayName = trim((string) $parent_package_name);
}
if (isset($parent_package_id)) {
    $parentPackageDisplayId = trim((string) $parent_package_id);
}
if ($parentPackageDisplayName === '' && isset($row['parent_package_id']) && (int) $row['parent_package_id'] > 0) {
    $parentPackageRows = commonPackageLoadRowsByIds($connect, array((int) $row['parent_package_id']), true);
    if (isset($parentPackageRows[(int) $row['parent_package_id']])) {
        $parentPackageDisplayName = packageFormatRelationValue($parentPackageRows[(int) $row['parent_package_id']]);
        $parentPackageDisplayId = (string) ((int) $row['parent_package_id']);
    }
}

$linkedChildPackages = array();
if (!empty($dataId) && ctype_digit((string) $dataId)) {
    $linkedChildResult = mysqli_query(
        $connect,
        "SELECT id, name, item_code FROM `" . PKG . "` WHERE status = 'A' AND parent_package_id = " . (int) $dataId . " ORDER BY name ASC, id ASC"
    );
    if ($linkedChildResult) {
        while ($linkedChildRow = mysqli_fetch_assoc($linkedChildResult)) {
            $linkedChildPackages[] = $linkedChildRow;
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>
<style>
    .platform-item-id-wrapper {
        min-height: 38px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        padding: 4px 6px;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
        background-color: #fff;
    }

    .platform-item-id-wrapper.readonly {
        background-color: #e9ecef;
    }

    .platform-item-id-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .platform-item-id-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid #d0d7de;
        border-radius: 16px;
        padding: 3px 8px;
        background-color: #f8f9fa;
        font-size: 0.875rem;
    }

    .platform-item-id-remove {
        border: 0;
        background: #999;
        color: #fff;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        line-height: 16px;
        padding: 0;
        font-size: 12px;
    }

    .platform-item-id-input {
        border: 0;
        outline: 0;
        min-width: 160px;
        flex: 1;
        font-weight: normal !important;
        font-size: 16px;
        color: #212529;
    }
</style>

    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/package.css">
</head>

<body>
    <?php if ($copyFromPackageId > 0 && !post('actionBtn')) { ?>
        <script>
            // The shared form helper restores a saved draft over every field on
            // DOMContentLoaded. A leftover Add draft would overwrite the values we just
            // copied, so drop it here, while inline scripts still run before that event.
            (function () {
                if (typeof clearLocalStoragePreservingCustomerRecordFilters === "function") {
                    clearLocalStoragePreservingCustomerRecordFilters();
                } else if (window.localStorage) {
                    window.localStorage.clear();
                }
            })();
        </script>
    <?php } ?>

    <div class="page-load-cover">

        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php echo $pageActionTitle ?>
            </p>
        </div>
        <div id="formContainer" class="container-fluid mt-2">
            <div class="col-12 col-md-12 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <?php if ($copyFromPackageId > 0) { ?>
                        <input type="hidden" name="copy_from" value="<?php echo (int) $copyFromPackageId; ?>">
                    <?php } ?>
                    <div class="form-group mb-5">
                        <h2>
                            <?php echo $pageActionTitle ?>
                        </h2>
                        <?php if (isset($copySourceError)) { ?>
                            <div class="alert alert-warning mt-3 mb-0" role="alert">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                <?php echo htmlspecialchars($copySourceError, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php } else if ($copyFromPackageId > 0) { ?>
                            <div class="alert alert-info mt-3 mb-0" role="alert">
                                <i class="fa-solid fa-copy me-1"></i>
                                Copied from <strong><?php echo htmlspecialchars($copySourceLabel, ENT_QUOTES, 'UTF-8'); ?></strong>.
                                Review the details below &mdash; <strong>Name</strong> and <strong>Item Code</strong> already carry a
                                &ldquo;(Copy)&rdquo; suffix so they stay unique. Saving creates a new package; the original is not changed.
                            </div>
                        <?php } ?>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" for="currentDataName"><?php echo $pageTitle ?>
                                    Name<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="currentDataName" id="currentDataName"
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('currentDataName') : (isset($row['name']) ? $row['name'] : '')); ?>"
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
                                            value="<?php echo htmlspecialchars(post('actionBtn') ? post('item_code') : (isset($row['item_code']) ? $row['item_code'] : '')); ?>" 
                                            <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label form_lbl" id="platform_item_id_lbl" for="platform_item_id_input">Platform Item ID</label>
                                        <div class="platform-item-id-wrapper<?php echo ($act == '') ? ' readonly' : ''; ?>" id="platform_item_id_wrapper">
                                            <div class="platform-item-id-tags" id="platform_item_id_tags"></div>
                                            <?php if ($act != '') { ?>
                                                <input class="platform-item-id-input" type="text" id="platform_item_id_input" autocomplete="off">
                                            <?php } ?>
                                        </div>
                                        <input type="hidden" name="platform_item_id" id="platform_item_id"
                                            value="<?php echo htmlspecialchars(post('actionBtn') ? post('platform_item_id') : (isset($row['platform_item_id']) ? $row['platform_item_id'] : '')); ?>">
                                        <small class="text-muted">Press Enter to add another platform item ID.</small>
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label form_lbl" id="item_description_lbl" for="item_description">Item Description<span class="requireRed">*</span></label>
                                        <input class="form-control" type="text" name="item_description" id="item_description" 
                                            value="<?php echo htmlspecialchars(post('actionBtn') ? post('item_description') : (isset($row['item_description']) ? $row['item_description'] : '')); ?>" 
                                            <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                                    </div>
                                </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" id="price_lbl" for="price">Selling Price<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="number" name="price" id="price"
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('price') : (isset($row['price']) ? $row['price'] : '')); ?>"
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
                                if (post('actionBtn')) {
                                    $curUnitName = post('cur_unit');
                                }
                                ?>
                                <input class="form-control" type="text" name="cur_unit" id="cur_unit"
                                    value="<?php echo htmlspecialchars($curUnitName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="cur_unit_hidden" id="cur_unit_hidden"
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('cur_unit_hidden') : (isset($row['currency_unit']) ? $row['currency_unit'] : '')); ?>">
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
                                if (post('actionBtn')) {
                                    $brandName = post('brand');
                                }
                                ?>
                                <input class="form-control" type="text" name="brand" id="brand"
                                    value="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="brand_hidden" id="brand_hidden"
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('brand_hidden') : (isset($row['brand']) ? $row['brand'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
                                
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
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('package_cost') : (isset($row['cost']) ? $row['cost'] : '')); ?>"
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
                                if (post('actionBtn')) {
                                    $costUnitName = post('cost_curr');
                                }
                                ?>
                                <input class="form-control" type="text" name="cost_curr" id="cost_curr"
                                    value="<?php echo htmlspecialchars($costUnitName); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> required>
                                <input type="hidden" name="cost_curr_hidden" id="cost_curr_hidden"
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('cost_curr_hidden') : (isset($row['cost_curr']) ? $row['cost_curr'] : '')); ?>">
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($cost_curr_err)) echo $cost_curr_err; ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <div class="form-group autocomplete mb-3">
                                <label class="form-label form_lbl" for="parent_package_name">Parent SKU / Warehouse SKU</label>
                                <input class="form-control" type="text" name="parent_package_name" id="parent_package_name"
                                    value="<?php echo htmlspecialchars($parentPackageDisplayName, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php if ($act == '') echo 'readonly'; ?> autocomplete="off">
                                <input type="hidden" name="parent_package_id" id="parent_package_id"
                                    value="<?php echo htmlspecialchars($parentPackageDisplayId, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (isset($parent_package_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo htmlspecialchars($parent_package_err, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php } ?>
                                <small class="text-muted">Warehouse will use parent SKU package and product stock for stock-out.</small>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label form_lbl" for="agent_cost">Agent Cost (RM)<span class="requireRed">*</span></label>
                                <input class="form-control" type="number" name="agent_cost" id="agent_cost" step="0.01"
                                    value="<?php echo htmlspecialchars(post('actionBtn') ? post('agent_cost') : (isset($row['agent_cost']) ? $row['agent_cost'] : '')); ?>"
                                    <?php if ($act == '') echo 'readonly' ?> required>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($agent_cost_err)) echo $agent_cost_err; ?></span>
                                </div>
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

                                $postedProdNames = postSpaceFilter('prod_name');
                                $postedProdVals = postSpaceFilter('prod_val');

                                if (!is_array($postedProdNames)) {
                                    $postedProdNames = array();
                                }

                                if (!is_array($postedProdVals)) {
                                    $postedProdVals = array();
                                }

                                $hasPostedProductRows = is_array(post('prod_name')) || is_array(post('prod_val'));
                                $displayProductIds = array();

                                if ($hasPostedProductRows) {
                                    $productRowCount = max(count($postedProdNames), count($postedProdVals));
                                    for ($productIndex = 0; $productIndex < $productRowCount; $productIndex++) {
                                        $productId = isset($postedProdVals[$productIndex]) ? trim((string) $postedProdVals[$productIndex]) : '';
                                        $displayProductIds[] = ($productId !== '' && ctype_digit($productId)) ? (int) $productId : 0;
                                    }
                                } else if (isset($row['product']) && trim((string) $row['product']) !== '') {
                                    $savedProductIds = explode(',', (string) $row['product']);
                                    foreach ($savedProductIds as $savedProductId) {
                                        $savedProductId = trim((string) $savedProductId);
                                        if ($savedProductId !== '' && ctype_digit($savedProductId)) {
                                            $displayProductIds[] = (int) $savedProductId;
                                        }
                                    }
                                }

                                $productRowsById = packageLoadProductDisplayRowsByIds($connect, $displayProductIds);

                                if (empty($displayProductIds)) {
                                    $displayProductIds[] = 0;
                                }

                                foreach ($displayProductIds as $productIndex => $pid) {
                                    $num = $productIndex + 1;
                                    $pid = (int) $pid;
                                    $productDisplayRow = ($pid > 0 && isset($productRowsById[$pid])) ? $productRowsById[$pid] : array();

                                    $pn = isset($postedProdNames[$productIndex]) ? trim((string) $postedProdNames[$productIndex]) : '';
                                    $pw = '';
                                    $pwu = '';
                                    $pwun = '';
                                    $ps = '0';
                                    $pslot = '0';

                                    if (!empty($productDisplayRow)) {
                                        $dbProductName = isset($productDisplayRow['name']) ? trim((string) $productDisplayRow['name']) : '';
                                        if ($dbProductName !== '') {
                                            $pn = $dbProductName;
                                        }

                                        $pw = isset($productDisplayRow['weight']) ? $productDisplayRow['weight'] : '';
                                        $pwu = isset($productDisplayRow['weight_unit']) ? $productDisplayRow['weight_unit'] : '';
                                        $pwun = isset($productDisplayRow['weight_unit_name']) ? $productDisplayRow['weight_unit_name'] : '';
                                        $ps = isset($productDisplayRow['barcode_status']) && trim((string) $productDisplayRow['barcode_status']) !== '' ? $productDisplayRow['barcode_status'] : '0';
                                        $pslot = isset($productDisplayRow['barcode_slot']) && trim((string) $productDisplayRow['barcode_slot']) !== '' ? $productDisplayRow['barcode_slot'] : '0';
                                    } else if ($pid > 0 && $pn === '') {
                                        $pn = 'Product #' . $pid . ' not found';
                                    }
                                ?>
                                    <tr>
                                        <td><?= $num ?></td>
                                        <td class="autocomplete">
                                            <input type="text" name="prod_name[]" id="prod_name_<?= $num ?>" value="<?= htmlspecialchars($pn, ENT_QUOTES, 'UTF-8') ?>" onkeyup="prodInfo(this)"<?= $readonly ?>>
                                            <input type="hidden" name="prod_val[]" id="prod_val_<?= $num ?>" value="<?= htmlspecialchars((string) ($pid > 0 ? $pid : ''), ENT_QUOTES, 'UTF-8') ?>" oninput="prodInfoAutoFill(this)">
                                            <div id="err_msg"><span class="mt-n1"><?php if (isset($err4)) echo $err4; ?></span></div>
                                        </td>
                                        <td><input class="readonlyInput" type="text" name="wgt[]" id="wgt_<?= $num ?>" value="<?= htmlspecialchars((string) $pw, ENT_QUOTES, 'UTF-8') ?>" readonly></td>
                                        <td>
                                            <input class="readonlyInput" type="text" name="wgt_unit[]" id="wgt_unit_<?= $num ?>" value="<?= htmlspecialchars((string) $pwun, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            <input type="hidden" name="wgt_unit_val[]" id="wgt_unit_val_<?= $num ?>" value="<?= htmlspecialchars((string) $pwu, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        </td>
                                        <td><input class="readonlyInput" type="text" name="barcode_status[]" id="barcode_status_<?= $num ?>" value="<?= htmlspecialchars((string) $ps, ENT_QUOTES, 'UTF-8') ?>" readonly></td>
                                        <td><input class="readonlyInput" type="text" name="barcode_slot[]" id="barcode_slot_<?= $num ?>" value="<?= htmlspecialchars((string) $pslot, ENT_QUOTES, 'UTF-8') ?>" readonly></td>
                                        <?php if ($act != ''): ?>
                                            <td>
                                                <?php if ($num == 1): ?>
                                                    <button class="mt-1" id="action_menu_btn" type="button" onclick="Add()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                <?php else: ?>
                                                    <button class="mt-1" id="action_menu_btn" type="button" onclick="Remove(this)"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000" value="Remove"></i></button>
                                                <?php endif; ?>
                                            </td>
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
                                                if (post('barcode_slot_total_hidden') !== '')
                                                    echo htmlspecialchars(post('barcode_slot_total_hidden'));
                                                else if (isset($dataExisted) && isset($row['barcode_slot_total']))
                                                    echo $row['barcode_slot_total'];
                                                else echo '0';
                                            }
                                            ?><input name="barcode_slot_total_hidden" id="barcode_slot_total_hidden"
                                                type="hidden"
                                                value="<?php echo htmlspecialchars(post('actionBtn') ? post('barcode_slot_total_hidden') : (isset($row['barcode_slot_total']) ? $row['barcode_slot_total'] : '')); ?>">
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
                            <?php if ($act == '') echo 'readonly' ?>><?php echo htmlspecialchars(post('actionBtn') ? post('currentDataRemark') : (isset($row['remark']) ? $row['remark'] : '')); ?></textarea>
                    </div>
                    <?php if (!empty($linkedChildPackages)) { ?>
                        <div class="form-group mb-3">
                            <label class="form-label">Linked Child SKUs</label>
                            <div class="form-control" style="min-height: auto; background-color: #f8f9fa; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px;">
                                <?php foreach ($linkedChildPackages as $linkedChildPackage) {
                                    $linkedChildLabel = packageFormatRelationDisplayName($linkedChildPackage);
                                ?>
                                    <div><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($linkedChildLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn"
                            value="back" formnovalidate>Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    //Initial Page And Action Value
    const page = "<?= $pageTitle ?>";
    const action = "<?php echo isset($act) ? $act : ''; ?>";

    checkCurrentPage(page, action);
    setButtonColor();
            preloader(300, action);

    </script>

</body>

<script>
<?php include __DIR__ . '/../js/package.js'; ?>
</script>

</html>
