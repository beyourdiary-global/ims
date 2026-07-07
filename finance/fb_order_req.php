<?php
ob_start();
$currentPagePin = 69;
$pageTitle = "Facebook Order Request";

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = FB_ORDER_REQ;

$orderDeleteApprovalModuleKey = 'facebook_order_request';
$orderDeleteApprovalState = orderDeleteApprovalInitPageState();
$orderDeleteApprovalMode = !empty($orderDeleteApprovalState['approval_mode']);
$orderDeleteApprovalRequestId = isset($orderDeleteApprovalState['request_id']) ? (int) $orderDeleteApprovalState['request_id'] : 0;
$dataId = isset($orderDeleteApprovalState['data_id']) ? $orderDeleteApprovalState['data_id'] : '';
$act = isset($orderDeleteApprovalState['act']) ? $orderDeleteApprovalState['act'] : '';
$orderDeleteApprovalPanelHtml = isset($orderDeleteApprovalState['panel_html']) ? (string) $orderDeleteApprovalState['panel_html'] : '';
$pageAction = getPageAction($act);
$allowed_ext = array("png", "jpg", "jpeg", "svg", "pdf");


$redirectPage = $SITEURL . '/finance/fb_order_req_table.php';
$back_redirect_page = commonResolveBackUrl($redirectPage);
$fbCurrentRequestPath = isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$fbBackRedirectPath = (string) parse_url((string) $back_redirect_page, PHP_URL_PATH);
if ($fbBackRedirectPath === '' || ($fbCurrentRequestPath !== '' && $fbBackRedirectPath === $fbCurrentRequestPath)) {
    $back_redirect_page = $redirectPage;
}
$redirectLink = '<script>location.href=' . json_encode($redirectPage) . ';</script>';
$clearLocalStorage = '<script>localStorage.clear();</script>';
$pendingStatusUpdate = shopeeOmsNormalizeStatusCode(post('updateStatusBtn'));
$forShouldSaveBeforeStatusUpdate = $pendingStatusUpdate !== '' && $act === 'E';
$forTriggerStatusTransitionAfterSave = false;
$forHandleStatusTransition = function ($newStatus) use ($connect, $finance_connect, $dataId, $pageTitle, $cdate, $ctime, $tblName, $redirectPage) {
    $newStatus = shopeeOmsNormalizeStatusCode($newStatus);
    $transitionRemark = 'Order Status Update to ' . shopeeOmsGetStatusLabel($newStatus);
    $transitionResult = shopeeOmsExecuteTransition($connect, $finance_connect, (int) $dataId, $newStatus, array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => $transitionRemark,
        'platform' => 'facebook',
    ));

    if (!empty($transitionResult['success'])) {
        $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
        $newStatusCode = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : '';
        audit_log(array(
            'log_act' => 'edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'OMS transition ' . $oldStatus . ' -> ' . $newStatusCode,
            'query_table' => $tblName,
            'page' => $pageTitle,
            'connect' => $connect,
            'oldval' => 'order_status: ' . $oldStatus,
            'changes' => 'order_status: ' . $newStatusCode,
            'act_msg' => USER_NAME . " updated Facebook order #" . (int) $dataId . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
        ));
        echo '<script>alert(' . json_encode((string) $transitionRemark) . '); window.location.replace(' . json_encode((string) $redirectPage) . ');</script>';
        exit;
    }
    return array(
        'success' => false,
        'message' => (string) (isset($transitionResult['message']) ? $transitionResult['message'] : 'Unable to update order status.'),
    );
};

$img_path = '../' . img_server . 'finance/fb_order_req/';
if (!file_exists($img_path)) {
    mkdir($img_path, 0777, true);
}
$forStatusOptions = shopeeOmsGetEditableStatusOptions();
$forWarehouseRows = shopeeOmsLoadActiveWarehouses($connect);
$forDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect, $forWarehouseRows);
$forWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect, true);
$forWarehouseOptionMap = array();
foreach ($forWarehouseRows as $forWarehouseRow) {
    $forWarehouseId = isset($forWarehouseRow['id']) ? (int) $forWarehouseRow['id'] : 0;
    if ($forWarehouseId > 0) {
        $forWarehouseOptionMap[$forWarehouseId] = isset($forWarehouseRow['name']) ? (string) $forWarehouseRow['name'] : ('Warehouse #' . $forWarehouseId);
    }
}
$forPopupErrorMessage = '';

