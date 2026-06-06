<?php
if (ob_get_level() === 0) {
    ob_start();
}

$customerFollowUpBootstrapIsAjax = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
    && (
        (isset($_POST['customer_follow_up_ajax']) && (string) $_POST['customer_follow_up_ajax'] === '1')
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(trim((string) $_SERVER['HTTP_X_REQUESTED_WITH'])) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower((string) $_SERVER['HTTP_ACCEPT']), 'application/json') !== false)
    );

$currentPagePin = 151;
$pageTitle = 'Customer Follow-Up';
$displayPageTitle = 'Customer Follow-Up';
$disablePinGroupPageTitleSync = true;

include_once 'include/connection.php';
include_once 'include/common.php';
include_once 'include/common_variable.php';
include_once 'checkCurrentPagePin.php';
include_once ROOT . '/include/customer_follow_up_common.php';

if (!$customerFollowUpBootstrapIsAjax) {
    include_once 'menuHeader.php';
}

if (!function_exists('customerFollowUpPageGetAllowedPinIds')) {
    function customerFollowUpPageGetAllowedPinIds($connect, $pinGroupId)
    {
        $pinGroupRow = findPinGroupRowById($connect, $pinGroupId);
        if (!$pinGroupRow || !isset($pinGroupRow['pins'])) {
            return array();
        }

        $groupPinIds = array();
        foreach (explode(',', (string) $pinGroupRow['pins']) as $pinId) {
            $pinId = trim((string) $pinId);
            if ($pinId !== '' && ctype_digit($pinId)) {
                $groupPinIds[] = (int) $pinId;
            }
        }

        $userPinArray = getUserPinGroup($connect);
        $userPinIds = array();
        foreach (getValuesByPinAssocIndex($userPinArray, $pinGroupId) as $pinId) {
            $pinId = trim((string) $pinId);
            if ($pinId !== '' && ctype_digit($pinId)) {
                $userPinIds[] = (int) $pinId;
            }
        }

        return array_values(array_unique(array_intersect($groupPinIds, $userPinIds)));
    }
}

$pageAccess = checkPinByGroupId($connect, $currentPagePin);
$canViewPage = isActionAllowed('View', $pageAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Customer Follow-Up."); location.replace("dashboard.php");</script>';
    exit;
}

$pagePinIds = customerFollowUpPageGetAllowedPinIds($connect, $currentPagePin);
$canViewLogsPermission = defined('USER_GROUP') && (int) USER_GROUP === 1;
$canApprovePermission = in_array(11, $pagePinIds, true);
$canRejectPermission = in_array(12, $pagePinIds, true);

if (empty($_SESSION['customer_follow_up_csrf'])) {
    $_SESSION['customer_follow_up_csrf'] = bin2hex(random_bytes(32));
}

if (!function_exists('customerFollowUpPageFlashSet')) {
    function customerFollowUpPageFlashSet($type, $message)
    {
        $_SESSION['customer_follow_up_flash'] = array(
            'type' => $type,
            'message' => $message,
        );
    }
}

if (!function_exists('customerFollowUpPageIsAjaxRequest')) {
    function customerFollowUpPageIsAjaxRequest()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        if (isset($_POST['customer_follow_up_ajax']) && (string) $_POST['customer_follow_up_ajax'] === '1') {
            return true;
        }

        $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower(trim((string) $_SERVER['HTTP_X_REQUESTED_WITH'])) : '';
        $acceptHeader = isset($_SERVER['HTTP_ACCEPT']) ? strtolower((string) $_SERVER['HTTP_ACCEPT']) : '';

        return $requestedWith === 'xmlhttprequest' || strpos($acceptHeader, 'application/json') !== false;
    }
}

if (!function_exists('customerFollowUpPageClearOutputBuffers')) {
    function customerFollowUpPageClearOutputBuffers()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

if (!function_exists('customerFollowUpPageJsonResponse')) {
    function customerFollowUpPageJsonResponse($type, $message, $extra = array())
    {
        customerFollowUpPageClearOutputBuffers();

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode(array_merge(array(
            'success' => $type === 'success',
            'type' => $type,
            'message' => (string) $message,
        ), is_array($extra) ? $extra : array()));
        exit;
    }
}

if (!function_exists('customerFollowUpPageFinishResponse')) {
    function customerFollowUpPageFinishResponse($result)
    {
        $success = !empty($result['success']);
        $type = $success ? 'success' : 'danger';
        $message = isset($result['message']) ? (string) $result['message'] : 'Action is invalid.';

        if (customerFollowUpPageIsAjaxRequest()) {
            customerFollowUpPageJsonResponse($type, $message);
        }

        customerFollowUpPageFlashSet($type, $message);
        customerFollowUpPageRedirectBack();
    }
}

if (!function_exists('customerFollowUpPageRedirectBack')) {
    function customerFollowUpPageRedirectBack()
    {
        $target = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : 'customer_follow_up_list.php';
        echo '<script>location.replace(' . json_encode($target) . ');</script>';
        exit;
    }
}

if (!function_exists('customerFollowUpPagePlatformLabel')) {
    function customerFollowUpPagePlatformLabel($platform)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $labels = array(
            'shopee' => 'Shopee',
            'lazada' => 'Lazada',
            'facebook' => 'Facebook',
            'website' => 'Website',
            'customer_info' => 'Whatsapp Customer',
        );
        return isset($labels[$platform]) ? $labels[$platform] : ucfirst((string) $platform);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8') . " viewed the page <b>" . htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : date('m');
$selectedYear = isset($_GET['year']) ? trim((string) $_GET['year']) : date('Y');
$selectedDate = trim((string) input('date'));
$currentYear = date('Y');
$selectedMonth = ($selectedMonth === '' || preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth)) ? $selectedMonth : date('m');
$selectedYear = ($selectedYear === '' || preg_match('/^\d{4}$/', $selectedYear)) ? $selectedYear : $currentYear;
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : '';
$statusFilter = trim((string) input('status'));
$platformFilter = customerFollowUpNormalizePlatform(input('platform'));
$customerTypeFilter = trim((string) input('customer_type'));
$assignedUserFilter = (int) input('assigned_user_id');
$canViewAllFollowUpRecords = customerFollowUpIsAdminUser(defined('USER_GROUP') ? USER_GROUP : null);
if (!$canViewAllFollowUpRecords) {
    $assignedUserFilter = defined('USER_ID') ? (int) USER_ID : 0;
}
$followUpIdFilter = (int) input('follow_up_id');
$roundIdFilter = (int) input('round_id');
$missedOnlyFilter = trim((string) input('missed_only')) === '1';
$lostOnlyFilter = trim((string) input('lost_only')) === '1';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedCsrfToken = isset($_POST['customer_follow_up_csrf']) ? (string) $_POST['customer_follow_up_csrf'] : '';
    if (!hash_equals((string) $_SESSION['customer_follow_up_csrf'], $postedCsrfToken)) {
        customerFollowUpPageFinishResponse(array(
            'success' => false,
            'message' => 'Invalid session token. Please refresh and try again.',
        ));
    }

    $followUpId = (int) postSpaceFilter('follow_up_id');
    $action = trim((string) postSpaceFilter('cfu_action'));
    $result = array('success' => false, 'message' => 'Action is invalid.');

    switch ($action) {
        case 'submit_follow_up':
            $result = customerFollowUpSubmitRound($connect, $followUpId, array(
                'message_shortcut_id' => postSpaceFilter('message_shortcut_id'),
                'next_follow_up_date' => postSpaceFilter('next_follow_up_date'),
                'contact_no' => postSpaceFilter('contact_no'),
            ), isset($_FILES['attachment']) ? $_FILES['attachment'] : array(), USER_ID, USER_GROUP);
            break;

        case 'approve_follow_up':
            if (!$canApprovePermission) {
                $result = array('success' => false, 'message' => 'You do not have approval permission for Customer Follow-Up.');
                break;
            }
            $result = customerFollowUpApproveRound($connect, $followUpId, USER_ID, USER_GROUP);
            break;

        case 'reject_follow_up':
            if (!$canRejectPermission) {
                $result = array('success' => false, 'message' => 'You do not have decline permission for Customer Follow-Up.');
                break;
            }
            $result = customerFollowUpRejectRound($connect, $followUpId, postSpaceFilter('reject_reason'), USER_ID, USER_GROUP);
            break;

        case 'complete_follow_up':
            $result = customerFollowUpCompleteCurrentRound($connect, $followUpId, USER_ID, USER_GROUP);
            break;

        case 'request_postponement':
            $result = customerFollowUpRequestPostponement($connect, $followUpId, postSpaceFilter('postpone_reason'), postSpaceFilter('requested_next_follow_up_date'), USER_ID, USER_GROUP);
            break;

        case 'approve_postponement':
            if (!$canApprovePermission) {
                $result = array('success' => false, 'message' => 'You do not have approval permission for Customer Follow-Up.');
                break;
            }
            $result = customerFollowUpApprovePostponement($connect, $followUpId, USER_ID, USER_GROUP);
            break;

        case 'reject_postponement':
            if (!$canRejectPermission) {
                $result = array('success' => false, 'message' => 'You do not have decline permission for Customer Follow-Up.');
                break;
            }
            $result = customerFollowUpRejectPostponement($connect, $followUpId, postSpaceFilter('postpone_reject_reason'), USER_ID, USER_GROUP);
            break;

        case 'save_delay_reason':
            $result = customerFollowUpSaveDelayReason($connect, $followUpId, postSpaceFilter('delay_reason'), USER_ID, USER_GROUP);
            break;
    }

    customerFollowUpPageFinishResponse($result);
}

