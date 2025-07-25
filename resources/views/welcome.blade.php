@php
    // Функция для сокращения ФИО до фамилии и первой буквы имени
    function shortenName($fullName) {
        if (empty($fullName)) return '';
        
        $parts = explode(' ', $fullName);
        if (count($parts) < 2) return $fullName;
        
        $lastName = $parts[0];
        $firstName = $parts[1];
        
        return $lastName . ' ' . mb_substr($firstName, 0, 1) . '.';
    }
@endphp
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система управления заявками</title>
    <style>
        /* Стили для выпадающего списка адресов */
        
        /* Стиль для выделения бригадира */
        .brigade-leader {
            border: 2px solid #dc3545 !important;
            position: relative;
        }
        
        .brigade-leader::after {
            content: 'Бригадир';
            position: absolute;
            top: 18px;
            left: 50%;
            transform: translateX(-50%); /* центрирование по горизонтали */
            font-size: 12px;
            background-color: #dc3545;
            color: white;
            padding: 0 5px;
            border-radius: 3px;
            white-space: nowrap; /* предотвращаем перенос текста */
        }
        
        /* Стили для таблицы отчётов */
        #reportTable tbody tr.status-row {
            --status-color: #ffffff; /* Белый цвет по умолчанию */
            background-color: var(--status-color);
            transition: background-color 0.2s;
        }

        /* Если у строки есть цвет статуса, применим его с прозрачностью */
        #reportTable tbody tr.status-row[style*="--status-color"] {
            background-color: color-mix(in srgb, var(--status-color) 10%, #ffffff);
        }

        /* Ховер-эффект для строк таблицы отчётов */
        #reportTable tbody tr.status-row:hover {
            background-color: color-mix(in srgb, var(--status-color, #e9ecef) 20%, #ffffff) !important;
        }

        /* Переопределение стилей Bootstrap для строк таблицы отчётов */
        #reportTable tbody tr.status-row > * {
            background-color: transparent !important;
        }

        /* Стили для кастомизации скроллбара в блоке комментариев */
        .comment-preview {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
        }
        
        /* Для WebKit (Chrome, Safari, Edge) */
        .comment-preview::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        .comment-preview::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 3px;
        }
        
        .comment-preview::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        
        .comment-preview::-webkit-scrollbar-thumb:hover {
            background-color: rgba(0, 0, 0, 0.3);
        }
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
    <link href="{{ asset('css/mobile-requests.css') }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.png') }}">

    <style>
        
    </style>

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
                    <!-- <li class="nav-item" role="presentation">
                        <button class="nav-link" id="statuses-tab" data-bs-toggle="tab" data-bs-target="#statuses"
                                type="button" role="tab">Статусы    
                        </button>
                    </li> -->
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

                                <div class="d-flex justify-content-start" style="flex: 1;">
                                    <label for="employeeFilter" class="form-label">Фильтр по сотрудникам в бригаде:</label>
                                    <select name="employee_filter" id="employeeFilter" class="form-select w-50 ms-2" style="margin-top: -0.4rem;">
                                        <option value="">Все сотрудники</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}" data-fio="{{ $employee->fio }}">{{ $employee->fio }}</option>
                                        @endforeach 
                                    </select>
                                </div>

                                @if($user->isAdmin)
                                <div class="d-flex justify-content-end" style="flex: 1;">
                                    <button type="button" class="btn btn-primary" id="new-request-button"
                                            data-bs-toggle="modal" data-bs-target="#newRequestModal">
                                        <i class="bi bi-plus-circle me-1"></i>Новая заявка
                                    </button>
                                </div>
                                @else
                                <div class="alert alert-info hide-me">Добавление заявок доступно только для администраторов</div>
                                @endif
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

                            <table id="requestsTable" class="table table-hover align-middle mb-0" style="min-width: 992px; margin-bottom: 0;">
                                <thead class="bg-dark">
                                <tr>
                                    <th class="th-col" style_="width: 1rem;"></th>
                                    <th class="th-col" style_ ="width: auto;">Дата<br> исполнения</th>
                                    <th class="th-col" style_="width: auto;">Адрес<br>Телефон</th>
                                    <th class="th-col" style_="width: auto;">Комментарии</th>

                                    <th id="brigadeHeader" class="th-col" style_="width: auto;">Бригада <span id="brigadeSortIcon"></span></th>
                                    @if($user->isAdmin)
                                    <th class="th-col" style="width: 1rem;" colspan_="2">Действия с заявкой</th>
                                    @endif
                                    <th class="th-col" style="width: 2rem;"></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($requests as $index => $request)
                                    @php
                                        $rowNumber = $loop->iteration; 
                                        // Get the current loop iteration (1-based index)
                                    @endphp
                                    <tr id="request-{{ $request->id }}" class="align-middle status-row zzzz"
                                        style="--status-color: {{ $request->status_color ?? '#e2e0e6' }}"
                                        data-request-id="{{ $request->id }}">
                                        <!-- Номер заявки -->
                                        <td class="col-number" style_="max-width: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $rowNumber }}</td>

                                        <!-- Дата заявки -->
                                        <td class="col-date">
                                            <div class="col-date__date" style_="font-size: 0.8rem;">{{ $request->execution_date ? \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') : 'Не указана' }}</div>
                                            <div class="col-date__number" style_="font-size: 0.8rem;">{{ $request->number }}</div>
                                        </td>

                                        <!-- Клиент -->
                                        <td class="col-address" style_="max-width: 10rem; overflow: hidden; text-overflow: ellipsis;">
                                            <div class="text-dark col-address__organization"style_="font-size: 0.8rem;">{{ $request->client_organization }}</div>
                                            @if(!empty($request->street))
                                                <small class="text-dark text-truncate_ d-block col-address__street"
                                                       data-bs-toggle="tooltip"
                                                       title="ул. {{ $request->street }}, д. {{ $request->houses }} ({{ $request->district }})">
                                                       @if($request->city_name && $request->city_name !== 'Москва')<strong>{{ $request->city_name }}</strong>, @endif ул. {{ $request->street }}, д. {{ $request->houses }}
                                                </small>
                                            @else
                                                <small class="text-dark text-truncate_ d-block">Адрес не указан</small>
                                            @endif
                                            <div class="text-dark" style="font-size: 0.8rem;"><i>{{ $request->client_fio }}</i></div>
                                            <small style="font-size: 0.8rem;"
                                                class="@if(isset($request->status_name) && $request->status_name !== 'выполнена_') text-success_ fw-bold_ @else text-black @endif text-truncate_ d-block">
                                                    <i>{{ $request->client_phone ?? 'Нет телефона' }}</i>
                                            </small>                              
                                        </td>

                                        <!-- Комментарий -->
                                        <td class="col-comments" style_="max-width: 50rem; overflow: hidden; text-overflow: ellipsis;">
                                            @if(isset($comments_by_request[$request->id]) && count($comments_by_request[$request->id]) > 0)
                                                @php
                                                    $firstComment = $comments_by_request[$request->id][0];
                                                    $commentText = $firstComment->comment;
                                                    $author = $firstComment->author_name;
                                                    $date = \Carbon\Carbon::parse($firstComment->created_at)->format('d.m.Y H:i');
                                                @endphp
                                                <div class="comment-preview small text-dark" 
                                                    style="background-color: white; border: 1px solid gray; border-radius: 3px; padding: 5px; line-height: 16px; font-size: smaller;"
                                                    data-bs-toggle="tooltip" title="{{ $commentText }}">
                                                    <p class="comment-preview-title" style_="font-weight: bold; margin-bottom: 2px;">Печатный комментарий:</p>

                                                    <p class="comment-preview-text">{{ $commentText }}</p>

                                                </div>
                                            @endif
                                            @if(isset($comments_by_request[$request->id]) && count($comments_by_request[$request->id]) >= 1)
                                                <div class="mt-1">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#commentsModal"
                                                            data-request-id="{{ $request->id }}"
                                                            style="position: relative; z-index: 1;">
                                                        <i class="bi bi-chat-left-text me-1"></i><span class="text-comment">Все комментарии</span>
                                                        <span class="badge bg-primary rounded-pill ms-1">
                                                            {{ count($comments_by_request[$request->id]) }}
                                                        </span>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Дата выполнения -->
                                        <!-- <td>
                                            <span
                                                class="brigade-lead-text">{{ $request->operator_name ?? 'Не указан' }}</span><br>
                                            <span
                                                class="brigade-lead-text">{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('d.m.Y') : 'Не указана' }}</span>
                                        </td> -->

                                        <!-- Состав бригады -->
                                        <td class="col-brigade">
                                            @if($request->brigade_id)
                                                @php
                                                    $brigadeMembers = collect($brigadeMembersWithDetails)
                                                        ->where('brigade_id', $request->brigade_id);
                                                @endphp

                                                @if($brigadeMembers->isNotEmpty())
                                                    <div class="col-brigade__div" style="font-size: 0.75rem; line-height: 1.2;">
                                                        @php
                                                            $leaderName = $brigadeMembers->first()->employee_leader_name;
                                                            $brigadeName = $brigadeMembers->first()->brigade_name;
                                                        @endphp

                                                        @if($leaderName)
                                                            <div class="mb-1"><i>{{ $brigadeName }}</i></div>
                                                            <div><strong>{{ shortenName($leaderName) }}</strong>
                                                            @foreach($brigadeMembers as $member)
                                                                , {{ shortenName($member->employee_name) }}
                                                            @endforeach
                                                            </div>
                                                        @endif
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

                                        @if($user->isAdmin)
                                        <!-- Action Buttons Group -->
                                        <td class="col-actions text-nowrap">
                                            <div class="col-actions__div d-flex flex-column gap-1">
                                                @if($request->status_name !== 'выполнена' && $request->status_name !== 'отменена')
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary assign-team-btn p-1"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="left"
                                                            data-bs-title="Назначить бригаду"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-people"></i>
                                                    </button>

                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-success transfer-request-btn p-1"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="left"
                                                            data-bs-title="Перенести заявку"
                                                            style="--bs-btn-color: #198754; --bs-btn-border-color: #198754; --bs-btn-hover-bg: rgba(25, 135, 84, 0.1); --bs-btn-hover-border-color: #198754;"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-arrow-left-right"></i>
                                                    </button>
                                                    
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-danger cancel-request-btn p-1"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="left"
                                                            data-bs-title="Отменить заявку"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                    
                                                    <!-- Инициализация тултипов -->
                                                    <script>
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                                            tooltipTriggerList.map(function (tooltipTriggerEl) {
                                                                return new bootstrap.Tooltip(tooltipTriggerEl);
                                                            });
                                                        });
                                                    </script>
                                                @endif
                                            </div>
                                        </td>
                                        @endif
                                        <!-- Action Buttons -->
                                        <td class="col-actions text-nowrap">
                                            <div class="col-actions__div d-flex flex-column gap-1">
                                                @if($request->status_name !== 'выполнена' && $request->status_name !== 'отменена')
                                                    <button data-request-id="{{ $request->id }}" type="button"
                                                            class="btn btn-sm btn-custom-brown p-1 close-request-btn">
                                                        Закрыть заявку
                                                    </button>
                                                    <!-- <button type="button"
                                                            id="btn-comment"
                                                            class="btn btn-sm btn-outline-primary p-1 comment-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#commentsModal"
                                                            data-request-id="{{ $request->id }}">
                                                        <i class="bi bi-chat-left-text me-1"></i>Комментарий
                                                    </button> -->
                                                @endif
                                                <button data-request-id="{{ $request->id }}" type="button"
                                                        class="btn btn-sm btn-outline-success add-photo-btn"
                                                        onclick="// console.log('Добавить фотоотчет', {{ $request->id }})">
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
                                       value="Бригада" required>
                            </div>

                            <!-- Бригадир выбирается кликом по участнику в списке -->
                            <input type="hidden" id="brigadeLeader" name="leader_id" value="">

                            @if($user->isAdmin)
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
                                <button type="button" id="createBrigadeBtn" data-info-handler="handlerCreateBrigade()[handler.js]" class="btn btn-primary">Создать бригаду
                                </button>
                            </div>
                            @else
                            <div class="alert alert-info">Добавление бригады доступно только для администраторов</div>
                            @endif

                            <div id="brigadeInfo" class="mt-4"> 
                            <!-- Здесь будет отображаться информация о бригадах за выбранный дату -->
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
                                            <label class="form-label" data-field-name="Название города">Название города</label>
                                            <input type="text" name="name" class="form-control" placeholder="Например: Коломна" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" data-field-name="Регион">Регион</label>
                                            <select name="positions" class="form-select" required data-field-name="Должность"></select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-outline-primary">Добавить город</button>
                                        </div>
                                    </form>
                                </div>
                                -->

                                <!-- Адрес -->
                                @if($user->isAdmin)
                                <div>
                                    <!-- Кнопка для открытия модального окна добавления адреса -->
                                    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#assignAddressModal">
                                        <i class="bi bi-plus-circle me-1"></i>Добавить адрес
                                    </button>
                                </div>
                                @else
                                <div class="alert alert-info">Добавление адресов доступно только для администраторов</div>
                                @endif

                                <div id="addressInfo">
                                    <!-- Здесь будет только добавленный адрес -->
                                </div>

                                <!-- Список всех адресов -->
                                <div id="addressesList">
                                    <!-- Здесь будет список всех адресов -->
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <div class="row">
                            @if($user->isAdmin)
                            <div class="mb-3 text-end">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">Новый сотрудник</button>
                            </div>
                            @endif
                            
                            <!-- Модальное окно для нового сотрудника -->
                            <div class="modal fade" id="newEmployeeModal" tabindex="-1" aria-labelledby="newEmployeeModalLabel">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="newEmployeeModalLabel">Добавление нового сотрудника</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Блок для отображения ошибок валидации -->
                                            <div id="formMessages" class="mb-3">
                                                <div class="alert alert-danger d-none">
                                                    <h6 class="alert-heading">Пожалуйста, заполните обязательные поля:</h6>
                                                    <ul class="mb-0">
                                                        <!-- Сюда будут добавляться ошибки валидации -->
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <div id="employeesFormContainer" class="">
                                                <form id="employeeForm" action="{{ route('employee.update') }}" method="POST" class="needs-validation" novalidate>
                                                    @csrf

                                                    <input type="hidden" name="user_id" id="userIdInput" value="">
                                                    
                                                    <h5 class="mb-3 p-2 bg-primary bg-opacity-10 rounded-2 border-bottom">Учетные данные</h5>
                                                    
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="name" class="form-label">Имя пользователя:</label>
                                                                <input type="text" name="name" id="name" class="form-control" placeholder="Введите имя" value="{{ old('name') }}" required autofocus data-field-name="Имя пользователя">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="email" class="form-label">Email:</label>
                                                                <input type="email" name="email" id="email" class="form-control" placeholder="Введите email" autocomplete="username" value="{{ old('email') }}" required data-field-name="Email">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="password" class="form-label">Пароль:</label>
                                                                <input type="password" name="password" id="password" class="form-control" placeholder="Введите пароль" autocomplete="new-password" required data-field-name="Пароль">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="password_confirmation" class="form-label">Подтвердите пароль:</label>
                                                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="Подтвердите пароль" autocomplete="new-password" required data-field-name="Подтверждение пароля">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <h5 class="mb-3 mt-4 p-2 bg-primary bg-opacity-10 rounded-2 border-bottom">Личные данные</h5>

                                                    <div class="row g-3 mt-3">
                                                        <div class="col-md-6">
                                                            <div class="mb-4">
                                                                <label class="form-label">Системная роль</label>
                                                                <select name="role_id" id="roles" class="form-select mb-4" required data-field-name="Системная роль">
                                                                    @foreach ($roles as $role)
                                                                        <option value="{{ $role->id }}" {{ $role->id == 2 ? 'selected' : '' }}>{{ $role->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row g-3 mt-3">
                                                        <div class="col-md-6">
                                                            <div class="mb-4">
                                                                <label class="form-label">ФИО</label>
                                                                <input type="text" name="fio" class="form-control" required data-field-name="ФИО">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Телефон</label>
                                                                <input type="text" name="phone" class="form-control" required data-field-name="Телефон">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label">Должность</label> 
                                                            <select name="position_id" id="position_id" class="form-select mb-4" required data-field-name="Должность">
                                                                <!-- <option value="">Выберите должность</option>     -->
                                                                @foreach ($positions as $position)
                                                                    <option value="{{ $position->id }}">{{ $position->name }}</option>
                                                                @endforeach 
                                                            </select>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Место регистрации</label>
                                                                <input type="text" name="registration_place" class="form-control" required data-field-name="Место регистрации">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Дата рождения</label>
                                                                <input type="date" name="birth_date" class="form-control">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Место рождения</label>
                                                                <input type="text" name="birth_place" class="form-control">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Паспорт (серия и номер)</label>
                                                                <input type="text" name="passport_series" class="form-control">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Кем выдан</label>
                                                                <input type="text" name="passport_issued_by" class="form-control">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Дата выдачи</label>
                                                                <input type="date" name="passport_issued_at" class="form-control">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Код подразделения</label>
                                                                <input type="text" name="passport_department_code" class="form-control">
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Марка машины</label>
                                                                <input type="text" name="car_brand" class="form-control">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Госномер</label>
                                                                <input type="text" name="car_plate" class="form-control">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex gap-2 mt-3 mb-3">
                                                        <button id="autoFillBtn" type="button" class="btn btn-outline-secondary" 
                                                            data-bs-toggle="tooltip" title="Заполнить случайными тестовыми данными">
                                                            Автозаполнение
                                                        </button>

                                                        <button id="saveBtn" type="submit" class="btn btn-primary flex-grow-1">Сохранить</button>
                                                    </div>

                                                    <div id="employeeInfo" style="border: 0px solid red; padding: 10px;">
                                                    </div>

                                                    <!-- <button id="editBtn" type="button" class="btn btn-primary w-100 mt-3 hide-me">Изменить</button> -->

                                                </form>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 flex-nowrap">
                            <!-- Таблица пользователей -->
                            <div id="usersTableContainer" class="col-12">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="mb-0">Список пользователей</h5>
                                    </div>
                                    @if($user->isAdmin)
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <div class="table-container">
                                                <table id="employeesTable" class="table table-hover users-table mb-0">
                                                    <thead>
                                                        <tr class="smaller">
                                                            <th>Имя</th>
                                                            <th>Телефон</th>
                                                            <th>Должность</th>
                                                            <th>Дата / место рождения</th>
                                                            <th>Паспорт / дата выдачи</th>
                                                            <th>Марка машины</th>
                                                            <th>Госномер</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    @foreach($employees as $employee)
                                                        <tr class="small">
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-primary ms-2 edit-employee-btn me-1" 
                                                                        data-employee-id="{{ $employee->id }}" 
                                                                        data-employee-name="{{ $employee->fio }}">
                                                                    <i class="bi bi-pencil-square"></i>
                                                                </button> 

                                                                <button type="button" class="btn btn-sm btn-outline-danger ms-2  delete-employee-btn me-1" 
                                                                        data-employee-id="{{ $employee->id }}" 
                                                                        data-employee-name="{{ $employee->fio }}">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>

                                                                {{ $employee->fio }} | {{ $employee->user_email }}
                                                            </td>
                                                            <td>{{ $employee->phone }}</td>
                                                            <td>{{ $employee->position }}</td>
                                                            <td>{{ $employee->birth_date }}</td>
                                                            <td>
                                                                <div>
                                                                    {{ $employee->series_number }} <br> 
                                                                    {{ $employee->passport_issued_at }} <br> 
                                                                    {{ $employee->passport_issued_by }} <br> 
                                                                    {{ $employee->department_code }}
                                                                </div>
                                                            </td>
                                                            <td>{{ $employee->car_brand }}</td>
                                                            <td>{{ $employee->car_plate }}</td>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    @else
                                    <div class="alert alert-info">Управление пользователями доступно только для администраторов</div>
                                    @endif

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade d-none" id="statuses" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Управление статусами заявок</h4>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                <i class="bi bi-plus-circle me-1"></i>Добавить статус
                            </button>
                        </div>
                        
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;">ID</th>
                                                <th>Название</th>
                                                <th>Цвет</th>
                                                <th>Количество заявок</th>
                                                @if($user->isAdmin)
                                                <th class="text-end">Действия</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody id="statusesList">
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Загрузка...</span>
                                                    </div>
                                                    <p class="mt-2 mb-0">Загрузка списка статусов...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Модальное окно добавления/редактирования статуса -->
                        <div class="modal fade" id="addStatusModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Добавить новый статус</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="statusForm">
                                            <input type="hidden" id="statusId" value="">
                                            <div class="mb-3">
                                                <label for="statusName" class="form-label">Название статуса</label>
                                                <input type="text" class="form-control" id="statusName" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="statusColor" class="form-label">Цвет</label>
                                                <input type="color" class="form-control form-control-color" id="statusColor" value="#e2e0e6" title="Выберите цвет">
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="button" class="btn btn-primary" id="saveStatusBtn">Сохранить</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <h4>Отчеты</h4>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="startDate" class="form-label">Дата начала</label>
                                
                                <div id="calendar-content" class="max-w-400 p-4">
                                    <div id="datepicker-reports-start"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="endDate" class="form-label">Дата окончания</label>
                                <div id="calendar-content" class="max-w-400 p-4">
                                    <div id="datepicker-reports-end"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div id="report-employees-container" class="col-md-4">
                                <!-- Сюда динамически загружаем список сотрудников -->
                            </div>
                        </div>

                        <div class="row">
                            <div id="report-addresses-container" class="col-md-4">
                                <!-- Сюда динамически загружаем список адресов -->
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="report-all-period" name="report-all-period" style="width: 2.5em; height: 1.3em;" value="1">
                                    <label class="form-check-label ms-2 mt-1" for="report-all-period">
                                        <span class="fw-medium">За весь период</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button id="generate-report-btn" class="btn btn-outline-primary mt-3 mb-3">Сгенерировать отчет</button>
                        
                        <!-- Report Table -->
                        <div class="table-responsive t-custom">
                            <table id="requestsReportTable" class="table table-hover align-middle mb-0" style="min-width: 992px; margin-bottom: 0;">
                                <thead class="bg-dark">
                                <tr>
                                <th style="width: 1rem;"></th>
                                    <th style="width: 10rem;">Дата исполнения</th>
                                    <th style="width: 15rem;">Адрес/Телефон</th>
                                    <th style="width: 15rem;">Комментарий</th>
                                    <th id="brigadeHeader" style="width: 20rem;">Бригада <span id="brigadeSortIcon"></span></th>
                                </tr>
                                </thead>
                                <tbody id="requestsReportBody">
                                </tbody>
                            </table>
                        </div>
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

<style>
    /* Custom styles for the switch toggle */
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    .form-switch .form-check-input:focus {
        border-color: rgba(13, 110, 253, 0.25);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    /* Dark theme support */
    [data-bs-theme="dark"] .form-switch .form-check-input {
        background-color: #495057;
        border-color: #6c757d;
    }
    
    [data-bs-theme="dark"] .form-switch .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    [data-bs-theme="dark"] .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
</style>

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

<script type="module">
    import { handleCommentEdit } from './js/form-handlers.js';
    
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
                const requestNumber = requestRow.querySelector('td:nth-child(2) div:last-child').textContent.trim();
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

                        function stringToColor(str) {
                            let hash = 0;
                            for (let i = 0; i < str.length; i++) {
                                hash = str.charCodeAt(i) + ((hash << 5) - hash);
                            }
                            // Смещаем оттенок к зелёной гамме: 120–180°
                            return `hsl(${120 + (hash % 60)},65%,40%)`;
                        }
                        let html = '<div class="list-group list-group-flush">';
                        console.log('Количество комментариев:', comments.length);

                        comments.forEach((comment, index) => {
                            const date = new Date(comment.created_at);
                            const formattedDate = date.toLocaleString('ru-RU', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            const color = comment.author_name === 'Система' ? '#6c757d' : stringToColor(comment.author_name);

                            html += `
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="me-3">
                                                <h6 class="fw-semibold mb-1" style="color:${color}">${comment.author_name}</h6>
                                                <p class="mb-1" data-comment-number="${index + 1}" data-comment-id="${comment.id}" style="word-break: break-all;">${comment.comment}</p>
                                                <small class="text-muted">${formattedDate}</small>
                                                ${index === comments.length - 1 ? `<button class="btn btn-sm btn-outline-primary ms-2 edit-comment-btn">Редактировать</button>` : ''}
                                            </div>
                                        </div>
                                    </div>`;
                        });

                        html += '</div>';
                        container.innerHTML = html;
                        
                        // Добавляем обработчик для кнопки "Редактировать"
                        const editButtons = container.querySelectorAll('.edit-comment-btn');
                        editButtons.forEach(button => {
                            button.addEventListener('click', function(e) {
                                e.preventDefault();
                                const commentElement = this.closest('.list-group-item').querySelector('p[data-comment-number]');
                                const commentNumber = commentElement.getAttribute('data-comment-number');
                                const commentId = commentElement.getAttribute('data-comment-id');
                                
                                // Вызываем функцию из модуля form-handlers.js
                                handleCommentEdit(commentElement, commentId, commentNumber, this);
                            });
                        });
                        
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

<!-- Form Validation -->
<script src="{{ asset('js/form-validator.js') }}"></script>

<!-- Form Handlers -->
<script type="module" src="{{ asset('js/form-handlers.js') }}"></script>

<!-- Event Handlers -->
<script type="module" src="{{ asset('js/handler.js') }}"></script>

<!-- Here modals -->

<!-- Модальное окно комментариев -->
<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentsModalLabel">Комментарии к заявке - #<span
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
                            <div class="col-md-6">
                                <label for="clientOrganization" class="form-label">Организация</label>
                                <input type="text" class="form-control" id="clientOrganization" name="client_organization">
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
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="executionDate" class="form-label">Дата выполнения <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="executionDate" name="execution_date"
                                       required>
                                <!-- min устанавливается динамически через JavaScript -->
                            </div>
                            <div class="col-md-6">
                                <label for="executionTime" class="form-label">Время выполнения</label>
                                <input type="time" class="form-control" id="executionTime" name="execution_time">
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
                            <div class="invalid-feedback">
                                Пожалуйста, выберите адрес из списка
                            </div>
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

                    <div class="mb-3">
                        <!-- <button type="button" class="btn btn-info mb-3" id="fillMockDataBtn" style="margin-top: 14px;">Автозаполнение</button> -->
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="submitRequest">Создать заявку</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                
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

    const newOrganizations = [
        "ООО Альфа",
        "ООО Бета",
        "ООО Гамма",
        "ООО Дельта",
        "ООО Эпсилон",
        "ООО Зета",
        "ООО Эта",
        "ООО Тета",
        "ООО Йота",
        "ООО Каппа",
        "ООО Лямбда",
        "ООО Мю",
        "ООО Ню",
        "ООО Кси",
        "ООО Омикрон",
        "ООО Пи",
        "ООО Ро",
        "ООО Сигма",
        "ООО Тау",
        "ООО Фи"
    ];

    const mockData = [
    // Предыдущий набор данных...
    {name: "Иван Иванов", phone: "+7 (999) 111-11-01", comment: newComments[0], organization: newOrganizations[0]},
    {name: "Мария Петрова", phone: "+7 (999) 111-11-02", comment: newComments[1], organization: newOrganizations[1]},
    {name: "Алексей Смирнов", phone: "+7 (999) 111-11-03", comment: newComments[2], organization: newOrganizations[2]},
    {name: "Елена Кузнецова", phone: "+7 (999) 111-11-04", comment: newComments[3], organization: newOrganizations[3]},
    {name: "Дмитрий Соколов", phone: "+7 (999) 111-11-05", comment: newComments[4], organization: newOrganizations[4]},
    {name: "Ольга Морозова", phone: "+7 (999) 111-11-06", comment: newComments[5], organization: newOrganizations[5]},
    {name: "Николай Васильев", phone: "+7 (999) 111-11-07", comment: newComments[6], organization: newOrganizations[6]},
    {name: "Татьяна Орлова", phone: "+7 (999) 111-11-08", comment: newComments[7], organization: newOrganizations[7]},
    {name: "Сергей Павлов", phone: "+7 (999) 111-11-09", comment: newComments[8], organization: newOrganizations[8]},
    {name: "Анна Федорова", phone: "+7 (999) 111-11-10", comment: newComments[9], organization: newOrganizations[9]},
    {name: "Владимир Беляев", phone: "+7 (999) 111-11-11", comment: newComments[10], organization: newOrganizations[10]},
    {name: "Екатерина Никитина", phone: "+7 (999) 111-11-12", comment: newComments[11], organization: newOrganizations[11]},
    {name: "Андрей Сидоров", phone: "+7 (999) 111-11-13", comment: newComments[12], organization: newOrganizations[12]},
    {name: "Ирина Григорьева", phone: "+7 (999) 111-11-14", comment: newComments[13], organization: newOrganizations[13]},
    {name: "Павел Егоров", phone: "+7 (999) 111-11-15", comment: newComments[14], organization: newOrganizations[14]},
    {name: "Людмила Киселева", phone: "+7 (999) 111-11-16", comment: newComments[15], organization: newOrganizations[15]},
    {name: "Михаил Козлов", phone: "+7 (999) 111-11-17", comment: newComments[16], organization: newOrganizations[16]},
    {name: "Светлана Михайлова", phone: "+7 (999) 111-11-18", comment: newComments[17], organization: newOrganizations[17]},
    {name: "Виктор Фролов", phone: "+7 (999) 111-11-19", comment: newComments[18], organization: newOrganizations[18]},
    {name: "Оксана Дмитриева", phone: "+7 (999) 111-11-20", comment: newComments[19], organization: newOrganizations[19]},
    {name: "Роман Кузьмичёв", phone: "+7 (999) 111-11-21", comment: newComments[0], organization: newOrganizations[0]},
    {name: "Наталья Алексеева", phone: "+7 (999) 111-11-22", comment: newComments[1], organization: newOrganizations[1]},
    {name: "Константин Власов", phone: "+7 (999) 111-11-23", comment: newComments[2], organization: newOrganizations[2]},
    {name: "Алёна Николаева", phone: "+7 (999) 111-11-24", comment: newComments[3], organization: newOrganizations[3]},
    {name: "Игорь Тимофеев", phone: "+7 (999) 111-11-25", comment: newComments[4], organization: newOrganizations[4]},
    {name: "Галина Павлова", phone: "+7 (999) 111-11-26", comment: newComments[5], organization: newOrganizations[5]},
    {name: "Денис Мельников", phone: "+7 (999) 111-11-27", comment: newComments[6], organization: newOrganizations[6]},
    {name: "Алла Сергеева", phone: "+7 (999) 111-11-28", comment: newComments[7], organization: newOrganizations[7]},
    {name: "Василий Лебедев", phone: "+7 (999) 111-11-29", comment: newComments[8], organization: newOrganizations[8]},
    {name: "Евгения Тихонова", phone: "+7 (999) 111-11-30", comment: newComments[9], organization: newOrganizations[9]},
    {name: "Олег Зайцев", phone: "+7 (999) 111-11-31", comment: newComments[10], organization: newOrganizations[10]},
    {name: "Нина Орехова", phone: "+7 (999) 111-11-32", comment: newComments[11], organization: newOrganizations[11]},
    {name: "Вячеслав Соколов", phone: "+7 (999) 111-11-33", comment: newComments[12], organization: newOrganizations[12]},
    {name: "Лариса Денисова", phone: "+7 (999) 111-11-34", comment: newComments[13], organization: newOrganizations[13]},
    {name: "Артур Крылов", phone: "+7 (999) 111-11-35", comment: newComments[14], organization: newOrganizations[14]},
    {name: "Ирина Соловьева", phone: "+7 (999) 111-11-36", comment: newComments[15], organization: newOrganizations[15]},
    {name: "Дмитрий Климов", phone: "+7 (999) 111-11-37", comment: newComments[16], organization: newOrganizations[16]},
    {name: "Марина Белова", phone: "+7 (999) 111-11-38", comment: newComments[17], organization: newOrganizations[17]},
    {name: "Владислав Орлов", phone: "+7 (999) 111-11-39", comment: newComments[18], organization: newOrganizations[18]},
    {name: "Софья Федотова", phone: "+7 (999) 111-11-40", comment: newComments[19], organization: newOrganizations[19]},
    {name: "Егор Панфилов", phone: "+7 (999) 111-11-41", comment: newComments[0], organization: newOrganizations[0]},
    {name: "Олеся Захарова", phone: "+7 (999) 111-11-42", comment: newComments[1], organization: newOrganizations[1]},
    {name: "Максим Ширяев", phone: "+7 (999) 111-11-43", comment: newComments[2], organization: newOrganizations[2]},
    {name: "Вероника Борисова", phone: "+7 (999) 111-11-44", comment: newComments[3], organization: newOrganizations[3]},
    {name: "Артём Дмитриев", phone: "+7 (999) 111-11-45", comment: newComments[4], organization: newOrganizations[4]},
    {name: "Людмила Соколова", phone: "+7 (999) 111-11-46", comment: newComments[5], organization: newOrganizations[5]},
    {name: "Никита Романов", phone: "+7 (999) 111-11-47", comment: newComments[6], organization: newOrganizations[6]},
    {name: "Елена Крылова", phone: "+7 (999) 111-11-48", comment: newComments[7], organization: newOrganizations[7]},
    {name: "Павел Гусев", phone: "+7 (999) 111-11-49", comment: newComments[8], organization: newOrganizations[8]},
    {name: "Алина Иванова", phone: "+7 (999) 111-11-50", comment: newComments[9], organization: newOrganizations[9]}
];

// console.log(mockData); // Выведет полный массив данных

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
        document.getElementById('clientOrganization').value = data.organization || '';
        // Заполняем поле комментария напрямую
        document.getElementById('comment').value = data.comment;

        if (document.getElementById('comment').value.length > 3) {
            document.getElementById('comment').classList.remove('is-invalid');
        }
    });
</script>

<script>
    // Обработчик для модальных окон для исправления проблемы доступности
    document.addEventListener('DOMContentLoaded', function() {
        // Список модальных окон, которые нужно исправить
        const modalIds = ['newEmployeeModal', 'editEmployeeModal'];
        
        // Патч для Bootstrap модальных окон
        const originalModalShow = bootstrap.Modal.prototype.show;
        bootstrap.Modal.prototype.show = function() {
            // Вызываем оригинальный метод
            originalModalShow.apply(this, arguments);
            
            // После открытия модального окна удаляем aria-hidden
            if (this._element && modalIds.includes(this._element.id)) {
                setTimeout(() => {
                    this._element.removeAttribute('aria-hidden');
                    
                    // Добавляем обработчик для предотвращения добавления aria-hidden
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'attributes' && 
                                mutation.attributeName === 'aria-hidden' && 
                                this._element.getAttribute('aria-hidden') === 'true' &&
                                this._isShown) {
                                this._element.removeAttribute('aria-hidden');
                            }
                        });
                    });
                    
                    observer.observe(this._element, { attributes: true });
                    
                    // Сохраняем наблюдатель в элементе, чтобы он не был удален сборщиком мусора
                    this._element._accessibilityObserver = observer;
                }, 0);
            }
        };
        
        // Для каждого модального окна удаляем атрибут aria-hidden из HTML
        modalIds.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.removeAttribute('aria-hidden');
            }
        });
    });

    // Функция для фильтрации строк таблицы по сотруднику
    document.addEventListener('DOMContentLoaded', function() {
        const employeeFilter = document.getElementById('employeeFilter');
        
        // Функция для сокращения ФИО до формата "Фамилия И."
        function shortenNameJs(fullName) {
            if (!fullName) return '';
            
            const parts = fullName.split(' ');
            if (parts.length < 2) return fullName;
            
            const lastName = parts[0];
            const firstName = parts[1];
            
            return lastName + ' ' + firstName.charAt(0) + '.';
        }
        
        if (employeeFilter) {
            employeeFilter.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const selectedFio = selectedOption.getAttribute('data-fio');
                const shortenedSelectedFio = shortenNameJs(selectedFio);
                const requestRows = document.querySelectorAll('#requestsTable tbody tr.status-row');
                const noRequestsRow = document.getElementById('no-requests-row');
                let visibleRowsCount = 0;
                
                // Если выбрано "Все сотрудники", показываем все строки
                if (!this.value) {
                    requestRows.forEach(row => {
                        row.style.display = '';
                        visibleRowsCount++;
                    });
                } else {
                    // Иначе фильтруем по сокращенному ФИО сотрудника
                    requestRows.forEach(row => {
                        // Получаем текст ячейки с составом бригады
                        const brigadeCell = row.querySelector('td:nth-child(5)');
                        if (brigadeCell) {
                            const brigadeCellText = brigadeCell.textContent;
                            
                            // Проверяем, содержит ли состав бригады сокращенное ФИО
                            if (brigadeCellText.includes(shortenedSelectedFio)) {
                                row.style.display = '';
                                visibleRowsCount++;
                            } else {
                                row.style.display = 'none';
                            }
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
                
                // Показываем или скрываем сообщение об отсутствии заявок
                if (visibleRowsCount === 0) {
                    noRequestsRow.classList.remove('d-none');
                } else {
                    noRequestsRow.classList.add('d-none');
                }
            });
        }
    });
    
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
            const today = new Date();
            // today.setDate(today.getDate() + 1);
            document.getElementById('executionDate').valueAsDate = today;

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
                
                // Проверяем, существует ли элемент select
                if (!select) {
                    // console.log('Элемент #operator не найден, пропускаем загрузку операторов');
                    return; // Выходим из функции, если элемент не найден
                }

                // Очищаем список и добавляем текущего пользователя первым
                // select.innerHTML = `
                //     <option value="{{ auth()->id() }}" selected>{{ $user->name }}</option>
                //     <option value="" disabled>──────────</option>
                // `;

                // Добавляем остальных операторов
                // operators
                //     .filter(op => op.id != {{ auth()->id() }}) // Исключаем текущего пользователя, если он есть в списке
                //     .sort((a, b) => (a.fio || '').localeCompare(b.fio || ''))
                //     .forEach(operator => {
                //         const option = document.createElement('option');
                //         option.value = operator.id;
                //         option.textContent = operator.fio || `Оператор #${operator.id}`;
                //         select.appendChild(option);
                //     });

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
            row.className = 'align-middle status-row xxx';
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
                <form id="closeRequestForm" novalidate="novalidate">
                    @csrf
                    <input type="hidden" id="requestIdToClose" name="request_id">
                    <div class="mb-3">
                        <label for="closeComment" class="form-label">Комментарий</label>
                        <textarea class="form-control" id="closeComment" name="comment" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <input type="checkbox" id="uncompletedWorks" name="uncompleted_works">
                        <label for="uncompletedWorks">Недоделанные работы</label>
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

        // Обработчик для поля комментария - удаление класса is-invalid при вводе текста
        document.getElementById('closeComment').addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            }
        });
        
        // Обработчик для кнопки подтверждения в модальном окне
        document.getElementById('confirmCloseRequest').addEventListener('click', async function () {
            const form = document.getElementById('closeRequestForm');
            const commentField = document.getElementById('closeComment');
            
            // Кастомная валидация вместо встроенной
            if (!commentField.value.trim()) {
                // Добавляем класс is-invalid для визуальной индикации ошибки
                commentField.classList.add('is-invalid');
                showAlert('Пожалуйста, заполните комментарий', 'warning');
                return;
            } else {
                // Убираем класс is-invalid, если поле заполнено
                commentField.classList.remove('is-invalid');
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
                        uncompleted_works: document.getElementById('uncompletedWorks').checked,
                        _token: document.querySelector('input[name="_token"]').value
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showAlert('Заявка успешно закрыта', 'success');
                    // Закрываем модальное окно
                    const modal = bootstrap.Modal.getInstance(document.getElementById('closeRequestModal'));
                    modal.hide();


                    console.log(result);

                    // Обновляем страницу
                    setTimeout(() => location.reload(), 2000);
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

</script>

<!-- Модальное окно для редактирования сотрудника -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEmployeeModalLabel">Редактирование сотрудника</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <!-- Здесь будет форма редактирования сотрудника -->
                <div id="editEmployeeContent">
                    <form id="employeeFormUpdate" action="{{ route('employee.update') }}" method="POST" class="needs-validation" novalidate>
                        @csrf

                        <input type="hidden" name="user_id_update" id="userIdInputUpdate" value="">
                        <input type="hidden" name="employee_id_update" id="employeeIdInputUpdate" value="">
                        
                        <h5 class="mb-3 mt-4 p-2 bg-primary bg-opacity-10 rounded-2 border-bottom">Личные данные</h5>

                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label">Системная роль</label>
                                    <select name="role_id_update" id="roleSelectUpdate" class="form-select mb-4" required data-field-name="Системная роль">
                                    <option value="">Выберите системную роль</option>    
                                    @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label class="form-label">ФИО</label>
                                    <input type="text" name="fio_update" id="fioInputUpdate" class="form-control" required data-field-name="ФИО">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Телефон</label>
                                    <input type="text" name="phone_update" id="phoneInputUpdate" class="form-control" required data-field-name="Телефон">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Должность</label> 
                                <select name="position_id_update" id="positionSelectUpdate" class="form-select mb-4" required data-field-name="Должность">
                                    @foreach ($positions as $position)
                                        <option value="{{ $position->id }}">{{ $position->name }}</option>
                                    @endforeach 
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Место регистрации</label>
                                    <input type="text" name="registration_place_update" id="registrationPlaceInputUpdate" class="form-control" required data-field-name="Место регистрации">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Дата рождения</label>
                                    <input type="date" name="birth_date_update" id="birthDateInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Место рождения</label>
                                    <input type="text" name="birth_place_update" id="birthPlaceInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Паспорт (серия и номер)</label>
                                    <input type="text" name="passport_series_update" id="passportSeriesInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Кем выдан</label>
                                    <input type="text" name="passport_issued_by_update" id="passportIssuedByInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Дата выдачи</label>
                                    <input type="date" name="passport_issued_at_update" id="passportIssuedAtInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Код подразделения</label>
                                    <input type="text" name="passport_department_code_update" id="passportDepartmentCodeInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Марка машины</label>
                                    <input type="text" name="car_brand_update" id="carBrandInputUpdate" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Госномер</label>
                                    <input type="text" name="car_plate_update" id="carLicensePlateInputUpdate" class="form-control">
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="saveEmployeeChanges">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

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
<!-- Bootstrap JS уже подключен через CDN выше -->

<script src="{{ asset('js/brigades.js') }}"></script>
<script src="{{ asset('js/calendar.js') }}"></script>
<script src="{{ asset('js/brigade-sort.js') }}"></script>
<link rel="stylesheet" href="{{ asset('css/brigade-sort.css') }}">

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

<!-- Модальное окно для назначения бригады -->
<div class="modal fade" id="assign-team-modal" tabindex="-1" aria-labelledby="assignTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignTeamModalLabel">Назначение бригады</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Выберите бригаду для назначения на заявку:</p>
                <div class="mb-3">
                    <select class="form-select" id="assign-team-select">
                        <option value="" selected>Выберите бригаду</option>
                        <!-- Опции будут загружены динамически -->
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirm-assign-team-btn">Назначить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления адреса -->
<div class="modal fade" id="assignAddressModal" tabindex="-1" aria-labelledby="assignAddressModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignAddressModalLabel">Добавление адреса</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <form id="addressForm" class="row g-2 align-items-end" method="POST" action="{{ route('address.add') }}">
                    @csrf
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Город <span class="text-danger">*</span></label>
                        <select name="city_id" id="citySelect" class="form-select" data-required="true">
                            <option value="" disabled>Выберите город</option>
                            <option value="1" selected>Москва</option>
                            <!-- Остальные города будут загружены динамически -->
                        </select>
                        <div class="invalid-feedback">Пожалуйста, выберите город</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Район <span class="text-danger">*</span></label>
                        <input type="text" name="district" class="form-control" placeholder="Район" data-required="true">
                        <div class="invalid-feedback">Пожалуйста, укажите район</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Улица <span class="text-danger">*</span></label>
                        <input type="text" name="street" class="form-control" placeholder="Улица" data-required="true">
                        <div class="invalid-feedback">Пожалуйста, укажите улицу</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Дом <span class="text-danger">*</span></label>
                        <input type="text" name="houses" class="form-control" placeholder="Дом" data-required="true">
                        <div class="invalid-feedback">Пожалуйста, укажите номер дома</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Ответственное лицо</label>
                        <input type="text" name="responsible_person" class="form-control" placeholder="ФИО ответственного лица">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Комментарий</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Дополнительная информация"></textarea>
                    </div>
                </form>
                <div class="mt-3">
                    <button id="testFillBtn" type="button" class="btn btn-secondary" style="display: none;">Автозаполнение</button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveAddressBtn">Сохранить</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Загрузка городов при открытии модального окна
    document.getElementById('assignAddressModal').addEventListener('show.bs.modal', function () {
        loadCities();
    });

    // Функция загрузки городов
    async function loadCities() {
        try {
            const response = await fetch('/api/cities');
            const cities = await response.json();
            const select = document.getElementById('citySelect');
            
            // Сохраняем Москву как выбранный город по умолчанию
            select.innerHTML = `
                <option value="" disabled>Выберите город</option>
                <option value="1" selected>Москва</option>
            `;
            
            // Добавляем остальные города, кроме Москвы
            cities.forEach(city => {
                // Пропускаем Москву, так как она уже добавлена
                if (city.id != 1) {
                    const option = document.createElement('option');
                    option.value = city.id;
                    option.textContent = city.name;
                    select.appendChild(option);
                }
            });
        } catch (error) {
            console.error('Ошибка при загрузке городов:', error);
        }
    }

    // Автозаполнение формы
    document.getElementById('testFillBtn').addEventListener('click', function() {
        const form = document.getElementById('addressForm');
        form.querySelector('input[name="street"]').value = 'Ленина';
        form.querySelector('input[name="houses"]').value = '10';
        form.querySelector('input[name="district"]').value = 'Центральный';
        
        // Выбираем первый город из списка
        const citySelect = document.getElementById('citySelect');
        if (citySelect.options.length > 1) {
            citySelect.selectedIndex = 1; // Первый город после placeholder
        }
        
        // Показываем уведомление
        const toastEl = document.getElementById('autoFillToast');
        const toast = new bootstrap.Toast(toastEl);
        document.getElementById('autoFillToastBody').textContent = 'Форма автоматически заполнена тестовыми данными';
        toast.show();
    });

    // Функция валидации формы адреса
    function validateAddressForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[data-required="true"]');

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                field.classList.remove('is-valid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            }
        });

        return isValid;
    }

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

    // Функция форматирования адреса для отображения в уведомлении
    function formatAddressInfo(address) {
        return `Город: ${address.city || '-'}, Район: ${address.district || '-'}, Улица: ${address.street || '-'}, Дом: ${address.houses || '-'}`;
    }

    // Функция для загрузки и отображения списка адресов в выпадающем списке
    async function loadAddresses() {
        try {
            const addressesList = document.getElementById('addressesList');
            if (!addressesList) return;
            
            // Показываем индикатор загрузки
            addressesList.innerHTML = `
                <div class="d-flex justify-content-center my-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            `;
            
            // Загружаем список адресов
            const response = await fetch('/api/geo/addresses', {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });
            
            if (!response.ok) {
                throw new Error('Ошибка при загрузке адресов');
            }
            
            const addresses = await response.json();
            
            // Если адресов нет, показываем соответствующее сообщение
            if (!addresses || addresses.length === 0) {
                addressesList.innerHTML = `
                    <div class="alert alert-info">
                        Список адресов пуст. Добавьте новый адрес, используя кнопку "Добавить адрес".
                    </div>
                `;
                return;
            }
            
            // Формируем выпадающий список с адресами
            let html = `
                <div class="form-group mb-3">
                    <label for="addressSelect" class="form-label">Выберите адрес:</label>
                    <select id="addressSelect" class="form-select">
                        <option value="" selected disabled>Выберите адрес из списка</option>
            `;
            
            // Сортируем адреса по городу, району и улице
            addresses.sort((a, b) => {
                if (a.city !== b.city) return a.city.localeCompare(b.city);
                if (a.district !== b.district) return a.district.localeCompare(b.district);
                if (a.street !== b.street) return a.street.localeCompare(b.street);
                return a.houses.localeCompare(b.houses);
            });
            
            // Добавляем опции в выпадающий список
            addresses.forEach(address => {
                const addressText = `${address.city}, ${address.district}, ул. ${address.street}, д. ${address.houses}`;
                html += `<option value="${address.id}">${addressText}</option>`;
            });
            
            html += `
                    </select>
                    <div class="form-text">Всего адресов: ${addresses.length}</div>
                </div>
            `;
            
            addressesList.innerHTML = html;
            
            // Добавляем обработчик события для выбора адреса
            const addressSelect = document.getElementById('addressSelect');
            
            // Инициализируем кастомный селект с поиском после обновления списка
            // Функция для попыток инициализации с повторными попытками
            function tryInitCustomSelect(attempts = 0) {
                if (typeof window.initCustomSelect === 'function') {
                    // console.log('Инициализация кастомного селекта после обновления списка адресов');
                    window.initCustomSelect("addressSelect", "Выберите адрес из списка");
                } else {
                    console.log(`Попытка ${attempts + 1}: Функция initCustomSelect не найдена, повторная попытка через 500мс`);
                    if (attempts < 5) { // Максимум 5 попыток
                        setTimeout(() => tryInitCustomSelect(attempts + 1), 500);
                    } else {
                        console.error('Не удалось найти функцию initCustomSelect после 5 попыток');
                    }
                }
            }
            
            // Запускаем инициализацию с небольшой задержкой, чтобы DOM успел обновиться
            setTimeout(() => tryInitCustomSelect(), 200);
            if (addressSelect) {
                addressSelect.addEventListener('change', function() {
                    const selectedAddressId = this.value;
                    if (!selectedAddressId) return;
                    
                    // Находим выбранный адрес
                    const selectedAddress = addresses.find(addr => addr.id == selectedAddressId);
                    if (!selectedAddress) return;
                    
                    // Отображаем информацию о выбранном адресе
                    const addressInfoBlock = document.getElementById('addressInfo');
                    if (addressInfoBlock) {
                        let addressHtml = `
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <strong>Выбранный адрес</strong>
                                </div>
                                <div class="card-body">
                                    <p><strong>Город:</strong> ${selectedAddress.city || '-'}</p>
                                    <p><strong>Район:</strong> ${selectedAddress.district || '-'}</p>
                                    <p><strong>Улица:</strong> ${selectedAddress.street || '-'}</p>
                                    <p><strong>Дом:</strong> ${selectedAddress.houses || '-'}</p>
                                    <p class="text-muted"><small>Идентификатор адреса: ${selectedAddress.id}</small></p>
                                </div>
                            </div>
                        `;
                        addressInfoBlock.innerHTML = addressHtml;
                    }
                });
            }
            
        } catch (error) {
            console.error('Ошибка при загрузке адресов:', error);
            document.getElementById('addressesList').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка при загрузке адресов. Пожалуйста, попробуйте обновить страницу.
                </div>
            `;
        }
    }

    // Добавляем валидацию при вводе данных
    document.addEventListener('DOMContentLoaded', function() {
        // Загружаем список адресов при загрузке страницы
        loadAddresses();
        
        const addressForm = document.getElementById('addressForm');
        if (addressForm) {
            const requiredFields = addressForm.querySelectorAll('[data-required="true"]');
            requiredFields.forEach(field => {
                field.addEventListener('input', function() {
                    if (!this.value.trim()) {
                        this.classList.add('is-invalid');
                        this.classList.remove('is-valid');
                    } else {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
            });
        }
    });

    // Обработка отправки формы
    document.getElementById('saveAddressBtn').addEventListener('click', async function() {
        const form = document.getElementById('addressForm');
        const submitBtn = this;
        const originalBtnText = submitBtn.innerHTML;
        
        // Проверка валидности формы
        let isValid = validateAddressForm(form);
        if (!isValid) {
            showAlert('Пожалуйста, заполните все обязательные поля', 'warning');
            return;
        }
        
        const formData = new FormData(form);
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';
            
            const response = await fetch('/address/add', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('assignAddressModal'));
                modal.hide();
                
                // Очищаем форму и убираем классы валидации
                form.reset();
                form.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
                    field.classList.remove('is-valid');
                    field.classList.remove('is-invalid');
                });
                
                // Показываем уведомление об успехе
                showAlert('Адрес успешно добавлен', 'success');
                
                // Отображаем информацию о добавленном адресе в блоке addressInfo
                if (result.address) {
                    const addressInfoBlock = document.getElementById('addressInfo');
                    
                    // Формируем текст с информацией об адресе
                    let addressHtml = `
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <strong>Добавленный адрес</strong>
                            </div>
                            <div class="card-body">
                                <p><strong>Город:</strong> ${result.address.city || '-'}</p>
                                <p><strong>Район:</strong> ${result.address.district || '-'}</p>
                                <p><strong>Улица:</strong> ${result.address.street || '-'}</p>
                                <p><strong>Дом:</strong> ${result.address.houses || '-'}</p>
                    `;
                    
                    // Добавляем необязательные поля, если они заполнены
                    if (result.address.responsible_person) {
                        addressHtml += `<p><strong>Ответственное лицо:</strong> ${result.address.responsible_person}</p>`;
                    }
                    
                    if (result.address.comments) {
                        addressHtml += `<p><strong>Комментарий:</strong> ${result.address.comments}</p>`;
                    }
                    
                    // Добавляем ID адреса
                    addressHtml += `
                                <p class="text-muted"><small>Идентификатор адреса: ${result.address.id}</small></p>
                            </div>
                        </div>
                    `;
                    
                    // Вставляем текст в блок
                    addressInfoBlock.innerHTML = addressHtml;
                }

                // Обновляем список адресов
                loadAddresses();
            } else {
                showAlert(result.message || 'Ошибка при добавлении адреса', 'danger');
            }
        } catch (error) {
            console.error('Ошибка при добавлении адреса:', error);
            showAlert('Произошла ошибка при добавлении адреса', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }); 
</script>


</body>

</html>
