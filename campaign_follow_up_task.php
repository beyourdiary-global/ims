<?php
$pageTitle = "Campaign Follow-Up Task";
$currentPagePin = 153;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';
include_once ROOT . '/include/customer_tag.php';

$campaignId = (int) input('campaign_id');
if ($campaignId <= 0) {
    $campaignId = (int) post('campaign_id');
}

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$canSave = isActionAllowed('Add', $pinAccess) || isActionAllowed('Edit', $pinAccess);
$canDelete = isActionAllowed('Delete', $pinAccess);
$csrfToken = campaignCsrfToken('follow_up_task');
$backUrl = $SITEURL . '/campaign_table.php';
$pageUrl = $SITEURL . '/campaign_follow_up_task.php' . ($campaignId > 0 ? '?campaign_id=' . $campaignId : '');
$campaign = $campaignId > 0 ? campaignFetchCampaign($connect, $campaignId) : array();

function campaignFollowUpStatusLabel($row)
{
    $status = isset($row['follow_up_status']) ? (string) $row['follow_up_status'] : 'Pending';
    if ($status === 'Completed') {
        return 'Completed';
    }

    $date = isset($row['follow_up_date']) ? (string) $row['follow_up_date'] : '';
    if ($date === date('Y-m-d')) {
        return 'Due Today';
    }

    if ($date !== '' && $date < date('Y-m-d')) {
        return 'Overdue';
    }

    return 'Pending';
}

function campaignFollowUpSanitizePathSegment($value)
{
    $value = trim((string) $value);
    $value = str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = trim((string) $value, ". \t\n\r\0\x0B");
    return $value !== '' ? $value : 'unknown';
}

function campaignFollowUpSanitizeFileBaseName($value)
{
    $value = campaignFollowUpSanitizePathSegment($value);
    $value = str_replace(' ', '_', $value);
    return $value !== '' ? $value : 'attachment';
}

function campaignFollowUpFetchTask($connect, $taskId)
{
    $taskId = (int) $taskId;
    if ($taskId <= 0) {
        return array();
    }

    $sql = "SELECT
            cf.*,
            c.`campaign_name`,
            cc.`customer_id`,
            cc.`customer_name`,
            cc.`platform`,
            cm.`message_title`,
            u.`name` AS pic_name,
            u.`username` AS pic_username
        FROM `" . CAMPAIGN_FOLLOW_UP . "` cf
        INNER JOIN `" . CAMPAIGN . "` c ON c.`id` = cf.`campaign_id`
        INNER JOIN `" . CAMPAIGN_CUSTOMER . "` cc ON cc.`id` = cf.`campaign_customer_id`
        INNER JOIN `" . CAMPAIGN_MESSAGE . "` cm ON cm.`id` = cf.`campaign_message_id`
        LEFT JOIN `" . USR_USER . "` u ON u.`id` = cf.`pic_user_id`
        WHERE cf.`id` = ? AND cf.`status` = 'A'
        LIMIT 1";
    $stmt = $connect->prepare($sql);
    if (!$stmt) {
        return array();
    }

    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : array();
    $stmt->close();

    return is_array($row) ? $row : array();
}

function campaignFollowUpResolveCustomerDisplay($connect, $financeConnect, $customer)
{
    $customerName = trim((string) ($customer['customer_name'] ?? ''));
    $customerId = trim((string) ($customer['customer_id'] ?? ''));
    $platform = strtolower(trim((string) ($customer['platform'] ?? '')));

    if ($platform === 'shopee' && function_exists('customerLabelResolveShopeeCustomerMeta')) {
        $buyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $customerName, $customerId);
        if (!empty($buyerMeta) && isset($buyerMeta['buyer_username']) && trim((string) $buyerMeta['buyer_username']) !== '') {
            return trim((string) $buyerMeta['buyer_username']);
        }
    }

    if ($customerName !== '' && !ctype_digit($customerName)) {
        return $customerName;
    }

    if ($customerId !== '' && $platform === 'shopee' && function_exists('customerLabelResolveShopeeCustomerMeta')) {
        $buyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $customerId, $customerName);
        if (!empty($buyerMeta) && isset($buyerMeta['buyer_username']) && trim((string) $buyerMeta['buyer_username']) !== '') {
            return trim((string) $buyerMeta['buyer_username']);
        }
    }

    if ($customerName !== '') {
        return $customerName;
    }

    return $customerId;
}

function campaignFollowUpBuildCampaignLabelName($task)
{
    $existingLabel = trim((string) ($task['label_preview'] ?? ''));
    if ($existingLabel !== '') {
        return $existingLabel;
    }

    return (string) ($task['campaign_name'] ?? '') . '-' . (string) ($task['follow_up_date'] ?? '') . '-notified';
}

function campaignFollowUpEnsureCustomerTag($connect, $tagName)
{
    $tagName = trim((string) $tagName);
    if ($tagName === '' || !($connect instanceof mysqli) || !defined('TAG')) {
        return array('success' => false, 'tag_id' => 0, 'tag_row' => null, 'query' => '');
    }

    $existingTag = function_exists('customerTagFindTagByName') ? customerTagFindTagByName($connect, $tagName) : null;
    if (is_array($existingTag) && (int) ($existingTag['id'] ?? 0) > 0) {
        $tagId = (int) $existingTag['id'];
        $tagStatus = trim((string) ($existingTag['status'] ?? ''));
        if ($tagStatus === 'A') {
            return array('success' => true, 'tag_id' => $tagId, 'tag_row' => $existingTag, 'query' => '');
        }

        $query = "UPDATE `" . TAG . "` SET `status`='A', `update_by`='" . $connect->real_escape_string((string) USER_ID) . "', `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`='" . $tagId . "'";
        if (mysqli_query($connect, $query)) {
            $reactivatedTag = function_exists('customerTagGetTagById') ? customerTagGetTagById($connect, $tagId) : $existingTag;
            return array('success' => true, 'tag_id' => $tagId, 'tag_row' => $reactivatedTag, 'query' => $query);
        }

        return array('success' => false, 'tag_id' => 0, 'tag_row' => null, 'query' => $query);
    }

    $safeTagName = $connect->real_escape_string($tagName);
    $safeUserId = $connect->real_escape_string((string) USER_ID);
    $query = "INSERT INTO `" . TAG . "` (`name`,`remark`,`create_by`,`create_date`,`create_time`,`status`) VALUES ('" . $safeTagName . "','Campaign follow-up tag','" . $safeUserId . "',CURDATE(),CURTIME(),'A')";
    if (!mysqli_query($connect, $query)) {
        return array('success' => false, 'tag_id' => 0, 'tag_row' => null, 'query' => $query);
    }

    $tagId = (int) mysqli_insert_id($connect);
    return array(
        'success' => $tagId > 0,
        'tag_id' => $tagId,
        'tag_row' => function_exists('customerTagGetTagById') ? customerTagGetTagById($connect, $tagId) : array('id' => $tagId, 'name' => $tagName),
        'query' => $query,
    );
}

