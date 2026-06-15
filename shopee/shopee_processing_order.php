<?php
$currentPagePin = 128;
$pageTitle = "Shopee Processing Order";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$processingPageName = getPinGroupNameById($connect, 128);
$verifyPageName = getPinGroupNameById($connect, 129);
$allOrdersPageName = getPinGroupNameById($connect, 130);

$pinAccess = checkPin($connect, $processingPageName);
if (!is_array($pinAccess) || count($pinAccess) === 0) {
    $verifyAccess = checkPin($connect, $verifyPageName);
    if (is_array($verifyAccess) && count($verifyAccess) > 0) {
        echo "<script>location.replace('shopee_verify.php');</script>";
        exit;
    }

    $allOrdersAccess = checkPin($connect, $allOrdersPageName);
    if (is_array($allOrdersAccess) && count($allOrdersAccess) > 0) {
        echo "<script>location.replace('shopee_order_req_table.php');</script>";
        exit;
    }
    echo "<script>alert('You do not have permission to view Shopee Processing Order.'); location.replace('../dashboard.php');</script>";
    exit;
}

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
$estimatedDateMax = $estimatedDateToday->modify('+1 month')->format('Y-m-d');

$num = $default_currency_id = 1; 
$verifyMessage = '';
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && isset($_GET['bulk_sync_shipped_orders'])
    && $_GET['bulk_sync_shipped_orders'] === '1'
    && $canBulkSyncShippedOrders
    && function_exists('shopeeOmsBulkMoveCurrentShippedOrdersToWaerd')
) {
    shopeeOmsBulkMoveCurrentShippedOrdersToWaerd($connect, $finance_connect, USER_ID, USER_GROUP, $pageTitle);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('assignEstimatedReceivedDateBtn')) {
    if (!$canAssignEstimatedReceivedDate) {
        $verifyMessage = 'Security Error: You do not have permission to assign Estimate Received Dates.';
    } else {
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

        $verifyMessage = $assignmentResult['message'];
    }
}

