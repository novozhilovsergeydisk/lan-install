// form-handlers.js

import { showAlert, postData } from './utils.js';

/**
 * Обрабатывает отправку формы новой заявки
 */
async function submitRequestForm() {
    const form = document.getElementById('newRequestForm');
    const submitBtn = document.getElementById('submitRequest');

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Создание...`;

    const formData = new FormData(form);
    const data = {};

    formData.forEach((value, key) => {
        if (data[key] !== undefined) {
            if (!Array.isArray(data[key])) data[key] = [data[key]];
            data[key].push(value);
        } else {
            data[key] = value;
        }
    });

    const addressId = document.getElementById('addresses_id').value;

    if (!addressId) {
        showAlert('Пожалуйста, выберите адрес из списка', 'danger');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Создать заявку';
        return;
    }

    // Формируем данные для отправки
    const requestData = {
        _token: data._token,
        client_name: data.client_name || '',
        client_phone: data.client_phone || '',
        request_type_id: data.request_type_id,
        status_id: data.status_id,
        comment: data.comment || '',
        execution_date: data.execution_date || null,
        execution_time: data.execution_time || null,
        brigade_id: data.brigade_id || null,
        operator_id: data.operator_id || null,
        address_id: addressId
    };

    // Логируем данные перед отправкой
    console.log('Отправляемые данные:', requestData);

    // return;

    try {
        const result = await postData('/api/requests', requestData);
        if (result.success) {
            showAlert('Заявка успешно создана!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('newRequestModal'));
            modal.hide();
            
            // Reset the form
            form.reset();
            
            // Dispatch event to notify other components about the new request
            const event = new CustomEvent('requestCreated', { detail: result.data });
            document.dispatchEvent(event);
            
            // If there's a refreshRequestsTable function, call it
            if (typeof window.refreshRequestsTable === 'function') {
                window.refreshRequestsTable();
            } else {
                // Fallback to page reload if the function doesn't exist
                window.location.reload();
            }
        } else {
            throw new Error(result.message || 'Ошибка при создании заявки');
        }
    } catch (error) {
        // Не показываем сообщение об ошибке, если это ошибка "Сотрудник не найден"
        // так как мы уже показали alert в функции postData
        if (error.message !== 'EMPLOYEE_NOT_FOUND') {
            showAlert(error.message || 'Произошла ошибка при создании заявки', 'danger');
        }
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Создать заявку';
    }
}

// Делаем функцию доступной глобально
window.submitRequestForm = submitRequestForm;
