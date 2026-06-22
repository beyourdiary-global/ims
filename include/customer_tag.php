<?php

if (!function_exists('customerTagGetAssignmentTable')) {
    function customerTagGetAssignmentTable()
    {
        return defined('CUS_TAG_ASSIGNMENT') ? CUS_TAG_ASSIGNMENT : 'customer_tag_assignment';
    }
}

if (!function_exists('customerTagNormalizePlatform')) {
    function customerTagNormalizePlatform($platform)
    {
        $platform = strtolower(trim((string) $platform));
        $configs = function_exists('customerLabelGetPlatformConfigs') ? customerLabelGetPlatformConfigs() : array();
        return ($platform !== '' && isset($configs[$platform])) ? $platform : '';
    }
}

if (!function_exists('customerTagTableExists')) {
    function customerTagTableExists($connect)
    {
        static $existsCache = array();

        if (!($connect instanceof mysqli)) {
            return false;
        }

        $cacheKey = function_exists('spl_object_hash') ? spl_object_hash($connect) : 'default';
        if (isset($existsCache[$cacheKey])) {
            return $existsCache[$cacheKey];
        }

        $safeTable = mysqli_real_escape_string($connect, customerTagGetAssignmentTable());
        $result = mysqli_query($connect, "SHOW TABLES LIKE '" . $safeTable . "'");
        $exists = ($result instanceof mysqli_result) && $result->num_rows > 0;
        if ($result instanceof mysqli_result) {
            $result->free();
        }

        $existsCache[$cacheKey] = $exists;
        return $exists;
    }
}

if (!function_exists('customerTagGetActiveTagOptions')) {
    function customerTagGetActiveTagOptions($connect)
    {
        if (!($connect instanceof mysqli)) {
            return array();
        }

        $rows = array();
        $sql = "SELECT id, name, remark FROM `" . TAG . "` WHERE status = 'A' ORDER BY name ASC, id ASC";
        $result = mysqli_query($connect, $sql);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('customerTagFindTagByName')) {
    function customerTagFindTagByName($connect, $tagName)
    {
        if (!($connect instanceof mysqli)) {
            return null;
        }

        $tagName = trim((string) $tagName);
        if ($tagName === '') {
            return null;
        }

        $safeTagName = mysqli_real_escape_string($connect, $tagName);
        $sql = "SELECT id, name, remark, status FROM `" . TAG . "` WHERE name = '" . $safeTagName . "' ORDER BY status = 'A' DESC, id ASC LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result instanceof mysqli_result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }
}

if (!function_exists('customerTagGetTagById')) {
    function customerTagGetTagById($connect, $tagId)
    {
        if (!($connect instanceof mysqli)) {
            return null;
        }

        $tagId = (int) $tagId;
        if ($tagId <= 0) {
            return null;
        }

        $result = getData('id,name,remark,status', "id = '" . $tagId . "'", 'LIMIT 1', TAG, $connect);
        if ($result instanceof mysqli_result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }
}

if (!function_exists('customerTagGetCustomerTagMap')) {
    function customerTagGetCustomerTagMap($connect, $platform, $customerIds)
    {
        $platform = customerTagNormalizePlatform($platform);
        if ($platform === '' || !customerTagTableExists($connect)) {
            return array();
        }

        $customerIds = array_values(array_unique(array_filter(array_map('intval', (array) $customerIds))));
        if (empty($customerIds)) {
            return array();
        }

        $safePlatform = mysqli_real_escape_string($connect, $platform);
        $sql = "SELECT a.id AS assignment_id, a.customer_id, a.tag_id, t.name, t.remark
                FROM `" . customerTagGetAssignmentTable() . "` a
                INNER JOIN `" . TAG . "` t ON t.id = a.tag_id
                WHERE a.status = 'A'
                  AND t.status = 'A'
                  AND a.platform = '" . $safePlatform . "'
                  AND a.customer_id IN (" . implode(',', $customerIds) . ")
                ORDER BY t.name ASC, a.id ASC";

        $tagMap = array();
        $result = mysqli_query($connect, $sql);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $customerId = isset($row['customer_id']) ? (int) $row['customer_id'] : 0;
                if ($customerId <= 0) {
                    continue;
                }

                if (!isset($tagMap[$customerId])) {
                    $tagMap[$customerId] = array();
                }

                $tagMap[$customerId][] = array(
                    'assignment_id' => isset($row['assignment_id']) ? (int) $row['assignment_id'] : 0,
                    'tag_id' => isset($row['tag_id']) ? (int) $row['tag_id'] : 0,
                    'name' => isset($row['name']) ? $row['name'] : '',
                    'remark' => isset($row['remark']) ? $row['remark'] : '',
                );
            }
        }

        return $tagMap;
    }
}

if (!function_exists('customerTagGetActiveTags')) {
    function customerTagGetActiveTags($connect, $platform, $customerId)
    {
        $tagMap = customerTagGetCustomerTagMap($connect, $platform, array((int) $customerId));
        return isset($tagMap[(int) $customerId]) ? $tagMap[(int) $customerId] : array();
    }
}

if (!function_exists('customerTagGetDraftSessionKey')) {
    function customerTagGetDraftSessionKey($platform, $draftToken = '')
    {
        $platform = customerTagNormalizePlatform($platform);
        $draftToken = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) $draftToken));
        if ($platform === '') {
            return '';
        }

        if ($draftToken !== '') {
            return 'customer_tag_draft_' . $platform . '_' . $draftToken;
        }

        return 'customer_tag_draft_' . $platform;
    }
}

if (!function_exists('customerTagGetDraftTagIds')) {
    function customerTagGetDraftTagIds($platform, $draftToken = '')
    {
        $sessionKey = customerTagGetDraftSessionKey($platform, $draftToken);
        if ($sessionKey === '' || !isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            return array();
        }

        $tagIds = array();
        foreach ($_SESSION[$sessionKey] as $tagId) {
            $tagId = (int) $tagId;
            if ($tagId > 0 && !in_array($tagId, $tagIds, true)) {
                $tagIds[] = $tagId;
            }
        }

        return $tagIds;
    }
}

if (!function_exists('customerTagSetDraftTagIds')) {
    function customerTagSetDraftTagIds($platform, $tagIds, $draftToken = '')
    {
        $sessionKey = customerTagGetDraftSessionKey($platform, $draftToken);
        if ($sessionKey === '') {
            return;
        }

        $cleanIds = array();
        foreach ((array) $tagIds as $tagId) {
            $tagId = (int) $tagId;
            if ($tagId > 0 && !in_array($tagId, $cleanIds, true)) {
                $cleanIds[] = $tagId;
            }
        }

        $_SESSION[$sessionKey] = $cleanIds;
    }
}

if (!function_exists('customerTagClearDraftTags')) {
    function customerTagClearDraftTags($platform, $draftToken = '')
    {
        $sessionKey = customerTagGetDraftSessionKey($platform, $draftToken);
        if ($sessionKey !== '' && isset($_SESSION[$sessionKey])) {
            unset($_SESSION[$sessionKey]);
        }
    }
}

