<?php

if (!function_exists('systemAlertEscape')) {
    function systemAlertEscape($connect, $value)
    {
        return mysqli_real_escape_string($connect, (string) $value);
    }
}

if (!function_exists('systemAlertNowDate')) {
    function systemAlertNowDate()
    {
        return date('Y-m-d');
    }
}

if (!function_exists('systemAlertNowTime')) {
    function systemAlertNowTime()
    {
        return date('H:i:s');
    }
}

if (!function_exists('systemAlertGetTableName')) {
    function systemAlertGetTableName()
    {
        return defined('SYSTEM_ALERT_MESSAGE') ? SYSTEM_ALERT_MESSAGE : 'system_alert_message';
    }
}

if (!function_exists('systemAlertGetModuleConfigs')) {
    function systemAlertGetModuleConfigs()
    {
        return array(
            'shopee_waiting_to_pack' => array(
                'pin_group_id' => 128,
                'title' => 'Shopee Waiting To Pack',
                'path' => '/finance/waiting_to_pack.php',
                'action_label' => 'View Orders',
            ),
            'shopee_arrival_management' => array(
                'pin_group_id' => 147,
                'title' => 'Shopee Arrival Management',
                'path' => '/finance/arrival_management.php',
                'action_label' => 'Open Page',
            ),
            'daily_flow_report' => array(
                'pin_group_id' => 148,
                'title' => 'Daily Flow Report',
                'path' => '/finance/flow_report.php',
                'action_label' => 'Open Report',
            ),
            'customer_follow_up' => array(
                'pin_group_id' => 151,
                'title' => 'Customer Follow-Up',
                'path' => '/customer/customer_follow_up_list.php',
                'action_label' => 'Open Follow-Up',
            ),
            'campaign_follow_up_task' => array(
                'pin_group_id' => 153,
                'title' => 'Campaign Follow-Up Task',
                'path' => '/campaign/campaign_follow_up_task.php',
                'action_label' => 'Open Follow-Up',
            ),
            'waiting_admin_final_check' => array(
                'pin_group_id' => 129,
                'title' => 'Waiting Admin Final Check',
                'path' => '/shopee/shopee_verify.php',
                'action_label' => 'Open Page',
            ),
            'order_delete_approval' => array(
                'pin_group_id' => 0,
                'title' => 'Order Delete Approval',
                'path' => '/dashboard.php',
                'action_label' => 'Review Request',
            ),
        );
    }
}

if (!function_exists('systemAlertBuildActionUrl')) {
    function systemAlertBuildActionUrl($moduleKey, $params = array())
    {
        $configs = systemAlertGetModuleConfigs();
        $config = isset($configs[$moduleKey]) ? $configs[$moduleKey] : array();
        $path = isset($config['path']) ? (string) $config['path'] : '/dashboard.php';
        $baseUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        $url = $baseUrl !== '' ? $baseUrl . $path : $path;
        $queryString = http_build_query(array_filter((array) $params, function ($value) {
            return $value !== null && $value !== '';
        }));

        if ($queryString !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $queryString;
        }

        return $url;
    }
}

if (!function_exists('systemAlertResolveRowActionUrl')) {
    function systemAlertResolveRowActionUrl($alertRow)
    {
        $actionUrl = trim((string) (isset($alertRow['action_url']) ? $alertRow['action_url'] : ''));
        $moduleKey = trim((string) (isset($alertRow['module_key']) ? $alertRow['module_key'] : ''));
        $relatedTable = trim((string) (isset($alertRow['related_table']) ? $alertRow['related_table'] : ''));

        // Module notice alerts should always follow the current module route.
        if ($moduleKey !== '' && $relatedTable === 'module_notice') {
            $configs = systemAlertGetModuleConfigs();
            if (isset($configs[$moduleKey])) {
                return systemAlertBuildActionUrl($moduleKey);
            }
        }

        return $actionUrl;
    }
}

if (!function_exists('systemAlertBuildDailyFlowTransitionLabel')) {
    function systemAlertBuildDailyFlowTransitionLabel($summaryRow)
    {
        $fromLabel = trim((string) (isset($summaryRow['from_label']) ? $summaryRow['from_label'] : ''));
        $toLabel = trim((string) (isset($summaryRow['to_label']) ? $summaryRow['to_label'] : ''));

        if ($fromLabel !== '' && $toLabel !== '' && strcasecmp($fromLabel, $toLabel) !== 0) {
            return $fromLabel . ' -> ' . $toLabel;
        }
        if ($toLabel !== '') {
            return $toLabel;
        }
        if ($fromLabel !== '') {
            return $fromLabel;
        }

        return 'Daily flow activity';
    }
}

if (!function_exists('systemAlertBuildDailyFlowSummaryMessage')) {
    function systemAlertBuildDailyFlowSummaryMessage($userName, $summaryRows)
    {
        $userName = trim((string) $userName);
        $summaryRows = is_array($summaryRows) ? $summaryRows : array();
        $totalActions = 0;
        foreach ($summaryRows as $summaryRow) {
            $totalActions += isset($summaryRow['total_count']) ? (int) $summaryRow['total_count'] : 0;
        }

        if ($totalActions <= 0) {
            return '';
        }

        $message = ($userName !== '' ? $userName : 'This user') . ' completed today\'s activity summary';
        $message .= ' with ' . $totalActions . ' tracked action' . ($totalActions === 1 ? '' : 's');

        $summaryParts = array();
        foreach (array_slice($summaryRows, 0, 3) as $summaryRow) {
            $count = isset($summaryRow['total_count']) ? (int) $summaryRow['total_count'] : 0;
            if ($count <= 0) {
                continue;
            }

            $summaryParts[] = systemAlertBuildDailyFlowTransitionLabel($summaryRow) . ' (' . $count . ')';
        }

        if (!empty($summaryParts)) {
            $message .= ': ' . implode(', ', $summaryParts);
        }

        $message .= '. Click to view details.';
        return $message;
    }
}

