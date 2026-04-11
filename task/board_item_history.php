<?php

if (!function_exists('taskRenderBoardItemHistorySection')) {
    function taskRenderBoardItemHistorySection()
    {
        ?>
        <div class="task-item-activity-section">
            <h5 class="mb-2">Activity</h5>
            <ul class="nav nav-tabs task-item-activity-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active task-item-activity-tab" type="button" data-tab-target="all">All</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link task-item-activity-tab" type="button" data-tab-target="comment">Comment</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link task-item-activity-tab" type="button" data-tab-target="history">History</button>
                </li>
            </ul>

            <div id="taskItemActivityPanelAll" class="task-item-activity-panel mt-3">
                <div id="taskItemActivityAllList" class="task-item-activity-feed">
                    <div class="task-item-activity-empty">No activity yet.</div>
                </div>
            </div>

            <div id="taskItemActivityPanelComment" class="task-item-activity-panel mt-3 d-none">
                <div class="task-item-activity-compose">
                    <textarea class="form-control" rows="3" placeholder="Add a comment..."></textarea>
                </div>
            </div>

            <div id="taskItemActivityPanelHistory" class="task-item-activity-panel mt-3 d-none">
                <div id="taskItemActivityHistoryList" class="task-item-activity-feed">
                    <div class="task-item-activity-empty">No history yet.</div>
                </div>
            </div>
        </div>
        <?php
    }
}
