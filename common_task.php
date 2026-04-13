<?php

if (!function_exists('taskEsc')) {
    function taskEsc($connect, $value)
    {
        return mysqli_real_escape_string($connect, (string) $value);
    }
}

if (!function_exists('taskJsonResponse')) {
    function taskJsonNormalizeUtf8($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = taskJsonNormalizeUtf8($item);
            }
            return $value;
        }

        if (is_object($value)) {
            foreach ($value as $key => $item) {
                $value->{$key} = taskJsonNormalizeUtf8($item);
            }
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('//u', $value)) {
            return $value;
        }

        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($normalized !== false) {
                return $normalized;
            }
        }

        return '';
    }

    function taskJsonEncodePayload($payload)
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json = json_encode($payload, $flags);
        if ($json !== false) {
            return $json;
        }

        $sanitizedPayload = taskJsonNormalizeUtf8($payload);
        $json = json_encode($sanitizedPayload, $flags);
        if ($json !== false) {
            return $json;
        }

        return json_encode(
            array(
                'ok' => 0,
                'message' => 'Failed to encode server response.',
            ),
            $flags
        );
    }

    function taskJsonResponse($payload)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/json');
        echo taskJsonEncodePayload($payload);
        exit;
    }
}

if (!function_exists('taskNormalizeHistoryValue')) {
    function taskNormalizeHistoryValue($value)
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        return $text;
    }
}

if (!function_exists('taskFormatWorklogDuration')) {
    function taskFormatWorklogDuration($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $days = (int) floor($seconds / 86400);
        $seconds = $seconds % 86400;
        $hours = (int) floor($seconds / 3600);
        $seconds = $seconds % 3600;
        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = (int) ($seconds % 60);

        $parts = array();
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }
        if ($remainingSeconds > 0) {
            $parts[] = $remainingSeconds . 's';
        }

        if (empty($parts)) {
            return '0s';
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('taskParseWorklogDurationSeconds')) {
    function taskParseWorklogDurationSeconds($value)
    {
        $text = strtolower(trim((string) $value));
        if ($text === '' || $text === 'no time logged') {
            return 0;
        }

        if (!preg_match_all('/(\d+)\s*([dhms])/', $text, $matches, PREG_SET_ORDER)) {
            return 0;
        }

        $totalSeconds = 0;
        foreach ($matches as $match) {
            $amount = isset($match[1]) ? (int) $match[1] : 0;
            $unit = isset($match[2]) ? (string) $match[2] : '';
            if ($amount <= 0 || $unit === '') {
                continue;
            }

            if ($unit === 'd') {
                $totalSeconds += $amount * 86400;
            } elseif ($unit === 'h') {
                $totalSeconds += $amount * 3600;
            } elseif ($unit === 'm') {
                $totalSeconds += $amount * 60;
            } elseif ($unit === 's') {
                $totalSeconds += $amount;
            }
        }

        return $totalSeconds;
    }
}

if (!function_exists('taskLogItemHistory')) {
    function taskLogItemHistory($connect, $itemId, $eventType, $fieldName, $fromValue, $toValue, $remark, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0 || !defined('TASK_ITEM_HISTORY')) {
            return false;
        }

        $eventType = trim((string) $eventType);
        if ($eventType === '') {
            $eventType = 'update';
        }

        $safeItemId = (int) $itemId;
        $safeEventType = taskEsc($connect, substr($eventType, 0, 80));
        $safeFieldName = taskEsc($connect, substr(trim((string) $fieldName), 0, 120));
        $safeFromValue = taskEsc($connect, substr(taskNormalizeHistoryValue($fromValue), 0, 65535));
        $safeToValue = taskEsc($connect, substr(taskNormalizeHistoryValue($toValue), 0, 65535));
        $safeRemark = taskEsc($connect, substr(trim((string) $remark), 0, 65535));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $sql = "INSERT INTO " . TASK_ITEM_HISTORY . "
                (item_id,event_type,field_name,from_value,to_value,remark,create_by,create_date,create_time,status)
                VALUES
                ('" . $safeItemId . "','" . $safeEventType . "','" . $safeFieldName . "','" . $safeFromValue . "','" . $safeToValue . "','" . $safeRemark . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        return mysqli_query($connect, $sql) ? true : false;
    }
}

if (!function_exists('taskGetItemHistory')) {
    function taskGetItemHistory($connect, $itemId, $limit = 150)
    {
        $itemId = (int) $itemId;
        $limit = (int) $limit;
        if ($itemId <= 0 || !defined('TASK_ITEM_HISTORY')) {
            return array();
        }

        if ($limit <= 0) {
            $limit = 150;
        }

        $rows = array();
        $sql = "SELECT h.id,h.event_type,h.field_name,h.from_value,h.to_value,h.remark,h.create_by,h.create_date,h.create_time,
                       COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(u.username), ''), h.create_by, 'User') AS actor_name
                FROM " . TASK_ITEM_HISTORY . " h
                LEFT JOIN " . USR_USER . " u ON u.id = h.create_by
                WHERE h.item_id='" . $itemId . "' AND h.status='A'
                ORDER BY h.id DESC
                LIMIT " . $limit;

        $rst = mysqli_query($connect, $sql);
        if (!$rst) {
            return $rows;
        }

        while ($row = $rst->fetch_assoc()) {
            $eventType = isset($row['event_type']) ? trim((string) $row['event_type']) : '';
            $fieldName = isset($row['field_name']) ? trim((string) $row['field_name']) : '';
            $remark = isset($row['remark']) ? trim((string) $row['remark']) : '';
            $toValue = isset($row['to_value']) ? trim((string) $row['to_value']) : '';

            if ($remark === '') {
                if ($eventType === 'create_item') {
                    $remark = 'created the Work item';
                } elseif ($eventType === 'change_status') {
                    $remark = 'changed the Status';
                } elseif ($eventType === 'worklog_saved') {
                    $remark = $toValue !== '' ? ('logged ' . $toValue) : 'logged work time';
                } elseif ($eventType === 'delete_item') {
                    $remark = 'deleted the Work item';
                } elseif ($fieldName !== '') {
                    $remark = 'changed ' . $fieldName;
                } else {
                    $remark = 'updated the Work item';
                }
            }

            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'event_type' => $eventType,
                'field_name' => $fieldName,
                'from_value' => isset($row['from_value']) ? (string) $row['from_value'] : '',
                'to_value' => isset($row['to_value']) ? (string) $row['to_value'] : '',
                'remark' => $remark,
                'actor_name' => isset($row['actor_name']) ? (string) $row['actor_name'] : 'User',
                'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
                'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
                'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            );
        }

        return $rows;
    }
}

if (!function_exists('taskParsePinBlock')) {
    function taskParsePinBlock($pinsText, $pinGroupId)
    {
        $pinGroupId = (string) ((int) $pinGroupId);
        $entries = array_filter(array_map('trim', explode('+', (string) $pinsText)), 'strlen');

        foreach ($entries as $entry) {
            $entry = trim($entry, '[]');
            $parts = explode(':', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }

            if (trim((string) $parts[0]) !== $pinGroupId) {
                continue;
            }

            $actions = array();
            foreach (explode(',', (string) $parts[1]) as $actionId) {
                $actionId = trim((string) $actionId);
                if ($actionId !== '' && ctype_digit($actionId)) {
                    $actions[] = (int) $actionId;
                }
            }

            return array_values(array_unique($actions));
        }

        return array();
    }
}

if (!function_exists('taskGetActionMap')) {
    function taskGetActionMap($connect)
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = array();
        $rst = getData('id,name', '', '', PIN, $connect);
        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $actionId = isset($row['id']) ? (int) $row['id'] : 0;
                $actionName = isset($row['name']) ? strtolower(trim((string) $row['name'])) : '';
                if ($actionId > 0 && $actionName !== '') {
                    $cache[$actionId] = $actionName;
                }
            }
        }

        return $cache;
    }
}

if (!function_exists('taskGetNumericAccessForPinGroup')) {
    function taskGetNumericAccessForPinGroup($connect, $pinGroupId)
    {
        $pinGroupId = (int) $pinGroupId;
        if ($pinGroupId <= 0) {
            return array();
        }

        if (isset($_SESSION['usr_pin_access'][$pinGroupId]) && is_array($_SESSION['usr_pin_access'][$pinGroupId])) {
            $sessionActions = array_map('intval', $_SESSION['usr_pin_access'][$pinGroupId]);
            return array_values(array_unique($sessionActions));
        }

        $userId = defined('USER_ID') ? (int) USER_ID : 0;
        if ($userId <= 0) {
            $userId = isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : 0;
        }
        if ($userId <= 0) {
            return array();
        }

        $userRst = getData('access_id', "id='" . $userId . "'", 'LIMIT 1', USR_USER, $connect);
        if (!$userRst || $userRst->num_rows === 0) {
            return array();
        }

        $userRow = $userRst->fetch_assoc();
        $userGroupId = isset($userRow['access_id']) ? (int) $userRow['access_id'] : 0;
        if ($userGroupId <= 0) {
            return array();
        }

        $groupRst = getData('pins', "id='" . $userGroupId . "'", 'LIMIT 1', USR_GRP, $connect);
        if (!$groupRst || $groupRst->num_rows === 0) {
            return array();
        }

        $groupRow = $groupRst->fetch_assoc();
        $userActions = taskParsePinBlock(isset($groupRow['pins']) ? $groupRow['pins'] : '', $pinGroupId);

        $pinGroupRst = getData('pins', "id='" . $pinGroupId . "'", 'LIMIT 1', PIN_GRP, $connect);
        if (!$pinGroupRst || $pinGroupRst->num_rows === 0) {
            return $userActions;
        }

        $pinGroupRow = $pinGroupRst->fetch_assoc();
        $groupActions = array();
        foreach (explode(',', (string) $pinGroupRow['pins']) as $actionId) {
            $actionId = trim((string) $actionId);
            if ($actionId !== '' && ctype_digit($actionId)) {
                $groupActions[] = (int) $actionId;
            }
        }

        return array_values(array_unique(array_intersect($userActions, $groupActions)));
    }
}

if (!function_exists('taskGetPinAccessByGroupId')) {
    function taskGetPinAccessByGroupId($connect, $pinGroupId)
    {
        $allowedActionIds = taskGetNumericAccessForPinGroup($connect, $pinGroupId);
        if (empty($allowedActionIds)) {
            return array();
        }

        $actionMap = taskGetActionMap($connect);
        $allowedActions = array();

        foreach ($allowedActionIds as $actionId) {
            if (isset($actionMap[$actionId])) {
                $allowedActions[] = $actionMap[$actionId];
            }
        }

        return array_values(array_unique($allowedActions));
    }
}

if (!function_exists('taskIsActionAllowed')) {
    function taskIsActionAllowed($actionName, $pinAccess)
    {
        if (!is_array($pinAccess)) {
            return false;
        }

        $actionName = strtolower(trim((string) $actionName));
        $normalized = array_map(function ($item) {
            return strtolower(trim((string) $item));
        }, $pinAccess);

        return in_array($actionName, $normalized, true);
    }
}

if (!function_exists('taskDefaultWorkTypeSvgIcon')) {
    function taskDefaultWorkTypeSvgIcon($name = '')
    {
        $key = strtolower(trim((string) $name));
        if ($key === 'epic') {
            return 'svg_icon/10307.svg';
        }

        return 'svg_icon/10318.svg';
    }
}

if (!function_exists('taskGetSvgIconOptions')) {
    function taskGetSvgIconOptions()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = array();
        $iconDir = __DIR__ . '/task/svg_icon';
        if (!is_dir($iconDir)) {
            return $cache;
        }

        $files = glob($iconDir . '/*.svg');
        if (!is_array($files)) {
            return $cache;
        }

        foreach ($files as $filePath) {
            $base = basename((string) $filePath);
            if ($base === '' || strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== 'svg') {
                continue;
            }

            $cache[] = 'svg_icon/' . $base;
        }

        natcasesort($cache);
        $cache = array_values($cache);
        return $cache;
    }
}

if (!function_exists('taskNormalizeWorkTypeSvgIcon')) {
    function taskNormalizeWorkTypeSvgIcon($iconPath, $name = '')
    {
        $iconPath = str_replace('\\', '/', trim((string) $iconPath));
        $fallback = taskDefaultWorkTypeSvgIcon($name);
        $allowed = taskGetSvgIconOptions();

        if ($iconPath === '') {
            if (in_array($fallback, $allowed, true)) {
                return $fallback;
            }

            return !empty($allowed) ? (string) $allowed[0] : $fallback;
        }

        if (strpos($iconPath, 'svg_icon/') !== 0) {
            $iconPath = 'svg_icon/' . basename($iconPath);
        }

        if (in_array($iconPath, $allowed, true)) {
            return $iconPath;
        }

        if (in_array($fallback, $allowed, true)) {
            return $fallback;
        }

        return !empty($allowed) ? (string) $allowed[0] : $fallback;
    }
}

if (!function_exists('taskEnsureDefaultWorkTypes')) {
    function taskEnsureDefaultWorkTypes($connect, $currentUserId, $cdate, $ctime)
    {
        $defaults = array(
            array('name' => 'Task', 'svg_icon' => taskDefaultWorkTypeSvgIcon('Task'), 'remark' => 'Default task work type'),
            array('name' => 'Epic', 'svg_icon' => taskDefaultWorkTypeSvgIcon('Epic'), 'remark' => 'Default epic work type'),
        );

        foreach ($defaults as $default) {
            $safeName = taskEsc($connect, $default['name']);
            $rst = getData('id,svg_icon', "LOWER(name)=LOWER('" . $safeName . "')", 'LIMIT 1', TASK_WORK_TYPE, $connect);
            if ($rst && $rst->num_rows > 0) {
                $row = $rst->fetch_assoc();
                $existingId = isset($row['id']) ? (int) $row['id'] : 0;
                $existingIcon = isset($row['svg_icon']) ? trim((string) $row['svg_icon']) : '';
                if ($existingId > 0 && $existingIcon === '') {
                    $safeIcon = taskEsc($connect, taskNormalizeWorkTypeSvgIcon($default['svg_icon'], $default['name']));
                    mysqli_query($connect, "UPDATE " . TASK_WORK_TYPE . " SET svg_icon='" . $safeIcon . "' WHERE id='" . $existingId . "' LIMIT 1");
                }
                continue;
            }

            $safeIcon = taskEsc($connect, taskNormalizeWorkTypeSvgIcon($default['svg_icon'], $default['name']));
            $safeRemark = taskEsc($connect, $default['remark']);
            $safeUser = taskEsc($connect, $currentUserId);
            $safeDate = taskEsc($connect, $cdate);
            $safeTime = taskEsc($connect, $ctime);

            mysqli_query(
                $connect,
                "INSERT INTO " . TASK_WORK_TYPE . " (name,svg_icon,remark,create_by,create_date,create_time,status) VALUES ('" . $safeName . "','" . $safeIcon . "','" . $safeRemark . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
            );
        }
    }
}

if (!function_exists('taskGetWorkTypes')) {
    function taskGetWorkTypes($connect)
    {
        $rows = array();
        $sql = "SELECT id,name,remark,svg_icon FROM " . TASK_WORK_TYPE . " WHERE status='A' ORDER BY id ASC";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            $sql = "SELECT id,name,remark,'' AS svg_icon FROM " . TASK_WORK_TYPE . " WHERE status='A' ORDER BY id ASC";
            $rst = mysqli_query($connect, $sql);
        }

        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'remark' => isset($row['remark']) ? (string) $row['remark'] : '',
                    'svg_icon' => taskNormalizeWorkTypeSvgIcon(isset($row['svg_icon']) ? $row['svg_icon'] : '', isset($row['name']) ? (string) $row['name'] : ''),
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskNormalizeProjectKey')) {
    function taskNormalizeProjectKey($projectKey)
    {
        $value = strtoupper(trim((string) $projectKey));
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^A-Z0-9\-]/', '', $value);
        return substr((string) $value, 0, 20);
    }
}

if (!function_exists('taskBuildWorkItemKey')) {
    function taskBuildWorkItemKey($projectKey, $itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return '';
        }

        $normalizedKey = taskNormalizeProjectKey($projectKey);
        if ($normalizedKey === '') {
            return '';
        }

        return $normalizedKey . '-' . $itemId;
    }
}

if (!function_exists('taskGetProjectKeySetting')) {
    function taskGetProjectKeySetting($connect)
    {
        $row = array(
            'id' => 0,
            'project_key' => '',
        );

        $sql = "SELECT id,project_key FROM " . TASK_PROJECT_KEY . " WHERE status='A' ORDER BY id DESC LIMIT 1";
        $rst = mysqli_query($connect, $sql);
        if ($rst && $rst->num_rows > 0) {
            $data = $rst->fetch_assoc();
            $row['id'] = isset($data['id']) ? (int) $data['id'] : 0;
            $row['project_key'] = taskNormalizeProjectKey(isset($data['project_key']) ? $data['project_key'] : '');
        }

        return $row;
    }
}

if (!function_exists('taskSaveProjectKeySetting')) {
    function taskSaveProjectKeySetting($connect, $projectKey, $currentUserId, $cdate, $ctime)
    {
        $normalizedKey = taskNormalizeProjectKey($projectKey);
        $safeKey = taskEsc($connect, $normalizedKey);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $existsSql = "SELECT id FROM " . TASK_PROJECT_KEY . " WHERE status='A' ORDER BY id DESC LIMIT 1";
        $existsRst = mysqli_query($connect, $existsSql);
        if ($existsRst === false) {
            return array('ok' => 0, 'message' => 'Failed to update project key. Please run insert_table.php first.');
        }

        if ($existsRst->num_rows > 0) {
            $exists = $existsRst->fetch_assoc();
            $settingId = isset($exists['id']) ? (int) $exists['id'] : 0;
            $updateSql = "UPDATE " . TASK_PROJECT_KEY . " SET
                            project_key='" . $safeKey . "',
                            update_by='" . $safeUser . "',
                            update_date='" . $safeDate . "',
                            update_time='" . $safeTime . "'
                          WHERE id='" . $settingId . "' AND status='A'";
            if (!mysqli_query($connect, $updateSql)) {
                return array('ok' => 0, 'message' => 'Failed to save project key.');
            }

            return array(
                'ok' => 1,
                'message' => 'Project key saved successfully.',
                'projectKey' => array(
                    'id' => $settingId,
                    'project_key' => $normalizedKey,
                ),
            );
        }

        $insertSql = "INSERT INTO " . TASK_PROJECT_KEY . " (project_key,create_by,create_date,create_time,status)
                      VALUES ('" . $safeKey . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to save project key.');
        }

        return array(
            'ok' => 1,
            'message' => 'Project key saved successfully.',
            'projectKey' => array(
                'id' => (int) mysqli_insert_id($connect),
                'project_key' => $normalizedKey,
            ),
        );
    }
}

