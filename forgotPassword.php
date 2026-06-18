<?php
$pageTitle = "Forgot Password";
include "include/common.php";
include "include/common_variable.php";
include "include/connection.php";

$img_path = $SITEURL . img_server . 'themes/';
$result = getData('*', "id = '1'", '', PROJ, $connect);

if ($result != false) {
    $dataExisted = 1;
    $row = $result->fetch_assoc();
} else {
    renderNotificationScript('Sorry, currently network temporary fail, please try again later.', 'error');
    echo "<script>location.href ='$SITEURL/index.php';</script>";
}

$resetpass_btn = post('resetpass_btn');

if (!function_exists('fpBase64UrlEncode')) {
    function fpBase64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode((string) $data), '+/', '-_'), '=');
    }
}

if (!function_exists('fpBuildResetToken')) {
    function fpBuildResetToken($email, $passwordHash, $expiresTs)
    {
        $email = trim((string) $email);
        $expiresTs = (int) $expiresTs;
        $passwordHash = (string) $passwordHash;
        $secret = hash('sha256', SITEURL . '|' . dbpwd . '|forgot-password');
        $payload = $email . '|' . $expiresTs;
        $sig = hash_hmac('sha256', $payload . '|' . $passwordHash, $secret);
        return fpBase64UrlEncode($payload . '|' . $sig);
    }
}

if (!function_exists('fpSendMailFallback')) {
    function fpSendMailFallback($toEmail, $subject, $htmlContent, $fromEmail = '')
    {
        $toEmail = trim((string) $toEmail);
        $subject = (string) $subject;
        $htmlContent = (string) $htmlContent;

        $host = (string) parse_url(SITEURL, PHP_URL_HOST);
        $baseHost = preg_replace('/^www\./i', '', $host);
        $fallbackSender = 'noreply@' . ($baseHost !== '' ? $baseHost : 'beyourdiary.com');

        $fromEmail = trim((string) $fromEmail);
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $fallbackSender;
        }

        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
        $headers[] = 'From: BeYourDiary <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'Return-Path: ' . $fromEmail;
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'Message-ID: <' . md5(uniqid((string) mt_rand(), true)) . '@' . ($baseHost !== '' ? $baseHost : 'beyourdiary.com') . '>';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headerStr = implode("\r\n", $headers);

        $sent = @mail($toEmail, $subject, $htmlContent, $headerStr);
        if (!$sent) {
            $sent = @mail($toEmail, $subject, $htmlContent, $headerStr, '-f' . $fromEmail);
        }

        return $sent;
    }
}

