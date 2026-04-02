FROM python:3.11-slim

WORKDIR /app

# Копируем зависимости и устанавливаем
COPY api/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Копируем код API
COPY api/app.py .

# Переменные окружения (переопределяются в Render)
ENV PORT=5000
ENV HF_TOKEN=""
ENV HF_MODEL="mistralai/Mistral-7B-Instruct-v0.3"

EXPOSE ${PORT}

CMD gunicorn app:app --bind 0.0.0.0:${PORT} --workers 2 --timeout 120
