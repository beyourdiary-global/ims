<?php
$pageTitle = 'Stock Order Request';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

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
    (SELECT GROUP_CONCAT(CONCAT(i.package_id, ':', IFNULL(i.packageQty, 1), ':', IFNULL(i.company_id, 0), ':', IFNULL(i.product_id, 0), ':', IFNULL(i.brand_id, 0), ':', IFNULL(i.package_group_key, '')) SEPARATOR '|')
         FROM " . STOCK_ORDER_REQ_ITEM . " i
         WHERE i.request_id = r.id AND i.status = 'A') AS item_data_raw
        FROM " . STOCK_ORDER_REQ . " r
        WHERE r.status = 'A'
        ORDER BY r.id DESC";
$result = mysqli_query($finance_connect, $sql);

// 2. Setup Bulk Fetch Arrays for Cross-Database queries
$rows = array();
$companyIds = array();
$courierIds = array();
$pkgIds = array();
$productIds = array();
$brandIds = array();
$requestOrderNumbers = array();

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $resolvedOrderNumber = trim((string) (isset($row['invoice_no']) ? $row['invoice_no'] : ''));
        if ($resolvedOrderNumber === '') {
            $resolvedOrderNumber = 'SOR-' . (int) $row['id'];
        }
        $row['_resolved_order_number'] = $resolvedOrderNumber;

        $rows[] = $row;

        $orderNumberKey = strtolower($resolvedOrderNumber);
        if ($orderNumberKey !== '') {
            $requestOrderNumbers[$orderNumberKey] = $resolvedOrderNumber;
        }

        if (!empty($row['brand_id'])) {
            $brandIds[(int) $row['brand_id']] = true;
        }
        if (!empty($row['company_id'])) {
            $companyIds[$row['company_id']] = true;
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
                if (isset($parts[2]) && (int) $parts[2] > 0) {
                    $companyIds[(int) $parts[2]] = true;
                }
                if (isset($parts[3]) && (int) $parts[3] > 0) {
                    $productIds[(int) $parts[3]] = true;
                }
                if (isset($parts[4]) && (int) $parts[4] > 0) {
                    $brandIds[(int) $parts[4]] = true;
                }
            }
        }
    }
}

$stockedInOrderMap = array();
if (!empty($requestOrderNumbers)) {
    $escapedOrderNumbers = array();
    foreach ($requestOrderNumbers as $orderNumber) {
        $escapedOrderNumbers[] = "'" . mysqli_real_escape_string($finance_connect, (string) $orderNumber) . "'";
    }

    if (!empty($escapedOrderNumbers)) {
        $stockedSql = "SELECT DISTINCT TRIM(order_number) AS order_number FROM `stock_in_order` WHERE status='A' AND TRIM(order_number) IN (" . implode(',', $escapedOrderNumbers) . ")";
        $stockedRst = mysqli_query($finance_connect, $stockedSql);
        if ($stockedRst) {
            while ($stockedRow = mysqli_fetch_assoc($stockedRst)) {
                $stockedOrderNumber = strtolower(trim((string) (isset($stockedRow['order_number']) ? $stockedRow['order_number'] : '')));
                if ($stockedOrderNumber !== '') {
                    $stockedInOrderMap[$stockedOrderNumber] = true;
                }
            }
        }
    }
}

// 3. Execute 3 Bulk Queries to map cross-database relationships safely
$companyMap = array();
if (!isset($companyMap) || !is_array($companyMap)) {
    $companyMap = array();
}
if (!empty($companyIds)) {
    // Only query companies that are not already present in $companyMap
    $newCompanyIds = array_diff_key($companyIds, $companyMap);
    if (!empty($newCompanyIds)) {
        $idsStr = implode(',', array_keys($newCompanyIds));
        $cmyRst = mysqli_query($connect, "SELECT id, name FROM " . COMPANY . " WHERE id IN ($idsStr)");
        if ($cmyRst) {
            while ($cmyRow = mysqli_fetch_assoc($cmyRst)) {
                $companyMap[$cmyRow['id']] = $cmyRow['name'];
            }
        }
    }
}

