<?php
$currentPagePin = 148;
$pageTitle = 'Daily Flow Report';
$displayPageTitle = 'Daily Flow Report';
$disablePinGroupPageTitleSync = true;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$reportAccess = checkPinByGroupId($connect, 148);
$canViewPage = isActionAllowed('View', $reportAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Daily Flow Report."); location.replace("../dashboard.php");</script>';
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

$selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : date('m');
$selectedYear = isset($_GET['year']) ? trim((string) $_GET['year']) : date('Y');
$selectedDate = trim((string) input('date'));
$currentYear = date('Y');
$selectedMonth = ($selectedMonth === '' || preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth)) ? $selectedMonth : date('m');
$selectedYear = ($selectedYear === '' || preg_match('/^\d{4}$/', $selectedYear)) ? $selectedYear : $currentYear;
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : '';
$dateFrom = '';
$dateTo = '';
if ($selectedMonth !== '' && $selectedYear !== '') {
    $monthStartTs = strtotime($selectedYear . '-' . $selectedMonth . '-01');
    if ($monthStartTs !== false) {
        $dateFrom = date('Y-m-01', $monthStartTs);
        $dateTo = date('Y-m-t', $monthStartTs);
    }
} else if ($selectedYear !== '') {
    $dateFrom = $selectedYear . '-01-01';
    $dateTo = $selectedYear . '-12-31';
}
$fromStatus = trim((string) input('from_status'));
$toStatus = trim((string) input('to_status'));
$orderCode = trim((string) input('order_id'));
$stockOutWarehouseId = shopeeOmsNormalizeWarehouseId(input('stock_out_warehouse_id'));
$flowReportWarehouseRows = shopeeOmsLoadActiveWarehouses($connect);

$platformTabs = array('all' => 'All');
foreach (shopeeOmsGetOrderSourceConfigs() as $platformKey => $platformConfig) {
    $platformTabs[$platformKey] = isset($platformConfig['label']) ? (string) $platformConfig['label'] : ucfirst((string) $platformKey);
}

$requestedPlatform = trim((string) input('platform'));
$activePlatform = shopeeOmsNormalizePlatformKey($requestedPlatform, true);
if ($activePlatform === '') {
    $activePlatform = 'all';
}

$reportData = shopeeOmsGetDailyFlowReport($connect, $finance_connect, $dateFrom, $dateTo, $fromStatus, $toStatus, $orderCode, $stockOutWarehouseId, '', $selectedDate, $selectedMonth, $selectedYear);
$summaryRows = isset($reportData['summary']) ? $reportData['summary'] : array();
$detailRows = isset($reportData['details']) ? $reportData['details'] : array();
$statusOptions = shopeeOmsGetEditableStatusOptions();

