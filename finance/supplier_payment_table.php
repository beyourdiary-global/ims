<?php
ob_start();
$pageTitle = 'Supplier Payment';
$currentPagePin = 169;

include_once '../include/list_page_header.php';

$tblName = SUPPLIER_PAYMENT;
$redirectPage = $SITEURL . '/finance/supplier_payment.php';
$deleteRedirectPage = $SITEURL . '/finance/supplier_payment_table.php';

if (!function_exists('supplierPaymentExportNormalizeIds')) {
    function supplierPaymentExportNormalizeIds($value)
    {
        $ids = array();
        foreach (explode(',', (string) $value) as $rawId) {
            $id = (int) trim($rawId);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('supplierPaymentExportExcelSerial')) {
    function supplierPaymentExportExcelSerial($dateValue)
    {
        $dateValue = trim((string) $dateValue);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if (!$date || $date->format('Y-m-d') !== $dateValue) {
            return '';
        }
        return (string) (new DateTimeImmutable('1899-12-30'))->diff($date)->days;
    }
}

if (!function_exists('supplierPaymentExportReplaceCell')) {
    function supplierPaymentExportReplaceCell($rowXml, $rowNumber, $column, $value, $numeric = false)
    {
        $cellPattern = '/<c r="' . preg_quote($column, '/') . $rowNumber . '"([^>]*)\/>|<c r="' . preg_quote($column, '/') . $rowNumber . '"([^>]*)>.*?<\/c>/s';
        if (!preg_match($cellPattern, $rowXml, $cellMatch)) {
            return $rowXml;
        }

        $attributes = isset($cellMatch[1]) && $cellMatch[1] !== '' ? $cellMatch[1] : (isset($cellMatch[2]) ? $cellMatch[2] : '');
        $style = '';
        if (preg_match('/\bs="(\d+)"/', $attributes, $styleMatch)) {
            $style = ' s="' . $styleMatch[1] . '"';
        }
        $escapedValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($value === '') {
            $newCell = '<c r="' . $column . $rowNumber . '"' . $style . ' />';
        } elseif ($numeric) {
            $newCell = '<c r="' . $column . $rowNumber . '"' . $style . ' t="n"><v>' . $escapedValue . '</v></c>';
        } else {
            $spaceAttribute = preg_match('/^\s|\s$/', (string) $value) ? ' xml:space="preserve"' : '';
            $newCell = '<c r="' . $column . $rowNumber . '"' . $style . ' t="inlineStr"><is><t' . $spaceAttribute . '>' . $escapedValue . '</t></is></c>';
        }
        return preg_replace($cellPattern, $newCell, $rowXml, 1);
    }
}

if (!function_exists('supplierPaymentExportTemplatePath')) {
    function supplierPaymentExportTemplatePath()
    {
        $candidates = array(
            ROOT . '/excel_template/Invoice Supplier Payment.xlsx',
            ROOT . '/excel_template/Invoice Supplier Payment.xls',
            ROOT . '/excel_template/Import Supplier Invoice.xlsx',
        );
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }
        return '';
    }
}

if (!function_exists('supplierPaymentExportBuildWorkbook')) {
    function supplierPaymentExportBuildWorkbook($templatePath, $paymentRows, $outputPath)
    {
        if (!class_exists('ZipArchive') || $templatePath === '' || !copy($templatePath, $outputPath)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            return false;
        }
        $sheetPath = 'xl/worksheets/sheet2.xml';
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false || !preg_match('/<sheetData>.*?<\/sheetData>/s', $sheetXml, $sheetDataMatch)) {
            $zip->close();
            return false;
        }
        $sheetDataXml = $sheetDataMatch[0];
        if (!preg_match('/<row r="6"[^>]*>.*?<\/row>/s', $sheetDataXml, $templateRowMatch)
            || !preg_match('/<row r="8"[^>]*>.*?<\/row>/s', $sheetDataXml, $blankRowMatch)
            || !preg_match('/<row r="9"[^>]*>.*?<\/row>/s', $sheetDataXml, $secondBlankRowMatch)) {
            $zip->close();
            return false;
        }

        $templateRowXml = $templateRowMatch[0];
        $newSheetDataXml = preg_replace('/<row r="(?:6|7|8|9)"[^>]*>.*?<\/row>/s', '', $sheetDataXml);
        $rowNumber = 6;
        $generatedRows = '';
        $sequence = 1;
        foreach ($paymentRows as $payment) {
            $row = str_replace('r="6"', 'r="' . $rowNumber . '"', $templateRowXml);
            foreach (range('A', 'S') as $column) {
                $row = str_replace('r="' . $column . '6"', 'r="' . $column . $rowNumber . '"', $row);
            }
            $description = trim((string) ($payment['description'] ?? ''));
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'A', (string) $sequence, false);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'C', $payment['code'] ?? '', false);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'D', supplierPaymentExportExcelSerial($payment['doc_date'] ?? ''), true);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'E', $description, false);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'J', '1', true);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'M', '310-000', false);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'N', $description, false);
            $row = supplierPaymentExportReplaceCell($row, $rowNumber, 'S', number_format((float) ($payment['total'] ?? 0), 2, '.', ''), true);
            foreach (array('B', 'F', 'G', 'H', 'I', 'K', 'L', 'O', 'P', 'Q', 'R') as $blankColumn) {
                $row = supplierPaymentExportReplaceCell($row, $rowNumber, $blankColumn, '', false);
            }
            $generatedRows .= $row;
            $rowNumber++;
            $sequence++;
        }

        foreach (array($blankRowMatch[0], $secondBlankRowMatch[0]) as $sourceRowXml) {
            $sourceRowNumber = strpos($sourceRowXml, 'r="8"') !== false ? 8 : 9;
            $row = str_replace('r="' . $sourceRowNumber . '"', 'r="' . $rowNumber . '"', $sourceRowXml);
            foreach (range('A', 'S') as $column) {
                $row = str_replace('r="' . $column . $sourceRowNumber . '"', 'r="' . $column . $rowNumber . '"', $row);
            }
            $generatedRows .= $row;
            $rowNumber++;
        }

        $newSheetDataXml = str_replace('</sheetData>', $generatedRows . '</sheetData>', $newSheetDataXml);
        $sheetXml = str_replace($sheetDataXml, $newSheetDataXml, $sheetXml);
        $sheetXml = preg_replace('/(<dimension\s+ref=")[^"]+("\s*\/>)/', '$1A1:S' . ($rowNumber - 1) . '$2', $sheetXml, 1);
        $zip->addFromString($sheetPath, $sheetXml);
        $zip->close();
        return true;
    }
}

