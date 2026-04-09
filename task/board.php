<?php
$currentPagePin = 136;
$pageTitle = 'Board';
$taskParentTitle = 'Task Management';
$isFinance = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action'])) {
    include_once '../include/connection.php';
    include_once ROOT . '/include/common.php';
    include_once ROOT . '/include/common_variable.php';
    include_once '../common_task.php';

    $pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
    if (!taskIsActionAllowed('view', $pinAccess)) {
        taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to access task board.'));
    }

    $currentUserId = defined('USER_ID') ? USER_ID : '';
    taskEnsureDefaultWorkTypes($connect, $currentUserId, $cdate, $ctime);

    $taskAction = isset($_POST['task_action']) ? trim((string) $_POST['task_action']) : '';

    if ($taskAction === 'create_column' || $taskAction === 'create_status') {
        if (!taskIsActionAllowed('add', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create statuses.'));
        }

        $result = taskCreateColumn($connect, isset($_POST['column_name']) ? $_POST['column_name'] : '', $currentUserId, $cdate, $ctime);
        taskJsonResponse($result);
    }

    if ($taskAction === 'rename_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to rename status.'));
        }

        $result = taskRenameColumn(
            $connect,
            isset($_POST['column_id']) ? (int) $_POST['column_id'] : 0,
            isset($_POST['column_name']) ? $_POST['column_name'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'move_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to move status.'));
        }

        $result = taskMoveColumn(
            $connect,
            isset($_POST['column_id']) ? (int) $_POST['column_id'] : 0,
            isset($_POST['direction']) ? $_POST['direction'] : ''
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete status.'));
        }

        $result = taskDeleteColumn(
            $connect,
            isset($_POST['column_id']) ? (int) $_POST['column_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'create_work_type') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add work type.'));
        }

        $result = taskCreateWorkType(
            $connect,
            isset($_POST['work_type_name']) ? $_POST['work_type_name'] : '',
            isset($_POST['work_type_remark']) ? $_POST['work_type_remark'] : '',
            isset($_POST['work_type_svg_icon']) ? $_POST['work_type_svg_icon'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );
        if (!empty($result['ok'])) {
            $result['workTypes'] = taskGetWorkTypes($connect);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_work_type') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to edit work type.'));
        }

        $result = taskUpdateWorkType(
            $connect,
            isset($_POST['work_type_id']) ? (int) $_POST['work_type_id'] : 0,
            isset($_POST['work_type_name']) ? $_POST['work_type_name'] : '',
            isset($_POST['work_type_remark']) ? $_POST['work_type_remark'] : '',
            isset($_POST['work_type_svg_icon']) ? $_POST['work_type_svg_icon'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['workTypes'] = taskGetWorkTypes($connect);
        }
        taskJsonResponse($result);
    }

    if ($taskAction === 'create_item') {
        if (!taskIsActionAllowed('add', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create work item.'));
        }

        $result = taskCreateItem(
            $connect,
            isset($_POST['column_id']) ? (int) $_POST['column_id'] : 0,
            isset($_POST['title']) ? $_POST['title'] : '',
            isset($_POST['work_type_id']) ? (int) $_POST['work_type_id'] : 0,
            isset($_POST['assignee_user_id']) ? (int) $_POST['assignee_user_id'] : 0,
            isset($_POST['due_date']) ? $_POST['due_date'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'move_item') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to move work item.'));
        }

        $result = taskMoveItem(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['move_to']) ? $_POST['move_to'] : ''
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'change_item_status') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to change status.'));
        }

        $result = taskChangeItemStatus(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['target_column_id']) ? (int) $_POST['target_column_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'move_item_drop') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to move work item.'));
        }

        $result = taskMoveItemByDrop(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['target_column_id']) ? (int) $_POST['target_column_id'] : 0,
            isset($_POST['target_index']) ? (int) $_POST['target_index'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_assignee') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to set assignee.'));
        }

        $result = taskSetItemAssignee(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['assignee_user_id']) ? (int) $_POST['assignee_user_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'save_project_key') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to change project key.'));
        }

        $result = taskSaveProjectKeySetting(
            $connect,
            isset($_POST['project_key']) ? $_POST['project_key'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_core') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to update work item.'));
        }

        $result = taskUpdateItemCore(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['title']) ? $_POST['title'] : '',
            isset($_POST['description']) ? $_POST['description'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'get_item_detail') {
        $result = taskGetItemDetail(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0
        );
        taskJsonResponse($result);
    }

    if ($taskAction === 'update_item_detail') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to update work item details.'));
        }

        $result = taskUpdateItemDetail(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['assignee_user_id']) ? (int) $_POST['assignee_user_id'] : 0,
            isset($_POST['reporter_user_id']) ? (int) $_POST['reporter_user_id'] : 0,
            isset($_POST['priority']) ? $_POST['priority'] : 'Medium',
            isset($_POST['original_estimate_value']) ? (int) $_POST['original_estimate_value'] : 0,
            isset($_POST['original_estimate_unit']) ? $_POST['original_estimate_unit'] : 'minutes',
            isset($_POST['task_status_label_ids']) ? $_POST['task_status_label_ids'] : '',
            isset($_POST['start_date']) ? $_POST['start_date'] : '',
            isset($_POST['due_date']) ? $_POST['due_date'] : '',
            isset($_POST['amendement_date']) ? $_POST['amendement_date'] : '',
            isset($_POST['amendement_time_minutes']) ? (int) $_POST['amendement_time_minutes'] : 0,
            isset($_POST['second_amendement_date']) ? $_POST['second_amendement_date'] : '',
            isset($_POST['second_amendement_time_minutes']) ? (int) $_POST['second_amendement_time_minutes'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_parent') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to link parent.'));
        }

        $result = taskSetItemParentRelation(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_POST['parent_item_id']) ? (int) $_POST['parent_item_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );

        taskJsonResponse($result);
    }

    if ($taskAction === 'create_item_web_link') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to add web links.'));
        }

        $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $result = taskCreateItemUrl(
            $connect,
            $itemId,
            isset($_POST['url']) ? $_POST['url'] : '',
            isset($_POST['link_text']) ? $_POST['link_text'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['webLinks'] = taskGetItemUrls($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_web_link') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete web links.'));
        }

        $result = taskDeleteItemUrl(
            $connect,
            isset($_POST['url_id']) ? (int) $_POST['url_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $itemId = isset($result['item_id']) ? (int) $result['item_id'] : 0;
            $result['webLinks'] = taskGetItemUrls($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_status_label') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete task status labels.'));
        }

        $result = taskDeleteStatusLabel(
            $connect,
            isset($_POST['status_label_id']) ? (int) $_POST['status_label_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['statusLabels'] = taskGetStatusLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'create_status_label') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create task status labels.'));
        }

        $result = taskCreateStatusLabel(
            $connect,
            isset($_POST['status_label_name']) ? $_POST['status_label_name'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['statusLabels'] = taskGetStatusLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'get_item_attachments') {
        $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        taskJsonResponse(array(
            'ok' => 1,
            'attachments' => taskGetItemAttachments($connect, $itemId),
        ));
    }

    if ($taskAction === 'upload_item_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to upload attachment.'));
        }

        $result = taskUploadItemAttachment(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            isset($_FILES['attachment']) ? $_FILES['attachment'] : array(),
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['attachments'] = taskGetItemAttachments($connect, isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item_attachment') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete attachment.'));
        }

        $result = taskDeleteItemAttachment(
            $connect,
            isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $itemId = isset($result['item_id']) ? (int) $result['item_id'] : 0;
            $result['attachments'] = taskGetItemAttachments($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_all_item_attachments') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete attachments.'));
        }

        $itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
        $result = taskDeleteAllItemAttachments(
            $connect,
            $itemId,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['attachments'] = taskGetItemAttachments($connect, $itemId);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'create_label') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to create labels.'));
        }

        $result = taskCreateLabel(
            $connect,
            isset($_POST['label_name']) ? $_POST['label_name'] : '',
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['labels'] = taskGetLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_label') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete labels.'));
        }

        $result = taskDeleteLabel(
            $connect,
            isset($_POST['label_id']) ? (int) $_POST['label_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['labels'] = taskGetLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'set_item_labels') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to set labels.'));
        }

        $labelIdsCsv = isset($_POST['label_ids']) ? trim((string) $_POST['label_ids']) : '';
        $labelIds = array();
        if ($labelIdsCsv !== '') {
            foreach (explode(',', $labelIdsCsv) as $labelId) {
                $labelId = trim((string) $labelId);
                if ($labelId !== '' && ctype_digit($labelId)) {
                    $labelIds[] = (int) $labelId;
                }
            }
        }

        $result = taskAssignItemLabels(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            $labelIds,
            $currentUserId,
            $cdate,
            $ctime
        );

        if (!empty($result['ok'])) {
            $result['allLabels'] = taskGetLabels($connect);
        }

        taskJsonResponse($result);
    }

    if ($taskAction === 'delete_item') {
        if (!taskIsActionAllowed('edit', $pinAccess)) {
            taskJsonResponse(array('ok' => 0, 'message' => 'You do not have permission to delete work item.'));
        }

        $result = taskDeleteItem(
            $connect,
            isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0,
            $currentUserId,
            $cdate,
            $ctime
        );
        taskJsonResponse($result);
    }

    taskJsonResponse(array('ok' => 0, 'message' => 'Invalid task action.'));
}

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once '../common_task.php';

