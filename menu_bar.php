<?php

$userID = USER_ID;

if (empty($userID)) {
    echo '<script>location.href = "' . $SITEURL . '/index.php";</script>';
    exit();
}

function getMenuTitleByPinGroupId($connect, $fallbackTitle, $pinGroupId)
{
    $pinGroupId = (int) $pinGroupId;
    if ($pinGroupId <= 0) {
        return $fallbackTitle;
    }

    $resolvedTitle = getPinGroupNameById($connect, $pinGroupId);
    return $resolvedTitle !== '' ? $resolvedTitle : $fallbackTitle;
}

function parseUserGroupPinMapFromString($rawPins)
{
    $groupPinMap = array();
    $entries = explode('+', (string) $rawPins);

    foreach ($entries as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        $parts = explode(':', trim($entry, '[]'));
        if (count($parts) !== 2) {
            continue;
        }

        $groupId = trim((string) $parts[0]);
        if ($groupId === '' || !ctype_digit($groupId)) {
            continue;
        }

        $pinIds = array();
        foreach (explode(',', (string) $parts[1]) as $pinId) {
            $pinId = trim((string) $pinId);
            if ($pinId !== '' && ctype_digit($pinId)) {
                $pinIds[] = $pinId;
            }
        }

        $groupPinMap[$groupId] = array_values(array_unique($pinIds));
    }

    return $groupPinMap;
}

function getUserAccessIdForMenu($connect, $userId)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return 0;
    }

    $result = getData('access_id', "id = '$userId'", 'LIMIT 1', 'user', $connect);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return isset($row['access_id']) ? (int) $row['access_id'] : 0;
    }

    return 0;
}

function getImportPinIdsForMenu($connect)
{
    $importPinIds = array();
    $result = getData('id,name', "LOWER(name) = 'import'", '', PIN, $connect);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pinId = isset($row['id']) ? trim((string) $row['id']) : '';
            if ($pinId !== '' && ctype_digit($pinId)) {
                $importPinIds[] = $pinId;
            }
        }
    }

    return array_values(array_unique($importPinIds));
}

function getPinGroupAllowedPinMapForMenu($connect, $pinGroupIds)
{
    $allowedMap = array();
    $validGroupIds = array();

    foreach ((array) $pinGroupIds as $pinGroupId) {
        $pinGroupId = trim((string) $pinGroupId);
        if ($pinGroupId !== '' && ctype_digit($pinGroupId)) {
            $validGroupIds[] = $pinGroupId;
        }
    }

    $validGroupIds = array_values(array_unique($validGroupIds));
    if (empty($validGroupIds)) {
        return $allowedMap;
    }

    $result = getData('id,pins', "id IN (" . implode(',', $validGroupIds) . ")", '', PIN_GRP, $connect);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groupId = isset($row['id']) ? trim((string) $row['id']) : '';
            if ($groupId === '' || !ctype_digit($groupId)) {
                continue;
            }

            $pins = array();
            foreach (explode(',', (string) $row['pins']) as $pinId) {
                $pinId = trim((string) $pinId);
                if ($pinId !== '' && ctype_digit($pinId)) {
                    $pins[] = $pinId;
                }
            }

            $allowedMap[$groupId] = array_values(array_unique($pins));
        }
    }

    return $allowedMap;
}

function hasImportActionForPinGroupForMenu($pinGroupId, $userPinGroupMap, $pinGroupAllowedPinMap, $importPinIds)
{
    $pinGroupId = trim((string) $pinGroupId);
    if ($pinGroupId === '' || !ctype_digit($pinGroupId)) {
        return false;
    }

    if (empty($importPinIds)) {
        return false;
    }

    $userAllowedPinIds = isset($userPinGroupMap[$pinGroupId]) ? $userPinGroupMap[$pinGroupId] : array();
    $groupAllowedPinIds = isset($pinGroupAllowedPinMap[$pinGroupId]) ? $pinGroupAllowedPinMap[$pinGroupId] : array();
    if (empty($userAllowedPinIds) || empty($groupAllowedPinIds)) {
        return false;
    }

    $effectivePinIds = array_intersect($userAllowedPinIds, $groupAllowedPinIds);
    return !empty(array_intersect($effectivePinIds, $importPinIds));
}

