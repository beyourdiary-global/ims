<?php
ob_start();
$pageTitle = "Campaign Rule Setting";
$currentPagePin = 154;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';


if ($finance_connect instanceof mysqli) {
    @mysqli_set_charset($finance_connect, 'utf8mb4');
}

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if (!empty($resolvedPageTitle)) {
    $pageTitle = $resolvedPageTitle;
}

$tblName = CAMPAIGN_RULE_SETTING;
$redirectPage = $SITEURL . '/campaign/campaign_rule_setting_table.php';
$pinAccess = checkCurrentPin($connect, $pageTitle);

$dataId = (int) (!empty(input('id')) ? input('id') : post('id'));
$act = !empty(input('act')) ? input('act') : post('act');
$isAdd = $act === $act_1;
$isEdit = $act === $act_2;
$isView = !$isAdd && !$isEdit;
$pageAction = $isAdd ? 'Add' : ($isEdit ? 'Edit' : 'View');
$pageActionTitle = displayPageAction($act, $pageTitle);

$canAdd = isActionAllowed('Add', $pinAccess);
$canEdit = isActionAllowed('Edit', $pinAccess);
$canDelete = isActionAllowed('Delete', $pinAccess);

if (($isAdd && !$canAdd) || ($isEdit && !$canEdit) || ($isView && !isActionAllowed('View', $pinAccess))) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

if (!$isAdd && $dataId <= 0) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

$csrfToken = campaignCsrfToken('campaign_rule_setting');
$scheduleOptions = array('Daily', 'Weekly', 'Monthly', 'End Of Month', 'Monthly Day');
$periodRuleOptions = array('Current Month', 'Next Month', 'Custom Days');
$statusOptions = array('Active', 'Inactive');
$generateDayEnabledSchedules = array('Weekly', 'Monthly Day');
$platformOptions = campaignRuleConditionPlatformOptions();
$tagOptions = campaignRuleFetchActiveTagOptions($connect);
$brandOptions = campaignRuleFetchActiveBrandOptions($connect);
$lastOrderOptions = campaignRuleConditionLastOrderOptions();
$userOptions = campaignFetchUsers($connect);
$messageOptions = campaignFetchMessageShortcutOptions($connect);

function campaignRuleSettingAudit($connect, $pageTitle, $action, $message, $query = '')
{
    campaignAudit($connect, $pageTitle, $action, $message, $query, CAMPAIGN_RULE_SETTING);
}

function campaignRuleSettingExtractShortcutIds($items)
{
    $ids = array();
    foreach ((array) $items as $item) {
        $shortcutId = is_array($item) ? (int) ($item['shortcut_id'] ?? 0) : (int) $item;
        if ($shortcutId > 0) {
            $ids[] = $shortcutId;
        }
    }

    return array_values(array_unique($ids));
}

function campaignRuleSettingBuildMessageJson($shortcutIds)
{
    $payload = array();
    foreach ((array) $shortcutIds as $shortcutId) {
        $shortcutId = (int) $shortcutId;
        if ($shortcutId > 0) {
            $payload[] = array('shortcut_id' => $shortcutId);
        }
    }

    return campaignRuleSettingJsonEncode($payload);
}

if (post('actionBtnHidden') === 'estimateCustomerTargetRules') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    if (!campaignVerifyCsrf('campaign_rule_setting', post('csrf_token'))) {
        http_response_code(403);
        echo campaignRuleSettingJsonEncode(array('success' => false, 'count' => 0, 'message' => 'Invalid security token.'));
        exit();
    }

    $existingConditionJson = trim((string) post('customer_condition_existing_json'));
    $conditionJson = campaignRuleConditionBuildJsonFromPost(null, $existingConditionJson);
    $count = campaignRuleEstimateMatchedCustomers($connect, $finance_connect, $conditionJson);
    echo campaignRuleSettingJsonEncode(array('success' => true, 'count' => $count));
    exit();
}

