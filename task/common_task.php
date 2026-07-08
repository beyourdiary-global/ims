<?php

if (defined('ROOT')) {
    include_once ROOT . '/include/system_alert_common.php';
}

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
        // Use mb_strcut to safely truncate bytes without corrupting UTF-8 characters
        $safeEventType = taskEsc($connect, mb_strcut($eventType, 0, 80, 'UTF-8'));
        $safeFieldName = taskEsc($connect, mb_strcut(trim((string) $fieldName), 0, 120, 'UTF-8'));
        $safeFromValue = taskEsc($connect, mb_strcut(taskNormalizeHistoryValue($fromValue), 0, 65535, 'UTF-8'));
        $safeToValue = taskEsc($connect, mb_strcut(taskNormalizeHistoryValue($toValue), 0, 65535, 'UTF-8'));
        $safeRemark = taskEsc($connect, mb_strcut(trim((string) $remark), 0, 65535, 'UTF-8'));
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
        $historyRows = array();
        $actorIds = array();
        $sql = "SELECT id,event_type,field_name,from_value,to_value,remark,create_by,create_date,create_time
            FROM " . TASK_ITEM_HISTORY . "
            WHERE item_id='" . $itemId . "' AND status='A'
            ORDER BY id DESC
            LIMIT " . $limit;

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $historyRows[] = $row;
            $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
        }

        $actorMap = taskFetchUserDisplayMap($connect, $actorIds, false);

        foreach ($historyRows as $row) {
            $eventType = isset($row['event_type']) ? trim((string) $row['event_type']) : '';
            if ($eventType === 'comment' || in_array($eventType, array('worklog_saved', 'update_worklog', 'delete_worklog'), true)) {
                continue;
            }
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

            $createById = isset($row['create_by']) ? (int) $row['create_by'] : 0;
            $actorName = isset($actorMap[$createById]) ? (string) $actorMap[$createById] : 'User';
            $fromValue = isset($row['from_value']) ? (string) $row['from_value'] : '';
            $toValue = isset($row['to_value']) ? (string) $row['to_value'] : '';

            if ($fieldName === 'Task Status') {
                $fromSelection = taskResolveStatusLabelSelection($connect, $fromValue);
                $toSelection = taskResolveStatusLabelSelection($connect, $toValue);
                $fromNames = array();
                $toNames = array();

                foreach (isset($fromSelection['labels']) && is_array($fromSelection['labels']) ? $fromSelection['labels'] : array() as $label) {
                    $labelName = isset($label['name']) ? trim((string) $label['name']) : '';
                    if ($labelName !== '') {
                        $fromNames[] = $labelName;
                    }
                }

                foreach (isset($toSelection['labels']) && is_array($toSelection['labels']) ? $toSelection['labels'] : array() as $label) {
                    $labelName = isset($label['name']) ? trim((string) $label['name']) : '';
                    if ($labelName !== '') {
                        $toNames[] = $labelName;
                    }
                }

                $fromValue = !empty($fromNames) ? implode(', ', $fromNames) : ($fromValue !== '' ? $fromValue : 'None');
                $toValue = !empty($toNames) ? implode(', ', $toNames) : ($toValue !== '' ? $toValue : 'None');
            } elseif ($fieldName === 'Assignee' || $fieldName === 'Reporter') {
                if (preg_match('/^User\\s*#(\\d+)$/i', trim($fromValue), $fromMatches)) {
                    $fromValue = taskFormatHistoryUserValue($connect, (int) $fromMatches[1]);
                }
                if (preg_match('/^User\\s*#(\\d+)$/i', trim($toValue), $toMatches)) {
                    $toValue = taskFormatHistoryUserValue($connect, (int) $toMatches[1]);
                }
                $fromValue = $fromValue !== '' ? $fromValue : 'Unassigned';
                $toValue = $toValue !== '' ? $toValue : 'Unassigned';
            }

            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'event_type' => $eventType,
                'field_name' => $fieldName,
                'from_value' => $fromValue,
                'to_value' => $toValue,
                'remark' => $remark,
                'actor_name' => $actorName,
                'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
                'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
                'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
            );
        }

        return $rows;
    }
}

if (!function_exists('taskSanitizeCommentHtml')) {
    function taskSanitizeCommentHtml($html)
    {
        $value = trim((string) $html);
        if ($value === '') {
            return '';
        }

        $value = str_replace("\0", '', $value);
        $allowedTags = '<p><br><strong><b><em><i><u><s><strike><sub><sup><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><span><img>';
        $value = strip_tags($value, $allowedTags);

        $value = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $value);
        $value = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $value);
        $value = preg_replace_callback(
            '/\sstyle\s*=\s*("|\')([^"\']*)\1/i',
            function ($matches) {
                $styleValue = isset($matches[2]) ? (string) $matches[2] : '';
                $declarations = preg_split('/\s*;\s*/', $styleValue);
                foreach ($declarations as $declaration) {
                    if (!preg_match('/^\s*color\s*:\s*(#[0-9a-f]{3,8}|(?:rgb|hsl)a?\([^)]{1,100}\)|[a-z]{1,30})\s*$/i', $declaration, $colorMatch)) {
                        continue;
                    }

                    return ' style="color:' . trim((string) $colorMatch[1]) . '"';
                }

                return '';
            },
            $value
        );
        $value = preg_replace('/href\s*=\s*"\s*javascript:[^"]*"/i', 'href="#"', $value);
        $value = preg_replace("/href\s*=\s*'\s*javascript:[^']*'/i", "href='#'", $value);
        $value = preg_replace('/src\s*=\s*"\s*javascript:[^"]*"/i', 'src=""', $value);
        $value = preg_replace("/src\s*=\s*'\s*javascript:[^']*'/i", "src=''", $value);

        return trim((string) $value);
    }
}

if (!function_exists('taskBuildCommentPlainText')) {
    function taskBuildCommentPlainText($html)
    {
        $text = trim((string) html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('taskNormalizeWorklogDate')) {
    function taskNormalizeWorklogDate($value, $fallback = '')
    {
        $text = trim((string) $value);
        if ($text === '') {
            return $fallback !== '' ? $fallback : date('Y-m-d');
        }

        $dt = DateTime::createFromFormat('Y-m-d', $text);
        if ($dt && $dt->format('Y-m-d') === $text) {
            return $text;
        }

        $ts = strtotime($text);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return $fallback !== '' ? $fallback : date('Y-m-d');
    }
}

if (!function_exists('taskNormalizeBoardDate')) {
    function taskNormalizeBoardDate($value, $fallback = '')
    {
        $text = trim((string) $value);
        if ($text === '') {
            return $fallback;
        }

        foreach (array('Y-m-d', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y') as $format) {
            $dt = DateTime::createFromFormat('!' . $format, $text);
            if ($dt instanceof DateTime && $dt->format($format) === $text) {
                return $dt->format('Y-m-d');
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $text)) {
            return substr($text, 0, 10);
        }

        $timestamp = strtotime($text);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $fallback;
    }
}

if (!function_exists('taskNormalizeWorklogTime')) {
    function taskNormalizeWorklogTime($value, $fallback = '')
    {
        $text = trim((string) $value);
        if ($text === '') {
            return $fallback !== '' ? $fallback : date('H:i:s');
        }

        foreach (array('H:i:s', 'H:i', 'g:i A', 'g:i a') as $format) {
            $dt = DateTime::createFromFormat($format, $text);
            if ($dt instanceof DateTime) {
                return $dt->format('H:i:s');
            }
        }

        $ts = strtotime($text);
        if ($ts !== false) {
            return date('H:i:s', $ts);
        }

        return $fallback !== '' ? $fallback : date('H:i:s');
    }
}

if (!function_exists('taskNormalizeEditorAttachmentPath')) {
    function taskNormalizeEditorAttachmentPath($value)
    {
        $path = trim((string) html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'));
        if ($path === '') {
            return '';
        }

        $siteUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        if ($siteUrl !== '' && stripos($path, $siteUrl . '/') === 0) {
            $path = substr($path, strlen($siteUrl) + 1);
        } else {
            $urlPath = parse_url($path, PHP_URL_PATH);
            if (is_string($urlPath) && $urlPath !== '') {
                $path = $urlPath;
            }
        }

        $path = rawurldecode((string) $path);
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        $markerPos = stripos($path, 'attachment/board/');
        if ($markerPos === false) {
            return '';
        }

        $path = substr($path, $markerPos);
        if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
            return '';
        }

        return $path;
    }
}

if (!function_exists('taskEditorAttachmentPathAllowed')) {
    function taskEditorAttachmentPathAllowed($path, $allowedPrefixes)
    {
        $path = (string) $path;
        $allowedPrefixes = is_array($allowedPrefixes) ? $allowedPrefixes : array();
        if (empty($allowedPrefixes)) {
            return true;
        }

        foreach ($allowedPrefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix !== '' && strpos($path, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('taskExtractEditorAttachmentPathsFromHtml')) {
    function taskExtractEditorAttachmentPathsFromHtml($html, $allowedPrefixes = array())
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return array();
        }

        $matches = array();
        preg_match_all('/\b(?:href|src)\s*=\s*(["\'])(.*?)\1/i', $html, $matches);
        $values = isset($matches[2]) && is_array($matches[2]) ? $matches[2] : array();
        $paths = array();

        foreach ($values as $value) {
            $path = taskNormalizeEditorAttachmentPath($value);
            if ($path === '' || !taskEditorAttachmentPathAllowed($path, $allowedPrefixes)) {
                continue;
            }

            $paths[$path] = $path;
        }

        return array_values($paths);
    }
}

if (!function_exists('taskNormalizeEditorAttachmentPathList')) {
    function taskNormalizeEditorAttachmentPathList($paths, $allowedPrefixes = array())
    {
        if (!is_array($paths)) {
            $paths = explode(',', (string) $paths);
        }

        $result = array();
        foreach ($paths as $pathValue) {
            $path = taskNormalizeEditorAttachmentPath($pathValue);
            if ($path === '' || !taskEditorAttachmentPathAllowed($path, $allowedPrefixes)) {
                continue;
            }
            $result[$path] = $path;
        }

        return array_values($result);
    }
}

if (!function_exists('taskDeleteEditorAttachmentFileByPath')) {
    function taskDeleteEditorAttachmentFileByPath($filePath)
    {
        $path = taskNormalizeEditorAttachmentPath($filePath);
        if ($path === '' || !defined('ROOT')) {
            return;
        }

        $rootPath = rtrim((string) ROOT, '/\\');
        $rootRealPath = realpath($rootPath);
        $absolutePath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $fileRealPath = realpath($absolutePath);

        if (!$rootRealPath || !$fileRealPath || !is_file($fileRealPath)) {
            return;
        }

        $rootRealPath = rtrim(str_replace('\\', '/', $rootRealPath), '/') . '/';
        $fileRealPathCheck = str_replace('\\', '/', $fileRealPath);
        if (strpos($fileRealPathCheck, $rootRealPath) !== 0) {
            return;
        }

        @unlink($fileRealPath);
    }
}

if (!function_exists('taskDeleteRemovedEditorAttachments')) {
    function taskDeleteRemovedEditorAttachments($connect, $itemId, $previousHtml, $currentHtml, $currentUserId, $cdate, $ctime, $allowedPrefixes = array(), $trackedAttachmentPaths = array())
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return;
        }

        $previousPaths = taskExtractEditorAttachmentPathsFromHtml($previousHtml, $allowedPrefixes);
        $trackedPaths = taskNormalizeEditorAttachmentPathList($trackedAttachmentPaths, $allowedPrefixes);
        foreach ($trackedPaths as $trackedPath) {
            $previousPaths[$trackedPath] = $trackedPath;
        }

        if (empty($previousPaths)) {
            return;
        }

        $currentPaths = taskExtractEditorAttachmentPathsFromHtml($currentHtml, $allowedPrefixes);
        $currentPathMap = array();
        foreach ($currentPaths as $currentPath) {
            $currentPathMap[$currentPath] = true;
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        foreach ($previousPaths as $previousPath) {
            if (isset($currentPathMap[$previousPath])) {
                continue;
            }

            $safePath = taskEsc($connect, $previousPath);
            $shouldDeleteFile = false;
            if (defined('TASK_ITEM_ATTACHMENT')) {
                $deleteRst = mysqli_query(
                    $connect,
                    "UPDATE " . TASK_ITEM_ATTACHMENT . " SET
                        status='D',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                     WHERE item_id='" . $itemId . "' AND file_path='" . $safePath . "' AND status='A'"
                );
                $shouldDeleteFile = $deleteRst && mysqli_affected_rows($connect) > 0;
            }

            if ($shouldDeleteFile) {
                taskDeleteEditorAttachmentFileByPath($previousPath);
            }
        }
    }
}

if (!function_exists('taskExtractMentionedUserIdsFromHtml')) {
    function taskExtractMentionedUserIdsFromHtml($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return array();
        }

        $matches = array();
        preg_match_all('/\bdata-mention-user-id\s*=\s*(["\']?)(\d+)\1/i', $html, $matches);
        $ids = isset($matches[2]) && is_array($matches[2]) ? $matches[2] : array();

        return taskUniquePositiveIntIds($ids);
    }
}

if (!function_exists('taskBuildWorkItemActionUrl')) {
    function taskBuildWorkItemActionUrl($projectId, $itemId)
    {
        $itemId = (int) $itemId;
        $projectId = (int) $projectId;
        if ($itemId <= 0) {
            return '';
        }

        $path = ROUTE_TASK_BOARD;
        $params = array();
        if ($projectId > 0) {
            $params['project_id'] = $projectId;
        }

        return siteUrlWithQuery($path, $params, 'task-item-' . $itemId);
    }
}

if (!function_exists('taskBuildProjectBoardUrl')) {
    function taskBuildProjectBoardUrl($projectId)
    {
        $projectId = (int) $projectId;
        $params = array();
        if ($projectId > 0) {
            $params['project_id'] = $projectId;
        }

        return siteUrlWithQuery(ROUTE_TASK_BOARD, $params);
    }
}

if (!function_exists('taskFormatDigestDate')) {
    function taskFormatDigestDate($dateValue)
    {
        $dateValue = taskNormalizeBoardDate($dateValue, trim((string) $dateValue));
        if ($dateValue === '') {
            return '';
        }

        $timestamp = strtotime($dateValue);
        if ($timestamp === false) {
            return $dateValue;
        }

        return date('j M Y', $timestamp);
    }
}

if (!function_exists('taskFormatDigestDateTime')) {
    function taskFormatDigestDateTime($dateValue, $timeValue = '')
    {
        $dateValue = taskNormalizeBoardDate($dateValue, trim((string) $dateValue));
        $timeValue = trim((string) $timeValue);
        if ($dateValue === '') {
            return '';
        }

        $combined = $dateValue;
        if ($timeValue !== '') {
            $combined .= ' ' . $timeValue;
        }

        $timestamp = strtotime($combined);
        if ($timestamp === false) {
            return trim($dateValue . ($timeValue !== '' ? ' ' . $timeValue : ''));
        }

        return date('j M Y g:i A', $timestamp);
    }
}

if (!function_exists('taskGetDueDigestReferenceDate')) {
    function taskGetDueDigestReferenceDate($referenceDate = '')
    {
        $referenceDate = taskNormalizeBoardDate($referenceDate);
        if ($referenceDate !== '') {
            return $referenceDate;
        }

        return date('Y-m-d');
    }
}

if (!function_exists('taskGetDueDigestProjectLastSortMap')) {
    function taskGetDueDigestProjectLastSortMap($connect, $projectIds)
    {
        $map = array();
        $projectIds = taskUniquePositiveIntIds($projectIds);
        if (empty($projectIds)) {
            return $map;
        }

        $sql = "SELECT project_id, MAX(sort_order) AS max_sort_order
                FROM " . TASK_COLUMN . "
                WHERE status='A' AND project_id IN (" . implode(',', $projectIds) . ")
                GROUP BY project_id";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            if ($projectId <= 0) {
                continue;
            }

            $map[$projectId] = isset($row['max_sort_order']) ? (int) $row['max_sort_order'] : 0;
        }

        return $map;
    }
}

if (!function_exists('taskBuildDueDigestEmailJobs')) {
    function taskBuildDueDigestEmailJobs($connect, $referenceDate = '')
    {
        $jobs = array();
        $referenceDate = taskGetDueDigestReferenceDate($referenceDate);
        if (!($connect instanceof mysqli)) {
            return array(
                'reference_date' => $referenceDate,
                'jobs' => array(),
                'matched_item_count' => 0,
                'eligible_item_count' => 0,
                'skipped_item_count' => 0,
            );
        }

        $taskItemHasProjectKeyId = function_exists('siColumnExistsByName') ? siColumnExistsByName($connect, TASK_ITEM, 'project_key_id') : true;
        $taskItemHasReporterUserId = function_exists('siColumnExistsByName') ? siColumnExistsByName($connect, TASK_ITEM, 'reporter_user_id') : true;
        $taskItemHasUpdateDate = function_exists('siColumnExistsByName') ? siColumnExistsByName($connect, TASK_ITEM, 'update_date') : true;
        $taskItemHasUpdateTime = function_exists('siColumnExistsByName') ? siColumnExistsByName($connect, TASK_ITEM, 'update_time') : true;
        $taskItemHasCreateTime = function_exists('siColumnExistsByName') ? siColumnExistsByName($connect, TASK_ITEM, 'create_time') : true;

        $sql = "SELECT
                    id,
                    project_id,
                    " . ($taskItemHasProjectKeyId ? 'project_key_id' : '0 AS project_key_id') . ",
                    column_id,
                    title,
                    due_date,
                    assignee_user_id,
                    " . ($taskItemHasReporterUserId ? 'reporter_user_id' : '0 AS reporter_user_id') . ",
                    create_date,
                    " . ($taskItemHasCreateTime ? 'create_time' : "'' AS create_time") . ",
                    " . ($taskItemHasUpdateDate ? 'update_date' : "'' AS update_date") . ",
                    " . ($taskItemHasUpdateTime ? 'update_time' : "'' AS update_time") . "
                FROM " . TASK_ITEM . "
                WHERE status='A'
                  AND assignee_user_id > 0
                  AND due_date IS NOT NULL
                  AND TRIM(CAST(due_date AS CHAR)) <> ''
                ORDER BY project_id ASC, assignee_user_id ASC, due_date ASC, id ASC";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return array(
                'reference_date' => $referenceDate,
                'jobs' => array(),
                'matched_item_count' => 0,
                'eligible_item_count' => 0,
                'skipped_item_count' => 0,
            );
        }

        $rows = array();
        $projectIds = array();
        $assigneeIds = array();
        $reporterIds = array();
        $projectKeyIds = array();
        $columnIds = array();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            $projectIds[] = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $assigneeIds[] = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
            $reporterIds[] = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
            $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $columnIds[] = isset($row['column_id']) ? (int) $row['column_id'] : 0;
        }

        $projectIds = taskUniquePositiveIntIds($projectIds);
        $assigneeIds = taskUniquePositiveIntIds($assigneeIds);
        $reporterIds = taskUniquePositiveIntIds($reporterIds);
        $projectKeyIds = taskUniquePositiveIntIds($projectKeyIds);
        $columnIds = taskUniquePositiveIntIds($columnIds);

        if (empty($rows) || empty($projectIds) || empty($assigneeIds)) {
            return array(
                'reference_date' => $referenceDate,
                'jobs' => array(),
                'matched_item_count' => count($rows),
                'eligible_item_count' => 0,
                'skipped_item_count' => count($rows),
            );
        }

        $projectLastSortMap = taskGetDueDigestProjectLastSortMap($connect, $projectIds);
        $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, true);
        $columnMap = taskFetchColumnInfoMap($connect, $columnIds, true);
        $assigneeNameMap = taskFetchUserDisplayMap($connect, $assigneeIds, true);
        $reporterNameMap = taskFetchUserDisplayMap($connect, $reporterIds, false);
        $projectNameMap = array();

        $projectResult = mysqli_query(
            $connect,
            "SELECT id, name
             FROM " . TASK_PROJECT . "
             WHERE status='A'
               AND id IN (" . implode(',', $projectIds) . ")"
        );
        if ($projectResult) {
            while ($projectRow = $projectResult->fetch_assoc()) {
                $projectId = isset($projectRow['id']) ? (int) $projectRow['id'] : 0;
                if ($projectId <= 0) {
                    continue;
                }

                $projectNameMap[$projectId] = isset($projectRow['name']) ? trim((string) $projectRow['name']) : '';
            }
        }

        $projectKeySettingMap = array();
        foreach ($projectIds as $projectId) {
            $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
            $projectKeySettingMap[$projectId] = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        }

        $assigneeEmailMap = array();
        $emailResult = mysqli_query(
            $connect,
            "SELECT id, email
             FROM " . USR_USER . "
             WHERE status='A'
               AND id IN (" . implode(',', $assigneeIds) . ")"
        );
        if ($emailResult) {
            while ($emailRow = $emailResult->fetch_assoc()) {
                $userId = isset($emailRow['id']) ? (int) $emailRow['id'] : 0;
                $email = trim((string) (isset($emailRow['email']) ? $emailRow['email'] : ''));
                if ($userId > 0 && $email !== '' && (function_exists('isEmail') ? isEmail($email) : filter_var($email, FILTER_VALIDATE_EMAIL))) {
                    $assigneeEmailMap[$userId] = $email;
                }
            }
        }

        $moduleAccessCache = array();
        $projectAccessCache = array();
        $matchedItemCount = 0;
        $eligibleItemCount = 0;

        foreach ($rows as $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
            if ($itemId <= 0 || $projectId <= 0 || $assigneeUserId <= 0) {
                continue;
            }

            $columnId = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            if (!isset($projectNameMap[$projectId]) || !isset($columnMap[$columnId])) {
                continue;
            }

            $columnName = isset($columnMap[$columnId]['name']) ? trim((string) $columnMap[$columnId]['name']) : '';
            $columnSortOrder = isset($columnMap[$columnId]['sort_order']) ? (int) $columnMap[$columnId]['sort_order'] : 0;
            $projectLastSortOrder = isset($projectLastSortMap[$projectId]) ? (int) $projectLastSortMap[$projectId] : 0;
            $isDone = taskIsDoneColumnName($columnName)
                || ($projectLastSortOrder > 0 && $columnSortOrder >= $projectLastSortOrder);
            if ($isDone) {
                continue;
            }

            $dueDate = taskNormalizeBoardDate(isset($row['due_date']) ? $row['due_date'] : '');
            if ($dueDate === '' || $dueDate > $referenceDate) {
                continue;
            }

            $matchedItemCount++;

            if (!isset($assigneeEmailMap[$assigneeUserId])) {
                continue;
            }

            if (!array_key_exists($assigneeUserId, $moduleAccessCache)) {
                $moduleAccessCache[$assigneeUserId] = function_exists('systemAlertUserHasAccessToModule')
                    ? systemAlertUserHasAccessToModule($connect, $assigneeUserId, 'project_task')
                    : true;
            }
            if (empty($moduleAccessCache[$assigneeUserId])) {
                continue;
            }

            $projectAccessKey = $projectId . ':' . $assigneeUserId;
            if (!array_key_exists($projectAccessKey, $projectAccessCache)) {
                $projectAccessCache[$projectAccessKey] = taskHasAnyProjectAccess($connect, $projectId, $assigneeUserId);
            }
            if (empty($projectAccessCache[$projectAccessKey])) {
                continue;
            }

            $projectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $projectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
            if ($projectKey === '') {
                $projectKey = taskNormalizeProjectKey(isset($projectKeySettingMap[$projectId]) ? $projectKeySettingMap[$projectId] : '');
            }
            $reporterUserId = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
            $lastUpdateDate = trim((string) (isset($row['update_date']) ? $row['update_date'] : ''));
            $lastUpdateTime = trim((string) (isset($row['update_time']) ? $row['update_time'] : ''));
            if ($lastUpdateDate === '') {
                $lastUpdateDate = trim((string) (isset($row['create_date']) ? $row['create_date'] : ''));
            }
            if ($lastUpdateTime === '') {
                $lastUpdateTime = trim((string) (isset($row['create_time']) ? $row['create_time'] : ''));
            }

            $jobKey = $projectId . ':' . $assigneeUserId;
            if (!isset($jobs[$jobKey])) {
                $assigneeName = isset($assigneeNameMap[$assigneeUserId]) ? trim((string) $assigneeNameMap[$assigneeUserId]) : '';
                if ($assigneeName === '') {
                    $assigneeName = 'User #' . $assigneeUserId;
                }

                $projectName = isset($projectNameMap[$projectId]) ? trim((string) $projectNameMap[$projectId]) : '';
                $jobs[$jobKey] = array(
                    'job_key' => $jobKey,
                    'project_id' => $projectId,
                    'project_name' => $projectName !== '' ? $projectName : ('Project #' . $projectId),
                    'assignee_user_id' => $assigneeUserId,
                    'assignee_name' => $assigneeName,
                    'assignee_email' => $assigneeEmailMap[$assigneeUserId],
                    'board_url' => taskBuildProjectBoardUrl($projectId),
                    'reference_date' => $referenceDate,
                    'items' => array(),
                    'overdue_count' => 0,
                    'due_today_count' => 0,
                );
            }

            $isOverdue = $dueDate !== '' && $dueDate < $referenceDate;
            if ($isOverdue) {
                $jobs[$jobKey]['overdue_count']++;
            } else {
                $jobs[$jobKey]['due_today_count']++;
            }

            $jobs[$jobKey]['items'][] = array(
                'id' => $itemId,
                'work_item_key' => taskBuildWorkItemKey($projectKey, $itemId),
                'title' => isset($row['title']) ? trim((string) $row['title']) : '',
                'due_date' => $dueDate,
                'due_date_label' => taskFormatDigestDate($dueDate),
                'reporter_name' => $reporterUserId > 0 && isset($reporterNameMap[$reporterUserId]) ? (string) $reporterNameMap[$reporterUserId] : '',
                'last_update_date' => $lastUpdateDate,
                'last_update_time' => $lastUpdateTime,
                'last_update_label' => taskFormatDigestDateTime($lastUpdateDate, $lastUpdateTime),
                'is_overdue' => $isOverdue ? 1 : 0,
                'action_url' => taskBuildWorkItemActionUrl($projectId, $itemId),
            );
            $eligibleItemCount++;
        }

        foreach ($jobs as $jobKey => $job) {
            if (empty($job['items'])) {
                unset($jobs[$jobKey]);
                continue;
            }

            usort($job['items'], function ($left, $right) {
                $leftDue = isset($left['due_date']) ? (string) $left['due_date'] : '';
                $rightDue = isset($right['due_date']) ? (string) $right['due_date'] : '';
                if ($leftDue !== $rightDue) {
                    return strcmp($leftDue, $rightDue);
                }

                $leftStamp = trim((string) (isset($left['last_update_date']) ? $left['last_update_date'] : '') . ' ' . (isset($left['last_update_time']) ? $left['last_update_time'] : ''));
                $rightStamp = trim((string) (isset($right['last_update_date']) ? $right['last_update_date'] : '') . ' ' . (isset($right['last_update_time']) ? $right['last_update_time'] : ''));
                if ($leftStamp !== $rightStamp) {
                    return strcmp($rightStamp, $leftStamp);
                }

                $leftId = isset($left['id']) ? (int) $left['id'] : 0;
                $rightId = isset($right['id']) ? (int) $right['id'] : 0;
                return $leftId <=> $rightId;
            });

            $job['item_count'] = count($job['items']);
            $job['subject'] = $job['assignee_name'] . ', you have work due in CMS ' . $job['project_name'];
            $jobs[$jobKey] = $job;
        }

        return array(
            'reference_date' => $referenceDate,
            'jobs' => array_values($jobs),
            'matched_item_count' => $matchedItemCount,
            'eligible_item_count' => $eligibleItemCount,
            'skipped_item_count' => max(0, $matchedItemCount - $eligibleItemCount),
        );
    }
}

if (!function_exists('taskBuildDueDigestEmailSummaryText')) {
    function taskBuildDueDigestEmailSummaryText($itemCount, $overdueCount, $dueTodayCount)
    {
        $itemCount = (int) $itemCount;
        $overdueCount = (int) $overdueCount;
        $dueTodayCount = (int) $dueTodayCount;

        $parts = array();
        if ($overdueCount > 0) {
            $parts[] = $overdueCount . ' overdue';
        }
        if ($dueTodayCount > 0) {
            $parts[] = $dueTodayCount . ' due today';
        }
        $baseLabel = $itemCount . ' work item' . ($itemCount === 1 ? '' : 's');
        if (empty($parts)) {
            return $baseLabel . ' due';
        }

        return $baseLabel . ' (' . implode(' and ', $parts) . ')';
    }
}

if (!function_exists('taskBuildDueDigestEmailHtml')) {
    function taskBuildDueDigestEmailHtml($job)
    {
        $assigneeName = trim((string) (isset($job['assignee_name']) ? $job['assignee_name'] : ''));
        $projectName = trim((string) (isset($job['project_name']) ? $job['project_name'] : ''));
        $boardUrl = trim((string) (isset($job['board_url']) ? $job['board_url'] : ''));
        $referenceDate = taskGetDueDigestReferenceDate(isset($job['reference_date']) ? $job['reference_date'] : '');
        $itemCount = isset($job['item_count']) ? (int) $job['item_count'] : 0;
        $overdueCount = isset($job['overdue_count']) ? (int) $job['overdue_count'] : 0;
        $dueTodayCount = isset($job['due_today_count']) ? (int) $job['due_today_count'] : 0;
        $summaryText = taskBuildDueDigestEmailSummaryText($itemCount, $overdueCount, $dueTodayCount);
        $safeAssigneeName = htmlspecialchars($assigneeName !== '' ? $assigneeName : 'there', ENT_QUOTES, 'UTF-8');
        $safeProjectName = htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8');

        $rowsHtml = '';
        foreach ((array) (isset($job['items']) ? $job['items'] : array()) as $item) {
            $actionUrl = isset($item['action_url']) ? (string) $item['action_url'] : '';
            $workItemKey = htmlspecialchars((string) (isset($item['work_item_key']) ? $item['work_item_key'] : ''), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) (isset($item['title']) ? $item['title'] : ''), ENT_QUOTES, 'UTF-8');
            $dueDateLabel = htmlspecialchars((string) (isset($item['due_date_label']) ? $item['due_date_label'] : ''), ENT_QUOTES, 'UTF-8');
            $reporterName = trim((string) (isset($item['reporter_name']) ? $item['reporter_name'] : ''));
            $lastUpdateLabel = htmlspecialchars((string) (isset($item['last_update_label']) ? $item['last_update_label'] : ''), ENT_QUOTES, 'UTF-8');
            $dueStyle = !empty($item['is_overdue']) ? 'color:#C62828;font-weight:700;' : 'color:#B26A00;font-weight:600;';
            $safeUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
            $reporterDisplay = $reporterName !== '' ? htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8') : '-';

            $rowsHtml .= '
                <tr>
                    <td style="padding:14px 12px;border-bottom:1px solid #E5E7EB;vertical-align:top;">
                        <div style="font-size:12px;font-weight:700;line-height:1.4;margin:0 0 6px;">
                            <a href="' . $safeUrl . '" style="color:#0F4C81;text-decoration:none;">' . $workItemKey . '</a>
                        </div>
                        <div style="font-size:13px;line-height:1.5;">
                            <a href="' . $safeUrl . '" style="color:#111827;text-decoration:none;">' . $title . '</a>
                        </div>
                    </td>
                    <td style="padding:14px 12px;border-bottom:1px solid #E5E7EB;vertical-align:top;' . $dueStyle . '">' . $dueDateLabel . '</td>
                    <td style="padding:14px 12px;border-bottom:1px solid #E5E7EB;vertical-align:top;color:#4B5563;">' . $reporterDisplay . '</td>
                    <td style="padding:14px 12px;border-bottom:1px solid #E5E7EB;vertical-align:top;color:#4B5563;">' . $lastUpdateLabel . '</td>
                </tr>';
        }

        return '
            <html>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
                <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=3.0">
                <head>
                    <title>' . htmlspecialchars((string) (isset($job['subject']) ? $job['subject'] : 'Project Task Due Reminder'), ENT_QUOTES, 'UTF-8') . '</title>
                </head>
                <body style="margin:0;background-color:#F3F6FB;font-family:sans-serif;color:#111827;">
                    <div style="display:grid;gap:12px;min-width:350px;margin:20px auto;width:680px;max-width:calc(100% - 24px);">
                        <table style="border-spacing:0;width:100%;background-color:#FFFFFF;border-radius:18px;">
                            <tr>
                                <td style="padding:28px 30px;">
                                    <p style="font-size:22px;font-weight:700;margin:0 0 10px;">Project Task Due Reminder</p>
                                    <p style="font-size:14px;line-height:1.6;margin:0 0 8px;">Hi ' . $safeAssigneeName . ',</p>
                                    <p style="font-size:14px;line-height:1.7;margin:0 0 16px;">You have <b>' . htmlspecialchars($summaryText, ENT_QUOTES, 'UTF-8') . '</b> in CMS <b>' . $safeProjectName . '</b>.</p>
                                    <p style="font-size:13px;color:#6B7280;margin:0 0 18px;">Reference date: ' . htmlspecialchars(taskFormatDigestDate($referenceDate), ENT_QUOTES, 'UTF-8') . '</p>
                                    <table style="width:100%;border-collapse:collapse;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;">
                                        <thead>
                                            <tr style="background-color:#F8FAFC;">
                                                <th align="left" style="padding:12px;border-bottom:1px solid #E5E7EB;font-size:12px;color:#4B5563;text-transform:uppercase;letter-spacing:0.04em;">Work Item</th>
                                                <th align="left" style="padding:12px;border-bottom:1px solid #E5E7EB;font-size:12px;color:#4B5563;text-transform:uppercase;letter-spacing:0.04em;">Due Date</th>
                                                <th align="left" style="padding:12px;border-bottom:1px solid #E5E7EB;font-size:12px;color:#4B5563;text-transform:uppercase;letter-spacing:0.04em;">Reporter</th>
                                                <th align="left" style="padding:12px;border-bottom:1px solid #E5E7EB;font-size:12px;color:#4B5563;text-transform:uppercase;letter-spacing:0.04em;">Last Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>' . $rowsHtml . '</tbody>
                                    </table>
                                    <div style="margin:20px 0 0;">
                                        <a href="' . htmlspecialchars($boardUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:11px 18px;border-radius:10px;background-color:#0F4C81;color:#FFFFFF;text-decoration:none;font-size:13px;font-weight:700;">View all works</a>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </body>
            </html>';
    }
}

if (!function_exists('taskGetMentionAlertContext')) {
    function taskGetMentionAlertContext($connect, $itemId)
    {
        $itemId = (int) $itemId;
        if (!($connect instanceof mysqli) || $itemId <= 0) {
            return array();
        }

        $sql = "SELECT id,project_id,title,project_key_id
                FROM " . TASK_ITEM . "
                WHERE id='" . $itemId . "' AND status='A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if (!$result || $result->num_rows === 0) {
            return array();
        }

        $row = $result->fetch_assoc();
        $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
        $projectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
        $projectKeyMap = taskFetchProjectKeyMap($connect, array($projectKeyId), true);
        $projectKeySetting = $projectId > 0 ? taskGetProjectKeySetting($connect, $projectId) : array();
        $fallbackProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $projectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
        if ($projectKey === '') {
            $projectKey = taskNormalizeProjectKey($fallbackProjectKey);
        }

        return array(
            'item_id' => $itemId,
            'project_id' => $projectId,
            'title' => isset($row['title']) ? trim((string) $row['title']) : '',
            'work_item_key' => taskBuildWorkItemKey($projectKey, $itemId),
            'action_url' => taskBuildWorkItemActionUrl($projectId, $itemId),
        );
    }
}

if (!function_exists('taskResolveMentionAlertRecipients')) {
    function taskResolveMentionAlertRecipients($connect, $projectId, $userIds, $currentUserId = 0)
    {
        $projectId = (int) $projectId;
        $currentUserId = (int) $currentUserId;
        $rows = array();
        $userIds = taskUniquePositiveIntIds($userIds);
        if (!($connect instanceof mysqli) || $projectId <= 0 || empty($userIds)) {
            return $rows;
        }

        $sql = "SELECT id, COALESCE(NULLIF(TRIM(name), ''), username) AS display_name
                FROM " . USR_USER . "
                WHERE status='A' AND id IN (" . implode(',', $userIds) . ")
                ORDER BY id ASC";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $userId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($userId <= 0 || $userId === $currentUserId) {
                continue;
            }

            if (!taskCanAccessAnyProjectPage($connect, $projectId, $userId)) {
                continue;
            }

            if (function_exists('systemAlertUserHasAccessToModule')
                && !systemAlertUserHasAccessToModule($connect, $userId, 'project_task')) {
                continue;
            }

            $rows[$userId] = array(
                'id' => $userId,
                'name' => isset($row['display_name']) ? trim((string) $row['display_name']) : '',
            );
        }

        return $rows;
    }
}

if (!function_exists('taskSendMentionAlerts')) {
    function taskSendMentionAlerts($connect, $itemId, $newHtml, $oldHtml, $currentUserId, $contextType, $cdate, $ctime)
    {
        if (!($connect instanceof mysqli) || !function_exists('systemAlertCreate')) {
            return 0;
        }

        $itemId = (int) $itemId;
        $currentUserId = (int) $currentUserId;
        if ($itemId <= 0) {
            return 0;
        }

        $newMentionIds = taskExtractMentionedUserIdsFromHtml($newHtml);
        if (empty($newMentionIds)) {
            return 0;
        }

        $oldMentionIds = taskExtractMentionedUserIdsFromHtml($oldHtml);
        $newMentionIds = array_values(array_diff($newMentionIds, $oldMentionIds));
        if (empty($newMentionIds)) {
            return 0;
        }

        $context = taskGetMentionAlertContext($connect, $itemId);
        if (empty($context)) {
            return 0;
        }

        $projectId = isset($context['project_id']) ? (int) $context['project_id'] : 0;
        $recipients = taskResolveMentionAlertRecipients($connect, $projectId, $newMentionIds, $currentUserId);
        if (empty($recipients)) {
            return 0;
        }

        $contextType = strtolower(trim((string) $contextType));
        if (!in_array($contextType, array('description', 'comment', 'reply'), true)) {
            $contextType = 'comment';
        }

        $workItemKey = isset($context['work_item_key']) ? trim((string) $context['work_item_key']) : '';
        $title = isset($context['title']) ? trim((string) $context['title']) : '';
        $workItemLabel = trim($workItemKey . ($title !== '' ? ' ' . $title : ''));
        if ($workItemLabel === '') {
            $workItemLabel = 'work item #' . $itemId;
        }

        $message = $actorName . ' mentioned you in the ' . $contextType . ' of ' . $workItemLabel . '.';
        $actionUrl = isset($context['action_url']) ? (string) $context['action_url'] : '';
        $createdCount = 0;

        foreach ($recipients as $recipient) {
            $targetUserId = isset($recipient['id']) ? (int) $recipient['id'] : 0;
            if ($targetUserId <= 0) {
                continue;
            }

            $alertId = systemAlertCreate($connect, array(
                'module_key' => 'project_task',
                'notification_type' => 'task_item_mention_' . $contextType,
                'target_user_id' => $targetUserId,
                'target_user_group_id' => function_exists('systemAlertGetUserGroupId') ? systemAlertGetUserGroupId($connect, $targetUserId) : 0,
                'title' => 'Work item mention',
                'message' => $message,
                'action_url' => $actionUrl,
                'action_label' => 'Open Work Item',
                'related_table' => 'task_item',
                'related_id' => $itemId,
                'display_date' => $cdate,
                'create_by' => $currentUserId > 0 ? (string) $currentUserId : 'SYSTEM',
                'create_date' => $cdate,
                'create_time' => $ctime,
            ));

            if ((int) $alertId > 0) {
                $createdCount++;
            }
        }

        return $createdCount;
    }
}

