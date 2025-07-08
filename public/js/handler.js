import { showAlert } from './utils.js';

// Константы для идентификаторов
const FILTER_IDS = {
    STATUSES: 'filter-statuses',
    TEAMS: 'filter-teams',
    DATEPICKER: 'datepicker',
    RESET_BUTTON: 'reset-filters-button',
    LOGOUT_BUTTON: 'logout-button',
    CALENDAR_BUTTON: 'btn-open-calendar',
    CALENDAR_CONTENT: 'calendar-content',
    BRIGADE_MODAL: 'brigadeModal',
    BRIGADE_DETAILS: 'brigadeDetails'
};

// Навигационные вкладки
const NAV_TABS = ['requests', 'teams', 'addresses', 'users', 'reports'];

// Состояние фильтров
const filterState = {
    statuses: [],
    teams: [],
    date: null
};

// Функция для обновления таблицы заявок
window.refreshRequestsTable = function() {
    console.log('Обновление таблицы заявок...');

    // Если есть активная дата, используем её для фильтрации
    if (filterState.date) {
        applyFilters();
    } else {
        // Иначе просто перезагружаем страницу
        window.location.reload();
    }
};

// Функция для применения фильтров
function applyFilters() {
    // Собираем все выбранные фильтры
    const activeFilters = {
        statuses: [...filterState.statuses],
        teams: [...filterState.teams],
        date: filterState.date
    };

    console.log('Применение фильтров:', activeFilters);

    // Если выбрана дата, делаем запрос на сервер
    if (filterState.date) {
        // Конвертируем дату из DD.MM.YYYY в YYYY-MM-DD
        const [day, month, year] = filterState.date.split('.');
        const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;

        // Формируем URL с отформатированной датой
        const apiUrl = `/api/requests/date/${formattedDate}`;

        // Логи запросов отключены

        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(async response => {
                const data = await response.json().catch(() => ({}));

                // Логи ответов отключены

                if (!response.ok) {
                    const error = new Error(data.message || `Ошибка HTTP: ${response.status}`);
                    error.response = response;
                    error.data = data;
                    throw error;
                }

                return data;
            })
            .then(data => {
                // Логи ответов отключены
                if (data) {
                    if (data.success === false) {
                        // Логи сообщений отключены
                        showAlert(data.message || 'Ошибка при загрузке заявок', 'danger');
                        return;
                    }

                    // Логи данных заявок отключены

                    // Логируем первую заявку для отладки
                    if (data.data && data.data.length > 0) {
                        // Отладочные логи полей заявки отключены
                    } else {
                        console.info('На выбранную дату заявок нет');
                    }

                    const tbody = document.querySelector('table.table-hover tbody');
                    if (!tbody) {
                        console.error('Не найден элемент tbody для вставки данных');
                        return;
                    }

                    // Очищаем существующие строки и скрываем сообщение о пустом списке
                    tbody.innerHTML = '';
                    const noRequestsRow = document.getElementById('no-requests-row');
                    if (noRequestsRow) {
                        noRequestsRow.classList.add('d-none');
                    }

                    // Добавляем новые строки с данными
                    if (Array.isArray(data.data) && data.data.length > 0) {
                        // Скрываем сообщение о пустом списке
                        const noRequestsRow = document.getElementById('no-requests-row');
                        if (noRequestsRow) {
                            noRequestsRow.classList.add('d-none');
                        }
                        data.data.forEach(request => {
                            // Отладочная информация
                            // Логи заявок отключены

                            // Форматируем дату с проверкой на валидность
                            let formattedDate = 'Не указана';
                            let requestDate = '';
                            try {
                                // Пробуем использовать request_date, если он есть, иначе created_at
                                const dateStr = request.execution_date;
                                // Логи дат отключены

                                if (dateStr) {
                                    const date = new Date(dateStr);
                                    if (!isNaN(date.getTime())) {
                                        formattedDate = date.toLocaleDateString('ru-RU', {
                                            day: '2-digit',
                                            month: '2-digit',
                                            year: 'numeric'
                                        });

                                        // Форматируем дату для номера заявки
                                        requestDate = [
                                            String(date.getDate()).padStart(2, '0'),
                                            String(date.getMonth() + 1).padStart(2, '0'),
                                            date.getFullYear()
                                        ].join('');
                                    }
                                }
                            } catch (e) {
                                console.error('Ошибка форматирования даты:', e, 'Request:', request);
                            }

                            // Формируем номер заявки
                            const requestNumber = request.number ||
                                `REQ-${requestDate}-${String(request.id).padStart(4, '0')}`;

                            // Формируем адрес
                            const address = [
                                request.street ? `ул. ${request.street}` : '',
                                request.houses ? `д. ${request.houses}` : ''
                            ].filter(Boolean).join(', ') || 'Не указан';

                            // Создаем строку с составом бригады
                            let brigadeMembers = 'Не назначена';
                            if (request.brigade_members && request.brigade_members.length > 0) {
                                brigadeMembers = request.brigade_members
                                    .map(member => `<div>${member.name || member}</div>`)
                                    .join('');

                                brigadeMembers += `
                                <a href="#" class="text-black hover:text-gray-700 hover:underline view-brigade-btn"
                                   style="text-decoration: none; font-size: 0.75rem; line-height: 1.2;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'"
                                   data-bs-toggle="modal"
                                   data-bs-target="#brigadeModal"
                                   data-brigade-id="${request.brigade_id || ''}">
                                    подробнее...
                                </a>`;
                            }


                            // Создаем HTML строки таблицы
                            const row = document.createElement('tr');
                            row.className = 'align-middle status-row';
                            row.style.setProperty('--status-color', request.status_color || '#e2e0e6');
                            // Отладочный вывод
                            // Логи данных запроса отключены

                            row.setAttribute('data-request-id', request.id);

                            // console.log(request);

                            row.innerHTML = `
                            <td style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${request.id}</td>
                            <td class="text-center" style="width: 1rem;">
                            ${request.status_name !== 'выполнена' ? `
                                <input type="checkbox" id="request-${request.id}" class="form-check-input request-checkbox" value="${request.id}" aria-label="Выбрать заявку">
                            ` : ''}
                            </td>
                            <td>
                                <div>${formattedDate}</div>
                                <div class="text-dark" style="font-size: 0.8rem;">${requestNumber}</div>
                            </td>

                            <td style="width: 12rem; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;">
                                <small class="text-dark text-truncate d-block" data-bs-toggle="tooltip" title="${request.address || address}">
                                    ${request.address || address}
                                </small>
                                <small class="text-success_ fw-bold_ text-truncate d-block">
                                    ${request.phone || request.client_phone || ''}
                                </small>
                            </td>

                            <td style="width: 20rem; max-width: 20rem; overflow: hidden; text-overflow: ellipsis;">
                                ${(() => {
                                    if (!request.comments) return '---';

                                    // Если comments - это массив, берем последний комментарий (новейший)
                                    let commentText = '';
                                    if (Array.isArray(request.comments) && request.comments.length > 0) {
                                        // Берем последний элемент массива (новейший комментарий)
                                        const lastComment = request.comments[request.comments.length - 1];
                                        commentText = lastComment.text || lastComment.comment || lastComment.content || JSON.stringify(lastComment);
                                    }
                                    // Если comments - это объект, но не массив
                                    else if (typeof request.comments === 'object' && request.comments !== null) {
                                        commentText = request.comments.text || request.comments.comment || request.comments.content || JSON.stringify(request.comments);
                                    }
                                    // Если comments - это строка
                                    else if (typeof request.comments === 'string') {
                                        commentText = request.comments;
                                    } else {
                                        return '---';
                                    }

                                    // Экранируем кавычки для атрибута title
                                    const escapedComment = String(commentText).replace(/"/g, '&quot;');
                                    const displayText = commentText;
                                    // const displayText = commentText.length > 50 ? commentText.substring(0, 50) + '...' : commentText;

                                    return `
                                        <div class="comment-preview small text-dark" data-bs-toggle="tooltip" title="${escapedComment}">
                                            ${displayText}
                                        </div>`;
                                })()}
                                <!-- Кнопка комментариев -->
                                <div class="mt-1">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#commentsModal"
                                            data-request-id="${request.id}"
                                            style="position: relative; z-index: 1;"
                                            ${(request.comments_count > 0 || (request.comments && request.comments.length > 0)) ? '' : 'disabled'}>
                                        <i class="bi bi-chat-left-text me-1"></i>Все комментарии
                                        ${(request.comments_count > 0 || (request.comments && request.comments.length > 0)) ?
                                    `<span class="badge bg-primary rounded-pill ms-1">
                                                ${request.comments_count || (request.comments ? request.comments.length : 0)}
                                            </span>` :
                                    ''
                                }
                                    </button>
                                </div>
                            </td>

                            <td>
                                <span class="brigade-lead-text">${request.operator_name || 'Не указан'}</span><br>
                                <span class="brigade-lead-text">${formattedDate}</span>
                            </td>

                            <td>
                                <div style="font-size: 0.75rem; line-height: 1.2;">
                                    ${brigadeMembers}
                                </div>
                            </td>

                            <td class="text-nowrap">
                                <div class="d-flex flex-column gap-1">
                                ${request.status_name !== 'выполнена' ? `
                                    <button type="button" class="btn btn-sm btn-outline-primary assign-team-btn p-1" data-request-id="${request.id}">
                                        <i class="bi bi-people me-1"></i>Назначить бригаду
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success transfer-request-btn p-1" style="--bs-btn-color: #198754; --bs-btn-border-color: #198754; --bs-btn-hover-bg: rgba(25, 135, 84, 0.1); --bs-btn-hover-border-color: #198754;" data-request-id="${request.id}">
                                        <i class="bi bi-arrow-left-right me-1"></i>Перенести заявку
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger cancel-request-btn p-1" data-request-id="${request.id}">
                                        <i class="bi bi-x-circle me-1"></i>Отменить заявку
                                    </button>
                                ` : ''}
                                </div>
                            </td>

                            <td class="text-nowrap">
                                <div class="d-flex flex-column gap-1">
                                    ${request.status_name !== 'выполнена' ? `
                                        <button data-request-id="${request.id}" type="button" class="btn btn-sm btn-custom-brown p-1 close-request-btn">
                                            Закрыть заявку
                                        </button>
                                    ` : ''}
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 comment-btn" data-bs-toggle="modal" data-bs-target="#commentsModal" data-request-id="${request.id}">
                                        <i class="bi bi-chat-left-text me-1"></i>Комментарий
                                    </button>
                                    <button data-request-id="${request.id}" type="button" class="btn btn-sm btn-outline-success add-photo-btn">
                                        <i class="bi bi-camera me-1"></i>Фотоотчет
                                    </button>
                                </div>
                            </td>
                        `;

                            tbody.appendChild(row);
                        });

                        // Инициализируем тултипы
                        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    } else {
                        // Если данных нет, показываем сообщение
                        const noRequestsRow = document.getElementById('no-requests-row');
                        if (noRequestsRow) {
                            noRequestsRow.classList.remove('d-none');
                        }
                    }

                    // Обновляем счетчик загруженных заявок
                    updateRequestsCount(Array.isArray(data.data) ? data.data.length : 0);

                    // Обновляем отображение счетчика
                    const countElement = document.querySelector('.requests-count');
                    if (countElement) {
                        countElement.textContent = data.count || 0;
                    }
                    showAlert(`Загружено заявок: ${data.count}`, 'success');
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке заявок 2:', error);
                const errorMessage = error.data?.message || error.message || 'Неизвестная ошибка';
                showAlert(`Ошибка при загрузке заявок: ${errorMessage}`, 'danger');
            });
    }

    // Логи фильтров отключены
}

// Функция для обновления счетчика заявок
function updateRequestsCount(count) {
    const counterElement = document.querySelector('.requests-count');
    if (counterElement) {
        counterElement.textContent = count;
    }
}

// Функция для логирования кликов
function logButtonClick(buttonId, buttonText) {
    // Логи кликов отключены
}

// Функция для отображения ошибок в модальном окне
function showError(message) {
    const modalBody = document.querySelector(`#${FILTER_IDS.BRIGADE_MODAL} .modal-body`);
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                ${message}
            </div>`;
    }
}

// Функция для отображения индикатора загрузки
function showLoader(modalBody) {
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <p class="mt-2">Загрузка данных о бригаде...</p>
            </div>`;
    }
}

