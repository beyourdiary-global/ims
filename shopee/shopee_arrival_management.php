<?php
$currentPagePin = 147;
$pageTitle = 'Shopee Arrival Management';
$disablePinGroupPageTitleSync = true;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pageTitle = 'Shopee Arrival Management';
$verifyAccess = checkPinByGroupId($connect, 147);
$legacyVerifyAccess = checkPinByGroupId($connect, 129);
$allOrdersAccess = checkPinByGroupId($connect, 130);
$canViewPage = isActionAllowed('View', $verifyAccess) || isActionAllowed('View', $legacyVerifyAccess) || isActionAllowed('View', $allOrdersAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Shopee Arrival Management."); location.replace("../dashboard.php");</script>';
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

shopeeOmsEnsureRealtimePostponedSync($connect, $finance_connect);

$statusMessage = '';
$statusClass = 'success';
$dateFrom = trim((string) input('date_from'));
$dateTo = trim((string) input('date_to'));
$currentStatusFilter = strtoupper(trim((string) input('current_status')));
$orderIdFilter = trim((string) input('order_id'));
$customerFilter = trim((string) input('customer'));
$estimatedDateValidation = validateEstimatedReceivedDate(date('Y-m-d', strtotime('+1 day')));
$arrivalAuditSafeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
$arrivalAuditEstimatedDate = function ($orderId, $assignedDate, $oldStatus, $newStatus) use ($pageTitle, $connect, $cdate, $ctime, $arrivalAuditSafeUserName) {
    $assignedDate = trim((string) $assignedDate);
    $oldStatus = trim((string) $oldStatus);
    $newStatus = trim((string) $newStatus);
    $changeSummary = 'estimated_received_date: ' . $assignedDate;
    if ($oldStatus !== '' && $newStatus !== '' && $oldStatus !== $newStatus) {
        $changeSummary = 'order_status: ' . $oldStatus . ' -> ' . $newStatus . ', ' . $changeSummary;
    }
    audit_log(array(
        'log_act' => 'edit',
        'page' => $pageTitle,
        'query_rec' => 'estimated_received_date=' . $assignedDate,
        'query_table' => SHOPEE_SG_ORDER_REQ,
        'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
        'changes' => $changeSummary,
        'uid' => USER_ID,
        'act_msg' => $arrivalAuditSafeUserName . " assigned the Estimate Received Date <b>" . $assignedDate . "</b> for Shopee order [ <b>ID = " . (int) $orderId . "</b> ].",
        'cdate' => $cdate,
        'ctime' => $ctime,
        'cby' => USER_ID,
        'connect' => $connect
    ));
};
$arrivalAuditStatusChange = function ($orderId, $oldStatus, $newStatus, $message) use ($pageTitle, $connect, $cdate, $ctime) {
    audit_log(array(
        'log_act' => 'edit',
        'page' => $pageTitle,
        'query_rec' => 'order_status=' . $newStatus,
        'query_table' => SHOPEE_SG_ORDER_REQ,
        'oldval' => trim((string) $oldStatus) !== '' ? ('order_status: ' . $oldStatus) : '',
        'changes' => 'order_status: ' . $newStatus,
        'uid' => USER_ID,
        'act_msg' => $message,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'cby' => USER_ID,
        'connect' => $connect
    ));
};
$arrivalAuditDelayRemarkUpdate = function ($orderId, $oldRemark, $newRemark, $queryText) use ($pageTitle, $connect, $cdate, $ctime) {
    $datafield = array('Delay Remark');
    $oldvalarr = array((string) $oldRemark);
    $chgvalarr = array((string) $newRemark);
    $log = array(
        'log_act' => 'Edit',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $queryText,
        'query_table' => SHOPEE_SG_ORDER_REQ,
        'page' => $pageTitle,
        'connect' => $connect,
        'oldval' => implodeWithComma($oldvalarr),
        'changes' => implodeWithComma($chgvalarr),
    );
    $log['act_msg'] = actMsgLog($orderId, $datafield, '', $oldvalarr, $chgvalarr, SHOPEE_SG_ORDER_REQ, 'Edit', '');
    audit_log($log);
};

if (post('bulkAssignBtn')) {
    $selectedOrderIds = isset($_POST['selected_order_ids']) && is_array($_POST['selected_order_ids']) ? $_POST['selected_order_ids'] : array();
    $estimatedDate = postSpaceFilter('bulk_estimated_received_date');
    $successCount = 0;
    $failedMessages = array();
    foreach ($selectedOrderIds as $selectedOrderId) {
        $assignResult = assignEstimatedReceivedDate($finance_connect, SHOPEE_SG_ORDER_REQ, (int) $selectedOrderId, $estimatedDate, USER_ID);
        if (!empty($assignResult['success'])) {
            $successCount++;
            $arrivalAuditEstimatedDate(
                (int) $selectedOrderId,
                isset($assignResult['date']) ? (string) $assignResult['date'] : '',
                isset($assignResult['old_status']) ? (string) $assignResult['old_status'] : '',
                isset($assignResult['new_status']) ? (string) $assignResult['new_status'] : ''
            );
        } else if (!empty($assignResult['message'])) {
            $failedMessages[] = '#' . (int) $selectedOrderId . ': ' . $assignResult['message'];
        }
    }

    if ($successCount > 0) {
        $statusMessage = $successCount . ' order(s) updated with Estimated Received Date.';
        if (!empty($failedMessages)) {
            $statusMessage .= ' Failed: ' . implode(' | ', $failedMessages);
            $statusClass = 'warning';
        }
    } else {
        $statusClass = 'danger';
        $statusMessage = !empty($failedMessages) ? implode(' | ', $failedMessages) : 'No order was updated.';
    }
}

if (post('saveEstimatedDateBtn')) {
    $orderId = (int) postSpaceFilter('estimated_received_order_id');
    $estimatedDate = postSpaceFilter('estimated_received_date');
    $assignResult = assignEstimatedReceivedDate($finance_connect, SHOPEE_SG_ORDER_REQ, $orderId, $estimatedDate, USER_ID);
    if (!empty($assignResult['success'])) {
        $arrivalAuditEstimatedDate(
            $orderId,
            isset($assignResult['date']) ? (string) $assignResult['date'] : '',
            isset($assignResult['old_status']) ? (string) $assignResult['old_status'] : '',
            isset($assignResult['new_status']) ? (string) $assignResult['new_status'] : ''
        );
    }
    $statusClass = !empty($assignResult['success']) ? 'success' : 'danger';
    $statusMessage = isset($assignResult['message']) ? (string) $assignResult['message'] : 'Unable to save Estimated Received Date.';
}

if (post('confirmReceiveBtn')) {
    $orderId = (int) postSpaceFilter('confirm_receive_id');
    $confirmResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'PR', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Parcel received confirmed by admin.',
    ));
    if (!empty($confirmResult['success'])) {
        $oldStatus = isset($confirmResult['old_status']) ? (string) $confirmResult['old_status'] : '';
        $newStatus = isset($confirmResult['new_status']) ? (string) $confirmResult['new_status'] : 'PR';
        $arrivalAuditStatusChange(
            $orderId,
            $oldStatus,
            $newStatus,
            $arrivalAuditSafeUserName . " updated Shopee order [ <b>ID = " . (int) $orderId . "</b> ] status from <b>" . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . "</b> to <b>" . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . "</b>."
        );
    }
    $statusClass = !empty($confirmResult['success']) ? 'success' : 'danger';
    $statusMessage = isset($confirmResult['message']) ? (string) $confirmResult['message'] : 'Unable to confirm parcel received.';
}