if (!function_exists('taskSendAssigneeReassignmentAlert')) {
    function taskSendAssigneeReassignmentAlert($connect, $itemId, $previousAssigneeUserId, $newAssigneeUserId, $currentUserId, $cdate, $ctime)
    {
        if (!($connect instanceof mysqli) || !function_exists('systemAlertCreate')) {
            return 0;
        }

        $itemId = (int) $itemId;
        $previousAssigneeUserId = (int) $previousAssigneeUserId;
        $newAssigneeUserId = (int) $newAssigneeUserId;
        $currentUserId = (int) $currentUserId;

        if ($itemId <= 0 || $previousAssigneeUserId <= 0 || $newAssigneeUserId <= 0 || $previousAssigneeUserId === $newAssigneeUserId) {
            return 0;
        }

        $context = taskGetMentionAlertContext($connect, $itemId);
        if (empty($context)) {
            return 0;
        }

        $projectId = isset($context['project_id']) ? (int) $context['project_id'] : 0;
        $recipients = taskResolveMentionAlertRecipients($connect, $projectId, array($newAssigneeUserId), 0);
        if (empty($recipients[$newAssigneeUserId])) {
            return 0;
        }

        $actorName = taskFormatHistoryUserValue($connect, $currentUserId);
        $workItemKey = isset($context['work_item_key']) ? trim((string) $context['work_item_key']) : '';
        $title = isset($context['title']) ? trim((string) $context['title']) : '';
        $workItemLabel = trim($workItemKey . ($title !== '' ? ' ' . $title : ''));
        if ($workItemLabel === '') {
            $workItemLabel = 'work item #' . $itemId;
        }

        $alertId = systemAlertCreate($connect, array(
            'module_key' => 'project_task',
            'notification_type' => 'task_item_reassigned',
            'target_user_id' => $newAssigneeUserId,
            'target_user_group_id' => function_exists('systemAlertGetUserGroupId') ? systemAlertGetUserGroupId($connect, $newAssigneeUserId) : 0,
            'title' => 'Work item reassigned',
            'message' => 'The ' . $workItemLabel . ' have been assigned to you.',
            'action_url' => isset($context['action_url']) ? (string) $context['action_url'] : '',
            'action_label' => 'Open Work Item',
            'related_table' => 'task_item',
            'related_id' => $itemId,
            'display_date' => $cdate,
            'create_by' => $currentUserId > 0 ? (string) $currentUserId : 'SYSTEM',
            'create_date' => $cdate,
            'create_time' => $ctime,
        ));

        return (int) $alertId > 0 ? 1 : 0;
    }
}

if (!function_exists('taskGetItemCommentRepliesMap')) {
    function taskGetItemCommentRepliesMap($connect, $itemId, $commentIds)
    {
        $itemId = (int) $itemId;
        $map = array();
        $commentIds = array_values(array_unique(array_map('intval', (array) $commentIds)));
        $commentIds = array_filter($commentIds, function ($id) {
            return $id > 0;
        });

        if ($itemId <= 0 || empty($commentIds) || !defined('TASK_ITEM_COMMENT_REPLY')) {
            return $map;
        }

        $idSql = implode(',', $commentIds);
        $limit = max(100, min(5000, count($commentIds) * 80));
        $sql = "SELECT id,item_id,comment_id,reply_html,reply_color_html,reply_text,create_by,create_date,create_time,update_date,update_time
            FROM " . TASK_ITEM_COMMENT_REPLY . "
            WHERE item_id='" . $itemId . "' AND status='A' AND comment_id IN (" . $idSql . ")
            ORDER BY id ASC
            LIMIT " . (int) $limit;

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        $replyRows = array();
        $actorIds = array();
        while ($row = $result->fetch_assoc()) {
            $replyRows[] = $row;
            $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
        }

        $actorMap = taskFetchUserDisplayMap($connect, $actorIds, false);

        foreach ($replyRows as $row) {
            $commentId = isset($row['comment_id']) ? (int) $row['comment_id'] : 0;
            if ($commentId <= 0) {
                continue;
            }

            $createDate = isset($row['create_date']) ? (string) $row['create_date'] : '';
            $createTime = isset($row['create_time']) ? (string) $row['create_time'] : '';
            $updateDate = isset($row['update_date']) ? (string) $row['update_date'] : '';
            $updateTime = isset($row['update_time']) ? (string) $row['update_time'] : '';
            $hasUpdateDate = ($updateDate !== '' && $updateDate !== '0000-00-00');
            $hasUpdateTime = ($updateTime !== '' && $updateTime !== '00:00:00');
            $isEdited = ($hasUpdateDate || $hasUpdateTime) && ($updateDate !== $createDate || $updateTime !== $createTime);

            $createById = isset($row['create_by']) ? (int) $row['create_by'] : 0;

            if (!isset($map[$commentId])) {
                $map[$commentId] = array();
            }

            $map[$commentId][] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : 0,
                'comment_id' => $commentId,
                'reply_html' => isset($row['reply_color_html']) && trim((string) $row['reply_color_html']) !== ''
                    ? (string) $row['reply_color_html']
                    : (isset($row['reply_html']) ? (string) $row['reply_html'] : ''),
                'reply_text' => isset($row['reply_text']) ? (string) $row['reply_text'] : '',
                'actor_name' => isset($actorMap[$createById]) ? (string) $actorMap[$createById] : 'User',
                'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
                'create_date' => $createDate,
                'create_time' => $createTime,
                'is_edited' => $isEdited ? 1 : 0,
            );
        }

        return $map;
    }
}

if (!function_exists('taskGetItemComments')) {
    function taskGetItemComments($connect, $itemId, $limit = 200)
    {
        $itemId = (int) $itemId;
        $limit = (int) $limit;
        if ($itemId <= 0 || !defined('TASK_ITEM_COMMENT')) {
            return array();
        }

        if ($limit <= 0) {
            $limit = 200;
        }

        $rows = array();
        $commentRows = array();
        $commentIds = array();
        $actorIds = array();
        $sql = "SELECT id,item_id,comment_html,comment_color_html,comment_text,create_by,create_date,create_time,update_date,update_time,status AS row_status
            FROM " . TASK_ITEM_COMMENT . "
            WHERE item_id='" . $itemId . "' AND status IN ('A','D')
            ORDER BY id DESC
            LIMIT " . $limit;

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $commentRows[] = $row;
            $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
        }

        $actorMap = taskFetchUserDisplayMap($connect, $actorIds, false);

        foreach ($commentRows as $row) {
            $commentId = isset($row['id']) ? (int) $row['id'] : 0;
            $createDate = isset($row['create_date']) ? (string) $row['create_date'] : '';
            $createTime = isset($row['create_time']) ? (string) $row['create_time'] : '';
            $updateDate = isset($row['update_date']) ? (string) $row['update_date'] : '';
            $updateTime = isset($row['update_time']) ? (string) $row['update_time'] : '';
            $hasUpdateDate = ($updateDate !== '' && $updateDate !== '0000-00-00');
            $hasUpdateTime = ($updateTime !== '' && $updateTime !== '00:00:00');
            $isEdited = ($hasUpdateDate || $hasUpdateTime) && ($updateDate !== $createDate || $updateTime !== $createTime);
            $createById = isset($row['create_by']) ? (int) $row['create_by'] : 0;

            if ($commentId > 0) {
                $commentIds[] = $commentId;
            }

            $rows[] = array(
                'id' => $commentId,
                'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : 0,
                'comment_html' => isset($row['comment_color_html']) && trim((string) $row['comment_color_html']) !== ''
                    ? (string) $row['comment_color_html']
                    : (isset($row['comment_html']) ? (string) $row['comment_html'] : ''),
                'comment_text' => isset($row['comment_text']) ? (string) $row['comment_text'] : '',
                'is_deleted' => (isset($row['row_status']) && (string) $row['row_status'] === 'D') ? 1 : 0,
                'actor_name' => isset($actorMap[$createById]) ? (string) $actorMap[$createById] : 'User',
                'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
                'create_date' => $createDate,
                'create_time' => $createTime,
                'is_edited' => $isEdited ? 1 : 0,
                'replies' => array(),
            );
        }

        if (!empty($rows) && !empty($commentIds)) {
            $replyMap = taskGetItemCommentRepliesMap($connect, $itemId, $commentIds);
            foreach ($rows as $idx => $commentRow) {
                $cid = isset($commentRow['id']) ? (int) $commentRow['id'] : 0;
                $rows[$idx]['replies'] = isset($replyMap[$cid]) ? $replyMap[$cid] : array();
            }
        }

        $visibleRows = array();
        foreach ($rows as $commentRow) {
            $isDeleted = isset($commentRow['is_deleted']) && (int) $commentRow['is_deleted'] === 1;
            $replyCount = isset($commentRow['replies']) && is_array($commentRow['replies']) ? count($commentRow['replies']) : 0;
            if ($isDeleted && $replyCount === 0) {
                continue;
            }

            if ($isDeleted) {
                $commentRow['comment_html'] = '';
                $commentRow['comment_text'] = '';
            }

            $visibleRows[] = $commentRow;
        }

        return $visibleRows;
    }
}

if (!function_exists('taskCreateItemComment')) {
    function taskCreateItemComment($connect, $itemId, $commentHtml, $currentUserId, $cdate, $ctime, $trackedAttachmentPaths = array())
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid comment request.');
        }

        if (!defined('TASK_ITEM_COMMENT')) {
            return array('ok' => 0, 'message' => 'Comment table is not configured. Please run insert_table.php.');
        }

        $itemRst = mysqli_query(
            $connect,
            "SELECT id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1"
        );
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $safeHtml = taskSanitizeCommentHtml($commentHtml);
        $plainText = taskBuildCommentPlainText($safeHtml);
        $hasImage = (bool) preg_match('/<img\b/i', $safeHtml);
        if ($safeHtml === '' || ($plainText === '' && !$hasImage)) {
            return array('ok' => 0, 'message' => 'Comment cannot be empty.');
        }

        $safeItemId = (int) $itemId;
        $safeCommentHtml = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeCommentColorHtml = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeCommentText = taskEsc($connect, mb_strcut($plainText, 0, 65535, 'UTF-8'));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okInsert = mysqli_query(
            $connect,
            "INSERT INTO " . TASK_ITEM_COMMENT . "
             (item_id,comment_html,comment_color_html,comment_text,create_by,create_date,create_time,status)
             VALUES
             ('" . $safeItemId . "','" . $safeCommentHtml . "','" . $safeCommentColorHtml . "','" . $safeCommentText . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
        );

        if (!$okInsert) {
            return array('ok' => 0, 'message' => 'Failed to save comment.');
        }

        taskSendMentionAlerts(
            $connect,
            $itemId,
            $safeHtml,
            '',
            $currentUserId,
            'comment',
            $cdate,
            $ctime
        );

        taskDeleteRemovedEditorAttachments(
            $connect,
            $itemId,
            '',
            $safeHtml,
            $currentUserId,
            $cdate,
            $ctime,
            array('attachment/board/comment/'),
            $trackedAttachmentPaths
        );

        return array(
            'ok' => 1,
            'message' => 'Comment saved successfully.',
            'comments' => taskGetItemComments($connect, $itemId, 200),
        );
    }
}

if (!function_exists('taskCreateItemCommentReply')) {
    function taskCreateItemCommentReply($connect, $itemId, $commentId, $replyHtml, $currentUserId, $cdate, $ctime, $trackedAttachmentPaths = array())
    {
        $itemId = (int) $itemId;
        $commentId = (int) $commentId;
        if ($itemId <= 0 || $commentId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid reply request.');
        }

        if (!defined('TASK_ITEM_COMMENT_REPLY')) {
            return array('ok' => 0, 'message' => 'Reply table is not configured. Please run insert_table.php.');
        }

        $commentRst = mysqli_query(
            $connect,
            "SELECT id FROM " . TASK_ITEM_COMMENT . "
             WHERE id='" . $commentId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1"
        );
        if (!$commentRst || $commentRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Parent comment not found.');
        }

        $safeHtml = taskSanitizeCommentHtml($replyHtml);
        $plainText = taskBuildCommentPlainText($safeHtml);
        $hasImage = (bool) preg_match('/<img\b/i', $safeHtml);
        if ($safeHtml === '' || ($plainText === '' && !$hasImage)) {
            return array('ok' => 0, 'message' => 'Reply cannot be empty.');
        }

        $safeHtmlSql = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeColorHtmlSql = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeTextSql = taskEsc($connect, mb_strcut($plainText, 0, 65535, 'UTF-8'));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okInsert = mysqli_query(
            $connect,
            "INSERT INTO " . TASK_ITEM_COMMENT_REPLY . "
             (item_id,comment_id,reply_html,reply_color_html,reply_text,create_by,create_date,create_time,status)
             VALUES
             ('" . $itemId . "','" . $commentId . "','" . $safeHtmlSql . "','" . $safeColorHtmlSql . "','" . $safeTextSql . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
        );

        if (!$okInsert) {
            return array('ok' => 0, 'message' => 'Failed to save reply.');
        }

        taskSendMentionAlerts(
            $connect,
            $itemId,
            $safeHtml,
            '',
            $currentUserId,
            'reply',
            $cdate,
            $ctime
        );

        taskDeleteRemovedEditorAttachments(
            $connect,
            $itemId,
            '',
            $safeHtml,
            $currentUserId,
            $cdate,
            $ctime,
            array('attachment/board/reply/'),
            $trackedAttachmentPaths
        );

        return array(
            'ok' => 1,
            'message' => 'Reply saved successfully.',
            'comments' => taskGetItemComments($connect, $itemId, 200),
        );
    }
}

if (!function_exists('taskDeleteItemComment')) {
    function taskDeleteItemComment($connect, $itemId, $commentId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $commentId = (int) $commentId;
        if ($itemId <= 0 || $commentId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid comment delete request.');
        }

        if (!defined('TASK_ITEM_COMMENT')) {
            return array('ok' => 0, 'message' => 'Comment table is not configured. Please run insert_table.php.');
        }

        $commentSql = "SELECT id,item_id,comment_html,comment_text FROM " . TASK_ITEM_COMMENT . "
                       WHERE id='" . $commentId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1";
        $commentRst = mysqli_query($connect, $commentSql);
        if (!$commentRst || $commentRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Comment not found or already deleted.');
        }

        $commentRow = $commentRst->fetch_assoc();
        $oldCommentHtml = isset($commentRow['comment_html']) ? (string) $commentRow['comment_html'] : '';
        $oldCommentText = isset($commentRow['comment_text']) ? (string) $commentRow['comment_text'] : '';

        $safeCommentId = (int) $commentId;
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okDelete = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_COMMENT . "
             SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "'
             WHERE id='" . $safeCommentId . "' AND item_id='" . $itemId . "' AND status='A'"
        );

        if (!$okDelete) {
            return array('ok' => 0, 'message' => 'Failed to delete comment.');
        }

        taskDeleteRemovedEditorAttachments(
            $connect,
            $itemId,
            $oldCommentHtml,
            '',
            $currentUserId,
            $cdate,
            $ctime,
            array('attachment/board/comment/')
        );

        taskLogItemHistory(
            $connect,
            $itemId,
            'delete_comment',
            'Comment',
            mb_strcut(taskBuildCommentPlainText($oldCommentText), 0, 255, 'UTF-8'),
            '',
            'deleted a comment',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array(
            'ok' => 1,
            'message' => 'Comment deleted successfully.',
            'comments' => taskGetItemComments($connect, $itemId, 200),
        );
    }
}

if (!function_exists('taskUpdateItemComment')) {
    function taskUpdateItemComment($connect, $itemId, $commentId, $commentHtml, $currentUserId, $cdate, $ctime, $trackedAttachmentPaths = array())
    {
        $itemId = (int) $itemId;
        $commentId = (int) $commentId;
        if ($itemId <= 0 || $commentId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid comment edit request.');
        }

        if (!defined('TASK_ITEM_COMMENT')) {
            return array('ok' => 0, 'message' => 'Comment table is not configured. Please run insert_table.php.');
        }

        $commentSql = "SELECT id,item_id,comment_html,comment_text FROM " . TASK_ITEM_COMMENT . "
                       WHERE id='" . $commentId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1";
        $commentRst = mysqli_query($connect, $commentSql);
        if (!$commentRst || $commentRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Comment not found.');
        }

        $commentRow = $commentRst->fetch_assoc();
        $safeHtml = taskSanitizeCommentHtml($commentHtml);
        $plainText = taskBuildCommentPlainText($safeHtml);
        $hasImage = (bool) preg_match('/<img\b/i', $safeHtml);
        if ($safeHtml === '' || ($plainText === '' && !$hasImage)) {
            return array('ok' => 0, 'message' => 'Comment cannot be empty.');
        }

        $safeCommentHtml = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeCommentColorHtml = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeCommentText = taskEsc($connect, mb_strcut($plainText, 0, 65535, 'UTF-8'));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_COMMENT . "
             SET comment_html='" . $safeCommentHtml . "', comment_color_html='" . $safeCommentColorHtml . "', comment_text='" . $safeCommentText . "',
                 update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "'
             WHERE id='" . $commentId . "' AND item_id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            return array('ok' => 0, 'message' => 'Failed to update comment.');
        }

        taskSendMentionAlerts(
            $connect,
            $itemId,
            $safeHtml,
            isset($commentRow['comment_html']) ? $commentRow['comment_html'] : '',
            $currentUserId,
            'comment',
            $cdate,
            $ctime
        );

        taskDeleteRemovedEditorAttachments(
            $connect,
            $itemId,
            isset($commentRow['comment_html']) ? $commentRow['comment_html'] : '',
            $safeHtml,
            $currentUserId,
            $cdate,
            $ctime,
            array('attachment/board/comment/'),
            $trackedAttachmentPaths
        );



        return array(
            'ok' => 1,
            'message' => 'Comment updated successfully.',
            'comments' => taskGetItemComments($connect, $itemId, 200),
        );
    }
}

if (!function_exists('taskUpdateItemCommentReply')) {
    function taskUpdateItemCommentReply($connect, $itemId, $replyId, $replyHtml, $currentUserId, $cdate, $ctime, $trackedAttachmentPaths = array())
    {
        $itemId = (int) $itemId;
        $replyId = (int) $replyId;
        if ($itemId <= 0 || $replyId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid reply edit request.');
        }

        if (!defined('TASK_ITEM_COMMENT_REPLY')) {
            return array('ok' => 0, 'message' => 'Reply table is not configured. Please run insert_table.php.');
        }

        $replySql = "SELECT id,item_id,reply_html,reply_text FROM " . TASK_ITEM_COMMENT_REPLY . "
                     WHERE id='" . $replyId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1";
        $replyRst = mysqli_query($connect, $replySql);
        if (!$replyRst || $replyRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Reply not found.');
        }

        $replyRow = $replyRst->fetch_assoc();
        $safeHtml = taskSanitizeCommentHtml($replyHtml);
        $plainText = taskBuildCommentPlainText($safeHtml);
        $hasImage = (bool) preg_match('/<img\b/i', $safeHtml);
        if ($safeHtml === '' || ($plainText === '' && !$hasImage)) {
            return array('ok' => 0, 'message' => 'Reply cannot be empty.');
        }

        $safeReplyHtml = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeReplyColorHtml = taskEsc($connect, mb_strcut($safeHtml, 0, 65535, 'UTF-8'));
        $safeReplyText = taskEsc($connect, mb_strcut($plainText, 0, 65535, 'UTF-8'));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_COMMENT_REPLY . "
             SET reply_html='" . $safeReplyHtml . "', reply_color_html='" . $safeReplyColorHtml . "', reply_text='" . $safeReplyText . "',
                 update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "'
             WHERE id='" . $replyId . "' AND item_id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            return array('ok' => 0, 'message' => 'Failed to update reply.');
        }

        taskSendMentionAlerts(
            $connect,
            $itemId,
            $safeHtml,
            isset($replyRow['reply_html']) ? $replyRow['reply_html'] : '',
            $currentUserId,
            'reply',
            $cdate,
            $ctime
        );

        taskDeleteRemovedEditorAttachments(
            $connect,
            $itemId,
            isset($replyRow['reply_html']) ? $replyRow['reply_html'] : '',
            $safeHtml,
            $currentUserId,
            $cdate,
            $ctime,
            array('attachment/board/reply/'),
            $trackedAttachmentPaths
        );



        $oldReplyText = isset($replyRow['reply_text']) ? (string) $replyRow['reply_text'] : '';
        taskLogItemHistory(
            $connect,
            $itemId,
            'update_reply',
            'Reply',
            mb_strcut(taskBuildCommentPlainText($oldReplyText), 0, 255, 'UTF-8'),
            mb_strcut(taskBuildCommentPlainText($plainText), 0, 255, 'UTF-8'),
            'edited a reply',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array(
            'ok' => 1,
            'message' => 'Reply updated successfully.',
            'comments' => taskGetItemComments($connect, $itemId, 200),
        );
    }
}

if (!function_exists('taskDeleteItemCommentReply')) {
    function taskDeleteItemCommentReply($connect, $itemId, $replyId, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $replyId = (int) $replyId;
        if ($itemId <= 0 || $replyId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid reply delete request.');
        }

        if (!defined('TASK_ITEM_COMMENT_REPLY')) {
            return array('ok' => 0, 'message' => 'Reply table is not configured. Please run insert_table.php.');
        }

        $replySql = "SELECT id,item_id,reply_html,reply_text FROM " . TASK_ITEM_COMMENT_REPLY . "
                     WHERE id='" . $replyId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1";
        $replyRst = mysqli_query($connect, $replySql);
        if (!$replyRst || $replyRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Reply not found or already deleted.');
        }

        $replyRow = $replyRst->fetch_assoc();
        $oldReplyHtml = isset($replyRow['reply_html']) ? (string) $replyRow['reply_html'] : '';
        $oldReplyText = isset($replyRow['reply_text']) ? (string) $replyRow['reply_text'] : '';

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okDelete = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_COMMENT_REPLY . "
             SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "'
             WHERE id='" . $replyId . "' AND item_id='" . $itemId . "' AND status='A'"
        );

        if (!$okDelete) {
            return array('ok' => 0, 'message' => 'Failed to delete reply.');
        }

        taskDeleteRemovedEditorAttachments(
            $connect,
            $itemId,
            $oldReplyHtml,
            '',
            $currentUserId,
            $cdate,
            $ctime,
            array('attachment/board/reply/')
        );

        taskLogItemHistory(
            $connect,
            $itemId,
            'delete_reply',
            'Reply',
            mb_strcut(taskBuildCommentPlainText($oldReplyText), 0, 255, 'UTF-8'),
            '',
            'deleted a reply',
            $currentUserId,
            $cdate,
            $ctime
        );

        return array(
            'ok' => 1,
            'message' => 'Reply deleted successfully.',
            'comments' => taskGetItemComments($connect, $itemId, 200),
        );
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
        $result = getData('id,name', '', '', PIN, $connect);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
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

if (!function_exists('taskGetPinGroupTitleById')) {
    function taskGetPinGroupTitleById($connect, $pinGroupId, $fallback = '')
    {
        static $cache = array();

        $pinGroupId = (int) $pinGroupId;
        $fallback = (string) $fallback;
        if ($pinGroupId <= 0) {
            return $fallback;
        }

        if (!array_key_exists($pinGroupId, $cache)) {
            $cache[$pinGroupId] = '';
            $result = getData('name', "id='" . $pinGroupId . "'", 'LIMIT 1', PIN_GRP, $connect);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (isset($row['name']) && trim((string) $row['name']) !== '') {
                    $cache[$pinGroupId] = trim((string) $row['name']);
                }
            }
        }

        return $cache[$pinGroupId] !== '' ? $cache[$pinGroupId] : $fallback;
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
        $iconDir = __DIR__ . '/svg_icon';
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
    function taskEnsureDefaultWorkTypes($connect, $projectId, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $defaults = array(
            array('name' => 'Task', 'svg_icon' => taskDefaultWorkTypeSvgIcon('Task'), 'remark' => 'Default task work type'),
            array('name' => 'Epic', 'svg_icon' => taskDefaultWorkTypeSvgIcon('Epic'), 'remark' => 'Default epic work type'),
        );

        foreach ($defaults as $default) {
            $safeName = taskEsc($connect, $default['name']);
            $result = getData('id,svg_icon', "project_id='" . $projectId . "' AND LOWER(name)=LOWER('" . $safeName . "')", 'LIMIT 1', TASK_WORK_TYPE, $connect);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
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
                "INSERT INTO " . TASK_WORK_TYPE . " (project_id,name,svg_icon,remark,create_by,create_date,create_time,status) VALUES ('" . $projectId . "','" . $safeName . "','" . $safeIcon . "','" . $safeRemark . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
            );
        }
    }
}

if (!function_exists('taskGetWorkTypes')) {
    function taskGetWorkTypes($connect, $projectId = 0)
    {
        $rows = array();
        $projectId = (int) $projectId;
        $sql = "SELECT id,name,remark,svg_icon FROM " . TASK_WORK_TYPE . " WHERE status='A'";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $sql .= " ORDER BY id ASC";
        $result = mysqli_query($connect, $sql);
        if ($result === false) {
            $sql = "SELECT id,name,remark,'' AS svg_icon FROM " . TASK_WORK_TYPE . " WHERE status='A'";
            if ($projectId > 0) {
                $sql .= " AND project_id='" . $projectId . "'";
            }
            $sql .= " ORDER BY id ASC";
            $result = mysqli_query($connect, $sql);
        }

        if ($result) {
            while ($row = $result->fetch_assoc()) {
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

if (!function_exists('taskNormalizeHexColor')) {
    function taskNormalizeHexColor($color, $fallback = '#f4f7fb')
    {
        $value = strtoupper(trim((string) $color));
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }

        if (preg_match('/^#[0-9A-F]{3}$/', $value)) {
            return '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }

        return strtoupper((string) $fallback);
    }
}

if (!function_exists('taskTextColorForBackground')) {
    function taskTextColorForBackground($color)
    {
        $hex = taskNormalizeHexColor($color, '#DCE8FF');
        $raw = ltrim($hex, '#');
        $red = hexdec(substr($raw, 0, 2));
        $green = hexdec(substr($raw, 2, 2));
        $blue = hexdec(substr($raw, 4, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
        return $brightness >= 186 ? '#1F2A37' : '#FFFFFF';
    }
}

if (!function_exists('taskGenerateProjectKeyFromName')) {
    function taskGenerateProjectKeyFromName($projectName)
    {
        $name = strtoupper(trim((string) $projectName));
        $name = preg_replace('/[^A-Z0-9\s\-]/', ' ', $name);
        $parts = preg_split('/[\s\-]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $letters = '';

        foreach ($parts as $part) {
            $letters .= substr($part, 0, 1);
            if (strlen($letters) >= 6) {
                break;
            }
        }

        if ($letters === '') {
            $letters = preg_replace('/[^A-Z0-9]/', '', $name);
        }

        $letters = substr((string) $letters, 0, 8);
        return taskNormalizeProjectKey($letters !== '' ? $letters : 'TASK');
    }
}

if (!function_exists('taskGetFirstProjectId')) {
    function taskGetFirstProjectId($connect)
    {
        $projectList = taskGetProjectList($connect);
        if (!empty($projectList)) {
            return isset($projectList[0]['id']) ? (int) $projectList[0]['id'] : 0;
        }

        return 0;
    }
}

if (!function_exists('taskGetItemProjectId')) {
    function taskGetItemProjectId($connect, $itemId)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return 0;
        }

        $result = mysqli_query(
            $connect,
            "SELECT project_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1"
        );
        if (!$result || $result->num_rows === 0) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return isset($row['project_id']) ? (int) $row['project_id'] : 0;
    }
}

if (!function_exists('taskResolveCurrentProjectId')) {
    function taskResolveCurrentProjectId($connect, $requestedProjectId = 0)
    {
        $projectId = (int) $requestedProjectId;
        if ($projectId <= 0) {
            $projectId = (int) numberInput('project_id');
        }
        if ($projectId <= 0) {
            $projectId = (int) post('project_id');
        }

        if ($projectId > 0 && defined('TASK_PROJECT')) {
            $result = mysqli_query($connect, "SELECT id FROM " . TASK_PROJECT . " WHERE id='" . $projectId . "' AND status='A' LIMIT 1");
            if ($result && $result->num_rows > 0) {
                return $projectId;
            }
        }

        return taskGetFirstProjectId($connect);
    }
}

if (!function_exists('taskGetProjectList')) {
    function taskGetProjectList($connect)
    {
        $rows = array();
        if (!defined('TASK_PROJECT')) {
            return $rows;
        }

        $sql = "SELECT id,name,owner_user_id,board_background_color FROM " . TASK_PROJECT . " WHERE status='A' ORDER BY name ASC, id ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            $currentUserId = USER_ID;
            while ($row = $result->fetch_assoc()) {
                $projectId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($projectId <= 0) {
                    continue;
                }

                if (!taskCanAccessAnyProjectPage($connect, $projectId, $currentUserId)) {
                    continue;
                }

                $rows[] = array(
                    'id' => $projectId,
                    'name' => isset($row['name']) ? (string) $row['name'] : '',
                    'owner_user_id' => isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0,
                    'board_background_color' => taskNormalizeHexColor(isset($row['board_background_color']) ? $row['board_background_color'] : '', '#f4f7fb'),
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskGetProjectById')) {
    function taskGetProjectById($connect, $projectId)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0 || !defined('TASK_PROJECT')) {
            return array();
        }

        $result = mysqli_query($connect, "SELECT id,name,owner_user_id,board_background_color,remark,create_by,create_date,create_time,update_by,update_date,update_time FROM " . TASK_PROJECT . " WHERE id='" . $projectId . "' AND status='A' LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            return array();
        }

        $row = $result->fetch_assoc();
        $projectKey = taskGetProjectKeySetting($connect, $projectId);

        return array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'name' => isset($row['name']) ? (string) $row['name'] : '',
            'owner_user_id' => isset($row['owner_user_id']) ? (int) $row['owner_user_id'] : 0,
            'board_background_color' => taskNormalizeHexColor(isset($row['board_background_color']) ? $row['board_background_color'] : '', '#f4f7fb'),
            'remark' => isset($row['remark']) ? (string) $row['remark'] : '',
            'project_key' => isset($projectKey['project_key']) ? (string) $projectKey['project_key'] : '',
            'project_key_id' => isset($projectKey['id']) ? (int) $projectKey['id'] : 0,
        );
    }
}

if (!function_exists('taskEnsureProjectDefaults')) {
    function taskEnsureProjectDefaults($connect, $projectId, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return false;
        }

        $safeProjectId = (int) $projectId;
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $statusCount = 0;
        $statusCountRst = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM " . TASK_COLUMN . " WHERE status='A' AND project_id='" . $safeProjectId . "'");
        if ($statusCountRst && $statusCountRst->num_rows > 0) {
            $statusCountRow = $statusCountRst->fetch_assoc();
            $statusCount = isset($statusCountRow['cnt']) ? (int) $statusCountRow['cnt'] : 0;
        }

        if ($statusCount <= 0) {
            $okStatusInsert = mysqli_query(
                $connect,
                "INSERT INTO " . TASK_COLUMN . " (project_id,name,color,sort_order,remark,create_by,create_date,create_time,status) VALUES
                ('" . $safeProjectId . "','To Do','#DFE1E6','1','Default status','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A'),
                ('" . $safeProjectId . "','In Progress','#579DFF','2','Default status','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A'),
                ('" . $safeProjectId . "','Done','#4BCE97','3','Default status','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
            );
            if (!$okStatusInsert) {
                return false;
            }
        }

        $workTypeCount = 0;
        $workTypeCountRst = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM " . TASK_WORK_TYPE . " WHERE status='A' AND project_id='" . $safeProjectId . "'");
        if ($workTypeCountRst && $workTypeCountRst->num_rows > 0) {
            $workTypeCountRow = $workTypeCountRst->fetch_assoc();
            $workTypeCount = isset($workTypeCountRow['cnt']) ? (int) $workTypeCountRow['cnt'] : 0;
        }

        if ($workTypeCount <= 0) {
            $okWorkTypeInsert = mysqli_query(
                $connect,
                "INSERT INTO " . TASK_WORK_TYPE . " (project_id,name,svg_icon,remark,create_by,create_date,create_time,status) VALUES
                ('" . $safeProjectId . "','Task','svg_icon/10318.svg','Default task work type','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A'),
                ('" . $safeProjectId . "','Epic','svg_icon/10307.svg','Default epic work type','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
            );
            if (!$okWorkTypeInsert) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('taskGetProjectCreatorPinAccess')) {
    function taskGetProjectCreatorPinAccess($connect)
    {
        return taskGetPinAccessByGroupId($connect, 139);
    }
}

if (!function_exists('taskGetUserGroupId')) {
    function taskGetUserGroupId($connect, $userId = 0)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            $userId = defined('USER_ID') ? (int) USER_ID : 0;
        }
        if ($userId <= 0) {
            return 0;
        }

        if (defined('USER_ID') && (int) USER_ID === $userId && defined('USER_GROUP')) {
            return (int) USER_GROUP;
        }

        $result = mysqli_query($connect, "SELECT access_id FROM " . USR_USER . " WHERE id='" . $userId . "' AND status='A' LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return isset($row['access_id']) ? (int) $row['access_id'] : 0;
    }
}

if (!function_exists('taskIsTaskAdminUser')) {
    function taskIsTaskAdminUser($connect, $userId = 0)
    {
        $groupId = taskGetUserGroupId($connect, $userId);
        return $groupId === 1 || $groupId === 2;
    }
}

if (!function_exists('taskUserHasFullProjectTaskAccess')) {
    function taskUserHasFullProjectTaskAccess($connect, $projectId = 0, $userId = 0)
    {
        return taskIsProjectOwner($connect, $projectId, $userId);
    }
}

if (!function_exists('taskCanCreateProject')) {
    function taskCanCreateProject($connect)
    {
        return taskIsActionAllowed('add', taskGetProjectCreatorPinAccess($connect));
    }
}

if (!function_exists('taskIsProjectOwner')) {
    function taskIsProjectOwner($connect, $projectId = 0, $userId = 0)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return false;
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            $userId = defined('USER_ID') ? (int) USER_ID : 0;
        }
        if ($userId <= 0) {
            return false;
        }

        $project = taskGetProjectById($connect, $projectId);
        return !empty($project) && isset($project['owner_user_id']) && (int) $project['owner_user_id'] === $userId;
    }
}

if (!function_exists('taskGetProjectUserAccessPinAccess')) {
    function taskGetProjectUserAccessPinAccess($connect)
    {
        return taskGetPinAccessByGroupId($connect, 141);
    }
}

if (!function_exists('taskCanAccessProjectSettings')) {
    function taskCanAccessProjectSettings($connect, $projectId = 0, $requireOwner = false)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return false;
        }

        if (taskIsProjectOwner($connect, $projectId)) {
            return true;
        }

        if (taskIsTaskAdminUser($connect)) {
            return true;
        }

        return taskIsActionAllowed('view', taskGetPinAccessByGroupId($connect, 140));
    }
}

if (!function_exists('taskCanEditProjectSettings')) {
    function taskCanEditProjectSettings($connect, $projectId = 0)
    {
        $userGroup = defined('USER_GROUP') ? trim((string) USER_GROUP) : '';
        $hasEditAccess = taskIsActionAllowed('edit', taskGetPinAccessByGroupId($connect, 140)) || $userGroup === '1' || $userGroup === '2';
        if (!$hasEditAccess) {
            return false;
        }

        return taskCanAccessProjectSettings($connect, $projectId, true);
    }
}

if (!function_exists('taskCanAccessProjectUserAccess')) {
    function taskCanAccessProjectUserAccess($connect, $projectId = 0)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return false;
        }

        if (taskIsProjectOwner($connect, $projectId)) {
            return true;
        }

        if (taskIsTaskAdminUser($connect)) {
            return true;
        }

        return taskIsActionAllowed('view', taskGetPinAccessByGroupId($connect, 141));
    }
}

if (!function_exists('taskCanManageProjectActions')) {
    function taskCanManageProjectActions($connect, $projectId = 0)
    {
        return taskCanAccessProjectSettings($connect, $projectId, true) || taskCanAccessProjectUserAccess($connect, $projectId);
    }
}

if (!function_exists('taskGetProjectKeySetting')) {
    function taskGetProjectKeySetting($connect, $projectId = 0)
    {
        $row = array(
            'id' => 0,
            'project_key' => '',
        );

        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            $projectId = taskResolveCurrentProjectId($connect, 0);
        }

        $sql = "SELECT id,project_key FROM " . TASK_PROJECT_KEY . " WHERE status='A'";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $sql .= " ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $row['id'] = isset($data['id']) ? (int) $data['id'] : 0;
            $row['project_key'] = taskNormalizeProjectKey(isset($data['project_key']) ? $data['project_key'] : '');
        }

        return $row;
    }
}

