<?php
$pageTitle = 'Shopee Order Report';
$currentPagePin = 155;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/order_report_common.php';

$pageTitle = getPinGroupNameById($connect, $currentPagePin);
$reportView = orderReportBuildView($connect, $finance_connect, 'shopee', $pageTitle);
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