function fbOrderReqMemberPointJsonExit($payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fbOrderReqMemberPointPlatformLabel($platform)
{
    $platform = memberPointNormalizePlatform($platform);
    if ($platform === 'shopee') {
        return 'Shopee';
    }
    if ($platform === 'lazada') {
        return 'Lazada';
    }
    if ($platform === 'facebook') {
        return 'Facebook';
    }
    if ($platform === 'website') {
        return 'Website';
    }

    return ucfirst($platform);
}

function fbOrderReqBuildLinkedMemberLabel($platform, $customerLabel)
{
    $platformLabel = fbOrderReqMemberPointPlatformLabel($platform);
    $customerLabel = trim((string) $customerLabel);
    if ($platformLabel === '') {
        return $customerLabel;
    }
    if ($customerLabel === '') {
        return $platformLabel;
    }

    return $platformLabel . ' | ' . $customerLabel;
}

function fbOrderReqBuildRedeemLabel($connect, $redeemId, $fallbackPoints = 0)
{
    $rewardRow = memberPointFetchRedeemRowById($connect, $redeemId);
    if (!empty($rewardRow)) {
        return memberPointBuildRewardDisplayText($rewardRow);
    }

    $fallbackPoints = (int) $fallbackPoints;
    return $fallbackPoints > 0 ? ($fallbackPoints . ' points') : '';
}

function fbOrderReqBuildCashbackLabel($points, $amount = null)
{
    if (function_exists('memberPointBuildCashbackLabel')) {
        return memberPointBuildCashbackLabel($points, $amount);
    }

    $points = max(0, (int) $points);
    if ($amount === null) {
        $amount = (float) $points;
    }
    $amount = max(0, (float) $amount);

    return 'Cashback ' . number_format($amount, 2, '.', '') . ' RM (' . $points . ' points)';
}

function fbOrderReqDeleteOrderById($financeConnect, $tableName, $orderId)
{
    $orderId = (int) $orderId;
    if (!($financeConnect instanceof mysqli) || $orderId <= 0 || trim((string) $tableName) === '') {
        return false;
    }

    $sql = "DELETE FROM `" . $tableName . "` WHERE `id` = " . $orderId . " LIMIT 1";
    return (bool) mysqli_query($financeConnect, $sql);
}

function fbOrderReqRestoreOrderRow($financeConnect, $tableName, $orderId, $row)
{
    $orderId = (int) $orderId;
    if (!($financeConnect instanceof mysqli) || $orderId <= 0 || trim((string) $tableName) === '' || !is_array($row) || empty($row)) {
        return false;
    }

    $stringColumns = array(
        'name', 'fb_link', 'contact', 'sales_pic', 'country', 'brand', 'series', 'package',
        'fb_page', 'channel', 'price', 'pay_method', 'ship_rec_name', 'ship_rec_add',
        'ship_rec_contact', 'remark', 'attachment', 'order_status', 'airbill_no',
        'airbill_attachment', 'member_point_platform', 'member_point_customer_label',
        'update_by',
    );
    $intColumns = array(
        'stock_out_warehouse_id', 'member_point_customer_id', 'member_point_redeem_id',
        'member_point_redeem_points', 'member_point_transaction_id',
    );

    $assignments = array();
    foreach ($stringColumns as $columnName) {
        $columnValue = isset($row[$columnName]) ? $row[$columnName] : null;
        if ($columnValue === null || $columnValue === '') {
            $assignments[] = "`" . $columnName . "` = NULL";
        } else {
            $assignments[] = "`" . $columnName . "` = '" . mysqli_real_escape_string($financeConnect, (string) $columnValue) . "'";
        }
    }

    foreach ($intColumns as $columnName) {
        $columnValue = isset($row[$columnName]) ? $row[$columnName] : null;
        if ($columnValue === null || $columnValue === '') {
            $assignments[] = "`" . $columnName . "` = NULL";
        } else {
            $assignments[] = "`" . $columnName . "` = " . (int) $columnValue;
        }
    }

    if (isset($row['update_date']) && trim((string) $row['update_date']) !== '') {
        $assignments[] = "`update_date` = '" . mysqli_real_escape_string($financeConnect, (string) $row['update_date']) . "'";
    } else {
        $assignments[] = "`update_date` = NULL";
    }
    if (isset($row['update_time']) && trim((string) $row['update_time']) !== '') {
        $assignments[] = "`update_time` = '" . mysqli_real_escape_string($financeConnect, (string) $row['update_time']) . "'";
    } else {
        $assignments[] = "`update_time` = NULL";
    }

    $sql = "UPDATE `" . $tableName . "` SET " . implode(', ', $assignments) . " WHERE `id` = " . $orderId . " LIMIT 1";
    return (bool) mysqli_query($financeConnect, $sql);
}

function fbOrderReqWriteMemberPointAudit($connect, $pageTitle, $logAct, $message, $queryRec = '', $oldVal = '', $changes = '')
{
    audit_log(array(
        'log_act' => $logAct,
        'cdate' => $GLOBALS['cdate'] ?? date('Y-m-d'),
        'ctime' => $GLOBALS['ctime'] ?? date('H:i:s'),
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $queryRec,
        'query_table' => FB_ORDER_REQ,
        'oldval' => $oldVal,
        'changes' => $changes,
        'act_msg' => $message,
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

function fbOrderReqCustomerJsonExit($payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fbOrderReqFetchCustomerDealRow($connect, $customerId)
{
    $customerId = (int) $customerId;
    if (!($connect instanceof mysqli) || $customerId <= 0) {
        return array();
    }

    $result = getData('*', "id = '" . $customerId . "' AND status = 'A'", 'LIMIT 1', FB_CUST_DEALS, $connect);
    if (!$result || $result->num_rows <= 0) {
        return array();
    }

    $row = $result->fetch_assoc();
    return is_array($row) ? $row : array();
}

function fbOrderReqFindCustomerDealId($connect, $customerName, $customerLink)
{
    if (!($connect instanceof mysqli)) {
        return 0;
    }

    $customerName = trim((string) $customerName);
    $customerLink = trim((string) $customerLink);
    if ($customerName === '' || $customerLink === '') {
        return 0;
    }

    $result = getData(
        'id',
        "name = '" . mysqli_real_escape_string($connect, $customerName) . "' AND fb_link = '" . mysqli_real_escape_string($connect, $customerLink) . "' AND status = 'A'",
        'LIMIT 1',
        FB_CUST_DEALS,
        $connect
    );
    if (!$result || $result->num_rows <= 0) {
        return 0;
    }

    $row = $result->fetch_assoc();
    return isset($row['id']) ? (int) $row['id'] : 0;
}

function fbOrderReqResolveLookupLabel($connect, $tableName, $id, $columnName = 'name', $dbConnect = null)
{
    $id = (int) $id;
    $tableName = trim((string) $tableName);
    $columnName = trim((string) $columnName);
    $activeConnect = $dbConnect instanceof mysqli ? $dbConnect : $connect;
    if (!($activeConnect instanceof mysqli) || $id <= 0 || $tableName === '' || $columnName === '') {
        return '';
    }

    $result = getData($columnName, "id = '" . $id . "' AND status = 'A'", 'LIMIT 1', $tableName, $activeConnect);
    if (!$result || $result->num_rows <= 0) {
        return '';
    }

    $row = $result->fetch_assoc();
    return isset($row[$columnName]) ? trim((string) $row[$columnName]) : '';
}

function fbOrderReqBuildCustomerAjaxPayload($connect, $financeConnect, $customerRow)
{
    if (!is_array($customerRow) || empty($customerRow)) {
        return array(
            'success' => false,
            'message' => 'Facebook customer not found.',
        );
    }

    return array(
        'success' => true,
        'customer' => array(
            'id' => isset($customerRow['id']) ? (int) $customerRow['id'] : 0,
            'name' => isset($customerRow['name']) ? trim((string) $customerRow['name']) : '',
            'fb_link' => isset($customerRow['fb_link']) ? trim((string) $customerRow['fb_link']) : '',
            'contact' => isset($customerRow['contact']) ? trim((string) $customerRow['contact']) : '',
            'sales_pic' => isset($customerRow['sales_pic']) ? (int) $customerRow['sales_pic'] : 0,
            'sales_pic_label' => fbOrderReqResolveLookupLabel($connect, USR_USER, isset($customerRow['sales_pic']) ? (int) $customerRow['sales_pic'] : 0, 'name'),
            'country' => isset($customerRow['country']) ? (int) $customerRow['country'] : 0,
            'country_label' => fbOrderReqResolveLookupLabel($connect, COUNTRIES, isset($customerRow['country']) ? (int) $customerRow['country'] : 0, 'nicename'),
            'brand' => isset($customerRow['brand']) ? (int) $customerRow['brand'] : 0,
            'brand_label' => fbOrderReqResolveLookupLabel($connect, BRAND, isset($customerRow['brand']) ? (int) $customerRow['brand'] : 0, 'name'),
            'series' => isset($customerRow['series']) ? (int) $customerRow['series'] : 0,
            'series_label' => fbOrderReqResolveLookupLabel($connect, BRD_SERIES, isset($customerRow['series']) ? (int) $customerRow['series'] : 0, 'name'),
            'fb_page' => isset($customerRow['fb_page']) ? (int) $customerRow['fb_page'] : 0,
            'fb_page_label' => fbOrderReqResolveLookupLabel($connect, FB_PAGE_ACC, isset($customerRow['fb_page']) ? (int) $customerRow['fb_page'] : 0, 'name', $financeConnect),
            'channel' => isset($customerRow['channel']) ? (int) $customerRow['channel'] : 0,
            'channel_label' => fbOrderReqResolveLookupLabel($connect, CHANEL_SC_MD, isset($customerRow['channel']) ? (int) $customerRow['channel'] : 0, 'name', $financeConnect),
            'ship_rec_name' => isset($customerRow['ship_rec_name']) ? trim((string) $customerRow['ship_rec_name']) : '',
            'ship_rec_contact' => isset($customerRow['ship_rec_contact']) ? trim((string) $customerRow['ship_rec_contact']) : '',
            'ship_rec_add' => isset($customerRow['ship_rec_add']) ? trim((string) $customerRow['ship_rec_add']) : '',
            'remark' => isset($customerRow['remark']) ? trim((string) $customerRow['remark']) : '',
        ),
    );
}

function fbOrderReqSearchCustomerDeals($connect, $keyword, $limit = 15)
{
    $keyword = trim((string) $keyword);
    $limit = max(1, (int) $limit);
    $limit = min($limit, 30);
    if (!($connect instanceof mysqli) || $keyword === '') {
        return array();
    }

    $escapedKeyword = mysqli_real_escape_string($connect, $keyword);
    $sql = "SELECT `id`, `name`, `fb_link`, `contact`
            FROM `" . FB_CUST_DEALS . "`
            WHERE `status` = 'A'
              AND (
                `name` LIKE '%" . $escapedKeyword . "%'
                OR `fb_link` LIKE '%" . $escapedKeyword . "%'
                OR `contact` LIKE '%" . $escapedKeyword . "%'
              )
            ORDER BY `name` ASC, `id` DESC
            LIMIT " . $limit;
    $result = mysqli_query($connect, $sql);
    if (!$result) {
        return array();
    }

    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        if (!is_array($row)) {
            continue;
        }
        $rows[] = array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'name' => isset($row['name']) ? trim((string) $row['name']) : '',
            'fb_link' => isset($row['fb_link']) ? trim((string) $row['fb_link']) : '',
            'contact' => isset($row['contact']) ? trim((string) $row['contact']) : '',
        );
    }

    return $rows;
}

function fbOrderReqSyncSelectedCustomerDeal($connect, $customerId, $customerData, $pageTitle = '')
{
    $customerId = (int) $customerId;
    if (!($connect instanceof mysqli) || $customerId <= 0 || !is_array($customerData) || empty($customerData)) {
        return array(
            'success' => true,
            'updated' => false,
        );
    }

    $currentRow = fbOrderReqFetchCustomerDealRow($connect, $customerId);
    if (empty($currentRow)) {
        return array(
            'success' => false,
            'updated' => false,
            'message' => 'Selected Facebook customer record was not found.',
        );
    }

    $stringColumns = array(
        'name', 'fb_link', 'contact', 'ship_rec_name', 'ship_rec_add', 'ship_rec_contact', 'remark',
    );
    $idColumns = array(
        'sales_pic', 'country', 'brand', 'series', 'fb_page', 'channel',
    );

    $assignments = array();
    $oldValues = array();
    $newValues = array();
    $changedFields = array();

    foreach ($stringColumns as $columnName) {
        $newValue = trim((string) (isset($customerData[$columnName]) ? $customerData[$columnName] : ''));
        $oldValue = trim((string) (isset($currentRow[$columnName]) ? $currentRow[$columnName] : ''));
        if ($newValue === $oldValue) {
            continue;
        }

        $assignments[] = "`" . $columnName . "` = '" . mysqli_real_escape_string($connect, $newValue) . "'";
        $oldValues[] = ($oldValue === '' ? 'Empty Value' : $oldValue);
        $newValues[] = ($newValue === '' ? 'Empty Value' : $newValue);
        $changedFields[] = $columnName;
    }

    foreach ($idColumns as $columnName) {
        $newValue = (int) (isset($customerData[$columnName]) ? $customerData[$columnName] : 0);
        $oldValue = (int) (isset($currentRow[$columnName]) ? $currentRow[$columnName] : 0);
        if ($newValue === $oldValue) {
            continue;
        }

        $assignments[] = "`" . $columnName . "` = " . $newValue;
        $oldValues[] = $oldValue > 0 ? (string) $oldValue : 'Empty Value';
        $newValues[] = $newValue > 0 ? (string) $newValue : 'Empty Value';
        $changedFields[] = $columnName;
    }

    if (empty($assignments)) {
        return array(
            'success' => true,
            'updated' => false,
        );
    }

    $assignments[] = "`update_date` = curdate()";
    $assignments[] = "`update_time` = curtime()";
    $assignments[] = "`update_by` = '" . mysqli_real_escape_string($connect, (string) USER_ID) . "'";

    $query = "UPDATE `" . FB_CUST_DEALS . "` SET " . implode(', ', $assignments) . " WHERE `id` = " . $customerId . " LIMIT 1";
    $updated = mysqli_query($connect, $query);
    if (!$updated) {
        return array(
            'success' => false,
            'updated' => false,
            'message' => mysqli_error($connect),
        );
    }

    audit_log(array(
        'log_act' => 'Edit',
        'cdate' => $GLOBALS['cdate'] ?? date('Y-m-d'),
        'ctime' => $GLOBALS['ctime'] ?? date('H:i:s'),
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $query,
        'query_table' => FB_CUST_DEALS,
        'oldval' => implodeWithComma($oldValues),
        'changes' => implodeWithComma($newValues),
        'act_msg' => USER_NAME . " updated linked Facebook customer record #<b>" . $customerId . "</b> from <b><i>" . $pageTitle . "</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ));

    return array(
        'success' => true,
        'updated' => true,
        'query' => $query,
        'changed_fields' => $changedFields,
    );
}

// to display data to input
if ($dataId) { //edit/remove/view
    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $finance_connect);

    if ($result != false && $result->num_rows > 0) {
        $dataExisted = 1;
        $row = $result->fetch_assoc();
    } else {
        // If $result is false or no data found ($act==null)
        $errorExist = 1;
        $_SESSION['tempValConfirmBox'] = true;
        $act = "F";
    }
}

$memberPointAjaxRequested = ((string) input('member_point_ajax') === '1') || ((string) post('member_point_ajax') === '1');
if ($memberPointAjaxRequested) {
    $ajaxPlatform = memberPointNormalizePlatform(postSpaceFilter('member_point_platform'));
    $ajaxCustomerId = (int) postSpaceFilter('member_point_customer_id');
    $ajaxLocked = $act === 'E' && !empty($row['member_point_transaction_id']);
    $payload = memberPointBuildLookupPayload($connect, $finance_connect, $ajaxPlatform, $ajaxCustomerId, array(
        'allowed_platforms' => array('shopee', 'lazada'),
        'locked' => $ajaxLocked,
    ));
    fbOrderReqMemberPointJsonExit($payload);
}

$fbCustomerAjaxRequested = ((string) input('fb_customer_ajax') === '1') || ((string) post('fb_customer_ajax') === '1');
if ($fbCustomerAjaxRequested) {
    $ajaxCustomerId = (int) postSpaceFilter('customer_id');
    $customerRow = fbOrderReqFetchCustomerDealRow($connect, $ajaxCustomerId);
    fbOrderReqCustomerJsonExit(fbOrderReqBuildCustomerAjaxPayload($connect, $finance_connect, $customerRow));
}

$fbCustomerSearchAjaxRequested = ((string) input('fb_customer_search_ajax') === '1') || ((string) post('fb_customer_search_ajax') === '1');
if ($fbCustomerSearchAjaxRequested) {
    $searchKeyword = postSpaceFilter('search');
    $resultRows = fbOrderReqSearchCustomerDeals($connect, $searchKeyword, 15);
    fbOrderReqCustomerJsonExit(array(
        'success' => true,
        'results' => $resultRows,
    ));
}

if ($pendingStatusUpdate !== '' && !$forShouldSaveBeforeStatusUpdate) {
    $forTransitionResult = $forHandleStatusTransition($pendingStatusUpdate);
    if (is_array($forTransitionResult) && empty($forTransitionResult['success'])) {
        $transitionErrorState = shopeeOmsResolveStatusTransitionErrorState(
            $pendingStatusUpdate,
            isset($forTransitionResult['message']) ? $forTransitionResult['message'] : '',
            'Unable to update order status.'
        );
        if ($transitionErrorState['stock_out_warehouse_err'] !== '') {
            $stock_out_warehouse_err = $transitionErrorState['stock_out_warehouse_err'];
        }
        $forPopupErrorMessage = $transitionErrorState['popup_error_message'];
    }
}

if (!($dataId) && !($act)) {
    renderNotificationScript('Invalid action.', 'error', $redirectPage);
    exit;
}

$forExecuteDeleteOrder = orderDeleteApprovalBuildStandardDeleteCallback(array(
    'data_connect' => $finance_connect,
    'audit_connect' => $connect,
    'table_name' => $tblName,
    'page_title' => $pageTitle,
    'fallback_data_id' => (int) $dataId,
    'label_field' => 'name',
));

$orderDeleteApprovalPanelHtml = orderDeleteApprovalHandlePageFlow(array(
    'connect' => $connect,
    'request_id' => $orderDeleteApprovalRequestId,
    'module_key' => $orderDeleteApprovalModuleKey,
    'data_id' => (int) $dataId,
    'current_user_id' => (int) USER_ID,
    'page_title' => $pageTitle,
    'redirect_page' => $redirectPage,
    'clear_local_storage' => $clearLocalStorage,
    'approval_mode' => $orderDeleteApprovalMode,
    'delete_callback' => $forExecuteDeleteOrder,
));

if (post('actionBtn') || $forShouldSaveBeforeStatusUpdate) {
    $action = post('actionBtn');
    if ($action === '' && $forShouldSaveBeforeStatusUpdate) {
        $action = 'updRecord';
    }

    $for_name = postSpaceFilter('for_name');
    $for_customer_id = (int) postSpaceFilter('for_customer_id');
    $for_link = postSpaceFilter('for_link');
    $for_ctc = postSpaceFilter('for_contact');
    $for_pic = postSpaceFilter('for_pic_hidden');
    $for_country = postSpaceFilter('for_country_hidden');
    $for_brand = postSpaceFilter('for_brand_hidden');
    $for_series = postSpaceFilter('for_series_hidden');
    $for_pkg = postSpaceFilter('for_pkg_hidden');
    $for_fbpage = postSpaceFilter('for_fbpage_hidden');
    $for_channel = postSpaceFilter('for_channel_hidden');
    $for_price = postSpaceFilter('for_price');
    $for_pay = postSpaceFilter('for_pay_meth_hidden');
    $for_rec_name = postSpaceFilter('for_rec_name');
    $for_rec_ctc = postSpaceFilter('for_rec_ctc');
    $for_rec_add = postSpaceFilter('for_rec_add');
    $for_remark = postSpaceFilter('for_remark');
    $for_order_status = shopeeOmsNormalizeStatusCode(postSpaceFilter('for_order_status'));
    if ($for_order_status === '') {
        $for_order_status = isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P';
    }
    $forCurrentEffectiveWarehouseId = isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $forDefaultWarehouseId) : $forDefaultWarehouseId;
    $for_stock_out_warehouse_id = shopeeOmsNormalizeWarehouseId(postSpaceFilter('for_stock_out_warehouse_id'));
    if ($for_stock_out_warehouse_id <= 0) {
        $for_stock_out_warehouse_id = $forDefaultWarehouseId;
    }
    $forStockOutWarehouseEditable = $action === 'addRecord'
        ? true
        : shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : '');
    if (!$forStockOutWarehouseEditable && $action === 'updRecord') {
        $for_stock_out_warehouse_id = $forCurrentEffectiveWarehouseId;
    }
    $for_update_airbill = strtolower(trim((string) postSpaceFilter('for_update_airbill')));
    if ($for_update_airbill === '') {
        $for_update_airbill = 'yes';
    }
    $for_airbill_no = postSpaceFilter('for_airbill_no');
    $for_airbill_attachment = postSpaceFilter('for_airbill_attachment_value');
    $for_order_status_sql = mysqli_real_escape_string($finance_connect, $for_order_status);
    $for_airbill_no_sql = mysqli_real_escape_string($finance_connect, $for_airbill_no);

    $for_attach = null;
    if (isset($_FILES["for_attach"]) && $_FILES["for_attach"]["size"] != 0) {
        $for_attach = $_FILES["for_attach"]["name"];
    } elseif (filter_has_var(INPUT_POST, 'for_attachmentValue')) {
        $for_attach = post('for_attachmentValue');
    } elseif (filter_has_var(INPUT_POST, 'existing_attachment')) {
        $for_attach = post('existing_attachment');
    }

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
    $memberPointAllowedPlatforms = array('shopee', 'lazada');
    $memberPointAllowedUseTypes = array('none', 'gift', 'cashback');
    $memberPointPostedPlatform = memberPointNormalizePlatform(postSpaceFilter('member_point_platform'));
    $memberPointPostedCustomerId = (int) postSpaceFilter('member_point_customer_id');
    $memberPointPostedCustomerLabel = trim((string) postSpaceFilter('member_point_customer_label'));
    $memberPointPostedUseType = strtolower(trim((string) postSpaceFilter('member_point_use_type')));
    if (!in_array($memberPointPostedUseType, $memberPointAllowedUseTypes, true)) {
        $memberPointPostedUseType = 'none';
    }
    $memberPointPostedRedeemId = (int) postSpaceFilter('member_point_redeem_id');
    $memberPointPostedCashbackPoints = (int) postSpaceFilter('member_point_cashback_points');
    $memberPointPostedOriginalPrice = max(0, (float) postSpaceFilter('member_point_original_price'));
    $memberPointLocked = $action === 'updRecord' && !empty($row['member_point_transaction_id']);
    $memberPointPlatform = $memberPointLocked ? memberPointNormalizePlatform(isset($row['member_point_platform']) ? $row['member_point_platform'] : '') : $memberPointPostedPlatform;
    $memberPointCustomerId = $memberPointLocked ? (int) (isset($row['member_point_customer_id']) ? $row['member_point_customer_id'] : 0) : $memberPointPostedCustomerId;
    $memberPointCustomerLabel = $memberPointLocked ? trim((string) (isset($row['member_point_customer_label']) ? $row['member_point_customer_label'] : '')) : $memberPointPostedCustomerLabel;
    $memberPointUseType = $memberPointLocked ? 'none' : $memberPointPostedUseType;
    $memberPointRedeemId = $memberPointLocked ? (int) (isset($row['member_point_redeem_id']) ? $row['member_point_redeem_id'] : 0) : $memberPointPostedRedeemId;
    $memberPointCashbackPoints = $memberPointLocked ? 0 : $memberPointPostedCashbackPoints;
    $memberPointOriginalPrice = $memberPointLocked ? 0 : $memberPointPostedOriginalPrice;
    $memberPointRedeemPoints = $memberPointLocked ? (int) (isset($row['member_point_redeem_points']) ? $row['member_point_redeem_points'] : 0) : 0;
    $memberPointTransactionId = $memberPointLocked ? (int) (isset($row['member_point_transaction_id']) ? $row['member_point_transaction_id'] : 0) : 0;
    $memberPointLookup = array();
    $memberPointSelectedReward = array();
    $memberPointSelectedRewardLabel = '';
    $memberPointLinkChanged = false;
    $memberPointRedeemCreated = false;
    $memberPointAuditOldLink = '';
    $memberPointAuditNewLink = '';
    $memberPointAuditFailureMessage = '';
    $memberPointCreatedTransactionId = 0;
    $memberPointEffectiveLabel = '';
    $memberPointPrivateEarnCreated = false;
    $memberPointPrivateEarnPoints = 0;
    $memberPointPrivateEarnTierLabel = '';

    switch ($action) {
        case 'addRecord':
        case 'updRecord':
            $error = 0;

            if ($for_customer_id <= 0 && $action === 'updRecord') {
                $for_customer_id = fbOrderReqFindCustomerDealId($connect, $for_name, $for_link);
            }

            $selectedFbCustomerRow = fbOrderReqFetchCustomerDealRow($connect, $for_customer_id);

            if ($_FILES["for_attach"]["size"] != 0) {
                // move file
                $for_file_name = $_FILES["for_attach"]["name"];
                $for_file_tmp_name = $_FILES["for_attach"]["tmp_name"];
                $img_ext = pathinfo($for_file_name, PATHINFO_EXTENSION);
                $img_ext_lc = strtolower($img_ext);

                if (in_array($img_ext_lc, $allowed_ext)) {
                    // Get the original file name without the extension
                    $base_name = pathinfo($for_file_name, PATHINFO_FILENAME);
                    $highestNumber = 0;
                    
                    // Check if files with this exact name already exist
                    $files = glob($img_path . $base_name . '_*.' . $img_ext);
                    foreach ($files as $file) {
                        $filename = basename($file);
                        if (preg_match('/^' . preg_quote($base_name, '/') . '_(\d+)\.' . preg_quote($img_ext, '/') . '$/', $filename, $matches)) {
                            $number = (int) $matches[1];
                            $highestNumber = max($highestNumber, $number);
                        }
                    }

                    // Use the original name, but append _1, _2 etc. only if it already exists
                    if (file_exists($img_path . $for_file_name) || $highestNumber > 0) {
                        $unique_id = $highestNumber + 1;
                        $new_file_name = $base_name . '_' . $unique_id . '.' . $img_ext_lc;
                    } else {
                        $new_file_name = $for_file_name;
                    }

                    // Move the uploaded file
                    if (move_uploaded_file($for_file_tmp_name, $img_path . $new_file_name)) {
                        $for_attach = $new_file_name; // Update $for_attach with the new filename
                    } else {
                        $err2 = "Failed to upload the file.";
                    }
                } else
                    $err2 = "Only allow PNG, JPG, JPEG, SVG or PDF file";
            }

            if ($for_update_airbill === 'yes' && isset($_FILES['for_airbill_attachment']) && isset($_FILES['for_airbill_attachment']['size']) && (int) $_FILES['for_airbill_attachment']['size'] > 0) {
                $forAirbillUploadResult = shopeeOmsStoreAirbillAttachmentUpload(
                    $_FILES['for_airbill_attachment'],
                    $connect,
                    $for_brand,
                    $for_pkg,
                    'fb_order_req'
                );
                if (!empty($forAirbillUploadResult['success'])) {
                    $for_airbill_attachment = isset($forAirbillUploadResult['path']) ? (string) $forAirbillUploadResult['path'] : '';
                } else {
                    $airbill_attachment_err = isset($forAirbillUploadResult['message']) ? (string) $forAirbillUploadResult['message'] : 'Failed to upload the airbill attachment.';
                    $error = 1;
                }
            }

            if ($for_update_airbill !== 'yes') {
                if ($action === 'updRecord') {
                    $for_airbill_no = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                    $for_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                } else {
                    $for_airbill_no = '';
                    $for_airbill_attachment = '';
                }
            }

            if (!$for_name) {
                $name_err = "Name cannot be empty.";
                break;
            } else if (!$for_link) {
                $link_err = "Facebook Link cannot be empty.";
                break;
            } else if (!$for_ctc) {
                $contact_err = "Contact cannot be empty.";
                break;
            } else if (!$for_pic && $for_pic < 1) {
                $pic_err = "Sales Person-In-Charge cannot be empty.";
                break;
            } else if (!$for_country && $for_country < 1) {
                $country_err = "Country cannot be empty.";
                break;
            } else if (!$for_brand && $for_brand < 1) {
                $brand_err = "Brand cannot be empty.";
                break;
            } else if (!$for_series && $for_series < 1) {
                $series_err = "Series cannot be empty.";
                break;
            } else if (!$for_pkg && $for_pkg < 1) {
                $pkg_err = "Package cannot be empty.";
                break;
            } else if (!$for_fbpage && $for_fbpage < 1) {
                $fbpage_err = "Facebook Page cannot be empty.";
                break;
            } else if (!$for_channel && $for_channel < 1) {
                $channel_err = "Channel cannot be empty.";
                break;
            } else if (!$for_price) {
                $price_err = "Price cannot be empty.";
                break;
            } else if (!$for_pay && $for_pay < 1) {
                $pay_err = "Payment Method cannot be empty.";
                break;
            } else if (!$for_rec_name) {
                $rec_name_err = "Receiver Name cannot be empty.";
                break;
            } else if (!$for_rec_ctc) {
                $rec_ctc_err = "Receiver Contact cannot be empty.";
                break;
            } else if (!$for_rec_add) {
                $rec_add_err = "Receiver Address cannot be empty.";
                break;
            } else if (!$for_attach) {
                $desc_err = "Attachment cannot be empty.";
                break;
            }

            if ($forStockOutWarehouseEditable) {
                if ($for_stock_out_warehouse_id <= 0) {
                    $stock_out_warehouse_err = "Stock Out Warehouse is required.";
                    $error = 1;
                } else if (!isset($forWarehouseOptionMap[$for_stock_out_warehouse_id])) {
                    $stock_out_warehouse_err = "Please select a valid active Stock Out Warehouse.";
                    $error = 1;
                }
            }

            $forEffectiveAirbill = $for_airbill_no;
            if ($action === 'updRecord' && $for_update_airbill !== 'yes') {
                $forEffectiveAirbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
            }

            $forStatusValidation = shopeeOmsValidateInitialStatusAndAirbill($for_order_status, $forEffectiveAirbill);
            if (!$forStatusValidation['valid']) {
                $airbill_err = isset($forStatusValidation['message']) ? (string) $forStatusValidation['message'] : 'Invalid order status or airbill.';
                $error = 1;
            }

            if ($for_update_airbill === 'yes') {
                if (trim((string) $for_airbill_no) === '') {
                    $airbill_err = 'Airbill No cannot be empty when Update Airbill is enabled.';
                    $error = 1;
                }
                if (trim((string) $for_airbill_attachment) === '') {
                    $airbill_attachment_err = 'Airbill Attachment cannot be empty when Update Airbill is enabled.';
                    $error = 1;
                }
            }

            if (!$memberPointLocked) {
                if (!in_array($memberPointUseType, $memberPointAllowedUseTypes, true)) {
                    $memberPointUseType = 'none';
                }
                if ($memberPointUseType !== 'gift') {
                    $memberPointRedeemId = 0;
                }
                if ($memberPointUseType !== 'cashback') {
                    $memberPointCashbackPoints = 0;
                    $memberPointOriginalPrice = 0;
                }

                if ($memberPointPlatform !== '' && !in_array($memberPointPlatform, $memberPointAllowedPlatforms, true)) {
                    $member_point_platform_err = 'Please select a valid member point platform.';
                    $error = 1;
                }

                if ($memberPointCustomerId > 0 && $memberPointPlatform === '') {
                    $member_point_platform_err = 'Please select Shopee or Lazada before linking a member.';
                    $error = 1;
                }

                if ($memberPointPlatform !== '' && $memberPointCustomerId <= 0) {
                    $member_point_customer_err = 'Please select a valid member.';
                    $error = 1;
                }

                if ($memberPointUseType !== 'none' && ($memberPointPlatform === '' || $memberPointCustomerId <= 0)) {
                    $member_point_redeem_err = 'Please link a valid member before applying member point redemption or cashback.';
                    $error = 1;
                }

                if ($memberPointPlatform !== '' && $memberPointCustomerId > 0 && !$error) {
                    $memberPointLookup = memberPointBuildLookupPayload($connect, $finance_connect, $memberPointPlatform, $memberPointCustomerId, array(
                        'allowed_platforms' => $memberPointAllowedPlatforms,
                        'sync_ledger' => true,
                    ));

                    if (empty($memberPointLookup['success'])) {
                        $member_point_customer_err = isset($memberPointLookup['message']) ? (string) $memberPointLookup['message'] : 'Unable to load member point details.';
                        $error = 1;
                    } else {
                        $memberPointCustomerLabel = isset($memberPointLookup['customer_label']) ? trim((string) $memberPointLookup['customer_label']) : $memberPointCustomerLabel;
                        $memberPointEffectiveLabel = fbOrderReqBuildLinkedMemberLabel($memberPointPlatform, $memberPointCustomerLabel);
                    }
                }

                if ($memberPointUseType === 'gift' && !$error) {
                    if ($memberPointRedeemId <= 0) {
                        $member_point_redeem_err = 'Please select a redeem item.';
                        $error = 1;
                    }
                }

                if ($memberPointUseType === 'gift' && $memberPointRedeemId > 0 && !$error) {
                    $memberPointSelectedReward = memberPointFetchRedeemRowById($connect, $memberPointRedeemId);
                    if (empty($memberPointSelectedReward)) {
                        $member_point_redeem_err = 'Selected redeem item is no longer available.';
                        $error = 1;
                    } else {
                        $memberPointRequiredPoints = (int) (isset($memberPointSelectedReward['point_tier']) ? $memberPointSelectedReward['point_tier'] : 0);
                        $memberPointAvailablePoints = (int) (isset($memberPointLookup['available_points']) ? $memberPointLookup['available_points'] : 0);
                        $memberPointEligibleRewardIds = array_map(function ($rewardRow) {
                            return isset($rewardRow['id']) ? (int) $rewardRow['id'] : 0;
                        }, isset($memberPointLookup['rewards']) && is_array($memberPointLookup['rewards']) ? $memberPointLookup['rewards'] : array());
                        if ($memberPointRequiredPoints <= 0) {
                            $member_point_redeem_err = 'Selected redeem item has an invalid point tier.';
                            $error = 1;
                        } else if ($memberPointAvailablePoints < $memberPointRequiredPoints) {
                            $member_point_redeem_err = 'The selected member does not have enough available points for this redeem item.';
                            $error = 1;
                        } else if (!in_array($memberPointRedeemId, $memberPointEligibleRewardIds, true)) {
                            $member_point_redeem_err = memberRedeemBuildThresholdFailureMessage($memberPointSelectedReward);
                            $error = 1;
                        } else {
                            $memberPointRedeemPoints = $memberPointRequiredPoints;
                            $memberPointSelectedRewardLabel = memberPointBuildRewardDisplayText($memberPointSelectedReward);
                        }
                    }
                } else if ($memberPointUseType === 'cashback' && !$error) {
                    $memberPointAvailablePoints = (int) (isset($memberPointLookup['available_points']) ? $memberPointLookup['available_points'] : 0);
                    $memberPointOriginalPrice = $memberPointPostedOriginalPrice > 0 ? $memberPointPostedOriginalPrice : 0;
                    $memberPointCashbackPoints = max(0, (int) $memberPointPostedCashbackPoints);

                    if ($memberPointOriginalPrice <= 0) {
                        $member_point_redeem_err = 'Original order amount is required before applying cashback.';
                        $error = 1;
                    } else if ($memberPointCashbackPoints <= 0) {
                        $member_point_redeem_err = 'Cashback points must be greater than zero.';
                        $error = 1;
                    } else {
                        $memberPointCashbackLimit = (int) floor($memberPointOriginalPrice * 0.30);
                        if ($memberPointCashbackLimit <= 0) {
                            $member_point_redeem_err = 'Cashback cannot exceed 30% of the order amount.';
                            $error = 1;
                        } else if ($memberPointCashbackPoints > $memberPointAvailablePoints) {
                            $member_point_redeem_err = 'The selected member does not have enough available points for cashback.';
                            $error = 1;
                        } else if ($memberPointCashbackPoints > $memberPointCashbackLimit) {
                            $member_point_redeem_err = 'Cashback points cannot exceed 30% of the order amount.';
                            $error = 1;
                        } else {
                            $memberPointExpectedFinalPrice = round($memberPointOriginalPrice - $memberPointCashbackPoints, 2);
                            if ($memberPointExpectedFinalPrice < 0) {
                                $member_point_redeem_err = 'Cashback cannot reduce the order amount below zero.';
                                $error = 1;
                            } else if (abs($memberPointExpectedFinalPrice - (float) $for_price) > 0.01) {
                                $member_point_redeem_err = 'Cashback deduction does not match the order price.';
                                $error = 1;
                            } else {
                                $memberPointRedeemPoints = $memberPointCashbackPoints;
                                $memberPointSelectedRewardLabel = fbOrderReqBuildCashbackLabel($memberPointCashbackPoints, $memberPointCashbackPoints);
                            }
                        }
                    }
                }
            } else {
                $memberPointEffectiveLabel = fbOrderReqBuildLinkedMemberLabel($memberPointPlatform, $memberPointCustomerLabel);
                $memberPointLockedTransaction = $memberPointTransactionId > 0 ? memberPointFetchTransactionById($connect, $memberPointTransactionId) : array();
                $memberPointLockedMetadata = !empty($memberPointLockedTransaction) ? memberPointDecodeTransactionMetadata($memberPointLockedTransaction) : array();
                if (trim((string) ($memberPointLockedMetadata['redeem_kind'] ?? '')) === 'cashback') {
                    $memberPointUseType = 'cashback';
                    $memberPointSelectedRewardLabel = fbOrderReqBuildCashbackLabel($memberPointRedeemPoints, isset($memberPointLockedMetadata['cashback_amount']) ? (float) $memberPointLockedMetadata['cashback_amount'] : $memberPointRedeemPoints);
                } else if ($memberPointRedeemId > 0) {
                    $memberPointUseType = 'gift';
                    $memberPointSelectedRewardLabel = fbOrderReqBuildRedeemLabel($connect, $memberPointRedeemId, $memberPointRedeemPoints);
                } else {
                    $memberPointUseType = 'none';
                }
            }

            if ($error) {
                break;
            }

            if ($action === 'addRecord' && $for_order_status === 'TP') {
                $forWarehouseStockValidation = shopeeOmsValidateWarehouseStockForOrder($connect, $finance_connect, array(
                    'package' => $for_pkg,
                    'stock_out_warehouse_id' => $for_stock_out_warehouse_id,
                ), array(
                    'platform' => 'facebook',
                ));
                if (empty($forWarehouseStockValidation['success'])) {
                    $stock_out_warehouse_err = isset($forWarehouseStockValidation['message']) ? (string) $forWarehouseStockValidation['message'] : 'Selected warehouse does not have enough stock.';
                    $error = 1;
                    break;
                }
            }

            if ($action == 'addRecord') {
                try {
                    if ($for_customer_id > 0 && !empty($selectedFbCustomerRow)) {
                        $selectedCustomerSyncResult = fbOrderReqSyncSelectedCustomerDeal($connect, $for_customer_id, array(
                            'name' => $for_name,
                            'fb_link' => $for_link,
                            'contact' => $for_ctc,
                            'sales_pic' => $for_pic,
                            'country' => $for_country,
                            'brand' => $for_brand,
                            'series' => $for_series,
                            'fb_page' => $for_fbpage,
                            'channel' => $for_channel,
                            'ship_rec_name' => $for_rec_name,
                            'ship_rec_add' => $for_rec_add,
                            'ship_rec_contact' => $for_rec_ctc,
                            'remark' => $for_remark,
                        ), $pageTitle);
                        if (empty($selectedCustomerSyncResult['success'])) {
                            throw new Exception(isset($selectedCustomerSyncResult['message']) ? (string) $selectedCustomerSyncResult['message'] : 'Unable to update the selected Facebook customer record.');
                        }
                    }

                    if ($for_name) {
                        array_push($newvalarr, $for_name);
                        array_push($datafield, 'name');
                    }
                    if ($for_link) {
                        array_push($newvalarr, $for_link);
                        array_push($datafield, 'facebook link');
                    }
                    if ($for_ctc) {
                        array_push($newvalarr, $for_ctc);
                        array_push($datafield, 'contact');
                    }
                    if ($for_pic) {
                        array_push($newvalarr, $for_pic);
                        array_push($datafield, 'pic');
                    }
                    if ($for_country) {
                        array_push($newvalarr, $for_country);
                        array_push($datafield, 'country');
                    }
                    if ($for_brand) {
                        array_push($newvalarr, $for_brand);
                        array_push($datafield, 'brand');
                    }
                    if ($for_series) {
                        array_push($newvalarr, $for_series);
                        array_push($datafield, 'series');
                    }
                    if ($for_pkg) {
                        array_push($newvalarr, $for_pkg);
                        array_push($datafield, 'package');
                    }
                    if ($for_fbpage) {
                        array_push($newvalarr, $for_fbpage);
                        array_push($datafield, 'fb page');
                    }
                    if ($for_channel) {
                        array_push($newvalarr, $for_channel);
                        array_push($datafield, 'channel');
                    }
                    if ($for_price) {
                        array_push($newvalarr, $for_price);
                        array_push($datafield, 'price');
                    }
                    if ($for_pay) {
                        array_push($newvalarr, $for_pay);
                        array_push($datafield, 'payment method');
                    }
                    if ($for_rec_name) {
                        array_push($newvalarr, $for_rec_name);
                        array_push($datafield, 'receiver name');
                    }
                    if ($for_rec_ctc) {
                        array_push($newvalarr, $for_rec_ctc);
                        array_push($datafield, 'receiver contact');
                    }
                    if ($for_rec_add) {
                        array_push($newvalarr, $for_rec_add);
                        array_push($datafield, 'receiver address');
                    }
                    if ($for_attach) {
                        array_push($newvalarr, $for_attach);
                        array_push($datafield, 'attachment');
                    }
                    if ($for_remark) {
                        array_push($newvalarr, $for_remark);
                        array_push($datafield, 'remark');
                    }
                    if ($for_order_status) {
                        array_push($newvalarr, $for_order_status);
                        array_push($datafield, 'order_status');
                    }
                    if ($for_stock_out_warehouse_id > 0) {
                        array_push($newvalarr, isset($forWarehouseOptionMap[$for_stock_out_warehouse_id]) ? $forWarehouseOptionMap[$for_stock_out_warehouse_id] : ('Warehouse #' . $for_stock_out_warehouse_id));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }
                    if ($for_airbill_no !== '') {
                        array_push($newvalarr, $for_airbill_no);
                        array_push($datafield, 'airbill_no');
                    }
                    if ($for_airbill_attachment !== '') {
                        array_push($newvalarr, $for_airbill_attachment);
                        array_push($datafield, 'airbill_attachment');
                    }
                    if ($memberPointPlatform !== '' && $memberPointCustomerId > 0) {
                        $memberPointLinkChanged = true;
                        $memberPointAuditNewLink = fbOrderReqBuildLinkedMemberLabel($memberPointPlatform, $memberPointCustomerLabel);
                        array_push($newvalarr, $memberPointAuditNewLink);
                        array_push($datafield, 'linked member');
                    }
                    if ($memberPointUseType !== 'none' && $memberPointSelectedRewardLabel !== '') {
                        array_push($newvalarr, $memberPointSelectedRewardLabel);
                        array_push($datafield, 'member point redeem');
                    }

                    $memberPointPlatformSql = $memberPointPlatform !== '' ? "'" . mysqli_real_escape_string($finance_connect, $memberPointPlatform) . "'" : "NULL";
                    $memberPointCustomerIdSql = $memberPointCustomerId > 0 ? $memberPointCustomerId : "NULL";
                    $memberPointCustomerLabelSql = $memberPointCustomerLabel !== '' ? "'" . mysqli_real_escape_string($finance_connect, $memberPointCustomerLabel) . "'" : "NULL";
                    $memberPointRedeemIdSql = ($memberPointUseType === 'gift' && $memberPointRedeemId > 0) ? $memberPointRedeemId : "NULL";
                    $memberPointRedeemPointsSql = 0;
                    $memberPointTransactionIdSql = "NULL";

                    $query = "INSERT INTO " . $tblName . " (name,fb_link,contact,sales_pic,country,brand,series,package,fb_page,channel,price,pay_method,ship_rec_name,ship_rec_add,ship_rec_contact,remark,attachment,order_status,stock_out_warehouse_id,airbill_no,airbill_attachment,member_point_platform,member_point_customer_id,member_point_customer_label,member_point_redeem_id,member_point_redeem_points,member_point_transaction_id,create_by,create_date,create_time) VALUES ('$for_name','$for_link','$for_ctc','$for_pic','$for_country','$for_brand','$for_series','$for_pkg','$for_fbpage','$for_channel','$for_price','$for_pay','$for_rec_name','$for_rec_add','$for_rec_ctc','$for_remark','$for_attach','$for_order_status_sql'," . ($for_stock_out_warehouse_id > 0 ? $for_stock_out_warehouse_id : 'NULL') . ",'$for_airbill_no_sql','" . mysqli_real_escape_string($finance_connect, $for_airbill_attachment) . "'," . $memberPointPlatformSql . "," . $memberPointCustomerIdSql . "," . $memberPointCustomerLabelSql . "," . $memberPointRedeemIdSql . "," . $memberPointRedeemPointsSql . "," . $memberPointTransactionIdSql . ",'" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($finance_connect, $query);
                    if (!$returnData) {
                        throw new Exception(mysqli_error($finance_connect));
                    }

                    $dataId = (int) mysqli_insert_id($finance_connect);
                    $shouldAttemptRedeemOnAdd = $dataId > 0 && !$memberPointLocked && (
                        ($memberPointUseType === 'gift' && $memberPointRedeemId > 0) ||
                        ($memberPointUseType === 'cashback' && $memberPointCashbackPoints > 0)
                    );
                    if ($shouldAttemptRedeemOnAdd) {
                        $memberPointRedeemResult = memberPointCreateRedeemTransaction($connect, $finance_connect, array(
                            'platform' => $memberPointPlatform,
                            'customer_id' => $memberPointCustomerId,
                            'customer_label' => $memberPointCustomerLabel,
                            'redeem_id' => $memberPointRedeemId,
                            'redeem_kind' => $memberPointUseType === 'cashback' ? 'cashback' : 'gift',
                            'cashback_points' => $memberPointUseType === 'cashback' ? $memberPointCashbackPoints : 0,
                            'original_order_amount' => $memberPointUseType === 'cashback' ? $memberPointOriginalPrice : 0,
                            'final_order_amount' => $memberPointUseType === 'cashback' ? (float) $for_price : 0,
                            'source_platform' => 'facebook',
                            'source_table' => $tblName,
                            'source_record_id' => $dataId,
                            'reference_label' => 'Facebook Order #' . $dataId,
                            'metadata' => array(
                                'order_name' => $for_name,
                                'fb_link' => $for_link,
                            ),
                        ));

                        if (empty($memberPointRedeemResult['success'])) {
                            fbOrderReqDeleteOrderById($finance_connect, $tblName, $dataId);
                            $memberPointAuditFailureMessage = isset($memberPointRedeemResult['message']) ? (string) $memberPointRedeemResult['message'] : 'Unable to deduct member points.';
                            fbOrderReqWriteMemberPointAudit(
                                $connect,
                                $pageTitle,
                                'add',
                                USER_NAME . ' failed to create a member point redeem transaction for Facebook order #' . $dataId . '. The order insert was rolled back.',
                                'member_point_redeem_failed',
                                '',
                                $memberPointAuditFailureMessage
                            );
                            unset($query, $returnData);
                            throw new Exception($memberPointAuditFailureMessage);
                        }

                        $memberPointCreatedTransactionId = (int) (isset($memberPointRedeemResult['transaction_id']) ? $memberPointRedeemResult['transaction_id'] : 0);
                        $memberPointRedeemPoints = (int) (isset($memberPointRedeemResult['required_points']) ? $memberPointRedeemResult['required_points'] : $memberPointRedeemPoints);
                        $memberPointRedeemCreated = $memberPointCreatedTransactionId > 0;
                        $memberPointTransactionId = $memberPointCreatedTransactionId;
                        $updateMemberPointRedeemIdSql = ($memberPointUseType === 'gift' && $memberPointRedeemId > 0) ? $memberPointRedeemId : 'NULL';
                        $updateMemberPointSql = "UPDATE `" . $tblName . "` SET `member_point_redeem_id` = " . $updateMemberPointRedeemIdSql . ", `member_point_redeem_points` = " . $memberPointRedeemPoints . ", `member_point_transaction_id` = " . $memberPointCreatedTransactionId . " WHERE `id` = " . $dataId . " LIMIT 1";
                        if (!mysqli_query($finance_connect, $updateMemberPointSql)) {
                            memberPointSoftDeleteTransactionById($connect, $memberPointCreatedTransactionId);
                            fbOrderReqDeleteOrderById($finance_connect, $tblName, $dataId);
                            $memberPointAuditFailureMessage = 'Unable to finalize member point redeem details on the saved Facebook order.';
                            fbOrderReqWriteMemberPointAudit(
                                $connect,
                                $pageTitle,
                                'add',
                                USER_NAME . ' failed to finalize the member point redeem update for Facebook order #' . $dataId . '. The order insert was rolled back.',
                                'member_point_redeem_finalize_failed',
                                '',
                                $memberPointAuditFailureMessage
                            );
                            unset($query, $returnData);
                            throw new Exception($memberPointAuditFailureMessage);
                        }
                    }

                    if ($dataId > 0) {
                        $memberPointPrivateEarnResult = memberPointUpsertFacebookPrivateEarnTransaction($connect, $finance_connect, array(
                            'order_id' => $dataId,
                            'platform' => $memberPointPlatform,
                            'customer_id' => $memberPointCustomerId,
                            'customer_label' => $memberPointCustomerLabel,
                            'order_amount' => (float) $for_price,
                            'order_date' => $cdate,
                            'reference_label' => 'Facebook Order #' . $dataId,
                            'order_name' => $for_name,
                            'fb_link' => $for_link,
                        ));

                        if (empty($memberPointPrivateEarnResult['success'])) {
                            if ($memberPointCreatedTransactionId > 0) {
                                memberPointSoftDeleteTransactionById($connect, $memberPointCreatedTransactionId);
                            }
                            fbOrderReqDeleteOrderById($finance_connect, $tblName, $dataId);
                            $memberPointAuditFailureMessage = isset($memberPointPrivateEarnResult['message']) ? (string) $memberPointPrivateEarnResult['message'] : 'Unable to finalize private member point earn.';
                            fbOrderReqWriteMemberPointAudit(
                                $connect,
                                $pageTitle,
                                'add',
                                USER_NAME . ' failed to create a private member point earn transaction for Facebook order #' . $dataId . '. The order insert was rolled back.',
                                'member_point_private_earn_failed',
                                '',
                                $memberPointAuditFailureMessage
                            );
                            unset($query, $returnData);
                            throw new Exception($memberPointAuditFailureMessage);
                        }

                        $memberPointPrivateEarnPoints = (int) (isset($memberPointPrivateEarnResult['points']) ? $memberPointPrivateEarnResult['points'] : 0);
                        $memberPointPrivateEarnTierLabel = trim((string) (($memberPointPrivateEarnResult['tier_meta']['label'] ?? '')));
                        $memberPointPrivateEarnCreated = $memberPointPrivateEarnPoints > 0 && empty($memberPointPrivateEarnResult['deleted_existing']);
                    }

                    if ($for_order_status === 'TP' && $dataId > 0) {
                        $freshOrderRow = shopeeOmsLoadOrder($finance_connect, $dataId, 'facebook');
                        $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $freshOrderRow, USER_ID, 'facebook');
                        if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                            $notifyResult = shopeeOmsSendWarehouseNotification($connect, $finance_connect, $tokenResult['token_row'], $tokenResult['notification'], $pageTitle);
                            if (!empty($notifyResult['sent']) && shopeeOmsTableHasColumn($finance_connect, dbFinance, $tblName, 'step_a_sent_at')) {
                                mysqli_query($finance_connect, "UPDATE `" . $tblName . "` SET `step_a_sent_at` = NOW() WHERE id = " . $dataId . " LIMIT 1");
                            }
                        }
                    }

                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $forPopupErrorMessage = trim((string) $errorMsg) !== '' ? trim((string) $errorMsg) : 'Unable to save Facebook order request.';
                    $act = "F";
                }
            } else {
                try {
                    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $result->fetch_assoc();
                    $previousRow = $row;
                    if ($for_customer_id > 0 && !empty($selectedFbCustomerRow)) {
                        $selectedCustomerSyncResult = fbOrderReqSyncSelectedCustomerDeal($connect, $for_customer_id, array(
                            'name' => $for_name,
                            'fb_link' => $for_link,
                            'contact' => $for_ctc,
                            'sales_pic' => $for_pic,
                            'country' => $for_country,
                            'brand' => $for_brand,
                            'series' => $for_series,
                            'fb_page' => $for_fbpage,
                            'channel' => $for_channel,
                            'ship_rec_name' => $for_rec_name,
                            'ship_rec_add' => $for_rec_add,
                            'ship_rec_contact' => $for_rec_ctc,
                            'remark' => $for_remark,
                        ), $pageTitle);
                        if (empty($selectedCustomerSyncResult['success'])) {
                            throw new Exception(isset($selectedCustomerSyncResult['message']) ? (string) $selectedCustomerSyncResult['message'] : 'Unable to update the selected Facebook customer record.');
                        }
                    }
                    $memberPointLocked = !empty($row['member_point_transaction_id']);
                    if ($memberPointLocked) {
                        $memberPointPlatform = memberPointNormalizePlatform(isset($row['member_point_platform']) ? $row['member_point_platform'] : '');
                        $memberPointCustomerId = (int) (isset($row['member_point_customer_id']) ? $row['member_point_customer_id'] : 0);
                        $memberPointCustomerLabel = trim((string) (isset($row['member_point_customer_label']) ? $row['member_point_customer_label'] : ''));
                        $memberPointRedeemId = (int) (isset($row['member_point_redeem_id']) ? $row['member_point_redeem_id'] : 0);
                        $memberPointRedeemPoints = (int) (isset($row['member_point_redeem_points']) ? $row['member_point_redeem_points'] : 0);
                        $memberPointTransactionId = (int) (isset($row['member_point_transaction_id']) ? $row['member_point_transaction_id'] : 0);
                        $memberPointSelectedRewardLabel = $memberPointRedeemId > 0 ? fbOrderReqBuildRedeemLabel($connect, $memberPointRedeemId, $memberPointRedeemPoints) : '';
                    }

                    if ($row['name'] != $for_name) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $for_name);
                        array_push($datafield, 'name');
                    }
                    if ($row['fb_link'] != $for_link) {
                        array_push($oldvalarr, $row['fb_link']);
                        array_push($chgvalarr, $for_link);
                        array_push($datafield, 'fb link');
                    }
                    if ($row['contact'] != $for_ctc) {
                        array_push($oldvalarr, $row['contact']);
                        array_push($chgvalarr, $for_ctc);
                        array_push($datafield, 'contact');
                    }
                    if ($row['sales_pic'] != $for_pic) {
                        array_push($oldvalarr, $row['sales_pic']);
                        array_push($chgvalarr, $for_pic);
                        array_push($datafield, 'pic');
                    }
                    if ($row['country'] != $for_country) {
                        array_push($oldvalarr, $row['country']);
                        array_push($chgvalarr, $for_country);
                        array_push($datafield, 'country');
                    }
                    if ($row['brand'] != $for_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $for_brand);
                        array_push($datafield, 'brand');
                    }
                    if ($row['series'] != $for_series) {
                        array_push($oldvalarr, $row['series']);
                        array_push($chgvalarr, $for_series);
                        array_push($datafield, 'series');
                    }
                    if ($row['package'] != $for_pkg) {
                        array_push($oldvalarr, $row['package']);
                        array_push($chgvalarr, $for_pkg);
                        array_push($datafield, 'package');
                    }
                    if ($row['fb_page'] != $for_fbpage) {
                        array_push($oldvalarr, $row['fb_page']);
                        array_push($chgvalarr, $for_fbpage);
                        array_push($datafield, 'fb_page');
                    }
                    if ($row['channel'] != $for_channel) {
                        array_push($oldvalarr, $row['channel']);
                        array_push($chgvalarr, $for_channel);
                        array_push($datafield, 'channel');
                    }
                    if ($row['price'] != $for_price) {
                        array_push($oldvalarr, $row['price']);
                        array_push($chgvalarr, $for_price);
                        array_push($datafield, 'price');
                    }
                    if ($row['pay_method'] != $for_pay) {
                        array_push($oldvalarr, $row['pay_method']);
                        array_push($chgvalarr, $for_pay);
                        array_push($datafield, 'payment method');
                    }
                    if ($row['ship_rec_name'] != $for_rec_name) {
                        array_push($oldvalarr, $row['ship_rec_name']);
                        array_push($chgvalarr, $for_rec_name);
                        array_push($datafield, 'shipping receiver name');
                    }
                    if ($row['ship_rec_contact'] != $for_rec_ctc) {
                        array_push($oldvalarr, $row['ship_rec_contact']);
                        array_push($chgvalarr, $for_rec_ctc);
                        array_push($datafield, 'shipping receiver contact');
                    }
                    if ($row['ship_rec_add'] != $for_rec_add) {
                        array_push($oldvalarr, $row['ship_rec_add']);
                        array_push($chgvalarr, $for_rec_add);
                        array_push($datafield, 'shipping receiver address');
                    }

                    $for_attach = isset($for_attach) ? $for_attach : '';
                    if (($row['attachment'] != $for_attach) && ($for_attach != '')) {
                        array_push($oldvalarr, $row['attachment']);
                        array_push($chgvalarr, $for_attach);
                        array_push($datafield, 'attachment');
                    }

                    if ($row['remark'] != $for_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $for_remark == '' ? 'Empty Value' : $for_remark);
                        array_push($datafield, 'remark');
                    }
                    if (shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '') !== $for_order_status) {
                        array_push($oldvalarr, isset($row['order_status']) && $row['order_status'] !== '' ? shopeeOmsGetStatusLabel($row['order_status']) : 'Empty Value');
                        array_push($chgvalarr, $for_order_status !== '' ? shopeeOmsGetStatusLabel($for_order_status) : 'Empty Value');
                        array_push($datafield, 'order_status');
                    }
                    if ((int) (isset($row['stock_out_warehouse_id']) ? $row['stock_out_warehouse_id'] : 0) !== (int) $for_stock_out_warehouse_id) {
                        array_push($oldvalarr, shopeeOmsResolveWarehouseNameById($connect, isset($row['stock_out_warehouse_id']) ? $row['stock_out_warehouse_id'] : 0, $forDefaultWarehouseId, $forWarehouseNameMap));
                        array_push($chgvalarr, shopeeOmsResolveWarehouseNameById($connect, $for_stock_out_warehouse_id, $forDefaultWarehouseId, $forWarehouseNameMap));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }
                    if ((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '') !== (string) $for_airbill_no) {
                        array_push($oldvalarr, isset($row['airbill_no']) && $row['airbill_no'] !== '' ? $row['airbill_no'] : 'Empty Value');
                        array_push($chgvalarr, $for_airbill_no !== '' ? $for_airbill_no : 'Empty Value');
                        array_push($datafield, 'airbill_no');
                    }
                    if ((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '') !== (string) $for_airbill_attachment) {
                        array_push($oldvalarr, isset($row['airbill_attachment']) && $row['airbill_attachment'] !== '' ? $row['airbill_attachment'] : 'Empty Value');
                        array_push($chgvalarr, $for_airbill_attachment !== '' ? $for_airbill_attachment : 'Empty Value');
                        array_push($datafield, 'airbill_attachment');
                    }

                    $memberPointAuditOldLink = fbOrderReqBuildLinkedMemberLabel(
                        isset($row['member_point_platform']) ? $row['member_point_platform'] : '',
                        isset($row['member_point_customer_label']) ? $row['member_point_customer_label'] : ''
                    );
                    $memberPointAuditNewLink = fbOrderReqBuildLinkedMemberLabel($memberPointPlatform, $memberPointCustomerLabel);
                    if ($memberPointAuditOldLink !== $memberPointAuditNewLink) {
                        $memberPointLinkChanged = true;
                        array_push($oldvalarr, $memberPointAuditOldLink !== '' ? $memberPointAuditOldLink : 'Empty Value');
                        array_push($chgvalarr, $memberPointAuditNewLink !== '' ? $memberPointAuditNewLink : 'Empty Value');
                        array_push($datafield, 'linked member');
                    }

                    $memberPointOldRedeemLabel = '';
                    $memberPointOldTransactionId = (int) (isset($row['member_point_transaction_id']) ? $row['member_point_transaction_id'] : 0);
                    if ($memberPointOldTransactionId > 0) {
                        $memberPointOldTransaction = memberPointFetchTransactionById($connect, $memberPointOldTransactionId);
                        $memberPointOldMetadata = !empty($memberPointOldTransaction) ? memberPointDecodeTransactionMetadata($memberPointOldTransaction) : array();
                        if (trim((string) ($memberPointOldMetadata['redeem_kind'] ?? '')) === 'cashback') {
                            $memberPointOldRedeemLabel = fbOrderReqBuildCashbackLabel(
                                (int) (isset($row['member_point_redeem_points']) ? $row['member_point_redeem_points'] : 0),
                                isset($memberPointOldMetadata['cashback_amount']) ? (float) $memberPointOldMetadata['cashback_amount'] : (int) (isset($row['member_point_redeem_points']) ? $row['member_point_redeem_points'] : 0)
                            );
                        }
                    }
                    if ($memberPointOldRedeemLabel === '' && (int) (isset($row['member_point_redeem_id']) ? $row['member_point_redeem_id'] : 0) > 0) {
                        $memberPointOldRedeemLabel = fbOrderReqBuildRedeemLabel($connect, (int) $row['member_point_redeem_id'], (int) (isset($row['member_point_redeem_points']) ? $row['member_point_redeem_points'] : 0));
                    }
                    $memberPointNewRedeemLabel = $memberPointUseType !== 'none'
                        ? ($memberPointSelectedRewardLabel !== '' ? $memberPointSelectedRewardLabel : ($memberPointRedeemId > 0 ? fbOrderReqBuildRedeemLabel($connect, $memberPointRedeemId, $memberPointRedeemPoints) : fbOrderReqBuildCashbackLabel($memberPointRedeemPoints, $memberPointRedeemPoints)))
                        : '';
                    if ($memberPointOldRedeemLabel !== $memberPointNewRedeemLabel) {
                        array_push($oldvalarr, $memberPointOldRedeemLabel !== '' ? $memberPointOldRedeemLabel : 'Empty Value');
                        array_push($chgvalarr, $memberPointNewRedeemLabel !== '' ? $memberPointNewRedeemLabel : 'Empty Value');
                        array_push($datafield, 'member point redeem');
                    }

                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);

                    $shouldAttemptRedeemOnEdit = !$memberPointLocked
                        && (int) (isset($row['member_point_transaction_id']) ? $row['member_point_transaction_id'] : 0) <= 0
                        && (
                            ($memberPointUseType === 'gift' && $memberPointRedeemId > 0) ||
                            ($memberPointUseType === 'cashback' && $memberPointCashbackPoints > 0)
                        );
                    $memberPointPlatformSql = $memberPointPlatform !== '' ? "'" . mysqli_real_escape_string($finance_connect, $memberPointPlatform) . "'" : "NULL";
                    $memberPointCustomerIdSql = $memberPointCustomerId > 0 ? $memberPointCustomerId : "NULL";
                    $memberPointCustomerLabelSql = $memberPointCustomerLabel !== '' ? "'" . mysqli_real_escape_string($finance_connect, $memberPointCustomerLabel) . "'" : "NULL";
                    $memberPointRedeemIdSql = ($memberPointUseType === 'gift' && $memberPointRedeemId > 0) ? $memberPointRedeemId : "NULL";
                    $memberPointRedeemPointsSql = $memberPointLocked ? $memberPointRedeemPoints : 0;
                    $memberPointTransactionIdSql = $memberPointLocked && $memberPointTransactionId > 0 ? $memberPointTransactionId : "NULL";

                    if (count($oldvalarr) > 0 || $shouldAttemptRedeemOnEdit) {
                        $query = "UPDATE " . $tblName . " SET name = '$for_name', fb_link = '$for_link', contact = '$for_ctc', sales_pic = '$for_pic', country = '$for_country', brand = '$for_brand', series = '$for_series', package = '$for_pkg', fb_page = '$for_fbpage', channel = '$for_channel', price = '$for_price', pay_method = '$for_pay', ship_rec_name = '$for_rec_name', ship_rec_add = '$for_rec_add', ship_rec_contact = '$for_rec_ctc', remark ='$for_remark', attachment ='$for_attach', order_status = '$for_order_status_sql', stock_out_warehouse_id = " . ($for_stock_out_warehouse_id > 0 ? $for_stock_out_warehouse_id : 'NULL') . ", airbill_no = '$for_airbill_no_sql', airbill_attachment = '" . mysqli_real_escape_string($finance_connect, $for_airbill_attachment) . "', member_point_platform = " . $memberPointPlatformSql . ", member_point_customer_id = " . $memberPointCustomerIdSql . ", member_point_customer_label = " . $memberPointCustomerLabelSql . ", member_point_redeem_id = " . $memberPointRedeemIdSql . ", member_point_redeem_points = " . $memberPointRedeemPointsSql . ", member_point_transaction_id = " . $memberPointTransactionIdSql . ", update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataId'";
                        $returnData = mysqli_query($finance_connect, $query);
                        if (!$returnData) {
                            throw new Exception(mysqli_error($finance_connect));
                        }

                        if ($shouldAttemptRedeemOnEdit) {
                            $memberPointRedeemResult = memberPointCreateRedeemTransaction($connect, $finance_connect, array(
                                'platform' => $memberPointPlatform,
                                'customer_id' => $memberPointCustomerId,
                                'customer_label' => $memberPointCustomerLabel,
                                'redeem_id' => $memberPointRedeemId,
                                'redeem_kind' => $memberPointUseType === 'cashback' ? 'cashback' : 'gift',
                                'cashback_points' => $memberPointUseType === 'cashback' ? $memberPointCashbackPoints : 0,
                                'original_order_amount' => $memberPointUseType === 'cashback' ? $memberPointOriginalPrice : 0,
                                'final_order_amount' => $memberPointUseType === 'cashback' ? (float) $for_price : 0,
                                'source_platform' => 'facebook',
                                'source_table' => $tblName,
                                'source_record_id' => (int) $dataId,
                                'reference_label' => 'Facebook Order #' . (int) $dataId,
                                'metadata' => array(
                                    'order_name' => $for_name,
                                    'fb_link' => $for_link,
                                ),
                            ));

                            if (empty($memberPointRedeemResult['success'])) {
                                fbOrderReqRestoreOrderRow($finance_connect, $tblName, (int) $dataId, $previousRow);
                                $memberPointAuditFailureMessage = isset($memberPointRedeemResult['message']) ? (string) $memberPointRedeemResult['message'] : 'Unable to deduct member points.';
                                fbOrderReqWriteMemberPointAudit(
                                    $connect,
                                    $pageTitle,
                                    'edit',
                                    USER_NAME . ' failed to create a member point redeem transaction for Facebook order #' . (int) $dataId . '. The previous order data was restored.',
                                    'member_point_redeem_failed',
                                    '',
                                    $memberPointAuditFailureMessage
                                );
                                unset($query, $returnData);
                                throw new Exception($memberPointAuditFailureMessage);
                            }

                            $memberPointCreatedTransactionId = (int) (isset($memberPointRedeemResult['transaction_id']) ? $memberPointRedeemResult['transaction_id'] : 0);
                            $memberPointRedeemPoints = (int) (isset($memberPointRedeemResult['required_points']) ? $memberPointRedeemResult['required_points'] : $memberPointRedeemPoints);
                            $memberPointRedeemCreated = $memberPointCreatedTransactionId > 0;
                            $memberPointTransactionId = $memberPointCreatedTransactionId;

                            $finalizeMemberPointRedeemIdSql = ($memberPointUseType === 'gift' && $memberPointRedeemId > 0) ? $memberPointRedeemId : 'NULL';
                            $finalizeRedeemSql = "UPDATE `" . $tblName . "` SET `member_point_redeem_id` = " . $finalizeMemberPointRedeemIdSql . ", `member_point_redeem_points` = " . $memberPointRedeemPoints . ", `member_point_transaction_id` = " . $memberPointCreatedTransactionId . " WHERE `id` = " . (int) $dataId . " LIMIT 1";
                            if (!mysqli_query($finance_connect, $finalizeRedeemSql)) {
                                memberPointSoftDeleteTransactionById($connect, $memberPointCreatedTransactionId);
                                fbOrderReqRestoreOrderRow($finance_connect, $tblName, (int) $dataId, $previousRow);
                                $memberPointAuditFailureMessage = 'Unable to finalize member point redeem details on the edited Facebook order.';
                                fbOrderReqWriteMemberPointAudit(
                                    $connect,
                                    $pageTitle,
                                    'edit',
                                    USER_NAME . ' failed to finalize the member point redeem update for Facebook order #' . (int) $dataId . '. The previous order data was restored.',
                                    'member_point_redeem_finalize_failed',
                                    '',
                                    $memberPointAuditFailureMessage
                                );
                                unset($query, $returnData);
                                throw new Exception($memberPointAuditFailureMessage);
                            }
                        }

                        $memberPointPrivateEarnResult = memberPointUpsertFacebookPrivateEarnTransaction($connect, $finance_connect, array(
                            'order_id' => (int) $dataId,
                            'platform' => $memberPointPlatform,
                            'customer_id' => $memberPointCustomerId,
                            'customer_label' => $memberPointCustomerLabel,
                            'order_amount' => (float) $for_price,
                            'order_date' => isset($previousRow['create_date']) ? (string) $previousRow['create_date'] : $cdate,
                            'reference_label' => 'Facebook Order #' . (int) $dataId,
                            'order_name' => $for_name,
                            'fb_link' => $for_link,
                        ));

                        if (empty($memberPointPrivateEarnResult['success'])) {
                            if ($memberPointCreatedTransactionId > 0) {
                                memberPointSoftDeleteTransactionById($connect, $memberPointCreatedTransactionId);
                            }
                            fbOrderReqRestoreOrderRow($finance_connect, $tblName, (int) $dataId, $previousRow);
                            $memberPointAuditFailureMessage = isset($memberPointPrivateEarnResult['message']) ? (string) $memberPointPrivateEarnResult['message'] : 'Unable to finalize private member point earn.';
                            fbOrderReqWriteMemberPointAudit(
                                $connect,
                                $pageTitle,
                                'edit',
                                USER_NAME . ' failed to create a private member point earn transaction for Facebook order #' . (int) $dataId . '. The previous order data was restored.',
                                'member_point_private_earn_failed',
                                '',
                                $memberPointAuditFailureMessage
                            );
                            unset($query, $returnData);
                            throw new Exception($memberPointAuditFailureMessage);
                        }

                        $memberPointPrivateEarnPoints = (int) (isset($memberPointPrivateEarnResult['points']) ? $memberPointPrivateEarnResult['points'] : 0);
                        $memberPointPrivateEarnTierLabel = trim((string) (($memberPointPrivateEarnResult['tier_meta']['label'] ?? '')));
                        $memberPointPrivateEarnCreated = $memberPointPrivateEarnPoints > 0;

                        if (isset($previousRow['attachment']) && $previousRow['attachment'] != '' && $previousRow['attachment'] != $for_attach) {
                            $old_file_path = $img_path . $previousRow['attachment'];
                            if (file_exists($old_file_path)) {
                                unlink($old_file_path);
                            }
                        }

                        $_SESSION['tempValConfirmBox'] = true;
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $forPopupErrorMessage = trim((string) $errorMsg) !== '' ? trim((string) $errorMsg) : 'Unable to save Facebook order request.';
                    $act = "F";
                }
            }

            if ($action === 'updRecord' && $forShouldSaveBeforeStatusUpdate && (($act === 'NC') || !empty($returnData))) {
                $forTriggerStatusTransitionAfterSave = true;
            }

            // audit log
            if (isset($query)) {

                $log = [
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }

            if (!empty($returnData)) {
                if ($memberPointLinkChanged) {
                    fbOrderReqWriteMemberPointAudit(
                        $connect,
                        $pageTitle,
                        strtolower((string) $pageAction),
                        USER_NAME . ' saved member point link details for Facebook order #' . (int) $dataId . '.',
                        'member_point_link',
                        $memberPointAuditOldLink,
                        $memberPointAuditNewLink
                    );
                }

                if ($memberPointRedeemCreated && $memberPointTransactionId > 0) {
                    $memberPointRedeemAuditLabel = $memberPointSelectedRewardLabel !== '' ? $memberPointSelectedRewardLabel : fbOrderReqBuildRedeemLabel($connect, $memberPointRedeemId, $memberPointRedeemPoints);
                    fbOrderReqWriteMemberPointAudit(
                        $connect,
                        $pageTitle,
                        strtolower((string) $pageAction),
                        USER_NAME . ' created member point redeem transaction #' . $memberPointTransactionId . ' for Facebook order #' . (int) $dataId . '.',
                        'member_point_redeem_transaction',
                        '',
                        $memberPointRedeemAuditLabel . ' (' . $memberPointRedeemPoints . ' points)'
                    );
                }

                if ($memberPointPrivateEarnCreated && $memberPointPlatform !== '' && $memberPointCustomerId > 0) {
                    $memberPointPrivateEarnAuditText = $memberPointPrivateEarnPoints . ' private points';
                    if ($memberPointPrivateEarnTierLabel !== '') {
                        $memberPointPrivateEarnAuditText .= ' (' . $memberPointPrivateEarnTierLabel . ')';
                    }

                    fbOrderReqWriteMemberPointAudit(
                        $connect,
                        $pageTitle,
                        strtolower((string) $pageAction),
                        USER_NAME . ' synced private member point earn for Facebook order #' . (int) $dataId . '.',
                        'member_point_private_earn',
                        '',
                        $memberPointPrivateEarnAuditText
                    );
                }
            }

            if ($action === 'updRecord' && $forShouldSaveBeforeStatusUpdate) {
                if ($forTriggerStatusTransitionAfterSave) {
                    unset($_SESSION['tempValConfirmBox']);
                    $forTransitionResult = $forHandleStatusTransition($pendingStatusUpdate);
                    if (is_array($forTransitionResult) && empty($forTransitionResult['success'])) {
                        $transitionErrorState = shopeeOmsResolveStatusTransitionErrorState(
                            $pendingStatusUpdate,
                            isset($forTransitionResult['message']) ? $forTransitionResult['message'] : '',
                            'Unable to update order status.'
                        );
                        if ($act === 'NC') {
                            $act = 'E';
                        }
                        if ($transitionErrorState['stock_out_warehouse_err'] !== '') {
                            $stock_out_warehouse_err = $transitionErrorState['stock_out_warehouse_err'];
                        }
                        $forPopupErrorMessage = $transitionErrorState['popup_error_message'];
                        break;
                    }
                }

                $forSaveErrorMessage = trim((string) $errorMsg) !== '' ? trim((string) $errorMsg) : 'Unable to save edited order details.';
                echo '<script>alert(' . json_encode($forSaveErrorMessage) . ');</script>';
                exit;
            }

            break;
    }
}


