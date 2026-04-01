<?php
ob_start();
$pageTitle = "Purchase Order";
$currentPagePin = 135;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$tblName = PURCHASE_ORDER;
$companyTbl = COMPANY;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;

$redirect_page = $SITEURL . '/purchase_order.php';
$deleteRedirectPage = $SITEURL . '/purchase_order_table.php';
$import_page = $SITEURL . '/purchase_order_import.php';

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

if ($actionBtn === 'exportData' && !empty($selectedExportIds)) {
    if (!isActionAllowed("Export", $pinAccess)) {
        ob_end_clean();
        echo "<script>alert('You do not have permission to export this page.'); location.href='" . $SITEURL . "/purchase_order_table.php';</script>";
        exit;
    }

    if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
        include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
    }

    $excelData = array();

    $header = array(
        'DocDate',
        'DocNo(20)',
        'Code(10)',
        'CompanyName(100)',
        'ADDRESS1(60)',
        'ADDRESS2(60)',
        'ADDRESS3(60)',
        'ADDRESS4(60)',
        'POSTCODE(10)',
        'CITY(50)',
        'STATE(50)',
        'COUNTRY(2)',
        'PHONE1(200)',
        'Description_HDR(200)',
        'SALESTAXNO(25)',
        'SERVICETAXNO(25)',
        'TIN(14)',
        'IDTYPE',
        'IDNO(20)',
        'TOURISMNO(17)',
        'SIC(10)',
        'INCOTERMS(3)',
        'SUBMISSIONTYPE',
        'SEQ',
        'ACCOUNT(10)',
        'ItemCode(30)',
        'Description_DTL(200)',
        'Qty',
        'UOM(10)',
        'UnitPrice',
        'Amount',
        'IRBM_CLASSIFICATION(3)',
        'TAXEXEMPTIONREASON(300)',
    );
    $excelData[] = $header;

    $query = "SELECT * FROM " . $tblName . " WHERE status='A' AND id IN (" . $selectedExportIds . ") ORDER BY id ASC";
    $exportRst = mysqli_query($connect, $query);

    if ($exportRst) {
        while ($poRow = mysqli_fetch_assoc($exportRst)) {
            $companyData = array();
            $safeCompanyName = mysqli_real_escape_string($connect, (string) (isset($poRow['company_name']) ? $poRow['company_name'] : ''));
            $cmpQ = "SELECT * FROM " . $companyTbl . " WHERE name='" . $safeCompanyName . "' AND status='A' LIMIT 1";
            $cmpR = mysqli_query($connect, $cmpQ);
            if ($cmpR && mysqli_num_rows($cmpR) === 1) {
                $companyData = mysqli_fetch_assoc($cmpR);
            }

            $exportDocDate = isset($poRow['doc_date']) ? (string) $poRow['doc_date'] : '';
            if ($exportDocDate !== '') {
                $dt = DateTime::createFromFormat('Y-m-d', $exportDocDate);
                if ($dt && $dt->format('Y-m-d') === $exportDocDate) {
                    $exportDocDate = $dt->format('d/m/Y');
                }
            }

            $incotermsValue = '';
            if (isset($companyData['incoterms'])) {
                $incotermsValue = (string) $companyData['incoterms'];
            } elseif (isset($companyData['income'])) {
                $incotermsValue = (string) $companyData['income'];
            }

            $line = array(
                $exportDocDate,
                isset($poRow['doc_no']) ? (string) $poRow['doc_no'] : '',
                isset($poRow['code']) ? (string) $poRow['code'] : '',
                isset($poRow['company_name']) ? (string) $poRow['company_name'] : '',
                isset($companyData['address1']) ? (string) $companyData['address1'] : '',
                isset($companyData['address2']) ? (string) $companyData['address2'] : '',
                isset($companyData['address3']) ? (string) $companyData['address3'] : '',
                isset($companyData['address4']) ? (string) $companyData['address4'] : '',
                isset($companyData['postcode']) ? (string) $companyData['postcode'] : '',
                isset($companyData['city']) ? (string) $companyData['city'] : '',
                isset($companyData['state']) ? (string) $companyData['state'] : '',
                isset($companyData['country']) ? (string) $companyData['country'] : '',
                isset($companyData['phone1']) ? (string) $companyData['phone1'] : '',
                isset($poRow['description_hdr']) ? (string) $poRow['description_hdr'] : '',
                isset($companyData['sales_tax_no']) ? (string) $companyData['sales_tax_no'] : '',
                isset($companyData['service_tax_no']) ? (string) $companyData['service_tax_no'] : '',
                isset($companyData['tin']) ? (string) $companyData['tin'] : '',
                isset($companyData['id_type']) ? (string) $companyData['id_type'] : '',
                isset($companyData['id_no']) ? (string) $companyData['id_no'] : '',
                isset($companyData['tourism_no']) ? (string) $companyData['tourism_no'] : '',
                isset($companyData['sic']) ? (string) $companyData['sic'] : '',
                $incotermsValue,
                isset($companyData['submission_type']) ? (string) $companyData['submission_type'] : '',
                isset($poRow['seq']) ? (string) $poRow['seq'] : '',
                isset($poRow['account']) ? (string) $poRow['account'] : '',
                isset($poRow['item_code']) ? (string) $poRow['item_code'] : '',
                isset($poRow['description_dtl']) ? (string) $poRow['description_dtl'] : '',
                isset($poRow['qty']) ? (string) $poRow['qty'] : '',
                isset($poRow['uom']) ? (string) $poRow['uom'] : '',
                isset($poRow['unit_price']) ? (string) $poRow['unit_price'] : '',
                isset($poRow['amount']) ? (string) $poRow['amount'] : '',
                isset($companyData['irbm_classification']) ? (string) $companyData['irbm_classification'] : '',
                isset($companyData['tax_exemption_reason']) ? (string) $companyData['tax_exemption_reason'] : '',
            );

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
        'act_msg' => USER_NAME . " exported purchase order data [<b>ID = " . $selectedExportIds . "</b>] from <b><i>" . $tblName . " Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    );
    audit_log($log);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (count($excelData) <= 1) {
        echo "<script>alert('No selected purchase order data found to export.');window.location.href='purchase_order_table.php';</script>";
        exit;
    }

    $filename = 'purchase_order_data_' . date('Y-m-d') . '.xlsx';
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit;
}

if ($actionBtn === 'exportData' && empty($selectedExportIds)) {
    echo "<script>alert('Please select data to export.');window.location.href='purchase_order_table.php';</script>";
    exit;
}

$result = mysqli_query($connect, "SELECT * FROM " . $tblName . " WHERE status='A' ORDER BY id DESC");
if (!$result) {
    echo "<script type='text/javascript'>alert('Unable to load Purchase Order records. Please try again later.');</script>";
    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .btn { padding: 0.2rem 0.5rem; font-size: 0.75rem; margin: 3px; }
        .btn-container { white-space: nowrap; }
    </style>
</head>
<body>
    <div class="pre-load-center"><div class="preloader"></div></div>

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
                                    <a class="btn btn-sm btn-rounded btn-primary" id="addBtn" href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?= $pageTitle ?></a>
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

                <form id="exportForm" method="post" action="purchase_order_table.php" class="d-none">
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
                            <th scope="col">Doc Date</th>
                            <th scope="col">Doc No</th>
                            <th scope="col">Code</th>
                            <th scope="col">Company Name</th>
                            <th scope="col">SEQ</th>
                            <th scope="col">Item Code</th>
                            <th scope="col">Qty</th>
                            <th scope="col">UOM</th>
                            <th scope="col">Unit Price</th>
                            <th scope="col">Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $result->fetch_assoc()) { ?>
                            <tr>
                                <th class="hideColumn" scope="row"><?= (int) $row['id'] ?></th>
                                <td class="text-center"><input type="checkbox" class="export" value="<?= (int) $row['id'] ?>"></td>
                                <th scope="row"><?= $num++; ?></th>
                                <td scope="row" class="btn-container">
                                    <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                    <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                    <?php renderDeleteButton($pinAccess, $row['id'], $row['doc_no'], isset($row['remark']) ? $row['remark'] : '', $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                </td>
                                <td><?= htmlspecialchars((string) (isset($row['doc_date']) ? $row['doc_date'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['doc_no']) ? $row['doc_no'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['code']) ? $row['code'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['company_name']) ? $row['company_name'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['seq']) ? $row['seq'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['item_code']) ? $row['item_code'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['qty']) ? $row['qty'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['uom']) ? $row['uom'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['unit_price']) ? $row['unit_price'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['amount']) ? $row['amount'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>

                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th class="text-center" scope="col"><input type="checkbox" class="exportAll"></th>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col" id="action_col" width="100px">Action</th>
                            <th scope="col">Doc Date</th>
                            <th scope="col">Doc No</th>
                            <th scope="col">Code</th>
                            <th scope="col">Company Name</th>
                            <th scope="col">SEQ</th>
                            <th scope="col">Item Code</th>
                            <th scope="col">Qty</th>
                            <th scope="col">UOM</th>
                            <th scope="col">Unit Price</th>
                            <th scope="col">Amount</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        window.__PURCHASE_ORDER_TABLE_CONFIG = {
            page: "<?= $pageTitle ?>",
            action: "<?= isset($act) ? $act : '' ?>"
        };
    </script>
    <script src="<?= $SITEURL ?>/js/purchase_order_table.js"></script>
</body>
</html>
