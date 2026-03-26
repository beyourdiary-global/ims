<?php
$pageTitle = 'Stock Order Request Info';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pinAccess = checkPin($connect, 'Stock Order Request');
if (!is_array($pinAccess) || count($pinAccess) === 0 || !isActionAllowed('View', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

$requestId = (int) (!empty(input('id')) ? input('id') : post('id'));
$redirectPage = $SITEURL . '/finance/stock_order_request_table.php';

if ($requestId <= 0) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit;
}

function sorInfoQrSrc($path, $siteUrl)
{
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    return rtrim((string) $siteUrl, '/') . '/' . ltrim($path, '/');
}

function sorInfoBuildItemsSummary($items, $packageNameMap, $productNameMap)
{
    $packageSummary = array();
    $productSummary = array();
    $seenGroups = array();

    foreach ($items as $item) {
        $pkgId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
        $prodId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $packageQty = isset($item['packageQty']) ? (int) $item['packageQty'] : 0;
        $productQty = isset($item['productQty']) ? (int) $item['productQty'] : 0;
        if ($packageQty <= 0) $packageQty = 1;
        if ($productQty <= 0) $productQty = 1;

        $groupKey = isset($item['package_group_key']) ? trim((string) $item['package_group_key']) : '';
        if ($groupKey === '') {
            $groupKey = 'pkg_' . $pkgId . '_' . $packageQty;
        }

        if (!isset($seenGroups[$groupKey])) {
            $packageName = '';
            if ($pkgId > 0 && isset($packageNameMap[$pkgId])) {
                $packageName = (string) $packageNameMap[$pkgId];
            } else {
                $packageName = isset($item['package_desc']) ? trim((string) $item['package_desc']) : '';
            }
            if ($packageName !== '') {
                $packageSummary[] = $packageName . ' x' . $packageQty;
                $seenGroups[$groupKey] = true;
            }
        }

        $productName = ($prodId > 0 && isset($productNameMap[$prodId])) ? (string) $productNameMap[$prodId] : '';
        if ($productName === '') {
            $productName = isset($item['package_desc']) ? trim((string) $item['package_desc']) : '';
        }
        if ($productName !== '') {
            $productSummary[] = $productName . ' x' . $productQty;
        }
    }

    return array(
        'package' => implode(', ', $packageSummary),
        'product' => implode(', ', $productSummary),
    );
}

function sorInfoTelegramRequest($url, $payload, &$curlErr)
{
    $curlErr = '';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    // Local hosts may lack CA bundles; retry without SSL verification as fallback.
    if ($resp === false && $curlErr !== '' && stripos($curlErr, 'SSL') !== false) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);
    }

    return $resp;
}

function sorInfoResolveTelegramChatId($apiBase, &$resolveErr)
{
    $resolveErr = '';
    $getUpdatesUrl = $apiBase . '/getUpdates';
    $payload = array(
        'offset' => -1,
        'limit' => 1,
        'timeout' => 10,
    );
    $curlErr = '';
    $resp = sorInfoTelegramRequest($getUpdatesUrl, $payload, $curlErr);
    if ($resp === false || $resp === '') {
        $resolveErr = $curlErr !== '' ? $curlErr : 'Unable to call Telegram getUpdates.';
        return '';
    }

    $decoded = json_decode($resp, true);
    if (!is_array($decoded) || empty($decoded['ok']) || !isset($decoded['result']) || !is_array($decoded['result']) || count($decoded['result']) === 0) {
        $resolveErr = 'No chat found from bot updates.';
        return '';
    }

    $latest = $decoded['result'][count($decoded['result']) - 1];
    $chatId = '';
    if (isset($latest['message']['chat']['id'])) {
        $chatId = (string) $latest['message']['chat']['id'];
    } else if (isset($latest['channel_post']['chat']['id'])) {
        $chatId = (string) $latest['channel_post']['chat']['id'];
    } else if (isset($latest['callback_query']['message']['chat']['id'])) {
        $chatId = (string) $latest['callback_query']['message']['chat']['id'];
    } else if (isset($latest['my_chat_member']['chat']['id'])) {
        $chatId = (string) $latest['my_chat_member']['chat']['id'];
    }

    return trim($chatId);
}

