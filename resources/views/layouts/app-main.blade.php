<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@yield('title', 'Система управления заявками')</title>
    
    <!-- Bootstrap CSS -->
    <link href="{{ asset('css/bootstrap.css') }}?v={{ time() }}" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    
    <!-- Custom CSS -->
    <link href="{{ asset('js/editor.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ asset('css/table-styles.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ asset('css/dark-theme.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}?v={{ time() }}" rel="stylesheet">
    <link href="{{ asset('css/mobile-requests.css') }}?v={{ time() }}" rel="stylesheet">
    <link id="desktop-view-css" href="{{ asset('css/desktop-view.css') }}?v={{ time() }}" rel="stylesheet" disabled>
    <link href="{{ asset('css/new-design.css') }}?v={{ time() }}" rel="stylesheet">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.png') }}">
    
    <!-- User Roles Initialization -->
    <script>
        window.App = window.App || {};
        window.App.user = {
            id: @json(auth()->user()->id ?? null),
            roles: @json(auth()->user()->roles ?? []),
            isAdmin: @json(auth()->user()->isAdmin ?? false),
            isUser: @json(auth()->user()->isUser ?? false),
            isFitter: @json(auth()->user()->isFitter ?? false)
        };
        window.App.role = (window.App.user.isAdmin && 'admin')
            || (window.App.user.isFitter && 'fitter')
            || (window.App.user.isUser && 'user')
            || 'guest';

        console.log('Current role:', window.App.role);
    </script>
    
    <!-- Bootstrap Load Check -->
    <script>
        window.addEventListener('load', function () {
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap не загружен!');
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger position-fixed top-0 start-0 w-100 rounded-0 m-0';
                alertDiv.style.zIndex = '2000';
                alertDiv.innerHTML = `
                    <div class="container">
                        <strong>Ошибка загрузки!</strong> Не удалось загрузить необходимые компоненты Bootstrap.
                        Пожалуйста, обновите страницу или проверьте подключение к интернету.
                    </div>`;
                document.body.prepend(alertDiv);
            }
        });
    </script>

    <style>
        /* Перенос специфичных стилей из welcome.blade.php */
        .brigade-leader {
            border: 2px solid #dc3545 !important;
            position: relative;
        }
        .brigade-leader::after {
            content: 'Бригадир';
            position: absolute;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            background-color: #dc3545;
            color: white;
            padding: 0 5px;
            border-radius: 3px;
            white-space: nowrap;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .invalid-feedback {
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        /* Исправлен селектор ID таблицы и добавлен сброс переменных Bootstrap для цвета текста */
        #requestsReportTable tbody tr.status-row {
            --status-color: #ffffff;
            background-color: var(--status-color);
            color: #000 !important;
            --bs-table-striped-color: #000; /* Переопределение цвета текста для striped строк */
            --bs-table-color-type: #000;
            transition: background-color 0.2s;
        }
        /* Временно отключено разбавление цвета белым и эффект наведения */
        #requestsReportTable tbody tr.status-row[style*="--status-color"] {
            background-color: var(--status-color);
        }
        #requestsReportTable tbody tr.status-row:hover {
            background-color: var(--status-color) !important;
            color: #000 !important;
        }
        #requestsReportTable tbody tr.status-row > * {
            background-color: transparent !important;
            color: #000 !important; /* Принудительный черный цвет для всех ячеек */
            box-shadow: none !important; /* Убираем возможные тени от striped */
        }
        .comment-preview {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
            background-color: white; border: 1px solid gray; border-radius: 3px; padding: 5px; line-height: 16px; font-size: smaller;
        }
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
        .comment-preview-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        /* Стили для Navbar-Menu (имитация вкладок) */
        .navbar-nav .nav-link {
            cursor: pointer;
        }
        .navbar-nav .nav-link.active {
            color: #fff !important;
            font-weight: bold;
        }
        
        /* Help button styles */
        #reports-help-btn {
            color: #7a7a7a;
            border-color: #ccc;
        }
        #reports-help-btn:hover, #reports-help-btn:focus {
            color: #666;
            border-color: #bbb;
            background-color: rgba(204, 204, 204, 0.15);
        }
        [data-bs-theme="dark"] #reports-help-btn {
            color: #ccc;
            border-color: #888;
        }
        
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        [data-bs-theme="dark"] .form-switch .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @stack('styles')
</head>
<body>
    <div id="app-container" class="d-flex flex-column min-vh-100">
        <!-- Main Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">{{ config('app.name', 'LAN Install') }}</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0" id="mainTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" role="tab" aria-controls="requests" aria-selected="true">
                                <i class="bi bi-list-task me-1"></i>Заявки
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="teams-tab" data-bs-toggle="tab" data-bs-target="#teams" role="tab" aria-controls="teams" aria-selected="false">
                                <i class="bi bi-people me-1"></i>Бригады
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" role="tab" aria-controls="addresses" aria-selected="false">
                                <i class="bi bi-geo-alt me-1"></i>Адреса
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" role="tab" aria-controls="users" aria-selected="false">
                                <i class="bi bi-person-badge me-1"></i>Пользователи
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" role="tab" aria-controls="reports" aria-selected="false">
                                <i class="bi bi-file-earmark-bar-graph me-1"></i>Отчеты
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="planning-tab" data-bs-toggle="tab" data-bs-target="#planning" role="tab" aria-controls="planning" aria-selected="false">
                                <i class="bi bi-calendar-range me-1"></i>Планирование
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="request-types-tab" data-bs-toggle="tab" data-bs-target="#request-types" role="tab" aria-controls="request-types" aria-selected="false">
                                <i class="bi bi-tags me-1"></i>Типы заявок
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Right Side Menu -->
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                        <!-- Desktop View Toggle -->
                        <li class="nav-item me-2" id="desktop-view-toggle-container" style="display: none;">
                            <button type="button" id="toggle-desktop-view" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-laptop"></i> Десктоп
                            </button>
                        </li>

                        <!-- Switch to Old Design -->
                        <li class="nav-item me-2">
                            <a href="{{ route('home') }}" class="btn btn-outline-light btn-sm d-none">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Старый дизайн
                            </a>
                        </li>
                        
                        <!-- Theme Toggle -->
                        <li class="nav-item me-3">
                            <div class="theme-toggle text-white" id="themeToggle" style="cursor: pointer;">
                                <i class="bi bi-sun theme-icon" id="sunIcon"></i>
                                <i class="bi bi-moon-stars-fill theme-icon d-none" id="moonIcon"></i>
                            </div>
                        </li>
                        
                        <!-- Logout -->
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="mb-0">
                                @csrf
                                <button type="submit" id="logout-button" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-box-arrow-right me-1"></i>Выход
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div id="main-content" class="flex-grow-1 bg-light">
            <div id="content-wrapper" class="container-fluid py-3">
                <div id="header-section" class="mb-2">
                    @if (session('success'))
                        <!-- <div style="color: green; font-weight: bold;">{{ session('success') }}</div> -->
                    @endif
                </div>
                
                @yield('content')
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-dark text-white d-none d-md-block mt-auto">
            <div class="container-fluid py-3">
                <div class="row justify-content-center text-center">
                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-center gap-4 mb-2">
                            <a href="https://docs.google.com/spreadsheets/d/1XHFtDmqNkXltwpZ_j83XgQwddBx6JBiQfqtoc9n3ZiA/edit?usp=drivesdk" target="_blank" class="text-white text-decoration-none small"><i class="bi bi-calendar3 me-1"></i>График работы</a>
                            <a href="https://docs.google.com/spreadsheets/d/1u2V2q3rPj1ajIUZ8do3SNGOIOmBx8wkvCJBeMX2_6jQ/edit?usp=drivesdk" target="_blank" class="text-white text-decoration-none small"><i class="bi bi-cash-stack me-1"></i>График оплаты</a>
                            <a href="https://storage.lan-install.online/" target="_blank" class="text-white text-decoration-none small"><i class="bi bi-box-seam me-1"></i>Склад</a>
                        </div>
                        <div class="small text-muted">
                            &copy; {{ date('Y') }} lan-install.online. Все права защищены.
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        
        <!-- Mobile Footer Links -->
        <div id="mobile-footer-links" class="d-md-none py-3 bg-light text-center border-top">
            <div class="container">
                <div class="d-flex flex-column gap-2 mb-2">
                    <a href="https://docs.google.com/spreadsheets/d/1XHFtDmqNkXltwpZ_j83XgQwddBx6JBiQfqtoc9n3ZiA/edit?usp=drivesdk" target="_blank" class="text-dark text-decoration-none small"><i class="bi bi-calendar3 me-2"></i>График работы</a>
                    <a href="https://docs.google.com/spreadsheets/d/1u2V2q3rPj1ajIUZ8do3SNGOIOmBx8wkvCJBeMX2_6jQ/edit?usp=drivesdk" target="_blank" class="text-dark text-decoration-none small"><i class="bi bi-cash-stack me-2"></i>График оплаты</a>
                    <a href="https://storage.lan-install.online/" target="_blank" class="text-dark text-decoration-none small"><i class="bi bi-box-seam me-2"></i>Склад</a>
                </div>
                <div class="small text-muted">
                    &copy; {{ date('Y') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Bootstrap Datepicker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.ru.min.js"></script>
    <!-- WYSIWYG Editor -->
    <script src="{{ asset('js/editor.js') }}"></script>
    
    <!-- Yandex Maps (нужен здесь, так как используется в нескольких табах) -->
    <script src="https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex.maps_key') }}&lang=ru_RU" type="text/javascript"></script>
    <script src="{{ asset('js/session-keepalive.js') }}"></script>

    @stack('scripts')
</body>
</html>