if (post('saveDelayBtn')) {
    $orderId = (int) postSpaceFilter('delay_order_id');
    $delayRemark = postSpaceFilter('delay_remark');
    $orderRow = shopeeOmsLoadOrder($finance_connect, $orderId);
    if (empty($orderRow)) {
        $statusClass = 'danger';
        $statusMessage = 'Order not found for delay remark.';
    } else if (!shopeeOmsPassesAssignmentScope($connect, $orderRow, USER_ID, USER_GROUP)) {
        $statusClass = 'danger';
        $statusMessage = 'You are not allowed to update this order.';
    } else {
        $currentStatus = shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '');
        if ($delayRemark !== '' && $currentStatus === 'WR') {
            $postponeResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'PD', array(
                'actor_user_id' => USER_ID,
                'actor_user_group_id' => USER_GROUP,
                'source_page' => $pageTitle,
                'remark' => 'Delay remark saved and order postponed.',
                'action' => 'postpone_order',
                'field_updates' => array(
                    'delay_remark' => $delayRemark,
                ),
                'allow_auto_follow_up' => false,
            ));
            if (!empty($postponeResult['success'])) {
                $oldStatus = isset($postponeResult['old_status']) ? (string) $postponeResult['old_status'] : '';
                $newStatus = isset($postponeResult['new_status']) ? (string) $postponeResult['new_status'] : 'PD';
                audit_log(array(
                    'log_act' => 'edit',
                    'page' => $pageTitle,
                    'query_rec' => 'order_status=' . $newStatus . ', delay_remark=' . $delayRemark,
                    'query_table' => SHOPEE_SG_ORDER_REQ,
                    'oldval' => 'order_status: ' . $oldStatus . ', delay_remark: ' . (string) (isset($orderRow['delay_remark']) ? $orderRow['delay_remark'] : ''),
                    'changes' => 'order_status: ' . $newStatus . ', delay_remark: ' . $delayRemark,
                    'uid' => USER_ID,
                    'act_msg' => $arrivalAuditSafeUserName . " updated delay remark and changed Shopee order [ <b>ID = " . (int) $orderId . "</b> ] status from <b>" . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . "</b> to <b>" . htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8') . "</b>.",
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'cby' => USER_ID,
                    'connect' => $connect
                ));
            }
            $statusClass = !empty($postponeResult['success']) ? 'success' : 'danger';
            $statusMessage = !empty($postponeResult['success'])
                ? 'Delay remark saved. Order status updated to Postponed.'
                : (isset($postponeResult['message']) ? (string) $postponeResult['message'] : 'Unable to postpone this order.');
        } else {
            $safeDelayRemark = mysqli_real_escape_string($finance_connect, $delayRemark);
            $safeUserId = mysqli_real_escape_string($finance_connect, USER_ID);
            $updateSql = "UPDATE `" . SHOPEE_SG_ORDER_REQ . "` SET `delay_remark` = '" . $safeDelayRemark . "', `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE id = " . $orderId . " LIMIT 1";
            if (mysqli_query($finance_connect, $updateSql)) {
                $arrivalAuditDelayRemarkUpdate(
                    $orderId,
                    isset($orderRow['delay_remark']) ? (string) $orderRow['delay_remark'] : '',
                    $delayRemark,
                    $updateSql
                );
                $statusClass = 'success';
                $statusMessage = 'Delay remark updated successfully.';
            } else {
                $statusClass = 'danger';
                $statusMessage = 'Unable to update delay remark.';
            }
        }
    }
}

