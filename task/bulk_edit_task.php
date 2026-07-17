<?php
$taskParentPin = 139;
$currentPagePin = $taskParentPin;
$pageTitlePin = $taskParentPin;
$pageTitle = 'Bulk Edit';
$taskParentTitle = 'Project Task';
$taskPermissionPin = $taskParentPin;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    include_once '../include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once './common_task.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $submittedToken = isset($_POST['csrf_token']) && !is_array($_POST['csrf_token'])
        ? (string) $_POST['csrf_token']
        : '';
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
    }

    $pinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
    if (!taskIsActionAllowed('view', $pinAccess)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to access bulk edit.'));
    }

    $projectId = isset($_POST['project_id']) && !is_array($_POST['project_id'])
        ? (int) $_POST['project_id']
        : 0;
    $currentUserId = defined('USER_ID') ? (int) USER_ID : 0;
    $project = $projectId > 0 ? taskGetProjectById($connect, $projectId) : array();
    if (empty($project) || !taskUserCanAccessProjectPageByPin($connect, $projectId, $taskPermissionPin, $currentUserId)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have access to this project.'));
    }

    $bulkAction = isset($_POST['bulk_action']) && !is_array($_POST['bulk_action'])
        ? strtolower(trim((string) $_POST['bulk_action']))
        : '';
    if ($bulkAction === 'load') {
        $parentItemId = isset($_POST['parent_item_id']) && !is_array($_POST['parent_item_id'])
            ? (int) $_POST['parent_item_id']
            : 0;
        $pageData = taskGetBulkChildPageData($connect, $projectId, $parentItemId);
        if (empty($pageData['ok'])) {
            taskJsonResponse($pageData);
        }
        taskJsonResponse(array(
            'ok' => 1,
            'parent' => isset($pageData['parent']) ? $pageData['parent'] : array(),
            'items' => isset($pageData['items']) ? $pageData['items'] : array(),
        ));
    }

    if ($bulkAction === 'apply') {
        $parentItemId = isset($_POST['parent_item_id']) && !is_array($_POST['parent_item_id'])
            ? (int) $_POST['parent_item_id']
            : 0;
        $operation = isset($_POST['operation']) && !is_array($_POST['operation'])
            ? strtolower(trim((string) $_POST['operation']))
            : '';
        $itemIds = isset($_POST['selected_item_ids']) ? $_POST['selected_item_ids'] : array();
        if (!is_array($itemIds)) {
            $itemIds = array($itemIds);
        }
        $changesJson = isset($_POST['changes_json']) && !is_array($_POST['changes_json'])
            ? (string) $_POST['changes_json']
            : '{}';
        $changes = json_decode($changesJson, true);
        if (!is_array($changes)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'Invalid bulk change details.'));
        }

        $operationRequiresEdit = in_array($operation, array('edit', 'move', 'transition'), true);
        if (($operationRequiresEdit && !taskIsActionAllowed('edit', $pinAccess))
            || ($operation === 'delete' && !taskIsActionAllowed('delete', $pinAccess))) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to perform this bulk operation.'));
        }

        $result = taskBulkApplyChildOperation(
            $connect,
            $projectId,
            $parentItemId,
            $itemIds,
            $operation,
            $changes,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    taskJsonResponse(array('ok' => 0, 'message' => 'Invalid bulk edit request.'));
}

include_once '../menuHeader.php';
include_once './common_task.php';

$pageTitle = taskGetPinGroupTitleById($connect, $pageTitlePin, $pageTitle);
$taskParentTitle = taskGetPinGroupTitleById($connect, $taskParentPin, $taskParentTitle);
$pinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    renderNotificationScript('You do not have permission to view bulk edit.', 'error', '../dashboard.php', 1200, true);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentUserId = defined('USER_ID') ? (int) USER_ID : 0;
$requestedProjectId = isset($_GET['project_id']) && !is_array($_GET['project_id'])
    ? (int) $_GET['project_id']
    : 0;
$currentProjectId = $requestedProjectId > 0 ? $requestedProjectId : taskResolveCurrentProjectId($connect, 0);
$currentProject = $currentProjectId > 0 ? taskGetProjectById($connect, $currentProjectId) : array();
if (empty($currentProject) || !taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $taskPermissionPin, $currentUserId)) {
    renderNotificationScript('You do not have access to this project bulk edit page.', 'error', '../dashboard.php', 1200, true);
    exit;
}

taskEnsureDefaultWorkTypes($connect, $currentProjectId, $currentUserId, $cdate, $ctime);

