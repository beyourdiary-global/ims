<?php
$taskParentPin = 139;
$currentPagePin = $taskParentPin;
$pageTitlePin = 138;
$pageTitle = 'View My Task';
$taskParentTitle = 'Project Task';
$taskPermissionPin = $taskParentPin;

// Toolbar Refresh asks for the task table only; buffer the shared page chrome
// (menu header, etc.) so the response carries just that fragment.
$viewMyTaskAjax = isset($_GET['ajax']) && $_GET['ajax'] === 'table';
if ($viewMyTaskAjax) {
    ob_start();
}

include_once '../menuHeader.php';
include_once './common_task.php';
include_once './board_item_history.php';
$pageTitle = 'View My Task';
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
    renderNotificationScript('You do not have access to this project.', 'error', '../dashboard.php', 1200, true);
    exit;
}
$taskParentTitle = !empty($currentProject) && isset($currentProject['name']) && trim((string) $currentProject['name']) !== ''
    ? (string) $currentProject['name']
    : $taskParentTitle;

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

$boardPinAccess = taskGetPinAccessByGroupId($connect, $taskPermissionPin);
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
$workItemCanAdd = taskIsActionAllowed('add', $boardPinAccess) && !empty($projectAccessRecord['work_item_add']);
$workItemCanEdit = taskIsActionAllowed('edit', $boardPinAccess) && !empty($projectAccessRecord['work_item_edit']);
$workItemCanDelete = taskIsActionAllowed('delete', $boardPinAccess) && !empty($projectAccessRecord['work_item_delete']);
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
$workTypeIcons = taskGetSvgIconOptions();
$projectKeySetting = taskGetProjectKeySetting($connect, $currentProjectId);
$assignees = taskGetAssignees($connect);
$labels = taskGetLabels($connect);
$statusLabels = taskGetStatusLabels($connect);
$columns = taskGetColumns($connect, $currentProjectId);

$myTaskGroups = $currentProjectId > 0 ? taskGetMyTaskGroups($connect, $currentProjectId, $currentUserId) : array();
$totalMyTaskCount = 0;
foreach ($myTaskGroups as $group) {
    $totalMyTaskCount += isset($group['item_count']) ? (int) $group['item_count'] : 0;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($viewMyTaskAjax) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    include __DIR__ . '/view_my_task_content.php';
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/sheets.css">
    <link rel="stylesheet" href="../css/view_my_task.css?v=<?= (int) @filemtime(__DIR__ . '/../css/view_my_task.css') ?>">
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
                <?php taskRenderSidebarMenu($connect, $SITEURL, 'view_my_task', $currentProjectId); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div class="task-main-content" id="viewMyTaskContent">
                <?php include __DIR__ . '/view_my_task_content.php'; ?>
            </div>
        </section>
    </div>
</div>

<?php include_once './board_item_detail_modal.php'; ?>
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
    allowedWorkTypeIds: <?= json_encode(array_values($allowedWorkTypeIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    allowedStatusIds: <?= json_encode(array_values($allowedStatusIds), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columnPermissions: <?= json_encode($columnPermissions, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProject: <?= json_encode($currentProject, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypeIcons: <?= json_encode($workTypeIcons, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/task_board_core.js?v=<?= (int) @filemtime(__DIR__ . '/../js/task_board_core.js') ?>"></script>
<script src="../js/task_board_ui.js?v=<?= (int) @filemtime(__DIR__ . '/../js/task_board_ui.js') ?>"></script>
<script src="../js/task_board.js?v=<?= (int) @filemtime(__DIR__ . '/../js/task_board.js') ?>"></script>
<script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
<script src="../js/text_editor.js"></script>
<script src="../js/view_my_task.js?v=<?= (int) @filemtime(__DIR__ . '/../js/view_my_task.js') ?>"></script>
</body>
</html>
