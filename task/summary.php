<?php
$currentPagePin = 137;
$taskPageTitleByPin = array(
    137 => 'Summary',
);
$pageTitle = isset($taskPageTitleByPin[$currentPagePin]) ? $taskPageTitleByPin[$currentPagePin] : 'Project Task';
$taskParentTitle = 'Project Task';
$isFinance = 1;

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

/* ───── AJAX POST handler ───── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['summary_action'])) {
    include_once '../include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once './common_task.php';

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'Invalid session token.'));
        exit;
    }

    $pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
    if (!taskIsActionAllowed('view', $pinAccess)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'No permission.'));
        exit;
    }

    $action = trim((string) $_POST['summary_action']);
    $currentProjectId = taskResolveCurrentProjectId($connect, 0);
    if (!taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $currentPagePin)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'You do not have access to this project summary.'));
        exit;
    }

    $filters = array();
    if (!empty($_POST['filters_json'])) {
        $decodedFilters = json_decode((string) $_POST['filters_json'], true);
        if (is_array($decodedFilters)) {
            $filters = $decodedFilters;
        }
    }

    if ($action === 'get_activity') {
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $perPageRaw = isset($_POST['per_page']) ? trim((string) $_POST['per_page']) : '10';
        $maxPerPage = 1000;
        $perPage = strtolower($perPageRaw) === 'all' ? $maxPerPage : max(1, min($maxPerPage, (int) $perPageRaw));
        if (!empty($_POST['assignee_id'])) {
            $filters['assignee_id'] = (int) $_POST['assignee_id'];
        }
        if (isset($_POST['search']) && trim((string) $_POST['search']) !== '') {
            $filters['search'] = trim((string) $_POST['search']);
        }
        $result = taskGetGlobalActivity($connect, $page, $perPage, $filters, $currentProjectId);
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 1, 'data' => $result));
        exit;
    }

    if ($action === 'get_stats') {
        if (!empty($_POST['assignee_id'])) {
            $filters['assignee_id'] = (int) $_POST['assignee_id'];
        }
        $stats = taskGetSummaryStats($connect, $filters, $currentProjectId);
        $activity = taskGetGlobalActivity($connect, 1, 10, $filters, $currentProjectId);
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 1, 'stats' => $stats, 'activity' => $activity));
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(array('ok' => 0, 'message' => 'Unknown action.'));
    exit;
}

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once './common_task.php';
include_once './board_item_history.php';

$pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    echo "<script>alert('You do not have permission to view Project Task.'); location.replace('../dashboard.php');</script>";
    exit;
}

$currentProjectId = taskResolveCurrentProjectId($connect, 0);
$currentProject = $currentProjectId > 0 ? taskGetProjectById($connect, $currentProjectId) : array();
$taskParentTitle = !empty($currentProject) && isset($currentProject['name']) && trim((string) $currentProject['name']) !== ''
    ? (string) $currentProject['name']
    : 'Project Task';
if (!taskUserCanAccessProjectPageByPin($connect, $currentProjectId, $currentPagePin)) {
    echo "<script>alert('You do not have access to this project summary.'); location.replace('../dashboard.php');</script>";
    exit;
}

$safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
$safePageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
$safeProjectName = htmlspecialchars((string) $taskParentTitle, ENT_QUOTES, 'UTF-8');
$viewActMsg = $safeUserName . ' viewed the page ' . $safePageTitle . ' (<b>' . $safeProjectName . '</b>).';
taskBoardAuditLog($connect, $pageTitle, 'View', $viewActMsg, $cdate, $ctime);
$assignees = taskGetAssignees($connect);
$workTypes = taskGetWorkTypes($connect, $currentProjectId);
$statusLabels = taskGetStatusLabels($connect);
$columns = taskGetColumns($connect, $currentProjectId);
$parentOptions = taskGetEpicParentOptions($connect, 0, $currentProjectId);
$labels = taskGetLabels($connect);
$projectKeySetting = taskGetProjectKeySetting($connect, $currentProjectId);
$workTypeIcons = taskGetSvgIconOptions();
$boardPinAccess = taskGetPinAccessByGroupId($connect, 136);
$canEdit = taskIsActionAllowed('edit', $boardPinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'edit');
$canAdd = taskIsActionAllowed('add', $boardPinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'add');
$canDelete = taskIsActionAllowed('delete', $boardPinAccess) && taskUserCanWorkItemAction($connect, $currentProjectId, 'delete');
$isProjectOwner = taskIsProjectOwner($connect, $currentProjectId, USER_ID);
$hasFullProjectAccess = taskUserHasFullProjectTaskAccess($connect, $currentProjectId, USER_ID);
$allowedWorkTypeIds = taskUserAllowedWorkTypeIds($connect, $currentProjectId, USER_ID);
$allowedStatusIds = taskUserAllowedStatusIds($connect, $currentProjectId, USER_ID);
$columnPermissions = taskGetProjectColumnAccessMap($connect, $currentProjectId, USER_ID);
if (!$hasFullProjectAccess) {
    $workTypes = array_values(array_filter($workTypes, function ($workType) use ($allowedWorkTypeIds) {
        return isset($workType['id']) && in_array((int) $workType['id'], $allowedWorkTypeIds, true);
    }));
    $columns = array_values(array_filter($columns, function ($column) use ($allowedStatusIds) {
        return isset($column['id']) && in_array((int) $column['id'], $allowedStatusIds, true);
    }));
}
$currentUserId = USER_ID;
$currentUserName = USER_NAME;

// Initial data load
$stats = taskGetSummaryStats($connect, array(), $currentProjectId);
$activity = taskGetGlobalActivity($connect, 1, 10, array(), $currentProjectId);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/summary.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="container-fluid d-flex justify-content-center mt-3 task-page-wrap">
    <div class="col-12 col-md-11">
        <div id="taskBoardApp" class="d-none" aria-hidden="true"></div>
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
                <?php taskRenderSidebarMenu($connect, $SITEURL, 'summary', $currentProjectId); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div class="task-main-content">
                <div class="summary-filter-layer">
                    <!-- Filter bar -->
                    <div class="summary-filter-bar">
                        <button id="summaryFilterBtn" class="summary-filter-toggle-btn">
                            <i class="fa-solid fa-filter"></i> Filter
                        </button>
                        <div id="summaryActiveFilters" class="summary-active-filters"></div>
                    </div>

                    <!-- Filter dropdown panel -->
                    <div id="summaryFilterPanel" class="summary-filter-panel" style="display:none;">
                        <div class="summary-filter-panel-inner">
                            <div class="summary-filter-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="summaryFilterSearch" placeholder="Search more filters" autocomplete="off">
                            </div>
                            <ul class="summary-filter-options">
                                <li><label><input type="checkbox" value="assignee"> Assignee</label></li>
                                <li><label><input type="checkbox" value="created"> Created</label></li>
                                <li><label><input type="checkbox" value="due_date"> Due date</label></li>
                                <li><label><input type="checkbox" value="parent"> Parent</label></li>
                                <li><label><input type="checkbox" value="priority"> Priority</label></li>
                                <li><label><input type="checkbox" value="status"> Status</label></li>
                                <li><label><input type="checkbox" value="updated"> Updated</label></li>
                                <li><label><input type="checkbox" value="work_type"> Work type</label></li>
                            </ul>
                            <div class="summary-filter-panel-footer">
                                <span id="summaryFilterCount">8 of 8</span>
                            </div>
                        </div>
                    </div>

                    <!-- Filter sub-panels (shown when a specific filter chip is active) -->
                    <div id="summaryFilterSubPanel" class="summary-filter-sub-panel" style="display:none;"></div>
                </div>

                <!-- Stats cards -->
                <div class="summary-stats-row" id="summaryStatsRow">
                    <div class="summary-stat-card summary-stat-completed">
                        <div class="summary-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="summary-stat-body">
                            <div class="summary-stat-value" id="statCompleted"><?= $stats['completed_7d'] ?></div>
                            <div class="summary-stat-label">completed<br><small>in the last 7 days</small></div>
                        </div>
                    </div>
                    <div class="summary-stat-card summary-stat-updated">
                        <div class="summary-stat-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                        <div class="summary-stat-body">
                            <div class="summary-stat-value" id="statUpdated"><?= $stats['updated_7d'] ?></div>
                            <div class="summary-stat-label">updated<br><small>in the last 7 days</small></div>
                        </div>
                    </div>
                    <div class="summary-stat-card summary-stat-created">
                        <div class="summary-stat-icon"><i class="fa-solid fa-square-check"></i></div>
                        <div class="summary-stat-body">
                            <div class="summary-stat-value" id="statCreated"><?= $stats['created_7d'] ?></div>
                            <div class="summary-stat-label">created<br><small>in the last 7 days</small></div>
                        </div>
                    </div>
                    <div class="summary-stat-card summary-stat-due">
                        <div class="summary-stat-icon"><i class="fa-solid fa-calendar-days"></i></div>
                        <div class="summary-stat-body">
                            <div class="summary-stat-value" id="statDueSoon"><?= $stats['due_soon_7d'] ?></div>
                            <div class="summary-stat-label">due soon<br><small>in the next 7 days</small></div>
                        </div>
                    </div>
                </div>

                <!-- Main content grid -->
                <div class="summary-grid">
                    <!-- Status Overview -->
                    <div class="summary-card summary-status-card">
                        <div class="summary-card-header">
                            <h6>Status overview</h6>
                            <p class="summary-card-desc">Get a snapshot of the status of your work items.</p>
                        </div>
                        <div class="summary-card-body summary-status-body">
                            <div class="summary-pie-wrap">
                                <canvas id="statusPieChart" width="220" height="220"></canvas>
                                <div class="summary-pie-center" id="pieCenterLabel">
                                    <span class="summary-pie-total"><?= $stats['total_items'] ?></span>
                                    <span class="summary-pie-total-label">Total work item...</span>
                                </div>
                            </div>
                            <div class="summary-pie-legend" id="statusPieLegend"></div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="summary-card summary-activity-card">
                        <div class="summary-card-header">
                            <h6>Recent activity</h6>
                            <p class="summary-card-desc">Stay up to date with what's happening across the space.</p>
                            <button class="summary-activity-expand-btn" id="activityExpandBtn" title="Expand">
                                <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                            </button>
                        </div>
                        <div class="summary-card-body summary-activity-body" id="activityList">
                            <!-- rendered by JS -->
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </div>
</div>

<!-- Activity Expand Modal -->
<div class="modal fade" id="activityExpandModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable summary-activity-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">All Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="activityModalBody">
                <div class="summary-activity-modal-controls">
                    <div class="summary-activity-modal-size-wrap">
                        <label for="activityModalPageSize" class="summary-activity-modal-size-label">Show</label>
                        <select id="activityModalPageSize" class="summary-activity-modal-size-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    <div class="summary-activity-modal-search-wrap">
                        <input type="text" id="activityModalSearch" class="summary-activity-modal-search" placeholder="Search activity..." autocomplete="off">
                    </div>
                </div>
                <div class="summary-activity-body" id="activityModalList"></div>
            </div>
            <div class="modal-footer">
                <div class="summary-pagination" id="activityModalPagination"></div>
            </div>
        </div>
    </div>
</div>

<?php include_once './board_item_detail_modal.php'; ?>
<?php taskRenderCreateProjectModal(); ?>

<script>
window.taskBoardConfig = {
    ajaxUrl: <?= json_encode('board.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProjectId: <?= (int) $currentProjectId ?>,
    canAdd: <?= $canAdd ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    canDelete: <?= $canDelete ? 'true' : 'false' ?>,
    isProjectOwner: <?= $hasFullProjectAccess ? 'true' : 'false' ?>,
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

window.summaryConfig = {
    ajaxUrl: <?= json_encode('summary.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    currentProjectId: <?= (int) $currentProjectId ?>,
    currentUserId: <?= json_encode($currentUserId, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentUserName: <?= json_encode($currentUserName, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode(array_map(function($wt) { return array('id' => $wt['id'], 'name' => $wt['name'], 'svg_icon' => isset($wt['svg_icon']) ? $wt['svg_icon'] : ''); }, $workTypes), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    parentOptions: <?= json_encode($parentOptions, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    initialStats: <?= json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    initialActivity: <?= json_encode($activity, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    boardUrl: <?= json_encode('board.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/task_board_core.js"></script>
<script src="../js/task_board_ui.js"></script>
<script src="../js/task_board.js"></script>
<script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
<script src="../js/text_editor.js"></script>
<script src="../js/summary.js"></script>
</body>
</html>
