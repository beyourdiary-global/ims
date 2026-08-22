<?php
$taskParentPin = 139;
$currentPagePin = $taskParentPin;
$pageTitlePin = 138;
$pageTitle = 'View My Task';
$taskParentTitle = 'Project Task';
$taskPermissionPin = $taskParentPin;

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
$isProjectOwner = taskIsProjectOwner($connect, $currentProjectId, $currentUserId);
$workItemCanAdd = taskIsActionAllowed('add', $boardPinAccess) && ($isProjectOwner || taskUserCanWorkItemAction($connect, $currentProjectId, 'add'));
$workItemCanEdit = taskIsActionAllowed('edit', $boardPinAccess) && ($isProjectOwner || taskUserCanWorkItemAction($connect, $currentProjectId, 'edit'));
$workItemCanDelete = taskIsActionAllowed('delete', $boardPinAccess) && ($isProjectOwner || taskUserCanWorkItemAction($connect, $currentProjectId, 'delete'));
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

            <div class="task-main-content">
                <?php if ($currentProjectId <= 0): ?>
                    <div class="task-empty-board-note">No project task found yet.</div>
                <?php elseif (empty($myTaskGroups)): ?>
                    <div class="task-empty-board-note">No work items are assigned to you in this project.</div>
                <?php else: ?>
                    <div class="view-my-task-toolbar">
                        <span class="view-my-task-count"><?= (int) $totalMyTaskCount ?> work item<?= $totalMyTaskCount === 1 ? '' : 's' ?> assigned to you</span>
                        <div class="view-my-task-toolbar-right">
                            <button class="sheets-toolbar-btn sheets-btn-reset-filter" id="viewMyTaskResetFilterBtn" type="button" title="Reset all column filters" style="display:none;">
                                <i class="fa-solid fa-filter-circle-xmark"></i> Reset Filter
                            </button>
                            <button class="sheets-toolbar-btn" id="viewMyTaskRefreshBtn" type="button" title="Refresh">
                                <i class="fa-solid fa-arrows-rotate"></i>
                            </button>
                        </div>
                    </div>

                    <div class="sheets-table-wrap view-my-task-wrap">
                        <table class="sheets-table view-my-task-table">
                            <colgroup>
                                <col style="width:120px;">
                                <col style="width:90px;">
                                <col>
                                <col style="width:110px;">
                                <col style="width:130px;">
                                <col style="width:150px;">
                                <col style="width:130px;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><div class="sheets-th-inner"><span class="sheets-th-label">Key</span></div></th>
                                    <th data-filter-col="type"><div class="sheets-th-inner"><span class="sheets-th-label">Type</span><span class="sheets-th-actions"><button class="sheets-th-btn btn-filter" data-filter-col="type" title="Filter"><i class="fa-solid fa-filter"></i></button></span></div></th>
                                    <th><div class="sheets-th-inner"><span class="sheets-th-label">Summary</span></div></th>
                                    <th data-filter-col="priority"><div class="sheets-th-inner"><span class="sheets-th-label">Priority</span><span class="sheets-th-actions"><button class="sheets-th-btn btn-filter" data-filter-col="priority" title="Filter"><i class="fa-solid fa-filter"></i></button></span></div></th>
                                    <th data-filter-col="dueDate"><div class="sheets-th-inner"><span class="sheets-th-label">Due Date</span><span class="sheets-th-actions"><button class="sheets-th-btn btn-filter" data-filter-col="dueDate" title="Filter"><i class="fa-solid fa-filter"></i></button></span></div></th>
                                    <th data-filter-col="assignee"><div class="sheets-th-inner"><span class="sheets-th-label">Assignee</span><span class="sheets-th-actions"><button class="sheets-th-btn btn-filter" data-filter-col="assignee" title="Filter"><i class="fa-solid fa-filter"></i></button></span></div></th>
                                    <th><div class="sheets-th-inner"><span class="sheets-th-label">Estimate Time</span></div></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myTaskGroups as $group): ?>
                                    <tr class="view-my-task-status-row<?= !empty($group['is_priority']) ? ' is-priority' : '' ?>" data-group-toggle>
                                        <td colspan="7">
                                            <i class="fa-solid fa-chevron-down view-my-task-toggle-icon"></i>
                                            <span class="view-my-task-status-dot" style="background:<?= htmlspecialchars((string) $group['color'], ENT_QUOTES, 'UTF-8') ?>"></span>
                                            <span class="view-my-task-status-name"><?= htmlspecialchars((string) $group['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="view-my-task-status-count"><?= (int) $group['item_count'] ?></span>
                                            <?php if (!empty($group['is_priority'])): ?>
                                                <span class="view-my-task-priority-badge">Priority</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($group['date_groups'] as $dateGroup): ?>
                                        <tr class="view-my-task-date-row" data-group-toggle>
                                            <td colspan="7">
                                                <i class="fa-solid fa-chevron-down view-my-task-toggle-icon"></i>
                                                <span class="view-my-task-date-label"><?= htmlspecialchars((string) $dateGroup['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="view-my-task-date-count"><?= count($dateGroup['items']) ?></span>
                                            </td>
                                        </tr>
                                        <?php foreach ($dateGroup['items'] as $item): ?>
                                            <tr class="view-my-task-item-row" data-item-id="<?= (int) $item['id'] ?>"
                                                data-status-column-id="<?= (int) $item['column_id'] ?>"
                                                data-work-type-id="<?= (int) $item['work_type_id'] ?>"
                                                data-work-type-name="<?= htmlspecialchars((string) $item['work_type_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-work-type-icon="<?= htmlspecialchars((string) $item['work_type_svg_icon'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-work-item-key="<?= htmlspecialchars((string) $item['work_item_key'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-item-description="<?= htmlspecialchars((string) $item['description'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-priority="<?= htmlspecialchars((string) $item['priority'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-assignee-user-id="<?= (int) $item['assignee_user_id'] ?>"
                                                data-assignee-name="<?= htmlspecialchars((string) $item['assignee_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-reporter-user-id="<?= (int) $item['reporter_user_id'] ?>"
                                                data-reporter-name="<?= htmlspecialchars((string) $item['reporter_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-parent-item-id="<?= (int) $item['parent_item_id'] ?>"
                                                data-due-date="<?= htmlspecialchars($dateGroup['due_date'] !== '' ? $dateGroup['due_date'] : '-', ENT_QUOTES, 'UTF-8') ?>">
                                                <td><span class="sheets-cell-key"><?= htmlspecialchars((string) $item['work_item_key'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                                <td>
                                                    <div class="sheets-cell-type">
                                                        <?php if (trim((string) $item['work_type_svg_icon']) !== ''): ?>
                                                            <img class="sheets-wt-icon" src="<?= htmlspecialchars((string) $item['work_type_svg_icon'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                                                        <?php endif; ?>
                                                        <span><?= htmlspecialchars((string) $item['work_type_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $item['priority'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($dateGroup['due_date'] !== '' ? $dateGroup['due_date'] : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($item['assignee_name'] !== '' ? $item['assignee_name'] : 'Unassigned', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= (int) $item['original_estimate_value'] > 0 ? htmlspecialchars($item['original_estimate_value'] . ' ' . $item['original_estimate_unit'], ENT_QUOTES, 'UTF-8') : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    currentProject: <?= json_encode($currentProject, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypeIcons: <?= json_encode($workTypeIcons, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    columns: <?= json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
console.log('[DEBUG] taskBoardData.statusLabels:', window.taskBoardData.statusLabels);
console.log('[DEBUG] statusLabels count:', (Array.isArray(window.taskBoardData.statusLabels) ? window.taskBoardData.statusLabels.length : 'NOT ARRAY'));
</script>
<script src="../js/task_board_core.js?v=<?= (int) @filemtime(__DIR__ . '/../js/task_board_core.js') ?>"></script>
<script src="../js/task_board_ui.js?v=<?= (int) @filemtime(__DIR__ . '/../js/task_board_ui.js') ?>"></script>
<script src="../js/task_board.js?v=<?= (int) @filemtime(__DIR__ . '/../js/task_board.js') ?>"></script>
<script src="<?= $SITEURL ?>/header/tinymce/tinymce.min.js"></script>
<script src="../js/text_editor.js"></script>
<script src="../js/view_my_task.js?v=<?= (int) @filemtime(__DIR__ . '/../js/view_my_task.js') ?>"></script>
</body>
</html>