if (isset($_GET['verify_id'])) {
    if (!$canVerifyAction) {
        $verifyMessage = "Security Error: You do not have permission to verify orders.";
    } else {
        $orderId = intval($_GET['verify_id']);
        $orderRow = shopeeOmsLoadOrder($finance_connect, $orderId);
        if (!empty($orderRow)) {
            $oldStatus = isset($orderRow['order_status']) ? (string) $orderRow['order_status'] : '';
            $oldStatusCode = shopeeOmsNormalizeStatusCode($oldStatus);
            if ($oldStatusCode === 'WAFC') {
                $verifyResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'V', array(
                    'actor_user_id' => USER_ID,
                    'actor_user_group_id' => USER_GROUP,
                    'source_page' => $pageTitle,
                    'remark' => 'Verified from processing order list.',
                    'action' => 'verify_order',
                    'allow_auto_follow_up' => false,
                ));
                if (!empty($verifyResult['success'])) {
                    $orderCode = isset($orderRow['orderID']) ? (string) $orderRow['orderID'] : '';
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

                $verifyMessage = isset($verifyResult['message']) ? (string) $verifyResult['message'] : 'Unable to verify order.';
            } else {
                $verifyMessage = "Warning: Order #$orderId is not in 'OC' or 'WAFC' status.";
            }
        } else {
            $verifyMessage = "Error: Order #$orderId not found.";
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['force_wafc_id'])) {
    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $wafcRedirectMonth = isset($_POST['wafc_redirect_month']) ? (string) $_POST['wafc_redirect_month'] : '';
    $wafcRedirectStatus = isset($_POST['wafc_redirect_status']) ? (string) $_POST['wafc_redirect_status'] : '';

    $redirectUrl = 'shopee_processing_order.php';
    $redirectQuery = array();

    if ($wafcRedirectMonth !== '') {
        $redirectQuery['month'] = $wafcRedirectMonth;
    }

    if ($wafcRedirectStatus !== '') {
        $redirectQuery['status'] = $wafcRedirectStatus;
    }

    if (!empty($redirectQuery)) {
        $redirectUrl .= '?' . http_build_query($redirectQuery);
    }

    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes($redirectUrl) . "');</script>";
        exit;
    }

    $orderId = intval($_POST['force_wafc_id']);

    $wafcResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'WAFC', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Moved to Waiting Admin Final Check without waiting 14 days from processing order list.',
        'action' => 'manual_force_wafc',
        'skip_permission' => true,
        'allow_auto_follow_up' => false,
    ));

    if (!empty($wafcResult['success'])) {
        $oldStatus = isset($wafcResult['old_status']) ? (string) $wafcResult['old_status'] : 'PR';
        $newStatus = isset($wafcResult['new_status']) ? (string) $wafcResult['new_status'] : 'WAFC';

        audit_log(array(
            'log_act' => 'edit',
            'page' => $pageTitle,
            'query_rec' => $orderId,
            'query_table' => SHOPEE_SG_ORDER_REQ,
            'oldval' => 'order_status: ' . $oldStatus,
            'changes' => 'order_status: ' . $oldStatus . ' -> ' . $newStatus,
            'uid' => USER_ID,
            'act_msg' => USER_NAME . " Moved Shopee order [ <b>ID = " . $orderId . "</b> ] from <b>" . $oldStatus . "</b> to <b>" . $newStatus . "</b> without waiting 14 days.",
            'cdate' => $cdate,
            'ctime' => $ctime,
            'cby' => USER_ID,
            'connect' => $connect
        ));
    }

    echo "<script>alert('" . addslashes(isset($wafcResult['message']) ? $wafcResult['message'] : 'Unable to move order to WAFC.') . "'); location.replace('" . addslashes($redirectUrl) . "');</script>";
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['return_id'])) {
    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    $returnRedirectMonth = isset($_POST['return_redirect_month']) ? (string) $_POST['return_redirect_month'] : '';
    $returnRedirectStatus = isset($_POST['return_redirect_status']) ? (string) $_POST['return_redirect_status'] : '';
    $redirectUrl = 'shopee_processing_order.php';
    $redirectQuery = array();
    if ($returnRedirectMonth !== '') {
        $redirectQuery['month'] = $returnRedirectMonth;
    }
    if ($returnRedirectStatus !== '') {
        $redirectQuery['status'] = $returnRedirectStatus;
    }
    if (!empty($redirectQuery)) {
        $redirectUrl .= '?' . http_build_query($redirectQuery);
    }

    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes($redirectUrl) . "');</script>";
        exit;
    }

    $orderId = intval($_POST['return_id']);
    $returnResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'R', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Marked as Return from processing order list.',
        'action' => 'mark_return',
    ));
    echo "<script>alert('" . addslashes(isset($returnResult['message']) ? $returnResult['message'] : 'Unable to mark order as Return.') . "'); location.replace('" . addslashes($redirectUrl) . "');</script>";
    exit;
}

$monthFilter = isset($_GET['month']) && $_GET['month'] !== '' ? ($_GET['month'] !=='All'?$_GET['month']:"") : date('Y-m');
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$brandFilter = isset($_GET['brand']) ? $_GET['brand'] : '';
$pkgFilter = isset($_GET['pkg']) ? $_GET['pkg'] : '';
$accFilter = isset($_GET['acc']) ? $_GET['acc'] : '';

$whereConditions = [];

