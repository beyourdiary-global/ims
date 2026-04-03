<?php
$currentPagePin = 126;
$pageTitle = "Stock Order Request";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
include_once ROOT . '/header/phpqrcode/qrlib.php';

$permissionPage = 'Stock Order Request';
$pinAccess = checkPin($connect, $permissionPage);
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $pinAccess = checkPin($connect, 'Stock List');
}

$tblName = STOCK_ORDER_REQ;

$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . ' ' . $pageTitle;
$actionBtnValue = ($act === 'I') ? 'addRecord' : 'updRecord';

$redirect_page = $SITEURL . '/finance/stock_order_request_table.php';
$redirectLink = "<script>location.href = '$redirect_page';</script>";

if ((!$dataID && !$act) || ($act && !isActionAllowed($pageAction, is_array($pinAccess) ? $pinAccess : array()))) {
    echo $redirectLink;
    exit;
}

$warehouses = array();
$warehouseRst = mysqli_query($connect, "SELECT id,name FROM " . WHSE . " WHERE status='A' ORDER BY name ASC");
if ($warehouseRst) {
    while ($warehouseRow = $warehouseRst->fetch_assoc()) {
        $warehouses[] = $warehouseRow;
    }
}

$couriers = array();
$courierRst = mysqli_query($connect, "SELECT id,name FROM " . COURIER . " WHERE status='A' ORDER BY name ASC");
if ($courierRst) {
    while ($courierRow = $courierRst->fetch_assoc()) {
        $couriers[] = $courierRow;
    }
}

$products = array();
$productNameMap = array();
$productNameToId = array();
$productBrandDirectMap = array();
$productRst = mysqli_query($connect, "SELECT id,name,brand FROM " . PROD . " WHERE status='A' ORDER BY name ASC");
if ($productRst) {
    while ($productRow = $productRst->fetch_assoc()) {
        $prodId = (int) $productRow['id'];
        $prodName = (string) $productRow['name'];
        $products[] = array('id' => $prodId, 'name' => $prodName);
        $productNameMap[$prodId] = $prodName;
        $productNameToId[strtolower(trim($prodName))] = $prodId;
        $productBrandDirectMap[$prodId] = isset($productRow['brand']) ? (int) $productRow['brand'] : 0;
    }
}

$packages = array();
$packageMap = array();
$packageNameMap = array();
$packageDescMap = array();
$packageNameToId = array();
$packageProductMap = array();
$packageBrandMap = array();
$brandCompanyMap = array();
$productHasPackageMap = array();
$brandRst = mysqli_query($connect, "SELECT id, company FROM " . BRAND . " WHERE status='A'");
if ($brandRst) {
    while ($brandRow = mysqli_fetch_assoc($brandRst)) {
        $brandCompanyMap[(int) $brandRow['id']] = isset($brandRow['company']) ? (int) $brandRow['company'] : 0;
    }
}

$companySqlAccountFolderMap = array();
$companySqlRst = mysqli_query($connect, "SELECT c.id AS company_id, s.name AS sql_account_name FROM " . COMPANY . " c LEFT JOIN " . SQL_ACC . " s ON c.sql_account_id = s.id WHERE c.status='A'");
if ($companySqlRst) {
    while ($companySqlRow = mysqli_fetch_assoc($companySqlRst)) {
        $companyId = isset($companySqlRow['company_id']) ? (int) $companySqlRow['company_id'] : 0;
        if ($companyId <= 0) {
            continue;
        }
        $sqlAccountName = isset($companySqlRow['sql_account_name']) ? trim((string) $companySqlRow['sql_account_name']) : '';
        $folder = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $sqlAccountName));
        $folder = trim((string) $folder, '_');
        if ($folder !== '') {
            $companySqlAccountFolderMap[$companyId] = $folder;
        }
    }
}

$packageRst = mysqli_query($connect, "SELECT id,name,item_description,price,product,brand FROM " . PKG . " WHERE status='A' ORDER BY name ASC");
if ($packageRst) {
    while ($packageRow = $packageRst->fetch_assoc()) {
        $pkgId = (int) $packageRow['id'];
        $pkgName = (string) $packageRow['name'];
        $pkgDesc = (string) $packageRow['item_description'];
        $pkgPrice = isset($packageRow['price']) ? (float) $packageRow['price'] : 0;
        $pkgProductCsv = isset($packageRow['product']) ? (string) $packageRow['product'] : '';
        $pkgProductIds = array();
        if ($pkgProductCsv !== '') {
            foreach (explode(',', $pkgProductCsv) as $prodIdRaw) {
                $prodId = (int) trim((string) $prodIdRaw);
                if ($prodId > 0) {
                    $pkgProductIds[] = $prodId;
                }
            }
        }
        $pkgProductIds = array_values(array_unique($pkgProductIds));
        $packages[] = array(
            'id' => $pkgId,
            'name' => $pkgName,
            'item_description' => $pkgDesc,
            'price' => $pkgPrice,
            'product_ids' => $pkgProductIds,
            'brand_id' => isset($packageRow['brand']) ? (int) $packageRow['brand'] : 0,
        );
        $packageMap[$pkgId] = $pkgPrice;
        $packageNameMap[$pkgId] = $pkgName;
        $packageDescMap[$pkgId] = $pkgDesc;
        $packageNameToId[strtolower(trim($pkgName))] = $pkgId;
        $packageProductMap[$pkgId] = $pkgProductIds;
        $packageBrandMap[$pkgId] = isset($packageRow['brand']) ? (int) $packageRow['brand'] : 0;
    }
}

foreach ($products as $productRow) {
    $productHasPackageMap[(int) $productRow['id']] = false;
}
foreach ($packageProductMap as $linkedProductIds) {
    foreach ($linkedProductIds as $linkedProductId) {
        $productHasPackageMap[(int) $linkedProductId] = true;
    }
}

$warehouseNameMap = array();
$warehouseNameToId = array();
foreach ($warehouses as $wh) {
    $whId = (int) $wh['id'];
    $whName = (string) $wh['name'];
    $warehouseNameMap[$whId] = $whName;
    $warehouseNameToId[strtolower(trim($whName))] = $whId;
}

$courierNameMap = array();
$courierNameToId = array();
foreach ($couriers as $cr) {
    $crId = trim((string) $cr['id']);
    if ($crId === '') continue;
    $crName = (string) $cr['name'];
    $courierNameMap[$crId] = $crName;
    $courierNameToId[strtolower(trim($crName))] = $crId;
}

$row = array();
$itemRows = array();
$packageItemRows = array();

if ($dataID) {
    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', STOCK_ORDER_REQ, $finance_connect);
    if ($rst && $rst->num_rows > 0) {
        $row = $rst->fetch_assoc();

        $itemSql = "SELECT *
                    FROM " . STOCK_ORDER_REQ_ITEM . "
                    WHERE request_id = '" . (int) $dataID . "' AND status = 'A'
                    ORDER BY id ASC";
        $itemRst = mysqli_query($finance_connect, $itemSql);
        if ($itemRst) {
            while ($item = mysqli_fetch_assoc($itemRst)) {
                $item['product_id'] = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                $pkgId = (int) $item['package_id'];
                if ((int) $item['product_id'] <= 0 && isset($packageProductMap[$pkgId]) && count($packageProductMap[$pkgId]) === 1) {
                    $item['product_id'] = (int) $packageProductMap[$pkgId][0];
                }
                $item['package_desc'] = isset($item['package_desc']) ? $item['package_desc'] : '';
                if ($item['package_desc'] === '' && isset($packages)) {
                    foreach ($packages as $pkgData) {
                        if ((int) $pkgData['id'] === $pkgId) {
                            $item['package_desc'] = $pkgData['item_description'];
                            break;
                        }
                    }
                }
                $legacyQty = isset($item['qty']) ? (int) $item['qty'] : 0;
                $itemPackageQty = isset($item['packageQty']) ? (int) $item['packageQty'] : $legacyQty;
                $itemProductQty = isset($item['productQty']) ? (int) $item['productQty'] : $itemPackageQty;
                if ($itemPackageQty <= 0) $itemPackageQty = 1;
                if ($itemProductQty <= 0) $itemProductQty = $itemPackageQty;
                $item['packageQty'] = $itemPackageQty;
                $item['productQty'] = $itemProductQty;
                $item['package_price'] = isset($item['package_price']) ? (float) $item['package_price'] : 0.00;
                $item['package_group_key'] = isset($item['package_group_key']) ? trim((string) $item['package_group_key']) : '';
                if ($item['package_price'] <= 0 && $pkgId > 0 && isset($packageMap[$pkgId])) {
                    $item['package_price'] = ((float) $packageMap[$pkgId]) * $itemPackageQty;
                }
                $itemRows[] = $item;
            }
        }
    } else {
        $act = 'F';
    }
}

if (!empty($itemRows)) {
    $packageItemRows = $itemRows;
}

