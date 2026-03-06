<!DOCTYPE html>
<html>

<head>
    <?php
    include_once "include/connection.php";
    include_once "include/common.php";
    include_once "include/common_variable.php";

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
    // --- END OF NEW PART ---

    include_once "header.php";

    ?>
    <link rel="icon" type="image" href="<?php if (isset($row['meta_logo']))
        echo $img_path . $row['meta_logo']; ?>">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
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
                                    <a class="dropdown-item" href="#">My profile</a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#">Settings</a>
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
            <div class="navbar-toggler pe-4">
                <div class="dropdown">
                    <button class="nav-link d-flex align-items-center" href="#" id="navbarTogglerMenuAvatar"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-vertical fa-lg"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right mt-4" aria-labelledby="navbarTogglerMenuAvatar">
                        <li>
                            <a class="dropdown-item" href="#">My profile</a>
                            <div class="dropdown-divider my-0"></div>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">Settings</a>
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

<!-- Move the script block to the end of the body -->
<script>
    function setButtonColor() {
        var buttons = document.querySelectorAll('#actionBtn, #addBtn,#backBtn');

        buttons.forEach(function (button) {
            button.style.backgroundColor = '<?php echo ($dataExisted ? $row['buttonColor'] : ''); ?>';
        });
    }
</script>

</html>