// Функция для инициализации модального окна
function initBrigadeModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (!modalElement) return null;

    // Инициализация модального окна
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: true
    });

    // Очистка содержимого при закрытии
    modalElement.addEventListener('hidden.bs.modal', function () {
        // Удаляем класс modal-open с body, который добавляет Bootstrap
        document.body.classList.remove('modal-open');
        // Удаляем backdrop, который создает затемнение
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }

        const modalBody = this.querySelector('.modal-body');
        if (modalBody) {
            showLoader(modalBody);
        }

        // Сбрасываем стили, которые могли быть добавлены
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });

    return { modal, modalElement };
}

// Загрузка и отображение кнопок статусов
function loadStatusButtons() {
    fetch('/api/request-statuses/all', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка при загрузке статусов');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.statuses) {
                const statusButtonsContainer = document.getElementById('status-buttons');

                // Очищаем контейнер перед добавлением кнопок
                statusButtonsContainer.innerHTML = '';

                // Создаем кнопку для всех статусов
                const allButton = document.createElement('button');
                allButton.type = 'button';
                allButton.className = 'btn btn-outline-secondary btn-sm mb-3';
                allButton.textContent = 'Все';
                allButton.dataset.statusId = 'all';
                allButton.addEventListener('click', () => handleStatusFilterClick('all'));
                statusButtonsContainer.appendChild(allButton);

                // Создаем кнопки для каждого статуса
                data.statuses.forEach(status => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-sm mb-3';
                    button.style.backgroundColor = status.color || '#6c757d';
                    button.style.borderColor = status.color || '#6c757d';
                    button.style.color = getContrastColor(status.color || '#6c757d');
                    button.textContent = status.name;
                    button.dataset.statusId = status.id;
                    button.addEventListener('click', () => handleStatusFilterClick(status.id));
                    statusButtonsContainer.appendChild(button);
                });
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке статусов:', error);
        });
}

// Функция для определения контрастного цвета текста
function getContrastColor(hexColor) {
    // Если цвет не передан, возвращаем белый
    if (!hexColor) return '#ffffff';

    // Удаляем символ #, если он есть
    const hex = hexColor.replace('#', '');

    // Конвертируем HEX в RGB
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);

    // Вычисляем яркость по формуле W3C
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;

    // Возвращаем черный для светлых цветов и белый для темных
    return brightness > 128 ? '#000000' : '#ffffff';
}

// Обработчик клика по кнопке статуса
function handleStatusFilterClick(statusId) {
    // Сбрасываем активное состояние у всех кнопок
    document.querySelectorAll('#status-buttons button').forEach(btn => {
        btn.classList.remove('active');
    });

    // Устанавливаем активное состояние для нажатой кнопки
    const clickedButton = document.querySelector(`#status-buttons button[data-status-id="${statusId}"]`);
    if (clickedButton) {
        clickedButton.classList.add('active');
    }

    // Здесь можно добавить логику фильтрации заявок по статусу
    console.log('Выбран статус с ID:', statusId);

    // Если нужно применить фильтр к таблице заявок, раскомментируйте следующую строку:
    // applyFilter('status', statusId === 'all' ? null : statusId);
}