if (!function_exists('customerTagGenerateDraftToken')) {
    function customerTagGenerateDraftToken()
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return str_replace('.', '', uniqid('ctd', true));
        }
    }
}

if (!function_exists('customerTagResolveDraftToken')) {
    function customerTagResolveDraftToken($act)
    {
        if ((string) $act !== 'I') {
            return '';
        }

        $postedToken = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) postSpaceFilter('customerTagDraftToken')));
        if ($postedToken !== '') {
            return $postedToken;
        }

        return customerTagGenerateDraftToken();
    }
}

if (!function_exists('customerTagResetDraftOnFreshAddPage')) {
    function customerTagResetDraftOnFreshAddPage($platform, $act, $draftToken = '')
    {
        if ((string) $act !== 'I') {
            return;
        }

        if (customerTagIsAjaxRequest()) {
            return;
        }

        $isCustomerTagAction = trim((string) postSpaceFilter('customerTagAction')) !== '';
        $isFormSubmitAction = trim((string) postSpaceFilter('actionBtn')) !== '';
        $isHiddenFormSubmitAction = trim((string) postSpaceFilter('actionBtnHidden')) !== '';

        if ($isCustomerTagAction || $isFormSubmitAction || $isHiddenFormSubmitAction) {
            return;
        }

        customerTagClearDraftTags($platform, $draftToken);
    }
}

if (!function_exists('customerTagExtractTagIds')) {
    function customerTagExtractTagIds($tagRows)
    {
        $tagIds = array();

        foreach ((array) $tagRows as $tagRow) {
            $tagId = isset($tagRow['tag_id']) ? (int) $tagRow['tag_id'] : 0;
            if ($tagId > 0 && !in_array($tagId, $tagIds, true)) {
                $tagIds[] = $tagId;
            }
        }

        return $tagIds;
    }
}

if (!function_exists('customerTagGetPostedDraftTagIds')) {
    function customerTagGetPostedDraftTagIds()
    {
        $rawTagIds = trim((string) postSpaceFilter('customerTagDraftIds'));
        if ($rawTagIds === '') {
            return array();
        }

        $tagIds = array();
        foreach (explode(',', $rawTagIds) as $tagId) {
            $tagId = (int) trim((string) $tagId);
            if ($tagId > 0 && !in_array($tagId, $tagIds, true)) {
                $tagIds[] = $tagId;
            }
        }

        return $tagIds;
    }
}

if (!function_exists('customerTagGetPostedSelectedTagIds')) {
    function customerTagGetPostedSelectedTagIds()
    {
        $tagIds = array();

        if (isset($_POST['customerTagSelectedIds']) && is_array($_POST['customerTagSelectedIds'])) {
            foreach ($_POST['customerTagSelectedIds'] as $tagId) {
                $tagId = (int) $tagId;
                if ($tagId > 0 && !in_array($tagId, $tagIds, true)) {
                    $tagIds[] = $tagId;
                }
            }
        }

        if (empty($tagIds)) {
            $singleTagId = (int) postSpaceFilter('customerTagSelectedId');
            if ($singleTagId > 0) {
                $tagIds[] = $singleTagId;
            }
        }

        return $tagIds;
    }
}

if (!function_exists('customerTagGetDraftTags')) {
    function customerTagGetDraftTags($connect, $platform, $draftToken = '')
    {
        $tagRows = array();
        $tagIds = customerTagGetDraftTagIds($platform, $draftToken);
        foreach ($tagIds as $tagId) {
            $tagRow = customerTagGetTagById($connect, $tagId);
            if ($tagRow && (isset($tagRow['status']) ? $tagRow['status'] : '') === 'A') {
                $tagRows[] = array(
                    'tag_id' => $tagId,
                    'name' => isset($tagRow['name']) ? $tagRow['name'] : '',
                );
            }
        }

        return $tagRows;
    }
}

if (!function_exists('customerTagGetTagsByIds')) {
    function customerTagGetTagsByIds($connect, $tagIds)
    {
        $tagRows = array();
        foreach ((array) $tagIds as $tagId) {
            $tagId = (int) $tagId;
            if ($tagId <= 0) {
                continue;
            }

            $tagRow = customerTagGetTagById($connect, $tagId);
            if ($tagRow && (isset($tagRow['status']) ? $tagRow['status'] : '') === 'A') {
                $tagRows[] = array(
                    'tag_id' => $tagId,
                    'name' => isset($tagRow['name']) ? $tagRow['name'] : '',
                );
            }
        }

        return $tagRows;
    }
}

if (!function_exists('customerTagGetDisplayTags')) {
    function customerTagGetDisplayTags($connect, $platform, $customerId, $draftToken = '')
    {
        $customerId = (int) $customerId;
        if ($customerId > 0) {
            return customerTagGetActiveTags($connect, $platform, $customerId);
        }

        return customerTagGetDraftTags($connect, $platform, $draftToken);
    }
}

if (!function_exists('customerTagApplyDraftTagsToCustomer')) {
    function customerTagApplyDraftTagsToCustomer($connect, $platform, $customerId, $pageTitle = '', $customerDisplayName = '', $draftTagIds = null, $draftToken = '')
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        if ($platform === '' || $customerId <= 0) {
            return array('success' => false, 'assigned_count' => 0);
        }

        $tagIdsToAssign = is_array($draftTagIds) ? array_values(array_unique(array_filter(array_map('intval', $draftTagIds)))) : customerTagGetDraftTagIds($platform, $draftToken);
        if (empty($tagIdsToAssign)) {
            customerTagClearDraftTags($platform, $draftToken);
            return array('success' => true, 'assigned_count' => 0);
        }

        $assignedCount = 0;
        $hadFailure = false;
        foreach ($tagIdsToAssign as $tagId) {
            if ($tagId <= 0) {
                continue;
            }

            $tagRow = customerTagGetTagById($connect, $tagId);
            if (!$tagRow || (isset($tagRow['status']) ? $tagRow['status'] : '') !== 'A') {
                $hadFailure = true;
                continue;
            }

            $assignResult = customerTagAssignToCustomer($connect, $platform, $customerId, $tagId);
            if (!$assignResult['success']) {
                $hadFailure = true;
                continue;
            }

            if (!empty($assignResult['already_active'])) {
                continue;
            }

            $assignedCount++;

            if ($pageTitle !== '') {
                customerTagWriteAuditLog(
                    $connect,
                    $pageTitle,
                    'Add',
                    USER_NAME . ' assigned customer tag [<b>' . htmlspecialchars((string) $tagRow['name'], ENT_QUOTES, 'UTF-8') . '</b>] to <b>' . htmlspecialchars(customerTagBuildCustomerLabel($pageTitle, $customerDisplayName, $customerId), ENT_QUOTES, 'UTF-8') . '</b>.',
                    $assignResult['query'],
                    customerTagGetAssignmentTable()
                );
            }
        }

        customerTagClearDraftTags($platform, $draftToken);

        return array(
            'success' => !$hadFailure,
            'assigned_count' => $assignedCount,
        );
    }
}