if (!function_exists('taskGetAssignees')) {
    function taskGetAssignees($connect)
    {
        $rows = array();
        $sql = "SELECT id, COALESCE(NULLIF(TRIM(name), ''), username) AS display_name, email FROM " . USR_USER . " WHERE status='A' ORDER BY display_name ASC";
        $rst = mysqli_query($connect, $sql);

        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['display_name'],
                    'email' => isset($row['email']) ? (string) $row['email'] : '',
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskGetLabels')) {
    function taskGetLabels($connect)
    {
        $rows = array();
        $sql = "SELECT id,name FROM " . TASK_LABEL . " WHERE status='A' ORDER BY sort_order ASC, name ASC";
        $rst = mysqli_query($connect, $sql);

        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskGetStatusLabels')) {
    function taskGetStatusLabels($connect)
    {
        $rows = array();
        $sql = "SELECT id,name FROM " . TASK_STATUS_LABEL . " WHERE status='A' ORDER BY sort_order ASC, name ASC";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            return $rows;
        }

        while ($row = $rst->fetch_assoc()) {
            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
            );
        }

        return $rows;
    }
}

if (!function_exists('taskParseCsvIdList')) {
    function taskParseCsvIdList($rawValue)
    {
        $value = trim((string) $rawValue);
        if ($value === '') {
            return array();
        }

        $parts = preg_split('/\s*,\s*/', $value);
        $ids = array();
        foreach ((array) $parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        return $ids;
    }
}

if (!function_exists('taskResolveStatusLabelSelection')) {
    function taskResolveStatusLabelSelection($connect, $rawValue)
    {
        $ids = taskParseCsvIdList($rawValue);
        if (empty($ids)) {
            return array(
                'ids' => array(),
                'csv' => '',
                'labels' => array(),
            );
        }

        $idSql = implode(',', $ids);
        $map = array();
        $rst = mysqli_query(
            $connect,
            "SELECT id,name FROM " . TASK_STATUS_LABEL . " WHERE status='A' AND id IN (" . $idSql . ")"
        );
        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($id > 0 && $name !== '') {
                    $map[$id] = $name;
                }
            }
        }

        $resolvedIds = array();
        $labels = array();
        foreach ($ids as $id) {
            if (!isset($map[$id])) {
                continue;
            }
            $resolvedIds[] = $id;
            $labels[] = array(
                'id' => $id,
                'name' => (string) $map[$id],
            );
        }

        return array(
            'ids' => $resolvedIds,
            'csv' => implode(',', $resolvedIds),
            'labels' => $labels,
        );
    }
}

if (!function_exists('taskIsDoneColumnName')) {
    function taskIsDoneColumnName($columnName)
    {
        $name = strtolower(trim((string) $columnName));
        if ($name === '') {
            return false;
        }

        return (bool) preg_match('/\b(done|closed|complete|completed|resolved)\b/i', $name);
    }
}

if (!function_exists('taskGetEpicChildWorkItemsSummary')) {
    function taskGetEpicChildWorkItemsSummary($connect, $epicItemId)
    {
        $epicItemId = (int) $epicItemId;
        $summary = array(
            'items' => array(),
            'total' => 0,
            'done' => 0,
            'progress_percent' => 0,
            'time_tracking' => 'No time logged',
            'time_tracking_seconds' => 0,
        );
        if ($epicItemId <= 0) {
            return $summary;
        }

        $projectKeySetting = taskGetProjectKeySetting($connect);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $lastColumnSortOrder = 0;

        $lastColumnRst = mysqli_query($connect, "SELECT MAX(sort_order) AS max_sort_order FROM " . TASK_COLUMN . " WHERE status='A'");
        if ($lastColumnRst && $lastColumnRst->num_rows > 0) {
            $lastColumnRow = $lastColumnRst->fetch_assoc();
            $lastColumnSortOrder = isset($lastColumnRow['max_sort_order']) ? (int) $lastColumnRow['max_sort_order'] : 0;
        }

        $sql = "SELECT c.id, c.title, c.priority, c.assignee_user_id,
                       c.sort_order,
                       c.time_tracking,
                       col.name AS column_name,
                       col.sort_order AS column_sort_order,
                       pk.project_key AS item_project_key,
                       COALESCE(NULLIF(TRIM(u.name), ''), u.username, '') AS assignee_name
                FROM " . TASK_ITEM . " c
                LEFT JOIN " . TASK_COLUMN . " col ON col.id = c.column_id AND col.status='A'
                LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = c.project_key_id AND pk.status='A'
                LEFT JOIN " . USR_USER . " u ON u.id = c.assignee_user_id AND u.status='A'
                WHERE c.status='A' AND (
                    c.parent_item_id='" . $epicItemId . "'
                    OR c.id IN (
                        SELECT r.child_board_item_id
                        FROM " . TASK_ITEM_RELATION . " r
                        WHERE r.parent_board_item_id='" . $epicItemId . "' AND r.status='A'
                    )
                )
                ORDER BY c.sort_order ASC, c.id ASC";

        $rst = mysqli_query($connect, $sql);
        if (!$rst) {
            return $summary;
        }

        while ($row = $rst->fetch_assoc()) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($itemId <= 0) {
                continue;
            }

            $projectKey = isset($row['item_project_key']) ? taskNormalizeProjectKey($row['item_project_key']) : '';
            if ($projectKey === '') {
                $projectKey = taskNormalizeProjectKey($defaultProjectKey);
            }

            $statusName = isset($row['column_name']) ? trim((string) $row['column_name']) : '';
            $columnSortOrder = isset($row['column_sort_order']) ? (int) $row['column_sort_order'] : 0;
            $isDone = taskIsDoneColumnName($statusName)
                || ($lastColumnSortOrder > 0 && $columnSortOrder >= $lastColumnSortOrder);
            if ($isDone) {
                $summary['done']++;
            }

            $timeTracking = isset($row['time_tracking']) ? trim((string) $row['time_tracking']) : '';
            $timeTrackingSeconds = taskParseWorklogDurationSeconds($timeTracking);
            $summary['time_tracking_seconds'] += $timeTrackingSeconds;

            $summary['items'][] = array(
                'id' => $itemId,
                'work_item_key' => taskBuildWorkItemKey($projectKey, $itemId),
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'priority' => taskNormalizePriority(isset($row['priority']) ? $row['priority'] : 'Medium'),
                'assignee_user_id' => isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0,
                'assignee_name' => isset($row['assignee_name']) ? (string) $row['assignee_name'] : '',
                'status_name' => $statusName,
                'is_done' => $isDone ? 1 : 0,
                'time_tracking' => $timeTracking !== '' ? $timeTracking : 'No time logged',
            );
        }

        $summary['total'] = count($summary['items']);
        if ($summary['total'] > 0) {
            $summary['progress_percent'] = (int) round(($summary['done'] * 100) / $summary['total']);
        }
        if ((int) $summary['time_tracking_seconds'] > 0) {
            $summary['time_tracking'] = taskFormatWorklogDuration((int) $summary['time_tracking_seconds']);
        }

        return $summary;
    }
}

if (!function_exists('taskCreateStatusLabel')) {
    function taskCreateStatusLabel($connect, $labelName, $currentUserId, $cdate, $ctime)
    {
        $labelName = trim((string) $labelName);
        if ($labelName === '') {
            return array('ok' => 0, 'message' => 'Task status name is required.');
        }

        $safeName = taskEsc($connect, substr($labelName, 0, 120));
        $existingRst = mysqli_query($connect, "SELECT id,status FROM " . TASK_STATUS_LABEL . " WHERE LOWER(name)=LOWER('" . $safeName . "') LIMIT 1");
        if ($existingRst === false) {
            return array('ok' => 0, 'message' => 'Failed to create task status label. Please run insert_table.php first.');
        }

        if ($existingRst->num_rows > 0) {
            $existing = $existingRst->fetch_assoc();
            $labelId = isset($existing['id']) ? (int) $existing['id'] : 0;
            if ((string) $existing['status'] !== 'A') {
                $safeUser = taskEsc($connect, $currentUserId);
                $safeDate = taskEsc($connect, $cdate);
                $safeTime = taskEsc($connect, $ctime);
                mysqli_query($connect, "UPDATE " . TASK_STATUS_LABEL . " SET status='A', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $labelId . "'");
            }

            return array('ok' => 1, 'message' => 'Task status ready.', 'statusLabel' => array('id' => $labelId, 'name' => $labelName));
        }

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_STATUS_LABEL . " WHERE status='A'");
        $sortOrder = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $sortOrder = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_STATUS_LABEL . " (name,sort_order,create_by,create_date,create_time,status)
                      VALUES ('" . $safeName . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create task status label.');
        }

        return array(
            'ok' => 1,
            'message' => 'Task status label created successfully.',
            'statusLabel' => array(
                'id' => (int) mysqli_insert_id($connect),
                'name' => $labelName,
            ),
        );
    }
}

if (!function_exists('taskNormalizePriority')) {
    function taskNormalizePriority($priority)
    {
        $allowed = array('Highest', 'High', 'Medium', 'Low', 'Lowest');
        $priority = trim((string) $priority);
        foreach ($allowed as $item) {
            if (strtolower($item) === strtolower($priority)) {
                return $item;
            }
        }

        return 'Medium';
    }
}

if (!function_exists('taskNormalizeEstimateUnit')) {
    function taskNormalizeEstimateUnit($unit)
    {
        $map = array(
            'minute' => 'minutes',
            'minutes' => 'minutes',
            'hour' => 'hours',
            'hours' => 'hours',
            'day' => 'days',
            'days' => 'days',
            'week' => 'weeks',
            'weeks' => 'weeks',
        );

        $key = strtolower(trim((string) $unit));
        return isset($map[$key]) ? $map[$key] : 'minutes';
    }
}

if (!function_exists('taskParseOriginalEstimate')) {
    function taskParseOriginalEstimate($estimate)
    {
        $estimate = trim((string) $estimate);
        if ($estimate === '') {
            return array('value' => 0, 'unit' => 'minutes');
        }

        if (preg_match('/^(\d+)\s*([A-Za-z]+)$/', $estimate, $matches)) {
            return array(
                'value' => (int) $matches[1],
                'unit' => taskNormalizeEstimateUnit($matches[2]),
            );
        }

        return array('value' => 0, 'unit' => 'minutes');
    }
}

if (!function_exists('taskMinutesToSqlTime')) {
    function taskMinutesToSqlTime($minutes)
    {
        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return null;
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d:00', $hours, $mins);
    }
}

if (!function_exists('taskSqlTimeToMinutes')) {
    function taskSqlTimeToMinutes($time)
    {
        $value = trim((string) $time);
        if ($value === '' || $value === '00:00:00') {
            return 0;
        }

        $parts = explode(':', $value);
        if (count($parts) < 2) {
            return 0;
        }

        $hours = (int) $parts[0];
        $mins = (int) $parts[1];
        return max(0, ($hours * 60) + $mins);
    }
}

if (!function_exists('taskNormalizeUrl')) {
    function taskNormalizeUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        return substr($url, 0, 500);
    }
}

if (!function_exists('taskGetItemUrls')) {
    function taskGetItemUrls($connect, $itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array();
        }

        $rows = array();
        $sql = "SELECT id,url,link_text,title,create_date,create_time
                FROM " . TASK_ITEM_URL . "
                WHERE item_id='" . $itemId . "' AND status='A'
                ORDER BY id DESC";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            $sql = "SELECT id,url,title AS link_text,title,create_date,create_time
                    FROM " . TASK_ITEM_URL . "
                    WHERE item_id='" . $itemId . "' AND status='A'
                    ORDER BY id DESC";
            $rst = mysqli_query($connect, $sql);
        }

        if (!$rst) {
            return array();
        }

        while ($row = $rst->fetch_assoc()) {
            $url = isset($row['url']) ? (string) $row['url'] : '';
            $linkText = isset($row['link_text']) ? trim((string) $row['link_text']) : '';
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
            if ($linkText === '') {
                $linkText = $title;
            }
            if ($linkText === '') {
                $linkText = $url;
            }

            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'url' => $url,
                'link_text' => $linkText,
                'title' => $title,
                'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
                'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            );
        }

        return $rows;
    }
}

