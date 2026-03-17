<?php
$pageTitle = "Import Shortcut";

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

// Enforce page-level pin access for the Import Shortcut page itself
$pagePinAccess = checkCurrentPin($connect, $pageTitle);
if (empty($pagePinAccess) || !isActionAllowed('View', $pagePinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}
 
$shopeeAdsPinAccess = checkPin($connect, 'Shopee Ads Top Up Transaction');
$facebookAdsPinAccess = checkPin($connect, 'Facebook Ads Top Up Transaction');
$shopeeOrderPinAccess = checkPin($connect, 'Shopee All Orders');
$packagePinAccess = checkPin($connect, 'Package');
$stockInPinAccess = checkPin($connect, 'Stock In');
$productPinAccess = checkPin($connect, 'Product');

$canShopeeAdsImport = is_array($shopeeAdsPinAccess) && isActionAllowed('Import', $shopeeAdsPinAccess);
$canFacebookAdsImport = is_array($facebookAdsPinAccess) && isActionAllowed('Import', $facebookAdsPinAccess);
$canShopeeOrderImport = is_array($shopeeOrderPinAccess) && isActionAllowed('Import', $shopeeOrderPinAccess);
$canPackageImport = is_array($packagePinAccess) && isActionAllowed('Import', $packagePinAccess);
$canStockInImport = is_array($stockInPinAccess) && isActionAllowed('Import', $stockInPinAccess);
$canProductImport = is_array($productPinAccess) && isActionAllowed('Import', $productPinAccess);

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
        'title' => 'Stock Import',
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