if (!function_exists('taskSaveProjectKeySetting')) {
    function taskSaveProjectKeySetting($connect, $projectId, $projectKey, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return array('ok' => 0, 'message' => 'Project not found.');
        }

        $normalizedKey = taskNormalizeProjectKey($projectKey);
        $safeKey = taskEsc($connect, $normalizedKey);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $existsSql = "SELECT id FROM " . TASK_PROJECT_KEY . " WHERE status='A' AND project_id='" . $projectId . "' ORDER BY id DESC LIMIT 1";
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

        $insertSql = "INSERT INTO " . TASK_PROJECT_KEY . " (project_id,project_key,create_by,create_date,create_time,status)
                      VALUES ('" . $projectId . "','" . $safeKey . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
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

if (!function_exists('taskCreateProject')) {
    function taskCreateProject($connect, $projectName, $currentUserId, $cdate, $ctime)
    {
        if (!defined('TASK_PROJECT')) {
            return array('ok' => 0, 'message' => 'Task project table is not configured. Please run insert_table.php.');
        }

        $projectName = trim((string) $projectName);
        if ($projectName === '') {
            return array('ok' => 0, 'message' => 'Project task name is required.');
        }

        $safeProjectName = taskEsc($connect, mb_strcut($projectName, 0, 180, 'UTF-8'));
        $dupSql = "SELECT id FROM " . TASK_PROJECT . " WHERE status='A' AND LOWER(name)=LOWER('" . $safeProjectName . "') LIMIT 1";
        $dupRst = mysqli_query($connect, $dupSql);
        if ($dupRst && $dupRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'Project task name already exists.');
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);
        $defaultBoardColor = taskNormalizeHexColor('#f4f7fb', '#f4f7fb');

        mysqli_begin_transaction($connect);

        $insertProjectSql = "INSERT INTO " . TASK_PROJECT . " (name,owner_user_id,board_background_color,create_by,create_date,create_time,status)
            VALUES ('" . $safeProjectName . "','" . (int) $currentUserId . "','" . taskEsc($connect, $defaultBoardColor) . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
        if (!mysqli_query($connect, $insertProjectSql)) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to create project task.');
        }

        $projectId = (int) mysqli_insert_id($connect);
        $projectKey = '';
        $projectKeySql = "INSERT INTO " . TASK_PROJECT_KEY . " (project_id,project_key,remark,create_by,create_date,create_time,status)
            VALUES ('" . $projectId . "','" . taskEsc($connect, $projectKey) . "','Project key setting','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
        if (!mysqli_query($connect, $projectKeySql)) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to create project key.');
        }

        if (!taskEnsureProjectDefaults($connect, $projectId, $currentUserId, $cdate, $ctime)) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to initialize the new project task.');
        }
        mysqli_commit($connect);

        return array(
            'ok' => 1,
            'message' => 'Project task created successfully.',
            'project' => taskGetProjectById($connect, $projectId),
        );
    }
}

if (!function_exists('taskSaveProjectSettings')) {
    function taskSaveProjectSettings($connect, $projectId, $projectName, $projectKey, $boardBackgroundColor, $statusRows, $statusDeleteIds, $workTypeRows, $workTypeDeleteIds, $labelRows, $labelDeleteIds, $statusLabelRows, $statusLabelDeleteIds, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return array('ok' => 0, 'message' => 'Project not found.');
        }

        $projectName = trim((string) $projectName);
        if ($projectName === '') {
            return array('ok' => 0, 'message' => 'Project task name is required.');
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);
        $safeProjectName = taskEsc($connect, mb_strcut($projectName, 0, 180, 'UTF-8'));
        $safeBoardColor = taskEsc($connect, taskNormalizeHexColor($boardBackgroundColor, '#f4f7fb'));

        $dupProjectSql = "SELECT id FROM " . TASK_PROJECT . " WHERE status='A' AND LOWER(name)=LOWER('" . $safeProjectName . "') AND id <> '" . $projectId . "' LIMIT 1";
        $dupProjectRst = mysqli_query($connect, $dupProjectSql);
        if ($dupProjectRst && $dupProjectRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'Another project task already uses this name.');
        }

        $normalizedStatuses = array();
        $statusNameLookup = array();
        foreach ((array) $statusRows as $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $rowName = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($rowName === '') {
                continue;
            }

            $lookup = strtolower($rowName);
            if (isset($statusNameLookup[$lookup])) {
                return array('ok' => 0, 'message' => 'Duplicate status name found: ' . $rowName);
            }
            $statusNameLookup[$lookup] = true;

            $normalizedStatuses[] = array(
                'id' => $rowId,
                'name' => $rowName,
                'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#dfe1e6'),
            );
        }

        if (empty($normalizedStatuses)) {
            return array('ok' => 0, 'message' => 'At least one status is required.');
        }

        $normalizedWorkTypes = array();
        $workTypeNameLookup = array();
        foreach ((array) $workTypeRows as $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $rowName = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($rowName === '') {
                continue;
            }

            $lookup = strtolower($rowName);
            if (isset($workTypeNameLookup[$lookup])) {
                return array('ok' => 0, 'message' => 'Duplicate task type name found: ' . $rowName);
            }
            $workTypeNameLookup[$lookup] = true;

            $normalizedWorkTypes[] = array(
                'id' => $rowId,
                'name' => $rowName,
                'icon' => taskNormalizeWorkTypeSvgIcon(isset($row['svg_icon']) ? $row['svg_icon'] : '', $rowName),
            );
        }

        if (empty($normalizedWorkTypes)) {
            return array('ok' => 0, 'message' => 'At least one task type is required.');
        }

        $normalizedLabels = array();
        $labelNameLookup = array();
        foreach ((array) $labelRows as $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $rowName = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($rowName === '') {
                continue;
            }

            $lookup = strtolower($rowName);
            if (isset($labelNameLookup[$lookup])) {
                return array('ok' => 0, 'message' => 'Duplicate label name found: ' . $rowName);
            }
            $labelNameLookup[$lookup] = true;

            $normalizedLabels[] = array(
                'id' => $rowId,
                'name' => $rowName,
                'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#DCE8FF'),
            );
        }

        $normalizedStatusLabels = array();
        $statusLabelNameLookup = array();
        foreach ((array) $statusLabelRows as $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            $rowName = trim((string) (isset($row['name']) ? $row['name'] : ''));
            if ($rowName === '') {
                continue;
            }

            $lookup = strtolower($rowName);
            if (isset($statusLabelNameLookup[$lookup])) {
                return array('ok' => 0, 'message' => 'Duplicate task status label name found: ' . $rowName);
            }
            $statusLabelNameLookup[$lookup] = true;

            $normalizedStatusLabels[] = array(
                'id' => $rowId,
                'name' => $rowName,
                'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#DCE8FF'),
            );
        }

        mysqli_begin_transaction($connect);

        $updateProjectSql = "UPDATE " . TASK_PROJECT . " SET
            name='" . $safeProjectName . "',
            board_background_color='" . $safeBoardColor . "',
            update_by='" . $safeUser . "',
            update_date='" . $safeDate . "',
            update_time='" . $safeTime . "'
            WHERE id='" . $projectId . "' AND status='A'";
        if (!mysqli_query($connect, $updateProjectSql)) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to update project task.');
        }

        $projectKeyResult = taskSaveProjectKeySetting($connect, $projectId, $projectKey, $currentUserId, $cdate, $ctime);
        if (empty($projectKeyResult['ok'])) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => isset($projectKeyResult['message']) ? (string) $projectKeyResult['message'] : 'Failed to update project key.');
        }

        foreach ($normalizedStatuses as $index => $row) {
            $statusId = isset($row['id']) ? (int) $row['id'] : 0;
            $safeStatusName = taskEsc($connect, mb_strcut((string) $row['name'], 0, 150, 'UTF-8'));
            $safeStatusColor = taskEsc($connect, (string) $row['color']);
            $sortOrder = $index + 1;

            if ($statusId > 0) {
                $existsRst = mysqli_query($connect, "SELECT id FROM " . TASK_COLUMN . " WHERE id='" . $statusId . "' AND project_id='" . $projectId . "' AND status='A' LIMIT 1");
                if (!$existsRst || $existsRst->num_rows === 0) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Invalid status update request.');
                }

                $updateStatusSql = "UPDATE " . TASK_COLUMN . " SET
                    name='" . $safeStatusName . "',
                    color='" . $safeStatusColor . "',
                    sort_order='" . $sortOrder . "',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                    WHERE id='" . $statusId . "' AND project_id='" . $projectId . "' AND status='A'";
                if (!mysqli_query($connect, $updateStatusSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to update status.');
                }
            } else {
                $insertStatusSql = "INSERT INTO " . TASK_COLUMN . " (project_id,name,color,sort_order,create_by,create_date,create_time,status)
                    VALUES ('" . $projectId . "','" . $safeStatusName . "','" . $safeStatusColor . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
                if (!mysqli_query($connect, $insertStatusSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to create status.');
                }
            }
        }

        $statusDeleteIds = taskUniquePositiveIntIds((array) $statusDeleteIds);
        foreach ($statusDeleteIds as $statusDeleteId) {
            $itemIds = array();
            $itemRst = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE project_id='" . $projectId . "' AND column_id='" . (int) $statusDeleteId . "' AND status='A'");
            if ($itemRst) {
                while ($itemRow = $itemRst->fetch_assoc()) {
                    $itemId = isset($itemRow['id']) ? (int) $itemRow['id'] : 0;
                    if ($itemId > 0) {
                        $itemIds[] = $itemId;
                    }
                }
            }

            if (!empty($itemIds)) {
                $itemIdSql = implode(',', $itemIds);
                $okItems = mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id IN (" . $itemIdSql . ") AND status='A'");
                $okItemLabels = mysqli_query($connect, "UPDATE " . TASK_ITEM_LABEL . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE item_id IN (" . $itemIdSql . ") AND status='A'");
                if (!$okItems || !$okItemLabels) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to remove work items under the deleted status.');
                }
            }

            if (!mysqli_query($connect, "UPDATE " . TASK_COLUMN . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . (int) $statusDeleteId . "' AND project_id='" . $projectId . "' AND status='A'")) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to remove status.');
            }
        }

        foreach ($normalizedWorkTypes as $row) {
            $workTypeId = isset($row['id']) ? (int) $row['id'] : 0;
            $safeWorkTypeName = taskEsc($connect, mb_strcut((string) $row['name'], 0, 80, 'UTF-8'));
            $safeIcon = taskEsc($connect, (string) $row['icon']);

            if ($workTypeId > 0) {
                $existsRst = mysqli_query($connect, "SELECT id FROM " . TASK_WORK_TYPE . " WHERE id='" . $workTypeId . "' AND project_id='" . $projectId . "' AND status='A' LIMIT 1");
                if (!$existsRst || $existsRst->num_rows === 0) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Invalid task type update request.');
                }

                $updateWorkTypeSql = "UPDATE " . TASK_WORK_TYPE . " SET
                    name='" . $safeWorkTypeName . "',
                    svg_icon='" . $safeIcon . "',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                    WHERE id='" . $workTypeId . "' AND project_id='" . $projectId . "' AND status='A'";
                if (!mysqli_query($connect, $updateWorkTypeSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to update task type.');
                }
            } else {
                $insertWorkTypeSql = "INSERT INTO " . TASK_WORK_TYPE . " (project_id,name,svg_icon,create_by,create_date,create_time,status)
                    VALUES ('" . $projectId . "','" . $safeWorkTypeName . "','" . $safeIcon . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
                if (!mysqli_query($connect, $insertWorkTypeSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to create task type.');
                }
            }
        }

        $workTypeDeleteIds = taskUniquePositiveIntIds((array) $workTypeDeleteIds);
        foreach ($workTypeDeleteIds as $workTypeDeleteId) {
            $usageRst = mysqli_query($connect, "SELECT COUNT(*) AS cnt FROM " . TASK_ITEM . " WHERE project_id='" . $projectId . "' AND work_type_id='" . (int) $workTypeDeleteId . "' AND status='A'");
            $usageCount = 0;
            if ($usageRst && $usageRst->num_rows > 0) {
                $usageRow = $usageRst->fetch_assoc();
                $usageCount = isset($usageRow['cnt']) ? (int) $usageRow['cnt'] : 0;
            }
            if ($usageCount > 0) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Cannot delete a task type that is still used by work items.');
            }

            if (!mysqli_query($connect, "UPDATE " . TASK_WORK_TYPE . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . (int) $workTypeDeleteId . "' AND project_id='" . $projectId . "' AND status='A'")) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to remove task type.');
            }
        }

        foreach ($normalizedLabels as $index => $row) {
            $labelId = isset($row['id']) ? (int) $row['id'] : 0;
            $safeLabelName = taskEsc($connect, mb_strcut((string) $row['name'], 0, 120, 'UTF-8'));
            $safeLabelColor = taskEsc($connect, (string) $row['color']);
            $sortOrder = $index + 1;

            if ($labelId > 0) {
                $existsRst = mysqli_query($connect, "SELECT id FROM " . TASK_LABEL . " WHERE id='" . $labelId . "' AND status='A' LIMIT 1");
                if (!$existsRst || $existsRst->num_rows === 0) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Invalid label update request.');
                }

                $updateLabelSql = "UPDATE " . TASK_LABEL . " SET
                    name='" . $safeLabelName . "',
                    color='" . $safeLabelColor . "',
                    sort_order='" . $sortOrder . "',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                    WHERE id='" . $labelId . "' AND status='A'";
                if (!mysqli_query($connect, $updateLabelSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to update label.');
                }
            } else {
                $insertLabelSql = "INSERT INTO " . TASK_LABEL . " (name,color,sort_order,create_by,create_date,create_time,status)
                    VALUES ('" . $safeLabelName . "','" . $safeLabelColor . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
                if (!mysqli_query($connect, $insertLabelSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to create label.');
                }
            }
        }

        $labelDeleteIds = taskUniquePositiveIntIds((array) $labelDeleteIds);
        foreach ($labelDeleteIds as $labelDeleteId) {
            $okLabel = mysqli_query($connect, "UPDATE " . TASK_LABEL . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . (int) $labelDeleteId . "' AND status='A'");
            $okMap = mysqli_query($connect, "UPDATE " . TASK_ITEM_LABEL . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE label_id='" . (int) $labelDeleteId . "' AND status='A'");
            if (!$okLabel || !$okMap) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to remove label.');
            }
        }

        foreach ($normalizedStatusLabels as $index => $row) {
            $labelId = isset($row['id']) ? (int) $row['id'] : 0;
            $safeLabelName = taskEsc($connect, mb_strcut((string) $row['name'], 0, 120, 'UTF-8'));
            $safeLabelColor = taskEsc($connect, (string) $row['color']);
            $sortOrder = $index + 1;

            if ($labelId > 0) {
                $existsRst = mysqli_query($connect, "SELECT id FROM " . TASK_STATUS_LABEL . " WHERE id='" . $labelId . "' AND status='A' LIMIT 1");
                if (!$existsRst || $existsRst->num_rows === 0) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Invalid task status label update request.');
                }

                $updateStatusLabelSql = "UPDATE " . TASK_STATUS_LABEL . " SET
                    name='" . $safeLabelName . "',
                    color='" . $safeLabelColor . "',
                    sort_order='" . $sortOrder . "',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                    WHERE id='" . $labelId . "' AND status='A'";
                if (!mysqli_query($connect, $updateStatusLabelSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to update task status label.');
                }
            } else {
                $insertStatusLabelSql = "INSERT INTO " . TASK_STATUS_LABEL . " (name,color,sort_order,create_by,create_date,create_time,status)
                    VALUES ('" . $safeLabelName . "','" . $safeLabelColor . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
                if (!mysqli_query($connect, $insertStatusLabelSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to create task status label.');
                }
            }
        }

        $statusLabelDeleteIds = taskUniquePositiveIntIds((array) $statusLabelDeleteIds);
        foreach ($statusLabelDeleteIds as $statusLabelDeleteId) {
            $result = mysqli_query($connect, "SELECT id,name FROM " . TASK_STATUS_LABEL . " WHERE id='" . (int) $statusLabelDeleteId . "' AND status='A' LIMIT 1");
            if (!$result || $result->num_rows === 0) {
                continue;
            }

            $row = $result->fetch_assoc();
            $statusName = isset($row['name']) ? trim((string) $row['name']) : '';
            $okStatus = mysqli_query($connect, "UPDATE " . TASK_STATUS_LABEL . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . (int) $statusLabelDeleteId . "' AND status='A'");
            $okItem = mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET
                task_status=TRIM(BOTH ',' FROM REPLACE(CONCAT(',', REPLACE(task_status, ' ', ''), ','), '," . (int) $statusLabelDeleteId . ",', ',')),
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE status='A' AND FIND_IN_SET('" . (int) $statusLabelDeleteId . "', REPLACE(task_status, ' ', '')) > 0");
            if ($okItem && $statusName !== '') {
                $safeStatusName = taskEsc($connect, $statusName);
                $okItem = mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET
                    task_status='',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                 WHERE task_status='" . $safeStatusName . "' AND status='A'");
            }
            if (!$okStatus || !$okItem) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to remove task status label.');
            }
        }

        mysqli_commit($connect);
        return array(
            'ok' => 1,
            'message' => 'Project settings updated successfully.',
            'project' => taskGetProjectById($connect, $projectId),
            'projectKey' => taskGetProjectKeySetting($connect, $projectId),
            'statuses' => taskGetColumns($connect, $projectId),
            'workTypes' => taskGetWorkTypes($connect, $projectId),
            'labels' => taskGetLabels($connect),
            'statusLabels' => taskGetStatusLabels($connect),
        );
    }
}

if (!function_exists('taskGetAssignees')) {
    function taskGetAssignees($connect)
    {
        $rows = array();
        $sql = "SELECT id, COALESCE(NULLIF(TRIM(name), ''), username) AS display_name, email FROM " . USR_USER . " WHERE status='A' ORDER BY display_name ASC";
        $result = mysqli_query($connect, $sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
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
        $sql = "SELECT id,name,color FROM " . TASK_LABEL . " WHERE status='A' ORDER BY sort_order ASC, name ASC";
        $result = mysqli_query($connect, $sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#DCE8FF'),
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
        $sql = "SELECT id,name,color FROM " . TASK_STATUS_LABEL . " WHERE status='A' ORDER BY sort_order ASC, name ASC";
        $result = mysqli_query($connect, $sql);
        if ($result === false) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#DCE8FF'),
            );
        }

        return $rows;
    }
}

if (!function_exists('taskUniquePositiveIntIds')) {
    function taskUniquePositiveIntIds($values)
    {
        $ids = array_values(array_unique(array_map('intval', (array) $values)));
        return array_values(array_filter($ids, function ($id) {
            return $id > 0;
        }));
    }
}

if (!function_exists('taskFetchUserDisplayMap')) {
    function taskFetchUserDisplayMap($connect, $userIds, $onlyActive = false)
    {
        $map = array();
        $ids = taskUniquePositiveIntIds($userIds);
        if (empty($ids)) {
            return $map;
        }

        $sql = "SELECT id,name,username FROM " . USR_USER . " WHERE id IN (" . implode(',', $ids) . ")";
        if ($onlyActive) {
            $sql .= " AND status='A'";
        }

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $username = isset($row['username']) ? trim((string) $row['username']) : '';
            $map[$id] = $name !== '' ? $name : ($username !== '' ? $username : 'User');
        }

        return $map;
    }
}

if (!function_exists('taskFormatHistoryUserValue')) {
    function taskFormatHistoryUserValue($connect, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return 'Unassigned';
        }

        $userMap = taskFetchUserDisplayMap($connect, array($userId), true);
        if (isset($userMap[$userId])) {
            $displayName = trim((string) $userMap[$userId]);
            if ($displayName !== '') {
                return $displayName;
            }
        }

        return 'User #' . $userId;
    }
}

if (!function_exists('taskFetchWorkTypeInfoMap')) {
    function taskFetchWorkTypeInfoMap($connect, $workTypeIds, $onlyActive = true)
    {
        $map = array();
        $ids = taskUniquePositiveIntIds($workTypeIds);
        if (empty($ids)) {
            return $map;
        }

        $sql = "SELECT id,name,svg_icon FROM " . TASK_WORK_TYPE . " WHERE id IN (" . implode(',', $ids) . ")";
        if ($onlyActive) {
            $sql .= " AND status='A'";
        }

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $name = isset($row['name']) ? trim((string) $row['name']) : 'Task';
            if ($name === '') {
                $name = 'Task';
            }

            $map[$id] = array(
                'name' => $name,
                'svg_icon' => taskNormalizeWorkTypeSvgIcon(isset($row['svg_icon']) ? $row['svg_icon'] : '', $name),
            );
        }

        return $map;
    }
}

if (!function_exists('taskFetchProjectKeyMap')) {
    function taskFetchProjectKeyMap($connect, $projectKeyIds, $onlyActive = true)
    {
        $map = array();
        $ids = taskUniquePositiveIntIds($projectKeyIds);
        if (empty($ids)) {
            return $map;
        }

        $sql = "SELECT id,project_key FROM " . TASK_PROJECT_KEY . " WHERE id IN (" . implode(',', $ids) . ")";
        if ($onlyActive) {
            $sql .= " AND status='A'";
        }

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }
            $map[$id] = isset($row['project_key']) ? taskNormalizeProjectKey($row['project_key']) : '';
        }

        return $map;
    }
}

