<?php
ob_start();
$pageTitle = 'FB-Ads WHT Submission';
$currentPagePin = 168;

include_once '../include/list_page_header.php';
include_once ROOT . '/include/fb_ads_topup_wht_submission_common.php';

$listPageRedirectPage = $SITEURL . '/finance/fb_ads_topup_wht_submission.php';
$listPageDeleteRedirectPage = $SITEURL . '/finance/fb_ads_topup_wht_submission_table.php';
$sourcePageUrl = $SITEURL . '/finance/fb_ads_topup_trans_table.php';
$listPageUrl = $SITEURL . '/finance/fb_ads_topup_wht_submission_table.php';
$submissionId = (int) input('id');
$requestedAction = strtoupper(trim((string) input('act')));
$formError = '';
$rows = array();
$submissionRef = '';
$remark = '';
$mode = $submissionId > 0 ? ($requestedAction === 'E' ? 'edit' : 'view') : 'create';

if (post('act') === 'D') {
    if (!isActionAllowed('Delete', $pinAccess)) {
        exit;
    }

    $deleteId = (int) post('id');
    $deleteRef = fbAdsWhtSubmissionResolveBatchRef($finance_connect, $deleteId);
    if ($deleteRef !== '') {
        $safeDeleteRef = mysqli_real_escape_string($finance_connect, $deleteRef);
        mysqli_begin_transaction($finance_connect);
        $deleteQuery = "UPDATE `" . FB_ADS_WHT_SUBMISSION . "`
            SET `status` = 'D', `update_by` = '" . mysqli_real_escape_string($finance_connect, (string) USER_ID) . "',
                `update_date` = CURDATE(), `update_time` = CURTIME()
            WHERE `submission_ref` = '" . $safeDeleteRef . "' AND `status` = 'A'";
        if (mysqli_query($finance_connect, $deleteQuery)) {
            mysqli_commit($finance_connect);
            audit_log(array(
                'log_act' => 'delete',
                'cdate' => date('Y-m-d'),
                'ctime' => date('H:i:s'),
                'uid' => USER_ID,
                'cby' => USER_ID,
                'act_msg' => USER_NAME . ' deleted FB-Ads WHT Submission [' . $deleteRef . '].',
                'page' => $pageTitle,
                'query_rec' => $deleteQuery,
                'query_table' => FB_ADS_WHT_SUBMISSION,
                'connect' => $connect,
            ));
        } else {
            mysqli_rollback($finance_connect);
        }
    }
    exit;
}

if ($mode === 'create' && !isActionAllowed('Add', $pinAccess)) {
    renderNotificationScript('You do not have permission to create FB-Ads WHT Submission.', 'error', $sourcePageUrl);
    exit;
}
if ($mode !== 'create' && !isActionAllowed('View', $pinAccess) && !isActionAllowed('Edit', $pinAccess)) {
    renderNotificationScript('You do not have permission to view FB-Ads WHT Submission.', 'error', $listPageUrl);
    exit;
}

if ($submissionId > 0) {
    $submissionRef = fbAdsWhtSubmissionResolveBatchRef($finance_connect, $submissionId);
    $rows = fbAdsWhtSubmissionGetBatchRows($finance_connect, $submissionRef);
    if ($submissionRef === '' || empty($rows)) {
        renderNotificationScript('Submission was not found.', 'error', $listPageUrl);
        exit;
    }
    $remark = (string) ($rows[0]['remark'] ?? '');
}

