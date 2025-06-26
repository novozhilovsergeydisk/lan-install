// requests.js

import { showAlert, fetchData, postData } from './utils.js';

/**
 * Закрывает заявку по ID
 * @param {number} requestId
 */
async function closeRequest(requestId) {
    if (!confirm(`Вы уверены, что хотите закрыть заявку #${requestId}?`)) return;

    try {
        const result = await postData(`/requests/${requestId}/close`, {});
        if (result.success) location.reload();
        else throw new Error(result.message || 'Неизвестная ошибка');
    } catch (error) {
        showAlert(`Ошибка при закрытии заявки: ${error.message}`, 'danger');
    }
}

export { closeRequest };
