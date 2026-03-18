<?php
$pageTitle = 'Stock In';

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
$tblName = $stockInOrderTable;

$pinAccess = checkCurrentPin($connect, 'Stock In');
if (!is_array($pinAccess)) {
    $pinAccess = array();
}

$redirectTable = $SITEURL . '/warehouse_stock_in_table.php';

$warehouses = siLoadWarehouses($connect);
$products = siLoadProducts($connect);
$packages = siLoadPackages($connect);
$packageProductMap = siBuildPackageProductMap($packages);

list($warehouseNameMap, $warehouseNameToId) = siBuildNameMaps($warehouses);
list($productNameMap, $productNameToId) = siBuildNameMaps($products);

$legacyItemId = !empty(input('item_id')) ? (int) input('item_id') : 0;
$dataID = !empty(input('order_id')) ? (int) input('order_id') : (int) post('order_id');
$act = !empty(input('act')) ? strtoupper(trim((string) input('act'))) : strtoupper(trim((string) post('act')));
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pageOrderId = $dataID;
$isViewMode = ($act === 'V' && $pageOrderId > 0);
$isEditMode = ($act === 'E' && $pageOrderId > 0);



$formData = array(
    'order_id' => 0,
    'item_id' => 0,
    'warehouse_id' => 0,
    'stock_in_date' => '',
    'order_number' => '',
    'product_id' => 0,
    'product_name' => '',
    'product_quantity' => '',
);

$formRows = array(
    array(
        'product_id' => (int) $formData['product_id'],
        'product_name' => (string) $formData['product_name'],
        'product_quantity' => (string) $formData['product_quantity'],
    )
);

$warehouseDisabledAttr = $isViewMode ? ' disabled' : '';
$inputReadonlyAttr = $isViewMode ? ' readonly' : '';
$inputDisabledAttr = $isViewMode ? ' disabled' : '';

$err = '';

$orderById = array();
foreach (siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable) as $row) {
    $orderId = (int) $row['order_id'];
    if (!isset($orderById[$orderId])) {
        $orderById[$orderId] = array(
            'order_id' => $orderId,
            'warehouse_id' => (int) $row['warehouse_id'],
            'stock_in_date' => (string) $row['stock_in_date'],
            'order_number' => (string) $row['order_number'],
            'items' => array(),
        );
    }
    $orderById[$orderId]['items'][] = array(
        'item_id' => (int) $row['item_id'],
        'product_id' => (int) $row['product_id'],
        'product_quantity' => (int) $row['product_quantity'],
    );

    if ($pageOrderId <= 0 && $legacyItemId > 0 && (int) $row['item_id'] === $legacyItemId) {
        $pageOrderId = $orderId;
        $isViewMode = ($act === 'V' && $pageOrderId > 0);
        $isEditMode = ($act === 'E' && $pageOrderId > 0);
    }
}

if (($isViewMode || $isEditMode) && $pageOrderId > 0) {
    if (!isset($orderById[$pageOrderId])) {
        $err = 'Stock In row not found.';
        $isViewMode = false;
        $isEditMode = false;
    } else {
        $r = $orderById[$pageOrderId];
        $formData['order_id'] = (int) $r['order_id'];
        $formData['item_id'] = isset($r['items'][0]['item_id']) ? (int) $r['items'][0]['item_id'] : 0;
        $formData['warehouse_id'] = (int) $r['warehouse_id'];
        $formData['stock_in_date'] = (string) $r['stock_in_date'];
        $formData['order_number'] = (string) $r['order_number'];
        $formData['product_id'] = isset($r['items'][0]['product_id']) ? (int) $r['items'][0]['product_id'] : 0;
        $formData['product_name'] = isset($productNameMap[(int) $formData['product_id']]) ? (string) $productNameMap[(int) $formData['product_id']] : '';
        $formData['product_quantity'] = isset($r['items'][0]['product_quantity']) ? (int) $r['items'][0]['product_quantity'] : 0;

        $formRows = array();
        foreach ($r['items'] as $itemRow) {
            $formRows[] = array(
                'product_id' => (int) $itemRow['product_id'],
                'product_name' => isset($productNameMap[(int) $itemRow['product_id']]) ? (string) $productNameMap[(int) $itemRow['product_id']] : '',
                'product_quantity' => (string) ((int) $itemRow['product_quantity']),
            );
        }
        if (count($formRows) === 0) {
            $formRows[] = array(
                'product_id' => (int) $formData['product_id'],
                'product_name' => (string) $formData['product_name'],
                'product_quantity' => (string) $formData['product_quantity'],
            );
        }
    }
}