$hasAnyImportAccess = false;
$userAccessId = getUserAccessIdForMenu($connect, $userID);
$userPinGroupMap = array();
if ($userAccessId > 0) {
    $userGroupResult = getData('pins', "id = '$userAccessId'", 'LIMIT 1', 'user_group', $connect);
    if ($userGroupResult && $userGroupResult->num_rows > 0) {
        $userGroupRow = $userGroupResult->fetch_assoc();
        $userPinGroupMap = parseUserGroupPinMapFromString(isset($userGroupRow['pins']) ? $userGroupRow['pins'] : '');
    }
}

$importPinIds = getImportPinIdsForMenu($connect);
$pinGroupAllowedPinMap = getPinGroupAllowedPinMapForMenu($connect, is_array(GlobalPin) ? GlobalPin : array());

if (!empty($importPinIds)) {
    foreach (array(77, 50, 21, 125, 20, 126, 88, 127, 135) as $importCardPinGroupId) {
        if (hasImportActionForPinGroupForMenu($importCardPinGroupId, $userPinGroupMap, $pinGroupAllowedPinMap, $importPinIds)) {
            $hasAnyImportAccess = true;
            break;
        }
    }

    if (!$hasAnyImportAccess) {
        foreach (array(130, 129, 128) as $shopeeImportPinGroupId) {
            if (hasImportActionForPinGroupForMenu($shopeeImportPinGroupId, $userPinGroupMap, $pinGroupAllowedPinMap, $importPinIds)) {
                $hasAnyImportAccess = true;
                break;
            }
        }
    }
}

$importShortcutVisiblePins = array();
if ($hasAnyImportAccess && is_array(GlobalPin)) {
    foreach (GlobalPin as $pinGroupId) {
        $pinGroupId = trim((string) $pinGroupId);
        if ($pinGroupId !== '') {
            $importShortcutVisiblePins[] = $pinGroupId;
        }
    }
}

$importShortcutVisiblePins = array_values(array_unique($importShortcutVisiblePins));

// Find the logged-in user's highest Shopee access level.
$userShopeePin = '999999'; // Default to hide if no access
$userShopeeLink = 'javascript:void(0)';
if (in_array('130', GlobalPin)) {
    $userShopeePin = '130';
    $userShopeeLink = $SITEURL . '/shopee/shopee_order_req_table.php';
} else if (in_array('129', GlobalPin)) {
    $userShopeePin = '129';
    $userShopeeLink = $SITEURL . '/shopee/shopee_verify.php';
} else if (in_array('128', GlobalPin)) {
    $userShopeePin = '128';
    $userShopeeLink = $SITEURL . '/shopee/shopee_processing_order.php';
}

$hasTaskSummaryAccess = is_array(GlobalPin) && in_array('137', GlobalPin);
$hasTaskBoardAccess = is_array(GlobalPin) && in_array('136', GlobalPin);
$hasTaskSheetsAccess = is_array(GlobalPin) && in_array('138', GlobalPin);
$hasTaskManagementAccess = $hasTaskSummaryAccess || $hasTaskBoardAccess || $hasTaskSheetsAccess;
$taskManagementLandingUrl = $hasTaskSummaryAccess
    ? $SITEURL . '/task/summary.php'
    : ($hasTaskBoardAccess
        ? $SITEURL . '/task/board.php'
        : ($hasTaskSheetsAccess ? $SITEURL . '/task/sheets.php' : 'javascript:void(0)'));

