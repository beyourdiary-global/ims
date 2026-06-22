<?php
$orderRequestInfoSource = isset($orderRequestInfoSource) ? (string) $orderRequestInfoSource : 'shopee';
$orderRequestInfoPageTitle = isset($orderRequestInfoPageTitle) && trim((string) $orderRequestInfoPageTitle) !== ''
    ? trim((string) $orderRequestInfoPageTitle)
    : ucfirst($orderRequestInfoSource) . ' Order Request Info';
$orderRequestInfoAllowedPins = isset($orderRequestInfoAllowedPins) && is_array($orderRequestInfoAllowedPins)
    ? $orderRequestInfoAllowedPins
    : array(isset($currentPagePin) ? (int) $currentPagePin : 0);
$orderRequestInfoRedirectPage = isset($orderRequestInfoRedirectPage) ? (string) $orderRequestInfoRedirectPage : '';
$pageTitle = $orderRequestInfoPageTitle;

include_once __DIR__ . '/menuHeader.php';
include_once __DIR__ . '/checkCurrentPagePin.php';
include_once ROOT . '/header/phpqrcode/qrlib.php';

$sourceConfig = shopeeOmsResolveOrderSourceConfig($orderRequestInfoSource);
$platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : shopeeOmsNormalizePlatformKey($orderRequestInfoSource);
$platformLabel = isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platform);
$pageTitle = $orderRequestInfoPageTitle;
$menuPageTitle = isset($currentPagePin) ? (string) getPinGroupNameById($connect, (int) $currentPagePin) : $platformLabel . ' Order Request';

$canViewPage = false;
foreach ($orderRequestInfoAllowedPins as $allowedPin) {
    $allowedPin = (int) $allowedPin;
    if ($allowedPin <= 0) {
        continue;
    }

    $pinAccess = checkPinByGroupId($connect, $allowedPin);
    if (isActionAllowed('View', $pinAccess)) {
        $canViewPage = true;
        break;
    }
}

if (!$canViewPage) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

shopeeOmsEnsureRealtimePostponedSync($connect, $finance_connect);

$requestId = (int) (!empty(input('id')) ? input('id') : post('id'));
if ($orderRequestInfoRedirectPage === '') {
    $orderRequestInfoRedirectPage = isset($sourceConfig['table_redirect_url']) ? (string) $sourceConfig['table_redirect_url'] : '';
}
if ($orderRequestInfoRedirectPage === '') {
    $orderRequestInfoRedirectPage = shopeeOmsGetOrderSourceInfoUrl($orderRequestInfoSource, 0);
}
$redirectPage = rtrim((string) $SITEURL, '/') . '/' . ltrim((string) trim($orderRequestInfoRedirectPage), '/');
if (preg_match('#/[^/]+\?id=0$#', $redirectPage)) {
    $redirectPage = preg_replace('#\?id=0$#', '', $redirectPage);
}

switch ($platform) {
    case 'lazada':
        $redirectPage = rtrim((string) $SITEURL, '/') . '/finance/lazada_order_req_table.php';
        break;
    case 'facebook':
        $redirectPage = rtrim((string) $SITEURL, '/') . '/finance/fb_order_req_table.php';
        break;
    case 'website':
        $redirectPage = rtrim((string) $SITEURL, '/') . '/finance/website_order_request_table.php';
        break;
    case 'shopee':
    default:
        $redirectPage = rtrim((string) $SITEURL, '/') . '/shopee/shopee_order_req_table.php';
        break;
}

if ($requestId <= 0) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit;
}

$orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $sourceConfig);
$requestRow = shopeeOmsLoadOrder($orderConnect, $requestId, $sourceConfig);
if (empty($requestRow)) {
    echo '<script>alert("Request not found.");location.href = "' . $redirectPage . '";</script>';
    exit;
}

