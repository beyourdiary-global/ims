<?php
$currentPagePin = 165;
$pageTitle = "Customize Bot Message";

include '../menuHeader.php';
include '../checkCurrentPagePin.php';

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if (!empty($resolvedPageTitle)) {
    $pageTitle = $resolvedPageTitle;
}

$tblName = CUSTOMIZE_BOT_MSG;
$redirectPage = $SITEURL . '/settings/customize_bot_msg_table.php';
$contextConfigs = customizeBotMsgGetContexts();

if (!function_exists('customizeBotMsgPageContextLabel')) {
    function customizeBotMsgPageContextLabel($context, $contextConfigs)
    {
        return isset($contextConfigs[$context]['label']) ? (string) $contextConfigs[$context]['label'] : ucfirst(str_replace('_', ' ', (string) $context));
    }
}

if (!function_exists('customizeBotMsgPagePreviewText')) {
    function customizeBotMsgPagePreviewText($value, $limit = 180)
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim((string) $text);

        if ($text === '') {
            return 'Empty Value';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '...' : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }
}

if (!function_exists('customizeBotMsgPageSetDefaultTemplate')) {
    function customizeBotMsgPageSetDefaultTemplate($connect, $context, $templateId)
    {
        $context = customizeBotMsgNormalizeContext($context);
        $templateId = (int) $templateId;
        if (!($connect instanceof mysqli) || $context === '' || $templateId <= 0) {
            return false;
        }

        if (!mysqli_query($connect, "UPDATE `" . CUSTOMIZE_BOT_MSG . "` SET `is_default` = 'N' WHERE `message_context` = '" . mysqli_real_escape_string($connect, $context) . "' AND `status` = 'A'")) {
            return false;
        }

        return (bool) mysqli_query($connect, "UPDATE `" . CUSTOMIZE_BOT_MSG . "` SET `is_default` = 'Y', `status` = 'A' WHERE `id` = " . $templateId . " LIMIT 1");
    }
}

if (!function_exists('customizeBotMsgPageEnsureAnyDefault')) {
    function customizeBotMsgPageEnsureAnyDefault($connect, $context)
    {
        $context = customizeBotMsgNormalizeContext($context);
        if (!($connect instanceof mysqli) || $context === '') {
            return;
        }

        $safeContext = mysqli_real_escape_string($connect, $context);
        $result = mysqli_query(
            $connect,
            "SELECT `id`, `is_default`
             FROM `" . CUSTOMIZE_BOT_MSG . "`
             WHERE `message_context` = '" . $safeContext . "'
               AND `status` = 'A'
             ORDER BY `is_default` DESC, `id` ASC"
        );
        if (!$result || mysqli_num_rows($result) === 0) {
            return;
        }

        $preferredTemplateId = 0;
        $firstTemplateId = 0;
        while ($templateRow = mysqli_fetch_assoc($result)) {
            $rowId = isset($templateRow['id']) ? (int) $templateRow['id'] : 0;
            if ($firstTemplateId <= 0) {
                $firstTemplateId = $rowId;
            }
            if ($preferredTemplateId <= 0 && isset($templateRow['is_default']) && $templateRow['is_default'] === 'Y') {
                $preferredTemplateId = $rowId;
            }
        }

        if ($preferredTemplateId <= 0) {
            $preferredTemplateId = $firstTemplateId;
        }

        if ($preferredTemplateId > 0) {
            customizeBotMsgPageSetDefaultTemplate($connect, $context, $preferredTemplateId);
        }
    }
}

if (!function_exists('customizeBotMsgPageDefaultChecked')) {
    function customizeBotMsgPageDefaultChecked($row)
    {
        return customizeBotMsgIsDefaultRow(is_array($row) ? $row : array()) ? 'Y' : 'N';
    }
}

