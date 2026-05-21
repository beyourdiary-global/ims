<?php
$pageTitle = "Facebook Order Request";
$currentPagePin = 69;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$pinAccess = checkCurrentPin($connect, $pageTitle);
$canAssignEstimatedReceivedDate = isActionAllowed('Edit', $pinAccess);
$estimatedDateToday = new DateTimeImmutable('today');
$estimatedDateMin = $estimatedDateToday->modify('+1 day')->format('Y-m-d');
$estimatedDateMax = $estimatedDateToday->modify('+10 days')->format('Y-m-d');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('assignEstimatedReceivedDateBtn')) {
    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        echo "<script>alert('Invalid session token. Please refresh the page and try again.'); location.replace('" . addslashes($_SERVER['REQUEST_URI']) . "');</script>";
        exit;
    }

    if (!$canAssignEstimatedReceivedDate) {
        echo "<script>alert('Security Error: You do not have permission to assign Estimate Received Dates.'); location.replace('" . addslashes($_SERVER['REQUEST_URI']) . "');</script>";
        exit;
    }

    $assignOrderId = postSpaceFilter('estimated_received_order_id');
    $assignDate = postSpaceFilter('estimated_received_date');
    $assignmentResult = assignEstimatedReceivedDate($finance_connect, FB_ORDER_REQ, $assignOrderId, $assignDate, USER_ID);

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
            'query_table' => FB_ORDER_REQ,
            'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
            'changes' => $changeSummary,
            'uid' => USER_ID,
            'act_msg' => $safeUserName . " assigned the Estimate Received Date <b>" . $safeAssignedDate . "</b> for Facebook order [ <b>ID = " . (int) $assignOrderId . "</b> ].",
            'cdate' => $cdate,
            'ctime' => $ctime,
            'cby' => USER_ID,
            'connect' => $connect
        );
        audit_log($auditData);
    }

    echo "<script>alert('" . addslashes($assignmentResult['message']) . "'); location.replace('" . addslashes($_SERVER['REQUEST_URI']) . "');</script>";
    exit;
}

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/finance/fb_order_req.php';
$deleteRedirectPage = $SITEURL . '/finance/fb_order_req_table.php';
$result = getData('*', '', '', FB_ORDER_REQ, $finance_connect);

