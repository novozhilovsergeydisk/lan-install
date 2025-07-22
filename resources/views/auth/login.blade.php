<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Общие стили для аутентификации -->
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body class="text-center">
    <div class="container container-center">
        <div class="form-container mx-auto">
            <h2 class="mb-4">Вход в систему</h2>

            {{-- Отображение общих ошибок --}}
            @if(session('error'))
                <div class="alert alert-danger mb-3">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Отображение успешных сообщений --}}
            @if(session('status'))
                <div class="alert alert-success mb-3">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3 text-start">
                    <label for="login" class="form-label">Email или Логин:</label>
                    <input type="text" name="login" id="login"
                           class="form-control @error('login') is-invalid @enderror"
                           placeholder="Введите логин или email"
                           value="{{ old('login') }}"
                           autocomplete="username"
                           required autofocus>
                    @error('login')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3 text-start">
                    <label for="password" class="form-label">Пароль:</label>
                    <input type="password" name="password" id="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Введите пароль"
                           autocomplete="current-password"
                           required>
                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Войти
                </button>

                <!-- <p class="mb-0">
                    Нет аккаунта? <a href="{{ route('register') }}">Зарегистрируйтесь</a>
                </p> -->
            </form>
        </div>
    </div>

    <!-- Theme Toggle Button -->
    <div class="theme-toggle" id="themeToggle">
        <i class="bi bi-sun theme-icon" id="sunIcon"></i>
        <i class="bi bi-moon-stars-fill theme-icon d-none" id="moonIcon"></i>
    </div>

    <!-- jQuery + Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 

    <script>
        $(document).ready(function () {
            const themeToggle = document.getElementById('themeToggle');
            const sunIcon = document.getElementById('sunIcon');
            const moonIcon = document.getElementById('moonIcon');
            const root = document.documentElement;

            const setTheme = (theme) => {
                root.setAttribute('data-bs-theme', theme);
                localStorage.setItem('theme', theme);

                if (theme === 'dark') {
                    sunIcon.classList.remove('d-none');
                    moonIcon.classList.add('d-none');
                } else {
                    sunIcon.classList.add('d-none');
                    moonIcon.classList.remove('d-none');
                }
            };

            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const savedTheme = localStorage.getItem('theme');

            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                setTheme('dark');
            } else {
                setTheme('light');
            }

            themeToggle.addEventListener('click', function () {
                const currentTheme = root.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                setTheme(newTheme);
            });
        });
    </script>
</body>
</html>
