<?php
/**
 * Telegram connectivity diagnostic.
 * Visit this page on the live site to diagnose why Telegram sending fails.
 * Delete this file after debugging is complete.
 */
$pageTitle = 'Telegram Diagnostic';
$isFinance = 1;

include_once '../menuHeader.php';

if (!isset($_SESSION['userid'])) {
    echo '<script>alert("Login required.");location.href = "' . $SITEURL . '/index.php";</script>';
    exit;
}

$results = array();

if (isset($_POST['runDiag'])) {
    $tokenTable = defined('TOKEN_SETT') ? TOKEN_SETT : 'token_setting';

    // Step 1: Find token
    $sql = "SELECT * FROM `" . $tokenTable . "` WHERE status='A' ORDER BY id DESC LIMIT 1";
    $rst = mysqli_query($connect, $sql);
    $tokenRow = ($rst && mysqli_num_rows($rst) > 0) ? mysqli_fetch_assoc($rst) : null;

    if (!$tokenRow) {
        $results[] = array('step' => 'Token Lookup', 'status' => 'FAIL', 'detail' => 'No active token_setting row found.');
    } else {
        $botToken = trim((string) (isset($tokenRow['bot_token']) ? $tokenRow['bot_token'] : ''));
        $chatId = isset($tokenRow['chat_id']) ? trim((string) $tokenRow['chat_id']) : '';
        $results[] = array('step' => 'Token Lookup', 'status' => 'OK', 'detail' =>
            'ID: ' . $tokenRow['id'] .
            ' | Name: ' . (isset($tokenRow['name']) ? $tokenRow['name'] : '') .
            ' | Bot Token: ' . ($botToken !== '' ? substr($botToken, 0, 10) . '...' : 'EMPTY') .
            ' | Chat ID: ' . ($chatId !== '' ? $chatId : 'EMPTY'));

        if ($botToken === '') {
            $results[] = array('step' => 'Bot Token', 'status' => 'FAIL', 'detail' => 'bot_token is empty.');
        } else {
            $apiBase = 'https://api.telegram.org/bot' . $botToken;

            // Step 2: Test basic HTTPS connectivity (strict SSL)
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiBase . '/getMe');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $resp1 = curl_exec($ch);
            $err1 = curl_error($ch);
            $http1 = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $sslOk = ($http1 > 0);
            curl_close($ch);

            $results[] = array('step' => 'SSL Strict (getMe)', 'status' => $sslOk ? 'OK' : 'FAIL',
                'detail' => 'HTTP ' . $http1 . ' | cURL error: ' . ($err1 !== '' ? $err1 : 'none') . ' | Response: ' . substr((string) $resp1, 0, 200));

            // Step 3: Test without SSL verification
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $apiBase . '/getMe');
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
            $resp2 = curl_exec($ch2);
            $err2 = curl_error($ch2);
            $http2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $noSslOk = ($http2 > 0);
            curl_close($ch2);

            $results[] = array('step' => 'SSL Disabled (getMe)', 'status' => $noSslOk ? 'OK' : 'FAIL',
                'detail' => 'HTTP ' . $http2 . ' | cURL error: ' . ($err2 !== '' ? $err2 : 'none') . ' | Response: ' . substr((string) $resp2, 0, 200));

            // Step 4: Test with file_get_contents
            $streamResp = @file_get_contents($apiBase . '/getMe');
            $streamOk = ($streamResp !== false && $streamResp !== '');
            $results[] = array('step' => 'file_get_contents (getMe)', 'status' => $streamOk ? 'OK' : 'FAIL',
                'detail' => $streamOk ? substr($streamResp, 0, 200) : 'Failed or disabled (allow_url_fopen=' . ini_get('allow_url_fopen') . ')');

            // Step 5: Test sendMessage via file_get_contents (the method that works)
            if ($chatId !== '') {
                $testPayload = http_build_query(array(
                    'chat_id' => $chatId,
                    'text' => 'Diagnostic test from IMS at ' . date('Y-m-d H:i:s'),
                ));

                $sendOpts = array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => $testPayload,
                        'timeout' => 30,
                        'ignore_errors' => true,
                    ),
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ),
                );
                $sendCtx = stream_context_create($sendOpts);
                $resp3 = @file_get_contents($apiBase . '/sendMessage', false, $sendCtx);
                $http3 = 0;
                if (isset($http_response_header) && is_array($http_response_header)) {
                    foreach ($http_response_header as $hdr) {
                        if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $hdr, $m)) {
                            $http3 = (int) $m[1];
                        }
                    }
                }

                $decoded3 = json_decode((string) $resp3, true);
                $sendOk = (is_array($decoded3) && !empty($decoded3['ok']));

                $results[] = array('step' => 'sendMessage (stream)', 'status' => $sendOk ? 'OK' : 'FAIL',
                    'detail' => 'HTTP ' . $http3 . ' | Response: ' . substr((string) $resp3, 0, 300));
            } else {
                $results[] = array('step' => 'sendMessage', 'status' => 'SKIP', 'detail' => 'No chat_id configured. Cannot test send.');
            }

            // Step 6: PHP/cURL info
            $results[] = array('step' => 'Environment', 'status' => 'INFO',
                'detail' => 'PHP ' . PHP_VERSION . ' | cURL ' . (function_exists('curl_version') ? curl_version()['version'] : '?')
                    . ' | SSL: ' . (function_exists('curl_version') ? curl_version()['ssl_version'] : '?')
                    . ' | OS: ' . PHP_OS
                    . ' | SAPI: ' . PHP_SAPI
                    . ' | curl.cainfo: ' . (ini_get('curl.cainfo') ?: 'not set')
                    . ' | openssl.cafile: ' . (ini_get('openssl.cafile') ?: 'not set'));
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
<div class="container-fluid mt-3">
    <div class="col-12 col-md-10 mx-auto">
        <h2>Telegram Diagnostic</h2>
        <p class="text-muted">Run this on the live site to diagnose Telegram connectivity issues.</p>

        <form method="post" class="mb-4">
            <button type="submit" name="runDiag" value="1" class="btn btn-primary">Run Diagnostic</button>
            <a href="<?= $SITEURL ?>/finance/stock_order_request_table.php" class="btn btn-outline-secondary ms-2">Back</a>
        </form>

        <?php if (!empty($results)) { ?>
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th style="width:200px;">Step</th>
                    <th style="width:80px;">Status</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r) {
                    $badge = 'secondary';
                    if ($r['status'] === 'OK') $badge = 'success';
                    else if ($r['status'] === 'FAIL') $badge = 'danger';
                    else if ($r['status'] === 'SKIP') $badge = 'warning';
                ?>
                <tr>
                    <td><?= htmlspecialchars($r['step'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= $r['status'] ?></span></td>
                    <td style="word-break:break-all; font-size:13px;"><?= htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } ?>
    </div>
</div>
<script>
    checkCurrentPage('Stock Order Request', '');
    dropdownMenuDispFix();
    setButtonColor();
    preloader(300);
</script>
</body>
</html>
