<?php
$pageTitle = "Campaign Message Shortcut";
$currentPagePin = 153;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';



$campaignId = (int) input('campaign_id');
if ($campaignId <= 0) {
    $campaignId = (int) post('campaign_id');
}

$campaign = campaignFetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    echo '<script>location.href = "' . $SITEURL . '/campaign_table.php";</script>';
    exit();
}

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$canSave = isActionAllowed('Add', $pinAccess) || isActionAllowed('Edit', $pinAccess);
$canDelete = isActionAllowed('Delete', $pinAccess);
$csrfToken = campaignCsrfToken('message_shortcut');
$backUrl = $SITEURL . '/campaign_table.php';
$pageUrl = $SITEURL . '/campaign_message_shortcut.php?campaign_id=' . $campaignId;

function campaignMessageShortcutOptions($connect)
{
    $rows = array();
    if (!defined('MESSAGE_SHORTCUTS') || !campaignTableExists($connect, MESSAGE_SHORTCUTS)) {
        return $rows;
    }

    $nameColumn = campaignFirstColumn($connect, MESSAGE_SHORTCUTS, array('shortcuts_tag', 'name', 'title'));
    $messageColumn = campaignFirstColumn($connect, MESSAGE_SHORTCUTS, array('shortcuts_message', 'message', 'description'));
    if ($nameColumn === '') {
        return $rows;
    }

    $select = '`id`, `' . $nameColumn . '` AS shortcut_name' . ($messageColumn !== '' ? ', `' . $messageColumn . '` AS shortcut_message' : '');
    $result = getData($select, '', '', MESSAGE_SHORTCUTS, $connect);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $name = isset($row['shortcut_name']) ? trim((string) $row['shortcut_name']) : '';
            if ($id > 0 && $name !== '') {
                $rows[] = array(
                    'id' => $id,
                    'name' => $name,
                    'preview' => isset($row['shortcut_message']) ? trim(strip_tags((string) $row['shortcut_message'])) : '',
                );
            }
        }
    }

    return $rows;
}

function campaignMessageShortcutName($row, $shortcutOptions)
{
    $shortcutId = isset($row['message_shortcut_id']) ? (int) $row['message_shortcut_id'] : 0;
    if ($shortcutId > 0) {
        foreach ($shortcutOptions as $option) {
            if ((int) $option['id'] === $shortcutId) {
                return (string) $option['name'];
            }
        }
    }

    return isset($row['message_title']) ? trim((string) $row['message_title']) : '';
}

function campaignMessageResolveShortcut($shortcutOptions, $shortcutId, $fallbackTitle)
{
    $shortcutId = (int) $shortcutId;
    foreach ($shortcutOptions as $option) {
        if ((int) $option['id'] === $shortcutId) {
            return $option;
        }
    }

    return array(
        'id' => 0,
        'name' => trim((string) $fallbackTitle),
        'preview' => '',
    );
}

function campaignMessageNormalizeText($value, $maxBytes = 65535)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (!preg_match('//u', $value)) {
        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($normalized !== false) {
                $value = $normalized;
            }
        } else {
            $value = preg_replace('/[^\\x20-\\x7E\\r\\n\\t]/', '', $value);
        }
    }

    $maxBytes = (int) $maxBytes;
    if ($maxBytes > 0) {
        if (function_exists('mb_strcut')) {
            $value = mb_strcut($value, 0, $maxBytes, 'UTF-8');
        } else {
            $value = substr($value, 0, $maxBytes);
        }
    }

    return trim((string) $value);
}

function campaignMessageNextSequence($connect, $campaignId)
{
    $campaignId = (int) $campaignId;
    if ($campaignId <= 0) {
        return 1;
    }

    $nextSequence = 1;
    $result = $connect->query("SELECT IFNULL(MAX(`sequence_no`), 0) + 1 AS next_sequence FROM `" . CAMPAIGN_MESSAGE . "` WHERE `campaign_id` = " . $campaignId . " AND `status` = 'A'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nextSequence = isset($row['next_sequence']) ? (int) $row['next_sequence'] : 1;
    }

    return $nextSequence > 0 ? $nextSequence : 1;
}

