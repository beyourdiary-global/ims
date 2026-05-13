<?php
$currentPagePin = 148;
$pageTitle = 'Shopee Daily Flow Report';
$disablePinGroupPageTitleSync = true;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pageTitle = 'Shopee Daily Flow Report';
$reportAccess = checkPinByGroupId($connect, 148);
$canViewPage = isActionAllowed('View', $reportAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Shopee Daily Flow Report."); location.replace("../dashboard.php");</script>';
    exit;
}

$defaultDateFrom = date('Y-m-d', strtotime('-1 day'));
$defaultDateTo = date('Y-m-d');
$dateFrom = trim((string) input('date_from')) !== '' ? trim((string) input('date_from')) : $defaultDateFrom;
$dateTo = trim((string) input('date_to')) !== '' ? trim((string) input('date_to')) : $defaultDateTo;
$fromStatus = trim((string) input('from_status'));
$toStatus = trim((string) input('to_status'));
$orderCode = trim((string) input('order_id'));

$reportData = shopeeOmsGetDailyFlowReport($finance_connect, $dateFrom, $dateTo, $fromStatus, $toStatus, $orderCode);
$summaryRows = isset($reportData['summary']) ? $reportData['summary'] : array();
$detailRows = isset($reportData['details']) ? $reportData['details'] : array();
$statusOptions = shopeeOmsGetEditableStatusOptions();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
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

        .shopee-flow-report-filter-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
        }

        .shopee-flow-report-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
        }

        .shopee-flow-report-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
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
            .shopee-flow-report-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .shopee-flow-report-card {
                padding: 16px;
            }

            .shopee-flow-report-filter-grid {
                grid-template-columns: 1fr;
            }

            .shopee-flow-report-filter-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($pageTitle) ?></h2>
                <div class="shopee-flow-report-subtitle">Status transition summary for the selected date range. Order IDs open the specific Shopee order detail page.</div>
            </div>
        </div>

        <div class="shopee-flow-report-stack">
            <div class="shopee-flow-report-card">
                <form method="get">
                    <div class="shopee-flow-report-filter-grid">
                        <div>
                            <label class="form-label" for="date_from">Date From</label>
                            <input class="form-control" type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="date_to">Date To</label>
                            <input class="form-control" type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
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
                    </div>
                    <div class="shopee-flow-report-filter-actions">
                        <button class="btn btn-primary" type="submit">Apply Filter</button>
                        <a class="btn shopee-flow-report-reset-btn" href="<?= htmlspecialchars($SITEURL . '/shopee/shopee_flow_report.php') ?>">Reset</a>
                    </div>
                </form>
            </div>

            <div class="shopee-flow-report-card">
                <h4 class="mb-3">Transition Summary</h4>
                <div class="table-responsive">
                    <table class="table table-bordered shopee-flow-report-table">
                        <thead>
                            <tr>
                                <th>From Status</th>
                                <th>To Status</th>
                                <th>Total Count</th>
                                <th>Last Transition Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($summaryRows)) { ?>
                                <?php foreach ($summaryRows as $summaryIndex => $summaryRow) { ?>
                                    <?php $transitionKey = isset($summaryRow['transition_key']) ? (string) $summaryRow['transition_key'] : ''; ?>
                                    <tr>
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
                                    <td colspan="5" class="text-center">No transition found for the selected filters.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="shopee-flow-report-card">
                <h4 class="mb-3">Order Details By Transition</h4>
                <div class="shopee-flow-report-detail-note">Click a transition to view the orders that moved through it.</div>
                <?php if (!empty($summaryRows)) { ?>
                    <div class="shopee-flow-report-accordion mt-3">
                        <?php foreach ($summaryRows as $summaryIndex => $summaryRow) { ?>
                            <?php
                            $transitionKey = isset($summaryRow['transition_key']) ? (string) $summaryRow['transition_key'] : '';
                            $transitionDetails = isset($detailRows[$transitionKey]) && is_array($detailRows[$transitionKey]) ? $detailRows[$transitionKey] : array();
                            $accordionId = 'transition-detail-' . $transitionKey;
                            ?>
                            <details class="shopee-flow-report-accordion-item" id="<?= htmlspecialchars($accordionId, ENT_QUOTES, 'UTF-8') ?>" <?= $summaryIndex === 0 ? 'open' : '' ?>>
                                <summary class="shopee-flow-report-accordion-summary">
                                    <i class="fa-solid fa-chevron-right fa-xs shopee-flow-report-chevron"></i>
                                    <span><?= htmlspecialchars((string) $summaryRow['from_label']) ?> -> <?= htmlspecialchars((string) $summaryRow['to_label']) ?> (<?= count($transitionDetails) ?> <?= count($transitionDetails) === 1 ? 'order' : 'orders' ?>)</span>
                                </summary>
                                <div class="shopee-flow-report-accordion-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered shopee-flow-report-table">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Transition Time</th>
                                                    <th>User</th>
                                                    <th>Remark</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($transitionDetails)) { ?>
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
                                                            <td><a class="shopee-flow-report-order-link" href="<?= $SITEURL ?>/shopee/shopee_order_req.php?id=<?= (int) $detailRow['order_id'] ?>"><?= htmlspecialchars((string) $detailRow['order_code']) ?></a></td>
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
                                                        <td colspan="4" class="text-center">No order detail found for this transition.</td>
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
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-transition-target]').forEach(function (link) {
            link.addEventListener('click', function () {
                var transitionKey = link.getAttribute('data-transition-target');
                var target = document.getElementById('transition-detail-' + transitionKey);
                if (!target) {
                    return;
                }

                target.open = true;
                window.setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 50);
            });
        });
    </script>
</body>
</html>