$tokenConditions = array(
    "order_id = " . $requestId,
    "token_type = 'stock_out'",
    "status = 'A'",
);
if (shopeeOmsTableHasColumn($finance_connect, dbFinance, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform')) {
    $tokenConditions[] = "platform = '" . mysqli_real_escape_string($finance_connect, $platform) . "'";
}

$tokenRow = array();
$tokenSql = "SELECT * FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE " . implode(' AND ', $tokenConditions) . " ORDER BY id DESC LIMIT 1";
$tokenRst = mysqli_query($finance_connect, $tokenSql);
if ($tokenRst && mysqli_num_rows($tokenRst) > 0) {
    $tokenRow = (array) mysqli_fetch_assoc($tokenRst);
} else if (shopeeOmsNormalizeStatusCode(isset($requestRow['order_status']) ? $requestRow['order_status'] : '') === 'TP') {
    $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $requestRow, USER_ID, $sourceConfig);
    if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && is_array($tokenResult['token_row'])) {
        $tokenRow = (array) $tokenResult['token_row'];
    }
}

$tokenValue = trim((string) (isset($tokenRow['token']) ? $tokenRow['token'] : ''));
$orderLink = $tokenValue !== ''
    ? rtrim((string) $SITEURL, '/') . '/stock/warehouse_stock_in_scan.php?t=' . urlencode($tokenValue)
    : '';

$qrImageUrl = '';
$qrUnavailableMessage = '';
if ($orderLink !== '') {
    $qrFolderKey = trim((string) (isset($sourceConfig['attachment_page_name']) ? $sourceConfig['attachment_page_name'] : ($platform . '_order_request')), '/\\');
    $qrRelativeDir = 'temp/' . ($qrFolderKey !== '' ? $qrFolderKey : 'order_request_info') . '/';
    $qrFsDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $qrRelativeDir);
    if (!is_dir($qrFsDir) && !@mkdir($qrFsDir, 0755, true) && !is_dir($qrFsDir)) {
        $qrUnavailableMessage = 'QR image folder could not be created on this server.';
    }

    $qrFileName = $platform . '_order_' . $requestId . '_' . md5($orderLink) . '.png';
    $qrFsPath = $qrFsDir . $qrFileName;
    if ($qrUnavailableMessage === '' && function_exists('imagecreate') && !file_exists($qrFsPath)) {
        QRcode::png($orderLink, $qrFsPath, 'H', 6, 2);
    }
    if ($qrUnavailableMessage === '' && file_exists($qrFsPath)) {
        $qrImageUrl = rtrim((string) $SITEURL, '/') . '/' . trim($qrRelativeDir, '/\\') . '/' . $qrFileName;
    } else if ($qrUnavailableMessage === '') {
        $qrUnavailableMessage = 'QR image could not be generated locally on this server.';
    }
}

$summary = shopeeOmsBuildOrderProductSummaryBySource($connect, $requestRow, $sourceConfig);
$customerName = shopeeOmsGetOrderCustomerNameText($connect, $finance_connect, $requestRow, $sourceConfig);
$customerNameDisplayHtml = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
if ($platform === 'shopee') {
    $shopeeBuyerMetaMap = customerLabelGetShopeeCustomerMetaMap($connect, $finance_connect, array(
        isset($requestRow['buyer']) ? $requestRow['buyer'] : '',
        $customerName,
    ));
    $customerNameDisplayHtml = customerLabelRenderShopeeBuyerCell(
        $connect,
        $finance_connect,
        isset($requestRow['buyer']) ? $requestRow['buyer'] : '',
        $customerName,
        $shopeeBuyerMetaMap
    );
}

$warehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect);
$defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect);
$stockOutWarehouseName = shopeeOmsResolveStockOutWarehouseName($connect, $requestRow, $defaultWarehouseId, $warehouseNameMap);
$addressField = isset($sourceConfig['address_field']) ? (string) $sourceConfig['address_field'] : 'customer_address';
$airbillNoField = isset($sourceConfig['airbill_no_field']) ? (string) $sourceConfig['airbill_no_field'] : 'airbill_no';
$airbillAttachmentField = isset($sourceConfig['airbill_attachment_field']) ? (string) $sourceConfig['airbill_attachment_field'] : 'airbill_attachment';
$orderCode = shopeeOmsGetOrderCodeValue($requestRow, $sourceConfig);
$customerAddress = trim((string) (isset($requestRow[$addressField]) ? $requestRow[$addressField] : ''));
$airbillNo = trim((string) (isset($requestRow[$airbillNoField]) ? $requestRow[$airbillNoField] : ''));
$airbillAttachment = trim((string) (isset($requestRow[$airbillAttachmentField]) ? $requestRow[$airbillAttachmentField] : ''));
$currentStatus = shopeeOmsGetStatusLabel(isset($requestRow['order_status']) ? $requestRow['order_status'] : '');

