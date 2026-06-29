<?php
$currentPagePin = 146;
$pageTitle = 'Waiting To Pack';
$displayPageTitle = 'Waiting To Pack';
$disablePinGroupPageTitleSync = true;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$processingAccess = checkPinByGroupId($connect, 146);
$legacyProcessingAccess = checkPinByGroupId($connect, 128);
$allOrdersAccess = checkPinByGroupId($connect, 130);
$canViewPage = isActionAllowed('View', $processingAccess) || isActionAllowed('View', $legacyProcessingAccess) || isActionAllowed('View', $allOrdersAccess);
if (!$canViewPage) {
    renderNotificationScript('You do not have permission to view Waiting To Pack.', 'error', '../dashboard.php', 1200, true);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $log = array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    );
    audit_log($log);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$platformTabs = array('all' => 'All');
foreach (shopeeOmsGetOrderSourceConfigs() as $platformKey => $platformConfig) {
    $platformTabs[$platformKey] = isset($platformConfig['label']) ? (string) $platformConfig['label'] : ucfirst((string) $platformKey);
}

$requestedPlatform = trim((string) input('platform'));
if ($requestedPlatform === '' && post('platform_section') !== '') {
    $requestedPlatform = trim((string) post('platform_section'));
}
$activePlatform = shopeeOmsNormalizePlatformKey($requestedPlatform, true);
if ($activePlatform === '') {
    $activePlatform = 'all';
}

$statusMessage = '';
$statusClass = 'success';

if (post('scanOrderBtn')) {
    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        $statusClass = 'danger';
        $statusMessage = 'Invalid session token. Please refresh the page and try again.';
    } else {
        $scanValue = trim((string) postSpaceFilter('scan_value'));
        $scanToken = '';
        $lookupPlatform = $activePlatform !== 'all' ? $activePlatform : '';
        if ($scanValue !== '' && preg_match('/[?&]t=([^&]+)/', $scanValue, $matches)) {
            $scanToken = trim((string) urldecode($matches[1]));
        } else {
            $safeScanValue = mysqli_real_escape_string($finance_connect, $scanValue);
            $tokenConditions = array(
                "token = '" . $safeScanValue . "'",
                "status = 'A'",
                "token_type = 'stock_out'",
            );
            if ($lookupPlatform !== '' && shopeeOmsTableHasColumn($finance_connect, dbFinance, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform')) {
                if ($lookupPlatform === 'shopee') {
                    $tokenConditions[] = "(platform = 'shopee' OR platform = '' OR platform IS NULL)";
                } else {
                    $tokenConditions[] = "platform = '" . mysqli_real_escape_string($finance_connect, $lookupPlatform) . "'";
                }
            }
            $tokenRst = mysqli_query($finance_connect, "SELECT token FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE " . implode(' AND ', $tokenConditions) . " ORDER BY id DESC LIMIT 1");
            if ($tokenRst && mysqli_num_rows($tokenRst) > 0) {
                $tokenRow = mysqli_fetch_assoc($tokenRst);
                $scanToken = isset($tokenRow['token']) ? (string) $tokenRow['token'] : '';
            } else {
                $orderRow = shopeeOmsLoadOrderByCodeAnyPlatform($connect, $finance_connect, $scanValue, $lookupPlatform);
                if (!empty($orderRow)) {
                    $platformKey = shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee');
                    $tokenConditions = array(
                        "order_id = " . (int) $orderRow['id'],
                        "status = 'A'",
                        "token_type = 'stock_out'",
                    );
                    if (shopeeOmsTableHasColumn($finance_connect, dbFinance, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform')) {
                        if ($platformKey === 'shopee') {
                            $tokenConditions[] = "(platform = 'shopee' OR platform = '' OR platform IS NULL)";
                        } else {
                            $tokenConditions[] = "platform = '" . mysqli_real_escape_string($finance_connect, $platformKey) . "'";
                        }
                    }
                    $existingTokenRst = mysqli_query($finance_connect, "SELECT token FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE " . implode(' AND ', $tokenConditions) . " ORDER BY id DESC LIMIT 1");
                    if ($existingTokenRst && mysqli_num_rows($existingTokenRst) > 0) {
                        $existingTokenRow = mysqli_fetch_assoc($existingTokenRst);
                        $scanToken = isset($existingTokenRow['token']) ? (string) $existingTokenRow['token'] : '';
                    } else {
                        $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $orderRow, USER_ID, $platformKey);
                        if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']['token'])) {
                            $scanToken = (string) $tokenResult['token_row']['token'];
                        }
                    }
                }
            }
        }

        if ($scanToken === '') {
            $statusClass = 'danger';
            $statusMessage = 'Scan value is invalid or order token was not found.';
        } else {
            $scanResult = shopeeOmsProcessWarehouseScanByToken($connect, $finance_connect, $scanToken, USER_ID, USER_GROUP, $pageTitle);
            $statusClass = !empty($scanResult['success']) ? 'success' : 'danger';
            $statusMessage = isset($scanResult['message']) ? (string) $scanResult['message'] : 'Unable to process warehouse scan.';
        }
    }
}