if (!function_exists('systemAlertNormalizeUserId')) {
    function systemAlertNormalizeUserId($userId)
    {
        return (int) $userId > 0 ? (int) $userId : 0;
    }
}

if (!function_exists('systemAlertReadUserRow')) {
    function systemAlertReadUserRow($connect, $userId)
    {
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return array();
        }

        $selectFields = array('`id`', '`name`', '`access_id`', '`status`');
        foreach (systemAlertGetAvailableSupervisorFields($connect) as $fieldName) {
            $selectFields[] = "`" . $fieldName . "`";
        }

        $sql = "SELECT " . implode(', ', $selectFields) . "
                FROM `" . USR_USER . "`
                WHERE `id` = " . $userId . "
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('systemAlertGetAvailableSupervisorFields')) {
    function systemAlertGetAvailableSupervisorFields($connect)
    {
        static $fieldCache = array();

        if (!($connect instanceof mysqli)) {
            return array();
        }

        $cacheKey = spl_object_hash($connect);
        if (isset($fieldCache[$cacheKey])) {
            return $fieldCache[$cacheKey];
        }

        $availableFields = array();
        $candidateFields = array(
            'main_report_supervisor',
            'report_supervisor',
            'supervisor_id',
            'leader_id',
            'report_to',
            'second_report_supervisor',
        );
        foreach ($candidateFields as $fieldName) {
            $sql = "SHOW COLUMNS FROM `" . USR_USER . "` LIKE '" . systemAlertEscape($connect, $fieldName) . "'";
            $result = mysqli_query($connect, $sql);
            if ($result && $result->num_rows > 0) {
                $availableFields[] = $fieldName;
            }
        }

        $fieldCache[$cacheKey] = $availableFields;
        return $availableFields;
    }
}

