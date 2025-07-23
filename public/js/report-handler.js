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
        
        const container = document.querySelector('.col-md-4');
        if (container) {
            container.prepend(select);
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
    let url = '/reports';    
    
    if (!startDate || !endDate) {
        showAlert('Необходимо указать даты', 'warning');
        return;
    }

    console.log(employeeSelect.value);

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

    renderReportTable(result);
}

/**
 * Формирует информацию о бригаде на основе данных заявки и глобальных данных о бригадах
 * @param {Object} request - Данные заявки
 * @returns {string} HTML-код с информацией о бригаде
 */
function getBrigadeInfo(request) {
    try {
        // Проверяем, есть ли у заявки бригада
        if (!request.brigade_id) return 'Не назначена';
        
        // Получаем данные о бригадах из глобальной переменной
        const brigadeMembers = window.brigadeMembersData || [];
        
        // Ищем информацию о бригаде по ID
        const brigadeData = brigadeMembers.find(b => b.id === request.brigade_id);

        console.log('brigadeMembers', brigadeMembers);
        console.log('brigadeData', brigadeData);
        
        // Если данные о бригаде не найдены, возвращаем информацию об ID
        if (!brigadeData) {
            return `
                <div class="small">
                    <div class="fw-bold">ID бригады: ${request.brigade_id}</div>
                    <div class="text-muted">Данные о бригаде не найдены</div>
                </div>
            `;
        }
        
        // Формируем информацию о бригаде
        return `
            <div class="small">
                <div class="fw-bold">${brigadeData.name || 'Без названия'}</div>
                ${brigadeData.leader_id ? 
                    `<div class="text-muted">Бригадир: ID ${brigadeData.leader_id}</div>` : ''}
                ${brigadeData.employee_leader_name ? 
                    `<div class="text-muted">Бригадир: ${brigadeData.employee_leader_name}</div>` : ''}
                ${request.brigade_lead ? 
                    `<div class="text-muted">Ответственный: ${request.brigade_lead}</div>` : ''}
            </div>
        `;
    } catch (error) {
        console.error('Ошибка при формировании информации о бригаде:', error);
        return `
            <div class="small text-danger">
                Ошибка загрузки информации о бригаде
                ${request.brigade_id ? `<div>ID: ${request.brigade_id}</div>` : ''}
            </div>
        `;
    }
}

/**
 * Formats brigade members information for display
 * @param {number} brigadeId - ID of the brigade
 * @param {Array} brigadeMembers - Array of all brigade members
 * @returns {string} HTML string with formatted brigade info
 */