$waitingToPackRows = array();
foreach (shopeeOmsGetOrderSourceConfigs() as $platformKey => $platformConfig) {
    $orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $platformConfig);
    if (!($orderConnect instanceof mysqli)) {
        continue;
    }

    $statusCondition = shopeeOmsBuildOrderStatusInCondition($orderConnect, 'order_status', array('TP'));
    if ($statusCondition === '') {
        continue;
    }

    $dateField = isset($platformConfig['date_field']) && trim((string) $platformConfig['date_field']) !== ''
        ? trim((string) $platformConfig['date_field'])
        : 'create_date';
    $orderByParts = array();
    if (shopeeOmsTableHasColumn($orderConnect, isset($platformConfig['db_name']) ? $platformConfig['db_name'] : dbFinance, isset($platformConfig['table']) ? $platformConfig['table'] : '', $dateField)) {
        $orderByParts[] = "`" . $dateField . "` DESC";
    }
    if (shopeeOmsTableHasColumn($orderConnect, isset($platformConfig['db_name']) ? $platformConfig['db_name'] : dbFinance, isset($platformConfig['table']) ? $platformConfig['table'] : '', 'time')) {
        $orderByParts[] = "`time` DESC";
    } else if (shopeeOmsTableHasColumn($orderConnect, isset($platformConfig['db_name']) ? $platformConfig['db_name'] : dbFinance, isset($platformConfig['table']) ? $platformConfig['table'] : '', 'create_time')) {
        $orderByParts[] = "`create_time` DESC";
    }
    $orderByParts[] = "id DESC";

    $sql = "SELECT * FROM `" . $platformConfig['table'] . "` WHERE status = 'A' AND " . $statusCondition . " ORDER BY " . implode(', ', $orderByParts);
    $result = mysqli_query($orderConnect, $sql);
    if (!$result) {
        continue;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $waitingToPackRows[] = shopeeOmsAttachOrderSourceMeta((array) $row, $platformKey, $platformConfig);
    }
}

usort($waitingToPackRows, function ($a, $b) use ($platformTabs) {
    $dateA = isset($a['date']) && trim((string) $a['date']) !== '' ? (string) $a['date'] : (isset($a['create_date']) ? (string) $a['create_date'] : '');
    $dateB = isset($b['date']) && trim((string) $b['date']) !== '' ? (string) $b['date'] : (isset($b['create_date']) ? (string) $b['create_date'] : '');
    $timeA = isset($a['time']) && trim((string) $a['time']) !== '' ? (string) $a['time'] : (isset($a['create_time']) ? (string) $a['create_time'] : '');
    $timeB = isset($b['time']) && trim((string) $b['time']) !== '' ? (string) $b['time'] : (isset($b['create_time']) ? (string) $b['create_time'] : '');
    $stampA = trim($dateA . ' ' . $timeA);
    $stampB = trim($dateB . ' ' . $timeB);
    if ($stampA !== $stampB) {
        return strcmp($stampB, $stampA);
    }

    $platformA = isset($a['__oms_platform']) ? (string) $a['__oms_platform'] : '';
    $platformB = isset($b['__oms_platform']) ? (string) $b['__oms_platform'] : '';
    $labelA = isset($platformTabs[$platformA]) ? (string) $platformTabs[$platformA] : $platformA;
    $labelB = isset($platformTabs[$platformB]) ? (string) $platformTabs[$platformB] : $platformB;
    if ($labelA !== $labelB) {
        return strcmp($labelA, $labelB);
    }

    return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
});

