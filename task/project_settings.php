<?php
ob_start();

$currentPagePin = 140;
$pageTitle = 'Project Settings';
$taskParentTitle = 'Task Management';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once './common_task.php';

if (!function_exists('taskProjectSettingsAuditValue')) {
    function taskProjectSettingsAuditValue($value)
    {
        $value = trim((string) $value);
        return $value === '' ? 'Empty Value' : $value;
    }
}

if (!function_exists('taskProjectSettingsRowsById')) {
    function taskProjectSettingsRowsById($rows)
    {
        $map = array();

        foreach ((array) $rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $map[$id] = $row;
        }

        return $map;
    }
}

if (!function_exists('taskProjectSettingsAuditSnapshot')) {
    function taskProjectSettingsAuditSnapshot($connect, $projectId)
    {
        $projectId = (int) $projectId;

        $project = $projectId > 0 ? taskGetProjectById($connect, $projectId) : array();
        $projectKey = $projectId > 0 ? taskGetProjectKeySetting($connect, $projectId) : array();

        return array(
            'project' => array(
                'id' => $projectId,
                'name' => isset($project['name']) ? (string) $project['name'] : '',
                'board_background_color' => isset($project['board_background_color']) ? (string) $project['board_background_color'] : '',
            ),
            'project_key' => array(
                'id' => isset($projectKey['id']) ? (int) $projectKey['id'] : 0,
                'project_key' => isset($projectKey['project_key']) ? (string) $projectKey['project_key'] : '',
            ),
            'statuses' => taskProjectSettingsRowsById(taskGetColumns($connect, $projectId)),
            'work_types' => taskProjectSettingsRowsById(taskGetWorkTypes($connect, $projectId)),
            'labels' => taskProjectSettingsRowsById(taskGetLabels($connect)),
            'status_labels' => taskProjectSettingsRowsById(taskGetStatusLabels($connect)),
        );
    }
}

if (!function_exists('taskProjectSettingsAuditRowValue')) {
    function taskProjectSettingsAuditRowValue($row, $key)
    {
        return isset($row[$key]) ? taskProjectSettingsAuditValue($row[$key]) : 'Empty Value';
    }
}

if (!function_exists('taskProjectSettingsAuditAddEditChange')) {
    function taskProjectSettingsAuditAddEditChange(&$changes, $action, $table, $recordId, $field, $oldValue, $newValue)
    {
        $action = strtolower(trim((string) $action));
        $oldValue = taskProjectSettingsAuditValue($oldValue);
        $newValue = taskProjectSettingsAuditValue($newValue);

        if ($action === 'edit' && $oldValue === $newValue) {
            return;
        }

        $changes[] = array(
            'action' => $action,
            'table' => $table,
            'record_id' => (int) $recordId,
            'field' => $field,
            'old' => $oldValue,
            'new' => $newValue,
        );
    }
}

if (!function_exists('taskProjectSettingsAuditCompareField')) {
    function taskProjectSettingsAuditCompareField(&$changes, $table, $recordId, $fieldLabel, $oldRow, $newRow, $key)
    {
        $oldValue = isset($oldRow[$key]) ? (string) $oldRow[$key] : '';
        $newValue = isset($newRow[$key]) ? (string) $newRow[$key] : '';

        if (taskProjectSettingsAuditValue($oldValue) !== taskProjectSettingsAuditValue($newValue)) {
            taskProjectSettingsAuditAddEditChange(
                $changes,
                'edit',
                $table,
                $recordId,
                $fieldLabel,
                $oldValue,
                $newValue
            );
        }
    }
}