$courierMap = array();
$courierLinkMap = array();
if (!empty($courierIds)) {
    $courierIdSqlParts = array();
    foreach (array_keys($courierIds) as $cid) {
        $courierIdSqlParts[] = "'" . mysqli_real_escape_string($connect, (string) $cid) . "'";
    }
    $idsStr = implode(',', $courierIdSqlParts);
    $cRst = mysqli_query($connect, "SELECT id, name, tracking_link FROM " . COURIER . " WHERE id IN ($idsStr)");
    if ($cRst) {
        while ($cRow = mysqli_fetch_assoc($cRst)) {
            $courierMap[$cRow['id']] = $cRow['name'];
            $courierLinkMap[$cRow['id']] = $cRow['tracking_link'];
        }
    }
}

$packageMap = array();
$packageProductMap = array();
if (!empty($pkgIds)) {
    $idsStr = implode(',', array_keys($pkgIds));
    $pRst = mysqli_query($connect, "SELECT id, name, product FROM " . PKG . " WHERE id IN ($idsStr)");
    if ($pRst) {
        while ($pRow = mysqli_fetch_assoc($pRst)) {
            $packageMap[$pRow['id']] = $pRow['name'];

            $pkgId = (int) $pRow['id'];
            $pkgProductIds = array();
            $productCsv = isset($pRow['product']) ? trim((string) $pRow['product']) : '';
            if ($productCsv !== '') {
                foreach (explode(',', $productCsv) as $prodRaw) {
                    $prodId = (int) trim((string) $prodRaw);
                    if ($prodId > 0) {
                        $pkgProductIds[] = $prodId;
                        $productIds[$prodId] = true;
                    }
                }
            }
            $packageProductMap[$pkgId] = array_values(array_unique($pkgProductIds));
        }
    }
}

$productBrandMap = array();
$productNameMap = array();
if (!empty($productIds)) {
    $idsStr = implode(',', array_keys($productIds));
    $prdRst = mysqli_query($connect, "SELECT id, name, brand FROM " . PROD . " WHERE id IN ($idsStr)");
    if ($prdRst) {
        while ($prdRow = mysqli_fetch_assoc($prdRst)) {
            $productNameMap[(int) $prdRow['id']] = isset($prdRow['name']) ? (string) $prdRow['name'] : '';
            $productBrandMap[(int) $prdRow['id']] = isset($prdRow['brand']) ? (int) $prdRow['brand'] : 0;
            if (!empty($prdRow['brand'])) {
                $brandIds[(int) $prdRow['brand']] = true;
            }
        }
    }
}

$brandCompanyMap = array();
if (!empty($brandIds)) {
    $idsStr = implode(',', array_keys($brandIds));
    $brRst = mysqli_query($connect, "SELECT id, company FROM " . BRAND . " WHERE id IN ($idsStr)");
    if ($brRst) {
        while ($brRow = mysqli_fetch_assoc($brRst)) {
            $brandCompanyMap[(int) $brRow['id']] = isset($brRow['company']) ? (int) $brRow['company'] : 0;
            if (!empty($brRow['company'])) {
                $companyIds[(int) $brRow['company']] = true;
            }
        }
    }
}