if (post('act') == 'D') {
    $id = post('id');
    if ($id) {
        try {
            $result = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $finance_connect);
            if (!$result || $result->num_rows === 0) {
                renderNotificationScript('Order record was not found.', 'error', $redirectPage, 1200, true);
                exit;
            }

            $row = $result->fetch_assoc();
            $dataId = (int) $row['id'];
            $deleteLabel = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($deleteLabel === '') {
                $deleteLabel = 'Order #' . $dataId;
            }

            $deleteApprovalResult = orderDeleteApprovalRequestDelete($connect, $orderDeleteApprovalModuleKey, $dataId, $deleteLabel, $pageTitle);
            if (!empty($deleteApprovalResult['direct_delete'])) {
                $deleteResult = $forExecuteDeleteOrder(array(
                    'source_order_id' => $dataId,
                    'source_order_label' => $deleteLabel,
                ));
                renderNotificationScript(
                    $deleteResult['message'],
                    !empty($deleteResult['success']) ? 'success' : 'error',
                    $redirectPage,
                    1200,
                    true
                );
                exit;
            }

            renderNotificationScript(
                $deleteApprovalResult['message'],
                isset($deleteApprovalResult['notification_type']) ? $deleteApprovalResult['notification_type'] : (!empty($deleteApprovalResult['success']) ? 'success' : 'error'),
                $redirectPage,
                1200,
                true
            );
            exit;
        } catch (Exception $e) {
            renderNotificationScript($e->getMessage(), 'error', $redirectPage, 1200, true);
            exit;
        }
    }
}

