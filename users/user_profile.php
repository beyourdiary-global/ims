<?php
$currentPagePin = 0;
$pageTitle = "User Profile";

include_once '../include/connection.php';
include_once '../include/common.php';
include_once '../include/common_variable.php';
include '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = USR_USER;
$redirectPage = $SITEURL . '/dashboard.php';

$pinAccess = checkCurrentPin($connect, $pageTitle);
if (!is_array($pinAccess) || count($pinAccess) === 0 || !isActionAllowed('View', $pinAccess)) {
    renderNotificationScript('No permission.', 'error', $redirectPage);
    exit;
}

$userId = (int) USER_ID;
if ($userId <= 0) {
    echo '<script>location.href = "' . $SITEURL . '/logout.php";</script>';
    exit;
}

function upfEscape($connect, $value)
{
    return mysqli_real_escape_string($connect, (string) $value);
}

function upfIsDuplicateField($connect, $table, $field, $value, $excludeId)
{
    $safeField = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $field);
    if ($safeField === '') return false;

    $safeValue = upfEscape($connect, $value);
    $safeId = (int) $excludeId;

    // Force same collation on both sides to avoid illegal mix of collations.
    $sql = "SELECT id FROM " . $table . " WHERE id <> '" . $safeId . "' AND CONVERT(`" . $safeField . "` USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT('" . $safeValue . "' USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 1";
    $result = mysqli_query($connect, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}

$profileResultAction = '';

$userRst = getData('*', "id='" . $userId . "'", 'LIMIT 1', $tblName, $connect);
if (!$userRst || $userRst->num_rows === 0) {
    renderNotificationScript('User not found.', 'info', $SITEURL . '/logout.php');
    exit;
}
$userRow = $userRst->fetch_assoc();

if (post('actionBtn') === 'checkDuplicateProfile') {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

    $name = postSpaceFilter('currentDataName');
    $username = postSpaceFilter('dataUsername');
    $email = postSpaceFilter('currentUserEmail');

    $errors = array(
        'currentDataName' => '',
        'dataUsername' => '',
        'currentUserEmail' => '',
    );

    if ($name !== '' && upfIsDuplicateField($connect, $tblName, 'name', $name, $userId)) {
        $errors['currentDataName'] = 'Duplicate record found for user name.';
    }
    if ($username !== '' && upfIsDuplicateField($connect, $tblName, 'username', $username, $userId)) {
        $errors['dataUsername'] = 'Duplicate record found for username.';
    }
    if ($email !== '' && upfIsDuplicateField($connect, $tblName, 'email', $email, $userId)) {
        $errors['currentUserEmail'] = 'Duplicate record found for user email.';
    }

    echo json_encode(array(
        'ok' => true,
        'errors' => $errors,
    ));
    exit;
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($action === 'back') {
        echo '<script>location.href = "' . $redirectPage . '";</script>';
        exit;
    }

    if ($action === 'saveProfile') {
        $canEditOwnProfile = ((int) USER_ID === (int) $userId);
        if (!isActionAllowed('Edit', $pinAccess) && !$canEditOwnProfile) {
            $profileResultAction = 'F';
        } else {
            $name = postSpaceFilter('currentDataName');
            $username = postSpaceFilter('dataUsername');
            $email = postSpaceFilter('currentUserEmail');

            if ($name === '') {
                $profileResultAction = 'F';
            } else if ($username === '') {
                $profileResultAction = 'F';
            } else if ($email === '') {
                $profileResultAction = 'F';
            } else if (
                upfIsDuplicateField($connect, $tblName, 'name', $name, $userId) ||
                upfIsDuplicateField($connect, $tblName, 'username', $username, $userId) ||
                upfIsDuplicateField($connect, $tblName, 'email', $email, $userId)
            ) {
                $profileResultAction = 'F';
            } else {
                $safeName = mysqli_real_escape_string($connect, $name);
                $safeUsername = mysqli_real_escape_string($connect, $username);
                $safeEmail = mysqli_real_escape_string($connect, $email);

                $oldName = isset($userRow['name']) ? trim((string) $userRow['name']) : '';
                $oldUsername = isset($userRow['username']) ? trim((string) $userRow['username']) : '';
                $oldEmail = isset($userRow['email']) ? trim((string) $userRow['email']) : '';

                if ($oldName === $name && $oldUsername === $username && $oldEmail === $email) {
                    $profileResultAction = 'NC';
                    $_SESSION['tempValConfirmBox'] = true;
                } else {
                    $datafield = array();
                    $oldvalarr = array();
                    $chgvalarr = array();

                    if ($oldName !== $name) {
                        $datafield[] = 'name';
                        $oldvalarr[] = $oldName;
                        $chgvalarr[] = $name;
                    }
                    if ($oldUsername !== $username) {
                        $datafield[] = 'username';
                        $oldvalarr[] = $oldUsername;
                        $chgvalarr[] = $username;
                    }
                    if ($oldEmail !== $email) {
                        $datafield[] = 'email';
                        $oldvalarr[] = $oldEmail;
                        $chgvalarr[] = $email;
                    }

                    $query = "UPDATE " . $tblName . " SET name='$safeName', username='$safeUsername', email='$safeEmail', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $userId . "'";
                    $ok = mysqli_query($connect, $query);

                    $auditErr = $ok ? '' : 'Update failed.';
                    $log = array(
                        'log_act' => 'edit',
                        'cdate' => $cdate,
                        'ctime' => $ctime,
                        'uid' => USER_ID,
                        'cby' => USER_ID,
                        'query_rec' => $query,
                        'query_table' => $tblName,
                        'oldval' => implodeWithComma($oldvalarr),
                        'changes' => implodeWithComma($chgvalarr),
                        'act_msg' => actMsgLog($userId, $datafield, array(), $oldvalarr, $chgvalarr, $tblName, 'Edit', $auditErr),
                        'page' => $pageTitle,
                        'connect' => $connect,
                    );
                    audit_log($log);

                    if ($ok) {
                        $_SESSION['username'] = $safeUsername;
                        $profileResultAction = 'E';
                        $_SESSION['tempValConfirmBox'] = true;
                    } else {
                        $profileResultAction = 'F';
                    }
                }
            }
        }
    }
}

