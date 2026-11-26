<?php
$pageTitle = "Campaign Follow-Up Task";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';
include_once ROOT . '/include/customer_tag.php';
include_once ROOT . '/include/customer_follow_up_common.php';

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
// Removing a test / wrongly-assigned customer is operationally the same tier
// as editing the list, so anyone who can save follow-ups can also delete one.
// A dedicated Delete pin permission still works if the account has it.
$canManage = $canSave || $canDelete;
$csrfToken = campaignCsrfToken('follow_up_task');
$backUrl = $SITEURL . '/campaign/campaign_table.php';
$pageUrl = $SITEURL . '/campaign/campaign_follow_up_task.php' . ($campaignId > 0 ? '?campaign_id=' . $campaignId : '');
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
            cm.`message_preview`,
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

function campaignFollowUpResolveSourceCustomerForTag($connect, $financeConnect, $task, $requireActive = true)
{
    $platform = strtolower(trim((string) ($task['platform'] ?? '')));
    $customerId = trim((string) ($task['customer_id'] ?? ''));

    if ($platform === '' || !preg_match('/^[1-9][0-9]*$/', $customerId)) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    // campaign_customer.customer_id is populated from the platform customer row's
    // primary key. Resolve only that key; names and external identifiers are not
    // reliable enough to choose a customer record for a write.
    $sourceCustomerId = (int) $customerId;
    if ($sourceCustomerId <= 0) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $platformConfigMap = array(
        'shopee' => array(
            'conn' => $financeConnect,
            'table' => defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info',
        ),
        'lazada' => array(
            'conn' => $connect,
            'table' => defined('LAZADA_CUST_RCD') ? LAZADA_CUST_RCD : 'customer_lazada_deals_transaction',
        ),
        'website' => array(
            'conn' => $connect,
            'table' => defined('WEB_CUST_RCD') ? WEB_CUST_RCD : 'customer_website_deals_transaction',
        ),
        'facebook' => array(
            'conn' => $connect,
            'table' => defined('FB_CUST_DEALS') ? FB_CUST_DEALS : 'customer_facebook_deals_transaction',
        ),
    );

    if (!isset($platformConfigMap[$platform])) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $platformConfig = $platformConfigMap[$platform];
    $customerConn = $platformConfig['conn'];
    $customerTable = (string) $platformConfig['table'];
    if (!($customerConn instanceof mysqli)
        || !function_exists('campaignTableExists')
        || !campaignTableExists($customerConn, $customerTable)
        || !function_exists('campaignColumnExists')
        || !campaignColumnExists($customerConn, $customerTable, 'id')
    ) {
        return array('platform' => $platform, 'customer_id' => 0);
    }

    $safeTable = str_replace('`', '``', $customerTable);
    $whereSql = '`id`=?';
    if ($requireActive && campaignColumnExists($customerConn, $customerTable, 'status')) {
        $whereSql .= " AND `status`='A'";
    }
    $stmt = $customerConn->prepare("SELECT `id` FROM `" . $safeTable . "` WHERE " . $whereSql . " LIMIT 1");
    if (!$stmt) {
        return array('platform' => $platform, 'customer_id' => 0);
    }
    $stmt->bind_param('i', $sourceCustomerId);
    if (!$stmt->execute()) {
        $stmt->close();
        return array('platform' => $platform, 'customer_id' => 0);
    }
    $result = $stmt->get_result();
    $row = ($result && $result->num_rows === 1) ? $result->fetch_assoc() : array();
    $stmt->close();

    $resolvedId = (int) ($row['id'] ?? 0);
    return array('platform' => $platform, 'customer_id' => $resolvedId === $sourceCustomerId ? $resolvedId : 0);
}

