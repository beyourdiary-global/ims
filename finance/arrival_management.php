<?php
$currentPagePin = 147;
$pageTitle = 'Arrival Management';
$displayPageTitle = 'Arrival Management';
$disablePinGroupPageTitleSync = true;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/customer_follow_up_common.php';

$verifyAccess = checkPinByGroupId($connect, 147);
$legacyVerifyAccess = checkPinByGroupId($connect, 129);
$allOrdersAccess = checkPinByGroupId($connect, 130);
$canViewPage = isActionAllowed('View', $verifyAccess) || isActionAllowed('View', $legacyVerifyAccess) || isActionAllowed('View', $allOrdersAccess);
if (!$canViewPage) {
    renderNotificationScript('You do not have permission to view Arrival Management.', 'error', '../dashboard.php', 1200, true);
    exit;
}

if (empty($_SESSION['arrival_follow_up_csrf'])) {
    $_SESSION['arrival_follow_up_csrf'] = bin2hex(random_bytes(32));
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
$selectedMonth = trim((string) input('month'));
$selectedYear = trim((string) input('year'));
$selectedDate = trim((string) input('date'));
$currentYear = date('Y');
$selectedMonth = ($selectedMonth === '' || preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth)) ? $selectedMonth : date('m');
$selectedYear = ($selectedYear === '' || preg_match('/^\d{4}$/', $selectedYear)) ? $selectedYear : $currentYear;
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : '';
$currentStatusFilter = strtoupper(trim((string) input('current_status')));
$orderIdFilter = trim((string) input('order_id'));
$customerFilter = trim((string) input('customer'));
$estimatedDateValidation = validateEstimatedReceivedDate(date('Y-m-d', strtotime('+1 day')));
$arrivalAuditSafeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
$arrivalFollowUpShortcutOptions = customerFollowUpGetMessageShortcutOptions($connect);

$buildArrivalAuditStatusMessage = function ($platformLabel, $orderCode, $oldStatus, $newStatus) use ($arrivalAuditSafeUserName) {
    return $arrivalAuditSafeUserName . " updated " . htmlspecialchars((string) $platformLabel, ENT_QUOTES, 'UTF-8') . " order [ <b>ID = " . htmlspecialchars((string) $orderCode, ENT_QUOTES, 'UTF-8') . "</b> ] status from <b>" . htmlspecialchars((string) $oldStatus, ENT_QUOTES, 'UTF-8') . "</b> to <b>" . htmlspecialchars((string) $newStatus, ENT_QUOTES, 'UTF-8') . "</b>.";
};

$logArrivalEstimatedDate = function ($platformLabel, $tblName, $orderId, $orderCode, $assignedDate, $oldStatus, $newStatus) use ($pageTitle, $connect, $cdate, $ctime, $arrivalAuditSafeUserName) {
    $changeSummary = 'estimated_received_date: ' . $assignedDate;
    if ($oldStatus !== '' && $newStatus !== '' && $oldStatus !== $newStatus) {
        $changeSummary = 'order_status: ' . $oldStatus . ' -> ' . $newStatus . ', ' . $changeSummary;
    }
    audit_log(array(
        'log_act' => 'edit',
        'page' => $pageTitle,
        'query_rec' => 'estimated_received_date=' . $assignedDate,
        'query_table' => $tblName,
        'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
        'changes' => $changeSummary,
        'uid' => USER_ID,
        'act_msg' => $arrivalAuditSafeUserName . " assigned the Estimate Received Date <b>" . htmlspecialchars((string) $assignedDate, ENT_QUOTES, 'UTF-8') . "</b> for " . htmlspecialchars((string) $platformLabel, ENT_QUOTES, 'UTF-8') . " order [ <b>ID = " . htmlspecialchars((string) $orderCode, ENT_QUOTES, 'UTF-8') . "</b> ].",
        'cdate' => $cdate,
        'ctime' => $ctime,
        'cby' => USER_ID,
        'connect' => $connect
    ));
};

$logArrivalDelayRemarkUpdate = function ($tblName, $orderId, $oldRemark, $newRemark, $queryText) use ($pageTitle, $connect, $cdate, $ctime) {
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
        'query_table' => $tblName,
        'page' => $pageTitle,
        'connect' => $connect,
        'oldval' => implodeWithComma($oldvalarr),
        'changes' => implodeWithComma($chgvalarr),
    );
    $log['act_msg'] = actMsgLog($orderId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, 'Edit', '');
    audit_log($log);
};

$parseOrderPlatformRef = function ($value) {
    $value = trim((string) $value);
    if ($value === '' || strpos($value, ':') === false) {
        return array('', 0);
    }

    list($platformKey, $orderIdRaw) = explode(':', $value, 2);
    $platformKey = shopeeOmsNormalizePlatformKey($platformKey);
    $orderId = ctype_digit((string) $orderIdRaw) ? (int) $orderIdRaw : 0;
    return array($platformKey, $orderId);
};

