<?php
ob_start();
$pageTitle = "Supplier Invoice";
$currentPagePin = 167;

include_once '../include/list_page_header.php';

$tblName = SUPPLIER_INVOICE;
$redirectPage = $SITEURL . '/finance/supplier_invoice.php';
$deleteRedirectPage = $SITEURL . '/finance/supplier_invoice_table.php';

if (!function_exists('supplierInvoiceExportNormalizeIds')) {
    function supplierInvoiceExportNormalizeIds($value)
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

if (!function_exists('supplierInvoiceExportExcelSerial')) {
    function supplierInvoiceExportExcelSerial($dateValue)
    {
        $dateValue = trim((string) $dateValue);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        if (!$date || $date->format('Y-m-d') !== $dateValue) {
            return '';
        }

        $baseDate = new DateTimeImmutable('1899-12-30');
        return (string) $baseDate->diff($date)->days;
    }
}

if (!function_exists('supplierInvoiceExportReplaceTemplateCell')) {
    function supplierInvoiceExportReplaceTemplateCell($rowXml, $rowNumber, $column, $value, $numeric = false)
    {
        static $templateCells = array(
            'A' => '<c r="A{row}" s="13" t="s"><v>14</v></c>',
            'C' => '<c r="C{row}" s="13" t="s"><v>31</v></c>',
            'D' => '<c r="D{row}" s="14" t="n"><v>45659</v></c>',
            'E' => '<c r="E{row}" s="13" t="s"><v>32</v></c>',
            'M' => '<c r="M{row}" s="13" t="s"><v>34</v></c>',
            'N' => '<c r="N{row}" s="13" />',
            'S' => '<c r="S{row}" s="30" t="n"><v>100</v></c>',
        );

        if (!isset($templateCells[$column])) {
            return $rowXml;
        }

        $oldCell = str_replace('{row}', (string) $rowNumber, $templateCells[$column]);
        $escapedValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($value === '') {
            $newCell = '<c r="' . $column . $rowNumber . '" s="' . ($column === 'S' ? '30' : ($column === 'D' ? '14' : '13')) . '" />';
        } elseif ($numeric) {
            $newCell = '<c r="' . $column . $rowNumber . '" s="' . ($column === 'S' ? '30' : '14') . '" t="n"><v>' . $escapedValue . '</v></c>';
        } else {
            $spaceAttribute = preg_match('/^\s|\s$/', (string) $value) ? ' xml:space="preserve"' : '';
            $newCell = '<c r="' . $column . $rowNumber . '" s="13" t="inlineStr"><is><t' . $spaceAttribute . '>' . $escapedValue . '</t></is></c>';
        }

        $replaceCount = 0;
        return str_replace($oldCell, $newCell, $rowXml, $replaceCount);
    }
}

if (!function_exists('supplierInvoiceExportBuildWorkbook')) {
    function supplierInvoiceExportBuildWorkbook($templatePath, $invoiceRows, $outputPath)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        if (!copy($templatePath, $outputPath)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($outputPath) !== true) {
            return false;
        }

        $sheetPath = 'xl/worksheets/sheet2.xml';
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            $zip->close();
            return false;
        }

        if (!preg_match('/<sheetData>.*?<\/sheetData>/s', $sheetXml, $sheetDataMatch)) {
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
        $blankRowXml = $blankRowMatch[0];
        $secondBlankRowXml = $secondBlankRowMatch[0];
        $newSheetDataXml = preg_replace('/<row r="(?:6|7|8|9)"[^>]*>.*?<\/row>/s', '', $sheetDataXml);

        $rowNumber = 6;
        $generatedRows = '';
        foreach ($invoiceRows as $invoice) {
            $row = str_replace('r="6"', 'r="' . $rowNumber . '"', $templateRowXml);
            foreach (range('A', 'S') as $column) {
                $row = str_replace('r="' . $column . '6"', 'r="' . $column . $rowNumber . '"', $row);
            }
            $row = supplierInvoiceExportReplaceTemplateCell($row, $rowNumber, 'A', $invoice['doc_no'] ?? '', false);
            $row = supplierInvoiceExportReplaceTemplateCell($row, $rowNumber, 'C', $invoice['code'] ?? '', false);
            $row = supplierInvoiceExportReplaceTemplateCell($row, $rowNumber, 'D', supplierInvoiceExportExcelSerial($invoice['doc_date'] ?? ''), true);
            $row = supplierInvoiceExportReplaceTemplateCell($row, $rowNumber, 'N', $invoice['description'] ?? '', false);
            $row = supplierInvoiceExportReplaceTemplateCell($row, $rowNumber, 'M', $invoice['control_account'] ?? '', false);
            $row = supplierInvoiceExportReplaceTemplateCell($row, $rowNumber, 'S', number_format((float) ($invoice['amount'] ?? 0), 2, '.', ''), true);
            $generatedRows .= $row;
            $rowNumber++;
        }

        foreach (array($blankRowXml, $secondBlankRowXml) as $sourceBlankRowXml) {
            $sourceRowNumber = strpos($sourceBlankRowXml, 'r="8"') !== false ? 8 : 9;
            $row = str_replace('r="' . $sourceRowNumber . '"', 'r="' . $rowNumber . '"', $sourceBlankRowXml);
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
$exportIds = supplierInvoiceExportNormalizeIds($checkboxValues);
if (!empty($exportIds)) {
    setcookie('rowID', '', time() - 3600, '/');

    if (!isActionAllowed('Export', $pinAccess)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo '<script>alert(' . json_encode('You do not have permission to export this page.') . ');location.href=' . json_encode($SITEURL . '/finance/supplier_invoice_table.php') . ';</script>';
        exit;
    }

    $idList = implode(',', $exportIds);
    $exportRows = array();
    $exportResult = mysqli_query($finance_connect, "SELECT id, doc_no, doc_date, code, control_account, description, amount FROM " . SUPPLIER_INVOICE . " WHERE status = 'A' AND id IN (" . $idList . ") ORDER BY id ASC");
    if ($exportResult) {
        while ($exportRow = mysqli_fetch_assoc($exportResult)) {
            $exportRows[] = $exportRow;
        }
    }

    $templatePath = ROOT . '/excel_template/Import Supplier Invoice.xlsx';
    $outputPath = tempnam(sys_get_temp_dir(), 'supplier_invoice_export_');
    $outputPath .= '.xlsx';
    $buildSucceeded = !empty($exportRows) && is_readable($templatePath) && supplierInvoiceExportBuildWorkbook($templatePath, $exportRows, $outputPath);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!$buildSucceeded) {
        @unlink($outputPath);
        echo '<script>alert(' . json_encode('No selected Supplier Invoice data found or the export template is unavailable.') . ');location.href=' . json_encode($SITEURL . '/finance/supplier_invoice_table.php') . ';</script>';
        exit;
    }

    $filename = 'supplier_invoice_data_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($outputPath));
    readfile($outputPath);
    @unlink($outputPath);
    exit;
}

$result = getData('*', '', '', $tblName, $finance_connect);
$qrUrlByInvoiceId = array();
$qrResult = getData('*', '', '', SUPPLIER_INVOICE_QR, $finance_connect);
if ($qrResult) {
    while ($qrRow = $qrResult->fetch_assoc()) {
        $invoiceId = isset($qrRow['supplier_invoice_id']) ? (int) $qrRow['supplier_invoice_id'] : 0;
        if ($invoiceId > 0 && !isset($qrUrlByInvoiceId[$invoiceId])) {
            $qrUrlByInvoiceId[$invoiceId] = isset($qrRow['qr_url']) ? (string) $qrRow['qr_url'] : '';
        }
    }
}
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
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="mt-auto mb-auto d-flex gap-2">
                                <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirectPage . '?act=' . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Import", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-info text-white" id="addBtn" href="<?= $SITEURL ?>/import/supplier_invoice_import.php"><i class="fa-solid fa-file-import"></i> Import</a>
                                <?php endif; ?>
                                <?php if (isActionAllowed("Export", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-success text-white" id="addBtn" name="exportBtn" href="supplier_invoice_table.php"><i class="fa-solid fa-file-export"></i> Export</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$result) : ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php else : ?>
                    <table class="table table-striped" id="supplier_invoice_table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">DocNo</th>
                                <th scope="col">DocDate</th>
                                <th scope="col">Description</th>
                                <th scope="col">Control A/C</th>
                                <th scope="col">Code</th>
                                <th scope="col">Amount</th>
                                <th scope="col">ODR</th>
                                <th scope="col">QR URL</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <?php
                                $invoiceId = isset($row['id']) ? (int) $row['id'] : 0;
                                if ($invoiceId <= 0) {
                                    continue;
                                }
                                $qrUrl = isset($qrUrlByInvoiceId[$invoiceId]) ? $qrUrlByInvoiceId[$invoiceId] : '';
                                ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= $invoiceId ?></th>
                                    <td class="text-center" scope="row"><input type="checkbox" class="export" value="<?= $invoiceId ?>"></td>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, $invoiceId, $row['doc_no'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['doc_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['doc_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) ($row['control_account'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['amount'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) ($row['odr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row">
                                        <?php if ($qrUrl !== '') : ?>
                                            <?php if (filter_var($qrUrl, FILTER_VALIDATE_URL)) : ?>
                                                <a href="<?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open QR URL</a>
                                            <?php else : ?>
                                                <?= htmlspecialchars($qrUrl, ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) ($row['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">DocNo</th>
                                <th scope="col">DocDate</th>
                                <th scope="col">Description</th>
                                <th scope="col">Control A/C</th>
                                <th scope="col">Code</th>
                                <th scope="col">Amount</th>
                                <th scope="col">ODR</th>
                                <th scope="col">QR URL</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="<?= $SITEURL ?>/js/supplier_invoice_table.js"></script>
    <script>
        const page = <?= json_encode($pageTitle) ?>;
        const action = <?= json_encode(isset($act) ? $act : '') ?>;

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        if ($('#supplier_invoice_table').length) {
            datatableAlignment('supplier_invoice_table');
        }
        setButtonColor();
    </script>
</body>

</html>