// Show processing-related states, including mixed legacy-label and code forms.
$processingStatusCondition = shopeeOmsBuildOrderStatusInCondition($finance_connect, 'order_status', array('P', 'TP', 'SP', 'WAERD', 'WR', 'PD', 'PR', 'WAFC'));
if ($processingStatusCondition !== '') {
    $whereConditions[] = $processingStatusCondition;
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

$monthGroup = isset($_GET['month_gb']) ? $_GET['month_gb'] : '';
$statusGroup = isset($_GET['status_gb']) ? $_GET['status_gb'] : '';
$brandGroup = isset($_GET['brand_gb']) ? $_GET['brand_gb'] : '';
$pkgGroup = isset($_GET['pkg_gb']) ? $_GET['pkg_gb'] : '';
$accGroup = isset($_GET['acc_gb']) ? $_GET['acc_gb'] : '';
$groupByFields = [];

if (!empty($monthGroup) && $monthGroup !== 'All') { $groupByFields[] = "DATE_FORMAT(date, '%Y-%m')"; }
if (!empty($statusGroup)) { $groupByFields[] = "order_status"; }
if (!empty($brandGroup)) { $groupByFields[] = "brand"; }
if (!empty($pkgGroup)) { $groupByFields[] = "package"; }
if (!empty($accGroup)) { $groupByFields[] = "shopee_acc"; }

$groupBySql = !empty($groupByFields) ? "GROUP BY " . implode(", ", $groupByFields) : "";
$whereSql = implode(" AND ", $whereConditions);

$redirect_page = $SITEURL . '/shopee/shopee_order_req.php';
$deleteRedirectPage = $SITEURL . '/shopee/shopee_processing_order.php';
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

  function toggleFilters(sectionId) {
        const section = document.getElementById(sectionId);
        section.style.display = (section.style.display === 'none') ? 'flex' : 'none';
    }
    function applyFilterOrGroup(param, element) {
        const value = element.value;
        const url = new URL(window.location.href);
        url.searchParams.set(param, value);
        window.location.href = url.toString();
    }
    function autoToggleSections() {
        const urlParams = new URLSearchParams(window.location.search);
        const filterFields = ['month', 'status', 'brand', 'pkg', 'acc'];
        const groupFields = ['month_gb', 'status_gb', 'brand_gb', 'pkg_gb', 'acc_gb'];
        let filterActive = filterFields.some(key => urlParams.get(key) && urlParams.get(key) !== '' && urlParams.get(key) !== 'All');
        let groupActive = groupFields.some(key => urlParams.get(key) && urlParams.get(key) !== '');
        if (filterActive) { document.getElementById('filterSection').style.display = 'flex'; }
        if (groupActive) { document.getElementById('groupBySection').style.display = 'flex'; }
    }
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
                                    <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="addBtn" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Request </a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Import", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="importBtn" id="importBtn" href="<?= $SITEURL ?>/shopee_order_import.php"><i class="fa-solid fa-file-import"></i> Import </a>
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
                            echo "<option value='{$brandRow['id']}' $selected>{$brandRow['name']}</option>";
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
                            echo "<option value='{$pkgRow['id']}' $selected>{$pkgRow['name']}</option>";
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
                            echo "<option value='{$accRow['id']}' $selected>{$accRow['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block invisible">Reset</label>
                    <a href="<?= $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-danger filter-reset">Reset</a>
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
                            echo "<option value='{$brandRow['id']}' $selected>{$brandRow['name']}</option>";
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
                            echo "<option value='{$pkgRow['id']}' $selected>{$pkgRow['name']}</option>";
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
                            echo "<option value='{$accRow['id']}' $selected>{$accRow['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block invisible">Reset</label>
                    <a href="<?= $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-danger filter-reset">Reset</a>
                </div>
            </div>

             <?php if (!empty($verifyMessage)): ?>
                    <div class="alert alert-info"> <?= $verifyMessage ?> </div>
                <?php endif; ?>
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
                            if (!empty($pkgNames)) {
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
                                <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                <th scope="row" class="sticky-action"><?= $num++; ?></th>
                                <td scope="row" class="btn-container sticky-action">
                                <?php renderViewEditButtonByPin("1", $redirect_page, $row, $accessActionKey); ?>
                                <?php renderViewEditButtonByPin("2", $redirect_page, $row, $accessActionKey, $act_2); ?>
                                <?php renderDeleteButtonByPin($accessActionKey, $row['id'], $row['orderID'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?> 
                                <?php
                                $estimatedDateRange = function_exists('shopeeOmsGetEstimatedReceivedDateRange')
                                    ? shopeeOmsGetEstimatedReceivedDateRange($row)
                                    : array('min_date' => $estimatedDateMin, 'max_date' => $estimatedDateMax);
                                ?>
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
                                <?php if($statusCode === 'WAFC' && $canVerifyAction){ ?>
                                 <a href="?verify_id=<?= $row['id'] ?>&month=<?= urlencode($monthFilter) ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-sm btn-success btn-verified" onclick="return confirm('Mark this order as verified?')">Verified</a>
                                <?php } ?>
                                <?php
                                $statusCode = shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '');
                                ?>
                                <?php if ($statusCode === 'TP') { ?>
                                 <a class="btn btn-sm btn-rounded btn-primary" href="<?= htmlspecialchars((string) shopeeOmsGetOrderSourceInfoUrl('shopee', (int) $row['id']), ENT_QUOTES, 'UTF-8') ?>" title="Open QR Info">
                                     <i class="fa-solid fa-qrcode"></i>
                                 </a>
                                <?php } ?>
                                <?php if ($statusCode === 'PR') { ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Move this order to Waiting Admin Final Check now without waiting 14 days?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="force_wafc_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="wafc_redirect_month" value="<?= htmlspecialchars((string) $monthFilter, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="wafc_redirect_status" value="<?= htmlspecialchars((string) $statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-sm btn-rounded btn-info" title="Move to WAFC Now">
                                        <i class="fas fa-forward"></i>
                                    </button>
                                </form>
                                <?php } ?>
                                <?php if (in_array($statusCode, array('SP', 'WAERD', 'WR', 'PD', 'PR', 'WAFC', 'V', 'C'), true)) { ?>
                                 <form method="post" class="d-inline" onsubmit="return confirm('Mark this order as Return?')">
                                     <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                     <input type="hidden" name="return_id" value="<?= (int) $row['id'] ?>">
                                     <input type="hidden" name="return_redirect_month" value="<?= htmlspecialchars((string) $monthFilter, ENT_QUOTES, 'UTF-8') ?>">
                                     <input type="hidden" name="return_redirect_status" value="<?= htmlspecialchars((string) $statusFilter, ENT_QUOTES, 'UTF-8') ?>">
                                     <button type="submit" class="btn btn-sm btn-rounded btn-warning" title="Mark as Return">
                                         <i class="fa-solid fa-rotate-left"></i>
                                     </button>
                                 </form>
                                <?php } ?>
                                </td>
                                <td scope="row"><?= getOrderStatusLabel($row['order_status']) ?></td>
                                <td scope="row"><?= $row['estimated_received_date'] ?? '' ?></td>
                                <td scope="row"><?= $acc['name'] ?? '' ?></td>
                                <td scope="row"><?= $curr['unit'] ?? '' ?></td>
                                <td scope="row"><?= $row['orderID'] ?? '' ?></td>
                                <td scope="row"><?= $row['date'] ?? '' ?></td>
                                <td scope="row"><?= $row['time'] ?? '' ?></td>
                                <td scope="row"><?= $pkg['name'] ?? '' ?></td>
                                <td scope="row"><?= $brand['name'] ?? '' ?></td>
                                <td scope="row"><?= customerLabelRenderShopeeBuyerCell($connect, $finance_connect, isset($row['buyer']) ? $row['buyer'] : '', '', $shopeeBuyerMetaMap) ?></td>
                                <td scope="row"><?= $pay['name'] ?? '' ?></td>
                                <td scope="row"><?= $pic['name'] ?? '' ?></td>
                                <td scope="row"><?= $row['price'] ?? '' ?></td>
                                <td scope="row"><?= $row['voucher'] ?? '' ?></td>
                                <td scope="row"><?= $row['act_shipping_fee'] ?? '' ?></td>
                                <td scope="row"><?= $row['service_fee'] ?? '' ?></td>
                                <td scope="row"><?= $row['trans_fee'] ?? '' ?></td>
                                <td scope="row"><?= $row['ams_fee'] ?? '' ?></td>
                                <td scope="row"><?= $row['fees'] ?? '' ?></td>
                                <td scope="row"><?= $row['final_amt'] ?? '' ?></td>
                                <td scope="row"><?= $row['remark'] ?? '' ?></td>
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
</body>
<script>
    dropdownMenuDispFix();
    datatableAlignment('shopee_order_req_table');
    keepDataTableControlsVisible('shopee_order_req_table');
</script>
</html>