$airbillAttachmentUrl = $airbillAttachment !== '' ? shopeeOmsBuildAirbillAttachmentUrl($airbillAttachment) : '';
if ($airbillAttachmentUrl === '' && $airbillAttachment !== '') {
    $storedAttachment = trim(str_replace('\\', '/', (string) $airbillAttachment), '/');
    $attachmentFileName = basename($storedAttachment);
    $imgServerBase = isset($img_server) ? trim((string) $img_server) : '';
    if ($imgServerBase !== '' && $attachmentFileName !== '') {
        $legacyAttachmentPath = '';
        $legacyAttachmentPos = strpos($storedAttachment, 'shopee_airbill_attachment/');
        if ($legacyAttachmentPos !== false) {
            $legacyAttachmentPath = substr($storedAttachment, $legacyAttachmentPos);
        } else {
            $legacyAttachmentPath = 'shopee_airbill_attachment/' . $attachmentFileName;
        }

        $airbillAttachmentUrl = rtrim($imgServerBase, '/') . '/' . ltrim($legacyAttachmentPath, '/');
    }
}

$airbillAttachmentExt = $airbillAttachmentUrl !== ''
    ? strtolower(pathinfo((string) parse_url($airbillAttachmentUrl, PHP_URL_PATH), PATHINFO_EXTENSION))
    : '';