function getBrigadeMembersInfo(brigadeId, brigadeMembers) {
    if (!brigadeId || !brigadeMembers || !brigadeMembers.length) return '';
    
    // Filter members of this specific brigade
    const brigadeGroup = brigadeMembers.filter(m => m.brigade_id == brigadeId);
    
    if (!brigadeGroup.length) return '';
    
    // Get brigade leader and name from the first member (all should have the same brigade info)
    const leaderName = brigadeGroup[0].employee_leader_name;
    const brigadeName = brigadeGroup[0].brigade_name;
    
    if (!leaderName) return '';
    
    // Create member list, excluding the leader
    const memberNames = brigadeGroup
        .filter(m => m.employee_name && m.employee_name !== leaderName)
        .map(m => {
            // Simple name shortening (first word + first letter of next words)
            const parts = m.employee_name.split(' ');
            if (parts.length <= 1) return m.employee_name;
            return parts[0] + ' ' + parts[1][0] + '.' + (parts[2] ? parts[2][0] + '.' : '');
        });
    
    return `
        <div style="font-size: 0.75rem; line-height: 1.2;">
            <div class="mb-1"><i>${brigadeName || 'Бригада'}</i></div>
            <div><strong>${shortenName(leaderName)}</strong>${memberNames.length ? ', ' + memberNames.join(', ') : ''}</div>
        </div>
    `;
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
    const tbody = document.getElementById('requestsReportBody');
    // console.log('Rendering report table with data:', data);

    
    if (!tbody) {
        console.error('Table body element not found');
        return;
    }
    
    // Clear existing rows
    tbody.innerHTML = '';
    
    if (!data || data.length === 0) {
        // Show "no data" message
        const row = document.createElement('tr');
        row.innerHTML = `
            <td colspan="8" class="text-center py-4">
                <div class="alert alert-info m-0">
                    <i class="bi bi-info-circle me-2"></i>Нет данных для отображения
                </div>
            </td>
        `;
        tbody.appendChild(row);
        return;
    }

    const requests = data.requestsByDateRange;
    const brigadeMembers = data.brigadeMembersWithDetails;
    const comments_by_request = data.comments_by_request;

    console.log('Requests:', requests);
    console.log('Brigade members:', brigadeMembers);
    console.log('Comments by request:', comments_by_request);
    
    // Add a row for each request   
    requests.forEach((request, index) => {
        const row = document.createElement('tr');
        row.id = `request-${request.id}`;
        row.className = 'align-middle';
        row.style.setProperty('--status-color', request.status_color || '#e2e0e6');
        row.setAttribute('data-request-id', request.id);
        
        // Format the execution date
        const executionDate = request.execution_date 
            ? new Date(request.execution_date).toLocaleDateString('ru-RU')
            : 'Не указана';
            
        // Format address and organization like in main table
        const addressHtml = request.client_organization || request.street || request.client_fio || request.client_phone 
            ? `
                ${request.client_organization ? 
                    `<div class="text-muted" style="font-size: 0.8rem;">${request.client_organization}</div>` : ''}
                ${request.street ? 
                    `<small class="text-muted text-truncate d-block" 
                            data-bs-toggle="tooltip" 
                            title="ул. ${request.street}, д. ${request.houses || ''} (${request.district || ''})">
                        ${request.city_name && request.city_name !== 'Москва' ? 
                            `${request.city_name}, ` : ''}ул. ${request.street}, д. ${request.houses || ''}
                    </small>` : 
                    '<small class="text-muted text-truncate d-block">Адрес не указан</small>'}
                ${request.client_fio ? 
                    `<div class="text-muted" style="font-size: 0.8rem;"><i>${request.client_fio}</i></div>` : ''}
                <small style="font-size: 0.8rem;" 
                       class="text-muted text-truncate d-block">
                    <i>${request.client_phone || 'Нет телефона'}</i>
                </small>
            `
            : '<small class="text-muted">Нет данных</small>';
        
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
                       class="text-muted hover-text-gray-700 hover-underline view-brigade-btn"
                       style="text-decoration: none; font-size: 0.75rem; line-height: 1.2;"
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
                       class="text-muted hover-text-gray-700 hover-underline view-brigade-btn"
                       style="text-decoration: none; font-size: 0.75rem; line-height: 1.2;"
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
            brigadeInfo = '<small class="text-muted d-block mb-1">Не назначена</small>';
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
        row.innerHTML = `
            <td class="text-muted">111 ${index + 1}</td>
            <td class="text-muted">
                <div class="d-flex flex-column">
                    <span>222 ${executionDate}</span>
                    ${request.execution_time ? 
                        `<small>${request.execution_time}</small>` : ''}
                </div>
            </td>
            <td class="text-muted" style="width: 14rem; max-width: 14rem; overflow: hidden; text-overflow: ellipsis;">
                333 ${addressHtml}
            </td>
            <td>
                444${comments_by_request[request.id] && comments_by_request[request.id].length > 0 ? 
                    `<div class="comment-preview small text-muted" 
                          style="max-height: 100px; overflow: auto; font-size: 0.85rem;">
                        ${comments_by_request[request.id].map(comment => {
                            const date = new Date(comment.created_at).toLocaleString('ru-RU');
                            return `
                                <div class="mb-1">
                                    <div class="d-flex justify-content-between">
                                        <span>${comment.comment || 'Аноним'}</span>
                                    </div>
                                    <div>${date}</div>
                                </div>
                            `;
                        }).join('<hr class="my-1">')}
                    </div>` : 
                    '<small class="text-muted">Нет комментариев</small>'
                }
            </td>
            <td class="text-muted">
                <div class="brigade-info">
                    555 ${brigadeInfo}
                </div>
            </td>
        `;
        
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
