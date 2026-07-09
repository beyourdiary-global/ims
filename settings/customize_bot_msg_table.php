<?php
$pageTitle = "Customize Bot Message";
$currentPagePin = 165;

include_once '../include/list_page_header.php';

$tblName = CUSTOMIZE_BOT_MSG;
$redirectPage = $SITEURL . '/settings/customize_bot_msg.php';
$deleteRedirectPage = $SITEURL . '/settings/customize_bot_msg_table.php';
$contextConfigs = customizeBotMsgGetContexts();

customizeBotMsgRepairLegacyDefaultStatuses($connect);
customizeBotMsgRepairLegacyBracketEncoding($connect);

$result = mysqli_query(
    $connect,
    "SELECT * FROM `" . $tblName . "` WHERE `status` = 'A' ORDER BY `is_default` DESC, `id` DESC"
);

if (!function_exists('customizeBotMsgRenderDeleteAction')) {
    function customizeBotMsgRenderDeleteAction($pinAccess, $row, $templateName, $previewSample, $pageTitle, $redirectPage, $deleteRedirectPage)
    {
        if (customizeBotMsgIsDefaultRow(is_array($row) ? $row : array())) {
            if (!isActionAllowed("Delete", $pinAccess)) {
                return;
            }

            $blockedMessage = customizeBotMsgGetDeleteBlockedMessage(is_array($row) ? $row : array());
            $args = array(
                '',
                array($blockedMessage),
                (string) $pageTitle,
                '',
                (string) $deleteRedirectPage,
                'ErrMO',
            );
            $payloadJson = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($payloadJson === false) {
                $payloadJson = '["",["Unable to delete default bot message."],"","","","ErrMO"]';
            }

            $payloadB64 = base64_encode($payloadJson);
            $jsCall = "(function(){var a=JSON.parse(atob('" . $payloadB64 . "'));confirmationDialog(a[0],a[1],a[2],a[3],a[4],a[5]);})();";
            $safeOnclick = htmlspecialchars($jsCall, ENT_QUOTES, 'UTF-8');
            echo '<a class="btn btn-danger" onclick="' . $safeOnclick . '"><i class="fas fa-trash-alt"></i></a>';
            return;
        }

        renderDeleteButton($pinAccess, $row['id'], $templateName, $previewSample, $pageTitle, $redirectPage, $deleteRedirectPage);
    }
}

if (!function_exists('customizeBotMsgTablePreview')) {
    function customizeBotMsgTablePreview($value, $limit = 160)
    {
        $text = customizeBotMsgRepairLegacyBracketText((string) $value);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '...' : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }
}

if (!function_exists('customizeBotMsgTableFullPreview')) {
    function customizeBotMsgTableFullPreview($value)
    {
        $text = customizeBotMsgRepairLegacyBracketText((string) $value);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\r\n", "\n", (string) $text);
        $text = preg_replace('/\x{00A0}/u', ' ', (string) $text);
        return trim((string) $text);
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .customize-bot-msg-btn {
            text-transform: none !important;
        }

        .customize-bot-msg-preview-cell {
            max-width: 420px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .customize-bot-msg-view-more {
            padding: 0;
            margin-top: 0.35rem;
            font-size: 0.9rem;
            line-height: 1.2;
            text-transform: none !important;
            text-decoration: none;
        }

        .customize-bot-msg-preview-modal-body {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.98rem;
            line-height: 1.65;
            color: #1f2937;
        }
    </style>
</head>

<script src="<?= $SITEURL ?>/js/list_page_common.js"></script>

<body>
    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                                class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary customize-bot-msg-btn" id="addBtn"
                                        href="<?= $redirectPage . '?act=' . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Customize Bot Message</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$result || mysqli_num_rows($result) === 0) { ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php } else { ?>
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" width="100px">Action</th>
                                <th scope="col">Template Name</th>
                                <th scope="col">Message Context</th>
                                <th scope="col">Preview Sample</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                <?php
                                $previewSample = customizeBotMsgTablePreview(isset($row['preview_sample']) ? $row['preview_sample'] : '');
                                $contextKey = isset($row['message_context']) ? (string) $row['message_context'] : '';
                                $contextLabel = isset($contextConfigs[$contextKey]['label']) ? (string) $contextConfigs[$contextKey]['label'] : ucfirst(str_replace('_', ' ', $contextKey));
                                $templateName = isset($row['template_name']) ? (string) $row['template_name'] : '';
                                $remark = isset($row['remark']) ? (string) $row['remark'] : '';
                                $fullPreviewSample = customizeBotMsgTableFullPreview(isset($row['preview_sample']) ? $row['preview_sample'] : '');
                                ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td class="btn-container">
                                        <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                        <?php customizeBotMsgRenderDeleteAction($pinAccess, $row, $templateName, $previewSample, $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                    </td>
                                    <td><?= htmlspecialchars($templateName . (customizeBotMsgIsDefaultRow($row) ? ' (Default)' : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="customize-bot-msg-preview-cell">
                                        <?= $previewSample !== '' ? htmlspecialchars($previewSample, ENT_QUOTES, 'UTF-8') : '-' ?>
                                        <?php if ($fullPreviewSample !== '') { ?>
                                            <div>
                                                <button
                                                    type="button"
                                                    class="btn btn-link customize-bot-msg-view-more"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#customizeBotMsgPreviewModal"
                                                    data-template-name="<?= htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-context-label="<?= htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-preview="<?= htmlspecialchars($fullPreviewSample, ENT_QUOTES, 'UTF-8') ?>">
                                                    View More
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <td><?= $remark !== '' ? htmlspecialchars($remark, ENT_QUOTES, 'UTF-8') : '-' ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" width="100px">Action</th>
                                <th scope="col">Template Name</th>
                                <th scope="col">Message Context</th>
                                <th scope="col">Preview Sample</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="customizeBotMsgPreviewModal" tabindex="-1" aria-labelledby="customizeBotMsgPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="customizeBotMsgPreviewModalLabel">Preview Sample</h5>
                        <div class="text-muted small" id="customizeBotMsgPreviewModalMeta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="customizeBotMsgPreviewModalBody" class="customize-bot-msg-preview-modal-body">-</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?>";
        const action = "<?= isset($act) ? htmlspecialchars((string) $act, ENT_QUOTES, 'UTF-8') : '' ?>";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('table');
        setButtonColor();

        const customizeBotMsgPreviewModal = document.getElementById('customizeBotMsgPreviewModal');
        if (customizeBotMsgPreviewModal) {
            customizeBotMsgPreviewModal.addEventListener('show.bs.modal', function(event) {
                const trigger = event.relatedTarget;
                const modalTitle = document.getElementById('customizeBotMsgPreviewModalLabel');
                const modalMeta = document.getElementById('customizeBotMsgPreviewModalMeta');
                const modalBody = document.getElementById('customizeBotMsgPreviewModalBody');

                if (!trigger || !modalBody || !modalMeta || !modalTitle) {
                    return;
                }

                const templateName = trigger.getAttribute('data-template-name') || '';
                const contextLabel = trigger.getAttribute('data-context-label') || '';
                const previewText = trigger.getAttribute('data-preview') || '-';

                modalTitle.textContent = 'Preview Sample';
                modalMeta.textContent = templateName !== ''
                    ? templateName + (contextLabel !== '' ? ' • ' + contextLabel : '')
                    : contextLabel;
                modalBody.textContent = previewText !== '' ? previewText : '-';
            });
        }
    </script>
</body>

</html>
