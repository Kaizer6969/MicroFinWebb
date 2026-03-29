<?php
/**
 * Admin Help & Support Page - Fundline Web Application
 * Protected page requiring authentication
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['user_type'] !== 'Client') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role_name = $_SESSION['role_name'] ?? 'Employee';
$avatar_letter = strtoupper(substr($username, 0, 1));

// $conn->close(); // Removed to allow header to access DB
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Help & Support - Fundline</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Fundline Design System -->
    <link href="../assets/css/main_style.css" rel="stylesheet">
    
    <style>
        /* Mobile Sidebar Handling */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1025; }
            .sidebar-overlay.show { display: block; }
        }
        
        .help-section {
            background-color: var(--color-surface-light);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            border: 1px solid var(--color-border-subtle);
        }
        
        .dark .help-section {
            background-color: var(--color-surface-dark);
            border-color: var(--color-border-dark);
        }
        
        .section-icon {
            width: 3rem;
            height: 3rem;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--color-primary);
            margin-bottom: 1rem;
        }
        
        .guide-step {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .step-number {
            width: 2rem;
            height: 2rem;
            background-color: var(--color-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .contact-card {
            background-color: var(--color-surface-light-alt);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            height: 100%;
            transition: all var(--transition-fast);
        }
        
        .dark .contact-card {
            background-color: var(--color-surface-dark-alt);
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .accordion-button:not(.collapsed) {
            background-color: rgba(59, 130, 246, 0.05);
            color: var(--color-primary);
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: var(--color-primary);
        }
        
        .accordion-item {
            border-color: var(--color-border-subtle);
            border-radius: var(--radius-lg) !important;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }
        
        .dark .accordion-item {
            background-color: var(--color-surface-dark-alt);
            border-color: var(--color-border-dark);
        }
        
        .dark .accordion-button {
            background-color: var(--color-surface-dark-alt);
            color: var(--color-text-dark);
        }
        
        .dark .accordion-button:not(.collapsed) {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--color-primary);
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="d-flex">
        <?php include 'user_sidebar.php'; ?>
        
        <main class="main-content w-100 bg-body-tertiary min-vh-100">
            <?php include 'client_header.php'; ?>
            
            <div class="content-area">
                <div class="container-fluid p-0" style="max-width: 1000px; margin: 0 auto;">
                    
                    <div class="row g-4">
                        <!-- Getting Started -->
                        <div class="col-lg-6">
                            <div class="help-section h-100">
                                <div class="section-icon">
                                    <span class="material-symbols-outlined fs-3">rocket_launch</span>
                                </div>
                                <h3 class="h5 fw-bold text-main mb-4">Getting Started</h3>
                                
                                <div class="guide-step">
                                    <div class="step-number">1</div>
                                    <div>
                                        <h6 class="fw-bold text-main mb-1">Apply for a Loan</h6>
                                        <p class="text-muted small mb-0">Navigate to 'Apply Loan' and fill out the application form with your details.</p>
                                    </div>
                                </div>
                                
                                <div class="guide-step">
                                    <div class="step-number">2</div>
                                    <div>
                                        <h6 class="fw-bold text-main mb-1">Submit Documents</h6>
                                        <p class="text-muted small mb-0">Upload required ID and proof of income in your profile or during application.</p>
                                    </div>
                                </div>
                                
                                <div class="guide-step">
                                    <div class="step-number">3</div>
                                    <div>
                                        <h6 class="fw-bold text-main mb-1">Wait for Approval</h6>
                                        <p class="text-muted small mb-0">Your application will be reviewed. Check 'My Loans' for status updates.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Tips -->
                        <div class="col-lg-6">
                            <div class="help-section h-100">
                                <div class="section-icon">
                                    <span class="material-symbols-outlined fs-3">lightbulb</span>
                                </div>
                                <h3 class="h5 fw-bold text-main mb-4">Quick Tips</h3>
                                
                                <ul class="list-unstyled d-flex flex-column gap-3 mb-0">
                                    <li class="d-flex gap-3 align-items-center bg-body-secondary p-3 rounded-3">
                                        <span class="material-symbols-outlined text-warning">verified_user</span>
                                        <span class="text-main small">Keep your profile updated for faster processing.</span>
                                    </li>
                                    <li class="d-flex gap-3 align-items-center bg-body-secondary p-3 rounded-3">
                                        <span class="material-symbols-outlined text-primary">schedule</span>
                                        <span class="text-main small">Pay on or before the due date to maintain a good credit score.</span>
                                    </li>
                                    <li class="d-flex gap-3 align-items-center bg-body-secondary p-3 rounded-3">
                                        <span class="material-symbols-outlined text-success">security</span>
                                        <span class="text-main small">Never share your password or OTP with anyone.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- FAQ -->
                        <div class="col-12">
                            <div class="help-section">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="section-icon mb-0">
                                        <span class="material-symbols-outlined fs-3">help</span>
                                    </div>
                                    <h3 class="h5 fw-bold text-main mb-0">Frequently Asked Questions</h3>
                                </div>
                                
                                <div class="accordion accordion-flush" id="faqAccordion">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                                How do I calculate my monthly payments?
                                            </button>
                                        </h2>
                                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body text-muted">
                                                Your monthly payment is calculated based on the loan amount, interest rate, and term. You can see the estimated breakdown before submitting your application.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                                What documents are required?
                                            </button>
                                        </h2>
                                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body text-muted">
                                                Generally, you need a valid government ID (Passport, Driver's License, etc.), proof of billing, and proof of income (Payslips or Business Permit). Requirements may vary by loan type.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                                Can I pay my loan early?
                                            </button>
                                        </h2>
                                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body text-muted">
                                                Yes, you can settle your loan early. Please contact our support team or visit the branch to process an early settlement.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                                How do I change my password?
                                            </button>
                                        </h2>
                                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                            <div class="accordion-body text-muted">
                                                Go to your Profile settings, look for the Security section, and select 'Change Password'. You will need to enter your current password to confirm.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact -->
                        <div class="col-12">
                            <div class="help-section">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="section-icon mb-0">
                                        <span class="material-symbols-outlined fs-3">support_agent</span>
                                    </div>
                                    <h3 class="h5 fw-bold text-main mb-0">Contact Support</h3>
                                </div>
                                
                                <div class="row g-4">
                                    <div class="col-md-4">
                                        <div class="contact-card text-center">
                                            <span class="material-symbols-outlined fs-2 text-primary mb-3">phone_in_talk</span>
                                            <h6 class="fw-bold text-main">Phone Support</h6>
                                            <p class="text-muted small">Mon-Fri, 8AM-5PM</p>
                                            <a href="tel:+631234567890" class="text-primary fw-bold text-decoration-none">+63 123 456 7890</a>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="contact-card text-center">
                                            <span class="material-symbols-outlined fs-2 text-primary mb-3">mail</span>
                                            <h6 class="fw-bold text-main">Email Us</h6>
                                            <p class="text-muted small">We'll reply within 24 hours</p>
                                            <a href="mailto:support@fundline.com" class="text-primary fw-bold text-decoration-none">support@fundline.com</a>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="contact-card text-center">
                                            <span class="material-symbols-outlined fs-2 text-primary mb-3">location_on</span>
                                            <h6 class="fw-bold text-main">Visit Branch</h6>
                                            <p class="text-muted small mb-0">Marilao Branch, Bulacan</p>
                                            <p class="text-muted small">Open Mon-Sat</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>
    
    <!-- Chat Widget -->
    <div id="chatWidget" class="chat-widget">
        <div class="chat-header" id="chatHeader">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined">support_agent</span>
                <div class="d-flex flex-column lh-1">
                    <span class="fw-bold">Customer Service</span>
                    <span style="font-size: 0.7rem; opacity: 0.9; font-weight: normal;">Chatting with Admin Jana</span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" id="closeChat"></button>
        </div>
        <div class="chat-body" id="chatBody">
            <div class="text-center text-muted small my-3">
                Start a conversation with our support team.
            </div>
            
            <!-- Quick Questions (Visible initially) -->
            <div id="quickQuestions" class="d-flex flex-column gap-2 p-2">
                <button onclick="askBot('apply', 'How to apply for a loan?')" class="btn btn-outline-primary btn-sm text-start rounded-pill">How to apply for a loan?</button>
                <button onclick="askBot('requirements', 'What are the requirements?')" class="btn btn-outline-primary btn-sm text-start rounded-pill">What are the requirements?</button>
                <button onclick="askBot('status', 'Check application status')" class="btn btn-outline-primary btn-sm text-start rounded-pill">Check application status</button>
                <button onclick="askBot('payment', 'How do I pay my loan?')" class="btn btn-outline-primary btn-sm text-start rounded-pill">How do I pay my loan?</button>
                <button onclick="askBot('rates', 'What are the interest rates?')" class="btn btn-outline-primary btn-sm text-start rounded-pill">What are the interest rates?</button>
                <button onclick="askBot('location', 'Where is your office?')" class="btn btn-outline-primary btn-sm text-start rounded-pill">Where is your office?</button>
                <button onclick="askBot('agent', 'Talk to an Agent')" class="btn btn-outline-secondary btn-sm text-start rounded-pill">Talk to an Agent</button>
            </div>
            <!-- Messages will be loaded here -->
        </div>
        <div class="chat-footer">
            <form id="chatForm" class="d-flex gap-2">
                <input type="text" class="form-control form-control-sm" id="chatInput" placeholder="Type a message..." required autocomplete="off">
                <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center p-2">
                    <span class="material-symbols-outlined fs-6">send</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Chat Trigger Button -->
    <button id="chatTrigger" class="chat-trigger btn btn-primary rounded-circle shadow-lg p-3 d-flex align-items-center justify-content-center">
        <span class="material-symbols-outlined fs-3">chat</span>
    </button>

    <style>
        .chat-widget {
            position: fixed;
            bottom: 90px;
            right: 30px;
            width: 350px;
            height: 500px;
            background-color: var(--color-surface-light);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            display: none;
            flex-direction: column;
            z-index: 1050;
            overflow: hidden;
            border: 1px solid var(--color-border-subtle);
        }

        .dark .chat-widget {
            background-color: var(--color-surface-dark);
            border-color: var(--color-border-dark);
        }

        .chat-header {
            background-color: var(--color-primary);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .chat-body {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background-color: rgba(0,0,0,0.02);
        }

        .dark .chat-body {
            background-color: rgba(255,255,255,0.02);
        }

        .chat-footer {
            padding: 1rem;
            border-top: 1px solid var(--color-border-subtle);
            background-color: var(--color-surface-light);
        }

        .dark .chat-footer {
            border-color: var(--color-border-dark);
            background-color: var(--color-surface-dark);
        }

        .chat-trigger {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            z-index: 1049;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .chat-trigger:hover {
            transform: scale(1.1);
        }

        .chat-message {
            max-width: 80%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.9rem;
            position: relative;
            word-wrap: break-word;
        }

        .chat-message.sent {
            align-self: flex-end;
            background-color: var(--color-primary);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }

        .chat-message.received {
            align-self: flex-start;
            background-color: var(--color-surface-light-alt);
            color: var(--color-text-main);
            border-bottom-left-radius: 0.25rem;
            border: 1px solid var(--color-border-subtle);
        }

        .dark .chat-message.received {
            background-color: var(--color-surface-dark-alt);
            color: var(--color-text-dark);
            border-color: var(--color-border-dark);
        }

        .message-time {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 0.25rem;
            display: block;
            text-align: right;
        }
    </style>

    <script>
        // Theme toggle logic (reused)
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const html = document.documentElement;
                const icon = this.querySelector('.material-symbols-outlined');
                if (html.classList.contains('light')) {
                    html.classList.remove('light');
                    html.classList.add('dark');
                    icon.textContent = 'light_mode';
                    localStorage.setItem('theme', 'dark');
                } else {
                    html.classList.remove('dark');
                    html.classList.add('light');
                    icon.textContent = 'dark_mode';
                    localStorage.setItem('theme', 'light');
                }
            });
        }
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.remove('light');
            document.documentElement.classList.add('dark');
            const toggleIcon = document.querySelector('#themeToggle .material-symbols-outlined');
            if (toggleIcon) toggleIcon.textContent = 'light_mode';
        }
        
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) sidebar.classList.toggle('open');
            });
        }

        // Chat Widget Logic
        const chatWidget = document.getElementById('chatWidget');
        const chatTrigger = document.getElementById('chatTrigger');
        const closeChat = document.getElementById('closeChat');
        const chatForm = document.getElementById('chatForm');
        const chatInput = document.getElementById('chatInput');
        const chatBody = document.getElementById('chatBody');
        const sendBtn = chatForm.querySelector('button');

        let lastMessageId = 0;
        let isPolling = false;

        function toggleChat() {
            if (chatWidget.style.display === 'flex') {
                chatWidget.style.display = 'none';
                chatTrigger.style.display = 'flex';
            } else {
                chatWidget.style.display = 'flex';
                chatTrigger.style.display = 'none';
                if (lastMessageId === 0) {
                    scrollToBottom();
                    fetchMessages(); 
                }
            }
        }

        chatTrigger.addEventListener('click', toggleChat);
        closeChat.addEventListener('click', toggleChat);

        function scrollToBottom() {
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function appendMessage(msg) {
            // Check if exists
            if (document.getElementById(`msg-${msg.id}`)) return;

            const msgDiv = document.createElement('div');
            msgDiv.id = `msg-${msg.id}`;
            msgDiv.className = `chat-message ${msg.type}`;
            
            // Build message content with role badge for received admin messages
            let messageContent = msg.message;
            let roleBadge = '';
            
            // Add role badge for received messages from admins
            if (msg.type === 'received' && msg.sender_role) {
                if (msg.sender_role === 'Super Admin') {
                    roleBadge = '<span class="badge bg-danger bg-opacity-75 text-white me-2" style="font-size: 0.65rem; padding: 0.2rem 0.5rem;">Super Admin</span>';
                } else if (msg.sender_role === 'Admin') {
                    roleBadge = '<span class="badge bg-primary bg-opacity-75 text-white me-2" style="font-size: 0.65rem; padding: 0.2rem 0.5rem;">Admin</span>';
                }
            }
            
            msgDiv.innerHTML = `
                ${roleBadge}${messageContent}
                <span class="message-time">${msg.time}</span>
            `;
            chatBody.appendChild(msgDiv);
            scrollToBottom();
            
            if (msg.id > lastMessageId) {
                lastMessageId = msg.id;
            }
        }

        async function askBot(topic, text) {
            // User sends the question text first (visually)
            await sendMessage(text, true); // true = skip fetch, we will fetch after bot reply
            
            // Hide Quick Questions
            const qq = document.getElementById('quickQuestions');
            if (qq) qq.style.display = 'none';

            // Trigger Bot Reply
            const formData = new FormData();
            formData.append('action', 'bot_auto_reply');
            formData.append('topic', topic);

            try {
                const response = await fetch('support_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    fetchMessages(); // Now fetch both user msg and bot reply
                    
                    if (topic === 'agent') {
                        // Optional: Visual indicator that human is needed?
                        // For now, the bot message "Connecting..." is enough.
                    }
                }
            } catch (error) {
                console.error('Error triggering bot:', error);
            }
        }

        async function sendMessage(text, skipFetch = false) {
            // UI Feedback
            sendBtn.disabled = true;
            chatInput.disabled = true;
            
            // Hide Quick Questions if user types manually
            const qq = document.getElementById('quickQuestions');
            if (qq) qq.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', text);

            try {
                const response = await fetch('support_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    chatInput.value = '';
                    if (!skipFetch) fetchMessages(); // Immediate fetch
                } else {
                    alert('Error sending message: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
            } finally {
                sendBtn.disabled = false;
                chatInput.disabled = false;
                chatInput.focus();
            }
        }

        async function fetchMessages() {
            if (isPolling) return;
            isPolling = true;

            try {
                const response = await fetch('support_api.php?action=get_messages');
                const data = await response.json();
                
                if (data.success) {
                    // Update Header
                    const chatSubtext = document.querySelector('#chatHeader span[style*="font-size"]');
                    if (data.messages.length > 0) {
                        // Find last message from admin (type 'received')
                        const lastAdminMsg = [...data.messages].reverse().find(m => m.type === 'received');
                        if (lastAdminMsg) {
                            if (lastAdminMsg.sender === 'Admin' || lastAdminMsg.sender === 'Super Admin') { // Assuming Bot or Generic
                                 chatSubtext.textContent = 'Waiting for Agent...';
                            } else {
                                 chatSubtext.textContent = `Chatting with ${lastAdminMsg.sender}`;
                            }
                        } else {
                            // No admin reply yet
                            chatSubtext.textContent = 'Waiting for Agent...';
                        }
                    } else {
                        chatSubtext.textContent = 'Chatting with Admin';
                    }

                    // Remove initial empty state if it exists
                    const emptyState = chatBody.querySelector('.text-muted.small.my-3');
                    if (emptyState && data.messages.length > 0) {
                        emptyState.remove();
                    }
                    
                    // Hide Quick Questions if there are messages
                    const qq = document.getElementById('quickQuestions');
                    if (qq && data.messages.length > 0) {
                        qq.style.display = 'none';
                    } else if (qq && data.messages.length === 0) {
                         qq.style.display = 'flex';
                    }

                    if (data.messages.length === 0 && chatBody.children.length <= 1) { 
                         // Empty state handled by QQ visibility
                    } else {
                        data.messages.forEach(msg => {
                            if (msg.id > lastMessageId) {
                                appendMessage(msg);
                            }
                        });
                    }
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
            } finally {
                isPolling = false;
            }
        }

        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const text = chatInput.value.trim();
            if (text) {
                sendMessage(text);
            }
        });

        // Fast Poll (1s)
        setInterval(() => {
            if (chatWidget.style.display === 'flex') {
                fetchMessages();
            }
        }, 1000);

    </script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
