import os
import json
import traceback
from flask import Flask, request, jsonify
from flask_cors import CORS
from huggingface_hub import InferenceClient

app = Flask(__name__)
CORS(app)

# HuggingFace настройки
HF_TOKEN = os.environ.get("HF_TOKEN", "")
# Бесплатные модели с поддержкой chat completion (по приоритету)

HF_MODEL = os.environ.get("HF_MODEL", 'Qwen/Qwen2.5-72B-Instruct')

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


def build_system_prompt(menu_items):
    """Формирует системный промпт."""
    menu_text = ""
    for item in menu_items:
        menu_text += (
            f"- ID:{item['id']} {item['name']} ({item['category']}): "
            f"{item['description']} — {item['price']} руб.\n"
        )

    return f"""Ты — ИИ-помощник кафе «Вкусный Уголок». Твоя задача — помочь гостю выбрать блюдо из меню на основе его предпочтений, аллергий и пожеланий.

Вот наше меню:
{menu_text}

Правила:
1. Рекомендуй ТОЛЬКО блюда из списка меню выше.
2. Учитывай аллергии и противопоказания — НЕ рекомендуй блюда с аллергенами.
3. Отвечай на русском языке, дружелюбно и кратко.
4. Для каждого рекомендованного блюда укажи название, цену и почему оно подходит.
5. Если ни одно блюдо не подходит, честно скажи об этом.
6. В конце ответа ОБЯЗАТЕЛЬНО добавь строку: РЕКОМЕНДУЮ_ID: 1,2,3 (перечисли ID рекомендованных блюд через запятую, или РЕКОМЕНДУЮ_ID: 0 если ничего не подходит)."""


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
    system_prompt = build_system_prompt(menu_items)

    try:
        token = HF_TOKEN if HF_TOKEN else None
        messages = [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_message}
        ]

        # Пробуем модели по очереди
        models_to_try = [HF_MODEL] 
        response_text = None
        last_error = None

        for model in models_to_try:
            try:
                print(f"Пробуем модель: {model}")
                client = InferenceClient(model=model, token=token)
                response = client.chat_completion(
                    messages=messages,
                    max_tokens=512,
                    temperature=0.7,
                )
                response_text = response.choices[0].message.content.strip()
                print(f"HF ответ ({model}): {response_text}")
                break
            except Exception as model_err:
                last_error = model_err
                print(f"Модель {model} не сработала: {model_err}")
                continue

        if response_text is None:
            raise last_error or Exception("Все модели недоступны")

        # Извлекаем ID рекомендованных блюд из ответа
        recommended_ids = []
        message = response_text

        # Ищем строку РЕКОМЕНДУЮ_ID: ...
        id_marker = "РЕКОМЕНДУЮ_ID:"
        if id_marker in response_text:
            parts = response_text.split(id_marker)
            message = parts[0].strip()
            ids_str = parts[1].strip().split("\n")[0].strip()
            for id_str in ids_str.split(","):
                id_str = id_str.strip()
                if id_str.isdigit() and int(id_str) > 0:
                    recommended_ids.append(int(id_str))

        # Если маркер не найден, пробуем JSON fallback
        if not recommended_ids and "{" in response_text:
            try:
                json_start = response_text.find("{")
                json_end = response_text.rfind("}") + 1
                if json_start != -1 and json_end > json_start:
                    parsed = json.loads(response_text[json_start:json_end])
                    if "recommended_ids" in parsed:
                        recommended_ids = [int(x) for x in parsed["recommended_ids"] if int(x) > 0]
                    if "message" in parsed:
                        message = parsed["message"]
            except (json.JSONDecodeError, ValueError):
                pass

        # Фильтруем рекомендованные блюда
        recommendations = [item for item in menu_items if item["id"] in recommended_ids]

        return jsonify({
            "message": message,
            "recommendations": recommendations
        })

    except Exception as e:
        error_details = traceback.format_exc()
        print(f"Ошибка HuggingFace API: {error_details}")
        return jsonify({
            "message": f"Ошибка ИИ-сервиса. Подробности: {str(e)}",
            "recommendations": []
        }), 500


@app.route("/api/health", methods=["GET"])
def health():
    """Проверка здоровья + диагностика."""
    token = HF_TOKEN if HF_TOKEN else None
    status = {"status": "ok", "hf_model": HF_MODEL, "hf_token_set": bool(HF_TOKEN), "db_host_set": bool(DB_HOST)}
    # Проверяем каждую модель
    client = InferenceClient(model=HF_MODEL, token=token)
    client.chat_completion(messages=[{"role": "user", "content": "Привет"}], max_tokens=10)
    status[HF_MODEL] = "ok"
    return jsonify(status)


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
