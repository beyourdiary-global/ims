<?php

if (defined('ROOT') && is_file(ROOT . '/include/customer_follow_up_common.php')) {
    include_once ROOT . '/include/customer_follow_up_common.php';
}

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
            $safeUrl = customerTimelinePreviewEscape($url);
            $safeName = customerTimelinePreviewEscape($fileName !== '' ? $fileName : 'Open file');
            $extension = strtolower(pathinfo($attachment, PATHINFO_EXTENSION));
            if (in_array($extension, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
                $items[$attachment] = '<button type="button" class="ct-proof-image" data-ct-image="' . $safeUrl . '" data-ct-image-name="' . $safeName . '" aria-label="Enlarge proof: ' . $safeName . '">'
                    . '<img src="' . $safeUrl . '" alt="Proof thumbnail: ' . $safeName . '" loading="lazy"><span>' . $safeName . '</span></button>';
            } else {
                $items[$attachment] = '<a class="ct-proof-link" href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">'
                    . $safeName . ' · Open file</a>';
            }
        }

        if (empty($items)) {
            return '';
        }
        return '<div class="ct-proof"><span class="ct-proof-label">Proof</span><div class="ct-proof-links">' . implode('', $items) . '</div></div>';
    }
}

if (!function_exists('customerTimelinePreviewRenderFollowUpAttachment')) {
    function customerTimelinePreviewRenderFollowUpAttachment($attachmentPath)
    {
        $attachmentPath = trim(str_replace('\\', '/', (string) $attachmentPath));
        if ($attachmentPath === '' || !function_exists('customerFollowUpBuildAttachmentUrl')) {
            return '';
        }
        $url = customerFollowUpBuildAttachmentUrl($attachmentPath);
        if ($url === '') {
            return '';
        }
        $fileName = basename($attachmentPath);
        $safeUrl = customerTimelinePreviewEscape($url);
        $safeName = customerTimelinePreviewEscape($fileName !== '' ? $fileName : 'Follow-Up proof');
        $extension = strtolower(pathinfo($attachmentPath, PATHINFO_EXTENSION));
        if (in_array($extension, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
            return '<div class="ct-proof"><span class="ct-proof-label">Submitted proof</span><div class="ct-proof-links">'
                . '<button type="button" class="ct-proof-image" data-ct-image="' . $safeUrl . '" data-ct-image-name="' . $safeName . '" aria-label="Enlarge proof: ' . $safeName . '">'
                . '<img src="' . $safeUrl . '" alt="Follow-Up proof thumbnail: ' . $safeName . '" loading="lazy"><span>' . $safeName . '</span></button></div></div>';
        }
        return '<div class="ct-proof"><span class="ct-proof-label">Submitted proof</span><div class="ct-proof-links"><a class="ct-proof-link" href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeName . ' · Open file</a></div></div>';
    }
}

if (!function_exists('customerTimelinePreviewRenderLightboxMarkup')) {
    function customerTimelinePreviewRenderLightboxMarkup()
    {
        return '<div class="ct-lightbox" hidden role="dialog" aria-modal="true" aria-label="Proof image preview">'
            . '<div class="ct-lightbox-panel"><button type="button" class="ct-lightbox-close" aria-label="Close image preview">×</button>'
            . '<img src="" alt=""><div class="ct-lightbox-caption"></div></div></div>'
            . '<script>(function(){if(window.__customerTimelineLightboxBound){return;}window.__customerTimelineLightboxBound=true;var active=null;var previousFocus=null;function close(){if(!active){return;}active.hidden=true;active.querySelector("img").src="";document.body.style.overflow="";if(previousFocus){previousFocus.focus();}active=null;}document.addEventListener("click",function(event){var closeButton=event.target.closest(".ct-lightbox-close");if(closeButton){close();return;}var lightbox=event.target.closest(".ct-lightbox");if(lightbox&&event.target===lightbox){close();return;}var trigger=event.target.closest(".ct-proof-image[data-ct-image]");if(!trigger){return;}var root=trigger.closest(".customer-follow-up-workflow, .customer-timeline-preview");if(!root){return;}active=root.querySelector(".ct-lightbox");if(!active){return;}previousFocus=trigger;var image=active.querySelector("img");var name=trigger.getAttribute("data-ct-image-name")||"Proof image";image.src=trigger.getAttribute("data-ct-image");image.alt=name;active.querySelector(".ct-lightbox-caption").textContent=name;active.hidden=false;document.body.style.overflow="hidden";active.querySelector(".ct-lightbox-close").focus();});document.addEventListener("keydown",function(event){if(event.key==="Escape"){close();}});})();</script>';
    }
}

if (!function_exists('customerTimelinePreviewRenderMessageSnapshot')) {
    function customerTimelinePreviewRenderMessageSnapshot($payload, $heading = 'Message sent to customer')
    {
        if (!is_array($payload)) {
            $payload = array();
        }
        $messageStatus = strtolower(trim((string) (isset($payload['sent_message_status']) ? $payload['sent_message_status'] : '')));
        $messageText = trim((string) (isset($payload['sent_message_text']) ? $payload['sent_message_text'] : ''));
        $html = '<div class="ct-message-snapshot"><div class="ct-message-snapshot-title">' . customerTimelinePreviewEscape($heading) . '</div>';
        if ($messageStatus === 'not_sent') {
            $html .= '<div class="ct-message-snapshot-empty">No written message was sent for this follow-up.</div>';
        } elseif ($messageText !== '') {
            $html .= '<details class="ct-message-snapshot-details"><summary>View complete message (' . (function_exists('mb_strlen') ? (int) mb_strlen($messageText, 'UTF-8') : strlen($messageText)) . ' characters)</summary>'
                . '<div class="ct-message-snapshot-body">' . nl2br(customerTimelinePreviewEscape($messageText)) . '</div></details>';
        } else {
            $html .= '<div class="ct-message-snapshot-empty">No sent-message snapshot was recorded for this submission.</div>';
        }
        $shortcutText = trim((string) (isset($payload['message_shortcut_text']) ? $payload['message_shortcut_text'] : ''));
        $shortcutLabel = trim((string) (isset($payload['message_shortcut_label']) ? $payload['message_shortcut_label'] : ''));
        if ($shortcutText !== '') {
            $html .= '<details class="ct-next-message"><summary>Next follow-up template' . ($shortcutLabel !== '' ? ' · ' . customerTimelinePreviewEscape($shortcutLabel) : '') . '</summary>'
                . '<div class="ct-message-snapshot-body">' . nl2br(customerTimelinePreviewEscape($shortcutText)) . '</div></details>';
        }
        return $html . '</div>';
    }
}

if (!function_exists('customerTimelinePreviewCanViewFollowUp')) {
    function customerTimelinePreviewCanViewFollowUp($connect)
    {
        if (!function_exists('checkPinByGroupId') || !function_exists('isActionAllowed')) {
            return false;
        }
        return isActionAllowed('View', checkPinByGroupId($connect, 151));
    }
}

if (!function_exists('customerTimelinePreviewGetReviewPermissions')) {
    function customerTimelinePreviewGetReviewPermissions($connect)
    {
        $userId = defined('USER_ID') ? (int) USER_ID : 0;
        return array(
            'approve' => $userId > 0 && function_exists('customerFollowUpUserHasPinAccess')
                && customerFollowUpUserHasPinAccess($connect, $userId, 11, 151),
            'reject' => $userId > 0 && function_exists('customerFollowUpUserHasPinAccess')
                && customerFollowUpUserHasPinAccess($connect, $userId, 12, 151),
        );
    }
}

if (!function_exists('customerTimelineHandleFollowUpReviewPost')) {
    function customerTimelineHandleFollowUpReviewPost($connect, $platform, $customerId, $returnUrl)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['customer_timeline_action'])) {
            return false;
        }

        $action = trim((string) $_POST['customer_timeline_action']);
        $followUpId = isset($_POST['follow_up_id']) ? (int) $_POST['follow_up_id'] : 0;
        $csrf = isset($_POST['customer_follow_up_csrf']) ? (string) $_POST['customer_follow_up_csrf'] : '';
        if (empty($_SESSION['customer_follow_up_csrf']) || !hash_equals((string) $_SESSION['customer_follow_up_csrf'], $csrf)) {
            $_SESSION['customer_timeline_flash'] = array('type' => 'danger', 'message' => 'Your session expired. Refresh the page and try again.');
        } elseif (!in_array($action, array('approve_follow_up', 'reject_follow_up'), true)) {
            $_SESSION['customer_timeline_flash'] = array('type' => 'danger', 'message' => 'Invalid Follow-Up review action.');
        } else {
            $caseRow = function_exists('customerFollowUpReadFollowUpCase')
                ? customerFollowUpReadFollowUpCase($connect, $followUpId)
                : array();
            $casePlatform = strtolower(trim((string) (isset($caseRow['platform']) ? $caseRow['platform'] : '')));
            $caseCustomerId = (int) (isset($caseRow['customer_id']) ? $caseRow['customer_id'] : 0);
            if (empty($caseRow) || strtoupper((string) (isset($caseRow['status']) ? $caseRow['status'] : '')) !== 'A'
                || $casePlatform !== strtolower(trim((string) $platform)) || $caseCustomerId !== (int) $customerId) {
                $_SESSION['customer_timeline_flash'] = array('type' => 'danger', 'message' => 'This Follow-Up case does not belong to the displayed customer.');
            } else {
                $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId, (int) (isset($caseRow['current_round_no']) ? $caseRow['current_round_no'] : 0));
                $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
                if (empty($roundRow) || $roundStatus !== 'Pending Approval') {
                    $_SESSION['customer_timeline_flash'] = array('type' => 'danger', 'message' => 'Only a currently pending round can be reviewed.');
                } elseif ($action === 'approve_follow_up') {
                    $result = customerFollowUpApproveRound(
                        $connect,
                        $followUpId,
                        isset($_POST['approval_comment']) ? $_POST['approval_comment'] : '',
                        defined('USER_ID') ? USER_ID : 0,
                        defined('USER_GROUP') ? USER_GROUP : 0,
                        isset($GLOBALS['finance_connect']) ? $GLOBALS['finance_connect'] : null
                    );
                    $_SESSION['customer_timeline_flash'] = array(
                        'type' => !empty($result['success']) ? 'success' : 'danger',
                        'message' => isset($result['message']) ? (string) $result['message'] : 'Unable to approve this Follow-Up.',
                    );
                } else {
                    $result = customerFollowUpRejectRound(
                        $connect,
                        $followUpId,
                        isset($_POST['reject_reason']) ? $_POST['reject_reason'] : '',
                        defined('USER_ID') ? USER_ID : 0,
                        defined('USER_GROUP') ? USER_GROUP : 0
                    );
                    $_SESSION['customer_timeline_flash'] = array(
                        'type' => !empty($result['success']) ? 'success' : 'danger',
                        'message' => isset($result['message']) ? (string) $result['message'] : 'Unable to reject this Follow-Up.',
                    );
                }
            }
        }

        $redirectUrl = $returnUrl . '#customer-follow-up-workflow';
        if (!headers_sent()) {
            header('Location: ' . $redirectUrl);
        } else {
            echo '<script>location.replace(' . json_encode($redirectUrl) . ');</script>';
        }
        exit;
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
        $reviewStateLabels = array(
            'submit_follow_up' => array('Pending Approval', 'pending'),
            'resubmit_rejected_follow_up' => array('Pending Approval', 'pending'),
            'approve_follow_up' => array('Approved', 'approved'),
            'reject_follow_up' => array('Not approved', 'rejected'),
        );
        $reviewState = isset($reviewStateLabels[strtolower($actionType)]) ? $reviewStateLabels[strtolower($actionType)] : array();
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
            . '</span>';
        if (!empty($reviewState)) {
            $html .= '<span class="ct-review-state ct-review-state-' . customerTimelinePreviewEscape($reviewState[1]) . '">' . customerTimelinePreviewEscape($reviewState[0]) . '</span>';
        }
        $html .= '</div><time class="ct-event-time">' . customerTimelinePreviewEscape($actionAt !== '' ? $actionAt : 'Time unavailable') . '</time></header>';
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
        if (in_array(strtolower($actionType), array('submit_follow_up', 'resubmit_rejected_follow_up'), true)) {
            $html .= customerTimelinePreviewRenderMessageSnapshot($newValue, 'Submitted message snapshot');
        } elseif ($newMessage !== '' && $oldMessage !== $newMessage) {
            $html .= '<details class="ct-message-change"><summary>View next follow-up template change</summary>';
            $html .= '<div class="ct-event-content"><strong>Previous:</strong><br>' . nl2br(customerTimelinePreviewEscape($oldMessage !== '' ? $oldMessage : 'Not set')) . '</div>';
            $html .= '<div class="ct-event-content"><strong>Current:</strong><br>' . nl2br(customerTimelinePreviewEscape($newMessage)) . '</div></details>';
        }
        if ($remark !== '') {
            $html .= '<div class="ct-event-content">' . nl2br(customerTimelinePreviewEscape($remark)) . '</div>';
        }
        $attachmentPath = trim((string) (isset($newValue['attachment_path']) ? $newValue['attachment_path'] : ''));
        if ($attachmentPath !== '') {
            $html .= customerTimelinePreviewRenderFollowUpAttachment($attachmentPath);
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

if (!function_exists('customerTimelinePreviewRenderFollowUpWorkflow')) {
    function customerTimelinePreviewRenderFollowUpWorkflow($connect, $platform, $customerId, $followUpRows, $permissions)
    {
        $caseDetails = array();
        $pendingCount = 0;
        foreach ($followUpRows as $followUpRow) {
            $followUpId = (int) (isset($followUpRow['id']) ? $followUpRow['id'] : 0);
            if ($followUpId <= 0) {
                continue;
            }
            $roundNo = (int) (isset($followUpRow['current_round_no']) ? $followUpRow['current_round_no'] : 0);
            $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId, $roundNo);
            if (empty($roundRow)) {
                continue;
            }
            $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
            $currentRoundId = (int) (isset($roundRow['id']) ? $roundRow['id'] : 0);
            $roundNumberMap = $currentRoundId > 0 ? array($currentRoundId => $roundNo) : array();
            if (defined('CUSTOMER_FOLLOW_UP_ROUND') && customerTimelinePreviewTableExists($connect, CUSTOMER_FOLLOW_UP_ROUND)) {
                $roundTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) CUSTOMER_FOLLOW_UP_ROUND);
                $roundResult = mysqli_query($connect, "SELECT `id`, `round_no` FROM `" . $roundTable . "` WHERE `follow_up_id`=" . $followUpId . " AND `status`='A' ORDER BY `round_no` DESC, `id` DESC");
                if ($roundResult) {
                    while ($roundIndexRow = mysqli_fetch_assoc($roundResult)) {
                        $roundNumberMap[(int) $roundIndexRow['id']] = (int) $roundIndexRow['round_no'];
                    }
                }
            }
            $actionRows = customerFollowUpFetchActionLogRows($connect, $followUpId, 0);
            $allRoundActions = array();
            $latestSubmission = array();
            foreach ($actionRows as $actionRow) {
                $actionRoundId = (int) (isset($actionRow['round_id']) ? $actionRow['round_id'] : 0);
                $actionRow['round_no'] = isset($roundNumberMap[$actionRoundId]) ? (int) $roundNumberMap[$actionRoundId] : 0;
                $actionRow['new_value_decoded'] = customerFollowUpDecodeActionLogPayload(isset($actionRow['new_value']) ? $actionRow['new_value'] : '');
                $allRoundActions[] = $actionRow;
                if ($actionRoundId === $currentRoundId
                    && empty($latestSubmission)
                    && in_array(strtolower(trim((string) (isset($actionRow['action_type']) ? $actionRow['action_type'] : ''))), array('submit_follow_up', 'resubmit_rejected_follow_up'), true)
                ) {
                    $latestSubmission = $actionRow;
                }
            }
            $caseDetails[] = array(
                'case' => $followUpRow,
                'round' => $roundRow,
                'round_no' => $roundNo,
                'round_status' => $roundStatus !== '' ? $roundStatus : customerFollowUpNormalizeStatus(isset($followUpRow['current_status']) ? $followUpRow['current_status'] : ''),
                'actions' => $allRoundActions,
                'submission' => $latestSubmission,
                'can_manage' => function_exists('customerFollowUpCanUserManageCase')
                    && customerFollowUpCanUserManageCase($followUpRow, defined('USER_ID') ? USER_ID : 0, defined('USER_GROUP') ? USER_GROUP : 0, $connect),
            );
            if ($roundStatus === 'Pending Approval') {
                $pendingCount++;
            }
        }

        if (empty($_SESSION['customer_follow_up_csrf'])) {
            $_SESSION['customer_follow_up_csrf'] = bin2hex(random_bytes(32));
        }
        $csrf = customerTimelinePreviewEscape($_SESSION['customer_follow_up_csrf']);
        $flash = isset($_SESSION['customer_timeline_flash']) && is_array($_SESSION['customer_timeline_flash']) ? $_SESSION['customer_timeline_flash'] : array();
        unset($_SESSION['customer_timeline_flash']);
        $permissions = is_array($permissions) ? $permissions : array();
        $canApprove = !empty($permissions['approve']);
        $canReject = !empty($permissions['reject']);
        ?>
        <section class="customer-follow-up-workflow" id="customer-follow-up-workflow" aria-labelledby="customer-follow-up-workflow-title">
            <style>
                .customer-follow-up-workflow { --fu-ink:#183849; --fu-muted:#617887; --fu-line:#d7e2e8; --fu-paper:#fff; margin:1.5rem 0 1.8rem; color:var(--fu-ink); }
                .customer-follow-up-workflow .fu-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap; padding:1rem 1.15rem; border-radius:.55rem .55rem 0 0; background:#173b50; color:#fff; }
                .customer-follow-up-workflow .fu-hero h4 { margin:0; font-size:1.25rem; font-weight:750; }
                .customer-follow-up-workflow .fu-hero p { margin:.3rem 0 0; color:#d5e4eb; max-width:58rem; }
                .customer-follow-up-workflow .fu-counts { display:flex; flex-wrap:wrap; gap:.45rem; }
                .customer-follow-up-workflow .fu-count { border:1px solid rgba(255,255,255,.35); border-radius:999px; padding:.24rem .65rem; font-size:.82rem; white-space:nowrap; }
                .customer-follow-up-workflow .fu-body { padding:1rem; border:1px solid var(--fu-line); border-top:0; background:#f7fafb; }
                .customer-follow-up-workflow .fu-flash { margin-bottom:1rem; padding:.7rem .9rem; border-radius:.35rem; background:#eaf5ed; border:1px solid #b8d8bf; }
                .customer-follow-up-workflow .fu-flash.danger { background:#fff1ef; border-color:#e8c0b9; }
                .customer-follow-up-workflow .fu-section-title { display:flex; align-items:center; gap:.55rem; margin:0 0 .75rem; font-size:1.05rem; font-weight:750; }
                .customer-follow-up-workflow .fu-queue { padding:.9rem; margin:0 0 1.1rem; border:1px solid #e9c89f; border-left:5px solid #b6752b; border-radius:.4rem; background:#fffaf3; }
                .customer-follow-up-workflow .fu-queue-empty { color:var(--fu-muted); padding:.35rem 0; }
                .customer-follow-up-workflow .fu-review-card,.customer-follow-up-workflow .fu-case-card { background:var(--fu-paper); border:1px solid var(--fu-line); border-radius:.4rem; padding:.9rem; margin:.65rem 0; }
                .customer-follow-up-workflow .fu-review-card { border-left:4px solid #d08b3f; }
                .customer-follow-up-workflow .fu-card-top { display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem; flex-wrap:wrap; }
                .customer-follow-up-workflow .fu-card-top h6 { margin:0; font-size:1rem; font-weight:750; }
                .customer-follow-up-workflow .fu-meta { color:var(--fu-muted); font-size:.84rem; margin-top:.28rem; overflow-wrap:anywhere; }
                .customer-follow-up-workflow .fu-status { display:inline-flex; border-radius:999px; padding:.24rem .62rem; font-size:.78rem; font-weight:750; background:#edf2f4; color:#465e6a; white-space:nowrap; }
                .customer-follow-up-workflow .fu-status.pending,.customer-follow-up-workflow .fu-status.pending-approval { background:#fff0d9; color:#87531d; }
                .customer-follow-up-workflow .fu-status.approved { background:#e6f3e9; color:#2e693b; }
                .customer-follow-up-workflow .fu-status.rejected { background:#fbe9e7; color:#a03f35; }
                .customer-follow-up-workflow .fu-case-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,390px),1fr)); gap:.65rem; }
                .customer-follow-up-workflow .fu-case-card { margin:0; }
                .customer-follow-up-workflow .fu-case-card summary { cursor:pointer; list-style:none; }
                .customer-follow-up-workflow .fu-case-card summary::-webkit-details-marker { display:none; }
                .customer-follow-up-workflow .fu-case-card summary:after { content:'View record details +'; display:block; margin-top:.55rem; color:#2d6683; font-size:.83rem; font-weight:700; }
                .customer-follow-up-workflow .fu-case-card[open] summary:after { content:'Hide record details −'; }
                .customer-follow-up-workflow .fu-history { border-top:1px solid #e6edf1; margin-top:.75rem; padding-top:.6rem; }
                .customer-follow-up-workflow .fu-history-item { padding:.55rem 0; border-bottom:1px solid #edf1f3; }
                .customer-follow-up-workflow .fu-history-item:last-child { border-bottom:0; }
                .customer-follow-up-workflow .fu-history-title { font-weight:700; }
                .customer-follow-up-workflow .fu-actions { display:flex; flex-wrap:wrap; gap:.45rem; margin-top:.8rem; }
                .customer-follow-up-workflow .fu-review-form { margin-top:.8rem; padding-top:.75rem; border-top:1px solid #ead9c3; }
                .customer-follow-up-workflow .fu-review-form textarea { width:100%; min-height:4.5rem; margin:.35rem 0; }
                .customer-follow-up-workflow .ct-message-snapshot { margin-top:.75rem; padding:.65rem .75rem; border:1px solid #e5ebee; border-radius:.35rem; background:#fbfcfd; }
                .customer-follow-up-workflow .ct-message-snapshot-title { font-size:.83rem; font-weight:750; color:#425e6e; }
                .customer-follow-up-workflow .ct-message-snapshot-details,.customer-follow-up-workflow .ct-next-message { margin-top:.35rem; }
                .customer-follow-up-workflow .ct-message-snapshot-details summary,.customer-follow-up-workflow .ct-next-message summary { cursor:pointer; color:#285b78; font-size:.84rem; font-weight:700; }
                .customer-follow-up-workflow .ct-message-snapshot-body { max-height:20rem; overflow:auto; margin-top:.4rem; padding:.65rem; border-left:3px solid #9ebdcd; background:#f3f8fa; white-space:pre-wrap; overflow-wrap:anywhere; }
                .customer-follow-up-workflow .ct-message-snapshot-empty { margin-top:.35rem; color:#6a7d89; font-size:.84rem; }
                .customer-follow-up-workflow .ct-proof-image { display:flex; align-items:center; gap:.45rem; max-width:min(100%, 19rem); padding:.28rem .42rem; border:1px solid #cbdbe4; border-radius:.32rem; background:#fff; color:#285b78; text-align:left; cursor:zoom-in; }
                .customer-follow-up-workflow .ct-proof-image img { width:3.2rem; height:2.4rem; object-fit:cover; border-radius:.2rem; background:#edf2f4; }
                .customer-follow-up-workflow .ct-proof-image span { font-size:.8rem; overflow-wrap:anywhere; }
                .customer-follow-up-workflow .ct-message-snapshot-title { font-size:.83rem; font-weight:750; color:#425e6e; }
                .customer-follow-up-workflow .fu-message-snapshot details { margin-top:.35rem; }
                .customer-follow-up-workflow .fu-message-snapshot summary { cursor:pointer; color:#285b78; font-size:.84rem; font-weight:700; }
                .customer-follow-up-workflow .fu-message-body { white-space:pre-wrap; overflow-wrap:anywhere; padding:.7rem; margin-top:.4rem; border-left:3px solid #aac6d4; background:#f3f8fa; }
                .customer-follow-up-workflow .fu-muted { color:var(--fu-muted); font-size:.84rem; }
                .customer-follow-up-workflow .fu-link { display:inline-flex; align-items:center; border:1px solid #bdd0da; border-radius:.28rem; padding:.34rem .6rem; background:#fff; color:#245a76; text-decoration:none; font-weight:700; }
                .customer-follow-up-workflow .ct-lightbox[hidden] { display:none; }
                .customer-follow-up-workflow .ct-lightbox { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; padding:1rem; background:rgba(9,20,27,.88); }
                .customer-follow-up-workflow .ct-lightbox-panel { position:relative; display:flex; flex-direction:column; align-items:center; max-width:96vw; max-height:96vh; }
                .customer-follow-up-workflow .ct-lightbox-panel img { display:block; max-width:92vw; max-height:86vh; object-fit:contain; background:#fff; }
                .customer-follow-up-workflow .ct-lightbox-caption { margin-top:.5rem; color:#fff; font-size:.9rem; text-align:center; overflow-wrap:anywhere; }
                .customer-follow-up-workflow .ct-lightbox-close { position:absolute; top:-.7rem; right:-.7rem; width:2.25rem; height:2.25rem; border:0; border-radius:50%; background:#fff; color:#173b50; font-size:1.35rem; font-weight:700; cursor:pointer; }
                @media (max-width:575px) { .customer-follow-up-workflow .fu-body { padding:.65rem; } .customer-follow-up-workflow .fu-review-card,.customer-follow-up-workflow .fu-case-card { padding:.7rem; } }
            </style>
            <div class="fu-hero">
                <div><h4 id="customer-follow-up-workflow-title">Follow-Up Records &amp; Approval Queue</h4><p>See each current case and round status, open the complete action history, and review submitted message snapshots and proof before approving or rejecting.</p></div>
                <div class="fu-counts"><span class="fu-count"><?php echo count($caseDetails); ?> Follow-Up cases</span><span class="fu-count"><?php echo $pendingCount; ?> pending approval</span></div>
            </div>
            <div class="fu-body">
                <?php if (!empty($flash)) { ?><div class="fu-flash <?php echo customerTimelinePreviewEscape(isset($flash['type']) ? $flash['type'] : ''); ?>" role="status"><?php echo customerTimelinePreviewEscape(isset($flash['message']) ? $flash['message'] : ''); ?></div><?php } ?>
                <section class="fu-queue" aria-labelledby="customer-follow-up-queue-title">
                    <h5 class="fu-section-title" id="customer-follow-up-queue-title">Pending Approval Queue <span class="fu-status pending"><?php echo $pendingCount; ?> waiting</span></h5>
                    <?php if ($pendingCount === 0) { ?><div class="fu-queue-empty">No active Follow-Up round is waiting for approval for this customer.</div><?php } ?>
                    <?php foreach ($caseDetails as $detail) {
                        if ($detail['round_status'] !== 'Pending Approval') { continue; }
                        $case = $detail['case'];
                        $round = $detail['round'];
                        $submission = $detail['submission'];
                        $payload = isset($submission['new_value_decoded']) && is_array($submission['new_value_decoded']) ? $submission['new_value_decoded'] : array();
                        $attachmentPath = trim((string) (isset($payload['attachment_path']) ? $payload['attachment_path'] : (isset($round['attachment']) ? $round['attachment'] : '')));
                        $followUpId = (int) $case['id'];
                        $roundNo = (int) $detail['round_no'];
                        $caseName = trim((string) (isset($case['customer_name']) ? $case['customer_name'] : ''));
                        $orderNo = trim((string) (isset($case['order_no']) ? $case['order_no'] : ''));
                        $actorName = !empty($submission['action_by']) ? customerFollowUpGetUserDisplayName($connect, $submission['action_by']) : '';
                        ?>
                        <article class="fu-review-card">
                            <div class="fu-card-top"><div><h6><?php echo customerTimelinePreviewEscape($caseName !== '' ? $caseName : 'Follow-Up Case #' . $followUpId); ?></h6><div class="fu-meta">Case #<?php echo $followUpId; ?> · Round <?php echo $roundNo; ?><?php echo $orderNo !== '' ? ' · Order ' . customerTimelinePreviewEscape($orderNo) : ''; ?></div><div class="fu-meta">Submitted <?php echo customerTimelinePreviewEscape(trim((string) (isset($submission['action_date']) ? $submission['action_date'] : '') . ' ' . (isset($submission['action_time']) ? $submission['action_time'] : '')) ?: 'time unavailable'); ?><?php echo $actorName !== '' ? ' by ' . customerTimelinePreviewEscape($actorName) : ''; ?></div></div><span class="fu-status pending">Pending Approval</span></div>
                            <?php echo customerTimelinePreviewRenderMessageSnapshot($payload); ?>
                            <?php echo customerTimelinePreviewRenderFollowUpAttachment($attachmentPath); ?>
                            <?php if ($canApprove || $canReject) { ?>
                                <div class="fu-actions"><span class="fu-muted">Review controls</span></div>
                                <?php if ($canApprove) { ?><form method="post" action="" class="fu-review-form"><input type="hidden" name="customer_timeline_action" value="approve_follow_up"><input type="hidden" name="id" value="<?php echo (int) $customerId; ?>"><input type="hidden" name="follow_up_id" value="<?php echo $followUpId; ?>"><input type="hidden" name="customer_follow_up_csrf" value="<?php echo $csrf; ?>"><label for="ct-approval-comment-<?php echo $followUpId; ?>">Approval note (optional)</label><textarea class="form-control" id="ct-approval-comment-<?php echo $followUpId; ?>" name="approval_comment" maxlength="4000" placeholder="Add a note for this approval"></textarea><button type="submit" class="btn btn-sm btn-success">Approve this round</button></form><?php } ?>
                                <?php if ($canReject) { ?><form method="post" action="" class="fu-review-form" onsubmit="return this.querySelector('[name=reject_reason]').value.trim() !== '' || (alert('Please enter a rejection reason.'), false);"><input type="hidden" name="customer_timeline_action" value="reject_follow_up"><input type="hidden" name="id" value="<?php echo (int) $customerId; ?>"><input type="hidden" name="follow_up_id" value="<?php echo $followUpId; ?>"><input type="hidden" name="customer_follow_up_csrf" value="<?php echo $csrf; ?>"><label for="ct-reject-reason-<?php echo $followUpId; ?>">Rejection reason (required)</label><textarea class="form-control" id="ct-reject-reason-<?php echo $followUpId; ?>" name="reject_reason" maxlength="4000" required placeholder="Explain what needs correction"></textarea><button type="submit" class="btn btn-sm btn-outline-danger">Reject and return</button></form><?php } ?>
                            <?php } else { ?><div class="fu-muted mt-2">This round is waiting for an authorized reviewer. You can view the submission but cannot approve or reject it with your current permissions.</div><?php } ?>
                        </article>
                    <?php } ?>
                </section>

                <section aria-labelledby="customer-follow-up-records-title">
                    <h5 class="fu-section-title" id="customer-follow-up-records-title">Follow-Up Records</h5>
                    <?php if (empty($caseDetails)) { ?><div class="fu-queue-empty">No active Follow-Up cases are linked to this platform customer.</div><?php } else { ?>
                        <div class="fu-case-list">
                            <?php foreach ($caseDetails as $detail) {
                                $case = $detail['case'];
                                $round = $detail['round'];
                                $followUpId = (int) $case['id'];
                                $roundNo = (int) $detail['round_no'];
                                $roundStatus = $detail['round_status'];
                                $statusClass = strtolower(str_replace(' ', '-', $roundStatus));
                                $caseName = trim((string) (isset($case['customer_name']) ? $case['customer_name'] : ''));
                                $orderNo = trim((string) (isset($case['order_no']) ? $case['order_no'] : ''));
                                $workspaceUrl = rtrim((string) SITEURL, '/') . '/customer/customer_follow_up_list.php?follow_up_id=' . $followUpId . '&round_id=' . (int) (isset($round['id']) ? $round['id'] : 0);
                                ?>
                                <details class="fu-case-card">
                                    <summary><div class="fu-card-top"><div><h6><?php echo customerTimelinePreviewEscape($caseName !== '' ? $caseName : 'Follow-Up Case #' . $followUpId); ?></h6><div class="fu-meta">Case #<?php echo $followUpId; ?> · Round <?php echo $roundNo; ?><?php echo $orderNo !== '' ? ' · Order ' . customerTimelinePreviewEscape($orderNo) : ''; ?></div></div><span class="fu-status <?php echo customerTimelinePreviewEscape($statusClass); ?>"><?php echo customerTimelinePreviewEscape($roundStatus !== '' ? $roundStatus : 'Status unavailable'); ?></span></div></summary>
                                    <div class="fu-history">
                                        <div class="fu-meta">Next follow-up date: <?php echo customerTimelinePreviewEscape(isset($round['next_follow_up_date']) ? $round['next_follow_up_date'] : 'Not scheduled'); ?></div>
                                        <?php if (!empty($detail['can_manage'])) { ?><div class="fu-actions"><a class="fu-link" href="<?php echo customerTimelinePreviewEscape($workspaceUrl); ?>">Open case to submit / appeal</a></div><?php } ?>
                                        <h6 class="mt-3 mb-2">Case action history across rounds</h6>
                                        <?php if (empty($detail['actions'])) { ?><div class="fu-muted">No action history is recorded for this case yet.</div><?php } else { ?>
                                            <?php foreach (array_reverse($detail['actions']) as $actionRow) {
                                                $actionLabel = customerTimelinePreviewActionTitle(isset($actionRow['action_type']) ? $actionRow['action_type'] : '');
                                                $actionAt = trim((string) (isset($actionRow['action_date']) ? $actionRow['action_date'] : '') . ' ' . (isset($actionRow['action_time']) ? $actionRow['action_time'] : ''));
                                                $actionBy = isset($actionRow['action_by']) ? customerFollowUpGetUserDisplayName($connect, $actionRow['action_by']) : '';
                                                $roundLabel = (int) (isset($actionRow['round_no']) ? $actionRow['round_no'] : 0) > 0 ? ' · Round ' . (int) $actionRow['round_no'] : '';
                                                $payload = isset($actionRow['new_value_decoded']) && is_array($actionRow['new_value_decoded']) ? $actionRow['new_value_decoded'] : array();
                                                ?>
                                                <div class="fu-history-item"><div class="fu-history-title"><?php echo customerTimelinePreviewEscape($actionLabel); ?></div><div class="fu-meta"><?php echo customerTimelinePreviewEscape($actionAt !== '' ? $actionAt : 'Time unavailable'); ?><?php echo customerTimelinePreviewEscape($roundLabel); ?><?php echo $actionBy !== '' ? ' · ' . customerTimelinePreviewEscape($actionBy) : ''; ?><?php echo trim((string) (isset($actionRow['remark']) ? $actionRow['remark'] : '')) !== '' ? ' · ' . customerTimelinePreviewEscape($actionRow['remark']) : ''; ?></div>
                                                    <?php if (in_array(strtolower(trim((string) (isset($actionRow['action_type']) ? $actionRow['action_type'] : ''))), array('submit_follow_up', 'resubmit_rejected_follow_up'), true)) { ?>
                                                        <?php echo customerTimelinePreviewRenderMessageSnapshot($payload, 'Submitted message snapshot'); ?>
                                                        <?php echo customerTimelinePreviewRenderFollowUpAttachment(isset($payload['attachment_path']) ? $payload['attachment_path'] : ''); ?>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </details>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </section>
            </div>
            <?php echo customerTimelinePreviewRenderLightboxMarkup(); ?>
        </section>
        <?php
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
        $canViewFollowUpModule = customerTimelinePreviewCanViewFollowUp($connect);
        $followUpPermissions = customerTimelinePreviewGetReviewPermissions($connect);
        // Keep the same visibility rule as the Follow-Up list page: only administrators see
        // every case, everybody else sees the cases assigned to them.
        $canViewAllFollowUpRecords = $canViewFollowUpModule && function_exists('customerFollowUpIsAdminUser')
            && customerFollowUpIsAdminUser(defined('USER_GROUP') ? USER_GROUP : null);
        if ($canViewFollowUpModule && function_exists('customerFollowUpFetchActiveCasesByCustomer')) {
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
        if ($canViewFollowUpModule
            && defined('CUSTOMER_FOLLOW_UP_ACTION_LOG')
            && customerTimelinePreviewTableExists($connect, CUSTOMER_FOLLOW_UP_ACTION_LOG)
        ) {
            foreach ($followUpRows as $followUpRow) {
                $followUpId = (int) (isset($followUpRow['id']) ? $followUpRow['id'] : 0);
                if ($followUpId <= 0 || !function_exists('customerFollowUpFetchActionLogRows')) {
                    continue;
                }
                foreach (customerFollowUpFetchActionLogRows($connect, $followUpId, 0) as $actionRow) {
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
        if ($canViewFollowUpModule) {
            customerTimelinePreviewRenderFollowUpWorkflow(
                $connect,
                $platform,
                $customerId,
                $followUpRows,
                $followUpPermissions
            );
        }
        ?>
        <section class="customer-timeline-preview mt-4" id="customer-activity-timeline" aria-labelledby="customer-timeline-title">
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
                .customer-timeline-preview .ct-review-state { display:inline-flex; border-radius:999px; padding:.18rem .55rem; font-size:.75rem; font-weight:750; background:#edf2f4; color:#465e6a; }
                .customer-timeline-preview .ct-review-state-pending { background:#fff0d9; color:#87531d; }
                .customer-timeline-preview .ct-review-state-approved { background:#e6f3e9; color:#2e693b; }
                .customer-timeline-preview .ct-review-state-rejected { background:#fbe9e7; color:#a03f35; }
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
                .customer-timeline-preview .ct-proof-links { display:flex; gap:.45rem; flex-wrap:wrap; }
                .customer-timeline-preview .ct-proof-link { display:inline-block; border:1px solid #cbdbe4; border-radius:.25rem; padding:.16rem .45rem; background:#fff; color:#285b78; font-size:.82rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-proof-image { display:flex; align-items:center; gap:.45rem; max-width:min(100%, 19rem); padding:.28rem .42rem; border:1px solid #cbdbe4; border-radius:.32rem; background:#fff; color:#285b78; text-align:left; cursor:zoom-in; }
                .customer-timeline-preview .ct-proof-image img { width:3.2rem; height:2.4rem; object-fit:cover; border-radius:.2rem; background:#edf2f4; }
                .customer-timeline-preview .ct-proof-image span { font-size:.8rem; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-message-snapshot { margin-top:.65rem; padding:.65rem .75rem; border:1px solid #dce7ec; border-radius:.35rem; background:#f8fbfc; }
                .customer-timeline-preview .ct-message-snapshot-title { font-size:.82rem; font-weight:750; color:#39596b; }
                .customer-timeline-preview .ct-message-snapshot-details,.customer-timeline-preview .ct-next-message { margin-top:.32rem; }
                .customer-timeline-preview .ct-message-snapshot-details summary,.customer-timeline-preview .ct-next-message summary { cursor:pointer; color:#285b78; font-size:.84rem; font-weight:700; }
                .customer-timeline-preview .ct-message-snapshot-body { max-height:24rem; overflow:auto; margin-top:.45rem; padding:.7rem; border-left:3px solid #9ebdcd; background:#fff; white-space:pre-wrap; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-message-snapshot-empty { margin-top:.35rem; color:#6a7d89; font-size:.84rem; }
                .customer-timeline-preview .ct-lightbox[hidden] { display:none; }
                .customer-timeline-preview .ct-lightbox { position:fixed; inset:0; z-index:10000; display:flex; align-items:center; justify-content:center; padding:1rem; background:rgba(9,20,27,.88); }
                .customer-timeline-preview .ct-lightbox-panel { position:relative; display:flex; flex-direction:column; align-items:center; max-width:96vw; max-height:96vh; }
                .customer-timeline-preview .ct-lightbox-panel img { display:block; max-width:92vw; max-height:86vh; object-fit:contain; background:#fff; }
                .customer-timeline-preview .ct-lightbox-caption { margin-top:.5rem; color:#fff; font-size:.9rem; text-align:center; overflow-wrap:anywhere; }
                .customer-timeline-preview .ct-lightbox-close { position:absolute; top:-.7rem; right:-.7rem; width:2.25rem; height:2.25rem; border:0; border-radius:50%; background:#fff; color:#173b50; font-size:1.35rem; font-weight:700; cursor:pointer; }
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
                    <p class="ct-intro">Each submission, approval and rejection is a separate dated event with its own status. Open a message snapshot to read the full saved text, or select a proof thumbnail to enlarge it. Older submissions may not have a sent-message snapshot if it was not captured at the time.</p>
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
            <?php echo customerTimelinePreviewRenderLightboxMarkup(); ?>
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