$requestSql = "SELECT * FROM " . STOCK_ORDER_REQ . " WHERE id='" . $requestId . "' AND status='A' LIMIT 1";
$requestRst = mysqli_query($finance_connect, $requestSql);
if (!$requestRst || mysqli_num_rows($requestRst) === 0) {
    echo '<script>alert("Request not found.");location.href = "' . $redirectPage . '";</script>';
    exit;
}
$requestRow = mysqli_fetch_assoc($requestRst);

$itemRows = array();
$itemSql = "SELECT * FROM " . STOCK_ORDER_REQ_ITEM . " WHERE request_id='" . $requestId . "' AND status='A' ORDER BY id ASC";
$itemRst = mysqli_query($finance_connect, $itemSql);
$productIds = array();
$packageIds = array();
if ($itemRst) {
    while ($item = mysqli_fetch_assoc($itemRst)) {
        $itemRows[] = $item;
        $pid = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $pkgId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
        if ($pid > 0) $productIds[$pid] = true;
        if ($pkgId > 0) $packageIds[$pkgId] = true;
    }
}

$productNameMap = array();
if (!empty($productIds)) {
    $idsStr = implode(',', array_keys($productIds));
    $prdRst = mysqli_query($connect, "SELECT id,name FROM " . PROD . " WHERE id IN (" . $idsStr . ")");
    if ($prdRst) {
        while ($prd = mysqli_fetch_assoc($prdRst)) {
            $productNameMap[(int) $prd['id']] = (string) $prd['name'];
        }
    }
}

$packageNameMap = array();
if (!empty($packageIds)) {
    $idsStr = implode(',', array_keys($packageIds));
    $pkgRst = mysqli_query($connect, "SELECT id,name FROM " . PKG . " WHERE id IN (" . $idsStr . ")");
    if ($pkgRst) {
        while ($pkg = mysqli_fetch_assoc($pkgRst)) {
            $packageNameMap[(int) $pkg['id']] = (string) $pkg['name'];
        }
    }
}

$summary = sorInfoBuildItemsSummary($itemRows, $packageNameMap, $productNameMap);
$orderLink = $SITEURL . '/warehouse_stock_in_scan.php?t=' . urlencode((string) (isset($requestRow['order_link_token']) ? $requestRow['order_link_token'] : ''));
$qrImageUrl = sorInfoQrSrc(isset($requestRow['qr_image']) ? $requestRow['qr_image'] : '', $SITEURL);
$telegramMsg = '';
$telegramErr = '';

