/**
 * Скрипт для поддержания сессии активной и предотвращения ошибки 419 Page Expired.
 * Особенно актуально для мобильных устройств, где браузер может "замораживать" вкладки.
 */

(function() {
    // Интервал пинга сервера (в миллисекундах) - каждые 5 минут
    const PING_INTERVAL = 5 * 60 * 1000;
    let lastPing = Date.now();

    /**
     * Пинг сервера для продления сессии
     */
    async function pingServer() {
        try {
            // Используем легкий запрос к API, который требует аутентификации
            const response = await fetch('/api/test-log', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (response.status === 419) {
                console.warn('Сессия истекла (419). Обновляем страницу...');
                window.location.reload();
            } else if (response.status === 401) {
                console.warn('Пользователь не авторизован (401).');
                // Можно перенаправить на логин, но обычно Laravel сам это делает
            } else {
                lastPing = Date.now();
                // console.log('Session keep-alive ping successful');
            }
        } catch (error) {
            console.error('Ошибка при выполнении keep-alive пинга:', error);
        }
    }

    // Перехватываем все fetch запросы для обработки 419 ошибки
    const originalFetch = window.fetch;
    window.fetch = async function(...args) {
        try {
            const response = await originalFetch.apply(this, args);
            
            if (response.status === 419) {
                console.warn('Сессия истекла (419) при вызове fetch. Обновляем страницу...');
                if (typeof showAlert === 'function') {
                    showAlert('Сессия истекла. Страница будет обновлена...', 'warning');
                } else {
                    alert('Сессия истекла. Страница будет обновлена...');
                }
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
            return response;
        } catch (error) {
            throw error;
        }
    };

    // Перехватываем jQuery AJAX запросы
    if (window.jQuery) {
        window.jQuery(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            if (jqXHR.status === 419) {
                console.warn('Сессия истекла (419) в jQuery AJAX. Обновляем страницу...');
                window.location.reload();
            }
        });
    }

    // Запускаем интервальный пинг
    setInterval(pingServer, PING_INTERVAL);

    // При возвращении пользователя на вкладку проверяем, не пора ли сделать пинг
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            const now = Date.now();
            // Если с последнего пинга прошло более 1 минуты, делаем новый пинг
            if (now - lastPing > 60 * 1000) {
                // console.log('Page visible, performing keep-alive ping...');
                pingServer();
            }
        }
    });

    // Также можно обновлять токен CSRF раз в час, если это необходимо
    // Но обычно продления сессии достаточно.
})();