if (!function_exists('taskCreateItemUrl')) {
    function taskCreateItemUrl($connect, $itemId, $url, $linkText, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $url = taskNormalizeUrl($url);
        $linkText = trim((string) $linkText);

        if ($itemId <= 0 || $url === '') {
            return array('ok' => 0, 'message' => 'Invalid web link request.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array('ok' => 0, 'message' => 'Please enter a valid URL.');
        }

        $itemRst = mysqli_query(
            $connect,
            "SELECT id,assignee_user_id,reporter_user_id,priority,original_estimate,task_status,start_date,due_date,amendement_date,amendement_time,second_amendement_date,second_amendement_time
             FROM " . TASK_ITEM . "
             WHERE id='" . $itemId . "' AND status='A' LIMIT 1"
        );
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $existingRow = $itemRst->fetch_assoc();

        $safeUrl = taskEsc($connect, $url);
        $safeLinkText = taskEsc($connect, substr($linkText, 0, 255));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_ITEM_URL . "
                        (item_id,url,link_text,title,create_by,create_date,create_time,status)
                      VALUES
                        ('" . $itemId . "','" . $safeUrl . "','" . $safeLinkText . "','" . $safeLinkText . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            $fallbackSql = "INSERT INTO " . TASK_ITEM_URL . "
                            (item_id,url,title,create_by,create_date,create_time,status)
                          VALUES
                            ('" . $itemId . "','" . $safeUrl . "','" . $safeLinkText . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
            if (!mysqli_query($connect, $fallbackSql)) {
                return array('ok' => 0, 'message' => 'Failed to add web link. Please run insert_table.php first.');
            }
        }

        $linkDisplay = $linkText !== '' ? $linkText . ' (' . $url . ')' : $url;
        taskLogItemHistory(
            $connect,
            $itemId,
            'add_web_link',
            'Web Link',
            '',
            $linkDisplay,
            'added web link',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array('ok' => 1, 'message' => 'Web link added successfully.');
    }
}

if (!function_exists('taskDeleteItemUrl')) {
    function taskDeleteItemUrl($connect, $urlId, $currentUserId, $cdate, $ctime)
    {
        $urlId = (int) $urlId;
        if ($urlId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid web link delete request.');
        }

        $rst = mysqli_query($connect, "SELECT id,item_id,url,link_text,title FROM " . TASK_ITEM_URL . " WHERE id='" . $urlId . "' AND status='A' LIMIT 1");
        if ($rst === false) {
            return array('ok' => 0, 'message' => 'Failed deleting web link. Please run insert_table.php first.');
        }

        if ($rst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Web link not found.');
        }

        $row = $rst->fetch_assoc();
        $itemId = isset($row['item_id']) ? (int) $row['item_id'] : 0;
        $url = isset($row['url']) ? trim((string) $row['url']) : '';
        $linkText = isset($row['link_text']) ? trim((string) $row['link_text']) : '';
        if ($linkText === '') {
            $linkText = isset($row['title']) ? trim((string) $row['title']) : '';
        }
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $ok = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_URL . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $urlId . "' AND status='A'"
        );

        if (!$ok) {
            return array('ok' => 0, 'message' => 'Failed deleting web link.');
        }

        $linkDisplay = $linkText !== '' ? $linkText . ($url !== '' ? ' (' . $url . ')' : '') : $url;
        taskLogItemHistory(
            $connect,
            $itemId,
            'delete_web_link',
            'Web Link',
            $linkDisplay,
            '',
            'removed web link',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array('ok' => 1, 'message' => 'Web link removed successfully.', 'item_id' => $itemId);
    }
}

if (!function_exists('taskGetEpicParentOptions')) {
    function taskGetEpicParentOptions($connect, $excludeChildItemId = 0)
    {
        $excludeChildItemId = (int) $excludeChildItemId;
        $options = array();
        $projectKeySetting = taskGetProjectKeySetting($connect);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

        $sql = "SELECT i.id, i.title, i.project_key_id, pk.project_key AS item_project_key
                FROM " . TASK_ITEM . " i
                INNER JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = i.project_key_id AND pk.status='A'
                WHERE i.status='A' AND LOWER(wt.name)='epic'";
        if ($excludeChildItemId > 0) {
            $sql .= " AND i.id <> '" . $excludeChildItemId . "'";
        }
        $sql .= " ORDER BY i.id DESC";

        $rst = mysqli_query($connect, $sql);
        if (!$rst) {
            return $options;
        }

        while ($row = $rst->fetch_assoc()) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            $itemKey = isset($row['item_project_key']) ? taskNormalizeProjectKey($row['item_project_key']) : '';
            if ($itemKey === '') {
                $itemKey = taskNormalizeProjectKey($defaultProjectKey);
            }

            $options[] = array(
                'id' => $itemId,
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'work_item_key' => taskBuildWorkItemKey($itemKey, $itemId),
            );
        }

        return $options;
    }
}

if (!function_exists('taskGetParentRelationInfo')) {
    function taskGetParentRelationInfo($connect, $childItemId)
    {
        $childItemId = (int) $childItemId;
        $info = array(
            'parent_item_id' => 0,
            'parent_display' => 'None',
            'parent_work_item_key' => '',
            'parent_work_type_name' => 'Task',
            'parent_work_type_svg_icon' => '',
        );

        if ($childItemId <= 0) {
            return $info;
        }

        $projectKeySetting = taskGetProjectKeySetting($connect);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

        $sql = "SELECT r.parent_board_item_id,
                       p.title AS parent_title,
                  pk.project_key AS parent_project_key,
                  COALESCE(NULLIF(TRIM(wt.name), ''), 'Task') AS parent_work_type_name,
                  wt.svg_icon AS parent_work_type_svg_icon
                FROM " . TASK_ITEM_RELATION . " r
                INNER JOIN " . TASK_ITEM . " p ON p.id = r.parent_board_item_id AND p.status='A'
                LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = p.project_key_id AND pk.status='A'
              LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = p.work_type_id AND wt.status='A'
                WHERE r.child_board_item_id='" . $childItemId . "' AND r.status='A'
                LIMIT 1";
        $rst = mysqli_query($connect, $sql);
        if ($rst && $rst->num_rows > 0) {
            $row = $rst->fetch_assoc();
            $parentItemId = isset($row['parent_board_item_id']) ? (int) $row['parent_board_item_id'] : 0;
            if ($parentItemId > 0) {
                $parentProjectKey = isset($row['parent_project_key']) ? taskNormalizeProjectKey($row['parent_project_key']) : '';
                if ($parentProjectKey === '') {
                    $parentProjectKey = taskNormalizeProjectKey($defaultProjectKey);
                }

                $parentKey = taskBuildWorkItemKey($parentProjectKey, $parentItemId);
                $parentTitle = isset($row['parent_title']) ? trim((string) $row['parent_title']) : '';
                $parentTypeName = isset($row['parent_work_type_name']) ? (string) $row['parent_work_type_name'] : 'Task';
                $info['parent_item_id'] = $parentItemId;
                $info['parent_work_item_key'] = $parentKey;
                $info['parent_work_type_name'] = $parentTypeName;
                $info['parent_work_type_svg_icon'] = taskNormalizeWorkTypeSvgIcon(isset($row['parent_work_type_svg_icon']) ? $row['parent_work_type_svg_icon'] : '', $parentTypeName);
                $info['parent_display'] = trim(($parentKey !== '' ? $parentKey . ' ' : '') . $parentTitle);
                if ($info['parent_display'] === '') {
                    $info['parent_display'] = 'None';
                }
                return $info;
            }
        }

        $fallbackSql = "SELECT i.parent_item_id,
                               p.title AS parent_title,
                       pk.project_key AS parent_project_key,
                       COALESCE(NULLIF(TRIM(wt.name), ''), 'Task') AS parent_work_type_name,
                       wt.svg_icon AS parent_work_type_svg_icon
                        FROM " . TASK_ITEM . " i
                        LEFT JOIN " . TASK_ITEM . " p ON p.id = i.parent_item_id AND p.status='A'
                        LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = p.project_key_id AND pk.status='A'
                   LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = p.work_type_id AND wt.status='A'
                        WHERE i.id='" . $childItemId . "' AND i.status='A' LIMIT 1";
        $fallbackRst = mysqli_query($connect, $fallbackSql);
        if ($fallbackRst && $fallbackRst->num_rows > 0) {
            $row = $fallbackRst->fetch_assoc();
            $parentItemId = isset($row['parent_item_id']) ? (int) $row['parent_item_id'] : 0;
            if ($parentItemId > 0) {
                $parentProjectKey = isset($row['parent_project_key']) ? taskNormalizeProjectKey($row['parent_project_key']) : '';
                if ($parentProjectKey === '') {
                    $parentProjectKey = taskNormalizeProjectKey($defaultProjectKey);
                }

                $parentKey = taskBuildWorkItemKey($parentProjectKey, $parentItemId);
                $parentTitle = isset($row['parent_title']) ? trim((string) $row['parent_title']) : '';
                $parentTypeName = isset($row['parent_work_type_name']) ? (string) $row['parent_work_type_name'] : 'Task';
                $info['parent_item_id'] = $parentItemId;
                $info['parent_work_item_key'] = $parentKey;
                $info['parent_work_type_name'] = $parentTypeName;
                $info['parent_work_type_svg_icon'] = taskNormalizeWorkTypeSvgIcon(isset($row['parent_work_type_svg_icon']) ? $row['parent_work_type_svg_icon'] : '', $parentTypeName);
                $info['parent_display'] = trim(($parentKey !== '' ? $parentKey . ' ' : '') . $parentTitle);
                if ($info['parent_display'] === '') {
                    $info['parent_display'] = 'None';
                }
            }
        }

        return $info;
    }
}

if (!function_exists('taskGetParentMapByChildIds')) {
    function taskGetParentMapByChildIds($connect, $childIds)
    {
        $map = array();
        $childIds = array_values(array_unique(array_map('intval', (array) $childIds)));
        $childIds = array_filter($childIds, function ($id) {
            return $id > 0;
        });

        if (empty($childIds)) {
            return $map;
        }

        $idSql = implode(',', $childIds);
        $sql = "SELECT child_board_item_id, parent_board_item_id
                FROM " . TASK_ITEM_RELATION . "
                WHERE status='A' AND child_board_item_id IN (" . $idSql . ")";
        $rst = mysqli_query($connect, $sql);
        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $childId = isset($row['child_board_item_id']) ? (int) $row['child_board_item_id'] : 0;
                $parentId = isset($row['parent_board_item_id']) ? (int) $row['parent_board_item_id'] : 0;
                if ($childId > 0) {
                    $map[$childId] = $parentId;
                }
            }
            return $map;
        }

        $fallbackSql = "SELECT id,parent_item_id FROM " . TASK_ITEM . " WHERE status='A' AND id IN (" . $idSql . ")";
        $fallbackRst = mysqli_query($connect, $fallbackSql);
        if ($fallbackRst) {
            while ($row = $fallbackRst->fetch_assoc()) {
                $childId = isset($row['id']) ? (int) $row['id'] : 0;
                $parentId = isset($row['parent_item_id']) ? (int) $row['parent_item_id'] : 0;
                if ($childId > 0) {
                    $map[$childId] = $parentId;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('taskSetItemParentRelation')) {
    function taskSetItemParentRelation($connect, $childItemId, $parentItemId, $currentUserId, $cdate, $ctime)
    {
        $childItemId = (int) $childItemId;
        $parentItemId = (int) $parentItemId;
        if ($childItemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid parent link request.');
        }

        $childSql = "SELECT i.id, wt.name AS work_type_name
                     FROM " . TASK_ITEM . " i
                     LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                     WHERE i.id='" . $childItemId . "' AND i.status='A' LIMIT 1";
        $childRst = mysqli_query($connect, $childSql);
        if (!$childRst || $childRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $childRow = $childRst->fetch_assoc();
        $childType = isset($childRow['work_type_name']) ? strtolower(trim((string) $childRow['work_type_name'])) : '';
        if ($childType === 'epic') {
            return array('ok' => 0, 'message' => 'Epic work item cannot be linked as child.');
        }

        $previousParentInfo = taskGetParentRelationInfo($connect, $childItemId);

        if ($parentItemId > 0) {
            if ($parentItemId === $childItemId) {
                return array('ok' => 0, 'message' => 'A work item cannot link itself as parent.');
            }

            $parentSql = "SELECT i.id, wt.name AS work_type_name
                          FROM " . TASK_ITEM . " i
                          LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                          WHERE i.id='" . $parentItemId . "' AND i.status='A' LIMIT 1";
            $parentRst = mysqli_query($connect, $parentSql);
            if (!$parentRst || $parentRst->num_rows === 0) {
                return array('ok' => 0, 'message' => 'Selected parent work item not found.');
            }

            $parentRow = $parentRst->fetch_assoc();
            $parentType = isset($parentRow['work_type_name']) ? strtolower(trim((string) $parentRow['work_type_name'])) : '';
            if ($parentType !== 'epic') {
                return array('ok' => 0, 'message' => 'Parent must be an Epic work item.');
            }
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);

        $okItem = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                parent_item_id='" . $parentItemId . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $childItemId . "' AND status='A'"
        );

        if (!$okItem) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed updating parent link.');
        }

        $okDeactivate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_RELATION . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE child_board_item_id='" . $childItemId . "' AND status='A'"
        );

        if ($okDeactivate === false) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed updating parent relation. Please run insert_table.php first.');
        }

        if ($parentItemId > 0) {
            $checkRst = mysqli_query(
                $connect,
                "SELECT id FROM " . TASK_ITEM_RELATION . "
                 WHERE child_board_item_id='" . $childItemId . "'
                 ORDER BY (status='A') DESC, id DESC
                 LIMIT 1"
            );

            if ($checkRst === false) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed updating parent relation. Please run insert_table.php first.');
            }

            if ($checkRst && $checkRst->num_rows > 0) {
                $checkRow = $checkRst->fetch_assoc();
                $relationId = isset($checkRow['id']) ? (int) $checkRow['id'] : 0;
                $okUpsert = mysqli_query(
                    $connect,
                    "UPDATE " . TASK_ITEM_RELATION . " SET
                        parent_board_item_id='" . $parentItemId . "',
                        child_board_item_id='" . $childItemId . "',
                        status='A',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                     WHERE id='" . $relationId . "'"
                );
            } else {
                $okUpsert = mysqli_query(
                    $connect,
                    "INSERT INTO " . TASK_ITEM_RELATION . "
                     (parent_board_item_id,child_board_item_id,create_by,create_date,create_time,status)
                     VALUES
                     ('" . $parentItemId . "','" . $childItemId . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
                );
            }

            if (!$okUpsert) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed updating parent relation.');
            }
        }

        mysqli_commit($connect);

        $parentInfo = taskGetParentRelationInfo($connect, $childItemId);

        $previousParentDisplay = isset($previousParentInfo['parent_display']) ? (string) $previousParentInfo['parent_display'] : 'None';
        $newParentDisplay = isset($parentInfo['parent_display']) ? (string) $parentInfo['parent_display'] : 'None';
        if ($previousParentDisplay !== $newParentDisplay) {
            taskLogItemHistory(
                $connect,
                $childItemId,
                'update_field',
                'Parent',
                $previousParentDisplay,
                $newParentDisplay,
                'changed Parent',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        return array(
            'ok' => 1,
            'message' => 'Parent linked successfully.',
            'parent_item_id' => isset($parentInfo['parent_item_id']) ? (int) $parentInfo['parent_item_id'] : 0,
            'parent_display' => isset($parentInfo['parent_display']) ? (string) $parentInfo['parent_display'] : 'None',
        );
    }
}

if (!function_exists('taskGetItemDetail')) {
    if (!function_exists('taskBuildItemTimeTrackingDetail')) {
        function taskBuildItemTimeTrackingDetail($ownTimeTracking, $childWorkItems)
        {
            $ownTimeTracking = trim((string) $ownTimeTracking);
            $ownSeconds = taskParseWorklogDurationSeconds($ownTimeTracking);
            $childSeconds = is_array($childWorkItems) && isset($childWorkItems['time_tracking_seconds'])
                ? (int) $childWorkItems['time_tracking_seconds']
                : 0;
            $canIncludeChild = is_array($childWorkItems) && isset($childWorkItems['total']) && (int) $childWorkItems['total'] > 0;
            $combinedSeconds = $ownSeconds + $childSeconds;

            return array(
                'time_tracking' => $canIncludeChild
                    ? ($combinedSeconds > 0 ? taskFormatWorklogDuration($combinedSeconds) : 'No time logged')
                    : ($ownSeconds > 0 ? taskFormatWorklogDuration($ownSeconds) : 'No time logged'),
                'own_time_tracking' => $ownSeconds > 0 ? taskFormatWorklogDuration($ownSeconds) : 'No time logged',
                'own_time_tracking_seconds' => $ownSeconds,
                'child_time_tracking' => $childSeconds > 0 ? taskFormatWorklogDuration($childSeconds) : 'No time logged',
                'child_time_tracking_seconds' => $childSeconds,
                'combined_time_tracking' => $combinedSeconds > 0 ? taskFormatWorklogDuration($combinedSeconds) : 'No time logged',
                'combined_time_tracking_seconds' => $combinedSeconds,
                'can_include_child_time_tracking' => $canIncludeChild ? 1 : 0,
                'include_child_time_tracking' => $canIncludeChild ? 1 : 0,
            );
        }
    }

    function taskGetItemDetail($connect, $itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid work item request.');
        }

        $sql = "SELECT i.id, i.title, i.description, i.assignee_user_id, i.reporter_user_id,
                       i.priority, i.original_estimate, i.task_status, i.parent_item_id, i.time_tracking,
                       i.due_date, i.start_date, i.amendement_date, i.amendement_time, i.second_amendement_date, i.second_amendement_time,
                       i.create_date, i.update_date,
                       COALESCE(NULLIF(TRIM(wt.name), ''), 'Task') AS work_type_name,
                       wt.svg_icon AS work_type_svg_icon,
                       pk.project_key AS item_project_key,
                       COALESCE(NULLIF(TRIM(ua.name), ''), ua.username, '') AS assignee_name,
                       COALESCE(NULLIF(TRIM(ur.name), ''), ur.username, '') AS reporter_name
                FROM " . TASK_ITEM . " i
                LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = i.project_key_id AND pk.status='A'
                LEFT JOIN " . USR_USER . " ua ON ua.id = i.assignee_user_id AND ua.status='A'
                LEFT JOIN " . USR_USER . " ur ON ur.id = i.reporter_user_id AND ur.status='A'
                WHERE i.id='" . $itemId . "' AND i.status='A' LIMIT 1";

        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
                 $sql = "SELECT i.id, i.title, '' AS description, i.assignee_user_id, 0 AS reporter_user_id,
                           'Medium' AS priority, '' AS original_estimate, '' AS task_status, 0 AS parent_item_id, '' AS time_tracking,
                           i.due_date, i.due_date AS start_date, NULL AS amendement_date, NULL AS amendement_time, NULL AS second_amendement_date, NULL AS second_amendement_time,
                           '' AS create_date, '' AS update_date,
                          'Task' AS work_type_name,
                          '' AS work_type_svg_icon,
                          '' AS item_project_key,
                           COALESCE(NULLIF(TRIM(ua.name), ''), ua.username, '') AS assignee_name,
                           '' AS reporter_name
                    FROM " . TASK_ITEM . " i
                    LEFT JOIN " . USR_USER . " ua ON ua.id = i.assignee_user_id AND ua.status='A'
                    WHERE i.id='" . $itemId . "' AND i.status='A' LIMIT 1";
            $rst = mysqli_query($connect, $sql);
        }

        if (!$rst || $rst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $row = $rst->fetch_assoc();
        $estimate = taskParseOriginalEstimate(isset($row['original_estimate']) ? $row['original_estimate'] : '');
        $labelsMap = taskGetItemLabelsByItemIds($connect, array($itemId));
        $labels = isset($labelsMap[$itemId]) ? $labelsMap[$itemId] : array();
        $parentInfo = taskGetParentRelationInfo($connect, $itemId);
        $parentItemId = isset($parentInfo['parent_item_id']) ? (int) $parentInfo['parent_item_id'] : 0;
        $statusSelection = taskResolveStatusLabelSelection(
            $connect,
            isset($row['task_status']) && $row['task_status'] !== null ? (string) $row['task_status'] : ''
        );
        $workTypeName = isset($row['work_type_name']) ? (string) $row['work_type_name'] : 'Task';
        $workTypeIcon = taskNormalizeWorkTypeSvgIcon(isset($row['work_type_svg_icon']) ? $row['work_type_svg_icon'] : '', $workTypeName);
        $projectKeySetting = taskGetProjectKeySetting($connect);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $itemProjectKey = isset($row['item_project_key']) ? taskNormalizeProjectKey($row['item_project_key']) : '';
        if ($itemProjectKey === '') {
            $itemProjectKey = taskNormalizeProjectKey($defaultProjectKey);
        }
        $workItemKey = taskBuildWorkItemKey($itemProjectKey, $itemId);
        $isEpic = strtolower(trim($workTypeName)) === 'epic';
        $childWorkItems = $isEpic ? taskGetEpicChildWorkItemsSummary($connect, $itemId) : array(
            'items' => array(),
            'total' => 0,
            'done' => 0,
            'progress_percent' => 0,
        );

        $timeTrackingDetail = taskBuildItemTimeTrackingDetail(
            isset($row['time_tracking']) ? $row['time_tracking'] : '',
            $childWorkItems
        );

        $detail = array(
            'id' => $itemId,
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'description' => isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : '',
            'assignee_user_id' => isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0,
            'assignee_name' => isset($row['assignee_name']) ? (string) $row['assignee_name'] : '',
            'reporter_user_id' => isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0,
            'reporter_name' => isset($row['reporter_name']) ? (string) $row['reporter_name'] : '',
            'work_type_name' => $workTypeName,
            'work_type_svg_icon' => $workTypeIcon,
            'work_item_key' => $workItemKey,
            'priority' => taskNormalizePriority(isset($row['priority']) ? $row['priority'] : 'Medium'),
            'original_estimate_value' => $estimate['value'],
            'original_estimate_unit' => $estimate['unit'],
            'task_status' => isset($statusSelection['csv']) ? (string) $statusSelection['csv'] : '',
            'task_status_label_ids' => isset($statusSelection['ids']) ? $statusSelection['ids'] : array(),
            'task_status_labels' => isset($statusSelection['labels']) ? $statusSelection['labels'] : array(),
            'parent_item_id' => $parentItemId,
            'parent_display' => isset($parentInfo['parent_display']) ? (string) $parentInfo['parent_display'] : 'None',
            'parent_work_item_key' => isset($parentInfo['parent_work_item_key']) ? (string) $parentInfo['parent_work_item_key'] : '',
            'parent_work_type_name' => isset($parentInfo['parent_work_type_name']) ? (string) $parentInfo['parent_work_type_name'] : 'Task',
            'parent_work_type_svg_icon' => isset($parentInfo['parent_work_type_svg_icon']) ? (string) $parentInfo['parent_work_type_svg_icon'] : '',
            'time_tracking' => isset($timeTrackingDetail['time_tracking']) ? (string) $timeTrackingDetail['time_tracking'] : 'No time logged',
            'own_time_tracking' => isset($timeTrackingDetail['own_time_tracking']) ? (string) $timeTrackingDetail['own_time_tracking'] : 'No time logged',
            'own_time_tracking_seconds' => isset($timeTrackingDetail['own_time_tracking_seconds']) ? (int) $timeTrackingDetail['own_time_tracking_seconds'] : 0,
            'child_time_tracking' => isset($timeTrackingDetail['child_time_tracking']) ? (string) $timeTrackingDetail['child_time_tracking'] : 'No time logged',
            'child_time_tracking_seconds' => isset($timeTrackingDetail['child_time_tracking_seconds']) ? (int) $timeTrackingDetail['child_time_tracking_seconds'] : 0,
            'combined_time_tracking' => isset($timeTrackingDetail['combined_time_tracking']) ? (string) $timeTrackingDetail['combined_time_tracking'] : 'No time logged',
            'combined_time_tracking_seconds' => isset($timeTrackingDetail['combined_time_tracking_seconds']) ? (int) $timeTrackingDetail['combined_time_tracking_seconds'] : 0,
            'can_include_child_time_tracking' => isset($timeTrackingDetail['can_include_child_time_tracking']) ? (int) $timeTrackingDetail['can_include_child_time_tracking'] : 0,
            'include_child_time_tracking' => isset($timeTrackingDetail['include_child_time_tracking']) ? (int) $timeTrackingDetail['include_child_time_tracking'] : 0,
            'due_date' => isset($row['due_date']) && $row['due_date'] !== null ? (string) $row['due_date'] : '',
            'start_date' => isset($row['start_date']) && $row['start_date'] !== null ? (string) $row['start_date'] : '',
            'create_date' => isset($row['create_date']) && $row['create_date'] !== null ? (string) $row['create_date'] : '',
            'update_date' => isset($row['update_date']) && $row['update_date'] !== null ? (string) $row['update_date'] : '',
            'amendement_date' => isset($row['amendement_date']) && $row['amendement_date'] !== null ? (string) $row['amendement_date'] : '',
            'amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['amendement_time']) ? $row['amendement_time'] : ''),
            'second_amendement_date' => isset($row['second_amendement_date']) && $row['second_amendement_date'] !== null ? (string) $row['second_amendement_date'] : '',
            'second_amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['second_amendement_time']) ? $row['second_amendement_time'] : ''),
            'labels' => $labels,
            'child_work_items' => $childWorkItems,
        );

        if ($detail['start_date'] === '' && $detail['due_date'] !== '') {
            $detail['start_date'] = $detail['due_date'];
        }

        return array(
            'ok' => 1,
            'detail' => $detail,
            'statusLabels' => taskGetStatusLabels($connect),
            'parentOptions' => taskGetEpicParentOptions($connect, $itemId),
            'webLinks' => taskGetItemUrls($connect, $itemId),
        );
    }
}

if (!function_exists('taskUpdateItemDetail')) {
    function taskUpdateItemDetail($connect, $itemId, $assigneeUserId, $reporterUserId, $priority, $originalEstimateValue, $originalEstimateUnit, $taskStatusLabelIds, $startDate, $dueDate, $amendementDate, $amendementTimeMinutes, $secondAmendementDate, $secondAmendementTimeMinutes, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid work item update request.');
        }

        $itemRst = mysqli_query(
            $connect,
            "SELECT id,
                    assignee_user_id,
                    reporter_user_id,
                    priority,
                    original_estimate,
                    task_status,
                    start_date,
                    due_date,
                    amendement_date,
                    amendement_time,
                    second_amendement_date,
                    second_amendement_time
             FROM " . TASK_ITEM . "
             WHERE id='" . $itemId . "' AND status='A' LIMIT 1"
        );
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $existingRow = $itemRst->fetch_assoc();

        $assigneeUserId = (int) $assigneeUserId;
        if ($assigneeUserId > 0) {
            $assigneeRst = getData('id', "id='" . $assigneeUserId . "' AND status='A'", 'LIMIT 1', USR_USER, $connect);
            if (!$assigneeRst || $assigneeRst->num_rows === 0) {
                $assigneeUserId = 0;
            }
        }

        $reporterUserId = (int) $reporterUserId;
        if ($reporterUserId > 0) {
            $reporterRst = getData('id', "id='" . $reporterUserId . "' AND status='A'", 'LIMIT 1', USR_USER, $connect);
            if (!$reporterRst || $reporterRst->num_rows === 0) {
                $reporterUserId = 0;
            }
        }

        $priority = taskNormalizePriority($priority);

        $originalEstimateValue = (int) $originalEstimateValue;
        if ($originalEstimateValue < 0) {
            $originalEstimateValue = 0;
        }
        $estimateUnit = taskNormalizeEstimateUnit($originalEstimateUnit);
        $originalEstimate = $originalEstimateValue . ' ' . $estimateUnit;

        $statusSelection = taskResolveStatusLabelSelection($connect, $taskStatusLabelIds);
        $taskStatus = isset($statusSelection['csv']) ? (string) $statusSelection['csv'] : '';

        $safeDueDate = 'NULL';
        $safeStartDate = 'NULL';
        $safeAmendementDate = 'NULL';
        $safeSecondAmendementDate = 'NULL';

        $dueDate = trim((string) $dueDate);
        $startDate = trim((string) $startDate);
        $amendementDate = trim((string) $amendementDate);
        $secondAmendementDate = trim((string) $secondAmendementDate);

        if ($dueDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $safeDueDate = "'" . taskEsc($connect, $dueDate) . "'";
        }
        if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $safeStartDate = "'" . taskEsc($connect, $startDate) . "'";
        } elseif ($safeDueDate !== 'NULL') {
            $safeStartDate = $safeDueDate;
        }
        if ($amendementDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $amendementDate)) {
            $safeAmendementDate = "'" . taskEsc($connect, $amendementDate) . "'";
        }
        if ($secondAmendementDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $secondAmendementDate)) {
            $safeSecondAmendementDate = "'" . taskEsc($connect, $secondAmendementDate) . "'";
        }

        $allowedMinuteOptions = array(5, 10, 15, 20, 25, 30, 35, 40, 45);
        $amendementTimeMinutes = (int) $amendementTimeMinutes;
        $secondAmendementTimeMinutes = (int) $secondAmendementTimeMinutes;

        $amendementTimeSql = in_array($amendementTimeMinutes, $allowedMinuteOptions, true) ? "'" . taskEsc($connect, (string) taskMinutesToSqlTime($amendementTimeMinutes)) . "'" : 'NULL';
        $secondAmendementTimeSql = in_array($secondAmendementTimeMinutes, $allowedMinuteOptions, true) ? "'" . taskEsc($connect, (string) taskMinutesToSqlTime($secondAmendementTimeMinutes)) . "'" : 'NULL';

        $safePriority = taskEsc($connect, $priority);
        $safeEstimate = taskEsc($connect, $originalEstimate);
        $safeTaskStatus = taskEsc($connect, $taskStatus);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $updateSql = "UPDATE " . TASK_ITEM . " SET
                        assignee_user_id='" . $assigneeUserId . "',
                        reporter_user_id='" . $reporterUserId . "',
                        priority='" . $safePriority . "',
                        original_estimate='" . $safeEstimate . "',
                        task_status='" . $safeTaskStatus . "',
                        due_date=" . $safeDueDate . ",
                        start_date=" . $safeStartDate . ",
                        amendement_date=" . $safeAmendementDate . ",
                        amendement_time=" . $amendementTimeSql . ",
                        second_amendement_date=" . $safeSecondAmendementDate . ",
                        second_amendement_time=" . $secondAmendementTimeSql . ",
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                      WHERE id='" . $itemId . "' AND status='A'";

        if (!mysqli_query($connect, $updateSql)) {
            $fallbackSql = "UPDATE " . TASK_ITEM . " SET
                            assignee_user_id='" . $assigneeUserId . "',
                            due_date=" . $safeDueDate . ",
                            update_by='" . $safeUser . "',
                            update_date='" . $safeDate . "',
                            update_time='" . $safeTime . "'
                          WHERE id='" . $itemId . "' AND status='A'";
            if (!mysqli_query($connect, $fallbackSql)) {
                return array('ok' => 0, 'message' => 'Failed to update work item details. Please run insert_table.php first.');
            }
        }

        $oldAssigneeUserId = isset($existingRow['assignee_user_id']) ? (int) $existingRow['assignee_user_id'] : 0;
        $oldReporterUserId = isset($existingRow['reporter_user_id']) ? (int) $existingRow['reporter_user_id'] : 0;
        $oldPriority = taskNormalizePriority(isset($existingRow['priority']) ? $existingRow['priority'] : 'Medium');
        $oldEstimate = taskParseOriginalEstimate(isset($existingRow['original_estimate']) ? $existingRow['original_estimate'] : '');
        $oldStatusSelection = taskResolveStatusLabelSelection(
            $connect,
            isset($existingRow['task_status']) && $existingRow['task_status'] !== null ? (string) $existingRow['task_status'] : ''
        );

        $oldStatusIds = isset($oldStatusSelection['ids']) && is_array($oldStatusSelection['ids']) ? $oldStatusSelection['ids'] : array();
        $newStatusIds = isset($statusSelection['ids']) && is_array($statusSelection['ids']) ? $statusSelection['ids'] : array();
        sort($oldStatusIds);
        sort($newStatusIds);

        $oldStartDate = isset($existingRow['start_date']) && $existingRow['start_date'] !== null ? (string) $existingRow['start_date'] : '';
        $oldDueDate = isset($existingRow['due_date']) && $existingRow['due_date'] !== null ? (string) $existingRow['due_date'] : '';
        $oldAmendementDate = isset($existingRow['amendement_date']) && $existingRow['amendement_date'] !== null ? (string) $existingRow['amendement_date'] : '';
        $oldSecondAmendementDate = isset($existingRow['second_amendement_date']) && $existingRow['second_amendement_date'] !== null ? (string) $existingRow['second_amendement_date'] : '';
        $oldAmendementTimeMinutes = taskSqlTimeToMinutes(isset($existingRow['amendement_time']) ? $existingRow['amendement_time'] : '');
        $oldSecondAmendementTimeMinutes = taskSqlTimeToMinutes(isset($existingRow['second_amendement_time']) ? $existingRow['second_amendement_time'] : '');

        $formatUserValue = function ($userId) {
            $userId = (int) $userId;
            return $userId > 0 ? ('User #' . $userId) : 'Unassigned';
        };

        if ($oldAssigneeUserId !== $assigneeUserId) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Assignee', $formatUserValue($oldAssigneeUserId), $formatUserValue($assigneeUserId), 'changed Assignee', $currentUserId, $cdate, $ctime);
        }
        if ($oldReporterUserId !== $reporterUserId) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Reporter', $formatUserValue($oldReporterUserId), $formatUserValue($reporterUserId), 'changed Reporter', $currentUserId, $cdate, $ctime);
        }
        if ($oldPriority !== $priority) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Priority', $oldPriority, $priority, 'changed Priority', $currentUserId, $cdate, $ctime);
        }

        $oldEstimateText = ((int) $oldEstimate['value']) . ' ' . (string) $oldEstimate['unit'];
        $newEstimateText = $originalEstimateValue . ' ' . $estimateUnit;
        if ($oldEstimateText !== $newEstimateText) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Original Estimate', $oldEstimateText, $newEstimateText, 'changed Original Estimate', $currentUserId, $cdate, $ctime);
        }

        if (implode(',', $oldStatusIds) !== implode(',', $newStatusIds)) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Task Status', implode(',', $oldStatusIds), implode(',', $newStatusIds), 'changed Task Status', $currentUserId, $cdate, $ctime);
        }
        if ($oldStartDate !== ($startDate !== '' ? $startDate : ($dueDate !== '' ? $dueDate : ''))) {
            $newStartDateValue = $startDate !== '' ? $startDate : ($dueDate !== '' ? $dueDate : '');
            taskLogItemHistory($connect, $itemId, 'update_field', 'Start date', $oldStartDate, $newStartDateValue, 'changed Start date', $currentUserId, $cdate, $ctime);
        }
        if ($oldDueDate !== $dueDate) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Due date', $oldDueDate, $dueDate, 'changed Due date', $currentUserId, $cdate, $ctime);
        }
        if ($oldAmendementDate !== $amendementDate) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Amendement Date', $oldAmendementDate, $amendementDate, 'changed Amendement Date', $currentUserId, $cdate, $ctime);
        }
        if ((int) $oldAmendementTimeMinutes !== (int) $amendementTimeMinutes) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Amendement Time', (string) ((int) $oldAmendementTimeMinutes), (string) ((int) $amendementTimeMinutes), 'changed Amendement Time', $currentUserId, $cdate, $ctime);
        }
        if ($oldSecondAmendementDate !== $secondAmendementDate) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Second Amen-Date', $oldSecondAmendementDate, $secondAmendementDate, 'changed Second Amen-Date', $currentUserId, $cdate, $ctime);
        }
        if ((int) $oldSecondAmendementTimeMinutes !== (int) $secondAmendementTimeMinutes) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Second Amen-Time', (string) ((int) $oldSecondAmendementTimeMinutes), (string) ((int) $secondAmendementTimeMinutes), 'changed Second Amen-Time', $currentUserId, $cdate, $ctime);
        }

        $detailResult = taskGetItemDetail($connect, $itemId);
        if (empty($detailResult['ok'])) {
            return array('ok' => 1, 'message' => 'Work item details updated successfully.');
        }

        return array(
            'ok' => 1,
            'message' => 'Work item details updated successfully.',
            'detail' => isset($detailResult['detail']) ? $detailResult['detail'] : array(),
            'statusLabels' => isset($detailResult['statusLabels']) ? $detailResult['statusLabels'] : array(),
            'parentOptions' => isset($detailResult['parentOptions']) ? $detailResult['parentOptions'] : array(),
            'webLinks' => isset($detailResult['webLinks']) ? $detailResult['webLinks'] : array(),
        );
    }
}

