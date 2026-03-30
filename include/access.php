<?php 
// Check access
    $accessActionKey = array();

    if (isset($_SESSION['usr_pin_access'][$currentPagePin]) && is_array($_SESSION['usr_pin_access'][$currentPagePin])) {
        $accessActionKey = $_SESSION['usr_pin_access'][$currentPagePin];
    } else if ((int) $currentPagePin !== 7) {
        // Non-dashboard pages still require a pin block.
        header("Location: " . $SITEURL . "/dashboard.php");
        exit;
    }

?>