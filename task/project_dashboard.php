<?php
ob_start();

$taskParentPin = 139;
$currentPagePin = 139;
$pageTitle = 'Project Task Dashboard';
$taskParentTitle = 'Project Task';

include_once '../menuHeader.php';
include_once './common_task.php';
$taskParentTitle = taskGetPinGroupTitleById($connect, $taskParentPin, $taskParentTitle);

$pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    renderNotificationScript('You do not have permission to view Project Task.', 'error', '../dashboard.php', 1200, true);
    exit;
}

$currentUserId = USER_ID;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('task_action') === 'create_project') {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
    }
    if (!taskCanCreateProject($connect)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create project task.'));
    }

    $result = taskCreateProject($connect, post('project_name'), $currentUserId, $cdate, $ctime);
    taskJsonResponse($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('task_action') === 'delete_project_task') {
    $submittedToken = (string) post('csrf_token');
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
    }

    $deleteProjectId = (int) post('project_id');
    if ($deleteProjectId <= 0 || !taskCanManageProjectActions($connect, $deleteProjectId)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete this project task.'));
    }

    $result = taskDeleteProject($connect, $deleteProjectId, $currentUserId, $cdate, $ctime);
    taskJsonResponse($result);
}

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

$canCreateTaskProject = taskCanCreateProject($connect);
$taskProjectList = taskGetProjectList($connect);

$ownerNameById = array();
foreach (taskGetAssignees($connect) as $assignee) {
    $ownerNameById[(int) $assignee['id']] = (string) $assignee['name'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
    <link rel="stylesheet" href="../css/project_dashboard.css">
</head>
<body>
<div class="container-fluid d-flex justify-content-center mt-3 task-page-wrap">
    <div class="col-12 col-md-11">
        <div class="d-flex flex-column mb-3">
            <div class="row">
                <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="row">
                <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                    <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
            </div>
        </div>

        <?php if ($canCreateTaskProject): ?>
            <div class="project-dashboard-create-row" id="projectDashboardCreateRow">
                <input type="text" id="projectDashboardCreateInput" class="form-control project-dashboard-create-input" maxlength="180" placeholder="Project task name">
                <button type="button" class="btn btn-primary project-dashboard-create-btn" id="projectDashboardCreateBtn">
                    <i class="fa-solid fa-plus"></i> New Project Task
                </button>
            </div>
        <?php endif; ?>

        <?php if (empty($taskProjectList)): ?>
            <div class="task-empty-board-note">No project task found yet.</div>
        <?php else: ?>
            <div class="project-dashboard-grid" id="projectDashboardGrid">
                <?php foreach ($taskProjectList as $taskProject): ?>
                    <?php
                    $pid = (int) $taskProject['id'];
                    $ownerId = (int) $taskProject['owner_user_id'];
                    $ownerName = isset($ownerNameById[$ownerId]) ? $ownerNameById[$ownerId] : '';
                    $canManage = taskCanManageProjectActions($connect, $pid);
                    ?>
                    <div class="card shadow-sm project-dashboard-card" data-project-id="<?= $pid ?>">
                        <div class="project-dashboard-card-color" style="background:<?= htmlspecialchars((string) $taskProject['board_background_color'], ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="card-body">
                            <h5 class="project-dashboard-card-title"><?= htmlspecialchars((string) $taskProject['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                            <?php if ($ownerName !== ''): ?>
                                <p class="project-dashboard-card-owner">Owner: <?= htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <div class="project-dashboard-card-actions">
                                <a class="btn btn-sm btn-primary" href="<?= $SITEURL ?>/task/summary.php?project_id=<?= $pid ?>">Enter</a>
                                <?php if ($canManage): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger project-dashboard-delete-btn" data-project-id="<?= $pid ?>" data-project-name="<?= htmlspecialchars((string) $taskProject['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="taskBoardToastHost" class="task-board-toast-host" aria-live="polite" aria-atomic="true"></div>

<script>
window.projectDashboardConfig = {
    ajaxUrl: <?= json_encode('project_dashboard.php', JSON_UNESCAPED_UNICODE) ?>,
    csrfToken: <?= json_encode($_SESSION['csrf_token'], JSON_UNESCAPED_UNICODE) ?>,
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="../js/project_dashboard.js"></script>
</body>
</html>
