<?php
$currentPagePin = 0;
$disablePinGroupPageTitleSync = true;
$pageTitle = "Import Shortcut";

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

function getImportCardTitleByPinGroupId($connect, $pinGroupId, $fallbackParentTitle)
{
    $resolvedParentTitle = getPinGroupNameById($connect, (int) $pinGroupId);
    $parentTitle = $resolvedParentTitle !== '' ? $resolvedParentTitle : $fallbackParentTitle;
    return $parentTitle . ' Import';
}
 
$shopeeAdsPinAccess = checkPinByGroupId($connect, 77);
$facebookAdsPinAccess = checkPinByGroupId($connect, 50);
$lazadaOrderPinAccess = checkPinByGroupId($connect, 93);
$websiteOrderPinAccess = checkPinByGroupId($connect, 92);
$shopeeOrderPinAccess = array();
$shopeeOrderParentTitle = '';
$shopeeOrderFallbackParentTitle = '';
$canShopeeOrderImport = false;
foreach (array(130, 129, 128) as $shopeeOrderPinGroupId) {
    $candidateName = getPinGroupNameById($connect, $shopeeOrderPinGroupId);
    if ($shopeeOrderFallbackParentTitle === '' && $candidateName !== '') {
        $shopeeOrderFallbackParentTitle = $candidateName;
    }

    $candidateAccess = checkPinByGroupId($connect, $shopeeOrderPinGroupId);
    if (is_array($candidateAccess) && isActionAllowed('Import', $candidateAccess)) {
        $shopeeOrderPinAccess = $candidateAccess;
        if ($candidateName !== '') {
            $shopeeOrderParentTitle = $candidateName;
        }
        $canShopeeOrderImport = true;
        break;
    }
}
if ($shopeeOrderParentTitle === '') {
    $shopeeOrderParentTitle = $shopeeOrderFallbackParentTitle !== '' ? $shopeeOrderFallbackParentTitle : 'Shopee Order Request';
}

$shopeeAdsImportTitle = getImportCardTitleByPinGroupId($connect, 77, 'Shopee Ads Top Up Transaction');
$facebookAdsImportTitle = getImportCardTitleByPinGroupId($connect, 50, 'Facebook Ads Top Up Transaction');
$shopeeOrderImportTitle = $shopeeOrderParentTitle . ' Import';
$lazadaOrderImportTitle = getImportCardTitleByPinGroupId($connect, 93, 'Lazada Order Request');
$websiteOrderImportTitle = getImportCardTitleByPinGroupId($connect, 92, 'Website Order Request');
$packageImportTitle = getImportCardTitleByPinGroupId($connect, 21, 'Package');
$supplierInvoiceImportTitle = getImportCardTitleByPinGroupId($connect, 167, 'Supplier Invoice');
$supplierPaymentImportTitle = getImportCardTitleByPinGroupId($connect, 169, 'Supplier Payment');
$stockInImportTitle = getImportCardTitleByPinGroupId($connect, 125, 'Stock In');
$productImportTitle = getImportCardTitleByPinGroupId($connect, 20, 'Product');
$stockOrderImportTitle = getImportCardTitleByPinGroupId($connect, 126, 'Stock Order Request');
$jtBackupImportTitle = getImportCardTitleByPinGroupId($connect, 88, 'J&T Transaction Backup Record');
$companyImportTitle = getImportCardTitleByPinGroupId($connect, 127, 'Company');
$purchaseOrderImportTitle = getImportCardTitleByPinGroupId($connect, 135, 'Purchase Order');
$packagePinAccess = checkPinByGroupId($connect, 21);
$supplierInvoicePinAccess = checkPinByGroupId($connect, 167);
$supplierPaymentPinAccess = checkPinByGroupId($connect, 169);
$stockInPinAccess = checkPinByGroupId($connect, 125);
$productPinAccess = checkPinByGroupId($connect, 20);
$stockOrderReqPinAccess = checkPinByGroupId($connect, 126);
$jtBackupPinAccess = checkPinByGroupId($connect, 88);
$companyPinAccess = checkPinByGroupId($connect, 127);
$purchaseOrderPinAccess = checkPinByGroupId($connect, 135);

