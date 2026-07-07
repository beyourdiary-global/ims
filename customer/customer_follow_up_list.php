<?php
if (ob_get_level() === 0) {
    ob_start();
}

$customerFollowUpUserRecordLogEmbedMode = (
    trim((string) filter_input(INPUT_GET, 'user_record_log_embed')) === '1'
    || trim((string) filter_input(INPUT_POST, 'user_record_log_embed')) === '1'
    || trim((string) filter_input(INPUT_POST, 'url_action')) !== ''
);

$customerFollowUpBootstrapIsAjax = $customerFollowUpUserRecordLogEmbedMode
    || ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
        && (
            (filter_input(INPUT_POST, 'customer_follow_up_ajax') === '1')
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(trim((string) $_SERVER['HTTP_X_REQUESTED_WITH'])) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower((string) $_SERVER['HTTP_ACCEPT']), 'application/json') !== false)
        ));

$currentPagePin = 151;
$pageTitle = 'Customer Follow-Up';
$displayPageTitle = 'Customer Follow-Up';
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once '../include/common.php';
include_once '../include/common_variable.php';
include_once ROOT . '/include/customer_follow_up_common.php';

if (!$customerFollowUpBootstrapIsAjax) {
    if (ob_get_length() > 0) {
        ob_clean();
    }
    include_once '../menuHeader.php';
}

include_once '../checkCurrentPagePin.php';

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
    renderNotificationScript('You do not have permission to view Customer Follow-Up.', 'error', 'dashboard.php', 1200, true);
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

        if (post('customer_follow_up_ajax') === '1') {
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
        $extra = array();
        if (isset($result['field_errors']) && is_array($result['field_errors'])) {
            $extra['field_errors'] = $result['field_errors'];
            if (!$success && !empty($result['field_errors'])) {
                $message = '';
            }
        }

        if (customerFollowUpPageIsAjaxRequest()) {
            customerFollowUpPageJsonResponse($type, $message, $extra);
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

if (!function_exists('customerFollowUpPageReadRequestValues')) {
    function customerFollowUpPageReadRequestValues($key)
    {
        if ($key === 'assigned_user_id') {
            return numberInputArray($key);
        }

        return inputArray($key);
    }
}

if (!function_exists('customerFollowUpPageBuildMultiSelectButtonLabel')) {
    function customerFollowUpPageBuildMultiSelectButtonLabel($selectedLabels, $placeholder)
    {
        $selectedLabels = array_values(array_filter(array_map(function ($label) {
            return trim((string) $label);
        }, (array) $selectedLabels), function ($label) {
            return $label !== '';
        }));

        if (empty($selectedLabels)) {
            return (string) $placeholder;
        }

        if (count($selectedLabels) === 1) {
            return $selectedLabels[0];
        }

        return count($selectedLabels) . ' selected';
    }
}

if (!function_exists('customerFollowUpPageBuildOrderViewUrl')) {
    function customerFollowUpPageBuildOrderViewUrl($platform, $orderId)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderId = (int) $orderId;
        if ($platform === '' || $orderId <= 0 || !function_exists('shopeeOmsGetOrderSourceViewUrl')) {
            return '';
        }

        return (string) shopeeOmsGetOrderSourceViewUrl($platform, $orderId);
    }
}

if (!function_exists('customerFollowUpPageBuildCustomerDetailUrl')) {
    function customerFollowUpPageBuildCustomerDetailUrl($connect, $financeConnect, $platform, $customerId)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        if ($platform === '' || $customerId <= 0 || !function_exists('customerDailyReportGetCustomerMeta')) {
            return '';
        }

        $customerMeta = customerDailyReportGetCustomerMeta($connect, $financeConnect, $platform, $customerId);
        return trim((string) (isset($customerMeta['record_url']) ? $customerMeta['record_url'] : ''));
    }
}

if (!function_exists('customerFollowUpPageBuildUserRecordLogEmbedUrl')) {
    function customerFollowUpPageBuildUserRecordLogEmbedUrl($platform, $customerId, $customerLabel = '', $returnUrl = '')
    {
        global $SITEURL;

        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $customerColumn = function_exists('customerFollowUpGetPlatformUserRecordLogColumn')
            ? customerFollowUpGetPlatformUserRecordLogColumn($platform)
            : '';

        if ($customerId <= 0 || $customerColumn === '') {
            return '';
        }

        $returnUrl = trim((string) $returnUrl);
        if ($returnUrl === '') {
            $returnUrl = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/customer/customer_follow_up_list.php';
        }

        return rtrim((string) $SITEURL, '/') . '/customer/customer_follow_up_list.php?' . http_build_query(array(
            'user_record_log_embed' => '1',
            'customer_id' => $customerId,
            'customer_column' => $customerColumn,
            'customer_label' => trim((string) $customerLabel),
            'return_url' => $returnUrl,
        ));
    }
}

if (!function_exists('customerFollowUpPageRenderCustomerTagLabelCell')) {
    function customerFollowUpPageRenderCustomerTagLabelCell($customerLabelMeta, $customerTagRows)
    {
        $badgeItems = array();

        foreach (array('segmentation', 'level', 'repeat') as $labelType) {
            if (isset($customerLabelMeta[$labelType]) && function_exists('customerLabelRenderBadge')) {
                $badgeItems[] = customerLabelRenderBadge($customerLabelMeta[$labelType]);
            }
        }

        if (function_exists('customerTagRenderBadgeItems')) {
            $badgeItems = array_merge($badgeItems, customerTagRenderBadgeItems($customerTagRows, 'customer-tag-table-badge'));
        }

        if (empty($badgeItems)) {
            return '-';
        }

        return function_exists('customerLabelRenderCollapsibleBadgeGroup')
            ? customerLabelRenderCollapsibleBadgeGroup($badgeItems, 'customer-label-summary-wrap')
            : implode(' ', $badgeItems);
    }
}

if ($customerFollowUpUserRecordLogEmbedMode) {
    $embedCustomerId = isset($_REQUEST['customer_id']) ? (int) $_REQUEST['customer_id'] : 0;
    $embedCustomerColumn = isset($_REQUEST['customer_column']) ? trim((string) $_REQUEST['customer_column']) : '';
    $embedCustomerLabel = isset($_REQUEST['customer_label']) ? trim((string) $_REQUEST['customer_label']) : '';
    $embedReturnUrl = isset($_REQUEST['return_url']) ? trim((string) $_REQUEST['return_url']) : '';

    if ($embedCustomerId <= 0 || $embedCustomerColumn === '') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && function_exists('urlJsonResponse')) {
            urlJsonResponse(array('ok' => 0, 'message' => 'Customer context is invalid.'));
        }

        customerFollowUpPageClearOutputBuffers();

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>User Record Log</title></head><body><div style="padding:16px;color:#842029;background:#f8d7da;border:1px solid #f5c2c7;border-radius:8px;">Customer context is invalid.</div></body></html>';
        exit;
    }

    $embedAjaxUrl = rtrim((string) $SITEURL, '/') . '/customer/customer_follow_up_list.php?' . http_build_query(array(
        'user_record_log_embed' => '1',
        'customer_id' => $embedCustomerId,
        'customer_column' => $embedCustomerColumn,
        'customer_label' => $embedCustomerLabel,
        'return_url' => $embedReturnUrl,
    ));

    $embedContext = function_exists('urlHandleUserRecordLogRequest')
        ? urlHandleUserRecordLogRequest($connect, $connect, array(
            'customer_id' => $embedCustomerId,
            'customer_column' => $embedCustomerColumn,
            'customer_label' => $embedCustomerLabel,
            'customer_only' => true,
            'return_url' => $embedReturnUrl,
            'ajax_url' => $embedAjaxUrl,
            'customer_lookup_connect' => $connect,
        ))
        : array();

    customerFollowUpPageClearOutputBuffers();

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>User Record Log</title>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="<?= htmlspecialchars(rtrim((string) $SITEURL, '/') . '/css/main.css?v=' . @filemtime(ROOT . '/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">

        <style>
            html,
            body {
                background: #f8f9fa;
                padding: 0;
                margin: 0;
                width: 100%;
                min-height: 100%;
                font-family: "Open Sans", Arial, sans-serif;
            }

            .customer-follow-up-user-record-log-embed {
                padding: 16px;
            }

            .customer-follow-up-user-record-log-embed .user-record-log-module {
                margin-top: 0 !important;
            }

            .customer-follow-up-user-record-log-embed .user-record-log-module > .card:first-child {
                margin-top: 0 !important;
            }
        </style>

        <script src="<?= htmlspecialchars(defined('JQUERY_3_6_4_JS') ? JQUERY_3_6_4_JS : 'https://code.jquery.com/jquery-3.6.4.min.js', ENT_QUOTES, 'UTF-8') ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            if (typeof window.showNotification !== 'function') {
                window.showNotification = function (message, type) {
                    var alertType = type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info');
                    var alertBox = document.createElement('div');
                    alertBox.className = 'alert alert-' + alertType;
                    alertBox.style.position = 'fixed';
                    alertBox.style.top = '16px';
                    alertBox.style.right = '16px';
                    alertBox.style.zIndex = '9999';
                    alertBox.style.maxWidth = '360px';
                    alertBox.textContent = message || '';
                    document.body.appendChild(alertBox);

                    window.setTimeout(function () {
                        if (alertBox && alertBox.parentNode) {
                            alertBox.parentNode.removeChild(alertBox);
                        }
                    }, 2200);
                };
            }
        </script>
    </head>
    <body>
        <div class="customer-follow-up-user-record-log-embed">
            <?php
            if (function_exists('urlRenderUserRecordLogModule')) {
                urlRenderUserRecordLogModule($connect, $connect, array(
                    'context' => $embedContext,
                    'customer_id' => $embedCustomerId,
                    'customer_column' => $embedCustomerColumn,
                    'customer_label' => $embedCustomerLabel,
                    'customer_only' => true,
                    'return_url' => $embedReturnUrl,
                    'ajax_url' => $embedAjaxUrl,
                    'section_heading' => 'User Record Log',
                    'show_scope_note' => true,
                ));
            } else {
                echo '<div class="alert alert-danger">User Record Log module is not available.</div>';
            }
            ?>
        </div>
    </body>
    </html>
    <?php
    exit;
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

