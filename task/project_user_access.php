<?php
ob_start();

$currentPagePin = 141;
$pageTitle = 'Project User Access';
$taskParentTitle = 'Task Management';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once './common_task.php';

if (!function_exists('taskProjectUserAccessAuditValue')) {
    function taskProjectUserAccessAuditValue($connect, $projectId, $accessUsers, $workTypes, $statusRows, $fieldOptions)
    {
        $projectId = (int) $projectId;

        $workTypeNameMap = array();
        foreach ((array) $workTypes as $workType) {
            $id = isset($workType['id']) ? (int) $workType['id'] : 0;
            if ($id > 0) {
                $workTypeNameMap[$id] = isset($workType['name']) ? (string) $workType['name'] : ('Work Type #' . $id);
            }
        }

        $statusNameMap = array();
        foreach ((array) $statusRows as $statusRow) {
            $id = isset($statusRow['id']) ? (int) $statusRow['id'] : 0;
            if ($id > 0) {
                $statusNameMap[$id] = isset($statusRow['name']) ? (string) $statusRow['name'] : ('Status #' . $id);
            }
        }

        $fieldLabelMap = array();
        foreach ((array) $fieldOptions as $fieldOption) {
            $key = isset($fieldOption['key']) ? trim((string) $fieldOption['key']) : '';
            if ($key !== '') {
                $fieldLabelMap[$key] = isset($fieldOption['label']) ? (string) $fieldOption['label'] : $key;
            }
        }

        $lines = array();

        foreach ((array) $accessUsers as $accessUser) {
            $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $userName = '';
            if (isset($accessUser['name']) && trim((string) $accessUser['name']) !== '') {
                $userName = trim((string) $accessUser['name']);
            } elseif (isset($accessUser['username']) && trim((string) $accessUser['username']) !== '') {
                $userName = trim((string) $accessUser['username']);
            } elseif (isset($accessUser['email']) && trim((string) $accessUser['email']) !== '') {
                $userName = trim((string) $accessUser['email']);
            } else {
                $userName = 'User #' . $userId;
            }

            $accessRow = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
            $columnMap = taskGetProjectColumnAccessMap($connect, $projectId, $userId);

            $parts = array();

            $itemAccess = array();
            if (!empty($accessRow['work_item_add'])) {
                $itemAccess[] = 'Add';
            }
            if (!empty($accessRow['work_item_edit'])) {
                $itemAccess[] = 'Edit';
            }
            if (!empty($accessRow['work_item_delete'])) {
                $itemAccess[] = 'Delete';
            }
            if (!empty($itemAccess)) {
                $parts[] = 'Work Item: ' . implode('/', $itemAccess);
            }

            $allowedWorkTypes = array();
            if (isset($accessRow['allowed_work_type_ids']) && is_array($accessRow['allowed_work_type_ids'])) {
                foreach ($accessRow['allowed_work_type_ids'] as $workTypeId) {
                    $workTypeId = (int) $workTypeId;
                    if ($workTypeId > 0) {
                        $allowedWorkTypes[] = isset($workTypeNameMap[$workTypeId]) ? $workTypeNameMap[$workTypeId] : ('Work Type #' . $workTypeId);
                    }
                }
            }
            if (!empty($allowedWorkTypes)) {
                $parts[] = 'Work Type: ' . implode(', ', $allowedWorkTypes);
            }

            $allowedStatuses = array();
            if (isset($accessRow['allowed_status_ids']) && is_array($accessRow['allowed_status_ids'])) {
                foreach ($accessRow['allowed_status_ids'] as $statusId) {
                    $statusId = (int) $statusId;
                    if ($statusId > 0) {
                        $allowedStatuses[] = isset($statusNameMap[$statusId]) ? $statusNameMap[$statusId] : ('Status #' . $statusId);
                    }
                }
            }
            if (!empty($allowedStatuses)) {
                $parts[] = 'Status: ' . implode(', ', $allowedStatuses);
            }

            $columnParts = array();
            foreach ((array) $columnMap as $columnKey => $permissionRow) {
                $actions = array();

                if (!empty($permissionRow['add'])) {
                    $actions[] = 'Add';
                }
                if (!empty($permissionRow['edit'])) {
                    $actions[] = 'Edit';
                }
                if (!empty($permissionRow['delete'])) {
                    $actions[] = 'Delete';
                }

                if (!empty($actions)) {
                    $label = isset($fieldLabelMap[$columnKey]) ? $fieldLabelMap[$columnKey] : $columnKey;
                    $columnParts[] = $label . ' (' . implode('/', $actions) . ')';
                }
            }
            if (!empty($columnParts)) {
                $parts[] = 'Column: ' . implode(', ', $columnParts);
            }

            $lines[] = $userName . ': ' . (!empty($parts) ? implode(' | ', $parts) : 'No Access');
        }

        return !empty($lines) ? implode("\n", $lines) : 'No Access';
    }
}

