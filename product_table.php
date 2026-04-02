<?php
ob_start();
$pageTitle = "Product";
$currentPagePin = 20;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = PROD;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/product.php';
$deleteRedirectPage = $SITEURL . '/product_table.php';

$checkboxValues = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';
if (!empty($checkboxValues)) {
    $checkboxValues = preg_replace('/[^0-9,]/', '', (string) $checkboxValues);
    $ids = array_filter(explode(',', $checkboxValues), 'strlen');
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids, function ($v) {
        return $v > 0;
    });
    $checkboxValues = implode(',', $ids);
}

if (!empty($checkboxValues)) {
    if (!isActionAllowed("Export", $pinAccess)) {
        setcookie('rowID', '', time() - 3600, '/');
        ob_end_clean();
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/product_table.php';</script>";
        exit;
    }

    if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
        include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
    }

    $excelData = array();
    $brandMap = array();
    $weightMap = array();
    $currencyMap = array();
    $categoryMap = array();
    $parentMap = array();
    $userMap = array();

    $brandRst = mysqli_query($connect, "SELECT id, name FROM " . BRAND . " WHERE status='A'");
    if ($brandRst) {
        while ($b = mysqli_fetch_assoc($brandRst)) {
            $brandMap[(int) $b['id']] = (string) $b['name'];
        }
    }
    $weightRst = mysqli_query($connect, "SELECT id, unit FROM " . WGT_UNIT . " WHERE status='A'");
    if ($weightRst) {
        while ($w = mysqli_fetch_assoc($weightRst)) {
            $weightMap[(int) $w['id']] = (string) $w['unit'];
        }
    }
    $currencyRst = mysqli_query($connect, "SELECT id, unit FROM " . CUR_UNIT . " WHERE status='A'");
    if ($currencyRst) {
        while ($cu = mysqli_fetch_assoc($currencyRst)) {
            $currencyMap[(int) $cu['id']] = (string) $cu['unit'];
        }
    }
    $categoryRst = mysqli_query($connect, "SELECT id, name FROM " . PROD_CATEGORY . " WHERE status='A'");
    if ($categoryRst) {
        while ($pc = mysqli_fetch_assoc($categoryRst)) {
            $categoryMap[(int) $pc['id']] = (string) $pc['name'];
        }
    }
    $parentRst = mysqli_query($connect, "SELECT id, name FROM " . PROD . " WHERE status='A'");
    if ($parentRst) {
        while ($pp = mysqli_fetch_assoc($parentRst)) {
            $parentMap[(int) $pp['id']] = (string) $pp['name'];
        }
    }
    $userRst = mysqli_query($connect, "SELECT id, name FROM " . USR_USER . " WHERE status='A'");
    if ($userRst) {
        while ($usr = mysqli_fetch_assoc($userRst)) {
            $userMap[(int) $usr['id']] = (string) $usr['name'];
        }
    }
    $query = "SELECT * FROM " . PROD . " WHERE status='A' AND id IN ($checkboxValues) ORDER BY id ASC";
    $exportRst = mysqli_query($connect, $query);
    if ($exportRst) {
        $fields = mysqli_fetch_fields($exportRst);
        $exportKeys = array();
        $header = array();
        foreach ($fields as $field) {
            $fieldName = (string) $field->name;
            if (strtolower($fieldName) === 'status') {
                continue;
            }
            $exportKeys[] = $fieldName;
            if (strtolower($fieldName) === 'id') {
                $header[] = 'S/N';
            } else {
                $header[] = strtoupper(str_replace('_', ' ', $fieldName));
            }
        }
        $excelData[] = $header;

        while ($row = mysqli_fetch_assoc($exportRst)) {
            $line = array();
            foreach ($exportKeys as $key) {
                $value = isset($row[$key]) && $row[$key] !== null ? (string) $row[$key] : '';
                if ($key === 'brand') {
                    $id = (int) $value;
                    $value = isset($brandMap[$id]) ? $brandMap[$id] : $value;
                } else if ($key === 'weight_unit') {
                    $id = (int) $value;
                    $value = isset($weightMap[$id]) ? $weightMap[$id] : $value;
                } else if ($key === 'currency_unit') {
                    $id = (int) $value;
                    $value = isset($currencyMap[$id]) ? $currencyMap[$id] : $value;
                } else if ($key === 'product_category') {
                    $id = (int) $value;
                    $value = isset($categoryMap[$id]) ? $categoryMap[$id] : $value;
                } else if ($key === 'parent_product') {
                    $id = (int) $value;
                    $value = isset($parentMap[$id]) ? $parentMap[$id] : $value;
                } else if ($key === 'create_by' || $key === 'update_by') {
                    $id = (int) $value;
                    $value = isset($userMap[$id]) ? $userMap[$id] : $value;
                }
                $line[] = $value;
            }
            $excelData[] = $line;
        }
    }

    setcookie('rowID', '', time() - 3600, '/');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (count($excelData) <= 1) {
        echo "<script>alert('No selected product data found to export.');window.location.href='product_table.php';</script>";
        exit;
    }

    $filename = 'product_data_' . date('Y-m-d') . '.xlsx';
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit;
}

