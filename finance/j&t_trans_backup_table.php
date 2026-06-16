<?php
ob_start();
$pageTitle = "J&T Transaction Backup Record";
$currentPagePin = 88;
$isFinance = 1;
include_once '../include/list_page_header.php';
require_once '../header/PhpXlsxGenerator/PhpXlsxGenerator.php';
$fileName = date('Y-m-d_H-i-s') . "_list.xlsx";
$img_path = '../' . img_server . 'finance/j&t_trans_backup/';
$tblName = 'jt_transaction_backup';


$tempDir = '../' . img_server . "temp/";
$tempAttachDir = $tempDir . "attachment/";
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
if (!file_exists($tempAttachDir)) {
    mkdir($tempAttachDir, 0777, true);
}

$checkboxValuesRaw = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';

// Sanitize checkbox values from cookie: allow only comma-separated integers
$checkboxIds = array();
if (!empty($checkboxValuesRaw)) {
    $parts = explode(',', $checkboxValuesRaw);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && ctype_digit($part)) {
            $checkboxIds[] = (int)$part;
        }
    }
}

// Check if any valid checkbox IDs are present
if (!empty($checkboxIds)) {
    setcookie('rowID', '', time() - 3600, '/');
    // Defining column names
    $excelData = array(
        array('S/N', 'INVOICE NUMBER', 'INVOICE DATE', 'INVOICE CURRENCY', 'TOTAL GST', 'TOTAL AMOUNT PAYABLE', 'ATTACHMENT', 'CREATE BY', 'CREATE DATE', 'CREATE TIME', 'UPDATE BY', 'UPDATE DATE', 'UPDATE TIME')
    );    
    // Get the data from the database using the WHERE clause
    $idList = implode(',', $checkboxIds);
    $query2 = $finance_connect->query("SELECT * FROM " . $tblName . " WHERE status = 'A' AND id IN ($idList) ORDER BY number ASC, date ASC");
    
    $excelRowNum = 1;
    if ($query2 && $query2->num_rows > 0) {
        while ($row2 = $query2->fetch_assoc()) {
            // Initialize an empty array to store the row data
            $lineData = array();
            $lineData[] = $excelRowNum;

            if (isset($row2['attachment']) && !empty($row2['attachment'])) {
                $attachmentRelPath = trim(str_replace('\\', '/', (string) $row2['attachment']), '/');
                if (strpos($attachmentRelPath, '/') !== false) {
                    if (strpos($attachmentRelPath, 'attachment/') === 0) {
                        $attachmentSourcePath = '../' . $attachmentRelPath;
                    } else {
                        $attachmentSourcePath = '../' . img_server . $attachmentRelPath;
                    }
                } else {
                    $attachmentSourcePath = $img_path . $attachmentRelPath;
                }
                if (file_exists($attachmentSourcePath)) {
                    if (strpos($attachmentRelPath, '/') !== false) {
                        $zipRelativePath = $attachmentRelPath;
                    } else {
                        $attachmentCreationDate = strtotime($row2['create_date']);
                        $zipRelativePath = date('Y', $attachmentCreationDate) . '/' . date('m', $attachmentCreationDate) . '/' . $attachmentRelPath;
                    }

                    $attachmentDestPath = $tempAttachDir . $zipRelativePath;
                    $attachmentDestDir = dirname($attachmentDestPath);
                    if (!file_exists($attachmentDestDir)) {
                        mkdir($attachmentDestDir, 0777, true);
                    }
                    copy($attachmentSourcePath, $attachmentDestPath);
                }
            }

            // Define the column names in the same order as in your database query
            $columnNames = array('number', 'date', 'currency', 'total_gst', 'total_amount', 'attachment', 'create_by', 'create_date', 'create_time', 'update_by', 'update_date', 'update_time');

            foreach ($columnNames as $columnName) {
                // Check if the value is null, if so, replace it with an empty string
                if ($columnName === 'create_by' || $columnName === 'update_by') {
                    $name = '';
                    $pic = getData('name', "id='" . $row2[$columnName] . "'", '', USR_USER, $connect);
                    if ($pic && $pic->num_rows > 0) {
                        $user = $pic->fetch_assoc();
                        $name = $user['name'];
                    }
                    $lineData[] = $name;
                } elseif ($columnName === 'create_date') {
                    // Modify create_date value as needed
                    $lineData[] = isset($row2[$columnName]) ? $row2[$columnName] : '';
                } else {
                    $lineData[] = isset($row2[$columnName]) ? $row2[$columnName] : '';
                }
            }
            $excelData[] = $lineData;
            $excelRowNum++;
        }
        $xlsx = CodexWorld\PhpXlsxGenerator::fromArray($excelData);
        // $xlsx->downloadAs($fileName);

        $tempExcelFilePath = $tempDir . $fileName;

        if ($tempExcelFilePath) {
            $xlsx->saveAs($tempExcelFilePath);
            $zipFile = date('Ymd_His') . ".zip";
            $zip = new ZipArchive();

            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                die("Failed to create zip file");
            }

            // Add the Excel file to the root of the zip archive
            $zip->addFile($tempExcelFilePath, basename($tempExcelFilePath));

            // Add the 'attachment' folder to the zip archive
            addDirToZip($tempAttachDir, $zip, $tempAttachDir);

            // Close the zip archive
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFile . '"');
            header('Content-Length: ' . filesize($zipFile));
            header('Pragma: no-cache');
            header('Expires: 0');
            ob_clean();
            readfile($zipFile);
            deleteDir($tempDir);


        }

    } else {
        echo 'Failed to create temporary Excel file';
    }
}