function campaignMessageResequence($connect, $campaignId)
{
    $campaignId = (int) $campaignId;
    $messageIds = array();
    $result = $connect->query("SELECT `id` FROM `" . CAMPAIGN_MESSAGE . "` WHERE `campaign_id` = " . $campaignId . " AND `status` = 'A' ORDER BY `follow_up_date` ASC, `id` ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messageIds[] = (int) $row['id'];
        }
    }

    if (empty($messageIds)) {
        return 0;
    }

    $updated = 0;
    $sequence = 1;
    $stmt = $connect->prepare("UPDATE `" . CAMPAIGN_MESSAGE . "` SET `sequence_no` = ?, `update_by` = ?, `update_date` = CURDATE(), `update_time` = CURTIME() WHERE `id` = ? AND `campaign_id` = ?");
    if (!$stmt) {
        return 0;
    }

    $userId = (string) USER_ID;
    foreach ($messageIds as $messageId) {
        $stmt->bind_param('isii', $sequence, $userId, $messageId, $campaignId);
        if ($stmt->execute()) {
            $updated++;
        }
        $sequence++;
    }
    $stmt->close();

    return $updated;
}

$shortcutOptions = campaignMessageShortcutOptions($connect);

$campaignMessageSaveRequested = post('actionBtn') === 'saveMessage';
$campaignMessageDeleteRequested = false;
$campaignMessageDeleteUsesCommonDialog = false;
$campaignMessageDeleteId = 0;
$campaignMessageDeleteCsrfToken = '';

if (post('actionBtn') === 'deleteMessage') {
    $campaignMessageDeleteRequested = true;
    $campaignMessageDeleteId = (int) post('message_id');
    $campaignMessageDeleteCsrfToken = (string) post('csrf_token');
} else if (post('act') === 'D') {
    $campaignMessageDeleteUsesCommonDialog = true;
    $commonDeletePayload = trim((string) post('id'));
    $commonDeleteParts = explode('|', $commonDeletePayload, 2);

    $campaignMessageDeleteId = isset($commonDeleteParts[0]) ? (int) $commonDeleteParts[0] : 0;
    $campaignMessageDeleteCsrfToken = isset($commonDeleteParts[1]) ? trim((string) $commonDeleteParts[1]) : '';
    $campaignMessageDeleteRequested = $campaignMessageDeleteId > 0;
}