$menuList = array(
    // dashboard
    array(
        'Dashboard',                    // pagename
        'mdi mdi-view-dashboard',       // icon class
        $SITEURL . '/dashboard.php',                // page
        'n',                            // check whether is a dropdown
        'expand' => array(),            // dropdown list item
        'pin' => array('0')             // action
    ),
    array(
        'Task Management',
        'mdi mdi-menu',
        $taskManagementLandingUrl,
        'n',
        'expand' => array(),
        'pin' => array('136', '137', '138')
    ),
    array(
        'Customer',
        'mdi mdi-account-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Shopee Customer', 'mdi mdi-deal-outline', $SITEURL . '/shopee/shopee_cust_info_table.php', '85'),
            array('Lazada Customer', 'mdi mdi-deal-outline', $SITEURL . '/lazada_cust_rcd_table.php', '91'),
            array('Facebook Customer', 'mdi mdi-deal-outline', $SITEURL . '/fb_cust_deals_table.php', '75'),
            array('Website Customer', 'mdi mdi-deal-outline', $SITEURL . '/website_customer_record_table.php', '84'),
            array('Whatsapp Customer', 'mdi mdi-deal-outline', $SITEURL . '/customerInfoTable.php', '38'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Customer Segmentation', 'mdi mdi-account-group-outline', $SITEURL . '/cus_segmentation_table.php', '29'),
                    array('Tag', 'mdi mdi-account-group-outline', $SITEURL . '/tagTable.php', '35'),
                ),
                'pin' => array('29', '35'),
            ),
        ),
        'pin' => array('85', '91', '75', '84', '38', '29', '35')
    ),
    array(
        'Order',
        'mdi mdi-cart-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Shopee Order', 'mdi mdi-cart', $userShopeeLink, $userShopeePin),
            array('Lazada Order', 'mdi mdi-note-text-outline', $SITEURL . '/lazada_order_req_table.php', '93'),
            array('Facebook Order', 'mdi mdi-note-text-outline', $SITEURL . '/finance/fb_order_req_table.php', '69'),
            array('Website Order', 'mdi mdi-note-text-outline', $SITEURL . '/finance/website_order_request_table.php', '92'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Shopee Account', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_acc_table.php', '58'),
                    array('Payment Method (Order)', 'mdi mdi-contactless-payment-circle', $SITEURL . '/shopee/payment_method_shopee_table.php', '80'),
                    array('Shopee SG Setting', 'mdi mdi-contactless-payment-circle', $SITEURL . '/shopee/shopee_sg_setting_table.php', '82'),
                    array('Shopee Service Charges Rate Setting', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_service_charges_rate_setting_table.php', '83'),
                ),
                'pin' => array('58', '80', '82', '83'),
            ),
        ),
        'pin' => array('128', '129', '130', '93', '69', '92', '58', '80', '82', '83')
    ),
    array(
        'Warehouse',
        'mdi mdi-package-variant',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Warehouse', 'mdi mdi-warehouse', $SITEURL . '/warehouse_table.php', '16'),
            array('Stock Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/stock_order_request_table.php', '126'),
            array('Stock In', 'mdi mdi-tray-arrow-down', $SITEURL . '/warehouse_stock_in_table.php', '125'),
            array('Stock List', 'mdi mdi-package-variant', $SITEURL . '/stock_list_table.php', '120'),
            array('Barcode Generate', 'mdi mdi-barcode', $SITEURL . '/barcode_generator.php', '22'),
            array('Rate Checking', 'mdi mdi-package-variant', $SITEURL . '/rate_checking.php', '17'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Courier', 'mdi mdi-label-outline', $SITEURL . '/courier_table.php', '53'),
                ),
                'pin' => array('53'),
            ),
        ),
        'pin' => array('16', '126', '125', '120', '22', '17', '53')
    ),
    array(
        'Product',
        'mdi mdi-package-variant',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Product', 'mdi mdi-package-variant', $SITEURL . '/product_table.php', '20'),
            array('Package', 'mdi mdi-package', $SITEURL . '/package_table.php', '21'),
            array('Product Status', 'mdi mdi-package-variant-closed', $SITEURL . '/prod_status_table.php', '15'),
            array('Brand', 'mdi mdi-label-outline', $SITEURL . '/brand_table.php', '9'),
            array('Category', 'mdi mdi-label-outline', $SITEURL . '/product_category_table.php', '56'),
            array('Brand Series', 'mdi mdi-label-outline', $SITEURL . '/brand_series_table.php', '74'),
        ),
        'pin' => array('20', '21', '15', '9', '56', '74')
    ),
    array(
        'Expense',
        'mdi mdi-finance',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Shopee Ads Top Up Transaction', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_ads_topup_trans_table.php', '77'),
            array('Facebook Ads Top Up', 'mdi storefront-outline', $SITEURL . '/finance/fb_ads_topup_trans_table.php', '50'),
            array('Delivery Fees Claim Record', 'mdi storefront-outline', $SITEURL . '/finance/del_fees_claim_table.php', '66'),
            array('Internal Consume Ticket/Credit', 'mdi storefront-outline', $SITEURL . '/finance/internal_consume_ticket_credit_table.php', '65'),
            array('Stock Credit Top Up Record', 'mdi storefront-outline', $SITEURL . '/finance/stock_credit_top_up_request_table.php', '78'),
            array('J&T Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/j&t_trans_backup_table.php', '88'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Facebook Page Account', 'mdi mdi-account-wrench-outline', $SITEURL . '/finance/fb_page_acc_table.php', '76'),
                    array('Meta Ads Account', 'mdi storefront-outline', $SITEURL . '/finance/meta_ads_acc_table.php', '48'),
                ),
                'pin' => array('48', '76'),
            ),
        ),
        'pin' => array('77', '50', '66', '65', '78', '88', '48', '76')
    ),
    array(
        'Income',
        'mdi mdi-finance',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Shopee Withdrawal Transactions', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_withdrawal_transactions_table.php', '51'),
            array('Merchant Commission Record', 'mdi storefront-outline', $SITEURL . '/finance/merchant_comm_record_table.php', '61'),
            array('Stripe Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/stripe_trans_backup_table.php', '89'),
            array('Atome Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/atome_trans_backup_table.php', '87'),
        ),
        'pin' => array('51', '61', '89', '87')
    ),
    array(
        'Accounting',
        'mdi mdi-finance',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Purchase Order', 'mdi mdi-file-document-outline', $SITEURL . '/purchase_order_table.php', '135'),
            array('Credit Notes (Invoice)', 'mdi storefront-outline', $SITEURL . '/finance/cred_notes_inv_table.php', '70'),
            array('Debit Notes (Invoice)', 'mdi storefront-outline', $SITEURL . '/finance/debit_notes_inv_table.php', '94'),
            array('Current Bank Account Transaction', 'mdi storefront-outline', $SITEURL . '/finance/curr_bank_trans_table.php', '43'),
            array('Investment Transaction', 'mdi storefront-outline', $SITEURL . '/finance/investment_trans_table.php', '40'),
            array('Inventories Transaction', 'mdi storefront-outline', $SITEURL . '/finance/invtr_trans_table.php', '41'),
            array('Sundry Debtors Transaction', 'mdi storefront-outline', $SITEURL . '/finance/sundry_debt_trans_table.php', '44'),
            array('Other Creditor Transaction', 'mdi storefront-outline', $SITEURL . '/finance/other_creditor_trans_table.php', '45'),
            array('Initial Capital Transaction', 'mdi storefront-outline', $SITEURL . '/finance/initial_capital_trans_table.php', '46'),
            array('Cash On Hand Transaction', 'mdi storefront-outline', $SITEURL . '/finance/cash_on_hand_trans_table.php', '47'),
            array('Monthly Bank Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/bank_trans_backup_table.php', '59'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Tax', 'mdi mdi-contactless-payment-circle', $SITEURL . '/finance/tax_table.php', '57'),
                    array('Expense Type', 'mdi mdi-account-wrench-outline', $SITEURL . '/finance/expense_type_table.php', '49'),
                    array('Payment Method (Finance)', 'mdi mdi-account-wrench-outline', $SITEURL . '/finance/fin_payment_method_table.php', '60'),
                    array('Payment Terms', 'mdi mdi-account-wrench-outline', $SITEURL . '/finance/payment_terms_table.php', '63'),
                ),
                'pin' => array('57', '49', '60', '63'),
            ),
        ),
        'pin' => array('135', '70', '94', '43', '40', '41', '44', '45', '46', '47', '59', '57', '49', '60', '63')
    ),
    array(
        'Report',
        'mdi mdi-note-text-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array(
                'Income',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Shopee Order Report', 'mdi storefront-outline', $SITEURL . '/shopee/shopeeOrder_request_income.php', '123'),
                    array('Facebook Order Request', 'mdi storefront-outline', $SITEURL . '/finance/fb_order_req_income_table.php', '69'),
                    array('Website Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/website_order_request_income_table.php', '92'),
                    array('Lazada Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/lazada_order_req_income_table.php', '93'),
                    array('Sales Person Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/sales_person_report_table.php', '100'),
                ),
                'pin' => array('123', '69', '92', '93', '100'),
            ),
        ),
        'pin' => array('123', '69', '92', '93', '100')
    ),
    array(
        'Other',
        'mdi mdi-dots-horizontal',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Merchant', 'mdi storefront-outline', $SITEURL . '/finance/merchant_table.php', '36'),
            array('Agent', 'mdi mdi-information-outline', $SITEURL . '/finance/agent_table.php', '62'),
            array('Goal Target', 'mdi mdi-bullseye-arrow', $SITEURL . '/goalTarget_table.php', '121'),

        ),
        'pin' => array('36', '62', '121')
    ),
    array(
        'Setting',
        'mdi mdi-cog',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Sql Account', 'mdi mdi-database', $SITEURL . '/sql_account_table.php', '132'),
            array('Token Setting', 'mdi mdi-key-chain', $SITEURL . '/token_setting_table.php', '133'),
            array('Theme Setting', 'mdi mdi-brush-variant', $SITEURL . '/theme_setting.php', '23'),
            array('System Setting', 'mdi mdi-brush-variant', $SITEURL . '/system_setting.php', '39'),
            array(
                'User Management',
                'mdi mdi-folder-account',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('User', 'mdi mdi-account-wrench-outline', $SITEURL . '/user_table.php', '90'),
                    array('User Group', 'mdi mdi-account-wrench-outline', $SITEURL . '/user_group_table.php', '3'),
                    array('Pin', 'mdi mdi-pin', $SITEURL . '/pin_table.php', '1'),
                    array('Pin Group', 'mdi mdi-ungroup', $SITEURL . '/pin_group_table.php', '2'),

                ),
                'pin' => array('1', '2', '90', '3'),

            ),
            array(
                'Administration Setting',
                'mdi mdi-account-key',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Bank', 'mdi mdi-bank', 'bank_table.php', '8'),
                    array('Currencies', 'mdi mdi-swap-horizontal', $SITEURL . '/currencies_table.php', '11'),
                    array('Currency Unit', 'mdi mdi-currency-usd', $SITEURL . '/currency_unit_table.php', '10'),
                    array('Platform', 'mdi mdi-home-outline', $SITEURL . '/platform_table.php', '14'),
                    array('Weight Unit', 'mdi mdi-weight', $SITEURL . '/weight_unit_table.php', '19'),
                    array('Chanel (Social Media)', 'mdi storefront-outline', $SITEURL . '/finance/chanel_social_media_table.php', '79'),
                    array('Company', 'mdi mdi-office-building-outline', $SITEURL . '/company_table.php', '127'),
                ),
                'pin' => array('8', '11', '10', '14', '19', '79', '127'),
            ),
        ),
        'pin' => array('132', '133', '23', '39', '1', '2', '90', '3', '8', '11', '10', '14', '19', '79', '127')
    ),
    array(
        'Import Shortcut',
        'mdi mdi-file-import-outline',
        $SITEURL . '/common_import.php',
        'n',
        'expand' => array(),
        'pin' => $importShortcutVisiblePins
    ),
    array(
        'Audit Log',
        'mdi mdi-text-box-search-outline',
        $SITEURL . '/audit_log.php',
        'n',
        'expand' => array(),
        'pin' => array('18')
    ),
);

