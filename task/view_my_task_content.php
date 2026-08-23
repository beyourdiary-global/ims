<?php
// Shared fragment: rendered inline on full page load and returned alone for
// the toolbar Refresh button (view_my_task.php?ajax=table).
if (!defined('USER_ID')) {
    exit;
}
?>
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