$flashMessage = isset($_SESSION['customer_follow_up_flash']) && is_array($_SESSION['customer_follow_up_flash']) ? $_SESSION['customer_follow_up_flash'] : array();
unset($_SESSION['customer_follow_up_flash']);

$messageShortcutOptions = customerFollowUpGetMessageShortcutOptions($connect);
$messageShortcutMap = array();
foreach ($messageShortcutOptions as $shortcutRow) {
    $shortcutId = isset($shortcutRow['id']) ? (int) $shortcutRow['id'] : 0;
    if ($shortcutId > 0) {
        $messageShortcutMap[$shortcutId] = $shortcutRow;
    }
}

$assignedUsers = array();
$userResult = getData('id,name,username', '', '', USR_USER, $connect);
if ($userResult) {
    while ($userRow = $userResult->fetch_assoc()) {
        $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
        if (!$canViewAllFollowUpRecords && defined('USER_ID') && $userId !== (int) USER_ID) {
            continue;
        }
        $assignedUsers[] = $userRow;
    }
}

$whereConditions = array("f.`status` = 'A'");
$effectiveStatusSql = "LOWER(CASE WHEN LOWER(IFNULL(r.`postpone_status`, 'none')) = 'pending' THEN 'pending approval' ELSE COALESCE(NULLIF(TRIM(r.`round_status`), ''), NULLIF(TRIM(f.`current_status`), '')) END)";
if ($platformFilter !== '') {
    $whereConditions[] = "f.`platform` = '" . customerFollowUpEscape($connect, $platformFilter) . "'";
}
if ($statusFilter !== '') {
    $whereConditions[] = $effectiveStatusSql . " = '" . customerFollowUpEscape($connect, strtolower($statusFilter)) . "'";
}
if ($assignedUserFilter > 0) {
    $whereConditions[] = "f.`assigned_user_id` = " . $assignedUserFilter;
}
if ($customerTypeFilter !== '') {
    $whereConditions[] = "LOWER(f.`customer_type`) = '" . customerFollowUpEscape($connect, strtolower($customerTypeFilter)) . "'";
}
if ($selectedDate !== '') {
    $whereConditions[] = "DATE(r.`next_follow_up_date`) = '" . customerFollowUpEscape($connect, $selectedDate) . "'";
} else {
    $scheduledDateConditions = array();
    if ($selectedMonth !== '') {
        $scheduledDateConditions[] = "MONTH(r.`next_follow_up_date`) = '" . customerFollowUpEscape($connect, $selectedMonth) . "'";
    }
    if ($selectedYear !== '') {
        $scheduledDateConditions[] = "YEAR(r.`next_follow_up_date`) = '" . customerFollowUpEscape($connect, $selectedYear) . "'";
    }
    if (!empty($scheduledDateConditions)) {
        $whereConditions[] = "((" . implode(' AND ', $scheduledDateConditions) . ") OR (r.`next_follow_up_date` IS NULL AND IFNULL(TRIM(r.`round_status`), '') = ''))";
    }
}
if ($followUpIdFilter > 0) {
    $whereConditions[] = "f.`id` = " . $followUpIdFilter;
}
if ($roundIdFilter > 0) {
    $whereConditions[] = "r.`id` = " . $roundIdFilter;
}
if ($missedOnlyFilter) {
    $whereConditions[] = $effectiveStatusSql . " = 'missed follow-up'";
}
if ($lostOnlyFilter) {
    $whereConditions[] = $effectiveStatusSql . " = 'lost'";
}

$listSql = "SELECT
        f.*,
        r.`id` AS `round_id`,
        r.`round_no`,
        r.`stage_no`,
        r.`next_follow_up_date`,
        r.`previous_follow_up_date`,
        r.`attachment`,
        r.`message_shortcut_id`,
        r.`message_shortcut_text`,
        r.`contact_no` AS `round_contact_no`,
        r.`approval_status`,
        r.`reject_reason`,
        r.`postpone_status`,
        r.`postpone_reason`,
        r.`postpone_reject_reason`,
        r.`delay_reason`,
        r.`missed_original_date`,
        r.`completed_date`,
        r.`round_status`
    FROM `" . CUSTOMER_FOLLOW_UP . "` f
    LEFT JOIN `" . CUSTOMER_FOLLOW_UP_ROUND . "` r
        ON r.`follow_up_id` = f.`id`
       AND r.`round_no` = f.`current_round_no`
       AND r.`status` = 'A'
    WHERE " . implode(' AND ', $whereConditions) . "
    ORDER BY
        CASE WHEN r.`next_follow_up_date` IS NULL THEN 1 ELSE 0 END ASC,
        r.`next_follow_up_date` ASC,
        f.`id` DESC";
$listResult = mysqli_query($connect, $listSql);
$rows = array();
$userIds = array();
if ($listResult) {
    while ($row = mysqli_fetch_assoc($listResult)) {
        $rows[] = $row;
        $userIds[] = isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : 0;
    }
}

