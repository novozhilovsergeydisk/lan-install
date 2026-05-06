# Карта системы комментариев и фото — lan-install.online

## 1. Контроллеры и методы

### HomeController (`app/Http/Controllers/HomeController.php`)

| Метод | Строки | Назначение | Фото | Файлы |
|--------|-------|--------------|------|--------|
| `addComment(Request $request)` | 1223–1620 | Создание комментария + прикрепление фото и файлов | Да (photos[]) | Да (files[]) |
| `updateComment($id, Request $request)` | 3197–3281 | Редактирование текста комментария (сохранение истории) | Нет | Нет |
| `getComments(Request $request)` | (в `form-handlers.js` упоминается) | Получение списка комментариев заявки | — | — |

### CommentPhotoController (`app/Http/Controllers/CommentPhotoController.php`)

| Метод | Строки | Назначение |
|--------|-------|--------------|
| `index($commentId)` | 17–67 | Получить фотографии, привязанные к комментарию (через `comment_photos` + `photos`) |
| `getCommentFiles($commentId)` | 81–133 | Получить файлы, привязанные к комментарию (через `comment_files` + `files`) |
| `uploadExcel(Request $request)` | 135–472 | Загрузка Excel-файла с адресами (архивная функция) |
| `downloadAllPhotos(Request $request)` | 474–532 | Фоновая генерация ZIP-архива всех фото по заявке |
| `downloadArchiveFile($requestId)` | 537–555 | Скачивание готового ZIP-архива |

---

## 2. Валидация (inline, без FormRequest-классов)

### `addComment` — валидация (HomeController.php:1256–1295)

```php
$validated = $request->validate([
    'request_id'    => 'required|integer|exists:requests,id',
    'comment'       => 'required|string|max:1000',
    'photos'        => 'nullable|array|max:100',
    'photos.*'      => 'file|max:512000|mimes:jpg,jpeg,png,gif,webp,bmp,tiff,heic,heif',
    'files'         => 'nullable|array|max:100',
    'files.*'       => [
        'file',
        'max:512000',
        function ($attribute, $value, $fail) {
            $allowedMimeTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff',
                'image/heic', 'image/heif', 'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'text/html', 'application/zip', 'application/x-rar', 'application/x-rar-compressed',
                'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-matroska',
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
            ];
            // Для .txt разрешаем text/html
            if (strtolower($value->getClientOriginalExtension()) === 'txt' && $value->getMimeType() === 'text/html') {
                return true;
            }
            if (! in_array($value->getMimeType(), $allowedMimeTypes)) {
                $errorMessage = "Файл {$value->getClientOriginalName()} имеет недопустимый тип...";
                $fail($errorMessage);
            }
        },
    ],
    '_token' => 'required|string',
]);
```

### `updateComment` — валидация (HomeController.php:3197–3208)

```php
$content = $request->input('content'); // Только текст
// Валидация минимальная — проверка наличия параметра 'content'
// Права доступа: админ ИЛИ автор И комментарий создан сегодня
```

---

## 3. Структура таблиц БД (по коду контроллеров)

### `comments`
| Поле | Тип | Описание |
|------|------|-----------|
| `id` | bigint | PK |
| `comment` | text | Текст комментария |
| `created_at` | timestamp | Дата создания |

### `request_comments` (связь заявка ↔ комментарий)
| Поле | Тип | Описание |
|------|------|-----------|
| `request_id` | integer | FK → requests.id |
| `comment_id` | integer | FK → comments.id |
| `user_id` | integer | FK → users.id (автор) |
| `created_at` | timestamp | Дата привязки |

