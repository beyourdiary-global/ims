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
        'Customer',
        'mdi mdi-account-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Customer Info', 'mdi mdi-information-outline', $SITEURL . '/customerInfoTable.php', '38'),
            array('Facebook Customer Record (Deals)', 'mdi mdi-deal-outline', $SITEURL . '/fb_cust_deals_table.php', '75'),
            array('Website Customer Record (Deals)', 'mdi mdi-deal-outline', $SITEURL . '/website_customer_record_table.php', '84'),
            array('Shopee Customer Record (Deals)', 'mdi mdi-deal-outline', $SITEURL . '/shopee/shopee_cust_info_table.php', '85'),
            array('Lazada Customer Record (Deals)', 'mdi mdi-deal-outline', $SITEURL . '/lazada_cust_rcd_table.php', '91'),
        ),
        'pin' => array('38', '75', '84', '85', '91')
    ),
    array(
        'Orders',
        'mdi mdi-cart-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Facebook Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/fb_order_req_table.php', '69'),
            array('Shopee Order Request', 'mdi mdi-cart', $userShopeeLink, $userShopeePin),
            array('Website Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/website_order_request_table.php', '92'),
            array('Lazada Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/lazada_order_req_table.php', '93'),
            array('Stock Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/stock_order_request_table.php', '126'),
            array('Order Process List', 'mdi mdi-note-text-outline', $SITEURL . '/finance/order_process_list.php', '119'),
        ),
        'pin' => array('69', '128', '129', '130', '92', '93', '126', '119')
    ),
    array(
        'Distributor',
        'mdi mdi-account-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Agent', 'mdi mdi-information-outline', $SITEURL . '/finance/agent_table.php', '62'),
        ),
        'pin' => array('62')
    ),
    array(
        'Product',
        'mdi mdi-package-variant',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Product', 'mdi mdi-package-variant', $SITEURL . '/product_table.php', '20'),
            array('Package', 'mdi mdi-package', $SITEURL . '/package_table.php', '21'),
        ),
        'pin' => array('20', '21')
    ),
    array(
        'Finance',
        'mdi mdi-finance',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array(
                'Accounting',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Merchant', 'mdi storefront-outline', $SITEURL . '/finance/merchant_table.php', '36'),
                    array('Credit Notes (Invoice)', 'mdi storefront-outline', $SITEURL . '/finance/cred_notes_inv_table.php', '70'),
                    array('Debit Notes (Invoice)', 'mdi storefront-outline', $SITEURL . '/finance/debit_notes_inv_table.php', '94'),
                ),
                'pin' => array('36', '70', '94'),
            ),
            array(
                'Assets and Liabilities List',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Current Bank Account Transaction', 'mdi storefront-outline', $SITEURL . '/finance/curr_bank_trans_table.php', '43'),
                    array('Investment Transaction', 'mdi storefront-outline', $SITEURL . '/finance/investment_trans_table.php', '40'),
                    array('Inventories Transaction', 'mdi storefront-outline', $SITEURL . '/finance/invtr_trans_table.php', '41'),
                    array('Sundry Debtors Transaction', 'mdi storefront-outline', $SITEURL . '/finance/sundry_debt_trans_table.php', '44'),
                    array('Other Creditor Transaction', 'mdi storefront-outline', $SITEURL . '/finance/other_creditor_trans_table.php', '45'),
                    array('Initial Capital Transaction', 'mdi storefront-outline', $SITEURL . '/finance/initial_capital_trans_table.php', '46'),
                    array('Cash On Hand Transaction', 'mdi storefront-outline', $SITEURL . '/finance/cash_on_hand_trans_table.php', '47'),
                    array('Monthly Bank Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/bank_trans_backup_table.php', '59'),

                ),
                'pin' => array('43', '40', '41', '44', '45', '46', '47', '59'),


            ),
            array(
                'Expense',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(

                ),
                'pin' => array(''),
            ),


            array(
                'Lazada',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(

                ),
                'pin' => array(''),
            ),
            array(
                'Record',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('J&T Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/j&t_trans_backup_table.php', '88'),
                ),
                'pin' => array('88'),
            ),
        ),

        'pin' => array('36', '70', '94', '43', '40', '41', '44', '45', '46', '47', '59', '88')

    ),
    array(
        'Report',
        'mdi mdi-note-text-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array(
                'Expense',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Facebook Ads Top Up Transaction', 'mdi storefront-outline', $SITEURL . '/finance/fb_ads_topup_trans_table.php', '50'),
                    array('Delivery Fees Claim Record', 'mdi storefront-outline', $SITEURL . '/finance/del_fees_claim_table.php', '66'),
                    array('Shopee Ads Top Up Transaction', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_ads_topup_trans_table.php', '77'),
                    array('Internal Consume Item', 'mdi storefront-outline', $SITEURL . '/finance/internal_consume_item_table.php', '67'),
                    array('Internal Consume Ticket/Credit', 'mdi storefront-outline', $SITEURL . '/finance/internal_consume_ticket_credit_table.php', '65'),
                    array('Stock Credit Top Up Record', 'mdi storefront-outline', $SITEURL . '/finance/stock_credit_top_up_request_table.php', '78'),
                ),
                'pin' => array('50', '66', '77', '67', '65', '78'),
            ),
            array(
                'Income',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Shopee Withdrawal Transactions', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_withdrawal_transactions_table.php', '51'),
                    array('Merchant Commission Record', 'mdi storefront-outline', $SITEURL . '/finance/merchant_comm_record_table.php', '61'),
                    array('Downline Top Up Record', 'mdi storefront-outline', $SITEURL . '/finance/downline_top_up_record_table.php', '68'),
                    array('Stripe Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/stripe_trans_backup_table.php', '89'),
                    array('Atome Transaction Backup Record', 'mdi storefront-outline', $SITEURL . '/finance/atome_trans_backup_table.php', '87'),
                    array('Facebook Order Request', 'mdi storefront-outline', $SITEURL . '/finance/fb_order_req_income_table.php', '69'),
                    array('Shopee Order Report', 'mdi storefront-outline', $SITEURL . '/shopee/shopeeOrder_request_income.php', '123'),
                    array('Website Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/website_order_request_income_table.php', '92'),
                    array('Lazada Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/lazadaOrder_request_income.php', '93'),

                ),
                'pin' => array('51', '61', '68', '89', '87', '69', '123', '92', '93'),
            ),
            array(
                'Group by',
                'mdi mdi-finance',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Sales Person Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/sales_person_report_table.php', '100'),
                    array('Payment Method Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/payment_method_report_table.php', '101'),
                    array('Package Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/package_report_table.php', '102'),
                    array('Brand Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/brand_report_table.php', '103'),
                    array('Stock Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/stock_report.php', '105'),
                ),
                'pin' => array('100', '101', '102', '103', '105'),
            ),
        ),
        'pin' => array('50', '66', '77', '67', '65', '78', '51', '61', '68', '89', '87', '69', '123', '92', '93', '100', '101', '102', '103', '105')
    ),
    array(
        'Other',
        'mdi mdi-dots-horizontal',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Barcode Generator', 'mdi mdi-barcode', $SITEURL . '/barcode_generator.php', '22'),
            array('Rate Checking', 'mdi mdi-package-variant', $SITEURL . '/rate_checking.php', '17'),
            array('SQL Account', 'mdi mdi-database', $SITEURL . '/sql_account_table.php', '132'),
            array('Theme Setting', 'mdi mdi-brush-variant', $SITEURL . '/theme_setting.php', '23'),
            array('System Setting', 'mdi mdi-brush-variant', $SITEURL . '/system_setting.php', '39'),

        ),
        'pin' => array('22', '17', '132', '23', '39')
    ),
    array(
        'Settings',
        'mdi mdi-cog',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array(
                'User Management',
                'mdi mdi-folder-account',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Pin', 'mdi mdi-pin', $SITEURL . '/pin_table.php', '1'),
                    array('Pin Group', 'mdi mdi-ungroup', $SITEURL . '/pin_group_table.php', '2'),
                    array('User', 'mdi mdi-account-wrench-outline', $SITEURL . '/user_table.php', '90'),
                    array('User Group', 'mdi mdi-account-wrench-outline', $SITEURL . '/user_group_table.php', '3'),

                ),
                'pin' => array('1', '2', '90', '3'),

            ),
            array(
                'User Administration Setting',
                'mdi mdi-account-key',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Bank', 'mdi mdi-bank', 'bank_table.php', '8'),
                    array('Currencies', 'mdi mdi-swap-horizontal', $SITEURL . '/currencies_table.php', '11'),
                    array('Currency Unit', 'mdi mdi-currency-usd', $SITEURL . '/currency_unit_table.php', '10'),
                    array('Platform', 'mdi mdi-home-outline', $SITEURL . '/platform_table.php', '14'),
                    array('Warehouse', 'mdi mdi-warehouse', $SITEURL . '/warehouse.php', '16'),
                    array('Weight Unit', 'mdi mdi-weight', $SITEURL . '/weight_unit_table.php', '19'),
                    array('Change Password', 'mdi mdi-key-change', $SITEURL . '/changePassword.php', '25'),
                    array('Chanel (Social Media)', 'mdi storefront-outline', $SITEURL . '/finance/chanel_social_media_table.php', '79'),
                ),
                'pin' => array('8', '11', '10', '14', '16', '19', '25', '79'),
            ),
            array(
                'Product Administration Setting',
                'mdi mdi-archive-settings-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Product Status', 'mdi mdi-package-variant-closed', $SITEURL . '/prod_status_table.php', '15'),
                    array('Brand', 'mdi mdi-label-outline', $SITEURL . '/brand_table.php', '9'),
                    array('Company', 'mdi mdi-office-building-outline', $SITEURL . '/company_table.php', '127'),
                    array('Purchase Order', 'mdi mdi-file-document-outline', $SITEURL . '/purchase_order_table.php', '135'),
                    array('Courier Account', 'mdi mdi-label-outline', $SITEURL . '/courier_table.php', '53'),
                    array('Category', 'mdi mdi-label-outline', $SITEURL . '/product_category_table.php', '56'),
                    array('Brand Series', 'mdi mdi-label-outline', $SITEURL . '/brand_series_table.php', '74'),
                ),

                'pin' => array('15', '9', '127', '135', '53', '56', '74'),

            ),
            array(
                'Employee Administration Setting',
                'mdi mdi-account-wrench-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Employment Type Status', 'mdi mdi-account-question-outline', $SITEURL . '/em_type_status_table.php', '12'),
                    array('Marital Status', 'mdi mdi-account-heart-outline', $SITEURL . '/marital_status_table.php', '13'),
                    array('Holidays', 'mdi mdi-calendar-star', $SITEURL . '/holiday_table.php', '6'),
                    array('Leave Type', 'mdi mdi-run-fast', $SITEURL . '/leave_type_table.php', '24'),
                    array('Identity Type', 'mdi mdi-book-search-outline', $SITEURL . '/identityTypeTable.php', '26'),
                    array('Leave Status', 'mdi mdi-run-fast', $SITEURL . '/leave_status_table.php', '27'),
                    array('Race', 'mdi mdi-account-star-outline', $SITEURL . '/race_table.php', '28'),
                    array('Socso Category', 'mdi mdi-google-fit', $SITEURL . '/socso_category_table.php', '30'),
                    array('Employer EPF Rate', 'mdi mdi-account-star-outline', $SITEURL . '/employer_epf_rate_table.php', '32'),
                    array('Employee EPF Rate', 'mdi mdi-account-supervisor', $SITEURL . '/employee_epf_rate_table.php', '31'),

                ),
                'pin' => array('12', '13', '6', '24', '26', '27', '28', '30', '31', '32'),
            ),
            array(
                'Customer Administration Setting',
                'mdi mdi-account-wrench-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Customer Segmentation', 'mdi mdi-account-group-outline', $SITEURL . '/cus_segmentation_table.php', '29'),
                    array('Tag', 'mdi mdi-account-group-outline', $SITEURL . '/tagTable.php', '35'),
                ),
                'pin' => array('29', '35'),
            ),
            array(
                'Payroll Administration  Setting',
                'mdi mdi-cash-multiple',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Payment Method', 'mdi mdi-contactless-payment-circle', $SITEURL . '/payment_method_table.php', '33'),
                    array('Tax Setting', 'mdi mdi-contactless-payment-circle', $SITEURL . '/finance/tax_table.php', '57'),
                ),
                'pin' => array('33', '57'),
            ),
            array(
                'Finance Administration  Setting',
                'mdi mdi-account-wrench-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Expense Type', 'mdimdi-account-wrench-outline', $SITEURL . '/finance/expense_type_table.php', '49'),
                    array('Payment Method (Finance)', 'mdimdi-account-wrench-outline', $SITEURL . '/finance/fin_payment_method_table.php', '60'),
                    array('Payment Terms', 'mdimdi-account-wrench-outline', $SITEURL . '/finance/payment_terms_table.php', '63'),
                ),
                'pin' => array('49', '60', '63'),
            ),

            array(
                'Social Administration  Setting',
                'mdi mdi-account-wrench-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Facebook Page Account', 'mdimdi-account-wrench-outline', $SITEURL . '/finance/fb_page_acc_table.php', '76'),
                    array('Meta Ads Account', 'mdi storefront-outline', $SITEURL . '/finance/meta_ads_acc_table.php', '48'),


                ),
                'pin' => array('48', '76'),
            ),

            array(
                'Order Administration  Setting',
                'mdi mdi-cash-multiple',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Payment Method (Shopee)', 'mdi mdi-contactless-payment-circle', $SITEURL . '/shopee/payment_method_shopee_table.php', '80'),
                    array('Shopee SG Setting', 'mdi mdi-contactless-payment-circle', $SITEURL . '/shopee/shopee_sg_setting_table.php', '82'),
                    array('Shopee Service Charges Rate Setting', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_service_charges_rate_setting_table.php', '83'),
                    array('Shopee Account Management', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_acc_table.php', '58'),
                    array('Lazada Account Management', 'mdi storefront-outline', $SITEURL . '/finance/lazada_acc_table.php', '81'),
                    array('Goal Target', 'mdi mdi-contactless-payment-circle', $SITEURL . '/goalTarget_table.php', '121'),
                ),
                'pin' => array('80', '82', '83', '58', '81', '121'),
            ),
            array('Token Setting', 'mdi mdi-key-chain', $SITEURL . '/token_setting_table.php', '133'),
        ),
        'pin' => array('1', '2', '90', '3', '8', '11', '10', '14', '16', '19', '25', '79', '15', '9', '127', '135', '53', '56', '74', '12', '13', '6', '24', '26', '27', '28', '30', '31', '32', '29', '35', '33', '57', '49', '60', '63', '48', '76', '80', '82', '83', '58', '81', '121', '133')
    ),
    array(
        'Warehouse',
        'mdi mdi-package-variant',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Warehouse', 'mdi mdi-warehouse', $SITEURL . '/warehouse_table.php', '16'),
            array('Stock In', 'mdi mdi-tray-arrow-down', $SITEURL . '/warehouse_stock_in_table.php', '125'),
            array('Stock List', 'mdi mdi-package-variant', $SITEURL . '/stock_list_table.php', '120'),
            array('Stock Costing Setting', 'mdi mdi-package-variant', $SITEURL . '/stockCosting.php?act=I', '106'),

        ),
        'pin' => array('16', '120','125', '106')
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
        'User Record Log',
        'mdi mdi-text-box-outline',
        $SITEURL . '/user_record_log.php',
        'n',
        'expand' => array(),
        'pin' => array('136')
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
    <link rel="stylesheet" href="<?php $SITEURL . '/css/main.css' ?>">