$userMetaMap = customerFollowUpGetUserMetaMap($connect, $userIds);
$actionLogMap = array();
if ($canViewLogsPermission) {
    foreach ($rows as $row) {
        $followUpId = isset($row['id']) ? (int) $row['id'] : 0;
        if ($followUpId > 0 && !isset($actionLogMap[$followUpId])) {
            $actionLogMap[$followUpId] = customerFollowUpFetchActionLogRows($connect, $followUpId, 20);
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .customer-follow-up-page .btn {
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            margin: 3px;
        }

        .customer-follow-up-page .modal-footer .btn,
        .customer-follow-up-page .modal-header .btn,
        .customer-follow-up-page .modal-body .btn {
            text-transform: none !important;
        }

        .customer-follow-up-page .btn-container {
            white-space: nowrap;
        }

        .customer-follow-up-page .btn-action-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: nowrap;
            gap: 0.45rem;
            white-space: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
        }

        .customer-follow-up-page .btn-action-row form {
            margin: 0;
            flex: 0 0 auto;
        }

        .customer-follow-up-page .btn-action-row .btn {
            margin: 0;
            height: auto;
            width: auto;
            min-width: 0;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            line-height: 1;
            box-sizing: border-box;
            vertical-align: middle;
        }

        .customer-follow-up-page .btn-action-row .btn i {
            font-size: 0.95rem;
        }

        .customer-follow-up-page .action-col {
            width: 1%;
            min-width: 140px;
            text-align: left;
        }

        .customer-follow-up-page .filter-card {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            background: #fff;
        }

        .customer-follow-up-page .follow-up-filter-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .customer-follow-up-page .follow-up-date-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(0, 280px);
            gap: 16px;
        }

        .customer-follow-up-page .follow-up-date-filter-reset {
            display: flex;
            align-items: flex-end;
        }

        .customer-follow-up-page .follow-up-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
            border-radius: 999px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .customer-follow-up-page .follow-up-filter-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 16px;
        }

        .customer-follow-up-page .follow-up-note {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .customer-follow-up-page .arrival-follow-up-summary {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .customer-follow-up-page .arrival-follow-up-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .customer-follow-up-page .arrival-follow-up-summary-row:last-child {
            margin-bottom: 0;
        }

        .customer-follow-up-page .arrival-follow-up-summary-label {
            color: #6c757d;
        }

        .customer-follow-up-page .arrival-follow-up-contact-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 0.625rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: #f8f9fa;
        }

        .customer-follow-up-page .arrival-follow-up-contact-edit {
            border: 0;
            background: transparent;
            color: #0d6efd;
            padding: 0;
        }

        .customer-follow-up-page .table-responsive {
            overflow-x: auto;
        }

        .customer-follow-up-page .arrival-follow-up-preview {
            display: none;
            margin-top: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #f8f9fa;
            padding: 0.75rem;
        }

        .customer-follow-up-page .arrival-follow-up-preview img {
            display: block;
            max-width: 100%;
            max-height: 260px;
            margin: 0 auto;
            border-radius: 0.375rem;
            object-fit: contain;
        }

        .customer-follow-up-page .arrival-follow-up-preview-note {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
        }

        .customer-follow-up-page .arrival-follow-up-preview-link {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            word-break: break-all;
        }


        /* Same modal CSS as Arrival Management follow-up popup. Keep Submit and Resubmit visually identical. */
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

        .customer-follow-up-required-star {
            color: #dc3545;
            margin-left: 2px;
        }

        .customer-follow-up-field-error {
            display: none;
            color: #dc3545;
            font-size: 0.82rem;
            margin-top: 4px;
        }

        .customer-follow-up-field-error.is-visible {
            display: block;
        }

        .customer-follow-up-page .filter-check-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-height: 38px;
        }

        .customer-follow-up-page .filter-check-wrap .form-check {
            margin-bottom: 0;
        }

        .customer-follow-up-page .status-subtext,
        .customer-follow-up-page .delay-subtext {
            font-size: 0.78rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .customer-follow-up-page .log-table td,
        .customer-follow-up-page .log-table th {
            font-size: 0.82rem;
            vertical-align: top;
        }

        @media (max-width: 1199px) {
            .customer-follow-up-page .follow-up-date-filter-grid,
            .customer-follow-up-page .follow-up-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .customer-follow-up-page .follow-up-date-filter-grid,
            .customer-follow-up-page .follow-up-filter-grid {
                grid-template-columns: 1fr;
            }
        }



    </style>
</head>
<script>
    preloader(300);
</script>

<body class="customer-follow-up-page">
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover customer-follow-up-page">
        <div class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <div>
                                <h2 class="mb-1"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                                <div class="follow-up-note">Follow-up workflow, missed/lost monitoring, and approval handling are managed from this page.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-card p-3 mb-4">
                    <form method="get" id="customer_follow_up_filter_form">
                        <div class="follow-up-filter-stack">
                            <div class="follow-up-date-filter-grid">
                                <div>
                                    <label class="form-label" for="customer_follow_up_date">Date</label>
                                    <input type="date" class="form-control" id="customer_follow_up_date" name="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div>
                                    <label class="form-label" for="customer_follow_up_month">Month</label>
                                    <select class="form-select" id="customer_follow_up_month" name="month">
                                        <option value="">Select Month</option>
                                        <?php for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) { ?>
                                            <?php
                                            $monthValue = str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT);
                                            $monthLabel = date('F', mktime(0, 0, 0, $monthNumber, 1));
                                            ?>
                                            <option value="<?= htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') ?>" <?= $monthValue === $selectedMonth ? 'selected' : '' ?>><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="customer_follow_up_year">Year</label>
                                    <select class="form-select" id="customer_follow_up_year" name="year">
                                        <option value="">Select Year</option>
                                        <?php for ($yearValue = (int) $currentYear; $yearValue >= ((int) $currentYear - 5); $yearValue--) { ?>
                                            <option value="<?= htmlspecialchars((string) $yearValue, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $yearValue === $selectedYear ? 'selected' : '' ?>><?= htmlspecialchars((string) $yearValue, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="follow-up-date-filter-reset">
                                    <a class="btn btn-outline-secondary w-100 follow-up-reset-btn" href="<?= htmlspecialchars($SITEURL . '/customer_follow_up_list.php', ENT_QUOTES, 'UTF-8') ?>" style="text-transform: none !important;">Reset Filters</a>
                                </div>
                            </div>
                            <div class="follow-up-filter-grid">
                                <div>
                                    <label class="form-label" for="platform">Platform</label>
                                    <select class="form-select" id="platform" name="platform">
                                        <option value="">All Platform</option>
                                        <?php foreach (array('shopee', 'lazada', 'facebook', 'website', 'customer_info') as $platformOption) { ?>
                                            <option value="<?= htmlspecialchars($platformOption, ENT_QUOTES, 'UTF-8') ?>" <?= $platformFilter === $platformOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(customerFollowUpPagePlatformLabel($platformOption), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="status">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">All Status</option>
                                        <?php foreach (array('Pending Approval', 'Approved', 'Rejected', 'Missed Follow-Up', 'Done', 'Postponed', 'Lost') as $statusOption) { ?>
                                            <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= strtolower($statusFilter) === strtolower($statusOption) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="assigned_user_id">Assigned User</label>
                                    <select class="form-select" id="assigned_user_id" name="assigned_user_id">
                                        <option value="">All User</option>
                                        <?php foreach ($assignedUsers as $userRow) {
                                            $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
                                            $userLabel = trim((string) (isset($userRow['name']) && $userRow['name'] !== '' ? $userRow['name'] : $userRow['username']));
                                            if ($userId <= 0 || $userLabel === '') {
                                                continue;
                                            }
                                            ?>
                                            <option value="<?= $userId ?>" <?= $assignedUserFilter === $userId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="customer_type">Customer Type</label>
                                    <select class="form-select" id="customer_type" name="customer_type">
                                        <option value="">All Type</option>
                                        <option value="new" <?= strtolower($customerTypeFilter) === 'new' ? 'selected' : '' ?>>New</option>
                                        <option value="return" <?= strtolower($customerTypeFilter) === 'return' ? 'selected' : '' ?>>Return</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label d-block" for="missed_only">Extra Filter</label>
                                    <div class="filter-check-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="missed_only" name="missed_only" value="1" <?= $missedOnlyFilter ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="missed_only">Missed Only</label>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label d-block" for="lost_only">&nbsp;</label>
                                    <div class="filter-check-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="lost_only" name="lost_only" value="1" <?= $lostOnlyFilter ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="lost_only">Lost Only</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div id="customer_follow_up_table_section">
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($rows)) { ?>
                            <div class="text-center py-5">
                                <h5 class="mb-2">No active follow-up case found.</h5>
                                <div class="follow-up-note">Try adjusting the filters or wait for the next follow-up case to be created from the received-order workflow.</div>
                            </div>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-striped align-middle" id="customer_follow_up_table">
                                    <thead>
                                        <tr>
                                            <th class="hideColumn">ID</th>
                                            <th width="60">S/N</th>
                                            <th class="action-col">Actions</th>
                                            <th>Order ID</th>
                                            <th>Username</th>
                                            <th>Package</th>
                                            <th>Received Date</th>
                                            <th>Next Follow-Up Date</th>
                                            <th>Status</th>
                                            <th>WhatsApp / Contact Number</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $index => $row) {
                                            $followUpId = isset($row['id']) ? (int) $row['id'] : 0;
                                            $roundId = isset($row['round_id']) ? (int) $row['round_id'] : 0;
                                            $roundNo = max(1, (int) (isset($row['current_round_no']) ? $row['current_round_no'] : 1));
                                            $displayStatus = customerFollowUpNormalizeStatus(isset($row['current_status']) ? $row['current_status'] : '');
                                            $roundStatus = customerFollowUpNormalizeStatus(isset($row['round_status']) ? $row['round_status'] : '');
                                            $effectiveStatus = $roundStatus !== '' ? $roundStatus : $displayStatus;
                                            $resolvedDisplayStatus = customerFollowUpResolveDisplayStatus($row, $row);
                                            $displayNextFollowUpDate = customerFollowUpResolveRoundDisplayDate($row);
                                            $effectiveContactNo = trim((string) (isset($row['round_contact_no']) && $row['round_contact_no'] !== '' ? $row['round_contact_no'] : (isset($row['contact_no']) ? $row['contact_no'] : '')));
                                            $assignedUserId = isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : 0;
                                            $assignedUserMeta = isset($userMetaMap[$assignedUserId]) ? $userMetaMap[$assignedUserId] : array();
                                            $assignedUserLabel = isset($assignedUserMeta['display_name']) ? $assignedUserMeta['display_name'] : '';
                                            $customerType = strtolower(trim((string) (isset($row['customer_type']) ? $row['customer_type'] : 'new')));
                                            $maxDateInfo = customerFollowUpCalculateMaxAllowedNextFollowUpDate($row, $row);
                                            $normalizedPostponeStatus = strtolower(trim((string) (isset($row['postpone_status']) ? $row['postpone_status'] : '')));
                                            $pendingPostponePayload = $roundId > 0 && in_array($normalizedPostponeStatus, array('pending', 'approved', 'rejected'), true)
                                                ? customerFollowUpGetLatestPendingPostponeRequest($connect, $roundId)
                                                : array();
                                            $requestedPostponeDate = trim((string) (isset($pendingPostponePayload['requested_next_follow_up_date']) ? $pendingPostponePayload['requested_next_follow_up_date'] : ''));
                                            $delayReason = trim((string) (isset($row['delay_reason']) ? $row['delay_reason'] : ''));
                                            $missedOriginalDate = trim((string) (isset($row['missed_original_date']) ? $row['missed_original_date'] : ''));
                                            $postponeReason = trim((string) (isset($row['postpone_reason']) ? $row['postpone_reason'] : ''));
                                            $postponeRejectReason = trim((string) (isset($row['postpone_reject_reason']) ? $row['postpone_reject_reason'] : ''));
                                            $logRows = isset($actionLogMap[$followUpId]) ? $actionLogMap[$followUpId] : array();

                                            $canManageOwnCase = customerFollowUpCanUserManageCase($row, USER_ID, USER_GROUP, $connect);
                                            $canSubmit = $canManageOwnCase
                                                && !in_array($roundStatus, array('Pending Approval', 'Approved', 'Postponed', 'Missed Follow-Up', 'Done', 'Lost'), true)
                                                && $roundNo <= 6;
                                            $canSaveDelayReason = $canManageOwnCase && customerFollowUpRequiresDelayReasonBeforeMissedAction($row);
                                            $canComplete = $canManageOwnCase && customerFollowUpCanCompleteRound($row);
                                            $canRequestPostpone = $canManageOwnCase && customerFollowUpCanRequestPostponement($row);
                                            $canApprove = $canApprovePermission && $customerType === 'new' && $roundStatus === 'Pending Approval';
                                            $canReject = $canRejectPermission && $customerType === 'new' && $roundStatus === 'Pending Approval';
                                            $canApprovePostpone = $canApprovePermission && $normalizedPostponeStatus === 'pending';
                                            $canRejectPostpone = $canRejectPermission && $normalizedPostponeStatus === 'pending';
                                            $canViewDelayInfo = ($canViewLogsPermission || $canManageOwnCase) && ($delayReason !== '' || $missedOriginalDate !== '' || $roundStatus === 'Missed Follow-Up');
                                            $canViewPostponeInfo = ($canApprovePermission || $canRejectPermission || $canManageOwnCase) && ($postponeReason !== '' || $postponeRejectReason !== '' || $requestedPostponeDate !== '' || in_array($normalizedPostponeStatus, array('pending', 'approved', 'rejected'), true));
                                            $canViewLogs = $canViewLogsPermission && !empty($logRows);
                                            ?>
                                            <tr>
                                                <td class="hideColumn"><?= $followUpId ?></td>
                                                <td><?= $index + 1 ?></td>
                                                <td class="btn-container action-col">
                                                    <div class="btn-action-row">
                                                    <?php if ($canSubmit) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-primary"
                                                            title="<?= htmlspecialchars($roundStatus === 'Rejected' ? 'Resubmit Follow-Up' : 'Submit Follow-Up', ENT_QUOTES, 'UTF-8') ?>"
                                                            aria-label="<?= htmlspecialchars($roundStatus === 'Rejected' ? 'Resubmit Follow-Up' : 'Submit Follow-Up', ENT_QUOTES, 'UTF-8') ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#submitFollowUpModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-order-no="<?= htmlspecialchars((string) (isset($row['order_no']) ? $row['order_no'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-name="<?= htmlspecialchars((string) (isset($row['customer_name']) ? $row['customer_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-username="<?= htmlspecialchars((string) (isset($row['customer_username']) ? $row['customer_username'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-package-name="<?= htmlspecialchars((string) (isset($row['package_name']) ? $row['package_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-received-date="<?= htmlspecialchars((string) (isset($row['received_date']) ? $row['received_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-purchase-count="<?= (int) (isset($row['purchase_count_snapshot']) ? $row['purchase_count_snapshot'] : 0) ?>"
                                                            data-customer-type="<?= htmlspecialchars($customerType, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-message-shortcut-id="<?= isset($row['message_shortcut_id']) ? (int) $row['message_shortcut_id'] : 0 ?>"
                                                            data-next-follow-up-date="<?= htmlspecialchars($displayNextFollowUpDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-contact-no="<?= htmlspecialchars($effectiveContactNo, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-existing-attachment-path="<?= htmlspecialchars((string) (isset($row['attachment']) ? $row['attachment'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-existing-attachment-url="<?= htmlspecialchars((string) customerFollowUpBuildAttachmentUrl(isset($row['attachment']) ? $row['attachment'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid <?= $roundStatus === 'Rejected' ? 'fa-rotate-right' : 'fa-paper-plane' ?>"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canComplete) { ?>
                                                        <form method="post" class="d-inline customer-follow-up-action-form" data-confirm-message="Mark this follow-up as Done?">
                                                            <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="cfu_action" value="complete_follow_up">
                                                            <input type="hidden" name="follow_up_id" value="<?= $followUpId ?>">
                                                            <button type="submit" class="btn btn-sm btn-rounded btn-success" title="Complete Follow-Up" aria-label="Complete Follow-Up"><i class="fa-solid fa-check-double"></i></button>
                                                        </form>
                                                    <?php } ?>

                                                    <?php if ($canSaveDelayReason) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-dark"
                                                            title="Save Delay Reason"
                                                            aria-label="Save Delay Reason"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#delayReasonModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-missed-original-date="<?= htmlspecialchars($missedOriginalDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-delay-reason="<?= htmlspecialchars($delayReason, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-hourglass-half"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canRequestPostpone) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-warning"
                                                            title="Request Postpone"
                                                            aria-label="Request Postpone"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#postponeFollowUpModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-current-date="<?= htmlspecialchars($displayNextFollowUpDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-calendar-plus"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canApprove) { ?>
                                                        <form method="post" class="d-inline customer-follow-up-action-form" data-confirm-message="Approve this follow-up?">
                                                            <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="cfu_action" value="approve_follow_up">
                                                            <input type="hidden" name="follow_up_id" value="<?= $followUpId ?>">
                                                            <button type="submit" class="btn btn-sm btn-rounded btn-success" title="Approve Follow-Up" aria-label="Approve Follow-Up"><i class="fa-solid fa-circle-check"></i></button>
                                                        </form>
                                                    <?php } ?>

                                                    <?php if ($canReject) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-danger"
                                                            title="Reject Follow-Up"
                                                            aria-label="Reject Follow-Up"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#rejectFollowUpModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>">
                                                            <i class="fa-solid fa-circle-xmark"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canApprovePostpone) { ?>
                                                        <form method="post" class="d-inline customer-follow-up-action-form" data-confirm-message="Approve this postponement request?">
                                                            <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <input type="hidden" name="cfu_action" value="approve_postponement">
                                                            <input type="hidden" name="follow_up_id" value="<?= $followUpId ?>">
                                                            <button type="submit" class="btn btn-sm btn-rounded btn-info" title="Approve Postponement" aria-label="Approve Postponement"><i class="fa-solid fa-calendar-check"></i></button>
                                                        </form>
                                                    <?php } ?>

                                                    <?php if ($canRejectPostpone) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-danger"
                                                            title="Reject Postponement"
                                                            aria-label="Reject Postponement"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#rejectPostponeModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-requested-next-date="<?= htmlspecialchars($requestedPostponeDate, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-calendar-xmark"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canViewDelayInfo) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-secondary"
                                                            title="View Delay Info"
                                                            aria-label="View Delay Info"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewDelayInfoModal"
                                                            data-order-no="<?= htmlspecialchars((string) (isset($row['order_no']) ? $row['order_no'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-missed-original-date="<?= htmlspecialchars($missedOriginalDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-delay-reason="<?= htmlspecialchars($delayReason, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-eye"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canViewPostponeInfo) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-info"
                                                            title="View Postponement Info"
                                                            aria-label="View Postponement Info"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewPostponeInfoModal"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-postpone-status="<?= htmlspecialchars((string) (isset($row['postpone_status']) ? $row['postpone_status'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-requested-next-date="<?= htmlspecialchars($requestedPostponeDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-postpone-reason="<?= htmlspecialchars($postponeReason, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-postpone-reject-reason="<?= htmlspecialchars($postponeRejectReason, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-calendar-days"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canViewLogs) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-primary"
                                                            title="View Logs"
                                                            aria-label="View Logs"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#actionLogModal_<?= $followUpId ?>">
                                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                                        </button>
                                                    <?php } ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string) (isset($row['order_no']) && $row['order_no'] !== '' ? $row['order_no'] : $row['order_id']), ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars(customerFollowUpPagePlatformLabel(isset($row['platform']) ? $row['platform'] : ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string) (isset($row['customer_username']) ? $row['customer_username'] : ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($customerType === 'return' ? 'Return' : 'New', ENT_QUOTES, 'UTF-8') ?></div>
                                                </td>
                                                <td><?= htmlspecialchars((string) (isset($row['package_name']) ? $row['package_name'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) (isset($row['received_date']) ? $row['received_date'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars($displayNextFollowUpDate, ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted small">Round <?= $roundNo ?></div>
                                                    <?php if ($missedOriginalDate !== '') { ?>
                                                        <div class="delay-subtext">Missed Original Date: <?= htmlspecialchars($missedOriginalDate, ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?= customerFollowUpFormatStatusLabel($resolvedDisplayStatus) ?>
                                                    <?php if ($delayReason !== '') { ?>
                                                        <div class="status-subtext">Delay Reason Saved</div>
                                                    <?php } ?>
                                                    <?php if (strtoupper(trim((string) (isset($row['lost_tag_added']) ? $row['lost_tag_added'] : 'N'))) === 'Y' && (int) (isset($row['lost_tag_id']) ? $row['lost_tag_id'] : 0) > 0) { ?>
                                                        <div class="status-subtext">Lost Tag ID: <?= (int) $row['lost_tag_id'] ?></div>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($effectiveContactNo !== '' ? $effectiveContactNo : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php if ($assignedUserLabel !== '') { ?>
                                                        <div class="text-muted small">Assigned: <?= htmlspecialchars($assignedUserLabel, ENT_QUOTES, 'UTF-8') ?></div>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="hideColumn">ID</th>
                                            <th width="60">S/N</th>
                                            <th class="action-col">Actions</th>
                                            <th>Order ID</th>
                                            <th>Username</th>
                                            <th>Package</th>
                                            <th>Received Date</th>
                                            <th>Next Follow-Up Date</th>
                                            <th>Status</th>
                                            <th>WhatsApp / Contact Number</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($canViewLogsPermission && !empty($rows)) { ?>
                    <?php foreach ($rows as $row) {
                        $followUpId = isset($row['id']) ? (int) $row['id'] : 0;
                        $logRows = isset($actionLogMap[$followUpId]) ? $actionLogMap[$followUpId] : array();
                        if ($followUpId <= 0 || empty($logRows)) {
                            continue;
                        }
                        ?>
                        <div class="modal fade" id="actionLogModal_<?= $followUpId ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Action Logs - <?= htmlspecialchars((string) (isset($row['order_no']) && $row['order_no'] !== '' ? $row['order_no'] : $followUpId), ENT_QUOTES, 'UTF-8') ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped log-table">
                                                <thead>
                                                    <tr>
                                                        <th>Date Time</th>
                                                        <th>Action</th>
                                                        <th>By</th>
                                                        <th>Remark</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($logRows as $logRow) {
                                                        $actionBy = trim((string) (isset($logRow['action_by']) ? $logRow['action_by'] : ''));
                                                        $actorLabel = ctype_digit($actionBy) ? customerFollowUpGetUserDisplayName($connect, (int) $actionBy) : ($actionBy !== '' ? $actionBy : 'SYSTEM');
                                                        ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars(trim((string) (isset($logRow['action_date']) ? $logRow['action_date'] : '')) . ' ' . trim((string) (isset($logRow['action_time']) ? $logRow['action_time'] : '')), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td><?= htmlspecialchars((string) (isset($logRow['action_type']) ? $logRow['action_type'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td><?= htmlspecialchars($actorLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                            <td><?= nl2br(htmlspecialchars((string) (isset($logRow['remark']) ? $logRow['remark'] : ''), ENT_QUOTES, 'UTF-8')) ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delayReasonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="delayReasonModalTitle">Save Delay Reason</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="save_delay_reason">
                        <input type="hidden" name="follow_up_id" id="delay_reason_follow_up_id" value="">
                        <div class="mb-2 text-muted small" id="delay_reason_missed_date_text"></div>
                        <div class="mb-3">
                            <label class="form-label" for="delay_reason">Delay Reason</label>
                            <textarea class="form-control" id="delay_reason" name="delay_reason" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-dark" style="text-transform: none !important;">Save Reason</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewDelayInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewDelayInfoModalTitle">Delay Reason Info</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><span class="fw-semibold">Missed Original Date:</span> <span id="view_delay_missed_original_date_text">-</span></div>
                    <div><span class="fw-semibold d-block mb-1">Delay Reason</span><div class="border rounded p-3 bg-light" id="view_delay_reason_text">-</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewPostponeInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPostponeInfoModalTitle">Postponement Info</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><span class="fw-semibold">Status:</span> <span id="view_postpone_status_text">-</span></div>
                    <div class="mb-2"><span class="fw-semibold">Requested New Date:</span> <span id="view_postpone_requested_date_text">-</span></div>
                    <div class="mb-3"><span class="fw-semibold d-block mb-1">Postpone Reason</span><div class="border rounded p-3 bg-light" id="view_postpone_reason_text">-</div></div>
                    <div id="view_postpone_reject_reason_wrap" class="d-none"><span class="fw-semibold d-block mb-1">Reject Reason</span><div class="border rounded p-3 bg-light" id="view_postpone_reject_reason_text">-</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="submitFollowUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data" class="customer-follow-up-action-form" id="submit_follow_up_form" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="submitFollowUpModalTitle">Submit Follow-Up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="submit_follow_up">
                        <input type="hidden" name="follow_up_id" id="submit_follow_up_id" value="">

                        <div class="arrival-follow-up-summary">
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Order ID</span>
                                <span id="submit_follow_up_order_code_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Customer</span>
                                <span id="submit_follow_up_customer_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Package</span>
                                <span id="submit_follow_up_package_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Received Date</span>
                                <span id="submit_follow_up_received_date_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Customer Type</span>
                                <span id="submit_follow_up_customer_type_text"></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="submit_attachment">
                                Screenshot / Attachment<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <input type="file" class="form-control" id="submit_attachment" name="attachment" required>
                            <div class="customer-follow-up-field-error" id="submit_attachment_error">Screenshot / Attachment is required.</div>
                            <div class="arrival-follow-up-preview" id="submit_attachment_preview_wrap">
                                <a id="submit_existing_attachment_link" class="arrival-follow-up-preview-link d-none" href="#" target="_blank" rel="noopener noreferrer"></a>
                                <img id="submit_attachment_preview_img" alt="Follow-Up Attachment Preview">
                                <div class="arrival-follow-up-preview-note d-none" id="submit_attachment_preview_note"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="submit_message_shortcut_id">
                                Message Shortcut<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <select class="form-select" id="submit_message_shortcut_id" name="message_shortcut_id" required>
                                <option value="">Select Message Shortcut</option>
                                <?php foreach ($messageShortcutOptions as $shortcutRow) {
                                    $shortcutId = isset($shortcutRow['id']) ? (int) $shortcutRow['id'] : 0;
                                    if ($shortcutId <= 0) {
                                        continue;
                                    }
                                    $shortcutLabel = trim((string) (isset($shortcutRow['shortcuts_tag']) ? $shortcutRow['shortcuts_tag'] : ''));
                                    ?>
                                    <option value="<?= $shortcutId ?>"><?= htmlspecialchars($shortcutLabel !== '' ? $shortcutLabel : ('Shortcut #' . $shortcutId), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                            <div class="customer-follow-up-field-error" id="submit_message_shortcut_error">Message Shortcut is required.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="submit_next_follow_up_date">
                                Next Follow-Up Date<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <input type="date" class="form-control" id="submit_next_follow_up_date" name="next_follow_up_date" required>
                            <div class="customer-follow-up-field-error" id="submit_next_follow_up_date_error">Next Follow-Up Date is required.</div>
                            <small class="text-muted" id="submit_next_follow_up_hint"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">WhatsApp / Contact Number</label>
                            <div id="submit_contact_display_wrap" class="arrival-follow-up-contact-display d-none">
                                <div id="submit_contact_display_text"></div>
                                <button type="button" class="arrival-follow-up-contact-edit" id="submit_contact_edit_btn" title="Edit Contact Number">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </div>
                            <div id="submit_contact_input_wrap">
                                <input type="text" class="form-control" id="submit_contact_no" name="contact_no" placeholder="Enter Contact Number">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submit_follow_up_submit_btn" style="text-transform: none !important;">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectFollowUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectFollowUpModalTitle">Reject Follow-Up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="reject_follow_up">
                        <input type="hidden" name="follow_up_id" id="reject_follow_up_id" value="">
                        <div class="mb-3">
                            <label class="form-label" for="reject_reason">Reject Reason</label>
                            <textarea class="form-control" id="reject_reason" name="reject_reason" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="text-transform: none !important;">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="postponeFollowUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="postponeFollowUpModalTitle">Request Postponement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="request_postponement">
                        <input type="hidden" name="follow_up_id" id="postpone_follow_up_id" value="">
                        <div class="mb-3">
                            <label class="form-label" for="postpone_reason">Postpone Reason</label>
                            <textarea class="form-control" id="postpone_reason" name="postpone_reason" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="requested_next_follow_up_date">New Next Follow-Up Date</label>
                            <input type="date" class="form-control" id="requested_next_follow_up_date" name="requested_next_follow_up_date" required>
                            <small class="text-muted" id="postpone_follow_up_hint"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-warning" style="text-transform: none !important;">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectPostponeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectPostponeModalTitle">Reject Postponement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="reject_postponement">
                        <input type="hidden" name="follow_up_id" id="reject_postpone_follow_up_id" value="">
                        <div class="mb-2 text-muted small" id="reject_postpone_requested_date_text"></div>
                        <div class="mb-3">
                            <label class="form-label" for="postpone_reject_reason">Reject Reason</label>
                            <textarea class="form-control" id="postpone_reject_reason" name="postpone_reject_reason" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="text-transform: none !important;">Reject Postponement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        var customerFollowUpInitialFlash = <?= json_encode(array(
            'type' => isset($flashMessage['type']) ? (string) $flashMessage['type'] : '',
            'message' => isset($flashMessage['message']) ? (string) $flashMessage['message'] : '',
        )) ?>;

        function customerFollowUpHideModalForForm(form) {
            var modalElement = form ? form.closest('.modal') : null;
            if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }

            var modalInstance = bootstrap.Modal.getInstance(modalElement) || bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.hide();
        }

        function customerFollowUpShowResultPopup(message, onContinue) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                window.alert(message || 'Action completed.');
                if (typeof onContinue === 'function') {
                    onContinue();
                }
                return;
            }

            var existingPopup = document.getElementById('customerFollowUpResultModal');
            if (existingPopup) {
                existingPopup.remove();
            }

            var popupElement = document.createElement('div');
            popupElement.className = 'modal fade';
            popupElement.id = 'customerFollowUpResultModal';
            popupElement.tabIndex = -1;
            popupElement.setAttribute('aria-hidden', 'true');
            popupElement.innerHTML =
                '<div class="modal-dialog modal-dialog-centered" style="font-family:\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">' +
                '    <div class="modal-content" style="border-radius:16px;">' +
                '        <div class="modal-body fs-6 mt-3">' +
                '            <p id="customerFollowUpResultMessage" style="text-align:center; font-weight:bold; font-size:25px; color:#4b4b4b;"></p>' +
                '        </div>' +
                '        <div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px">' +
                '            <button id="customerFollowUpResultContinueBtn" type="button" class="btn" style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius:24px; text-transform: none !important;">Continue</button>' +
                '        </div>' +
                '    </div>' +
                '</div>';

            document.body.appendChild(popupElement);
            var popupMessage = popupElement.querySelector('#customerFollowUpResultMessage');
            if (popupMessage) {
                popupMessage.textContent = message || 'Action completed.';
            }

            var popup = new bootstrap.Modal(popupElement, {
                keyboard: false,
                backdrop: 'static'
            });
            popup.show();

            popupElement.addEventListener('click', function (event) {
                if (event.target && event.target.id === 'customerFollowUpResultContinueBtn') {
                    popup.hide();
                }
            });

            popupElement.addEventListener('hidden.bs.modal', function () {
                popupElement.remove();
                if (typeof onContinue === 'function') {
                    onContinue();
                }
            });
        }

        function customerFollowUpCloseModalThenShowPopup(form, message, onContinue) {
            var modalElement = form ? form.closest('.modal') : null;
            if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal || !modalElement.classList.contains('show')) {
                customerFollowUpShowResultPopup(message, onContinue);
                return;
            }

            var modalInstance = bootstrap.Modal.getInstance(modalElement) || bootstrap.Modal.getOrCreateInstance(modalElement);
            modalElement.addEventListener('hidden.bs.modal', function handleModalHidden() {
                modalElement.removeEventListener('hidden.bs.modal', handleModalHidden);
                customerFollowUpShowResultPopup(message, onContinue);
            });
            modalInstance.hide();
        }

        function customerFollowUpInitializeTableSection() {
            if (document.getElementById('customer_follow_up_table')) {
                createSortingTable('customer_follow_up_table', { searching: false });
                datatableAlignment('customer_follow_up_table');
                keepDataTableControlsVisible('customer_follow_up_table');
            }

            customerFollowUpBindAjaxForms();
            dropdownMenuDispFix();
            setButtonColor();
        }

        function customerFollowUpRefreshTableSection() {
            var tableSection = document.getElementById('customer_follow_up_table_section');
            if (!tableSection) {
                return Promise.resolve();
            }

            return fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to refresh the follow-up list.');
                    }

                    return response.text();
                })
                .then(function (html) {
                    var parser = new DOMParser();
                    var documentNode = parser.parseFromString(html, 'text/html');
                    var newTableSection = documentNode.getElementById('customer_follow_up_table_section');
                    if (!newTableSection) {
                        throw new Error('Unable to refresh the follow-up list.');
                    }

                    tableSection.innerHTML = newTableSection.innerHTML;
                    customerFollowUpInitializeTableSection();
                });
        }

        function setCustomerFollowUpFieldError(fieldId, errorId, hasError) {
            var field = document.getElementById(fieldId);
            var error = document.getElementById(errorId);

            if (field) {
                field.classList.toggle('is-invalid', hasError);
            }

            if (error) {
                error.classList.toggle('is-visible', hasError);
            }
        }

        function clearSubmitFollowUpRequiredErrors() {
            setCustomerFollowUpFieldError('submit_attachment', 'submit_attachment_error', false);
            setCustomerFollowUpFieldError('submit_message_shortcut_id', 'submit_message_shortcut_error', false);
            setCustomerFollowUpFieldError('submit_next_follow_up_date', 'submit_next_follow_up_date_error', false);
        }

        function validateSubmitFollowUpRequiredFields() {
            var attachmentInput = document.getElementById('submit_attachment');
            var shortcutInput = document.getElementById('submit_message_shortcut_id');
            var nextDateInput = document.getElementById('submit_next_follow_up_date');

            var attachmentIsRequired = attachmentInput ? attachmentInput.required : true;
            var attachmentMissing = attachmentIsRequired && (!attachmentInput || !attachmentInput.files || attachmentInput.files.length === 0);
            var shortcutMissing = !shortcutInput || shortcutInput.value.trim() === '';
            var nextDateMissing = !nextDateInput || nextDateInput.value.trim() === '';

            setCustomerFollowUpFieldError('submit_attachment', 'submit_attachment_error', attachmentMissing);
            setCustomerFollowUpFieldError('submit_message_shortcut_id', 'submit_message_shortcut_error', shortcutMissing);
            setCustomerFollowUpFieldError('submit_next_follow_up_date', 'submit_next_follow_up_date_error', nextDateMissing);

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

        function customerFollowUpBindAjaxForms() {
            document.querySelectorAll('.customer-follow-up-action-form').forEach(function (form) {
                if (form.getAttribute('data-ajax-bound') === '1') {
                    return;
                }

                form.setAttribute('data-ajax-bound', '1');
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    if (form.id === 'submit_follow_up_form' && !validateSubmitFollowUpRequiredFields()) {
                        return;
                    }

                    var confirmMessage = form.getAttribute('data-confirm-message') || '';
                    if (confirmMessage && !window.confirm(confirmMessage)) {
                        return;
                    }

                    var submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
                    var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

                    submitButtons.forEach(function (button) {
                        button.disabled = true;
                    });

                    var formData = new FormData(form);
                    formData.set('customer_follow_up_ajax', '1');
                    if (submitter && submitter.name) {
                        formData.set(submitter.name, submitter.value);
                    }

                    fetch(form.getAttribute('action') || window.location.href, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            return response.text().then(function (text) {
                                var payload = {};

                                if (text) {
                                    try {
                                        payload = JSON.parse(text);
                                    } catch (error) {
                                        throw new Error('Unexpected response received. Please try again.');
                                    }
                                }

                                if (!response.ok && !payload.message) {
                                    throw new Error('Unable to complete the action right now.');
                                }

                                return payload;
                            });
                        })
                        .then(function (payload) {
                            var wasSuccessful = !!payload.success;
                            var popupMessage = payload.message || (wasSuccessful ? 'Action completed successfully.' : 'Unable to complete the action.');

                            if (wasSuccessful) {
                                form.reset();
                            }

                            customerFollowUpCloseModalThenShowPopup(
                                form,
                                popupMessage,
                                wasSuccessful ? function () {
                                    if (typeof window.systemAlertRefreshNow === 'function') {
                                        window.systemAlertRefreshNow();
                                    }
                                    customerFollowUpRefreshTableSection();
                                } : null
                            );
                        })
                        .catch(function (error) {
                            customerFollowUpCloseModalThenShowPopup(
                                form,
                                error && error.message ? error.message : 'Unable to complete the action right now.',
                            );
                        })
                        .finally(function () {
                            submitButtons.forEach(function (button) {
                                button.disabled = false;
                            });
                        });
                });
            });
        }

        function bindFollowUpAttachmentPreview(inputId, wrapId, imageId, noteId, existingLinkId) {
            var fileInput = document.getElementById(inputId);
            var previewWrap = document.getElementById(wrapId);
            var previewImage = document.getElementById(imageId);
            var previewNote = document.getElementById(noteId);
            var existingLink = existingLinkId ? document.getElementById(existingLinkId) : null;
            if (!fileInput || !previewWrap || !previewImage || !previewNote) {
                return null;
            }

            var currentObjectUrl = null;
            var getExistingAttachment = function () {
                return {
                    url: fileInput.getAttribute('data-existing-url') || '',
                    path: fileInput.getAttribute('data-existing-path') || ''
                };
            };
            var isImagePath = function (path) {
                return /\.(png|jpe?g|webp|gif)$/i.test(path || '');
            };
            var clearExistingLink = function () {
                if (existingLink) {
                    existingLink.removeAttribute('href');
                    existingLink.textContent = '';
                    existingLink.classList.add('d-none');
                }
            };
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
            var showExistingAttachment = function () {
                var existing = getExistingAttachment();

                clearPreview();
                clearExistingLink();

                if (!existing.url || !existing.path) {
                    return;
                }

                if (existingLink) {
                    existingLink.href = existing.url;
                    existingLink.textContent = 'Previous Attachment: ' + existing.path;
                    existingLink.classList.remove('d-none');
                }

                if (isImagePath(existing.path)) {
                    previewImage.src = existing.url;
                    previewImage.style.display = 'block';
                    previewNote.textContent = '';
                    previewNote.classList.add('d-none');
                    previewWrap.style.display = 'block';
                    return;
                }

                previewImage.removeAttribute('src');
                previewImage.style.display = 'none';
                previewNote.textContent = 'Previous attachment is available. Click the link above to view it.';
                previewNote.classList.remove('d-none');
                previewWrap.style.display = 'block';
            };

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (!file) {
                    showExistingAttachment();
                    return;
                }

                if (currentObjectUrl) {
                    URL.revokeObjectURL(currentObjectUrl);
                    currentObjectUrl = null;
                }

                clearExistingLink();

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
            return {
                clearPreview: clearPreview,
                showExistingAttachment: showExistingAttachment
            };
        }

        var submitAttachmentPreviewHelper = bindFollowUpAttachmentPreview(
            'submit_attachment',
            'submit_attachment_preview_wrap',
            'submit_attachment_preview_img',
            'submit_attachment_preview_note',
            'submit_existing_attachment_link'
        );

        function clearSubmitFollowUpSingleFieldErrorIfFilled(fieldId) {
            var field = document.getElementById(fieldId);
            if (!field) {
                return;
            }

            if (fieldId === 'submit_attachment') {
                var attachmentHasFile = field.files && field.files.length > 0;
                var attachmentNotRequired = !field.required;
                if (attachmentHasFile || attachmentNotRequired) {
                    setCustomerFollowUpFieldError('submit_attachment', 'submit_attachment_error', false);
                }
                return;
            }

            if (fieldId === 'submit_message_shortcut_id' && field.value.trim() !== '') {
                setCustomerFollowUpFieldError('submit_message_shortcut_id', 'submit_message_shortcut_error', false);
                return;
            }

            if (fieldId === 'submit_next_follow_up_date' && field.value.trim() !== '') {
                setCustomerFollowUpFieldError('submit_next_follow_up_date', 'submit_next_follow_up_date_error', false);
            }
        }

        ['submit_attachment', 'submit_message_shortcut_id', 'submit_next_follow_up_date'].forEach(function (fieldId) {
            var field = document.getElementById(fieldId);
            if (!field) {
                return;
            }

            field.addEventListener('change', function () {
                clearSubmitFollowUpSingleFieldErrorIfFilled(fieldId);
            });
        });

        var submitFollowUpModal = document.getElementById('submitFollowUpModal');
        if (submitFollowUpModal) {
            submitFollowUpModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) {
                    return;
                }

                var followUpId = button.getAttribute('data-follow-up-id') || '';
                var roundNo = button.getAttribute('data-round-no') || '';
                var orderNo = button.getAttribute('data-order-no') || '';
                var messageShortcutId = button.getAttribute('data-message-shortcut-id') || '';
                var nextFollowUpDate = button.getAttribute('data-next-follow-up-date') || '';
                var contactNo = button.getAttribute('data-contact-no') || '';
                var existingAttachmentPath = button.getAttribute('data-existing-attachment-path') || '';
                var existingAttachmentUrl = button.getAttribute('data-existing-attachment-url') || '';
                var maxDate = button.getAttribute('data-max-date') || '';
                var ruleLabel = button.getAttribute('data-rule-label') || '';
                var actionText = (button.textContent || '').trim().toLowerCase();
                var isResubmit = actionText === 'resubmit';

                document.getElementById('submit_follow_up_id').value = followUpId;
                document.getElementById('submitFollowUpModalTitle').textContent =
                    (isResubmit ? 'Resubmit Customer Follow-Up - ' : 'Customer Follow-Up - ') + (orderNo || '');
                document.getElementById('submit_follow_up_order_code_text').textContent = orderNo || '-';
                document.getElementById('submit_follow_up_customer_text').textContent = button.getAttribute('data-customer-username') || button.getAttribute('data-customer-name') || '-';
                document.getElementById('submit_follow_up_package_text').textContent = button.getAttribute('data-package-name') || '-';
                document.getElementById('submit_follow_up_received_date_text').textContent = button.getAttribute('data-received-date') || '';
                document.getElementById('submit_follow_up_customer_type_text').textContent =
                    (button.getAttribute('data-customer-type') || 'new') === 'return'
                        ? 'Return Customer (' + (button.getAttribute('data-purchase-count') || '0') + ' previous purchase)'
                        : 'New Customer';
                document.getElementById('submit_message_shortcut_id').value = messageShortcutId;
                document.getElementById('submit_next_follow_up_date').value = nextFollowUpDate;
                document.getElementById('submit_next_follow_up_date').max = maxDate;
                document.getElementById('submit_next_follow_up_hint').textContent = ruleLabel;
                document.getElementById('submit_follow_up_submit_btn').textContent = isResubmit ? 'Resubmit' : 'Submit';

                var submitAttachmentInput = document.getElementById('submit_attachment');
                submitAttachmentInput.value = '';
                submitAttachmentInput.setAttribute('data-existing-path', existingAttachmentPath);
                submitAttachmentInput.setAttribute('data-existing-url', existingAttachmentUrl);

                /* Resubmit must display previous submitted attachment and allow user to keep it. */
                submitAttachmentInput.required = !(isResubmit && existingAttachmentPath);

                clearSubmitFollowUpRequiredErrors();

                var contactDisplayWrap = document.getElementById('submit_contact_display_wrap');
                var contactDisplayText = document.getElementById('submit_contact_display_text');
                var contactInputWrap = document.getElementById('submit_contact_input_wrap');
                var contactInput = document.getElementById('submit_contact_no');

                contactInput.value = contactNo;
                if (contactNo) {
                    contactDisplayText.textContent = contactNo;
                    contactDisplayWrap.classList.remove('d-none');
                    contactInputWrap.classList.add('d-none');
                } else {
                    contactDisplayWrap.classList.add('d-none');
                    contactInputWrap.classList.remove('d-none');
                }

                if (submitAttachmentPreviewHelper) {
                    submitAttachmentPreviewHelper.clearPreview();
                    submitAttachmentPreviewHelper.showExistingAttachment();
                }
            });
        }

        var submitContactEditBtn = document.getElementById('submit_contact_edit_btn');
        if (submitContactEditBtn) {
            submitContactEditBtn.addEventListener('click', function () {
                document.getElementById('submit_contact_display_wrap').classList.add('d-none');
                document.getElementById('submit_contact_input_wrap').classList.remove('d-none');
                document.getElementById('submit_contact_no').focus();
            });
        }

        var delayReasonModal = document.getElementById('delayReasonModal');
        if (delayReasonModal) {
            delayReasonModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('delay_reason_follow_up_id').value = button.getAttribute('data-follow-up-id') || '';
                document.getElementById('delay_reason').value = button.getAttribute('data-delay-reason') || '';
                document.getElementById('delayReasonModalTitle').textContent = 'Save Delay Reason - Round ' + (button.getAttribute('data-round-no') || '');
                var missedOriginalDate = button.getAttribute('data-missed-original-date') || '';
                document.getElementById('delay_reason_missed_date_text').textContent = missedOriginalDate ? 'Missed Original Date: ' + missedOriginalDate : '';
            });
        }

        var viewDelayInfoModal = document.getElementById('viewDelayInfoModal');
        if (viewDelayInfoModal) {
            viewDelayInfoModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('viewDelayInfoModalTitle').textContent = 'Delay Reason Info - Round ' + (button.getAttribute('data-round-no') || '');
                document.getElementById('view_delay_missed_original_date_text').textContent = button.getAttribute('data-missed-original-date') || '-';
                document.getElementById('view_delay_reason_text').textContent = button.getAttribute('data-delay-reason') || 'No delay reason saved.';
            });
        }

        var viewPostponeInfoModal = document.getElementById('viewPostponeInfoModal');
        if (viewPostponeInfoModal) {
            viewPostponeInfoModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var postponeStatus = button.getAttribute('data-postpone-status') || '';
                var rejectReasonWrap = document.getElementById('view_postpone_reject_reason_wrap');
                document.getElementById('viewPostponeInfoModalTitle').textContent = 'Postponement Info - Round ' + (button.getAttribute('data-round-no') || '');
                document.getElementById('view_postpone_status_text').textContent = postponeStatus || '-';
                document.getElementById('view_postpone_requested_date_text').textContent = button.getAttribute('data-requested-next-date') || '-';
                document.getElementById('view_postpone_reason_text').textContent = button.getAttribute('data-postpone-reason') || 'No postpone reason saved.';
                document.getElementById('view_postpone_reject_reason_text').textContent = button.getAttribute('data-postpone-reject-reason') || 'No reject reason saved.';
                if (rejectReasonWrap) {
                    if (postponeStatus.toLowerCase() === 'rejected') {
                        rejectReasonWrap.classList.remove('d-none');
                    } else {
                        rejectReasonWrap.classList.add('d-none');
                    }
                }
            });
        }

        var rejectFollowUpModal = document.getElementById('rejectFollowUpModal');
        if (rejectFollowUpModal) {
            rejectFollowUpModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('reject_follow_up_id').value = button.getAttribute('data-follow-up-id') || '';
                document.getElementById('reject_reason').value = '';
                document.getElementById('rejectFollowUpModalTitle').textContent = 'Reject Follow-Up Round ' + (button.getAttribute('data-round-no') || '');
            });
        }

        var postponeFollowUpModal = document.getElementById('postponeFollowUpModal');
        if (postponeFollowUpModal) {
            postponeFollowUpModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var followUpId = button.getAttribute('data-follow-up-id') || '';
                var roundNo = button.getAttribute('data-round-no') || '';
                var maxDate = button.getAttribute('data-max-date') || '';
                var ruleLabel = button.getAttribute('data-rule-label') || '';

                document.getElementById('postpone_follow_up_id').value = followUpId;
                document.getElementById('requested_next_follow_up_date').value = '';
                document.getElementById('requested_next_follow_up_date').max = maxDate;
                document.getElementById('postpone_reason').value = '';
                document.getElementById('postpone_follow_up_hint').textContent = ruleLabel;
                document.getElementById('postponeFollowUpModalTitle').textContent = 'Request Postponement - Round ' + roundNo;
            });
        }

        var rejectPostponeModal = document.getElementById('rejectPostponeModal');
        if (rejectPostponeModal) {
            rejectPostponeModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var requestedNextDate = button.getAttribute('data-requested-next-date') || '';

                document.getElementById('reject_postpone_follow_up_id').value = button.getAttribute('data-follow-up-id') || '';
                document.getElementById('postpone_reject_reason').value = '';
                document.getElementById('rejectPostponeModalTitle').textContent = 'Reject Postponement - Round ' + (button.getAttribute('data-round-no') || '');
                document.getElementById('reject_postpone_requested_date_text').textContent = requestedNextDate ? 'Requested new next follow-up date: ' + requestedNextDate : '';
            });
        }

        var customerFollowUpFilterForm = document.getElementById('customer_follow_up_filter_form');
        if (customerFollowUpFilterForm) {
            document.querySelectorAll('#customer_follow_up_filter_form select, #customer_follow_up_filter_form input[type="date"], #customer_follow_up_filter_form input[type="checkbox"]').forEach(function (filterElement) {
                filterElement.addEventListener('change', function () {
                    customerFollowUpFilterForm.submit();
                });
            });
        }

        customerFollowUpInitializeTableSection();

        if (customerFollowUpInitialFlash && customerFollowUpInitialFlash.message) {
            customerFollowUpShowResultPopup(customerFollowUpInitialFlash.message);
        }
    </script>
</body>

</html>
