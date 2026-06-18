<?php
$_ims_root = dirname(__DIR__);
require_once $_ims_root . '/init.php';
$path =  $_SERVER['PHP_SELF'];
$path = explode("/", $path);
$login_url = $SITEURL."/index.php";

$currentPage = $path[sizeof($path) - 1];
$isForgotPasswordPage = ($currentPage === 'forgotPassword.php');
$isLoginProcessPage = ($currentPage === 'login.php');
$isTokenResetPage = (
    $currentPage === 'changePassword.php'
    && isset($_GET['token'])
    && isset($_GET['email'])
    && !is_array($_GET['token'])
    && !is_array($_GET['email'])
    && trim((string) $_GET['token']) !== ''
    && trim((string) $_GET['email']) !== ''
);

if (!($isForgotPasswordPage || $isTokenResetPage || $isLoginProcessPage)) {
    include_once ROOT . '/include/auto_login.php';

    if (!isset($_SESSION['userid'])) {
        cmsTryAutoLoginFromCookie($connect);
    }

    if (!isset($_SESSION['userid'])) {
        echo("<script>location.href = '$login_url';</script>");
        exit;
    }
}

/* 
include ROOT.'/include/header.php';
include ROOT.'/include/common.php';
include ROOT.'/include/breadcrumb.php';
include ROOT.'/include/menu.php';
include ROOT.'/include/sideMenu.php';
include ROOT.'/include/footer.php'; */

// include ROOT.'/includes/get_country.php';
// include ROOT.'/auditlog/auditor.php';
$currentScriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isProcessScript = (
    strpos($currentScriptName, '_process.php') !== false
    || strpos($currentScriptName, '_action.php') !== false
    || strpos($currentScriptName, '_ajax.php') !== false
);

if (!$isProcessScript) {
    include ROOT . '/recordDelete.php';
}
?>