if ($resetpass_btn == 1) {
    header('Content-Type: application/json; charset=utf-8');

    $email = str_replace(',', '.', trim((string) post('email-addr')));
    $response = array(
        'status' => 'error',
        'message' => 'Unable to process your request now. Please try again later.',
    );

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address.';
        echo json_encode($response);
        exit;
    }

    $safeEmail = mysqli_real_escape_string($connect, $email);
    $query = "SELECT id, name, email, password_alt FROM " . USR_USER . " WHERE email = '" . $safeEmail . "' LIMIT 1";
    $result = mysqli_query($connect, $query);

    if (!$result) {
        echo json_encode($response);
        exit;
    }

    

    if (mysqli_num_rows($result) == 1) {
        $rowUser = $result->fetch_assoc();
        $name = isset($rowUser['name']) ? (string) $rowUser['name'] : 'User';
        $to = isset($rowUser['email']) ? (string) $rowUser['email'] : $email;
        $pwdHash = isset($rowUser['password_alt']) ? (string) $rowUser['password_alt'] : '';

        $expiresTs = time() + 86400;
        $token = fpBuildResetToken($to, $pwdHash, $expiresTs);
        $resetUrl = $SITEURL . '/changePassword.php?token=' . urlencode($token) . '&email=' . urlencode($to);

        $subject = 'Request Reset Password';
        $message = '
            <html>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
                <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=3.0">
                <link rel="icon" type="image" href="' . ($dataExisted ? $img_path . $row['meta_logo'] : '.img/byd_logo.') . '">
                <head>
                    <title>' . $subject . '</title>
                </head>
                <body style="margin: 0;background-color: #FFF0E3;font-family: sans-serif;">
                    
                <div class="container" style="display: grid;gap: 5px;min-width: 350px;margin-left: 15px;margin-right: 15px;width: 550px;height: 55rem;margin: auto;">

                    <table class="header" style="border-spacing: 0;width: 100%;">
                        <tr>
                            <td class="logo" align="center" style="padding: 0;">
                            <img src="' . ($dataExisted ? $img_path . $row['meta_logo'] : '.img/byd_logo.') . '" style="border: 0;">;
                            </td>
                        </tr>
                    </table> <!-- End Header -->

                    <table class="middle" style="border-spacing: 0;width: 100%;height: 600px;background-color: #FFFFFF;border-radius: 18px;">
                        <tr class="content_row">
                            <td class="content" align="center" style="padding: 0;">
                                <p class="h1" style="font-size: 22;font-weight: bold;">Forgot Your Password?</p>
                                <p class="h2" style="font-size: 12;">Hi, <b>' . $name . '</b>,</p>
                                <p class="center" style="font-size: 10;width: 60%;line-height: 1.4;margin-top: -8px;text-align: left !important;">
                                    You recently requested to reset your password for your [' . $to . '] account. Use the button below to reset it. <b>This password reset is only valid for the next 24 hours</b>.
                                </p>
                                <p class="btn_row" style="margin-top: 35px;">
                                    <a class="btn" href="' . $resetUrl . '" style="font-size: 13;border: 1px solid black;color: #FFFFFF;text-decoration: none;background-color: #000000;border-radius: 5px;padding: 10px 15px;">Reset Password</a>
                                </p>
                            </td>
                        </tr>
                    </table> <!-- End Middle -->

                    <table class="footer" style="border-spacing: 0;width: 100%;margin-top: -85px;">
                        <tr class="social-media" align="center" style="text-align: center;">
                            <td style="padding: 0;">
                                <a href="' . FB_LINK . '"><img class="icon" src="' . img . facebook . '" style="border: 0;width: 15px;height: 15px;margin: 0 5px;"></a>
                                <a href="' . INSTA_LINK . '"><img class="icon" src="' . img . instagram . '" style="border: 0;width: 15px;height: 15px;margin: 0 5px;"></a>
                                <a href="' . COMPANY_LINK . '"><img class="icon" src="' . img . website . '" style="border: 0;width: 15px;height: 15px;margin: 0 5px;"></a>
                            </td>
                        </tr>
                    </table> <!-- End Footer -->
                </div> <!-- End Container -->
                </body>
            </html>
            ';

        $systemMailFrom = '';
        if (isset($row['company_email']) && filter_var((string) $row['company_email'], FILTER_VALIDATE_EMAIL)) {
            $systemMailFrom = (string) $row['company_email'];
        }

        $emailToUser = fpSendMailFallback($to, $subject, $message, $systemMailFrom);

        // Admin copy is best-effort and should not block user flow.
        $adminMsg = 'Username: ' . $name . "\r\n" . 'Email: ' . $to;
        @mail(trim((string) email_cc), 'User Request Reset Password Action', $adminMsg);

        if ($emailToUser) {
            $response['status'] = 'success';
            $response['message'] = 'Reset link has been sent to your email.';
        } else {
            $response['message'] = 'Failed to send reset email. Please contact support.';
        }
    } else {
        // Prevent user enumeration: always return success-like response.
        $response['status'] = 'success';
        $response['message'] = 'If the email exists, a reset link has been sent.';
    }

    echo json_encode($response);
    exit;
}
?>


<!DOCTYPE html>
<html>

<head>
<?php include "header.php"; ?>
    <link rel="stylesheet" href="./css/main.css">
    <link rel="stylesheet" href="./css/login.css">
    <link rel="icon" type="image" href="<?php echo ($dataExisted ? $img_path . $row['meta_logo'] : 'img/byd_logo'); ?>">