$safeStatuses = array(
    "'WAERD'",
    "'WR'",
    "'PD'",
    "'AED'",
    "'Postponed'",
    "'Waiting Assign Estimate Received Date'",
    "'Waiting Receive'",
    "'Assigned Estimate Date'"
);
$orderRows = array();
$orderConditions = array(
    "status = 'A'",
    "order_status IN (" . implode(',', $safeStatuses) . ")"
);
if ($dateFrom !== '') {
    $orderConditions[] = "`date` >= '" . mysqli_real_escape_string($finance_connect, $dateFrom) . "'";
}
if ($dateTo !== '') {
    $orderConditions[] = "`date` <= '" . mysqli_real_escape_string($finance_connect, $dateTo) . "'";
}
if ($currentStatusFilter !== '' && in_array($currentStatusFilter, array('WAERD', 'WR', 'PD'), true)) {
    if ($currentStatusFilter === 'WAERD') {
        $orderConditions[] = "order_status IN ('WAERD', 'Waiting Assign Estimate Received Date')";
    } else if ($currentStatusFilter === 'WR') {
        $orderConditions[] = "order_status IN ('WR', 'AED', 'Waiting Receive', 'Assigned Estimate Date')";
    } else if ($currentStatusFilter === 'PD') {
        $orderConditions[] = "order_status IN ('PD', 'Postponed')";
    }
}
if ($orderIdFilter !== '') {
    $safeOrderIdFilter = mysqli_real_escape_string($finance_connect, $orderIdFilter);
    $orderConditions[] = "orderID LIKE '%" . $safeOrderIdFilter . "%'";
}
if ($customerFilter !== '') {
    $safeCustomerFilter = mysqli_real_escape_string($finance_connect, $customerFilter);
    $buyerSearchIds = array();
    $buyerSearchSql = "SELECT id FROM `" . SHOPEE_CUST_INFO . "` WHERE buyer_username LIKE '%" . $safeCustomerFilter . "%'";
    $buyerSearchRst = mysqli_query($finance_connect, $buyerSearchSql);
    if ($buyerSearchRst) {
        while ($buyerSearchRow = mysqli_fetch_assoc($buyerSearchRst)) {
            $buyerSearchId = isset($buyerSearchRow['id']) ? (int) $buyerSearchRow['id'] : 0;
            if ($buyerSearchId > 0) {
                $buyerSearchIds[] = $buyerSearchId;
            }
        }
    }

    $buyerSearchConditions = array("buyer LIKE '%" . $safeCustomerFilter . "%'");
    if (!empty($buyerSearchIds)) {
        $buyerSearchConditions[] = "buyer IN (" . implode(',', $buyerSearchIds) . ")";
    }
    $orderConditions[] = '(' . implode(' OR ', $buyerSearchConditions) . ')';
}
$orderSql = "SELECT * FROM `" . SHOPEE_SG_ORDER_REQ . "` WHERE " . implode(' AND ', $orderConditions) . " ORDER BY CASE WHEN order_status IN ('WAERD', 'Waiting Assign Estimate Received Date') THEN 1 WHEN order_status IN ('WR', 'AED', 'Waiting Receive', 'Assigned Estimate Date') THEN 2 WHEN order_status IN ('PD', 'Postponed') THEN 3 ELSE 4 END, estimated_received_date ASC, date DESC, time DESC, id DESC";
$orderRst = mysqli_query($finance_connect, $orderSql);
if ($orderRst) {
    while ($row = mysqli_fetch_assoc($orderRst)) {
        $orderRows[] = $row;
    }
}
$shopeeBuyerMetaMap = customerLabelGetShopeeCustomerMetaMap($connect, $finance_connect, array_map(function ($row) {
    return isset($row['buyer']) ? $row['buyer'] : '';
}, $orderRows));
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .shopee-arrival-stack {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .shopee-arrival-card {
            background: #ffffff;
            border: 1px solid #e7edf4;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
            padding: 18px 20px;
        }

        .shopee-arrival-subtitle {
            color: #667085;
        }

        .shopee-arrival-filter-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.75fr) minmax(0, 0.75fr) minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr);
            gap: 16px;
        }

        .shopee-arrival-bulk-grid {
            display: grid;
            grid-template-columns: minmax(0, 280px) auto;
            gap: 16px;
            align-items: end;
        }

        .shopee-arrival-filter-actions,
        .shopee-arrival-bulk-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
        }

        .shopee-arrival-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
        }

        .shopee-arrival-table {
            margin-bottom: 0;
        }

        .shopee-arrival-table th,
        .shopee-arrival-table td {
            vertical-align: middle;
        }

        .shopee-arrival-order-link {
            color: #2f5be6;
            text-decoration: none;
        }

        .shopee-arrival-order-link:hover {
            text-decoration: underline;
        }

        .shopee-arrival-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid transparent;
            white-space: normal;
            text-align: center;
            line-height: 1.35;
        }

        .shopee-arrival-status-badge-waerd {
            background: #eef4ff;
            border-color: #d9e6ff;
            color: #2f5be6;
            max-width: 220px;
        }

        .shopee-arrival-status-badge-wr {
            background: #ecfdf3;
            border-color: #c7f0d7;
            color: #1f8f4e;
        }

        .shopee-arrival-status-badge-pd {
            background: #fff4e8;
            border-color: #ffd8ae;
            color: #c26b00;
        }

        .shopee-arrival-estimated-cell {
            min-width: 190px;
        }

        .shopee-arrival-delay-field {
            min-width: 220px;
        }

        .shopee-arrival-empty-action {
            color: #98a2b3;
        }

        .shopee-arrival-action-cell {
            white-space: nowrap;
        }

        .shopee-arrival-action-cell .btn {
            white-space: nowrap;
        }

        .estimated-date-modal {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.45);
            padding: 16px;
        }

        .estimated-date-modal.is-open {
            display: flex;
        }

        .estimated-date-modal__dialog {
            width: 100%;
            max-width: 420px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
            padding: 20px;
        }

        .estimated-date-modal__close-btn,
        .estimated-date-modal__action-btn {
            text-transform: none !important;
        }

        @media (max-width: 1199px) {
            .shopee-arrival-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .shopee-arrival-card {
                padding: 16px;
            }

            .shopee-arrival-filter-grid,
            .shopee-arrival-bulk-grid {
                grid-template-columns: 1fr;
            }

            .shopee-arrival-filter-actions,
            .shopee-arrival-bulk-actions {
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
                <div class="shopee-arrival-subtitle">Manage orders in WAERD, Waiting Receive, and Postponed. Bulk update Estimated Received Date, confirm parcel received, and track delays.</div>
            </div>
        </div>

        <div class="shopee-arrival-stack">
            <div class="shopee-arrival-card">
                <form method="get">
                    <div class="shopee-arrival-filter-grid">
                        <div>
                            <label class="form-label" for="date_from">Date From</label>
                            <input class="form-control" type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="date_to">Date To</label>
                            <input class="form-control" type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="current_status">Current Status</label>
                            <select class="form-select" id="current_status" name="current_status">
                                <option value="">WAERD, Waiting Receive and Postponed</option>
                                <option value="WAERD" <?= $currentStatusFilter === 'WAERD' ? 'selected' : '' ?>>Waiting Assign Estimate Received Date</option>
                                <option value="WR" <?= $currentStatusFilter === 'WR' ? 'selected' : '' ?>>Waiting Receive</option>
                                <option value="PD" <?= $currentStatusFilter === 'PD' ? 'selected' : '' ?>>Postponed</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="order_id">Order ID</label>
                            <input class="form-control" type="text" id="order_id" name="order_id" value="<?= htmlspecialchars($orderIdFilter) ?>" placeholder="Search Order ID (optional)" autocomplete="off">
                        </div>
                        <div>
                            <label class="form-label" for="customer">Shopee Buyer Username</label>
                            <input class="form-control" type="text" id="customer" name="customer" value="<?= htmlspecialchars($customerFilter) ?>" placeholder="Search Shopee Buyer Username (optional)" autocomplete="off">
                        </div>
                    </div>
                    <div class="shopee-arrival-filter-actions">
                        <button class="btn btn-primary" type="submit">Apply Filter</button>
                        <a class="btn shopee-arrival-reset-btn" href="<?= htmlspecialchars($SITEURL . '/shopee/shopee_arrival_management.php') ?>">Reset</a>
                    </div>
                </form>
            </div>

            <form method="post" id="arrival_order_form">
            <div class="shopee-arrival-card">
                <h4 class="mb-3">Bulk Update Estimated Received Date</h4>
                    <div class="shopee-arrival-bulk-grid">
                        <div>
                            <label class="form-label" for="bulk_estimated_received_date">Estimated Received Date</label>
                            <input class="form-control" type="date" id="bulk_estimated_received_date" name="bulk_estimated_received_date" min="<?= htmlspecialchars($estimatedDateValidation['min_date']) ?>" max="<?= htmlspecialchars($estimatedDateValidation['max_date']) ?>">
                        </div>
                    <div class="shopee-arrival-bulk-actions">
                        <button class="btn btn-primary" type="submit" name="bulkAssignBtn" value="1">Apply to Selected</button>
                    </div>
                </div>
            </div>

            <div class="shopee-arrival-card">
                <h4 class="mb-3">Arrival Order List</h4>
                    <div class="table-responsive">
                    <table class="table table-striped table-bordered shopee-arrival-table" id="arrival_management_table">
                        <thead>
                            <tr>
                                <th width="60"><input type="checkbox" id="check_all_orders"></th>
                                <th width="60">S/N</th>
                                <th>Order ID</th>
                                <th>Action</th>
                                <th>Shopee Buyer Username</th>
                                <th>Current Status</th>
                                <th>Shipped Date</th>
                                <th>Estimated Received Date</th>
                                <th>Postpone / Delay Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($orderRows)) { ?>
                                <?php $rowNumber = 1; ?>
                                <?php foreach ($orderRows as $row) { ?>
                                    <?php
                                    $statusCode = shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '');
                                    $canAssign = shopeeOmsPassesAssignmentScope($connect, $row, USER_ID, USER_GROUP) && ($statusCode === 'WAERD' ? shopeeOmsHasTransitionPermission($connect, $statusCode, 'WR', USER_GROUP, $row, USER_ID) : true);
                                    $canConfirm = shopeeOmsHasTransitionPermission($connect, $statusCode, 'PR', USER_GROUP, $row, USER_ID);
                                    $shippedDate = trim((string) (isset($row['date']) ? $row['date'] : ''));
                                    $shippedTime = trim((string) (isset($row['time']) ? $row['time'] : ''));
                                    $shippedDisplay = $shippedDate !== '' ? $shippedDate . ($shippedTime !== '' ? ' ' . $shippedTime : '') : '-';
                                    $estimatedDate = trim((string) (isset($row['estimated_received_date']) ? $row['estimated_received_date'] : ''));
                                    $statusBadgeClass = $statusCode === 'WAERD'
                                        ? 'shopee-arrival-status-badge-waerd'
                                        : ($statusCode === 'PD' ? 'shopee-arrival-status-badge-pd' : 'shopee-arrival-status-badge-wr');
                                    $statusBadgeLabel = $statusCode === 'WAERD'
                                        ? 'Waiting Assign<br>Estimate Received Date'
                                        : ($statusCode === 'PD' ? 'Postponed' : 'Waiting Receive');
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($statusCode === 'WAERD' && $canAssign) { ?>
                                                <input type="checkbox" name="selected_order_ids[]" value="<?= (int) $row['id'] ?>">
                                            <?php } ?>
                                        </td>
                                        <td><?= $rowNumber++ ?></td>
                                        <td><a class="shopee-arrival-order-link" href="<?= $SITEURL ?>/shopee/shopee_order_req.php?id=<?= (int) $row['id'] ?>"><?= htmlspecialchars((string) $row['orderID']) ?></a></td>
                                        <td class="shopee-arrival-action-cell">
                                            <?php if ($statusCode === 'WAERD' && $canAssign) { ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-warning btn-assign-estimated-date"
                                                    data-order-id="<?= (int) $row['id'] ?>"
                                                    data-order-code="<?= htmlspecialchars((string) $row['orderID'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-min-date="<?= htmlspecialchars($estimatedDateValidation['min_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-max-date="<?= htmlspecialchars($estimatedDateValidation['max_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                            <?php } else if (in_array($statusCode, array('WR', 'PD'), true) && $canConfirm) { ?>
                                                <button class="btn btn-sm btn-success confirm-receive-btn" type="button" data-order-id="<?= (int) $row['id'] ?>">Confirm Received</button>
                                            <?php } else { ?>
                                                <span class="shopee-arrival-empty-action">No direct action</span>
                                            <?php } ?>
                                        </td>
                                        <td><?= customerLabelRenderShopeeBuyerCell($connect, $finance_connect, isset($row['buyer']) ? $row['buyer'] : '', '', $shopeeBuyerMetaMap) ?></td>
                                        <td><span class="shopee-arrival-status-badge <?= $statusBadgeClass ?>"><?= $statusBadgeLabel ?></span></td>
                                        <td><?= htmlspecialchars($shippedDisplay) ?></td>
                                        <td class="shopee-arrival-estimated-cell">
                                            <?php if ($statusCode === 'WAERD' && $canAssign) { ?>
                                                
                                            <?php } else { ?>
                                                <?= htmlspecialchars($estimatedDate !== '' ? $estimatedDate : '-') ?>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if (in_array($statusCode, array('WR', 'PD'), true)) { ?>
                                                <div class="d-flex flex-column gap-2 shopee-arrival-delay-field">
                                                    <textarea class="form-control form-control-sm delay-remark-field" name="delay_remark_display_<?= (int) $row['id'] ?>" rows="2"><?= htmlspecialchars((string) (isset($row['delay_remark']) ? $row['delay_remark'] : '')) ?></textarea>
                                                    <button class="btn btn-sm btn-outline-secondary save-delay-btn" type="button" data-order-id="<?= (int) $row['id'] ?>">Save Delay Remark</button>
                                                </div>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="9" class="text-center">No WAERD, Waiting Receive, or Postponed orders found.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    </div>

                    <input type="hidden" name="confirm_receive_id" id="table_confirm_receive_id" value="">
                    <input type="hidden" name="delay_order_id" id="table_delay_order_id" value="">
                    <input type="hidden" name="delay_remark" id="table_delay_remark" value="">
            </div>
            </form>
        </div>
        </div>
    </div>

    <div id="estimatedReceivedDateModal" class="estimated-date-modal" onclick="if (event.target === this) closeEstimatedReceivedDateModal();">
        <div class="estimated-date-modal__dialog">
            <form method="post">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <h5 class="mb-0" id="estimatedReceivedDateTitle">Assign Estimate Received Date</h5>
                    <button type="button" class="btn btn-sm btn-light px-2 estimated-date-modal__close-btn" onclick="closeEstimatedReceivedDateModal()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <input type="hidden" name="estimated_received_order_id" id="estimated_received_order_id" value="">
                <div class="mb-2">
                    <label class="form-label" for="estimated_received_date">Estimate Received Date</label>
                    <input type="date" class="form-control" name="estimated_received_date" id="estimated_received_date" min="<?= htmlspecialchars($estimatedDateValidation['min_date']) ?>" max="<?= htmlspecialchars($estimatedDateValidation['max_date']) ?>" required>
                </div>
                <div class="text-muted mb-4">
                    Choose a date from <?= htmlspecialchars($estimatedDateValidation['min_date']) ?> until <?= htmlspecialchars($estimatedDateValidation['max_date']) ?>.
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <button type="button" class="btn btn-outline-secondary estimated-date-modal__action-btn" onclick="closeEstimatedReceivedDateModal()">Cancel</button>
                    <button type="submit" name="saveEstimatedDateBtn" value="1" class="btn btn-primary estimated-date-modal__action-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showArrivalStatusPopup(message) {
        const modelResult = document.createElement('div');
        modelResult.id = 'arrival-status-modal';
        modelResult.className = 'modal fade';
        modelResult.innerHTML = `
            <div class="modal-dialog modal-dialog-centered" style="font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                <div class="modal-content">
                    <div class="modal-body fs-6 mt-3">
                        <p style="text-align:center; font-weight:bold; font-size:25px;">${message}</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px">
                        <button id="arrivalStatusContinueBtn" type="button" class="btn"
                            style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius:24px; text-transform:none;">Continue</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modelResult);

        const popup = new bootstrap.Modal(modelResult, {
            keyboard: false,
            backdrop: 'static',
        });
        popup.show();

        modelResult.addEventListener('click', function (event) {
            if (event.target && event.target.id === 'arrivalStatusContinueBtn') {
                popup.hide();
            }
        });

        modelResult.addEventListener('hidden.bs.modal', function () {
            modelResult.remove();
        });
    }

    function openEstimatedReceivedDateModal(orderId, orderCode, minDate, maxDate) {
        const modal = document.getElementById('estimatedReceivedDateModal');
        const title = document.getElementById('estimatedReceivedDateTitle');
        const orderIdInput = document.getElementById('estimated_received_order_id');
        const dateInput = document.getElementById('estimated_received_date');

        if (!modal || !orderIdInput || !dateInput) {
            return;
        }

        title.textContent = orderCode ? 'Assign Estimate Received Date for ' + orderCode : 'Assign Estimate Received Date';
        orderIdInput.value = orderId;
        dateInput.value = '';
        dateInput.min = minDate;
        dateInput.max = maxDate;
        modal.classList.add('is-open');
    }

    function closeEstimatedReceivedDateModal() {
        const modal = document.getElementById('estimatedReceivedDateModal');
        if (modal) {
            modal.classList.remove('is-open');
        }
    }

    (function () {
        <?php if ($statusMessage !== '') { ?>
        showArrivalStatusPopup(<?= json_encode($statusMessage) ?>);
        <?php } ?>

        var arrivalRowCount = <?= !empty($orderRows) ? count($orderRows) : 0 ?>;
        var arrivalManagementTable = null;
        if (arrivalRowCount > 0) {
            arrivalManagementTable = new DataTable('#arrival_management_table', {
                paging: arrivalRowCount > 10,
                info: arrivalRowCount > 10,
                searching: false,
                ordering: true,
                lengthChange: arrivalRowCount > 10,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                autoWidth: false,
                order: [],
                columnDefs: [
                    { orderable: false, searchable: false, targets: [0, 1, 3, 8] }
                ]
            });
            datatableAlignment('arrival_management_table');

            arrivalManagementTable.on('draw', function () {
                var pageInfo = arrivalManagementTable.page.info();
                arrivalManagementTable.column(1, { page: 'current' }).nodes().each(function (cell, index) {
                    cell.innerHTML = pageInfo.start + index + 1;
                });
            });
            arrivalManagementTable.draw(false);
        }

        var checkAll = document.getElementById('check_all_orders');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('input[name="selected_order_ids[]"]').forEach(function (checkbox) {
                    checkbox.checked = checkAll.checked;
                });
            });
        }

        var arrivalForm = document.getElementById('arrival_order_form');

        document.querySelectorAll('.confirm-receive-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!window.confirm('Confirm parcel received for this order?')) {
                    return;
                }
                document.getElementById('table_confirm_receive_id').value = button.getAttribute('data-order-id');
                var hiddenButton = document.createElement('input');
                hiddenButton.type = 'hidden';
                hiddenButton.name = 'confirmReceiveBtn';
                hiddenButton.value = '1';
                arrivalForm.appendChild(hiddenButton);
                arrivalForm.submit();
            });
        });

        document.querySelectorAll('.save-delay-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var orderId = button.getAttribute('data-order-id');
                var remarkField = document.querySelector('textarea[name="delay_remark_display_' + orderId + '"]');
                document.getElementById('table_delay_order_id').value = orderId;
                document.getElementById('table_delay_remark').value = remarkField ? remarkField.value : '';
                var hiddenButton = document.createElement('input');
                hiddenButton.type = 'hidden';
                hiddenButton.name = 'saveDelayBtn';
                hiddenButton.value = '1';
                arrivalForm.appendChild(hiddenButton);
                arrivalForm.submit();
            });
        });

        document.querySelectorAll('.btn-assign-estimated-date').forEach(function (button) {
            button.addEventListener('click', function () {
                openEstimatedReceivedDateModal(
                    button.getAttribute('data-order-id'),
                    button.getAttribute('data-order-code'),
                    button.getAttribute('data-min-date'),
                    button.getAttribute('data-max-date')
                );
            });
        });
    })();
    </script>
</body>
</html>
