// form-handlers.js

import { showAlert, postData, fetchData } from './utils.js';

// Глобальная переменная для хранения текущей даты
const currentDateState = {
    // Инициализируем текущей датой в формате DD.MM.YYYY
    date: new Date().toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
};

// Структура для хранения выбранной в календаре даты
// Экспортируемый объект состояния даты
export const selectedDateState = {
    // Инициализируем текущей датой в формате DD.MM.YYYY
    date: new Date().toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    }),
    // Метод для обновления даты из календаря
    updateDate(newDate) {
        this.date = newDate;
        // console.log('Дата в selectedDateState обновлена:', this.date);
    } 
};

export const executionDateState = {
    // Инициализируем текущей датой в формате DD.MM.YYYY
    date: null,
    // Метод для обновления даты из календаря
    updateDate(newDate) {
        // Проверяем формат даты и преобразуем его при необходимости
        if (newDate && typeof newDate === 'string') {
            // Если дата в формате YYYY-MM-DD, преобразуем в DD.MM.YYYY
            if (newDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const dateParts = newDate.split('-');
                this.date = `${dateParts[2]}.${dateParts[1]}.${dateParts[0]}`;
                // console.log('Дата преобразована из YYYY-MM-DD в DD.MM.YYYY:', this.date);
                return;
            }
        }
        // Если формат другой или преобразование не требуется, сохраняем как есть
        this.date = newDate;
        // console.log('Дата в executionDateState обновлена:', this.date);
    }
};

export const selectedRequestState = {
    id: null,
    number: null,
    execution_date: null,
    status_name: null,
    status_color: null,
    street: null,
    houses: null,
    district: null,
    client_phone: null,
    operator_name: null,
    request_date: null,
    brigade_id: null,
    
    updateRequest(newRequest) {
        this.id = newRequest.id;
        this.number = newRequest.number;
        this.execution_date = newRequest.execution_date;
        this.status_name = newRequest.status_name;
        this.status_color = newRequest.status_color;
        this.street = newRequest.street;
        this.houses = newRequest.houses;
        this.district = newRequest.district;
        this.client_phone = newRequest.client_phone;
        this.operator_name = newRequest.operator_name;
        this.request_date = newRequest.request_date;
        this.brigade_id = newRequest.brigade_id;
    },

    clearRequest() {
        this.id = null;
        this.number = null;
        this.execution_date = null;
        this.status_name = null;
        this.status_color = null;
        this.street = null;
        this.houses = null;
        this.district = null;
        this.client_phone = null;
        this.operator_name = null;
        this.request_date = null;
        this.brigade_id = null;
    },

    updateStatus(newStatus, newColor = null) {
        this.status_name = newStatus;
        if (newColor) {
            this.status_color = newColor;
        }
    },

    updateExecutionDate(newDate) {
        this.execution_date = newDate;
    },

    updateAddress(newAddress) {
        this.street = newAddress.street;
        this.houses = newAddress.houses;
        this.district = newAddress.district;
    },
    
    updateClientPhone(newPhone) {
        this.client_phone = newPhone;
    },

    updateOperatorName(newName) {
        this.operator_name = newName;
    },

    updateRequestDate(newDate) {
        this.request_date = newDate;
    },

    updateBrigadeId(newId) {
        this.brigade_id = newId;
    },

    // Обработчики событий
    listeners: {
        onStatusChange: [],
        onDateChange: [],
        onRequestChange: []
    },
    
    // Методы для добавления слушателей
    addStatusChangeListener(callback) {
        this.listeners.onStatusChange.push(callback);
    },
    
    // Методы для вызова слушателей
    notifyStatusChange(oldStatus, newStatus) {
        this.listeners.onStatusChange.forEach(callback => 
            callback(oldStatus, newStatus, this));
    }
};

// Добавляем объект в глобальную область видимости для обратной совместимости
window.selectedDateState = selectedDateState;
window.executionDateState = executionDateState;

/**
 * Отображает информацию о сотруднике в блоке employeeInfo
 * @param {Object} employeeData - данные о сотруднике
 */
