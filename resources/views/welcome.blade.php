<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bootstrap 5 Datepicker</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <style>
        :root {
            --bg-color: #f8f9fa;
            --text-color: #212529;
            --sidebar-bg: #fff;
            --card-bg: #fff;
            --card-border: rgba(0, 0, 0, .125);
            --calendar-border: #dee2e6;
            /* Light theme border color */
        }

        [data-bs-theme="dark"] {
            --bg-color: #212529;
            --text-color: #f8f9fa;
            --sidebar-bg: #2c3034;
            --card-bg: #2c3034;
            --card-border: #495057;
            --calendar-border: #ffffff;
            /* White border for dark theme */
        }

        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .sidebar {
            position: sticky;
            top: 0;
            min-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            /* Prevent horizontal scroll */
            background-color: var(--bg-color);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            z-index: 1000;
            flex: 0 0 auto;
            width: 100%;
            max-width: 400px;
            /* Max width as requested */
            padding: 1rem 7px;
            /* Added top/bottom padding */
            border-right: 1px solid var(--card-border);
        }

        .datepicker {
            border: 1px solid var(--calendar-border);
            border-radius: 0.5rem;
            width: 100%;
            max-width: 100%;
            /*height: 320px;*/
            /* Increased height */
            margin: 0;
            padding: 1rem 0.75rem;
            /* More vertical padding */
            background-color: var(--card-bg);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .datepicker table {
            width: 100%;
            height: 100%;
            margin: 0;
        }

        .datepicker table {
            width: 100%;
        }

        .datepicker .day {
            width: 2.5rem;
            height: 2.5rem;
            line-height: 2.5rem;
            margin: 0.1rem;
            border-radius: 50%;
        }

        .datepicker .day:hover {
            background-color: #e9ecef;
        }

        .datepicker .day.active {
            background-color: #0d6efd;
            color: white;
        }

        .card {
            background-color: var(--card-bg);
            border-color: var(--card-border);
        }

        .form-control,
        .form-select,
        .input-group-text {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: var(--card-border);
        }

        .form-control:focus,
        .form-select:focus {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            padding-bottom: 0;
            /* Remove bottom padding to prevent overlap with sticky footer */
        }

        .nav-tabs .nav-link {
            color: var(--text-color);
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background-color: var(--card-bg);
            border-color: var(--card-border) var(--card-border) var(--card-bg);
        }

        .theme-toggle {
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            padding: 4px;
            /*0.5rem;*/
            border-radius: 50%;
            margin: 18px;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background-color: var(--card-bg);
            transform: scale(1.1);
        }

        .theme-icon {
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        .tab-content {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-top: none;
            padding: 1.5rem;
            border-radius: 0 0 0.25rem 0.25rem;
        }
    </style>
</head>

<body>
    <div class="container-fluid g-0">
        <div class="row g-0" style="min-height: 100vh;">
            <!-- Left Sidebar with Calendar -->
            <div class="col-auto sidebar p-3">
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
                @if (!empty($requestStatuses))
                    <h4 class="mt-5 mb-3">Статусы заявок</h4>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($requestStatuses as $status)
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
            <div class="main-content">
                <div class="container-fluid position-relative" style="min-height: 100vh;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="mb-0">Система управления заявками</h1>
                        @if (session('success'))
                            <div style="color: green; font-weight: bold;">
                                {{ session('success') }}
                            </div>
                        @endif

                        <div class="d-flex align-items-center gap-2">

                            <!-- Logout Button -->
                            <form action="{{ route('logout') }}" method="POST" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm mr-5 px-3"
                                    style="margin-right: 2rem;"><i class="bi bi-box-arrow-right me-1"></i>
                                    Выход</button>
                            </form>

                        </div>

                        <div class="theme-toggle position-fixed" id="themeToggle"
                            style="top: 1rem; right: 1rem; z-index: 1050;">
                            <i class="bi bi-sun theme-icon" id="sunIcon"></i>
                            <i class="bi bi-moon-stars-fill theme-icon d-none" id="moonIcon"></i>
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
                            <p>Здесь будет отображаться список заявок. Вы можете создавать, просматривать и управлять
                                заявками на выполнение работ. Используйте фильтры для поиска нужных заявок по статусу,
                                дате или другим параметрам.</p>
                            <p>Для создания новой заявки нажмите кнопку "Добавить заявку" в правом верхнем углу таблицы.
                                Вы сможете указать все необходимые детали, включая описание работ, адрес и приоритет.
                            </p>

@if($requests)
    <div class="table-responsive mt-4">
        <table class="table table-hover align-middle mb-0">
<thead class="bg-dark text-white">      
                <tr>
                    <th>ID</th>
                    <th>Номер</th>
                    <th>Клиент</th>
                    <th>Статус</th>
                    <th>Бригада</th>
                    <th>Дата выполнения</th>
                    <th>Время</th>
                    <th>Создана</th>
                    <th>Комментарий</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $request)
                    <tr class="align-middle">
                        <td>{{ $request->id }}</td>
                        <td><strong>{{ $request->number ?? '—' }}</strong></td>

                        <!-- Клиент -->
                        <td>
                            {{ $request->client_fio ?? 'Неизвестный клиент' }}<br>
                            <small class="text-muted">{{ $request->client_phone ?? 'Нет телефона' }}</small>
                        </td>

                        <!-- Статус -->
                        <td>
                            <span 
                                class="badge rounded-pill" 
                                style="background-color: {{ $request->status_color ?? '#ccc' }}; color: #fff;">
                                {{ $request->status_name ?? 'Не указано' }}
                            </span>
                        </td>

                        <!-- Бригада -->
                        <td>
                            {{ $request->brigade_name ?? 'Не назначена' }}<br>
                            <small class="text-muted">Руководитель: {{ $request->brigade_lead ?? 'Нет данных' }}</small>
                        </td>

                        <!-- Дата выполнения -->
                        <td>{{ \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') }}</td>
                        <td>{{ $request->execution_time ?? '—' }}</td>

                        <!-- Дата создания -->
                        <td>{{ \Carbon\Carbon::parse($request->request_date)->format('d.m.Y') }}</td>

                        <!-- Комментарий -->
                        <td>{{ \Illuminate\Support\Str::limit($request->comment, 30) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="mt-4 text-muted">Нет заявок для отображения.</p>
@endif

                            @if (session('user_data'))
                                <ul>
                                    <li><strong>ID:</strong> {{ session('user_data')['id'] }}</li>
                                    <li><strong>Имя:</strong> {{ session('user_data')['name'] }}</li>
                                    <li><strong>Email:</strong> {{ session('user_data')['email'] }}</li>
                                    <li><strong>Роль:</strong> {{ session('user_data')['role'] }}</li>
                                    <li><strong>Дата регистрации:</strong> {{ session('user_data')['created_at'] }}
                                    </li>
                                </ul>
                            @endif
                            @if (session('migrations'))
                                <h3>Список миграций:</h3>
                                <ul>
                                    @foreach (session('migrations') as $migration)
                                        <li>
                                            <strong>{{ $migration->migration }}</strong> (ID: {{ $migration->id }},
                                            Batch: {{ $migration->batch }})
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (session('clients'))
                                <h3>Список клиентов:</h3>
                                <ul class="list-group">
                                    @foreach (session('clients') as $client)
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            {{ $client->fio ?? 'Без имени' }}
                                            <span
                                                class="badge bg-primary">{{ $client->phone ?? 'Нет телефона' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p>Нет данных о клиентах.</p>
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
                                            <span
                                                class="badge bg-primary">{{ $client->phone ?? 'Нет телефона' }}</span>
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
                    <div class="card">
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
    <!-- Bootstrap Datepicker JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.ru.min.js">
    </script>

    <script>
        $(document).ready(function() {
            // Initialize datepicker
            $('#datepicker').datepicker({
                format: 'dd.mm.yyyy',
                language: 'ru',
                autoclose: true,
                todayHighlight: true,
                container: '#datepicker'
            });

            // Sync datepicker with input field
            $('#datepicker').on('changeDate', function(e) {
                $('#dateInput').val(e.format('dd.mm.yyyy'));
                $('#selectedDate').text(e.format('dd.mm.yyyy'));
            });

            // Initialize input field datepicker
            $('#dateInput').datepicker({
                format: 'dd.mm.yyyy',
                language: 'ru',
                autoclose: true,
                todayHighlight: true,
                container: '#datepicker'
            });

            // Set today's date on load
            let today = new Date();
            let formattedDate = today.toLocaleDateString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            $('#datepicker').datepicker('update', today);
            $('#dateInput').val(formattedDate);
            $('#selectedDate').text(formattedDate);

            // Theme toggle functionality
            const themeToggle = document.getElementById('themeToggle');
            const sunIcon = document.getElementById('sunIcon');
            const moonIcon = document.getElementById('moonIcon');
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
            const currentTheme = localStorage.getItem('theme');

            // Check for saved theme preference or use system preference
            if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
                sunIcon.classList.remove('d-none'); // Show sun in dark mode
                moonIcon.classList.add('d-none');
            } else {
                document.documentElement.setAttribute('data-bs-theme', 'light');
                sunIcon.classList.add('d-none');
                moonIcon.classList.remove('d-none'); // Show moon in light mode
            }

            // Toggle theme on icon click
            themeToggle.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                if (currentTheme === 'dark') {
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                    localStorage.setItem('theme', 'light');
                    sunIcon.classList.add('d-none');
                    moonIcon.classList.remove('d-none'); // Show moon in light mode
                } else {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    sunIcon.classList.remove('d-none'); // Show sun in dark mode
                    moonIcon.classList.add('d-none');
                }
            });
        });
    </script>
</body>

</html>
