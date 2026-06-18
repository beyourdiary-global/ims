<?php
/**
 * Shared setup for simple list/table pages.
 *
 * Page can set before include:
 * $currentPagePin
 * $pageTitle
 * $redirectPage
 * $deleteRedirectPage
 * $listPageRedirectPage
 * $listPageDeleteRedirectPage
 * $listPageSkipTitleResolve
 * $listPageSkipPinAccess
 * $listPageSkipSessionReset
 * $listPageSkipNumbering
 * $listPageNetworkFailRedirect
 */

if (!isset($currentPagePin)) {
    $currentPagePin = 0;
}

if (!isset($pageTitle)) {
    $pageTitle = '';
}

$rootPath = dirname(__DIR__);

// Allow table pages in root folder and subfolders to use the same include path:
// include_once 'include/list_page_preloader.php';
$includePaths = explode(PATH_SEPARATOR, get_include_path());
if (!in_array($rootPath, $includePaths, true)) {
    set_include_path($rootPath . PATH_SEPARATOR . get_include_path());
}

include_once $rootPath . '/menuHeader.php';
include_once $rootPath . '/checkCurrentPagePin.php';

if ((empty($listPageSkipTitleResolve)) && function_exists('getPinGroupNameById') && isset($connect)) {
    $resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
    if ($resolvedPageTitle !== '') {
        $pageTitle = $resolvedPageTitle;
    }
}

if (!isset($listPageSkipPinAccess) || !$listPageSkipPinAccess) {
    $pinAccess = checkCurrentPin($connect, $pageTitle);
}

if (!isset($pinAccess) || !is_array($pinAccess)) {
    $pinAccess = array();
}

if (empty($listPageSkipSessionReset)) {
    $_SESSION['act'] = '';
    $_SESSION['viewChk'] = '';
    $_SESSION['searchChk'] = '';
    unset($_SESSION['resetChk']);
    $_SESSION['delChk'] = '';
}

if (empty($listPageSkipNumbering)) {
    $num = 1;
}

if (isset($listPageRedirectPage) && trim((string) $listPageRedirectPage) !== '') {
    $redirectPage = $listPageRedirectPage;
} else if (!isset($redirectPage)) {
    $redirectPage = '';
}

if (isset($listPageDeleteRedirectPage) && trim((string) $listPageDeleteRedirectPage) !== '') {
    $deleteRedirectPage = $listPageDeleteRedirectPage;
} else if (!isset($deleteRedirectPage)) {
    $deleteRedirectPage = $redirectPage;
}

$networkFailRedirect = isset($listPageNetworkFailRedirect) && trim((string) $listPageNetworkFailRedirect) !== ''
    ? trim((string) $listPageNetworkFailRedirect)
    : (defined('SITEURL') ? SITEURL . '/dashboard.php' : 'dashboard.php');

if (!isset($connect) || !$connect) {
    renderNotificationScript('Network error. Please try again later.', 'error', $networkFailRedirect);
    exit;
}
