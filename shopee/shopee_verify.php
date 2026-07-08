<?php
$currentPagePin = 129;
$pageTitle = "Shopee Verify Order";

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/shopee_order_verify_modal_ui.php';

$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$processingPageName = getPinGroupNameById($connect, 128);
$verifyPageName = getPinGroupNameById($connect, 129);
$allOrdersPageName = getPinGroupNameById($connect, 130);

$pinAccess = checkPin($connect, $verifyPageName);
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $allOrdersAccess = checkPin($connect, $allOrdersPageName);
    if (is_array($allOrdersAccess) && count($allOrdersAccess) > 0) {
        echo "<script>location.replace('shopee_order_req_table.php');</script>";
        exit;
    }

    $processingAccess = checkPin($connect, $processingPageName);
    if (is_array($processingAccess) && count($processingAccess) > 0) {
        echo "<script>location.replace('shopee_processing_order.php');</script>";
        exit;
    }
    renderNotificationScript('You do not have permission to view Shopee Verify Order.', 'error', '../dashboard.php', 1200, true);
    exit;
}

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['shopee_order_verify_pdf_csrf'])) {
    $_SESSION['shopee_order_verify_pdf_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    audit_log(array(
        'log_act' => 'view',
        'page' => $pageTitle,
        'uid' => USER_ID,
        'act_msg' => USER_NAME . " viewed the " . $pageTitle . " page.",
        'cdate' => $cdate,
        'ctime' => $ctime,
        'cby' => USER_ID,
        'connect' => $connect
    ));
}
shopeeOmsEnsureRealtimePostponedSync($connect, $finance_connect);

// Build numeric action keys from the latest user-group pins in database.
$accessActionKey = array();
$shopeePinGroups = array(128, 129, 130);
$userPinGroupData = getUserPinGroup($connect);
foreach ($shopeePinGroups as $pinGroupId) {
    $latestActions = getValuesByPinAssocIndex($userPinGroupData, (int) $pinGroupId);
    if (!empty($latestActions)) {
        $accessActionKey = array_merge($accessActionKey, $latestActions);
    }
}
$accessActionKey = array_values(array_unique(array_map('intval', $accessActionKey)));
$canVerifyAction = in_array(14, $accessActionKey, true);
$canViewProfit = in_array(15, $accessActionKey, true);
$canAssignEstimatedReceivedDate = in_array(2, $accessActionKey, true) || $canVerifyAction;
$canBulkSyncShippedOrders = function_exists('shopeeOmsHasTransitionPermission')
    ? shopeeOmsHasTransitionPermission($connect, 'SP', 'WAERD', USER_GROUP, array('create_by' => USER_ID), USER_ID)
    : false;
$estimatedDateToday = new DateTimeImmutable('today');
$estimatedDateMin = $estimatedDateToday->modify('+1 day')->format('Y-m-d');
$estimatedDateMax = $estimatedDateToday->modify('+7 days')->format('Y-m-d');

