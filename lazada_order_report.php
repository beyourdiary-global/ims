<?php
$pageTitle = 'Lazada Order Report';
$currentPagePin = 158;

include_once __DIR__ . '/menuHeader.php';
include_once __DIR__ . '/checkCurrentPagePin.php';
include_once ROOT . '/include/order_report_common.php';

$pageTitle = getPinGroupNameById($connect, $currentPagePin);
$reportView = orderReportBuildView($connect, $finance_connect, 'lazada', $pageTitle);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
<?php orderReportRenderPage($reportView); ?>
</body>
</html>
