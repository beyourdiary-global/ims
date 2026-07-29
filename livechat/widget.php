<?php
$pageTitle = "Live Chat Widget";
include_once __DIR__ . '/../menuHeader.php';
include_once __DIR__ . '/livechat_common.php';

if (!isset($_SESSION['usr_id']) || empty($_SESSION['usr_id'])) {
    http_response_code(401);
    exit;
}

$currentUserId = (int)$_SESSION['usr_id'];
$currentUserName = isset($_SESSION['usr_name']) ? $_SESSION['usr_name'] : 'User';
livechatInitUserStatus($connect, $currentUserId);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        #livechat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            height: 600px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
            display: flex;
            flex-direction: column;
            z-index: 9999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        #livechat-widget.minimized {
            height: 60px;
            width: 60px;
        }

        .livechat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            cursor: pointer;
        }

        .livechat-header-title {
            font-weight: 600;
            font-size: 16px;
            margin: 0;
        }

        .livechat-header-subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin: 4px 0 0 0;
        }

        .livechat-header-actions {
            display: flex;
            gap: 8px;
        }

        .livechat-header-btn {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 14px;
            transition: background 0.2s;
        }

        .livechat-header-btn:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        #livechat-widget.minimized .livechat-body,
        #livechat-widget.minimized .livechat-footer {
            display: none;
        }

        .livechat-body {
            flex: 1;
            display: flex;
            gap: 0;
            overflow: hidden;
            background: #f5f5f5;
        }

        .livechat-user-list {
            width: 120px;
            background: white;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            padding: 8px 0;
        }

        .livechat-user-item {
            padding: 10px 8px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            text-align: center;
            font-size: 12px;
        }

        .livechat-user-item:hover {
            background-color: #f9f9f9;
        }

        .livechat-user-item.active {
            background-color: #e3f2fd;
            border-left: 3px solid #2196F3;
        }

        .livechat-user-avatar {
            width: 32px;
            height: 32px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            margin: 0 auto 4px;
        }

        .livechat-user-badge {
            display: inline-block;
            background: #4CAF50;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            position: relative;
            top: -8px;
            right: -12px;
        }

        .livechat-user-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            margin-top: 4px;
        }

        .livechat-chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .livechat-no-chat {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            text-align: center;
            padding: 20px;
        }

        .livechat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .livechat-message-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 8px;
        }

        .livechat-message {
            max-width: 85%;
            padding: 8px 12px;
            border-radius: 12px;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.4;
        }

        .livechat-message.sent {
            align-self: flex-end;
            background: #2196F3;
            color: white;
            border-bottom-right-radius: 4px;
        }

        .livechat-message.received {
            align-self: flex-start;
            background: #f0f0f0;
            color: #333;
            border-bottom-left-radius: 4px;
        }

        .livechat-message-time {
            font-size: 11px;
            color: #999;
            padding: 0 12px;
            text-align: right;
        }

        .livechat-message.received .livechat-message-time {
            text-align: left;
        }

        .livechat-footer {
            padding: 12px;
            border-top: 1px solid #e0e0e0;
            background: white;
            border-radius: 0 0 12px 12px;
            flex-shrink: 0;
        }

        .livechat-input-group {
            display: flex;
            gap: 8px;
        }

        .livechat-input-group textarea {
            flex: 1;
            padding: 8px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            resize: none;
            max-height: 80px;
            min-height: 36px;
        }

        .livechat-input-group textarea:focus {
            outline: none;
            border-color: #2196F3;
        }

        .livechat-send-btn {
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
            align-self: flex-end;
        }

        .livechat-send-btn:hover {
            background: #1976D2;
        }

        .livechat-send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        @media (max-width: 600px) {
            #livechat-widget {
                width: 100%;
                height: 100%;
                bottom: 0;
                right: 0;
                border-radius: 0;
                max-width: 100vw;
            }

            .livechat-user-list {
                display: none;
            }
        }

        .livechat-loading {
            text-align: center;
            color: #999;
            padding: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div id="livechat-widget" class="minimized">
    <div class="livechat-header">
        <div>
            <p class="livechat-header-title">💬 Chat</p>
            <p class="livechat-header-subtitle" id="status-subtitle">Connect to start</p>
        </div>
        <div class="livechat-header-actions">
            <button class="livechat-header-btn" id="minimize-btn" title="Minimize">
                <i class="fas fa-minus"></i>
            </button>
            <button class="livechat-header-btn" id="close-btn" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <div class="livechat-body">
        <div class="livechat-user-list" id="users-container">
            <div class="livechat-loading">Loading...</div>
        </div>

        <div class="livechat-chat-area">
            <div class="livechat-messages" id="messages-container">
                <div class="livechat-no-chat">Select a user to start chatting</div>
            </div>
        </div>
    </div>

    <div class="livechat-footer">
        <div class="livechat-input-group">
            <textarea id="message-input" placeholder="Type a message..." rows="2" disabled></textarea>
            <button class="livechat-send-btn" id="send-btn" disabled>Send</button>
        </div>
    </div>
</div>

<script>
const currentUserId = <?= $currentUserId ?>;
const currentUserName = '<?= addslashes($currentUserName) ?>';
const siteUrl = '<?= $SITEURL ?>';

let selectedUserId = null;
let eventSource = null;
let userList = [];
let messageCache = {};

const widget = document.getElementById('livechat-widget');
const minimizeBtn = document.getElementById('minimize-btn');
const closeBtn = document.getElementById('close-btn');
const usersContainer = document.getElementById('users-container');
const messagesContainer = document.getElementById('messages-container');
const messageInput = document.getElementById('message-input');
const sendBtn = document.getElementById('send-btn');
const header = document.querySelector('.livechat-header');
const statusSubtitle = document.getElementById('status-subtitle');

header.addEventListener('click', toggleMinimize);
minimizeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    toggleMinimize();
});
closeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    widget.style.display = 'none';
});
sendBtn.addEventListener('click', sendMessage);
messageInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

