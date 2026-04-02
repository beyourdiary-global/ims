<?php
$pageTitle = "Website Order Request";
$currentPagePin = 92;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$pinAccess = checkCurrentPin($connect, $pageTitle);
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
</head>

<script>
    $(document).ready(() => {
        createSortingTable('website_order_request_table');
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
                                        </div>
                                    </td>
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

</body>
<script>
    dropdownMenuDispFix();
    datatableAlignment('website_order_request_table');
</script>

</html>