if (!function_exists('taskGetItemLabelsByItemIds')) {
    function taskGetItemLabelsByItemIds($connect, $itemIds)
    {
        $map = array();
        $itemIds = array_values(array_unique(array_map('intval', (array) $itemIds)));
        $itemIds = array_filter($itemIds, function ($id) {
            return $id > 0;
        });

        if (empty($itemIds)) {
            return $map;
        }

        $idSql = implode(',', $itemIds);
        $sql = "SELECT il.item_id, l.id AS label_id, l.name AS label_name
                FROM " . TASK_ITEM_LABEL . " il
                INNER JOIN " . TASK_LABEL . " l ON l.id = il.label_id AND l.status='A'
                WHERE il.status='A' AND il.item_id IN (" . $idSql . ")
                ORDER BY l.name ASC";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            $sql = "SELECT i.id, i.column_id, i.title, '' AS description, i.work_type_id, i.assignee_user_id, i.due_date, i.sort_order, 0 AS project_key_id,
                    wt.name AS work_type_name, '' AS work_type_svg_icon,
                '' AS item_project_key,
                COALESCE(NULLIF(TRIM(u.name), ''), u.username, '') AS assignee_name
                FROM " . TASK_ITEM . " i
                LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                LEFT JOIN " . USR_USER . " u ON u.id = i.assignee_user_id AND u.status='A'
                WHERE i.status='A'
                ORDER BY i.column_id ASC, i.sort_order ASC, i.id ASC";
            $rst = mysqli_query($connect, $sql);
        }
        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $itemId = (int) $row['item_id'];
                if (!isset($map[$itemId])) {
                    $map[$itemId] = array();
                }

                $map[$itemId][] = array(
                    'id' => (int) $row['label_id'],
                    'name' => (string) $row['label_name'],
                );
            }
        }

        return $map;
    }
}

if (!function_exists('taskSaveItemWorklog')) {
    function taskSaveItemWorklog($connect, $itemId, $seconds, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $seconds = (int) $seconds;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid worklog save request.');
        }

        if ($seconds <= 0) {
            return array('ok' => 0, 'message' => 'Worklog time must be greater than 0.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,time_tracking FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $oldValue = isset($itemRow['time_tracking']) && $itemRow['time_tracking'] !== null
            ? trim((string) $itemRow['time_tracking'])
            : '';
        $oldSeconds = taskParseWorklogDurationSeconds($oldValue);
        $newTotalSeconds = max(0, $oldSeconds + $seconds);
        $newValue = $newTotalSeconds > 0 ? taskFormatWorklogDuration($newTotalSeconds) : 'No time logged';
        $addedValue = taskFormatWorklogDuration($seconds);

        $safeNewValue = taskEsc($connect, $newValue);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                time_tracking='" . $safeNewValue . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            return array('ok' => 0, 'message' => 'Failed to save worklog.');
        }

        taskLogItemHistory(
            $connect,
            $itemId,
            'worklog_saved',
            'Time Tracking',
            $oldValue,
            $newValue,
            'logged ' . $addedValue,
            $currentUserId,
            $cdate,
            $ctime
        );

        $detailResult = taskGetItemDetail($connect, $itemId);
        $detail = !empty($detailResult['ok']) && isset($detailResult['detail']) && is_array($detailResult['detail'])
            ? $detailResult['detail']
            : array();

        return array(
            'ok' => 1,
            'message' => 'Work log added.',
            'time_tracking' => isset($detail['time_tracking']) ? (string) $detail['time_tracking'] : $newValue,
            'detail' => $detail,
            'history' => taskGetItemHistory($connect, $itemId, 150),
        );
    }
}

