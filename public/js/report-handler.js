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

// Функция для загрузки списка организаций
export async function loadOrganizationsForReport() {
    try {
        const response = await fetch('/reports/organizations');
        const data = await response.json();

        const container = document.getElementById('report-organizations-container');
        if (!container) return;
        
        // Очищаем контейнер
        container.innerHTML = '';

        // Создаем контейнер для селекта и кнопки
        const inputGroup = document.createElement('div');
        inputGroup.className = 'position-relative mt-2';

        const select = document.createElement('select');
        select.id = 'report-organizations';
        select.className = 'form-select';
        select.style.paddingRight = '35px';
        select.style.textOverflow = 'ellipsis';
        select.style.overflow = 'hidden';
        select.style.whiteSpace = 'nowrap';
        
        // Создаем обертку для кастомного селекта
        const customSelectWrapper = document.createElement('div');
        customSelectWrapper.className = 'custom-select-wrapper';
        customSelectWrapper.id = 'custom-select-wrapper-report-organizations';

        const defaultOption = document.createElement('option');
        defaultOption.value = 'all_organizations';
        defaultOption.textContent = 'Все организации';
        select.appendChild(defaultOption);
        
        // Создаем кнопку очистки
        const clearButton = document.createElement('button');
        clearButton.className = 'btn btn-link text-secondary';
        clearButton.type = 'button';
        clearButton.id = 'clear-organization-btn';
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
            select.value = 'all_organizations';
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
        
        clearButton.style.pointerEvents = 'auto';

        data.forEach(org => {
            const option = document.createElement('option');
            option.value = org.organization;
            option.textContent = org.organization;
            option.dataset.text = org.organization;
            select.appendChild(option);
        });

        container.appendChild(inputGroup);
        
        // Инициализируем кастомный селект
        if (typeof window.initCustomSelect === 'function') {
            window.initCustomSelect('report-organizations', 'Выберите организацию');
            
            // Добавляем обработчик для кнопки очистки после инициализации кастомного селекта
            setTimeout(() => {
                const customSelectInput = document.querySelector('#custom-select-wrapper-report-organizations .custom-select-input');
                if (customSelectInput) {
                    clearButton.addEventListener('click', function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        
                        select.selectedIndex = -1;
                        
                        if (customSelectInput) {
                            customSelectInput.value = '';
                            customSelectInput.placeholder = 'Выберите организацию';
                            
                            const dropdown = customSelectInput.closest('.custom-select-wrapper');
                            if (dropdown) {
                                const options = dropdown.querySelectorAll('.custom-select-options li');
                                options.forEach(option => {
                                    option.classList.remove('active');
                                });
                                
                                const optionsList = dropdown.querySelector('.custom-select-options');
                                if (optionsList) {
                                    optionsList.style.display = 'none';
                                }
                            }
                        }
                        
                        const event = new Event('change', { bubbles: true });
                        select.dispatchEvent(event);
                    });
                }
            }, 100);
        } else {
             select.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Ошибка при загрузке организаций:', error);
        showAlert('Не удалось загрузить список организаций', 'danger');
    }
}

// Функция для загрузки списка типов заявок
export async function loadRequestTypesForReport() {
    try {
        const response = await fetch('/request-types');
        const data = await response.json();

        const select = document.createElement('select');
        select.id = 'report-request-types';
        select.className = 'form-select';

        const defaultOption = document.createElement('option');
        defaultOption.value = 'all_request_types';
        defaultOption.textContent = 'Все типы заявок';
        select.appendChild(defaultOption);

        data.forEach(type => {
            const option = document.createElement('option');
            option.value = type.id;
            option.textContent = type.name;
            select.appendChild(option);
        });

        const container = document.getElementById('report-request-types-container');
        if (container) {
            container.appendChild(select);
        }
    } catch (error) {
        console.error('Ошибка при загрузке типов заявок:', error);
        showAlert('Не удалось загрузить список типов заявок', 'danger');
    }
}

// Pagination state
let currentReportPage = 1;
const reportLimit = 20;
let isLoading = false;
let totalReportPages = 1;