if (!function_exists('customizeBotMsgPageShowPopupMessage')) {
    function customizeBotMsgPageShowPopupMessage($message, $redirectPage = '')
    {
        echo '<script>confirmationDialog("", ' . json_encode((string) $message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', "", "", ' . json_encode((string) $redirectPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', "ErrMO");</script>';
        exit;
    }
}

$dataId = !empty(input('id')) ? (int) input('id') : (int) post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$effectivePageAction = $act === '' ? 'View' : getPageAction($act);
$pageActionTitle = ($act === '' ? 'View' : getPageAction($act)) . ' ' . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

customizeBotMsgRepairLegacyDefaultStatuses($connect);
customizeBotMsgRepairLegacyBracketEncoding($connect);

if (($dataId <= 0 && $act === '') || !isActionAllowed($effectivePageAction, $pinAccess)) {
    echo "<script>location.href = " . json_encode((string) $redirectPage) . ";</script>";
    exit;
}

$row = array();
if ($dataId > 0) {
    $result = getData('*', "id = '" . $dataId . "'", 'LIMIT 1', $tblName, $connect);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
    }
}

if ($dataId > 0 && empty($row) && $act !== 'I') {
    renderNotificationScript('Requested template was not found.', 'error', $redirectPage, 1200, true);
    exit;
}

if ($act === 'D') {
    $deleteContext = isset($row['message_context']) ? (string) $row['message_context'] : '';
    $deleteTemplateName = isset($row['template_name']) ? (string) $row['template_name'] : ('ID ' . $dataId);

    if (customizeBotMsgIsDefaultRow($row)) {
        renderNotificationScript(customizeBotMsgGetDeleteBlockedMessage($row), 'error', $redirectPage, 1500, true);
        exit;
    }

    mysqli_begin_transaction($connect);
    $deleteStmt = mysqli_prepare(
        $connect,
        "UPDATE `" . $tblName . "`
         SET `status` = 'D', `is_default` = 'N', `update_by` = ?, `update_date` = CURDATE(), `update_time` = CURTIME()
         WHERE `id` = ? LIMIT 1"
    );

    $actor = trim((string) USER_ID) !== '' ? trim((string) USER_ID) : 'SYSTEM';
    mysqli_stmt_bind_param($deleteStmt, 'si', $actor, $dataId);
    $deleteOk = mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($deleteOk) {
        customizeBotMsgPageEnsureAnyDefault($connect, $deleteContext);
        mysqli_commit($connect);
        audit_log(array(
            'log_act' => 'Delete',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'page' => $pageTitle,
            'connect' => $connect,
            'act_msg' => USER_NAME . ' soft deleted Customize Bot Message template <b>' . htmlspecialchars($deleteTemplateName, ENT_QUOTES, 'UTF-8') . '</b>.',
            'query_table' => $tblName,
        ));
        renderNotificationScript('Customize Bot Message deleted successfully.', 'success', $redirectPage, 1200, true);
        exit;
    }

    mysqli_rollback($connect);
    renderNotificationScript('Unable to delete Customize Bot Message.', 'error', $redirectPage, 1200, true);
    exit;
}

if ($dataId > 0 && $act === '' && USER_ID && empty($_SESSION['viewChk'])) {
    $_SESSION['viewChk'] = 1;
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'page' => $pageTitle,
        'connect' => $connect,
        'act_msg' => USER_NAME . ' viewed Customize Bot Message template <b>' . htmlspecialchars((string) ($row['template_name'] ?? ('ID ' . $dataId)), ENT_QUOTES, 'UTF-8') . '</b>.',
    ));
}

