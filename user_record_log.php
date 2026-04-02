<?php
$currentPagePin = 0;
$pageTitle = 'User Record Log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once 'include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once ROOT . '/include/user_record_log.php';
    urlHandleUserRecordLogRequest($connect, $finance_connect, array(
        'table_name' => USER_RECORD_LOG,
        'page_title' => $pageTitle,
    ));
    exit;
}

include_once 'menuHeader.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
include_once ROOT . '/include/user_record_log.php';

$context = urlResolveUserRecordLogContext($connect, $finance_connect, array(
    'return_url' => $SITEURL . '/user_record_log.php' . (!empty($_GET['customer_id']) ? ('?customer_id=' . (int) $_GET['customer_id']) : ''),
    'ajax_url' => $SITEURL . '/user_record_log.php',
));

$pageHeading = $pageTitle;
if (!empty($context['customer_label'])) {
    $pageHeading .= ' - ' . $context['customer_label'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
<div class="page-load-cover" style="display: block !important;">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                        <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                    </div>
                </div>
            </div>
            <?php
            urlRenderUserRecordLogModule($connect, $finance_connect, array(
                'table_name' => USER_RECORD_LOG,
                'context' => $context,
                'section_heading' => '',
                'show_scope_note' => true,
            ));
            ?>
        </div>
    </div>
</div>
</body>
</html>