function displayEmployeeInfo(employeeData) {
    const employeeInfoBlock = document.getElementById('employeeInfo');

    if (!employeeInfoBlock || !employeeData) return;
    
    console.log('Получены данные сотрудника:', employeeData);
    
    // Форматирование даты рождения, если она есть
    const birthDate = employeeData.birth_date ? new Date(employeeData.birth_date).toLocaleDateString('ru-RU') : 'Не указана';
    
    // Форматирование даты выдачи паспорта, если она есть
    const passportIssuedAt = employeeData.passport && employeeData.passport.issued_at 
        ? new Date(employeeData.passport.issued_at).toLocaleDateString('ru-RU') 
        : 'Не указана';
    
    // Подготовка блока с паспортными данными, если они есть
    let passportHtml = '';
    
    if (employeeData.passport) {
        passportHtml = `
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">Паспортные данные</div>
                <div class="card-body">
                    <p><strong>Серия и номер:</strong> ${employeeData.passport.series_number || 'Не указаны'}</p>
                    <p><strong>Кем выдан:</strong> ${employeeData.passport.issued_by || 'Не указано'}</p>
                    <p><strong>Дата выдачи:</strong> ${passportIssuedAt}</p>
                    <p><strong>Код подразделения:</strong> ${employeeData.passport.department_code || 'Не указан'}</p>
                </div>
            </div>
        `;
    }
    
    // Подготовка блока с данными об автомобиле, если они есть
    let carHtml = '';
    if (employeeData.car) {
        carHtml = `
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">Данные об автомобиле</div>
                <div class="card-body">
                    <p><strong>Марка:</strong> ${employeeData.car.brand || 'Не указана'}</p>
                    <p><strong>Госномер:</strong> ${employeeData.car.license_plate || 'Не указан'}</p>
                </div>
            </div>
        `;
    }
    
    // Создаем HTML для отображения основной информации
    const mainInfoHtml = `
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">Информация о сотруднике ID: ${employeeData.employee_id || employeeData.id || ''}</div>
            <div class="card-body">
                <p><strong>ФИО:</strong> ${employeeData.fio || 'Не указано'}</p>
                <p><strong>Телефон:</strong> ${employeeData.phone || 'Не указан'}</p>
                <p><strong>Дата рождения:</strong> ${birthDate}</p>
                <p><strong>Место рождения:</strong> ${employeeData.birth_place || 'Не указано'}</p>
                <p><strong>Место регистрации:</strong> ${employeeData.registration_place || 'Не указано'}</p>
                <p><strong>Должность:</strong> ${employeeData.position || 'Не указана'}</p>
            </div>
        </div>
    `;

    const btnUpdate = `
        <button id="editBtn" type="button" class="btn btn-primary w-100 mt-3
        ">Изменить</button>
    `;
    
    // Собираем все блоки вместе
    const html = mainInfoHtml + passportHtml + carHtml + btnUpdate;
    
    
    // Вставляем HTML в блок
    employeeInfoBlock.innerHTML = html;
    employeeInfoBlock.style.display = 'block';
}

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
        address_id: addressId,
        organization: data.client_organization || null,
    };

    // Логируем данные перед отправкой
    console.log('Отправляемые данные:', requestData);

    // return;

    try {
        const result = await postData('/api/requests', requestData);

        console.log('Ответ от сервера:', result);

        if (result.success) {
            showAlert('Заявка успешно создана!', 'success');

            // Обновляем дату исполнения заявки (преобразование формата происходит в методе updateDate)
            executionDateState.updateDate(result.data.request.execution_date);

            console.log('currentDateState.date:', currentDateState.date);
            console.log('selectedDateState.date:', selectedDateState.date);
            console.log('executionDateState.date:', executionDateState.date);

            

            // Динамическое формирование строки заявки и добавление её в начало таблицы
            if (currentDateState.date === selectedDateState.date && executionDateState.date === selectedDateState.date) {
                addRequestToTable(result);
            } else if (currentDateState.date !== selectedDateState.date && executionDateState.date === selectedDateState.date) {
                console.log('Добавляем заявку в таблицу, если дата исполнения заявки совпадает с выбранной датой');
                addRequestToTable(result);
            }
            
            // Не перезагружаем страницу, чтобы не потерять динамически добавленную строку
            
            // Отображаем информацию о сотруднике, если она есть в ответе
            if (result.data && result.data.employee) {
                displayEmployeeInfo(result.data.employee);
            }
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('newRequestModal'));
            modal.hide();
            
            // Reset the form
            form.reset();
            
            // Dispatch event to notify other components about the new request
            const event = new CustomEvent('requestCreated', { detail: result.data });
            document.dispatchEvent(event);
   
            // If there's a refreshRequestsTable function, call it
            if (typeof window.refreshRequestsTable === 'function') {
                // showAlert('window.refreshRequestsTable()', 'info');
                // window.refreshRequestsTable();
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

/**
 * Добавляет новую заявку в таблицу
 * @param {Object} result - результат ответа сервера
 */
function addRequestToTable(result) {
    console.log('Начало функции addRequestToTable', result);
    
    if (!result.data) {
        console.error('Отсутствует result.data');
        return false;
    }
    
    if (!result.data.request) {
        console.error('Отсутствует result.data.request');
        return false;
    }
    
    const requestData = result.data.request;
    const clientData = result.data.client || {};
    const addressData = result.data.address || {};
    const commentData = result.data.comment || {};
    
    console.log('Данные заявки:', requestData);
    console.log('Данные клиента:', clientData);
    console.log('Данные адреса:', addressData);
    console.log('Данные комментария:', commentData);
    
    // Исправление обработки комментария
    // Проверяем разные варианты структуры данных комментария
    let extractedComment = '';
    if (commentData && commentData.text) {
        extractedComment = commentData.text;
    } else if (commentData && commentData.comment) {
        extractedComment = commentData.comment;
    } else if (requestData && requestData.comment) {
        extractedComment = requestData.comment;
    } else if (result.data && result.data.comments && result.data.comments.length > 0) {
        extractedComment = result.data.comments[0].text || result.data.comments[0].comment || '';
    }
    
    console.log('Извлеченный текст комментария:', extractedComment);
    
    // Попробуем найти таблицу заявок с разными селекторами
    let requestsTable = document.querySelector('.table.table-hover.align-middle tbody');
    
    if (!requestsTable) {
        console.log('Попытка найти таблицу с другим селектором');
        requestsTable = document.querySelector('#requestsTab table tbody');
    }
    
    if (!requestsTable) {
        console.error('Не найдена таблица заявок');
        return false;
    }
    
    console.log('Таблица найдена:', requestsTable);
    
    // Получаем текущую дату для отображения
    const currentDate = new Date();
    const formattedDate = currentDate.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    
    // Создаем новую строку для таблицы
    const newRow = document.createElement('tr');
    newRow.className = 'align-middle status-row';
    newRow.dataset.requestId = requestData.id;
    newRow.style.setProperty('--status-color', requestData.status_color || '#e2e0e6');
    
    // Формируем адрес из данных адреса
    const street = addressData.street || '';
    const house = addressData.house || '';
    const addressText = street && house ? `ул. ${street}, д. ${house}` : 'Адрес не указан';
    
    // Получаем комментарий, если есть
    const commentText = commentData.comment || requestData.comment || '';
            
    // Формируем содержимое строки
    newRow.innerHTML = `
        <td style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">1</td>
        <td>        
            <div>${requestData.execution_date ? new Date(requestData.execution_date).toLocaleDateString('ru-RU') : formattedDate}</div>
            <div class="text-dark" style="font-size: 0.8rem;">${requestData.number || 'REQ-' + formattedDate.replace(/\./g, '') + '-' + String(requestData.id).padStart(4, '0')}</div>
        </td>
        <td style="width: 12rem; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;">
            <small class="text-dark text-truncate d-block" data-bs-toggle="tooltip" data-bs-original-title="${addressText}">
                ${addressText}
            </small>
            <small class="text-truncate d-block">
                ${clientData.phone || requestData.client_phone || 'Нет телефона'}
            </small>
        </td>
        <td style="width: 20rem; max-width: 20rem; overflow: hidden; text-overflow: ellipsis;">
            ${extractedComment ? `
                <div class="comment-preview small text-dark" 
                    data-bs-toggle="tooltip" 
                    style="background-color: white; border: 1px solid gray; border-radius: 3px; padding: 5px; line-height: 16px; font-size: smaller;" 
                    data-bs-original-title="${extractedComment}">
                    <p style="font-weight: bold; margin-bottom: 2px;">Печатный комментарий:</p>
                    ${extractedComment}
                </div>
                <div class="mt-1">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                            data-bs-toggle="modal"
                            data-bs-target="#commentsModal"
                            data-request-id="${requestData.id}"
                            style="position: relative; z-index: 1;">
                        <i class="bi bi-chat-left-text me-1"></i>Все комментарии
                        <span class="badge bg-primary rounded-pill ms-1">
                            1
                        </span>
                    </button>
                </div>
            ` : ''}
        </td>
        <td>
            <span class="brigade-lead-text">${requestData.operator_name || 'Не указан'}</span><br>
            <span class="brigade-lead-text">${requestData.request_date ? new Date(requestData.request_date).toLocaleDateString('ru-RU') : formattedDate}</span>
        </td>
        <td>
            <div style="font-size: 0.75rem; line-height: 1.2;">
                Не назначена
            </div>
        </td>
        <td class="text-nowrap">
            <div class="d-flex flex-column gap-1">
                <button type="button" class="btn btn-sm btn-outline-primary assign-team-btn p-1" data-request-id="${requestData.id}">
                    <i class="bi bi-people me-1"></i>Назначить бригаду
                </button>
                <button type="button" class="btn btn-sm btn-outline-success transfer-request-btn p-1" 
                        style="--bs-btn-color: #198754; --bs-btn-border-color: #198754; --bs-btn-hover-bg: rgba(25, 135, 84, 0.1); --bs-btn-hover-border-color: #198754;" 
                        data-request-id="${requestData.id}">
                    <i class="bi bi-arrow-left-right me-1"></i>Перенести заявку
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger cancel-request-btn p-1" data-request-id="${requestData.id}">
                    <i class="bi bi-x-circle me-1"></i>Отменить заявку
                </button>
            </div>
        </td>
        <td class="text-nowrap">
            <div class="d-flex flex-column gap-1">
                <button data-request-id="${requestData.id}" type="button" class="btn btn-sm btn-custom-brown p-1 close-request-btn">
                    Закрыть заявку
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary p-1 comment-btn" 
                        data-bs-toggle="modal" data-bs-target="#commentsModal" 
                        data-request-id="${requestData.id}">
                    <i class="bi bi-chat-left-text me-1"></i>Комментарий
                </button>
                <button data-request-id="${requestData.id}" type="button" class="btn btn-sm btn-outline-success add-photo-btn">
                    <i class="bi bi-camera me-1"></i>Фотоотчет
                </button>
            </div>
        </td>
    `;
    
    // Добавляем строку в начало таблицы
    const firstRow = requestsTable.querySelector('tr');
    if (firstRow) {
        requestsTable.insertBefore(newRow, firstRow);
    } else {
        requestsTable.appendChild(newRow);
    }
    
    // Инициализируем тултипы Bootstrap для новых элементов
    const tooltips = newRow.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
    
    // Обновляем нумерацию строк
    updateRowNumbers();
    
    console.log('Строка успешно добавлена в таблицу');
    return true;
}

/**
 * Обновляет нумерацию строк в таблице заявок
 */
function updateRowNumbers() {
    console.log('Обновление нумерации строк');
    
    // Попробуем найти таблицу заявок с разными селекторами
    let rows = document.querySelectorAll('.table.table-hover.align-middle tbody tr');
    
    if (!rows.length) {
        rows = document.querySelectorAll('#requestsTab table tbody tr');
    }
    
    console.log('Найдено строк для обновления:', rows.length);
    
    rows.forEach((row, index) => {
        const numberCell = row.querySelector('td:first-child');
        if (numberCell) {
            numberCell.textContent = index + 1;
        }
    });
}

// Делаем функции доступными глобально
window.submitRequestForm = submitRequestForm;
window.displayEmployeeInfo = displayEmployeeInfo;
window.updateRowNumbers = updateRowNumbers;
window.addRequestToTable = addRequestToTable;
window.handleCommentEdit = handleCommentEdit;

/**
 * Функция для обработки редактирования комментария
 * @param {HTMLElement} commentElement - Элемент комментария
 * @param {number} commentId - ID комментария
 * @param {number} commentNumber - Порядковый номер комментария
 * @param {HTMLElement} editButton - Кнопка редактирования
 * @returns {void}
 */
async function handleCommentEdit(commentElement, commentId, commentNumber, editButton) {
    // Получаем текущий текст комментария
    const commentText = commentElement.textContent;
    
    // Создаем поле для редактирования
    const inputElement = document.createElement('textarea');
    inputElement.className = 'form-control mb-2';
    inputElement.style.width = '730px';
    inputElement.style.minHeight = '60px';
    inputElement.value = commentText;
    
    // Создаем кнопки Сохранить/Отмена
    const saveButton = document.createElement('button');
    saveButton.className = 'btn btn-sm btn-success me-2';
    saveButton.textContent = 'Сохранить';
    
    const cancelButton = document.createElement('button');
    cancelButton.className = 'btn btn-sm btn-secondary';
    cancelButton.textContent = 'Отмена';
    
    // Создаем контейнер для поля ввода
    const inputContainer = document.createElement('div');
    inputContainer.className = 'mb-2';
    inputContainer.style.width = '100%';
    
    // Создаем контейнер для кнопок
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'mb-2';
    
    // Создаем общий контейнер для редактирования
    const editContainer = document.createElement('div');
    editContainer.className = 'edit-comment-container';
    editContainer.setAttribute('data-comment-number', commentNumber);
    editContainer.setAttribute('data-comment-id', commentId);
    editContainer.style.width = '730px';
    
    // Добавляем поле ввода в контейнер
    inputContainer.appendChild(inputElement);
    editContainer.appendChild(inputContainer);
    
    // Добавляем кнопки в контейнер
    buttonContainer.appendChild(saveButton);
    buttonContainer.appendChild(cancelButton);
    editContainer.appendChild(buttonContainer);
    
    // Скрываем параграф и вставляем наш контейнер после него
    commentElement.style.display = 'none';
    commentElement.parentNode.insertBefore(editContainer, commentElement.nextSibling);
    
    // Скрываем кнопку редактирования
    editButton.style.display = 'none';
    
    // Обработчик кнопки Сохранить
    saveButton.addEventListener('click', async function() {
        const newText = inputElement.value.trim();

        console.log('newText', newText);

        console.log('commentId', commentId);

        if (newText) {
            try {
                // Показываем индикатор загрузки
                saveButton.disabled = true;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';
                
                // Отправляем запрос на сервер
                const url = `/api/comments/${commentId}`;
                
                const response = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ content: newText }),
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Ошибка при сохранении комментария');
                }
                
                const result = await response.json();
                
                // Показываем уведомление об успехе
                showAlert('Комментарий успешно обновлен', 'success');
                
                // Обновляем текст комментария в DOM
                commentElement.textContent = newText;
                commentElement.style.display = '';
                editButton.style.display = 'inline-block';
                
                // Удаляем контейнер редактирования
                editContainer.remove();
                
            } catch (error) {
                console.error('Ошибка при сохранении комментария:', error);
                
                // Показываем уведомление об ошибке
                showAlert(`Ошибка: ${error.message}`, 'danger');
                
                // Возвращаем кнопку в исходное состояние
                saveButton.disabled = false;
                saveButton.textContent = 'Сохранить';
            }
        }
    });
    
    // Обработчик кнопки Отмена
    cancelButton.addEventListener('click', function() {
        // Возвращаем обычный вид комментария
        commentElement.style.display = '';
        editButton.style.display = 'inline-block';
        
        // Удаляем контейнер редактирования
        editContainer.remove();
    });
    
    // Фокус на поле ввода
    inputElement.focus();
}

// Экспортируем функции для использования в других модулях
export { submitRequestForm, displayEmployeeInfo, updateRowNumbers, addRequestToTable, handleCommentEdit };