if (!function_exists('customerTagRenderBadgeItems')) {
    function customerTagRenderBadgeItems($tagRows, $badgeClass = 'customer-tag-badge')
    {
        $tagRows = is_array($tagRows) ? $tagRows : array();
        $items = array();

        foreach ($tagRows as $tagRow) {
            $tagName = isset($tagRow['name']) ? trim((string) $tagRow['name']) : '';
            if ($tagName === '') {
                continue;
            }

            $items[] = '<span class="' . htmlspecialchars((string) $badgeClass, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return $items;
    }
}

if (!function_exists('customerTagRenderBadges')) {
    function customerTagRenderBadges($tagRows, $wrapperClass = 'customer-tag-badge-group', $badgeClass = 'customer-tag-badge')
    {
        $items = customerTagRenderBadgeItems($tagRows, $badgeClass);
        return customerLabelRenderCollapsibleBadgeGroup($items, $wrapperClass);
    }
}

if (!function_exists('customerTagRenderTitle')) {
    function customerTagRenderTitle($title, $tagRows)
    {
        $safeTitle = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
        $badgeHtml = customerTagRenderBadges($tagRows, 'customer-tag-title-badge-group', 'customer-tag-title-badge');

        if ($badgeHtml === '') {
            return $safeTitle;
        }

        return '<span class="customer-tag-title-wrap"><span>' . $safeTitle . '</span>' . $badgeHtml . '</span>';
    }
}

if (!function_exists('customerTagGetModalId')) {
    function customerTagGetModalId($platform, $customerId)
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        return 'customerTagManageModal_' . $platform . '_' . $customerId;
    }
}

if (!function_exists('customerTagRenderManageButton')) {
    function customerTagRenderManageButton($platform, $customerId, $allowManage = true)
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        if ($platform === '' || $customerId < 0 || !$allowManage) {
            return '';
        }

        $modalId = customerTagGetModalId($platform, $customerId);
        return '<div class="customer-tag-manage-sticky-wrap"><button type="button" class="btn btn-rounded btn-primary customer-tag-manage-btn" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '">Manage Tag</button></div>';
    }
}

if (!function_exists('customerTagBuildCustomerLabel')) {
    function customerTagBuildCustomerLabel($pageTitle, $customerDisplayName, $customerId)
    {
        $pageTitle = trim((string) $pageTitle);
        $customerDisplayName = trim((string) $customerDisplayName);
        $customerId = (int) $customerId;

        $label = $pageTitle !== '' ? $pageTitle : 'Customer Record';
        if ($customerDisplayName !== '') {
            $label .= ' [' . $customerDisplayName . ']';
        }
        if ($customerId > 0) {
            $label .= ' (ID = ' . $customerId . ')';
        }

        return $label;
    }
}

if (!function_exists('customerTagWriteAuditLog')) {
    function customerTagWriteAuditLog($connect, $pageTitle, $logAct, $message, $query, $queryTable)
    {
        $log = array(
            'log_act' => $logAct,
            'cdate' => defined('date_dis') ? date_dis : date('Y-m-d'),
            'ctime' => defined('time_dis') ? time_dis : date('H:i:s'),
            'uid' => defined('USER_ID') ? USER_ID : '',
            'cby' => defined('USER_ID') ? USER_ID : '',
            'act_msg' => $message,
            'query_rec' => $query,
            'query_table' => $queryTable,
            'page' => $pageTitle,
            'connect' => $connect,
        );

        audit_log($log);
    }
}

if (!function_exists('customerTagAssignToCustomer')) {
    function customerTagAssignToCustomer($connect, $platform, $customerId, $tagId)
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $tagId = (int) $tagId;

        if ($platform === '' || $customerId <= 0 || $tagId <= 0 || !customerTagTableExists($connect)) {
            return array('success' => false, 'already_active' => false, 'query' => '');
        }

        $safePlatform = mysqli_real_escape_string($connect, $platform);
        $tblName = customerTagGetAssignmentTable();
        $queryCheck = "SELECT id, status FROM `" . $tblName . "` WHERE platform = '" . $safePlatform . "' AND customer_id = '" . $customerId . "' AND tag_id = '" . $tagId . "' LIMIT 1";
        $result = mysqli_query($connect, $queryCheck);
        if ($result instanceof mysqli_result && $result->num_rows > 0) {
            $existingRow = $result->fetch_assoc();
            $assignmentId = isset($existingRow['id']) ? (int) $existingRow['id'] : 0;
            $existingStatus = isset($existingRow['status']) ? (string) $existingRow['status'] : '';

            if ($assignmentId > 0 && $existingStatus === 'A') {
                return array('success' => true, 'already_active' => true, 'query' => $queryCheck);
            }

            $query = "UPDATE `" . $tblName . "` SET status = 'A', update_by = '" . USER_ID . "', update_date = CURDATE(), update_time = CURTIME() WHERE id = '" . $assignmentId . "'";
            return array('success' => (bool) mysqli_query($connect, $query), 'already_active' => false, 'query' => $query);
        }

        $query = "INSERT INTO `" . $tblName . "` (platform, customer_id, tag_id, create_by, create_date, create_time, status) VALUES ('" . $safePlatform . "', '" . $customerId . "', '" . $tagId . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";
        return array('success' => (bool) mysqli_query($connect, $query), 'already_active' => false, 'query' => $query);
    }
}

if (!function_exists('customerTagRemoveFromCustomer')) {
    function customerTagRemoveFromCustomer($connect, $platform, $customerId, $tagId)
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $tagId = (int) $tagId;

        if ($platform === '' || $customerId <= 0 || $tagId <= 0 || !customerTagTableExists($connect)) {
            return array('success' => false, 'changed' => false, 'query' => '');
        }

        $safePlatform = mysqli_real_escape_string($connect, $platform);
        $query = "UPDATE `" . customerTagGetAssignmentTable() . "` SET status = 'D', update_by = '" . USER_ID . "', update_date = CURDATE(), update_time = CURTIME() WHERE platform = '" . $safePlatform . "' AND customer_id = '" . $customerId . "' AND tag_id = '" . $tagId . "' AND status = 'A'";
        $result = mysqli_query($connect, $query);

        return array(
            'success' => (bool) $result,
            'changed' => (bool) $result && mysqli_affected_rows($connect) > 0,
            'query' => $query,
        );
    }
}