function campaignFollowUpResolveSourceCustomerForTag($connect, $financeConnect, $task)
{
    $platform = strtolower(trim((string) ($task['platform'] ?? '')));
    $customerId = trim((string) ($task['customer_id'] ?? ''));
    $customerName = trim((string) ($task['customer_name'] ?? ''));

    if ($platform === '') {
        return array('platform' => '', 'customer_id' => 0);
    }

    if ($platform === 'shopee' && function_exists('customerLabelResolveShopeeCustomerMeta')) {
        $buyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $customerId, $customerName);
        $sourceCustomerId = (int) ($buyerMeta['id'] ?? 0);
        return array('platform' => $platform, 'customer_id' => $sourceCustomerId);
    }

    $platformConfigMap = array(
        'lazada' => array(
            'conn' => $connect,
            'table' => defined('LAZADA_CUST_RCD') ? LAZADA_CUST_RCD : 'customer_lazada_deals_transaction',
            'columns' => array('id', 'lcr_id', 'name', 'customer_name'),
        ),
        'website' => array(
            'conn' => $connect,
            'table' => defined('WEB_CUST_RCD') ? WEB_CUST_RCD : 'customer_website_deals_transaction',
            'columns' => array('id', 'cust_id', 'name', 'customer_name'),
        ),
        'facebook' => array(
            'conn' => $connect,
            'table' => defined('FB_CUST_DEALS') ? FB_CUST_DEALS : 'customer_facebook_deals_transaction',
            'columns' => array('id', 'name', 'customer_name'),
        ),
    );

    if (!isset($platformConfigMap[$platform])) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $platformConfig = $platformConfigMap[$platform];
    $customerConn = $platformConfig['conn'];
    $customerTable = $platformConfig['table'];
    if (!($customerConn instanceof mysqli) || !function_exists('campaignTableExists') || !campaignTableExists($customerConn, $customerTable)) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $lookupValues = array_values(array_unique(array_filter(array($customerId, $customerName), function ($value) {
        return trim((string) $value) !== '';
    })));
    if (empty($lookupValues)) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $whereParts = array();
    foreach ($platformConfig['columns'] as $column) {
        if (!function_exists('campaignColumnExists') || !campaignColumnExists($customerConn, $customerTable, $column)) {
            continue;
        }

        $safeValues = array();
        foreach ($lookupValues as $lookupValue) {
            $safeValues[] = "'" . mysqli_real_escape_string($customerConn, (string) $lookupValue) . "'";
        }
        $whereParts[] = "`" . str_replace('`', '``', $column) . "` IN (" . implode(',', $safeValues) . ")";
    }

    if (empty($whereParts)) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $whereClause = "(" . implode(' OR ', $whereParts) . ")";
    if (function_exists('campaignColumnExists') && campaignColumnExists($customerConn, $customerTable, 'status')) {
        $whereClause .= " AND `status`='A'";
    }

    $sql = "SELECT `id` FROM `" . str_replace('`', '``', $customerTable) . "` WHERE " . $whereClause . " ORDER BY `id` ASC LIMIT 1";
    $result = mysqli_query($customerConn, $sql);
    if ($result instanceof mysqli_result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return array('platform' => $platform, 'customer_id' => (int) ($row['id'] ?? 0));
    }

    return array('platform' => $platform, 'customer_id' => 0);
}

function campaignFollowUpAssignCampaignTagToCustomer($connect, $financeConnect, $task, $tagName, $pageTitle)
{
    if (!function_exists('customerTagAssignToCustomer')) {
        return array('success' => false, 'message' => 'Customer tag helper is not available.', 'tag_row' => null);
    }

    if (!function_exists('customerTagTableExists') || !customerTagTableExists($connect)) {
        return array('success' => false, 'message' => 'Customer tag assignment table is not ready yet. Please run insert_table.php first.', 'tag_row' => null);
    }

    $tagResult = campaignFollowUpEnsureCustomerTag($connect, $tagName);
    if (empty($tagResult['success']) || (int) ($tagResult['tag_id'] ?? 0) <= 0) {
        return array('success' => false, 'message' => 'Unable to prepare campaign tag.', 'tag_row' => null);
    }

    $sourceCustomer = campaignFollowUpResolveSourceCustomerForTag($connect, $financeConnect, $task);
    $sourcePlatform = trim((string) ($sourceCustomer['platform'] ?? ''));
    $sourceCustomerId = (int) ($sourceCustomer['customer_id'] ?? 0);
    if ($sourcePlatform === '' || $sourceCustomerId <= 0) {
        return array('success' => false, 'message' => 'Unable to locate the source customer record for campaign tag assignment.', 'tag_row' => $tagResult['tag_row']);
    }

    $assignResult = customerTagAssignToCustomer($connect, $sourcePlatform, $sourceCustomerId, (int) $tagResult['tag_id']);
    if (empty($assignResult['success'])) {
        return array('success' => false, 'message' => 'Unable to assign campaign tag to customer.', 'tag_row' => $tagResult['tag_row']);
    }

    $customerDisplayName = campaignFollowUpResolveCustomerDisplay($connect, $financeConnect, $task);
    customerTagWriteAuditLog(
        $connect,
        $pageTitle,
        'edit',
        USER_NAME . ' assigned campaign follow-up tag [<b>' . htmlspecialchars((string) (($tagResult['tag_row']['name'] ?? $tagName)), ENT_QUOTES, 'UTF-8') . '</b>] to <b>' . htmlspecialchars(customerTagBuildCustomerLabel($pageTitle, $customerDisplayName, $sourceCustomerId), ENT_QUOTES, 'UTF-8') . '</b>.',
        isset($assignResult['query']) ? (string) $assignResult['query'] : '',
        customerTagGetAssignmentTable()
    );

    return array('success' => true, 'message' => '', 'tag_row' => $tagResult['tag_row']);
}

