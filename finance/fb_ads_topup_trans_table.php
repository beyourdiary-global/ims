<?php
ob_start();
$pageTitle = "Facebook Ads Top Up Transaction";
$currentPagePin = 50;

include_once '../include/list_page_header.php';

require_once '../header/PhpXlsxGenerator/PhpXlsxGenerator.php';
$fileName = date('Y-m-d_H-i-s') . "_list.xlsx";
$img_path = '../' . img_server . 'finance/fb_ads_topup_trans/';

$tempDir = '../' . img_server . "temp/";
$tempAttachDir = $tempDir . "attachment/";
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}
if (!file_exists($tempAttachDir)) {
    mkdir($tempAttachDir, 0777, true);
}

$checkboxValues = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';

// Sanitize to a comma-separated list of integer IDs
 if (!empty($checkboxValues)) {
     // Remove any character that is not a digit or a comma
     $checkboxValues = preg_replace('/[^0-9,]/', '', $checkboxValues);
     // Split, filter out empty values, cast to integers, and re-join
     $ids = array_filter(explode(',', $checkboxValues), 'strlen');
     $ids = array_map('intval', $ids);
     $checkboxValues = implode(',', $ids);
 }
 
// Check if any checkboxes are checked
if (!empty($checkboxValues)) {
    if (!isActionAllowed("Export", $pinAccess)) {
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/finance/fb_ads_topup_trans_table.php';</script>";
        exit;
    }

    setcookie('rowID', '', time() - 3600, '/');
    // Defining column names
    $excelData = array(
        array('S/N', 'META ACCOUNT', 'TRANSACTION ID', 'INVOICE/PAYMENT DATE', 'PERSON IN CHARGE', 'TOP-UP AMOUNT','ATTACHMENT','REMARK','CREATE BY', 'CREATE DATE', 'CREATE TIME', 'UPDATE BY', 'UPDATE DATE', 'UPDATE TIME')
    );    // Get the data from the database using the WHERE clause
    $query2 = $finance_connect->query("SELECT * FROM " . FB_ADS_TOPUP . " WHERE status = 'A' AND id IN ($checkboxValues) ORDER BY meta_acc ASC, transactionID ASC, payment_date ASC, pic ASC, topup_amt ASC");
    if (!$query2) {
        echo "<script>alert('Failed to load data for export.'); location.href='" . $SITEURL . "/finance/fb_ads_topup_trans_table.php';</script>";
        exit;
    }

    $excelRowNum = 1;
    if ($query2->num_rows > 0) {
        while ($row2 = $query2->fetch_assoc()) {
            // Initialize an empty array to store the row data
            $lineData = array();
            $lineData[] = $excelRowNum;
            $metaQuery = getData('*', "id='" . $row2['meta_acc'] . "'", '', META_ADS_ACC, $finance_connect);
            $meta_acc = $metaQuery->fetch_assoc();
            $accName = isset($meta_acc['accName']) ? $meta_acc['accName'] : '';
    
            // Replace meta_acc ID with accName
            $row2['meta_acc'] = $accName;
        
            if (isset($row2['attachment']) && !empty($row2['attachment'])) {
                $attachmentRelPath = trim(str_replace('\\', '/', (string) $row2['attachment']), '/');
                $attachmentSourcePath = '';

                if (strpos($attachmentRelPath, '/') !== false) {
                    if (strpos($attachmentRelPath, 'attachment/') === 0) {
                        $attachmentSourcePath = '../' . $attachmentRelPath;
                    } else {
                        $attachmentSourcePath = '../' . img_server . $attachmentRelPath;
                    }
                } else {
                    $attachmentSourcePath = $img_path . $attachmentRelPath;
                }

                if ($attachmentSourcePath !== '' && file_exists($attachmentSourcePath)) {
                    if (strpos($attachmentRelPath, '/') !== false) {
                        $pathForZip = ltrim($attachmentRelPath, '/');
                        if (strpos($pathForZip, 'attachment/') === 0) {
                            $pathForZip = substr($pathForZip, strlen('attachment/'));
                        }
                        $zipRelativePath = 'attachment/' . ltrim($pathForZip, '/');
                    } else {
                        $attachmentCreationDate = strtotime($row2['create_date']);
                        $zipRelativePath = 'attachment/' . date('Y', $attachmentCreationDate) . '/' . date('m', $attachmentCreationDate) . '/' . $attachmentRelPath;
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
            $columnNames = array('meta_acc', 'transactionID', 'payment_date', 'pic', 'topup_amt', 'attachment', 'remark','create_by', 'create_date', 'create_time', 'update_by', 'update_date', 'update_time');

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

                } elseif ($columnName === 'pic') {
                    $picName = '';
                    $picRst = getData('name', "id='" . $row2[$columnName] . "'", '', USR_USER, $connect);
                    if ($picRst && $picRst->num_rows > 0) {
                        $picName = $picRst->fetch_assoc()['name'];
                    }
                    $lineData[] = $picName !== '' ? $picName : (isset($row2[$columnName]) ? $row2[$columnName] : '');

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
                    echo "<script>alert('Failed to create export zip file.'); location.href='" . $SITEURL . "/finance/fb_ads_topup_trans_table.php';</script>";
                    exit;
                }

                $zip->addFile($tempExcelFilePath, basename($tempExcelFilePath));
                addDirToZip($tempAttachDir, $zip, $tempAttachDir);
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

$deleteRedirectPage = $SITEURL . '/fb_ads_topup_trans_table.php';
$redirectPage = $SITEURL . '/finance/fb_ads_topup_trans.php';
$result = getData('*', '', '', FB_ADS_TOPUP, $finance_connect);
$result2 = getData('*', '', '', FB_ADS_TOPUP, $finance_connect);
$tblName = FB_ADS_TOPUP;
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>
<script>
    $(document).ready(() => {
        createSortingTable('fb_ads_topup_trans_table');
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
                                <a class="btn btn-sm btn-rounded btn-primary px-3" name="addBtn" id="addBtn" href="<?= $redirectPage . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Transaction </a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Import", $pinAccess)) : ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3" name="importBtn" id="addBtn" href="<?= $SITEURL ?>/import/facebook_ads_topup_import.php"><i class="fa-solid fa-file-import"></i> Import </a>
                            <?php endif; ?>
                            <?php if (isActionAllowed("Export", $pinAccess)) : ?>
                                <a class="btn btn-sm btn-rounded btn-primary px-3" name="exportBtn" id="addBtn" onclick="captureAndExport('<?php echo $tblName; ?>')"><i class="fa-solid fa-file-export"></i> Export</a>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
           
            <div class="row mb-3">
                    <div class="col-md-3 dateFilters">
                        <label for="timeInterval" class="form-label">Filter by:</label>
                       <select class="form-select" id="timeInterval" >

                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-4 dateFilters">
                        <label for="dateFilter" class="form-label">Filter by Claim Date:</label>
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
                        <select class="form-select" id="group">
                        <option value="" selected>Select a Group</option>
                            <option value="metaaccount" >Meta Account</option>
                            <option value="invoice">Invoice/Payment Date</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-center justify-c ontent-center">
                    <a id='resetButton' href="../reset.php?redirect=finance/fb_ads_topup_trans_table.php" class="btn btn-sm btn-rounded btn-primary"> <i class="fa fa-refresh"></i> Reset </a>            
                </div>
        
                 
                </div>
           
            <input type="hidden" id="groupParam" name="group" value="">
            <input type="hidden" id="timeIntervalParam" name="timeInterval" value="">
            <input type="hidden" id="timeRangeParam" name="timeRange" value="">
             
            <table class="table table-striped" id="fb_ads_topup_trans_table">
                <thead>
                <tr>
                <?php if (!isset($_GET['group'])): ?>
                    <th class="hideColumn" scope="col">ID</th>
                    <th class="text-center">
                        <input type="checkbox" class="exportAll">
                    </th>
                    <th scope="col" width="60px">S/N</th>
                    <th scope="col" id="action_col">Action</th>
                    <th scope="col">Meta Account</th>
                    <th scope="col">Transaction ID</th>
                    <th scope="col">Invoice/Payment Date</th>
                    <th scope="col">Person In Charge</th>
                    <th scope="col">Top-up Amount</th>
                    <th scope="col">Attachment</th>
                    <th scope="col">Remark</th>
                    <?php else: ?>
                    <th class="hideColumn" scope="col">ID</th>
                    <th class="text-center">
                            <input type="checkbox" class="exportAll">
                    </th>
                    <th scope="col" width="60px">S/N</th>                       
                    <th id="group_header" scope="col"><?php echo isset($_GET['group']) && $_GET['group'] == 'metaaccount' ? "Meta Account" : "Invoice/Payment Date"; ?></th>
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
                $totalTopupAmount = 0;
                while ($row = $result->fetch_assoc()) {
                    $viewActMsg = '';
                    $sql = '';
                    $metaQuery = getData('*', "id='" . $row['meta_acc'] . "'", '', META_ADS_ACC, $finance_connect);
                    $meta_acc = $metaQuery->fetch_assoc();
                    $accName = isset($meta_acc['accName']) ? $meta_acc['accName'] : '';
                    $paymentDate = $row['payment_date'];
                    $pic = getData('name', "id='" . $row['pic'] . "'", '', USR_USER, $connect);
                    $usr = $pic->fetch_assoc();
                    if ($groupOption === '') {
                        $totalTopupAmount += (float) (isset($row['topup_amt']) ? $row['topup_amt'] : 0);
                        echo '<tr>
                        <th class="hideColumn" scope="row">' . $row['id'] . '</th>
                        <th class="text-center"><input type="checkbox" class="export" value="' . $row['id'] . '"></th>
                        <th scope="row">' . $num++ . '</th>
                        <td scope="row" class="btn-container">
                            <div class="d-flex align-items-center">' 
                        
                        ?>
                            <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess);?>
                            <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2) ?>
                            <?php renderDeleteButton($pinAccess, $row['id'], $row['meta_acc'], $row['transactionID'], $pageTitle, $redirectPage, $deleteRedirectPage) ?>
                        <?php echo'</div>
                        </td>
                        <td scope="row">' . (isset($meta_acc['accName']) ? $meta_acc['accName'] : '') . '</td>
                        <td scope="row">' . $row['transactionID'] . '</td>
                        <td scope="row">' . (isset($row['payment_date']) ? $row['payment_date'] : '') . '</td>
                        <td scope="row">' . (isset($usr['name']) ? $usr['name'] : '') . '</td>
                        <td scope="row">' . (isset($row['topup_amt']) ? $row['topup_amt'] : '') . '</td>
                        <td scope="row">' . (isset($row['attachment']) ? $row['attachment'] : '') . '</td>
                        <td scope="row">' . (isset($row['remark']) ? $row['remark'] : '') . '</td>
                    </tr>';
                    }
                    if ($groupOption && $groupOption3) {
                        if (($groupOption === 'metaaccount' || $groupOption === 'invoice') && $groupOption3 === $paymentDate) {
                            $key = $groupOption === 'metaaccount' ? $accName : $paymentDate;
                            if (!isset($groupedRows[$key])) {
                                $groupedRows[$key] = [
                                    'ids' => [$row['id']],
                                    'totalTopupAmount' => $row['topup_amt']
                                ];
                            } else {
                                $groupedRows[$key]['ids'][] = $row['id'];
                                $groupedRows[$key]['totalTopupAmount'] += $row['topup_amt'];
                            }
                           
                          
                        }else if ($groupOption === 'invoice' && $groupOption4 === 'weekly') {
                            $dateRange = explode('to', $groupOption3);
                            $startDate = strtotime(trim($dateRange[0]));
                            $endDate = strtotime(trim($dateRange[1]));
                        
                            $paymentDateTimestamp = strtotime($paymentDate);
                        
                            if ($paymentDateTimestamp >= $startDate && $paymentDateTimestamp <= $endDate) {
                                if (!isset($groupedRows[$paymentDate])) {
                                    $groupedRows[$paymentDate] = [
                                        'ids' => [$row['id']],
                                        'totalTopupAmount' => $row['topup_amt']
                                    ];
                                } else {
                                    $groupedRows[$paymentDate]['ids'][] = $row['id']; 
                                    $groupedRows[$paymentDate]['totalTopupAmount'] += $row['topup_amt'];
                                }
                            }
                          
                        }else if ($groupOption === 'metaaccount' && $groupOption4 === 'weekly') {
                            $dateRange = explode('to', $groupOption3);
                            $startDate = strtotime(trim($dateRange[0]));
                            $endDate = strtotime(trim($dateRange[1]));
                        
                            $paymentDateTimestamp = strtotime($paymentDate);
                        
                            if ($paymentDateTimestamp >= $startDate && $paymentDateTimestamp <= $endDate) {
                                if (!isset($groupedRows[$accName])) {
                                    $groupedRows[$accName] = [
                                        'ids' => [$row['id']], 
                                        'totalTopupAmount' => $row['topup_amt']
                                    ];
                                } else {
                                    $groupedRows[$accName]['ids'][] = $row['id']; 
                                    $groupedRows[$accName]['totalTopupAmount'] += $row['topup_amt'];
                                }
                            }
                          
                            
                          
                        }else if ($groupOption === 'invoice' && $groupOption4 === 'monthly') {
                            $dateRange = explode('to', $groupOption3);
                            $startDate = strtotime(trim($dateRange[0]));
                            $endDate = strtotime('last day of ' . trim($dateRange[1]));
                        
                            $paymentDateTimestamp = strtotime($paymentDate);
                        
                            if ($paymentDateTimestamp >= $startDate && $paymentDateTimestamp <= $endDate) {
                                $monthYear = date('Y-m', $paymentDateTimestamp);
                        
                                if (!isset($groupedRows[$paymentDate])) {
                                    $groupedRows[$paymentDate] = [
                                        'ids' => [$row['id']],
                                        'totalTopupAmount' => $row['topup_amt']
                                    ];
                                } else {
                                    $groupedRows[$paymentDate]['ids'][] = $row['id']; 
                                    $groupedRows[$paymentDate]['totalTopupAmount'] += $row['topup_amt'];
                                }
                            }
                          
                        }else if ($groupOption === 'metaaccount' && $groupOption4 === 'monthly') {
                            $dateRange = explode('to', $groupOption3);
                            $startDate = strtotime(trim($dateRange[0]));
                            $endDate = strtotime('last day of ' . trim($dateRange[1]));
                        
                            $paymentDateTimestamp = strtotime($paymentDate);
                        
                            if ($paymentDateTimestamp >= $startDate && $paymentDateTimestamp <= $endDate) {
                                $monthYear = date('Y-m', $paymentDateTimestamp);
                        
                                if (!isset($groupedRows[$accName])) {
                                    $groupedRows[$accName] = [
                                        'ids' => [$row['id']], 
                                        'totalTopupAmount' => $row['topup_amt']
                                    ];
                                } else {
                                    $groupedRows[$accName]['ids'][] = $row['id'];
                                    $groupedRows[$accName]['totalTopupAmount'] += $row['topup_amt'];
                                }
                            }
                            
                          
                        }else if ($groupOption === 'invoice' && $groupOption4 === 'yearly') {
                            $dateRange = explode('to', $groupOption3);
                            $startDate = strtotime('first day of January ' . trim($dateRange[0]));
                            $endDate = strtotime('last day of December ' . trim($dateRange[1]));
                        
                            $paymentDateTimestamp = strtotime($paymentDate);
                        
                            if ($paymentDateTimestamp >= $startDate && $paymentDateTimestamp <= $endDate) {
                                $year = date('Y', $paymentDateTimestamp);
                        
                                if (!isset($groupedRows[$paymentDate])) {
                                    $groupedRows[$paymentDate] = [
                                        'ids' => [$row['id']],
                                        'totalTopupAmount' => $row['topup_amt']
                                    ];
                                } else {
                                    $groupedRows[$paymentDate]['ids'][] = $row['id']; 
                                    $groupedRows[$paymentDate]['totalTopupAmount'] += $row['topup_amt'];
                                }
                            }
                        }else if ($groupOption === 'metaaccount' && $groupOption4 === 'yearly') {
                            $dateRange = explode('to', $groupOption3);
                            $startDate = strtotime('first day of January ' . trim($dateRange[0]));
                            $endDate = strtotime('last day of December ' . trim($dateRange[1]));
                        
                            $paymentDateTimestamp = strtotime($paymentDate);
                        
                            if ($paymentDateTimestamp >= $startDate && $paymentDateTimestamp <= $endDate) {
                                $year = date('Y', $paymentDateTimestamp);
                        
                                if (!isset($groupedRows[$accName])) {
                                    $groupedRows[$accName] = [
                                        'ids' => [$row['id']], 
                                        'totalTopupAmount' => $row['topup_amt']
                                    ];
                                } else {
                                    $groupedRows[$accName]['ids'][] = $row['id'];
                                    $groupedRows[$accName]['totalTopupAmount'] += $row['topup_amt'];
                                }
                            }
                          
                        } else {
                            $viewActMsg = '';
                            $sql = '';
                        }                      
                        
                    }  else if ($groupOption === 'invoice') {
                        $totalTopupAmount += (float) (isset($row['topup_amt']) ? $row['topup_amt'] : 0);
                        financeGenerateTableRow(array(
                            'id' => $row['id'],
                            'summary_page' => 'fb_ads_topup_trans_table_summary.php',
                            'cells' => array($paymentDate),
                            'amount' => $row['topup_amt'],
                        ), $counters);
                    } else if ($groupOption === 'metaaccount') {
                        $totalTopupAmount += (float) (isset($row['topup_amt']) ? $row['topup_amt'] : 0);
                        financeGenerateTableRow(array(
                            'id' => $row['id'],
                            'summary_page' => 'fb_ads_topup_trans_table_summary.php',
                            'cells' => array($accName),
                            'amount' => $row['topup_amt'],
                        ), $counters);
                    }
                    
                }
                
              
                foreach ($groupedRows as $key => $groupedRow) {
                   
                    if (isset($key)) {
                        if($groupOption4 == 'daily') {
                            if (!isset($groupedRow['displayed'])) {
                                $groupedRow['displayed'] = true;
                                $viewActMsg = USER_NAME . " searched the data [<b> ID = " . implode(', ', $groupedRow['ids']) . "</b> ] with the date <b>" . $paymentDate. "</b> from <b><i>$tblName Table</i></b>.";
                                $idss = implode(', ', $groupedRow['ids']);
                                $sql = "SELECT * FROM $tblName WHERE id IN ($idss)";
                            } else {
                                $viewActMsg = '';
                                $sql = '';
                            }
                        }else{
                            if (!isset($groupedRow['displayed'])) {
                                $groupedRow['displayed'] = true;
                                
                                $idss = is_array($groupedRow['ids']) ? implode(', ', $groupedRow['ids']) : $groupedRow['ids'];
                                
                                $viewActMsg = USER_NAME . " searched the data [ <b>ID = " . $idss . " </b>] for the period between <b> " . date('Y-m-d', ($startDate)) . " </b> and <b>" . date('Y-m-d', ($endDate)) . "</b> from <b><i>" . $tblName . "Table</i></b> .";
                                $sql = "SELECT * FROM $tblName WHERE id IN ($idss)";
                               
                            } else {
                                $viewActMsg = '';
                                $sql = '';
                            }
                        }
                        $log = [
                            'log_act' => 'search',
                            'cdate'   => $cdate,
                            'ctime'   => $ctime,
                            'uid'     => USER_ID,
                            'cby'     => USER_ID,
                            'query_rec'    => $sql,
                            'query_table'  => $tblName,
                            'act_msg' => $viewActMsg,
                            'page'    => $pageTitle,
                            'connect' => $connect,
                        ];
                        audit_log($log);
                
                        $ids = is_array($groupedRow['ids']) ? implode(',', $groupedRow['ids']) : $groupedRow['ids'];

                        $url = $groupOption4 == 'daily' ? "fb_ads_topup_trans_table_detail.php?ids=" . urlencode($ids) : "fb_ads_topup_trans_table_summary.php?ids=" . urlencode($ids);
                        echo "<tr onclick=\"window.location='$url'\" style=\"cursor:pointer;\">";
                        echo'<th class="text-center"><input type="checkbox" class="export" value="' . $ids . '"></th>';
                        echo '<th class="hideColumn" scope="row">' . $ids . '</th>'; 
                        echo '<th scope="row">' . $counters++ . '</th>';
                        $groupTopupAmount = (float) (isset($groupedRow['totalTopupAmount']) ? $groupedRow['totalTopupAmount'] : 0);
                        $totalTopupAmount += $groupTopupAmount;
                        echo '<td scope="row">' . $key . '</td>';
                        echo '<td scope="row">' . number_format($groupTopupAmount, 2, '.', '') . '</td>';
                        echo '</tr>';
                    }
                }    
                ?>


                </tbody>
   
                <tfoot>
                    <tr>
                    <?php if (!isset($_GET['group'])): ?>
                        <th class="hideColumn" scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col"><?php echo number_format($totalTopupAmount, 2, '.', ''); ?></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                    <?php else: ?>
                        <th class="hideColumn" scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col"><?php echo number_format($totalTopupAmount, 2, '.', ''); ?></th>
                    <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>

</body>





<script>

    $('#resetButton').click(function() {

$('#datepicker input, #datepicker2 input[name="start"], #datepicker2 input[name="end"], #datepicker3 input[name="start"], #datepicker3 input[name="end"], #datepicker4 input[name="start"], #datepicker4 input[name="end"]').val('');


$('#group').val('');
$('#timeInterval').val('');
$('#datepicker input').change();
});


<?php include "../js/fb_ads_topup_table.js" ?>
    
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
    datatableAlignment('fb_ads_topup_trans_table');
</script>

</html>