if ($isViewMode && $pageOrderId > 0 && isset($orderById[$pageOrderId])) {
    $viewRow = $orderById[$pageOrderId];
    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => 'OrderID=' . (int) $pageOrderId,
        'query_table' => $tblName,
        'act_msg' => USER_NAME . " viewed stock in data [ <b>Order No = " . htmlspecialchars((string) $viewRow['order_number'], ENT_QUOTES, 'UTF-8') . "</b> ] from <b><i>" . $stockInOrderTable . " Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);
}

if (post('actionBtn') === 'save') {
    $warehouseId = (int) postSpaceFilter('warehouse_id');
    $stockInDate = trim((string) postSpaceFilter('stock_in_date'));
    $orderNumber = trim((string) postSpaceFilter('order_number'));

    $productIds = isset($_POST['product_id']) ? postSpaceFilter('product_id') : array();
    $productNames = isset($_POST['product_name']) ? postSpaceFilter('product_name') : array();
    $quantities = isset($_POST['product_quantity']) ? postSpaceFilter('product_quantity') : array();

    if (!is_array($productIds)) $productIds = array();
    if (!is_array($productNames)) $productNames = array();
    if (!is_array($quantities)) $quantities = array();

    $items = array();
    $invalidProduct = false;

    $max = max(count($productIds), count($productNames), count($quantities));
    for ($i = 0; $i < $max; $i++) {
        $prodId = isset($productIds[$i]) ? (int) $productIds[$i] : 0;
        $prodName = isset($productNames[$i]) ? trim((string) $productNames[$i]) : '';
        $qty = isset($quantities[$i]) ? (int) $quantities[$i] : 0;

        if ($prodId <= 0 && $prodName !== '') {
            $prodKey = strtolower(trim($prodName));
            if (isset($productNameToId[$prodKey])) {
                $prodId = (int) $productNameToId[$prodKey];
            }
        }

        $hasAnyValue = ($prodId > 0 || $qty > 0 || $prodName !== '');
        if (!$hasAnyValue) {
            continue;
        }

        if ($prodId <= 0) {
            $invalidProduct = true;
            continue;
        }

        if ($qty <= 0) {
            continue;
        }

        $items[] = array(
            'product_id' => $prodId,
            'package_id' => 0,
            'qty' => $qty,
        );
    }

    if ($warehouseId <= 0) {
        $err = 'Warehouse cannot be empty.';
    } else if ($stockInDate === '') {
        $err = 'Stock In Date cannot be empty.';
    } else if ($orderNumber === '') {
        $err = 'Order Number cannot be empty.';
    } else if ($invalidProduct) {
        $err = 'Please select valid product name from the list.';
    } else if (count($items) === 0) {
        $err = 'Please add at least one valid product row.';
    } else {
        $saved = siSaveOrder(
            $finance_connect,
            $stockInOrderTable,
            $stockInItemTable,
            $warehouseId,
            $stockInDate,
            $orderNumber,
            $items
        );

        if ($saved[0]) {
            $savePageAction = getPageAction('I');
            $summaryRows = array();
            foreach ($items as $it) {
                $summaryRows[] = 'ProductID=' . (int) $it['product_id'] . ', Qty=' . (int) $it['qty'];
            }
            $queryRecord = 'WarehouseID=' . $warehouseId . '; StockInDate=' . $stockInDate . '; OrderNo=' . $orderNumber;
            $newValue = implode(' | ', $summaryRows);
            $log = [
                'log_act' => $savePageAction,
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $queryRecord,
                'query_table' => $tblName,
                'newval' => $newValue,
                'act_msg' => USER_NAME . " added stock in data [ <b>Order No = " . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . "</b> ] under <b><i>" . $stockInItemTable . " Table</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo "<script>confirmationDialog('', '', '" . addslashes($pageTitle) . "', '', '" . $redirectTable . "', 'I');</script>";
            exit;
        }

        $err = $saved[1];
    }
}

if (post('actionBtn') === 'update') {
    $orderId = (int) postSpaceFilter('order_id');
    $warehouseId = (int) postSpaceFilter('warehouse_id');
    $stockInDate = trim((string) postSpaceFilter('stock_in_date'));
    $orderNumber = trim((string) postSpaceFilter('order_number'));

    $productIds = isset($_POST['product_id']) ? postSpaceFilter('product_id') : array();
    $productNames = isset($_POST['product_name']) ? postSpaceFilter('product_name') : array();
    $quantities = isset($_POST['product_quantity']) ? postSpaceFilter('product_quantity') : array();

    if (!is_array($productIds)) $productIds = array($productIds);
    if (!is_array($productNames)) $productNames = array($productNames);
    if (!is_array($quantities)) $quantities = array($quantities);

    $items = array();
    $invalidProduct = false;
    $max = max(count($productIds), count($productNames), count($quantities));

    for ($i = 0; $i < $max; $i++) {
        $prodId = isset($productIds[$i]) ? (int) $productIds[$i] : 0;
        $prodName = isset($productNames[$i]) ? trim((string) $productNames[$i]) : '';
        $qty = isset($quantities[$i]) ? (int) $quantities[$i] : 0;

        if ($prodId <= 0 && $prodName !== '') {
            $prodKey = strtolower(trim($prodName));
            if (isset($productNameToId[$prodKey])) {
                $prodId = (int) $productNameToId[$prodKey];
            }
        }

        $hasAnyValue = ($prodId > 0 || $qty > 0 || $prodName !== '');
        if (!$hasAnyValue) {
            continue;
        }

        if ($prodId <= 0) {
            $invalidProduct = true;
            continue;
        }

        if ($qty <= 0) {
            continue;
        }

        $items[] = array(
            'product_id' => $prodId,
            'qty' => $qty,
        );
    }

    if ($orderId <= 0) {
        $err = 'Invalid row for update.';
    } else if ($warehouseId <= 0) {
        $err = 'Warehouse cannot be empty.';
    } else if ($stockInDate === '') {
        $err = 'Stock In Date cannot be empty.';
    } else if ($orderNumber === '') {
        $err = 'Order Number cannot be empty.';
    } else if ($invalidProduct) {
        $err = 'Please select valid product name from the list.';
    } else if (count($items) === 0) {
        $err = 'Please add at least one valid product row.';
    } else {
        $oldWarehouseId = 0;
        $oldStockInDate = '';
        $oldOrderNumber = '';
        $oldSummaryRows = array();

        $oldOrderSql = "SELECT warehouse_id, stock_in_date, order_number FROM `" . $stockInOrderTable . "` WHERE id='" . $orderId . "' AND status='A' LIMIT 1";
        $oldOrderRst = mysqli_query($finance_connect, $oldOrderSql);
        if ($oldOrderRst && ($oldOrderRow = mysqli_fetch_assoc($oldOrderRst))) {
            $oldWarehouseId = (int) $oldOrderRow['warehouse_id'];
            $oldStockInDate = (string) $oldOrderRow['stock_in_date'];
            $oldOrderNumber = (string) $oldOrderRow['order_number'];
        }

        $oldItemsSql = "SELECT product_id, product_quantity FROM `" . $stockInItemTable . "` WHERE stock_in_order_id='" . $orderId . "' AND status='A' ORDER BY id ASC";
        $oldItemsRst = mysqli_query($finance_connect, $oldItemsSql);
        if ($oldItemsRst) {
            while ($oldItem = mysqli_fetch_assoc($oldItemsRst)) {
                $oldSummaryRows[] = 'ProductID=' . (int) $oldItem['product_id'] . ', Qty=' . (int) $oldItem['product_quantity'];
            }
        }

        $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
        $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);

        $uOrder = "UPDATE `" . $stockInOrderTable . "` SET warehouse_id='" . $warehouseId . "', stock_in_date='" . $safeDate . "', order_number='" . $safeOrderNo . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $orderId . "' AND status='A'";
        $deactivateItems = "UPDATE `" . $stockInItemTable . "` SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE stock_in_order_id='" . $orderId . "' AND status='A'";

        mysqli_begin_transaction($finance_connect);
        try {
            mysqli_query($finance_connect, $uOrder);

            mysqli_query($finance_connect, $deactivateItems);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['qty'];
                $iItem = "INSERT INTO `" . $stockInItemTable . "` (stock_in_order_id, product_id, package_id, product_quantity, create_by, create_date, create_time, status) VALUES ('" . $orderId . "', '" . $productId . "', '0', '" . $qty . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
                mysqli_query($finance_connect, $iItem);
            }

            mysqli_commit($finance_connect);

            $editPageAction = getPageAction('E');
            $summaryRows = array();
            foreach ($items as $it) {
                $summaryRows[] = 'ProductID=' . (int) $it['product_id'] . ', Qty=' . (int) $it['qty'];
            }
            
            $oldChanges = implode(' | ', $oldSummaryRows);
            $changes = implode(' | ', $summaryRows);

            $oldVals = array();
            $newVals = array();
            $msgChanges = array();

            if ((int)$oldWarehouseId !== (int)$warehouseId) {
                $oldVals[] = "WarehouseID=" . $oldWarehouseId;
                $newVals[] = "WarehouseID=" . $warehouseId;
                $msgChanges[] = "[ <b>WarehouseID</b> : <b>'" . $oldWarehouseId . "'</b> to <b>'" . $warehouseId . "'</b> ]";
            }
            if ($oldStockInDate !== $stockInDate) {
                $oldVals[] = "StockInDate=" . $oldStockInDate;
                $newVals[] = "StockInDate=" . $stockInDate;
                $msgChanges[] = "[ <b>StockInDate</b> : <b>'" . $oldStockInDate . "'</b> to <b>'" . $stockInDate . "'</b> ]";
            }
            if ($oldOrderNumber !== $orderNumber) {
                $oldVals[] = "OrderNo=" . $oldOrderNumber;
                $newVals[] = "OrderNo=" . $orderNumber;
                $msgChanges[] = "[ <b>OrderNo</b> : <b>'" . htmlspecialchars($oldOrderNumber, ENT_QUOTES, 'UTF-8') . "'</b> to <b>'" . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . "'</b> ]";
            }
            if ($oldChanges !== $changes) {
                $oldItemsStr = $oldChanges !== '' ? $oldChanges : '-';
                $newItemsStr = $changes !== '' ? $changes : '-';
                $oldVals[] = "Items={" . $oldItemsStr . "}";
                $newVals[] = "Items={" . $newItemsStr . "}";
                $msgChanges[] = "[ <b>Items</b> : <b>'" . htmlspecialchars($oldItemsStr, ENT_QUOTES, 'UTF-8') . "'</b> to <b>'" . htmlspecialchars($newItemsStr, ENT_QUOTES, 'UTF-8') . "'</b> ]";
            }

            if (count($msgChanges) > 0) {
                $queryRecord = 'OrderID=' . $orderId;
                
                $log = [
                    'log_act' => $editPageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $queryRecord,
                    'query_table' => $tblName,
                    'oldval' => implode(' ; ', $oldVals),
                    'changes' => implode(' ; ', $newVals),
                    'act_msg' => USER_NAME . " edited stock in data [ <b>Order ID = " . (int) $orderId . "</b> ] " . implode(' ', $msgChanges) . " under <b><i>" . $stockInItemTable . " Table</i></b>.",
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                audit_log($log);
            }

            echo "<script>confirmationDialog('', '', '" . addslashes($pageTitle) . "', '', '" . $redirectTable . "', 'E');</script>";
            exit;
        } catch (Exception $ex) {
            mysqli_rollback($finance_connect);
            $err = 'Update failed: ' . $ex->getMessage();
        }
    }
}

