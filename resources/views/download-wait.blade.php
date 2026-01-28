<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подготовка архива...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: #f8f9fa;
        }
        .card {
            max-width: 500px;
            width: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="card text-center p-5">
        <div id="loading-spinner" class="mb-4">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        
        <div id="success-icon" class="mb-4 d-none">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-check-circle-fill text-success" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
            </svg>
        </div>

        <h4 class="card-title mb-3" id="status-title">Подготовка архива</h4>
        
        <p class="card-text text-muted" id="status-text">
            Мы собираем фотографии в один файл.<br>
            Пожалуйста, не закрывайте эту страницу.
        </p>

        <div id="download-area" class="d-none mt-4">
            <div class="alert alert-success mb-3">
                <strong>Архив готов!</strong> Скачивание началось автоматически.
            </div>
            
            <p class="text-muted">
                Если загрузка не пошла — 
                <a href="" id="download-link" class="fw-bold text-decoration-underline">нажмите здесь</a> 
                для скачивания вручную.
            </p>

            <div class="mt-4 pt-3 border-top">
                <a href="/" class="btn btn-outline-primary btn-sm">
                    Перейти на главную
                </a>
            </div>
        </div>
        
        <p class="small text-muted mt-4" id="footer-hint">
            Для больших заявок это может занять до минуты.
        </p>
    </div>

    <script>
        const currentUrl = window.location.href.split('?')[0]; // Чистый URL без параметров
        
        function checkStatus() {
            fetch(currentUrl + '?check_status=1')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'ready') {
                        // Архив готов!
                        showSuccess();
                        // Запускаем скачивание
                        window.location.href = currentUrl;
                    } else {
                        // Продолжаем ждать
                        setTimeout(checkStatus, 2000);
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    // Пробуем снова через 5 сек даже при ошибке
                    setTimeout(checkStatus, 5000);
                });
        }

        function showSuccess() {
            document.getElementById('loading-spinner').classList.add('d-none');
            document.getElementById('success-icon').classList.remove('d-none');
            
            document.getElementById('status-title').textContent = 'Архив готов!';
            document.getElementById('status-text').textContent = 'Файл создан и готов к загрузке.';
            
            document.getElementById('download-area').classList.remove('d-none');
            document.getElementById('download-link').href = currentUrl;
            
            document.getElementById('footer-hint').classList.add('d-none');
        }

        // Запускаем проверку при загрузке
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(checkStatus, 1000);
        });
    </script>
</body>
</html>