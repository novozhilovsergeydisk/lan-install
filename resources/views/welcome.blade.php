<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система управления заявками</title>
    <style>
        /* Стили для выпадающего списка адресов */
    </style>
    <!-- Bootstrap 5 CSS -->
    <link href="{{ asset('css/bootstrap.css') }}" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/table-styles.css') }}" rel="stylesheet">
    <link href="{{ asset('css/dark-theme.css') }}" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.png') }}">

    <!-- Проверка загрузки Bootstrap -->
    <script>
        // Проверяем, загружен ли Bootstrap
        window.addEventListener('load', function () {
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap не загружен!');
                // Показываем уведомление пользователю
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger position-fixed top-0 start-0 w-100 rounded-0 m-0';
                alertDiv.style.zIndex = '2000';
                alertDiv.innerHTML = `
                    <div class="container">
                        <strong>Ошибка загрузки!</strong> Не удалось загрузить необходимые компоненты Bootstrap.
                        Пожалуйста, обновите страницу или проверьте подключение к интернету.
                    </div>`;
                document.body.prepend(alertDiv);
            } else {
                // console.log('Bootstrap успешно загружен:', bootstrap);
            }
        });
    </script>
    <!-- Стили для темной темы вынесены в отдельный файл dark-theme.css -->
</head>

