<?php
include "include/common.php";
include "include/connection.php";
include_once "include/auto_login.php";

if(isset($_SESSION['userid']))
{
    // audit log

    $query = "SELECT name FROM ".USR_USER." WHERE id ='".$_SESSION['userid']."'";
    $result = mysqli_query($connect, $query);

    if (!$result) {
        echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
        echo '<script>location.href = "' . $SITEURL . '/index.php";</script>';
    }

    $row = $result->fetch_assoc();

    $log = [
        'log_act' => 'logout',
        'act_msg' => "{$row['name']} has logged out of the system.",
        'cdate'   => $cdate,
        'ctime'   => $ctime,
        'uid'     => $_SESSION['userid'],
        'cby'     => $_SESSION['userid'],
        'connect' => $connect,
    ];
    
    audit_log($log);
}

cmsClearAutoLoginCookie();

setcookie(session_name(), '', time() - 3600, '/');
session_unset();
session_destroy();

// redirect
echo '<script>location.href = "' . $SITEURL . '/index.php";</script>';
?>