if (!function_exists('taskProjectSettingsAuditDiffRows')) {
    function taskProjectSettingsAuditDiffRows(&$changes, $oldRows, $newRows, $table, $nameLabel, $fields)
    {
        $oldIds = array_keys((array) $oldRows);
        $newIds = array_keys((array) $newRows);
        $allIds = array_values(array_unique(array_merge($oldIds, $newIds)));
        sort($allIds);

        foreach ($allIds as $id) {
            $id = (int) $id;
            $oldExists = isset($oldRows[$id]) && is_array($oldRows[$id]);
            $newExists = isset($newRows[$id]) && is_array($newRows[$id]);

            if ($oldExists && !$newExists) {
                $oldName = isset($oldRows[$id]['name']) ? (string) $oldRows[$id]['name'] : ('ID ' . $id);

                taskProjectSettingsAuditAddEditChange(
                    $changes,
                    'delete',
                    $table,
                    $id,
                    $nameLabel,
                    $oldName,
                    'Deleted'
                );

                continue;
            }

            if (!$oldExists && $newExists) {
                foreach ($fields as $key => $label) {
                    taskProjectSettingsAuditAddEditChange(
                        $changes,
                        'add',
                        $table,
                        $id,
                        $label,
                        '',
                        isset($newRows[$id][$key]) ? (string) $newRows[$id][$key] : ''
                    );
                }

                continue;
            }

            if ($oldExists && $newExists) {
                foreach ($fields as $key => $label) {
                    taskProjectSettingsAuditCompareField(
                        $changes,
                        $table,
                        $id,
                        $label,
                        $oldRows[$id],
                        $newRows[$id],
                        $key
                    );
                }
            }
        }
    }
}

if (!function_exists('taskProjectSettingsAuditDiff')) {
    function taskProjectSettingsAuditDiff($oldSnapshot, $newSnapshot)
    {
        $changes = array();

        $oldProject = isset($oldSnapshot['project']) ? $oldSnapshot['project'] : array();
        $newProject = isset($newSnapshot['project']) ? $newSnapshot['project'] : array();
        $projectId = isset($newProject['id']) ? (int) $newProject['id'] : (isset($oldProject['id']) ? (int) $oldProject['id'] : 0);

        if ($projectId > 0 && defined('TASK_PROJECT')) {
            taskProjectSettingsAuditCompareField($changes, TASK_PROJECT, $projectId, 'Project Task Name', $oldProject, $newProject, 'name');
            taskProjectSettingsAuditCompareField($changes, TASK_PROJECT, $projectId, 'Board Background Color', $oldProject, $newProject, 'board_background_color');
        }

        $oldProjectKey = isset($oldSnapshot['project_key']) ? $oldSnapshot['project_key'] : array();
        $newProjectKey = isset($newSnapshot['project_key']) ? $newSnapshot['project_key'] : array();
        $projectKeyId = isset($newProjectKey['id']) ? (int) $newProjectKey['id'] : (isset($oldProjectKey['id']) ? (int) $oldProjectKey['id'] : 0);

        if ($projectKeyId > 0 && defined('TASK_PROJECT_KEY')) {
            taskProjectSettingsAuditCompareField($changes, TASK_PROJECT_KEY, $projectKeyId, 'Project Key', $oldProjectKey, $newProjectKey, 'project_key');
        }

        if (defined('TASK_COLUMN')) {
            taskProjectSettingsAuditDiffRows(
                $changes,
                isset($oldSnapshot['statuses']) ? $oldSnapshot['statuses'] : array(),
                isset($newSnapshot['statuses']) ? $newSnapshot['statuses'] : array(),
                TASK_COLUMN,
                'Status',
                array(
                    'name' => 'Status Name',
                    'color' => 'Status Color',
                    'sort_order' => 'Status Sort Order',
                )
            );
        }

        if (defined('TASK_WORK_TYPE')) {
            taskProjectSettingsAuditDiffRows(
                $changes,
                isset($oldSnapshot['work_types']) ? $oldSnapshot['work_types'] : array(),
                isset($newSnapshot['work_types']) ? $newSnapshot['work_types'] : array(),
                TASK_WORK_TYPE,
                'Task Type',
                array(
                    'name' => 'Task Type Name',
                    'svg_icon' => 'Task Type Icon',
                )
            );
        }

        if (defined('TASK_LABEL')) {
            taskProjectSettingsAuditDiffRows(
                $changes,
                isset($oldSnapshot['labels']) ? $oldSnapshot['labels'] : array(),
                isset($newSnapshot['labels']) ? $newSnapshot['labels'] : array(),
                TASK_LABEL,
                'Label',
                array(
                    'name' => 'Label Name',
                    'color' => 'Label Color',
                )
            );
        }

        if (defined('TASK_STATUS_LABEL')) {
            taskProjectSettingsAuditDiffRows(
                $changes,
                isset($oldSnapshot['status_labels']) ? $oldSnapshot['status_labels'] : array(),
                isset($newSnapshot['status_labels']) ? $newSnapshot['status_labels'] : array(),
                TASK_STATUS_LABEL,
                'Task Status Label',
                array(
                    'name' => 'Task Status Label Name',
                    'color' => 'Task Status Label Color',
                )
            );
        }

        return $changes;
    }
}

