<?php
ob_start();

$pageTitle = "Live Chat";
$currentPagePin = 999; // You can adjust this based on your permission system

include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/livechat_common.php';

if (!isset($_SESSION['usr_id']) || empty($_SESSION['usr_id'])) {
    echo "<script>location.href='" . $SITEURL . "/login.php';</script>";
    exit;
}

$currentUserId = (int)$_SESSION['usr_id'];
$currentUserName = isset($_SESSION['usr_name']) ? $_SESSION['usr_name'] : 'User';

// Initialize user status if not exists
livechatInitUserStatus($connect, $currentUserId);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        #livechat-container {
            display: flex;
            height: calc(100vh - 200px);
            gap: 0;
            background: #f5f5f5;
        }

        #user-list-panel {
            width: 300px;
            background: white;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-list-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background: #fafafa;
        }

        .user-list-header h3 {
            margin: 0;
            font-size: 16px;
        }

        #users-container {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .user-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }

        .user-item:hover {
            background-color: #f9f9f9;
        }

        .user-item.active {
            background-color: #e3f2fd;
            border-left: 3px solid #2196F3;
        }

        .user-item-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 500;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-status {
            font-size: 12px;
            color: #999;
            margin-top: 3px;
        }

        .online-badge {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #ccc;
            margin-right: 8px;
            flex-shrink: 0;
        }

        .online-badge.active {
            background-color: #4CAF50;
        }

        .unread-badge {
            background-color: #f44336;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }

        #chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background: #fafafa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header h2 {
            margin: 0;
            font-size: 16px;
        }

        #messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: flex;
            flex-direction: column;
        }

        .message-group {
            margin-bottom: 15px;
            display: flex;
            gap: 8px;
        }

        .message-group.sent {
            flex-direction: row-reverse;
        }

        .message-content {
            max-width: 70%;
        }

        .message-group.sent .message-content {
            align-items: flex-end;
        }

        .message-bubble {
            padding: 10px 14px;
            border-radius: 10px;
            word-wrap: break-word;
            font-size: 14px;
        }

        .message-group.received .message-bubble {
            background-color: #e0e0e0;
            color: #333;
        }

        .message-group.sent .message-bubble {
            background-color: #2196F3;
            color: white;
        }

        .message-time {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .message-attachments {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .message-attachment {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            cursor: pointer;
        }

        .chat-input-area {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            background: white;
        }

        .input-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        #message-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: none;
            min-height: 40px;
            max-height: 100px;
            font-family: inherit;
        }

        #message-input:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.1);
        }

        .button-group {
            display: flex;
            gap: 5px;
        }

        .btn-send, .btn-attach {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }

        .btn-send {
            background-color: #2196F3;
            color: white;
        }

        .btn-send:hover {
            background-color: #1976D2;
        }

        .btn-attach {
            background-color: #f0f0f0;
            color: #333;
        }

        .btn-attach:hover {
            background-color: #e0e0e0;
        }

        #file-input {
            display: none;
        }

        .file-preview {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .file-item {
            display: flex;
            align-items: center;
            background: #f0f0f0;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            gap: 8px;
        }

        .file-remove {
            cursor: pointer;
            color: #f44336;
            font-weight: bold;
        }

        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            text-align: center;
        }

        .loading {
            text-align: center;
            color: #999;
            padding: 20px;
        }

        @media (max-width: 768px) {
            #user-list-panel {
                display: none;
            }

            .message-content {
                max-width: 90%;
            }
        }
    </style>
</head>

