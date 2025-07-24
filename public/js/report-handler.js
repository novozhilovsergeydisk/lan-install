import { showAlert, postData } from './utils.js';

// Инициализация календарей для отчётов
export function initReportDatepickers() {
    // Настройки для русского языка
    $.fn.datepicker.dates['ru'] = {
        days: ["Воскресенье", "Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота"],
        daysShort: ["Вск", "Пнд", "Втр", "Срд", "Чтв", "Птн", "Суб"],
        daysMin: ["Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"],
        months: ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"],
        monthsShort: ["Янв", "Фев", "Мар", "Апр", "Май", "Июн", "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек"],
        today: "Сегодня",
        clear: "Очистить",
        format: "dd.mm.yyyy",
        weekStart: 1,
        autoclose: true
    };

    // Инициализация datepicker для календарей в отчётах
    $('#datepicker-reports-start, #datepicker-reports-end').datepicker({
        language: 'ru',
        format: 'dd.mm.yyyy',
        autoclose: true,
        todayHighlight: true
    });

    // Установка дат по умолчанию
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    
    $('#datepicker-reports-start').datepicker('setDate', firstDayOfMonth);
    $('#datepicker-reports-end').datepicker('setDate', today);
}

// Функция для загрузки списка сотрудников
export async function loadEmployeesForReport() {
    try {
        const response = await fetch('/reports/employees');
        const data = await response.json();
        
        const select = document.createElement('select');
        select.id = 'report-employees';
        select.className = 'form-select';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = 'all';
        defaultOption.textContent = 'Все сотрудники';
        select.appendChild(defaultOption);
        
        data.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.id;
            option.textContent = employee.fio;
            select.appendChild(option);
        });
        
        const container = document.getElementById('report-employees-container');
        if (container) {
            container.appendChild(select);
        }
    } catch (error) {
        console.error('Ошибка при загрузке сотрудников:', error);
        showAlert('Не удалось загрузить список сотрудников', 'danger');
    }
}

// Функция для загрузки отчёта
export async function loadReport() {
    const startDate = $('#datepicker-reports-start').datepicker('getFormattedDate');
    const endDate = $('#datepicker-reports-end').datepicker('getFormattedDate');
    const employeeSelect = document.getElementById('report-employees');
    let url = '';    
    
    if (!startDate || !endDate) {
        showAlert('Необходимо указать даты', 'warning');
        return;
    }

    // console.log(employeeSelect.value);

    if (startDate && endDate && employeeSelect.value === 'all') {
        url = '/reports/requests/by-date';
    }

    if (startDate && endDate && employeeSelect.value > 0) {
        url = '/reports/requests/by-employee-date';
    }  
    
    console.log('Request URL:', url);

    console.log('Request data:', { startDate, endDate, employeeId: employeeSelect.value });

    const result = await postData(url, { startDate, endDate, employeeId: employeeSelect.value });

    console.log('Result:', result); 

    if (result.success) {
        renderReportTable({
            requests: result.requestsByDateRange || result.requestsByEmployeeAndDateRange || [],
            brigadeMembers: result.brigadeMembersWithDetails || [],
            comments_by_request: result.comments_by_request || {}
        });
    } else {
        renderReportTable({
            requests: [],
            brigadeMembers: [],
            comments_by_request: {}
        });
        showAlert(result.message || 'Ошибка при загрузке отчёта', 'danger');
    }
}

/**
 * Shortens a full name to first name + initials
 * @param {string} fullName - Full name to shorten
 * @returns {string} Shortened name
 */
function shortenName(fullName) {
    if (!fullName) return '';
    const parts = fullName.split(' ');
    if (parts.length <= 1) return fullName;
    return parts[0] + ' ' + parts[1][0] + '.' + (parts[2] ? parts[2][0] + '.' : '');
}

/**
 * Renders the report data in the table
 * @param {Array} data - Array of request objects
 */