if (!empty($companyIds)) {
    $idsStr = implode(',', array_keys($companyIds));
    $cmyRst = mysqli_query($connect, "SELECT id, name FROM " . COMPANY . " WHERE id IN ($idsStr)");
    if ($cmyRst) {
        while ($cmyRow = mysqli_fetch_assoc($cmyRst)) {
            $companyMap[$cmyRow['id']] = $cmyRow['name'];
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
                                    <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $redirect_page . '?act=' . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Request</a>
                                    <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $SITEURL ?>/finance/stock_order_request_import.php"><i class="fa-solid fa-file-import"></i> Import</a>
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
                                <th>Package/Product</th>
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
                                        $seenPackageGroups = array();
                                    $items = explode('|', $row['item_data_raw']);
                                    $companyNames = array();
                                        foreach ($items as $item) {
                                        $parts = explode(':', $item);
                                        if (count($parts) >= 2) {
                                            $pkgId = $parts[0];
                                            $qty = $parts[1];
                                            $itemCompanyId = isset($parts[2]) ? (int) $parts[2] : 0;
                                            $itemProductId = isset($parts[3]) ? (int) $parts[3] : 0;
                                            $itemBrandId = isset($parts[4]) ? (int) $parts[4] : 0;
                                                $itemGroupKey = isset($parts[5]) ? trim((string) $parts[5]) : '';
                                            $pkgName = isset($packageMap[$pkgId]) ? (string) $packageMap[$pkgId] : '';

                                            $resolvedProductId = $itemProductId;
                                            if ($resolvedProductId <= 0 && isset($packageProductMap[(int) $pkgId]) && count($packageProductMap[(int) $pkgId]) === 1) {
                                                $resolvedProductId = (int) $packageProductMap[(int) $pkgId][0];
                                            }
                                            $productName = ($resolvedProductId > 0 && isset($productNameMap[$resolvedProductId])) ? (string) $productNameMap[$resolvedProductId] : '';

                                            $displayName = '';
                                            if ($pkgName !== '') {
                                                $displayName = $pkgName;
                                            } else if ($productName !== '') {
                                                $displayName = $productName;
                                            }

                                                if ($displayName !== '') {
                                                    if ((int) $pkgId > 0) {
                                                        $summaryKey = ($itemGroupKey !== '') ? ('pkg_' . $itemGroupKey) : ('pkg_' . $pkgId . '_' . $qty);
                                                        if (!isset($seenPackageGroups[$summaryKey])) {
                                                            $itemParts[] = $displayName . 'x' . $qty;
                                                            $seenPackageGroups[$summaryKey] = true;
                                                        }
                                                    } else {
                                                        $itemParts[] = $displayName . 'x' . $qty;
                                                    }
                                                }

                                            $resolvedCompanyId = $itemCompanyId;
                                            if ($resolvedCompanyId <= 0) {
                                                $resolvedBrandId = $itemBrandId;
                                                if ($resolvedBrandId <= 0 && $itemProductId > 0 && isset($productBrandMap[$itemProductId])) {
                                                    $resolvedBrandId = (int) $productBrandMap[$itemProductId];
                                                }
                                                if ($resolvedBrandId <= 0 && isset($packageProductMap[(int) $pkgId]) && !empty($packageProductMap[(int) $pkgId])) {
                                                    foreach ($packageProductMap[(int) $pkgId] as $pkgProdId) {
                                                        if (isset($productBrandMap[(int) $pkgProdId]) && (int) $productBrandMap[(int) $pkgProdId] > 0) {
                                                            $resolvedBrandId = (int) $productBrandMap[(int) $pkgProdId];
                                                            break;
                                                        }
                                                    }
                                                }
                                                if ($resolvedBrandId > 0 && isset($brandCompanyMap[$resolvedBrandId])) {
                                                    $resolvedCompanyId = (int) $brandCompanyMap[$resolvedBrandId];
                                                }
                                            }

                                            if ($resolvedCompanyId > 0 && isset($companyMap[$resolvedCompanyId])) {
                                                $companyNames[$resolvedCompanyId] = $companyMap[$resolvedCompanyId];
                                            }
                                        }
                                        }
                                        $itemSummary = implode(', ', $itemParts);
                                } else {
                                    $companyNames = array();
                                }

                                if (!empty($row['company_id']) && isset($companyMap[$row['company_id']])) {
                                    $companyNames[(int) $row['company_id']] = $companyMap[$row['company_id']];
                                } else if (!empty($row['brand_id']) && isset($brandCompanyMap[(int) $row['brand_id']])) {
                                    $fallbackCompanyId = (int) $brandCompanyMap[(int) $row['brand_id']];
                                    if ($fallbackCompanyId > 0 && isset($companyMap[$fallbackCompanyId])) {
                                        $companyNames[$fallbackCompanyId] = $companyMap[$fallbackCompanyId];
                                    }
                                }

                                // Apply Bulk Maps
                                $companyName = implode(', ', array_values($companyNames));
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
                                    <?php
                                    $resolvedOrderNumber = strtolower(trim((string) (isset($row['_resolved_order_number']) ? $row['_resolved_order_number'] : '')));
                                    $isStockedInOrder = ($resolvedOrderNumber !== '' && isset($stockedInOrderMap[$resolvedOrderNumber]));
                                    ?>
                                    <?php if (!empty($row['qr_image']) && !$isStockedInOrder) { ?>
                                        <button type="button" class="btn btn-sm btn-rounded btn-primary sor-qr-btn" data-id="<?= (int) $row['id'] ?>" data-href="<?= htmlspecialchars(sorQrHref($row['qr_image'], $SITEURL), ENT_QUOTES, 'UTF-8') ?>" title="Download QR">
                                            <i class="fa-solid fa-qrcode"></i>
                                        </button>
                                    <?php } ?>
                                    <button type="button" class="btn btn-sm btn-rounded btn-primary sor-refresh-btn" data-id="<?= (int) $row['id'] ?>" title="Refresh Tracking">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </td>
                                <td><?= htmlspecialchars((string) $companyName, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $itemSummary, ENT_QUOTES, 'UTF-8') ?></td>
                                <td id="tracking-status-<?= (int) $row['id'] ?>" data-full-status="<?= htmlspecialchars($fullStatus, ENT_QUOTES, 'UTF-8') ?>">
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

        function sorShowStatusModal(title, msg) {
            var modalEl = document.getElementById('sorResultModal');
            if (!modalEl) {
                modalEl = document.createElement('div');
                modalEl.className = 'modal fade';
                modalEl.id = 'sorResultModal';
                modalEl.tabIndex = -1;
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.innerHTML =
                    '<div class="modal-dialog modal-dialog-centered">' +
                    '  <div class="modal-content">' +
                    '    <div class="modal-header">' +
                    '      <h5 class="modal-title" id="sorResultModalTitle"></h5>' +
                    '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                    '    </div>' +
                    '    <div class="modal-body" id="sorResultModalBody" style="white-space:pre-wrap;"></div>' +
                    '    <div class="modal-footer">' +
                    '      <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue</button>' +
                    '    </div>' +
                    '  </div>' +
                    '</div>';
                document.body.appendChild(modalEl);
            }

            var titleEl = document.getElementById('sorResultModalTitle');
            var bodyEl = document.getElementById('sorResultModalBody');
            if (titleEl) {
                titleEl.textContent = title || 'Tracking Refresh';
            }
            if (bodyEl) {
                bodyEl.textContent = msg || '';
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                alert(msg || 'Done.');
            }
        }

        function sorNotify(msg, title) {
            sorShowStatusModal(title || 'Notice', msg);
        }

        function sorRenderTrackingStatus(rowId, fullStatus) {
            var td = document.getElementById('tracking-status-' + String(rowId || ''));
            if (!td) {
                return;
            }

            var text = String(fullStatus || '').trim();
            td.setAttribute('data-full-status', text);
            td.innerHTML = '';

            var shortText = text;
            if (shortText.length > 70) {
                shortText = shortText.substring(0, 70) + '...';
            }

            td.appendChild(document.createTextNode(shortText));

            if (text.length > 70) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-link';
                btn.textContent = 'View More';
                btn.addEventListener('click', function() {
                    sorShowStatusModal('Tracking Status', text);
                });
                td.appendChild(document.createTextNode(' '));
                td.appendChild(btn);
            }
        }

        document.querySelectorAll('.sor-qr-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var href = btn.getAttribute('data-href') || '';
                var id = btn.getAttribute('data-id') || 'order';
                if (!href) {
                    sorNotify('QR image not found.', 'QR Download');
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
                        sorNotify('QR download success.', 'QR Download');
                    })
                    .catch(function() {
                        sorNotify('Failed to download QR image.', 'QR Download');
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
                    if (resp && typeof resp.tracking_status !== 'undefined') {
                        sorRenderTrackingStatus(id, resp.tracking_status || '');
                    }
                    sorNotify((resp && resp.message) ? resp.message : 'Tracking refresh completed.', 'Tracking Refresh');
                })
                .catch(function() {
                    sorNotify('Tracking refresh failed.', 'Tracking Refresh');
                })
                .finally(function() {
                    btn.disabled = false;
                });
            });
        });
    </script>
</body>

</html>