if (!function_exists('taskGetColumns')) {
    function taskGetColumns($connect)
    {
        $rows = array();
        $sql = "SELECT id,name,sort_order FROM " . TASK_COLUMN . " WHERE status='A' ORDER BY sort_order ASC, id ASC";
        $rst = mysqli_query($connect, $sql);

        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'sort_order' => (int) $row['sort_order'],
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskGetItemsGroupedByColumn')) {
    function taskGetItemsGroupedByColumn($connect)
    {
        $grouped = array();
        $allItemIds = array();
        $projectKeySetting = taskGetProjectKeySetting($connect);
        $defaultProjectKeyId = isset($projectKeySetting['id']) ? (int) $projectKeySetting['id'] : 0;
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

    $sql = "SELECT i.id, i.column_id, i.title, i.description, i.work_type_id, i.assignee_user_id, i.reporter_user_id,
                i.priority, i.start_date, i.due_date, i.task_status, i.create_date, i.update_date,
                i.original_estimate, i.amendement_date, i.amendement_time, i.second_amendement_date, i.second_amendement_time,
                i.sort_order, i.project_key_id,
                wt.name AS work_type_name, wt.svg_icon AS work_type_svg_icon,
                pk.project_key AS item_project_key,
                COALESCE(NULLIF(TRIM(u.name), ''), u.username, '') AS assignee_name,
                COALESCE(NULLIF(TRIM(ur.name), ''), ur.username, '') AS reporter_name
                FROM " . TASK_ITEM . " i
                LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = i.project_key_id AND pk.status='A'
                LEFT JOIN " . USR_USER . " u ON u.id = i.assignee_user_id AND u.status='A'
                LEFT JOIN " . USR_USER . " ur ON ur.id = i.reporter_user_id AND ur.status='A'
                WHERE i.status='A'
                ORDER BY i.column_id ASC, i.sort_order ASC, i.id ASC";

        $rst = mysqli_query($connect, $sql);
        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $columnId = (int) $row['column_id'];
                if (!isset($grouped[$columnId])) {
                    $grouped[$columnId] = array();
                }

                $resolvedProjectKey = isset($row['item_project_key']) && $row['item_project_key'] !== null ? taskNormalizeProjectKey($row['item_project_key']) : '';
                if ($resolvedProjectKey === '') {
                    $resolvedProjectKey = taskNormalizeProjectKey($defaultProjectKey);
                }

                $resolvedProjectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
                if ($resolvedProjectKeyId <= 0) {
                    $resolvedProjectKeyId = $defaultProjectKeyId;
                }

                $estimate = taskParseOriginalEstimate(isset($row['original_estimate']) ? $row['original_estimate'] : '');

                $grouped[$columnId][] = array(
                    'id' => (int) $row['id'],
                    'column_id' => $columnId,
                    'title' => (string) $row['title'],
                    'description' => isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : '',
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
                    'project_key_id' => $resolvedProjectKeyId,
                    'project_key' => $resolvedProjectKey,
                    'work_item_key' => taskBuildWorkItemKey($resolvedProjectKey, (int) $row['id']),
                    'work_type_id' => isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0,
                    'work_type_name' => isset($row['work_type_name']) && $row['work_type_name'] !== null ? (string) $row['work_type_name'] : 'Task',
                    'work_type_svg_icon' => taskNormalizeWorkTypeSvgIcon(isset($row['work_type_svg_icon']) ? $row['work_type_svg_icon'] : '', isset($row['work_type_name']) ? (string) $row['work_type_name'] : 'Task'),
                    'assignee_user_id' => isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0,
                    'reporter_user_id' => isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0,
                    'reporter_name' => isset($row['reporter_name']) ? (string) $row['reporter_name'] : '',
                    'priority' => taskNormalizePriority(isset($row['priority']) ? $row['priority'] : 'Medium'),
                    'original_estimate_value' => isset($estimate['value']) ? (int) $estimate['value'] : 0,
                    'original_estimate_unit' => isset($estimate['unit']) ? (string) $estimate['unit'] : 'minutes',
                    'start_date' => isset($row['start_date']) && $row['start_date'] !== null ? (string) $row['start_date'] : '',
                    'assignee_name' => isset($row['assignee_name']) ? (string) $row['assignee_name'] : '',
                    'due_date' => isset($row['due_date']) && $row['due_date'] !== null ? (string) $row['due_date'] : '',
                    'task_status' => isset($row['task_status']) && $row['task_status'] !== null ? (string) $row['task_status'] : '',
                    'task_status_label_ids' => taskParseCsvIdList(isset($row['task_status']) ? $row['task_status'] : ''),
                    'create_date' => isset($row['create_date']) && $row['create_date'] !== null ? (string) $row['create_date'] : '',
                    'update_date' => isset($row['update_date']) && $row['update_date'] !== null ? (string) $row['update_date'] : '',
                    'amendement_date' => isset($row['amendement_date']) && $row['amendement_date'] !== null ? (string) $row['amendement_date'] : '',
                    'amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['amendement_time']) ? $row['amendement_time'] : ''),
                    'second_amendement_date' => isset($row['second_amendement_date']) && $row['second_amendement_date'] !== null ? (string) $row['second_amendement_date'] : '',
                    'second_amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['second_amendement_time']) ? $row['second_amendement_time'] : ''),
                );

                $allItemIds[] = (int) $row['id'];
            }
        }

        $labelsMap = taskGetItemLabelsByItemIds($connect, $allItemIds);
        $parentMap = taskGetParentMapByChildIds($connect, $allItemIds);
        foreach ($grouped as $columnId => $items) {
            foreach ($items as $index => $item) {
                $itemId = (int) $item['id'];
                $grouped[$columnId][$index]['labels'] = isset($labelsMap[$itemId]) ? $labelsMap[$itemId] : array();
                $grouped[$columnId][$index]['parent_item_id'] = isset($parentMap[$itemId]) ? (int) $parentMap[$itemId] : 0;
            }
        }

        return $grouped;
    }
}

if (!function_exists('taskInitials')) {
    function taskInitials($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name);
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials === '' ? 'U' : $initials;
    }
}

if (!function_exists('taskRenderSidebarMenu')) {
    function taskRenderSidebarMenu($siteUrl, $activeMenu)
    {
        $menus = array(
            'summary' => array('label' => 'Summary', 'path' => '/task/summary.php'),
            'board' => array('label' => 'Board', 'path' => '/task/board.php'),
            'sheets' => array('label' => 'Sheets', 'path' => '/task/sheets.php'),
        );

        foreach ($menus as $menuKey => $menu) {
            $isActive = ($activeMenu === $menuKey);
            echo '<a class="task-sidebar-link' . ($isActive ? ' active' : '') . '" href="' . htmlspecialchars(rtrim((string) $siteUrl, '/') . $menu['path'], ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8');
            echo '</a>';
        }
    }
}

if (!function_exists('taskRenderMobileMenuDropdown')) {
    function taskRenderMobileMenuDropdown($siteUrl, $activeMenu)
    {
        $menus = array(
            'summary' => array('label' => 'Summary', 'path' => '/task/summary.php'),
            'board' => array('label' => 'Board', 'path' => '/task/board.php'),
            'sheets' => array('label' => 'Sheets', 'path' => '/task/sheets.php'),
        );

        $activeLabel = isset($menus[$activeMenu]['label']) ? $menus[$activeMenu]['label'] : 'Board';

        echo '<div class="task-mobile-menu dropdown d-lg-none">';
        echo '<button class="btn dropdown-toggle task-mobile-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
        echo '<i class="fa-solid fa-bars"></i> ' . htmlspecialchars($activeLabel, ENT_QUOTES, 'UTF-8');
        echo '</button>';
        echo '<ul class="dropdown-menu task-mobile-menu-list">';

        foreach ($menus as $menuKey => $menu) {
            $href = htmlspecialchars(rtrim((string) $siteUrl, '/') . $menu['path'], ENT_QUOTES, 'UTF-8');
            $isActive = $activeMenu === $menuKey;
            echo '<li><a class="dropdown-item' . ($isActive ? ' active' : '') . '" href="' . $href . '">' . htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}

if (!function_exists('taskRenderWorkTypeDropdownItems')) {
    function taskRenderWorkTypeDropdownItems($workTypes)
    {
        foreach ($workTypes as $workType) {
            $workTypeId = (int) $workType['id'];
            $workTypeName = (string) $workType['name'];
            $workTypeRemark = isset($workType['remark']) ? (string) $workType['remark'] : '';
            $workTypeIcon = taskNormalizeWorkTypeSvgIcon(isset($workType['svg_icon']) ? $workType['svg_icon'] : '', $workTypeName);
            echo '<li><a class="dropdown-item task-work-type-option" href="#" data-work-type-id="' . $workTypeId . '" data-work-type-name="' . htmlspecialchars($workTypeName, ENT_QUOTES, 'UTF-8') . '" data-work-type-remark="' . htmlspecialchars($workTypeRemark, ENT_QUOTES, 'UTF-8') . '" data-work-type-icon="' . htmlspecialchars($workTypeIcon, ENT_QUOTES, 'UTF-8') . '"><img class="task-work-type-option-icon" src="' . htmlspecialchars($workTypeIcon, ENT_QUOTES, 'UTF-8') . '" alt=""> ' . htmlspecialchars($workTypeName, ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li><a class="dropdown-item task-work-type-action" href="#" data-action="add">Add work type</a></li>';
        echo '<li><a class="dropdown-item task-work-type-action" href="#" data-action="edit">Edit work type</a></li>';
    }
}

if (!function_exists('taskRenderAssigneeDropdownItems')) {
    function taskRenderAssigneeDropdownItems($assignees)
    {
        echo '<li class="task-assignee-search-wrap px-2 pb-2">';
        echo '  <input type="text" class="form-control form-control-sm task-assignee-search-input" placeholder="Search assignee">';
        echo '</li>';
        echo '<li><a class="dropdown-item task-assignee-option" href="#" data-user-id="0" data-user-name="Unassigned"><span class="task-assignee-option-avatar"><i class="fa-regular fa-user"></i></span><span class="task-assignee-option-text">Unassigned</span></a></li>';
        echo '<li><hr class="dropdown-divider"></li>';

        foreach ($assignees as $assignee) {
            $assigneeId = (int) $assignee['id'];
            $assigneeName = (string) $assignee['name'];
            $assigneeEmail = isset($assignee['email']) ? trim((string) $assignee['email']) : '';

            $line = '<span class="task-assignee-option-avatar">' . htmlspecialchars(taskInitials($assigneeName), ENT_QUOTES, 'UTF-8') . '</span><span class="task-assignee-option-text">' . htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8');
            if ($assigneeEmail !== '') {
                $line .= '<br><small class="text-muted">' . htmlspecialchars($assigneeEmail, ENT_QUOTES, 'UTF-8') . '</small>';
            }
            $line .= '</span>';

            echo '<li><a class="dropdown-item task-assignee-option" href="#" data-user-id="' . $assigneeId . '" data-user-name="' . htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8') . '">' . $line . '</a></li>';
        }
    }
}

if (!function_exists('taskRenderCard')) {
    function taskRenderCard($taskItem, $assignees = array())
    {
        $title = isset($taskItem['title']) ? (string) $taskItem['title'] : '';
        $description = isset($taskItem['description']) ? (string) $taskItem['description'] : '';
        $workTypeName = isset($taskItem['work_type_name']) ? (string) $taskItem['work_type_name'] : 'Task';
        $workTypeIcon = taskNormalizeWorkTypeSvgIcon(isset($taskItem['work_type_svg_icon']) ? $taskItem['work_type_svg_icon'] : '', $workTypeName);
        $workItemKey = isset($taskItem['work_item_key']) ? trim((string) $taskItem['work_item_key']) : '';
        $parentItemId = isset($taskItem['parent_item_id']) ? (int) $taskItem['parent_item_id'] : 0;
        $assigneeUserId = isset($taskItem['assignee_user_id']) ? (int) $taskItem['assignee_user_id'] : 0;
        $reporterUserId = isset($taskItem['reporter_user_id']) ? (int) $taskItem['reporter_user_id'] : 0;
        $priority = taskNormalizePriority(isset($taskItem['priority']) ? $taskItem['priority'] : 'Medium');
        $startDate = isset($taskItem['start_date']) ? trim((string) $taskItem['start_date']) : '';
        $assigneeName = isset($taskItem['assignee_name']) ? trim((string) $taskItem['assignee_name']) : '';
        $reporterName = isset($taskItem['reporter_name']) ? trim((string) $taskItem['reporter_name']) : '';
        $dueDate = isset($taskItem['due_date']) ? trim((string) $taskItem['due_date']) : '';
        $createDate = isset($taskItem['create_date']) ? trim((string) $taskItem['create_date']) : '';
        $updateDate = isset($taskItem['update_date']) ? trim((string) $taskItem['update_date']) : '';
        $estimateValue = isset($taskItem['original_estimate_value']) ? (int) $taskItem['original_estimate_value'] : 0;
        $estimateUnit = isset($taskItem['original_estimate_unit']) ? (string) $taskItem['original_estimate_unit'] : 'minutes';
        $amendementDate = isset($taskItem['amendement_date']) ? trim((string) $taskItem['amendement_date']) : '';
        $amendementTimeMinutes = isset($taskItem['amendement_time_minutes']) ? (int) $taskItem['amendement_time_minutes'] : 0;
        $secondAmendementDate = isset($taskItem['second_amendement_date']) ? trim((string) $taskItem['second_amendement_date']) : '';
        $secondAmendementTimeMinutes = isset($taskItem['second_amendement_time_minutes']) ? (int) $taskItem['second_amendement_time_minutes'] : 0;
        $parentDisplay = isset($taskItem['parent_display']) ? trim((string) $taskItem['parent_display']) : '';
        $labels = isset($taskItem['labels']) && is_array($taskItem['labels']) ? $taskItem['labels'] : array();
        $labelIds = array();
        $statusLabelIds = array();
        $assigneeDisplay = $assigneeName === '' ? 'Unassigned' : $assigneeName;
        $assigneeInitial = $assigneeName === '' ? '' : taskInitials($assigneeName);
        $isEpic = strtolower(trim($workTypeName)) === 'epic';

        foreach ($labels as $label) {
            $labelId = isset($label['id']) ? (int) $label['id'] : 0;
            if ($labelId > 0) {
                $labelIds[] = $labelId;
            }
        }

        $labelActionText = !empty($labelIds) ? 'Edit label' : 'Add labels';
        if (isset($taskItem['task_status_label_ids']) && is_array($taskItem['task_status_label_ids'])) {
            foreach ($taskItem['task_status_label_ids'] as $statusLabelId) {
                $statusLabelId = (int) $statusLabelId;
                if ($statusLabelId > 0) {
                    $statusLabelIds[] = $statusLabelId;
                }
            }
            $statusLabelIds = array_values(array_unique($statusLabelIds));
        } else {
            $statusLabelIds = taskParseCsvIdList(isset($taskItem['task_status']) ? $taskItem['task_status'] : '');
        }
        $statusLabelActionText = !empty($statusLabelIds) ? 'Edit task status labels' : 'Add task status labels';

        echo '<article class="task-item-card" data-item-id="' . (int) $taskItem['id'] . '" data-label-ids="' . htmlspecialchars(implode(',', $labelIds), ENT_QUOTES, 'UTF-8') . '" data-assignee-user-id="' . $assigneeUserId . '" data-assignee-name="' . htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8') . '" data-reporter-user-id="' . $reporterUserId . '" data-reporter-name="' . htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8') . '" data-priority="' . htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') . '" data-start-date="' . htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') . '" data-due-date="' . htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8') . '" data-create-date="' . htmlspecialchars($createDate, ENT_QUOTES, 'UTF-8') . '" data-update-date="' . htmlspecialchars($updateDate, ENT_QUOTES, 'UTF-8') . '" data-original-estimate-value="' . $estimateValue . '" data-original-estimate-unit="' . htmlspecialchars($estimateUnit, ENT_QUOTES, 'UTF-8') . '" data-amendement-date="' . htmlspecialchars($amendementDate, ENT_QUOTES, 'UTF-8') . '" data-amendement-time-minutes="' . $amendementTimeMinutes . '" data-second-amendement-date="' . htmlspecialchars($secondAmendementDate, ENT_QUOTES, 'UTF-8') . '" data-second-amendement-time-minutes="' . $secondAmendementTimeMinutes . '" data-work-type-id="' . (int) (isset($taskItem['work_type_id']) ? $taskItem['work_type_id'] : 0) . '" data-work-type-icon="' . htmlspecialchars($workTypeIcon, ENT_QUOTES, 'UTF-8') . '" data-item-description="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" data-work-type-name="' . htmlspecialchars($workTypeName, ENT_QUOTES, 'UTF-8') . '" data-work-item-key="' . htmlspecialchars($workItemKey, ENT_QUOTES, 'UTF-8') . '" data-parent-item-id="' . $parentItemId . '" data-parent-display="' . htmlspecialchars($parentDisplay, ENT_QUOTES, 'UTF-8') . '" data-task-status-label-ids="' . htmlspecialchars(implode(',', $statusLabelIds), ENT_QUOTES, 'UTF-8') . '" draggable="true">';
        echo '<div class="task-item-head">';
        echo '<h6 class="task-item-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h6>';
        echo '<div class="task-item-menu-dropdown">';
        echo '<button class="btn task-item-menu-btn task-open-item-actions-btn" type="button" title="Task options"><i class="fa-solid fa-ellipsis"></i></button>';
        echo '</div>';
        echo '</div>';

        if (!empty($labels)) {
            echo '<div class="task-item-label-row">';
            foreach ($labels as $label) {
                $labelName = isset($label['name']) ? (string) $label['name'] : '';
                if ($labelName === '') {
                    continue;
                }
                echo '<span class="task-label-pill">' . htmlspecialchars($labelName, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '</div>';
        }

        echo '<div class="task-item-meta">';
        echo '<div class="task-item-meta-left">';
        echo '<span class="task-type-icon" title="' . htmlspecialchars($workTypeName, ENT_QUOTES, 'UTF-8') . '"><img class="task-type-pill-icon" src="' . htmlspecialchars($workTypeIcon, ENT_QUOTES, 'UTF-8') . '" alt=""></span>';
        echo '<span class="task-item-key' . ($workItemKey === '' ? ' d-none' : '') . '">' . htmlspecialchars($workItemKey, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</div>';
        echo '<div class="dropdown task-item-assignee-wrap">';
        echo '  <button class="btn task-assignee-pill task-item-assignee-btn dropdown-toggle' . ($assigneeUserId <= 0 ? ' task-assignee-pill-unassigned' : '') . '" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-user-id="' . $assigneeUserId . '" title="' . htmlspecialchars($assigneeDisplay, ENT_QUOTES, 'UTF-8') . '">';
        if ($assigneeUserId > 0 && $assigneeInitial !== '') {
            echo htmlspecialchars($assigneeInitial, ENT_QUOTES, 'UTF-8');
        } else {
            echo '<i class="fa-regular fa-user"></i>';
        }
        echo '  </button>';
        echo '  <ul class="dropdown-menu task-assignee-menu task-assignee-menu-scroll task-item-assignee-menu">';
        taskRenderAssigneeDropdownItems($assignees);
        echo '  </ul>';
        echo '</div>';
        echo '</div>';
        if ($dueDate !== '') {
            echo '<small class="task-item-due-date">Due: ' . htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8') . '</small>';
        }
        echo '</article>';
    }
}

if (!function_exists('taskRenderComposer')) {
    function taskRenderComposer($columnId, $workTypes, $assignees)
    {
        $defaultWorkTypeId = !empty($workTypes) ? (int) $workTypes[0]['id'] : 0;
        $defaultWorkTypeName = !empty($workTypes) ? (string) $workTypes[0]['name'] : 'Task';
        $defaultWorkTypeRemark = !empty($workTypes) && isset($workTypes[0]['remark']) ? (string) $workTypes[0]['remark'] : '';
        $defaultWorkTypeIcon = !empty($workTypes) && isset($workTypes[0]['svg_icon'])
            ? taskNormalizeWorkTypeSvgIcon($workTypes[0]['svg_icon'], $defaultWorkTypeName)
            : taskDefaultWorkTypeSvgIcon($defaultWorkTypeName);

        echo '<div class="task-composer d-none" data-column-id="' . (int) $columnId . '">';
        echo '  <textarea class="form-control task-title-input" rows="2" maxlength="255" placeholder="What needs to be done?"></textarea>';
        echo '  <div class="task-composer-controls">';
        echo '      <div class="task-composer-controls-left">';

        echo '      <div class="dropdown">';
        echo '          <button class="btn task-icon-btn task-icon-btn-compact task-work-type-toggle" type="button" data-bs-toggle="dropdown" data-work-type-id="' . $defaultWorkTypeId . '" data-work-type-name="' . htmlspecialchars($defaultWorkTypeName, ENT_QUOTES, 'UTF-8') . '" data-work-type-remark="' . htmlspecialchars($defaultWorkTypeRemark, ENT_QUOTES, 'UTF-8') . '" data-work-type-icon="' . htmlspecialchars($defaultWorkTypeIcon, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($defaultWorkTypeName, ENT_QUOTES, 'UTF-8') . '"><img class="task-work-type-toggle-icon" src="' . htmlspecialchars($defaultWorkTypeIcon, ENT_QUOTES, 'UTF-8') . '" alt=""></button>';
        echo '          <ul class="dropdown-menu task-work-type-menu">';
        taskRenderWorkTypeDropdownItems($workTypes);
        echo '          </ul>';
        echo '      </div>';

        echo '      <button class="btn task-icon-btn task-due-date-btn" type="button" title="Select due date"><i class="fa-regular fa-calendar"></i></button>';
        echo '      <input class="task-due-date-input" type="date">';

        echo '      <div class="dropdown">';
        echo '          <button class="btn task-icon-btn task-icon-btn-compact dropdown-toggle task-assignee-toggle task-assignee-icon-toggle" type="button" data-bs-toggle="dropdown" data-user-id="0" title="Unassigned"><i class="fa-regular fa-user"></i></button>';
        echo '          <ul class="dropdown-menu task-assignee-menu task-assignee-menu-scroll">';
        taskRenderAssigneeDropdownItems($assignees);
        echo '          </ul>';
        echo '      </div>';
        echo '      </div>';

        echo '      <div class="task-composer-controls-right">';
        echo '          <button class="btn task-create-item-btn" type="button" disabled title="Create work item"><span class="mdi mdi-keyboard-return"></span></button>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';
    }
}

if (!function_exists('taskRenderBoardColumn')) {
    function taskRenderBoardColumn($column, $items, $workTypes, $assignees)
    {
        $columnId = (int) $column['id'];
        $columnName = isset($column['name']) ? (string) $column['name'] : '';
        $itemCount = is_array($items) ? count($items) : 0;

        echo '<section class="task-column" data-column-id="' . $columnId . '">';
        echo '  <div class="task-column-header">';
        echo '      <div class="task-column-title-wrap">';
        echo '          <h5 class="task-column-title">' . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . '</h5>';
        echo '          <span class="task-column-count">' . $itemCount . '</span>';
        echo '      </div>';
        echo '      <div class="task-column-header-actions">';
        echo '          <button class="btn task-column-collapse-btn" type="button" title="Collapse status"><i class="fa-solid fa-left-right"></i></button>';
        echo '          <div class="dropdown">';
        echo '              <button class="btn task-column-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ellipsis"></i></button>';
        echo '              <ul class="dropdown-menu task-column-menu-list">';
        echo '                  <li><a class="dropdown-item task-column-action" href="#" data-action="rename">Rename status</a></li>';
        echo '                  <li><a class="dropdown-item task-column-action" href="#" data-action="move_left">Move status left</a></li>';
        echo '                  <li><a class="dropdown-item task-column-action" href="#" data-action="move_right">Move status right</a></li>';
        echo '                  <li><hr class="dropdown-divider"></li>';
        echo '                  <li><a class="dropdown-item task-column-action text-danger" href="#" data-action="delete">Delete status</a></li>';
        echo '              </ul>';
        echo '          </div>';
        echo '      </div>';
        echo '  </div>';

        echo '  <div class="task-item-list">';
        foreach ($items as $taskItem) {
            taskRenderCard($taskItem, $assignees);
        }
        echo '  </div>';

        echo '  <button class="btn task-open-composer-btn" type="button"><i class="fa-solid fa-plus"></i> Create</button>';
        taskRenderComposer($columnId, $workTypes, $assignees);
        echo '</section>';
    }
}

if (!function_exists('taskCreateColumn')) {
    function taskCreateColumn($connect, $columnName, $currentUserId, $cdate, $ctime)
    {
        $columnName = trim((string) $columnName);
        if ($columnName === '') {
            return array('ok' => 0, 'message' => 'Status name is required.');
        }

        $safeName = taskEsc($connect, substr($columnName, 0, 150));

        $duplicateSql = "SELECT id FROM " . TASK_COLUMN . " WHERE status='A' AND LOWER(name)=LOWER('" . $safeName . "') LIMIT 1";
        $duplicateRst = mysqli_query($connect, $duplicateSql);
        if ($duplicateRst && $duplicateRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'This status name already exists.');
        }

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_COLUMN . " WHERE status='A'");
        $sortOrder = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $sortOrder = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_COLUMN . " (name,sort_order,create_by,create_date,create_time,status)
                      VALUES ('" . $safeName . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create status.');
        }

        return array(
            'ok' => 1,
            'message' => 'Status created successfully.',
            'column' => array(
                'id' => (int) mysqli_insert_id($connect),
                'name' => $columnName,
                'sort_order' => $sortOrder,
            ),
        );
    }
}

if (!function_exists('taskRenameColumn')) {
    function taskRenameColumn($connect, $columnId, $columnName, $currentUserId, $cdate, $ctime)
    {
        $columnId = (int) $columnId;
        $columnName = trim((string) $columnName);

        if ($columnId <= 0 || $columnName === '') {
            return array('ok' => 0, 'message' => 'Invalid status rename request.');
        }

        $safeName = taskEsc($connect, substr($columnName, 0, 150));
        $existsSql = "SELECT id,name FROM " . TASK_COLUMN . " WHERE id='" . $columnId . "' AND status='A' LIMIT 1";
        $existsRst = mysqli_query($connect, $existsSql);
        if (!$existsRst || $existsRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Status not found.');
        }
        $existsRow = $existsRst->fetch_assoc();

        $duplicateSql = "SELECT id FROM " . TASK_COLUMN . " WHERE status='A' AND LOWER(name)=LOWER('" . $safeName . "') AND id <> '" . $columnId . "' LIMIT 1";
        $duplicateRst = mysqli_query($connect, $duplicateSql);
        if ($duplicateRst && $duplicateRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'Another status already uses this name.');
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $updateSql = "UPDATE " . TASK_COLUMN . " SET
                        name='" . $safeName . "',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                      WHERE id='" . $columnId . "' AND status='A'";

        if (!mysqli_query($connect, $updateSql)) {
            return array('ok' => 0, 'message' => 'Failed to rename status.');
        }

        return array('ok' => 1, 'message' => 'Status renamed successfully.', 'column_name' => $columnName, 'old_column_name' => isset($existsRow['name']) ? (string) $existsRow['name'] : '');
    }
}

if (!function_exists('taskMoveColumn')) {
    function taskMoveColumn($connect, $columnId, $direction)
    {
        $columnId = (int) $columnId;
        $direction = strtolower(trim((string) $direction));
        if ($columnId <= 0 || !in_array($direction, array('left', 'right'), true)) {
            return array('ok' => 0, 'message' => 'Invalid status move request.');
        }

        $currentSql = "SELECT id, sort_order FROM " . TASK_COLUMN . " WHERE id='" . $columnId . "' AND status='A' LIMIT 1";
        $currentRst = mysqli_query($connect, $currentSql);
        if (!$currentRst || $currentRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Status not found.');
        }

        $current = $currentRst->fetch_assoc();
        $currentSort = (int) $current['sort_order'];

        if ($direction === 'left') {
            $targetSql = "SELECT id, sort_order FROM " . TASK_COLUMN . " WHERE status='A' AND sort_order < '" . $currentSort . "' ORDER BY sort_order DESC, id DESC LIMIT 1";
        } else {
            $targetSql = "SELECT id, sort_order FROM " . TASK_COLUMN . " WHERE status='A' AND sort_order > '" . $currentSort . "' ORDER BY sort_order ASC, id ASC LIMIT 1";
        }

        $targetRst = mysqli_query($connect, $targetSql);
        if (!$targetRst || $targetRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'No status available to move.');
        }

        $target = $targetRst->fetch_assoc();
        $targetId = (int) $target['id'];
        $targetSort = (int) $target['sort_order'];

        mysqli_begin_transaction($connect);
        $okA = mysqli_query($connect, "UPDATE " . TASK_COLUMN . " SET sort_order='-999999' WHERE id='" . $columnId . "'");
        $okB = mysqli_query($connect, "UPDATE " . TASK_COLUMN . " SET sort_order='" . $currentSort . "' WHERE id='" . $targetId . "'");
        $okC = mysqli_query($connect, "UPDATE " . TASK_COLUMN . " SET sort_order='" . $targetSort . "' WHERE id='" . $columnId . "'");

        if (!$okA || !$okB || !$okC) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to move status.');
        }

        mysqli_commit($connect);

        return array('ok' => 1, 'message' => 'Status moved successfully.');
    }
}

if (!function_exists('taskDeleteColumn')) {
    function taskDeleteColumn($connect, $columnId, $currentUserId, $cdate, $ctime)
    {
        $columnId = (int) $columnId;
        if ($columnId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid status delete request.');
        }

        $existsSql = "SELECT id,name FROM " . TASK_COLUMN . " WHERE id='" . $columnId . "' AND status='A' LIMIT 1";
        $existsRst = mysqli_query($connect, $existsSql);
        if (!$existsRst || $existsRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Status not found.');
        }
        $existsRow = $existsRst->fetch_assoc();

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);
        $okItems = mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE column_id='" . $columnId . "' AND status='A'");
        $okStatus = mysqli_query($connect, "UPDATE " . TASK_COLUMN . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $columnId . "' AND status='A'");

        if (!$okItems || !$okStatus) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to delete status.');
        }

        mysqli_commit($connect);

        return array('ok' => 1, 'message' => 'Status deleted successfully.', 'column_name' => isset($existsRow['name']) ? (string) $existsRow['name'] : '');
    }
}