$shopeeBuyerMetaMap = array();
$shopeeBuyerLookupValues = array();
foreach ($waitingToPackRows as $waitingRow) {
    if (shopeeOmsGetOrderSourcePlatform($waitingRow, 'shopee') !== 'shopee') {
        continue;
    }
    $shopeeBuyerLookupValues[] = isset($waitingRow['buyer']) ? $waitingRow['buyer'] : '';
}
if (!empty($shopeeBuyerLookupValues)) {
    $shopeeBuyerMetaMap = customerLabelGetShopeeCustomerMetaMap($connect, $finance_connect, $shopeeBuyerLookupValues);
}

$waitingToPackWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect);
$waitingToPackDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect);

$orderTokenMap = array();
$tokenOrderIdsByPlatform = array();
foreach ($waitingToPackRows as $waitingRow) {
    $platformKey = shopeeOmsGetOrderSourcePlatform($waitingRow, 'shopee');
    $rowId = isset($waitingRow['id']) ? (int) $waitingRow['id'] : 0;
    if ($rowId > 0) {
        if (!isset($tokenOrderIdsByPlatform[$platformKey])) {
            $tokenOrderIdsByPlatform[$platformKey] = array();
        }
        $tokenOrderIdsByPlatform[$platformKey][$rowId] = $rowId;
    }
}

