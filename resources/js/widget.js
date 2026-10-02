(function () {
    'use strict';

    if (window.OddenChatWidgetLoaded) return;
    window.OddenChatWidgetLoaded = true;

    const STORAGE_KEY = 'odden_support_chat_token';
    // Full base URL of the getodden/crm-service chat API, including any configured prefix
    // (e.g. "https://crm.example.com/api/service"). Defaults to the same-origin default path.
    const API_BASE = (window.ODDEN_CHAT_API_URL || '/api/service').replace(/\/+$/, '');

    // Inject styles
    const style = document.createElement('style');
    style.textContent = `
        .odden-chat-launcher {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4), 0 8px 10px -6px rgba(79, 70, 229, 0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999998;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            outline: none;
        }
        .odden-chat-launcher:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 20px 30px -10px rgba(79, 70, 229, 0.5);
        }
        .odden-chat-window {
            position: fixed;
            bottom: 96px;
            right: 24px;
            width: 380px;
            max-width: calc(100vw - 32px);
            height: 560px;
            max-height: calc(100vh - 120px);
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.2), 0 0 0 1px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            pointer-events: none;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .odden-chat-window.open {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .odden-chat-header {
            background: #4f46e5;
            color: #ffffff;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .odden-chat-header-title {
            font-weight: 700;
            font-size: 15px;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .odden-chat-status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
        }
        .odden-chat-close-btn {
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: #ffffff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .odden-chat-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .odden-chat-body {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .odden-msg {
            max-width: 82%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13.5px;
            line-height: 1.45;
            word-break: break-word;
        }
        .odden-msg-customer {
            align-self: flex-end;
            background: #4f46e5;
            color: #ffffff;
            border-bottom-right-radius: 3px;
        }
        .odden-msg-agent, .odden-msg-system {
            align-self: flex-start;
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 3px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .odden-msg-sender {
            font-size: 10.5px;
            font-weight: 600;
            margin-bottom: 4px;
            opacity: 0.75;
        }
        .odden-msg-time {
            font-size: 10px;
            opacity: 0.65;
            margin-top: 4px;
            text-align: right;
        }
        .odden-chat-footer {
            padding: 12px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
            display: flex;
            gap: 8px;
        }
        .odden-chat-input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.2s;
        }
        .odden-chat-input:focus {
            border-color: #4f46e5;
        }
        .odden-chat-send-btn {
            background: #4f46e5;
            color: #ffffff;
            border: none;
            padding: 0 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .odden-chat-send-btn:hover {
            background: #4338ca;
        }
        .odden-chat-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 10px;
        }
        .odden-form-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .odden-form-label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .odden-form-input, .odden-form-textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
            outline: none;
        }
        .odden-form-input:focus, .odden-form-textarea:focus {
            border-color: #4f46e5;
        }
    `;
    document.head.appendChild(style);

    // Create Launcher Button
    const launcher = document.createElement('button');
    launcher.className = 'odden-chat-launcher';
    launcher.setAttribute('aria-label', 'Open Support Chat');
    launcher.innerHTML = `
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
    `;
    document.body.appendChild(launcher);

    // Create Chat Window
    const chatWindow = document.createElement('div');
    chatWindow.className = 'odden-chat-window';
    chatWindow.innerHTML = `
        <div class="odden-chat-header">
            <div class="odden-chat-header-title">
                <span class="odden-chat-status-dot"></span>
                <span>Support Team</span>
            </div>
            <button class="odden-chat-close-btn" aria-label="Close Chat">&times;</button>
        </div>
        <div class="odden-chat-body" id="odden-chat-messages">
            <!-- Messages or form rendered here -->
        </div>
        <div class="odden-chat-footer" id="odden-chat-footer" style="display: none;">
            <input type="text" class="odden-chat-input" id="odden-chat-input" placeholder="Type your reply..." />
            <button class="odden-chat-send-btn" id="odden-chat-send-btn">Send</button>
        </div>
    `;
    document.body.appendChild(chatWindow);

    let isOpen = false;
    let pollInterval = null;
    let currentToken = localStorage.getItem(STORAGE_KEY);

    const closeBtn = chatWindow.querySelector('.odden-chat-close-btn');
    const messagesContainer = chatWindow.querySelector('#odden-chat-messages');
    const footer = chatWindow.querySelector('#odden-chat-footer');
    const input = chatWindow.querySelector('#odden-chat-input');
    const sendBtn = chatWindow.querySelector('#odden-chat-send-btn');

    function toggleChat() {
        isOpen = !isOpen;
        if (isOpen) {
            chatWindow.classList.add('open');
            if (currentToken) {
                loadMessages();
                pollInterval = setInterval(loadMessages, 4000);
            } else {
                renderStartForm();
            }
        } else {
            chatWindow.classList.remove('open');
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        }
    }

    launcher.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    function renderStartForm() {
        footer.style.display = 'none';
        messagesContainer.innerHTML = `
            <div class="odden-chat-form">
                <div style="margin-bottom: 6px;">
                    <h3 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: #0f172a;">Chat with Support</h3>
                    <p style="margin: 0; font-size: 12.5px; color: #64748b;">We're online and ready to help answer questions.</p>
                </div>
                <div class="odden-form-field">
                    <label class="odden-form-label">Full Name</label>
                    <input type="text" id="odden-form-name" class="odden-form-input" placeholder="e.g. Jane Doe" required />
                </div>
                <div class="odden-form-field">
                    <label class="odden-form-label">Email Address</label>
                    <input type="email" id="odden-form-email" class="odden-form-input" placeholder="jane@example.com" required />
                </div>
                <div class="odden-form-field">
                    <label class="odden-form-label">Company (Optional)</label>
                    <input type="text" id="odden-form-company" class="odden-form-input" placeholder="Acme Corp" />
                </div>
                <div class="odden-form-field">
                    <label class="odden-form-label">How can we help?</label>
                    <textarea id="odden-form-msg" class="odden-form-textarea" rows="3" placeholder="Describe what you need..." required></textarea>
                </div>
                <button type="button" id="odden-form-submit" class="odden-chat-send-btn" style="padding: 11px; margin-top: 4px; font-size: 14px;">
                    Start Conversation
                </button>
            </div>
        `;

        const submitBtn = messagesContainer.querySelector('#odden-form-submit');
        submitBtn.addEventListener('click', startConversation);
    }

    function startConversation() {
        const name = messagesContainer.querySelector('#odden-form-name').value.trim();
        const email = messagesContainer.querySelector('#odden-form-email').value.trim();
        const company = messagesContainer.querySelector('#odden-form-company').value.trim();
        const message = messagesContainer.querySelector('#odden-form-msg').value.trim();

        if (!name || !email || !message) {
            alert('Please fill out name, email, and message.');
            return;
        }

        fetch(`${API_BASE}/chat/start`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, company, message })
        })
        .then(res => res.json())
        .then(data => {
            if (data.token) {
                currentToken = data.token;
                localStorage.setItem(STORAGE_KEY, currentToken);
                renderMessages(data.messages);
                footer.style.display = 'flex';
                pollInterval = setInterval(loadMessages, 4000);
            }
        })
        .catch(err => {
            console.error('Failed to start chat:', err);
            alert('Unable to connect to chat service. Please try again.');
        });
    }

    function loadMessages() {
        if (!currentToken) return;

        fetch(`${API_BASE}/chat/${encodeURIComponent(currentToken)}/messages`)
            .then(res => res.json())
            .then(data => {
                if (data.messages) {
                    renderMessages(data.messages);
                    footer.style.display = data.merged ? 'none' : 'flex';
                }
                if (data.merged) {
                    showMergedNotice(data.notice);
                }
            })
            .catch(() => {});
    }

    function renderMessages(msgs) {
        // Build nodes with textContent so sender names, bodies and timestamps are never parsed as HTML.
        messagesContainer.replaceChildren(...msgs.map(m => {
            const msg = document.createElement('div');
            msg.className = 'odden-msg ' + (m.is_customer ? 'odden-msg-customer' : 'odden-msg-agent');

            const sender = document.createElement('div');
            sender.className = 'odden-msg-sender';
            sender.textContent = m.sender_name;

            const body = document.createElement('div');
            body.textContent = m.body;

            const time = document.createElement('div');
            time.className = 'odden-msg-time';
            time.textContent = m.created_at;

            msg.append(sender, body, time);

            return msg;
        }));
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function sendMessage() {
        const text = input.value.trim();
        if (!text || !currentToken) return;

        input.value = '';

        fetch(`${API_BASE}/chat/${encodeURIComponent(currentToken)}/message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
        .then(res => res.json())
        .then(data => {
            if (data.messages) {
                renderMessages(data.messages);
            }
            if (data.merged) {
                showMergedNotice(data.notice);
            }
        })
        .catch(err => console.error('Failed to send message:', err));
    }

    // The chat's ticket was merged into another ticket: the session is read-only from now on,
    // and the customer carries on by email.
    function showMergedNotice(text) {
        const notice = document.createElement('div');
        notice.className = 'odden-msg odden-msg-agent';
        notice.textContent = text || 'This conversation has moved. Please check your email.';
        messagesContainer.append(notice);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        footer.style.display = 'none';
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
})();