//view
if (($dataId) && !($act) && (USER_ID != '') && empty($_SESSION['viewChk']) && empty($_SESSION['delChk'])) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

$urbanismBadgeSeedName = '';
$urbanismBadgeSeedId = '';
$urbanismFbLink = '';

if (isset($row['name']) && trim((string) $row['name']) !== '') {
    $urbanismBadgeSeedName = trim((string) $row['name']);
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('for_name') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('for_name'));
}

if (isset($row['fb_link']) && trim((string) $row['fb_link']) !== '') {
    $urbanismFbLink = trim((string) $row['fb_link']);
}
if ($urbanismFbLink === '' && postSpaceFilter('for_link') !== '') {
    $urbanismFbLink = trim((string) postSpaceFilter('for_link'));
}

if ($urbanismBadgeSeedName !== '' && $urbanismFbLink !== '') {
    $safeFbName = mysqli_real_escape_string($connect, $urbanismBadgeSeedName);
    $safeFbLink = mysqli_real_escape_string($connect, $urbanismFbLink);
    $dealRst = getData('id', "name='" . $safeFbName . "' AND fb_link='" . $safeFbLink . "'", 'LIMIT 1', FB_CUST_DEALS, $connect);
    if ($dealRst && $dealRst->num_rows > 0) {
        $dealRow = $dealRst->fetch_assoc();
        $urbanismBadgeSeedId = (string) ((int) $dealRow['id']);
    }
}