// Функция для загрузки списка адресов
function loadAddresses() {
    const selectElement = document.getElementById('addresses_id');
    if (!selectElement) return;

    // Показываем индикатор загрузки
    const originalInnerHTML = selectElement.innerHTML;
    selectElement.innerHTML = '<option value="" disabled selected>Загрузка адресов...</option>';

    // Загружаем адреса с сервера
    fetch('/api/addresses')
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка загрузки адресов');
            }
            return response.json();
        })
        .then(addresses => {
            // Очищаем список и добавляем заглушку
            selectElement.innerHTML = '<option value="" disabled selected>Выберите адрес</option>';

            // Добавляем адреса в выпадающий список
            addresses.forEach(address => {
                const option = document.createElement('option');
                option.value = address.id;
                option.textContent = address.full_address;
                // Добавляем дополнительные данные для удобства
                option.dataset.street = address.street;
                option.dataset.houses = address.houses;
                option.dataset.city = address.city;
                option.dataset.district = address.district;

                selectElement.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Ошибка при загрузке адресов:', error);
            selectElement.innerHTML = originalInnerHTML;
            showAlert('Ошибка при загрузке списка адресов', 'danger');
        });
}

// Функция для обновления скрытого поля с данными о составе бригады
function updateBrigadeMembersFormField() {
    // Просто вызываем валидацию, которая обновит данные
    validateBrigadeMembers();
}

// Функция для получения всех участников бригады
function getAllBrigadeMembers() {
    const leaderId = document.querySelector('select[name="leader_id"]')?.value;
    const memberInputs = Array.from(document.querySelectorAll('input[name^="brigade_members["]'));
    const members = [];
    const memberIds = new Set();

    // 1. Собираем всех участников из скрытых полей
    memberInputs.forEach(input => {
        if (input.value) {
            const employeeId = input.value;
            if (!memberIds.has(employeeId)) {
                members.push({
                    employee_id: employeeId,
                    is_leader: employeeId === leaderId,
                    name: input.dataset.name || `Сотрудник ${employeeId}`
                });
                memberIds.add(employeeId);
            }
        }
    });

    // 2. Добавляем бригадира, если его еще нет в списке
    if (leaderId && !memberIds.has(leaderId)) {
        const leaderSelect = document.querySelector('select[name="leader_id"]');
        const leaderOption = leaderSelect?.options[leaderSelect.selectedIndex];

        members.unshift({
            employee_id: leaderId,
            is_leader: true,
            name: leaderOption?.dataset.fullName || `Бригадир ${leaderId}`
        });
        memberIds.add(leaderId);
    }

    return members;
}

// Функция для валидации состава бригады
function validateBrigadeMembers() {
    // Обновляем данные перед валидацией
    const members = getAllBrigadeMembers();
    const leaderId = document.querySelector('select[name="leader_id"]')?.value;

    // Обновляем скрытое поле с данными
    let hiddenField = document.getElementById('brigade_members_data');
    if (!hiddenField) {
        hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.id = 'brigade_members_data';
        hiddenField.name = 'brigade_members_data';
        document.getElementById('brigadeForm')?.appendChild(hiddenField);
    }
    hiddenField.value = JSON.stringify(members);

    // Проверяем валидность
    const hasLeader = !!leaderId;
    const totalMembers = members.length;
    const isValid = hasLeader && totalMembers >= 2;

    // Обновляем UI
    const errorElement = document.querySelector('#brigade-members-error');
    if (errorElement) {
        errorElement.style.display = isValid ? 'none' : 'block';
        const missing = 2 - totalMembers;
        errorElement.textContent = missing > 0
            ? `Добавьте ещё ${missing} участника`
            : 'В бригаде должно быть минимум 2 участника, включая бригадира';
    }

    const submitButton = document.querySelector('button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = !isValid;
    }

    console.log('Валидация бригады:', {
        members,
        totalMembers,
        hasLeader,
        isValid,
        hiddenFieldValue: hiddenField.value,
        formData: Array.from(new FormData(document.getElementById('brigadeForm')))
    });

    return isValid;
}

// Функция для инициализации всех обработчиков при загрузке страницы
function initializePage() {
    // Загружаем кнопки статусов
    loadStatusButtons();

    // Загружаем список адресов
    loadAddresses();

    // Обработчик кнопки выхода
    const logoutButton = document.getElementById(FILTER_IDS.LOGOUT_BUTTON);
    if (logoutButton) {
        logoutButton.addEventListener('click', function (e) {
            e.preventDefault();
            logButtonClick('logout-button', 'Выход');

            // Показываем подтверждение выхода
            if (confirm('Вы уверены, что хотите выйти?')) {
                // Отправляем запрос на выход
                fetch('/logout', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при выходе:', error);
                        window.location.href = '/login';
                    });
            }
        });
    }

    // Вкладки навигации
    NAV_TABS.forEach(tab => {
        const tabElement = document.getElementById(`${tab}-tab`);
        if (tabElement) {
            // Добавляем специальный обработчик для вкладки заявок
            if (tab === 'requests') {
                tabElement.addEventListener('shown.bs.tab', async function() {
                    try {
                        const response = await fetch('/api/brigades/current', {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });

                        if (!response.ok) {
                            throw new Error('Ошибка при загрузке бригад');
                        }

                        const brigades = await response.json();
                        const select = document.getElementById('brigade-leader-select');

                        if (select) {
                            // Сохраняем текущее выбранное значение
                            const currentValue = select.value;

                            // Очищаем список, оставляя только первый элемент
                            while (select.options.length > 1) {
                                select.remove(1);
                            }

                            // Добавляем актуальные бригады
                            brigades.forEach(brigade => {
                                const option = new Option(
                                    `[Бригада: ${brigade.brigade_id}] [Бригадир: ${brigade.leader_name}]`,
                                    brigade.employee_id,
                                    false,
                                    false
                                );
                                option.dataset.brigadeId = brigade.brigade_id;
                                select.add(option);
                            });

                            // Восстанавливаем выбранное значение, если оно есть в новом списке
                            if (currentValue && Array.from(select.options).some(opt => opt.value === currentValue)) {
                                select.value = currentValue;
                            }
                        }
                    } catch (error) {
                        console.error('Ошибка при обновлении списка бригад:', error);
                    }
                });
            }
            // Добавляем специальный обработчик для вкладки бригад
            else if (tab === 'teams') {
                tabElement.addEventListener('shown.bs.tab', async function() {
                    try {
                        const response = await fetch('/api/employees', {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });

                        if (!response.ok) {
                            throw new Error('Ошибка при загрузке списка сотрудников');
                        }

                        const employees = await response.json();

                        // Обновляем select бригадира
                        const brigadeLeaderSelect = document.getElementById('brigadeLeader');
                        if (brigadeLeaderSelect) {
                            // Обработчик изменения выбранного бригадира
                            brigadeLeaderSelect.addEventListener('change', function() {
                                const selectedLeaderId = this.value;

                                const selectedLeaderName = this.options[this.selectedIndex].textContent;

                                console.log('selectedLeaderName :', selectedLeaderName);

                                // Обновляем скрытое поле leader_id сразу
                                const leaderIdInput = document.querySelector('input[name="leader_id"]');
                                if (leaderIdInput) {
                                    leaderIdInput.value = selectedLeaderId;
                                }

                                if (!selectedLeaderId) {
                                    updateBrigadeMembersFormField();
                                    return;
                                }

                                // Находим контейнер с участниками бригады
                                const brigadeMembersContainer = document.getElementById('brigadeMembers');
                                if (!brigadeMembersContainer) {
                                    console.error('Элемент #brigadeMembers не найден');
                                    return;
                                }

                                // Проверяем, есть ли уже этот участник в списке
                                const existingMember = brigadeMembersContainer.querySelector(`[data-employee-id="${selectedLeaderId}"]`);

                                // Если участника еще нет в списке, добавляем его
                                if (!existingMember) {
                                    const leaderData = this.options[this.selectedIndex].dataset;

                                    const leaderName = leaderData.fullName || `Бригадир ${selectedLeaderId}`;

                                    // Создаем элемент для отображения участника
                                    const memberElement = document.createElement('div');
                                    memberElement.className = 'd-flex justify-content-between align-items-center mb-2';
                                    memberElement.dataset.employeeId = selectedLeaderId;
                                    memberElement.dataset.isLeader = 'true';

                                    // Добавляем имя участника
                                    const nameSpan = document.createElement('span');
                                    nameSpan.textContent = selectedLeaderName + ' (Бригадир)';
                                    memberElement.appendChild(nameSpan);

                                    // Добавляем кнопку удаления
                                    const deleteButton = document.createElement('button');
                                    deleteButton.className = 'btn btn-sm btn-outline-danger';
                                    deleteButton.innerHTML = '&times;';
                                    deleteButton.onclick = function(e) {
                                        e.preventDefault();
                                        memberElement.remove();
                                        updateBrigadeMembersFormField();
                                    };
                                    memberElement.appendChild(deleteButton);

                                    // Добавляем участника в начало списка
                                    brigadeMembersContainer.insertBefore(memberElement, brigadeMembersContainer.firstChild);
                                }

                                // Обновляем данные формы и валидацию
                                updateBrigadeMembersFormField();
                            });
                            // Сохраняем текущее выбранное значение
                            const currentLeader = brigadeLeaderSelect.value;

                            // Очищаем список, оставляя только первый элемент
                            while (brigadeLeaderSelect.options.length > 1) {
                                brigadeLeaderSelect.remove(1);
                            }

                            // Добавляем сотрудников
                            employees.forEach(emp => {
                                const option = new Option(emp.fio, emp.id, false, false);
                                brigadeLeaderSelect.add(option);
                            });

                            // Восстанавливаем выбранное значение, если оно есть в новом списке
                            if (currentLeader && Array.from(brigadeLeaderSelect.options).some(opt => opt.value === currentLeader)) {
                                brigadeLeaderSelect.value = currentLeader;
                            }
                        }

                        // Обновляем multiple select сотрудников
                        const employeesSelect = document.getElementById('employeesSelect');
                        if (employeesSelect) {
                            // Сохраняем текущие выбранные значения
                            const selectedEmployees = Array.from(employeesSelect.selectedOptions).map(opt => opt.value);

                            // Очищаем список полностью
                            employeesSelect.innerHTML = '';

                            // Добавляем сотрудников
                            employees.forEach(emp => {
                                const option = new Option(emp.fio, emp.id, false, false);
                                option.dataset.employeeId = emp.id;
                                employeesSelect.add(option);

                                // Восстанавливаем выбранные значения
                                if (selectedEmployees.includes(emp.id.toString())) {
                                    option.selected = true;
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Ошибка при обновлении списка сотрудников:', error);
                    }
                });
            }

            // Общий обработчик для всех вкладок
            tabElement.addEventListener('click', function () {
                logButtonClick(`${tab}-tab`, `Вкладка ${tab}`);
            });
        }
    });

    // Кнопка сброса фильтров
    const resetFiltersButton = document.getElementById(FILTER_IDS.RESET_BUTTON);
    if (resetFiltersButton) {
        resetFiltersButton.addEventListener('click', function () {
            logButtonClick('reset-filters-button', 'Сбросить фильтры');

            // Сбрасываем состояние фильтров
            filterState.statuses = [];
            filterState.teams = [];
            filterState.date = null;

            // Снимаем выделение со всех чекбоксов фильтров
            const filterCheckboxes = [FILTER_IDS.STATUSES, FILTER_IDS.TEAMS];
            filterCheckboxes.forEach(checkboxId => {
                const checkbox = document.getElementById(checkboxId);
                if (checkbox && checkbox.checked) {
                    checkbox.checked = false;
                    // Имитируем событие change для обновления состояния
                    const event = new Event('change');
                    checkbox.dispatchEvent(event);
                }
            });

            // Сбрасываем дату, если используется datepicker
            const DATEPICKER_CLEAR_METHOD = $.fn.datepicker ? 'clearDates' : 'value';
            const DATEPICKER_CLEAR_VALUE = $.fn.datepicker ? '' : '';
            if ($.fn.datepicker) {
                $(`#${FILTER_IDS.DATEPICKER}`).datepicker({ [DATEPICKER_CLEAR_METHOD]: DATEPICKER_CLEAR_VALUE });
            } else {
                const dateInput = document.getElementById(FILTER_IDS.DATEPICKER);
                if (dateInput) dateInput.value = DATEPICKER_CLEAR_VALUE;
            }

            // Логи сброса фильтров отключены
            applyFilters();
        });
    }

    // Обработчики для чекбоксов фильтров
    const filterCheckboxes = [
        { id: FILTER_IDS.STATUSES, type: 'statuses' },
        { id: FILTER_IDS.TEAMS, type: 'teams' }
    ];

    filterCheckboxes.forEach(({ id, type }) => {
        const checkbox = document.getElementById(id);
        if (checkbox) {
            checkbox.addEventListener('change', function () {
                const label = document.querySelector(`label[for="${id}"]`);
                const labelText = label ? label.textContent.trim() : 'Неизвестный фильтр';
                const filterValue = this.value || labelText;

                if (this.checked) {
                    // Добавляем значение в соответствующий массив фильтров
                    if (!filterState[type].includes(filterValue)) {
                        filterState[type].push(filterValue);
                    }
                } else {
                    // Удаляем значение из массива фильтров
                    filterState[type] = filterState[type].filter(item => item !== filterValue);
                }

                // Логи состояния фильтров отключены
                applyFilters();
            });
        }
    });

    // Обработчик для выбора даты в календаре
    const datepicker = document.getElementById(FILTER_IDS.DATEPICKER);
    if (datepicker) {
        // Инициализация datepicker, если используется плагин
        if ($.fn.datepicker) {
            $('#datepicker').datepicker({
                format: 'dd.mm.yyyy',
                language: 'ru',
                autoclose: true,
                todayHighlight: true
            }).on('changeDate', function (e) {
                const selectedDate = e.format('dd.mm.yyyy');
                filterState.date = selectedDate;
                // Логи выбора даты отключены
                applyFilters();
            });
        } else {
            // Если плагин не загружен, используем стандартный input
            datepicker.addEventListener('change', function () {
                filterState.date = this.value;
                // Логи выбора даты отключены
                applyFilters();
            });
        }
    }

    //******* Календарь *******//

    const button = document.getElementById(FILTER_IDS.CALENDAR_BUTTON);
    const calendarContent = document.getElementById(FILTER_IDS.CALENDAR_CONTENT);

    if (button && calendarContent) {
        button.addEventListener('click', function () {
            // Логи отображения отключены

            // Переключаем видимость
            if (calendarContent.classList.contains('hide-me')) {
                calendarContent.classList.remove('hide-me');
                calendarContent.classList.add('show-me');
            } else {
                calendarContent.classList.remove('show-me');
                calendarContent.classList.add('hide-me');
            }
        });
    }

    // Обработчик клика по кнопке просмотра бригады
    document.addEventListener('click', async function (event) {
        const btn = event.target.closest('.view-brigade-btn');
        if (!btn) return;

        const brigadeId = btn.getAttribute('data-brigade-id');
        if (!brigadeId) {
            showError('Не указан ID бригады');
            return;
        }

        // Инициализация или получение модального окна
        const modalInstance = window.brigadeModal || initBrigadeModal(FILTER_IDS.BRIGADE_MODAL);
        if (!modalInstance) {
            showError('Не удалось инициализировать модальное окно');
            return;
        }

        // Сохраняем ссылку на модальное окно в глобальной области видимости
        window.brigadeModal = modalInstance;
        const { modal, modalElement } = modalInstance;

        // Возвращаем фокус на кнопку при закрытии
        const onModalHidden = function () {
            if (btn.focus) btn.focus();
            // Удаляем обработчик после срабатывания
            modalElement.removeEventListener('hidden.bs.modal', onModalHidden);

            // Принудительно скрываем модальное окно, если оно все еще видимо
            if (modalElement.classList.contains('show')) {
                modal.hide();
            }
        };

        modalElement.addEventListener('hidden.bs.modal', onModalHidden, { once: true });

        // Показываем индикатор загрузки
        const modalBody = modalElement.querySelector('.modal-body');
        showLoader(modalBody);

        // Показываем модальное окно
        modal.show();

        try {
            // Отправляем запрос к серверу
            const response = await fetch(`/brigade/${brigadeId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Ошибка HTTP: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                renderBrigadeDetails(data);
            } else {
                throw new Error(data.message || 'Не удалось загрузить данные бригады');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            showError(`Ошибка при загрузке данных: ${error.message}`);
        }
    });

    // Инициализация модального окна при загрузке страницы
    window.brigadeModal = initBrigadeModal();

    //******* Функция отображения сведений о бригаде в модальном окне *******//

    function renderBrigadeDetails(data) {
        const { brigade, leader, members } = data;
        const detailsContainer = document.getElementById(FILTER_IDS.BRIGADE_DETAILS);

        let html = `
            <div class="brigade-details">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">${brigade.name || 'Без названия'}</h4>
                </div>

                ${leader ? `
                <div class="card mb-4">
                    <div class="card-header bg-primary">
                        <h5 class="mb-0 text-white"><i class="bi bi-person-badge me-2"></i>Бригадир</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h5 class="card-title mb-1">${leader.fio || 'Не указано'}</h5>
                                ${leader.position_name ? `<p class="text-muted mb-1">${leader.position_name}</p>` : ''}
                                ${leader.phone ? `<p class="mb-0"><i class="bi bi-telephone me-2"></i>${leader.phone}</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>` : ''}

                <div class="card">
                    <div class="card-header bg-light-2">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Члены бригады
                            <span class="badge bg-primary ms-2">${members.length}</span>
                        </h5>
                    </div>
                    <div class="list-group list-group-flush">
                        ${members.length > 0 ?
                members.map(member => {
                    const isLeader = member.is_leader ? '<span class="badge bg-success ms-2">Бригадир</span>' : '';
                    return `
                                <div class="list-group-item ${member.is_leader ? 'bg-light-2' : ''}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0">${member.fio || 'Без имени'}</h6>
                                                ${isLeader}
                                            </div>
                                            ${member.position_name ? `<small class="text-muted d-block">${member.position_name}</small>` : ''}
                                            ${member.phone ? `<small class="text-muted"><i class="bi bi-telephone me-1"></i>${member.phone}</small>` : ''}
                                        </div>
                                    </div>
                                </div>`;
                }).join('') : `
                                <div class="list-group-item text-muted text-center py-4">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-muted"></i>
                                    В бригаде пока нет участников
                                </div>`
            }
                    </div>
                </div>
            </div>`;

        detailsContainer.innerHTML = html;
    }

    //******* Работа с фильтрами на странице *******//

    // Получаем элемент чекбокса с ID "filter-statuses" и контейнер для кнопок статусов
    const statusCheckbox = document.getElementById('filter-statuses');
    const statusButtonsContainer = document.getElementById('status-buttons');

    // Проверяем, существуют ли элементы на странице
    if (statusCheckbox && statusButtonsContainer) {
        // console.log('Элементы найдены:', {statusCheckbox, statusButtonsContainer});

        // Добавляем класс для скрытия кнопок статусов при загрузке страницы
        statusButtonsContainer.classList.add('d-none');
        // Добавляем инлайновые стили для гарантированного скрытия
        statusButtonsContainer.style.display = 'none !important';
        // console.log('Кнопки статусов скрыты при загрузке');

        // Назначаем обработчик события изменения состояния чекбокса
        statusCheckbox.addEventListener('change', function () {
            // console.log('Состояние чекбокса изменилось:', this.checked);

            // Показываем или скрываем кнопки статусов
            if (this.checked) {
                statusButtonsContainer.classList.remove('d-none');
                statusButtonsContainer.classList.add('d-flex');
                statusButtonsContainer.style.display = 'flex !important';
            } else {
                statusButtonsContainer.classList.remove('d-flex');
                statusButtonsContainer.classList.add('d-none');
                statusButtonsContainer.style.display = 'none !important';
            }

            console.log('Классы контейнера:', statusButtonsContainer.className);

            // Если нужно загружать заявки при включении чекбокса, раскомментируйте код ниже
            if (false) { // Замените на this.checked, когда нужно включить загрузку заявок
                // Отправляем GET-запрос на сервер для получения заявок по определённым статусам
                fetch('/api/requests/by-status?statuses[]=1&statuses[]=2', {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    // Обрабатываем JSON-ответ от сервера
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Успешно получены заявки — выводим в консоль
                            console.log('Заявки по статусам:', data.requests);

                            // TODO: здесь можно вызвать функцию renderFilteredRequests(data.requests)
                            // чтобы отрисовать таблицу заявок на странице
                        } else {
                            // Если сервер вернул ошибку — показываем сообщение
                            if (typeof utils !== 'undefined' && typeof utils.alert === 'function') {
                                utils.alert(data.message || 'Ошибка');
                            } else {
                                showAlert(data.message || 'Ошибка', 'danger');
                            }
                        }
                    })
                    // Обработка сетевых или других ошибок
                    .catch(err => {
                        console.error('Ошибка:', err);
                        if (typeof utils !== 'undefined' && typeof utils.alert === 'function') {
                            utils.alert('Ошибка при фильтрации заявок');
                        } else {
                            showAlert('Ошибка при фильтрации заявок', 'danger');
                        }
                    });
            } else {
                // Если чекбокс снят — можно добавить сброс фильтров без перезагрузки страницы
                // Например, сбросить выбранные статусы и обновить таблицу
                // resetStatusFilters();
            }
        });
    }

    // Фильтр по бригадам (показываем список бригадиров)
    const teamCheckbox = document.getElementById('filter-teams');
    const brigadeLeaderFilter = document.getElementById('brigade-leader-filter');
    const brigadeLeaderSelect = document.getElementById('brigade-leader-select');
    let brigadeLeaders = [];

    if (teamCheckbox && brigadeLeaderFilter && brigadeLeaderSelect) {
        // Обработчик изменения состояния флажка
        teamCheckbox.addEventListener('change', async function () {
            if (this.checked) {
                try {
                    console.log('Запрос списка бригад...');
                    const response = await fetch('/api/brigade-leaders', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`Ошибка HTTP! Статус: ${response.status}`);
                    }

                    const data = await response.json();

                    console.log('Ответ сервера:', data.$leaders);

                    if (data.success) {
                        if (data.$leaders && data.$leaders.length > 0) {
                            brigadeLeaders = data.$leaders;

                            // Очищаем и заполняем выпадающий список
                            brigadeLeaderSelect.innerHTML = '<option value="" selected disabled>Выберите бригаду...</option>';

                            // Добавляем бригадиров в выпадающий список
                            brigadeLeaders.forEach(leader => {
                                const option = document.createElement('option');
                                option.value = leader.id;
                                option.textContent = '[Номер бригады:' + leader.brigade_id + '] ' + leader.name;
                                option.setAttribute('data-brigade-id', leader.brigade_id),
                                    brigadeLeaderSelect.appendChild(option);
                            });

                            // Показываем контейнер с выбором бригадира
                            brigadeLeaderFilter.classList.remove('d-none');
                        } else {
                            // Если успешный ответ, но список пуст, показываем сообщение
                            console.log(data.message || 'Список бригадиров пуст');
                            showAlert(data.message || 'На сегодня не создано ни одной бригады!', 'info');
                            brigadeLeaderFilter.classList.add('d-none');
                            teamCheckbox.checked = false;
                        }
                    } else {
                        // Обработка ошибки
                        console.error('Ошибка при загрузке списка бригадиров:', data.message || 'Неизвестная ошибка');
                        brigadeLeaderFilter.classList.add('d-none');
                        teamCheckbox.checked = false;
                        showAlert(data.message || 'Не удалось загрузить список бригадиров', 'warning');
                    }
                } catch (error) {
                    console.error('Ошибка при запросе списка бригадиров:', error);
                    // Скрываем контейнер при ошибке
                    brigadeLeaderFilter.classList.add('d-none');
                    // Снимаем флажок при ошибке
                    teamCheckbox.checked = false;

                    // Показываем сообщение об ошибке
                    showAlert('Произошла ошибка при загрузке списка бригадиров', 'danger');
                }
            } else {
                console.log('Фильтр по бригадам отключен');
                // Скрываем контейнер с выбором бригадира
                brigadeLeaderFilter.classList.add('d-none');
                // Очищаем выбранное значение
                brigadeLeaderSelect.value = '';
                // Очищаем список бригадиров
                brigadeLeaders = [];

                // Здесь можно добавить сброс фильтрации по бригадиру
                // Например, показать все заявки
                // applyFilters();
            }
        });

        // Обработчик выбора бригадира
        brigadeLeaderSelect.addEventListener('change', function () {
            const selectedLeaderId = this.value;
            if (selectedLeaderId) {
                console.log('Выбран бригадир с ID:', selectedLeaderId);
                // Здесь можно добавить логику фильтрации заявок по выбранному бригадиру
                // Например, отправить запрос на сервер или отфильтровать существующие данные
                // applyFilters();
            }
        });
    }
}

// Обработчик загрузки страницы
// Обработчик кнопки 'Назначить бригаду'
function handleAssignTeam(button) {
    const requestId = button.dataset.requestId;
    console.log('Назначение бригады для заявки:', requestId);
    // Здесь будет логика открытия модального окна для выбора бригады
    showAlert(`Функционал 'Назначить бригаду' для заявки ${requestId} будет реализован позже`, 'info');
}

// Обработчик кнопки 'Перенести заявку'
function handleTransferRequest(button) {
    const requestId = button.dataset.requestId;
    console.log('Перенос заявки:', requestId);
    
    // Создаем модальное окно для выбора даты переноса
    const modalHtml = `
        <div class="modal fade" id="transferRequestModal" tabindex="-1" aria-labelledby="transferRequestModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="transferRequestModalLabel">Перенос заявки #${requestId}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="transferDate" class="form-label">Выберите новую дату для заявки:</label>
                            <input type="date" class="form-control" id="transferDate" required>
                        </div>
                        <div class="mb-3">
                            <label for="transferReason" class="form-label">Причина переноса:</label>
                            <textarea class="form-control" id="transferReason" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="confirmTransfer">Перенести</button>
                    </div>
                </div>
            </div>
        </div>`;

    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Инициализируем модальное окно
    const modalElement = document.getElementById('transferRequestModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Устанавливаем минимальную дату - завтрашний день
    const dateInput = modalElement.querySelector('#transferDate');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split('T')[0];
    
    // Обработчик подтверждения переноса
    modalElement.querySelector('#confirmTransfer').addEventListener('click', async () => {
        const selectedDate = dateInput.value;
        const reason = document.getElementById('transferReason').value.trim();
        
        if (!selectedDate) {
            showAlert('Пожалуйста, выберите дату для переноса', 'info');
            return;
        }
        
        if (!reason) {
            showAlert('Пожалуйста, укажите причину переноса', 'info');
            return;
        }
        
        try {
            const response = await fetch('/api/requests/transfer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    request_id: requestId,
                    new_date: selectedDate,
                    reason: reason
                })
            });
            
            const result = await response.json();

            console.log(result);
            
            // Проверяем дату выполнения из ответа сервера
            if (result.execution_date) {
                const serverDate = new Date(result.execution_date);
                const currentDate = new Date();
                
                // Сбрасываем время для корректного сравнения только дат
                serverDate.setHours(0, 0, 0, 0);
                currentDate.setHours(0, 0, 0, 0);
                
                console.log('Дата выполнения от сервера:', serverDate);
                console.log('Текущая дата:', currentDate);
                
                if (serverDate < currentDate) {
                    console.log('Внимание: Дата выполнения заявки уже прошла!');
                } else if (serverDate.getTime() === currentDate.getTime()) {
                    console.log('Заявка запланирована на сегодня');
                } else {
                    // скрыть заявку, если она запланирована на будущее
                    console.log('Заявка запланирована на будущее');
                    const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                    if (row) {
                        row.style.display = 'none';
                    }
                }
            }
            
            if (response.ok) {
                showAlert('Заявка успешно перенесена', 'success');
                // Обновляем цвет строки без перезагрузки
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                if (row) {
                    row.style.setProperty('--status-color', '#BBDEFB');
                    // Обновляем текст статуса, если он отображается
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) {
                        statusCell.textContent = 'перенесена';
                    }

                    // Обновляем блок комментариев
                    const commentsContainer = row.querySelector('.comment-preview').parentElement;
                    if (commentsContainer) {
                        const existingButton = commentsContainer.querySelector('.view-comments-btn');
                        const commentsCount = result.comments_count || 1; // Используем переданное количество или 1 по умолчанию
                        
                        if (!existingButton) {
                            // Создаем новую кнопку, если её нет
                            const buttonHtml = `
                                <div class="mt-1">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#commentsModal" 
                                            data-request-id="${requestId}" 
                                            style="position: relative; z-index: 1;">
                                        <i class="bi bi-chat-left-text me-1"></i>Все комментарии
                                        <span class="badge bg-primary rounded-pill ms-1">${commentsCount}</span>
                                    </button>
                                </div>`;
                            commentsContainer.insertAdjacentHTML('beforeend', buttonHtml);
                            
                            // Инициализируем tooltip для новой кнопки
                            const tooltipTriggerList = [].slice.call(commentsContainer.querySelectorAll('[data-bs-toggle="tooltip"]'));
                            tooltipTriggerList.map(function (tooltipTriggerEl) {
                                return new bootstrap.Tooltip(tooltipTriggerEl);
                            });
                        }
                    }
                }
                // Закрываем модальное окно
                modal.hide();
                // Удаляем модальное окно из DOM
                modalElement.remove();
            } else {
                throw new Error(result.message || 'Ошибка при переносе заявки');
            }
        } catch (error) {
            console.error('Ошибка при переносе заявки:', error);
            showAlert(error.message || 'Произошла ошибка при переносе заявки', 'error');
        }
    });
    
    // Обработчик закрытия модального окна
    modalElement.addEventListener('hidden.bs.modal', () => {
        modalElement.remove();
    });
    
    // Показываем модальное окно
    modal.show();
}

// Обработчик кнопки 'Отменить заявку'
function handleCancelRequest(button) {
    const requestId = button.dataset.requestId;
    console.log('Отмена заявки:', requestId);
    
    // Создаем модальное окно для подтверждения отмены
    const modalHtml = `
        <div class="modal fade" id="cancelRequestModal" tabindex="-1" aria-labelledby="cancelRequestModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cancelRequestModalLabel">Подтверждение отмены</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Вы уверены, что хотите отменить заявку #${requestId}?</p>
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Причина отмены:</label>
                            <textarea class="form-control" id="cancelReason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="button" class="btn btn-danger" id="confirmCancel">Подтвердить отмену</button>
                    </div>
                </div>
            </div>
        </div>`;

    // Добавляем модальное окно в DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Инициализируем модальное окно
    const modalElement = document.getElementById('cancelRequestModal');
    const modal = new bootstrap.Modal(modalElement);
    
    // Обработчик подтверждения отмены
    modalElement.querySelector('#confirmCancel').addEventListener('click', async () => {
        const reason = document.getElementById('cancelReason').value.trim();
        
        if (!reason) {
            showAlert('Пожалуйста, укажите причину отмены', 'info');
            return;
        }
        
        try {
            console.log('Отправка запроса на отмену заявки:', { requestId, reason });
            
            // Отправка запроса на отмену заявки
            const response = await fetch('/requests/cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    reason: reason
                })
            });
            
            const result = await response.json();
            
            if (response.ok) {
                showAlert('Заявка успешно отменена', 'success');
                console.log('Заявка отменена:', result);
                
                // Обновляем интерфейс
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                if (row) {
                    // Обновляем статус
                    row.style.setProperty('--status-color', '#ffebee');
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) {
                        statusCell.textContent = 'отменена';
                    }
                    
                    // Обновляем кнопки
                    const buttonsContainer = row.querySelector('.btn-group');
                    if (buttonsContainer) {
                        buttonsContainer.innerHTML = `
                            <button type="button" class="btn btn-sm btn-outline-primary comment-btn" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#commentsModal" 
                                    data-request-id="${requestId}">
                                <i class="bi bi-chat-left-text me-1"></i>Комментарий
                            </button>
                        `;
                    }
                }
                
                // Обновляем счетчик заявок, если он есть
                updateRequestsCount();
                
            } else {
                throw new Error(result.message || 'Ошибка при отмене заявки');
            }
            
        } catch (error) {
            console.error('Ошибка при отмене заявки:', error);
            showAlert(error.message || 'Произошла ошибка при отмене заявки', 'error');
        } finally {
            // Закрываем модальное окно
            modal.hide();
            // Удаляем модальное окно из DOM
            modalElement.remove();
        }
    });
    
    // Обработчик закрытия модального окна
    modalElement.addEventListener('hidden.bs.modal', () => {
        modalElement.remove();
    });
    
    // Показываем модальное окно
    modal.show();
}

