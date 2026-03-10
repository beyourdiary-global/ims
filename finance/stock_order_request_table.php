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

// 1. Refactored Main Query using a SUBQUERY (No JOINs)
$sql = "SELECT r.*,
        (SELECT GROUP_CONCAT(CONCAT(i.package_id, ':', i.qty) SEPARATOR '|')
         FROM " . STOCK_ORDER_REQ_ITEM . " i
         WHERE i.request_id = r.id AND i.status = 'A') AS item_data_raw
        FROM " . STOCK_ORDER_REQ . " r
        WHERE r.status = 'A'
        ORDER BY r.id DESC";
$result = mysqli_query($finance_connect, $sql);

// 2. Setup Bulk Fetch Arrays for Cross-Database queries
$rows = array();
$whseIds = array();
$courierIds = array();
$pkgIds = array();

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
        if (!empty($row['warehouse_id'])) {
            $whseIds[$row['warehouse_id']] = true;
        }
        if (!empty($row['courier_id'])) {
            $courierIds[$row['courier_id']] = true;
        }
        if (!empty($row['item_data_raw'])) {
            $items = explode('|', $row['item_data_raw']);
            foreach ($items as $item) {
                $parts = explode(':', $item);
                if (isset($parts[0]) && !empty($parts[0])) {
                    $pkgIds[$parts[0]] = true;
                }
            }
        }
    }
}

// 3. Execute 3 Bulk Queries to map cross-database relationships safely
$warehouseMap = array();
if (!empty($whseIds)) {
    $idsStr = implode(',', array_keys($whseIds));
    $wRst = mysqli_query($connect, "SELECT id, name FROM " . WHSE . " WHERE id IN ($idsStr)");
    if ($wRst) {
        while ($wRow = mysqli_fetch_assoc($wRst)) {
            $warehouseMap[$wRow['id']] = $wRow['name'];
        }
    }
}

$courierMap = array();
$courierLinkMap = array();
if (!empty($courierIds)) {
    $idsStr = implode(',', array_keys($courierIds));
    $cRst = mysqli_query($connect, "SELECT id, name, tracking_link FROM " . COURIER . " WHERE id IN ($idsStr)");
    if ($cRst) {
        while ($cRow = mysqli_fetch_assoc($cRst)) {
            $courierMap[$cRow['id']] = $cRow['name'];
            $courierLinkMap[$cRow['id']] = $cRow['tracking_link'];
        }
    }
}

$packageMap = array();
if (!empty($pkgIds)) {
    $idsStr = implode(',', array_keys($pkgIds));
    $pRst = mysqli_query($connect, "SELECT id, name FROM " . PKG . " WHERE id IN ($idsStr)");
    if ($pRst) {
        while ($pRow = mysqli_fetch_assoc($pRst)) {
            $packageMap[$pRow['id']] = $pRow['name'];
        }
    }
}

function sorShortText($text, $limit = 70)
{
    $text = trim((string) $text);
    if ($text === '') return '';
    if (strlen($text) <= $limit) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return htmlspecialchars(substr($text, 0, $limit) . '...', ENT_QUOTES, 'UTF-8');
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
// Note: sorNameById and sorPackageNameById removed as N+1 logic is gone
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

                <?php if (empty($rows)) { ?>
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
                            <?php 
                            $num = 1; 
                            foreach ($rows as $row) {
                                // Deconstruct Subquery Data
                                $itemSummary = '';
                                if (!empty($row['item_data_raw'])) {
                                    $itemParts = array();
                                    $items = explode('|', $row['item_data_raw']);
                                    foreach ($items as $item) {
                                        $parts = explode(':', $item);
                                        if (count($parts) >= 2) {
                                            $pkgId = $parts[0];
                                            $qty = $parts[1];
                                            $pkgName = isset($packageMap[$pkgId]) ? $packageMap[$pkgId] : '';
                                            if ($pkgName !== '') {
                                                $itemParts[] = $pkgName . ' x' . $qty;
                                            }
                                        }
                                    }
                                    $itemSummary = implode(', ', $itemParts);
                                }

                                // Apply Bulk Maps
                                $warehouseName = isset($warehouseMap[$row['warehouse_id']]) ? $warehouseMap[$row['warehouse_id']] : '';
                                $courierName = isset($courierMap[$row['courier_id']]) ? $courierMap[$row['courier_id']] : '';
                                $courierTrackingLink = isset($courierLinkMap[$row['courier_id']]) ? $courierLinkMap[$row['courier_id']] : '';
                                
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