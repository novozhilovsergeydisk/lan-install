# Механизм показа и обновления оборудования в колонке «Бригада»

## 1. Хранилище — таблица `request_equipment`

Каждая строка — «снимок» одного предмета, взятого со склада:

| Колонка | Назначение |
|---|---|
| `request_id` | Какой заявке принадлежит |
| `kind` | `tool` (инструмент H-*) или `vehicle` (авто) |
| `label` | Отображаемая строка: `H-7` или `Т499АК185 Mercedes-Benz Sprinter` |
| `holder_emp_id` / `holder_fio` | Кто из бригады держит |
| `wms_ref` | Сырое значение склада (инв. номер / госномер) |
| `source` | `warehouse` (по умолчанию) |
| `created_at` | Время создания снимка |

Одна заявка → много строк (у каждого члена бригады своё оборудование).

## 2. Опрос склада — `WmsEquipmentService`

### `fetchWarehouseRows($requestId)` (`WmsEquipmentService.php:115`)

1. SQL-запросом находит **всех участников бригады** (бригадир + члены) → получает их `email`
2. Для каждого участника вызывает API склада:
   ```
   GET {WMS_BASE_URL}/api/external/user-equipment?email={email}
   ```
   С заголовком `X-API-Key`, таймаут 5 сек.
3. Парсит ответ:
   - `data.tools[]` → `kind=tool`, `label=$inventoryNumber`
   - `data.vehicles[]` → `kind=vehicle`, `label=trim($plateNumber . ' ' . $model)`

### `captureSnapshotForRequest($requestId)` (`WmsEquipmentService.php:27`)

- **Удаляет** все старые строки для заявки
- **Вставляет** свежие из API
- Полная перезапись, не мерж
- Обёрнуто в try/catch — ошибки логируются, но не прерывают выполнение

### `refreshTodayBestEffort()` (`WmsEquipmentService.php:68`)

Живое обновление «по требованию»:

1. Проверяет existence таблицы `request_equipment`
2. **Троттл 5 сек** через Cache (`wms_equipment_refreshed_at`) — частые F5 не долбят склад
3. Читает конфиг `WMS_API_KEY` / `WMS_BASE_URL` — если пусто, выходит
4. **Пинг склада** (GET `/api/external/warehouses`, таймаут 2 сек) — недоступен → выходит
5. Находит **все открытые сегодня заявки**: `DATE(execution_date) = CURRENT_DATE`, `status_id NOT IN (4,5,6,7)`, `brigade_id IS NOT NULL`
6. Вызывает `captureSnapshotForRequest()` для каждой
7. Ставит кэш-метку `wms_equipment_refreshed_at` с TTL 3600 сек

## 3. Три триггера обновления

### Триггер A: Загрузка страницы

```
Пользователь открывает /
  → HomeController::index() (HomeController.php:1125)
    → refreshTodayBestEffort()
      → Кэш < 5 сек? → пропустить
      → Пинг склада (2 сек таймаут) → недоступен? → выйти
      → Найти ВСЕ открытые сегодня заявки
      → captureSnapshotForRequest() для каждой
    → Читать request_equipment → отдать в Blade
```

То же при AJAX-смене даты на сегодня (`getRequestsByDate`, HomeController.php:2023).

### Триггер B: Крон

```php
// routes/console.php:17
Schedule::command('wms:refresh-equipment')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wms-equipment.log'));
```

Раз в час опрашивает склад для **всех открытых сегодняшних заявок**. Основной механизм на сервере — не зависит от загрузки страниц.

Команда `wms:refresh-equipment` (`RefreshRequestEquipment.php`):
- Находит открытые сегодня заявки с бригадой
- Вызывает `captureSnapshotForRequest()` для каждой

### Триггер C: Закрытие заявки

```php
// HomeController.php:2771 (после commit транзакции)
$hasEquipSnapshot = Schema::hasTable('request_equipment')
    && DB::table('request_equipment')->where('request_id', $id)->exists();
if (! $hasEquipSnapshot) {
    app(WmsEquipmentService::class)->captureSnapshotForRequest((int) $id);
}
```

