#!/bin/bash

# Настройки
DB_NAME="lan_install"
DB_USER="postgres"
BACKUP_FILE="BACKUPS/lan_install_backup_$(date +%Y%m%d_%H%M).dump"  # Добавлены часы и минуты

# Функция для выполнения SQL-запросов
execute_sql() {
    psql -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 -c "$1" || {
        echo "🔴 Ошибка при выполнении SQL: $1"
        exit 1
    }
}

# 1. Создать резервную копию
echo "🔵 Создаем резервную копию базы данных..."
pg_dump -U "$DB_USER" -d "$DB_NAME" -F c -f "$BACKUP_FILE" || {
    echo "🔴 Ошибка при создании резервной копии!"
    exit 1
}
echo "🟢 Резервная копия сохранена в: $BACKUP_FILE"

# 2. Удалить дублирующихся сотрудников (кроме proxima)
echo "🔵 Удаляем дубликаты сотрудников..."
execute_sql "
BEGIN;

-- Удаляем связи из brigade_members
DELETE FROM brigade_members 
WHERE employee_id IN (SELECT id FROM employees WHERE user_id = 2 AND id != 2);

-- Обнуляем operator_id в заявках
UPDATE requests 
SET operator_id = NULL 
WHERE operator_id IN (SELECT id FROM employees WHERE user_id = 2 AND id != 2);

-- Удаляем сотрудников
DELETE FROM employees 
WHERE user_id = 2 AND id != 2;

COMMIT;
"

# 3. Назначаем операторов для заявок
echo "🔵 Назначаем операторов для заявок..."
execute_sql "
BEGIN;

-- Назначаем оператором сотрудника с id=1
UPDATE requests 
SET operator_id = 1 
WHERE operator_id IS NULL;

-- Устанавливаем NOT NULL для operator_id
ALTER TABLE requests 
ALTER COLUMN operator_id SET NOT NULL;

COMMIT;
"

# 4. Обрабатываем дубликаты user_id перед добавлением UNIQUE-ограничения
echo "🔵 Обрабатываем дубликаты user_id..."
execute_sql "
-- Обнуляем дубликаты (кроме первой записи)
UPDATE employees e1
SET user_id = NULL
FROM (
    SELECT user_id, MIN(id) as min_id
    FROM employees
    WHERE user_id IS NOT NULL
    GROUP BY user_id
    HAVING COUNT(*) > 1
) e2
WHERE e1.user_id = e2.user_id AND e1.id != e2.min_id;
"

# 5. Добавляем UNIQUE-ограничение для user_id
echo "🔵 Добавляем UNIQUE-ограничение для user_id..."
execute_sql "
ALTER TABLE employees 
ADD CONSTRAINT uq_employee_user UNIQUE (user_id);
"

# 6. Проверяем результаты
echo "🔵 Проверяем результаты..."
execute_sql "
-- Сотрудники с user_id=2
SELECT id, fio, user_id FROM employees WHERE user_id = 2;

-- Заявки без операторов (должно быть 0)
SELECT COUNT(*) FROM requests WHERE operator_id IS NULL;

-- Проверяем ограничения
SELECT conname, conkey FROM pg_constraint 
WHERE conrelid = 'employees'::regclass AND conname = 'uq_employee_user';
"

echo "🟢 Все операции успешно завершены!"
