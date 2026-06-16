<?php
$currentPagePin = 133;
$pageTitle = "Token Setting";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = TOKEN_SETT;
$tokenSettingPageOptions = function_exists('shopeeOmsGetTokenSettingPageOptions')
    ? shopeeOmsGetTokenSettingPageOptions()
    : array(
        'Shopee Order Request' => 'Shopee Order Request',
        'Stock Order Request' => 'Stock Order Request',
        'Lazada Order Request' => 'Lazada Order Request',
        'Facebook Order Request' => 'Facebook Order Request',
        'Website Order Request' => 'Website Order Request',
    );

if (function_exists('isStatusFieldAvailable') && !isStatusFieldAvailable($tblName, $connect)) {
    @mysqli_query($connect, "ALTER TABLE `" . $tblName . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A'");
}

$tokenSettingPageUsedAvailable = false;
$pageUsedColumnRst = @mysqli_query($connect, "SHOW COLUMNS FROM `" . $tblName . "` LIKE 'page_used'");
if ($pageUsedColumnRst instanceof mysqli_result && $pageUsedColumnRst->num_rows > 0) {
    $tokenSettingPageUsedAvailable = true;
    $pageUsedColumnRow = $pageUsedColumnRst->fetch_assoc();
    $pageUsedColumnType = isset($pageUsedColumnRow['Type']) ? strtolower((string) $pageUsedColumnRow['Type']) : '';
    if ($pageUsedColumnType !== '' && strpos($pageUsedColumnType, 'varchar(') === 0 && preg_match('/varchar\((\d+)\)/', $pageUsedColumnType, $pageUsedLengthMatches)) {
        if ((int) $pageUsedLengthMatches[1] < 255) {
            @mysqli_query($connect, "ALTER TABLE `" . $tblName . "` MODIFY COLUMN `page_used` VARCHAR(255) NOT NULL DEFAULT ''");
        }
    }
} else {
    @mysqli_query($connect, "ALTER TABLE `" . $tblName . "` ADD COLUMN `page_used` VARCHAR(255) NOT NULL DEFAULT ''");
    $pageUsedColumnRst = @mysqli_query($connect, "SHOW COLUMNS FROM `" . $tblName . "` LIKE 'page_used'");
    $tokenSettingPageUsedAvailable = $pageUsedColumnRst instanceof mysqli_result && $pageUsedColumnRst->num_rows > 0;
}

$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

$redirect_page = $SITEURL . '/token_setting_table.php';
$redirectLink = "<script>location.href = '$redirect_page';</script>";
$clearLocalStorage = '<script>localStorage.clear();</script>';

$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

if ((!$dataID && !$act) || !isActionAllowed($pageAction, $pinAccess)) {
    echo $redirectLink;
}

$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);

if ((!$rst || !($row = $rst->fetch_assoc())) && $act !== 'I') {
    $errorExist = 1;
    $act = 'F';
}