### `photos` (фотографии)
| Поле | Тип | Описание |
|------|------|-----------|
| `id` | bigint | PK |
| `path` | varchar | Путь: `images/<filename>` |
| `original_name` | varchar | Оригинальное имя файла |
| `file_size` | integer | Размер в байтах |
| `mime_type` | varchar | image/jpeg и т.д. |
| `width` | integer | Ширина (для изображений) |
| `height` | integer | Высота |
| `created_by` | integer | FK → users.id |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `comment_photos` (связь комментарий ↔ фото)
| Поле | Тип | Описание |
|------|------|-----------|
| `comment_id` | integer | FK → comments.id |
| `photo_id` | integer | FK → photos.id |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `request_photos` (связь заявка ↔ фото, для фотоотчетов)
| Поле | Тип | Описание |
|------|------|-----------|
| `request_id` | integer | FK → requests.id |
| `photo_id` | integer | FK → photos.id |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `files` (произвольные файлы)
| Поле | Тип | Описание |
|------|------|-----------|
| `id` | bigint | PK |
| `path` | varchar | Путь: `files/<filename>` |
| `original_name` | varchar | Оригинальное имя |
| `file_size` | integer | Размер |
| `mime_type` | varchar | MIME-тип |
| `extension` | varchar | Расширение |
| `created_by` | integer | FK → users.id |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `comment_files` (связь комментарий ↔ файлы)
| Поле | Тип | Описание |
|------|------|-----------|
| `comment_id` | integer | FK → comments.id |
| `file_id` | integer | FK → files.id |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `comment_edits` (история редактирования комментариев)
| Поле | Тип | Описание |
|------|------|-----------|
| `comment_id` | integer | FK → comments.id |
| `old_comment` | text | Старый текст |
| `edited_by_user_id` | integer | FK → users.id |
| `edited_at` | timestamp | |

---

## 4. Blade-шаблоны (форма комментария + фото)

### `resources/views/welcome.blade.php`

#### Модальное окно комментариев (строки 3046–3126)

| Элемент | ID / Класс | Назначение |
|-----------|--------------|--------------|
| Форма | `addCommentForm` | Отправка комментария + фото/файлы |
| Поле `request_id` | `commentRequestId` (hidden) | ID заявки |
| Textarea | `commentField` | Текст комментария (required) |
| Загрузка фото | `photoUpload` (name=`photos[]`) | Мульти-загрузка фото |
| Загрузка файлов | `commentFilesInput` (name=`files[]`) | Мульти-загрузка любых файлов |
| Контейнер фото | `photoPreviewNew` | Предпросмотр выбранных фото |
| Контейнер списка | `commentsContainer` | Список комментариев (грузится AJAX) |
| Кнопка "Фотоотчет" | `.add-photo-btn` | Открыть `addPhotoModal` |
| Кнопка "Показать все фото" | `showPhotosBtn` | Показать `photoReportContainer` |
| Кнопка "Скачать ZIP" | `.download-all-photos-btn` | Скачать архив всех фото |

#### Модальное окно добавления фотоотчета (строки 5365–5392)

| Элемент | ID | Назначение |
|-----------|----|--------------|
| Форма | `photoReportForm_` | Загрузка фотоотчета (в коде закомментировано) |
| Поле `request_id` | `photoRequestId` | ID заявки |
| Input файл | `photoUpload_` (name=`photos[]`) | Фото для отчета |

#### Модальное окно редактирования комментария (строки 5478–5500)

| Элемент | ID | Назначение |
|-----------|----|--------------|
| Форма | `editCommentForm` | Редактирование текста |
| Поле `comment_id` | `editCommentId` | ID комментария |
| Textarea | `editCommentContent` | Новый текст |
| Кнопка "Сохранить" | `saveCommentChangesBtn` | Отправка изменений |

---

## 5. JavaScript-обработчики

### `public/js/form-handlers.js`

| Функция | Строки | Роль |
|----------|-------|------|
| `initializePage()` | (вызывается из `init-handlers.js`) | Инициализация всех обработчиков заявок |
| `initFormHandlers()` | 46–272 | Обработка кнопок редактирования, закрытия, открытия заявок |
| `initSaveEmployeeChanges()` | — | Сохранение изменений сотрудника |
| `initCommentEditHandlers()` | — | Редактирование комментариев (открытие `editCommentModal`) |

### `public/js/modals.js`

| Функция | Строки | Роль |
|----------|-------|------|
| `initPhotoReportModal()` | 258–648 | Обработка `addPhotoModal`: выбор файлов, предпросмотр, отправка `FormData` на `/api/requests/photo-report` |
| `initAdditionalTaskModal()` | 45–253 | Обработка формы "Дополнительное задание" (тоже может содержать комментарий) |

