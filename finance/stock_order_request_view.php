<?php
$isFinance = 1;
include '../init.php';
include ROOT . '/include/connection.php';
include ROOT . '/include/common.php';

sorEnsureSchema($finance_connect);

$token = input('t');
$requestId = sorDecodeToken($token);

if ($requestId <= 0) {
    echo '<h3>Invalid order link.</h3>';
    exit;
}

$sql = "SELECT *
    FROM " . STOCK_ORDER_REQ . "
    WHERE id='" . (int) $requestId . "' AND status='A'";
$rst = mysqli_query($finance_connect, $sql);
if (!$rst || !($row = mysqli_fetch_assoc($rst))) {
    echo '<h3>Order request not found.</h3>';
    exit;
}

$itemSql = "SELECT i.package_desc, i.qty
            FROM " . STOCK_ORDER_REQ_ITEM . " i
            WHERE i.request_id='" . (int) $requestId . "' AND i.status='A'
            ORDER BY i.id ASC";
$itemRst = mysqli_query($finance_connect, $itemSql);

$warehouse_name = '';
$request_by_name = '';
$courier_name = '';
$courier_tracking_link = '';

if (!empty($row['warehouse_id'])) {
    $wRst = getData('name', "id='" . (int) $row['warehouse_id'] . "'", 'LIMIT 1', WHSE, $connect);
    if ($wRst && ($wRow = $wRst->fetch_assoc())) {
        $warehouse_name = isset($wRow['name']) ? $wRow['name'] : '';
    }
}

if (!empty($row['request_by'])) {
    $uRst = getData('name', "id='" . (int) $row['request_by'] . "'", 'LIMIT 1', USR_USER, $connect);
    if ($uRst && ($uRow = $uRst->fetch_assoc())) {
        $request_by_name = isset($uRow['name']) ? $uRow['name'] : '';
    }
}

if (!empty($row['courier_id'])) {
    $cRst = getData('name,tracking_link', "id='" . (int) $row['courier_id'] . "'", 'LIMIT 1', COURIER, $connect);
    if ($cRst && ($cRow = $cRst->fetch_assoc())) {
        $courier_name = isset($cRow['name']) ? $cRow['name'] : '';
        $courier_tracking_link = isset($cRow['tracking_link']) ? $cRow['tracking_link'] : '';
    }
}

$trackingUrl = sorBuildTrackingUrl($courier_tracking_link, isset($row['tracking_no']) ? $row['tracking_no'] : '');

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Order Request <?= e($row['request_no']) ?></title>
    <link rel="stylesheet" href="<?= SITEURL ?>/header/bootstrap-5.0.2-dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title mb-3">Stock Order Request</h3>
                <div class="row mb-2">
                    <div class="col-md-4"><strong>Invoice:</strong> <?= nl2br(e($row['invoice_no'])) ?></div>
                    <div class="col-md-4"><strong>Invoice Date:</strong> <?= e($row['invoice_date']) ?></div>
                    <div class="col-md-4"><strong>Date:</strong> <?= e($row['request_date']) ?></div>
                    <div class="col-md-4"><strong>Warehouse:</strong> <?= e($warehouse_name) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-4"><strong>Courier:</strong> <?= e($courier_name) ?></div>
                    <div class="col-md-4"><strong>Total Price:</strong> <?= number_format((float) (isset($row['total_price']) ? $row['total_price'] : 0), 2) ?></div>
                    <div class="col-md-4"><strong>Tracking No:</strong>
                        <?php if ($trackingUrl !== '') { ?>
                            <a href="<?= e($trackingUrl) ?>" target="_blank"><?= e($row['tracking_no']) ?></a>
                        <?php } else { ?>
                            <?= e($row['tracking_no']) ?>
                        <?php } ?>
                    </div>
                </div>

                <div class="mt-3">
                    <h5>Package Items</h5>
                    <table class="table table-bordered">
                        <thead><tr><th>#</th><th>Description</th><th>Qty</th></tr></thead>
                        <tbody>
                            <?php $i = 1; if ($itemRst) { while ($item = mysqli_fetch_assoc($itemRst)) { ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= e($item['package_desc']) ?></td>
                                    <td><?= e($item['qty']) ?></td>
                                </tr>
                            <?php }} ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($row['remark'])) { ?>
                    <div class="mt-3"><strong>Remark:</strong> <?= nl2br(e($row['remark'])) ?></div>
                <?php } ?>
                <?php if (!empty($row['tracking_status'])) { ?>
                    <div class="mt-3"><strong>Tracking Status:</strong><br><?= nl2br(e($row['tracking_status'])) ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
</body>
</html>
