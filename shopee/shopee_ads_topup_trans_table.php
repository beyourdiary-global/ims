<?php
ob_start();
$pageTitle = "Shopee Ads Top Up Transaction";
$isFinance = 1;
include '../menuHeader.php';
include '../checkCurrentPagePin.php';

checkCurrentPin($connect, $pageTitle);
$pinAccess = checkPin($connect, $pageTitle);

require_once '../header/PhpXlsxGenerator/PhpXlsxGenerator.php';
$fileName = date('Y-m-d_H-i-s') . "_list.xlsx";
$img_path = '../' . img_server . 'finance/shopee_ads_topup_trans/';

$tempDir = '../' . img_server . "temp/";
$tempAttachDir = $tempDir . "attachment/";
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
if (!file_exists($tempAttachDir)) {
    mkdir($tempAttachDir, 0777, true);
}

$checkboxValues = isset($_GET['export_ids']) ? $_GET['export_ids'] : (isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '');
$checkboxValues = preg_replace('/[^0-9,]/', '', (string) $checkboxValues);

if (isset($_GET['export_ids'])) {
    error_log('[shopee_ads_topup_export] raw_export_ids=' . $_GET['export_ids'] . ' sanitized=' . $checkboxValues);
}

// Check if any checkboxes are checked
if (!empty($checkboxValues)) {
    if (!isActionAllowed("Export", $pinAccess)) {
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/shopee/shopee_ads_topup_trans_table.php';</script>";
        exit;
    }

    setcookie('rowID', '', time() - 3600, '/');
    // Defining column names
    $excelData = array(
        array('S/N', 'SHOPEE ACCOUNT', 'ORDER ID', 'DATETIME', 'CURRENCY UNIT', 'TOP-UP AMOUNT', 'SUBTOTAL', 'GST(%)', 'PAYMENT METHOD', 'ATTACHMENT', 'REMARK', 'CREATE BY', 'CREATE DATE', 'CREATE TIME', 'UPDATE BY', 'UPDATE DATE', 'UPDATE TIME')
    );
    // Get the data from the database using the WHERE clause
    $query2 = $finance_connect->query("SELECT * FROM " . SHOPEE_ADS_TOPUP . " WHERE status = 'A' AND id IN ($checkboxValues) ORDER BY shopee_acc ASC, orderID ASC, payment_date ASC, currency ASC, topup_amt ASC,subtotal ASC,gst ASC, pay_meth ASC");
    if (!$query2) {
        error_log('[shopee_ads_topup_export] query_error=' . $finance_connect->error);
    } else {
        error_log('[shopee_ads_topup_export] matched_rows=' . $query2->num_rows);
    }
   
    $excelRowNum = 1;
    if ($query2->num_rows > 0) {
        while ($row2 = $query2->fetch_assoc()) {
            // Initialize an empty array to store the row data
            $lineData = array();
            $lineData[] = $excelRowNum;

            if (isset($row2['attachment']) && !empty($row2['attachment'])) {
                $attachmentRelPath = trim(str_replace('\\', '/', (string) $row2['attachment']), '/');
                if (strpos($attachmentRelPath, '/') !== false) {
                    $attachmentSourcePath = '../' . img_server . $attachmentRelPath;
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
            $columnNames = array('shopee_acc', 'orderID', 'payment_date', 'currency', 'topup_amt', 'subtotal', 'gst', 'pay_meth', 'attachment', 'remark', 'create_by', 'create_date', 'create_time', 'update_by', 'update_date', 'update_time');

            foreach ($columnNames as $columnName) {
                // Check if the value is null, if so, replace it with an empty string
                if ($columnName === 'shopee_acc') {
                    $accVal = isset($row2[$columnName]) ? (string) $row2[$columnName] : '';
                    if (ctype_digit($accVal)) {
                        $accRst = getData('name', "id='" . $accVal . "'", '', SHOPEE_ACC, $finance_connect);
                        if ($accRst && $accRst->num_rows > 0) {
                            $accVal = (string) $accRst->fetch_assoc()['name'];
                        }
                    }
                    $lineData[] = $accVal;
                } elseif ($columnName === 'create_by' || $columnName === 'update_by') {
                    $name = '';
                    $pic = getData('name', "id='" . $row2[$columnName] . "'", '', USR_USER, $connect);
                    if ($pic && $pic->num_rows > 0) {
                        $user = $pic->fetch_assoc();
                        $name = $user['name'];
                    }
                    $lineData[] = $name;
                } elseif ($columnName === 'currency') {
                    $currencyVal = isset($row2[$columnName]) ? (string) $row2[$columnName] : '';
                    if (ctype_digit($currencyVal)) {
                        $curRst = getData('unit', "id='" . $currencyVal . "'", '', CUR_UNIT, $connect);
                        if ($curRst && $curRst->num_rows > 0) {
                            $currencyVal = (string) $curRst->fetch_assoc()['unit'];
                        }
                    }
                    $lineData[] = $currencyVal;
                } elseif ($columnName === 'pay_meth') {
                    $payVal = isset($row2[$columnName]) ? (string) $row2[$columnName] : '';
                    if (ctype_digit($payVal)) {
                        $payRst = getData('name', "id='" . $payVal . "'", '', FIN_PAY_METH, $finance_connect);
                        if ($payRst && $payRst->num_rows > 0) {
                            $payVal = (string) $payRst->fetch_assoc()['name'];
                        }
                    }
                    $lineData[] = $payVal;
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
            if (class_exists('ZipArchive')) {
                $zipFile = $tempDir . date('Ymd_His') . ".zip";
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
                header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
                header('Content-Length: ' . filesize($zipFile));
                header('Pragma: no-cache');
                header('Expires: 0');
                ob_clean();
                readfile($zipFile);
                @unlink($zipFile);
                deleteDir($tempDir);
                exit;
            }

            // Fallback when ZipArchive extension is unavailable: download Excel directly.
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($tempExcelFilePath) . '"');
            header('Content-Length: ' . filesize($tempExcelFilePath));
            header('Pragma: no-cache');
            header('Expires: 0');
            ob_clean();
            readfile($tempExcelFilePath);
            deleteDir($tempDir);
            exit;
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

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['searchChk'] = '';
unset($_SESSION['resetChk']);
$_SESSION['delChk'] = '';
$num = 1;   // numbering
$deleteRedirectPage = $SITEURL . '/shopee/shopee_ads_topup_trans_table.php';
$redirect_page = $SITEURL . '/shopee/shopee_ads_topup_trans.php';
$result = getData('*', '', '', SHOPEE_ADS_TOPUP, $finance_connect);
$tblName = SHOPEE_ADS_TOPUP;

// Initialize total variables
$totalTopupAmount = 0;
$totalSubtotal = 0;
$totalGST = 0;

$timeInterval = isset($_GET['timeInterval']) ? strtolower(trim((string) $_GET['timeInterval'])) : 'daily';
$allowedIntervals = array('daily', 'weekly', 'monthly', 'yearly');
if (!in_array($timeInterval, $allowedIntervals, true)) {
    $timeInterval = 'daily';
}

$dateFilter = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
$rangeStart = isset($_GET['start']) ? trim((string) $_GET['start']) : '';
$rangeEnd = isset($_GET['end']) ? trim((string) $_GET['end']) : '';
$groupOption = isset($_GET['group']) ? strtolower(trim((string) $_GET['group'])) : '';
$allowedGroups = array('', 'shopee', 'currency', 'method');
if (!in_array($groupOption, $allowedGroups, true)) {
    $groupOption = '';
}

function sat_parse_day($dateVal)
{
    $dateVal = trim((string) $dateVal);
    if ($dateVal === '') {
        return null;
    }

    $ts = strtotime($dateVal);
    if ($ts === false) {
        return null;
    }

    return strtotime(date('Y-m-d', $ts));
}

function sat_parse_month($monthVal, $isEnd = false)
{
    $monthVal = trim((string) $monthVal);
    if ($monthVal === '') {
        return null;
    }

    if (preg_match('/^(\d{4})-(\d{2})$/', $monthVal, $m)) {
        $year = (int) $m[1];
        $month = (int) $m[2];
        if ($month < 1 || $month > 12) {
            return null;
        }

        if ($isEnd) {
            return strtotime(date('Y-m-t', strtotime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01')));
        }

        return strtotime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01');
    }

    return null;
}

function sat_parse_year($yearVal, $isEnd = false)
{
    $yearVal = trim((string) $yearVal);
    if (!preg_match('/^\d{4}$/', $yearVal)) {
        return null;
    }

    return $isEnd ? strtotime($yearVal . '-12-31') : strtotime($yearVal . '-01-01');
}

function sat_is_in_interval($paymentDate, $interval, $dateFilter, $start, $end)
{
    $paymentTs = strtotime((string) $paymentDate);
    if ($paymentTs === false) {
        return false;
    }

    $paymentDayTs = strtotime(date('Y-m-d', $paymentTs));

    if ($interval === 'daily') {
        if ($dateFilter === '') {
            return true;
        }
        $dayTs = sat_parse_day($dateFilter);
        if ($dayTs === null) {
            return true;
        }
        return $paymentDayTs === $dayTs;
    }

    if ($interval === 'weekly') {
        if ($start === '' || $end === '') {
            return true;
        }
        $startTs = sat_parse_day($start);
        $endTs = sat_parse_day($end);
        if ($startTs === null || $endTs === null) {
            return true;
        }
        if ($startTs > $endTs) {
            $tmp = $startTs;
            $startTs = $endTs;
            $endTs = $tmp;
        }
        return $paymentDayTs >= $startTs && $paymentDayTs <= $endTs;
    }

    if ($interval === 'monthly') {
        if ($start === '' || $end === '') {
            return true;
        }
        $startTs = sat_parse_month($start, false);
        $endTs = sat_parse_month($end, true);
        if ($startTs === null || $endTs === null) {
            return true;
        }
        if ($startTs > $endTs) {
            $tmp = $startTs;
            $startTs = strtotime(date('Y-m-01', $endTs));
            $endTs = strtotime(date('Y-m-t', $tmp));
        }
        return $paymentDayTs >= $startTs && $paymentDayTs <= $endTs;
    }

    if ($interval === 'yearly') {
        if ($start === '' || $end === '') {
            return true;
        }
        $startTs = sat_parse_year($start, false);
        $endTs = sat_parse_year($end, true);
        if ($startTs === null || $endTs === null) {
            return true;
        }
        if ($startTs > $endTs) {
            $tmp = $startTs;
            $startTs = strtotime(date('Y-01-01', $endTs));
            $endTs = strtotime(date('Y-12-31', $tmp));
        }
        return $paymentDayTs >= $startTs && $paymentDayTs <= $endTs;
    }

    return true;
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

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
                                <a class="btn btn-sm btn-rounded btn-primary px-3" name="addBtn" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Transaction </a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Import", $pinAccess)) : ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3" name="importBtn" id="addBtn" href="<?= $SITEURL ?>/shopee_ads_topup_import.php"><i class="fa-solid fa-file-import"></i> Import </a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Export", $pinAccess)) : ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3" name="exportBtnShopee" id="addBtn" href="#"><i class="fa-solid fa-file-export"></i> Export</a>
                            <?php endif; ?>
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
                    <button id="applyFilterBtn" type="button" class="btn btn-sm btn-rounded btn-primary me-2"><i class="fa fa-filter"></i> Apply</button>
                    <a id='resetButton' href="../reset.php?redirect=shopee/shopee_ads_topup_trans_table.php" class="btn btn-sm btn-rounded btn-primary"> <i class="fa fa-refresh"></i> Reset </a>
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
                            <input type="checkbox" class="exportAll" disabled>
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
                    $groupedRows = array();

                    if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $viewActMsg = '';
                        $sql = '';
                        if (isset($row['orderID'], $row['id']) && !empty($row['orderID'])) {
                            $paymentDate = isset($row['payment_date']) ? $row['payment_date'] : '';
                            if (!sat_is_in_interval($paymentDate, $timeInterval, $dateFilter, $rangeStart, $rangeEnd)) {
                                continue;
                            }

                            $q1 = getData('*', "id='" . $row['shopee_acc'] . "'", 'LIMIT 1', SHOPEE_ACC, $finance_connect);
                            $shopee_acc = $q1->fetch_assoc();
                            $q2 = getData('unit', "id='" . $row['currency'] . "'", 'LIMIT 1', CUR_UNIT, $connect);
                            $currs = $q2->fetch_assoc();
                            $q3 = getData('name', "id='" . $row['pay_meth'] . "'", 'LIMIT 1', FIN_PAY_METH, $finance_connect);
                            $pay = $q3->fetch_assoc();

                            $shopee = isset($shopee_acc['name']) ? $shopee_acc['name'] : '';
                            $curr = isset($currs['unit']) ? $currs['unit'] : '';
                            $method = isset($pay['name']) ? $pay['name'] : '';

                            if ($groupOption !== '') {
                                $groupKey = '';
                                if ($groupOption === 'shopee') {
                                    $groupKey = $shopee;
                                } else if ($groupOption === 'currency') {
                                    $groupKey = $curr;
                                } else if ($groupOption === 'method') {
                                    $groupKey = $method;
                                }

                                if ($groupKey === '') {
                                    $groupKey = 'N/A';
                                }

                                if (!isset($groupedRows[$groupKey])) {
                                    $groupedRows[$groupKey] = 0;
                                }
                                $groupedRows[$groupKey] += (float) (isset($row['topup_amt']) ? $row['topup_amt'] : 0);
                            }
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
                    }

                    if ($groupOption !== '') {
                        $num = 1;
                        foreach ($groupedRows as $groupName => $groupTotal) {
                            echo '<tr>
                            <th class="hideColumn" scope="row">0</th>
                            <th class="text-center"><input type="checkbox" class="export" value="" disabled></th>
                            <th scope="row">' . $num++ . '</th>
                            <td scope="row">' . htmlspecialchars((string) $groupName, ENT_QUOTES, 'UTF-8') . '</td>
                            <td scope="row">' . number_format((float) $groupTotal, 2, '.', '') . '</td>
                            </tr>';
                        }
                    }
                    ?>      
                </tbody>
                <tfoot>
                <tr>
                    <?php if ($groupOption == ''): ?>
                    <th colspan="8" class="text-end">Total:</th>
                    <th><?php echo number_format($totalTopupAmount, 2, '.', ''); ?></th>
                    <th><?php echo number_format($totalSubtotal, 2, '.', ''); ?></th>
                    <th><?php echo number_format($totalGST, 2, '.', ''); ?></th>
                    <th colspan="2"></th>
                    <?php else: ?>
                    <th colspan="4" class="text-end">Total:</th>
                    <th><?php echo number_format($totalTopupAmount, 2, '.', ''); ?></th>
                    <?php endif; ?>
                </tr>
                </tfoot>
            </table>
        </div>

    </div>

</body>

<script>
<?php include "../js/shopee_ads_topup_trans_table.js" ?>

    window.shopeeAdsTableFilters = {
        timeInterval: <?= json_encode($timeInterval) ?>,
        date: <?= json_encode($dateFilter) ?>,
        start: <?= json_encode($rangeStart) ?>,
        end: <?= json_encode($rangeEnd) ?>,
        group: <?= json_encode($groupOption) ?>
    };

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