if (!function_exists('systemAlertLoadActiveUserRows')) {
    function systemAlertLoadActiveUserRows($connect)
    {
        $rows = array();
        if (!($connect instanceof mysqli)) {
            return $rows;
        }

        $selectFields = array('`id`', '`name`', '`access_id`', '`status`');
        foreach (systemAlertGetAvailableSupervisorFields($connect) as $fieldName) {
            $selectFields[] = "`" . $fieldName . "`";
        }

        $sql = "SELECT " . implode(', ', $selectFields) . "
                FROM `" . USR_USER . "`
                WHERE `status` = 'A'
                ORDER BY `id` ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('systemAlertResolveUserSupervisorIds')) {
    function systemAlertResolveUserSupervisorIds($connect, $userRow)
    {
        if (!is_array($userRow)) {
            return array();
        }

        $availableFields = systemAlertGetAvailableSupervisorFields($connect);
        if (empty($availableFields)) {
            // TODO: If reporting lines live outside the user table in this installation, connect that mapping here.
            return array();
        }

        $supervisorUserIds = array();
        foreach ($availableFields as $fieldName) {
            $supervisorUserId = isset($userRow[$fieldName]) ? (int) $userRow[$fieldName] : 0;
            if ($supervisorUserId > 0) {
                $supervisorUserIds[$supervisorUserId] = $supervisorUserId;
            }
        }

        return array_values($supervisorUserIds);
    }
}

if (!function_exists('systemAlertResolveUserSupervisorId')) {
    function systemAlertResolveUserSupervisorId($connect, $userRow)
    {
        $supervisorUserIds = systemAlertResolveUserSupervisorIds($connect, $userRow);
        return !empty($supervisorUserIds) ? (int) $supervisorUserIds[0] : 0;
    }
}

if (!function_exists('systemAlertGetUserGroupId')) {
    function systemAlertGetUserGroupId($connect, $userId)
    {
        $userRow = systemAlertReadUserRow($connect, $userId);
        return isset($userRow['access_id']) ? (int) $userRow['access_id'] : 0;
    }
}

if (!function_exists('systemAlertExtractPinAccessValues')) {
    function systemAlertExtractPinAccessValues($pinString, $pinGroupId)
    {
        $pinGroupId = (int) $pinGroupId;
        if ($pinGroupId <= 0) {
            return array();
        }

        $entries = explode('+', (string) $pinString);
        foreach ($entries as $entry) {
            $parts = explode(':', trim((string) $entry, '[]'));
            if (count($parts) !== 2 || (int) trim((string) $parts[0]) !== $pinGroupId) {
                continue;
            }

            return array_values(array_filter(array_map('trim', explode(',', (string) $parts[1])), 'strlen'));
        }

        return array();
    }
}

if (!function_exists('systemAlertUserHasAccessToModule')) {
    function systemAlertUserHasAccessToModule($connect, $userId, $moduleKey)
    {
        $userId = systemAlertNormalizeUserId($userId);
        $configs = systemAlertGetModuleConfigs();
        $config = isset($configs[$moduleKey]) ? $configs[$moduleKey] : array();
        $pinGroupId = isset($config['pin_group_id']) ? (int) $config['pin_group_id'] : 0;
        if (!($connect instanceof mysqli) || $userId <= 0 || $pinGroupId <= 0) {
            return false;
        }

        $userRow = systemAlertReadUserRow($connect, $userId);
        $groupId = isset($userRow['access_id']) ? (int) $userRow['access_id'] : 0;
        if ($groupId <= 0) {
            return false;
        }

        $groupResult = getData('pins', "id = '" . $groupId . "'", 'LIMIT 1', USR_GRP, $connect);
        if (!$groupResult || $groupResult->num_rows === 0) {
            return false;
        }

        $groupRow = $groupResult->fetch_assoc();
        $allowedActions = systemAlertExtractPinAccessValues(isset($groupRow['pins']) ? $groupRow['pins'] : '', $pinGroupId);
        return !empty($allowedActions);
    }
}

if (!function_exists('systemAlertCreate')) {
    function systemAlertCreate($connect, $data)
    {
        $tblName = systemAlertGetTableName();
        $targetUserId = isset($data['target_user_id']) ? (int) $data['target_user_id'] : 0;
        if (!($connect instanceof mysqli) || $targetUserId <= 0) {
            return 0;
        }

        $createDate = trim((string) (isset($data['create_date']) ? $data['create_date'] : systemAlertNowDate()));
        $createTime = trim((string) (isset($data['create_time']) ? $data['create_time'] : systemAlertNowTime()));
        $displayDate = trim((string) (isset($data['display_date']) ? $data['display_date'] : $createDate));
        $actorUserId = trim((string) (isset($data['create_by']) ? $data['create_by'] : (defined('USER_ID') ? USER_ID : 'SYSTEM')));

        $sql = "INSERT INTO `" . $tblName . "` (
                    `module_key`,
                    `notification_type`,
                    `target_user_id`,
                    `target_user_group_id`,
                    `title`,
                    `message`,
                    `action_url`,
                    `action_label`,
                    `related_table`,
                    `related_id`,
                    `related_platform`,
                    `is_read`,
                    `read_date`,
                    `read_time`,
                    `display_date`,
                    `expire_date`,
                    `create_by`,
                    `create_date`,
                    `create_time`,
                    `status`
                ) VALUES (
                    '" . systemAlertEscape($connect, isset($data['module_key']) ? $data['module_key'] : '') . "',
                    '" . systemAlertEscape($connect, isset($data['notification_type']) ? $data['notification_type'] : '') . "',
                    " . $targetUserId . ",
                    " . ((int) (isset($data['target_user_group_id']) ? $data['target_user_group_id'] : 0) > 0 ? (int) $data['target_user_group_id'] : 'NULL') . ",
                    " . (trim((string) (isset($data['title']) ? $data['title'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['title']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['message']) ? $data['message'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['message']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['action_url']) ? $data['action_url'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['action_url']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['action_label']) ? $data['action_label'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['action_label']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['related_table']) ? $data['related_table'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['related_table']) . "'") : 'NULL') . ",
                    " . ((int) (isset($data['related_id']) ? $data['related_id'] : 0) > 0 ? (int) $data['related_id'] : 'NULL') . ",
                    " . (trim((string) (isset($data['related_platform']) ? $data['related_platform'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['related_platform']) . "'") : 'NULL') . ",
                    '" . systemAlertEscape($connect, isset($data['is_read']) ? $data['is_read'] : 'N') . "',
                    " . (trim((string) (isset($data['read_date']) ? $data['read_date'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['read_date']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['read_time']) ? $data['read_time'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['read_time']) . "'") : 'NULL') . ",
                    " . ($displayDate !== '' ? ("'" . systemAlertEscape($connect, $displayDate) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['expire_date']) ? $data['expire_date'] : '')) !== '' ? ("'" . systemAlertEscape($connect, $data['expire_date']) . "'") : 'NULL') . ",
                    '" . systemAlertEscape($connect, $actorUserId) . "',
                    '" . systemAlertEscape($connect, $createDate) . "',
                    '" . systemAlertEscape($connect, $createTime) . "',
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        return (int) mysqli_insert_id($connect);
    }
}

if (!function_exists('systemAlertCreateOnce')) {
    function systemAlertCreateOnce($connect, $data)
    {
        $tblName = systemAlertGetTableName();
        $targetUserId = isset($data['target_user_id']) ? (int) $data['target_user_id'] : 0;
        if (!($connect instanceof mysqli) || $targetUserId <= 0) {
            return 0;
        }

        $moduleKey = trim((string) (isset($data['module_key']) ? $data['module_key'] : ''));
        $notificationType = trim((string) (isset($data['notification_type']) ? $data['notification_type'] : ''));
        $displayDate = trim((string) (isset($data['display_date']) ? $data['display_date'] : systemAlertNowDate()));
        $relatedId = isset($data['related_id']) ? (int) $data['related_id'] : 0;
        if ($moduleKey === '' || $notificationType === '') {
            return 0;
        }

        $displayDateCondition = $displayDate !== ''
            ? "`display_date` = '" . systemAlertEscape($connect, $displayDate) . "'"
            : "`display_date` IS NULL";

        $sql = "SELECT `id`
                FROM `" . $tblName . "`
                WHERE `module_key` = '" . systemAlertEscape($connect, $moduleKey) . "'
                  AND `notification_type` = '" . systemAlertEscape($connect, $notificationType) . "'
                  AND `target_user_id` = " . $targetUserId . "
                  AND IFNULL(`related_id`, 0) = " . $relatedId . "
                  AND " . $displayDateCondition . "
                  AND `status` = 'A'
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['id']) ? (int) $row['id'] : 0;
        }

        return systemAlertCreate($connect, $data);
    }
}