$urbanismBadgeAction = getUrbanismMemberActionData(
    $connect,
    '',
    $urbanismBadgeSeedName,
    $redirectPage,
    $pageTitle
);

$memberPointRenderPlatform = isset($memberPointPlatform) ? memberPointNormalizePlatform($memberPointPlatform) : memberPointNormalizePlatform(isset($row['member_point_platform']) ? $row['member_point_platform'] : '');
$memberPointRenderCustomerId = isset($memberPointCustomerId) ? (int) $memberPointCustomerId : (int) (isset($row['member_point_customer_id']) ? $row['member_point_customer_id'] : 0);
$memberPointRenderCustomerLabel = isset($memberPointCustomerLabel) ? trim((string) $memberPointCustomerLabel) : trim((string) (isset($row['member_point_customer_label']) ? $row['member_point_customer_label'] : ''));
$memberPointRenderRedeemId = isset($memberPointRedeemId) ? (int) $memberPointRedeemId : (int) (isset($row['member_point_redeem_id']) ? $row['member_point_redeem_id'] : 0);
$memberPointRenderRedeemPoints = isset($memberPointRedeemPoints) ? (int) $memberPointRedeemPoints : (int) (isset($row['member_point_redeem_points']) ? $row['member_point_redeem_points'] : 0);
$memberPointRenderTransactionId = (int) (isset($row['member_point_transaction_id']) ? $row['member_point_transaction_id'] : 0);
$memberPointRenderLocked = ($act === 'E' && $memberPointRenderTransactionId > 0);
$memberPointRenderDisabled = $act === '' || $memberPointRenderLocked;
$memberPointRenderTransactionRow = $memberPointRenderTransactionId > 0 ? memberPointFetchTransactionById($connect, $memberPointRenderTransactionId) : array();
$memberPointRenderTransactionMetadata = !empty($memberPointRenderTransactionRow) ? memberPointDecodeTransactionMetadata($memberPointRenderTransactionRow) : array();
$memberPointRenderUseType = isset($memberPointUseType) && in_array($memberPointUseType, array('none', 'gift', 'cashback'), true) ? $memberPointUseType : 'none';
$memberPointRenderCashbackPoints = isset($memberPointCashbackPoints) ? max(0, (int) $memberPointCashbackPoints) : 0;
$memberPointRenderOriginalPrice = isset($memberPointOriginalPrice) ? max(0, (float) $memberPointOriginalPrice) : 0;
$memberPointRenderLookup = array(
    'success' => false,
    'customer_label' => $memberPointRenderCustomerLabel,
    'available_points' => 0,
    'rewards' => array(),
    'message' => '',
    'locked' => $memberPointRenderLocked,
);