<body>
<div id="app-container" class="container-fluid g-0">
    <div id="main-layout" class="row g-0" style="min-height: 100vh;">
        <!-- Left Sidebar with Calendar -->

        <!--
        <div id="sidebar" class="col-auto sidebar p-3">

        </div>
        -->

        <!-- Main Content -->
        <div id="main-content" class="main-content">
            <div id="content-wrapper" class="container-fluid position-relative" style="min-height: 100vh; overflow-x: hidden;">
                <div id="header-section" class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="mb-0">Система управления заявками</h1>
                        @if(isset($user))
                            <div class="text-success small mt-1 fw-bold">
                                Авторизован: {{ $user->name }}
                            </div>
                        @endif
                    </div>
                    @if (session('success'))
                        <div style="color: green; font-weight: bold;">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="d-flex align-items-center">
                        <!-- Theme Toggle -->
                        <div class="theme-toggle me-3" id="themeToggle">
                            <i class="bi bi-sun theme-icon" id="sunIcon"></i>
                            <i class="bi bi-moon-stars-fill theme-icon d-none" id="moonIcon"></i>
                        </div>

                        <!-- Logout Button -->
                        <form action="{{ route('logout') }}" method="POST" class="mb-0">
                            @csrf
                            <button type="submit" id="logout-button" class="btn btn-outline-danger btn-sm px-3">
                                <i class="bi bi-box-arrow-right me-1"></i>Выход
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="mainTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="requests-tab" data-bs-toggle="tab"
                                data-bs-target="#requests" type="button" role="tab">Заявки
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="teams-tab" data-bs-toggle="tab" data-bs-target="#teams"
                                type="button" role="tab">Бригады
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses"
                                type="button" role="tab">Адреса
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users"
                                type="button" role="tab">Пользователи
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports"
                                type="button" role="tab">Отчеты
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="mainTabsContent">
                    <div class="tab-pane fade show active" id="requests" role="tabpanel">
                        <h4>Заявки</h4>

                        <!-- Filter Section -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex" style="max-width: 100%;">
                                    <div id="request-filters" class="d-flex align-items-center"
                                         style="height: 2rem; border: 1px solid var(--card-border, #dee2e6); border-radius: 0.25rem 0 0 0.25rem; padding: 0 0.5rem; background-color: var(--card-bg, #ffffff);">
                                        <label class="me-2 mb-0">Фильтр заявок по:</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="filter-statuses">
                                            <label class="form-check-label" for="filter-statuses">статусам</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="filter-teams">
                                            <label class="form-check-label" for="filter-teams">бригадам</label>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="reset-filters-button"
                                            style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Сброс
                                    </button>
                                </div>
                                <button type="button" class="btn btn-primary" id="new-request-button"
                                        data-bs-toggle="modal" data-bs-target="#newRequestModal">
                                    <i class="bi bi-plus-circle me-1"></i>Новая заявка
                                </button>
                            </div>
                        </div>

                        <!-- Calendar and Status Buttons -->
                        <div class="pt-4 ps-4 pb-0 d-flex align-items-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-3 me-2" id="btn-open-calendar">
                                <i class="bi bi-calendar me-1"></i>Календарь
                            </button>
                            <div id="status-buttons" class="d-flex flex-wrap gap-2">
                                <!-- Кнопки статусов будут добавлены через JavaScript -->
                            </div>
                        </div>

                        <!-- Calendar Container (initially hidden) -->
                        <div id="calendar-content" class="max-w-400 p-4 hide-me">
                            <div id="datepicker"></div>
                        </div>

                        <!-- Контейнер таблицы заявок (всегда отображается) -->
                        <div id="requests-table-container" class="table-responsive mt-4 t-custom">
                            <!-- Легенда статусов -->
                            @if (!empty($request_statuses))
                                <div class="p-4">
                                    <div class="d-flex flex-column gap-2">
                                        @foreach ($request_statuses as $status)
                                            <div class="d-flex align-items-center rounded-3 bg-gray-200 dark:bg-gray-700">
                                                <div class="me-3 w-7 h-7 rounded-sm"
                                                     style="width: 8rem; height: 2rem; background-color: {{ $status->color }};">
                                                </div>
                                                <span>{{ $status->name }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                                <table class="table table-hover align-middle mb-0" style="min-width: 992px; margin-bottom: 0;">
                                    <thead class="bg-dark">
                                    <tr>
                                        <th style="width: 1rem;"></th>
                                        <th style="width: 1rem;"></th>
                                        <th style="width: 10rem;">Дата</th>
                                        <th>Комментарий</th>
                                        <th style="width: 15rem;">Адрес/Телефон</th>
                                        <th>Оператор/Создана</th>
                                        <th>Бригада</th>
                                        <th style="width: 12rem;">Действия</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <style>

                                </style>

                                @foreach ($requests as $request)
                                    @php
                                        $formattedDate = $request->request_date
                                            ? \Carbon\Carbon::parse($request->request_date)->format('d.m.Y, H:i')
                                            : ($request->created_at ? \Carbon\Carbon::parse($request->created_at)->format('d.m.Y, H:i') : 'Не указана');
                                        $requestNumber = 'REQ-' . \Carbon\Carbon::parse($request->request_date ?? $request->created_at)->format('dmY, H:i') . '-' . str_pad($request->id, 4, '0', STR_PAD_LEFT);
                                    @endphp
                                    <tr class="align-middle status-row" style="--status-color: {{ $request->status_color ?? '#e2e0e6' }}" data-request-id="{{ $request->id }}">
                                        <td style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $request->id }}</td>

                                        <td class="text-center" style="width: 1rem;">
                                            <input type="checkbox" id="request-{{ $request->id }}" class="form-check-input request-checkbox" value="{{ $request->id }}" aria-label="Выбрать заявку">
                                        </td>
                                        <!-- Дата и номер заявки -->
                                        <td>
                                            <div>{{ $formattedDate }}</div>
                                            <div class="text-dark" style="font-size: 0.8rem;">{{ $requestNumber }}</div>
                                        </td>

                                        <!-- Комментарий -->
                                        <td style="width: 20rem; max-width: 20rem; overflow: hidden; text-overflow: ellipsis;">
                                            @if(isset($comments_by_request[$request->id]) && count($comments_by_request[$request->id]) > 0)
                                                @php
                                                    $firstComment = $comments_by_request[$request->id][0];
                                                    $commentText = $firstComment->comment;
                                                    $author = $firstComment->author_name;
                                                    $date = \Carbon\Carbon::parse($firstComment->created_at)->format('d.m.Y H:i');
                                                @endphp
                                                <div class="comment-preview small text-dark"
                                                     data-bs-toggle="tooltip" title="{{ $commentText }}">
                                                    @if(count($comments_by_request[$request->id]) > 1)
                                                        {{ Str::limit($commentText, 65, '...') }}
                                                    @else
                                                        {{ $commentText }}
                                                    @endif
                                                </div>
                                            @endif
                                            @if(isset($comments_by_request[$request->id]) && count($comments_by_request[$request->id]) > 1)
                                                <div class="mt-1">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#commentsModal"
                                                            data-request-id="{{ $request->id }}"
                                                            style="position: relative; z-index: 1;">
                                                        <i class="bi bi-chat-left-text me-1"></i>Все комментарии
                                                        <span class="badge bg-primary rounded-pill ms-1">
                                                                        {{ count($comments_by_request[$request->id]) }}
                                                                    </span>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Клиент -->
                                        <td style="width: 12rem; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;">
                                            @if(!empty($request->street))
                                                <small class="text-dark text-truncate d-block"
                                                       data-bs-toggle="tooltip"
                                                       title="ул. {{ $request->street }}, д. {{ $request->houses }} ({{ $request->district }})">
                                                    ул. {{ $request->street }}, д. {{ $request->houses }}
                                                </small>
                                            @else
                                                <small class="text-dark text-truncate d-block">Адрес не
                                                    указан</small>
                                            @endif
                                            <small
                                                class="@if(isset($request->status_name) && $request->status_name !== 'выполнена_') text-success_ fw-bold_ @else text-black @endif text-truncate d-block">
                                                {{ $request->client_phone ?? 'Нет телефона' }}
                                            </small>
                                        </td>

                                        <!-- Дата выполнения -->
                                        <td>
                                            <span class="brigade-lead-text">{{ $request->operator_name ?? 'Не указан' }}</span><br>
                                            <span class="brigade-lead-text">{{ $formattedDate }}</span>
                                        </td>

                                        <!-- Состав бригады -->
                                        <td>
                                            @if($request->brigade_id)
                                                @php
                                                    $brigadeMembers = collect($brigadeMembersWithDetails)
                                                        ->where('brigade_id', $request->brigade_id);
                                                @endphp
                                                    @php
                                                        $brigadeMembers = collect($brigadeMembersWithDetails)
                                                            ->where('brigade_id', $request->brigade_id);
                                                    @endphp

                                                    @if($brigadeMembers->isNotEmpty())
                                                        <div class="mb-2" style="font-size: 0.75rem; line-height: 1.2;">
                                                            @foreach($brigadeMembers as $member)
                                                                <div>
                                                                    {{ $member->employee_name }}
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <a href="#"
                                                       class="text-black hover:text-gray-700 hover:underline view-brigade-btn"
                                                       style="text-decoration: none; font-size: 0.75rem; line-height: 1.2;"
                                                       onmouseover="this.style.textDecoration='underline'"
                                                       onmouseout="this.style.textDecoration='none'"
                                                       data-bs-toggle="modal" data-bs-target="#brigadeModal"
                                                       data-brigade-id="{{ $request->brigade_id }}">
                                                        подробнее...
                                                    </a>
                                                @else
                                                    <small class="text-muted d-block mb-1">Не назначена</small>
                                                @endif
                                            </td>

                                            <!-- Action Buttons -->
                                            <td class="text-nowrap">
                                                <div class="d-flex flex-column gap-1">
                                                    @if($request->status_name !== 'выполнена')
                                                        <button data-request-id="{{ $request->id }}" type="button"
                                                                class="btn btn-sm btn-custom-brown p-1 close-request-btn"
                                                                onclick="closeRequest({{ $request->id }}); return false;">
                                                            Закрыть заявку
                                                        </button>
                                                        <button type="button"
                                                                id="btn-comment"
                                                                class="btn btn-sm btn-outline-primary p-1 comment-btn"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#commentsModal"
                                                                data-request-id="{{ $request->id }}">
                                                            <i class="bi bi-chat-left-text me-1"></i>Комментарий
                                                        </button>
                                                    @endif
                                                    <button data-request-id="{{ $request->id }}" type="button"
                                                            class="btn btn-sm btn-outline-success add-photo-btn"
                                                            onclick="console.log('Добавить фотоотчет', {{ $request->id }})">
                                                        <i class="bi bi-camera me-1"></i>Фотоотчет
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                    <tr id="no-requests-row" class="d-none">
                                        <td colspan="8" class="text-center py-4">
                                            <div class="alert alert-info m-0">
                                                <i class="bi bi-info-circle me-2"></i>Нет заявок для отображения.
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="tab-pane fade" id="teams" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Бригады</h4>
                            <button type="button" class="btn btn-primary" id="createBrigadeBtn">
                                <i class="bi bi-plus-circle"></i> Создать бригаду
                            </button>
                        </div>

                        <!-- Brigades List -->
                        <div class="card">
                            <div class="card-body">
                                <div id="brigadesList" class="list-group">
                                    <!-- Brigades will be loaded here -->
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                        <p class="mt-2 mb-0">Загрузка списка бригад...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Brigade details modal is defined below in the file -->

                        <p>В этом разделе отображается информация о бригадах. Вы можете просматривать состав бригад,
                            их загрузку и текущие задачи. Для каждой бригады доступна контактная информация
                            ответственного лица.</p>
                        <p>Используйте этот раздел для назначения заявок на бригады и контроля за выполнением работ.
                            Вы можете фильтровать бригады по специализации или текущему статусу.</p>
                    </div>

                    <div class="tab-pane fade" id="addresses" role="tabpanel">
                        <h4>Адреса</h4>
                        <p>Справочник адресов позволяет вести учет всех объектов, с которыми вы работаете. Для
                            каждого адреса хранится полная контактная информация, история обращений и выполненных
                            работ.</p>
                        <p>Добавляйте новые адреса вручную или импортируйте их из файла. Система автоматически
                            проверяет дубликаты и предлагает объединить похожие записи.</p>
                    </div>

                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <h4>Пользователи</h4>
                        <p>Управление пользователями системы. В этом разделе вы можете создавать новые учетные
                            записи, назначать роли и права доступа. Для каждого пользователя можно настроить
                            уведомления и персональные настройки.</p>
                        <p>Используйте фильтры для поиска пользователей по отделам, ролям или статусу активности. Вы
                            можете экспортировать список пользователей в различных форматах.</p>

                        @if (!empty($clients))
                            <h3>Список клиентов</h3>
                            <ul class="list-group mb-4">
                                @foreach ($clients as $client)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        {{ $client->fio ?? 'Без имени' }}
                                        <span class="badge bg-primary">xx {{ $client->phone ?? 'Нет телефона' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p>Нет данных о клиентах.</p>
                        @endif

                    </div>

                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <h4>Отчеты</h4>
                        <p>В этом разделе доступны различные отчеты по деятельности компании. Вы можете
                            анализировать загруженность бригад, финансовые показатели и эффективность работы.</p>
                        <p>Настраивайте автоматическую отправку отчетов на электронную почту в удобное для вас
                            время. Доступны шаблоны отчетов для различных подразделений компании.</p>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Выберите дату в календаре</h5>
                        <!-- <p class="card-text">Используйте календарь слева для выбора даты.</p> -->
                        <p>Выбранная дата: <span id="selectedDate" class="fw-bold">не выбрана</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Divider -->
<hr class="my-0 border-top border-2 border-opacity-10">

<!-- Footer -->
<footer class="bg-dark text-white sticky-bottom">
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-10">
                <div class="row g-4 d-none">
                    <!-- Temporarily hidden footer content -->
                    <div class="col-md-4 text-center text-md-start">
                        <h5>О компании</h5>
                        <p class="text-muted small">Сервис для управления заявками и бригадами. Удобный инструмент
                            для организации работы монтажных бригад.</p>
                    </div>
                    <div class="col-md-4">
                        <h5>Контакты</h5>
                        <ul class="list-unstyled text-muted small">
                            <li><i class="bi bi-telephone me-2"></i> +7 (XXX) XXX-XX-XX</li>
                            <li><i class="bi bi-envelope me-2"></i> info@fursa.ru</li>
                            <li><i class="bi bi-geo-alt me-2"></i> г. Москва, ул. Примерная, 123</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5>Быстрые ссылки</h5>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-white text-decoration-none">Главная</a></li>
                            <li><a href="#" class="text-white text-decoration-none">Услуги</a></li>
                            <li><a href="#" class="text-white text-decoration-none">Тарифы</a></li>
                            <li><a href="#" class="text-white text-decoration-none">Документация</a></li>
                        </ul>
                    </div>
                </div>
                <hr class="my-4 bg-secondary d-none">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="text-center text-md-start small">
                            &copy; 2025 lan-install.online. Все права защищены.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center text-md-end">
                            <a href="#" class="text-white text-decoration-none me-3"><i
                                    class="bi bi-telegram"></i></a>
                            <a href="#" class="text-white text-decoration-none me-3"><i
                                    class="bi bi-whatsapp"></i></a>
                            <a href="#" class="text-white text-decoration-none me-3"><i
                                    class="bi bi-vk"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function closeRequest(requestId) {
        if (confirm('Вы уверены, что хотите закрыть заявку #' + requestId + '?')) {
            fetch(`/requests/${requestId}/close`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Ошибка при закрытии заявки: ' + (data.message || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Произошла ошибка при отправке запроса');
                });
        }
    }
