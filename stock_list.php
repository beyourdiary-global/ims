<?php
include_once 'include/connection.php';

$stockMovementViewOnly = true;
$currentPagePin = 120;
$stockMovementUsePinTitle = true;
$stockMovementRedirectTable = $SITEURL . '/stock_list_table.php';
$stockMovementBackButtonTitle = 'Back to Stock List';

include 'warehouse_stock_in.php';