$canShopeeAdsImport = is_array($shopeeAdsPinAccess) && isActionAllowed('Import', $shopeeAdsPinAccess);
$canFacebookAdsImport = is_array($facebookAdsPinAccess) && isActionAllowed('Import', $facebookAdsPinAccess);
$canLazadaOrderImport = is_array($lazadaOrderPinAccess) && isActionAllowed('Import', $lazadaOrderPinAccess);
$canWebsiteOrderImport = is_array($websiteOrderPinAccess) && isActionAllowed('Import', $websiteOrderPinAccess);
$canPackageImport = is_array($packagePinAccess) && isActionAllowed('Import', $packagePinAccess);
$canSupplierInvoiceImport = is_array($supplierInvoicePinAccess) && isActionAllowed('Import', $supplierInvoicePinAccess);
$canSupplierPaymentImport = is_array($supplierPaymentPinAccess) && isActionAllowed('Import', $supplierPaymentPinAccess);
$canStockInImport = is_array($stockInPinAccess) && isActionAllowed('Import', $stockInPinAccess);
$canProductImport = is_array($productPinAccess) && isActionAllowed('Import', $productPinAccess);
$canStockOrderReqImport = is_array($stockOrderReqPinAccess) && isActionAllowed('Import', $stockOrderReqPinAccess);
$canJtBackupImport = is_array($jtBackupPinAccess) && isActionAllowed('Import', $jtBackupPinAccess);
$canCompanyImport = is_array($companyPinAccess) && isActionAllowed('Import', $companyPinAccess);
$canPurchaseOrderImport = is_array($purchaseOrderPinAccess) && isActionAllowed('Import', $purchaseOrderPinAccess);