if (!function_exists('customerTagHandlePost')) {
    function customerTagHandlePost($connect, $platform, $customerId, $pageTitle, $customerDisplayName = '', $draftToken = '')
    {
        $state = array(
            'handled' => false,
            'reopen_modal' => false,
            'message' => '',
            'message_type' => 'info',
            'success' => false,
        );

        $action = postSpaceFilter('customerTagAction');
        if ($action === '') {
            return $state;
        }

        $postedPlatform = customerTagNormalizePlatform(postSpaceFilter('customerTagPlatform'));
        $postedCustomerId = (int) postSpaceFilter('customerTagCustomerId');
        $postedDraftToken = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) postSpaceFilter('customerTagDraftToken')));
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $draftToken = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) $draftToken));

        if ($platform === '' || $postedPlatform !== $platform || $postedCustomerId !== $customerId) {
            return $state;
        }

        if ($customerId <= 0 && $draftToken !== $postedDraftToken) {
            return $state;
        }

        $state['handled'] = true;
        $state['reopen_modal'] = true;

        if (!customerTagTableExists($connect)) {
            $state['message_type'] = 'danger';
            $state['message'] = 'Customer tag assignment table is not ready yet. Please run insert_table.php first.';
            return $state;
        }

        $isDraftTagState = $customerId <= 0;
        $draftTagIds = $isDraftTagState ? customerTagGetPostedDraftTagIds() : array();
        $customerLabel = customerTagBuildCustomerLabel($pageTitle, $customerDisplayName, $customerId);
        $selectedTagId = (int) postSpaceFilter('customerTagSelectedId');
        $selectedTagIds = customerTagGetPostedSelectedTagIds();
        $currentTagId = (int) postSpaceFilter('customerTagCurrentId');

        switch ($action) {
            case 'reset_draft':
                if (!$isDraftTagState) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Draft tags can only be reset on add pages.';
                    break;
                }

                $draftTagIds = array();
                customerTagClearDraftTags($platform, $draftToken);
                $state['message_type'] = 'success';
                $state['message'] = '';
                $state['success'] = true;
                $state['reopen_modal'] = false;
                break;

            case 'assign_existing':
                if (empty($selectedTagIds)) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Please select at least one tag to assign.';
                    break;
                }

                $assignedTagNames = array();
                $alreadyAssignedTagNames = array();
                $failedTagNames = array();

                foreach ($selectedTagIds as $selectedTagIdItem) {
                    $tagRow = customerTagGetTagById($connect, $selectedTagIdItem);
                    $tagName = ($tagRow && isset($tagRow['name'])) ? $tagRow['name'] : ('Tag #' . $selectedTagIdItem);

                    if (!$tagRow || (isset($tagRow['status']) ? $tagRow['status'] : '') !== 'A') {
                        $failedTagNames[] = $tagName;
                        continue;
                    }

                    if ($isDraftTagState) {
                        if (in_array($selectedTagIdItem, $draftTagIds, true)) {
                            $alreadyAssignedTagNames[] = $tagName;
                            continue;
                        }

                        $draftTagIds[] = $selectedTagIdItem;
                        $assignedTagNames[] = $tagName;
                        continue;
                    }

                    $assignResult = customerTagAssignToCustomer($connect, $platform, $customerId, $selectedTagIdItem);
                    if (!$assignResult['success']) {
                        $failedTagNames[] = $tagName;
                        continue;
                    }

                    if ($assignResult['already_active']) {
                        $alreadyAssignedTagNames[] = $tagName;
                        continue;
                    }

                    $assignedTagNames[] = $tagName;
                    customerTagWriteAuditLog(
                        $connect,
                        $pageTitle,
                        'Edit',
                        USER_NAME . ' assigned customer tag [<b>' . htmlspecialchars((string) $tagName, ENT_QUOTES, 'UTF-8') . '</b>] to <b>' . htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8') . '</b>.',
                        $assignResult['query'],
                        customerTagGetAssignmentTable()
                    );
                }

                $messageParts = array();
                if (!empty($assignedTagNames)) {
                    $messageParts[] = 'Assigned tag(s) [' . implode(', ', $assignedTagNames) . '] to this customer.';
                }
                if (!empty($alreadyAssignedTagNames)) {
                    $messageParts[] = 'Already assigned: [' . implode(', ', $alreadyAssignedTagNames) . '].';
                }
                if (!empty($failedTagNames)) {
                    $messageParts[] = 'Unable to assign: [' . implode(', ', $failedTagNames) . '].';
                }

                $state['success'] = !empty($assignedTagNames);
                if (!empty($assignedTagNames)) {
                    $state['message_type'] = 'success';
                } elseif (!empty($alreadyAssignedTagNames) && empty($failedTagNames)) {
                    $state['message_type'] = 'info';
                } else {
                    $state['message_type'] = 'danger';
                }
                $state['message'] = implode(' ', $messageParts);
                break;

            case 'replace_assignment':
                if ($currentTagId <= 0) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Please choose the assigned tag that you want to change.';
                    break;
                }

                if ($selectedTagId <= 0) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Please select the replacement tag.';
                    break;
                }

                if ($currentTagId === $selectedTagId) {
                    $state['message_type'] = 'info';
                    $state['message'] = 'No changes Made';
                    break;
                }

                $oldTagRow = customerTagGetTagById($connect, $currentTagId);
                $newTagRow = customerTagGetTagById($connect, $selectedTagId);
                if (!$oldTagRow || !$newTagRow || (isset($newTagRow['status']) ? $newTagRow['status'] : '') !== 'A') {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Selected tag is not available.';
                    break;
                }

                if ($isDraftTagState) {
                    if (in_array($selectedTagId, $draftTagIds, true)) {
                        $state['message_type'] = 'info';
                        $state['message'] = 'Tag [' . $newTagRow['name'] . '] is already assigned to this customer.';
                        break;
                    }

                    foreach ($draftTagIds as $draftIndex => $draftTagId) {
                        if ((int) $draftTagId === $currentTagId) {
                            $draftTagIds[$draftIndex] = $selectedTagId;
                        }
                    }
                    $state['message_type'] = 'success';
                    $state['message'] = 'Updated tag from [' . $oldTagRow['name'] . '] to [' . $newTagRow['name'] . '].';
                    $state['success'] = true;
                    break;
                }

                $assignResult = customerTagAssignToCustomer($connect, $platform, $customerId, $selectedTagId);
                if (!$assignResult['success']) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Unable to assign the replacement tag right now.';
                    break;
                }

                if (!empty($assignResult['already_active'])) {
                    $state['message_type'] = 'info';
                    $state['message'] = 'Tag [' . $newTagRow['name'] . '] is already assigned to this customer.';
                    break;
                }

                $removeResult = customerTagRemoveFromCustomer($connect, $platform, $customerId, $currentTagId);
                if (!$removeResult['success'] || !$removeResult['changed']) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Unable to replace the current tag right now.';
                    break;
                }

                $state['message_type'] = 'success';
                $state['message'] = 'Updated tag from [' . $oldTagRow['name'] . '] to [' . $newTagRow['name'] . '].';
                $state['success'] = true;
                customerTagWriteAuditLog(
                    $connect,
                    $pageTitle,
                    'Edit',
                    USER_NAME . ' changed customer tag from [<b>' . htmlspecialchars((string) $oldTagRow['name'], ENT_QUOTES, 'UTF-8') . '</b>] to [<b>' . htmlspecialchars((string) $newTagRow['name'], ENT_QUOTES, 'UTF-8') . '</b>] for <b>' . htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8') . '</b>.',
                    trim($assignResult['query'] . '; ' . $removeResult['query']),
                    customerTagGetAssignmentTable()
                );
                break;

            case 'remove_assignment':
                if ($selectedTagId <= 0) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Please choose a tag to remove.';
                    break;
                }

                $tagRow = customerTagGetTagById($connect, $selectedTagId);
                if ($isDraftTagState) {
                    if (!in_array($selectedTagId, $draftTagIds, true)) {
                        $state['message_type'] = 'info';
                        $state['message'] = 'That tag is already removed from this customer.';
                        break;
                    }

                    $draftTagIds = array_values(array_filter($draftTagIds, function ($draftTagId) use ($selectedTagId) {
                        return (int) $draftTagId !== (int) $selectedTagId;
                    }));

                    $tagName = ($tagRow && isset($tagRow['name'])) ? $tagRow['name'] : ('Tag #' . $selectedTagId);
                    $state['message_type'] = 'success';
                    $state['message'] = 'Removed tag [' . $tagName . '] from this customer.';
                    $state['success'] = true;
                    break;
                }

                $removeResult = customerTagRemoveFromCustomer($connect, $platform, $customerId, $selectedTagId);
                if (!$removeResult['success']) {
                    $state['message_type'] = 'danger';
                    $state['message'] = 'Unable to remove the selected tag right now.';
                    break;
                }

                if (!$removeResult['changed']) {
                    $state['message_type'] = 'info';
                    $state['message'] = 'That tag is already removed from this customer.';
                    break;
                }

                $tagName = ($tagRow && isset($tagRow['name'])) ? $tagRow['name'] : ('Tag #' . $selectedTagId);
                $state['message_type'] = 'success';
                $state['message'] = 'Removed tag [' . $tagName . '] from this customer.';
                $state['success'] = true;
                customerTagWriteAuditLog(
                    $connect,
                    $pageTitle,
                    'delete',
                    USER_NAME . ' removed customer tag [<b>' . htmlspecialchars((string) $tagName, ENT_QUOTES, 'UTF-8') . '</b>] from <b>' . htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8') . '</b>.',
                    $removeResult['query'],
                    customerTagGetAssignmentTable()
                );
                break;
        }

        if (customerTagIsAjaxRequest()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/json');

            $activeTags = $isDraftTagState ? customerTagGetTagsByIds($connect, $draftTagIds) : customerTagGetDisplayTags($connect, $platform, $customerId, $draftToken);
            $tagOptions = customerTagGetActiveTagOptions($connect);
            $response = array(
                'success' => !empty($state['success']),
                'message' => $state['message'],
                'message_type' => $state['message_type'],
                'draft_tag_ids' => customerTagExtractTagIds($activeTags),
                'body_html' => customerTagRenderManagerBody(
                    $connect,
                    $platform,
                    $customerId,
                    $pageTitle,
                    $customerDisplayName,
                    array(
                        'active_tags' => $activeTags,
                        'tag_options' => $tagOptions,
                        'message' => $state['message'],
                        'message_type' => $state['message_type'],
                        'draft_token' => $draftToken,
                    )
                ),
                'title_badges_html' => customerTagRenderBadges($activeTags, 'customer-tag-title-badge-group', 'customer-tag-title-badge'),
            );

            echo json_encode($response);
            exit;
        }

        return $state;
    }
}