$deleteRequested = post('actionBtn') === 'deleteRule' || post('act') === 'D';
$deleteUsesCommonDialog = post('act') === 'D';
if ($deleteRequested) {
    $ruleId = (int) ($deleteUsesCommonDialog ? post('id') : post('id'));
    if ($ruleId <= 0) {
        $ruleId = (int) input('id');
    }

    $existingRule = campaignFetchRuleById($connect, $ruleId);
    if (!$canDelete || $ruleId <= 0 || empty($existingRule)) {
        if ($deleteUsesCommonDialog) {
            http_response_code(403);
            echo 'Unable to delete Campaign Rule Setting.';
            exit();
        }

        campaignSetPopup('Unable to delete Campaign Rule Setting.', $redirectPage, 'ErrMO');
        echo '<script>location.href = "' . $redirectPage . '";</script>';
        exit();
    }

    $deleteSql = "UPDATE " . campaignTableName(CAMPAIGN_RULE_SETTING) . " SET `status`='D', `update_by`='" . $connect->real_escape_string((string) USER_ID) . "', `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`='" . $ruleId . "' AND `status`='A'";
    if ($connect->query($deleteSql)) {
        campaignRuleSettingAudit($connect, $pageTitle, 'delete', USER_NAME . ' deleted campaign rule setting [<b> ID = ' . $ruleId . '</b>] <b>' . campaignH($existingRule['rule_name'] ?? '') . '</b>.', $deleteSql);
        if ($deleteUsesCommonDialog) {
            echo 'OK';
            exit();
        }

        campaignSetPopup('Successful Delete Campaign Rule Setting', $redirectPage, 'ErrMO');
    } else {
        campaignRuleSettingAudit($connect, $pageTitle, 'delete', USER_NAME . ' failed to delete campaign rule setting [<b> ID = ' . $ruleId . '</b>].', $deleteSql);
        if ($deleteUsesCommonDialog) {
            http_response_code(500);
            echo 'Failed to delete Campaign Rule Setting.';
            exit();
        }

        campaignSetPopup('Failed to delete Campaign Rule Setting.', $redirectPage, 'ErrMO');
    }

    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

$row = $isAdd ? array() : campaignFetchRuleById($connect, $dataId);
if (!$isAdd && empty($row)) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

$selectedDefaultPicIds = $isAdd ? array() : campaignRuleDecodeJson($row['default_pic_json'] ?? '', array());
$selectedDefaultMessageIds = $isAdd ? array() : campaignRuleSettingExtractShortcutIds(campaignRuleDecodeJson($row['default_message_json'] ?? '', array()));
$selectedDefaultPicId = !empty($selectedDefaultPicIds) ? (int) $selectedDefaultPicIds[0] : 0;
$selectedDefaultPicName = '';
$userAutocompleteOptions = $userOptions;
foreach ($userAutocompleteOptions as $userOption) {
    if ((int) ($userOption['id'] ?? 0) === $selectedDefaultPicId) {
        $selectedDefaultPicName = (string) ($userOption['name'] ?? '');
        break;
    }
}
$errors = array();
$formValues = array(
    'rule_name' => isset($row['rule_name']) ? (string) $row['rule_name'] : '',
    'generate_schedule' => isset($row['generate_schedule']) ? (string) $row['generate_schedule'] : 'Daily',
    'generate_day' => isset($row['generate_day']) ? (string) $row['generate_day'] : '',
    'campaign_name_template' => isset($row['campaign_name_template']) ? (string) $row['campaign_name_template'] : '',
    'campaign_period_rule' => isset($row['campaign_period_rule']) ? (string) $row['campaign_period_rule'] : 'Current Month',
    'customer_condition_json' => isset($row['customer_condition_json']) && trim((string) $row['customer_condition_json']) !== '' ? (string) $row['customer_condition_json'] : '{}',
    'rule_status' => isset($row['rule_status']) ? (string) $row['rule_status'] : 'Active',
    'remark' => isset($row['remark']) ? (string) $row['remark'] : '',
);

if (post('actionBtn') === 'saveRule') {
    $postedToken = (string) post('csrf_token');
    $existingConditionJson = trim((string) post('customer_condition_existing_json'));
    $formValues['rule_name'] = campaignNormalizeTextValue(post('rule_name'), 255);
    $formValues['generate_schedule'] = campaignNormalizeTextValue(post('generate_schedule'), 100);
    $formValues['generate_day'] = campaignNormalizeTextValue(post('generate_day'), 50);
    $formValues['campaign_name_template'] = campaignNormalizeTextValue(post('campaign_name_template'), 255);
    $formValues['campaign_period_rule'] = campaignNormalizeTextValue(post('campaign_period_rule'), 100);
    $formValues['customer_condition_json'] = campaignRuleConditionBuildJsonFromPost(null, $existingConditionJson);
    $formValues['rule_status'] = campaignNormalizeTextValue(post('rule_status'), 30);
    $formValues['remark'] = campaignNormalizeTextValue(post('remark'), 65535);
    $targetCustomPeriodDays = max(0, (int) post('custom_period_days'));
    $selectedDefaultPicName = trim((string) post('default_pic_search'));
    $selectedDefaultPicId = (int) post('default_pic_id');
    $selectedDefaultPicIds = $selectedDefaultPicId > 0 ? array($selectedDefaultPicId) : array();
    $selectedDefaultMessageIds = campaignRuleSettingReadSelectedIds('default_message_shortcut_ids');

    if (!campaignVerifyCsrf('campaign_rule_setting', $postedToken)) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    if ($formValues['rule_name'] === '') {
        $errors[] = 'Rule Name is required.';
    }
    if (!in_array($formValues['generate_schedule'], $scheduleOptions, true)) {
        $errors[] = 'Invalid Generate Schedule.';
    }
    if (!in_array($formValues['generate_schedule'], $generateDayEnabledSchedules, true)) {
        $formValues['generate_day'] = '';
    }
    if ($formValues['campaign_name_template'] === '') {
        $errors[] = 'Campaign Name Template is required.';
    }
    if (!in_array($formValues['campaign_period_rule'], $periodRuleOptions, true)) {
        $errors[] = 'Invalid Campaign Period Rule.';
    }
    if ($formValues['campaign_period_rule'] === 'Custom Days' && $targetCustomPeriodDays <= 0) {
        $errors[] = 'Custom Days must be greater than 0.';
    }
    if (!in_array($formValues['rule_status'], $statusOptions, true)) {
        $errors[] = 'Invalid Status.';
    }

    if (empty($errors)) {
        $userId = campaignCurrentUserId();
        $defaultPicJson = campaignRuleSettingJsonEncode($selectedDefaultPicIds);
        $defaultMessageJson = campaignRuleSettingBuildMessageJson($selectedDefaultMessageIds);
        $queryForAudit = '';

        if ($isAdd) {
            $stmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_RULE_SETTING) . " (`rule_name`,`generate_schedule`,`generate_day`,`campaign_name_template`,`campaign_period_rule`,`customer_condition_json`,`default_pic_json`,`default_message_json`,`rule_status`,`remark`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE(),CURTIME(),'A')");
            if ($stmt) {
                $stmt->bind_param('sssssssssss', $formValues['rule_name'], $formValues['generate_schedule'], $formValues['generate_day'], $formValues['campaign_name_template'], $formValues['campaign_period_rule'], $formValues['customer_condition_json'], $defaultPicJson, $defaultMessageJson, $formValues['rule_status'], $formValues['remark'], $userId);
                if ($stmt->execute()) {
                    $dataId = (int) $stmt->insert_id;
                    $queryForAudit = 'INSERT INTO ' . CAMPAIGN_RULE_SETTING . ' id=' . $dataId;
                    campaignRuleSettingAudit($connect, $pageTitle, 'add', USER_NAME . ' added campaign rule setting [<b> ID = ' . $dataId . '</b>] <b>' . campaignH($formValues['rule_name']) . '</b>.', $queryForAudit);
                    $stmt->close();
                    campaignSetPopup('Campaign Rule Setting added successfully.', $redirectPage, 'ErrMO');
                    echo '<script>location.href = "' . $redirectPage . '";</script>';
                    exit();
                }
                $stmt->close();
            }

            $errors[] = 'Failed to add Campaign Rule Setting.';
        } else {
            $stmt = $connect->prepare("UPDATE " . campaignTableName(CAMPAIGN_RULE_SETTING) . " SET `rule_name`=?, `generate_schedule`=?, `generate_day`=?, `campaign_name_template`=?, `campaign_period_rule`=?, `customer_condition_json`=?, `default_pic_json`=?, `default_message_json`=?, `rule_status`=?, `remark`=?, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `status`='A'");
            if ($stmt) {
                $stmt->bind_param('sssssssssssi', $formValues['rule_name'], $formValues['generate_schedule'], $formValues['generate_day'], $formValues['campaign_name_template'], $formValues['campaign_period_rule'], $formValues['customer_condition_json'], $defaultPicJson, $defaultMessageJson, $formValues['rule_status'], $formValues['remark'], $userId, $dataId);
                if ($stmt->execute()) {
                    $queryForAudit = 'UPDATE ' . CAMPAIGN_RULE_SETTING . ' id=' . $dataId;
                    campaignRuleSettingAudit($connect, $pageTitle, 'edit', USER_NAME . ' edited campaign rule setting [<b> ID = ' . $dataId . '</b>] <b>' . campaignH($formValues['rule_name']) . '</b>.', $queryForAudit);
                    $stmt->close();
                    campaignSetPopup('Campaign Rule Setting saved successfully.', $redirectPage, 'ErrMO');
                    echo '<script>location.href = "' . $redirectPage . '";</script>';
                    exit();
                }
                $stmt->close();
            }

            $errors[] = 'Failed to save Campaign Rule Setting.';
        }
    }
}