// Configuration Array for all shortcut cards
$shortcutCards = array(
    array(
        'title' => $shopeeAdsImportTitle,
        'desc' => 'Import Shopee ads topup receipts.',
        'canImport' => $canShopeeAdsImport,
        'importUrl' => $SITEURL . '/import/shopee_ads_topup_import.php',
        'backUrl' => $SITEURL . '/shopee/shopee_ads_topup_trans_table.php',
        'backText' => 'Back To Shopee Ads Page'
    ),
    array(
        'title' => $facebookAdsImportTitle,
        'desc' => 'Import Facebook ads receipts (PDF/ZIP).',
        'canImport' => $canFacebookAdsImport,
        'importUrl' => $SITEURL . '/import/facebook_ads_topup_import.php',
        'backUrl' => $SITEURL . '/finance/fb_ads_topup_trans_table.php',
        'backText' => 'Back To Facebook Ads Page'
    ),
    array(
        'title' => $shopeeOrderImportTitle,
        'desc' => 'Import Shopee order request HTML data.',
        'canImport' => $canShopeeOrderImport,
        'importUrl' => $SITEURL . '/import/shopee_order_import.php',
        'backUrl' => $SITEURL . '/shopee/shopee_order_req_table.php',
        'backText' => 'Back To Shopee Order Page'
    ),
    array(
        'title' => $lazadaOrderImportTitle,
        'desc' => 'Import Lazada invoice PDF into Lazada order request.',
        'canImport' => $canLazadaOrderImport,
        'importUrl' => $SITEURL . '/import/lazada_order_import.php',
        'backUrl' => $SITEURL . '/finance/lazada_order_req_table.php',
        'backText' => 'Back To Lazada Order Page'
    ),
    array(
        'title' => $websiteOrderImportTitle,
        'desc' => 'Import Website order PDF into Website order request.',
        'canImport' => $canWebsiteOrderImport,
        'importUrl' => $SITEURL . '/import/website_order_import.php',
        'backUrl' => $SITEURL . '/finance/website_order_request_table.php',
        'backText' => 'Back To Website Order Page'
    ),
    array(
        'title' => $packageImportTitle,
        'desc' => 'Import package data.',
        'canImport' => $canPackageImport,
        'importUrl' => $SITEURL . '/import/package_import.php',
        'backUrl' => $SITEURL . '/product/package_table.php',
        'backText' => 'Back To Package Page'
    ),
    array(
        'title' => $supplierInvoiceImportTitle,
        'desc' => 'Import Supplier Invoice PDF data and match its description to an active package.',
        'canImport' => $canSupplierInvoiceImport,
        'importUrl' => $SITEURL . '/import/supplier_invoice_import.php',
        'backUrl' => $SITEURL . '/finance/supplier_invoice_table.php',
        'backText' => 'Back To Supplier Invoice Page'
    ),
    array(
        'title' => $supplierPaymentImportTitle,
        'desc' => 'Import image-based Supplier Payment PDF data.',
        'canImport' => $canSupplierPaymentImport,
        'importUrl' => $SITEURL . '/import/supplier_payment_import.php',
        'backUrl' => $SITEURL . '/finance/supplier_payment_table.php',
        'backText' => 'Back To Supplier Payment Page'
    ),
    array(
        'title' => $stockInImportTitle,
        'desc' => 'Import stock in data.',
        'canImport' => $canStockInImport,
        'importUrl' => $SITEURL . '/import/warehouse_stock_in_import.php',
        'backUrl' => $SITEURL . '/stock/warehouse_stock_in_table.php',
        'backText' => 'Back To Stock In Page'
    ),
    array(
        'title' => $productImportTitle,
        'desc' => 'Import product data.',
        'canImport' => $canProductImport,
        'importUrl' => $SITEURL . '/import/product_import.php',
        'backUrl' => $SITEURL . '/product/product_table.php',
        'backText' => 'Back To Product Page'
    ),
    array(
        'title' => $stockOrderImportTitle,
        'desc' => 'Import stock order request data.',
        'canImport' => $canStockOrderReqImport,
        'importUrl' => $SITEURL . '/import/stock_order_request_import.php',
        'backUrl' => $SITEURL . '/stock/stock_order_request_table.php',
        'backText' => 'Back To Stock Order Page'
    ),
    array(
        'title' => $jtBackupImportTitle,
        'desc' => 'Import J&T transaction backup PDF or ZIP files.',
        'canImport' => $canJtBackupImport,
        'importUrl' => $SITEURL . '/import/j&t_trans_backup_import.php',
        'backUrl' => $SITEURL . '/finance/j&t_trans_backup_table.php',
        'backText' => 'Back To J&T Transaction Page'
    ),
    array(
        'title' => $companyImportTitle,
        'desc' => 'Import company data and update existing records by company code.',
        'canImport' => $canCompanyImport,
        'importUrl' => $SITEURL . '/import/company_import.php',
        'backUrl' => $SITEURL . '/settings/company_table.php',
        'backText' => 'Back To Company Page'
    ),
    array(
        'title' => $purchaseOrderImportTitle,
        'desc' => 'Import purchase order data and update existing records by doc no + seq + item code.',
        'canImport' => $canPurchaseOrderImport,
        'importUrl' => $SITEURL . '/import/purchase_order_import.php',
        'backUrl' => $SITEURL . '/stock/purchase_order_table.php',
        'backText' => 'Back To Purchase Order Page'
    )
);

$shortcutCards = array_values(array_filter($shortcutCards, function ($card) {
    return !empty($card['canImport']);
}));

if (empty($shortcutCards)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $log = [
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($log);
}

?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
        
    <div class="page-load-cover">
        <div class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageTitle ?></p>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= $pageTitle ?></h2>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <?php foreach ($shortcutCards as $card) { ?>
                        <div class="col-12 col-md-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                                    <p class="card-text"><?= htmlspecialchars($card['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="mt-auto d-flex gap-2 flex-wrap">
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $card['importUrl'] ?>">
                                            <i class="fa-solid fa-file-import"></i> Import
                                        </a>
                                        <a class="btn btn-sm btn-rounded btn-info text-white" href="<?= $card['backUrl'] ?>">
                                            <?= htmlspecialchars($card['backText'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>
    </div>

    <script>
        checkCurrentPage("<?= $pageTitle ?>", "");
        dropdownMenuDispFix();
        setButtonColor();
        preloader(0, '');
    </script>
</body>
</html>
