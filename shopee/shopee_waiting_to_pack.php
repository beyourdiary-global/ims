<?php
$currentPagePin = 146;
$pageTitle = 'Shopee Waiting To Pack';
$disablePinGroupPageTitleSync = true;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pageTitle = 'Shopee Waiting To Pack';
$processingAccess = checkPinByGroupId($connect, 146);
$legacyProcessingAccess = checkPinByGroupId($connect, 128);
$allOrdersAccess = checkPinByGroupId($connect, 130);
$canViewPage = isActionAllowed('View', $processingAccess) || isActionAllowed('View', $legacyProcessingAccess) || isActionAllowed('View', $allOrdersAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Shopee Waiting To Pack."); location.replace("../dashboard.php");</script>';
    exit;
}

$statusMessage = '';
$statusClass = 'success';

if (post('scanOrderBtn')) {
    $scanValue = trim((string) postSpaceFilter('scan_value'));
    $scanToken = '';
    if ($scanValue !== '' && preg_match('/[?&]t=([^&]+)/', $scanValue, $matches)) {
        $scanToken = trim((string) urldecode($matches[1]));
    } else {
        $safeScanValue = mysqli_real_escape_string($finance_connect, $scanValue);
        $tokenRst = mysqli_query($finance_connect, "SELECT token FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE token = '" . $safeScanValue . "' AND status = 'A' AND token_type = 'stock_out' ORDER BY id DESC LIMIT 1");
        if ($tokenRst && mysqli_num_rows($tokenRst) > 0) {
            $tokenRow = mysqli_fetch_assoc($tokenRst);
            $scanToken = isset($tokenRow['token']) ? (string) $tokenRow['token'] : '';
        } else {
            $orderRow = shopeeOmsLoadOrderByCode($finance_connect, $scanValue);
            if (!empty($orderRow)) {
                $existingTokenRst = mysqli_query($finance_connect, "SELECT token FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE order_id = " . (int) $orderRow['id'] . " AND status = 'A' AND token_type = 'stock_out' ORDER BY id DESC LIMIT 1");
                if ($existingTokenRst && mysqli_num_rows($existingTokenRst) > 0) {
                    $existingTokenRow = mysqli_fetch_assoc($existingTokenRst);
                    $scanToken = isset($existingTokenRow['token']) ? (string) $existingTokenRow['token'] : '';
                } else {
                    $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $orderRow, USER_ID);
                    if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']['token'])) {
                        $scanToken = (string) $tokenResult['token_row']['token'];
                    }
                }
            }
        }
    }

    if ($scanToken === '') {
        $statusClass = 'danger';
        $statusMessage = 'Scan value is invalid or order token was not found.';
    } else {
        $scanResult = shopeeOmsProcessWarehouseScanByToken($connect, $finance_connect, $scanToken, USER_ID, USER_GROUP, $pageTitle);
        $statusClass = !empty($scanResult['success']) ? 'success' : 'danger';
        $statusMessage = isset($scanResult['message']) ? (string) $scanResult['message'] : 'Unable to process warehouse scan.';
    }
}

$whereStatuses = array('TP', 'To Pack', 'WP', 'Waiting Packing');
$safeStatuses = array();
foreach ($whereStatuses as $statusValue) {
    $safeStatuses[] = "'" . mysqli_real_escape_string($finance_connect, $statusValue) . "'";
}
$orderRows = array();
$orderSql = "SELECT * FROM `" . SHOPEE_SG_ORDER_REQ . "` WHERE status = 'A' AND order_status IN (" . implode(',', $safeStatuses) . ") ORDER BY date DESC, time DESC, id DESC";
$orderRst = mysqli_query($finance_connect, $orderSql);
if ($orderRst) {
    while ($row = mysqli_fetch_assoc($orderRst)) {
        $orderRows[] = $row;
    }
}

