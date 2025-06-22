<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система управления заявками</title>
    <!-- Bootstrap 5 CSS -->
    <link href="{{ asset('css/bootstrap.css') }}" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.png') }}">    
    
    <!-- Проверка загрузки Bootstrap -->
    <script>
        // Проверяем, загружен ли Bootstrap
        window.addEventListener('load', function() {
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
    <style>
        /* Styles for the dark theme in the modal window */
        [data-bs-theme="dark"] .brigade-details,
        [data-bs-theme="dark"] .brigade-details h4,
        [data-bs-theme="dark"] .brigade-details h5,
        [data-bs-theme="dark"] .brigade-details h6,
        [data-bs-theme="dark"] .brigade-details p,
        [data-bs-theme="dark"] .brigade-details .card,
        [data-bs-theme="dark"] .brigade-details .card-body,
        [data-bs-theme="dark"] .brigade-details .list-group-item {
            color: #f8f9fa !important;
        }
        
        [data-bs-theme="dark"] .brigade-details .text-muted {
            color: #adb5bd !important;
        }
        
        [data-bs-theme="dark"] .brigade-details .card {
            background-color: #2b3035 !important;
            border-color: #373b3e !important;
        }
        
        [data-bs-theme="dark"] .brigade-details .card-header {
            background-color: #212529 !important;
            border-bottom-color: #373b3e !important;
        }
        
        [data-bs-theme="dark"] .brigade-details .list-group-item {
            background-color: #2b3035 !important;
            border-color: #373b3e !important;
        }
        
        [data-bs-theme="dark"] .brigade-details .list-group-item.bg-light {
            background-color: #343a40 !important;
        }
        
        /* Стили для кнопки комментариев */
        .comment-btn-hover:hover,
        .comment-btn-hover:hover i {
            color: white !important;
            transition: color 0.2s ease-in-out;
        }
        
        [data-bs-theme="dark"] .brigade-details .card-header h5 {
            color: #fff !important;
        }
    </style>
</head>

<body>
    <div id="app-container" class="container-fluid g-0">
        <div id="main-layout" class="row g-0" style="min-height: 100vh;">
            <!-- Left Sidebar with Calendar -->
            <div id="sidebar" class="col-auto sidebar p-3">
                <h4 class="mb-4">Календарь</h4>
                <div id="datepicker"></div>
                <div class="mt-3 d-none">
                    <div class="form-group">
                        <label for="dateInput" class="form-label">Выберите дату:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="dateInput" placeholder="дд.мм.гггг">
                            <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                        </div>
                    </div>
                </div>
                @if (!empty($request_statuses))
                    <h4 class="mt-5 mb-3">Статусы заявок</h4>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($request_statuses as $status)
                            <div class="d-flex align-items-center rounded-3 bg-gray-200 dark:bg-gray-700">
                                <div class="me-3 w-7 h-7 rounded-sm"
                                    style="width: 8rem; height: 2rem; background-color: {{ $status->color ?? '#ccc' }};">
                                </div>
                                <span>{{ $status->name }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mt-3">Нет доступных статусов заявок.</p>
                @endif

            </div>

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
                                data-bs-target="#requests" type="button" role="tab">Заявки</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="teams-tab" data-bs-toggle="tab" data-bs-target="#teams"
                                type="button" role="tab">Бригады</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses"
                                type="button" role="tab">Адреса</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users"
                                type="button" role="tab">Пользователи</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports"
                                type="button" role="tab">Отчеты</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="mainTabsContent">
                        <div class="tab-pane fade show active" id="requests" role="tabpanel">
                            <h4>Заявки</h4>
                            
                            <!-- Filter Section -->
                            <div class="mb-3">
                                <div class="d-flex" style="width: fit-content; max-width: 100%;">
                                    <div id="request-filters" class="d-flex align-items-center" style="height: 2rem; border: 1px solid var(--card-border, #dee2e6); border-radius: 0.25rem 0 0 0.25rem; padding: 0 0.5rem; background-color: var(--card-bg, #ffffff);">
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
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="reset-filters-button" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Сброс
                                    </button>
                                </div>
                            </div>

                            <!-- max-height: 33vh; overflow: auto; -webkit-overflow-scrolling: touch; -->

                            @if ($requests)
                                <div id="requests-table-container" class="table-responsive mt-4" style="border: 1px solid rgb(110, 113, 117); border-radius: 0.375rem;">
                                    <table class="table table-hover align-middle mb-0" style="min-width: 992px; margin-bottom: 0;">
                                        <style>
                                            #requests-table-container thead th {
                                                /* position: sticky;
                                                top: 0; */
                                                /* background-color: #f8f9fa; */
                                                /* z-index: 10; */
                                            }
                                            [data-bs-theme="dark"] #requests-table-container thead th {
                                                background-color: #343a40;
                                            }
                                            [data-bs-theme="dark"] thead.bg-dark,
                                            [data-bs-theme="dark"] thead.bg-dark th,
                                            [data-bs-theme="dark"] thead.bg-dark * {
                                                color: #fff !important;
                                            }
                                            [data-bs-theme="dark"] thead.bg-dark {
                                                --bs-table-bg: #343a40;
                                                --bs-table-color: #fff;
                                                background-color: #343a40;
                                            }

                                            /* Стили для чекбоксов */
                                            .request-checkbox {
                                                background-color: rgba(255, 255, 255, 0.5) !important;
                                                border-color: rgba(0, 0, 0, 0.25) !important;
                                                opacity: 0.7;
                                                transition: opacity 0.2s ease;
                                            }
                                            
                                            .request-checkbox:checked {
                                                background-color: #0d6efd !important;
                                                border-color: #0d6efd !important;
                                                opacity: 1;
                                            }
                                            
                                            .request-checkbox:not(:checked) {
                                                background-color: transparent !important;
                                                border-color: rgba(0, 0, 0, 0.25) !important;
                                            }
                                            
                                            tr:hover .request-checkbox {
                                                opacity: 1;
                                            }
                                        </style>
                                        <thead class="bg-dark">
                                            <tr>
                                                <th style="width: 1rem;"></th>
                                                <th style="width: 1rem;"></th>
                                                <th style="width: 10rem;">Дата</th>
                                                <th>Комментарий</th>
                                                <th style="width: 15rem;">Адрес/Телефон</th>
                                                <th>Добавлено</th>
                                                <th>Бригада</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <style>
                                                /* Цвет текста в таблице */
                                                .table {
                                                    --bs-table-color: #000000;
                                                }
                                                
                                                /* Цвет текста при наведении */
                                                .table-hover tbody tr:hover td,
                                                .table-hover tbody tr:hover td * {
                                                    color: #000000 !important;
                                                }

                                                /* Стиль для текста бригадира - всегда черный */
                                                .brigade-lead-text {
                                                    /* color: #000000 !important; */
                                                }
                                                .table-hover tbody tr:hover {
                                                    /* --bs-table-color: #000000 !important;
                                                    color: #000000 !important; */
                                                }

                                                .status-row {
                                                    --bs-table-bg: var(--status-color);
                                                    background-color: var(--status-color);
                                                }

                                            </style>

                                            @foreach ($requests as $request)
                                                <tr class="align-middle status-row" style="--status-color: {{ $request->status_color }}">
                                                    <td style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $request->id }}</td>
                                                    <td class="text-center" style="width: 1rem;">
                                                        <input type="checkbox" id="request-{{ $request->id }}" class="form-check-input request-checkbox" value="{{ $request->id }}" aria-label="Выбрать заявку">
                                                    </td>
                                                    <!-- Дата и номер заявки -->
                                                    <td>
                                                        <div>{{ \Carbon\Carbon::parse($request->request_date)->format('d.m.Y') }}</div>
                                                        <div class="text-dark" style="font-size: 0.8rem;">{{ $request->number }}</div>
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
                                                            <div class="comment-preview small text-dark" data-bs-toggle="tooltip" title="{{ $commentText }}">
                                                                {{ $commentText }}
                                                            </div>
                                                        @endif
                                                        @if(isset($comments_by_request[$request->id]) && count($comments_by_request[$request->id]) > 1)
                                                            <div class="mt-1">
                                                                <button type="button" 
                                                                        class="btn btn-sm btn-outline-secondary view-comments-btn p-1" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#commentsModal"
                                                                        data-request-id="{{ $request->id }}"
                                                                        style="position: relative; z-index: 1;"
                                                                        onmouseover="this.style.setProperty('color', 'white', 'important'); this.querySelector('i').style.setProperty('color', 'white', 'important'); this.style.setProperty('background-color', '#6c757d', 'important'); this.style.setProperty('border-color', '#6c757d', 'important');"
                                                                        onmouseout="this.style.removeProperty('color'); this.querySelector('i').style.removeProperty('color'); this.style.removeProperty('background-color'); this.style.removeProperty('border-color');">
                                                                    <i class="bi bi-chat-left-text me-1"></i>Все комментарии
                                                                    <span class="badge bg-primary rounded-pill ms-1">
                                                                        {{ count($comments_by_request[$request->id]) }}
                                                                    </span>
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <!-- Клиент -->
                                                    <td style="width: 10rem; max-width: 10rem; overflow: hidden; text-overflow: ellipsis;">
                                                        <div class="text-truncate">{{ $request->client_fio ?? 'Неизвестный клиент' }}</div>
                                                        <small class="@if(isset($request->status_name) && $request->status_name !== 'выполнена_') text-success_ fw-bold_ @else text-black @endif text-truncate d-block">
                                                            {{ $request->client_phone ?? 'Нет телефона' }}
                                                        </small>
                                                    </td>

                                                    <!-- Дата выполнения -->
                                                    <td>{{ \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') }}</td>

                                                    <!-- Состав бригады -->
                                                    <td>
                                                        @if($request->brigade_id)
                                                            <button type="button" class="btn btn-sm btn-outline-primary view-brigade-btn mb-1" data-bs-toggle="modal" data-bs-target="#brigadeModal" data-brigade-id="{{ $request->brigade_id }}">
                                                                <i class="bi bi-people me-1"></i>Состав бригады
                                                            </button>
                                                        @else
                                                            <small class="text-muted d-block mb-1">Не назначена</small>
                                                        @endif
                                                        

                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="mt-4 text-muted">Нет заявок для отображения.</p>
                            @endif

                        </div>
                        <div class="tab-pane fade" id="teams" role="tabpanel">
                            <h4>Бригады</h4>
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
                            <p class="card-text">Используйте календарь слева для выбора даты.</p>
                            <p>Выбранная дата: <span id="selectedDate" class="fw-bold">не выбрана</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно комментариев -->
    <div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="commentsModalLabel">Комментарии к заявке #<span id="commentsRequestId"></span></h5>
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
                            <input type="text" name="comment" class="form-control" placeholder="Напишите комментарий..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Отправить
                            </button>
                        </div>
                    </form>
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
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Обработка открытия модального окна комментариев
        document.addEventListener('DOMContentLoaded', function() {
            const commentsModal = document.getElementById('commentsModal');
            
            if (commentsModal) {
                commentsModal.addEventListener('show.bs.modal', function(event) {
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
                commentForm.addEventListener('submit', function(e) {
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
                            // Обновляем список комментариев
                            loadComments(requestId);
                            // Очищаем поле ввода
                            commentForm.querySelector('input[name="comment"]').value = '';
                            
                            // Показываем уведомление об успехе
                            showAlert('Комментарий успешно добавлен', 'success');
                            
                            // Обновляем счетчик комментариев
                            updateCommentsBadge(requestId);
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        showAlert('Произошла ошибка при добавлении комментария', 'danger');
                    });
                });
            }
            
            // Функция загрузки комментариев
            function loadComments(requestId) {
                const container = document.getElementById('commentsContainer');
                
                // Показываем индикатор загрузки
                container.innerHTML = `
                    <div class="text-center my-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                    </div>
                `;
                
                // Загружаем комментарии
                fetch(`/api/requests/${requestId}/comments`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            let html = `
                                <div class="comments-list">
                                    <div class="mb-3">
                                        <span class="fw-bold">Комментариев: ${data.length}</span>
                                    </div>
                            `;
                            
                            data.forEach(comment => {
                                html += `
                                    <div class="card mb-3">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted">
                                                    ${new Date(comment.created_at).toLocaleDateString('ru-RU')} 
                                                    ${new Date(comment.created_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}
                                                </span>
                                            </div>
                                            <p class="mb-0">${comment.comment}</p>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                            container.innerHTML = html;
                        } else {
                            container.innerHTML = '<div class="text-muted text-center py-4">Нет комментариев</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке комментариев:', error);
                        container.innerHTML = `
                            <div class="alert alert-danger">
                                Не удалось загрузить комментарии. Пожалуйста, попробуйте позже.
                            </div>
                        `;
                    });
            }
            
            // Функция обновления счетчика комментариев
            function updateCommentsBadge(requestId) {
                const badge = document.querySelector(`button[data-request-id="${requestId}"] .badge`);
                if (badge) {
                    const currentCount = parseInt(badge.textContent.trim()) || 0;
                    badge.textContent = currentCount + 1;
                } else {
                    const button = document.querySelector(`button[data-request-id="${requestId}"]`);
                    if (button) {
                        button.innerHTML += `
                            <span class="badge bg-primary rounded-pill ms-1">1</span>
                        `;
                    }
                }
            }
            
            // Функция показа уведомлений
            function showAlert(message, type = 'success') {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
                alertDiv.style.zIndex = '1060';
                alertDiv.role = 'alert';
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                document.body.appendChild(alertDiv);
                
                // Автоматически скрываем уведомление через 5 секунд
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertDiv);
                    bsAlert.close();
                }, 5000);
            }
        });
    </script>
    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.ru.min.js"></script>
    
    <!-- Custom Application JS -->
    <script src="{{ asset('js/app.js') }}"></script>
    
    <!-- Event Handlers -->
    <script src="{{ asset('js/handler.js') }}"></script>
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
</body>

</html>