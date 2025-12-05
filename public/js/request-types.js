// Функции для работы с типами заявок

/**
 * Загрузка списка типов заявок
 */
export async function loadRequestTypes() {
    try {
        const response = await fetch('/api/request-types/');
        if (!response.ok) {
            throw new Error('Ошибка загрузки типов заявок');
        }

        const data = await response.json();
        renderRequestTypesTable(data);
    } catch (error) {
        console.error('Ошибка при загрузке типов заявок:', error);
        window.utils.showAlert('Ошибка загрузки типов заявок', 'danger');
    }
}

/**
 * Отрисовка таблицы типов заявок
 */
function renderRequestTypesTable(requestTypes) {
    const tbody = document.getElementById('requestTypesList');

    if (!requestTypes || requestTypes.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="text-center py-4">
                    <p class="mb-0">Типы заявок не найдены</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = requestTypes.map(type => `
        <tr>
            <td>${type.name}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="color-indicator me-2" style="background-color: ${type.color}; width: 20px; height: 20px; border-radius: 50%; border: 1px solid #ddd;"></div>
                    ${type.color}
                </div>
            </td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary me-1 edit-request-type-btn" data-id="${type.id}" data-name="${type.name}" data-color="${type.color}">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger delete-request-type-btn" data-id="${type.id}" data-name="${type.name}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Создание нового типа заявки
 */
export async function createRequestType(name, color) {
    try {
        const response = await fetch('/api/request-types/', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name, color })
        });

        const data = await response.json();

        if (data.success) {
            window.utils.showAlert('Тип заявки успешно создан', 'success');
            loadRequestTypes();
            return true;
        } else {
            window.utils.showAlert(data.message || 'Ошибка создания типа заявки', 'danger');
            return false;
        }
    } catch (error) {
        console.error('Ошибка при создании типа заявки:', error);
        window.utils.showAlert('Ошибка создания типа заявки', 'danger');
        return false;
    }
}

/**
 * Обновление типа заявки
 */
export async function updateRequestType(id, name, color) {
    try {
        const response = await fetch(`/api/request-types/${id}/`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name, color })
        });

        const data = await response.json();

        if (data.success) {
            window.utils.showAlert('Тип заявки успешно обновлен', 'success');
            loadRequestTypes();
            return true;
        } else {
            window.utils.showAlert(data.message || 'Ошибка обновления типа заявки', 'danger');
            return false;
        }
    } catch (error) {
        console.error('Ошибка при обновлении типа заявки:', error);
        window.utils.showAlert('Ошибка обновления типа заявки', 'danger');
        return false;
    }
}

/**
 * Удаление типа заявки
 */
export async function deleteRequestType(id, name) {
    if (!confirm(`Вы уверены, что хотите удалить тип "${name}"?`)) {
        return;
    }

    try {
        const response = await fetch(`/api/request-types/${id}/`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            window.utils.showAlert('Тип заявки успешно удален', 'success');
            loadRequestTypes();
        } else {
            window.utils.showAlert(data.message || 'Ошибка удаления типа заявки', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при удалении типа заявки:', error);
        window.utils.showAlert('Ошибка удаления типа заявки', 'danger');
    }
}

/**
 * Инициализация обработчиков для типов заявок
 */
export function initRequestTypesHandlers() {
    // Обработчик кнопки сохранения
    document.getElementById('saveRequestTypeBtn').addEventListener('click', async () => {
        const id = document.getElementById('requestTypeId').value;
        const name = document.getElementById('requestTypeName').value.trim();
        const color = document.getElementById('requestTypeColor').value;

        if (!name) {
            window.utils.showAlert('Введите название типа заявки', 'warning');
            return;
        }

        let success = false;
        if (id) {
            success = await updateRequestType(id, name, color);
        } else {
            success = await createRequestType(name, color);
        }

        if (success) {
            // Закрытие модального окна
            const modal = bootstrap.Modal.getInstance(document.getElementById('addRequestTypeModal'));
            modal.hide();

            // Сброс формы
            document.getElementById('requestTypeForm').reset();
            document.getElementById('requestTypeId').value = '';
        }
    });

    // Обработчик открытия модального окна для создания
    document.querySelector('[data-bs-target="#addRequestTypeModal"]').addEventListener('click', () => {
        document.getElementById('requestTypeForm').reset();
        document.getElementById('requestTypeId').value = '';
        document.querySelector('#addRequestTypeModal .modal-title').textContent = 'Добавить новый тип заявки';
    });

    // Обработчик закрытия модального окна
    document.getElementById('addRequestTypeModal').addEventListener('hidden.bs.modal', () => {
        document.getElementById('requestTypeForm').reset();
        document.getElementById('requestTypeId').value = '';
    });

    // Обработчики для кнопок редактирования и удаления типов заявок
    document.addEventListener('click', function(e) {
        if (e.target.matches('.edit-request-type-btn') || e.target.closest('.edit-request-type-btn')) {
            e.preventDefault();
            const button = e.target.closest('.edit-request-type-btn');
            const id = button.dataset.id;
            const name = button.dataset.name;
            const color = button.dataset.color;
            handleEditRequestType(id, name, color);
        }

        if (e.target.matches('.delete-request-type-btn') || e.target.closest('.delete-request-type-btn')) {
            e.preventDefault();
            const button = e.target.closest('.delete-request-type-btn');
            const id = button.dataset.id;
            const name = button.dataset.name;
            deleteRequestType(id, name);
        }
    });
}

// Функции для обработки редактирования и удаления типов заявок
function handleEditRequestType(id, name, color) {
    document.getElementById('requestTypeId').value = id;
    document.getElementById('requestTypeName').value = name;
    document.getElementById('requestTypeColor').value = color;
    document.querySelector('#addRequestTypeModal .modal-title').textContent = 'Редактировать тип заявки';

    const modal = new bootstrap.Modal(document.getElementById('addRequestTypeModal'));
    modal.show();
}