$userRst = getData('*', "id='" . $userId . "'", 'LIMIT 1', $tblName, $connect);
if (!$userRst || $userRst->num_rows === 0) {
    renderNotificationScript('User not found.', 'info', $SITEURL . '/logout.php');
    exit;
}
$userRow = $userRst->fetch_assoc();

$mainSupName = '-';
$secondSupName = '-';

$mainSupId = isset($userRow['main_report_supervisor']) ? (int) $userRow['main_report_supervisor'] : 0;
$secondSupId = isset($userRow['second_report_supervisor']) ? (int) $userRow['second_report_supervisor'] : 0;

if ($mainSupId > 0) {
    $mainRst = getData('name', "id='" . $mainSupId . "'", 'LIMIT 1', $tblName, $connect);
    if ($mainRst && $mainRst->num_rows > 0) {
        $mainSupName = (string) $mainRst->fetch_assoc()['name'];
    }
}

if ($secondSupId > 0) {
    $secondRst = getData('name', "id='" . $secondSupId . "'", 'LIMIT 1', $tblName, $connect);
    if ($secondRst && $secondRst->num_rows > 0) {
        $secondSupName = (string) $secondRst->fetch_assoc()['name'];
    }
}

$showSupervisorInfo = !($mainSupName === '-' && $secondSupName === '-');

include '../menuHeader.php';

