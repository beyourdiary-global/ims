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

$canShopeeAdsImport = is_array($shopeeAdsPinAccess) && isActionAllowed('Import', $shopeeAdsPinAccess);
$canFacebookAdsImport = is_array($facebookAdsPinAccess) && isActionAllowed('Import', $facebookAdsPinAccess);
$canShopeeOrderImport = is_array($shopeeOrderPinAccess) && isActionAllowed('Import', $shopeeOrderPinAccess);

$shopeeAdsImportPage = $SITEURL . '/shopee_ads_topup_import.php';
$facebookAdsImportPage = $SITEURL . '/facebook_ads_topup_import.php';
$shopeeOrderImportPage = $SITEURL . '/shopee_order_import.php';
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
                    <div class="col-12 col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Shopee Ads Topup Import</h5>
                                <p class="card-text">Import Shopee ads topup receipts.</p>
                                <div class="mt-auto">
                                    <?php if ($canShopeeAdsImport) { ?>
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $shopeeAdsImportPage ?>"><i class="fa-solid fa-file-import"></i> Open</a>
                                    <?php } else { ?>
                                        <button class="btn btn-sm btn-rounded btn-secondary" disabled>No Permission</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Facebook Ads Topup Import</h5>
                                <p class="card-text">Import Facebook ads receipts (PDF/ZIP).</p>
                                <div class="mt-auto">
                                    <?php if ($canFacebookAdsImport) { ?>
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $facebookAdsImportPage ?>"><i class="fa-solid fa-file-import"></i> Open</a>
                                    <?php } else { ?>
                                        <button class="btn btn-sm btn-rounded btn-secondary" disabled>No Permission</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Shopee Order Import</h5>
                                <p class="card-text">Import Shopee order request HTML data.</p>
                                <div class="mt-auto">
                                    <?php if ($canShopeeOrderImport) { ?>
                                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $shopeeOrderImportPage ?>"><i class="fa-solid fa-file-import"></i> Open</a>
                                    <?php } else { ?>
                                        <button class="btn btn-sm btn-rounded btn-secondary" disabled>No Permission</button>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
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