if (!function_exists('systemAlertGetUnreadCount')) {
    function systemAlertGetUnreadCount($connect, $userId)
    {
        $tblName = systemAlertGetTableName();
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS `total`
                FROM `" . $tblName . "`
                WHERE `target_user_id` = " . $userId . "
                  AND `is_read` = 'N'
                  AND `status` = 'A'
                  AND (`expire_date` IS NULL OR `expire_date` >= CURDATE())";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['total']) ? (int) $row['total'] : 0;
        }

        return 0;
    }
}

if (!function_exists('systemAlertGetTotalCount')) {
    function systemAlertGetTotalCount($connect, $userId)
    {
        $tblName = systemAlertGetTableName();
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS `total`
                FROM `" . $tblName . "`
                WHERE `target_user_id` = " . $userId . "
                  AND `status` = 'A'
                  AND (`expire_date` IS NULL OR `expire_date` >= CURDATE())";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['total']) ? (int) $row['total'] : 0;
        }

        return 0;
    }
}

if (!function_exists('systemAlertFetchForUser')) {
    function systemAlertFetchForUser($connect, $userId, $limit = 10)
    {
        $tblName = systemAlertGetTableName();
        $userId = systemAlertNormalizeUserId($userId);
        $limit = max(1, (int) $limit);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . $tblName . "`
                WHERE `target_user_id` = " . $userId . "
                  AND `status` = 'A'
                  AND (`expire_date` IS NULL OR `expire_date` >= CURDATE())
                ORDER BY
                    CASE WHEN `is_read` = 'N' THEN 0 ELSE 1 END ASC,
                    COALESCE(`display_date`, `create_date`) DESC,
                    `create_time` DESC,
                    `id` DESC
                LIMIT " . $limit;
        $result = mysqli_query($connect, $sql);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('systemAlertFormatModuleLabel')) {
    function systemAlertFormatModuleLabel($moduleKey)
    {
        $moduleKey = trim((string) $moduleKey);
        $configs = systemAlertGetModuleConfigs();
        if ($moduleKey !== '' && isset($configs[$moduleKey]['title']) && trim((string) $configs[$moduleKey]['title']) !== '') {
            return trim((string) $configs[$moduleKey]['title']);
        }

        if ($moduleKey === '') {
            return 'General';
        }

        $label = str_replace(array('_', '-'), ' ', strtolower($moduleKey));
        $label = preg_replace('/\s+/', ' ', (string) $label);
        return ucwords(trim((string) $label));
    }
}

if (!function_exists('systemAlertGetModuleFilterOptions')) {
    function systemAlertGetModuleFilterOptions()
    {
        $options = array();
        foreach (systemAlertGetModuleConfigs() as $moduleKey => $config) {
            $options[$moduleKey] = isset($config['title']) ? (string) $config['title'] : systemAlertFormatModuleLabel($moduleKey);
        }

        return $options;
    }
}

if (!function_exists('systemAlertFetchListForUser')) {
    function systemAlertFetchListForUser($connect, $userId, $filters = array())
    {
        $tblName = systemAlertGetTableName();
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return array();
        }

        $filters = is_array($filters) ? $filters : array();
        $where = array(
            "`target_user_id` = " . $userId,
            "`status` = 'A'",
            "(`expire_date` IS NULL OR `expire_date` >= CURDATE())",
        );

        $readStatus = strtolower(trim((string) (isset($filters['read_status']) ? $filters['read_status'] : '')));
        if ($readStatus === 'unread') {
            $where[] = "`is_read` = 'N'";
        } else if ($readStatus === 'read') {
            $where[] = "`is_read` = 'Y'";
        }

        $moduleKey = trim((string) (isset($filters['module_key']) ? $filters['module_key'] : ''));
        if ($moduleKey !== '') {
            $where[] = "`module_key` = '" . systemAlertEscape($connect, $moduleKey) . "'";
        }

        $dateFrom = trim((string) (isset($filters['date_from']) ? $filters['date_from'] : ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = "COALESCE(`display_date`, `create_date`) >= '" . systemAlertEscape($connect, $dateFrom) . "'";
        }

        $dateTo = trim((string) (isset($filters['date_to']) ? $filters['date_to'] : ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = "COALESCE(`display_date`, `create_date`) <= '" . systemAlertEscape($connect, $dateTo) . "'";
        }

        $keyword = trim((string) (isset($filters['keyword']) ? $filters['keyword'] : ''));
        if ($keyword !== '') {
            $safeKeyword = systemAlertEscape($connect, $keyword);
            $where[] = "(
                `title` LIKE '%" . $safeKeyword . "%'
                OR `message` LIKE '%" . $safeKeyword . "%'
                OR `module_key` LIKE '%" . $safeKeyword . "%'
            )";
        }

        $sql = "SELECT *
                FROM `" . $tblName . "`
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    CASE WHEN `is_read` = 'N' THEN 0 ELSE 1 END ASC,
                    COALESCE(`display_date`, `create_date`) DESC,
                    `create_time` DESC,
                    `id` DESC";
        $result = mysqli_query($connect, $sql);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('systemAlertReadRow')) {
    function systemAlertReadRow($connect, $alertId, $userId = 0)
    {
        $tblName = systemAlertGetTableName();
        $alertId = (int) $alertId;
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $alertId <= 0) {
            return array();
        }

        $conditions = array(
            "`id` = " . $alertId,
            "`status` = 'A'",
        );
        if ($userId > 0) {
            $conditions[] = "`target_user_id` = " . $userId;
        }

        $sql = "SELECT *
                FROM `" . $tblName . "`
                WHERE " . implode(' AND ', $conditions) . "
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('systemAlertMarkRead')) {
    function systemAlertMarkRead($connect, $alertId, $userId)
    {
        $tblName = systemAlertGetTableName();
        $alertId = (int) $alertId;
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $alertId <= 0 || $userId <= 0) {
            return false;
        }

        $sql = "UPDATE `" . $tblName . "`
                SET `is_read` = 'Y',
                    `read_date` = CURDATE(),
                    `read_time` = CURTIME()
                WHERE `id` = " . $alertId . "
                  AND `target_user_id` = " . $userId . "
                  AND `status` = 'A'
                LIMIT 1";
        return mysqli_query($connect, $sql) ? true : false;
    }
}

if (!function_exists('systemAlertMarkAllRead')) {
    function systemAlertMarkAllRead($connect, $userId)
    {
        $tblName = systemAlertGetTableName();
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return false;
        }

        $sql = "UPDATE `" . $tblName . "`
                SET `is_read` = 'Y',
                    `read_date` = CURDATE(),
                    `read_time` = CURTIME()
                WHERE `target_user_id` = " . $userId . "
                  AND `is_read` = 'N'
                  AND `status` = 'A'";
        return mysqli_query($connect, $sql) ? true : false;
    }
}