$canEdit = taskIsActionAllowed('edit', $pinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'edit', $currentUserId);
$canDelete = taskIsActionAllowed('delete', $pinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'delete', $currentUserId);
$hasFullProjectAccess = taskUserHasFullProjectTaskAccess($connect, $currentProjectId, $currentUserId);
$allowedWorkTypeIds = taskUserAllowedWorkTypeIds($connect, $currentProjectId, $currentUserId);
$allowedStatusIds = taskUserAllowedStatusIds($connect, $currentProjectId, $currentUserId);

$workTypes = taskGetWorkTypes($connect, $currentProjectId);
if (!$hasFullProjectAccess) {
    $workTypes = array_values(array_filter($workTypes, function ($workType) use ($allowedWorkTypeIds) {
        return isset($workType['id']) && in_array((int) $workType['id'], $allowedWorkTypeIds, true);
    }));
}
$workTypes = array_values(array_filter($workTypes, function ($workType) {
    return !taskIsParentWorkTypeName(isset($workType['name']) ? $workType['name'] : '');
}));

$allColumns = taskGetColumns($connect, $currentProjectId);
$columns = $allColumns;
if (!$hasFullProjectAccess) {
    $columns = array_values(array_filter($columns, function ($column) use ($allowedStatusIds) {
        return isset($column['id']) && in_array((int) $column['id'], $allowedStatusIds, true);
    }));
}