// Инициализация обработчиков для кнопок заявок
function initRequestButtons() {
    // Используем делегирование событий для всех кнопок
    document.addEventListener('click', function(e) {
        // Обработка кнопки 'Назначить бригаду'
        if (e.target.closest('.assign-team-btn')) {
            e.preventDefault();
            handleAssignTeam(e.target.closest('.assign-team-btn'));
        }
        // Обработка кнопки 'Перенести заявку'
        else if (e.target.closest('.transfer-request-btn')) {
            e.preventDefault();
            handleTransferRequest(e.target.closest('.transfer-request-btn'));
        }
        // Обработка кнопки 'Отменить заявку'
        else if (e.target.closest('.cancel-request-btn')) {
            e.preventDefault();
            handleCancelRequest(e.target.closest('.cancel-request-btn'));
        }
    });
}

console.log('DOM загружен, инициализация обработчиков...');
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM полностью загружен');
    initializePage();

    // Инициализация кнопок заявок
    initRequestButtons();

    // Добавляем обработчик для вкладки заявок
    const requestsTab = document.getElementById('requests-tab');
    if (requestsTab) {
        requestsTab.addEventListener('click', async function () {
            console.log('Вкладка "Заявки" была нажата');

            // Обновить данные об адресах
            loadAddresses();

            try {
                // Запрашиваем актуальный список бригад с сервера
                const response = await fetch('/api/brigades/current-day');
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Ошибка при загрузке списка бригад');
                }

                // Обновляем выпадающий список
                const select = document.getElementById('brigade-leader-select');
                if (select) {
                    // Сохраняем текущее выбранное значение
                    const selectedValue = select.value;

                    // Очищаем список, оставляя только первый элемент
                    select.innerHTML = '<option value="" selected disabled>Выберите бригаду...</option>';

                    // Добавляем новые опции
                    result.data.forEach(brigade => {
                        const option = document.createElement('option');
                        option.value = brigade.leader_id;
                        option.textContent = brigade.leader_name;
                        option.setAttribute('data-brigade-id', brigade.brigade_id);
                        select.appendChild(option);
                    });

                    // Восстанавливаем выбранное значение, если оно есть в новом списке
                    if (selectedValue && Array.from(select.options).some(opt => opt.value === selectedValue)) {
                        select.value = selectedValue;
                    }

                    console.log('Список бригад обновлен');
                }
            } catch (error) {
                console.error('Ошибка при обновлении списка бригад:', error);
                showAlert('Не удалось обновить список бригад: ' + error.message);
            }
        });
    }

    // Перехватываем вызов applyFilters для обновления обработчиков после фильтрации
    const originalApplyFilters = window.applyFilters;
    window.applyFilters = function () {
        return originalApplyFilters.apply(this, arguments).then(() => {
            initRequestButtons();
        });
    };


    setupBrigadeAttachment();
    console.log('Вызов handlerCreateBrigade...');
    handlerCreateBrigade();
    console.log('handlerCreateBrigade вызван');
    hanlerAddToBrigade();
    handlerAddEmployee();
    initUserSelection();
});

