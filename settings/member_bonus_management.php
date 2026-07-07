<?php
ob_start();

$pageTitle = 'Member Bonus Management';
$currentPagePin = 164;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedPageTitle !== '') {
    $pageTitle = $resolvedPageTitle;
}

$pinAccess = checkCurrentPin($connect, $pageTitle);
$redirectPage = $SITEURL . '/dashboard.php';
$pageUrl = $SITEURL . '/settings/member_bonus_management.php';
$canView = isActionAllowed('View', $pinAccess);
$canManage = isActionAllowed('Add', $pinAccess) || isActionAllowed('Edit', $pinAccess);
$fieldDisabled = $canManage ? '' : 'readonly disabled';
$csrfSessionKey = 'member_bonus_management_csrf_token';
$popupSessionKey = 'member_bonus_management_popup';

if (!$canView) {
    renderNotificationScript('You do not have permission to view Member Bonus Management.', 'error', $redirectPage, 1200, true);
    exit;
}

if (!memberPointBonusTierSettingTableExists($connect) || !memberPointBonusSpecialSettingTableExists($connect)) {
    renderNotificationScript('Member Bonus Management tables are not ready. Please run insert_table.php first.', 'error', $redirectPage, 1200, true);
    exit;
}

if (empty($_SESSION[$csrfSessionKey])) {
    $_SESSION[$csrfSessionKey] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION[$csrfSessionKey];

function memberBonusManagementSetPopup($message, $act = 'ErrMO')
{
    global $popupSessionKey;
    $_SESSION[$popupSessionKey] = array(
        'message' => (string) $message,
        'act' => (string) $act,
    );
}

function memberBonusManagementRenderPopupScript($pageTitle, $returnUrl)
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

function memberBonusManagementBlankTierRow()
{
    return array(
        'id' => 0,
        'tier_key' => '',
        'tier_name' => '',
        'requirement_type' => 'register',
        'minimum_purchase_amount' => 0,
        'private_point_rate' => 0.03,
        'marketplace_point_rate' => 0.03,
        'bonus_points' => 0,
        'bonus_frequency' => 'monthly',
        'remark' => '',
        'display_order' => 0,
    );
}

function memberBonusManagementFormatDecimal($value)
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

function memberBonusManagementFormatPercent($rate)
{
    return memberBonusManagementFormatDecimal(((float) $rate) * 100);
}

function memberBonusManagementParseNumber($value, &$isValid)
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

function memberBonusManagementParseInteger($value, &$isValid)
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

function memberBonusManagementSlugifyTierKey($label)
{
    $label = strtolower(trim((string) $label));
    $label = preg_replace('/[^a-z0-9]+/', '_', $label);
    $label = trim((string) $label, '_');
    return $label !== '' ? substr($label, 0, 40) : '';
}

function memberBonusManagementBuildUniqueTierKey($preferredKey, $label, $usedKeys, $fallbackIndex)
{
    $baseKey = strtolower(trim((string) $preferredKey));
    if ($baseKey === '') {
        $baseKey = memberBonusManagementSlugifyTierKey($label);
    }
    if ($baseKey === '') {
        $baseKey = 'tier_' . max(1, (int) $fallbackIndex);
    }

    $uniqueKey = $baseKey;
    $suffix = 2;
    while (in_array($uniqueKey, $usedKeys, true)) {
        $uniqueKey = substr($baseKey, 0, 32) . '_' . $suffix;
        $suffix++;
    }

    return $uniqueKey;
}

function memberBonusManagementDescribeTierRow($row)
{
    $requirementType = trim((string) ($row['requirement_type'] ?? 'register'));
    $requirementLabel = isset(memberPointGetTierRequirementOptions()[$requirementType]) ? memberPointGetTierRequirementOptions()[$requirementType] : $requirementType;
    return implode(' | ', array(
        'Tier: ' . trim((string) ($row['tier_name'] ?? '')),
        'Requirement: ' . $requirementLabel,
        'Minimum Purchase: RM' . number_format((float) ($row['minimum_purchase_amount'] ?? 0), 2, '.', ''),
        'Private Ratio: ' . memberBonusManagementFormatPercent($row['private_point_rate'] ?? 0) . '%',
        'Shopee/Lazada Ratio: ' . memberBonusManagementFormatPercent($row['marketplace_point_rate'] ?? 0) . '%',
        'Bonus Points: ' . (int) ($row['bonus_points'] ?? 0),
        'Bonus Frequency: ' . ucfirst((string) ($row['bonus_frequency'] ?? 'monthly')),
        'Remark: ' . trim((string) ($row['remark'] ?? '')),
    ));
}

function memberBonusManagementDescribeSpecialRow($row)
{
    return implode(' | ', array(
        'Bonus: ' . trim((string) ($row['bonus_name'] ?? '')),
        'Minimum Purchase: RM' . number_format((float) ($row['minimum_purchase_amount'] ?? 0), 2, '.', ''),
        'Purchase Times: ' . (int) ($row['minimum_purchase_times'] ?? 0),
        'Bonus Points: ' . (int) ($row['bonus_points'] ?? 0),
        'Remark: ' . trim((string) ($row['remark'] ?? '')),
    ));
}

function memberBonusManagementWriteAudit($connect, $pageTitle, $logAct, $message, $queryTable, $query = '', $oldVal = '', $newVal = '', $changes = '')
{
    audit_log(array(
        'log_act' => $logAct,
        'cdate' => $GLOBALS['cdate'] ?? date('Y-m-d'),
        'ctime' => $GLOBALS['ctime'] ?? date('H:i:s'),
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $query,
        'query_table' => $queryTable,
        'oldval' => $oldVal,
        'newval' => $newVal,
        'changes' => $changes,
        'act_msg' => $message,
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$tierRows = memberPointFetchTierSettingRows($connect, true);
if (empty($tierRows)) {
    $tierRows = array(memberBonusManagementBlankTierRow());
}

$specialBonusDefaults = memberPointGetDefaultSpecialBonusConfigs();
$specialBonusRows = memberPointGetSpecialBonusConfigs();
$specialBonusRows = array_merge($specialBonusDefaults, $specialBonusRows);

$tierErrors = array();
$specialErrors = array();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    memberBonusManagementWriteAudit(
        $connect,
        $pageTitle,
        'view',
        USER_NAME . ' viewed the page <b>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</b>.',
        MEMBER_BONUS_TIER_SETTING
    );
}

if (post('actionBtn') === 'saveTierSetting') {
    if (!$canManage) {
        renderNotificationScript('You do not have permission to save Member Bonus Management tier settings.', 'error', $pageUrl, 1200, true);
        exit;
    }

    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($csrfToken, $submittedToken)) {
        $tierErrors[] = 'Invalid session token. Please refresh the page and try again.';
    }

    $existingTierRows = memberPointFetchTierSettingRows($connect, true);
    $existingTierRowsById = array();
    foreach ($existingTierRows as $existingTierRow) {
        $existingTierRowsById[(int) ($existingTierRow['id'] ?? 0)] = $existingTierRow;
    }

    $rowIds = isset($_POST['tier_row_id']) && is_array($_POST['tier_row_id']) ? $_POST['tier_row_id'] : array();
    $postedTierKeys = isset($_POST['tier_key']) && is_array($_POST['tier_key']) ? $_POST['tier_key'] : array();
    $tierNames = isset($_POST['tier_name']) && is_array($_POST['tier_name']) ? $_POST['tier_name'] : array();
    $requirementTypes = isset($_POST['requirement_type']) && is_array($_POST['requirement_type']) ? $_POST['requirement_type'] : array();
    $minimumAmounts = isset($_POST['minimum_purchase_amount']) && is_array($_POST['minimum_purchase_amount']) ? $_POST['minimum_purchase_amount'] : array();
    $privateRates = isset($_POST['private_point_rate']) && is_array($_POST['private_point_rate']) ? $_POST['private_point_rate'] : array();
    $marketplaceRates = isset($_POST['marketplace_point_rate']) && is_array($_POST['marketplace_point_rate']) ? $_POST['marketplace_point_rate'] : array();
    $bonusPoints = isset($_POST['bonus_points']) && is_array($_POST['bonus_points']) ? $_POST['bonus_points'] : array();
    $bonusFrequencies = isset($_POST['bonus_frequency']) && is_array($_POST['bonus_frequency']) ? $_POST['bonus_frequency'] : array();
    $remarks = isset($_POST['remark']) && is_array($_POST['remark']) ? $_POST['remark'] : array();

    $rowCount = max(
        count($rowIds),
        count($postedTierKeys),
        count($tierNames),
        count($requirementTypes),
        count($minimumAmounts),
        count($privateRates),
        count($marketplaceRates),
        count($bonusPoints),
        count($bonusFrequencies),
        count($remarks),
        1
    );

    $tierRows = array();
    $usedTierKeys = array();
    for ($index = 0; $index < $rowCount; $index++) {
        $rowId = (int) ($rowIds[$index] ?? 0);
        $postedTierKey = trim((string) ($postedTierKeys[$index] ?? ''));
        $tierName = trim((string) ($tierNames[$index] ?? ''));
        $requirementType = strtolower(trim((string) ($requirementTypes[$index] ?? 'register')));
        $minimumAmountRaw = trim((string) ($minimumAmounts[$index] ?? ''));
        $privateRateRaw = trim((string) ($privateRates[$index] ?? ''));
        $marketplaceRateRaw = trim((string) ($marketplaceRates[$index] ?? ''));
        $bonusPointRaw = trim((string) ($bonusPoints[$index] ?? ''));
        $bonusFrequency = strtolower(trim((string) ($bonusFrequencies[$index] ?? 'monthly')));
        $remark = trim((string) ($remarks[$index] ?? ''));

        $isBlank = $tierName === ''
            && $minimumAmountRaw === ''
            && $privateRateRaw === ''
            && $marketplaceRateRaw === ''
            && $bonusPointRaw === ''
            && $remark === '';
        if ($isBlank) {
            continue;
        }

        $isValid = true;
        $minimumAmount = memberBonusManagementParseNumber($minimumAmountRaw, $isValid);
        $privateRatePercent = memberBonusManagementParseNumber($privateRateRaw, $isValid);
        $marketplaceRatePercent = memberBonusManagementParseNumber($marketplaceRateRaw, $isValid);
        $bonusPointValue = memberBonusManagementParseInteger($bonusPointRaw, $isValid);

        if (!isset(memberPointGetTierRequirementOptions()[$requirementType])) {
            $tierErrors[] = 'Row ' . ($index + 1) . ': Invalid requirement type.';
        }
        if (!isset(memberPointGetBonusFrequencyOptions()[$bonusFrequency])) {
            $tierErrors[] = 'Row ' . ($index + 1) . ': Invalid bonus frequency.';
        }
        if (!$isValid) {
            $tierErrors[] = 'Row ' . ($index + 1) . ' contains invalid numeric values.';
        }
        if ($tierName === '') {
            $tierErrors[] = 'Row ' . ($index + 1) . ': Tier name is required.';
        }
        if ($minimumAmount < 0) {
            $tierErrors[] = 'Row ' . ($index + 1) . ': Minimum purchase amount cannot be negative.';
        }
        if ($privateRatePercent < 0 || $marketplaceRatePercent < 0) {
            $tierErrors[] = 'Row ' . ($index + 1) . ': Point ratio cannot be negative.';
        }
        if ($bonusPointValue < 0) {
            $tierErrors[] = 'Row ' . ($index + 1) . ': Bonus points cannot be negative.';
        }

        if ($requirementType === 'register') {
            $minimumAmount = 0;
        }

        $tierKey = memberBonusManagementBuildUniqueTierKey($postedTierKey, $tierName, $usedTierKeys, $index + 1);
        $usedTierKeys[] = $tierKey;

        $tierRows[] = array(
            'id' => $rowId,
            'tier_key' => $tierKey,
            'tier_name' => $tierName,
            'requirement_type' => $requirementType,
            'minimum_purchase_amount' => $minimumAmount,
            'private_point_rate' => round($privateRatePercent / 100, 4),
            'marketplace_point_rate' => round($marketplaceRatePercent / 100, 4),
            'bonus_points' => $bonusPointValue,
            'bonus_frequency' => $bonusFrequency,
            'remark' => $remark,
            'display_order' => $index + 1,
        );
    }

    if (empty($tierRows)) {
        $tierErrors[] = 'At least one member tier row is required.';
        $tierRows = array(memberBonusManagementBlankTierRow());
    }

    if (empty($tierErrors)) {
        mysqli_begin_transaction($connect);
        try {
            $retainedIds = array();
            $changeCount = 0;
            $displayOrder = 1;

            foreach ($tierRows as $tierRow) {
                $rowId = (int) ($tierRow['id'] ?? 0);
                $safeTierKey = mysqli_real_escape_string($connect, (string) $tierRow['tier_key']);
                $safeTierName = mysqli_real_escape_string($connect, (string) $tierRow['tier_name']);
                $safeRequirementType = mysqli_real_escape_string($connect, (string) $tierRow['requirement_type']);
                $safeRemark = mysqli_real_escape_string($connect, (string) $tierRow['remark']);
                $minimumAmountSql = number_format((float) $tierRow['minimum_purchase_amount'], 2, '.', '');
                $privateRateSql = number_format((float) $tierRow['private_point_rate'], 4, '.', '');
                $marketplaceRateSql = number_format((float) $tierRow['marketplace_point_rate'], 4, '.', '');
                $bonusPointsSql = (int) $tierRow['bonus_points'];
                $safeBonusFrequency = mysqli_real_escape_string($connect, (string) $tierRow['bonus_frequency']);
                $tierRow['display_order'] = $displayOrder;

                if ($rowId > 0 && isset($existingTierRowsById[$rowId])) {
                    $retainedIds[] = $rowId;
                    $existingTierRow = $existingTierRowsById[$rowId];
                    $hasChanges =
                        trim((string) ($existingTierRow['tier_key'] ?? '')) !== (string) $tierRow['tier_key']
                        || trim((string) ($existingTierRow['tier_name'] ?? '')) !== (string) $tierRow['tier_name']
                        || trim((string) ($existingTierRow['requirement_type'] ?? 'register')) !== (string) $tierRow['requirement_type']
                        || abs((float) ($existingTierRow['minimum_purchase_amount'] ?? 0) - (float) $tierRow['minimum_purchase_amount']) > 0.00001
                        || abs((float) ($existingTierRow['private_point_rate'] ?? 0) - (float) $tierRow['private_point_rate']) > 0.00001
                        || abs((float) ($existingTierRow['marketplace_point_rate'] ?? 0) - (float) $tierRow['marketplace_point_rate']) > 0.00001
                        || (int) ($existingTierRow['bonus_points'] ?? 0) !== (int) $tierRow['bonus_points']
                        || trim((string) ($existingTierRow['bonus_frequency'] ?? 'monthly')) !== (string) $tierRow['bonus_frequency']
                        || trim((string) ($existingTierRow['remark'] ?? '')) !== (string) $tierRow['remark']
                        || (int) ($existingTierRow['display_order'] ?? 0) !== $displayOrder;

                    if ($hasChanges) {
                        $query = "UPDATE `" . MEMBER_BONUS_TIER_SETTING . "`
                            SET `tier_key` = '" . $safeTierKey . "',
                                `tier_name` = '" . $safeTierName . "',
                                `requirement_type` = '" . $safeRequirementType . "',
                                `minimum_purchase_amount` = '" . $minimumAmountSql . "',
                                `private_point_rate` = '" . $privateRateSql . "',
                                `marketplace_point_rate` = '" . $marketplaceRateSql . "',
                                `bonus_points` = " . $bonusPointsSql . ",
                                `bonus_frequency` = '" . $safeBonusFrequency . "',
                                `remark` = " . ($safeRemark !== '' ? "'" . $safeRemark . "'" : "NULL") . ",
                                `display_order` = " . $displayOrder . ",
                                `update_by` = '" . USER_ID . "',
                                `update_date` = CURDATE(),
                                `update_time` = CURTIME()
                            WHERE `id` = " . $rowId . " AND `status` = 'A'";
                        if (!mysqli_query($connect, $query)) {
                            throw new Exception(mysqli_error($connect));
                        }

                        memberBonusManagementWriteAudit(
                            $connect,
                            $pageTitle,
                            'edit',
                            USER_NAME . ' edited member bonus tier setting [<b>ID = ' . $rowId . '</b>] <b>' . htmlspecialchars((string) $tierRow['tier_name'], ENT_QUOTES, 'UTF-8') . '</b>.',
                            MEMBER_BONUS_TIER_SETTING,
                            $query,
                            memberBonusManagementDescribeTierRow($existingTierRow),
                            '',
                            memberBonusManagementDescribeTierRow($tierRow)
                        );
                        $changeCount++;
                    }
                } else {
                    $query = "INSERT INTO `" . MEMBER_BONUS_TIER_SETTING . "` (
                            `tier_key`, `tier_name`, `requirement_type`, `minimum_purchase_amount`,
                            `private_point_rate`, `marketplace_point_rate`, `bonus_points`, `bonus_frequency`,
                            `remark`, `display_order`, `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`
                        ) VALUES (
                            '" . $safeTierKey . "', '" . $safeTierName . "', '" . $safeRequirementType . "', '" . $minimumAmountSql . "',
                            '" . $privateRateSql . "', '" . $marketplaceRateSql . "', " . $bonusPointsSql . ", '" . $safeBonusFrequency . "',
                            " . ($safeRemark !== '' ? "'" . $safeRemark . "'" : "NULL") . ", " . $displayOrder . ",
                            '" . USER_ID . "', CURDATE(), CURTIME(), '" . USER_ID . "', CURDATE(), CURTIME(), 'A'
                        )";
                    if (!mysqli_query($connect, $query)) {
                        throw new Exception(mysqli_error($connect));
                    }

                    $newId = (int) mysqli_insert_id($connect);
                    memberBonusManagementWriteAudit(
                        $connect,
                        $pageTitle,
                        'add',
                        USER_NAME . ' added member bonus tier setting [<b>ID = ' . $newId . '</b>] <b>' . htmlspecialchars((string) $tierRow['tier_name'], ENT_QUOTES, 'UTF-8') . '</b>.',
                        MEMBER_BONUS_TIER_SETTING,
                        $query,
                        '',
                        memberBonusManagementDescribeTierRow($tierRow)
                    );
                    $changeCount++;
                }

                $displayOrder++;
            }

            foreach ($existingTierRowsById as $existingId => $existingTierRow) {
                if (in_array($existingId, $retainedIds, true)) {
                    continue;
                }

                $query = "UPDATE `" . MEMBER_BONUS_TIER_SETTING . "`
                    SET `status` = 'D',
                        `update_by` = '" . USER_ID . "',
                        `update_date` = CURDATE(),
                        `update_time` = CURTIME()
                    WHERE `id` = " . (int) $existingId . " AND `status` = 'A'";
                if (!mysqli_query($connect, $query)) {
                    throw new Exception(mysqli_error($connect));
                }

                memberBonusManagementWriteAudit(
                    $connect,
                    $pageTitle,
                    'delete',
                    USER_NAME . ' removed member bonus tier setting [<b>ID = ' . (int) $existingId . '</b>] <b>' . htmlspecialchars((string) ($existingTierRow['tier_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</b>.',
                    MEMBER_BONUS_TIER_SETTING,
                    $query,
                    memberBonusManagementDescribeTierRow($existingTierRow)
                );
                $changeCount++;
            }

            mysqli_commit($connect);
            $memberStateRefreshSummary = memberPointRefreshAllActiveMemberStates($connect, $finance_connect, date('Y-m-d'));
            if (!empty($memberStateRefreshSummary['errors'])) {
                memberBonusManagementWriteAudit(
                    $connect,
                    $pageTitle,
                    'edit',
                    USER_NAME . ' saved Member Bonus Management tier settings, but some member state refreshes reported issues.',
                    MEMBER_POINT_MEMBER_STATE,
                    'REFRESH_MEMBER_STATE',
                    '',
                    '',
                    implode(" | ", array_slice($memberStateRefreshSummary['errors'], 0, 10))
                );
            }

            if ($changeCount > 0) {
                memberBonusManagementSetPopup('', 'E');
                header('Location: ' . $pageUrl);
                exit;
            }

            memberBonusManagementWriteAudit(
                $connect,
                $pageTitle,
                'edit',
                USER_NAME . ' saved Member Bonus Management tier settings but no changes were detected.',
                MEMBER_BONUS_TIER_SETTING,
                'NO_CHANGES'
            );
            memberBonusManagementSetPopup('', 'NC');
            header('Location: ' . $pageUrl);
            exit;
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            $tierErrors[] = 'Failed to save member tier settings. ' . $exception->getMessage();
            memberBonusManagementWriteAudit(
                $connect,
                $pageTitle,
                'edit',
                USER_NAME . ' failed to save Member Bonus Management tier settings.',
                MEMBER_BONUS_TIER_SETTING,
                'SAVE_FAILED'
            );
        }
    }
}

if (post('actionBtn') === 'saveSpecialBonusSetting') {
    if (!$canManage) {
        renderNotificationScript('You do not have permission to save Member Bonus Management special bonus settings.', 'error', $pageUrl, 1200, true);
        exit;
    }

    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($csrfToken, $submittedToken)) {
        $specialErrors[] = 'Invalid session token. Please refresh the page and try again.';
    }

    $existingSpecialRows = memberPointFetchSpecialBonusRows($connect, true);
    $existingSpecialRowsByKey = array();
    foreach ($existingSpecialRows as $existingSpecialRow) {
        $existingSpecialRowsByKey[strtolower(trim((string) ($existingSpecialRow['bonus_key'] ?? '')))] = $existingSpecialRow;
    }

    $birthdayBonusPointsRaw = trim((string) postSpaceFilter('birthday_bonus_points'));
    $birthdayRemark = trim((string) postSpaceFilter('birthday_remark'));
    $monthlyPurchaseAmountRaw = trim((string) postSpaceFilter('monthly_purchase_amount'));
    $monthlyPurchaseTimesRaw = trim((string) postSpaceFilter('monthly_purchase_times'));
    $monthlyPurchaseBonusPointsRaw = trim((string) postSpaceFilter('monthly_purchase_bonus_points'));
    $monthlyPurchaseRemark = trim((string) postSpaceFilter('monthly_purchase_remark'));

    $isValid = true;
    $birthdayBonusPoints = memberBonusManagementParseInteger($birthdayBonusPointsRaw, $isValid);
    $monthlyPurchaseAmount = memberBonusManagementParseNumber($monthlyPurchaseAmountRaw, $isValid);
    $monthlyPurchaseTimes = memberBonusManagementParseInteger($monthlyPurchaseTimesRaw, $isValid);
    $monthlyPurchaseBonusPoints = memberBonusManagementParseInteger($monthlyPurchaseBonusPointsRaw, $isValid);

    if (!$isValid) {
        $specialErrors[] = 'Special bonus settings contain invalid numeric values.';
    }
    if ($birthdayBonusPoints < 0 || $monthlyPurchaseAmount < 0 || $monthlyPurchaseTimes < 0 || $monthlyPurchaseBonusPoints < 0) {
        $specialErrors[] = 'Special bonus values cannot be negative.';
    }

    $specialBonusRows['birthday']['bonus_points'] = $birthdayBonusPoints;
    $specialBonusRows['birthday']['remark'] = $birthdayRemark;
    $specialBonusRows['monthly_purchase']['minimum_purchase_amount'] = $monthlyPurchaseAmount;
    $specialBonusRows['monthly_purchase']['minimum_purchase_times'] = $monthlyPurchaseTimes;
    $specialBonusRows['monthly_purchase']['bonus_points'] = $monthlyPurchaseBonusPoints;
    $specialBonusRows['monthly_purchase']['remark'] = $monthlyPurchaseRemark;

    if (empty($specialErrors)) {
        mysqli_begin_transaction($connect);
        try {
            $changeCount = 0;
            $specialRowsToSave = array(
                'birthday' => array(
                    'bonus_key' => 'birthday',
                    'bonus_name' => 'Birthday Bonus',
                    'minimum_purchase_amount' => 0,
                    'minimum_purchase_times' => 0,
                    'bonus_points' => $birthdayBonusPoints,
                    'remark' => $birthdayRemark,
                    'display_order' => 1,
                ),
                'monthly_purchase' => array(
                    'bonus_key' => 'monthly_purchase',
                    'bonus_name' => 'Monthly Purchase Bonus',
                    'minimum_purchase_amount' => $monthlyPurchaseAmount,
                    'minimum_purchase_times' => $monthlyPurchaseTimes,
                    'bonus_points' => $monthlyPurchaseBonusPoints,
                    'remark' => $monthlyPurchaseRemark,
                    'display_order' => 2,
                ),
            );

            foreach ($specialRowsToSave as $specialKey => $specialRow) {
                $existingSpecialRow = $existingSpecialRowsByKey[$specialKey] ?? array();
                $safeBonusKey = mysqli_real_escape_string($connect, (string) $specialRow['bonus_key']);
                $safeBonusName = mysqli_real_escape_string($connect, (string) $specialRow['bonus_name']);
                $safeRemark = mysqli_real_escape_string($connect, (string) $specialRow['remark']);
                $minimumAmountSql = number_format((float) $specialRow['minimum_purchase_amount'], 2, '.', '');
                $minimumTimesSql = (int) $specialRow['minimum_purchase_times'];
                $bonusPointsSql = (int) $specialRow['bonus_points'];
                $displayOrder = (int) $specialRow['display_order'];

                if (!empty($existingSpecialRow)) {
                    $rowId = (int) ($existingSpecialRow['id'] ?? 0);
                    $hasChanges =
                        abs((float) ($existingSpecialRow['minimum_purchase_amount'] ?? 0) - (float) $specialRow['minimum_purchase_amount']) > 0.00001
                        || (int) ($existingSpecialRow['minimum_purchase_times'] ?? 0) !== $minimumTimesSql
                        || (int) ($existingSpecialRow['bonus_points'] ?? 0) !== $bonusPointsSql
                        || trim((string) ($existingSpecialRow['remark'] ?? '')) !== (string) $specialRow['remark']
                        || trim((string) ($existingSpecialRow['bonus_name'] ?? '')) !== (string) $specialRow['bonus_name']
                        || (int) ($existingSpecialRow['display_order'] ?? 0) !== $displayOrder;

                    if ($hasChanges) {
                        $query = "UPDATE `" . MEMBER_BONUS_SPECIAL_SETTING . "`
                            SET `bonus_name` = '" . $safeBonusName . "',
                                `minimum_purchase_amount` = '" . $minimumAmountSql . "',
                                `minimum_purchase_times` = " . $minimumTimesSql . ",
                                `bonus_points` = " . $bonusPointsSql . ",
                                `remark` = " . ($safeRemark !== '' ? "'" . $safeRemark . "'" : "NULL") . ",
                                `display_order` = " . $displayOrder . ",
                                `update_by` = '" . USER_ID . "',
                                `update_date` = CURDATE(),
                                `update_time` = CURTIME(),
                                `status` = 'A'
                            WHERE `id` = " . $rowId . " AND `status` = 'A'";
                        if (!mysqli_query($connect, $query)) {
                            throw new Exception(mysqli_error($connect));
                        }

                        memberBonusManagementWriteAudit(
                            $connect,
                            $pageTitle,
                            'edit',
                            USER_NAME . ' edited member special bonus setting [<b>ID = ' . $rowId . '</b>] <b>' . htmlspecialchars((string) $specialRow['bonus_name'], ENT_QUOTES, 'UTF-8') . '</b>.',
                            MEMBER_BONUS_SPECIAL_SETTING,
                            $query,
                            memberBonusManagementDescribeSpecialRow($existingSpecialRow),
                            '',
                            memberBonusManagementDescribeSpecialRow($specialRow)
                        );
                        $changeCount++;
                    }
                } else {
                    $query = "INSERT INTO `" . MEMBER_BONUS_SPECIAL_SETTING . "` (
                            `bonus_key`, `bonus_name`, `minimum_purchase_amount`, `minimum_purchase_times`, `bonus_points`, `remark`, `display_order`,
                            `create_by`, `create_date`, `create_time`, `update_by`, `update_date`, `update_time`, `status`
                        ) VALUES (
                            '" . $safeBonusKey . "', '" . $safeBonusName . "', '" . $minimumAmountSql . "', " . $minimumTimesSql . ",
                            " . $bonusPointsSql . ", " . ($safeRemark !== '' ? "'" . $safeRemark . "'" : "NULL") . ", " . $displayOrder . ",
                            '" . USER_ID . "', CURDATE(), CURTIME(), '" . USER_ID . "', CURDATE(), CURTIME(), 'A'
                        )";
                    if (!mysqli_query($connect, $query)) {
                        throw new Exception(mysqli_error($connect));
                    }

                    $newId = (int) mysqli_insert_id($connect);
                    memberBonusManagementWriteAudit(
                        $connect,
                        $pageTitle,
                        'add',
                        USER_NAME . ' added member special bonus setting [<b>ID = ' . $newId . '</b>] <b>' . htmlspecialchars((string) $specialRow['bonus_name'], ENT_QUOTES, 'UTF-8') . '</b>.',
                        MEMBER_BONUS_SPECIAL_SETTING,
                        $query,
                        '',
                        memberBonusManagementDescribeSpecialRow($specialRow)
                    );
                    $changeCount++;
                }
            }

            mysqli_commit($connect);

            if ($changeCount > 0) {
                memberBonusManagementSetPopup('', 'E');
                header('Location: ' . $pageUrl);
                exit;
            }

            memberBonusManagementWriteAudit(
                $connect,
                $pageTitle,
                'edit',
                USER_NAME . ' saved Member Bonus Management special bonus settings but no changes were detected.',
                MEMBER_BONUS_SPECIAL_SETTING,
                'NO_CHANGES'
            );
            memberBonusManagementSetPopup('', 'NC');
            header('Location: ' . $pageUrl);
            exit;
        } catch (Exception $exception) {
            mysqli_rollback($connect);
            $specialErrors[] = 'Failed to save special bonus settings. ' . $exception->getMessage();
            memberBonusManagementWriteAudit(
                $connect,
                $pageTitle,
                'edit',
                USER_NAME . ' failed to save Member Bonus Management special bonus settings.',
                MEMBER_BONUS_SPECIAL_SETTING,
                'SAVE_FAILED'
            );
        }
    }
}

if (empty($tierRows)) {
    $tierRows = array(memberBonusManagementBlankTierRow());
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .member-bonus-card {
            border: 1px solid #e4e8ef;
            border-radius: 16px;
            box-shadow: 0 .5rem 1rem rgba(15, 23, 42, .05);
        }

        .member-bonus-table th,
        .member-bonus-table td {
            vertical-align: middle;
        }

        .member-bonus-table input,
        .member-bonus-table textarea,
        .member-bonus-table select {
            min-width: 120px;
        }

        .member-bonus-action-btn {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid transparent;
            background: #fff;
        }

        .member-bonus-add-btn {
            color: #16a34a;
            border-color: rgba(22, 163, 74, .25);
        }

        .member-bonus-remove-btn {
            color: #dc2626;
            border-color: rgba(220, 38, 38, .2);
        }

        .member-bonus-save-btn {
            text-transform: none !important;
            min-width: 92px;
            height: 34px;
            padding: 0 24px !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1;
        }

        .member-bonus-save-wrap {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
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
                    <form method="post" action="" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="member-bonus-card bg-white p-4">
                            <div class="mb-4">
                                <h2 class="mb-1"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                                <div class="text-muted">Manage member tier thresholds, point ratios, and recurring bonus point rules used by the member-point cron.</div>
                            </div>

                            <?php if (!empty($tierErrors)) { ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($tierErrors as $tierError) { ?>
                                        <div><?= htmlspecialchars((string) $tierError, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <h5 class="mb-3">Member Tier Bonus Management</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle member-bonus-table mb-0" id="memberBonusTierTable">
                                    <thead>
                                        <tr>
                                            <th width="180">Member Tier</th>
                                            <th width="170">Requirement Type</th>
                                            <th width="150">Minimum Purchase Amount (RM)</th>
                                            <th width="130">Private Ratio (%)</th>
                                            <th width="150">Shopee/Lazada Ratio (%)</th>
                                            <th width="110">Bonus Points</th>
                                            <th width="120">Bonus Frequency</th>
                                            <th width="240">Remark</th>
                                            <?php if ($canManage) { ?>
                                                <th width="120">Action</th>
                                            <?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody id="memberBonusTierTableBody">
                                        <?php foreach ($tierRows as $tierRow) { ?>
                                            <tr class="member-bonus-tier-row">
                                                <td>
                                                    <input type="hidden" name="tier_row_id[]" value="<?= (int) ($tierRow['id'] ?? 0) ?>">
                                                    <input type="hidden" name="tier_key[]" value="<?= htmlspecialchars((string) ($tierRow['tier_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="text" class="form-control" name="tier_name[]" value="<?= htmlspecialchars((string) ($tierRow['tier_name'] ?? ($tierRow['label'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <select class="form-select member-bonus-requirement-type" name="requirement_type[]" <?= $fieldDisabled ?>>
                                                        <?php foreach (memberPointGetTierRequirementOptions() as $requirementValue => $requirementLabel) { ?>
                                                            <option value="<?= htmlspecialchars($requirementValue, ENT_QUOTES, 'UTF-8') ?>" <?= strtolower((string) ($tierRow['requirement_type'] ?? 'register')) === $requirementValue ? 'selected' : '' ?>><?= htmlspecialchars($requirementLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="0.01" class="form-control member-bonus-minimum-amount" name="minimum_purchase_amount[]" value="<?= htmlspecialchars(memberBonusManagementFormatDecimal($tierRow['minimum_purchase_amount'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="0.01" class="form-control" name="private_point_rate[]" value="<?= htmlspecialchars(memberBonusManagementFormatPercent($tierRow['private_point_rate'] ?? 0.03), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="0.01" class="form-control" name="marketplace_point_rate[]" value="<?= htmlspecialchars(memberBonusManagementFormatPercent($tierRow['marketplace_point_rate'] ?? 0.03), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" step="1" class="form-control" name="bonus_points[]" value="<?= htmlspecialchars((string) ($tierRow['bonus_points'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>>
                                                </td>
                                                <td>
                                                    <select class="form-select" name="bonus_frequency[]" <?= $fieldDisabled ?>>
                                                        <?php foreach (memberPointGetBonusFrequencyOptions() as $frequencyValue => $frequencyLabel) { ?>
                                                            <option value="<?= htmlspecialchars($frequencyValue, ENT_QUOTES, 'UTF-8') ?>" <?= strtolower((string) ($tierRow['bonus_frequency'] ?? 'monthly')) === $frequencyValue ? 'selected' : '' ?>><?= htmlspecialchars($frequencyLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <textarea class="form-control" name="remark[]" rows="2" <?= $fieldDisabled ?>><?= htmlspecialchars((string) ($tierRow['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                                </td>
                                                <?php if ($canManage) { ?>
                                                    <td>
                                                        <div class="d-flex gap-2 justify-content-center">
                                                            <button type="button" class="member-bonus-action-btn member-bonus-add-btn" data-member-bonus-add="1" aria-label="Add row">
                                                                <i class="fa-solid fa-square-plus"></i>
                                                            </button>
                                                            <button type="button" class="member-bonus-action-btn member-bonus-remove-btn" data-member-bonus-remove="1" aria-label="Remove row">
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
                                <div class="member-bonus-save-wrap">
                                    <button type="submit" class="btn btn-primary btn-rounded px-4 member-bonus-save-btn" name="actionBtn" value="saveTierSetting">Save</button>
                                </div>
                            <?php } ?>
                        </div>
                    </form>

                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="member-bonus-card bg-white p-4">
                            <div class="mb-4">
                                <h5 class="mb-1">Birthday and Monthly Purchase Bonus</h5>
                                <div class="text-muted">Manage the bonus points for birthday-month rewards and monthly purchase rewards. These rewards are saved into the private point wallet.</div>
                            </div>

                            <?php if (!empty($specialErrors)) { ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($specialErrors as $specialError) { ?>
                                        <div><?= htmlspecialchars((string) $specialError, ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle member-bonus-table mb-0">
                                    <thead>
                                        <tr>
                                            <th width="220">Bonus Type</th>
                                            <th width="180">Minimum Purchase Amount (RM)</th>
                                            <th width="140">Purchase Times</th>
                                            <th width="120">Bonus Points</th>
                                            <th width="280">Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Birthday Bonus</td>
                                            <td><input type="text" class="form-control" value="Birthday month" readonly></td>
                                            <td><input type="text" class="form-control" value="-" readonly></td>
                                            <td><input type="number" min="0" step="1" class="form-control" name="birthday_bonus_points" value="<?= htmlspecialchars((string) ($specialBonusRows['birthday']['bonus_points'] ?? 10), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>></td>
                                            <td><textarea class="form-control" name="birthday_remark" rows="2" <?= $fieldDisabled ?>><?= htmlspecialchars((string) ($specialBonusRows['birthday']['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></td>
                                        </tr>
                                        <tr>
                                            <td>Monthly Purchase Bonus</td>
                                            <td><input type="number" min="0" step="0.01" class="form-control" name="monthly_purchase_amount" value="<?= htmlspecialchars(memberBonusManagementFormatDecimal($specialBonusRows['monthly_purchase']['minimum_purchase_amount'] ?? 300), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>></td>
                                            <td><input type="number" min="0" step="1" class="form-control" name="monthly_purchase_times" value="<?= htmlspecialchars((string) ($specialBonusRows['monthly_purchase']['minimum_purchase_times'] ?? 2), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>></td>
                                            <td><input type="number" min="0" step="1" class="form-control" name="monthly_purchase_bonus_points" value="<?= htmlspecialchars((string) ($specialBonusRows['monthly_purchase']['bonus_points'] ?? 10), ENT_QUOTES, 'UTF-8') ?>" <?= $fieldDisabled ?>></td>
                                            <td><textarea class="form-control" name="monthly_purchase_remark" rows="2" <?= $fieldDisabled ?>><?= htmlspecialchars((string) ($specialBonusRows['monthly_purchase']['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($canManage) { ?>
                                <div class="member-bonus-save-wrap">
                                    <button type="submit" class="btn btn-primary btn-rounded px-4 member-bonus-save-btn" name="actionBtn" value="saveSpecialBonusSetting">Save</button>
                                </div>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManage) { ?>
        <template id="memberBonusTierRowTemplate">
            <tr class="member-bonus-tier-row">
                <td>
                    <input type="hidden" name="tier_row_id[]" value="0">
                    <input type="hidden" name="tier_key[]" value="">
                    <input type="text" class="form-control" name="tier_name[]" value="">
                </td>
                <td>
                    <select class="form-select member-bonus-requirement-type" name="requirement_type[]">
                        <?php foreach (memberPointGetTierRequirementOptions() as $requirementValue => $requirementLabel) { ?>
                            <option value="<?= htmlspecialchars($requirementValue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($requirementLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php } ?>
                    </select>
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="form-control member-bonus-minimum-amount" name="minimum_purchase_amount[]" value="">
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="form-control" name="private_point_rate[]" value="3">
                </td>
                <td>
                    <input type="number" min="0" step="0.01" class="form-control" name="marketplace_point_rate[]" value="3">
                </td>
                <td>
                    <input type="number" min="0" step="1" class="form-control" name="bonus_points[]" value="0">
                </td>
                <td>
                    <select class="form-select" name="bonus_frequency[]">
                        <?php foreach (memberPointGetBonusFrequencyOptions() as $frequencyValue => $frequencyLabel) { ?>
                            <option value="<?= htmlspecialchars($frequencyValue, ENT_QUOTES, 'UTF-8') ?>" <?= $frequencyValue === 'monthly' ? 'selected' : '' ?>><?= htmlspecialchars($frequencyLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php } ?>
                    </select>
                </td>
                <td>
                    <textarea class="form-control" name="remark[]" rows="2"></textarea>
                </td>
                <td>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="member-bonus-action-btn member-bonus-add-btn" data-member-bonus-add="1" aria-label="Add row">
                            <i class="fa-solid fa-square-plus"></i>
                        </button>
                        <button type="button" class="member-bonus-action-btn member-bonus-remove-btn" data-member-bonus-remove="1" aria-label="Remove row">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </td>
            </tr>
        </template>

        <script>
            (function() {
                var tableBody = document.getElementById('memberBonusTierTableBody');
                var rowTemplate = document.getElementById('memberBonusTierRowTemplate');
                if (!tableBody || !rowTemplate) {
                    return;
                }

                function toggleMinimumAmount(row) {
                    var requirementSelect = row.querySelector('.member-bonus-requirement-type');
                    var amountInput = row.querySelector('.member-bonus-minimum-amount');
                    if (!requirementSelect || !amountInput) {
                        return;
                    }

                    if (requirementSelect.value === 'register') {
                        amountInput.value = '0';
                        amountInput.readOnly = true;
                    } else {
                        amountInput.readOnly = false;
                    }
                }

                function bindRow(row) {
                    var requirementSelect = row.querySelector('.member-bonus-requirement-type');
                    if (requirementSelect) {
                        requirementSelect.addEventListener('change', function() {
                            toggleMinimumAmount(row);
                        });
                    }
                    toggleMinimumAmount(row);
                }

                tableBody.addEventListener('click', function(event) {
                    var addButton = event.target.closest('[data-member-bonus-add]');
                    var removeButton = event.target.closest('[data-member-bonus-remove]');

                    if (addButton) {
                        var fragment = rowTemplate.content.cloneNode(true);
                        var newRow = fragment.querySelector('.member-bonus-tier-row');
                        tableBody.appendChild(fragment);
                        bindRow(tableBody.lastElementChild);
                    }

                    if (removeButton) {
                        if (tableBody.querySelectorAll('.member-bonus-tier-row').length <= 1) {
                            var currentRow = removeButton.closest('.member-bonus-tier-row');
                            if (!currentRow) {
                                return;
                            }
                            currentRow.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(function(input) {
                                if (input.name === 'private_point_rate[]' || input.name === 'marketplace_point_rate[]') {
                                    input.value = '3';
                                } else if (input.name === 'bonus_points[]' || input.name === 'minimum_purchase_amount[]') {
                                    input.value = '0';
                                } else {
                                    input.value = '';
                                }
                            });
                            currentRow.querySelectorAll('input[type="hidden"]').forEach(function(input) {
                                input.value = '';
                            });
                            var selectElements = currentRow.querySelectorAll('select');
                            if (selectElements[0]) {
                                selectElements[0].value = 'register';
                            }
                            if (selectElements[1]) {
                                selectElements[1].value = 'monthly';
                            }
                            toggleMinimumAmount(currentRow);
                            return;
                        }

                        var row = removeButton.closest('.member-bonus-tier-row');
                        if (row) {
                            row.remove();
                        }
                    }
                });

                Array.prototype.forEach.call(tableBody.querySelectorAll('.member-bonus-tier-row'), bindRow);
            })();
        </script>
    <?php } ?>
    <?php memberBonusManagementRenderPopupScript($pageTitle, $pageUrl); ?>
</body>
</html>
