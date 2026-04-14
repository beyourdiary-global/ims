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
                <div id="taskItemActivityAllList" class="task-item-activity-feed">
                    <div class="task-item-activity-empty">No activity yet.</div>
                </div>
            </div>

            <div id="taskItemActivityPanelComment" class="task-item-activity-panel mt-3 d-none" role="tabpanel" aria-labelledby="taskItemActivityTabComment" hidden>
                <div class="task-item-activity-compose">
                    <textarea class="form-control" rows="3" placeholder="Add a comment..." aria-label="Add a comment"></textarea>
                </div>
            </div>

            <div id="taskItemActivityPanelHistory" class="task-item-activity-panel mt-3 d-none" role="tabpanel" aria-labelledby="taskItemActivityTabHistory" hidden>
                <div id="taskItemActivityHistoryList" class="task-item-activity-feed">
                    <div class="task-item-activity-empty">No history yet.</div>
                </div>
            </div>
        </div>
        <?php
    }
}