if (post('actionBtn') === 'duplicateRule') {
    if (!campaignVerifyCsrf('campaign_rule_setting', post('csrf_token')) || !$canAdd || $dataId <= 0) {
        campaignSetPopup('Unable to duplicate Campaign Rule Setting.', $redirectPage, 'ErrMO');
        echo '<script>location.href = "' . $redirectPage . '";</script>';
        exit();
    }

    $rule = campaignFetchRuleById($connect, $dataId);
    if (!empty($rule)) {
        $userId = campaignCurrentUserId();
        $ruleName = 'Copy of ' . ($rule['rule_name'] ?? 'Rule');
        $stmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_RULE_SETTING) . " (`rule_name`,`generate_schedule`,`generate_day`,`campaign_name_template`,`campaign_period_rule`,`customer_condition_json`,`default_pic_json`,`default_message_json`,`rule_status`,`remark`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE(),CURTIME(),'A')");
        if ($stmt) {
            $stmt->bind_param('sssssssssss', $ruleName, $rule['generate_schedule'], $rule['generate_day'], $rule['campaign_name_template'], $rule['campaign_period_rule'], $rule['customer_condition_json'], $rule['default_pic_json'], $rule['default_message_json'], $rule['rule_status'], $rule['remark'], $userId);
            $stmt->execute();
            $newRuleId = (int) $stmt->insert_id;
            $stmt->close();
            campaignRuleSettingAudit($connect, $pageTitle, 'add', USER_NAME . ' duplicated campaign rule setting [<b> ID = ' . $dataId . '</b>] to [<b> ID = ' . $newRuleId . '</b>].');
        }
    }

    campaignSetPopup('Campaign Rule Setting duplicated successfully.', $redirectPage, 'ErrMO');
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