if (!function_exists('taskProjectSettingsWriteAudit')) {
    function taskProjectSettingsWriteAudit($connect, $projectId, $projectName, $change)
    {
        global $cdate, $ctime;

        if (!function_exists('audit_log')) {
            return;
        }

        $action = isset($change['action']) ? strtolower(trim((string) $change['action'])) : '';
        if (!in_array($action, array('add', 'edit', 'delete'), true)) {
            return;
        }

        $table = isset($change['table']) ? trim((string) $change['table']) : '';
        if ($table === '') {
            $table = defined('TASK_PROJECT') ? TASK_PROJECT : 'task_project';
        }

        $recordId = isset($change['record_id']) ? (int) $change['record_id'] : (int) $projectId;
        $field = isset($change['field']) ? (string) $change['field'] : 'Project Settings';
        $oldValue = isset($change['old']) ? taskProjectSettingsAuditValue($change['old']) : 'Empty Value';
        $newValue = isset($change['new']) ? taskProjectSettingsAuditValue($change['new']) : 'Empty Value';

        if ($action === 'add') {
            $actMsg = function_exists('actMsgLog')
                ? actMsgLog($recordId, array($field), array($newValue), array(), array(), $table, 'Add', '')
                : (USER_NAME . " add the data [ ID = " . $recordId . " ] [ " . $field . " : '" . $newValue . "' ] under " . $table . " Table.");
        } elseif ($action === 'edit') {
            $actMsg = function_exists('actMsgLog')
                ? actMsgLog($recordId, array($field), array(), array($oldValue), array($newValue), $table, 'Edit', '')
                : (USER_NAME . " edit the data [ ID = " . $recordId . " ] [ " . $field . " : '" . $oldValue . "' to '" . $newValue . "' ] under " . $table . " Table.");
        } else {
            $actMsg = USER_NAME . " delete the data [ <b> ID = " . $recordId . " </b> ]";
            $actMsg .= " [ <b> " . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . " </b> : <b>'" . htmlspecialchars($oldValue, ENT_QUOTES, 'UTF-8') . "'</b> ]";
            $actMsg .= " under <b><i>" . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . " Table</i></b>.";
        }

        $projectName = trim((string) $projectName);
        if ($projectName !== '') {
            $actMsg .= " [ Project : " . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . " ]";
        }

        audit_log(array(
            'log_act' => ucfirst($action),
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'project_id=' . (int) $projectId . ';id=' . $recordId,
            'query_table' => $table,
            'oldval' => $oldValue,
            'changes' => $newValue,
            'newval' => $newValue,
            'act_msg' => $actMsg,
            'page' => 'Project Settings',
            'connect' => $connect,
        ));
    }
}

if (!function_exists('taskProjectSettingsWriteViewAudit')) {
    function taskProjectSettingsWriteViewAudit($connect, $projectId, $projectName)
    {
        global $cdate, $ctime;

        if (!function_exists('audit_log')) {
            return;
        }

        $projectName = trim((string) $projectName);
        if ($projectName === '') {
            $projectName = 'Project #' . (int) $projectId;
        }

        audit_log(array(
            'log_act' => 'View',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'project_id=' . (int) $projectId,
            'query_table' => defined('TASK_PROJECT') ? TASK_PROJECT : 'task_project',
            'oldval' => '',
            'changes' => '',
            'newval' => '',
            'act_msg' => USER_NAME . " viewed the project settings page (<b>" . htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') . "</b>).",
            'page' => 'Project Settings',
            'connect' => $connect,
        ));
    }
}

$pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    echo "<script>alert('You do not have permission to view project settings.'); location.replace('../dashboard.php');</script>";
    exit;
}