function campaignFollowUpRenderLabelBadges($labelName)
{
    $labelName = trim((string) $labelName);
    if ($labelName === '') {
        return '';
    }

    return customerTagRenderBadges(
        array(array('name' => $labelName)),
        'campaign-follow-up-label-wrap',
        'campaign-follow-up-label-badge'
    );
}

function campaignFollowUpDeleteCustomerAssignment($connect, $task)
{
    $campaignId = (int) ($task['campaign_id'] ?? 0);
    $campaignCustomerId = (int) ($task['campaign_customer_id'] ?? 0);
    if ($campaignId <= 0 || $campaignCustomerId <= 0) {
        return false;
    }

    $userId = (string) USER_ID;
    $deletedTasks = 0;
    $deletedCustomer = false;

    $connect->begin_transaction();

    try {
        $taskStmt = $connect->prepare("UPDATE `" . CAMPAIGN_FOLLOW_UP . "` SET `status`='D', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `campaign_id`=? AND `campaign_customer_id`=? AND `status`='A'");
        if (!$taskStmt) {
            throw new Exception($connect->error);
        }
        $taskStmt->bind_param('sii', $userId, $campaignId, $campaignCustomerId);
        if (!$taskStmt->execute()) {
            $taskStmt->close();
            throw new Exception($taskStmt->error);
        }
        $deletedTasks = (int) $taskStmt->affected_rows;
        $taskStmt->close();

        $customerStmt = $connect->prepare("UPDATE `" . CAMPAIGN_CUSTOMER . "` SET `follow_up_status`='Pending', `status`='D', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `campaign_id`=? AND `status`='A'");
        if (!$customerStmt) {
            throw new Exception($connect->error);
        }
        $customerStmt->bind_param('sii', $userId, $campaignCustomerId, $campaignId);
        if (!$customerStmt->execute()) {
            $customerStmt->close();
            throw new Exception($customerStmt->error);
        }
        $deletedCustomer = $customerStmt->affected_rows > 0;
        $customerStmt->close();

        $connect->commit();

        return array(
            'deleted_tasks' => $deletedTasks,
            'deleted_customer' => $deletedCustomer,
        );
    } catch (Exception $e) {
        $connect->rollback();
        return false;
    }
}

function campaignFollowUpUploadScreenshot($fieldName, $task)
{
    if (empty($_FILES[$fieldName]) || !is_array($_FILES[$fieldName]) || (int) $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ((int) $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Screenshot upload failed.');
    }

    if ((int) $_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        throw new Exception('Screenshot file is too large.');
    }

    $ext = strtolower(pathinfo((string) $_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'pdf'), true)) {
        throw new Exception('Only jpg, jpeg, png, webp, and pdf files are allowed.');
    }

    $campaignName = campaignFollowUpSanitizePathSegment($task['campaign_name'] ?? '');
    $followUpDate = campaignFollowUpSanitizePathSegment($task['follow_up_date'] ?? date('Y-m-d'));
    $customerName = campaignFollowUpSanitizePathSegment($task['customer_name'] ?? '');

    $relativeDir = 'attachment/campaign/' . $campaignName . '/' . $followUpDate . '/' . $customerName;
    $absoluteDir = ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
        throw new Exception('Unable to create screenshot folder.');
    }

    $originalBaseName = pathinfo((string) $_FILES[$fieldName]['name'], PATHINFO_FILENAME);
    $safeBaseName = campaignFollowUpSanitizeFileBaseName($originalBaseName);
    $fileName = $safeBaseName . '.' . $ext;
    $duplicateCounter = 1;
    while (file_exists($absoluteDir . DIRECTORY_SEPARATOR . $fileName)) {
        $fileName = $safeBaseName . '_' . $duplicateCounter . '.' . $ext;
        $duplicateCounter++;
    }
    $targetPath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
        throw new Exception('Unable to save screenshot.');
    }

    return $relativeDir . '/' . $fileName;
}

$followUpDeleteRequested = post('actionBtn') === 'deleteTask' || post('act') === 'D';
$followUpDeleteUsesCommonDialog = post('act') === 'D';
$followUpSaveRequested = post('actionBtn') === 'saveFollowUp';

