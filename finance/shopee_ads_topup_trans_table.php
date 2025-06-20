<?php
ob_start();
$pageTitle = "Shopee Ads Top Up Transaction";
$isFinance = 1;
include '../menuHeader.php';
include '../checkCurrentPagePin.php';

require_once '../header/PhpXlsxGenerator/PhpXlsxGenerator.php';
$fileName = date('Y-m-d H:i:s') . "_list.xlsx";
$img_path = '../' . img_server . 'finance/shopee_ads_topup_trans/';

$tempDir = '../' . img_server . "temp/";
$tempAttachDir = $tempDir . "attachment/";
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
if (!file_exists($tempAttachDir)) {
    mkdir($tempAttachDir, 0777, true);
}

$checkboxValues = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';

// Check if any checkboxes are checked
if (!empty($checkboxValues)) {
    setcookie('rowID', '', time() - 3600, '/');
    // Defining column names
    $excelData = array(
        array('S/N', 'SHOPEE ACCOUNT','ORDER ID','DATETIME','CURRENCY UNIT', 'SUBTOTAL','GST(%)','PAYMENT METHOD','REMARK','CREATE BY', 'CREATE DATE', 'CREATE TIME', 'UPDATE BY', 'UPDATE DATE', 'UPDATE TIME')
    );    
    // Get the data from the database using the WHERE clause
    $query2 = $finance_connect->query("SELECT * FROM " . SHOPEE_ADS_TOPUP . " WHERE status = 'A' AND id IN ($checkboxValues) ORDER BY shopee_acc ASC, orderID ASC, payment_date ASC, currency ASC, topup_amt ASC,subtotal ASC,gst ASC, pay_meth ASC");
   
    $excelRowNum = 1;
    if ($query2->num_rows > 0) {
        while ($row2 = $query2->fetch_assoc()) {
            // Initialize an empty array to store the row data
            $lineData = array();
            $lineData[] = $excelRowNum;

            if (isset($row2['attachment']) && !empty($row2['attachment'])) {
                $attachmentSourcePath = $img_path . $row2['attachment'];
                if (file_exists($attachmentSourcePath)) {
                    $attachmentCreationDate = strtotime($row2['create_date']);
                    $yearMonthFolder = $tempAttachDir . date('Y', $attachmentCreationDate) . '/' . date('m', $attachmentCreationDate) . '/';
                    if (!file_exists($yearMonthFolder)) {
                        mkdir($yearMonthFolder, 0777, true);
                    }
                    $attachmentDestPath = $yearMonthFolder . $row2['attachment'];
                    copy($attachmentSourcePath, $attachmentDestPath);
                }
            }

            // Define the column names in the same order as in your database query
            $columnNames = array('shopee_acc' , 'orderID' , 'payment_date' , 'currency' , 'topup_amt' ,'subtotal' ,'gst' , 'pay_meth' ,'remark','create_by', 'create_date', 'create_time', 'update_by', 'update_date', 'update_time');

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
            header('Content-Disposition: attachment; filename="' .$zipFile .'"');
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

function addDirToZip($dir, $zip, $basePath)
{
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $filePath = $dir . $file;
        if (is_file($filePath)) {
            // Add the file to the zip archive with a relative path
            $relativePath = str_replace($basePath, '', $filePath);
            $zip->addFile($filePath, $relativePath);
        } elseif (is_dir($filePath)) {
            // Add the directory to the zip archive
            $zip->addEmptyDir(str_replace($basePath, '', $filePath));
            // Recursively add files and directories inside the current directory
            addDirToZip($filePath . '/', $zip, $basePath);
        }
    }
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) {
        return;
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

$pinAccess = checkCurrentPin($connect, $pageTitle);
$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['searchChk'] = '';
unset($_SESSION['resetChk']);
$_SESSION['delChk'] = '';
$num = 1;   // numbering
$deleteRedirectPage = $SITEURL . '/shopee_ads_topup_trans_table.php';
$redirect_page = $SITEURL . '/finance/shopee_ads_topup_trans.php';
$result = getData('*', '', '', SHOPEE_ADS_TOPUP, $finance_connect);
$tblName = SHOPEE_ADS_TOPUP;

// Initialize total variables
$totalTopupAmount = 0;
$totalSubtotal = 0;
$totalGST = 0;
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    $(document).ready(() => {
        createSortingTable('shopee_ads_topup_trans_table');
    });
</script>

<body>

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
                                <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Transaction </a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-rounded btn-primary" name="exportBtn" id="addBtn" onclick="captureAndExport('<?php echo $tblName; ?>')"><i class="fa-solid fa-file-export"></i> Export</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3 dateFilters">
                    <label for="timeInterval" class="form-label">Filter by:</label>
                    <select class="form-select" id="timeInterval">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="col-md-4 dateFilters">
                    <label for="dateFilter" class="form-label">Filter by Date:</label>
                    <div class="input-group date" id="datepicker"> 
                        <input type="text" class="form-control" placeholder="Select date" autocomplete="off">
                        <div class="input-group-addon">
                            <span class="glyphicon glyphicon-th"></span>
                        </div>
                    </div>
                    <div class="input-daterange input-group" id="datepicker2" style="display: none;">
                        <input type="text" class="input form-control" name="start" placeholder="Start date" autocomplete="off"/>
                        <span class="input-group-addon date-separator"> to </span>
                        <input type="text" class="input-sm form-control" name="end" placeholder="End date" autocomplete="off"/>
                    </div>
                    <div class="input-group input-daterange" id="datepicker3" style="display: none;">
                        <input type="text" class="input form-control" name="start" placeholder="Start month" autocomplete="off"/>
                        <span class="input-group-addon date-separator"> to </span>
                        <input type="text" class="input-sm form-control" name="end" placeholder="End month" autocomplete="off"/>
                    </div>
                    <div class="input-group input-daterange" id="datepicker4" style="display: none;">
                        <input type="text" class="input form-control" name="start" placeholder="Start year" autocomplete="off"/>
                        <span class="input-group-addon date-separator"> to </span>
                        <input type="text" class="input-sm form-control" name="end" placeholder="End year" autocomplete="off"/>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Group by:</label>
                    <select class="form-select" id="group" placeholder="Select a Group">
                        <option value="">Select a Group</option>
                        <option value="shopee">Shopee Account</option>
                        <option value="currency">Currency</option>
                        <option value="method">Payment Method</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-center justify-content-center">
                    <a id='resetButton' href="../reset.php?redirect=finance/shopee_ads_topup_trans_table.php" class="btn btn-sm btn-rounded btn-primary"> <i class="fa fa-refresh"></i> Reset </a>
                </div>
            </div>
            <table class="table table-striped" id="shopee_ads_topup_trans_table">
                <thead>
                    <tr>
                    <?php if (!isset($_GET['group'])): ?>
                        <th class="hideColumn" scope="col">ID</th>
                        <th class="text-center">
                            <input type="checkbox" class="exportAll">
                        </th>
                        <th scope="col" width="60px">S/N</th>
                        <th scope="col" id="action_col">Action</th>
                        <th scope="col">Shopee Account</th>
                        <th scope="col">Order ID</th>
                        <th scope="col">DateTime</th>
                        <th scope="col">Currency</th>
                        <th scope="col">Top-up Amount</th>
                        <th scope="col">Subtotal</th>
                        <th scope="col">GST (%)</th>
                        <th scope="col">Payment Method</th>
                        <th scope="col">Remark</th>
                    <?php else: ?>
                        <th class="hideColumn" scope="col">ID</th>
                        <th class="text-center">
                            <input type="checkbox" class="exportAll">
                        </th>
                        <th scope="col" width="60px">S/N</th>                       
                        <th id="group_header" scope="col">
                            <?php 
                                if (isset($_GET['group'])) {
                                    if ($_GET['group'] == 'shopee') {
                                        echo "Shopee Account";
                                    } elseif ($_GET['group'] == 'currency') {
                                        echo "Currency";
                                    } elseif ($_GET['group'] == 'method') {
                                        echo "Payment Method";
                                    }
                                }
                            ?>
                        </th>
                        <th scope="col">Total Top-up Amount</th>
                    <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $groupOption = isset($_GET['group']) ? $_GET['group'] : ''; 
                    $groupOption3 = isset($_GET['timeRange']) ? $_GET['timeRange'] : ''; 
                    $groupOption4 = isset($_GET['timeInterval']) ? $_GET['timeInterval'] : ''; 
                    $groupedRows = [];
                    $counters = 1;

                    while ($row = $result->fetch_assoc()) {
                        $viewActMsg = '';
                        $sql = '';
                        if (isset($row['orderID'], $row['id']) && !empty($row['orderID'])) {
                            $q1 = getData('*', "id='" . $row['shopee_acc'] . "'", 'LIMIT 1', SHOPEE_ACC, $finance_connect);
                            $shopee_acc = $q1->fetch_assoc();
                            $q2 = getData('unit', "id='" . $row['currency'] . "'", 'LIMIT 1', CUR_UNIT, $connect);
                            $currs = $q2->fetch_assoc();
                            $q3 = getData('name', "id='" . $row['pay_meth'] . "'", 'LIMIT 1', FIN_PAY_METH, $finance_connect);
                            $pay = $q3->fetch_assoc();

                            $shopee = isset($shopee_acc['name']) ? $shopee_acc['name'] : '';
                            $curr = isset($currs['unit']) ? $currs['unit'] : '';
                            $method = isset($pay['name']) ? $pay['name'] : '';
                            $paymentDate = $row['payment_date'];
                        }

                        // Add to totals
                        $totalTopupAmount += isset($row['topup_amt']) ? $row['topup_amt'] : 0;
                        $totalSubtotal += isset($row['subtotal']) ? $row['subtotal'] : 0;
                        $totalGST += isset($row['gst']) ? $row['gst'] : 0;

                        if ($groupOption == '') {
                            echo '<tr>
                            <th class="hideColumn" scope="row">' . $row['id'] . '</th>
                            <th class="text-center"><input type="checkbox" class="export" value="'  . $row['id'] . '"></th>
                            <th scope="row">' . $num++ . '</th>
                            <td scope="row" class="btn-container">
                            <div class="d-flex align-items-center">' 
                            ?>
                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess);?>
                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2) ?>
                            <?php renderDeleteButton($pinAccess, $row['id'], $row['shopee_acc'], $row['orderID'], $pageTitle, $redirect_page, $deleteRedirectPage) ?>
                            <?php echo'</div>
                            </td>
                            <td scope="row">' . (isset($shopee_acc['name']) ? $shopee_acc['name'] : '') . '</td>
                            <td scope="row">' . $row['orderID'] . '</td>
                            <td scope="row">' . (isset($row['payment_date']) ? $row['payment_date'] : '') . '</td>
                            <td scope="row">' . (isset($currs['unit']) ? $currs['unit'] : '') . '</td>
                            <td scope="row">' . (isset($row['topup_amt']) ? $row['topup_amt'] : '') . '</td>
                            <td scope="row">' . (isset($row['subtotal']) ? $row['subtotal'] : '') . '</td>
                            <td scope="row">' . (isset($row['gst']) ? $row['gst'] : '') . '</td>
                            <td scope="row">' . (isset($pay['name']) ? $pay['name'] : '') . '</td>
                            <td scope="row">' . (isset($row['remark']) ? $row['remark'] : '') . '</td>
                            </tr>';
                        }
                    }
                    ?>      
                </tbody>
                <tfoot>
                <tr>
                    <?php if (!isset($_GET['group'])): ?>
                        <th class="hideColumn" scope="col">ID</th>
                        <th class="text-center">
                            <input type="checkbox" class="exportAll">
                        </th>
                        <th scope="col" width="60px">S/N</th>
                        <th scope="col" id="action_col">Action</th>
                        <th scope="col">Shopee Account</th>
                        <th scope="col">Order ID</th>
                        <th scope="col">DateTime</th>
                        <th scope="col">Currency</th>
                        <th scope="col">Top-up Amount</th>
                        <th scope="col">Subtotal</th>
                        <th scope="col">GST (%)</th>
                        <th scope="col">Payment Method</th>
                        <th scope="col">Remark</th>
                    <?php else: ?>
                        <th class="hideColumn" scope="col">ID</th>
                        <th class="text-center">
                            <input type="checkbox" class="exportAll">
                        </th>
                        <th scope="col" width="60px">S/N</th>                       
                        <th id="group_header" scope="col">
                            <?php 
                                if (isset($_GET['group'])) {
                                    if ($_GET['group'] == 'shopee') {
                                        echo "Shopee Account";
                                    } elseif ($_GET['group'] == 'currency') {
                                        echo "Currency";
                                    } elseif ($_GET['group'] == 'method') {
                                        echo "Payment Method";
                                    }
                                }
                            ?>
                        </th>
                        <th scope="col">Total Top-up Amount</th>
                    <?php endif; ?>
                </tr>
                <tr>
                    <th colspan="8" class="text-end">Total:</th>
                    <th scope="col"><?php echo number_format($totalTopupAmount, 2, '.', ''); ?></th>
                    <th scope="col"><?php echo number_format($totalSubtotal, 2, '.', ''); ?></th>
                    <th scope="col"><?php echo number_format($totalGST, 2, '.', ''); ?></th>
                    <th scope="col"></th>
                </tr>
                </tfoot>
            </table>
        </div>

    </div>

</body>

<script>
<?php include "../js/fb_ads_topup_table.js" ?>
<?php include "../js/shopee_ads_topup_trans_table.js" ?>

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
    datatableAlignment('shopee_ads_topup_trans_table');
</script>

</html>