if ($memberPointRenderPlatform !== '' && $memberPointRenderCustomerId > 0) {
    if (isset($memberPointLookup['success']) && $memberPointRenderPlatform === (isset($memberPointPlatform) ? $memberPointPlatform : '') && $memberPointRenderCustomerId === (int) (isset($memberPointCustomerId) ? $memberPointCustomerId : 0)) {
        $memberPointRenderLookup = $memberPointLookup;
    } else {
        $memberPointRenderLookup = memberPointBuildLookupPayload($connect, $finance_connect, $memberPointRenderPlatform, $memberPointRenderCustomerId, array(
            'allowed_platforms' => array('shopee', 'lazada'),
            'locked' => $memberPointRenderLocked,
            'sync_ledger' => true,
        ));
    }
}

if (!empty($memberPointRenderLookup['success']) && trim((string) $memberPointRenderLookup['customer_label']) !== '') {
    $memberPointRenderCustomerLabel = trim((string) $memberPointRenderLookup['customer_label']);
}

$memberPointRenderRewards = !empty($memberPointRenderLookup['rewards']) && is_array($memberPointRenderLookup['rewards']) ? $memberPointRenderLookup['rewards'] : array();
if ($memberPointRenderLocked && trim((string) ($memberPointRenderTransactionMetadata['redeem_kind'] ?? '')) === 'cashback') {
    $memberPointRenderUseType = 'cashback';
    $memberPointRenderCashbackPoints = $memberPointRenderRedeemPoints;
    $memberPointRenderOriginalPrice = isset($memberPointRenderTransactionMetadata['original_order_amount'])
        ? max(0, (float) $memberPointRenderTransactionMetadata['original_order_amount'])
        : max(0, (float) (isset($row['price']) ? $row['price'] : 0) + $memberPointRenderCashbackPoints);
}
if ($memberPointRenderUseType === 'cashback' && $memberPointRenderCashbackPoints <= 0) {
    $memberPointRenderCashbackPoints = $memberPointRenderRedeemPoints > 0 ? $memberPointRenderRedeemPoints : 0;
}
if ($memberPointRenderUseType === 'cashback' && $memberPointRenderOriginalPrice <= 0 && isset($row['price'])) {
    $memberPointRenderOriginalPrice = max(0, (float) $row['price']);
}
$memberPointRenderSelectedRewardLabel = '';
if ($memberPointRenderUseType === 'cashback' && $memberPointRenderCashbackPoints > 0) {
    $memberPointRenderSelectedRewardLabel = fbOrderReqBuildCashbackLabel(
        $memberPointRenderCashbackPoints,
        isset($memberPointRenderTransactionMetadata['cashback_amount']) ? (float) $memberPointRenderTransactionMetadata['cashback_amount'] : $memberPointRenderCashbackPoints
    );
} else if ($memberPointRenderRedeemId > 0) {
    $memberPointRenderUseType = $memberPointRenderUseType === 'none' ? 'gift' : $memberPointRenderUseType;
    $memberPointRenderSelectedRewardLabel = fbOrderReqBuildRedeemLabel($connect, $memberPointRenderRedeemId, $memberPointRenderRedeemPoints);
}
$memberPointRenderRewardIds = array();
foreach ($memberPointRenderRewards as $memberPointRenderReward) {
    $memberPointRenderRewardIds[] = (int) (isset($memberPointRenderReward['id']) ? $memberPointRenderReward['id'] : 0);
}
if ($memberPointRenderUseType === 'gift' && $memberPointRenderRedeemId > 0 && !in_array($memberPointRenderRedeemId, $memberPointRenderRewardIds, true)) {
    $memberPointRenderRewards[] = array(
        'id' => $memberPointRenderRedeemId,
        'required_points' => $memberPointRenderRedeemPoints,
        'gift_label' => $memberPointRenderSelectedRewardLabel,
        'strategy_text' => '',
        'display_text' => $memberPointRenderSelectedRewardLabel,
    );
}

$memberPointRenderLinkedMember = fbOrderReqBuildLinkedMemberLabel($memberPointRenderPlatform, $memberPointRenderCustomerLabel);
$memberPointRenderAvailablePoints = (int) (isset($memberPointRenderLookup['available_points']) ? $memberPointRenderLookup['available_points'] : 0);
$memberPointRenderSummaryRewards = array();
foreach ($memberPointRenderRewards as $memberPointRewardRow) {
    $memberPointRenderSummaryRewards[] = isset($memberPointRewardRow['display_text']) ? (string) $memberPointRewardRow['display_text'] : '';
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <script src="<?= $SITEURL ?>/finance/header/js/pdf.min.js"></script>
    <script src="<?= $SITEURL ?>/js/pdf_airbill_parser.js"></script>
    <script src="<?= $SITEURL ?>/finance/header/js/tesseract.min.js"></script>
    <style>
        .shopee-airbill-toggle-col {
            display: flex;
            flex-direction: column;
        }

        .shopee-airbill-toggle-field {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            margin-top: 0;
            padding: 0;
        }

        .shopee-airbill-toggle-label {
            margin: 0;
        }

        @media (max-width: 767px) {
            .shopee-airbill-toggle-col {
                margin-top: 0;
            }
        }

        .shopee-airbill-toggle {
            position: relative;
            width: 54px;
            height: 28px;
            display: inline-flex;
            align-items: center;
        }

        .shopee-airbill-toggle input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .shopee-airbill-toggle-slider {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
            border-radius: 999px;
            background: #31343a;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle-slider::before {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle-slider::after {
            content: "\f00d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #ffffff;
            font-size: 0.62rem;
            position: absolute;
            right: 10px;
            top: 8px;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider {
            background: #6f922f;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider::before {
            left: 29px;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider::after {
            content: "\f00c";
            right: 32px;
        }

        .shopee-airbill-extract-status {
            display: block;
            min-height: 18px;
            margin-top: 6px;
            color: #198754;
        }

        .shopee-airbill-extract-status.is-error {
            color: #dc3545;
        }

        .shopee-airbill-preview-media {
            width: 100%;
            max-width: 520px;
        }

        .shopee-airbill-preview-media img,
        .shopee-airbill-preview-media iframe {
            width: 100%;
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            background: #fff;
        }

        .shopee-airbill-preview-media img {
            height: auto;
            display: block;
        }

        .shopee-airbill-preview-media iframe {
            min-height: 520px;
        }

        .fb-order-req-container {
            max-width: 1280px;
            margin-left: auto;
            margin-right: auto;
        }

        .fb-order-req-form-wrap {
            width: 100%;
            max-width: 1100px;
        }

        .fb-order-req-form-wrap .form-control,
        .fb-order-req-form-wrap .form-select {
            width: 100%;
            min-width: 0;
        }

        .fb-order-req-form-wrap .row {
            --bs-gutter-x: 1.5rem;
        }

        .fb-order-req-form-wrap .autocomplete {
            position: relative;
        }

        .member-point-summary-card {
            border: 1px solid #d9e2ef;
            border-radius: 12px;
            background: #f8fbff;
            padding: 16px 18px;
            min-height: 100%;
        }

        .member-point-summary-card h6 {
            margin-bottom: 8px;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .member-point-summary-value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #274c7d;
        }

        .member-point-reward-list {
            margin: 0;
            padding-left: 18px;
        }

        .member-point-reward-list li {
            margin-bottom: 6px;
        }

        .member-point-platform-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-right: 8px;
        }

        .member-point-platform-badge[data-platform="shopee"] {
            background: #fff0ea;
            color: #d04d1c;
        }

        .member-point-platform-badge[data-platform="lazada"] {
            background: #eef0ff;
            color: #4548b6;
        }

        .member-point-cashback-summary {
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            background: #f8fbff;
            padding: 12px 14px;
        }

        .member-point-cashback-summary-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
        }

        .member-point-cashback-summary-line:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 767px) {
            .fb-order-req-form-wrap {
                max-width: 100%;
            }
        }
    </style>

    <?php
    $forCustomerIdValue = 0;
    if (isset($for_customer_id) && (int) $for_customer_id > 0) {
        $forCustomerIdValue = (int) $for_customer_id;
    } elseif (isset($row['name']) || isset($row['fb_link'])) {
        $forCustomerIdValue = fbOrderReqFindCustomerDealId(
            $connect,
            isset($row['name']) ? $row['name'] : '',
            isset($row['fb_link']) ? $row['fb_link'] : ''
        );
    }

    $forNameDisplayValue = isset($for_name) && trim((string) $for_name) !== ''
        ? trim((string) $for_name)
        : (isset($row['name']) ? trim((string) $row['name']) : '');
    $forLinkDisplayValue = isset($for_link) && trim((string) $for_link) !== ''
        ? trim((string) $for_link)
        : (isset($row['fb_link']) ? trim((string) $row['fb_link']) : '');
    $forContactDisplayValue = isset($for_ctc) && trim((string) $for_ctc) !== ''
        ? trim((string) $for_ctc)
        : (isset($row['contact']) ? trim((string) $row['contact']) : '');
    ?>

</head>

