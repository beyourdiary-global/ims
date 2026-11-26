<?php

if (!function_exists('customerTimelinePreviewEscape')) {
    function customerTimelinePreviewEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('customerTimelinePreviewGetCustomerColumn')) {
    function customerTimelinePreviewGetCustomerColumn($platform)
    {
        $platform = strtolower(trim((string) $platform));
        $customerColumns = array(
            'customer_info' => 'cust_id',
            'shopee' => 'shopee_cust_id',
            'lazada' => 'lazada_cust_id',
            'facebook' => 'facebook_cust_id',
            'website' => 'website_cust_id',
        );
        return isset($customerColumns[$platform]) ? $customerColumns[$platform] : '';
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
            'resubmit_rejected_follow_up' => 'Follow-up resubmitted',
            'approve_follow_up' => 'Follow-up approved',
            'reject_follow_up' => 'Follow-up rejected',
            'complete_follow_up' => 'Follow-up completed',
            'request_postponement' => 'Postponement requested',
            'approve_postponement' => 'Postponement approved',
            'reject_postponement' => 'Postponement rejected',
            'schedule_next_follow_up_from_customer_page' => 'Next follow-up scheduled',
            'overwrite_follow_up_date' => 'Follow-up date changed',
            'adjust_follow_up_round' => 'Follow-up round changed',
            'contact_number_edited' => 'Contact number edited',
            'merged_duplicate_case' => 'Duplicate case merged',
        );
        $actionType = trim((string) $actionType);
        if (isset($labels[$actionType])) {
            return $labels[$actionType];
        }

        return $actionType !== '' ? ucwords(str_replace('_', ' ', $actionType)) : 'Follow-up action';
    }
}

if (!function_exists('customerTimelinePreviewGetAttachments')) {
    function customerTimelinePreviewGetAttachments($row)
    {
        if (!is_array($row)) {
            return array();
        }
        if (function_exists('urlDecodeUserRecordLogAttachmentSequence')) {
            return urlDecodeUserRecordLogAttachmentSequence(
                isset($row['attachment']) ? $row['attachment'] : '',
                isset($row['attachment_sequence']) ? $row['attachment_sequence'] : ''
            );
        }
        return array();
    }
}

if (!function_exists('customerTimelinePreviewRenderAttachmentLinks')) {
    function customerTimelinePreviewRenderAttachmentLinks($attachments)
    {
        if (!is_array($attachments) || empty($attachments) || !function_exists('urlBuildUserRecordLogAttachmentUrl')) {
            return '';
        }

        $items = array();
        foreach ($attachments as $attachment) {
            $attachment = trim(str_replace('\\', '/', (string) $attachment));
            if ($attachment === '') {
                continue;
            }
            $url = urlBuildUserRecordLogAttachmentUrl(
                $attachment,
                function_exists('urlGetUserRecordLogUploadWebDir') ? urlGetUserRecordLogUploadWebDir() : ''
            );
            if ($url === '') {
                continue;
            }
            $fileName = basename($attachment);
            $items[$attachment] = '<a class="ct-proof-link" href="' . customerTimelinePreviewEscape($url) . '" target="_blank" rel="noopener noreferrer">'
                . customerTimelinePreviewEscape($fileName !== '' ? $fileName : 'Open file') . '</a>';
        }

        if (empty($items)) {
            return '';
        }
        return '<div class="ct-proof"><span class="ct-proof-label">Proof</span><div class="ct-proof-links">' . implode('', $items) . '</div></div>';
    }
}

