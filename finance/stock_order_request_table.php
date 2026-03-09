<?php
$pageTitle = 'Stock Order Request';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

sorEnsureSchema($finance_connect);

$pinAccess = checkPin($connect, 'Stock Order Request');
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $pinAccess = checkPin($connect, 'Stock List');
}
$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';

$redirect_page = $SITEURL . '/finance/stock_order_request.php';
$deleteRedirectPage = $SITEURL . '/finance/stock_order_request_table.php';

$sql = "SELECT *
        FROM " . STOCK_ORDER_REQ . "
        WHERE status = 'A'
        ORDER BY id DESC";
$result = mysqli_query($finance_connect, $sql);

function sorShortText($text, $limit = 70)
{
    $text = trim((string) $text);
    if ($text === '') return '';
    if (strlen($text) <= $limit) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return htmlspecialchars(substr($text, 0, $limit) . '...', ENT_QUOTES, 'UTF-8');
}

function sorNameById($connect, $table, $id)
{
    $id = (int) $id;
    if ($id <= 0) return '';
    $rst = getData('name', "id='$id'", 'LIMIT 1', $table, $connect);
    if ($rst && $row = $rst->fetch_assoc()) {
        return isset($row['name']) ? (string) $row['name'] : '';
    }
    return '';
}

function sorQrHref($path, $siteUrl)
{
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return rtrim((string) $siteUrl, '/') . '/' . ltrim($path, '/');
}

function sorPackageNameById($connect, $id)
{
    static $cache = array();
    $id = (int) $id;
    if ($id <= 0) return '';
    if (isset($cache[$id])) return $cache[$id];

    $rst = getData('name', "id='" . $id . "'", 'LIMIT 1', PKG, $connect);
    if ($rst && $row = $rst->fetch_assoc()) {
        $cache[$id] = isset($row['name']) ? (string) $row['name'] : '';
        return $cache[$id];
    }

    $cache[$id] = '';
    return '';
}
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    $(document).ready(function() {
        if (document.getElementById('sorTable')) {
            createSortingTable('sorTable');
        }
    });
</script>

