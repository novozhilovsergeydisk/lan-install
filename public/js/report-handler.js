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

    // Инициализация datepicker для календарей в отчётах (привязываем к инпутам)
    const $startInput = $('#datepicker-reports-start');
    const $endInput = $('#datepicker-reports-end');

    $startInput.add($endInput).datepicker({
        language: 'ru',
        format: 'dd.mm.yyyy',
        autoclose: true,
        todayHighlight: true
    });

    // Отключаем автопоказ календаря по фокусу/клику на инпуте — календарь открывается только по кнопке
    // В bootstrap-datepicker 1.9.0 обработчики навешиваются без namespace, поэтому снимаем generic-события
    $startInput.add($endInput)
        .off('focus')
        .off('click')
        .off('focusin')
        .off('mousedown');

    // Открытие только по кнопке: используем флаги допуска показа
    let allowShowStart = false;
    let allowShowEnd = false;

    $startInput.on('show', function (e) {
        if (!allowShowStart) {
            e.preventDefault();
            return false;
        }
        // Сброс флага после разрешённого показа
        allowShowStart = false;
    });
    $endInput.on('show', function (e) {
        if (!allowShowEnd) {
            e.preventDefault();
            return false;
        }
        allowShowEnd = false;
    });

    // Обработчики кнопок для показа календаря
    $('#btn-report-start-calendar').on('click', function () {
        allowShowStart = true;
        $startInput.datepicker('show');
    });
    $('#btn-report-end-calendar').on('click', function () {
        allowShowEnd = true;
        $endInput.datepicker('show');
    });

    // Разрешаем обычный ввод с клавиатуры: но если плагин всё же пытается открыть при клике/фокусе — сразу скрываем
    $startInput.on('click focusin', function () {
        if (!allowShowStart) {
            try { $startInput.datepicker('hide'); } catch (_) {}
        }
        // Выделяем весь текст, чтобы новый ввод заменял предзаполненную дату
        try { this.select(); } catch (_) {}
    });
    $endInput.on('click focusin', function () {
        if (!allowShowEnd) {
            try { $endInput.datepicker('hide'); } catch (_) {}
        }
        // Выделяем весь текст, чтобы новый ввод заменял предзаполненную дату
        try { this.select(); } catch (_) {}
    });

    // Маска ввода dd.mm.yyyy: авто-точки, только цифры, максимум 10 символов
    function formatToDateMask(raw) {
        const digits = (raw || '').replace(/\D/g, '').slice(0, 8); // максимум 8 цифр
        let out = '';
        if (digits.length <= 2) {
            out = digits;
        } else if (digits.length <= 4) {
            out = digits.slice(0, 2) + '.' + digits.slice(2);
        } else {
            out = digits.slice(0, 2) + '.' + digits.slice(2, 4) + '.' + digits.slice(4);
        }
        return out;
    }

    function attachDateInputMask($el, which) {
        // На ввод фильтруем и расставляем точки
        $el.on('input', function () {
            const masked = formatToDateMask($el.val());
            if ($el.val() !== masked) {
                $el.val(masked);
            }
        });

        // Ограничим клавиатурный ввод: цифры и служебные
        $el.on('keydown', function (e) {
            const allowedCtrl = [8, 9, 13, 27, 37, 38, 39, 40, 46]; // backspace, tab, enter, esc, arrows, delete
            const isCtrl = e.ctrlKey || e.metaKey;
            const isDigit = e.key >= '0' && e.key <= '9';
            // Если нажата цифра и весь текст выделен — очищаем поле, чтобы не дописывать к старой дате
            if (isDigit) {
                try {
                    const el = this;
                    const allSelected = typeof el.selectionStart === 'number' && typeof el.selectionEnd === 'number' 
                        && el.selectionStart === 0 && el.selectionEnd === (el.value ? el.value.length : 0);
                    if (allSelected) {
                        el.value = '';
                    }
                } catch (_) {}
                return;
            }
            if (allowedCtrl.includes(e.keyCode) || isCtrl) return;
            // Разрешим точку только если уже есть 1-2 сегмента и нет двойной точки подряд
            if (e.key === '.') return;
            e.preventDefault();
        });

        // При потере фокуса простая валидация + синк
        $el.on('blur', function () {
            const val = ($el.val() || '').trim();
            const re = /^\d{2}\.\d{2}\.\d{4}$/;
            if (val && !re.test(val)) {
                $el.addClass('is-invalid');
                return;
            }
            $el.removeClass('is-invalid');
            // Синхронизируем корректный ввод с пикером
            if (val) {
                if (which === 'start') syncManualInput($startInput, 'start'); else syncManualInput($endInput, 'end');
            }
        });
    }

    attachDateInputMask($startInput, 'start');
    attachDateInputMask($endInput, 'end');

    // Синхронизация ручного ввода с датапикером (c защитой от рекурсии)
    let isProgUpdateStart = false;
    let isProgUpdateEnd = false;

    function syncManualInput($el, which) {
        // Не реагируем на программные обновления
        if ((which === 'start' && isProgUpdateStart) || (which === 'end' && isProgUpdateEnd)) return;

        const val = ($el.val() || '').trim();
        const isValid = /^\d{2}\.\d{2}\.\d{4}$/.test(val);
        if (!isValid) return;

        // Ставим флаг, чтобы не ловить собственный change
        if (which === 'start') isProgUpdateStart = true; else isProgUpdateEnd = true;
        $el.datepicker('update', val);
        // Сбрасываем флаг после стека событий
        setTimeout(() => { if (which === 'start') isProgUpdateStart = false; else isProgUpdateEnd = false; }, 0);
    }
    $startInput.on('change', function () { syncManualInput($startInput, 'start'); });
    $endInput.on('change', function () { syncManualInput($endInput, 'end'); });

    // Установка дат по умолчанию
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    
    $startInput.datepicker('setDate', firstDayOfMonth);
    $endInput.datepicker('setDate', today);
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
        
        // Создаем контейнер для селекта и кнопки
        const inputGroup = document.createElement('div');
        inputGroup.className = 'position-relative mt-2';
        
        // Создаем выпадающий список
        const select = document.createElement('select');
        select.id = 'report-addresses';
        select.className = 'form-select';
        select.style.paddingRight = '35px'; // Добавляем отступ справа для кнопки очистки
        select.style.textOverflow = 'ellipsis'; // Добавляем троеточие для длинного текста
        select.style.overflow = 'hidden'; // Скрываем текст, который не помещается
        select.style.whiteSpace = 'nowrap'; // Запрещаем перенос текста
        
        // Создаем обертку для кастомного селекта
        const customSelectWrapper = document.createElement('div');
        customSelectWrapper.className = 'custom-select-wrapper';
        customSelectWrapper.id = 'custom-select-wrapper-report-addresses';
        
        const defaultOption = document.createElement('option');
        defaultOption.value = 'all_addresses';
        defaultOption.textContent = 'Все адреса';
        select.appendChild(defaultOption);
        
        // Создаем кнопку очистки
        const clearButton = document.createElement('button');
        clearButton.className = 'btn btn-link text-secondary';
        clearButton.type = 'button';
        clearButton.id = 'clear-address-btn';
        clearButton.innerHTML = '&times;';
        clearButton.title = 'Очистить выбор';
        clearButton.style.position = 'absolute';
        clearButton.style.right = '8px';
        clearButton.style.top = '50%';
        clearButton.style.transform = 'translateY(-50%)';
        clearButton.style.background = 'none';
        clearButton.style.border = 'none';
        clearButton.style.fontSize = '1.5em';
        clearButton.style.padding = '0 5px';
        clearButton.style.zIndex = '5';
        clearButton.style.lineHeight = '1';
        clearButton.style.textDecoration = 'none';
        
        // Добавляем обработчик для кнопки очистки
        clearButton.addEventListener('click', function() {
            select.value = 'all_addresses';
            // Триггерим событие change для обновления связанных обработчиков
            const event = new Event('change');
            select.dispatchEvent(event);
        });
        
        // Добавляем элементы в контейнер
        customSelectWrapper.appendChild(select);
        inputGroup.appendChild(customSelectWrapper);
        
        // Добавляем кнопку очистки
        const clearButtonWrapper = document.createElement('div');
        clearButtonWrapper.style.position = 'absolute';
        clearButtonWrapper.style.right = '10px';
        clearButtonWrapper.style.top = '50%';
        clearButtonWrapper.style.transform = 'translateY(-50%)';
        clearButtonWrapper.style.zIndex = '10';
        clearButtonWrapper.style.pointerEvents = 'none';
        
        clearButtonWrapper.appendChild(clearButton);
        customSelectWrapper.appendChild(clearButtonWrapper);
        
        // Разрешаем клики по кнопке
        clearButton.style.pointerEvents = 'auto';
        
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
        
        // Добавляем контейнер с селектом на страницу
        container.appendChild(inputGroup);
        
        // Инициализируем кастомный селект, если функция доступна
        if (typeof window.initCustomSelect === 'function') {
            window.initCustomSelect('report-addresses', 'Выберите из списка');
            
            // Добавляем обработчик для кнопки очистки после инициализации кастомного селекта
            setTimeout(() => {
                const customSelectInput = document.querySelector('#custom-select-wrapper-report-addresses .custom-select-input');
                if (customSelectInput) {
                    clearButton.addEventListener('click', function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        
                        // Сбрасываем значение селекта
                        select.selectedIndex = -1;
                        
                        // Обновляем отображение кастомного селекта
                        if (customSelectInput) {
                            customSelectInput.value = '';
                            customSelectInput.placeholder = 'Выберите из списка';
                            
                            // Сбрасываем стили выбранного элемента
                            const dropdown = customSelectInput.closest('.custom-select-wrapper');
                            if (dropdown) {
                                const options = dropdown.querySelectorAll('.custom-select-options li');
                                options.forEach(option => {
                                    option.classList.remove('active');
                                });
                                
                                // Скрываем выпадающий список, если открыт
                                const optionsList = dropdown.querySelector('.custom-select-options');
                                if (optionsList) {
                                    optionsList.style.display = 'none';
                                }
                            }
                        }
                        
                        // Триггерим событие change для обновления связанного функционала
                        const event = new Event('change', { bubbles: true });
                        select.dispatchEvent(event);
                    });
                }
            }, 100);
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

    // Сначала проверяем все случаи с allPeriod.checked
    if (allPeriod.checked) {
        startDate = null;
        endDate = null;
        
        // Отчет за ВЕСЬ ПЕРИОД по сотруднику
        if (employeeSelect.value > 0 && (addressSelect.value === 'all_addresses' || addressSelect.value === '')) {
            url = '/reports/requests/by-employee-all-period';
        } 
        // Отчет за ВЕСЬ ПЕРИОД по адресу
        else if (addressSelect.value > 0 && employeeSelect.value === 'all_employees') {
            url = '/reports/requests/by-address-all-period';
        }
        // Отчет за ВЕСЬ ПЕРИОД по сотруднику и адресу
        else if (employeeSelect.value > 0 && addressSelect.value > 0) {
            url = '/reports/requests/by-employee-address-all-period';
        }
        // Просто отчет за весь период (без фильтров)
        else {
            url = '/reports/requests/all-period';
        }
    }
    // Затем проверяем случаи с выбранным диапазоном дат
    else if (startDate && endDate) {
        if (employeeSelect.value === 'all_employees' && (addressSelect.value === 'all_addresses' || addressSelect.value === '')) {
            url = '/reports/requests/by-date';
        }
        else if (employeeSelect.value > 0 && (addressSelect.value === 'all_addresses' || addressSelect.value === '')) {
            url = '/reports/requests/by-employee-date';
        } 
        else if (addressSelect.value > 0 && employeeSelect.value === 'all_employees') {
            url = '/reports/requests/by-address-date';
        }
        else if (employeeSelect.value > 0 && addressSelect.value > 0) {
            url = '/reports/requests/by-employee-address-date';
        }
    }
    
    // Отчет за ВЕСЬ ПЕРИОД по адресу
    if (!startDate && !endDate && addressSelect.value > 0 && employeeSelect.value === 'all_employees' && allPeriod.checked) {
        url = '/reports/requests/by-address-all-period';
    }

    // Отчет за ВЕСЬ ПЕРИОД по сотруднику и адресу
    if (!startDate && !endDate &&  employeeSelect.value > 0 && addressSelect.value > 0 && allPeriod.checked) {
        url = '/reports/requests/by-employee-address-all-period';
    }

    // Отчет за ПЕРИОД по сотруднику и адресу
    if (startDate && endDate &&  employeeSelect.value > 0 && addressSelect.value > 0 && !allPeriod.checked) {
        url = '/reports/requests/by-employee-address-date';
    }
    
    console.log('Request URL:', url);

    // Формируем данные для запроса
    const requestData = { 
        startDate, 
        endDate, 
        employeeId: employeeSelect.value === 'all_employees' ? '' : employeeSelect.value,
        allPeriod: allPeriod.checked 
    };
    
    // Добавляем addressId только если он выбран
    if (addressSelect.value && addressSelect.value !== 'all_addresses') {
        requestData.addressId = addressSelect.value;
    }
    
    console.log('Данные для запроса:', requestData);

    // Показываем индикатор загрузки
    const tbody = document.querySelector('#requestsReportTable tbody');
    tbody.innerHTML = `
        <tr>
            <td colspan="100%" class="text-center" style="height: 200px; vertical-align: middle;">
                <div class="d-flex justify-content-center align-items-center w-100 h-100">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <span class="ms-3 fs-5"></span>
                </div>
            </td>
        </tr>`;
    
    const result = await postData(url, requestData);

    console.log('Result:', result);
    console.log('Brigade Members:', result.brigadeMembersWithDetails);

    if (result.success) {
        renderReportTable({
            requests: result.requestsByDateRange || result.requestsByEmployeeAndDateRange || result.requestsAllPeriodByEmployee || result.requestsAllPeriod || result.requestsByAddressAndDateRange || [],
            brigadeMembers: result.brigadeMembersWithDetails || [],
            comments_by_request: result.comments_by_request || result.commentsByRequest || {}
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
export function renderReportTable(data) {
    console.log('Полученные данные в data:', data);
    const exportBtn = document.getElementById('export-report-btn');

    // Нужно сохранить данные в localStorage
    // localStorage.setItem('reportData', JSON.stringify(data));

    // console.log('reportData:', localStorage.getItem('reportData'));

    if (data.requests.length === 0) {
        // showAlert('Нет данных для отображения', 'warning');
        exportBtn.classList.add('d-none');
    } else {
        exportBtn.classList.remove('d-none');
    }
    
    const tbody = document.getElementById('requestsReportBody');
    if (!tbody) {
        console.error('Table body not found. Looking for: #requestsReportBody');
        return;
    }

    // Очищаем таблицу
    tbody.innerHTML = '';

    // Определяем, пришёл ли массив или объект
    const responseData = Array.isArray(data) ? data[0] : data;

    console.log('Полученные данные в responseData:', responseData);
    
    // Проверяем данные (поддерживаем оба формата ответа)
    let requests, brigadeMembers, comments_by_request;
    
    // Новый формат ответа
    if (responseData.requestsByDateRange && Array.isArray(responseData.requestsByDateRange)) {
        requests = responseData.requestsByDateRange || [];
        brigadeMembers = responseData.brigadeMembersWithDetails || [];
        comments_by_request = responseData.comments_by_request || responseData.commentsByRequest || {};
    } 
    // Старый формат ответа
    else if (Array.isArray(responseData.requests)) {
        requests = responseData.requests || [];
        brigadeMembers = responseData.brigadeMembers || [];
        comments_by_request = responseData.comments_by_request || responseData.commentsByRequest || {};
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

    // console.log('Обработка запросов:', requests);
    // console.log('Данные бригад:', brigadeMembers);
    // console.log('Комментарии:', comments_by_request);

    // Add a row for each request   
    requests.forEach((request, index) => {
        const row = document.createElement('tr');
        row.id = `request-${request.id}`;
        row.className = 'align-middle status-row';

        // console.log('Обработка запроса:', request);
        
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
                    `<small class="d-block" 
                            data-bs-toggle="tooltip" 
                            title="ул. ${request.street}, д. ${request.houses || ''} (${request.district || ''})">
                        ${request.city_name && request.city_name !== 'Москва' ? 
                            `${request.city_name}, ` : ''}ул. ${request.street}, д. ${request.houses || ''}
                    </small>` : 
                    '<small class="d-block">Адрес не указан</small>'}
                ${request.client_fio ? 
                    `<div style="font-size: 0.8rem;"><i>${request.client_fio}</i></div>` : ''}
                <small style="font-size: 0.8rem;" 
                       class="d-block">
                    <i>${request.client_phone || 'Нет телефона'}</i>
                </small>
            `
            : '<small>Нет данных</small>';
        
        // Format brigade info for display
        let brigadeInfo = '';
        if (request.brigade_id) {
            // Find brigade members for this request
            const brigadeGroup = brigadeMembers.filter(m => m.brigade_id == request.brigade_id);
            console.log('Brigade group for request', request.id, ':', brigadeGroup);

            if (brigadeGroup.length > 0) {
                const brigade = brigadeGroup[0];
                const allMembers = brigadeGroup.map(m => m.employee_name).filter(name => name);
                const leaderName = brigadeGroup[0].employee_leader_name;
                const leaderShort = leaderName ? shortenName(leaderName) : '';
                const otherMembers = allMembers.filter(name => shortenName(name) !== leaderShort).map(shortenName);

                brigadeInfo = `
                    <div style="font-size: 0.75rem; line-height: 1.2;">
                        <div class="mb-1"><i>${brigade.brigade_name || 'Бригада'}</i></div>
                        ${leaderName ?
                            `<div><strong>${leaderShort}</strong>${otherMembers.length ? ', ' + otherMembers.join(', ') : ''}</div>` :
                            `<div>${allMembers.map(shortenName).join(', ')}</div>`
                        }
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

        const isAdmin = window.App.role == 'admin' ?? false;
        const statusName = request.status_name;

        // console.log('START reports');
        // console.log('isAdmin', isAdmin);
        // console.log('statusName', statusName);
        // console.log('========================');

        
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
                                <div style="color:rgb(66, 68, 69); font-size: 1.0em;">Добавил тест: ${comment.author_name}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
                
                <div class="mt-1">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                            data-bs-toggle="modal"
                            data-bs-target="#commentsModal"
                            data-request-id="${request.id}"
                            style="position: relative; z-index: 1;">
                        <i class="bi bi-chat-left-text me-1"></i>
                        <span class="text-comment">Комментарии</span>
                        <span class="badge bg-primary rounded-pill ms-1">
                            ${comments_by_request[request.id].length}
                        </span>
                    </button>

                    ${isAdmin && statusName == 'выполнена' ? `
                        <button data-request-id="${request.id}" type="button" style="min-width: 3rem;"
                                class="btn btn-sm btn-custom-green-dark p-1 open-additional-task-request-btn"
                                data-bs-toggle="tooltip"
                                data-bs-placement="right"
                                data-bs-title="Дополнительное задание">
                            <i class="bi bi-plus-circle"></i> 
                        </button>
                    ` : ''}
                </div>
                
                `;
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
                showAlert('Произошла ошибка при загрузке отчёта: ' + error, 'danger');
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
