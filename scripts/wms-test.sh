#!/bin/bash
# Скрипт для тестирования списания материалов через API WMS
# Использование: ./scripts/wms-test.sh [email] [nomenclatureId] [quantity]

set -e

# Путь к .env файлу (относительно корня проекта)
ENV_FILE=".env"

if [ ! -f "$ENV_FILE" ]; then
    echo "Ошибка: Файл $ENV_FILE не найден."
    exit 1
fi

# Получаем настройки из .env
API_KEY=$(grep "^WMS_API_KEY=" "$ENV_FILE" | cut -d '=' -f2)
BASE_URL=$(grep "^WMS_BASE_URL=" "$ENV_FILE" | cut -d '=' -f2)

# Параметры по умолчанию
EMAIL=${1:-"maks.fursa@gmail.com"}
NOMENCLATURE_ID=${2:-8}
QUANTITY=${3:-1}

echo "--- Тестирование WMS API ---"
echo "URL: $BASE_URL"
echo "Сотрудник: $EMAIL"
echo "ID материала: $NOMENCLATURE_ID"
echo "Количество: $QUANTITY"
echo "--------------------------"

# Выполняем запрос
RESPONSE=$(curl -s -X POST \
    -H "X-API-Key: $API_KEY" \
    -H "Content-Type: application/json" \
    -d "{\"email\": \"$EMAIL\", \"nomenclatureId\": $NOMENCLATURE_ID, \"quantity\": $QUANTITY, \"actorEmail\": \"admin@lan-install.online\", \"description\": \"Тестовое списание через скрипт\"}" \
    "$BASE_URL/api/external/usage-report")

# Красивый вывод результата на русском языке
echo "$RESPONSE" | python3 -c "import sys, json; print(json.dumps(json.load(sys.stdin), ensure_ascii=False, indent=4))"