$redirect_page = $SITEURL . '/finance/j&t_trans_backup.php';
$deleteRedirectPage = $SITEURL . '/finance/j&t_trans_backup_table.php';
$import_page = $SITEURL . '/finance/j&t_trans_backup_import.php';

$result = getData('*', '', '', $tblName, $finance_connect);

$img_path = SITEURL . img_server . 'finance/j&t_trans_backup/';
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    $(document).ready(() => {
        let table = new DataTable("#jt_trans_backup_table", {
            paging: $("#jt_trans_backup_table tbody tr").length > 10,
            searching: $("#jt_trans_backup_table tbody tr").length > 10,
            /* info: false, */
            order: [[2, "asc"]], // 0 = db id column; 1 = numbering column
            /* responsive: true, */
            autoWidth: false,
            "columnDefs": [
                { "orderable": false, "targets": 0 } // Disabling sorting for the first column (index 0)
            ]
        })
    });
</script>

<body>

    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">

        <div class="col-12 col-md-11">

            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                            class="fa-solid fa-chevron-right fa-xs"></i>
                        <?php echo $pageTitle ?>
                    </p>
                </div>

                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2>
                            <?php echo $pageTitle ?>
                        </h2>
                        <div class="mt-auto mb-auto">
                            <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                    href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                    Transaction </a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Import", $pinAccess)): ?>
                                <a class="btn btn-sm btn-rounded btn-primary" name="importBtn" id="addBtn"
                                    href="<?= $import_page ?>"><i class="fa-solid fa-file-import"></i> Import</a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Export", $pinAccess)): ?>
                            <a class="btn btn-sm btn-rounded btn-primary" name="exportBtn" id="addBtn"
                                onclick="if (exportData()) { showExportNotification(); }"><i
                                    class="fa-solid fa-file-export"></i> Export</a>
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

                <table class="table table-striped" id="jt_trans_backup_table">
                    <thead>
                        <tr>
                            <th class="text-center">
                                <input type="checkbox" class="exportAll">
                            </th>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Invoice Number</th>
                            <th scope="col">Invoice Date</th>
                            <th scope="col">Invoice Currency</th>
                            <th scope="col">Total GST</th>
                            <th scope="col">Total Amount Payable</th>
                            <th scope="col">Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) {
                            if (isset($row['id']) && !empty($row['id'])) {
                                ?>

                                <tr>
                                    <th class="text-center">
                                        <input type="checkbox" class="export" value="<?= $row['id'] ?>">
                                    </th>
                                    <th class="hideColumn" scope="row">
                                        <?= $row['id'] ?>
                                    </th>
                                    <th scope="row">
                                        <?= $num++; ?>
                                    </th>
                                    <td scope="row" class="btn-container">
                                        <div class="d-flex align-items-center">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2) ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['number'], $row['date'], $pageTitle, $redirect_page, $deleteRedirectPage) ?>
                                        </div>
                                    </td>
                                    <td scope="row"><?php if (isset($row['number']))
                                        echo $row['number'] ?></td>

                                        <td scope="row"><?php if (isset($row['date']))
                                        echo $row['date'] ?></td>

                                        <td scope="row"><?php if (isset($row['currency']))
                                            echo $row['currency'] ?></td>

                                        <td scope="row"><?php if (isset($row['total_gst']))
                                            echo $row['total_gst'] ?></td>

                                        <td scope="row"><?php if (isset($row['total_amount']))
                                            echo $row['total_amount'] ?></td>

                                        <td scope="row">
                                        <?php
                                        if (isset($row['attachment']) && trim((string) $row['attachment']) !== '') {
                                            $attachmentRel = trim(str_replace('\\', '/', (string) $row['attachment']), '/');
                                            $attachmentLabel = basename($attachmentRel);
                                            $attachmentHref = (strpos($attachmentRel, '/') !== false)
                                                ? ((strpos($attachmentRel, 'attachment/') === 0)
                                                    ? (rtrim((string) SITEURL, '/') . '/' . ltrim($attachmentRel, '/'))
                                                    : (SITEURL . img_server . $attachmentRel))
                                                : ($img_path . $attachmentRel);
                                        ?>
                                            <a href="<?= $attachmentHref ?>" target="_blank">
                                                <?= htmlspecialchars((string) $attachmentLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php }
                        } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th class="text-center">
                                <input type="checkbox" class="exportAll">
                            </th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Invoice Number</th>
                            <th scope="col">Invoice Date</th>
                            <th scope="col">Invoice Currency</th>
                            <th scope="col">Total GST</th>
                            <th scope="col">Total Amount Payable</th>
                            <th scope="col">Attachment</th>

                        </tr>
                    </tfoot>
                </table>
            <?php } ?>
        </div>

    </div>

</body>
<script>
    //Initial Page And Action Value
    var page = "<?= $pageTitle ?>";
    var action = "<?php echo isset($act) ? $act : ' '; ?>";
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
    datatableAlignment('jt_trans_backup_table');
    <?php include '../js/j&t_trans_backup_table.js' ?>
</script>

</html>
