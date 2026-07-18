<?php
$taskParentPin = 139;
$currentPagePin = $taskParentPin;
$pageTitlePin = 136;
$pageTitle = 'Board';
$taskParentTitle = 'Project Task';
$taskPermissionPin = $taskParentPin;

if (!function_exists('taskBoardAuditLog')) {
    function taskBoardAuditLog($connect, $pageTitle, $pageAction, $viewActMsg, $cdate, $ctime)
    {
        if (!function_exists('audit_log')) {
            return;
        }

        $log = [
            'log_act' => $pageAction,
            'cdate'   => $cdate,
            'ctime'   => $ctime,
            'uid'     => USER_ID,
            'cby'     => USER_ID,
            'act_msg' => $viewActMsg,
            'page'    => $pageTitle,
            'connect' => $connect,
        ];

        audit_log($log);
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['task_action'])
    && !is_array($_POST['task_action'])
    && trim((string) $_POST['task_action']) !== ''
) {
    include_once '../include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once './common_task.php';
    $pageTitle = taskGetPinGroupTitleById($connect, $pageTitlePin, $pageTitle);
    $taskParentTitle = taskGetPinGroupTitleById($connect, $taskParentPin, $taskParentTitle);

    $pinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
    if (!taskIsActionAllowed('view', $pinAccess)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to access task board.'));
    }

    // Ensure session token exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Validate CSRF token for all state-changing actions
    $submittedToken = post('csrf_token');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
    }

    $currentUserId = USER_ID;
    $currentProjectId = taskResolveCurrentProjectId($connect, 0);
    if (!taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $taskPermissionPin)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to this project board.'));
    }
    if ($currentProjectId > 0) {
        taskEnsureDefaultWorkTypes($connect, $currentProjectId, $currentUserId, $cdate, $ctime);
    }
    $taskAction = trim((string) post('task_action'));
    $safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safePageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');

    if ($taskAction === 'get_board_items') {
        $columnId = max(0, (int) post('column_id'));
        $offset = max(0, (int) post('offset'));
        $limit = min(100, max(10, (int) post('limit')));
        if ($columnId <= 0) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Invalid board status.'));
        }

        $itemsByColumn = taskGetItemsGroupedByColumn($connect, $currentProjectId, false, $limit, $offset, $columnId);
        $items = isset($itemsByColumn[$columnId]) && is_array($itemsByColumn[$columnId])
            ? array_values($itemsByColumn[$columnId])
            : array();
        $itemCounts = taskGetItemCountsByColumn($connect, $currentProjectId);
        $total = isset($itemCounts[$columnId]) ? (int) $itemCounts[$columnId] : 0;
        $nextOffset = $offset + count($items);

        taskJsonResponse(array(
            'ok' => 1,
            'column_id' => $columnId,
            'items' => $items,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'total' => $total,
            'has_more' => $nextOffset < $total ? 1 : 0,
        ));
    }

    $taskAttachmentVideoExts = array('mp4', 'mov', 'webm', 'avi', 'mkv');
    $taskAttachmentAllowedExts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'mp4', 'mov', 'webm', 'avi', 'mkv');
    $taskAttachmentAllowedMimes = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
        'video/mp4',
        'video/quicktime',
        'video/webm',
        'video/x-msvideo',
        'video/x-matroska',
        'application/octet-stream'
    );

    $validateTaskAttachmentUpload = function ($file, $normalMaxSizeBytes, $normalMaxSizeLabel) use ($taskAttachmentAllowedExts, $taskAttachmentAllowedMimes, $taskAttachmentVideoExts) {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            taskJsonResponse(array('ok' => 0, 'message' => 'File upload failed or no file selected.'));
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $taskAttachmentAllowedExts, true)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Invalid file extension. Allowed types: ' . implode(', ', $taskAttachmentAllowedExts) . '.'));
        }

        $isVideoFile = in_array($ext, $taskAttachmentVideoExts, true);
        $maxSizeBytes = $isVideoFile ? 1073741824 : (int) $normalMaxSizeBytes;
        if ((int) $file['size'] > $maxSizeBytes) {
            taskJsonResponse(array(
                'ok' => 0,
                'message' => $isVideoFile ? 'Video files must not exceed 1GB.' : 'File exceeds the ' . $normalMaxSizeLabel . ' size limit.'
            ));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        // Some servers report a valid OOXML workbook as application/octet-stream.
        // Validate the package structure before accepting that generic MIME type.
        $isValidXlsxPackage = false;
        if ($ext === 'xlsx' && (string) $mime === 'application/octet-stream' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) === true) {
                $hasContentTypes = false;
                $hasWorkbook = false;
                for ($zipIndex = 0; $zipIndex < $zip->numFiles; $zipIndex++) {
                    $zipEntryName = strtolower((string) $zip->getNameIndex($zipIndex));
                    if ($zipEntryName === '[content_types].xml') {
                        $hasContentTypes = true;
                    } elseif ($zipEntryName === 'xl/workbook.xml') {
                        $hasWorkbook = true;
                    }
                }
                $isValidXlsxPackage = $hasContentTypes && $hasWorkbook;
                $zip->close();
            }
        }

        if (!in_array((string) $mime, $taskAttachmentAllowedMimes, true) && !$isValidXlsxPackage) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Invalid file format detected by server.'));
        }

        if ((string) $mime === 'application/octet-stream' && !$isVideoFile && !$isValidXlsxPackage) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Invalid file format detected by server.'));
        }

        return array(
            'extension' => $ext,
            'mime' => (string) $mime,
            'is_video' => $isVideoFile,
        );
    };

    if ($taskAction === 'create_project') {
        if (!taskCanCreateProject($connect)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create project task.'));
        }

        $result = taskCreateProject(
            $connect,
            post('project_name'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    $projectOwnerManageAccess = taskCanEditProjectSettings($connect, $currentProjectId);
    $projectWorkItemCanAdd = taskUserCanWorkItemAction($connect, $currentProjectId, 'add');
    $projectWorkItemCanEdit = taskUserCanWorkItemAction($connect, $currentProjectId, 'edit');
    $projectWorkItemCanDelete = taskUserCanWorkItemAction($connect, $currentProjectId, 'delete');
    $projectAllowedWorkTypeIds = taskUserAllowedWorkTypeIds($connect, $currentProjectId);
    $projectAllowedStatusIds = taskUserAllowedStatusIds($connect, $currentProjectId);
    $projectIsOwner = taskIsProjectOwner($connect, $currentProjectId, $currentUserId);
    $projectHasFullAccess = taskUserHasFullProjectTaskAccess($connect, $currentProjectId, $currentUserId);
    $projectColumnPermissions = taskGetProjectColumnAccessMap($connect, $currentProjectId, $currentUserId);

    $loadItemDetailForPermission = function ($itemId) use ($connect) {
        $detailResult = taskGetItemDetail($connect, $itemId);
        if (empty($detailResult['ok']) || empty($detailResult['detail']) || !is_array($detailResult['detail'])) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Work item not found.'));
        }

        return $detailResult['detail'];
    };

    $requireColumnTransition = function ($columnKey, $oldValue, $newValue) use ($connect, $currentProjectId, $currentUserId) {
        $permissionResult = taskValidateProjectColumnFieldTransition(
            $connect,
            $currentProjectId,
            $columnKey,
            $oldValue,
            $newValue,
            $currentUserId
        );

        if (empty($permissionResult['ok'])) {
            taskJsonResponse(array(
                'ok' => 0,
                'message' => isset($permissionResult['message']) ? (string) $permissionResult['message'] : 'You do not have permission to update this field.',
            ));
        }
    };

    $ownerOnlyActions = array(
        'create_column',
        'create_status',
        'rename_status',
        'move_status',
        'delete_status',
        'create_work_type',
        'update_work_type',
        'save_project_key',
        'delete_status_label',
        'create_status_label',
        'create_label',
        'delete_label',
    );
    if (in_array($taskAction, $ownerOnlyActions, true) && !$projectOwnerManageAccess) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Only the project owner can manage this project setting.'));
    }

    $editItemActions = array(
        'set_item_assignee',
        'set_item_work_type',
        'update_item_core',
        'create_item_comment',
        'create_item_comment_reply',
        'update_item_comment',
        'update_item_comment_reply',
        'delete_item_comment',
        'delete_item_comment_reply',
        'upload_item_comment_attachment',
        'upload_item_description_attachment',
        'upload_item_reply_attachment',
        'update_item_detail',
        'save_item_worklog',
        'update_item_worklog',
        'delete_item_worklog',
        'set_item_parent',
        'search_child_work_items',
        'create_child_work_item',
        'link_existing_child_work_item',
        'create_item_web_link',
        'delete_item_web_link',
        'search_link_work_items',
        'create_item_link',
        'delete_item_link',
        'upload_item_attachment',
        'delete_item_attachment',
        'delete_all_item_attachments',
        'set_item_labels',
    );
    if (in_array($taskAction, $editItemActions, true) && !$projectWorkItemCanEdit) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit work items in this project.'));
    }

    if ($taskAction === 'create_item' && !$projectWorkItemCanAdd) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create work items in this project.'));
    }

    if ($taskAction === 'create_child_work_item' && !$projectWorkItemCanAdd) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create work items in this project.'));
    }

    if ($taskAction === 'delete_item' && !$projectWorkItemCanDelete) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete work items in this project.'));
    }

    if ($taskAction === 'set_item_work_type' && !$projectHasFullAccess) {
        $targetWorkTypeId = (int) post('work_type_id');
        if ($targetWorkTypeId <= 0 || empty($projectAllowedWorkTypeIds) || !in_array($targetWorkTypeId, $projectAllowedWorkTypeIds, true)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to this task type in the current project.'));
        }
    }

    if (($taskAction === 'change_item_status' || $taskAction === 'move_item_drop') && !$projectHasFullAccess) {
        $targetColumnId = (int) post('target_column_id');
        if ($targetColumnId <= 0 || empty($projectAllowedStatusIds) || !in_array($targetColumnId, $projectAllowedStatusIds, true)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to move work items into that status.'));
        }
    }

    if ($taskAction === 'create_item' && !$projectHasFullAccess) {
        $targetColumnId = (int) post('column_id');
        $targetWorkTypeId = (int) post('work_type_id');
        if ($targetColumnId <= 0 || empty($projectAllowedStatusIds) || !in_array($targetColumnId, $projectAllowedStatusIds, true)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to create work items in that status.'));
        }
        if ($targetWorkTypeId <= 0 || empty($projectAllowedWorkTypeIds) || !in_array($targetWorkTypeId, $projectAllowedWorkTypeIds, true)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to use that task type in this project.'));
        }
    }

    if ($taskAction === 'create_child_work_item' && !$projectHasFullAccess) {
        $targetWorkTypeId = (int) post('work_type_id');
        if ($targetWorkTypeId > 0 && (empty($projectAllowedWorkTypeIds) || !in_array($targetWorkTypeId, $projectAllowedWorkTypeIds, true))) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to use that task type in this project.'));
        }
    }

    if ($taskAction === 'create_column' || $taskAction === 'create_status') {
        if (!taskIsActionAllowed('add', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create statuses.'));
        }

        $result = taskCreateColumn($connect, $currentProjectId, post('column_name'), $currentUserId, $cdate, $ctime);
        if (!empty($result['ok'])) {
            $statusName = isset($result['column']['name']) ? htmlspecialchars((string) $result['column']['name'], ENT_QUOTES, 'UTF-8') : '';
            $viewActMsg = $safeUserName . " added new status <b>" . $statusName . "</b> on <b>" . $safePageTitle . "</b>.";
            taskBoardAuditLog($connect, $pageTitle, 'Add', $viewActMsg, $cdate, $ctime);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'rename_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to rename status.'));
        }

        $result = taskRenameColumn(
            $connect,
            $currentProjectId,
            (int) post('column_id'),
            post('column_name'),
            $currentUserId,
            $cdate,
            $ctime
        );
        if (!empty($result['ok'])) {
            $statusName = isset($result['column_name']) ? htmlspecialchars((string) $result['column_name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars((string) post('column_name'), ENT_QUOTES, 'UTF-8');
            $viewActMsg = $safeUserName . " edited status <b>" . $statusName . "</b> on <b>" . $safePageTitle . "</b>.";
            taskBoardAuditLog($connect, $pageTitle, 'Edit', $viewActMsg, $cdate, $ctime);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'move_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to move status.'));
        }

        $result = taskMoveColumn(
            $connect,
            $currentProjectId,
            (int) post('column_id'),
            post('direction')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_status') {
        if (!taskIsActionAllowed('delete', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete status.'));
        }

        $result = taskDeleteColumn(
            $connect,
            $currentProjectId,
            (int) post('column_id'),
            $currentUserId,
            $cdate,
            $ctime
        );
        if (!empty($result['ok'])) {
            $statusName = isset($result['column_name']) ? htmlspecialchars((string) $result['column_name'], ENT_QUOTES, 'UTF-8') : '';
            $viewActMsg = $safeUserName . " deleted status <b>" . $statusName . "</b> on <b>" . $safePageTitle . "</b>.";
            taskBoardAuditLog($connect, $pageTitle, 'Delete', $viewActMsg, $cdate, $ctime);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'create_work_type') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add work type.'));
        }

        $result = taskCreateWorkType(
            $connect,
            $currentProjectId,
            post('work_type_name'),
            post('work_type_remark'),
            post('work_type_svg_icon'),
            $currentUserId,
            $cdate,
            $ctime
        );
        if (!empty($result['ok'])) {
            $result['workTypes'] = taskGetWorkTypes($connect, $currentProjectId);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_work_type') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit work type.'));
        }

        $result = taskUpdateWorkType(
            $connect,
            $currentProjectId,
            (int) post('work_type_id'),
            post('work_type_name'),
            post('work_type_remark'),
            post('work_type_svg_icon'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['workTypes'] = taskGetWorkTypes($connect, $currentProjectId);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'create_item') {
        if (!taskIsActionAllowed('add', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create work item.'));
        }

        $result = taskCreateItem(
            $connect,
            $currentProjectId,
            (int) post('column_id'),
            post('title'),
            (int) post('work_type_id'),
            (int) post('assignee_user_id'),
            post('due_date'),
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'move_item') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to move work item.'));
        }

        $result = taskMoveItem(
            $connect,
            (int) post('item_id'),
            post('move_to')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'change_item_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to change status.'));
        }

        $result = taskChangeItemStatus(
            $connect,
            (int) post('item_id'),
            (int) post('target_column_id'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'move_item_drop') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to move work item.'));
        }

        $result = taskMoveItemByDrop(
            $connect,
            (int) post('item_id'),
            (int) post('target_column_id'),
            (int) post('target_index'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_assignee') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to set assignee.'));
        }

        $itemId = (int) post('item_id');
        $detail = $loadItemDetailForPermission($itemId);
        $assigneeUserId = (int) post('assignee_user_id');
        $requireColumnTransition('assignee_name', isset($detail['assignee_user_id']) ? (int) $detail['assignee_user_id'] : 0, $assigneeUserId);

        $result = taskSetItemAssignee(
            $connect,
            $itemId,
            $assigneeUserId,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_work_type') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to change work type.'));
        }

        $result = taskSetItemWorkType(
            $connect,
            (int) post('item_id'),
            (int) post('work_type_id'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'save_project_key') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to change project key.'));
        }

        $result = taskSaveProjectKeySetting(
            $connect,
            $currentProjectId,
            post('project_key'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_core') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to update work item.'));
        }

        $result = taskUpdateItemCore(
            $connect,
            (int) post('item_id'),
            post('title'),
            filter_has_var(INPUT_POST, 'description') ? post('description') : null,
            $currentUserId,
            $cdate,
            $ctime,
            post('description_attachment_paths')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'get_item_detail') {
        $result = taskGetItemDetail(
            $connect,
            (int) post('item_id')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'search_child_work_items') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to search child work items.'));
        }

        $parentItemId = (int) post('parent_item_id');
        taskJsonResponse(array(
            'ok' => 1,
            'items' => taskSearchChildWorkItems(
                $connect,
                $currentProjectId,
                $parentItemId,
                post('keyword')
            ),
        ));
    }

    if ($taskAction === 'create_child_work_item') {
        if (!taskIsActionAllowed('add', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create work item.'));
        }

        $parentItemId = (int) post('parent_item_id');
        $result = taskCreateChildWorkItem(
            $connect,
            $currentProjectId,
            $parentItemId,
            post('title'),
            (int) post('work_type_id'),
            $currentUserId
        );

        if (!empty($result['ok']) && $parentItemId > 0) {
            $detailResult = taskGetItemDetail($connect, $parentItemId);
            if (!empty($detailResult['ok'])) {
                $result['detail'] = isset($detailResult['detail']) ? $detailResult['detail'] : array();
            }
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'link_existing_child_work_item') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to link child work items.'));
        }

        $parentItemId = (int) post('parent_item_id');
        $childItemId = (int) post('child_item_id');
        if ($childItemId <= 0 && trim((string) post('child_value')) !== '') {
            $resolvedChild = taskResolveWorkItemFromUrlOrKey($connect, $currentProjectId, post('child_value'));
            $childItemId = isset($resolvedChild['id']) ? (int) $resolvedChild['id'] : 0;
        }

        $result = taskLinkExistingChildWorkItem(
            $connect,
            $currentProjectId,
            $parentItemId,
            $childItemId,
            $currentUserId
        );

        if (!empty($result['ok']) && $parentItemId > 0) {
            $detailResult = taskGetItemDetail($connect, $parentItemId);
            if (!empty($detailResult['ok'])) {
                $result['detail'] = isset($detailResult['detail']) ? $detailResult['detail'] : array();
            }
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'get_item_history') {
        $itemId = (int) post('item_id');
        taskJsonResponse(array(
            'ok' => 1,
            'history' => taskGetItemHistory($connect, $itemId, 150),
        ));
    }

    if ($taskAction === 'get_item_comments') {
        $itemId = (int) post('item_id');
        taskJsonResponse(array(
            'ok' => 1,
            'comments' => taskGetItemComments($connect, $itemId, 200),
        ));
    }

    if ($taskAction === 'get_item_worklogs') {
        $itemId = (int) post('item_id');
        if ($itemId <= 0 || taskGetItemProjectId($connect, $itemId) !== $currentProjectId) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Work item not found.'));
        }

        taskJsonResponse(array(
            'ok' => 1,
            'worklogs' => taskGetItemWorklogs($connect, $itemId, 200),
        ));
    }

    if ($taskAction === 'create_item_comment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add comments.'));
        }

        $result = taskCreateItemComment(
            $connect,
            (int) post('item_id'),
            post('comment_html'),
            $currentUserId,
            $cdate,
            $ctime,
            post('comment_attachment_paths')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'create_item_comment_reply') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add replies.'));
        }

        $result = taskCreateItemCommentReply(
            $connect,
            (int) post('item_id'),
            (int) post('comment_id'),
            post('reply_html'),
            $currentUserId,
            $cdate,
            $ctime,
            post('reply_attachment_paths')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_comment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit comments.'));
        }

        $result = taskUpdateItemComment(
            $connect,
            (int) post('item_id'),
            (int) post('comment_id'),
            post('comment_html'),
            $currentUserId,
            $cdate,
            $ctime,
            post('comment_attachment_paths')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_comment_reply') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit replies.'));
        }

        $result = taskUpdateItemCommentReply(
            $connect,
            (int) post('item_id'),
            (int) post('reply_id'),
            post('reply_html'),
            $currentUserId,
            $cdate,
            $ctime,
            post('reply_attachment_paths')
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_comment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete comments.'));
        }

        $result = taskDeleteItemComment(
            $connect,
            (int) post('item_id'),
            (int) post('comment_id'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_comment_reply') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete replies.'));
        }

        $result = taskDeleteItemCommentReply(
            $connect,
            (int) post('item_id'),
            (int) post('reply_id'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'upload_item_comment_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to upload comment attachment.'));
        }

        $itemId = (int) post('item_id');
        $file = isset($_FILES['attachment']) ? $_FILES['attachment'] : null;

        $validateTaskAttachmentUpload($file, 50 * 1024 * 1024, '50MB');

        $result = taskUploadItemCommentAttachment(
            $connect,
            $itemId,
            $file,
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'upload_item_description_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to upload description attachment.'));
        }

        $itemId = (int) post('item_id');
        $file = isset($_FILES['attachment']) ? $_FILES['attachment'] : null;

        $validateTaskAttachmentUpload($file, 50 * 1024 * 1024, '50MB');

        $result = taskUploadItemDescriptionAttachment(
            $connect,
            $itemId,
            $file,
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'upload_item_reply_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to upload reply attachment.'));
        }

        $itemId = (int) post('item_id');
        $file = isset($_FILES['attachment']) ? $_FILES['attachment'] : null;

        $validateTaskAttachmentUpload($file, 50 * 1024 * 1024, '50MB');

        $result = taskUploadItemReplyAttachment(
            $connect,
            $itemId,
            $file,
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_detail') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to update work item details.'));
        }

        $itemId = (int) post('item_id');
        $detail = $loadItemDetailForPermission($itemId);
        $assigneeUserId = (int) post('assignee_user_id');
        $reporterUserId = (int) post('reporter_user_id');
        $priority = filter_has_var(INPUT_POST, 'priority') ? post('priority') : 'Medium';
        $originalEstimateValue = (int) post('original_estimate_value');
        $originalEstimateUnit = filter_has_var(INPUT_POST, 'original_estimate_unit') ? post('original_estimate_unit') : 'minutes';
        $taskStatusLabelIds = post('task_status_label_ids');
        $startDate = post('start_date');
        $dueDate = post('due_date');
        $amendementDate = post('amendement_date');
        $amendementTimeMinutes = (int) post('amendement_time_minutes');
        $secondAmendementDate = post('second_amendement_date');
        $secondAmendementTimeMinutes = (int) post('second_amendement_time_minutes');

        $requireColumnTransition('assignee_name', isset($detail['assignee_user_id']) ? (int) $detail['assignee_user_id'] : 0, $assigneeUserId);
        $requireColumnTransition('reporter_name', isset($detail['reporter_user_id']) ? (int) $detail['reporter_user_id'] : 0, $reporterUserId);
        $requireColumnTransition(
            'priority',
            isset($detail['priority']) ? (string) $detail['priority'] : '',
            taskNormalizePriority($priority)
        );
        $requireColumnTransition(
            'original_estimate',
            array(
                'value' => isset($detail['original_estimate_value']) ? (int) $detail['original_estimate_value'] : 0,
                'unit' => isset($detail['original_estimate_unit']) ? (string) $detail['original_estimate_unit'] : 'minutes',
            ),
            array(
                'value' => $originalEstimateValue,
                'unit' => $originalEstimateUnit,
            )
        );
        $requireColumnTransition(
            'task_status',
            isset($detail['task_status_label_ids']) ? (array) $detail['task_status_label_ids'] : array(),
            $taskStatusLabelIds
        );
        $requireColumnTransition('start_date', isset($detail['start_date']) ? (string) $detail['start_date'] : '', $startDate);
        $requireColumnTransition('due_date', isset($detail['due_date']) ? (string) $detail['due_date'] : '', $dueDate);
        $requireColumnTransition('amendement_date', isset($detail['amendement_date']) ? (string) $detail['amendement_date'] : '', $amendementDate);
        $requireColumnTransition(
            'amendement_time_minutes',
            isset($detail['amendement_time_minutes']) ? (int) $detail['amendement_time_minutes'] : 0,
            $amendementTimeMinutes
        );
        $requireColumnTransition(
            'second_amendement_date',
            isset($detail['second_amendement_date']) ? (string) $detail['second_amendement_date'] : '',
            $secondAmendementDate
        );
        $requireColumnTransition(
            'second_amendement_time_minutes',
            isset($detail['second_amendement_time_minutes']) ? (int) $detail['second_amendement_time_minutes'] : 0,
            $secondAmendementTimeMinutes
        );

        $result = taskUpdateItemDetail(
            $connect,
            $itemId,
            $assigneeUserId,
            $reporterUserId,
            $priority,
            $originalEstimateValue,
            $originalEstimateUnit,
            $taskStatusLabelIds,
            $startDate,
            $dueDate,
            $amendementDate,
            $amendementTimeMinutes,
            $secondAmendementDate,
            $secondAmendementTimeMinutes,
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'save_item_worklog') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to save worklog.'));
        }

        $itemId = (int) post('item_id');
        $detail = $loadItemDetailForPermission($itemId);
        $durationSeconds = (int) post('duration_seconds');
        $oldSeconds = isset($detail['own_time_tracking_seconds']) ? (int) $detail['own_time_tracking_seconds'] : 0;
        $requireColumnTransition('time_tracking', $oldSeconds, max(0, $oldSeconds + $durationSeconds));

        $result = taskSaveItemWorklog(
            $connect,
            $itemId,
            $durationSeconds,
            $currentUserId,
            $cdate,
            $ctime,
            array(
                'started_date' => post('started_date'),
                'started_time' => post('started_time'),
                'work_description_html' => post('work_description_html'),
                'remaining_seconds' => post('remaining_seconds') !== '' ? (int) post('remaining_seconds') : null,
            )
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_worklog') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit worklog.'));
        }

        $itemId = (int) post('item_id');
        $worklogId = (int) post('worklog_id');
        $detail = $loadItemDetailForPermission($itemId);
        $durationSeconds = (int) post('duration_seconds');
        $oldSeconds = isset($detail['own_time_tracking_seconds']) ? (int) $detail['own_time_tracking_seconds'] : 0;
        $oldWorklogSeconds = 0;
        if (defined('TASK_ITEM_WORKLOG')) {
            $worklogRst = mysqli_query(
                $connect,
                "SELECT duration_seconds FROM " . TASK_ITEM_WORKLOG . " WHERE id='" . $worklogId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1"
            );
            if ($worklogRst && $worklogRst->num_rows > 0) {
                $worklogRow = $worklogRst->fetch_assoc();
                $oldWorklogSeconds = isset($worklogRow['duration_seconds']) ? max(0, (int) $worklogRow['duration_seconds']) : 0;
            }
        }
        $requireColumnTransition('time_tracking', $oldSeconds, max(0, $oldSeconds - $oldWorklogSeconds + $durationSeconds));
        $result = taskUpdateItemWorklog(
            $connect,
            $itemId,
            $worklogId,
            $durationSeconds,
            $currentUserId,
            $cdate,
            $ctime,
            array(
                'started_date' => post('started_date'),
                'started_time' => post('started_time'),
                'work_description_html' => post('work_description_html'),
                'remaining_seconds' => post('remaining_seconds') !== '' ? (int) post('remaining_seconds') : null,
            )
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_worklog') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete worklog.'));
        }

        $itemId = (int) post('item_id');
        $worklogId = (int) post('worklog_id');
        $detail = $loadItemDetailForPermission($itemId);
        $oldSeconds = isset($detail['own_time_tracking_seconds']) ? (int) $detail['own_time_tracking_seconds'] : 0;
        $deletedWorklogSeconds = 0;
        if (defined('TASK_ITEM_WORKLOG')) {
            $worklogRst = mysqli_query(
                $connect,
                "SELECT duration_seconds FROM " . TASK_ITEM_WORKLOG . " WHERE id='" . $worklogId . "' AND item_id='" . $itemId . "' AND status='A' LIMIT 1"
            );
            if ($worklogRst && $worklogRst->num_rows > 0) {
                $worklogRow = $worklogRst->fetch_assoc();
                $deletedWorklogSeconds = isset($worklogRow['duration_seconds']) ? max(0, (int) $worklogRow['duration_seconds']) : 0;
            }
        }
        $requireColumnTransition('time_tracking', $oldSeconds, max(0, $oldSeconds - $deletedWorklogSeconds));
        $result = taskDeleteItemWorklog(
            $connect,
            $itemId,
            $worklogId,
            post('adjust_remaining') !== '' ? (int) post('adjust_remaining') : 1,
            (int) post('remaining_seconds'),
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_parent') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to link parent.'));
        }

        $itemId = (int) post('item_id');
        $parentItemId = (int) post('parent_item_id');
        $detail = $loadItemDetailForPermission($itemId);
        $requireColumnTransition('parent_display', isset($detail['parent_item_id']) ? (int) $detail['parent_item_id'] : 0, $parentItemId);

        try {
            $result = taskSetItemParentRelation(
                $connect,
                $itemId,
                $parentItemId,
                $currentUserId,
                $cdate,
                $ctime
            );
            taskJsonResponse($result);
        } catch (Throwable $e) {
            taskJsonResponse(array(
                'ok' => 0,
                'message' => 'Failed updating parent relation.',
            ));
        }
    }

    if ($taskAction === 'create_item_web_link') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add web links.'));
        }

        $itemId = (int) post('item_id');
        $result = taskCreateItemUrl(
            $connect,
            $itemId,
            post('url'),
            post('link_text'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['webLinks'] = taskGetItemUrls($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'search_link_work_items') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to search linked work items.'));
        }

        $itemId = (int) post('item_id');
        taskJsonResponse(array(
            'ok' => 1,
            'items' => taskSearchLinkWorkItems(
                $connect,
                $currentProjectId,
                $itemId,
                post('keyword'),
                post('relation_type')
            ),
        ));
    }

    if ($taskAction === 'create_item_link') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add linked work items.'));
        }

        $sourceItemId = (int) post('item_id');
        $targetItemId = (int) post('target_item_id');
        if ($targetItemId <= 0 && trim((string) post('target_value')) !== '') {
            $resolvedTarget = taskResolveWorkItemFromUrlOrKey($connect, $currentProjectId, post('target_value'));
            $targetItemId = isset($resolvedTarget['id']) ? (int) $resolvedTarget['id'] : 0;
        }

        $result = taskCreateItemLink(
            $connect,
            $currentProjectId,
            $sourceItemId,
            $targetItemId,
            post('relation_type'),
            $currentUserId
        );

        if (!empty($result['ok']) && $sourceItemId > 0) {
            $result['itemLinks'] = taskGetItemLinks($connect, $currentProjectId, $sourceItemId);
            $detailResult = taskGetItemDetail($connect, $sourceItemId);
            if (!empty($detailResult['ok'])) {
                $result['detail'] = isset($detailResult['detail']) ? $detailResult['detail'] : array();
            }
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_link') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to remove linked work items.'));
        }

        $currentItemId = (int) post('item_id');
        $result = taskDeleteItemLink(
            $connect,
            $currentProjectId,
            (int) post('link_id'),
            $currentUserId
        );

        if (!empty($result['ok']) && $currentItemId > 0) {
            $result['itemLinks'] = taskGetItemLinks($connect, $currentProjectId, $currentItemId);
            $detailResult = taskGetItemDetail($connect, $currentItemId);
            if (!empty($detailResult['ok'])) {
                $result['detail'] = isset($detailResult['detail']) ? $detailResult['detail'] : array();
            }
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'get_item_links') {
        $itemId = (int) post('item_id');
        taskJsonResponse(array(
            'ok' => 1,
            'itemLinks' => taskGetItemLinks($connect, $currentProjectId, $itemId),
        ));
    }

    if ($taskAction === 'delete_item_web_link') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete web links.'));
        }

        $result = taskDeleteItemUrl(
            $connect,
            (int) post('url_id'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $itemId = isset($result['item_id']) ? (int) $result['item_id'] : 0;
            $result['webLinks'] = taskGetItemUrls($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_status_label') {
        if (!taskIsActionAllowed('edit', $pinAccess) || !taskIsProjectOwner($connect, $currentProjectId, $currentUserId)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete task status labels.'));
        }

        $result = taskDeleteStatusLabel(
            $connect,
            (int) post('status_label_id'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['statusLabels'] = taskGetStatusLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'create_status_label') {
        if (!taskIsActionAllowed('edit', $pinAccess) || !taskIsProjectOwner($connect, $currentProjectId, $currentUserId)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create task status labels.'));
        }

        $result = taskCreateStatusLabel(
            $connect,
            post('status_label_name'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['statusLabels'] = taskGetStatusLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'get_item_attachments') {
        $itemId = (int) post('item_id');
        taskJsonResponse(array(
            'ok' => 1,
            'attachments' => taskGetItemAttachments($connect, $itemId),
        ));
    }

    if ($taskAction === 'upload_item_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to upload attachment.'));
        }

        $file = isset($_FILES['attachment']) ? $_FILES['attachment'] : null;

        $validateTaskAttachmentUpload($file, 100 * 1024 * 1024, '100MB');

        $result = taskUploadItemAttachment(
            $connect,
            (int) post('item_id'),
            $file,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['attachments'] = taskGetItemAttachments($connect, (int) post('item_id'));
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete attachment.'));
        }

        $result = taskDeleteItemAttachment(
            $connect,
            (int) post('attachment_id'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $itemId = isset($result['item_id']) ? (int) $result['item_id'] : 0;
            $result['attachments'] = taskGetItemAttachments($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_all_item_attachments') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete attachments.'));
        }

        $itemId = (int) post('item_id');
        $result = taskDeleteAllItemAttachments(
            $connect,
            $itemId,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['attachments'] = taskGetItemAttachments($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'create_label') {
        if (!taskIsActionAllowed('edit', $pinAccess) || !taskIsProjectOwner($connect, $currentProjectId, $currentUserId)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create labels.'));
        }

        $result = taskCreateLabel(
            $connect,
            post('label_name'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['labels'] = taskGetLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_label') {
        if (!taskIsActionAllowed('edit', $pinAccess) || !taskIsProjectOwner($connect, $currentProjectId, $currentUserId)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete labels.'));
        }

        $result = taskDeleteLabel(
            $connect,
            (int) post('label_id'),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['labels'] = taskGetLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_labels') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to set labels.'));
        }

        $itemId = (int) post('item_id');
        $detail = $loadItemDetailForPermission($itemId);
        $labelIdsCsv = trim((string) post('label_ids'));
        $labelIds = array();
        if ($labelIdsCsv !== '') {
            foreach (explode(',', $labelIdsCsv) as $labelId) {
                $labelId = trim((string) $labelId);
                if ($labelId !== '' && ctype_digit($labelId)) {
                    $labelIds[] = (int) $labelId;
                }
            }
        }
        $requireColumnTransition('labels', isset($detail['labels']) ? array_column((array) $detail['labels'], 'id') : array(), $labelIds);

        $result = taskAssignItemLabels(
            $connect,
            $itemId,
            $labelIds,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['allLabels'] = taskGetLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item') {
        if (!taskIsActionAllowed('delete', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete work item.'));
        }

        $result = taskDeleteItem(
            $connect,
            (int) post('item_id'),
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    taskJsonResponse(array('ok' => 0, 'message' => 'Invalid task action.'));
}

include_once '../menuHeader.php';
include_once './common_task.php';
include_once './board_item_history.php';
$pageTitle = taskGetPinGroupTitleById($connect, $pageTitlePin, $pageTitle);
$taskParentTitle = taskGetPinGroupTitleById($connect, $taskParentPin, $taskParentTitle);

$pinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    renderNotificationScript('You do not have permission to view task board.', 'error', '../dashboard.php', 1200, true);
    exit;
}

$safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
$currentUserId = USER_ID;
$currentProjectId = taskResolveCurrentProjectId($connect, 0);
$currentProject = $currentProjectId > 0 ? taskGetProjectById($connect, $currentProjectId) : array();
if (!taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $taskPermissionPin)) {
    renderNotificationScript('You do not have access to this project board.', 'error', '../dashboard.php', 1200, true);
    exit;
}
$taskParentTitle = !empty($currentProject) && isset($currentProject['name']) && trim((string) $currentProject['name']) !== ''
    ? (string) $currentProject['name']
    : $taskParentTitle;
$safePageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
$safeProjectName = htmlspecialchars((string) $taskParentTitle, ENT_QUOTES, 'UTF-8');
$viewActMsg = $safeUserName . ' viewed the page ' . $safePageTitle . ' (<b>' . $safeProjectName . '</b>).';
taskBoardAuditLog($connect, $pageTitle, 'View', $viewActMsg, $cdate, $ctime);
if ($currentProjectId > 0) {
    taskEnsureDefaultWorkTypes($connect, $currentProjectId, $currentUserId, $cdate, $ctime);
}

$isProjectOwner = $currentProjectId > 0
    && isset($currentProject['owner_user_id'])
    && (int) $currentProject['owner_user_id'] === (int) $currentUserId;
$hasFullProjectAccess = $isProjectOwner;
$projectAccessRecord = array(
    'work_item_add' => $hasFullProjectAccess ? 1 : 0,
    'work_item_edit' => $hasFullProjectAccess ? 1 : 0,
    'work_item_delete' => $hasFullProjectAccess ? 1 : 0,
    'allowed_work_type_ids' => array(),
    'allowed_status_ids' => array(),
);
if (!$hasFullProjectAccess) {
    $projectAccessRecord = taskGetProjectUserAccessRecord($connect, $currentProjectId, $currentUserId);
}
$allowedWorkTypeIds = isset($projectAccessRecord['allowed_work_type_ids']) && is_array($projectAccessRecord['allowed_work_type_ids'])
    ? $projectAccessRecord['allowed_work_type_ids']
    : array();
$allowedStatusIds = isset($projectAccessRecord['allowed_status_ids']) && is_array($projectAccessRecord['allowed_status_ids'])
    ? $projectAccessRecord['allowed_status_ids']
    : array();
$canAdd = taskIsActionAllowed('add', $pinAccess) && !empty($projectAccessRecord['work_item_add']);
$canEdit = taskIsActionAllowed('edit', $pinAccess) && !empty($projectAccessRecord['work_item_edit']);
$canDelete = taskIsActionAllowed('delete', $pinAccess) && !empty($projectAccessRecord['work_item_delete']);
$columnPermissions = $hasFullProjectAccess
    ? array_reduce(taskGetProjectAccessFieldOptions(), function ($permissions, $field) {
        $fieldKey = isset($field['key']) ? (string) $field['key'] : '';
        if ($fieldKey !== '') {
            $permissions[$fieldKey] = array(
                'column_key' => $fieldKey,
                'add' => 1,
                'edit' => 1,
                'delete' => 1,
            );
        }
        return $permissions;
    }, array())
    : taskGetProjectColumnAccessMap($connect, $currentProjectId, $currentUserId);
$workTypes = taskGetWorkTypes($connect, $currentProjectId);
if (!$hasFullProjectAccess) {
    $workTypes = array_values(array_filter($workTypes, function ($workType) use ($allowedWorkTypeIds) {
        return isset($workType['id']) && in_array((int) $workType['id'], $allowedWorkTypeIds, true);
    }));
}
$workTypeIcons = taskGetSvgIconOptions();
$projectKeySetting = taskGetProjectKeySetting($connect, $currentProjectId);
$assignees = taskGetAssignees($connect);
$labels = taskGetLabels($connect);
$statusLabels = taskGetStatusLabels($connect);
$columns = taskGetColumns($connect, $currentProjectId);
$boardPageSize = 40;
$boardItemCounts = taskGetItemCountsByColumn($connect, $currentProjectId);
$itemsByColumn = array();
foreach ($columns as $boardColumn) {
    $boardColumnId = isset($boardColumn['id']) ? (int) $boardColumn['id'] : 0;
    if ($boardColumnId <= 0) {
        continue;
    }
    $boardItems = taskGetItemsGroupedByColumn($connect, $currentProjectId, false, $boardPageSize, 0, $boardColumnId);
    $itemsByColumn[$boardColumnId] = isset($boardItems[$boardColumnId]) ? $boardItems[$boardColumnId] : array();
}
$projectBoardBackground = isset($currentProject['board_background_color']) ? (string) $currentProject['board_background_color'] : '#f4f7fb';
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
</head>
<body class="task-board-page" style="--task-project-board-bg: <?= htmlspecialchars($projectBoardBackground, ENT_QUOTES, 'UTF-8') ?>;">
<div class="container-fluid d-flex justify-content-center mt-3 task-page-wrap">
    <div class="col-12 col-md-11">
        <div id="taskBoardHeaderZoomArea" class="task-board-header-zoom-shell">
            <div class="task-board-page-header">
                <div class="d-flex flex-column mb-2">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($taskParentTitle, ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                            <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section id="taskModuleLayout" class="task-module-layout task-sidebar-open">
            <aside class="task-module-sidebar" id="taskModuleSidebar">
                <h5 class="mb-2">Project Task</h5>
                <?php taskRenderSidebarMenu($connect, $SITEURL, 'board', $currentProjectId); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div id="taskBoardContentZoomArea" class="task-board-zoom-area task-board-content-zoom-shell">
                <div class="task-main-content" style="background: <?= htmlspecialchars($projectBoardBackground, ENT_QUOTES, 'UTF-8') ?>;">
                    <div class="task-board-toolbar mb-2">
                        <div class="task-board-toolbar-left">
                            <div class="task-board-search-group">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input id="taskBoardSearchInput" class="form-control" type="text" maxlength="150" placeholder="Search board">
                            </div>
                            <div id="taskBoardFilterSelectedAssignees" class="task-board-filter-selected-assignees d-none"></div>
                            <div id="taskBoardFilterDropdown" class="dropdown task-board-filter-wrap">
                                <button id="taskBoardFilterBtn" class="btn task-board-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span class="task-board-filter-btn-label">Filter</span>
                                    <span id="taskBoardFilterCountBadge" class="task-board-filter-count-badge d-none">0</span>
                                </button>
                                <div id="taskBoardFilterMenu" class="dropdown-menu task-board-filter-menu p-0"></div>
                            </div>
                        </div>
                        <div class="task-board-toolbar-actions">
                            <div id="taskBoardGroupDropdown" class="dropdown task-board-group-wrap">
                                <button id="taskBoardGroupBtn" class="btn task-board-group-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="taskBoardGroupLabel">Group: Status</span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end task-board-group-menu p-2">
                                    <button type="button" class="dropdown-item task-board-group-option" data-group-by="assignee">
                                        <span>Assignee</span>
                                        <i class="fa-solid fa-check task-board-group-check d-none"></i>
                                    </button>
                                    <button type="button" class="dropdown-item task-board-group-option" data-group-by="priority">
                                        <span>Priority</span>
                                        <i class="fa-solid fa-check task-board-group-check d-none"></i>
                                    </button>
                                    <button type="button" class="dropdown-item task-board-group-option" data-group-by="status">
                                        <span>Status</span>
                                        <i class="fa-solid fa-check task-board-group-check d-none"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="dropdown task-board-settings-wrap">
                            <button id="taskBoardSettingsBtn" class="btn task-board-settings-btn" type="button" data-bs-auto-close="outside" aria-expanded="false" title="Board settings">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            <div id="taskBoardSettingsPanel" class="dropdown-menu dropdown-menu-end task-board-settings-panel p-3">
                                <h6 class="task-board-settings-section-title mb-2">View</h6>
                                <div class="task-board-zoom-control">
                                    <button id="taskBoardZoomOutBtn" type="button" class="btn task-board-zoom-step-btn" title="Zoom out" aria-label="Zoom out board">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <div class="task-board-zoom-main">
                                        <div class="task-board-zoom-head">
                                            <span class="task-board-zoom-label">Board size</span>
                                            <button id="taskBoardZoomResetBtn" type="button" class="btn task-board-zoom-reset-btn" title="Reset to default 90%">Default 90%</button>
                                        </div>
                                        <input
                                            id="taskBoardZoomRange"
                                            class="form-range task-board-zoom-range"
                                            type="range"
                                            min="50"
                                            max="120"
                                            step="1"
                                            value="90"
                                            aria-label="Board zoom percentage"
                                        >
                                    </div>
                                    <span id="taskBoardZoomValue" class="task-board-zoom-value">90%</span>
                                    <button id="taskBoardZoomInBtn" type="button" class="btn task-board-zoom-step-btn" title="Zoom in" aria-label="Zoom in board">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                <div class="task-board-settings-divider"></div>
                                <h6 class="task-board-settings-section-title mb-2">Fields</h6>
                                <div class="task-board-settings-fields">
                                    <?php
                                        $settingsFields = array(
                                            'work_item_key' => 'Work item key',
                                            'work_type' => 'Work type',
                                            'labels' => 'Labels',
                                            'assignee' => 'Assignee',
                                            'priority' => 'Priority',
                                            'due_date' => 'Due date',
                                            'created' => 'Created',
                                            'updated' => 'Updated',
                                            'amendement_date' => 'Amendement date',
                                            'amendement_time' => 'Amendement time',
                                            'second_amendement_date' => 'Second amen-date',
                                            'second_amendement_time' => 'Second amen-time',
                                            'start_date' => 'Start date',
                                            'original_estimate' => 'Original estimate',
                                            'parent' => 'Parent',
                                        );
                                    ?>
                                    <?php foreach ($settingsFields as $fieldKey => $fieldLabel): ?>
                                        <div class="task-board-settings-field-row">
                                            <span><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <label class="task-field-toggle mb-0" title="Toggle <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?>">
                                                <input class="task-board-view-field-checkbox" type="checkbox" data-field-key="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>" checked>
                                                <span class="task-field-toggle-slider"></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        </div>
                    </div>

                    <div id="taskBoardApp" class="task-board-scroll">
                        <div id="taskBoardGrid" class="task-board-grid">
                            <?php foreach ($columns as $column): ?>
                                <?php
                                    $columnId = (int) $column['id'];
                                    $columnItems = isset($itemsByColumn[$columnId]) ? $itemsByColumn[$columnId] : array();
                                    $canCreateInColumn = $canAdd && ($hasFullProjectAccess || in_array($columnId, $allowedStatusIds, true));
                                    $columnTotalItemCount = isset($boardItemCounts[$columnId]) ? (int) $boardItemCounts[$columnId] : count($columnItems);
                                    taskRenderBoardColumn($column, $columnItems, $workTypes, $assignees, $canCreateInColumn, $canEdit, $canDelete, $isProjectOwner, $columnTotalItemCount, $boardPageSize);
                                ?>
                            <?php endforeach; ?>
                        </div>

                        <div id="taskBoardEmpty" class="task-empty-board-note mt-3 <?= !empty($columns) ? 'd-none' : '' ?>">
                            Board is empty. Click the + button to create your first status.
                        </div>

                        <div id="taskBoardNoResult" class="task-board-no-result d-none">
                            <div class="task-board-no-result-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                            <h4>No search results</h4>
                            <p>Try a different word, phrase or filter.</p>
                            <button id="taskBoardNoResultClearBtn" type="button" class="btn task-board-no-result-clear-btn">Clear</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="taskCreateStatusMobileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="taskStatusNameMobile">Status name</label>
                <input id="taskStatusNameMobile" class="form-control" type="text" maxlength="150" placeholder="Status name" <?= $canAdd ? '' : 'disabled' ?>>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="taskCreateStatusSubmitMobile" class="btn btn-primary" <?= $canAdd ? '' : 'disabled' ?>>Add</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="taskWorkTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered task-work-type-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskWorkTypeModalTitle">Add work type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="taskWorkTypeNameInput">Name <span class="text-danger">*</span></label>
                    <input id="taskWorkTypeNameInput" class="form-control" type="text" maxlength="80" placeholder="Work type name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="taskWorkTypeDescriptionInput">Description</label>
                    <textarea id="taskWorkTypeDescriptionInput" class="form-control" rows="3" maxlength="255" placeholder="Let people know when to use this work type."></textarea>
                    <small class="text-muted">Let people know when to use this work type.</small>
                </div>
                <div>
                    <label class="form-label">Icon</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="taskWorkTypeChangeIcon" checked>
                        <label class="form-check-label" for="taskWorkTypeChangeIcon">Change icon</label>
                    </div>
                    <div class="dropdown task-work-type-icon-dropdown-wrap">
                        <button id="taskWorkTypeIconDropdownBtn" class="btn btn-outline-primary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Change icon</button>
                        <div id="taskWorkTypeIconPicker" class="dropdown-menu task-work-type-icon-picker-dropdown"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="taskWorkTypeSaveBtn" class="btn btn-primary" disabled>Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="taskItemActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable task-item-action-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 id="taskItemActionModalTitle" class="modal-title">Task options</h5>
                    <div id="taskItemActionModalMeta" class="task-item-action-modal-meta d-none"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body task-item-action-modal-body">
                <div id="taskItemActionModalSections" class="task-item-action-modal-sections"></div>
            </div>
        </div>
    </div>
</div>

<?php include_once './board_item_detail_modal.php'; ?>
<?php taskRenderCreateProjectModal(); ?>

<div id="taskBoardToastHost" class="task-board-toast-host" aria-live="polite" aria-atomic="true"></div>

<script>
<?php
    // Ensure token exists for initial page load rendering
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
?>
window.taskBoardConfig = {
    ajaxUrl: <?= json_encode('board.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    enableTaskItemHashUrl: true,
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProjectId: <?= (int) $currentProjectId ?>,
    canAdd: <?= $canAdd ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    canDelete: <?= $canDelete ? 'true' : 'false' ?>,
    isProjectOwner: <?= $isProjectOwner ? 'true' : 'false' ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProject: <?= json_encode($currentProject, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    allowedWorkTypeIds: <?= json_encode(array_values($allowedWorkTypeIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    allowedStatusIds: <?= json_encode(array_values($allowedStatusIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columnPermissions: <?= json_encode($columnPermissions, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypeIcons: <?= json_encode($workTypeIcons, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    linkRelationTypes: <?= json_encode(taskGetLinkRelationTypes(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    boardItemCounts: <?= json_encode($boardItemCounts, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    boardPageSize: <?= (int) $boardPageSize ?>
};
</script>
<script src="../js/task_board_core.js"></script>
<script src="../js/task_board_ui.js"></script>
<script src="../js/task_board.js"></script>
<script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
<script src="../js/text_editor.js"></script>
</body>
</html>