$num = $default_currency_id = 1; 
$bulkSyncShippedOrders = numberInput('bulk_sync_shipped_orders');
$completeId = (int) numberInput('complete_id');
$monthInput = input('month');
if ($monthInput === 'All') {
    $monthFilter = '';
} else if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
    $monthFilter = $monthInput;
} else {
    $monthFilter = date('Y-m');
}
$statusFilter = input('status');
$brandFilter = numberInput('brand');
$pkgFilter = numberInput('pkg');
$accFilter = numberInput('acc');
$monthGroupInput = input('month_gb');
if ($monthGroupInput === 'All') {
    $monthGroup = 'All';
} else if ($monthGroupInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthGroupInput)) {
    $monthGroup = $monthGroupInput;
} else {
    $monthGroup = '';
}
$statusGroup = input('status_gb');
$brandGroup = numberInput('brand_gb');
$pkgGroup = numberInput('pkg_gb');
$accGroup = numberInput('acc_gb');
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && $bulkSyncShippedOrders === '1'
    && $canBulkSyncShippedOrders
    && function_exists('shopeeOmsBulkMoveCurrentShippedOrdersToWaerd')
) {
    shopeeOmsBulkMoveCurrentShippedOrdersToWaerd($connect, $finance_connect, USER_ID, USER_GROUP, $pageTitle);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('assignEstimatedReceivedDateBtn')) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', (string) $_SERVER['REQUEST_URI'], 1200, true);
        exit;
    }

    if (!$canAssignEstimatedReceivedDate) {
        renderNotificationScript('Security Error: You do not have permission to assign Estimate Received Dates.', 'error', (string) $_SERVER['REQUEST_URI'], 1200, true);
        exit;
    }

    $assignOrderId = postSpaceFilter('estimated_received_order_id');
    $assignDate = postSpaceFilter('estimated_received_date');
    $assignmentResult = assignEstimatedReceivedDate($finance_connect, SHOPEE_SG_ORDER_REQ, $assignOrderId, $assignDate, USER_ID);

    if ($assignmentResult['success']) {
        $safeAssignedDate = isset($assignmentResult['date']) ? $assignmentResult['date'] : '';
        $safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
        $oldStatus = isset($assignmentResult['old_status']) ? (string) $assignmentResult['old_status'] : '';
        $newStatus = isset($assignmentResult['new_status']) ? (string) $assignmentResult['new_status'] : '';
        $changeSummary = 'estimated_received_date: ' . $safeAssignedDate;
        if ($oldStatus !== '' && $newStatus !== '' && $oldStatus !== $newStatus) {
            $changeSummary = 'order_status: ' . $oldStatus . ' -> ' . $newStatus . ', ' . $changeSummary;
        }
        $auditData = array(
            'log_act' => 'edit',
            'page' => $pageTitle,
            'query_rec' => 'estimated_received_date=' . $safeAssignedDate,
            'query_table' => SHOPEE_SG_ORDER_REQ,
            'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
            'changes' => $changeSummary,
            'uid' => USER_ID,
            'act_msg' => $safeUserName . " assigned the Estimate Received Date <b>" . $safeAssignedDate . "</b> for Shopee order [ <b>ID = " . (int) $assignOrderId . "</b> ].",
            'cdate' => $cdate,
            'ctime' => $ctime,
            'cby' => USER_ID,
            'connect' => $connect
        );
        audit_log($auditData);
    }

    $assignmentMessage = (string) $assignmentResult['message'];
    renderNotificationScript($assignmentMessage, resolveNotificationType($assignmentMessage, 'info'), (string) $_SERVER['REQUEST_URI'], 1200, true);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (int) post('verify_id') > 0) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', 'shopee_verify.php', 1200, true);
        exit;
    }
    
    if (!$canVerifyAction) {
        renderNotificationScript('Security Error: You do not have permission to verify orders.', 'error', 'shopee_verify.php', 1200, true);
        exit;
    }

    $orderId = (int) post('verify_id');

    $oldStatus = '';
    $orderCode = '';

    $checkSql = "SELECT order_status, orderID FROM " . SHOPEE_SG_ORDER_REQ . " WHERE id = $orderId LIMIT 1";
    $checkResult = mysqli_query($finance_connect, $checkSql);

    if ($checkResult && $checkRow = mysqli_fetch_assoc($checkResult)) {
        $oldStatus = isset($checkRow['order_status']) ? (string) $checkRow['order_status'] : '';
        $orderCode = isset($checkRow['orderID']) ? (string) $checkRow['orderID'] : '';
    }

    $oldStatusCode = shopeeOmsNormalizeStatusCode($oldStatus);

    if (!in_array($oldStatusCode, array('OC', 'WAFC'), true)) {
        renderNotificationScript('Only OC or WAFC orders can be verified.', 'warning', 'shopee_verify.php', 1200, true);
        exit;
    }

    $verifyResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'V', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Verified from verify order list.',
        'action' => 'verify_order',
        'skip_permission' => true,
        'allow_auto_follow_up' => false,
    ));

    if (!empty($verifyResult['success'])) {
        $newStatus = isset($verifyResult['new_status']) ? (string) $verifyResult['new_status'] : 'V';
        $safeOrderCode = htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8');
        $safeOldStatus = htmlspecialchars($oldStatusCode, ENT_QUOTES, 'UTF-8');
        $safeNewStatus = htmlspecialchars($newStatus, ENT_QUOTES, 'UTF-8');

        audit_log(array(
            'log_act' => 'edit',
            'page' => $pageTitle,
            'query_rec' => $orderId,
            'query_table' => SHOPEE_SG_ORDER_REQ,
            'oldval' => 'order_status: ' . $oldStatusCode,
            'changes' => 'order_status: ' . $oldStatusCode . ' -> ' . $newStatus,
            'uid' => USER_ID,
            'act_msg' => USER_NAME . " verified Shopee order [ <b>ID = " . $orderId . "</b> ]" . ($safeOrderCode !== '' ? " [ <b>Order ID = " . $safeOrderCode . "</b> ]" : "") . " from <b>" . $safeOldStatus . "</b> to <b>" . $safeNewStatus . "</b>.",
            'cdate' => $cdate,
            'ctime' => $ctime,
            'cby' => USER_ID,
            'connect' => $connect
        ));
    }

    $verifyMessage = (string) (isset($verifyResult['message']) ? $verifyResult['message'] : 'Unable to verify order.');
    renderNotificationScript($verifyMessage, resolveNotificationType($verifyMessage, 'info'), 'shopee_verify.php', 1200, true);
    exit;
}

