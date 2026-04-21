import os
import traceback
import re
from flask import Flask, request, jsonify
from flask_cors import CORS
from huggingface_hub import InferenceClient

app = Flask(__name__)
CORS(app)

# =============================
# НАСТРОЙКИ
# =============================

HF_TOKEN = os.environ.get("HF_TOKEN", "")
HF_MODEL = os.environ.get("HF_MODEL", "meta-llama/Llama-3.2-3B-Instruct")

DB_HOST = os.environ.get("DB_HOST", "")
DB_PORT = 23176
DB_NAME = os.environ.get("DB_NAME", "defaultdb")
DB_USER = os.environ.get("DB_USER", "")
DB_PASS = os.environ.get("DB_PASS", "")

# =============================
# ПОЛУЧЕНИЕ МЕНЮ
# =============================

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
        print(f"Ошибка БД: {e}")
        return None


def get_menu_items():
    db_menu = get_menu_from_db()
    return db_menu if db_menu else []


# =============================
# ПРОМПТ
# =============================

def build_system_prompt(menu_items):
    menu_text = ""
    for item in menu_items:
        menu_text += (
            f"- ID:{item['id']} {item['name']} ({item['category']}): "
            f"{item['description']} — {item['price']} руб.\n"
        )

    return f"""
Ты — дружелюбный ИИ-консультант кафе «Вкусный Уголок».

Ты ведёшь диалог и обязан учитывать ВСЕ ранее указанные ограничения,
аллергии, бюджет и предпочтения пользователя.

=====================
МЕНЮ:
=====================
{menu_text}

=====================
ПРАВИЛА:
=====================

1. Рекомендуй ТОЛЬКО блюда из меню.
2. Если пользователь указал аллергию — учитывай её во всех следующих ответах.
3. Если ингредиент явно не указан в описании — считать, что его нет.
4. Нельзя придумывать состав.
5. При ограничении бюджета — считай сумму.
6. Если ничего не подходит — честно сообщи об этом.
7. Не копируй описание дословно — перефразируй.
8. "РЕКОМЕНДУЮ_ID:" — служебная строка, не объясняй её.

=====================
СТИЛЬ:
=====================

Отвечай живо, как официант.
3–5 предложений.
Не сухо.
Не слишком длинно.

=====================
ФОРМАТ:
=====================

В конце ОБЯЗАТЕЛЬНО добавь:

РЕКОМЕНДУЮ_ID: 1,2

Если ничего не подходит:

РЕКОМЕНДУЮ_ID: 0
"""


# =============================
# API
# =============================

@app.route("/api/recommend", methods=["POST"])
def recommend():
    data = request.get_json()

    if not data or "messages" not in data:
        return jsonify({"error": "Поле 'messages' обязательно"}), 400

    conversation = data["messages"]

    if not isinstance(conversation, list) or len(conversation) == 0:
        return jsonify({"error": "messages должен быть непустым массивом"}), 400

    menu_items = get_menu_items()
    system_prompt = build_system_prompt(menu_items)

    try:
        client = InferenceClient(
            model=HF_MODEL,
            token=HF_TOKEN if HF_TOKEN else None,
            timeout=60
        )

        hf_messages = [{"role": "system", "content": system_prompt}] + conversation

        response = client.chat_completion(
            messages=hf_messages,
            max_tokens=512,
            temperature=0.3
        )

        response_text = response.choices[0].message.content.strip()
        print("HF ответ:", response_text)

        # =============================
        # ИЗВЛЕЧЕНИЕ ID
        # =============================

        recommended_ids = []

        match = re.search(r"РЕКОМЕНДУЮ_ID:\s*([0-9,\s]+)", response_text)
        if match:
            ids_str = match.group(1)
            for id_str in ids_str.split(","):
                id_str = id_str.strip()
                if id_str.isdigit() and int(id_str) > 0:
                    recommended_ids.append(int(id_str))

        # Удаляем служебную строку
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


# =============================
# HEALTH
# =============================

@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "hf_model": HF_MODEL,
        "hf_token_set": bool(HF_TOKEN),
        "db_host_set": bool(DB_HOST)
    })


# =============================
# ЗАПУСК
# =============================

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
