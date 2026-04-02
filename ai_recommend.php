<?php
session_start();
include 'includes/db_connect.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кафе Вкусный Уголок - ИИ Подбор блюд</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="css/ai_recommend.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="ai-hero text-center mb-4">
                    <div class="ai-icon-wrap mb-3">
                        <i class="bi bi-robot"></i>
                    </div>
                    <h2>ИИ-помощник по выбору блюд</h2>
                    <p class="text-muted">Расскажите о своих предпочтениях, аллергиях или настроении — и наш ИИ подберёт идеальное блюдо из меню!</p>
                </div>

                <!-- Быстрые подсказки -->
                <div class="quick-hints mb-4">
                    <p class="small text-muted mb-2"><i class="bi bi-lightbulb me-1"></i>Попробуйте спросить:</p>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-hint" onclick="setQuery('Я люблю итальянскую кухню, что посоветуете?')">
                            Итальянская кухня
                        </button>
                        <button class="btn btn-hint" onclick="setQuery('У меня аллергия на молочные продукты')">
                            Аллергия на молоко
                        </button>
                        <button class="btn btn-hint" onclick="setQuery('Хочу что-нибудь сладкое к кофе')">
                            Сладкое к кофе
                        </button>
                        <button class="btn btn-hint" onclick="setQuery('Посоветуйте лёгкий салат')">
                            Лёгкий салат
                        </button>
                    </div>
                </div>

                <!-- Чат -->
                <div class="chat-container">
                    <div class="chat-messages" id="chatMessages">
                        <div class="message bot-message">
                            <div class="message-avatar">
                                <i class="bi bi-robot"></i>
                            </div>
                            <div class="message-content">
                                <p>Привет! Я ваш ИИ-помощник. Расскажите, что вы любите или на что у вас аллергия, и я подберу подходящие блюда из нашего меню.</p>
                            </div>
                        </div>
                    </div>

                    <form id="chatForm" class="chat-input-area">
                        <div class="input-group">
                            <input type="text" id="userMessage" class="form-control"
                                   placeholder="Например: люблю мясо, но не ем глютен..."
                                   autocomplete="off" required>
                            <button type="submit" class="btn btn-coffee" id="sendBtn">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // API на том же сервере через nginx proxy
        const API_URL = '/api/recommend';

        const chatMessages = document.getElementById('chatMessages');
        const chatForm = document.getElementById('chatForm');
        const userMessageInput = document.getElementById('userMessage');
        const sendBtn = document.getElementById('sendBtn');

        function setQuery(text) {
            userMessageInput.value = text;
            userMessageInput.focus();
        }

        function addMessage(text, isUser) {
            const div = document.createElement('div');
            div.className = `message ${isUser ? 'user-message' : 'bot-message'}`;

            const avatar = document.createElement('div');
            avatar.className = 'message-avatar';
            avatar.innerHTML = isUser ? '<i class="bi bi-person-fill"></i>' : '<i class="bi bi-robot"></i>';

            const content = document.createElement('div');
            content.className = 'message-content';
            content.innerHTML = `<p>${escapeHtml(text)}</p>`;

            div.appendChild(avatar);
            div.appendChild(content);
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addRecommendations(recommendations) {
            if (!recommendations || recommendations.length === 0) return;

            const div = document.createElement('div');
            div.className = 'recommendations-grid';

            recommendations.forEach(item => {
                const imgSrc = item.image || 'https://via.placeholder.com/300x200?text=Блюдо';
                const price = parseFloat(item.price).toLocaleString('ru-RU');
                div.innerHTML += `
                    <div class="rec-card">
                        <img src="${escapeHtml(imgSrc)}" alt="${escapeHtml(item.name)}" class="rec-img">
                        <div class="rec-body">
                            <h6 class="rec-title">${escapeHtml(item.name)}</h6>
                            <p class="rec-desc">${escapeHtml(item.description || '')}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="rec-price">${price} &#8381;</span>
                                <span class="badge bg-coffee-cat">${escapeHtml(item.category || '')}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function addLoading() {
            const div = document.createElement('div');
            div.className = 'message bot-message loading-message';
            div.id = 'loadingMsg';
            div.innerHTML = `
                <div class="message-avatar"><i class="bi bi-robot"></i></div>
                <div class="message-content">
                    <div class="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            chatMessages.appendChild(div);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function removeLoading() {
            const el = document.getElementById('loadingMsg');
            if (el) el.remove();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = userMessageInput.value.trim();
            if (!message) return;

            addMessage(message, true);
            userMessageInput.value = '';
            sendBtn.disabled = true;
            addLoading();

            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message })
                });

                removeLoading();

                const data = await response.json();

                if (!response.ok) {
                    console.error('Ответ сервера (ошибка):', JSON.stringify(data, null, 2));
                    throw new Error(data.message || 'Ошибка сервера: ' + response.status);
                }

                console.log('Ответ сервера:', JSON.stringify(data, null, 2));
                addMessage(data.message || 'Не удалось получить рекомендации.', false);

                if (data.recommendations && data.recommendations.length > 0) {
                    addRecommendations(data.recommendations);
                }
            } catch (err) {
                removeLoading();
                addMessage('Ошибка: ' + err.message, false);
                console.error(err);
            } finally {
                sendBtn.disabled = false;
                userMessageInput.focus();
            }
        });
    </script>
</body>
</html>
