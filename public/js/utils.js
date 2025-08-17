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
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '1060';
    alertDiv.setAttribute('role', 'alert');
    alertDiv.style.minWidth = '300px';
    alertDiv.style.textAlign = 'center';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);

    // Auto-close after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode === document.body) {
            const bsAlert = bootstrap.Alert.getInstance(alertDiv) || new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }
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

        const responseData = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                // Проверяем сообщение об ошибке от сервера
                if (responseData.message && responseData.message.includes('Сотрудник не найден')) {
                    showAlert('Необходимо создать сотрудника для данного пользователя', 'danger');
                    throw new Error('EMPLOYEE_NOT_FOUND');
                }
                // Проверяем ошибки валидации для operator_id
                if (responseData.errors && responseData.errors.operator_id) {
                    const errorMessage = responseData.errors.operator_id[0];
                    if (errorMessage.includes('employee') || errorMessage.includes('not found')) {
                        showAlert('Необходимо создать сотрудника для данного пользователя', 'danger');
                        throw new Error('EMPLOYEE_NOT_FOUND');
                    }
                }
                // Для других ошибок валидации показываем первое сообщение об ошибке
                const firstError = responseData.message || Object.values(responseData.errors || {}).flat()[0];
                showAlert(firstError || 'Ошибка валидации данных', 'danger');
                throw new Error(firstError || 'Ошибка валидации данных');
            }
            // Обработка других HTTP ошибок
            throw new Error(responseData.message || `Ошибка сервера: ${response.status}`);
        }

        // console.log('responseData', responseData);

        return responseData;
    } catch (error) {
        console.error('Ошибка отправки:', error);
        if (error.message !== 'EMPLOYEE_NOT_FOUND') {
            showAlert(error.message || ('Произошла ошибка при отправке данных: ' + (error && error.message ? error.message : '')), 'danger');
        }
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
