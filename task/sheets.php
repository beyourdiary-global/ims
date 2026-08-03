<?php
$taskParentPin = 139;
$currentPagePin = $taskParentPin;
$pageTitlePin = 138;
$pageTitle = 'Sheets';
$taskParentTitle = 'Project Task';
$taskPermissionPin = $taskParentPin;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('task_action') !== '') {
    include_once '../include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once './common_task.php';
    $pageTitle = taskGetPinGroupTitleById($connect, $pageTitlePin, $pageTitle);
    $taskParentTitle = taskGetPinGroupTitleById($connect, $taskParentPin, $taskParentTitle);

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $submittedToken = post('csrf_token');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
        exit;
    }

    $pinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
    if (!taskIsActionAllowed('view', $pinAccess)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to access Project Task.'));
        exit;
    }

    $currentUserId = (int) USER_ID;
    $currentProjectId = taskResolveCurrentProjectId($connect, 0);
    if (!taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $taskPermissionPin)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'You do not have access to this project sheets.'));
        exit;
    }
    $taskAction    = trim((string) post('task_action'));
    $safeUserName  = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');

    if ($taskAction === 'sheets_get_data') {
        $items = taskGetAllItemsFlat($connect, $currentProjectId);
        $itemsByColumn = taskGetItemsGroupedByColumn($connect, $currentProjectId);
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 1, 'items' => $items, 'itemsByColumn' => $itemsByColumn));
        exit;
    }

    if ($taskAction === 'sheets_save_columns') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to edit columns.'));
            exit;
        }

        $columnLabels = array(
            'work_type'                      => 'Work Type',
            'work_item_key'                  => 'Key',
            'title'                          => 'Title',
            'description'                    => 'Description',
            'board_status'                   => 'Board Status',
            'original_estimate'              => 'Original Estimate',
            'task_status'                    => 'Status',
            'parent_display'                 => 'Parent',
            'assignee_name'                  => 'Assignee',
            'reporter_name'                  => 'Reporter',
            'priority'                       => 'Priority',
            'labels'                         => 'Labels',
            'time_tracking'                  => 'Time Tracking',
            'start_date'                     => 'Start Date',
            'due_date'                       => 'Due Date',
            'amendement_date'                => 'First Amendment Date',
            'amendement_time_minutes'        => 'First Amendment Time',
            'second_amendement_date'         => 'Second Amendment Date',
            'second_amendement_time_minutes' => 'Second Amendment Time',
        );

        // Capture previous columns before saving
        $prevCols = taskGetSheetsColumns($connect, $currentUserId, $currentProjectId);
        $prevKeys = array_column($prevCols, 'column_key');

        $columnsJson = filter_has_var(INPUT_POST, 'columns_json') ? post('columns_json') : '[]';
        $requestedCols = json_decode($columnsJson, true);
        if (!is_array($requestedCols)) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'Invalid columns data.'));
            exit;
        }

        $allowedFieldMap = taskGetProjectAccessFieldKeyMap();
        $isProjectOwner = taskIsProjectOwner($connect, $currentProjectId, $currentUserId);
        $normalizedRequestedCols = array();
        foreach ($requestedCols as $idx => $col) {
            $columnKey = isset($col['column_key']) ? strtolower(trim((string) $col['column_key'])) : '';
            if ($columnKey === '' || !isset($allowedFieldMap[$columnKey])) {
                header('Content-Type: application/json');
                echo json_encode(array('ok' => 0, 'message' => 'Invalid sheets column selection.'));
                exit;
            }
            $normalizedRequestedCols[] = array(
                'column_key' => $columnKey,
                'sort_order' => isset($col['sort_order']) ? (int) $col['sort_order'] : $idx,
            );
        }
        $columnsJson = json_encode($normalizedRequestedCols);
        $requestedKeys = array_column($normalizedRequestedCols, 'column_key');

        $prevKeyCounts = array_count_values($prevKeys);
        $requestedKeyCounts  = array_count_values($requestedKeys);
        $allKeySet     = array_unique(array_merge(array_keys($prevKeyCounts), array_keys($requestedKeyCounts)));

        $hasAdditions = false;
        $hasRemovals = false;
        foreach ($allKeySet as $key) {
            $before = isset($prevKeyCounts[$key]) ? (int) $prevKeyCounts[$key] : 0;
            $after  = isset($requestedKeyCounts[$key]) ? (int) $requestedKeyCounts[$key] : 0;
            if ($after > $before) {
                $hasAdditions = true;
            } elseif ($after < $before) {
                $hasRemovals = true;
            }
        }

        if ($hasAdditions && !taskUserCanColumnAction($connect, $currentProjectId, 'add')) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to add sheets columns in this project.'));
            exit;
        }
        if ($hasAdditions && !$isProjectOwner) {
            foreach ($requestedKeys as $columnKey) {
                $before = isset($prevKeyCounts[$columnKey]) ? (int) $prevKeyCounts[$columnKey] : 0;
                $after = isset($requestedKeyCounts[$columnKey]) ? (int) $requestedKeyCounts[$columnKey] : 0;
                if ($after > $before && !taskUserCanColumnFieldAction($connect, $currentProjectId, $columnKey, 'add', $currentUserId)) {
                    header('Content-Type: application/json');
                    echo json_encode(array('ok' => 0, 'message' => 'You do not have access to add the selected column field.'));
                    exit;
                }
            }
        }
        if ($hasRemovals && !taskUserCanColumnAction($connect, $currentProjectId, 'delete')) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to remove sheets columns in this project.'));
            exit;
        }
        if ($hasRemovals && !$isProjectOwner) {
            foreach ($prevKeys as $columnKey) {
                $before = isset($prevKeyCounts[$columnKey]) ? (int) $prevKeyCounts[$columnKey] : 0;
                $after = isset($requestedKeyCounts[$columnKey]) ? (int) $requestedKeyCounts[$columnKey] : 0;
                if ($after < $before && !taskUserCanColumnFieldAction($connect, $currentProjectId, $columnKey, 'delete', $currentUserId)) {
                    header('Content-Type: application/json');
                    echo json_encode(array('ok' => 0, 'message' => 'You do not have access to remove the selected column field.'));
                    exit;
                }
            }
        }
        if (!$hasAdditions && !$hasRemovals && implode(',', $prevKeys) !== implode(',', $requestedKeys) && !taskUserCanColumnAction($connect, $currentProjectId, 'edit')) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to reorder sheets columns in this project.'));
            exit;
        }
        if (!$hasAdditions && !$hasRemovals && implode(',', $prevKeys) !== implode(',', $requestedKeys) && !$isProjectOwner) {
            foreach (array_unique($requestedKeys) as $columnKey) {
                if (!taskUserCanColumnFieldAction($connect, $currentProjectId, $columnKey, 'edit', $currentUserId)) {
                    header('Content-Type: application/json');
                    echo json_encode(array('ok' => 0, 'message' => 'You do not have access to edit the selected column field.'));
                    exit;
                }
            }
        }

        $newCols     = taskSaveSheetsColumns($connect, $currentUserId, $currentProjectId, $columnsJson);
        $newKeys     = array_column($newCols, 'column_key');

        $newKeyCounts  = array_count_values($newKeys);
        $allKeySet     = array_unique(array_merge(array_keys($prevKeyCounts), array_keys($newKeyCounts)));

        $addedCounts = array();
        $removedCounts = array();
        foreach ($allKeySet as $key) {
            $before = isset($prevKeyCounts[$key]) ? (int) $prevKeyCounts[$key] : 0;
            $after  = isset($newKeyCounts[$key]) ? (int) $newKeyCounts[$key] : 0;
            $delta  = $after - $before;

            if ($delta > 0) {
                $addedCounts[$key] = $delta;
            } elseif ($delta < 0) {
                $removedCounts[$key] = abs($delta);
            }
        }

        if (!empty($addedCounts) || !empty($removedCounts)) {
            $labelName = function ($key) use ($columnLabels) {
                return isset($columnLabels[$key]) ? $columnLabels[$key] : htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
            };

            $addedLabels = array();
            foreach ($addedCounts as $key => $count) {
                $label = '<b>' . $labelName($key) . '</b>';
                if ($count > 1) {
                    $label .= ' (x' . $count . ')';
                }
                $addedLabels[] = $label;
            }

            $removedLabels = array();
            foreach ($removedCounts as $key => $count) {
                $label = '<b>' . $labelName($key) . '</b>';
                if ($count > 1) {
                    $label .= ' (x' . $count . ')';
                }
                $removedLabels[] = $label;
            }

            $parts = array();
            if (!empty($addedLabels)) {
                $parts[] = 'added column ' . implode(', ', $addedLabels);
            }
            if (!empty($removedLabels)) {
                $parts[] = 'removed column ' . implode(', ', $removedLabels);
            }
            $actMsg = $safeUserName . ' ' . implode(' and ', $parts) . ' on <b>' . htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') . '</b>.';

            if (function_exists('audit_log') && $currentUserId > 0) {
                $logAction = 'Edit';
                if (!empty($addedLabels) && empty($removedLabels)) {
                    $logAction = 'Add';
                } elseif (empty($addedLabels) && !empty($removedLabels)) {
                    $logAction = 'Delete';
                }

                $logDate = isset($cdate) && trim((string) $cdate) !== '' ? $cdate : date('Y-m-d');
                $logTime = isset($ctime) && trim((string) $ctime) !== '' ? $ctime : date('H:i:s');

                $log = [
                    'log_act' => $logAction,
                    'cdate'   => $logDate,
                    'ctime'   => $logTime,
                    'uid'     => $currentUserId,
                    'cby'     => $currentUserId,
                    'act_msg' => $actMsg,
                    'page'    => $pageTitle,
                    'connect' => $connect,
                ];
                audit_log($log);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(array('ok' => 1, 'sheetsColumns' => $newCols));
        exit;
    }

    if ($taskAction === 'sheets_bulk_apply') {
        $operation = strtolower(trim((string) post('operation')));
        $requiredAction = $operation === 'delete' ? 'delete' : 'edit';
        if (!taskIsActionAllowed($requiredAction, $pinAccess)) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to perform this bulk operation.'));
            exit;
        }

        $itemIds = filter_has_var(INPUT_POST, 'selected_item_ids') ? $_POST['selected_item_ids'] : array();
        if (!is_array($itemIds)) {
            $itemIds = array($itemIds);
        }
        $changesJson = post('changes_json') !== '' ? post('changes_json') : '{}';
        $changes = json_decode($changesJson, true);
        if (!is_array($changes)) {
            header('Content-Type: application/json');
            echo json_encode(array('ok' => 0, 'message' => 'Invalid bulk change details.'));
            exit;
        }

        $result = taskBulkApplyChildOperation($connect, $currentProjectId, 0, $itemIds, $operation, $changes, $currentUserId, $cdate, $ctime);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(array('ok' => 0, 'message' => 'Invalid task action.'));
    exit;
}

