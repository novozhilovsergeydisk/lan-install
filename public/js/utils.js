// public/js/utils.js

/**
 * @typedef {'success'|'danger'|'warning'} AlertType
 */

/**
 * Показывает пользовательское уведомление
 * @param {string} message Текст сообщения
 * @param {AlertType} [type='success'] Тип уведомления
 */
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '1060';
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);

    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}

/**
 * Получает данные из API
 * @param {string} url
 * @returns {Promise<any>}
 */
async function fetchData(url) {
    try {
        const response = await fetch(url, {
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error(`Ошибка сети: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Ошибка при запросе:', error);
        showAlert(`Ошибка загрузки данных: ${error.message}`, 'danger');
        throw error;
    }
}

/**
 * Отправляет данные в API
 * @param {string} url
 * @param {Object} body
 * @returns {Promise<any>}
 */
async function postData(url, body) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        });

        if (!response.ok) throw new Error(`Ошибка сети: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Ошибка отправки:', error);
        showAlert(`Ошибка отправки данных: ${error.message}`, 'danger');
        throw error;
    }
}

/**
 * Универсальная функция для выполнения HTTP-запросов
 * @param {string} url
 * @param {Object} options
 * @returns {Promise<any>}
 */
async function sendRequest(url, options) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        };

        const defaultOptions = {
            headers,
            credentials: 'same-origin',
            ...options
        };

        const response = await fetch(url, defaultOptions);
        if (!response.ok) throw new Error(`Ошибка сети: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Ошибка запроса:', error);
        showAlert(`Ошибка запроса: ${error.message}`, 'danger');
        throw error;
    }
}

// Экспорт для модулей
export { showAlert, fetchData, postData, sendRequest };

// Экспорт глобально
if (typeof window !== 'undefined') {
    window.utils = {
        showAlert,
        fetchData,
        postData,
        sendRequest
    };
}
