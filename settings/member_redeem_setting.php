<?php
ob_start();

$pageTitle = 'Member Redeem Setting';
$currentPagePin = 163;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedPageTitle !== '') {
    $pageTitle = $resolvedPageTitle;
}

$tblName = MEMBER_REDEEM_SETTING;
$pinAccess = checkCurrentPin($connect, $pageTitle);
$redirectPage = $SITEURL . '/dashboard.php';
$pageUrl = $SITEURL . '/settings/member_redeem_setting.php';
$canView = isActionAllowed('View', $pinAccess);
$canManage = isActionAllowed('Add', $pinAccess) || isActionAllowed('Edit', $pinAccess);
$fieldDisabled = $canManage ? '' : 'readonly disabled';
$csrfSessionKey = 'member_redeem_setting_csrf_token';
$popupSessionKey = 'member_redeem_setting_popup';

if (!$canView) {
    renderNotificationScript('You do not have permission to view Member Redeem Setting.', 'error', $redirectPage, 1200, true);
    exit;
}

if (!memberRedeemTableExists($connect)) {
    renderNotificationScript('Member Redeem Setting table is not ready. Please run insert_table.php first.', 'error', $redirectPage, 1200, true);
    exit;
}

if (empty($_SESSION[$csrfSessionKey])) {
    $_SESSION[$csrfSessionKey] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION[$csrfSessionKey];

function memberRedeemSettingSetPopup($message, $act = 'ErrMO')
{
    global $popupSessionKey;
    $_SESSION[$popupSessionKey] = array(
        'message' => (string) $message,
        'act' => (string) $act,
    );
}

function memberRedeemSettingRenderPopupScript($pageTitle, $returnUrl)
{
    global $popupSessionKey;
    if (empty($_SESSION[$popupSessionKey]) || !is_array($_SESSION[$popupSessionKey])) {
        return;
    }

    $popup = $_SESSION[$popupSessionKey];
    unset($_SESSION[$popupSessionKey]);

    $message = isset($popup['message']) ? (string) $popup['message'] : '';
    $act = isset($popup['act']) && trim((string) $popup['act']) !== '' ? (string) $popup['act'] : 'ErrMO';

    echo '<script>confirmationDialog("", '
        . json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ', '
        . json_encode((string) $pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ', "", '
        . json_encode((string) $returnUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ', '
        . json_encode($act, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . ');</script>';
}

function memberRedeemSettingBlankRow()
{
    return array(
        'id' => 0,
        'point_tier' => '',
        'redeemable_gift' => '',
        'price' => '',
        'selling_price' => '',
        'cost_ratio' => 0,
        'remark' => '',
        'shopee_lazada_redeem_order' => '',
        'private_redeem_order' => '',
    );
}

function memberRedeemSettingParseNumber($value, &$isValid)
{
    $rawValue = trim((string) $value);
    if ($rawValue === '') {
        return 0.0;
    }

    $normalizedValue = str_replace(',', '', $rawValue);
    if (!is_numeric($normalizedValue)) {
        $isValid = false;
        return 0.0;
    }

    return round((float) $normalizedValue, 2);
}

function memberRedeemSettingParseInteger($value, &$isValid)
{
    $rawValue = trim((string) $value);
    if ($rawValue === '') {
        return 0;
    }

    if (!preg_match('/^-?\d+$/', $rawValue)) {
        $isValid = false;
        return 0;
    }

    return (int) $rawValue;
}

function memberRedeemSettingFormatDecimal($value)
{
    if ($value === null || trim((string) $value) === '') {
        return '';
    }

    $value = (float) $value;
    if (abs($value - round($value)) < 0.00001) {
        return number_format($value, 0, '.', '');
    }

    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
}

function memberRedeemSettingFormatRatio($value)
{
    $value = max(0, (float) $value);
    if (abs($value - round($value)) < 0.00001) {
        return number_format($value, 0, '.', '') . '%';
    }

    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
}

function memberRedeemSettingDescribeRow($row)
{
    return implode(' | ', array(
        'Point Tier: ' . (int) ($row['point_tier'] ?? 0),
        'Gift: ' . trim((string) ($row['redeemable_gift'] ?? '')),
        'Price: RM' . number_format((float) ($row['price'] ?? 0), 2, '.', ''),
        'Selling Price: RM' . number_format((float) ($row['selling_price'] ?? 0), 2, '.', ''),
        'Cost Ratio: ' . memberRedeemSettingFormatRatio($row['cost_ratio'] ?? 0),
        'Remark: ' . trim((string) ($row['remark'] ?? '')),
        'Shopee/Lazada Redeem Order: ' . (int) ($row['shopee_lazada_redeem_order'] ?? 0),
        'Private Redeem Order: ' . (int) ($row['private_redeem_order'] ?? 0),
    ));
}

function memberRedeemSettingWriteAudit($connect, $pageTitle, $logAct, $message, $query = '', $oldVal = '', $newVal = '', $changes = '')
{
    audit_log(array(
        'log_act' => $logAct,
        'cdate' => $GLOBALS['cdate'] ?? date('Y-m-d'),
        'ctime' => $GLOBALS['ctime'] ?? date('H:i:s'),
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $query,
        'query_table' => MEMBER_REDEEM_SETTING,
        'oldval' => $oldVal,
        'newval' => $newVal,
        'changes' => $changes,
        'act_msg' => $message,
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$existingRows = memberRedeemFetchRows($connect, true);
$formRows = !empty($existingRows) ? $existingRows : array(memberRedeemSettingBlankRow());
$errors = array();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    memberRedeemSettingWriteAudit(
        $connect,
        $pageTitle,
        'view',
        USER_NAME . ' viewed the page <b>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</b>.'
    );
}

if (post('actionBtn') === 'saveRedeemSetting') {
    if (!$canManage) {
        renderNotificationScript('You do not have permission to save Member Redeem Setting.', 'error', $pageUrl, 1200, true);
        exit;
    }

    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Invalid session token. Please refresh the page and try again.';
    }

    $rowIds = isset($_POST['row_id']) && is_array($_POST['row_id']) ? $_POST['row_id'] : array();
    $pointTiers = isset($_POST['point_tier']) && is_array($_POST['point_tier']) ? $_POST['point_tier'] : array();
    $gifts = isset($_POST['redeemable_gift']) && is_array($_POST['redeemable_gift']) ? $_POST['redeemable_gift'] : array();
    $prices = isset($_POST['price']) && is_array($_POST['price']) ? $_POST['price'] : array();
    $sellingPrices = isset($_POST['selling_price']) && is_array($_POST['selling_price']) ? $_POST['selling_price'] : array();
    $remarks = isset($_POST['remark']) && is_array($_POST['remark']) ? $_POST['remark'] : array();
    $shopeeOrders = isset($_POST['shopee_lazada_redeem_order']) && is_array($_POST['shopee_lazada_redeem_order']) ? $_POST['shopee_lazada_redeem_order'] : array();
    $privateOrders = isset($_POST['private_redeem_order']) && is_array($_POST['private_redeem_order']) ? $_POST['private_redeem_order'] : array();

    $maxRowCount = max(
        count($rowIds),
        count($pointTiers),
        count($gifts),
        count($prices),
        count($sellingPrices),
        count($remarks),
        count($shopeeOrders),
        count($privateOrders),
        1
    );

    $formRows = array();
    for ($index = 0; $index < $maxRowCount; $index++) {
        $pointTierRaw = trim((string) ($pointTiers[$index] ?? ''));
        $giftRaw = trim((string) ($gifts[$index] ?? ''));
        $priceRaw = trim((string) ($prices[$index] ?? ''));
        $sellingPriceRaw = trim((string) ($sellingPrices[$index] ?? ''));
        $remarkRaw = trim((string) ($remarks[$index] ?? ''));
        $shopeeOrderRaw = trim((string) ($shopeeOrders[$index] ?? ''));
        $privateOrderRaw = trim((string) ($privateOrders[$index] ?? ''));
        $rowId = (int) ($rowIds[$index] ?? 0);

        $isBlankRow = $pointTierRaw === ''
            && $giftRaw === ''
            && $priceRaw === ''
            && $sellingPriceRaw === ''
            && $remarkRaw === ''
            && $shopeeOrderRaw === ''
            && $privateOrderRaw === '';

        if ($isBlankRow) {
            continue;
        }

        $numericValid = true;
        $pointTier = memberRedeemSettingParseInteger($pointTierRaw, $numericValid);
        $price = memberRedeemSettingParseNumber($priceRaw, $numericValid);
        $sellingPrice = memberRedeemSettingParseNumber($sellingPriceRaw, $numericValid);
        $shopeeOrder = memberRedeemSettingParseInteger($shopeeOrderRaw, $numericValid);
        $privateOrder = memberRedeemSettingParseInteger($privateOrderRaw, $numericValid);

        $normalizedRow = array(
            'id' => $rowId,
            'point_tier' => $pointTierRaw,
            'redeemable_gift' => $giftRaw,
            'price' => $priceRaw,
            'selling_price' => $sellingPriceRaw,
            'cost_ratio' => memberRedeemCalculateCostRatio($price, $sellingPrice),
            'remark' => $remarkRaw,
            'shopee_lazada_redeem_order' => $shopeeOrderRaw,
            'private_redeem_order' => $privateOrderRaw,
        );

        if (!$numericValid) {
            $errors[] = 'Row ' . ($index + 1) . ' contains invalid numeric values.';
        }
        if ($pointTier <= 0) {
            $errors[] = 'Row ' . ($index + 1) . ': Point Tiers must be greater than 0.';
        }
        if ($giftRaw === '') {
            $errors[] = 'Row ' . ($index + 1) . ': Redeemable Gift is required.';
        }
        if ($price < 0) {
            $errors[] = 'Row ' . ($index + 1) . ': Price cannot be negative.';
        }
        if ($sellingPrice < 0) {
            $errors[] = 'Row ' . ($index + 1) . ': Selling Price cannot be negative.';
        }
        if ($shopeeOrder < 0 || $privateOrder < 0) {
            $errors[] = 'Row ' . ($index + 1) . ': Redeem order count cannot be negative.';
        }

        $normalizedRow['point_tier'] = $pointTier;
        $normalizedRow['price'] = $price;
        $normalizedRow['selling_price'] = $sellingPrice;
        $normalizedRow['shopee_lazada_redeem_order'] = $shopeeOrder;
        $normalizedRow['private_redeem_order'] = $privateOrder;
        $formRows[] = $normalizedRow;
    }

    if (empty($formRows)) {
        $errors[] = 'At least one redeem setting row is required.';
        $formRows = array(memberRedeemSettingBlankRow());
    }

    $existingById = array();
    foreach ($existingRows as $existingRow) {
        $existingById[(int) $existingRow['id']] = $existingRow;
    }

    if (empty($errors)) {
        $retainedExistingIds = array();
        $changeCount = 0;
        $displayOrder = 1;

        mysqli_begin_transaction($connect);
        try {
            foreach ($formRows as $rowIndex => $rowData) {
                $rowId = (int) ($rowData['id'] ?? 0);
                $pointTier = (int) ($rowData['point_tier'] ?? 0);
                $gift = trim((string) ($rowData['redeemable_gift'] ?? ''));
                $price = (float) ($rowData['price'] ?? 0);
                $sellingPrice = (float) ($rowData['selling_price'] ?? 0);
                $costRatio = memberRedeemCalculateCostRatio($price, $sellingPrice);
                $remark = trim((string) ($rowData['remark'] ?? ''));
                $shopeeOrder = (int) ($rowData['shopee_lazada_redeem_order'] ?? 0);
                $privateOrder = (int) ($rowData['private_redeem_order'] ?? 0);

                $safeGift = mysqli_real_escape_string($connect, $gift);
                $safeRemark = mysqli_real_escape_string($connect, $remark);

                $normalizedAuditRow = array(
                    'point_tier' => $pointTier,
                    'redeemable_gift' => $gift,
                    'price' => $price,
                    'selling_price' => $sellingPrice,
                    'cost_ratio' => $costRatio,
                    'remark' => $remark,
                    'shopee_lazada_redeem_order' => $shopeeOrder,
                    'private_redeem_order' => $privateOrder,
                );

                if ($rowId > 0 && isset($existingById[$rowId])) {
                    $retainedExistingIds[] = $rowId;
                    $existingRow = $existingById[$rowId];

                    $hasChanges =
                        (int) ($existingRow['point_tier'] ?? 0) !== $pointTier
                        || trim((string) ($existingRow['redeemable_gift'] ?? '')) !== $gift
                        || abs((float) ($existingRow['price'] ?? 0) - $price) > 0.00001
                        || abs((float) ($existingRow['selling_price'] ?? 0) - $sellingPrice) > 0.00001
                        || abs((float) ($existingRow['cost_ratio'] ?? 0) - $costRatio) > 0.00001
                        || trim((string) ($existingRow['remark'] ?? '')) !== $remark
                        || (int) ($existingRow['shopee_lazada_redeem_order'] ?? 0) !== $shopeeOrder
                        || (int) ($existingRow['private_redeem_order'] ?? 0) !== $privateOrder
                        || (int) ($existingRow['display_order'] ?? 0) !== $displayOrder;

                    if ($hasChanges) {
                        $query = "UPDATE `" . $tblName . "`
                            SET `point_tier` = '" . $pointTier . "',
                                `redeemable_gift` = '" . $safeGift . "',
                                `price` = '" . number_format($price, 2, '.', '') . "',
                                `selling_price` = '" . number_format($sellingPrice, 2, '.', '') . "',
                                `cost_ratio` = '" . number_format($costRatio, 2, '.', '') . "',
                                `remark` = '" . $safeRemark . "',
                                `shopee_lazada_redeem_order` = '" . $shopeeOrder . "',
                                `private_redeem_order` = '" . $privateOrder . "',
                                `display_order` = '" . $displayOrder . "',
                                `update_by` = '" . USER_ID . "',
                                `update_date` = CURDATE(),
                                `update_time` = CURTIME()
                            WHERE `id` = '" . $rowId . "' AND `status` = 'A'";

                        if (!mysqli_query($connect, $query)) {
                            throw new Exception(mysqli_error($connect));
                        }

                        memberRedeemSettingWriteAudit(
                            $connect,
                            $pageTitle,
                            'edit',
                            USER_NAME . ' edited member redeem setting [<b>ID = ' . $rowId . '</b>] <b>' . htmlspecialchars($gift, ENT_QUOTES, 'UTF-8') . '</b>.',
                            $query,
                            memberRedeemSettingDescribeRow($existingRow),
                            '',
                            memberRedeemSettingDescribeRow($normalizedAuditRow)
                        );
                        $changeCount++;
                    }
                } else {
                    $query = "INSERT INTO `" . $tblName . "` (
                            `point_tier`, `redeemable_gift`, `price`, `selling_price`, `cost_ratio`, `remark`,
                            `shopee_lazada_redeem_order`, `private_redeem_order`, `display_order`,
                            `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`
                        ) VALUES (
                            '" . $pointTier . "', '" . $safeGift . "', '" . number_format($price, 2, '.', '') . "',
                            '" . number_format($sellingPrice, 2, '.', '') . "', '" . number_format($costRatio, 2, '.', '') . "',
                            '" . $safeRemark . "', '" . $shopeeOrder . "', '" . $privateOrder . "', '" . $displayOrder . "',
                            '" . USER_ID . "', CURDATE(), CURTIME(), '" . USER_ID . "', CURDATE(), CURTIME(), 'A'
                        )";

                    if (!mysqli_query($connect, $query)) {
                        throw new Exception(mysqli_error($connect));
                    }

                    $newId = (int) mysqli_insert_id($connect);
                    memberRedeemSettingWriteAudit(
                        $connect,
                        $pageTitle,
                        'add',
                        USER_NAME . ' added member redeem setting [<b>ID = ' . $newId . '</b>] <b>' . htmlspecialchars($gift, ENT_QUOTES, 'UTF-8') . '</b>.',
                        $query,
                        '',
                        memberRedeemSettingDescribeRow($normalizedAuditRow)
                    );
                    $changeCount++;
                }

                $displayOrder++;
            }

            foreach ($existingById as $existingId => $existingRow) {
                if (in_array($existingId, $retainedExistingIds, true)) {
                    continue;
                }

                $query = "UPDATE `" . $tblName . "`
                    SET `status` = 'D',
                        `update_by` = '" . USER_ID . "',
                        `update_date` = CURDATE(),
                        `update_time` = CURTIME()
                    WHERE `id` = '" . $existingId . "' AND `status` = 'A'";

                if (!mysqli_query($connect, $query)) {
                    throw new Exception(mysqli_error($connect));
                }

                memberRedeemSettingWriteAudit(
                    $connect,
                    $pageTitle,
                    'delete',
                    USER_NAME . ' removed member redeem setting [<b>ID = ' . $existingId . '</b>] <b>' . htmlspecialchars((string) ($existingRow['redeemable_gift'] ?? ''), ENT_QUOTES, 'UTF-8') . '</b>.',
                    $query,
                    memberRedeemSettingDescribeRow($existingRow)
                );
                $changeCount++;
            }

            mysqli_commit($connect);

            if ($changeCount > 0) {
                memberRedeemSettingSetPopup('', 'E');
                header('Location: ' . $pageUrl);
                exit;
            }

            memberRedeemSettingWriteAudit(
                $connect,
                $pageTitle,
                'edit',
                USER_NAME . ' saved Member Redeem Setting but no changes were detected.',
                'NO_CHANGES'
            );
            memberRedeemSettingSetPopup('', 'NC');
            header('Location: ' . $pageUrl);
            exit;
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            $errors[] = 'Failed to save Member Redeem Setting. ' . $exception->getMessage();
            memberRedeemSettingWriteAudit(
                $connect,
                $pageTitle,
                'edit',
                USER_NAME . ' failed to save Member Redeem Setting.',
                'SAVE_FAILED'
            );
        }
    }
}

if (empty($formRows)) {
    $formRows = array(memberRedeemSettingBlankRow());
}
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .member-redeem-card {
            border: 1px solid #e4e8ef;
            border-radius: 16px;
            box-shadow: 0 .5rem 1rem rgba(15, 23, 42, .05);
        }

        .member-redeem-table th,
        .member-redeem-table td {
            vertical-align: middle;
        }

        .member-redeem-table input,
        .member-redeem-table textarea {
            min-width: 120px;
        }

        .member-redeem-ratio-input {
            background: #f8fafc;
            font-weight: 600;
        }

        .member-redeem-action-btn {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid transparent;
            background: #fff;
        }

        .member-redeem-add-btn {
            color: #16a34a;
            border-color: rgba(22, 163, 74, .25);
        }

        .member-redeem-remove-btn {
            color: #dc2626;
            border-color: rgba(220, 38, 38, .2);
        }

        .member-redeem-action-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .member-redeem-save-btn {
            text-transform: none !important;
        }
    </style>
</head>

<body>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= htmlspecialchars($redirectPage, ENT_QUOTES, 'UTF-8') ?>">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div class="container-fluid pb-5">
            <div class="row justify-content-center">
                <div class="col-12 col-xl-11">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="member-redeem-card bg-white p-4">
                            <div class="mb-4">
                                <div>
                                    <h2 class="mb-1"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                                    <div class="text-muted">Set which gifts should appear in the Redeem Suggestion List on the Member Point page.</div>
                                </div>
                            </div>

                            <?php if (!empty($errors)) { ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $errorMessage) { ?>
                                        <div><?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle member-redeem-table mb-0" id="memberRedeemTable">
                                    <thead>
                                        <tr>
                                            <th width="120">Point Tiers</th>
                                            <th width="220">Redeemable Gift</th>
                                            <th width="130">Price (RM)</th>
                                            <th width="150">Selling Price (RM)</th>
                                            <th width="130">Cost Ratio</th>
                                            <th width="220">Remark</th>
                                            <th width="190">Shopee/Lazada RM300 Package Redeem Order</th>
                                            <th width="190">Private (FB) RM300 Package Redeem Order</th>
                                            <?php if ($canManage) { ?>
                                                <th width="120">Action</th>
                                            <?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody id="memberRedeemTableBody">
                                        <?php foreach ($formRows as $rowIndex => $rowData) { ?>
                                            <tr class="member-redeem-row">
                                                <td>
                                                    <input type="hidden" name="row_id[]" value="<?= (int) ($rowData['id'] ?? 0) ?>">
                                                    <input type="number" min="1" step="1" class="form-control member-redeem-point-tier" name="point_tier[]" value="<?= htmlspecialchars((string) ($rowData['point_tier'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control" name="redeemable_gift[]" value="<?= htmlspecialchars((string) ($rowData['redeemable_gift'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="0.01" class="form-control member-redeem-price" name="price[]" value="<?= htmlspecialchars(memberRedeemSettingFormatDecimal($rowData['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="0.01" class="form-control member-redeem-selling-price" name="selling_price[]" value="<?= htmlspecialchars(memberRedeemSettingFormatDecimal($rowData['selling_price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control member-redeem-ratio-input member-redeem-cost-ratio" value="<?= htmlspecialchars(memberRedeemSettingFormatRatio($rowData['cost_ratio'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" readonly>
                                                </td>
                                                <td>
                                                    <textarea class="form-control" name="remark[]" rows="2" <?= $fieldDisabled ?>><?= htmlspecialchars((string) ($rowData['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="1" class="form-control" name="shopee_lazada_redeem_order[]" value="<?= htmlspecialchars((string) ($rowData['shopee_lazada_redeem_order'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="1" class="form-control" name="private_redeem_order[]" value="<?= htmlspecialchars((string) ($rowData['private_redeem_order'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <?php if ($canManage) { ?>
                                                    <td>
                                                        <div class="d-flex gap-2 justify-content-center">
                                                            <button type="button" class="member-redeem-action-btn member-redeem-add-btn" data-member-redeem-add="1" aria-label="Add row">
                                                                <i class="fa-solid fa-square-plus"></i>
                                                            </button>
                                                            <button type="button" class="member-redeem-action-btn member-redeem-remove-btn" data-member-redeem-remove="1" aria-label="Remove row">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                <?php } ?>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($canManage) { ?>
                                <div class="d-flex justify-content-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-rounded px-4 member-redeem-save-btn" name="actionBtn" value="saveRedeemSetting">Save</button>
                                </div>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManage) { ?>
        <template id="memberRedeemRowTemplate">
            <tr class="member-redeem-row">
                <td>
                    <input type="hidden" name="row_id[]" value="0">
                    <input type="number" min="1" step="1" class="form-control member-redeem-point-tier" name="point_tier[]" value="">
                </td>
                <td>
                    <input type="text" class="form-control" name="redeemable_gift[]" value="">
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="form-control member-redeem-price" name="price[]" value="">
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="form-control member-redeem-selling-price" name="selling_price[]" value="">
                </td>
                <td>
                    <input type="text" class="form-control member-redeem-ratio-input member-redeem-cost-ratio" value="0%" readonly>
                </td>
                <td>
                    <textarea class="form-control" name="remark[]" rows="2"></textarea>
                </td>
                <td>
                    <input type="number" min="0" step="1" class="form-control" name="shopee_lazada_redeem_order[]" value="">
                </td>
                <td>
                    <input type="number" min="0" step="1" class="form-control" name="private_redeem_order[]" value="">
                </td>
                <td>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="member-redeem-action-btn member-redeem-add-btn" data-member-redeem-add="1" aria-label="Add row">
                            <i class="fa-solid fa-square-plus"></i>
                        </button>
                        <button type="button" class="member-redeem-action-btn member-redeem-remove-btn" data-member-redeem-remove="1" aria-label="Remove row">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        </template>

        <script>
            (function() {
                var tableBody = document.getElementById('memberRedeemTableBody');
                var rowTemplate = document.getElementById('memberRedeemRowTemplate');
                if (!tableBody || !rowTemplate) {
                    return;
                }

                function formatRatio(value) {
                    var numericValue = Number(value);
                    if (!isFinite(numericValue) || numericValue <= 0) {
                        return '0%';
                    }

                    var roundedValue = Math.round(numericValue * 100) / 100;
                    if (Math.abs(roundedValue - Math.round(roundedValue)) < 0.00001) {
                        return String(Math.round(roundedValue)) + '%';
                    }

                    return String(roundedValue).replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1') + '%';
                }

                function updateRowRatio(row) {
                    if (!row) {
                        return;
                    }

                    var priceInput = row.querySelector('.member-redeem-price');
                    var sellingPriceInput = row.querySelector('.member-redeem-selling-price');
                    var ratioInput = row.querySelector('.member-redeem-cost-ratio');
                    if (!priceInput || !sellingPriceInput || !ratioInput) {
                        return;
                    }

                    var price = parseFloat(priceInput.value || '0');
                    var sellingPrice = parseFloat(sellingPriceInput.value || '0');
                    var ratio = 0;
                    if (isFinite(price) && isFinite(sellingPrice) && sellingPrice > 0) {
                        ratio = (price / sellingPrice) * 100;
                    }

                    ratioInput.value = formatRatio(ratio);
                }

                function ensureOneRow() {
                    if (tableBody.querySelectorAll('.member-redeem-row').length > 0) {
                        return;
                    }

                    var fragment = rowTemplate.content.cloneNode(true);
                    tableBody.appendChild(fragment);
                }

                tableBody.addEventListener('input', function(event) {
                    var row = event.target.closest('.member-redeem-row');
                    if (row && (event.target.classList.contains('member-redeem-price') || event.target.classList.contains('member-redeem-selling-price'))) {
                        updateRowRatio(row);
                    }
                });

                tableBody.addEventListener('click', function(event) {
                    var addButton = event.target.closest('[data-member-redeem-add]');
                    if (addButton) {
                        var currentRow = addButton.closest('.member-redeem-row');
                        var fragment = rowTemplate.content.cloneNode(true);
                        var newRow = fragment.querySelector('.member-redeem-row');
                        if (currentRow && currentRow.parentNode && newRow) {
                            currentRow.parentNode.insertBefore(fragment, currentRow.nextSibling);
                        } else {
                            tableBody.appendChild(fragment);
                        }
                        return;
                    }

                    var removeButton = event.target.closest('[data-member-redeem-remove]');
                    if (removeButton) {
                        var targetRow = removeButton.closest('.member-redeem-row');
                        if (targetRow) {
                            targetRow.remove();
                            ensureOneRow();
                        }
                    }
                });

                tableBody.querySelectorAll('.member-redeem-row').forEach(updateRowRatio);
            })();
        </script>
    <?php } ?>
    <?php memberRedeemSettingRenderPopupScript($pageTitle, $pageUrl); ?>
</body>

</html>
