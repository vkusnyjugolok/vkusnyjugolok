import os
import json
import traceback
import re
from flask import Flask, request, jsonify
from flask_cors import CORS
from huggingface_hub import InferenceClient

app = Flask(__name__)
CORS(app)

# HuggingFace настройки
HF_TOKEN = os.environ.get("HF_TOKEN", "")
HF_MODEL = os.environ.get("HF_MODEL", "meta-llama/Llama-3.2-3B-Instruct")

# MySQL настройки
DB_HOST = os.environ.get("DB_HOST", "")
DB_PORT = 23176
DB_NAME = os.environ.get("DB_NAME", "defaultdb")
DB_USER = os.environ.get("DB_USER", "")
DB_PASS = os.environ.get("DB_PASS", "")

# Локальное меню fallback
FALLBACK_MENU = [
    {"id": 1, "name": "Паста Карбонара", "description": "Классическая итальянская паста с беконом и сливочным соусом", "price": 450.0, "category": "Основные блюда", "image": ""},
    {"id": 2, "name": "Тирамису", "description": "Нежный десерт с маскарпоне и кофе", "price": 250.0, "category": "Десерты", "image": ""},
    {"id": 3, "name": "Латте", "description": "Кофе с молоком и пышной пенкой", "price": 150.0, "category": "Напитки", "image": ""},
]

def get_menu_from_db():
    if not DB_HOST:
        return None
    try:
        import mysql.connector
        conn = mysql.connector.connect(
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASS,
            charset="utf8",
            ssl_disabled=False,
            connection_timeout=10
        )
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT id, name, description, price, category, image FROM menu")
        items = cursor.fetchall()
        cursor.close()
        conn.close()
        for item in items:
            item["price"] = float(item["price"])
        return items if items else None
    except Exception as e:
        print(f"БД недоступна: {e}")
        return None

def get_menu_items():
    db_menu = get_menu_from_db()
    return db_menu if db_menu else FALLBACK_MENU


def build_system_prompt(menu_items):
    menu_text = ""
    for item in menu_items:
        menu_text += (
            f"- ID:{item['id']} {item['name']} ({item['category']}): "
            f"{item['description']} — {item['price']} руб.\n"
        )

    return f"""
Ты — профессиональный ИИ-консультант кафе «Вкусный Уголок».

=====================
МЕНЮ:
=====================
{menu_text}

=====================
СТРОГИЕ ПРАВИЛА:
=====================

1. Рекомендуй ТОЛЬКО блюда из списка выше.
2. Если пользователь просит конкретный ингредиент —
   проверяй его наличие в названии или описании.
3. Нельзя додумывать состав.
4. Если ингредиент явно не указан — блюдо не подходит.
5. Если подходящих блюд нет — честно скажи об этом.
6. Отвечай кратко и дружелюбно.
7. Строка "РЕКОМЕНДУЮ_ID:" — служебная. Не объясняй её.

ФОРМАТ:

Название — цена  
Короткое объяснение  

В конце обязательно:

РЕКОМЕНДУЮ_ID: 1,2,3

Если ничего нет:

РЕКОМЕНДУЮ_ID: 0
"""


@app.route("/api/recommend", methods=["POST"])
def recommend():
    data = request.get_json()
    if not data or "message" not in data:
        return jsonify({"error": "Поле 'message' обязательно"}), 400

    user_message = data["message"].strip()
    if not user_message:
        return jsonify({"error": "Сообщение не может быть пустым"}), 400

    menu_items = get_menu_items()
    system_prompt = build_system_prompt(menu_items)

    try:
        client = InferenceClient(
            model=HF_MODEL,
            token=HF_TOKEN if HF_TOKEN else None,
            timeout=60
        )

        response = client.chat_completion(
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_message}
            ],
            max_tokens=512,
            temperature=0.2  # снижена для логичности
        )

        response_text = response.choices[0].message.content.strip()
        print("HF ответ:", response_text)

        recommended_ids = []

        # Извлекаем ID
        match = re.search(r"РЕКОМЕНДУЮ_ID:\s*([0-9,\s]+)", response_text)
        if match:
            ids_str = match.group(1)
            for id_str in ids_str.split(","):
                id_str = id_str.strip()
                if id_str.isdigit() and int(id_str) > 0:
                    recommended_ids.append(int(id_str))

        # Удаляем служебную строку полностью
        clean_message = re.sub(r"РЕКОМЕНДУЮ_ID:.*", "", response_text).strip()

        recommendations = [
            item for item in menu_items
            if item["id"] in recommended_ids
        ]

        return jsonify({
            "message": clean_message,
            "recommendations": recommendations
        })

    except Exception as e:
        print("Ошибка HF:", traceback.format_exc())
        return jsonify({
            "message": f"Ошибка ИИ-сервиса: {str(e)}",
            "recommendations": []
        }), 500


@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "hf_model": HF_MODEL,
        "hf_token_set": bool(HF_TOKEN),
        "db_host_set": bool(DB_HOST)
    })


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
