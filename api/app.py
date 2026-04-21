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
HF_MODEL = os.environ.get("HF_MODEL", "deepseek-ai/DeepSeek-R1")

DB_HOST = os.environ.get("DB_HOST", "")
DB_PORT = 23176
DB_NAME = os.environ.get("DB_NAME", "defaultdb")
DB_USER = os.environ.get("DB_USER", "")
DB_PASS = os.environ.get("DB_PASS", "")

# =============================
# ОЧИСТКА DEEPSEEK
# =============================

def clean_deepseek_response(text):
    """Убираем блок размышлений DeepSeek-R1"""
    text = re.sub(r"<think>.*?</think>", "", text, flags=re.DOTALL)
    return text.strip()

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
            f"[{item['id']}] {item['name']} | "
            f"{item['category']} | "
            f"{item['description']} | "
            f"{item['price']} руб.\n"
        )

    return f"""
Ты — ИИ-консультант кафе «Вкусный Уголок».

Ты ОБЯЗАН строго соблюдать ограничения пользователя.
Аллергии и запреты — абсолютный приоритет.

==================================
МЕНЮ (ничего нельзя придумывать):
==================================
{menu_text}

==================================
СТРОГИЕ ПРАВИЛА:
==================================

1. Можно рекомендовать ТОЛЬКО блюда из списка.
2. Нельзя изменять состав блюда.
3. Нельзя предлагать заменить ингредиенты.
4. НИКОГДА не перечисляй неподходящие блюда.
5. НИКОГДА не объясняй почему что-то не подходит — молча игнорируй.
6. Нельзя упоминать ID в тексте ответа.
7. Нельзя показывать состав, цену или категорию в тексте ответа.
8. Нельзя писать "заменить", "убрать", "без X".
9. Если ничего не подходит — честно скажи об этом.

==================================
ПРОВЕРКА АЛЛЕРГЕНОВ:
==================================
Перед рекомендацией проверь название и описание каждого блюда.
Если блюдо ТИПИЧНО содержит запрещённый продукт — оно ЗАПРЕЩЕНО.

Молочная группа: молоко, сливки, сыр, фета, творог, йогурт,
сметана, масло сливочное, моцарелла, пармезан, маскарпоне,
рикотта, крем-чиз, латте, капучино

Глютен: мука, паста, спагетти, хлеб, пшеница, тесто,
сухарики, савоярди, булочка

Яйца: яйца, майонез, меренга

Орехи: орехи, грецкие орехи, миндаль, арахис, фундук, кешью

Рыба: лосось, тунец, треска, сёмга, форель, анчоус

При малейшем сомнении — НЕ рекомендуй блюдо.

==================================
СТИЛЬ:
==================================
Дружелюбно, естественно.
2–3 предложения.
Без технических пояснений.
Пиши ТОЛЬКО название блюда и краткое описание своими словами.

==================================
ФОРМАТ ОТВЕТА:
==================================

Текст рекомендации.

В конце строго:
РЕКОМЕНДУЮ_ID: 4,10

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
            timeout=180
        )

        hf_messages = [{"role": "system", "content": system_prompt}] + conversation

        response = client.chat_completion(
            messages=hf_messages,
            max_tokens=2048,
            temperature=0.3
        )

        response_text = response.choices[0].message.content.strip()

        # Убираем <think> блок DeepSeek
        response_text = clean_deepseek_response(response_text)

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

        clean_message = re.sub(r"РЕКОМЕНДУЮ_ID:.*", "", response_text).strip()

        # =============================
        # ПОСТ-ФИЛЬТРАЦИЯ
        # =============================

        ALLERGEN_TRIGGERS = {
            "молочн": ["молоко", "сливки", "сыр", "фета", "творог", "йогурт",
                       "сметана", "масло сливочное", "моцарелла", "пармезан",
                       "маскарпоне", "рикотта", "крем-чиз", "латте", "капучино"],
            "молок":  ["молоко", "сливки", "сыр", "фета", "творог", "йогурт",
                       "сметана", "масло сливочное", "моцарелла", "пармезан",
                       "маскарпоне", "рикотта", "крем-чиз", "латте", "капучино"],
            "лактоз": ["молоко", "сливки", "сыр", "фета", "творог", "йогурт",
                       "сметана", "масло сливочное", "моцарелла", "пармезан",
                       "маскарпоне", "рикотта", "крем-чиз", "латте", "капучино"],
            "глютен": ["паста", "спагетти", "хлеб", "мука", "пшениц",
                       "сухарики", "савоярди", "булочка", "тесто"],
            "яиц":    ["яйц", "омлет", "майонез", "меренг"],
            "орех":   ["орех", "миндаль", "арахис", "фундук", "кешью"],
            "рыб":    ["лосось", "тунец", "треска", "сёмга", "форель", "анчоус"],
        }

        # Собираем всю историю пользователя
        all_user_text = " ".join(
            m["content"] for m in conversation if m["role"] == "user"
        ).lower()

        forbidden_ingredients = []
        for trigger, words in ALLERGEN_TRIGGERS.items():
            if trigger in all_user_text:
                forbidden_ingredients.extend(words)

        forbidden_ingredients = list(set(forbidden_ingredients))

        recommendations = []
        for item in menu_items:
            if item["id"] not in recommended_ids:
                continue

            if forbidden_ingredients:
                item_text = (
                    item['name'] + " " + (item.get('description') or "")
                ).lower()

                if any(w in item_text for w in forbidden_ingredients):
                    print(f"⚠️ Пост-фильтр убрал: {item['name']}")
                    continue

            recommendations.append(item)

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