function renderReportTable(data) {
    console.log('Полученные данные в renderReportTable:', data);
    
    const tbody = document.querySelector('#requestsReportTable tbody');
    if (!tbody) {
        console.error('Table body not found. Looking for: #requestsReportTable tbody');
        return;
    }

    // Очищаем таблицу
    tbody.innerHTML = '';

    // Определяем, пришёл ли массив или объект
    const responseData = Array.isArray(data) ? data[0] : data;
    
    // Проверяем данные (поддерживаем оба формата ответа)
    let requests, brigadeMembers, comments_by_request;
    
    // Новый формат ответа
    if (responseData.requestsByDateRange && Array.isArray(responseData.requestsByDateRange)) {
        requests = responseData.requestsByDateRange || [];
        brigadeMembers = responseData.brigadeMembersWithDetails || [];
        comments_by_request = responseData.comments_by_request || {};
    } 
    // Старый формат ответа
    else if (Array.isArray(responseData.requests)) {
        requests = responseData.requests || [];
        brigadeMembers = responseData.brigadeMembers || [];
        comments_by_request = responseData.comments_by_request || {};
    } 
    // Неизвестный формат
    else {
        console.error('Некорректный формат данных от сервера:', responseData);
        const row = document.createElement('tr');
        row.innerHTML = '<td colspan="8" class="text-center">Ошибка формата данных</td>';
        tbody.appendChild(row);
        return;
    }
    
    if (requests.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="8" class="text-center py-4">
                <div class="alert alert-info m-0">
                    <i class="bi bi-info-circle me-2"></i>Нет данных для отображения.
                </div>
            </td>`;
        tbody.appendChild(row);
        return;
    }

    console.log('Обработка запросов:', requests);
    console.log('Данные бригад:', brigadeMembers);
    console.log('Комментарии:', comments_by_request);

    // Add a row for each request   
    requests.forEach((request, index) => {
        const row = document.createElement('tr');
        row.id = `request-${request.id}`;
        row.className = 'align-middle status-row';

        console.log('Обработка запроса:', request);
        
        // Устанавливаем цвет статуса через CSS-переменную
        if (request.status_color) {
            row.style.setProperty('--status-color', request.status_color);
        }
        
        row.setAttribute('data-request-id', request.id);
        
        // Format the execution date
        const executionDate = request.execution_date 
            ? new Date(request.execution_date).toLocaleDateString('ru-RU')
            : 'Не указана';
            
        // Format address and organization like in main table
        const addressHtml = request.client_organization || request.street || request.client_fio || request.client_phone 
            ? `
                ${request.client_organization ? 
                    `<div style="font-size: 0.8rem;">${request.client_organization}</div>` : ''}
                ${request.street ? 
                    `<small class="text-truncate d-block" 
                            data-bs-toggle="tooltip" 
                            title="ул. ${request.street}, д. ${request.houses || ''} (${request.district || ''})">
                        ${request.city_name && request.city_name !== 'Москва' ? 
                            `${request.city_name}, ` : ''}ул. ${request.street}, д. ${request.houses || ''}
                    </small>` : 
                    '<small class="text-truncate d-block">Адрес не указан</small>'}
                ${request.client_fio ? 
                    `<div style="font-size: 0.8rem;"><i>${request.client_fio}</i></div>` : ''}
                <small style="font-size: 0.8rem;" 
                       class="text-truncate d-block">
                    <i>${request.client_phone || 'Нет телефона'}</i>
                </small>
            `
            : '<small>Нет данных</small>';
        
        // Format brigade info for display
        let brigadeInfo = '';
        if (request.brigade_id) {
            // Find brigade members for this request
            const brigadeGroup = brigadeMembers.filter(m => m.brigade_id == request.brigade_id);
            
            if (brigadeGroup.length > 0) {
                const brigade = brigadeGroup[0];
                const members = brigadeGroup
                    .filter(m => m.employee_name && m.employee_name !== brigade.employee_leader_name)
                    .map(m => shortenName(m.employee_name));
                
                brigadeInfo = `
                    <div style="font-size: 0.75rem; line-height: 1.2;">
                        <div class="mb-1"><i>${brigade.brigade_name || 'Бригада'}</i></div>
                        ${brigade.employee_leader_name ? 
                            `<div><strong>${shortenName(brigade.employee_leader_name)}</strong>${members.length ? ', ' + members.join(', ') : ''}</div>` : 
                            ''}
                    </div>
                    <a href="#" 
                       class="hover-text-gray-700 hover-underline view-brigade-btn"
                       style="color: #000; text-decoration: none; font-size: 0.75rem; line-height: 1.2;"
                       onmouseover="this.style.textDecoration='underline'"
                       onmouseout="this.style.textDecoration='none'"
                       data-bs-toggle="modal" 
                       data-bs-target="#brigadeModal"
                       data-brigade-id="${request.brigade_id}">
                        подробнее...
                    </a>
                `;
            } else {
                // Fallback to basic info if no members found
                brigadeInfo = `
                    <div style="font-size: 0.75rem; line-height: 1.2;">
                        <div class="mb-1"><i>${request.brigade_name || 'Бригада'}</i></div>
                        ${request.brigade_lead ? `<div><strong>${shortenName(request.brigade_lead)}</strong></div>` : ''}
                    </div>
                    <a href="#" 
                       class="hover-text-gray-700 hover-underline view-brigade-btn"
                       style="color: #000; text-decoration: none; font-size: 0.75rem; line-height: 1.2;"
                       onmouseover="this.style.textDecoration='underline'"
                       onmouseout="this.style.textDecoration='none'"
                       data-bs-toggle="modal" 
                       data-bs-target="#brigadeModal"
                       data-brigade-id="${request.brigade_id}">
                        подробнее...
                    </a>
                `;
            }
        } else {
            brigadeInfo = '<small>Не назначена</small>';
        }
        
        // Format comment with status info if available
        let commentContent = comments_by_request[request.id] || 'Нет комментариев';

        if (request.status_name) {
            commentContent = `
                <div class="d-flex align-items-center mb-1">
                    <span class="badge me-2" style="background-color: ${request.status_color || '#e2e0e6'}">
                        ${request.status_name}
                    </span>
                    ${request.number || ''}
                </div>
                ${commentContent}
            `;
        }
        
        // Create the row HTML
        const numberCell = document.createElement('td');
        numberCell.textContent = index + 1;
        row.appendChild(numberCell);
        
        // Дата заявки
        const dateCell = document.createElement('td');
        dateCell.innerHTML = `
            <div class="d-flex flex-column">
                <span>${executionDate}</span>
                <div style="font-size: 0.8rem;">${request.number || ''}</div>
            </div>
        `;
        row.appendChild(dateCell);
        
        // Адрес
        const addressCell = document.createElement('td');
        addressCell.style.width = '14rem';
        addressCell.style.maxWidth = '14rem';
        addressCell.style.overflow = 'hidden';
        addressCell.style.textOverflow = 'ellipsis';
        addressCell.innerHTML = addressHtml;
        row.appendChild(addressCell);
        
        // Комментарии
        const commentCell = document.createElement('td');
        let commentHtml = '';
        
        if (comments_by_request[request.id] && comments_by_request[request.id].length > 0) {
            commentHtml = `
                <div class="comment-preview small" 
                     style="max-height: 100px; overflow: auto; font-size: 0.85rem;">
                    ${comments_by_request[request.id].map(comment => {
                        const date = new Date(comment.created_at).toLocaleString('ru-RU');
                        return `
                            <div class="comment-item" style="background-color: white; border: 1px solid gray; border-radius: 3px; padding: 5px; line-height: 16px; font-size: smaller; margin-bottom: 5px;">
                                <div class="d-flex justify-content-between">
                                    <span>${comment.comment || 'Система'}</span>
                                </div>
                                <div style="color:rgb(66, 68, 69); font-size: 0.9em;">${date}</div>
                                <div style="color:rgb(66, 68, 69); font-size: 1.0em;">Добавил: ${comment.author_name}</div>
                            </div>
                        `;
                    }).join('')}
                </div>`;
        } else {
            commentHtml = '<small>Нет комментариев</small>';
        }
        
        commentCell.innerHTML = commentHtml;
        row.appendChild(commentCell);
        
        // Бригада
        const brigadeCell = document.createElement('td');
        brigadeCell.innerHTML = `
            <div class="brigade-info">
                ${brigadeInfo}
            </div>
        `;
        row.appendChild(brigadeCell);
        
        tbody.appendChild(row);
    });
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Обработчик клика по кнопке просмотра заявки
function handleViewRequest(event) {
    const button = event.currentTarget;
    const requestId = button.dataset.requestId;
    
    // Здесь можно добавить логику для открытия модального окна с деталями заявки
    // Например, можно сделать запрос к API для получения полной информации о заявке
    console.log('Просмотр заявки с ID:', requestId);
    
    // Пример открытия модального окна (если оно есть на странице)
    const modal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
    if (modal) {
        // Установить ID заявки в модальное окно
        const modalElement = document.getElementById('requestDetailsModal');
        if (modalElement) {
            modalElement.dataset.requestId = requestId;
            modal.show();
            
            // Здесь можно загрузить детали заявки через API
            // loadRequestDetails(requestId);
        }
    }
}

// Инициализация обработчиков для отчётов
export function initReportHandlers() {
    // Инициализация календарей
    initReportDatepickers();
    
    // Загрузка списка сотрудников
    loadEmployeesForReport();
    
    // Обработчик кнопки генерации отчёта
    const generateBtn = document.getElementById('generate-report-btn');
    if (generateBtn) {
        generateBtn.addEventListener('click', async () => {
            try {
                await loadReport();
            } catch (error) {
                console.error('Ошибка при генерации отчёта:', error);
                showAlert('Произошла ошибка при загрузке отчёта', 'danger');
            }
        });
    }
    
    // Делегирование событий для кнопок просмотра в таблице отчётов
    document.addEventListener('click', (event) => {
        if (event.target.closest('.view-request-btn')) {
            handleViewRequest(event);
        }
    });
}