if (!function_exists('taskProjectUserAccessWriteAudit')) {
    function taskProjectUserAccessWriteAudit($connect, $logAct, $projectId, $projectName, $oldValue, $newValue, $message, $auditTable = '')
    {
        global $cdate, $ctime;

        if (!defined('AUDIT_LOG')) {
            return;
        }

        if (!function_exists('get_allowed_audit_actions')) {
            return;
        }

        $logAct = strtolower(trim((string) $logAct));
        $actionId = get_allowed_audit_actions($logAct);

        if ($actionId === null) {
            return;
        }

        $projectId = (int) $projectId;
        $projectName = trim((string) $projectName);

        if ($projectName === '') {
            $projectName = 'Project #' . $projectId;
        }

        $auditTable = trim((string) $auditTable);
        if ($auditTable === '') {
            $auditTable = defined('TASK_PROJECT_ITEM_ACCESS') ? TASK_PROJECT_ITEM_ACCESS : 'task_project_item_access';
        }

        $oldValue = trim((string) $oldValue);
        $newValue = trim((string) $newValue);

        if ($logAct === 'view') {
            $actionMessage = USER_NAME . " viewed the project user access page (<b>" . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . "</b>).";
        } else {
            $fieldLabel = trim((string) $message);
            if ($fieldLabel === '') {
                $fieldLabel = 'Project User Access';
            }

            $actionMessage = USER_NAME . " edit the data";
            $actionMessage .= " [ <b> Project </b> : <b>'" . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . "'</b> ]";
            $actionMessage .= " , [ <b> " . htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') . " </b> : <b>'" . htmlspecialchars($oldValue !== '' ? $oldValue : 'Empty Value', ENT_QUOTES, 'UTF-8') . "'</b>";
            $actionMessage .= " to <b>'" . htmlspecialchars($newValue !== '' ? $newValue : 'Empty Value', ENT_QUOTES, 'UTF-8') . "'</b> ]";
            $actionMessage .= " under <b><i>" . htmlspecialchars($auditTable, ENT_QUOTES, 'UTF-8') . " Table</i></b>.";
        }

        $safeActionId = (int) $actionId;
        $safePage = mysqli_real_escape_string($connect, 'Project User Access');
        $safeUserId = mysqli_real_escape_string($connect, (string) USER_ID);
        $safeMessage = mysqli_real_escape_string($connect, $actionMessage);
        $safeDate = mysqli_real_escape_string($connect, (string) $cdate);
        $safeTime = mysqli_real_escape_string($connect, (string) $ctime);
        $safeQueryRec = mysqli_real_escape_string($connect, 'project_id=' . $projectId);
        $safeQueryTable = mysqli_real_escape_string($connect, $auditTable);
        $safeOldValue = mysqli_real_escape_string($connect, $oldValue !== '' ? $oldValue : 'Empty Value');
        $safeNewValue = mysqli_real_escape_string($connect, $newValue !== '' ? $newValue : 'Empty Value');

        $sql = "INSERT INTO " . AUDIT_LOG . " (
                    log_action,
                    screen_type,
                    query_record,
                    query_table,
                    old_value,
                    new_value,
                    changes,
                    user_id,
                    action_message,
                    create_date,
                    create_time,
                    create_by
                ) VALUES (
                    '" . $safeActionId . "',
                    '" . $safePage . "',
                    '" . $safeQueryRec . "',
                    '" . $safeQueryTable . "',
                    '" . $safeOldValue . "',
                    '" . $safeNewValue . "',
                    '',
                    '" . $safeUserId . "',
                    '" . $safeMessage . "',
                    '" . $safeDate . "',
                    '" . $safeTime . "',
                    '" . $safeUserId . "'
                )";

        mysqli_query($connect, $sql);
    }
}

