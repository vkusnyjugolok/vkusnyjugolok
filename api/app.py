import os
import traceback
import re
import json
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
# КЭШ СОСТАВОВ
# =============================

_ingredients_cache = {}

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
# ГЕНЕРАЦИЯ СОСТАВОВ ЧЕРЕЗ ИИ
# =============================

def generate_ingredients_for_menu(menu_items):
    if not menu_items:
        return {}

    client = InferenceClient(
        model=HF_MODEL,
        token=HF_TOKEN if HF_TOKEN else None,
        timeout=120
    )

    dishes_list = "\n".join([
        f"- {item['name']}: {item['description']}"
        for item in menu_items
    ])

    prompt = f"""Respond with ONLY a JSON object. No thinking, no explanation, no markdown.

For each dish list its typical ingredients in Russian.

Dishes:
{dishes_list}

Required format (pure JSON only):
{{
  "Паста Карбонара": "спагетти, бекон, яйца, пармезан, сливки",
  "Тирамису": "маскарпоне, яйца, кофе, савоярди, какао"
}}"""

    try:
        response = client.chat_completion(
            messages=[{"role": "user", "content": prompt}],
            max_tokens=2048,
            temperature=0.1
        )

        text = response.choices[0].message.content.strip()

        # Убираем <think> блок DeepSeek
        text = clean_deepseek_response(text)

        # Убираем markdown
        text = re.sub(r"```json|```", "", text).strip()

        # Ищем JSON даже если модель добавила текст вокруг
        json_match = re.search(r"\{.*\}", text, re.DOTALL)
        if json_match:
            text = json_match.group(0)

        ingredients = json.loads(text)
        print("✅ Составы сгенерированы:", list(ingredients.keys()))
        return ingredients

    except Exception as e:
        print(f"❌ Ошибка генерации составов: {e}")
        print(f"Ответ модели: {text if 'text' in locals() else 'нет ответа'}")
        return {}


def get_or_generate_ingredients(menu_items):
    global _ingredients_cache

    if not _ingredients_cache:
        print("🔄 Генерируем составы блюд...")
        _ingredients_cache = generate_ingredients_for_menu(menu_items)

    return _ingredients_cache


# =============================
# ПРОМПТ
# =============================

def build_system_prompt(menu_items):
    ingredients_map = get_or_generate_ingredients(menu_items)

    menu_text = ""
    for item in menu_items:
        ingredients = ingredients_map.get(item['name'], "состав не определён")
        menu_text += (
            f"[{item['id']}] {item['name']} | "
            f"{item['category']} | "
            f"Состав: {ingredients} | "
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
Перед рекомендацией проверь строку "Состав:" каждого блюда.
Если хоть один ингредиент относится к запрещённой группе — блюдо ЗАПРЕЩЕНО.

Молочная группа: молоко, сливки, сыр, фета, творог, йогурт,
сметана, масло сливочное, моцарелла, пармезан, маскарпоне,
рикотта, крем-чиз, бри, горгонзола

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
ФОРМАТ:
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
            timeout=120
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
        # PYTHON ПОСТ-ФИЛЬТРАЦИЯ
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

        ingredients_map = get_or_generate_ingredients(menu_items)

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
                item_ingredients = ingredients_map.get(item['name'], "").lower()
                item_text = (item['name'] + " " + item_ingredients).lower()

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
# ОБНОВЛЕНИЕ КЭША
# =============================

@app.route("/api/refresh-ingredients", methods=["POST"])
def refresh_ingredients():
    global _ingredients_cache
    _ingredients_cache = {}
    menu = get_menu_items()
    get_or_generate_ingredients(menu)
    return jsonify({"status": "ok", "count": len(_ingredients_cache)})


# =============================
# HEALTH
# =============================

@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "hf_model": HF_MODEL,
        "hf_token_set": bool(HF_TOKEN),
        "db_host_set": bool(DB_HOST),
        "ingredients_cached": len(_ingredients_cache)
    })


# =============================
# ЗАПУСК
# =============================

if __name__ == "__main__":
    print("🚀 Запуск сервера...")

    menu = get_menu_items()
    if menu:
        get_or_generate_ingredients(menu)
    else:
        print("⚠️ Меню пустое, составы не сгенерированы")

    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=False)