if (!function_exists('customerTagRenderActionHiddenFields')) {
    function customerTagRenderActionHiddenFields($platform, $customerId, $action, $draftToken = '')
    {
        $currentAct = postSpaceFilter('act');
        if ($currentAct === '') {
            $currentAct = input('act');
        }

        return '<input type="hidden" name="id" value="' . (int) $customerId . '">' .
            '<input type="hidden" name="act" value="' . htmlspecialchars((string) $currentAct, ENT_QUOTES, 'UTF-8') . '">' .
            '<input type="hidden" name="actionBtnHidden" value="manageCustomerTag">' .
            '<input type="hidden" name="customerTagPlatform" value="' . htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8') . '">' .
            '<input type="hidden" name="customerTagCustomerId" value="' . (int) $customerId . '">' .
            '<input type="hidden" name="customerTagDraftToken" value="' . htmlspecialchars((string) $draftToken, ENT_QUOTES, 'UTF-8') . '">' .
            '<input type="hidden" name="customerTagAction" value="' . htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('customerTagIsAjaxRequest')) {
    function customerTagIsAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('customerTagBuildSelectOptions')) {
    function customerTagBuildSelectOptions($tagOptions, $selectedTagId = 0, $placeholder = 'Select Tag', $includePlaceholder = true)
    {
        $selectedTagId = (int) $selectedTagId;
        $optionHtml = $includePlaceholder ? '<option value="">' . htmlspecialchars((string) $placeholder, ENT_QUOTES, 'UTF-8') . '</option>' : '';

        foreach ((array) $tagOptions as $tagOption) {
            $tagId = isset($tagOption['id']) ? (int) $tagOption['id'] : 0;
            if ($tagId <= 0) {
                continue;
            }

            $selected = $selectedTagId === $tagId ? ' selected' : '';
            $optionHtml .= '<option value="' . $tagId . '"' . $selected . '>' . htmlspecialchars((string) $tagOption['name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        return $optionHtml;
    }
}

if (!function_exists('customerTagRenderManagerBody')) {
    function customerTagRenderManagerBody($connect, $platform, $customerId, $pageTitle, $customerDisplayName = '', $options = array())
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $draftToken = isset($options['draft_token']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) $options['draft_token'])) : '';
        $tagRows = isset($options['active_tags']) && is_array($options['active_tags']) ? $options['active_tags'] : customerTagGetDisplayTags($connect, $platform, $customerId, $draftToken);
        $tagOptions = isset($options['tag_options']) && is_array($options['tag_options']) ? $options['tag_options'] : customerTagGetActiveTagOptions($connect);
        $tableReady = customerTagTableExists($connect);
        $assignedTagIds = customerTagExtractTagIds($tagRows);
        $assignOptionParts = array();
        $activeAssignedHtml = '';

        foreach ((array) $tagOptions as $tagOption) {
            $tagId = isset($tagOption['id']) ? (int) $tagOption['id'] : 0;
            if ($tagId <= 0) {
                continue;
            }

            $tagName = isset($tagOption['name']) ? trim((string) $tagOption['name']) : '';
            if ($tagName === '') {
                continue;
            }

            $isAssigned = in_array($tagId, $assignedTagIds, true);
            $assignOptionParts[] = '<label class="form-check customer-tag-checkbox-item' . ($isAssigned ? ' customer-tag-checkbox-item-disabled' : '') . '" data-tag-label="' . htmlspecialchars(function_exists('mb_strtolower') ? mb_strtolower($tagName, 'UTF-8') : strtolower($tagName), ENT_QUOTES, 'UTF-8') . '">' .
                '<input class="form-check-input" type="checkbox" name="customerTagSelectedIds[]" value="' . $tagId . '"' . ($isAssigned ? ' disabled' : '') . '>' .
                '<span class="form-check-label">' . htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8') . '</span>' .
                '</label>';
        }

        $assignOptionsHtml = empty($assignOptionParts)
            ? '<div class="text-muted">No active tags available.</div>'
            : '<div class="customer-tag-assign-picker">' .
                '<input type="text" class="form-control customer-tag-checkbox-search mb-2" placeholder="Search tag">' .
                '<div class="customer-tag-checkbox-list">' . implode('', $assignOptionParts) . '</div>' .
                '<div class="customer-tag-checkbox-empty text-muted" style="display:none;">No matching tag found.</div>' .
            '</div>';

        if (empty($tagRows)) {
            $activeAssignedHtml = '<div class="text-muted">No active tags assigned yet.</div>';
        } else {
            $assignedParts = array();
            foreach ($tagRows as $tagRow) {
                $tagId = isset($tagRow['tag_id']) ? (int) $tagRow['tag_id'] : 0;
                if ($tagId <= 0) {
                    continue;
                }

                $editOptionHtml = customerTagBuildSelectOptions($tagOptions, $tagId, 'Select Tag', false);
                $assignedParts[] = '<div class="customer-tag-assigned-item">' .
                    '<div class="customer-tag-assigned-name">' . customerTagRenderBadges(array($tagRow), 'customer-tag-modal-badge-group', 'customer-tag-modal-badge') . '</div>' .
                    '<div class="customer-tag-action-group">' .
                    '<button type="button" class="btn btn-sm btn-warning customer-tag-icon-btn customer-tag-edit-toggle" title="Change Tag"><i class="fa-solid fa-pen-to-square"></i></button>' .
                    '<form method="post" class="customer-tag-inline-form customer-tag-ajax-form" data-confirm="Are you sure you want to remove this tag?">' .
                    customerTagRenderActionHiddenFields($platform, $customerId, 'remove_assignment', $draftToken) .
                    '<input type="hidden" name="customerTagSelectedId" value="' . $tagId . '">' .
                    '<button type="submit" class="btn btn-sm btn-danger customer-tag-icon-btn" title="Remove Tag"><i class="fa-solid fa-trash"></i></button>' .
                    '</form>' .
                    '</div>' .
                    '</div>' .
                    '<div class="customer-tag-edit-panel" style="display:none;">' .
                    '<div class="customer-tag-edit-title">Change Tag</div>' .
                    '<form method="post" class="customer-tag-ajax-form customer-tag-edit-form">' .
                    customerTagRenderActionHiddenFields($platform, $customerId, 'replace_assignment', $draftToken) .
                    '<input type="hidden" name="customerTagCurrentId" value="' . $tagId . '">' .
                    '<select class="form-select" name="customerTagSelectedId">' . $editOptionHtml . '</select>' .
                    '<div class="customer-tag-edit-btn-row">' .
                    '<button type="submit" class="btn btn-lg btn-rounded btn-primary customer-tag-form-btn">Change</button>' .
                    '<button type="button" class="btn btn-lg btn-rounded btn-primary customer-tag-form-btn customer-tag-edit-cancel">Cancel</button>' .
                    '</div>' .
                    '</form>' .
                    '</div>';
            }

            $activeAssignedHtml = implode('', $assignedParts);
        }

        $html = '';

        if (!$tableReady) {
            $html .= '<div class="text-danger">Customer tag assignment table is not ready yet. Run insert_table.php first.</div>';
            return $html;
        }

        $html .= '<div class="customer-tag-modal-section"><div class="fw-semibold mb-2">Assigned Tags</div>' . $activeAssignedHtml . '</div>';
        $html .= '<div class="customer-tag-modal-section"><div class="fw-semibold mb-2">Assign Tag</div><form method="post" class="customer-tag-ajax-form">' .
            customerTagRenderActionHiddenFields($platform, $customerId, 'assign_existing', $draftToken) .
            $assignOptionsHtml .
            '<button type="submit" class="btn btn-lg btn-rounded btn-primary customer-tag-form-btn customer-tag-primary-btn">Assign Selected Tag</button>' .
            '</form></div>';

        return $html;
    }
}

if (!function_exists('customerTagRenderManager')) {
    function customerTagRenderManager($connect, $platform, $customerId, $pageTitle, $customerDisplayName = '', $options = array())
    {
        $platform = customerTagNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $allowManage = isset($options['allow_manage']) ? (bool) $options['allow_manage'] : true;
        $uiState = isset($options['ui_state']) && is_array($options['ui_state']) ? $options['ui_state'] : array();
        $draftToken = isset($options['draft_token']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string) $options['draft_token'])) : '';
        $tagRows = isset($options['active_tags']) && is_array($options['active_tags']) ? $options['active_tags'] : customerTagGetDisplayTags($connect, $platform, $customerId, $draftToken);
        $tagOptions = customerTagGetActiveTagOptions($connect);
        $resetDraftOnLoad = !empty($options['reset_draft_on_load']);

        if ($platform === '' || $customerId < 0 || !$allowManage) {
            return '';
        }

        $modalId = customerTagGetModalId($platform, $customerId);
        $openModal = !empty($uiState['reopen_modal']);
        $message = isset($uiState['message']) ? trim((string) $uiState['message']) : '';
        $messageType = isset($uiState['message_type']) ? trim((string) $uiState['message_type']) : 'info';
        $emptyDraftBodyHtml = ($resetDraftOnLoad && $customerId === 0) ? customerTagRenderManagerBody(
            $connect,
            $platform,
            $customerId,
            $pageTitle,
            $customerDisplayName,
            array(
                'active_tags' => array(),
                'tag_options' => $tagOptions,
                'message' => '',
                'message_type' => 'info',
                'draft_token' => $draftToken,
            )
        ) : '';

        $html = '';

        $html .= '<div class="modal fade customer-tag-manage-modal" id="' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '" tabindex="-1" aria-hidden="true">';
        $html .= '<div class="modal-dialog modal-dialog-scrollable">';
        $html .= '<div class="modal-content">';
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title">Manage Tag</h5>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
        $html .= '</div>';
        $html .= '<div class="modal-body">';
        $html .= '<div class="customer-tag-modal-body-content" data-platform="' . htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8') . '" data-customer-id="' . $customerId . '" data-draft-token="' . htmlspecialchars((string) $draftToken, ENT_QUOTES, 'UTF-8') . '" data-page-title="' . htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') . '" data-customer-display-name="' . htmlspecialchars((string) $customerDisplayName, ENT_QUOTES, 'UTF-8') . '">';
        $html .= customerTagRenderManagerBody(
            $connect,
            $platform,
            $customerId,
            $pageTitle,
            $customerDisplayName,
            array(
                'active_tags' => $tagRows,
                'tag_options' => $tagOptions,
                'message' => $message,
                'message_type' => $messageType,
                'draft_token' => $draftToken,
            )
        );
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<script>
document.addEventListener("DOMContentLoaded", function () {
    var modalElement = document.getElementById("' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '");
    if (!modalElement || modalElement.dataset.customerTagAjaxBound === "1") {
        return;
    }

    modalElement.dataset.customerTagAjaxBound = "1";
    var customerTagResetBodyHtml = ' . json_encode($emptyDraftBodyHtml) . ';
    var customerTagPopupAutoCloseTimer = null;

    function customerTagEscapeHtml(value) {
        var text = String(value || "");
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/\'/g, "&#039;");
    }

    function customerTagRefreshTitleNode(titleNode, badgesHtml) {
        if (!titleNode) {
            return;
        }

        var baseTitle = titleNode.getAttribute("data-base-title") || "";
        if (typeof badgesHtml === "string" && badgesHtml !== "") {
            titleNode.innerHTML = "<span class=\"customer-tag-title-wrap\"><span>" + customerTagEscapeHtml(baseTitle) + "</span>" + badgesHtml + "</span>";
            return;
        }

        titleNode.textContent = baseTitle;
    }

    function customerTagSyncBodyModalClass() {
        var hasOpenTagModal = !!document.querySelector(".customer-tag-manage-modal.show, #customerTagActionPopup.show");
        document.body.classList.toggle("customer-tag-modal-open", hasOpenTagModal);
    }

    function customerTagCloseManagerModal() {
        if (!modalElement || typeof bootstrap === "undefined" || !bootstrap.Modal) {
            return;
        }

        var managerModal = bootstrap.Modal.getInstance(modalElement);
        if (managerModal && modalElement.classList.contains("show")) {
            managerModal.hide();
        }
    }

    function customerTagSchedulePopupAutoClose(popupElement) {
        if (!popupElement || typeof bootstrap === "undefined" || !bootstrap.Modal) {
            return;
        }

        if (customerTagPopupAutoCloseTimer) {
            window.clearTimeout(customerTagPopupAutoCloseTimer);
        }

        customerTagPopupAutoCloseTimer = window.setTimeout(function () {
            var popupModal = bootstrap.Modal.getInstance(popupElement);
            if (popupModal && popupElement.classList.contains("show")) {
                popupModal.hide();
            }
        }, 1800);
    }

    function customerTagEnsurePopupModal() {
        var popupElement = document.getElementById("customerTagActionPopup");
        if (popupElement) {
            return popupElement;
        }

        var popupWrap = document.createElement("div");
        popupWrap.innerHTML =
            \'<div class="modal fade" id="customerTagActionPopup" tabindex="-1" aria-hidden="true">\' +
                \'<div class="modal-dialog modal-dialog-centered" style="font-family:\\\'Segoe UI\\\', Tahoma, Geneva, Verdana, sans-serif;">\' +
                    \'<div class="modal-content">\' +
                        \'<div class="modal-body fs-6 mt-3">\' +
                            \'<p id="customerTagActionPopupTitle" style="text-align:center; font-weight:bold; font-size:25px; margin-bottom:0;"></p>\' +
                        \'</div>\' +
                        \'<div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px;">\' +
                            \'<button type="button" class="btn" data-bs-dismiss="modal" style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius:24px; text-transform:none;">Continue</button>\' +
                        \'</div>\' +
                    \'</div>\' +
                \'</div>\' +
            \'</div>\';
        document.body.appendChild(popupWrap.firstChild);
        popupElement = document.getElementById("customerTagActionPopup");

        if (popupElement) {
            popupElement.addEventListener("shown.bs.modal", customerTagSyncBodyModalClass);
            popupElement.addEventListener("hidden.bs.modal", function () {
                if (customerTagPopupAutoCloseTimer) {
                    window.clearTimeout(customerTagPopupAutoCloseTimer);
                    customerTagPopupAutoCloseTimer = null;
                }
                customerTagSyncBodyModalClass();
                customerTagCloseManagerModal();
            });
        }

        return popupElement;
    }

    function customerTagShowPopupMessage(message, isSuccess) {
        var popupMessage = String(message || "").trim();
        if (!popupMessage) {
            return;
        }

        if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
            showNotification(popupMessage, isSuccess ? "success" : "info");
            return;
        }

        var popupElement = customerTagEnsurePopupModal();
        if (!popupElement) {
            showNotification(popupMessage, isSuccess ? "success" : "info");
            return;
        }

        var titleNode = document.getElementById("customerTagActionPopupTitle");
        if (titleNode) {
            titleNode.textContent = popupMessage;
        }

        bootstrap.Modal.getOrCreateInstance(popupElement, {
            keyboard: false,
            backdrop: "static"
        }).show();
        customerTagSchedulePopupAutoClose(popupElement);
    }

    function customerTagClearDraftUi() {
        var bodyWrap = modalElement.querySelector(".customer-tag-modal-body-content");
        if (bodyWrap && typeof customerTagResetBodyHtml === "string" && customerTagResetBodyHtml !== "") {
            bodyWrap.innerHTML = customerTagResetBodyHtml;
        }

        document.querySelectorAll(".customer-tag-page-title").forEach(function (titleNode) {
            customerTagRefreshTitleNode(titleNode, "");
        });

        document.querySelectorAll(".customer-tag-draft-input[data-platform=\"' . htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8') . '\"]").forEach(function (draftInput) {
            draftInput.value = "";
        });
    }

    function customerTagApplyCheckboxSearch(searchInput) {
        if (!searchInput) {
            return;
        }

        var pickerWrap = searchInput.closest(".customer-tag-assign-picker");
        if (!pickerWrap) {
            return;
        }

        var keyword = String(searchInput.value || "").toLowerCase().trim();
        var hasVisibleItem = false;

        pickerWrap.querySelectorAll(".customer-tag-checkbox-item").forEach(function (item) {
            var itemText = item.getAttribute("data-tag-label") || "";
            var isVisible = keyword === "" || itemText.indexOf(keyword) !== -1;
            item.style.display = isVisible ? "" : "none";
            if (isVisible) {
                hasVisibleItem = true;
            }
        });

        var emptyNode = pickerWrap.querySelector(".customer-tag-checkbox-empty");
        if (emptyNode) {
            emptyNode.style.display = hasVisibleItem ? "none" : "";
        }
    }

    modalElement.addEventListener("shown.bs.modal", customerTagSyncBodyModalClass);
    modalElement.addEventListener("hidden.bs.modal", customerTagSyncBodyModalClass);
    ' . (($resetDraftOnLoad && $customerId === 0) ? '
    modalElement.addEventListener("show.bs.modal", function () {
        customerTagClearDraftUi();
    });
    ' : '') . '

    modalElement.addEventListener("click", function (event) {
        var editToggle = event.target.closest(".customer-tag-edit-toggle");
        if (editToggle) {
            var assignedItem = editToggle.closest(".customer-tag-assigned-item");
            var editPanel = assignedItem ? assignedItem.nextElementSibling : null;
            if (!editPanel || !editPanel.classList.contains("customer-tag-edit-panel")) {
                return;
            }

            modalElement.querySelectorAll(".customer-tag-edit-panel").forEach(function (panel) {
                if (panel !== editPanel) {
                    panel.style.display = "none";
                }
            });

            editPanel.style.display = editPanel.style.display === "none" || editPanel.style.display === "" ? "block" : "none";
            return;
        }

        var cancelBtn = event.target.closest(".customer-tag-edit-cancel");
        if (cancelBtn) {
            var cancelPanel = cancelBtn.closest(".customer-tag-edit-panel");
            if (cancelPanel) {
                cancelPanel.style.display = "none";
            }
            return;
        }
    });

    modalElement.addEventListener("input", function (event) {
        var searchInput = event.target.closest(".customer-tag-checkbox-search");
        if (searchInput) {
            customerTagApplyCheckboxSearch(searchInput);
        }
    });

    modalElement.addEventListener("submit", function (event) {
        var form = event.target.closest(".customer-tag-ajax-form");
        if (!form) {
            return;
        }

        event.preventDefault();

        var confirmMessage = form.getAttribute("data-confirm");
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }

        var bodyWrap = modalElement.querySelector(".customer-tag-modal-body-content");
        if (!bodyWrap) {
            return;
        }

        var formData = new FormData(form);
        var customerId = parseInt(bodyWrap.getAttribute("data-customer-id") || "0", 10);
        var platform = bodyWrap.getAttribute("data-platform") || "";
        if (customerId === 0) {
            var draftInput = document.querySelector(".customer-tag-draft-input[data-platform=\"" + platform + "\"]");
            if (draftInput) {
                formData.set("customerTagDraftIds", draftInput.value || "");
            }
        }

        fetch(window.location.href, {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            },
            body: formData
        })
        .then(function (response) {
            return response.text().then(function (text) {
                var data = null;
                try {
                    data = JSON.parse(text);
                } catch (error) {
                    console.error("Customer tag AJAX parse error:", error, text);
                    throw error;
                }

                if (!response.ok) {
                    console.error("Customer tag AJAX HTTP error:", response.status, data);
                }

                return data;
            });
        })
        .then(function (data) {
            if (!data || typeof data !== "object") {
                return;
            }

            try {
                if (typeof data.body_html === "string") {
                    bodyWrap.innerHTML = data.body_html;
                }

                if (typeof data.title_badges_html === "string") {
                    document.querySelectorAll(".customer-tag-page-title").forEach(function (titleNode) {
                        customerTagRefreshTitleNode(titleNode, data.title_badges_html);
                    });
                }

                if (Array.isArray(data.draft_tag_ids)) {
                    var draftValue = data.draft_tag_ids.join(",");
                    document.querySelectorAll(".customer-tag-draft-input[data-platform=\"' . htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8') . '\"]").forEach(function (draftInput) {
                        draftInput.value = draftValue;
                    });
                }
            } catch (error) {
                console.error("Customer tag DOM update error:", error, data);
            }

            if (typeof data.message === "string" && data.message.trim() !== "") {
                customerTagShowPopupMessage(data.message, !!data.success);
            }
        })
        .catch(function (error) {
            console.error("Customer tag AJAX request failed:", error);
            customerTagShowPopupMessage("Unable to update customer tags right now.", false);
        });
    });

    ' . (($resetDraftOnLoad && $customerId === 0) ? '
    function customerTagRunDraftReset() {
        var bodyWrap = modalElement.querySelector(".customer-tag-modal-body-content");
        customerTagClearDraftUi();

        var draftToken = bodyWrap ? (bodyWrap.getAttribute("data-draft-token") || "") : "";
        var formData = new FormData();
        formData.append("actionBtnHidden", "manageCustomerTag");
        formData.append("customerTagPlatform", "' . htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8') . '");
        formData.append("customerTagCustomerId", "0");
        formData.append("customerTagDraftToken", draftToken);
        formData.append("customerTagAction", "reset_draft");

        fetch(window.location.href, {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            },
            body: formData
        })
        .then(function (response) {
            return response.text().then(function (text) {
                return text ? JSON.parse(text) : null;
            });
        })
        .then(function (data) {
            if (!data || typeof data !== "object") {
                return;
            }

            if (bodyWrap && typeof data.body_html === "string") {
                bodyWrap.innerHTML = data.body_html;
            }

            if (typeof data.title_badges_html === "string") {
                document.querySelectorAll(".customer-tag-page-title").forEach(function (titleNode) {
                    customerTagRefreshTitleNode(titleNode, data.title_badges_html);
                });
            }

            document.querySelectorAll(".customer-tag-draft-input[data-platform=\"' . htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8') . '\"]").forEach(function (draftInput) {
                draftInput.value = "";
            });
        })
        .catch(function (error) {
            console.error("Customer tag draft reset failed:", error);
            customerTagClearDraftUi();
        });
    }

    customerTagRunDraftReset();
    window.addEventListener("pageshow", function () {
        customerTagRunDraftReset();
    });
    ' : '') . '
});
</script>';

        if ($openModal) {
            $html .= '<script>
document.addEventListener("DOMContentLoaded", function () {
    var modalElement = document.getElementById("' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '");
    if (!modalElement || typeof bootstrap === "undefined" || !bootstrap.Modal) {
        return;
    }
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>';
        }

        if ($message !== '') {
            $html .= '<script>
document.addEventListener("DOMContentLoaded", function () {
    var popupMessage = ' . json_encode((string) $message) . ';
    if (!popupMessage) {
        return;
    }

    if (typeof bootstrap === "undefined" || !bootstrap.Modal) {
        showNotification(popupMessage, "info");
        return;
    }

    var managerModalElement = document.getElementById(' . json_encode((string) $modalId) . ');
    var customerTagPopupAutoCloseTimer = null;

    var popupElement = document.getElementById("customerTagActionPopup");
    if (!popupElement) {
        var popupWrap = document.createElement("div");
        popupWrap.innerHTML =
            \'<div class="modal fade" id="customerTagActionPopup" tabindex="-1" aria-hidden="true">\' +
                \'<div class="modal-dialog modal-dialog-centered" style="font-family:\\\'Segoe UI\\\', Tahoma, Geneva, Verdana, sans-serif;">\' +
                    \'<div class="modal-content">\' +
                        \'<div class="modal-body fs-6 mt-3">\' +
                            \'<p id="customerTagActionPopupTitle" style="text-align:center; font-weight:bold; font-size:25px; margin-bottom:0;"></p>\' +
                        \'</div>\' +
                        \'<div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px;">\' +
                            \'<button type="button" class="btn" data-bs-dismiss="modal" style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius:24px; text-transform:none;">Continue</button>\' +
                        \'</div>\' +
                    \'</div>\' +
                \'</div>\' +
            \'</div>\';
        document.body.appendChild(popupWrap.firstChild);
        popupElement = document.getElementById("customerTagActionPopup");
    }

    if (popupElement && !popupElement.hasAttribute("data-customer-tag-close-bound")) {
        popupElement.setAttribute("data-customer-tag-close-bound", "1");
        popupElement.addEventListener("hidden.bs.modal", function () {
            if (customerTagPopupAutoCloseTimer) {
                window.clearTimeout(customerTagPopupAutoCloseTimer);
                customerTagPopupAutoCloseTimer = null;
            }

            if (!managerModalElement || typeof bootstrap === "undefined" || !bootstrap.Modal) {
                return;
            }

            var managerModal = bootstrap.Modal.getInstance(managerModalElement);
            if (managerModal && managerModalElement.classList.contains("show")) {
                managerModal.hide();
            }
        });
    }

    var titleNode = document.getElementById("customerTagActionPopupTitle");
    if (titleNode) {
        titleNode.textContent = popupMessage;
    }

    var popupModal = bootstrap.Modal.getOrCreateInstance(popupElement, {
        keyboard: false,
        backdrop: "static"
    });
    popupModal.show();

    customerTagPopupAutoCloseTimer = window.setTimeout(function () {
        if (popupElement.classList.contains("show")) {
            popupModal.hide();
        }
    }, 3000);
});
</script>';
        }

        return $html;
    }
}
