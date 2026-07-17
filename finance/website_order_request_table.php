<?php
$pageTitle = "Website Order Request";
$currentPagePin = 92;
$listPageSkipSessionReset = true;
$listPageSkipNumbering = true;


include_once '../include/list_page_header.php';

$canAssignEstimatedReceivedDate = isActionAllowed('Edit', $pinAccess);
$estimatedDateToday = new DateTimeImmutable('today');
$estimatedDateMin = $estimatedDateToday->modify('+1 day')->format('Y-m-d');
$estimatedDateMax = $estimatedDateToday->modify('+10 days')->format('Y-m-d');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentTablePath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$currentTableQuery = array();
$currentTableRawQuery = array();
parse_str((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY), $currentTableRawQuery);
unset($currentTableRawQuery['verify_id'], $currentTableRawQuery['complete_id']);
foreach ($currentTableRawQuery as $queryKey => $queryValue) {
    if (!is_array($queryValue)) {
        $currentTableQuery[$queryKey] = input($queryKey);
    }
}
$currentTableRedirect = $currentTablePath !== '' ? $currentTablePath : '/finance/website_order_request_table.php';
if (!empty($currentTableQuery)) {
    $currentTableRedirect .= '?' . http_build_query($currentTableQuery);
}

$verifyId = (int) numberInput('verify_id');
$completeId = (int) numberInput('complete_id');

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
    $assignmentResult = assignEstimatedReceivedDate($finance_connect, WEB_ORDER_REQ, $assignOrderId, $assignDate, USER_ID);

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
            'query_table' => WEB_ORDER_REQ,
            'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
            'changes' => $changeSummary,
            'uid' => USER_ID,
            'act_msg' => $safeUserName . " assigned the Estimate Received Date <b>" . $safeAssignedDate . "</b> for Website order [ <b>ID = " . (int) $assignOrderId . "</b> ].",
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('resendTelegramBtn')) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', $currentTableRedirect, 1200, true);
        exit;
    }

    $resendResult = shopeeOmsResendWarehouseNotification(
        $connect,
        $finance_connect,
        postSpaceFilter('resend_telegram_order_id'),
        'website',
        USER_ID,
        $pageTitle
    );
    $resendMessage = (string) (isset($resendResult['message']) ? $resendResult['message'] : 'Unable to resend Telegram message.');
    renderNotificationScript($resendMessage, !empty($resendResult['sent']) ? 'success' : 'error', $currentTableRedirect, 1200, true);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('moveToPackBtn')) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', (string) $_SERVER['REQUEST_URI'], 1200, true);
        exit;
    }

    $moveOrderId = (int) postSpaceFilter('move_to_pack_order_id');
    $moveToPackResult = shopeeOmsExecuteTransition($connect, $finance_connect, $moveOrderId, 'TP', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Moved to To Pack from Website order request table.',
        'action' => 'move_to_pack',
        'platform' => 'website',
    ));

    $moveToPackMessage = (string) (isset($moveToPackResult['message']) ? $moveToPackResult['message'] : 'Unable to move order to To Pack.');
    renderNotificationScript($moveToPackMessage, resolveNotificationType($moveToPackMessage, 'info'), $currentTableRedirect, 1200, true);
    exit;
}

if ($verifyId > 0) {
    $orderId = $verifyId;
    $verifyResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'V', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Verified from Website order request table.',
        'platform' => 'website',
    ));
    $verifyMessage = (string) (isset($verifyResult['message']) ? $verifyResult['message'] : 'Unable to verify order.');
    renderNotificationScript($verifyMessage, resolveNotificationType($verifyMessage, 'info'), $currentTableRedirect, 1200, true);
    exit;
}