if ($act === 'D') {
    $safeDeleteId = (int) $dataID;
    $deleteName = isset($row['name']) ? $row['name'] : '';
    $deleteQuery = "UPDATE " . $tblName . " SET status='D', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $safeDeleteId . "'";
    mysqli_query($connect, $deleteQuery);

    $deleteLog = [
        'log_act' => 'delete',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $deleteQuery,
        'query_table' => $tblName,
        'act_msg' => USER_NAME . " deleted the data [<b> ID = " . $safeDeleteId . "</b> ] <b>" . $deleteName . "</b> from <b><i>$tblName Table</i></b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ];
    audit_log($deleteLog);
    $_SESSION['delChk'] = 1;
}

if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':
            $currentDataName = postSpaceFilter('currentDataName');
            $botToken = postSpaceFilter('botToken');
            $chatId = postSpaceFilter('chatId');
            $remark = postSpaceFilter('remark');
            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
            $pageUsed = ($action === 'updData' && isset($row['page_used'])) ? (string) $row['page_used'] : '';

            if ($currentDataName === '') {
                $err = 'Name is required.';
                break;
            }

            if ($botToken === '') {
                $err = 'Bot Token is required.';
                break;
            }

            // Pass raw values here because isDuplicateRecord() escapes internally.
            if (isDuplicateRecord('name', $currentDataName, $tblName, $connect, $dataID)) {
                $err = 'Duplicate record found for Token Setting name.';
                break;
            }

            $safePageUsed = mysqli_real_escape_string($connect, $pageUsed);

            if ($action === 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    $newvalarr[] = $currentDataName;
                    $newvalarr[] = $botToken;
                    $datafield[] = 'name';
                    $datafield[] = 'bot_token';

                    $safeName = mysqli_real_escape_string($connect, $currentDataName);
                    $safeToken = mysqli_real_escape_string($connect, $botToken);
                    $safeChatId = mysqli_real_escape_string($connect, $chatId);
                    $safeRemark = mysqli_real_escape_string($connect, $remark);
                    $query = "INSERT INTO " . $tblName . "(name,page_used,bot_token,chat_id,remark,create_by,create_date,create_time,update_by,update_date,update_time,status) VALUES ('$safeName','$safePageUsed','$safeToken','$safeChatId','$safeRemark','" . USER_ID . "',CURDATE(),CURTIME(),'" . USER_ID . "',CURDATE(),CURTIME(),'A')";
                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = 'F';
                }
            } else {
                try {
                    if ($row['name'] != $currentDataName) {
                        $oldvalarr[] = $row['name'];
                        $chgvalarr[] = $currentDataName;
                        $datafield[] = 'name';
                    }

                    if ((string) (isset($row['bot_token']) ? $row['bot_token'] : '') !== (string) $botToken) {
                        $oldvalarr[] = (string) (isset($row['bot_token']) ? $row['bot_token'] : '');
                        $chgvalarr[] = (string) $botToken;
                        $datafield[] = 'bot_token';
                    }

                    if ((string) (isset($row['chat_id']) ? $row['chat_id'] : '') !== (string) $chatId) {
                        $oldvalarr[] = (string) (isset($row['chat_id']) ? $row['chat_id'] : '');
                        $chgvalarr[] = (string) $chatId;
                        $datafield[] = 'chat_id';
                    }

                    if ((string) (isset($row['remark']) ? $row['remark'] : '') !== (string) $remark) {
                        $oldvalarr[] = (string) (isset($row['remark']) ? $row['remark'] : '');
                        $chgvalarr[] = (string) $remark;
                        $datafield[] = 'remark';
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if (!empty($oldvalarr) && !empty($chgvalarr)) {
                        $safeName = mysqli_real_escape_string($connect, $currentDataName);
                        $safeToken = mysqli_real_escape_string($connect, $botToken);
                        $safeChatId = mysqli_real_escape_string($connect, $chatId);
                        $safeRemark = mysqli_real_escape_string($connect, $remark);
                        $query = "UPDATE " . $tblName . " SET name ='$safeName', page_used='$safePageUsed', bot_token='$safeToken', chat_id='$safeChatId', remark='$safeRemark', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id = '$dataID' AND status='A'";
                        $returnData = mysqli_query($connect, $query);
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = 'F';
                }
            }

            if (isset($query)) {
                $log = [
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
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
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .token-help-trigger {
            border: 0;
            background: transparent;
            padding: 0;
            margin-left: 8px;
            color: #2f67d8;
            line-height: 1;
        }

        .token-help-trigger:hover,
        .token-help-trigger:focus {
            color: #1f4eac;
        }

        .token-help-media {
            width: 100%;
            max-width: 520px;
            border: 1px solid #d7dce3;
            border-radius: 8px;
            padding: 6px;
            background: #fff;
            margin: 8px 0 12px;
        }

        .token-help-steps {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .token-help-steps li {
            margin-bottom: 8px;
        }

        .token-page-dropdown {
            position: relative;
        }

        .token-page-dropdown-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .token-page-dropdown-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 20;
            display: none;
            max-height: 260px;
            overflow-y: auto;
            padding: 8px 0;
            border: 1px solid #d7dce3;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
        }

        .token-page-dropdown-menu.is-open {
            display: block;
        }

        .token-page-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            margin: 0;
            cursor: pointer;
        }

        .token-page-dropdown-item:hover {
            background: #f5f8fd;
        }

        .token-page-dropdown-item input {
            margin: 0;
        }
    </style>
</head>

<body>
    

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageActionTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2><?= $pageActionTitle ?></h2>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataName">Name*</label>
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?= htmlspecialchars(isset($currentDataName) ? (string) $currentDataName : (isset($row['name']) ? (string) $row['name'] : ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($act == '') ? 'readonly' : '' ?> required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label d-flex align-items-center" for="botToken">
                            Bot Token*
                            <button type="button" class="token-help-trigger" data-help-target="botToken" title="How to get Bot Token" aria-label="How to get Bot Token">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input class="form-control" type="text" name="botToken" id="botToken" value="<?= htmlspecialchars(isset($botToken) ? (string) $botToken : (isset($row['bot_token']) ? (string) $row['bot_token'] : ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($act == '') ? 'readonly' : '' ?> required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label d-flex align-items-center" for="chatId">
                            Chat ID
                            <button type="button" class="token-help-trigger" data-help-target="chatId" title="How to get Chat ID" aria-label="How to get Chat ID">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input class="form-control" type="text" name="chatId" id="chatId" value="<?= htmlspecialchars(isset($chatId) ? (string) $chatId : (isset($row['chat_id']) ? (string) $row['chat_id'] : ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($act == '') ? 'readonly' : '' ?> autocomplete="off" placeholder="e.g. -1001234567890">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="remark">Remark</label>
                        <textarea class="form-control" name="remark" id="remark" rows="3" <?= ($act == '') ? 'readonly' : '' ?>><?= htmlspecialchars(isset($remark) ? (string) $remark : (isset($row['remark']) ? (string) $row['remark'] : ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div id="err_msg">
                        <span class="mt-n1" id="errorSpan"><?= isset($err) ? htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8') : '' ?></span>
                    </div>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?= ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="tokenHelpModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tokenHelpModalTitle">Telegram Setup Guide</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="tokenHelpBotTokenSection">
                            <h6>How to get Bot Token</h6>
                            <ol class="token-help-steps">
                                <li>
                                    Open <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>, then send <strong>/start</strong>.<br>
                                    <img class="token-help-media mt-2" src="<?= $SITEURL ?>/images_server/token_setting_guide/bot_token_step1.png" alt="BotFather start and command list">
                                </li>
                                <li>Send <strong>/newbot</strong> and enter your bot display name when BotFather asks.</li>
                                <li>
                                    Enter a username that ends with <strong>bot</strong> (for example: <code>testing_0406_bot</code>).<br>
                                    <img class="token-help-media mt-2" src="<?= $SITEURL ?>/images_server/token_setting_guide/bot_token_step2.png" alt="BotFather new bot token message">
                                </li>
                                <li>From the success message, copy the HTTP API token and paste it into the Bot Token field.</li>
                            </ol>
                        </div>

                        <div id="tokenHelpChatIdSection" class="mt-3">
                            <h6>How to get Chat ID</h6>
                            <ol class="token-help-steps">
                                <li>
                                    Open your created bot chat and send <strong>/start</strong> once.<br>
                                    <img class="token-help-media mt-2" src="<?= $SITEURL ?>/images_server/token_setting_guide/chat_id_step1.png" alt="Telegram bot chat with start command">
                                </li>
                                <li>Open this URL in browser (replace with your bot token):<br><code>TELEGRAM_API&lt;YOUR_BOT_TOKEN&gt;/getUpdates</code></li>
                                <li>
                                    In the JSON response, find <code>message</code> -&gt; <code>chat</code> -&gt; <code>id</code>.<br>
                                    <img class="token-help-media mt-2" src="<?= $SITEURL ?>/images_server/token_setting_guide/chat_id_step2.png" alt="Telegram getUpdates response with chat id">
                                </li>
                                <li>
                                    For a group chat, add your bot into the Telegram group first, then check the same <code>message</code> -&gt; <code>chat</code> -&gt; <code>id</code> value.
                                    Group chat IDs are usually negative numbers.<br>
                                    <img class="token-help-media mt-2" src="<?= $SITEURL ?>/images_server/token_setting_guide/chat_id_step3.png" alt="Telegram group chat id example from getUpdates response">
                                </li>
                                <li>Copy that numeric value (example: <code>1064420282</code> or group chat <code>-5185979975</code>) and paste it into Chat ID.</li>
                            </ol>
                            <p class="mb-0">Reference: <a href="https://core.telegram.org/bots/api#getupdates" target="_blank" rel="noopener noreferrer">Telegram Bot API - getUpdates</a></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var page = "<?= $pageTitle ?>";
        var action = "<?= isset($act) ? $act : '' ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        (function () {
            var dropdownWrap = document.getElementById('pageUsedDropdown');
            var dropdownToggle = document.getElementById('pageUsedDropdownToggle');
            var dropdownMenu = document.getElementById('pageUsedDropdownMenu');
            var dropdownLabel = document.getElementById('pageUsedDropdownLabel');
            var pageCheckboxes = dropdownMenu ? dropdownMenu.querySelectorAll('.token-page-checkbox') : [];

            function updatePageUsedLabel() {
                if (!dropdownLabel) {
                    return;
                }
                var labels = [];
                pageCheckboxes.forEach(function (checkbox) {
                    if (!checkbox.checked) {
                        return;
                    }
                    var optionLabel = checkbox.parentElement ? checkbox.parentElement.querySelector('span') : null;
                    labels.push(optionLabel ? optionLabel.textContent.trim() : checkbox.value);
                });
                dropdownLabel.textContent = labels.length ? labels.join(', ') : 'Select Page Used';
            }

            function closePageUsedDropdown() {
                if (dropdownMenu) {
                    dropdownMenu.classList.remove('is-open');
                }
                if (dropdownToggle) {
                    dropdownToggle.setAttribute('aria-expanded', 'false');
                }
            }

            if (dropdownWrap && dropdownToggle && dropdownMenu && !dropdownToggle.disabled) {
                dropdownToggle.addEventListener('click', function () {
                    var isOpen = dropdownMenu.classList.toggle('is-open');
                    dropdownToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });

                document.addEventListener('click', function (event) {
                    if (!dropdownWrap.contains(event.target)) {
                        closePageUsedDropdown();
                    }
                });

                pageCheckboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', updatePageUsedLabel);
                });
            }

            updatePageUsedLabel();

            var triggers = document.querySelectorAll('.token-help-trigger');
            var modalEl = document.getElementById('tokenHelpModal');
            if (!triggers.length || !modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }

            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            var titleEl = document.getElementById('tokenHelpModalTitle');
            var botSection = document.getElementById('tokenHelpBotTokenSection');
            var chatSection = document.getElementById('tokenHelpChatIdSection');

            function showSection(target) {
                if (!botSection || !chatSection || !titleEl) {
                    modal.show();
                    return;
                }

                if (target === 'botToken') {
                    titleEl.textContent = 'How to Get Bot Token';
                    botSection.style.display = '';
                    chatSection.style.display = 'none';
                } else if (target === 'chatId') {
                    titleEl.textContent = 'How to Get Chat ID';
                    botSection.style.display = 'none';
                    chatSection.style.display = '';
                } else {
                    titleEl.textContent = 'Telegram Setup Guide';
                    botSection.style.display = '';
                    chatSection.style.display = '';
                }

                modal.show();
            }

            triggers.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    showSection(btn.getAttribute('data-help-target') || '');
                });
            });
        })();
    </script>
</body>

</html>