if (($followUpSaveRequested || $followUpDeleteRequested)
    && (($followUpSaveRequested && !campaignVerifyCsrf('follow_up_task', post('csrf_token')))
        || (!$canSave && $followUpSaveRequested)
        || (!$canDelete && $followUpDeleteRequested))) {
    campaignSetPopup('Unable to update Campaign Follow-Up Task.', $pageUrl, 'ErrMO');
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

if ($followUpDeleteRequested) {
    $taskId = 0;
    $postedToken = '';

    if ($followUpDeleteUsesCommonDialog) {
        $deletePayloadParts = explode('|', trim((string) post('id')), 2);
        $taskId = isset($deletePayloadParts[0]) ? (int) $deletePayloadParts[0] : 0;
        $postedToken = isset($deletePayloadParts[1]) ? (string) $deletePayloadParts[1] : '';
    } else {
        $taskId = (int) post('task_id');
        $postedToken = (string) post('csrf_token');
    }

    if (!hash_equals($csrfToken, $postedToken) || !$canDelete) {
        if ($followUpDeleteUsesCommonDialog) {
            http_response_code(403);
            echo 'Unable to delete Campaign Follow-Up Customer.';
            exit();
        }
        campaignSetPopup('Unable to delete Campaign Follow-Up Customer.', $pageUrl, 'ErrMO');
    } else {
        $task = campaignFollowUpFetchTask($connect, $taskId);
        if (empty($task)) {
            if ($followUpDeleteUsesCommonDialog) {
                http_response_code(404);
                echo 'Campaign Follow-Up Customer not found.';
                exit();
            }
            campaignSetPopup('Campaign Follow-Up Customer not found.', $pageUrl, 'ErrMO');
        } else {
            $deleteResult = campaignFollowUpDeleteCustomerAssignment($connect, $task);
            if ($deleteResult === false) {
                if ($followUpDeleteUsesCommonDialog) {
                    http_response_code(500);
                    echo 'Failed to delete Campaign Follow-Up Customer.';
                    exit();
                }
                campaignSetPopup('Failed to delete Campaign Follow-Up Customer.', $pageUrl, 'ErrMO');
            } else {
                $customerName = campaignFollowUpResolveCustomerDisplay(
                    $connect,
                    isset($finance_connect) ? $finance_connect : $connect,
                    $task
                );
                $campaignCustomerId = (int) ($task['campaign_customer_id'] ?? 0);
                $deletedTasks = (int) ($deleteResult['deleted_tasks'] ?? 0);
                $query = "DELETE campaign follow-up customer assignment task_id=" . $taskId . ", campaign_customer_id=" . $campaignCustomerId . ", deactivated_tasks=" . $deletedTasks;
                campaignAudit($connect, $pageTitle, 'delete', USER_NAME . " deleted campaign follow-up customer " . ($customerName !== '' ? ('<b>' . campaignH($customerName) . '</b> ') : '') . "(campaign_customer_id=" . $campaignCustomerId . ") and moved it back to unassigned.", $query, CAMPAIGN_FOLLOW_UP);
                if ($followUpDeleteUsesCommonDialog) {
                    echo 'OK';
                    exit();
                }
                campaignSetPopup('Successful Delete Campaign Follow-Up Customer', $pageUrl, 'ErrMO');
            }
        }
    }

    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

if ($followUpSaveRequested) {
    $taskId = (int) post('task_id');
    $task = campaignFollowUpFetchTask($connect, $taskId);
    if (!empty($task)) {
        try {
            $remark = trim((string) post('remark'));
            $uploadPath = campaignFollowUpUploadScreenshot('screenshot', $task);
            $existingScreenshot = isset($task['screenshot_path']) ? trim((string) $task['screenshot_path']) : '';
            if ($uploadPath === '' && $existingScreenshot === '') {
                throw new Exception('Screenshot / Attachment is required.');
            }

            $screenshotPath = $uploadPath !== '' ? $uploadPath : $existingScreenshot;
            $status = 'Completed';
            $labelPreview = campaignFollowUpBuildCampaignLabelName($task);
            $completedBy = (string) USER_ID;
            $completedDate = date('Y-m-d');
            $completedTime = date('H:i:s');
            $userId = (string) USER_ID;

            $connect->begin_transaction();

            $campaignTagResult = campaignFollowUpAssignCampaignTagToCustomer(
                $connect,
                isset($finance_connect) ? $finance_connect : $connect,
                $task,
                $labelPreview,
                $pageTitle
            );
            if (empty($campaignTagResult['success'])) {
                throw new Exception((string) ($campaignTagResult['message'] ?? 'Unable to assign campaign tag.'));
            }

            $stmt = $connect->prepare("UPDATE `" . CAMPAIGN_FOLLOW_UP . "` SET `screenshot_path`=?, `remark`=?, `follow_up_status`=?, `label_preview`=?, `completed_by`=?, `completed_date`=?, `completed_time`=?, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=?");
            if (!$stmt) {
                throw new Exception($connect->error);
            }
            $stmt->bind_param('ssssssssi', $screenshotPath, $remark, $status, $labelPreview, $completedBy, $completedDate, $completedTime, $userId, $taskId);
            $stmt->execute();
            $stmt->close();

            $connect->commit();

            campaignAudit($connect, $pageTitle, 'edit', USER_NAME . " updated campaign follow-up task " . $taskId . ".", '', CAMPAIGN_FOLLOW_UP);
            campaignSetPopup('Successful Save Follow Up', $pageUrl, 'ErrMO');
        } catch (Exception $e) {
            if ($connect instanceof mysqli && $connect->errno !== null) {
                @mysqli_rollback($connect);
            }
            campaignSetPopup($e->getMessage(), $pageUrl, 'ErrMO');
        }
    }
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : date('m');
$selectedYear = isset($_GET['year']) ? trim((string) $_GET['year']) : date('Y');
$selectedDate = trim((string) input('date'));
$currentYear = date('Y');
$selectedMonth = ($selectedMonth === '' || preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth)) ? $selectedMonth : date('m');
$selectedYear = ($selectedYear === '' || preg_match('/^\d{4}$/', $selectedYear)) ? $selectedYear : $currentYear;
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : '';
$filterAction = trim((string) input('filter_action'));
if ($filterAction === 'reset') {
    campaignAudit($connect, $pageTitle, 'reset', USER_NAME . " reset campaign follow-up task filters.", '', CAMPAIGN_FOLLOW_UP);
    $selectedMonth = date('m');
    $selectedYear = $currentYear;
    $selectedDate = '';
} elseif (isset($_GET['date']) || isset($_GET['month']) || isset($_GET['year'])) {
    campaignAudit($connect, $pageTitle, 'search', USER_NAME . " searched campaign follow-up tasks.", '', CAMPAIGN_FOLLOW_UP);
}

$where = array("cf.`status` = 'A'");
$types = '';
$values = array();
if ($campaignId > 0) {
    $where[] = "cf.`campaign_id` = ?";
    $types .= 'i';
    $values[] = $campaignId;
}
if ($selectedDate !== '') {
    $where[] = "DATE(cf.`follow_up_date`) = ?";
    $types .= 's';
    $values[] = $selectedDate;
} else {
    if ($selectedMonth !== '') {
        $where[] = "MONTH(cf.`follow_up_date`) = ?";
        $types .= 's';
        $values[] = $selectedMonth;
    }
    if ($selectedYear !== '') {
        $where[] = "YEAR(cf.`follow_up_date`) = ?";
        $types .= 's';
        $values[] = $selectedYear;
    }
}

$taskRows = array();
$sql = "SELECT
        cf.*,
        c.`campaign_name`,
        cc.`customer_id`,
        cc.`customer_name`,
        cc.`platform`,
        cm.`message_title`,
        u.`name` AS pic_name,
        u.`username` AS pic_username
    FROM `" . CAMPAIGN_FOLLOW_UP . "` cf
    INNER JOIN `" . CAMPAIGN . "` c ON c.`id` = cf.`campaign_id`
    INNER JOIN `" . CAMPAIGN_CUSTOMER . "` cc ON cc.`id` = cf.`campaign_customer_id`
    INNER JOIN `" . CAMPAIGN_MESSAGE . "` cm ON cm.`id` = cf.`campaign_message_id`
    LEFT JOIN `" . USR_USER . "` u ON u.`id` = cf.`pic_user_id`
    WHERE " . implode(' AND ', $where) . "
    ORDER BY cf.`follow_up_date` ASC, cf.`id` ASC";
$stmt = $connect->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $params = array($types);
        foreach ($values as $index => $value) {
            $params[] = &$values[$index];
        }
        call_user_func_array(array($stmt, 'bind_param'), $params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $row['display_customer_name'] = campaignFollowUpResolveCustomerDisplay(
            $connect,
            isset($finance_connect) ? $finance_connect : $connect,
            $row
        );
        $taskRows[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .campaign-follow-up-page .filter-card {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            background: #fff;
        }

        .campaign-follow-up-page .follow-up-date-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(0, 280px);
            gap: 16px;
        }

        .campaign-follow-up-page .follow-up-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
            border-radius: 999px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .campaign-follow-up-page .action-col {
            width: 1%;
            min-width: 180px;
            text-align: left;
        }

        .campaign-follow-up-page .arrival-follow-up-summary {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .campaign-follow-up-page .arrival-follow-up-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .campaign-follow-up-page .arrival-follow-up-summary-row:last-child {
            margin-bottom: 0;
        }

        .campaign-follow-up-page .arrival-follow-up-summary-label {
            color: #6c757d;
        }

        .campaign-follow-up-page .arrival-follow-up-preview {
            display: none;
            margin-top: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #f8f9fa;
            padding: 0.75rem;
        }

        .campaign-follow-up-page .arrival-follow-up-preview img {
            display: block;
            max-width: 100%;
            max-height: 260px;
            margin: 0 auto;
            border-radius: 0.375rem;
            object-fit: contain;
        }

        .campaign-follow-up-page .arrival-follow-up-preview-note {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
        }

        .campaign-follow-up-page .arrival-follow-up-preview-link {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            word-break: break-all;
        }

        .campaign-follow-up-page .customer-follow-up-required-star {
            color: #dc3545;
            margin-left: 2px;
        }

        .campaign-follow-up-page .customer-follow-up-field-error {
            display: none;
            color: #dc3545;
            font-size: 0.82rem;
            margin-top: 4px;
        }

        .campaign-follow-up-page .customer-follow-up-field-error.is-visible {
            display: block;
        }

        .campaign-follow-up-page .follow-up-screenshot-link {
            word-break: break-all;
        }

        .campaign-follow-up-page .campaign-follow-up-label-wrap {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: flex-start;
        }

        .campaign-follow-up-page .campaign-follow-up-label-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef6ff;
            border: 1px solid #b8d3ff;
            color: #2a5ea8;
            font-size: 0.92rem;
            line-height: 1.2;
            white-space: normal;
            word-break: break-word;
        }

        @media (max-width: 1199px) {
            .campaign-follow-up-page .follow-up-date-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .campaign-follow-up-page .follow-up-date-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<script>
    
    $(document).ready(function () {
        createSortingTable('campaign_follow_up_table', {
            searching: true,
            order: [[1, 'asc']],
            orderFixed: {
                pre: [[0, 'asc']]
            },
            columnDefs: [
                { visible: false, searchable: false, targets: [0] },
                { orderable: false, searchable: false, targets: [2] }
            ]
        });
    });
</script>
<body class="campaign-follow-up-page">
    
<div class="page-load-cover campaign-follow-up-page">
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row"><p><a href="<?= $SITEURL ?>/campaign_table.php">Campaign</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Follow-Up Task</p></div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <div><h2>Campaign Follow-Up Task</h2><?php if (!empty($campaign)) campaignRenderBadge($campaign); ?></div>
                    </div>
                </div>
            </div>

            <table class="table table-striped" id="campaign_follow_up_table">
                <thead>
                    <tr>
                        <th class="hideColumn">Status Priority</th>
                        <th>S/N</th>
                        <th class="action-col">Action</th>
                        <th>Customer</th>
                        <th>Platform</th>
                        <th>Message Shortcut</th>
                        <th>Follow-Up Date</th>
                        <th>PIC</th>
                        <th>Screenshot</th>
                        <th>Follow-Up Status</th>
                        <th>Label</th>
                        <th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                <?php $num = 1; foreach ($taskRows as $row): ?>
                    <?php
                    $displayCustomerName = trim((string) ($row['display_customer_name'] ?? ''));
                    $labelPreviewHtml = campaignFollowUpRenderLabelBadges($row['label_preview'] ?? '');
                    $picName = trim((string) (($row['pic_name'] ?? '') !== '' ? $row['pic_name'] : ($row['pic_username'] ?? '')));
                    $statusLabel = campaignFollowUpStatusLabel($row);
                    $isCompletedStatus = $statusLabel === 'Completed';
                    $statusPriority = $isCompletedStatus ? 1 : 0;
                    $existingScreenshotPath = trim((string) ($row['screenshot_path'] ?? ''));
                    $existingScreenshotUrl = $existingScreenshotPath !== '' ? $SITEURL . '/' . $existingScreenshotPath : '';
                    ?>
                    <tr>
                        <td class="hideColumn"><?= $statusPriority ?></td>
                        <td><?= $num++ ?></td>
                        <td class="btn-container btn-action-row">
                            <button
                                class="btn btn-primary me-1"
                                type="button"
                                title="View"
                                data-bs-toggle="modal"
                                data-bs-target="#campaignFollowUpModal"
                                data-modal-mode="view"
                                data-task-id="<?= (int) $row['id'] ?>"
                                data-customer-name="<?= campaignH($displayCustomerName) ?>"
                                data-platform="<?= campaignH($row['platform'] ?? '') ?>"
                                data-message-title="<?= campaignH($row['message_title'] ?? '') ?>"
                                data-follow-up-date="<?= campaignH($row['follow_up_date'] ?? '') ?>"
                                data-pic-name="<?= campaignH($picName) ?>"
                                data-status-label="<?= campaignH($statusLabel) ?>"
                                data-label-preview="<?= campaignH($row['label_preview'] ?? '') ?>"
                                data-remark="<?= campaignH($row['remark'] ?? '') ?>"
                                data-existing-attachment-path="<?= campaignH($existingScreenshotPath) ?>"
                                data-existing-attachment-url="<?= campaignH($existingScreenshotUrl) ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($canSave && !$isCompletedStatus): ?>
                                <button
                                    class="btn btn-success me-1"
                                    type="button"
                                    title="Mark Follow Up"
                                    data-bs-toggle="modal"
                                    data-bs-target="#campaignFollowUpModal"
                                    data-modal-mode="mark"
                                    data-task-id="<?= (int) $row['id'] ?>"
                                    data-customer-name="<?= campaignH($displayCustomerName) ?>"
                                    data-platform="<?= campaignH($row['platform'] ?? '') ?>"
                                    data-message-title="<?= campaignH($row['message_title'] ?? '') ?>"
                                    data-follow-up-date="<?= campaignH($row['follow_up_date'] ?? '') ?>"
                                    data-pic-name="<?= campaignH($picName) ?>"
                                    data-status-label="<?= campaignH($statusLabel) ?>"
                                    data-label-preview="<?= campaignH($row['label_preview'] ?? '') ?>"
                                    data-remark="<?= campaignH($row['remark'] ?? '') ?>"
                                    data-existing-attachment-path="<?= campaignH($existingScreenshotPath) ?>"
                                    data-existing-attachment-url="<?= campaignH($existingScreenshotUrl) ?>">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($canSave): ?>
                                <button
                                    class="btn btn-warning me-1"
                                    type="button"
                                    title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#campaignFollowUpModal"
                                    data-modal-mode="edit"
                                    data-task-id="<?= (int) $row['id'] ?>"
                                    data-customer-name="<?= campaignH($displayCustomerName) ?>"
                                    data-platform="<?= campaignH($row['platform'] ?? '') ?>"
                                    data-message-title="<?= campaignH($row['message_title'] ?? '') ?>"
                                    data-follow-up-date="<?= campaignH($row['follow_up_date'] ?? '') ?>"
                                    data-pic-name="<?= campaignH($picName) ?>"
                                    data-status-label="<?= campaignH($statusLabel) ?>"
                                    data-label-preview="<?= campaignH($row['label_preview'] ?? '') ?>"
                                    data-remark="<?= campaignH($row['remark'] ?? '') ?>"
                                    data-existing-attachment-path="<?= campaignH($existingScreenshotPath) ?>"
                                    data-existing-attachment-url="<?= campaignH($existingScreenshotUrl) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <?php
                                $deletePayload = (string) ((int) $row['id']) . '|' . (string) $csrfToken;
                                $deleteOnClick = 'confirmationDialog('
                                    . campaignJson($deletePayload) . ', '
                                    . campaignJson(array($displayCustomerName)) . ', '
                                    . campaignJson('Campaign Follow-Up Customer') . ', '
                                    . campaignJson($pageUrl) . ', '
                                    . campaignJson($pageUrl) . ', '
                                    . campaignJson('D')
                                    . ');';
                                ?>
                                <button class="btn btn-danger me-1" type="button" title="Delete" onclick="<?= campaignH($deleteOnClick) ?>"><i class="fas fa-trash-alt"></i></button>
                            <?php endif; ?>
                        </td>
                        <td><?= campaignH($displayCustomerName) ?></td>
                        <td><?= campaignH($row['platform'] ?? '') ?></td>
                        <td><?= campaignH($row['message_title'] ?? '') ?></td>
                        <td><?= campaignH($row['follow_up_date'] ?? '') ?></td>
                        <td><?= campaignH($picName) ?></td>
                        <td><?php if ($existingScreenshotPath !== ''): ?><a class="follow-up-screenshot-link" target="_blank" href="<?= campaignH($existingScreenshotUrl) ?>"><?= campaignH($existingScreenshotPath) ?></a><?php endif; ?></td>
                        <td><?= campaignH($statusLabel) ?></td>
                        <td><?= $labelPreviewHtml ?></td>
                        <td><?= campaignH($row['remark'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php campaignRenderBackButton($backUrl, false); ?>
        </div>
    </div>
</div>

<div class="modal fade" id="campaignFollowUpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data" id="campaign_follow_up_modal_form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignFollowUpModalTitle">Campaign Follow-Up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
                    <input type="hidden" name="campaign_id" value="<?= (int) $campaignId ?>">
                    <input type="hidden" name="task_id" id="campaign_follow_up_task_id" value="">
                    <input type="hidden" name="actionBtn" value="saveFollowUp">

                    <div class="arrival-follow-up-summary">
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Customer:</span>
                            <span id="campaign_follow_up_customer_text">-</span>
                        </div>
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Platform:</span>
                            <span id="campaign_follow_up_platform_text">-</span>
                        </div>
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Message Shortcut:</span>
                            <span id="campaign_follow_up_message_text">-</span>
                        </div>
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Follow-Up Date:</span>
                            <span id="campaign_follow_up_date_text">-</span>
                        </div>
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">PIC:</span>
                            <span id="campaign_follow_up_pic_text">-</span>
                        </div>
                        <div class="arrival-follow-up-summary-row">
                            <span class="arrival-follow-up-summary-label">Follow-Up Status:</span>
                            <span id="campaign_follow_up_status_text">-</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="campaign_follow_up_attachment">
                            Screenshot / Attachment<span class="customer-follow-up-required-star">*</span>
                        </label>
                        <input type="file" class="form-control" id="campaign_follow_up_attachment" name="screenshot" required>
                        <div class="customer-follow-up-field-error" id="campaign_follow_up_attachment_error">Screenshot / Attachment is required.</div>
                        <div class="arrival-follow-up-preview" id="campaign_follow_up_attachment_preview_wrap">
                            <a id="campaign_follow_up_existing_attachment_link" class="arrival-follow-up-preview-link d-none" href="#" target="_blank" rel="noopener noreferrer"></a>
                            <img id="campaign_follow_up_attachment_preview_img" alt="Follow-Up Attachment Preview">
                            <div class="arrival-follow-up-preview-note d-none" id="campaign_follow_up_attachment_preview_note"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="campaign_follow_up_remark">Remark</label>
                        <textarea class="form-control" id="campaign_follow_up_remark" name="remark" rows="4"></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="text-transform: none !important;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="campaign_follow_up_submit_btn" style="text-transform: none !important;">Save Follow Up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var campaignFollowUpHasAttemptedSubmit = false;

    function setCampaignFollowUpFieldError(fieldId, errorId, hasError) {
        var field = document.getElementById(fieldId);
        var error = document.getElementById(errorId);

        if (field) {
            field.classList.toggle('is-invalid', hasError);
        }

        if (error) {
            error.classList.toggle('is-visible', hasError);
        }
    }

    function clearCampaignFollowUpRequiredErrors() {
        campaignFollowUpHasAttemptedSubmit = false;
        setCampaignFollowUpFieldError('campaign_follow_up_attachment', 'campaign_follow_up_attachment_error', false);
    }

    function validateCampaignFollowUpRequiredFields() {
        var attachmentInput = document.getElementById('campaign_follow_up_attachment');
        var attachmentMissing = attachmentInput
            && attachmentInput.required
            && (!attachmentInput.files || attachmentInput.files.length === 0);

        setCampaignFollowUpFieldError('campaign_follow_up_attachment', 'campaign_follow_up_attachment_error', attachmentMissing);

        if (attachmentMissing) {
            attachmentInput.focus();
            return false;
        }

        return true;
    }

    function bindCampaignFollowUpAttachmentPreview(inputId, wrapId, imageId, noteId, existingLinkId) {
        var fileInput = document.getElementById(inputId);
        var previewWrap = document.getElementById(wrapId);
        var previewImage = document.getElementById(imageId);
        var previewNote = document.getElementById(noteId);
        var existingLink = existingLinkId ? document.getElementById(existingLinkId) : null;
        if (!fileInput || !previewWrap || !previewImage || !previewNote) {
            return null;
        }

        var currentObjectUrl = null;
        var getExistingAttachment = function () {
            return {
                url: fileInput.getAttribute('data-existing-url') || '',
                path: fileInput.getAttribute('data-existing-path') || ''
            };
        };
        var isImagePath = function (path) {
            return /\.(png|jpe?g|webp|gif)$/i.test(path || '');
        };
        var clearExistingLink = function () {
            if (existingLink) {
                existingLink.removeAttribute('href');
                existingLink.textContent = '';
                existingLink.classList.add('d-none');
            }
        };
        var clearPreview = function () {
            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
                currentObjectUrl = null;
            }
            previewImage.removeAttribute('src');
            previewImage.style.display = 'none';
            previewNote.textContent = '';
            previewNote.classList.add('d-none');
            previewWrap.style.display = 'none';
        };
        var showExistingAttachment = function () {
            var existing = getExistingAttachment();

            clearPreview();
            clearExistingLink();

            if (!existing.url || !existing.path) {
                return;
            }

            if (existingLink) {
                existingLink.href = existing.url;
                existingLink.textContent = 'Previous Attachment: ' + existing.path;
                existingLink.classList.remove('d-none');
            }

            if (isImagePath(existing.path)) {
                previewImage.src = existing.url;
                previewImage.style.display = 'block';
                previewNote.textContent = '';
                previewNote.classList.add('d-none');
                previewWrap.style.display = 'block';
                return;
            }

            previewImage.removeAttribute('src');
            previewImage.style.display = 'none';
            previewNote.textContent = 'Previous attachment is available. Click the link above to view it.';
            previewNote.classList.remove('d-none');
            previewWrap.style.display = 'block';
        };

        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!file) {
                showExistingAttachment();
                return;
            }

            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
                currentObjectUrl = null;
            }

            clearExistingLink();

            if (file.type.indexOf('image/') === 0) {
                currentObjectUrl = URL.createObjectURL(file);
                previewImage.src = currentObjectUrl;
                previewImage.style.display = 'block';
                previewNote.textContent = '';
                previewNote.classList.add('d-none');
                previewWrap.style.display = 'block';
                return;
            }

            previewImage.removeAttribute('src');
            previewImage.style.display = 'none';
            previewNote.textContent = 'Preview is available for image files only.';
            previewNote.classList.remove('d-none');
            previewWrap.style.display = 'block';
        });

        window.addEventListener('beforeunload', clearPreview);
        return {
            clearPreview: clearPreview,
            showExistingAttachment: showExistingAttachment
        };
    }

    function clearCampaignFollowUpSingleFieldErrorIfFilled() {
        var attachmentField = document.getElementById('campaign_follow_up_attachment');
        if (!attachmentField) {
            return;
        }

        if (!campaignFollowUpHasAttemptedSubmit) {
            return;
        }

        var hasFile = attachmentField.files && attachmentField.files.length > 0;
        var notRequired = !attachmentField.required;
        if (hasFile || notRequired) {
            setCampaignFollowUpFieldError('campaign_follow_up_attachment', 'campaign_follow_up_attachment_error', false);
        }
    }

    var campaignFollowUpAttachmentPreviewHelper = bindCampaignFollowUpAttachmentPreview(
        'campaign_follow_up_attachment',
        'campaign_follow_up_attachment_preview_wrap',
        'campaign_follow_up_attachment_preview_img',
        'campaign_follow_up_attachment_preview_note',
        'campaign_follow_up_existing_attachment_link'
    );

    var attachmentField = document.getElementById('campaign_follow_up_attachment');
    if (attachmentField) {
        attachmentField.addEventListener('change', clearCampaignFollowUpSingleFieldErrorIfFilled);
    }

    var campaignFollowUpModal = document.getElementById('campaignFollowUpModal');
    if (campaignFollowUpModal) {
        campaignFollowUpModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) {
                return;
            }

            var mode = button.getAttribute('data-modal-mode') || 'view';
            var taskId = button.getAttribute('data-task-id') || '';
            var customerName = button.getAttribute('data-customer-name') || '-';
            var platform = button.getAttribute('data-platform') || '-';
            var messageTitle = button.getAttribute('data-message-title') || '-';
            var followUpDate = button.getAttribute('data-follow-up-date') || '-';
            var picName = button.getAttribute('data-pic-name') || '-';
            var statusLabel = button.getAttribute('data-status-label') || '-';
            var labelPreview = button.getAttribute('data-label-preview') || '-';
            var remark = button.getAttribute('data-remark') || '';
            var existingAttachmentPath = button.getAttribute('data-existing-attachment-path') || '';
            var existingAttachmentUrl = button.getAttribute('data-existing-attachment-url') || '';
            var isViewMode = mode === 'view';
            var isMarkMode = mode === 'mark';
            var isCompleted = statusLabel === 'Completed';

            document.getElementById('campaign_follow_up_task_id').value = taskId;
            document.getElementById('campaignFollowUpModalTitle').textContent =
                (isViewMode ? 'View Follow Up - ' : (isMarkMode ? 'Mark Follow Up - ' : 'Edit Follow Up - ')) + customerName;
            document.getElementById('campaign_follow_up_customer_text').textContent = customerName;
            document.getElementById('campaign_follow_up_platform_text').textContent = platform;
            document.getElementById('campaign_follow_up_message_text').textContent = messageTitle;
            document.getElementById('campaign_follow_up_date_text').textContent = followUpDate;
            document.getElementById('campaign_follow_up_pic_text').textContent = picName;
            document.getElementById('campaign_follow_up_status_text').textContent = statusLabel + (labelPreview && labelPreview !== '-' ? ' | ' + labelPreview : '');
            document.getElementById('campaign_follow_up_remark').value = remark;
            document.getElementById('campaign_follow_up_remark').readOnly = isViewMode;

            var submitButton = document.getElementById('campaign_follow_up_submit_btn');
            submitButton.classList.toggle('d-none', isViewMode);
            submitButton.textContent = 'Save Follow Up';

            var screenshotField = document.getElementById('campaign_follow_up_attachment');
            screenshotField.value = '';
            screenshotField.disabled = isViewMode;
            screenshotField.setAttribute('data-existing-path', existingAttachmentPath);
            screenshotField.setAttribute('data-existing-url', existingAttachmentUrl);
            screenshotField.required = !isViewMode && !existingAttachmentPath;

            clearCampaignFollowUpRequiredErrors();
            if (campaignFollowUpAttachmentPreviewHelper) {
                campaignFollowUpAttachmentPreviewHelper.clearPreview();
                campaignFollowUpAttachmentPreviewHelper.showExistingAttachment();
            }
        });
    }

    var campaignFollowUpModalForm = document.getElementById('campaign_follow_up_modal_form');
    if (campaignFollowUpModalForm) {
        campaignFollowUpModalForm.addEventListener('submit', function (event) {
            campaignFollowUpHasAttemptedSubmit = true;
            if (!validateCampaignFollowUpRequiredFields()) {
                event.preventDefault();
            }
        });
    }

    const page = "Campaign";
    const action = "";
    checkCurrentPage(page, action);
    dropdownMenuDispFix();
    datatableAlignment('campaign_follow_up_table');
    setButtonColor();

    var campaignFollowUpFilterForm = document.getElementById('campaign_follow_up_filter_form');
    if (campaignFollowUpFilterForm) {
        document.querySelectorAll('#campaign_follow_up_filter_form select, #campaign_follow_up_filter_form input[type="date"]').forEach(function (filterElement) {
            filterElement.addEventListener('change', function () {
                campaignFollowUpFilterForm.submit();
            });
        });
    }
</script>
<?php campaignRenderPopupScript($pageTitle, $pageUrl); ?>
</body>
</html>
