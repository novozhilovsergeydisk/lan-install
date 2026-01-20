// Функции для работы с параметрами типов заявок

/**
 * Загрузка списка параметров типов заявок
 */
export async function loadWorkParameterTypes(requestTypeId = null) {
    const tbody = document.getElementById('workParameterTypesList');

    if (!requestTypeId) {
        tbody.innerHTML = '';
        return;
    }

    try {
        const response = await fetch(`/api/work-parameter-types/by-request-type/${requestTypeId}`);
        if (!response.ok) {
            throw new Error('Ошибка загрузки параметров типов заявок');
        }

        const data = await response.json();
        renderWorkParameterTypesTable(data);
    } catch (error) {
        console.error('Ошибка при загрузке параметров типов заявок:', error);
        window.utils.showAlert('Ошибка загрузки параметров типов заявок', 'danger');
    }
}

/**
 * Отрисовка таблицы параметров типов заявок
 */
function renderWorkParameterTypesTable(workParameterTypes) {
    const tbody = document.getElementById('workParameterTypesList');

    if (!workParameterTypes || workParameterTypes.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center py-4">
                    <p class="mb-0">Параметры типов заявок не найдены</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = workParameterTypes.map(param => `
        <tr>
            <td>${param.name}</td>
            <td>${param.request_type_name}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1 edit-work-parameter-type-btn" data-id="${param.id}" data-name="${param.name}" data-request-type-id="${param.request_type_id}">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger delete-work-parameter-type-btn" data-id="${param.id}" data-name="${param.name}" data-request-type-id="${param.request_type_id}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Загрузка списка типов заявок для select
 */
async function loadRequestTypesForSelect() {
    try {
        const response = await fetch('/api/request-types/');
        if (!response.ok) {
            throw new Error('Ошибка загрузки типов заявок');
        }

        const data = await response.json();
        const select = document.getElementById('workParameterTypeRequestType');
        const currentValue = select.value;
        select.innerHTML = '<option value="">Выберите тип заявки</option>' +
            data.map(type => `<option value="${type.id}">${type.name}</option>`).join('');
        
        if (currentValue) {
            select.value = currentValue;
        }
    } catch (error) {
        console.error('Ошибка при загрузке типов заявок для select:', error);
        window.utils.showAlert('Ошибка загрузки типов заявок', 'danger');
    }
}

/**
 * Создание нового параметра типа заявки
 */
export async function createWorkParameterType(name, requestTypeId) {
    try {
        const response = await fetch('/api/work-parameter-types/', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name, request_type_id: requestTypeId })
        });

        const data = await response.json();

        if (data.success) {
            window.utils.showAlert('Параметр типа заявки успешно создан', 'success');
            loadWorkParameterTypes(requestTypeId);
            return true;
        } else {
            window.utils.showAlert(data.message || 'Ошибка создания параметра типа заявки', 'danger');
            return false;
        }
    } catch (error) {
        console.error('Ошибка при создании параметра типа заявки:', error);
        window.utils.showAlert('Ошибка создания параметра типа заявки', 'danger');
        return false;
    }
}

/**
 * Обновление параметра типа заявки
 */
export async function updateWorkParameterType(id, name, requestTypeId) {
    try {
        const response = await fetch(`/api/work-parameter-types/${id}/`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name, request_type_id: requestTypeId })
        });

        const data = await response.json();

        if (data.success) {
            window.utils.showAlert('Параметр типа заявки успешно обновлен', 'success');
            loadWorkParameterTypes(requestTypeId);
            return true;
        } else {
            window.utils.showAlert(data.message || 'Ошибка обновления параметра типа заявки', 'danger');
            return false;
        }
    } catch (error) {
        console.error('Ошибка при обновлении параметра типа заявки:', error);
        window.utils.showAlert('Ошибка обновления параметра типа заявки', 'danger');
        return false;
    }
}

/**
 * Удаление параметра типа заявки
 */
export async function deleteWorkParameterType(id, name, requestTypeId) {
    if (!confirm(`Вы уверены, что хотите удалить параметр "${name}"?`)) {
        return;
    }

    try {
        const response = await fetch(`/api/work-parameter-types/${id}/`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            window.utils.showAlert('Параметр типа заявки успешно удален', 'success');
            loadWorkParameterTypes(requestTypeId);
        } else {
            window.utils.showAlert(data.message || 'Ошибка удаления параметра типа заявки', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при удалении параметра типа заявки:', error);
        window.utils.showAlert('Ошибка удаления параметра типа заявки', 'danger');
    }
}

/**
 * Инициализация обработчиков для параметров типов заявок
 */
export function initWorkParameterTypesHandlers() {
    // Обработчик кнопки сохранения
    document.getElementById('saveWorkParameterTypeBtn').addEventListener('click', async () => {
        const id = document.getElementById('workParameterTypeId').value;
        const name = document.getElementById('workParameterTypeName').value.trim();
        const requestTypeId = document.getElementById('workParameterTypeRequestType').value;

        if (!name) {
            window.utils.showAlert('Введите название параметра', 'warning');
            return;
        }

        if (!requestTypeId) {
            window.utils.showAlert('Выберите тип заявки', 'warning');
            return;
        }

        let success = false;
        if (id) {
            success = await updateWorkParameterType(id, name, requestTypeId);
        } else {
            success = await createWorkParameterType(name, requestTypeId);
        }

        if (success) {
            if (id) {
                // Если редактирование - закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('addWorkParameterTypeModal'));
                modal.hide();

                // Сброс формы
                document.getElementById('workParameterTypeForm').reset();
                document.getElementById('workParameterTypeId').value = '';
            } else {
                // Если создание - очищаем только поле названия для быстрого добавления следующего параметра
                document.getElementById('workParameterTypeName').value = '';
                document.getElementById('workParameterTypeName').focus();
            }
        }
    });

    // Обработчик открытия модального окна для создания
    const modalBtn = document.querySelector('[data-bs-target="#addWorkParameterTypeModal"]');
    if (modalBtn) {
        modalBtn.addEventListener('click', async () => {
            document.getElementById('workParameterTypeForm').reset();
            document.getElementById('workParameterTypeId').value = '';
            document.querySelector('#addWorkParameterTypeModal .modal-title').textContent = 'Параметры типа заявки';
            document.getElementById('workParameterTypesList').innerHTML = '';
            await loadRequestTypesForSelect();
        });
    }

    // Обработчик изменения типа заявки
    const typeSelect = document.getElementById('workParameterTypeRequestType');
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            loadWorkParameterTypes(this.value);
        });
    }

    // Обработчик закрытия модального окна
    const modalEl = document.getElementById('addWorkParameterTypeModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => {
            document.getElementById('workParameterTypeForm').reset();
            document.getElementById('workParameterTypeId').value = '';
            document.getElementById('workParameterTypesList').innerHTML = '';
        });
    }

    // Обработчики для кнопок редактирования и удаления параметров типов заявок
    document.addEventListener('click', function(e) {
        if (e.target.matches('.edit-work-parameter-type-btn') || e.target.closest('.edit-work-parameter-type-btn')) {
            e.preventDefault();
            const button = e.target.closest('.edit-work-parameter-type-btn');
            const id = button.dataset.id;
            const name = button.dataset.name;
            const requestTypeId = button.dataset.requestTypeId;
            handleEditWorkParameterType(id, name, requestTypeId);
        }

        if (e.target.matches('.delete-work-parameter-type-btn') || e.target.closest('.delete-work-parameter-type-btn')) {
            e.preventDefault();
            const button = e.target.closest('.delete-work-parameter-type-btn');
            const id = button.dataset.id;
            const name = button.dataset.name;
            const requestTypeId = button.dataset.requestTypeId;
            deleteWorkParameterType(id, name, requestTypeId);
        }
    });
}

// Функции для обработки редактирования параметров типов заявок
async function handleEditWorkParameterType(id, name, requestTypeId) {
    document.getElementById('workParameterTypeId').value = id;
    document.getElementById('workParameterTypeName').value = name;
    document.querySelector('#addWorkParameterTypeModal .modal-title').textContent = 'Редактировать параметр типа заявки';

    await loadRequestTypesForSelect();
    document.getElementById('workParameterTypeRequestType').value = requestTypeId;
}
