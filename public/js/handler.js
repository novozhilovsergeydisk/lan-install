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

    // Здесь можно добавить логику применения фильтров к таблице или списку
    console.log('Применены фильтры:', activeFilters);

    // Пример: перезагрузка данных с учетом фильтров
    // loadData(activeFilters);
}

// Функция для логирования кликов
function logButtonClick(buttonId, buttonText) {
    console.log(`Клик по кнопке: ${buttonText} (ID: ${buttonId})`);
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

// Обработчики для кнопок
document.addEventListener('DOMContentLoaded', function () {
    // Кнопка выхода
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

            console.log('Все фильтры сброшены', filterState);
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

                console.log(`Фильтр "${labelText}" ${this.checked ? 'включен' : 'отключен'}`, filterState);
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
                console.log(`Выбрана дата: ${selectedDate}`);
                applyFilters();
            });
        } else {
            // Если плагин не загружен, используем стандартный input
            datepicker.addEventListener('change', function () {
                filterState.date = this.value;
                console.log(`Выбрана дата: ${this.value}`);
                applyFilters();
            });
        }
    }

    /******* Календарь *******/

    const button = document.getElementById(FILTER_IDS.CALENDAR_BUTTON);
    const calendarContent = document.getElementById(FILTER_IDS.CALENDAR_CONTENT);

    if (button && calendarContent) {
        button.addEventListener('click', function () {
            console.log('Display', calendarContent.style.display)

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

    /******* Функция отображения сведений о бригаде в модальном окне *******/
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
                    <div class="card-header bg-light">
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
                                <div class="list-group-item ${member.is_leader ? 'bg-light' : ''}">
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


});
