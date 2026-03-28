<?php
/**
 * EasyParcel Tracking Diagnostic.
 * Run this on the live site to diagnose why tracking status fails.
 * Delete this file after debugging is complete.
 */
$pageTitle = 'Tracking Diagnostic';
$isFinance = 1;

include_once '../menuHeader.php';

if (!isset($_SESSION['userid'])) {
    echo '<script>alert("Login required.");location.href = "' . $SITEURL . '/index.php";</script>';
    exit;
}

include_once ROOT . '/include/common.php';

$results = array();
$testTrackingNo = isset($_POST['trackingNo']) ? trim((string) $_POST['trackingNo']) : 'MYJZSROZEEEM3T5';
$testCountry = isset($_POST['country']) ? trim((string) $_POST['country']) : 'MY';

if (isset($_POST['runDiag'])) {

    // Step 1: Environment check
    $results[] = array('step' => 'Environment', 'status' => 'INFO', 'detail' =>
        'PHP ' . PHP_VERSION .
        ' | SAPI: ' . PHP_SAPI .
        ' | allow_url_fopen: ' . ini_get('allow_url_fopen') .
        ' | $siteOrlocalMode: ' . (isset($siteOrlocalMode) ? ($siteOrlocalMode ? 'true (live)' : 'false (local)') : 'undefined'));

    // Step 2: Show EasyParcel config
    $cfg = sorGetEasyParcelConfig($testCountry);
    $results[] = array('step' => 'EasyParcel Config (' . htmlspecialchars($testCountry) . ')', 'status' => 'INFO', 'detail' =>
        'Domain: ' . $cfg['domain'] .
        ' | Auth: ' . substr($cfg['auth'], 0, 4) . '...' .
        ' | API: ' . $cfg['api'] .
        ' | Is Demo: ' . (stripos($cfg['domain'], 'demo.connect') !== false ? 'YES' : 'NO'));

    // Step 3: Test raw EasyParcel API call
    $url = $cfg['domain'] . 'EPTrackingBulk';
    $postparam = array(
        'authentication' => $cfg['auth'],
        'api' => $cfg['api'],
        'bulk' => array(
            array('awb' => $testTrackingNo),
        ),
    );

    $postData = http_build_query($postparam);

    $results[] = array('step' => 'Request Details', 'status' => 'INFO', 'detail' =>
        'URL: ' . $url .
        ' | POST body length: ' . strlen($postData) .
        ' | POST body: ' . substr($postData, 0, 500));

    $opts = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 20,
            'ignore_errors' => true,
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        ),
    );
    $ctx = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);

    $httpCode = 0;
    $responseHeaders = '';
    if (isset($http_response_header) && is_array($http_response_header)) {
        $responseHeaders = implode("\n", $http_response_header);
        foreach ($http_response_header as $hdr) {
            if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $hdr, $m)) {
                $httpCode = (int) $m[1];
            }
        }
    }

    $gotResponse = ($response !== false && $response !== null && $response !== '');

    $results[] = array('step' => 'HTTP Response', 'status' => $gotResponse ? 'OK' : 'FAIL', 'detail' =>
        'HTTP ' . $httpCode .
        ' | Response length: ' . ($gotResponse ? strlen($response) : 0) .
        ' | Headers: ' . substr($responseHeaders, 0, 500));

    $results[] = array('step' => 'Raw Response Body', 'status' => 'INFO', 'detail' =>
        $gotResponse ? substr($response, 0, 2000) : '(empty or false)');

    if ($gotResponse) {
        $json = json_decode($response, true);
        $jsonError = json_last_error_msg();

        if (is_array($json)) {
            $results[] = array('step' => 'JSON Parse', 'status' => 'OK', 'detail' =>
                'Parsed OK. Keys: ' . implode(', ', array_keys($json)));

            // Show api_status
            $apiStatus = isset($json['api_status']) ? (string) $json['api_status'] : '(not set)';
            $error = isset($json['error']) ? (string) $json['error'] : '(not set)';
            $isSuccess = ($apiStatus === '0' || $apiStatus === '' || strtolower($apiStatus) === 'success');
            $results[] = array('step' => 'API Status', 'status' => $isSuccess ? 'OK' : 'FAIL', 'detail' =>
                'api_status: ' . $apiStatus . ' | error: ' . $error);

            // Show result array
            if (isset($json['result'])) {
                $results[] = array('step' => 'Result Data', 'status' => 'INFO', 'detail' =>
                    substr(json_encode($json['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 3000));
            } else {
                $results[] = array('step' => 'Result Data', 'status' => 'FAIL', 'detail' => 'No "result" key in response');
            }

            // Try to extract status the same way the function does
            $status = '';
            if (isset($json['result'][0]['latest_status'])) {
                $status = (string) $json['result'][0]['latest_status'];
                $results[] = array('step' => 'Status Extraction', 'status' => 'OK', 'detail' => 'From result[0].latest_status: ' . $status);
            } else if (isset($json['result'][0]['status'])) {
                $status = (string) $json['result'][0]['status'];
                $results[] = array('step' => 'Status Extraction', 'status' => 'OK', 'detail' => 'From result[0].status: ' . $status);
            } else if (isset($json['result'][0]['detail'][0]['content'])) {
                $status = (string) $json['result'][0]['detail'][0]['content'];
                $results[] = array('step' => 'Status Extraction', 'status' => 'OK', 'detail' => 'From result[0].detail[0].content: ' . $status);
            } else {
                $results[] = array('step' => 'Status Extraction', 'status' => 'FAIL', 'detail' => 'Could not find status in any known path');
            }
        } else {
            $results[] = array('step' => 'JSON Parse', 'status' => 'FAIL', 'detail' =>
                'JSON error: ' . $jsonError . ' | Raw (first 500): ' . substr($response, 0, 500));
        }
    }

    // Step 4: Test the actual function
    $funcResult = sorFetchTrackingStatusEasyParcel($testTrackingNo, $testCountry);
    $results[] = array('step' => 'sorFetchTrackingStatusEasyParcel()', 'status' => ($funcResult !== '' && stripos($funcResult, 'failed') === false && stripos($funcResult, 'unavailable') === false) ? 'OK' : 'FAIL',
        'detail' => $funcResult !== '' ? $funcResult : '(empty string - skipped or no result)');

    // Step 5: Test courier scrape fallback
    // Find the courier for this tracking number from DB
    $testRequestId = isset($_POST['requestId']) ? (int) $_POST['requestId'] : 0;
    if ($testRequestId > 0) {
        $reqRst = mysqli_query($finance_connect, "SELECT id, tracking_no, courier_id FROM " . STOCK_ORDER_REQ . " WHERE id = '" . $testRequestId . "' LIMIT 1");
        if ($reqRst && ($reqRow = mysqli_fetch_assoc($reqRst))) {
            $courierId = (int) $reqRow['courier_id'];
            $trackingLink = '';
            if ($courierId > 0) {
                $cRst = mysqli_query($connect, "SELECT tracking_link, name FROM " . COURIER . " WHERE id = '" . $courierId . "' LIMIT 1");
                if ($cRst && ($cRow = mysqli_fetch_assoc($cRst))) {
                    $trackingLink = isset($cRow['tracking_link']) ? trim((string) $cRow['tracking_link']) : '';
                    $results[] = array('step' => 'Courier Info', 'status' => 'INFO', 'detail' =>
                        'Courier ID: ' . $courierId .
                        ' | Name: ' . (isset($cRow['name']) ? $cRow['name'] : '') .
                        ' | Tracking Link Template: ' . ($trackingLink !== '' ? $trackingLink : '(empty)'));
                }
            } else {
                $results[] = array('step' => 'Courier Info', 'status' => 'FAIL', 'detail' => 'courier_id is 0 for this request');
            }

            $trackingUrl = sorBuildTrackingUrl($trackingLink, $testTrackingNo);
            $results[] = array('step' => 'Built Tracking URL', 'status' => $trackingUrl !== '' ? 'OK' : 'FAIL',
                'detail' => $trackingUrl !== '' ? $trackingUrl : '(empty - no tracking link or tracking number)');

            if ($trackingUrl !== '') {
                $scrapeResult = sorFetchTrackingStatus($trackingUrl);
                $results[] = array('step' => 'Courier Scrape Result', 'status' => stripos($scrapeResult, 'Detected:') !== false ? 'OK' : 'FAIL',
                    'detail' => $scrapeResult);
            }

            // Try tracking.my fallback
            $altUrl = 'https://www.tracking.my/' . rawurlencode($testTrackingNo);
            $altResult = sorFetchTrackingStatus($altUrl);
            $results[] = array('step' => 'tracking.my Fallback', 'status' => stripos($altResult, 'Detected:') !== false ? 'OK' : 'FAIL',
                'detail' => $altResult);
        } else {
            $results[] = array('step' => 'DB Lookup', 'status' => 'FAIL', 'detail' => 'Request ID ' . $testRequestId . ' not found in DB');
        }
    } else {
        // Even without request ID, test tracking.my with auto-detected slug
        $slug = sorResolveTrackingMySlug('', $testTrackingNo);
        $results[] = array('step' => 'Auto-detect Slug', 'status' => $slug !== '' ? 'OK' : 'FAIL',
            'detail' => 'Slug: ' . ($slug !== '' ? $slug : '(none - could not detect)') . ' | From tracking number: ' . $testTrackingNo);

        if ($slug !== '') {
            $altUrl = 'https://www.tracking.my/' . $slug . '/' . rawurlencode($testTrackingNo);
            $results[] = array('step' => 'tracking.my URL', 'status' => 'INFO', 'detail' => $altUrl);
            $altResult = sorFetchTrackingStatus($altUrl);
            $results[] = array('step' => 'tracking.my Result', 'status' => stripos($altResult, 'Detected:') !== false ? 'OK' : 'FAIL',
                'detail' => $altResult);
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
        <h2>Tracking Diagnostic</h2>
        <p class="text-muted">Run this on the live site to diagnose why tracking status fails. Delete after debugging.</p>

        <form method="post" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Tracking Number</label>
                    <input type="text" name="trackingNo" class="form-control" value="<?= htmlspecialchars($testTrackingNo, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. MYJZSROZEEEM3T5">
                </div>
                <div class="col-auto">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="MY" <?= $testCountry === 'MY' ? 'selected' : '' ?>>MY</option>
                        <option value="SG" <?= $testCountry === 'SG' ? 'selected' : '' ?>>SG</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Request ID (optional, for courier test)</label>
                    <input type="number" name="requestId" class="form-control" value="<?= isset($_POST['requestId']) ? (int) $_POST['requestId'] : '' ?>" placeholder="e.g. 6">
                </div>
                <div class="col-auto">
                    <button type="submit" name="runDiag" value="1" class="btn btn-primary">Run Diagnostic</button>
                    <a href="<?= $SITEURL ?>/finance/stock_order_request_table.php" class="btn btn-outline-secondary ms-2">Back</a>
                </div>
            </div>
        </form>

        <?php if (!empty($results)) { ?>
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th style="width:220px;">Step</th>
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
                    <td style="word-break:break-all; font-size:13px; white-space:pre-wrap;"><?= htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') ?></td>
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
