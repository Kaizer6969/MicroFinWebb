<?php
/**
 * Admin Chat Interface
 * View and reply to client messages
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure only Admin/Super Admin/Employee can access
if ($_SESSION['user_type'] === 'Client') {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Customer Support Chat - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        .chat-container {
            height: calc(100vh - 180px);
            min-height: 500px;
            background-color: var(--color-surface-light);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border-subtle);
            overflow: hidden;
            display: flex;
        }

        .chat-list {
            width: 300px;
            border-right: 1px solid var(--color-border-subtle);
            display: flex;
            flex-direction: column;
            background-color: var(--color-surface-light-alt);
        }

        .chat-list-header {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
        }

        .conversation-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .conversation-item:hover, .conversation-item.active {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .conversation-item.active {
            border-left: 4px solid var(--color-primary);
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--color-surface-light);
        }

        .chat-area-header {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .messages-container {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            background-color: rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .dark .messages-container {
            background-color: rgba(255,255,255,0.02);
        }

        .message {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.95rem;
            position: relative;
        }

        .message.sent {
            align-self: flex-end;
            background-color: var(--color-primary);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }

        .message.received {
            align-self: flex-start;
            background-color: var(--color-surface-light-alt);
            color: var(--color-text-main);
            border-bottom-left-radius: 0.25rem;
            border: 1px solid var(--color-border-subtle);
        }

        .dark .message.received {
            background-color: var(--color-surface-dark-alt);
            color: var(--color-text-dark);
            border-color: var(--color-border-dark);
        }

        .message-box {
            padding: 1rem;
            border-top: 1px solid var(--color-border-subtle);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'admin_sidebar.php'; ?>
        
        <main class="main-content w-100 bg-body-tertiary min-vh-100">
            <?php include 'admin_header.php'; ?>
            
            <div class="content-area">
                <div class="container-fluid p-0">
                    <h1 class="h3 fw-bold text-main mb-4">Customer Support Chat</h1>
                    
                    <div class="chat-container">
                        <!-- Left: Conversation List -->
                        <div class="chat-list">
                            <div class="chat-list-header">
                                <h6 class="fw-bold mb-0">Conversations</h6>
                            </div>
                            <div class="conversation-list" id="conversationList">
                                <!-- Loaded via JS -->
                                <div class="text-center p-4 text-muted small">Loading...</div>
                            </div>
                        </div>
                        
                        <!-- Right: Chat Area -->
                        <div class="chat-area" id="chatArea">
                            <div class="chat-area-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px">
                                        <span class="material-symbols-outlined">person</span>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0" id="chatUserName">Select a conversation</h6>
                                        <small class="text-muted" id="chatUserStatus">...</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="messages-container" id="messagesContainer">
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                                    <span class="material-symbols-outlined fs-1 mb-2">chat</span>
                                    <p>Select a conversation to start chatting</p>
                                </div>
                            </div>
                            
                            <div class="message-box">
                                <form id="adminChatForm" class="d-flex gap-2">
                                    <input type="hidden" id="currentChatUserId" value="">
                                    <input type="text" class="form-control" id="adminChatInput" placeholder="Type your reply..." disabled autocomplete="off">
                                    <button type="submit" class="btn btn-primary" id="adminSendBtn" disabled>
                                        <span class="material-symbols-outlined">send</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const conversationList = document.getElementById('conversationList');
        const messagesContainer = document.getElementById('messagesContainer');
        const chatUserName = document.getElementById('chatUserName');
        const chatUserStatus = document.getElementById('chatUserStatus');
        const currentChatUserId = document.getElementById('currentChatUserId');
        const adminChatInput = document.getElementById('adminChatInput');
        const adminSendBtn = document.getElementById('adminSendBtn');
        const adminChatForm = document.getElementById('adminChatForm');
        
        let activeChatId = null;

        // Load conversations list
        async function loadConversations() {
            try {
                const response = await fetch('chat_handler.php?action=get_conversations');
                const data = await response.json();
                
                if (data.success) {
                    conversationList.innerHTML = '';
                    if (data.conversations.length === 0) {
                        conversationList.innerHTML = '<div class="text-center p-4 text-muted small">No active conversations</div>';
                        return;
                    }
                    
                    data.conversations.forEach(conv => {
                        const isActive = activeChatId == conv.user_id ? 'active' : '';
                        const unreadBadge = conv.unread > 0 ? `<span class="badge bg-danger rounded-pill ms-auto">${conv.unread}</span>` : '';
                        
                        const div = document.createElement('div');
                        div.className = `conversation-item ${isActive}`;
                        div.innerHTML = `
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-secondary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;color:var(--color-text-main)">
                                    ${conv.username.substring(0,1).toUpperCase()}
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fw-bold text-truncate">${conv.username}</span>
                                        <small class="text-muted" style="font-size:0.7em">${conv.last_active}</small>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="text-muted small text-truncate d-block" style="max-width:140px">${conv.user_type}</span>
                                        ${unreadBadge}
                                    </div>
                                </div>
                            </div>
                        `;
                        div.onclick = () => selectChat(conv.user_id, conv.username);
                        conversationList.appendChild(div);
                    });
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }

        // Select a chat
        function selectChat(userId, username) {
            activeChatId = userId;
            currentChatUserId.value = userId;
            chatUserName.textContent = username;
            chatUserStatus.textContent = 'Active now';
            
            // Enable inputs
            adminChatInput.disabled = false;
            adminSendBtn.disabled = false;
            adminChatInput.focus();
            
            // Reload list to update active state
            loadConversations();
            
            // Load messages
            loadMessages(userId);
        }

        // Load messages for specific user
        async function loadMessages(userId) {
            try {
                const response = await fetch(`chat_handler.php?action=get_messages&user_id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    messagesContainer.innerHTML = '';
                    
                    data.messages.forEach(msg => {
                        // 'sent' in admin view means sent BY admin (me)
                        // 'received' means sent BY client (them)
                        const msgClass = msg.type === 'sent' ? 'sent' : 'received';
                        
                        const div = document.createElement('div');
                        div.className = `message ${msgClass}`;
                        div.innerHTML = `
                            ${msg.message}
                            <span class="d-block text-end opacity-50 mt-1" style="font-size:0.7em">${msg.time}</span>
                        `;
                        messagesContainer.appendChild(div);
                    });
                    
                    scrollToBottom();
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Send message
        async function sendMessage(text) {
            if (!activeChatId) return;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', activeChatId);
            formData.append('message', text);

            try {
                const response = await fetch('chat_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    adminChatInput.value = '';
                    loadMessages(activeChatId); // Reload to show new message
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        }

        adminChatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const text = adminChatInput.value.trim();
            if (text) sendMessage(text);
        });

        // Initialize
        loadConversations();
        
        // Polling
        setInterval(() => {
            loadConversations();
            if (activeChatId) {
                loadMessages(activeChatId);
            }
        }, 3000); // 3 seconds polling

    </script>
</body>
</html>