if ($completeId > 0) {
    $orderId = $completeId;
    $completeResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'C', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Completed from Website order request table.',
        'platform' => 'website',
    ));
    $completeMessage = (string) (isset($completeResult['message']) ? $completeResult['message'] : 'Unable to complete order.');
    renderNotificationScript($completeMessage, resolveNotificationType($completeMessage, 'info'), $currentTableRedirect, 1200, true);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('force_wafc_id') !== '' && post('move_to_wafc_with_received_date_btn') === '') {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', $currentTableRedirect, 1200, true);
        exit;
    }

    $orderId = (int) post('force_wafc_id');
    $wafcResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'WAFC', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Moved to Waiting Admin Final Check without waiting 14 days.',
        'action' => 'manual_force_wafc',
        'skip_permission' => true,
        'allow_auto_follow_up' => false,
        'platform' => 'website',
    ));
    $wafcMessage = (string) (isset($wafcResult['message']) ? $wafcResult['message'] : 'Unable to move order to WAFC.');
    renderNotificationScript($wafcMessage, resolveNotificationType($wafcMessage, 'info'), $currentTableRedirect, 1200, true);
    exit;
}

shopeeOmsHandleMoveToWafcWithReceivedDatePost($connect, $finance_connect, array(
    'redirect_url' => $currentTableRedirect,
    'source_page' => $pageTitle,
    'platform' => 'website',
    'actor_user_id' => USER_ID,
    'actor_user_group_id' => USER_GROUP,
    'audit_connect' => $connect,
    'query_table' => WEB_ORDER_REQ,
));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('return_id') !== '') {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', $currentTableRedirect, 1200, true);
        exit;
    }

    $orderId = (int) post('return_id');
    $returnResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'R', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Marked as Return from Website order request table.',
        'action' => 'mark_return',
        'platform' => 'website',
    ));
    $returnMessage = (string) (isset($returnResult['message']) ? $returnResult['message'] : 'Unable to mark order as Return.');
    renderNotificationScript($returnMessage, resolveNotificationType($returnMessage, 'info'), $currentTableRedirect, 1200, true);
    exit;
}

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirectPage = $SITEURL . '/finance/website_order_request.php';
$deleteRedirectPage = $SITEURL . '/finance/website_order_request_table.php';
$websiteImportUrl = $SITEURL . '/import/website_order_import.php';

$monthInput = input('month');
$monthFilter = $monthInput === 'All'
    ? ''
    : (($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) ? $monthInput : date('Y-m'));
$statusFilter = input('status');
$brandFilter = numberInput('brand');
$pkgFilter = numberInput('pkg');
$picFilter = numberInput('pic');

$monthGroupInput = input('month_gb');
$monthGroup = $monthGroupInput === 'All'
    ? 'All'
    : (($monthGroupInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthGroupInput)) ? $monthGroupInput : '');
$statusGroup = input('status_gb');
$brandGroup = numberInput('brand_gb');
$pkgGroup = numberInput('pkg_gb');
$picGroup = numberInput('pic_gb');
$statusFilterCode = shopeeOmsNormalizeStatusCode($statusFilter);
$statusGroupCode = shopeeOmsNormalizeStatusCode($statusGroup);

$whereConditions = array();
if ($monthFilter !== '') {
    $whereConditions[] = "DATE_FORMAT(create_date, '%Y-%m') = '" . mysqli_real_escape_string($finance_connect, $monthFilter) . "'";
}
if ($statusFilterCode !== '') {
    $statusCondition = shopeeOmsBuildOrderStatusFilterCondition($finance_connect, 'order_status', $statusFilterCode);
    if ($statusCondition !== '') {
        $whereConditions[] = $statusCondition;
    }
}
if ($brandFilter > 0) {
    $whereConditions[] = "FIND_IN_SET('" . mysqli_real_escape_string($finance_connect, (string) $brandFilter) . "', brand) > 0";
}
if ($pkgFilter > 0) {
    $whereConditions[] = "FIND_IN_SET('" . mysqli_real_escape_string($finance_connect, (string) $pkgFilter) . "', pkg) > 0";
}
if ($picFilter > 0) {
    $whereConditions[] = "pic = '" . mysqli_real_escape_string($finance_connect, (string) $picFilter) . "'";
}

$groupByFields = array();
if ($monthGroup !== '' && $monthGroup !== 'All') {
    $groupByFields[] = "DATE_FORMAT(create_date, '%Y-%m')";
}
if ($statusGroupCode !== '') {
    $groupByFields[] = 'order_status';
}
if ($brandGroup > 0) {
    $groupByFields[] = 'brand';
}
if ($pkgGroup > 0) {
    $groupByFields[] = 'pkg';
}
if ($picGroup > 0) {
    $groupByFields[] = 'pic';
}

