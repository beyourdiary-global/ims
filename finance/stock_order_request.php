<?php
$pageTitle = "Stock Order Request";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/header/phpqrcode/qrlib.php';

sorEnsureSchema($finance_connect);

$permissionPage = 'Stock Order Request';
$pinAccess = checkPin($connect, $permissionPage);
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $pinAccess = checkPin($connect, 'Stock List');
}

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
$productRst = mysqli_query($connect, "SELECT id,name FROM " . PROD . " WHERE status='A' ORDER BY name ASC");
if ($productRst) {
    while ($productRow = $productRst->fetch_assoc()) {
        $prodId = (int) $productRow['id'];
        $prodName = (string) $productRow['name'];
        $products[] = array('id' => $prodId, 'name' => $prodName);
        $productNameMap[$prodId] = $prodName;
        $productNameToId[strtolower(trim($prodName))] = $prodId;
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
    $crId = (int) $cr['id'];
    $crName = (string) $cr['name'];
    $courierNameMap[$crId] = $crName;
    $courierNameToId[strtolower(trim($crName))] = $crId;
}

$row = array();
$itemRows = array();

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
                $itemRows[] = $item;
            }
        }
    } else {
        $act = 'F';
    }
}

if ($act == 'D' && $dataID) {
    mysqli_query($finance_connect, "UPDATE " . STOCK_ORDER_REQ . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . (int) $dataID . "'");
    mysqli_query($finance_connect, "UPDATE " . STOCK_ORDER_REQ_ITEM . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE request_id='" . (int) $dataID . "'");
    echo "<script>location.href='$redirect_page';</script>";
    exit;
}

$showQrPanel = false;
$orderLink = '';
$qrWebPath = '';

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
        $prodNameArr = isset($_POST['sor_item_prod_name']) ? postSpaceFilter('sor_item_prod_name') : array();
        $pkgIdArr = isset($_POST['sor_item_pkg_id']) ? postSpaceFilter('sor_item_pkg_id') : array();
        $pkgNameArr = isset($_POST['sor_item_pkg_name']) ? postSpaceFilter('sor_item_pkg_name') : array();
        $pkgDescArr = isset($_POST['sor_item_desc']) ? postSpaceFilter('sor_item_desc') : array();
        $pkgQtyArr = isset($_POST['sor_item_qty']) ? postSpaceFilter('sor_item_qty') : array();

        if (!is_array($prodIdArr)) $prodIdArr = array();
        if (!is_array($prodNameArr)) $prodNameArr = array();
        if (!is_array($pkgIdArr)) $pkgIdArr = array();
        if (!is_array($pkgNameArr)) $pkgNameArr = array();
        if (!is_array($pkgDescArr)) $pkgDescArr = array();
        if (!is_array($pkgQtyArr)) $pkgQtyArr = array();

        if ((int) $sor_warehouse <= 0 && trim((string) $sor_warehouse_name) !== '') {
            $whKey = strtolower(trim((string) $sor_warehouse_name));
            if (isset($warehouseNameToId[$whKey])) {
                $sor_warehouse = (string) $warehouseNameToId[$whKey];
            }
        }

        if ((int) $sor_courier <= 0 && trim((string) $sor_courier_name) !== '') {
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
        $resolvedBrandIds = array();
        $resolvedCompanyIds = array();
        $maxCount = max(count($prodIdArr), count($prodNameArr), count($pkgIdArr), count($pkgNameArr), count($pkgDescArr), count($pkgQtyArr));
        for ($i = 0; $i < $maxCount; $i++) {
            $prodId = isset($prodIdArr[$i]) ? (int) $prodIdArr[$i] : 0;
            $prodName = isset($prodNameArr[$i]) ? trim((string) $prodNameArr[$i]) : '';
            $pkgId = isset($pkgIdArr[$i]) ? (int) $pkgIdArr[$i] : 0;
            $pkgName = isset($pkgNameArr[$i]) ? trim((string) $pkgNameArr[$i]) : '';
            $pkgDesc = isset($pkgDescArr[$i]) ? trim((string) $pkgDescArr[$i]) : '';
            $qty = isset($pkgQtyArr[$i]) ? (int) $pkgQtyArr[$i] : 0;

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

            $hasAnyValue = ($prodName !== '' || $pkgName !== '' || $qty > 0 || $prodId > 0 || $pkgId > 0 || $pkgDesc !== '');
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

            if ($prodId <= 0 || $pkgId <= 0 || $qty <= 0) {
                continue;
            }

            $allowedProducts = isset($packageProductMap[$pkgId]) ? $packageProductMap[$pkgId] : array();
            if (empty($allowedProducts) || !in_array($prodId, $allowedProducts, true)) {
                $mismatchItems[] = $pkgName !== '' ? $pkgName : (isset($packageNameMap[$pkgId]) ? $packageNameMap[$pkgId] : '');
                continue;
            }

            $pkgPrice = isset($packageMap[$pkgId]) ? (float) $packageMap[$pkgId] : 0.00;
            $pkgBrandId = isset($packageBrandMap[$pkgId]) ? (int) $packageBrandMap[$pkgId] : 0;
            $pkgCompanyId = ($pkgBrandId > 0 && isset($brandCompanyMap[$pkgBrandId])) ? (int) $brandCompanyMap[$pkgBrandId] : 0;
            if ($pkgDesc === '' && isset($packageDescMap[$pkgId])) {
                $pkgDesc = (string) $packageDescMap[$pkgId];
            }
            $computedTotal += ($pkgPrice * $qty);

            if ($pkgBrandId > 0) {
                $resolvedBrandIds[$pkgBrandId] = true;
            }
            if ($pkgCompanyId > 0) {
                $resolvedCompanyIds[$pkgCompanyId] = true;
            }

            $items[] = array(
                'product_id' => $prodId,
                'brand_id' => $pkgBrandId,
                'company_id' => $pkgCompanyId,
                'package_id' => $pkgId,
                'package_desc' => $pkgDesc,
                'qty' => $qty,
            );
        }

        $requestBrandId = count($resolvedBrandIds) === 1 ? (int) array_key_first($resolvedBrandIds) : 0;
        $requestCompanyId = count($resolvedCompanyIds) === 1 ? (int) array_key_first($resolvedCompanyIds) : 0;

        if (!$sor_warehouse) {
            $err = 'Warehouse cannot be empty.';
        } else if (!isset($warehouseNameMap[(int) $sor_warehouse])) {
            $err = 'Please select a valid warehouse from the list.';
        } else if (!$sor_invoice_no) {
            $err = 'Invoice cannot be empty.';
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
        } else if ($sor_courier !== '' && (int) $sor_courier > 0 && !isset($courierNameMap[(int) $sor_courier])) {
            $err = 'Please select a valid courier from the list.';
        } else if (count($items) === 0) {
            $err = 'Please add at least one product and package item with quantity.';
        } else {
            $existingAttachment = postSpaceFilter('existing_attachment');
            $sor_attachment = $existingAttachment;

            if (isset($_FILES['sor_attachment']) && $_FILES['sor_attachment']['error'] === UPLOAD_ERR_OK) {
                $requestNoForPath = sorGenerateRequestNo($finance_connect);
                $targetRelativeDir = 'attachment/' . date('Y') . '/' . date('m') . '/beyourdiary/' . $requestNoForPath . '/';
                $targetFsDir = ROOT . img_server . $targetRelativeDir;

                if (!file_exists($targetFsDir)) {
                    mkdir($targetFsDir, 0777, true);
                }

                $original = $_FILES['sor_attachment']['name'];
                $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                $allowed = array('pdf', 'png', 'jpg', 'jpeg', 'zip');

                if (!in_array($ext, $allowed, true)) {
                    $err = 'Attachment format not supported. Allowed: pdf, png, jpg, jpeg, zip.';
                } else {
                    $newName = 'sor_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    $targetFile = $targetFsDir . $newName;
                    if (!move_uploaded_file($_FILES['sor_attachment']['tmp_name'], $targetFile)) {
                        $err = 'Failed to upload attachment.';
                    } else {
                        $sor_attachment = $targetRelativeDir . $newName;
                    }
                }
            }

            if (!isset($err)) {
                $safeWarehouse = mysqli_real_escape_string($finance_connect, $sor_warehouse);
                $safeInvoiceNo = mysqli_real_escape_string($finance_connect, $sor_invoice_no);
                $safeInvoiceDate = mysqli_real_escape_string($finance_connect, $sor_invoice_date);
                $safeRequestDate = mysqli_real_escape_string($finance_connect, $sor_request_date);
                $safeCourier = mysqli_real_escape_string($finance_connect, $sor_courier);
                $safeTrackingNo = mysqli_real_escape_string($finance_connect, $sor_tracking_no);
                $safeTotalPrice = number_format((float) $computedTotal, 2, '.', '');
                $safeAttachment = mysqli_real_escape_string($finance_connect, (string) $sor_attachment);
                $safeRemark = mysqli_real_escape_string($finance_connect, $sor_remark);

                if ($action === 'addRecord') {
                    $query = "INSERT INTO " . STOCK_ORDER_REQ . "
                                                                (warehouse_id, company_id, brand_id, invoice_no, invoice_date, request_date, courier_id, tracking_no, total_price, attachment, remark, create_by, create_date, create_time)
                              VALUES
                                                                ('$safeWarehouse', '" . $requestCompanyId . "', '" . $requestBrandId . "', '$safeInvoiceNo', '$safeInvoiceDate', '$safeRequestDate', '" . ($safeCourier === '' ? 0 : $safeCourier) . "', '$safeTrackingNo', '$safeTotalPrice', '$safeAttachment', '$safeRemark', '" . USER_ID . "', CURDATE(), CURTIME())";
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
                                  courier_id = '" . ($safeCourier === '' ? 0 : $safeCourier) . "',
                                  tracking_no = '$safeTrackingNo',
                                  total_price = '$safeTotalPrice',
                                  attachment = '$safeAttachment',
                                  remark = '$safeRemark',
                                  update_by = '" . USER_ID . "',
                                  update_date = CURDATE(),
                                  update_time = CURTIME()
                              WHERE id = '" . (int) $dataID . "'";
                    $returnData = mysqli_query($finance_connect, $query);
                }

                if ($returnData) {
                    mysqli_query($finance_connect, "UPDATE " . STOCK_ORDER_REQ_ITEM . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE request_id='" . (int) $dataID . "' AND status='A'");

                    foreach ($items as $item) {
                        $safeProdId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                        $safeBrandId = isset($item['brand_id']) ? (int) $item['brand_id'] : 0;
                        $safeCompanyId = isset($item['company_id']) ? (int) $item['company_id'] : 0;
                        $safePkgId = mysqli_real_escape_string($finance_connect, $item['package_id']);
                        $safeDesc = mysqli_real_escape_string($finance_connect, $item['package_desc']);
                        $safeQty = (int) $item['qty'];
                        $insertItemSql = "INSERT INTO " . STOCK_ORDER_REQ_ITEM . "
                                          (request_id, product_id, brand_id, company_id, package_id, package_desc, qty, create_by, create_date, create_time)
                                          VALUES
                                          ('" . (int) $dataID . "', '" . $safeProdId . "', '" . $safeBrandId . "', '" . $safeCompanyId . "', '$safePkgId', '$safeDesc', '$safeQty', '" . USER_ID . "', CURDATE(), CURTIME())";
                        mysqli_query($finance_connect, $insertItemSql);
                    }

                    $token = sorEncodeToken($dataID);
                    $orderLink = $SITEURL . '/warehouse_stock_in.php?t=' . urlencode($token);

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

                    $showQrPanel = true;
                    $row['total_price'] = $safeTotalPrice;
                } else {
                    $err = 'Failed to save stock order request.';
                }
            }
        }
    }
}

function sorEcho($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>
    <div class="pre-load-center"><div class="preloader"></div></div>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-11 col-md-10 formWidthAdjust">
                <form id="sorForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= sorEcho($dataID) ?>">
                    <input type="hidden" name="act" value="<?= sorEcho($act) ?>">

                    <div class="form-group mb-4">
                        <h2><?= $pageActionTitle ?></h2>
                    </div>

                    <?php if (isset($err)) { ?>
                        <div id="err_msg" class="mb-3"><span><?= sorEcho($err) ?></span></div>
                    <?php } ?>

                    <?php if ($showQrPanel) { ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center mb-3">
                            <span>Stock Order saved. Redirecting to table in <strong><span id="countdownSec">15</span>s</strong>.</span>
                            <button type="button" class="btn btn-sm btn-rounded btn-primary" id="goNowBtn">Go To Table Now</button>
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
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= sorEcho($orderLink) ?>" target="_blank">Open Order Link</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_warehouse">Warehouse<span class="requireRed">*</span></label>
                            <div class="autocomplete">
                                <input class="form-control" type="text" id="sor_warehouse_name" name="sor_warehouse_name" value="<?= sorEcho(isset($warehouseNameMap[(int) (isset($row['warehouse_id']) ? $row['warehouse_id'] : 0)]) ? $warehouseNameMap[(int) $row['warehouse_id']] : '') ?>" placeholder="Select Warehouse" <?= ($act == '') ? 'readonly' : '' ?>>
                                <input type="hidden" id="sor_warehouse" name="sor_warehouse" value="<?= sorEcho(isset($row['warehouse_id']) ? $row['warehouse_id'] : '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_invoice_no">Invoice<span class="requireRed">*</span></label>
                            <textarea class="form-control" id="sor_invoice_no" name="sor_invoice_no" rows="1" <?= ($act == '') ? 'readonly' : '' ?>><?= sorEcho(isset($row['invoice_no']) ? $row['invoice_no'] : '') ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_invoice_date">Invoices Date<span class="requireRed">*</span></label>
                            <input class="form-control" type="date" id="sor_invoice_date" name="sor_invoice_date" value="<?= sorEcho(isset($row['invoice_date']) ? $row['invoice_date'] : date('Y-m-d')) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_request_date">Request Date<span class="requireRed">*</span></label>
                            <input class="form-control" type="date" id="sor_request_date" name="sor_request_date" value="<?= sorEcho(isset($row['request_date']) ? $row['request_date'] : date('Y-m-d')) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_courier">Courier</label>
                            <div class="autocomplete">
                                <input class="form-control" type="text" id="sor_courier_name" name="sor_courier_name" value="<?= sorEcho(isset($courierNameMap[(int) (isset($row['courier_id']) ? $row['courier_id'] : 0)]) ? $courierNameMap[(int) $row['courier_id']] : '') ?>" placeholder="Select Courier" <?= ($act == '') ? 'readonly' : '' ?>>
                                <input type="hidden" id="sor_courier" name="sor_courier" value="<?= sorEcho(isset($row['courier_id']) ? $row['courier_id'] : '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_tracking_no">Tracking Number</label>
                            <input class="form-control" type="text" id="sor_tracking_no" name="sor_tracking_no" value="<?= sorEcho(isset($row['tracking_no']) ? $row['tracking_no'] : '') ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label form_lbl">Product Name<span class="requireRed">*</span></label>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="sorItemTable">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Product Name</th>
                                        <th>Package</th>
                                        <th>Item Description</th>
                                        <th width="140">Quantity</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sorItemBody">
                                <?php
                                if (count($itemRows) === 0) {
                                    $itemRows[] = array('product_id' => '', 'package_id' => '', 'package_desc' => '', 'qty' => 1);
                                }
                                $idx = 1;
                                foreach ($itemRows as $item) {
                                    $prodId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                                    $prodName = isset($productNameMap[$prodId]) ? $productNameMap[$prodId] : '';
                                    $pkgId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                                    $pkgName = isset($packageNameMap[$pkgId]) ? $packageNameMap[$pkgId] : '';
                                    $desc = isset($item['package_desc']) ? $item['package_desc'] : '';
                                    $rowKey = $idx;
                                ?>
                                    <tr data-row-key="<?= (int) $rowKey ?>">
                                        <td class="row-no"><?= $idx++ ?></td>
                                        <td>
                                            <div class="autocomplete">
                                                <input class="form-control sor-item-prod-name" type="text" id="sor_item_prod_name_<?= (int) $rowKey ?>" name="sor_item_prod_name[]" value="<?= sorEcho($prodName) ?>" placeholder="Type Product Name" <?= ($act == '') ? 'readonly' : '' ?>>
                                                <input type="hidden" class="sor-item-prod-id" id="sor_item_prod_id_<?= (int) $rowKey ?>" name="sor_item_prod_id[]" value="<?= (int) $prodId ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="autocomplete">
                                                <input class="form-control sor-item-pkg-name" type="text" id="sor_item_pkg_name_<?= (int) $rowKey ?>" name="sor_item_pkg_name[]" value="<?= sorEcho($pkgName) ?>" placeholder="Type Package" <?= ($act == '') ? 'readonly' : '' ?>>
                                                <input type="hidden" class="sor-item-pkg-id" id="sor_item_pkg_id_<?= (int) $rowKey ?>" name="sor_item_pkg_id[]" value="<?= (int) $pkgId ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <input class="form-control sor-item-desc" type="text" name="sor_item_desc[]" value="<?= sorEcho($desc) ?>" readonly>
                                        </td>
                                        <td>
                                            <input class="form-control sor-item-qty" type="number" min="1" name="sor_item_qty[]" value="<?= sorEcho(isset($item['qty']) ? $item['qty'] : 1) ?>" <?= ($act == '') ? 'readonly' : '' ?>>
                                        </td>
                                        <td>
                                            <?php if ($act != '') { ?>
                                                <button type="button" class="btn btn-sm btn-rounded btn-primary remove-item-btn">Remove</button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($act != '') { ?>
                            <button type="button" id="addItemRowBtn" class="btn btn-sm btn-rounded btn-primary">+ Add Item</button>
                        <?php } ?>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_total_price">Total Price</label>
                            <input class="form-control" type="text" id="sor_total_price" name="sor_total_price" value="<?= sorEcho(isset($row['total_price']) ? $row['total_price'] : '0.00') ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_attachment">Attachment</label>
                            <input class="form-control" type="file" id="sor_attachment" name="sor_attachment" <?= ($act == '') ? 'disabled' : '' ?>>
                            <input type="hidden" name="existing_attachment" value="<?= sorEcho(isset($row['attachment']) ? $row['attachment'] : '') ?>">
                            <?php if (isset($row['attachment']) && $row['attachment'] !== '') { ?>
                                <div class="mt-2">
                                    <a href="<?= $SITEURL . img_server . $row['attachment'] ?>" target="_blank">View Current Attachment</a>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_remark">Remark</label>
                            <textarea class="form-control" id="sor_remark" name="sor_remark" rows="3" <?= ($act == '') ? 'readonly' : '' ?>><?= sorEcho(isset($row['remark']) ? $row['remark'] : '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-group mt-4 d-flex justify-content-center flex-md-row flex-column">
                        <?php if ($act) { ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2" name="actionBtn" value="<?= $actionBtnValue ?>"><?= $pageActionTitle ?></button>
                        <?php } ?>
                        <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2" name="actionBtn" value="back">Back</button>
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
            warehouses: <?= json_encode(array_values($warehouses)) ?>,
            couriers: <?= json_encode(array_values($couriers)) ?>,
            products: <?= json_encode(array_values($products)) ?>,
            packages: <?= json_encode(array_values($packages)) ?>
        };
    </script>
    <script src="../js/stock_order_req.js"></script>
</body>
</html>
