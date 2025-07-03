<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Общие стили для аутентификации -->
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body class="text-center">
    <!-- Кнопка переключения темы -->
    <div class="theme-toggle" id="themeToggle">
        <i class="bi bi-sun theme-icon" id="sunIcon"></i>
        <i class="bi bi-moon-stars-fill theme-icon d-none" id="moonIcon"></i>
    </div>

    <main class="container container-center">
        <div class="form-container mx-auto">
            <h2 class="mb-4">Регистрация</h2>

            @if ($errors->any())
                <div class="alert alert-danger mb-3" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="mb-3 text-start">
                    <label for="name" class="form-label">Имя:</label>
                    <input type="text" name="name" id="name"
                           class="form-control"
                           placeholder="Введите имя"
                           value="{{ old('name') }}" required autofocus>
                </div>

                <div class="mb-3 text-start">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" name="email" id="email"
                           class="form-control"
                           placeholder="Введите email"
                           value="{{ old('email') }}" required>
                </div>

                <div class="mb-3 text-start">
                    <label for="password" class="form-label">Пароль:</label>
                    <input type="password" name="password" id="password"
                           class="form-control"
                           placeholder="Введите пароль"
                           required>
                </div>

                <div class="mb-3 text-start">
                    <label for="password_confirmation" class="form-label">Подтвердите пароль:</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="form-control"
                           placeholder="Подтвердите пароль"
                           required>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3" disabled>
                    <i class="bi bi-person-plus me-1"></i> Зарегистрироваться
                </button>
            </form>

            <p class="mb-0">
                Уже есть аккаунт? <a href="{{ route('login') }}">Войти</a>
            </p>
        </div>
    </main>

    <!-- jQuery + Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 

    <script>
        $(document).ready(function () {
            const themeToggle = document.getElementById('themeToggle');
            const sunIcon = document.getElementById('sunIcon');
            const moonIcon = document.getElementById('moonIcon');
            const root = document.documentElement;

            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const savedTheme = localStorage.getItem('theme');

            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                root.setAttribute('data-bs-theme', 'dark');
                sunIcon.classList.remove('d-none');
                moonIcon.classList.add('d-none');
            } else {
                root.setAttribute('data-bs-theme', 'light');
                sunIcon.classList.add('d-none');
                moonIcon.classList.remove('d-none');
            }

            themeToggle.addEventListener('click', function () {
                const currentTheme = root.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                root.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                sunIcon.classList.toggle('d-none');
                moonIcon.classList.toggle('d-none');
            });
        });
    </script>
</body>
</html>