if ($completeId > 0) {
    $orderId = $completeId;
    $completeResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'C', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Completed from verify order list.',
    ));
    $completeMessage = (string) (isset($completeResult['message']) ? $completeResult['message'] : 'Unable to complete order.');
    renderNotificationScript($completeMessage, resolveNotificationType($completeMessage, 'info'), 'shopee_verify.php', 1200, true);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (int) post('move_to_pack_id') > 0) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', 'shopee_verify.php', 1200, true);
        exit;
    }

    $orderId = (int) post('move_to_pack_id');
    $warehouseCustomerName = trim((string) postSpaceFilter('warehouse_customer_name'));
    if ($warehouseCustomerName !== '') {
        shopeeOmsRememberWarehouseDeliveryInfo('shopee', $orderId, array(
            'customer_name' => $warehouseCustomerName,
        ));
    }
    $moveToPackResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'TP', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Moved to To Pack from verify order list.',
        'action' => 'move_to_pack',
        'platform' => 'shopee',
    ));
    $moveToPackMessage = (string) (isset($moveToPackResult['message']) ? $moveToPackResult['message'] : 'Unable to move order to To Pack.');
    renderNotificationScript($moveToPackMessage, resolveNotificationType($moveToPackMessage, 'info'), 'shopee_verify.php', 1200, true);
    exit;
}

shopeeOmsHandleMoveToWafcWithReceivedDatePost($connect, $finance_connect, array(
    'redirect_url' => 'shopee_verify.php',
    'source_page' => $pageTitle,
    'platform' => 'shopee',
    'actor_user_id' => USER_ID,
    'actor_user_group_id' => USER_GROUP,
    'audit_connect' => $connect,
    'query_table' => SHOPEE_SG_ORDER_REQ,
));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (int) post('return_id') > 0) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', 'shopee_verify.php', 1200, true);
        exit;
    }

    $orderId = (int) post('return_id');
    $returnResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'R', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Marked as Return from verify order list.',
        'action' => 'mark_return',
    ));
    $returnMessage = (string) (isset($returnResult['message']) ? $returnResult['message'] : 'Unable to mark order as Return.');
    renderNotificationScript($returnMessage, resolveNotificationType($returnMessage, 'info'), 'shopee_verify.php', 1200, true);
    exit;
}

$whereConditions = [];

// Show Shopee OMS orders from To Ship upward, covering both legacy labels and status codes.
$verifyStatusCondition = shopeeOmsBuildOrderStatusInCondition($finance_connect, 'order_status', array('P', 'TP', 'SP', 'WAERD', 'WR', 'PD', 'PR', 'WAFC', 'V', 'C', 'R', 'CR'));
if ($verifyStatusCondition !== '') {
    $whereConditions[] = $verifyStatusCondition;
}

if (!empty($monthFilter)) { $whereConditions[] = "DATE_FORMAT(date, '%Y-%m') = '" . mysqli_real_escape_string($finance_connect, $monthFilter) . "'"; }
if (!empty($statusFilter)) {
    $statusCondition = shopeeOmsBuildOrderStatusFilterCondition($finance_connect, 'order_status', $statusFilter);
    if ($statusCondition !== '') {
        $whereConditions[] = $statusCondition;
    }
}
// Use FIND_IN_SET to correctly search inside comma-separated IDs
if (!empty($brandFilter)) { $whereConditions[] = "FIND_IN_SET('" . mysqli_real_escape_string($finance_connect, $brandFilter) . "', brand) > 0"; }
if (!empty($pkgFilter)) { $whereConditions[] = "FIND_IN_SET('" . mysqli_real_escape_string($finance_connect, $pkgFilter) . "', package) > 0"; }
if (!empty($accFilter)) { $whereConditions[] = "shopee_acc = '" . mysqli_real_escape_string($finance_connect, $accFilter) . "'"; }

$groupByFields = [];

if (!empty($monthGroup) && $monthGroup !== 'All') { $groupByFields[] = "DATE_FORMAT(date, '%Y-%m')"; }
if (!empty($statusGroup)) { $groupByFields[] = "order_status"; }
if (!empty($brandGroup)) { $groupByFields[] = "brand"; }
if (!empty($pkgGroup)) { $groupByFields[] = "package"; }
if (!empty($accGroup)) { $groupByFields[] = "shopee_acc"; }

$groupBySql = !empty($groupByFields) ? "GROUP BY " . implode(", ", $groupByFields) : "";
$whereSql = implode(" AND ", $whereConditions);

