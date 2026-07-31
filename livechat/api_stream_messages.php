<?php
// Server-Sent Events endpoint for real-time message streaming

session_start();

$userId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : (isset($_SESSION['usr_id']) ? (int)$_SESSION['usr_id'] : 0);

if ($userId <= 0) {
    http_response_code(401);
    exit;
}

// Direct database connection (avoid init.php session issues)
$connect = @mysqli_connect('127.0.0.1:3306', 'beyourdi_cms', 'Byd1234@Global', 'beyourdi_cms-uat');
if (!$connect) {
    http_response_code(500);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

include_once __DIR__ . '/livechat_common.php';
$conversationWith = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($conversationWith <= 0) {
    http_response_code(400);
    exit;
}

// Set headers for SSE
header('Cache-Control: no-cache');
header('Content-Type: text/event-stream');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Disable output buffering
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Function to send SSE message
function sendSSEMessage($eventType, $data) {
    echo "event: " . htmlspecialchars($eventType) . "\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Track last message ID to avoid duplicate
$lastMessageId = 0;
$inactivityCounter = 0;
$maxInactivity = 300; // 5 minutes in 1-second intervals

// Update user status to online
livechatUpdateUserStatus($connect, $userId, 1);

// Initial connection message
sendSSEMessage('connected', array('status' => 'connected', 'user_id' => $userId));

// Main polling loop
while (true) {
    // Check for new messages
    $query = "
        SELECT id, sender_id, recipient_id, message, created_at, is_read
        FROM livechat_messages
        WHERE id > $lastMessageId
        AND (
            (sender_id = $userId AND recipient_id = $conversationWith)
            OR (sender_id = $conversationWith AND recipient_id = $userId)
        )
        ORDER BY id ASC
        LIMIT 100
    ";

    $result = mysqli_query($connect, $query);
    $hasNewMessages = false;

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $messageId = (int)$row['id'];
            $lastMessageId = max($lastMessageId, $messageId);

            // Get attachments for this message
            $attachQuery = "
                SELECT id, file_name, file_path, file_size, file_type
                FROM livechat_attachments
                WHERE message_id = $messageId
            ";
            $attachResult = mysqli_query($connect, $attachQuery);
            $attachments = array();
            while ($attachRow = $attachResult->fetch_assoc()) {
                $attachments[] = array(
                    'id' => (int)$attachRow['id'],
                    'file_name' => $attachRow['file_name'],
                    'file_path' => $attachRow['file_path'],
                    'file_size' => (int)$attachRow['file_size'],
                    'file_type' => $attachRow['file_type']
                );
            }

            sendSSEMessage('message', array(
                'id' => $messageId,
                'sender_id' => (int)$row['sender_id'],
                'recipient_id' => (int)$row['recipient_id'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'is_read' => (int)$row['is_read'],
                'attachments' => $attachments
            ));

            $hasNewMessages = true;
            $inactivityCounter = 0;

            // Mark as read if this message is for current user
            if ((int)$row['recipient_id'] === $userId) {
                mysqli_query($connect, "UPDATE livechat_messages SET is_read = 1 WHERE id = $messageId");
            }
        }
    }

    // Send heartbeat to keep connection alive
    if (!$hasNewMessages) {
        sendSSEMessage('heartbeat', array('timestamp' => time()));
        $inactivityCounter++;

        // Close connection if inactive
        if ($inactivityCounter >= $maxInactivity) {
            break;
        }
    }

    // Sleep for 1 second before next check
    sleep(1);

    // Check if client is still connected
    if (connection_status() !== CONNECTION_NORMAL) {
        break;
    }
}

// Update user status to offline
livechatUpdateUserStatus($connect, $userId, 0);
exit;
?>