### `public/js/comments.js`

| Функция | Строки | Роль |
|----------|-------|------|
| `loadComments(requestId)` | 10–98 | AJAX GET `/api/requests/{request}/comments`, рендеринг списка |
| `updateCommentsBadge(requestId)` | 104–131 | Обновление бейджа с количеством комментариев |

### `public/js/init-handlers.js`

| Строки | Роль |
|-------|------|
| 46–78 | Вызов `initFormHandlers()`, `initCommentEditHandlers()`, `initPhotoReportModal()` и др. при `DOMContentLoaded` |

---

## 6. Конфигурация файлового хранилища (`config/filesystems.php`)

| Диск | root | Назначение |
|------|------|-----------|
| `local` | `storage_path('app/private')` | Внутреннее хранилище (не публично) |
| `public` | `storage_path('app/public')` | **Публичные файлы** — именно сюда сохраняются фото и файлы |
| `private` | `storage_path('app/private')` | Приватные файлы |
| `s3` | (AWS) | Облачное хранилище (опционально) |

### Пути сохранения (из `HomeController::addComment`)

| Тип файла | Диск | Путь в `storage/app/public/` | URL через asset() |
|-----------|------|--------------------------|----------------------|
| Фото | `public` | `images/<time>_<random>_<orig_name>` | `storage/images/<filename>` |
| Файлы | `public` | `files/<time>_<random>_<orig_name>` | `storage/files/<filename>` |

**Важно:** Для корректной работы публичных файлов должна быть выполнена команда:
```bash
php artisan storage:link
```

---

## 7. Навигация по файлам проекта

| Файл | Роль | Ключевые строки |
|------|------|-------------------|
| `app/Http/Controllers/HomeController.php` | Основной контроллер: `addComment`, `updateComment` | 1223–1620, 3197–3281 |
| `app/Http/Controllers/CommentPhotoController.php` | Работа с фото/файлами комментариев | 1–556 |
| `config/filesystems.php` | Конфигурация дисков хранения | 1–88 |
| `resources/views/welcome.blade.php` | Blade-шаблон: `commentsModal`, `addPhotoModal`, `editCommentModal` | 3046–3126, 5365–5392, 5478–5500 |
| `public/js/modals.js` | Обработка модальных окон (фото, доп. задание) | 1–648 |
| `public/js/comments.js` | Загрузка и отображение комментариев | 1–133 |
| `public/js/form-handlers.js` | Инициализация всех обработчиков форм | 1–500+ |
| `public/js/init-handlers.js` | Точка входа JS: запускает все `init*()` | 1–505 |

---

## 8. Схема взаимодействия (упрощенная)

```
[Пользователь] → [Blade: addCommentForm] → [JS: modals.js / form-handlers.js]
                                 ↓
                     POST /requests/comment  (FormData: comment, photos[], files[])
                                 ↓
                    [HomeController::addComment]
                       ├─ Валидация (inline)
                       ├─ DB::insert → comments (текст)
                       ├─ DB::insert → request_comments (связь с заявкой)
                       ├─ Сохранение файлов в storage/app/public/images/ и /files/
                       ├─ DB::insert → photos / files
                       └─ DB::insert → comment_photos / comment_files
                                 ↓
                     Response JSON { success, comments, commentId }
```

---

## 9. Примечания

1. **Модели Eloquent не используются** — проект работает на прямых SQL-запросах через `DB::table()`, `DB::insert()`, `DB::select()`.
2. **FormRequest-классов нет** — вся валидация inline через `$request->validate()` в контроллерах.
3. Фото и файлы сохраняются **только через `addComment`** (при создании комментария). Редактирование (`updateComment`) меняет только текст.
4. Связь "комментарий ↔ фото" реализована через промежуточные таблицы `comment_photos` и `request_photos`.
5. История правок комментариев хранится в `comment_edits` (сохраняется старый текст, кто и когда изменил).
6. ZIP-архив всех фото по заявке генерируется **фоново** (через `exec(nohup php artisan archive:create ...)`) — статус проверяется AJAX-запросами.
