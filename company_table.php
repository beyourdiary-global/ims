<?php
ob_start();
$pageTitle = "Company";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$tblName = COMPANY;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;

$redirect_page = $SITEURL . '/company.php';
$deleteRedirectPage = $SITEURL . '/company_table.php';
$import_page = $SITEURL . '/company_import.php';

$actionBtn = post('actionBtn');
$selectedExportIdsRaw = trim((string) post('export_ids'));
$selectedExportIds = '';
if ($selectedExportIdsRaw !== '') {
    $selectedExportIdsRaw = preg_replace('/[^0-9,]/', '', $selectedExportIdsRaw);
    $selectedIds = array_filter(explode(',', $selectedExportIdsRaw), 'strlen');
    $selectedIds = array_map('intval', $selectedIds);
    $selectedIds = array_filter($selectedIds, function ($v) {
        return $v > 0;
    });
    $selectedExportIds = implode(',', $selectedIds);
}

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

if ($actionBtn === 'exportData' && !empty($selectedExportIds)) {
    if (!isActionAllowed("Export", $pinAccess)) {
        ob_end_clean();
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/company_table.php';</script>";
        exit;
    }

    if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
        include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
    }

    $excelData = array();

    $query = "SELECT * FROM " . $tblName . " WHERE status='A' AND id IN (" . $selectedExportIds . ") ORDER BY id ASC";
    $exportRst = mysqli_query($connect, $query);
    if ($exportRst) {
        $dbFields = mysqli_fetch_fields($exportRst);
        $header = array('S/N');
        foreach ($dbFields as $fieldInfo) {
            if (strtolower((string) $fieldInfo->name) === 'status') {
                continue;
            }
            $header[] = strtoupper(str_replace('_', ' ', (string) $fieldInfo->name));
        }
        $excelData[] = $header;

        $sn = 1;
        while ($row = mysqli_fetch_assoc($exportRst)) {
            $line = array((string) $sn++);
            foreach ($dbFields as $fieldInfo) {
                $fieldName = (string) $fieldInfo->name;
                if (strtolower($fieldName) === 'status') {
                    continue;
                }
                $line[] = isset($row[$fieldName]) && $row[$fieldName] !== null ? (string) $row[$fieldName] : '';
            }
            $excelData[] = $line;
        }
    }

    $log = array(
        'log_act' => 'Export',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $selectedExportIds,
        'query_table' => $tblName,
        'act_msg' => USER_NAME . " exported company data [<b>ID = " . $selectedExportIds . "</b>] from <b><i>" . $tblName . " Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    );
    audit_log($log);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (count($excelData) <= 1) {
        echo "<script>alert('No selected company data found to export.');window.location.href='company_table.php';</script>";
        exit;
    }

    $filename = 'company_data_' . date('Y-m-d') . '.xlsx';
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit;
}

if ($actionBtn === 'exportData' && empty($selectedExportIds)) {
    echo "<script>alert('Please select data to export.');window.location.href='company_table.php';</script>";
    exit;
}

$result = mysqli_query($connect, "SELECT * FROM " . $tblName . " WHERE status='A' ORDER BY id DESC");

if (!$result) {
    echo "<script type='text/javascript'>alert('Unable to load Company records. Please try again later.');</script>";
    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
}

$sqlAccountMap = array();
$accRst = mysqli_query($connect, "SELECT id, name FROM " . SQL_ACC . " WHERE status='A'");
if ($accRst) {
    while ($acc = mysqli_fetch_assoc($accRst)) {
        $sqlAccountMap[(int) $acc['id']] = (string) $acc['name'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

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
                            <div class="mt-auto mb-auto d-flex gap-2">
                                <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?= $pageTitle ?></a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Import", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-info text-white" id="addBtn" href="<?= $import_page ?>"><i class="fa-solid fa-file-import"></i> Import</a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Export", $pinAccess)) : ?>
                                    <button class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" name="exportBtn" type="button"><i class="fa-solid fa-file-export"></i> Export</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <form id="exportForm" method="post" action="company_table.php" class="d-none">
                    <input type="hidden" name="actionBtn" value="exportData">
                    <input type="hidden" name="export_ids" id="export_ids" value="">
                </form>

                <table class="table table-striped" id="table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Company Name</th>
                            <th scope="col">Company Code</th>
                            <th scope="col">ID No</th>
                            <th scope="col">Phone 1</th>
                            <th scope="col">City</th>
                            <th scope="col">State</th>
                            <th scope="col">Country</th>
                            <th scope="col">SQL Account</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        while ($row = $result->fetch_assoc()) {
                            if (isset($row['name'], $row['id']) && !empty($row['name'])) {
                                $sqlAccId = (int) (isset($row['sql_account_id']) ? $row['sql_account_id'] : 0);
                                $sqlAccName = isset($sqlAccountMap[$sqlAccId]) ? $sqlAccountMap[$sqlAccId] : '';
                        ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                    <td class="text-center"><input type="checkbox" class="export" value="<?= (int) $row['id'] ?>"></td>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], isset($row['remark']) ? $row['remark'] : '', $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= isset($row['name']) ? htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['code']) ? htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['id_no']) ? htmlspecialchars($row['id_no'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['phone1']) ? htmlspecialchars($row['phone1'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['city']) ? htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['state']) ? htmlspecialchars($row['state'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= isset($row['country']) ? htmlspecialchars($row['country'], ENT_QUOTES, 'UTF-8') : '' ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $sqlAccName, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= isset($row['remark']) ? htmlspecialchars($row['remark'], ENT_QUOTES, 'UTF-8') : '' ?></td>
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
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Company Name</th>
                            <th scope="col">Company Code</th>
                            <th scope="col">ID No</th>
                            <th scope="col">Phone 1</th>
                            <th scope="col">City</th>
                            <th scope="col">State</th>
                            <th scope="col">Country</th>
                            <th scope="col">SQL Account</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.__COMPANY_TABLE_CONFIG = {
            page: "<?= $pageTitle ?>",
            action: "<?= isset($act) ? $act : '' ?>"
        };
    </script>
    <script src="<?= $SITEURL ?>/js/company_table.js"></script>
</body>
</html>
