<?php
if (ob_get_level() === 0) {
    ob_start();
}

$currentPagePin = 0;
$pageTitle = 'User Record Log';

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/common_variable.php';
include_once ROOT . '/include/user_record_log.php';

$requestedCustomerColumn = '';
if (isset($_REQUEST['customer_column'])) {
    $requestedCustomerColumn = trim((string) $_REQUEST['customer_column']);
}

$allowedCustomerColumns = array('cust_id', 'shopee_cust_id', 'facebook_cust_id', 'website_cust_id', 'lazada_cust_id', 'urbanism_member_id');
if (!in_array($requestedCustomerColumn, $allowedCustomerColumns, true)) {
    $requestedCustomerColumn = '';
}

$returnUrlParams = array();
$requestedCustomerId = numberInput('customer_id');
if ($requestedCustomerId !== '') {
    $returnUrlParams['customer_id'] = (int) $requestedCustomerId;
}
if ($requestedCustomerColumn !== '') {
    $returnUrlParams['customer_column'] = $requestedCustomerColumn;
}

$returnUrl = '/users/user_record_log.php';
if (!empty($returnUrlParams)) {
    $returnUrl .= '?' . http_build_query($returnUrlParams);
}

$userRecordLogDeleteModuleKey = 'user_record_log';
$userRecordLogDeleteState = orderDeleteApprovalInitPageState();
if (
    empty($userRecordLogDeleteState['approval_mode']) &&
    !empty($userRecordLogDeleteState['data_id']) &&
    function_exists('orderDeleteApprovalReadLatestRequestBySource')
) {
    $legacyUserRecordLogRequest = orderDeleteApprovalReadLatestRequestBySource(
        $connect,
        $userRecordLogDeleteModuleKey,
        (int) $userRecordLogDeleteState['data_id']
    );
    if (
        !empty($legacyUserRecordLogRequest) &&
        trim((string) (isset($legacyUserRecordLogRequest['request_status']) ? $legacyUserRecordLogRequest['request_status'] : '')) === 'rejected' &&
        (int) (isset($legacyUserRecordLogRequest['request_user_id']) ? $legacyUserRecordLogRequest['request_user_id'] : 0) === (int) USER_ID
    ) {
        $userRecordLogDeleteState['approval_mode'] = true;
        $userRecordLogDeleteState['request_id'] = (int) (isset($legacyUserRecordLogRequest['id']) ? $legacyUserRecordLogRequest['id'] : 0);
    }
}
$userRecordLogDeleteCallback = orderDeleteApprovalBuildStandardDeleteCallback(array(
    'data_connect' => $connect,
    'audit_connect' => $connect,
    'table_name' => USER_RECORD_LOG,
    'page_title' => $pageTitle,
    'fallback_data_id' => isset($userRecordLogDeleteState['data_id']) ? (int) $userRecordLogDeleteState['data_id'] : 0,
    'source_noun' => 'User Record',
    'delete_success_message' => 'User record deleted successfully.',
    'not_found_message' => 'User record log was not found.',
));
$userRecordLogApprovalPanelHtml = orderDeleteApprovalHandlePageFlow(array(
    'connect' => $connect,
    'request_id' => isset($userRecordLogDeleteState['request_id']) ? (int) $userRecordLogDeleteState['request_id'] : 0,
    'module_key' => $userRecordLogDeleteModuleKey,
    'data_id' => isset($userRecordLogDeleteState['data_id']) ? (int) $userRecordLogDeleteState['data_id'] : 0,
    'current_user_id' => (int) USER_ID,
    'page_title' => $pageTitle,
    'redirect_page' => $SITEURL . $returnUrl,
    'approval_mode' => !empty($userRecordLogDeleteState['approval_mode']),
    'use_confirmation_dialog' => true,
    'delete_callback' => $userRecordLogDeleteCallback,
));

if (isset($finance_connect) && ($finance_connect instanceof mysqli)) {
    $customerLookupConnect = $finance_connect;
} else {
    $customerLookupConnect = $connect;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    urlHandleUserRecordLogRequest($connect, $connect, array(
        'table_name' => USER_RECORD_LOG,
        'page_title' => $pageTitle,
        'customer_column' => $requestedCustomerColumn,
        'customer_lookup_connect' => $customerLookupConnect,
    ));
    exit;
}

include_once '../menuHeader.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$context = urlResolveUserRecordLogContext($connect, $connect, array(
    'return_url' => $returnUrl,
    'ajax_url' => $SITEURL . '/users/user_record_log.php',
    'customer_column' => $requestedCustomerColumn,
    'customer_lookup_connect' => $customerLookupConnect,
));

$pageHeading = $pageTitle;
if (!empty($context['customer_label'])) {
    $pageHeading .= ' - ' . $context['customer_label'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
<div class="page-load-cover" style="display: block !important;">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                        <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                </div>
            </div>
            <?php
            urlRenderUserRecordLogModule($connect, $connect, array(
                'table_name' => USER_RECORD_LOG,
                'context' => $context,
                'section_heading' => '',
                'show_scope_note' => true,
                'approval_panel_html' => $userRecordLogApprovalPanelHtml,
                'approval_record_id' => isset($userRecordLogDeleteState['data_id']) ? (int) $userRecordLogDeleteState['data_id'] : 0,
                'approval_request_id' => isset($userRecordLogDeleteState['request_id']) ? (int) $userRecordLogDeleteState['request_id'] : 0,
            ));
            ?>
        </div>
    </div>
</div>
</body>
</html>