$selectedMonth = trim((string) input('month'));
$selectedYear = trim((string) input('year'));
$selectedDate = trim((string) input('date'));
$currentYear = date('Y');
$selectedMonth = ($selectedMonth === '' || preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth)) ? $selectedMonth : date('m');
$selectedYear = ($selectedYear === '' || preg_match('/^\d{4}$/', $selectedYear)) ? $selectedYear : $currentYear;
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : '';
$platformFilters = array();
foreach (customerFollowUpPageReadRequestValues('platform') as $platformValue) {
    $normalizedPlatform = customerFollowUpNormalizePlatform($platformValue);
    if ($normalizedPlatform !== '') {
        $platformFilters[$normalizedPlatform] = $normalizedPlatform;
    }
}
$platformFilters = array_values($platformFilters);

$statusFilters = array();
foreach (customerFollowUpPageReadRequestValues('status') as $statusValue) {
    $normalizedStatus = customerFollowUpNormalizeStatus($statusValue);
    if ($normalizedStatus !== '') {
        $statusFilters[strtolower($normalizedStatus)] = $normalizedStatus;
    }
}
$statusFilters = array_values($statusFilters);

$customerTypeFilters = array();
foreach (customerFollowUpPageReadRequestValues('customer_type') as $customerTypeValue) {
    $normalizedCustomerType = strtolower(trim((string) $customerTypeValue));
    if (in_array($normalizedCustomerType, array('new', 'return'), true)) {
        $customerTypeFilters[$normalizedCustomerType] = $normalizedCustomerType;
    }
}
$customerTypeFilters = array_values($customerTypeFilters);

$assignedUserFilters = array();
foreach (customerFollowUpPageReadRequestValues('assigned_user_id') as $assignedUserValue) {
    $assignedUserValue = trim((string) $assignedUserValue);
    if ($assignedUserValue !== '' && ctype_digit($assignedUserValue) && (int) $assignedUserValue > 0) {
        $assignedUserFilters[(int) $assignedUserValue] = (int) $assignedUserValue;
    }
}
$assignedUserFilters = array_values($assignedUserFilters);

$canViewAllFollowUpRecords = customerFollowUpIsAdminUser(defined('USER_GROUP') ? USER_GROUP : null);
if (!$canViewAllFollowUpRecords) {
    $assignedUserFilters = defined('USER_ID') && (int) USER_ID > 0 ? array((int) USER_ID) : array();
}
$followUpIdFilter = (int) input('follow_up_id');
$roundIdFilter = (int) input('round_id');
$missedOnlyFilter = trim((string) input('missed_only')) === '1';
$lostOnlyFilter = trim((string) input('lost_only')) === '1';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postedCsrfToken = (string) post('customer_follow_up_csrf');
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
                'submit_mode' => postSpaceFilter('submit_mode'),
                'appeal_tag_ids' => isset($_POST['appeal_tag_ids']) && is_array($_POST['appeal_tag_ids']) ? $_POST['appeal_tag_ids'] : array(),
                'appeal_new_tag_name' => postSpaceFilter('appeal_new_tag_name'),
                'appeal_user_record_log' => post('appeal_user_record_log'),
            ), isset($_FILES['attachment']) ? $_FILES['attachment'] : array(), USER_ID, USER_GROUP);
            break;

        case 'assign_customer_tag':
            $result = customerFollowUpAssignCustomerTags($connect, $followUpId, array(
                'appeal_tag_ids' => isset($_POST['appeal_tag_ids']) && is_array($_POST['appeal_tag_ids']) ? $_POST['appeal_tag_ids'] : array(),
                'appeal_new_tag_name' => postSpaceFilter('appeal_new_tag_name'),
            ), USER_ID, USER_GROUP);
            break;

        case 'approve_follow_up':
            if (!$canApprovePermission) {
                $result = array('success' => false, 'message' => 'You do not have approval permission for Customer Follow-Up.');
                break;
            }
            $result = customerFollowUpApproveRound(
                $connect,
                $followUpId,
                post('approval_comment'),
                USER_ID,
                USER_GROUP,
                isset($finance_connect) ? $finance_connect : null
            );
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

        case 'reschedule_first_round_date':
            $result = customerFollowUpRescheduleFirstRoundDate($connect, $followUpId, postSpaceFilter('rescheduled_next_follow_up_date'), USER_ID, USER_GROUP);
            break;

        case 'request_postponement':
            $result = customerFollowUpRequestPostponement($connect, $followUpId, postSpaceFilter('postpone_reason'), postSpaceFilter('requested_next_follow_up_date'), USER_ID, USER_GROUP);
            break;

        case 'submit_missing_next_follow_up_date':
            $result = customerFollowUpSubmitMissingNextFollowUpDate($connect, $followUpId, postSpaceFilter('submitted_missing_next_follow_up_date'), USER_ID, USER_GROUP);
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
            $result = customerFollowUpSaveDelayReason($connect, $followUpId, postSpaceFilter('delay_reason'), USER_ID, USER_GROUP, postSpaceFilter('delay_next_follow_up_date'));
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
$customerTagOptions = function_exists('customerTagGetActiveTagOptions')
    ? (array) customerTagGetActiveTagOptions($connect)
    : array();

$assignedUsers = array();
$assignedUserLabelsById = array();
$userResult = getData('id,name,username', '', '', USR_USER, $connect);
if ($userResult) {
    while ($userRow = $userResult->fetch_assoc()) {
        $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
        if (!$canViewAllFollowUpRecords && defined('USER_ID') && $userId !== (int) USER_ID) {
            continue;
        }
        $userLabel = trim((string) (isset($userRow['name']) && $userRow['name'] !== '' ? $userRow['name'] : $userRow['username']));
        if ($userId <= 0 || $userLabel === '') {
            continue;
        }
        $assignedUserLabelsById[$userId] = $userLabel;
        $assignedUsers[] = $userRow;
    }
}

if ($canViewAllFollowUpRecords) {
    $assignedUserFilters = array_values(array_filter($assignedUserFilters, function ($userId) use ($assignedUserLabelsById) {
        return isset($assignedUserLabelsById[(int) $userId]);
    }));
}

$selectedPlatformLabels = array_map('customerFollowUpPagePlatformLabel', $platformFilters);
$selectedStatusLabels = $statusFilters;
$selectedCustomerTypeLabels = array_map(function ($customerType) {
    return ucfirst((string) $customerType);
}, $customerTypeFilters);
$selectedAssignedUserLabels = array();
foreach ($assignedUserFilters as $assignedUserId) {
    if (isset($assignedUserLabelsById[$assignedUserId])) {
        $selectedAssignedUserLabels[] = $assignedUserLabelsById[$assignedUserId];
    }
}

