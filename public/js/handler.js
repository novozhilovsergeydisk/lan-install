// Функция для логирования кликов
function logButtonClick(buttonId, buttonText) {
    console.log(`Клик по кнопке: ${buttonText} (ID: ${buttonId})`);
    // Здесь можно добавить дополнительную логику, например, показ уведомления
}

// Обработчики для кнопок
document.addEventListener('DOMContentLoaded', function() {
    // Кнопка выхода
    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            logButtonClick('logout-button', 'Выход');
            // e.preventDefault(); // Раскомментируйте, если нужно отменить стандартное действие
        });
    }

    // Вкладки навигации
    const navTabs = ['requests', 'teams', 'addresses', 'users', 'reports'];
    navTabs.forEach(tab => {
        const tabElement = document.getElementById(`${tab}-tab`);
        if (tabElement) {
            tabElement.addEventListener('click', function() {
                logButtonClick(`${tab}-tab`, `Вкладка ${tab}`);
            });
        }
    });

    // Кнопка сброса фильтров
    const resetFiltersButton = document.getElementById('reset-filters-button');
    if (resetFiltersButton) {
        resetFiltersButton.addEventListener('click', function() {
            logButtonClick('reset-filters-button', 'Сбросить фильтры');
            
            // Снимаем выделение со всех чекбоксов фильтров
            const filterCheckboxes = ['filter-statuses', 'filter-teams'];
            filterCheckboxes.forEach(checkboxId => {
                const checkbox = document.getElementById(checkboxId);
                if (checkbox && checkbox.checked) {
                    checkbox.checked = false;
                    // Имитируем событие change для обновления состояния
                    const event = new Event('change');
                    checkbox.dispatchEvent(event);
                }
            });
            
            console.log('Все фильтры сброшены');
        });
    }

    // Обработчики для чекбоксов фильтров
    const filterCheckboxes = ['filter-statuses', 'filter-teams'];
    filterCheckboxes.forEach(checkboxId => {
        const checkbox = document.getElementById(checkboxId);
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                const label = document.querySelector(`label[for="${checkboxId}"]`);
                const labelText = label ? label.textContent : 'Неизвестный фильтр';
                console.log(`Фильтр "${labelText}" ${this.checked ? 'включен' : 'отключен'}`);
                // Здесь будет логика применения фильтра
            });
        }
    });

    // Обработчик для выбора даты в календаре
    const datepicker = document.getElementById('datepicker');
    if (datepicker) {
        // Инициализация datepicker, если используется плагин
        if ($.fn.datepicker) {
            $('#datepicker').datepicker({
                format: 'dd.mm.yyyy',
                language: 'ru',
                autoclose: true,
                todayHighlight: true
            }).on('changeDate', function(e) {
                const selectedDate = e.format('dd.mm.yyyy');
                console.log(`Выбрана дата: ${selectedDate}`);
                // Здесь будет логика фильтрации по дате
            });
        } else {
            // Если плагин не загружен, используем стандартный input
            datepicker.addEventListener('change', function() {
                console.log(`Выбрана дата: ${this.value}`);
                // Здесь будет логика фильтрации по дате
            });
        }
    }

// Простая проверка загрузки скрипта
console.log('handler.js загружен');

// Обработчик клика по кнопке просмотра бригады
document.addEventListener('click', function(event) {
    // Проверяем, что клик был по кнопке просмотра бригады
    const btn = event.target.closest('.view-brigade-btn');
    if (!btn) return;
    
    console.log('Нажата кнопка просмотра бригады');
    
    // Получаем ID бригады из атрибута data-brigade-id
    const brigadeId = btn.getAttribute('data-brigade-id');
    console.log('ID бригады:', brigadeId);
    
    if (!brigadeId) {
        console.error('Не указан ID бригады');
        return;
    }
    
    // Находим модальное окно и показываем его
    const modalElement = document.getElementById('brigadeModal');
    if (!modalElement) {
        console.error('Модальное окно не найдено в DOM');
        return;
    }
    
    // Инициализируем модальное окно, если еще не инициализировано
    let modal = bootstrap.Modal.getInstance(modalElement);
    if (!modal) {
        modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
    }
    
    // Показываем индикатор загрузки
    const modalBody = modalElement.querySelector('.modal-body');
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="text-center my-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <p class="mt-2">Загрузка данных о бригаде...</p>
            </div>`;
    }
    
    // Показываем модальное окно
    modal.show();
    
    // Отправляем запрос к серверу
    fetch(`/brigade/${brigadeId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(async response => {
        console.log('Ответ сервера:', response.status, response.statusText);
        const responseData = await response.text();
        console.log('Данные ответа:', responseData);
        
        if (!response.ok) {
            throw new Error(`Ошибка HTTP: ${response.status}`);
        }
        
        return JSON.parse(responseData);
    })
    .then(data => {
        if (data.success) {
            renderBrigadeDetails(data);
        } else {
            throw new Error(data.message || 'Не удалось загрузить данные бригады');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        const modalBody = document.querySelector('#brigadeModal .modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Ошибка при загрузке данных: ${error.message}
                </div>`;
        }
    });
});

// Функция для отображения данных бригады
function renderBrigadeDetails(data) {
    console.log('Рендерим данные бригады:', data);
    const modalBody = document.querySelector('#brigadeModal .modal-body');
    if (!modalBody) return;
    
    let html = `
        <div class="brigade-details">
            <h5>Бригада: ${data.brigade.name || 'Не указано'}</h5>
            <p class="text-muted">ID: ${data.brigade.id}</p>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Бригадир</h6>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>${data.leader.fio || 'Не указан'}</strong></p>
                    <p class="mb-1 text-muted">${data.leader.position_name || ''}</p>
                    <p class="mb-0">${data.leader.phone || 'Телефон не указан'}</p>
                </div>
            </div>`;
    
    if (data.members && data.members.length > 0) {
        html += `
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Члены бригады</h6>
                </div>
                <ul class="list-group list-group-flush">`;
        
        data.members.forEach(member => {
            html += `
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${member.fio || 'Без имени'}</strong>
                            <div class="text-muted small">${member.position_name || 'Должность не указана'}</div>
                        </div>
                        <span class="badge bg-secondary">ID: ${member.id}</span>
                    </div>
                </li>`;
        });
        
        html += `
                </ul>
            </div>`;
    } else {
        html += `
            <div class="alert alert-info mt-3">
                В бригаде нет участников
            </div>`;
    }
    
    html += `
        </div>`;
    
    modalBody.innerHTML = html;
}

// Инициализация модального окна при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM загружен, инициализируем модальное окно');
    
    // Инициализируем модальное окно
    const modalElement = document.getElementById('brigadeModal');
    if (modalElement) {
        // Очищаем содержимое при закрытии
        modalElement.addEventListener('hidden.bs.modal', function() {
            const modalBody = this.querySelector('.modal-body');
            if (modalBody) {
                modalBody.innerHTML = `
                    <div class="text-center my-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2">Загрузка данных о бригаде...</p>
                    </div>`;
            }
        });
    } else {
        console.error('Элемент модального окна не найден');
    }
});

    // Function to render brigade details in the modal
    function renderBrigadeDetails(data) {
        const { brigade, leader, members } = data;
        const detailsContainer = document.getElementById('brigadeDetails');
        
        let html = `
            <div class="brigade-details">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">${brigade.name || 'Без названия'}</h4>
                    <span class="badge bg-primary">ID: ${brigade.id}</span>
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