<body>
    
    <!-- <div class="page-load-cover"> -->
    <div class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= htmlspecialchars((string) $back_redirect_page, ENT_QUOTES, 'UTF-8') ?>">
                <?= $pageTitle ?>
            </a> <i class="fa-solid fa-chevron-right fa-xs"></i>
            <?php
            echo displayPageAction($act, $pageTitle);
            ?>
        </p>

    </div>

    <div id="formContainer" class="container-fluid px-3 px-md-4 fb-order-req-container">
        <div class="fb-order-req-form-wrap mx-auto">
            <form id="FORForm" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars((string) $back_redirect_page, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group mb-5">
                    <div class="order-title-row">
                        <h2 class="mb-0"><?php echo displayPageAction($act, $pageTitle); ?></h2>
                    </div>
                    <div class="order-badge-row text-end mt-2">
                        <a
                            class="btn btn-sm <?= $urbanismBadgeAction['is_member'] ? 'btn-success' : 'btn-outline-secondary' ?> <?= $urbanismBadgeAction['disabled'] ? 'disabled' : '' ?>"
                            href="<?= htmlspecialchars($urbanismBadgeAction['url'], ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars($urbanismBadgeAction['title'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $urbanismBadgeAction['disabled'] ? 'onclick="return false;" aria-disabled="true"' : '' ?>><i class="fa-solid fa-id-badge"></i></a>
                    </div>
                </div>

                <div id="err_msg" class="mb-3">
                    <span class="mt-n2" style="font-size: 21px;">
                        <?php if (isset($err1))
                            echo $err1; ?>
                    </span>
                </div>

                <?php echo $orderDeleteApprovalPanelHtml; ?>

                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="for_name_lbl" for="for_name">Name<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="text" name="for_name" id="for_name" value="<?= htmlspecialchars($forNameDisplayValue, ENT_QUOTES, 'UTF-8') ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <input type="hidden" name="for_customer_id" id="for_customer_id" value="<?= (int) $forCustomerIdValue ?>">
                            <small class="text-muted d-block mt-1">Select an existing Facebook customer to auto-fill the customer details.</small>
                            <?php if (isset($name_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $name_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="for_link_lbl" for="for_link">Facebook Link<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="text" name="for_link" id="for_link" value="<?= htmlspecialchars($forLinkDisplayValue, ENT_QUOTES, 'UTF-8') ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($link_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $link_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="for_contact_lbl" for="for_contact">Contact<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="number" step="0.01" name="for_contact" id="for_contact" value="<?= htmlspecialchars($forContactDisplayValue, ENT_QUOTES, 'UTF-8') ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($contact_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $contact_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>


                    </div>

                </div>
                <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                    <legend class="float-none w-auto p-2">Order Request Details</legend>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_pic_lbl" for="for_pic">Sales Person In
                                    Charge<span class="requireRed">*</span></label>
                                <?php
                                   if(($act == 'E' || $act == '')){
                                unset($echoVal);

                                if (isset($row['sales_pic']))
                                    $echoVal = $row['sales_pic'];

                                if (isset($echoVal)) {
                                    $user_rst = getData('name', "id = '$echoVal'", '', USR_USER, $connect);
                                    if (!$user_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $user_row = ($user_rst && $user_rst->num_rows > 0) ? $user_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_pic" id="for_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? (isset($user_row['name']) ? $user_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_pic_hidden" id="for_pic_hidden"
                                    value="<?php echo (isset($row['sales_pic'])) ? $row['sales_pic'] : ''; ?>">
                                <?php } ?>
                                <?php
                                if(($act == 'I')){
                           
                                    $loggedInUserId = USER_ID; // Assuming USER_ID contains the ID of the logged-in user
                                    $defaultUser = '';
                                
                                    // Retrieve details of the logged-in user
                                    $user_rst = getData('name', "id = '$loggedInUserId'", '', USR_USER, $connect);
                                    if ($user_rst && $user_rst->num_rows > 0) {
                                        $user_row = ($user_rst && $user_rst->num_rows > 0) ? $user_rst->fetch_assoc() : array();
                                        $defaultUser = $user_row['name'];
                                    }
                                    
                                ?>
                                <input class="form-control" type="text" name="for_pic" id="for_pic" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo $defaultUser ?>">
                                <input type="hidden" name="for_pic_hidden" id="for_pic_hidden"
                                    value="<?php echo $loggedInUserId ?>">
                                <?php }?>
                                <?php if (isset($pic_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pic_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_country_lbl" for="for_country">Country<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['country']))
                                    $echoVal = $row['country'];

                                if (isset($echoVal)) {
                                    $country_rst = getData('nicename', "id = '$echoVal'", '', COUNTRIES, $connect);
                                    if (!$country_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $country_row = ($country_rst && $country_rst->num_rows > 0) ? $country_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_country" id="for_country" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($country_row['nicename']) ? $country_row['nicename'] : '') : '' ?>">
                                <input type="hidden" name="for_country_hidden" id="for_country_hidden"
                                    value="<?php echo (isset($row['country'])) ? $row['country'] : ''; ?>">


                                <?php if (isset($country_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $country_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>

                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_brand_lbl" for="for_brand">Brand<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['brand']))
                                    $echoVal = $row['brand'];

                                if (isset($echoVal)) {
                                    $brand_rst = getData('name', "id = '$echoVal'", '', BRAND, $connect);
                                    if (!$brand_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $brand_row = ($brand_rst && $brand_rst->num_rows > 0) ? $brand_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_brand" id="for_brand" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($brand_row['name']) ? $brand_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_brand_hidden" id="for_brand_hidden"
                                    value="<?php echo (isset($row['brand'])) ? $row['brand'] : ''; ?>">


                                <?php if (isset($brand_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $brand_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_series_lbl" for="for_series">Series<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['series']))
                                    $echoVal = $row['series'];

                                if (isset($echoVal)) {
                                    $series_rst = getData('name', "id = '$echoVal'", '', BRD_SERIES, $connect);
                                    if (!$series_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $series_row = ($series_rst && $series_rst->num_rows > 0) ? $series_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_series" id="for_series" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($series_row['name']) ? $series_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_series_hidden" id="for_series_hidden"
                                    value="<?php echo (isset($row['series'])) ? $row['series'] : ''; ?>">


                                <?php if (isset($series_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $series_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_pkg_lbl" for="for_pkg">Package<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['package']))
                                    $echoVal = $row['package'];

                                if (isset($echoVal)) {
                                    $pkg_rst = getData('name', "id = '$echoVal'", '', PKG, $connect);
                                    if (!$pkg_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $pkg_row = ($pkg_rst && $pkg_rst->num_rows > 0) ? $pkg_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_pkg" id="for_pkg" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? (isset($pkg_row['name']) ? $pkg_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_pkg_hidden" id="for_pkg_hidden"
                                    value="<?php echo (isset($row['package'])) ? $row['package'] : ''; ?>">


                                <?php if (isset($pkg_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pkg_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_fb_page_lbl" for="for_fbpage">Facebook
                                    Page<span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['fb_page']))
                                    $echoVal = $row['fb_page'];

                                if (isset($echoVal)) {
                                    $fbpage_rst = getData('name', "id = '$echoVal'", '', FB_PAGE_ACC, $finance_connect);
                                    if (!$fbpage_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $fbpage_row = ($fbpage_rst && $fbpage_rst->num_rows > 0) ? $fbpage_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_fbpage" id="for_fbpage" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($fbpage_row['name']) ? $fbpage_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_fbpage_hidden" id="for_fbpage_hidden"
                                    value="<?php echo (isset($row['fb_page'])) ? $row['fb_page'] : ''; ?>">


                                <?php if (isset($fbpage_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $fbpage_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_channel_lbl" for="for_channel">Channel<span
                                        class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['channel']))
                                    $echoVal = $row['channel'];

                                if (isset($echoVal)) {
                                    $channel_rst = getData('*', "id = '$echoVal'", '', CHANEL_SC_MD, $finance_connect);
                                    if (!$channel_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $channel_row = ($channel_rst && $channel_rst->num_rows > 0) ? $channel_rst->fetch_assoc() : array();
                                }

                                ?>
                                <input class="form-control" type="text" name="for_channel" id="for_channel" <?php if ($act == '')
                                    echo 'disabled' ?> value="<?php echo !empty($echoVal) ? (isset($channel_row['name']) ? $channel_row['name'] : '') : ''; ?>">
                                <input type="hidden" name="for_channel_hidden" id="for_channel_hidden"
                                value="<?php echo (isset($row['channel'])) ? $row['channel'] : (isset($channel_row) ? $channel_row['id'] : ''); ?>">



                                <?php if (isset($channel_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $channel_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" id="for_price_lbl" for="for_price">Price<span class="requireRed">*</span></label>
                                <?php 
                                unset($echoVal);

                                if (isset($row['price']))
                                    $echoVal = $row['price'];
                                ?>
                                <input class="form-control" type="text" name="for_price" id="for_price" value="<?php echo !empty($echoVal) ? $row['price'] : '' ?>" <?php if ($act == '') echo 'disabled' ?>>
                                <input type="hidden" name="member_point_original_price" id="member_point_original_price" value="<?= $memberPointRenderOriginalPrice > 0 ? htmlspecialchars(number_format($memberPointRenderOriginalPrice, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>">
                                <div id="member_point_cashback_price_summary" class="member-point-cashback-summary mt-2" style="display:none;">
                                    <div class="small text-muted mb-1">Cashback price summary</div>
                                    <div class="member-point-cashback-summary-line">
                                        <span>Original Price</span>
                                        <strong id="member_point_original_price_display">RM 0.00</strong>
                                    </div>
                                    <div class="member-point-cashback-summary-line">
                                        <span>Cashback Deduction</span>
                                        <strong id="member_point_cashback_deduction_display">- RM 0.00</strong>
                                    </div>
                                    <div class="member-point-cashback-summary-line">
                                        <span>Customer Pay</span>
                                        <strong id="member_point_cashback_final_price_display">RM 0.00</strong>
                                    </div>
                                    <div id="member_point_cashback_limit_hint" class="small text-muted mt-1"></div>
                                </div>
                                <?php if (isset($price_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $price_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3 autocomplete">
                                <label class="form-label form_lbl" id="for_pay_meth_lbl" for="for_pay_meth">Payment
                                    Method<span class="requireRed">*</span></label>
                                <?php
                                unset($echoVal);

                                if (isset($row['pay_method']))
                                    $echoVal = $row['pay_method'];

                                if (isset($echoVal)) {
                                    $pay_rst = getData('name', "id = '$echoVal'", '', FIN_PAY_METH, $finance_connect);
                                    if (!$pay_rst) {
                                        // Graceful fallback: keep form usable even when lookup query is unavailable.
                                    }
                                    $pay_row = ($pay_rst && $pay_rst->num_rows > 0) ? $pay_rst->fetch_assoc() : array();
                                }
                                ?>
                                <input class="form-control" type="text" name="for_pay_meth" id="for_pay_meth" <?php if ($act == '')
                                    echo 'disabled' ?>
                                        value="<?php echo !empty($echoVal) ? (isset($pay_row['name']) ? $pay_row['name'] : '') : '' ?>">
                                <input type="hidden" name="for_pay_meth_hidden" id="for_pay_meth_hidden"
                                    value="<?php echo (isset($row['pay_method'])) ? $row['pay_method'] : ''; ?>">


                                <?php if (isset($pay_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $pay_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="for_order_status">Initial Order Status<span class="requireRed">*</span></label>
                                <?php
                                $forCurrentOrderStatusValue = isset($for_order_status) && trim((string) $for_order_status) !== ''
                                    ? $for_order_status
                                    : (isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P');
                                ?>
                                <?php if ($act === 'I') { ?>
                                    <select class="form-select" id="for_order_status" name="for_order_status">
                                        <?php foreach ($forStatusOptions as $statusCode => $statusLabel) { ?>
                                            <option value="<?= htmlspecialchars($statusCode) ?>" <?= $forCurrentOrderStatusValue === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <input class="form-control" type="text" value="<?= htmlspecialchars(shopeeOmsGetStatusLabel($forCurrentOrderStatusValue)) ?>" readonly>
                                    <input type="hidden" id="for_order_status" name="for_order_status" value="<?= htmlspecialchars($forCurrentOrderStatusValue) ?>">
                                <?php } ?>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="for_stock_out_warehouse_id">Stock Out Warehouse<span class="requireRed">*</span></label>
                                <?php
                                $forCurrentStockOutWarehouseId = isset($for_stock_out_warehouse_id) && (int) $for_stock_out_warehouse_id > 0
                                    ? (int) $for_stock_out_warehouse_id
                                    : (isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $forDefaultWarehouseId) : $forDefaultWarehouseId);
                                if ($forCurrentStockOutWarehouseId <= 0 && !empty($forWarehouseRows)) {
                                    $forCurrentStockOutWarehouseId = (int) $forWarehouseRows[0]['id'];
                                }
                                $forCurrentStockOutWarehouseName = shopeeOmsResolveWarehouseNameById($connect, $forCurrentStockOutWarehouseId, $forDefaultWarehouseId, $forWarehouseNameMap);
                                $forIsStockOutWarehouseEditableForForm = $act !== '' && ($act === 'I' || shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : ''));
                                ?>
                                <?php if ($forIsStockOutWarehouseEditableForForm) { ?>
                                    <select class="form-select" id="for_stock_out_warehouse_id" name="for_stock_out_warehouse_id">
                                        <?php foreach ($forWarehouseRows as $forWarehouseRow) { ?>
                                            <?php $forWarehouseId = isset($forWarehouseRow['id']) ? (int) $forWarehouseRow['id'] : 0; ?>
                                            <option value="<?= $forWarehouseId ?>" <?= $forCurrentStockOutWarehouseId === $forWarehouseId ? 'selected' : '' ?>><?= htmlspecialchars((string) $forWarehouseRow['name']) ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } else { ?>
                                    <input class="form-control" type="text" value="<?= htmlspecialchars($forCurrentStockOutWarehouseName) ?>" readonly>
                                    <input type="hidden" id="for_stock_out_warehouse_id" name="for_stock_out_warehouse_id" value="<?= (int) $forCurrentStockOutWarehouseId ?>">
                                <?php } ?>
                                <?php if (isset($stock_out_warehouse_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $stock_out_warehouse_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-2 mb-3 shopee-airbill-toggle-col">
                                <?php
                                $forHasSavedAirbillData = false;
                                if (isset($row['airbill_no']) && trim((string) $row['airbill_no']) !== '') {
                                    $forHasSavedAirbillData = true;
                                }
                                if (isset($row['airbill_attachment']) && trim((string) $row['airbill_attachment']) !== '') {
                                    $forHasSavedAirbillData = true;
                                }
                                $forUpdateAirbillValue = isset($for_update_airbill) && trim((string) $for_update_airbill) !== ''
                                    ? strtolower(trim((string) $for_update_airbill))
                                    : ($forHasSavedAirbillData ? 'yes' : ($act === 'I' ? 'yes' : 'no'));
                                if ($forUpdateAirbillValue !== 'yes' && $forHasSavedAirbillData) {
                                    $forUpdateAirbillValue = 'yes';
                                } else if ($forUpdateAirbillValue !== 'yes') {
                                    $forUpdateAirbillValue = 'no';
                                }
                                ?>
                                <input type="hidden" id="for_update_airbill" name="for_update_airbill" value="<?= htmlspecialchars($forUpdateAirbillValue) ?>">
                                <label class="form-label form_lbl shopee-airbill-toggle-label" for="for_update_airbill_toggle">Update Airbill?</label>
                                <div class="shopee-airbill-toggle-field">
                                    <label class="shopee-airbill-toggle mb-0" for="for_update_airbill_toggle">
                                        <input type="checkbox" id="for_update_airbill_toggle" <?= $forUpdateAirbillValue === 'yes' ? 'checked' : '' ?> <?= $act == '' ? 'disabled' : '' ?>>
                                        <span class="shopee-airbill-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="for_airbill_no">Airbill No</label>
                                <input class="form-control" type="text" name="for_airbill_no" id="for_airbill_no" value="<?php
                                if (isset($for_airbill_no)) {
                                    echo htmlspecialchars($for_airbill_no);
                                } else if (isset($row['airbill_no'])) {
                                    echo htmlspecialchars((string) $row['airbill_no']);
                                }
                                ?>" <?php if ($act == '') echo 'disabled' ?>>
                                <?php if (isset($airbill_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $airbill_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="for_airbill_attachment">Airbill Attachment</label>
                                <input class="form-control" type="file" name="for_airbill_attachment" id="for_airbill_attachment" <?php if ($act == '') echo 'disabled' ?>>
                                <small id="for_airbill_extract_status" class="shopee-airbill-extract-status"></small>
                                <?php
                                $forCurrentAirbillAttachmentValue = isset($for_airbill_attachment) && trim((string) $for_airbill_attachment) !== ''
                                    ? trim((string) $for_airbill_attachment)
                                    : (isset($row['airbill_attachment']) ? trim((string) $row['airbill_attachment']) : '');
                                $forCurrentAirbillAttachmentUrl = $forCurrentAirbillAttachmentValue !== '' ? shopeeOmsBuildAirbillAttachmentUrl($forCurrentAirbillAttachmentValue) : '';
                                $forCurrentAirbillAttachmentExt = $forCurrentAirbillAttachmentUrl !== ''
                                    ? strtolower(pathinfo((string) parse_url($forCurrentAirbillAttachmentUrl, PHP_URL_PATH), PATHINFO_EXTENSION))
                                    : '';
                                ?>
                                <?php if ($forCurrentAirbillAttachmentValue !== '') { ?>
                                    <div class="mt-2 small">
                                        Current Attachment:
                                        <?php if ($forCurrentAirbillAttachmentUrl !== '') { ?>
                                            <a href="<?= htmlspecialchars($forCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= htmlspecialchars($forCurrentAirbillAttachmentValue) ?></a>
                                        <?php } else { ?>
                                            <span><?= htmlspecialchars($forCurrentAirbillAttachmentValue) ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                                <input type="hidden" name="for_airbill_attachment_value" id="for_airbill_attachment_value" value="<?= htmlspecialchars($forCurrentAirbillAttachmentValue, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($airbill_attachment_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $airbill_attachment_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>

                            <div class="col-md-6 mb-3 d-flex justify-content-center justify-content-md-end">
                                <?php if ($forCurrentAirbillAttachmentUrl !== '') { ?>
                                    <div id="for_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media">
                                        <?php if (in_array($forCurrentAirbillAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                                            <img src="<?= htmlspecialchars($forCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Airbill Attachment Preview">
                                        <?php } else if ($forCurrentAirbillAttachmentExt === 'pdf') { ?>
                                            <iframe src="<?= htmlspecialchars($forCurrentAirbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" title="Airbill Attachment Preview"></iframe>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div id="for_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media" style="display:none;"></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                    <legend class="float-none w-auto p-2">Shipping Receiver Details</legend>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="for_rec_name_lbl" for="for_rec_name">Receiver
                                    Name<span class="requireRed">*</span></label>
                                    <?php 
                                unset($echoVal);

                                if (isset($row['ship_rec_name']))
                                    $echoVal = $row['ship_rec_name'];
                                ?>
                                <input class="form-control" type="text" name="for_rec_name" id="for_rec_name" value="<?php echo !empty($echoVal) ? $row['ship_rec_name'] : '' ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($rec_name_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $rec_name_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="for_rec_ctc_lbl" for="for_rec_ctc">Receiver
                                    Contact<span class="requireRed">*</span></label>
                                    <?php 
                                unset($echoVal);

                                if (isset($row['ship_rec_contact']))
                                    $echoVal = $row['ship_rec_contact'];
                                ?>
                                <input class="form-control" type="number" name="for_rec_ctc" id="for_rec_ctc" value="<?php echo !empty($echoVal) ? $row['ship_rec_contact'] : '' ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($rec_ctc_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $rec_ctc_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label form_lbl" id="for_rec_add_lbl" for="for_rec_add">Receiver
                                    Address<span class="requireRed">*</span></label>
                                    <?php 
                                unset($echoVal);

                                if (isset($row['ship_rec_add']))
                                    $echoVal = $row['ship_rec_add'];
                                ?>
                                <input class="form-control" type="text" name="for_rec_add" id="for_rec_add" value="<?php echo !empty($echoVal) ? $row['ship_rec_add'] : '' ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($rec_add_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $rec_add_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>

                        </div>
                    </div>
                </fieldset>
                <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                    <legend class="float-none w-auto p-2">Redeem Details</legend>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="redeem_source_display">Redeem Source</label>
                                <input class="form-control" type="text" id="redeem_source_display" value="<?= htmlspecialchars((string) ($row['redeem_source'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="redeem_reference_display">Redeem Reference</label>
                                <input class="form-control" type="text" id="redeem_reference_display" value="<?= htmlspecialchars((string) ($row['redeem_reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="claim_email_display">Claim Email</label>
                                <input class="form-control" type="text" id="claim_email_display" value="<?= htmlspecialchars((string) ($row['claim_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" disabled>
                            </div>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="border p-2 mb-3" style="border-radius: 3px;">
                    <legend class="float-none w-auto p-2">Member Points</legend>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="member_point_platform">Platform</label>
                                <select class="form-select" name="member_point_platform" id="member_point_platform" <?= $memberPointRenderDisabled ? 'disabled' : '' ?>>
                                    <option value="">Select Platform</option>
                                    <option value="shopee" <?= $memberPointRenderPlatform === 'shopee' ? 'selected' : '' ?>>Shopee</option>
                                    <option value="lazada" <?= $memberPointRenderPlatform === 'lazada' ? 'selected' : '' ?>>Lazada</option>
                                </select>
                                <?php if ($memberPointRenderDisabled) { ?>
                                    <input type="hidden" name="member_point_platform" value="<?= htmlspecialchars($memberPointRenderPlatform, ENT_QUOTES, 'UTF-8') ?>">
                                <?php } ?>
                                <?php if (isset($member_point_platform_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $member_point_platform_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-8 mb-3 autocomplete">
                                <label class="form-label form_lbl" for="member_point_customer_search">Linked Member</label>
                                <input
                                    class="form-control"
                                    type="text"
                                    name="member_point_customer_search"
                                    id="member_point_customer_search"
                                    value="<?= htmlspecialchars($memberPointRenderCustomerLabel, ENT_QUOTES, 'UTF-8') ?>"
                                    placeholder="Select Shopee or Lazada customer"
                                    <?= $memberPointRenderDisabled ? 'readonly' : '' ?>>
                                <input type="hidden" name="member_point_customer_id" id="member_point_customer_id" value="<?= (int) $memberPointRenderCustomerId ?>">
                                <input type="hidden" name="member_point_customer_label" id="member_point_customer_label" value="<?= htmlspecialchars($memberPointRenderCustomerLabel, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (isset($member_point_customer_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $member_point_customer_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="member-point-summary-card">
                                    <h6>Available Points</h6>
                                    <div id="member_point_available_points" class="member-point-summary-value"><?= (int) $memberPointRenderAvailablePoints ?></div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="member-point-summary-card">
                                    <h6>Linked Customer</h6>
                                    <div id="member_point_customer_summary"><?= $memberPointRenderLinkedMember !== '' ? htmlspecialchars($memberPointRenderLinkedMember, ENT_QUOTES, 'UTF-8') : 'No member linked' ?></div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="member-point-summary-card">
                                    <h6>Redeemable Items</h6>
                                    <div id="member_point_reward_summary">
                                        <?php if (!empty($memberPointRenderSummaryRewards)) { ?>
                                            <ul id="member_point_reward_list" class="member-point-reward-list">
                                                <?php foreach ($memberPointRenderSummaryRewards as $memberPointRewardText) { ?>
                                                    <li><?= htmlspecialchars((string) $memberPointRewardText, ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php } ?>
                                            </ul>
                                        <?php } else { ?>
                                            <div id="member_point_reward_empty">No redeemable gift.</div>
                                            <ul id="member_point_reward_list" class="member-point-reward-list" style="display:none;"></ul>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label form_lbl" for="member_point_use_type">Apply Member Points</label>
                                <select class="form-select" name="member_point_use_type" id="member_point_use_type" <?= $memberPointRenderDisabled ? 'disabled' : '' ?>>
                                    <option value="none" <?= $memberPointRenderUseType === 'none' ? 'selected' : '' ?>>No Redeem</option>
                                    <option value="gift" <?= $memberPointRenderUseType === 'gift' ? 'selected' : '' ?>>Redeem Gift</option>
                                    <option value="cashback" <?= $memberPointRenderUseType === 'cashback' ? 'selected' : '' ?>>Use Cashback</option>
                                </select>
                                <?php if ($memberPointRenderDisabled) { ?>
                                    <input type="hidden" name="member_point_use_type" value="<?= htmlspecialchars($memberPointRenderUseType, ENT_QUOTES, 'UTF-8') ?>">
                                <?php } ?>
                            </div>
                            <div class="col-md-12 mb-3" id="member_point_gift_wrap">
                                <label class="form-label form_lbl" for="member_point_redeem_id">Redeem Item</label>
                                <select class="form-select" name="member_point_redeem_id" id="member_point_redeem_id" <?= $memberPointRenderDisabled ? 'disabled' : '' ?>>
                                    <option value="">No Redeem</option>
                                    <?php foreach ($memberPointRenderRewards as $memberPointRewardOption) { ?>
                                        <?php $memberPointRewardOptionId = (int) (isset($memberPointRewardOption['id']) ? $memberPointRewardOption['id'] : 0); ?>
                                        <?php $memberPointRewardOptionText = isset($memberPointRewardOption['display_text']) ? (string) $memberPointRewardOption['display_text'] : ''; ?>
                                        <option value="<?= $memberPointRewardOptionId ?>" <?= $memberPointRenderRedeemId === $memberPointRewardOptionId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($memberPointRewardOptionText, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <small class="text-muted d-block mt-1">Gift redemption deducts the required member points after this Facebook order is saved.</small>
                                <?php if ($memberPointRenderDisabled) { ?>
                                    <input type="hidden" name="member_point_redeem_id" value="<?= (int) $memberPointRenderRedeemId ?>">
                                <?php } ?>
                            </div>
                            <div class="col-md-12 mb-3" id="member_point_cashback_wrap">
                                <label class="form-label form_lbl" for="member_point_cashback_points">Cashback Points</label>
                                <input class="form-control" type="number" min="0" step="1" name="member_point_cashback_points" id="member_point_cashback_points" value="<?= (int) $memberPointRenderCashbackPoints ?>" <?= $memberPointRenderDisabled ? 'readonly' : '' ?>>
                                <small class="text-muted d-block mt-1">1 point = RM1. Single cashback cannot exceed 30% of the order amount, and Shopee/Lazada points can only be used on this private order flow.</small>
                                <div id="member_point_cashback_help" class="small text-muted mt-1"></div>
                                <?php if ($memberPointRenderDisabled) { ?>
                                    <input type="hidden" name="member_point_cashback_points" value="<?= (int) $memberPointRenderCashbackPoints ?>">
                                <?php } ?>
                            </div>
                            <div class="col-md-12">
                                <?php if (isset($member_point_redeem_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php echo $member_point_redeem_err; ?></span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="form-group mb-3">
                    <label class="form-label form_lbl" id="for_remark_lbl" for="for_remark">Remark</label>
                    <textarea class="form-control" name="for_remark" id="for_remark" rows="3" <?php if ($act == '')
                        echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                        echo $row['remark'] ?></textarea>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="for_attach_lbl" for="for_attach">Attachment*</label>
                                <input class="form-control" type="file" name="for_attach" id="for_attach" <?php if ($act == '')
                        echo 'disabled' ?>>

                            <?php if (isset($for_attach) && $for_attach) { ?>
                                <div class="text-success mt-1">
                                    <span class="mt-n1">
                                        <?php echo "Uploaded Attachment: " . htmlspecialchars($for_attach); ?>
                                    </span>
                                </div>
                                <input type="hidden" name="existing_attachment"
                                    value="<?php echo htmlspecialchars($for_attach); ?>">
                            <?php } else if (isset($row['attachment']) && $row['attachment']) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo "Current Attachment: " . htmlspecialchars($row['attachment']); ?>
                                    </span>
                                </div>
                                <input type="hidden" name="existing_attachment"
                                    value="<?php echo htmlspecialchars($row['attachment']); ?>">
                            <?php } ?>

                            <?php if (isset($attach_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $attach_err; ?>
                                    </span>
                                </div>
                            <?php } ?>

                        </div>
                        <div class="col-md-6 mb-3">
                        <div class="d-flex justify-content-center justify-content-md-end px-4">
                                <?php
                                    
                                unset($echoVal);
                                $attachmentSrc = '';
                                if (isset($row['attachment']))
                                    $echoVal = $row['attachment'];
                                    if(isset($echoVal)){
                                        
                                    if (isset($for_attach)) {
                                        $attachmentSrc = $img_path . $for_attach;
                                    }else{
                                        $attachmentSrc = $img_path . $echoVal;
                                    }
                                    }else{
                                        $attachmentSrc = '';
                                    }
                               
                                ?>
                                <img id="for_attach_preview" name="for_attach_preview"
                                    src="<?php echo !empty($echoVal) ? $attachmentSrc : '' ?>" class="img-thumbnail" alt="Attachment Preview">
                                <input type="hidden" name="for_attachmentValue" id="for_attachmentValue" value="<?php if (isset($row['attachment']))
                                    echo $row['attachment']; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                if(isset($row['order_status'])){
                if($row['order_status'] == 'SP'){
                ?>
                <div class="form-group mb-4">
                    <h3>
                        Tracking Details
                    </h3>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_courier_lbl" for="sor_courier">Courier</label>
                            <?php
                           
                            if (isset($row['id'])) {
                                $echoVal = $row['id'];
                            }
                            
                            $echoVal2 = ''; // Initialize safely
                            $courier_rst2 = getData('courier_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);

                            if ($courier_rst2 && $courier_rst2->num_rows > 0) {
                                $courier_row2 = $courier_rst2->fetch_assoc();
                                if (isset($courier_row2['courier_id'])) {
                                    $echoVal2 = $courier_row2['courier_id'];
                                }
                            }
                       
                            $courier_rst = getData('name', "id = '$echoVal2'", '', COURIER, $connect);
                            $courier_row = ($courier_rst && $courier_rst->num_rows > 0) ? $courier_rst->fetch_assoc() : array();
                      
                            if (isset($courier_row['name'])) {
                                $courier_name = $courier_row['name'];
                            } else {
                                $courier_name = '';
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_courier" id="sor_courier" value="<?php echo !empty($echoVal2) ? $courier_name : ''; ?>" disabled ?>

                            <?php if (isset($courier_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $courier_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_track_lbl" for="sor_track">Tracking Number</label>
                            
                            <?php
                             $tracking_rst = getData('tracking_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);
                             if (!$tracking_rst) {
                                // Graceful fallback: keep form usable even when lookup query is unavailable.
                            }
                            $tracking_row = ($tracking_rst && $tracking_rst->num_rows > 0) ? $tracking_rst->fetch_assoc() : array();
                            if (isset($tracking_row['tracking_id'])) {
                                $tracking_id = $tracking_row['tracking_id'];
                            } else {
                                $tracking_id = '';
                            }
                             ?>
                             <input class="form-control" type="text"  name="sor_track" id="sor_track" value="<?php echo !empty($echoVal) ? $tracking_id : ''; ?>" disabled ?>
                            <?php if (isset($tracking_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $tracking_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-4 d-flex align-items-end">
                            <label>&nbsp;</label><br>
                            <?php
                   
                            $tracking_link = ''; // Initialize safely
                            $tracking_rst2 = getData('tracking_link', "id = '$echoVal2'", '', COURIER, $connect);
                            
                            if ($tracking_rst2 && $tracking_rst2->num_rows > 0) {
                                $track_row = $tracking_rst2->fetch_assoc();
                                if (isset($track_row['tracking_link'])) {
                                    $tracking_link = $track_row['tracking_link'];
                                }
                            }
                            ?>
                            
                            <a href="<?php echo $tracking_link; ?>" id="trackOrderBtn" class="track-order-btn" data-tracking-id="<?php echo $tracking_id; ?>" target="_blank">Track Order</a>
                            
                            </div>
                        </div>
                    </div>
                <?php } }?>
                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column mobile-sticky-form-actions-target shopee-order-action-row">
                    <?php
                    if ($act === 'E' && isset($row['order_status'])) {
                        $statusCode = shopeeOmsNormalizeStatusCode($row['order_status']);
                        $canMoveToPack = shopeeOmsHasTransitionPermission($connect, $statusCode, 'TP', USER_GROUP, $row, USER_ID);
                        if ($statusCode === 'P' && $canMoveToPack) {
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="TP" formnovalidate>MOVE TO TO PACK</button>';
                        }
                    }

                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    ?>
                    <button type="button" class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="backBtn" id="backBtn"
                        onclick="location.href = <?= htmlspecialchars(json_encode($back_redirect_page), ENT_QUOTES, 'UTF-8') ?>;">Back</button>
                </div>
            </form>
        </div>
    </div>
    <!-- </div> -->

    <?php
    /*
        oufei 20231014
        common.fun.js
        function(title, subtitle, page name, ajax url path, redirect path, action)
        to show action dialog after finish certain action (eg. edit)
    */
    if (isset($_SESSION['tempValConfirmBox'])) {
        unset($_SESSION['tempValConfirmBox']);
        echo $clearLocalStorage;
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
    }
    if ($forPopupErrorMessage !== '') {
        echo '<script>document.addEventListener("DOMContentLoaded", function () { confirmationDialog("", ' . json_encode($forPopupErrorMessage) . ', ' . json_encode((string) $pageTitle) . ', "", "", "ErrMO"); });</script>';
    }
    ?>
    <script>
        <?php echo shopeeOmsRenderAirbillAttachmentPreviewScript(); ?>

        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";
        window.fbOrderReqConfig = {
            siteUrl: <?= json_encode($SITEURL) ?>,
            tables: {
                user: <?= json_encode(USR_USER) ?>,
                countries: <?= json_encode(COUNTRIES) ?>,
                brand: <?= json_encode(BRAND) ?>,
                series: <?= json_encode(BRD_SERIES) ?>,
                package: <?= json_encode(PKG) ?>,
                facebookPage: <?= json_encode(FB_PAGE_ACC) ?>,
                channel: <?= json_encode(CHANEL_SC_MD) ?>,
                paymentMethod: <?= json_encode(FIN_PAY_METH) ?>
            },
            memberPoint: {
                locked: <?= $memberPointRenderLocked ? 'true' : 'false' ?>,
                viewOnly: <?= $act === '' ? 'true' : 'false' ?>,
                initialUseType: <?= json_encode($memberPointRenderUseType) ?>,
                initialCashbackPoints: <?= (int) $memberPointRenderCashbackPoints ?>,
                initialOriginalPrice: <?= json_encode($memberPointRenderOriginalPrice > 0 ? number_format($memberPointRenderOriginalPrice, 2, '.', '') : '') ?>,
                lookupUrl: window.location.href.split('#')[0],
                platforms: {
                    shopee: {
                        searchType: 'buyer_username',
                        dbTable: <?= json_encode(SHOPEE_CUST_INFO) ?>
                    },
                    lazada: {
                        searchType: 'name',
                        dbTable: <?= json_encode(LAZADA_CUST_RCD) ?>
                    }
                }
            }
        };

        checkCurrentPage(page, action);

        setButtonColor();
        preloader(300, action);

        <?php
        include "../js/fb_order_req.js"
            ?>

        document.addEventListener('DOMContentLoaded', function () {
            function toggleFacebookAirbillFields() {
                var updateAirbill = document.getElementById('for_update_airbill');
                var updateAirbillToggle = document.getElementById('for_update_airbill_toggle');
                var airbillNo = document.getElementById('for_airbill_no');
                var airbillAttachment = document.getElementById('for_airbill_attachment');
                var existingAttachment = document.getElementById('for_airbill_attachment_value');
                if (!updateAirbill || !updateAirbillToggle || !airbillNo || !airbillAttachment) {
                    return;
                }

                updateAirbill.value = updateAirbillToggle.checked ? 'yes' : 'no';
                var enabled = updateAirbillToggle.checked;
                var readOnlyMode = "<?= $act ?>" === '';
                airbillNo.disabled = readOnlyMode || !enabled;
                airbillAttachment.disabled = readOnlyMode || !enabled;
                airbillNo.required = enabled;
                airbillAttachment.required = enabled && (!existingAttachment || existingAttachment.value.trim() === '');
            }

            if (window.shopeeOmsAirbillAttachmentPreview) {
                window.shopeeOmsAirbillAttachmentPreview.bind({
                    fileInputSelector: '#for_airbill_attachment',
                    previewWrapSelector: '#for_airbill_attachment_preview_wrap'
                });
            }

            if (window.shopeeOmsAirbillPdfAutofill) {
                window.shopeeOmsAirbillPdfAutofill.bind({
                    fileInputSelector: '#for_airbill_attachment',
                    airbillNoSelector: '#for_airbill_no',
                    customerAddressSelector: '#for_rec_add',
                    statusSelector: '#for_airbill_extract_status',
                    workerSrc: 'header/js/pdf.worker.min.js',
                    errorClass: 'is-error'
                });
            }

            toggleFacebookAirbillFields();

            var facebookUpdateAirbillToggle = document.getElementById('for_update_airbill_toggle');
            if (facebookUpdateAirbillToggle) {
                facebookUpdateAirbillToggle.addEventListener('change', toggleFacebookAirbillFields);
            }
        });
    </script>

</body>

</html>
