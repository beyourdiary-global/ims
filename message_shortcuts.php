<?php
$currentPagePin = 144;
$pageTitle = "Message Shortcuts";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if (!empty($resolvedPageTitle)) {
    $pageTitle = $resolvedPageTitle;
}

$tblName = MESSAGE_SHORTCUTS;

if (!function_exists('messageShortcutsSanitizeHtml')) {
    function messageShortcutsSanitizeHtml($html)
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        if (!function_exists('messageShortcutsSanitizeStyle')) {
            function messageShortcutsSanitizeStyle($styleValue)
            {
                $styleValue = trim((string) $styleValue);
                if ($styleValue === '') {
                    return '';
                }

                $allowedTextAlign = array('left', 'right', 'center', 'justify');
                $sanitizedRules = array();
                $styleParts = explode(';', $styleValue);

                foreach ($styleParts as $stylePart) {
                    $stylePart = trim((string) $stylePart);
                    if ($stylePart === '' || strpos($stylePart, ':') === false) {
                        continue;
                    }

                    list($propertyName, $propertyValue) = array_map('trim', explode(':', $stylePart, 2));
                    $propertyName = strtolower((string) $propertyName);
                    $propertyValue = trim((string) $propertyValue);

                    if ($propertyValue === '') {
                        continue;
                    }

                    if (in_array($propertyName, array('color', 'background-color'), true)) {
                        if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9,\.\s%]+\)|hsla?\([0-9,\.\s%]+\)|[a-zA-Z]+)$/', $propertyValue)) {
                            $sanitizedRules[] = $propertyName . ': ' . $propertyValue;
                        }
                    } elseif ($propertyName === 'text-align') {
                        $normalizedAlign = strtolower($propertyValue);
                        if (in_array($normalizedAlign, $allowedTextAlign, true)) {
                            $sanitizedRules[] = $propertyName . ': ' . $normalizedAlign;
                        }
                    }
                }

                return implode('; ', $sanitizedRules);
            }
        }

        if (!class_exists('DOMDocument')) {
            return strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><span><div><blockquote><code><pre><h1><h2><h3><h4>');
        }

        $wrapperId = 'message-shortcuts-wrapper';
        $prevState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = @$dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($prevState);
            return strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><span><div><blockquote><code><pre><h1><h2><h3><h4>');
        }

        $wrapper = null;
        $wrapperNodes = $dom->getElementsByTagName('div');
        foreach ($wrapperNodes as $wrapperNode) {
            if ($wrapperNode instanceof DOMElement && $wrapperNode->getAttribute('id') === $wrapperId) {
                $wrapper = $wrapperNode;
                break;
            }
        }

        if (!$wrapper) {
            libxml_clear_errors();
            libxml_use_internal_errors($prevState);
            return strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li><span><div><blockquote><code><pre><h1><h2><h3><h4>');
        }

        $allowedTags = array(
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li',
            'span', 'div', 'blockquote', 'code', 'pre', 'h1', 'h2', 'h3', 'h4'
        );
        $skipTags = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta');

        $renderNode = function ($node) use (&$renderNode, $allowedTags, $skipTags) {
            if ($node->nodeType === XML_TEXT_NODE) {
                return htmlspecialchars((string) $node->nodeValue, ENT_QUOTES, 'UTF-8');
            }

            if ($node->nodeType !== XML_ELEMENT_NODE) {
                return '';
            }

            $tagName = strtolower((string) $node->nodeName);
            if (in_array($tagName, $skipTags, true)) {
                return '';
            }

            $childHtml = '';
            foreach ($node->childNodes as $childNode) {
                $childHtml .= $renderNode($childNode);
            }

            if (!in_array($tagName, $allowedTags, true)) {
                return $childHtml;
            }

            if ($tagName === 'br') {
                return '<br>';
            }

            $attributes = '';
            if ($node->hasAttribute('style')) {
                $sanitizedStyle = messageShortcutsSanitizeStyle($node->getAttribute('style'));
                if ($sanitizedStyle !== '') {
                    $attributes .= ' style="' . htmlspecialchars($sanitizedStyle, ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            if ($tagName === 'a' && $node->hasAttribute('href')) {
                $href = trim((string) $node->getAttribute('href'));
                $lowerHref = strtolower($href);
                if (
                    $href !== '' &&
                    !preg_match('/^\s*(javascript|data|vbscript):/i', $lowerHref) &&
                    preg_match('/^(https?:|mailto:|tel:|\/|#)/i', $href)
                ) {
                    $attributes .= ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
                    if ($node->hasAttribute('target')) {
                        $target = trim((string) $node->getAttribute('target'));
                        if (in_array($target, array('_blank', '_self'), true)) {
                            $attributes .= ' target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"';
                            if ($target === '_blank') {
                                $attributes .= ' rel="noopener noreferrer"';
                            }
                        }
                    }
                }
            }

            return '<' . $tagName . $attributes . '>' . $childHtml . '</' . $tagName . '>';
        };

        $sanitizedHtml = '';
        foreach ($wrapper->childNodes as $childNode) {
            $sanitizedHtml .= $renderNode($childNode);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prevState);

        return trim((string) $sanitizedHtml);
    }
}

if (!function_exists('messageShortcutsPlainText')) {
    function messageShortcutsPlainText($html)
    {
        $plainText = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainText = preg_replace('/\x{00A0}/u', ' ', $plainText);
        $plainText = preg_replace('/\s+/u', ' ', (string) $plainText);
        return trim((string) $plainText);
    }
}

if (!function_exists('messageShortcutsAuditValue')) {
    function messageShortcutsAuditValue($html, $limit = 180)
    {
        $plainText = messageShortcutsPlainText($html);
        if ($plainText === '') {
            return 'Empty Value';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($plainText, 'UTF-8') > $limit ? mb_substr($plainText, 0, $limit, 'UTF-8') . '...' : $plainText;
        }

        return strlen($plainText) > $limit ? substr($plainText, 0, $limit) . '...' : $plainText;
    }
}

$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirect_page = $SITEURL . '/message_shortcuts_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if (!($dataID) && !($act) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
}

