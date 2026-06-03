<?php
$isFinance = 1;
$pageTitle = 'Flow Setting';
include_once '../include/connection.php';
include_once '../include/common.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flow_action']) && $_POST['flow_action'] === 'save_shopee_flow_setting_ajax') {
    if ((int) USER_GROUP !== 1) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'Only Super Admin can update Flow Setting.'));
        exit;
    }

    $submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => 0, 'message' => 'Invalid session token. Please refresh the page and try again.'));
        exit;
    }

    $transitionRows = shopeeOmsGetConfigurableTransitions();
    $userGroups = array();
    $userGroupRst = getData('id, name, badge_color, badge_icon_class', '', '', USR_GRP, $connect);
    if ($userGroupRst) {
        while ($row = $userGroupRst->fetch_assoc()) {
            $userGroups[] = $row;
        }
    }
    $userGroupNameMap = array();
    foreach ($userGroups as $userGroup) {
        $userGroupId = isset($userGroup['id']) ? (int) $userGroup['id'] : 0;
        if ($userGroupId > 0) {
            $userGroupNameMap[$userGroupId] = isset($userGroup['name']) ? (string) $userGroup['name'] : ('User Group #' . $userGroupId);
        }
    }

    $warehouseNameMap = array();
    $warehouseRst = getData('id, name', '', '', WHSE, $connect);
    if ($warehouseRst) {
        while ($row = $warehouseRst->fetch_assoc()) {
            $warehouseId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($warehouseId > 0) {
                $warehouseNameMap[$warehouseId] = isset($row['name']) ? (string) $row['name'] : ('Warehouse #' . $warehouseId);
            }
        }
    }

    $userNameMap = array();
    $userRst = getData('id, name', '', '', USR_USER, $connect);
    if ($userRst) {
        while ($row = $userRst->fetch_assoc()) {
            $userId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($userId > 0) {
                $userNameMap[$userId] = isset($row['name']) ? (string) $row['name'] : ('User #' . $userId);
            }
        }
    }

    $assignmentScope = strtolower(trim((string) postSpaceFilter('assignment_scope')));
    if (!in_array($assignmentScope, array('individual', 'global'), true)) {
        $assignmentScope = 'global';
    }

    $defaultWarehouseId = (int) postSpaceFilter('default_warehouse_id');
    $mainSupervisorUserId = (int) postSpaceFilter('daily_report_main_supervisor_user_id');
    $secondSupervisorUserId = (int) postSpaceFilter('daily_report_second_supervisor_user_id');
    $oldAssignmentScope = shopeeOmsGetAssignmentScope($connect);
    $oldDefaultWarehouseId = (int) shopeeOmsGetSetting($connect, 'shopee_oms_default_warehouse_id', '0');
    $oldMainSupervisorUserId = (int) shopeeOmsGetSetting($connect, 'shopee_oms_daily_report_main_supervisor_user_id', '0');
    $oldSecondSupervisorUserId = (int) shopeeOmsGetSetting($connect, 'shopee_oms_daily_report_second_supervisor_user_id', '0');

    $existingPermissionMap = array();
    $permSql = "SELECT p.from_status, p.to_status, p.user_group_id, p.can_move
        FROM `" . ORDER_FLOW_TRANSITION_PERMISSION . "` p
        INNER JOIN (
            SELECT from_status, to_status, user_group_id, MAX(id) AS latest_id
            FROM `" . ORDER_FLOW_TRANSITION_PERMISSION . "`
            WHERE module_key = 'shopee_oms' AND status = 'A'
            GROUP BY from_status, to_status, user_group_id
        ) latest
            ON latest.latest_id = p.id";
    $permRst = mysqli_query($connect, $permSql);
    if ($permRst) {
        while ($row = mysqli_fetch_assoc($permRst)) {
            $transitionKey = shopeeOmsBuildTransitionKey(
                isset($row['from_status']) ? (string) $row['from_status'] : '',
                isset($row['to_status']) ? (string) $row['to_status'] : ''
            );
            $userGroupId = isset($row['user_group_id']) ? (int) $row['user_group_id'] : 0;
            if ($transitionKey !== '' && $userGroupId > 0) {
                $existingPermissionMap[$transitionKey][$userGroupId] = !empty($row['can_move']);
            }
        }
    }

    $settingFieldLabels = array();
    $settingOldVals = array();
    $settingNewVals = array();
    $permissionFieldLabels = array();
    $permissionOldVals = array();
    $permissionNewVals = array();

    shopeeOmsSetSetting($connect, 'shopee_oms_assignment_scope', $assignmentScope, 'OMS assignment scope.', USER_ID);
    shopeeOmsSetSetting($connect, 'shopee_oms_default_warehouse_id', (string) $defaultWarehouseId, 'Default warehouse id for OMS stock-out.', USER_ID);
    shopeeOmsSetSetting($connect, 'shopee_oms_daily_report_main_supervisor_user_id', (string) $mainSupervisorUserId, 'OMS daily report main supervisor user id.', USER_ID);
    shopeeOmsSetSetting($connect, 'shopee_oms_daily_report_second_supervisor_user_id', (string) $secondSupervisorUserId, 'OMS daily report second supervisor user id.', USER_ID);

    if ($oldAssignmentScope !== $assignmentScope) {
        $settingFieldLabels[] = 'Assignment Scope';
        $settingOldVals[] = $oldAssignmentScope;
        $settingNewVals[] = $assignmentScope;
    }
    if ($oldDefaultWarehouseId !== $defaultWarehouseId) {
        $settingFieldLabels[] = 'Default Warehouse';
        $settingOldVals[] = isset($warehouseNameMap[$oldDefaultWarehouseId]) ? $warehouseNameMap[$oldDefaultWarehouseId] : (string) $oldDefaultWarehouseId;
        $settingNewVals[] = isset($warehouseNameMap[$defaultWarehouseId]) ? $warehouseNameMap[$defaultWarehouseId] : (string) $defaultWarehouseId;
    }
    if ($oldMainSupervisorUserId !== $mainSupervisorUserId) {
        $settingFieldLabels[] = 'Main Report Supervisor';
        $settingOldVals[] = isset($userNameMap[$oldMainSupervisorUserId]) ? $userNameMap[$oldMainSupervisorUserId] : (string) $oldMainSupervisorUserId;
        $settingNewVals[] = isset($userNameMap[$mainSupervisorUserId]) ? $userNameMap[$mainSupervisorUserId] : (string) $mainSupervisorUserId;
    }
    if ($oldSecondSupervisorUserId !== $secondSupervisorUserId) {
        $settingFieldLabels[] = 'Second Report Supervisor';
        $settingOldVals[] = isset($userNameMap[$oldSecondSupervisorUserId]) ? $userNameMap[$oldSecondSupervisorUserId] : (string) $oldSecondSupervisorUserId;
        $settingNewVals[] = isset($userNameMap[$secondSupervisorUserId]) ? $userNameMap[$secondSupervisorUserId] : (string) $secondSupervisorUserId;
    }

    foreach ($transitionRows as $transitionRow) {
        $transitionKey = isset($transitionRow['key']) ? (string) $transitionRow['key'] : '';
        $fromStatus = isset($transitionRow['from_status']) ? (string) $transitionRow['from_status'] : '';
        $toStatus = isset($transitionRow['to_status']) ? (string) $transitionRow['to_status'] : '';
        $actionName = isset($transitionRow['action']) ? (string) $transitionRow['action'] : '';

        foreach ($userGroups as $userGroup) {
            $userGroupId = isset($userGroup['id']) ? (int) $userGroup['id'] : 0;
            if ($userGroupId <= 0) {
                continue;
            }

            $isAllowed = isset($_POST['perm'][$transitionKey][$userGroupId]) ? 1 : 0;
            $safeTransitionKey = mysqli_real_escape_string($connect, $transitionKey);
            $safeFromStatus = mysqli_real_escape_string($connect, $fromStatus);
            $safeToStatus = mysqli_real_escape_string($connect, $toStatus);
            $safeActionName = mysqli_real_escape_string($connect, $actionName);
            $safeUserId = mysqli_real_escape_string($connect, USER_ID);
            $oldCanMove = !empty($existingPermissionMap[$transitionKey][$userGroupId]) ? 1 : 0;

            $sql = "INSERT INTO `" . ORDER_FLOW_TRANSITION_PERMISSION . "`
                (`module_key`, `transition_key`, `from_status`, `to_status`, `user_group_id`, `can_move`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                ('shopee_oms', '" . $safeTransitionKey . "', '" . $safeFromStatus . "', '" . $safeToStatus . "', " . $userGroupId . ", " . $isAllowed . ", '" . $safeActionName . "', '" . $safeUserId . "', CURDATE(), CURTIME(), 'A')
                ON DUPLICATE KEY UPDATE `can_move` = VALUES(`can_move`), `remark` = VALUES(`remark`), `status` = 'A', `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME()";
            mysqli_query($connect, $sql);

            if ($oldCanMove !== $isAllowed) {
                $permissionFieldLabels[] = (isset($transitionRow['from_label']) ? (string) $transitionRow['from_label'] : $fromStatus)
                    . ' -> '
                    . (isset($transitionRow['to_label']) ? (string) $transitionRow['to_label'] : $toStatus)
                    . ' [' . (isset($userGroupNameMap[$userGroupId]) ? $userGroupNameMap[$userGroupId] : ('User Group #' . $userGroupId)) . ']';
                $permissionOldVals[] = $oldCanMove ? 'Allowed' : 'Blocked';
                $permissionNewVals[] = $isAllowed ? 'Allowed' : 'Blocked';
            }
        }
    }

    if (!empty($settingFieldLabels)) {
        $settingLog = array(
            'log_act' => 'Edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'Shopee OMS setting update',
            'query_table' => ORDER_FLOW_SETTING,
            'page' => $pageTitle,
            'connect' => $connect,
            'oldval' => implodeWithComma($settingOldVals),
            'changes' => implodeWithComma($settingNewVals),
        );
        $settingLog['act_msg'] = actMsgLog('1', $settingFieldLabels, '', $settingOldVals, $settingNewVals, ORDER_FLOW_SETTING, 'Edit', '');
        audit_log($settingLog);
    }

    if (!empty($permissionFieldLabels)) {
        $permissionLog = array(
            'log_act' => 'Edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'Shopee OMS transition permission update',
            'query_table' => ORDER_FLOW_TRANSITION_PERMISSION,
            'page' => $pageTitle,
            'connect' => $connect,
            'oldval' => implodeWithComma($permissionOldVals),
            'changes' => implodeWithComma($permissionNewVals),
        );
        $permissionLog['act_msg'] = actMsgLog('1', $permissionFieldLabels, '', $permissionOldVals, $permissionNewVals, ORDER_FLOW_TRANSITION_PERMISSION, 'Edit', '');
        audit_log($permissionLog);
    }

    header('Content-Type: application/json');
    echo json_encode(array('ok' => 1));
    exit;
}

$currentPagePin = 149;
$pageTitle = 'Flow Setting';
$disablePinGroupPageTitleSync = true;
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pageTitle = 'Flow Setting';
if (!isActionAllowed('View', checkPinByGroupId($connect, 149))) {
    echo '<script>alert("You do not have permission to view Flow Setting."); location.replace("../dashboard.php");</script>';
    exit;
}
if ((int) USER_GROUP !== 1) {
    echo '<script>alert("Only Super Admin can access Flow Setting."); location.replace("../dashboard.php");</script>';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    $log = array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    );
    audit_log($log);
}

$transitionRows = shopeeOmsGetConfigurableTransitions();
$userGroups = array();
$userGroupRst = getData('id, name, badge_color, badge_icon_class', '', '', USR_GRP, $connect);
if ($userGroupRst) {
    while ($row = $userGroupRst->fetch_assoc()) {
        $userGroups[] = $row;
    }
}

$warehouseRows = array();
$warehouseRst = getData('id, name', '', '', WHSE, $connect);
if ($warehouseRst) {
    while ($row = $warehouseRst->fetch_assoc()) {
        $warehouseRows[] = $row;
    }
}

$userRows = array();
$userRst = getData('id, name, email', '', '', USR_USER, $connect);
if ($userRst) {
    while ($row = $userRst->fetch_assoc()) {
        $userRows[] = $row;
    }
}

$assignmentScopeValue = shopeeOmsGetAssignmentScope($connect);
$defaultWarehouseValue = (int) shopeeOmsGetSetting($connect, 'shopee_oms_default_warehouse_id', '0');
$mainSupervisorValue = (int) shopeeOmsGetSetting($connect, 'shopee_oms_daily_report_main_supervisor_user_id', '0');
$secondSupervisorValue = (int) shopeeOmsGetSetting($connect, 'shopee_oms_daily_report_second_supervisor_user_id', '0');

$permissionMap = array();
$permSql = "SELECT p.from_status, p.to_status, p.user_group_id, p.can_move
    FROM `" . ORDER_FLOW_TRANSITION_PERMISSION . "` p
    INNER JOIN (
        SELECT from_status, to_status, user_group_id, MAX(id) AS latest_id
        FROM `" . ORDER_FLOW_TRANSITION_PERMISSION . "`
        WHERE module_key = 'shopee_oms' AND status = 'A'
        GROUP BY from_status, to_status, user_group_id
    ) latest
        ON latest.latest_id = p.id";
$permRst = mysqli_query($connect, $permSql);
if ($permRst) {
    while ($row = mysqli_fetch_assoc($permRst)) {
        $transitionKey = shopeeOmsBuildTransitionKey(
            isset($row['from_status']) ? (string) $row['from_status'] : '',
            isset($row['to_status']) ? (string) $row['to_status'] : ''
        );
        $userGroupId = isset($row['user_group_id']) ? (int) $row['user_group_id'] : 0;
        $permissionMap[$transitionKey][$userGroupId] = !empty($row['can_move']);
    }
}

foreach ($transitionRows as $transitionRow) {
    $transitionKey = isset($transitionRow['key']) ? (string) $transitionRow['key'] : '';
    if ($transitionKey === '') {
        continue;
    }

    $fallbackKey = shopeeOmsResolveTransitionPermissionFallbackKey($transitionKey);
    if ($fallbackKey === '') {
        continue;
    }

    foreach ($userGroups as $userGroup) {
        $userGroupId = isset($userGroup['id']) ? (int) $userGroup['id'] : 0;
        if ($userGroupId <= 0 || isset($permissionMap[$transitionKey][$userGroupId]) || !isset($permissionMap[$fallbackKey][$userGroupId])) {
            continue;
        }

        $permissionMap[$transitionKey][$userGroupId] = !empty($permissionMap[$fallbackKey][$userGroupId]);
    }
}

$columnToggleStateMap = array();
foreach ($userGroups as $userGroup) {
    $userGroupId = isset($userGroup['id']) ? (int) $userGroup['id'] : 0;
    if ($userGroupId <= 0) {
        continue;
    }

    $hasAnyChecked = false;
    $allChecked = !empty($transitionRows);
    foreach ($transitionRows as $transitionRow) {
        $transitionKey = isset($transitionRow['key']) ? (string) $transitionRow['key'] : '';
        $isChecked = !empty($permissionMap[$transitionKey][$userGroupId]);
        if ($isChecked) {
            $hasAnyChecked = true;
        } else {
            $allChecked = false;
        }
    }

    $columnToggleStateMap[$userGroupId] = array(
        'all_checked' => $allChecked,
        'has_any_checked' => $hasAnyChecked,
    );
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/project_user_access.css">
    <style>
        .shopee-flow-setting-col-toggle {
            margin-top: 6px;
        }

        .shopee-flow-setting-col-toggle .form-check-input {
            float: none;
            margin: 0;
        }

        .shopee-flow-setting-col-toggle-label {
            font-size: 12px;
            color: #667085;
            margin-top: 4px;
        }

        .shopee-flow-setting-scope-field {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            background: #fff;
        }

        .shopee-flow-setting-scope-copy {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .shopee-flow-setting-scope-title {
            font-size: 16px;
            color: #344054;
            line-height: 1.2;
        }

        .shopee-flow-setting-scope-hint {
            font-size: 12px;
            color: #667085;
            line-height: 1.2;
        }

        .shopee-flow-setting-scope-toggle {
            position: relative;
            width: 54px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            flex: 0 0 auto;
            margin: 0;
        }

        .shopee-flow-setting-scope-toggle input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .shopee-flow-setting-scope-slider {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
            border-radius: 999px;
            background: #31343a;
            transition: all 0.18s ease;
        }

        .shopee-flow-setting-scope-slider::before {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            transition: all 0.18s ease;
        }

        .shopee-flow-setting-scope-slider::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #ffffff;
            font-size: 0.62rem;
            position: absolute;
            left: 10px;
            top: 8px;
            transition: all 0.18s ease;
        }

        .shopee-flow-setting-scope-toggle input:checked + .shopee-flow-setting-scope-slider {
            background: #6f922f;
        }

        .shopee-flow-setting-scope-toggle input:checked + .shopee-flow-setting-scope-slider::before {
            left: 29px;
        }

        .shopee-flow-setting-scope-toggle input:checked + .shopee-flow-setting-scope-slider::after {
            content: "\f00d";
            left: auto;
            right: 10px;
        }
    </style>
</head>
<body>
    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="mb-1"><?= htmlspecialchars($pageTitle) ?></h2>
                <div class="text-muted">Super Admin only. Configure OMS status transition permissions, assignment scope, warehouse stock-out scope, and daily report recipients.</div>
            </div>
        </div>

        <form method="post" id="shopeeFlowSettingForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token']) ?>">
            <div class="card p-3 mb-4">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="assignment_scope">Assignment Scope</label>
                        <input type="hidden" id="assignment_scope" name="assignment_scope" value="<?= $assignmentScopeValue === 'individual' ? 'individual' : 'global' ?>">
                        <div class="shopee-flow-setting-scope-field">
                            <div class="shopee-flow-setting-scope-copy">
                                <span class="shopee-flow-setting-scope-title" id="assignment_scope_text"><?= $assignmentScopeValue === 'individual' ? 'Individual Mode' : 'Global Mode' ?></span>
                                <span class="shopee-flow-setting-scope-hint" id="assignment_scope_hint"><?= $assignmentScopeValue === 'individual' ? 'Only own created orders are assigned.' : 'All users share the same assignment pool.' ?></span>
                            </div>
                            <label class="shopee-flow-setting-scope-toggle" for="assignment_scope_toggle">
                                <input type="checkbox" id="assignment_scope_toggle" <?= $assignmentScopeValue === 'individual' ? 'checked' : '' ?>>
                                <span class="shopee-flow-setting-scope-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="default_warehouse_id">Default Warehouse</label>
                        <select class="form-select" id="default_warehouse_id" name="default_warehouse_id">
                            <option value="0">Not Set</option>
                            <?php foreach ($warehouseRows as $warehouseRow) { ?>
                                <option value="<?= (int) $warehouseRow['id'] ?>" <?= $defaultWarehouseValue === (int) $warehouseRow['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $warehouseRow['name']) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="daily_report_main_supervisor_user_id">Main Report Supervisor</label>
                        <select class="form-select" id="daily_report_main_supervisor_user_id" name="daily_report_main_supervisor_user_id">
                            <option value="0">Not Set</option>
                            <?php foreach ($userRows as $userRow) { ?>
                                <option value="<?= (int) $userRow['id'] ?>" <?= $mainSupervisorValue === (int) $userRow['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $userRow['name']) ?><?= !empty($userRow['email']) ? (' (' . htmlspecialchars((string) $userRow['email']) . ')') : '' ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="daily_report_second_supervisor_user_id">Second Report Supervisor</label>
                        <select class="form-select" id="daily_report_second_supervisor_user_id" name="daily_report_second_supervisor_user_id">
                            <option value="0">Not Set</option>
                            <?php foreach ($userRows as $userRow) { ?>
                                <option value="<?= (int) $userRow['id'] ?>" <?= $secondSupervisorValue === (int) $userRow['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $userRow['name']) ?><?= !empty($userRow['email']) ? (' (' . htmlspecialchars((string) $userRow['email']) . ')') : '' ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Transition Permission Matrix</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Transition</th>
                                <?php foreach ($userGroups as $userGroup) { ?>
                                <th class="text-center">
                                        <div class="d-flex flex-column align-items-center gap-2">
                                            <span><?= htmlspecialchars((string) $userGroup['name']) ?></span>
                                            <?= shopeeOmsRenderUserGroupBadge($connect, isset($userGroup['id']) ? (int) $userGroup['id'] : 0) ?>
                                            <div class="shopee-flow-setting-col-toggle d-flex flex-column align-items-center">
                                                <input
                                                    class="form-check-input shopee-flow-setting-column-toggle"
                                                    type="checkbox"
                                                    value="1"
                                                    data-user-group-id="<?= (int) $userGroup['id'] ?>"
                                                    title="Tick all permissions for this user group"
                                                    <?= !empty($columnToggleStateMap[(int) $userGroup['id']]['all_checked']) ? 'checked' : '' ?>
                                                >
                                                <span class="shopee-flow-setting-col-toggle-label">Tick All</span>
                                            </div>
                                        </div>
                                    </th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transitionRows as $transitionRow) { ?>
                                <?php $transitionKey = isset($transitionRow['key']) ? (string) $transitionRow['key'] : ''; ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) $transitionRow['from_label']) ?> -> <?= htmlspecialchars((string) $transitionRow['to_label']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string) $transitionRow['action']) ?></div>
                                    </td>
                                    <?php foreach ($userGroups as $userGroup) { ?>
                                        <?php $userGroupId = isset($userGroup['id']) ? (int) $userGroup['id'] : 0; ?>
                                        <td class="text-center">
                                            <input
                                                class="form-check-input project-access-checkbox shopee-flow-setting-permission-checkbox"
                                                type="checkbox"
                                                name="perm[<?= htmlspecialchars($transitionKey) ?>][<?= $userGroupId ?>]"
                                                value="1"
                                                data-user-group-id="<?= $userGroupId ?>"
                                                <?= !empty($permissionMap[$transitionKey][$userGroupId]) ? 'checked' : '' ?>
                                            >
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
    </div>
    <script>
        (function () {
            var form = document.getElementById('shopeeFlowSettingForm');
            if (!form) {
                return;
            }

            var saveTimer = null;
            var saveInFlight = false;
            var saveQueued = false;
            var columnToggles = form.querySelectorAll('.shopee-flow-setting-column-toggle');
            var permissionCheckboxes = form.querySelectorAll('.shopee-flow-setting-permission-checkbox');
            var assignmentScopeInput = document.getElementById('assignment_scope');
            var assignmentScopeToggle = document.getElementById('assignment_scope_toggle');
            var assignmentScopeText = document.getElementById('assignment_scope_text');
            var assignmentScopeHint = document.getElementById('assignment_scope_hint');

            function refreshAssignmentScopeDisplay() {
                if (!assignmentScopeInput || !assignmentScopeToggle) {
                    return;
                }

                var isIndividual = assignmentScopeToggle.checked;
                assignmentScopeInput.value = isIndividual ? 'individual' : 'global';

                if (assignmentScopeText) {
                    assignmentScopeText.textContent = isIndividual ? 'Individual Mode' : 'Global Mode';
                }

                if (assignmentScopeHint) {
                    assignmentScopeHint.textContent = isIndividual
                        ? 'Only own created orders are assigned.'
                        : 'All users share the same assignment pool.';
                }
            }

            function saveNow() {
                var formData = new FormData(form);
                formData.append('flow_action', 'save_shopee_flow_setting_ajax');
                saveInFlight = true;

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        window.alert(data && data.message ? data.message : 'Failed to update Flow Setting.');
                    }
                })
                .catch(function () {
                    window.alert('Failed to update Flow Setting.');
                })
                .finally(function () {
                    saveInFlight = false;
                    if (saveQueued) {
                        saveQueued = false;
                        queueSave();
                    }
                });
            }

            function queueSave() {
                if (saveInFlight) {
                    saveQueued = true;
                    return;
                }

                window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(saveNow, 120);
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
            });

            function getColumnPermissionCheckboxes(userGroupId) {
                return form.querySelectorAll('.shopee-flow-setting-permission-checkbox[data-user-group-id="' + userGroupId + '"]');
            }

            function refreshColumnToggleState(userGroupId) {
                var columnCheckboxes = getColumnPermissionCheckboxes(userGroupId);
                var toggle = form.querySelector('.shopee-flow-setting-column-toggle[data-user-group-id="' + userGroupId + '"]');
                if (!toggle || !columnCheckboxes.length) {
                    return;
                }

                var checkedCount = 0;
                columnCheckboxes.forEach(function (checkbox) {
                    if (checkbox.checked) {
                        checkedCount++;
                    }
                });
                var allChecked = Array.prototype.every.call(columnCheckboxes, function (checkbox) {
                    return checkbox.checked;
                });
                toggle.indeterminate = checkedCount > 0 && checkedCount < columnCheckboxes.length;
                toggle.checked = allChecked;
            }

            columnToggles.forEach(function (toggle) {
                toggle.addEventListener('change', function () {
                    var userGroupId = toggle.getAttribute('data-user-group-id');
                    getColumnPermissionCheckboxes(userGroupId).forEach(function (checkbox) {
                        checkbox.checked = toggle.checked;
                    });
                    queueSave();
                });

                refreshColumnToggleState(toggle.getAttribute('data-user-group-id'));
            });

            permissionCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    refreshColumnToggleState(checkbox.getAttribute('data-user-group-id'));
                });
            });

            if (assignmentScopeToggle) {
                refreshAssignmentScopeDisplay();
                assignmentScopeToggle.addEventListener('change', function () {
                    refreshAssignmentScopeDisplay();
                    queueSave();
                });
            }

            form.querySelectorAll('select, input[type="checkbox"]').forEach(function (field) {
                field.addEventListener('change', queueSave);
            });
        })();
    </script>
</body>
</html>
