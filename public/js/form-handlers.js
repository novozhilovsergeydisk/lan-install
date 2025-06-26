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

    const requestData = {
        _token: data._token,
        client: {
            fio: data.client_name,
            phone: data.client_phone
        },
        request: {
            request_type_id: data.request_type_id,
            status_id: data.status_id,
            comment: data.comment || '',
            execution_date: data.execution_date || null,
            execution_time: data.execution_time || null,
            brigade_id: data.brigade_id || null,
            operator_id: data.operator_id || null
        },
        addresses: []
    };

    const cityIds = Array.isArray(data['city_id[]']) ? data['city_id[]'] : [data['city_id[]']];
    const streets = Array.isArray(data['street[]']) ? data['street[]'] : [data['street[]']];
    const houses = Array.isArray(data['house[]']) ? data['house[]'] : [data['house[]']];
    const addressComments = Array.isArray(data['address_comment[]']) ? data['address_comment[]'] : [data['address_comment[]']];

    for (let i = 0; i < cityIds.length; i++) {
        if (cityIds[i] && streets[i] && houses[i]) {
            requestData.addresses.push({
                city_id: cityIds[i],
                street: streets[i],
                house: houses[i],
                comment: addressComments[i] || ''
            });
        }
    }

    if (requestData.addresses.length === 0) {
        showAlert('Необходимо указать хотя бы один адрес', 'danger');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Создать заявку';
        return;
    }

    try {
        const result = await postData('/api/requests', requestData);
        if (result.success) {
            showAlert('Заявка успешно создана!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('newRequestModal'));
            modal.hide();
            window.location.reload();
        } else {
            throw new Error(result.message || 'Ошибка при создании заявки');
        }
    } catch (error) {
        showAlert(error.message || 'Произошла ошибка при создании заявки', 'danger');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Создать заявку';
    }
}

export { submitRequestForm };
