<?php
/**
 * GET /api/customer.php
 *
 * One Shopee customer record plus everything the customer page shows:
 *   - profile fields (from shopee_customer_info)
 *   - tags
 *   - segmentation / level / repeat labels
 *   - user record log entries
 *
 * Params: id (preferred) or username, log_limit
 */

define('CMS_API_ENTRY', 'customer');

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/customer_shape.php';

$keyRow = cmsApiAuthenticate($connect, 'read');

require_once ROOT . '/include/customer_tag.php';
require_once ROOT . '/include/user_record_log.php';

// ------------------------------------------------------------------
// Helpers (declared before use - PHP does not hoist conditional declarations)
// ------------------------------------------------------------------
if (!function_exists('cmsApiColumnExists')) {
    function cmsApiColumnExists($connection, $table, $column)
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        $column = mysqli_real_escape_string($connection, (string) $column);
        if ($table === '' || $column === '') {
            return false;
        }

        $result = @mysqli_query($connection, "SHOW COLUMNS FROM `" . $table . "` LIKE '" . $column . "'");

        return ($result instanceof mysqli_result && $result->num_rows > 0);
    }
}

if (!function_exists('cmsApiResolveUserRecordLogConnection')) {
    function cmsApiResolveUserRecordLogConnection($connect, $financeConnect)
    {
        $table = urlGetUserRecordLogTableName();

        foreach (array($connect, $financeConnect) as $candidate) {
            if (!($candidate instanceof mysqli)) {
                continue;
            }
            if (cmsApiColumnExists($candidate, $table, 'id')) {
                return $candidate;
            }
        }

        return $connect;
    }
}

if (!function_exists('cmsApiResolveUserRecordLogCustomerColumn')) {
    function cmsApiResolveUserRecordLogCustomerColumn($connection, $table)
    {
        foreach (array('shopee_cust_id', 'cust_id') as $column) {
            if (cmsApiColumnExists($connection, $table, $column)) {
                return $column;
            }
        }

        return '';
    }
}

$customerId = cmsApiQueryInt('id', 0, 0, null);
$username = cmsApiQueryValue('username', 190);
$logLimit = cmsApiQueryInt('log_limit', 20, 0, 200);

if ($customerId <= 0 && $username === '') {
    cmsApiFail(400, 'missing_parameter', 'Provide "id" or "username".');
}

$table = defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info';

if ($customerId > 0) {
    $where = "`id` = " . (int) $customerId;
} else {
    $where = "`buyer_username` = '" . mysqli_real_escape_string($finance_connect, $username) . "'";
}

$result = mysqli_query($finance_connect, "SELECT * FROM `" . $table . "` WHERE " . $where . " ORDER BY `id` DESC LIMIT 1");

if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
    cmsApiFail(404, 'customer_not_found', 'No Shopee customer matched that id / username.');
}

$row = $result->fetch_assoc();
$customerId = isset($row['id']) ? (int) $row['id'] : 0;

// ------------------------------------------------------------------
// Tags + labels
// ------------------------------------------------------------------
$tagMap = customerTagGetCustomerTagMap($connect, 'shopee', array($customerId));
$labelMap = customerLabelGetCustomerLabelMap($connect, 'shopee', array($customerId));

$lookupMaps = array();
foreach (array('pic' => array(USR_USER, 'name'), 'country' => array(COUNTRIES, 'nicename'), 'brand' => array(BRAND, 'name'), 'series' => array(BRD_SERIES, 'name')) as $field => $meta) {
    $lookupMaps[$field] = shopeeCustomerRecordResolveLookupMap($connect, array($row), $field, $meta[0], $meta[1], array($meta[1]));
}

$customer = cmsApiShapeCustomer(
    $row,
    isset($tagMap[$customerId]) ? $tagMap[$customerId] : array(),
    isset($labelMap[$customerId]) ? $labelMap[$customerId] : array(),
    $lookupMaps
);

// ------------------------------------------------------------------
// User record log
// ------------------------------------------------------------------
$logConnection = cmsApiResolveUserRecordLogConnection($connect, $finance_connect);
$logEntries = array();
$logTotal = 0;

if ($logConnection instanceof mysqli && $logLimit > 0) {
    $logTable = urlGetUserRecordLogTableName();
    $logColumn = cmsApiResolveUserRecordLogCustomerColumn($logConnection, $logTable);

    if ($logColumn !== '') {
        $conditions = array(
            "`status` = 'A'",
            "`" . $logColumn . "` = " . (int) $customerId,
            "(IFNULL(`content`,'') <> '' OR IFNULL(`attachment`,'') <> '')",
        );

        if (cmsApiColumnExists($logConnection, $logTable, 'log_type')) {
            $conditions[] = "(`log_type` IS NULL OR `log_type` <> 'tag')";
        }

        $whereSql = implode(' AND ', $conditions);
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) $logTable);

        $countResult = mysqli_query($logConnection, "SELECT COUNT(*) AS total_count FROM `" . $safeTable . "` WHERE " . $whereSql);
        if ($countResult instanceof mysqli_result && ($countRow = $countResult->fetch_assoc())) {
            $logTotal = isset($countRow['total_count']) ? (int) $countRow['total_count'] : 0;
        }

        $logResult = mysqli_query(
            $logConnection,
            "SELECT * FROM `" . $safeTable . "` WHERE " . $whereSql . " ORDER BY `created_at` DESC, `id` DESC LIMIT " . (int) $logLimit
        );

        if ($logResult instanceof mysqli_result) {
            $uploadWebDir = urlGetUserRecordLogUploadWebDir();

            while ($logRow = $logResult->fetch_assoc()) {
                $attachmentList = urlDecodeUserRecordLogAttachmentList(
                    isset($logRow['attachment']) ? $logRow['attachment'] : '',
                    isset($logRow['attachments']) ? $logRow['attachments'] : ''
                );

                $attachmentUrls = array();
                foreach ((array) $attachmentList as $attachmentPath) {
                    $attachmentPath = trim((string) $attachmentPath);
                    if ($attachmentPath === '') {
                        continue;
                    }
                    $attachmentUrls[] = urlBuildUserRecordLogAttachmentUrl($attachmentPath, $uploadWebDir);
                }

                $logEntries[] = array(
                    'id' => isset($logRow['id']) ? (int) $logRow['id'] : 0,
                    'summary' => isset($logRow['summary']) ? (string) $logRow['summary'] : '',
                    'content' => isset($logRow['content']) ? (string) $logRow['content'] : '',
                    'attachments' => $attachmentUrls,
                    'next_follow_up_date' => isset($logRow['next_follow_up_date']) ? (string) $logRow['next_follow_up_date'] : '',
                    'follow_up_times' => isset($logRow['follow_up_times']) ? (string) $logRow['follow_up_times'] : '',
                    'follow_up_day' => isset($logRow['follow_up_day']) ? (string) $logRow['follow_up_day'] : '',
                    'created_at' => isset($logRow['created_at']) ? (string) $logRow['created_at'] : '',
                    'updated_at' => isset($logRow['updated_at']) ? (string) $logRow['updated_at'] : '',
                    'created_by' => urlGetUserName($connect, isset($logRow['created_by']) ? $logRow['created_by'] : ''),
                );
            }
        }
    }
}

$customer['user_record_log'] = $logEntries;
$customer['user_record_log_total'] = $logTotal;

cmsApiOk(
    $customer,
    cmsApiMeta('customer', $keyRow, array(
        'source_table' => $table,
        'log_limit' => $logLimit,
    ))
);