</script>

<script>
    // Обработка открытия модального окна комментариев
    document.addEventListener('DOMContentLoaded', function () {
        const commentsModal = document.getElementById('commentsModal');

        if (commentsModal) {
            commentsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const requestId = button.getAttribute('data-request-id');
                const modalTitle = commentsModal.querySelector('.modal-title');
                const requestIdSpan = commentsModal.querySelector('#commentsRequestId');
                const commentRequestId = commentsModal.querySelector('#commentRequestId');

                // Устанавливаем номер заявки в заголовок
                const requestRow = button.closest('tr');
                const requestNumber = requestRow.querySelector('td:nth-child(3) div:last-child').textContent.trim();
                requestIdSpan.textContent = requestNumber;
                commentRequestId.value = requestId;

                // Загружаем комментарии
                loadComments(requestId);
            });
        }

        // Обработка отправки формы комментария
        const commentForm = document.getElementById('addCommentForm');
        if (commentForm) {
            commentForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const requestId = formData.get('request_id');

                fetch('{{ route('requests.comment') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(formData)
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Ошибка при отправке комментария');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Очищаем поле ввода
                            commentForm.querySelector('input[name="comment"]').value = '';

                            // Показываем уведомление об успехе
                            utils.showAlert('Комментарий успешно добавлен', 'success');

                            // Обновляем список комментариев
                            loadComments(requestId).then(() => {
                                // Даем время на обновление DOM
                                setTimeout(() => {
                                    updateCommentsBadge(requestId);
                                }, 100);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        utils.showAlert('Произошла ошибка при добавлении комментария', 'danger');
                    });
            });
        }

        // Функция загрузки комментариев
        function loadComments(requestId) {
            return new Promise((resolve, reject) => {
                const container = document.getElementById('commentsContainer');

                // Показываем индикатор загрузки
                container.innerHTML = `
                        <div class="text-center my-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <p class="mt-2">Загрузка комментариев...</p>
                        </div>`;

                fetch(`/api/requests/${requestId}/comments`)
                    .then(response => response.json())
                    .then(comments => {
                        if (comments.length === 0) {
                            container.innerHTML = '<div class="text-muted text-center py-4">Нет комментариев</div>';
                            resolve(comments);
                            return;
                        }

                        let html = '<div class="list-group list-group-flush">';

                        comments.forEach(comment => {
                            const date = new Date(comment.created_at);
                            const formattedDate = date.toLocaleString('ru-RU', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            html += `
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="me-3">
                                                <p class="mb-1">${comment.comment}</p>
                                                <small class="text-muted">${formattedDate}</small>
                                            </div>
                                        </div>
                                    </div>`;
                        });

                        html += '</div>';
                        container.innerHTML = html;
                        resolve(comments);
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке комментариев:', error);
                        container.innerHTML = `
                                <div class="alert alert-danger">
                                    Произошла ошибка при загрузке комментариев. Пожалуйста, обновите страницу.
                                </div>`;
                        reject(error);
                    });
            });
        }

        // Функция обновления счетчика комментариев
        function updateCommentsBadge(requestId) {
            console.log('Updating badge for request ID:', requestId);

            // Запрашиваем актуальное количество комментариев
            fetch(`/api/requests/${requestId}/comments/count`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received comments count data:', data);
                    const commentCount = data.count || 0;
                    console.log(`Found ${commentCount} comments for request ${requestId}`);

                    if (commentCount == 2) {
                        console.log('Joker:', commentCount);
                    }

                    // Находим строку таблицы с нужной заявкой
                    const requestRow = document.querySelector(`tr[data-request-id="${requestId}"]`) ||
                        document.querySelector(`tr:has(button[data-request-id="${requestId}"])`);

                    if (!requestRow) {
                        console.error('Не удалось найти строку с заявкой ID:', requestId);
                        return;
                    }

                    // Обновляем бейдж только на кнопке 'Все комментарии'
                    const commentButtons = requestRow.querySelectorAll(`
                            button.view-comments-btn[data-request-id="${requestId}"]
                        `);

                    console.log(`Found ${commentButtons.length} comment buttons to update in row`);

                    commentButtons.forEach(button => {
                        console.log('Updating comment button:', button);

                        // Находим существующий бейдж или его место для вставки
                        let badge = button.querySelector('.badge');

                        if (commentCount > 0) {
                            if (!badge) {
                                // Создаем новый бейдж, если его нет
                                badge = document.createElement('span');
                                badge.className = 'badge bg-primary rounded-pill ms-1';

                                // Вставляем бейдж после иконки, если она есть
                                const icon = button.querySelector('i');
                                if (icon) {
                                    icon.insertAdjacentElement('afterend', badge);
                                } else {
                                    button.appendChild(badge);
                                }
                            }
                            // Обновляем только текст бейджа
                            badge.textContent = commentCount;
                        } else if (badge) {
                            // Если комментариев нет, но бейдж есть - удаляем его
                            badge.remove();
                        }
                    });
                })
                .catch(error => {
                    console.error('Ошибка при получении количества комментариев:', error);
                });
        }

        // Функция показа уведомлений


    });

    // Глобальная функция показа уведомлений
    // function showAlert(message, type = 'success') {
    //     const alertDiv = document.createElement('div');
    //     alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    //     alertDiv.style.zIndex = '1060';
    //     alertDiv.role = 'alert';
    //     alertDiv.innerHTML = `
    //         ${message}
    //         <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    //     `;

    //     document.body.appendChild(alertDiv);

    //     // Автоматически скрываем уведомление через 5 секунд
    //     setTimeout(() => {
    //         const bsAlert = new bootstrap.Alert(alertDiv);
    //         bsAlert.close();
    //     }, 5000);
    // }