?>


<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/sidebar_menu.css">
</head>


<!-- H.Navbar -->
<nav class="menuBar">
    <!-- Container wrapper -->
    <div class="container-fluid">
        <!-- Elements -->
        <ul class="nav nav-tabs">
            <?php
            /*
            if(!GlobalPin){
                echo "<script>location.href ='$SITEURL/index.php';</script>";
            }
            */
            foreach ($menuList as $innerList) {
                if (!empty(array_intersect($innerList['pin'], GlobalPin))) {
                    $isTaskTopMenu = $innerList[0] === 'Task Management';
                    $li = $innerList[3] == 'y' ? "class=\"nav-item dropdown\"" : "class=\"nav-item\"";
                    $linkClass = $innerList[3] == 'y' ? 'nav-link dropdown-toggle' : 'nav-link';
                    if ($isTaskTopMenu) {
                        $linkClass .= ' task-top-menu-trigger';
                    }
                    $taskAriaLabel = $isTaskTopMenu ? ' aria-label="Task Management" title="Task Management"' : '';
                    $a = $innerList[3] == 'y'
                        ? "class=\"" . $linkClass . "\" data-bs-toggle=\"dropdown\" role=\"button\" aria-expanded=\"false\"" . $taskAriaLabel
                        : "class=\"" . $linkClass . "\"" . $taskAriaLabel;

                    echo "<li $li>";
                    echo "<a $a href=\"$innerList[2]\"><i class=\"$innerList[1]\"></i>";
                    if (!$isTaskTopMenu) {
                        echo "<span> $innerList[0]</span>";
                    } else {
                        echo "<span class=\"visually-hidden\">$innerList[0]</span>";
                    }
                    echo "</a>";
                    echo "<ul class=\"dropdown-menu menuBar\">";
                    foreach ($innerList['expand'] as $url) {
                        if (isset($url['expand'])) {
                            if (!empty(array_intersect($url['pin'], GlobalPin))) {
                                echo "<li>";
                                echo "<a class=\"dropdown-item dropdown-toggle\" href=\"$url[2]\"><span> $url[0]</span></a>";
                                echo "<ul class=\"dropdown-menu dropdown-submenu menuBar\">";

                                foreach ($url['expand'] as $url2) {
                                    if (in_array($url2[3], GlobalPin)) {
                                        $url2Title = getMenuTitleByPinGroupId($connect, $url2[0], $url2[3]);
                                        echo "<li><a class=\"dropdown-item\" href=\"$url2[2]\">$url2Title</a></li>";
                                    }
                                }

                                echo "</ul>";
                                echo "</li>";
                            }
                        } else {
                            if (in_array($url[3], GlobalPin)) {
                                $urlTitle = getMenuTitleByPinGroupId($connect, $url[0], $url[3]);
                                echo "<li><a class=\"dropdown-item\" href=\"$url[2]\">$urlTitle</a></li>";
                            }
                        }
                    }
                    echo "</ul>";
                    echo "</li>";
                }
            }
            ?>
        </ul>
        <!-- Elements -->
    </div>
    <!-- Container wrapper -->