$whereConditions = array("f.`status` = 'A'");
$effectiveStatusSql = "LOWER(CASE WHEN LOWER(IFNULL(r.`postpone_status`, 'none')) = 'pending' THEN 'pending approval' ELSE COALESCE(NULLIF(TRIM(r.`round_status`), ''), NULLIF(TRIM(f.`current_status`), '')) END)";
if (!empty($platformFilters)) {
    $whereConditions[] = "f.`platform` IN ('" . implode("','", array_map(function ($platformValue) use ($connect) {
        return customerFollowUpEscape($connect, $platformValue);
    }, $platformFilters)) . "')";
}
if (!empty($statusFilters)) {
    $whereConditions[] = $effectiveStatusSql . " IN ('" . implode("','", array_map(function ($statusValue) use ($connect) {
        return customerFollowUpEscape($connect, strtolower($statusValue));
    }, $statusFilters)) . "')";
}
if (!empty($assignedUserFilters)) {
    $whereConditions[] = "f.`assigned_user_id` IN (" . implode(',', array_map('intval', $assignedUserFilters)) . ")";
}
if (!empty($customerTypeFilters)) {
    $whereConditions[] = "LOWER(f.`customer_type`) IN ('" . implode("','", array_map(function ($customerTypeValue) use ($connect) {
        return customerFollowUpEscape($connect, strtolower($customerTypeValue));
    }, $customerTypeFilters)) . "')";
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
if (!empty($rows)) {
    foreach ($rows as $row) {
        $followUpId = isset($row['id']) ? (int) $row['id'] : 0;
        if ($followUpId > 0 && !isset($actionLogMap[$followUpId])) {
            $actionLogMap[$followUpId] = customerFollowUpFetchActionLogRows($connect, $followUpId, 20);
        }
    }
}

$customerIdsByPlatform = array();
foreach ($rows as $row) {
    $rowPlatform = customerFollowUpNormalizePlatform(isset($row['platform']) ? $row['platform'] : '');
    $rowCustomerId = isset($row['customer_id']) ? (int) $row['customer_id'] : 0;
    if ($rowPlatform === '' || $rowCustomerId <= 0) {
        continue;
    }

    if (!isset($customerIdsByPlatform[$rowPlatform])) {
        $customerIdsByPlatform[$rowPlatform] = array();
    }

    $customerIdsByPlatform[$rowPlatform][$rowCustomerId] = $rowCustomerId;
}

$customerLabelMapByPlatform = array();
$customerTagMapByPlatform = array();
foreach ($customerIdsByPlatform as $rowPlatform => $customerIdMap) {
    $platformCustomerIds = array_values($customerIdMap);
    $customerLabelMapByPlatform[$rowPlatform] = function_exists('customerLabelGetCustomerLabelMap')
        ? (array) customerLabelGetCustomerLabelMap($connect, $rowPlatform, $platformCustomerIds)
        : array();
    $customerTagMapByPlatform[$rowPlatform] = function_exists('customerTagGetCustomerTagMap')
        ? (array) customerTagGetCustomerTagMap($connect, $rowPlatform, $platformCustomerIds)
        : array();
}
?>
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

        .customer-follow-up-page .follow-up-filter-actions {
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            gap: 10px;
            width: 100%;
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

        .customer-follow-up-page .follow-up-search-btn {
            border-radius: 999px;
            min-width: 120px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .customer-follow-up-page .follow-up-filter-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 16px;
        }

        .customer-follow-up-page .follow-up-filter-multiselect .customer-record-filter-dropdown-toggle {
            min-height: 38px;
            padding: 0.375rem 0.75rem;
            padding-right: 2.25rem;
            text-align: left;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            position: relative;
            width: 100%;
        }

        .customer-follow-up-page .follow-up-filter-multiselect {
            position: relative;
            width: 100%;
        }

        .customer-follow-up-page .follow-up-filter-multiselect .customer-record-filter-dropdown-toggle::after {
            content: "";
            position: absolute;
            top: 50%;
            right: 0.95rem;
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid #6c757d;
            transform: translateY(-35%);
            pointer-events: none;
        }

        .customer-follow-up-page .follow-up-filter-multiselect .dropdown-menu {
            display: none;
            width: 100%;
            min-width: 100%;
            max-width: 100%;
            max-height: 240px;
            overflow-y: auto;
            inset: calc(100% + 0.25rem) auto auto 0 !important;
            transform: none !important;
            z-index: 1080;
        }

        .customer-follow-up-page .follow-up-filter-multiselect.is-open .dropdown-menu {
            display: block;
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

        .customer-follow-up-user-record-log-dialog {
            max-width: min(1320px, 96vw);
        }

        .customer-follow-up-user-record-log-modal-body {
            padding: 0;
            background: #f8f9fa;
        }

        .customer-follow-up-user-record-log-iframe {
            display: block;
            width: 100%;
            height: 82vh;
            border: 0;
            background: #f8f9fa;
        }

        @media (max-width: 767px) {
            .customer-follow-up-user-record-log-dialog {
                max-width: 100vw;
                margin: 0;
            }

            .customer-follow-up-user-record-log-iframe {
                height: calc(100vh - 70px);
            }
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

        .customer-follow-up-checkbox-list {
            display: grid;
            gap: 0.55rem;
            max-height: 180px;
            overflow-y: auto;
            padding: 0.85rem 1rem;
            border: 1px solid #dcdfe5;
            border-radius: 0.5rem;
            background-color: #fff;
        }

        .customer-follow-up-checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }

        .customer-follow-up-checkbox-item input[type="checkbox"] {
            margin-top: 0;
            flex: 0 0 auto;
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

        .customer-follow-up-page .customer-follow-up-record-link {
            color: inherit;
            text-decoration: none;
        }

        .customer-follow-up-page .customer-follow-up-record-link:hover,
        .customer-follow-up-page .customer-follow-up-record-link:focus {
            color: #0d6efd;
            text-decoration: none;
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
                        <?php if ($followUpIdFilter > 0) { ?>
                            <input type="hidden" name="follow_up_id" value="<?= $followUpIdFilter ?>">
                        <?php } ?>
                        <?php if ($roundIdFilter > 0) { ?>
                            <input type="hidden" name="round_id" value="<?= $roundIdFilter ?>">
                        <?php } ?>
                        <div class="follow-up-filter-stack">
                            <div class="follow-up-date-filter-grid">
                                <div>
                                    <label class="form-label" for="customer_follow_up_date">Date</label>
                                    <input type="date" class="form-control" id="customer_follow_up_date" name="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div>
                                    <label class="form-label" for="customer_follow_up_month">Month</label>
                                    <select class="form-select" id="customer_follow_up_month" name="month">
                                        <option value="">All</option>
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
                                        <option value="">All</option>
                                        <?php for ($yearValue = (int) $currentYear; $yearValue >= ((int) $currentYear - 5); $yearValue--) { ?>
                                            <option value="<?= htmlspecialchars((string) $yearValue, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $yearValue === $selectedYear ? 'selected' : '' ?>><?= htmlspecialchars((string) $yearValue, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="follow-up-date-filter-reset">
                                    <div class="follow-up-filter-actions">
                                        <button type="submit" class="btn btn-primary follow-up-search-btn">Search</button>
                                        <a class="btn btn-outline-secondary w-100 follow-up-reset-btn" href="<?= htmlspecialchars($SITEURL . '/customer/customer_follow_up_list.php', ENT_QUOTES, 'UTF-8') ?>" style="text-transform: none !important;">Reset Filters</a>
                                    </div>
                                </div>
                            </div>
                            <div class="follow-up-filter-grid">
                                <div>
                                    <label class="form-label" for="platform">Platform</label>
                                    <div class="dropdown customer-record-filter-dropdown follow-up-filter-multiselect" data-follow-up-multiselect="platform">
                                        <button
                                            class="customer-record-filter-dropdown-toggle"
                                            type="button"
                                            id="platform"
                                            data-placeholder="All Platform"
                                            aria-expanded="false"><?= htmlspecialchars(customerFollowUpPageBuildMultiSelectButtonLabel($selectedPlatformLabels, 'All Platform'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <div class="dropdown-menu" aria-labelledby="platform">
                                            <div class="form-check">
                                                <input class="form-check-input customer-record-filter-checkbox" type="checkbox" value="" id="platform_all" <?= empty($platformFilters) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="platform_all">All Platform</label>
                                            </div>
                                            <?php foreach (array('shopee', 'lazada', 'facebook', 'website', 'customer_info') as $platformOption) { ?>
                                                <div class="form-check">
                                                    <input class="form-check-input customer-record-filter-checkbox" type="checkbox" name="platform[]" value="<?= htmlspecialchars($platformOption, ENT_QUOTES, 'UTF-8') ?>" id="platform_<?= htmlspecialchars($platformOption, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($platformOption, $platformFilters, true) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="platform_<?= htmlspecialchars($platformOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(customerFollowUpPagePlatformLabel($platformOption), ENT_QUOTES, 'UTF-8') ?></label>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="status">Status</label>
                                    <div class="dropdown customer-record-filter-dropdown follow-up-filter-multiselect" data-follow-up-multiselect="status">
                                        <button
                                            class="customer-record-filter-dropdown-toggle"
                                            type="button"
                                            id="status"
                                            data-placeholder="All Status"
                                            aria-expanded="false"><?= htmlspecialchars(customerFollowUpPageBuildMultiSelectButtonLabel($selectedStatusLabels, 'All Status'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <div class="dropdown-menu" aria-labelledby="status">
                                            <div class="form-check">
                                                <input class="form-check-input customer-record-filter-checkbox" type="checkbox" value="" id="status_all" <?= empty($statusFilters) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="status_all">All Status</label>
                                            </div>
                                            <?php foreach (array('Pending Approval', 'Approved', 'Rejected', 'Missed Follow-Up', 'Done', 'Postponed', 'Lost') as $statusOption) { ?>
                                                <div class="form-check">
                                                    <input class="form-check-input customer-record-filter-checkbox" type="checkbox" name="status[]" value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" id="status_<?= htmlspecialchars(strtolower(str_replace(array(' ', '-'), '_', $statusOption)), ENT_QUOTES, 'UTF-8') ?>" <?= in_array($statusOption, $statusFilters, true) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="status_<?= htmlspecialchars(strtolower(str_replace(array(' ', '-'), '_', $statusOption)), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?></label>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="assigned_user_id">Assigned User</label>
                                    <div class="dropdown customer-record-filter-dropdown follow-up-filter-multiselect" data-follow-up-multiselect="assigned_user_id">
                                        <button
                                            class="customer-record-filter-dropdown-toggle"
                                            type="button"
                                            id="assigned_user_id"
                                            data-placeholder="All User"
                                            aria-expanded="false"><?= htmlspecialchars(customerFollowUpPageBuildMultiSelectButtonLabel($selectedAssignedUserLabels, 'All User'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <div class="dropdown-menu" aria-labelledby="assigned_user_id">
                                            <div class="form-check">
                                                <input class="form-check-input customer-record-filter-checkbox" type="checkbox" value="" id="assigned_user_id_all" <?= empty($assignedUserFilters) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="assigned_user_id_all">All User</label>
                                            </div>
                                            <?php foreach ($assignedUsers as $userRow) {
                                                $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
                                                $userLabel = trim((string) (isset($userRow['name']) && $userRow['name'] !== '' ? $userRow['name'] : $userRow['username']));
                                                if ($userId <= 0 || $userLabel === '') {
                                                    continue;
                                                }
                                                ?>
                                                <div class="form-check">
                                                    <input class="form-check-input customer-record-filter-checkbox" type="checkbox" name="assigned_user_id[]" value="<?= $userId ?>" id="assigned_user_id_<?= $userId ?>" <?= in_array($userId, $assignedUserFilters, true) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="assigned_user_id_<?= $userId ?>"><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></label>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label" for="customer_type">Customer Type</label>
                                    <div class="dropdown customer-record-filter-dropdown follow-up-filter-multiselect" data-follow-up-multiselect="customer_type">
                                        <button
                                            class="customer-record-filter-dropdown-toggle"
                                            type="button"
                                            id="customer_type"
                                            data-placeholder="All Type"
                                            aria-expanded="false"><?= htmlspecialchars(customerFollowUpPageBuildMultiSelectButtonLabel($selectedCustomerTypeLabels, 'All Type'), ENT_QUOTES, 'UTF-8') ?></button>
                                        <div class="dropdown-menu" aria-labelledby="customer_type">
                                            <div class="form-check">
                                                <input class="form-check-input customer-record-filter-checkbox" type="checkbox" value="" id="customer_type_all" <?= empty($customerTypeFilters) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="customer_type_all">All Type</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input customer-record-filter-checkbox" type="checkbox" name="customer_type[]" value="new" id="customer_type_new" <?= in_array('new', $customerTypeFilters, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="customer_type_new">New</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input customer-record-filter-checkbox" type="checkbox" name="customer_type[]" value="return" id="customer_type_return" <?= in_array('return', $customerTypeFilters, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="customer_type_return">Return</label>
                                            </div>
                                        </div>
                                    </div>
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
                                            <th>Customer Tag / Label</th>
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
                                            $latestAppealLog = $roundId > 0 ? customerFollowUpFindLatestRoundActionLog($logRows, $roundId, 'resubmit_rejected_follow_up') : array();
                                            $latestAppealPayload = isset($latestAppealLog['new_value_decoded']) && is_array($latestAppealLog['new_value_decoded'])
                                                ? $latestAppealLog['new_value_decoded']
                                                : array();
                                            $rowPlatform = customerFollowUpNormalizePlatform(isset($row['platform']) ? $row['platform'] : '');
                                            $rowCustomerId = isset($row['customer_id']) ? (int) $row['customer_id'] : 0;
                                            $customerLabelMeta = isset($customerLabelMapByPlatform[$rowPlatform][$rowCustomerId]) ? $customerLabelMapByPlatform[$rowPlatform][$rowCustomerId] : array();
                                            $customerTagRows = isset($customerTagMapByPlatform[$rowPlatform][$rowCustomerId]) ? $customerTagMapByPlatform[$rowPlatform][$rowCustomerId] : array();
                                            $customerAssignedTagIds = function_exists('customerTagExtractTagIds') ? customerTagExtractTagIds($customerTagRows) : array();
                                            $orderViewUrl = customerFollowUpPageBuildOrderViewUrl($rowPlatform, isset($row['order_id']) ? (int) $row['order_id'] : 0);
                                            $customerDetailUrl = customerFollowUpPageBuildCustomerDetailUrl($connect, isset($finance_connect) ? $finance_connect : null, $rowPlatform, $rowCustomerId);
                                            $orderDisplayValue = (string) (isset($row['order_no']) && $row['order_no'] !== '' ? $row['order_no'] : $row['order_id']);
                                            $customerUsername = (string) (isset($row['customer_username']) ? $row['customer_username'] : '');
                                            $userRecordLogCustomerLabel = $customerUsername !== ''
                                                ? $customerUsername
                                                : (string) (isset($row['customer_name']) ? $row['customer_name'] : '');
                                            $userRecordLogEmbedUrl = customerFollowUpPageBuildUserRecordLogEmbedUrl(
                                                $rowPlatform,
                                                $rowCustomerId,
                                                $userRecordLogCustomerLabel,
                                                isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/customer/customer_follow_up_list.php'
                                            );
                                            $rowAttachmentPath = trim((string) (isset($row['attachment']) ? $row['attachment'] : ''));
                                            $appealAttachmentPath = trim((string) (isset($latestAppealPayload['attachment_path']) ? $latestAppealPayload['attachment_path'] : $rowAttachmentPath));
                                            $appealAttachmentUrl = customerFollowUpBuildAttachmentUrl($appealAttachmentPath);
                                            $appealMessageShortcutId = isset($latestAppealPayload['message_shortcut_id']) ? (int) $latestAppealPayload['message_shortcut_id'] : (isset($row['message_shortcut_id']) ? (int) $row['message_shortcut_id'] : 0);
                                            $appealNextFollowUpDate = trim((string) (isset($latestAppealPayload['next_follow_up_date']) ? $latestAppealPayload['next_follow_up_date'] : $displayNextFollowUpDate));
                                            $appealContactNo = trim((string) (isset($latestAppealPayload['contact_no']) ? $latestAppealPayload['contact_no'] : $effectiveContactNo));
                                            $appealRejectReason = trim((string) (isset($latestAppealPayload['reject_reason']) ? $latestAppealPayload['reject_reason'] : (isset($row['reject_reason']) ? $row['reject_reason'] : '')));

                                            $canManageOwnCase = customerFollowUpCanUserManageCase($row, USER_ID, USER_GROUP, $connect);
                                            $hasMissingNextFollowUpDate = customerFollowUpIsEmptyDateValue(isset($row['next_follow_up_date']) ? $row['next_follow_up_date'] : '');
                                            $canAppeal = $canManageOwnCase && $roundStatus === 'Rejected' && $roundNo <= 6;
                                            $canViewAppeal = !empty($latestAppealLog);
                                            $canSubmit = !$canAppeal
                                                && $canManageOwnCase
                                                && !in_array($roundStatus, array('Pending Approval', 'Approved', 'Postponed', 'Missed Follow-Up', 'Done', 'Lost', 'Rejected'), true)
                                                && $roundNo <= 6;
                                            $canSaveDelayReason = $canManageOwnCase && customerFollowUpRequiresDelayReasonBeforeMissedAction($row);
                                            $canComplete = $canManageOwnCase && customerFollowUpCanCompleteRound($row);
                                            $canRescheduleFirstRound = $canManageOwnCase
                                                && $roundId > 0
                                                && $normalizedPostponeStatus !== 'pending'
                                                && !in_array($roundStatus, array('Done', 'Lost'), true);
                                            $canRequestPostpone = $canManageOwnCase && !in_array($roundStatus, array('Done', 'Lost'), true) && customerFollowUpCanRequestPostponement($row);
                                            $canSubmitMissingNextFollowUpDate = $canManageOwnCase && $hasMissingNextFollowUpDate && !in_array($roundStatus, array('Done', 'Lost'), true);
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
                                                    <?php if ($canViewAppeal) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-danger"
                                                            title="View Appeal"
                                                            aria-label="View Appeal"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#submitFollowUpModal"
                                                            data-submit-mode="view_appeal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-order-no="<?= htmlspecialchars((string) (isset($row['order_no']) ? $row['order_no'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-name="<?= htmlspecialchars((string) (isset($row['customer_name']) ? $row['customer_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-username="<?= htmlspecialchars((string) (isset($row['customer_username']) ? $row['customer_username'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-package-name="<?= htmlspecialchars((string) (isset($row['package_name']) ? $row['package_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-received-date="<?= htmlspecialchars((string) (isset($row['received_date']) ? $row['received_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-purchase-count="<?= (int) (isset($row['purchase_count_snapshot']) ? $row['purchase_count_snapshot'] : 0) ?>"
                                                            data-customer-type="<?= htmlspecialchars($customerType, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-message-shortcut-id="<?= $appealMessageShortcutId ?>"
                                                            data-next-follow-up-date="<?= htmlspecialchars($appealNextFollowUpDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-contact-no="<?= htmlspecialchars($appealContactNo, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-existing-attachment-path="<?= htmlspecialchars($appealAttachmentPath, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-existing-attachment-url="<?= htmlspecialchars((string) $appealAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>"

                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-reject-reason="<?= htmlspecialchars($appealRejectReason, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-eye"></i><span class="ms-1">View Appeal</span>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canAppeal) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-danger"
                                                            title="Appeal"
                                                            aria-label="Appeal"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#submitFollowUpModal"
                                                            data-submit-mode="appeal"
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
                                                            data-existing-attachment-path="<?= htmlspecialchars($rowAttachmentPath, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-existing-attachment-url="<?= htmlspecialchars((string) customerFollowUpBuildAttachmentUrl($rowAttachmentPath), ENT_QUOTES, 'UTF-8') ?>"

                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-reject-reason="<?= htmlspecialchars((string) (isset($row['reject_reason']) ? $row['reject_reason'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-gavel"></i><span class="ms-1">Appeal</span>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canSubmit) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-primary"
                                                            title="Submit Follow-Up"
                                                            aria-label="Submit Follow-Up"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#submitFollowUpModal"
                                                            data-submit-mode="submit"
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
                                                            data-existing-attachment-path="<?= htmlspecialchars($rowAttachmentPath, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-existing-attachment-url="<?= htmlspecialchars((string) customerFollowUpBuildAttachmentUrl($rowAttachmentPath), ENT_QUOTES, 'UTF-8') ?>"

                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-reject-reason="">
                                                            <i class="fa-solid fa-paper-plane"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canManageOwnCase) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-dark"
                                                            title="Assign Tag"
                                                            aria-label="Assign Tag"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#assignCustomerTagModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-order-no="<?= htmlspecialchars((string) (isset($row['order_no']) ? $row['order_no'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-name="<?= htmlspecialchars((string) (isset($row['customer_name']) ? $row['customer_name'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-username="<?= htmlspecialchars((string) (isset($row['customer_username']) ? $row['customer_username'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-platform-label="<?= htmlspecialchars(customerFollowUpPagePlatformLabel(isset($row['platform']) ? $row['platform'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-customer-assigned-tag-ids="<?= htmlspecialchars((string) json_encode($customerAssignedTagIds), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-tags"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canManageOwnCase && $userRecordLogEmbedUrl !== '') { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-primary"
                                                            title="User Record Log"
                                                            aria-label="User Record Log"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#customerFollowUpUserRecordLogModal"
                                                            data-user-record-log-url="<?= htmlspecialchars($userRecordLogEmbedUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-user-record-log-customer="<?= htmlspecialchars($userRecordLogCustomerLabel !== '' ? $userRecordLogCustomerLabel : ('Customer #' . $rowCustomerId), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-clipboard-list"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canSubmitMissingNextFollowUpDate && !$canSaveDelayReason) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-outline-primary"
                                                            title="Submit Next Follow-Up Date"
                                                            aria-label="Submit Next Follow-Up Date"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#missingNextFollowUpDateModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-order-no="<?= htmlspecialchars($orderDisplayValue, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-calendar-day"></i>
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
                                                            data-order-no="<?= htmlspecialchars($orderDisplayValue, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-current-next-follow-up-date="<?= htmlspecialchars($displayNextFollowUpDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-missed-original-date="<?= htmlspecialchars($missedOriginalDate, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-delay-reason="<?= htmlspecialchars($delayReason, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-hourglass-half"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canRequestPostpone) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-warning"
                                                            title="Request Postponement"
                                                            aria-label="Request Postponement"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#postponeFollowUpModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-max-date="<?= htmlspecialchars((string) (isset($maxDateInfo['max_date']) ? $maxDateInfo['max_date'] : ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-rule-label="<?= htmlspecialchars((string) (isset($maxDateInfo['rule_label']) ? $maxDateInfo['rule_label'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-calendar-plus"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canRescheduleFirstRound) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-dark text-white"
                                                            title="Reschedule Follow-Up Date"
                                                            aria-label="Reschedule Follow-Up Date"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#rescheduleFirstRoundModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-current-date="<?= htmlspecialchars($displayNextFollowUpDate, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-calendar-plus"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($canApprove) { ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-rounded btn-success"
                                                            title="Approve Follow-Up"
                                                            aria-label="Approve Follow-Up"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#approveFollowUpModal"
                                                            data-follow-up-id="<?= $followUpId ?>"
                                                            data-round-no="<?= $roundNo ?>"
                                                            data-order-no="<?= htmlspecialchars($orderDisplayValue, ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fa-solid fa-circle-check"></i>
                                                        </button>
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
                                                    <div class="fw-semibold">
                                                        <?php if ($orderViewUrl !== '') { ?>
                                                            <a class="customer-follow-up-record-link" href="<?= htmlspecialchars($orderViewUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orderDisplayValue, ENT_QUOTES, 'UTF-8') ?></a>
                                                        <?php } else { ?>
                                                            <?= htmlspecialchars($orderDisplayValue, ENT_QUOTES, 'UTF-8') ?>
                                                        <?php } ?>
                                                    </div>
                                                    <div class="text-muted small"><?= htmlspecialchars(customerFollowUpPagePlatformLabel(isset($row['platform']) ? $row['platform'] : ''), ENT_QUOTES, 'UTF-8') ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">
                                                        <?php if ($customerDetailUrl !== '' && $customerUsername !== '') { ?>
                                                            <a class="customer-follow-up-record-link" href="<?= htmlspecialchars($customerDetailUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($customerUsername, ENT_QUOTES, 'UTF-8') ?></a>
                                                        <?php } else { ?>
                                                            <?= htmlspecialchars($customerUsername, ENT_QUOTES, 'UTF-8') ?>
                                                        <?php } ?>
                                                    </div>
                                                    <div class="text-muted small"><?= htmlspecialchars($customerType === 'return' ? 'Return' : 'New', ENT_QUOTES, 'UTF-8') ?></div>
                                                </td>
                                                <td><?= customerFollowUpPageRenderCustomerTagLabelCell($customerLabelMeta, $customerTagRows) ?></td>
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
                                            <th>Customer Tag / Label</th>
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
                        <div class="mb-2 text-muted small" id="delay_reason_context_text"></div>

                        <div class="mb-3">
                            <label class="form-label" for="delay_reason">Delay Reason</label>
                            <textarea class="form-control" id="delay_reason" name="delay_reason" rows="4" required></textarea>
                        </div>

                        <div class="mb-3 d-none" id="delay_next_follow_up_date_wrap">
                            <label class="form-label" for="delay_next_follow_up_date">
                                Next Follow-Up Date<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <input type="date" class="form-control" id="delay_next_follow_up_date" name="delay_next_follow_up_date">
                            <small class="text-muted" id="delay_next_follow_up_date_hint"></small>
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
                        <input type="hidden" name="submit_mode" id="submit_mode" value="submit">
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
                                Customer Chat Screenshot<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <input type="file" class="form-control" id="submit_attachment" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,application/pdf" required>
                            <div class="customer-follow-up-field-error" id="submit_attachment_error">Customer Chat Screenshot is required.</div>
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
                            <label class="form-label" for="submit_previous_next_follow_up_date">Previous Next Follow-Up Date</label>
                            <input type="text" class="form-control" id="submit_previous_next_follow_up_date" value="" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="submit_next_follow_up_date">
                                New Next Follow-Up Date<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <input type="date" class="form-control" id="submit_next_follow_up_date" name="next_follow_up_date" required>
                            <div class="customer-follow-up-field-error" id="submit_next_follow_up_date_error">New Next Follow-Up Date is required.</div>
                            <small class="text-muted" id="submit_next_follow_up_hint"></small>
                        </div>
                        <div class="mb-3 d-none" id="submit_reject_reason_wrap">
                            <label class="form-label">Reject Reason</label>
                            <div class="border rounded p-3 bg-light" id="submit_reject_reason_text">-</div>
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
                        <button type="button" class="btn btn-outline-secondary" id="submit_follow_up_close_btn" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submit_follow_up_submit_btn" style="text-transform: none !important;">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customerFollowUpUserRecordLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable customer-follow-up-user-record-log-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customer_follow_up_user_record_log_title">User Record Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body customer-follow-up-user-record-log-modal-body">
                    <iframe
                        id="customer_follow_up_user_record_log_iframe"
                        class="customer-follow-up-user-record-log-iframe"
                        src="about:blank"
                        title="User Record Log"></iframe>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignCustomerTagModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form" id="assign_customer_tag_form">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Tag</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="assign_customer_tag">
                        <input type="hidden" name="follow_up_id" id="assign_customer_tag_follow_up_id" value="">

                        <div class="arrival-follow-up-summary mb-3">
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Order ID</span>
                                <span id="assign_customer_tag_order_code_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Customer</span>
                                <span id="assign_customer_tag_customer_text"></span>
                            </div>
                            <div class="arrival-follow-up-summary-row">
                                <span class="arrival-follow-up-summary-label">Platform</span>
                                <span id="assign_customer_tag_platform_text"></span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assign Existing Tag</label>
                            <div class="customer-follow-up-checkbox-list" id="appeal_tag_checkbox_list">
                                <?php foreach ($customerTagOptions as $tagOption) {
                                    $tagOptionId = isset($tagOption['id']) ? (int) $tagOption['id'] : 0;
                                    if ($tagOptionId <= 0) {
                                        continue;
                                    }
                                    $tagOptionName = trim((string) (isset($tagOption['name']) ? $tagOption['name'] : ''));
                                    ?>
                                    <label class="customer-follow-up-checkbox-item" data-appeal-tag-option-item="1" data-tag-id="<?= $tagOptionId ?>">
                                        <input type="checkbox" class="form-check-input" name="appeal_tag_ids[]" value="<?= $tagOptionId ?>">
                                        <span><?= htmlspecialchars($tagOptionName !== '' ? $tagOptionName : ('Tag #' . $tagOptionId), ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php } ?>
                            </div>
                            <div class="text-muted d-none" id="appeal_tag_empty_message">No available existing tags to assign.</div>
                            <small class="text-muted">Select one or more existing tags to assign.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="appeal_new_tag_name">Create New Tag</label>
                            <input type="text" class="form-control" id="appeal_new_tag_name" name="appeal_new_tag_name" maxlength="120" placeholder="Enter new tag name">
                            <div class="customer-follow-up-field-error" id="appeal_new_tag_name_error">This tag name already exists. Please select it from Assign Existing Tag.</div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="text-transform: none !important;">Assign Tag</button>
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

    <div class="modal fade" id="approveFollowUpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approveFollowUpModalTitle">Approve Follow-Up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="approve_follow_up">
                        <input type="hidden" name="follow_up_id" id="approve_follow_up_id" value="">
                        <div class="mb-2 text-muted small" id="approve_follow_up_context_text"></div>
                        <div class="mb-3">
                            <label class="form-label" for="approval_comment">Comment</label>
                            <textarea class="form-control" id="approval_comment" name="approval_comment" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-success" style="text-transform: none !important;">Approve</button>
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

    <div class="modal fade" id="rescheduleFirstRoundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rescheduleFirstRoundModalTitle">Reschedule Follow-Up Date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="reschedule_first_round_date">
                        <input type="hidden" name="follow_up_id" id="reschedule_first_round_follow_up_id" value="">
                        <div class="mb-3">
                            <label class="form-label" for="reschedule_first_round_current_date">Current Next Follow-Up Date</label>
                            <input type="text" class="form-control" id="reschedule_first_round_current_date" value="" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="rescheduled_next_follow_up_date">New Next Follow-Up Date</label>
                            <input type="date" class="form-control" id="rescheduled_next_follow_up_date" name="rescheduled_next_follow_up_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-info text-white" style="text-transform: none !important;">Submit Reschedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="missingNextFollowUpDateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" class="customer-follow-up-action-form" id="missing_next_follow_up_date_form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="missingNextFollowUpDateModalTitle">Submit Next Follow-Up Date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['customer_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="cfu_action" value="submit_missing_next_follow_up_date">
                        <input type="hidden" name="follow_up_id" id="missing_next_follow_up_id" value="">
                        <div class="mb-2 text-muted small" id="missing_next_follow_up_context_text"></div>
                        <div class="mb-3">
                            <label class="form-label" for="submitted_missing_next_follow_up_date">
                                Next Follow-Up Date<span class="customer-follow-up-required-star">*</span>
                            </label>
                            <input type="date" class="form-control" id="submitted_missing_next_follow_up_date" name="submitted_missing_next_follow_up_date" required>
                            <small class="text-muted" id="missing_next_follow_up_hint"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="text-transform: none !important;">Submit Next Follow-Up Date</button>
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

    <script src="<?= htmlspecialchars($SITEURL . '/header/tinymce/tinymce.min.js?v=' . @filemtime(ROOT . '/header/tinymce/tinymce.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
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

        function customerFollowUpCleanupBootstrapModalState() {
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });

            document.querySelectorAll('.modal.show').forEach(function (modalElement) {
                modalElement.classList.remove('show');
                modalElement.style.display = 'none';
                modalElement.setAttribute('aria-hidden', 'true');
                modalElement.removeAttribute('aria-modal');
                modalElement.removeAttribute('role');
            });

            if (document.body) {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }
        }

        function customerFollowUpShowResultPopup(message, onContinue) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                showNotification(message || 'Action completed.', 'info');
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
                var popupInstance = bootstrap.Modal.getInstance(popupElement);
                if (popupInstance) {
                    popupInstance.dispose();
                }

                popupElement.remove();
                customerFollowUpCleanupBootstrapModalState();

                window.setTimeout(function () {
                    customerFollowUpCleanupBootstrapModalState();
                }, 80);

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
                customerFollowUpCleanupBootstrapModalState();
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

        function customerFollowUpUpdateMultiSelectButton(dropdown) {
            if (!dropdown) {
                return;
            }

            var button = dropdown.querySelector('.customer-record-filter-dropdown-toggle');
            if (!button) {
                return;
            }

            var placeholder = button.getAttribute('data-placeholder') || 'All';
            var selectedLabels = Array.prototype.slice.call(dropdown.querySelectorAll('input[type="checkbox"][name]:checked')).map(function (checkbox) {
                var checkboxId = checkbox.getAttribute('id');
                var label = checkboxId ? dropdown.querySelector('label[for="' + checkboxId + '"]') : null;
                return label ? label.textContent.trim() : '';
            }).filter(function (label) {
                return label !== '';
            });

            if (!selectedLabels.length) {
                button.textContent = placeholder;
            } else if (selectedLabels.length === 1) {
                button.textContent = selectedLabels[0];
            } else {
                button.textContent = selectedLabels.length + ' selected';
            }
        }

        function customerFollowUpSetMultiSelectOpen(dropdown, shouldOpen) {
            if (!dropdown) {
                return;
            }

            var button = dropdown.querySelector('.customer-record-filter-dropdown-toggle');
            dropdown.classList.toggle('is-open', shouldOpen);
            if (button) {
                button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            }
        }

        function customerFollowUpCloseAllMultiSelects(exceptDropdown) {
            document.querySelectorAll('[data-follow-up-multiselect]').forEach(function (dropdown) {
                if (exceptDropdown && dropdown === exceptDropdown) {
                    return;
                }

                customerFollowUpSetMultiSelectOpen(dropdown, false);
            });
        }

        function customerFollowUpSyncMultiSelectState(dropdown, changedCheckbox) {
            if (!dropdown) {
                return;
            }

            var allCheckbox = dropdown.querySelector('input[type="checkbox"]:not([name])');
            var optionCheckboxes = Array.prototype.slice.call(dropdown.querySelectorAll('input[type="checkbox"][name]'));
            var checkedOptionCount = optionCheckboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (changedCheckbox && !changedCheckbox.hasAttribute('name') && changedCheckbox.checked) {
                optionCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = false;
                });
            } else if (changedCheckbox && changedCheckbox.hasAttribute('name') && changedCheckbox.checked && allCheckbox) {
                allCheckbox.checked = false;
            }

            if (allCheckbox) {
                allCheckbox.checked = optionCheckboxes.every(function (checkbox) {
                    return !checkbox.checked;
                });
            }

            if (checkedOptionCount === 0 && allCheckbox) {
                allCheckbox.checked = true;
            }

            customerFollowUpUpdateMultiSelectButton(dropdown);
        }

        function customerFollowUpInitializeMultiSelectFilters() {
            document.querySelectorAll('[data-follow-up-multiselect]').forEach(function (dropdown) {
                var button = dropdown.querySelector('.customer-record-filter-dropdown-toggle');
                var menu = dropdown.querySelector('.dropdown-menu');

                if (button && !button.dataset.followUpToggleBound) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        var shouldOpen = !dropdown.classList.contains('is-open');
                        customerFollowUpCloseAllMultiSelects(dropdown);
                        customerFollowUpSetMultiSelectOpen(dropdown, shouldOpen);
                    });
                    button.dataset.followUpToggleBound = '1';
                }

                if (menu && !menu.dataset.followUpMenuBound) {
                    menu.addEventListener('click', function (event) {
                        event.stopPropagation();
                    });
                    menu.dataset.followUpMenuBound = '1';
                }

                dropdown.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                    if (!checkbox.dataset.followUpCheckboxBound) {
                        checkbox.addEventListener('change', function () {
                            customerFollowUpSyncMultiSelectState(dropdown, checkbox);
                        });
                        checkbox.dataset.followUpCheckboxBound = '1';
                    }
                });

                customerFollowUpSyncMultiSelectState(dropdown, null);
            });

            if (!document.body.dataset.followUpMultiSelectCloseBound) {
                document.addEventListener('click', function (event) {
                    if (event.target.closest('[data-follow-up-multiselect]')) {
                        return;
                    }

                    customerFollowUpCloseAllMultiSelects();
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        customerFollowUpCloseAllMultiSelects();
                    }
                });

                document.body.dataset.followUpMultiSelectCloseBound = '1';
            }
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

                    customerFollowUpCleanupBootstrapModalState();
                    tableSection.innerHTML = newTableSection.innerHTML;
                    customerFollowUpInitializeTableSection();
                    customerFollowUpCleanupBootstrapModalState();
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

        function customerFollowUpSubmitAttachmentHasFiles() {
            var attachmentInput = document.getElementById('submit_attachment');
            return !!(attachmentInput && attachmentInput.files && attachmentInput.files.length > 0);
        }

        function customerFollowUpSetSubmitAttachmentError(hasError) {
            var attachmentInput = document.getElementById('submit_attachment');
            if (attachmentInput) {
                attachmentInput.classList.toggle('is-invalid', !!hasError);
            }

            var error = document.getElementById('submit_attachment_error');
            if (error) {
                error.classList.toggle('is-visible', !!hasError);
            }
        }

        function clearSubmitFollowUpRequiredErrors() {
            customerFollowUpSetSubmitAttachmentError(false);
            setCustomerFollowUpFieldError('submit_message_shortcut_id', 'submit_message_shortcut_error', false);
            setCustomerFollowUpFieldError('submit_next_follow_up_date', 'submit_next_follow_up_date_error', false);
        }

        function customerFollowUpValidateYmdDate(value) {
            return /^\d{4}-\d{2}-\d{2}$/.test(String(value || '').trim());
        }

        function customerFollowUpFormatYmdDateForDisplay(value) {
            var normalizedValue = String(value || '').trim();
            if (!customerFollowUpValidateYmdDate(normalizedValue)) {
                return normalizedValue;
            }

            var parts = normalizedValue.split('-');
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }

        function customerFollowUpGetAppealUserRecordLogEditor() {
            if (!window.tinymce || typeof window.tinymce.get !== 'function') {
                return null;
            }

            return window.tinymce.get('appeal_user_record_log');
        }

        function customerFollowUpSetAppealUserRecordLogContent(html) {
            var normalizedHtml = String(html || '');
            var editor = customerFollowUpGetAppealUserRecordLogEditor();
            if (editor) {
                editor.setContent(normalizedHtml);
                return;
            }

            var textarea = document.getElementById('appeal_user_record_log');
            if (textarea) {
                textarea.value = normalizedHtml;
            }
        }

        function customerFollowUpSetAppealUserRecordLogReadOnly(isReadOnly) {
            var editor = customerFollowUpGetAppealUserRecordLogEditor();
            if (editor) {
                if (editor.mode && typeof editor.mode.set === 'function') {
                    editor.mode.set(isReadOnly ? 'readonly' : 'design');
                } else if (typeof editor.setMode === 'function') {
                    editor.setMode(isReadOnly ? 'readonly' : 'design');
                }
            }

            var textarea = document.getElementById('appeal_user_record_log');
            if (textarea) {
                textarea.readOnly = !!isReadOnly;
                textarea.disabled = !!isReadOnly;
            }
        }

        function customerFollowUpParseJsonAttribute(value, fallback) {
            var normalizedFallback = typeof fallback === 'undefined' ? [] : fallback;
            if (typeof value !== 'string' || value.trim() === '') {
                return normalizedFallback;
            }

            try {
                var parsed = JSON.parse(value);
                return Array.isArray(parsed) ? parsed : normalizedFallback;
            } catch (error) {
                return normalizedFallback;
            }
        }

        function customerFollowUpResetAppealTagSelections() {
            var checkboxList = document.getElementById('appeal_tag_checkbox_list');
            if (!checkboxList) {
                return;
            }

            Array.prototype.slice.call(checkboxList.querySelectorAll('input[name="appeal_tag_ids[]"]')).forEach(function (checkbox) {
                checkbox.checked = false;
            });

            Array.prototype.slice.call(checkboxList.querySelectorAll('[data-temp-checkbox-item="1"]')).forEach(function (item) {
                item.remove();
            });
        }

        function customerFollowUpSetAppealTagSelections(tagItems) {
            var checkboxList = document.getElementById('appeal_tag_checkbox_list');
            if (!checkboxList) {
                return;
            }

            customerFollowUpResetAppealTagSelections();

            customerFollowUpNormalizeAppealTagItems(tagItems).forEach(function (tagItem) {
                var tagId = String(tagItem && typeof tagItem.id !== 'undefined' ? tagItem.id : '').trim();
                var tagLabel = String(tagItem && typeof tagItem.label !== 'undefined' ? tagItem.label : '').trim();
                if (tagId === '') {
                    return;
                }

                var matchingCheckbox = checkboxList.querySelector('input[name="appeal_tag_ids[]"][value="' + customerFollowUpCssEscape(tagId) + '"]');
                if (matchingCheckbox) {
                    matchingCheckbox.checked = true;
                    return;
                }

                var tempLabel = document.createElement('label');
                tempLabel.className = 'customer-follow-up-checkbox-item';
                tempLabel.setAttribute('data-temp-checkbox-item', '1');

                var tempCheckbox = document.createElement('input');
                tempCheckbox.type = 'checkbox';
                tempCheckbox.className = 'form-check-input';
                tempCheckbox.name = 'appeal_tag_ids[]';
                tempCheckbox.value = tagId;
                tempCheckbox.checked = true;
                tempLabel.appendChild(tempCheckbox);

                var tempText = document.createElement('span');
                tempText.textContent = tagLabel || ('Tag #' + tagId);
                tempLabel.appendChild(tempText);

                checkboxList.appendChild(tempLabel);
            });
        }

        function customerFollowUpNormalizeTagIdList(tagIds) {
            var normalizedTagIds = [];

            if (!Array.isArray(tagIds)) {
                return normalizedTagIds;
            }

            tagIds.forEach(function (tagId) {
                var normalizedTagId = String(tagId).trim();
                if (normalizedTagId !== '' && normalizedTagIds.indexOf(normalizedTagId) === -1) {
                    normalizedTagIds.push(normalizedTagId);
                }
            });

            return normalizedTagIds;
        }

        function customerFollowUpFilterAppealTagOptions(assignedTagIds, keepTagItems) {
            var checkboxList = document.getElementById('appeal_tag_checkbox_list');
            if (!checkboxList) {
                return;
            }

            var assignedTagIdList = customerFollowUpNormalizeTagIdList(assignedTagIds);
            var keepTagIdList = [];

            customerFollowUpNormalizeAppealTagItems(keepTagItems).forEach(function (tagItem) {
                var tagId = String(tagItem && typeof tagItem.id !== 'undefined' ? tagItem.id : '').trim();
                if (tagId !== '' && keepTagIdList.indexOf(tagId) === -1) {
                    keepTagIdList.push(tagId);
                }
            });

            var visibleCount = 0;

            Array.prototype.slice.call(checkboxList.querySelectorAll('[data-appeal-tag-option-item="1"]')).forEach(function (item) {
                var checkbox = item.querySelector('input[name="appeal_tag_ids[]"]');
                var tagId = checkbox ? String(checkbox.value).trim() : '';
                var shouldHide = tagId !== '' && assignedTagIdList.indexOf(tagId) !== -1 && keepTagIdList.indexOf(tagId) === -1;

                item.classList.toggle('d-none', shouldHide);

                if (checkbox) {
                    if (shouldHide) {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                    } else {
                        checkbox.disabled = false;
                        visibleCount++;
                    }
                }
            });

            var emptyMessage = document.getElementById('appeal_tag_empty_message');
            if (emptyMessage) {
                emptyMessage.classList.toggle('d-none', visibleCount > 0);
            }
        }

        function customerFollowUpApplySubmitFollowUpFieldErrors(fieldErrors) {
            if (!fieldErrors || typeof fieldErrors !== 'object') {
                return false;
            }

            var hasFieldError = false;
            if (fieldErrors.appeal_new_tag_name) {
                var errorNode = document.getElementById('appeal_new_tag_name_error');
                if (errorNode) {
                    errorNode.textContent = String(fieldErrors.appeal_new_tag_name);
                }
                setCustomerFollowUpFieldError('appeal_new_tag_name', 'appeal_new_tag_name_error', true);
                hasFieldError = true;
            }

            if (hasFieldError) {
                var newTagField = document.getElementById('appeal_new_tag_name');
                if (newTagField) {
                    newTagField.focus();
                }
            }

            return hasFieldError;
        }

        function customerFollowUpCssEscape(value) {
            if (window.CSS && typeof window.CSS.escape === 'function') {
                return window.CSS.escape(value);
            }

            return String(value).replace(/["\\]/g, '\\$&');
        }

        function customerFollowUpNormalizeAppealTagItems(tagItems) {
            if (!Array.isArray(tagItems)) {
                return [];
            }

            var normalizedItems = [];
            tagItems.forEach(function (tagItem) {
                var tagId = String(tagItem && typeof tagItem.id !== 'undefined' ? tagItem.id : '').trim();
                var tagLabel = String(tagItem && typeof tagItem.label !== 'undefined' ? tagItem.label : '').trim();
                if (tagId === '') {
                    return;
                }

                normalizedItems.push({
                    id: tagId,
                    label: tagLabel
                });
            });

            return normalizedItems;
        }

        function customerFollowUpSyncAppealUserRecordLogEditor() {
            if (!window.tinymce || typeof window.tinymce.triggerSave !== 'function') {
                return;
            }

            window.tinymce.triggerSave();

            var textarea = document.getElementById('appeal_user_record_log');
            if (!textarea) {
                return;
            }

            var normalizedText = String(textarea.value || '')
                .replace(/<br\s*\/?>/gi, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/<[^>]*>/g, ' ')
                .trim();

            if (normalizedText === '') {
                textarea.value = '';
            }
        }

        function customerFollowUpEnsureAppealUserRecordLogEditor() {
            var textarea = document.getElementById('appeal_user_record_log');
            if (!textarea || !window.tinymce || typeof window.tinymce.init !== 'function') {
                return Promise.resolve(null);
            }

            var editor = customerFollowUpGetAppealUserRecordLogEditor();
            if (editor) {
                return Promise.resolve(editor);
            }

            if (appealUserRecordLogEditorPromise) {
                return appealUserRecordLogEditorPromise;
            }

            appealUserRecordLogEditorPromise = window.tinymce.init({
                selector: '#appeal_user_record_log',
                base_url: <?= json_encode(rtrim((string) $SITEURL, '/') . '/header/tinymce') ?>,
                license_key: 'gpl',
                menubar: false,
                branding: false,
                promotion: false,
                statusbar: false,
                height: 220,
                plugins: 'lists link autoresize',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
                autoresize_bottom_margin: 12,
                content_style: 'body { font-family: Open Sans, sans-serif; font-size: 14px; }',
                setup: function (instance) {
                    instance.on('init', function () {
                        instance.setContent(textarea.value || '');
                    });
                }
            }).then(function () {
                return customerFollowUpGetAppealUserRecordLogEditor();
            }).catch(function (error) {
                console.warn('Failed to initialize the appeal user record log editor.', error);
                return null;
            }).finally(function () {
                appealUserRecordLogEditorPromise = null;
            });

            return appealUserRecordLogEditorPromise;
        }

        function customerFollowUpSetSubmitFollowUpModalReadOnly(isReadOnly) {
            ['submit_message_shortcut_id', 'submit_next_follow_up_date', 'submit_contact_no'].forEach(function (fieldId) {
                var field = document.getElementById(fieldId);
                if (!field) {
                    return;
                }

                if (fieldId === 'submit_contact_no') {
                    field.readOnly = !!isReadOnly;
                }
                field.disabled = !!isReadOnly;
            });

            var attachmentInput = document.getElementById('submit_attachment');
            if (attachmentInput) {
                attachmentInput.disabled = !!isReadOnly;
            }

            var closeBtn = document.getElementById('submit_follow_up_close_btn');
            if (closeBtn) {
                closeBtn.textContent = isReadOnly ? 'Close' : 'Cancel';
            }

            var submitBtn = document.getElementById('submit_follow_up_submit_btn');
            if (submitBtn) {
                submitBtn.disabled = !!isReadOnly;
                submitBtn.classList.toggle('d-none', !!isReadOnly);
            }

            var contactEditBtn = document.getElementById('submit_contact_edit_btn');
            if (contactEditBtn) {
                contactEditBtn.disabled = !!isReadOnly;
                contactEditBtn.classList.toggle('d-none', !!isReadOnly);
            }
        }

        function validateMissingNextFollowUpDateForm() {
            var dateInput = document.getElementById('submitted_missing_next_follow_up_date');
            if (!dateInput || !customerFollowUpValidateYmdDate(dateInput.value)) {
                if (dateInput) {
                    dateInput.focus();
                }
                customerFollowUpShowResultPopup('Next Follow-Up Date is required in YYYY-MM-DD format.');
                return false;
            }

            return true;
        }

        function validateSubmitFollowUpRequiredFields() {
            var attachmentInput = document.getElementById('submit_attachment');
            var shortcutInput = document.getElementById('submit_message_shortcut_id');
            var nextDateInput = document.getElementById('submit_next_follow_up_date');

            var attachmentIsRequired = attachmentInput ? attachmentInput.required : true;
            var attachmentMissing = attachmentIsRequired && (!attachmentInput || !attachmentInput.files || attachmentInput.files.length === 0);
            var shortcutMissing = !shortcutInput || shortcutInput.value.trim() === '';
            var nextDateMissing = !nextDateInput || nextDateInput.value.trim() === '';

            customerFollowUpSetSubmitAttachmentError(attachmentMissing);
            setCustomerFollowUpFieldError('submit_message_shortcut_id', 'submit_message_shortcut_error', shortcutMissing);
            setCustomerFollowUpFieldError('submit_next_follow_up_date', 'submit_next_follow_up_date_error', nextDateMissing);

            if (attachmentMissing || shortcutMissing || nextDateMissing || !customerFollowUpValidateYmdDate(nextDateInput ? nextDateInput.value : '')) {
                var nextDateInvalid = !nextDateMissing && !customerFollowUpValidateYmdDate(nextDateInput ? nextDateInput.value : '');
                if (nextDateInvalid) {
                    setCustomerFollowUpFieldError('submit_next_follow_up_date', 'submit_next_follow_up_date_error', true);
                }

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

                    if (form.id === 'submit_follow_up_form') {
                        if (!validateSubmitFollowUpRequiredFields()) {
                            return;
                        }
                    } else if (form.id === 'assign_customer_tag_form') {
                        customerFollowUpSyncAppealUserRecordLogEditor();
                    } else {
                        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                            return;
                        }
                        if (form.id === 'missing_next_follow_up_date_form' && !validateMissingNextFollowUpDateForm()) {
                            return;
                        }
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
                            var fieldErrors = payload && payload.field_errors && typeof payload.field_errors === 'object'
                                ? payload.field_errors
                                : {};

                            if (
                                !wasSuccessful
                                && (form.id === 'submit_follow_up_form' || form.id === 'assign_customer_tag_form')
                                && Object.keys(fieldErrors).length > 0
                            ) {
                                customerFollowUpApplySubmitFollowUpFieldErrors(fieldErrors);
                                return;
                            }

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

            var isImagePath = function (path) {
                return /\.(png|jpe?g|webp|gif)$/i.test(path || '');
            };

            var getFileNameFromPath = function (path) {
                path = String(path || '');
                var parts = path.split('/');
                return parts.length > 0 ? parts[parts.length - 1] : path;
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
                var existingPath = fileInput.getAttribute('data-existing-path') || '';
                var existingUrl = fileInput.getAttribute('data-existing-url') || '';

                clearPreview();
                clearExistingLink();

                if (!existingUrl || !existingPath) {
                    return;
                }

                if (existingLink) {
                    existingLink.href = existingUrl;
                    existingLink.textContent = 'Previous Attachment: ' + getFileNameFromPath(existingPath);
                    existingLink.classList.remove('d-none');
                }

                if (isImagePath(existingPath)) {
                    previewImage.src = existingUrl;
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
                customerFollowUpSetSubmitAttachmentError(false);

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

        var appealNewTagNameField = document.getElementById('appeal_new_tag_name');
        if (appealNewTagNameField) {
            appealNewTagNameField.addEventListener('input', function () {
                setCustomerFollowUpFieldError('appeal_new_tag_name', 'appeal_new_tag_name_error', false);
            });
        }

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
                var submitMode = (button.getAttribute('data-submit-mode') || 'submit').toLowerCase();
                var isAppeal = submitMode === 'appeal';
                var isViewAppeal = submitMode === 'view_appeal';
                var isAppealContext = isAppeal || isViewAppeal;
                var rejectReason = button.getAttribute('data-reject-reason') || '';

                document.getElementById('submit_follow_up_id').value = followUpId;
                document.getElementById('submit_mode').value = isAppeal ? 'appeal' : 'submit';
                document.getElementById('submitFollowUpModalTitle').textContent = isViewAppeal ? 'View Appeal' : (isAppeal ? 'Appeal Rejected Follow-Up' : 'Submit Follow-Up');
                document.getElementById('submit_follow_up_order_code_text').textContent = orderNo || '-';
                document.getElementById('submit_follow_up_customer_text').textContent = button.getAttribute('data-customer-username') || button.getAttribute('data-customer-name') || '-';
                document.getElementById('submit_follow_up_package_text').textContent = button.getAttribute('data-package-name') || '-';
                document.getElementById('submit_follow_up_received_date_text').textContent = button.getAttribute('data-received-date') || '';
                document.getElementById('submit_follow_up_customer_type_text').textContent =
                    (button.getAttribute('data-customer-type') || 'new') === 'return'
                        ? 'Return Customer (' + (button.getAttribute('data-purchase-count') || '0') + ' previous purchase)'
                        : 'New Customer';
                document.getElementById('submit_message_shortcut_id').value = messageShortcutId;
                document.getElementById('submit_previous_next_follow_up_date').value = customerFollowUpFormatYmdDateForDisplay(nextFollowUpDate);
                document.getElementById('submit_next_follow_up_date').value = nextFollowUpDate;
                document.getElementById('submit_next_follow_up_date').max = maxDate;
                document.getElementById('submit_next_follow_up_hint').textContent = ruleLabel;

                document.getElementById('submit_follow_up_submit_btn').textContent = isAppeal ? 'Submit Appeal' : 'Submit';
                document.getElementById('submit_reject_reason_text').textContent = rejectReason || 'No reject reason recorded.';
                document.getElementById('submit_reject_reason_wrap').classList.toggle('d-none', !isAppealContext);

                var submitAttachmentInput = document.getElementById('submit_attachment');
                submitAttachmentInput.value = '';

                submitAttachmentInput.setAttribute('data-existing-path', existingAttachmentPath);
                submitAttachmentInput.setAttribute('data-existing-url', existingAttachmentUrl);

                submitAttachmentInput.required = !isViewAppeal && !(isAppeal && existingAttachmentPath !== '');

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

                customerFollowUpSetSubmitFollowUpModalReadOnly(isViewAppeal);
            });

            submitFollowUpModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('submit_mode').value = 'submit';
                document.getElementById('submit_reject_reason_wrap').classList.add('d-none');
                customerFollowUpSetSubmitFollowUpModalReadOnly(false);
            });
        }

        var customerFollowUpUserRecordLogModal = document.getElementById('customerFollowUpUserRecordLogModal');
        if (customerFollowUpUserRecordLogModal) {
            customerFollowUpUserRecordLogModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var iframe = document.getElementById('customer_follow_up_user_record_log_iframe');
                var title = document.getElementById('customer_follow_up_user_record_log_title');

                if (!button || !iframe) {
                    return;
                }

                var userRecordLogUrl = button.getAttribute('data-user-record-log-url') || '';
                var customerLabel = button.getAttribute('data-user-record-log-customer') || '';

                if (title) {
                    title.textContent = customerLabel !== ''
                        ? 'User Record Log - ' + customerLabel
                        : 'User Record Log';
                }

                iframe.src = userRecordLogUrl !== '' ? userRecordLogUrl : 'about:blank';
            });

            customerFollowUpUserRecordLogModal.addEventListener('hidden.bs.modal', function () {
                var iframe = document.getElementById('customer_follow_up_user_record_log_iframe');
                if (iframe) {
                    iframe.src = 'about:blank';
                }
            });
        }        

        var assignCustomerTagModal = document.getElementById('assignCustomerTagModal');
        if (assignCustomerTagModal) {
            assignCustomerTagModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) {
                    return;
                }

                var assignedTagIds = customerFollowUpParseJsonAttribute(button.getAttribute('data-customer-assigned-tag-ids') || '[]', []);
                document.getElementById('assign_customer_tag_follow_up_id').value = button.getAttribute('data-follow-up-id') || '';
                document.getElementById('assign_customer_tag_order_code_text').textContent = button.getAttribute('data-order-no') || '-';
                document.getElementById('assign_customer_tag_customer_text').textContent = button.getAttribute('data-customer-username') || button.getAttribute('data-customer-name') || '-';
                document.getElementById('assign_customer_tag_platform_text').textContent = button.getAttribute('data-platform-label') || '-';

                customerFollowUpResetAppealTagSelections();
                customerFollowUpFilterAppealTagOptions(assignedTagIds, []);
                document.getElementById('appeal_new_tag_name').value = '';
                setCustomerFollowUpFieldError('appeal_new_tag_name', 'appeal_new_tag_name_error', false);
            });

            assignCustomerTagModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('assign_customer_tag_follow_up_id').value = '';
                customerFollowUpResetAppealTagSelections();
                customerFollowUpFilterAppealTagOptions([], []);
                document.getElementById('appeal_new_tag_name').value = '';
                setCustomerFollowUpFieldError('appeal_new_tag_name', 'appeal_new_tag_name_error', false);
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
                var roundNo = button ? (button.getAttribute('data-round-no') || '') : '';
                var orderNo = button ? (button.getAttribute('data-order-no') || '') : '';
                var currentNextFollowUpDate = button ? (button.getAttribute('data-current-next-follow-up-date') || '') : '';
                var missedOriginalDate = button ? (button.getAttribute('data-missed-original-date') || '') : '';
                var maxDate = button ? (button.getAttribute('data-max-date') || '') : '';
                var ruleLabel = button ? (button.getAttribute('data-rule-label') || '') : '';
                var dateWrap = document.getElementById('delay_next_follow_up_date_wrap');
                var dateInput = document.getElementById('delay_next_follow_up_date');
                var dateHint = document.getElementById('delay_next_follow_up_date_hint');

                document.getElementById('delay_reason_follow_up_id').value = button ? (button.getAttribute('data-follow-up-id') || '') : '';
                document.getElementById('delay_reason').value = button ? (button.getAttribute('data-delay-reason') || '') : '';
                document.getElementById('delayReasonModalTitle').textContent = 'Save Delay Reason - Round ' + roundNo;
                document.getElementById('delay_reason_missed_date_text').textContent = missedOriginalDate ? 'Missed Original Date: ' + missedOriginalDate : '';
                document.getElementById('delay_reason_context_text').textContent = orderNo !== ''
                    ? ('Order ID: ' + orderNo + (roundNo !== '' ? (' | Round ' + roundNo) : ''))
                    : '';

                if (dateWrap && dateInput) {
                    dateInput.value = currentNextFollowUpDate;
                    dateInput.max = maxDate;
                    dateInput.required = true;

                    if (dateHint) {
                        dateHint.textContent = ruleLabel;
                    }

                    dateWrap.classList.remove('d-none');
                }
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

        var approveFollowUpModal = document.getElementById('approveFollowUpModal');
        if (approveFollowUpModal) {
            approveFollowUpModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var orderNo = button ? (button.getAttribute('data-order-no') || '') : '';
                var roundNo = button ? (button.getAttribute('data-round-no') || '') : '';

                document.getElementById('approve_follow_up_id').value = button ? (button.getAttribute('data-follow-up-id') || '') : '';
                document.getElementById('approval_comment').value = '';
                document.getElementById('approveFollowUpModalTitle').textContent = 'Approve Follow-Up';
                document.getElementById('approve_follow_up_context_text').textContent = orderNo !== ''
                    ? ('Order ID: ' + orderNo + (roundNo !== '' ? (' | Round ' + roundNo) : ''))
                    : (roundNo !== '' ? ('Round ' + roundNo) : '');
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

        var rescheduleFirstRoundModal = document.getElementById('rescheduleFirstRoundModal');
        if (rescheduleFirstRoundModal) {
            rescheduleFirstRoundModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var followUpId = button.getAttribute('data-follow-up-id') || '';
                var roundNo = button.getAttribute('data-round-no') || '';
                var currentDate = button.getAttribute('data-current-date') || '';
                var dateInput = document.getElementById('rescheduled_next_follow_up_date');

                document.getElementById('reschedule_first_round_follow_up_id').value = followUpId;
                document.getElementById('reschedule_first_round_current_date').value = currentDate || '-';
                dateInput.value = '';
                dateInput.removeAttribute('max');
                document.getElementById('rescheduleFirstRoundModalTitle').textContent = 'Reschedule Follow-Up Date - Round ' + roundNo;
            });
        }

        var missingNextFollowUpDateModal = document.getElementById('missingNextFollowUpDateModal');
        if (missingNextFollowUpDateModal) {
            missingNextFollowUpDateModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var roundNo = button ? (button.getAttribute('data-round-no') || '') : '';
                var orderNo = button ? (button.getAttribute('data-order-no') || '') : '';
                var maxDate = button ? (button.getAttribute('data-max-date') || '') : '';
                var ruleLabel = button ? (button.getAttribute('data-rule-label') || '') : '';

                document.getElementById('missing_next_follow_up_id').value = button ? (button.getAttribute('data-follow-up-id') || '') : '';
                document.getElementById('submitted_missing_next_follow_up_date').value = '';
                document.getElementById('submitted_missing_next_follow_up_date').max = maxDate;
                document.getElementById('missing_next_follow_up_hint').textContent = ruleLabel;
                document.getElementById('missingNextFollowUpDateModalTitle').textContent = 'Submit Next Follow-Up Date';
                document.getElementById('missing_next_follow_up_context_text').textContent = orderNo !== ''
                    ? ('Order ID: ' + orderNo + (roundNo !== '' ? (' | Round ' + roundNo) : ''))
                    : (roundNo !== '' ? ('Round ' + roundNo) : '');
            });

            missingNextFollowUpDateModal.addEventListener('shown.bs.modal', function () {
                var field = document.getElementById('submitted_missing_next_follow_up_date');
                if (field) {
                    field.focus();
                }
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

        customerFollowUpInitializeMultiSelectFilters();

        customerFollowUpInitializeTableSection();

        if (customerFollowUpInitialFlash && customerFollowUpInitialFlash.message) {
            customerFollowUpShowResultPopup(customerFollowUpInitialFlash.message);
        }
    </script>