<body>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= $pageTitle ?>
            </p>
        </div>

        <div id="livechat-container" class="container-fluid p-0">
            <!-- User List Panel -->
            <div id="user-list-panel">
                <div class="user-list-header">
                    <h3>Conversations</h3>
                </div>
                <div id="users-container"></div>
            </div>

            <!-- Chat Panel -->
            <div id="chat-panel" style="display: none;">
                <div class="chat-header">
                    <div class="chat-header-user-info">
                        <div class="online-badge" id="selected-user-status"></div>
                        <div>
                            <h2 id="selected-user-name">-</h2>
                            <div style="font-size: 12px; color: #999;" id="selected-user-info">-</div>
                        </div>
                    </div>
                </div>

                <div id="messages-container"></div>

                <div class="chat-input-area">
                    <div class="file-preview" id="file-preview"></div>
                    <div class="input-row">
                        <textarea id="message-input" placeholder="Type your message..."></textarea>
                        <div class="button-group">
                            <button class="btn-attach" id="attach-btn" title="Attach photos (max 5 files, 5MB each)">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button class="btn-send" id="send-btn">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Selection -->
            <div id="no-chat" class="no-conversation" style="flex: 1;">
                <div>
                    <p>Select a conversation to start chatting</p>
                </div>
            </div>
        </div>
    </div>

    <input type="file" id="file-input" accept="image/*" multiple>

    <script>
        const SITE_URL = '<?= $SITEURL ?>';
        const CURRENT_USER_ID = <?= $currentUserId ?>;
        const CURRENT_USER_NAME = '<?= htmlspecialchars($currentUserName, ENT_QUOTES) ?>';

        let selectedUserId = null;
        let eventSource = null;
        let selectedFiles = [];
        let messageCache = {};

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadUserList();
            setupEventListeners();
            setInterval(loadUserList, 30000); // Refresh user list every 30s
        });

        function setupEventListeners() {
            document.getElementById('message-input').addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            document.getElementById('send-btn').addEventListener('click', sendMessage);
            document.getElementById('attach-btn').addEventListener('click', () => {
                document.getElementById('file-input').click();
            });

            document.getElementById('file-input').addEventListener('change', handleFileSelect);
        }

        function loadUserList() {
            fetch(SITE_URL + '/livechat/api_user_list.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderUserList(data.users);
                    }
                })
                .catch(err => console.error('Error loading user list:', err));
        }

        function renderUserList(users) {
            const container = document.getElementById('users-container');
            container.innerHTML = '';

            if (users.length === 0) {
                container.innerHTML = '<div class="loading">No other users available</div>';
                return;
            }

            users.forEach(user => {
                const userEl = document.createElement('div');
                userEl.className = 'user-item' + (user.id === selectedUserId ? ' active' : '');
                userEl.onclick = () => selectUser(user);

                let statusText = user.is_online ? 'Online' : 'Offline';
                if (user.last_seen) {
                    const lastSeenDate = new Date(user.last_seen);
                    const diffMinutes = Math.floor((Date.now() - lastSeenDate) / 60000);
                    if (diffMinutes < 1) statusText = 'Just now';
                    else if (diffMinutes < 60) statusText = `${diffMinutes}m ago`;
                }

                let unreadHtml = '';
                if (user.unread_count > 0) {
                    unreadHtml = `<span class="unread-badge">${user.unread_count}</span>`;
                }

                userEl.innerHTML = `
                    <div class="user-item-content">
                        <div style="display: flex; align-items: center; flex: 1; min-width: 0;">
                            <div class="online-badge${user.is_online ? ' active' : ''}"></div>
                            <div class="user-info">
                                <div class="user-name">${user.name}</div>
                                <div class="user-status">${statusText}</div>
                            </div>
                        </div>
                        ${unreadHtml}
                    </div>
                `;

                container.appendChild(userEl);
            });
        }

        function selectUser(user) {
            selectedUserId = user.id;
            selectedFiles = [];
            document.getElementById('file-preview').innerHTML = '';
            document.getElementById('message-input').value = '';

            // Update UI
            document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Show chat panel
            document.getElementById('no-chat').style.display = 'none';
            document.getElementById('chat-panel').style.display = 'flex';

            // Update header
            document.getElementById('selected-user-name').textContent = user.name;
            document.getElementById('selected-user-status').className = 'online-badge' + (user.is_online ? ' active' : '');
            document.getElementById('selected-user-info').textContent = user.is_online ? 'Online' : 'Offline';

            // Load messages
            loadMessages();

            // Start SSE stream
            startMessageStream();
        }

        function loadMessages() {
            if (!selectedUserId) return;

            const url = SITE_URL + '/livechat/api_get_messages.php?user_id=' + selectedUserId + '&limit=100';
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderMessages(data.messages);
                    }
                })
                .catch(err => console.error('Error loading messages:', err));
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            container.innerHTML = '';

            messages.forEach(msg => {
                const msgEl = document.createElement('div');
                msgEl.className = 'message-group ' + (msg.sender_id === CURRENT_USER_ID ? 'sent' : 'received');

                const time = new Date(msg.created_at);
                const timeStr = time.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

                let attachmentsHtml = '';
                if (msg.attachments && msg.attachments.length > 0) {
                    attachmentsHtml = '<div class="message-attachments">';
                    msg.attachments.forEach(att => {
                        if (att.file_type && att.file_type.startsWith('image/')) {
                            attachmentsHtml += `<img src="${SITE_URL}/${att.file_path}" alt="${att.file_name}" class="message-attachment" onclick="openImage('${SITE_URL}/${att.file_path}')">`;
                        }
                    });
                    attachmentsHtml += '</div>';
                }

                msgEl.innerHTML = `
                    <div class="message-content">
                        ${msg.message ? `<div class="message-bubble">${escapeHtml(msg.message)}</div>` : ''}
                        ${attachmentsHtml}
                        <div class="message-time">${timeStr}</div>
                    </div>
                `;

                container.appendChild(msgEl);
            });

            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        function startMessageStream() {
            if (eventSource) {
                eventSource.close();
            }

            if (!selectedUserId) return;

            const url = SITE_URL + '/livechat/api_stream_messages.php?user_id=' + selectedUserId;
            eventSource = new EventSource(url);

            eventSource.addEventListener('message', (event) => {
                const data = JSON.parse(event.data);
                const container = document.getElementById('messages-container');

                const msgEl = document.createElement('div');
                msgEl.className = 'message-group ' + (data.sender_id === CURRENT_USER_ID ? 'sent' : 'received');

                const time = new Date(data.created_at);
                const timeStr = time.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

                let attachmentsHtml = '';
                if (data.attachments && data.attachments.length > 0) {
                    attachmentsHtml = '<div class="message-attachments">';
                    data.attachments.forEach(att => {
                        if (att.file_type && att.file_type.startsWith('image/')) {
                            attachmentsHtml += `<img src="${SITE_URL}/${att.file_path}" alt="${att.file_name}" class="message-attachment" onclick="openImage('${SITE_URL}/${att.file_path}')">`;
                        }
                    });
                    attachmentsHtml += '</div>';
                }

                msgEl.innerHTML = `
                    <div class="message-content">
                        ${data.message ? `<div class="message-bubble">${escapeHtml(data.message)}</div>` : ''}
                        ${attachmentsHtml}
                        <div class="message-time">${timeStr}</div>
                    </div>
                `;

                container.appendChild(msgEl);
                container.scrollTop = container.scrollHeight;
            });

            eventSource.addEventListener('error', () => {
                console.warn('SSE connection closed');
                eventSource.close();
            });
        }

        function handleFileSelect(e) {
            const files = Array.from(e.target.files);
            const maxSize = 5 * 1024 * 1024; // 5MB
            const maxFiles = 5;

            if (selectedFiles.length + files.length > maxFiles) {
                alert('Maximum ' + maxFiles + ' files allowed');
                return;
            }

            files.forEach(file => {
                if (file.size > maxSize) {
                    alert(file.name + ' exceeds 5MB limit');
                    return;
                }
                selectedFiles.push(file);
            });

            updateFilePreview();
            e.target.value = '';
        }

        function updateFilePreview() {
            const preview = document.getElementById('file-preview');
            preview.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const el = document.createElement('div');
                el.className = 'file-item';
                el.innerHTML = `
                    <span>${file.name}</span>
                    <span class="file-remove" onclick="removeFile(${index})">×</span>
                `;
                preview.appendChild(el);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilePreview();
        }

        function sendMessage() {
            if (!selectedUserId) {
                alert('Please select a user first');
                return;
            }

            const message = document.getElementById('message-input').value.trim();

            if (!message && selectedFiles.length === 0) {
                alert('Please enter a message or attach files');
                return;
            }

            const formData = new FormData();
            formData.append('recipient_id', selectedUserId);
            formData.append('message', message);

            selectedFiles.forEach(file => {
                formData.append('files[]', file);
            });

            fetch(SITE_URL + '/livechat/api_send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('message-input').value = '';
                    selectedFiles = [];
                    updateFilePreview();
                    // Messages will be received via SSE
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => {
                console.error('Error sending message:', err);
                alert('Failed to send message');
            });
        }

        function openImage(url) {
            window.open(url, '_blank');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Update user status when page is closed
        window.addEventListener('beforeunload', () => {
            if (eventSource) {
                eventSource.close();
            }
        });
    </script>
</body>
</html>
