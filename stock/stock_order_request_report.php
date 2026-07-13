<?php
$pageTitle = 'Stock Order Request Report';
$currentPagePin = 166;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/order_report_common.php';

$pageTitle = getPinGroupNameById($connect, $currentPagePin);
$reportView = orderReportBuildView($connect, $finance_connect, 'stock_order_request', $pageTitle);
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