if (!function_exists('taskProjectUserAccessActionText')) {
    function taskProjectUserAccessActionText($actions)
    {
        $actions = array_values(array_filter((array) $actions, function ($value) {
            return trim((string) $value) !== '';
        }));

        return !empty($actions) ? implode('/', $actions) : 'No Access';
    }
}

if (!function_exists('taskProjectUserAccessListText')) {
    function taskProjectUserAccessListText($items)
    {
        $items = array_values(array_filter((array) $items, function ($value) {
            return trim((string) $value) !== '';
        }));

        return !empty($items) ? implode(', ', $items) : 'No Access';
    }
}

if (!function_exists('taskProjectUserAccessAuditSnapshot')) {
    function taskProjectUserAccessAuditSnapshot($connect, $projectId, $accessUsers, $workTypes, $statusRows, $fieldOptions)
    {
        $projectId = (int) $projectId;

        $workTypeNameMap = array();
        foreach ((array) $workTypes as $workType) {
            $id = isset($workType['id']) ? (int) $workType['id'] : 0;
            if ($id > 0) {
                $workTypeNameMap[$id] = isset($workType['name']) ? (string) $workType['name'] : ('Work Type #' . $id);
            }
        }

        $statusNameMap = array();
        foreach ((array) $statusRows as $statusRow) {
            $id = isset($statusRow['id']) ? (int) $statusRow['id'] : 0;
            if ($id > 0) {
                $statusNameMap[$id] = isset($statusRow['name']) ? (string) $statusRow['name'] : ('Status #' . $id);
            }
        }

        $fieldLabelMap = array();
        foreach ((array) $fieldOptions as $fieldOption) {
            $key = isset($fieldOption['key']) ? trim((string) $fieldOption['key']) : '';
            if ($key !== '') {
                $fieldLabelMap[$key] = isset($fieldOption['label']) ? (string) $fieldOption['label'] : $key;
            }
        }

        $snapshot = array();

        foreach ((array) $accessUsers as $accessUser) {
            $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $userName = '';
            if (isset($accessUser['name']) && trim((string) $accessUser['name']) !== '') {
                $userName = trim((string) $accessUser['name']);
            } elseif (isset($accessUser['username']) && trim((string) $accessUser['username']) !== '') {
                $userName = trim((string) $accessUser['username']);
            } elseif (isset($accessUser['email']) && trim((string) $accessUser['email']) !== '') {
                $userName = trim((string) $accessUser['email']);
            } else {
                $userName = 'User #' . $userId;
            }

            $accessRow = taskGetProjectUserAccessRecord($connect, $projectId, $userId);
            $columnMap = taskGetProjectColumnAccessMap($connect, $projectId, $userId);

            $workItemActions = array();
            if (!empty($accessRow['work_item_add'])) {
                $workItemActions[] = 'Add';
            }
            if (!empty($accessRow['work_item_edit'])) {
                $workItemActions[] = 'Edit';
            }
            if (!empty($accessRow['work_item_delete'])) {
                $workItemActions[] = 'Delete';
            }

            $allowedWorkTypes = array();
            if (isset($accessRow['allowed_work_type_ids']) && is_array($accessRow['allowed_work_type_ids'])) {
                foreach ($accessRow['allowed_work_type_ids'] as $workTypeId) {
                    $workTypeId = (int) $workTypeId;
                    if ($workTypeId > 0) {
                        $allowedWorkTypes[] = isset($workTypeNameMap[$workTypeId]) ? $workTypeNameMap[$workTypeId] : ('Work Type #' . $workTypeId);
                    }
                }
            }

            $allowedStatuses = array();
            if (isset($accessRow['allowed_status_ids']) && is_array($accessRow['allowed_status_ids'])) {
                foreach ($accessRow['allowed_status_ids'] as $statusId) {
                    $statusId = (int) $statusId;
                    if ($statusId > 0) {
                        $allowedStatuses[] = isset($statusNameMap[$statusId]) ? $statusNameMap[$statusId] : ('Status #' . $statusId);
                    }
                }
            }

            $columns = array();
            foreach ($fieldLabelMap as $fieldKey => $fieldLabel) {
                $permissionRow = isset($columnMap[$fieldKey]) && is_array($columnMap[$fieldKey]) ? $columnMap[$fieldKey] : array();

                $actions = array();
                if (!empty($permissionRow['add'])) {
                    $actions[] = 'Add';
                }
                if (!empty($permissionRow['edit'])) {
                    $actions[] = 'Edit';
                }
                if (!empty($permissionRow['delete'])) {
                    $actions[] = 'Delete';
                }

                $columns[$fieldKey] = array(
                    'label' => $fieldLabel,
                    'actions' => $actions,
                );
            }

            $snapshot[$userId] = array(
                'user_name' => $userName,
                'work_item' => $workItemActions,
                'work_type' => $allowedWorkTypes,
                'status' => $allowedStatuses,
                'columns' => $columns,
            );
        }

        return $snapshot;
    }
}