</nav>
<!-- H.Navbar -->

<!-- V.Navbar -->
<aside>
    <nav class="sidebar-nav" id="sidebar">
        <!-- Container wrapper -->
        <div class="container-fluid">
            <!-- Elements -->
            <ul class="nav nav-tabs">
                <?php
                foreach ($menuList as $innerList) {
                    if (!empty(array_intersect($innerList['pin'], GlobalPin))) {
                        $isTaskManagementMenu = $innerList[0] === 'Task Management';
                        $isDropdownMenu = $innerList[3] == 'y' || $isTaskManagementMenu;
                        $collapseId = $isTaskManagementMenu ? 'Task-Management' : $innerList[0];
                        $li = $isDropdownMenu ? "class=\"nav-item dropdown\"" : "class=\"nav-item\"";
                        $a = $isDropdownMenu
                            ? "class=\"nav-link dropdown-toggle\" data-bs-toggle=\"collapse\" data-bs-target=\"#$collapseId-collapse\" aria-expanded=\"false\""
                            : "class=\"nav-link\" href=\"$innerList[2]\"";

                        $expandMenus = $innerList['expand'];
                        if ($isTaskManagementMenu) {
                            $expandMenus = array(
                                array('Summary', 'mdi mdi-view-dashboard-outline', $SITEURL . '/task/summary.php', '137'),
                                array('Board', 'mdi mdi-view-column-outline', $SITEURL . '/task/board.php', '136'),
                                array('Sheets', 'mdi mdi-table-large', $SITEURL . '/task/sheets.php', '138'),
                            );
                        }

                        echo "<li $li>";
                        echo "<a $a href=\"#\"><i class=\"$innerList[1]\"></i><span> $innerList[0]</span></a>";
                        echo "<div class=\"collapse\" id=\"$collapseId-collapse\">";
                        echo "<ul class=\"list-unstyled collapse-menu\">";
                        foreach ($expandMenus as $url) {
                            if (isset($url['expand'])) {
                                if (!empty(array_intersect($url['pin'], GlobalPin))) {
                                    // FIX: Prefix with parent menu name to prevent duplicate IDs for menus like 'Setting'
                                    $idCollapse = str_replace(" ", "-", $innerList[0] . "-" . $url[0]);

                                    $li = $url[3] == 'y' ? "class=\"nav-item dropdown\"" : "class=\"nav-item\"";
                                    $a = $url[3] == 'y' ? "class=\"nav-link dropdown-toggle\" data-bs-toggle=\"collapse\" data-bs-target=\"#$idCollapse-collapse\" aria-expanded=\"false\"" : "class=\"nav-link\" href=\"$url[2]\"";

                                    echo "<li $li>";
                                    echo "<a $a href=\"#\"><i class=\"$url[1]\"></i><span> $url[0]</span></a>";
                                    echo "<div class=\"collapse\" id=\"$idCollapse-collapse\">";
                                    echo "<ul class=\"list-unstyled collapse-menu\">";

                                    foreach ($url['expand'] as $url2) {
                                        if (in_array($url2[3], GlobalPin)) {
                                            $url2Title = getMenuTitleByPinGroupId($connect, $url2[0], $url2[3]);
                                            echo "<li><a class=\"nav-link\" href=\"$url2[2]\"><i class=\"$url2[1]\"></i><span> $url2Title<span></a></li>";
                                        }
                                    }

                                    echo "</ul>";
                                    echo "</div>";
                                    echo "</li>";
                                }
                            } else {
                                if (in_array($url[3], GlobalPin)) {
                                    $urlTitle = $isTaskManagementMenu ? $url[0] : getMenuTitleByPinGroupId($connect, $url[0], $url[3]);
                                    echo "<li><a class=\"nav-link\" href=\"$url[2]\"><i class=\"$url[1]\"></i><span> $urlTitle<span></a></li>";
                                }
                            }

                            /* if(in_array($url[3], GlobalPin))
                            {
                                echo "<li><a class=\"nav-link\" href=\"$url[2]\"><i class=\"$url[1]\"></i><span> $url[0]<span></a></li>";
                            } */
                        }
                        echo "</ul>";
                        echo "</div>";
                        echo "</li>";
                    }
                }
                ?>
            </ul>
            <!-- Elements -->
        </div>
        <!-- Container wrapper -->
    </nav>
    <div id="filter_screen" class="filter_screen" style="display:none;">
    </div>
