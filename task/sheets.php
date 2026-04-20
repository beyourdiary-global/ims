<?php
$currentPagePin = 138;
$taskPageTitleByPin = array(
    138 => 'Task Management Sheets',
);
$pageTitle = isset($taskPageTitleByPin[$currentPagePin]) ? $taskPageTitleByPin[$currentPagePin] : 'Task Management';
$isFinance = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action'])) {
    include_once '../include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once '../common_task.php';

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
        exit;
    }

    $pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
    if (!taskIsActionAllowed('view', $pinAccess)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'You do not have permission to access task management.'));
        exit;
    }

    $currentUserId = (int) USER_ID;
    $taskAction    = trim((string) $_POST['task_action']);
    $safeUserName  = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');

    if ($taskAction === 'sheets_get_data') {
        $items = taskGetAllItemsFlat($connect);
        $itemsByColumn = taskGetItemsGroupedByColumn($connect);
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
        $prevCols = taskGetSheetsColumns($connect, $currentUserId);
        $prevKeys = array_column($prevCols, 'column_key');

        $columnsJson = isset($_POST['columns_json']) ? $_POST['columns_json'] : '[]';
        $newCols     = taskSaveSheetsColumns($connect, $currentUserId, $columnsJson);
        $newKeys     = array_column($newCols, 'column_key');

        $prevKeyCounts = array_count_values($prevKeys);
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

    header('Content-Type: application/json');
    echo json_encode(array('ok' => 0, 'message' => 'Invalid task action.'));
    exit;
}

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once '../common_task.php';
include_once './board_item_history.php';

$pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    echo "<script>alert('You do not have permission to view task management.'); location.replace('../dashboard.php');</script>";
    exit;
}

$currentUserId = USER_ID;
taskEnsureDefaultWorkTypes($connect, $currentUserId, $cdate, $ctime);

$safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
$safePageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
$viewActMsg = $safeUserName . ' viewed the page ' . $safePageTitle . '.';
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

$canEdit = taskIsActionAllowed('edit', $pinAccess);
$canAdd = taskIsActionAllowed('add', $pinAccess);
$workTypes = taskGetWorkTypes($connect);
$workTypeIcons = taskGetSvgIconOptions();
$projectKeySetting = taskGetProjectKeySetting($connect);
$assignees = taskGetAssignees($connect);
$labels = taskGetLabels($connect);
$statusLabels = taskGetStatusLabels($connect);
$columns = taskGetColumns($connect);
$allItems = taskGetAllItemsFlat($connect);
$itemsByColumn = taskGetItemsGroupedByColumn($connect);
$sheetsColumns = taskGetSheetsColumns($connect, $currentUserId);

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
</head>
<body>
<div class="container-fluid d-flex justify-content-center mt-3 task-page-wrap">
    <div id="taskBoardApp" class="d-none" aria-hidden="true"></div>
    <div class="col-12 col-md-11">
        <div class="d-flex flex-column mb-3">
            <div class="row">
                <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Task Management Sheets</p>
            </div>
            <div class="row">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                    <h2>Task Management Sheets</h2>
                </div>
            </div>
        </div>

        <section id="taskModuleLayout" class="task-module-layout task-sidebar-open">
            <aside class="task-module-sidebar" id="taskModuleSidebar">
                <h5 class="mb-2">Task Management</h5>
                <?php taskRenderSidebarMenu($SITEURL, 'sheets'); ?>
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

<div id="taskBoardToastHost" class="task-board-toast-host" aria-live="polite" aria-atomic="true"></div>

<script>
window.taskBoardConfig = {
    ajaxUrl: 'board.php',
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    canAdd: <?= $canAdd ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypeIcons: <?= json_encode($workTypeIcons, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};

window.sheetsConfig = {
    ajaxUrl: 'board.php',
    sheetsDataAjaxUrl: 'sheets.php',
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypesForJs, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    items: <?= json_encode($allItems, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    itemsByColumn: <?= json_encode($itemsByColumn, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    sheetsColumns: <?= json_encode($sheetsColumns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    sheetsColumnAjaxUrl: 'sheets.php'
};
</script>
<script src="../js/task_board_core.js"></script>
<script src="../js/task_board_ui.js"></script>
<script src="../js/task_board.js"></script>
<script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
<script src="../js/text_editor.js"></script>
<script src="../js/sheets.js"></script>
</body>
</html>
