<?php
// =============================================================================
// footer.php — Shared HTML Footer
// =============================================================================
?>
</main><!-- /main-content -->

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> NutriShift &mdash; Track by cycle, not the clock.</p>
</footer>

<!-- TEACHING NOTE: We load JavaScript at the BOTTOM of the body (before </body>)
     so that the browser can render the HTML first without being blocked by JS.
     This improves perceived page load speed. -->
<script src="assets/js/main.js"></script>

<!-- ─── AI DAILY COACH FAB & CHAT WINDOW ─── -->
<style>
/* Sleek Dark Mode Chat UI */
#ai-chat-fab {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #00d26a;
    color: white;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    z-index: 1000;
}
#ai-chat-fab:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 210, 106, 0.4);
}
#ai-chat-window {
    position: fixed;
    bottom: 100px;
    right: 30px;
    width: 350px;
    height: 450px;
    background: #1f2937;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateY(120%);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.4s ease;
    z-index: 999;
    border: 1px solid #374151;
}
#ai-chat-window.open {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
#ai-chat-header {
    background: #111827;
    color: white;
    padding: 15px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #374151;
}
#ai-chat-close {
    cursor: pointer;
    font-size: 20px;
    color: #9ca3af;
    transition: color 0.2s;
}
#ai-chat-close:hover {
    color: white;
}
#chat-messages {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #1f2937;
}
.chat-msg {
    padding: 10px 14px;
    border-radius: 8px;
    max-width: 85%;
    font-size: 0.95rem;
    line-height: 1.4;
    word-wrap: break-word;
}
.chat-msg.user {
    background: #00d26a;
    color: white;
    align-self: flex-end;
    border-bottom-right-radius: 2px;
}
.chat-msg.ai {
    background: #374151;
    color: #f8fafc;
    align-self: flex-start;
    border-bottom-left-radius: 2px;
}
.chat-msg.loading {
    background: transparent;
    color: #9ca3af;
    font-style: italic;
    align-self: flex-start;
}
#ai-chat-input-area {
    display: flex;
    padding: 10px;
    background: #111827;
    border-top: 1px solid #374151;
}
#ai-chat-input {
    flex: 1;
    padding: 10px;
    border: 1px solid #374151;
    border-radius: 6px;
    background: #1f2937;
    color: white;
    outline: none;
}
#ai-chat-input:focus {
    border-color: #00d26a;
}
#ai-chat-send {
    background: #00d26a;
    color: white;
    border: none;
    padding: 0 15px;
    margin-left: 10px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.2s;
}
#ai-chat-send:hover {
    background: #00b359;
}
</style>

<div id="ai-chat-fab">💬</div>

<div id="ai-chat-window">
    <div id="ai-chat-header">
        <span>⚡ NutriShift AI Coach</span>
        <span id="ai-chat-close">&times;</span>
    </div>
    <div id="chat-messages">
        <div class="chat-msg ai">Hello! I'm your personal AI coach. Need advice on your workout or nutrition today?</div>
    </div>
    <div id="ai-chat-input-area">
        <input type="text" id="ai-chat-input" placeholder="Ask your coach..." autocomplete="off">
        <button id="ai-chat-send">Send</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const fab = document.getElementById("ai-chat-fab");
    const chatWindow = document.getElementById("ai-chat-window");
    const closeBtn = document.getElementById("ai-chat-close");
    const chatMessages = document.getElementById("chat-messages");
    const inputField = document.getElementById("ai-chat-input");
    const sendBtn = document.getElementById("ai-chat-send");

    if (fab && chatWindow) {
        fab.addEventListener("click", () => {
            chatWindow.classList.toggle("open");
        });

        closeBtn.addEventListener("click", () => {
            chatWindow.classList.remove("open");
        });

        async function sendMessage() {
            const text = inputField.value.trim();
            if (!text) return;

            // Add user message
            const userMsg = document.createElement("div");
            userMsg.className = "chat-msg user";
            userMsg.textContent = text;
            chatMessages.appendChild(userMsg);
            
            inputField.value = "";
            chatMessages.scrollTop = chatMessages.scrollHeight;

            // Add loading state
            const loadingMsg = document.createElement("div");
            loadingMsg.className = "chat-msg loading";
            loadingMsg.textContent = "Coach is typing...";
            chatMessages.appendChild(loadingMsg);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            try {
                const response = await fetch("chat_handler.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ message: text })
                });

                if (response.status === 401) {
                    window.location.href = "index.php"; // Redirect if not logged in
                    return;
                }

                const data = await response.json();
                
                // Remove loading
                loadingMsg.remove();

                // Add AI response
                const aiMsg = document.createElement("div");
                aiMsg.className = "chat-msg ai";
                aiMsg.innerHTML = marked.parse(data.response) || "Sorry, I couldn't process that.";
                chatMessages.appendChild(aiMsg);
                chatMessages.scrollTop = chatMessages.scrollHeight;

            } catch (error) {
                loadingMsg.remove();
                const errorMsg = document.createElement("div");
                errorMsg.className = "chat-msg ai";
                errorMsg.style.color = "#ef4444";
                errorMsg.textContent = "Network error. Please try again later.";
                chatMessages.appendChild(errorMsg);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        sendBtn.addEventListener("click", sendMessage);
        inputField.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                sendMessage();
            }
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
</body>
</html>
