<?php
// 30 days in seconds (30 days * 24 hours * 60 mins * 60 secs)
$sessionLifetime = 2592000;

// Determine if the current connection is secure (HTTPS) to set the cookie's "secure" flag appropriately
$secure = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
);

// Configure session cookie parameters with secure defaults
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

ini_set('session.gc_maxlifetime', $sessionLifetime);

// --- FIX: Create a private, isolated folder for this app's sessions ---
$sessionPath = __DIR__ . '/app_sessions'; // Creates a folder named 'app_sessions' next to init.php
if (!file_exists($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);
// ----------------------------------------------------------------------

session_start();

// Auto-detect environment based on the URL host
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$hostOnly = strtolower(trim(explode(':', $host)[0]));

// If the URL contains 'localhost' or '127.0.0.1', it's local mode. Otherwise, it's live.
if (strpos($hostOnly, 'localhost') !== false || strpos($hostOnly, '127.0.0.1') !== false) {
    $siteOrlocalMode = false; // Local environment
} else {
    $siteOrlocalMode = true;  // Live site environment
}

date_default_timezone_set('Asia/Singapore');

$dbUser = $siteOrlocalMode ? 'beyourdi_cms' : 'root';

$dbName = 'beyourdi_cms-uat';
$dbFinanceName = 'beyourdi_financial-uat';
$siteUrl = 'https://uatcms.beyourdiary.com';

if (!$siteOrlocalMode) {
    $dbName = 'beyourdi_cms-uat';
    $dbFinanceName = 'beyourdi_financial-uat';
    $siteUrl = 'http://localhost/cms';
} elseif ($hostOnly === 'cms.beyourdiary.com') {
    $dbName = 'beyourdi_cms';
    $dbFinanceName = 'beyourdi_financial';
    $siteUrl = 'https://cms.beyourdiary.com';
} elseif ($hostOnly === 'uatcms.beyourdiary.com') {
    $dbName = 'beyourdi_cms-uat';
    $dbFinanceName = 'beyourdi_financial-uat';
    $siteUrl = 'https://uatcms.beyourdiary.com';
}

/**
 * TODO SECURITY:
 * Database credentials are still defined in this file for current system compatibility.
 * This should be improved later by moving the database password and host config into
 * environment variables or a .env file that is excluded from Git.
 *
 * When this is changed in the future:
 * 1. Update the server/local environment configuration.
 * 2. Remove plaintext credentials from the repository.
 * 3. Rotate the exposed database password.
 * 4. Test both CMS and finance database connections.
 */
define('dbuser', $dbUser);
define('dbpwd', $siteOrlocalMode ? 'Byd1234@Global' : '');
define('dbhost', $siteOrlocalMode ? '127.0.0.1:3306' : 'localhost');
define('dbname', $dbName);
define('dbFinance', $dbFinanceName);
define('SITEURL', $siteUrl);
$SITEURL = SITEURL;
define('ROOT', dirname(__FILE__));
define('email_cc', 'report@beyourdiary.com');

// shared external URLs / CDN paths
define('TELEGRAM_API', 'https://api.telegram.org/bot');
define('QR_CODE_API_URL', 'https://api.qrserver.com/v1/create-qr-code/');
define('IPAPI_URL', 'https://ipapi.co/');
define('DUCKDUCKGO_FAVICON_URL', 'https://icons.duckduckgo.com/ip3/');
define('GOOGLE_FAVICON_URL', 'https://www.google.com/s2/favicons');
define('JQUERY_3_6_4_JS', 'https://code.jquery.com/jquery-3.6.4.min.js');
define('CHART_JS_CDN_URL', 'https://cdn.jsdelivr.net/npm/chart.js');
define('CHART_JS_LOCAL_PATH', SITEURL . '/header/js/chart.umd.min.js');
define('PDF_JS_LOCAL_PATH', SITEURL . '/header/js/pdf.min.js');
define('PDF_WORKER_LOCAL_PATH', SITEURL . '/header/js/pdf.worker.min.js');

if (!function_exists('siteUrlPath')) {
    function siteUrlPath($path = '')
    {
        return rtrim(SITEURL, '/') . '/' . ltrim((string) $path, '/');
    }
}

// shared internal routes
define('ROUTE_INDEX', '/index.php');
define('ROUTE_DASHBOARD', '/dashboard.php');
define('ROUTE_SYSTEM_ALERT_ACTION', '/system_alert_action.php');
define('ROUTE_SYSTEM_ALERT_LIVE', '/system_alert_live.php');
define('ROUTE_FINANCE_WAITING_TO_PACK', '/finance/waiting_to_pack.php');
define('ROUTE_FINANCE_ARRIVAL_MANAGEMENT', '/finance/arrival_management.php');
define('ROUTE_FINANCE_FLOW_REPORT', '/finance/flow_report.php');
define('ROUTE_CUSTOMER_FOLLOW_UP_LIST', '/customer/customer_follow_up_list.php');
define('ROUTE_CAMPAIGN_FOLLOW_UP_TASK', '/campaign/campaign_follow_up_task.php');
define('ROUTE_TASK_BOARD', '/task/board.php');
define('ROUTE_SHOPEE_VERIFY', '/shopee/shopee_verify.php');
define('ROUTE_FINANCE_WEBSITE_ORDER_REQUEST', '/finance/website_order_request.php');
define('ROUTE_FINANCE_WEBSITE_ORDER_REQUEST_TABLE', '/finance/website_order_request_table.php');
define('ROUTE_FINANCE_FB_ORDER_REQ', '/finance/fb_order_req.php');
define('ROUTE_FINANCE_FB_ORDER_REQ_TABLE', '/finance/fb_order_req_table.php');
define('ROUTE_FINANCE_LAZADA_ORDER_REQ', '/finance/lazada_order_req.php');
define('ROUTE_FINANCE_LAZADA_ORDER_REQ_TABLE', '/finance/lazada_order_req_table.php');
define('ROUTE_SHOPEE_ORDER_REQ', '/shopee/shopee_order_req.php');
define('ROUTE_SHOPEE_ORDER_REQ_TABLE', '/shopee/shopee_order_req_table.php');
define('ROUTE_STOCK_ORDER_REQUEST', '/stock/stock_order_request.php');
define('ROUTE_STOCK_ORDER_REQUEST_TABLE', '/stock/stock_order_request_table.php');

if (!function_exists('siteUrlWithQuery')) {
    function siteUrlWithQuery($path = '', $params = array(), $fragment = '')
    {
        $url = siteUrlPath($path);
        $params = array_filter((array) $params, function ($value) {
            return $value !== null && $value !== '';
        });

        if (!empty($params)) {
            $queryString = http_build_query($params);
            if ($queryString !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
            }
        }

        $fragment = trim((string) $fragment);
        if ($fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return $url;
    }
}


// //define date time
define('date_dis', date("Y-m-d"));
define('time_dis', date("G:i:s"));
define('yearMonth', strtolower(date('YM')));
define('comYMD', strtolower(date('Ymd')));
define('GlobalPin', isset($_SESSION['usr_pin']) ? $_SESSION['usr_pin'] : '');
// define('memberImportDetail', yearMonth.'_importInfo');

$email_collect = '';
$cdate = date_dis;
$ctime = time_dis;
$comYMD = comYMD;
/* $cby = $_SESSION['userid']; */

$act_1 = 'I'; //Insert/ Add
$act_2 = 'E'; //Edit/ Update
$act_3 = 'D'; //Delete

// //session define
// $displayName = $_SESSION['login_name'];
define('USER_ID', isset($_SESSION['userid']) ? $_SESSION['userid'] : '');
define('USER_NAME', isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '');
define('USER_EMAIL', isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');
define('USER_GROUP', isset($_SESSION['user_group']) ? $_SESSION['user_group'] : '');
// //call client url //demo link 
// if($livemode==true)
// 	$curl_ship_domain = 'https://demo.connect.easyparcel.sg/?ac=';
// else
// 	$curl_ship_domain = 'https://connect.easyparcel.sg/?ac=';

// //api courier
// $api = 'EP-Mqx0IKqqS';
// if($livemode==true)
// 	$authentication = 'zKpyWplgj9'; //demo authentication
// els
// 	$authentication = 'nYgGJWc9Hq'; //live authentication

// //error message default mean
// $error_msg = array('3'=>'Required api key', '4'=>'Invalid api key', '5'=>'Unauthorized user', '0'=>'Success', '1'=>'Required authentication key', '1'=>'Invalid authentication key', '6'=>'Invalid data format');

// easyparcel auth & api (live on live server, demo on localhost)
define('EASYPARCEL_LIVE_DOMAIN_MY', 'https://connect.easyparcel.my/?ac=');
define('EASYPARCEL_DEMO_DOMAIN_MY', 'https://demo.connect.easyparcel.my/?ac=');
define('EASYPARCEL_LIVE_DOMAIN_SG', 'https://connect.easyparcel.sg/?ac=');
define('EASYPARCEL_DEMO_DOMAIN_SG', 'https://demo.connect.easyparcel.sg/?ac=');

if ($siteOrlocalMode) {
    define('EASYPARCEL_DOMAIN_MY', EASYPARCEL_LIVE_DOMAIN_MY);
    define('EASYPARCEL_AUTH_MY', 'MwxHG9i3Wu');
    define('EASYPARCEL_API_MY', 'EP-Jj0HYyEkp');
    define('EASYPARCEL_DOMAIN_SG', EASYPARCEL_LIVE_DOMAIN_SG);
    define('EASYPARCEL_AUTH_SG', 'nYgGJWc9Hq');
    define('EASYPARCEL_API_SG', 'EP-Mqx0IKqqS');
} else {
    define('EASYPARCEL_DOMAIN_MY', EASYPARCEL_DEMO_DOMAIN_MY);
    define('EASYPARCEL_AUTH_MY', 'MwxHG9i3Wu');
    define('EASYPARCEL_API_MY', 'EP-Jj0HYyEkp');
    define('EASYPARCEL_DOMAIN_SG', EASYPARCEL_DEMO_DOMAIN_SG);
    define('EASYPARCEL_AUTH_SG', 'zKpyWplgj9');
    define('EASYPARCEL_API_SG', 'EP-Mqx0IKqqS');
}

// //table name define
define('USR_USER', 'user');
define('LANG', 'language');
define('PIN', 'pin');
define('PIN_GRP', 'pin_group');
define('AUDIT_LOG', 'audit_log');
define('USR_GRP', 'user_group');
define('DESIG', 'designation');
define('DEPT', 'department');
define('HOLIDAY', 'holiday');
define('BRAND', 'brand');
define('COMPANY', 'company');
define('PURCHASE_ORDER', 'purchase_order');
define('PLTF', 'platform');
define('PROD_STATUS', 'product_status');
define('WHSE', 'warehouse');
define('MRTL_STATUS', 'marital_status');
define('BANK', 'bank');
define('EM_TYPE_STATUS', 'em_type_status');
define('CUR_UNIT', 'currency_unit');
define('CURRENCIES', 'currencies');
define('CUST', 'customer');
define('COURIER', 'courier');
define('SHIPREQ', 'shipping_request');
define('WGT_UNIT', 'weight_unit');
define('PROD', 'product');
define('PKG', 'package');
define('PROJ', 'projects');
define('STK_REC', 'stock_record');
define('STOCK_OUT_BATCH_USAGE', 'stock_out_batch_usage');
define('STOCK_ORDER_REQ', 'stock_order_request');
define('STOCK_ORDER_REQ_ITEM', 'stock_order_request_item');
define('L_TYPE', 'leave_type');
define('CUR_SEGMENTATION', 'customer_segmentation');
define('CUS_LEVEL', 'customer_level');
define('CUS_REPEAT', 'customer_repeat');
define('MESSAGE_SHORTCUTS', 'message_shortcuts');
define('RACE', 'race');
define('L_STS', 'leave_status');
define('ID_TYPE', 'identity_type');
define('SOCSO_CATH', 'socso_category');
define('EMPLOYEE_EPF', 'employee_epf_rate');
define('EMPLOYER_EPF', 'employer_epf_rate');
define('PAY_METH', 'payment_method');
define('SQL_ACC', 'sql_account');
define('TOKEN_SETT', 'token_setting');
define('EMPINFO', 'employee_info');
define('EMPPERSONALINFO', 'employee_personal_info');
define('TAG', 'tag');
define('CUS_TAG_ASSIGNMENT', 'customer_tag_assignment');
define('LABEL', 'label');
define('EMPLEAVE', 'employee_leave');
define('CUS_INFO', 'customer_info');
define('L_PENDING', 'leave_pending');
define('BRD_SERIES', 'brand_series');
define('FB_CUST_DEALS', 'customer_facebook_deals_transaction');
define('URBAN_CUST_REG', 'urbanism_customer_register_info');
define('OFFICIAL_PROCESS_ORDER', 'official_process_order');
define('ORDER_FLOW_TRANSITION_PERMISSION', 'order_flow_transition_permission');
define('ORDER_FLOW_SETTING', 'order_flow_setting');
define('ORDER_STATUS_TRANSITION_LOG', 'order_status_transition_log');
define('ORDER_EDIT_HISTORY', 'order_edit_history');
define('ORDER_RETURN_LOG', 'order_return_log');
define('ORDER_WAREHOUSE_SCAN_TOKEN', 'order_warehouse_scan_token');
define('YEARLYGOAL', 'yearly_goals');
define('USER_RECORD_LOG', 'user_record_log');
define('CUSTOMER_FOLLOW_UP', 'customer_follow_up');
define('CUSTOMER_FOLLOW_UP_ROUND', 'customer_follow_up_round');
define('CUSTOMER_FOLLOW_UP_ACTION_LOG', 'customer_follow_up_action_log');
define('CUSTOMER_FOLLOW_UP_NOTIFICATION', 'customer_follow_up_notification');
define('CAMPAIGN', 'campaign');
define('CAMPAIGN_PIC', 'campaign_pic');
define('CAMPAIGN_CUSTOMER', 'campaign_customer');
define('CAMPAIGN_MESSAGE', 'campaign_message');
define('CAMPAIGN_FOLLOW_UP', 'campaign_follow_up');
define('CAMPAIGN_PURCHASE_RECORD', 'campaign_purchase_record');
define('CAMPAIGN_RULE_SETTING', 'campaign_rule_setting');
define('CAMPAIGN_RULE_GENERATED_LOG', 'campaign_rule_generated_log');
define('SYSTEM_ALERT_MESSAGE', 'system_alert_message');
define('ORDER_DELETE_APPROVAL_REQUEST', 'order_delete_approval_request');
define('TASK_COLUMN', 'task_board_status');
define('TASK_WORK_TYPE', 'task_work_type');
define('TASK_ITEM', 'task_board_item');
define('TASK_LABEL', 'task_board_label');
define('TASK_ITEM_LABEL', 'task_board_item_label');
define('TASK_STATUS_LABEL', 'task_status_label');
define('TASK_PROJECT_KEY', 'task_project_key');
define('TASK_ITEM_ATTACHMENT', 'task_board_item_attachment');
define('TASK_ITEM_URL', 'task_board_item_url');
define('TASK_ITEM_RELATION', 'task_board_item_relation');
define('TASK_ITEM_LINK', 'task_board_item_link');
define('TASK_ITEM_HISTORY', 'task_board_item_history');
define('TASK_ITEM_COMMENT', 'task_board_item_comment');
define('TASK_ITEM_COMMENT_REPLY', 'task_board_comment_reply');
define('TASK_ITEM_WORKLOG', 'task_board_item_worklog');
define('TASK_SHEETS', 'task_sheets_column');
define('TASK_PROJECT', 'task_project');
define('TASK_PROJECT_ITEM_ACCESS', 'task_project_item_access');
define('TASK_PROJECT_COLUMN_ACCESS', 'task_project_column_access');
define('TASK_PROJECT_STATUS_ACCESS', 'task_project_status_access');



//finance
define('MERCHANT', 'merchant');
define('CURR_BANK_TRANS', 'asset_current_bank_acc_transaction');
define('INV_TRANS', 'asset_investment_transaction');
define('INVTR_TRANS', 'asset_inventories_transaction');
define('SD_TRANS', 'asset_sundry_debtors_transactions');
define('INITCA_TRANS', 'asset_initial_capital_transaction');
define('OCR_TRANS', 'asset_other_creditor_transaction');
define('CAONHD', 'asset_cash_on_hand_transaction');
define('META_ADS_ACC', 'meta_ads_account');
define('EXPENSE_TYPE', 'expense_type');
define('FB_ADS_TOPUP', 'facebook_ads_topup_transaction');
define('COUNTRIES', 'countries');
define('MRCHT_COMM', 'merchant_commission');
define('BANK_TRANS_BACKUP', 'bank_transaction_backup');
define('FIN_PAY_METH', 'finance_payment_method');
define('PROD_CATEGORY', 'product_category');
define('TAX_SETT', 'tax_setting');
define('INTERNAL_CONSUME', 'internal_consume_ticket_credit_transaction');
define('DEL_FEES_CLAIM', 'delivery_fees_claim_transaction');
define('FIN_PAY_TERMS', 'payment_terms');
define('ITL_CSM_ITEM', 'internal_consume_item');
define('SHOPEE_WDL_TRANS', 'shopee_withdrawal_transactions');
define('DW_TOP_UP_RECORD', 'downline_top_up_record');
define('AGENT', 'agent');
define('SHOPEE_ACC', 'shopee_account');
define('FB_ORDER_REQ', 'facebook_order_request');
define('FB_PAGE_ACC', 'facebook_page_account');
define('SHOPEE_ADS_TOPUP', 'shopee_ads_topup_transaction');
define('STK_CDT_TOPUP_RCD', 'stock_credit_topup_record');
define('CHANEL_SC_MD', 'chanel_social_media');
define('PAY_MTHD_SHOPEE', 'shopee_payment_method');
define('LAZADA_ACC', 'lazada_account');
define('CRED_NOTES_INV', 'credit_notes_invoice');
define('SHOPEE_SG_SETT', 'shopee_sg_fees_setting');
define('SHOPEE_SCR_SETT', 'shopee_service_charges_rate_setting');
define('WEB_CUST_RCD', 'customer_website_deals_transaction');
define('SHOPEE_CUST_INFO', 'shopee_customer_info');
define('ATOME_TRANS_BACKUP', 'atome_transaction_backup');
define('SHOPEE_SG_ORDER_REQ', 'shopee_sg_order_request');
define('JT_TRANS_BACKUP', 'jt_transaction_backup');
define('JT_TRANS_ITEM', 'jt_transaction_items');
define('JT_TRANS_GST', 'jt_transaction_extra_charges');
define('STRIPE_TRANS_BACKUP', 'stripe_transaction_backup');
define('LAZADA_CUST_RCD', 'customer_lazada_deals_transaction');
define('WEB_ORDER_REQ', 'website_order_request');
// define('LAZADA_CUST_RCD', 'customer_lazada_deals_transaction');
define('CRED_INV_PROD', 'cred_inv_products');
define('DEBIT_NOTES_INV', 'debit_notes_invoice');
define('DEBIT_INV_PROD', 'debit_inv_products');
define('LAZADA_ORDER_REQ', 'lazada_order_request');
define('ORDER_WAREHOUSE_TRANSFER_LOG', 'order_warehouse_transfer_log');

$connect = @mysqli_connect(dbhost, dbuser, dbpwd, dbname);
$finance_connect = @mysqli_connect(dbhost, dbuser, dbpwd, dbFinance);

if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
}

if ($finance_connect) {
    mysqli_set_charset($finance_connect, 'utf8mb4');
}
//define session
?>
