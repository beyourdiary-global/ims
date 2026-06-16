<!DOCTYPE html>
<html>

<head>
    <?php
    include_once "include/connection.php";
    include_once "include/common.php";
    include_once "include/common_variable.php";
    include_once ROOT . "/include/system_alert_common.php";

    $menuHeaderRootPath = defined('ROOT') ? realpath(ROOT) : realpath(__DIR__);
    if ($menuHeaderRootPath === false) {
        $menuHeaderRootPath = defined('ROOT') ? ROOT : __DIR__;
    }

    $menuHeaderIncludePaths = explode(PATH_SEPARATOR, get_include_path());
    $menuHeaderNormalizedIncludePaths = array();

    foreach ($menuHeaderIncludePaths as $menuHeaderIncludePath) {
        $menuHeaderRealIncludePath = realpath($menuHeaderIncludePath);
        $menuHeaderNormalizedIncludePaths[] = $menuHeaderRealIncludePath !== false ? $menuHeaderRealIncludePath : $menuHeaderIncludePath;
    }

    if (!in_array($menuHeaderRootPath, $menuHeaderNormalizedIncludePaths, true)) {
        set_include_path($menuHeaderRootPath . PATH_SEPARATOR . get_include_path());
    }

    if (!function_exists('listPageNetworkFailRedirect')) {
        function listPageNetworkFailRedirect($redirectUrl = '')
        {
            global $SITEURL;

            $redirectUrl = trim((string) $redirectUrl);

            if ($redirectUrl === '') {
                if (isset($SITEURL) && trim((string) $SITEURL) !== '') {
                    $redirectUrl = rtrim((string) $SITEURL, '/') . '/dashboard.php';
                } else if (defined('SITEURL')) {
                    $redirectUrl = rtrim((string) SITEURL, '/') . '/dashboard.php';
                } else {
                    $redirectUrl = 'dashboard.php';
                }
            }

            echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
            echo '<script>location.href = ' . json_encode($redirectUrl) . ';</script>';
            exit;
        }
    }



    $img_path = $SITEURL . '/' . img_server . 'themes/';
    $rst = getData('*', "id = '1'", '', 'projects', $connect);

    if (!$rst) {
        echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
        echo '<script>location.href = "' . $SITEURL . '/index.php";</script>';
    } else {
        $dataExisted = 1;
        $row = $rst->fetch_assoc();
    }

    // --- ADD THIS NEW PART ---
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $displayUserName = "User"; 
    if (isset($_SESSION['userid'])) {
        $user_rst = getData('*', "id = '" . $_SESSION['userid'] . "'", '', 'user', $connect);
        if ($user_rst && $user_rst->num_rows > 0) {
            $user_row = $user_rst->fetch_assoc();
            $displayUserName = $user_row['username']; 
        }
    } elseif (isset($_SESSION['username'])) {
        $user_rst = getData('*', "username = '" . $_SESSION['username'] . "'", '', 'user', $connect);
        if ($user_rst && $user_rst->num_rows > 0) {
            $user_row = $user_rst->fetch_assoc();
            $displayUserName = $user_row['username'];
        }
    }

    if (!function_exists('systemAlertMenuAppendQueryParam')) {
        function systemAlertMenuAppendQueryParam($url, $key, $value)
        {
            $url = trim((string) $url);
            $key = trim((string) $key);
            if ($url === '' || $key === '') {
                return $url;
            }

            $fragment = '';
            if (strpos($url, '#') !== false) {
                $parts = explode('#', $url, 2);
                $url = $parts[0];
                $fragment = '#' . $parts[1];
            }

            $separator = strpos($url, '?') === false ? '?' : '&';
            return $url . $separator . rawurlencode($key) . '=' . rawurlencode((string) $value) . $fragment;
        }
    }

    $menuAlertRows = array();
    $menuAlertAllRows = array();
    $menuAlertUnreadCount = 0;
    $menuAlertTotalCount = 0;
    $menuAlertModuleOptions = function_exists('systemAlertGetModuleFilterOptions') ? systemAlertGetModuleFilterOptions() : array();
    $menuAlertUserId = defined('USER_ID') ? (int) USER_ID : (isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : 0);
    $menuAlertRequestUri = isset($_SERVER['REQUEST_URI']) && trim((string) $_SERVER['REQUEST_URI']) !== ''
        ? (string) $_SERVER['REQUEST_URI']
        : '/dashboard.php';
    $menuAlertCurrentPageUrl = rtrim((string) $SITEURL, '/') . $menuAlertRequestUri;
    $menuAlertModalReturnUrl = systemAlertMenuAppendQueryParam($menuAlertCurrentPageUrl, 'system_alert_modal', '1');
    $menuAlertLiveEndpointUrl = rtrim((string) $SITEURL, '/') . '/system_alert_live.php';

    if ($menuAlertUserId > 0 && function_exists('systemAlertGenerateForUser')) {
        systemAlertGenerateForUser($connect, isset($finance_connect) ? $finance_connect : $connect, $menuAlertUserId);
        $menuAlertUnreadCount = systemAlertGetUnreadCount($connect, $menuAlertUserId);
        $menuAlertTotalCount = systemAlertGetTotalCount($connect, $menuAlertUserId);
        $menuAlertRows = systemAlertFetchForUser($connect, $menuAlertUserId, 10);
        $menuAlertAllRows = function_exists('systemAlertFetchListForUser')
            ? systemAlertFetchListForUser($connect, $menuAlertUserId)
            : $menuAlertRows;
    }

    if (!function_exists('systemAlertMenuFormatDateLabel')) {
        function systemAlertMenuFormatDateLabel($alertRow)
        {
            $dateValue = trim((string) (isset($alertRow['display_date']) && $alertRow['display_date'] !== '' ? $alertRow['display_date'] : (isset($alertRow['create_date']) ? $alertRow['create_date'] : '')));
            $timeValue = trim((string) (isset($alertRow['create_time']) ? $alertRow['create_time'] : ''));
            if ($dateValue === '') {
                return '';
            }

            $timestamp = strtotime($dateValue . ($timeValue !== '' ? (' ' . $timeValue) : ''));
            if ($timestamp === false) {
                return $dateValue;
            }

            return date('d M Y', $timestamp) . ($timeValue !== '' ? (' ' . date('H:i', $timestamp)) : '');
        }
    }
    // --- END OF NEW PART ---

    include_once "header.php";

    ?>
    <link rel="icon" type="image" href="<?php if (isset($row['meta_logo']))
        echo $img_path . $row['meta_logo']; ?>">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .system-alert-bell-link {
            position: relative;
            color: #fff;
            padding-right: 0.25rem;
        }

        .system-alert-bell-link.system-alert-bell-pulse {
            animation: system-alert-bell-ring 0.7s ease;
        }

        .system-alert-bell-link.system-alert-bell-pulse i {
            transform-origin: top center;
            animation: system-alert-bell-icon-ring 0.7s ease;
        }

        .system-alert-badge {
            position: absolute;
            top: -0.2rem;
            right: -0.35rem;
            min-width: 1.1rem;
            height: 1.1rem;
            padding: 0 0.25rem;
            border-radius: 999px;
            background: #dc3545;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .system-alert-dropdown {
            min-width: 340px;
            max-width: 380px;
            padding: 0;
            border-radius: 14px;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .topnav-mobile-actions {
                display: flex !important;
                align-items: center;
                justify-content: flex-end;
                gap: 0.1rem;
                margin-left: auto;
                padding-right: 0.75rem;
                min-width: 96px;
                flex-shrink: 0;
            }

            .topnav-mobile-actions .dropdown {
                flex: 0 0 44px;
            }

            .topnav-mobile-actions .nav-link {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 44px;
                height: 44px;
                padding: 0;
                color: #fff;
            }

            .topnav-mobile-actions .dropdown-menu {
                z-index: 1065;
            }

            .topnav-mobile-actions .system-alert-dropdown {
                position: fixed !important;
                top: 58px !important;
                left: 50% !important;
                right: auto !important;
                transform: translateX(-50%) !important;
                width: min(380px, 92vw);
                min-width: 300px;
                max-width: 92vw;
                margin-top: 0 !important;
            }
        }

        .system-alert-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .system-alert-dropdown-header-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #212529;
        }

        .system-alert-mark-all {
            font-size: 0.8rem;
            text-decoration: none;
            text-transform: none !important;
        }

        .system-alert-list {
            max-height: 420px;
            overflow-y: auto;
        }

        .system-alert-item {
            display: block;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f3f5;
            text-decoration: none;
            color: inherit;
            background: #fff;
        }

        .system-alert-item:last-child {
            border-bottom: 0;
        }

        .system-alert-item:hover {
            background: #f8f9fa;
        }

        .system-alert-item-unread {
            background: #f8fbff;
        }

        .system-alert-item-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.2rem;
            flex: 1 1 0;
            min-width: 0;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .system-alert-item-message {
            font-size: 0.82rem;
            color: #6c757d;
            line-height: 1.35;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .system-alert-item-meta {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 0.2rem;
        }

        .system-alert-item-time {
            font-size: 0.72rem;
            color: #6c757d;
            white-space: normal;
            flex: 0 1 auto;
        }

        .system-alert-unread-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #0d6efd;
            flex-shrink: 0;
        }

        .system-alert-empty {
            padding: 1rem;
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .system-alert-dropdown-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid #e9ecef;
            background: #fff;
            text-align: center;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }

        .system-alert-view-all {
            font-size: 0.82rem;
            text-decoration: none;
            font-weight: 500;
            text-transform: none !important;
        }

        .system-alert-modal-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.84rem;
            font-weight: 500;
            color: #495057;
        }

        .system-alert-modal-status-dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            display: inline-block;
            flex-shrink: 0;
        }

        .system-alert-modal-status-dot-unread {
            background: #0d6efd;
        }

        .system-alert-modal-status-dot-read {
            background: #adb5bd;
        }

        .system-alert-modal-filter-row .form-label {
            font-size: 0.84rem;
            font-weight: 500;
            margin-bottom: 0.35rem;
        }

        .system-alert-modal-message {
            min-width: 260px;
            color: #6c757d;
            line-height: 1.4;
        }

        .system-alert-modal-title {
            min-width: 180px;
            font-weight: 600;
            color: #212529;
        }

        .system-alert-modal-module-badge {
            display: inline-block;
            padding: 0.28rem 0.6rem;
            border-radius: 999px;
            background: #eef4ff;
            color: #3157a3;
            font-size: 0.78rem;
            font-weight: 500;
        }

        .system-alert-modal-action-group .btn {
            white-space: nowrap;
            text-transform: none !important;
        }

        #allNotificationModal .modal-footer .btn {
            text-transform: none !important;
        }

        @media (max-width: 767.98px) {
            .system-alert-dropdown {
                min-width: 300px;
                max-width: 92vw;
            }
        }

        @keyframes system-alert-bell-ring {
            0% {
                transform: scale(1);
            }
            30% {
                transform: scale(1.12);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes system-alert-bell-icon-ring {
            0% {
                transform: rotate(0deg);
            }
            20% {
                transform: rotate(-18deg);
            }
            40% {
                transform: rotate(16deg);
            }
            60% {
                transform: rotate(-10deg);
            }
            80% {
                transform: rotate(8deg);
            }
            100% {
                transform: rotate(0deg);
            }
        }
    </style>
</head>

<!-- Navbar -->
<div class="sticky-top">
    <nav class="navbar navbar-expand-md topNav p-0" id="topNav" style="background-color:<?php if ($dataExisted)
        echo $row['themesColor']; ?>;">
        <!-- Container wrapper -->
        <div class="container-fluid p-0">
            <!-- Toggle button -->
            <button class="navbar-toggler ps-4" style="height:50px;" type="button" data-bs-toggle="collapse"
                data-bs-target="#sidebar-collapse" aria-expanded="false" id="sidebarCollapse">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navbar brand -->
            <div class="d-flex align-items-center mx-2">
                <a class="logo_section navbar-brand mx-4" href="<?php echo SITEURL; ?>/dashboard.php">
                    <img id="logo" src="
                    <?php
                    if ($dataExisted)
                        echo $img_path . $row['logo'];
                    else
                        echo $SITEURL . '/' . img . byd_logo;
                    ?>">
                </a>
            </div>

            <!-- Collapsible wrapper -->
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Right elements -->
                <div class="container-fluid d-flex justify-content-between">
                    <!-- Title -->
                    <div class="d-flex flex-row align-items-center">
                        <ul class="navbar-nav ms-4">
                            <li class="nav-item menuheader-text">
                                <?php
                                if ($dataExisted)
                                    echo $row['project_title'];
                                else
                                    echo "CMS SYSTEM";
                                ?>
                            </li>
                        </ul>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="dropdown me-3">
                            <a class="nav-link system-alert-bell-link" href="#"
                                id="navbarAlertDropdown" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fas fa-bell fa-lg"></i>
                                <span id="systemAlertDesktopBadgeSlot"><?php if ($menuAlertUnreadCount > 0) { ?><span class="system-alert-badge"><?= $menuAlertUnreadCount > 99 ? '99+' : (int) $menuAlertUnreadCount ?></span><?php } ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right mt-3 system-alert-dropdown"
                                aria-labelledby="navbarAlertDropdown" id="systemAlertDesktopDropdownMenu">
                                <div class="system-alert-dropdown-header">
                                    <div class="system-alert-dropdown-header-title">Notifications</div>
                                    <?php if ($menuAlertUnreadCount > 0) { ?>
                                        <a class="system-alert-mark-all" href="<?= htmlspecialchars($SITEURL . '/system_alert_action.php?action=mark_all&redirect=' . urlencode($menuAlertCurrentPageUrl), ENT_QUOTES, 'UTF-8') ?>">Mark all as read</a>
                                    <?php } ?>
                                </div>
                                <div class="system-alert-list">
                                    <?php if (empty($menuAlertRows)) { ?>
                                        <div class="system-alert-empty">No notifications</div>
                                    <?php } else { ?>
                                        <?php foreach ($menuAlertRows as $menuAlertRow) {
                                            $menuAlertId = isset($menuAlertRow['id']) ? (int) $menuAlertRow['id'] : 0;
                                            $menuAlertIsUnread = strtoupper(trim((string) (isset($menuAlertRow['is_read']) ? $menuAlertRow['is_read'] : 'N'))) !== 'Y';
                                            $menuAlertTitle = trim((string) (isset($menuAlertRow['title']) ? $menuAlertRow['title'] : 'Notification'));
                                            $menuAlertMessage = trim((string) (isset($menuAlertRow['message']) ? $menuAlertRow['message'] : ''));
                                            $menuAlertLink = $SITEURL . '/system_alert_action.php?id=' . $menuAlertId . '&redirect=' . urlencode($menuAlertCurrentPageUrl);
                                            ?>
                                            <a class="system-alert-item <?= $menuAlertIsUnread ? 'system-alert-item-unread' : '' ?>" href="<?= htmlspecialchars($menuAlertLink, ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="system-alert-item-meta">
                                                    <div class="system-alert-item-title"><?= htmlspecialchars($menuAlertTitle !== '' ? $menuAlertTitle : 'Notification', ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($menuAlertIsUnread) { ?>
                                                            <span class="system-alert-unread-dot"></span>
                                                        <?php } ?>
                                                        <span class="system-alert-item-time"><?= htmlspecialchars(systemAlertMenuFormatDateLabel($menuAlertRow), ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                </div>
                                                <div class="system-alert-item-message"><?= htmlspecialchars($menuAlertMessage, ENT_QUOTES, 'UTF-8') ?></div>
                                            </a>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                                <?php if ($menuAlertTotalCount > 10) { ?>
                                    <div class="system-alert-dropdown-footer">
                                        <button type="button" class="btn btn-link system-alert-view-all p-0"
                                            data-bs-toggle="modal" data-bs-target="#allNotificationModal">
                                            View All Notifications
                                        </button>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#"
                                id="navbarDropdownMenuAvatar" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fas fa-user-circle fa-lg me-2"></i>
                                <?php echo htmlspecialchars($displayUserName); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-right mt-3"
                                aria-labelledby="navbarDropdownMenuAvatar">
                                <li>
                                    <a class="dropdown-item" href="<?= $SITEURL ?>/user_profile.php">My profile</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= $SITEURL ?>/changePassword.php">Settings</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= $SITEURL ?>/logout.php">Logout</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Right elements -->
            </div>

            <!-- Toggle button -->
            <div class="topnav-mobile-actions d-md-none">
                <div class="dropdown">
                    <button class="nav-link system-alert-bell-link" type="button" id="navbarTogglerAlertDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fa-lg"></i>
                        <span id="systemAlertMobileBadgeSlot"><?php if ($menuAlertUnreadCount > 0) { ?><span class="system-alert-badge"><?= $menuAlertUnreadCount > 99 ? '99+' : (int) $menuAlertUnreadCount ?></span><?php } ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right mt-0 system-alert-dropdown" aria-labelledby="navbarTogglerAlertDropdown" id="systemAlertMobileDropdownMenu">
                        <div class="system-alert-dropdown-header">
                            <div class="system-alert-dropdown-header-title">Notifications</div>
                            <?php if ($menuAlertUnreadCount > 0) { ?>
                                <a class="system-alert-mark-all" href="<?= htmlspecialchars($SITEURL . '/system_alert_action.php?action=mark_all&redirect=' . urlencode($menuAlertCurrentPageUrl), ENT_QUOTES, 'UTF-8') ?>">Mark all as read</a>
                            <?php } ?>
                        </div>
                        <div class="system-alert-list">
                            <?php if (empty($menuAlertRows)) { ?>
                                <div class="system-alert-empty">No notifications</div>
                            <?php } else { ?>
                                <?php foreach ($menuAlertRows as $menuAlertRow) {
                                    $menuAlertId = isset($menuAlertRow['id']) ? (int) $menuAlertRow['id'] : 0;
                                    $menuAlertIsUnread = strtoupper(trim((string) (isset($menuAlertRow['is_read']) ? $menuAlertRow['is_read'] : 'N'))) !== 'Y';
                                    $menuAlertTitle = trim((string) (isset($menuAlertRow['title']) ? $menuAlertRow['title'] : 'Notification'));
                                    $menuAlertMessage = trim((string) (isset($menuAlertRow['message']) ? $menuAlertRow['message'] : ''));
                                    $menuAlertLink = $SITEURL . '/system_alert_action.php?id=' . $menuAlertId . '&redirect=' . urlencode($menuAlertCurrentPageUrl);
                                    ?>
                                    <a class="system-alert-item <?= $menuAlertIsUnread ? 'system-alert-item-unread' : '' ?>" href="<?= htmlspecialchars($menuAlertLink, ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="system-alert-item-meta">
                                            <div class="system-alert-item-title"><?= htmlspecialchars($menuAlertTitle !== '' ? $menuAlertTitle : 'Notification', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($menuAlertIsUnread) { ?>
                                                    <span class="system-alert-unread-dot"></span>
                                                <?php } ?>
                                                <span class="system-alert-item-time"><?= htmlspecialchars(systemAlertMenuFormatDateLabel($menuAlertRow), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                        <div class="system-alert-item-message"><?= htmlspecialchars($menuAlertMessage, ENT_QUOTES, 'UTF-8') ?></div>
                                    </a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <?php if ($menuAlertTotalCount > 10) { ?>
                            <div class="system-alert-dropdown-footer">
                                <button type="button" class="btn btn-link system-alert-view-all p-0"
                                    data-bs-toggle="modal" data-bs-target="#allNotificationModal">
                                    View All Notifications
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="nav-link d-flex align-items-center" type="button" id="navbarTogglerMenuAvatar"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-vertical fa-lg"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right mt-0" aria-labelledby="navbarTogglerMenuAvatar">
                        <li>
                            <a class="dropdown-item" href="<?= $SITEURL ?>/user_profile.php">My profile</a>
                            <div class="dropdown-divider my-0"></div>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $SITEURL ?>/changePassword.php">Settings</a>
                            <div class="dropdown-divider my-0"></div>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $SITEURL ?>/logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>


        </div>
        <!-- Container wrapper -->
    </nav>
    <!-- Navbar -->
    <?php include ROOT . "/menu_bar.php"; ?>
</div>

<?php include_once ROOT . '/include/list_page_preloader.php'; ?>
<script>
    if (typeof preloader === 'function') {
        preloader(300);
    }
</script>

<div class="modal fade" id="allNotificationModal" tabindex="-1" aria-labelledby="allNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allNotificationModalLabel">All Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 system-alert-modal-filter-row mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="allNotificationReadStatusFilter">Read Status</label>
                        <select id="allNotificationReadStatusFilter" class="form-select">
                            <option value="">All</option>
                            <option value="Unread">Unread</option>
                            <option value="Read">Read</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="allNotificationModuleFilter">Module</label>
                        <select id="allNotificationModuleFilter" class="form-select">
                            <option value="">All Module</option>
                            <?php foreach ($menuAlertModuleOptions as $moduleKey => $moduleLabel) { ?>
                                <option value="<?= htmlspecialchars((string) $moduleLabel, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $moduleLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0" id="allNotificationTable">
                        <thead>
                            <tr>
                                <th>Read Status</th>
                                <th>Title</th>
                                <th>Message</th>
                                <th>Module</th>
                                <th>Date / Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($menuAlertAllRows)) { ?>
                                <?php foreach ($menuAlertAllRows as $menuAlertRow) { ?>
                                    <?php
                                    $menuAlertId = isset($menuAlertRow['id']) ? (int) $menuAlertRow['id'] : 0;
                                    $menuAlertIsUnread = strtoupper(trim((string) (isset($menuAlertRow['is_read']) ? $menuAlertRow['is_read'] : 'N'))) !== 'Y';
                                    $menuAlertTitle = trim((string) (isset($menuAlertRow['title']) ? $menuAlertRow['title'] : 'Notification'));
                                    $menuAlertMessage = trim((string) (isset($menuAlertRow['message']) ? $menuAlertRow['message'] : ''));
                                    $menuAlertModuleLabel = function_exists('systemAlertFormatModuleLabel')
                                        ? systemAlertFormatModuleLabel(isset($menuAlertRow['module_key']) ? $menuAlertRow['module_key'] : '')
                                        : trim((string) (isset($menuAlertRow['module_key']) ? $menuAlertRow['module_key'] : 'General'));
                                    $menuAlertOpenLink = $SITEURL . '/system_alert_action.php?id=' . $menuAlertId . '&redirect=' . urlencode($menuAlertCurrentPageUrl);
                                    $menuAlertMarkReadLink = $SITEURL . '/system_alert_action.php?action=mark_read&id=' . $menuAlertId . '&redirect=' . urlencode($menuAlertModalReturnUrl);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="system-alert-modal-status-pill">
                                                <span class="system-alert-modal-status-dot <?= $menuAlertIsUnread ? 'system-alert-modal-status-dot-unread' : 'system-alert-modal-status-dot-read' ?>"></span>
                                                <?= $menuAlertIsUnread ? 'Unread' : 'Read' ?>
                                            </span>
                                        </td>
                                        <td class="system-alert-modal-title"><?= htmlspecialchars($menuAlertTitle !== '' ? $menuAlertTitle : 'Notification', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="system-alert-modal-message"><?= nl2br(htmlspecialchars($menuAlertMessage, ENT_QUOTES, 'UTF-8')) ?></td>
                                        <td><span class="system-alert-modal-module-badge"><?= htmlspecialchars($menuAlertModuleLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars(systemAlertMenuFormatDateLabel($menuAlertRow), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2 system-alert-modal-action-group">
                                                <a href="<?= htmlspecialchars($menuAlertOpenLink, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-primary">Open</a>
                                                <?php if ($menuAlertIsUnread) { ?>
                                                    <a href="<?= htmlspecialchars($menuAlertMarkReadLink, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-secondary">Mark As Read</a>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No notifications found.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?= htmlspecialchars($SITEURL . '/system_alert_action.php?action=mark_all&redirect=' . urlencode($menuAlertModalReturnUrl), ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-outline-primary">Mark All As Read</a>
            </div>
        </div>
    </div>
</div>

<!-- Move the script block to the end of the body -->
<script>
    let allNotificationDataTable = null;
    const systemAlertLiveConfig = {
        endpointUrl: <?= json_encode($menuAlertLiveEndpointUrl) ?>,
        currentUrl: <?= json_encode($menuAlertCurrentPageUrl) ?>,
        modalReturnUrl: <?= json_encode($menuAlertModalReturnUrl) ?>,
        pollMs: 3000
    };
    let systemAlertRefreshInFlight = false;
    let systemAlertPollHandle = null;
    let systemAlertPreviousUnreadCount = <?= (int) $menuAlertUnreadCount ?>;

    function systemAlertEscapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function systemAlertRenderBadge(unreadCount) {
        unreadCount = Number(unreadCount || 0);
        if (unreadCount <= 0) {
            return '';
        }

        return '<span class="system-alert-badge">' + (unreadCount > 99 ? '99+' : unreadCount) + '</span>';
    }

    function systemAlertRenderDropdown(payload) {
        var unreadCount = Number(payload && payload.unread_count ? payload.unread_count : 0);
        var totalCount = Number(payload && payload.total_count ? payload.total_count : 0);
        var items = payload && Array.isArray(payload.items) ? payload.items : [];
        var html = '' +
            '<div class="system-alert-dropdown-header">' +
            '    <div class="system-alert-dropdown-header-title">Notifications</div>';

        if (unreadCount > 0 && payload && payload.mark_all_url) {
            html += '<a class="system-alert-mark-all" href="' + systemAlertEscapeHtml(payload.mark_all_url) + '">Mark all as read</a>';
        }

        html += '</div><div class="system-alert-list">';

        if (!items.length) {
            html += '<div class="system-alert-empty">No notifications</div>';
        } else {
            items.forEach(function (item) {
                var unreadClass = item && item.is_unread ? ' system-alert-item-unread' : '';
                html += '' +
                    '<a class="system-alert-item' + unreadClass + '" href="' + systemAlertEscapeHtml(item.link || '#') + '">' +
                    '    <div class="system-alert-item-meta">' +
                    '        <div class="system-alert-item-title">' + systemAlertEscapeHtml(item.title || 'Notification') + '</div>' +
                    '        <div class="d-flex align-items-center gap-2">';

                if (item && item.is_unread) {
                    html += '<span class="system-alert-unread-dot"></span>';
                }

                html += '' +
                    '            <span class="system-alert-item-time">' + systemAlertEscapeHtml(item.time_label || '') + '</span>' +
                    '        </div>' +
                    '    </div>' +
                    '    <div class="system-alert-item-message">' + systemAlertEscapeHtml(item.message || '') + '</div>' +
                    '</a>';
            });
        }

        html += '</div>';

        if (totalCount > 10) {
            html += '' +
                '<div class="system-alert-dropdown-footer">' +
                '    <button type="button" class="btn btn-link system-alert-view-all p-0" data-bs-toggle="modal" data-bs-target="#allNotificationModal">' +
                '        View All Notifications' +
                '    </button>' +
                '</div>';
        }

        return html;
    }

    function systemAlertCaptureDropdownScroll(menuElement) {
        if (!menuElement) {
            return null;
        }

        var listElement = menuElement.querySelector('.system-alert-list');
        if (!listElement) {
            return null;
        }

        return {
            isOpen: menuElement.classList.contains('show'),
            scrollTop: listElement.scrollTop,
            scrollLeft: listElement.scrollLeft
        };
    }

    function systemAlertRestoreDropdownScroll(menuElement, scrollState) {
        if (!menuElement || !scrollState || !scrollState.isOpen) {
            return;
        }

        var listElement = menuElement.querySelector('.system-alert-list');
        if (!listElement) {
            return;
        }

        listElement.scrollTop = scrollState.scrollTop;
        listElement.scrollLeft = scrollState.scrollLeft;
    }

    function systemAlertApplyLivePayload(payload) {
        var unreadCount = Number(payload && payload.unread_count ? payload.unread_count : 0);
        var desktopBadgeSlot = document.getElementById('systemAlertDesktopBadgeSlot');
        var mobileBadgeSlot = document.getElementById('systemAlertMobileBadgeSlot');
        var desktopDropdownMenu = document.getElementById('systemAlertDesktopDropdownMenu');
        var mobileDropdownMenu = document.getElementById('systemAlertMobileDropdownMenu');
        var desktopBellTrigger = document.getElementById('navbarAlertDropdown');
        var mobileBellTrigger = document.getElementById('navbarTogglerAlertDropdown');
        var dropdownHtml = systemAlertRenderDropdown(payload || {});

        if (desktopBadgeSlot) {
            desktopBadgeSlot.innerHTML = systemAlertRenderBadge(unreadCount);
        }
        if (mobileBadgeSlot) {
            mobileBadgeSlot.innerHTML = systemAlertRenderBadge(unreadCount);
        }
        if (desktopDropdownMenu) {
            if (desktopDropdownMenu.innerHTML !== dropdownHtml) {
                var desktopScrollState = systemAlertCaptureDropdownScroll(desktopDropdownMenu);
                desktopDropdownMenu.innerHTML = dropdownHtml;
                systemAlertRestoreDropdownScroll(desktopDropdownMenu, desktopScrollState);
            }
        }
        if (mobileDropdownMenu) {
            if (mobileDropdownMenu.innerHTML !== dropdownHtml) {
                var mobileScrollState = systemAlertCaptureDropdownScroll(mobileDropdownMenu);
                mobileDropdownMenu.innerHTML = dropdownHtml;
                systemAlertRestoreDropdownScroll(mobileDropdownMenu, mobileScrollState);
            }
        }

        if (unreadCount > systemAlertPreviousUnreadCount) {
            [desktopBellTrigger, mobileBellTrigger].forEach(function (bellTrigger) {
                if (!bellTrigger) {
                    return;
                }

                bellTrigger.classList.remove('system-alert-bell-pulse');
                void bellTrigger.offsetWidth;
                bellTrigger.classList.add('system-alert-bell-pulse');
                window.setTimeout(function () {
                    bellTrigger.classList.remove('system-alert-bell-pulse');
                }, 800);
            });
        }

        systemAlertPreviousUnreadCount = unreadCount;
    }

    function systemAlertRefreshNow() {
        if (systemAlertRefreshInFlight || !systemAlertLiveConfig.endpointUrl) {
            return Promise.resolve();
        }

        systemAlertRefreshInFlight = true;
        return fetch(systemAlertLiveConfig.endpointUrl + '?current_url=' + encodeURIComponent(systemAlertLiveConfig.currentUrl) + '&_ts=' + Date.now(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to refresh notifications.');
                }

                return response.json();
            })
            .then(function (payload) {
                if (payload && payload.success) {
                    systemAlertApplyLivePayload(payload);
                }
            })
            .catch(function () {
                return null;
            })
            .finally(function () {
                systemAlertRefreshInFlight = false;
            });
    }

    window.systemAlertRefreshNow = systemAlertRefreshNow;

    function systemAlertInitModalTable() {
        if (typeof jQuery === 'undefined' || typeof $.fn === 'undefined') {
            return;
        }

        if ($.fn.DataTable) {
            if (!$.fn.DataTable.isDataTable('#allNotificationTable')) {
                allNotificationDataTable = $('#allNotificationTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    order: [[4, 'desc']]
                });
            } else {
                allNotificationDataTable = $('#allNotificationTable').DataTable();
                allNotificationDataTable.columns.adjust().draw(false);
            }

            $('#allNotificationReadStatusFilter').off('change.systemAlert').on('change.systemAlert', function () {
                if (allNotificationDataTable) {
                    allNotificationDataTable.column(0).search(this.value).draw();
                }
            });

            $('#allNotificationModuleFilter').off('change.systemAlert').on('change.systemAlert', function () {
                if (allNotificationDataTable) {
                    allNotificationDataTable.column(3).search(this.value).draw();
                }
            });
        } else if (typeof createSortingTable === 'function') {
            createSortingTable('allNotificationTable');
            if (typeof datatableAlignment === 'function') {
                datatableAlignment('allNotificationTable');
            }
        }
    }

    function setButtonColor() {
        var buttons = document.querySelectorAll('#actionBtn, #addBtn,#backBtn');

        buttons.forEach(function (button) {
            button.style.backgroundColor = '<?php echo ($dataExisted ? $row['buttonColor'] : ''); ?>';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var allNotificationModal = document.getElementById('allNotificationModal');
        var desktopBellTrigger = document.getElementById('navbarAlertDropdown');
        var mobileBellTrigger = document.getElementById('navbarTogglerAlertDropdown');

        if (desktopBellTrigger) {
            desktopBellTrigger.addEventListener('show.bs.dropdown', function () {
                systemAlertRefreshNow();
            });
        }

        if (mobileBellTrigger) {
            mobileBellTrigger.addEventListener('show.bs.dropdown', function () {
                systemAlertRefreshNow();
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                systemAlertRefreshNow();
            }
        });

        systemAlertRefreshNow();
        systemAlertPollHandle = window.setInterval(function () {
            if (document.visibilityState === 'visible') {
                systemAlertRefreshNow();
            }
        }, systemAlertLiveConfig.pollMs);

        if (!allNotificationModal || typeof bootstrap === 'undefined') {
            return;
        }

        allNotificationModal.addEventListener('shown.bs.modal', function () {
            systemAlertInitModalTable();
        });

        var searchParams = new URLSearchParams(window.location.search);
        if (searchParams.get('system_alert_modal') === '1') {
            bootstrap.Modal.getOrCreateInstance(allNotificationModal).show();
        }
    });
</script>

</html>
