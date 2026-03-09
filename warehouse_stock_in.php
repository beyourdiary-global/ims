<?php
$pageTitle = 'Stock In';

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/common.php';

$stockInOrderTable = 'stock_in_order';
$stockInItemTable = 'stock_in_order_item';
siEnsureSchema($finance_connect, $stockInOrderTable, $stockInItemTable);

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

$pageAction = strtoupper(trim((string) input('act')));
$pageItemId = (int) input('item_id');
$isViewMode = ($pageAction === 'V' && $pageItemId > 0);
$isEditMode = ($pageAction === 'E' && $pageItemId > 0);

$formData = array(
    'order_id' => 0,
    'item_id' => 0,
    'warehouse_id' => 0,
    'stock_in_date' => date('Y-m-d'),
    'order_number' => '',
    'product_id' => 0,
    'product_name' => '',
    'product_quantity' => 1,
);

$warehouseDisabledAttr = $isViewMode ? ' disabled' : '';
$inputReadonlyAttr = $isViewMode ? ' readonly' : '';
$inputDisabledAttr = $isViewMode ? ' disabled' : '';

$itemById = array();
foreach (siFetchFlatRows($finance_connect, $stockInOrderTable, $stockInItemTable) as $row) {
    $itemById[(int) $row['item_id']] = $row;
}

if (($isViewMode || $isEditMode) && $pageItemId > 0) {
    if (!isset($itemById[$pageItemId])) {
        $err = 'Stock In row not found.';
        $isViewMode = false;
        $isEditMode = false;
    } else {
        $r = $itemById[$pageItemId];
        $formData['order_id'] = (int) $r['order_id'];
        $formData['item_id'] = (int) $r['item_id'];
        $formData['warehouse_id'] = (int) $r['warehouse_id'];
        $formData['stock_in_date'] = (string) $r['stock_in_date'];
        $formData['order_number'] = (string) $r['order_number'];
        $formData['product_id'] = (int) $r['product_id'];
        $formData['product_name'] = isset($productNameMap[(int) $r['product_id']]) ? (string) $productNameMap[(int) $r['product_id']] : '';
        $formData['product_quantity'] = (int) $r['product_quantity'];
    }
}

$err = '';

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
            echo "<script>location.href='" . $redirectTable . "?msg=" . urlencode($saved[1]) . "';</script>";
            exit;
        }

        $err = $saved[1];
    }
}

