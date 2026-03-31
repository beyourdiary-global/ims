<?php
$pageTitle = "Import Shortcut";

$importShortcutPinGroupId = 131;

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

$importShortcutPinGroupName = getPinGroupNameById($connect, $importShortcutPinGroupId);
if ($importShortcutPinGroupName !== '') {
    $pageTitle = $importShortcutPinGroupName;
}

// Enforce page-level pin access for the Import Shortcut page itself
$pagePinAccess = checkPinByGroupId($connect, $importShortcutPinGroupId);
if (empty($pagePinAccess) || !isActionAllowed('View', $pagePinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}
 
$shopeeAdsPinAccess = checkPinByGroupId($connect, 77);
$facebookAdsPinAccess = checkPinByGroupId($connect, 50);
$shopeeOrderPinAccess = array();
foreach (array(130, 129, 128) as $shopeeOrderPinGroupId) {
    $candidateAccess = checkPinByGroupId($connect, $shopeeOrderPinGroupId);
    if (!empty($candidateAccess)) {
        $shopeeOrderPinAccess = $candidateAccess;
        break;
    }
}
$packagePinAccess = checkPinByGroupId($connect, 21);
$stockInPinAccess = checkPinByGroupId($connect, 125);
$productPinAccess = checkPinByGroupId($connect, 20);
$stockOrderReqPinAccess = checkPinByGroupId($connect, 126);
$jtBackupPinAccess = checkPinByGroupId($connect, 88);
$companyPinAccess = checkPinByGroupId($connect, 127);
$purchaseOrderPinAccess = checkPinByGroupId($connect, 135);

$canShopeeAdsImport = is_array($shopeeAdsPinAccess) && isActionAllowed('Import', $shopeeAdsPinAccess);
$canFacebookAdsImport = is_array($facebookAdsPinAccess) && isActionAllowed('Import', $facebookAdsPinAccess);
$canShopeeOrderImport = is_array($shopeeOrderPinAccess) && isActionAllowed('Import', $shopeeOrderPinAccess);
$canPackageImport = is_array($packagePinAccess) && isActionAllowed('Import', $packagePinAccess);
$canStockInImport = is_array($stockInPinAccess) && isActionAllowed('Import', $stockInPinAccess);
$canProductImport = is_array($productPinAccess) && isActionAllowed('Import', $productPinAccess);
$canStockOrderReqImport = is_array($stockOrderReqPinAccess) && isActionAllowed('Import', $stockOrderReqPinAccess);
$canJtBackupImport = is_array($jtBackupPinAccess) && isActionAllowed('Import', $jtBackupPinAccess);
$canCompanyImport = is_array($companyPinAccess) && isActionAllowed('Import', $companyPinAccess);
$canPurchaseOrderImport = is_array($purchaseOrderPinAccess) && isActionAllowed('Import', $purchaseOrderPinAccess);

// Configuration Array for all shortcut cards
$shortcutCards = array(
    array(
        'title' => 'Shopee Ads Topup Import',
        'desc' => 'Import Shopee ads topup receipts.',
        'canImport' => $canShopeeAdsImport,
        'canViewBack' => ($canShopeeAdsImport),
        'importUrl' => $SITEURL . '/shopee_ads_topup_import.php',
        'backUrl' => $SITEURL . '/shopee/shopee_ads_topup_trans_table.php',
        'backText' => 'Back To Shopee Ads Page'
    ),
    array(
        'title' => 'Facebook Ads Topup Import',
        'desc' => 'Import Facebook ads receipts (PDF/ZIP).',
        'canImport' => $canFacebookAdsImport,
        'canViewBack' => ($canFacebookAdsImport),
        'importUrl' => $SITEURL . '/facebook_ads_topup_import.php',
        'backUrl' => $SITEURL . '/finance/fb_ads_topup_trans_table.php',
        'backText' => 'Back To Facebook Ads Page'
    ),
    array(
        'title' => 'Shopee Order Import',
        'desc' => 'Import Shopee order request HTML data.',
        'canImport' => $canShopeeOrderImport,
        'canViewBack' => ($canShopeeOrderImport),
        'importUrl' => $SITEURL . '/shopee_order_import.php',
        'backUrl' => $SITEURL . '/shopee/shopee_order_req_table.php',
        'backText' => 'Back To Shopee Order Page'
    ),
    array(
        'title' => 'Package Import',
        'desc' => 'Import package data.',
        'canImport' => $canPackageImport,
        'canViewBack' => ($canPackageImport),
        'importUrl' => $SITEURL . '/package_import.php',
        'backUrl' => $SITEURL . '/package_table.php',
        'backText' => 'Back To Package Page'
    ),
    array(
        'title' => 'Stock In Import',
        'desc' => 'Import stock in data.',
        'canImport' => $canStockInImport,
        'canViewBack' => ($canStockInImport),
        'importUrl' => $SITEURL . '/warehouse_stock_in_import.php',
        'backUrl' => $SITEURL . '/warehouse_stock_in_table.php',
        'backText' => 'Back To Stock In Page'
    ),
    array(
        'title' => 'Product Import',
        'desc' => 'Import product data.',
        'canImport' => $canProductImport,
        'canViewBack' => ($canProductImport),
        'importUrl' => $SITEURL . '/product_import.php',
        'backUrl' => $SITEURL . '/product_table.php',
        'backText' => 'Back To Product Page'
    ),
    array(
        'title' => 'Stock Order Import',
        'desc' => 'Import stock order request data.',
        'canImport' => $canStockOrderReqImport,
        'canViewBack' => ($canStockOrderReqImport),
        'importUrl' => $SITEURL . '/finance/stock_order_request_import.php',
        'backUrl' => $SITEURL . '/finance/stock_order_request_table.php',
        'backText' => 'Back To Stock Order Page'
    ),
    array(
        'title' => 'J&T Transaction Backup Import',
        'desc' => 'Import J&T transaction backup PDF or ZIP files.',
        'canImport' => $canJtBackupImport,
        'canViewBack' => ($canJtBackupImport),
        'importUrl' => $SITEURL . '/finance/j&t_trans_backup_import.php',
        'backUrl' => $SITEURL . '/finance/j&t_trans_backup_table.php',
        'backText' => 'Back To J&T Transaction Page'
    ),
    array(
        'title' => 'Company Import',
        'desc' => 'Import company data and update existing records by company code.',
        'canImport' => $canCompanyImport,
        'canViewBack' => ($canCompanyImport),
        'importUrl' => $SITEURL . '/company_import.php',
        'backUrl' => $SITEURL . '/company_table.php',
        'backText' => 'Back To Company Page'
    ),
    array(
        'title' => 'Purchase Order Import',
        'desc' => 'Import purchase order data and update existing records by doc no + seq + item code.',
        'canImport' => $canPurchaseOrderImport,
        'canViewBack' => ($canPurchaseOrderImport),
        'importUrl' => $SITEURL . '/purchase_order_import.php',
        'backUrl' => $SITEURL . '/purchase_order_table.php',
        'backText' => 'Back To Purchase Order Page'
    )
);

?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    <div class="pre-load-center"><div class="preloader"></div></div>
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
                                        <?php if ($card['canImport']) { ?>
                                            <a class="btn btn-sm btn-rounded btn-primary" href="<?= $card['importUrl'] ?>">
                                                <i class="fa-solid fa-file-import"></i> Import
                                            </a>
                                        <?php } ?>
                                        
                                        <?php if ($card['canViewBack']) { ?>
                                            <a class="btn btn-sm btn-rounded btn-info text-white" href="<?= $card['backUrl'] ?>">
                                                <?= htmlspecialchars($card['backText'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php } else { ?>
                                            <button class="btn btn-sm btn-rounded btn-secondary" disabled>No Permission</button>
                                        <?php } ?>
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