function autoFillEmployeeForm() {
    document.getElementById('autoFillBtn').addEventListener('click', function(e) {
        e.preventDefault();

        // 30 вариантов тестовых данных
        const mockDataArray = [
            {
                fio: "Лавров Иван Федорович", phone: "+7 (912) 345-67-89",
                birth_date: "1990-05-15", birth_place: "г. Москва",
                passport_series: "4510 123456", passport_issued_by: "ОУФМС России по г. Москве",
                passport_issued_at: "2015-06-20", passport_department_code: "770-123",
                car_brand: "Toyota Camry", car_plate: "А123БВ777"
            },
            {
                fio: "Петров Алексей Романович", phone: "+7 (923) 456-78-90",
                birth_date: "1985-08-22", birth_place: "г. Санкт-Петербург",
                passport_series: "4012 654321", passport_issued_by: "ГУ МВД по СПб и ЛО",
                passport_issued_at: "2018-03-15", passport_department_code: "780-456",
                car_brand: "Hyundai Solaris", car_plate: "В987СН178"
            },
            {
                fio: "Сидоров Михаил Александрович", phone: "+7 (934) 567-89-01",
                birth_date: "1995-02-10", birth_place: "г. Екатеринбург",
                passport_series: "4603 789012", passport_issued_by: "УМВД по Свердловской области",
                passport_issued_at: "2017-11-30", passport_department_code: "660-789",
                car_brand: "Kia Rio", car_plate: "Е456КХ123"
            },
            // Продолжение с другими вариантами...
            {
                fio: "Кузнецов Дмитрий Сергеевич", phone: "+7 (945) 678-90-12",
                birth_date: "1988-07-14", birth_place: "г. Новосибирск",
                passport_series: "5401 345678", passport_issued_by: "ГУ МВД по Новосибирской области",
                passport_issued_at: "2019-04-25", passport_department_code: "540-234",
                car_brand: "Volkswagen Polo", car_plate: "Н543ТУ777"
            },
            {
                fio: "Смирнов Олег Владимирович", phone: "+7 (956) 789-01-23",
                birth_date: "1992-12-05", birth_place: "г. Казань",
                passport_series: "9204 567890", passport_issued_by: "МВД по Республике Татарстан",
                passport_issued_at: "2016-09-18", passport_department_code: "160-567",
                car_brand: "Lada Vesta", car_plate: "У321ХС123"
            },
            // Еще 25 вариантов...
            {
                fio: "Васильев Артем Игоревич", phone: "+7 (967) 890-12-34",
                birth_date: "1993-04-30", birth_place: "г. Нижний Новгород",
                passport_series: "5205 901234", passport_issued_by: "ГУ МВД по Нижегородской области",
                passport_issued_at: "2020-01-12", passport_department_code: "520-890",
                car_brand: "Skoda Rapid", car_plate: "В654АС321"
            }
        ];

        // Выбираем случайный вариант из массива
        const randomIndex = Math.floor(Math.random() * mockDataArray.length);
        const mockData = mockDataArray[randomIndex];

        // Заполняем поля формы
        Object.keys(mockData).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) input.value = mockData[key];
        });

        // Показываем уведомление с номером варианта
        const toastBody = document.getElementById('autoFillToastBody');
        toastBody.textContent = `Форма заполнена тестовыми данными (вариант ${randomIndex + 1} из ${mockDataArray.length}). Проверьте информацию.`;

        const toast = new bootstrap.Toast(document.getElementById('autoFillToast'));
        toast.show();
    });
}