if (post('bulkAssignBtn')) {
    $selectedOrderRefs = (array) post('selected_order_refs') ?: array();
    $estimatedDate = postSpaceFilter('bulk_estimated_received_date');
    $successCount = 0;
    $failedMessages = array();

    foreach ($selectedOrderRefs as $selectedOrderRef) {
        list($platformKey, $selectedOrderId) = $parseOrderPlatformRef($selectedOrderRef);
        if ($platformKey === '' || $selectedOrderId <= 0) {
            continue;
        }

        $sourceConfig = shopeeOmsGetOrderSourceConfig($platformKey);
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $sourceConfig);
        $assignmentResult = assignEstimatedReceivedDate($orderConnect, isset($sourceConfig['table']) ? $sourceConfig['table'] : '', $selectedOrderId, $estimatedDate, USER_ID);
        if (!empty($assignmentResult['success'])) {
            $successCount++;
            $orderRow = shopeeOmsLoadOrder($orderConnect, $selectedOrderId, $sourceConfig);
            $logArrivalEstimatedDate(
                isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platformKey),
                isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '',
                $selectedOrderId,
                shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
                isset($assignmentResult['date']) ? (string) $assignmentResult['date'] : '',
                isset($assignmentResult['old_status']) ? (string) $assignmentResult['old_status'] : '',
                isset($assignmentResult['new_status']) ? (string) $assignmentResult['new_status'] : ''
            );
        } else if (!empty($assignmentResult['message'])) {
            $failedMessages[] = $platformTabs[$platformKey] . ' #' . $selectedOrderId . ': ' . $assignmentResult['message'];
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
    $platformKey = shopeeOmsNormalizePlatformKey(postSpaceFilter('estimated_received_platform'));
    $orderId = (int) postSpaceFilter('estimated_received_order_id');
    $estimatedDate = postSpaceFilter('estimated_received_date');
    if ($platformKey === '' || $orderId <= 0) {
        $statusClass = 'danger';
        $statusMessage = 'Order platform is invalid for Estimated Received Date update.';
    } else {
        $sourceConfig = shopeeOmsGetOrderSourceConfig($platformKey);
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $sourceConfig);
        $assignResult = assignEstimatedReceivedDate($orderConnect, isset($sourceConfig['table']) ? $sourceConfig['table'] : '', $orderId, $estimatedDate, USER_ID);
        if (!empty($assignResult['success'])) {
            $orderRow = shopeeOmsLoadOrder($orderConnect, $orderId, $sourceConfig);
            $logArrivalEstimatedDate(
                isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platformKey),
                isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '',
                $orderId,
                shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
                isset($assignResult['date']) ? (string) $assignResult['date'] : '',
                isset($assignResult['old_status']) ? (string) $assignResult['old_status'] : '',
                isset($assignResult['new_status']) ? (string) $assignResult['new_status'] : ''
            );
        }
        $statusClass = !empty($assignResult['success']) ? 'success' : 'danger';
        $statusMessage = isset($assignResult['message']) ? (string) $assignResult['message'] : 'Unable to save Estimated Received Date.';
    }
}

if (post('confirmReceiveFollowUpBtn')) {
    $postedCsrfToken = (string) post('arrival_follow_up_csrf');
    if (!hash_equals((string) $_SESSION['arrival_follow_up_csrf'], $postedCsrfToken)) {
        $statusClass = 'danger';
        $statusMessage = 'Invalid follow-up session token. Please refresh and try again.';
    } else {
        $platformKey = shopeeOmsNormalizePlatformKey(postSpaceFilter('confirm_receive_platform'));
        $orderId = (int) postSpaceFilter('confirm_receive_id');
        $actorRemark = shopeeOmsBuildParcelReceivedRemark($connect, USER_ID, 'user');

        $submitResult = customerFollowUpSubmitReceivedOrderAndTransition(
            $connect,
            $finance_connect,
            $platformKey,
            $orderId,
            array(
                'message_shortcut_id' => postSpaceFilter('follow_up_message_shortcut_id'),
                'next_follow_up_date' => postSpaceFilter('follow_up_next_follow_up_date'),
                'contact_no' => postSpaceFilter('follow_up_contact_no'),
            ),
            isset($_FILES['follow_up_attachment']) ? $_FILES['follow_up_attachment'] : array(),
            USER_ID,
            USER_GROUP,
            array(
                'source_page' => $pageTitle,
                'transition_remark' => $actorRemark,
            )
        );

        if (!empty($submitResult['success'])) {
            $sourceConfig = isset($submitResult['source_config']) ? $submitResult['source_config'] : shopeeOmsGetOrderSourceConfig($platformKey);
            $orderRow = isset($submitResult['order_row_after']) && !empty($submitResult['order_row_after'])
                ? $submitResult['order_row_after']
                : shopeeOmsLoadOrder(shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $sourceConfig), $orderId, $sourceConfig);
            $transitionResult = isset($submitResult['transition_result']) ? $submitResult['transition_result'] : array();
            $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
            $newStatus = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : 'PR';

            audit_log(array(
                'log_act' => 'edit',
                'page' => $pageTitle,
                'query_rec' => 'order_status=' . $newStatus,
                'query_table' => isset($sourceConfig['table']) ? $sourceConfig['table'] : '',
                'oldval' => trim((string) $oldStatus) !== '' ? ('order_status: ' . $oldStatus) : '',
                'changes' => 'order_status: ' . $newStatus,
                'uid' => USER_ID,
                'act_msg' => $buildArrivalAuditStatusMessage(
                    isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platformKey),
                    shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
                    $oldStatus,
                    $newStatus
                ),
                'cdate' => $cdate,
                'ctime' => $ctime,
                'cby' => USER_ID,
                'connect' => $connect
            ));
        }

        $statusClass = !empty($submitResult['success']) ? 'success' : 'danger';
        $statusMessage = isset($submitResult['message']) ? (string) $submitResult['message'] : 'Unable to confirm parcel received.';
    }
}

if (post('confirmReceiveBtn')) {
    $statusClass = 'danger';
    $statusMessage = 'Please submit the Customer Follow-Up form before confirming parcel received.';
}

