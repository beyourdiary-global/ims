<?php
$pageTitle = "Warehouse";
$currentPagePin = 16;


include_once '../include/list_page_header.php';

$tblName = WHSE;

$redirectPage = $SITEURL . '/stock/warehouse.php';
$deleteRedirectPage = $SITEURL . '/stock/warehouse_table.php';
$stockBalancePage = $SITEURL . '/stock/warehouse.php';
$warehouseTokenColumnAvailable = function_exists('commonWarehouseTelegramTokenColumnExists')
    ? commonWarehouseTelegramTokenColumnExists($connect)
    : false;
$warehouseTelegramTokenNameMap = function_exists('shopeeOmsLoadTokenSettingNameMap')
    ? shopeeOmsLoadTokenSettingNameMap($connect)
    : array();

$warehousesWithStock = array();
$stockRst = mysqli_query(
    $finance_connect,
    "SELECT DISTINCT o.warehouse_id
     FROM `stock_in_order` o
     INNER JOIN `stock_in_order_item` i ON i.stock_in_order_id = o.id AND i.status = 'A'
     WHERE o.status = 'A'"
);
if ($stockRst) {
    while ($stockRow = mysqli_fetch_assoc($stockRst)) {
        $wid = isset($stockRow['warehouse_id']) ? (int) $stockRow['warehouse_id'] : 0;
        if ($wid > 0) {
            $warehousesWithStock[$wid] = true;
        }
    }
}

$result = getData('*', '', '', $tblName, $connect);

// if (!$result) {
//     echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
//     echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
// }
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
                <?php if (!$result) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else { ?>
                    <div class="table-scroll-wrap mobile-scroll-wrap">
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="220px">Action</th>
                                <th scope="col">Name</th>
                                <th scope="col">Telegram Notification Bot</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                    <tr>
                                        <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                        <th scope="row"><?= $num++; ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], '', $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                            <?php if (isActionAllowed("View", $pinAccess) && isset($warehousesWithStock[(int) $row['id']])) { ?>
                                                <a class="btn btn-sm btn-rounded btn-primary" href="<?= $stockBalancePage . '?view=stock_balance&id=' . (int) $row['id'] ?>">View Stock Balance</a>
                                            <?php } ?>
                                        </td>
                                        <td scope="row"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row">
                                            <?php
                                            $telegramTokenSettingId = $warehouseTokenColumnAvailable && isset($row['telegram_token_setting_id'])
                                                ? (int) $row['telegram_token_setting_id']
                                                : 0;
                                            echo htmlspecialchars(
                                                $telegramTokenSettingId > 0 && isset($warehouseTelegramTokenNameMap[$telegramTokenSettingId])
                                                    ? (string) $warehouseTelegramTokenNameMap[$telegramTokenSettingId]
                                                    : '-',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </td>
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
                                <th scope="col" id="action_col" width="220px">Action</th>
                                <th scope="col">Name</th>
                                <th scope="col">Telegram Notification Bot</th>
                            </tr>
                        </tfoot>
                    </table>
                    </div><?php } ?>
            </div>
        </div>
    </div>

    <script>
        //Initial Page And Action Value
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        //to solve the issue of dropdown menu displaying inside the table when table class include table-responsive
        dropdownMenuDispFix();
        //to resize table with bootstrap 5 classes
        datatableAlignment('table');
        setButtonColor();
    </script>
</body>

</html>
