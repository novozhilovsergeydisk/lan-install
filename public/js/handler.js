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

// Функция для применения фильтров
function applyFilters() {
    // Собираем все выбранные фильтры
    const activeFilters = {
        statuses: [...filterState.statuses],
        teams: [...filterState.teams],
        date: filterState.date
    };

    // Логи фильтров отключены

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
                            const dateStr = request.request_date || request.created_at;
                            // Логи дат отключены

                            if (dateStr) {
                                const date = new Date(dateStr);
                                if (!isNaN(date.getTime())) {
                                    formattedDate = date.toLocaleDateString('ru-RU', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        hour12: false
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

                        row.innerHTML = `
                            <td style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${request.id}</td>
                            <td class="text-center" style="width: 1rem;">
                                <input type="checkbox" id="request-${request.id}" class="form-check-input request-checkbox" value="${request.id}" aria-label="Выбрать заявку">
                            </td>
                            <td>
                                <div>${formattedDate}</div>
                                <div class="text-dark" style="font-size: 0.8rem;">${requestNumber}</div>
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
                                    const displayText = commentText.length > 50
                                        ? commentText.substring(0, 50) + '...'
                                        : commentText;

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
                            <td style="width: 12rem; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;">
                                <small class="text-dark text-truncate d-block" data-bs-toggle="tooltip" title="${request.address || address}">
                                    ${request.address || address}
                                </small>
                                <small class="text-success_ fw-bold_ text-truncate d-block">
                                    ${request.phone || request.client_phone || ''}
                                </small>
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
                                    ${request.status !== 'completed' ? `
                                        <button data-request-id="${request.id}" type="button" class="btn btn-sm btn-custom-brown p-1 close-request-btn" onclick="closeRequest(${request.id}); return false;">
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
        console.log('Элементы найдены:', {statusCheckbox, statusButtonsContainer});

        // Добавляем класс для скрытия кнопок статусов при загрузке страницы
        statusButtonsContainer.classList.add('d-none');
        // Добавляем инлайновые стили для гарантированного скрытия
        statusButtonsContainer.style.display = 'none !important';
        console.log('Кнопки статусов скрыты при загрузке');

        // Назначаем обработчик события изменения состояния чекбокса
        statusCheckbox.addEventListener('change', function () {
            console.log('Состояние чекбокса изменилось:', this.checked);

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
                            alert(data.message || 'Ошибка');
                        }
                    })
                    // Обработка сетевых или других ошибок
                    .catch(err => {
                        console.error('Ошибка:', err);
                        alert('Ошибка при фильтрации заявок');
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
                    console.log('Запрос списка бригадиров...');
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
                    console.log('Ответ сервера:', data);
                    
                    if (data.success && data.leaders && data.leaders.length > 0) {
                        brigadeLeaders = data.leaders;
                        console.log('Получен список бригадиров:', brigadeLeaders);
                        
                        // Очищаем и заполняем выпадающий список
                        brigadeLeaderSelect.innerHTML = '<option value="" selected disabled>Выберите бригадира...</option>';
                        
                        // Добавляем бригадиров в выпадающий список
                        brigadeLeaders.forEach(leader => {
                            const option = document.createElement('option');
                            option.value = leader.id;
                            option.textContent = leader.name;
                            brigadeLeaderSelect.appendChild(option);
                        });
                        
                        // Показываем контейнер с выбором бригадира
                        brigadeLeaderFilter.classList.remove('d-none');
                        
                    } else {
                        console.error('Не удалось загрузить список бригадиров или список пуст');
                        // Скрываем контейнер, если нет данных
                        brigadeLeaderFilter.classList.add('d-none');
                        // Снимаем флажок, так как загрузить данные не удалось
                        teamCheckbox.checked = false;
                        
                        // Показываем сообщение пользователю
                        showAlert('Не удалось загрузить список бригадиров', 'warning');
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
        brigadeLeaderSelect.addEventListener('change', function() {
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
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    setupBrigadeAttachment();
});

// Функция для настройки прикрепления бригады к заявке
function setupBrigadeAttachment() {
    // Обработчик изменения состояния чекбоксов заявок
    document.addEventListener('change', function(e) {
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
                        button.onclick = async function() {
                            const select = document.getElementById('brigade-leader-select');
                            const checkedCheckbox = document.querySelector('input[type="checkbox"].request-checkbox:checked');
                            
                            if (!select.value) {
                                console.log('Бригадир не выбран!');
                                return;
                            }
                            
                            if (!checkedCheckbox) {
                                console.log('Не выбрана ни одна заявка!');
                                return;
                            }
                            
                            const leaderId = select.value;
                            const requestId = checkedCheckbox.value;
                            const brigadeName = select.options[select.selectedIndex].text;
                            
                            console.log(`ID выбранной заявки: ${requestId}`);
                            console.log(`ID выбранного бригадира: ${leaderId}`);
                            
                            try {
                                console.log('1. Получаем данные о бригаде...');
                                const brigadeResponse = await fetch(`/api/requests/brigade/by-leader/${leaderId}`);
                                const brigadeData = await brigadeResponse.json();
                                
                                console.log('Ответ от API бригады:', brigadeData);
                                
                                if (!brigadeResponse.ok) {
                                    throw new Error(brigadeData.message || `Ошибка ${brigadeResponse.status} при получении данных о бригаде`);
                                }
                                
                                if (!brigadeData.data || !brigadeData.data.brigade_id) {
                                    throw new Error('Не удалось получить ID бригады из ответа сервера');
                                }
                                
                                console.log('2. Отправляем запрос на обновление заявки...');
                                const updateResponse = await fetch('/api/requests/update-brigade', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        brigade_id: brigadeData.data.brigade_id,
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
                                
                                console.log(`Бригадир ${brigadeName} (ID: ${leaderId}) успешно прикреплен к заявке ${requestId}`);
                                
                                // 3. Обновляем страницу для отображения изменений
                                window.location.reload();
                                
                            } catch (error) {
                                console.error('Ошибка при прикреплении бригады:', error.message);
                                alert(`Ошибка: ${error.message}`);
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
    document.addEventListener('change', function(e) {
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
                    button.addEventListener('click', function() {
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