$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);

if ($act != 'I' && (!$rst || !($row = $rst->fetch_assoc()))) {
    $errorExist = 1;
    $act = "F";
}

if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, isset($row['shortcuts_tag']) ? $row['shortcuts_tag'] : '', $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8') . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
        $safeTag = htmlspecialchars(isset($row['shortcuts_tag']) ? (string) $row['shortcuts_tag'] : '', ENT_QUOTES, 'UTF-8');
        $viewActMsg = $safeUserName . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $safeTag . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate'   => $cdate,
        'ctime'   => $ctime,
        'uid'     => USER_ID,
        'cby'     => USER_ID,
        'act_msg' => $viewActMsg,
        'page'    => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':
            $shortcutsTag = postSpaceFilter('shortcuts_tag');
            $shortcutsMessageRaw = post('shortcuts_message');
            $shortcutsMessage = messageShortcutsSanitizeHtml($shortcutsMessageRaw);
            $shortcutsMessageAuditValue = messageShortcutsAuditValue($shortcutsMessage);

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if ($shortcutsTag === '') {
                $tagErr = "Message Shortcuts Tag is required.";
                $errorCount = 1;
            } else if (isDuplicateRecord("shortcuts_tag", $shortcutsTag, $tblName, $connect, $dataID)) {
                $tagErr = "Duplicate record found for Message Shortcuts Tag.";
                $errorCount = 1;
            }

            if (messageShortcutsPlainText($shortcutsMessage) === '') {
                $messageErr = "Message Shortcuts is required.";
                $errorCount = 1;
            }

            if (isset($errorCount)) {
                break;
            }

            $safeShortcutsTag = mysqli_real_escape_string($connect, (string) $shortcutsTag);
            $safeShortcutsMessage = mysqli_real_escape_string($connect, (string) $shortcutsMessage);

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($shortcutsTag !== '') {
                        $newvalarr[] = $shortcutsTag;
                        $datafield[] = 'shortcuts_tag';
                    }

                    if ($shortcutsMessageAuditValue !== 'Empty Value') {
                        $newvalarr[] = $shortcutsMessageAuditValue;
                        $datafield[] = 'shortcuts_message';
                    }

                    $query = "INSERT INTO `" . $tblName . "` (`shortcuts_tag`, `shortcuts_message`, `create_by`, `create_date`, `create_time`) VALUES ('" . $safeShortcutsTag . "', '" . $safeShortcutsMessage . "', '" . USER_ID . "', CURDATE(), CURTIME())";
                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;
                    $act = $returnData ? 'I' : 'F';
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    if ((string) $row['shortcuts_tag'] !== (string) $shortcutsTag) {
                        $oldvalarr[] = isset($row['shortcuts_tag']) && $row['shortcuts_tag'] !== '' ? $row['shortcuts_tag'] : 'Empty Value';
                        $chgvalarr[] = $shortcutsTag;
                        $datafield[] = 'shortcuts_tag';
                    }

                    if ((string) $row['shortcuts_message'] !== (string) $shortcutsMessage) {
                        $oldvalarr[] = messageShortcutsAuditValue(isset($row['shortcuts_message']) ? $row['shortcuts_message'] : '');
                        $chgvalarr[] = $shortcutsMessageAuditValue;
                        $datafield[] = 'shortcuts_message';
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE `" . $tblName . "` SET `shortcuts_tag` = '" . $safeShortcutsTag . "', `shortcuts_message` = '" . $safeShortcutsMessage . "', `update_date` = CURDATE(), `update_time` = CURTIME(), `update_by` = '" . USER_ID . "' WHERE `id` = '" . mysqli_real_escape_string($connect, (string) $dataID) . "'";
                        $returnData = mysqli_query($connect, $query);
                        $act = $returnData ? 'E' : 'F';
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            if (isset($query)) {
                $log = [
                    'log_act'      => $pageAction,
                    'cdate'        => $cdate,
                    'ctime'        => $ctime,
                    'uid'          => USER_ID,
                    'cby'          => USER_ID,
                    'query_rec'    => $query,
                    'query_table'  => $tblName,
                    'page'         => $pageTitle,
                    'connect'      => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }

                audit_log($log);
            }
            break;

        case 'back':
            echo $clearLocalStorage . ' ' . $redirectLink;
            break;
    }
}

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
}