$whereSql = implode(' AND ', $whereConditions);
$groupBySql = !empty($groupByFields) ? 'GROUP BY ' . implode(', ', $groupByFields) : '';

$monthSql = "SELECT DISTINCT DATE_FORMAT(create_date, '%Y-%m') AS month_value, DATE_FORMAT(create_date, '%M %Y') AS month_label FROM " . WEB_ORDER_REQ . " ORDER BY month_value DESC";
$monthResult = mysqli_query($finance_connect, $monthSql);
$monthOptions = array();
if ($monthResult) {
    while ($monthRow = mysqli_fetch_assoc($monthResult)) {
        $monthOptions[] = $monthRow;
    }
}

$statusSql = "SELECT DISTINCT order_status FROM " . WEB_ORDER_REQ . " ORDER BY order_status ASC";
$statusResult = mysqli_query($finance_connect, $statusSql);
$statusOptions = array();
if ($statusResult) {
    while ($statusRow = mysqli_fetch_assoc($statusResult)) {
        $statusCode = shopeeOmsNormalizeStatusCode(isset($statusRow['order_status']) ? $statusRow['order_status'] : '');
        if ($statusCode !== '' && !isset($statusOptions[$statusCode])) {
            $statusOptions[$statusCode] = getOrderStatusLabel($statusCode);
        }
    }
}

$brandResult = mysqli_query($connect, "SELECT id, name FROM " . BRAND . " ORDER BY name ASC");
$brandOptions = array();
if ($brandResult) {
    while ($brandRow = mysqli_fetch_assoc($brandResult)) {
        $brandOptions[] = $brandRow;
    }
}

$pkgResult = mysqli_query($connect, "SELECT id, name FROM " . PKG . " ORDER BY name ASC");
$pkgOptions = array();
if ($pkgResult) {
    while ($pkgRow = mysqli_fetch_assoc($pkgResult)) {
        $pkgOptions[] = $pkgRow;
    }
}

$picResult = mysqli_query($connect, "SELECT id, name FROM " . USR_USER . " ORDER BY name ASC");
$picOptions = array();
if ($picResult) {
    while ($picRow = mysqli_fetch_assoc($picResult)) {
        $picOptions[] = $picRow;
    }
}

$result = getData('*', $whereSql, $groupBySql, WEB_ORDER_REQ, $finance_connect);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">

</head>