$orderTokenMap = array();
if (!empty($orderRows)) {
    $orderIds = array();
    foreach ($orderRows as $orderRow) {
        $orderIds[] = (int) (isset($orderRow['id']) ? $orderRow['id'] : 0);
    }
    $orderIds = array_values(array_unique(array_filter($orderIds)));

    if (!empty($orderIds)) {
        $tokenSql = "SELECT order_id, token FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE status = 'A' AND token_type = 'stock_out' AND order_id IN (" . implode(',', $orderIds) . ") ORDER BY order_id ASC, id DESC";
        $tokenRst = mysqli_query($finance_connect, $tokenSql);
        if ($tokenRst) {
            while ($tokenRow = mysqli_fetch_assoc($tokenRst)) {
                $tokenOrderId = (int) (isset($tokenRow['order_id']) ? $tokenRow['order_id'] : 0);
                if ($tokenOrderId > 0 && !isset($orderTokenMap[$tokenOrderId])) {
                    $orderTokenMap[$tokenOrderId] = isset($tokenRow['token']) ? (string) $tokenRow['token'] : '';
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
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($pageTitle) ?></h2>
                <div class="text-muted">To Pack orders only. Scan token, scan link, or Order ID to move order to Shipped.</div>
            </div>
        </div>

        <?php if ($statusMessage !== '') { ?>
            <div class="alert alert-<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusMessage) ?></div>
        <?php } ?>

        <div class="card p-3 mb-4">
            <form method="post" class="row g-3 align-items-end" id="waitingToPackScanForm">
                <div class="col-12 col-md-9">
                    <label class="form-label" for="scan_value">Scan Input</label>
                    <input class="form-control" type="text" id="scan_value" name="scan_value" placeholder="Paste warehouse token, stock-out link, or Order ID">
                </div>
                <div class="col-12 col-md-3">
                    <button class="btn btn-primary w-100" type="submit" name="scanOrderBtn" value="1">Process Scan</button>
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label" for="scan_qr_image">QR Code Attachment</label>
                    <input class="form-control" type="file" id="scan_qr_image" accept="image/*">
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Package</th>
                        <th>Airbill No</th>
                        <th>Link</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orderRows)) { ?>
                        <?php foreach ($orderRows as $row) { ?>
                            <?php
                            $customerName = trim((string) (isset($row['customer_name']) ? $row['customer_name'] : ''));
                            if ($customerName === '') {
                                $customerName = trim((string) (isset($row['buyer']) ? $row['buyer'] : '-'));
                            }
                            $packageSummary = shopeeOmsBuildOrderProductSummary($connect, $row);
                            $tokenLink = '';
                            $tokenValue = isset($orderTokenMap[(int) $row['id']]) ? (string) $orderTokenMap[(int) $row['id']] : '';
                            if ($tokenValue !== '') {
                                $tokenLink = $SITEURL . '/warehouse_stock_in_scan.php?t=' . urlencode($tokenValue);
                            }
                            ?>
                            <tr>
                                <td><a href="<?= $SITEURL ?>/shopee/shopee_order_req.php?id=<?= (int) $row['id'] ?>"><?= htmlspecialchars((string) $row['orderID']) ?></a></td>
                                <td><?= htmlspecialchars($customerName !== '' ? $customerName : '-') ?></td>
                                <td><?= htmlspecialchars(!empty($packageSummary['bundle_name']) ? $packageSummary['bundle_name'] : '-') ?></td>
                                <td><?= htmlspecialchars((string) (isset($row['airbill_no']) && trim((string) $row['airbill_no']) !== '' ? $row['airbill_no'] : '-')) ?></td>
                                <td>
                                    <?php if ($tokenLink !== '') { ?>
                                        <a href="<?= htmlspecialchars($tokenLink) ?>" target="_blank">Open Scan Link</a>
                                    <?php } else { ?>
                                        <span class="text-muted">Will generate on scan</span>
                                    <?php } ?>
                                </td>
                                <td><?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($row['order_status']) ? $row['order_status'] : '')) ?></td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="6" class="text-center">No To Pack orders found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
    <script src="<?= $SITEURL ?>/header/js/jsQR.js"></script>
    <script>
        (function () {
            var form = document.getElementById('waitingToPackScanForm');
            var scanValueInput = document.getElementById('scan_value');
            var qrFileInput = document.getElementById('scan_qr_image');
            var submitButton = form ? form.querySelector('button[name="scanOrderBtn"]') : null;

            if (!form || !scanValueInput || !qrFileInput) {
                return;
            }

            function readFileAsImage(file) {
                return new Promise(function (resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function (event) {
                        var image = new Image();
                        image.onload = function () {
                            resolve(image);
                        };
                        image.onerror = function () {
                            reject(new Error('Unable to load QR image.'));
                        };
                        image.src = event.target.result;
                    };
                    reader.onerror = function () {
                        reject(new Error('Unable to read QR image.'));
                    };
                    reader.readAsDataURL(file);
                });
            }

            async function decodeQrFromFile(file) {
                if (typeof jsQR === 'undefined') {
                    throw new Error('jsQR is not loaded. Please refresh the page and try again.');
                }

                var image = await readFileAsImage(file);
                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');

                if (!ctx) {
                    throw new Error('Canvas is not available in this browser.');
                }

                canvas.width = image.width;
                canvas.height = image.height;
                ctx.drawImage(image, 0, 0);

                var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var code = jsQR(imageData.data, canvas.width, canvas.height);
                if (!code || !code.data) {
                    throw new Error('No QR code was detected in the uploaded image.');
                }

                return String(code.data);
            }

            form.addEventListener('submit', async function (event) {
                var scanValue = scanValueInput.value.trim();
                var qrFile = qrFileInput.files && qrFileInput.files.length ? qrFileInput.files[0] : null;

                if (scanValue !== '' || !qrFile) {
                    return;
                }

                event.preventDefault();

                try {
                    var decodedValue = await decodeQrFromFile(qrFile);
                    scanValueInput.value = decodedValue;
                    if (submitButton && typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitButton);
                    } else {
                        var hiddenSubmit = document.createElement('input');
                        hiddenSubmit.type = 'hidden';
                        hiddenSubmit.name = 'scanOrderBtn';
                        hiddenSubmit.value = '1';
                        form.appendChild(hiddenSubmit);
                        form.submit();
                    }
                } catch (error) {
                    alert(error && error.message ? error.message : 'Unable to scan the uploaded QR image.');
                }
            });
        })();
    </script>
</body>
</html>
