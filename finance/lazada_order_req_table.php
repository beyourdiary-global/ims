<?php
$pageTitle = "Lazada Order Request";
$currentPagePin = 93;
$listPageSkipSessionReset = true;
$listPageSkipNumbering = true;

include_once __DIR__ . '/../include/list_page_header.php';

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
$currentTableRedirect = $currentTablePath !== '' ? $currentTablePath : '/finance/lazada_order_req_table.php';
if (!empty($currentTableQuery)) {
    $currentTableRedirect .= '?' . http_build_query($currentTableQuery);
}

$verifyId = (int) numberInput('verify_id');
$completeId = (int) numberInput('complete_id');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('assignEstimatedReceivedDateBtn')) {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes(htmlspecialchars((string) $_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8')) . "');</script>";
        exit;
    }

    if (!$canAssignEstimatedReceivedDate) {
        echo "<script>alert('Security Error: You do not have permission to assign Estimate Received Dates.'); location.replace('" . addslashes(htmlspecialchars((string) $_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8')) . "');</script>";
        exit;
    }

    $assignOrderId = postSpaceFilter('estimated_received_order_id');
    $assignDate = postSpaceFilter('estimated_received_date');
    $assignmentResult = assignEstimatedReceivedDate($connect, LAZADA_ORDER_REQ, $assignOrderId, $assignDate, USER_ID);

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
            'query_table' => LAZADA_ORDER_REQ,
            'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
            'changes' => $changeSummary,
            'uid' => USER_ID,
            'act_msg' => $safeUserName . " assigned the Estimate Received Date <b>" . $safeAssignedDate . "</b> for Lazada order [ <b>ID = " . (int) $assignOrderId . "</b> ].",
            'cdate' => $cdate,
            'ctime' => $ctime,
            'cby' => USER_ID,
            'connect' => $connect
        );
        audit_log($auditData);
    }

    echo "<script>alert('" . addslashes(htmlspecialchars((string) $assignmentResult['message'], ENT_QUOTES, 'UTF-8')) . "'); location.replace('" . addslashes(htmlspecialchars((string) $_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8')) . "');</script>";
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
        'lazada',
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
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes(htmlspecialchars((string) $_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8')) . "');</script>";
        exit;
    }

    $moveOrderId = (int) postSpaceFilter('move_to_pack_order_id');
    $moveToPackResult = shopeeOmsExecuteTransition($connect, $finance_connect, $moveOrderId, 'TP', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Moved to To Pack from Lazada order request table.',
        'action' => 'move_to_pack',
        'platform' => 'lazada',
    ));

    echo '<script>alert(' . json_encode((string) (isset($moveToPackResult['message']) ? $moveToPackResult['message'] : 'Unable to move order to To Pack.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '); location.replace(' . json_encode((string) $currentTableRedirect, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>';
    exit;
}

if ($verifyId > 0) {
    $orderId = $verifyId;
    $verifyResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'V', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Verified from Lazada order request table.',
        'platform' => 'lazada',
    ));
    echo "<script>alert('" . addslashes(isset($verifyResult['message']) ? $verifyResult['message'] : 'Unable to verify order.') . "'); location.replace('" . addslashes($currentTableRedirect) . "');</script>";
    exit;
}

if ($completeId > 0) {
    $orderId = $completeId;
    $completeResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'C', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Completed from Lazada order request table.',
        'platform' => 'lazada',
    ));
    echo "<script>alert('" . addslashes(isset($completeResult['message']) ? $completeResult['message'] : 'Unable to complete order.') . "'); location.replace('" . addslashes($currentTableRedirect) . "');</script>";
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('force_wafc_id') !== '' && post('move_to_wafc_with_received_date_btn') === '') {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes($currentTableRedirect) . "');</script>";
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
        'platform' => 'lazada',
    ));
    echo "<script>alert('" . addslashes(isset($wafcResult['message']) ? $wafcResult['message'] : 'Unable to move order to WAFC.') . "'); location.replace('" . addslashes($currentTableRedirect) . "');</script>";
    exit;
}

shopeeOmsHandleMoveToWafcWithReceivedDatePost($connect, $finance_connect, array(
    'redirect_url' => $currentTableRedirect,
    'source_page' => $pageTitle,
    'platform' => 'lazada',
    'actor_user_id' => USER_ID,
    'actor_user_group_id' => USER_GROUP,
    'audit_connect' => $connect,
    'query_table' => LAZADA_ORDER_REQ,
));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('return_id') !== '') {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes($currentTableRedirect) . "');</script>";
        exit;
    }

    $orderId = (int) post('return_id');
    $returnResult = shopeeOmsExecuteTransition($connect, $finance_connect, $orderId, 'R', array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => 'Marked as Return from Lazada order request table.',
        'action' => 'mark_return',
        'platform' => 'lazada',
    ));
    echo "<script>alert('" . addslashes(isset($returnResult['message']) ? $returnResult['message'] : 'Unable to mark order as Return.') . "'); location.replace('" . addslashes($currentTableRedirect) . "');</script>";
    exit;
}

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering
$whereCondition = "";
if (input('ids')) {
    $whereCondition = "id IN (" . input('ids') . ")";
}
$redirectPage = $SITEURL . '/finance/lazada_order_req.php';
$deleteRedirectPage = $SITEURL . '/finance/lazada_order_req_table.php';
$result = getData('*', $whereCondition, '', LAZADA_ORDER_REQ, $connect);

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/shopeeOrderRequest.css">
</head>

