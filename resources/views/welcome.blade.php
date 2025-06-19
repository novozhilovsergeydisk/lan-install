<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система управления заявками</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap Datepicker CSS -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
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
                                <button type="submit" class="btn btn-outline-danger btn-sm px-3">
                                    <i class="bi bi-box-arrow-right me-1"></i>Выход
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <div id="request-filters" class="d-flex align-items-center mb-3" style="height: 2rem;">
                        <label class="me-2 mb-0">Фильтр заявок по:</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="filter-types">
                            <label class="form-check-label" for="filter-types">типам</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="filter-statuses">
                            <label class="form-check-label" for="filter-statuses">статусам</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="filter-teams">
                            <label class="form-check-label" for="filter-teams">бригадам</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="filter-time">
                            <label class="form-check-label" for="filter-time">времени</label>
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

                    <div id="tabs-content" class="tab-content" id="mainTabsContent">
                        <div id="requests-tab-content" class="tab-pane fade show active" id="requests" role="tabpanel">
                            <h4>Заявки</h4>
                            <p>Здесь будет отображаться список заявок. Вы можете создавать, просматривать и управлять
                                заявками на выполнение работ. Используйте фильтры для поиска нужных заявок по статусу,
                                дате или другим параметрам.</p>
                            <p>Для создания новой заявки нажмите кнопку "Добавить заявку" в правом верхнем углу таблицы.
                                Вы сможете указать все необходимые детали, включая описание работ, адрес и приоритет.
                            </p>

                            @if ($requests)
                                <div id="requests-table-container" class="table-responsive mt-4" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                                    <table class="table table-hover align-middle mb-0" style="min-width: 992px;">
                                        <thead class="bg-dark text-white">
                                            <tr>
                                                <th>ID</th>
                                                <th>Номер</th>
                                                <th>Клиент</th>
                                                <th>Бригада</th>
                                                <th>Дата выполнения</th>
                                                <th>Время</th>
                                                <th>Создана</th>
                                                <th>Комментарий</th>
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
                                                .table-hover tbody tr:hover {
                                                    --bs-table-color: #000000 !important;
                                                    color: #000000 !important;
                                                }
                                                
                                                /* Стили для статусов */
                                                tr[style*="background-color: #BBDEFB"] { --bs-table-bg: #BBDEFB !important; } /* новая */
                                                tr[style*="background-color: #FFECB3"] { --bs-table-bg: #FFECB3 !important; } /* в работе */
                                                tr[style*="background-color: #FFCC80"] { --bs-table-bg: #FFCC80 !important; } /* ожидает клиента */
                                                tr[style*="background-color: #C8E6C9"] { --bs-table-bg: #C8E6C9 !important; } /* выполнена */
                                                tr[style*="background-color: #FFCDD2"] { --bs-table-bg: #FFCDD2 !important; } /* отменена */
                                                tr[style*="background-color: #E0E0E0"] { --bs-table-bg: #E0E0E0 !important; } /* на уточнении */
                                                tr[style*="background-color: #E1BEE7"] { --bs-table-bg: #E1BEE7 !important; } /* приостановлена */
                                            </style>
                                            @php
                                                $statusColors = [
                                                    'новая' => 'background-color: #BBDEFB;', /* голубой */
                                                    'в работе' => 'background-color: #FFECB3;', /* желтый */
                                                    'ожидает клиента' => 'background-color: #FFCC80;', /* оранжевый */
                                                    'выполнена' => 'background-color: #C8E6C9;', /* зеленый */
                                                    'отменена' => 'background-color: #FFCDD2;', /* красный */
                                                    'на уточнении' => 'background-color: #E0E0E0;', /* серый */
                                                    'приостановлена' => 'background-color: #E1BEE7;', /* фиолетовый */
                                                ];
                                            @endphp

                                            @foreach ($requests as $request)
                                                @php
                                                    // Получаем статус и приводим к нижнему регистру
                                                    $status = strtolower($request->status_name ?? '');
                                                    $rowStyle = $statusColors[$status] ?? '';
                                                @endphp
                                                <tr class="align-middle" style="{{ $rowStyle }}">
                                                    <td>{{ $request->id }}</td>
                                                    <td><strong>{{ $request->number ?? '—' }}</strong></td>

                                                    <!-- Клиент -->
                                                    <td>
                                                        {{ $request->client_fio ?? 'Неизвестный клиент' }}<br>
                                                        <small class="@if(isset($request->status_name) && $request->status_name === 'выполнена') text-success fw-bold @else text-black @endif">
                                                            {{ $request->client_phone ?? 'Нет телефона' }}
                                                        </small>
                                                    </td>



                                                    <!-- Бригада -->
                                                    <td>
                                                        {{ $request->brigade_name ?? 'Не назначена' }}<br>
                                                        <small class="@if(isset($request->status_name) && $request->status_name === 'выполнена') text-success fw-bold @else text-black @endif">
                                                            Руководитель: {{ $request->brigade_lead ?? 'Нет данных' }}
                                                        </small>
                                                    </td>

                                                    <!-- Дата выполнения -->
                                                    <td>{{ \Carbon\Carbon::parse($request->execution_date)->format('d.m.Y') }}
                                                    </td>
                                                    <td>{{ $request->execution_time ?? '—' }}</td>

                                                    <!-- Дата создания -->
                                                    <td>{{ \Carbon\Carbon::parse($request->request_date)->format('d.m.Y') }}
                                                    </td>

                                                    <!-- Комментарий -->
                                                    <td>{{ \Illuminate\Support\Str::limit($request->comment, 30) }}
                                                    </td>
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