$checkboxValues = isset($_COOKIE['rowID']) ? $_COOKIE['rowID'] : '';
$exportIds = supplierPaymentExportNormalizeIds($checkboxValues);
if (!empty($exportIds)) {
    setcookie('rowID', '', time() - 3600, '/');
    if (!isActionAllowed('Export', $pinAccess)) {
        while (ob_get_level() > 0) ob_end_clean();
        echo '<script>alert(' . json_encode('You do not have permission to export this page.') . ');location.href=' . json_encode($SITEURL . '/finance/supplier_payment_table.php') . ';</script>';
        exit;
    }
    $paymentRows = array();
    $idList = implode(',', $exportIds);
    $exportResult = mysqli_query($finance_connect, 'SELECT id, doc_date, code, description, total FROM ' . SUPPLIER_PAYMENT . ' WHERE status = \'A\' AND id IN (' . $idList . ') ORDER BY id ASC');
    if ($exportResult) {
        while ($exportRow = mysqli_fetch_assoc($exportResult)) $paymentRows[] = $exportRow;
    }
    $templatePath = supplierPaymentExportTemplatePath();
    $outputPath = tempnam(sys_get_temp_dir(), 'supplier_payment_export_') . '.xlsx';
    $buildSucceeded = !empty($paymentRows) && supplierPaymentExportBuildWorkbook($templatePath, $paymentRows, $outputPath);
    while (ob_get_level() > 0) ob_end_clean();
    if (!$buildSucceeded) {
        @unlink($outputPath);
        echo '<script>alert(' . json_encode('No selected Supplier Payment data found or the export template is unavailable.') . ');location.href=' . json_encode($SITEURL . '/finance/supplier_payment_table.php') . ';</script>';
        exit;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="supplier_payment_data_' . date('Y-m-d') . '.xlsx"');
    header('Content-Length: ' . filesize($outputPath));
    readfile($outputPath);
    @unlink($outputPath);
    exit;
}

$result = getData('*', '', '', $tblName, $finance_connect);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<script src="<?= $SITEURL ?>/js/list_page_common.js"></script>
<body>
    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row"><p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p></div>
                    <div class="row"><div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <div class="mt-auto mb-auto d-flex gap-2">
                            <?php if (isActionAllowed('Add', $pinAccess)) : ?><a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirectPage . '?act=' . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
                            <?php if (isActionAllowed('Import', $pinAccess)) : ?><a class="btn btn-sm btn-rounded btn-info text-white" id="addBtn" href="<?= $SITEURL ?>/import/supplier_payment_import.php"><i class="fa-solid fa-file-import"></i> Import</a><?php endif; ?>
                            <?php if (isActionAllowed('Export', $pinAccess)) : ?><a class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" name="exportBtn" href="supplier_payment_table.php"><i class="fa-solid fa-file-export"></i> Export</a><?php endif; ?>
                        </div>
                    </div></div>
                </div>
                <?php if (!$result || $result->num_rows === 0) : ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php else : ?>
                    <table class="table table-striped" id="supplier_payment_table">
                        <thead><tr>
                            <th class="hideColumn" scope="col">ID</th><th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th><th scope="col">S/N</th><th scope="col" id="action_col">Action</th>
                            <th scope="col">DocDate</th><th scope="col">Code</th><th scope="col">Bill No.</th><th scope="col">Description</th>
                            <th scope="col">Quantity</th><th scope="col">Amount</th><th scope="col">Add SST</th><th scope="col">Total</th><th scope="col">Remark</th>
                        </tr></thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= (int) $row['id'] ?></th><td class="text-center" scope="row"><input type="checkbox" class="export" value="<?= (int) $row['id'] ?>"></td>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton('View', $redirectPage, $row, $pinAccess); ?>
                                        <?php renderViewEditButton('Edit', $redirectPage, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, (int) $row['id'], $row['bill_no'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['doc_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['bill_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['quantity'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['amount'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars(number_format((float) $row['add_sst'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['total'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) ($row['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot><tr>
                            <th class="hideColumn" scope="col">ID</th><th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th><th scope="col">S/N</th><th scope="col" id="action_col">Action</th>
                            <th scope="col">DocDate</th><th scope="col">Code</th><th scope="col">Bill No.</th><th scope="col">Description</th>
                            <th scope="col">Quantity</th><th scope="col">Amount</th><th scope="col">Add SST</th><th scope="col">Total</th><th scope="col">Remark</th>
                        </tr></tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const page = <?= json_encode($pageTitle) ?>;
        const action = <?= json_encode(isset($act) ? $act : '') ?>;
        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        if ($('#supplier_payment_table').length) datatableAlignment('supplier_payment_table');
        setButtonColor();
    </script>
    <script src="<?= $SITEURL ?>/js/supplier_payment_table.js"></script>
</body>
</html>
