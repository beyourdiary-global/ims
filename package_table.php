<?php
ob_start();
$pageTitle = "Package";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$libPath = __DIR__ . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
if (is_readable($libPath)) {
    require_once $libPath;
}

$tblName = PKG;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/package.php';
$deleteRedirectPage = $SITEURL . '/package_table.php';

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
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/package_table.php';</script>";
        exit;
    }

    $excelData = array();
    $query = "SELECT * FROM " . PKG . " WHERE status='A' AND id IN ($checkboxValues) ORDER BY id ASC";
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
                $line[] = isset($row[$key]) && $row[$key] !== null ? (string) $row[$key] : '';
            }
            $excelData[] = $line;
        }
    }

    setcookie('rowID', '', time() - 3600, '/');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (count($excelData) <= 1) {
        echo "<script>alert('No selected package data found to export.');window.location.href='package_table.php';</script>";
        exit;
    }

    if (!class_exists('\CodexWorld\PhpXlsxGenerator')) {
         echo "<script>alert('The export library is not available. Please contact the administrator.');window.location.href='package_table.php';</script>";
         exit;
     }

    $filename = 'package_data_' . date('Y-m-d') . '.xlsx';
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit;
}

$result = getData('*', '', '', $tblName, $connect);

// Ensure the query was successful
if (!$result) {
    $result = [];
} elseif ($result->num_rows == 0) {
    $result = [];
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
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> 
                            <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo $pageTitle ?></h2>
                            <div class="mt-auto mb-auto d-flex gap-2">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                       href="<?= $redirect_page . "?act=" . $act_1 ?>">
                                        <i class="fa-solid fa-plus"></i> Add <?php echo $pageTitle ?>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (isActionAllowed("Import", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-info text-white" id="addBtn" href="package_import.php">
                                        <i class="fa-solid fa-file-import"></i> Import
                                    </a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Export", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" name="exportBtn" href="package_table.php">
                                        <i class="fa-solid fa-file-export"></i> Export
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($result)) { ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php } else { ?>
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn">ID</th>
                                <th class="text-center"><input type="checkbox" class="exportAll"></th>
                                <th>S/N</th>
                                <th id="action_col">Action</th>
                                <th>Name</th>
                                <th>Item Code</th>
                                <th>Item Description</th>
                                <th>Price</th>
                                <th>Brand</th>
                                <th>Cost</th>
                                <th>Agent Cost</th>
                                <th>Product Quantity</th>
                                <th>Remark</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                if (empty($row['name']) || empty($row['id'])) {
                                    continue; // Skip invalid rows
                                }
                                ?>
                                <tr>
                                    <th class="hideColumn"><?= $row['id'] ?></th>
                                    <td class="text-center"><input type="checkbox" class="export" value="<?= (int) $row['id'] ?>"></td>
                                    <th><?= $num++ ?></th>
                                    <td class="btn-container">
                                        <?php if (isActionAllowed("View", $pinAccess)): ?>
                                            <a class="btn btn-primary me-1" href="<?= $redirect_page . "?id=" . $row['id'] ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (isActionAllowed("Edit", $pinAccess)): ?>
                                            <a class="btn btn-warning me-1" href="<?= $redirect_page . "?id=" . $row['id'] . '&act=' . $act_2 ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (isActionAllowed("Delete", $pinAccess)): ?>
                                            <a class="btn btn-danger"
                                               onclick="confirmationDialog('<?= $row['id'] ?>',['<?= $row['name'] ?>','<?= $row['remark'] ?>'],'<?php echo $pageTitle ?>','<?= $redirect_page ?>','<?= $deleteRedirectPage ?>','D')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars(isset($row['item_code']) ? $row['item_code'] : '') ?></td>
                                    <td><?= htmlspecialchars(isset($row['item_description']) ? $row['item_description'] : '') ?></td>
                                    <td>
                                        <?php
                                        $resultCurUnit = getData('unit', "id='" . $row['currency_unit'] . "'", '', CUR_UNIT, $connect);
                                        $rowCurUnit = ($resultCurUnit && $resultCurUnit->num_rows > 0) ? $resultCurUnit->fetch_assoc() : null;
                                        echo $rowCurUnit ? $rowCurUnit['unit'] . ' ' . $row['price'] : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $resultBrand = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                                        $rowBrand = ($resultBrand && $resultBrand->num_rows > 0) ? $resultBrand->fetch_assoc() : null;
                                        echo $rowBrand ? $rowBrand['name'] : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $resultCurUnit2 = getData('unit', "id='" . $row['cost_curr'] . "'", '', CUR_UNIT, $connect);
                                        $rowCurUnit2 = ($resultCurUnit2 && $resultCurUnit2->num_rows > 0) ? $resultCurUnit2->fetch_assoc() : null;
                                        echo $rowCurUnit2 ? $rowCurUnit2['unit'] . ' ' . $row['cost'] : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        echo $row['agent_cost']?'RM'. $row['agent_cost']:'';
                                        ?>
                                    </td>
                                    <td>
                                        <?= isset($row['product']) ? count(explode(",", $row['product'])) : '0' ?>
                                    </td>
                                    <td width="25%">
                                        <?php
                                        echo $row['remark']?'RM'. $row['remark']:'';
                                        ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th class="hideColumn">ID</th>
                                <th class="text-center"><input type="checkbox" class="exportAll"></th>
                                <th>S/N</th>
                                <th>Action</th>
                                <th>Name</th>
                                <th>Item Code</th>
                                <th>Item Description</th>
                                <th>Price</th>
                                <th>Brand</th>
                                <th>Cost</th>
                                <th>Agent Cost</th>
                                <th>Product Quantity</th>
                                <th>Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ''; ?>";
        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('table');
        setButtonColor();
    </script>
    <script src="<?= $SITEURL ?>/js/package_table.js"></script>
</body>
</html>