if (!function_exists('customerTimelinePreviewIsRoundMirror')) {
    function customerTimelinePreviewIsRoundMirror($row)
    {
        if (!is_array($row)) {
            return false;
        }
        $sourceType = strtolower(trim((string) (isset($row['source_type']) ? $row['source_type'] : '')));
        if ($sourceType === 'customer_follow_up_round') {
            return true;
        }
        if ($sourceType !== '') {
            return false;
        }
        $history = json_decode((string) (isset($row['follow_up_history']) ? $row['follow_up_history'] : ''), true);
        if (!is_array($history)) {
            return false;
        }
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $action = strtolower(trim((string) (isset($entry['action']) ? $entry['action'] : '')));
            if ($action !== '' && $action !== 'updated from customer page' && array_key_exists('detail', $entry)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('customerTimelinePreviewCanIncludeFollowUpRecord')) {
    function customerTimelinePreviewCanIncludeFollowUpRecord($followUpId, $canViewAllFollowUpRecords, $visibleCaseMap)
    {
        $followUpId = (int) $followUpId;
        if ($followUpId <= 0 || $canViewAllFollowUpRecords) {
            return true;
        }
        return is_array($visibleCaseMap) && isset($visibleCaseMap[$followUpId]);
    }
}

if (!function_exists('customerTimelinePreviewDecodeHistory')) {
    function customerTimelinePreviewDecodeHistory($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : array();
    }
}

if (!function_exists('customerTimelinePreviewRenderRevisionHistory')) {
    function customerTimelinePreviewRenderRevisionHistory($row, $connect)
    {
        $history = customerTimelinePreviewDecodeHistory(isset($row['source_history']) ? $row['source_history'] : '');
        if (empty($history)) {
            return '';
        }

        $html = '<details class="ct-revisions"><summary>Record updates (' . count($history) . ')</summary><ol class="ct-revision-list">';
        foreach ($history as $revision) {
            if (!is_array($revision)) {
                continue;
            }
            $actorId = trim((string) (isset($revision['by']) ? $revision['by'] : ''));
            $actor = function_exists('urlGetUserName') ? urlGetUserName($connect, $actorId) : $actorId;
            $html .= '<li><div class="ct-revision-meta">' . customerTimelinePreviewEscape(isset($revision['time']) ? $revision['time'] : 'Time unavailable')
                . ' · ' . customerTimelinePreviewEscape($actor !== '' ? $actor : 'unknown') . '</div>';
            foreach ((isset($revision['changes']) && is_array($revision['changes'])) ? $revision['changes'] : array() as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $field = trim((string) (isset($change['field']) ? $change['field'] : 'Update'));
                $from = trim((string) (isset($change['from']) ? $change['from'] : ''));
                $to = trim((string) (isset($change['to']) ? $change['to'] : ''));
                if ($field === 'Proof') {
                    $from = $from !== '' && $from !== 'No proof' ? basename(str_replace('\\', '/', $from)) : $from;
                    $to = $to !== '' ? basename(str_replace('\\', '/', $to)) : $to;
                }
                $html .= '<div class="ct-revision-change"><strong>' . customerTimelinePreviewEscape($field) . ':</strong> '
                    . customerTimelinePreviewEscape($from !== '' ? $from : 'Not set') . ' <span aria-hidden="true">→</span> '
                    . customerTimelinePreviewEscape($to !== '' ? $to : 'Not set') . '</div>';
            }
            $html .= '</li>';
        }
        $html .= '</ol></details>';
        return $html;
    }
}

if (!function_exists('customerTimelinePreviewRenderNoteEvent')) {
    function customerTimelinePreviewRenderNoteEvent($connect, $row, $title, $timestamp, $roundLabel = '')
    {
        $content = trim((string) (isset($row['content']) ? $row['content'] : ''));
        $plainContent = function_exists('urlGetUserRecordLogContentPlainText')
            ? trim((string) urlGetUserRecordLogContentPlainText($content))
            : trim(strip_tags($content));
        $createdAt = trim((string) (isset($row['created_at']) ? $row['created_at'] : ''));
        $updatedAt = trim((string) (isset($row['updated_at']) ? $row['updated_at'] : ''));
        $createdById = trim((string) (isset($row['created_by']) ? $row['created_by'] : ''));
        $updatedById = trim((string) (isset($row['updated_by']) ? $row['updated_by'] : ''));
        $createdBy = function_exists('urlGetUserName') ? urlGetUserName($connect, $createdById) : $createdById;
        $updatedBy = function_exists('urlGetUserName') ? urlGetUserName($connect, $updatedById) : $updatedById;
        $attachments = customerTimelinePreviewGetAttachments($row);

        $html = '<article class="ct-event ct-event-note"><div class="ct-event-marker" aria-hidden="true"></div>';
        $html .= '<header class="ct-event-header"><div class="ct-event-heading"><span class="ct-kind">Record</span><h5>' . customerTimelinePreviewEscape($title) . '</h5>';
        if ($roundLabel !== '') {
            $html .= '<span class="ct-context">' . customerTimelinePreviewEscape($roundLabel) . '</span>';
        }
        $html .= '</div><time class="ct-event-time">' . customerTimelinePreviewEscape($timestamp !== '' ? $timestamp : 'Time unavailable') . '</time></header>';

        if ($plainContent !== '') {
            $contentHtml = function_exists('urlRenderUserRecordLogContentHtml')
                ? urlRenderUserRecordLogContentHtml($content)
                : nl2br(customerTimelinePreviewEscape(strip_tags($content)));
            if (function_exists('mb_strlen') ? mb_strlen($plainContent, 'UTF-8') > 260 : strlen($plainContent) > 260) {
                $excerpt = function_exists('mb_substr') ? mb_substr($plainContent, 0, 260, 'UTF-8') : substr($plainContent, 0, 260);
                $html .= '<details class="ct-content-details"><summary>' . customerTimelinePreviewEscape($excerpt) . '…</summary><div class="ct-event-content">' . $contentHtml . '</div></details>';
            } else {
                $html .= '<div class="ct-event-content">' . $contentHtml . '</div>';
            }
        }
        $html .= customerTimelinePreviewRenderAttachmentLinks($attachments);
        $html .= customerTimelinePreviewRenderRevisionHistory($row, $connect);

        if ($updatedAt !== '' && $updatedAt !== $createdAt) {
            $html .= '<div class="ct-event-meta">Created ' . customerTimelinePreviewEscape($createdAt !== '' ? $createdAt : 'time unavailable')
                . ' by ' . customerTimelinePreviewEscape($createdBy !== '' ? $createdBy : 'unknown') . '</div>';
            $html .= '<div class="ct-event-meta">Last updated ' . customerTimelinePreviewEscape($updatedAt)
                . ' by ' . customerTimelinePreviewEscape($updatedBy !== '' ? $updatedBy : 'unknown') . '</div>';
        } else {
            $html .= '<div class="ct-event-meta">Recorded ' . customerTimelinePreviewEscape($createdAt !== '' ? $createdAt : ($timestamp !== '' ? $timestamp : 'time unavailable'))
                . ' by ' . customerTimelinePreviewEscape($createdBy !== '' ? $createdBy : 'unknown') . '</div>';
        }
        return $html . '</article>';
    }
}

if (!function_exists('customerTimelinePreviewRenderActionEvent')) {
    function customerTimelinePreviewRenderActionEvent($connect, $actionRow, $roundNo)
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
        $fieldLabels = array(
            'approval_status' => 'Approval',
            'round_status' => 'Follow-up status',
            'postpone_status' => 'Postponement',
            'cancel_status' => 'Cancellation',
            'next_follow_up_date' => 'Follow-up date',
            'requested_next_follow_up_date' => 'Requested date',
            'approved_next_follow_up_date' => 'Approved date',
            'round_no' => 'Round',
            'contact_no' => 'Contact number',
            'message_shortcut_label' => 'Message shortcut',
            'reject_reason' => 'Rejection reason',
            'postpone_reason' => 'Postponement reason',
            'postpone_reject_reason' => 'Postponement rejection reason',
            'cancel_reason' => 'Cancellation reason',
            'cancel_reject_reason' => 'Cancellation rejection reason',
            'delay_reason' => 'Delay reason',
            'tag_name' => 'Customer tag',
            'tag_action' => 'Tag action',
        );

        $html = '<article class="ct-event ct-event-action"><div class="ct-event-marker" aria-hidden="true"></div>';
        $html .= '<header class="ct-event-header"><div class="ct-event-heading"><span class="ct-kind ct-kind-action">Follow-Up action</span><h5>'
            . customerTimelinePreviewEscape(customerTimelinePreviewActionTitle($actionType)) . '</h5><span class="ct-context">'
            . customerTimelinePreviewEscape('Case #' . (int) (isset($actionRow['follow_up_id']) ? $actionRow['follow_up_id'] : 0) . ($roundNo > 0 ? ' · Round ' . (int) $roundNo : ''))
            . '</span></div><time class="ct-event-time">' . customerTimelinePreviewEscape($actionAt !== '' ? $actionAt : 'Time unavailable') . '</time></header>';
        $html .= '<div class="ct-event-meta">By ' . customerTimelinePreviewEscape($actor !== '' ? $actor : 'unknown') . '</div>';

        $shownFields = array();
        $historyChanges = isset($newValue['history_changes']) && is_array($newValue['history_changes']) ? $newValue['history_changes'] : array();
        if (!empty($historyChanges)) {
            $html .= '<div class="ct-change-list">';
            foreach ($historyChanges as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $field = trim((string) (isset($change['field']) ? $change['field'] : 'Change'));
                $from = trim((string) (isset($change['from']) ? $change['from'] : ''));
                $to = trim((string) (isset($change['to']) ? $change['to'] : ''));
                $shownFields[$field] = true;
                $html .= '<div class="ct-change"><strong>' . customerTimelinePreviewEscape($field) . ':</strong> '
                    . customerTimelinePreviewEscape($from !== '' ? $from : 'Not set') . ' <span aria-hidden="true">→</span> '
                    . customerTimelinePreviewEscape($to !== '' ? $to : 'Not set') . '</div>';
            }
            $html .= '</div>';
        }

        $genericChanges = array();
        foreach ($fieldLabels as $field => $label) {
            if (!array_key_exists($field, $newValue)) {
                continue;
            }
            if (!is_scalar($newValue[$field])) {
                continue;
            }
            $from = trim((string) (isset($oldValue[$field]) && is_scalar($oldValue[$field]) ? $oldValue[$field] : ''));
            $to = trim((string) $newValue[$field]);
            if (strcasecmp($from, $to) === 0 || isset($shownFields[$label])) {
                continue;
            }
            if (in_array($field, array('approval_status', 'round_status', 'postpone_status', 'cancel_status'), true)
                && function_exists('customerFollowUpFormatStateValueLabel')
            ) {
                $from = $from !== '' ? customerFollowUpFormatStateValueLabel($from) : 'Not set';
                $to = $to !== '' ? customerFollowUpFormatStateValueLabel($to) : 'Cleared';
            }
            $genericChanges[] = array('label' => $label, 'from' => $from, 'to' => $to);
        }
        if (!empty($genericChanges)) {
            $html .= '<div class="ct-change-list">';
            foreach ($genericChanges as $change) {
                $html .= '<div class="ct-change"><strong>' . customerTimelinePreviewEscape($change['label']) . ':</strong> '
                    . customerTimelinePreviewEscape($change['from'] !== '' ? $change['from'] : 'Not set') . ' <span aria-hidden="true">→</span> '
                    . customerTimelinePreviewEscape($change['to'] !== '' ? $change['to'] : 'Cleared') . '</div>';
            }
            $html .= '</div>';
        }
        $oldMessage = trim((string) (isset($oldValue['message_shortcut_text']) && is_scalar($oldValue['message_shortcut_text']) ? $oldValue['message_shortcut_text'] : ''));
        $newMessage = trim((string) (isset($newValue['message_shortcut_text']) && is_scalar($newValue['message_shortcut_text']) ? $newValue['message_shortcut_text'] : ''));
        if ($newMessage !== '' && $oldMessage !== $newMessage) {
            $html .= '<details class="ct-message-change"><summary>View message content change</summary>';
            $html .= '<div class="ct-event-content"><strong>Previous:</strong><br>' . nl2br(customerTimelinePreviewEscape($oldMessage !== '' ? $oldMessage : 'Not set')) . '</div>';
            $html .= '<div class="ct-event-content"><strong>Current:</strong><br>' . nl2br(customerTimelinePreviewEscape($newMessage)) . '</div></details>';
        }
        if ($remark !== '') {
            $html .= '<div class="ct-event-content">' . nl2br(customerTimelinePreviewEscape($remark)) . '</div>';
        }
        $attachmentPath = trim((string) (isset($newValue['attachment_path']) ? $newValue['attachment_path'] : ''));
        if ($attachmentPath !== '') {
            $html .= customerTimelinePreviewRenderAttachmentLinks(array($attachmentPath));
        }
        return $html . '</article>';
    }
}

if (!function_exists('customerTimelinePreviewBuildHistoryEvents')) {
    function customerTimelinePreviewBuildHistoryEvents($row, $caseRow, $roundNo, $actionEventsExist)
    {
        if (!customerTimelinePreviewIsRoundMirror($row) || $actionEventsExist) {
            return array();
        }
        $history = customerTimelinePreviewDecodeHistory(isset($row['follow_up_history']) ? $row['follow_up_history'] : '');
        if (empty($history)) {
            return array();
        }

        $events = array();
        foreach ($history as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $time = trim((string) (isset($entry['time']) ? $entry['time'] : ''));
            $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : array();
            $proofs = array();
            foreach ($changes as $change) {
                if (is_array($change)
                    && stripos((string) (isset($change['field']) ? $change['field'] : ''), 'screenshot') !== false
                    && !empty($row['attachment'])
                ) {
                    $proofs[] = $row['attachment'];
                    break;
                }
            }
            $events[] = array(
                'kind' => 'history',
                'row' => $row,
                'history_entry' => $entry,
                'time' => $time !== '' ? $time : (string) (isset($row['created_at']) ? $row['created_at'] : ''),
                'round_no' => (int) $roundNo,
                'follow_up_id' => (int) (isset($row['follow_up_id']) ? $row['follow_up_id'] : 0),
                'proofs' => $proofs,
                'sort_id' => (int) (isset($row['id']) ? $row['id'] : 0) * 1000 + (int) $index,
            );
        }
        return $events;
    }
}

if (!function_exists('customerTimelinePreviewRenderHistoryEvent')) {
    function customerTimelinePreviewRenderHistoryEvent($connect, $event)
    {
        $entry = isset($event['history_entry']) && is_array($event['history_entry']) ? $event['history_entry'] : array();
        $actorId = trim((string) (isset($entry['by']) ? $entry['by'] : ''));
        $actor = function_exists('urlGetUserName') ? urlGetUserName($connect, $actorId) : $actorId;
        $actionLabel = trim((string) (isset($entry['action']) ? $entry['action'] : 'Follow-up updated'));
        $caseId = (int) (isset($event['follow_up_id']) ? $event['follow_up_id'] : 0);
        $roundNo = (int) (isset($event['round_no']) ? $event['round_no'] : 0);

        $html = '<article class="ct-event ct-event-action"><div class="ct-event-marker" aria-hidden="true"></div>';
        $html .= '<header class="ct-event-header"><div class="ct-event-heading"><span class="ct-kind ct-kind-action">Follow-Up action</span><h5>'
            . customerTimelinePreviewEscape($actionLabel) . '</h5><span class="ct-context">'
            . customerTimelinePreviewEscape('Case #' . $caseId . ($roundNo > 0 ? ' · Round ' . $roundNo : ''))
            . '</span></div><time class="ct-event-time">' . customerTimelinePreviewEscape(isset($event['time']) ? $event['time'] : 'Time unavailable') . '</time></header>';
        $html .= '<div class="ct-event-meta">By ' . customerTimelinePreviewEscape($actor !== '' ? $actor : 'unknown') . '</div>';

        $changes = isset($entry['changes']) && is_array($entry['changes']) ? $entry['changes'] : array();
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $field = trim((string) (isset($change['field']) ? $change['field'] : 'Change'));
            $from = trim((string) (isset($change['from']) ? $change['from'] : ''));
            $to = trim((string) (isset($change['to']) ? $change['to'] : ''));
            $html .= '<div class="ct-change"><strong>' . customerTimelinePreviewEscape($field) . ':</strong> '
                . customerTimelinePreviewEscape($from !== '' ? $from : 'Not set') . ' <span aria-hidden="true">→</span> '
                . customerTimelinePreviewEscape($to !== '' ? $to : 'Not set') . '</div>';
        }
        $detail = trim((string) (isset($entry['detail']) ? $entry['detail'] : ''));
        if ($detail !== '') {
            $html .= '<div class="ct-event-content">' . customerTimelinePreviewEscape($detail) . '</div>';
        }
        $html .= customerTimelinePreviewRenderAttachmentLinks(isset($event['proofs']) ? $event['proofs'] : array());
        return $html . '</article>';
    }
}

