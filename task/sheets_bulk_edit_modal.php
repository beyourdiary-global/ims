<div class="modal fade" id="sheetsBulkEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable task-bulk-edit-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Edit Work Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="taskBulkEditAlert" class="task-bulk-edit-alert d-none" role="alert"></div>

                <div class="task-bulk-edit-layout">
                    <aside class="task-bulk-edit-stepper" aria-label="Bulk edit steps">
                        <button type="button" class="task-bulk-step is-active" data-bulk-step-link="1">
                            <span class="task-bulk-step-dot">1</span><span>Choose work items</span>
                        </button>
                        <button type="button" class="task-bulk-step" data-bulk-step-link="2">
                            <span class="task-bulk-step-dot">2</span><span>Choose operation</span>
                        </button>
                        <button type="button" class="task-bulk-step" data-bulk-step-link="3">
                            <span class="task-bulk-step-dot">3</span><span>Operation details</span>
                        </button>
                        <button type="button" class="task-bulk-step" data-bulk-step-link="4">
                            <span class="task-bulk-step-dot">4</span><span>Confirmation</span>
                        </button>
                    </aside>

                    <section class="task-bulk-edit-content">
                        <section class="task-bulk-panel" data-bulk-step="1">
                            <div class="task-bulk-panel-heading">
                                <div>
                                    <h2>Step 1 of 4: Choose work items</h2>
                                    <p id="taskBulkParentSummary">Loading work items...</p>
                                </div>
                                <span class="task-bulk-limit-note"><i class="fa-solid fa-circle-info"></i> Maximum 1,000 selected</span>
                            </div>
                            <div class="task-bulk-selection-toolbar">
                                <label class="task-bulk-checkbox-label"><input id="taskBulkSelectAll" type="checkbox"> Select all</label>
                                <span id="taskBulkSelectionCount">0 selected</span>
                            </div>
                            <div class="task-bulk-table-wrap">
                                <table class="table task-bulk-table" id="taskBulkItemsTable">
                                    <thead>
                                    <tr>
                                        <th class="task-bulk-check-cell"></th>
                                        <th>Key</th>
                                        <th>Summary</th>
                                        <th>Assignee</th>
                                        <th>Reporter</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody><tr><td colspan="7" class="task-bulk-empty">Loading...</td></tr></tbody>
                                </table>
                            </div>
                            <div class="task-bulk-actions"><button type="button" class="btn btn-primary" data-bulk-next="1">Next</button></div>
                        </section>

                        <section class="task-bulk-panel d-none" data-bulk-step="2">
                            <div class="task-bulk-panel-heading">
                                <div><h2>Step 2 of 4: Choose operation</h2><p>Choose one action for the selected work items.</p></div>
                            </div>
                            <div class="task-bulk-operation-list">
                                <label class="task-bulk-operation-card <?= $workItemCanEdit ? '' : 'is-disabled' ?>">
                                    <input type="radio" name="bulk_operation" value="edit" <?= $workItemCanEdit ? '' : 'disabled' ?>>
                                    <span><strong>Edit</strong><small>Edit field values on the selected work items.</small></span>
                                </label>
                                <label class="task-bulk-operation-card <?= $workItemCanEdit ? '' : 'is-disabled' ?>">
                                    <input type="radio" name="bulk_operation" value="move" <?= $workItemCanEdit ? '' : 'disabled' ?>>
                                    <span><strong>Move</strong><small>Change the work type and/or parent in this project.</small></span>
                                </label>
                                <label class="task-bulk-operation-card <?= $workItemCanEdit ? '' : 'is-disabled' ?>">
                                    <input type="radio" name="bulk_operation" value="transition" <?= $workItemCanEdit ? '' : 'disabled' ?>>
                                    <span><strong>Transitions</strong><small>Move the selected work items to another status column.</small></span>
                                </label>
                                <label class="task-bulk-operation-card <?= $workItemCanDelete ? '' : 'is-disabled' ?>">
                                    <input type="radio" name="bulk_operation" value="delete" <?= $workItemCanDelete ? '' : 'disabled' ?>>
                                    <span><strong>Delete</strong><small>Soft-delete the selected work items.</small></span>
                                </label>
                            </div>
                            <div class="task-bulk-actions"><button type="button" class="btn btn-light" data-bulk-back="2">Back</button><button type="button" class="btn btn-primary" data-bulk-next="2">Next</button></div>
                        </section>

                        <section class="task-bulk-panel d-none" data-bulk-step="3">
                            <div class="task-bulk-panel-heading"><div><h2>Step 3 of 4: Operation details</h2><p id="taskBulkDetailsIntro">Configure the selected operation.</p></div></div>
                            <div id="taskBulkOperationDetails"></div>
                            <div class="task-bulk-actions"><button type="button" class="btn btn-light" data-bulk-back="3">Back</button><button type="button" class="btn btn-primary" data-bulk-next="3">Next</button></div>
                        </section>

                        <section class="task-bulk-panel d-none" data-bulk-step="4">
                            <div class="task-bulk-panel-heading"><div><h2>Step 4 of 4: Confirmation</h2><p>Review the changes before they are applied. The server will reload and validate every selected item.</p></div></div>
                            <div id="taskBulkSummary"></div>
                            <div class="task-bulk-confirm-note"><i class="fa-solid fa-triangle-exclamation"></i><span>This operation is applied to all selected work items in one transaction. If any item fails validation, no changes will be saved.</span></div>
                            <div class="task-bulk-actions"><button type="button" class="btn btn-light" data-bulk-back="4">Back</button><button type="button" id="taskBulkConfirmBtn" class="btn btn-primary">Confirm</button></div>
                        </section>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.taskBulkEditConfig = <?= json_encode(array(
    'mode' => 'project',
    'ajaxUrl' => 'sheets.php' . ($currentProjectId > 0 ? '?project_id=' . $currentProjectId : ''),
    'siteUrl' => rtrim((string) $SITEURL, '/'),
    'projectId' => $currentProjectId,
    'csrfToken' => (string) $_SESSION['csrf_token'],
    'canEdit' => $workItemCanEdit ? 1 : 0,
    'canDelete' => $workItemCanDelete ? 1 : 0,
    'workTypes' => $workTypesForBulk,
    'columns' => $columns,
    'assignees' => $assignees,
    'labels' => $labels,
    'statusLabels' => $statusLabels,
    'parentOptions' => $parentOptionsForBulk,
    'allColumns' => $allColumnsForBulk,
    'workflowColumns' => $columns,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