autoFillEmployeeForm();

function initUserSelection() {
    const selectUserBtns = document.querySelectorAll('.select-user');
    const userIdInput = document.getElementById('userIdInput');

    selectUserBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            userIdInput.value = userId;

            // Показываем уведомление о выборе пользователя
            const toast = new bootstrap.Toast(document.getElementById('userSelectedToast'));
            toast.show();

            // Прокручиваем к форме
            document.getElementById('employeesFormContainer').scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Инициализация тултипов
    if (window.bootstrap) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

function handlerAddEmployee() {
    console.log('Инициализация обработчика формы сотрудника');
    const form = document.querySelector('form#employeeForm');

    if (!form) {
        console.error('Форма сотрудника не найдена');
        return;
    }

    // Инициализация обработчика отправки формы
    initEmployeeForm(form);

    /**
     * Инициализирует обработчик отправки формы сотрудника
     * @param {HTMLFormElement} form - Элемент формы
     */
    function initEmployeeForm(form) {
        form.addEventListener('submit', handleEmployeeFormSubmit);
    }

    /**
     * Обрабатывает отправку формы сотрудника
     * @param {Event} e - Событие отправки формы
     */
    async function handleEmployeeFormSubmit(e) {
        e.preventDefault();

        if (!validateForm(this)) {
            return;
        }

        const formData = new FormData(this);
        const submitBtn = document.getElementById('saveBtn');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : 'Сохранить';

        try {
            // Показываем индикатор загрузки
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

            // Отладочная информация
            console.log('=== ДАННЫЕ ФОРМЫ ===');
            console.log('URL запроса:', this.action);
            console.log('Метод: POST');
            console.log('Заголовки:', {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            });

            // Выводим все поля формы
            const formDataObj = {};
            for (let [key, value] of formData.entries()) {
                formDataObj[key] = value;
            }
            console.log('Данные формы:', formDataObj);

            const response = await fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(Object.fromEntries(formData))
            });

            const data = await response.json().catch(() => ({}));
            console.log('Ответ сервера:', data);

            if (!response.ok) {
                handleFormErrors(this, response, data);
            } else {
                handleFormSuccess(this);
            }
        } catch (error) {
            console.error('Ошибка при сохранении сотрудника:', error);
            showAlert(error.message || 'Произошла ошибка при сохранении', 'danger');
        } finally {
            // Восстанавливаем состояние кнопки
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }

    /**
     * Валидирует форму
     * @param {HTMLFormElement} form - Элемент формы
     * @returns {boolean} - Возвращает true, если форма валидна
     */
    function validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            showAlert('Пожалуйста, заполните все обязательные поля', 'warning');
        }

        return isValid;
    }

    /**
     * Обрабатывает ошибки формы
     * @param {HTMLFormElement} form - Элемент формы
     * @param {Response} response - Ответ сервера
     * @param {Object} data - Данные ответа
     */
    function handleFormErrors(form, response, data) {
        // Обработка ошибок валидации
        if (response.status === 422 && data.errors) {
            // Очищаем предыдущие ошибки
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Показываем ошибки валидации
            Object.entries(data.errors).forEach(([field, messages]) => {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = input.nextElementSibling || document.createElement('div');
                    if (!feedback.classList.contains('invalid-feedback')) {
                        feedback.className = 'invalid-feedback';
                        input.parentNode.insertBefore(feedback, input.nextSibling);
                    }
                    feedback.textContent = messages[0];
                }
            });
            showAlert('Пожалуйста, исправьте ошибки в форме', 'danger');
        } else {
            throw new Error(data.message || 'Произошла ошибка при сохранении');
        }
    }

    /**
     * Обрабатывает успешное сохранение формы
     * @param {HTMLFormElement} form - Элемент формы
     */
    function handleFormSuccess(form) {
        // Успешное сохранение
        showAlert('Данные сотрудника успешно сохранены', 'success');

        // Обновляем таблицу сотрудников
        if (window.loadEmployees) {
            loadEmployees();
        }

        // Сбрасываем форму
        form.reset();
    }

    // Обработчик для кнопки "Изменить"
    const editBtn = document.getElementById('editBtn');
    if (editBtn) {
        editBtn.addEventListener('click', function () {
            // Разблокируем поля для редактирования
            document.querySelectorAll('form input, form select').forEach(input => {
                input.readOnly = false;
            });

            // Показываем кнопку "Сохранить" и скрываем "Изменить"
            this.classList.add('d-none');
            document.getElementById('saveBtn').classList.remove('d-none');
        });
    }

    // Add event listener for the save button
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            console.log('Save button clicked');
            const form = document.querySelector('form#employeeForm');
            if (form) {
                const submitEvent = new Event('submit', {
                    bubbles: true,
                    cancelable: true
                });
                form.dispatchEvent(submitEvent);
            }
        });
    }
}