if (!function_exists('taskFetchColumnInfoMap')) {
    function taskFetchColumnInfoMap($connect, $columnIds, $onlyActive = true)
    {
        $map = array();
        $ids = taskUniquePositiveIntIds($columnIds);
        if (empty($ids)) {
            return $map;
        }

        $sql = "SELECT id,name,sort_order FROM " . TASK_COLUMN . " WHERE id IN (" . implode(',', $ids) . ")";
        if ($onlyActive) {
            $sql .= " AND status='A'";
        }

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $map[$id] = array(
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
            );
        }

        return $map;
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

if (!function_exists('taskParseCsvKeyList')) {
    function taskParseCsvKeyList($rawValue)
    {
        $value = trim((string) $rawValue);
        if ($value === '') {
            return array();
        }

        $parts = preg_split('/\s*,\s*/', $value);
        $keys = array();
        foreach ((array) $parts as $part) {
            $key = strtolower(trim((string) $part));
            if ($key === '') {
                continue;
            }
            $key = preg_replace('/[^a-z0-9_]/', '', $key);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}

if (!function_exists('taskGetProjectAccessFieldOptions')) {
    function taskGetProjectAccessFieldOptions()
    {
        return array(
            array('key' => 'original_estimate', 'label' => 'Original Estimate'),
            array('key' => 'task_status', 'label' => 'Task Status'),
            array('key' => 'parent_display', 'label' => 'Parent'),
            array('key' => 'assignee_name', 'label' => 'Assignee'),
            array('key' => 'labels', 'label' => 'Labels'),
            array('key' => 'due_date', 'label' => 'Due Date'),
            array('key' => 'start_date', 'label' => 'Start Date'),
            array('key' => 'reporter_name', 'label' => 'Reporter'),
            array('key' => 'priority', 'label' => 'Priority'),
            array('key' => 'amendement_date', 'label' => 'Amendment Date'),
            array('key' => 'amendement_time_minutes', 'label' => 'Amendment Time'),
            array('key' => 'second_amendement_date', 'label' => 'Second Amendment Date'),
            array('key' => 'second_amendement_time_minutes', 'label' => 'Second Amendment Time'),
        );
    }
}

if (!function_exists('taskGetProjectAccessFieldKeyMap')) {
    function taskGetProjectAccessFieldKeyMap()
    {
        $map = array();
        foreach (taskGetProjectAccessFieldOptions() as $option) {
            $key = isset($option['key']) ? strtolower(trim((string) $option['key'])) : '';
            if ($key !== '') {
                $map[$key] = isset($option['label']) ? (string) $option['label'] : $key;
            }
        }
        return $map;
    }
}

if (!function_exists('taskProjectPagePinToKey')) {
    function taskProjectPagePinToKey($pinGroupId)
    {
        $pinGroupId = (int) $pinGroupId;

        if ($pinGroupId === 137) {
            return 'summary';
        }

        if ($pinGroupId === 136) {
            return 'board';
        }

        if ($pinGroupId === 138) {
            return 'sheets';
        }

        return '';
    }
}

if (!function_exists('taskGetProjectUserAccessRecord')) {
    function taskGetProjectUserAccessRecord($connect, $projectId, $userId = 0, $useEffectiveAccess = true)
    {
        $projectId = (int) $projectId;
        $userId = (int) $userId;

        if ($userId <= 0) {
            $userId = defined('USER_ID') ? (int) USER_ID : 0;
        }

        $empty = array(
            'user_id' => $userId,
            'is_owner' => 0,
            'work_item_add' => 0,
            'work_item_edit' => 0,
            'work_item_delete' => 0,
            'allowed_work_type_ids' => array(),
            'allowed_status_ids' => array(),
        );

        if ($projectId <= 0 || $userId <= 0) {
            return $empty;
        }

        if ($useEffectiveAccess && taskUserHasFullProjectTaskAccess($connect, $projectId, $userId)) {
            $empty['is_owner'] = 1;
            $empty['work_item_add'] = 1;
            $empty['work_item_edit'] = 1;
            $empty['work_item_delete'] = 1;
            return $empty;
        }

        if (!defined('TASK_PROJECT_ITEM_ACCESS')) {
            return $empty;
        }

        $result = mysqli_query(
            $connect,
            "SELECT can_add, can_edit, can_delete, allowed_work_type_ids
             FROM " . TASK_PROJECT_ITEM_ACCESS . "
             WHERE project_id='" . $projectId . "'
               AND user_id='" . $userId . "'
               AND status='A'
             LIMIT 1"
        );

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $empty['work_item_add'] = isset($row['can_add']) ? (int) $row['can_add'] : 0;
            $empty['work_item_edit'] = isset($row['can_edit']) ? (int) $row['can_edit'] : 0;
            $empty['work_item_delete'] = isset($row['can_delete']) ? (int) $row['can_delete'] : 0;
            $empty['allowed_work_type_ids'] = taskParseCsvIdList(isset($row['allowed_work_type_ids']) ? $row['allowed_work_type_ids'] : '');
        }

        if (defined('TASK_PROJECT_STATUS_ACCESS')) {
            $statusRst = mysqli_query(
                $connect,
                "SELECT to_status_id
                 FROM " . TASK_PROJECT_STATUS_ACCESS . "
                 WHERE project_id='" . $projectId . "'
                   AND user_id='" . $userId . "'
                   AND can_move='1'
                   AND status='A'"
            );

            if ($statusRst) {
                while ($statusRow = $statusRst->fetch_assoc()) {
                    $statusId = isset($statusRow['to_status_id']) ? (int) $statusRow['to_status_id'] : 0;
                    if ($statusId > 0) {
                        $empty['allowed_status_ids'][] = $statusId;
                    }
                }
            }

            $empty['allowed_status_ids'] = array_values(array_unique($empty['allowed_status_ids']));
        }

        return $empty;
    }
}

if (!function_exists('taskHasAnyProjectAccess')) {
    function taskHasAnyProjectAccess($connect, $projectId, $userId = 0)
    {
        if (taskUserHasFullProjectTaskAccess($connect, $projectId, $userId)) {
            return true;
        }

        $record = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
        if (!empty($record['work_item_add'])
            || !empty($record['work_item_edit'])
            || !empty($record['work_item_delete'])
            || !empty($record['allowed_work_type_ids'])
            || !empty($record['allowed_status_ids'])) {
            return true;
        }

        $columnPermissions = taskGetProjectColumnAccessMap($connect, $projectId, $userId);
        foreach ($columnPermissions as $permissionRow) {
            if (!empty($permissionRow['add']) || !empty($permissionRow['edit']) || !empty($permissionRow['delete'])) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('taskCanAccessAnyProjectPage')) {
    function taskCanAccessAnyProjectPage($connect, $projectId, $userId = 0)
    {
        $projectTaskPinAccess = taskGetProjectCreatorPinAccess($connect);
        if (!taskIsActionAllowed('view', $projectTaskPinAccess)) {
            return false;
        }

        return taskHasAnyProjectAccess($connect, $projectId, $userId);
    }
}

if (!function_exists('taskUserCanAccessProjectPageByPin')) {
    function taskUserCanAccessProjectPageByPin($connect, $projectId, $pinGroupId, $userId = 0)
    {
        $projectTaskPinAccess = taskGetProjectCreatorPinAccess($connect);
        if (!taskIsActionAllowed('view', $projectTaskPinAccess)) {
            return false;
        }

        $pinAccess = taskGetPinAccessByGroupId($connect, $pinGroupId);

        if (!taskIsActionAllowed('view', $pinAccess)) {
            return false;
        }

        return taskHasAnyProjectAccess($connect, $projectId, $userId);
    }
}

if (!function_exists('taskUserCanWorkItemAction')) {
    function taskUserCanWorkItemAction($connect, $projectId, $actionName, $userId = 0)
    {
        $record = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
        if (empty($record)) {
            return false;
        }
        if (!empty($record['is_owner'])) {
            return true;
        }

        $actionName = strtolower(trim((string) $actionName));
        if ($actionName === 'add') {
            return !empty($record['work_item_add']);
        }
        if ($actionName === 'edit') {
            return !empty($record['work_item_edit']);
        }
        if ($actionName === 'delete') {
            return !empty($record['work_item_delete']);
        }

        return false;
    }
}

if (!function_exists('taskUserAllowedWorkTypeIds')) {
    function taskUserAllowedWorkTypeIds($connect, $projectId, $userId = 0)
    {
        $record = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
        if (empty($record) || !isset($record['allowed_work_type_ids'])) {
            return array();
        }
        if (!empty($record['is_owner'])) {
            return array();
        }
        return taskUniquePositiveIntIds($record['allowed_work_type_ids']);
    }
}

if (!function_exists('taskUserAllowedStatusIds')) {
    function taskUserAllowedStatusIds($connect, $projectId, $userId = 0)
    {
        $record = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
        if (empty($record) || !isset($record['allowed_status_ids'])) {
            return array();
        }
        if (!empty($record['is_owner'])) {
            return array();
        }
        return taskUniquePositiveIntIds($record['allowed_status_ids']);
    }
}

if (!function_exists('taskUserAllowedColumnKeys')) {
    function taskUserAllowedColumnKeys($connect, $projectId, $userId = 0)
    {
        $permissions = taskGetProjectColumnAccessMap($connect, $projectId, $userId);
        if (empty($permissions)) {
            return array();
        }

        $allowedKeys = array();
        foreach ($permissions as $columnKey => $permissionRow) {
            if (!empty($permissionRow['add']) || !empty($permissionRow['edit']) || !empty($permissionRow['delete'])) {
                $allowedKeys[] = (string) $columnKey;
            }
        }

        return taskParseCsvKeyList(implode(',', $allowedKeys));
    }
}

if (!function_exists('taskUserCanColumnAction')) {
    function taskUserCanColumnAction($connect, $projectId, $actionName, $userId = 0)
    {
        $record = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
        if (empty($record)) {
            return false;
        }
        if (!empty($record['is_owner'])) {
            return true;
        }

        $actionName = strtolower(trim((string) $actionName));
        if ($actionName === 'add') {
            return !empty($record['column_add']);
        }
        if ($actionName === 'edit') {
            return !empty($record['column_edit']);
        }
        if ($actionName === 'delete') {
            return !empty($record['column_delete']);
        }

        return false;
    }
}

if (!function_exists('taskGetProjectColumnAccessMap')) {
    function taskGetProjectColumnAccessMap($connect, $projectId, $userId = 0, $useEffectiveAccess = true)
    {
        $projectId = (int) $projectId;
        $userId = (int) $userId;
        if ($projectId <= 0) {
            return array();
        }
        if ($userId <= 0) {
            $userId = defined('USER_ID') ? (int) USER_ID : 0;
        }
        if ($userId <= 0 || !defined('TASK_PROJECT_COLUMN_ACCESS')) {
            return array();
        }

        $fieldKeyMap = taskGetProjectAccessFieldKeyMap();
        $permissions = array();

        if ($useEffectiveAccess && taskUserHasFullProjectTaskAccess($connect, $projectId, $userId)) {
            foreach ($fieldKeyMap as $fieldKey => $fieldLabel) {
                $permissions[$fieldKey] = array(
                    'column_key' => $fieldKey,
                    'add' => 1,
                    'edit' => 1,
                    'delete' => 1,
                );
            }
            return $permissions;
        }

        $sql = "SELECT column_key, can_add, can_edit, can_delete
                FROM " . TASK_PROJECT_COLUMN_ACCESS . "
                WHERE project_id='" . $projectId . "' AND user_id='" . $userId . "' AND status='A'";
        $result = @mysqli_query($connect, $sql);
        if (!$result) {
            return array();
        }

        while ($row = $result->fetch_assoc()) {
            $fieldKey = isset($row['column_key']) ? strtolower(trim((string) $row['column_key'])) : '';
            if ($fieldKey === '' || !isset($fieldKeyMap[$fieldKey])) {
                continue;
            }
            $permissions[$fieldKey] = array(
                'column_key' => $fieldKey,
                'add' => isset($row['can_add']) ? (int) $row['can_add'] : 0,
                'edit' => isset($row['can_edit']) ? (int) $row['can_edit'] : 0,
                'delete' => isset($row['can_delete']) ? (int) $row['can_delete'] : 0,
            );
        }

        return $permissions;
    }
}

if (!function_exists('taskUserCanColumnFieldAction')) {
    function taskUserCanColumnFieldAction($connect, $projectId, $columnKey, $actionName, $userId = 0)
    {
        $columnKey = strtolower(trim((string) $columnKey));
        if ($columnKey === '') {
            return false;
        }

        $permissions = taskGetProjectColumnAccessMap($connect, $projectId, $userId);
        if (empty($permissions)) {
            return false;
        }

        if (!isset($permissions[$columnKey])) {
            return false;
        }

        $actionName = strtolower(trim((string) $actionName));
        if ($actionName === 'add') {
            return !empty($permissions[$columnKey]['add']);
        }
        if ($actionName === 'edit') {
            return !empty($permissions[$columnKey]['edit']);
        }
        if ($actionName === 'delete') {
            return !empty($permissions[$columnKey]['delete']);
        }

        return false;
    }
}

if (!function_exists('taskNormalizeProjectColumnValue')) {
    function taskNormalizeProjectColumnValue($columnKey, $value)
    {
        $columnKey = strtolower(trim((string) $columnKey));

        if ($columnKey === 'assignee_name' || $columnKey === 'reporter_name' || $columnKey === 'parent_display') {
            return (int) $value;
        }

        if ($columnKey === 'labels' || $columnKey === 'task_status') {
            $ids = array();

            if (is_array($value)) {
                $ids = taskUniquePositiveIntIds($value);
            } else {
                $ids = taskParseCsvIdList((string) $value);
            }

            sort($ids);
            return implode(',', $ids);
        }

        if ($columnKey === 'original_estimate') {
            if (is_array($value)) {
                $estimateValue = isset($value['value']) ? (int) $value['value'] : 0;
                $estimateUnit = taskNormalizeEstimateUnit(isset($value['unit']) ? $value['unit'] : 'minutes');
            } else {
                $estimate = taskParseOriginalEstimate((string) $value);
                $estimateValue = isset($estimate['value']) ? (int) $estimate['value'] : 0;
                $estimateUnit = isset($estimate['unit']) ? taskNormalizeEstimateUnit($estimate['unit']) : 'minutes';
            }

            if ($estimateValue <= 0) {
                return '';
            }

            return $estimateValue . ' ' . $estimateUnit;
        }

        if ($columnKey === 'time_tracking') {
            if (is_numeric($value)) {
                return max(0, (int) $value);
            }

            return max(0, taskParseWorklogDurationSeconds((string) $value));
        }

        if (is_array($value)) {
            $normalized = array_map(function ($item) {
                return trim((string) $item);
            }, $value);
            $normalized = array_values(array_filter($normalized, function ($item) {
                return $item !== '';
            }));
            sort($normalized);
            return implode(',', $normalized);
        }

        return trim((string) $value);
    }
}

if (!function_exists('taskIsProjectColumnValueEmpty')) {
    function taskIsProjectColumnValueEmpty($columnKey, $normalizedValue)
    {
        $columnKey = strtolower(trim((string) $columnKey));

        if ($columnKey === 'assignee_name' || $columnKey === 'reporter_name' || $columnKey === 'parent_display' || $columnKey === 'time_tracking') {
            return (int) $normalizedValue <= 0;
        }

        return trim((string) $normalizedValue) === '';
    }
}

if (!function_exists('taskResolveProjectColumnFieldAction')) {
    function taskResolveProjectColumnFieldAction($columnKey, $oldValue, $newValue)
    {
        $oldNormalized = taskNormalizeProjectColumnValue($columnKey, $oldValue);
        $newNormalized = taskNormalizeProjectColumnValue($columnKey, $newValue);

        if ((string) $oldNormalized === (string) $newNormalized) {
            return '';
        }

        $oldEmpty = taskIsProjectColumnValueEmpty($columnKey, $oldNormalized);
        $newEmpty = taskIsProjectColumnValueEmpty($columnKey, $newNormalized);

        if ($oldEmpty && !$newEmpty) {
            return 'add';
        }

        if (!$oldEmpty && $newEmpty) {
            return 'delete';
        }

        return 'edit';
    }
}

if (!function_exists('taskValidateProjectColumnFieldTransition')) {
    function taskValidateProjectColumnFieldTransition($connect, $projectId, $columnKey, $oldValue, $newValue, $userId = 0)
    {
        $columnKey = strtolower(trim((string) $columnKey));
        if ($columnKey === '') {
            return array('ok' => 0, 'message' => 'Invalid project field permission request.');
        }

        if ($columnKey === 'time_tracking') {
            return array('ok' => 1, 'action' => taskResolveProjectColumnFieldAction($columnKey, $oldValue, $newValue));
        }

        $actionName = taskResolveProjectColumnFieldAction($columnKey, $oldValue, $newValue);
        if ($actionName === '') {
            return array('ok' => 1, 'action' => '');
        }

        if (taskUserCanColumnFieldAction($connect, $projectId, $columnKey, $actionName, $userId)) {
            return array('ok' => 1, 'action' => $actionName);
        }

        $fieldKeyMap = taskGetProjectAccessFieldKeyMap();
        $fieldLabel = isset($fieldKeyMap[$columnKey]) ? (string) $fieldKeyMap[$columnKey] : ucwords(str_replace('_', ' ', $columnKey));
        $actionLabel = $actionName === 'delete' ? 'remove' : $actionName;

        return array(
            'ok' => 0,
            'action' => $actionName,
            'message' => 'You do not have permission to ' . $actionLabel . ' ' . $fieldLabel . '.',
        );
    }
}

if (!function_exists('taskGetProjectAccessUsers')) {
    function taskGetProjectAccessUsers($connect, $projectId = 0)
    {
        $rows = array();
        $projectId = (int) $projectId;
        $project = $projectId > 0 ? taskGetProjectById($connect, $projectId) : array();
        $ownerUserId = isset($project['owner_user_id']) ? (int) $project['owner_user_id'] : 0;

        $sql = "SELECT 
                    u.id,
                    COALESCE(NULLIF(TRIM(u.name), ''), u.username) AS display_name,
                    u.email,
                    u.access_id,
                    COALESCE(NULLIF(TRIM(g.name), ''), CONCAT('Group #', u.access_id)) AS user_group_name
                FROM " . USR_USER . " u
                LEFT JOIN " . USR_GRP . " g ON g.id = u.access_id
                WHERE u.status='A'
                ORDER BY display_name ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $userId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($userId <= 0 || $userId === $ownerUserId) {
                    continue;
                }

                $rows[] = array(
                    'id' => $userId,
                    'name' => isset($row['display_name']) ? (string) $row['display_name'] : '',
                    'email' => isset($row['email']) ? (string) $row['email'] : '',
                    'user_group_id' => isset($row['access_id']) ? (int) $row['access_id'] : 0,
                    'user_group_name' => isset($row['user_group_name']) ? (string) $row['user_group_name'] : '',
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskSaveProjectUserAccess')) {
    function taskSaveProjectUserAccess($connect, $projectId, $rowsByUser, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return array('ok' => 0, 'message' => 'Project not found.');
        }

        $currentUserId = (int) $currentUserId;
        if ($currentUserId <= 0 || !taskIsProjectOwner($connect, $projectId, $currentUserId)) {
            return array('ok' => 0, 'message' => 'Only the project owner can manage project user access.');
        }

        if (!defined('TASK_PROJECT_ITEM_ACCESS')) {
            return array('ok' => 0, 'message' => 'Project item access table is not configured. Please run insert_table.php.');
        }

        $workTypeMap = array();
        foreach (taskGetWorkTypes($connect, $projectId) as $workType) {
            $id = isset($workType['id']) ? (int) $workType['id'] : 0;
            if ($id > 0) {
                $workTypeMap[$id] = true;
            }
        }

        $statusMap = array();
        foreach (taskGetColumns($connect, $projectId) as $column) {
            $id = isset($column['id']) ? (int) $column['id'] : 0;
            if ($id > 0) {
                $statusMap[$id] = true;
            }
        }

        $fieldKeyMap = taskGetProjectAccessFieldKeyMap();

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);

        foreach ((array) $rowsByUser as $userId => $row) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }

            $workItemAdd = !empty($row['work_item_add']) ? 1 : 0;
            $workItemEdit = !empty($row['work_item_edit']) ? 1 : 0;
            $workItemDelete = !empty($row['work_item_delete']) ? 1 : 0;

            $allowedWorkTypeIds = array();
            foreach (taskUniquePositiveIntIds(isset($row['allowed_work_type_ids']) ? $row['allowed_work_type_ids'] : array()) as $workTypeId) {
                if (isset($workTypeMap[$workTypeId])) {
                    $allowedWorkTypeIds[] = $workTypeId;
                }
            }

            $allowedStatusIds = array();
            foreach (taskUniquePositiveIntIds(isset($row['allowed_status_ids']) ? $row['allowed_status_ids'] : array()) as $statusId) {
                if (isset($statusMap[$statusId])) {
                    $allowedStatusIds[] = $statusId;
                }
            }

            $columnPermissions = array();
            $rawColumnPermissions = isset($row['column_permissions']) && is_array($row['column_permissions'])
                ? $row['column_permissions']
                : array();

            foreach ($rawColumnPermissions as $fieldKey => $permissionRow) {
                $fieldKey = strtolower(trim((string) $fieldKey));
                if ($fieldKey === '' || !isset($fieldKeyMap[$fieldKey])) {
                    continue;
                }

                $canAdd = !empty($permissionRow['add']) ? 1 : 0;
                $canEdit = !empty($permissionRow['edit']) ? 1 : 0;
                $canDelete = !empty($permissionRow['delete']) ? 1 : 0;

                if (!$canAdd && !$canEdit && !$canDelete) {
                    continue;
                }

                $columnPermissions[$fieldKey] = array(
                    'add' => $canAdd,
                    'edit' => $canEdit,
                    'delete' => $canDelete,
                );
            }

            $hasAnyPermission = (
                $workItemAdd ||
                $workItemEdit ||
                $workItemDelete ||
                !empty($allowedWorkTypeIds) ||
                !empty($allowedStatusIds) ||
                !empty($columnPermissions)
            );

            $safeWorkTypeIds = taskEsc($connect, implode(',', $allowedWorkTypeIds));

            if (!$hasAnyPermission) {
                $deleteItemSql = "UPDATE " . TASK_PROJECT_ITEM_ACCESS . "
                    SET status='D',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                    WHERE project_id='" . $projectId . "'
                      AND user_id='" . $userId . "'";

                if (!mysqli_query($connect, $deleteItemSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to remove project item access.');
                }

                if (defined('TASK_PROJECT_COLUMN_ACCESS')) {
                    mysqli_query(
                        $connect,
                        "UPDATE " . TASK_PROJECT_COLUMN_ACCESS . "
                         SET status='D',
                             update_by='" . $safeUser . "',
                             update_date='" . $safeDate . "',
                             update_time='" . $safeTime . "'
                         WHERE project_id='" . $projectId . "'
                           AND user_id='" . $userId . "'"
                    );
                }

                if (defined('TASK_PROJECT_STATUS_ACCESS')) {
                    mysqli_query(
                        $connect,
                        "UPDATE " . TASK_PROJECT_STATUS_ACCESS . "
                         SET status='D',
                             update_by='" . $safeUser . "',
                             update_date='" . $safeDate . "',
                             update_time='" . $safeTime . "'
                         WHERE project_id='" . $projectId . "'
                           AND user_id='" . $userId . "'"
                    );
                }

                continue;
            }

            $insertItemSql = "INSERT INTO " . TASK_PROJECT_ITEM_ACCESS . " (
                project_id,
                user_id,
                can_add,
                can_edit,
                can_delete,
                allowed_work_type_ids,
                create_by,
                create_date,
                create_time,
                status
            ) VALUES (
                '" . $projectId . "',
                '" . $userId . "',
                '" . $workItemAdd . "',
                '" . $workItemEdit . "',
                '" . $workItemDelete . "',
                '" . $safeWorkTypeIds . "',
                '" . $safeUser . "',
                '" . $safeDate . "',
                '" . $safeTime . "',
                'A'
            )
            ON DUPLICATE KEY UPDATE
                can_add=VALUES(can_add),
                can_edit=VALUES(can_edit),
                can_delete=VALUES(can_delete),
                allowed_work_type_ids=VALUES(allowed_work_type_ids),
                status='A',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'";

            if (!mysqli_query($connect, $insertItemSql)) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to save project item access.');
            }

            if (defined('TASK_PROJECT_STATUS_ACCESS')) {
                $resetStatusSql = "UPDATE " . TASK_PROJECT_STATUS_ACCESS . "
                    SET status='D',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                    WHERE project_id='" . $projectId . "'
                      AND user_id='" . $userId . "'";

                if (!mysqli_query($connect, $resetStatusSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to reset project status access.');
                }

                foreach ($allowedStatusIds as $statusId) {
                    $statusId = (int) $statusId;

                    $insertStatusSql = "INSERT INTO " . TASK_PROJECT_STATUS_ACCESS . " (
                        project_id,
                        user_id,
                        from_status_id,
                        to_status_id,
                        can_move,
                        create_by,
                        create_date,
                        create_time,
                        status
                    ) VALUES (
                        '" . $projectId . "',
                        '" . $userId . "',
                        '0',
                        '" . $statusId . "',
                        '1',
                        '" . $safeUser . "',
                        '" . $safeDate . "',
                        '" . $safeTime . "',
                        'A'
                    )
                    ON DUPLICATE KEY UPDATE
                        can_move='1',
                        status='A',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'";

                    if (!mysqli_query($connect, $insertStatusSql)) {
                        mysqli_rollback($connect);
                        return array('ok' => 0, 'message' => 'Failed to save project status access.');
                    }
                }
            }

            if (defined('TASK_PROJECT_COLUMN_ACCESS')) {
                $resetColumnSql = "UPDATE " . TASK_PROJECT_COLUMN_ACCESS . "
                    SET status='D',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                    WHERE project_id='" . $projectId . "'
                      AND user_id='" . $userId . "'";

                if (!mysqli_query($connect, $resetColumnSql)) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed to reset project column access.');
                }

                foreach ($columnPermissions as $fieldKey => $permissionRow) {
                    $safeFieldKey = taskEsc($connect, $fieldKey);
                    $canAdd = !empty($permissionRow['add']) ? 1 : 0;
                    $canEdit = !empty($permissionRow['edit']) ? 1 : 0;
                    $canDelete = !empty($permissionRow['delete']) ? 1 : 0;

                    $insertColumnSql = "INSERT INTO " . TASK_PROJECT_COLUMN_ACCESS . " (
                        project_id,
                        user_id,
                        column_key,
                        can_add,
                        can_edit,
                        can_delete,
                        create_by,
                        create_date,
                        create_time,
                        status
                    ) VALUES (
                        '" . $projectId . "',
                        '" . $userId . "',
                        '" . $safeFieldKey . "',
                        '" . $canAdd . "',
                        '" . $canEdit . "',
                        '" . $canDelete . "',
                        '" . $safeUser . "',
                        '" . $safeDate . "',
                        '" . $safeTime . "',
                        'A'
                    )
                    ON DUPLICATE KEY UPDATE
                        can_add=VALUES(can_add),
                        can_edit=VALUES(can_edit),
                        can_delete=VALUES(can_delete),
                        status='A',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'";

                    if (!mysqli_query($connect, $insertColumnSql)) {
                        mysqli_rollback($connect);
                        return array('ok' => 0, 'message' => 'Failed to save project column access.');
                    }
                }
            }
        }

        mysqli_commit($connect);

        return array(
            'ok' => 1,
            'message' => 'Project user access updated successfully.',
        );
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
        $result = mysqli_query(
            $connect,
            "SELECT id,name,color FROM " . TASK_STATUS_LABEL . " WHERE status='A' AND id IN (" . $idSql . ")"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($id > 0 && $name !== '') {
                    $map[$id] = array(
                        'name' => $name,
                        'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#DCE8FF'),
                    );
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
                'name' => (string) $map[$id]['name'],
                'color' => (string) $map[$id]['color'],
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
        $projectId = taskGetItemProjectId($connect, $epicItemId);
        $summary = array(
            'items' => array(),
            'total' => 0,
            'done' => 0,
            'progress_percent' => 0,
            'time_tracking' => 'No time logged',
            'time_tracking_seconds' => 0,
            'original_estimate_seconds' => 0,
        );
        if ($epicItemId <= 0) {
            return $summary;
        }

        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $lastColumnSortOrder = 0;

        $lastColumnSql = "SELECT MAX(sort_order) AS max_sort_order FROM " . TASK_COLUMN . " WHERE status='A'";
        if ($projectId > 0) {
            $lastColumnSql .= " AND project_id='" . $projectId . "'";
        }
        $lastColumnRst = mysqli_query($connect, $lastColumnSql);
        if ($lastColumnRst && $lastColumnRst->num_rows > 0) {
            $lastColumnRow = $lastColumnRst->fetch_assoc();
            $lastColumnSortOrder = isset($lastColumnRow['max_sort_order']) ? (int) $lastColumnRow['max_sort_order'] : 0;
        }

        $sql = "SELECT id,title,priority,assignee_user_id,sort_order,time_tracking,original_estimate,column_id,project_key_id
                FROM " . TASK_ITEM . "
                WHERE status='A' AND (
                    parent_item_id='" . $epicItemId . "'
                    OR id IN (
                        SELECT r.child_board_item_id
                        FROM " . TASK_ITEM_RELATION . " r
                        WHERE r.parent_board_item_id='" . $epicItemId . "' AND r.status='A'
                    )
                )";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $summary;
        }

        $itemRows = array();
        $columnIds = array();
        $projectKeyIds = array();
        $assigneeIds = array();
        while ($row = $result->fetch_assoc()) {
            $itemRows[] = $row;
            $columnIds[] = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $assigneeIds[] = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
        }

        $columnMap = taskFetchColumnInfoMap($connect, $columnIds, true);
        $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, true);
        $assigneeMap = taskFetchUserDisplayMap($connect, $assigneeIds, true);

        foreach ($itemRows as $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($itemId <= 0) {
                continue;
            }

            $projectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $projectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
            if ($projectKey === '') {
                $projectKey = taskNormalizeProjectKey($defaultProjectKey);
            }

            $columnId = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            $columnInfo = isset($columnMap[$columnId]) ? $columnMap[$columnId] : array('name' => '', 'sort_order' => 0);
            $statusName = isset($columnInfo['name']) ? trim((string) $columnInfo['name']) : '';
            $columnSortOrder = isset($columnInfo['sort_order']) ? (int) $columnInfo['sort_order'] : 0;
            $isDone = taskIsDoneColumnName($statusName)
                || ($lastColumnSortOrder > 0 && $columnSortOrder >= $lastColumnSortOrder);
            if ($isDone) {
                $summary['done']++;
            }

            $timeTracking = isset($row['time_tracking']) ? trim((string) $row['time_tracking']) : '';
            $timeTrackingSeconds = taskParseWorklogDurationSeconds($timeTracking);
            $summary['time_tracking_seconds'] += $timeTrackingSeconds;
            $estimateInfo = taskParseOriginalEstimate(isset($row['original_estimate']) ? $row['original_estimate'] : '');
            $summary['original_estimate_seconds'] += taskEstimateToSeconds(
                isset($estimateInfo['value']) ? $estimateInfo['value'] : 0,
                isset($estimateInfo['unit']) ? $estimateInfo['unit'] : 'minutes'
            );

            $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;

            $summary['items'][] = array(
                'id' => $itemId,
                'work_item_key' => taskBuildWorkItemKey($projectKey, $itemId),
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'priority' => taskNormalizePriority(isset($row['priority']) ? $row['priority'] : 'Medium'),
                'assignee_user_id' => $assigneeUserId,
                'assignee_name' => isset($assigneeMap[$assigneeUserId]) ? (string) $assigneeMap[$assigneeUserId] : '',
                'column_id' => $columnId,
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
    function taskCreateStatusLabel($connect, $labelName, $currentUserId, $cdate, $ctime, $color = '')
    {
        $labelName = trim((string) $labelName);
        if ($labelName === '') {
            return array('ok' => 0, 'message' => 'Task status name is required.');
        }

        $safeName = taskEsc($connect, substr($labelName, 0, 120));
        $normalizedColor = taskNormalizeHexColor($color, '#DCE8FF');
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
                mysqli_query($connect, "UPDATE " . TASK_STATUS_LABEL . " SET status='A', color='" . taskEsc($connect, $normalizedColor) . "', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $labelId . "'");
            }

            return array('ok' => 1, 'message' => 'Task status ready.', 'statusLabel' => array('id' => $labelId, 'name' => $labelName, 'color' => $normalizedColor));
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

        $insertSql = "INSERT INTO " . TASK_STATUS_LABEL . " (name,color,sort_order,create_by,create_date,create_time,status)
                      VALUES ('" . $safeName . "','" . taskEsc($connect, $normalizedColor) . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create task status label.');
        }

        return array(
            'ok' => 1,
            'message' => 'Task status label created successfully.',
            'statusLabel' => array(
                'id' => (int) mysqli_insert_id($connect),
                'name' => $labelName,
                'color' => $normalizedColor,
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

if (!function_exists('taskEstimateToSeconds')) {
    function taskEstimateToSeconds($value, $unit)
    {
        $amount = max(0, (int) $value);
        $normalizedUnit = taskNormalizeEstimateUnit($unit);

        if ($normalizedUnit === 'weeks') {
            return $amount * 604800;
        }
        if ($normalizedUnit === 'days') {
            return $amount * 86400;
        }
        if ($normalizedUnit === 'hours') {
            return $amount * 3600;
        }

        return $amount * 60;
    }
}

if (!function_exists('taskResolveOwnRemainingEstimateSeconds')) {
    function taskResolveOwnRemainingEstimateSeconds($estimateSeconds, $loggedSeconds, $storedRemainingSeconds = null)
    {
        $estimateSeconds = max(0, (int) $estimateSeconds);
        $loggedSeconds = max(0, (int) $loggedSeconds);

        if ($storedRemainingSeconds !== null && $storedRemainingSeconds !== '' && is_numeric($storedRemainingSeconds)) {
            return max(0, (int) $storedRemainingSeconds);
        }

        return max(0, $estimateSeconds - $loggedSeconds);
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
        $result = mysqli_query($connect, $sql);
        if ($result === false) {
            $sql = "SELECT id,url,title AS link_text,title,create_date,create_time
                    FROM " . TASK_ITEM_URL . "
                    WHERE item_id='" . $itemId . "' AND status='A'
                    ORDER BY id DESC";
            $result = mysqli_query($connect, $sql);
        }

        if (!$result) {
            return array();
        }

        while ($row = $result->fetch_assoc()) {
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

        $result = mysqli_query($connect, "SELECT id,item_id,url,link_text,title FROM " . TASK_ITEM_URL . " WHERE id='" . $urlId . "' AND status='A' LIMIT 1");
        if ($result === false) {
            return array('ok' => 0, 'message' => 'Failed deleting web link. Please run insert_table.php first.');
        }

        if ($result->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Web link not found.');
        }

        $row = $result->fetch_assoc();
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
    function taskGetEpicParentOptions($connect, $excludeChildItemId = 0, $projectId = 0)
    {
        $excludeChildItemId = (int) $excludeChildItemId;
        $projectId = (int) $projectId;
        $options = array();
        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

        $allWorkTypes = array();
        $epicTypeIds = array();
        $workTypeSql = "SELECT id,name,svg_icon FROM " . TASK_WORK_TYPE . " WHERE status='A'";
        if ($projectId > 0) {
            $workTypeSql .= " AND project_id='" . $projectId . "'";
        }
        $workTypeRst = mysqli_query($connect, $workTypeSql);
        if ($workTypeRst) {
            while ($wt = $workTypeRst->fetch_assoc()) {
                $wtId = isset($wt['id']) ? (int) $wt['id'] : 0;
                if ($wtId <= 0) {
                    continue;
                }
                $wtName = isset($wt['name']) ? trim((string) $wt['name']) : 'Task';
                if ($wtName === '') {
                    $wtName = 'Task';
                }
                $allWorkTypes[$wtId] = array(
                    'name' => $wtName,
                    'svg_icon' => taskNormalizeWorkTypeSvgIcon(isset($wt['svg_icon']) ? $wt['svg_icon'] : '', $wtName),
                );
                if (strtolower($wtName) === 'epic') {
                    $epicTypeIds[] = $wtId;
                }
            }
        }

        $epicTypeIds = taskUniquePositiveIntIds($epicTypeIds);
        if (empty($epicTypeIds)) {
            return $options;
        }

        $sql = "SELECT id,title,project_key_id,work_type_id
                FROM " . TASK_ITEM . "
                WHERE status='A' AND work_type_id IN (" . implode(',', $epicTypeIds) . ")";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        if ($excludeChildItemId > 0) {
            $sql .= " AND id <> '" . $excludeChildItemId . "'";
        }
        $sql .= " ORDER BY id DESC";

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $options;
        }

        $rows = array();
        $projectKeyIds = array();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
        }

        $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, true);

        foreach ($rows as $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            $projectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $itemKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
            if ($itemKey === '') {
                $itemKey = taskNormalizeProjectKey($defaultProjectKey);
            }

            $workTypeId = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
            $workTypeInfo = isset($allWorkTypes[$workTypeId]) ? $allWorkTypes[$workTypeId] : array(
                'name' => 'Epic',
                'svg_icon' => taskDefaultWorkTypeSvgIcon('Epic'),
            );

            $options[] = array(
                'id' => $itemId,
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'work_item_key' => taskBuildWorkItemKey($itemKey, $itemId),
                'work_type_name' => isset($workTypeInfo['name']) ? (string) $workTypeInfo['name'] : 'Epic',
                'work_type_svg_icon' => isset($workTypeInfo['svg_icon']) ? (string) $workTypeInfo['svg_icon'] : taskDefaultWorkTypeSvgIcon('Epic'),
            );
        }

        return $options;
    }
}

if (!function_exists('taskGetParentRelationInfo')) {
    function taskGetParentRelationInfo($connect, $childItemId)
    {
        $childItemId = (int) $childItemId;
        $projectId = taskGetItemProjectId($connect, $childItemId);
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

        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

        $buildParentInfo = function ($parentItemId) use ($connect, $defaultProjectKey, $projectId, &$info) {
            $parentItemId = (int) $parentItemId;
            if ($parentItemId <= 0) {
                return false;
            }

            $parentRst = mysqli_query(
                $connect,
                "SELECT id,title,project_key_id,work_type_id FROM " . TASK_ITEM . " WHERE id='" . $parentItemId . "' AND status='A'" . ($projectId > 0 ? " AND project_id='" . $projectId . "'" : '') . " LIMIT 1"
            );
            if (!$parentRst || $parentRst->num_rows === 0) {
                return false;
            }

            $parentRow = $parentRst->fetch_assoc();
            $projectKeyId = isset($parentRow['project_key_id']) ? (int) $parentRow['project_key_id'] : 0;
            $workTypeId = isset($parentRow['work_type_id']) ? (int) $parentRow['work_type_id'] : 0;
            $projectKeyMap = taskFetchProjectKeyMap($connect, array($projectKeyId), true);
            $workTypeMap = taskFetchWorkTypeInfoMap($connect, array($workTypeId), true);

            $parentProjectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
            if ($parentProjectKey === '') {
                $parentProjectKey = taskNormalizeProjectKey($defaultProjectKey);
            }

            $workTypeInfo = isset($workTypeMap[$workTypeId]) ? $workTypeMap[$workTypeId] : array(
                'name' => 'Task',
                'svg_icon' => taskDefaultWorkTypeSvgIcon('Task'),
            );

            $parentKey = taskBuildWorkItemKey($parentProjectKey, $parentItemId);
            $parentTitle = isset($parentRow['title']) ? trim((string) $parentRow['title']) : '';
            $parentTypeName = isset($workTypeInfo['name']) ? (string) $workTypeInfo['name'] : 'Task';

            $info['parent_item_id'] = $parentItemId;
            $info['parent_work_item_key'] = $parentKey;
            $info['parent_work_type_name'] = $parentTypeName;
            $info['parent_work_type_svg_icon'] = isset($workTypeInfo['svg_icon']) ? (string) $workTypeInfo['svg_icon'] : taskDefaultWorkTypeSvgIcon($parentTypeName);
            $info['parent_display'] = trim(($parentKey !== '' ? $parentKey . ' ' : '') . $parentTitle);
            if ($info['parent_display'] === '') {
                $info['parent_display'] = 'None';
            }

            return true;
        };

        $result = mysqli_query(
            $connect,
            "SELECT parent_board_item_id FROM " . TASK_ITEM_RELATION . " WHERE child_board_item_id='" . $childItemId . "' AND status='A' LIMIT 1"
        );
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $parentItemId = isset($row['parent_board_item_id']) ? (int) $row['parent_board_item_id'] : 0;
            if ($buildParentInfo($parentItemId)) {
                return $info;
            }
        }

        $fallbackRst = mysqli_query(
            $connect,
            "SELECT parent_item_id FROM " . TASK_ITEM . " WHERE id='" . $childItemId . "' AND status='A' LIMIT 1"
        );
        if ($fallbackRst && $fallbackRst->num_rows > 0) {
            $row = $fallbackRst->fetch_assoc();
            $parentItemId = isset($row['parent_item_id']) ? (int) $row['parent_item_id'] : 0;
            $buildParentInfo($parentItemId);
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
        $foundChildIds = array();
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $childId = isset($row['child_board_item_id']) ? (int) $row['child_board_item_id'] : 0;
                $parentId = isset($row['parent_board_item_id']) ? (int) $row['parent_board_item_id'] : 0;
                if ($childId > 0 && $parentId > 0) {
                    $map[$childId] = $parentId;
                    $foundChildIds[$childId] = 1;
                }
            }
        }

        $missingChildIds = array();
        foreach ($childIds as $childId) {
            $childId = (int) $childId;
            if ($childId > 0 && !isset($foundChildIds[$childId])) {
                $missingChildIds[] = $childId;
            }
        }

        if (empty($missingChildIds)) {
            return $map;
        }

        $missingIdSql = implode(',', $missingChildIds);
        $fallbackSql = "SELECT id,parent_item_id FROM " . TASK_ITEM . " WHERE status='A' AND id IN (" . $missingIdSql . ")";
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

        $childSql = "SELECT id,work_type_id FROM " . TASK_ITEM . " WHERE id='" . $childItemId . "' AND status='A' LIMIT 1";
        $childRst = mysqli_query($connect, $childSql);
        if (!$childRst || $childRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $childRow = $childRst->fetch_assoc();
        $childWorkTypeId = isset($childRow['work_type_id']) ? (int) $childRow['work_type_id'] : 0;
        $childWorkTypeMap = taskFetchWorkTypeInfoMap($connect, array($childWorkTypeId), true);
        $childType = isset($childWorkTypeMap[$childWorkTypeId]['name']) ? strtolower(trim((string) $childWorkTypeMap[$childWorkTypeId]['name'])) : '';
        if ($childType === 'epic') {
            return array('ok' => 0, 'message' => 'Epic work item cannot be linked as child.');
        }

        $previousParentInfo = taskGetParentRelationInfo($connect, $childItemId);

        if ($parentItemId > 0) {
            if ($parentItemId === $childItemId) {
                return array('ok' => 0, 'message' => 'A work item cannot link itself as parent.');
            }

            $parentSql = "SELECT id,work_type_id FROM " . TASK_ITEM . " WHERE id='" . $parentItemId . "' AND status='A' LIMIT 1";
            $parentRst = mysqli_query($connect, $parentSql);
            if (!$parentRst || $parentRst->num_rows === 0) {
                return array('ok' => 0, 'message' => 'Selected parent work item not found.');
            }

            $parentRow = $parentRst->fetch_assoc();
            $parentWorkTypeId = isset($parentRow['work_type_id']) ? (int) $parentRow['work_type_id'] : 0;
            $parentWorkTypeMap = taskFetchWorkTypeInfoMap($connect, array($parentWorkTypeId), true);
            $parentType = isset($parentWorkTypeMap[$parentWorkTypeId]['name']) ? strtolower(trim((string) $parentWorkTypeMap[$parentWorkTypeId]['name'])) : '';
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

if (!function_exists('taskIsParentWorkTypeName')) {
    function taskIsParentWorkTypeName($workTypeName)
    {
        return strtolower(trim((string) $workTypeName)) === 'epic';
    }
}

if (!function_exists('taskGetDefaultChildColumnId')) {
    function taskGetDefaultChildColumnId($connect, $projectId, $userId = 0)
    {
        $projectId = (int) $projectId;
        $userId = (int) $userId;
        if ($projectId <= 0) {
            return 0;
        }
        if ($userId <= 0 && defined('USER_ID')) {
            $userId = (int) USER_ID;
        }

        $columns = taskGetColumns($connect, $projectId);
        if (empty($columns)) {
            return 0;
        }

        $hasFullAccess = $userId > 0
            ? taskUserHasFullProjectTaskAccess($connect, $projectId, $userId)
            : true;
        $allowedStatusIds = $hasFullAccess || $userId <= 0
            ? array()
            : taskUserAllowedStatusIds($connect, $projectId, $userId);

        foreach ($columns as $column) {
            $columnId = isset($column['id']) ? (int) $column['id'] : 0;
            if ($columnId <= 0) {
                continue;
            }
            if ($hasFullAccess || empty($allowedStatusIds) || in_array($columnId, $allowedStatusIds, true)) {
                return $columnId;
            }
        }

        return 0;
    }
}

if (!function_exists('taskGetDefaultChildWorkTypeId')) {
    function taskGetDefaultChildWorkTypeId($connect, $projectId, $userId = 0)
    {
        $projectId = (int) $projectId;
        $userId = (int) $userId;
        if ($projectId <= 0) {
            return 0;
        }
        if ($userId <= 0 && defined('USER_ID')) {
            $userId = (int) USER_ID;
        }

        $workTypes = taskGetWorkTypes($connect, $projectId);
        if (empty($workTypes)) {
            return 0;
        }

        $hasFullAccess = $userId > 0
            ? taskUserHasFullProjectTaskAccess($connect, $projectId, $userId)
            : true;
        $allowedWorkTypeIds = $hasFullAccess || $userId <= 0
            ? array()
            : taskUserAllowedWorkTypeIds($connect, $projectId, $userId);

        $candidates = array();
        foreach ($workTypes as $workType) {
            $workTypeId = isset($workType['id']) ? (int) $workType['id'] : 0;
            $workTypeName = isset($workType['name']) ? (string) $workType['name'] : '';
            if ($workTypeId <= 0 || taskIsParentWorkTypeName($workTypeName)) {
                continue;
            }
            if (!$hasFullAccess && !empty($allowedWorkTypeIds) && !in_array($workTypeId, $allowedWorkTypeIds, true)) {
                continue;
            }
            $candidates[] = array(
                'id' => $workTypeId,
                'name' => strtolower(trim((string) $workTypeName)),
            );
        }

        foreach ($candidates as $candidate) {
            if ($candidate['name'] === 'task') {
                return (int) $candidate['id'];
            }
        }

        return !empty($candidates) ? (int) $candidates[0]['id'] : 0;
    }
}

if (!function_exists('taskGetChildWorkItemIds')) {
    function taskGetChildWorkItemIds($connect, $parentItemId, $projectId = 0)
    {
        $parentItemId = (int) $parentItemId;
        $projectId = (int) $projectId;
        $childIds = array();

        if ($parentItemId <= 0) {
            return $childIds;
        }

        $sql = "SELECT id FROM " . TASK_ITEM . " WHERE status='A' AND parent_item_id='" . $parentItemId . "'";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $childId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($childId > 0) {
                    $childIds[] = $childId;
                }
            }
        }

        if (defined('TASK_ITEM_RELATION')) {
            $relationSql = "SELECT child_board_item_id FROM " . TASK_ITEM_RELATION . " WHERE status='A' AND parent_board_item_id='" . $parentItemId . "'";
            $relationRst = mysqli_query($connect, $relationSql);
            if ($relationRst) {
                while ($row = $relationRst->fetch_assoc()) {
                    $childId = isset($row['child_board_item_id']) ? (int) $row['child_board_item_id'] : 0;
                    if ($childId > 0) {
                        $childIds[] = $childId;
                    }
                }
            }
        }

        return taskUniquePositiveIntIds($childIds);
    }
}

if (!function_exists('taskWouldCreateParentChildCycle')) {
    function taskWouldCreateParentChildCycle($connect, $projectId, $parentItemId, $childItemId)
    {
        $projectId = (int) $projectId;
        $parentItemId = (int) $parentItemId;
        $childItemId = (int) $childItemId;

        if ($parentItemId <= 0 || $childItemId <= 0) {
            return false;
        }
        if ($parentItemId === $childItemId) {
            return true;
        }

        $seen = array();
        $currentParentId = $parentItemId;
        while ($currentParentId > 0) {
            if ($currentParentId === $childItemId) {
                return true;
            }
            if (isset($seen[$currentParentId])) {
                break;
            }
            $seen[$currentParentId] = 1;

            $parentInfo = taskGetParentRelationInfo($connect, $currentParentId);
            $nextParentId = isset($parentInfo['parent_item_id']) ? (int) $parentInfo['parent_item_id'] : 0;
            if ($nextParentId <= 0 || ($projectId > 0 && taskGetItemProjectId($connect, $nextParentId) !== $projectId)) {
                break;
            }
            $currentParentId = $nextParentId;
        }

        return false;
    }
}

if (!function_exists('taskGetLinkRelationTypes')) {
    function taskGetLinkRelationTypes()
    {
        return array(
            'is blocked by',
            'blocks',
            'is cloned by',
            'is connected to',
            'connects to',
            'is duplicated by',
            'duplicates',
            'add to idea',
            'is idea for',
            'merged into',
            'merged from',
            'is implemented by',
            'implements',
            'is caused by',
            'causes',
            'relates to',
        );
    }
}

if (!function_exists('taskGetLinkRelationInverseMap')) {
    function taskGetLinkRelationInverseMap()
    {
        return array(
            'is blocked by' => 'blocks',
            'blocks' => 'is blocked by',
            'is cloned by' => 'is cloned by',
            'is connected to' => 'connects to',
            'connects to' => 'is connected to',
            'is duplicated by' => 'duplicates',
            'duplicates' => 'is duplicated by',
            'add to idea' => 'is idea for',
            'is idea for' => 'add to idea',
            'merged into' => 'merged from',
            'merged from' => 'merged into',
            'is implemented by' => 'implements',
            'implements' => 'is implemented by',
            'is caused by' => 'causes',
            'causes' => 'is caused by',
            'relates to' => 'relates to',
        );
    }
}

if (!function_exists('taskNormalizeLinkRelationType')) {
    function taskNormalizeLinkRelationType($relationType)
    {
        $normalized = strtolower(trim((string) $relationType));
        if ($normalized === '') {
            return '';
        }

        $allowed = taskGetLinkRelationTypes();
        foreach ($allowed as $allowedType) {
            if ($normalized === strtolower((string) $allowedType)) {
                return (string) $allowedType;
            }
        }

        return '';
    }
}

if (!function_exists('taskGetInverseLinkRelationType')) {
    function taskGetInverseLinkRelationType($relationType)
    {
        $normalized = taskNormalizeLinkRelationType($relationType);
        if ($normalized === '') {
            return '';
        }

        $map = taskGetLinkRelationInverseMap();
        return isset($map[$normalized]) ? (string) $map[$normalized] : $normalized;
    }
}

if (!function_exists('taskBuildWorkItemSummaryMap')) {
    function taskBuildWorkItemSummaryMap($connect, $projectId, $itemIds, $onlyActive = true)
    {
        $projectId = (int) $projectId;
        $itemIds = taskUniquePositiveIntIds($itemIds);
        $map = array();

        if (empty($itemIds)) {
            return $map;
        }

        $sql = "SELECT id,project_id,column_id,title,work_type_id,project_key_id,assignee_user_id,parent_item_id
                FROM " . TASK_ITEM . "
                WHERE id IN (" . implode(',', $itemIds) . ")";
        if ($onlyActive) {
            $sql .= " AND status='A'";
        }
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }

        $rows = array();
        $workTypeIds = array();
        $projectKeyIds = array();
        $assigneeIds = array();
        $columnIds = array();
        $projectIds = array();

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
            $workTypeIds[] = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
            $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $assigneeIds[] = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
            $columnIds[] = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            $projectIds[] = isset($row['project_id']) ? (int) $row['project_id'] : 0;
        }

        $workTypeMap = taskFetchWorkTypeInfoMap($connect, $workTypeIds, $onlyActive);
        $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, $onlyActive);
        $assigneeMap = taskFetchUserDisplayMap($connect, $assigneeIds, true);
        $columnMap = taskFetchColumnInfoMap($connect, $columnIds, true);
        $defaultProjectKeyMap = array();

        foreach ($projectIds as $rowProjectId) {
            $rowProjectId = (int) $rowProjectId;
            if ($rowProjectId <= 0 || isset($defaultProjectKeyMap[$rowProjectId])) {
                continue;
            }
            $projectKeySetting = taskGetProjectKeySetting($connect, $rowProjectId);
            $defaultProjectKeyMap[$rowProjectId] = isset($projectKeySetting['project_key'])
                ? (string) $projectKeySetting['project_key']
                : '';
        }

        foreach ($rows as $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($itemId <= 0) {
                continue;
            }

            $rowProjectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $workTypeId = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
            $projectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
            $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
            $columnId = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            $workTypeInfo = isset($workTypeMap[$workTypeId]) ? $workTypeMap[$workTypeId] : array(
                'name' => 'Task',
                'svg_icon' => taskDefaultWorkTypeSvgIcon('Task'),
            );
            $workTypeName = isset($workTypeInfo['name']) ? (string) $workTypeInfo['name'] : 'Task';
            $projectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
            if ($projectKey === '') {
                $projectKey = isset($defaultProjectKeyMap[$rowProjectId]) ? (string) $defaultProjectKeyMap[$rowProjectId] : '';
            }
            $columnInfo = isset($columnMap[$columnId]) ? $columnMap[$columnId] : array();

            $map[$itemId] = array(
                'id' => $itemId,
                'project_id' => $rowProjectId,
                'column_id' => $columnId,
                'status_name' => isset($columnInfo['name']) ? (string) $columnInfo['name'] : '',
                'status_color' => isset($columnInfo['color']) ? (string) $columnInfo['color'] : '#DFE1E6',
                'title' => isset($row['title']) ? (string) $row['title'] : '',
                'work_type_id' => $workTypeId,
                'work_type_name' => $workTypeName,
                'work_type_svg_icon' => isset($workTypeInfo['svg_icon']) ? (string) $workTypeInfo['svg_icon'] : taskDefaultWorkTypeSvgIcon($workTypeName),
                'work_item_key' => taskBuildWorkItemKey($projectKey, $itemId),
                'assignee_user_id' => $assigneeUserId,
                'assignee_name' => isset($assigneeMap[$assigneeUserId]) ? (string) $assigneeMap[$assigneeUserId] : '',
                'parent_item_id' => isset($row['parent_item_id']) ? (int) $row['parent_item_id'] : 0,
            );
        }

        return $map;
    }
}

if (!function_exists('taskResolveWorkItemFromUrlOrKey')) {
    function taskResolveWorkItemFromUrlOrKey($connect, $projectId, $value)
    {
        $projectId = (int) $projectId;
        $value = trim((string) $value);
        if ($projectId <= 0 || $value === '') {
            return array();
        }

        $normalizeKey = function ($keyText) {
            return strtoupper(trim(preg_replace('/\s+/', '', (string) $keyText)));
        };

        $resolvedItemId = 0;
        $expectedKey = '';

        if (preg_match('/(?:^|[^0-9])task-item-(\d+)(?:$|[^0-9])/i', $value, $match)) {
            $resolvedItemId = isset($match[1]) ? (int) $match[1] : 0;
        }

        if ($resolvedItemId <= 0 && preg_match('/(?:^|[?&#])(item_id|id)=(\d+)(?:$|[&#])/i', $value, $match)) {
            $resolvedItemId = isset($match[2]) ? (int) $match[2] : 0;
        }

        if (preg_match('/([A-Z][A-Z0-9\-]{0,19}-\d+)/i', $value, $keyMatch)) {
            $expectedKey = $normalizeKey(isset($keyMatch[1]) ? $keyMatch[1] : '');
            if ($resolvedItemId <= 0 && preg_match('/-(\d+)$/', $expectedKey, $idMatch)) {
                $resolvedItemId = isset($idMatch[1]) ? (int) $idMatch[1] : 0;
            }
        }

        if ($resolvedItemId <= 0 && preg_match('/^\d+$/', $value)) {
            $resolvedItemId = (int) $value;
        }

        if ($resolvedItemId <= 0) {
            return array();
        }

        $map = taskBuildWorkItemSummaryMap($connect, $projectId, array($resolvedItemId), true);
        if (!isset($map[$resolvedItemId])) {
            return array();
        }

        $resolved = $map[$resolvedItemId];
        if ($expectedKey !== '') {
            $actualKey = $normalizeKey(isset($resolved['work_item_key']) ? $resolved['work_item_key'] : '');
            if ($actualKey === '' || $actualKey !== $expectedKey) {
                return array();
            }
        }

        return $resolved;
    }
}