if (!function_exists('taskProjectUserAccessDiffAuditValues')) {
    function taskProjectUserAccessDiffAuditValues($oldSnapshot, $newSnapshot)
    {
        $changes = array();

        $userIds = array_unique(array_merge(array_keys((array) $oldSnapshot), array_keys((array) $newSnapshot)));
        sort($userIds);

        foreach ($userIds as $userId) {
            $oldUser = isset($oldSnapshot[$userId]) && is_array($oldSnapshot[$userId]) ? $oldSnapshot[$userId] : array();
            $newUser = isset($newSnapshot[$userId]) && is_array($newSnapshot[$userId]) ? $newSnapshot[$userId] : array();

            $userName = '';
            if (!empty($newUser['user_name'])) {
                $userName = (string) $newUser['user_name'];
            } elseif (!empty($oldUser['user_name'])) {
                $userName = (string) $oldUser['user_name'];
            } else {
                $userName = 'User #' . (int) $userId;
            }

            $oldWorkItem = isset($oldUser['work_item']) ? (array) $oldUser['work_item'] : array();
            $newWorkItem = isset($newUser['work_item']) ? (array) $newUser['work_item'] : array();

            if ($oldWorkItem !== $newWorkItem) {
                $changes[] = array(
                    'field' => $userName . ' - Work Item',
                    'old' => taskProjectUserAccessActionText($oldWorkItem),
                    'new' => taskProjectUserAccessActionText($newWorkItem),
                    'table' => defined('TASK_PROJECT_ITEM_ACCESS') ? TASK_PROJECT_ITEM_ACCESS : 'task_project_item_access',
                );
            }

            $oldWorkType = isset($oldUser['work_type']) ? (array) $oldUser['work_type'] : array();
            $newWorkType = isset($newUser['work_type']) ? (array) $newUser['work_type'] : array();

            if ($oldWorkType !== $newWorkType) {
                $changes[] = array(
                    'field' => $userName . ' - Work Type',
                    'old' => taskProjectUserAccessListText($oldWorkType),
                    'new' => taskProjectUserAccessListText($newWorkType),
                    'table' => defined('TASK_PROJECT_ITEM_ACCESS') ? TASK_PROJECT_ITEM_ACCESS : 'task_project_item_access',
                );
            }

            $oldStatus = isset($oldUser['status']) ? (array) $oldUser['status'] : array();
            $newStatus = isset($newUser['status']) ? (array) $newUser['status'] : array();

            if ($oldStatus !== $newStatus) {
                $changes[] = array(
                    'field' => $userName . ' - Status',
                    'old' => taskProjectUserAccessListText($oldStatus),
                    'new' => taskProjectUserAccessListText($newStatus),
                    'table' => defined('TASK_PROJECT_STATUS_ACCESS') ? TASK_PROJECT_STATUS_ACCESS : 'task_project_status_access',
                );
            }

            $oldColumns = isset($oldUser['columns']) && is_array($oldUser['columns']) ? $oldUser['columns'] : array();
            $newColumns = isset($newUser['columns']) && is_array($newUser['columns']) ? $newUser['columns'] : array();
            $columnKeys = array_unique(array_merge(array_keys($oldColumns), array_keys($newColumns)));

            foreach ($columnKeys as $columnKey) {
                $oldColumn = isset($oldColumns[$columnKey]) && is_array($oldColumns[$columnKey]) ? $oldColumns[$columnKey] : array();
                $newColumn = isset($newColumns[$columnKey]) && is_array($newColumns[$columnKey]) ? $newColumns[$columnKey] : array();

                $label = '';
                if (!empty($newColumn['label'])) {
                    $label = (string) $newColumn['label'];
                } elseif (!empty($oldColumn['label'])) {
                    $label = (string) $oldColumn['label'];
                } else {
                    $label = (string) $columnKey;
                }

                $oldActions = isset($oldColumn['actions']) ? (array) $oldColumn['actions'] : array();
                $newActions = isset($newColumn['actions']) ? (array) $newColumn['actions'] : array();

                if ($oldActions !== $newActions) {
                    $changes[] = array(
                        'field' => $userName . ' - Column - ' . $label,
                        'old' => taskProjectUserAccessActionText($oldActions),
                        'new' => taskProjectUserAccessActionText($newActions),
                        'table' => defined('TASK_PROJECT_COLUMN_ACCESS') ? TASK_PROJECT_COLUMN_ACCESS : 'task_project_column_access',
                    );
                }
            }
        }

        return $changes;
    }
}

