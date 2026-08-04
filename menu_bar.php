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
    foreach (array(77, 50, 21, 125, 20, 126, 88, 127, 135, 93, 92) as $importCardPinGroupId) {
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

include_once ROOT . '/task/common_task.php';

$hasTaskManagementAccess = taskIsActionAllowed('view', taskGetPinAccessByGroupId($connect, 139));
$taskManagementLandingUrl = $hasTaskManagementAccess
    ? $SITEURL . '/task/summary.php'
    : 'javascript:void(0)';

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
        'Project Task',
        'mdi mdi-menu',
        $taskManagementLandingUrl,
        'n',
        'expand' => array(),
        'pin' => array('139')
    ),
    array(
        'Customer',
        'mdi mdi-account-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Customer Dashboard', 'mdi mdi-chart-bar', $SITEURL . '/customer/customer_analysis_dashboard.php', '160'),
            array('Shopee Customer', 'mdi mdi-deal-outline', $SITEURL . '/shopee/shopee_cust_info_table.php', '85'),
            array('Lazada Customer', 'mdi mdi-deal-outline', $SITEURL . '/finance/lazada_cust_rcd_table.php', '91'),
            array('Facebook Customer', 'mdi mdi-deal-outline', $SITEURL . '/customer/fb_cust_deals_table.php', '75'),
            array('Website Customer', 'mdi mdi-deal-outline', $SITEURL . '/customer/website_customer_record_table.php', '84'),
            array('Whatsapp Customer', 'mdi mdi-deal-outline', $SITEURL . '/customer/customerInfoTable.php', '38'),
            array('Customer Follow-Up', 'mdi mdi-account-clock-outline', $SITEURL . '/customer/customer_follow_up_list.php', '151'),
            array('Daily Follow Up Report', 'mdi mdi-history', $SITEURL . '/customer/customer_daily_follow_up_report.php', '161'),
            array('Customer Daily Report', 'mdi mdi-file-chart-outline', $SITEURL . '/customer/customer_daily_report.php', '150'),
            array('Campaign', 'mdi mdi-bullhorn-outline', $SITEURL . '/campaign/campaign_table.php', '153'),
            array(
                'Lucky Draw',
                'mdi mdi-gift-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Lucky Draw Dashboard', 'mdi mdi-view-dashboard-outline', $SITEURL . '/lucky_draw/dashboard.php', '159', true),
                    array('Prize Management', 'mdi mdi-gift-outline', $SITEURL . '/lucky_draw/prizes_table.php', '159', true),
                    array('Lucky Draw Logs', 'mdi mdi-file-document-outline', $SITEURL . '/lucky_draw/logs.php', '159', true),
                    array('Virtual Board', 'mdi mdi-format-list-bulleted', $SITEURL . '/lucky_draw/virtual_board_table.php', '159', true),
                ),
                'pin' => array('159'),
            ),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Customer Segmentation', 'mdi mdi-account-group-outline', $SITEURL . '/customer/cus_segmentation_table.php', '29'),
                    array('Customer Level', 'mdi mdi-account-group-outline', $SITEURL . '/customer/cus_level_table.php', '142'),
                    array('Customer Repeat', 'mdi mdi-account-group-outline', $SITEURL . '/customer/cus_repeat_table.php', '143'),
                    array('Message Shortcuts', 'mdi mdi-message-text-outline', $SITEURL . '/settings/message_shortcuts_table.php', '144'),
                    array('Customize Bot Message', 'mdi mdi-message-text-outline', $SITEURL . '/settings/customize_bot_msg_table.php', '165'),
                    array('Tag', 'mdi mdi-account-group-outline', $SITEURL . '/customer/tagTable.php', '35'),
                    array('Campaign Rule Setting', 'mdi mdi-cog-outline', $SITEURL . '/campaign/campaign_rule_setting_table.php', '154'),
                    array('Member Bonus Management', 'mdi mdi-star-cog-outline', $SITEURL . '/settings/member_bonus_management.php', '164'),
                    array('Member Redeem Setting', 'mdi mdi-gift-outline', $SITEURL . '/settings/member_redeem_setting.php', '163'),
                ),
                'pin' => array('29', '142', '143', '144', '165', '35', '154', '164', '163'),
            ),
        ),
        'pin' => array('160', '159', '85', '91', '75', '84', '38', '150', '151', '153', '29', '142', '143', '144', '165', '35', '154', '164', '163')
    ),
    array(
        'Order',
        'mdi mdi-cart-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Shopee Order', 'mdi mdi-cart', $userShopeeLink, $userShopeePin),
            array('Lazada Order', 'mdi mdi-note-text-outline', $SITEURL . '/finance/lazada_order_req_table.php', '93'),
            array('Facebook Order', 'mdi mdi-note-text-outline', $SITEURL . '/finance/fb_order_req_table.php', '69'),
            array('Website Order', 'mdi mdi-note-text-outline', $SITEURL . '/finance/website_order_request_table.php', '92'),
            array('Waiting To Pack', 'mdi mdi-package-variant-closed', $SITEURL . '/finance/waiting_to_pack.php', '146'),
            array('Arrival Management', 'mdi mdi-truck-delivery-outline', $SITEURL . '/finance/arrival_management.php', '147'),
            array('Daily Flow Report', 'mdi mdi-chart-box-outline', $SITEURL . '/finance/flow_report.php', '148'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array_merge(array(
                    array('Shopee Account', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_acc_table.php', '58'),
                    array('Payment Method (Order)', 'mdi mdi-contactless-payment-circle', $SITEURL . '/shopee/payment_method_shopee_table.php', '80'),
                    array('Shopee SG Setting', 'mdi mdi-contactless-payment-circle', $SITEURL . '/shopee/shopee_sg_setting_table.php', '82'),
                    array('Shopee Service Charges Rate Setting', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_service_charges_rate_setting_table.php', '83'),
                ), ((int) USER_GROUP === 1 ? array(
                    array('Flow Setting', 'mdi mdi-cog-outline', $SITEURL . '/finance/flow_setting.php', '149'),
                ) : array())),
                'pin' => array('58', '80', '82', '83', '149'),
            ),
        ),
        'pin' => array('128', '129', '130', '146', '147', '148', '149', '93', '69', '92', '58', '80', '82', '83')
    ),
    array(
        'Warehouse',
        'mdi mdi-package-variant',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Warehouse', 'mdi mdi-warehouse', $SITEURL . '/stock/warehouse_table.php', '16'),
            array('Stock Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/stock/stock_order_request_table.php', '126'),
            array('Stock In', 'mdi mdi-tray-arrow-down', $SITEURL . '/stock/warehouse_stock_in_table.php', '125'),
            array('Stock List', 'mdi mdi-package-variant', $SITEURL . '/stock/stock_list_table.php', '120'),
            array('Order Warehouse Transfer', 'mdi mdi-swap-horizontal', $SITEURL . '/stock/order_warehouse_transfer.php', '152'),
            array('Barcode Generate', 'mdi mdi-barcode', $SITEURL . '/product/barcode_generator.php', '22'),
            array('Rate Checking', 'mdi mdi-package-variant', $SITEURL . '/stock/rate_checking.php', '17'),
            array(
                'Setting',
                'mdi mdi-cog',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Courier', 'mdi mdi-label-outline', $SITEURL . '/stock/courier_table.php', '53'),
                ),
                'pin' => array('53'),
            ),
        ),
        'pin' => array('16', '126', '125', '120', '152', '22', '17', '53')
    ),
    array(
        'Product',
        'mdi mdi-package-variant',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Product', 'mdi mdi-package-variant', $SITEURL . '/product/product_table.php', '20'),
            array('Package', 'mdi mdi-package', $SITEURL . '/product/package_table.php', '21'),
            array('Product Status', 'mdi mdi-package-variant-closed', $SITEURL . '/product/prod_status_table.php', '15'),
            array('Brand', 'mdi mdi-label-outline', $SITEURL . '/product/brand_table.php', '9'),
            array('Category', 'mdi mdi-label-outline', $SITEURL . '/product/product_category_table.php', '56'),
            array('Label', 'mdi mdi-label-outline', $SITEURL . '/customer/label_table.php', '145'),
            array('Brand Series', 'mdi mdi-label-outline', $SITEURL . '/product/brand_series_table.php', '74'),
        ),
        'pin' => array('20', '21', '15', '9', '56', '145', '74')
    ),
    array(
        'Expense',
        'mdi mdi-finance',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Shopee Ads Top Up Transaction', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_ads_topup_trans_table.php', '77'),
            array('Facebook Ads Top Up', 'mdi storefront-outline', $SITEURL . '/finance/fb_ads_topup_trans_table.php', '50'),
            array('FB-Ads WHT Submission', 'mdi mdi-file-document-check-outline', $SITEURL . '/finance/fb_ads_topup_wht_submission_table.php', '168'),
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
        'pin' => array('77', '50', '168', '66', '65', '78', '88', '48', '76')
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
        'Supplier',
        'mdi mdi-truck-delivery-outline',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Merchant', 'mdi storefront-outline', $SITEURL . '/finance/merchant_table.php', '36'),
            array('Supplier Invoice', 'mdi mdi-file-document-outline', $SITEURL . '/finance/supplier_invoice_table.php', '167'),
            array('Supplier Payment', 'mdi mdi-cash-check', $SITEURL . '/finance/supplier_payment_table.php', '169'),
        ),
        'pin' => array('36', '167', '169')
    ),
    array(
        'Accounting',
        'mdi mdi-finance',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Purchase Order', 'mdi mdi-file-document-outline', $SITEURL . '/stock/purchase_order_table.php', '135'),
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
                    array('Lazada Order Request', 'mdi mdi-note-text-outline', $SITEURL . '/finance/lazada_order_req_income_table.php', '93'),
                    array('Sales Person Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/sales_person_report_table.php', '100'),
                ),
                'pin' => array('123', '69', '92', '93', '100'),
            ),
            array(
                'Order',
                'mdi mdi-cart-outline',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Shopee Order Report', 'mdi storefront-outline', $SITEURL . '/shopee/shopee_order_report.php', '155'),
                    array('Facebook Order Report', 'mdi storefront-outline', $SITEURL . '/finance/facebook_order_report.php', '156'),
                    array('Website Order Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/website_order_report.php', '157'),
                    array('Lazada Order Report', 'mdi mdi-note-text-outline', $SITEURL . '/finance/lazada_order_report.php', '158'),
                    array('Stock Order Request Report', 'mdi mdi-note-text-outline', $SITEURL . '/stock/stock_order_request_report.php', '166'),
                ),
                'pin' => array('155', '156', '157', '158', '166'),
            ),
        ),
        'pin' => array('123', '69', '92', '93', '100', '155', '156', '157', '158', '166')
    ),
    array(
        'Other',
        'mdi mdi-dots-horizontal',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Agent', 'mdi mdi-information-outline', $SITEURL . '/finance/agent_table.php', '62'),
            array('Goal Target', 'mdi mdi-bullseye-arrow', $SITEURL . '/settings/goalTarget_table.php', '121'),

        ),
        'pin' => array('62', '121')
    ),
    array(
        'Setting',
        'mdi mdi-cog',
        'javascript:void(0)',
        'y',
        'expand' => array(
            array('Sql Account', 'mdi mdi-database', $SITEURL . '/settings/sql_account_table.php', '132'),
            array('Token Setting', 'mdi mdi-key-chain', $SITEURL . '/settings/token_setting_table.php', '133'),
            array('Theme Setting', 'mdi mdi-brush-variant', $SITEURL . '/settings/theme_setting.php', '23'),
            array('System Setting', 'mdi mdi-brush-variant', $SITEURL . '/settings/system_setting.php', '39'),
            array(
                'User Management',
                'mdi mdi-folder-account',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('User', 'mdi mdi-account-wrench-outline', $SITEURL . '/users/user_table.php', '90'),
                    array('User Group', 'mdi mdi-account-wrench-outline', $SITEURL . '/users/user_group_table.php', '3'),
                    array('Pin', 'mdi mdi-pin', $SITEURL . '/users/pin_table.php', '1'),
                    array('Pin Group', 'mdi mdi-ungroup', $SITEURL . '/users/pin_group_table.php', '2'),

                ),
                'pin' => array('1', '2', '90', '3'),

            ),
            array(
                'Administration Setting',
                'mdi mdi-account-key',
                'javascript:void(0)',
                'y',
                'expand' => array(
                    array('Bank', 'mdi mdi-bank', $SITEURL . '/settings/bank_table.php', '8'),
                    array('Currencies', 'mdi mdi-swap-horizontal', $SITEURL . '/settings/currencies_table.php', '11'),
                    array('Currency Unit', 'mdi mdi-currency-usd', $SITEURL . '/settings/currency_unit_table.php', '10'),
                    array('Platform', 'mdi mdi-home-outline', $SITEURL . '/settings/platform_table.php', '14'),
                    array('Weight Unit', 'mdi mdi-weight', $SITEURL . '/settings/weight_unit_table.php', '19'),
                    array('Chanel (Social Media)', 'mdi storefront-outline', $SITEURL . '/finance/chanel_social_media_table.php', '79'),
                    array('Company', 'mdi mdi-office-building-outline', $SITEURL . '/settings/company_table.php', '127'),
                ),
                'pin' => array('8', '11', '10', '14', '19', '79', '127'),
            ),
        ),
        'pin' => array('132', '133', '23', '39', '1', '2', '90', '3', '8', '11', '10', '14', '19', '79', '127')
    ),
    array(
        'Import Shortcut',
        'mdi mdi-file-import-outline',
        $SITEURL . '/import/common_import.php',
        'n',
        'expand' => array(),
        'pin' => $importShortcutVisiblePins
    ),
    array(
        'BMI Calculator',
        'mdi mdi-calculator',
        $SITEURL . '/bmi-calculator/bmi.php',
        'n',
        'expand' => array(),
        'pin' => is_array(GlobalPin) ? GlobalPin : array('0')
    ),
    array(
        'Audit Log',
        'mdi mdi-text-box-search-outline',
        $SITEURL . '/users/audit_log.php',
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

<?php
$taskCurrentPath = '';
$isTaskViewMyTaskPage = false;
$isTaskSummaryPage = false;
$isTaskBoardPage = false;
$isTaskSheetsPage = false;
$isTaskProjectSettingsPage = false;
$isTaskProjectUserAccessPage = false;
$taskCurrentProjectId = 0;
$taskProjectList = array();
$canCreateTaskProject = false;
$taskActiveMenuKey = '';

if ($hasTaskManagementAccess) {
    $taskProjectList = taskGetProjectList($connect);
    $canCreateTaskProject = taskCanCreateProject($connect);
    $taskCurrentPath = isset($_SERVER['REQUEST_URI']) ? (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';

    if (strpos($taskCurrentPath, '/task/') !== false) {
        $isTaskViewMyTaskPage = strpos($taskCurrentPath, '/task/view_my_task.php') !== false;
        $isTaskSummaryPage = strpos($taskCurrentPath, '/task/summary.php') !== false;
        $isTaskBoardPage = strpos($taskCurrentPath, '/task/board.php') !== false;
        $isTaskSheetsPage = strpos($taskCurrentPath, '/task/sheets.php') !== false;
        $isTaskProjectSettingsPage = strpos($taskCurrentPath, '/task/project_settings.php') !== false;
        $isTaskProjectUserAccessPage = strpos($taskCurrentPath, '/task/project_user_access.php') !== false;
        $taskCurrentProjectId = taskResolveCurrentProjectId($connect, (int) numberInput('project_id'));

        if ($isTaskViewMyTaskPage) {
            $taskActiveMenuKey = 'view_my_task';
        } elseif ($isTaskSummaryPage) {
            $taskActiveMenuKey = 'summary';
        } elseif ($isTaskBoardPage) {
            $taskActiveMenuKey = 'board';
        } elseif ($isTaskSheetsPage) {
            $taskActiveMenuKey = 'sheets';
        } elseif ($isTaskProjectUserAccessPage) {
            $taskActiveMenuKey = 'project_user_access';
        } elseif ($isTaskProjectSettingsPage) {
            $taskActiveMenuKey = 'project_settings';
        }
    }
}
?>

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
                if ($innerList[0] === 'Project Task' && !$hasTaskManagementAccess) {
                    continue;
                }

                if (!empty(array_intersect($innerList['pin'], GlobalPin))) {
                    $isTaskTopMenu = $innerList[0] === 'Project Task';
                    $liClass = $innerList[3] == 'y' ? 'nav-item dropdown' : 'nav-item';
                    if ($isTaskTopMenu) {
                        $liClass .= ' task-top-menu-trigger-item';
                    }
                    $li = "class=\"" . $liClass . "\"";
                    $linkClass = $innerList[3] == 'y' ? 'nav-link dropdown-toggle' : 'nav-link';
                    if ($isTaskTopMenu) {
                        $linkClass .= ' task-top-menu-trigger';
                    }
                    $taskAriaLabel = $isTaskTopMenu ? ' aria-label="Project Task" title="Project Task"' : '';
                    $a = $innerList[3] == 'y'
                        ? "class=\"" . $linkClass . "\" data-bs-toggle=\"dropdown\" role=\"button\" aria-expanded=\"false\"" . $taskAriaLabel
                        : "class=\"" . $linkClass . "\"" . $taskAriaLabel;

                    echo "<li $li>";
                    $iconHtml = $isTaskTopMenu
                        ? '<i class="fa-solid fa-bars task-top-menu-icon" aria-hidden="true"></i>'
                        : "<i class=\"$innerList[1]\"></i>";
                    echo "<a $a href=\"$innerList[2]\">$iconHtml";
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
                                        $url2Title = !empty($url2[4]) ? $url2[0] : getMenuTitleByPinGroupId($connect, $url2[0], $url2[3]);
                                        echo "<li><a class=\"dropdown-item\" href=\"$url2[2]\">$url2Title</a></li>";
                                    }
                                }

                                echo "</ul>";
                                echo "</li>";
                            }
                        } else {
                            if (in_array($url[3], GlobalPin)) {
                                $urlTitle = !empty($url[4]) ? $url[0] : getMenuTitleByPinGroupId($connect, $url[0], $url[3]);
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
                        $isTaskManagementMenu = $innerList[0] === 'Project Task';
                        $isDropdownMenu = $innerList[3] == 'y' || $isTaskManagementMenu;
                        $collapseId = $isTaskManagementMenu ? 'Task-Management' : $innerList[0];
                        $li = $isDropdownMenu ? "class=\"nav-item dropdown\"" : "class=\"nav-item\"";
                        $a = $isDropdownMenu
                            ? "class=\"nav-link dropdown-toggle\" data-bs-toggle=\"collapse\" data-bs-target=\"#$collapseId-collapse\" aria-expanded=\"false\""
                            : "class=\"nav-link\" href=\"$innerList[2]\"";

                        $expandMenus = $innerList['expand'];
                        if ($isTaskManagementMenu) {
                            $expandMenus = array(
                                array('Summary', 'mdi mdi-view-dashboard-outline', $SITEURL . '/task/summary.php', '139'),
                                array('Board', 'mdi mdi-view-column-outline', $SITEURL . '/task/board.php', '139'),
                                array('Sheets', 'mdi mdi-table-large', $SITEURL . '/task/sheets.php', '139'),
                            );
                        }

                        echo "<li $li>";
                        echo "<a $a href=\"#\"><i class=\"$innerList[1]\"></i><span> $innerList[0]</span></a>";
                        echo "<div class=\"collapse\" id=\"$collapseId-collapse\">";
                        if ($isTaskManagementMenu) {
                            taskRenderProjectBrowserMenu(
                                $connect,
                                $SITEURL,
                                $taskActiveMenuKey,
                                $taskCurrentProjectId,
                                array(
                                    'section_class' => 'task-mobile-project-section',
                                    'panel_id_prefix' => 'taskMobileProjectPanel',
                                    'action_panel_id_prefix' => 'taskMobileProjectActions',
                                )
                            );
                            echo "</div>";
                            echo "</li>";
                            continue;
                        }

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
                                            $url2Title = !empty($url2[4]) ? $url2[0] : getMenuTitleByPinGroupId($connect, $url2[0], $url2[3]);
                                            echo "<li><a class=\"nav-link\" href=\"$url2[2]\"><i class=\"$url2[1]\"></i><span> $url2Title<span></a></li>";
                                        }
                                    }

                                    echo "</ul>";
                                    echo "</div>";
                                    echo "</li>";
                                }
                            } else {
                                if (in_array($url[3], GlobalPin)) {
                                    $urlTitle = ($isTaskManagementMenu || !empty($url[4])) ? $url[0] : getMenuTitleByPinGroupId($connect, $url[0], $url[3]);
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
<aside id="taskGlobalSidebar" class="task-global-sidebar" aria-hidden="true">
    <button type="button" id="taskGlobalSidebarResizeHandle" class="task-global-sidebar-resize-handle" aria-label="Resize task sidebar" title="Drag to resize"></button>
    <div class="task-global-sidebar-inner">

        <div class="task-global-project-section">
            <div class="task-global-project-header">
                <span>Project Task</span>

                <?php if ($canCreateTaskProject): ?>
                    <button type="button"
                        id="taskCreateProjectBtn"
                        class="task-global-create-project-btn"
                        title="Create project task">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($canCreateTaskProject): ?>
            <div class="task-global-create-project-row" id="taskGlobalCreateProjectRow">
                <input type="text" id="taskGlobalCreateProjectInput" class="form-control task-global-create-project-input" maxlength="180" placeholder="Project task name">
                <button type="button" class="btn task-global-create-project-confirm-btn" id="taskGlobalCreateProjectConfirmBtn" title="Create">
                    <i class="fa-solid fa-check"></i>
                </button>
                <button type="button" class="btn task-global-create-project-cancel-btn" id="taskGlobalCreateProjectCancelBtn" title="Cancel">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <?php endif; ?>

            <ul class="task-global-project-list">
                <?php foreach ($taskProjectList as $taskProject): ?>
                    <?php
                    $pid = (int) $taskProject['id'];
                    $isActiveProject = $pid === (int) $taskCurrentProjectId;
                    $projectHasSummaryAccess = taskUserCanAccessProjectPageByPin($connect, $pid, 139);
                    $projectHasBoardAccess = taskUserCanAccessProjectPageByPin($connect, $pid, 139);
                    $projectHasSheetsAccess = taskUserCanAccessProjectPageByPin($connect, $pid, 139);
                    $canAccessProjectSettings = taskCanAccessProjectSettings($connect, $pid, false);
                    $canAccessProjectUserAccess = taskCanAccessProjectUserAccess($connect, $pid);
                    $canManageProjectActions = taskCanManageProjectActions($connect, $pid);
                    $projectItemPanelId = 'taskGlobalProjectPanel' . $pid;
                    $projectItemActionPanelId = 'taskGlobalProjectActions' . $pid;
                    ?>
                    <li class="task-global-project-item <?= $isActiveProject ? 'active' : '' ?> <?= $isActiveProject ? 'expanded' : '' ?>"
                        data-project-id="<?= $pid ?>">
                        <div class="task-global-project-row">
                            <button type="button"
                                    class="task-global-project-toggle"
                                    data-task-project-toggle
                                    aria-expanded="<?= $isActiveProject ? 'true' : 'false' ?>"
                                    aria-controls="<?= $projectItemPanelId ?>">
                                <span class="task-global-project-toggle-text"><?= htmlspecialchars($taskProject['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="task-global-project-toggle-icon" aria-hidden="true">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            </button>

                            <?php if ($canManageProjectActions): ?>
                                <div class="task-global-project-actions">
                                    <button type="button"
                                            class="task-global-project-settings-link task-global-project-actions-btn"
                                            data-task-project-actions-btn
                                            aria-expanded="false"
                                            aria-controls="<?= $projectItemActionPanelId ?>"
                                            title="Project options">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <div class="task-global-project-actions-panel" id="<?= $projectItemActionPanelId ?>">
                                        <?php if ($canAccessProjectUserAccess): ?>
                                            <a href="<?= $SITEURL ?>/task/project_user_access.php?project_id=<?= $pid ?>">Project User Access</a>
                                        <?php endif; ?>
                                        <?php if ($canAccessProjectSettings): ?>
                                            <a href="<?= $SITEURL ?>/task/project_settings.php?project_id=<?= $pid ?>">Project Settings</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <ul class="task-global-project-submenu <?= $isActiveProject ? 'active' : '' ?>"
                            id="<?= $projectItemPanelId ?>">
                            <?php if ($projectHasSummaryAccess): ?>
                                    <a class="<?= $isTaskViewMyTaskPage && $isActiveProject ? 'task-global-link-active' : '' ?>"
                                       href="<?= $SITEURL ?>/task/view_my_task.php?project_id=<?= $pid ?>">
                                        View My Task
                                    </a>
                            <?php endif; ?>
                            <?php if ($projectHasSummaryAccess): ?>
                                    <a class="<?= $isTaskSummaryPage && $isActiveProject ? 'task-global-link-active' : '' ?>"
                                       href="<?= $SITEURL ?>/task/summary.php?project_id=<?= $pid ?>">
                                        Summary
                                    </a>
                            <?php endif; ?>
                            <?php if ($projectHasBoardAccess): ?>
                                    <a class="<?= $isTaskBoardPage && $isActiveProject ? 'task-global-link-active' : '' ?>"
                                       href="<?= $SITEURL ?>/task/board.php?project_id=<?= $pid ?>">
                                        Board
                                    </a>
                            <?php endif; ?>
                            <?php if ($projectHasSheetsAccess): ?>
                                    <a class="<?= $isTaskSheetsPage && $isActiveProject ? 'task-global-link-active' : '' ?>"
                                       href="<?= $SITEURL ?>/task/sheets.php?project_id=<?= $pid ?>">
                                        Sheets
                                    </a>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
</aside>
<?php endif; ?>

<script>
    <?php
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    ?>
    function isMobileViewport() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    var sidebar = $("#sidebar");
    var sidebar_toggleBtn = $("#sidebarCollapse"); // variable from menuHeader
    var opacityBackground = $('div#filter_screen');
    var taskTopMenuTrigger = $('.task-top-menu-trigger');
    var taskTopMenuIcon = taskTopMenuTrigger.find('.task-top-menu-icon');
    var taskGlobalSidebar = $('#taskGlobalSidebar');
    var taskGlobalSidebarResizeHandle = $('#taskGlobalSidebarResizeHandle');
    var taskSidebarStorageKey = 'task_global_sidebar_open';
    var taskSidebarWidthStorageKey = 'task_global_sidebar_width';
    var taskSidebarMinWidth = 260;
    var taskSidebarMaxWidth = 520;
    var hasTaskManagementAccess = <?php echo $hasTaskManagementAccess ? 'true' : 'false'; ?>;

    function syncMobileSidebarOpenState() {
        if (isMobileViewport() && sidebar.hasClass('active')) {
            $('body').addClass('mobile-sidebar-open');
        } else {
            $('body').removeClass('mobile-sidebar-open');
        }
    }

    function syncMobileSidebarViewport() {
        var mobileTop = $('.topNav').outerHeight() || 50;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        if (window.visualViewport && window.visualViewport.height) {
            viewportHeight = window.visualViewport.height;
        }

        var availableHeight = Math.max(0, Math.floor(viewportHeight - mobileTop));
        document.documentElement.style.setProperty('--mobile-sidebar-top', mobileTop + 'px');

        if (availableHeight > 0) {
            document.documentElement.style.setProperty('--mobile-sidebar-height', availableHeight + 'px');
        }
    }

    function positionTaskProjectOptionsPanel(actionBtn) {
        if (!actionBtn) {
            return;
        }

        var wrap = actionBtn.closest('.task-global-project-actions');
        var panelId = actionBtn.getAttribute('aria-controls') || '';
        var panel = panelId ? document.getElementById(panelId) : null;
        if (!wrap || !panel) {
            return;
        }

        var buttonRect = actionBtn.getBoundingClientRect();
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        var panelWidth = panel.offsetWidth || 185;
        var panelHeight = panel.offsetHeight || 0;
        var left = buttonRect.right + 8;
        var top = buttonRect.top;

        if (left + panelWidth > viewportWidth - 12) {
            left = Math.max(12, buttonRect.left - panelWidth - 8);
        }

        if (panelHeight > 0 && top + panelHeight > viewportHeight - 12) {
            top = Math.max(12, viewportHeight - panelHeight - 12);
        }

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    function mountTaskProjectOptionsPanel(actionBtn) {
        if (!actionBtn) {
            return null;
        }

        var wrap = actionBtn.closest('.task-global-project-actions');
        var panelId = actionBtn.getAttribute('aria-controls') || '';
        var panel = panelId ? document.getElementById(panelId) : null;
        if (!wrap || !panel) {
            return null;
        }

        if (panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }
        panel.classList.add('task-global-project-actions-panel-open');
        return panel;
    }

    function restoreTaskProjectOptionsPanel(wrap) {
        if (!wrap) {
            return;
        }

        var actionBtn = wrap.querySelector('[data-task-project-actions-btn]');
        var panelId = actionBtn ? actionBtn.getAttribute('aria-controls') : '';
        var panel = panelId ? document.getElementById(panelId) : null;
        if (!panel) {
            return;
        }

        panel.classList.remove('task-global-project-actions-panel-open');
        panel.style.left = '';
        panel.style.top = '';

        if (panel.parentElement !== wrap) {
            wrap.appendChild(panel);
        }
    }

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

    function applyTaskSidebarWidth(width) {
        var parsedWidth = Number(width) || 0;
        if (!parsedWidth) {
            return;
        }

        var clampedWidth = Math.max(taskSidebarMinWidth, Math.min(taskSidebarMaxWidth, parsedWidth));
        document.documentElement.style.setProperty('--task-global-sidebar-width-expanded', clampedWidth + 'px');
    }

    function bindTaskSidebarResize() {
        if (!taskGlobalSidebarResizeHandle.length) {
            return;
        }

        var isDragging = false;
        var dragStartX = 0;
        var dragStartWidth = 0;

        function onPointerMove(event) {
            if (!isDragging) {
                return;
            }
            var delta = event.clientX - dragStartX;
            applyTaskSidebarWidth(dragStartWidth + delta);
        }

        function onPointerUp() {
            if (!isDragging) {
                return;
            }
            isDragging = false;
            document.body.classList.remove('task-global-sidebar-resizing');
            document.removeEventListener('pointermove', onPointerMove);
            document.removeEventListener('pointerup', onPointerUp);

            var finalWidth = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--task-global-sidebar-width-expanded')) || 0;
            if (finalWidth > 0) {
                try {
                    window.localStorage.setItem(taskSidebarWidthStorageKey, String(Math.round(finalWidth)));
                } catch (e) {}
            }
        }

        taskGlobalSidebarResizeHandle.on('pointerdown', function (event) {
            if (isMobileViewport() || !$('body').hasClass('task-global-sidebar-open')) {
                return;
            }

            event.preventDefault();
            isDragging = true;
            dragStartX = event.clientX;
            dragStartWidth = taskGlobalSidebar.outerWidth() || 0;
            document.body.classList.add('task-global-sidebar-resizing');
            document.addEventListener('pointermove', onPointerMove);
            document.addEventListener('pointerup', onPointerUp);
        });
    }

    syncTaskSidebarTopOffset();
    syncMobileSidebarViewport();

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncMobileSidebarViewport);
        window.visualViewport.addEventListener('scroll', syncMobileSidebarViewport);
    }

    if (hasTaskManagementAccess) {
        try {
            applyTaskSidebarWidth(window.localStorage.getItem(taskSidebarWidthStorageKey));
        } catch (e) {}
        bindTaskSidebarResize();
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
                    syncMobileSidebarOpenState();
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
                syncMobileSidebarOpenState();

                // timeout value based on .close css transition (0.3s)
                setTimeout(() => {
                    sidebar.removeClass('active');
                    sidebar.removeClass('close');
                    syncMobileSidebarOpenState();
                }, 500);
            } else {
                sidebar.toggleClass("active", true);
                sidebar.toggleClass("close", false);
                if (isMobileViewport()) {
                    opacityBackground.show();
                } else {
                    opacityBackground.hide();
                }
                syncMobileSidebarOpenState();
            }
        });

        $(window).on('resize', function () {
            syncTaskSidebarTopOffset();
            syncMobileSidebarViewport();
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
            syncMobileSidebarOpenState();
        });

        opacityBackground.on('click', function (e) {
            var sidebar2 = $("#sidebar, #sidebarCollapse");
            if (!sidebar2.is(e.target) && sidebar2.has(e.target).length === 0) {
                sidebar.toggleClass('close', true);
                opacityBackground.hide();
                syncMobileSidebarOpenState();
                setTimeout(() => {
                    sidebar.removeClass('active');
                    sidebar.removeClass('close');
                    syncMobileSidebarOpenState();
                }, 300);
            }
        });

        syncMobileSidebarOpenState();

        document.addEventListener('DOMContentLoaded', function () {

        const createBtn = document.getElementById('taskCreateProjectBtn');
        const createRow = document.querySelector('.task-global-create-project-row');
        const input = createRow ? createRow.querySelector('input') : null;
        const confirmBtn = document.getElementById('taskGlobalCreateProjectConfirmBtn');
        const cancelBtn = document.getElementById('taskGlobalCreateProjectCancelBtn');
        const csrfToken = <?php echo json_encode(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '', JSON_UNESCAPED_UNICODE); ?>;

        // OPEN create row
        if (createBtn && createRow) {
            createBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            createRow.classList.add('active');

            if (input) {
                input.focus();
                input.select();
            }
            });
        }

        // CANCEL
        if (cancelBtn && createRow) {
            cancelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            createRow.classList.remove('active');
            });
        }

        // PRESS ESC = cancel
        if (input && createRow) {
            input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                createRow.classList.remove('active');
            } else if (e.key === 'Enter' && confirmBtn) {
                e.preventDefault();
                confirmBtn.click();
            }
            });
        }

        // CREATE project
        if (confirmBtn && input && createRow) {
            confirmBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const projectName = (input.value || '').trim();
            if (!projectName) {
                input.focus();
                return;
            }

            confirmBtn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('task_action', 'create_project');
            payload.append('project_name', projectName);
            payload.append('csrf_token', csrfToken);

            fetch('<?php echo $SITEURL; ?>/task/board.php', {
                method: 'POST',
                headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString()
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                if (data && data.ok && data.project && Number(data.project.id || 0) > 0) {
                    window.location.href = '<?php echo $SITEURL; ?>/task/summary.php?project_id=' + Number(data.project.id || 0);
                    return;
                }
                confirmBtn.disabled = false;
                if (data && data.message) {
                    showNotification(data.message, 'error');
                }
                })
                .catch(function () {
                confirmBtn.disabled = false;
                showNotification('Failed to create project task.', 'error');
                });
            });
        }

        document.querySelectorAll('[data-task-project-toggle]').forEach(function (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var item = toggleBtn.closest('.task-global-project-item');
                if (!item) {
                    return;
                }

                var isExpanded = item.classList.contains('expanded');

                document.querySelectorAll('.task-global-project-item.expanded').forEach(function (expandedItem) {
                    expandedItem.classList.remove('expanded');
                    var expandedBtn = expandedItem.querySelector('[data-task-project-toggle]');
                    var expandedPanel = expandedItem.querySelector('.task-global-project-submenu');
                    if (expandedBtn) {
                        expandedBtn.setAttribute('aria-expanded', 'false');
                    }
                    if (expandedPanel) {
                        expandedPanel.classList.remove('active');
                    }
                });

                if (!isExpanded) {
                    item.classList.add('expanded');
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    var panel = item.querySelector('.task-global-project-submenu');
                    if (panel) {
                        panel.classList.add('active');
                    }
                }
            });
        });

        document.querySelectorAll('[data-task-project-actions-btn]').forEach(function (actionBtn) {
            actionBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var wrap = actionBtn.closest('.task-global-project-actions');
                if (!wrap) {
                    return;
                }

                var willOpen = !wrap.classList.contains('open');
                document.querySelectorAll('.task-global-project-actions.open').forEach(function (openWrap) {
                    openWrap.classList.remove('open');
                    var openBtn = openWrap.querySelector('[data-task-project-actions-btn]');
                    if (openBtn) {
                        openBtn.setAttribute('aria-expanded', 'false');
                    }
                    restoreTaskProjectOptionsPanel(openWrap);
                });

                if (willOpen) {
                    mountTaskProjectOptionsPanel(actionBtn);
                    wrap.classList.add('open');
                    actionBtn.setAttribute('aria-expanded', 'true');
                    window.requestAnimationFrame(function () {
                        positionTaskProjectOptionsPanel(actionBtn);
                    });
                } else {
                    actionBtn.setAttribute('aria-expanded', 'false');
                    restoreTaskProjectOptionsPanel(wrap);
                }
            });
        });

        document.addEventListener('click', function (e) {
            var actionWrap = e.target.closest('.task-global-project-actions');
            if (actionWrap) {
                return;
            }

            document.querySelectorAll('.task-global-project-actions.open').forEach(function (openWrap) {
                openWrap.classList.remove('open');
                var openBtn = openWrap.querySelector('[data-task-project-actions-btn]');
                if (openBtn) {
                    openBtn.setAttribute('aria-expanded', 'false');
                }
                restoreTaskProjectOptionsPanel(openWrap);
            });
        });

        window.addEventListener('resize', function () {
            document.querySelectorAll('.task-global-project-actions.open [data-task-project-actions-btn]').forEach(function (actionBtn) {
                positionTaskProjectOptionsPanel(actionBtn);
            });
        });

        });
</script>