$_showProfileConfirmModal = false;
$_profileConfirmAction = '';
if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    $_showProfileConfirmModal = true;
    $_profileConfirmAction = (string) $profileResultAction;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageTitle ?></p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2><?= $pageTitle ?></h2>
                    </div>

                    <div class="row align-items-end mb-2">
                        <?php if ($showSupervisorInfo) { ?>
                        <div class="col-12 col-lg-4">
                            <div class="form-group mb-3">
                                <label class="form-label">Main Report Supervisor</label>
                                <label class="form-control"><?= htmlspecialchars($mainSupName, ENT_QUOTES, 'UTF-8') ?></label>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="form-group mb-3">
                                <label class="form-label">Second Report Supervisor</label>
                                <label class="form-control"><?= htmlspecialchars($secondSupName, ENT_QUOTES, 'UTF-8') ?></label>
                            </div>
                        </div>

                        <div class="col-12 col-lg-4">
                            <div class="form-group mb-3">
                                <a class="btn btn-sm btn-rounded btn-primary" id="actionBtn" href="<?= $SITEURL ?>/changePassword.php">Change Password</a>
                            </div>
                        </div>
                        <?php } else { ?>
                        <div class="col-12 col-lg-4 offset-lg-8">
                            <div class="form-group mb-3">
                                <a class="btn btn-sm btn-rounded btn-primary" id="actionBtn" href="<?= $SITEURL ?>/changePassword.php">Change Password</a>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label" for="currentDataName">Name*</label>
                                <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?= htmlspecialchars((string) $userRow['name'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                                <div id="err_msg"><span class="mt-n1" id="err_currentDataName"></span></div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label" for="dataUsername">Username*</label>
                                <input class="form-control" type="text" name="dataUsername" id="dataUsername" value="<?= htmlspecialchars((string) $userRow['username'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                                <div id="err_msg"><span class="mt-n1" id="err_dataUsername"></span></div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group mb-3">
                                <label class="form-label" for="currentUserEmail">Email*</label>
                                <input class="form-control" type="text" name="currentUserEmail" id="currentUserEmail" value="<?= htmlspecialchars((string) $userRow['email'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                                <div id="err_msg"><span class="mt-n1" id="err_currentUserEmail"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4 d-flex justify-content-center flex-md-row flex-column">
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="saveProfile">Save Profile</button>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="backBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= $pageTitle ?>";
        const action = "E";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        (function () {
            var form = document.getElementById('form');
            if (!form) return;

            var allowSubmit = false;
            var fields = ['currentDataName', 'dataUsername', 'currentUserEmail'];

            function setFieldError(fieldId, msg) {
                var box = document.getElementById('err_' + fieldId);
                var input = document.getElementById(fieldId);
                if (box) {
                    box.textContent = msg || '';
                    box.style.color = msg ? '#ff0000' : '';
                }
                if (input) {
                    if (msg) input.classList.add('is-invalid');
                    else input.classList.remove('is-invalid');
                }
            }

            function clearAllErrors() {
                fields.forEach(function (id) {
                    setFieldError(id, '');
                });
            }

            form.addEventListener('submit', function (e) {
                var active = document.activeElement;
                if (active && active.id === 'backBtn') return;

                if (allowSubmit) return;

                e.preventDefault();
                clearAllErrors();

                var nameVal = (document.getElementById('currentDataName').value || '').trim();
                var usernameVal = (document.getElementById('dataUsername').value || '').trim();
                var emailVal = (document.getElementById('currentUserEmail').value || '').trim();

                var hasError = false;
                if (nameVal === '') {
                    setFieldError('currentDataName', 'Name is required.');
                    hasError = true;
                }
                if (usernameVal === '') {
                    setFieldError('dataUsername', 'Username is required.');
                    hasError = true;
                }
                if (emailVal === '') {
                    setFieldError('currentUserEmail', 'Email is required.');
                    hasError = true;
                }

                if (hasError) return;

                var fd = new FormData();
                fd.append('actionBtn', 'checkDuplicateProfile');
                fd.append('currentDataName', nameVal);
                fd.append('dataUsername', usernameVal);
                fd.append('currentUserEmail', emailVal);

                fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                }).then(function (r) {
                    return r.text();
                }).then(function (text) {
                    var res = null;
                    try {
                        res = JSON.parse(text);
                    } catch (e) {
                        allowSubmit = true;

                        var hiddenActionFallback = document.createElement('input');
                        hiddenActionFallback.type = 'hidden';
                        hiddenActionFallback.name = 'actionBtn';
                        hiddenActionFallback.value = 'saveProfile';
                        form.appendChild(hiddenActionFallback);

                        form.submit();
                        return;
                    }

                    var dup = (res && res.errors) ? res.errors : {};
                    var dupHit = false;

                    if (dup.currentDataName) {
                        setFieldError('currentDataName', dup.currentDataName);
                        dupHit = true;
                    }
                    if (dup.dataUsername) {
                        setFieldError('dataUsername', dup.dataUsername);
                        dupHit = true;
                    }
                    if (dup.currentUserEmail) {
                        setFieldError('currentUserEmail', dup.currentUserEmail);
                        dupHit = true;
                    }

                    if (dupHit) return;

                    allowSubmit = true;
                    
                    var hiddenAction = document.createElement('input');
                    hiddenAction.type = 'hidden';
                    hiddenAction.name = 'actionBtn';
                    hiddenAction.value = 'saveProfile';
                    form.appendChild(hiddenAction);
                    
                    form.submit();
                }).catch(function () {
                    allowSubmit = true;

                    var hiddenActionCatch = document.createElement('input');
                    hiddenActionCatch.type = 'hidden';
                    hiddenActionCatch.name = 'actionBtn';
                    hiddenActionCatch.value = 'saveProfile';
                    form.appendChild(hiddenActionCatch);

                    form.submit();
                });
            });
        })();

        <?php if ($_showProfileConfirmModal) { ?>
        confirmationDialog("", "", <?= json_encode((string) $pageTitle) ?>, "", <?= json_encode((string) ($SITEURL . '/users/user_profile.php')) ?>, <?= json_encode((string) $_profileConfirmAction) ?>);
        <?php } ?>
    </script>
</body>
</html>