if (post('saveDelayBtn')) {
    $platformKey = shopeeOmsNormalizePlatformKey(postSpaceFilter('delay_platform'));
    $orderId = (int) postSpaceFilter('delay_order_id');
    $delayRemark = postSpaceFilter('delay_remark');
    if ($platformKey === '' || $orderId <= 0) {
        $statusClass = 'danger';
        $statusMessage = 'Order platform is invalid for Delay Remark.';
    } else {
        $sourceConfig = shopeeOmsGetOrderSourceConfig($platformKey);
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $sourceConfig);
        $orderRow = shopeeOmsLoadOrder($orderConnect, $orderId, $sourceConfig);
        $delayRemarkField = isset($sourceConfig['delay_remark_field']) && trim((string) $sourceConfig['delay_remark_field']) !== ''
            ? trim((string) $sourceConfig['delay_remark_field'])
            : 'delay_remark';
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
                        $delayRemarkField => $delayRemark,
                    ),
                    'allow_auto_follow_up' => false,
                    'platform' => $platformKey,
                ));
                if (!empty($postponeResult['success'])) {
                    $oldStatus = isset($postponeResult['old_status']) ? (string) $postponeResult['old_status'] : '';
                    $newStatus = isset($postponeResult['new_status']) ? (string) $postponeResult['new_status'] : 'PD';
                    audit_log(array(
                        'log_act' => 'edit',
                        'page' => $pageTitle,
                        'query_rec' => 'order_status=' . $newStatus . ', ' . $delayRemarkField . '=' . $delayRemark,
                        'query_table' => isset($sourceConfig['table']) ? $sourceConfig['table'] : '',
                        'oldval' => 'order_status: ' . $oldStatus . ', ' . $delayRemarkField . ': ' . (string) (isset($orderRow[$delayRemarkField]) ? $orderRow[$delayRemarkField] : ''),
                        'changes' => 'order_status: ' . $newStatus . ', ' . $delayRemarkField . ': ' . $delayRemark,
                        'uid' => USER_ID,
                        'act_msg' => $buildArrivalAuditStatusMessage(
                            isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platformKey),
                            shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
                            $oldStatus,
                            $newStatus
                        ),
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
                $safeDelayRemark = mysqli_real_escape_string($orderConnect, $delayRemark);
                $safeUserId = mysqli_real_escape_string($orderConnect, USER_ID);
                $updateSql = "UPDATE `" . $sourceConfig['table'] . "` SET `" . $delayRemarkField . "` = '" . $safeDelayRemark . "', `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE id = " . $orderId . " LIMIT 1";
                if (mysqli_query($orderConnect, $updateSql)) {
                    $logArrivalDelayRemarkUpdate(
                        isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '',
                        $orderId,
                        isset($orderRow[$delayRemarkField]) ? (string) $orderRow[$delayRemarkField] : '',
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
}

$arrivalRows = array();
foreach (shopeeOmsGetOrderSourceConfigs() as $platformKey => $platformConfig) {
    $orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $platformConfig);
    if (!($orderConnect instanceof mysqli)) {
        continue;
    }

    $orderConditions = array(
        "status = 'A'",
        shopeeOmsBuildOrderStatusInCondition($orderConnect, 'order_status', array('WAERD', 'WR', 'PD')),
    );
    if ($currentStatusFilter !== '' && in_array($currentStatusFilter, array('WAERD', 'WR', 'PD'), true)) {
        $orderConditions[] = shopeeOmsBuildOrderStatusInCondition($orderConnect, 'order_status', array($currentStatusFilter));
    }

    $dateField = isset($platformConfig['date_field']) && trim((string) $platformConfig['date_field']) !== ''
        ? trim((string) $platformConfig['date_field'])
        : 'create_date';
    if ($selectedDate !== '') {
        $orderConditions[] = "DATE(`" . $dateField . "`) = '" . mysqli_real_escape_string($orderConnect, $selectedDate) . "'";
    } else {
        if ($selectedMonth !== '') {
            $orderConditions[] = "MONTH(`" . $dateField . "`) = '" . mysqli_real_escape_string($orderConnect, $selectedMonth) . "'";
        }
        if ($selectedYear !== '') {
            $orderConditions[] = "YEAR(`" . $dateField . "`) = '" . mysqli_real_escape_string($orderConnect, $selectedYear) . "'";
        }
    }
    $orderByParts = array();
    if (shopeeOmsTableHasColumn($orderConnect, isset($platformConfig['db_name']) ? $platformConfig['db_name'] : dbFinance, isset($platformConfig['table']) ? $platformConfig['table'] : '', $dateField)) {
        $orderByParts[] = "CASE WHEN order_status IN ('WAERD', 'Waiting Assign Estimate Received Date') THEN 1 WHEN order_status IN ('WR', 'AED', 'Waiting Receive', 'Assigned Estimate Date') THEN 2 WHEN order_status IN ('PD', 'Postponed') THEN 3 ELSE 4 END";
        $orderByParts[] = "`estimated_received_date` ASC";
        $orderByParts[] = "`" . $dateField . "` DESC";
    }
    if (shopeeOmsTableHasColumn($orderConnect, isset($platformConfig['db_name']) ? $platformConfig['db_name'] : dbFinance, isset($platformConfig['table']) ? $platformConfig['table'] : '', 'time')) {
        $orderByParts[] = "`time` DESC";
    } else if (shopeeOmsTableHasColumn($orderConnect, isset($platformConfig['db_name']) ? $platformConfig['db_name'] : dbFinance, isset($platformConfig['table']) ? $platformConfig['table'] : '', 'create_time')) {
        $orderByParts[] = "`create_time` DESC";
    }
    $orderByParts[] = "id DESC";

    $sql = "SELECT * FROM `" . $platformConfig['table'] . "` WHERE " . implode(' AND ', array_filter($orderConditions)) . " ORDER BY " . implode(', ', $orderByParts);
    $result = mysqli_query($orderConnect, $sql);
    if (!$result) {
        continue;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $row = shopeeOmsAttachOrderSourceMeta((array) $row, $platformKey, $platformConfig);
        $orderCode = shopeeOmsGetOrderCodeValue($row, $platformConfig);
        $customerNameText = shopeeOmsGetOrderCustomerNameText($connect, $finance_connect, $row, $platformConfig);
        if ($orderIdFilter !== '' && stripos($orderCode, $orderIdFilter) === false) {
            continue;
        }
        if ($customerFilter !== '' && stripos($customerNameText, $customerFilter) === false) {
            continue;
        }
        $arrivalRows[] = $row;
    }
}

usort($arrivalRows, function ($a, $b) {
    $statusOrder = array('WAERD' => 1, 'WR' => 2, 'PD' => 3);
    $statusA = shopeeOmsNormalizeStatusCode(isset($a['order_status']) ? $a['order_status'] : '');
    $statusB = shopeeOmsNormalizeStatusCode(isset($b['order_status']) ? $b['order_status'] : '');
    $statusRankA = isset($statusOrder[$statusA]) ? $statusOrder[$statusA] : 99;
    $statusRankB = isset($statusOrder[$statusB]) ? $statusOrder[$statusB] : 99;
    if ($statusRankA !== $statusRankB) {
        return $statusRankA <=> $statusRankB;
    }

    $etaA = isset($a['estimated_received_date']) ? (string) $a['estimated_received_date'] : '';
    $etaB = isset($b['estimated_received_date']) ? (string) $b['estimated_received_date'] : '';
    if ($etaA !== $etaB) {
        return strcmp($etaA, $etaB);
    }

    $dateA = isset($a['date']) && trim((string) $a['date']) !== '' ? (string) $a['date'] : (isset($a['create_date']) ? (string) $a['create_date'] : '');
    $dateB = isset($b['date']) && trim((string) $b['date']) !== '' ? (string) $b['date'] : (isset($b['create_date']) ? (string) $b['create_date'] : '');
    if ($dateA !== $dateB) {
        return strcmp($dateB, $dateA);
    }

    return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
});

$shopeeBuyerMetaMap = array();
$shopeeBuyerLookupValues = array();
foreach ($arrivalRows as $arrivalRow) {
    if (shopeeOmsGetOrderSourcePlatform($arrivalRow, 'shopee') !== 'shopee') {
        continue;
    }
    $shopeeBuyerLookupValues[] = isset($arrivalRow['buyer']) ? $arrivalRow['buyer'] : '';
}
if (!empty($shopeeBuyerLookupValues)) {
    $shopeeBuyerMetaMap = customerLabelGetShopeeCustomerMetaMap($connect, $finance_connect, $shopeeBuyerLookupValues);
}

$arrivalWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect);
$arrivalDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect);

