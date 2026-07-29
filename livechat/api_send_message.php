<?php
header('Content-Type: application/json');

session_start();
include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/livechat_common.php';

if (!isset($_SESSION['usr_id']) || empty($_SESSION['usr_id'])) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

$senderId = (int)$_SESSION['usr_id'];
$recipientId = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
$message = isset($_POST['message']) ? (string)$_POST['message'] : '';

if ($recipientId <= 0) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Invalid recipient'));
    exit;
}

if ($senderId === $recipientId) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'error' => 'Cannot send message to yourself'));
    exit;
}

$result = livechatSendMessage($connect, $senderId, $recipientId, $message);

if ($result['success']) {
    $messageId = $result['message_id'];

    // Handle file uploads
    if (isset($_FILES['files'])) {
        $files = $_FILES['files'];
        $uploadDir = __DIR__ . '/uploads/' . date('Y/m/d');

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!is_array($files['name'])) {
            $files = array(
                'name' => array($files['name']),
                'type' => array($files['type']),
                'tmp_name' => array($files['tmp_name']),
                'error' => array($files['error']),
                'size' => array($files['size'])
            );
        }

        $attachments = array();
        for ($i = 0; $i < count($files['name']); $i++) {
            $file = array(
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            );

            $validation = livechatValidateFile($file, 5242880); // 5MB
            if (!$validation['valid']) {
                continue;
            }

            $fileName = $files['name'][$i];
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = uniqid('msg_', true) . '.' . $fileExt;
            $filePath = $uploadDir . '/' . $newFileName;
            $relativePathForDb = 'livechat/uploads/' . date('Y/m/d') . '/' . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $fileName = mysqli_real_escape_string($connect, $fileName);
                $relativePathForDb = mysqli_real_escape_string($connect, $relativePathForDb);
                $fileType = mysqli_real_escape_string($connect, $file['type']);
                $fileSize = (int)$file['size'];

                $query = "INSERT INTO livechat_attachments (message_id, file_name, file_path, file_size, file_type)
                          VALUES ($messageId, '$fileName', '$relativePathForDb', $fileSize, '$fileType')";

                if (mysqli_query($connect, $query)) {
                    $attachmentId = mysqli_insert_id($connect);
                    $attachments[] = array(
                        'id' => $attachmentId,
                        'file_name' => $files['name'][$i],
                        'file_path' => $relativePathForDb,
                        'file_size' => $fileSize,
                        'file_type' => $file['type']
                    );
                }
            }
        }
    }

    http_response_code(200);
    echo json_encode(array(
        'success' => true,
        'message_id' => $messageId,
        'attachments' => isset($attachments) ? $attachments : array()
    ));
} else {
    http_response_code(400);
    echo json_encode($result);
}
?>