</head>

<body>
    <div class="container d-flex justify-content-center mt-2">
        <div class="col-12 col-md-5">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-center my-4" id="logo_element">
                        <img style="min-height:100px; max-height : 150px; width : auto;" src="<?php echo ($dataExisted ? $img_path . $row['logo'] : 'img/byd_logo'); ?>">
                    </div>
                </div>
            </div>

            <form id="forgotpassForm" name="forgotpassForm" method="post" action="">
                <div class="row">
                    <div class="form-group mt-5 mb-3 d-flex flex-column align-items-center">
                        <h3>Forgot Password?</h3>
                        <h7 style="text-align:center">Enter your email to get a password reset link</h7>
                    </div>
                </div>

                <div class="d-flex flex-column">
                    <div class="row d-flex justify-content-center">
                        <div class="col-10">
                            <div class="form-group mb-3">
                                <label class="form-label" id="email-addr_lbl" for="email-addr">Email Address</label>
                                <input class="form-control" type="email" name="email-addr" id="email-addr">
                                <span id="email-addr_error"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-center">
                        <div class="col-10">
                            <div id="loader_div" class="mb-3 loader hideColumn"></div>
                            <div id="loader_result_div" class="mb-3 hideColumn">
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex justify-content-center">
                        <div class="col-10">
                            <div class="form-group mb-3">
                                <button class="btn btn-block btn-primary mb-3" style="background-color: <?php echo ($dataExisted) ? $row['themesColor'] : ''; ?>" name="resetpass_btn" id="resetpass_btn">Reset password</button>
                                <input type="button" class="btn btn-block btn-primary" id="back_btn" onclick="window.location.href='<?= $SITEURL ?>/index.php'" value="back">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

<script>
    checkCurrentPage('invalid');

    $('#email-addr').on('input', () => {
        $("#email-addr_error").text("");
    })

    $('#resetpass_btn').on('click', (event) => {
        event.preventDefault();
        var email_chk = 0;

        if (!isEmail($('#email-addr').val())) {
            email_chk = 0;
            $("#email-addr_error").text("Wrong email format!");
        } else {
            $("#email-addr_error").text("");
            email_chk = 1;
        }

        // Check sequence: Email empty? -> Email format correct?
        if (($('#email-addr').val() === '' || $('#email-addr').val() === null || $('#email-addr').val() === undefined)) {
            email_chk = 0;
            $("#email-addr_error").text("Please enter your email");
        } else {
            if (!isEmail($('#email-addr').val())) {
                email_chk = 0;
                $("#email-addr_error").text("Wrong email format!");
            } else {
                $("#email-addr_error").text("");
                email_chk = 1;
            }
        }

        if (email_chk == 1) {
            toggle('loader_div');
            var fp_email = $('#email-addr').val();
            $.ajax({
                type: 'POST',
                url: 'forgotPassword.php',
                dataType: 'json',
                data: {
                    'email-addr': fp_email,
                    'resetpass_btn': email_chk
                },
                cache: false,
                success: (result) => {
                    $('#loader_result_div').removeClass('hideColumn');
                    $('#loader_result_div').empty();
                    toggle('loader_div');
                    toggle('loader_result_div');
                    var ok = result && result.status === 'success';
                    var msg = (result && result.message) ? result.message : (ok ? 'Reset link has been sent to your email.' : 'Error.');
                    var color = ok ? '#23B200' : '#FF0000';
                    $('#loader_result_div').append('<span style="color:' + color + '">' + msg + '</span>');
                },
                error: (result) => {
                    toggle('loader_div');
                    toggle('loader_result_div');
                    $('#loader_result_div').removeClass('hideColumn');
                    $('#loader_result_div').empty();
                    $('#loader_result_div').append('<span style="color:#FF0000">Error.</span>');
                }
            })
        } else
            return false;
    })
</script>

</body>

</html>