if (!function_exists('taskSearchChildWorkItems')) {
    function taskSearchChildWorkItems($connect, $projectId, $parentItemId, $keyword)
    {
        $projectId = (int) $projectId;
        $parentItemId = (int) $parentItemId;
        $keyword = trim((string) $keyword);
        $rows = array();

        if ($projectId <= 0 || $parentItemId <= 0 || $keyword === '') {
            return $rows;
        }

        $parentMap = taskBuildWorkItemSummaryMap($connect, $projectId, array($parentItemId), true);
        if (!isset($parentMap[$parentItemId]) || !taskIsParentWorkTypeName(isset($parentMap[$parentItemId]['work_type_name']) ? $parentMap[$parentItemId]['work_type_name'] : '')) {
            return $rows;
        }

        $excludedIds = array_fill_keys(taskGetChildWorkItemIds($connect, $parentItemId, $projectId), true);
        $excludedIds[$parentItemId] = true;

        $resolved = taskResolveWorkItemFromUrlOrKey($connect, $projectId, $keyword);
        if (!empty($resolved)) {
            $resolvedId = isset($resolved['id']) ? (int) $resolved['id'] : 0;
            if (
                $resolvedId > 0
                && !isset($excludedIds[$resolvedId])
                && !taskIsParentWorkTypeName(isset($resolved['work_type_name']) ? $resolved['work_type_name'] : '')
                && !taskWouldCreateParentChildCycle($connect, $projectId, $parentItemId, $resolvedId)
            ) {
                return array($resolved);
            }
        }

        $likeKeyword = taskEsc($connect, str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $keyword));
        $searchSql = "SELECT id
                      FROM " . TASK_ITEM . "
                      WHERE status='A'
                        AND project_id='" . $projectId . "'
                        AND id <> '" . $parentItemId . "'
                        AND title LIKE '%" . $likeKeyword . "%' ESCAPE '\\'
                      ORDER BY id DESC
                      LIMIT 20";
        $result = mysqli_query($connect, $searchSql);
        if (!$result) {
            return $rows;
        }

        $candidateIds = array();
        while ($row = $result->fetch_assoc()) {
            $candidateId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($candidateId > 0) {
                $candidateIds[] = $candidateId;
            }
        }

        if (empty($candidateIds)) {
            return $rows;
        }

        $summaryMap = taskBuildWorkItemSummaryMap($connect, $projectId, $candidateIds, true);
        foreach ($candidateIds as $candidateId) {
            if (
                isset($excludedIds[$candidateId])
                || !isset($summaryMap[$candidateId])
                || taskIsParentWorkTypeName(isset($summaryMap[$candidateId]['work_type_name']) ? $summaryMap[$candidateId]['work_type_name'] : '')
                || taskWouldCreateParentChildCycle($connect, $projectId, $parentItemId, $candidateId)
            ) {
                continue;
            }
            $rows[] = $summaryMap[$candidateId];
        }

        return $rows;
    }
}

if (!function_exists('taskCreateChildWorkItem')) {
    function taskCreateChildWorkItem($connect, $projectId, $parentItemId, $title, $workTypeId, $userId)
    {
        $projectId = (int) $projectId;
        $parentItemId = (int) $parentItemId;
        $workTypeId = (int) $workTypeId;
        $userId = (int) $userId;
        $title = trim((string) $title);

        $logDate = defined('date_dis') ? date_dis : date('Y-m-d');
        $logTime = defined('time_dis') ? time_dis : date('H:i:s');

        if ($projectId <= 0 || $parentItemId <= 0 || $title === '') {
            return array('ok' => 0, 'message' => 'Child work item title is required.');
        }

        $parentMap = taskBuildWorkItemSummaryMap($connect, $projectId, array($parentItemId), true);
        if (!isset($parentMap[$parentItemId])) {
            return array('ok' => 0, 'message' => 'Parent work item not found.');
        }
        if (!taskIsParentWorkTypeName(isset($parentMap[$parentItemId]['work_type_name']) ? $parentMap[$parentItemId]['work_type_name'] : '')) {
            return array('ok' => 0, 'message' => 'Only parent work items can create child work items.');
        }

        $columnId = taskGetDefaultChildColumnId($connect, $projectId, $userId);
        if ($columnId <= 0) {
            return array('ok' => 0, 'message' => 'No active status is available to create the child work item.');
        }

        if ($workTypeId <= 0) {
            $workTypeId = taskGetDefaultChildWorkTypeId($connect, $projectId, $userId);
        }
        if ($workTypeId <= 0) {
            return array('ok' => 0, 'message' => 'No child work type is available for this project.');
        }

        $hasFullAccess = $userId > 0
            ? taskUserHasFullProjectTaskAccess($connect, $projectId, $userId)
            : true;
        $allowedWorkTypeIds = $hasFullAccess || $userId <= 0
            ? array()
            : taskUserAllowedWorkTypeIds($connect, $projectId, $userId);
        if (!$hasFullAccess && (empty($allowedWorkTypeIds) || !in_array($workTypeId, $allowedWorkTypeIds, true))) {
            return array('ok' => 0, 'message' => 'You do not have access to use that task type in this project.');
        }

        $workTypeMap = taskFetchWorkTypeInfoMap($connect, array($workTypeId), true);
        if (!isset($workTypeMap[$workTypeId])) {
            return array('ok' => 0, 'message' => 'Selected work type is not available.');
        }
        if (taskIsParentWorkTypeName(isset($workTypeMap[$workTypeId]['name']) ? $workTypeMap[$workTypeId]['name'] : '')) {
            return array('ok' => 0, 'message' => 'Parent work types cannot be created as child work items.');
        }

        $createResult = taskCreateItem(
            $connect,
            $projectId,
            $columnId,
            $title,
            $workTypeId,
            0,
            '',
            $userId,
            $logDate,
            $logTime
        );
        if (empty($createResult['ok'])) {
            return $createResult;
        }

        $childItemId = isset($createResult['item']['id']) ? (int) $createResult['item']['id'] : 0;
        if ($childItemId <= 0) {
            return array('ok' => 0, 'message' => 'Failed creating child work item.');
        }

        $linkResult = taskSetItemParentRelation($connect, $childItemId, $parentItemId, $userId, $logDate, $logTime);
        if (empty($linkResult['ok'])) {
            $safeUser = taskEsc($connect, $userId);
            $safeDate = taskEsc($connect, $logDate);
            $safeTime = taskEsc($connect, $logTime);
            mysqli_query(
                $connect,
                "UPDATE " . TASK_ITEM . " SET
                    status='D',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                 WHERE id='" . $childItemId . "' AND status='A'"
            );
            return array('ok' => 0, 'message' => isset($linkResult['message']) ? (string) $linkResult['message'] : 'Failed linking child work item.');
        }

        $childDisplay = trim(
            (isset($createResult['item']['work_item_key']) ? (string) $createResult['item']['work_item_key'] : '')
            . ' ' .
            (isset($createResult['item']['title']) ? (string) $createResult['item']['title'] : $title)
        );
        taskLogItemHistory(
            $connect,
            $parentItemId,
            'link_child_work_item',
            'Child work item',
            '',
            $childDisplay,
            'linked child work item',
            $userId,
            $logDate,
            $logTime
        );

        return array(
            'ok' => 1,
            'message' => 'Child work item created successfully.',
            'item' => isset($createResult['item']) ? $createResult['item'] : array(),
            'child_work_items' => taskGetEpicChildWorkItemsSummary($connect, $parentItemId),
        );
    }
}

if (!function_exists('taskLinkExistingChildWorkItem')) {
    function taskLinkExistingChildWorkItem($connect, $projectId, $parentItemId, $childItemId, $userId)
    {
        $projectId = (int) $projectId;
        $parentItemId = (int) $parentItemId;
        $childItemId = (int) $childItemId;
        $userId = (int) $userId;
        $logDate = defined('date_dis') ? date_dis : date('Y-m-d');
        $logTime = defined('time_dis') ? time_dis : date('H:i:s');

        if ($projectId <= 0 || $parentItemId <= 0 || $childItemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid child link request.');
        }

        $summaryMap = taskBuildWorkItemSummaryMap($connect, $projectId, array($parentItemId, $childItemId), true);
        if (!isset($summaryMap[$parentItemId])) {
            return array('ok' => 0, 'message' => 'Parent work item not found.');
        }
        if (!taskIsParentWorkTypeName(isset($summaryMap[$parentItemId]['work_type_name']) ? $summaryMap[$parentItemId]['work_type_name'] : '')) {
            return array('ok' => 0, 'message' => 'Only parent work items can link child work items.');
        }
        if (!isset($summaryMap[$childItemId])) {
            return array('ok' => 0, 'message' => 'Selected child work item not found.');
        }
        if ($parentItemId === $childItemId) {
            return array('ok' => 0, 'message' => 'A work item cannot link itself as child.');
        }
        if (taskIsParentWorkTypeName(isset($summaryMap[$childItemId]['work_type_name']) ? $summaryMap[$childItemId]['work_type_name'] : '')) {
            return array('ok' => 0, 'message' => 'Parent work types cannot be linked as child work items.');
        }

        $currentChildIds = taskGetChildWorkItemIds($connect, $parentItemId, $projectId);
        if (in_array($childItemId, $currentChildIds, true)) {
            return array('ok' => 0, 'message' => 'This work item is already linked as a child.');
        }
        if (taskWouldCreateParentChildCycle($connect, $projectId, $parentItemId, $childItemId)) {
            return array('ok' => 0, 'message' => 'This child link would create a circular relationship.');
        }

        $linkResult = taskSetItemParentRelation($connect, $childItemId, $parentItemId, $userId, $logDate, $logTime);
        if (empty($linkResult['ok'])) {
            return $linkResult;
        }

        $childDisplay = trim(
            (isset($summaryMap[$childItemId]['work_item_key']) ? (string) $summaryMap[$childItemId]['work_item_key'] : '')
            . ' ' .
            (isset($summaryMap[$childItemId]['title']) ? (string) $summaryMap[$childItemId]['title'] : '')
        );
        taskLogItemHistory(
            $connect,
            $parentItemId,
            'link_child_work_item',
            'Child work item',
            '',
            $childDisplay,
            'linked child work item',
            $userId,
            $logDate,
            $logTime
        );

        return array(
            'ok' => 1,
            'message' => 'Child work item linked successfully.',
            'child_work_items' => taskGetEpicChildWorkItemsSummary($connect, $parentItemId),
        );
    }
}

if (!function_exists('taskItemLinkExists')) {
    function taskItemLinkExists($connect, $projectId, $sourceItemId, $targetItemId, $relationType, $excludeLinkId = 0)
    {
        $projectId = (int) $projectId;
        $sourceItemId = (int) $sourceItemId;
        $targetItemId = (int) $targetItemId;
        $excludeLinkId = (int) $excludeLinkId;
        $normalizedRelationType = taskNormalizeLinkRelationType($relationType);

        if ($projectId <= 0 || $sourceItemId <= 0 || $targetItemId <= 0 || $normalizedRelationType === '' || !defined('TASK_ITEM_LINK')) {
            return false;
        }

        $inverseRelationType = taskGetInverseLinkRelationType($normalizedRelationType);
        $safeRelationType = taskEsc($connect, $normalizedRelationType);
        $safeInverseRelationType = taskEsc($connect, $inverseRelationType);

        $sql = "SELECT id
                FROM " . TASK_ITEM_LINK . "
                WHERE project_id='" . $projectId . "'
                  AND status='A'
                  AND (
                        (source_item_id='" . $sourceItemId . "' AND target_item_id='" . $targetItemId . "' AND relation_type='" . $safeRelationType . "')
                        OR
                        (source_item_id='" . $targetItemId . "' AND target_item_id='" . $sourceItemId . "' AND relation_type='" . $safeInverseRelationType . "')
                  )";
        if ($excludeLinkId > 0) {
            $sql .= " AND id <> '" . $excludeLinkId . "'";
        }
        $sql .= " LIMIT 1";

        $result = mysqli_query($connect, $sql);
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('taskSearchLinkWorkItems')) {
    function taskSearchLinkWorkItems($connect, $projectId, $currentItemId, $keyword, $relationType = '')
    {
        $projectId = (int) $projectId;
        $currentItemId = (int) $currentItemId;
        $keyword = trim((string) $keyword);
        $normalizedRelationType = taskNormalizeLinkRelationType($relationType);
        $rows = array();

        if ($projectId <= 0 || $currentItemId <= 0 || $keyword === '') {
            return $rows;
        }

        $currentMap = taskBuildWorkItemSummaryMap($connect, $projectId, array($currentItemId), true);
        if (!isset($currentMap[$currentItemId])) {
            return $rows;
        }

        $resolved = taskResolveWorkItemFromUrlOrKey($connect, $projectId, $keyword);
        if (!empty($resolved)) {
            $resolvedId = isset($resolved['id']) ? (int) $resolved['id'] : 0;
            if (
                $resolvedId > 0
                && $resolvedId !== $currentItemId
                && ($normalizedRelationType === '' || !taskItemLinkExists($connect, $projectId, $currentItemId, $resolvedId, $normalizedRelationType))
            ) {
                return array($resolved);
            }
        }

        $likeKeyword = taskEsc($connect, str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $keyword));
        $searchSql = "SELECT id
                      FROM " . TASK_ITEM . "
                      WHERE status='A'
                        AND project_id='" . $projectId . "'
                        AND id <> '" . $currentItemId . "'
                        AND title LIKE '%" . $likeKeyword . "%' ESCAPE '\\'
                      ORDER BY id DESC
                      LIMIT 20";
        $result = mysqli_query($connect, $searchSql);
        if (!$result) {
            return $rows;
        }

        $candidateIds = array();
        while ($row = $result->fetch_assoc()) {
            $candidateId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($candidateId > 0) {
                $candidateIds[] = $candidateId;
            }
        }

        if (empty($candidateIds)) {
            return $rows;
        }

        $summaryMap = taskBuildWorkItemSummaryMap($connect, $projectId, $candidateIds, true);
        foreach ($candidateIds as $candidateId) {
            if (
                $candidateId === $currentItemId
                || !isset($summaryMap[$candidateId])
                || ($normalizedRelationType !== '' && taskItemLinkExists($connect, $projectId, $currentItemId, $candidateId, $normalizedRelationType))
            ) {
                continue;
            }
            $rows[] = $summaryMap[$candidateId];
        }

        return $rows;
    }
}

if (!function_exists('taskCreateItemLink')) {
    function taskCreateItemLink($connect, $projectId, $sourceItemId, $targetItemId, $relationType, $userId)
    {
        $projectId = (int) $projectId;
        $sourceItemId = (int) $sourceItemId;
        $targetItemId = (int) $targetItemId;
        $userId = (int) $userId;
        $normalizedRelationType = taskNormalizeLinkRelationType($relationType);
        $logDate = defined('date_dis') ? date_dis : date('Y-m-d');
        $logTime = defined('time_dis') ? time_dis : date('H:i:s');

        if ($projectId <= 0 || $sourceItemId <= 0 || $targetItemId <= 0 || $normalizedRelationType === '') {
            return array('ok' => 0, 'message' => 'Invalid linked work item request.');
        }
        if ($sourceItemId === $targetItemId) {
            return array('ok' => 0, 'message' => 'A work item cannot be linked to itself.');
        }

        $summaryMap = taskBuildWorkItemSummaryMap($connect, $projectId, array($sourceItemId, $targetItemId), true);
        if (!isset($summaryMap[$sourceItemId]) || !isset($summaryMap[$targetItemId])) {
            return array('ok' => 0, 'message' => 'Linked work item not found.');
        }
        if (taskItemLinkExists($connect, $projectId, $sourceItemId, $targetItemId, $normalizedRelationType)) {
            return array('ok' => 0, 'message' => 'This linked work item relation already exists.');
        }

        $safeRelationType = taskEsc($connect, $normalizedRelationType);
        $safeUser = taskEsc($connect, $userId);
        $safeDate = taskEsc($connect, $logDate);
        $safeTime = taskEsc($connect, $logTime);
        $insertSql = "INSERT INTO " . TASK_ITEM_LINK . "
                      (project_id,source_item_id,target_item_id,relation_type,create_by,create_date,create_time,status)
                      VALUES
                      ('" . $projectId . "','" . $sourceItemId . "','" . $targetItemId . "','" . $safeRelationType . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed creating linked work item relation.');
        }

        $targetDisplay = trim(
            (isset($summaryMap[$targetItemId]['work_item_key']) ? (string) $summaryMap[$targetItemId]['work_item_key'] : '')
            . ' ' .
            (isset($summaryMap[$targetItemId]['title']) ? (string) $summaryMap[$targetItemId]['title'] : '')
        );
        $sourceDisplay = trim(
            (isset($summaryMap[$sourceItemId]['work_item_key']) ? (string) $summaryMap[$sourceItemId]['work_item_key'] : '')
            . ' ' .
            (isset($summaryMap[$sourceItemId]['title']) ? (string) $summaryMap[$sourceItemId]['title'] : '')
        );
        taskLogItemHistory(
            $connect,
            $sourceItemId,
            'create_item_link',
            'Linked work item',
            '',
            $normalizedRelationType . ' ' . $targetDisplay,
            'added linked work item',
            $userId,
            $logDate,
            $logTime
        );
        taskLogItemHistory(
            $connect,
            $targetItemId,
            'create_item_link',
            'Linked work item',
            '',
            taskGetInverseLinkRelationType($normalizedRelationType) . ' ' . $sourceDisplay,
            'added linked work item',
            $userId,
            $logDate,
            $logTime
        );

        return array(
            'ok' => 1,
            'message' => 'Linked work item added successfully.',
            'link_id' => (int) mysqli_insert_id($connect),
        );
    }
}

if (!function_exists('taskDeleteItemLink')) {
    function taskDeleteItemLink($connect, $projectId, $linkId, $userId)
    {
        $projectId = (int) $projectId;
        $linkId = (int) $linkId;
        $userId = (int) $userId;
        $logDate = defined('date_dis') ? date_dis : date('Y-m-d');
        $logTime = defined('time_dis') ? time_dis : date('H:i:s');

        if ($projectId <= 0 || $linkId <= 0 || !defined('TASK_ITEM_LINK')) {
            return array('ok' => 0, 'message' => 'Invalid linked work item removal request.');
        }

        $sql = "SELECT id,source_item_id,target_item_id,relation_type
                FROM " . TASK_ITEM_LINK . "
                WHERE id='" . $linkId . "'
                  AND project_id='" . $projectId . "'
                  AND status='A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if (!$result || $result->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Linked work item relation not found.');
        }

        $row = $result->fetch_assoc();
        $sourceItemId = isset($row['source_item_id']) ? (int) $row['source_item_id'] : 0;
        $targetItemId = isset($row['target_item_id']) ? (int) $row['target_item_id'] : 0;
        $relationType = taskNormalizeLinkRelationType(isset($row['relation_type']) ? $row['relation_type'] : '');
        if ($sourceItemId <= 0 || $targetItemId <= 0 || $relationType === '') {
            return array('ok' => 0, 'message' => 'Linked work item relation is invalid.');
        }

        $safeUser = taskEsc($connect, $userId);
        $safeDate = taskEsc($connect, $logDate);
        $safeTime = taskEsc($connect, $logTime);
        $updateSql = "UPDATE " . TASK_ITEM_LINK . " SET
                        status='D',
                        update_by='" . $safeUser . "',
                        update_date='" . $safeDate . "',
                        update_time='" . $safeTime . "'
                      WHERE id='" . $linkId . "' AND status='A'";
        if (!mysqli_query($connect, $updateSql)) {
            return array('ok' => 0, 'message' => 'Failed removing linked work item.');
        }

        $summaryMap = taskBuildWorkItemSummaryMap($connect, $projectId, array($sourceItemId, $targetItemId), true);
        $targetDisplay = trim(
            (isset($summaryMap[$targetItemId]['work_item_key']) ? (string) $summaryMap[$targetItemId]['work_item_key'] : '')
            . ' ' .
            (isset($summaryMap[$targetItemId]['title']) ? (string) $summaryMap[$targetItemId]['title'] : '')
        );
        $sourceDisplay = trim(
            (isset($summaryMap[$sourceItemId]['work_item_key']) ? (string) $summaryMap[$sourceItemId]['work_item_key'] : '')
            . ' ' .
            (isset($summaryMap[$sourceItemId]['title']) ? (string) $summaryMap[$sourceItemId]['title'] : '')
        );
        taskLogItemHistory(
            $connect,
            $sourceItemId,
            'delete_item_link',
            'Linked work item',
            $relationType . ' ' . $targetDisplay,
            '',
            'removed linked work item',
            $userId,
            $logDate,
            $logTime
        );
        taskLogItemHistory(
            $connect,
            $targetItemId,
            'delete_item_link',
            'Linked work item',
            taskGetInverseLinkRelationType($relationType) . ' ' . $sourceDisplay,
            '',
            'removed linked work item',
            $userId,
            $logDate,
            $logTime
        );

        return array(
            'ok' => 1,
            'message' => 'Linked work item removed successfully.',
            'source_item_id' => $sourceItemId,
            'target_item_id' => $targetItemId,
        );
    }
}

if (!function_exists('taskGetItemLinks')) {
    function taskGetItemLinks($connect, $projectId, $itemId)
    {
        $projectId = (int) $projectId;
        $itemId = (int) $itemId;
        $groups = array();

        if ($projectId <= 0 || $itemId <= 0 || !defined('TASK_ITEM_LINK')) {
            return array('groups' => array(), 'total' => 0);
        }

        $sql = "SELECT id,source_item_id,target_item_id,relation_type
                FROM " . TASK_ITEM_LINK . "
                WHERE project_id='" . $projectId . "'
                  AND status='A'
                  AND (source_item_id='" . $itemId . "' OR target_item_id='" . $itemId . "')
                ORDER BY id DESC";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return array('groups' => array(), 'total' => 0);
        }

        $rows = array();
        $relatedItemIds = array();
        while ($row = $result->fetch_assoc()) {
            $sourceItemId = isset($row['source_item_id']) ? (int) $row['source_item_id'] : 0;
            $targetItemId = isset($row['target_item_id']) ? (int) $row['target_item_id'] : 0;
            $relationType = taskNormalizeLinkRelationType(isset($row['relation_type']) ? $row['relation_type'] : '');
            if ($sourceItemId <= 0 || $targetItemId <= 0 || $relationType === '') {
                continue;
            }

            $isOutgoing = $sourceItemId === $itemId;
            $relatedItemId = $isOutgoing ? $targetItemId : $sourceItemId;
            if ($relatedItemId <= 0 || $relatedItemId === $itemId) {
                continue;
            }

            $rows[] = array(
                'link_id' => isset($row['id']) ? (int) $row['id'] : 0,
                'source_item_id' => $sourceItemId,
                'target_item_id' => $targetItemId,
                'relation_type' => $relationType,
                'display_relation_type' => $isOutgoing ? $relationType : taskGetInverseLinkRelationType($relationType),
                'related_item_id' => $relatedItemId,
                'direction' => $isOutgoing ? 'outgoing' : 'incoming',
            );
            $relatedItemIds[] = $relatedItemId;
        }

        if (empty($rows)) {
            return array('groups' => array(), 'total' => 0);
        }

        $summaryMap = taskBuildWorkItemSummaryMap($connect, $projectId, $relatedItemIds, true);
        $groupOrder = array_flip(taskGetLinkRelationTypes());
        $grouped = array();

        foreach ($rows as $row) {
            $relatedItemId = isset($row['related_item_id']) ? (int) $row['related_item_id'] : 0;
            if (!isset($summaryMap[$relatedItemId])) {
                continue;
            }

            $groupKey = isset($row['display_relation_type']) ? (string) $row['display_relation_type'] : 'relates to';
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = array();
            }

            $summary = $summaryMap[$relatedItemId];
            $grouped[$groupKey][] = array(
                'link_id' => isset($row['link_id']) ? (int) $row['link_id'] : 0,
                'relation_type' => isset($row['relation_type']) ? (string) $row['relation_type'] : '',
                'display_relation_type' => $groupKey,
                'direction' => isset($row['direction']) ? (string) $row['direction'] : 'outgoing',
                'id' => $relatedItemId,
                'project_id' => isset($summary['project_id']) ? (int) $summary['project_id'] : $projectId,
                'work_type_id' => isset($summary['work_type_id']) ? (int) $summary['work_type_id'] : 0,
                'work_type_name' => isset($summary['work_type_name']) ? (string) $summary['work_type_name'] : 'Task',
                'work_type_svg_icon' => isset($summary['work_type_svg_icon']) ? (string) $summary['work_type_svg_icon'] : taskDefaultWorkTypeSvgIcon('Task'),
                'work_item_key' => isset($summary['work_item_key']) ? (string) $summary['work_item_key'] : '',
                'title' => isset($summary['title']) ? (string) $summary['title'] : '',
                'column_id' => isset($summary['column_id']) ? (int) $summary['column_id'] : 0,
                'status_name' => isset($summary['status_name']) ? (string) $summary['status_name'] : '',
                'status_color' => isset($summary['status_color']) ? (string) $summary['status_color'] : '#DFE1E6',
                'assignee_user_id' => isset($summary['assignee_user_id']) ? (int) $summary['assignee_user_id'] : 0,
                'assignee_name' => isset($summary['assignee_name']) ? (string) $summary['assignee_name'] : '',
            );
        }

        if (empty($grouped)) {
            return array('groups' => array(), 'total' => 0);
        }

        uksort($grouped, function ($left, $right) use ($groupOrder) {
            $leftOrder = isset($groupOrder[$left]) ? (int) $groupOrder[$left] : PHP_INT_MAX;
            $rightOrder = isset($groupOrder[$right]) ? (int) $groupOrder[$right] : PHP_INT_MAX;
            if ($leftOrder === $rightOrder) {
                return strcmp((string) $left, (string) $right);
            }
            return $leftOrder < $rightOrder ? -1 : 1;
        });

        $total = 0;
        foreach ($grouped as $groupKey => $groupItems) {
            $groups[] = array(
                'relation_type' => (string) $groupKey,
                'items' => array_values($groupItems),
            );
            $total += count($groupItems);
        }

        return array(
            'groups' => $groups,
            'total' => $total,
        );
    }
}

