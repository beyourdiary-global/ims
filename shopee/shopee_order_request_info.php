<?php
$currentPagePin = 130;
$pageTitle = 'Shopee Order Request Info';
$disablePinGroupPageTitleSync = true;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/header/phpqrcode/qrlib.php';

$pageTitle = 'Shopee Order Request Info';
$allOrdersAccess = checkPinByGroupId($connect, 130);
$verifyAccess = checkPinByGroupId($connect, 129);
$processingAccess = checkPinByGroupId($connect, 146);
$legacyProcessingAccess = checkPinByGroupId($connect, 128);
$canViewPage = isActionAllowed('View', $allOrdersAccess)
    || isActionAllowed('View', $verifyAccess)
    || isActionAllowed('View', $processingAccess)
    || isActionAllowed('View', $legacyProcessingAccess);

if (!$canViewPage) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

shopeeOmsEnsureRealtimePostponedSync($connect, $finance_connect);

$requestId = (int) (!empty(input('id')) ? input('id') : post('id'));
$redirectPage = $SITEURL . '/shopee/shopee_order_req_table.php';

if ($requestId <= 0) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit;
}

$requestRow = shopeeOmsLoadOrder($finance_connect, $requestId);
if (empty($requestRow)) {
    echo '<script>alert("Request not found.");location.href = "' . $redirectPage . '";</script>';
    exit;
}

$tokenRow = array();
$tokenRst = mysqli_query(
    $finance_connect,
    "SELECT * FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE order_id = " . $requestId . " AND token_type = 'stock_out' AND status = 'A' ORDER BY id DESC LIMIT 1"
);
if ($tokenRst && mysqli_num_rows($tokenRst) > 0) {
    $tokenRow = (array) mysqli_fetch_assoc($tokenRst);
} else if (shopeeOmsNormalizeStatusCode(isset($requestRow['order_status']) ? $requestRow['order_status'] : '') === 'TP') {
    $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $requestRow, USER_ID);
    if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && is_array($tokenResult['token_row'])) {
        $tokenRow = (array) $tokenResult['token_row'];
    }
}

$tokenValue = trim((string) (isset($tokenRow['token']) ? $tokenRow['token'] : ''));
$orderLink = $tokenValue !== ''
    ? $SITEURL . '/warehouse_stock_in_scan.php?t=' . urlencode($tokenValue)
    : '';

$qrImageUrl = '';
$qrUnavailableMessage = '';
if ($orderLink !== '') {
    $qrRelativeDir = 'temp/shopee_order_request/';
    $qrFsDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $qrRelativeDir);
    if (!file_exists($qrFsDir)) {
        mkdir($qrFsDir, 0777, true);
    }

    $qrFileName = 'shopee_order_' . $requestId . '_' . md5($orderLink) . '.png';
    $qrFsPath = $qrFsDir . $qrFileName;
    if (function_exists('imagecreate') && !file_exists($qrFsPath)) {
        QRcode::png($orderLink, $qrFsPath, 'H', 6, 2);
    }
    if (file_exists($qrFsPath)) {
        $qrImageUrl = rtrim((string) $SITEURL, '/') . '/' . trim($qrRelativeDir, '/\\') . '/' . $qrFileName;
    } else {
        $qrUnavailableMessage = 'QR image could not be generated locally on this server.';
    }
}

$summary = shopeeOmsBuildOrderProductSummary($connect, $requestRow);
$customerName = trim((string) (isset($requestRow['buyer']) ? $requestRow['buyer'] : ''));
if ($customerName !== '' && ctype_digit($customerName)) {
    $buyerRst = getData('buyer_username', "id='" . (int) $customerName . "'", 'LIMIT 1', SHOPEE_CUST_INFO, $finance_connect);
    if ($buyerRst && $buyerRst->num_rows > 0) {
        $buyerRow = $buyerRst->fetch_assoc();
        if (isset($buyerRow['buyer_username']) && trim((string) $buyerRow['buyer_username']) !== '') {
            $customerName = trim((string) $buyerRow['buyer_username']);
        }
    }
}
$customerAddress = trim((string) (isset($requestRow['customer_address']) ? $requestRow['customer_address'] : ''));
$airbillNo = trim((string) (isset($requestRow['airbill_no']) ? $requestRow['airbill_no'] : ''));
$airbillAttachment = trim((string) (isset($requestRow['airbill_attachment']) ? $requestRow['airbill_attachment'] : ''));
$currentStatus = shopeeOmsGetStatusLabel(isset($requestRow['order_status']) ? $requestRow['order_status'] : '');
$airbillAttachmentUrl = '';
$airbillAttachmentExt = '';
if ($airbillAttachment !== '') {
    $storedAttachment = trim(str_replace('\\', '/', (string) $airbillAttachment), '/');
    $attachmentFileName = basename($storedAttachment);
    if (strpos($storedAttachment, 'attachment/') === 0) {
        $airbillAttachmentUrl = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
    } else {
        $imgServerBase = isset($img_server) ? trim((string) $img_server) : '';
        if ($imgServerBase !== '') {
            $legacyAttachmentPath = '';
            $legacyAttachmentPos = strpos($storedAttachment, 'shopee_airbill_attachment/');
            if ($legacyAttachmentPos !== false) {
                $legacyAttachmentPath = substr($storedAttachment, $legacyAttachmentPos);
            } elseif ($attachmentFileName !== '') {
                $legacyAttachmentPath = 'shopee_airbill_attachment/' . $attachmentFileName;
            }

            if ($legacyAttachmentPath !== '') {
                $airbillAttachmentUrl = rtrim($imgServerBase, '/') . '/' . ltrim($legacyAttachmentPath, '/');
            }
        }
    }
    $airbillAttachmentExt = strtolower(pathinfo($attachmentFileName, PATHINFO_EXTENSION));
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
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
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p>
                        <a href="<?= $redirectPage ?>">Shopee Order Request</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <?= $pageTitle ?>
                    </p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                        <h2><?= $pageTitle ?></h2>
                        <form method="get" action="<?= $redirectPage ?>" class="m-0">
                            <button class="btn btn-sm btn-rounded btn-primary" type="submit">Back To Table</button>
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
                                    <a class="btn btn-sm btn-rounded btn-primary" href="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" download="shopee-order-qr-<?= (int) $requestId ?>.png">Download QR</a>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-warning mb-0"><?= htmlspecialchars($qrUnavailableMessage !== '' ? $qrUnavailableMessage : 'QR image is not available for this order yet.', ENT_QUOTES, 'UTF-8') ?></div>
                            <?php } ?>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label form_lbl">Order ID</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars((string) (isset($requestRow['orderID']) ? $requestRow['orderID'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Shopee Buyer Username</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?>">
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
                                            <img src="<?= htmlspecialchars($airbillAttachmentUrl) ?>" alt="Airbill Attachment Preview">
                                        <?php } else if ($airbillAttachmentExt === 'pdf') { ?>
                                            <iframe src="<?= htmlspecialchars($airbillAttachmentUrl) ?>" title="Airbill Attachment Preview"></iframe>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
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
                                    <input type="text" class="form-control" id="shopeeOrderLink" readonly value="<?= htmlspecialchars((string) $orderLink, ENT_QUOTES, 'UTF-8') ?>">
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
    checkCurrentPage('Shopee Order Request', '');
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);

    var copyBtn = document.getElementById('copyOrderLinkBtn');
    var orderLinkInput = document.getElementById('shopeeOrderLink');
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
