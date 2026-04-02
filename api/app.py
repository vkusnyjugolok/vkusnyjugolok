import os
import json
import mysql.connector
from flask import Flask, request, jsonify
from flask_cors import CORS
from huggingface_hub import InferenceClient

app = Flask(__name__)
CORS(app)

# HuggingFace настройки
HF_TOKEN = os.environ.get("HF_TOKEN", "")
HF_MODEL = os.environ.get("HF_MODEL", "mistralai/Mistral-7B-Instruct-v0.3")

# MySQL настройки (из переменных окружения или дефолтные)
DB_HOST = os.environ.get("DB_HOST", "sql310.infinityfree.com")
DB_NAME = os.environ.get("DB_NAME", "if0_40780426_vkusnyjugolok")
DB_USER = os.environ.get("DB_USER", "if0_40780426")
DB_PASS = os.environ.get("DB_PASS", "SxEf8ruMFVF")


def get_db_connection():
    return mysql.connector.connect(
        host=DB_HOST,
        database=DB_NAME,
        user=DB_USER,
        password=DB_PASS,
        charset="utf8"
    )


def get_menu_items():
    """Получает все блюда из базы данных."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id, name, description, price, category, image FROM menu")
        items = cursor.fetchall()
        cursor.close()
        conn.close()
        # Конвертируем Decimal в float для JSON
        for item in items:
            if item.get("price"):
                item["price"] = float(item["price"])
        return items
    except Exception as e:
        print(f"Ошибка подключения к БД: {e}")
        return []


def build_prompt(user_message, menu_items):
    """Формирует промпт для модели."""
    menu_text = ""
    for item in menu_items:
        menu_text += (
            f"- {item['name']} ({item['category']}): "
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
6. Верни ответ в формате JSON:
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

    # Получаем меню из БД
    menu_items = get_menu_items()
    if not menu_items:
        return jsonify({
            "message": "К сожалению, не удалось загрузить меню. Попробуйте позже.",
            "recommendations": []
        })

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

        # Пытаемся распарсить JSON из ответа модели
        try:
            # Ищем JSON в ответе
            json_start = response_text.find("{")
            json_end = response_text.rfind("}") + 1
            if json_start != -1 and json_end > json_start:
                json_str = response_text[json_start:json_end]
                parsed = json.loads(json_str)
                message = parsed.get("message", response_text)
                recommended_ids = parsed.get("recommended_ids", [])
        except (json.JSONDecodeError, ValueError):
            # Если не удалось распарсить JSON, используем весь текст
            message = response_text

        # Фильтруем рекомендованные блюда
        recommendations = []
        for item in menu_items:
            if item["id"] in recommended_ids:
                recommendations.append(item)

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