foreach ($tokenOrderIdsByPlatform as $platformKey => $orderIds) {
    if (empty($orderIds)) {
        continue;
    }

    $tokenConditions = array(
        "status = 'A'",
        "token_type = 'stock_out'",
        "order_id IN (" . implode(',', $orderIds) . ")",
    );
    if (shopeeOmsTableHasColumn($finance_connect, dbFinance, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform')) {
        if ($platformKey === 'shopee') {
            $tokenConditions[] = "(platform = 'shopee' OR platform = '' OR platform IS NULL)";
        } else {
            $tokenConditions[] = "platform = '" . mysqli_real_escape_string($finance_connect, $platformKey) . "'";
        }
    }

    $tokenSql = "SELECT order_id, token" . (shopeeOmsTableHasColumn($finance_connect, dbFinance, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform') ? ", platform" : "") . " FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE " . implode(' AND ', $tokenConditions) . " ORDER BY order_id ASC, id DESC";
    $tokenRst = mysqli_query($finance_connect, $tokenSql);
    if (!$tokenRst) {
        continue;
    }

    while ($tokenRow = mysqli_fetch_assoc($tokenRst)) {
        $tokenOrderId = (int) (isset($tokenRow['order_id']) ? $tokenRow['order_id'] : 0);
        if ($tokenOrderId <= 0) {
            continue;
        }

        $tokenPlatform = shopeeOmsNormalizePlatformKey(isset($tokenRow['platform']) ? $tokenRow['platform'] : '') ?: 'shopee';
        $tokenMapKey = $tokenPlatform . '|' . $tokenOrderId;
        if (!isset($orderTokenMap[$tokenMapKey])) {
            $orderTokenMap[$tokenMapKey] = isset($tokenRow['token']) ? (string) $tokenRow['token'] : '';
        }
    }
}

$waitingRowsByPlatform = array('all' => $waitingToPackRows);
foreach ($platformTabs as $platformKey => $platformLabel) {
    if ($platformKey === 'all') {
        continue;
    }
    $waitingRowsByPlatform[$platformKey] = array_values(array_filter($waitingToPackRows, function ($row) use ($platformKey) {
        return shopeeOmsGetOrderSourcePlatform($row, 'shopee') === $platformKey;
    }));
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .oms-platform-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .oms-platform-tab-btn {
            border: 1px solid #d0d5dd;
            border-radius: 999px;
            background: #fff;
            color: #344054;
            padding: 8px 16px;
            font-weight: 600;
            transition: all .2s ease;
        }

        .oms-platform-tab-btn.is-active {
            background: #2f5be6;
            border-color: #2f5be6;
            color: #fff;
            box-shadow: 0 10px 24px rgba(47, 91, 230, .2);
        }

        .oms-platform-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            margin-left: 8px;
            padding: 0 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .18);
            font-size: 12px;
        }

        .oms-platform-tab-btn:not(.is-active) .oms-platform-count {
            background: #eef2ff;
            color: #2f5be6;
        }

        .oms-platform-panel {
            display: none;
        }

        .oms-platform-panel.is-active {
            display: block;
        }
    </style>
</head>
<body>
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11 py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h2 class="mb-1"><?= htmlspecialchars($displayPageTitle) ?></h2>
                    <div class="text-muted">To Pack orders only. Scan token, scan link, or Order ID to move order to Shipped.</div>
                </div>
            </div>

            <div class="card p-3 mb-4">
                <form method="post" class="row g-3 align-items-end" id="waitingToPackScanForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="platform_section" id="waiting_to_pack_platform_section" value="<?= htmlspecialchars($activePlatform, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-12 col-md-9">
                        <label class="form-label" for="scan_value">Scan Input</label>
                        <input class="form-control" type="text" id="scan_value" name="scan_value" placeholder="Paste warehouse token, stock-out link, or Order ID">
                    </div>
                    <div class="col-12 col-md-3">
                        <button class="btn btn-primary w-100" type="submit" name="scanOrderBtn" value="1">Process Scan</button>
                    </div>
                    <div class="col-12 col-md-9">
                        <label class="form-label" for="scan_qr_image">QR Code Attachment</label>
                        <input class="form-control" type="file" id="scan_qr_image" accept="image/*">
                    </div>
                </form>
            </div>

            <div class="card p-3 mb-4">
                <div class="oms-platform-tabs" id="omsPlatformTabs">
                    <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                        <?php $platformCount = isset($waitingRowsByPlatform[$platformKey]) ? count($waitingRowsByPlatform[$platformKey]) : 0; ?>
                        <button
                            type="button"
                            class="oms-platform-tab-btn<?= $activePlatform === $platformKey ? ' is-active' : '' ?>"
                            data-platform-tab="<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($platformLabel) ?></span>
                            <span class="oms-platform-count"><?= (int) $platformCount ?></span>
                        </button>
                    <?php } ?>
                </div>
            </div>

            <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                <?php $panelRows = isset($waitingRowsByPlatform[$platformKey]) ? $waitingRowsByPlatform[$platformKey] : array(); ?>
                <div class="oms-platform-panel<?= $activePlatform === $platformKey ? ' is-active' : '' ?>" data-platform-panel="<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered waiting-to-pack-table-js" id="waiting_to_pack_table_<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                            <thead>
                                <tr>
                                    <th width="60">S/N</th>
                                    <th>Platform</th>
                                    <th>Order ID</th>
                                    <th>Stock Out Warehouse</th>
                                    <th>Customer</th>
                                    <th>Package</th>
                                    <th>Airbill No</th>
                                    <th>Link</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($panelRows)) { ?>
                                    <?php $rowNumber = 1; ?>
                                    <?php foreach ($panelRows as $row) { ?>
                                        <?php
                                        $rowPlatform = shopeeOmsGetOrderSourcePlatform($row, 'shopee');
                                        $rowSourceConfig = shopeeOmsGetOrderSourceConfig($rowPlatform);
                                        $packageSummary = shopeeOmsBuildOrderProductSummaryBySource($connect, $row, $rowSourceConfig);
                                        $orderCode = shopeeOmsGetOrderCodeValue($row, $rowSourceConfig);
                                        $tokenMapKey = $rowPlatform . '|' . (int) $row['id'];
                                        $tokenValue = isset($orderTokenMap[$tokenMapKey]) ? (string) $orderTokenMap[$tokenMapKey] : '';
                                        $tokenLink = $tokenValue !== '' ? $SITEURL . '/stock/warehouse_stock_in_scan.php?t=' . urlencode($tokenValue) : '';
                                        $stockOutWarehouseName = shopeeOmsResolveStockOutWarehouseName($connect, $row, $waitingToPackDefaultWarehouseId, $waitingToPackWarehouseNameMap);
                                        $airbillField = isset($rowSourceConfig['airbill_no_field']) ? (string) $rowSourceConfig['airbill_no_field'] : 'airbill_no';
                                        $customerDisplayHtml = htmlspecialchars(shopeeOmsGetOrderCustomerNameText($connect, $finance_connect, $row, $rowSourceConfig), ENT_QUOTES, 'UTF-8');
                                        if ($rowPlatform === 'shopee') {
                                            $customerDisplayHtml = customerLabelRenderShopeeBuyerCell($connect, $finance_connect, isset($row['buyer']) ? $row['buyer'] : '', '', $shopeeBuyerMetaMap);
                                        }
                                        $orderViewUrl = shopeeOmsGetOrderSourceViewUrl($rowSourceConfig, (int) $row['id']);
                                        ?>
                                        <tr>
                                            <td><?= $rowNumber++ ?></td>
                                            <td><?= htmlspecialchars(isset($platformTabs[$rowPlatform]) ? $platformTabs[$rowPlatform] : ucfirst($rowPlatform)) ?></td>
                                            <td>
                                                <?php if ($orderViewUrl !== '') { ?>
                                                    <a href="<?= htmlspecialchars($orderViewUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orderCode) ?></a>
                                                <?php } else { ?>
                                                    <?= htmlspecialchars($orderCode) ?>
                                                <?php } ?>
                                                <?php if (trim((string) ($row['redeem_source'] ?? '')) !== '') { ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-info text-dark"><?= htmlspecialchars((string) $row['redeem_source'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <small class="d-block text-muted"><?= htmlspecialchars((string) ($row['redeem_reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                                    </div>
                                                <?php } ?>
                                            </td>
                                            <td><?= htmlspecialchars($stockOutWarehouseName !== '' ? $stockOutWarehouseName : '-') ?></td>
                                            <td><?= $customerDisplayHtml ?></td>
                                            <td><?= htmlspecialchars(!empty($packageSummary['bundle_name']) ? $packageSummary['bundle_name'] : '-') ?></td>
                                            <td><?= htmlspecialchars((string) (isset($row[$airbillField]) && trim((string) $row[$airbillField]) !== '' ? $row[$airbillField] : '-')) ?></td>
                                            <td>
                                                <?php if ($tokenLink !== '') { ?>
                                                    <a href="<?= htmlspecialchars($tokenLink) ?>" target="_blank">Open Scan Link</a>
                                                <?php } else { ?>
                                                    <span class="text-muted">Will generate on scan</span>
                                                <?php } ?>
                                            </td>
                                            <td><?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($row['order_status']) ? $row['order_status'] : '')) ?></td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No To Pack orders found.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <script src="<?= $SITEURL ?>/header/js/jsQR.js"></script>
    <script>
        function showWaitingToPackStatusPopup(message) {
            const modelResult = document.createElement('div');
            modelResult.id = 'waiting-to-pack-status-modal';
            modelResult.className = 'modal fade';

            const dialog = document.createElement('div');
            dialog.className = 'modal-dialog modal-dialog-centered';
            dialog.style.fontFamily = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

            const content = document.createElement('div');
            content.className = 'modal-content';

            const body = document.createElement('div');
            body.className = 'modal-body fs-6 mt-3';

            const text = document.createElement('p');
            text.style.textAlign = 'center';
            text.style.fontWeight = 'bold';
            text.style.fontSize = '25px';
            text.textContent = String(message || '');
            body.appendChild(text);

            const footer = document.createElement('div');
            footer.className = 'modal-footer d-flex justify-content-center mt-n3';
            footer.style.borderTop = '0px';

            const continueButton = document.createElement('button');
            continueButton.id = 'waitingToPackContinueBtn';
            continueButton.type = 'button';
            continueButton.className = 'btn';
            continueButton.style.border = '1px solid #FF9B44';
            continueButton.style.backgroundColor = '#FFFFFF';
            continueButton.style.color = '#FF9B44';
            continueButton.style.setProperty('box-shadow', '0 0', 'important');
            continueButton.style.borderRadius = '24px';
            continueButton.style.textTransform = 'none';
            continueButton.textContent = 'Continue';
            footer.appendChild(continueButton);

            content.appendChild(body);
            content.appendChild(footer);
            dialog.appendChild(content);
            modelResult.appendChild(dialog);
            document.body.appendChild(modelResult);

            const popup = new bootstrap.Modal(modelResult, {
                keyboard: false,
                backdrop: 'static',
            });
            popup.show();

            modelResult.addEventListener('click', function (event) {
                if (event.target && event.target.id === 'waitingToPackContinueBtn') {
                    popup.hide();
                }
            });

            modelResult.addEventListener('hidden.bs.modal', function () {
                modelResult.remove();
            });
        }

        (function () {
            <?php if ($statusMessage !== '') { ?>
            showWaitingToPackStatusPopup(<?= json_encode($statusMessage) ?>);
            <?php } ?>

        

        document.querySelectorAll('.waiting-to-pack-table-js').forEach(function (tableElement) {
            var detailRowCount = getValidDataTableRowCount(tableElement);
            if (detailRowCount === 0) {
                return;
            }

                var table = new DataTable('#' + tableElement.id, {
                    paging: detailRowCount > 10,
                    info: detailRowCount > 10,
                    searching: true,
                    ordering: true,
                    lengthChange: detailRowCount > 10,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    autoWidth: false,
                    order: [],
                    columnDefs: [
                        { orderable: false, searchable: false, targets: [0, 7] }
                    ]
                });
                datatableAlignment(tableElement.id);

                table.on('draw', function () {
                    var pageInfo = table.page.info();
                    table.column(0, { page: 'current' }).nodes().each(function (cell, index) {
                        cell.innerHTML = pageInfo.start + index + 1;
                    });
                });
                table.draw(false);
            });

            var hiddenPlatformInput = document.getElementById('waiting_to_pack_platform_section');

            document.querySelectorAll('[data-platform-tab]').forEach(function (button) {
                button.addEventListener('click', function () {
                    activatePlatformTab(button.getAttribute('data-platform-tab') || 'all');
                });
            });

            activatePlatformTab(<?= json_encode($activePlatform) ?>);

            var form = document.getElementById('waitingToPackScanForm');
            var scanValueInput = document.getElementById('scan_value');
            var qrFileInput = document.getElementById('scan_qr_image');
            var submitButton = form ? form.querySelector('button[name="scanOrderBtn"]') : null;

            if (!form || !scanValueInput || !qrFileInput) {
                return;
            }

            function readFileAsImage(file) {
                return new Promise(function (resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function (event) {
                        var image = new Image();
                        image.onload = function () {
                            resolve(image);
                        };
                        image.onerror = function () {
                            reject(new Error('Unable to load QR image.'));
                        };
                        image.src = event.target.result;
                    };
                    reader.onerror = function () {
                        reject(new Error('Unable to read QR image.'));
                    };
                    reader.readAsDataURL(file);
                });
            }

            async function decodeQrFromFile(file) {
                if (typeof jsQR === 'undefined') {
                    throw new Error('jsQR is not loaded. Please refresh the page and try again.');
                }

                var image = await readFileAsImage(file);
                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');

                if (!ctx) {
                    throw new Error('Canvas is not available in this browser.');
                }

                canvas.width = image.width;
                canvas.height = image.height;
                ctx.drawImage(image, 0, 0);

                var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var code = jsQR(imageData.data, canvas.width, canvas.height);
                if (!code || !code.data) {
                    throw new Error('No QR code was detected in the uploaded image.');
                }

                return String(code.data);
            }

            form.addEventListener('submit', async function (event) {
                var scanValue = scanValueInput.value.trim();
                var qrFile = qrFileInput.files && qrFileInput.files.length ? qrFileInput.files[0] : null;

                if (scanValue !== '' || !qrFile) {
                    return;
                }

                event.preventDefault();

                try {
                    var decodedValue = await decodeQrFromFile(qrFile);
                    scanValueInput.value = decodedValue;
                    if (submitButton && typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitButton);
                    } else {
                        var hiddenSubmit = document.createElement('input');
                        hiddenSubmit.type = 'hidden';
                        hiddenSubmit.name = 'scanOrderBtn';
                        hiddenSubmit.value = '1';
                        form.appendChild(hiddenSubmit);
                        form.submit();
                    }
                } catch (error) {
                    showWaitingToPackStatusPopup(error && error.message ? error.message : 'Unable to scan the uploaded QR image.');
                }
            });
        })();
    </script>
</body>
</html>
