<?php

if (!function_exists('taskRenderBoardItemHistorySection')) {
    function taskRenderBoardItemHistorySection()
    {
        ?>
        <div class="task-item-activity-section">
            <h5 class="mb-2">Activity</h5>
            <ul class="nav nav-tabs task-item-activity-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button id="taskItemActivityTabAll" class="nav-link active task-item-activity-tab" type="button" role="tab" aria-selected="true" aria-controls="taskItemActivityPanelAll" data-tab-target="all">All</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="taskItemActivityTabComment" class="nav-link task-item-activity-tab" type="button" role="tab" aria-selected="false" aria-controls="taskItemActivityPanelComment" data-tab-target="comment">Comment</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="taskItemActivityTabHistory" class="nav-link task-item-activity-tab" type="button" role="tab" aria-selected="false" aria-controls="taskItemActivityPanelHistory" data-tab-target="history">History</button>
                </li>
            </ul>

            <div id="taskItemActivityPanelAll" class="task-item-activity-panel mt-3" role="tabpanel" aria-labelledby="taskItemActivityTabAll">
                <div class="task-item-activity-panel-head">
                    <button type="button" class="btn task-item-activity-sort-btn" data-tab="all" title="Reverse sort direction" aria-label="Reverse sort direction">
                        <i class="fa-solid fa-arrow-down-short-wide"></i>
                    </button>
                </div>
                <div id="taskItemActivityAllList" class="task-item-activity-feed">
                    <div class="task-item-activity-empty">No activity yet.</div>
                </div>
            </div>

            <div id="taskItemActivityPanelComment" class="task-item-activity-panel mt-3 d-none" role="tabpanel" aria-labelledby="taskItemActivityTabComment">
                <div class="task-item-activity-panel-head">
                    <button type="button" class="btn task-item-activity-sort-btn" data-tab="comment" title="Reverse sort direction" aria-label="Reverse sort direction">
                        <i class="fa-solid fa-arrow-down-short-wide"></i>
                    </button>
                </div>
                <div id="taskItemCommentDraftNotice" class="task-item-draft-reminder d-none">
                    <button id="taskItemCommentDraftRestoreBtn" type="button" class="btn task-item-draft-reminder-btn">You have unsaved comment</button>
                </div>
                <div id="taskItemCommentComposeLauncherWrap" class="task-item-comment-compose-launcher-wrap">
                    <button id="taskItemCommentComposeLauncher" type="button" class="btn task-item-comment-compose-launcher" aria-label="Add comment">Add a comment...</button>
                </div>
                <div id="taskItemCommentComposeWrap" class="task-item-activity-compose d-none">
                    <textarea id="taskItemCommentEditor" rows="4" placeholder="Type @ to mention and notify someone."></textarea>
                    <div class="task-item-comment-actions mt-2">
                        <button id="taskItemCommentSaveBtn" type="button" class="btn btn-primary btn-sm" disabled>Save</button>
                        <button id="taskItemCommentCancelBtn" type="button" class="btn btn-light btn-sm">Cancel</button>
                    </div>
                </div>
                <div id="taskItemActivityCommentList" class="task-item-activity-comment-list mb-3">
                    <div class="task-item-activity-empty">No comments yet.</div>
                </div>
            </div>

            <div id="taskItemActivityPanelHistory" class="task-item-activity-panel mt-3 d-none" role="tabpanel" aria-labelledby="taskItemActivityTabHistory">
                <div class="task-item-activity-panel-head">
                    <button type="button" class="btn task-item-activity-sort-btn" data-tab="history" title="Reverse sort direction" aria-label="Reverse sort direction">
                        <i class="fa-solid fa-arrow-down-short-wide"></i>
                    </button>
                </div>
                <div id="taskItemActivityHistoryList" class="task-item-activity-feed">
                    <div class="task-item-activity-empty">No history yet.</div>
                </div>
            </div>
        </div>
        <?php
    }
}