$orderDetailPdf = '';
$orderDetailPdfUrl = '';
$orderDetailPdfExt = '';
if ($platform === 'shopee' && shopeeOmsTableHasColumn($orderConnect, dbFinance, SHOPEE_SG_ORDER_REQ, 'order_detail_pdf')) {
    $orderDetailPdf = trim((string) (isset($requestRow['order_detail_pdf']) ? $requestRow['order_detail_pdf'] : ''));
    $orderDetailPdfUrl = $orderDetailPdf !== '' ? shopeeOmsBuildAirbillAttachmentUrl($orderDetailPdf) : '';
    $orderDetailPdfExt = $orderDetailPdfUrl !== ''
        ? strtolower(pathinfo((string) parse_url($orderDetailPdfUrl, PHP_URL_PATH), PATHINFO_EXTENSION))
        : '';
}
$customerFieldLabel = $platform === 'shopee' ? 'Shopee Buyer Username' : $platformLabel . ' Customer';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= htmlspecialchars(rtrim((string) $SITEURL, '/') . '/css/main.css', ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .sor-copy-btn {
            min-width: 42px;
            border-radius: 8px !important;
            border: 1px solid #2f67d8;
            background: linear-gradient(180deg, #4f86eb 0%, #2f67d8 100%);
            color: #fff;
            transition: all .2s ease;
            box-shadow: 0 2px 6px rgba(47, 103, 216, .25);
        }

        .sor-copy-btn:hover,
        .sor-copy-btn:focus {
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(47, 103, 216, .35);
            color: #fff;
        }

        .sor-copy-btn i {
            font-size: 16px;
            line-height: 1;
        }

        .attachment-preview-media {
            margin-top: 12px;
        }

        .attachment-preview-media img,
        .attachment-preview-media iframe {
            width: 100%;
            max-width: 520px;
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            background: #fff;
        }

        .attachment-preview-media img {
            height: auto;
            display: block;
        }

        .attachment-preview-media iframe {
            min-height: 520px;
        }
    </style>
</head>
<body>
    
<div class="page-load-cover">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p>
                        <a href="<?= htmlspecialchars($redirectPage, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menuPageTitle, ENT_QUOTES, 'UTF-8') ?></a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                        <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <form method="get" action="<?= htmlspecialchars($redirectPage, ENT_QUOTES, 'UTF-8') ?>" class="m-0">
                            <button class="btn btn-sm btn-rounded btn-primary" type="submit" id="actionBtn">Back To Table</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <?php if ($qrImageUrl !== '') { ?>
                                <img class="img-fluid border rounded p-2 bg-white" style="max-width:220px;" src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="QR Code">
                                <div class="mt-3">
                                    <a class="btn btn-sm btn-rounded btn-primary" href="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" download="<?= htmlspecialchars($platform . '-order-qr-' . (int) $requestId . '.png', ENT_QUOTES, 'UTF-8') ?>">Download QR</a>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-warning mb-0"><?= htmlspecialchars($qrUnavailableMessage !== '' ? $qrUnavailableMessage : 'QR image is not available for this order yet.', ENT_QUOTES, 'UTF-8') ?></div>
                            <?php } ?>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label form_lbl">Order ID</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl"><?= htmlspecialchars($customerFieldLabel, ENT_QUOTES, 'UTF-8') ?></label>
                                <?php if ($platform === 'shopee') { ?>
                                    <div class="form-control d-flex align-items-center flex-nowrap gap-2" style="min-height:42px;"><?= $customerNameDisplayHtml ?></div>
                                <?php } else { ?>
                                    <input class="form-control" type="text" readonly value="<?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?>">
                                <?php } ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Stock Out Warehouse</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($stockOutWarehouseName, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Address</label>
                                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars($customerAddress, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Airbill</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($airbillNo, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Airbill Attachment</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($airbillAttachment, ENT_QUOTES, 'UTF-8') ?>">
                                <?php if ($airbillAttachmentUrl !== '') { ?>
                                    <div class="attachment-preview-media">
                                        <?php if (in_array($airbillAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                                            <img src="<?= htmlspecialchars($airbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Airbill Attachment Preview">
                                        <?php } else if ($airbillAttachmentExt === 'pdf') { ?>
                                            <iframe src="<?= htmlspecialchars($airbillAttachmentUrl, ENT_QUOTES, 'UTF-8') ?>" title="Airbill Attachment Preview"></iframe>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($platform === 'shopee') { ?>
                                <div class="mb-3">
                                    <label class="form-label form_lbl">Order Detail PDF</label>
                                    <input class="form-control" type="text" readonly value="<?= htmlspecialchars($orderDetailPdf, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($orderDetailPdfUrl !== '' && $orderDetailPdfExt === 'pdf') { ?>
                                        <div class="attachment-preview-media">
                                            <iframe src="<?= htmlspecialchars($orderDetailPdfUrl, ENT_QUOTES, 'UTF-8') ?>" title="Order Detail PDF Preview"></iframe>
                                        </div>
                                    <?php } else { ?>
                                        <div class="text-muted mt-2">No Order Detail PDF uploaded.</div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Current Status</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Package</label>
                                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars((string) (isset($summary['package_summary']) ? $summary['package_summary'] : ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Products</label>
                                <textarea class="form-control" rows="3" readonly><?= htmlspecialchars(!empty($summary['product_lines']) ? implode(', ', $summary['product_lines']) : '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Encrypted Order Link</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="orderRequestLink" readonly value="<?= htmlspecialchars($orderLink, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button" class="btn sor-copy-btn" id="copyOrderLinkBtn" title="Copy Link" aria-label="Copy Link"><i class="fa-regular fa-copy"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.title = <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    checkCurrentPage(<?= json_encode($menuPageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, '');
    dropdownMenuDispFix();
    setButtonColor();
    

    var copyBtn = document.getElementById('copyOrderLinkBtn');
    var orderLinkInput = document.getElementById('orderRequestLink');
    if (copyBtn && orderLinkInput) {
        copyBtn.addEventListener('click', function () {
            var originalIcon = copyBtn.innerHTML;
            var doneIcon = '<i class="fa-solid fa-check"></i>';

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(orderLinkInput.value);
            } else {
                orderLinkInput.focus();
                orderLinkInput.select();
                document.execCommand('copy');
            }

            copyBtn.innerHTML = doneIcon;
            copyBtn.setAttribute('title', 'Copied');
            setTimeout(function () {
                copyBtn.innerHTML = originalIcon;
                copyBtn.setAttribute('title', 'Copy Link');
            }, 1200);
        });
    }
</script>
</body>
</html>
