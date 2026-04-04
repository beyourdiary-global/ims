<?php
$currentPagePin = 0;
$pageTitle = 'Stock Order Request Info';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

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

function sorInfoTelegramRequest($url, $payload, &$curlErr, &$httpCode = 0)
{
    $curlErr = '';
    $httpCode = 0;

    // Use file_get_contents with stream context instead of cURL.
    // LiteSpeed SAPI on this server blocks outbound cURL but allows
    // PHP stream wrappers (file_get_contents).
    $postData = is_array($payload) ? http_build_query($payload) : (string) $payload;
    $opts = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 30,
            'ignore_errors' => true,
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        ),
    );
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);

    if ($resp === false) {
        $curlErr = 'file_get_contents failed for ' . $url;
        $httpCode = 0;
        return false;
    }

    // Parse HTTP status code from response headers
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $hdr, $m)) {
                $httpCode = (int) $m[1];
            }
        }
    }

    return $resp;
}

function sorInfoResolveTelegramChatId($apiBase, &$resolveErr)
{
    $resolveErr = '';
    $extractChatId = function ($decoded) {
        if (!is_array($decoded) || empty($decoded['ok']) || !isset($decoded['result']) || !is_array($decoded['result']) || count($decoded['result']) === 0) {
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
    };

    $callGetUpdates = function () use ($apiBase) {
        $url = $apiBase . '/getUpdates';
        $payload = array(
            'offset' => -1,
            'limit' => 1,
            'timeout' => 2,
        );
        $curlErr = '';
        $resp = sorInfoTelegramRequest($url, $payload, $curlErr);
        $decoded = json_decode((string) $resp, true);
        return array($resp, $decoded, $curlErr);
    };

    list($resp, $decoded, $curlErr) = $callGetUpdates();
    if ($resp === false || $resp === '') {
        $resolveErr = $curlErr !== '' ? $curlErr : 'Unable to call Telegram getUpdates.';
        return '';
    }

    $chatId = $extractChatId($decoded);
    if ($chatId !== '') {
        return $chatId;
    }

    $desc = (is_array($decoded) && isset($decoded['description'])) ? trim((string) $decoded['description']) : '';
    $isWebhookConflict = ($desc !== '' && stripos($desc, 'webhook') !== false);

    // On some live deployments, bots use webhooks and Telegram blocks getUpdates (409 conflict).
    // Remove webhook and retry once so both local (polling) and live can resolve chat_id.
    if ($isWebhookConflict) {
        $delErr = '';
        $delResp = sorInfoTelegramRequest($apiBase . '/deleteWebhook', array('drop_pending_updates' => false), $delErr);
        $delDecoded = json_decode((string) $delResp, true);
        if (is_array($delDecoded) && !empty($delDecoded['ok'])) {
            list($resp2, $decoded2, $curlErr2) = $callGetUpdates();
            if ($resp2 !== false && $resp2 !== '') {
                $chatId = $extractChatId($decoded2);
                if ($chatId !== '') {
                    return $chatId;
                }
            }
            if ($curlErr2 !== '') {
                $resolveErr = $curlErr2;
            }
        } else {
            $resolveErr = $delErr !== '' ? $delErr : ($desc !== '' ? $desc : 'Unable to disable Telegram webhook.');
            return '';
        }
    }

    if ($resolveErr === '') {
        $resolveErr = $desc !== '' ? $desc : 'No chat found from bot updates.';
    }

    return '';
}

function sorInfoResolveChatIdFromTokenRow($tokenRow)
{
    $chatId = '';

    if (is_array($tokenRow) && isset($tokenRow['chat_id'])) {
        $chatId = trim((string) $tokenRow['chat_id']);
        if ($chatId !== '') {
            return $chatId;
        }
    }

    if (is_array($tokenRow) && isset($tokenRow['remark'])) {
        $remark = trim((string) $tokenRow['remark']);
        if ($remark !== '' && preg_match('/(?:chat[_\s-]*id|chat|channel)\s*[:=]\s*(@[a-z0-9_]{4,}|-?\d{5,})/i', $remark, $m)) {
            return trim((string) $m[1]);
        }
        if ($remark !== '' && preg_match('/(^|\s)(@[a-z0-9_]{4,})($|\s)/i', $remark, $m2)) {
            return trim((string) $m2[2]);
        }
    }

    return '';
}

function sorInfoFindPreferredTokenRow($connect, $tokenTable)
{
    // Prefer rows clearly intended for Stock In Telegram flow, then fallback to latest active token.
    $sql = "SELECT * FROM `" . $tokenTable . "` WHERE status='A' ORDER BY "
        . "CASE "
        . "WHEN LOWER(name) LIKE '%stock in%' OR LOWER(name) LIKE '%stockin%' OR LOWER(name) LIKE '%stock-order%' OR LOWER(name) LIKE '%stock order%' OR LOWER(name) LIKE '%warehouse%' THEN 0 "
        . "WHEN LOWER(COALESCE(remark, '')) LIKE '%stock in%' OR LOWER(COALESCE(remark, '')) LIKE '%stockin%' OR LOWER(COALESCE(remark, '')) LIKE '%stock-order%' OR LOWER(COALESCE(remark, '')) LIKE '%stock order%' OR LOWER(COALESCE(remark, '')) LIKE '%warehouse%' THEN 1 "
        . "ELSE 2 END, id DESC LIMIT 1";

    $rst = mysqli_query($connect, $sql);
    if ($rst && mysqli_num_rows($rst) > 0) {
        return mysqli_fetch_assoc($rst);
    }

    return null;
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
    $tokenTable = defined('TOKEN_SETT') ? TOKEN_SETT : 'token_setting';

    // Auto-add chat_id column if it doesn't exist yet
    $colCheck = @mysqli_query($connect, "SHOW COLUMNS FROM `" . $tokenTable . "` LIKE 'chat_id'");
    if ($colCheck && mysqli_num_rows($colCheck) === 0) {
        @mysqli_query($connect, "ALTER TABLE `" . $tokenTable . "` ADD COLUMN `chat_id` VARCHAR(100) DEFAULT '' AFTER `bot_token`");
    }

    $tokenRow = sorInfoFindPreferredTokenRow($connect, $tokenTable);

    if (!$tokenRow) {
        $telegramErr = 'Token Setting not found. Please create Token Setting first.';
    } else {
        $botToken = trim((string) (isset($tokenRow['bot_token']) ? $tokenRow['bot_token'] : ''));

        // Prefer explicit chat_id when available, then fallback to auto-detection.
        $chatId = sorInfoResolveChatIdFromTokenRow($tokenRow);

        if ($botToken === '') {
            $telegramErr = 'Token Setting is incomplete. Bot Token is required.';
        } else {
            $invoiceNo = isset($requestRow['invoice_no']) ? (string) $requestRow['invoice_no'] : ('SOR-' . $requestId);
            $caption = "Invoice ID: " . $invoiceNo . "\n"
                . "Package: " . ($summary['package'] !== '' ? $summary['package'] : '-') . "\n"
                . "Product: " . ($summary['product'] !== '' ? $summary['product'] : '-') . "\n"
                . "Link: " . $orderLink;

            $apiBase = 'https://api.telegram.org/bot' . $botToken;
            $sendPhotoUrl = $apiBase . '/sendPhoto';
            $sendMessageUrl = $apiBase . '/sendMessage';

            if ($chatId === '') {
                $resolveErr = '';
                $chatId = sorInfoResolveTelegramChatId($apiBase, $resolveErr);
                if ($chatId === '') {
                    $telegramErr = 'Unable to detect Telegram chat automatically. Please enter the Chat ID in Settings > Token Setting, or send /start to your bot once, then try again.';
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
                $httpCode = 0;
                $resp = sorInfoTelegramRequest($sendPhotoUrl, $payload, $curlErr, $httpCode);

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
                    $httpCode2 = 0;
                    $resp2 = sorInfoTelegramRequest($sendMessageUrl, $msgPayload, $curlErr2, $httpCode2);

                    $decoded2 = json_decode((string) $resp2, true);
                    if (is_array($decoded2) && !empty($decoded2['ok'])) {
                        $telegramMsg = 'Telegram message sent successful.';
                    } else {
                        $telegramErr = 'Failed to send Telegram message.';
                        $details = array();

                        $apiErr = (is_array($decoded) && isset($decoded['description'])) ? trim((string) $decoded['description']) : '';
                        $apiErr2 = (is_array($decoded2) && isset($decoded2['description'])) ? trim((string) $decoded2['description']) : '';

                        if ($curlErr !== '') $details[] = 'Photo cURL: ' . $curlErr;
                        if ($apiErr !== '') $details[] = 'Photo API [HTTP ' . $httpCode . ']: ' . $apiErr;
                        if ($curlErr2 !== '') $details[] = 'Text cURL: ' . $curlErr2;
                        if ($apiErr2 !== '') $details[] = 'Text API [HTTP ' . $httpCode2 . ']: ' . $apiErr2;

                        if (count($details) === 0) {
                            if ($resp !== false && $resp !== '') {
                                $details[] = 'Photo response [HTTP ' . $httpCode . ']: ' . substr((string) $resp, 0, 200);
                            }
                            if ($resp2 !== false && $resp2 !== '') {
                                $details[] = 'Text response [HTTP ' . $httpCode2 . ']: ' . substr((string) $resp2, 0, 200);
                            }
                        }

                        if (count($details) > 0) {
                            $telegramErr .= ' ' . implode(' | ', $details);
                        }
                        $telegramErr .= ' (Chat ID: ' . $chatId . ')';
                    }
                } else {
                    $telegramMsg = 'Telegram message sent successful.';
                }

                // Save resolved chat_id back to token_setting for future sends
                if ($telegramMsg !== '' && $chatId !== '' && is_array($tokenRow) && isset($tokenRow['id'])) {
                    $storedChatId = isset($tokenRow['chat_id']) ? trim((string) $tokenRow['chat_id']) : '';
                    if ($storedChatId === '') {
                        $safeChatId = mysqli_real_escape_string($connect, $chatId);
                        $tokenId = (int) $tokenRow['id'];
                        @mysqli_query($connect, "UPDATE `" . $tokenTable . "` SET chat_id='" . $safeChatId . "' WHERE id='" . $tokenId . "' AND status='A'");
                    }
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
                        <form method="get" action="<?= $redirectPage ?>" class="m-0">
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
                                    <button type="button" class="btn sor-copy-btn" id="copyOrderLinkBtn" title="Copy Link" aria-label="Copy Link"><i class="fa-regular fa-copy"></i></button>
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

    function sorShowResultModal(message, isError) {
        var modalEl = document.getElementById('sorResultModal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.id = 'sorResultModal';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML =
                '<div class="modal-dialog modal-dialog-centered" style="font-family:\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">' +
                '  <div class="modal-content">' +
                '    <div class="modal-body fs-6 mt-3">' +
                '      <p id="sorResultModalText" style="text-align:center; font-weight:bold; font-size:25px;"></p>' +
                '    </div>' +
                '    <div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px">' +
                '      <button id="sorResultContinueBtn" type="button" class="btn" style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius:24px; text-transform:none;">Continue</button>' +
                '    </div>' +
                '  </div>' +
                '</div>';
            document.body.appendChild(modalEl);
        }

        var textEl = document.getElementById('sorResultModalText');
        if (textEl) {
            textEl.textContent = message || (isError ? 'Error occurred, please try again later.' : 'Telegram message sent successful.');
            textEl.style.color = isError ? '#c0392b' : '#4b4b4b';
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
                keyboard: false,
                backdrop: 'static'
            });
            modal.show();

            var continueBtn = document.getElementById('sorResultContinueBtn');
            if (continueBtn) {
                continueBtn.onclick = function () {
                    modal.hide();
                };
            }
        } else {
            alert(message || 'Done.');
        }
    }

    var copyBtn = document.getElementById('copyOrderLinkBtn');
    var orderLinkInput = document.getElementById('sorOrderLink');
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

    <?php if ($telegramMsg !== '') { ?>
        sorShowResultModal(<?= json_encode((string) $telegramMsg) ?>, false);
    <?php } else if ($telegramErr !== '') { ?>
        sorShowResultModal(<?= json_encode((string) $telegramErr) ?>, true);
    <?php } ?>
</script>
</body>
</html>