if (!function_exists('taskCreateWorkType')) {
    function taskCreateWorkType($connect, $name, $remark, $svgIcon, $currentUserId, $cdate, $ctime)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return array('ok' => 0, 'message' => 'Work type name is required.');
        }

        $safeName = taskEsc($connect, substr($name, 0, 80));
        $duplicateSql = "SELECT id FROM " . TASK_WORK_TYPE . " WHERE status='A' AND LOWER(name)=LOWER('" . $safeName . "') LIMIT 1";
        $duplicateRst = mysqli_query($connect, $duplicateSql);
        if ($duplicateRst && $duplicateRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'This work type already exists.');
        }

        $safeRemark = taskEsc($connect, substr(trim((string) $remark), 0, 255));
        $safeIcon = taskEsc($connect, taskNormalizeWorkTypeSvgIcon($svgIcon, $name));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_WORK_TYPE . " (name,svg_icon,remark,create_by,create_date,create_time,status)
                  VALUES ('" . $safeName . "','" . $safeIcon . "','" . $safeRemark . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create work type.');
        }

        return array('ok' => 1, 'message' => 'Work type created successfully.');
    }
}

if (!function_exists('taskUpdateWorkType')) {
    function taskUpdateWorkType($connect, $workTypeId, $name, $remark, $svgIcon, $currentUserId, $cdate, $ctime)
    {
        $workTypeId = (int) $workTypeId;
        $name = trim((string) $name);
        if ($workTypeId <= 0 || $name === '') {
            return array('ok' => 0, 'message' => 'Invalid work type update request.');
        }

        $safeName = taskEsc($connect, substr($name, 0, 80));
        $duplicateSql = "SELECT id FROM " . TASK_WORK_TYPE . " WHERE status='A' AND LOWER(name)=LOWER('" . $safeName . "') AND id <> " . $workTypeId . " LIMIT 1";
        $duplicateRst = mysqli_query($connect, $duplicateSql);
        if ($duplicateRst && $duplicateRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'Another work type already uses this name.');
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);
    $safeRemark = taskEsc($connect, substr(trim((string) $remark), 0, 255));
    $safeIcon = taskEsc($connect, taskNormalizeWorkTypeSvgIcon($svgIcon, $name));

        $updateSql = "UPDATE " . TASK_WORK_TYPE . " SET
                        name='" . $safeName . "',
            svg_icon='" . $safeIcon . "',
            remark='" . $safeRemark . "',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                      WHERE id='" . $workTypeId . "' AND status='A'";

        if (!mysqli_query($connect, $updateSql)) {
            return array('ok' => 0, 'message' => 'Failed to update work type.');
        }

        return array('ok' => 1, 'message' => 'Work type updated successfully.');
    }
}

