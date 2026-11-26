<?php

if (!function_exists('customerTimelinePreviewEscape')) {
    function customerTimelinePreviewEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('customerTimelinePreviewTableExists')) {
    function customerTimelinePreviewTableExists($connect, $tableName)
    {
        static $cache = array();
        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        if (!($connect instanceof mysqli) || $tableName === '') {
            return false;
        }

        $cacheKey = spl_object_hash($connect) . '|' . $tableName;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = false;
        $result = mysqli_query($connect, 'SHOW TABLES');
        if ($result) {
            while ($row = mysqli_fetch_row($result)) {
                if (isset($row[0]) && (string) $row[0] === $tableName) {
                    $cache[$cacheKey] = true;
                    break;
                }
            }
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('customerTimelinePreviewActionTitle')) {
    function customerTimelinePreviewActionTitle($actionType)
    {
        $labels = array(
            'submit_follow_up' => 'Follow-up submitted',
            'approve_follow_up' => 'Follow-up approved',
            'reject_follow_up' => 'Follow-up rejected',
            'complete_follow_up' => 'Follow-up completed',
            'request_postponement' => 'Postponement requested',
            'approve_postponement' => 'Postponement approved',
            'reject_postponement' => 'Postponement rejected',
            'schedule_next_follow_up_from_customer_page' => 'Next follow-up scheduled',
            'overwrite_follow_up_date' => 'Follow-up date changed',
            'adjust_follow_up_round' => 'Follow-up round changed',
            'merged_duplicate_case' => 'Duplicate case merged',
        );
        $actionType = trim((string) $actionType);
        if (isset($labels[$actionType])) {
            return $labels[$actionType];
        }

        return $actionType !== '' ? ucwords(str_replace('_', ' ', $actionType)) : 'Follow-up action';
    }
}

if (!function_exists('customerTimelinePreviewRenderAttachmentLinks')) {
    function customerTimelinePreviewRenderAttachmentLinks($attachments)
    {
        if (!is_array($attachments) || empty($attachments) || !function_exists('urlBuildUserRecordLogAttachmentUrl')) {
            return '';
        }

        $html = '<div class="ct-attachments"><strong>Proof / attachments</strong><div class="ct-attachment-list">';
        $count = 0;
        foreach ($attachments as $attachment) {
            $attachment = trim(str_replace('\\', '/', (string) $attachment));
            if ($attachment === '') {
                continue;
            }
            $url = urlBuildUserRecordLogAttachmentUrl($attachment, function_exists('urlGetUserRecordLogUploadWebDir') ? urlGetUserRecordLogUploadWebDir() : '');
            if ($url === '') {
                continue;
            }
            $fileName = basename($attachment);
            $html .= '<a class="ct-attachment-link" href="' . customerTimelinePreviewEscape($url) . '" target="_blank" rel="noopener noreferrer">'
                . customerTimelinePreviewEscape($fileName !== '' ? $fileName : 'Open file') . '</a>';
            $count++;
        }
        $html .= '</div></div>';

        return $count > 0 ? $html : '';
    }
}

if (!function_exists('customerTimelinePreviewRenderNote')) {
    function customerTimelinePreviewRenderNote($connect, $row, $related = false, $renderAttachments = true)
    {
        $summary = trim((string) (isset($row['summary']) ? $row['summary'] : ''));
        $content = trim((string) (isset($row['content']) ? $row['content'] : ''));
        $plainContent = function_exists('urlGetUserRecordLogContentPlainText')
            ? trim((string) urlGetUserRecordLogContentPlainText($content))
            : trim(strip_tags($content));
        $createdBy = trim((string) (isset($row['created_by']) ? $row['created_by'] : ''));
        $actor = function_exists('urlGetUserName') ? urlGetUserName($connect, $createdBy) : $createdBy;
        $createdAt = trim((string) (isset($row['created_at']) ? $row['created_at'] : ''));
        $updatedAt = trim((string) (isset($row['updated_at']) ? $row['updated_at'] : ''));
        $updatedBy = trim((string) (isset($row['updated_by']) ? $row['updated_by'] : ''));
        $title = $related ? 'Related customer note' : 'Customer note';
        $systemRecord = function_exists('urlIsUserRecordLogSystemRecord')
            && urlIsUserRecordLogSystemRecord($row, $actor);
        if ($systemRecord) {
            $title = $related ? 'Related system note' : 'System record';
        }

        $html = '<div class="ct-note">';
        $html .= '<div class="ct-note-heading">' . customerTimelinePreviewEscape($title) . '</div>';
        if ($summary !== '') {
            $summaryHtml = function_exists('urlRenderUserRecordLogContentHtml')
                ? urlRenderUserRecordLogContentHtml($summary)
                : customerTimelinePreviewEscape(strip_tags($summary));
            $html .= '<div class="ct-note-summary">' . $summaryHtml . '</div>';
        }
        if ($plainContent !== '') {
            $excerpt = function_exists('mb_substr') ? mb_substr($plainContent, 0, 240, 'UTF-8') : substr($plainContent, 0, 240);
            if (strlen($plainContent) > 240) {
                $excerpt .= '...';
            }
            $html .= '<details class="ct-note-details"><summary>' . customerTimelinePreviewEscape($excerpt !== '' ? $excerpt : 'View note details') . '</summary>';
            $contentHtml = function_exists('urlRenderUserRecordLogContentHtml')
                ? urlRenderUserRecordLogContentHtml($content)
                : nl2br(customerTimelinePreviewEscape(strip_tags($content)));
            $html .= '<div class="ct-note-content">' . $contentHtml . '</div></details>';
        }

        if ($renderAttachments) {
            $attachments = array();
            if (function_exists('urlDecodeUserRecordLogAttachmentSequence')) {
                $attachments = urlDecodeUserRecordLogAttachmentSequence(
                    isset($row['attachment']) ? $row['attachment'] : '',
                    isset($row['attachment_sequence']) ? $row['attachment_sequence'] : ''
                );
            }
            $html .= customerTimelinePreviewRenderAttachmentLinks($attachments);
        }
        $html .= '<div class="ct-note-meta">Created ' . customerTimelinePreviewEscape($createdAt !== '' ? $createdAt : 'time unavailable')
            . ' by ' . customerTimelinePreviewEscape($actor !== '' ? $actor : 'unknown') . '</div>';
        if ($updatedAt !== '' && $updatedAt !== $createdAt) {
            $updatedActor = function_exists('urlGetUserName') ? urlGetUserName($connect, $updatedBy) : $updatedBy;
            $html .= '<div class="ct-note-meta">Last edited ' . customerTimelinePreviewEscape($updatedAt)
                . ' by ' . customerTimelinePreviewEscape($updatedActor !== '' ? $updatedActor : 'unknown') . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('customerTimelinePreviewRenderAction')) {
    function customerTimelinePreviewRenderAction($connect, $actionRow)
    {
        $actionType = trim((string) (isset($actionRow['action_type']) ? $actionRow['action_type'] : ''));
        $actionBy = trim((string) (isset($actionRow['action_by']) ? $actionRow['action_by'] : ''));
        $actor = $actionBy;
        if ($actionBy !== '' && ctype_digit($actionBy) && function_exists('customerFollowUpGetUserDisplayName')) {
            $actor = customerFollowUpGetUserDisplayName($connect, (int) $actionBy);
        }
        $actionAt = trim((string) (isset($actionRow['action_date']) ? $actionRow['action_date'] : '') . ' ' . (isset($actionRow['action_time']) ? $actionRow['action_time'] : ''));
        $remark = trim((string) (isset($actionRow['remark']) ? $actionRow['remark'] : ''));
        $oldValue = function_exists('customerFollowUpDecodeActionLogPayload')
            ? customerFollowUpDecodeActionLogPayload(isset($actionRow['old_value']) ? $actionRow['old_value'] : '')
            : array();
        $newValue = function_exists('customerFollowUpDecodeActionLogPayload')
            ? customerFollowUpDecodeActionLogPayload(isset($actionRow['new_value']) ? $actionRow['new_value'] : '')
            : array();
        $diffLabels = array(
            'approval_status' => 'Approval',
            'round_status' => 'Status',
            'postpone_status' => 'Postponement',
            'next_follow_up_date' => 'Next follow-up date',
            'requested_next_follow_up_date' => 'Requested date',
            'approved_next_follow_up_date' => 'Approved date',
            'round_no' => 'Round',
        );

        $html = '<div class="ct-action">';
        $html .= '<div class="ct-action-heading"><strong>' . customerTimelinePreviewEscape(customerTimelinePreviewActionTitle($actionType)) . '</strong>';
        $html .= '<span>' . customerTimelinePreviewEscape($actionAt !== '' ? $actionAt : 'Time unavailable') . '</span></div>';
        $html .= '<div class="ct-action-meta">By ' . customerTimelinePreviewEscape($actor !== '' ? $actor : 'unknown administrator') . '</div>';
        foreach ($diffLabels as $field => $label) {
            if (!array_key_exists($field, $newValue)) {
                continue;
            }
            $from = isset($oldValue[$field]) ? trim((string) $oldValue[$field]) : '';
            $to = trim((string) $newValue[$field]);
            if ($to === '' || $from === $to) {
                continue;
            }
            if (in_array($field, array('approval_status', 'round_status', 'postpone_status'), true)
                && function_exists('customerFollowUpFormatStateValueLabel')
            ) {
                $from = customerFollowUpFormatStateValueLabel($from);
                $to = customerFollowUpFormatStateValueLabel($to);
            }
            $html .= '<div class="ct-action-change"><strong>' . customerTimelinePreviewEscape($label) . ':</strong> '
                . customerTimelinePreviewEscape($from !== '' ? $from : 'Not set') . ' to ' . customerTimelinePreviewEscape($to) . '</div>';
        }
        if ($remark !== '') {
            $html .= '<div class="ct-action-remark">' . nl2br(customerTimelinePreviewEscape($remark)) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('customerTimelineRenderCustomerInfoPreview')) {
    function customerTimelineRenderCustomerInfoPreview($connect, $customerId)
    {
        $customerId = (int) $customerId;
        if (!($connect instanceof mysqli) || $customerId <= 0 || !defined('USER_RECORD_LOG')) {
            return;
        }

        $events = array();
        $groups = array();
        $followUpRows = array();
        $caseIds = array();
        $caseMap = array();
        $canViewAllFollowUpRecords = function_exists('customerFollowUpIsAdminUser')
            && customerFollowUpIsAdminUser(defined('USER_GROUP') ? USER_GROUP : null);
        if (function_exists('customerFollowUpFetchActiveCasesByCustomer')) {
            $followUpRows = customerFollowUpFetchActiveCasesByCustomer($connect, 'customer_info', $customerId);
        }
        if (!$canViewAllFollowUpRecords) {
            $currentUserId = defined('USER_ID') ? (int) USER_ID : 0;
            $visibleFollowUpRows = array();
            foreach ($followUpRows as $followUpRow) {
                if ($currentUserId > 0 && (int) (isset($followUpRow['assigned_user_id']) ? $followUpRow['assigned_user_id'] : 0) === $currentUserId) {
                    $visibleFollowUpRows[] = $followUpRow;
                }
            }
            $followUpRows = $visibleFollowUpRows;
        }
        foreach ($followUpRows as $followUpRow) {
            $followUpId = (int) (isset($followUpRow['id']) ? $followUpRow['id'] : 0);
            if ($followUpId > 0) {
                $caseIds[] = $followUpId;
                $caseMap[$followUpId] = $followUpRow;
            }
        }

        $logTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) USER_RECORD_LOG);
        if (customerTimelinePreviewTableExists($connect, $logTable)
            && function_exists('urlUserRecordLogColumnExists')
            && urlUserRecordLogColumnExists($connect, $logTable, 'cust_id')
        ) {
            $where = "`status`='A' AND `cust_id`=" . $customerId . " AND (IFNULL(`content`,'')<>'' OR IFNULL(`attachment`,'')<>'')";
            if (urlUserRecordLogColumnExists($connect, $logTable, 'log_type')) {
                $where .= " AND (IFNULL(`log_type`,'')='' OR `log_type`<>'tag')";
            }
            $result = mysqli_query($connect, "SELECT * FROM `" . $logTable . "` WHERE " . $where . " ORDER BY GREATEST(IFNULL(`updated_at`,`created_at`),`created_at`) DESC, `id` DESC LIMIT 500");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $hasCaseLink = urlUserRecordLogColumnExists($connect, $logTable, 'follow_up_id')
                        && (int) (isset($row['follow_up_id']) ? $row['follow_up_id'] : 0) > 0;
                    $followUpId = $hasCaseLink ? (int) $row['follow_up_id'] : 0;
                    $roundId = $hasCaseLink && urlUserRecordLogColumnExists($connect, $logTable, 'follow_up_round_id')
                        ? (int) (isset($row['follow_up_round_id']) ? $row['follow_up_round_id'] : 0)
                        : 0;
                    if ($followUpId > 0 && !$canViewAllFollowUpRecords && !isset($caseMap[$followUpId])) {
                        $followUpId = 0;
                        $roundId = 0;
                    }
                    $key = $hasCaseLink && $followUpId > 0
                        ? ('follow-up-' . $followUpId . '-' . ($roundId > 0 ? ('round-' . $roundId) : 'case'))
                        : ('note-' . (int) (isset($row['id']) ? $row['id'] : 0));
                    if (!isset($groups[$key])) {
                        $groups[$key] = array(
                            'key' => $key,
                            'follow_up_id' => $followUpId,
                            'round_id' => $roundId,
                            'notes' => array(),
                            'actions' => array(),
                            'timestamp' => '',
                            'round_no' => 0,
                        );
                    }
                    $groups[$key]['notes'][] = $row;
                    $createdAt = trim((string) (isset($row['created_at']) ? $row['created_at'] : ''));
                    $updatedAt = trim((string) (isset($row['updated_at']) ? $row['updated_at'] : ''));
                    $candidateAt = $updatedAt !== '' && $updatedAt > $createdAt ? $updatedAt : $createdAt;
                    if ($candidateAt > $groups[$key]['timestamp']) {
                        $groups[$key]['timestamp'] = $candidateAt;
                    }
                }
            }
        }

        $roundMap = array();
        if (!empty($caseIds) && defined('CUSTOMER_FOLLOW_UP_ROUND')
            && customerTimelinePreviewTableExists($connect, CUSTOMER_FOLLOW_UP_ROUND)
            && function_exists('urlUserRecordLogColumnExists')
        ) {
            $safeRoundTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) CUSTOMER_FOLLOW_UP_ROUND);
            $hasRoundFollowUpId = urlUserRecordLogColumnExists($connect, $safeRoundTable, 'follow_up_id');
            $hasRoundStatus = urlUserRecordLogColumnExists($connect, $safeRoundTable, 'status');
            if ($hasRoundFollowUpId) {
                $roundFields = array('`id`', '`follow_up_id`');
                foreach (array('round_no', 'next_follow_up_date', 'attachment') as $optionalRoundField) {
                    if (urlUserRecordLogColumnExists($connect, $safeRoundTable, $optionalRoundField)) {
                        $roundFields[] = '`' . $optionalRoundField . '`';
                    }
                }
                $caseIdSql = implode(',', array_map('intval', $caseIds));
                $roundWhere = '`follow_up_id` IN (' . $caseIdSql . ')';
                if ($hasRoundStatus) {
                    $roundWhere .= " AND `status`='A'";
                }
                $roundResult = mysqli_query($connect, "SELECT " . implode(', ', $roundFields) . " FROM `" . $safeRoundTable . "` WHERE " . $roundWhere . " ORDER BY `id` DESC");
                if ($roundResult) {
                    while ($roundRow = mysqli_fetch_assoc($roundResult)) {
                        $roundMap[(int) $roundRow['id']] = $roundRow;
                    }
                }
            }
        }

        $canViewFollowUpActionLogs = defined('USER_GROUP') && (int) USER_GROUP === 1;
        $actionLogTableAvailable = defined('CUSTOMER_FOLLOW_UP_ACTION_LOG')
            && customerTimelinePreviewTableExists($connect, CUSTOMER_FOLLOW_UP_ACTION_LOG);
        if ($canViewFollowUpActionLogs && $actionLogTableAvailable) {
            foreach ($followUpRows as $followUpRow) {
                $followUpId = (int) (isset($followUpRow['id']) ? $followUpRow['id'] : 0);
                if ($followUpId <= 0 || !function_exists('customerFollowUpFetchActionLogRows')) {
                    continue;
                }
                foreach (customerFollowUpFetchActionLogRows($connect, $followUpId, 100) as $actionRow) {
                    $roundId = (int) (isset($actionRow['round_id']) ? $actionRow['round_id'] : 0);
                    $key = 'follow-up-' . $followUpId . '-' . ($roundId > 0 ? ('round-' . $roundId) : 'case');
                    if (!isset($groups[$key])) {
                        $groups[$key] = array(
                            'key' => $key,
                            'follow_up_id' => $followUpId,
                            'round_id' => $roundId,
                            'notes' => array(),
                            'actions' => array(),
                            'timestamp' => '',
                            'round_no' => 0,
                        );
                    }
                    $groups[$key]['actions'][] = $actionRow;
                    $actionAt = trim((string) (isset($actionRow['action_date']) ? $actionRow['action_date'] : '') . ' ' . (isset($actionRow['action_time']) ? $actionRow['action_time'] : ''));
                    if ($actionAt > $groups[$key]['timestamp']) {
                        $groups[$key]['timestamp'] = $actionAt;
                    }
                    if ($roundId > 0 && isset($roundMap[$roundId])) {
                        $groups[$key]['round_no'] = (int) (isset($roundMap[$roundId]['round_no']) ? $roundMap[$roundId]['round_no'] : 0);
                    }
                }
            }
        }

        foreach ($groups as $group) {
            $events[] = $group;
        }
        usort($events, function ($left, $right) {
            return strcmp((string) $right['timestamp'], (string) $left['timestamp']);
        });
        $events = array_slice($events, 0, 100);
        $actionCount = 0;
        foreach ($events as $event) {
            $actionCount += count($event['actions']);
        }

        $eventCount = count($events);
        ?>
        <section class="customer-timeline-preview mt-4" aria-labelledby="customer-timeline-title">
            <style>
                .customer-timeline-preview { --ct-line: #d7e0e8; --ct-accent: #2878a8; color: #263746; }
                .customer-timeline-preview .ct-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
                .customer-timeline-preview .ct-head h4 { margin:0; font-size:1.15rem; font-weight:600; }
                .customer-timeline-preview .ct-intro { color:#667887; font-size:.9rem; margin:.35rem 0 0; }
                .customer-timeline-preview .ct-counts { display:flex; gap:.5rem; flex-wrap:wrap; }
                .customer-timeline-preview .ct-count { border:1px solid #d7e0e8; border-radius:999px; padding:.25rem .65rem; background:#fff; color:#536675; font-size:.8rem; }
                .customer-timeline-preview .ct-list { position:relative; padding-left:1.5rem; }
                .customer-timeline-preview .ct-list:before { content:""; position:absolute; left:.46rem; top:.7rem; bottom:.7rem; width:2px; background:var(--ct-line); }
                .customer-timeline-preview .ct-event { position:relative; border:1px solid #dce5eb; border-radius:.65rem; background:#fff; padding:1rem 1.1rem; margin:0 0 .9rem; }
                .customer-timeline-preview .ct-event:before { content:""; position:absolute; left:-1.48rem; top:1.15rem; width:.7rem; height:.7rem; border:2px solid var(--ct-accent); border-radius:50%; background:#fff; }
                .customer-timeline-preview .ct-event-header { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; flex-wrap:wrap; border-bottom:1px solid #edf1f4; padding-bottom:.6rem; margin-bottom:.75rem; }
                .customer-timeline-preview .ct-event-title { font-weight:600; color:#1e3d53; }
                .customer-timeline-preview .ct-event-time { color:#647887; font-size:.82rem; }
                .customer-timeline-preview .ct-case-status { color:#647887; font-size:.84rem; margin-top:.2rem; }
                .customer-timeline-preview .ct-action { border-left:2px solid #b7d9eb; padding:.1rem 0 .55rem .8rem; margin:.5rem 0 .65rem .15rem; }
                .customer-timeline-preview .ct-action-heading { display:flex; justify-content:space-between; gap:.5rem; flex-wrap:wrap; }
                .customer-timeline-preview .ct-action-heading span,.customer-timeline-preview .ct-action-meta,.customer-timeline-preview .ct-note-meta { color:#687b89; font-size:.82rem; }
                .customer-timeline-preview .ct-action-change { font-size:.88rem; margin-top:.25rem; }
                .customer-timeline-preview .ct-action-remark { white-space:normal; margin-top:.35rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-note { background:#f6f9fb; border-radius:.45rem; padding:.75rem; margin-top:.6rem; }
                .customer-timeline-preview .ct-note-heading { font-weight:600; font-size:.88rem; margin-bottom:.35rem; }
                .customer-timeline-preview .ct-note-summary,.customer-timeline-preview .ct-note-content { overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-note-details { margin-top:.35rem; }
                .customer-timeline-preview .ct-note-details summary { cursor:pointer; color:#365c76; }
                .customer-timeline-preview .ct-note-content { border-top:1px solid #e1e9ee; margin-top:.5rem; padding-top:.5rem; }
                .customer-timeline-preview .ct-note-meta { margin-top:.35rem; }
                .customer-timeline-preview .ct-attachments { margin-top:.55rem; font-size:.85rem; }
                .customer-timeline-preview .ct-attachment-list { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.25rem; }
                .customer-timeline-preview .ct-attachment-link { border:1px solid #cbdce6; border-radius:.35rem; padding:.2rem .5rem; background:#fff; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-empty { border:1px dashed #cbd7df; border-radius:.6rem; padding:1rem; color:#657987; background:#fbfcfd; }
                @media (max-width:575px) {
                    .customer-timeline-preview .ct-event { padding:.8rem; }
                    .customer-timeline-preview .ct-list { padding-left:1.25rem; }
                    .customer-timeline-preview .ct-event:before { left:-1.23rem; }
                    .customer-timeline-preview .ct-event-header { display:block; }
                    .customer-timeline-preview .ct-event-header > div { min-width:0; }
                    .customer-timeline-preview .ct-event-time { display:block; margin-top:.3rem; white-space:normal; overflow-wrap:anywhere; }
                    .customer-timeline-preview .ct-action-heading { display:block; }
                    .customer-timeline-preview .ct-action-heading span { display:block; margin-top:.15rem; }
                }
            </style>
            <div class="ct-head">
                <div>
                    <h4 id="customer-timeline-title">Customer Timeline</h4>
                    <p class="ct-intro">Read-only view of existing customer notes and linked Follow-Up actions. The User Record Log above is unchanged; Follow-Up visibility rules are respected. Campaign records without a stable Customer Info link are not included.</p>
                </div>
                <div class="ct-counts"><span class="ct-count"><?php echo (int) $eventCount; ?> grouped events</span><span class="ct-count"><?php echo (int) $actionCount; ?> Follow-Up actions</span></div>
            </div>
            <?php if (empty($events)) { ?>
                <div class="ct-empty">No linked customer notes or Follow-Up actions were found for this record.</div>
            <?php } else { ?>
                <div class="ct-list">
                    <?php foreach ($events as $event) {
                        $followUpId = (int) $event['follow_up_id'];
                        $roundId = (int) $event['round_id'];
                        $caseRow = isset($caseMap[$followUpId]) ? $caseMap[$followUpId] : array();
                        $title = $followUpId > 0
                            ? ('Follow-Up Case #' . $followUpId . ($event['round_no'] > 0 ? (' - Round ' . (int) $event['round_no']) : ($roundId > 0 ? (' - Round record #' . $roundId) : '')))
                            : 'Customer note';
                        $caseStatus = trim((string) (isset($caseRow['current_status']) ? $caseRow['current_status'] : ''));
                        $eventTime = trim((string) $event['timestamp']);
                        if ($eventTime === '') {
                            foreach ($event['notes'] as $noteRow) {
                                $eventTime = trim((string) (isset($noteRow['created_at']) ? $noteRow['created_at'] : ''));
                                if ($eventTime !== '') { break; }
                            }
                        }
                    ?>
                        <article class="ct-event">
                            <div class="ct-event-header">
                                <div><div class="ct-event-title"><?php echo customerTimelinePreviewEscape($title); ?></div><?php if ($caseStatus !== '') { ?><div class="ct-case-status">Current case status: <?php echo customerTimelinePreviewEscape($caseStatus); ?></div><?php } ?></div>
                                <time class="ct-event-time"><?php echo customerTimelinePreviewEscape($eventTime !== '' ? $eventTime : 'Time unavailable'); ?></time>
                            </div>
                            <?php if (!empty($event['actions'])) {
                                $actions = $event['actions'];
                                usort($actions, function ($left, $right) {
                                    $leftAt = trim((string) (isset($left['action_date']) ? $left['action_date'] : '') . ' ' . (isset($left['action_time']) ? $left['action_time'] : ''));
                                    $rightAt = trim((string) (isset($right['action_date']) ? $right['action_date'] : '') . ' ' . (isset($right['action_time']) ? $right['action_time'] : ''));
                                    return strcmp($leftAt, $rightAt);
                                });
                                foreach ($actions as $actionRow) {
                                    echo customerTimelinePreviewRenderAction($connect, $actionRow);
                                }
                            } ?>
                            <?php
                            $groupAttachments = array();
                            foreach ($event['notes'] as $noteRow) {
                                $noteAttachments = function_exists('urlDecodeUserRecordLogAttachmentSequence')
                                    ? urlDecodeUserRecordLogAttachmentSequence(
                                        isset($noteRow['attachment']) ? $noteRow['attachment'] : '',
                                        isset($noteRow['attachment_sequence']) ? $noteRow['attachment_sequence'] : ''
                                    )
                                    : array();
                                foreach ($noteAttachments as $noteAttachment) {
                                    $attachmentKey = trim(str_replace('\\', '/', (string) $noteAttachment));
                                    if ($attachmentKey !== '') {
                                        $groupAttachments[$attachmentKey] = $attachmentKey;
                                    }
                                }
                                echo customerTimelinePreviewRenderNote($connect, $noteRow, $followUpId > 0, $followUpId <= 0);
                            }
                            if ($followUpId > 0 && $roundId > 0 && isset($roundMap[$roundId])
                                && function_exists('urlDecodeUserRecordLogAttachmentList')
                            ) {
                                $roundAttachments = urlDecodeUserRecordLogAttachmentList(isset($roundMap[$roundId]['attachment']) ? $roundMap[$roundId]['attachment'] : '');
                                foreach ($roundAttachments as $roundAttachment) {
                                    $attachmentKey = trim(str_replace('\\', '/', (string) $roundAttachment));
                                    if ($attachmentKey !== '') {
                                        $groupAttachments[$attachmentKey] = $attachmentKey;
                                    }
                                }
                            }
                            if ($followUpId > 0) {
                                echo customerTimelinePreviewRenderAttachmentLinks(array_values($groupAttachments));
                            }
                            ?>
                        </article>
                    <?php } ?>
                </div>
                <?php if (count($events) >= 100) { ?><div class="ct-intro">Showing the latest 100 grouped events.</div><?php } ?>
            <?php } ?>
        </section>
        <?php
    }
}
