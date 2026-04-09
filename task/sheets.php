<?php
$currentPagePin = 136;
$pageTitle = 'Task Management Sheets';
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once '../common_task.php';

$pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    echo "<script>alert('You do not have permission to view task management.'); location.replace('../dashboard.php');</script>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/task.css">
</head>
<body>
<div class="container-fluid d-flex justify-content-center task-page-wrap">
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
                <?php taskRenderMobileMenuDropdown($SITEURL, 'sheets'); ?>

                <div class="task-placeholder-card">
                    <h5>Sheets</h5>
                    <p class="mb-0">Sheets page scaffold is ready for the next phase.</p>
                </div>
            </div>
        </section>
    </div>
</div>
<script>
window.taskBoardConfig = {
    ajaxUrl: 'board.php',
    canAdd: false,
    canEdit: false,
    workTypes: [],
    assignees: []
};
</script>
<script src="../js/task_board.js"></script>
</body>
</html>
