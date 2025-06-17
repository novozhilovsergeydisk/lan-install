<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>

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
        }

        [data-bs-theme="dark"] {
            --bg-color: #212529;
            --text-color: #f8f9fa;
            --card-bg: #2c3034;
            --card-border: #495057;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
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

        .form-control, .form-select {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: var(--card-border);
        }

        .form-control:focus, .form-select:focus {
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
    <div class="container container-center">
        <div class="form-container mx-auto">
            <h2 class="mb-4">Вход в систему</h2>

            @if(session('error'))
                <div class="alert alert-danger mb-3" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3 text-start">
                    <label for="login" class="form-label">Email или Логин:</label>
                    <input type="text" name="login" id="login"
                           class="form-control"
                           placeholder="Введите логин или email"
                           required autofocus>
                </div>

                <div class="mb-3 text-start">
                    <label for="password" class="form-label">Пароль:</label>
                    <input type="password" name="password" id="password"
                           class="form-control"
                           placeholder="Введите пароль"
                           required>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Войти
                </button>

                <p class="mb-0">
                    Нет аккаунта? <a href="{{ route('register') }}">Зарегистрируйтесь</a>
                </p>
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