if (!function_exists('taskCreateItem')) {
    function taskCreateItem($connect, $columnId, $title, $workTypeId, $assigneeUserId, $dueDate, $currentUserId, $cdate, $ctime)
    {
        $columnId = (int) $columnId;
        $workTypeId = (int) $workTypeId;
        $assigneeUserId = (int) $assigneeUserId;
        $title = trim((string) $title);
        $dueDate = trim((string) $dueDate);

        if ($columnId <= 0 || $title === '') {
            return array('ok' => 0, 'message' => 'Task title is required.');
        }

        $columnRst = getData('id', "id='" . $columnId . "'", 'LIMIT 1', TASK_COLUMN, $connect);
        if (!$columnRst || $columnRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Selected column does not exist.');
        }

        if ($workTypeId > 0) {
            $workTypeRst = getData('id', "id='" . $workTypeId . "'", 'LIMIT 1', TASK_WORK_TYPE, $connect);
            if (!$workTypeRst || $workTypeRst->num_rows === 0) {
                $workTypeId = 0;
            }
        }

        if ($assigneeUserId > 0) {
            $assigneeRst = getData('id', "id='" . $assigneeUserId . "'", 'LIMIT 1', USR_USER, $connect);
            if (!$assigneeRst || $assigneeRst->num_rows === 0) {
                $assigneeUserId = 0;
            }
        }

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $columnId . "'");
        $sortOrder = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $sortOrder = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $safeTitle = taskEsc($connect, substr($title, 0, 255));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);
        $safeDueDate = 'NULL';
        if ($dueDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $safeDueDate = "'" . taskEsc($connect, $dueDate) . "'";
        }

        $projectKeySetting = taskGetProjectKeySetting($connect);
        $projectKeyId = isset($projectKeySetting['id']) ? (int) $projectKeySetting['id'] : 0;
        $projectKeyText = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $reporterUserId = ctype_digit((string) $currentUserId) ? (int) $currentUserId : 0;
        $safePriority = taskEsc($connect, 'Medium');
        $safeOriginalEstimate = taskEsc($connect, '0 minutes');
        $safeStartDate = $safeDueDate;

        $insertSql = "INSERT INTO " . TASK_ITEM . "
                            (column_id,title,description,project_key_id,work_type_id,assignee_user_id,due_date,start_date,original_estimate,task_status,parent_item_id,reporter_user_id,priority,time_tracking,amendement_date,amendement_time,second_amendement_date,second_amendement_time,sort_order,create_by,create_date,create_time,status)
                      VALUES
                        ('" . $columnId . "','" . $safeTitle . "','','" . $projectKeyId . "','" . $workTypeId . "','" . $assigneeUserId . "'," . $safeDueDate . "," . $safeStartDate . ",'" . $safeOriginalEstimate . "','','0','" . $reporterUserId . "','" . $safePriority . "','',NULL,NULL,NULL,NULL,'" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            $fallbackSql = "INSERT INTO " . TASK_ITEM . "
                                                (column_id,title,work_type_id,assignee_user_id,due_date,sort_order,create_by,create_date,create_time,status)
                          VALUES
                                                ('" . $columnId . "','" . $safeTitle . "','" . $workTypeId . "','" . $assigneeUserId . "'," . $safeDueDate . ",'" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
            if (!mysqli_query($connect, $fallbackSql)) {
                return array('ok' => 0, 'message' => 'Failed to create work item.');
            }
        }

        $insertedId = (int) mysqli_insert_id($connect);
        $itemSql = "SELECT i.id, i.column_id, i.title, i.description, i.project_key_id, i.work_type_id, i.assignee_user_id, i.reporter_user_id,
                    i.priority, i.start_date, i.due_date, i.task_status, i.create_date, i.update_date,
                    i.original_estimate, i.amendement_date, i.amendement_time, i.second_amendement_date, i.second_amendement_time,
                    wt.name AS work_type_name, wt.svg_icon AS work_type_svg_icon,
                    pk.project_key AS item_project_key,
                    COALESCE(NULLIF(TRIM(u.name), ''), u.username, '') AS assignee_name,
                    COALESCE(NULLIF(TRIM(ur.name), ''), ur.username, '') AS reporter_name
                    FROM " . TASK_ITEM . " i
                    LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                    LEFT JOIN " . TASK_PROJECT_KEY . " pk ON pk.id = i.project_key_id AND pk.status='A'
                    LEFT JOIN " . USR_USER . " u ON u.id = i.assignee_user_id AND u.status='A'
                    LEFT JOIN " . USR_USER . " ur ON ur.id = i.reporter_user_id AND ur.status='A'
                    WHERE i.id='" . $insertedId . "' LIMIT 1";

        $itemRst = mysqli_query($connect, $itemSql);
        if ($itemRst === false) {
            $itemSql = "SELECT i.id, i.column_id, i.title, '' AS description, 0 AS project_key_id, i.work_type_id, i.assignee_user_id, 0 AS reporter_user_id,
                        'Medium' AS priority, i.due_date AS start_date, i.due_date, '' AS task_status, '' AS create_date, '' AS update_date,
                        '' AS original_estimate, NULL AS amendement_date, NULL AS amendement_time, NULL AS second_amendement_date, NULL AS second_amendement_time,
                        wt.name AS work_type_name, '' AS work_type_svg_icon,
                        '' AS item_project_key,
                        COALESCE(NULLIF(TRIM(u.name), ''), u.username, '') AS assignee_name,
                        '' AS reporter_name
                        FROM " . TASK_ITEM . " i
                        LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                        LEFT JOIN " . USR_USER . " u ON u.id = i.assignee_user_id AND u.status='A'
                        WHERE i.id='" . $insertedId . "' LIMIT 1";
            $itemRst = mysqli_query($connect, $itemSql);
        }
        $item = array(
            'id' => $insertedId,
            'column_id' => $columnId,
            'title' => $title,
            'description' => '',
            'project_key_id' => $projectKeyId,
            'project_key' => taskNormalizeProjectKey($projectKeyText),
            'work_item_key' => taskBuildWorkItemKey($projectKeyText, $insertedId),
            'work_type_id' => $workTypeId,
            'work_type_name' => 'Task',
            'work_type_svg_icon' => taskDefaultWorkTypeSvgIcon('Task'),
            'assignee_user_id' => $assigneeUserId,
            'reporter_user_id' => $reporterUserId,
            'assignee_name' => '',
            'reporter_name' => '',
            'priority' => 'Medium',
            'start_date' => $dueDate,
            'due_date' => $dueDate,
            'task_status' => '',
            'task_status_label_ids' => array(),
            'create_date' => $cdate,
            'update_date' => $cdate,
            'original_estimate_value' => 0,
            'original_estimate_unit' => 'minutes',
            'amendement_date' => '',
            'amendement_time_minutes' => 0,
            'second_amendement_date' => '',
            'second_amendement_time_minutes' => 0,
        );

        if ($itemRst && $itemRst->num_rows > 0) {
            $item = $itemRst->fetch_assoc();
            $item['id'] = (int) $item['id'];
            $item['column_id'] = (int) $item['column_id'];
            $item['description'] = isset($item['description']) && $item['description'] !== null ? (string) $item['description'] : '';
            $item['project_key_id'] = isset($item['project_key_id']) ? (int) $item['project_key_id'] : 0;
            $item['work_type_id'] = isset($item['work_type_id']) ? (int) $item['work_type_id'] : 0;
            $item['assignee_user_id'] = isset($item['assignee_user_id']) ? (int) $item['assignee_user_id'] : 0;
            $item['work_type_name'] = isset($item['work_type_name']) && $item['work_type_name'] !== null ? (string) $item['work_type_name'] : 'Task';
            $itemProjectKey = isset($item['item_project_key']) && $item['item_project_key'] !== null ? taskNormalizeProjectKey($item['item_project_key']) : '';
            if ($itemProjectKey === '') {
                $itemProjectKey = taskNormalizeProjectKey($projectKeyText);
            }
            $item['project_key'] = $itemProjectKey;
            $item['work_item_key'] = taskBuildWorkItemKey($itemProjectKey, $insertedId);
            $item['work_type_svg_icon'] = taskNormalizeWorkTypeSvgIcon(isset($item['work_type_svg_icon']) ? $item['work_type_svg_icon'] : '', $item['work_type_name']);
            $item['assignee_name'] = isset($item['assignee_name']) ? (string) $item['assignee_name'] : '';
            $item['reporter_user_id'] = isset($item['reporter_user_id']) ? (int) $item['reporter_user_id'] : 0;
            $item['reporter_name'] = isset($item['reporter_name']) ? (string) $item['reporter_name'] : '';
            $item['priority'] = taskNormalizePriority(isset($item['priority']) ? $item['priority'] : 'Medium');
            $item['start_date'] = isset($item['start_date']) && $item['start_date'] !== null ? (string) $item['start_date'] : '';
            $item['due_date'] = isset($item['due_date']) && $item['due_date'] !== null ? (string) $item['due_date'] : '';
            $item['task_status'] = isset($item['task_status']) && $item['task_status'] !== null ? (string) $item['task_status'] : '';
            $item['task_status_label_ids'] = taskParseCsvIdList($item['task_status']);
            $item['create_date'] = isset($item['create_date']) && $item['create_date'] !== null ? (string) $item['create_date'] : $cdate;
            $item['update_date'] = isset($item['update_date']) && $item['update_date'] !== null ? (string) $item['update_date'] : $cdate;
            $estimate = taskParseOriginalEstimate(isset($item['original_estimate']) ? $item['original_estimate'] : '');
            $item['original_estimate_value'] = isset($estimate['value']) ? (int) $estimate['value'] : 0;
            $item['original_estimate_unit'] = isset($estimate['unit']) ? (string) $estimate['unit'] : 'minutes';
            $item['amendement_date'] = isset($item['amendement_date']) && $item['amendement_date'] !== null ? (string) $item['amendement_date'] : '';
            $item['amendement_time_minutes'] = taskSqlTimeToMinutes(isset($item['amendement_time']) ? $item['amendement_time'] : '');
            $item['second_amendement_date'] = isset($item['second_amendement_date']) && $item['second_amendement_date'] !== null ? (string) $item['second_amendement_date'] : '';
            $item['second_amendement_time_minutes'] = taskSqlTimeToMinutes(isset($item['second_amendement_time']) ? $item['second_amendement_time'] : '');
            unset($item['item_project_key']);
            unset($item['original_estimate']);
            unset($item['amendement_time']);
            unset($item['second_amendement_time']);
        }

        taskLogItemHistory(
            $connect,
            $insertedId,
            'create_item',
            'Work item',
            '',
            isset($item['title']) ? (string) $item['title'] : $title,
            'created the Work item',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array('ok' => 1, 'message' => 'Work item created successfully.', 'item' => $item);
    }
}

if (!function_exists('taskResequenceItemsInColumn')) {
    function taskResequenceItemsInColumn($connect, $columnId)
    {
        $columnId = (int) $columnId;
        if ($columnId <= 0) {
            return;
        }

        $rst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $columnId . "' ORDER BY sort_order ASC, id ASC");
        if (!$rst) {
            return;
        }

        $seq = 1;
        while ($row = $rst->fetch_assoc()) {
            $itemId = (int) $row['id'];
            mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET sort_order='" . $seq . "' WHERE id='" . $itemId . "'");
            $seq++;
        }
    }
}

if (!function_exists('taskMoveItem')) {
    function taskMoveItem($connect, $itemId, $moveTo)
    {
        $itemId = (int) $itemId;
        $moveTo = strtolower(trim((string) $moveTo));
        if ($itemId <= 0 || !in_array($moveTo, array('top', 'up', 'down', 'bottom'), true)) {
            return array('ok' => 0, 'message' => 'Invalid move work item request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,column_id,title FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $columnId = (int) $itemRow['column_id'];

        $list = array();
        $listRst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $columnId . "' ORDER BY sort_order ASC, id ASC");
        if ($listRst) {
            while ($row = $listRst->fetch_assoc()) {
                $list[] = (int) $row['id'];
            }
        }

        $currentIndex = array_search($itemId, $list, true);
        if ($currentIndex === false || count($list) <= 1) {
            return array('ok' => 0, 'message' => 'No move available for this work item.');
        }

        $maxIndex = count($list) - 1;
        $targetIndex = $currentIndex;
        if ($moveTo === 'top') {
            $targetIndex = 0;
        } elseif ($moveTo === 'up') {
            $targetIndex = max(0, $currentIndex - 1);
        } elseif ($moveTo === 'down') {
            $targetIndex = min($maxIndex, $currentIndex + 1);
        } elseif ($moveTo === 'bottom') {
            $targetIndex = $maxIndex;
        }

        if ($targetIndex === $currentIndex) {
            return array('ok' => 0, 'message' => 'No move available for this work item.');
        }

        $itemIdToMove = $list[$currentIndex];
        array_splice($list, $currentIndex, 1);
        array_splice($list, $targetIndex, 0, array($itemIdToMove));

        mysqli_begin_transaction($connect);
        $seq = 1;
        $ok = true;
        foreach ($list as $id) {
            if (!mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET sort_order='" . $seq . "' WHERE id='" . (int) $id . "'")) {
                $ok = false;
                break;
            }
            $seq++;
        }

        if (!$ok) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to move work item.');
        }

        mysqli_commit($connect);
        return array('ok' => 1, 'message' => 'Work item moved successfully.');
    }
}

if (!function_exists('taskChangeItemStatus')) {
    function taskChangeItemStatus($connect, $itemId, $targetColumnId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $targetColumnId = (int) $targetColumnId;
        if ($itemId <= 0 || $targetColumnId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid change status request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,column_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $item = $itemRst->fetch_assoc();
        $currentColumnId = (int) $item['column_id'];
        $fromStatusName = '';
        $toStatusName = '';

        $fromStatusRst = mysqli_query($connect, "SELECT name FROM " . TASK_COLUMN . " WHERE id='" . $currentColumnId . "' AND status='A' LIMIT 1");
        if ($fromStatusRst && $fromStatusRst->num_rows > 0) {
            $fromStatusRow = $fromStatusRst->fetch_assoc();
            $fromStatusName = isset($fromStatusRow['name']) ? (string) $fromStatusRow['name'] : '';
        }

        $columnRst = mysqli_query($connect, "SELECT id FROM " . TASK_COLUMN . " WHERE id='" . $targetColumnId . "' AND status='A' LIMIT 1");
        if (!$columnRst || $columnRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Target status not found.');
        }

        $toStatusRst = mysqli_query($connect, "SELECT name FROM " . TASK_COLUMN . " WHERE id='" . $targetColumnId . "' AND status='A' LIMIT 1");
        if ($toStatusRst && $toStatusRst->num_rows > 0) {
            $toStatusRow = $toStatusRst->fetch_assoc();
            $toStatusName = isset($toStatusRow['name']) ? (string) $toStatusRow['name'] : '';
        }

        if ($currentColumnId === $targetColumnId) {
            return array('ok' => 1, 'message' => 'Status updated successfully.');
        }

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $targetColumnId . "'");
        $targetSort = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $targetSort = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);
        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                column_id='" . $targetColumnId . "',
                sort_order='" . $targetSort . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to change status.');
        }

        taskResequenceItemsInColumn($connect, $currentColumnId);
        taskResequenceItemsInColumn($connect, $targetColumnId);
        mysqli_commit($connect);

        taskLogItemHistory(
            $connect,
            $itemId,
            'change_status',
            'Status',
            $fromStatusName,
            $toStatusName,
            'changed the Status',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array('ok' => 1, 'message' => 'Status updated successfully.');
    }
}

if (!function_exists('taskMoveItemByDrop')) {
    function taskMoveItemByDrop($connect, $itemId, $targetColumnId, $targetIndex, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $targetColumnId = (int) $targetColumnId;
        $targetIndex = (int) $targetIndex;

        if ($itemId <= 0 || $targetColumnId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid drag and drop request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,column_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $item = $itemRst->fetch_assoc();
        $sourceColumnId = (int) $item['column_id'];
        $fromStatusName = '';
        $toStatusName = '';

        $fromStatusRst = mysqli_query($connect, "SELECT name FROM " . TASK_COLUMN . " WHERE id='" . $sourceColumnId . "' AND status='A' LIMIT 1");
        if ($fromStatusRst && $fromStatusRst->num_rows > 0) {
            $fromStatusRow = $fromStatusRst->fetch_assoc();
            $fromStatusName = isset($fromStatusRow['name']) ? (string) $fromStatusRow['name'] : '';
        }

        $columnRst = mysqli_query($connect, "SELECT id FROM " . TASK_COLUMN . " WHERE id='" . $targetColumnId . "' AND status='A' LIMIT 1");
        if (!$columnRst || $columnRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Target status not found.');
        }

        $toStatusRst = mysqli_query($connect, "SELECT name FROM " . TASK_COLUMN . " WHERE id='" . $targetColumnId . "' AND status='A' LIMIT 1");
        if ($toStatusRst && $toStatusRst->num_rows > 0) {
            $toStatusRow = $toStatusRst->fetch_assoc();
            $toStatusName = isset($toStatusRow['name']) ? (string) $toStatusRow['name'] : '';
        }

        $sourceList = array();
        $sourceRst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $sourceColumnId . "' ORDER BY sort_order ASC, id ASC");
        if ($sourceRst) {
            while ($row = $sourceRst->fetch_assoc()) {
                $sourceList[] = (int) $row['id'];
            }
        }

        $targetList = array();
        if ($targetColumnId === $sourceColumnId) {
            $targetList = $sourceList;
        } else {
            $targetRst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $targetColumnId . "' ORDER BY sort_order ASC, id ASC");
            if ($targetRst) {
                while ($row = $targetRst->fetch_assoc()) {
                    $targetList[] = (int) $row['id'];
                }
            }
        }

        $sourceIndex = array_search($itemId, $sourceList, true);
        if ($sourceIndex === false) {
            return array('ok' => 0, 'message' => 'Work item not found in status list.');
        }

        array_splice($sourceList, $sourceIndex, 1);

        if ($targetColumnId === $sourceColumnId) {
            $targetList = $sourceList;
        }

        $maxInsertIndex = count($targetList);
        if ($targetIndex <= 0) {
            $targetIndex = $maxInsertIndex + 1;
        }

        $targetIndex = max(1, min($targetIndex, $maxInsertIndex + 1));
        array_splice($targetList, $targetIndex - 1, 0, array($itemId));

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);

        $okItem = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                column_id='" . $targetColumnId . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okItem) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to move work item.');
        }

        $seq = 1;
        foreach ($sourceList as $id) {
            if (!mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET sort_order='" . $seq . "' WHERE id='" . (int) $id . "'")) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to move work item.');
            }
            $seq++;
        }

        $seq = 1;
        foreach ($targetList as $id) {
            if (!mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET sort_order='" . $seq . "' WHERE id='" . (int) $id . "'")) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to move work item.');
            }
            $seq++;
        }

        mysqli_commit($connect);

        if ($sourceColumnId !== $targetColumnId) {
            taskLogItemHistory(
                $connect,
                $itemId,
                'change_status',
                'Status',
                $fromStatusName,
                $toStatusName,
                'changed the Status',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        return array('ok' => 1, 'message' => 'Work item moved successfully.');
    }
}

