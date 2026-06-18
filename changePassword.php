<?php
$pageTitle = "Change Password";
$currentPagePin = 25;

include_once 'include/connection.php';
include_once 'include/common.php';
include_once 'include/common_variable.php';

// checking
if (input('token') && input('email'))
    $pageMode = 'emailRstPassword';
else
    $pageMode = 'userChgPassword';

if ($pageMode == 'emailRstPassword') {
    $img_path = $SITEURL . img_server . 'themes/';
    $rstProj = getData('*', "id = '1'", '', PROJ, $connect);

    if ($rstProj != false) {
        $dataExisted = 1;
        $row = $rstProj->fetch_assoc();
    } else {
        renderNotificationScript('Sorry, currently network temporary fail, please try again later.', 'error');
        echo "<script>location.href ='$SITEURL/index.php';</script>";
    }
}

// Load full authenticated layout only for in-session password change.
if ($pageMode == 'userChgPassword') {
    $pageTitle = getPinGroupNameById($connect, $currentPagePin);
    include 'menuHeader.php';
}

$sendEmail = '';
$redirect_page = '';

if (!function_exists('cpBase64UrlDecode')) {
    function cpBase64UrlDecode($data)
    {
        $b64 = strtr((string) $data, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($b64, true);
    }
}

if (!function_exists('cpIsValidResetToken')) {
    function cpIsValidResetToken($token, $email, $passwordHash)
    {
        $decoded = cpBase64UrlDecode($token);
        if ($decoded === false) {
            return false;
        }

        $parts = explode('|', (string) $decoded);
        if (count($parts) !== 3) {
            return false;
        }

        $tokenEmail = (string) $parts[0];
        $expiresTs = (int) $parts[1];
        $tokenSig = (string) $parts[2];

        if ($tokenEmail === '' || $expiresTs <= 0 || $tokenSig === '') {
            return false;
        }

        if (strcasecmp($tokenEmail, (string) $email) !== 0) {
            return false;
        }

        if (time() > $expiresTs) {
            return false;
        }

        $secret = hash('sha256', SITEURL . '|' . dbpwd . '|forgot-password');
        $expectedSig = hash_hmac('sha256', $tokenEmail . '|' . $expiresTs . '|' . (string) $passwordHash, $secret);

        return hash_equals($expectedSig, $tokenSig);
    }
}

if ($pageMode == 'userChgPassword') {
    // menuheader

    $redirect_page = $SITEURL . '/dashboard.php';

    if (post('actionBtn') == 'updpass') {
        $id = $_SESSION['userid'];
        $old_password = postSpaceFilter('chgoldpass');
        $new_password = postSpaceFilter('chgnewpass');
        $confirm_password = postSpaceFilter('chgconfirmpass');

        if ($id && $old_password && $new_password && $confirm_password) {
            if ($new_password == $confirm_password) {
                $rst = getData('*', "id = '$id'", '', USR_USER, $connect);
                if (!$rst) {
                    renderNotificationScript('Sorry, currently network temporary fail, please try again later.', 'error');
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $row = $rst->fetch_assoc();

                if (mysqli_num_rows($rst) == 1) {
                    if ($row['password_alt'] == md5($old_password)) {
                        try {
                            $_SESSION['tempValConfirmBox'] = true;
                            $query = "UPDATE " . USR_USER . " SET password_alt = '" . md5($new_password) . "' WHERE id = '" . $id . "'";
                            mysqli_query($connect, $query);
                        } catch (Exception $e) {
                            $commonErr = $e->getMessage();
                        }
                    } else $oldpassErr = 'Wrong old password entered, please try again.';
                } else $commonErr = 'No email existed in the system.';
            } else $newpassErr = $confirmpassErr = 'Password Not Match.';
        } else $commonErr = 'Field cannot be blank.';
    }
} else if ($pageMode == 'emailRstPassword') {
    $redirect_page = $SITEURL . '/index.php';
    $email = input('email');
    $token = input('token');
    $tokenValid = false;

    if ($email && $token) {
        $safeEmail = mysqli_real_escape_string($connect, $email);
        $rstToken = getData('*', "email = '" . $safeEmail . "'", '', USR_USER, $connect);
        if ($rstToken && mysqli_num_rows($rstToken) == 1) {
            $rowToken = $rstToken->fetch_assoc();
            $pwdHash = isset($rowToken['password_alt']) ? (string) $rowToken['password_alt'] : '';
            $tokenValid = cpIsValidResetToken($token, $email, $pwdHash);
        }
    }

    if (!$tokenValid) {
        $commonErr = 'Reset link is invalid or expired. Please request a new one.';
    }

    if (post('actionBtn') == 'rstpass') {
        $new_password = post('rstnewpass');
        $confirm_password = post('rstconfirmpass');

        if ($tokenValid && $email && $token && $new_password && $confirm_password) {
            if ($new_password == $confirm_password) {
                $safeEmail = mysqli_real_escape_string($connect, $email);
                $rst = getData('*', "email = '" . $safeEmail . "'", '', USR_USER, $connect);
                if (!$rst) {
                    renderNotificationScript('Sorry, currently network temporary fail, please try again later.', 'error');
                    echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
                }
                $row = $rst->fetch_assoc();

                if (mysqli_num_rows($rst) == 1) {
                    try {
                        $_SESSION['tempValConfirmBox'] = true;
                        $query = "UPDATE " . USR_USER . " SET password_alt = '" . md5($new_password) . "' WHERE email = '" . $safeEmail . "'";
                        mysqli_query($connect, $query);
                        $sendEmail = 'rstSendEmail';
                    } catch (Exception $e) {
                        $commonErr = $e->getMessage();
                    }
                } else $commonErr = 'No email existed in the system.';
            } else $newpassErr = $confirmpassErr = 'Password Not Match.';
        } else if (!$tokenValid) {
            $commonErr = 'Reset link is invalid or expired. Please request a new one.';
        } else {
            $commonErr = 'Field cannot be blank.';
        }
    }

    if ($sendEmail == 'rstSendEmail') {
        ob_start();
        $to = $email;
        $subject = 'Password has been reset';
        $message = 'Password has been successfully reset.';
        @mail($to, $subject, $message);
        ob_get_clean();
    }
} else {
    echo 'Error.';
}

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    if ($pageMode == 'emailRstPassword') {
        echo '<script>alert("Password updated successfully.");location.href="' . $redirect_page . '";</script>';
    } else {
        echo '<script>confirmationDialog("","","User Password","","' . $redirect_page . '","PC");</script>';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php
    if ($pageMode == 'emailRstPassword') {
        include "header.php";
    }
    ?>
    <link rel="stylesheet" href="./css/main.css">
    <?php if ($pageMode == 'emailRstPassword') { ?>
        <link rel="stylesheet" href="./css/login.css">
        <link rel="icon" type="image" href="<?php echo ($dataExisted ? $img_path . $row['meta_logo'] : 'img/byd_logo'); ?>">
    <?php } ?>
</head>

<body>
    <div<?php if ($pageMode == 'userChgPassword') echo ' class="task-page-wrap"'; ?>>
    <?php if ($pageMode == 'userChgPassword') { ?>
        <div class="d-flex flex-column my-3 ms-3">
            <div class="row">
                <p><a href="<?= $redirect_page ?>">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                    <?php
                    switch ($pageMode) {
                        case 'userChgPassword':
                            echo $pageTitle;
                            break;
                        case 'emailRstPassword':
                            echo 'Reset Password';
                            break;
                        default:
                            echo '';
                    }
                    ?></p>
            </div>
        </div>
    <?php } ?>

    <div id="passwordContainer" class="container d-flex justify-content-center mt-2">
        <?php if ($pageMode == 'emailRstPassword') { ?>
            <div class="col-12 col-md-5">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-center my-4" id="logo_element">
                            <a href="<?= $SITEURL ?>/index.php">
                                <img id="logo" style="min-height:100px; max-height : 150px; width : auto;"
                                    src="<?php echo ($dataExisted ? $img_path . $row['logo'] : 'img/byd_logo'); ?>">
                            </a>
                        </div>
                    </div>
                </div>

                <form id="resetPasswordForm" method="post" action="">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group mt-5 mb-3 d-flex flex-column align-items-center">
                                <h2>Reset Password</h2>
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-center">
                        <div class="col-10">
                            <div class="form-group mb-3">
                                <label class="form-label" id="newpass_lbl" for="rstnewpass">New password</label>
                                <div id="row-password-input">
                                    <div class="d-flex justify-content-end">
                                        <i class="fa fa-eye-slash icon" id="showRstnewpass" onclick="togglePassword('rstnewpass')"></i>
                                    </div>
                                    <input class="form-control" type="password" name="rstnewpass" id="rstnewpass">
                                </div>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($newpassErr)) echo $newpassErr;
                                                        else echo ''; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-center">
                        <div class="col-10">
                            <div class="form-group mb-3">
                                <label class="form-label" id="confirmpass_lbl" for="rstconfirmpass">Confirm password</label>
                                <div id="row-password-input">
                                    <div class="d-flex justify-content-end">
                                        <i class="fa fa-eye-slash icon" id="showRstconfirmpass" onclick="togglePassword('rstconfirmpass')"></i>
                                    </div>
                                    <input class="form-control" type="password" name="rstconfirmpass" id="rstconfirmpass">
                                </div>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php if (isset($confirmpassErr)) echo $confirmpassErr;
                                                        else echo ''; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-center">
                        <div class="col-10">
                            <div class="form-group mt-5 d-flex justify-content-center">
                                <button class="btn btn-lg btn-rounded btn-primary" name="actionBtn" id="actionBtn" value="rstpass">Update Password</button>
                            </div>
                            <div class="d-flex justify-content-center mt-4 mb-3">
                                <a id="forgot-password_link" href="<?= $SITEURL ?>/index.php">Back to Login</a>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($commonErr) && $commonErr !== '') { ?>
                        <div class="row d-flex justify-content-center">
                            <div class="col-12">
                                <div class="d-flex justify-content-center my-4">
                                    <div id="err_msg">
                                        <span><?php echo $commonErr; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </form>
            <?php } else if ($pageMode == 'userChgPassword') { ?>
                <div class="col-6 col-md-6 formWidthAdjust">
                    <form id="changePasswordForm" method="post" action="">
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mb-5">
                                    <h2><?php echo $pageTitle ?></h2>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mb-3">
                                    <label class="form-label" id="oldpass_lbl" for="chgoldpass">Old password</label>
                                    <input class="form-control" type="password" name="chgoldpass" id="chgoldpass">
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php if (isset($oldpassErr)) echo $oldpassErr;
                                                            else echo ''; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mb-3">
                                    <label class="form-label" id="newpass_lbl" for="chgnewpass">New password</label>
                                    <input class="form-control" type="password" name="chgnewpass" id="chgnewpass">
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php if (isset($newpassErr)) echo $newpassErr;
                                                            else echo ''; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mb-3">
                                    <label class="form-label" id="confirmpass_lbl" for="chgconfirmpass">Confirm password</label>
                                    <input class="form-control" type="password" name="chgconfirmpass" id="chgconfirmpass">
                                    <div id="err_msg">
                                        <span class="mt-n1"><?php if (isset($confirmpassErr)) echo $confirmpassErr;
                                                            else echo ''; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group mt-5 d-flex justify-content-center">
                                    <button class="btn btn-lg btn-rounded btn-primary" name="actionBtn" id="actionBtn" value="updpass">Update Password</button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-center mt-4">
                                    <div id="err_msg">
                                        <span><?php if (isset($commonErr)) echo $commonErr;
                                                else echo ''; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
            <?php } else {
                echo 'Error.';
            } ?>
                </div>
            </div>
    </div>
    </div>

    <script>

        if (typeof checkCurrentPage === 'function') {
            checkCurrentPage('invalid');
        }
        if (typeof centerAlignment === 'function') {
            centerAlignment("passwordContainer");
        }
    </script>
</body>

</html>