if (!function_exists('customerTimelineRenderCustomerPreview')) {
    function customerTimelineRenderCustomerPreview($connect, $platform, $customerId)
    {
        $platform = strtolower(trim((string) $platform));
        $customerId = (int) $customerId;
        $customerColumn = customerTimelinePreviewGetCustomerColumn($platform);
        if (!($connect instanceof mysqli)
            || $customerId <= 0
            || !defined('USER_RECORD_LOG')
            || $customerColumn === ''
        ) {
            return;
        }

        $noteRows = array();
        $events = array();
        $followUpRows = array();
        $caseIds = array();
        $caseMap = array();
        $canViewAllFollowUpRecords = function_exists('customerFollowUpIsAdminUser')
            && customerFollowUpIsAdminUser(defined('USER_GROUP') ? USER_GROUP : null);
        if (function_exists('customerFollowUpFetchActiveCasesByCustomer')) {
            $followUpRows = customerFollowUpFetchActiveCasesByCustomer($connect, $platform, $customerId);
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
            && urlUserRecordLogColumnExists($connect, $logTable, $customerColumn)
        ) {
            $where = "`status`='A' AND `" . $customerColumn . "`=" . $customerId . " AND (IFNULL(`content`,'')<>'' OR IFNULL(`attachment`,'')<>'')";
            if (urlUserRecordLogColumnExists($connect, $logTable, 'log_type')) {
                $where .= " AND (IFNULL(`log_type`,'')='' OR `log_type`<>'tag')";
            }
            $result = mysqli_query($connect, "SELECT * FROM `" . $logTable . "` WHERE " . $where . " ORDER BY GREATEST(IFNULL(`updated_at`,`created_at`),`created_at`) DESC, `id` DESC LIMIT 500");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $originalFollowUpId = (int) (isset($row['follow_up_id']) ? $row['follow_up_id'] : 0);
                    if (!customerTimelinePreviewCanIncludeFollowUpRecord($originalFollowUpId, $canViewAllFollowUpRecords, $caseMap)) {
                        // Do not downgrade a hidden Follow-Up mirror to a normal customer
                        // note; that would still expose its content and proof to unassigned users.
                        continue;
                    }
                    $followUpId = $originalFollowUpId;
                    $roundId = $followUpId > 0 ? (int) (isset($row['follow_up_round_id']) ? $row['follow_up_round_id'] : 0) : 0;
                    $row['_timeline_follow_up_id'] = $followUpId;
                    $row['_timeline_round_id'] = $roundId;
                    $row['_timeline_original_follow_up_id'] = $originalFollowUpId;
                    $noteRows[] = $row;
                }
            }
        }

        $roundMap = array();
        if (!empty($caseIds) && defined('CUSTOMER_FOLLOW_UP_ROUND')
            && customerTimelinePreviewTableExists($connect, CUSTOMER_FOLLOW_UP_ROUND)
            && function_exists('urlUserRecordLogColumnExists')
        ) {
            $safeRoundTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) CUSTOMER_FOLLOW_UP_ROUND);
            if (urlUserRecordLogColumnExists($connect, $safeRoundTable, 'follow_up_id')) {
                $roundFields = array('`id`', '`follow_up_id`');
                foreach (array('round_no', 'attachment', 'status') as $optionalRoundField) {
                    if (urlUserRecordLogColumnExists($connect, $safeRoundTable, $optionalRoundField)) {
                        $roundFields[] = '`' . $optionalRoundField . '`';
                    }
                }
                $roundWhere = '`follow_up_id` IN (' . implode(',', array_map('intval', $caseIds)) . ')';
                if (urlUserRecordLogColumnExists($connect, $safeRoundTable, 'status')) {
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

        $actionRowsByRound = array();
        if (defined('USER_GROUP') && (int) USER_GROUP === 1
            && defined('CUSTOMER_FOLLOW_UP_ACTION_LOG')
            && customerTimelinePreviewTableExists($connect, CUSTOMER_FOLLOW_UP_ACTION_LOG)
        ) {
            foreach ($followUpRows as $followUpRow) {
                $followUpId = (int) (isset($followUpRow['id']) ? $followUpRow['id'] : 0);
                if ($followUpId <= 0 || !function_exists('customerFollowUpFetchActionLogRows')) {
                    continue;
                }
                foreach (customerFollowUpFetchActionLogRows($connect, $followUpId, 100) as $actionRow) {
                    $roundId = (int) (isset($actionRow['round_id']) ? $actionRow['round_id'] : 0);
                    $key = $followUpId . ':' . $roundId;
                    $roundNo = isset($roundMap[$roundId]['round_no']) ? (int) $roundMap[$roundId]['round_no'] : 0;
                    $actionAt = trim((string) (isset($actionRow['action_date']) ? $actionRow['action_date'] : '') . ' ' . (isset($actionRow['action_time']) ? $actionRow['action_time'] : ''));
                    $events[] = array(
                        'kind' => 'action',
                        'row' => $actionRow,
                        'time' => $actionAt,
                        'round_no' => $roundNo,
                        'follow_up_id' => $followUpId,
                        'sort_id' => (int) (isset($actionRow['id']) ? $actionRow['id'] : 0),
                    );
                    $actionRowsByRound[$key] = true;
                }
            }
        }

        foreach ($noteRows as $row) {
            $followUpId = (int) (isset($row['_timeline_follow_up_id']) ? $row['_timeline_follow_up_id'] : 0);
            $roundId = (int) (isset($row['_timeline_round_id']) ? $row['_timeline_round_id'] : 0);
            $roundNo = isset($roundMap[$roundId]['round_no']) ? (int) $roundMap[$roundId]['round_no'] : 0;
            $hasActionEvents = $followUpId > 0 && isset($actionRowsByRound[$followUpId . ':' . $roundId]);
            $isRoundMirror = customerTimelinePreviewIsRoundMirror($row);

            if ($isRoundMirror && $hasActionEvents) {
                // Action rows are the authoritative event stream; this linked row is only
                // their readable-log mirror and must not render as a second copy.
                continue;
            }
            if ($isRoundMirror && $followUpId > 0) {
                $historyEvents = customerTimelinePreviewBuildHistoryEvents($row, isset($caseMap[$followUpId]) ? $caseMap[$followUpId] : array(), $roundNo, false);
                if (!empty($historyEvents)) {
                    foreach ($historyEvents as $historyEvent) {
                        $events[] = $historyEvent;
                    }
                    continue;
                }
            }

            $createdAt = trim((string) (isset($row['created_at']) ? $row['created_at'] : ''));
            $updatedAt = trim((string) (isset($row['updated_at']) ? $row['updated_at'] : ''));
            $timestamp = $updatedAt !== '' && $updatedAt > $createdAt ? $updatedAt : $createdAt;
            $sourceType = strtolower(trim((string) (isset($row['source_type']) ? $row['source_type'] : '')));
            if ($sourceType === 'campaign_follow_up_task') {
                $title = 'Campaign follow-up completed';
            } elseif ($followUpId > 0) {
                $title = 'Follow-Up note';
            } else {
                $title = 'Customer note';
            }
            $roundLabel = $followUpId > 0
                ? ('Case #' . $followUpId . ($roundNo > 0 ? ' · Round ' . $roundNo : ($roundId > 0 ? ' · Round record #' . $roundId : '')))
                : '';
            $events[] = array(
                'kind' => 'note',
                'row' => $row,
                'title' => $title,
                'round_label' => $roundLabel,
                'time' => $timestamp,
                'sort_id' => (int) (isset($row['id']) ? $row['id'] : 0),
            );
        }

        usort($events, function ($left, $right) {
            $timeOrder = strcmp((string) (isset($right['time']) ? $right['time'] : ''), (string) (isset($left['time']) ? $left['time'] : ''));
            if ($timeOrder !== 0) {
                return $timeOrder;
            }
            return (int) (isset($right['sort_id']) ? $right['sort_id'] : 0) - (int) (isset($left['sort_id']) ? $left['sort_id'] : 0);
        });
        $events = array_slice($events, 0, 100);
        $eventCount = count($events);
        ?>
        <section class="customer-timeline-preview mt-4" aria-labelledby="customer-timeline-title">
            <style>
                .customer-timeline-preview { --ct-line:#d8e2e9; --ct-accent:#26749c; color:#273b49; }
                .customer-timeline-preview .ct-head { display:flex; justify-content:space-between; align-items:flex-end; gap:1rem; flex-wrap:wrap; padding:0 0 .8rem; margin-bottom:1rem; border-bottom:2px solid #294e63; }
                .customer-timeline-preview .ct-head h4 { margin:0; font-size:1.25rem; font-weight:700; color:#173b50; }
                .customer-timeline-preview .ct-intro { color:#607482; font-size:.9rem; margin:.35rem 0 0; max-width:58rem; }
                .customer-timeline-preview .ct-count { border:1px solid #d3e0e8; border-radius:999px; padding:.28rem .7rem; background:#f5f9fb; color:#405d70; font-size:.82rem; white-space:nowrap; }
                .customer-timeline-preview .ct-list { position:relative; padding-left:1.35rem; }
                .customer-timeline-preview .ct-list:before { content:""; position:absolute; left:.32rem; top:.35rem; bottom:.35rem; width:2px; background:var(--ct-line); }
                .customer-timeline-preview .ct-event { position:relative; border:1px solid #dce5eb; border-radius:.45rem; background:#fff; padding:.9rem 1rem; margin:0 0 .7rem; box-shadow:0 1px 2px rgba(28,56,73,.04); }
                .customer-timeline-preview .ct-event-marker { position:absolute; left:-1.34rem; top:1.1rem; width:.62rem; height:.62rem; border:2px solid var(--ct-accent); border-radius:50%; background:#fff; }
                .customer-timeline-preview .ct-event-action .ct-event-marker { border-color:#a86a2c; }
                .customer-timeline-preview .ct-event-header { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; flex-wrap:wrap; margin-bottom:.55rem; }
                .customer-timeline-preview .ct-event-heading { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem .55rem; min-width:0; }
                .customer-timeline-preview .ct-event-heading h5 { margin:0; font-size:1rem; font-weight:700; color:#213f51; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-kind { color:#42697e; background:#eef5f8; border-radius:.2rem; padding:.12rem .38rem; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
                .customer-timeline-preview .ct-kind-action { color:#87521f; background:#fbf2e8; }
                .customer-timeline-preview .ct-context { color:#697e8a; font-size:.8rem; }
                .customer-timeline-preview .ct-event-time { color:#536c7b; font-size:.83rem; font-variant-numeric:tabular-nums; white-space:nowrap; }
                .customer-timeline-preview .ct-event-meta { color:#6a7d89; font-size:.79rem; margin-top:.35rem; }
                .customer-timeline-preview .ct-event-summary,.customer-timeline-preview .ct-event-content { margin-top:.5rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-content-details { margin-top:.5rem; }
                .customer-timeline-preview .ct-content-details summary,.customer-timeline-preview .ct-revisions summary,.customer-timeline-preview .ct-message-change summary { cursor:pointer; color:#365c76; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-message-change { margin-top:.55rem; }
                .customer-timeline-preview .ct-change-list { margin-top:.55rem; padding:.55rem .7rem; border-left:3px solid #d5e5ed; background:#f7fafb; }
                .customer-timeline-preview .ct-change { margin:.16rem 0; font-size:.89rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-proof { display:flex; align-items:flex-start; gap:.55rem; flex-wrap:wrap; margin-top:.65rem; padding-top:.55rem; border-top:1px solid #edf1f3; }
                .customer-timeline-preview .ct-proof-label { color:#536c7b; font-size:.8rem; font-weight:700; }
                .customer-timeline-preview .ct-proof-links { display:flex; gap:.35rem; flex-wrap:wrap; }
                .customer-timeline-preview .ct-proof-link { display:inline-block; border:1px solid #cbdbe4; border-radius:.25rem; padding:.16rem .45rem; background:#fff; color:#285b78; font-size:.82rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-revisions { margin-top:.65rem; font-size:.85rem; }
                .customer-timeline-preview .ct-revision-list { margin:.45rem 0 0; padding-left:1.4rem; }
                .customer-timeline-preview .ct-revision-list li { padding:.3rem 0 .3rem .1rem; border-top:1px solid #edf1f3; }
                .customer-timeline-preview .ct-revision-meta { color:#647986; font-size:.78rem; }
                .customer-timeline-preview .ct-revision-change { margin-top:.18rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-empty { border:1px dashed #cbd7df; border-radius:.45rem; padding:1rem; color:#657987; background:#fbfcfd; }
                @media (max-width:575px) {
                    .customer-timeline-preview .ct-event { padding:.75rem; }
                    .customer-timeline-preview .ct-event-header { display:block; }
                    .customer-timeline-preview .ct-event-time { display:block; margin-top:.35rem; white-space:normal; overflow-wrap:anywhere; }
                    .customer-timeline-preview .ct-list { padding-left:1.15rem; }
                    .customer-timeline-preview .ct-event-marker { left:-1.14rem; }
                    .customer-timeline-preview .ct-head { align-items:flex-start; }
                }
            </style>
            <div class="ct-head">
                <div>
                    <h4 id="customer-timeline-title">Customer Activity Timeline</h4>
                    <p class="ct-intro">One card per recorded action. Follow-Up action entries are shown once; their readable-log mirror is folded into the same event stream. Proof replacements and later edits stay inside the original record.</p>
                </div>
                <span class="ct-count"><?php echo (int) $eventCount; ?> events</span>
            </div>
            <?php if (empty($events)) { ?>
                <div class="ct-empty">No customer activity records were found.</div>
            <?php } else { ?>
                <div class="ct-list">
                    <?php foreach ($events as $event) {
                        if ($event['kind'] === 'action') {
                            echo customerTimelinePreviewRenderActionEvent($connect, $event['row'], (int) $event['round_no']);
                        } elseif ($event['kind'] === 'history') {
                            echo customerTimelinePreviewRenderHistoryEvent($connect, $event);
                        } else {
                            echo customerTimelinePreviewRenderNoteEvent(
                                $connect,
                                $event['row'],
                                $event['title'],
                                $event['time'],
                                $event['round_label']
                            );
                        }
                    } ?>
                </div>
                <?php if (count($events) >= 100) { ?><div class="ct-intro">Showing the latest 100 events.</div><?php } ?>
            <?php } ?>
        </section>
        <?php
    }
}

if (!function_exists('customerTimelineRenderCustomerInfoPreview')) {
    function customerTimelineRenderCustomerInfoPreview($connect, $customerId)
    {
        customerTimelineRenderCustomerPreview($connect, 'customer_info', $customerId);
    }
}