$currentUserId = USER_ID;
$currentProjectId = taskResolveCurrentProjectId($connect, 0);
$currentProject = $currentProjectId > 0 ? taskGetProjectById($connect, $currentProjectId) : array();
$isOwner = $currentProjectId > 0 ? taskIsProjectOwner($connect, $currentProjectId, $currentUserId) : false;
$taskParentTitle = !empty($currentProject) && isset($currentProject['name']) && trim((string) $currentProject['name']) !== ''
    ? (string) $currentProject['name']
    : 'Task Management';
$canEdit = $currentProjectId > 0 ? taskCanEditProjectSettings($connect, $currentProjectId) : false;
if ($currentProjectId > 0 && !taskCanAccessProjectSettings($connect, $currentProjectId, true)) {
    echo "<script>alert('You do not have permission to view project settings.'); location.replace('../dashboard.php');</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $currentProjectId > 0) {
    taskProjectSettingsWriteViewAudit(
        $connect,
        $currentProjectId,
        isset($currentProject['name']) ? (string) $currentProject['name'] : ('Project #' . $currentProjectId)
    );
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action']) && $_POST['task_action'] === 'save_project_settings_ajax') {
    if (!$canEdit) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit project settings.'));
    }

    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
    }

        $statusRows = array();
        $statusIds = isset($_POST['status_ids']) && is_array($_POST['status_ids']) ? $_POST['status_ids'] : array();
        $statusNames = isset($_POST['status_names']) && is_array($_POST['status_names']) ? $_POST['status_names'] : array();
        $statusColors = isset($_POST['status_colors']) && is_array($_POST['status_colors']) ? $_POST['status_colors'] : array();
        $statusCount = max(count($statusIds), count($statusNames), count($statusColors));
        for ($i = 0; $i < $statusCount; $i++) {
            $statusRows[] = array(
                'id' => isset($statusIds[$i]) ? (int) $statusIds[$i] : 0,
                'name' => isset($statusNames[$i]) ? (string) $statusNames[$i] : '',
                'color' => isset($statusColors[$i]) ? (string) $statusColors[$i] : '',
            );
        }

        $workTypeRows = array();
        $workTypeIds = isset($_POST['work_type_ids']) && is_array($_POST['work_type_ids']) ? $_POST['work_type_ids'] : array();
        $workTypeNames = isset($_POST['work_type_names']) && is_array($_POST['work_type_names']) ? $_POST['work_type_names'] : array();
        $workTypeIcons = isset($_POST['work_type_icons']) && is_array($_POST['work_type_icons']) ? $_POST['work_type_icons'] : array();
        $workTypeCount = max(count($workTypeIds), count($workTypeNames), count($workTypeIcons));
        for ($i = 0; $i < $workTypeCount; $i++) {
            $workTypeRows[] = array(
                'id' => isset($workTypeIds[$i]) ? (int) $workTypeIds[$i] : 0,
                'name' => isset($workTypeNames[$i]) ? (string) $workTypeNames[$i] : '',
                'svg_icon' => isset($workTypeIcons[$i]) ? (string) $workTypeIcons[$i] : '',
            );
        }

        $labelRows = array();
        $labelIds = isset($_POST['label_ids']) && is_array($_POST['label_ids']) ? $_POST['label_ids'] : array();
        $labelNames = isset($_POST['label_names']) && is_array($_POST['label_names']) ? $_POST['label_names'] : array();
        $labelColors = isset($_POST['label_colors']) && is_array($_POST['label_colors']) ? $_POST['label_colors'] : array();
        $labelCount = max(count($labelIds), count($labelNames), count($labelColors));
        for ($i = 0; $i < $labelCount; $i++) {
            $labelRows[] = array(
                'id' => isset($labelIds[$i]) ? (int) $labelIds[$i] : 0,
                'name' => isset($labelNames[$i]) ? (string) $labelNames[$i] : '',
                'color' => isset($labelColors[$i]) ? (string) $labelColors[$i] : '',
            );
        }

        $statusLabelRows = array();
        $statusLabelIds = isset($_POST['status_label_ids']) && is_array($_POST['status_label_ids']) ? $_POST['status_label_ids'] : array();
        $statusLabelNames = isset($_POST['status_label_names']) && is_array($_POST['status_label_names']) ? $_POST['status_label_names'] : array();
        $statusLabelColors = isset($_POST['status_label_colors']) && is_array($_POST['status_label_colors']) ? $_POST['status_label_colors'] : array();
        $statusLabelCount = max(count($statusLabelIds), count($statusLabelNames), count($statusLabelColors));
        for ($i = 0; $i < $statusLabelCount; $i++) {
            $statusLabelRows[] = array(
                'id' => isset($statusLabelIds[$i]) ? (int) $statusLabelIds[$i] : 0,
                'name' => isset($statusLabelNames[$i]) ? (string) $statusLabelNames[$i] : '',
                'color' => isset($statusLabelColors[$i]) ? (string) $statusLabelColors[$i] : '',
            );
        }

        $statusDeleteIds = isset($_POST['status_delete_ids']) && is_array($_POST['status_delete_ids']) ? $_POST['status_delete_ids'] : array();
        $workTypeDeleteIds = isset($_POST['work_type_delete_ids']) && is_array($_POST['work_type_delete_ids']) ? $_POST['work_type_delete_ids'] : array();
        $labelDeleteIds = isset($_POST['label_delete_ids']) && is_array($_POST['label_delete_ids']) ? $_POST['label_delete_ids'] : array();
        $statusLabelDeleteIds = isset($_POST['status_label_delete_ids']) && is_array($_POST['status_label_delete_ids']) ? $_POST['status_label_delete_ids'] : array();

        $oldSettingsAuditSnapshot = taskProjectSettingsAuditSnapshot($connect, $currentProjectId);
        $auditProjectName = isset($currentProject['name']) ? (string) $currentProject['name'] : ('Project #' . $currentProjectId);

        $result = taskSaveProjectSettings(
            $connect,
            $currentProjectId,
            isset($_POST['project_name']) ? $_POST['project_name'] : '',
            isset($_POST['project_key']) ? $_POST['project_key'] : '',
            isset($_POST['board_background_color']) ? $_POST['board_background_color'] : '',
            $statusRows,
            $statusDeleteIds,
            $workTypeRows,
            $workTypeDeleteIds,
            $labelRows,
            $labelDeleteIds,
            $statusLabelRows,
            $statusLabelDeleteIds,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $newSettingsAuditSnapshot = taskProjectSettingsAuditSnapshot($connect, $currentProjectId);
            $auditChanges = taskProjectSettingsAuditDiff($oldSettingsAuditSnapshot, $newSettingsAuditSnapshot);

            foreach ($auditChanges as $auditChange) {
                taskProjectSettingsWriteAudit(
                    $connect,
                    $currentProjectId,
                    $auditProjectName,
                    $auditChange
                );
            }
        }

        taskJsonResponse($result);
}