$summaryRowsByPlatform = array('all' => $summaryRows);
$detailRowsByPlatform = array('all' => $detailRows);
foreach ($platformTabs as $platformKey => $platformLabel) {
    if ($platformKey === 'all') {
        continue;
    }

    $summaryRowsByPlatform[$platformKey] = array_values(array_filter($summaryRows, function ($row) use ($platformKey) {
        return isset($row['platform']) && (string) $row['platform'] === $platformKey;
    }));

    $detailRowsByPlatform[$platformKey] = array();
    foreach ($detailRows as $transitionKey => $transitionDetails) {
        if (strpos((string) $transitionKey, $platformKey . '__') !== 0) {
            continue;
        }
        $detailRowsByPlatform[$platformKey][$transitionKey] = $transitionDetails;
    }
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

        .shopee-flow-report-stack {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .shopee-flow-report-card {
            background: #ffffff;
            border: 1px solid #e7edf4;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
            padding: 18px 20px;
        }

        .shopee-flow-report-subtitle {
            color: #667085;
        }

        .shopee-flow-report-date-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 220px)) minmax(0, 220px);
            gap: 16px;
        }

        .shopee-flow-report-date-filter-reset {
            display: flex;
            align-items: flex-end;
        }

        .shopee-flow-report-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
            border-radius: 999px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .shopee-flow-report-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .shopee-flow-report-table {
            margin-bottom: 0;
        }

        .shopee-flow-report-table th,
        .shopee-flow-report-table td {
            vertical-align: middle;
        }

        .shopee-flow-report-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            padding: 4px 10px;
            border-radius: 8px;
            background: #eef4ff;
            color: #2f5be6;
            border: 1px solid #d9e6ff;
        }

        .shopee-flow-report-action-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2f5be6;
            text-decoration: none;
        }

        .shopee-flow-report-action-link:hover {
            text-decoration: none;
        }

        .shopee-flow-report-detail-note {
            color: #667085;
            margin-top: -8px;
        }

        .shopee-flow-report-accordion {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .shopee-flow-report-accordion-item {
            border: 1px solid #d9e3f0;
            border-radius: 10px;
            overflow: hidden;
            background: #ffffff;
        }

        .shopee-flow-report-accordion-summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #ffffff;
        }

        .shopee-flow-report-accordion-summary::-webkit-details-marker {
            display: none;
        }

        .shopee-flow-report-accordion-item[open] .shopee-flow-report-accordion-summary {
            background: #f5f9ff;
            border-bottom: 1px solid #d9e3f0;
        }

        .shopee-flow-report-chevron {
            transition: transform 0.2s ease;
            color: #2f5be6;
        }

        .shopee-flow-report-accordion-item[open] .shopee-flow-report-chevron {
            transform: rotate(90deg);
        }

        .shopee-flow-report-accordion-body {
            padding: 12px 16px 16px;
        }

        .shopee-flow-report-order-link {
            color: #2f5be6;
            text-decoration: none;
        }

        .shopee-flow-report-order-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 1199px) {
            .shopee-flow-report-date-filter-grid,
            .shopee-flow-report-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .shopee-flow-report-card {
                padding: 16px;
            }

            .shopee-flow-report-date-filter-grid,
            .shopee-flow-report-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11 py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h2 class="mb-1"><?= htmlspecialchars($displayPageTitle) ?></h2>
                    <div class="shopee-flow-report-subtitle">Status transition summary for the selected date range across all supported platforms.</div>
                </div>
            </div>

            <div class="shopee-flow-report-stack">
                <div class="shopee-flow-report-card">
                    <form method="get" id="flow_report_filter_form">
                        <input type="hidden" name="platform" id="flow_report_platform_query" value="<?= htmlspecialchars($activePlatform, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="shopee-flow-report-date-filter-grid">
                            <div>
                                <label class="form-label" for="flow_report_date">Date</label>
                                <input class="form-control" type="date" id="flow_report_date" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="flow_report_month">Month</label>
                                <select class="form-select" id="flow_report_month" name="month">
                                    <option value="">Select Month</option>
                                    <?php for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) { ?>
                                        <?php
                                        $monthValue = str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT);
                                        $monthLabel = date('F', mktime(0, 0, 0, $monthNumber, 1));
                                        ?>
                                        <option value="<?= htmlspecialchars($monthValue) ?>" <?= $monthValue === $selectedMonth ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="flow_report_year">Year</label>
                                <select class="form-select" id="flow_report_year" name="year">
                                    <option value="">Select Year</option>
                                    <?php for ($yearValue = (int) $currentYear; $yearValue >= ((int) $currentYear - 5); $yearValue--) { ?>
                                        <option value="<?= htmlspecialchars((string) $yearValue) ?>" <?= (string) $yearValue === $selectedYear ? 'selected' : '' ?>><?= htmlspecialchars((string) $yearValue) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="shopee-flow-report-date-filter-reset">
                                <a class="btn btn-outline-secondary w-100 shopee-flow-report-reset-btn" href="<?= htmlspecialchars($SITEURL . '/finance/flow_report.php') ?>">Reset Filters</a>
                            </div>
                        </div>
                        <div class="shopee-flow-report-filter-grid">
                            <div>
                                <label class="form-label" for="from_status">From Status</label>
                                <select class="form-select" id="from_status" name="from_status">
                                    <option value="">All</option>
                                    <?php foreach ($statusOptions as $statusCode => $statusLabel) { ?>
                                        <option value="<?= htmlspecialchars($statusCode) ?>" <?= $fromStatus === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="to_status">To Status</label>
                                <select class="form-select" id="to_status" name="to_status">
                                    <option value="">All</option>
                                    <?php foreach ($statusOptions as $statusCode => $statusLabel) { ?>
                                        <option value="<?= htmlspecialchars($statusCode) ?>" <?= $toStatus === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="order_id">Order ID</label>
                                <input class="form-control" type="text" id="order_id" name="order_id" value="<?= htmlspecialchars($orderCode) ?>" placeholder="Search Order ID (optional)" autocomplete="off">
                            </div>
                            <div>
                                <label class="form-label" for="stock_out_warehouse_id">Stock Out Warehouse</label>
                                <select class="form-select" id="stock_out_warehouse_id" name="stock_out_warehouse_id">
                                    <option value="">All</option>
                                    <?php foreach ($flowReportWarehouseRows as $warehouseRow) { ?>
                                        <?php $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0; ?>
                                        <option value="<?= $warehouseId ?>" <?= $stockOutWarehouseId === $warehouseId ? 'selected' : '' ?>><?= htmlspecialchars((string) $warehouseRow['name']) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="shopee-flow-report-card">
                    <div class="oms-platform-tabs" id="omsPlatformTabs">
                        <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                            <?php $platformCount = isset($summaryRowsByPlatform[$platformKey]) ? count($summaryRowsByPlatform[$platformKey]) : 0; ?>
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
                    <?php
                    $platformSummaryRows = isset($summaryRowsByPlatform[$platformKey]) ? $summaryRowsByPlatform[$platformKey] : array();
                    $platformDetailRows = isset($detailRowsByPlatform[$platformKey]) ? $detailRowsByPlatform[$platformKey] : array();
                    ?>
                    <div class="oms-platform-panel<?= $activePlatform === $platformKey ? ' is-active' : '' ?>" data-platform-panel="<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="shopee-flow-report-card mb-4">
                            <h4 class="mb-3">Transition Summary</h4>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered shopee-flow-report-table flow-report-summary-table-js" id="flow_report_summary_table_<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                                    <thead>
                                        <tr>
                                            <th>Platform</th>
                                            <th>From Status</th>
                                            <th>To Status</th>
                                            <th>Total Count</th>
                                            <th>Last Transition Time</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($platformSummaryRows)) { ?>
                                            <?php foreach ($platformSummaryRows as $summaryRow) { ?>
                                                <?php $transitionKey = isset($summaryRow['transition_key']) ? (string) $summaryRow['transition_key'] : ''; ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) (isset($summaryRow['platform_label']) ? $summaryRow['platform_label'] : '')) ?></td>
                                                    <td><?= htmlspecialchars((string) $summaryRow['from_label']) ?></td>
                                                    <td><?= htmlspecialchars((string) $summaryRow['to_label']) ?></td>
                                                    <td><span class="shopee-flow-report-count-badge"><?= (int) $summaryRow['total_count'] ?></span></td>
                                                    <td><?= htmlspecialchars((string) (isset($summaryRow['last_transition_time']) ? $summaryRow['last_transition_time'] : '')) ?></td>
                                                    <td>
                                                        <a class="shopee-flow-report-action-link" href="#transition-detail-<?= htmlspecialchars($transitionKey, ENT_QUOTES, 'UTF-8') ?>" data-transition-target="<?= htmlspecialchars($transitionKey, ENT_QUOTES, 'UTF-8') ?>">
                                                            <span>View Details</span>
                                                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No transition found for the selected filters.</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="shopee-flow-report-card">
                            <h4 class="mb-3">Order Details By Transition</h4>
                            <div class="shopee-flow-report-detail-note">Click a transition to view the orders that moved through it.</div>
                            <?php if (!empty($platformSummaryRows)) { ?>
                                <div class="shopee-flow-report-accordion mt-3">
                                    <?php foreach ($platformSummaryRows as $summaryIndex => $summaryRow) { ?>
                                        <?php
                                        $transitionKey = isset($summaryRow['transition_key']) ? (string) $summaryRow['transition_key'] : '';
                                        $transitionDetails = isset($platformDetailRows[$transitionKey]) && is_array($platformDetailRows[$transitionKey]) ? $platformDetailRows[$transitionKey] : array();
                                        $accordionId = 'transition-detail-' . $transitionKey;
                                        ?>
                                        <details class="shopee-flow-report-accordion-item" id="<?= htmlspecialchars($accordionId, ENT_QUOTES, 'UTF-8') ?>" <?= $summaryIndex === 0 ? 'open' : '' ?>>
                                            <summary class="shopee-flow-report-accordion-summary">
                                                <i class="fa-solid fa-chevron-right fa-xs shopee-flow-report-chevron"></i>
                                                <span><?= htmlspecialchars((string) $summaryRow['platform_label']) ?>: <?= htmlspecialchars((string) $summaryRow['from_label']) ?> -> <?= htmlspecialchars((string) $summaryRow['to_label']) ?> (<?= count($transitionDetails) ?> <?= count($transitionDetails) === 1 ? 'order' : 'orders' ?>)</span>
                                            </summary>
                                            <div class="shopee-flow-report-accordion-body">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-bordered shopee-flow-report-table shopee-flow-report-detail-table">
                                                        <thead>
                                                            <tr>
                                                                <th width="60">S/N</th>
                                                                <th>Platform</th>
                                                                <th>Order ID</th>
                                                                <th>Transition Time</th>
                                                                <th>User</th>
                                                                <th>Remark</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($transitionDetails)) { ?>
                                                                <?php $rowNumber = 1; ?>
                                                                <?php foreach ($transitionDetails as $detailRow) { ?>
                                                                    <?php
                                                                    $userDisplayName = commonResolveUserDisplayName(
                                                                        $connect,
                                                                        isset($detailRow['user_id']) ? (string) $detailRow['user_id'] : 'SYSTEM'
                                                                    );
                                                                    if (trim((string) $userDisplayName) === '') {
                                                                        $userDisplayName = 'SYSTEM';
                                                                    }
                                                                    ?>
                                                                    <tr>
                                                                        <td><?= $rowNumber++ ?></td>
                                                                        <td><?= htmlspecialchars((string) (isset($detailRow['platform_label']) ? $detailRow['platform_label'] : '')) ?></td>
                                                                        <td>
                                                                            <?php if (!empty($detailRow['order_view_url'])) { ?>
                                                                                <a class="shopee-flow-report-order-link" href="<?= htmlspecialchars((string) $detailRow['order_view_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $detailRow['order_code']) ?></a>
                                                                            <?php } else { ?>
                                                                                <?= htmlspecialchars((string) $detailRow['order_code']) ?>
                                                                            <?php } ?>
                                                                        </td>
                                                                        <td><?= htmlspecialchars((string) (isset($detailRow['transition_at']) ? $detailRow['transition_at'] : '')) ?></td>
                                                                        <td>
                                                                            <div class="d-flex align-items-center gap-2">
                                                                                <span><?= htmlspecialchars((string) $userDisplayName) ?></span>
                                                                                <?= shopeeOmsRenderUserGroupBadge($connect, isset($detailRow['user_group_id']) ? (int) $detailRow['user_group_id'] : 0) ?>
                                                                            </div>
                                                                        </td>
                                                                        <td><?= nl2br(htmlspecialchars((string) (isset($detailRow['remark']) ? $detailRow['remark'] : ''))) ?></td>
                                                                    </tr>
                                                                <?php } ?>
                                                            <?php } else { ?>
                                                                <tr>
                                                                    <td colspan="6" class="text-center">No order detail found for this transition.</td>
                                                                </tr>
                                                            <?php } ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </details>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-light border mb-0 mt-3">No report details found.</div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <script>
        var flowReportFilterForm = document.getElementById('flow_report_filter_form');
        if (flowReportFilterForm) {
            document.querySelectorAll('#flow_report_filter_form select, #flow_report_filter_form input[type="date"]').forEach(function (filterElement) {
                filterElement.addEventListener('change', function () {
                    flowReportFilterForm.submit();
                });
            });

            var orderIdInput = document.getElementById('order_id');
            if (orderIdInput) {
                orderIdInput.addEventListener('change', function () {
                    flowReportFilterForm.submit();
                });
                orderIdInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        flowReportFilterForm.submit();
                    }
                });
            }
        }

        function getValidDataTableRowCount(tableElement) {
            var headerCount = tableElement.querySelectorAll('thead th').length;
            var validRows = 0;
            tableElement.querySelectorAll('tbody tr').forEach(function (rowElement) {
                var cellCount = rowElement.querySelectorAll('td, th').length;
                var hasColspan = rowElement.querySelector('[colspan]');
                if (!hasColspan && cellCount === headerCount) {
                    validRows += 1;
                }
            });
            return validRows;
        }

        document.querySelectorAll('.flow-report-summary-table-js').forEach(function (tableElement) {
            var rowCount = getValidDataTableRowCount(tableElement);
            if (rowCount === 0) {
                return;
            }

            new DataTable('#' + tableElement.id, {
                paging: rowCount > 10,
                info: rowCount > 10,
                searching: true,
                ordering: true,
                lengthChange: rowCount > 10,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                autoWidth: false,
                order: [],
                columnDefs: [
                    { orderable: false, searchable: false, targets: [5] }
                ]
            });
            datatableAlignment(tableElement.id);
        });

        document.querySelectorAll('.shopee-flow-report-detail-table').forEach(function (tableElement, index) {
            if (!tableElement.id) {
                tableElement.id = 'shopee_flow_report_detail_table_' + index;
            }

            var detailRowCount = getValidDataTableRowCount(tableElement);
            if (detailRowCount === 0) {
                return;
            }

            var detailTable = new DataTable('#' + tableElement.id, {
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
                    { orderable: false, searchable: false, targets: [0] }
                ]
            });
            datatableAlignment(tableElement.id);

            detailTable.on('draw', function () {
                var pageInfo = detailTable.page.info();
                detailTable.column(0, { page: 'current' }).nodes().each(function (cell, drawIndex) {
                    cell.innerHTML = pageInfo.start + drawIndex + 1;
                });
            });
            detailTable.draw(false);
        });

        var hiddenPlatformInput = document.getElementById('flow_report_platform_query');
        function activatePlatformTab(platformKey) {
            document.querySelectorAll('[data-platform-tab]').forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-platform-tab') === platformKey);
            });
            document.querySelectorAll('[data-platform-panel]').forEach(function (panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-platform-panel') === platformKey);
            });
            if (hiddenPlatformInput) {
                hiddenPlatformInput.value = platformKey;
            }
        }

        document.querySelectorAll('[data-platform-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                activatePlatformTab(button.getAttribute('data-platform-tab') || 'all');
            });
        });

        activatePlatformTab(<?= json_encode($activePlatform) ?>);

        document.querySelectorAll('[data-transition-target]').forEach(function (link) {
            link.addEventListener('click', function () {
                var transitionKey = link.getAttribute('data-transition-target');
                var target = document.getElementById('transition-detail-' + transitionKey);
                if (!target) {
                    return;
                }

                target.open = true;
                window.setTimeout(function () {
                    $(target).find('.shopee-flow-report-detail-table').each(function () {
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable(this)) {
                            $(this).DataTable().columns.adjust().draw(false);
                        }
                    });
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 120);
            });
        });
    </script>
</body>
</html>
