<?php
// Live Chat Common Functions

if (!function_exists('livechatInitUserStatus')) {
    function livechatInitUserStatus($connect, $userId) {
        $userId = (int)$userId;
        $result = mysqli_query($connect, "SELECT user_id FROM livechat_user_status WHERE user_id = $userId LIMIT 1");
        if (!$result || $result->num_rows === 0) {
            // Use REPLACE INTO to ensure record exists with fresh timestamp
            $insertResult = mysqli_query($connect, "REPLACE INTO livechat_user_status (user_id, is_online, last_seen, last_activity) VALUES ($userId, 0, NOW(), NOW())");
            if (!$insertResult) {
                error_log("[LiveChat] Failed to initialize user $userId: " . mysqli_error($connect));
            }
        }
    }
}

if (!function_exists('livechatUpdateUserStatus')) {
    function livechatUpdateUserStatus($connect, $userId, $isOnline) {
        $userId = (int)$userId;
        $isOnline = (int)$isOnline;
        return mysqli_query($connect, "UPDATE livechat_user_status SET is_online = $isOnline, last_activity = NOW() WHERE user_id = $userId");
    }
}

if (!function_exists('livechatGetUserStatus')) {
    function livechatGetUserStatus($connect, $userIds = array()) {
        if (empty($userIds)) {
            return array();
        }
        $userIdList = implode(',', array_map('intval', $userIds));
        $result = mysqli_query($connect, "
            SELECT user_id, is_online, last_seen, last_activity
            FROM livechat_user_status
            WHERE user_id IN ($userIdList)
        ");
        $statusMap = array();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $statusMap[(int)$row['user_id']] = array(
                    'is_online' => (int)$row['is_online'],
                    'last_seen' => $row['last_seen'],
                    'last_activity' => $row['last_activity']
                );
            }
        }
        return $statusMap;
    }
}

if (!function_exists('livechatSendMessage')) {
    function livechatSendMessage($connect, $senderId, $recipientId, $message = '', $attachmentIds = array()) {
        $senderId = (int)$senderId;
        $recipientId = (int)$recipientId;
        $message = trim((string)$message);

        if (empty($message) && empty($attachmentIds)) {
            return array('success' => false, 'error' => 'Message and attachments cannot both be empty');
        }

        $messageEscaped = mysqli_real_escape_string($connect, $message);
        $query = "INSERT INTO livechat_messages (sender_id, recipient_id, message) VALUES ($senderId, $recipientId, '$messageEscaped')";

        if (mysqli_query($connect, $query)) {
            $messageId = mysqli_insert_id($connect);
            return array('success' => true, 'message_id' => $messageId);
        }

        return array('success' => false, 'error' => 'Failed to send message');
    }
}

if (!function_exists('livechatGetMessages')) {
    function livechatGetMessages($connect, $userId1, $userId2, $limit = 50, $offset = 0) {
        $userId1 = (int)$userId1;
        $userId2 = (int)$userId2;
        $limit = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $query = "
            SELECT m.id, m.sender_id, m.recipient_id, m.message, m.created_at, m.is_read,
                   GROUP_CONCAT(a.id) as attachment_ids,
                   GROUP_CONCAT(a.file_name) as file_names,
                   GROUP_CONCAT(a.file_path) as file_paths,
                   GROUP_CONCAT(a.file_size) as file_sizes,
                   GROUP_CONCAT(a.file_type) as file_types
            FROM livechat_messages m
            LEFT JOIN livechat_attachments a ON m.id = a.message_id
            WHERE (m.sender_id = $userId1 AND m.recipient_id = $userId2)
               OR (m.sender_id = $userId2 AND m.recipient_id = $userId1)
            GROUP BY m.id
            ORDER BY m.created_at DESC
            LIMIT $offset, $limit
        ";

        $result = mysqli_query($connect, $query);
        $messages = array();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $attachments = array();
                if (!empty($row['attachment_ids'])) {
                    $ids = explode(',', $row['attachment_ids']);
                    $names = explode(',', $row['file_names']);
                    $paths = explode(',', $row['file_paths']);
                    $sizes = explode(',', $row['file_sizes']);
                    $types = explode(',', $row['file_types']);

                    for ($i = 0; $i < count($ids); $i++) {
                        $attachments[] = array(
                            'id' => (int)$ids[$i],
                            'file_name' => $names[$i] ?? '',
                            'file_path' => $paths[$i] ?? '',
                            'file_size' => (int)($sizes[$i] ?? 0),
                            'file_type' => $types[$i] ?? ''
                        );
                    }
                }

                $messages[] = array(
                    'id' => (int)$row['id'],
                    'sender_id' => (int)$row['sender_id'],
                    'recipient_id' => (int)$row['recipient_id'],
                    'message' => $row['message'] ?? '',
                    'created_at' => $row['created_at'],
                    'is_read' => (int)$row['is_read'],
                    'attachments' => $attachments
                );
            }
        }

        return array_reverse($messages);
    }
}

if (!function_exists('livechatMarkAsRead')) {
    function livechatMarkAsRead($connect, $messageIds = array()) {
        if (empty($messageIds)) {
            return false;
        }
        $idList = implode(',', array_map('intval', $messageIds));
        return mysqli_query($connect, "UPDATE livechat_messages SET is_read = 1 WHERE id IN ($idList)");
    }
}

if (!function_exists('livechatGetUnreadCount')) {
    function livechatGetUnreadCount($connect, $userId) {
        $userId = (int)$userId;
        $result = mysqli_query($connect, "SELECT COUNT(*) as count FROM livechat_messages WHERE recipient_id = $userId AND is_read = 0");
        if ($result) {
            $row = $result->fetch_assoc();
            return (int)$row['count'];
        }
        return 0;
    }
}

if (!function_exists('livechatValidateFile')) {
    function livechatValidateFile($file, $maxSize = 5242880) { // 5MB default
        $maxSize = max(1, (int)$maxSize);

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return array('valid' => false, 'error' => 'File upload failed');
        }

        if ($file['size'] > $maxSize) {
            return array('valid' => false, 'error' => 'File size exceeds limit');
        }

        $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp');
        if (!in_array($file['type'], $allowedTypes)) {
            return array('valid' => false, 'error' => 'File type not allowed');
        }

        return array('valid' => true);
    }
}
?>
