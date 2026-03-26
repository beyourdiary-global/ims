<?php
$pageTitle = "User Profile";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$tblName = USR_USER;
$redirectPage = $SITEURL . '/dashboard.php';

$pinAccess = checkCurrentPin($connect, $pageTitle);
if (!is_array($pinAccess) || count($pinAccess) === 0 || !isActionAllowed('View', $pinAccess)) {
    echo '<script>alert("No permission.");location.href = "' . $redirectPage . '";</script>';
    exit;
}

$userId = (int) USER_ID;
if ($userId <= 0) {
    echo '<script>location.href = "' . $SITEURL . '/logout.php";</script>';
    exit;
}

$msg = '';
$err = '';

if (post('actionBtn')) {
    $action = post('actionBtn');

    if ($action === 'back') {
        echo '<script>location.href = "' . $redirectPage . '";</script>';
        exit;
    }

    if ($action === 'saveProfile') {
        if (!isActionAllowed('Edit', $pinAccess)) {
            $err = 'No permission to edit profile.';
        } else {
            $name = postSpaceFilter('currentDataName');
            $username = postSpaceFilter('dataUsername');
            $email = postSpaceFilter('currentUserEmail');

            if ($name === '') {
                $err = 'Name is required.';
            } else if ($username === '') {
                $err = 'Username is required.';
            } else if ($email === '') {
                $err = 'Email is required.';
            } else if (isDuplicateRecord('name', $name, $tblName, $connect, $userId)) {
                $err = 'Duplicate record found for username.';
            } else if (isDuplicateRecord('username', $username, $tblName, $connect, $userId)) {
                $err = 'Duplicate record found for user name.';
            } else if (isDuplicateRecord('email', $email, $tblName, $connect, $userId)) {
                $err = 'Duplicate record found for user email.';
            } else {
                $safeName = mysqli_real_escape_string($connect, $name);
                $safeUsername = mysqli_real_escape_string($connect, $username);
                $safeEmail = mysqli_real_escape_string($connect, $email);

                $query = "UPDATE " . $tblName . " SET name='$safeName', username='$safeUsername', email='$safeEmail', update_by='" . USER_ID . "', update_date=CURDATE(), update_time=CURTIME() WHERE id='" . $userId . "'";
                $ok = mysqli_query($connect, $query);

                if ($ok) {
                    $_SESSION['username'] = $safeUsername;
                    $msg = 'Profile updated successfully.';
                } else {
                    $err = 'Failed to update profile.';
                }
            }
        }
    }
}

$userRst = getData('*', "id='" . $userId . "'", 'LIMIT 1', $tblName, $connect);
if (!$userRst || $userRst->num_rows === 0) {
    echo '<script>alert("User not found.");location.href = "' . $SITEURL . '/logout.php";</script>';
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
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

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

                    <?php if ($msg !== '') { ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
                    <?php if ($err !== '') { ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataName">Name</label>
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?= htmlspecialchars((string) $userRow['name'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="dataUsername">Username</label>
                        <input class="form-control" type="text" name="dataUsername" id="dataUsername" value="<?= htmlspecialchars((string) $userRow['username'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentUserEmail">Email</label>
                        <input class="form-control" type="text" name="currentUserEmail" id="currentUserEmail" value="<?= htmlspecialchars((string) $userRow['email'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Main Report Supervisor</label>
                        <input class="form-control" type="text" value="<?= htmlspecialchars($mainSupName, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Second Report Supervisor</label>
                        <input class="form-control" type="text" value="<?= htmlspecialchars($secondSupName, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>

                    <div class="form-group mb-4">
                        <a class="btn btn-sm btn-rounded btn-primary" href="<?= $SITEURL ?>/changePassword.php">Change Password</a>
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
        var page = "<?= $pageTitle ?>";
        var action = "E";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
    </script>
</body>
</html>