// Функция для загрузки отчёта
// changePage: 0 = сброс, 1 = вперед, -1 = назад, 'first' = начало, 'last' = конец
export async function loadReport(changePage = 0) {
    if (isLoading) return;

    let startDate = $('#datepicker-reports-start').datepicker('getFormattedDate');
    let endDate = $('#datepicker-reports-end').datepicker('getFormattedDate');
    const employeeSelect = document.getElementById('report-employees');
    const addressSelect = document.getElementById('report-addresses');
    const organizationSelect = document.getElementById('report-organizations');
    const requestTypeSelect = document.getElementById('report-request-types');
    const allPeriod = document.getElementById('report-all-period');
    let url = '';    
    
    if (!startDate || !endDate) {
        showAlert('Необходимо указать даты', 'warning');
        return;
    }

    if (changePage === 0 || changePage === 'first') {
        currentReportPage = 1;
    } else if (changePage === 'last') {
        currentReportPage = totalReportPages > 0 ? totalReportPages : 1;
    } else {
        currentReportPage += changePage;
        if (currentReportPage < 1) currentReportPage = 1;
        if (currentReportPage > totalReportPages && totalReportPages > 0) currentReportPage = totalReportPages;
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
        // Отчет за выбранный диапазон дат по всем сотрудникам и всем адресам
        if (employeeSelect.value === 'all_employees' && (addressSelect.value === 'all_addresses' || addressSelect.value === '')) {
            url = '/reports/requests/by-date';
        }
        // Отчет за выбранный диапазон дат по всем сотрудникам и определенному адресу
        else if (employeeSelect.value > 0 && (addressSelect.value === 'all_addresses' || addressSelect.value === '')) {
            url = '/reports/requests/by-employee-date';
        } 
        // Отчет за выбранный диапазон дат по определенному адресу и всем сотрудникам
        else if (addressSelect.value > 0 && employeeSelect.value === 'all_employees') {
            url = '/reports/requests/by-address-date';
        }
        // Отчет за выбранный диапазон дат по определенному адресу и определенному сотруднику
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
        allPeriod: allPeriod.checked,
        page: currentReportPage,
        limit: reportLimit
    };

    // Добавляем addressId только если он выбран
    if (addressSelect.value && addressSelect.value !== 'all_addresses') {
        requestData.addressId = addressSelect.value;
    }

    // Добавляем organization только если она выбрана
    if (organizationSelect.value && organizationSelect.value !== 'all_organizations') {
        requestData.organization = organizationSelect.value;
    }

    // Добавляем requestTypeId только если он выбран
    if (requestTypeSelect.value && requestTypeSelect.value !== 'all_request_types') {
        requestData.requestTypeId = requestTypeSelect.value;
    }
    
    console.log('Данные для запроса:', requestData);

    isLoading = true;
    updatePaginationControls(0, 0); // Disable controls while loading

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
    isLoading = false;

    console.log('Result:', result);
    // console.log('Brigade Members:', result.brigadeMembersWithDetails);

    if (result.success) {
        const requests = result.requestsByDateRange || result.requestsByEmployeeAndDateRange || result.requestsAllPeriodByEmployee || result.requestsAllPeriod || result.requestsByAddressAndDateRange || [];
        
        // Update total pages from server response
        if (result.total) {
            totalReportPages = Math.ceil(result.total / reportLimit);
        } else {
             // Fallback if total not present: if full page returned, assume there are more pages
            totalReportPages = (requests.length === reportLimit) ? currentReportPage + 1 : currentReportPage;
        }

        renderReportTable({
            requests: requests,
            brigadeMembers: result.brigadeMembersWithDetails || [],
            comments_by_request: result.comments_by_request || result.commentsByRequest || {}
        });

        // Update pagination based on results count
        updatePaginationControls(requests.length, totalReportPages);
    } else {
        renderReportTable({
            requests: [],
            brigadeMembers: [],
            comments_by_request: {}
        });
        updatePaginationControls(0, 0);
        showAlert(result.message || 'Ошибка при загрузке отчёта', 'danger');
    }
}