$formTagValue = post('actionBtn') && post('actionBtn') !== 'back'
    ? (string) post('shortcuts_tag')
    : (isset($row['shortcuts_tag']) ? (string) $row['shortcuts_tag'] : '');
$formMessageValue = post('actionBtn') && post('actionBtn') !== 'back'
    ? (string) post('shortcuts_message')
    : (isset($row['shortcuts_message']) ? (string) $row['shortcuts_message'] : '');
$viewMessageHtml = isset($row['shortcuts_message']) ? messageShortcutsSanitizeHtml($row['shortcuts_message']) : '';
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<style>
    .message-shortcuts-preview {
        min-height: 260px;
        background-color: #fff;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    .message-shortcuts-preview p:last-child,
    .message-shortcuts-preview ul:last-child,
    .message-shortcuts-preview ol:last-child,
    .message-shortcuts-preview blockquote:last-child,
    .message-shortcuts-preview pre:last-child {
        margin-bottom: 0;
    }
</style>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php echo $pageActionTitle ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-11 col-xl-8 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2><?php echo $pageActionTitle ?></h2>
                        <p class="text-muted mb-0">Create reusable customer reply templates with rich text formatting.</p>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="shortcuts_tag">Message Shortcuts Tag*</label>
                        <input class="form-control" type="text" name="shortcuts_tag" id="shortcuts_tag"
                            value="<?= htmlspecialchars($formTagValue, ENT_QUOTES, 'UTF-8') ?>"
                            <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                        <div id="err_msg">
                            <span class="mt-n1"><?= isset($tagErr) ? $tagErr : '' ?></span>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="shortcuts_message">Message Shortcuts*</label>
                        <?php if ($act == '') { ?>
                            <div class="form-control message-shortcuts-preview">
                                <?= $viewMessageHtml !== '' ? $viewMessageHtml : '<span class="text-muted">No message content.</span>' ?>
                            </div>
                        <?php } else { ?>
                            <textarea class="form-control" name="shortcuts_message" id="shortcuts_message" rows="12"><?= htmlspecialchars($formMessageValue, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php } ?>
                        <div id="shortcutsMessageError" class="error-message">
                            <span class="mt-n1"><?= isset($messageErr) ? $messageErr : '' ?></span>
                        </div>
                    </div>

                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
    <script>
        window.messageShortcutsConfig = {
            editable: <?= $act === '' ? 'false' : 'true' ?>,
            siteUrl: <?= json_encode($SITEURL, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
        };

        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ''; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
    </script>
    <script src="<?= $SITEURL ?>/js/message_shortcuts.js"></script>
</body>

</html>