include_once '../menuHeader.php';
include_once './common_task.php';
include_once './board_item_history.php';
$pageTitle = taskGetPinGroupTitleById($connect, $pageTitlePin, $pageTitle);
$taskParentTitle = taskGetPinGroupTitleById($connect, $taskParentPin, $taskParentTitle);

$pinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    renderNotificationScript('You do not have permission to view Project Task.', 'error', '../dashboard.php', 1200, true);
    exit;
}

$currentUserId = USER_ID;
$currentProjectId = taskResolveCurrentProjectId($connect, 0);
$currentProject = $currentProjectId > 0 ? taskGetProjectById($connect, $currentProjectId) : array();
if (!taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $taskPermissionPin)) {
    renderNotificationScript('You do not have access to this project sheets.', 'error', '../dashboard.php', 1200, true);
    exit;
}
$taskParentTitle = !empty($currentProject) && isset($currentProject['name']) && trim((string) $currentProject['name']) !== ''
    ? (string) $currentProject['name']
    : $taskParentTitle;
if ($currentProjectId > 0) {
    taskEnsureDefaultWorkTypes($connect, $currentProjectId, $currentUserId, $cdate, $ctime);
}

$safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
$safePageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
$safeProjectName = htmlspecialchars((string) $taskParentTitle, ENT_QUOTES, 'UTF-8');
$viewActMsg = $safeUserName . ' viewed the page ' . $safePageTitle . ' (<b>' . $safeProjectName . '</b>).';
if (function_exists('audit_log')) {
    $log = [
        'log_act' => 'View',
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

$canEdit = taskIsActionAllowed('edit', $pinAccess) && taskUserCanColumnAction($connect, $currentProjectId, 'edit');
$canAdd = taskIsActionAllowed('add', $pinAccess) && taskUserCanColumnAction($connect, $currentProjectId, 'add');
$boardPinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
$workItemCanAdd = taskIsActionAllowed('add', $boardPinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'add');
$workItemCanEdit = taskIsActionAllowed('edit', $boardPinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'edit');
$workItemCanDelete = taskIsActionAllowed('delete', $boardPinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'delete');
$isProjectOwner = taskIsProjectOwner($connect, $currentProjectId, $currentUserId);
$hasFullProjectAccess = taskUserHasFullProjectTaskAccess($connect, $currentProjectId, $currentUserId);
$allowedWorkTypeIds = taskUserAllowedWorkTypeIds($connect, $currentProjectId, $currentUserId);
$allowedStatusIds = taskUserAllowedStatusIds($connect, $currentProjectId, $currentUserId);
$columnPermissions = taskGetProjectColumnAccessMap($connect, $currentProjectId, $currentUserId);
$workTypes = taskGetWorkTypes($connect, $currentProjectId);
$workTypeIcons = taskGetSvgIconOptions();
$projectKeySetting = taskGetProjectKeySetting($connect, $currentProjectId);
$assignees = taskGetAssignees($connect);
$labels = taskGetLabels($connect);
$statusLabels = taskGetStatusLabels($connect);
$columns = taskGetColumns($connect, $currentProjectId);
$allColumnsForBulk = $columns;
$parentOptionsForBulk = taskGetEpicParentOptions($connect, 0, $currentProjectId);
$allItems = taskGetAllItemsFlat($connect, $currentProjectId);
$itemsByColumn = taskGetItemsGroupedByColumn($connect, $currentProjectId);
$sheetsColumns = taskGetSheetsColumns($connect, $currentUserId, $currentProjectId);

if (!$hasFullProjectAccess) {
    $workTypes = array_values(array_filter($workTypes, function ($workType) use ($allowedWorkTypeIds) {
        return isset($workType['id']) && in_array((int) $workType['id'], $allowedWorkTypeIds, true);
    }));
    $columns = array_values(array_filter($columns, function ($column) use ($allowedStatusIds) {
        return isset($column['id']) && in_array((int) $column['id'], $allowedStatusIds, true);
    }));
}

// Work types for the bulk-edit wizard's Move operation: exclude parent/Epic work types,
// since a work item being bulk-edited (child-level) cannot be retyped as a parent.
$workTypesForBulk = array_values(array_filter($workTypes, function ($workType) {
    return !taskIsParentWorkTypeName(isset($workType['name']) ? $workType['name'] : '');
}));

// Work types for JS (keep svg_icon as file path for <img> rendering)
$workTypesForJs = array();
foreach ($workTypes as $wt) {
    $workTypesForJs[] = array(
        'id' => $wt['id'],
        'name' => $wt['name'],
        'svg_icon' => isset($wt['svg_icon']) ? $wt['svg_icon'] : '',
    );
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/sheets.css">
    <link rel="stylesheet" href="../css/task_bulk_edit.css">
</head>
<body>
<div class="container-fluid d-flex justify-content-center mt-3 task-page-wrap">
    <div id="taskBoardApp" class="d-none" aria-hidden="true"></div>
    <div class="col-12 col-md-11">
        <div class="d-flex flex-column mb-3">
            <div class="row">
                <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($taskParentTitle, ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="row">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                    <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
            </div>
        </div>

        <section id="taskModuleLayout" class="task-module-layout task-sidebar-open">
            <aside class="task-module-sidebar" id="taskModuleSidebar">
                <h5 class="mb-2">Project Task</h5>
                <?php taskRenderSidebarMenu($connect, $SITEURL, 'sheets', $currentProjectId); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div class="task-main-content">
                <!-- Toolbar -->
                <div class="sheets-toolbar">
                    <div class="sheets-toolbar-left">
                        <span class="sheets-toolbar-info"><?= count($allItems) ?> work items</span>
                        <div class="sheets-assignee-filter-wrap" style="position:relative;display:inline-block;margin-left:8px;">
                            <select id="sheetsAssigneeFilter" class="sheets-assignee-filter-select">
                                <option value="">All Assignees</option>
                                <option value="__unassigned__">Unassigned</option>
                                <?php foreach ($assignees as $a): ?>
                                    <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="sheets-toolbar-right">
                        <?php if ($workItemCanEdit || $workItemCanDelete): ?>
                        <button class="sheets-toolbar-btn sheets-btn-bulk-edit" id="sheetsBulkEditBtn" type="button" title="Bulk Edit" disabled>
                            <i class="fa-solid fa-list-check"></i> Bulk Edit
                        </button>
                        <?php endif; ?>
                        <button class="sheets-toolbar-btn sheets-btn-collapse" title="Collapse/Expand groups">
                            <i class="fa-solid fa-arrows-up-down"></i>
                        </button>
                        <button class="sheets-toolbar-btn sheets-btn-refresh" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                        <button class="sheets-toolbar-btn sheets-btn-sum" title="Sum up">
                            <i class="fa-solid fa-calculator"></i>
                        </button>
                        <div style="position:relative;display:inline-block">
                            <button class="sheets-toolbar-btn sheets-btn-group" title="Grouping">
                                <i class="fa-solid fa-layer-group"></i>
                            </button>
                        </div>
                        <div class="sheets-search-wrap">
                            <button class="sheets-toolbar-btn sheets-btn-search" title="Search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                            <input type="text" class="sheets-search-input" placeholder="Search...">
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="sheets-table-wrap" id="sheetsTableWrap">
                    <!-- rendered by JS -->
                </div>
            </div>
        </section>
    </div>
</div>

<?php include_once './board_item_detail_modal.php'; ?>
<?php if ($workItemCanEdit || $workItemCanDelete): ?>
<?php include_once './sheets_bulk_edit_modal.php'; ?>
<?php endif; ?>
<?php taskRenderCreateProjectModal(); ?>

<div id="taskBoardToastHost" class="task-board-toast-host" aria-live="polite" aria-atomic="true"></div>

<script>
window.taskBoardConfig = {
    ajaxUrl: <?= json_encode('board.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    enableTaskItemHashUrl: true,
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProjectId: <?= (int) $currentProjectId ?>,
    canAdd: <?= $workItemCanAdd ? 'true' : 'false' ?>,
    canEdit: <?= $workItemCanEdit ? 'true' : 'false' ?>,
    canDelete: <?= $workItemCanDelete ? 'true' : 'false' ?>,
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
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};

window.sheetsConfig = {
    ajaxUrl: <?= json_encode('board.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    sheetsDataAjaxUrl: <?= json_encode('sheets.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    canEdit: <?= $workItemCanEdit ? 'true' : 'false' ?>,
    canAdd: <?= $canAdd ? 'true' : 'false' ?>,
    currentProjectId: <?= (int) $currentProjectId ?>,
    isProjectOwner: <?= $isProjectOwner ? 'true' : 'false' ?>,
    allowedWorkTypeIds: <?= json_encode(array_values($allowedWorkTypeIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    allowedStatusIds: <?= json_encode(array_values($allowedStatusIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columnPermissions: <?= json_encode($columnPermissions, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypesForJs, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    items: <?= json_encode($allItems, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    itemsByColumn: <?= json_encode($itemsByColumn, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    sheetsColumns: <?= json_encode($sheetsColumns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    sheetsColumnAjaxUrl: <?= json_encode('sheets.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/task_board_core.js"></script>
<script src="../js/task_board_ui.js"></script>
<script src="../js/task_board.js"></script>
<script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
<script src="../js/text_editor.js"></script>
<script src="../js/sheets.js"></script>
<?php if ($workItemCanEdit || $workItemCanDelete): ?>
<script src="../js/task_bulk_edit.js"></script>
<?php endif; ?>
</body>
</html>