if (!function_exists('taskGetItemDetail')) {
    if (!function_exists('taskBuildItemTimeTrackingDetail')) {
        function taskBuildItemTimeTrackingDetail($ownTimeTracking, $childWorkItems, $ownEstimateSeconds = 0, $ownRemainingSeconds = null)
        {
            $ownTimeTracking = trim((string) $ownTimeTracking);
            $ownSeconds = taskParseWorklogDurationSeconds($ownTimeTracking);
            $ownEstimateSeconds = max(0, (int) $ownEstimateSeconds);
            $ownRemainingSeconds = taskResolveOwnRemainingEstimateSeconds($ownEstimateSeconds, $ownSeconds, $ownRemainingSeconds);
            $childSeconds = is_array($childWorkItems) && isset($childWorkItems['time_tracking_seconds'])
                ? (int) $childWorkItems['time_tracking_seconds']
                : 0;
            $childEstimateSeconds = is_array($childWorkItems) && isset($childWorkItems['original_estimate_seconds'])
                ? (int) $childWorkItems['original_estimate_seconds']
                : 0;
            $childRemainingSeconds = max(0, $childEstimateSeconds - $childSeconds);
            $canIncludeChild = is_array($childWorkItems) && isset($childWorkItems['total']) && (int) $childWorkItems['total'] > 0;
            $combinedSeconds = $ownSeconds + $childSeconds;
            $combinedRemainingSeconds = $ownRemainingSeconds + ($canIncludeChild ? $childRemainingSeconds : 0);

            return array(
                'time_tracking' => $canIncludeChild
                    ? ($combinedSeconds > 0 ? taskFormatWorklogDuration($combinedSeconds) : 'No time logged')
                    : ($ownSeconds > 0 ? taskFormatWorklogDuration($ownSeconds) : 'No time logged'),
                'own_time_tracking' => $ownSeconds > 0 ? taskFormatWorklogDuration($ownSeconds) : 'No time logged',
                'own_time_tracking_seconds' => $ownSeconds,
                'own_remaining_seconds' => $ownRemainingSeconds,
                'own_estimate_seconds' => $ownEstimateSeconds,
                'child_time_tracking' => $childSeconds > 0 ? taskFormatWorklogDuration($childSeconds) : 'No time logged',
                'child_time_tracking_seconds' => $childSeconds,
                'child_original_estimate_seconds' => $childEstimateSeconds,
                'child_remaining_seconds' => $childRemainingSeconds,
                'combined_time_tracking' => $combinedSeconds > 0 ? taskFormatWorklogDuration($combinedSeconds) : 'No time logged',
                'combined_time_tracking_seconds' => $combinedSeconds,
                'combined_remaining_seconds' => $combinedRemainingSeconds,
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

        $sql = "SELECT id,project_id,column_id,title,description,description_color_html,work_type_id,project_key_id,assignee_user_id,reporter_user_id,
               priority,original_estimate,remaining_estimate_seconds,task_status,parent_item_id,time_tracking,
               due_date,start_date,amendement_date,amendement_time,second_amendement_date,second_amendement_time,
               create_date,create_time,update_date,update_time
            FROM " . TASK_ITEM . "
            WHERE id='" . $itemId . "' AND status='A' LIMIT 1";

        $result = mysqli_query($connect, $sql);
        if ($result === false) {
                     $sql = "SELECT id,project_id,column_id,title,'' AS description,'' AS description_color_html,work_type_id,0 AS project_key_id,assignee_user_id,0 AS reporter_user_id,
                         'Medium' AS priority,'' AS original_estimate,NULL AS remaining_estimate_seconds,'' AS task_status,0 AS parent_item_id,'' AS time_tracking,
                         due_date,due_date AS start_date,NULL AS amendement_date,NULL AS amendement_time,NULL AS second_amendement_date,NULL AS second_amendement_time,
                         '' AS create_date,'' AS create_time,'' AS update_date,'' AS update_time
                      FROM " . TASK_ITEM . "
                      WHERE id='" . $itemId . "' AND status='A' LIMIT 1";
            $result = mysqli_query($connect, $sql);
        }

        if (!$result || $result->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $row = $result->fetch_assoc();
        $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
        $workTypeId = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
        $projectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
        $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
        $reporterUserId = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
        $workTypeMap = taskFetchWorkTypeInfoMap($connect, array($workTypeId), true);
        $projectKeyMap = taskFetchProjectKeyMap($connect, array($projectKeyId), true);
        $userMap = taskFetchUserDisplayMap($connect, array($assigneeUserId, $reporterUserId), true);

        $estimate = taskParseOriginalEstimate(isset($row['original_estimate']) ? $row['original_estimate'] : '');
        $labelsMap = taskGetItemLabelsByItemIds($connect, array($itemId));
        $labels = isset($labelsMap[$itemId]) ? $labelsMap[$itemId] : array();
        $parentInfo = taskGetParentRelationInfo($connect, $itemId);
        $parentItemId = isset($parentInfo['parent_item_id']) ? (int) $parentInfo['parent_item_id'] : 0;
        $statusSelection = taskResolveStatusLabelSelection(
            $connect,
            isset($row['task_status']) && $row['task_status'] !== null ? (string) $row['task_status'] : ''
        );
        $workTypeName = isset($workTypeMap[$workTypeId]['name']) ? (string) $workTypeMap[$workTypeId]['name'] : 'Task';
        $workTypeIcon = isset($workTypeMap[$workTypeId]['svg_icon']) ? (string) $workTypeMap[$workTypeId]['svg_icon'] : taskDefaultWorkTypeSvgIcon($workTypeName);
        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $itemProjectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
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

        $ownEstimateSeconds = taskEstimateToSeconds(
            isset($estimate['value']) ? (int) $estimate['value'] : 0,
            isset($estimate['unit']) ? (string) $estimate['unit'] : 'minutes'
        );
        $storedRemainingSeconds = isset($row['remaining_estimate_seconds']) && $row['remaining_estimate_seconds'] !== null
            ? (int) $row['remaining_estimate_seconds']
            : null;
        $timeTrackingDetail = taskBuildItemTimeTrackingDetail(
            isset($row['time_tracking']) ? $row['time_tracking'] : '',
            $childWorkItems,
            $ownEstimateSeconds,
            $storedRemainingSeconds
        );

        $normalizedDueDate = taskNormalizeBoardDate(isset($row['due_date']) ? $row['due_date'] : '');
        $normalizedStartDate = taskNormalizeBoardDate(isset($row['start_date']) ? $row['start_date'] : '');
        $normalizedAmendementDate = taskNormalizeBoardDate(isset($row['amendement_date']) ? $row['amendement_date'] : '');
        $normalizedSecondAmendementDate = taskNormalizeBoardDate(isset($row['second_amendement_date']) ? $row['second_amendement_date'] : '');

        $detail = array(
            'id' => $itemId,
            'column_id' => isset($row['column_id']) ? (int) $row['column_id'] : 0,
            'title' => isset($row['title']) ? (string) $row['title'] : '',
            'description' => isset($row['description_color_html']) && trim((string) $row['description_color_html']) !== ''
                ? (string) $row['description_color_html']
                : (isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : ''),
            'assignee_user_id' => $assigneeUserId,
            'assignee_name' => isset($userMap[$assigneeUserId]) ? (string) $userMap[$assigneeUserId] : '',
            'reporter_user_id' => $reporterUserId,
            'reporter_name' => isset($userMap[$reporterUserId]) ? (string) $userMap[$reporterUserId] : '',
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
            'own_remaining_seconds' => isset($timeTrackingDetail['own_remaining_seconds']) ? (int) $timeTrackingDetail['own_remaining_seconds'] : 0,
            'own_estimate_seconds' => isset($timeTrackingDetail['own_estimate_seconds']) ? (int) $timeTrackingDetail['own_estimate_seconds'] : 0,
            'child_time_tracking' => isset($timeTrackingDetail['child_time_tracking']) ? (string) $timeTrackingDetail['child_time_tracking'] : 'No time logged',
            'child_time_tracking_seconds' => isset($timeTrackingDetail['child_time_tracking_seconds']) ? (int) $timeTrackingDetail['child_time_tracking_seconds'] : 0,
            'child_original_estimate_seconds' => isset($timeTrackingDetail['child_original_estimate_seconds']) ? (int) $timeTrackingDetail['child_original_estimate_seconds'] : 0,
            'child_remaining_seconds' => isset($timeTrackingDetail['child_remaining_seconds']) ? (int) $timeTrackingDetail['child_remaining_seconds'] : 0,
            'combined_time_tracking' => isset($timeTrackingDetail['combined_time_tracking']) ? (string) $timeTrackingDetail['combined_time_tracking'] : 'No time logged',
            'combined_time_tracking_seconds' => isset($timeTrackingDetail['combined_time_tracking_seconds']) ? (int) $timeTrackingDetail['combined_time_tracking_seconds'] : 0,
            'combined_remaining_seconds' => isset($timeTrackingDetail['combined_remaining_seconds']) ? (int) $timeTrackingDetail['combined_remaining_seconds'] : 0,
            'can_include_child_time_tracking' => isset($timeTrackingDetail['can_include_child_time_tracking']) ? (int) $timeTrackingDetail['can_include_child_time_tracking'] : 0,
            'include_child_time_tracking' => isset($timeTrackingDetail['include_child_time_tracking']) ? (int) $timeTrackingDetail['include_child_time_tracking'] : 0,
            'due_date' => $normalizedDueDate,
            'start_date' => $normalizedStartDate,
            'create_date' => isset($row['create_date']) && $row['create_date'] !== null ? (string) $row['create_date'] : '',
            'create_time' => isset($row['create_time']) && $row['create_time'] !== null ? (string) $row['create_time'] : '',
            'update_date' => isset($row['update_date']) && $row['update_date'] !== null ? (string) $row['update_date'] : '',
            'update_time' => isset($row['update_time']) && $row['update_time'] !== null ? (string) $row['update_time'] : '',
            'amendement_date' => $normalizedAmendementDate,
            'amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['amendement_time']) ? $row['amendement_time'] : ''),
            'second_amendement_date' => $normalizedSecondAmendementDate,
            'second_amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['second_amendement_time']) ? $row['second_amendement_time'] : ''),
            'labels' => $labels,
            'is_parent_type' => $isEpic ? 1 : 0,
            'child_work_items' => $childWorkItems,
            'item_links' => taskGetItemLinks($connect, $projectId, $itemId),
        );

        if ($detail['start_date'] === '' && $detail['due_date'] !== '') {
            $detail['start_date'] = $detail['due_date'];
        }

        return array(
            'ok' => 1,
            'detail' => $detail,
            'statusLabels' => taskGetStatusLabels($connect),
            'parentOptions' => taskGetEpicParentOptions($connect, $itemId, $projectId),
            'webLinks' => taskGetItemUrls($connect, $itemId),
            'itemLinks' => isset($detail['item_links']) ? $detail['item_links'] : array('groups' => array(), 'total' => 0),
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
                    remaining_estimate_seconds,
                    time_tracking,
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
        if ($itemRst === false) {
            $itemRst = mysqli_query(
                $connect,
                "SELECT id,
                        assignee_user_id,
                        reporter_user_id,
                        priority,
                        original_estimate,
                        NULL AS remaining_estimate_seconds,
                        time_tracking,
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
        }
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

        $dueDate = taskNormalizeBoardDate($dueDate);
        $startDate = taskNormalizeBoardDate($startDate);
        $amendementDate = taskNormalizeBoardDate($amendementDate);
        $secondAmendementDate = taskNormalizeBoardDate($secondAmendementDate);

        if ($dueDate !== '') {
            $safeDueDate = "'" . taskEsc($connect, $dueDate) . "'";
        }
        if ($startDate !== '') {
            $safeStartDate = "'" . taskEsc($connect, $startDate) . "'";
        } elseif ($safeDueDate !== 'NULL') {
            $safeStartDate = $safeDueDate;
        }
        if ($amendementDate !== '') {
            $safeAmendementDate = "'" . taskEsc($connect, $amendementDate) . "'";
        }
        if ($secondAmendementDate !== '') {
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
        $ownLoggedSeconds = taskParseWorklogDurationSeconds(isset($existingRow['time_tracking']) ? (string) $existingRow['time_tracking'] : '');
        $estimateSeconds = taskEstimateToSeconds($originalEstimateValue, $estimateUnit);
        $newRemainingEstimateSeconds = taskResolveOwnRemainingEstimateSeconds(
            $estimateSeconds,
            $ownLoggedSeconds,
            isset($existingRow['remaining_estimate_seconds']) ? $existingRow['remaining_estimate_seconds'] : null
        );
        $safeRemainingEstimateSeconds = taskEsc($connect, (string) $newRemainingEstimateSeconds);

        $updateSql = "UPDATE " . TASK_ITEM . " SET
                        assignee_user_id='" . $assigneeUserId . "',
                        reporter_user_id='" . $reporterUserId . "',
                        priority='" . $safePriority . "',
                        original_estimate='" . $safeEstimate . "',
                        remaining_estimate_seconds='" . $safeRemainingEstimateSeconds . "',
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

        $oldStartDate = taskNormalizeBoardDate(isset($existingRow['start_date']) ? $existingRow['start_date'] : '');
        $oldDueDate = taskNormalizeBoardDate(isset($existingRow['due_date']) ? $existingRow['due_date'] : '');
        $oldAmendementDate = taskNormalizeBoardDate(isset($existingRow['amendement_date']) ? $existingRow['amendement_date'] : '');
        $oldSecondAmendementDate = taskNormalizeBoardDate(isset($existingRow['second_amendement_date']) ? $existingRow['second_amendement_date'] : '');
        $oldAmendementTimeMinutes = taskSqlTimeToMinutes(isset($existingRow['amendement_time']) ? $existingRow['amendement_time'] : '');
        $oldSecondAmendementTimeMinutes = taskSqlTimeToMinutes(isset($existingRow['second_amendement_time']) ? $existingRow['second_amendement_time'] : '');

        if ($oldAssigneeUserId !== $assigneeUserId) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Assignee', taskFormatHistoryUserValue($connect, $oldAssigneeUserId), taskFormatHistoryUserValue($connect, $assigneeUserId), 'changed Assignee', $currentUserId, $cdate, $ctime);
            taskSendAssigneeReassignmentAlert($connect, $itemId, $oldAssigneeUserId, $assigneeUserId, $currentUserId, $cdate, $ctime);
        }
        if ($oldReporterUserId !== $reporterUserId) {
            taskLogItemHistory($connect, $itemId, 'update_field', 'Reporter', taskFormatHistoryUserValue($connect, $oldReporterUserId), taskFormatHistoryUserValue($connect, $reporterUserId), 'changed Reporter', $currentUserId, $cdate, $ctime);
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
        $itemIds = taskUniquePositiveIntIds($itemIds);

        if (empty($itemIds)) {
            return $map;
        }

        $idSql = implode(',', $itemIds);
        $sql = "SELECT item_id,label_id
                FROM " . TASK_ITEM_LABEL . "
                WHERE status='A' AND item_id IN (" . $idSql . ")";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $map;
        }

        $pairs = array();
        $labelIds = array();
        while ($row = $result->fetch_assoc()) {
            $itemId = isset($row['item_id']) ? (int) $row['item_id'] : 0;
            $labelId = isset($row['label_id']) ? (int) $row['label_id'] : 0;
            if ($itemId <= 0 || $labelId <= 0) {
                continue;
            }
            $pairs[] = array('item_id' => $itemId, 'label_id' => $labelId);
            $labelIds[] = $labelId;
        }

        $labelIds = taskUniquePositiveIntIds($labelIds);
        if (empty($labelIds)) {
            return $map;
        }

        $labelMap = array();
        $labelSql = "SELECT id,name,color FROM " . TASK_LABEL . " WHERE status='A' AND id IN (" . implode(',', $labelIds) . ")";
        $labelRst = mysqli_query($connect, $labelSql);
        if ($labelRst) {
            while ($row = $labelRst->fetch_assoc()) {
                $labelId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($labelId <= 0) {
                    continue;
                }
                $labelMap[$labelId] = array(
                    'name' => isset($row['name']) ? (string) $row['name'] : '',
                    'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#DCE8FF'),
                );
            }
        }

        foreach ($pairs as $pair) {
            $itemId = $pair['item_id'];
            $labelId = $pair['label_id'];
            if (!isset($labelMap[$labelId])) {
                continue;
            }
            if (!isset($map[$itemId])) {
                $map[$itemId] = array();
            }
            $map[$itemId][] = array(
                'id' => $labelId,
                'name' => (string) $labelMap[$labelId]['name'],
                'color' => (string) $labelMap[$labelId]['color'],
            );
        }

        foreach ($map as $itemId => $labels) {
            usort($labels, function ($a, $b) {
                return strcmp((string) $a['name'], (string) $b['name']);
            });
            $map[$itemId] = $labels;
        }

        return $map;
    }
}

if (!function_exists('taskSaveItemWorklog')) {
    function taskGetItemWorklogs($connect, $itemId, $limit = 200)
    {
        $itemId = (int) $itemId;
        $limit = (int) $limit;
        if ($itemId <= 0 || !defined('TASK_ITEM_WORKLOG')) {
            return array();
        }

        if ($limit <= 0) {
            $limit = 200;
        }

        $rows = array();
        $worklogRows = array();
        $actorIds = array();
        $sql = "SELECT id,item_id,duration_seconds,started_date,started_time,work_description_html,work_description_text,remaining_seconds_snapshot,create_by,create_date,create_time,update_date,update_time
            FROM " . TASK_ITEM_WORKLOG . "
            WHERE item_id='" . $itemId . "' AND status='A'
            ORDER BY started_date DESC, started_time DESC, id DESC
            LIMIT " . $limit;

        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $worklogRows[] = $row;
            $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
        }

        $actorMap = taskFetchUserDisplayMap($connect, $actorIds, false);

        foreach ($worklogRows as $row) {
            $createDate = isset($row['create_date']) ? (string) $row['create_date'] : '';
            $createTime = isset($row['create_time']) ? (string) $row['create_time'] : '';
            $updateDate = isset($row['update_date']) ? (string) $row['update_date'] : '';
            $updateTime = isset($row['update_time']) ? (string) $row['update_time'] : '';
            $hasUpdateDate = ($updateDate !== '' && $updateDate !== '0000-00-00');
            $hasUpdateTime = ($updateTime !== '' && $updateTime !== '00:00:00');
            $isEdited = ($hasUpdateDate || $hasUpdateTime) && ($updateDate !== $createDate || $updateTime !== $createTime);
            $createById = isset($row['create_by']) ? (int) $row['create_by'] : 0;
            $durationSeconds = isset($row['duration_seconds']) ? max(0, (int) $row['duration_seconds']) : 0;

            $rows[] = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'item_id' => isset($row['item_id']) ? (int) $row['item_id'] : 0,
                'duration_seconds' => $durationSeconds,
                'duration_text' => taskFormatWorklogDuration($durationSeconds),
                'started_date' => isset($row['started_date']) ? (string) $row['started_date'] : '',
                'started_time' => isset($row['started_time']) ? (string) $row['started_time'] : '',
                'work_description_html' => isset($row['work_description_html']) ? (string) $row['work_description_html'] : '',
                'work_description_text' => isset($row['work_description_text']) ? (string) $row['work_description_text'] : '',
                'remaining_seconds_snapshot' => isset($row['remaining_seconds_snapshot']) && $row['remaining_seconds_snapshot'] !== null ? (int) $row['remaining_seconds_snapshot'] : null,
                'actor_name' => isset($actorMap[$createById]) ? (string) $actorMap[$createById] : 'User',
                'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
                'create_date' => $createDate,
                'create_time' => $createTime,
                'is_edited' => $isEdited ? 1 : 0,
            );
        }

        return $rows;
    }

    function taskRefreshItemWorklogSummary($connect, $itemId, $remainingSeconds, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid work item.');
        }

        $sumResult = mysqli_query(
            $connect,
            "SELECT COALESCE(SUM(duration_seconds), 0) AS total_seconds
             FROM " . TASK_ITEM_WORKLOG . "
             WHERE item_id='" . $itemId . "' AND status='A'"
        );
        if (!$sumResult) {
            return array('ok' => 0, 'message' => 'Failed to refresh worklog summary.');
        }

        $sumRow = $sumResult->fetch_assoc();
        $totalSeconds = isset($sumRow['total_seconds']) ? max(0, (int) $sumRow['total_seconds']) : 0;
        $timeTrackingText = $totalSeconds > 0 ? taskFormatWorklogDuration($totalSeconds) : 'No time logged';
        $remainingSql = $remainingSeconds === null
            ? 'NULL'
            : "'" . taskEsc($connect, (string) max(0, (int) $remainingSeconds)) . "'";
        $safeTrackingText = taskEsc($connect, $timeTrackingText);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                time_tracking='" . $safeTrackingText . "',
                remaining_estimate_seconds=" . $remainingSql . ",
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            return array('ok' => 0, 'message' => 'Failed to update work item worklog summary.');
        }

        return array(
            'ok' => 1,
            'time_tracking' => $timeTrackingText,
            'time_tracking_seconds' => $totalSeconds,
        );
    }

    function taskSaveItemWorklog($connect, $itemId, $seconds, $currentUserId, $cdate, $ctime, $options = array())
    {
        $itemId = (int) $itemId;
        $seconds = (int) $seconds;
        $options = is_array($options) ? $options : array();
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid worklog save request.');
        }

        if ($seconds <= 0) {
            return array('ok' => 0, 'message' => 'Worklog time must be greater than 0.');
        }

        if (!defined('TASK_ITEM_WORKLOG')) {
            return array('ok' => 0, 'message' => 'Worklog table is not configured. Please run insert_table.php.');
        }

        mysqli_begin_transaction($connect);

        $itemRst = mysqli_query(
            $connect,
            "SELECT id,original_estimate,remaining_estimate_seconds,time_tracking
             FROM " . TASK_ITEM . "
             WHERE id='" . $itemId . "' AND status='A'
             LIMIT 1 FOR UPDATE"
        );
        if (!$itemRst || $itemRst->num_rows === 0) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $oldTimeTracking = isset($itemRow['time_tracking']) && $itemRow['time_tracking'] !== null
            ? trim((string) $itemRow['time_tracking'])
            : '';
        $oldLoggedSeconds = taskParseWorklogDurationSeconds($oldTimeTracking);
        $oldEstimate = taskParseOriginalEstimate(isset($itemRow['original_estimate']) ? $itemRow['original_estimate'] : '');
        $oldEstimateSeconds = taskEstimateToSeconds(
            isset($oldEstimate['value']) ? (int) $oldEstimate['value'] : 0,
            isset($oldEstimate['unit']) ? (string) $oldEstimate['unit'] : 'minutes'
        );
        $currentRemainingSeconds = taskResolveOwnRemainingEstimateSeconds(
            $oldEstimateSeconds,
            $oldLoggedSeconds,
            isset($itemRow['remaining_estimate_seconds']) ? $itemRow['remaining_estimate_seconds'] : null
        );
        $hasRemainingOverride = array_key_exists('remaining_seconds', $options) && $options['remaining_seconds'] !== null && $options['remaining_seconds'] !== '';
        $newRemainingSeconds = $hasRemainingOverride
            ? max(0, (int) $options['remaining_seconds'])
            : max(0, ($oldLoggedSeconds + $currentRemainingSeconds) - ($oldLoggedSeconds + $seconds));
        $startedDate = taskNormalizeWorklogDate(isset($options['started_date']) ? $options['started_date'] : '', $cdate);
        $startedTime = taskNormalizeWorklogTime(isset($options['started_time']) ? $options['started_time'] : '', $ctime);
        $safeDescriptionHtml = taskSanitizeCommentHtml(isset($options['work_description_html']) ? $options['work_description_html'] : '');
        $plainDescription = taskBuildCommentPlainText($safeDescriptionHtml);

        $safeDuration = (int) $seconds;
        $safeStartedDate = taskEsc($connect, $startedDate);
        $safeStartedTime = taskEsc($connect, $startedTime);
        $safeDescriptionHtmlSql = taskEsc($connect, mb_strcut($safeDescriptionHtml, 0, 16777215, 'UTF-8'));
        $safeDescriptionTextSql = taskEsc($connect, mb_strcut($plainDescription, 0, 65535, 'UTF-8'));
        $safeRemainingSnapshot = taskEsc($connect, (string) $newRemainingSeconds);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okInsert = mysqli_query(
            $connect,
            "INSERT INTO " . TASK_ITEM_WORKLOG . "
             (item_id,duration_seconds,started_date,started_time,work_description_html,work_description_text,remaining_seconds_snapshot,create_by,create_date,create_time,status)
             VALUES
             ('" . $itemId . "','" . $safeDuration . "','" . $safeStartedDate . "','" . $safeStartedTime . "','" . $safeDescriptionHtmlSql . "','" . $safeDescriptionTextSql . "','" . $safeRemainingSnapshot . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')"
        );

        if (!$okInsert) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to save worklog.');
        }

        $summaryResult = taskRefreshItemWorklogSummary($connect, $itemId, $newRemainingSeconds, $currentUserId, $cdate, $ctime);
        if (empty($summaryResult['ok'])) {
            mysqli_rollback($connect);
            return $summaryResult;
        }

        mysqli_commit($connect);

        $addedValue = taskFormatWorklogDuration($seconds);
        taskLogItemHistory(
            $connect,
            $itemId,
            'worklog_saved',
            'Time Tracking',
            $oldTimeTracking,
            isset($summaryResult['time_tracking']) ? $summaryResult['time_tracking'] : '',
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
            'time_tracking' => isset($detail['time_tracking']) ? (string) $detail['time_tracking'] : (isset($summaryResult['time_tracking']) ? (string) $summaryResult['time_tracking'] : 'No time logged'),
            'detail' => $detail,
            'history' => taskGetItemHistory($connect, $itemId, 150),
            'worklogs' => taskGetItemWorklogs($connect, $itemId, 200),
        );
    }

    function taskUpdateItemWorklog($connect, $itemId, $worklogId, $seconds, $currentUserId, $cdate, $ctime, $options = array())
    {
        $itemId = (int) $itemId;
        $worklogId = (int) $worklogId;
        $seconds = (int) $seconds;
        $options = is_array($options) ? $options : array();
        if ($itemId <= 0 || $worklogId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid worklog edit request.');
        }

        if ($seconds <= 0) {
            return array('ok' => 0, 'message' => 'Worklog time must be greater than 0.');
        }

        if (!defined('TASK_ITEM_WORKLOG')) {
            return array('ok' => 0, 'message' => 'Worklog table is not configured. Please run insert_table.php.');
        }

        mysqli_begin_transaction($connect);

        $itemRst = mysqli_query(
            $connect,
            "SELECT id,original_estimate,remaining_estimate_seconds,time_tracking
             FROM " . TASK_ITEM . "
             WHERE id='" . $itemId . "' AND status='A'
             LIMIT 1 FOR UPDATE"
        );
        if (!$itemRst || $itemRst->num_rows === 0) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $worklogRst = mysqli_query(
            $connect,
            "SELECT id,duration_seconds,started_date,started_time,work_description_html,remaining_seconds_snapshot
             FROM " . TASK_ITEM_WORKLOG . "
             WHERE id='" . $worklogId . "' AND item_id='" . $itemId . "' AND status='A'
             LIMIT 1 FOR UPDATE"
        );
        if (!$worklogRst || $worklogRst->num_rows === 0) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Worklog not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $worklogRow = $worklogRst->fetch_assoc();
        $oldTimeTracking = isset($itemRow['time_tracking']) && $itemRow['time_tracking'] !== null
            ? trim((string) $itemRow['time_tracking'])
            : '';
        $oldLoggedSeconds = taskParseWorklogDurationSeconds($oldTimeTracking);
        $oldDurationSeconds = isset($worklogRow['duration_seconds']) ? max(0, (int) $worklogRow['duration_seconds']) : 0;
        $oldEstimate = taskParseOriginalEstimate(isset($itemRow['original_estimate']) ? $itemRow['original_estimate'] : '');
        $oldEstimateSeconds = taskEstimateToSeconds(
            isset($oldEstimate['value']) ? (int) $oldEstimate['value'] : 0,
            isset($oldEstimate['unit']) ? (string) $oldEstimate['unit'] : 'minutes'
        );
        $currentRemainingSeconds = taskResolveOwnRemainingEstimateSeconds(
            $oldEstimateSeconds,
            $oldLoggedSeconds,
            isset($itemRow['remaining_estimate_seconds']) ? $itemRow['remaining_estimate_seconds'] : null
        );
        $newLoggedSeconds = max(0, $oldLoggedSeconds - $oldDurationSeconds + $seconds);
        $hasRemainingOverride = array_key_exists('remaining_seconds', $options) && $options['remaining_seconds'] !== null && $options['remaining_seconds'] !== '';
        $newRemainingSeconds = $hasRemainingOverride
            ? max(0, (int) $options['remaining_seconds'])
            : max(0, ($oldLoggedSeconds + $currentRemainingSeconds) - $newLoggedSeconds);
        $startedDate = taskNormalizeWorklogDate(isset($options['started_date']) ? $options['started_date'] : (isset($worklogRow['started_date']) ? $worklogRow['started_date'] : ''), $cdate);
        $startedTime = taskNormalizeWorklogTime(isset($options['started_time']) ? $options['started_time'] : (isset($worklogRow['started_time']) ? $worklogRow['started_time'] : ''), $ctime);
        $safeDescriptionHtml = taskSanitizeCommentHtml(isset($options['work_description_html']) ? $options['work_description_html'] : (isset($worklogRow['work_description_html']) ? $worklogRow['work_description_html'] : ''));
        $plainDescription = taskBuildCommentPlainText($safeDescriptionHtml);
        $safeStartedDate = taskEsc($connect, $startedDate);
        $safeStartedTime = taskEsc($connect, $startedTime);
        $safeDescriptionHtmlSql = taskEsc($connect, mb_strcut($safeDescriptionHtml, 0, 16777215, 'UTF-8'));
        $safeDescriptionTextSql = taskEsc($connect, mb_strcut($plainDescription, 0, 65535, 'UTF-8'));
        $safeRemainingSnapshot = taskEsc($connect, (string) $newRemainingSeconds);
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_WORKLOG . " SET
                duration_seconds='" . $seconds . "',
                started_date='" . $safeStartedDate . "',
                started_time='" . $safeStartedTime . "',
                work_description_html='" . $safeDescriptionHtmlSql . "',
                work_description_text='" . $safeDescriptionTextSql . "',
                remaining_seconds_snapshot='" . $safeRemainingSnapshot . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $worklogId . "' AND item_id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to update worklog.');
        }

        $summaryResult = taskRefreshItemWorklogSummary($connect, $itemId, $newRemainingSeconds, $currentUserId, $cdate, $ctime);
        if (empty($summaryResult['ok'])) {
            mysqli_rollback($connect);
            return $summaryResult;
        }

        mysqli_commit($connect);

        taskLogItemHistory(
            $connect,
            $itemId,
            'update_worklog',
            'Time Tracking',
            $oldTimeTracking,
            isset($summaryResult['time_tracking']) ? $summaryResult['time_tracking'] : '',
            'updated worklog from ' . taskFormatWorklogDuration($oldDurationSeconds) . ' to ' . taskFormatWorklogDuration($seconds),
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
            'message' => 'Work log updated.',
            'detail' => $detail,
            'history' => taskGetItemHistory($connect, $itemId, 150),
            'worklogs' => taskGetItemWorklogs($connect, $itemId, 200),
        );
    }

    function taskDeleteItemWorklog($connect, $itemId, $worklogId, $adjustRemaining, $newRemainingSeconds, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        $worklogId = (int) $worklogId;
        if ($itemId <= 0 || $worklogId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid worklog delete request.');
        }

        if (!defined('TASK_ITEM_WORKLOG')) {
            return array('ok' => 0, 'message' => 'Worklog table is not configured. Please run insert_table.php.');
        }

        mysqli_begin_transaction($connect);

        $itemRst = mysqli_query(
            $connect,
            "SELECT id,original_estimate,remaining_estimate_seconds,time_tracking
             FROM " . TASK_ITEM . "
             WHERE id='" . $itemId . "' AND status='A'
             LIMIT 1 FOR UPDATE"
        );
        if (!$itemRst || $itemRst->num_rows === 0) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $worklogRst = mysqli_query(
            $connect,
            "SELECT id,duration_seconds,work_description_text
             FROM " . TASK_ITEM_WORKLOG . "
             WHERE id='" . $worklogId . "' AND item_id='" . $itemId . "' AND status='A'
             LIMIT 1 FOR UPDATE"
        );
        if (!$worklogRst || $worklogRst->num_rows === 0) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Worklog not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $worklogRow = $worklogRst->fetch_assoc();
        $oldTimeTracking = isset($itemRow['time_tracking']) && $itemRow['time_tracking'] !== null
            ? trim((string) $itemRow['time_tracking'])
            : '';
        $oldLoggedSeconds = taskParseWorklogDurationSeconds($oldTimeTracking);
        $deletedDurationSeconds = isset($worklogRow['duration_seconds']) ? max(0, (int) $worklogRow['duration_seconds']) : 0;
        $oldEstimate = taskParseOriginalEstimate(isset($itemRow['original_estimate']) ? $itemRow['original_estimate'] : '');
        $oldEstimateSeconds = taskEstimateToSeconds(
            isset($oldEstimate['value']) ? (int) $oldEstimate['value'] : 0,
            isset($oldEstimate['unit']) ? (string) $oldEstimate['unit'] : 'minutes'
        );
        $currentRemainingSeconds = taskResolveOwnRemainingEstimateSeconds(
            $oldEstimateSeconds,
            $oldLoggedSeconds,
            isset($itemRow['remaining_estimate_seconds']) ? $itemRow['remaining_estimate_seconds'] : null
        );
        $targetRemainingSeconds = $adjustRemaining
            ? max(0, (int) $newRemainingSeconds)
            : $currentRemainingSeconds;
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $okDelete = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM_WORKLOG . " SET
                status='D',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $worklogId . "' AND item_id='" . $itemId . "' AND status='A'"
        );

        if (!$okDelete) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to delete worklog.');
        }

        $summaryResult = taskRefreshItemWorklogSummary($connect, $itemId, $targetRemainingSeconds, $currentUserId, $cdate, $ctime);
        if (empty($summaryResult['ok'])) {
            mysqli_rollback($connect);
            return $summaryResult;
        }

        mysqli_commit($connect);

        taskLogItemHistory(
            $connect,
            $itemId,
            'delete_worklog',
            'Time Tracking',
            $oldTimeTracking,
            isset($summaryResult['time_tracking']) ? $summaryResult['time_tracking'] : '',
            'deleted worklog ' . taskFormatWorklogDuration($deletedDurationSeconds),
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
            'message' => 'Work log deleted.',
            'detail' => $detail,
            'history' => taskGetItemHistory($connect, $itemId, 150),
            'worklogs' => taskGetItemWorklogs($connect, $itemId, 200),
        );
    }
}