function fbReqFetchAssoc($rst)
{
    if ($rst instanceof mysqli_result && $rst->num_rows > 0) {
        return $rst->fetch_assoc();
    }
    return array();
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
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
    </style>
</head>

<script>
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

    $(document).ready(() => {
        createSortingTable('fb_order_req_table');

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
                        
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                        href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                        Request </a>
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

                <table class="table table-striped" id="fb_order_req_table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Order Status</th>
                            <th scope="col">Estimate Received Date</th>
                            <th scope="col">Name</th>
                            <th scope="col">Facebook Link</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Country</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Series</th>
                            <th scope="col">Package</th>
                            <th scope="col">Facebook Page</th>
                            <th scope="col">Channel</th>
                            <th scope="col">Price</th>
                            <th scope="col">Payment Method</th>
                            <th scope="col">Shipping Receiver Name</th>
                            <th scope="col">Shipping Receiver Address</th>
                            <th scope="col">Shipping Receiver Contact</th>
                            <th scope="col">Remark</th>
                            <th scope="col">Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) {
                            $q1 = getData('name', "id='" . $row['sales_pic'] . "'", '', USR_USER, $connect);
                            $pic = fbReqFetchAssoc($q1);

                            $q2 = getData('nicename', "id='" . $row['country'] . "'", '', COUNTRIES, $connect);
                            $country = fbReqFetchAssoc($q2);

                            $q3 = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                            $brand = fbReqFetchAssoc($q3);

                            $q4 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            $series = fbReqFetchAssoc($q4);

                            $q5 = getData('name', "id='" . $row['package'] . "'", '', PKG, $connect);
                            $package = fbReqFetchAssoc($q5);

                            // fb page
                            $q6 = getData('name', "id='" . $row['fb_page'] . "'", '', FB_PAGE_ACC, $finance_connect);
                            $fb_page = fbReqFetchAssoc($q6);

                            // channel
                            $q7 = getData('name', "id='" . $row['channel'] . "'", '', CHANEL_SC_MD, $finance_connect);
                            $channel = fbReqFetchAssoc($q7);

                            $q8 = getData('name', "id='" . $row['pay_method'] . "'", '', FIN_PAY_METH, $finance_connect);
                            $pay_meth = fbReqFetchAssoc($q8);
                            ?>

                            <tr>
                                <th class="hideColumn" scope="row">
                                    <?= $row['id'] ?>
                                </th>
                                <th scope="row">
                                    <?= $num++; ?>
                                </th>
                                <td scope="row" class="btn-container">
                                    <div class="d-flex align-items-center">
                                    <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                    <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                    <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], $row['contact'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    <?php
                                    $safeFbName = mysqli_real_escape_string($connect, (string) $row['name']);
                                    $safeFbLink = mysqli_real_escape_string($connect, (string) $row['fb_link']);
                                    $dealRowRst = getData('id', "name='" . $safeFbName . "' AND fb_link='" . $safeFbLink . "'", 'LIMIT 1', FB_CUST_DEALS, $connect);
                                    $dealRow = fbReqFetchAssoc($dealRowRst);
                                    $dealId = isset($dealRow['id']) ? (string) ((int) $dealRow['id']) : '';
                                    $urbanismAction = getUrbanismMemberActionData(
                                        $connect,
                                        '',
                                        isset($row['name']) ? (string) $row['name'] : '',
                                        $deleteRedirectPage,
                                        $pageTitle
                                    );
                                    ?>
                                    <a
                                        class="btn <?= $urbanismAction['is_member'] ? 'btn-success' : 'btn-secondary' ?> me-1 <?= $urbanismAction['disabled'] ? 'disabled' : '' ?>"
                                        href="<?= htmlspecialchars($urbanismAction['url'], ENT_QUOTES, 'UTF-8') ?>"
                                        title="<?= htmlspecialchars($urbanismAction['title'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $urbanismAction['disabled'] ? 'onclick="return false;" aria-disabled="true"' : '' ?>><i class="<?= $urbanismAction['icon_class'] ?>"></i></a>
                                    <?php if (shouldShowEstimatedReceivedDateButton($row) && $canAssignEstimatedReceivedDate) { ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-warning btn-assign-estimated-date"
                                            data-order-id="<?= (int) $row['id'] ?>"
                                            data-order-code="<?= htmlspecialchars('FB Order #' . (int) $row['id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-min-date="<?= $estimatedDateMin ?>"
                                            data-max-date="<?= $estimatedDateMax ?>"
                                            title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                    <?php } ?>
                                    </div>
                                    </td>
                                <td><?= getMarketplaceRequestStatusLabel(isset($row['order_status']) ? $row['order_status'] : '') ?></td>
                                <td><?= isset($row['estimated_received_date']) && !empty($row['estimated_received_date']) ? htmlspecialchars((string) $row['estimated_received_date'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <td scope="row">
                                    <?= $row['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['fb_link'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['contact'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $pic['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $country['nicename'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $brand['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $series['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $package['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $fb_page['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $channel['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['price'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $pay_meth['name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['ship_rec_name'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['ship_rec_add'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['ship_rec_contact'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['remark'] ?? '' ?>
                                </td>
                                <td scope="row">
                                    <?= $row['attachment'] ?? '' ?>
                                </td>
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
                            <th scope="col">Name</th>
                            <th scope="col">Facebook Link</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Country</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Series</th>
                            <th scope="col">Package</th>
                            <th scope="col">Facebook Page</th>
                            <th scope="col">Channel</th>
                            <th scope="col">Price</th>
                            <th scope="col">Payment Method</th>
                            <th scope="col">Shipping Receiver Name</th>
                            <th scope="col">Shipping Receiver Address</th>
                            <th scope="col">Shipping Receiver Contact</th>
                            <th scope="col">Remark</th>
                            <th scope="col">Attachment</th>
                        </tr>
                    </tfoot>
                </table>
            <?php } ?>
        </div>

    </div>

    <div class="estimated-date-modal" id="estimatedReceivedDateModal" aria-hidden="true">
        <div class="estimated-date-modal__dialog">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h4 class="mb-0" id="estimatedReceivedDateTitle">Assign Estimate Received Date</h4>
                <button type="button" class="btn btn-light estimated-date-modal__close-btn" onclick="closeEstimatedReceivedDateModal()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="estimated_received_order_id" id="estimated_received_order_id" value="">
                <div class="mb-3">
                    <label class="form-label" for="estimated_received_date">Estimate Received Date</label>
                    <input type="date" class="form-control" name="estimated_received_date" id="estimated_received_date" min="<?= $estimatedDateMin ?>" max="<?= $estimatedDateMax ?>" required>
                    <small class="text-muted">Choose a date from <?= $estimatedDateMin ?> until <?= $estimatedDateMax ?>.</small>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light estimated-date-modal__action-btn" onclick="closeEstimatedReceivedDateModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary estimated-date-modal__action-btn" name="assignEstimatedReceivedDateBtn" value="1">Save</button>
                </div>
            </form>
        </div>
    </div>

</body>
<script>
    /**
  oufei 20231014
  common.fun.js
  function(void)
  to solve the issue of dropdown menu displaying inside the table when table class include table-responsive
*/
    dropdownMenuDispFix();

    /**
      oufei 20231014
      common.fun.js
      function(id)
      to resize table with bootstrap 5 classes
    */
    datatableAlignment('fb_order_req_table');
</script>

</html>