if (post('actionBtn') === 'runRule') {
    if (!campaignVerifyCsrf('campaign_rule_setting', post('csrf_token')) || !$canAdd || $dataId <= 0) {
        campaignSetPopup('Unable to run Campaign Rule.', $redirectPage, 'ErrMO');
        echo '<script>location.href = "' . $redirectPage . '";</script>';
        exit();
    }

    $result = campaignRuleGenerateCampaign($connect, $finance_connect, $dataId, true);
    campaignRuleSettingAudit($connect, $pageTitle, 'add', USER_NAME . ' ran campaign rule setting [<b> ID = ' . $dataId . '</b>]. ' . ($result['message'] ?? ''));
    campaignSetPopup($result['message'] !== '' ? $result['message'] : 'Campaign rule run completed.', $redirectPage, 'ErrMO');
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

if ($isView && $dataId > 0 && USER_ID && empty($_SESSION['campaign_rule_setting_view_' . $dataId])) {
    $_SESSION['campaign_rule_setting_view_' . $dataId] = 1;
    campaignRuleSettingAudit($connect, $pageTitle, 'view', USER_NAME . ' viewed campaign rule setting [<b> ID = ' . $dataId . '</b>] <b>' . campaignH($formValues['rule_name']) . '</b>.');
}

$readonlyAttr = $isView ? 'readonly' : '';
$disabledAttr = $isView ? 'disabled' : '';
$generateDayDisabledAttr = ($isView || !in_array($formValues['generate_schedule'], $generateDayEnabledSchedules, true)) ? 'disabled' : '';
$targetCondition = campaignRuleConditionDecodeForUi($formValues['customer_condition_json']);
$selectedTargetPlatforms = $targetCondition['platforms'];
$selectedTargetTags = $targetCondition['tags'];
$selectedTargetBrands = $targetCondition['brands'];
$selectedTargetLastOrder = $targetCondition['last_order_key'];
$targetCustomPeriodDays = 0;
$decodedCustomerCondition = campaignRuleDecodeJson($formValues['customer_condition_json'], array());
if (is_array($decodedCustomerCondition) && isset($decodedCustomerCondition['period_days'])) {
    $targetCustomPeriodDays = max(0, (int) $decodedCustomerCondition['period_days']);
}
$estimatedCustomers = campaignRuleEstimateMatchedCustomers($connect, $finance_connect, $formValues['customer_condition_json']);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .campaign-rule-card {
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            background: #fff;
            padding: 18px;
        }

        .campaign-rule-card-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .campaign-rule-card-note {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 14px;
        }

        .campaign-rule-multiselect {
            position: relative;
        }

        .campaign-rule-multiselect-control {
            min-height: 38px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            background: #fff;
        }

        .campaign-rule-multiselect-control.disabled {
            background: #e9ecef;
            cursor: default;
        }

        .campaign-rule-multiselect-values {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            flex: 1 1 auto;
        }

        .campaign-rule-multiselect-placeholder {
            color: #6c757d;
        }

        .campaign-rule-multiselect-tag {
            display: inline-flex;
            align-items: center;
            border-radius: 14px;
            background: #eef3fb;
            color: #495057;
            padding: 2px 10px;
            font-size: 12px;
            line-height: 1.4;
            max-width: 100%;
            word-break: break-word;
        }

        .campaign-rule-multiselect-arrow {
            color: #6c757d;
            flex: 0 0 auto;
        }

        .campaign-rule-multiselect-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 25;
            display: none;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            padding: 8px 0;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
        }

        .campaign-rule-multiselect.open .campaign-rule-multiselect-menu {
            display: block;
        }

        .campaign-rule-multiselect-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            margin: 0;
            font-size: 14px;
            cursor: pointer;
        }

        .campaign-rule-multiselect-option:hover {
            background: #f8f9fa;
        }

        .campaign-rule-summary-box,
        .campaign-rule-estimate-box {
            border: 1px solid #dfe3e8;
            border-radius: 6px;
            background: #fff;
            min-height: 88px;
            padding: 14px;
        }

        .campaign-rule-summary-title,
        .campaign-rule-estimate-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .campaign-rule-summary-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .campaign-rule-summary-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 14px;
            padding: 4px 12px;
            font-size: 12px;
            line-height: 1.4;
            max-width: 100%;
            word-break: break-word;
        }

        .campaign-rule-summary-badge.platform {
            background: #edf4ff;
            color: #2d6cdf;
        }

        .campaign-rule-summary-badge.tag {
            background: #eef9ef;
            color: #2f8f46;
        }

        .campaign-rule-summary-badge.brand {
            background: #f4efff;
            color: #8559d8;
        }

        .campaign-rule-summary-badge.last-order {
            background: #fff2e8;
            color: #f08a24;
        }

        .campaign-rule-summary-empty {
            color: #6c757d;
            font-size: 13px;
        }

        .campaign-rule-estimate-box {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .campaign-rule-estimate-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 1px solid #c9dbff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #4a7df0;
            font-size: 22px;
            background: #f6f9ff;
            flex: 0 0 auto;
        }

        .campaign-rule-estimate-value {
            font-size: 34px;
            font-weight: 700;
            line-height: 1;
            color: #212529;
        }

        .campaign-rule-condition-cell {
            max-width: 320px;
            white-space: normal;
        }

        .campaign-rule-dropdown-label {
            flex: 1 1 auto;
            min-width: 0;
        }

        .campaign-rule-dropdown-text {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p>
                <a href="<?= $redirectPage ?>"><?= campaignH($pageTitle) ?></a>
                <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= campaignH($pageActionTitle) ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-12 col-md-8 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= (int) $dataId ?>">
                    <input type="hidden" name="act" value="<?= campaignH($act) ?>">

                    <div class="form-group mb-4">
                        <h2><?= campaignH($pageActionTitle) ?></h2>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label form_lbl" for="rule_name">Rule Name*</label>
                                <input class="form-control" type="text" name="rule_name" id="rule_name" value="<?= campaignH($formValues['rule_name']) ?>" <?= $readonlyAttr ?> required autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="generate_schedule">Generate Schedule*</label>
                                <select class="form-select" name="generate_schedule" id="generate_schedule" <?= $disabledAttr ?> required>
                                    <?php foreach ($scheduleOptions as $option): ?>
                                        <option value="<?= campaignH($option) ?>" <?= $formValues['generate_schedule'] === $option ? 'selected' : '' ?>><?= campaignH($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="generate_day">Generate Day</label>
                                <input class="form-control" type="text" name="generate_day" id="generate_day" value="<?= campaignH($formValues['generate_day']) ?>" <?= $readonlyAttr ?> <?= $generateDayDisabledAttr ?> placeholder="Example: 15 or 1-7" autocomplete="off">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="campaign_period_rule">Campaign Period Rule*</label>
                                <select class="form-select" name="campaign_period_rule" id="campaign_period_rule" <?= $disabledAttr ?> required>
                                    <?php foreach ($periodRuleOptions as $option): ?>
                                        <option value="<?= campaignH($option) ?>" <?= $formValues['campaign_period_rule'] === $option ? 'selected' : '' ?>><?= campaignH($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="customPeriodDaysGroup" style="<?= $formValues['campaign_period_rule'] === 'Custom Days' ? '' : 'display:none;' ?>">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="custom_period_days">Custom Days*</label>
                                <input class="form-control" type="number" min="1" step="1" name="custom_period_days" id="custom_period_days" value="<?= (int) $targetCustomPeriodDays ?>" <?= $readonlyAttr ?> <?= $formValues['campaign_period_rule'] === 'Custom Days' ? '' : 'disabled' ?> placeholder="Example: 30" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="campaign_name_template">Campaign Name Template*</label>
                        <input class="form-control" type="text" name="campaign_name_template" id="campaign_name_template" value="<?= campaignH($formValues['campaign_name_template']) ?>" <?= $readonlyAttr ?> placeholder="Birthday Campaign - {month} {year}" required autocomplete="off">
                    </div>

                    <input type="hidden" name="customer_condition_json" id="customer_condition_json" value="<?= campaignH($formValues['customer_condition_json']) ?>">
                    <input type="hidden" name="customer_condition_existing_json" id="customer_condition_existing_json" value="<?= campaignH($formValues['customer_condition_json']) ?>">

                    <div class="form-group mb-3">
                        <div class="campaign-rule-card">
                            <div class="campaign-rule-card-title">Customer Target Rules</div>
                            <div class="campaign-rule-card-note">Select target customers using simple filters.</div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label form_lbl" for="target_platforms">Platform</label>
                                    <div class="campaign-rule-multiselect <?= $isView ? 'is-view' : '' ?>" data-select-id="target_platforms" data-placeholder="All" data-refresh-target-rules="1">
                                        <div class="form-control campaign-rule-multiselect-control <?= $isView ? 'disabled' : '' ?>" tabindex="<?= $isView ? '-1' : '0' ?>">
                                            <div class="campaign-rule-multiselect-values"></div>
                                            <div class="campaign-rule-multiselect-arrow"><i class="fa-solid fa-chevron-down fa-xs"></i></div>
                                        </div>
                                        <?php if (!$isView): ?>
                                            <div class="campaign-rule-multiselect-menu">
                                                <?php foreach ($platformOptions as $optionValue => $optionLabel): ?>
                                                    <label class="campaign-rule-multiselect-option">
                                                        <input type="checkbox" value="<?= campaignH($optionValue) ?>" <?= in_array($optionValue, $selectedTargetPlatforms, true) ? 'checked' : '' ?>>
                                                        <span><?= campaignH($optionLabel) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <select class="d-none" name="target_platforms[]" id="target_platforms" multiple>
                                            <?php foreach ($platformOptions as $optionValue => $optionLabel): ?>
                                                <option value="<?= campaignH($optionValue) ?>" <?= in_array($optionValue, $selectedTargetPlatforms, true) ? 'selected' : '' ?>><?= campaignH($optionLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label form_lbl" for="target_tags">Tag</label>
                                    <div class="campaign-rule-multiselect <?= $isView ? 'is-view' : '' ?>" data-select-id="target_tags" data-placeholder="All" data-refresh-target-rules="1">
                                        <div class="form-control campaign-rule-multiselect-control <?= $isView ? 'disabled' : '' ?>" tabindex="<?= $isView ? '-1' : '0' ?>">
                                            <div class="campaign-rule-multiselect-values"></div>
                                            <div class="campaign-rule-multiselect-arrow"><i class="fa-solid fa-chevron-down fa-xs"></i></div>
                                        </div>
                                        <?php if (!$isView): ?>
                                            <div class="campaign-rule-multiselect-menu">
                                                <?php foreach ($tagOptions as $option): ?>
                                                    <?php $optionValue = (string) ($option['id'] ?? ''); ?>
                                                    <label class="campaign-rule-multiselect-option">
                                                        <input type="checkbox" value="<?= campaignH($optionValue) ?>" <?= in_array($optionValue, $selectedTargetTags, true) ? 'checked' : '' ?>>
                                                        <span><?= campaignH($option['name'] ?? '') ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <select class="d-none" name="target_tags[]" id="target_tags" multiple>
                                            <?php foreach ($tagOptions as $option): ?>
                                                <?php $optionValue = (string) ($option['id'] ?? ''); ?>
                                                <option value="<?= campaignH($optionValue) ?>" <?= in_array($optionValue, $selectedTargetTags, true) ? 'selected' : '' ?>><?= campaignH($option['name'] ?? '') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label form_lbl" for="target_brands">Brand</label>
                                    <div class="campaign-rule-multiselect <?= $isView ? 'is-view' : '' ?>" data-select-id="target_brands" data-placeholder="All" data-refresh-target-rules="1">
                                        <div class="form-control campaign-rule-multiselect-control <?= $isView ? 'disabled' : '' ?>" tabindex="<?= $isView ? '-1' : '0' ?>">
                                            <div class="campaign-rule-multiselect-values"></div>
                                            <div class="campaign-rule-multiselect-arrow"><i class="fa-solid fa-chevron-down fa-xs"></i></div>
                                        </div>
                                        <?php if (!$isView): ?>
                                            <div class="campaign-rule-multiselect-menu">
                                                <?php foreach ($brandOptions as $option): ?>
                                                    <?php $optionValue = (string) ($option['id'] ?? ''); ?>
                                                    <label class="campaign-rule-multiselect-option">
                                                        <input type="checkbox" value="<?= campaignH($optionValue) ?>" <?= in_array($optionValue, $selectedTargetBrands, true) ? 'checked' : '' ?>>
                                                        <span><?= campaignH($option['name'] ?? '') ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <select class="d-none" name="target_brands[]" id="target_brands" multiple>
                                            <?php foreach ($brandOptions as $option): ?>
                                                <?php $optionValue = (string) ($option['id'] ?? ''); ?>
                                                <option value="<?= campaignH($optionValue) ?>" <?= in_array($optionValue, $selectedTargetBrands, true) ? 'selected' : '' ?>><?= campaignH($option['name'] ?? '') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label form_lbl" for="target_last_order">Last Order</label>
                                    <select class="form-select" name="target_last_order" id="target_last_order" <?= $disabledAttr ?>>
                                        <?php foreach ($lastOrderOptions as $optionValue => $option): ?>
                                            <option value="<?= campaignH($optionValue) ?>" <?= $selectedTargetLastOrder === $optionValue ? 'selected' : '' ?>><?= campaignH($option['label'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-2">
                                    <div class="campaign-rule-summary-box">
                                        <div class="campaign-rule-summary-title">Rule Summary</div>
                                        <div id="customerTargetRuleSummary" class="campaign-rule-summary-badges"></div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="campaign-rule-estimate-box">
                                        <div class="campaign-rule-estimate-icon"><i class="fa-regular fa-user"></i></div>
                                        <div>
                                            <div class="campaign-rule-estimate-title">Estimated Customers</div>
                                            <div id="estimatedCustomersValue" class="campaign-rule-estimate-value"><?= (int) $estimatedCustomers ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="default_pic_search">Default PIC</label>
                                <div class="autocomplete">
                                    <input class="form-control" type="text" id="default_pic_search" value="<?= campaignH($selectedDefaultPicName) ?>" <?= $readonlyAttr ?> autocomplete="off">
                                    <input type="hidden" name="default_pic_id" id="default_pic_id" value="<?= (int) $selectedDefaultPicId ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="default_message_shortcut_ids">Default Message Shortcut</label>
                                <div class="campaign-rule-multiselect <?= $isView ? 'is-view' : '' ?>" data-select-id="default_message_shortcut_ids" data-placeholder="Select Message Shortcut">
                                    <div class="form-control campaign-rule-multiselect-control <?= $isView ? 'disabled' : '' ?>" tabindex="<?= $isView ? '-1' : '0' ?>">
                                        <div class="campaign-rule-multiselect-values"></div>
                                        <div class="campaign-rule-multiselect-arrow"><i class="fa-solid fa-chevron-down fa-xs"></i></div>
                                    </div>
                                    <?php if (!$isView): ?>
                                        <div class="campaign-rule-multiselect-menu">
                                            <?php foreach ($messageOptions as $option): ?>
                                                <?php $optionId = (int) ($option['id'] ?? 0); ?>
                                                <label class="campaign-rule-multiselect-option">
                                                    <input type="checkbox" value="<?= $optionId ?>" <?= in_array($optionId, $selectedDefaultMessageIds, true) ? 'checked' : '' ?>>
                                                    <span class="campaign-rule-dropdown-label">
                                                        <span class="campaign-rule-dropdown-text"><?= campaignH($option['name'] ?? '') ?></span>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <select class="d-none" name="default_message_shortcut_ids[]" id="default_message_shortcut_ids" multiple>
                                        <?php foreach ($messageOptions as $option): ?>
                                            <?php $optionId = (int) ($option['id'] ?? 0); ?>
                                            <option value="<?= $optionId ?>" <?= in_array($optionId, $selectedDefaultMessageIds, true) ? 'selected' : '' ?>><?= campaignH($option['name'] ?? '') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" for="rule_status">Status*</label>
                                <select class="form-select" name="rule_status" id="rule_status" <?= $disabledAttr ?> required>
                                    <?php foreach ($statusOptions as $option): ?>
                                        <option value="<?= campaignH($option) ?>" <?= $formValues['rule_status'] === $option ? 'selected' : '' ?>><?= campaignH($option) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="remark">Remark</label>
                        <textarea class="form-control" name="remark" id="remark" rows="4" <?= $readonlyAttr ?>><?= campaignH($formValues['remark']) ?></textarea>
                    </div>



                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php if (!$isView): ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" name="actionBtn" id="actionBtn" value="saveRule"><?= $isAdd ? 'Add Rule' : 'Edit Rule' ?></button>
                        <?php endif; ?>
                        <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" name="actionBtn" id="backBtn" value="back" onclick="<?= campaignH(campaignBackButtonJs($redirectPage)) ?>">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= campaignH($pageTitle) ?>";
        const action = "<?= campaignH($act) ?>";
        var customerTargetRuleUrl = "<?= campaignH($SITEURL . '/campaign/campaign_rule_setting.php') ?>";
        var customerTargetRuleLabels = {
            platforms: <?= campaignJson($platformOptions) ?>,
            tags: <?= campaignJson(campaignRuleBuildOptionNameMap($tagOptions)) ?>,
            brands: <?= campaignJson(campaignRuleBuildOptionNameMap($brandOptions)) ?>,
            lastOrder: <?= campaignJson(array_map(function ($option) {
                return array(
                    'label' => isset($option['label']) ? $option['label'] : '',
                    'summary_label' => isset($option['summary_label']) ? $option['summary_label'] : '',
                );
            }, $lastOrderOptions)) ?>
        };

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        function toggleGenerateDayField() {
            var scheduleField = document.getElementById('generate_schedule');
            var generateDayField = document.getElementById('generate_day');
            if (!scheduleField || !generateDayField || action === "view") {
                return;
            }

            var canEditGenerateDay = scheduleField.value === 'Weekly' || scheduleField.value === 'Monthly Day';
            generateDayField.disabled = !canEditGenerateDay;
        }

        function toggleCustomPeriodDaysField() {
            var periodRuleField = document.getElementById('campaign_period_rule');
            var customDaysGroup = document.getElementById('customPeriodDaysGroup');
            var customDaysField = document.getElementById('custom_period_days');
            if (!periodRuleField || !customDaysGroup || !customDaysField) {
                return;
            }

            var isCustomDays = periodRuleField.value === 'Custom Days';
            customDaysGroup.style.display = isCustomDays ? '' : 'none';
            if (action !== "view") {
                customDaysField.disabled = !isCustomDays;
                if (!isCustomDays) {
                    customDaysField.value = '';
                }
            }
        }

        toggleGenerateDayField();
        toggleCustomPeriodDaysField();

        var generateScheduleField = document.getElementById('generate_schedule');
        if (generateScheduleField) {
            generateScheduleField.addEventListener('change', toggleGenerateDayField);
        }
        var campaignPeriodRuleField = document.getElementById('campaign_period_rule');
        if (campaignPeriodRuleField) {
            campaignPeriodRuleField.addEventListener('change', function () {
                toggleCustomPeriodDaysField();
                syncHiddenConditionJson();
            });
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getSelectedValues(selectElement) {
            if (!selectElement) {
                return [];
            }

            return Array.from(selectElement.options)
                .filter(function (option) { return option.selected; })
                .map(function (option) { return option.value; });
        }

        function renderMultiselectValues(wrapper) {
            if (!wrapper) {
                return;
            }

            var selectId = wrapper.getAttribute('data-select-id');
            var selectElement = document.getElementById(selectId);
            var valueWrap = wrapper.querySelector('.campaign-rule-multiselect-values');
            if (!selectElement || !valueWrap) {
                return;
            }

            var labels = getSelectedValues(selectElement).map(function (value) {
                var option = Array.from(selectElement.options).find(function (item) {
                    return item.value === value;
                });
                return option ? option.text : value;
            });
            var placeholderText = wrapper.getAttribute('data-placeholder') || 'Select';

            if (labels.length === 0) {
                valueWrap.innerHTML = '<span class="campaign-rule-multiselect-placeholder">' + escapeHtml(placeholderText) + '</span>';
                return;
            }

            valueWrap.innerHTML = labels.map(function (label) {
                return '<span class="campaign-rule-multiselect-tag">' + escapeHtml(label) + '</span>';
            }).join('');
        }

        function syncHiddenConditionJson() {
            var customerConditionField = document.getElementById('customer_condition_json');
            if (!customerConditionField) {
                return;
            }

            var payload = {};
            var selectedPlatforms = getSelectedValues(document.getElementById('target_platforms'));
            var selectedTags = getSelectedValues(document.getElementById('target_tags'));
            var selectedBrands = getSelectedValues(document.getElementById('target_brands'));
            var lastOrderValue = (document.getElementById('target_last_order') || {}).value || 'any';
            var customPeriodDaysField = document.getElementById('custom_period_days');
            var campaignPeriodRuleField = document.getElementById('campaign_period_rule');

            if (selectedPlatforms.length > 0) {
                payload.platforms = selectedPlatforms;
            }
            if (selectedTags.length > 0) {
                payload.tags = selectedTags;
            }
            if (selectedBrands.length > 0) {
                payload.brands = selectedBrands;
            }

            if (lastOrderValue.indexOf('more_than_') === 0) {
                payload.last_order = {
                    operator: 'more_than',
                    days: parseInt(lastOrderValue.replace('more_than_', ''), 10) || 0
                };
            } else if (lastOrderValue.indexOf('less_than_') === 0) {
                payload.last_order = {
                    operator: 'less_than',
                    days: parseInt(lastOrderValue.replace('less_than_', ''), 10) || 0
                };
            } else if (lastOrderValue === 'no_order_record') {
                payload.last_order = {
                    operator: 'no_order_record'
                };
            }

            if (
                campaignPeriodRuleField
                && campaignPeriodRuleField.value === 'Custom Days'
                && customPeriodDaysField
                && parseInt(customPeriodDaysField.value, 10) > 0
            ) {
                payload.period_days = parseInt(customPeriodDaysField.value, 10);
            }

            customerConditionField.value = Object.keys(payload).length === 0 ? '{}' : JSON.stringify(payload);
        }

        function renderRuleSummary() {
            var summaryWrap = document.getElementById('customerTargetRuleSummary');
            if (!summaryWrap) {
                return;
            }

            var badges = [];
            var selectedPlatforms = getSelectedValues(document.getElementById('target_platforms')).map(function (value) {
                return customerTargetRuleLabels.platforms[value] || value;
            });
            var selectedTags = getSelectedValues(document.getElementById('target_tags')).map(function (value) {
                return customerTargetRuleLabels.tags[value] || value;
            });
            var selectedBrands = getSelectedValues(document.getElementById('target_brands')).map(function (value) {
                return customerTargetRuleLabels.brands[value] || value;
            });
            var lastOrderValue = (document.getElementById('target_last_order') || {}).value || 'any';
            var lastOrderInfo = customerTargetRuleLabels.lastOrder[lastOrderValue] || {};

            badges.push('<span class="campaign-rule-summary-badge platform">Platform: ' + escapeHtml(selectedPlatforms.length > 0 ? selectedPlatforms.join(', ') : 'All') + '</span>');
            badges.push('<span class="campaign-rule-summary-badge tag">Tag: ' + escapeHtml(selectedTags.length > 0 ? selectedTags.join(', ') : 'All') + '</span>');
            badges.push('<span class="campaign-rule-summary-badge brand">Brand: ' + escapeHtml(selectedBrands.length > 0 ? selectedBrands.join(', ') : 'All') + '</span>');
            badges.push('<span class="campaign-rule-summary-badge last-order">Last Order: ' + escapeHtml(lastOrderInfo.summary_label || 'All') + '</span>');

            summaryWrap.innerHTML = badges.join('');
        }

        var customerEstimateTimer = null;
        function updateEstimatedCustomers() {
            if (action === "view") {
                return;
            }

            var form = document.getElementById('form');
            var estimateNode = document.getElementById('estimatedCustomersValue');
            if (!form || !estimateNode) {
                return;
            }

            var formData = new FormData(form);
            formData.append('actionBtnHidden', 'estimateCustomerTargetRules');

            fetch(customerTargetRuleUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    estimateNode.textContent = data && data.success ? data.count : 0;
                })
                .catch(function () {
                    estimateNode.textContent = '0';
                });
        }

        function queueEstimatedCustomersUpdate() {
            if (customerEstimateTimer) {
                window.clearTimeout(customerEstimateTimer);
            }
            customerEstimateTimer = window.setTimeout(updateEstimatedCustomers, 250);
        }

        function refreshCustomerTargetRules() {
            document.querySelectorAll('.campaign-rule-multiselect').forEach(renderMultiselectValues);
            syncHiddenConditionJson();
            renderRuleSummary();
            queueEstimatedCustomersUpdate();
        }

        document.querySelectorAll('.campaign-rule-multiselect').forEach(function (wrapper) {
            var selectId = wrapper.getAttribute('data-select-id');
            var selectElement = document.getElementById(selectId);
            var control = wrapper.querySelector('.campaign-rule-multiselect-control');
            var menu = wrapper.querySelector('.campaign-rule-multiselect-menu');

            renderMultiselectValues(wrapper);

            if (!control || !menu || action === "view") {
                return;
            }

            control.addEventListener('click', function (event) {
                event.stopPropagation();
                document.querySelectorAll('.campaign-rule-multiselect.open').forEach(function (openWrapper) {
                    if (openWrapper !== wrapper) {
                        openWrapper.classList.remove('open');
                    }
                });
                wrapper.classList.toggle('open');
            });

            menu.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            menu.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    Array.from(selectElement.options).forEach(function (option) {
                        if (option.value === checkbox.value) {
                            option.selected = checkbox.checked;
                        }
                    });
                    renderMultiselectValues(wrapper);
                    if (wrapper.getAttribute('data-refresh-target-rules') === '1') {
                        refreshCustomerTargetRules();
                    }
                });
            });
        });

        document.addEventListener('click', function () {
            document.querySelectorAll('.campaign-rule-multiselect.open').forEach(function (openWrapper) {
                openWrapper.classList.remove('open');
            });
        });

        var lastOrderField = document.getElementById('target_last_order');
        if (lastOrderField) {
            lastOrderField.addEventListener('change', refreshCustomerTargetRules);
        }
        var customPeriodDaysField = document.getElementById('custom_period_days');
        if (customPeriodDaysField) {
            customPeriodDaysField.addEventListener('input', function () {
                syncHiddenConditionJson();
            });
        }

        syncHiddenConditionJson();
        renderRuleSummary();
        if (action !== "view") {
            updateEstimatedCustomers();
        }
    </script>
    <?php
    campaignRenderAutocompleteScript(array(
        array(
            'inputId' => 'default_pic_search',
            'hiddenId' => 'default_pic_id',
            'options' => $userAutocompleteOptions,
        ),
    ));
    if (!empty($errors)) {
        echo '<script>confirmationDialog("", ' . campaignJson(implode("\\n", $errors)) . ', ' . campaignJson($pageTitle) . ', "", ' . campaignJson($redirectPage) . ', "ErrMO");</script>';
    }
    ?>
</body>

</html>