if (
    ($campaignMessageSaveRequested && (!campaignVerifyCsrf('message_shortcut', post('csrf_token')) || !$canSave))
    || ($campaignMessageDeleteRequested && (!campaignVerifyCsrf('message_shortcut', $campaignMessageDeleteCsrfToken) || !$canDelete))
) {
    if ($campaignMessageDeleteUsesCommonDialog) {
        http_response_code(403);
        echo 'Unable to delete Campaign Message Shortcut.';
        exit();
    }

    campaignSetPopup('Unable to update Campaign Message Shortcut.', $pageUrl, 'ErrMO');
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

if ($campaignMessageDeleteRequested) {
    $messageId = (int) $campaignMessageDeleteId;
    if ($messageId > 0) {
        $userId = $connect->real_escape_string((string) USER_ID);
        $query = "UPDATE `" . CAMPAIGN_MESSAGE . "` SET `status`='D', `update_by`='" . $userId . "', `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=" . $messageId . " AND `campaign_id`=" . (int) $campaignId;
        $connect->query($query);
        campaignMessageResequence($connect, $campaignId);
        $syncSummary = campaignSyncFollowUpTasks($connect, $campaignId);
        campaignAudit(
            $connect,
            $pageTitle,
            'delete',
            USER_NAME . " deleted campaign message shortcut " . $messageId . " and synced follow-up tasks (created " . (int) $syncSummary['created'] . ", updated " . (int) $syncSummary['updated'] . ", deactivated " . (int) $syncSummary['deactivated'] . ").",
            $query,
            CAMPAIGN_MESSAGE
        );
    }

    if ($campaignMessageDeleteUsesCommonDialog) {
        echo 'OK';
        exit();
    }

    campaignSetPopup('Successful Delete Campaign Message Shortcut', $pageUrl, 'ErrMO');
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

if (post('actionBtn') === 'saveMessage') {
    $messageId = (int) post('message_id');
    $shortcutId = (int) post('message_shortcut_id');
    $title = campaignMessageNormalizeText(post('message_title'), 255);
    $preview = campaignMessageNormalizeText(post('message_preview'), 65535);
    $followUpDate = trim((string) post('follow_up_date'));
    $remark = campaignMessageNormalizeText(post('remark'), 65535);


    $shortcutMeta = campaignMessageResolveShortcut($shortcutOptions, $shortcutId, $title);
    if ($title === '' && isset($shortcutMeta['name'])) {
        $title = trim((string) $shortcutMeta['name']);
    }
    if ($preview === '' && $shortcutId > 0 && isset($shortcutMeta['preview'])) {
        $preview = trim((string) $shortcutMeta['preview']);
    }

    if ($title === '') {
        campaignSetPopup('Message Shortcut is required.', $pageUrl, 'ErrMO');
        echo '<script>location.href = "' . $pageUrl . '";</script>';
        exit();
    }

    if ($followUpDate === '') {
        campaignSetPopup('Follow-Up Date is required.', $pageUrl, 'ErrMO');
        echo '<script>location.href = "' . $pageUrl . '";</script>';
        exit();
    }

    try {
        $userId = (string) USER_ID;
        if ($messageId > 0) {
            $stmt = $connect->prepare("UPDATE `" . CAMPAIGN_MESSAGE . "` SET `message_shortcut_id` = ?, `message_title` = ?, `message_preview` = ?, `follow_up_date` = ?, `remark` = ?, `update_by` = ?, `update_date` = CURDATE(), `update_time` = CURTIME() WHERE `id` = ? AND `campaign_id` = ?");
            if (!$stmt) {
                throw new Exception($connect->error);
            }
            $stmt->bind_param('isssssii', $shortcutId, $title, $preview, $followUpDate, $remark, $userId, $messageId, $campaignId);
            $auditAction = 'edit';
            $auditText = USER_NAME . " updated campaign message shortcut " . $messageId . ".";
        } else {
            $sequenceNo = campaignMessageNextSequence($connect, $campaignId);
            $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN_MESSAGE . "` (`campaign_id`, `sequence_no`, `message_shortcut_id`, `message_title`, `message_preview`, `follow_up_date`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'A')");
            if (!$stmt) {
                throw new Exception($connect->error);
            }
            $stmt->bind_param('iiisssss', $campaignId, $sequenceNo, $shortcutId, $title, $preview, $followUpDate, $remark, $userId);
            $auditAction = 'add';
            $auditText = USER_NAME . " added a campaign message shortcut.";
        }

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $resequenceCount = campaignMessageResequence($connect, $campaignId);
        $syncSummary = campaignSyncFollowUpTasks($connect, $campaignId);
        campaignAudit(
            $connect,
            $pageTitle,
            $auditAction,
            $auditText . " Resequenced " . $resequenceCount . " row(s) and synced follow-up tasks (created " . (int) $syncSummary['created'] . ", updated " . (int) $syncSummary['updated'] . ", deactivated " . (int) $syncSummary['deactivated'] . ").",
            '',
            CAMPAIGN_MESSAGE
        );
        campaignSetPopup('Successful Save Campaign Message Shortcut', $pageUrl, 'ErrMO');
    } catch (Exception $e) {
        campaignSetPopup($e->getMessage(), $pageUrl, 'ErrMO');
    }

    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$messageRows = array();
$result = $connect->query("SELECT * FROM `" . CAMPAIGN_MESSAGE . "` WHERE `campaign_id` = " . (int) $campaignId . " AND `status` = 'A' ORDER BY `sequence_no` ASC, `follow_up_date` ASC, `id` ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $messageRows[] = $row;
    }
}


$autocompleteConfigs = array(
    array('inputId' => 'campaign_message_shortcut_text', 'hiddenId' => 'campaign_message_shortcut_id', 'options' => $shortcutOptions),
);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .campaign-message-page .action-col {
            width: 1%;
            min-width: 120px;
            text-align: left;
        }

        .campaign-message-page .btn-action-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .campaign-message-page .arrival-follow-up-summary {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 14px 16px;
            background: #f8fafc;
            margin-bottom: 16px;
        }

        .campaign-message-page .arrival-follow-up-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 6px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .campaign-message-page .arrival-follow-up-summary-row:last-child {
            border-bottom: 0;
        }

        .campaign-message-page .arrival-follow-up-summary-label {
            color: #6b7280;
            font-weight: 600;
        }

        .campaign-message-page .customer-follow-up-required-star {
            color: #dc3545;
        }

        .campaign-message-page .customer-follow-up-field-error,
        #campaignMessageModal .customer-follow-up-field-error {
            display: none;
            margin-top: 6px;
            color: #dc3545;
            font-size: 0.875rem;
        }

        .campaign-message-page .customer-follow-up-field-error.is-visible,
        #campaignMessageModal .customer-follow-up-field-error.is-visible {
            display: block;
        }

    </style>
