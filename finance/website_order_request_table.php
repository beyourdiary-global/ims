<?php
$pageTitle = "Website Order Request";
$currentPagePin = 92;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
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
    $assignmentResult = assignEstimatedReceivedDate($finance_connect, WEB_ORDER_REQ, $assignOrderId, $assignDate, USER_ID);

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
            'query_table' => WEB_ORDER_REQ,
            'oldval' => $oldStatus !== '' ? ('order_status: ' . $oldStatus) : '',
            'changes' => $changeSummary,
            'uid' => USER_ID,
            'act_msg' => USER_NAME . " assigned the Estimate Received Date <b>" . $safeAssignedDate . "</b> for Website order [ <b>ID = " . (int) $assignOrderId . "</b> ].",
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

$redirect_page = $SITEURL . '/finance/website_order_request.php';
$deleteRedirectPage = $SITEURL . '/finance/website_order_request_table.php';

// Fetch all orders from Finance Database
$result = getData('*', '', '', WEB_ORDER_REQ, $finance_connect);
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
                                <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                    href="<?= $redirect_page . "?act=I&pageTitle=" . $pageTitle ?>">
                                    <i class="fa-solid fa-plus"></i> Add Request
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
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
                                    <td class="hideColumn" scope="row"><?= $row['id'] ?></td>
                                    <td scope="row"><?= $num++ ?></td>
                                    <td scope="row" class="btn-container">
                                        <div class="d-flex align-items-center">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['order_id'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                            <?php if (shouldShowEstimatedReceivedDateButton($row) && $canAssignEstimatedReceivedDate) { ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-warning btn-assign-estimated-date"
                                                    data-order-id="<?= (int) $row['id'] ?>"
                                                    data-order-code="<?= htmlspecialchars((string) (isset($row['order_id']) ? $row['order_id'] : ('Website Order #' . (int) $row['id'])), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-min-date="<?= $estimatedDateMin ?>"
                                                    data-max-date="<?= $estimatedDateMax ?>"
                                                    title="Assign Estimate Received Date"><i class="fa-solid fa-calendar-days"></i></button>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td scope="row"><?= getMarketplaceRequestStatusLabel(isset($row['order_status']) ? $row['order_status'] : '') ?></td>
                                    <td scope="row"><?= isset($row['estimated_received_date']) && !empty($row['estimated_received_date']) ? $row['estimated_received_date'] : '' ?></td>
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
    dropdownMenuDispFix();
    datatableAlignment('website_order_request_table');
</script>

</html>