if (!function_exists('systemAlertBuildFollowUpActionUrl')) {
    function systemAlertBuildFollowUpActionUrl($followUpId, $roundId = 0, $notificationType = '')
    {
        $params = array(
            'follow_up_id' => (int) $followUpId,
        );

        if ((int) $roundId > 0) {
            $params['round_id'] = (int) $roundId;
        }

        $notificationType = trim((string) $notificationType);
        if ($notificationType !== '') {
            $params['notification_type'] = $notificationType;
        }

        return systemAlertBuildActionUrl('customer_follow_up', $params);
    }
}

if (!function_exists('systemAlertCreateFromFollowUpNotification')) {
    function systemAlertCreateFromFollowUpNotification($connect, $notificationRow, $followUpRow = array())
    {
        if (!($connect instanceof mysqli) || !is_array($notificationRow) || empty($notificationRow)) {
            return 0;
        }

        $notificationId = isset($notificationRow['id']) ? (int) $notificationRow['id'] : 0;
        $notifyUserId = isset($notificationRow['notify_user_id']) ? (int) $notificationRow['notify_user_id'] : 0;
        if ($notificationId <= 0 || $notifyUserId <= 0) {
            return 0;
        }

        $followUpId = isset($notificationRow['follow_up_id']) ? (int) $notificationRow['follow_up_id'] : 0;
        if ($followUpId > 0 && empty($followUpRow) && defined('CUSTOMER_FOLLOW_UP')) {
            $result = getData('*', "id = '" . $followUpId . "' AND status = 'A'", 'LIMIT 1', CUSTOMER_FOLLOW_UP, $connect);
            if ($result && $result->num_rows > 0) {
                $followUpRow = $result->fetch_assoc();
            }
        }

        return systemAlertCreateOnce($connect, array(
            'module_key' => 'customer_follow_up',
            'notification_type' => trim((string) (isset($notificationRow['notification_type']) ? $notificationRow['notification_type'] : 'follow_up_notice')),
            'target_user_id' => $notifyUserId,
            'target_user_group_id' => systemAlertGetUserGroupId($connect, $notifyUserId),
            'title' => isset($notificationRow['title']) ? $notificationRow['title'] : 'Customer Follow-Up',
            'message' => isset($notificationRow['message']) ? $notificationRow['message'] : '',
            'action_url' => systemAlertBuildFollowUpActionUrl(
                $followUpId,
                isset($notificationRow['round_id']) ? (int) $notificationRow['round_id'] : 0,
                isset($notificationRow['notification_type']) ? $notificationRow['notification_type'] : ''
            ),
            'action_label' => 'Open Follow-Up',
            'related_table' => defined('CUSTOMER_FOLLOW_UP_NOTIFICATION') ? CUSTOMER_FOLLOW_UP_NOTIFICATION : 'customer_follow_up_notification',
            'related_id' => $notificationId,
            'related_platform' => isset($followUpRow['platform']) ? $followUpRow['platform'] : '',
            'display_date' => isset($notificationRow['create_date']) ? $notificationRow['create_date'] : systemAlertNowDate(),
            'create_by' => isset($notificationRow['notify_user_id']) ? $notificationRow['notify_user_id'] : 'SYSTEM',
            'create_date' => isset($notificationRow['create_date']) ? $notificationRow['create_date'] : systemAlertNowDate(),
            'create_time' => isset($notificationRow['create_time']) ? $notificationRow['create_time'] : systemAlertNowTime(),
        ));
    }
}

if (!function_exists('systemAlertSyncFollowUpNotificationsForUser')) {
    function systemAlertSyncFollowUpNotificationsForUser($connect, $userId, $limit = 50)
    {
        $userId = systemAlertNormalizeUserId($userId);
        $limit = max(1, (int) $limit);
        if (!($connect instanceof mysqli) || $userId <= 0 || !defined('CUSTOMER_FOLLOW_UP_NOTIFICATION')) {
            return 0;
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP_NOTIFICATION . "`
                WHERE `notify_user_id` = " . $userId . "
                  AND `status` = 'A'
                ORDER BY `id` DESC
                LIMIT " . $limit;
        $result = mysqli_query($connect, $sql);
        $count = 0;
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (systemAlertCreateFromFollowUpNotification($connect, $row) > 0) {
                    $count++;
                }
            }
        }

        return $count;
    }
}