</head>
<script>
    
    $(document).ready(function () {
        var campaignMessageTable = document.getElementById('campaign_message_table');
        if (campaignMessageTable) {
            var headerColumnCount = campaignMessageTable.querySelectorAll('thead th').length;
            var invalidBodyRow = Array.prototype.some.call(campaignMessageTable.querySelectorAll('tbody tr'), function (row) {
                return row.children.length !== headerColumnCount;
            });

            if (!invalidBodyRow) {
                createSortingTable('campaign_message_table', {
                    searching: false,
                    language: {
                        emptyTable: 'No Result!'
                    }
                });
            }
        }
    });
</script>
<body>
    
<div class="page-load-cover">
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3 campaign-message-page">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p>
                        <a href="<?= $SITEURL ?>/campaign_table.php">Campaign</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        Message Shortcut
                    </p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <div>
                            <h2>Campaign Message Shortcut</h2>
                            <?php campaignRenderBadge($campaign); ?>
                        </div>
                        <div class="mt-auto mb-auto">
                            <?php if ($canSave): ?>
                                <button class="btn btn-sm btn-rounded btn-primary" type="button" name="addBtn" id="campaignMessageAddBtn"><i class="fa-solid fa-plus"></i> Add Message</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <table class="table table-striped align-middle" id="campaign_message_table">
                <thead>
                <tr>
                    <th>Step / Sequence</th>
                    <th class="action-col">Action</th>
                    <th>Message Shortcut</th>
                    <th>Message Preview</th>
                    <th>Follow-Up Date</th>
                    <th>Remark</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($messageRows)): ?>
                    <?php foreach ($messageRows as $row): ?>
                        <?php
                        $rowId = isset($row['id']) ? (int) $row['id'] : 0;
                        $shortcutId = isset($row['message_shortcut_id']) ? (int) $row['message_shortcut_id'] : 0;
                        $shortcutName = campaignMessageShortcutName($row, $shortcutOptions);
                        ?>
                        <tr>
                            <td><?= (int) ($row['sequence_no'] ?? 0) ?></td>
                            <td class="action-col">
                                <div class="btn-action-row">
                                    <?php if ($canSave): ?>
                                        <button
                                            type="button"
                                            class="btn btn-warning me-1 campaign-message-edit-btn"
                                            title="Edit"
                                            data-message-id="<?= $rowId ?>"
                                            data-message-shortcut-id="<?= $shortcutId ?>"
                                            data-message-title="<?= campaignH($shortcutName) ?>"
                                            data-message-preview="<?= campaignH($row['message_preview'] ?? '') ?>"
                                            data-follow-up-date="<?= campaignH($row['follow_up_date'] ?? '') ?>"

                                            data-remark="<?= campaignH($row['remark'] ?? '') ?>"
                                            data-sequence-no="<?= (int) ($row['sequence_no'] ?? 0) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete && $rowId > 0): ?>
                                        <?php
                                        $deletePayload = (string) $rowId . '|' . (string) $csrfToken;
                                        $deleteMessagePreview = campaignMessageNormalizeText($row['message_preview'] ?? '', 160);
                                        $deleteOnClick = 'confirmationDialog('
                                            . campaignJson($deletePayload) . ', '
                                            . campaignJson(array($shortcutName, $deleteMessagePreview)) . ', '
                                            . campaignJson('Campaign Message Shortcut') . ', '
                                            . campaignJson($pageUrl) . ', '
                                            . campaignJson($pageUrl) . ', '
                                            . campaignJson('D')
                                            . ');';
                                        ?>
                                        <button
                                            class="btn btn-danger me-1"
                                            type="button"
                                            title="Delete"
                                            onclick="<?= campaignH($deleteOnClick) ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$canSave && !($canDelete && $rowId > 0)): ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?= campaignH($shortcutName) ?></td>
                            <td><?= nl2br(campaignH($row['message_preview'] ?? '')) ?></td>
                            <td><?= campaignH($row['follow_up_date'] ?? '') ?></td>
                            <td><?= nl2br(campaignH($row['remark'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php campaignRenderBackButton($backUrl, false); ?>
        </div>
    </div>
</div>

<div class="modal fade" id="campaignMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="campaignMessageModalForm" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignMessageModalTitle">Add Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
                    <input type="hidden" name="campaign_id" value="<?= (int) $campaignId ?>">
                    <input type="hidden" name="actionBtn" value="saveMessage">
                    <input type="hidden" name="message_id" id="campaign_message_id" value="0">

                    <div class="arrival-follow-up-summary">
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Campaign: </span>
                            <span id="campaign_message_modal_campaign_name"><?= campaignH($campaign['campaign_name'] ?? '') ?></span>
                        </div>
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Step / Sequence: </span>
                            <span id="campaign_message_modal_sequence_text">will auto arrange by follow-up date.</span>
                        </div>
                    </div>

                    <div class="mb-3 autocomplete">
                        <label class="form-label" for="campaign_message_shortcut_text">
                            Message Shortcut<span class="customer-follow-up-required-star">*</span>
                        </label>
                        <input class="form-control" type="text" id="campaign_message_shortcut_text" name="message_title" value="" autocomplete="off">
                        <input type="hidden" id="campaign_message_shortcut_id" name="message_shortcut_id" value="0">
                        <div class="customer-follow-up-field-error" id="campaign_message_shortcut_error">Message Shortcut is required.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="campaign_message_preview">Message Preview</label>
                        <textarea class="form-control" id="campaign_message_preview" name="message_preview" rows="4" readonly></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="campaign_message_follow_up_date">
                            Follow-Up Date<span class="customer-follow-up-required-star">*</span>
                        </label>
                        <input class="form-control" type="date" id="campaign_message_follow_up_date" name="follow_up_date" value="">
                        <div class="customer-follow-up-field-error" id="campaign_message_follow_up_date_error">Follow-Up Date is required.</div>
                    </div>



                    <div class="mb-3">
                        <label class="form-label" for="campaign_message_remark">Remark</label>
                        <textarea class="form-control" id="campaign_message_remark" name="remark" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="campaignMessageModalSubmitBtn" style="text-transform: none !important;">Save Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var campaignMessageShortcutOptions = <?= campaignJson($shortcutOptions) ?>;
    var campaignMessageFormSubmitted = false;

    function campaignMessageGetElement(id) {
        return document.getElementById(id);
    }

    function campaignMessageSetFieldError(errorId, isVisible) {
        var errorElement = campaignMessageGetElement(errorId);
        if (!errorElement) {
            return;
        }

        if (isVisible) {
            errorElement.classList.add('is-visible');
        } else {
            errorElement.classList.remove('is-visible');
        }
    }

    function campaignMessageSetInvalidField(fieldId, isInvalid) {
        var fieldElement = campaignMessageGetElement(fieldId);
        if (!fieldElement) {
            return;
        }

        if (isInvalid) {
            fieldElement.classList.add('is-invalid');
        } else {
            fieldElement.classList.remove('is-invalid');
        }
    }

    function clearCampaignMessageErrors() {
        campaignMessageSetFieldError('campaign_message_shortcut_error', false);
        campaignMessageSetFieldError('campaign_message_follow_up_date_error', false);
        campaignMessageSetInvalidField('campaign_message_shortcut_text', false);
        campaignMessageSetInvalidField('campaign_message_follow_up_date', false);
    }

    function normalizeCampaignMessageText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function findCampaignMessageShortcutById(shortcutId) {
        shortcutId = String(shortcutId || '');
        if (shortcutId === '' || shortcutId === '0') {
            return null;
        }

        return (campaignMessageShortcutOptions || []).find(function (option) {
            return String(option.id) === shortcutId;
        }) || null;
    }

    function findCampaignMessageShortcutByName(shortcutName) {
        var normalizedName = normalizeCampaignMessageText(shortcutName);
        if (normalizedName === '') {
            return null;
        }

        return (campaignMessageShortcutOptions || []).find(function (option) {
            return normalizeCampaignMessageText(option.name) === normalizedName;
        }) || null;
    }

    function syncCampaignMessageShortcutFields(forcePreviewUpdate) {
        var shortcutText = campaignMessageGetElement('campaign_message_shortcut_text');
        var shortcutIdField = campaignMessageGetElement('campaign_message_shortcut_id');
        var previewField = campaignMessageGetElement('campaign_message_preview');

        if (!shortcutText || !shortcutIdField || !previewField) {
            return;
        }

        var matched = findCampaignMessageShortcutById(shortcutIdField.value);
        if (!matched) {
            matched = findCampaignMessageShortcutByName(shortcutText.value);
            if (matched) {
                shortcutIdField.value = String(matched.id);
            }
        }

        if (matched && (forcePreviewUpdate || previewField.value.trim() === '')) {
            previewField.value = matched.preview || '';
        }
    }

    function validateCampaignMessageForm() {
        var shortcutText = campaignMessageGetElement('campaign_message_shortcut_text');
        var followUpDate = campaignMessageGetElement('campaign_message_follow_up_date');
        var shortcutMissing = !shortcutText || shortcutText.value.trim() === '';
        var followUpDateMissing = !followUpDate || followUpDate.value.trim() === '';

        campaignMessageSetFieldError('campaign_message_shortcut_error', shortcutMissing);
        campaignMessageSetFieldError('campaign_message_follow_up_date_error', followUpDateMissing);
        campaignMessageSetInvalidField('campaign_message_shortcut_text', shortcutMissing);
        campaignMessageSetInvalidField('campaign_message_follow_up_date', followUpDateMissing);

        return !shortcutMissing && !followUpDateMissing;
    }

    function resetCampaignMessageModal() {
        campaignMessageFormSubmitted = false;
        clearCampaignMessageErrors();
        campaignMessageGetElement('campaign_message_id').value = '0';
        campaignMessageGetElement('campaign_message_shortcut_text').value = '';
        campaignMessageGetElement('campaign_message_shortcut_id').value = '0';
        campaignMessageGetElement('campaign_message_preview').value = '';
        campaignMessageGetElement('campaign_message_follow_up_date').value = '';
        campaignMessageGetElement('campaign_message_remark').value = '';
        campaignMessageGetElement('campaign_message_modal_sequence_text').textContent = 'Will auto arrange by follow-up date.';
    }

    function openCampaignMessageModal(mode, data) {
        resetCampaignMessageModal();

        var modalTitle = campaignMessageGetElement('campaignMessageModalTitle');
        var submitBtn = campaignMessageGetElement('campaignMessageModalSubmitBtn');
        if (mode === 'edit') {
            modalTitle.textContent = 'Edit Message';
            submitBtn.textContent = 'Save Message';
            campaignMessageGetElement('campaign_message_id').value = data.messageId || '0';
            campaignMessageGetElement('campaign_message_shortcut_text').value = data.messageTitle || '';
            campaignMessageGetElement('campaign_message_shortcut_id').value = data.shortcutId || '0';
            campaignMessageGetElement('campaign_message_preview').value = data.messagePreview || '';
            campaignMessageGetElement('campaign_message_follow_up_date').value = data.followUpDate || '';
            campaignMessageGetElement('campaign_message_remark').value = data.remark || '';
            campaignMessageGetElement('campaign_message_modal_sequence_text').textContent = data.sequenceNo ? ('Current Step ' + data.sequenceNo) : 'Will auto arrange by follow-up date.';
        } else {
            modalTitle.textContent = 'Add Message';
            submitBtn.textContent = 'Add Message';
        }

        var modalElement = campaignMessageGetElement('campaignMessageModal');
        var modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        modalInstance.show();
    }

    campaignMessageGetElement('campaignMessageModalForm').addEventListener('submit', function (event) {
        campaignMessageFormSubmitted = true;
        syncCampaignMessageShortcutFields(true);
        if (!validateCampaignMessageForm()) {
            event.preventDefault();
        }
    });

    campaignMessageGetElement('campaign_message_shortcut_text').addEventListener('change', function () {
        syncCampaignMessageShortcutFields(true);
        if (campaignMessageFormSubmitted) {
            validateCampaignMessageForm();
        } else if (this.value.trim() !== '') {
            campaignMessageSetFieldError('campaign_message_shortcut_error', false);
            campaignMessageSetInvalidField('campaign_message_shortcut_text', false);
        }
    });

    campaignMessageGetElement('campaign_message_shortcut_text').addEventListener('blur', function () {
        setTimeout(function () {
            syncCampaignMessageShortcutFields(true);
        }, 160);
    });

    campaignMessageGetElement('campaign_message_shortcut_text').addEventListener('input', function () {
        var previewField = campaignMessageGetElement('campaign_message_preview');
        var shortcutIdField = campaignMessageGetElement('campaign_message_shortcut_id');
        if (shortcutIdField) {
            shortcutIdField.value = '0';
        }
        if (previewField) {
            previewField.value = '';
        }

        if (campaignMessageFormSubmitted) {
            validateCampaignMessageForm();
        } else if (this.value.trim() !== '') {
            campaignMessageSetFieldError('campaign_message_shortcut_error', false);
            campaignMessageSetInvalidField('campaign_message_shortcut_text', false);
        }
    });

    campaignMessageGetElement('campaign_message_shortcut_id').addEventListener('change', function () {
        syncCampaignMessageShortcutFields(true);
    });

    campaignMessageGetElement('campaign_message_follow_up_date').addEventListener('input', function () {
        if (campaignMessageFormSubmitted) {
            validateCampaignMessageForm();
        } else if (this.value.trim() !== '') {
            campaignMessageSetFieldError('campaign_message_follow_up_date_error', false);
            campaignMessageSetInvalidField('campaign_message_follow_up_date', false);
        }
    });

    var addButton = campaignMessageGetElement('campaignMessageAddBtn');
    if (addButton) {
        addButton.addEventListener('click', function () {
            openCampaignMessageModal('add', {});
        });
    }

    document.querySelectorAll('.campaign-message-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            openCampaignMessageModal('edit', {
                messageId: button.getAttribute('data-message-id') || '0',
                shortcutId: button.getAttribute('data-message-shortcut-id') || '0',
                messageTitle: button.getAttribute('data-message-title') || '',
                messagePreview: button.getAttribute('data-message-preview') || '',
                followUpDate: button.getAttribute('data-follow-up-date') || '',
                remark: button.getAttribute('data-remark') || '',
                sequenceNo: button.getAttribute('data-sequence-no') || ''
            });
        });
    });

    const page = "Campaign";
    const action = "";
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    datatableAlignment('campaign_message_table');
    setButtonColor();
</script>
<?php campaignRenderAutocompleteScript($autocompleteConfigs); ?>
<?php campaignRenderPopupScript($pageTitle, $pageUrl); ?>
</body>
</html>