</script>
<!-- Bootstrap Datepicker JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.ru.min.js"></script>

<!-- Custom Application JS -->
<script src="{{ asset('js/app.js') }}"></script>
<script type="module" src="{{ asset('js/utils.js') }}"></script>

<!-- Event Handlers -->
<script type="module" src="{{ asset('js/handler.js') }}"></script>

<!-- Here modals -->

<!-- Модальное окно комментариев -->
<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentsModalLabel">Комментарии к заявке #<span
                        id="commentsRequestId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="commentsContainer">
                <!-- Список комментариев будет загружен здесь -->
                <div class="text-center my-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form id="addCommentForm" class="w-100">
                    @csrf
                    <input type="hidden" name="request_id" id="commentRequestId">
                    <div class="input-group">
                        <input type="text" name="comment" class="form-control" placeholder="Напишите комментарий..."
                               required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Отправить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for displaying brigade details -->
<div class="modal fade" id="brigadeModal" tabindex="-1" aria-labelledby="brigadeModalLabel" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="brigadeModalLabel">Состав бригады!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body" id="brigadeDetails">
                <div class="text-center my-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-2">Загрузка данных о бригаде...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- New Request Modal -->
<div class="modal fade" id="newRequestModal" tabindex="-1" aria-labelledby="newRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newRequestModalLabel">Создание новой заявки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="newRequestForm">
                    @csrf
                    <div class="mb-3">
                        <h6>Информация о клиенте</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="clientName" class="form-label">Контактное лицо <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="clientName" name="client_name">
                            </div>
                            <div class="col-md-6">
                                <label for="clientPhone" class="form-label">Телефон <span
                                        class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="clientPhone" name="client_phone">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 hide-me">
                        <h6>Детали заявки</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="requestType" class="form-label">Тип заявки <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="requestType" name="request_type_id" required>
                                    <option value="" disabled selected>Выберите тип заявки</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="requestStatus" class="form-label">Статус</label>
                                <select class="form-select" id="requestStatus" name="status_id">
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6>Планирование</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="executionDate" class="form-label">Дата выполнения <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="executionDate" name="execution_date"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label for="executionTime" class="form-label">Время выполнения</label>
                                <input type="time" class="form-control" id="executionTime" name="execution_time">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 hide-me">
                        <h6>Назначение</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="brigade" class="form-label">Бригада</label>
                                <select class="form-select" id="brigade" name="brigade_id">
                                    <option value="" selected>Не выбрано</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="operator" class="form-label">Оператор <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="operator" name="operator_id" required>
                                    <option value="" disabled selected>Выберите оператора</option>
                                    <!-- Will be populated by JavaScript -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6>Адрес</h6>
                        <div class="mb-3">
                            <select class="form-select" id="addresses_id" name="addresses_id" required>
                                <option value="" disabled selected>Выберите адрес</option>
                                <!-- Will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6>Комментарий к заявке</h6>
                        <div class="mb-3">
                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Введите комментарий к заявке" required minlength="3" maxlength="1000"></textarea>
                            <div class="invalid-feedback">
                                Пожалуйста, введите комментарий (от 3 до 1000 символов)
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="submitRequest" onclick="submitRequestForm()">Создать заявку</button>
            </div>
        </div>
    </div>