if (!function_exists('systemAlertCountOrdersByStatuses')) {
    function systemAlertCountOrdersByStatuses($dbConnect, $tblName, $statuses)
    {
        if (!($dbConnect instanceof mysqli) || trim((string) $tblName) === '' || empty($statuses)) {
            return 0;
        }

        $statusConditions = array();
        foreach ((array) $statuses as $status) {
            $status = trim((string) $status);
            if ($status === '') {
                continue;
            }
            $statusConditions[] = "`order_status` = '" . systemAlertEscape($dbConnect, $status) . "'";
        }

        if (empty($statusConditions)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS `total`
                FROM `" . $tblName . "`
                WHERE `status` = 'A'
                  AND (" . implode(' OR ', $statusConditions) . ")";
        $result = mysqli_query($dbConnect, $sql);
        if ($result && $result->num_rows > 0) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['total']) ? (int) $row['total'] : 0;
        }

        return 0;
    }
}

if (!function_exists('systemAlertCountWaitingToPack')) {
    function systemAlertCountWaitingToPack($connect, $financeConnect)
    {
        $sourceConfig = function_exists('shopeeOmsGetOrderSourceConfig') ? shopeeOmsGetOrderSourceConfig('shopee') : array();
        $orderConnect = function_exists('shopeeOmsGetOrderSourceDbConnection')
            ? shopeeOmsGetOrderSourceDbConnection($connect, $financeConnect, $sourceConfig)
            : $financeConnect;
        return systemAlertCountOrdersByStatuses($orderConnect, isset($sourceConfig['table']) ? $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ, array('TP'));
    }
}

if (!function_exists('systemAlertCountArrivalManagementOrders')) {
    function systemAlertCountArrivalManagementOrders($connect, $financeConnect)
    {
        $count = 0;
        if (!function_exists('shopeeOmsGetOrderSourceConfigs') || !function_exists('shopeeOmsGetOrderSourceDbConnection')) {
            return $count;
        }

        foreach (shopeeOmsGetOrderSourceConfigs() as $sourceConfig) {
            $orderConnect = shopeeOmsGetOrderSourceDbConnection($connect, $financeConnect, $sourceConfig);
            $count += systemAlertCountOrdersByStatuses($orderConnect, isset($sourceConfig['table']) ? $sourceConfig['table'] : '', array('WAERD', 'WR', 'PD'));
        }

        return $count;
    }
}

if (!function_exists('systemAlertCountWaitingAdminFinalCheckOrders')) {
    function systemAlertCountWaitingAdminFinalCheckOrders($connect, $financeConnect)
    {
        $sourceConfig = function_exists('shopeeOmsGetOrderSourceConfig') ? shopeeOmsGetOrderSourceConfig('shopee') : array();
        $orderConnect = function_exists('shopeeOmsGetOrderSourceDbConnection')
            ? shopeeOmsGetOrderSourceDbConnection($connect, $financeConnect, $sourceConfig)
            : $financeConnect;
        return systemAlertCountOrdersByStatuses($orderConnect, isset($sourceConfig['table']) ? $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ, array('WAFC'));
    }
}

if (!function_exists('systemAlertGenerateModuleAlert')) {
    function systemAlertGenerateModuleAlert($connect, $userId, $moduleKey, $title, $message, $displayDate = '')
    {
        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return 0;
        }

        $displayDate = trim((string) $displayDate) !== '' ? trim((string) $displayDate) : systemAlertNowDate();
        $configs = systemAlertGetModuleConfigs();
        $config = isset($configs[$moduleKey]) ? $configs[$moduleKey] : array();

        return systemAlertCreateOnce($connect, array(
            'module_key' => $moduleKey,
            'notification_type' => $moduleKey . '_daily_notice',
            'target_user_id' => $userId,
            'target_user_group_id' => systemAlertGetUserGroupId($connect, $userId),
            'title' => $title,
            'message' => $message,
            'action_url' => systemAlertBuildActionUrl($moduleKey),
            'action_label' => isset($config['action_label']) ? $config['action_label'] : 'Open Page',
            'related_table' => 'module_notice',
            'related_id' => 0,
            'display_date' => $displayDate,
            'create_by' => 'SYSTEM',
            'create_date' => $displayDate,
            'create_time' => systemAlertNowTime(),
        ));
    }
}