if (post('actionBtn')) {
    $actionBtn = post('actionBtn');

    if ($actionBtn === 'back') {
        echo "<script>location.href = " . json_encode((string) $redirectPage) . ";</script>";
        exit;
    }

    if ($actionBtn === 'addData' || $actionBtn === 'updData') {
        $postedTemplateName = trim((string) post('template_name'));
        $postedContext = customizeBotMsgNormalizeContext(post('message_context'));
        $postedRemark = trim((string) post('remark'));
        $postedDefaultFlag = post('default_flag') === 'Y' ? 'Y' : 'N';
        $postedComponentsJson = (string) post('components_json');
        $postedComponents = json_decode($postedComponentsJson, true);
        $postedComponents = customizeBotMsgHydrateComponents($postedComponents, $postedContext);
        $postedTemplateBody = customizeBotMsgBuildTemplateBodyFromComponents($postedComponents);
        $postedParseMode = customizeBotMsgGetContextParseMode($postedContext);
        $postedPreviewSample = customizeBotMsgRenderTemplate($postedTemplateBody, customizeBotMsgGetSampleData($postedContext), $postedParseMode);
        $postedPreviewAudit = customizeBotMsgPagePreviewText($postedPreviewSample);

        if ($postedTemplateName === '') {
            $templateNameErr = 'Template Name is required.';
        }
        if ($postedContext === '') {
            $messageContextErr = 'Please select a valid Message Context.';
        }
        if ($postedTemplateBody === '') {
            $templateBodyErr = 'Template Body cannot be empty.';
        }

        if (!isset($templateNameErr) && !isset($messageContextErr) && !isset($templateBodyErr)) {
            $actor = trim((string) USER_ID) !== '' ? trim((string) USER_ID) : 'SYSTEM';
            $oldContext = isset($row['message_context']) ? (string) $row['message_context'] : '';
            $oldPreviewAudit = customizeBotMsgPagePreviewText(isset($row['preview_sample']) ? $row['preview_sample'] : '');

            mysqli_begin_transaction($connect);

            if ($actionBtn === 'addData') {
                $activeStatus = 'A';
                $stmt = mysqli_prepare(
                    $connect,
                    "INSERT INTO `" . $tblName . "`
                        (`template_name`, `message_context`, `parse_mode`, `template_body`, `components_json`, `preview_sample`, `is_default`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssssss',
                    $postedTemplateName,
                    $postedContext,
                    $postedParseMode,
                    $postedTemplateBody,
                    $postedComponentsJson,
                    $postedPreviewSample,
                    $postedDefaultFlag,
                    $postedRemark,
                    $actor,
                    $activeStatus
                );
                $saveOk = mysqli_stmt_execute($stmt);
                $savedTemplateId = $saveOk ? (int) mysqli_insert_id($connect) : 0;
                mysqli_stmt_close($stmt);
                if ($saveOk) {
                    if ($postedDefaultFlag === 'Y') {
                        $saveOk = customizeBotMsgPageSetDefaultTemplate($connect, $postedContext, $savedTemplateId);
                    } else {
                        customizeBotMsgPageEnsureAnyDefault($connect, $postedContext);
                    }
                }
            } else {
                $activeStatus = 'A';
                $stmt = mysqli_prepare(
                    $connect,
                    "UPDATE `" . $tblName . "`
                     SET `template_name` = ?, `message_context` = ?, `parse_mode` = ?, `template_body` = ?, `components_json` = ?, `preview_sample` = ?, `is_default` = ?, `remark` = ?, `status` = ?, `update_by` = ?, `update_date` = CURDATE(), `update_time` = CURTIME()
                     WHERE `id` = ? LIMIT 1"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssssssi',
                    $postedTemplateName,
                    $postedContext,
                    $postedParseMode,
                    $postedTemplateBody,
                    $postedComponentsJson,
                    $postedPreviewSample,
                    $postedDefaultFlag,
                    $postedRemark,
                    $activeStatus,
                    $actor,
                    $dataId
                );
                $saveOk = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $savedTemplateId = $dataId;
                if ($saveOk) {
                    if ($postedDefaultFlag === 'Y') {
                        $saveOk = customizeBotMsgPageSetDefaultTemplate($connect, $postedContext, $savedTemplateId);
                    } else {
                        customizeBotMsgPageEnsureAnyDefault($connect, $postedContext);
                    }
                }
                if ($saveOk && $oldContext !== '') {
                    $oldWasDefault = customizeBotMsgPageDefaultChecked($row) === 'Y';
                    if ($oldContext !== $postedContext || $oldWasDefault) {
                        customizeBotMsgPageEnsureAnyDefault($connect, $oldContext);
                    }
                }
            }

            if (!empty($saveOk)) {
                mysqli_commit($connect);
                $logAction = $actionBtn === 'addData' ? 'Add' : 'Edit';
                $logData = array(
                    'log_act' => $logAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'page' => $pageTitle,
                    'connect' => $connect,
                    'query_table' => $tblName,
                );

                if ($actionBtn === 'addData') {
                    $logData['newval'] = implodeWithComma(array(
                        normalizeAuditLogValue($postedTemplateName),
                        normalizeAuditLogValue(customizeBotMsgPageContextLabel($postedContext, $contextConfigs)),
                        normalizeAuditLogValue($postedPreviewAudit),
                        normalizeAuditLogValue($postedRemark),
                    ));
                    $logData['act_msg'] = USER_NAME . ' added Customize Bot Message template <b>' . htmlspecialchars($postedTemplateName, ENT_QUOTES, 'UTF-8') . '</b>.';
                } else {
                    $logData['oldval'] = implodeWithComma(array(
                        normalizeAuditLogValue(isset($row['template_name']) ? $row['template_name'] : ''),
                        normalizeAuditLogValue(customizeBotMsgPageContextLabel($oldContext, $contextConfigs)),
                        normalizeAuditLogValue($oldPreviewAudit),
                        normalizeAuditLogValue(isset($row['remark']) ? $row['remark'] : ''),
                    ));
                    $logData['changes'] = implodeWithComma(array(
                        normalizeAuditLogValue($postedTemplateName),
                        normalizeAuditLogValue(customizeBotMsgPageContextLabel($postedContext, $contextConfigs)),
                        normalizeAuditLogValue($postedPreviewAudit),
                        normalizeAuditLogValue($postedRemark),
                    ));
                    $logData['act_msg'] = USER_NAME . ' updated Customize Bot Message template <b>' . htmlspecialchars($postedTemplateName, ENT_QUOTES, 'UTF-8') . '</b>.';
                }

                audit_log($logData);
                customizeBotMsgPageShowPopupMessage(
                    $actionBtn === 'addData' ? 'Customize Bot Message added successfully.' : 'Customize Bot Message updated successfully.',
                    $redirectPage
                );
            }

            mysqli_rollback($connect);
            $saveErr = 'Unable to save Customize Bot Message. Please try again.';
        }
    }
}

$selectedContext = '';
if (post('actionBtn') && post('actionBtn') !== 'back') {
    $selectedContext = customizeBotMsgNormalizeContext(post('message_context'));
}
if ($selectedContext === '') {
    $selectedContext = isset($row['message_context']) ? customizeBotMsgNormalizeContext($row['message_context']) : 'shopee';
}

$selectedComponents = array();
if (post('actionBtn') && post('actionBtn') !== 'back') {
    $selectedComponents = customizeBotMsgHydrateComponents(json_decode((string) post('components_json'), true), $selectedContext);
} elseif (!empty($row)) {
    $selectedComponents = customizeBotMsgHydrateComponents(json_decode((string) ($row['components_json'] ?? ''), true), $selectedContext);
}
if (empty($selectedComponents)) {
    $selectedComponents = customizeBotMsgGetDefaultComponents($selectedContext);
}

$selectedTemplateBody = post('actionBtn') && post('actionBtn') !== 'back'
    ? customizeBotMsgBuildTemplateBodyFromComponents($selectedComponents)
    : (trim((string) ($row['template_body'] ?? '')) !== '' ? (string) $row['template_body'] : customizeBotMsgBuildTemplateBodyFromComponents($selectedComponents));
$selectedParseMode = customizeBotMsgGetContextParseMode($selectedContext);
$selectedPreviewSample = customizeBotMsgRenderTemplate($selectedTemplateBody, customizeBotMsgGetSampleData($selectedContext), $selectedParseMode);

$formTemplateName = post('actionBtn') && post('actionBtn') !== 'back'
    ? (string) post('template_name')
    : (isset($row['template_name']) ? (string) $row['template_name'] : '');
$formRemark = post('actionBtn') && post('actionBtn') !== 'back'
    ? (string) post('remark')
    : (isset($row['remark']) ? (string) $row['remark'] : '');
$formDefaultFlag = post('actionBtn') && post('actionBtn') !== 'back'
    ? (post('default_flag') === 'Y' ? 'Y' : 'N')
    : customizeBotMsgPageDefaultChecked($row);

$contextBuilderConfig = array();
foreach ($contextConfigs as $contextKey => $contextConfig) {
    $defaultComponents = customizeBotMsgGetDefaultComponents($contextKey);
    $contextBuilderConfig[$contextKey] = array(
        'label' => customizeBotMsgPageContextLabel($contextKey, $contextConfigs),
        'parse_mode' => customizeBotMsgGetContextParseMode($contextKey),
        'default_components' => $defaultComponents,
        'sample_data' => customizeBotMsgGetSampleData($contextKey),
    );
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/customize_bot_msg.css">
    <style>
        .customize-bot-msg-btn {
            text-transform: none !important;
        }
    </style>
</head>

<body>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>"><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= htmlspecialchars((string) $pageActionTitle, ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div id="formContainer" class="container-fluid d-flex justify-content-center">
            <div class="col-12 col-xl-11 col-xxl-10 formWidthAdjust">
                <form id="customizeBotMsgForm" method="post" novalidate>
                    <input type="hidden" name="id" value="<?= (int) $dataId ?>">
                    <input type="hidden" name="act" value="<?= htmlspecialchars((string) $act, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="parse_mode" id="parse_mode" value="<?= htmlspecialchars((string) $selectedParseMode, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="template_body" id="template_body" value="<?= htmlspecialchars((string) $selectedTemplateBody, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="components_json" id="components_json" value="<?= htmlspecialchars((string) json_encode($selectedComponents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="preview_sample" id="preview_sample" value="<?= htmlspecialchars((string) $selectedPreviewSample, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group mb-4">
                        <h2><?= htmlspecialchars((string) $pageActionTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>

                    <?php if (isset($saveErr)) { ?>
                        <div class="alert alert-danger"><?= htmlspecialchars((string) $saveErr, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="template_name">Template Name*</label>
                            <input class="form-control" type="text" id="template_name" name="template_name"
                                value="<?= htmlspecialchars((string) $formTemplateName, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $act === '' ? 'readonly' : '' ?> autocomplete="off">
                            <div class="error-message" id="templateNameError"><span><?= isset($templateNameErr) ? htmlspecialchars((string) $templateNameErr, ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="message_context">Message Context*</label>
                            <select class="form-select" id="message_context" name="message_context" <?= $act === '' ? 'disabled' : '' ?>>
                                <?php foreach ($contextConfigs as $contextKey => $contextConfig) { ?>
                                    <option value="<?= htmlspecialchars((string) $contextKey, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedContext === $contextKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(customizeBotMsgPageContextLabel($contextKey, $contextConfigs), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="error-message" id="messageContextError"><span><?= isset($messageContextErr) ? htmlspecialchars((string) $messageContextErr, ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-md-4 pt-md-2">
                                <input class="form-check-input" type="checkbox" id="default_flag" name="default_flag" value="Y" <?= $formDefaultFlag === 'Y' ? 'checked' : '' ?> <?= $act === '' ? 'disabled' : '' ?>>
                                <label class="form-check-label form_lbl" for="default_flag">Default?</label>
                            </div>
                            <?php if ($act === '') { ?>
                                <input type="hidden" name="default_flag" value="<?= htmlspecialchars((string) $formDefaultFlag, ENT_QUOTES, 'UTF-8') ?>">
                            <?php } ?>
                        </div>
                    </div>

                    <div class="row g-3 align-items-stretch">
                        <div class="col-xl-7">
                            <div class="customize-bot-builder-card h-100">
                                <div class="customize-bot-builder-header">
                                    <div>
                                        <h5 class="mb-1">Template Body</h5>
                                        <p class="mb-0 text-muted">Drag to reorder, rename labels inline, remove or restore components, and add spacers.</p>
                                    </div>
                                    <?php if ($act !== '') { ?>
                                        <button type="button" class="btn btn-sm btn-rounded btn-dark customize-bot-msg-btn" id="resetTemplateBtn">Reset</button>
                                    <?php } ?>
                                </div>
                                <div id="templateBodyError" class="error-message mb-2"><span><?= isset($templateBodyErr) ? htmlspecialchars((string) $templateBodyErr, ENT_QUOTES, 'UTF-8') : '' ?></span></div>
                                <div id="builderRows" class="customize-bot-builder-rows"></div>
                            </div>
                        </div>
                        <div class="col-xl-2">
                            <div class="customize-bot-side-card h-100">
                                <h5 class="mb-1">Removed Components</h5>
                                <p class="text-muted mb-3">Restore any removed component back into the builder.</p>
                                <div id="removedComponents" class="customize-bot-removed-list"></div>
                            </div>
                        </div>
                        <div class="col-xl-3">
                            <div class="customize-bot-side-card h-100">
                                <h5 class="mb-3">Preview (Sample)</h5>
                                <div id="templatePreview" class="customize-bot-preview"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-1">
                        <div class="col-12 mb-3">
                            <label class="form-label form_lbl" for="remark">Remark</label>
                            <textarea class="form-control" id="remark" name="remark" rows="3" <?= $act === '' ? 'readonly' : '' ?>><?= htmlspecialchars((string) $formRemark, ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>

                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-4 d-flex justify-content-center flex-md-row flex-column">
                        <?php if ($act === 'I') { ?>
                            <button class="btn btn-rounded btn-primary mx-2 mb-2 customize-bot-msg-btn" name="actionBtn" value="addData">Add Record</button>
                        <?php } elseif ($act === 'E') { ?>
                            <button class="btn btn-rounded btn-primary mx-2 mb-2 customize-bot-msg-btn" name="actionBtn" value="updData">Update Record</button>
                        <?php } ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2 customize-bot-msg-btn" name="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.customizeBotMsgConfig = {
            editable: <?= $act === '' ? 'false' : 'true' ?>,
            contexts: <?= json_encode($contextBuilderConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
            initialContext: <?= json_encode($selectedContext, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
            initialComponents: <?= json_encode($selectedComponents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
            previewTargetId: 'templatePreview'
        };

        const page = <?= json_encode((string) $pageTitle) ?>;
        const action = <?= json_encode((string) $act) ?>;

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
    </script>
    <script src="<?= $SITEURL ?>/js/customize_bot_msg.js"></script>
</body>

</html>
