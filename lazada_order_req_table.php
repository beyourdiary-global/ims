<?php
$pageTitle = "Lazada Order Request";
$currentPagePin = 93;
include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$pinAccess = checkCurrentPin($connect, $pageTitle);
$canAssignEstimatedReceivedDate = isActionAllowed('Edit', $pinAccess);
$estimatedDateToday = new DateTimeImmutable('today');
$estimatedDateMin = $estimatedDateToday->modify('+1 day')->format('Y-m-d');
$estimatedDateMax = $estimatedDateToday->modify('+10 days')->format('Y-m-d');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('assignEstimatedReceivedDateBtn')) {
    if (!$canAssignEstimatedReceivedDate) {
        echo "<script>alert('Security Error: You do not have permission to assign Estimate Received Dates.'); location.replace('" . addslashes($_SERVER['REQUEST_URI']) . "');</script>";
        exit;
    }

    $assignOrderId = postSpaceFilter('estimated_received_order_id');
    $assignDate = postSpaceFilter('estimated_received_date');
    $assignmentResult = assignEstimatedReceivedDate($connect, LAZADA_ORDER_REQ, $assignOrderId, $assignDate, USER_ID);

    if ($assignmentResult['success']) {
        $safeAssignedDate = isset($assignmentResult['date']) ? $assignmentResult['date'] : '';
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
            'act_msg' => USER_NAME . " assigned the Estimate Received Date <b>" . $safeAssignedDate . "</b> for Lazada order [ <b>ID = " . (int) $assignOrderId . "</b> ].",
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
$whereCondition = "";
if (input('ids')) {
    $whereCondition = "id IN (" . input('ids') . ")";
}
$redirect_page = $SITEURL . '/lazada_order_req.php';
$deleteRedirectPage = $SITEURL . '/lazada_order_req_table.php';
$result = getData('*', $whereCondition, '', LAZADA_ORDER_REQ, $connect);

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
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
        createSortingTable('lazada_order_req');

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

<style>
    .btn {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
        margin: 3px;
    }

    .btn-container {
        white-space: nowrap;
    }

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
                                    Record </a>
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
                        <?php while ($row = $result->fetch_assoc()) {
                            $q1 = getData('name', "id='" . $row['lazada_acc'] . "'", '', LAZADA_ACC, $finance_connect);
                            $lazada_acc = $q1->fetch_assoc();

                            $q2 = getData('nicename', "id='" . $row['country'] . "'", '', COUNTRIES, $connect);
                            $country = $q2->fetch_assoc();

                            $q3 = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                            $brand = $q3->fetch_assoc();

                            $q4 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            $series = $q4->fetch_assoc();

                            $q5 = getData('unit', "id='" . $row['curr_unit'] . "'", '', CUR_UNIT, $connect);
                            $curr_unit = $q5->fetch_assoc();

                            $q6 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            $series = $q6->fetch_assoc();

                            $q7 = getData('name', "id='" . $row['pay_meth'] . "'", '', FIN_PAY_METH, $finance_connect);
                            $pay_meth = $q7->fetch_assoc();

                            $q8 = getData('name', "id='" . $row['pkg'] . "'", '', PKG, $connect);
                            $package = $q8->fetch_assoc();
                            ?>

                            <tr>
                                <th class="hideColumn" scope="row">
                                    <?= $row['id'] ?>
                                </th>
                                <th scope="row">
                                    <?= $num++; ?>
                                </th>
                                <td scope="row" class="btn-container">
                                    <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                    <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                    <?php if (isActionAllowed("Delete", $pinAccess)): ?>
                                        <a class="btn btn-danger"
                                            onclick="confirmationDialog('<?= $row['id'] ?>',['<?= $row['curr_unit'] ?>','<?= $row['country'] ?>'],'<?php echo $pageTitle ?>','<?= $redirect_page ?>','<?= $deleteRedirectPage ?>','D')"><i
                                                class="fas fa-trash-alt"></i></a>
                                    <?php endif; ?>
                                    <?php if (shouldShowEstimatedReceivedDateButton($row) && $canAssignEstimatedReceivedDate) { ?>
                                        <button
                                            type="button"
                                            class="btn btn-warning btn-assign-estimated-date"
                                            data-order-id="<?= (int) $row['id'] ?>"
                                            data-order-code="<?= htmlspecialchars((string) (isset($row['oder_number']) ? $row['oder_number'] : ('Lazada Order #' . (int) $row['id'])), ENT_QUOTES, 'UTF-8') ?>"
                                            data-min-date="<?= $estimatedDateMin ?>"
                                            data-max-date="<?= $estimatedDateMax ?>"
                                            title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                    <?php } ?>
                                </td>
                                <td><?= getMarketplaceRequestStatusLabel(isset($row['order_status']) ? $row['order_status'] : '') ?></td>
                                <td scope="row"><?= isset($row['estimated_received_date']) && !empty($row['estimated_received_date']) ? $row['estimated_received_date'] : '' ?></td>
                                <td scope="row"><?= isset($lazada_acc['name']) ? $lazada_acc['name'] : '' ?></td>
                                <td scope="row"><?= isset($curr_unit['unit']) ? $curr_unit['unit'] : $row['curr_unit']; ?></td>
                                <td scope="row"><?= isset($country['nicename']) ? $country['nicename'] : '' ?></td>
                                <td scope="row"><?= $row['cust_id'] ?></td>
                                <td scope="row"><?= $row['cust_name'] ?></td>
                                <td scope="row"><?= $row['cust_email'] ?></td>
                                <td scope="row"><?= $row['cust_phone'] ?></td>
                                <td scope="row"><?= isset($country['nicename']) ? $country['nicename'] : '' ?></td>
                                <td scope="row"><?= $row['oder_number'] ?></td>
                                <td scope="row"><?= $row['sales_pic'] ?></td>
                                <td scope="row"><?= $row['ship_rec_name'] ?></td>
                                <td scope="row"><?= $row['ship_rec_address'] ?></td>
                                <td scope="row"><?= $row['ship_rec_contact'] ?></td>
                                <td scope="row"><?= isset($brand['name']) ? $brand['name'] : '' ?></td>
                                <td scope="row"><?= isset($series['name']) ? $series['name'] : '' ?></td>
                                <td scope="row"><?= isset($package['name']) ? $package['name'] : '' ?></td>
                                <td scope="row"><?= $row['item_price_credit'] ?></td>
                                <td scope="row"><?= $row['commision'] ?></td>
                                <td scope="row"><?= $row['other_discount'] ?></td>
                                <td scope="row"><?= $row['pay_fee'] ?></td>
                                <td scope="row"><?= $row['final_income'] ?></td>
                                <td scope="row"><?= isset($pay_meth['name']) ? $pay_meth['name'] : '' ?></td>
                                <td scope="row"><?= $row['remark'] ?></td>
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

    <div class="estimated-date-modal" id="estimatedReceivedDateModal" aria-hidden="true">
        <div class="estimated-date-modal__dialog">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h4 class="mb-0" id="estimatedReceivedDateTitle">Assign Estimate Received Date</h4>
                <button type="button" class="btn btn-light estimated-date-modal__close-btn" onclick="closeEstimatedReceivedDateModal()" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="post">
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
    datatableAlignment('lazada_order_req');
</script>

</html>