if (!function_exists('systemAlertGenerateDailyFlowSupervisorAlerts')) {
    function systemAlertGenerateDailyFlowSupervisorAlerts($connect, $financeConnect, $displayDate = '')
    {
        $displayDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $displayDate))
            ? trim((string) $displayDate)
            : systemAlertNowDate();
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return 0;
        }

        $activeUserRows = systemAlertLoadActiveUserRows($connect);
        if (empty($activeUserRows)) {
            return 0;
        }

        $activeUserMap = array();
        foreach ($activeUserRows as $userRow) {
            $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
            if ($userId > 0) {
                $activeUserMap[$userId] = $userRow;
            }
        }

        $createdCount = 0;
        foreach ($activeUserRows as $userRow) {
            $activityUserId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
            if ($activityUserId <= 0) {
                continue;
            }

            $supervisorUserIds = systemAlertResolveUserSupervisorIds($connect, $userRow);
            if (empty($supervisorUserIds)) {
                continue;
            }

            $reportData = shopeeOmsGetDailyFlowReport(
                $connect,
                $financeConnect,
                '',
                '',
                '',
                '',
                '',
                0,
                '',
                $displayDate,
                '',
                '',
                $activityUserId
            );
            $summaryRows = isset($reportData['summary']) && is_array($reportData['summary']) ? $reportData['summary'] : array();
            if (empty($summaryRows)) {
                continue;
            }

            $activityUserName = trim((string) (isset($userRow['name']) ? $userRow['name'] : ''));
            if ($activityUserName === '') {
                $activityUserName = commonResolveUserDisplayName($connect, (string) $activityUserId);
            }

            $message = systemAlertBuildDailyFlowSummaryMessage($activityUserName, $summaryRows);
            if ($message === '') {
                continue;
            }

            foreach ($supervisorUserIds as $supervisorUserId) {
                $supervisorUserId = (int) $supervisorUserId;
                if ($supervisorUserId <= 0 || !isset($activeUserMap[$supervisorUserId])) {
                    continue;
                }
                if (!systemAlertUserHasAccessToModule($connect, $supervisorUserId, 'daily_flow_report')) {
                    continue;
                }

                if (systemAlertCreateOnce($connect, array(
                    'module_key' => 'daily_flow_report',
                    'notification_type' => 'user_daily_activity_summary',
                    'target_user_id' => $supervisorUserId,
                    'target_user_group_id' => systemAlertGetUserGroupId($connect, $supervisorUserId),
                    'title' => 'Daily Flow Report - ' . ($activityUserName !== '' ? $activityUserName : ('User #' . $activityUserId)),
                    'message' => $message,
                    'action_url' => systemAlertBuildActionUrl('daily_flow_report', array(
                        'date' => $displayDate,
                        'user_id' => $activityUserId,
                    )),
                    'action_label' => 'Open Report',
                    'related_table' => USR_USER,
                    'related_id' => $activityUserId,
                    'display_date' => $displayDate,
                    'create_by' => 'SYSTEM',
                    'create_date' => $displayDate,
                    'create_time' => systemAlertNowTime(),
                )) > 0) {
                    $createdCount++;
                }
            }
        }

        return $createdCount;
    }
}

if (!function_exists('systemAlertGenerateForUser')) {
    function systemAlertGenerateForUser($connect, $financeConnect, $userId)
    {
        static $generatedCache = array();

        $userId = systemAlertNormalizeUserId($userId);
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return 0;
        }

        $cacheKey = $userId . '|' . systemAlertNowDate();
        if (isset($generatedCache[$cacheKey])) {
            return $generatedCache[$cacheKey];
        }

        $createdCount = 0;

        if (systemAlertUserHasAccessToModule($connect, $userId, 'shopee_waiting_to_pack')) {
            $waitingToPackCount = systemAlertCountWaitingToPack($connect, $financeConnect);
            if ($waitingToPackCount > 0 && systemAlertGenerateModuleAlert($connect, $userId, 'shopee_waiting_to_pack', 'Shopee Waiting To Pack', 'There are ' . $waitingToPackCount . ' order(s) waiting to be packed.') > 0) {
                $createdCount++;
            }
        }

        if (systemAlertUserHasAccessToModule($connect, $userId, 'shopee_arrival_management')) {
            $arrivalCount = systemAlertCountArrivalManagementOrders($connect, $financeConnect);
            if ($arrivalCount > 0 && systemAlertGenerateModuleAlert($connect, $userId, 'shopee_arrival_management', 'Shopee Arrival Management', 'There are ' . $arrivalCount . ' order(s) waiting for arrival update.') > 0) {
                $createdCount++;
            }
        }

        if (systemAlertUserHasAccessToModule($connect, $userId, 'waiting_admin_final_check')) {
            $finalCheckCount = systemAlertCountWaitingAdminFinalCheckOrders($connect, $financeConnect);
            if ($finalCheckCount > 0 && systemAlertGenerateModuleAlert($connect, $userId, 'waiting_admin_final_check', 'Waiting Admin Final Check', 'There are ' . $finalCheckCount . ' order(s) waiting for final check.') > 0) {
                $createdCount++;
            }
        }

        $createdCount += systemAlertSyncFollowUpNotificationsForUser($connect, $userId);
        $generatedCache[$cacheKey] = $createdCount;
        return $createdCount;
    }
}

if (!function_exists('systemAlertBuildCampaignFollowUpActionUrl')) {
    function systemAlertBuildCampaignFollowUpActionUrl($campaignId, $followUpId = 0)
    {
        $params = array(
            'campaign_id' => (int) $campaignId,
        );

        if ((int) $followUpId > 0) {
            $params['follow_up_id'] = (int) $followUpId;
        }

        return systemAlertBuildActionUrl('campaign_follow_up_task', $params);
    }
}

