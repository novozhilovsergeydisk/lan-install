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
        defaultOption.value = 'all_employees';
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

// Функция для загрузки списка адресов
export async function loadAddressesForReport() {
    try {
        const response = await fetch('/reports/addresses');

        if (!response.ok) {
            throw new Error('Ошибка при загрузке адресов');
        }

        const data = await response.json();
        const container = document.getElementById('report-addresses-container');
        
        if (!container) return;
        
        // Очищаем контейнер перед добавлением нового содержимого
        container.innerHTML = '';
        
        // Создаем выпадающий список
        const select = document.createElement('select');
        select.id = 'report-addresses';
        select.className = 'form-select mt-2';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = 'all_addresses';
        defaultOption.textContent = 'Все адреса';
        select.appendChild(defaultOption);
        
        // Заполняем выпадающий список адресами
        data.forEach(address => {
            const option = document.createElement('option');
            option.value = address.id;
            const addressText = 'ул. ' + address.street + ', ' + 
                             (address.houses ? 'д.' + address.houses + ', ' : '') + 
                             (address.city_name || '');
            option.textContent = addressText;
            option.dataset.text = addressText; // Сохраняем текст для поиска
            option.setAttribute('data-city-id', address.city_id);
            select.appendChild(option);
        });
        
        // Добавляем select в контейнер
        container.appendChild(select);
        
        // Инициализируем кастомный селект, если функция доступна
        if (typeof window.initCustomSelect === 'function') {
            window.initCustomSelect('report-addresses', 'Выберите адрес из списка');
        } else {
            // Если функция initCustomSelect недоступна, просто показываем обычный select
            select.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Ошибка при загрузке адресов:', error);
        showAlert('Не удалось загрузить список адресов', 'danger');
    }
}

// Функция для загрузки отчёта
export async function loadReport() {
    let startDate = $('#datepicker-reports-start').datepicker('getFormattedDate');
    let endDate = $('#datepicker-reports-end').datepicker('getFormattedDate');
    const employeeSelect = document.getElementById('report-employees');
    const addressSelect = document.getElementById('report-addresses');
    const allPeriod = document.getElementById('report-all-period');
    let url = '';    
    
    if (!startDate || !endDate) {
        showAlert('Необходимо указать даты', 'warning');
        return;
    }

    // console.log(employeeSelect.value);
    // console.log(addressSelect.value);
    // console.log(allPeriod.checked);

    // Отчет за весь период
    if (allPeriod.checked) {
        startDate = null;
        endDate = null;
        url = '/reports/requests/all-period';
    }

    if (startDate && endDate && employeeSelect.value === 'all_employees' && addressSelect.value === 'all_addresses') {
        url = '/reports/requests/by-date';
    }

    if (startDate && endDate && employeeSelect.value > 0 && addressSelect.value === 'all_addresses') {
        url = '/reports/requests/by-employee-date';
    } 
    
    if (startDate && endDate && addressSelect.value > 0 && employeeSelect.value === 'all_employees') {
        url = '/reports/requests/by-address-date';
    }

    // Отчет за ВЕСЬ ПЕРИОД по сотруднику +
    if (!startDate && !endDate &&  employeeSelect.value > 0 && addressSelect.value === 'all_addresses' && allPeriod.checked) {
        url = '/reports/requests/by-employee-all-period';
    }
    
    // Отчет за ВЕСЬ ПЕРИОД по адресу
    if (!startDate && !endDate && addressSelect.value > 0 && employeeSelect.value === 'all_employees' && allPeriod.checked) {
        url = '/reports/requests/by-address-all-period';
    }

    // Отчет за ВЕСЬ ПЕРИОД по сотруднику и адресу
    if (!startDate && !endDate &&  employeeSelect.value > 0 && addressSelect.value > 0 && allPeriod.checked) {
        url = '/reports/requests/by-employee-and-address-all-period';
    }

    // Отчет за ПЕРИОД по сотруднику и адресу
    if (startDate && endDate &&  employeeSelect.value > 0 && addressSelect.value > 0 && !allPeriod.checked) {
        url = '/reports/requests/by-employee-and-address-date';
    }
    
    console.log('Request URL:', url);

    console.log('Request data:', { startDate, endDate, employeeId: employeeSelect.value, addressId: addressSelect.value, allPeriod: allPeriod.checked });

    

    const result = await postData(url, { startDate, endDate, employeeId: employeeSelect.value, addressId: addressSelect.value, allPeriod: allPeriod.checked });

    console.log('Result:', result); 

    if (result.success) {
        renderReportTable({
            requests: result.requestsByDateRange || result.requestsByEmployeeAndDateRange || result.requestsAllPeriodByEmployee || result.requestsAllPeriod || result.requestsByAddressAndDateRange || [],
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
                     style="max-height: 100px; overflow: auto; font-size: 0.85rem; max-width: 35rem;">
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
export async function initReportHandlers() {
    try {
        // Инициализация календарей
        initReportDatepickers();
        
        // Загрузка списка сотрудников
        await loadEmployeesForReport();

        // Загрузка списка адресов и ожидание её завершения
        await loadAddressesForReport();

        const employeeSelect = document.getElementById('report-employees');
        const addressSelect = document.getElementById('report-addresses');
        const allPeriodCheckbox = document.getElementById('report-all-period');
        
        if (allPeriodCheckbox) {
            allPeriodCheckbox.addEventListener('change', () => {
                if (allPeriodCheckbox.checked) {
                  console.log('All period checkbox is checked');

                  // Сбрасываем выбор адреса на "Все адреса"
                  if (addressSelect) {
                    //   addressSelect.value = 'all_addresses';
                      
                    //   // Очищаем кастомный инпут для адреса, если он существует
                    //   const customAddressInput = document.querySelector('#custom-select-wrapper-report-addresses .custom-select-input');
                    //   if (customAddressInput) {
                    //       customAddressInput.value = '';
                    //       customAddressInput.placeholder = 'Выберите адрес из списка';
                    //   }
                  }

                  // Сбрасываем выбор сотрудника на "Все сотрудники"
                  if (employeeSelect) {
                    //   employeeSelect.value = 'all_employees';
                      
                    //   // Очищаем кастомный инпут для сотрудника, если он существует
                    //   const customEmployeeInput = document.querySelector('#custom-select-wrapper-report-employees .custom-select-input');
                    //   if (customEmployeeInput) {
                    //       customEmployeeInput.value = '';
                    //       customEmployeeInput.placeholder = 'Выберите сотрудника из списка';
                    //   }
                  }
                } else {
                  console.log('All period checkbox is not checked');
                }
            });
        }

        if (employeeSelect) {
            employeeSelect.addEventListener('change', () => {
                const selectedEmployeeId = employeeSelect.value;
                console.log('Selected employee ID:', selectedEmployeeId);
                
                // Сбрасываем выбор адреса на "Все адреса"
                if (addressSelect) {
                    // addressSelect.value = 'all_addresses';
                    
                    // // Находим и очищаем кастомный инпут для адреса, если он существует
                    // const customAddressInput = document.querySelector('#custom-select-wrapper-report-addresses .custom-select-input');
                    // if (customAddressInput) {
                    //     customAddressInput.value = '';
                    //     customAddressInput.placeholder = 'Выберите адрес из списка';
                    // }
                }   

                // Сбрасываем чекбокс "За весь период"
                if (allPeriodCheckbox) {
                    // allPeriodCheckbox.checked = false;
                }
            });
        }

        if (addressSelect) {
            addressSelect.addEventListener('change', () => {
                const selectedAddressId = addressSelect.value;
                console.log('Selected address ID:', selectedAddressId);
                
                // Сбрасываем выбор сотрудника на "Все сотрудники"
                if (employeeSelect) {
                    // employeeSelect.value = 'all_employees';
                    
                    // // Находим и очищаем кастомный инпут для сотрудника, если он существует
                    // const customEmployeeInput = document.querySelector('#custom-select-wrapper-report-employees .custom-select-input');
                    // if (customEmployeeInput) {
                    //     customEmployeeInput.value = '';
                    //     customEmployeeInput.placeholder = 'Выберите сотрудника из списка';
                    // }
                }
                
                // Сбрасываем чекбокс "За весь период"
                if (allPeriodCheckbox) {
                    // allPeriodCheckbox.checked = false;
                }
                
                // Дополнительная логика фильтрации, если нужна
            });
        } else {
            console.error('Элемент report-addresses не найден после загрузки адресов');
        }
    } catch (error) {
        console.error('Ошибка при инициализации обработчиков отчета:', error);
        showAlert('Произошла ошибка при загрузке отчета', 'danger');
    }
    
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