$pageTitle = 'Board';

$pinAccess = taskGetPinAccessByGroupId($connect, $currentPagePin);
if (!taskIsActionAllowed('view', $pinAccess)) {
    echo "<script>alert('You do not have permission to view task board.'); location.replace('../dashboard.php');</script>";
    exit;
}

$currentUserId = defined('USER_ID') ? USER_ID : '';
taskEnsureDefaultWorkTypes($connect, $currentUserId, $cdate, $ctime);

$canAdd = taskIsActionAllowed('add', $pinAccess);
$canEdit = taskIsActionAllowed('edit', $pinAccess);
$workTypes = taskGetWorkTypes($connect);
$workTypeIcons = taskGetSvgIconOptions();
$projectKeySetting = taskGetProjectKeySetting($connect);
$assignees = taskGetAssignees($connect);
$labels = taskGetLabels($connect);
$statusLabels = taskGetStatusLabels($connect);
$columns = taskGetColumns($connect);
$itemsByColumn = taskGetItemsGroupedByColumn($connect);
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
        <div class="d-flex flex-column mb-2">
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
                <h5 class="mb-2">Task Management</h5>
                <?php taskRenderSidebarMenu($SITEURL, 'board'); ?>
            </aside>

            <div id="taskSidebarBackdrop" class="task-sidebar-backdrop"></div>

            <div class="task-main-content">
                <div class="task-board-toolbar mb-2">
                    <div class="task-board-search-group">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input id="taskBoardSearchInput" class="form-control" type="text" maxlength="150" placeholder="Search board">
                    </div>
                    <div class="dropdown">
                        <button id="taskBoardAssigneeFilterBtn" class="btn task-board-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Filter: All
                        </button>
                        <ul id="taskBoardAssigneeFilterMenu" class="dropdown-menu task-board-filter-menu">
                            <li><a class="dropdown-item task-board-assignee-filter-option active" href="#" data-assignee-filter="all" data-assignee-name="All">All</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php foreach ($assignees as $assignee): ?>
                                <?php
                                    $assigneeId = (int) $assignee['id'];
                                    $assigneeName = isset($assignee['name']) ? (string) $assignee['name'] : '';
                                ?>
                                <li>
                                    <a class="dropdown-item task-board-assignee-filter-option" href="#" data-assignee-filter="<?= $assigneeId ?>" data-assignee-name="<?= htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="dropdown ms-auto task-board-settings-wrap">
                        <button id="taskBoardSettingsBtn" class="btn task-board-settings-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Board settings">
                            <i class="fa-solid fa-sliders"></i>
                        </button>
                        <div id="taskBoardSettingsPanel" class="dropdown-menu dropdown-menu-end task-board-settings-panel p-3">
                            <h6 class="mb-3">View settings</h6>
                            <div class="mb-3">
                                <label class="form-label mb-1" for="taskProjectKeyInput">Project Key fields</label>
                                <div class="task-project-key-row">
                                    <input id="taskProjectKeyInput" type="text" class="form-control form-control-sm" maxlength="20" placeholder="Example: BCS" value="<?= htmlspecialchars(isset($projectKeySetting['project_key']) ? (string) $projectKeySetting['project_key'] : '', ENT_QUOTES, 'UTF-8') ?>">
                                    <button id="taskProjectKeySaveBtn" class="btn btn-light task-project-key-action-btn" type="button" title="Save project key"><i class="fa-solid fa-check"></i></button>
                                    <button id="taskProjectKeyClearBtn" class="btn btn-light task-project-key-action-btn" type="button" title="Clear project key"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button id="taskManageStatusBtn" class="btn btn-outline-secondary btn-sm w-100 task-manage-status-btn" type="button">Manage Task Status</button>
                                <div id="taskManageStatusPanel" class="task-manage-status-panel d-none mt-2">
                                    <div class="input-group input-group-sm mb-2">
                                        <input id="taskManageStatusInput" type="text" class="form-control" maxlength="120" placeholder="Type status label name">
                                        <button id="taskManageStatusCreateBtn" class="btn btn-primary task-manage-status-create-btn" type="button" title="Add status label"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                    <div id="taskManageStatusList" class="task-manage-status-list"></div>
                                </div>
                            </div>
                            <hr class="my-2">
                            <h6 class="mb-2">Fields</h6>
                            <div class="task-board-settings-fields">
                                <?php
                                    $settingsFields = array(
                                        'Work item key',
                                        'Work type',
                                        'Assignee',
                                        'Priority',
                                        'Reporter',
                                        'Due date',
                                        'Amendement date',
                                        'Amendement time',
                                        'Second amen-date',
                                        'Second amen-time',
                                        'Start date',
                                        'Original estimate',
                                        'Parent',
                                    );
                                ?>
                                <?php foreach ($settingsFields as $fieldLabel): ?>
                                    <div class="task-board-settings-field-row">
                                        <span><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        <label class="task-field-toggle mb-0" title="Toggle <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="checkbox" checked>
                                            <span class="task-field-toggle-slider"></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="taskBoardApp" class="task-board-scroll">
                    <div id="taskBoardGrid" class="task-board-grid">
                        <?php foreach ($columns as $column): ?>
                            <?php
                                $columnId = (int) $column['id'];
                                $columnItems = isset($itemsByColumn[$columnId]) ? $itemsByColumn[$columnId] : array();
                                taskRenderBoardColumn($column, $columnItems, $workTypes, $assignees);
                            ?>
                        <?php endforeach; ?>

                        <div id="taskCreateStatusSlot" class="task-create-status-slot">
                            <button id="taskOpenCreateStatusBtn" class="btn task-create-status-icon-btn" type="button" <?= $canAdd ? '' : 'disabled' ?> title="Add status">
                                <i class="fa-solid fa-plus"></i>
                            </button>

                            <form id="taskCreateStatusInlineForm" class="task-create-status-inline d-none">
                                <input id="taskStatusName" class="form-control" type="text" maxlength="150" placeholder="Status name" <?= $canAdd ? '' : 'disabled' ?>>
                                <button id="taskCreateStatusSubmit" class="btn btn-primary" type="submit" <?= $canAdd ? '' : 'disabled' ?>><i class="fa-solid fa-check"></i></button>
                                <button id="taskCreateStatusCancel" class="btn btn-light" type="button"><i class="fa-solid fa-xmark"></i></button>
                            </form>
                        </div>
                    </div>

                    <div id="taskBoardEmpty" class="task-empty-board-note mt-3 <?= !empty($columns) ? 'd-none' : '' ?>">
                        Board is empty. Click the + button to create your first status.
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="taskCreateStatusMobileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="taskStatusNameMobile">Status name</label>
                <input id="taskStatusNameMobile" class="form-control" type="text" maxlength="150" placeholder="Status name" <?= $canAdd ? '' : 'disabled' ?>>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="taskCreateStatusSubmitMobile" class="btn btn-primary" <?= $canAdd ? '' : 'disabled' ?>>Add</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="taskWorkTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered task-work-type-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskWorkTypeModalTitle">Add work type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="taskWorkTypeNameInput">Name <span class="text-danger">*</span></label>
                    <input id="taskWorkTypeNameInput" class="form-control" type="text" maxlength="80" placeholder="Work type name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="taskWorkTypeDescriptionInput">Description</label>
                    <textarea id="taskWorkTypeDescriptionInput" class="form-control" rows="3" maxlength="255" placeholder="Let people know when to use this work type."></textarea>
                    <small class="text-muted">Let people know when to use this work type.</small>
                </div>
                <div>
                    <label class="form-label">Icon</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="taskWorkTypeChangeIcon" checked>
                        <label class="form-check-label" for="taskWorkTypeChangeIcon">Change icon</label>
                    </div>
                    <div class="dropdown task-work-type-icon-dropdown-wrap">
                        <button id="taskWorkTypeIconDropdownBtn" class="btn btn-outline-primary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Change icon</button>
                        <div id="taskWorkTypeIconPicker" class="dropdown-menu task-work-type-icon-picker-dropdown"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="taskWorkTypeSaveBtn" class="btn btn-primary" disabled>Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="taskItemDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable task-item-detail-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="taskItemDetailModalTitle" class="modal-title">Work item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-8">
                        <div id="taskItemDetailKeyTrail" class="task-item-detail-key-trail d-none"></div>
                        <div class="mb-3">
                            <label class="form-label" for="taskItemDetailTitleInput">Title</label>
                            <div class="task-item-detail-title-row">
                                <input id="taskItemDetailTitleInput" class="form-control" type="text" maxlength="255" placeholder="Work item name">
                                <button id="taskItemDetailTitleSaveBtn" class="btn btn-light task-item-detail-title-btn" type="button" title="Save title"><i class="fa-solid fa-check"></i></button>
                                <button id="taskItemDetailTitleResetBtn" class="btn btn-light task-item-detail-title-btn" type="button" title="Reset title"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <div class="dropdown task-item-detail-add-wrap mt-2">
                                <button id="taskItemDetailAddBtn" class="btn btn-outline-primary task-item-detail-add-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Add">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                                <ul class="dropdown-menu task-item-detail-add-menu">
                                    <li><a id="taskItemDetailAddAttachmentAction" class="dropdown-item" href="#">Add attachment</a></li>
                                    <li><a id="taskItemDetailAddWebLinkAction" class="dropdown-item" href="#">Add web link</a></li>
                                </ul>
                                <input id="taskItemAttachmentInput" type="file" class="d-none" multiple>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="taskItemDetailDescriptionInput">Description</label>
                            <textarea id="taskItemDetailDescriptionInput" class="form-control" rows="6" placeholder="Description"></textarea>
                        </div>

                        <div id="taskItemChildWorkItemsSection" class="mb-3 task-item-child-section d-none">
                            <div class="task-item-child-header">
                                <button id="taskItemChildWorkItemsCollapseBtn" type="button" class="btn task-item-child-collapse-btn" aria-expanded="true" title="Collapse child work items">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </button>
                                <span class="task-item-child-title">Child work items</span>
                                <span id="taskItemChildWorkItemsCount" class="task-item-child-count">0</span>
                                <span id="taskItemChildWorkItemsProgressText" class="task-item-child-progress-text ms-auto">0% Done</span>
                            </div>
                            <div id="taskItemChildWorkItemsBody" class="task-item-child-body">
                                <div class="task-item-child-progress-bar-wrap">
                                    <div id="taskItemChildWorkItemsProgressBar" class="task-item-child-progress-bar" style="width:0%;"></div>
                                </div>
                                <div class="task-item-child-table-head">
                                    <span>Work</span>
                                    <span>Priority</span>
                                    <span>Assignee</span>
                                    <span>Status</span>
                                </div>
                                <div id="taskItemChildWorkItemsList" class="task-item-child-list"></div>
                            </div>
                        </div>

                        <div class="mb-3 task-item-attachments-panel" id="taskItemAttachmentsPanel">
                            <div class="task-item-attachments-header">
                                <button id="taskItemAttachmentCollapseBtn" type="button" class="btn task-item-attachment-collapse-btn" aria-expanded="true" title="Collapse attachments">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </button>
                                <span class="task-item-attachments-title">Attachments</span>
                                <span id="taskItemAttachmentCount" class="task-item-attachment-count">0</span>
                                <div class="task-item-attachments-header-actions ms-auto">
                                    <div class="dropdown">
                                        <button id="taskItemAttachmentMoreBtn" class="btn task-item-attachment-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Attachment options">
                                            <i class="fa-solid fa-ellipsis"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end task-item-attachment-more-menu">
                                            <li><a id="taskItemAttachmentToggleViewAction" class="dropdown-item" href="#">Switch to strip view</a></li>
                                            <li><a id="taskItemAttachmentDownloadAllAction" class="dropdown-item" href="#">Download all <span id="taskItemAttachmentDownloadAllCount" class="task-item-attachment-menu-count">0</span></a></li>
                                            <li><a id="taskItemAttachmentDeleteAllAction" class="dropdown-item text-danger" href="#">Delete all</a></li>
                                        </ul>
                                    </div>
                                    <button id="taskItemAttachmentAddBtn" class="btn btn-outline-primary task-item-attachment-icon-btn" type="button" title="Add attachment">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="taskItemAttachmentDetails" class="task-item-attachment-details">
                                <div class="task-item-attachment-table-head">
                                    <button class="btn task-item-attachment-sort-btn" type="button" data-sort-field="name" title="Sort by name">
                                        <span>Name</span>
                                        <i class="fa-solid fa-arrow-down-long"></i>
                                    </button>
                                    <button class="btn task-item-attachment-sort-btn" type="button" data-sort-field="size" title="Sort by size">
                                        <span>Size</span>
                                        <i class="fa-solid fa-arrow-down-long"></i>
                                    </button>
                                    <button class="btn task-item-attachment-sort-btn" type="button" data-sort-field="date" title="Sort by date">
                                        <span>Date added</span>
                                        <i class="fa-solid fa-arrow-down-long"></i>
                                    </button>
                                    <span class="task-item-attachment-head-actions"></span>
                                </div>
                                <div id="taskItemAttachmentList" class="task-item-attachment-list">
                                    <div class="task-item-attachment-empty">No attachments yet.</div>
                                </div>
                            </div>
                        </div>

                        <div id="taskItemWebLinksSection" class="mb-3 task-item-web-links-section d-none">
                            <div class="task-item-web-links-header">
                                <h5 class="mb-0">Web Links</h5>
                                <button id="taskItemWebLinkAddBtn" class="btn btn-outline-primary task-item-web-link-add-btn" type="button" title="Add web link">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            <div id="taskItemWebLinkEditor" class="task-item-web-link-editor d-none">
                                <input id="taskItemWebLinkUrlInput" class="form-control form-control-sm" type="text" maxlength="500" placeholder="URL">
                                <input id="taskItemWebLinkTextInput" class="form-control form-control-sm" type="text" maxlength="255" placeholder="Link text">
                                <div class="task-item-web-link-editor-actions">
                                    <button id="taskItemWebLinkSaveBtn" class="btn btn-primary btn-sm" type="button">Link</button>
                                    <button id="taskItemWebLinkCancelBtn" class="btn btn-light btn-sm" type="button">Cancel</button>
                                </div>
                            </div>
                            <div id="taskItemWebLinkList" class="task-item-web-link-list"></div>
                        </div>

                        <div class="task-item-activity-section">
                            <h5 class="mb-2">Activity</h5>
                            <ul class="nav nav-tabs task-item-activity-tabs" role="tablist">
                                <li class="nav-item" role="presentation"><button class="nav-link active" type="button">All</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" type="button">Comment</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" type="button">History</button></li>
                            </ul>
                            <div class="task-item-activity-compose mt-3">
                                <textarea class="form-control" rows="3" placeholder="Add a comment..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div id="taskItemDetailSideCard" class="task-item-detail-side-card">
                            <div class="task-item-detail-side-head mb-3">
                                <button id="taskItemDetailSideCollapseBtn" type="button" class="btn task-item-detail-side-collapse-btn" aria-expanded="true" title="Collapse details">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </button>
                                <h6 class="task-item-detail-side-title mb-0">Details</h6>
                                <span id="taskItemDetailSideSummary" class="task-item-detail-side-summary d-none">Time tracking, Assignee, Labels, Due date, Start date, Reporter</span>
                            </div>
                            <div id="taskItemDetailFieldRowsWrap">

                            <div class="task-item-detail-field-row" data-detail-field="original_estimate">
                                <label class="task-item-detail-field-label" for="taskItemDetailEstimateValueInput">Original Estimate</label>
                                <div class="task-item-detail-estimate-wrap">
                                    <input id="taskItemDetailEstimateValueInput" class="form-control form-control-sm" type="number" min="0" step="1" placeholder="45">
                                    <select id="taskItemDetailEstimateUnitInput" class="form-select form-select-sm">
                                        <option value="minutes">minutes</option>
                                        <option value="hours">hours</option>
                                        <option value="days">days</option>
                                        <option value="weeks">weeks</option>
                                    </select>
                                </div>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="task_status">
                                <label class="task-item-detail-field-label" for="taskItemDetailStatusSearchInput">Task Status</label>
                                <div class="task-item-detail-status-wrap">
                                    <div class="dropdown task-item-detail-status-dropdown">
                                        <button id="taskItemDetailStatusDropdownBtn" class="btn task-item-detail-status-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                            <div id="taskItemDetailStatusChips" class="task-item-detail-status-chips"></div>
                                        </button>
                                        <div id="taskItemDetailStatusMenu" class="dropdown-menu task-item-detail-status-menu p-2">
                                            <input id="taskItemDetailStatusSearchInput" class="form-control form-control-sm mb-2" type="text" maxlength="120" autocomplete="off" placeholder="Search task status">
                                            <div id="taskItemDetailStatusOptionList" class="task-item-detail-status-option-list"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="parent">
                                <label class="task-item-detail-field-label" for="taskItemDetailParentSelect">Parent</label>
                                <select id="taskItemDetailParentSelect" class="form-select form-select-sm"></select>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="assignee">
                                <label class="task-item-detail-field-label" for="taskItemDetailAssigneeSelect">Assignee</label>
                                <select id="taskItemDetailAssigneeSelect" class="form-select form-select-sm"></select>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="reporter">
                                <label class="task-item-detail-field-label" for="taskItemDetailReporterSelect">Reporter</label>
                                <select id="taskItemDetailReporterSelect" class="form-select form-select-sm"></select>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="priority">
                                <span class="task-item-detail-field-label">Priority</span>
                                <div class="dropdown task-item-detail-priority-wrap">
                                    <button id="taskItemDetailPriorityBtn" class="btn task-item-detail-priority-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                    <ul id="taskItemDetailPriorityMenu" class="dropdown-menu task-item-detail-priority-menu">
                                        <li><a class="dropdown-item task-item-detail-priority-option" href="#" data-priority="Highest"><i class="fa-solid fa-angles-up task-priority-icon task-priority-highest"></i> Highest</a></li>
                                        <li><a class="dropdown-item task-item-detail-priority-option" href="#" data-priority="High"><i class="fa-solid fa-angle-up task-priority-icon task-priority-high"></i> High</a></li>
                                        <li><a class="dropdown-item task-item-detail-priority-option" href="#" data-priority="Medium"><i class="fa-solid fa-minus task-priority-icon task-priority-medium"></i> Medium</a></li>
                                        <li><a class="dropdown-item task-item-detail-priority-option" href="#" data-priority="Low"><i class="fa-solid fa-angle-down task-priority-icon task-priority-low"></i> Low</a></li>
                                        <li><a class="dropdown-item task-item-detail-priority-option" href="#" data-priority="Lowest"><i class="fa-solid fa-angles-down task-priority-icon task-priority-lowest"></i> Lowest</a></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="labels">
                                <span class="task-item-detail-field-label">Labels</span>
                                <div class="task-item-detail-label-wrap">
                                    <div class="dropdown task-item-detail-label-dropdown">
                                        <button id="taskItemDetailLabelDropdownBtn" class="btn task-item-detail-label-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                            <div id="taskItemDetailLabelChips" class="task-item-detail-label-chips"></div>
                                        </button>
                                        <div id="taskItemDetailLabelMenu" class="dropdown-menu task-item-detail-label-menu p-2">
                                            <input id="taskItemDetailLabelSearchInput" class="form-control form-control-sm mb-2" type="text" maxlength="120" placeholder="Search labels">
                                            <div id="taskItemDetailLabelOptionList" class="task-item-detail-label-option-list"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="time_tracking">
                                <span class="task-item-detail-field-label">Time Tracking</span>
                                <span id="taskItemDetailTimeTrackingValue" class="task-item-detail-field-value">No time logged</span>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="start_date">
                                <label class="task-item-detail-field-label" for="taskItemDetailStartDateInput">Start date</label>
                                <input id="taskItemDetailStartDateInput" class="form-control form-control-sm" type="date">
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="due_date">
                                <label class="task-item-detail-field-label" for="taskItemDetailDueDateInput">Due date</label>
                                <input id="taskItemDetailDueDateInput" class="form-control form-control-sm" type="date">
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="amendement_date">
                                <label class="task-item-detail-field-label" for="taskItemDetailAmendDateInput">Amendement Date</label>
                                <input id="taskItemDetailAmendDateInput" class="form-control form-control-sm" type="date">
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="amendement_time">
                                <label class="task-item-detail-field-label" for="taskItemDetailAmendTimeInput">Amendement Time</label>
                                <select id="taskItemDetailAmendTimeInput" class="form-select form-select-sm">
                                    <option value="">Add option</option>
                                    <option value="5">5 min</option>
                                    <option value="10">10 min</option>
                                    <option value="15">15 min</option>
                                    <option value="20">20 min</option>
                                    <option value="25">25 min</option>
                                    <option value="30">30 min</option>
                                    <option value="35">35 min</option>
                                    <option value="40">40 min</option>
                                    <option value="45">45 min</option>
                                </select>
                            </div>

                            <div class="task-item-detail-field-row" data-detail-field="second_amendement_date">
                                <label class="task-item-detail-field-label" for="taskItemDetailSecondAmendDateInput">Second Amen-Date</label>
                                <input id="taskItemDetailSecondAmendDateInput" class="form-control form-control-sm" type="date">
                            </div>

                            <div class="task-item-detail-field-row mb-0" data-detail-field="second_amendement_time">
                                <label class="task-item-detail-field-label" for="taskItemDetailSecondAmendTimeInput">Second Amen-Time</label>
                                <select id="taskItemDetailSecondAmendTimeInput" class="form-select form-select-sm">
                                    <option value="">Add option</option>
                                    <option value="5">5 min</option>
                                    <option value="10">10 min</option>
                                    <option value="15">15 min</option>
                                    <option value="20">20 min</option>
                                    <option value="25">25 min</option>
                                    <option value="30">30 min</option>
                                    <option value="35">35 min</option>
                                    <option value="40">40 min</option>
                                    <option value="45">45 min</option>
                                </select>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="taskItemDetailSaveBtn" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
window.taskBoardConfig = {
    ajaxUrl: 'board.php',
    siteUrl: <?= json_encode(rtrim((string) $SITEURL, '/'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    canAdd: <?= $canAdd ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    projectKey: <?= json_encode($projectKeySetting, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypes: <?= json_encode($workTypes, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    workTypeIcons: <?= json_encode($workTypeIcons, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    assignees: <?= json_encode($assignees, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>,
    statusLabels: <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>
};
</script>
<script src="../js/task_board.js"></script>
</body>
</html>