<body>

    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">

        <div class="col-12 col-md-11">

            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                            class="fa-solid fa-chevron-right fa-xs"></i>
                        <?php echo $pageTitle ?>
                    </p>
                </div>

                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2>
                            <?php echo $pageTitle ?>
                        </h2>
                        <div class="mt-auto mb-auto d-flex gap-2 flex-wrap">
                            <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="addBtn" id="addBtn"
                                    href="<?= $redirectPage . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                    Record </a>
                            <?php endif; ?>

                            <?php if (isActionAllowed("Import", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3 uniform-header-btn" name="importBtn" id="importBtn"
                                    href="<?= $SITEURL ?>/import/lazada_order_import.php"><i class="fa-solid fa-file-import"></i> Import </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            if (!$result) {
                echo '<div class="text-center"><h4>No Result!</h4></div>';
            } else {
                ?>

                <table class="table table-striped" id="lazada_order_req">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Order Status</th>
                            <th scope="col">Estimate Received Date</th>
                            <th scope="col">Lazada Account</th>
                            <th scope="col">Currency Unit</th>
                            <th scope="col">Country</th>
                            <th scope="col">Customer ID</th>
                            <th scope="col">Customer Name</th>
                            <th scope="col">Customer Email</th>
                            <th scope="col">Customer Phone</th>
                            <th scope="col">Country</th>
                            <th scope="col">Order Number</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Shipping Receiver Name</th>
                            <th scope="col">Shipping Receiver Address</th>
                            <th scope="col">Shipping Receiver Contact</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Series</th>
                            <th scope="col">Package</th>
                            <th scope="col">Item Price Credit</th>
                            <th scope="col">Commision</th>
                            <th scope="col">Other Discount</th>
                            <th scope="col">Payment Fee</th>
                            <th scope="col">Final Income</th>
                            <th scope="col">Payment Method</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fetchLookupRow = function ($result) {
                            if (!$result || !is_object($result) || !method_exists($result, 'fetch_assoc')) {
                                return array();
                            }

                            $row = $result->fetch_assoc();
                            return is_array($row) ? $row : array();
                        };
                        ?>
                        <?php while ($row = $result->fetch_assoc()) {
                            $row = shopeeOmsApplyReturnedOrderFinancials($row, 'lazada');
                            $q1 = getData('name', "id='" . $row['lazada_acc'] . "'", '', LAZADA_ACC, $finance_connect);
                            $lazada_acc = $fetchLookupRow($q1);

                            $q2 = getData('nicename', "id='" . $row['country'] . "'", '', COUNTRIES, $connect);
                            $country = $fetchLookupRow($q2);

                            $q3 = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                            $brand = $fetchLookupRow($q3);

                            $q4 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            $series = $fetchLookupRow($q4);

                            $q5 = getData('unit', "id='" . $row['curr_unit'] . "'", '', CUR_UNIT, $connect);
                            $curr_unit = $fetchLookupRow($q5);

                            $q6 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            $series = $fetchLookupRow($q6);

                            $q7 = getData('name', "id='" . $row['pay_meth'] . "'", '', FIN_PAY_METH, $finance_connect);
                            $pay_meth = $fetchLookupRow($q7);

                            $q8 = getData('name', "id='" . $row['pkg'] . "'", '', PKG, $connect);
                            $package = $fetchLookupRow($q8);
                            ?>

                            <tr>
                                <th class="hideColumn" scope="row">
                                    <?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?>
                                </th>
                                <th scope="row">
                                    <?= $num++; ?>
                                </th>
                                <td scope="row" class="btn-container">
                                    <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                    <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                    <?php renderDeleteButton($pinAccess, $row['id'], $row['curr_unit'], $row['country'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
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
                                        <form method="post" class="d-inline" onsubmit="return confirm('Move this order to To Pack?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="move_to_pack_order_id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-rounded btn-info" name="moveToPackBtn" value="1" title="Move to To Pack"><i class="fas fa-box-open"></i></button>
                                        </form>
                                    <?php } ?>
                                    <?php if ($statusCode === 'TP') { ?>
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= htmlspecialchars((string) shopeeOmsGetOrderSourceInfoUrl('lazada', (int) $row['id']), ENT_QUOTES, 'UTF-8') ?>" title="Open QR Info"><i class="fa-solid fa-qrcode"></i></a>
                                        <form method="post" class="d-inline" onsubmit="return commonConfirmFormSubmit(this, 'Resend Telegram Message');">
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
                                            data-order-code="<?= htmlspecialchars((string) (isset($row['oder_number']) ? $row['oder_number'] : ('Lazada Order #' . (int) $row['id'])), ENT_QUOTES, 'UTF-8') ?>"
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
                                                data-order-code="<?= htmlspecialchars((string) (isset($row['oder_number']) ? $row['oder_number'] : ('Lazada Order #' . (int) $row['id'])), ENT_QUOTES, 'UTF-8') ?>"
                                                title="Move to WAFC Now"><i class="fas fa-forward"></i></button>
                                        <?php } ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Mark this order as Return?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="return_id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-rounded btn-warning" title="Mark as Return"><i class="fa-solid fa-rotate-left"></i></button>
                                        </form>
                                    <?php } ?>
                                </td>
                                <td><?= getMarketplaceRequestStatusLabel(isset($row['order_status']) ? $row['order_status'] : '') ?></td>
                                <td scope="row"><?= isset($row['estimated_received_date']) && !empty($row['estimated_received_date']) ? htmlspecialchars((string) $row['estimated_received_date'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td scope="row"><?= isset($lazada_acc['name']) ? $lazada_acc['name'] : '' ?></td>
                                <td scope="row"><?= isset($curr_unit['unit']) ? $curr_unit['unit'] : $row['curr_unit']; ?></td>
                                <td scope="row"><?= isset($country['nicename']) ? $country['nicename'] : '' ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['cust_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['cust_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['cust_email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['cust_phone'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= isset($country['nicename']) ? $country['nicename'] : '' ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['oder_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['sales_pic'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['ship_rec_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['ship_rec_address'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['ship_rec_contact'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= isset($brand['name']) ? $brand['name'] : '' ?></td>
                                <td scope="row"><?= isset($series['name']) ? $series['name'] : '' ?></td>
                                <td scope="row"><?= isset($package['name']) ? $package['name'] : '' ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['item_price_credit'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['commision'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['other_discount'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['pay_fee'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['final_income'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td scope="row"><?= isset($pay_meth['name']) ? htmlspecialchars((string) $pay_meth['name'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td scope="row"><?= htmlspecialchars((string) $row['remark'], ENT_QUOTES, 'UTF-8') ?></td>
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
                            <th scope="col">Lazada Account</th>
                            <th scope="col">Currency Unit</th>
                            <th scope="col">Country</th>
                            <th scope="col">Customer ID</th>
                            <th scope="col">Customer Name</th>
                            <th scope="col">Customer Email</th>
                            <th scope="col">Customer Phone</th>
                            <th scope="col">Country</th>
                            <th scope="col">Order Number</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Shipping Receiver Name</th>
                            <th scope="col">Shipping Receiver Address</th>
                            <th scope="col">Shipping Receiver Contact</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Series</th>
                            <th scope="col">Package</th>
                            <th scope="col">Item Price Credit</th>
                            <th scope="col">Commision</th>
                            <th scope="col">Other Discount</th>
                            <th scope="col">Payment Fee</th>
                            <th scope="col">Final Income</th>
                            <th scope="col">Payment Method</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </tfoot>
                </table>
            <?php } ?>
        </div>


    </div>

    <?php include_once ROOT . '/include/estimated_date_modal.php'; ?>
    <?php shopeeOmsRenderReceivedDateModal(array('wrapper_attributes' => 'aria-hidden="true"')); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery) {
                const $ = window.jQuery;
                const tableSelector = '#lazada_order_req';
                const isDataTableReady = $.fn.DataTable && typeof $.fn.DataTable.isDataTable === 'function';

                if (typeof createSortingTable === 'function' && (!isDataTableReady || !$.fn.DataTable.isDataTable(tableSelector))) {
                    createSortingTable('lazada_order_req');
                }

                if (typeof datatableAlignment === 'function') {
                    datatableAlignment('lazada_order_req');
                }

                $(document).on('click', '.btn-assign-estimated-date', function () {
                    if (typeof openEstimatedReceivedDateModal === 'function') {
                        openEstimatedReceivedDateModal(
                            $(this).data('orderId'),
                            $(this).data('orderCode'),
                            $(this).data('minDate'),
                            $(this).data('maxDate')
                        );
                    }
                });
            }

            if (typeof dropdownMenuDispFix === 'function') {
                dropdownMenuDispFix();
            }
        });
    </script>
    <?php shopeeOmsRenderReceivedDateModalScript(); ?>

</body>

</html>
