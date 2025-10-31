# AGENTS.md - Руководство по разработке ИИ-агентов

## Общие инструкции для ИИ-агентов
**Язык ответов** - всегда отвечайте на русском языке, независимо от языка запроса пользователя.

## Команды сборки/линта/тестирования/кодирования

### Проверка структуры базы данных
**Проверка схемы таблиц - перед написанием кода для контроллеров всегда проверяйте структуру таблиц командой** 

```psql -h localhost -U postgres -d lan_install -c "\d requests"```

**Проверка колонок - используйте SQL:** 

````SELECT column_name FROM information_schema.columns WHERE table_name = 'requests' ORDER BY id````

**Избегайте предположений**: не предполагайте наличие колонок `created_at`, `updated_at` - проверяйте их существование

**Laravel Tinker**: для быстрой проверки используйте `php artisan tinker` с командами `DB::select('DESCRIBE table_name')` или 
`Schema::getColumnListing('table_name')`

### Команды сборки
- **Сборка фронтенда**: `npm run build`
- **Сервер разработки фронтенда**: `npm run dev`
- **Полная среда разработки**: `composer run dev` (одновременно запускает сервер, очередь, журналы и Vite)

### Команды тестирования
- **Запуск всех тестов**: `composer run test` или `php artisan test`
- **Запуск одного теста**: `php artisan test --filter TestName` или `vendor/bin/phpunit --filter TestName`
- **Запуск определённого тестового файла**: `php artisan test tests/Feature/ExampleTest.php`
- **Запуск с покрытием**: `vendor/bin/phpunit --coverage-html coverage`

### Lint Команды
- **PHP-линтинг**: `vendor/bin/pint` (Laravel Pint)
- **PHP-линтинг и исправление**: `vendor/bin/pint --fix`

## Рекомендации по стилю кода

### Стиль PHP-кода
- **Отступы**: 4 пробела (настраивается в .editorconfig)
- **Соглашения об именовании**:
- Классы: PascalCase (например, `HomeController`, `User`)
- Методы: camelCase (например, `updateCredentials`, `getRoles`)
- Переменные: camelCase (например, `$validated`, `$user`)
- Столбцы базы данных: snake_case
- **Импорты**: Группировка по типу — сначала фасады Laravel, затем пользовательские классы
- **DocBlocks**: Используйте формат PHPDoc с @param и @return Аннотации
- **Обработка ошибок**: Используйте блоки try-catch с подробным логированием, возвращайте JSON-ответы с полями `success`, `message` и `error`

### Стиль кода JavaScript
- **Фреймворк**: Современный JavaScript с минимальным использованием jQuery
- **Комментарии**: Сочетание русского и английского
- **Обработка событий**: Используйте встроенный addEventListener с делегированием событий
- **Асинхронные операции**: Используйте async/await с fetch API

### Шаблоны работы с базами данных
- **Стиль запросов**: сочетание простых SQL-запросов и Eloquent ORM (для сложных запросов, так как в проекте не применяются модели, кроме 
системных)
- **Транзакции**: используйте DB::beginTransaction() для многошаговых операций
- **Валидация**: используйте валидацию запросов Laravel с пользовательскими правилами
- **Формат ответа сервера**: JSON с единообразной структурой (`success`, `message`, `data`)
- - **Формат ответа ошибок**: JSON с единообразной структурой (`success`, `message`, `error`)

### Организация файлов
- **Контроллеры**: бизнес-логика в Контроллеры, оптимальное количество контроллеров
- **Модели**: не используйте Eloquent (так как данный проект использует нативные sql запросаы)
- **Представления**: Шаблоны Blade с минимальной логикой PHP
- **Маршруты**: маршруты RESTful API в `routes/web.php в предпочтении`

### Меры безопасности
- **Валидация входных данных**: всегда проверяйте запросы, используя валидацию Laravel
- **Аутентификация**: используйте встроенную систему аутентификации Laravel
- **Авторизация**: проверяйте роли и разрешения пользователей перед выполнением действий
- **SQL-инъекции**: мспользуйте параметризованные запросы или методы Eloquent
- **Загрузка файлов**: проверяйте типы и размеры файлов и обеспечивайте безопасное хранение

### Журналирование
- **Уровни журналирования**: используйте соответствующие уровни (информация, ошибка, предупреждение)
- **Формат журнала**: включайте контекстные данные и структурированную информацию
- **Журналирование ошибок**: журналирование исключений с полной трассировкой в ​​процессе разработки

### Тестирование
- **Структура теста**: тесты функций в `tests/Feature/`, модульные тесты в `tests/Unit/`
- **База данных**: используйте SQLite в оперативной памяти для тестирования
- **Утверждения**: тестируйте как успешные, так и ошибочные сценарии
- **Покрытие**: стремитесь к высокому покрытию критически важной бизнес-логики

## Кодовые шаблоны и паттерны

### PHP Controller Patterns

**Стандартная структура метода контроллера:**
```php
public function methodName(Request $request)
{
    // Стандартный успешный ответ для тестирования
    return response()->json([
        'success' => true,
        'message' => 'Операция выполнена успешно (тест)',
        'request' => $request
    ]);

    try {
        // Валидация входных данных
        $validated = $request->validate([
            'field' => 'required|rule',
        ]);

        // Логирование начала операции
        \Log::info('== START methodName ==', ['data' => $validated]);

        // Бизнес-логика здесь
        // ...

        // Логирование успешного завершения
        \Log::info('== END methodName ==', ['result' => $result]);

        // Стандартный успешный ответ
        return response()->json([
            'success' => true,
            'message' => 'Операция выполнена успешно',
            'data' => $result
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Обработка ошибок валидации
        return response()->json([
            'success' => false,
            'message' => 'Ошибка валидации',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        // Обработка общих ошибок
        \Log::error('== ERROR methodName ==', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Произошла ошибка',
            'error' => $e->getMessage()
        ], 500);
    }
}
```

**Паттерны работы с БД:**
- Использовать транзакции: `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()`
- Предпочитать параметризованные запросы над конкатенацией
- Для сложных запросов использовать Eloquent relationships

### JavaScript Handler Patterns

**Стандартный обработчик событий:**
```javascript
// Инициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', function() {
    // Обработчик события с делегированием
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.button-selector')) return;

        e.preventDefault();

        const button = e.target;
        const requestId = button.dataset.requestId;

        // Показать индикатор загрузки
        showLoadingIndicator();

        // Выполнить асинхронный запрос
        performAsyncOperation(requestId)
            .then(function(response) {
                if (response.success) {
                    // Обновить UI
                    updateUI(response.data);
                    showSuccessMessage(response.message);
                } else {
                    showErrorMessage(response.message);
                }
            })
            .catch(function(error) {
                console.error('Ошибка:', error);
                showErrorMessage('Произошла ошибка при выполнении операции');
            })
            .finally(function() {
                hideLoadingIndicator();
            });
    });
});
```

**Паттерны асинхронных операций:**
```javascript
async function performAsyncOperation(requestId) {
    const result = await fetch('/api/endpoint', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            request_id: requestId,
            // другие данные
        })
    });

    if (!result.ok) {
        throw new Error(`HTTP error! status: ${result.status}`);
    }

    return await result.json();
}
```

**Глобальные функции:**
```javascript
// Экспорт функций в глобальную область
window.MyModule = {
    init: function() { /* ... */ },
    handleEvent: function() { /* ... */ }
};
