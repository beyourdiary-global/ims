<?php
$pageTitle = "Cash On Hand Transaction";
$currentPagePin = 47;

include_once '../include/list_page_header.php';

$redirectPage = $SITEURL . '/finance/cash_on_hand_trans.php';
$result = getData('*', '', '', CAONHD, $finance_connect);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    $(document).ready(() => {
        createSortingTable('cash_on_hand_trans_table');
    });
</script>

<body>

    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">

        <div class="col-12 col-md-11">

            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                            class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                </div>

                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2><?php echo $pageTitle ?></h2>
                        <div class="mt-auto mb-auto">
                            <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                    href="<?= $redirectPage . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                    Transaction </a>
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

                <table class="table table-striped" id="cash_on_hand_trans_table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Transaction ID</th>
                            <th scope="col">Type</th>
                            <th scope="col">PIC</th>
                            <th scope="col">Date</th>
                            <th scope="col">Bank</th>
                            <th scope="col">Currency</th>
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

                                $curr = getData('unit', "id='" . $row['currency'] . "'", '', CUR_UNIT, $connect);
                                $row2 = $curr->fetch_assoc();

                                $bank = getData('name', "id='" . $row['bank'] . "'", '', BANK, $connect);
                                $row3 = $bank->fetch_assoc();

                                $pic = getData('name', "id='" . $row['pic'] . "'", '', USR_USER, $connect);
                                $usr = $pic->fetch_assoc();
                                ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <div class="d-flex align-items-center">
                                            <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2) ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['transactionID'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage) ?>
                                        </div>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['transactionID'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?php if (isset($row['type']))
                                        echo htmlspecialchars((string) $row['type'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($usr['name']))
                                        echo htmlspecialchars((string) $usr['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['date']))
                                        echo htmlspecialchars((string) $row['date'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row3['name']))
                                        echo htmlspecialchars((string) $row3['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row2['unit']))
                                        echo htmlspecialchars((string) $row2['unit'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['amount']))
                                        echo htmlspecialchars((string) $row['amount'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['prev_amt']))
                                        echo htmlspecialchars((string) $row['prev_amt'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['final_amt']))
                                        echo htmlspecialchars((string) $row['final_amt'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['description']))
                                        echo htmlspecialchars((string) $row['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['remark']))
                                        echo htmlspecialchars((string) $row['remark'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['attachment']))
                                        echo htmlspecialchars((string) $row['attachment'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                            <?php }
                        } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Transaction ID</th>
                            <th scope="col">Type</th>
                            <th scope="col">PIC</th>
                            <th scope="col">Date</th>
                            <th scope="col">Bank</th>
                            <th scope="col">Currency</th>
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
    const page = "<?= $pageTitle ?>";
    const action = "<?php echo isset($act) ? $act : ' '; ?>";

    checkCurrentPage(page, action);
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
    datatableAlignment('cash_on_hand_trans_table');
</script>

</html>
