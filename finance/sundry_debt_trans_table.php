<?php
$pageTitle = "Sundry Debtors Transaction";
$isFinance = 1;
include '../menuHeader.php';
include '../checkCurrentPagePin.php';

$pinAccess = checkCurrentPin($connect, $pageTitle);
$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/finance/sundry_debt_trans.php';
$result = getData('*', '', '', SD_TRANS, $finance_connect);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    preloader(300);

    $(document).ready(() => {
        createSortingTable('sundry_debt_trans_table');
    });
</script>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">

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
                                            Transaction </a>
                                    <?php endif; ?>
                                </div>
                        </div>
                    </div>
                </div>

                <?php if (!$result) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else {
                    ?>

                    <table class="table table-striped" id="sundry_debt_trans_table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Transaction ID</th>
                                <th scope="col">Type</th>
                                <th scope="col">Payment Date</th>
                                <th scope="col">Debtors</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Previous Amount Record</th>
                                <th scope="col">Final Amount Record</th>
                                <th scope="col">Description</th>
                                <th scope="col">Remark</th>
                                <th scope="col">Attachment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) {
                                if (isset($row['transactionID'], $row['id']) && !empty($row['transactionID'])) {

                                    $debtors = getData('name', "id='" . $row['debtors'] . "'", '', MERCHANT, $finance_connect);
                                    $row2 = $debtors->fetch_assoc();
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
                                        <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess);?>
                                        <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2) ?>
                                        <?php renderDeleteButton($pinAccess, $row['id'], $row['transactionID'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage) ?>
                                        </div>
                                        </td>
                                        <td scope="row">
                                            <?= $row['transactionID'] ?>
                                        </td>
                                        <td scope="row">
                                            <?php if (isset($row['type']))
                                                echo $row['type'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['payment_date']))
                                                echo $row['payment_date'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row2['name']))
                                                echo $row2['name'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['amount']))
                                                echo $row['amount'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['prev_amt']))
                                                echo $row['prev_amt'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['final_amt']))
                                                echo $row['final_amt'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['description']))
                                                echo $row['description'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['remark']))
                                                echo $row['remark'] ?>
                                            </td>
                                            <td scope="row">
                                            <?php if (isset($row['attachment']))
                                                echo $row['attachment'] ?>
                                            </td>
                                    </tr>
                                <?php }
                            } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Type</th>
                                <th scope="col">Payment Date</th>
                                <th scope="col">Debtors</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Previous Amount Record</th>
                                <th scope="col">Final Amount Record</th>
                                <th scope="col">Description</th>
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
    //Initial Page And Action Value
    var page = "<?= $pageTitle ?>";
    var action = "<?php echo isset($act) ? $act : ' '; ?>";

    checkCurrentPage(page, action);
    /* function(void) : to solve the issue of dropdown menu displaying inside the table when table class include table-responsive */
    dropdownMenuDispFix();
    /* function(id): to resize table with bootstrap 5 classes */
    datatableAlignment('sundry_debt_trans_table');
    setButtonColor();
</script>

</html>