if (post('actionBtn') === 'sendTelegramStockInBot') {
    $tokenRst = mysqli_query($connect, "SELECT * FROM " . TOKEN_SETT . " WHERE status='A' ORDER BY id DESC LIMIT 1");
    $tokenRow = ($tokenRst && mysqli_num_rows($tokenRst) > 0) ? mysqli_fetch_assoc($tokenRst) : null;

    if (!$tokenRow) {
        $telegramErr = 'Token Setting not found. Please create Token Setting first.';
    } else {
        $botToken = trim((string) (isset($tokenRow['bot_token']) ? $tokenRow['bot_token'] : ''));
        $chatId = trim((string) (isset($tokenRow['chat_id']) ? $tokenRow['chat_id'] : ''));

        if ($botToken === '') {
            $telegramErr = 'Token Setting is incomplete. Bot Token is required.';
        } else {
            $invoiceNo = isset($requestRow['invoice_no']) ? (string) $requestRow['invoice_no'] : ('SOR-' . $requestId);
            $caption = "Invoice ID: " . $invoiceNo . "\n"
                . "Package: " . ($summary['package'] !== '' ? $summary['package'] : '-') . "\n"
                . "Product: " . ($summary['product'] !== '' ? $summary['product'] : '-') . "\n"
                . "Link: " . $orderLink;

            $apiBase = 'https://api.telegram.org/bot' . rawurlencode($botToken);
            $sendPhotoUrl = $apiBase . '/sendPhoto';
            $sendMessageUrl = $apiBase . '/sendMessage';

            if ($chatId === '') {
                $resolveErr = '';
                $chatId = sorInfoResolveTelegramChatId($apiBase, $resolveErr);
                if ($chatId === '') {
                    $telegramErr = 'Unable to detect Telegram chat automatically. Please send /start to your bot once, then try again.';
                    if ($resolveErr !== '') {
                        $telegramErr .= ' ' . $resolveErr;
                    }
                }
            }

            if ($telegramErr !== '') {
                // Do nothing; error already prepared.
            } else {
                $payload = array(
                    'chat_id' => $chatId,
                    'caption' => $caption,
                    'photo' => $qrImageUrl,
                );

                $curlErr = '';
                $resp = sorInfoTelegramRequest($sendPhotoUrl, $payload, $curlErr);

                $ok = false;
                if ($resp !== false && $resp !== '') {
                    $decoded = json_decode($resp, true);
                    $ok = (is_array($decoded) && !empty($decoded['ok']));
                }

                if (!$ok) {
                    $msgPayload = array(
                        'chat_id' => $chatId,
                        'text' => $caption,
                        'disable_web_page_preview' => true,
                    );
                    $curlErr2 = '';
                    $resp2 = sorInfoTelegramRequest($sendMessageUrl, $msgPayload, $curlErr2);

                    $decoded2 = json_decode((string) $resp2, true);
                    if (is_array($decoded2) && !empty($decoded2['ok'])) {
                        $telegramMsg = 'Telegram message sent successfully (fallback without photo).';
                    } else {
                        $telegramErr = 'Failed to send Telegram message.';
                        if ($curlErr !== '') {
                            $telegramErr .= ' ' . $curlErr;
                        } else if ($curlErr2 !== '') {
                            $telegramErr .= ' ' . $curlErr2;
                        }
                    }
                } else {
                    $telegramMsg = 'Telegram message sent successfully.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
</head>
<body>
<div class="pre-load-center"><div class="preloader"></div></div>
<div class="page-load-cover">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p>
                        <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <a href="<?= $redirectPage ?>">Stock Order Request</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <?= $pageTitle ?>
                    </p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center gap-2">
                        <h2><?= $pageTitle ?></h2>
                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $redirectPage ?>">Back To Table</a>
                    </div>
                </div>
            </div>

            <?php if ($telegramMsg !== '') { ?>
                <div class="alert alert-success"><?= htmlspecialchars($telegramMsg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php } ?>
            <?php if ($telegramErr !== '') { ?>
                <div class="alert alert-danger"><?= htmlspecialchars($telegramErr, ENT_QUOTES, 'UTF-8') ?></div>
            <?php } ?>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <?php if ($qrImageUrl !== '') { ?>
                                <img class="img-fluid border rounded p-2 bg-white" style="max-width:220px;" src="<?= htmlspecialchars($qrImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="QR Code">
                            <?php } else { ?>
                                <div class="alert alert-warning mb-0">QR image not found for this request.</div>
                            <?php } ?>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label form_lbl">Invoice ID</label>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars((string) (isset($requestRow['invoice_no']) ? $requestRow['invoice_no'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Package</label>
                                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars((string) $summary['package'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Product</label>
                                <textarea class="form-control" rows="3" readonly><?= htmlspecialchars((string) $summary['product'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label form_lbl">Encrypted Order Link</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sorOrderLink" readonly value="<?= htmlspecialchars((string) $orderLink, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button" class="btn btn-sm btn-rounded btn-primary" id="copyOrderLinkBtn" title="Copy Link" aria-label="Copy Link"><i class="fa-regular fa-copy"></i></button>
                                </div>
                            </div>
                            <form method="post">
                                <input type="hidden" name="id" value="<?= (int) $requestId ?>">
                                <button class="btn btn-sm btn-rounded btn-primary" type="submit" name="actionBtn" value="sendTelegramStockInBot">Send To Telegram Stockin Bot</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    checkCurrentPage('Stock Order Request', '');
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);

    var copyBtn = document.getElementById('copyOrderLinkBtn');
    var orderLinkInput = document.getElementById('sorOrderLink');
    if (copyBtn && orderLinkInput) {
        copyBtn.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(orderLinkInput.value);
            } else {
                orderLinkInput.focus();
                orderLinkInput.select();
                document.execCommand('copy');
            }
        });
    }
</script>
</body>
</html>