if (!function_exists('taskSetItemAssignee')) {
    function taskSetItemAssignee($connect, $itemId, $assigneeUserId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $assigneeUserId = (int) $assigneeUserId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid assignee request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,assignee_user_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $previousAssigneeUserId = isset($itemRow['assignee_user_id']) ? (int) $itemRow['assignee_user_id'] : 0;

        if ($assigneeUserId > 0) {
            $assigneeRst = getData('id', "id='" . $assigneeUserId . "' AND status='A'", 'LIMIT 1', USR_USER, $connect);
            if (!$assigneeRst || $assigneeRst->num_rows === 0) {
                $assigneeUserId = 0;
            }
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                assignee_user_id='" . $assigneeUserId . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            return array('ok' => 0, 'message' => 'Failed to update assignee.');
        }

        if ($previousAssigneeUserId !== $assigneeUserId) {
            taskLogItemHistory(
                $connect,
                $itemId,
                'update_field',
                'Assignee',
                $previousAssigneeUserId > 0 ? ('User #' . $previousAssigneeUserId) : 'Unassigned',
                $assigneeUserId > 0 ? ('User #' . $assigneeUserId) : 'Unassigned',
                'changed Assignee',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        $itemSql = "SELECT i.assignee_user_id,
                    COALESCE(NULLIF(TRIM(u.name), ''), u.username, '') AS assignee_name
                    FROM " . TASK_ITEM . " i
                    LEFT JOIN " . USR_USER . " u ON u.id = i.assignee_user_id AND u.status='A'
                    WHERE i.id='" . $itemId . "' LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        $assigneeName = '';
        if ($itemRst && $itemRst->num_rows > 0) {
            $row = $itemRst->fetch_assoc();
            $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
            $assigneeName = isset($row['assignee_name']) ? (string) $row['assignee_name'] : '';
        }

        return array(
            'ok' => 1,
            'message' => 'Assignee updated successfully.',
            'assignee' => array(
                'user_id' => $assigneeUserId,
                'name' => $assigneeName,
            ),
        );
    }
}

if (!function_exists('taskSetItemWorkType')) {
    function taskSetItemWorkType($connect, $itemId, $workTypeId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $workTypeId = (int) $workTypeId;
        if ($itemId <= 0 || $workTypeId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid work type request.');
        }

        $itemSql = "SELECT i.id,i.work_type_id,COALESCE(NULLIF(TRIM(wt.name), ''), 'Task') AS work_type_name
                    FROM " . TASK_ITEM . " i
                    LEFT JOIN " . TASK_WORK_TYPE . " wt ON wt.id = i.work_type_id AND wt.status='A'
                    WHERE i.id='" . $itemId . "' AND i.status='A'
                    LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $previousWorkTypeId = isset($itemRow['work_type_id']) ? (int) $itemRow['work_type_id'] : 0;
        $previousWorkTypeName = isset($itemRow['work_type_name']) ? (string) $itemRow['work_type_name'] : 'Task';

        $workTypeSql = "SELECT id,name,remark,svg_icon
                        FROM " . TASK_WORK_TYPE . "
                        WHERE id='" . $workTypeId . "' AND status='A'
                        LIMIT 1";
        $workTypeRst = mysqli_query($connect, $workTypeSql);
        if (!$workTypeRst || $workTypeRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work type not found.');
        }

        $workTypeRow = $workTypeRst->fetch_assoc();
        $workTypeId = isset($workTypeRow['id']) ? (int) $workTypeRow['id'] : 0;
        if ($workTypeId <= 0) {
            return array('ok' => 0, 'message' => 'Work type not found.');
        }

        $workTypeName = isset($workTypeRow['name']) ? trim((string) $workTypeRow['name']) : 'Task';
        if ($workTypeName === '') {
            $workTypeName = 'Task';
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                work_type_id='" . $workTypeId . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            return array('ok' => 0, 'message' => 'Failed to update work type.');
        }

        if ($previousWorkTypeId !== $workTypeId) {
            taskLogItemHistory(
                $connect,
                $itemId,
                'update_field',
                'Work type',
                $previousWorkTypeName,
                $workTypeName,
                'changed Work type',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        return array(
            'ok' => 1,
            'message' => 'Work type updated successfully.',
            'work_type' => array(
                'id' => $workTypeId,
                'name' => $workTypeName,
                'remark' => isset($workTypeRow['remark']) ? (string) $workTypeRow['remark'] : '',
                'svg_icon' => taskNormalizeWorkTypeSvgIcon(isset($workTypeRow['svg_icon']) ? $workTypeRow['svg_icon'] : '', $workTypeName),
            ),
        );
    }
}

if (!function_exists('taskUpdateItemCore')) {
    function taskUpdateItemCore($connect, $itemId, $title, $description, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $title = trim((string) $title);
        $description = trim((string) $description);

        if ($itemId <= 0 || $title === '') {
            return array('ok' => 0, 'message' => 'Invalid work item update request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,title,description FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");

        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $previousTitle = isset($itemRow['title']) ? trim((string) $itemRow['title']) : '';
        $previousDescription = isset($itemRow['description']) && $itemRow['description'] !== null ? trim((string) $itemRow['description']) : '';

        $safeTitle = taskEsc($connect, substr($title, 0, 255));
        $safeDescription = taskEsc($connect, substr($description, 0, 65535));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $updateSql = "UPDATE " . TASK_ITEM . " SET
                        title='" . $safeTitle . "',
                        description='" . $safeDescription . "',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                      WHERE id='" . $itemId . "' AND status='A'";

        if (!mysqli_query($connect, $updateSql)) {
            $fallbackUpdateSql = "UPDATE " . TASK_ITEM . " SET
                                title='" . $safeTitle . "',
                                update_by='" . $safeUser . "',
                                update_date='" . $safeDate . "',
                                update_time='" . $safeTime . "'
                              WHERE id='" . $itemId . "' AND status='A'";
            if (!mysqli_query($connect, $fallbackUpdateSql)) {
                return array('ok' => 0, 'message' => 'Failed to update work item.');
            }
        }

        if ($previousTitle !== $title) {
            taskLogItemHistory(
                $connect,
                $itemId,
                'update_field',
                'Title',
                $previousTitle,
                $title,
                'changed Title',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        if ($previousDescription !== $description) {
            taskLogItemHistory(
                $connect,
                $itemId,
                'update_field',
                'Description',
                $previousDescription,
                $description,
                'changed Description',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        return array(
            'ok' => 1,
            'message' => 'Work item updated successfully.',
            'item' => array(
                'id' => $itemId,
                'title' => $title,
                'description' => $description,
            ),
        );
    }
}

if (!function_exists('taskGetItemAttachments')) {
    function taskGetItemAttachments($connect, $itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array();
        }

        $rows = array();
        $sql = "SELECT id,file_name,file_path,file_size,mime_type,create_date,create_time
                FROM " . TASK_ITEM_ATTACHMENT . "
                WHERE item_id='" . $itemId . "' AND status='A'
                ORDER BY id DESC";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            return array();
        }

        while ($row = $rst->fetch_assoc()) {
            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'file_name' => isset($row['file_name']) ? (string) $row['file_name'] : '',
                'file_path' => isset($row['file_path']) ? (string) $row['file_path'] : '',
                'file_size' => isset($row['file_size']) ? (int) $row['file_size'] : 0,
                'mime_type' => isset($row['mime_type']) ? (string) $row['mime_type'] : '',
                'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
                'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            );
        }

        return $rows;
    }
}

if (!function_exists('taskSanitizeUploadFileName')) {
    function taskSanitizeUploadFileName($fileName)
    {
        $base = basename((string) $fileName);
        $base = str_replace(array(' ', "\t", "\r", "\n"), '_', $base);
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
        $base = trim((string) $base, '._-');

        if ($base === '') {
            return 'attachment_' . date('Ymd_His');
        }

        return substr($base, 0, 200);
    }
}

if (!function_exists('taskUploadItemAttachment')) {
    function taskUploadItemAttachment($connect, $itemId, $fileInfo, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid work item attachment request.');
        }

        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['error'])) {
            return array('ok' => 0, 'message' => 'No attachment uploaded.');
        }

        if ((int) $fileInfo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => 0, 'message' => 'Attachment upload failed.');
        }

        if (empty($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            return array('ok' => 0, 'message' => 'Invalid uploaded attachment.');
        }

        $itemSql = "SELECT id,project_key_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $projectKeyId = isset($itemRow['project_key_id']) ? (int) $itemRow['project_key_id'] : 0;
        $storageFolderId = $projectKeyId > 0 ? $projectKeyId : $itemId;

        $safeFileName = taskSanitizeUploadFileName(isset($fileInfo['name']) ? $fileInfo['name'] : '');
        $namePart = pathinfo($safeFileName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeFileName, PATHINFO_EXTENSION);
        $relativeDir = 'attachment/task_management/board/' . $storageFolderId;
        $absoluteDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
                return array('ok' => 0, 'message' => 'Failed to prepare attachment folder.');
            }
        }

        $finalFileName = $safeFileName;
        $counter = 1;
        while (file_exists($absoluteDir . DIRECTORY_SEPARATOR . $finalFileName)) {
            $suffix = '_' . $counter;
            $finalFileName = $namePart . $suffix . ($extPart !== '' ? '.' . $extPart : '');
            $counter++;
            if ($counter > 5000) {
                return array('ok' => 0, 'message' => 'Too many files with similar name.');
            }
        }

        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $finalFileName;
        if (!move_uploaded_file($fileInfo['tmp_name'], $absolutePath)) {
            return array('ok' => 0, 'message' => 'Failed to store uploaded attachment.');
        }

        $relativePath = $relativeDir . '/' . $finalFileName;
        $fileSize = isset($fileInfo['size']) ? (int) $fileInfo['size'] : 0;
        $mimeType = isset($fileInfo['type']) ? (string) $fileInfo['type'] : '';
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);
        $safeFileNameSql = taskEsc($connect, $finalFileName);
        $safeRelativePath = taskEsc($connect, $relativePath);
        $safeMimeType = taskEsc($connect, substr($mimeType, 0, 120));

        $insertSql = "INSERT INTO " . TASK_ITEM_ATTACHMENT . "
                        (item_id,file_name,file_path,file_size,mime_type,create_by,create_date,create_time,status)
                      VALUES
                        ('" . $itemId . "','" . $safeFileNameSql . "','" . $safeRelativePath . "','" . $fileSize . "','" . $safeMimeType . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            @unlink($absolutePath);
            return array('ok' => 0, 'message' => 'Failed saving attachment record. Please run insert_table.php first.');
        }

        taskLogItemHistory(
            $connect,
            $itemId,
            'add_attachment',
            'Attachment',
            '',
            $finalFileName,
            'added attachment',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array(
            'ok' => 1,
            'message' => 'Attachment uploaded successfully.',
            'attachment' => array(
                'id' => (int) mysqli_insert_id($connect),
                'file_name' => $finalFileName,
                'file_path' => $relativePath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
            ),
        );
    }
}

if (!function_exists('taskDeleteItemAttachment')) {
    function taskDeleteItemAttachment($connect, $attachmentId, $currentUserId, $cdate, $ctime)
    {
        $attachmentId = (int) $attachmentId;
        if ($attachmentId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid attachment delete request.');
        }

        $sql = "SELECT id,item_id,file_path,file_name FROM " . TASK_ITEM_ATTACHMENT . " WHERE id='" . $attachmentId . "' AND status='A' LIMIT 1";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            return array('ok' => 0, 'message' => 'Failed deleting attachment. Please run insert_table.php first.');
        }

        if ($rst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Attachment not found.');
        }

        $row = $rst->fetch_assoc();
        $itemId = isset($row['item_id']) ? (int) $row['item_id'] : 0;
        $filePath = isset($row['file_path']) ? (string) $row['file_path'] : '';
        $fileName = isset($row['file_name']) ? (string) $row['file_name'] : '';
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $ok = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_ATTACHMENT . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $attachmentId . "' AND status='A'"
        );

        if (!$ok) {
            return array('ok' => 0, 'message' => 'Failed deleting attachment.');
        }

        taskLogItemHistory(
            $connect,
            $itemId,
            'delete_attachment',
            'Attachment',
            $fileName,
            '',
            'removed attachment',
            $currentUserId,
            $cdate,
            $ctime
        );

        $normalizedPath = str_replace('\\', '/', trim((string) $filePath));
        if ($normalizedPath !== '') {
            $absolute = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedPath, '/'));
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        return array(
            'ok' => 1,
            'message' => 'Attachment removed successfully.',
            'item_id' => $itemId,
        );
    }
}

if (!function_exists('taskDeleteAllItemAttachments')) {
    function taskDeleteAllItemAttachments($connect, $itemId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid attachment delete request.');
        }

        $sql = "SELECT id,file_path,file_name FROM " . TASK_ITEM_ATTACHMENT . " WHERE item_id='" . $itemId . "' AND status='A'";
        $rst = mysqli_query($connect, $sql);
        if ($rst === false) {
            return array('ok' => 0, 'message' => 'Failed deleting attachments. Please run insert_table.php first.');
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $updated = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_ATTACHMENT . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE item_id='" . $itemId . "' AND status='A'"
        );

        if (!$updated) {
            return array('ok' => 0, 'message' => 'Failed deleting attachments.');
        }

        while ($row = $rst->fetch_assoc()) {
            $filePath = isset($row['file_path']) ? (string) $row['file_path'] : '';
            $fileName = isset($row['file_name']) ? (string) $row['file_name'] : '';
            if ($fileName !== '') {
                taskLogItemHistory(
                    $connect,
                    $itemId,
                    'delete_attachment',
                    'Attachment',
                    $fileName,
                    '',
                    'removed attachment',
                    $currentUserId,
                    $cdate,
                    $ctime
                );
            }
            $normalizedPath = str_replace('\\', '/', trim((string) $filePath));
            if ($normalizedPath === '') {
                continue;
            }

            $absolute = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normalizedPath, '/'));
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        return array(
            'ok' => 1,
            'message' => 'All attachments removed successfully.',
            'item_id' => $itemId,
        );
    }
}

if (!function_exists('taskCreateLabel')) {
    function taskCreateLabel($connect, $labelName, $currentUserId, $cdate, $ctime)
    {
        $labelName = trim((string) $labelName);
        if ($labelName === '') {
            return array('ok' => 0, 'message' => 'Label name is required.');
        }

        $safeName = taskEsc($connect, substr($labelName, 0, 120));
        $existingRst = mysqli_query($connect, "SELECT id,status FROM " . TASK_LABEL . " WHERE LOWER(name)=LOWER('" . $safeName . "') LIMIT 1");
        if ($existingRst && $existingRst->num_rows > 0) {
            $existing = $existingRst->fetch_assoc();
            $labelId = (int) $existing['id'];
            if ((string) $existing['status'] !== 'A') {
                $safeUser = taskEsc($connect, $currentUserId);
                $safeDate = taskEsc($connect, $cdate);
                $safeTime = taskEsc($connect, $ctime);
                mysqli_query($connect, "UPDATE " . TASK_LABEL . " SET status='A', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $labelId . "'");
            }

            return array('ok' => 1, 'message' => 'Label ready.', 'label' => array('id' => $labelId, 'name' => $labelName));
        }

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_LABEL . " WHERE status='A'");
        $sortOrder = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $sortOrder = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_LABEL . " (name,sort_order,create_by,create_date,create_time,status)
                      VALUES ('" . $safeName . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create label.');
        }

        return array(
            'ok' => 1,
            'message' => 'Label created successfully.',
            'label' => array(
                'id' => (int) mysqli_insert_id($connect),
                'name' => $labelName,
            ),
        );
    }
}

if (!function_exists('taskDeleteLabel')) {
    function taskDeleteLabel($connect, $labelId, $currentUserId, $cdate, $ctime)
    {
        $labelId = (int) $labelId;
        if ($labelId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid label delete request.');
        }

        $rst = mysqli_query($connect, "SELECT id FROM " . TASK_LABEL . " WHERE id='" . $labelId . "' AND status='A' LIMIT 1");
        if ($rst === false) {
            return array('ok' => 0, 'message' => 'Failed to delete label. Please run insert_table.php first.');
        }
        if ($rst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Label not found.');
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);
        $okLabel = mysqli_query(
            $connect,
            "UPDATE " . TASK_LABEL . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $labelId . "' AND status='A'"
        );

        $okMap = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_LABEL . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE label_id='" . $labelId . "' AND status='A'"
        );

        if (!$okLabel || !$okMap) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to delete label.');
        }

        mysqli_commit($connect);
        return array('ok' => 1, 'message' => 'Label deleted successfully.');
    }
}

if (!function_exists('taskDeleteStatusLabel')) {
    function taskDeleteStatusLabel($connect, $statusLabelId, $currentUserId, $cdate, $ctime)
    {
        $statusLabelId = (int) $statusLabelId;
        if ($statusLabelId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid task status label delete request.');
        }

        $rst = mysqli_query($connect, "SELECT id,name FROM " . TASK_STATUS_LABEL . " WHERE id='" . $statusLabelId . "' AND status='A' LIMIT 1");
        if ($rst === false) {
            return array('ok' => 0, 'message' => 'Failed to delete task status label. Please run insert_table.php first.');
        }
        if ($rst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Task status label not found.');
        }

        $row = $rst->fetch_assoc();
        $statusName = isset($row['name']) ? trim((string) $row['name']) : '';
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);

        $okStatus = mysqli_query(
            $connect,
            "UPDATE " . TASK_STATUS_LABEL . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $statusLabelId . "' AND status='A'"
        );

        $okItem = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                task_status=TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(task_status, ' ', ''), ','), '," . $statusLabelId . ",', ',')),
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE status='A' AND FIND_IN_SET('" . $statusLabelId . "', REPLACE(task_status, ' ', '')) > 0"
        );

        if ($okItem && $statusName !== '') {
            $safeStatusName = taskEsc($connect, $statusName);
            $okItem = mysqli_query(
                $connect,
                "UPDATE " . TASK_ITEM . " SET
                    task_status='',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                 WHERE task_status='" . $safeStatusName . "' AND status='A'"
            );
        }

        if (!$okStatus || !$okItem) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to delete task status label.');
        }

        mysqli_commit($connect);
        return array('ok' => 1, 'message' => 'Task status label deleted successfully.');
    }
}

if (!function_exists('taskGetItemLabels')) {
    function taskGetItemLabels($connect, $itemId)
    {
        $rows = array();
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return $rows;
        }

        $sql = "SELECT l.id, l.name
                FROM " . TASK_ITEM_LABEL . " il
                INNER JOIN " . TASK_LABEL . " l ON l.id = il.label_id AND l.status='A'
                WHERE il.status='A' AND il.item_id='" . $itemId . "'
                ORDER BY l.name ASC";
        $rst = mysqli_query($connect, $sql);
        if ($rst) {
            while ($row = $rst->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskAssignItemLabels')) {
    function taskAssignItemLabels($connect, $itemId, $labelIds, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid label request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $labelIds = array_values(array_unique(array_map('intval', (array) $labelIds)));
        $labelIds = array_filter($labelIds, function ($id) {
            return $id > 0;
        });

        $previousLabels = taskGetItemLabels($connect, $itemId);
        $previousLabelNames = array();
        foreach ($previousLabels as $previousLabel) {
            $name = isset($previousLabel['name']) ? trim((string) $previousLabel['name']) : '';
            if ($name !== '') {
                $previousLabelNames[] = $name;
            }
        }
        sort($previousLabelNames);

        $validLabelIds = array();
        if (!empty($labelIds)) {
            $idSql = implode(',', $labelIds);
            $labelRst = mysqli_query($connect, "SELECT id FROM " . TASK_LABEL . " WHERE status='A' AND id IN (" . $idSql . ")");
            if ($labelRst) {
                while ($row = $labelRst->fetch_assoc()) {
                    $validLabelIds[] = (int) $row['id'];
                }
            }
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);
        $ok = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_LABEL . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE item_id='" . $itemId . "' AND status='A'"
        );

        if (!$ok) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to save labels.');
        }

        foreach ($validLabelIds as $labelId) {
            $checkRst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM_LABEL . " WHERE item_id='" . $itemId . "' AND label_id='" . (int) $labelId . "' LIMIT 1");
            if ($checkRst && $checkRst->num_rows > 0) {
                $row = $checkRst->fetch_assoc();
                $mapId = (int) $row['id'];
                $ok = mysqli_query(
                    $connect,
                    "UPDATE " . TASK_ITEM_LABEL . " SET
                        status='A',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                     WHERE id='" . $mapId . "'"
                );
            } else {
                $ok = mysqli_query(
                    $connect,
                    "INSERT INTO " . TASK_ITEM_LABEL . " (item_id,label_id,create_by,create_date,create_time,status)
                     VALUES ('" . $itemId . "','" . (int) $labelId . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
                );
            }

            if (!$ok) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to save labels.');
            }
        }

        mysqli_commit($connect);

        $updatedLabels = taskGetItemLabels($connect, $itemId);
        $updatedLabelNames = array();
        foreach ($updatedLabels as $updatedLabel) {
            $name = isset($updatedLabel['name']) ? trim((string) $updatedLabel['name']) : '';
            if ($name !== '') {
                $updatedLabelNames[] = $name;
            }
        }
        sort($updatedLabelNames);

        if (implode(',', $previousLabelNames) !== implode(',', $updatedLabelNames)) {
            taskLogItemHistory(
                $connect,
                $itemId,
                'update_field',
                'Labels',
                implode(', ', $previousLabelNames),
                implode(', ', $updatedLabelNames),
                'changed Labels',
                $currentUserId,
                $cdate,
                $ctime
            );
        }

        return array('ok' => 1, 'message' => 'Labels updated successfully.', 'labels' => $updatedLabels);
    }
}

if (!function_exists('taskDeleteItem')) {
    function taskDeleteItem($connect, $itemId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid delete request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,column_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $item = $itemRst->fetch_assoc();
        $columnId = (int) $item['column_id'];
        $itemTitle = isset($item['title']) ? (string) $item['title'] : '';

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);
        $okItem = mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $itemId . "' AND status='A'");
        $okLabels = mysqli_query($connect, "UPDATE " . TASK_ITEM_LABEL . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE item_id='" . $itemId . "' AND status='A'");

        if (!$okItem || !$okLabels) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to delete work item.');
        }

        taskResequenceItemsInColumn($connect, $columnId);
        mysqli_commit($connect);

        taskLogItemHistory(
            $connect,
            $itemId,
            'delete_item',
            'Work item',
            $itemTitle,
            '',
            'deleted the Work item',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array('ok' => 1, 'message' => 'Work item deleted successfully.');
    }
}
