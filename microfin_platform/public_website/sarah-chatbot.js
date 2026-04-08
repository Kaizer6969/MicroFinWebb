(function () {
    function initSarahChatbot() {
        const root = document.querySelector('[data-sarah-chatbot]');
        if (!root || root.dataset.sarahReady === '1') {
            return;
        }

        root.dataset.sarahReady = '1';

        const windowEl = root.querySelector('.sarah-chatbot-window');
        const launcher = root.querySelector('.sarah-chatbot-launcher');
        const closeBtn = root.querySelector('.sarah-chatbot-close');
        const messagesEl = root.querySelector('.sarah-chatbot-messages');
        const form = root.querySelector('.sarah-chatbot-form');
        const input = root.querySelector('.sarah-chatbot-input');
        const chips = root.querySelectorAll('.sarah-chatbot-chip');
        const hasPricingSection = Boolean(document.querySelector('#pricing'));
        const hasSecuritySection = Boolean(document.querySelector('#security'));
        const hasApplyForm = Boolean(document.getElementById('demo-form'));
        const agentMailTo = 'mailto:hello@microfin.os?subject=Talk%20to%20a%20MicroFin%20Agent';
        let initializedConversation = false;

        function openChat() {
            root.classList.add('is-open');
            windowEl.hidden = false;
            launcher.setAttribute('aria-expanded', 'true');

            if (!initializedConversation) {
                appendBotMessage(buildWelcomeMessage());
                initializedConversation = true;
            }

            window.setTimeout(function () {
                input.focus();
                scrollMessagesToBottom();
            }, 60);
        }

        function closeChat() {
            root.classList.remove('is-open');
            launcher.setAttribute('aria-expanded', 'false');
            windowEl.hidden = true;
        }

        function scrollMessagesToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function appendMessage(text, sender, allowHtml) {
            const bubble = document.createElement('div');
            bubble.className = 'sarah-chatbot-message sarah-chatbot-message--' + sender;

            if (allowHtml) {
                bubble.innerHTML = text;
            } else {
                bubble.textContent = text;
            }

            messagesEl.appendChild(bubble);
            scrollMessagesToBottom();
        }

        function appendUserMessage(text) {
            appendMessage(text, 'user', false);
        }

        function appendBotMessage(text) {
            appendMessage(text, 'bot', true);
        }

        function linkHtml(label, href) {
            return '<a class="sarah-chatbot-inline-link" href="' + href + '">' + label + '</a>';
        }

        function buildWelcomeMessage() {
            return "Hi, I'm <strong>Sarah</strong>. I can help with pricing, onboarding, security, migration, and getting started with MicroFin.";
        }

        function buildResponse(userInput) {
            const text = userInput.trim().toLowerCase();

            if (!text) {
                return "Ask me about pricing, setup time, security, or how to get started.";
            }

            if (/(hi|hello|hey|good morning|good afternoon|good evening)/i.test(text)) {
                return "Hello. I'm Sarah, your MicroFin website assistant. You can ask me about pricing, onboarding, security, migration, or applying.";
            }

            if (/(price|pricing|plan|cost|subscription|starter|pro|enterprise|unlimited)/i.test(text)) {
                if (hasPricingSection) {
                    return "Our plans start at <strong>P4,999/mo</strong> for Starter, then scale through Pro, Enterprise, and Unlimited. You can review the full breakdown in " + linkHtml('Pricing', '#pricing') + ".";
                }

                return "You can choose from Starter, Pro, Enterprise, and Unlimited directly in the application form below. Pick the plan that matches your client and staff scale.";
            }

            if (/(security|safe|compliance|encrypt|encryption|backup|tenant isolation|isolation)/i.test(text)) {
                if (hasSecuritySection) {
                    return "MicroFin highlights strict tenant isolation, AES-256 and TLS 1.3 protection, plus automated backups. You can jump to the " + linkHtml('Security section', '#security') + " for the overview.";
                }

                return "MicroFin is built around isolated tenant environments, encrypted traffic and storage, and automated backup processes to protect operational data.";
            }

            if (/(setup|onboarding|migration|migrate|go live|implementation|how long|timeline)/i.test(text)) {
                if (document.querySelector('#how-it-works')) {
                    return "Most institutions can get moving in days, not months. The usual path is discovery, provisioning, then onboarding and setup. You can review the flow in " + linkHtml('How it Works', '#how-it-works') + ".";
                }

                return "MicroFin onboarding is designed to move quickly. Once your request is approved, provisioning and guided setup can begin right away.";
            }

            if (/(apply|demo|start|get started|signup|sign up|register)/i.test(text)) {
                if (hasApplyForm) {
                    return "You're already on the application page. Complete the form, verify your email with OTP, and then submit your request.";
                }

                return "You can start your onboarding request from " + linkHtml('Apply Now', 'demo.php') + ".";
            }

            if (/(app|mobile|android|apk)/i.test(text)) {
                return "MicroFin also supports tenant mobile-app distribution workflows, including tenant-specific app delivery once the mobile build is prepared.";
            }

            if (/(staff|expert|agent|human|person|representative|support)/i.test(text)) {
                return "You can still talk to an agent. Use " + linkHtml('Email an Agent', agentMailTo) + " for a direct handoff, or submit " + linkHtml('Apply Now', 'demo.php') + " and the team will follow up with you.";
            }

            return "I can help with pricing, setup time, security, migration, or getting started. Try one of the quick prompts below, or ask me in a different way.";
        }

        function submitPrompt(promptText) {
            const cleaned = promptText.trim();
            if (!cleaned) {
                return;
            }

            appendUserMessage(cleaned);

            window.setTimeout(function () {
                appendBotMessage(buildResponse(cleaned));
            }, 240);
        }

        launcher.addEventListener('click', function () {
            if (root.classList.contains('is-open')) {
                closeChat();
            } else {
                openChat();
            }
        });

        closeBtn.addEventListener('click', function () {
            closeChat();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const promptText = input.value;
            input.value = '';
            openChat();
            submitPrompt(promptText);
        });

        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                openChat();
                submitPrompt(chip.dataset.prompt || chip.textContent || '');
            });
        });

        document.querySelectorAll('.js-open-sarah-chat').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openChat();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && root.classList.contains('is-open')) {
                closeChat();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSarahChatbot);
    } else {
        initSarahChatbot();
    }
})();