</head>

<style>
    @media (max-width: 768px) {
        #navbarMenuBar {
            display: none;
            color: #FFFFFF;
        }
    }
</style>

<script>

</script>

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
                    $li = $innerList[3] == 'y' ? "class=\"nav-item dropdown\"" : "class=\"nav-item\"";
                    $a = $innerList[3] == 'y' ? "class=\"nav-link dropdown-toggle\" data-bs-toggle=\"dropdown\" role=\"button\" aria-expanded=\"false\"" : "class=\"nav-link\"";

                    echo "<li $li>";
                    echo "<a $a href=\"$innerList[2]\"><i class=\"$innerList[1]\"></i><span> $innerList[0]</span></a>";
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
                                echo "<li><a class=\"dropdown-item\" href=\"$url[2]\">$url[0]</a></li>";
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
                        $li = $innerList[3] == 'y' ? "class=\"nav-item dropdown\"" : "class=\"nav-item\"";
                        $a = $innerList[3] == 'y' ? "class=\"nav-link dropdown-toggle\" data-bs-toggle=\"collapse\" data-bs-target=\"#$innerList[0]-collapse\" aria-expanded=\"false\"" : "class=\"nav-link\" href=\"$innerList[2]\"";

                        echo "<li $li>";
                        echo "<a $a href=\"#\"><i class=\"$innerList[1]\"></i><span> $innerList[0]</span></a>";
                        echo "<div class=\"collapse\" id=\"$innerList[0]-collapse\">";
                        echo "<ul class=\"list-unstyled collapse-menu\">";
                        foreach ($innerList['expand'] as $url) {
                            if (isset($url['expand'])) {
                                if (!empty(array_intersect($url['pin'], GlobalPin))) {
                                    $idCollapse = str_replace(" ", "-", $url[0]);

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
                                    echo "<li><a class=\"nav-link\" href=\"$url[2]\"><i class=\"$url[1]\"></i><span> $url[0]<span></a></li>";
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

<script>
    var sidebar = $("#sidebar");
    var sidebar_toggleBtn = $("#sidebarCollapse"); // variable from menuHeader
    var opacityBackground = $('div#filter_screen');

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