$currentUserId = USER_ID;
$currentProjectId = taskResolveCurrentProjectId($connect, 0);
$currentProject = $currentProjectId > 0 ? taskGetProjectById($connect, $currentProjectId) : array();
$taskParentTitle = !empty($currentProject) && isset($currentProject['name']) && trim((string) $currentProject['name']) !== ''
    ? (string) $currentProject['name']
    : 'Task Management';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $currentProjectId > 0) {
    $auditProjectName = isset($currentProject['name']) ? (string) $currentProject['name'] : ('Project #' . $currentProjectId);

    taskProjectUserAccessWriteAudit(
        $connect,
        'view',
        $currentProjectId,
        $auditProjectName,
        '',
        '',
        ''
    );
}

if ($currentProjectId > 0 && !taskCanAccessProjectUserAccess($connect, $currentProjectId)) {
    echo "<script>alert('Only the project owner can access project user access page.'); location.replace('../dashboard.php');</script>";
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action']) && $_POST['task_action'] === 'save_project_user_access_ajax') {
    if ($currentProjectId <= 0) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Project not found.'));
    }

    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
    }

    $auditProjectName = isset($currentProject['name']) ? (string) $currentProject['name'] : ('Project #' . $currentProjectId);

    $auditAccessUsers = taskGetProjectAccessUsers($connect, $currentProjectId);
    $auditWorkTypes = taskGetWorkTypes($connect, $currentProjectId);
    $auditStatusRows = taskGetColumns($connect, $currentProjectId);
    $auditFieldOptions = taskGetProjectAccessFieldOptions();

    $oldAuditSnapshot = taskProjectUserAccessAuditSnapshot(
        $connect,
        $currentProjectId,
        $auditAccessUsers,
        $auditWorkTypes,
        $auditStatusRows,
        $auditFieldOptions
    );

    $userRows = array();
    $postedUsers = isset($_POST['access_user_ids']) && is_array($_POST['access_user_ids']) ? $_POST['access_user_ids'] : array();

    foreach ($postedUsers as $rawUserId) {
        $userId = (int) $rawUserId;
        if ($userId <= 0) {
            continue;
        }

        $userRows[$userId] = array(
            'work_item_add' => isset($_POST['work_item_add'][$userId]) ? 1 : 0,
            'work_item_edit' => isset($_POST['work_item_edit'][$userId]) ? 1 : 0,
            'work_item_delete' => isset($_POST['work_item_delete'][$userId]) ? 1 : 0,
            'allowed_work_type_ids' => isset($_POST['allowed_work_type_ids'][$userId]) && is_array($_POST['allowed_work_type_ids'][$userId]) ? $_POST['allowed_work_type_ids'][$userId] : array(),
            'allowed_status_ids' => isset($_POST['allowed_status_ids'][$userId]) && is_array($_POST['allowed_status_ids'][$userId]) ? $_POST['allowed_status_ids'][$userId] : array(),
            'column_permissions' => isset($_POST['column_permissions'][$userId]) && is_array($_POST['column_permissions'][$userId]) ? $_POST['column_permissions'][$userId] : array(),
        );
    }

    $result = taskSaveProjectUserAccess($connect, $currentProjectId, $userRows, $currentUserId, $cdate, $ctime);

    if (!empty($result['ok'])) {
        $newAuditSnapshot = taskProjectUserAccessAuditSnapshot(
            $connect,
            $currentProjectId,
            $auditAccessUsers,
            $auditWorkTypes,
            $auditStatusRows,
            $auditFieldOptions
        );

        $auditChanges = taskProjectUserAccessDiffAuditValues($oldAuditSnapshot, $newAuditSnapshot);

        foreach ($auditChanges as $auditChange) {
            taskProjectUserAccessWriteAudit(
                $connect,
                'edit',
                $currentProjectId,
                $auditProjectName,
                isset($auditChange['old']) ? (string) $auditChange['old'] : '',
                isset($auditChange['new']) ? (string) $auditChange['new'] : '',
                isset($auditChange['field']) ? (string) $auditChange['field'] : 'Project User Access',
                isset($auditChange['table']) ? (string) $auditChange['table'] : ''
            );
        }
    }

    taskJsonResponse($result);
}

