<?php
$pageTitle = "Tag";
$currentPagePin = 35;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = TAG;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/tag.php';
$deleteRedirectPage = $SITEURL . '/tag_table.php';

$result = getData('*', "status = 'A'", '', $tblName, $connect);

$tagCustomerCountMap = array();
$platformDisplayMap = array(
    'shopee' => 'Shopee',
    'lazada' => 'Lazada',
    'facebook' => 'Facebook',
    'website' => 'Website',
    'customer_info' => 'Customer Info',
);
$assignmentCountSql = "SELECT
    tag_id,
    LOWER(TRIM(platform)) AS platform,
    COUNT(DISTINCT CONCAT(LOWER(TRIM(platform)), ':', customer_id)) AS platform_customer_count
    FROM `" . CUS_TAG_ASSIGNMENT . "`
    WHERE status = 'A'
    GROUP BY tag_id, LOWER(TRIM(platform))";
$countResult = mysqli_query($connect, $assignmentCountSql);

if ($countResult instanceof mysqli_result) {
    while ($countRow = $countResult->fetch_assoc()) {
        $tagId = isset($countRow['tag_id']) ? (int) $countRow['tag_id'] : 0;
        $platformKey = isset($countRow['platform']) ? (string) $countRow['platform'] : '';
        $platformCount = isset($countRow['platform_customer_count']) ? (int) $countRow['platform_customer_count'] : 0;

        if ($tagId <= 0 || !isset($platformDisplayMap[$platformKey])) {
            continue;
        }

        if (!isset($tagCustomerCountMap[$tagId])) {
            $tagCustomerCountMap[$tagId] = array(
                'total' => 0,
                'platforms' => array_fill_keys(array_keys($platformDisplayMap), 0),
            );
        }

        $tagCustomerCountMap[$tagId]['platforms'][$platformKey] = $platformCount;
        $tagCustomerCountMap[$tagId]['total'] += $platformCount;
    }
    $countResult->free();
}

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
<script>
    preloader(300);
</script>

<style>
    

    
</style>

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
                                <th scope="col">Total Assigned Customers</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                    <?php
                                    $tagId = (int) $row['id'];
                                    $assignedCustomerCountData = isset($tagCustomerCountMap[$tagId]) ? $tagCustomerCountMap[$tagId] : array(
                                        'total' => 0,
                                        'platforms' => array_fill_keys(array_keys($platformDisplayMap), 0),
                                    );
                                    $assignedCustomerCount = isset($assignedCustomerCountData['total']) ? (int) $assignedCustomerCountData['total'] : 0;
                                    $platformBreakdownParts = array();
                                    foreach ($platformDisplayMap as $platformKey => $platformLabel) {
                                        $platformBreakdownParts[] = $platformLabel . ': ' . (int) $assignedCustomerCountData['platforms'][$platformKey];
                                    }
                                    $platformBreakdownText = implode(' | ', $platformBreakdownParts);
                                    ?>
                                    <tr>
                                        <th class="hideColumn" scope="row"><?= (int) $row['id'] ?></th>
                                        <th scope="row"><?= (int) $num++; ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                        </td>
                                        <td scope="row"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row">
                                            <?= (int) $assignedCustomerCount ?><br>
                                            <?= htmlspecialchars($platformBreakdownText, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td scope="row"><?php if (isset($row['remark']))
                                            echo htmlspecialchars((string) $row['remark'], ENT_QUOTES, 'UTF-8') ?></td>
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
                                <th scope="col">Total Assigned Customers</th>
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
    </script>
</body>

</html>
