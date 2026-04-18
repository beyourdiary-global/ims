<div class="modal fade" id="taskItemDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable task-item-detail-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="taskItemDetailModalTitle" class="modal-title">Work item</h5>
                <div class="task-item-detail-header-actions">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-8 task-item-detail-main-col">
                        <div id="taskItemDetailKeyTrail" class="task-item-detail-key-trail d-none"></div>
                        <div class="mb-3">
                            <div class="task-item-detail-title-row">
                                <input id="taskItemDetailTitleInput" class="form-control task-item-detail-title-input" type="text" maxlength="255" placeholder="Work item name">
                                <button id="taskItemDetailTitleSaveBtn" class="btn task-item-detail-title-btn task-item-detail-title-btn-save" type="button" title="Save title" aria-label="Save title">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                <button id="taskItemDetailTitleResetBtn" class="btn task-item-detail-title-btn task-item-detail-title-btn-cancel" type="button" title="Cancel title edit" aria-label="Cancel title edit">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
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
                            <div id="taskItemDetailAutosaveStatus" class="task-item-detail-autosave-status d-none" aria-live="polite"></div>
                        </div>
                        <div class="mb-3">
                                <div class="task-item-detail-description-section" id="taskItemDetailDescriptionSection">
                                    <div class="task-item-detail-description-header">
                                        <button id="taskItemDetailDescriptionCollapseBtn" type="button" class="btn task-item-detail-description-collapse-btn" aria-expanded="true" title="Collapse description">
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </button>
                                        <label class="form-label mb-0" for="taskItemDetailDescriptionInput">Description</label>
                                    </div>
                                    <div id="taskItemDetailDescriptionBody" class="task-item-detail-description-body">
                                        <div id="taskItemDetailDescriptionViewWrap" class="task-item-detail-description-view-wrap">
                                            <div id="taskItemDetailDescriptionView" class="task-item-detail-description-view is-empty" role="button" tabindex="0" aria-label="Add or edit description">
                                                <span id="taskItemDetailDescriptionViewText" class="task-item-detail-description-view-text">Add a description...</span>
                                                <div id="taskItemDetailDescriptionViewContent" class="task-item-detail-description-rendered d-none"></div>
                                            </div>
                                            <div id="taskItemDetailDescriptionDraftNotice" class="task-item-draft-reminder d-none">
                                                <button id="taskItemDetailDescriptionDraftRestoreBtn" type="button" class="btn task-item-draft-reminder-btn">You have unsaved description</button>
                                            </div>
                                        </div>
                                        <div id="taskItemDetailDescriptionEditWrap" class="task-item-detail-description-edit-wrap d-none">
                                            <textarea id="taskItemDetailDescriptionInput" class="form-control" rows="6" placeholder="Description"></textarea>
                                            <div class="task-item-detail-description-actions">
                                                <button id="taskItemDetailDescriptionSaveBtn" type="button" class="btn btn-primary">Save</button>
                                                <button id="taskItemDetailDescriptionCancelBtn" type="button" class="btn btn-light">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

                        <?php taskRenderBoardItemHistorySection(); ?>
                    </div>
                    <div class="col-12 col-lg-4 task-item-detail-side-col">
                        <div id="taskItemDetailSideCard" class="task-item-detail-side-card">
                            <div class="task-item-detail-board-status-wrap mb-3">
                                <div class="dropdown task-item-detail-board-status-dropdown">
                                    <button id="taskItemDetailBoardStatusBtn" class="btn task-item-detail-board-status-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Select status</button>
                                    <div id="taskItemDetailBoardStatusMenu" class="dropdown-menu task-item-detail-board-status-menu p-2">
                                        <div id="taskItemDetailBoardStatusOptionList" class="task-item-detail-board-status-option-list"></div>
                                    </div>
                                </div>
                            </div>

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
                                <label class="task-item-detail-field-label" for="taskItemDetailParentSearchInput">Parent</label>
                                <div class="task-item-detail-parent-wrap">
                                    <div class="dropdown task-item-detail-parent-dropdown">
                                        <button id="taskItemDetailParentDropdownBtn" class="btn task-item-detail-parent-dropdown-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                            <span id="taskItemDetailParentSelectedText" class="task-item-detail-parent-selected-text">None</span>
                                            <i class="fa-solid fa-chevron-down task-item-detail-dropdown-icon" aria-hidden="true"></i>
                                        </button>
                                        <div id="taskItemDetailParentMenu" class="dropdown-menu task-item-detail-parent-menu p-2">
                                            <input id="taskItemDetailParentSearchInput" class="form-control form-control-sm mb-2" type="text" maxlength="120" autocomplete="off" placeholder="Search parent">
                                            <div id="taskItemDetailParentOptionList" class="task-item-detail-parent-option-list"></div>
                                        </div>
                                    </div>
                                </div>
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

                            <div id="taskItemWorklogTimerSection" class="task-item-worklog-section mt-3">
                                <div class="task-item-worklog-header">
                                    <button id="taskItemWorklogToggleBtn" type="button" class="btn task-item-worklog-toggle-btn" aria-expanded="true" title="Collapse worklog timer">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                    <span class="task-item-worklog-title">Simple Worklog Timer</span>
                                </div>
                                <div id="taskItemWorklogBody" class="task-item-worklog-body">
                                    <div class="task-item-worklog-display" id="taskItemWorklogDisplay">
                                        <div class="task-item-worklog-number" id="taskItemWorklogDays">00</div>
                                        <div class="task-item-worklog-sep">:</div>
                                        <div class="task-item-worklog-number" id="taskItemWorklogHours">00</div>
                                        <div class="task-item-worklog-sep">:</div>
                                        <div class="task-item-worklog-number" id="taskItemWorklogMinutes">00</div>
                                        <div class="task-item-worklog-sep">:</div>
                                        <div class="task-item-worklog-number" id="taskItemWorklogSeconds">00</div>
                                        <div class="task-item-worklog-label">DAYS</div>
                                        <div></div>
                                        <div class="task-item-worklog-label">HOURS</div>
                                        <div></div>
                                        <div class="task-item-worklog-label">MINUTES</div>
                                        <div></div>
                                        <div class="task-item-worklog-label">SECONDS</div>
                                    </div>
                                    <div id="taskItemWorklogActions" class="task-item-worklog-actions mt-3">
                                        <button type="button" id="taskItemWorklogStartBtn" class="btn task-worklog-btn task-worklog-btn-start">Start <i class="fa-solid fa-play"></i></button>
                                        <button type="button" id="taskItemWorklogSaveBtn" class="btn task-worklog-btn task-worklog-btn-save d-none">Save in Work log</button>
                                        <button type="button" id="taskItemWorklogStopBtn" class="btn task-worklog-btn task-worklog-btn-stop d-none">Stop <i class="fa-solid fa-stop"></i></button>
                                        <button type="button" id="taskItemWorklogContinueBtn" class="btn task-worklog-btn task-worklog-btn-continue d-none">Continue</button>
                                        <button type="button" id="taskItemWorklogResetBtn" class="btn task-worklog-btn task-worklog-btn-reset d-none">Reset time</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="taskBoardToastHost" class="task-board-toast-host" aria-live="polite" aria-atomic="true"></div>