$projectNameValue = isset($currentProject['name']) ? (string) $currentProject['name'] : '';
$boardBackgroundValue = isset($currentProject['board_background_color']) ? (string) $currentProject['board_background_color'] : '#f4f7fb';
$projectKeySetting = taskGetProjectKeySetting($connect, $currentProjectId);
$statusRows = taskGetColumns($connect, $currentProjectId);
$workTypeRows = taskGetWorkTypes($connect, $currentProjectId);
$labelRows = taskGetLabels($connect);
$statusLabelRows = taskGetStatusLabels($connect);
$workTypeIconOptions = taskGetSvgIconOptions();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/project_settings.css">
</head>
<body class="project-settings-page">
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
                <?php taskRenderSidebarMenu($connect, $SITEURL, 'project_settings', $currentProjectId); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div class="task-main-content">
                <?php if ($currentProjectId <= 0): ?>
                    <div class="task-empty-board-note">No project task found yet.</div>
                <?php else: ?>
                    <form method="post" class="task-project-settings-form" id="taskProjectSettingsForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="task-project-settings-top-row mb-4">
                            <div class="row g-3 mb-0">
                                <div class="col-12 col-lg-7">
                                    <label class="form-label" for="project_name">Project Task Name</label>
                                    <input type="text" class="form-control" id="project_name" name="project_name" maxlength="180" value="<?= htmlspecialchars($projectNameValue, ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                </div>
                                <div class="col-12 col-lg-3">
                                    <label class="form-label" for="board_background_color">Board Background Color</label>
                                    <div class="task-project-color-control">
                                        <input type="color" class="form-control form-control-color w-100" id="board_background_color" name="board_background_color" value="<?= htmlspecialchars($boardBackgroundValue, ENT_QUOTES, 'UTF-8') ?>" data-default-color="#F4F7FB" <?= $canEdit ? '' : 'disabled' ?>>
                                        <?php if ($canEdit): ?>
                                            <button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="Reset board background color to default?" data-color-target="#board_background_color" data-default-color="#F4F7FB">Reset Default</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-2">
                                    <label class="form-label" for="project_key">Project Key</label>
                                    <input type="text" class="form-control" id="project_key" name="project_key" maxlength="20" value="<?= htmlspecialchars(isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '', ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Statuses</h5>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addProjectStatusRowBtn">Add Status</button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div id="projectStatusDeleteBucket"></div>
                                <div id="projectStatusRows" class="d-flex flex-column gap-2">
                                    <?php foreach ($statusRows as $status): ?>
                                        <div class="task-project-settings-row task-project-status-row">
                                            <input type="hidden" name="status_ids[]" value="<?= (int) $status['id'] ?>">
                                            <input type="text" class="form-control" name="status_names[]" maxlength="150" value="<?= htmlspecialchars(isset($status['name']) ? (string) $status['name'] : '', ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                            <div class="task-project-color-control">
                                                <input type="color" class="form-control form-control-color" name="status_colors[]" value="<?= htmlspecialchars(isset($status['color']) ? (string) $status['color'] : '#dfe1e6', ENT_QUOTES, 'UTF-8') ?>" data-default-color="<?= htmlspecialchars(isset($status['color']) ? (string) $status['color'] : '#dfe1e6', ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                                <?php if ($canEdit): ?>
                                                    <button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="Reset this status color to default?" data-color-input="closest" data-default-color="<?= htmlspecialchars(isset($status['color']) ? (string) $status['color'] : '#dfe1e6', ENT_QUOTES, 'UTF-8') ?>">Reset Default</button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($canEdit): ?>
                                                <button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="status" data-existing-id="<?= (int) $status['id'] ?>">Remove</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Task Types</h5>
                                <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addProjectWorkTypeRowBtn">Add Task Type</button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div id="projectWorkTypeDeleteBucket"></div>
                                <div id="projectWorkTypeRows" class="d-flex flex-column gap-2">
                                    <?php foreach ($workTypeRows as $workType): ?>
                                        <div class="task-project-settings-row task-project-worktype-row">
                                            <input type="hidden" name="work_type_ids[]" value="<?= (int) $workType['id'] ?>">
                                            <div class="task-project-worktype-name-wrap">
                                                <span class="task-project-worktype-icon-preview">
                                                    <img src="<?= htmlspecialchars(isset($workType['svg_icon']) ? (string) $workType['svg_icon'] : 'svg_icon/10318.svg', ENT_QUOTES, 'UTF-8') ?>" alt="">
                                                </span>
                                                <input type="text" class="form-control" name="work_type_names[]" maxlength="80" value="<?= htmlspecialchars(isset($workType['name']) ? (string) $workType['name'] : '', ENT_QUOTES, 'UTF-8') ?>" <?= $canEdit ? '' : 'disabled' ?>>
                                            </div>
                                            <div class="dropdown task-project-icon-picker">
                                                <input type="hidden" name="work_type_icons[]" value="<?= htmlspecialchars(isset($workType['svg_icon']) ? (string) $workType['svg_icon'] : 'svg_icon/10318.svg', ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="button" class="btn btn-light task-project-icon-picker-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" <?= $canEdit ? '' : 'disabled' ?>>
                                                    <img src="<?= htmlspecialchars(isset($workType['svg_icon']) ? (string) $workType['svg_icon'] : 'svg_icon/10318.svg', ENT_QUOTES, 'UTF-8') ?>" alt="">
                                                </button>
                                                <div class="dropdown-menu task-project-icon-picker-menu"></div>
                                            </div>
                                            <?php if ($canEdit): ?>
                                                <button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="work_type" data-existing-id="<?= (int) $workType['id'] ?>">Remove</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Labels</h5>
                                <?php if ($isOwner): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addProjectLabelRowBtn">Add Label</button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div id="projectLabelDeleteBucket"></div>
                                <div id="projectLabelRows" class="d-flex flex-column gap-2">
                                    <?php foreach ($labelRows as $label): ?>
                                        <div class="task-project-settings-row task-project-label-row">
                                            <input type="hidden" name="label_ids[]" value="<?= (int) $label['id'] ?>">
                                            <input type="text" class="form-control" name="label_names[]" maxlength="120" value="<?= htmlspecialchars(isset($label['name']) ? (string) $label['name'] : '', ENT_QUOTES, 'UTF-8') ?>" <?= $isOwner ? '' : 'disabled' ?>>
                                            <div class="task-project-color-control">
                                                <input type="color" class="form-control form-control-color" name="label_colors[]" value="<?= htmlspecialchars(isset($label['color']) ? (string) $label['color'] : '#DCE8FF', ENT_QUOTES, 'UTF-8') ?>" data-default-color="<?= htmlspecialchars(isset($label['color']) ? (string) $label['color'] : '#DCE8FF', ENT_QUOTES, 'UTF-8') ?>" <?= $isOwner ? '' : 'disabled' ?>>
                                                <?php if ($isOwner): ?>
                                                    <button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="Reset this label color to default?" data-color-input="closest" data-default-color="<?= htmlspecialchars(isset($label['color']) ? (string) $label['color'] : '#DCE8FF', ENT_QUOTES, 'UTF-8') ?>">Reset Default</button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($isOwner): ?>
                                                <button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="label" data-existing-id="<?= (int) $label['id'] ?>">Remove</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Task Status Labels</h5>
                                <?php if ($isOwner): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addProjectStatusLabelRowBtn">Add Task Status</button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div id="projectStatusLabelDeleteBucket"></div>
                                <div id="projectStatusLabelRows" class="d-flex flex-column gap-2">
                                    <?php foreach ($statusLabelRows as $statusLabel): ?>
                                        <div class="task-project-settings-row task-project-status-label-row">
                                            <input type="hidden" name="status_label_ids[]" value="<?= (int) $statusLabel['id'] ?>">
                                            <input type="text" class="form-control" name="status_label_names[]" maxlength="120" value="<?= htmlspecialchars(isset($statusLabel['name']) ? (string) $statusLabel['name'] : '', ENT_QUOTES, 'UTF-8') ?>" <?= $isOwner ? '' : 'disabled' ?>>
                                            <div class="task-project-color-control">
                                                <input type="color" class="form-control form-control-color" name="status_label_colors[]" value="<?= htmlspecialchars(isset($statusLabel['color']) ? (string) $statusLabel['color'] : '#DCE8FF', ENT_QUOTES, 'UTF-8') ?>" data-default-color="<?= htmlspecialchars(isset($statusLabel['color']) ? (string) $statusLabel['color'] : '#DCE8FF', ENT_QUOTES, 'UTF-8') ?>" <?= $isOwner ? '' : 'disabled' ?>>
                                                <?php if ($isOwner): ?>
                                                    <button type="button" class="btn btn-outline-secondary task-project-reset-color-btn" data-confirm-text="Reset this task status color to default?" data-color-input="closest" data-default-color="<?= htmlspecialchars(isset($statusLabel['color']) ? (string) $statusLabel['color'] : '#DCE8FF', ENT_QUOTES, 'UTF-8') ?>">Reset Default</button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($isOwner): ?>
                                                <button type="button" class="btn btn-outline-danger task-project-row-remove-btn" data-delete-type="status_label" data-existing-id="<?= (int) $statusLabel['id'] ?>">Remove</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php taskRenderCreateProjectModal(); ?>

<script>
window.taskBoardConfig = {
    ajaxUrl: <?= json_encode('board.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProjectId: <?= (int) $currentProjectId ?>,
    currentProject: <?= json_encode($currentProject, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    canAdd: <?= $canEdit ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    isProjectOwner: <?= $isOwner ? 'true' : 'false' ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/task_board_core.js"></script>
<script>
window.projectSettingsConfig = {
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    canManageTaxonomy: <?= $isOwner ? 'true' : 'false' ?>,
    ajaxUrl: <?= json_encode('project_settings.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    iconOptions: <?= json_encode(array_values(array_map(function ($iconPath) {
        return array(
            'value' => (string) $iconPath,
            'src' => (string) ltrim((string) $iconPath, '/')
        );
    }, $workTypeIconOptions)), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/project_settings.js"></script>
</body>
</html>
