<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"  rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"> 

    <style>
        :root {
            --bg-color: #f8f9fa;
            --text-color: #212529;
            --card-bg: #fff;
            --card-border: rgba(0,0,0,.125);
            --card-hover: #e9ecef;
        }

        [data-bs-theme="dark"] {
            --bg-color: #212529;
            --text-color: #f8f9fa;
            --card-bg: #2c3034;
            --card-border: #495057;
            --card-hover: #495057;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container-center {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .form-container {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            border-radius: 0.5rem;
            background-color: var(--card-bg);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: 1px solid var(--card-border);
        }

        .form-control,
        .form-select {
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

        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1050;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }

        .theme-icon {
            color: var(--text-color);
            transition: all 0.3s ease;
        }
    </style>
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

                <button type="submit" class="btn btn-primary w-100 mb-3">
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