<body>
    <div class="pre-load-center"><div class="preloader"></div></div>
    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageTitle ?></p>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed('Add', is_array($pinAccess) ? $pinAccess : array())) { ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" href="<?= $redirect_page . '?act=' . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Request</a>
                                    <a class="btn btn-sm btn-rounded btn-primary" href="<?= $SITEURL ?>/finance/stock_order_request_import.php"><i class="fa-solid fa-file-import"></i> Import</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$result || mysqli_num_rows($result) === 0) { ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php } else { ?>
                    <div class="table-responsive">
                    <table class="table table-striped" id="sorTable">
                        <thead>
                            <tr>
                                <th class="hideColumn">ID</th>
                                <th>S/N</th>
                                <th id="action_col">Action</th>
                                <th>Company</th>
                                <th>Package</th>
                                <th>Tracking Status</th>
                                <th>Tracking Number</th>
                                <th>Invoice</th>
                                <th>Invoices Date</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $num = 1; while ($row = mysqli_fetch_assoc($result)) {
                                $itemSummarySql = "SELECT i.package_id, i.qty
                                                   FROM " . STOCK_ORDER_REQ_ITEM . " i
                                                   WHERE i.request_id='" . (int) $row['id'] . "' AND i.status='A'";
                                $itemSummaryRst = mysqli_query($finance_connect, $itemSummarySql);
                                $itemSummary = '';
                                if ($itemSummaryRst) {
                                    $itemParts = array();
                                    while ($itemData = mysqli_fetch_assoc($itemSummaryRst)) {
                                        $pkgName = sorPackageNameById($connect, isset($itemData['package_id']) ? $itemData['package_id'] : 0);
                                        $qty = isset($itemData['qty']) ? (int) $itemData['qty'] : 0;
                                        if ($pkgName !== '') {
                                            $itemParts[] = $pkgName . ' x' . $qty;
                                        }
                                    }
                                    $itemSummary = implode(', ', $itemParts);
                                }

                                $warehouseName = sorNameById($connect, WHSE, isset($row['warehouse_id']) ? $row['warehouse_id'] : 0);
                                $courierName = sorNameById($connect, COURIER, isset($row['courier_id']) ? $row['courier_id'] : 0);
                                $courierTrackingLink = '';
                                if (!empty($row['courier_id'])) {
                                    $linkRst = getData('tracking_link', "id='" . (int) $row['courier_id'] . "'", 'LIMIT 1', COURIER, $connect);
                                    if ($linkRst && $linkRow = $linkRst->fetch_assoc()) {
                                        $courierTrackingLink = isset($linkRow['tracking_link']) ? $linkRow['tracking_link'] : '';
                                    }
                                }
                                $trackingUrl = sorBuildTrackingUrl($courierTrackingLink, isset($row['tracking_no']) ? $row['tracking_no'] : '');
                                $fullStatus = isset($row['tracking_status']) ? (string) $row['tracking_status'] : '';
                                $modalId = 'statusModal_' . (int) $row['id'];
                            ?>
                            <tr>
                                <td class="hideColumn"><?= (int) $row['id'] ?></td>
                                <td><?= $num++ ?></td>
                                <td class="btn-container">
                                    <?php renderViewEditButton('View', $redirect_page, $row, $pinAccess); ?>
                                    <?php renderViewEditButton('Edit', $redirect_page, $row, $pinAccess, $act_2); ?>
                                    <?php renderDeleteButton($pinAccess, $row['id'], isset($row['invoice_no']) ? $row['invoice_no'] : '', '', $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    <?php if (!empty($row['qr_image'])) { ?>
                                        <button type="button" class="btn btn-sm btn-rounded btn-primary sor-qr-btn" data-id="<?= (int) $row['id'] ?>" data-href="<?= htmlspecialchars(sorQrHref($row['qr_image'], $SITEURL), ENT_QUOTES, 'UTF-8') ?>" title="Download QR">
                                            <i class="fa-solid fa-qrcode"></i>
                                        </button>
                                    <?php } ?>
                                    <button type="button" class="btn btn-sm btn-rounded btn-primary sor-refresh-btn" data-id="<?= (int) $row['id'] ?>" title="Refresh Tracking">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </td>
                                <td><?= htmlspecialchars($warehouseName, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $itemSummary, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?= sorShortText($fullStatus) ?>
                                    <?php if (strlen($fullStatus) > 70) { ?>
                                        <button type="button" class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">View More</button>

                                        <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Tracking Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="white-space: pre-wrap;"><?= htmlspecialchars($fullStatus, ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($trackingUrl !== '') { ?>
                                        <a href="<?= htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= htmlspecialchars((string) $row['tracking_no'], ENT_QUOTES, 'UTF-8') ?></a>
                                    <?php } else { ?>
                                        <?= htmlspecialchars((string) $row['tracking_no'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php } ?>
                                </td>
                                <td style="white-space: pre-line;"><?= htmlspecialchars((string) $row['invoice_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $row['invoice_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= number_format((float) (isset($row['total_price']) ? $row['total_price'] : 0), 2) ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        var page = "<?= $pageTitle ?>";
        var action = "";
        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        if (document.getElementById('sorTable')) {
            datatableAlignment('sorTable');
        }
        setButtonColor();
        preloader(300);

        function sorNotify(msg) {
            if (typeof showNotification === 'function') {
                showNotification(msg);
            } else {
                alert(msg);
            }
        }

        document.querySelectorAll('.sor-qr-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var href = btn.getAttribute('data-href') || '';
                var id = btn.getAttribute('data-id') || 'order';
                if (!href) {
                    sorNotify('QR image not found.');
                    return;
                }

                fetch(href)
                    .then(function(resp) { return resp.blob(); })
                    .then(function(blob) {
                        var blobUrl = URL.createObjectURL(blob);
                        var link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = 'stock_order_qr_' + id + '.png';
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(blobUrl);
                        sorNotify('QR download success.');
                    })
                    .catch(function() {
                        sorNotify('Failed to download QR image.');
                    });
            });
        });

        document.querySelectorAll('.sor-refresh-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                btn.disabled = true;
                fetch('stock_order_tracking_refresh.php?id=' + encodeURIComponent(id), {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json(); })
                .then(function(resp) {
                    sorNotify(resp.message || 'Tracking refresh completed.');
                    window.location.reload();
                })
                .catch(function() {
                    sorNotify('Tracking refresh failed.');
                })
                .finally(function() {
                    btn.disabled = false;
                });
            });
        });
    </script>
</body>

</html>