if (!function_exists('taskGetColumns')) {
    function taskGetColumns($connect, $projectId = 0)
    {
        $rows = array();
        $projectId = (int) $projectId;
        $sql = "SELECT id,name,color,sort_order FROM " . TASK_COLUMN . " WHERE status='A'";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        $result = mysqli_query($connect, $sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = array(
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'color' => taskNormalizeHexColor(isset($row['color']) ? $row['color'] : '', '#dfe1e6'),
                    'sort_order' => (int) $row['sort_order'],
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('taskGetItemsGroupedByColumn')) {
    function taskGetItemsGroupedByColumn($connect, $projectId = 0)
    {
        $grouped = array();
        $allItemIds = array();
        $projectId = (int) $projectId;
        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $defaultProjectKeyId = isset($projectKeySetting['id']) ? (int) $projectKeySetting['id'] : 0;
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

    $sql = "SELECT id,column_id,title,description,description_color_html,work_type_id,assignee_user_id,reporter_user_id,
                project_id,
                priority,start_date,due_date,task_status,create_date,update_date,
                original_estimate,amendement_date,amendement_time,second_amendement_date,second_amendement_time,
                sort_order,project_key_id
                FROM " . TASK_ITEM . "
                WHERE status='A'";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $sql .= "
                ORDER BY column_id ASC, sort_order ASC, id ASC";

        $result = mysqli_query($connect, $sql);
        if ($result) {
            $rows = array();
            $workTypeIds = array();
            $projectKeyIds = array();
            $assigneeIds = array();
            $reporterIds = array();

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                $workTypeIds[] = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
                $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
                $assigneeIds[] = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
                $reporterIds[] = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
            }

            $workTypeMap = taskFetchWorkTypeInfoMap($connect, $workTypeIds, true);
            $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, true);
            $userMap = taskFetchUserDisplayMap($connect, array_merge($assigneeIds, $reporterIds), true);

            foreach ($rows as $row) {
                $columnId = (int) $row['column_id'];
                if (!isset($grouped[$columnId])) {
                    $grouped[$columnId] = array();
                }

                $resolvedProjectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
                $resolvedProjectKey = isset($projectKeyMap[$resolvedProjectKeyId]) ? (string) $projectKeyMap[$resolvedProjectKeyId] : '';
                if ($resolvedProjectKey === '') {
                    $resolvedProjectKey = taskNormalizeProjectKey($defaultProjectKey);
                }

                if ($resolvedProjectKeyId <= 0) {
                    $resolvedProjectKeyId = $defaultProjectKeyId;
                }

                $estimate = taskParseOriginalEstimate(isset($row['original_estimate']) ? $row['original_estimate'] : '');
                $workTypeId = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
                $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
                $reporterUserId = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
                $workTypeInfo = isset($workTypeMap[$workTypeId]) ? $workTypeMap[$workTypeId] : array(
                    'name' => 'Task',
                    'svg_icon' => taskDefaultWorkTypeSvgIcon('Task'),
                );

                $grouped[$columnId][] = array(
                    'id' => (int) $row['id'],
                    'column_id' => $columnId,
                    'title' => (string) $row['title'],
                    'description' => isset($row['description_color_html']) && trim((string) $row['description_color_html']) !== ''
                        ? (string) $row['description_color_html']
                        : (isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : ''),
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
                    'project_key_id' => $resolvedProjectKeyId,
                    'project_key' => $resolvedProjectKey,
                    'work_item_key' => taskBuildWorkItemKey($resolvedProjectKey, (int) $row['id']),
                    'work_type_id' => $workTypeId,
                    'work_type_name' => isset($workTypeInfo['name']) ? (string) $workTypeInfo['name'] : 'Task',
                    'work_type_svg_icon' => isset($workTypeInfo['svg_icon']) ? (string) $workTypeInfo['svg_icon'] : taskDefaultWorkTypeSvgIcon('Task'),
                    'assignee_user_id' => $assigneeUserId,
                    'reporter_user_id' => $reporterUserId,
                    'reporter_name' => isset($userMap[$reporterUserId]) ? (string) $userMap[$reporterUserId] : '',
                    'priority' => taskNormalizePriority(isset($row['priority']) ? $row['priority'] : 'Medium'),
                    'original_estimate_value' => isset($estimate['value']) ? (int) $estimate['value'] : 0,
                    'original_estimate_unit' => isset($estimate['unit']) ? (string) $estimate['unit'] : 'minutes',
                    'start_date' => isset($row['start_date']) && $row['start_date'] !== null ? (string) $row['start_date'] : '',
                    'assignee_name' => isset($userMap[$assigneeUserId]) ? (string) $userMap[$assigneeUserId] : '',
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

if (!function_exists('taskBuildProjectPageUrl')) {
    function taskBuildProjectPageUrl($siteUrl, $path, $projectId)
    {
        $base = rtrim((string) $siteUrl, '/');
        $projectId = (int) $projectId;
        $url = $base . $path;
        if ($projectId > 0) {
            $url .= '?project_id=' . $projectId;
        }
        return $url;
    }
}

if (!function_exists('taskRenderProjectBrowserMenu')) {
    function taskRenderProjectBrowserMenu($connect, $siteUrl, $activeMenu, $currentProjectId = 0, $options = array())
    {
        $currentProjectId = (int) $currentProjectId;
        $showCreateButton = !empty($options['show_create_button']);
        $createButtonId = isset($options['create_button_id']) ? (string) $options['create_button_id'] : '';
        $sectionClass = trim('task-global-project-section ' . (isset($options['section_class']) ? (string) $options['section_class'] : ''));
        $panelIdPrefix = isset($options['panel_id_prefix']) ? (string) $options['panel_id_prefix'] : 'taskProjectPanel';
        $actionIdPrefix = isset($options['action_panel_id_prefix']) ? (string) $options['action_panel_id_prefix'] : 'taskProjectActions';

        $menus = array(
            'summary' => array('label' => 'Summary', 'path' => '/task/summary.php', 'pin_id' => 139),
            'board' => array('label' => 'Board', 'path' => '/task/board.php', 'pin_id' => 139),
            'sheets' => array('label' => 'Sheets', 'path' => '/task/sheets.php', 'pin_id' => 139),
        );

        $hasTaskNavView = taskIsActionAllowed('view', taskGetProjectCreatorPinAccess($connect));
        if (!$hasTaskNavView) {
            return;
        }

        $projectList = taskGetProjectList($connect);
        $canCreateProject = taskCanCreateProject($connect);

        echo '<div class="' . htmlspecialchars($sectionClass, ENT_QUOTES, 'UTF-8') . '">';
        echo '  <div class="task-global-project-header">';
        echo '      <span>Project Task</span>';

        if ($showCreateButton && $canCreateProject && $createButtonId !== '') {
            echo '  <button type="button" id="' . htmlspecialchars($createButtonId, ENT_QUOTES, 'UTF-8') . '" class="btn task-global-create-project-btn" title="Create project task"><i class="fa-solid fa-plus"></i></button>';
        }

        echo '  </div>';

        if (empty($projectList)) {
            echo '<div class="task-global-project-empty">No project task found yet.</div>';
            echo '</div>';
            return;
        }

        echo '<ul class="task-global-project-list">';

        $currentUserId = USER_ID;
        foreach ($projectList as $project) {
            $projectId = isset($project['id']) ? (int) $project['id'] : 0;
            $projectName = isset($project['name']) ? (string) $project['name'] : '';
            if ($projectId <= 0 || $projectName === '') {
                continue;
            }

            $projectHasSummaryAccess = taskUserCanAccessProjectPageByPin($connect, $projectId, 139);
            $projectHasBoardAccess = taskUserCanAccessProjectPageByPin($connect, $projectId, 139);
            $projectHasSheetsAccess = taskUserCanAccessProjectPageByPin($connect, $projectId, 139);
            
            $canAccessProjectSettings = taskCanAccessProjectSettings($connect, $projectId);
            $canAccessProjectUserAccess = taskCanAccessProjectUserAccess($connect, $projectId);
            $canManageProjectActions = $canAccessProjectSettings || $canAccessProjectUserAccess;

            if (!$projectHasSummaryAccess && !$projectHasBoardAccess && !$projectHasSheetsAccess && !$canManageProjectActions) {
                continue;
            }

            $isActiveProject = $projectId === $currentProjectId;
            $projectItemPanelId = $panelIdPrefix . $projectId;
            $projectItemActionPanelId = $actionIdPrefix . $projectId;

            echo '<li class="task-global-project-item' . ($isActiveProject ? ' active expanded' : '') . '" data-project-id="' . $projectId . '">';
            echo '  <div class="task-global-project-row">';
            echo '      <button type="button" class="task-global-project-toggle" data-task-project-toggle aria-expanded="' . ($isActiveProject ? 'true' : 'false') . '" aria-controls="' . htmlspecialchars($projectItemPanelId, ENT_QUOTES, 'UTF-8') . '">';
            echo '          <span class="task-global-project-toggle-text">' . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '          <span class="task-global-project-toggle-icon" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>';
            echo '      </button>';

            if ($canManageProjectActions) {
                echo '  <div class="task-global-project-actions">';
                echo '      <button type="button" class="task-global-project-settings-link task-global-project-actions-btn" data-task-project-actions-btn aria-expanded="false" aria-controls="' . htmlspecialchars($projectItemActionPanelId, ENT_QUOTES, 'UTF-8') . '" title="Project options">';
                echo '          <i class="fa-solid fa-ellipsis"></i>';
                echo '      </button>';
                echo '      <div class="task-global-project-actions-panel" id="' . htmlspecialchars($projectItemActionPanelId, ENT_QUOTES, 'UTF-8') . '">';
                if ($canAccessProjectUserAccess) {
                    echo '      <a href="' . htmlspecialchars(taskBuildProjectPageUrl($siteUrl, '/task/project_user_access.php', $projectId), ENT_QUOTES, 'UTF-8') . '">Project User Access</a>';
                }
                if ($canAccessProjectSettings) {
                    echo '      <a href="' . htmlspecialchars(taskBuildProjectPageUrl($siteUrl, '/task/project_settings.php', $projectId), ENT_QUOTES, 'UTF-8') . '">Project Settings</a>';
                }
                echo '      </div>';
                echo '  </div>';
            }

            echo '  </div>';
            echo '  <ul class="task-global-project-submenu' . ($isActiveProject ? ' active' : '') . '" id="' . htmlspecialchars($projectItemPanelId, ENT_QUOTES, 'UTF-8') . '">';

            foreach ($menus as $menuKey => $menu) {
                $pinId = isset($menu['pin_id']) ? (int) $menu['pin_id'] : 0;
                if ($pinId > 0 && !taskUserCanAccessProjectPageByPin($connect, $projectId, $pinId)) {
                    continue;
                }

                $isActive = $isActiveProject && $activeMenu === $menuKey;
                echo '      <li><a class="' . ($isActive ? 'task-global-link-active' : '') . '" href="' . htmlspecialchars(taskBuildProjectPageUrl($siteUrl, $menu['path'], $projectId), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
            }

            echo '  </ul>';
            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}

if (!function_exists('taskRenderSidebarMenu')) {
    function taskRenderSidebarMenu($connect, $siteUrl, $activeMenu, $currentProjectId = 0)
    {
        taskRenderProjectBrowserMenu(
            $connect,
            $siteUrl,
            $activeMenu,
            $currentProjectId,
            array(
                'show_create_button' => true,
                'create_button_id' => 'taskCreateProjectBtn',
                'section_class' => 'task-local-project-section',
                'panel_id_prefix' => 'taskLocalProjectPanel',
                'action_panel_id_prefix' => 'taskLocalProjectActions',
            )
        );
    }
}

if (!function_exists('taskRenderMobileMenuDropdown')) {
    function taskRenderMobileMenuDropdown($siteUrl, $activeMenu, $currentProjectId = 0)
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
            $href = htmlspecialchars(taskBuildProjectPageUrl($siteUrl, $menu['path'], $currentProjectId), ENT_QUOTES, 'UTF-8');
            $isActive = $activeMenu === $menuKey;
            echo '<li><a class="dropdown-item' . ($isActive ? ' active' : '') . '" href="' . $href . '">' . htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
        }

        echo '</ul>';
        echo '</div>';
    }
}

if (!function_exists('taskRenderCreateProjectModal')) {
    function taskRenderCreateProjectModal()
    {
        echo '<div class="modal fade" id="taskCreateProjectModal" tabindex="-1" aria-hidden="true">';
        echo '  <div class="modal-dialog modal-dialog-centered task-create-project-modal-dialog">';
        echo '      <div class="modal-content task-create-project-modal-content">';
        echo '          <div class="modal-body task-create-project-modal-body">';
        echo '              <input type="text" id="taskCreateProjectName" class="form-control task-create-project-input" maxlength="180" placeholder="Project task name">';
        echo '              <button type="button" id="taskCreateProjectSubmitBtn" class="btn task-create-project-confirm-btn" title="Create project task"><i class="fa-solid fa-check"></i></button>';
        echo '              <button type="button" class="btn task-create-project-cancel-btn" data-bs-dismiss="modal" title="Cancel"><i class="fa-solid fa-xmark"></i></button>';
        echo '      </div>';
        echo '  </div>';
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
    function taskRenderCard($taskItem, $assignees = array(), $canEdit = true, $canDelete = true)
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
        $priorityIconHtml = '<span class="task-priority-medium-icon" aria-hidden="true"></span>';
        if ($priority === 'Highest') {
            $priorityIconHtml = '<i class="fa-solid fa-angles-up task-priority-highest" aria-hidden="true"></i>';
        } elseif ($priority === 'High') {
            $priorityIconHtml = '<i class="fa-solid fa-angle-up task-priority-high" aria-hidden="true"></i>';
        } elseif ($priority === 'Low') {
            $priorityIconHtml = '<i class="fa-solid fa-angle-down task-priority-low" aria-hidden="true"></i>';
        } elseif ($priority === 'Lowest') {
            $priorityIconHtml = '<i class="fa-solid fa-angles-down task-priority-lowest" aria-hidden="true"></i>';
        }

        echo '<article class="task-item-card" data-item-id="' . (int) $taskItem['id'] . '" data-label-ids="' . htmlspecialchars(implode(',', $labelIds), ENT_QUOTES, 'UTF-8') . '" data-assignee-user-id="' . $assigneeUserId . '" data-assignee-name="' . htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8') . '" data-reporter-user-id="' . $reporterUserId . '" data-reporter-name="' . htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8') . '" data-priority="' . htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') . '" data-start-date="' . htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') . '" data-due-date="' . htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8') . '" data-create-date="' . htmlspecialchars($createDate, ENT_QUOTES, 'UTF-8') . '" data-update-date="' . htmlspecialchars($updateDate, ENT_QUOTES, 'UTF-8') . '" data-original-estimate-value="' . $estimateValue . '" data-original-estimate-unit="' . htmlspecialchars($estimateUnit, ENT_QUOTES, 'UTF-8') . '" data-amendement-date="' . htmlspecialchars($amendementDate, ENT_QUOTES, 'UTF-8') . '" data-amendement-time-minutes="' . $amendementTimeMinutes . '" data-second-amendement-date="' . htmlspecialchars($secondAmendementDate, ENT_QUOTES, 'UTF-8') . '" data-second-amendement-time-minutes="' . $secondAmendementTimeMinutes . '" data-work-type-id="' . (int) (isset($taskItem['work_type_id']) ? $taskItem['work_type_id'] : 0) . '" data-work-type-icon="' . htmlspecialchars($workTypeIcon, ENT_QUOTES, 'UTF-8') . '" data-item-description="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '" data-work-type-name="' . htmlspecialchars($workTypeName, ENT_QUOTES, 'UTF-8') . '" data-work-item-key="' . htmlspecialchars($workItemKey, ENT_QUOTES, 'UTF-8') . '" data-parent-item-id="' . $parentItemId . '" data-parent-display="' . htmlspecialchars($parentDisplay, ENT_QUOTES, 'UTF-8') . '" data-task-status-label-ids="' . htmlspecialchars(implode(',', $statusLabelIds), ENT_QUOTES, 'UTF-8') . '" draggable="true">';
        echo '<div class="task-item-head">';
        echo '<h6 class="task-item-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h6>';
        echo '<div class="task-item-menu-dropdown" style="display: flex; gap: 2px;">';
        if ($canEdit) {
            echo '<button class="btn task-item-menu-btn task-item-edit-btn" type="button" title="Edit title" aria-label="Edit title"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>';
        }
        if ($canEdit || $canDelete) {
            echo '<button class="btn task-item-menu-btn task-open-item-actions-btn" type="button" title="Task options" aria-label="Task options"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></button>';
        }
        echo '</div>';
        echo '</div>';

        if (!empty($labels)) {
            echo '<div class="task-item-label-row">';
            foreach ($labels as $label) {
                $labelName = isset($label['name']) ? (string) $label['name'] : '';
                $labelColor = taskNormalizeHexColor(isset($label['color']) ? $label['color'] : '', '#DCE8FF');
                if ($labelName === '') {
                    continue;
                }
                echo '<span class="task-label-pill" style="--task-label-bg:' . htmlspecialchars($labelColor, ENT_QUOTES, 'UTF-8') . ';--task-label-text:' . htmlspecialchars(taskTextColorForBackground($labelColor), ENT_QUOTES, 'UTF-8') . ';">' . htmlspecialchars($labelName, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '</div>';
        }

        echo '<div class="task-item-field-list"></div>';
        echo '<div class="task-item-meta">';
        echo '<div class="task-item-meta-left">';
        echo '<span class="task-type-icon" title="' . htmlspecialchars($workTypeName, ENT_QUOTES, 'UTF-8') . '"><img class="task-type-pill-icon" src="' . htmlspecialchars($workTypeIcon, ENT_QUOTES, 'UTF-8') . '" alt=""></span>';
        echo '<span class="task-item-key' . ($workItemKey === '' ? ' d-none' : '') . '">' . htmlspecialchars($workItemKey, ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</div>';
        echo '<div class="task-item-meta-right">';
        echo '<span class="task-item-priority-wrap">' . $priorityIconHtml . '</span>';
        if ($canEdit) {
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
        } else {
            echo '<span class="btn task-assignee-pill task-item-assignee-btn' . ($assigneeUserId <= 0 ? ' task-assignee-pill-unassigned' : '') . '" data-user-id="' . $assigneeUserId . '" title="' . htmlspecialchars($assigneeDisplay, ENT_QUOTES, 'UTF-8') . '">';
            if ($assigneeUserId > 0 && $assigneeInitial !== '') {
                echo htmlspecialchars($assigneeInitial, ENT_QUOTES, 'UTF-8');
            } else {
                echo '<i class="fa-regular fa-user"></i>';
            }
            echo '</span>';
        }
        echo '</div>';
        echo '</div>';
        echo '</article>';
    }
}

if (!function_exists('taskRenderComposer')) {
    function taskRenderComposer($columnId, $workTypes, $assignees, $canAdd = true)
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
        echo '          <button class="btn task-create-item-btn" type="button" disabled title="Create work item"' . ($canAdd ? '' : ' style="display:none;"') . '><span class="mdi mdi-keyboard-return"></span></button>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';
    }
}

if (!function_exists('taskRenderBoardColumn')) {
    function taskRenderBoardColumn($column, $items, $workTypes, $assignees, $canAdd = true, $canEdit = true, $canDelete = true, $canManageColumn = false)
    {
        $columnId = (int) $column['id'];
        $columnName = isset($column['name']) ? (string) $column['name'] : '';
        $columnColor = taskNormalizeHexColor(isset($column['color']) ? $column['color'] : '', '#dfe1e6');
        $itemCount = is_array($items) ? count($items) : 0;

        echo '<section class="task-column" data-column-id="' . $columnId . '" data-column-color="' . htmlspecialchars($columnColor, ENT_QUOTES, 'UTF-8') . '" style="--task-column-color:' . htmlspecialchars($columnColor, ENT_QUOTES, 'UTF-8') . ';">';
        echo '  <div class="task-column-header">';
        echo '      <div class="task-column-title-wrap">';
        echo '          <h5 class="task-column-title">' . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . '</h5>';
        echo '          <span class="task-column-count">' . $itemCount . '</span>';
        echo '      </div>';
        echo '      <div class="task-column-header-actions">';
        echo '          <button class="btn task-column-collapse-btn" type="button" title="Collapse status"><i class="fa-solid fa-left-right"></i></button>';
        if ($canManageColumn) {
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
        }
        echo '      </div>';
        echo '  </div>';

        echo '  <div class="task-item-list">';
        foreach ($items as $taskItem) {
            taskRenderCard($taskItem, $assignees, $canEdit, $canDelete);
        }
        echo '  </div>';

        if ($canAdd && !empty($workTypes)) {
            echo '  <button class="btn task-open-composer-btn" type="button"><span class="task-open-composer-btn-icon">+</span><span class="task-open-composer-btn-text">Create</span></button>';
        }
        taskRenderComposer($columnId, $workTypes, $assignees, $canAdd);
        echo '</section>';
    }
}

if (!function_exists('taskCreateColumn')) {
    function taskCreateColumn($connect, $projectId, $columnName, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $columnName = trim((string) $columnName);
        if ($columnName === '') {
            return array('ok' => 0, 'message' => 'Status name is required.');
        }

        $safeName = taskEsc($connect, substr($columnName, 0, 150));

        $duplicateSql = "SELECT id FROM " . TASK_COLUMN . " WHERE status='A' AND project_id='" . $projectId . "' AND LOWER(name)=LOWER('" . $safeName . "') LIMIT 1";
        $duplicateRst = mysqli_query($connect, $duplicateSql);
        if ($duplicateRst && $duplicateRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'This status name already exists.');
        }

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_COLUMN . " WHERE status='A' AND project_id='" . $projectId . "'");
        $sortOrder = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $sortOrder = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_COLUMN . " (project_id,name,color,sort_order,create_by,create_date,create_time,status)
                      VALUES ('" . $projectId . "','" . $safeName . "','#DFE1E6','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create status.');
        }

        return array(
            'ok' => 1,
            'message' => 'Status created successfully.',
            'column' => array(
                'id' => (int) mysqli_insert_id($connect),
                'name' => $columnName,
                'color' => '#DFE1E6',
                'sort_order' => $sortOrder,
            ),
        );
    }
}

if (!function_exists('taskRenameColumn')) {
    function taskRenameColumn($connect, $projectId, $columnId, $columnName, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $columnId = (int) $columnId;
        $columnName = trim((string) $columnName);

        if ($columnId <= 0 || $columnName === '') {
            return array('ok' => 0, 'message' => 'Invalid status rename request.');
        }

        $safeName = taskEsc($connect, substr($columnName, 0, 150));
        $existsSql = "SELECT id,name FROM " . TASK_COLUMN . " WHERE id='" . $columnId . "' AND project_id='" . $projectId . "' AND status='A' LIMIT 1";
        $existsRst = mysqli_query($connect, $existsSql);
        if (!$existsRst || $existsRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Status not found.');
        }
        $existsRow = $existsRst->fetch_assoc();

        $duplicateSql = "SELECT id FROM " . TASK_COLUMN . " WHERE status='A' AND project_id='" . $projectId . "' AND LOWER(name)=LOWER('" . $safeName . "') AND id <> '" . $columnId . "' LIMIT 1";
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
                      WHERE id='" . $columnId . "' AND project_id='" . $projectId . "' AND status='A'";

        if (!mysqli_query($connect, $updateSql)) {
            return array('ok' => 0, 'message' => 'Failed to rename status.');
        }

        return array('ok' => 1, 'message' => 'Status renamed successfully.', 'column_name' => $columnName, 'old_column_name' => isset($existsRow['name']) ? (string) $existsRow['name'] : '');
    }
}

if (!function_exists('taskMoveColumn')) {
    function taskMoveColumn($connect, $projectId, $columnId, $direction)
    {
        $projectId = (int) $projectId;
        $columnId = (int) $columnId;
        $direction = strtolower(trim((string) $direction));
        if ($columnId <= 0 || !in_array($direction, array('left', 'right'), true)) {
            return array('ok' => 0, 'message' => 'Invalid status move request.');
        }

        $currentSql = "SELECT id, sort_order FROM " . TASK_COLUMN . " WHERE id='" . $columnId . "' AND project_id='" . $projectId . "' AND status='A' LIMIT 1";
        $currentRst = mysqli_query($connect, $currentSql);
        if (!$currentRst || $currentRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Status not found.');
        }

        $current = $currentRst->fetch_assoc();
        $currentSort = (int) $current['sort_order'];

        if ($direction === 'left') {
            $targetSql = "SELECT id, sort_order FROM " . TASK_COLUMN . " WHERE project_id='" . $projectId . "' AND status='A' AND sort_order < '" . $currentSort . "' ORDER BY sort_order DESC, id DESC LIMIT 1";
        } else {
            $targetSql = "SELECT id, sort_order FROM " . TASK_COLUMN . " WHERE project_id='" . $projectId . "' AND status='A' AND sort_order > '" . $currentSort . "' ORDER BY sort_order ASC, id ASC LIMIT 1";
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
    function taskDeleteColumn($connect, $projectId, $columnId, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $columnId = (int) $columnId;
        if ($columnId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid status delete request.');
        }

        $existsSql = "SELECT id,name FROM " . TASK_COLUMN . " WHERE id='" . $columnId . "' AND project_id='" . $projectId . "' AND status='A' LIMIT 1";
        $existsRst = mysqli_query($connect, $existsSql);
        if (!$existsRst || $existsRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Status not found.');
        }
        $existsRow = $existsRst->fetch_assoc();

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);
        $okItems = mysqli_query($connect, "UPDATE " . TASK_ITEM . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE project_id='" . $projectId . "' AND column_id='" . $columnId . "' AND status='A'");
        $okStatus = mysqli_query($connect, "UPDATE " . TASK_COLUMN . " SET status='D', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $columnId . "' AND project_id='" . $projectId . "' AND status='A'");

        if (!$okItems || !$okStatus) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to delete status.');
        }

        mysqli_commit($connect);

        return array('ok' => 1, 'message' => 'Status deleted successfully.', 'column_name' => isset($existsRow['name']) ? (string) $existsRow['name'] : '');
    }
}

if (!function_exists('taskCreateWorkType')) {
    function taskCreateWorkType($connect, $projectId, $name, $remark, $svgIcon, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $name = trim((string) $name);
        if ($name === '') {
            return array('ok' => 0, 'message' => 'Work type name is required.');
        }

        $safeName = taskEsc($connect, substr($name, 0, 80));
        $duplicateSql = "SELECT id FROM " . TASK_WORK_TYPE . " WHERE status='A' AND project_id='" . $projectId . "' AND LOWER(name)=LOWER('" . $safeName . "') LIMIT 1";
        $duplicateRst = mysqli_query($connect, $duplicateSql);
        if ($duplicateRst && $duplicateRst->num_rows > 0) {
            return array('ok' => 0, 'message' => 'This work type already exists.');
        }

        $safeRemark = taskEsc($connect, substr(trim((string) $remark), 0, 255));
        $safeIcon = taskEsc($connect, taskNormalizeWorkTypeSvgIcon($svgIcon, $name));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        $insertSql = "INSERT INTO " . TASK_WORK_TYPE . " (project_id,name,svg_icon,remark,create_by,create_date,create_time,status)
                  VALUES ('" . $projectId . "','" . $safeName . "','" . $safeIcon . "','" . $safeRemark . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create work type.');
        }

        return array('ok' => 1, 'message' => 'Work type created successfully.');
    }
}

if (!function_exists('taskUpdateWorkType')) {
    function taskUpdateWorkType($connect, $projectId, $workTypeId, $name, $remark, $svgIcon, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $workTypeId = (int) $workTypeId;
        $name = trim((string) $name);
        if ($workTypeId <= 0 || $name === '') {
            return array('ok' => 0, 'message' => 'Invalid work type update request.');
        }

        $safeName = taskEsc($connect, substr($name, 0, 80));
        $duplicateSql = "SELECT id FROM " . TASK_WORK_TYPE . " WHERE status='A' AND project_id='" . $projectId . "' AND LOWER(name)=LOWER('" . $safeName . "') AND id <> " . $workTypeId . " LIMIT 1";
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
                      WHERE id='" . $workTypeId . "' AND project_id='" . $projectId . "' AND status='A'";

        if (!mysqli_query($connect, $updateSql)) {
            return array('ok' => 0, 'message' => 'Failed to update work type.');
        }

        return array('ok' => 1, 'message' => 'Work type updated successfully.');
    }
}

if (!function_exists('taskCreateItem')) {
    function taskCreateItem($connect, $projectId, $columnId, $title, $workTypeId, $assigneeUserId, $dueDate, $currentUserId, $cdate, $ctime)
    {
        $projectId = (int) $projectId;
        $columnId = (int) $columnId;
        $workTypeId = (int) $workTypeId;
        $assigneeUserId = (int) $assigneeUserId;
        $title = trim((string) $title);
        $dueDate = trim((string) $dueDate);

        if ($columnId <= 0 || $title === '') {
            return array('ok' => 0, 'message' => 'Task title is required.');
        }

        $columnRst = getData('id', "id='" . $columnId . "' AND project_id='" . $projectId . "'", 'LIMIT 1', TASK_COLUMN, $connect);
        if (!$columnRst || $columnRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Selected column does not exist.');
        }

        if ($workTypeId > 0) {
            $workTypeRst = getData('id', "id='" . $workTypeId . "' AND project_id='" . $projectId . "'", 'LIMIT 1', TASK_WORK_TYPE, $connect);
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

        $sortRst = mysqli_query($connect, "SELECT IFNULL(MAX(sort_order),0)+1 AS next_sort FROM " . TASK_ITEM . " WHERE status='A' AND project_id='" . $projectId . "' AND column_id='" . $columnId . "'");
        $sortOrder = 1;
        if ($sortRst && $sortRst->num_rows > 0) {
            $sortRow = $sortRst->fetch_assoc();
            $sortOrder = isset($sortRow['next_sort']) ? (int) $sortRow['next_sort'] : 1;
        }

        $dueDate = taskNormalizeBoardDate($dueDate);

        $safeTitle = taskEsc($connect, substr($title, 0, 255));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);
        $safeDueDate = 'NULL';
        if ($dueDate !== '') {
            $safeDueDate = "'" . taskEsc($connect, $dueDate) . "'";
        }

        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $projectKeyId = isset($projectKeySetting['id']) ? (int) $projectKeySetting['id'] : 0;
        $projectKeyText = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';
        $reporterUserId = ctype_digit((string) $currentUserId) ? (int) $currentUserId : 0;
        $safePriority = taskEsc($connect, 'Medium');
        $safeOriginalEstimate = taskEsc($connect, '0 minutes');
        $safeStartDate = $safeDueDate;

        $insertSql = "INSERT INTO " . TASK_ITEM . "
                            (project_id,column_id,title,description,project_key_id,work_type_id,assignee_user_id,due_date,start_date,original_estimate,task_status,parent_item_id,reporter_user_id,priority,time_tracking,amendement_date,amendement_time,second_amendement_date,second_amendement_time,sort_order,create_by,create_date,create_time,status)
                      VALUES
                        ('" . $projectId . "','" . $columnId . "','" . $safeTitle . "','','" . $projectKeyId . "','" . $workTypeId . "','" . $assigneeUserId . "'," . $safeDueDate . "," . $safeStartDate . ",'" . $safeOriginalEstimate . "','','0','" . $reporterUserId . "','" . $safePriority . "','',NULL,NULL,NULL,NULL,'" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            $fallbackSql = "INSERT INTO " . TASK_ITEM . "
                                                (project_id,column_id,title,work_type_id,assignee_user_id,due_date,sort_order,create_by,create_date,create_time,status)
                          VALUES
                                                ('" . $projectId . "','" . $columnId . "','" . $safeTitle . "','" . $workTypeId . "','" . $assigneeUserId . "'," . $safeDueDate . ",'" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";
            if (!mysqli_query($connect, $fallbackSql)) {
                return array('ok' => 0, 'message' => 'Failed to create work item.');
            }
        }

        $insertedId = (int) mysqli_insert_id($connect);
        $itemSql = "SELECT id,column_id,title,description,project_key_id,work_type_id,assignee_user_id,reporter_user_id,
                priority,start_date,due_date,task_status,create_date,update_date,
                original_estimate,amendement_date,amendement_time,second_amendement_date,second_amendement_time
                FROM " . TASK_ITEM . "
                WHERE id='" . $insertedId . "' LIMIT 1";

        $itemRst = mysqli_query($connect, $itemSql);
        if ($itemRst === false) {
            $itemSql = "SELECT id,column_id,title,'' AS description,0 AS project_key_id,work_type_id,assignee_user_id,0 AS reporter_user_id,
                        'Medium' AS priority,due_date AS start_date,due_date,'' AS task_status,'' AS create_date,'' AS update_date,
                        '' AS original_estimate,NULL AS amendement_date,NULL AS amendement_time,NULL AS second_amendement_date,NULL AS second_amendement_time
                        FROM " . TASK_ITEM . "
                        WHERE id='" . $insertedId . "' LIMIT 1";
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

            $workTypeMap = taskFetchWorkTypeInfoMap($connect, array($item['work_type_id']), true);
            $projectMap = taskFetchProjectKeyMap($connect, array($item['project_key_id']), true);
            $userMap = taskFetchUserDisplayMap($connect, array($item['assignee_user_id'], isset($item['reporter_user_id']) ? (int) $item['reporter_user_id'] : 0), true);

            $item['work_type_name'] = isset($workTypeMap[$item['work_type_id']]['name']) ? (string) $workTypeMap[$item['work_type_id']]['name'] : 'Task';
            $itemProjectKey = isset($projectMap[$item['project_key_id']]) ? (string) $projectMap[$item['project_key_id']] : '';
            if ($itemProjectKey === '') {
                $itemProjectKey = taskNormalizeProjectKey($projectKeyText);
            }
            $item['project_key'] = $itemProjectKey;
            $item['work_item_key'] = taskBuildWorkItemKey($itemProjectKey, $insertedId);
            $item['work_type_svg_icon'] = isset($workTypeMap[$item['work_type_id']]['svg_icon']) ? (string) $workTypeMap[$item['work_type_id']]['svg_icon'] : taskDefaultWorkTypeSvgIcon($item['work_type_name']);
            $item['assignee_name'] = isset($userMap[$item['assignee_user_id']]) ? (string) $userMap[$item['assignee_user_id']] : '';
            $item['reporter_user_id'] = isset($item['reporter_user_id']) ? (int) $item['reporter_user_id'] : 0;
            $item['reporter_name'] = isset($userMap[$item['reporter_user_id']]) ? (string) $userMap[$item['reporter_user_id']] : '';
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

        $result = mysqli_query($connect, "SELECT id FROM " . TASK_ITEM . " WHERE status='A' AND column_id='" . $columnId . "' ORDER BY sort_order ASC, id ASC");
        if (!$result) {
            return;
        }

        $seq = 1;
        while ($row = $result->fetch_assoc()) {
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
                taskFormatHistoryUserValue($connect, $previousAssigneeUserId),
                taskFormatHistoryUserValue($connect, $assigneeUserId),
                'changed Assignee',
                $currentUserId,
                $cdate,
                $ctime
            );
            taskSendAssigneeReassignmentAlert($connect, $itemId, $previousAssigneeUserId, $assigneeUserId, $currentUserId, $cdate, $ctime);
        }

        $itemSql = "SELECT assignee_user_id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        $assigneeName = '';
        if ($itemRst && $itemRst->num_rows > 0) {
            $row = $itemRst->fetch_assoc();
            $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
            $userMap = taskFetchUserDisplayMap($connect, array($assigneeUserId), true);
            $assigneeName = isset($userMap[$assigneeUserId]) ? (string) $userMap[$assigneeUserId] : '';
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

        $itemSql = "SELECT id,work_type_id
                    FROM " . TASK_ITEM . "
                    WHERE id='" . $itemId . "' AND status='A'
                    LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $previousWorkTypeId = isset($itemRow['work_type_id']) ? (int) $itemRow['work_type_id'] : 0;
        $previousTypeMap = taskFetchWorkTypeInfoMap($connect, array($previousWorkTypeId), true);
        $previousWorkTypeName = isset($previousTypeMap[$previousWorkTypeId]['name']) ? (string) $previousTypeMap[$previousWorkTypeId]['name'] : 'Task';
        $previousParentInfo = taskGetParentRelationInfo($connect, $itemId);

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

        $isTargetEpic = strtolower($workTypeName) === 'epic';

        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

        mysqli_begin_transaction($connect);

        $okUpdate = mysqli_query(
            $connect,
            "UPDATE " . TASK_ITEM . " SET
                work_type_id='" . $workTypeId . "',
                parent_item_id='" . ($isTargetEpic ? 0 : (int) (isset($previousParentInfo['parent_item_id']) ? $previousParentInfo['parent_item_id'] : 0)) . "',
                update_by='" . $safeUser . "',
                update_date='" . $safeDate . "',
                update_time='" . $safeTime . "'
             WHERE id='" . $itemId . "' AND status='A'"
        );

        if (!$okUpdate) {
            mysqli_rollback($connect);
            return array('ok' => 0, 'message' => 'Failed to update work type.');
        }

        if ($isTargetEpic) {
            $okUnlink = mysqli_query(
                $connect,
                "UPDATE " . TASK_ITEM . " SET
                    parent_item_id='0',
                    update_by='" . $safeUser . "',
                    update_date='" . $safeDate . "',
                    update_time='" . $safeTime . "'
                 WHERE id='" . $itemId . "' AND status='A'"
            );

            if (!$okUnlink) {
                mysqli_rollback($connect);
                return array('ok' => 0, 'message' => 'Failed to clear Epic parent relation.');
            }

            if (defined('TASK_ITEM_RELATION')) {
                $okDeleteRelation = mysqli_query(
                    $connect,
                    "DELETE FROM " . TASK_ITEM_RELATION . "
                     WHERE child_board_item_id='" . $itemId . "'"
                );
                if ($okDeleteRelation === false) {
                    mysqli_rollback($connect);
                    return array('ok' => 0, 'message' => 'Failed deleting parent relation from db.');
                }
            }
        }

        mysqli_commit($connect);

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

        if ($isTargetEpic) {
            $oldParentDisplay = isset($previousParentInfo['parent_display']) ? trim((string) $previousParentInfo['parent_display']) : 'None';
            if ($oldParentDisplay !== '' && strtolower($oldParentDisplay) !== 'none') {
                taskLogItemHistory(
                    $connect,
                    $itemId,
                    'update_field',
                    'Parent',
                    $oldParentDisplay,
                    'None',
                    'cleared Parent after changing Work type to Epic',
                    $currentUserId,
                    $cdate,
                    $ctime
                );
            }
        }

        $latestParentInfo = taskGetParentRelationInfo($connect, $itemId);

        return array(
            'ok' => 1,
            'message' => 'Work type updated successfully.',
            'work_type' => array(
                'id' => $workTypeId,
                'name' => $workTypeName,
                'remark' => isset($workTypeRow['remark']) ? (string) $workTypeRow['remark'] : '',
                'svg_icon' => taskNormalizeWorkTypeSvgIcon(isset($workTypeRow['svg_icon']) ? $workTypeRow['svg_icon'] : '', $workTypeName),
            ),
            'parent_item_id' => isset($latestParentInfo['parent_item_id']) ? (int) $latestParentInfo['parent_item_id'] : 0,
            'parent_display' => isset($latestParentInfo['parent_display']) ? (string) $latestParentInfo['parent_display'] : 'None',
            'parent_relation_removed' => $isTargetEpic ? 1 : 0,
        );
    }
}

if (!function_exists('taskUpdateItemCore')) {
    function taskUpdateItemCore($connect, $itemId, $title, $description, $currentUserId, $cdate, $ctime, $trackedAttachmentPaths = array())
    {
        $itemId = (int) $itemId;
        $title = trim((string) $title);

        if ($itemId <= 0 || $title === '') {
            return array('ok' => 0, 'message' => 'Invalid work item update request.');
        }

        $itemRst = mysqli_query($connect, "SELECT id,title,description,description_color_html FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1");

        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $itemRow = $itemRst->fetch_assoc();
        $previousTitle = isset($itemRow['title']) ? trim((string) $itemRow['title']) : '';
        $previousDescription = isset($itemRow['description']) && $itemRow['description'] !== null ? trim((string) $itemRow['description']) : '';
        $hasDescriptionUpdate = $description !== null;
        $description = $hasDescriptionUpdate ? taskSanitizeCommentHtml($description) : $previousDescription;
        $description = trim((string) $description);

        $safeTitle = taskEsc($connect, substr($title, 0, 255));
        $safeDescription = taskEsc($connect, substr($description, 0, 65535));
        $safeDescriptionColorHtml = taskEsc($connect, substr($description, 0, 65535));
        $safeUser = taskEsc($connect, $currentUserId);
        $safeDate = taskEsc($connect, $cdate);
        $safeTime = taskEsc($connect, $ctime);

                if ($hasDescriptionUpdate) {
                        $updateSql = "UPDATE " . TASK_ITEM . " SET
                                                        title='" . $safeTitle . "',
                                                        description='" . $safeDescription . "',
                                                        description_color_html='" . $safeDescriptionColorHtml . "',
                                                        update_by='" . $safeUser . "',
                                                        update_date='" . $safeDate . "',
                                                        update_time='" . $safeTime . "'
                                                    WHERE id='" . $itemId . "' AND status='A'";
                } else {
                        $updateSql = "UPDATE " . TASK_ITEM . " SET
                                                        title='" . $safeTitle . "',
                                                        update_by='" . $safeUser . "',
                                                        update_date='" . $safeDate . "',
                                                        update_time='" . $safeTime . "'
                                                    WHERE id='" . $itemId . "' AND status='A'";
                }

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

        if ($hasDescriptionUpdate && $previousDescription !== $description) {
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

            taskSendMentionAlerts(
                $connect,
                $itemId,
                $description,
                $previousDescription,
                $currentUserId,
                'description',
                $cdate,
                $ctime
            );

            taskDeleteRemovedEditorAttachments(
                $connect,
                $itemId,
                $previousDescription,
                $description,
                $currentUserId,
                $cdate,
                $ctime,
                array('attachment/board/description/'),
                $trackedAttachmentPaths
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
        $result = mysqli_query($connect, $sql);
        if ($result === false) {
            return array();
        }

        while ($row = $result->fetch_assoc()) {
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

if (!function_exists('taskBuildWorkItemKeyFolder')) {
    /**
     * Build a folder name from project key + item id, e.g. "ATM-17".
     * Falls back to just the numeric item id if no project key is configured.
     */
    function taskBuildWorkItemKeyFolder($connect, $itemId)
    {
        $itemId = (int) $itemId;
        $projectId = taskGetItemProjectId($connect, $itemId);
        if ($itemId <= 0) {
            return '0';
        }

        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $projectKey = isset($projectKeySetting['project_key']) ? trim((string) $projectKeySetting['project_key']) : '';
        $projectKey = strtoupper((string) preg_replace('/[^A-Z0-9_-]+/i', '', $projectKey));

        if ($projectKey !== '') {
            return $projectKey . '-' . $itemId;
        }

        return (string) $itemId;
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

        $workItemKeyFolder = taskBuildWorkItemKeyFolder($connect, $itemId);

        $safeFileName = taskSanitizeUploadFileName(isset($fileInfo['name']) ? $fileInfo['name'] : '');
        $namePart = pathinfo($safeFileName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeFileName, PATHINFO_EXTENSION);
        $dateTimeFolder = preg_replace('/[^0-9]/', '', (string) $cdate . (string) $ctime);
        if ($dateTimeFolder === '') {
            $dateTimeFolder = date('YmdHis');
        }

        $relativeDir = 'attachment/board/' . $workItemKeyFolder . '/' . $dateTimeFolder;
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

if (!function_exists('taskUploadItemCommentAttachment')) {
    function taskUploadItemCommentAttachment($connect, $itemId, $fileInfo, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid comment attachment request.');
        }

        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['error'])) {
            return array('ok' => 0, 'message' => 'No comment attachment uploaded.');
        }

        if ((int) $fileInfo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => 0, 'message' => 'Comment attachment upload failed.');
        }

        if (empty($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            return array('ok' => 0, 'message' => 'Invalid uploaded comment attachment.');
        }

        $itemSql = "SELECT id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $workItemKeyFolder = taskBuildWorkItemKeyFolder($connect, $itemId);

        $safeFileName = taskSanitizeUploadFileName(isset($fileInfo['name']) ? $fileInfo['name'] : '');
        $namePart = pathinfo($safeFileName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeFileName, PATHINFO_EXTENSION);
        $dateTimeFolder = preg_replace('/[^0-9]/', '', (string) $cdate . (string) $ctime);
        if ($dateTimeFolder === '') {
            $dateTimeFolder = date('YmdHis');
        }

        $relativeDir = 'attachment/board/comment/' . $workItemKeyFolder . '/' . $dateTimeFolder;
        $absoluteDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
                return array('ok' => 0, 'message' => 'Failed to prepare comment attachment folder.');
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
            return array('ok' => 0, 'message' => 'Failed to store uploaded comment attachment.');
        }

        $relativePath = $relativeDir . '/' . $finalFileName;
        $siteUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        $fileUrl = $siteUrl !== '' ? ($siteUrl . '/' . ltrim($relativePath, '/')) : $relativePath;

        return array(
            'ok' => 1,
            'message' => 'Comment attachment uploaded successfully.',
            'attachment' => array(
                'file_name' => $finalFileName,
                'file_path' => $relativePath,
                'file_url' => $fileUrl,
                'file_size' => isset($fileInfo['size']) ? (int) $fileInfo['size'] : 0,
                'mime_type' => isset($fileInfo['type']) ? (string) $fileInfo['type'] : '',
            ),
        );
    }
}

if (!function_exists('taskUploadItemDescriptionAttachment')) {
    function taskUploadItemDescriptionAttachment($connect, $itemId, $fileInfo, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid description attachment request.');
        }

        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['error'])) {
            return array('ok' => 0, 'message' => 'No description attachment uploaded.');
        }

        if ((int) $fileInfo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => 0, 'message' => 'Description attachment upload failed.');
        }

        if (empty($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            return array('ok' => 0, 'message' => 'Invalid uploaded description attachment.');
        }

        $itemSql = "SELECT id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $workItemKeyFolder = taskBuildWorkItemKeyFolder($connect, $itemId);

        $safeFileName = taskSanitizeUploadFileName(isset($fileInfo['name']) ? $fileInfo['name'] : '');
        $namePart = pathinfo($safeFileName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeFileName, PATHINFO_EXTENSION);
        $dateTimeFolder = preg_replace('/[^0-9]/', '', (string) $cdate . (string) $ctime);
        if ($dateTimeFolder === '') {
            $dateTimeFolder = date('YmdHis');
        }

        $relativeDir = 'attachment/board/description/' . $workItemKeyFolder . '/' . $dateTimeFolder;
        $absoluteDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
                return array('ok' => 0, 'message' => 'Failed to prepare description attachment folder.');
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
            return array('ok' => 0, 'message' => 'Failed to store uploaded description attachment.');
        }

        $relativePath = $relativeDir . '/' . $finalFileName;
        $siteUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        $fileUrl = $siteUrl !== '' ? ($siteUrl . '/' . ltrim($relativePath, '/')) : $relativePath;

        return array(
            'ok' => 1,
            'message' => 'Description attachment uploaded successfully.',
            'attachment' => array(
                'file_name' => $finalFileName,
                'file_path' => $relativePath,
                'file_url' => $fileUrl,
                'file_size' => isset($fileInfo['size']) ? (int) $fileInfo['size'] : 0,
                'mime_type' => isset($fileInfo['type']) ? (string) $fileInfo['type'] : '',
            ),
        );
    }
}

if (!function_exists('taskUploadItemReplyAttachment')) {
    function taskUploadItemReplyAttachment($connect, $itemId, $fileInfo, $currentUserId, $cdate, $ctime)
    {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array('ok' => 0, 'message' => 'Invalid reply attachment request.');
        }

        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['error'])) {
            return array('ok' => 0, 'message' => 'No reply attachment uploaded.');
        }

        if ((int) $fileInfo['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => 0, 'message' => 'Reply attachment upload failed.');
        }

        if (empty($fileInfo['tmp_name']) || !is_uploaded_file($fileInfo['tmp_name'])) {
            return array('ok' => 0, 'message' => 'Invalid uploaded reply attachment.');
        }

        $itemSql = "SELECT id FROM " . TASK_ITEM . " WHERE id='" . $itemId . "' AND status='A' LIMIT 1";
        $itemRst = mysqli_query($connect, $itemSql);
        if (!$itemRst || $itemRst->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Work item not found.');
        }

        $workItemKeyFolder = taskBuildWorkItemKeyFolder($connect, $itemId);

        $safeFileName = taskSanitizeUploadFileName(isset($fileInfo['name']) ? $fileInfo['name'] : '');
        $namePart = pathinfo($safeFileName, PATHINFO_FILENAME);
        $extPart = pathinfo($safeFileName, PATHINFO_EXTENSION);
        $dateTimeFolder = preg_replace('/[^0-9]/', '', (string) $cdate . (string) $ctime);
        if ($dateTimeFolder === '') {
            $dateTimeFolder = date('YmdHis');
        }

        $relativeDir = 'attachment/board/reply/' . $workItemKeyFolder . '/' . $dateTimeFolder;
        $absoluteDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
                return array('ok' => 0, 'message' => 'Failed to prepare reply attachment folder.');
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
            return array('ok' => 0, 'message' => 'Failed to store uploaded reply attachment.');
        }

        $relativePath = $relativeDir . '/' . $finalFileName;
        $siteUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        $fileUrl = $siteUrl !== '' ? ($siteUrl . '/' . ltrim($relativePath, '/')) : $relativePath;

        return array(
            'ok' => 1,
            'message' => 'Reply attachment uploaded successfully.',
            'attachment' => array(
                'file_name' => $finalFileName,
                'file_path' => $relativePath,
                'file_url' => $fileUrl,
                'file_size' => isset($fileInfo['size']) ? (int) $fileInfo['size'] : 0,
                'mime_type' => isset($fileInfo['type']) ? (string) $fileInfo['type'] : '',
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
        $result = mysqli_query($connect, $sql);
        if ($result === false) {
            return array('ok' => 0, 'message' => 'Failed deleting attachment. Please run insert_table.php first.');
        }

        if ($result->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Attachment not found.');
        }

        $row = $result->fetch_assoc();
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
        $result = mysqli_query($connect, $sql);
        if ($result === false) {
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

        while ($row = $result->fetch_assoc()) {
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
    function taskCreateLabel($connect, $labelName, $currentUserId, $cdate, $ctime, $color = '')
    {
        $labelName = trim((string) $labelName);
        if ($labelName === '') {
            return array('ok' => 0, 'message' => 'Label name is required.');
        }

        $safeName = taskEsc($connect, substr($labelName, 0, 120));
        $normalizedColor = taskNormalizeHexColor($color, '#DCE8FF');
        $existingRst = mysqli_query($connect, "SELECT id,status FROM " . TASK_LABEL . " WHERE LOWER(name)=LOWER('" . $safeName . "') LIMIT 1");
        if ($existingRst && $existingRst->num_rows > 0) {
            $existing = $existingRst->fetch_assoc();
            $labelId = (int) $existing['id'];
            if ((string) $existing['status'] !== 'A') {
                $safeUser = taskEsc($connect, $currentUserId);
                $safeDate = taskEsc($connect, $cdate);
                $safeTime = taskEsc($connect, $ctime);
                mysqli_query($connect, "UPDATE " . TASK_LABEL . " SET status='A', color='" . taskEsc($connect, $normalizedColor) . "', update_by='" . $safeUser . "', update_date='" . $safeDate . "', update_time='" . $safeTime . "' WHERE id='" . $labelId . "'");
            }

            return array('ok' => 1, 'message' => 'Label ready.', 'label' => array('id' => $labelId, 'name' => $labelName, 'color' => $normalizedColor));
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

        $insertSql = "INSERT INTO " . TASK_LABEL . " (name,color,sort_order,create_by,create_date,create_time,status)
                      VALUES ('" . $safeName . "','" . taskEsc($connect, $normalizedColor) . "','" . $sortOrder . "','" . $safeUser . "','" . $safeDate . "','" . $safeTime . "','A')";

        if (!mysqli_query($connect, $insertSql)) {
            return array('ok' => 0, 'message' => 'Failed to create label.');
        }

        return array(
            'ok' => 1,
            'message' => 'Label created successfully.',
            'label' => array(
                'id' => (int) mysqli_insert_id($connect),
                'name' => $labelName,
                'color' => $normalizedColor,
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

        $result = mysqli_query($connect, "SELECT id FROM " . TASK_LABEL . " WHERE id='" . $labelId . "' AND status='A' LIMIT 1");
        if ($result === false) {
            return array('ok' => 0, 'message' => 'Failed to delete label. Please run insert_table.php first.');
        }
        if ($result->num_rows === 0) {
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

        $result = mysqli_query($connect, "SELECT id,name FROM " . TASK_STATUS_LABEL . " WHERE id='" . $statusLabelId . "' AND status='A' LIMIT 1");
        if ($result === false) {
            return array('ok' => 0, 'message' => 'Failed to delete task status label. Please run insert_table.php first.');
        }
        if ($result->num_rows === 0) {
            return array('ok' => 0, 'message' => 'Task status label not found.');
        }

        $row = $result->fetch_assoc();
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
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            return array();
        }

        $map = taskGetItemLabelsByItemIds($connect, array($itemId));
        return isset($map[$itemId]) ? $map[$itemId] : array();
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

if (!function_exists('taskGetAllItemsFlat')) {
    /**
     * Return all active work items as a flat array with time_tracking resolved.
     * Used by the Sheets view.
     */
    function taskGetAllItemsFlat($connect, $projectId = 0)
    {
        $projectId = (int) $projectId;
        $projectKeySetting = taskGetProjectKeySetting($connect, $projectId);
        $defaultProjectKeyId = isset($projectKeySetting['id']) ? (int) $projectKeySetting['id'] : 0;
        $defaultProjectKey = isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '';

        $sql = "SELECT id,column_id,title,description,description_color_html,work_type_id,assignee_user_id,reporter_user_id,
            project_id,
            priority,start_date,due_date,task_status,create_date,update_date,
            original_estimate,time_tracking,
            amendement_date,amendement_time,second_amendement_date,second_amendement_time,
            sort_order,project_key_id
            FROM " . TASK_ITEM . "
            WHERE status='A'";
        if ($projectId > 0) {
            $sql .= " AND project_id='" . $projectId . "'";
        }
        $sql .= "
            ORDER BY id DESC";

        $items = array();
        $allItemIds = array();
        $result = mysqli_query($connect, $sql);
        if ($result) {
            $rows = array();
            $workTypeIds = array();
            $projectKeyIds = array();
            $assigneeIds = array();
            $reporterIds = array();

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                $workTypeIds[] = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
                $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
                $assigneeIds[] = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
                $reporterIds[] = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
            }

            $workTypeMap = taskFetchWorkTypeInfoMap($connect, $workTypeIds, true);
            $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, true);
            $userMap = taskFetchUserDisplayMap($connect, array_merge($assigneeIds, $reporterIds), true);

            foreach ($rows as $row) {
                $resolvedProjectKeyId = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
                $resolvedProjectKey = isset($projectKeyMap[$resolvedProjectKeyId]) ? (string) $projectKeyMap[$resolvedProjectKeyId] : '';
                if ($resolvedProjectKey === '') {
                    $resolvedProjectKey = taskNormalizeProjectKey($defaultProjectKey);
                }
                if ($resolvedProjectKeyId <= 0) {
                    $resolvedProjectKeyId = $defaultProjectKeyId;
                }
                $estimate = taskParseOriginalEstimate(isset($row['original_estimate']) ? $row['original_estimate'] : '');
                $timeTracking = isset($row['time_tracking']) ? trim((string) $row['time_tracking']) : '';
                if ($timeTracking !== '') {
                    $ttSeconds = taskParseWorklogDurationSeconds($timeTracking);
                    $timeTracking = $ttSeconds > 0 ? taskFormatWorklogDuration($ttSeconds) : 'No time logged';
                } else {
                    $timeTracking = 'No time logged';
                }

                $workTypeId = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
                $assigneeUserId = isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0;
                $reporterUserId = isset($row['reporter_user_id']) ? (int) $row['reporter_user_id'] : 0;
                $workTypeInfo = isset($workTypeMap[$workTypeId]) ? $workTypeMap[$workTypeId] : array(
                    'name' => 'Task',
                    'svg_icon' => taskDefaultWorkTypeSvgIcon('Task'),
                );

                $item = array(
                    'id' => (int) $row['id'],
                    'column_id' => (int) $row['column_id'],
                    'title' => (string) $row['title'],
                    'description' => isset($row['description_color_html']) && trim((string) $row['description_color_html']) !== ''
                        ? (string) $row['description_color_html']
                        : (isset($row['description']) && $row['description'] !== null ? (string) $row['description'] : ''),
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
                    'project_key_id' => $resolvedProjectKeyId,
                    'project_key' => $resolvedProjectKey,
                    'work_item_key' => taskBuildWorkItemKey($resolvedProjectKey, (int) $row['id']),
                    'work_type_id' => $workTypeId,
                    'work_type_name' => isset($workTypeInfo['name']) ? (string) $workTypeInfo['name'] : 'Task',
                    'work_type_svg_icon' => isset($workTypeInfo['svg_icon']) ? (string) $workTypeInfo['svg_icon'] : taskDefaultWorkTypeSvgIcon('Task'),
                    'assignee_user_id' => $assigneeUserId,
                    'assignee_name' => isset($userMap[$assigneeUserId]) ? (string) $userMap[$assigneeUserId] : '',
                    'reporter_user_id' => $reporterUserId,
                    'reporter_name' => isset($userMap[$reporterUserId]) ? (string) $userMap[$reporterUserId] : '',
                    'priority' => taskNormalizePriority(isset($row['priority']) ? $row['priority'] : 'Medium'),
                    'original_estimate_value' => isset($estimate['value']) ? (int) $estimate['value'] : 0,
                    'original_estimate_unit' => isset($estimate['unit']) ? (string) $estimate['unit'] : 'minutes',
                    'task_status' => isset($row['task_status']) && $row['task_status'] !== null ? (string) $row['task_status'] : '',
                    'time_tracking' => $timeTracking,
                    'start_date' => isset($row['start_date']) && $row['start_date'] !== null ? (string) $row['start_date'] : '',
                    'due_date' => isset($row['due_date']) && $row['due_date'] !== null ? (string) $row['due_date'] : '',
                    'create_date' => isset($row['create_date']) && $row['create_date'] !== null ? (string) $row['create_date'] : '',
                    'update_date' => isset($row['update_date']) && $row['update_date'] !== null ? (string) $row['update_date'] : '',
                    'amendement_date' => isset($row['amendement_date']) && $row['amendement_date'] !== null ? (string) $row['amendement_date'] : '',
                    'amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['amendement_time']) ? $row['amendement_time'] : ''),
                    'second_amendement_date' => isset($row['second_amendement_date']) && $row['second_amendement_date'] !== null ? (string) $row['second_amendement_date'] : '',
                    'second_amendement_time_minutes' => taskSqlTimeToMinutes(isset($row['second_amendement_time']) ? $row['second_amendement_time'] : ''),
                );

                $items[] = $item;
                $allItemIds[] = (int) $row['id'];
            }
        }

        // Enrich with labels and parent
        $labelsMap = taskGetItemLabelsByItemIds($connect, $allItemIds);
        $parentMap = taskGetParentMapByChildIds($connect, $allItemIds);
        foreach ($items as $index => $item) {
            $itemId = (int) $item['id'];
            $items[$index]['labels'] = isset($labelsMap[$itemId]) ? $labelsMap[$itemId] : array();
            $items[$index]['parent_item_id'] = isset($parentMap[$itemId]) ? (int) $parentMap[$itemId] : 0;
        }

        return $items;
    }
}

/* ----- Sheets Column Configuration ----- */

if (!function_exists('taskGetSheetsColumns')) {
    function taskGetSheetsColumns($connect, $userId, $projectId = 0) {
        $userId = (int) $userId;
        $projectId = (int) $projectId;
        $sql = "SELECT id, column_key, sort_order FROM " . TASK_SHEETS . " WHERE user_id = $userId AND status = 'A'";
        if ($projectId > 0) {
            $sql .= " AND project_id = " . $projectId;
        }
        $sql .= " ORDER BY sort_order ASC";
        $result = mysqli_query($connect, $sql);
        $cols = array();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $cols[] = array(
                    'id' => (int) $row['id'],
                    'column_key' => (string) $row['column_key'],
                    'sort_order' => (int) $row['sort_order'],
                );
            }
        }
        return $cols;
    }
}

if (!function_exists('taskSaveSheetsColumns')) {
    function taskSaveSheetsColumns($connect, $userId, $projectId, $columnsJson) {
        $userId = (int) $userId;
        $projectId = (int) $projectId;
        $currentUser = defined('USER_ID') ? USER_ID : '';
        $cdate = date('Y-m-d');
        $ctime = date('G:i:s');

        // Soft-delete existing
        $delSql = "UPDATE " . TASK_SHEETS . " SET status = 'D', update_by = '" . mysqli_real_escape_string($connect, $currentUser) . "', update_date = '$cdate', update_time = '$ctime' WHERE user_id = $userId AND project_id = $projectId AND status = 'A'";
        mysqli_query($connect, $delSql);

        // Insert new
        $cols = json_decode($columnsJson, true);
        if (!is_array($cols)) return array();

        foreach ($cols as $idx => $col) {
            $key = mysqli_real_escape_string($connect, $col['column_key']);
            $order = (int) (isset($col['sort_order']) ? $col['sort_order'] : $idx);
            $sql = "INSERT INTO " . TASK_SHEETS . " (project_id, user_id, column_key, sort_order, create_by, create_date, create_time, status) VALUES ($projectId, $userId, '$key', $order, '" . mysqli_real_escape_string($connect, $currentUser) . "', '$cdate', '$ctime', 'A')";
            mysqli_query($connect, $sql);
        }

        return taskGetSheetsColumns($connect, $userId, $projectId);
    }
}

/* ----- Summary page helpers ----- */

if (!function_exists('taskSummaryNormalizeUnit')) {
    function taskSummaryNormalizeUnit($unit)
    {
        $raw = strtolower(trim((string) $unit));
        if ($raw === 'minute' || $raw === 'minutes' || $raw === 'min' || $raw === 'mins') {
            return 'MINUTE';
        }
        if ($raw === 'hour' || $raw === 'hours' || $raw === 'hr' || $raw === 'hrs') {
            return 'HOUR';
        }
        if ($raw === 'week' || $raw === 'weeks' || $raw === 'w') {
            return 'WEEK';
        }
        return 'DAY';
    }
}

if (!function_exists('taskSummaryIsValidDate')) {
    function taskSummaryIsValidDate($value)
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}

if (!function_exists('taskSummaryParseRelativeDurationSeconds')) {
    function taskSummaryParseRelativeDurationSeconds($text)
    {
        $value = trim((string) $text);
        if ($value === '') {
            return null;
        }

        $globalSign = 1;
        if (strpos($value, '-') === 0) {
            $globalSign = -1;
            $value = ltrim(substr($value, 1));
        } elseif (strpos($value, '+') === 0) {
            $value = ltrim(substr($value, 1));
        }

        if (!preg_match_all('/([+-]?\d+)\s*([wdhm])/i', $value, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $seconds = 0;
        foreach ($matches as $match) {
            $num = isset($match[1]) ? (int) $match[1] : 0;
            $unit = isset($match[2]) ? strtolower($match[2]) : '';
            if ($num === 0 || $unit === '') {
                continue;
            }

            $absNum = abs($num);
            $unitSeconds = 0;
            if ($unit === 'w') {
                $unitSeconds = 7 * 24 * 3600;
            } elseif ($unit === 'd') {
                $unitSeconds = 24 * 3600;
            } elseif ($unit === 'h') {
                $unitSeconds = 3600;
            } elseif ($unit === 'm') {
                $unitSeconds = 60;
            }

            $sign = $num < 0 ? -1 : 1;
            $seconds += ($absNum * $unitSeconds * $sign);
        }

        if ($seconds === 0) {
            return 0;
        }

        return $seconds * $globalSign;
    }
}

if (!function_exists('taskSummaryBuildListConditionSql')) {
    function taskSummaryBuildListConditionSql($connect, $expression, $values, $operator = 'eq', $isNumeric = true, $allowNone = false)
    {
        if (!is_array($values)) {
            return '';
        }

        $op = strtolower(trim((string) $operator)) === 'neq' ? 'neq' : 'eq';
        $parts = array();
        $normalized = array();
        $hasNone = false;

        foreach ($values as $value) {
            $strVal = trim((string) $value);
            if ($strVal === '') {
                continue;
            }

            if ($allowNone && ($strVal === '0' || strtolower($strVal) === 'none' || strtolower($strVal) === 'unassigned')) {
                $hasNone = true;
                continue;
            }

            if ($isNumeric) {
                if (ctype_digit($strVal)) {
                    $num = (int) $strVal;
                    if ($num > 0) {
                        $normalized[] = $num;
                    }
                }
            } else {
                $normalized[] = taskEsc($connect, $strVal);
            }
        }

        if (!empty($normalized)) {
            if ($isNumeric) {
                $parts[] = $expression . ' IN (' . implode(',', array_map('intval', $normalized)) . ')';
            } else {
                $quoted = array();
                foreach ($normalized as $val) {
                    $quoted[] = "'" . $val . "'";
                }
                $parts[] = $expression . ' IN (' . implode(',', $quoted) . ')';
            }
        }

        if ($allowNone && $hasNone) {
            $parts[] = '(' . $expression . ' IS NULL OR ' . $expression . " = 0 OR " . $expression . " = '')";
        }

        if (empty($parts)) {
            return '';
        }

        $baseExpr = '(' . implode(' OR ', $parts) . ')';
        return $op === 'neq' ? '(NOT ' . $baseExpr . ')' : $baseExpr;
    }
}

if (!function_exists('taskSummaryBuildDateConditionSql')) {
    function taskSummaryBuildDateConditionSql($alias, $column, $filter)
    {
        if (!is_array($filter)) {
            return '';
        }

        $mode = strtolower(trim((string) (isset($filter['mode']) ? $filter['mode'] : '')));
        $field = $alias . '.' . $column;
        $dateTimeField = 'CAST(' . $field . ' AS DATETIME)';

        if ($mode === 'within' || $mode === 'more') {
            $value = isset($filter['value']) ? (int) $filter['value'] : 0;
            if ($value <= 0) {
                return '';
            }
            $unit = taskSummaryNormalizeUnit(isset($filter['unit']) ? $filter['unit'] : 'days');
            $cmp = $mode === 'within' ? '>=' : '<';
            return '(' . $field . ' IS NOT NULL AND ' . $dateTimeField . ' ' . $cmp . ' DATE_SUB(NOW(), INTERVAL ' . $value . ' ' . $unit . '))';
        }

        if ($mode === 'between') {
            $from = isset($filter['from']) ? trim((string) $filter['from']) : '';
            $to = isset($filter['to']) ? trim((string) $filter['to']) : '';
            if (!taskSummaryIsValidDate($from) || !taskSummaryIsValidDate($to)) {
                return '';
            }
            if ($from > $to) {
                $tmp = $from;
                $from = $to;
                $to = $tmp;
            }
            return '(' . $field . ' IS NOT NULL AND DATE(' . $field . ") BETWEEN '" . $from . "' AND '" . $to . "')";
        }

        if ($mode === 'range') {
            $fromSeconds = taskSummaryParseRelativeDurationSeconds(isset($filter['range_from']) ? $filter['range_from'] : '');
            $toSeconds = taskSummaryParseRelativeDurationSeconds(isset($filter['range_to']) ? $filter['range_to'] : '');
            if ($fromSeconds === null || $toSeconds === null) {
                return '';
            }

            $startTs = time() + (int) $fromSeconds;
            $endTs = time() + (int) $toSeconds;
            if ($startTs > $endTs) {
                $tmp = $startTs;
                $startTs = $endTs;
                $endTs = $tmp;
            }

            $start = date('Y-m-d H:i:s', $startTs);
            $end = date('Y-m-d H:i:s', $endTs);
            return '(' . $field . ' IS NOT NULL AND ' . $dateTimeField . " BETWEEN '" . $start . "' AND '" . $end . "')";
        }

        return '';
    }
}

if (!function_exists('taskSummaryBuildDueDateConditionSql')) {
    function taskSummaryBuildDueDateConditionSql($alias, $filter)
    {
        if (!is_array($filter)) {
            return '';
        }

        $mode = strtolower(trim((string) (isset($filter['mode']) ? $filter['mode'] : '')));
        $field = $alias . '.due_date';
        $dateTimeField = 'CAST(' . $field . ' AS DATETIME)';

        if ($mode === 'overdue') {
            return '(' . $field . ' IS NOT NULL AND DATE(' . $field . ') < CURDATE())';
        }

        if ($mode === 'more') {
            $value = isset($filter['value']) ? (int) $filter['value'] : 0;
            if ($value <= 0) {
                return '';
            }
            $unit = taskSummaryNormalizeUnit(isset($filter['unit']) ? $filter['unit'] : 'days');
            return '(' . $field . ' IS NOT NULL AND ' . $dateTimeField . ' <= DATE_SUB(NOW(), INTERVAL ' . $value . ' ' . $unit . '))';
        }

        if ($mode === 'due_next') {
            $value = isset($filter['value']) ? (int) $filter['value'] : 0;
            if ($value <= 0) {
                return '';
            }
            $unit = taskSummaryNormalizeUnit(isset($filter['unit']) ? $filter['unit'] : 'days');
            $includeOverdue = !empty($filter['include_overdue']);
            $cond = '(' . $field . ' IS NOT NULL AND ' . $dateTimeField . ' <= DATE_ADD(NOW(), INTERVAL ' . $value . ' ' . $unit . '))';
            if (!$includeOverdue) {
                $cond = '(' . $cond . ' AND DATE(' . $field . ') >= CURDATE())';
            }
            return $cond;
        }

        if ($mode === 'between') {
            $from = isset($filter['from']) ? trim((string) $filter['from']) : '';
            $to = isset($filter['to']) ? trim((string) $filter['to']) : '';
            if (!taskSummaryIsValidDate($from) || !taskSummaryIsValidDate($to)) {
                return '';
            }
            if ($from > $to) {
                $tmp = $from;
                $from = $to;
                $to = $tmp;
            }
            return '(' . $field . ' IS NOT NULL AND DATE(' . $field . ") BETWEEN '" . $from . "' AND '" . $to . "')";
        }

        if ($mode === 'range') {
            $fromSeconds = taskSummaryParseRelativeDurationSeconds(isset($filter['range_from']) ? $filter['range_from'] : '');
            $toSeconds = taskSummaryParseRelativeDurationSeconds(isset($filter['range_to']) ? $filter['range_to'] : '');
            if ($fromSeconds === null || $toSeconds === null) {
                return '';
            }

            $startTs = time() + (int) $fromSeconds;
            $endTs = time() + (int) $toSeconds;
            if ($startTs > $endTs) {
                $tmp = $startTs;
                $startTs = $endTs;
                $endTs = $tmp;
            }

            $start = date('Y-m-d H:i:s', $startTs);
            $end = date('Y-m-d H:i:s', $endTs);
            return '(' . $field . ' IS NOT NULL AND ' . $dateTimeField . " BETWEEN '" . $start . "' AND '" . $end . "')";
        }

        return '';
    }
}

if (!function_exists('taskBuildSummaryItemFilterSql')) {
    function taskBuildSummaryItemFilterSql($connect, $filters = array(), $alias = 'i')
    {
        $clauses = array();

        if (!empty($filters['assignee_id'])) {
            $clauses[] = $alias . '.assignee_user_id = ' . (int) $filters['assignee_id'];
        }

        if (!empty($filters['assignee']) && is_array($filters['assignee'])) {
            $assigneeValues = isset($filters['assignee']['values']) && is_array($filters['assignee']['values']) ? $filters['assignee']['values'] : array();
            $assigneeOp = isset($filters['assignee']['op']) ? $filters['assignee']['op'] : 'eq';
            $assigneeClause = taskSummaryBuildListConditionSql($connect, $alias . '.assignee_user_id', $assigneeValues, $assigneeOp, true, true);
            if ($assigneeClause !== '') {
                $clauses[] = $assigneeClause;
            }
        }

        if (!empty($filters['work_type']) && is_array($filters['work_type'])) {
            $workTypeValues = isset($filters['work_type']['values']) && is_array($filters['work_type']['values']) ? $filters['work_type']['values'] : array();
            $workTypeOp = isset($filters['work_type']['op']) ? $filters['work_type']['op'] : 'eq';
            $workTypeClause = taskSummaryBuildListConditionSql($connect, $alias . '.work_type_id', $workTypeValues, $workTypeOp, true, false);
            if ($workTypeClause !== '') {
                $clauses[] = $workTypeClause;
            }
        }

        if (!empty($filters['status']) && is_array($filters['status'])) {
            $statusValues = isset($filters['status']['values']) && is_array($filters['status']['values']) ? $filters['status']['values'] : array();
            $statusOp = isset($filters['status']['op']) ? $filters['status']['op'] : 'eq';
            $statusClause = taskSummaryBuildListConditionSql($connect, $alias . '.column_id', $statusValues, $statusOp, true, false);
            if ($statusClause !== '') {
                $clauses[] = $statusClause;
            }
        }

        if (!empty($filters['priority']) && is_array($filters['priority'])) {
            $priorityValues = isset($filters['priority']['values']) && is_array($filters['priority']['values']) ? $filters['priority']['values'] : array();
            $priorityOp = isset($filters['priority']['op']) ? $filters['priority']['op'] : 'eq';
            $priorityClause = taskSummaryBuildListConditionSql($connect, "COALESCE(NULLIF(TRIM(" . $alias . ".priority),''), 'Medium')", $priorityValues, $priorityOp, false, false);
            if ($priorityClause !== '') {
                $clauses[] = $priorityClause;
            }
        }

        if (!empty($filters['parent']) && is_array($filters['parent'])) {
            $parentOp = strtolower(trim((string) (isset($filters['parent']['op']) ? $filters['parent']['op'] : 'eq'))) === 'neq' ? 'neq' : 'eq';
            $rawValues = isset($filters['parent']['values']) && is_array($filters['parent']['values']) ? $filters['parent']['values'] : array();
            $parentIds = array();
            $includeNone = false;
            foreach ($rawValues as $rawVal) {
                $rawStr = trim((string) $rawVal);
                if ($rawStr === '' || $rawStr === '0' || strtolower($rawStr) === 'none') {
                    $includeNone = true;
                    continue;
                }
                if (ctype_digit($rawStr)) {
                    $num = (int) $rawStr;
                    if ($num > 0) {
                        $parentIds[] = $num;
                    }
                }
            }

            $parentParts = array();
            if (!empty($parentIds)) {
                $idSql = implode(',', array_map('intval', array_unique($parentIds)));
                $parentParts[] = '(' . $alias . '.parent_item_id IN (' . $idSql . ') OR EXISTS (SELECT 1 FROM ' . TASK_ITEM_RELATION . ' srpr WHERE srpr.status=\'A\' AND srpr.child_board_item_id=' . $alias . '.id AND srpr.parent_board_item_id IN (' . $idSql . ')))';
            }
            if ($includeNone) {
                $parentParts[] = '(COALESCE(' . $alias . '.parent_item_id,0)=0 AND NOT EXISTS (SELECT 1 FROM ' . TASK_ITEM_RELATION . ' srpr WHERE srpr.status=\'A\' AND srpr.child_board_item_id=' . $alias . '.id AND srpr.parent_board_item_id > 0))';
            }

            if (!empty($parentParts)) {
                $parentExpr = '(' . implode(' OR ', $parentParts) . ')';
                $clauses[] = $parentOp === 'neq' ? '(NOT ' . $parentExpr . ')' : $parentExpr;
            }
        }

        if (!empty($filters['created']) && is_array($filters['created'])) {
            $createdClause = taskSummaryBuildDateConditionSql($alias, 'create_date', $filters['created']);
            if ($createdClause !== '') {
                $clauses[] = $createdClause;
            }
        }

        if (!empty($filters['updated']) && is_array($filters['updated'])) {
            $updatedClause = taskSummaryBuildDateConditionSql($alias, 'update_date', $filters['updated']);
            if ($updatedClause !== '') {
                $clauses[] = $updatedClause;
            }
        }

        if (!empty($filters['due_date']) && is_array($filters['due_date'])) {
            $dueClause = taskSummaryBuildDueDateConditionSql($alias, $filters['due_date']);
            if ($dueClause !== '') {
                $clauses[] = $dueClause;
            }
        }

        if (empty($clauses)) {
            return '1=1';
        }

        return implode(' AND ', $clauses);
    }
}

if (!function_exists('taskGetSummaryStats')) {
    /**
     * Return summary statistics for the task board.
     */
    function taskGetSummaryStats($connect, $filters = array(), $projectId = 0)
    {
        $projectId = (int) $projectId;
        $now = date('Y-m-d');
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
        $sevenDaysLater = date('Y-m-d', strtotime('+7 days'));

        $itemFilterSql = taskBuildSummaryItemFilterSql($connect, $filters, 'i');
        $where = "i.status='A'";
        if ($projectId > 0) {
            $where .= " AND i.project_id='" . $projectId . "'";
        }
        if ($itemFilterSql !== '1=1') {
            $where .= ' AND ' . $itemFilterSql;
        }

        $statusCounts = array();
        $totalItems = 0;
        $statusRows = array();
        $columnIds = array();
        $sql = "SELECT i.column_id, COUNT(i.id) AS cnt
                FROM " . TASK_ITEM . " i
                WHERE $where
                GROUP BY i.column_id";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $statusRows[] = $row;
                $columnIds[] = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            }
        }

        $columnMap = taskFetchColumnInfoMap($connect, $columnIds, true);
        usort($statusRows, function ($a, $b) use ($columnMap) {
            $colA = isset($a['column_id']) ? (int) $a['column_id'] : 0;
            $colB = isset($b['column_id']) ? (int) $b['column_id'] : 0;
            $sortA = isset($columnMap[$colA]['sort_order']) ? (int) $columnMap[$colA]['sort_order'] : PHP_INT_MAX;
            $sortB = isset($columnMap[$colB]['sort_order']) ? (int) $columnMap[$colB]['sort_order'] : PHP_INT_MAX;
            if ($sortA === $sortB) {
                return $colA <=> $colB;
            }
            return $sortA <=> $sortB;
        });

        foreach ($statusRows as $row) {
            $columnId = isset($row['column_id']) ? (int) $row['column_id'] : 0;
            $name = isset($columnMap[$columnId]['name']) && trim((string) $columnMap[$columnId]['name']) !== ''
                ? (string) $columnMap[$columnId]['name']
                : 'Unknown';
            $cnt = isset($row['cnt']) ? (int) $row['cnt'] : 0;
            $statusCounts[] = array('name' => $name, 'count' => $cnt);
            $totalItems += $cnt;
        }

        $parentCount = 0;
        $parentIds = array();
        $rstP1 = mysqli_query($connect, "SELECT DISTINCT i.parent_item_id AS pid FROM " . TASK_ITEM . " i WHERE $where AND i.parent_item_id IS NOT NULL AND i.parent_item_id > 0");
        if ($rstP1) {
            while ($row = $rstP1->fetch_assoc()) {
                $pid = isset($row['pid']) ? (int) $row['pid'] : 0;
                if ($pid > 0) {
                    $parentIds[$pid] = true;
                }
            }
        }
        $rstP2 = mysqli_query(
            $connect,
            "SELECT DISTINCT r.parent_board_item_id AS pid
             FROM " . TASK_ITEM_RELATION . " r
             WHERE r.status='A' AND r.parent_board_item_id > 0
               AND r.child_board_item_id IN (SELECT i.id FROM " . TASK_ITEM . " i WHERE $where)"
        );
        if ($rstP2) {
            while ($row = $rstP2->fetch_assoc()) {
                $pid = isset($row['pid']) ? (int) $row['pid'] : 0;
                if ($pid > 0) {
                    $parentIds[$pid] = true;
                }
            }
        }
        $parentCount = count($parentIds);

        $completedCount = 0;
        $sqlCompleted = "SELECT COUNT(*) AS cnt FROM " . TASK_ITEM_HISTORY . " h
                         WHERE h.status='A' AND h.event_type='change_status'
                           AND h.create_date >= '$sevenDaysAgo'
                           AND h.item_id IN (SELECT i.id FROM " . TASK_ITEM . " i WHERE $where)";

        $lastCol = '';
        $sqlLastCol = "SELECT name FROM " . TASK_COLUMN . " WHERE status='A' ORDER BY sort_order DESC, id DESC LIMIT 1";
        $rstLC = mysqli_query($connect, $sqlLastCol);
        if ($rstLC && $rowLC = $rstLC->fetch_assoc()) {
            $lastCol = (string) $rowLC['name'];
        }
        if ($lastCol !== '') {
            $sqlCompleted .= " AND h.to_value = '" . mysqli_real_escape_string($connect, $lastCol) . "'";
        }
        $rstC = mysqli_query($connect, $sqlCompleted);
        if ($rstC && $rowC = $rstC->fetch_assoc()) {
            $completedCount = (int) $rowC['cnt'];
        }

        $updatedCount = 0;
                $sqlUpdated = "SELECT COUNT(DISTINCT h.item_id) AS cnt FROM " . TASK_ITEM_HISTORY . " h
                                             WHERE h.status='A' AND h.create_date >= '$sevenDaysAgo'
                                                 AND h.item_id IN (SELECT i.id FROM " . TASK_ITEM . " i WHERE $where)";
        $rstU = mysqli_query($connect, $sqlUpdated);
        if ($rstU && $rowU = $rstU->fetch_assoc()) {
            $updatedCount = (int) $rowU['cnt'];
        }

        $createdCount = 0;
        $sqlCreated = "SELECT COUNT(*) AS cnt FROM " . TASK_ITEM . " i
                       WHERE i.status='A' AND i.create_date >= '$sevenDaysAgo'";
        if ($projectId > 0) {
            $sqlCreated .= " AND i.project_id='" . $projectId . "'";
        }
        if ($itemFilterSql !== '1=1') {
            $sqlCreated .= ' AND ' . $itemFilterSql;
        }
        $rstCr = mysqli_query($connect, $sqlCreated);
        if ($rstCr && $rowCr = $rstCr->fetch_assoc()) {
            $createdCount = (int) $rowCr['cnt'];
        }

        $dueSoonCount = 0;
        $sqlDue = "SELECT COUNT(*) AS cnt FROM " . TASK_ITEM . " i
                   WHERE i.status='A' AND i.due_date IS NOT NULL
                     AND i.due_date >= '$now' AND i.due_date <= '$sevenDaysLater'";
        if ($projectId > 0) {
            $sqlDue .= " AND i.project_id='" . $projectId . "'";
        }
        if ($itemFilterSql !== '1=1') {
            $sqlDue .= ' AND ' . $itemFilterSql;
        }
        $rstD = mysqli_query($connect, $sqlDue);
        if ($rstD && $rowD = $rstD->fetch_assoc()) {
            $dueSoonCount = (int) $rowD['cnt'];
        }

        $workTypeCounts = array();
        $sqlWT = "SELECT i.work_type_id, COUNT(i.id) AS cnt
                  FROM " . TASK_ITEM . " i
                  WHERE $where
                  GROUP BY i.work_type_id
                  ORDER BY cnt DESC";
        $rstWT = mysqli_query($connect, $sqlWT);
        $wtRows = array();
        $wtIds = array();
        if ($rstWT) {
            while ($row = $rstWT->fetch_assoc()) {
                $wtRows[] = $row;
                $wtIds[] = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
            }
        }
        $wtMap = taskFetchWorkTypeInfoMap($connect, $wtIds, true);
        foreach ($wtRows as $row) {
            $wtId = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
            $name = isset($wtMap[$wtId]['name']) ? (string) $wtMap[$wtId]['name'] : 'Unknown';
            $workTypeCounts[] = array(
                'name' => $name,
                'count' => isset($row['cnt']) ? (int) $row['cnt'] : 0,
            );
        }

        $priorityCounts = array();
        $sqlPri = "SELECT COALESCE(NULLIF(TRIM(i.priority),''), 'Medium') AS pri, COUNT(*) AS cnt
                   FROM " . TASK_ITEM . " i
                   WHERE $where
                   GROUP BY pri
                   ORDER BY FIELD(pri, 'Highest', 'High', 'Medium', 'Low', 'Lowest')";
        $rstPri = mysqli_query($connect, $sqlPri);
        if ($rstPri) {
            while ($row = $rstPri->fetch_assoc()) {
                $priorityCounts[] = array(
                    'name' => (string) $row['pri'],
                    'count' => (int) $row['cnt'],
                );
            }
        }

        return array(
            'total_items' => $totalItems,
            'completed_7d' => $completedCount,
            'updated_7d' => $updatedCount,
            'created_7d' => $createdCount,
            'due_soon_7d' => $dueSoonCount,
            'status_counts' => $statusCounts,
            'parent_count' => $parentCount,
            'work_type_counts' => $workTypeCounts,
            'priority_counts' => $priorityCounts,
        );
    }
}

if (!function_exists('taskGetGlobalActivity')) {
    /**
     * Fetch combined activity (history + comments + replies) across all items.
     * Returns unified entries sorted by date desc with pagination.
     */
    function taskGetGlobalActivity($connect, $page = 1, $perPage = 10, $filters = array(), $projectId = 0)
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(100000, (int) $perPage));
        $projectId = (int) $projectId;
        $offset = ($page - 1) * $perPage;
        $itemFilterSql = taskBuildSummaryItemFilterSql($connect, $filters, 'i');

        $where = "i.status='A'";
        if ($projectId > 0) {
            $where .= " AND i.project_id='" . $projectId . "'";
        }
        if ($itemFilterSql !== '1=1') {
            $where .= ' AND ' . $itemFilterSql;
        }

        $itemMap = array();
        $itemIds = array();
        $workTypeIds = array();
        $projectKeyIds = array();
        $actorIds = array();
        $itemRst = mysqli_query($connect, "SELECT i.id,i.title,i.work_type_id,i.project_key_id,i.column_id,i.priority,i.assignee_user_id FROM " . TASK_ITEM . " i WHERE $where");
        if ($itemRst) {
            while ($row = $itemRst->fetch_assoc()) {
                $itemId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($itemId <= 0) {
                    continue;
                }
                $itemMap[$itemId] = array(
                    'title' => isset($row['title']) ? (string) $row['title'] : '',
                    'work_type_id' => isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0,
                    'project_key_id' => isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0,
                    'column_id' => isset($row['column_id']) ? (string) $row['column_id'] : '',
                    'priority' => isset($row['priority']) ? (string) $row['priority'] : 'Medium',
                    'assignee_user_id' => isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : 0,
                );
                $itemIds[] = $itemId;
                $workTypeIds[] = isset($row['work_type_id']) ? (int) $row['work_type_id'] : 0;
                $projectKeyIds[] = isset($row['project_key_id']) ? (int) $row['project_key_id'] : 0;
                if (!empty($row['assignee_user_id'])) {
                    $actorIds[] = (int) $row['assignee_user_id'];
                }
            }
        }

        if (empty($itemIds)) {
            return array(
                'rows' => array(),
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 1,
            );
        }

        $itemIdSql = implode(',', taskUniquePositiveIntIds($itemIds));
        $workTypeMap = taskFetchWorkTypeInfoMap($connect, $workTypeIds, true);
        $projectKeyMap = taskFetchProjectKeyMap($connect, $projectKeyIds, true);

        $rawRows = array();

        $historySql = "SELECT id AS record_id,item_id AS h_item_id,event_type,field_name,from_value,to_value,remark,
                              '' AS comment_html,'' AS comment_text,create_by,create_date,create_time,'history' AS record_type
                       FROM " . TASK_ITEM_HISTORY . "
                       WHERE status='A' AND event_type <> 'comment' AND item_id IN (" . $itemIdSql . ")";
        $historyRst = mysqli_query($connect, $historySql);
        if ($historyRst) {
            while ($row = $historyRst->fetch_assoc()) {
                $rawRows[] = $row;
                $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
            }
        }

        $commentSql = "SELECT id AS record_id,item_id AS h_item_id,'comment' AS event_type,'' AS field_name,'' AS from_value,'' AS to_value,'' AS remark,
                              comment_html,comment_text,create_by,create_date,create_time,'comment' AS record_type
                       FROM " . TASK_ITEM_COMMENT . "
                       WHERE status='A' AND item_id IN (" . $itemIdSql . ")";
        $commentRst = mysqli_query($connect, $commentSql);
        if ($commentRst) {
            while ($row = $commentRst->fetch_assoc()) {
                $rawRows[] = $row;
                $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
            }
        }

        $replySql = "SELECT id AS record_id,item_id AS h_item_id,'reply' AS event_type,'' AS field_name,'' AS from_value,'' AS to_value,'' AS remark,
                            reply_html AS comment_html,reply_text AS comment_text,create_by,create_date,create_time,'reply' AS record_type
                     FROM " . TASK_ITEM_COMMENT_REPLY . "
                     WHERE status='A' AND item_id IN (" . $itemIdSql . ")";
        $replyRst = mysqli_query($connect, $replySql);
        if ($replyRst) {
            while ($row = $replyRst->fetch_assoc()) {
                $rawRows[] = $row;
                $actorIds[] = isset($row['create_by']) ? (int) $row['create_by'] : 0;
            }
        }

        $actorMap = taskFetchUserDisplayMap($connect, $actorIds, false);
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $hasSearch = $search !== '';
        $searchLower = strtolower($search);

        $rows = array();
        foreach ($rawRows as $row) {
            $itemId = isset($row['h_item_id']) ? (int) $row['h_item_id'] : 0;
            if ($itemId <= 0 || !isset($itemMap[$itemId])) {
                continue;
            }

            $itemMeta = $itemMap[$itemId];
            $workTypeId = isset($itemMeta['work_type_id']) ? (int) $itemMeta['work_type_id'] : 0;
            $projectKeyId = isset($itemMeta['project_key_id']) ? (int) $itemMeta['project_key_id'] : 0;
            $projectKey = isset($projectKeyMap[$projectKeyId]) ? (string) $projectKeyMap[$projectKeyId] : '';
            $workItemKey = taskBuildWorkItemKey($projectKey, $itemId);
            $workTypeName = isset($workTypeMap[$workTypeId]['name']) ? (string) $workTypeMap[$workTypeId]['name'] : '';
            $workTypeIcon = isset($workTypeMap[$workTypeId]['svg_icon']) ? (string) $workTypeMap[$workTypeId]['svg_icon'] : '';
            $createById = isset($row['create_by']) ? (int) $row['create_by'] : 0;
            $actorName = isset($actorMap[$createById]) ? (string) $actorMap[$createById] : 'User';

            $eventType = isset($row['event_type']) ? trim((string) $row['event_type']) : '';
            $fieldName = isset($row['field_name']) ? trim((string) $row['field_name']) : '';
            $fromValue = isset($row['from_value']) ? (string) $row['from_value'] : '';
            $toValue = isset($row['to_value']) ? (string) $row['to_value'] : '';
            $remark = isset($row['remark']) ? trim((string) $row['remark']) : '';
            $recordType = isset($row['record_type']) ? (string) $row['record_type'] : 'history';
            $commentText = isset($row['comment_text']) ? (string) $row['comment_text'] : '';

            if ($recordType === 'history' && $remark === '') {
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
            } elseif ($recordType === 'comment') {
                $remark = 'commented';
            } elseif ($recordType === 'reply') {
                $remark = 'replied to a comment';
            }

            if ($hasSearch) {
                $searchPool = array(
                    strtolower((string) $itemMeta['title']),
                    strtolower($workItemKey),
                    strtolower($actorName),
                    strtolower($remark),
                    strtolower($fieldName),
                    strtolower($fromValue),
                    strtolower($toValue),
                    strtolower($commentText),
                );
                $matched = false;
                foreach ($searchPool as $poolText) {
                    if ($poolText !== '' && strpos($poolText, $searchLower) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            }

            $rows[] = array(
                'record_id' => isset($row['record_id']) ? (int) $row['record_id'] : 0,
                'record_type' => $recordType,
                'item_id' => $itemId,
                'event_type' => $eventType,
                'field_name' => $fieldName,
                'from_value' => $fromValue,
                'to_value' => $toValue,
                'remark' => $remark,
                'comment_html' => isset($row['comment_html']) ? (string) $row['comment_html'] : '',
                'comment_text' => $commentText,
                'actor_name' => $actorName,
                'create_by' => isset($row['create_by']) ? (string) $row['create_by'] : '',
                'create_date' => isset($row['create_date']) ? (string) $row['create_date'] : '',
                'create_time' => isset($row['create_time']) ? (string) $row['create_time'] : '',
                'item_title' => isset($itemMeta['title']) ? (string) $itemMeta['title'] : '',
                'work_item_key' => $workItemKey,
                'work_type_name' => $workTypeName,
                'work_type_svg_icon' => $workTypeIcon,
                'item_task_status' => isset($itemMeta['column_id']) ? (string) $itemMeta['column_id'] : '',
                'item_priority' => isset($itemMeta['priority']) ? (string) $itemMeta['priority'] : 'Medium',
                'item_assignee_id' => isset($itemMeta['assignee_user_id']) ? (int) $itemMeta['assignee_user_id'] : 0,
                'item_assignee_name' => (isset($itemMeta['assignee_user_id']) && $itemMeta['assignee_user_id'] > 0 && isset($actorMap[$itemMeta['assignee_user_id']])) ? $actorMap[$itemMeta['assignee_user_id']] : 'Unassigned',
            );
        }

        usort($rows, function ($a, $b) {
            $dateCmp = strcmp((string) $b['create_date'], (string) $a['create_date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            $timeCmp = strcmp((string) $b['create_time'], (string) $a['create_time']);
            if ($timeCmp !== 0) {
                return $timeCmp;
            }
            return ((int) $b['record_id']) <=> ((int) $a['record_id']);
        });

        $totalRecords = count($rows);
        $pagedRows = array_slice($rows, $offset, $perPage);

        return array(
            'rows' => $pagedRows,
            'total' => $totalRecords,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalRecords > 0 ? (int) ceil($totalRecords / $perPage) : 1,
        );
    }
}