if (post('actionBtn') === 'submitWhtSubmission' || post('actionBtn') === 'updateWhtSubmission') {
    $action = post('actionBtn');
    $isCreate = $action === 'submitWhtSubmission';
    $remark = trim((string) post('remark'));

    if (!isActionAllowed($isCreate ? 'Add' : 'Edit', $pinAccess)) {
        $formError = 'You do not have permission to perform this action.';
    } else {
        if ($isCreate) {
            $selectedIds = fbAdsWhtSubmissionNormalizeIds(post('source_ids'));
            $rows = fbAdsWhtSubmissionGetSourceRows($finance_connect, $selectedIds);
            if (empty($selectedIds) || count($rows) !== count($selectedIds)) {
                $formError = 'One or more selected Facebook Ads transactions are no longer available.';
            } else {
                $duplicateIds = fbAdsWhtSubmissionGetDuplicateSourceIds($finance_connect, $selectedIds);
                if (!empty($duplicateIds)) {
                    $formError = 'The selected record is already submitted at FB-Ads WHT Submission.';
                }
            }
        } else {
            $submissionId = (int) post('submission_id');
            $submissionRef = fbAdsWhtSubmissionResolveBatchRef($finance_connect, $submissionId);
            $rows = fbAdsWhtSubmissionGetBatchRows($finance_connect, $submissionRef);
            if ($submissionRef === '' || empty($rows)) {
                $formError = 'Submission was not found.';
            }
        }

        $uploadedAttachment = '';
        if ($formError === '' && isset($_FILES['fb_ads_wht_attachment'])) {
            $uploadError = '';
            $uploadedAttachment = fbAdsWhtSubmissionStoreAttachment($_FILES['fb_ads_wht_attachment'], $uploadError);
            if ($uploadedAttachment === '' && $uploadError !== '') {
                $formError = $uploadError;
            }
        }

        if ($formError === '') {
            $attachment = $uploadedAttachment;
            if (!$isCreate && $attachment === '') {
                $attachment = isset($rows[0]['attachment']) ? (string) $rows[0]['attachment'] : '';
            }
            $submissionStatus = $attachment === '' ? 'pending' : 'success';
            $uploadedAbsolutePath = $uploadedAttachment !== ''
                ? rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($uploadedAttachment, '/'))
                : '';

            mysqli_begin_transaction($finance_connect);
            $transactionSuccess = true;
            if ($isCreate) {
                $lockedRows = fbAdsWhtSubmissionGetSourceRows($finance_connect, $selectedIds, true);
                $lockedDuplicateIds = fbAdsWhtSubmissionGetDuplicateSourceIds($finance_connect, $selectedIds);
                if (count($lockedRows) !== count($selectedIds)) {
                    $transactionSuccess = false;
                    $formError = 'One or more selected Facebook Ads transactions are no longer available.';
                } elseif (!empty($lockedDuplicateIds)) {
                    $transactionSuccess = false;
                    $formError = 'The selected record is already submitted at FB-Ads WHT Submission.';
                } else {
                    $rows = $lockedRows;
                    $submissionRef = fbAdsWhtSubmissionGenerateRef();
                    $insertStatement = mysqli_prepare($finance_connect, "INSERT INTO `" . FB_ADS_WHT_SUBMISSION . "`
                        (`submission_ref`, `source_transaction_id`, `payment_date`, `transaction_id`, `subtotal`, `sst`, `amount`, `remark`, `attachment`, `submission_status`, `create_by`, `create_date`, `create_time`, `status`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'A')");
                    if (!$insertStatement) {
                        $transactionSuccess = false;
                    } else {
                        foreach ($rows as $row) {
                            $sourceTransactionId = (int) $row['id'];
                            $paymentDate = (string) ($row['payment_date'] ?? '');
                            $transactionId = (string) ($row['transactionID'] ?? '');
                            $subtotal = (float) $row['subtotal'];
                            $sst = (float) $row['sst'];
                            $amount = (float) $row['amount'];
                            $createBy = (string) USER_ID;
                            mysqli_stmt_bind_param($insertStatement, 'sissdddssss', $submissionRef, $sourceTransactionId, $paymentDate, $transactionId, $subtotal, $sst, $amount, $remark, $attachment, $submissionStatus, $createBy);
                            if (!mysqli_stmt_execute($insertStatement)) {
                                $transactionSuccess = false;
                                break;
                            }
                        }
                        mysqli_stmt_close($insertStatement);
                    }
                }
            } else {
                $safeRemark = mysqli_real_escape_string($finance_connect, $remark);
                $safeAttachment = mysqli_real_escape_string($finance_connect, $attachment);
                $safeStatus = mysqli_real_escape_string($finance_connect, $submissionStatus);
                $safeUserId = mysqli_real_escape_string($finance_connect, (string) USER_ID);
                $safeRef = mysqli_real_escape_string($finance_connect, $submissionRef);
                $updateQuery = "UPDATE `" . FB_ADS_WHT_SUBMISSION . "`
                    SET `remark` = '" . $safeRemark . "', `attachment` = '" . $safeAttachment . "', `submission_status` = '" . $safeStatus . "',
                        `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME()
                    WHERE `submission_ref` = '" . $safeRef . "' AND `status` = 'A'";
                $transactionSuccess = mysqli_query($finance_connect, $updateQuery) === true;
            }

            if ($transactionSuccess) {
                mysqli_commit($finance_connect);
                audit_log(array(
                    'log_act' => $isCreate ? 'insert' : 'edit',
                    'cdate' => date('Y-m-d'),
                    'ctime' => date('H:i:s'),
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'act_msg' => USER_NAME . ($isCreate ? ' created' : ' updated') . ' FB-Ads WHT Submission [' . $submissionRef . '].',
                    'page' => $pageTitle,
                    'query_rec' => $isCreate ? 'INSERT ' . FB_ADS_WHT_SUBMISSION : $updateQuery,
                    'query_table' => FB_ADS_WHT_SUBMISSION,
                    'connect' => $connect,
                ));
                $_SESSION['fbAdsWhtSubmissionConfirmAction'] = $isCreate ? 'I' : 'E';
            } else {
                mysqli_rollback($finance_connect);
                if ($uploadedAbsolutePath !== '' && is_file($uploadedAbsolutePath)) {
                    @unlink($uploadedAbsolutePath);
                }
                if ($formError === '') {
                    $formError = 'Failed to save FB-Ads WHT Submission.';
                }
            }
        }
    }
}

if ($mode === 'create' && empty($rows)) {
    $selectedIds = fbAdsWhtSubmissionNormalizeIds(input('ids'));
    $rows = fbAdsWhtSubmissionGetSourceRows($finance_connect, $selectedIds);
    if (empty($selectedIds) || count($rows) !== count($selectedIds)) {
        renderNotificationScript('One or more selected Facebook Ads transactions are no longer available.', 'error', $sourcePageUrl);
        exit;
    }

    $duplicateIds = fbAdsWhtSubmissionGetDuplicateSourceIds($finance_connect, $selectedIds);
    if (!empty($duplicateIds)) {
        renderNotificationScript('The selected record is already submitted at FB-Ads WHT Submission.', 'error', $sourcePageUrl);
        exit;
    }
}

$totalAmount = 0;
$totalSubtotal = 0;
$totalSst = 0;
foreach ($rows as $row) {
    $totalAmount += (float) (isset($row['amount']) ? $row['amount'] : 0);
    $totalSubtotal += (float) (isset($row['subtotal']) ? $row['subtotal'] : 0);
    $totalSst += (float) (isset($row['sst']) ? $row['sst'] : 0);
}
$totalAmount = round($totalAmount, 2);
$totalSubtotal = round($totalSubtotal, 2);
$totalSst = round($totalSst, 2);
$currentAttachment = !empty($rows) && isset($rows[0]['attachment']) ? (string) $rows[0]['attachment'] : '';
$isFormMode = $mode === 'create' || $mode === 'edit';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .fb-ads-wht-summary { max-width: 420px; margin-left: auto; margin-right: auto; }
        .fb-ads-wht-summary td { padding: 6px 10px; }
        .fb-ads-wht-attachment { word-break: break-all; }
    </style>
</head>
<body>
<div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
    <div class="col-12 col-md-11">
        <div class="d-flex flex-column mb-3">
            <div class="row">
                <p><a href="<?= htmlspecialchars($SITEURL . '/dashboard.php', ENT_QUOTES, 'UTF-8') ?>">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <a href="<?= htmlspecialchars($sourcePageUrl, ENT_QUOTES, 'UTF-8') ?>">Facebook Ads Top Up Transaction</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="row">
                <div class="col-12 d-flex justify-content-between flex-wrap">
                    <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
            </div>
        </div>

        <?php if ($formError !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($submissionRef !== ''): ?>
            <div class="mb-3"><strong>Submission Reference:</strong> <?= htmlspecialchars($submissionRef, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction ID</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">SST: 8%</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['payment_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($row['transactionID'] ?? $row['transaction_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end"><?= number_format((float) ($row['subtotal'] ?? 0), 2, '.', '') ?></td>
                            <td class="text-end"><?= number_format((float) ($row['sst'] ?? 0), 2, '.', '') ?></td>
                            <td class="text-end"><?= number_format((float) ($row['amount'] ?? 0), 2, '.', '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <table class="table table-bordered fb-ads-wht-summary">
            <tr><th>Total Amount</th><td class="text-end"><?= number_format($totalAmount, 2, '.', '') ?></td></tr>
            <tr><th>Total Subtotal</th><td class="text-end"><?= number_format($totalSubtotal, 2, '.', '') ?></td></tr>
            <tr><th>Total GST / SST</th><td class="text-end"><?= number_format($totalSst, 2, '.', '') ?></td></tr>
        </table>

        <?php if ($currentAttachment !== ''): ?>
            <div class="mb-3 fb-ads-wht-attachment">
                <strong>Current Attachment:</strong>
                <a href="<?= htmlspecialchars(rtrim($SITEURL, '/') . '/' . ltrim($currentAttachment, '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"> <?= htmlspecialchars($currentAttachment, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        <?php endif; ?>

        <?php if ($isFormMode): ?>
            <form method="post" enctype="multipart/form-data">
                <?php if ($mode === 'create'): ?>
                    <input type="hidden" name="source_ids" value="<?= htmlspecialchars(implode(',', array_map(function ($row) { return (int) $row['id']; }, $rows)), ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                    <input type="hidden" name="submission_id" value="<?= (int) $submissionId ?>">
                <?php endif; ?>
                <div class="row mb-3 mt-5">
                    <div class="col-md-6">
                        <label class="form-label" for="fb_ads_wht_attachment">Attachment</label>
                        <input class="form-control" type="file" name="fb_ads_wht_attachment" id="fb_ads_wht_attachment" accept=".png,.jpg,.jpeg,.svg,.pdf">
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label" for="fb_ads_wht_remark">Remark</label>
                    <textarea class="form-control" name="remark" id="fb_ads_wht_remark" rows="3"><?= htmlspecialchars($remark, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <?= commonRenderCreateUpdateInfo(!empty($rows) ? $rows[0] : array(), $connect, $requestedAction) ?>
                </div>
                <div class="d-flex justify-content-center mt-4">
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" name="actionBtn" id="actionBtn" value="<?= $mode === 'create' ? 'submitWhtSubmission' : 'updateWhtSubmission' ?>"><?= $mode === 'create' ? 'Submit' : 'Edit FB-Ads WHT Submission' ?></button>
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" name="actionBtn" id="actionBtn" value="back" onclick="window.location.href='<?= htmlspecialchars($mode === 'create' ? $sourcePageUrl : $listPageUrl, ENT_QUOTES, 'UTF-8') ?>';">Back</button>
                </div>
            </form>
        <?php else: ?>
            <div class="form-group mb-3">
                <label class="form-label" for="fb_ads_wht_remark">Remark</label>
                <textarea class="form-control" id="fb_ads_wht_remark" rows="3" readonly><?= htmlspecialchars($remark, ENT_QUOTES, 'UTF-8') ?></textarea>
                <?= commonRenderCreateUpdateInfo(!empty($rows) ? $rows[0] : array(), $connect, $requestedAction) ?>
            </div>
            <div class="d-flex justify-content-center mt-4">
                <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" name="actionBtn" id="actionBtn" value="back" onclick="window.location.href='<?= htmlspecialchars($listPageUrl, ENT_QUOTES, 'UTF-8') ?>';">Back</button>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
if (isset($_SESSION['fbAdsWhtSubmissionConfirmAction'])) {
    $fbAdsWhtSubmissionConfirmAction = (string) $_SESSION['fbAdsWhtSubmissionConfirmAction'];
    unset($_SESSION['fbAdsWhtSubmissionConfirmAction']);
    echo '<script>confirmationDialog("","",' . json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',"",' . json_encode($listPageUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',' . json_encode($fbAdsWhtSubmissionConfirmAction, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>';
}
?>
</body>
</html>