if ($act == 'D' && $dataID) {
    $deleteHeaderQuery = "UPDATE " . STOCK_ORDER_REQ . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . (int) $dataID . "'";
    $deleteItemQuery = "UPDATE " . STOCK_ORDER_REQ_ITEM . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE request_id='" . (int) $dataID . "'";
    $deleteHeaderResult = mysqli_query($finance_connect, $deleteHeaderQuery);
    $deleteItemResult = mysqli_query($finance_connect, $deleteItemQuery);

    $orderNoForLog = isset($row['invoice_no']) ? normalizeAuditLogValue($row['invoice_no']) : 'SOR-' . (int) $dataID;
    $delErrMsg = '';
    if (!$deleteHeaderResult || !$deleteItemResult) {
        $delErrMsg = mysqli_error($finance_connect);
    }

    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $deleteHeaderQuery . '; ' . $deleteItemQuery,
        'query_table' => $tblName . ', ' . STOCK_ORDER_REQ_ITEM,
        'oldval' => 'Order No: ' . $orderNoForLog,
        'act_msg' => USER_NAME . ' ' . (!$deleteHeaderResult || !$deleteItemResult ? 'failed to delete' : 'deleted') . ' Stock Order Request [<b>ID = ' . (int) $dataID . '</b>] from <b><i>' . $tblName . '</i></b>' . ($delErrMsg !== '' ? ' (' . $delErrMsg . ')' : '.') ,
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);

    $_SESSION['delChk'] = 1;
    echo "<script>location.href='$redirect_page';</script>";
    exit;
}

