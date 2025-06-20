<?php
$pageTitle = "Pin Group";
$currentPagePin = 2;
include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include ROOT.'/include/access.php';


$tblName = PIN_GRP;

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/pin_group.php';
$deleteRedirectPage = $SITEURL . '/pin_group_table.php';

$result = getData('*', '', '', $tblName, $connect);


?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<script>
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
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?php echo $pageTitle ?> </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$result) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else { ?>
                <table class="table table-striped" id="table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Pins</th>
                            <th scope="col">Remark</th>
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
                                    <?php renderViewEditButtonByPin("1", $redirect_page, $row, $accessActionKey); ?>
                                    <?php renderViewEditButtonByPin("2", $redirect_page, $row, $accessActionKey, $act_2); ?>
                                    <?php renderDeleteButtonByPin($accessActionKey, $row['id'], $row['name'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= $row['name'] ?></td>
                                    <td scope="row">
                                        <?php
                                        if(isset($row['pins'])){
                                            $pin_arr = explode(",", $row['pins']);
                                            $pinname_arr = array();
                                            foreach ($pin_arr as $val) {
                                                if ($val === '') continue;
                                                $pinname_result = getData('name', "id='$val'", '', PIN, $connect);
                                                if (!$pinname_result) {
                                                    echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
                                                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                                                }
                                                $pinname_row = $pinname_result->fetch_assoc();
                                                if ($pinname_row) {
                                                    array_push($pinname_arr, $pinname_row['name']);
                                                } else {
                                                    $pinname_arr = '';
                                                }
                                            }
                                            if (!empty($pinname_arr)) {
                                                echo implodeWithComma($pinname_arr);
                                            }
                                        }
                                        ?>
                                    </td>
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
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Pins</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </tfoot>
                </table>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <script>
        //Initial Page And Action Value
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        //to solve the issue of dropdown menu displaying inside the table when table class include table-responsive
        dropdownMenuDispFix();
        //to resize table with bootstrap 5 classes
        datatableAlignment('table');
        setButtonColor();
        preloader(300);
    </script>

</body>

</html>