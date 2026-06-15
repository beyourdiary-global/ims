<?php
$pageTitle = "Customer Level";
$currentPagePin = 142;

include_once 'include/list_page_header.php';

$tblName = CUS_LEVEL;

$redirect_page = $SITEURL . '/cus_level.php';
$deleteRedirectPage = $SITEURL . '/cus_level_table.php';

$result = getData('*', '', '', $tblName, $connect);
$amountCountMap = customerLabelGetLabelCountMap($connect, 'level');
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<script>
    preloader(300);

    $(document).ready(() => {
        createSortingTable('table');
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
</style>

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
                                class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                        href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
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
                                <th scope="col">Purchase Amount From</th>
                                <th scope="col">Purchase Amount Until</th>
                                <th scope="col">Currency</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                $currencyRow = null;
                                if (!empty($row['currency'])) {
                                    $currencyResult = getData('unit', "id='" . $row['currency'] . "'", '', CUR_UNIT, $connect);
                                    if ($currencyResult) {
                                        $currencyRow = $currencyResult->fetch_assoc();
                                    }
                                }
                                if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                    <tr>
                                        <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                        <th scope="row"><?= $num++; ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['colorCode'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                        </td>
                                        <td scope="row"><?= $row['name'] ?></td>
                                        <td scope="row">
                                            <?php
                                            $amountCount = isset($amountCountMap[(int) $row['id']]) ? (int) $amountCountMap[(int) $row['id']] : 0;
                                            $breakdownUrl = customerLabelBuildBreakdownUrl('level', (int) $row['id']);
                                            if ($breakdownUrl !== '') {
                                                echo '<a href="' . htmlspecialchars($breakdownUrl, ENT_QUOTES, 'UTF-8') . '">' . $amountCount . '</a>';
                                            } else {
                                                echo $amountCount;
                                            }
                                            ?>
                                        </td>
                                        <td scope="row"><?php if (isset($row['colorCode'])) { ?><input type="color"
                                                    value="<?= $row['colorCode'] ?>" disabled><?php } ?></td>
                                        <td scope="row"><?php if (isset($row['purchaseAmountFrom'])) echo $row['purchaseAmountFrom'] ?></td>
                                        <td scope="row"><?php if (isset($row['purchaseAmountUntil'])) echo $row['purchaseAmountUntil'] ?></td>
                                        <td scope="row"><?php if (isset($currencyRow['unit'])) echo $currencyRow['unit'] ?></td>
                                        <td scope="row"><?php if (isset($row['remark'])) echo $row['remark'] ?></td>
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
                                <th scope="col">Purchase Amount From</th>
                                <th scope="col">Purchase Amount Until</th>
                                <th scope="col">Currency</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('table');
        setButtonColor();
    </script>
</body>

</html>