</div>

<script>
    // New Request Form Functionality
    document.addEventListener('DOMContentLoaded', function () {
        const newRequestModal = document.getElementById('newRequestModal');

        if (newRequestModal) {
            // Set default execution date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('executionDate').valueAsDate = tomorrow;

            // Load dynamic data when modal is shown
            newRequestModal.addEventListener('show.bs.modal', async function () {
                try {
                    await Promise.all([
                        loadRequestTypes(),
                        loadRequestStatuses(),
                        // loadBrigades(),  // Not used in the form
                        loadOperators()
                        // loadAddresses() is called from handler.js
                    ]);
                } catch (error) {
                    console.error('Error loading form data:', error);
                    utils.showAlert('Ошибка при загрузке данных формы', 'danger');
                }
            });
        }

        // Load request types from API
        async function loadRequestTypes() {
            try {
                const response = await fetch('/api/request-types');
                const types = await response.json();
                const select = document.getElementById('requestType');

                // Clear any existing options first
                select.innerHTML = '';

                types.forEach((type, index) => {
                    const option = document.createElement('option');
                    option.value = type.id;
                    option.textContent = type.name;
                    select.appendChild(option);

                    // Select the first item by default
                    if (index === 0) {
                        option.selected = true;
                    }
                });
            } catch (error) {
                console.error('Error loading request types:', error);
                throw error;
            }
        }

        // Load request statuses from API
        async function loadRequestStatuses() {
            try {
                const response = await fetch('/api/request-statuses');
                const statuses = await response.json();
                const select = document.getElementById('requestStatus');

                statuses.forEach(status => {
                    const option = document.createElement('option');
                    option.value = status.id;
                    option.textContent = status.name;
                    // Select 'Новая' status by default if it exists
                    if (status.name.toLowerCase() === 'новая') {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading request statuses:', error);
                throw error;
            }
        }

        // Load brigades from API
        async function loadBrigades() {
            try {
                const response = await fetch('/api/brigades');
                const brigades = await response.json();
                const select = document.getElementById('brigade');

                brigades.forEach(brigade => {
                    const option = document.createElement('option');
                    option.value = brigade.id;
                    option.textContent = brigade.name;
                    select.appendChild(option);
                });
            } catch (error) {
                console.error('Error loading brigades:', error);
                throw error;
            }
        }

        // Load operators from API
        async function loadOperators() {
            try {
                const response = await fetch('/api/operators');
                let operators = await response.json();
                const select = document.getElementById('operator');

                // Очищаем список и добавляем текущего пользователя первым
                select.innerHTML = `
                    <option value="{{ auth()->id() }}" selected>{{ $user->name }}</option>
                    <option value="" disabled>──────────</option>
                `;

                // Добавляем остальных операторов
                operators
                    .filter(op => op.id != {{ auth()->id() }}) // Исключаем текущего пользователя, если он есть в списке
                    .sort((a, b) => (a.fio || '').localeCompare(b.fio || ''))
                    .forEach(operator => {
                        const option = document.createElement('option');
                        option.value = operator.id;
                        option.textContent = operator.fio || `Оператор #${operator.id}`;
                        select.appendChild(option);
                    });

                console.log('Операторы загружены, выбран:', select.options[select.selectedIndex]?.text);
            } catch (error) {
                console.error('Error loading operators:', error);
                throw error;
            }
        }

        // Function to load addresses (moved to handler.js)

        // Handle form submission
        // Function to validate the form
        function validateForm(form) {
            // Check all required fields
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else if (field.id === 'comment' && (field.value.length < 3 || field.value.length > 1000)) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            return isValid;
        }

        // Add real-time validation for comment field
        document.addEventListener('DOMContentLoaded', function() {
            const commentField = document.getElementById('comment');
            if (commentField) {
                commentField.addEventListener('input', function() {
                    if (this.value.length < 3 || this.value.length > 1000) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            }
        });

        async function submitRequestForm(event) {
            // Предотвращаем стандартную отправку формы
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            const form = document.getElementById('newRequestForm');
            const submitBtn = document.getElementById('submitRequest');

            // Validate form
            if (!validateForm(form)) {
                utils.showAlert('Пожалуйста, заполните все обязательные поля корректно', 'warning');
                return false;
            }
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                form.classList.add('was-validated');
                utils.showAlert('Пожалуйста, заполните все обязательные поля', 'danger');
                return;
            }

            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Создание...';

                // Get all form inputs
                const formInputs = form.querySelectorAll('input, select, textarea');
                const data = { _token: '' };
                
                // Convert form data to object
                formInputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        if (input.checked) {
                            if (!data[input.name]) {
                                data[input.name] = [input.value];
                            } else if (Array.isArray(data[input.name])) {
                                data[input.name].push(input.value);
                            } else {
                                data[input.name] = [data[input.name], input.value];
                            }
                        }
                    } else {
                        if (data[input.name] !== undefined) {
                            if (!Array.isArray(data[input.name])) {
                                data[input.name] = [data[input.name]];
                            }
                            data[input.name].push(input.value);
                        } else {
                            data[input.name] = input.value;
                        }
                    }
                });



                // Process addresses
                const addresses = [];
                const cityIds = Array.isArray(data['city_id']) ? data['city_id'] : (data['city_id'] ? [data['city_id']] : []);
                const streets = Array.isArray(data['street']) ? data['street'] : (data['street'] ? [data['street']] : []);
                const houses = Array.isArray(data['house']) ? data['house'] : (data['house'] ? [data['house']] : []);
                const addressComments = Array.isArray(data['address_comment']) ? data['address_comment'] : (data['address_comment'] ? [data['address_comment']] : []);

                // Debug information
                console.log('cityIds:', cityIds);
                console.log('streets:', streets);
                console.log('houses:', houses);
                console.log('addressComments:', addressComments);
                console.log('FormData:', data);

                // Создаем объект с данными для отправки
                const requestData = {
                    _token: data._token,
                    request: {
                        request_type_id: data.request_type_id,
                        status_id: data.status_id,
                        comment: data.comment || '',
                        execution_date: data.execution_date || null,
                        execution_time: data.execution_time || null,
                        brigade_id: data.brigade_id || null,
                        operator_id: data.operator_id || null
                    },
                    addresses: []
                };

                // Добавляем данные клиента, только если они заполнены
                if (data.client_name || data.client_phone) {
                    requestData.client = {
                        fio: data.client_name || '',
                        phone: data.client_phone || ''
                    };
                }

                // Добавляем адреса
                for (let i = 0; i < cityIds.length; i++) {
                    if (cityIds[i] && streets[i] && houses[i]) {
                        requestData.addresses.push({
                            city_id: cityIds[i],
                            street: streets[i],
                            house: houses[i],
                            comment: addressComments[i] || ''
                        });
                    }
                }

                console.log('Request data to be sent:', JSON.stringify(requestData, null, 2));

                if (requestData.addresses.length === 0) {
                    throw new Error('Необходимо указать хотя бы один адрес');
                }

                console.log('Sending request to /api/requests with data:', JSON.stringify(requestData));

                const response = await fetch('/api/requests', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': requestData._token
                    },
                    body: JSON.stringify(requestData)
                });

                const responseData = await response.json();
                console.log('Server response:', response.status, responseData);

                if (!response.ok) {
                    if (response.status === 422) {
                        // Обработка ошибок валидации
                        const errors = responseData.errors || {};
                        const errorMessages = Object.values(errors).flat().join('\n');
                        throw new Error(`Ошибка валидации: ${errorMessages}`);
                    }
                    throw new Error(responseData.message || 'Ошибка при создании заявки');
                }

                // Успешное создание заявки
                utils.showAlert('Заявка успешно создана!', 'success');

                // Очищаем форму
                form.reset();
                
                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('newRequestModal'));
                modal.hide();
                
                // Очищаем форму
                form.reset();
                
                // Обновляем таблицу заявок
                try {
                    await loadRequests();
                } catch (error) {
                    console.error('Ошибка при обновлении таблицы заявок:', error);
                    // В случае ошибки просто перезагружаем страницу
                    window.location.reload();
                }

                return responseData;

            } catch (error) {
                console.error('Error submitting request:', error);
                utils.showAlert(error.message || 'Произошла ошибка при создании заявки', 'danger');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Создать заявку';
            }
        }
        
        // Function to load and update the requests table via AJAX
        async function loadRequests() {
            try {
                // Show loading state
                const tableBody = document.querySelector('#requests-table-container tbody');
                const noRequestsRow = document.getElementById('no-requests-row');
                const loadingRow = document.createElement('tr');
                loadingRow.id = 'loading-requests';
                loadingRow.innerHTML = `
                    <td colspan="9" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <div class="mt-2">Загрузка заявок...</div>
                    </td>`;
                
                // Clear existing rows except the "no requests" row
                const existingRows = tableBody.querySelectorAll('tr:not(#no-requests-row)');
                existingRows.forEach(row => row.remove());
                
                // Add loading row
                tableBody.insertBefore(loadingRow, noRequestsRow);
                noRequestsRow.classList.add('d-none');
                
                // Get current date filter
                const dateFilter = document.getElementById('dateFilter')?.value || '';
                
                // Fetch requests from API
                const response = await fetch(`/api/requests?date=${encodeURIComponent(dateFilter)}`);
                if (!response.ok) throw new Error('Ошибка при загрузке заявок');
                
                const data = await response.json();
                
                // Remove loading row
                const loadingElement = document.getElementById('loading-requests');
                if (loadingElement) loadingElement.remove();
                
                // Clear existing rows
                tableBody.innerHTML = '';
                
                if (data.data && data.data.length > 0) {
                    // Add new rows for each request
                    data.data.forEach(request => {
                        const row = createRequestRow(request);
                        tableBody.appendChild(row);
                    });
                    noRequestsRow.classList.add('d-none');
                } else {
                    noRequestsRow.classList.remove('d-none');
                }
                
                // Reinitialize tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
                
                return data;
                
            } catch (error) {
                console.error('Error loading requests:', error);
                utils.showAlert('Ошибка при загрузке заявок: ' + error.message, 'danger');
                
                // Show error state
                const loadingElement = document.getElementById('loading-requests');
                if (loadingElement) {
                    loadingElement.innerHTML = `
                        <td colspan="9" class="text-center py-4 text-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Не удалось загрузить заявки. Попробуйте обновить страницу.
                        </td>`;
                }
                
                throw error;
            }
        }
        
        // Helper function to create a request row
        function createRequestRow(request) {
            const row = document.createElement('tr');
            row.className = 'align-middle status-row';
            row.style.setProperty('--status-color', request.status_color || '#e2e0e6');
            row.setAttribute('data-request-id', request.id);
            
            // Format the date
            const requestDate = request.request_date || request.created_at;
            const formattedDate = requestDate 
                ? new Date(requestDate).toLocaleString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }).replace(',', '') 
                : 'Не указана';
                
            // Format the execution date
            const executionDate = request.execution_date 
                ? new Date(request.execution_date).toLocaleDateString('ru-RU', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                })
                : 'Не указана';
            
            // Create row HTML
            row.innerHTML = `
                <td>${request.id}</td>
                <td class="text-center">
                    <input type="checkbox" id="request-${request.id}" class="form-check-input request-checkbox" value="${request.id}" aria-label="Выбрать заявку">
                </td>
                <td>${formattedDate}</td>
                <td>${request.number || 'Нет номера'}</td>
                <td>${request.client_name || 'Не указан'}</td>
                <td>${request.phone || 'Не указан'}</td>
                <td>${request.request_type_name || 'Не указан'}</td>
                <td>${request.status_name || 'Не указан'}</td>
                <td>${executionDate}</td>
                <td>${request.operator_name || 'Не назначен'}</td>
                <td>${request.brigade_name || 'Не назначена'}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="showRequestDetails(${request.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="editRequest(${request.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </div>
                </td>`;
                
            return row;
        }
    });
</script>

<!-- Modal for Brigade Details -->
<div class="modal fade" id="brigadeDetailsModal" tabindex="-1" aria-labelledby="brigadeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="brigadeDetailsModalLabel">Информация о бригаде</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Содержимое будет загружено динамически -->
                <div class="text-center my-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-2">Загрузка данных о бригаде...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Brigades Script -->
<script>
    // Передаем данные заявок в JavaScript
    window.requestsData = @json($requests);
    console.log('Данные заявок переданы в JavaScript:', window.requestsData);
</script>
<script src="{{ asset('js/brigades.js') }}"></script>
<script src="{{ asset('js/calendar.js') }}"></script>
<script type="module" src="{{ asset('js/form-handlers.js') }}"></script>
</body>

</html>
