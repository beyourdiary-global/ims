<?php
$pageTitle = "Expense Type";
$currentPagePin = 49;

include_once '../include/list_page_header.php';


$redirectPage = $SITEURL . '/finance/expense_type.php';
$deleteRedirectPage = $SITEURL . '/finance/expense_type_table.php';
$result = getData('*', '', '', EXPENSE_TYPE, $finance_connect);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    $(document).ready(() => {
        createSortingTable('expense_type_table');
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
                                    Expense Type </a>
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
                <table class="table table-striped" id="expense_type_table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) {
                            if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?php if (isset($row['code']))
                                        echo htmlspecialchars((string) $row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['remark']))
                                        echo htmlspecialchars((string) $row['remark'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                            <?php }
                        } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Remark</th>
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
    datatableAlignment('expense_type_table');
</script>

</html>