function updatePaginationControls(itemsCount, totalPages) {
    let paginationContainer = document.getElementById('report-pagination-container');
    
    // Если контейнера нет, создаем его
    if (!paginationContainer) {
        paginationContainer = document.createElement('div');
        paginationContainer.id = 'report-pagination-container';
        paginationContainer.className = 'd-flex justify-content-center align-items-center mt-3 mb-5 gap-2';
        
        paginationContainer.innerHTML = `
            <button id="report-first-btn" class="btn btn-outline-secondary" title="В начало">
                <i class="bi bi-chevron-double-left"></i>
            </button>
            <button id="report-prev-btn" class="btn btn-outline-secondary" title="Назад">
                <i class="bi bi-chevron-left"></i>
            </button>
            <span class="fw-bold mx-2" id="report-page-indicator" style="min-width: 80px; text-align: center;">Стр. 1</span>
            <button id="report-next-btn" class="btn btn-outline-secondary" title="Вперед">
                <i class="bi bi-chevron-right"></i>
            </button>
            <button id="report-last-btn" class="btn btn-outline-secondary" title="В конец">
                <i class="bi bi-chevron-double-right"></i>
            </button>
        `;
        
        const tableResponsive = document.querySelector('#requestsReportTable').closest('.table-responsive');
        if (tableResponsive) {
            tableResponsive.parentNode.insertBefore(paginationContainer, tableResponsive.nextSibling);
            
            document.getElementById('report-first-btn').addEventListener('click', () => {
                if (!isLoading && currentReportPage > 1) loadReport('first');
            });

            document.getElementById('report-prev-btn').addEventListener('click', () => {
                if (!isLoading && currentReportPage > 1) loadReport(-1);
            });
            
            document.getElementById('report-next-btn').addEventListener('click', () => {
                if (!isLoading && currentReportPage < totalReportPages) loadReport(1);
            });

            document.getElementById('report-last-btn').addEventListener('click', () => {
                if (!isLoading && currentReportPage < totalReportPages) loadReport('last');
            });
        }
    }

    const firstBtn = document.getElementById('report-first-btn');
    const prevBtn = document.getElementById('report-prev-btn');
    const nextBtn = document.getElementById('report-next-btn');
    const lastBtn = document.getElementById('report-last-btn');
    const pageIndicator = document.getElementById('report-page-indicator');

    if (pageIndicator) pageIndicator.textContent = `Стр. ${currentReportPage} из ${totalPages > 0 ? totalPages : '?'}`;

    if (firstBtn) firstBtn.disabled = isLoading || currentReportPage <= 1;
    if (prevBtn) prevBtn.disabled = isLoading || currentReportPage <= 1;
    
    if (nextBtn) nextBtn.disabled = isLoading || currentReportPage >= totalPages || itemsCount === 0;
    if (lastBtn) lastBtn.disabled = isLoading || currentReportPage >= totalPages || itemsCount === 0;
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

    // Всегда очищаем таблицу
    tbody.innerHTML = '';

    // Определяем, пришёл ли массив или объект
    const responseData = Array.isArray(data) ? data[0] : data;

    // console.log('Полученные данные в responseData:', responseData);
    
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

    // Если "Загрузить еще" контейнер остался от предыдущей реализации - удаляем его
    const oldLoadMore = document.getElementById('load-more-reports-container');
    if (oldLoadMore) oldLoadMore.remove();
    
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
        numberCell.className = 'text-center text-muted d-none d-md-table-cell';
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
        // addressCell.style.width = '14rem'; // Removed to match address-history styles
        // addressCell.style.maxWidth = '14rem';
        // addressCell.style.overflow = 'hidden';
        // addressCell.style.textOverflow = 'ellipsis';
        addressCell.innerHTML = addressHtml;
        row.appendChild(addressCell);
        
        // Комментарии
        const commentCell = document.createElement('td');
        commentCell.className = 'p-2'; // Match padding from address-history
        let commentHtml = '';

        const isAdmin = window.App.role == 'admin' ?? false;
        const statusName = request.status_name;

        // console.log('START reports');
        // console.log('isAdmin', isAdmin);
        // console.log('statusName', statusName);
        // console.log('========================');

        
        if (comments_by_request[request.id] && comments_by_request[request.id].length > 0) {
            commentHtml = `
                <div class="comment-preview" 
                     style="max-height: 250px; overflow-y: auto; font-size: 0.85rem; line-height: 1.3;">
                    ${comments_by_request[request.id].map(comment => {
                        const date = new Date(comment.created_at).toLocaleString('ru-RU');
                        return `
                            <div class="comment-item mb-2 p-2 bg-white border rounded shadow-sm">
                                <div class="mb-1 text-break">
                                    ${comment.comment || 'Система'}
                                </div>
                                <div class="d-flex justify-content-between text-muted border-top pt-1 mt-1" style="font-size: 0.7rem;">
                                    <span>${date}</span>
                                    <span>${comment.author_name}</span>
                                </div>
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
            commentHtml = '<span class="text-muted small fst-italic">Комментариев нет</span>';
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

        // Загрузка списка организаций
        await loadOrganizationsForReport();

        // Загрузка списка типов заявок
        await loadRequestTypesForReport();

        const employeeSelect = document.getElementById('report-employees');
        const addressSelect = document.getElementById('report-addresses');
        const organizationSelect = document.getElementById('report-organizations');
        const requestTypeSelect = document.getElementById('report-request-types');
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

        if (organizationSelect) {
            organizationSelect.addEventListener('change', () => {
                const selectedOrganization = organizationSelect.value;
                console.log('Selected organization:', selectedOrganization);

                // Сбрасываем чекбокс "За весь период"
                if (allPeriodCheckbox) {
                    // allPeriodCheckbox.checked = false;
                }
            });
        }

        if (requestTypeSelect) {
            requestTypeSelect.addEventListener('change', () => {
                const selectedRequestTypeId = requestTypeSelect.value;
                console.log('Selected request type ID:', selectedRequestTypeId);

                // Сбрасываем чекбокс "За весь период"
                if (allPeriodCheckbox) {
                    // allPeriodCheckbox.checked = false;
                }
            });
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
