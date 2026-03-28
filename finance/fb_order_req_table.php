<?php
$pageTitle = "Facebook Order Request";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pinAccess = checkCurrentPin($connect, $pageTitle);
$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/finance/fb_order_req.php';
$deleteRedirectPage = $SITEURL . '/finance/fb_order_req_table.php';
$reg_member_page = $SITEURL . '/urb_cust_reg.php';
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
</head>

<script>
    $(document).ready(() => {
        createSortingTable('fb_order_req_table');
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
                        <?php if ($result) { ?>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                        href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                        Request </a>
                                <?php endif; ?>
                            </div>
                        <?php } ?>
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
                                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-users"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <?php 
                                            $safeMemberName = mysqli_real_escape_string($connect, (string) $row['name']);
                                            $safeFbName = mysqli_real_escape_string($connect, (string) $row['name']);
                                            $safeFbLink = mysqli_real_escape_string($connect, (string) $row['fb_link']);

                                            $member_exist = getData('name', "name='" . $safeMemberName . "'", 'LIMIT 1', URBAN_CUST_REG, $connect);
                                            $deal_row_rst = getData('id', "name='" . $safeFbName . "' AND fb_link='" . $safeFbLink . "'", 'LIMIT 1', FB_CUST_DEALS, $connect);
                                            $deal_row = fbReqFetchAssoc($deal_row_rst);
                                            $deal_id = isset($deal_row['id']) ? (int) $deal_row['id'] : 0;

                                            if ($member_exist instanceof mysqli_result && $member_exist->num_rows > 0) {
                                                $reg_url = $reg_member_page . "?id=" . urlencode((string) $row['name']) . '&act=' . (isset($act_2) ? $act_2 : 'E');
                                                $menu_label = "Edit Member";
                                            } else if ($deal_id > 0) {
                                                $reg_url = $reg_member_page . "?id=" . $deal_id . '&act=' . (isset($act_1) ? $act_1 : 'I');
                                                $menu_label = "Register Member";
                                            } else {
                                                $reg_url = '#';
                                                $menu_label = "Register Member (Unavailable)";
                                            }
                                            ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= htmlspecialchars($reg_url, ENT_QUOTES, 'UTF-8') ?>" <?= $reg_url === '#' ? 'onclick="return false;"' : '' ?>>
                                                    <?= $menu_label ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    </td>
                                <td>
                                    <?php
                                         $status = $row['order_status'];
                                         if ($status == 'P') {
                                             $status = 'Processing';
                                         }else  if ($status == 'SP') {
                                             $status = 'Shipped';
                                         }else  if ($status == 'WP') {
                                             $status = 'Waiting Packing';
                                         }
                                        echo $status;
                                        ?>
                                </td>
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