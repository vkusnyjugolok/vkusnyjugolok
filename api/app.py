import os
import json
from flask import Flask, request, jsonify
from flask_cors import CORS
from huggingface_hub import InferenceClient

app = Flask(__name__)
CORS(app)

# HuggingFace настройки
HF_TOKEN = os.environ.get("HF_TOKEN", "")
HF_MODEL = os.environ.get("HF_MODEL", "mistralai/Mistral-7B-Instruct-v0.3")

# MySQL настройки (из переменных окружения)
DB_HOST = os.environ.get("DB_HOST", "")
DB_NAME = os.environ.get("DB_NAME", "")
DB_USER = os.environ.get("DB_USER", "")
DB_PASS = os.environ.get("DB_PASS", "")

# Локальное меню (fallback, если БД недоступна)
FALLBACK_MENU = [
    {"id": 1, "name": "Паста Карбонара", "description": "Классическая итальянская паста с беконом и сливочным соусом", "price": 450.0, "category": "Основные блюда", "image": "uploads/689b3dbec371c.jpg"},
    {"id": 2, "name": "Тирамису", "description": "Нежный десерт с маскарпоне и кофе", "price": 250.0, "category": "Десерты", "image": "uploads/689b3e008a93d.jpg"},
    {"id": 3, "name": "Латте", "description": "Кофе с молоком и пышной пенкой", "price": 150.0, "category": "Напитки", "image": ""},
    {"id": 4, "name": "Цезарь с курицей", "description": "Салат с курицей, пармезаном и сухариками", "price": 350.0, "category": "Салаты", "image": ""},
    {"id": 5, "name": "Бутерброд", "description": "Вкусный бутерброд", "price": 150.0, "category": "Основные блюда", "image": ""},
]


def get_menu_from_db():
    """Пытается получить меню из MySQL. Возвращает None при ошибке."""
    if not DB_HOST:
        return None
    try:
        import mysql.connector
        conn = mysql.connector.connect(
            host=DB_HOST, database=DB_NAME,
            user=DB_USER, password=DB_PASS, charset="utf8"
        )
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id, name, description, price, category, image FROM menu")
        items = cursor.fetchall()
        cursor.close()
        conn.close()
        for item in items:
            if item.get("price"):
                item["price"] = float(item["price"])
        return items if items else None
    except Exception as e:
        print(f"БД недоступна, используем локальное меню: {e}")
        return None


def get_menu_items():
    """Получает меню: сначала из БД, fallback — локальный список."""
    db_menu = get_menu_from_db()
    return db_menu if db_menu else FALLBACK_MENU


def build_prompt(user_message, menu_items):
    """Формирует промпт для модели."""
    menu_text = ""
    for item in menu_items:
        menu_text += (
            f"- ID:{item['id']} {item['name']} ({item['category']}): "
            f"{item['description']} — {item['price']} руб.\n"
        )

    prompt = f"""<s>[INST] Ты — ИИ-помощник кафе «Вкусный Уголок». Твоя задача — помочь гостю выбрать блюдо из меню на основе его предпочтений, аллергий и пожеланий.

Вот наше меню:
{menu_text}

Правила:
1. Рекомендуй ТОЛЬКО блюда из списка меню выше.
2. Учитывай аллергии и противопоказания — НЕ рекомендуй блюда с аллергенами.
3. Отвечай на русском языке, дружелюбно и кратко.
4. Для каждого рекомендованного блюда укажи название, цену и почему оно подходит.
5. Если ни одно блюдо не подходит, честно скажи об этом.
6. Верни ответ СТРОГО в формате JSON без дополнительного текста:
{{
  "message": "Текст ответа для пользователя с рекомендациями",
  "recommended_ids": [1, 2]
}}

Запрос гостя: {user_message} [/INST]</s>"""

    return prompt


@app.route("/api/recommend", methods=["POST"])
def recommend():
    data = request.get_json()
    if not data or "message" not in data:
        return jsonify({"error": "Поле 'message' обязательно"}), 400

    user_message = data["message"].strip()
    if not user_message:
        return jsonify({"error": "Сообщение не может быть пустым"}), 400

    # Получаем меню
    menu_items = get_menu_items()

    # Формируем промпт и отправляем в HuggingFace
    prompt = build_prompt(user_message, menu_items)

    try:
        client = InferenceClient(token=HF_TOKEN)
        response = client.text_generation(
            prompt,
            model=HF_MODEL,
            max_new_tokens=1024,
            temperature=0.7,
            do_sample=True,
        )

        # Пробуем извлечь JSON из ответа
        response_text = response.strip()
        recommended_ids = []
        message = response_text

        try:
            json_start = response_text.find("{")
            json_end = response_text.rfind("}") + 1
            if json_start != -1 and json_end > json_start:
                json_str = response_text[json_start:json_end]
                parsed = json.loads(json_str)
                message = parsed.get("message", response_text)
                recommended_ids = parsed.get("recommended_ids", [])
        except (json.JSONDecodeError, ValueError):
            message = response_text

        # Фильтруем рекомендованные блюда
        recommendations = [item for item in menu_items if item["id"] in recommended_ids]

        return jsonify({
            "message": message,
            "recommendations": recommendations
        })

    except Exception as e:
        print(f"Ошибка HuggingFace API: {e}")
        return jsonify({
            "message": "Произошла ошибка при обработке запроса. Попробуйте позже.",
            "recommendations": []
        }), 500


@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