if ($dataID && !$act && USER_ID && empty($_SESSION['viewChk']) && empty($_SESSION['delChk'])) {
    $_SESSION['viewChk'] = 1;

    $viewActMsg = USER_NAME . " viewed the data [<b>ID = " . (int) $dataID . "</b>] from <b><i>" . $tblName . " Table</i></b>.";
    if (empty($row)) {
        $viewActMsg = USER_NAME . " failed to view the data [<b>ID = " . (int) $dataID . "</b>] from <b><i>" . $tblName . " Table</i></b>.";
    }

    $log = [
        'log_act' => 'View',
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

$showQrPanel = false;
$orderLink = '';
$qrWebPath = '';
$modalAct = '';
$existingItemPairById = array();
foreach ($itemRows as $existingItemRow) {
    $existingId = isset($existingItemRow['id']) ? (int) $existingItemRow['id'] : 0;
    if ($existingId > 0) {
        $existingItemPairById[$existingId] = array(
            'product_id' => isset($existingItemRow['product_id']) ? (int) $existingItemRow['product_id'] : 0,
            'package_id' => isset($existingItemRow['package_id']) ? (int) $existingItemRow['package_id'] : 0,
        );
    }
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($action === 'back') {
        echo $redirectLink;
        exit;
    }

    if ($action === 'addRecord' || $action === 'updRecord') {
        $sor_warehouse = postSpaceFilter('sor_warehouse');
        $sor_warehouse_name = postSpaceFilter('sor_warehouse_name');
        $sor_invoice_no = postSpaceFilter('sor_invoice_no');
        $sor_invoice_date = postSpaceFilter('sor_invoice_date');
        $sor_request_date = postSpaceFilter('sor_request_date');
        $sor_courier = postSpaceFilter('sor_courier');
        $sor_courier_name = postSpaceFilter('sor_courier_name');
        $sor_tracking_no = postSpaceFilter('sor_tracking_no');
        $sor_total_price = postSpaceFilter('sor_total_price');
        $sor_remark = postSpaceFilter('sor_remark');

        $prodIdArr = isset($_POST['sor_item_prod_id']) ? postSpaceFilter('sor_item_prod_id') : array();
        $itemIdArr = isset($_POST['sor_item_id']) ? postSpaceFilter('sor_item_id') : array();
        $rowRoleArr = isset($_POST['sor_item_row_role']) ? postSpaceFilter('sor_item_row_role') : array();
        $prodNameArr = isset($_POST['sor_item_prod_name']) ? postSpaceFilter('sor_item_prod_name') : array();
        $pkgIdArr = isset($_POST['sor_item_pkg_id']) ? postSpaceFilter('sor_item_pkg_id') : array();
        $pkgNameArr = isset($_POST['sor_item_pkg_name']) ? postSpaceFilter('sor_item_pkg_name') : array();
        $pkgDescArr = isset($_POST['sor_item_desc']) ? postSpaceFilter('sor_item_desc') : array();
        $pkgQtyArr = isset($_POST['sor_item_package_qty']) ? postSpaceFilter('sor_item_package_qty') : array();
        $productQtyArr = isset($_POST['sor_item_product_qty']) ? postSpaceFilter('sor_item_product_qty') : array();
        $packageGroupKeyArr = isset($_POST['sor_item_group_key']) ? postSpaceFilter('sor_item_group_key') : array();
        $packagePriceArr = isset($_POST['sor_item_package_price']) ? postSpaceFilter('sor_item_package_price') : array();

        if (!is_array($prodIdArr)) $prodIdArr = array();
        if (!is_array($itemIdArr)) $itemIdArr = array();
        if (!is_array($rowRoleArr)) $rowRoleArr = array();
        if (!is_array($prodNameArr)) $prodNameArr = array();
        if (!is_array($pkgIdArr)) $pkgIdArr = array();
        if (!is_array($pkgNameArr)) $pkgNameArr = array();
        if (!is_array($pkgDescArr)) $pkgDescArr = array();
        if (!is_array($pkgQtyArr)) $pkgQtyArr = array();
        if (!is_array($productQtyArr)) $productQtyArr = array();
        if (!is_array($packageGroupKeyArr)) $packageGroupKeyArr = array();
        if (!is_array($packagePriceArr)) $packagePriceArr = array();

        if ((int) $sor_warehouse <= 0 && trim((string) $sor_warehouse_name) !== '') {
            $whKey = strtolower(trim((string) $sor_warehouse_name));
            if (isset($warehouseNameToId[$whKey])) {
                $sor_warehouse = (string) $warehouseNameToId[$whKey];
            }
        }

        if ($sor_courier === '' && trim((string) $sor_courier_name) !== '') {
            $crKey = strtolower(trim((string) $sor_courier_name));
            if (isset($courierNameToId[$crKey])) {
                $sor_courier = (string) $courierNameToId[$crKey];
            }
        }

        $items = array();
        $invalidProducts = array();
        $invalidPackages = array();
        $productsWithoutPackage = array();
        $mismatchItems = array();
        $computedTotal = 0.00;
        $countedPackageTotals = array();
        $resolvedBrandCounts = array();
        $resolvedCompanyCounts = array();
        $resolvedBrandFirstOrder = array();
        $resolvedCompanyFirstOrder = array();
        $maxCount = max(count($prodIdArr), count($prodNameArr), count($pkgIdArr), count($pkgNameArr), count($pkgDescArr), count($pkgQtyArr), count($productQtyArr));

        // Resolve one authoritative package id per package group key so product rows
        // in the same group cannot be mismatched by stale hidden inputs.
        $groupPackageIdMap = array();
        for ($g = 0; $g < $maxCount; $g++) {
            $gPkgId = isset($pkgIdArr[$g]) ? (int) $pkgIdArr[$g] : 0;
            if ($gPkgId <= 0) {
                continue;
            }
            $gRowRole = isset($rowRoleArr[$g]) ? strtolower(trim((string) $rowRoleArr[$g])) : 'product';
            if ($gRowRole !== 'package') {
                continue;
            }
            $gGroupKey = isset($packageGroupKeyArr[$g]) ? trim((string) $packageGroupKeyArr[$g]) : '';
            if ($gGroupKey === '') {
                continue;
            }
            if (!isset($groupPackageIdMap[$gGroupKey])) {
                $groupPackageIdMap[$gGroupKey] = $gPkgId;
            }
        }

        for ($i = 0; $i < $maxCount; $i++) {
            $prodId = isset($prodIdArr[$i]) ? (int) $prodIdArr[$i] : 0;
            $itemId = isset($itemIdArr[$i]) ? (int) $itemIdArr[$i] : 0;
            $rowRole = isset($rowRoleArr[$i]) ? strtolower(trim((string) $rowRoleArr[$i])) : 'product';
            $prodName = isset($prodNameArr[$i]) ? trim((string) $prodNameArr[$i]) : '';
            $pkgId = isset($pkgIdArr[$i]) ? (int) $pkgIdArr[$i] : 0;
            $pkgName = isset($pkgNameArr[$i]) ? trim((string) $pkgNameArr[$i]) : '';
            $pkgDesc = isset($pkgDescArr[$i]) ? trim((string) $pkgDescArr[$i]) : '';
            $packageQty = isset($pkgQtyArr[$i]) ? (int) $pkgQtyArr[$i] : 0;
            $productQty = isset($productQtyArr[$i]) ? (int) $productQtyArr[$i] : 0;
            $packageGroupKey = isset($packageGroupKeyArr[$i]) ? trim((string) $packageGroupKeyArr[$i]) : '';
            $postedPackagePrice = isset($packagePriceArr[$i]) ? (float) $packagePriceArr[$i] : 0.00;

            if ($rowRole === 'package' && $packageGroupKey !== '' && $pkgId > 0 && !isset($groupPackageIdMap[$packageGroupKey])) {
                $groupPackageIdMap[$packageGroupKey] = $pkgId;
            }

            if ($packageGroupKey !== '' && isset($groupPackageIdMap[$packageGroupKey]) && (int) $groupPackageIdMap[$packageGroupKey] > 0) {
                $pkgId = (int) $groupPackageIdMap[$packageGroupKey];
            }

            if ($prodId <= 0 && $prodName !== '') {
                $prodKey = strtolower(trim($prodName));
                if (isset($productNameToId[$prodKey])) {
                    $prodId = (int) $productNameToId[$prodKey];
                }
            }

            if ($pkgId <= 0 && $pkgName !== '') {
                $pkgKey = strtolower(trim($pkgName));
                if (isset($packageNameToId[$pkgKey])) {
                    $pkgId = (int) $packageNameToId[$pkgKey];
                }
            }

            $hasAnyValue = ($prodName !== '' || $pkgName !== '' || $packageQty > 0 || $productQty > 0 || $prodId > 0 || $pkgId > 0 || $pkgDesc !== '');
            if (!$hasAnyValue) {
                continue;
            }

            if ($prodName !== '' && $prodId <= 0) {
                $invalidProducts[] = $prodName;
                continue;
            }

            if ($prodId > 0 && empty($productHasPackageMap[$prodId])) {
                $productsWithoutPackage[] = $prodName !== '' ? $prodName : (isset($productNameMap[$prodId]) ? $productNameMap[$prodId] : '');
                continue;
            }

            if ($pkgName !== '' && $pkgId <= 0) {
                $invalidPackages[] = $pkgName;
                continue;
            }

            if ($prodId <= 0 || $pkgId <= 0 || $packageQty <= 0 || $productQty <= 0) {
                continue;
            }

            $allowedProducts = isset($packageProductMap[$pkgId]) ? $packageProductMap[$pkgId] : array();
            if ($rowRole !== 'package' && !empty($allowedProducts) && !in_array($prodId, $allowedProducts, true)) {
                $legacyPairUnchanged = false;
                if ($action === 'updRecord' && $itemId > 0 && isset($existingItemPairById[$itemId])) {
                    $legacyPair = $existingItemPairById[$itemId];
                    $legacyPairUnchanged = ((int) $legacyPair['product_id'] === $prodId && (int) $legacyPair['package_id'] === $pkgId);
                }

                if (!$legacyPairUnchanged) {
                    $mismatchItems[] = $pkgName !== '' ? $pkgName : (isset($packageNameMap[$pkgId]) ? $packageNameMap[$pkgId] : '');
                    continue;
                }
            }

            $pkgPrice = isset($packageMap[$pkgId]) ? (float) $packageMap[$pkgId] : 0.00;
            $pkgBrandId = isset($packageBrandMap[$pkgId]) ? (int) $packageBrandMap[$pkgId] : 0;
            $productBrandId = ($prodId > 0 && isset($productBrandDirectMap[$prodId])) ? (int) $productBrandDirectMap[$prodId] : 0;
            $effectiveBrandId = $productBrandId > 0 ? $productBrandId : $pkgBrandId;
            $pkgCompanyId = ($effectiveBrandId > 0 && isset($brandCompanyMap[$effectiveBrandId])) ? (int) $brandCompanyMap[$effectiveBrandId] : 0;
            if ($pkgDesc === '' && isset($packageDescMap[$pkgId])) {
                $pkgDesc = (string) $packageDescMap[$pkgId];
            }

            if ($packageGroupKey === '') {
                $packageGroupKey = 'pkg_' . $pkgId . '_' . ($i + 1);
            }
            $groupPackagePrice = $postedPackagePrice > 0 ? $postedPackagePrice : ($pkgPrice * $packageQty);
            if (!isset($countedPackageTotals[$packageGroupKey])) {
                $computedTotal += (float) $groupPackagePrice;
                $countedPackageTotals[$packageGroupKey] = true;
            }

            if ($effectiveBrandId > 0) {
                if (!isset($resolvedBrandCounts[$effectiveBrandId])) {
                    $resolvedBrandCounts[$effectiveBrandId] = 0;
                    $resolvedBrandFirstOrder[$effectiveBrandId] = $i;
                }
                $resolvedBrandCounts[$effectiveBrandId]++;
            }
            if ($pkgCompanyId > 0) {
                if (!isset($resolvedCompanyCounts[$pkgCompanyId])) {
                    $resolvedCompanyCounts[$pkgCompanyId] = 0;
                    $resolvedCompanyFirstOrder[$pkgCompanyId] = $i;
                }
                $resolvedCompanyCounts[$pkgCompanyId]++;
            }

            $items[] = array(
                'item_id' => $itemId,
                'row_role' => $rowRole,
                'product_id' => $prodId,
                'brand_id' => $effectiveBrandId,
                'company_id' => $pkgCompanyId,
                'package_id' => $pkgId,
                'package_group_key' => $packageGroupKey,
                'package_desc' => $pkgDesc,
                'package_price' => $groupPackagePrice,
                'packageQty' => $packageQty,
                'productQty' => $productQty,
            );
        }

        $pathBrandCounts = array();
        $pathCompanyCounts = array();
        $pathBrandFirstOrder = array();
        $pathCompanyFirstOrder = array();
        $packageByGroup = array();

        foreach ($items as $idx => $it) {
            $gk = isset($it['package_group_key']) ? trim((string) $it['package_group_key']) : '';
            if ($gk === '') {
                $gk = 'idx_' . $idx;
            }
            $itPkgId = isset($it['package_id']) ? (int) $it['package_id'] : 0;
            if ($itPkgId > 0 && !isset($packageByGroup[$gk])) {
                $packageByGroup[$gk] = $itPkgId;
            }
        }

        $groupOrderCounter = 0;
        foreach ($packageByGroup as $groupPkgId) {
            $groupPkgId = (int) $groupPkgId;
            if ($groupPkgId <= 0) {
                continue;
            }

            $linkedProductIds = isset($packageProductMap[$groupPkgId]) && is_array($packageProductMap[$groupPkgId])
                ? $packageProductMap[$groupPkgId]
                : array();

            if (!empty($linkedProductIds)) {
                foreach ($linkedProductIds as $linkedProductIdRaw) {
                    $linkedProductId = (int) $linkedProductIdRaw;
                    $brandId = ($linkedProductId > 0 && isset($productBrandDirectMap[$linkedProductId])) ? (int) $productBrandDirectMap[$linkedProductId] : 0;
                    if ($brandId <= 0 && isset($packageBrandMap[$groupPkgId])) {
                        $brandId = (int) $packageBrandMap[$groupPkgId];
                    }
                    $companyId = ($brandId > 0 && isset($brandCompanyMap[$brandId])) ? (int) $brandCompanyMap[$brandId] : 0;

                    if ($brandId > 0) {
                        if (!isset($pathBrandCounts[$brandId])) {
                            $pathBrandCounts[$brandId] = 0;
                            $pathBrandFirstOrder[$brandId] = $groupOrderCounter;
                        }
                        $pathBrandCounts[$brandId]++;
                    }
                    if ($companyId > 0) {
                        if (!isset($pathCompanyCounts[$companyId])) {
                            $pathCompanyCounts[$companyId] = 0;
                            $pathCompanyFirstOrder[$companyId] = $groupOrderCounter;
                        }
                        $pathCompanyCounts[$companyId]++;
                    }

                    $groupOrderCounter++;
                }
                continue;
            }

            $fallbackBrandId = isset($packageBrandMap[$groupPkgId]) ? (int) $packageBrandMap[$groupPkgId] : 0;
            $fallbackCompanyId = ($fallbackBrandId > 0 && isset($brandCompanyMap[$fallbackBrandId])) ? (int) $brandCompanyMap[$fallbackBrandId] : 0;

            if ($fallbackBrandId > 0) {
                if (!isset($pathBrandCounts[$fallbackBrandId])) {
                    $pathBrandCounts[$fallbackBrandId] = 0;
                    $pathBrandFirstOrder[$fallbackBrandId] = $groupOrderCounter;
                }
                $pathBrandCounts[$fallbackBrandId]++;
            }
            if ($fallbackCompanyId > 0) {
                if (!isset($pathCompanyCounts[$fallbackCompanyId])) {
                    $pathCompanyCounts[$fallbackCompanyId] = 0;
                    $pathCompanyFirstOrder[$fallbackCompanyId] = $groupOrderCounter;
                }
                $pathCompanyCounts[$fallbackCompanyId]++;
            }
            $groupOrderCounter++;
        }

        $requestBrandId = sorPickDominantIdByCounts($pathBrandCounts, $pathBrandFirstOrder);
        if ($requestBrandId <= 0) {
            $requestBrandId = sorPickDominantIdByCounts($resolvedBrandCounts, $resolvedBrandFirstOrder);
        }

        $requestCompanyId = sorPickDominantIdByCounts($pathCompanyCounts, $pathCompanyFirstOrder);
        if ($requestCompanyId <= 0) {
            $requestCompanyId = sorPickDominantIdByCounts($resolvedCompanyCounts, $resolvedCompanyFirstOrder);
        }

        // --- FIX: Check for duplicate invoice numbers in the database ---
        $safeInvoiceNoCheck = mysqli_real_escape_string($finance_connect, $sor_invoice_no);
        $invCheckQuery = "SELECT id FROM " . STOCK_ORDER_REQ . " WHERE invoice_no = '$safeInvoiceNoCheck' AND status = 'A'";
        if ($action === 'updRecord' && $dataID) {
            $invCheckQuery .= " AND id != '" . (int)$dataID . "'"; // Ignore itself when editing
        }
        $invCheckRst = mysqli_query($finance_connect, $invCheckQuery);
        $isDuplicateInvoice = ($invCheckRst && $invCheckRst->num_rows > 0);
        // -----------------------------------------------------------------

        if (!$sor_warehouse) {
            $err = 'Warehouse cannot be empty.';
        } else if (!isset($warehouseNameMap[(int) $sor_warehouse])) {
            $err = 'Please select a valid warehouse from the list.';
        } else if (!$sor_invoice_no) {
            $invoice_no_err = 'Invoice cannot be empty.';
            $err = $invoice_no_err;
        } else if ($isDuplicateInvoice) {
            $invoice_no_err = 'Invoice number (' . htmlspecialchars($sor_invoice_no) . ') already exists. Please use a different invoice number.';
            $err = $invoice_no_err;
        } else if (!$sor_invoice_date) {
            $err = 'Invoice date cannot be empty.';
        } else if (!$sor_request_date) {
            $err = 'Request date cannot be empty.';
        } else if (!empty($invalidProducts)) {
            $err = 'Please select valid product name from the list.';
        } else if (!empty($productsWithoutPackage)) {
            $err = 'Selected product does not exist in any package, please add product into package first.';
        } else if (!empty($invalidPackages)) {
            $err = 'Please select valid package name from the list.';
        } else if (!empty($mismatchItems)) {
            $err = 'Selected package does not match the selected product.';
        } else if ($sor_courier !== '' && !isset($courierNameMap[(string) $sor_courier])) {
            $err = 'Please select a valid courier from the list.';
        } else if (count($items) === 0) {
            $err = 'Please add at least one package item with quantity.';
        } else {
            $existingAttachment = sorNormalizeAttachmentRelativePath(postSpaceFilter('existing_attachment'));
            $sor_attachment = $existingAttachment;

            if (isset($_FILES['sor_attachment']) && $_FILES['sor_attachment']['error'] === UPLOAD_ERR_OK) {
                $sqlAccountFolder = sorResolveSqlAccountFolderFromCompany($requestCompanyId, $companySqlAccountFolderMap);
                $targetRelativeDir = 'attachment/' . $sqlAccountFolder . '/' . substr((string) comYMD, 0, 4) . '/' . substr((string) comYMD, 4, 2) . '/' . basename(__FILE__, '.php') . '/';
                $targetFsDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . ltrim($targetRelativeDir, '/\\');

                if (!file_exists($targetFsDir)) {
                    mkdir($targetFsDir, 0777, true);
                }

                $original = $_FILES['sor_attachment']['name'];
                $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                $allowed = array('pdf', 'png', 'jpg', 'jpeg', 'zip');

                if (!in_array($ext, $allowed, true)) {
                    $err = 'Attachment format not supported. Allowed: pdf, png, jpg, jpeg, zip.';
                } else {
                    $baseName = (string) pathinfo($original, PATHINFO_FILENAME);
                    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
                    if ($safeBase === '') {
                        $safeBase = 'sor_attachment';
                    }
                    $newName = $safeBase . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    $targetFile = $targetFsDir . $newName;
                    if (!move_uploaded_file($_FILES['sor_attachment']['tmp_name'], $targetFile)) {
                        $err = 'Failed to upload attachment.';
                    } else {
                        $sor_attachment = sorNormalizeAttachmentRelativePath($targetRelativeDir . $newName);
                    }
                }
            }

            if (!isset($err)) {
                $auditDataField = array();
                $auditOldValArr = array();
                $auditNewValArr = array();
                $auditChgValArr = array();
                $auditOldRow = is_array($row) ? $row : array();
                $auditOldItemSnapshot = sorBuildAuditItemSnapshot($itemRows, $packageNameMap, $productNameMap);

                $safeWarehouse = mysqli_real_escape_string($finance_connect, $sor_warehouse);
                $safeInvoiceNo = mysqli_real_escape_string($finance_connect, $sor_invoice_no);
                $safeInvoiceDate = mysqli_real_escape_string($finance_connect, $sor_invoice_date);
                $safeRequestDate = mysqli_real_escape_string($finance_connect, $sor_request_date);
                $safeCourier = mysqli_real_escape_string($finance_connect, $sor_courier);
                $safeTrackingNo = mysqli_real_escape_string($finance_connect, $sor_tracking_no);
                $safeTotalPrice = number_format((float) $computedTotal, 2, '.', '');
                $safeAttachment = mysqli_real_escape_string($finance_connect, (string) $sor_attachment);
                $safeRemark = mysqli_real_escape_string($finance_connect, $sor_remark);
                $courierSqlValue = ($safeCourier === '' ? "NULL" : "'" . $safeCourier . "'");

                if ($action === 'addRecord') {
                    $auditDataField = array('warehouse_id', 'company_id', 'brand_id', 'invoice_no', 'invoice_date', 'request_date', 'courier_id', 'tracking_no', 'total_price', 'attachment', 'remark', 'item_snapshot');
                    $auditNewValArr = array(
                        normalizeAuditLogValue($sor_warehouse),
                        normalizeAuditLogValue($requestCompanyId),
                        normalizeAuditLogValue($requestBrandId),
                        normalizeAuditLogValue($sor_invoice_no),
                        normalizeAuditLogValue($sor_invoice_date),
                        normalizeAuditLogValue($sor_request_date),
                        normalizeAuditLogValue($sor_courier),
                        normalizeAuditLogValue($sor_tracking_no),
                        normalizeAuditLogValue($safeTotalPrice),
                        normalizeAuditLogValue($sor_attachment),
                        normalizeAuditLogValue($sor_remark),
                        normalizeAuditLogValue(sorBuildAuditItemSnapshot($items, $packageNameMap, $productNameMap)),
                    );

                    $query = "INSERT INTO " . STOCK_ORDER_REQ . "
                                                                (warehouse_id, company_id, brand_id, invoice_no, invoice_date, request_date, courier_id, tracking_no, total_price, attachment, remark, create_by, create_date, create_time)
                              VALUES
                                                                ('$safeWarehouse', '" . $requestCompanyId . "', '" . $requestBrandId . "', '$safeInvoiceNo', '$safeInvoiceDate', '$safeRequestDate', " . $courierSqlValue . ", '$safeTrackingNo', '$safeTotalPrice', '$safeAttachment', '$safeRemark', '" . USER_ID . "', CURDATE(), CURTIME())";
                    $returnData = mysqli_query($finance_connect, $query);
                    $dataID = $finance_connect->insert_id;
                } else {
                    $query = "UPDATE " . STOCK_ORDER_REQ . "
                              SET warehouse_id = '$safeWarehouse',
                                                                    company_id = '" . $requestCompanyId . "',
                                                                    brand_id = '" . $requestBrandId . "',
                                  invoice_no = '$safeInvoiceNo',
                                  invoice_date = '$safeInvoiceDate',
                                  request_date = '$safeRequestDate',
                                  courier_id = " . $courierSqlValue . ",
                                  tracking_no = '$safeTrackingNo',
                                  total_price = '$safeTotalPrice',
                                  attachment = '$safeAttachment',
                                  remark = '$safeRemark',
                                  update_by = '" . USER_ID . "',
                                  update_date = CURDATE(),
                                  update_time = CURTIME()
                              WHERE id = '" . (int) $dataID . "'";

                    $auditPairs = array(
                        'warehouse_id' => array(isset($auditOldRow['warehouse_id']) ? $auditOldRow['warehouse_id'] : '', $sor_warehouse),
                        'company_id' => array(isset($auditOldRow['company_id']) ? $auditOldRow['company_id'] : '', $requestCompanyId),
                        'brand_id' => array(isset($auditOldRow['brand_id']) ? $auditOldRow['brand_id'] : '', $requestBrandId),
                        'invoice_no' => array(isset($auditOldRow['invoice_no']) ? $auditOldRow['invoice_no'] : '', $sor_invoice_no),
                        'invoice_date' => array(isset($auditOldRow['invoice_date']) ? $auditOldRow['invoice_date'] : '', $sor_invoice_date),
                        'request_date' => array(isset($auditOldRow['request_date']) ? $auditOldRow['request_date'] : '', $sor_request_date),
                        'courier_id' => array(isset($auditOldRow['courier_id']) ? $auditOldRow['courier_id'] : '', $sor_courier),
                        'tracking_no' => array(isset($auditOldRow['tracking_no']) ? $auditOldRow['tracking_no'] : '', $sor_tracking_no),
                        'total_price' => array(isset($auditOldRow['total_price']) ? $auditOldRow['total_price'] : '', $safeTotalPrice),
                        'attachment' => array(isset($auditOldRow['attachment']) ? $auditOldRow['attachment'] : '', $sor_attachment),
                        'remark' => array(isset($auditOldRow['remark']) ? $auditOldRow['remark'] : '', $sor_remark),
                        'item_snapshot' => array($auditOldItemSnapshot, sorBuildAuditItemSnapshot($items, $packageNameMap, $productNameMap)),
                    );

                    foreach ($auditPairs as $field => $pair) {
                        $oldVal = normalizeAuditLogValue($pair[0]);
                        $newVal = normalizeAuditLogValue($pair[1]);
                        $oldCmpVal = sorCanonicalAuditValue($field, $pair[0]);
                        $newCmpVal = sorCanonicalAuditValue($field, $pair[1]);
                        if ($oldCmpVal !== $newCmpVal) {
                            $auditDataField[] = $field;
                            $auditOldValArr[] = $oldVal;
                            $auditChgValArr[] = $newVal;
                        }
                    }

                    if (!empty($auditDataField) && !empty($auditOldValArr) && !empty($auditChgValArr)) {
                        $returnData = mysqli_query($finance_connect, $query);
                    } else {
                        // No change: keep user on edit page, show no-change modal, do not regenerate QR.
                        $act = 'E';
                        $pageAction = getPageAction($act);
                        $pageActionTitle = $pageAction . ' ' . $pageTitle;
                        $actionBtnValue = 'updRecord';
                        $showQrPanel = false;
                        $modalAct = 'NC';
                    }
                }

                if (isset($returnData) && $returnData) {
                    if ($action === 'addRecord') {
                        foreach ($items as $item) {
                            $safeProdId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                            $safeBrandId = isset($item['brand_id']) ? (int) $item['brand_id'] : 0;
                            $safeCompanyId = isset($item['company_id']) ? (int) $item['company_id'] : 0;
                            $safePkgId = mysqli_real_escape_string($finance_connect, $item['package_id']);
                            $safePkgGroupKey = mysqli_real_escape_string($finance_connect, isset($item['package_group_key']) ? (string) $item['package_group_key'] : '');
                            $safeDesc = mysqli_real_escape_string($finance_connect, $item['package_desc']);
                            $safePkgPrice = isset($item['package_price']) ? (float) $item['package_price'] : 0.00;
                            $safePackageQty = isset($item['packageQty']) ? (int) $item['packageQty'] : 1;
                            $safeProductQty = isset($item['productQty']) ? (int) $item['productQty'] : $safePackageQty;
                            $insertItemSql = "INSERT INTO " . STOCK_ORDER_REQ_ITEM . "
                                              (request_id, product_id, brand_id, company_id, package_id, package_group_key, package_desc, package_price, packageQty, productQty, create_by, create_date, create_time)
                                              VALUES
                                              ('" . (int) $dataID . "', '" . $safeProdId . "', '" . $safeBrandId . "', '" . $safeCompanyId . "', '$safePkgId', '$safePkgGroupKey', '$safeDesc', '" . number_format($safePkgPrice, 2, '.', '') . "', '$safePackageQty', '$safeProductQty', '" . USER_ID . "', CURDATE(), CURTIME())";
                            mysqli_query($finance_connect, $insertItemSql);
                        }
                    } else {
                        $existingItemIds = array();
                        $existingRst = mysqli_query($finance_connect, "SELECT id FROM " . STOCK_ORDER_REQ_ITEM . " WHERE request_id='" . (int) $dataID . "' AND status='A'");
                        if ($existingRst) {
                            while ($existingRow = mysqli_fetch_assoc($existingRst)) {
                                $existingItemIds[(int)$existingRow['id']] = true;
                            }
                        }

                        $postedItemIds = array();

                        foreach ($items as $item) {
                            $itemId = (int) $item['item_id'];
                            $safeProdId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                            $safeBrandId = isset($item['brand_id']) ? (int) $item['brand_id'] : 0;
                            $safeCompanyId = isset($item['company_id']) ? (int) $item['company_id'] : 0;
                            $safePkgId = mysqli_real_escape_string($finance_connect, $item['package_id']);
                            $safePkgGroupKey = mysqli_real_escape_string($finance_connect, isset($item['package_group_key']) ? (string) $item['package_group_key'] : '');
                            $safeDesc = mysqli_real_escape_string($finance_connect, $item['package_desc']);
                            $safePkgPrice = isset($item['package_price']) ? (float) $item['package_price'] : 0.00;
                            $safePackageQty = isset($item['packageQty']) ? (int) $item['packageQty'] : 1;
                            $safeProductQty = isset($item['productQty']) ? (int) $item['productQty'] : $safePackageQty;

                            if ($itemId > 0 && isset($existingItemIds[$itemId])) {
                                // UPDATE existing record by its specific ID
                                $updateItemSql = "UPDATE " . STOCK_ORDER_REQ_ITEM . "
                                                  SET product_id='" . $safeProdId . "',
                                                      brand_id='" . $safeBrandId . "',
                                                      company_id='" . $safeCompanyId . "',
                                                      package_id='" . $safePkgId . "',
                                                      package_group_key='" . $safePkgGroupKey . "',
                                                      package_desc='" . $safeDesc . "',
                                                      package_price='" . number_format($safePkgPrice, 2, '.', '') . "',
                                                      packageQty='" . $safePackageQty . "',
                                                      productQty='" . $safeProductQty . "',
                                                      status='A',
                                                      update_by='" . USER_ID . "',
                                                      update_date=CURDATE(),
                                                      update_time=CURTIME()
                                                  WHERE id='" . $itemId . "' AND request_id='" . (int) $dataID . "'";
                                mysqli_query($finance_connect, $updateItemSql);
                                $postedItemIds[] = $itemId;
                            } else {
                                // INSERT new record
                                $insertItemSql = "INSERT INTO " . STOCK_ORDER_REQ_ITEM . "
                                                  (request_id, product_id, brand_id, company_id, package_id, package_group_key, package_desc, package_price, packageQty, productQty, create_by, create_date, create_time)
                                                  VALUES
                                                  ('" . (int) $dataID . "', '" . $safeProdId . "', '" . $safeBrandId . "', '" . $safeCompanyId . "', '$safePkgId', '$safePkgGroupKey', '$safeDesc', '" . number_format($safePkgPrice, 2, '.', '') . "', '$safePackageQty', '$safeProductQty', '" . USER_ID . "', CURDATE(), CURTIME())";
                                mysqli_query($finance_connect, $insertItemSql);
                            }
                        }

                        // DELETE any records that existed in the DB but were NOT posted back from the UI
                        foreach ($existingItemIds as $id => $val) {
                            if (!in_array($id, $postedItemIds)) {
                                mysqli_query($finance_connect, "UPDATE " . STOCK_ORDER_REQ_ITEM . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $id . "' AND request_id='" . (int) $dataID . "'");
                            }
                        }
                    }

                    $token = sorEncodeToken($dataID);
                    $orderLink = $SITEURL . '/warehouse_stock_in_scan.php?t=' . urlencode($token);

                    $qrDir = ROOT . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'stock_order_request' . DIRECTORY_SEPARATOR;
                    if (!file_exists($qrDir)) {
                        mkdir($qrDir, 0777, true);
                    }
                    $qrFileName = 'sor_' . (int) $dataID . '.png';
                    $qrFsPath = $qrDir . $qrFileName;
                    $qrWebPath = '';
                    if (function_exists('imagecreate')) {
                        QRcode::png($orderLink, $qrFsPath, 'H', 6, 2);
                        if (file_exists($qrFsPath)) {
                            $qrWebPath = 'temp/stock_order_request/' . $qrFileName;
                        }
                    }
                    if ($qrWebPath === '') {
                        // Fallback when GD extension is unavailable.
                        $qrWebPath = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($orderLink);
                    }

                    $safeToken = mysqli_real_escape_string($finance_connect, $token);
                    $safeQr = mysqli_real_escape_string($finance_connect, $qrWebPath);
                    mysqli_query($finance_connect, "UPDATE " . STOCK_ORDER_REQ . " SET order_link_token='$safeToken', qr_image='$safeQr' WHERE id='" . (int) $dataID . "'");

                    // After successful save, stay on current page in edit mode.
                    $act = 'E';
                    $pageAction = getPageAction($act);
                    $pageActionTitle = $pageAction . ' ' . $pageTitle;
                    $actionBtnValue = 'updRecord';
                    $modalAct = ($action === 'addRecord') ? 'I' : 'E';

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

                    if ($pageAction === 'Add') {
                        $log['newval'] = implodeWithComma($auditNewValArr);
                        $log['act_msg'] = actMsgLog($dataID, $auditDataField, $auditNewValArr, array(), array(), $tblName, $pageAction, '');
                        audit_log($log);
                    } else if ($pageAction === 'Edit') {
                        if (!empty($auditDataField) && !empty($auditOldValArr) && !empty($auditChgValArr)) {
                            $log['oldval'] = implodeWithComma($auditOldValArr);
                            $log['changes'] = implodeWithComma($auditChgValArr);
                            $log['act_msg'] = actMsgLog($dataID, $auditDataField, array(), $auditOldValArr, $auditChgValArr, $tblName, $pageAction, '');
                            audit_log($log);
                        }
                    }

                    // Reload persisted header and item rows so add/edit success view exactly matches DB state.
                    $reloadRst = getData('*', "id='" . (int) $dataID . "'", 'LIMIT 1', STOCK_ORDER_REQ, $finance_connect);
                    if ($reloadRst && $reloadRst->num_rows > 0) {
                        $row = $reloadRst->fetch_assoc();
                    }

                    $itemRows = array();
                    $packageItemRows = array();
                    $reloadItemSql = "SELECT * FROM " . STOCK_ORDER_REQ_ITEM . " WHERE request_id='" . (int) $dataID . "' AND status='A' ORDER BY id ASC";
                    $reloadItemRst = mysqli_query($finance_connect, $reloadItemSql);
                    if ($reloadItemRst) {
                        while ($item = mysqli_fetch_assoc($reloadItemRst)) {
                            $item['product_id'] = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                            $reloadPkgId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                            if ((int) $item['product_id'] <= 0 && isset($packageProductMap[$reloadPkgId]) && count($packageProductMap[$reloadPkgId]) === 1) {
                                $item['product_id'] = (int) $packageProductMap[$reloadPkgId][0];
                            }
                            $item['package_desc'] = isset($item['package_desc']) ? $item['package_desc'] : '';
                            if ($item['package_desc'] === '' && isset($packages)) {
                                foreach ($packages as $pkgData) {
                                    if ((int) $pkgData['id'] === $reloadPkgId) {
                                        $item['package_desc'] = $pkgData['item_description'];
                                        break;
                                    }
                                }
                            }
                            $legacyQty = isset($item['qty']) ? (int) $item['qty'] : 0;
                            $itemPackageQty = isset($item['packageQty']) ? (int) $item['packageQty'] : $legacyQty;
                            $itemProductQty = isset($item['productQty']) ? (int) $item['productQty'] : $itemPackageQty;
                            if ($itemPackageQty <= 0) $itemPackageQty = 1;
                            if ($itemProductQty <= 0) $itemProductQty = $itemPackageQty;
                            $item['packageQty'] = $itemPackageQty;
                            $item['productQty'] = $itemProductQty;
                            $item['package_price'] = isset($item['package_price']) ? (float) $item['package_price'] : 0.00;
                            $item['package_group_key'] = isset($item['package_group_key']) ? trim((string) $item['package_group_key']) : '';
                            if ($item['package_price'] <= 0 && $reloadPkgId > 0 && isset($packageMap[$reloadPkgId])) {
                                $item['package_price'] = ((float) $packageMap[$reloadPkgId]) * $itemPackageQty;
                            }
                            $itemRows[] = $item;
                        }
                    }

                    $packageItemRows = $itemRows;

                    $showQrPanel = true;
                } else {
                    if ($modalAct !== 'NC') {
                        $err = 'Failed to save stock order request.';
                    }
                }
            }
        }
    }
}

function sorEcho($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sorPickDominantIdByCounts($counts, $firstOrder)
{
    if (!is_array($counts) || empty($counts)) {
        return 0;
    }

    $bestId = 0;
    $bestCount = -1;
    $bestOrder = PHP_INT_MAX;

    foreach ($counts as $id => $count) {
        $id = (int) $id;
        $count = (int) $count;
        if ($id <= 0 || $count <= 0) {
            continue;
        }

        $order = isset($firstOrder[$id]) ? (int) $firstOrder[$id] : PHP_INT_MAX;
        if ($count > $bestCount || ($count === $bestCount && $order < $bestOrder)) {
            $bestId = $id;
            $bestCount = $count;
            $bestOrder = $order;
        }
    }

    return $bestId > 0 ? $bestId : 0;
}

function sorResolveSqlAccountFolderFromCompany($companyId, $companySqlAccountFolderMap)
{
    $companyId = (int) $companyId;
    if ($companyId > 0 && is_array($companySqlAccountFolderMap) && isset($companySqlAccountFolderMap[$companyId])) {
        $folder = trim((string) $companySqlAccountFolderMap[$companyId]);
        if ($folder !== '') {
            return $folder;
        }
    }

    return 'sqlaccount';
}

function sorNormalizeAttachmentRelativePath($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^https?://[^/]+/#i', '', $path);
    $path = preg_replace('#^/?images_server/#i', '', $path);
    $path = ltrim((string) $path, '/');

    if (stripos($path, 'attachment/') !== 0) {
        if (strpos($path, 'attachment/') !== false) {
            $pos = strpos($path, 'attachment/');
            $path = substr($path, $pos);
        }
    }

    if (strpos($path, 'attachment/') === false) {
        return '';
    }

    return $path;
}

function sorBuildAuditItemSnapshot($rows, $packageNameMap = array(), $productNameMap = array())
{
    if (!is_array($rows) || empty($rows)) {
        return '';
    }

    $grouped = array();
    foreach ($rows as $idx => $item) {
        if (!is_array($item)) {
            continue;
        }

        $prodId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $pkgId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
        $pkgQty = isset($item['packageQty']) ? (int) $item['packageQty'] : 0;
        $prodQty = isset($item['productQty']) ? (int) $item['productQty'] : 0;
        $pkgPrice = isset($item['package_price']) ? number_format((float) $item['package_price'], 2, '.', '') : '0.00';
        $pkgName = isset($packageNameMap[$pkgId]) ? trim((string) $packageNameMap[$pkgId]) : '';
        $prodName = isset($productNameMap[$prodId]) ? trim((string) $productNameMap[$prodId]) : '';
        $groupKey = isset($item['package_group_key']) ? trim((string) $item['package_group_key']) : '';

        if ($groupKey === '') {
            $groupKey = 'pkg_' . $pkgId . '_' . ($idx + 1);
        }

        if ($pkgName === '') {
            $pkgName = $pkgId > 0 ? ('Package #' . $pkgId) : 'Package';
        }
        if ($prodName === '') {
            $prodName = $prodId > 0 ? ('Product #' . $prodId) : 'Product';
        }

        if (!isset($grouped[$groupKey])) {
            $grouped[$groupKey] = array(
                'package_name' => $pkgName,
                'package_qty' => $pkgQty,
                'price' => $pkgPrice,
                'products' => array(),
            );
        }

        $grouped[$groupKey]['products'][] = array(
            'product_name' => $prodName,
            'product_qty' => $prodQty,
        );
    }

    $snapshotRows = array();
    foreach ($grouped as $group) {
        $productParts = array();
        if (!empty($group['products']) && is_array($group['products'])) {
            foreach ($group['products'] as $pIdx => $p) {
                $labelSuffix = $pIdx === 0 ? '' : (string) ($pIdx + 1);
                $productParts[] = 'Product' . $labelSuffix . ' Name: ' . $p['product_name'] . '; Product' . $labelSuffix . ' Qty: ' . (int) $p['product_qty'];
            }
        }

        $snapshotRows[] = 'Package Name: ' . $group['package_name']
            . '; Package Qty: ' . (int) $group['package_qty']
            . '; ' . implode(', ', $productParts)
            . '; Price: ' . $group['price'];
    }

    sort($snapshotRows);
    return implode('|', $snapshotRows);
}

function sorCanonicalAuditValue($field, $value)
{
    $field = trim((string) $field);
    $normalized = normalizeAuditLogValue($value);

    if ($field === 'warehouse_id' || $field === 'company_id' || $field === 'brand_id' || $field === 'courier_id') {
        return (string) ((int) $normalized);
    }

    if ($field === 'total_price') {
        return number_format((float) $normalized, 2, '.', '');
    }

    if ($field === 'invoice_no') {
        return ltrim(trim((string) $normalized), "'");
    }

    if ($field === 'item_snapshot') {
        $raw = trim((string) $normalized);
        if ($raw === '' || $raw === 'Empty Value') {
            return '';
        }

        $rows = explode('|', $raw);
        $canonicalRows = array();
        foreach ($rows as $row) {
            $row = trim((string) $row);
            if ($row === '') {
                continue;
            }
            $canonicalRows[] = preg_replace('/\s+/', ' ', $row);
        }

        sort($canonicalRows);
        return implode('|', $canonicalRows);
    }

    return trim((string) $normalized);
}

function sorQrSrc($path, $siteUrl)
{
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return rtrim((string) $siteUrl, '/') . '/' . ltrim($path, '/');
}

function sorAttachmentUrl($relativePath, $siteUrl)
{
    $relativePath = sorNormalizeAttachmentRelativePath($relativePath);
    if ($relativePath === '') {
        return '';
    }

    $newAbsPath = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    if (is_file($newAbsPath)) {
        return rtrim((string) $siteUrl, '/') . '/' . ltrim($relativePath, '/');
    }

    return rtrim((string) $siteUrl, '/') . '/' . ltrim((string) img_server, '/') . ltrim($relativePath, '/');
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .sor-group-product-row .pkg-main-fields,
        .sor-group-product-row .desc-main-field,
        .sor-group-product-row .total-main-field {
            display: none;
        }

        .sor-group-package-row .sor-item-prod-name {
            background: #f3f4f6;
        }

        .sor-group-package-row td:nth-child(3) .autocomplete {
            display: none;
        }

        #sorItemBody tr.sor-group-package-row {
            border-top: 2px solid #9ca3af;
            background: #f8fafc;
        }

        #sorItemBody tr.sor-group-product-row {
            background: #ffffff;
        }

        #sorItemBody tr.sor-group-package-row td,
        #sorItemBody tr.sor-group-product-row td {
            border-color: #6b7280;
        }

        .sor-package-qty-note {
            font-size: 0.88rem;
            color: #4b5563;
            margin-top: 4px;
            font-weight: 600;
        }

        .sor-package-qty-note input {
            width: 80px;
            display: inline-block;
            margin-left: 6px;
            padding: 2px 6px;
            height: 28px;
        }

        .sor-field-error {
            color: #ff0000;
            margin-top: 4px;
            font-size: 0.95rem;
            display: none;
        }

        .sor-invalid {
            border-color: #ff0000 !important;
        }

        .sor-item-panel {
            border: 0;
            border-radius: 0;
            padding: 0;
            margin-bottom: 16px;
        }

        .action_menu_btn {
            border: 1px solid #dbdbdb;
            border-radius: 5px;
            background: #fff;
            width: 38px;
            height: 38px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="pre-load-center"><div class="preloader"></div></div>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-11 col-md-10 formWidthAdjust">
                <form id="sorForm" method="post" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="id" value="<?= sorEcho($dataID) ?>">
                    <input type="hidden" name="act" value="<?= sorEcho($act) ?>">

                    <div class="form-group mb-4">
                        <h2><?= $pageActionTitle ?></h2>
                    </div>

                    <?php if (isset($err)) { ?>
                        <div id="err_msg" class="mb-3"><span><?= sorEcho($err) ?></span></div>
                    <?php } ?>

                    <?php if ($showQrPanel) { ?>
                        <script>
                            if ('scrollRestoration' in history) {
                                history.scrollRestoration = 'manual';
                            }
                            $(document).ready(function() {
                                setTimeout(function() {
                                    $('html, body').animate({
                                        scrollTop: $("#qr_success_panel").offset().top - 120
                                    }, 600); // Smoothly glides up to the panel
                                }, 200); 
                            });
                        </script>
                        
                        <div id="qr_success_panel" class="alert alert-success d-flex justify-content-between align-items-center mb-3">
                            <span>Stock Order saved. Redirecting to table in <strong><span id="countdownSec">15</span>s</strong>.</span>
                            <button type="button" class="btn btn-sm btn-rounded btn-primary" id="goNowBtn">Back</button>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3">QR Generated Successfully</h5>
                                <div class="row">
                                    <div class="col-md-4 text-center mb-3">
                                        <img class="img-fluid border rounded p-2 bg-white" style="max-width:220px;" src="<?= sorEcho(sorQrSrc($qrWebPath, $SITEURL)) ?>" alt="QR Code">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label form_lbl">Encrypted Order Link</label>
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control" id="sorOrderLink" value="<?= sorEcho($orderLink) ?>" readonly>
                                            <button type="button" class="btn btn-sm btn-rounded btn-primary" id="copyOrderLinkBtn">Copy Link</button>
                                        </div>
                                        <a class="btn btn-sm btn-rounded btn-primary me-2" href="<?= sorEcho(sorQrSrc($qrWebPath, $SITEURL)) ?>" download>Download QR</a>
                                        <a class="btn btn-sm btn-rounded btn-primary" id="actionBtn" href="<?= sorEcho($orderLink) ?>" target="_blank">Open Order Link</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_warehouse">Warehouse<span class="requireRed">*</span></label>
                            <div class="autocomplete">
                                <input class="form-control" type="text" id="sor_warehouse_name" name="sor_warehouse_name" value="<?= sorEcho(isset($_POST['sor_warehouse_name']) ? $_POST['sor_warehouse_name'] : (isset($warehouseNameMap[(int) (isset($row['warehouse_id']) ? $row['warehouse_id'] : 0)]) ? $warehouseNameMap[(int) $row['warehouse_id']] : '')) ?>" placeholder="Select Warehouse" <?= ($act == '') ? 'readonly' : '' ?>>
                                <input type="hidden" id="sor_warehouse" name="sor_warehouse" value="<?= sorEcho(isset($_POST['sor_warehouse']) ? $_POST['sor_warehouse'] : (isset($row['warehouse_id']) ? $row['warehouse_id'] : '')) ?>">
                            </div>
                            <div class="sor-field-error" id="sor_warehouse_err"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_invoice_no">Invoice<span class="requireRed">*</span></label>
                            <textarea class="form-control" id="sor_invoice_no" name="sor_invoice_no" rows="1" <?= ($act == '') ? 'readonly' : '' ?>><?= sorEcho(isset($_POST['sor_invoice_no']) ? $_POST['sor_invoice_no'] : (isset($row['invoice_no']) ? $row['invoice_no'] : '')) ?></textarea>
                            <div class="sor-field-error" id="sor_invoice_no_err"<?= isset($invoice_no_err) ? ' style="display:block;"' : '' ?>><?= isset($invoice_no_err) ? sorEcho($invoice_no_err) : '' ?></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_invoice_date">Invoices Date<span class="requireRed">*</span></label>
                            <input class="form-control" type="date" id="sor_invoice_date" name="sor_invoice_date" value="<?= sorEcho(isset($_POST['sor_invoice_date']) ? $_POST['sor_invoice_date'] : (($act === 'I') ? '' : (isset($row['invoice_date']) ? $row['invoice_date'] : ''))) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                            <div class="sor-field-error" id="sor_invoice_date_err"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_request_date">Request Date<span class="requireRed">*</span></label>
                            <input class="form-control" type="date" id="sor_request_date" name="sor_request_date" value="<?= sorEcho(isset($_POST['sor_request_date']) ? $_POST['sor_request_date'] : (($act === 'I') ? '' : (isset($row['request_date']) ? $row['request_date'] : ''))) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                            <div class="sor-field-error" id="sor_request_date_err"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_courier">Courier</label>
                            <div class="autocomplete">
                                <input class="form-control" type="text" id="sor_courier_name" name="sor_courier_name" value="<?= sorEcho(isset($_POST['sor_courier_name']) ? $_POST['sor_courier_name'] : (($act === 'I') ? '' : (isset($courierNameMap[(string) (isset($row['courier_id']) ? $row['courier_id'] : '')]) ? $courierNameMap[(string) $row['courier_id']] : ''))) ?>" placeholder="Select Courier" <?= ($act == '') ? 'readonly' : '' ?> autocomplete="off">
                                <input type="hidden" id="sor_courier" name="sor_courier" value="<?= sorEcho(isset($_POST['sor_courier']) ? $_POST['sor_courier'] : (($act === 'I') ? '' : (isset($row['courier_id']) ? $row['courier_id'] : ''))) ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_tracking_no">Tracking Number</label>
                            <input class="form-control" type="text" id="sor_tracking_no" name="sor_tracking_no" value="<?= sorEcho(isset($_POST['sor_tracking_no']) ? $_POST['sor_tracking_no'] : (($act === 'I') ? '' : (isset($row['tracking_no']) ? $row['tracking_no'] : ''))) ?>" <?= ($act == '') ? 'readonly' : '' ?> autocomplete="off">
                        </div>
                    </div>

                    <div class="sor-item-panel">
                    <div class="mb-3">
                        <label class="form-label form_lbl">Package Items<span class="requireRed">*</span></label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="sorItemTable">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Package Name</th>
                                        <th>Product Name</th>
                                        <th>Item Description</th>
                                        <th width="160">Quantity</th>
                                        <th width="140">Total Price</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sorItemBody">
                                <?php
                                $groupedPackageRows = array();
                                $groupOrder = array();

                                foreach ($packageItemRows as $k => $item) {
                                    $pkgId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                                    if ($pkgId <= 0) continue;

                                    $storedGroupKey = isset($item['package_group_key']) ? trim((string) $item['package_group_key']) : '';
                                    $fallbackGroupKey = 'pkg_' . $pkgId . '_' . ($k + 1);
                                    $rawGroupKey = ($storedGroupKey !== '') ? $storedGroupKey : $fallbackGroupKey;
                                    $groupKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $rawGroupKey);
                                    if ($groupKey === '') {
                                        $groupKey = $fallbackGroupKey;
                                    }

                                    if (!isset($groupedPackageRows[$groupKey])) {
                                        $pkgName = isset($packageNameMap[$pkgId]) ? (string) $packageNameMap[$pkgId] : '';
                                        $desc = isset($item['package_desc']) ? (string) $item['package_desc'] : '';
                                        $packageQty = isset($item['packageQty']) ? (int) $item['packageQty'] : (isset($item['qty']) ? (int) $item['qty'] : 1);
                                        if ($packageQty <= 0) $packageQty = 1;
                                        $pkgPrice = isset($item['package_price']) ? (float) $item['package_price'] : 0.00;
                                        if ($pkgPrice <= 0 && $pkgId > 0 && isset($packageMap[$pkgId])) {
                                            $pkgPrice = ((float) $packageMap[$pkgId]) * $packageQty;
                                        }

                                        $groupedPackageRows[$groupKey] = array(
                                            'group_key' => $groupKey,
                                            'package_id' => $pkgId,
                                            'package_name' => $pkgName,
                                            'package_desc' => $desc,
                                            'package_qty' => $packageQty,
                                            'package_price' => (float) $pkgPrice,
                                            'products' => array(),
                                        );
                                        $groupOrder[] = $groupKey;
                                    }

                                    $prodId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                                    $prodName = isset($productNameMap[$prodId]) ? (string) $productNameMap[$prodId] : '';
                                    $productQty = isset($item['productQty']) ? (int) $item['productQty'] : (isset($item['qty']) ? (int) $item['qty'] : 1);
                                    if ($productQty <= 0) {
                                        $productQty = (int) $groupedPackageRows[$groupKey]['package_qty'];
                                    }
                                    $baseQty = (int) max(1, round($productQty / max(1, (int) $groupedPackageRows[$groupKey]['package_qty'])));

                                    $groupedPackageRows[$groupKey]['products'][] = array(
                                        'product_id' => $prodId,
                                        'product_name' => $prodName,
                                        'product_qty' => $productQty,
                                        'base_qty' => $baseQty,
                                    );
                                }

                                if (count($groupOrder) === 0) {
                                    $groupOrder[] = 'pkg_group_init_1';
                                    $groupedPackageRows['pkg_group_init_1'] = array(
                                        'group_key' => 'pkg_group_init_1',
                                        'package_id' => 0,
                                        'package_name' => '',
                                        'package_desc' => '',
                                        'package_qty' => 1,
                                        'package_price' => 0.00,
                                        'products' => array(),
                                    );
                                }

                                $rowKey = 1;
                                $isFirstRenderedRow = true;
                                foreach ($groupOrder as $gk) {
                                    $g = $groupedPackageRows[$gk];
                                    $pkgId = (int) $g['package_id'];
                                    $pkgName = (string) $g['package_name'];
                                    $desc = (string) $g['package_desc'];
                                    $packageQty = (int) $g['package_qty'];
                                    $groupPrice = (float) $g['package_price'];
                                ?>
                                    <tr data-row-key="<?= (int) $rowKey ?>" data-package-group="<?= sorEcho($gk) ?>" data-row-role="package">
                                        <td class="row-no"></td>
                                        <td class="cell-package">
                                            <div class="pkg-main-fields">
                                                <div class="autocomplete">
                                                    <input class="form-control sor-item-pkg-name" type="text" id="sor_item_pkg_name_<?= (int) $rowKey ?>" name="sor_item_pkg_name[]" value="<?= sorEcho($pkgName) ?>" placeholder="Type Package" <?= ($act == '') ? 'readonly' : '' ?>>
                                                    <input type="hidden" class="sor-item-pkg-id" id="sor_item_pkg_id_<?= (int) $rowKey ?>" name="sor_item_pkg_id[]" value="<?= (int) $pkgId ?>">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="hidden" class="sor-item-prod-name" name="sor_item_prod_name[]" value="">
                                            <input type="hidden" class="sor-item-prod-id" name="sor_item_prod_id[]" value="">
                                        </td>
                                        <td class="cell-desc">
                                            <div class="desc-main-field">
                                                <input class="form-control sor-item-desc" type="text" name="sor_item_desc[]" value="<?= sorEcho($desc) ?>" readonly>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="qty-main-field">
                                                <input class="form-control sor-item-qty" type="number" min="1" name="sor_item_product_qty[]" value="<?= sorEcho($packageQty) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                                                <input class="sor-item-id" type="hidden" name="sor_item_id[]" value="">
                                                <input class="sor-item-row-role" type="hidden" name="sor_item_row_role[]" value="package">
                                                <input class="sor-item-package-qty" type="hidden" name="sor_item_package_qty[]" value="<?= sorEcho($packageQty) ?>">
                                                <input class="sor-item-base-qty" type="hidden" name="sor_item_base_qty[]" value="1">
                                                <input class="sor-item-group-key" type="hidden" name="sor_item_group_key[]" value="<?= sorEcho($gk) ?>">
                                                <input class="sor-item-package-price" type="hidden" name="sor_item_package_price[]" value="<?= sorEcho(number_format($groupPrice, 2, '.', '')) ?>">
                                            </div>
                                        </td>
                                        <td class="cell-total">
                                            <div class="total-main-field">
                                                <input class="form-control sor-item-total" type="text" value="<?= sorEcho(number_format($groupPrice, 2, '.', '')) ?>" readonly>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($act != '') { ?>
                                                <?php if ($isFirstRenderedRow) { ?>
                                                    <button type="button" class="mt-1 add-item-row-btn" id="action_menu_btn"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                <?php } else { ?>
                                                    <button type="button" class="mt-1 remove-package-btn" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>
                                                <?php } ?>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php
                                    $isFirstRenderedRow = false;
                                    $rowKey++;

                                    $productNo = 1;
                                    foreach ($g['products'] as $prodIdx => $prod) {
                                        $prodId = (int) $prod['product_id'];
                                        $prodName = (string) $prod['product_name'];
                                        $productQty = (int) $prod['product_qty'];
                                        $baseQty = (int) $prod['base_qty'];
                                ?>
                                    <tr data-row-key="<?= (int) $rowKey ?>" data-package-group="<?= sorEcho($gk) ?>" data-row-role="product">
                                        <td class="row-no"><?= (int) $productNo++ ?></td>
                                        <td class="cell-package">
                                            <div class="pkg-main-fields">
                                                <div class="autocomplete">
                                                    <input class="form-control sor-item-pkg-name" type="text" id="sor_item_pkg_name_<?= (int) $rowKey ?>" name="sor_item_pkg_name[]" value="<?= sorEcho($pkgName) ?>" placeholder="Type Package" <?= ($act == '') ? 'readonly' : '' ?> >
                                                    <input type="hidden" class="sor-item-pkg-id" id="sor_item_pkg_id_<?= (int) $rowKey ?>" name="sor_item_pkg_id[]" value="<?= (int) $pkgId ?>">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="autocomplete">
                                                <input class="form-control sor-item-prod-name" type="text" id="sor_item_prod_name_<?= (int) $rowKey ?>" name="sor_item_prod_name[]" value="<?= sorEcho($prodName) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                                                <input type="hidden" class="sor-item-prod-id" id="sor_item_prod_id_<?= (int) $rowKey ?>" name="sor_item_prod_id[]" value="<?= (int) $prodId ?>">
                                            </div>
                                        </td>
                                        <td class="cell-desc">
                                            <div class="desc-main-field">
                                                <input class="form-control sor-item-desc" type="text" name="sor_item_desc[]" value="<?= sorEcho($desc) ?>" readonly>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="qty-main-field">
                                                <input class="form-control sor-item-qty" type="number" min="1" name="sor_item_product_qty[]" value="<?= sorEcho($productQty) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                                                <input class="sor-item-id" type="hidden" name="sor_item_id[]" value="<?= sorEcho(isset($item['id']) ? (int) $item['id'] : '') ?>">
                                                <input class="sor-item-row-role" type="hidden" name="sor_item_row_role[]" value="product">
                                                <input class="sor-item-package-qty" type="hidden" name="sor_item_package_qty[]" value="<?= sorEcho($packageQty) ?>">
                                                <input class="sor-item-base-qty" type="hidden" name="sor_item_base_qty[]" value="<?= sorEcho($baseQty) ?>">
                                                <input class="sor-item-group-key" type="hidden" name="sor_item_group_key[]" value="<?= sorEcho($gk) ?>">
                                                <input class="sor-item-package-price" type="hidden" name="sor_item_package_price[]" value="<?= sorEcho(number_format($groupPrice, 2, '.', '')) ?>">
                                            </div>
                                        </td>
                                        <td class="cell-total">
                                            <div class="total-main-field">
                                                <input class="form-control sor-item-total" type="text" value="" readonly>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($act != '') { ?>
                                                <button type="button" class="mt-1 remove-product-btn" id="action_menu_btn"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php
                                        $rowKey++;
                                    }
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="sor-field-error" id="sor_items_err"></div>
                    </div>

                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_total_price">Total Price</label>
                            <input class="form-control" type="text" id="sor_total_price" name="sor_total_price" value="<?= sorEcho(isset($row['total_price']) ? $row['total_price'] : '0.00') ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_attachment">Attachment</label>
                            <input class="form-control" type="file" id="sor_attachment" name="sor_attachment" <?= ($act == '') ? 'disabled' : '' ?>>
                            <input type="hidden" name="existing_attachment" value="<?= sorEcho(sorNormalizeAttachmentRelativePath(isset($row['attachment']) ? $row['attachment'] : '')) ?>">
                            <?php if (isset($row['attachment']) && $row['attachment'] !== '') { ?>
                                <div class="mt-2">
                                    <a href="<?= sorAttachmentUrl(sorNormalizeAttachmentRelativePath($row['attachment']), $SITEURL) ?>" target="_blank">View Current Attachment</a>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_remark">Remark</label>
                            <textarea class="form-control" id="sor_remark" name="sor_remark" rows="3" <?= ($act == '') ? 'readonly' : '' ?> autocomplete="off"><?= sorEcho(isset($_POST['sor_remark']) ? $_POST['sor_remark'] : (($act === 'I') ? '' : (isset($row['remark']) ? $row['remark'] : ''))) ?></textarea>
                        </div>
                    </div>

                    <div class="form-group mt-4 d-flex justify-content-center flex-md-row flex-column">
                        <?php if ($act) { ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="addBtn" value="<?= $actionBtnValue ?>"><?= $pageActionTitle ?></button>
                        <?php } ?>
                        <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.stockOrderReqConfig = {
            page: <?= json_encode((string) $pageTitle) ?>,
            siteURL: <?= json_encode((string) $SITEURL) ?>,
            action: <?= json_encode((string) (isset($act) ? $act : '')) ?>,
            redirectPage: <?= json_encode((string) $redirect_page) ?>,
            showQrPanel: <?= $showQrPanel ? 'true' : 'false' ?>,
            modalAct: <?= json_encode((string) $modalAct) ?>,
            warehouses: <?= json_encode(array_values($warehouses)) ?>,
            couriers: <?= json_encode(array_values($couriers)) ?>,
            products: <?= json_encode(array_values($products)) ?>,
            packages: <?= json_encode(array_values($packages)) ?>
        };
    </script>
    <script src="../js/stock_order_req.js"></script>
</body>
</html>