**Заморозка при закрытии:**
- Если снимок **уже есть** (наполнило live-обновление за день) — **не трогаем**. Это «липкий» снимок: `captureSnapshotForRequest()` не стирает данные, если склад вернул пусто (бригада сдала инвентарь). Снимок остаётся с последними показанными данными.
- Если снимка **нет** (заявка закрыта до первого обновления) — создаём заморозку через опрос склада.

> **Важно:** «липкий» снимок защищает от затирания между «сдали инвентарь» и «закрыли заявку». Без этой логики live-обновление (троттл 5 сек) или крон (раз в час) стёрли бы снимок при пустом ответе склада, и при закрытии нечего было бы замораживать.

## 4. Логика отображения

### `index()` — серверный рендер при первой загрузке

- `refreshTodayBestEffort()` обновляет снимки
- Читает `request_equipment` для всех заявок
- Для **каждой** сегодняшней заявки equipment показывается (фильтрации по статусу нет в `index()`)

### `getRequestsByDate()` — AJAX при смене даты

```php
// HomeController.php:2052
$showEquip = $isTodayView || ((int) ($request->status_id ?? 0) === 4);
```

| Сценарий | Показываем? |
|---|---|
| **Сегодня** (главная / API) | У **всех** заявок, где есть снимок |
| **Прошлое/ будущее**, открытая заявка | **Нет** — оборудование неактуально |
| **Прошлое/будущее**, закрытая (status=4) | **Да** — замороженный снимок навсегда |

## 5. Рендер в интерфейсе

### Blade (`welcome.blade.php:632-644`)

Серверный рендер при первой загрузке страницы:

```blade
@php
    $eq = $request->equipment ?? ['tools' => [], 'vehicles' => []];
@endphp
@if(!empty($eq['tools']) || !empty($eq['vehicles']))
    <div style="margin-top:6px; font-size:0.8rem; line-height:1.3;">
        @if(!empty($eq['tools']))
            <div><span style="color:#6b7280;">Инструмент:</span> <strong>{{ implode(', ', $eq['tools']) }}</strong></div>
        @endif
        @if(!empty($eq['vehicles']))
            <div><span style="color:#6b7280;">Авто:</span> <strong>{{ implode(', ', $eq['vehicles']) }}</strong></div>
        @endif
    </div>
@endif
```

### JavaScript (`handler.js:1069-1081`)

Клиентский рендер при AJAX-смене даты — та же логика, тот же HTML:

```javascript
const equipment = request.equipment || { tools: [], vehicles: [] };
if ((equipment.tools && equipment.tools.length) || (equipment.vehicles && equipment.vehicles.length)) {
    let equipmentHtml = '<div style="margin-top:6px; font-size:0.8rem; line-height:1.3;">';
    if (equipment.tools && equipment.tools.length) {
        equipmentHtml += `<div><span style="color:#6b7280;">Инструмент:</span> <strong>${equipment.tools.join(', ')}</strong></div>`;
    }
    if (equipment.vehicles && equipment.vehicles.length) {
        equipmentHtml += `<div><span style="color:#6b7280;">Авто:</span> <strong>${equipment.vehicles.join(', ')}</strong></div>`;
    }
    equipmentHtml += '</div>';
    brigadeMembers += equipmentHtml;
}
```

## 6. Схема потока данных

```
Склад API ──► WmsEquipmentService ──► request_equipment (БД)
                                          │
                     ┌────────────────────┤
                     │                    │
              index() /              closeRequest()
          getRequestsByDate()        (заморозка)
          (live обновление)          если снимка нет
                     │
                     ▼
            Blade / handler.js
            (отрисовка в колонке «Бригада»)
```

Три входа (live-обновление, крон, закрытие) → одно хранилище (`request_equipment`) → два рендера (Blade при загрузке, JS при смене даты).

## 7. Конфигурация

- `WMS_API_KEY` — ключ API склада (в `.env`)
- `WMS_BASE_URL` — URL API склада (в `.env`, по умолчанию `https://stock.lan-install.online`)
- Крон: `wms:refresh-equipment` — раз в час, `withoutOverlapping`
- Логи крона: `storage/logs/wms-equipment.log`
- Логи ошибок WMS: `storage/logs/laravel.log` (уровень WARNING/ERROR)