function hanlerAddToBrigade() {
    document.getElementById('addToBrigadeBtn').addEventListener('click', function () {
        const select = document.getElementById('employeesSelect');
        const brigadeMembers = document.getElementById('brigadeMembers');
        const selectedOptions = Array.from(select.selectedOptions);

        if (selectedOptions.length > 0) {
            selectedOptions.forEach(option => {
                // Проверяем, не является ли добавляемый сотрудник уже бригадиром
                const leaderSelect = document.querySelector('select[name="leader_id"]');
                if (leaderSelect && leaderSelect.value === option.value) {
                    showAlert('Этот сотрудник уже является бригадиром', 'warning');
                    option.selected = false;
                    return; // Пропускаем добавление, если это бригадир
                }
                // Создаем элемент для отображения сотрудника
                const memberDiv = document.createElement('div');
                memberDiv.className = 'd-flex justify-content-between align-items-center p-2 mb-2 border rounded';

                // Добавляем имя сотрудника
                const nameSpan = document.createElement('span');
                nameSpan.textContent = option.text;

                // Создаем кнопку удаления
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-sm btn-outline-danger';
                deleteBtn.innerHTML = '&times;';
                deleteBtn.onclick = function () {
                    memberDiv.remove();
                    // Разблокируем опцию в селекте
                    option.selected = false;
                };

                // Скрытое поле для формы
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'brigade_members[]';
                hiddenInput.value = option.value;
                hiddenInput.dataset.employeeId = option.dataset.employeeId; // Добавляем data-employee-id

                // Собираем всё вместе
                memberDiv.appendChild(hiddenInput);
                memberDiv.appendChild(nameSpan);
                memberDiv.appendChild(deleteBtn);

                // Добавляем в список бригады
                brigadeMembers.appendChild(memberDiv);

                // Делаем опцию в селекте неактивной
                option.disabled = true;
                option.selected = false;
            });

            // Обновляем скрытое поле с данными о составе бригады
            updateBrigadeMembersFormField();
        } else {
            console.log('Выберите хотя бы одного сотрудника');
        }
    });
}