</aside>
<!-- V.Navbar -->

<?php if ($hasTaskManagementAccess): ?>
<?php
$taskCurrentPath = isset($_SERVER['REQUEST_URI']) ? (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
$isTaskSummaryPage = strpos($taskCurrentPath, '/task/summary.php') !== false;
$isTaskBoardPage = strpos($taskCurrentPath, '/task/board.php') !== false;
$isTaskSheetsPage = strpos($taskCurrentPath, '/task/sheets.php') !== false;
?>

<aside id="taskGlobalSidebar" class="task-global-sidebar" aria-hidden="true">
    <div class="task-global-sidebar-inner">
        <h6 class="task-global-sidebar-title">Task Management</h6>
        <ul class="task-global-sidebar-links">
            <?php if ($hasTaskSummaryAccess): ?>
            <li><a class="<?= $isTaskSummaryPage ? 'task-global-link-active' : '' ?>" href="<?= $SITEURL ?>/task/summary.php">Summary</a></li>
            <?php endif; ?>
            <?php if ($hasTaskBoardAccess): ?>
            <li><a class="<?= $isTaskBoardPage ? 'task-global-link-active' : '' ?>" href="<?= $SITEURL ?>/task/board.php">Board</a></li>
            <?php endif; ?>
            <?php if ($hasTaskSheetsAccess): ?>
            <li><a class="<?= $isTaskSheetsPage ? 'task-global-link-active' : '' ?>" href="<?= $SITEURL ?>/task/sheets.php">Sheets</a></li>
            <?php endif; ?>
        </ul>
    </div>
</aside>
<?php endif; ?>

<script>
    function isMobileViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    var sidebar = $("#sidebar");
    var sidebar_toggleBtn = $("#sidebarCollapse"); // variable from menuHeader
    var opacityBackground = $('div#filter_screen');
    var taskTopMenuTrigger = $('.task-top-menu-trigger');
    var taskGlobalSidebar = $('#taskGlobalSidebar');
    var taskSidebarStorageKey = 'task_global_sidebar_open';
    var hasTaskManagementAccess = <?php echo $hasTaskManagementAccess ? 'true' : 'false'; ?>;

    if (hasTaskManagementAccess && taskGlobalSidebar.length) {
        $('body').addClass('task-global-sidebar-enabled');
    }

    function syncTaskSidebarTopOffset() {
        if (!taskGlobalSidebar.length) {
            return;
        }

        var topNavHeight = $('.topNav').outerHeight() || 50;
        var menuBarHeight = $('.menuBar').outerHeight() || 0;
        var totalTop = topNavHeight + menuBarHeight;
        document.documentElement.style.setProperty('--task-global-sidebar-top', totalTop + 'px');
    }

    function setTaskGlobalSidebar(open) {
        if (!hasTaskManagementAccess || !taskGlobalSidebar.length || isMobileViewport()) {
            $('body').removeClass('task-global-sidebar-open');
            taskGlobalSidebar.attr('aria-hidden', 'true');
            return;
        }

        $('body').toggleClass('task-global-sidebar-open', open);
        taskGlobalSidebar.attr('aria-hidden', open ? 'false' : 'true');

        try {
            window.localStorage.setItem(taskSidebarStorageKey, open ? '1' : '0');
        } catch (e) {}
    }

    function expandTaskMenuInMobileSidebar() {
        var taskCollapse = document.getElementById('Task-Management-collapse');
        if (!taskCollapse || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
            return;
        }

        try {
            var instance = bootstrap.Collapse.getOrCreateInstance(taskCollapse, { toggle: false });
            instance.show();
        } catch (e) {}
    }

    syncTaskSidebarTopOffset();

    if (hasTaskManagementAccess) {
        try {
            setTaskGlobalSidebar(window.localStorage.getItem(taskSidebarStorageKey) === '1');
        } catch (e) {
            setTaskGlobalSidebar(false);
        }
    }

    if (hasTaskManagementAccess) {
        taskTopMenuTrigger.on('click', function (e) {
            if (isMobileViewport()) {
                e.preventDefault();
                if (!sidebar.hasClass('active')) {
                    sidebar.toggleClass('active', true);
                    sidebar.toggleClass('close', false);
                    opacityBackground.show();
                }
                expandTaskMenuInMobileSidebar();
                return;
            }

            e.preventDefault();
            setTaskGlobalSidebar(!$('body').hasClass('task-global-sidebar-open'));
        });
    }

        sidebar_toggleBtn.on("click", function () {
            if (sidebar.hasClass("active")) {
                sidebar.toggleClass("close", true);
                opacityBackground.hide();

                // timeout value based on .close css transition (0.3s)
                setTimeout(() => {
                    sidebar.removeClass('active');
                    sidebar.removeClass('close');
                }, 500);
            } else {
                sidebar.toggleClass("active", true);
                sidebar.toggleClass("close", false);
                if (isMobileViewport()) {
                    opacityBackground.show();
                } else {
                    opacityBackground.hide();
                }
            }
        });

        $(window).on('resize', function () {
            syncTaskSidebarTopOffset();
            if (!hasTaskManagementAccess) {
                $('body').removeClass('task-global-sidebar-open task-global-sidebar-enabled');
                return;
            }

            if (isMobileViewport()) {
                $('body').removeClass('task-global-sidebar-open');
                taskGlobalSidebar.attr('aria-hidden', 'true');
            } else {
                try {
                    setTaskGlobalSidebar(window.localStorage.getItem(taskSidebarStorageKey) === '1');
                } catch (e) {
                    setTaskGlobalSidebar(false);
                }
            }
        });

        opacityBackground.on('click', function (e) {
            var sidebar2 = $("#sidebar, #sidebarCollapse");
            if (!sidebar2.is(e.target) && sidebar2.has(e.target).length === 0) {
                sidebar.toggleClass('close', true);
                opacityBackground.hide();
                setTimeout(() => {
                    sidebar.removeClass('active');
                    sidebar.removeClass('close');
                }, 300);
            }
        });
</script>