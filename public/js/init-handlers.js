import { showAlert, postData } from './utils.js';
import { initReportHandlers } from './report-handler.js';
import { 
    initFormHandlers, 
    initEmployeeEditHandlers, 
    initSaveEmployeeChanges, 
    initEmployeeFilter,     
    initDeleteEmployee, 
    initDeleteMember,
    currentDateState,
    initAddPhotoReport,
    initAddressEditHandlers,
    initDeleteAddressHandlers,
    saveEmployeeChangesSystem,
    initPlanningRequestFormHandlers,
    initAddressEditButton
} from './form-handlers.js';
import { 
    initializePage, 
    initTooltips, 
    initRequestButtons,
    initAllCustomSelects,
    setupBrigadeAttachment,
    handlerCreateBrigade,
    hanlerAddToBrigade,
    handlerAddEmployee
} from './handler.js';

// Инициализация страницы при загрузке DOM
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM полностью загружен');
    initializePage();
    initFormHandlers();
    initEmployeeEditHandlers();
    initSaveEmployeeChanges();
    initEmployeeFilter();
    initDeleteEmployee();
    initReportHandlers();
    initAddressEditButton();
    initDeleteMember();
    initTooltips();
    saveEmployeeChangesSystem();
    initAddPhotoReport();
    initAddressEditHandlers();
    initDeleteAddressHandlers();
    initPlanningRequestFormHandlers();

    // Запускаем инициализацию кастомных селектов с задержкой
    setTimeout(() => {
        initAllCustomSelects();
    }, 1000); // Даем время на загрузку данных

    // Добавляем обработчик события открытия модального окна с формой создания заявки
    const newRequestModal = document.getElementById('newRequestModal');
    if (newRequestModal) {
        newRequestModal.addEventListener('shown.bs.modal', function() {
            // console.log('Модальное окно открыто, сбрасываем валидацию');

            // Сбрасываем валидацию оригинального селекта
            const addressSelect = document.getElementById('addresses_id');
            if (addressSelect) {
                addressSelect.classList.remove('is-invalid');
            }

            // Сбрасываем валидацию кастомного селекта
            const customSelects = document.querySelectorAll('.custom-select-wrapper');
            for (const wrapper of customSelects) {
                const input = wrapper.querySelector('.custom-select-input');
                if (input && input.placeholder === 'Выберите адрес из списка') {
                    if (wrapper.resetValidation && typeof wrapper.resetValidation === 'function') {
                        wrapper.resetValidation();
                    } else {
                        input.classList.remove('is-invalid');
                    }
                    break;
                }
            }

            // Сбрасываем валидацию поля комментария
            const commentField = document.getElementById('comment');
            if (commentField) {
                commentField.classList.remove('is-invalid');
            }

            // Сбрасываем класс was-validated у формы
            const form = document.getElementById('newRequestForm');
            if (form) {
                form.classList.remove('was-validated');
            }
        });
    }

    // Обработчик для кнопки "Назначить" в модальном окне назначения бригады
    const confirmAssignTeamBtn = document.getElementById('confirm-assign-team-btn');
    if (confirmAssignTeamBtn) {
        confirmAssignTeamBtn.addEventListener('click', async function() {
            const modal = document.getElementById('assign-team-modal');
            const requestId = modal.dataset.requestId;
            console.log('request_id =', requestId);

            const selectElement = document.getElementById('assign-team-select');
            const selectedTeamId = selectElement.value;

            if (!selectedTeamId) {
                showAlert('Бригада не выбрана!', 'warning');
                return;
            }

            const selectedTeamName = selectElement.options[selectElement.selectedIndex].text;
            const leaderId = selectedTeamId; // ID бригадира

            // Получаем ID бригады из атрибута data-brigade-id выбранной опции
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const brigade_id = selectedOption.getAttribute('data-brigade-id');

            if (!brigade_id) {
                console.error('Не найден ID бригады в выбранной опции');
                showAlert('Ошибка: Не найден ID бригады', 'danger');
                return;
            }

            const brigadeName = selectedTeamName;

            // console.log(`ID выбранной заявки: ${requestId}`);
            // console.log(`ID выбранного бригадира: ${leaderId}`);
            // console.log(`ID бригады: ${brigade_id}`);
            // console.log(`Название бригады: ${brigadeName}`);

            try {
                // Отправляем запрос на обновление заявки
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
                // window.location.reload();

                // Обновляем данные заявки без перезагрузки страницы
                updateRequest({
                    id: requestId,
                    brigade_id: brigade_id,
                    brigade_name: brigadeName,
                    brigadeMembers: updateData.data.brigadeMembers,
                    date: currentDateState.date // Add current date to request data
                });

            } catch (error) {
                console.error('Ошибка при прикреплении бригады:', error.message);
                if (typeof utils !== 'undefined' && typeof utils.alert === 'function') {
                    utils.alert(`Ошибка: ${error.message}`);
                } else {
                    showAlert(`Ошибка: ${error.message}`, 'danger');
                }
            }

            // Закрываем модальное окно
            const bsModal = bootstrap.Modal.getInstance(modal);
            bsModal.hide();
        });
    }

    function updateRequest(requestData) {
        console.log('Updating request with data:', requestData);
        const requestId = requestData.id; // Убираем префикс 'request-'
        console.log('Ищем строку с data-request-id:', requestId);
        
        // Ищем строку по атрибуту data-request-id
        const requestRow = document.querySelector(`[data-request-id="${requestId}"]`);
        
        // Если строка не найдена, возможно, она еще не загружена
        if (!requestRow) {
            console.log('Строка заявки не найдена в DOM. ID:', requestId);
            
            // Если это новая заявка, добавляем её в начало таблицы
            if (confirm('Заявка не найдена в текущем представлении. Хотите обновить страницу для отображения изменений?')) {
                window.location.reload();
            }
            return;
        }

        // Находим ячейку с бригадой
        console.log('Поиск ячейки бригады в строке:', requestRow);

        /*
<div data-name="brigadeMembers" class="col-brigade__div" style="font-size: 0.75rem; line-height: 1.2;">
                                    
        <div class="mb-1"><i>Бригада</i></div>
        <div><strong>Селиверстов А.</strong>
    , Марков А., Соловьев Н.</div>
<a href="#" class="text-black hover:text-gray-700 hover:underline view-brigade-btn" style="text-decoration: none; font-size: 0.75rem; 
line-height: 1.2; display: inline-block; margin-top: 10px;" onmouseover="this.style.textDecoration='underline'" 
onmouseout="this.style.textDecoration='none'" data-bs-toggle="modal" data-bs-target="#brigadeModal" data-brigade-id="148">
    подробнее...
</a>
</div>
        */
        
        // Пробуем разные возможные селекторы
        let brigadeCell = requestRow.querySelector('td[data-col="brigade"]') || 
                         requestRow.querySelector('.col-brigade') ||
                         requestRow.querySelector('td:nth-child(5)'); // 5-я колонка, если другие не сработают
        
        if (!brigadeCell) {
            console.error('Ячейка бригады не найдена. Доступные ячейки:');
            const allCells = requestRow.querySelectorAll('td');
            allCells.forEach((cell, index) => {
                console.log(`Ячейка ${index}:`, cell.outerHTML);
            });
            return;
        }
        
        console.log('Найдена ячейка бригады:', brigadeCell);

        try {
            // Функция для сокращения ФИО
            const shortenName = (fullName) => {
                if (!fullName) return '';
                const nameToProcess = typeof fullName === 'object' ? fullName.name || '' : String(fullName);
                const parts = nameToProcess.trim().split(/\s+/);
                if (parts.length < 2) return nameToProcess;
                const lastName = parts[0];
                const firstName = parts[1];
                return `${lastName} ${firstName.charAt(0)}.`;
            };

            let brigadeHtml = '';
            const brigade = requestData.brigadeMembers && requestData.brigadeMembers[0];

            if (brigade) {
                // Формируем HTML для бригадира
                brigadeHtml = `
                    <div data-name="brigadeMembers" class="col-brigade__div" style="font-size: 0.75rem; line-height: 1.2;">
                    <div class="mb-1"><i>${brigade.brigade_name}</i></div>
                    <div><strong>${shortenName(brigade.leader_fio)}</strong>`;

                // Добавляем участников бригады, если они есть
                if (brigade.members) {
                    const members = brigade.members.split(', ')
                        .map(member => `, ${shortenName(member)}`)
                        .join('');
                    brigadeHtml += members;
                }
                brigadeHtml += '</div>';

                // Добавляем кнопку "подробнее..."
                brigadeHtml += `
                <a href="#" class="text-black hover:text-gray-700 hover:underline view-brigade-btn"
                   style="text-decoration: none; font-size: 0.75rem; line-height: 1.2; display: inline-block; margin-top: 10px;"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'"
                   data-bs-toggle="modal"
                   data-bs-target="#brigadeModal"
                   data-brigade-id="${brigade.brigade_id || ''}">
                    подробнее...
                </a></div>`;
            } else {
                // Если данных о бригаде нет, отображаем только название
                brigadeHtml = requestData.brigade_name || requestData.brigade_id || '';
            }

            // Обновляем содержимое ячейки
            brigadeCell.innerHTML = brigadeHtml;
            
            // Обновляем data-атрибут, если он используется
            if (brigadeCell.hasAttribute('data-col-brigade-id')) {
                brigadeCell.setAttribute('data-col-brigade-id', requestData.brigade_id || '');
            }
            
            // Показываем уведомление об успешном обновлении
            showAlert(`Бригада успешно назначена на заявку #${requestData.id}`, 'success');
        } catch (error) {
            console.error('Ошибка при обновлении отображения бригады:', error);
            // В случае ошибки просто отображаем название бригады как есть
            brigadeCell.textContent = requestData.brigade_name || requestData.brigade_id || '';
            
            // Обновляем data-атрибут, если он используется
            if (brigadeCell.hasAttribute('data-col-brigade-id')) {
                brigadeCell.setAttribute('data-col-brigade-id', requestData.brigade_id || '');
            }
            
            showAlert('Ошибка при обновлении информации о бригаде', 'danger');
        }
    }

    // Инициализация кнопок заявок
    initRequestButtons();

    // Добавляем обработчик для вкладки заявок
    const requestsTab = document.getElementById('requests-tab');
    if (requestsTab) {
        requestsTab.addEventListener('click', async function () {
            // console.log('Вкладка "Заявки" была нажата');

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

                    // console.log('Список бригад обновлен');
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
    // Запускаем инициализацию с небольшой задержкой, чтобы убедиться, что DOM полностью загружен
    setTimeout(() => {
        // console.log('Вызов handlerCreateBrigade...');
        handlerCreateBrigade();
        // console.log('handlerCreateBrigade вызван');
        hanlerAddToBrigade();
        handlerAddEmployee();
    }, 100);
    // initUserSelection();

    // // Устанавливаем текущую дату + 1 день в формате YYYY-MM-DD
    // const today = new Date();
    // // Добавляем 1 день, чтобы компенсировать разницу в датах
    // today.setDate(today.getDate() + 1);
    // const dateStr = today.toISOString().split('T')[0];
    // const dateInput = document.getElementById('executionDate');
    // if (dateInput) {
    //     dateInput.value = dateStr;
    //     console.log('Установлена дата:', dateStr);
    // }

    // Получаем текущую дату с учетом локального времени
    const now = new Date();
    // Получаем смещение в минутах и конвертируем в миллисекунды
    const timezoneOffset = now.getTimezoneOffset() * 60000;
    // Вычитаем смещение, чтобы получить корректную локальную дату
    const localDate = new Date(now - timezoneOffset).toISOString().split('T')[0];
    const dateInput = document.getElementById('executionDate');
    if (dateInput) {
        dateInput.value = localDate;
        // console.log('Установлена локальная дата:', localDate);
    }
});