$accessUsers = $currentProjectId > 0 ? taskGetProjectAccessUsers($connect, $currentProjectId) : array();
$workTypes = $currentProjectId > 0 ? taskGetWorkTypes($connect, $currentProjectId) : array();
$statusRows = $currentProjectId > 0 ? taskGetColumns($connect, $currentProjectId) : array();
$fieldOptions = taskGetProjectAccessFieldOptions();
$userAccessMap = array();
$userColumnAccessMap = array();
foreach ($accessUsers as $accessUser) {
    $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
    if ($userId > 0) {
        $userAccessMap[$userId] = taskGetProjectUserAccessRecord($connect, $currentProjectId, $userId, false);
        $userColumnAccessMap[$userId] = taskGetProjectColumnAccessMap($connect, $currentProjectId, $userId, false);
    }
}

function taskProjectAccessChecked($row, $key, $needle = null)
{
    if ($needle === null) {
        return !empty($row[$key]);
    }
    if (!isset($row[$key]) || !is_array($row[$key])) {
        return false;
    }
    return in_array($needle, $row[$key], true);
}

function taskProjectAccessInitials($name)
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
    return $initials !== '' ? $initials : strtoupper(substr($name, 0, 1));
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/project_user_access.css">
</head>
<body class="project-user-access-page">
<div class="container-fluid task-page-wrap px-0">
    <div id="taskBoardApp" class="d-none" aria-hidden="true"></div>
    <div class="col-12 px-0">
        <div class="col-12 col-md-11 mx-auto project-access-page-header">
        <div class="d-flex flex-column mb-3">
            <div class="row">
                <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($taskParentTitle, ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="row">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                    <div>
                        <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="project-access-subtitle mb-0">Manage what users can do in this project.</p>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <section id="taskModuleLayout" class="task-module-layout task-sidebar-open">
            <aside class="task-module-sidebar" id="taskModuleSidebar">
                <h5 class="mb-2">Task Management</h5>
                <?php taskRenderSidebarMenu($connect, $SITEURL, 'project_user_access', $currentProjectId); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div class="task-main-content">
                <?php if ($currentProjectId <= 0): ?>
                    <div class="task-empty-board-note">No project task found yet.</div>
                <?php else: ?>
                    <form method="post" class="project-access-form" id="projectUserAccessForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div id="projectAccessHiddenColumnStore"></div>

                        <?php foreach ($accessUsers as $accessUser): ?>
                        <?php
                        $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
                        $accessRow = isset($userAccessMap[$userId]) && is_array($userAccessMap[$userId])
                        ? $userAccessMap[$userId]
                        : array();
                        ?>
                            <input type="hidden" name="access_user_ids[]" value="<?= (int) $accessUser['id'] ?>">
                        <?php endforeach; ?>

                        <div class="project-access-tabs" role="tablist" aria-label="Project user access sections">
                            <button type="button" class="project-access-tab active" data-project-access-tab="work-item">Work Item Access</button>
                            <button type="button" class="project-access-tab" data-project-access-tab="work-type">Work Type Access</button>
                            <button type="button" class="project-access-tab" data-project-access-tab="status">Status Access</button>
                            <button type="button" class="project-access-tab" data-project-access-tab="column">Column Access</button>
                        </div>

                        <div class="project-access-panel active" data-project-access-panel="work-item">
                            <div class="project-access-card">
                                <h5>Work Item Access</h5>
                                <p class="project-access-card-note">Control which users can create, edit, or delete work items.</p>
                                <div class="project-access-table-wrap">
                                    <table class="project-access-table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Add</th>
                                                <th>Edit</th>
                                                <th>Delete</th>
                                                <th>Tick All</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($accessUsers as $accessUser): ?>
                                        <?php
                                        $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
                                        $accessRow = isset($userAccessMap[$userId]) && is_array($userAccessMap[$userId])
                                            ? $userAccessMap[$userId]
                                            : array();
                                        ?>
                                                <?php
                                                $userId = (int) $accessUser['id'];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="project-access-user">
                                                            <span class="project-access-avatar"><?= htmlspecialchars(taskProjectAccessInitials(isset($accessUser['name']) ? $accessUser['name'] : ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                            <div class="project-access-user-meta">
                                                                <strong><?= htmlspecialchars(isset($accessUser['name']) ? (string) $accessUser['name'] : '', ENT_QUOTES, 'UTF-8') ?></strong>
                                                                <span><?= htmlspecialchars(isset($accessUser['email']) ? (string) $accessUser['email'] : '', ENT_QUOTES, 'UTF-8') ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><input class="form-check-input project-access-checkbox" type="checkbox" name="work_item_add[<?= $userId ?>]" value="1" <?= taskProjectAccessChecked($accessRow, 'work_item_add') ? 'checked' : '' ?>></td>
                                                    <td><input class="form-check-input project-access-checkbox" type="checkbox" name="work_item_edit[<?= $userId ?>]" value="1" <?= taskProjectAccessChecked($accessRow, 'work_item_edit') ? 'checked' : '' ?>></td>
                                                    <td><input class="form-check-input project-access-checkbox" type="checkbox" name="work_item_delete[<?= $userId ?>]" value="1" <?= taskProjectAccessChecked($accessRow, 'work_item_delete') ? 'checked' : '' ?>></td>
                                                    <td><input class="form-check-input project-access-checkbox project-access-row-toggle" type="checkbox"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="project-access-panel" data-project-access-panel="work-type">
                            <div class="project-access-card">
                                <h5>Work Type Access</h5>
                                <p class="project-access-card-note">Choose which task types each user can use in this project.</p>
                                <div class="project-access-table-wrap">
                                    <table class="project-access-table project-access-table-wide">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <?php foreach ($workTypes as $workType): ?>
                                                    <th><?= htmlspecialchars(isset($workType['name']) ? (string) $workType['name'] : '', ENT_QUOTES, 'UTF-8') ?></th>
                                                <?php endforeach; ?>
                                                <th>Tick All</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($accessUsers as $accessUser): ?>
                                        <?php
                                        $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
                                        $accessRow = isset($userAccessMap[$userId]) && is_array($userAccessMap[$userId])
                                            ? $userAccessMap[$userId]
                                            : array();
                                        ?>
                                                <?php
                                                $userId = (int) $accessUser['id'];
                                                $accessRow = isset($userAccessMap[$userId]) ? $userAccessMap[$userId] : array();
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(isset($accessUser['name']) ? (string) $accessUser['name'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <?php foreach ($workTypes as $workType): ?>
                                                        <?php $workTypeId = isset($workType['id']) ? (int) $workType['id'] : 0; ?>
                                                        <td>
                                                            <label class="project-access-inline-option">
                                                                <span class="project-access-inline-icon">
                                                                    <img src="<?= htmlspecialchars($SITEURL . '/task/' . ltrim((string) (isset($workType['svg_icon']) ? $workType['svg_icon'] : 'svg_icon/10318.svg'), '/'), ENT_QUOTES, 'UTF-8') ?>" alt="">
                                                                </span>
                                                                <input class="form-check-input project-access-checkbox" type="checkbox" name="allowed_work_type_ids[<?= $userId ?>][]" value="<?= $workTypeId ?>" <?= taskProjectAccessChecked($accessRow, 'allowed_work_type_ids', $workTypeId) ? 'checked' : '' ?>>
                                                            </label>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td><input class="form-check-input project-access-checkbox project-access-row-toggle" type="checkbox"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="project-access-panel" data-project-access-panel="status">
                            <div class="project-access-card">
                                <h5>Status Access</h5>
                                <p class="project-access-card-note">Choose which board statuses each user can create into or move work items into.</p>
                                <div class="project-access-table-wrap">
                                    <table class="project-access-table project-access-table-wide">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <?php foreach ($statusRows as $statusRow): ?>
                                                    <th><?= htmlspecialchars(isset($statusRow['name']) ? (string) $statusRow['name'] : '', ENT_QUOTES, 'UTF-8') ?></th>
                                                <?php endforeach; ?>
                                                <th>Tick All</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($accessUsers as $accessUser): ?>
                                        <?php
                                        $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
                                        $accessRow = isset($userAccessMap[$userId]) && is_array($userAccessMap[$userId])
                                            ? $userAccessMap[$userId]
                                            : array();
                                        ?>
                                                <?php
                                                $userId = (int) $accessUser['id'];
                                                $accessRow = isset($userAccessMap[$userId]) ? $userAccessMap[$userId] : array();
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars(isset($accessUser['name']) ? (string) $accessUser['name'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                                                    <?php foreach ($statusRows as $statusRow): ?>
                                                        <?php $statusId = isset($statusRow['id']) ? (int) $statusRow['id'] : 0; ?>
                                                        <td><input class="form-check-input project-access-checkbox" type="checkbox" name="allowed_status_ids[<?= $userId ?>][]" value="<?= $statusId ?>" <?= taskProjectAccessChecked($accessRow, 'allowed_status_ids', $statusId) ? 'checked' : '' ?>></td>
                                                    <?php endforeach; ?>
                                                    <td><input class="form-check-input project-access-checkbox project-access-row-toggle" type="checkbox"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="project-access-panel" data-project-access-panel="column">
                            <div class="project-access-card">
                                <h5>Column Access</h5>
                                <p class="project-access-card-note">Control user permissions for work item detail fields (columns).</p>
                                <div class="project-access-column-sections">
                                    <?php foreach ($fieldOptions as $index => $fieldOption): ?>
                                        <?php
                                        $fieldKey = isset($fieldOption['key']) ? (string) $fieldOption['key'] : '';
                                        $fieldLabel = isset($fieldOption['label']) ? (string) $fieldOption['label'] : '';
                                        ?>
                                        <button type="button"
                                                class="project-access-column-section <?= $index === 0 ? 'active' : '' ?>"
                                                data-column-key="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="project-access-table-wrap">
                                    <table class="project-access-table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Add</th>
                                                <th>Edit</th>
                                                <th>Delete</th>
                                                <th>Tick All</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($accessUsers as $accessUser): ?>
                                        <?php
                                        $userId = isset($accessUser['id']) ? (int) $accessUser['id'] : 0;
                                        $accessRow = isset($userAccessMap[$userId]) && is_array($userAccessMap[$userId])
                                            ? $userAccessMap[$userId]
                                            : array();
                                        ?>
                                                <?php
                                                $userId = (int) $accessUser['id'];
                                                $accessRow = isset($userAccessMap[$userId]) ? $userAccessMap[$userId] : array();
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="project-access-user">
                                                            <span class="project-access-avatar"><?= htmlspecialchars(taskProjectAccessInitials(isset($accessUser['name']) ? $accessUser['name'] : ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                            <div class="project-access-user-meta">
                                                                <strong><?= htmlspecialchars(isset($accessUser['name']) ? (string) $accessUser['name'] : '', ENT_QUOTES, 'UTF-8') ?></strong>
                                                                <span><?= htmlspecialchars(isset($accessUser['email']) ? (string) $accessUser['email'] : '', ENT_QUOTES, 'UTF-8') ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><input class="form-check-input project-access-checkbox project-access-column-action" type="checkbox" data-column-action="add" data-user-id="<?= $userId ?>"></td>
                                                    <td><input class="form-check-input project-access-checkbox project-access-column-action" type="checkbox" data-column-action="edit" data-user-id="<?= $userId ?>"></td>
                                                    <td><input class="form-check-input project-access-checkbox project-access-column-action" type="checkbox" data-column-action="delete" data-user-id="<?= $userId ?>"></td>
                                                    <td><input class="form-check-input project-access-checkbox project-access-column-row-toggle" type="checkbox" data-user-id="<?= $userId ?>"></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </form>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
window.projectUserAccessColumnState = <?= json_encode($userColumnAccessMap, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
window.projectUserAccessConfig = {
    ajaxUrl: <?= json_encode($SITEURL . '/task/project_user_access.php?project_id=' . (int) $currentProjectId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode((string) $_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/project_user_access.js"></script>
</body>
</html>