$token = input('t');
if ($token) {
    $requestId = sorDecodeToken($token);
    if ($requestId > 0) {
        $reqSql = "SELECT id,
                          COALESCE(NULLIF(TRIM(invoice_no), ''), CONCAT('SOR-', id)) AS order_number,
                          warehouse_id,
                          request_date
                   FROM " . STOCK_ORDER_REQ . "
                   WHERE id='" . (int) $requestId . "' AND status='A' LIMIT 1";
        $reqRst = mysqli_query($finance_connect, $reqSql);
        if ($reqRst && ($req = mysqli_fetch_assoc($reqRst))) {
            $itemSql = "SELECT product_id,
                               package_id,
                           IFNULL(productQty, IFNULL(packageQty, 1)) AS qty
                        FROM " . STOCK_ORDER_REQ_ITEM . "
                        WHERE request_id='" . (int) $requestId . "' AND status='A'";
            $itemRst = mysqli_query($finance_connect, $itemSql);
            $items = array();
            if ($itemRst) {
                while ($it = mysqli_fetch_assoc($itemRst)) {
                    $prodId = isset($it['product_id']) ? (int) $it['product_id'] : 0;
                    $pkgId = isset($it['package_id']) ? (int) $it['package_id'] : 0;
                    if ($prodId <= 0) {
                        $prodId = siResolveProductIdFromPackage($packageProductMap, $pkgId);
                    }
                    if ($prodId <= 0) {
                        continue;
                    }
                    $items[] = array(
                        'product_id' => $prodId,
                        'package_id' => 0,
                        'qty' => (int) $it['qty'],
                    );
                }
            }

            $saved = siSaveOrder(
                $finance_connect,
                $stockInOrderTable,
                $stockInItemTable,
                (int) $req['warehouse_id'],
                (string) $req['request_date'],
                (string) $req['order_number'],
                $items
            );

            if ($saved[0]) {
                $tokenPageAction = getPageAction('I');
                $log = [
                    'log_act' => $tokenPageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => 'RequestID=' . (int) $requestId,
                    'query_table' => $tblName,
                    'newval' => 'Auto-created from request token',
                    'act_msg' => USER_NAME . " created stock in data from stock order request [ <b>Request ID = " . (int) $requestId . "</b> ] under <b><i>" . $stockInItemTable . " Table</i></b>.",
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                audit_log($log);
            }

            echo "<script>alert('" . addslashes($saved[1]) . "'); location.href='" . $redirectTable . "';</script>";
            exit;
        }
        $err = 'Invalid or inactive stock order request token.';
    } else {
        $err = 'Invalid token.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="./css/main.css">
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <a href="<?= $redirectTable ?>">Stock In</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Add Stock In</p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2><?= $isViewMode ? 'View Stock In' : ($isEditMode ? 'Edit Stock In' : 'Add Stock In') ?></h2>
                        <div class="mt-auto mb-auto">
                            <a class="btn btn-sm btn-rounded btn-primary" id="actionBtn" href="<?= $redirectTable ?>">Back To Table</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($err !== '') { ?>
                <div class="alert alert-danger"><?= siEsc($err) ?></div>
            <?php } ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Stock In Form</h5>
                    <form method="post" id="stockInForm">
                        <?php if ($isEditMode || $isViewMode) { ?>
                            <input type="hidden" name="order_id" value="<?= (int) $formData['order_id'] ?>">
                        <?php } ?>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Warehouse*</label>
                                <select class="form-select" name="warehouse_id" id="warehouse_id" required<?= $warehouseDisabledAttr ?>>
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $w) { ?>
                                        <option value="<?= (int) $w['id'] ?>" <?= ((int) $formData['warehouse_id'] === (int) $w['id']) ? 'selected' : '' ?>><?= siEsc($w['name']) ?></option>
                                    <?php } ?>
                                </select>
                                <?php if ($isViewMode) { ?>
                                    <input type="hidden" name="warehouse_id" value="<?= (int) $formData['warehouse_id'] ?>">
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock In Date*</label>
                                <input class="form-control" type="date" name="stock_in_date" id="stock_in_date" value="<?= siEsc($formData['stock_in_date']) ?>" required<?= $inputReadonlyAttr ?>>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Order Number*</label>
                                <input class="form-control" type="text" name="order_number" id="order_number" value="<?= siEsc($formData['order_number']) ?>" required<?= $inputReadonlyAttr ?>>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered" id="stockInItemTable">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Product Name*</th>
                                        <th width="180">Product Quantity*</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="stockInItemBody">
                                    <?php foreach ($formRows as $idx => $formRow) { ?>
                                    <tr>
                                        <td class="row-no"><?= (int) ($idx + 1) ?></td>
                                        <td>
                                            <div class="autocomplete">
                                                <input class="form-control product_name" name="product_name[]" placeholder="Type Product" value="<?= siEsc($formRow['product_name']) ?>" required<?= $inputReadonlyAttr ?>>
                                                <input type="hidden" name="product_id[]" class="product_id" value="<?= (int) $formRow['product_id'] ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <input class="form-control" type="number" name="product_quantity[]" min="1" value="<?= siEsc($formRow['product_quantity']) ?>" required<?= $inputReadonlyAttr ?>>
                                        </td>
                                        <td>
                                            <?php if (!$isViewMode) { ?>
                                                <button type="button" class="btn btn-sm btn-rounded btn-danger remove-stock-row">Remove</button>
                                            <?php } else { ?>
                                                &nbsp;
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!$isViewMode && !$isEditMode) { ?>
                            <button type="button" class="btn btn-sm btn-rounded btn-primary" id="addStockInRowBtn">+ Add Product</button>
                            <button class="btn btn-sm btn-rounded btn-primary ms-2" name="actionBtn" value="save">Save Stock In</button>
                        <?php } else if ($isEditMode) { ?>
                            <button type="button" class="btn btn-sm btn-rounded btn-primary" id="addStockInRowBtn">+ Add Product</button>
                            <button class="btn btn-sm btn-rounded btn-primary ms-2" name="actionBtn" value="update">Update Stock In</button>
                        <?php } ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var page = <?= json_encode('Stock In') ?>;
    var action = '';
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);

    function norm(v) {
        return String(v || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    var products = <?= json_encode(array_values($products)) ?>;
    var productByName = {};
    products.forEach(function(p){
        productByName[norm(p.name)] = p;
    });

    function closeList(input) {
        var listId = input.getAttribute('data-list-id');
        if (!listId) return;
        var el = document.getElementById(listId);
        if (el) el.remove();
    }

    function renderList(input, hiddenInput, options) {
        closeList(input);
        var keyword = norm(input.value);
        if (keyword === '' || input.hasAttribute('readonly')) return;

        var filtered = (options || []).filter(function(opt){
            return norm(opt.name).indexOf(keyword) !== -1;
        }).slice(0, 20);
        if (filtered.length === 0) return;

        var listId = 'sr_' + (input.name || 'x') + '_' + Date.now();
        input.setAttribute('data-list-id', listId);

        var ul = document.createElement('ul');
        ul.className = 'searchResult';
        ul.id = listId;
        ul.style.width = input.offsetWidth + 'px';

        filtered.forEach(function(opt){
            var li = document.createElement('li');
            li.textContent = opt.name;
            li.addEventListener('mousedown', function(e){
                e.preventDefault();
                input.value = opt.name;
                hiddenInput.value = String(opt.id);
                closeList(input);
            });
            ul.appendChild(li);
        });

        input.after(ul);
    }

    function bindRow(row) {
        var productName = row.querySelector('.product_name');
        var productId = row.querySelector('.product_id');
        if (!productName || !productId || productName.dataset.bound === '1') return;

        productName.dataset.bound = '1';

        productName.addEventListener('input', function(){
            productId.value = '';
            renderList(productName, productId, products);
        });

        productName.addEventListener('change', function(){
            var byName = productByName[norm(productName.value)] || null;
            productId.value = byName ? String(byName.id) : '';
        });

        productName.addEventListener('blur', function(){
            setTimeout(function(){ closeList(productName); }, 120);
        });
    }

    function reindexRows() {
        document.querySelectorAll('#stockInItemBody tr').forEach(function(row, idx) {
            var no = row.querySelector('.row-no');
            if (no) no.textContent = String(idx + 1);
        });
    }

    var isViewMode = <?= json_encode($isViewMode) ?>;
    var isEditMode = <?= json_encode($isEditMode) ?>;

    document.getElementById('stockInItemBody').addEventListener('click', function(e) {
        if (isViewMode) {
            return;
        }

        var removeBtn = e.target.closest('.remove-stock-row');
        if (!removeBtn) {
            return;
        }

        var tbody = document.getElementById('stockInItemBody');
        var rows = tbody.querySelectorAll('tr');
        if (rows.length <= 1) {
            var row = removeBtn.closest('tr');
            if (!row) return;
            var productNameInput = row.querySelector('input[name="product_name[]"]');
            var productIdInput = row.querySelector('input[name="product_id[]"]');
            var qtyInput = row.querySelector('input[name="product_quantity[]"]');
            if (productNameInput) productNameInput.value = '';
            if (productIdInput) productIdInput.value = '';
            if (qtyInput) qtyInput.value = '';
            return;
        }

        var tr = removeBtn.closest('tr');
        if (tr) {
            tr.remove();
            reindexRows();
        }
    });

    var addBtn = document.getElementById('addStockInRowBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var tbody = document.getElementById('stockInItemBody');
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="row-no"></td>' +
                '<td><div class="autocomplete"><input class="form-control product_name" name="product_name[]" placeholder="Type Product" required><input type="hidden" name="product_id[]" class="product_id" value=""></div></td>' +
                '<td><input class="form-control" type="number" name="product_quantity[]" min="1" value="" required></td>' +
                '<td><button type="button" class="btn btn-sm btn-rounded btn-danger remove-stock-row">Remove</button></td>';
            tbody.appendChild(tr);
            bindRow(tr);
            reindexRows();
        });
    }

    document.querySelectorAll('#stockInItemBody tr').forEach(function(row) {
        bindRow(row);
    });
</script>
</body>
</html>