function toggleMinimize() {
    widget.classList.toggle('minimized');
}

function loadUsers() {
    fetch(`${siteUrl}/livechat/api_user_list.php`)
        .then(r => r.json())
        .then(users => {
            userList = users || [];
            renderUserList();
            loadUserStatusPeriodically();
        })
        .catch(err => console.error('Failed to load users:', err));
}

function loadUserStatusPeriodically() {
    setInterval(loadUsers, 30000);
}

function renderUserList() {
    usersContainer.innerHTML = userList.map(user => `
        <div class="livechat-user-item ${user.id === selectedUserId ? 'active' : ''}" data-user-id="${user.id}">
            <div class="livechat-user-avatar">
                ${user.name.charAt(0).toUpperCase()}
                ${user.is_online ? '<span class="livechat-user-badge"></span>' : ''}
            </div>
            <span class="livechat-user-name">${escapeHtml(user.name)}</span>
        </div>
    `).join('');

    usersContainer.querySelectorAll('.livechat-user-item').forEach(item => {
        item.addEventListener('click', () => {
            selectUser(parseInt(item.dataset.userId));
        });
    });
}

function selectUser(userId) {
    selectedUserId = userId;
    messageInput.disabled = false;
    sendBtn.disabled = false;

    const userItem = usersContainer.querySelector(`[data-user-id="${userId}"]`);
    usersContainer.querySelectorAll('.livechat-user-item').forEach(item => item.classList.remove('active'));
    userItem.classList.add('active');

    const user = userList.find(u => u.id === userId);
    statusSubtitle.textContent = user.is_online ? '🟢 Online' : '🔴 Offline';

    messagesContainer.innerHTML = '<div class="livechat-loading">Loading messages...</div>';
    messageCache[userId] = [];

    loadMessages();
    connectSSE();
}

function loadMessages() {
    if (!selectedUserId) return;

    fetch(`${siteUrl}/livechat/api_get_messages.php?user_id=${selectedUserId}&limit=50`)
        .then(r => r.json())
        .then(messages => {
            messageCache[selectedUserId] = messages || [];
            renderMessages();
            scrollToBottom();
        })
        .catch(err => console.error('Failed to load messages:', err));
}

function renderMessages() {
    const messages = messageCache[selectedUserId] || [];

    if (messages.length === 0) {
        messagesContainer.innerHTML = '<div class="livechat-no-chat">No messages yet. Start a conversation!</div>';
        return;
    }

    const html = messages.map(msg => {
        const isSent = msg.sender_id === currentUserId;
        const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return `
            <div class="livechat-message-group">
                <div class="livechat-message ${isSent ? 'sent' : 'received'}">
                    ${escapeHtml(msg.message)}
                </div>
                <div class="livechat-message-time">${time}</div>
            </div>
        `;
    }).join('');

    messagesContainer.innerHTML = html;
    scrollToBottom();
}

function connectSSE() {
    if (eventSource) eventSource.close();

    eventSource = new EventSource(`${siteUrl}/livechat/api_stream_messages.php?user_id=${selectedUserId}`);

    eventSource.addEventListener('message', (e) => {
        const msg = JSON.parse(e.data);
        if (msg.sender_id === selectedUserId || msg.recipient_id === currentUserId) {
            messageCache[selectedUserId].push(msg);
            renderMessages();
            scrollToBottom();
        }
    });

    eventSource.onerror = () => {
        eventSource.close();
    };
}

function sendMessage() {
    if (!selectedUserId || !messageInput.value.trim()) return;

    const message = messageInput.value.trim();
    messageInput.value = '';
    sendBtn.disabled = true;

    const formData = new FormData();
    formData.append('recipient_id', selectedUserId);
    formData.append('message', message);

    fetch(`${siteUrl}/livechat/api_send_message.php`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(() => {
        loadMessages();
        sendBtn.disabled = false;
    })
    .catch(err => {
        console.error('Failed to send message:', err);
        messageInput.value = message;
        sendBtn.disabled = false;
    });
}

function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

loadUsers();
</script>

</body>
</html>