if (!function_exists('systemAlertCreateCampaignFollowUpAlert')) {
    function systemAlertCreateCampaignFollowUpAlert($connect, $taskRow)
    {
        if (!($connect instanceof mysqli) || !is_array($taskRow) || empty($taskRow)) {
            return 0;
        }

        $taskId = isset($taskRow['id']) ? (int) $taskRow['id'] : 0;
        $targetUserId = isset($taskRow['pic_user_id']) ? (int) $taskRow['pic_user_id'] : 0;
        $campaignId = isset($taskRow['campaign_id']) ? (int) $taskRow['campaign_id'] : 0;
        $campaignMessageId = isset($taskRow['campaign_message_id']) ? (int) $taskRow['campaign_message_id'] : 0;
        if ($taskId <= 0 || $targetUserId <= 0 || $campaignId <= 0 || $campaignMessageId <= 0) {
            return 0;
        }

        $campaignName = trim((string) ($taskRow['campaign_name'] ?? 'Campaign'));
        $messageTitle = trim((string) ($taskRow['message_title'] ?? 'Follow-Up'));
        $followUpDate = trim((string) ($taskRow['follow_up_date'] ?? systemAlertNowDate()));
        $dueCustomerTotal = isset($taskRow['due_customer_total']) ? (int) $taskRow['due_customer_total'] : 0;
        if ($dueCustomerTotal <= 0) {
            $dueCustomerTotal = 1;
        }

        $messageParts = array();
        $messageParts[] = $campaignName . ': ' . $messageTitle;
        $messageParts[] = $dueCustomerTotal . ' follow-up customer' . ($dueCustomerTotal === 1 ? '' : 's') . ' due';
        if ($followUpDate !== '') {
            $messageParts[] = 'Follow-up date ' . $followUpDate;
        }

        return systemAlertCreateOnce($connect, array(
            'module_key' => 'campaign_follow_up_task',
            'notification_type' => 'campaign_follow_up_due',
            'target_user_id' => $targetUserId,
            'target_user_group_id' => systemAlertGetUserGroupId($connect, $targetUserId),
            'title' => 'Campaign Follow-Up Task',
            'message' => implode('. ', array_filter($messageParts, 'strlen')) . '.',
            'action_url' => systemAlertBuildCampaignFollowUpActionUrl($campaignId),
            'action_label' => 'Open Follow-Up',
            'related_table' => defined('CAMPAIGN_FOLLOW_UP') ? CAMPAIGN_FOLLOW_UP : 'campaign_follow_up',
            'related_id' => $campaignMessageId,
            'related_platform' => '',
            'display_date' => $followUpDate !== '' ? $followUpDate : systemAlertNowDate(),
            'create_by' => 'SYSTEM',
            'create_date' => systemAlertNowDate(),
            'create_time' => systemAlertNowTime(),
        ));
    }
}

if (!function_exists('systemAlertGenerateCampaignFollowUpAlerts')) {
    function systemAlertGenerateCampaignFollowUpAlerts($connect, $displayDate = '')
    {
        $displayDate = trim((string) $displayDate) !== '' ? trim((string) $displayDate) : systemAlertNowDate();
        if (!($connect instanceof mysqli) || !defined('CAMPAIGN_FOLLOW_UP') || !defined('CAMPAIGN') || !defined('CAMPAIGN_CUSTOMER') || !defined('CAMPAIGN_MESSAGE')) {
            return 0;
        }

        $requiredTables = array(CAMPAIGN_FOLLOW_UP, CAMPAIGN, CAMPAIGN_CUSTOMER, CAMPAIGN_MESSAGE);
        foreach ($requiredTables as $tblName) {
            if (!tableExists($tblName, $connect)) {
                return 0;
            }
        }

        $sql = "SELECT cf.`id`, cf.`campaign_id`, cf.`campaign_message_id`, cf.`pic_user_id`, cf.`follow_up_date`, cf.`follow_up_status`,
                       c.`campaign_name`,
                       cc.`customer_name`, cc.`platform`,
                       cm.`message_title`
                FROM `" . CAMPAIGN_FOLLOW_UP . "` cf
                INNER JOIN `" . CAMPAIGN . "` c ON c.`id` = cf.`campaign_id` AND c.`status` = 'A'
                INNER JOIN `" . CAMPAIGN_CUSTOMER . "` cc ON cc.`id` = cf.`campaign_customer_id` AND cc.`status` = 'A'
                INNER JOIN `" . CAMPAIGN_MESSAGE . "` cm ON cm.`id` = cf.`campaign_message_id` AND cm.`status` = 'A'
                WHERE cf.`status` = 'A'
                  AND IFNULL(cf.`follow_up_status`, '') <> 'Completed'
                  AND IFNULL(cf.`pic_user_id`, 0) > 0
                  AND IFNULL(cf.`notification_sent`, 'N') = 'N'
                  AND cf.`follow_up_date` <= '" . systemAlertEscape($connect, $displayDate) . "'
                ORDER BY cf.`follow_up_date` ASC, cf.`id` ASC
                LIMIT 500";

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return 0;
        }

        $groupedRows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $groupKey = (int) ($row['campaign_id'] ?? 0)
                . '|' . (int) ($row['campaign_message_id'] ?? 0)
                . '|' . (int) ($row['pic_user_id'] ?? 0)
                . '|' . trim((string) ($row['follow_up_date'] ?? ''));
            if (!isset($groupedRows[$groupKey])) {
                $row['task_ids'] = array();
                $row['due_customer_total'] = 0;
                $groupedRows[$groupKey] = $row;
            }

            $taskId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($taskId > 0) {
                $groupedRows[$groupKey]['task_ids'][] = $taskId;
            }
            $groupedRows[$groupKey]['due_customer_total']++;
        }

        $createdCount = 0;
        foreach ($groupedRows as $row) {
            $targetUserId = isset($row['pic_user_id']) ? (int) $row['pic_user_id'] : 0;
            if ($targetUserId <= 0) {
                continue;
            }

            $alertId = systemAlertCreateCampaignFollowUpAlert($connect, $row);
            if ($alertId > 0) {
                $taskIds = isset($row['task_ids']) && is_array($row['task_ids']) ? array_values(array_filter(array_map('intval', $row['task_ids']))) : array();
                if (!empty($taskIds)) {
                    mysqli_query($connect, "UPDATE `" . CAMPAIGN_FOLLOW_UP . "` SET `notification_sent`='Y', `notification_sent_date`=CURDATE(), `notification_sent_time`=CURTIME() WHERE `id` IN (" . implode(',', $taskIds) . ")");
                }
                $createdCount++;
            }
        }

        return $createdCount;
    }
}