function handlerCreateBrigade() {
    console.log('Инициализация handlerCreateBrigade...');
    const createBtn = document.getElementById('createBrigadeBtn');

    console.log('Найдена кнопка createBrigadeBtn:', !!createBtn);

    if (createBtn) {
        createBtn.addEventListener('click', async function (e) {
            console.log('Кнопка нажата!');
            e.preventDefault();
            console.clear();
            console.log('Начало обработки клика...');

            // Получаем данные формы
            const form = document.getElementById('brigadeForm');
            if (!form) {
                console.error('Форма не найдена');
                return;
            }

            // Обновляем данные бригады перед отправкой
            const members = getAllBrigadeMembers();
            const leaderId = document.querySelector('select[name="leader_id"]')?.value;

            // Проверяем валидность
            if (!leaderId) {
                showAlert('Пожалуйста, выберите бригадира', 'warning');
                return;
            }

            if (members.length < 2) {
                showAlert('В бригаде должно быть минимум 2 участника', 'warning');
                return;
            }

            // Обновляем скрытое поле с данными
            const hiddenField = document.getElementById('brigade_members_data') ||
                (() => {
                    const el = document.createElement('input');
                    el.type = 'hidden';
                    el.id = 'brigade_members_data';
                    el.name = 'brigade_members_data';
                    form.appendChild(el);
                    return el;
                })();

            hiddenField.value = JSON.stringify(members);

            // Создаем FormData и добавляем все необходимые поля
            const formData = new FormData(form);

            // Очищаем старые данные о членах бригады
            document.querySelectorAll('input[name^="brigade_members["]').forEach(input => {
                input.remove();
            });

            // Добавляем всех участников в форму
            members.forEach((member, index) => {
                if (!member.is_leader) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `brigade_members[${index}]`;
                    input.value = member.employee_id;
                    form.appendChild(input);
                }
            });

            // Обновляем FormData
            formData.delete('brigade_members[]');
            members.forEach((member, index) => {
                if (!member.is_leader) {
                    formData.append('brigade_members[]', member.employee_id);
                }
            });

            // Логируем данные перед отправкой
            console.log('Отправка данных бригады:', {
                formData: Object.fromEntries(formData.entries()),
                members,
                leaderId
            });

            // Собираем все данные формы в объект
            const formValues = {};
            for (let [key, value] of formData.entries()) {
                if (formValues[key] !== undefined) {
                    // Если поле уже существует, преобразуем его в массив
                    if (!Array.isArray(formValues[key])) {
                        formValues[key] = [formValues[key]];
                    }
                    formValues[key].push(value);
                } else {
                    formValues[key] = value;
                }
            }

            // Получаем данные о членах бригады
            const brigadeMembersData = JSON.parse(formValues.brigade_members_data || '[]');

            // Формируем данные для логирования
            const formJson = {
                formData: formValues,
                members: brigadeMembersData,
                metadata: {
                    totalMembers: brigadeMembersData.length,
                    hasLeader: !!leaderId,
                    timestamp: new Date().toISOString()
                }
            };

            // Выводим JSON в консоль
            console.log('=== ДАННЫЕ ФОРМЫ В ФОРМАТЕ JSON ===');
            console.log(JSON.stringify(formJson, null, 2));

            // Проверяем обязательные поля
            if (!formValues.leader_id) {
                showAlert('Пожалуйста, выберите бригадира', 'warning');
                return;
            }

            // Проверяем, что в бригаде минимум 2 участника (включая бригадира)
            if (formJson.members.length < 2) {
                showAlert('В бригаде должно быть минимум 2 участника (включая бригадира)', 'warning');
                return;
            }

            // Проверяем, что бригадир есть в списке участников
            const leaderInMembers = formJson.members.some(member =>
                member.employee_id === formValues.leader_id
            );

            // Если бригадира нет в списке участников, добавляем его
            if (!leaderInMembers && formValues.leader_id) {
                formJson.members.push({
                    employee_id: formValues.leader_id,
                    is_leader: true
                });
            }

            showAlert('Данные формы успешно обработаны!', 'success');

            // Функция для загрузки списка бригад
            window.loadBrigadesList = async () => {
                try {
                    const brigadesList = document.getElementById('brigadesList');
                    if (!brigadesList) return;

                    // Показываем индикатор загрузки
                    brigadesList.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <p class="mt-2 mb-0">Загрузка списка бригад...</p>
                        </div>`;

                    const response = await fetch('/api/brigades');
                    if (!response.ok) {
                        throw new Error('Ошибка при загрузке списка бригад');
                    }

                    const brigades = await response.json();

                    if (brigades.length === 0) {
                        brigadesList.innerHTML = `
                            <div class="text-center py-4">
                                <p class="text-muted">Список бригад пуст</p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBrigadeModal">
                                    <i class="bi bi-plus-circle"></i> Создать бригаду
                                </button>
                            </div>`;
                        return;
                    }

                    // Формируем HTML для списка бригад
                    let html = `
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Список бригад</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBrigadeModal">
                                <i class="bi bi-plus-circle"></i> Новая бригада
                            </button>
                        </div>`;

                    brigades.forEach(brigade => {
                        html += `
                            <div class="list-group-item list-group-item-action" data-brigade-id="${brigade.id}">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${brigade.name}</h6>
                                    <small>ID: ${brigade.id}</small>
                                </div>
                                <p class="mb-1">Бригадир: ${brigade.leader_name || 'Не назначен'}</p>
                                <small>Участников: ${brigade.members_count || 0}</small>
                            </div>`;
                    });

                    brigadesList.innerHTML = html;
                } catch (error) {
                    console.error('Ошибка при загрузке списка бригад:', error);
                    const brigadesList = document.getElementById('brigadesList');
                    if (brigadesList) {
                        brigadesList.innerHTML = `
                            <div class="alert alert-danger">
                                Ошибка при загрузке списка бригад. <button class="btn btn-link p-0" onclick="loadBrigadesList()">Повторить</button>
                            </div>`;
                    }
                }
            };

            // Вспомогательная функция для создания элемента бригады
            function createBrigadeElement(brigade) {
                const div = document.createElement('div');
                div.className = 'list-group-item list-group-item-action';
                div.dataset.brigadeId = brigade.id;
                div.innerHTML = `
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${brigade.name}</h6>
                        <small>ID: ${brigade.id}</small>
                    </div>
                    <p class="mb-1">Бригадир: ${brigade.leader_name || 'Не назначен'}</p>
                    <small>Участников: ${brigade.members_count || 0}</small>
                `;
                return div;
            }

            // Функция для обновления списка после создания новой бригады
            window.updateBrigadesList = (newBrigade) => {
                try {
                    const brigadesList = document.getElementById('brigadesList');
                    if (!brigadesList) {
                        console.log('Элемент с id="brigadesList" не найден');
                        return;
                    }

                    // Создаем новый элемент списка
                    const newItem = createBrigadeElement(newBrigade);

                    // Получаем все существующие элементы списка
                    const existingItems = brigadesList.querySelectorAll('.list-group-item');

                    // Если есть существующие элементы, вставляем новый перед первым
                    if (existingItems.length > 0) {
                        existingItems[0].before(newItem);
                    } else {
                        // Иначе создаем новый список
                        const listGroup = document.createElement('div');
                        listGroup.className = 'list-group';
                        listGroup.appendChild(newItem);

                        // Добавляем заголовок, если его нет
                        if (!brigadesList.querySelector('h5')) {
                            const header = document.createElement('div');
                            header.className = 'd-flex justify-content-between align-items-center mb-3';
                            header.innerHTML = `
                    <h5 class="mb-0">Список бригад</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBrigadeModal">
                        <i class="bi bi-plus-circle"></i> Новая бригада
                    </button>
                `;
                            brigadesList.prepend(header);
                        }

                        // Добавляем список, если его нет
                        if (!brigadesList.querySelector('.list-group')) {
                            brigadesList.appendChild(listGroup);
                        } else {
                            brigadesList.querySelector('.list-group').prepend(newItem);
                        }
                    }

                    // Удаляем сообщения о загрузке и пустом списке
                    const loadingMessages = brigadesList.querySelectorAll('.text-center.py-4, .text-muted');
                    loadingMessages.forEach(msg => msg.remove());

                } catch (error) {
                    console.error('Ошибка при обновлении списка бригад:', error);
                    // В случае ошибки просто перезагружаем список полностью
                    if (typeof loadBrigadesList === 'function') {
                        loadBrigadesList();
                    }
                }
            };

            // Загружаем список бригад при загрузке страницы
            document.addEventListener('DOMContentLoaded', () => {
                // Загружаем список бригад при открытии вкладки
                const teamsTab = document.querySelector('a[data-bs-target="#teams"]');
                if (teamsTab) {
                    teamsTab.addEventListener('shown.bs.tab', () => {
                        loadBrigadesList();
                    });
                }
            });

            // Функция для отправки данных на сервер
            /*
                {_token: 'WhlfBIUMYIRpxc1zBMiLWo6yiq9k4QOqt5lNdo5f', name: 'Бригада технической интеграции видеосистем', leader_id: '34', brigade_members: Array(1), members: Array(1)}
                brigade_members
                :
                Array(1)
                0
                :
                "34"
                length
                :
                1
                [[Prototype]]
                :
                Array(0)
                leader_id
                :
                "34"
                members
                :
                Array(1)
                0: 34

                Array(0)
                name: "Бригада технической интеграции видеосистем"
                _token: "WhlfBIUMYIRpxc1zBMiLWo6yiq9k4QOqt5lNdo5f"
            */
            const createBrigade = async () => {
                try {
                    console.log('Отправка запроса на создание бригады...');

                    // Парсим данные о членах бригады из JSON
                    const membersData = JSON.parse(formJson.formData.brigade_members_data || '[]');

                    // Формируем данные для отправки
                    const requestData = {
                        name: formJson.formData.name,
                        leader_id: formJson.formData.leader_id,
                        members: membersData
                            .filter(member => !member.is_leader) // Исключаем бригадира из списка участников
                            .map(member => member.employee_id)
                    };

                    console.log('Данные для отправки:', requestData);

                    const response = await fetch('/brigades', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': formJson.formData._token,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(requestData)
                    });

                    // Проверяем Content-Type ответа
                    const contentType = response.headers.get('content-type');
                    let data;

                    if (contentType && contentType.includes('application/json')) {
                        data = await response.json();
                    } else {
                        const text = await response.text();
                        console.error('Ожидался JSON, но получен:', text);
                        throw new Error('Сервер вернул неожиданный ответ. Проверьте консоль для деталей.');
                    }

                    if (!response.ok) {
                        throw new Error(data.message || `Ошибка ${response.status}: ${response.statusText}`);
                    }

                    console.log('Ответ сервера:', data);

                    if (data.success) {
                        showAlert('Бригада успешно создана!', 'success');

                        // Закрываем модальное окно, если оно открыто
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createBrigadeModal'));
                        if (modal) {
                            modal.hide();
                        }

                        // Очищаем форму и список участников бригады
                        const form = document.getElementById('brigadeForm');
                        if (form) {
                            form.reset();
                        }

                        // Очищаем список участников бригады
                        const brigadeMembers = document.getElementById('brigadeMembers');
                        if (brigadeMembers) {
                            brigadeMembers.innerHTML = '';
                        }

                        // Обновляем список бригад
                        if (typeof window.updateBrigadesList === 'function') {
                            window.updateBrigadesList(data.brigade);
                        } else {
                            // Если функция обновления не определена, перезагружаем страницу
                            console.warn('Функция updateBrigadesList не найдена, выполняется перезагрузка страницы');
                            setTimeout(() => window.location.reload(), 1000);
                        }

                    } else {
                        throw new Error(data.message || 'Неизвестная ошибка сервера');
                    }

                } catch (error) {
                    console.error('Ошибка при создании бригады:', error);
                    console.error('Полный стек ошибки:', error.stack);
                    showAlert(`Ошибка: ${error.message}`, 'danger');
                }
            };

            // Вызываем функцию создания бригады
            createBrigade();

            // Для отправки формы раскомментируйте строку ниже
            // form.submit();
        });
    } else {
        console.warn('Кнопка createBrigadeBtn не найдена');
    }
}

// Функция для настройки прикрепления бригады к заявке
function setupBrigadeAttachment() {
    // Обработчик изменения состояния чекбоксов заявок
    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type="checkbox"].request-checkbox')) {
            const brigadeSelect = document.getElementById('brigade-leader-select');
            const attachButton = document.getElementById('attach-brigade-button');
            const filterContainer = document.getElementById('brigade-leader-filter');

            if (brigadeSelect && filterContainer) {
                // Добавляем классы для отображения в строку
                filterContainer.classList.add('d-flex', 'align-items-center');

                if (e.target.checked) {
                    // Если кнопка еще не создана - создаем ее
                    if (!attachButton) {
                        const button = document.createElement('button');
                        button.id = 'attach-brigade-button';
                        button.className = 'btn btn-primary btn-sm ms-2';
                        button.style.whiteSpace = 'nowrap'; // Предотвращаем перенос текста
                        button.style.marginTop = '-12px'; // Выравнивание по вертикали с селектом
                        button.textContent = 'Прикрепить бригаду к заявке';
                        button.onclick = async function () {
                            const select = document.getElementById('brigade-leader-select');
                            const checkedCheckbox = document.querySelector('input[type="checkbox"].request-checkbox:checked');

                            if (!select.value) {
                                showAlert('Бригада не выбрана!');
                                return;
                            }

                            if (!checkedCheckbox) {
                                showAlert('Не выбрана ни одна заявка!');
                                return;
                            }

                            const leaderId = select.value;
                            const requestId = checkedCheckbox.value;
                            const brigadeName = select.options[select.selectedIndex].text;
                            const selectedOption = select.options[select.selectedIndex];
                            const brigade_id = selectedOption.getAttribute('data-brigade-id');

                            console.log(`ID выбранной заявки: ${requestId}`);
                            console.log(`ID выбранного бригадира: ${leaderId}`);
                            console.log(`ID бригады: ${brigade_id}`);
                            console.log(`Название бригады: ${brigadeName}`);

                            try {
                                // console.log('1. Получаем данные о бригаде...');
                                /*const brigadeResponse = await fetch(`/api/requests/brigade/by-leader/${leaderId}`);
                                const brigadeData = await brigadeResponse.json();
                                if (!brigadeResponse.ok) {
                                    throw new Error(brigadeData.message || `Ошибка ${brigadeResponse.status} при получении данных о бригаде`);
                                }

                                if (!brigadeData.data || !brigadeData.data.brigade_id) {
                                    throw new Error('Не удалось получить ID бригады из ответа сервера');
                                }*/

                                // console.log('2. Отправляем запрос на обновление заявки...');
                                const updateResponse = await fetch('/api/requests/update-brigade', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        brigade_id: brigade_id,
                                        request_id: requestId
                                    })
                                });

                                const updateData = await updateResponse.json().catch(() => ({}));
                                console.log('Ответ от API обновления заявки:', updateResponse.status, updateData);

                                if (!updateResponse.ok) {
                                    const errorMessage = updateData.message ||
                                        updateData.error ||
                                        (updateData.error_details ? JSON.stringify(updateData.error_details) : 'Неизвестная ошибка');
                                    throw new Error(`Ошибка ${updateResponse.status}: ${errorMessage}`);
                                }

                                // console.log(`Бригадир ${brigadeName} (ID: ${leaderId}) успешно прикреплен к заявке ${requestId}`);

                                // 3. Обновляем страницу для отображения изменений
                                window.location.reload();

                            } catch (error) {
                                console.error('Ошибка при прикреплении бригады:', error.message);
                                if (typeof utils !== 'undefined' && typeof utils.alert === 'function') {
                                    utils.alert(`Ошибка: ${error.message}`);
                                } else {
                                    showAlert(`Ошибка: ${error.message}`, 'danger');
                                }
                            }
                        };

                        // Вставляем кнопку после селекта
                        filterContainer.appendChild(button);
                    } else {
                        // Если кнопка уже существует, просто показываем её
                        attachButton.style.display = 'inline-block';
                    }
                } else {
                    // Проверяем, есть ли другие отмеченные чекбоксы
                    const anyChecked = document.querySelector('input[type="checkbox"].request-checkbox:checked');
                    // Если нет отмеченных чекбоксов - скрываем кнопку
                    if (!anyChecked && attachButton) {
                        attachButton.style.display = 'none';
                    }
                }
            }
        }
    });
    // Обработчик изменения состояния чекбоксов
    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('.request-checkbox')) {
            const checkbox = e.target;
            const requestId = checkbox.value;
            const row = checkbox.closest('tr');

            // Находим select с бригадирами в строке
            const brigadeSelect = row.querySelector('select#brigade-leader-select');

            if (brigadeSelect) {
                // Удаляем существующую кнопку, если есть
                const existingButton = brigadeSelect.nextElementSibling;
                if (existingButton && existingButton.matches('.attach-brigade-button')) {
                    existingButton.remove();
                }

                // Если чекбокс отмечен, добавляем кнопку
                if (checkbox.checked) {
                    const button = document.createElement('button');
                    button.className = 'btn btn-sm btn-primary attach-brigade-button';
                    button.textContent = 'Прикрепить бригаду к заявке';
                    button.style.marginLeft = '10px';

                    // Моковый обработчик
                    button.addEventListener('click', function () {
                        console.log('Кнопка нажата для заявки', requestId);
                        console.log('Выбран бригадир с ID:', brigadeSelect.value);
                    });

                    // Вставляем кнопку после select
                    brigadeSelect.parentNode.insertBefore(button, brigadeSelect.nextSibling);
                }
            }
        }
    });
}