<script>

    window.onload = autoToggleSections;

    $(document).ready(() => {
        createSortingTable('website_order_request_table');

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
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                </div>

                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2><?php echo $pageTitle ?></h2>
                        <div class="mt-auto mb-auto">
                            <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="addBtn" id="addBtn"
                                    href="<?= $redirectPage . "?act=I" ?>">
                                    <i class="fa-solid fa-plus"></i> Add Request
                                </a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Import", $pinAccess)) : ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="importBtn" id="importBtn"
                                    href="<?= htmlspecialchars((string) $websiteImportUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-file-import"></i> Import
                                </a>
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
                        <option value="All" <?= ($monthFilter === '') ? 'selected' : '' ?>>All Months</option>
                        <?php foreach ($monthOptions as $monthRow) {
                            $monthValue = isset($monthRow['month_value']) ? (string) $monthRow['month_value'] : '';
                            $monthLabel = isset($monthRow['month_label']) ? (string) $monthRow['month_label'] : $monthValue;
                            if ($monthValue === '') {
                                continue;
                            }
                            $selected = ($monthFilter === $monthValue) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Filter by Order Status</label>
                    <select id="statusFilter" name="status" class="form-select" onchange="applyFilterOrGroup('status', this)">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusOptions as $statusCode => $label) {
                            $selected = ($statusFilterCode === $statusCode) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars((string) $statusCode, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="brandFilter" class="form-label">Filter by Brand</label>
                    <select id="brandFilter" name="brand" class="form-select" onchange="applyFilterOrGroup('brand', this)">
                        <option value="">All Brands</option>
                        <?php foreach ($brandOptions as $brandRow) {
                            $brandId = isset($brandRow['id']) ? (string) $brandRow['id'] : '';
                            $selected = ((string) $brandFilter === $brandId) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($brandId, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $brandRow['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="pkgFilter" class="form-label">Filter by Package</label>
                    <select id="pkgFilter" name="pkg" class="form-select" onchange="applyFilterOrGroup('pkg', this)">
                        <option value="">All Packages</option>
                        <?php foreach ($pkgOptions as $pkgRow) {
                            $pkgId = isset($pkgRow['id']) ? (string) $pkgRow['id'] : '';
                            $selected = ((string) $pkgFilter === $pkgId) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($pkgId, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $pkgRow['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="picFilter" class="form-label">Filter by Person In Charge</label>
                    <select id="picFilter" name="pic" class="form-select" onchange="applyFilterOrGroup('pic', this)">
                        <option value="">All Persons In Charge</option>
                        <?php foreach ($picOptions as $picRow) {
                            $picId = isset($picRow['id']) ? (string) $picRow['id'] : '';
                            $selected = ((string) $picFilter === $picId) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($picId, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $picRow['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
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
                        <?php foreach ($monthOptions as $monthRow) {
                            $monthValue = isset($monthRow['month_value']) ? (string) $monthRow['month_value'] : '';
                            $monthLabel = isset($monthRow['month_label']) ? (string) $monthRow['month_label'] : $monthValue;
                            if ($monthValue === '') {
                                continue;
                            }
                            $selected = ($monthGroup === $monthValue) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statusGroupBy" class="form-label">Group by Order Status</label>
                    <select id="statusGroupBy" name="status_gb" class="form-select" onchange="applyFilterOrGroup('status_gb', this)">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusOptions as $statusCode => $label) {
                            $selected = ($statusGroupCode === $statusCode) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars((string) $statusCode, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="brandGroupBy" class="form-label">Group by Brand</label>
                    <select id="brandGroupBy" name="brand_gb" class="form-select" onchange="applyFilterOrGroup('brand_gb', this)">
                        <option value="">All Brands</option>
                        <?php foreach ($brandOptions as $brandRow) {
                            $brandId = isset($brandRow['id']) ? (string) $brandRow['id'] : '';
                            $selected = ((string) $brandGroup === $brandId) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($brandId, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $brandRow['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="pkgGroupBy" class="form-label">Group by Package</label>
                    <select id="pkgGroupBy" name="pkg_gb" class="form-select" onchange="applyFilterOrGroup('pkg_gb', this)">
                        <option value="">All Packages</option>
                        <?php foreach ($pkgOptions as $pkgRow) {
                            $pkgId = isset($pkgRow['id']) ? (string) $pkgRow['id'] : '';
                            $selected = ((string) $pkgGroup === $pkgId) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($pkgId, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $pkgRow['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="picGroupBy" class="form-label">Group by Person In Charge</label>
                    <select id="picGroupBy" name="pic_gb" class="form-select" onchange="applyFilterOrGroup('pic_gb', this)">
                        <option value="">All Persons In Charge</option>
                        <?php foreach ($picOptions as $picRow) {
                            $picId = isset($picRow['id']) ? (string) $picRow['id'] : '';
                            $selected = ((string) $picGroup === $picId) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($picId, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $picRow['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label d-block invisible">Reset</label>
                    <a href="<?= htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-danger filter-reset">Reset</a>
                </div>
            </div>

            <?php if (!$result || $result->num_rows == 0) {
                echo '<div class="text-center"><h4>No Result!</h4></div>';
            } else { ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="website_order_request_table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Order Status</th>
                                <th scope="col">Estimate Received Date</th>
                                <th scope="col">Order ID</th>
                                <th scope="col">Brand</th>
                                <th scope="col">Series</th>
                                <th scope="col">Package</th>
                                <th scope="col">Country</th>
                                <th scope="col">Currency</th>
                                <th scope="col">Price</th>
                                <th scope="col">Shipping</th>
                                <th scope="col">Discount Price</th>
                                <th scope="col">Total</th>
                                <th scope="col">Payment Method</th>
                                <th scope="col">Person In Charges</th>
                                <th scope="col">Customer ID</th>
                                <th scope="col">Customer Name</th>
                                <th scope="col">Customer Email</th>
                                <th scope="col">Customer Birthday</th>
                                <th scope="col">Shipping Name</th>
                                <th scope="col">Shipping Address</th>
                                <th scope="col">Shipping Contact</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                $row = shopeeOmsApplyReturnedOrderFinancials($row, 'website');
                                // Default all mapped values to blank
                                $brand = $series = $pkg = $country = $currency = $pay_method = $pic = $cust_id = '';

                                if (!empty($row['brand'])) {
                                    $brand_rst = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                                    if ($brand_rst && $brand_rst->num_rows > 0) {
                                        $brand_row = $brand_rst->fetch_assoc();
                                        $brand = isset($brand_row['name']) ? $brand_row['name'] : '';
                                    } else {
                                        // Fallback: If it's stored as a string name instead of an ID, display it directly
                                        $brand = $row['brand']; 
                                    }
                                }

                                if (!empty($row['series'])) {
                                    $series_rst = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                                    if ($series_rst && $series_rst->num_rows > 0) {
                                        $series_row = $series_rst->fetch_assoc();
                                        $series = isset($series_row['name']) ? $series_row['name'] : '';
                                    } else {
                                        // Fallback: If it's stored as a string name instead of an ID, display it directly
                                        $series = $row['series'];
                                    }
                                }

                                if (!empty($row['pkg'])) {
                                    $pkg_rst = getData('name', "id='" . $row['pkg'] . "'", '', PKG, $connect);
                                    if ($pkg_rst && $pkg_rst->num_rows > 0) {
                                        $pkg_row = $pkg_rst->fetch_assoc();
                                        $pkg = isset($pkg_row['name']) ? $pkg_row['name'] : '';
                                    }
                                }

                                if (!empty($row['country'])) {
                                    $country_rst = getData('nicename', "id='" . $row['country'] . "'", '', COUNTRIES, $connect);
                                    if ($country_rst && $country_rst->num_rows > 0) {
                                        $country_row = $country_rst->fetch_assoc();
                                        $country = isset($country_row['nicename']) ? $country_row['nicename'] : '';
                                    }
                                }

                                if (!empty($row['currency'])) {
                                    $currency_rst = getData('unit', "id='" . $row['currency'] . "'", '', CUR_UNIT, $connect);
                                    if ($currency_rst && $currency_rst->num_rows > 0) {
                                        $currency_row = $currency_rst->fetch_assoc();
                                        $currency = isset($currency_row['unit']) ? $currency_row['unit'] : '';
                                    }
                                }

                                if (!empty($row['pay_method'])) {
                                    $pay_rst = getData('name', "id='" . $row['pay_method'] . "'", '', FIN_PAY_METH, $finance_connect);
                                    if ($pay_rst && $pay_rst->num_rows > 0) {
                                        $pay_row = $pay_rst->fetch_assoc();
                                        $pay_method = isset($pay_row['name']) ? $pay_row['name'] : '';
                                    }
                                }

                                if (!empty($row['pic'])) {
                                    $pic_rst = getData('name', "id='" . $row['pic'] . "'", '', USR_USER, $connect);
                                    if ($pic_rst && $pic_rst->num_rows > 0) {
                                        $pic_row = $pic_rst->fetch_assoc();
                                        $pic = isset($pic_row['name']) ? $pic_row['name'] : '';
                                    } else {
                                        $pic = $row['pic']; 
                                    }
                                }

                                if (!empty($row['cust_id'])) {
                                    $cust_rst = getData('cust_id', "id='" . $row['cust_id'] . "'", '', WEB_CUST_RCD, $connect);
                                    if ($cust_rst && $cust_rst->num_rows > 0) {
                                        $cust_row = $cust_rst->fetch_assoc();
                                        $cust_id = isset($cust_row['cust_id']) ? $cust_row['cust_id'] : '';
                                    } else {
                                        $cust_id = $row['cust_id']; 
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="hideColumn" scope="row"><?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= $num++ ?></td>
                                    <td scope="row" class="btn-container">
                                        <div class="d-flex align-items-center">
                                            <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['order_id'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                            <?php
                                            $statusCode = shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '');
                                            $canAssignThisOrder = $canAssignEstimatedReceivedDate && shopeeOmsPassesAssignmentScope($connect, $row, USER_ID, USER_GROUP);
                                            $canMoveToPackThisOrder = shopeeOmsHasTransitionPermission($connect, $statusCode, 'TP', USER_GROUP, $row, USER_ID);
                                            $canVerifyThisOrder = shopeeOmsHasTransitionPermission($connect, $statusCode, 'V', USER_GROUP, $row, USER_ID);
                                            $canCompleteThisOrder = shopeeOmsHasTransitionPermission($connect, $statusCode, 'C', USER_GROUP, $row, USER_ID);
                                            $estimatedDateRange = function_exists('shopeeOmsGetEstimatedReceivedDateRange')
                                                ? shopeeOmsGetEstimatedReceivedDateRange($row)
                                                : array('min_date' => $estimatedDateMin, 'max_date' => $estimatedDateMax);
                                            $verifyQuery = $currentTableQuery;
                                            $verifyQuery['verify_id'] = (int) $row['id'];
                                            $completeQuery = $currentTableQuery;
                                            $completeQuery['complete_id'] = (int) $row['id'];
                                            $verifyActionUrl = $currentTablePath . '?' . http_build_query($verifyQuery);
                                            $completeActionUrl = $currentTablePath . '?' . http_build_query($completeQuery);
                                            ?>
                                            <?php if ($statusCode === 'P' && $canMoveToPackThisOrder) { ?>
                                                <form method="post" class="d-inline me-1" onsubmit="return confirm('Move this order to To Pack?')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="move_to_pack_order_id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-rounded btn-info" name="moveToPackBtn" value="1" title="Move to To Pack"><i class="fas fa-box-open"></i></button>
                                                </form>
                                            <?php } ?>
                                            <?php if ($statusCode === 'TP') { ?>
                                                <a class="btn btn-sm btn-rounded btn-primary me-1" href="<?= htmlspecialchars((string) shopeeOmsGetOrderSourceInfoUrl('website', (int) $row['id']), ENT_QUOTES, 'UTF-8') ?>" title="Open QR Info"><i class="fa-solid fa-qrcode"></i></a>
                                                <form method="post" class="d-inline me-1" onsubmit="return commonConfirmFormSubmit(this, 'Resend Telegram Message');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="resend_telegram_order_id" value="<?= (int) $row['id'] ?>">
                                                    <input type="hidden" name="resendTelegramBtn" value="1">
                                                    <button type="submit" class="btn btn-sm btn-rounded btn-info" name="resendTelegramBtn" value="1" title="Resend Telegram Message"><i class="fa-brands fa-telegram"></i></button>
                                                </form>
                                            <?php } ?>
                                            <?php if (shouldShowEstimatedReceivedDateButton($row) && $canAssignThisOrder) { ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-warning btn-assign-estimated-date"
                                                    data-order-id="<?= (int) $row['id'] ?>"
                                                    data-order-code="<?= htmlspecialchars((string) (isset($row['order_id']) ? $row['order_id'] : ('Website Order #' . (int) $row['id'])), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-min-date="<?= htmlspecialchars((string) $estimatedDateRange['min_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-max-date="<?= htmlspecialchars((string) $estimatedDateRange['max_date'], ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                            <?php } ?>
                                            <?php if ($statusCode === 'WAFC' && $canVerifyThisOrder) { ?>
                                                <a href="<?= htmlspecialchars((string) $verifyActionUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-success btn-verified" onclick="return confirm('Mark this order as verified?')">Verified</a>
                                            <?php } ?>
                                            <?php if ($statusCode === 'V' && $canCompleteThisOrder) { ?>
                                                <a href="<?= htmlspecialchars((string) $completeActionUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-dark btn-verified" onclick="return confirm('Mark this order as complete?')">Complete</a>
                                            <?php } ?>
                                            <?php if (in_array($statusCode, array('SP', 'WAERD', 'WR', 'PD', 'PR', 'WAFC', 'V', 'C'), true)) { ?>
                                                <?php if ($statusCode === 'PR') { ?>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-rounded btn-info btn-open-received-date-modal"
                                                        data-order-id="<?= (int) $row['id'] ?>"
                                                        data-order-code="<?= htmlspecialchars((string) (isset($row['order_id']) ? $row['order_id'] : ('Website Order #' . (int) $row['id'])), ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Move to WAFC Now"><i class="fas fa-forward"></i></button>
                                                <?php } ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Mark this order as Return?')">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="return_id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-rounded btn-warning" title="Mark as Return"><i class="fa-solid fa-rotate-left"></i></button>
                                                </form>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td scope="row"><?= getMarketplaceRequestStatusLabel(isset($row['order_status']) ? $row['order_status'] : '') ?></td>
                                    <td scope="row"><?= isset($row['estimated_received_date']) && !empty($row['estimated_received_date']) ? htmlspecialchars((string) $row['estimated_received_date'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['order_id']) ? $row['order_id'] : '' ?></td>
                                    <td scope="row"><?= $brand ?></td>
                                    <td scope="row"><?= $series ?></td>
                                    <td scope="row"><?= $pkg ?></td>
                                    <td scope="row"><?= $country ?></td>
                                    <td scope="row"><?= $currency ?></td>
                                    <td scope="row"><?= isset($row['price']) ? $row['price'] : '' ?></td>
                                    <td scope="row"><?= isset($row['shipping']) ? $row['shipping'] : '' ?></td>
                                    <td scope="row"><?= isset($row['discount']) ? $row['discount'] : '' ?></td>
                                    <td scope="row"><?= isset($row['total']) ? $row['total'] : '' ?></td>
                                    <td scope="row"><?= $pay_method ?></td>
                                    <td scope="row"><?= $pic ?></td>
                                    <td scope="row"><?= $cust_id ?></td>
                                    <td scope="row"><?= isset($row['cust_name']) ? $row['cust_name'] : '' ?></td>
                                    <td scope="row"><?= isset($row['cust_email']) ? $row['cust_email'] : '' ?></td>
                                    <td scope="row"><?= isset($row['cust_birthday']) ? $row['cust_birthday'] : '' ?></td>
                                    <td scope="row"><?= isset($row['shipping_name']) ? $row['shipping_name'] : '' ?></td>
                                    <td scope="row"><?= isset($row['shipping_address']) ? $row['shipping_address'] : '' ?></td>
                                    <td scope="row"><?= isset($row['shipping_contact']) ? $row['shipping_contact'] : '' ?></td>
                                    <td scope="row"><?= isset($row['remark']) ? $row['remark'] : '' ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Order Status</th>
                                <th scope="col">Estimate Received Date</th>
                                <th scope="col">Order ID</th>
                                <th scope="col">Brand</th>
                                <th scope="col">Series</th>
                                <th scope="col">Package</th>
                                <th scope="col">Country</th>
                                <th scope="col">Currency</th>
                                <th scope="col">Price</th>
                                <th scope="col">Shipping</th>
                                <th scope="col">Discount Price</th>
                                <th scope="col">Total</th>
                                <th scope="col">Payment Method</th>
                                <th scope="col">Person In Charges</th>
                                <th scope="col">Customer ID</th>
                                <th scope="col">Customer Name</th>
                                <th scope="col">Customer Email</th>
                                <th scope="col">Customer Birthday</th>
                                <th scope="col">Shipping Name</th>
                                <th scope="col">Shipping Address</th>
                                <th scope="col">Shipping Contact</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>

    <?php include_once ROOT . '/include/estimated_date_modal.php'; ?>
    <?php shopeeOmsRenderReceivedDateModal(array('wrapper_attributes' => 'aria-hidden="true"')); ?>

</body>
<script>
    dropdownMenuDispFix();
    datatableAlignment('website_order_request_table');
</script>
<?php shopeeOmsRenderReceivedDateModalScript(); ?>

</html>