if (post('actionBtn') === 'update') {
    $orderId = (int) postSpaceFilter('order_id');
    $itemId = (int) postSpaceFilter('item_id');
    $warehouseId = (int) postSpaceFilter('warehouse_id');
    $stockInDate = trim((string) postSpaceFilter('stock_in_date'));
    $orderNumber = trim((string) postSpaceFilter('order_number'));
    $productId = (int) postSpaceFilter('product_id');
    $productName = trim((string) postSpaceFilter('product_name'));
    $qty = (int) postSpaceFilter('product_quantity');

    if ($productId <= 0 && $productName !== '') {
        $prodKey = strtolower(trim($productName));
        if (isset($productNameToId[$prodKey])) {
            $productId = (int) $productNameToId[$prodKey];
        }
    }

    if ($orderId <= 0 || $itemId <= 0) {
        $err = 'Invalid row for update.';
    } else if ($warehouseId <= 0) {
        $err = 'Warehouse cannot be empty.';
    } else if ($stockInDate === '') {
        $err = 'Stock In Date cannot be empty.';
    } else if ($orderNumber === '') {
        $err = 'Order Number cannot be empty.';
    } else if ($productId <= 0) {
        $err = 'Please select valid product name from the list.';
    } else if ($qty <= 0) {
        $err = 'Product quantity must be greater than 0.';
    } else {
        $safeDate = mysqli_real_escape_string($finance_connect, $stockInDate);
        $safeOrderNo = mysqli_real_escape_string($finance_connect, $orderNumber);

        $uOrder = "UPDATE `" . $stockInOrderTable . "` SET warehouse_id='" . $warehouseId . "', stock_in_date='" . $safeDate . "', order_number='" . $safeOrderNo . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $orderId . "' AND status='A'";
        $uItem = "UPDATE `" . $stockInItemTable . "` SET product_id='" . $productId . "', package_id='0', product_quantity='" . $qty . "', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $itemId . "' AND status='A'";

        mysqli_begin_transaction($finance_connect);
        try {
            mysqli_query($finance_connect, $uOrder);
            mysqli_query($finance_connect, $uItem);
            mysqli_commit($finance_connect);
            echo "<script>location.href='" . $redirectTable . "?msg=" . urlencode('Stock In updated successfully.') . "';</script>";
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
        $reqSql = "SELECT id, request_no, warehouse_id, request_date
                   FROM " . STOCK_ORDER_REQ . "
                   WHERE id='" . (int) $requestId . "' AND status='A' LIMIT 1";
        $reqRst = mysqli_query($finance_connect, $reqSql);
        if ($reqRst && ($req = mysqli_fetch_assoc($reqRst))) {
            $itemSql = "SELECT product_id, package_id, qty
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
                (string) $req['request_no'],
                $items,
                (int) $requestId
            );

            $q = $saved[0] ? 'msg' : 'err';
            echo "<script>location.href='" . $redirectTable . "?" . $q . "=" . urlencode($saved[1]) . "';</script>";
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
                            <a class="btn btn-sm btn-rounded btn-primary" href="<?= $redirectTable ?>">Back To Table</a>
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
                            <input type="hidden" name="item_id" value="<?= (int) $formData['item_id'] ?>">
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
                                    <tr>
                                        <td class="row-no">1</td>
                                        <td>
                                            <div class="autocomplete">
                                                <input class="form-control product_name" name="product_name<?= ($isEditMode || $isViewMode) ? '' : '[]' ?>" placeholder="Type Product" value="<?= siEsc($formData['product_name']) ?>" required<?= $inputReadonlyAttr ?>>
                                                <input type="hidden" name="product_id<?= ($isEditMode || $isViewMode) ? '' : '[]' ?>" class="product_id" value="<?= (int) $formData['product_id'] ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <input class="form-control" type="number" name="product_quantity<?= ($isEditMode || $isViewMode) ? '' : '[]' ?>" min="1" value="<?= (int) $formData['product_quantity'] ?>" required<?= $inputReadonlyAttr ?>>
                                        </td>
                                        <td>
                                            <?php if (!$isViewMode && !$isEditMode) { ?>
                                                <button type="button" class="btn btn-sm btn-rounded btn-primary remove-row-btn">Remove</button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!$isViewMode && !$isEditMode) { ?>
                            <button type="button" class="btn btn-sm btn-rounded btn-primary" id="addStockInRowBtn">+ Add Product</button>
                            <button class="btn btn-sm btn-rounded btn-primary ms-2" name="actionBtn" value="save">Save Stock In</button>
                        <?php } else if ($isEditMode) { ?>
                            <button class="btn btn-sm btn-rounded btn-primary" name="actionBtn" value="update">Update Stock In</button>
                        <?php } else { ?>
                            <a class="btn btn-sm btn-rounded btn-warning" href="<?= $SITEURL ?>/warehouse_stock_in.php?act=E&item_id=<?= (int) $formData['item_id'] ?>">Edit This Row</a>
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
        if (isViewMode || isEditMode) {
            return;
        }
        if (e.target.classList.contains('remove-row-btn')) {
            var rows = document.querySelectorAll('#stockInItemBody tr').length;
            if (rows > 1) {
                e.target.closest('tr').remove();
                reindexRows();
            }
        }
    });

    var addBtn = document.getElementById('addStockInRowBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            var tbody = document.getElementById('stockInItemBody');
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="row-no"></td>' +
                '<td><div class="autocomplete"><input class="form-control product_name" name="product_name[]" placeholder="Type Product" required><input type="hidden" name="product_id[]" class="product_id" value=""></div></td>' +
                '<td><input class="form-control" type="number" name="product_quantity[]" min="1" value="1" required></td>' +
                '<td><button type="button" class="btn btn-sm btn-rounded btn-primary remove-row-btn">Remove</button></td>';
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