$result = getData('*', '', '', $tblName, $connect);

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
                            <div class="mt-auto mb-auto d-flex gap-2">
                                <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?php echo $pageTitle ?> </a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Import", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-info text-white" id="addBtn" href="product_import.php"><i class="fa-solid fa-file-import"></i> Import</a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Export", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" name="exportBtn" href="product_table.php"><i class="fa-solid fa-file-export"></i> Export</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table table-striped" id="table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Cost</th>
                            <th scope="col">Weight</th>
                            <th scope="col">Parent Product</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        while ($row = $result->fetch_assoc()) {
                            if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                    <td class="text-center"><input type="checkbox" class="export" value="<?= (int) $row['id'] ?>"></td>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], '', $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= $row['name'] ?></td>
                                    <td scope="row">
                                        <?php
                                        $currency_unit = '';
                                        if (!empty($row['currency_unit'])) {
                                            $resultCurUnit = getData('unit', "id='" . $row['currency_unit'] . "'", '', CUR_UNIT, $connect);
                                            if ($resultCurUnit && $resultCurUnit->num_rows > 0) {
                                                $currency_unit = $resultCurUnit->fetch_assoc()['unit'] . ' ';
                                            }
                                        }
                                        echo $currency_unit . (isset($row['cost']) ? $row['cost'] : '');
                                        ?>
                                    </td>
                                    <td scope="row">
                                        <?php
                                        $weight_unit = '';
                                        if (!empty($row['weight_unit'])) {
                                            $resultWeightUnit = getData('unit', "id='" . $row['weight_unit'] . "'", '', WGT_UNIT, $connect);
                                            if ($resultWeightUnit && $resultWeightUnit->num_rows > 0) {
                                                $weight_unit = ' ' . $resultWeightUnit->fetch_assoc()['unit'];
                                            }
                                        }
                                        echo (isset($row['weight']) ? $row['weight'] : '') . $weight_unit;
                                        ?>
                                    </td>
                                    <td scope="row">
                                        <?php
                                        if (!empty($row['parent_product'])) {
                                            $resultParentPrd = getData('name', "id='" . $row['parent_product'] . "'", '', $tblName, $connect);
                                            if ($resultParentPrd && $resultParentPrd->num_rows > 0) {
                                                echo $resultParentPrd->fetch_assoc()['name'];
                                            } else {
                                                echo $row['parent_product']; // Fallback if name is missing but ID exists
                                            }
                                        }
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
                            <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Cost</th>
                            <th scope="col">Weight</th>
                            <th scope="col">Parent Product</th>
                        </tr>
                    </tfoot>
                </table>
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
    <script src="<?= $SITEURL ?>/js/product_table.js"></script>
</body>

</html>