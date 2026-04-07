<?php
$currentPagePin = 133;
$pageTitle = "Token Setting";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = TOKEN_SETT;

if (function_exists('isStatusFieldAvailable') && !isStatusFieldAvailable($tblName, $connect)) {
    @mysqli_query($connect, "ALTER TABLE `" . $tblName . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A'");
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

            if ($currentDataName === '') {
                $err = 'Name is required.';
                break;
            }

            if ($botToken === '') {
                $err = 'Bot Token is required.';
                break;
            }

            // Escape the input before checking for duplicates to prevent SQL injection
            $safeCurrentDataName = mysqli_real_escape_string($connect, $currentDataName);
            if (isDuplicateRecord('name', $safeCurrentDataName, $tblName, $connect, $dataID)) {
                $err = 'Duplicate record found for Token Setting name.';
                break;
            }

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
                    $query = "INSERT INTO " . $tblName . "(name,bot_token,chat_id,remark,create_by,create_date,create_time,update_by,update_date,update_time,status) VALUES ('$safeName','$safeToken','$safeChatId','$safeRemark','" . USER_ID . "',CURDATE(),CURTIME(),'" . USER_ID . "',CURDATE(),CURTIME(),'A')";
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
                        $query = "UPDATE " . $tblName . " SET name ='$safeName', bot_token='$safeToken', chat_id='$safeChatId', remark='$safeRemark', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id = '$dataID' AND status='A'";
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
    </style>
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

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
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?= isset($row['name']) ? htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') : '' ?>" <?= ($act == '') ? 'readonly' : '' ?> required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label d-flex align-items-center" for="botToken">
                            Bot Token*
                            <button type="button" class="token-help-trigger" data-help-target="botToken" title="How to get Bot Token" aria-label="How to get Bot Token">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input class="form-control" type="text" name="botToken" id="botToken" value="<?= isset($row['bot_token']) ? htmlspecialchars((string) $row['bot_token'], ENT_QUOTES, 'UTF-8') : '' ?>" <?= ($act == '') ? 'readonly' : '' ?> required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label d-flex align-items-center" for="chatId">
                            Chat ID
                            <button type="button" class="token-help-trigger" data-help-target="chatId" title="How to get Chat ID" aria-label="How to get Chat ID">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                        </label>
                        <input class="form-control" type="text" name="chatId" id="chatId" value="<?= isset($row['chat_id']) ? htmlspecialchars((string) $row['chat_id'], ENT_QUOTES, 'UTF-8') : '' ?>" <?= ($act == '') ? 'readonly' : '' ?> autocomplete="off" placeholder="e.g. -1001234567890">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="remark">Remark</label>
                        <textarea class="form-control" name="remark" id="remark" rows="3" <?= ($act == '') ? 'readonly' : '' ?>><?= isset($row['remark']) ? htmlspecialchars((string) $row['remark'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                    </div>

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
                                <li>Open your created bot chat and send <strong>/start</strong> once.</li>
                                <img class="token-help-media" src="<?= $SITEURL ?>/images_server/token_setting_guide/chat_id_step1.png" alt="Telegram bot chat with start command">
                                <li>Open this URL in browser (replace with your bot token):<br><code>https://api.telegram.org/bot&lt;YOUR_BOT_TOKEN&gt;/getUpdates</code></li>
                                <li>In the JSON response, find <code>message</code> -&gt; <code>chat</code> -&gt; <code>id</code>.</li>
                                <img class="token-help-media mt-2" src="<?= $SITEURL ?>/images_server/token_setting_guide/chat_id_step2.png" alt="Telegram getUpdates response with chat id">
                                <li>Copy that numeric value (example: <code>1064420282</code>) and paste it into Chat ID.</li>
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