$siteBaseUrl = rtrim((string) $SITEURL, '/');
$requestUri = isset($_SERVER['REQUEST_URI']) ? trim((string) $_SERVER['REQUEST_URI']) : '';
$basePath = rtrim((string) parse_url($siteBaseUrl, PHP_URL_PATH), '/');
if ($basePath !== '' && strpos($requestUri, $basePath . '/') === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}
$currentQueueUrl = $siteBaseUrl . ($requestUri !== '' ? $requestUri : '/shopee/shopee_verify.php');
$redirectPage = $SITEURL . '/shopee/shopee_order_req.php';
$addRequestUrl = $redirectPage . '?act=' . $act_1;
$deleteRedirectPage = $currentQueueUrl;
$result = getData('*', $whereSql, $groupBySql, SHOPEE_SG_ORDER_REQ, $finance_connect);
$shopeeBuyerMetaMap = array();
if ($result instanceof mysqli_result) {
    $shopeeBuyerLookupValues = array();
    while ($buyerLookupRow = $result->fetch_assoc()) {
        $shopeeBuyerLookupValues[] = isset($buyerLookupRow['buyer']) ? $buyerLookupRow['buyer'] : '';
    }
    mysqli_data_seek($result, 0);
    $shopeeBuyerMetaMap = customerLabelGetShopeeCustomerMetaMap($connect, $finance_connect, $shopeeBuyerLookupValues);
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/shopeeOrderRequest.css">

</head>
<script>


    window.onload = autoToggleSections;
    $(document).ready(() => {
        createSortingTable('shopee_order_req_table');

        $(document).on('click', '.btn-assign-estimated-date', function () {
            openEstimatedReceivedDateModal(
                $(this).data('orderId'),
                $(this).data('orderCode'),
                $(this).data('minDate'),
                $(this).data('maxDate')
            );
        });

    });
</script>
<body>
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?> </p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2><?php echo $pageTitle ?></h2>
                        <div class="mt-auto mb-auto">
                            <?php if (isActionAllowed("Add", $pinAccess) || isActionAllowed("Import", $pinAccess)): ?>
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="addBtn" id="addBtn" href="<?= $addRequestUrl ?>"><i class="fa-solid fa-plus"></i> Add Request </a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Import", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="importBtn" id="importBtn" href="<?= $SITEURL ?>/import/shopee_order_import.php"><i class="fa-solid fa-file-import"></i> Import </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
           <div class="col-md-12 mb-3">
                <button class="btn btn-info" type="button" onclick="toggleFilters('filterSection')">Show/Hide Filters</button>
                <button class="btn btn-primary" type="button" onclick="toggleFilters('groupBySection')">Show/Hide Group By</button>
            </div>
            <div id="filterSection" class="row mb-3" style="display: none;">
                <div class="col-md-3">
                    <label for="monthFilter" class="form-label">Filter by Month</label>
                    <select id="monthFilter" name="month" class="form-select" onchange="applyFilterOrGroup('month', this)">
                        <option value="All" <?= ($monthFilter === 'All') ? 'selected' : '' ?>>All Months</option>
                        <?php
                        $monthSql = "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') AS month_value, DATE_FORMAT(date, '%M %Y') AS month_label FROM " . SHOPEE_SG_ORDER_REQ . " ORDER BY month_value DESC";
                        $monthResult = mysqli_query($finance_connect, $monthSql);
                        while ($monthRow = mysqli_fetch_assoc($monthResult)) {
                            $monthValue = $monthRow['month_value'];
                            $monthLabel = $monthRow['month_label'];
                            $selected = ($monthFilter == $monthValue) ? "selected" : "";
                            echo "<option value='$monthValue' $selected>$monthLabel</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Filter by Order Status</label>
                    <select id="statusFilter" name="status" class="form-select" onchange="applyFilterOrGroup('status', this)">
                        <option value="">All Statuses</option>
                        <?php
                        $statusSql = "SELECT DISTINCT order_status FROM " . SHOPEE_SG_ORDER_REQ;
                        $statusResult = mysqli_query($finance_connect, $statusSql);
                        while ($statusRow = mysqli_fetch_assoc($statusResult)) {
                            $status = $statusRow['order_status'];
                            $label = getOrderStatusLabel($status);
                            $selected = ($statusFilter == $status) ? "selected" : "";
                            echo "<option value='$status' $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="brandFilter" class="form-label">Filter by Brand</label>
                    <select id="brandFilter" name="brand" class="form-select" onchange="applyFilterOrGroup('brand', this)">
                        <option value="">All Brands</option>
                        <?php
                        $brandSql = "SELECT id, name FROM " . BRAND . " ORDER BY name ASC";
                        $brandResult = mysqli_query($connect, $brandSql);
                        while ($brandRow = mysqli_fetch_assoc($brandResult)) {
                            $selected = ($brandFilter == $brandRow['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars((string) $brandRow['id'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars((string) $brandRow['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="pkgFilter" class="form-label">Filter by Package</label>
                    <select id="pkgFilter" name="pkg" class="form-select" onchange="applyFilterOrGroup('pkg', this)">
                        <option value="">All Packages</option>
                        <?php
                        $pkgSql = "SELECT id, name FROM " . PKG . " ORDER BY name ASC";
                        $pkgResult = mysqli_query($connect, $pkgSql);
                        while ($pkgRow = mysqli_fetch_assoc($pkgResult)) {
                            $selected = ($pkgFilter == $pkgRow['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars((string) $pkgRow['id'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars((string) $pkgRow['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="accFilter" class="form-label">Filter by Shopee Account</label>
                    <select id="accFilter" name="acc" class="form-select" onchange="applyFilterOrGroup('acc', this)">
                        <option value="">All Accounts</option>
                        <?php
                        $accSql = "SELECT id, name FROM " . SHOPEE_ACC . " ORDER BY name ASC";
                        $accResult = mysqli_query($finance_connect, $accSql);
                        while ($accRow = mysqli_fetch_assoc($accResult)) {
                            $selected = ($accFilter == $accRow['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars((string) $accRow['id'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars((string) $accRow['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block invisible">Reset</label>
                    <a href="<?= htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-danger filter-reset">Reset</a>
                </div>
            </div>
    
            <div id="groupBySection" class="row mb-3" style="display: none;">
                <div class="col-md-3">
                    <label for="monthGroupBy" class="form-label">Group by Month</label>
                    <select id="monthGroupBy" name="month_gb" class="form-select" onchange="applyFilterOrGroup('month_gb', this)">
                        <option value="All" <?= ($monthGroup === 'All') ? 'selected' : '' ?>>All Months</option>
                        <?php
                        mysqli_data_seek($monthResult, 0); 
                        while ($monthRow = mysqli_fetch_assoc($monthResult)) {
                            $monthValue = $monthRow['month_value'];
                            $monthLabel = $monthRow['month_label'];
                            $selected = ($monthGroup == $monthValue) ? "selected" : "";
                            echo "<option value='$monthValue' $selected>$monthLabel</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusGroupBy" class="form-label">Group by Order Status</label>
                    <select id="statusGroupBy" name="status_gb" class="form-select" onchange="applyFilterOrGroup('status_gb', this)">
                        <option value="">All Statuses</option>
                        <?php
                        mysqli_data_seek($statusResult, 0); 
                        while ($statusRow = mysqli_fetch_assoc($statusResult)) {
                            $status = $statusRow['order_status'];
                            $label = getOrderStatusLabel($status);
                            $selected = ($statusGroup == $status) ? "selected" : "";
                            echo "<option value='$status' $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="brandGroupBy" class="form-label">Group by Brand</label>
                    <select id="brandGroupBy" name="brand_gb" class="form-select" onchange="applyFilterOrGroup('brand_gb', this)">
                        <option value="">All Brands</option>
                        <?php
                        mysqli_data_seek($brandResult, 0); 
                        while ($brandRow = mysqli_fetch_assoc($brandResult)) {
                            $selected = ($brandGroup == $brandRow['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars((string) $brandRow['id'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars((string) $brandRow['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="pkgGroupBy" class="form-label">Group by Package</label>
                    <select id="pkgGroupBy" name="pkg_gb" class="form-select" onchange="applyFilterOrGroup('pkg_gb', this)">
                        <option value="">All Packages</option>
                        <?php
                        mysqli_data_seek($pkgResult, 0); 
                        while ($pkgRow = mysqli_fetch_assoc($pkgResult)) {
                            $selected = ($pkgGroup == $pkgRow['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars((string) $pkgRow['id'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars((string) $pkgRow['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="accGroupBy" class="form-label">Group by Shopee Account</label>
                    <select id="accGroupBy" name="acc_gb" class="form-select" onchange="applyFilterOrGroup('acc_gb', this)">
                        <option value="">All Accounts</option>
                        <?php
                        mysqli_data_seek($accResult, 0);
                        while ($accRow = mysqli_fetch_assoc($accResult)) {
                            $selected = ($accGroup == $accRow['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars((string) $accRow['id'], ENT_QUOTES, 'UTF-8') . "' $selected>" . htmlspecialchars((string) $accRow['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block invisible">Reset</label>
                    <a href="<?= htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-danger filter-reset">Reset</a>
                </div>
            </div>
            <?php
            if (!$result) {
                echo '<div class="text-center"><h4>No Result!</h4></div>';
            } else {
                ?>
                <?php
                $total_price = 0; $total_voucher = 0; $total_shipping = 0;
                $total_trans_fee = 0; $total_ams_fee = 0; $total_fees = 0;
                $total_final_amt = 0; $total_final_service_fee = 0;
                $total_final_agentCostProfit = 0; $total_final_companyCostProfit = 0;
                ?>
                <table class="table table-striped" id="shopee_order_req_table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Order Status</th>
                            <th scope="col">Estimate Received Date</th>
                            <th scope="col">Shopee Account</th>
                            <th scope="col">Currency</th>
                            <th scope="col">Order ID</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Package</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Shopee Buyer Username</th>
                            <th scope="col">Buyer Payment Method</th>
                            <th scope="col">Person In Charge</th>
                            <th scope="col">Product Price</th>
                            <th scope="col">Voucher</th>
                            <th scope="col">Actual Shipping Fee</th>
                            <th scope="col">Service Fee (incl. GST)</th>
                            <th scope="col">Transaction Fee (incl. GST)</th>
                            <th scope="col">AMS Commission Fee</th>
                            <th scope="col">Fees & Charges</th>
                            <th scope="col">Final Amount</th>
                            <th scope="col">Remark</th>
                           <?php  if($canViewProfit){ 
                            echo "<th scope=\"col\">Agent Profit</th><th scope=\"col\">Company Profit</th>";
                           } ?>
                        </tr>
                    </thead>
                    
                    <?php
                    // --- PREFETCH DATA TO FIX N+1 QUERY ISSUE ---
                    $packageMap = array();
                    $pkgResult = mysqli_query($connect, "SELECT id, name, agent_cost, cost FROM " . PKG);
                    if ($pkgResult) {
                        while ($p = mysqli_fetch_assoc($pkgResult)) {
                            $packageMap[$p['id']] = $p;
                        }
                    }

                    $brandMap = array();
                    $brandResult = mysqli_query($connect, "SELECT id, name FROM " . BRAND);
                    if ($brandResult) {
                        while ($b = mysqli_fetch_assoc($brandResult)) {
                            $brandMap[$b['id']] = $b['name'];
                        }
                    }
                    ?>

                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) {
                            $q1 = getData('*', "id='" . $row['shopee_acc'] . "'", '', SHOPEE_ACC, $finance_connect);
                            $acc = $q1 ? $q1->fetch_assoc() : [];
                            $q7 = getData('*', "id='" . $row['currency'] . "'", '', CUR_UNIT, $connect);
                            $curr = $q7 ? $q7->fetch_assoc() : [];

                            $q8 = getData('*', "default_currency_unit='" . $row['currency'] . "'", '', CURRENCIES, $connect);
                            $currExcRate = $q8 ? $q8->fetch_assoc() : [];
                            
                            // Fetch Packages from fast memory map instead of DB query
                            $packageIds = array_values(array_filter(array_map('trim', explode(',', (string) ($row['package'] ?? ''))), 'strlen'));
                            $pkg = array();
                            $pkgNames = array();
                            if (count($packageIds) > 0) {
                                $firstPkgId = $packageIds[0];
                                if (isset($packageMap[$firstPkgId])) {
                                    $pkg['agent_cost'] = $packageMap[$firstPkgId]['agent_cost'];
                                    $pkg['cost'] = $packageMap[$firstPkgId]['cost'];
                                }
                                foreach ($packageIds as $pkgId) {
                                    if (isset($packageMap[$pkgId])) {
                                        $pkgNames[] = $packageMap[$pkgId]['name'];
                                    }
                                }
                            }
                            $packageDisplayParts = array();
                            if (function_exists('shopeeOmsResolveOrderPackageRows')) {
                                $resolvedPackageRows = shopeeOmsResolveOrderPackageRows($connect, $row);
                                foreach ($resolvedPackageRows as $packageRow) {
                                    $packageName = trim((string) (isset($packageRow['package_name']) ? $packageRow['package_name'] : ''));
                                    if ($packageName === '') {
                                        continue;
                                    }

                                    $packageQty = isset($packageRow['qty']) ? (int) $packageRow['qty'] : 1;
                                    if ($packageQty <= 0) {
                                        $packageQty = 1;
                                    }

                                    $packageDisplayParts[] = $packageQty > 1
                                        ? ($packageName . ' x' . $packageQty)
                                        : $packageName;
                                }
                            }
                            if (!empty($packageDisplayParts)) {
                                $pkg['name'] = implode(', ', $packageDisplayParts);
                            } else if (!empty($pkgNames)) {
                                $pkg['name'] = implode(', ', $pkgNames);
                            }

                            // Fetch Brands from fast memory map instead of DB query
                            $brandIds = array_values(array_filter(array_map('trim', explode(',', (string) ($row['brand'] ?? ''))), 'strlen'));
                            $brandNames = array();
                            foreach ($brandIds as $brandId) {
                                if (isset($brandMap[$brandId])) {
                                    $brandNames[] = $brandMap[$brandId];
                                }
                            }
                            $brand = array('name' => implode(', ', $brandNames));

                            $q6 = getData('*', "id='" . $row['buyer_pay_meth'] . "'", '', PAY_MTHD_SHOPEE, $finance_connect);
                            $pay = $q6 ? $q6->fetch_assoc() : [];

                            $q5 = getData('name', "id='" . $row['pic'] . "'", '', USR_USER, $connect);
                            $pic = $q5 ? $q5->fetch_assoc() : [];
                            
                            $price = (float) ($row['price'] ?? 0);
                            $voucher = (float) ($row['voucher'] ?? 0);
                            $shipping = (float) ($row['act_shipping_fee'] ?? 0);
                            $trans_fee = (float) ($row['trans_fee'] ?? 0);
                            $service_fee = (float) ($row['service_fee'] ?? 0);
                            $ams_fee = (float) ($row['ams_fee'] ?? 0);
                            $fees = (float) ($row['fees'] ?? 0);
                            $final_amt = (float) ($row['final_amt'] ?? 0);
                            
                            if ($row['currency'] != $default_currency_id) {
                                if (!empty($currExcRate) && $currExcRate['exchange_currency_unit'] == $default_currency_id) {
                                    $rate = (float) $currExcRate['exchange_currency_rate'];
                                    if ($rate > 0) {
                                        $final_amt = $final_amt * $rate;
                                        $final_fees =$fees * $rate;
                                        $final_ams_fee = $ams_fee *$rate;
                                        $final_trans_fee = $trans_fee * $rate;
                                        $final_shipping = $shipping * $rate;
                                        $final_voucher = $voucher * $rate;
                                        $final_price = $price * $rate;
                                        $final_service_fee = $service_fee * $rate;
                                    }
                                }
                            } else {
                                $final_fees =$fees; $final_ams_fee = $ams_fee; $final_trans_fee = $trans_fee;
                                $final_shipping = $shipping; $final_voucher = $voucher; $final_price = $price;
                                $final_service_fee = $service_fee;
                            }
                            $total_price += $final_price; $total_voucher += $final_voucher; $total_shipping += $final_shipping;
                            $total_trans_fee += $final_trans_fee; $total_ams_fee += $final_ams_fee; $total_fees += $final_fees;
                            $total_final_amt += $final_amt; $total_final_service_fee += $final_service_fee;
                            ?>
                            <tr>
                                <th class="hideColumn" scope="row"><?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                <th scope="row" class="sticky-action"><?= $num++; ?></th>
                                <td scope="row" class="btn-container sticky-action">
                                <?php renderViewEditButtonByPin("1", $redirectPage, $row, $accessActionKey); ?>
                                <?php renderViewEditButtonByPin("2", $redirectPage, $row, $accessActionKey, $act_2); ?>
                                <?php renderDeleteButtonByPin($accessActionKey, $row['id'], $row['orderID'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?> 
                                <?php
                                $statusCode = shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '');
                                $canMoveToPackThisOrder = shopeeOmsHasTransitionPermission($connect, $statusCode, 'TP', USER_GROUP, $row, USER_ID);
                                $canVerifyThisOrder = shopeeOmsHasTransitionPermission($connect, $statusCode, 'V', USER_GROUP, $row, USER_ID);
                                $canCompleteThisOrder = shopeeOmsHasTransitionPermission($connect, $statusCode, 'C', USER_GROUP, $row, USER_ID);
                                $estimatedDateRange = function_exists('shopeeOmsGetEstimatedReceivedDateRange')
                                    ? shopeeOmsGetEstimatedReceivedDateRange($row)
                                    : array('min_date' => $estimatedDateMin, 'max_date' => $estimatedDateMax);
                                ?>
                                <?php if ($statusCode === 'P' && $canMoveToPackThisOrder) { ?>
                                 <form method="post" class="d-inline shopee-move-to-pack-form" data-order-id="<?= (int) $row['id'] ?>" onsubmit="return confirm('Move this order to To Pack?')">
                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                     <input type="hidden" name="move_to_pack_id" value="<?= (int) $row['id'] ?>">
                                     <input type="hidden" name="warehouse_customer_name" value="">
                                     <button type="submit" class="btn btn-sm btn-rounded btn-info" title="Move to To Pack">
                                         <i class="fas fa-box-open"></i>
                                     </button>
                                 </form>
                                <?php } ?>
                                <?php if ($statusCode === 'TP') { ?>
                                 <a class="btn btn-sm btn-rounded btn-primary" href="<?= htmlspecialchars((string) shopeeOmsGetOrderSourceInfoUrl('shopee', (int) $row['id']), ENT_QUOTES, 'UTF-8') ?>" title="Open QR Info">
                                     <i class="fa-solid fa-qrcode"></i>
                                 </a>
                                <?php } ?>
                                <?php if (shouldShowEstimatedReceivedDateButton($row) && $canAssignEstimatedReceivedDate) { ?>
                                 <button
                                     type="button"
                                     class="btn btn-sm btn-warning btn-assign-estimated-date"
                                     data-order-id="<?= (int) $row['id'] ?>"
                                     data-order-code="<?= htmlspecialchars((string) ($row['orderID'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                     data-min-date="<?= htmlspecialchars((string) $estimatedDateRange['min_date'], ENT_QUOTES, 'UTF-8') ?>"
                                     data-max-date="<?= htmlspecialchars((string) $estimatedDateRange['max_date'], ENT_QUOTES, 'UTF-8') ?>"
                                     title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                <?php } ?>
                                <?php if (in_array($statusCode, array('OC', 'WAFC'), true) && $canVerifyAction && $canVerifyThisOrder) { ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-success btn-verified sor-verify-order-trigger"
                                    data-order-id="<?= (int) $row['id'] ?>"
                                    data-order-code="<?= htmlspecialchars((string) (isset($row['orderID']) ? $row['orderID'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-existing-pdf-path="<?= htmlspecialchars((string) (isset($row['order_detail_pdf']) ? $row['order_detail_pdf'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                >Verified</button>
                                <?php } ?>
                                <?php if ($statusCode === 'V' && $canCompleteThisOrder) { ?>
                                 <a href="?complete_id=<?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-dark btn-verified" onclick="return confirm('Mark this order as complete?')">Complete</a>
                                <?php } ?>
                                <?php if ($statusCode === 'PR') { ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-rounded btn-info btn-open-received-date-modal"
                                    data-order-id="<?= (int) $row['id'] ?>"
                                    data-order-code="<?= htmlspecialchars((string) ($row['orderID'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    title="Move to WAFC Now">
                                     <i class="fas fa-forward"></i>
                                </button>
                                <?php } ?>
                                <?php if (in_array($statusCode, array('SP', 'WAERD', 'WR', 'PD', 'PR', 'WAFC', 'V', 'C'), true)) { ?>
                                 <form method="post" class="d-inline" onsubmit="return confirm('Mark this order as Return?')">
                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                     <input type="hidden" name="return_id" value="<?= (int) $row['id'] ?>">
                                     <button type="submit" class="btn btn-sm btn-rounded btn-warning" title="Mark as Return">
                                         <i class="fa-solid fa-rotate-left"></i>
                                     </button>
                                 </form>
                                <?php } ?>
                                </td>
                                <td scope="row"><?= getOrderStatusLabel($row['order_status']) ?></td>
                                <td scope="row"><?= isset($row['estimated_received_date']) ? htmlspecialchars((string) $row['estimated_received_date'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($acc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($curr['unit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['orderID'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($pkg['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($brand['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= customerLabelRenderShopeeBuyerCell($connect, $finance_connect, isset($row['buyer']) ? $row['buyer'] : '', '', $shopeeBuyerMetaMap) ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($pay['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($pic['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['voucher'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['act_shipping_fee'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['service_fee'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['trans_fee'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['ams_fee'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['fees'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['final_amt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) ($row['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                               <?php  
                                if ($canViewProfit) { 
                                    $clear_profit = ($final_amt - (float)($pkg['cost'] ?? 0));
                                    echo "<td scope=\"row\">" . ($agentCostProfit = ($clear_profit *0.4)). "</td>";
                                     $total_final_agentCostProfit += $agentCostProfit;
                                    echo "<td scope=\"row\">" . ($companyCostProfit = ($clear_profit *0.6)) . "</td>";
                                     $total_final_companyCostProfit += $companyCostProfit;
                                } 
                                ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Order Status</th>
                            <th scope="col">Estimate Received Date</th>
                            <th scope="col">Shopee Account</th>
                            <th scope="col">Currency</th>
                            <th scope="col">Order ID</th>
                            <th scope="col">Date</th>
                            <th scope="col">Time</th>
                            <th scope="col">Package</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Shopee Buyer Username</th>
                            <th scope="col">Buyer Payment Method</th>
                            <th scope="col">Person In Charge</th>
                            <th scope="col">Product Price<br><?php echo "(RM)".$total_price;?></th>
                            <th scope="col">Voucher<br><?php echo "(RM)".$total_voucher;?></th>
                            <th scope="col">Actual Shipping Fee<br><?php echo "(RM)".$total_shipping;?></th>
                            <th scope="col">Service Fee (incl. GST)<br><?php echo "(RM)".$total_final_service_fee;?></th>
                            <th scope="col">Transaction Fee (incl. GST)<br><?php echo "(RM)".$total_trans_fee;?></th>
                            <th scope="col">AMS Commission Fee<br><?php echo "(RM)".$total_ams_fee;?></th>
                            <th scope="col">Fees & Charges<br><?php echo "(RM)".$total_fees;?></th>
                            <th scope="col">Final Amount<br><?php echo "(RM)".$total_final_amt; ?></th>
                            <th scope="col">Remark</th>
                           <?php  if($canViewProfit){ 
                            echo "<th scope=\"col\">Agent Profit (".$total_final_agentCostProfit.")</th>";
                            echo "<th scope=\"col\">Company Profit (".$total_final_companyCostProfit.")</th>";
                           } ?>
                        </tr>
                    </tfoot>
                </table>
            <?php } ?>
        </div>
    </div>
        <?php include_once ROOT . '/include/estimated_date_modal.php'; ?>
    <?php shopeeOmsRenderReceivedDateModal(); ?>
    <?php
    shopeeOrderDetailPdfRenderVerifyModal(array(
        'modal_id' => 'sorVerifyOrderModal',
        'csrf_token' => isset($_SESSION['shopee_order_verify_pdf_csrf']) ? $_SESSION['shopee_order_verify_pdf_csrf'] : '',
    ));
    ?>
</body>
<script>
    dropdownMenuDispFix();
    datatableAlignment('shopee_order_req_table');
    keepDataTableControlsVisible('shopee_order_req_table');

    (function bindShopeeMoveToPackCustomerName() {
        var moveForms = document.querySelectorAll('.shopee-move-to-pack-form');
        if (!moveForms.length) {
            return;
        }

        moveForms.forEach(function (form) {
            var orderId = String(form.getAttribute('data-order-id') || '').trim();
            var customerNameField = form.querySelector('input[name="warehouse_customer_name"]');
            if (!orderId || !customerNameField || typeof window.localStorage === 'undefined') {
                return;
            }

            try {
                var rawData = window.localStorage.getItem('shopee_airbill_delivery_info_' + orderId);
                if (!rawData) {
                    return;
                }

                var parsedData = JSON.parse(rawData);
                if (parsedData && typeof parsedData.customerName === 'string' && parsedData.customerName.trim() !== '') {
                    customerNameField.value = parsedData.customerName.trim();
                }
            } catch (error) {
            }
        });
    })();
</script>
<?php shopeeOmsRenderReceivedDateModalScript(); ?>
<?php
shopeeOrderDetailPdfRenderVerifyModalScript(array(
    'modal_id' => 'sorVerifyOrderModal',
    'trigger_selector' => '.sor-verify-order-trigger',
    'endpoint_template' => '../shopee/shopee_order_req.php?id=__ORDER_ID__&act=E',
    'redirect_url' => $currentQueueUrl,
    'site_url' => rtrim((string) $SITEURL, '/'),
));
?>
</html>
