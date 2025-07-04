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
            <div id="content-wrapper" class="container-fluid position-relative"
                 style="min-height: 100vh; overflow-x: hidden;">
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
                                <div class="d-flex d-none" style="max-width: 100%; display: none;">
                                    <div id="request-filters" class="d-flex align-items-center"
                                         style="height: 2rem; border: 1px solid var(--card-border, #dee2e6); border-radius: 0.25rem 0 0 0.25rem; padding: 0 0.5rem; background-color: var(--card-bg, #ffffff);">
                                        <!-- <label class="me-2 mb-0">Фильтр заявок по:</label> -->
                                        <div class="form-check form-check-inline hide-me">
                                            <input class="form-check-input" type="checkbox" id="filter-statuses">
                                            <label class="form-check-label" for="filter-statuses">статусам</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="filter-teams">
                                            <label class="form-check-label" for="filter-teams">Выбрать бригаду</label>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="reset-filters-button"
                                            style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Сброс
                                    </button>
                                </div>

                                <div class="d-flex justify-content-end" style="flex: 1;">
                                    <button type="button" class="btn btn-primary" id="new-request-button"
                                            data-bs-toggle="modal" data-bs-target="#newRequestModal">
                                        <i class="bi bi-plus-circle me-1"></i>Новая заявка
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Calendar and Status Buttons -->
                        <div class="pt-4 ps-4 pb-0 d-flex align-items-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm mb-3 me-2"
                                    id="btn-open-calendar">
                                <i class="bi bi-calendar me-1"></i>Календарь
                            </button>

                            <div id="status-buttons" class="d-flex flex-wrap gap-2  hide-me">
                                <!-- Кнопки статусов будут добавлены через JavaScript -->
                            </div>

                            <!-- Контейнер для выбора бригады -->
                            <div id="brigade-leader-filter" class="ms-3 d-none_">
                                <select id="brigade-leader-select" class="form-select form-select-sm"
                                        style="width: 250px; margin-top: -12px;">
                                    <option value="" selected disabled>Выберите бригаду...</option>
                                    @foreach ($brigadesCurrentDay as $brigade)
                                        <option value="{{ $brigade->employee_id }}"
                                                data-brigade-id="{{ $brigade->brigade_id }}">[Номер
                                            бригады: {{ $brigade->brigade_id }} ] [Бригадир: {{ $brigade->leader_name }}
                                            ]
                                        </option>
                                    @endforeach
                                </select>
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
                                <div class="p-4 hide-me">
                                    <div class="d-flex flex-column gap-2">
                                        @foreach ($request_statuses as $status)
                                            <div
                                                class="d-flex align-items-center rounded-3 bg-gray-200 dark:bg-gray-700">
                                                <div class="me-3 w-7 h-7 rounded-sm"
                                                     style="width: 8rem; height: 2rem; background-color: {{ $status->color }};">
                                                </div>
                                                <span>{{ $status->name }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <table class="table table-hover align-middle mb-0"
                                   style="min-width: 992px; margin-bottom: 0;">
                                <thead class="bg-dark">
                                <tr>
                                    <th style="width: 1rem;"></th>
                                    <th style="width: 1rem;"></th>
                                    <th style="width: 10rem;">Дата исполнения</th>
                                    <th style="width: 10rem;">Адрес/Телефон</th>
                                    <th style="width: 30rem;">Комментарий</th>
                                    <th style="width: 15rem;">Оператор / Дата создания</th>
                                    <th style="width: 15rem;">Бригада</th>
                                    <th style="width: 3rem;" colspan_="2">Действия с заявкой</th>
                                    <th style="width: 3rem;"></th>
                                </tr>
                                </thead>
                                <tbody>

                                @foreach ($requests as $index => $request)
                                    @php
                                        $rowNumber = $loop->iteration; 
                                        // Get the current loop iteration (1-based index)
                                    @endphp
                                    <tr class="align-middle status-row"
                                        style="--status-color: {{ $request->status_color ?? '#e2e0e6' }}"
                                        data-request-id="{{ $request->id }}">
                                        <td style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $rowNumber }}</td>

                                        <td class="text-center" style="width: 1rem;">
                                            @if($request->status_name !== 'выполнена')
                                                <input type="checkbox" id="request-{{ $request->id }}"
                                                       class="form-check-input request-checkbox"
                                                       value="{{ $request->id }}" aria-label="Выбрать заявку">
                                            @endif
                                        </td>
                                        <!-- Дата и номер заявки -->
                                        <td>
                                            <div>{{ $request->execution_date ? \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') : 'Не указана' }}</div>
                                            <div class="text-dark"
                                                 style="font-size: 0.8rem;">{{ $request->number }}</div>
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
                                                        {{ Str::limit($commentText, 300, '...') }}
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

                                        <!-- Дата выполнения -->
                                        <td>
                                            <span
                                                class="brigade-lead-text">{{ $request->operator_name ?? 'Не указан' }}</span><br>
                                            <span
                                                class="brigade-lead-text">{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('d.m.Y') : 'Не указана' }}</span>
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

                                        <!-- Action Buttons Group -->
                                        <td class="text-nowrap">
                                            <div class="d-flex flex-column gap-1">
                                                @if($request->status_name !== 'выполнена')
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary assign-team-btn p-1"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-people me-1"></i>Назначить бригаду
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-success transfer-request-btn p-1"
                                                            style="--bs-btn-color: #198754; --bs-btn-border-color: #198754; --bs-btn-hover-bg: rgba(25, 135, 84, 0.1); --bs-btn-hover-border-color: #198754;"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-arrow-left-right me-1"></i>Перенести заявку
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger cancel-request-btn p-1"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-x-circle me-1"></i>Отменить заявку
                                                    </button>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td class="text-nowrap">
                                            <div class="d-flex flex-column gap-1">
                                                @if($request->status_name !== 'выполнена')
                                                    <button data-request-id="{{ $request->id }}" type="button"
                                                            class="btn btn-sm btn-custom-brown p-1 close-request-btn">
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
                                    <td colspan="9" class="text-center py-4">
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
                        <!--
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Бригады</h4>
                            <button type="button" class="btn btn-primary" id="createBrigadeBtn">
                                <i class="bi bi-plus-circle"></i> Создать бригаду
                            </button>
                        </div>-->

                        <div class="card hide-me">
                            <div class="card-body">
                                <div id="brigadesList" class="list-group">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Загрузка...</span>
                                        </div>
                                        <p class="mt-2 mb-0">Загрузка списка бригад...</p>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <form id="brigadeForm" action="{{ route('brigades.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Название бригады</label>
                                <input type="text" id="brigadeName" name="name" class="form-control"
                                       value="Бригада технической интеграции видеосистем" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Бригадир</label>
                                <select id="brigadeLeader" name="leader_id" class="form-select" required>
                                    <option value="">-- Выберите бригадира --</option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->fio }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="d-flex gap-3 mb-3" style="height: 450px;">
                                <div class="d-flex flex-column" style="flex: 1.2;">
                                    <label class="form-label">Выбрать сотрудника</label>
                                    <select name="members[]" id="employeesSelect" class="form-select h-100" multiple>
                                        @foreach ($employees as $emp)
                                            <option value="{{ $emp->id }}"
                                                    data-employee-id="{{ $emp->id }}">{{ $emp->fio }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="d-flex flex-column justify-content-center">
                                    <button type="button" id="addToBrigadeBtn" class="btn btn-outline-secondary"
                                            style="width: 50px; height: 50px; border-radius: 50%;">
                                        <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>

                                <div class="d-flex flex-column" style="flex: 1.2;">
                                    <label class="form-label">Состав бригады</label>
                                    <div id="brigadeMembers" class="border rounded p-3 h-100 overflow-auto" require>
                                        <!-- Здесь будет отображаться выбранный состав бригады -->
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="button" id="createBrigadeBtn" class="btn btn-primary">Создать бригаду
                                </button>
                            </div>
                        </form>

                    </div>

                    <div class="tab-pane fade" id="addresses" role="tabpanel">
                        <h4>Адреса</h4>

                        <div class="card mb-4">
                            <div class="card-body">
                                <!-- Регион -->
                                <!--
                                <div class="mb-4">
                                    <h6>Добавить регион</h6>
                                    <form id="regionForm" class="row g-2 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label">Название региона</label>
                                            <input type="text" name="name" class="form-control" placeholder="Например: Московская область" required>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-outline-primary">Добавить регион</button>
                                        </div>
                                    </form>
                                </div>
                                -->

                                <!-- Город -->
                                <!--
                                <div class="mb-4">
                                    <h6>Добавить город</h6>
                                    <form id="cityForm" class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Название города</label>
                                            <input type="text" name="name" class="form-control" placeholder="Например: Коломна" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Регион</label>
                                            <select name="region_id" class="form-select" required></select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-outline-primary">Добавить город</button>
                                        </div>
                                    </form>
                                </div>
                                -->

                                <!-- Адрес -->
                                <div>
                                    <h6>Добавить адрес</h6>
                                    <form id="addressForm" class="row g-2 align-items-end" method="POST"
                                          action="{{ route('address.add') }}">
                                        @csrf
                                        <div class="col-md-3">
                                            <label class="form-label">Улица</label>
                                            <input type="text" name="street" class="form-control" placeholder="Улица"
                                                   required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Дом</label>
                                            <input type="text" name="houses" class="form-control" placeholder="12А"
                                                   required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Район</label>
                                            <input type="text" name="district" class="form-control"
                                                   placeholder="Центральный">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Город</label>
                                            <select id="filter_city_id" name="city_id" class="form-select" required>
                                                <option value="">Выберите город</option>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-outline-primary">Добавить адрес
                                            </button>
                                        </div>
                                        <div class="mt-3">
                                            <button id="testFillBtn" type="button" class="btn btn-secondary">Тестовое
                                                заполнение
                                            </button>
                                        </div>
                                    </form>
                                    <script>
                                        // Функция для загрузки городов
                                        function loadCities(selectElement) {
                                            // Устанавливаем состояние загрузки
                                            const loadingOption = document.createElement('option');
                                            loadingOption.value = '';
                                            loadingOption.textContent = 'Загрузка городов...';
                                            selectElement.innerHTML = '';
                                            selectElement.appendChild(loadingOption);

                                            // Загружаем города с сервера
                                            return fetch('/api/cities')
                                                .then(response => {
                                                    if (!response.ok) {
                                                        throw new Error('Ошибка загрузки городов');
                                                    }
                                                    return response.json();
                                                })
                                                .then(cities => {
                                                    // Очищаем список
                                                    selectElement.innerHTML = '';

                                                    // Добавляем опцию по умолчанию
                                                    const defaultOption = document.createElement('option');
                                                    defaultOption.value = '';
                                                    defaultOption.textContent = 'Выберите город';
                                                    selectElement.appendChild(defaultOption);

                                                    // Добавляем города в список
                                                    if (Array.isArray(cities) && cities.length > 0) {
                                                        cities.forEach(city => {
                                                            const option = document.createElement('option');
                                                            option.value = city.id;
                                                            option.textContent = city.name;
                                                            selectElement.appendChild(option);
                                                        });
                                                        // console.log('Загружено городов:', cities.length);
                                                    } else {
                                                        console.warn('Получен пустой список городов');
                                                    }
                                                    return cities;
                                                })
                                                .catch(error => {
                                                    console.error('Ошибка при загрузке городов:', error);
                                                    selectElement.innerHTML = '<option value="">Ошибка загрузки</option>';
                                                    throw error;
                                                });
                                        }

                                        // Загружаем города при загрузке страницы
                                        document.addEventListener('DOMContentLoaded', function () {
                                            const citySelect = document.getElementById('filter_city_id');
                                            if (citySelect) {
                                                loadCities(citySelect);
                                            }
                                        });

                                        const testData = [
                                            {street: "Ленина", houses: "12А", district: "Центральный", city_id: "1"},
                                            {street: "Пушкина", houses: "5", district: "Северный", city_id: "2"},
                                            {street: "Крылова", houses: "34", district: "Южный", city_id: "3"},
                                            {street: "Мира", houses: "17Б", district: "Западный", city_id: "4"},
                                            {street: "Садовая", houses: "8", district: "Восточный", city_id: "5"},
                                            {street: "Чехова", houses: "21", district: "Центральный", city_id: "1"},
                                            {street: "Толстого", houses: "13", district: "Северный", city_id: "2"},
                                            {street: "Гагарина", houses: "3", district: "Южный", city_id: "3"},
                                            {street: "Космонавтов", houses: "9А", district: "Западный", city_id: "4"},
                                            {street: "Новая", houses: "27", district: "Восточный", city_id: "5"},
                                            {street: "Лесная", houses: "11", district: "Центральный", city_id: "1"},
                                            {street: "Зеленая", houses: "4", district: "Северный", city_id: "2"},
                                            {street: "Речная", houses: "19", district: "Южный", city_id: "3"},
                                            {street: "Березовая", houses: "7", district: "Западный", city_id: "4"},
                                            {street: "Солнечная", houses: "15", district: "Восточный", city_id: "5"},
                                            {street: "Победы", houses: "2", district: "Центральный", city_id: "1"},
                                            {street: "Калинина", houses: "16", district: "Северный", city_id: "2"},
                                            {street: "Молодежная", houses: "10", district: "Южный", city_id: "3"},
                                            {street: "Луговая", houses: "6", district: "Западный", city_id: "4"},
                                            {street: "Восточная", houses: "20", district: "Восточный", city_id: "5"},
                                            {street: "Цветочная", houses: "14", district: "Центральный", city_id: "1"},
                                            {street: "Школьная", houses: "22", district: "Северный", city_id: "2"},
                                            {street: "Парковая", houses: "9", district: "Южный", city_id: "3"},
                                            {street: "Кленовая", houses: "18", district: "Западный", city_id: "4"},
                                            {street: "Набережная", houses: "5А", district: "Восточный", city_id: "5"},
                                            {
                                                street: "Магистральная",
                                                houses: "29",
                                                district: "Центральный",
                                                city_id: "1"
                                            },
                                            {street: "Советская", houses: "7Б", district: "Северный", city_id: "2"},
                                            {street: "Индустриальная", houses: "12", district: "Южный", city_id: "3"},
                                            {street: "Октябрьская", houses: "3А", district: "Западный", city_id: "4"},
                                            {
                                                street: "Революционная",
                                                houses: "10",
                                                district: "Восточный",
                                                city_id: "5"
                                            },
                                            {street: "Лазурная", houses: "8Б", district: "Центральный", city_id: "1"},
                                            {street: "Кирова", houses: "26", district: "Северный", city_id: "2"},
                                            {street: "Горького", houses: "13А", district: "Южный", city_id: "3"},
                                            {street: "Фестивальная", houses: "4", district: "Западный", city_id: "4"},
                                            {street: "Заречная", houses: "15Б", district: "Восточный", city_id: "5"},
                                            {street: "Полевая", houses: "11А", district: "Центральный", city_id: "1"},
                                            {street: "Южная", houses: "19Б", district: "Северный", city_id: "2"},
                                            {street: "Вишневая", houses: "6А", district: "Южный", city_id: "3"},
                                            {street: "Вокзальная", houses: "1", district: "Западный", city_id: "4"},
                                            {street: "Шоссейная", houses: "23", district: "Восточный", city_id: "5"},
                                            {street: "Мирная", houses: "17А", district: "Центральный", city_id: "1"},
                                            {street: "Песчаная", houses: "20Б", district: "Северный", city_id: "2"},
                                            {street: "Ясная", houses: "14А", district: "Южный", city_id: "3"},
                                            {street: "Полярная", houses: "9Б", district: "Западный", city_id: "4"},
                                            {street: "Светлая", houses: "5А", district: "Восточный", city_id: "5"},
                                            {street: "Пионерская", houses: "3Б", district: "Центральный", city_id: "1"},
                                            {
                                                street: "Водопроводная",
                                                houses: "12Б",
                                                district: "Северный",
                                                city_id: "2"
                                            },
                                            {street: "Золотая", houses: "7А", district: "Южный", city_id: "3"},
                                            {street: "Медовая", houses: "18Б", district: "Западный", city_id: "4"},
                                            {street: "Кавказская", houses: "10А", district: "Восточный", city_id: "5"}
                                        ];

                                        document.getElementById('testFillBtn').addEventListener('click', function () {
                                            const randomIndex = Math.floor(Math.random() * testData.length);
                                            const data = testData[randomIndex];

                                            const form = document.getElementById('addressForm');
                                            form.elements['street'].value = data.street;
                                            form.elements['houses'].value = data.houses;
                                            form.elements['district'].value = data.district;
                                            // Устанавливаем значение города
                                            form.elements['city_id'].value = data.city_id;
                                        });
                                    </script>

                                    <script>
                                        // Обработчик отправки формы добавления адреса
                                        document.getElementById('addressForm').addEventListener('submit', async function (e) {
                                            e.preventDefault();

                                            const form = e.target;
                                            const submitBtn = form.querySelector('button[type="submit"]');
                                            const originalBtnText = submitBtn.innerHTML;

                                            try {
                                                // Показываем индикатор загрузки
                                                submitBtn.disabled = true;
                                                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

                                                const formData = new FormData(form);
                                                const response = await fetch(form.action, {
                                                    method: 'POST',
                                                    headers: {
                                                        'Accept': 'application/json',
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                                    },
                                                    body: formData
                                                });

                                                const result = await response.json();

                                                if (response.ok) {
                                                    // Очищаем форму при успешном сохранении
                                                    form.reset();
                                                    // Сбрасываем выпадающий список городов
                                                    const citySelect = form.elements['city_id'];
                                                    citySelect.innerHTML = '<option value="">Выберите город</option>';
                                                    // Перезагружаем города
                                                    loadCities(citySelect);
                                                    // Показываем уведомление об успехе
                                                    if (typeof showAlert === 'function') {
                                                        showAlert('Адрес успешно добавлен!', 'success');
                                                    } else {
                                                        alert('Адрес успешно добавлен!');
                                                    }
                                                } else if (response.status === 422) {
                                                    // Обработка ошибок валидации
                                                    const errorMessages = [];
                                                    for (const field in result.errors) {
                                                        errorMessages.push(result.errors[field].join('\n'));
                                                    }
                                                    const errorMessage = errorMessages.join('\n');
                                                    if (typeof showAlert === 'function') {
                                                        showAlert(errorMessage, 'danger');
                                                    } else {
                                                        alert(errorMessage);
                                                    }
                                                    throw new Error(errorMessage);
                                                } else {
                                                    const errorMessage = result.message || 'Ошибка при сохранении адреса';
                                                    if (typeof showAlert === 'function') {
                                                        showAlert(errorMessage, 'danger');
                                                    } else {
                                                        alert(errorMessage);
                                                    }
                                                    throw new Error(errorMessage);
                                                }
                                            } catch (error) {
                                                console.error('Ошибка:', error);
                                                const errorMessage = error.message || 'Произошла ошибка при сохранении адреса';
                                                if (typeof showAlert === 'function') {
                                                    showAlert(errorMessage, 'danger');
                                                } else {
                                                    alert(errorMessage);
                                                }
                                            } finally {
                                                // Восстанавливаем кнопку
                                                submitBtn.disabled = false;
                                                submitBtn.innerHTML = originalBtnText;
                                            }
                                        });
                                    </script>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <div class="row g-4 flex-nowrap">
                            <!-- Форма регистрации -->
                            <div id="registerFormContainer" class="col-lg-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Добавить нового пользователя</h5>
                                    </div>
                                    <div class="card-body">
                                        @include('auth.partials.register-form')
                                    </div>
                                </div>
                            </div>

                            <!-- Таблица пользователей -->
                            <div id="usersTableContainer" class="col-lg-9">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Список пользователей</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <style>
                                                .table-container {
                                                    height: 30rem;
                                                    overflow-y: auto;
                                                }
                                                
                                                [data-bs-theme="dark"] .users-table {
                                                    --bs-table-bg: transparent;
                                                    --bs-table-color: #fff;
                                                    --bs-table-hover-bg: rgba(255, 255, 255, 0.075);
                                                }

                                                [data-bs-theme="dark"] .users-table th,
                                                [data-bs-theme="dark"] .users-table td {
                                                    color: #fff;
                                                    border-color: #495057;
                                                }

                                                [data-bs-theme="dark"] .users-table thead th {
                                                    border-bottom-color: #6c757d;
                                                }
                                            </style>
                                            <div class="table-container">
                                                <table class="table table-hover users-table mb-0">
                                                <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Имя</th>
                                                    <th>Email</th>
                                                    <th>Дата регистрации</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach(\App\Models\User::orderBy('created_at', 'desc')->get() as $user)
                                                    <tr>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary select-user" 
                                                                    data-user-id="{{ $user->id }}" 
                                                                    data-bs-toggle="tooltip" 
                                                                    title="Выбрать пользователя (ID: {{ $user->id }})">
                                                                <i class="bi bi-person-plus"></i> {{ $user->id }}
                                                            </button>
                                                        </td>
                                                        <td>{{ $user->name }}</td>
                                                        <td>{{ $user->email }}</td>
                                                        <td>{{ $user->created_at->format('d.m.Y H:i') }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                        <div id="employeesFormContainer" class="col-lg-6">
                                <form action="{{ route('employees.store') }}" method="POST">
                                    @csrf

                                    <input type="hidden" name="user_id" id="userIdInput" value="">
                                    
                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const selectUserBtns = document.querySelectorAll('.select-user');
                                            const userIdInput = document.getElementById('userIdInput');
                                            
                                            selectUserBtns.forEach(btn => {
                                                btn.addEventListener('click', function() {
                                                    const userId = this.getAttribute('data-user-id');
                                                    userIdInput.value = userId;
                                                    
                                                    // Показываем уведомление о выборе пользователя
                                                    const toast = new bootstrap.Toast(document.getElementById('userSelectedToast'));
                                                    toast.show();
                                                    
                                                    // Прокручиваем к форме
                                                    document.getElementById('employeesFormContainer').scrollIntoView({ behavior: 'smooth' });
                                                });
                                            });
                                            
                                            // Инициализация тултипов
                                            if (window.bootstrap) {
                                                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                                tooltipTriggerList.map(function (tooltipTriggerEl) {
                                                    return new bootstrap.Tooltip(tooltipTriggerEl);
                                                });
                                            }
                                        });
                                    </script>
                                    
                                    <!-- Toast уведомление о выборе пользователя -->
                                    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                                        <div id="userSelectedToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                                            <div class="toast-header">
                                                <strong class="me-auto">Успешно</strong>
                                                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
                                            </div>
                                            <div class="toast-body">
                                                Пользователь выбран. Теперь можно заполнить форму сотрудника.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">ФИО</label>
                                        <input type="text" name="fio" class="form-control" required>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Телефон</label>
                                        <input type="text" name="phone" class="form-control">
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Дата рождения</label>
                                        <input type="date" name="birth_date" class="form-control">
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Место рождения</label>
                                        <input type="text" name="birth_place" class="form-control">
                                    </div>

                                    <hr>

                                    <div class="mb-2">
                                        <label class="form-label">Паспорт (серия и номер)</label>
                                        <input type="text" name="passport_series" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Кем выдан</label>
                                        <input type="text" name="passport_issued_by" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Дата выдачи</label>
                                        <input type="date" name="passport_issued_at" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Код подразделения</label>
                                        <input type="text" name="passport_department_code" class="form-control">
                                    </div>

                                    <hr>

                                    <div class="mb-2">
                                        <label class="form-label">Марка машины</label>
                                        <input type="text" name="car_brand" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Госномер</label>
                                        <input type="text" name="car_plate" class="form-control">
                                    </div>

                                    <button id="saveBtn" type="submit" class="btn btn-primary w-100 mt-3">Сохранить</button>

                                    <button id="editBtn" type="button" class="btn btn-primary w-100 mt-3">Изменить</button>

                                    <button id="autoFillBtn" type="button" class="btn btn-outline-secondary mb-3 mt-3" 
                                        data-bs-toggle="tooltip" title="Заполнить случайными тестовыми данными">
                                        Автозаполнение
                                    </button>
                                </form>
                            </div>  
                        </div>

                        <script>
                            document.getElementById('autoFillBtn').addEventListener('click', function(e) {
                                e.preventDefault();
                                
                                // 30 вариантов тестовых данных
                                const mockDataArray = [
                                    {
                                        fio: "Иванов Иван Иванович", phone: "+7 (912) 345-67-89",
                                        birth_date: "1990-05-15", birth_place: "г. Москва",
                                        passport_series: "4510 123456", passport_issued_by: "ОУФМС России по г. Москве",
                                        passport_issued_at: "2015-06-20", passport_department_code: "770-123",
                                        car_brand: "Toyota Camry", car_plate: "А123БВ777"
                                    },
                                    {
                                        fio: "Петров Петр Петрович", phone: "+7 (923) 456-78-90",
                                        birth_date: "1985-08-22", birth_place: "г. Санкт-Петербург",
                                        passport_series: "4012 654321", passport_issued_by: "ГУ МВД по СПб и ЛО",
                                        passport_issued_at: "2018-03-15", passport_department_code: "780-456",
                                        car_brand: "Hyundai Solaris", car_plate: "В987СН178"
                                    },
                                    {
                                        fio: "Сидорова Анна Михайловна", phone: "+7 (934) 567-89-01",
                                        birth_date: "1995-02-10", birth_place: "г. Екатеринбург",
                                        passport_series: "4603 789012", passport_issued_by: "УМВД по Свердловской области",
                                        passport_issued_at: "2017-11-30", passport_department_code: "660-789",
                                        car_brand: "Kia Rio", car_plate: "Е456КХ123"
                                    },
                                    // Продолжение с другими вариантами...
                                    {
                                        fio: "Кузнецов Дмитрий Сергеевич", phone: "+7 (945) 678-90-12",
                                        birth_date: "1988-07-14", birth_place: "г. Новосибирск",
                                        passport_series: "5401 345678", passport_issued_by: "ГУ МВД по Новосибирской области",
                                        passport_issued_at: "2019-04-25", passport_department_code: "540-234",
                                        car_brand: "Volkswagen Polo", car_plate: "Н543ТУ777"
                                    },
                                    {
                                        fio: "Смирнова Ольга Викторовна", phone: "+7 (956) 789-01-23",
                                        birth_date: "1992-12-05", birth_place: "г. Казань",
                                        passport_series: "9204 567890", passport_issued_by: "МВД по Республике Татарстан",
                                        passport_issued_at: "2016-09-18", passport_department_code: "160-567",
                                        car_brand: "Lada Vesta", car_plate: "У321ХС123"
                                    },
                                    // Еще 25 вариантов...
                                    {
                                        fio: "Васильев Артем Игоревич", phone: "+7 (967) 890-12-34",
                                        birth_date: "1993-04-30", birth_place: "г. Нижний Новгород",
                                        passport_series: "5205 901234", passport_issued_by: "ГУ МВД по Нижегородской области",
                                        passport_issued_at: "2020-01-12", passport_department_code: "520-890",
                                        car_brand: "Skoda Rapid", car_plate: "В654АС321"
                                    }
                                ];

                                // Выбираем случайный вариант из массива
                                const randomIndex = Math.floor(Math.random() * mockDataArray.length);
                                const mockData = mockDataArray[randomIndex];

                                // Заполняем поля формы
                                Object.keys(mockData).forEach(key => {
                                    const input = document.querySelector(`[name="${key}"]`);
                                    if (input) input.value = mockData[key];
                                });

                                // Показываем уведомление с номером варианта
                                const toastBody = document.getElementById('autoFillToastBody');
                                toastBody.textContent = `Форма заполнена тестовыми данными (вариант ${randomIndex + 1} из ${mockDataArray.length}). Проверьте информацию.`;
                                
                                const toast = new bootstrap.Toast(document.getElementById('autoFillToast'));
                                toast.show();
                            });
                        </script>

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
            // console.log('Updating badge for request ID:', requestId);

            // Запрашиваем актуальное количество комментариев
            fetch(`/api/requests/${requestId}/comments/count`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // console.log('Received comments count data:', data);
                    const commentCount = data.count || 0;
                    // console.log(`Found ${commentCount} comments for request ${requestId}`);

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

                    // console.log(`Found ${commentButtons.length} comment buttons to update in row`);

                    commentButtons.forEach(button => {
                        // console.log('Updating comment button:', button);

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
<div class="modal fade" id="newRequestModal" tabindex="-1" aria-labelledby="newRequestModalLabel" aria-hidden="true"
     role="dialog">
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
                        <!-- <h6>Информация о клиенте</h6> -->
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
                        <!-- <h6>Детали заявки</h6> -->
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
                        <!-- <h6>Планирование</h6> -->
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
                            <textarea class="form-control" id="comment" name="comment" rows="3"
                                      placeholder="Введите комментарий к заявке" required minlength="3"
                                      maxlength="1000"></textarea>
                            <div class="invalid-feedback">
                                Пожалуйста, введите комментарий (от 3 до 1000 символов)
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info mb-3" id="fillMockDataBtn" style="margin-top: 14px;">Заполнить
                    тестовыми данными
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="submitRequest" onclick="submitRequestForm()">Создать
                    заявку
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const newComments = [
        "Ваше обращение принято в обработку. Специалист отдела обслуживания свяжется с вами для завершения подготовки к работам.",
        "Принята ваша заявка на выполнение монтажа. Менеджеры скоро перезвонят для уточнения условий.",
        "Заказ успешно передан в отдел обработки заявок. Наши сотрудники подробно проконсультируют вас по предстоящему проекту.",
        "Получили ваш запрос и начали подготовительные мероприятия. Согласуем нюансы монтажа по телефону.",
        "Обработка вашей заявки началась. После проверки информации с вами свяжутся наши специалисты.",
        "Ваша заявка принята в производственный цикл. Детали будут оговорены дополнительно при контакте.",
        "Информация по вашему запросу зафиксирована. В скором времени мы будем обсуждать организацию монтажа.",
        "Оформление вашей заявки завершилось успешно. Вы получите звонок для утверждения сроков начала работ.",
        "Работаем над вашим проектом. Ждите контакта от наших сотрудников для подтверждения деталей.",
        "Спасибо за отправленную заявку! Наши менеджеры обязательно свяжутся с вами для координации процесса установки оборудования.",
        "Ваша заявка внесена в рабочий график. Связываемся с вами для точного определения плана действий.",
        "Начало процедуры оформления вашей заявки. По вопросам уточняющей информации свяжитесь с нашим представителем.",
        "Запрос принят. Ожидайте дальнейшего взаимодействия от менеджера для организации монтажных мероприятий.",
        "Подтверждение приема заявки получено. Организуем встречу или телефонный разговор для согласования технических аспектов.",
        "Уведомляем о принятии вашей заявки. Дожидаетесь контактирования специалистов для обсуждения деталей реализации.",
        "Выполнено принятие заявки на оказание услуг. Направлен запрос менеджеру для согласования порядка исполнения.",
        "Началась обработка вашей заявки. Будет произведена связь с вами для внесения дополнений и обсуждений.",
        "Фиксация заявки состоялась. Сообщение поступит от координатора проектов для рассмотрения деталей и сроков выполнения.",
        "Предложение зафиксировано и передано специалистам. Следующий этап — обсуждение ваших пожеланий относительно монтажа.",
        "Официально подтверждено начало обработки заявки. Осталось лишь согласовать ряд вопросов с нашими сотрудниками."
    ];

    const mockData = [
        {name: "Иван Иванов", phone: "+7 (999) 111-11-01", comment: newComments[0]},
        {name: "Мария Петрова", phone: "+7 (999) 111-11-02", comment: newComments[1]},
        {name: "Алексей Смирнов", phone: "+7 (999) 111-11-03", comment: newComments[2]},
        {name: "Елена Кузнецова", phone: "+7 (999) 111-11-04", comment: newComments[3]},
        {name: "Дмитрий Соколов", phone: "+7 (999) 111-11-05", comment: newComments[4]},
        {name: "Ольга Морозова", phone: "+7 (999) 111-11-06", comment: newComments[5]},
        {name: "Николай Васильев", phone: "+7 (999) 111-11-07", comment: newComments[6]},
        {name: "Татьяна Орлова", phone: "+7 (999) 111-11-08", comment: newComments[7]},
        {name: "Сергей Павлов", phone: "+7 (999) 111-11-09", comment: newComments[8]},
        {name: "Анна Федорова", phone: "+7 (999) 111-11-10", comment: newComments[9]},
        {name: "Владимир Беляев", phone: "+7 (999) 111-11-11", comment: newComments[10]},
        {name: "Екатерина Никитина", phone: "+7 (999) 111-11-12", comment: newComments[11]},
        {name: "Андрей Сидоров", phone: "+7 (999) 111-11-13", comment: newComments[12]},
        {name: "Ирина Григорьева", phone: "+7 (999) 111-11-14", comment: newComments[13]},
        {name: "Павел Егоров", phone: "+7 (999) 111-11-15", comment: newComments[14]},
        {name: "Людмила Киселева", phone: "+7 (999) 111-11-16", comment: newComments[15]},
        {name: "Михаил Козлов", phone: "+7 (999) 111-11-17", comment: newComments[16]},
        {name: "Светлана Михайлова", phone: "+7 (999) 111-11-18", comment: newComments[17]},
        {name: "Виктор Фролов", phone: "+7 (999) 111-11-19", comment: newComments[18]},
        {name: "Оксана Дмитриева", phone: "+7 (999) 111-11-20", comment: newComments[19]},
        {name: "Роман Кузьмин", phone: "+7 (999) 111-11-21", comment: newComments[0]},
        {name: "Наталья Алексеева", phone: "+7 (999) 111-11-22", comment: newComments[1]},
        {name: "Константин Власов", phone: "+7 (999) 111-11-23", comment: newComments[2]},
        {name: "Алёна Николаева", phone: "+7 (999) 111-11-24", comment: newComments[3]},
        {name: "Игорь Тимофеев", phone: "+7 (999) 111-11-25", comment: newComments[4]},
        {name: "Галина Павлова", phone: "+7 (999) 111-11-26", comment: newComments[5]},
        {name: "Денис Мельников", phone: "+7 (999) 111-11-27", comment: newComments[6]},
        {name: "Алла Сергеева", phone: "+7 (999) 111-11-28", comment: newComments[7]},
        {name: "Василий Лебедев", phone: "+7 (999) 111-11-29", comment: newComments[8]},
        {name: "Евгения Тихонова", phone: "+7 (999) 111-11-30", comment: newComments[9]},
        {name: "Олег Зайцев", phone: "+7 (999) 111-11-31", comment: newComments[10]},
        {name: "Нина Орехова", phone: "+7 (999) 111-11-32", comment: newComments[11]},
        {name: "Вячеслав Соколов", phone: "+7 (999) 111-11-33", comment: newComments[12]},
        {name: "Лариса Денисова", phone: "+7 (999) 111-11-34", comment: newComments[13]},
        {name: "Артур Крылов", phone: "+7 (999) 111-11-35", comment: newComments[14]},
        {name: "Ирина Соловьева", phone: "+7 (999) 111-11-36", comment: newComments[15]},
        {name: "Дмитрий Климов", phone: "+7 (999) 111-11-37", comment: newComments[16]},
        {name: "Марина Белова", phone: "+7 (999) 111-11-38", comment: newComments[17]},
        {name: "Владислав Орлов", phone: "+7 (999) 111-11-39", comment: newComments[18]},
        {name: "Софья Федотова", phone: "+7 (999) 111-11-40", comment: newComments[19]},
        {name: "Егор Панфилов", phone: "+7 (999) 111-11-41", comment: newComments[0]},
        {name: "Олеся Захарова", phone: "+7 (999) 111-11-42", comment: newComments[1]},
        {name: "Максим Ширяев", phone: "+7 (999) 111-11-43", comment: newComments[2]},
        {name: "Вероника Борисова", phone: "+7 (999) 111-11-44", comment: newComments[3]},
        {name: "Артём Дмитриев", phone: "+7 (999) 111-11-45", comment: newComments[4]},
        {name: "Людмила Соколова", phone: "+7 (999) 111-11-46", comment: newComments[5]},
        {name: "Никита Романов", phone: "+7 (999) 111-11-47", comment: newComments[6]},
        {name: "Елена Крылова", phone: "+7 (999) 111-11-48", comment: newComments[7]},
        {name: "Павел Гусев", phone: "+7 (999) 111-11-49", comment: newComments[8]},
        {name: "Алина Иванова", phone: "+7 (999) 111-11-50", comment: newComments[9]},
    ];

    // Если нужно ровно 50, можно циклом дополнить:
    while (mockData.length < 50) {
        const i = mockData.length % comments.length;
        mockData.push({
            name: `Пользователь ${mockData.length + 1}`,
            phone: `+7 (999) 111-11-${(mockData.length + 1).toString().padStart(2, '0')}`,
            comment: comments[i]
        });
    }

    document.getElementById('fillMockDataBtn').addEventListener('click', function () {
        const randomIndex = Math.floor(Math.random() * mockData.length);
        const data = mockData[randomIndex];

        document.getElementById('clientName').value = data.name;
        document.getElementById('clientPhone').value = data.phone;
        document.getElementById('comment').value = data.comment;
    });
</script>

<script>
    // New Request Form Functionality
    document.addEventListener('DOMContentLoaded', function () {
        const newRequestModal = document.getElementById('newRequestModal');

        // Initialize modal with proper ARIA attributes
        if (newRequestModal) {
            // Set up event listeners for modal show/hide
            newRequestModal.addEventListener('show.bs.modal', function () {
                this.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                // Add inert to the rest of the page when modal is open
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.setAttribute('inert', 'true');
                }
            });

            newRequestModal.addEventListener('hidden.bs.modal', function () {
                this.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
                // Remove inert from the main content when modal is closed
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.removeAttribute('inert');
                }
            });
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

                // console.log('Операторы загружены, выбран:', select.options[select.selectedIndex]?.text);
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
        document.addEventListener('DOMContentLoaded', function () {
            const commentField = document.getElementById('comment');
            if (commentField) {
                commentField.addEventListener('input', function () {
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
                const data = {_token: ''};

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
                // console.log('cityIds:', cityIds);
                // console.log('streets:', streets);
                // console.log('houses:', houses);
                // console.log('addressComments:', addressComments);
                // console.log('FormData:', data);

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

                // console.log('Request data to be sent:', JSON.stringify(requestData, null, 2));

                if (requestData.addresses.length === 0) {
                    throw new Error('Необходимо указать хотя бы один адрес');
                }

                // console.log('Sending request to /api/requests with data:', JSON.stringify(requestData));

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
                // console.log('Server response:', response.status, responseData);

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
                ${request.status_name !== 'выполнена' ? `
                    <input type="checkbox" id="request-${request.id}" class="form-check-input request-checkbox" value="${request.id}" aria-label="Выбрать заявку">
                ` : ''}
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

<!-- Modal for Closing Request -->
<div class="modal fade" id="closeRequestModal" tabindex="-1" aria-labelledby="closeRequestModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="closeRequestModalLabel">Закрытие заявки <span id="modalRequestId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="closeRequestForm">
                    @csrf
                    <input type="hidden" id="requestIdToClose" name="request_id">
                    <div class="mb-3">
                        <label for="closeComment" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="closeComment" name="comment" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirmCloseRequest">Закрыть заявку</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Обработчик для кнопки закрытия заявки в таблице
    document.addEventListener('DOMContentLoaded', function () {
        // Обработчик для кнопки "Закрыть заявку" в таблице
        document.addEventListener('click', function (e) {
            if (e.target.closest('.close-request-btn')) {
                e.preventDefault();
                const requestId = e.target.closest('.close-request-btn').getAttribute('data-request-id');
                const modal = new bootstrap.Modal(document.getElementById('closeRequestModal'));

                // Устанавливаем ID заявки в скрытое поле и заголовок
                document.getElementById('requestIdToClose').value = requestId;
                document.getElementById('modalRequestId').textContent = '#' + requestId;

                // Показываем модальное окно
                modal.show();
            }
        });

        // Обработчик для кнопки подтверждения в модальном окне
        document.getElementById('confirmCloseRequest').addEventListener('click', async function () {
            const form = document.getElementById('closeRequestForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const requestId = document.getElementById('requestIdToClose').value;
            const comment = document.getElementById('closeComment').value;
            const submitBtn = this;
            const originalBtnText = submitBtn.innerHTML;

            try {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Обработка...';

                const response = await fetch(`/requests/${requestId}/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        comment: comment,
                        _token: document.querySelector('input[name="_token"]').value
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showAlert('Заявка успешно закрыта', 'success');
                    // Закрываем модальное окно
                    const modal = bootstrap.Modal.getInstance(document.getElementById('closeRequestModal'));
                    modal.hide();
                    // Обновляем страницу
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(result.message || 'Неизвестная ошибка при закрытии заявки');
                }
            } catch (error) {
                console.error('Ошибка при закрытии заявки:', error);
                showAlert(`Ошибка при закрытии заявки: ${error.message}`, 'danger');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
    });

    // Функция для отображения уведомлений
    function showAlert(message, type = 'success') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '2000';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
        document.body.appendChild(alertDiv);

        // Автоматическое скрытие через 5 секунд
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }, 5000);
    }
</script>


<!-- Modal for Brigade Details -->
<div class="modal fade" id="brigadeDetailsModal" tabindex="-1" aria-labelledby="brigadeDetailsModalLabel"
     aria-hidden="true">
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
    {{--window.requestsData = @json($requests);--}}
    // console.log('Данные заявок переданы в JavaScript:', window.requestsData);
</script>
<script src="{{ asset('js/brigades.js') }}"></script>
<script src="{{ asset('js/calendar.js') }}"></script>
<script type="module" src="{{ asset('js/form-handlers.js') }}"></script>

<!-- Stack for pushed scripts -->
@stack('scripts')

<!-- В конце страницы, перед </body> -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="autoFillToast" class="toast fade hide" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Автозаполнение</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Закрыть"></button>
        </div>
        <div class="toast-body" id="autoFillToastBody"></div>
    </div>
</div>

</body>

</html>