$assignees = taskGetAssignees($connect);
$labels = taskGetLabels($connect);
$statusLabels = taskGetStatusLabels($connect);
$parentOptions = taskGetEpicParentOptions($connect, 0, $currentProjectId);
$safeProjectName = htmlspecialchars(isset($currentProject['name']) ? (string) $currentProject['name'] : $taskParentTitle, ENT_QUOTES, 'UTF-8');
$safePageTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
$safeSiteUrl = htmlspecialchars(rtrim((string) $SITEURL, '/'), ENT_QUOTES, 'UTF-8');
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$bulkConfig = array(
    'ajaxUrl' => 'bulk_edit_task.php',
    'siteUrl' => rtrim((string) $SITEURL, '/'),
    'projectId' => $currentProjectId,
    'csrfToken' => (string) $_SESSION['csrf_token'],
    'canEdit' => $canEdit ? 1 : 0,
    'canDelete' => $canDelete ? 1 : 0,
    'workTypes' => $workTypes,
    'columns' => $columns,
    'assignees' => $assignees,
    'labels' => $labels,
    'statusLabels' => $statusLabels,
    'parentOptions' => $parentOptions,
    'allColumns' => $allColumns,
    'workflowColumns' => $columns,
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $safePageTitle ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/task_bulk_edit.css">
</head>
<body class="task-bulk-edit-page">
<main class="task-bulk-edit-wrap">
    <div class="task-bulk-edit-breadcrumb">
        <a href="<?= $safeSiteUrl ?>/dashboard.php">Dashboard</a>
        <i class="fa-solid fa-chevron-right fa-xs"></i>
        <span><?= $safeProjectName ?></span>
        <i class="fa-solid fa-chevron-right fa-xs"></i>
        <span><?= $safePageTitle ?></span>
    </div>

    <div class="task-bulk-edit-heading">
        <div>
            <h1>Bulk Edit Child Work Items</h1>
            <p>Project: <?= $safeProjectName ?></p>
        </div>
        <a class="btn btn-light" href="<?= $safeSiteUrl ?>/task/board.php?project_id=<?= (int) $currentProjectId ?>">Back to Board</a>
    </div>

    <div id="taskBulkEditAlert" class="task-bulk-edit-alert d-none" role="alert"></div>

    <div class="task-bulk-edit-layout">
        <aside class="task-bulk-edit-stepper" aria-label="Bulk edit steps">
            <button type="button" class="task-bulk-step is-active" data-bulk-step-link="1">
                <span class="task-bulk-step-dot">1</span><span>Choose work items</span>
            </button>
            <button type="button" class="task-bulk-step" data-bulk-step-link="2">
                <span class="task-bulk-step-dot">2</span><span>Choose operation</span>
            </button>
            <button type="button" class="task-bulk-step" data-bulk-step-link="3">
                <span class="task-bulk-step-dot">3</span><span>Operation details</span>
            </button>
            <button type="button" class="task-bulk-step" data-bulk-step-link="4">
                <span class="task-bulk-step-dot">4</span><span>Confirmation</span>
            </button>
        </aside>

        <section class="task-bulk-edit-content">
            <section class="task-bulk-panel" data-bulk-step="1">
                <div class="task-bulk-panel-heading">
                    <div>
                        <h2>Step 1 of 4: Choose work items</h2>
                        <p id="taskBulkParentSummary">Loading child work items...</p>
                    </div>
                    <span class="task-bulk-limit-note"><i class="fa-solid fa-circle-info"></i> Maximum 1,000 selected</span>
                </div>
                <div class="task-bulk-selection-toolbar">
                    <label class="task-bulk-checkbox-label"><input id="taskBulkSelectAll" type="checkbox"> Select all</label>
                    <span id="taskBulkSelectionCount">0 selected</span>
                </div>
                <div class="task-bulk-table-wrap">
                    <table class="table task-bulk-table" id="taskBulkItemsTable">
                        <thead>
                        <tr>
                            <th class="task-bulk-check-cell"></th>
                            <th>Key</th>
                            <th>Summary</th>
                            <th>Assignee</th>
                            <th>Reporter</th>
                            <th>Priority</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody><tr><td colspan="7" class="task-bulk-empty">Loading...</td></tr></tbody>
                    </table>
                </div>
                <div class="task-bulk-actions"><button type="button" class="btn btn-primary" data-bulk-next="1">Next</button></div>
            </section>

            <section class="task-bulk-panel d-none" data-bulk-step="2">
                <div class="task-bulk-panel-heading">
                    <div><h2>Step 2 of 4: Choose operation</h2><p>Choose one action for the selected child work items.</p></div>
                </div>
                <div class="task-bulk-operation-list">
                    <label class="task-bulk-operation-card <?= $canEdit ? '' : 'is-disabled' ?>">
                        <input type="radio" name="bulk_operation" value="edit" <?= $canEdit ? '' : 'disabled' ?>>
                        <span><strong>Edit</strong><small>Edit field values on the selected work items.</small></span>
                    </label>
                    <label class="task-bulk-operation-card <?= $canEdit ? '' : 'is-disabled' ?>">
                        <input type="radio" name="bulk_operation" value="move" <?= $canEdit ? '' : 'disabled' ?>>
                        <span><strong>Move</strong><small>Change the work type and/or parent in this project.</small></span>
                    </label>
                    <label class="task-bulk-operation-card <?= $canEdit ? '' : 'is-disabled' ?>">
                        <input type="radio" name="bulk_operation" value="transition" <?= $canEdit ? '' : 'disabled' ?>>
                        <span><strong>Transitions</strong><small>Move the selected work items to another status column.</small></span>
                    </label>
                    <label class="task-bulk-operation-card <?= $canDelete ? '' : 'is-disabled' ?>">
                        <input type="radio" name="bulk_operation" value="delete" <?= $canDelete ? '' : 'disabled' ?>>
                        <span><strong>Delete</strong><small>Soft-delete the selected work items.</small></span>
                    </label>
                </div>
                <div class="task-bulk-actions"><button type="button" class="btn btn-light" data-bulk-back="2">Back</button><button type="button" class="btn btn-primary" data-bulk-next="2">Next</button></div>
            </section>

            <section class="task-bulk-panel d-none" data-bulk-step="3">
                <div class="task-bulk-panel-heading"><div><h2>Step 3 of 4: Operation details</h2><p id="taskBulkDetailsIntro">Configure the selected operation.</p></div></div>
                <div id="taskBulkOperationDetails"></div>
                <div class="task-bulk-actions"><button type="button" class="btn btn-light" data-bulk-back="3">Back</button><button type="button" class="btn btn-primary" data-bulk-next="3">Next</button></div>
            </section>

            <section class="task-bulk-panel d-none" data-bulk-step="4">
                <div class="task-bulk-panel-heading"><div><h2>Step 4 of 4: Confirmation</h2><p>Review the changes before they are applied. The server will reload and validate every selected item.</p></div></div>
                <div id="taskBulkSummary"></div>
                <div class="task-bulk-confirm-note"><i class="fa-solid fa-triangle-exclamation"></i><span>This operation is applied to all selected child work items in one transaction. If any item fails validation, no changes will be saved.</span></div>
                <div class="task-bulk-actions"><button type="button" class="btn btn-light" data-bulk-back="4">Back</button><button type="button" id="taskBulkConfirmBtn" class="btn btn-primary">Confirm</button></div>
            </section>
        </section>
    </div>
</main>

<script>
window.taskBulkEditConfig = <?= json_encode($bulkConfig, $jsonFlags) ?>;
</script>
<script src="../js/task_bulk_edit.js"></script>
</body>
</html>
