<?php
$pageTitle = "Token Setting";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$tblName = TOKEN_SETT;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if (function_exists('isStatusFieldAvailable') && !isStatusFieldAvailable($tblName, $connect)) {
    @mysqli_query($connect, "ALTER TABLE `" . $tblName . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A'");
}

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;

$redirect_page = $SITEURL . '/token_setting.php';
$deleteRedirectPage = $SITEURL . '/token_setting_table.php';

$result = false;
$hasRows = false;
if (function_exists('tableExists') && tableExists($tblName, $connect)) {
    $query = "SELECT * FROM `" . $tblName . "` WHERE status='A' ORDER BY id DESC";
    $result = mysqli_query($connect, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $hasRows = true;
    }
}

if (!$result) {
    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    preloader(300);

    $(document).ready(function () {
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
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirect_page . '?act=' . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?= $pageTitle ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table table-striped" id="table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="130px">Action</th>
                            <th scope="col">Name</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($hasRows) { ?>
                            <?php while ($row = $result->fetch_assoc()) {
                                if (isset($row['id'], $row['name']) && trim((string) $row['name']) !== '') { ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= (int) $row['id'] ?></th>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], '', $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php }
                            } ?>
                        <?php } ?>
                    </tbody>

                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="130px">Action</th>
                            <th scope="col">Name</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        var page = "<?= $pageTitle ?>";
        var action = "<?= isset($act) ? $act : ' ' ?>";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('table');
        setButtonColor();
    </script>
</body>

</html>