function campaignFollowUpBuildCustomerProfileUrl($connect, $financeConnect, $task)
{
    if (!function_exists('customerDailyReportGetCustomerMeta')) {
        return '';
    }

    $sourceCustomer = campaignFollowUpResolveSourceCustomerForTag($connect, $financeConnect, $task);
    $sourcePlatform = trim((string) ($sourceCustomer['platform'] ?? ''));
    $sourceCustomerId = (int) ($sourceCustomer['customer_id'] ?? 0);
    if ($sourcePlatform === '' || $sourceCustomerId <= 0) {
        return '';
    }

    $customerMeta = customerDailyReportGetCustomerMeta($connect, $financeConnect, $sourcePlatform, $sourceCustomerId);
    return trim((string) ($customerMeta['record_url'] ?? ''));
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
        return array('success' => false, 'message' => 'Unable to locate the source customer record for campaign tag assignment.', 'tag_row' => $tagResult['tag_row'], 'source_platform' => $sourcePlatform, 'source_customer_id' => $sourceCustomerId);
    }

    $assignResult = customerTagAssignToCustomer($connect, $sourcePlatform, $sourceCustomerId, (int) $tagResult['tag_id']);
    if (empty($assignResult['success'])) {
        return array('success' => false, 'message' => 'Unable to assign campaign tag to customer.', 'tag_row' => $tagResult['tag_row'], 'source_platform' => $sourcePlatform, 'source_customer_id' => $sourceCustomerId);
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

    return array('success' => true, 'message' => '', 'tag_row' => $tagResult['tag_row'], 'source_platform' => $sourcePlatform, 'source_customer_id' => $sourceCustomerId);
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

function campaignFollowUpUploadScreenshot($fieldName, $task, &$createdAbsolutePath = null)
{
    $createdAbsolutePath = '';
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

    $relativeDir = 'attachment/campaign/campaign/' . $campaignName . '/' . $followUpDate . '/' . $customerName;
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

    $createdAbsolutePath = $targetPath;
    return $relativeDir . '/' . $fileName;
}

$followUpDeleteRequested = post('actionBtn') === 'deleteTask' || post('act') === 'D';
$followUpDeleteUsesCommonDialog = post('act') === 'D';
$followUpSaveRequested = post('actionBtn') === 'saveFollowUp';

if (($followUpSaveRequested || $followUpDeleteRequested)
    && (($followUpSaveRequested && !campaignVerifyCsrf('follow_up_task', post('csrf_token')))
        || (!$canSave && $followUpSaveRequested)
        || (!$canManage && $followUpDeleteRequested))) {
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

    if (!hash_equals($csrfToken, $postedToken) || !$canManage) {
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
    $uploadedProofAbsolutePath = '';
    $saveTransactionStarted = false;
    $saveTransactionCommitted = false;
    $taskId = (int) post('task_id');
    $task = campaignFollowUpFetchTask($connect, $taskId);
    if (!empty($task)) {
        try {
            $remark = trim((string) post('remark'));
            $userId = (string) USER_ID;
            if (!$connect->begin_transaction()) {
                throw new Exception('Unable to start the follow-up transaction. No changes were saved.');
            }
            $saveTransactionStarted = true;

            // Serialize edits to the task so concurrent Mark requests cannot both assign
            // the tag or compute proof/remark changes from stale values.
            $stateStmt = $connect->prepare("SELECT `follow_up_status`, `completed_by`, `completed_date`, `completed_time`, `screenshot_path`, `remark` FROM `" . CAMPAIGN_FOLLOW_UP . "` WHERE `id`=? AND `status`='A' LIMIT 1 FOR UPDATE");
            if (!$stateStmt) {
                throw new Exception($connect->error);
            }
            $stateStmt->bind_param('i', $taskId);
            if (!$stateStmt->execute()) {
                $stateError = $stateStmt->error;
                $stateStmt->close();
                throw new Exception($stateError);
            }
            $stateResult = $stateStmt->get_result();
            $lockedTaskState = ($stateResult && $stateResult->num_rows > 0) ? $stateResult->fetch_assoc() : array();
            $stateStmt->close();
            if (empty($lockedTaskState)) {
                throw new Exception('This follow-up is no longer available. Reload the page and try again.');
            }
            $task = array_merge($task, $lockedTaskState);

            // The posted mode is only a request, not authority. Once the database says the
            // task is completed, only Edit is accepted and it can never assign the campaign
            // tag again. Pending tasks retain the explicit Mark/Edit distinction.
            $modalMode = strtolower(trim((string) post('modal_mode')));
            $isCompletedTask = strtolower(trim((string) ($task['follow_up_status'] ?? ''))) === 'completed';
            if ($isCompletedTask && $modalMode !== 'edit') {
                throw new Exception('This follow-up is already completed. Reload the page and use Edit to update its proof or remark.');
            }
            if (!$isCompletedTask && !in_array($modalMode, array('mark', 'edit'), true)) {
                throw new Exception('Please reopen this follow-up and choose Mark or Edit before saving.');
            }
            $shouldAssignTag = !$isCompletedTask && $modalMode === 'mark';
            $preserveOriginalContent = $isCompletedTask;
            $status = 'Completed';
            $labelPreview = campaignFollowUpBuildCampaignLabelName($task);
            $uploadPath = campaignFollowUpUploadScreenshot('screenshot', $task, $uploadedProofAbsolutePath);
            $existingScreenshot = trim((string) ($task['screenshot_path'] ?? ''));
            if ($uploadPath === '' && $existingScreenshot === '') {
                throw new Exception('Screenshot / Attachment is required.');
            }
            $screenshotPath = $uploadPath !== '' ? $uploadPath : $existingScreenshot;
            $sourceChanges = array();
            if ($existingScreenshot !== $screenshotPath) {
                $sourceChanges[] = array(
                    'field' => 'Proof',
                    'from' => $existingScreenshot !== '' ? $existingScreenshot : 'No proof',
                    'to' => $screenshotPath,
                );
            }
            $existingRemark = trim((string) ($task['remark'] ?? ''));
            if ($existingRemark !== $remark) {
                $sourceChanges[] = array(
                    'field' => 'Remark',
                    'from' => $existingRemark !== '' ? $existingRemark : 'Empty',
                    'to' => $remark !== '' ? $remark : 'Cleared',
                );
            }
            // A proof/remark correction is not a new completion. Keep the original completion
            // actor and timestamp; update_by/update_date/update_time capture this edit.
            $completedBy = $isCompletedTask ? ($task['completed_by'] ?? null) : $userId;
            $completedDate = $isCompletedTask ? ($task['completed_date'] ?? null) : date('Y-m-d');
            $completedTime = $isCompletedTask ? ($task['completed_time'] ?? null) : date('H:i:s');
            $campaignTagResult = array('success' => true, 'message' => '', 'tag_row' => null, 'source_platform' => '', 'source_customer_id' => 0);

            if ($shouldAssignTag) {
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
            } else {
                // Edit: no tag, but still resolve the source customer so the User Record Log
                // entry lands on the right person. Pending tasks require an active source;
                // completed history remains editable if the customer was later deactivated.
                $sourceCustomer = campaignFollowUpResolveSourceCustomerForTag($connect, isset($finance_connect) ? $finance_connect : $connect, $task, !$isCompletedTask);
                $campaignTagResult['source_platform'] = (string) ($sourceCustomer['platform'] ?? '');
                $campaignTagResult['source_customer_id'] = (int) ($sourceCustomer['customer_id'] ?? 0);
                if ($campaignTagResult['source_customer_id'] <= 0) {
                    throw new Exception('Unable to resolve this task to one exact platform customer record. No changes were saved.');
                }
            }

            $stmt = $connect->prepare("UPDATE `" . CAMPAIGN_FOLLOW_UP . "` SET `screenshot_path`=?, `remark`=?, `follow_up_status`=?, `label_preview`=?, `completed_by`=?, `completed_date`=?, `completed_time`=?, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `status`='A'");
            if (!$stmt) {
                throw new Exception($connect->error);
            }
            $stmt->bind_param('ssssssssi', $screenshotPath, $remark, $status, $labelPreview, $completedBy, $completedDate, $completedTime, $userId, $taskId);
            if (!$stmt->execute()) {
                $updateError = $stmt->error;
                $stmt->close();
                throw new Exception('Unable to update the follow-up task: ' . $updateError);
            }
            $stmt->close();

            // Merge this completed follow-up into the customer's own User Record Log so it
            // shows up alongside their other interaction history, not just inside the
            // campaign module. Uses the same exact platform-customer id resolved above.
            if (!function_exists('customerFollowUpInsertReadableUserRecordLog')) {
                throw new Exception('The customer timeline writer is unavailable. No changes were saved.');
            }
            $recordLogLines = array();
            $recordLogLines[] = 'Campaign follow-up completed for campaign "' . (string) ($task['campaign_name'] ?? '') . '".';
            $recordLogLines[] = 'Message: ' . (string) ($task['message_preview'] ?? '');
            $tagNameForLog = trim((string) ($campaignTagResult['tag_row']['name'] ?? ''));
            if ($shouldAssignTag && $tagNameForLog !== '') {
                $recordLogLines[] = 'Tag added: ' . $tagNameForLog . '.';
            }
            if ($remark !== '') {
                $recordLogLines[] = 'Remark: ' . $remark;
            }
            $recordLogContent = implode("\n", $recordLogLines);

            $sourceCustomerId = (int) ($campaignTagResult['source_customer_id'] ?? 0);
            $sourcePlatform = (string) ($campaignTagResult['source_platform'] ?? '');
            if ($sourceCustomerId <= 0 || $sourcePlatform === '') {
                throw new Exception('Unable to resolve this task to one exact platform customer record. No changes were saved.');
            }
            $userRecordLogId = customerFollowUpInsertReadableUserRecordLog($connect, array(
                'platform' => $sourcePlatform,
                'customer_id' => $sourceCustomerId,
                'content' => $recordLogContent,
                'attachment' => $screenshotPath,
                'created_by' => $userId,
                'source_type' => 'campaign_follow_up_task',
                'source_id' => $taskId,
                'source_changes' => $sourceChanges,
                // Editing a completed task updates its proof and revision details
                // without rewriting the original completion description.
                'preserve_content' => $preserveOriginalContent,
            ));
            if ($userRecordLogId <= 0) {
                throw new Exception('Unable to update the original customer timeline entry. Run insert_table.php if the source-key fields are missing. If this task was completed before stable source links were added, its old log cannot be matched safely, so no duplicate was created.');
            }

            if (!$connect->commit()) {
                throw new Exception('Unable to commit the follow-up transaction. No changes were confirmed.');
            }
            $saveTransactionCommitted = true;

            campaignAudit($connect, $pageTitle, 'edit', USER_NAME . " updated campaign follow-up task " . $taskId . ".", '', CAMPAIGN_FOLLOW_UP);
            campaignSetPopup('Successful Save Follow Up', $pageUrl, 'ErrMO');
        } catch (Exception $e) {
            if ($connect instanceof mysqli && $saveTransactionStarted && !$saveTransactionCommitted) {
                @mysqli_rollback($connect);
            }
            $errorMessage = $e->getMessage();
            if (!$saveTransactionCommitted && $uploadedProofAbsolutePath !== '' && is_file($uploadedProofAbsolutePath)) {
                if (!@unlink($uploadedProofAbsolutePath)) {
                    $errorMessage .= ' The uploaded proof could not be removed after the failed save; please contact an administrator.';
                }
            }
            campaignSetPopup($errorMessage, $pageUrl, 'ErrMO');
        }
    }
    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$selectedMonth = trim((string) input('month'));
$selectedYear = trim((string) input('year'));
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
} elseif (input('date') !== '' || input('month') !== '' || input('year') !== '') {
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
        $row['customer_profile_url'] = campaignFollowUpBuildCustomerProfileUrl(
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
            cursor: zoom-in;
        }

        .campaign-follow-up-page .arrival-follow-up-preview-resize-note {
            font-size: 0.78rem;
            color: #6c757d;
            text-align: center;
            margin-top: 0.35rem;
        }

        /* Keep the edit/view follow-up modal inside the viewport so the summary,
           attachment preview and remark never overlap the page behind it. */
        .campaign-follow-up-page .campaign-follow-up-modal-dialog {
            max-width: 720px;
            margin: 1.5rem auto;
        }

        .campaign-follow-up-page .campaign-follow-up-modal-dialog .modal-content {
            max-height: calc(100vh - 3rem);
            display: flex;
            flex-direction: column;
        }

        .campaign-follow-up-page .campaign-follow-up-modal-dialog .modal-body {
            overflow-y: auto;
            flex: 1 1 auto;
        }

        .campaign-follow-up-image-lightbox {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .campaign-follow-up-image-lightbox.is-open {
            display: flex;
        }

        .campaign-follow-up-image-lightbox-viewport {
            width: 90vw;
            height: 82vh;
            overflow: auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .campaign-follow-up-image-lightbox-viewport img {
            transform-origin: center center;
            transition: transform 0.12s ease-out;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .campaign-follow-up-image-lightbox-toolbar {
            position: absolute;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
        }

        .campaign-follow-up-image-lightbox-toolbar button {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }

        .campaign-follow-up-image-lightbox-toolbar button:hover {
            background: rgba(255, 255, 255, 0.3);
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
                <div class="row"><p><a href="<?= $SITEURL ?>/campaign/campaign_table.php">Campaign</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Follow-Up Task</p></div>
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
                            <?php if ($canManage): ?>
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
                        <td><?php $customerProfileUrl = trim((string) ($row['customer_profile_url'] ?? '')); ?><?php if ($customerProfileUrl !== ''): ?><a href="<?= campaignH($customerProfileUrl) ?>" target="_blank" rel="noopener noreferrer"><?= campaignH($displayCustomerName) ?></a><?php else: ?><?= campaignH($displayCustomerName) ?><?php endif; ?></td>
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
    <div class="modal-dialog modal-dialog-scrollable campaign-follow-up-modal-dialog">
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
                    <input type="hidden" name="modal_mode" id="campaign_follow_up_modal_mode" value="">
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
                            <img id="campaign_follow_up_attachment_preview_img" alt="Follow-Up Attachment Preview" title="Click to zoom in">
                            <div class="arrival-follow-up-preview-note d-none" id="campaign_follow_up_attachment_preview_note"></div>
                            <div class="arrival-follow-up-preview-resize-note d-none" id="campaign_follow_up_attachment_resize_note"></div>
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

<div class="campaign-follow-up-image-lightbox" id="campaign_follow_up_image_lightbox">
    <div class="campaign-follow-up-image-lightbox-toolbar">
        <button type="button" id="campaign_follow_up_lightbox_zoom_out" title="Zoom out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
        <button type="button" id="campaign_follow_up_lightbox_zoom_in" title="Zoom in"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        <button type="button" id="campaign_follow_up_lightbox_close" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="campaign-follow-up-image-lightbox-viewport">
        <img id="campaign_follow_up_lightbox_img" alt="Follow-Up Attachment (zoomed)">
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

    var CAMPAIGN_FOLLOW_UP_RESIZE_THRESHOLD_BYTES = 1.5 * 1024 * 1024;
    var CAMPAIGN_FOLLOW_UP_RESIZE_MAX_DIMENSION = 1920;
    var CAMPAIGN_FOLLOW_UP_RESIZE_QUALITY = 0.82;

    // Large screenshots can silently exceed the server's post_max_size/upload_max_filesize
    // limits, which makes PHP drop $_FILES entirely -- the save then fails with a
    // confusing "Screenshot / Attachment is required" error even though a file was
    // clearly chosen. Downscaling oversized images client-side before they're
    // submitted avoids that failure mode without needing the user to manually resize
    // anything themselves.
    function campaignFollowUpResizeImageIfNeeded(file) {
        return new Promise(function (resolve) {
            if (!file || file.type.indexOf('image/') !== 0 || file.type === 'image/gif' || file.size <= CAMPAIGN_FOLLOW_UP_RESIZE_THRESHOLD_BYTES) {
                resolve({ file: file, resized: false });
                return;
            }

            var objectUrl = URL.createObjectURL(file);
            var img = new Image();

            img.onload = function () {
                var width = img.naturalWidth;
                var height = img.naturalHeight;
                var scale = Math.min(1, CAMPAIGN_FOLLOW_UP_RESIZE_MAX_DIMENSION / Math.max(width, height));
                var targetWidth = Math.max(1, Math.round(width * scale));
                var targetHeight = Math.max(1, Math.round(height * scale));

                var canvas = document.createElement('canvas');
                canvas.width = targetWidth;
                canvas.height = targetHeight;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
                URL.revokeObjectURL(objectUrl);

                canvas.toBlob(function (blob) {
                    if (!blob || blob.size >= file.size) {
                        resolve({ file: file, resized: false });
                        return;
                    }

                    var resizedFile;
                    try {
                        resizedFile = new File([blob], file.name, { type: 'image/jpeg', lastModified: Date.now() });
                    } catch (e) {
                        resizedFile = blob;
                    }
                    resolve({ file: resizedFile, resized: true });
                }, 'image/jpeg', CAMPAIGN_FOLLOW_UP_RESIZE_QUALITY);
            };
            img.onerror = function () {
                URL.revokeObjectURL(objectUrl);
                resolve({ file: file, resized: false });
            };
            img.src = objectUrl;
        });
    }

    function campaignFollowUpFormatFileSize(bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
        return Math.round(bytes / 1024) + ' KB';
    }

    var campaignFollowUpLightbox = document.getElementById('campaign_follow_up_image_lightbox');
    var campaignFollowUpLightboxImg = document.getElementById('campaign_follow_up_lightbox_img');
    var campaignFollowUpLightboxZoom = 1;

    function campaignFollowUpSetLightboxZoom(zoom) {
        campaignFollowUpLightboxZoom = Math.min(4, Math.max(0.5, zoom));
        if (campaignFollowUpLightboxImg) {
            campaignFollowUpLightboxImg.style.transform = 'scale(' + campaignFollowUpLightboxZoom + ')';
        }
    }

    function campaignFollowUpOpenLightbox(src) {
        if (!src || !campaignFollowUpLightbox || !campaignFollowUpLightboxImg) {
            return;
        }
        campaignFollowUpLightboxImg.src = src;
        campaignFollowUpSetLightboxZoom(1);
        campaignFollowUpLightbox.classList.add('is-open');
    }

    function campaignFollowUpCloseLightbox() {
        if (campaignFollowUpLightbox) {
            campaignFollowUpLightbox.classList.remove('is-open');
        }
    }

    if (campaignFollowUpLightbox) {
        document.getElementById('campaign_follow_up_lightbox_close').addEventListener('click', campaignFollowUpCloseLightbox);
        document.getElementById('campaign_follow_up_lightbox_zoom_in').addEventListener('click', function () {
            campaignFollowUpSetLightboxZoom(campaignFollowUpLightboxZoom + 0.25);
        });
        document.getElementById('campaign_follow_up_lightbox_zoom_out').addEventListener('click', function () {
            campaignFollowUpSetLightboxZoom(campaignFollowUpLightboxZoom - 0.25);
        });
        campaignFollowUpLightbox.addEventListener('click', function (event) {
            if (event.target === campaignFollowUpLightbox) {
                campaignFollowUpCloseLightbox();
            }
        });
    }

    function bindCampaignFollowUpAttachmentPreview(inputId, wrapId, imageId, noteId, existingLinkId, resizeNoteId) {
        var fileInput = document.getElementById(inputId);
        var previewWrap = document.getElementById(wrapId);
        var previewImage = document.getElementById(imageId);
        var previewNote = document.getElementById(noteId);
        var existingLink = existingLinkId ? document.getElementById(existingLinkId) : null;
        var resizeNote = resizeNoteId ? document.getElementById(resizeNoteId) : null;
        if (!fileInput || !previewWrap || !previewImage || !previewNote) {
            return null;
        }

        previewImage.addEventListener('click', function () {
            if (previewImage.src) {
                campaignFollowUpOpenLightbox(previewImage.src);
            }
        });

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

            if (resizeNote) {
                resizeNote.textContent = '';
                resizeNote.classList.add('d-none');
            }

            if (file.type.indexOf('image/') === 0) {
                var originalSize = file.size;
                campaignFollowUpResizeImageIfNeeded(file).then(function (result) {
                    if (result.resized) {
                        var dt = new DataTransfer();
                        dt.items.add(result.file);
                        fileInput.files = dt.files;
                        if (resizeNote) {
                            resizeNote.textContent = 'Image was too large and was automatically resized (' +
                                campaignFollowUpFormatFileSize(originalSize) + ' → ' +
                                campaignFollowUpFormatFileSize(result.file.size) + ').';
                            resizeNote.classList.remove('d-none');
                        }
                    }

                    currentObjectUrl = URL.createObjectURL(result.file);
                    previewImage.src = currentObjectUrl;
                    previewImage.style.display = 'block';
                    previewNote.textContent = '';
                    previewNote.classList.add('d-none');
                    previewWrap.style.display = 'block';
                });
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
        'campaign_follow_up_existing_attachment_link',
        'campaign_follow_up_attachment_resize_note'
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
            document.getElementById('campaign_follow_up_modal_mode').value = (isMarkMode ? 'mark' : (isViewMode ? 'view' : 'edit'));
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
            var resizeNoteEl = document.getElementById('campaign_follow_up_attachment_resize_note');
            if (resizeNoteEl) {
                resizeNoteEl.textContent = '';
                resizeNoteEl.classList.add('d-none');
            }
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
