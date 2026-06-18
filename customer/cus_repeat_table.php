<?php
$pageTitle = "Customer Repeat";
$currentPagePin = 143;


include_once '../include/list_page_header.php';

$tblName = CUS_REPEAT;

$redirectPage = $SITEURL . '/customer/cus_repeat.php';
$deleteRedirectPage = $SITEURL . '/customer/cus_repeat_table.php';

$result = getData('*', '', '', $tblName, $connect);
$amountCountMap = customerLabelGetLabelCountMap($connect, 'repeat');
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script src="<?= $SITEURL ?>/js/list_page_common.js"></script>

<body>
    

    <div class="page-load-cover">
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
                                        <?php echo $pageTitle ?> </a>
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
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Name</th>
                                <th scope="col">Customer Count</th>
                                <th scope="col">Color Segmentation</th>
                                <th scope="col">Order Frequency From</th>
                                <th scope="col">Order Frequency Until</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                    <tr>
                                        <th class="hideColumn" scope="row"><?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                        <th scope="row"><?= $num++; ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['colorCode'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                        </td>
                                        <td scope="row"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row">
                                            <?php
                                            $amountCount = isset($amountCountMap[(int) $row['id']]) ? (int) $amountCountMap[(int) $row['id']] : 0;
                                            $breakdownUrl = customerLabelBuildBreakdownUrl('repeat', (int) $row['id']);
                                            if ($breakdownUrl !== '') {
                                                echo '<a href="' . htmlspecialchars($breakdownUrl, ENT_QUOTES, 'UTF-8') . '">' . $amountCount . '</a>';
                                            } else {
                                                echo $amountCount;
                                            }
                                            ?>
                                        </td>
                                        <td scope="row"><?php if (isset($row['colorCode'])) { ?><input type="color"
                                                    value="<?= htmlspecialchars((string) $row['colorCode'], ENT_QUOTES, 'UTF-8') ?>" disabled><?php } ?></td>
                                        <td scope="row"><?php if (isset($row['orderFrequencyFrom'])) echo htmlspecialchars((string) $row['orderFrequencyFrom'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['orderFrequencyUntil'])) echo htmlspecialchars((string) $row['orderFrequencyUntil'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?php if (isset($row['remark'])) echo htmlspecialchars((string) $row['remark'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Name</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Color Segmentation</th>
                                <th scope="col">Order Frequency From</th>
                                <th scope="col">Order Frequency Until</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('table');
        setButtonColor();
    </script>
</body>

</html>