$arrivalRowsByPlatform = array('all' => $arrivalRows);
foreach ($platformTabs as $platformKey => $platformLabel) {
    if ($platformKey === 'all') {
        continue;
    }
    $arrivalRowsByPlatform[$platformKey] = array_values(array_filter($arrivalRows, function ($row) use ($platformKey) {
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

        .shopee-arrival-date-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 220px)) minmax(0, 220px);
            gap: 16px;
        }

        .shopee-arrival-date-filter-reset {
            display: flex;
            align-items: flex-end;
        }

        .shopee-arrival-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
            border-radius: 999px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .shopee-arrival-filter-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr);
            gap: 16px;
            margin-top: 16px;
        }

        .shopee-arrival-bulk-grid {
            display: grid;
            grid-template-columns: minmax(0, 280px) auto;
            gap: 16px;
            align-items: end;
        }

        .shopee-arrival-bulk-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
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
            min-width: 180px;
        }


        .arrival-follow-up-summary {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .arrival-follow-up-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .arrival-follow-up-summary-row:last-child {
            margin-bottom: 0;
        }

        .arrival-follow-up-summary-label {
            color: #6c757d;
        }

        .arrival-follow-up-contact-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0.625rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: #f8f9fa;
        }

        .arrival-follow-up-contact-edit {
            border: 0;
            background: transparent;
            color: #0d6efd;
            padding: 0;
        }

        .arrival-follow-up-preview {
            display: none;
            margin-top: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #f8f9fa;
            padding: 0.75rem;
        }

        .arrival-follow-up-preview img {
            display: block;
            max-width: 100%;
            max-height: 260px;
            margin: 0 auto;
            border-radius: 0.375rem;
            object-fit: contain;
        }

        .arrival-follow-up-preview-note {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
        }

        .arrival-required-star {
            color: #dc3545;
            margin-left: 2px;
        }

        .arrival-follow-up-field-error {
            display: none;
            color: #dc3545;
            font-size: 0.82rem;
            margin-top: 4px;
        }

        .arrival-follow-up-field-error.is-visible {
            display: block;
        }

        @media (max-width: 1199px) {
            .shopee-arrival-date-filter-grid,
            .shopee-arrival-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .shopee-arrival-card {
                padding: 16px;
            }

            .shopee-arrival-date-filter-grid,
            .shopee-arrival-filter-grid,
            .shopee-arrival-bulk-grid {
                grid-template-columns: 1fr;
            }

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
                    <h2 class="mb-1"><?= htmlspecialchars($displayPageTitle) ?></h2>
                    <div class="shopee-arrival-subtitle">Manage Waiting Assign Estimate Received Date, Waiting Receive, and Postponed orders across all supported platforms.</div>
                </div>
            </div>

            <div class="shopee-arrival-stack">
                <div class="shopee-arrival-card">
                    <form method="get" id="arrival_management_filter_form">
                        <input type="hidden" name="platform" id="arrival_platform_query" value="<?= htmlspecialchars($activePlatform, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="shopee-arrival-date-filter-grid">
                            <div>
                                <label class="form-label" for="arrival_date">Date</label>
                                <input class="form-control" type="date" id="arrival_date" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="arrival_month">Month</label>
                                <select class="form-select" id="arrival_month" name="month">
                                    <option value="">All</option>
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
                                <label class="form-label" for="arrival_year">Year</label>
                                <select class="form-select" id="arrival_year" name="year">
                                    <option value="">All</option>
                                    <?php for ($yearValue = (int) $currentYear; $yearValue >= ((int) $currentYear - 5); $yearValue--) { ?>
                                        <option value="<?= htmlspecialchars((string) $yearValue) ?>" <?= (string) $yearValue === $selectedYear ? 'selected' : '' ?>><?= htmlspecialchars((string) $yearValue) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="shopee-arrival-date-filter-reset">
                                <a class="btn btn-outline-secondary w-100 shopee-arrival-reset-btn" href="<?= htmlspecialchars($SITEURL . '/finance/arrival_management.php') ?>">Reset Filters</a>
                            </div>
                        </div>
                        <div class="shopee-arrival-filter-grid">
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
                                <label class="form-label" for="customer">Customer</label>
                                <input class="form-control" type="text" id="customer" name="customer" value="<?= htmlspecialchars($customerFilter) ?>" placeholder="Search customer (optional)" autocomplete="off">
                            </div>
                        </div>
                    </form>
                </div>

                <div class="shopee-arrival-card">
                    <div class="oms-platform-tabs" id="omsPlatformTabs">
                        <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                            <?php $platformCount = isset($arrivalRowsByPlatform[$platformKey]) ? count($arrivalRowsByPlatform[$platformKey]) : 0; ?>
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

                <form method="post" id="arrival_order_form">
                    <input type="hidden" name="platform_section" id="arrival_platform_section" value="<?= htmlspecialchars($activePlatform, ENT_QUOTES, 'UTF-8') ?>">
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

                    <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                        <?php $panelRows = isset($arrivalRowsByPlatform[$platformKey]) ? $arrivalRowsByPlatform[$platformKey] : array(); ?>
                        <div class="shopee-arrival-card oms-platform-panel<?= $activePlatform === $platformKey ? ' is-active' : '' ?>" data-platform-panel="<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                            <h4 class="mb-3">Arrival Order List</h4>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered shopee-arrival-table arrival-management-table-js" id="arrival_management_table_<?= htmlspecialchars($platformKey, ENT_QUOTES, 'UTF-8') ?>">
                                    <thead>
                                        <tr>
                                            <th width="60"><input type="checkbox" class="check-all-orders"></th>
                                            <th width="60">S/N</th>
                                            <th>Platform</th>
                                            <th>Order ID</th>
                                            <th>Action</th>
                                            <th>Stock Out Warehouse</th>
                                            <th>Customer</th>
                                            <th>Current Status</th>
                                            <th>Shipped Date</th>
                                            <th>Estimated Received Date</th>
                                            <th>Postpone / Delay Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($panelRows)) { ?>
                                            <?php $rowNumber = 1; ?>
                                            <?php foreach ($panelRows as $row) { ?>
                                                <?php
                                                $rowPlatform = shopeeOmsGetOrderSourcePlatform($row, 'shopee');
                                                $rowSourceConfig = shopeeOmsGetOrderSourceConfig($rowPlatform);
                                                $statusCode = shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '');
                                                $canAssign = shopeeOmsPassesAssignmentScope($connect, $row, USER_ID, USER_GROUP) && ($statusCode === 'WAERD' ? shopeeOmsHasTransitionPermission($connect, $statusCode, 'WR', USER_GROUP, $row, USER_ID) : true);
                                                $canConfirm = shopeeOmsHasTransitionPermission($connect, $statusCode, 'PR', USER_GROUP, $row, USER_ID);
                                                $dateField = isset($rowSourceConfig['date_field']) && trim((string) $rowSourceConfig['date_field']) !== '' ? (string) $rowSourceConfig['date_field'] : 'create_date';
                                                $timeField = isset($row['time']) ? 'time' : (isset($row['create_time']) ? 'create_time' : '');
                                                $shippedDate = trim((string) (isset($row[$dateField]) ? $row[$dateField] : ''));
                                                $shippedTime = $timeField !== '' && isset($row[$timeField]) ? trim((string) $row[$timeField]) : '';
                                                $shippedDisplay = $shippedDate !== '' ? $shippedDate . ($shippedTime !== '' ? ' ' . $shippedTime : '') : '-';
                                                $estimatedDate = trim((string) (isset($row['estimated_received_date']) ? $row['estimated_received_date'] : ''));
                                                $estimatedDateRange = function_exists('shopeeOmsGetEstimatedReceivedDateRange')
                                                    ? shopeeOmsGetEstimatedReceivedDateRange($row)
                                                    : $estimatedDateValidation;
                                                $statusBadgeClass = $statusCode === 'WAERD'
                                                    ? 'shopee-arrival-status-badge-waerd'
                                                    : ($statusCode === 'PD' ? 'shopee-arrival-status-badge-pd' : 'shopee-arrival-status-badge-wr');
                                                $statusBadgeLabel = $statusCode === 'WAERD'
                                                    ? 'Waiting Assign<br>Estimate Received Date'
                                                    : ($statusCode === 'PD' ? 'Postponed' : 'Waiting Receive');
                                                $stockOutWarehouseName = shopeeOmsResolveStockOutWarehouseName($connect, $row, $arrivalDefaultWarehouseId, $arrivalWarehouseNameMap);
                                                $customerDisplayHtml = htmlspecialchars(shopeeOmsGetOrderCustomerNameText($connect, $finance_connect, $row, $rowSourceConfig), ENT_QUOTES, 'UTF-8');
                                                if ($rowPlatform === 'shopee') {
                                                    $customerDisplayHtml = customerLabelRenderShopeeBuyerCell($connect, $finance_connect, isset($row['buyer']) ? $row['buyer'] : '', '', $shopeeBuyerMetaMap);
                                                }
                                                $orderViewUrl = shopeeOmsGetOrderSourceViewUrl($rowSourceConfig, (int) $row['id']);
                                                $orderCode = shopeeOmsGetOrderCodeValue($row, $rowSourceConfig);
                                                $delayRemarkField = isset($rowSourceConfig['delay_remark_field']) && trim((string) $rowSourceConfig['delay_remark_field']) !== ''
                                                    ? trim((string) $rowSourceConfig['delay_remark_field'])
                                                    : 'delay_remark';
                                                $followUpModalContext = array();
                                                if (in_array($statusCode, array('WR', 'PD'), true) && $canConfirm) {
                                                    $followUpModalContext = customerFollowUpBuildReceivedOrderContext($connect, $finance_connect, $rowPlatform, (int) $row['id'], $row);
                                                }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($statusCode === 'WAERD' && $canAssign) { ?>
                                                            <input type="checkbox" name="selected_order_refs[]" value="<?= htmlspecialchars($rowPlatform . ':' . (int) $row['id'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= $rowNumber++ ?></td>
                                                    <td><?= htmlspecialchars(isset($platformTabs[$rowPlatform]) ? $platformTabs[$rowPlatform] : ucfirst($rowPlatform)) ?></td>
                                                    <td>
                                                        <?php if ($orderViewUrl !== '') { ?>
                                                            <a class="shopee-arrival-order-link" href="<?= htmlspecialchars($orderViewUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orderCode) ?></a>
                                                        <?php } else { ?>
                                                            <?= htmlspecialchars($orderCode) ?>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="shopee-arrival-action-cell">
                                                        <?php if ($statusCode === 'WAERD' && $canAssign) { ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-warning btn-assign-estimated-date"
                                                                data-platform="<?= htmlspecialchars($rowPlatform, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-order-id="<?= (int) $row['id'] ?>"
                                                                data-order-code="<?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-min-date="<?= htmlspecialchars((string) $estimatedDateRange['min_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-max-date="<?= htmlspecialchars((string) $estimatedDateRange['max_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                                title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                                        <?php } else if (in_array($statusCode, array('WR', 'PD'), true) && $canConfirm) { ?>
                                                            <button
                                                                class="btn btn-sm btn-success confirm-receive-btn"
                                                                type="button"
                                                                data-platform="<?= htmlspecialchars($rowPlatform, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-order-id="<?= (int) $row['id'] ?>"
                                                                data-order-code="<?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-customer-name="<?= htmlspecialchars((string) (isset($followUpModalContext['customer_name']) ? $followUpModalContext['customer_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-customer-username="<?= htmlspecialchars((string) (isset($followUpModalContext['customer_username']) ? $followUpModalContext['customer_username'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-package-name="<?= htmlspecialchars((string) (isset($followUpModalContext['package_name']) ? $followUpModalContext['package_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-received-date="<?= htmlspecialchars((string) (isset($followUpModalContext['received_date']) ? $followUpModalContext['received_date'] : customerFollowUpNowDate()), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-purchase-count="<?= (int) (isset($followUpModalContext['purchase_count_snapshot']) ? $followUpModalContext['purchase_count_snapshot'] : 0) ?>"
                                                                data-customer-type="<?= htmlspecialchars((string) (isset($followUpModalContext['customer_type']) ? $followUpModalContext['customer_type'] : 'new'), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-contact-no="<?= htmlspecialchars((string) (isset($followUpModalContext['contact_no']) ? $followUpModalContext['contact_no'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-max-date="<?= htmlspecialchars((string) (isset($followUpModalContext['max_date']) ? $followUpModalContext['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-rule-label="<?= htmlspecialchars((string) (isset($followUpModalContext['rule_label']) ? $followUpModalContext['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                                data-block-message="<?= htmlspecialchars((string) (isset($followUpModalContext['block_message']) ? $followUpModalContext['block_message'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                Confirm Received
                                                            </button>
                                                        <?php } else { ?>
                                                            <span class="shopee-arrival-empty-action">No direct action</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($stockOutWarehouseName !== '' ? $stockOutWarehouseName : '-') ?></td>
                                                    <td><?= $customerDisplayHtml ?></td>
                                                    <td><span class="shopee-arrival-status-badge <?= $statusBadgeClass ?>"><?= $statusBadgeLabel ?></span></td>
                                                    <td><?= htmlspecialchars($shippedDisplay) ?></td>
                                                    <td class="shopee-arrival-estimated-cell"><?= htmlspecialchars($estimatedDate !== '' ? $estimatedDate : '-') ?></td>
                                                    <td>
                                                        <?php if (in_array($statusCode, array('WR', 'PD'), true)) { ?>
                                                            <div class="d-flex flex-column gap-2 shopee-arrival-delay-field">
                                                                <textarea class="form-control form-control-sm delay-remark-field" data-platform="<?= htmlspecialchars($rowPlatform, ENT_QUOTES, 'UTF-8') ?>" data-order-id="<?= (int) $row['id'] ?>" rows="2"><?= htmlspecialchars((string) (isset($row[$delayRemarkField]) ? $row[$delayRemarkField] : '')) ?></textarea>
                                                                <button class="btn btn-sm btn-outline-secondary save-delay-btn" type="button" data-platform="<?= htmlspecialchars($rowPlatform, ENT_QUOTES, 'UTF-8') ?>" data-order-id="<?= (int) $row['id'] ?>">Save Delay Remark</button>
                                                            </div>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr>
                                                <td colspan="11" class="text-center">No WAERD, Waiting Receive, or Postponed orders found.</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php } ?>

                    <input type="hidden" name="confirm_receive_id" id="table_confirm_receive_id" value="">
                    <input type="hidden" name="confirm_receive_platform" id="table_confirm_receive_platform" value="">
                    <input type="hidden" name="delay_order_id" id="table_delay_order_id" value="">
                    <input type="hidden" name="delay_platform" id="table_delay_platform" value="">
                    <input type="hidden" name="delay_remark" id="table_delay_remark" value="">
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="arrivalFollowUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data" id="arrival_follow_up_form" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="arrivalFollowUpModalTitle">Customer Follow-Up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="arrival_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['arrival_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="confirm_receive_platform" id="arrival_follow_up_platform" value="">
                        <input type="hidden" name="confirm_receive_id" id="arrival_follow_up_order_id" value="">
                        <input type="hidden" name="platform_section" id="arrival_follow_up_platform_section" value="<?= htmlspecialchars($activePlatform, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="arrival-follow-up-summary">
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Order ID</span>
                                <span id="arrival_follow_up_order_code_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Customer</span>
                                <span id="arrival_follow_up_customer_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Package</span>
                                <span id="arrival_follow_up_package_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Received Date</span>
                                <span id="arrival_follow_up_received_date_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Customer Type</span>
                                <span id="arrival_follow_up_customer_type_text"></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="arrival_follow_up_attachment">
                                Customer Chat Screenshot<span class="arrival-required-star">*</span>
                            </label>
                            <input type="file" class="form-control" id="arrival_follow_up_attachment" name="follow_up_attachment" required>
                            <div class="arrival-follow-up-field-error" id="arrival_follow_up_attachment_error">Customer Chat Screenshot is required.</div>
                            <div class="arrival-follow-up-preview" id="arrival_follow_up_attachment_preview_wrap">
                                <img id="arrival_follow_up_attachment_preview_img" alt="Follow-Up Attachment Preview">
                                <div class="arrival-follow-up-preview-note d-none" id="arrival_follow_up_attachment_preview_note"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="arrival_follow_up_message_shortcut_id">
                                Message Shortcut<span class="arrival-required-star">*</span>
                            </label>
                            <select class="form-select" id="arrival_follow_up_message_shortcut_id" name="follow_up_message_shortcut_id" required>
                                <option value="">Select Message Shortcut</option>
                                <?php foreach ($arrivalFollowUpShortcutOptions as $shortcutRow) {
                                    $shortcutId = isset($shortcutRow['id']) ? (int) $shortcutRow['id'] : 0;
                                    if ($shortcutId <= 0) {
                                        continue;
                                    }
                                    $shortcutLabel = trim((string) (isset($shortcutRow['shortcuts_tag']) ? $shortcutRow['shortcuts_tag'] : ''));
                                    ?>
                                    <option value="<?= $shortcutId ?>"><?= htmlspecialchars($shortcutLabel !== '' ? $shortcutLabel : ('Shortcut #' . $shortcutId), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                            <div class="arrival-follow-up-field-error" id="arrival_follow_up_message_shortcut_error">Message Shortcut is required.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="arrival_follow_up_next_date">
                                Next Follow-Up Date<span class="arrival-required-star">*</span>
                            </label>
                            <input type="date" class="form-control" id="arrival_follow_up_next_date" name="follow_up_next_follow_up_date" required>
                            <div class="arrival-follow-up-field-error" id="arrival_follow_up_next_date_error">Next Follow-Up Date is required.</div>
                            <small class="text-muted" id="arrival_follow_up_rule_hint"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">WhatsApp / Contact Number</label>
                            <div id="arrival_follow_up_contact_display_wrap" class="arrival-follow-up-contact-display d-none">
                                <div id="arrival_follow_up_contact_display_text"></div>
                                <button type="button" class="arrival-follow-up-contact-edit" id="arrival_follow_up_contact_edit_btn" title="Edit Contact Number">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </div>
                            <div id="arrival_follow_up_contact_input_wrap">
                                <input type="text" class="form-control" id="arrival_follow_up_contact_no" name="follow_up_contact_no" placeholder="Enter Contact Number">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" name="confirmReceiveFollowUpBtn" value="1" class="btn btn-success" style="text-transform: none !important;">Submit & Confirm Received</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once ROOT . '/include/estimated_date_modal.php'; ?>

    <script>
    function bindArrivalFollowUpAttachmentPreview(inputId, wrapId, imageId, noteId) {
        var fileInput = document.getElementById(inputId);
        var previewWrap = document.getElementById(wrapId);
        var previewImage = document.getElementById(imageId);
        var previewNote = document.getElementById(noteId);
        if (!fileInput || !previewWrap || !previewImage || !previewNote) {
            return null;
        }

        var currentObjectUrl = null;
        var clearPreview = function () {
            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
                currentObjectUrl = null;
            }
            previewImage.removeAttribute('src');
            previewImage.style.display = 'none';
            previewNote.textContent = '';
            previewNote.classList.add('d-none');
            previewWrap.style.display = 'none';
        };

        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!file) {
                clearPreview();
                return;
            }

            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
                currentObjectUrl = null;
            }

            if (file.type.indexOf('image/') === 0) {
                currentObjectUrl = URL.createObjectURL(file);
                previewImage.src = currentObjectUrl;
                previewImage.style.display = 'block';
                previewNote.textContent = '';
                previewNote.classList.add('d-none');
                previewWrap.style.display = 'block';
                return;
            }

            previewImage.removeAttribute('src');
            previewImage.style.display = 'none';
            previewNote.textContent = 'Preview is available for image files only.';
            previewNote.classList.remove('d-none');
            previewWrap.style.display = 'block';
        });

        window.addEventListener('beforeunload', clearPreview);
        return clearPreview;
    }

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


    (function () {
        <?php if ($statusMessage !== '') { ?>
        showArrivalStatusPopup(<?= json_encode($statusMessage) ?>);
        <?php } ?>

        var arrivalFilterForm = document.getElementById('arrival_management_filter_form');
        var arrivalAutoSubmitSelector = '#arrival_management_filter_form select, #arrival_management_filter_form input[type="date"]';
        if (arrivalFilterForm) {
            document.querySelectorAll(arrivalAutoSubmitSelector).forEach(function (filterElement) {
                filterElement.addEventListener('change', function () {
                    arrivalFilterForm.submit();
                });
            });

            ['order_id', 'customer'].forEach(function (fieldId) {
                var inputElement = document.getElementById(fieldId);
                if (!inputElement) {
                    return;
                }

                inputElement.addEventListener('change', function () {
                    arrivalFilterForm.submit();
                });
                inputElement.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        arrivalFilterForm.submit();
                    }
                });
            });
        }

        

        document.querySelectorAll('.arrival-management-table-js').forEach(function (tableElement) {
            var rowCount = getValidDataTableRowCount(tableElement);
            if (rowCount === 0) {
                return;
            }

            var table = new DataTable('#' + tableElement.id, {
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
                    { orderable: false, searchable: false, targets: [0, 1, 4, 10] }
                ]
            });
            datatableAlignment(tableElement.id);

            table.on('draw', function () {
                var pageInfo = table.page.info();
                table.column(1, { page: 'current' }).nodes().each(function (cell, index) {
                    cell.innerHTML = pageInfo.start + index + 1;
                });
            });
            table.draw(false);
        });

        var hiddenPlatformInputs = [
            document.getElementById('arrival_platform_query'),
            document.getElementById('arrival_platform_section'),
            document.getElementById('estimated_received_platform_section')
        ];

        document.querySelectorAll('[data-platform-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                activatePlatformTab(button.getAttribute('data-platform-tab') || 'all');
            });
        });

        activatePlatformTab(<?= json_encode($activePlatform) ?>);

        document.querySelectorAll('.check-all-orders').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var table = checkbox.closest('table');
                if (!table) {
                    return;
                }
                table.querySelectorAll('input[name="selected_order_refs[]"]').forEach(function (rowCheckbox) {
                    rowCheckbox.checked = checkbox.checked;
                });
            });
        });

        var arrivalForm = document.getElementById('arrival_order_form');
        var delayOrderId = document.getElementById('table_delay_order_id');
        var delayPlatform = document.getElementById('table_delay_platform');
        var delayRemark = document.getElementById('table_delay_remark');
        var clearArrivalFollowUpAttachmentPreview = bindArrivalFollowUpAttachmentPreview(
            'arrival_follow_up_attachment',
            'arrival_follow_up_attachment_preview_wrap',
            'arrival_follow_up_attachment_preview_img',
            'arrival_follow_up_attachment_preview_note'
        );
        
        var arrivalFollowUpModalElement = document.getElementById('arrivalFollowUpModal');
        var arrivalFollowUpModal = arrivalFollowUpModalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(arrivalFollowUpModalElement)
            : null;

        function setArrivalFollowUpFieldError(fieldId, errorId, hasError) {
            var field = document.getElementById(fieldId);
            var error = document.getElementById(errorId);

            if (field) {
                field.classList.toggle('is-invalid', hasError);
            }

            if (error) {
                error.classList.toggle('is-visible', hasError);
            }
        }

        function clearArrivalFollowUpRequiredErrors() {
            setArrivalFollowUpFieldError('arrival_follow_up_attachment', 'arrival_follow_up_attachment_error', false);
            setArrivalFollowUpFieldError('arrival_follow_up_message_shortcut_id', 'arrival_follow_up_message_shortcut_error', false);
            setArrivalFollowUpFieldError('arrival_follow_up_next_date', 'arrival_follow_up_next_date_error', false);
        }

        function validateArrivalFollowUpRequiredFields() {
            var attachmentInput = document.getElementById('arrival_follow_up_attachment');
            var shortcutInput = document.getElementById('arrival_follow_up_message_shortcut_id');
            var nextDateInput = document.getElementById('arrival_follow_up_next_date');

            var attachmentMissing = !attachmentInput || !attachmentInput.files || attachmentInput.files.length === 0;
            var shortcutMissing = !shortcutInput || shortcutInput.value.trim() === '';
            var nextDateMissing = !nextDateInput || nextDateInput.value.trim() === '';

            setArrivalFollowUpFieldError('arrival_follow_up_attachment', 'arrival_follow_up_attachment_error', attachmentMissing);
            setArrivalFollowUpFieldError('arrival_follow_up_message_shortcut_id', 'arrival_follow_up_message_shortcut_error', shortcutMissing);
            setArrivalFollowUpFieldError('arrival_follow_up_next_date', 'arrival_follow_up_next_date_error', nextDateMissing);

            if (attachmentMissing || shortcutMissing || nextDateMissing) {
                var firstInvalidField = attachmentMissing
                    ? attachmentInput
                    : (shortcutMissing ? shortcutInput : nextDateInput);

                if (firstInvalidField) {
                    firstInvalidField.focus();
                }

                return false;
            }

            return true;
        }

        var arrivalFollowUpFormSubmitted = false;

        function clearSingleArrivalFollowUpFieldError(fieldId) {
            if (fieldId === 'arrival_follow_up_attachment') {
                var attachmentInput = document.getElementById('arrival_follow_up_attachment');
                var hasAttachment = attachmentInput && attachmentInput.files && attachmentInput.files.length > 0;
                if (hasAttachment) {
                    setArrivalFollowUpFieldError('arrival_follow_up_attachment', 'arrival_follow_up_attachment_error', false);
                }
                return;
            }

            if (fieldId === 'arrival_follow_up_message_shortcut_id') {
                var shortcutInput = document.getElementById('arrival_follow_up_message_shortcut_id');
                if (shortcutInput && shortcutInput.value.trim() !== '') {
                    setArrivalFollowUpFieldError('arrival_follow_up_message_shortcut_id', 'arrival_follow_up_message_shortcut_error', false);
                }
                return;
            }

            if (fieldId === 'arrival_follow_up_next_date') {
                var nextDateInput = document.getElementById('arrival_follow_up_next_date');
                if (nextDateInput && nextDateInput.value.trim() !== '') {
                    setArrivalFollowUpFieldError('arrival_follow_up_next_date', 'arrival_follow_up_next_date_error', false);
                }
            }
        }

        var arrivalFollowUpForm = document.getElementById('arrival_follow_up_form');
        if (arrivalFollowUpForm) {
            arrivalFollowUpForm.addEventListener('submit', function (event) {
                arrivalFollowUpFormSubmitted = true;

                if (!validateArrivalFollowUpRequiredFields()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            });
        }

        ['arrival_follow_up_attachment', 'arrival_follow_up_message_shortcut_id', 'arrival_follow_up_next_date'].forEach(function (fieldId) {
            var field = document.getElementById(fieldId);
            if (!field) {
                return;
            }

            field.addEventListener('change', function () {
                if (!arrivalFollowUpFormSubmitted) {
                    return;
                }

                clearSingleArrivalFollowUpFieldError(fieldId);
            });
        });
        document.querySelectorAll('.confirm-receive-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var blockMessage = button.getAttribute('data-block-message') || '';
                if (blockMessage) {
                    showNotification(blockMessage, 'warning');
                    return;
                }

                document.getElementById('arrival_follow_up_platform').value = button.getAttribute('data-platform') || '';
                document.getElementById('arrival_follow_up_order_id').value = button.getAttribute('data-order-id') || '';
                document.getElementById('arrival_follow_up_platform_section').value = <?= json_encode($activePlatform) ?>;
                document.getElementById('arrivalFollowUpModalTitle').textContent = 'Customer Follow-Up - ' + (button.getAttribute('data-order-code') || '');
                document.getElementById('arrival_follow_up_order_code_text').textContent = button.getAttribute('data-order-code') || '-';
                document.getElementById('arrival_follow_up_customer_text').textContent = button.getAttribute('data-customer-username') || button.getAttribute('data-customer-name') || '-';
                document.getElementById('arrival_follow_up_package_text').textContent = button.getAttribute('data-package-name') || '-';
                document.getElementById('arrival_follow_up_received_date_text').textContent = button.getAttribute('data-received-date') || '';
                document.getElementById('arrival_follow_up_customer_type_text').textContent =
                    (button.getAttribute('data-customer-type') || 'new') === 'return'
                        ? 'Return Customer (' + (button.getAttribute('data-purchase-count') || '0') + ' previous purchase)'
                        : 'New Customer';
                document.getElementById('arrival_follow_up_attachment').value = '';
                document.getElementById('arrival_follow_up_message_shortcut_id').value = '';
                document.getElementById('arrival_follow_up_next_date').value = '';
                document.getElementById('arrival_follow_up_next_date').max = button.getAttribute('data-max-date') || '';
                document.getElementById('arrival_follow_up_rule_hint').textContent = button.getAttribute('data-rule-label') || '';
                
                arrivalFollowUpFormSubmitted = false;
                clearArrivalFollowUpRequiredErrors();

                var contactNo = button.getAttribute('data-contact-no') || '';
                var contactDisplayWrap = document.getElementById('arrival_follow_up_contact_display_wrap');
                var contactDisplayText = document.getElementById('arrival_follow_up_contact_display_text');
                var contactInputWrap = document.getElementById('arrival_follow_up_contact_input_wrap');
                var contactInput = document.getElementById('arrival_follow_up_contact_no');
                contactInput.value = contactNo;
                if (contactNo) {
                    contactDisplayText.textContent = contactNo;
                    contactDisplayWrap.classList.remove('d-none');
                    contactInputWrap.classList.add('d-none');
                } else {
                    contactDisplayWrap.classList.add('d-none');
                    contactInputWrap.classList.remove('d-none');
                }

                if (typeof clearArrivalFollowUpAttachmentPreview === 'function') {
                    clearArrivalFollowUpAttachmentPreview();
                }

                if (arrivalFollowUpModal) {
                    arrivalFollowUpModal.show();
                }
            });
        });

        var arrivalFollowUpContactEditBtn = document.getElementById('arrival_follow_up_contact_edit_btn');
        if (arrivalFollowUpContactEditBtn) {
            arrivalFollowUpContactEditBtn.addEventListener('click', function () {
                document.getElementById('arrival_follow_up_contact_display_wrap').classList.add('d-none');
                document.getElementById('arrival_follow_up_contact_input_wrap').classList.remove('d-none');
                document.getElementById('arrival_follow_up_contact_no').focus();
            });
        }

        document.querySelectorAll('.save-delay-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var platformKey = button.getAttribute('data-platform') || '';
                var orderId = button.getAttribute('data-order-id') || '';
                var remarkField = document.querySelector('.delay-remark-field[data-platform="' + platformKey + '"][data-order-id="' + orderId + '"]');
                delayOrderId.value = orderId;
                delayPlatform.value = platformKey;
                delayRemark.value = remarkField ? remarkField.value : '';
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
                    button.getAttribute('data-platform') || '',
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
