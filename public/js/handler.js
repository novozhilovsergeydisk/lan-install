// Импорт необходимых функций из utils.js
import { postData, makeEscapedPreview, linkifyPreservingAnchors } from './utils.js';
import { initRequestInWorkHandlers } from './form-handlers.js';

// Делаем postData доступной глобально для обратной совместимости
window.postData = postData;

// Функция инициализации: применяет linkifyPreservingAnchors к серверно-рендеренным комментариям
export function linkifyRenderedComments(root = document) {
    try {
        const nodes = root.querySelectorAll('.comment-preview-text:not([data-linkified="1"])');
        nodes.forEach(node => {
            const originalHtml = node.innerHTML;
            const processed = linkifyPreservingAnchors(originalHtml);
            node.innerHTML = processed;
            node.setAttribute('data-linkified', '1');
        });
    } catch (e) {
        console.error('Ошибка при постобработке ссылок в комментариях:', e);
    }
}

// Делаем доступной глобально при необходимости ручного вызова после динамических обновлений
window.linkifyRenderedComments = linkifyRenderedComments;

// Авто-вызов после полной загрузки DOM
document.addEventListener('DOMContentLoaded', () => linkifyRenderedComments());

// Загружаем запланированные заявки
export async function loadPlanningRequests() {
    const container = document.getElementById('planning-container');
    if (!container) {
        console.debug('Контейнер для таблицы запланированных заявок не найден (возможно, страница/роль без планирования)');
        return;
    }

    // console.log('Запланированные заявки загружены test');

    try {
        // Показываем индикатор загрузки
        container.innerHTML = `
            <div class="d-flex justify-content-center m-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
            `;

        // Загружаем данные с сервера
        const response = await fetch('/get-planning-requests', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        } else {
            container.innerHTML = '';
        }

        const result = await response.json();
        // console.log('Ответ сервера списка запланированных заявок:', result);

        if (!result.success) {
            throw new Error(result.message || 'Ошибка при загрузке запланированных заявок');
        }
        
        // Находим таблицу и её тело
        // const table_old = container.closest('.table-responsive').querySelector('table');
        const table = document.getElementById('requestsPlanningTable');
        const tbody = table.querySelector('tbody');
        
        // Очищаем таблицу, кроме заголовка
        tbody.innerHTML = ''; 
        
        // Добавляем строки для каждой заявки
        result.data.planningRequests.forEach((request, index) => {
            // Парсим комментарии, если они есть
            let comments = [];
            try {
                comments = JSON.parse(request.comments || '[]');
            } catch (e) {
                console.error('Ошибка парсинга комментариев:', e);
            }

            const commentsContent = linkifyPreservingAnchors(comments[comments.length - 1].comment);
            const commentsCount = comments.length;
                
            
            // Форматируем комментарии для отображения
            const formattedComments = comments.length > 0 
                ? comments.map(c => `${c.author_fio || 'Неизвестный'}: ${c.comment || ''}`).join('<br>')
                : 'Нет комментариев';
            
            // console.log('Комментарии:', comments);
            // console.log('Комментарии форматированные:', formattedComments);
            // console.log(request);
            // Формируем адрес
            const cityPrefix = request.city_name && request.city_name !== 'Москва' ? `${request.city_name}, ` : '';
            const fullAddress = `${cityPrefix}ул. ${request.street || ''}, ${request.houses || ''}`.trim();
            
            // Создаем строку таблицы
            const row = document.createElement('tr');
            row.id = `request-${request.id}`;
            row.dataset.requestNumber = request.number || '';
            row.dataset.requestStatus = request.status_id || 'status';
            row.dataset.address = fullAddress;
            row.className = 'align-middle status-row welcome-blade';
            row.style.setProperty('--status-color', request.color || '#e2e0e6');
            row.style.setProperty('--bs-table-bg', request.color, 'important');
            row.style.backgroundColor = request.color;
            row.style.color = '#000000'; // Устанавливаем черный цвет текста
            row.innerHTML = `
                <td style="width: 5%">
                    <div class="form-check">
                        ${index + 1}
                    </div>
                </td>
                <td style="width: 35%">
                    <div class="d-flex flex-column">
                        <div class="text-dark col-address__organization">${request.organization || 'Не указан'}</div>
                        <small class="text-dark d-block col-address__street" data-bs-toggle="tooltip" data-bs-original-title="${fullAddress}">
                            <strong>${fullAddress}</strong>
                        </small>
                        <div class="text-dark font-size-0-8rem"><i>${request.fio || ''}</i></div>
                        <small class="text-black d-block font-size-0-8rem">
                            <i>${request.phone || 'Нет телефона'}</i>
                        </small>
                    </div>
                </td>
                <td style="width: 50%">
                    <div class="col-date__date">${request.request_date} | ${request.number}</div>
                    ${commentsContent.length > 0 ? `
                        <div class="comment-preview small text-dark" data-bs-toggle="tooltip">
                            <p class="comment-preview-title">Печатный комментарий:</p>
                            <div data-comment-request-id="${request.id}" class="comment-preview-text">${commentsContent}</div>
                        </div>
                        ${commentsCount >= 1 ? `
                            <div class="mb-0">
                                ${(() => { const p = makeEscapedPreview(commentsContent, 4); return `<p class="font-size-0-8rem mb-0 pt-1 ps-1 pe-1 last-comment">${comments[0].created_at} | ${comments[0].author_fio}<br>${p.html}${p.ellipsis}</p>`; })()}
                            </div>
                            <div class="mt-1">
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#commentsModal"
                                        data-request-id="${request.id}"
                                        style="position: relative; z-index: 1;">
                                    <i class="bi bi-chat-left-text me-1"></i><span class="text-comment"> </span>
                                    Все комментарии
                                    <span class="badge bg-primary rounded-pill ms-1">
                                        ${comments.length}
                                    </span>
                                </button>
                            </div>
                        ` : ''}
                    ` : '<div class="text-muted small">Нет комментариев</div>'}
                </td>
                <td style="width: 10%; vertical-align: top">
                    <div class="d-flex align-items-start mt-4">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary request-in-work" data-request-id="${request.id}">
                                <i class="bi bi-pencil-square"></i> В работу
                            </button>
                        </div>
                    </div>
                    <div class="d-flex align-items-start mt-4">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-danger request-delete" data-request-id="${request.id}">
                                <i class="bi bi-x-circle"></i> Удалить
                            </button>
                        </div>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
        
        // Инициализируем обработчики для кнопок "В работу" после загрузки заявок
        initRequestInWorkHandlers();
        
        // Инициализируем тултипы для комментариев
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    //     container.innerHTML += `
    //         <table class="table table-hover align-middle mb-0" style="margin-bottom: 0;">
    //             <thead class="bg-dark">
    //                 <tr>
    //                     <th>Заявка</th>
    //                     <th>Клиент</th>
    //                     <th>Адрес</th>
    //                     <th>Комментарии</th>
    //                     <th>Бригада</th>
    //                     <th>Действия</th>
    //                 </tr>
    //             </thead>
    //             <tbody>
    //                 <!-- Заполнение таблицы происходит динамически -->
    //             </tbody>
    //         </table>`;

    //     // Заполняем таблицу данными
    //     const tbody = container.querySelector('tbody');
    //     data.forEach(request => {
    //         const row = document.createElement('tr');
    //         row.innerHTML = `
    //             <td>${request.request}</td>
    //             <td>${request.client}</td>
    //             <td>${request.address}</td>
    //             <td>${request.comments}</td>
    //             <td>${request.brigade}</td>
    //             <td>
    //                 <button type="button" class="btn btn-primary btn-sm">Просмотр</button>
    //             </td>
    //         `;
    //         tbody.appendChild(row);
    //     });
    } catch (error) {
        console.error('Ошибка при загрузке запланированных заявок:', error);
        showAlert('Ошибка при загрузке запланированных заявок', 'danger');
    }
}
 

/**
 * Функция для загрузки и отображения списка адресов с пагинацией
 * @param {number} page - номер страницы
 * @param {number} perPage - количество элементов на странице
 */
export async function loadAddressesPaginated(page = 1, perPage = 30) {
    // Находим контейнер для таблицы адресов
    const container = document.getElementById('AllAddressesList');
    if (!container) {
        console.error('Контейнер для таблицы адресов не найден');
        return;
    }

    try {
        // Показываем индикатор загрузки
        container.innerHTML = `
            <div class="d-flex justify-content-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>`;

        // Загружаем данные с сервера
        const response = await fetch(`/api/addresses/paginated?page=${page}&per_page=${perPage}`);
        
        if (!response.ok) {
            throw new Error('Ошибка загрузки списка адресов');
        }
        
        const data = await response.json();
        
        // Делаем функцию глобально доступной
        window.sortTable = (columnIndex, isNumeric = false) => {
            const table = document.querySelector('#AllAddressesList table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Определяем направление сортировки
            const currentDir = table.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
            table.setAttribute('data-sort-dir', currentDir);
            table.setAttribute('data-sort-col', columnIndex);
            
            // Сортируем строки
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].textContent.trim().toLowerCase();
                const bText = b.cells[columnIndex].textContent.trim().toLowerCase();
                
                if (isNumeric) {
                    const aNum = parseFloat(aText) || 0;
                    const bNum = parseFloat(bText) || 0;
                    return currentDir === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                return currentDir === 'asc' 
                    ? aText.localeCompare(bText)
                    : bText.localeCompare(aText);
            });
            
            // Обновляем иконки сортировки
            document.querySelectorAll('th i.bi').forEach(icon => icon.remove());
            const sortIcon = document.createElement('i');
            sortIcon.className = `bi bi-arrow-${currentDir === 'asc' ? 'up' : 'down'}-short ms-1`;
            table.querySelector(`th:nth-child(${columnIndex + 1})`).appendChild(sortIcon);
            
            // Обновляем таблицу
            rows.forEach(row => tbody.appendChild(row));
        };
        
        // Контейнер уже объявлен в начале функции

        // Формируем HTML для таблицы адресов
        let html = `
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 10%; cursor: pointer;"> </th>
                        <th style="width: 10%; cursor: pointer;" onclick="sortTable(1)">Город <i class="bi"></i></th>
                        <th style="width: 27%; cursor: pointer;" onclick="sortTable(2)">Район <i class="bi"></i></th>
                        <th style="width: 30%; cursor: pointer;" onclick="sortTable(3)">Улица <i class="bi"></i></th>
                        <th style="width: 23%; cursor: pointer;">Дом <i class="bi"></i></th>
                    </tr>
                </thead>
                <tbody>`;

        // Добавляем строки с адресами
        data.data.forEach(address => {
            const createdAt = new Date(address.created_at).toLocaleDateString('ru-RU');
            const updatedAt = new Date(address.updated_at).toLocaleDateString('ru-RU');
            
            // Обрезаем длинные комментарии, чтобы не ломали таблицу
            const shortComments = address.comments && address.comments.length > 30 
                ? address.comments.substring(0, 30) + '...' 
                : address.comments || '-';
                
            html += `
                <tr data-address-id="${address.id}">
                    <td>
                        ${window.App?.user?.isAdmin ? `
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2 edit-address-btn me-1" data-address-id="${address.id}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 delete-address-btn me-1" data-address-id="${address.id}">
                                <i class="bi bi-trash"></i>
                            </button>
                        ` : ''}
                    </td>
                    <td>${address.city_name || '-'}</td>
                    <td>${address.district || '-'}</td>
                    <td>${address.street || '-'}</td>
                    <td>${address.houses || '-'}</td>
                </tr>`;
        });

        // Закрываем таблицу
        html += `
                </tbody>
            </table>`;

        // Добавляем пагинацию
        if (data.last_page > 1) {
            html += `
            <nav aria-label="Навигация по страницам">
                <ul class="pagination justify-content-center">
                    <li class="page-item ${data.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${data.current_page - 1}" ${data.current_page === 1 ? 'tabindex="-1" aria-disabled="true"' : ''}>Назад</a>
                    </li>`;

            // Показываем не более 5 страниц
            let startPage = Math.max(1, data.current_page - 2);
            let endPage = Math.min(data.last_page, startPage + 4);
            
            if (endPage - startPage < 4 && startPage > 1) {
                startPage = Math.max(1, endPage - 4);
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === data.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>`;
            }

            html += `
                    <li class="page-item ${data.current_page === data.last_page ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-page="${data.current_page + 1}" ${data.current_page === data.last_page ? 'tabindex="-1" aria-disabled="true"' : ''}>Вперед</a>
                    </li>
                </ul>
            </nav>`;
        }

        // Обновляем содержимое контейнера
        container.innerHTML = html;

        // Добавляем обработчики событий для пагинации
        container.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page);
                if (!isNaN(page) && page !== data.current_page) {
                    loadAddressesPaginated(page, perPage);
                }
            });
        });

    } catch (error) {
        console.error('Ошибка при загрузке адресов:', error);
        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                Не удалось загрузить список адресов. ${error.message}
            </div>`;
    }
}

/**
 * Функция для отображения информации о бригадах
 * @param {Object} data - данные о бригадах
 */
function displayBrigadeInfo(data) {
    // Ищем контейнер для отображения информации о бригадах
    const brigadeInfoContainer = document.getElementById('brigadeInfo');

    if (!brigadeInfoContainer) {
        console.error('Не найден контейнер для отображения информации о бригадах');
        return;
    }

    // Очищаем контейнер
    brigadeInfoContainer.innerHTML = '<h5>Информация о бригадах на текущий день</h5>';

    // Создаем контейнер для информации о бригадах
    const brigadeInfoDiv = document.createElement('div');
    brigadeInfoDiv.className = 'brigade-info-container mt-3';

    if (data.success && data.$brigadesInfoCurrentDay && data.$brigadesInfoCurrentDay.length > 0) {
        // Создаем карточки для каждой бригады
        data.$brigadesInfoCurrentDay.forEach(brigade => {
            // Парсим JSON-строки в объекты
            const brigadeId = brigade.brigade_id;

            // console.log(brigadeId);

            let leaderInfoObj = {};
            let membersArray = [];

            try {
                if (typeof brigade.leader_info === 'string') {
                    leaderInfoObj = JSON.parse(brigade.leader_info);
                } else if (typeof brigade.leader_info === 'object') {
                    leaderInfoObj = brigade.leader_info;
                }
            } catch (e) {
                console.error('Ошибка при парсинге данных бригадира:', e);
            }

            try {
                if (typeof brigade.members === 'string' && brigade.members) {
                    membersArray = JSON.parse(brigade.members);
                } else if (Array.isArray(brigade.members)) {
                    membersArray = brigade.members;
                }
            } catch (e) {
                console.error('Ошибка при парсинге данных участников:', e);
            }

            const brigadeCard = document.createElement('div');
            brigadeCard.className = 'card mb-3';
            brigadeCard.setAttribute('data-card-brigade-id', brigade.brigade_id);

            const cardHeader = document.createElement('div');
            cardHeader.innerHTML = `
                <h5 class="ms-1 mt-1">${brigade.brigade_name || 'Бригада без названия'}</h5>
            `;

            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';

            // Информация о бригадире
            const leaderInfo = document.createElement('div');
            leaderInfo.setAttribute('data-info', 'leader-info');
            // leaderInfo.className = 'mb-3';
            if (leaderInfoObj && Object.keys(leaderInfoObj).length > 0) {
                leaderInfo.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div><strong>${leaderInfoObj.fio || 'Не указано'}</strong></div>
                    </div>
                `;
            } else {
                leaderInfo.innerHTML = '<div class="alert alert-warning">Информация о бригадире отсутствует</div>';
            }

            // Список участников бригады
            const membersList = document.createElement('div');
            // membersList.innerHTML = '<h6>Состав бригады:</h6>';

            if (membersArray && membersArray.length > 0) {
                const membersTable = document.createElement('table');
                membersTable.className = 'table table-hover users-table mb-0';
                // Проверяем, добавлен ли уже стиль для заголовков таблицы
                if (!document.getElementById('brigade-table-dark-theme-style')) {
                    const style = document.createElement('style');
                    style.id = 'brigade-table-dark-theme-style';
                    style.textContent = ``;
                    document.head.appendChild(style);
                }

                membersTable.innerHTML = `
                    <tbody>
                        ${membersArray.map(member => `
                            <tr data-brigade-id="${brigadeId}" data-member-id="${member.id}">
                                <td>${member.fio || 'Не указано'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <div>
                        <button class="btn btn-sm btn-outline-danger delete-member-btn mt-2" 
                                data-brigade-id="${brigadeId}" 
                                data-employee-id="${leaderInfoObj.id}">
                            Удалить
                        </button>
                    </div>
                `;
                membersList.appendChild(membersTable);
            } else {
                membersList.innerHTML += `
                    <div class="alert alert-info">Бригадир сам является участником бригады</div>
                    <div>
                        <button class="btn btn-sm btn-outline-danger delete-member-btn mt-2" 
                                data-brigade-id="${brigadeId}" 
                                data-employee-id="${leaderInfoObj.id}">
                            Удалить
                        </button>
                    </div>
                `;
            }

            // Добавляем информацию в карточку
            cardBody.appendChild(leaderInfo);
            cardBody.appendChild(membersList);

            // Собираем карточку
            brigadeCard.appendChild(cardHeader);
            brigadeCard.appendChild(cardBody);

            // Добавляем карточку в контейнер
            brigadeInfoDiv.appendChild(brigadeCard);
        });
    } else {
        brigadeInfoDiv.innerHTML = '<div class="alert alert-info">На текущий день бригады не найдены</div>';
    }

    // Добавляем контейнер на страницу
    brigadeInfoContainer.appendChild(brigadeInfoDiv);
}

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
    // console.log('Обновление таблицы заявок...');

    // Если есть активная дата, используем её для фильтрации
    if (filterState.date) {
        applyFilters();
    } else {
        // Иначе просто перезагружаем страницу
        window.location.reload();
    }
};

// Функция для применения фильтров
async function applyFilters() {
    // Собираем все выбранные фильтры
    const activeFilters = {
        statuses: [...filterState.statuses],
        teams: [...filterState.teams],
        date: filterState.date
    };

    selectedDateState.updateDate(filterState.date);
    executionDateState.updateDate(filterState.date);

    // Если выбрана дата, делаем запрос на сервер
    if (filterState.date) {
        // Конвертируем дату из DD.MM.YYYY в YYYY-MM-DD
        const [day, month, year] = filterState.date.split('.');
        const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;

        console.log('formattedDate', formattedDate);

        const result = await fetch(`/brigades/date/${formattedDate}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const brigadeData = await result.json();

        console.log('localStorage.setItem', brigadeData.data);

        localStorage.setItem('brigadeMembersCurrentDayData', JSON.stringify(brigadeData.data));

        // Формируем URL с отформатированной датой
        const apiUrl = `/api/requests/date/${formattedDate}`;

        await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(async response => {
                const data = await response.json().catch(() => ({}));

                // console.log(data);

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
                console.log('Загрузка заявок для выбранной даты ', data);
                
                // Проверяем, что есть данные для сохранения
                if (data && data.data && Array.isArray(data.data)) {
                    // Сохраняем данные в localStorage
                    localStorage.setItem('requestsDataFilter', '[]');
                    localStorage.setItem('requestsData', JSON.stringify(data.data));
                    console.log('Данные заявок сохранены в localStorage:', data.data.length, 'записей');
                    
                    // Скрываем контейнер карты
                    const mapContainer = document.getElementById('map-content');
                    if (mapContainer) {
                        mapContainer.classList.add('hide-me');
                    }
                    
                    // Вызываем loadRequestsByDate для обновления данных на карте
                    if (typeof loadRequestsByDate === 'function') {
                        loadRequestsByDate(selectedDate);
                    }
                    
                    // Если функция initMapWithRequests доступна, вызываем её
                    if (typeof initMapWithRequests === 'function') {
                        initMapWithRequests();
                    }
                } else {
                    console.log('Нет данных о заявках для сохранения');
                }
                
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
                        // console.info('На выбранную дату заявки есть!');
                    } else {
                        // console.info('На выбранную дату заявок нет!');
                    }

                    const tbody = document.querySelector('table.table-hover tbody');
                    if (!tbody) {
                        console.error('Не найден элемент tbody для вставки данных');
                        return;
                    }

                    // Очищаем существующие строки
                    tbody.innerHTML = '';

                    // Добавляем новые строки с данными или сообщение об их отсутствии
                    if (Array.isArray(data.data) && data.data.length > 0) {
                        // Скрываем строку "Нет заявок для отображения", если она есть
                        const noRequestsRow = document.getElementById('no-requests-row');
                        if (noRequestsRow) {
                            noRequestsRow.classList.add('d-none');
                        }

                        data.data.forEach((request, index) => {
                            if (request.status_name === 'отменена') {
                                return;
                            }
                            
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

                            // Дебаг - выводим структуру объекта request
                            // console.log('Request object:', request);
                            // console.log('Brigade members:', request.brigade_members);
                            // console.log('Brigade leader name:', request.brigade_leader_name);
                            // console.log('Brigade lead:', request.brigade_lead);
                            // console.log('Employee leader name:', request.employee_leader_name);

                            // console.log(request.brigade_members);

                            if (request.brigade_members && request.brigade_members.length > 0) {
                                // Функция для сокращения ФИО до фамилии и первой буквы имени
                                const shortenName = (fullName) => {
                                    // Проверяем, что fullName существует и является строкой
                                    if (!fullName) return '';
                                    
                                    // Если fullName - это объект с полем name, используем его
                                    const nameToProcess = typeof fullName === 'object' ? fullName.name || '' : String(fullName);
                                    
                                    // Разбиваем строку на части по пробелам
                                    const parts = nameToProcess.trim().split(/\s+/);
                                    if (parts.length < 2) return nameToProcess;

                                    const lastName = parts[0];
                                    const firstName = parts[1];

                                    return `${lastName} ${firstName.charAt(0)}.`;
                                };

                                // Находим бригадира (первый элемент с полем employee_leader_name)
                                let leaderHtml = '';
                                let membersHtml = '';

                                // Проверяем, есть ли у нас данные о бригадире
                                if (request.brigade_leader_name) {
                                    // Выводим бригадира отдельно и выделенным
                                    leaderHtml = `
                                        <div class="mb-1"><i>${request.brigade_name}</i></div>
                                        <div><strong>${shortenName(request.brigade_leader_name)}</strong>
                                    `;
                                } else if (request.brigade_lead) {
                                    // Запасной вариант, если поле brigade_leader_name отсутствует
                                    leaderHtml = `<div><strong>${shortenName(request.brigade_lead)}</strong>`;
                                }

                                // Формируем список обычных сотрудников
                                membersHtml = request.brigade_members
                                    .map(member => {
                                        const memberName = member.name || member;
                                        return `, ${shortenName(memberName)}`;
                                    })
                                    .join('');

                                // Объединяем бригадира и сотрудников
                                brigadeMembers = leaderHtml + membersHtml + '</div>';

                                // Добавляем ссылку "подробнее..."
                                brigadeMembers += `
                                <a href="#" class="text-black hover:text-gray-700 hover:underline view-brigade-btn"
                                   style="text-decoration: none; font-size: 0.75rem; line-height: 1.2; display: inline-block; margin-top: 10px;"
                                   onmouseover="this.style.textDecoration='underline'"
                                   onmouseout="this.style.textDecoration='none'"
                                   data-bs-toggle="modal"
                                   data-bs-target="#brigadeModal"
                                   data-brigade-id="${request.brigade_id || ''}">
                                    подробнее...
                                </a>`;
                            }

                            const isAdmin = window.App.role == 'admin' ?? false;
                            const statusName = request.status_name;
                            const executionDate = request.execution_date;
                            const today = new Date().toISOString().split('T')[0];
                            // const showButton = statusName == 'выполнена' && isAdmin && isToday;

                            console.log('START');
                            console.log('executionDate', executionDate);
                            console.log('today', today);
                            // console.log('request.status_name', request.status_name);
                            console.log('statusName', statusName);
                            console.log('isAdmin', isAdmin);
                            
                            // console.log('showButton', showButton);
                            // console.log('window.App.role', window.App.role);
                            console.log('--------------------------');

                            // Создаем HTML строки таблицы
                            const row = document.createElement('tr');
                            row.id = `request-${request.id}`;
                            row.setAttribute('data-address', request.address || '');
                            row.setAttribute('data-request-number', request.number || '');
                            
                            row.className = 'align-middle status-row class-handler';
                            row.style.setProperty('--status-color', request.status_color || '#e2e0e6');
                            // Отладочный вывод
                            // Логи данных запроса отключены

                            row.setAttribute('data-request-id', request.id);

                            // console.log(request);

                            // Добавляем счетчик для нумерации строк (начинаем с 1)
                            const rowNumber = index + 1;

                            // console.log({ request });

                            // console.log(request.is_admin);
                            
                            // Подготовим HTML блока комментариев без IIFE
                            let commentsSectionHtml = '--';
                            if (request.comments) {
                                let firstCommentText = '';
                                
                                if (Array.isArray(request.comments) && request.comments.length > 0) {
                                    // console.log(typeof request.comments);
                                    const firstComment = request.comments[request.comments.length - 1];
                                    firstCommentText = firstComment.text || firstComment.comment || firstComment.content || JSON.stringify(firstComment);
                                }

                                if (firstCommentText) {
                                    const displayCommentText = linkifyPreservingAnchors(firstCommentText);
                                    const mainBlock = `
                                        <div class="comment-preview small text-dark" data-bs-toggle="tooltip">
                                            <p class="comment-preview-title">Печатный комментарий:</p>
                                            <div data-comment-request-id="${request.id}" class="comment-preview-text">${displayCommentText}</div>
                                        </div>`;

                                    let lastCommentHtml = '';
                                    if (Array.isArray(request.comments) && request.comments.length > 1) {
                                        const lastComment = request.comments[0];
                                        const lcText = lastComment.comment || lastComment.text || lastComment.content || '';
                                        const lcDate = lastComment.created_at ? new Date(lastComment.created_at).toLocaleString('ru-RU', {
                                            day: '2-digit',
                                            month: '2-digit',
                                            year: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                            hour12: false
                                        }).replace(',', '') : '';

                                        const p = makeEscapedPreview(lcText, 4);
                                        lastCommentHtml = `<div class="mb-0"><p class="font-size-0-8rem mb-0 pt-1 ps-1 pe-1 last-comment">[${lcDate}] ${p.html}${p.ellipsis}</p></div>`;
                                    }

                                    commentsSectionHtml = mainBlock + (lastCommentHtml || '');
                                }
                            }

                            row.innerHTML = `
                            <!-- Номер заявки -->
                            <td class=" col-number" style="width: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${rowNumber}</td>

                            <!-- Клиент -->
                            <td class="col-address" style_="width: 12rem; max-width: 12rem; overflow: hidden; text-overflow: ellipsis;">
                                <div class="text-dark col-address__organization" style_="font-size: 0.8rem;">${request.client_organization}</div>
                                <small class="text-dark d-block col-address__street" data-bs-toggle="tooltip" title="${request.address || address}">
                                    ${request.city_name && request.city_name !== 'Москва' ? `<strong>${request.city_name}</strong>, ` : ''}<strong>ул. ${request.address || address}</strong>
                                </small>
                                <div class="text-dark font-size-0-8rem"><i>${request.client_fio}</i></div>
                                <small class="d-block col-address__phone font-size-0-8rem">
                                    <i>${request.phone || request.client_phone || ''}</i>
                                </small>
                            </td>

                            <!-- Комментарий -->
                            <td class="col-comments" style_="commentsContainer">
                                <div class="col-date__date">${formattedDate} | ${requestNumber}</div>
                                ${commentsSectionHtml}
                                <!-- Кнопка комментариев -->
                                <div class="mt-1">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#commentsModal"
                                            data-request-id="${request.id}"
                                            style="position: relative; z-index: 1;"
                                            ${(request.comments_count > 0 || (request.comments && request.comments.length > 0)) ? '' : 'disabled'}>
                                        <i class="bi bi-chat-left-text me-1"></i>
                                        ${(request.comments_count > 0 || (request.comments && request.comments.length > 0)) ?
                                    `<span class="badge bg-primary rounded-pill ms-1">
                                                ${request.comments_count || (request.comments ? request.comments.length : 0)}
                                            </span>` :
                                    ''
                                }
                                    </button>
                                    ${request.status_name !== 'выполнена' && request.status_name !== 'отменена' ? `
                                        <button data-request-id="${request.id}" type="button" class="btn btn-sm btn-custom-brown p-1 close-request-btn">
                                            Закрыть заявку
                                        </button>
                                    ` : ''}
                                </div>
                            </td>

                            <!-- Состав бригады -->
                            <td class="col-brigade" data-col-brigade-id="${request.brigade_id}">
                                <div data-name="brigadeMembers" class="col-brigade__div" style="font-size: 0.75rem; line-height: 1.2;">
                                    ${brigadeMembers}
                                </div>
                            </td>

                            ${request.isAdmin ? `
                            <!-- Action Buttons Group -->
                            <td class="col-actions text-nowrap">
                                <div class="col-actions__div d-flex flex-column gap-1">
                                ${request.status_name !== 'выполнена' && request.status_name !== 'отменена' ? `
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary assign-team-btn p-1" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="left" 
                                            data-bs-title="Назначить бригаду"
                                            data-request-id="${request.id}">
                                        <i class="bi bi-people"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-success transfer-request-btn p-1" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="left" 
                                            data-bs-title="Перенести заявку"
                                            style="--bs-btn-color: #198754; --bs-btn-border-color: #198754; --bs-btn-hover-bg: rgba(25, 135, 84, 0.1); --bs-btn-hover-border-color: #198754;" 
                                            data-request-id="${request.id}">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger cancel-request-btn p-1" 
                                            data-bs-toggle="tooltip" 
                                            data-bs-placement="left" 
                                            data-bs-title="Отменить заявку"
                                            data-request-id="${request.id}">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-purple edit-request-btn p-1"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="left"
                                            data-bs-title="Редактировать заявку"
                                            data-request-id="${request.id}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                ` : ''}

                                ${isAdmin && statusName == 'выполнена' && (today === executionDate) ? `
                                    <button data-request-id="${request.id}" type="button"
                                            class="btn btn-sm btn-custom-green p-1 open-request-btn"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="left"
                                            data-bs-title="Открыть заявку">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                ` : ''}

                                ${isAdmin && statusName == 'выполнена' ? `
                                    <button data-request-id="${request.id}" type="button"
                                            class="btn btn-sm btn-custom-green-dark p-1 open-additional-task-request-btn"
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="left"
                                            data-bs-title="Дополнительное задание">
                                        <i class="bi bi-plus-circle"></i> 
                                    </button>
                                ` : ''}
                            </td>
                            ` : ''}
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
                    showAlert(`На выбранную дату заявок: ${data.count}`, 'success');
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

// ================================
// Управление статусами заявок
// ================================

/**
 * Загружает список статусов с сервера и отображает их в таблице
 */
function loadStatuses() {
    const tbody = document.getElementById('statusesList');
    if (!tbody) return;

    fetch('/statuses')
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <p class="text-muted mb-0">Статусы не найдены</p>
                        </td>
                    </tr>`;
                return;
            }

            data.forEach(status => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${status.id}</td>
                    <td>
                        <span class="badge" style="background-color: ${status.color};">
                            ${status.name}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div style="width: 20px; height: 20px; background-color: ${status.color}; border-radius: 4px; margin-right: 8px;"></div>
                            <span>${status.color}</span>
                        </div>
                    </td>
                    <td>${status.requests_count || 0}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary edit-status-btn me-1"
                                data-id="${status.id}"
                                data-name="${status.name}"
                                data-color="${status.color}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        ${status.requests_count == 0 ?
                            `<button class="btn btn-sm btn-outline-danger delete-status-btn"
                                    data-id="${status.id}">
                                <i class="bi bi-trash"></i>
                            </button>` :
                            `<button class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="bi bi-trash"></i>
                            </button>`
                        }
                    </td>`;
                tbody.appendChild(row);
            });

            // Добавляем обработчики для кнопок редактирования
            document.querySelectorAll('.edit-status-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const color = this.getAttribute('data-color');

                    document.getElementById('statusId').value = id;
                    document.getElementById('statusName').value = name;
                    document.getElementById('statusColor').value = color;

                    const modal = new bootstrap.Modal(document.getElementById('addStatusModal'));
                    document.querySelector('#addStatusModal .modal-title').textContent = 'Редактировать статус';
                    modal.show();
                });
            });

            // Добавляем обработчики для кнопок удаления
            document.querySelectorAll('.delete-status-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Вы уверены, что хотите удалить этот статус?')) {
                        const statusId = this.getAttribute('data-id');
                        deleteStatus(statusId);
                    }
                });
            });

        })
        .catch(error => {
            console.error('Ошибка при загрузке статусов:', error);
            showAlert('Не удалось загрузить список статусов', 'error');
        });
}

/**
 * Сохраняет статус (создает новый или обновляет существующий)
 */
function saveStatus() {
    const id = document.getElementById('statusId').value;
    const name = document.getElementById('statusName').value.trim();
    const color = document.getElementById('statusColor').value;

    if (!name) {
        showAlert('Пожалуйста, укажите название статуса', 'error');
        return;
    }

    const url = id ? `/statuses/${id}` : '/statuses';
    const method = id ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ name, color })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Статус успешно ${id ? 'обновлен' : 'добавлен'}`, 'success');
            loadStatuses();

            // Закрываем модальное окно
            const modal = bootstrap.Modal.getInstance(document.getElementById('addStatusModal'));
            modal.hide();
        } else {
            throw new Error(data.message || 'Произошла ошибка');
        }
    })
    .catch(error => {
        console.error('Ошибка при сохранении статуса:', error);
        showAlert(error.message || 'Не удалось сохранить статус', 'error');
    });
}

/**
 * Удаляет статус по ID
 * @param {string} id - ID статуса для удаления
 */
function deleteStatus(id) {
    fetch(`/statuses/${id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Статус успешно удален', 'success');
            loadStatuses();
        } else {
            throw new Error(data.message || 'Произошла ошибка');
        }
    })
    .catch(error => {
        console.error('Ошибка при удалении статуса:', error);
        showAlert(error.message || 'Не удалось удалить статус', 'error');
    });
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
    // console.log('Выбран статус с ID:', statusId);

    // Если нужно применить фильтр к таблице заявок, раскомментируйте следующую строку:
    // applyFilter('status', statusId === 'all' ? null : statusId);
}

// Функция для загрузки списка адресов
export function loadAddresses(selectId = 'addresses_id') {
    const selectElement = document.getElementById(selectId);
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
            selectElement.innerHTML = '<option value="" disabled selected>Выберите адрес!</option>';

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

            // Инициализируем кастомный селект с поиском после загрузки адресов
            // Используем функцию с повторными попытками
            function tryInitAddressesSelect(attempts = 0) {
                if (typeof window.initCustomSelect === 'function') {
                    // console.log('Инициализация кастомного селекта для выбора адреса в форме');
                    window.initCustomSelect(selectId, "Выберите адрес из списка");
                } else {
                    // console.log(`Попытка ${attempts + 1}: Функция initCustomSelect не найдена для ${selectId}, повторная попытка через 500мс`);
                    if (attempts < 5) { // Максимум 5 попыток
                        setTimeout(() => tryInitAddressesSelect(attempts + 1), 500);
                    } else {
                        console.error(`Не удалось найти функцию initCustomSelect для ${selectId} после 5 попыток`);
                    }
                }
            }

            // Запускаем инициализацию с небольшой задержкой, чтобы DOM успел обновиться
            setTimeout(() => tryInitAddressesSelect(), 200);
        })
        .catch(error => {
            console.error('Ошибка при загрузке адресов:', error);
            selectElement.innerHTML = originalInnerHTML;
            showAlert('Ошибка при загрузке списка адресов', 'danger');
        });
}

// Функция для загрузки списка адресов для заявок на планирование
// Функция для загрузки адресов в select для дополнительной формы
export async function loadAddressesForAdditional(selectId = 'additionalAddressesId', selectedValue = null) {
    const selectElement = document.getElementById(selectId);
    if (!selectElement) return;

    console.log('Загрузка адресов для дополнительной формы');

    try {
        const response = await fetch('/api/geo/addresses', {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });

        if (!response.ok) {
            throw new Error('Ошибка при загрузке адресов');
        }

        const addresses = await response.json();

        // Очищаем список и добавляем заглушку
        selectElement.innerHTML = '<option value="" disabled selected>Выберите адрес</option>';

        // Сортируем адреса по городу, району и улице
        addresses.sort((a, b) => {
            if (a.city !== b.city) return a.city.localeCompare(b.city);
            if (a.district !== b.district) return a.district.localeCompare(b.district);
            if (a.street !== b.street) return a.street.localeCompare(b.street);
            return a.houses.localeCompare(b.houses);
        });

        // Добавляем адреса в выпадающий список
        addresses.forEach(address => {
            const option = document.createElement('option');
            option.value = address.id;
            option.textContent = `${address.street}, ${address.houses} [${address.district}] [${address.city}]`;
            selectElement.appendChild(option);
        });

        // Устанавливаем selected value, если передан
        if (selectedValue) {
            selectElement.value = selectedValue;
        }

        // Инициализируем кастомный селект с поиском
        if (typeof window.initCustomSelect === 'function') {
            window.initCustomSelect(selectId, "Выберите адрес из списка");
        }

    } catch (error) {
        console.error('Ошибка при загрузке адресов для дополнительной формы:', error);
    }
}

export async function loadAddressesForPlanning() {
    const selectElement = document.getElementById('addressesPlanningRequest');
    if (!selectElement) {
        console.error('Элемент addressesPlanningRequest не найден');
        return;
    }

    // Показываем индикатор загрузки
    const originalInnerHTML = selectElement.innerHTML;
    selectElement.innerHTML = '<option value="" disabled selected>Загрузка адресов...</option>';

    try {
        const response = await fetch('/api/addresses');
        
        if (!response.ok) {
            throw new Error('Ошибка загрузки адресов');
        }

        const data = await response.json();
        // console.log('Получены данные с сервера:', data);
        
        // Проверяем структуру ответа
        let addresses = [];
        if (Array.isArray(data)) {
            // Если пришел массив, используем его как есть
            addresses = data;
            // console.log('Используем массив адресов из корня ответа');
        } else if (data.addresses && Array.isArray(data.addresses)) {
            // Если адреса в свойстве addresses
            addresses = data.addresses;
            // console.log('Используем массив адресов из свойства addresses');
        } else if (data.data && Array.isArray(data.data)) {
            // Если адреса в свойстве data (типично для пагинации)
            addresses = data.data;
            // console.log('Используем массив адресов из свойства data');
        } else {
            console.warn('Не удалось найти массив адресов в ответе сервера');
        }
        
        // console.log('Обработанные адреса:', addresses);

        // Очищаем список и добавляем заглушку
        selectElement.innerHTML = '<option value="" disabled selected>Выберите адрес</option>';

        // Добавляем адреса в выпадающий список
        addresses.forEach(address => {
            const option = document.createElement('option');
            option.value = address.id;
            // Формируем читаемый адрес
            const addressParts = [
                address.street + ', ' + address.houses,
                address.district ? `[${address.district}]` : '',
                address.city ? `[${address.city}]` : ''
            ].filter(Boolean).join(' ');
            
            option.textContent = addressParts;
            selectElement.appendChild(option);
        });

        // console.log(selectElement);

        // Обновляем кастомный селект, если он уже был инициализирован
        if (selectElement.classList.contains('custom-select-initialized')) {
            // Находим кастомный инпут и список опций
            const customInput = document.querySelector('#custom-select-wrapper-addressesPlanningRequest .custom-select-input');
            const customOptions = document.querySelector('#custom-select-wrapper-addressesPlanningRequest .custom-select-options');
            
            if (customInput && customOptions) {
                // Очищаем список опций
                customOptions.innerHTML = '';
                
                // Добавляем опции в кастомный селект
                const options = selectElement.querySelectorAll('option');
                options.forEach(option => {
                    const li = document.createElement('li');
                    li.textContent = option.textContent;
                    li.dataset.value = option.value;
                    li.addEventListener('click', () => {
                        selectElement.value = option.value;
                        customInput.value = option.textContent;
                        customOptions.style.display = 'none';
                    });
                    customOptions.appendChild(li);
                });
                
                // Обновляем плейсхолдер
                if (customInput.placeholder !== 'Выберите адрес из списка') {
                    customInput.placeholder = 'Выберите адрес из списка';
                }
            }
        } else if (typeof window.initCustomSelect === 'function') {
            // Инициализируем кастомный селект, если он еще не инициализирован
            window.initCustomSelect('addressesPlanningRequest', 'Выберите адрес из списка');
            selectElement.classList.add('custom-select-initialized');
        }

    } catch (error) {
        console.error('Ошибка при загрузке адресов:', error);
        selectElement.innerHTML = originalInnerHTML;
        showAlert('Не удалось загрузить список адресов', 'danger');
    }
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
    const leaderId = document.getElementById('brigadeLeader')?.value;

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

    // Скоупим выбор кнопки отправки только к формам/модалкам бригад,
    // чтобы не затрагивать кнопку выхода и другие submit-кнопки на странице
    const submitButton = document.querySelector('#brigadeModal button[type="submit"], #brigadeDetails button[type="submit"]');
    if (submitButton) {
        submitButton.disabled = !isValid;
    }

    return isValid;
}

// Функция для инициализации всех обработчиков при загрузке страницы
export function initializePage() {
    // Загружаем кнопки статусов
    loadStatusButtons();

    // Загружаем список адресов для выпадающего списка
    loadAddresses();
    
    // Загружаем список адресов с пагинацией в таблицу
    loadAddressesPaginated();

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

                        // Больше не обновляем select бригадира, так как теперь это скрытое поле
                        // Бригадир выбирается кликом по участнику в списке

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

                    // Загружаем информацию о бригадах
                    try {
                        const brigadeInfoResponse = await fetch('/api/brigades/info-current-day', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                date: document.querySelector('#datepicker').value
                            })
                        });

                        const brigadeInfoData = await brigadeInfoResponse.json();
                        console.log('Информация о бригадах:', brigadeInfoData);

                        // Очищаем контейнер для информации о бригадах
                        const brigadeInfoContainer = document.getElementById('brigadeInfo');
                        if (brigadeInfoContainer) {
                            brigadeInfoContainer.innerHTML = '';
                        }

                        // Отображаем информацию о бригадах на странице
                        displayBrigadeInfo(brigadeInfoData);
                    } catch (error) {
                        console.error('Ошибка при получении информации о бригадах:', error);
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
            })
            .on('changeDate', function (e) {
                // console.log('Изменение даты в календаре:', e.format('dd.mm.yyyy'));

                const selectedDate = e.format('dd.mm.yyyy');
                filterState.date = selectedDate;

                // Обновляем значение выбранной даты в selectedDateState используя метод updateDate
                if (window.selectedDateState && typeof window.selectedDateState.updateDate === 'function') {
                    window.selectedDateState.updateDate(selectedDate);
                } else if (window.selectedDateState) {
                    // Запасной вариант, если метод updateDate не доступен
                    window.selectedDateState.date = selectedDate;
                    // console.log('Выбрана дата в календаре (handler.js):', window.selectedDateState.date);
                }

                console.log('Выбрана дата в календаре (handler.js):', selectedDate);

                // Обновить список сотрудников для фильтрации заявок
                updateEmployeesFilter(selectedDate);    

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

    function updateEmployeesFilter(date) {
        // запрос к серверу (POST) для получения списка сотрудников за выбранный день
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : null;

        fetch('/api/employees/filter', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            },
            body: JSON.stringify({ date })
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                // обновление списка сотрудников в UI
                const employeesSelect = document.getElementById('employeeFilter');
                if (!employeesSelect) return;
                employeesSelect.innerHTML = '';

                // Опция "Все сотрудники"
                const allOpt = document.createElement('option');
                allOpt.value = '';
                allOpt.textContent = 'Все сотрудники';
                employeesSelect.appendChild(allOpt);

                data.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = employee.fio || employee.name || '';
                    employeesSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Ошибка загрузки сотрудников:', error);
            });
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

            // console.log('Классы контейнера:', statusButtonsContainer.className);

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
                            // console.log('Заявки по статусам:', data.requests);

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
                    // console.log('Запрос списка бригад...');
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

                    // console.log('Ответ сервера:', data.$leaders);

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
                // console.log('Фильтр по бригадам отключен');
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
                // console.log('Выбран бригадир с ID:', selectedLeaderId);
                // Здесь можно добавить логику фильтрации заявок по выбранному бригадиру
                // Например, отправить запрос на сервер или отфильтровать существующие данные
                // applyFilters();
            }
        });
    }
}

// Функция для отображения модального окна
function showModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error(`Модальное окно с ID ${modalId} не найдено`);
    }
}

// Загрузка списка бригад в выпадающий список
async function loadTeamsToSelect() {
    try {
        // Запрашиваем актуальный список бригад с сервера
        const response = await fetch('/api/brigades/current-day');
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Ошибка при загрузке списка бригад');
        }

        // console.log('Данные бригад успешно загружены', result);

        // Обновляем выпадающий список в модальном окне
        const selectElement = document.getElementById('assign-team-select');
        if (selectElement) {
            // Очищаем список, оставляя только первый элемент
            selectElement.innerHTML = '<option value="" selected>Выберите бригаду</option>';

            // Добавляем новые опции
            if (result.data && Array.isArray(result.data)) {
                result.data.forEach(brigade => {
                    const option = document.createElement('option');
                    option.value = brigade.leader_id;
                    option.textContent = `[${brigade.brigade_name}] [Бригадир: ${brigade.leader_name}]`;
                    option.setAttribute('data-brigade-id', brigade.brigade_id);
                    selectElement.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Ошибка при загрузке бригад:', error.message);
        showAlert(`Ошибка при загрузке бригад: ${error.message}`, 'danger');
    }
}

// Обработчик кнопки 'Назначить бригаду'
async function handleAssignTeam(button) {
    // console.log('handleAssignTeam');

    // Сохраняем ID заявки в data-атрибуте модального окна
    const requestId = button.dataset.requestId;
    // console.log(requestId);

    const assignTeamModal = document.getElementById('assign-team-modal');
    assignTeamModal.dataset.requestId = requestId;

    // Отображаем модальное окно
    showModal('assign-team-modal');

    // Загружаем список бригад
    await loadTeamsToSelect();
}

// Обработчик кнопки 'Перенести заявку'
function handleTransferRequest(button) {
    const requestId = button.dataset.requestId;
    // console.log('Перенос заявки:', requestId);

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
                        <div class="mb-3">
                            <input type="checkbox" id="transferToPlanning" name="transferToPlanning" class="form-check-input">
                            <label for="transferToPlanning" class="form-check-label">Перенести заявку в планирование</label>
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

    // Устанавливаем минимальную дату - сегодняшний день
    const dateInput = modalElement.querySelector('#transferDate');

    // Получаем текущую дату в локальном формате
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    const day = today.getDate();

    // Форматируем дату в формате YYYY-MM-DD для инпута типа date
    const todayFormatted = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    // Устанавливаем минимальную дату (сегодня)
    dateInput.min = todayFormatted;

    // Устанавливаем сегодняшнюю дату как значение по умолчанию
    dateInput.value = todayFormatted;

    // Добавляем обработчик изменения даты, чтобы проверить, что выбранная дата не раньше сегодняшней
    dateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const minDate = new Date(todayFormatted);
        minDate.setHours(0, 0, 0, 0);

        // Если выбранная дата раньше сегодняшней, сбрасываем на сегодняшнюю
        if (selectedDate < minDate) {
            this.value = todayFormatted;
            showAlert('Дата переноса не может быть раньше сегодняшнего дня', 'warning');
        }
    });

    // Обработчик подтверждения переноса
    modalElement.querySelector('#confirmTransfer').addEventListener('click', async () => {
        const selectedDate = dateInput.value;
        const reason = document.getElementById('transferReason').value.trim();
        const transferToPlanning = document.getElementById('transferToPlanning').checked;

        if (!selectedDate) {
            showAlert('Пожалуйста, выберите дату для переноса', 'info');
            return;
        }

        if (!reason) {
            showAlert('Пожалуйста, укажите причину переноса', 'info');
            return;
        }

        try {
            // Выводим в консоль отправляемые данные для отладки
            console.log('Отправка запроса на перенос заявки:', {
                request_id: requestId,
                new_date: selectedDate,
                reason: reason,
                transfer_to_planning: transferToPlanning
            });

            const response = await fetch('/api/requests/transfer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    request_id: requestId,
                    new_date: selectedDate,
                    reason: reason,
                    transfer_to_planning: transferToPlanning
                })
            });

            const result = await response.json();

            // Выводим ответ сервера в консоль
            console.log('Ответ сервера при переносе заявки:', result);

            // console.log(result);

            if (!result.isPlanning) {
                // Проверяем дату выполнения из ответа сервера
                if (result.execution_date) {
                    const serverDate = new Date(result.execution_date);
                    const currentDate = new Date();

                    // Сбрасываем время для корректного сравнения только дат
                    serverDate.setHours(0, 0, 0, 0);
                    currentDate.setHours(0, 0, 0, 0);

                    // console.log('Дата выполнения от сервера:', serverDate);
                    // console.log('Текущая дата:', currentDate);

                    if (serverDate < currentDate) {
                        showAlert('Внимание: Дата выполнения заявки уже прошла!', 'error');
                    } else if (serverDate.getTime() === currentDate.getTime()) {
                        showAlert('Заявка запланирована на сегодня', 'info');
                    } else {
                        // скрыть заявку, если она запланирована на будущее
                        showAlert('Заявка запланирована на будущее', 'info');
                        const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                        if (row) {
                            row.style.display = 'none';
                        }
                    }
                }
            }

            if (response.ok) {
                const isPlanning = result.isPlanning;
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);

                if (isPlanning) {
                    // row.style.setProperty('--status-color', '#BBDEFB');
                    // Полностью удаляем строку из DOM
                    row.remove();
                    showAlert('Заявка перенесена в раздел запланированных заявок', 'info');
                    loadPlanningRequests();
                } else {
                    // loadRequests();

                    showAlert('Заявка успешно перенесена', 'success');
                    // Обновляем цвет строки без перезагрузки
                    
                    if (row) {
                        row.style.setProperty('--status-color', '#BBDEFB');
                        // Обновляем текст статуса, если он отображается
                        const statusCell = row.querySelector('.status-badge');
                        if (statusCell) {
                            statusCell.textContent = 'перенесена';
                        }

                        // const executionDateFormated = DateFormated(result.execution_date);

                        const execDate = new Date(result.execution_date);
                        const [selDay, selMonth, selYear] = selectedDateState.date.split('.');
                        const selDate = new Date(selYear, selMonth - 1, selDay);

                        console.log('Дата выполнения:', execDate);
                        console.log('Выбранная дата:', selDate);

                        if (execDate < selDate) {
                            row.remove();
                            showAlert('Заявка скрыта!', 'info');
                        }

                        // Обновляем блок комментариев в модальном окне
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
                }
                
                // Закрываем модальное окно
                modal.hide();
                // Удаляем модальное окно из DOM
                modalElement.remove();
            } else {
                throw new Error(result.message || 'Ошибка при переносе заявки');
            }
        } catch (error) {
            // console.error('Ошибка при переносе заявки:', error);
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
    // console.log('Отмена заявки:', requestId);

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
            // console.log('Отправка запроса на отмену заявки:', { requestId, reason });

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
                // console.log('Заявка отменена:', result);

                // Обновляем интерфейс
                const row = document.querySelector(`tr[data-request-id="${requestId}"]`);
                if (row) {
                    // Обновляем статус
                    row.style.setProperty('--status-color', result.status_color);
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) {
                        statusCell.textContent = 'отменена';
                    }

                    // Обновляем кнопки
                    const buttonsContainer = row.querySelector('.btn-group');
                    if (buttonsContainer) {
                        buttonsContainer.innerHTML = `

                        `;
                    }
                }

                // Находим строку заявки
                const requestRow = document.querySelector(`tr[data-request-id="${requestId}"]`);

                console.log('Заявка отменена:', requestRow);

                if (requestRow) {
                    // Скрыть саму строку

                    console.log('Заявка отменена', requestRow.dataset.requestId);

                    // requestRow.style.display = 'none';

                    // удаляем строку из DOM
                    requestRow.remove();

                    // Перенумерация только оставшихся видимых строк
                    const rows = Array.from(document.querySelectorAll('tr[data-request-id]'))
                    .filter(r => r.offsetParent !== null);

                    rows.forEach((row, index) => {
                    const numCell = row.querySelector('td.col-number') || row.querySelector('td:first-child');
                    if (numCell) numCell.textContent = index + 1;
                    });

                    // Если строк не осталось — показать строку-заглушку (если используется)
                    const tbody = document.querySelector('#requestsTable tbody');
                    const noRow = document.getElementById('no-requests-row');
                    if (tbody && noRow) {
                    if (rows.length === 0) noRow.classList.remove('d-none');
                    }

                    // // Скрываем кнопки в первом блоке (Назначить бригаду, Перенести, Отменить)
                    // const firstActionBlock = requestRow.querySelector('td.text-nowrap .d-flex.flex-column.gap-1');
                    // if (firstActionBlock) {
                    //     const buttonsToHide = firstActionBlock.querySelectorAll('button');
                    //     buttonsToHide.forEach(button => {
                    //         button.style.display = 'none';
                    //     });

                    //     // Добавляем текст "Заявка отменена"
                    //     const statusText = document.createElement('div');
                    //     statusText.className = 'text-muted small';
                    //     statusText.textContent = 'Заявка отменена';
                    //     firstActionBlock.appendChild(statusText);
                    // }

                    // // Скрываем кнопки во втором блоке, кроме кнопки Фотоотчет
                    // const secondActionBlock = requestRow.querySelectorAll('td.text-nowrap .d-flex.flex-column.gap-1')[1];
                    // if (secondActionBlock) {
                    //     const buttonsToHide = secondActionBlock.querySelectorAll('button:not(.add-photo-btn)');
                    //     buttonsToHide.forEach(button => {
                    //         button.style.display = 'none';
                    //     });
                    // }
                }

                // Обновляем счетчик заявок, если он есть
                // updateRequestsCount();

            } else {
                throw new Error(result.message || 'Ошибка при отмене заявки');
            }

        } catch (error) {
            // console.error('Ошибка при отмене заявки:', error);
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
export function initRequestButtons() {
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

// Глобальная функция для инициализации кастомного выпадающего списка
// Явно добавляем в глобальный объект window
window.initCustomSelect = function(selectId, placeholder = "Выберите из списка") {
    // console.log(`Инициализация initCustomSelect для ${selectId}...`);

    const originalSelect = document.getElementById(selectId);
    // console.log('Оригинальный select:', originalSelect);

    if (!originalSelect) {
        console.error(`Не найден элемент с id ${selectId}`);
        return;
    }

    // Проверяем, не был ли уже инициализирован кастомный селект
    if (originalSelect.getAttribute('data-custom-initialized') === 'true') {
        // Удаляем старый кастомный селект, если он существует
        const oldWrapper = document.getElementById(`custom-select-wrapper-${selectId}`);
        if (oldWrapper) {
            // console.log(`Удаляем старый кастомный селект для ${selectId}`);
            oldWrapper.remove();
        }
    }

    const wrapper = document.createElement("div");
    wrapper.className = "custom-select-wrapper";
    wrapper.id = `custom-select-wrapper-${selectId}`;
    // console.log('Создан wrapper:', wrapper);

    const input = document.createElement("input");
    input.type = "text";
    input.id = "custom-select-input";
    input.className = "custom-select-input";
    input.placeholder = placeholder;
    input.readOnly = false; // Разрешаем редактировать для поиска

    // Надежное отключение автозаполнения браузера
    input.setAttribute('autocomplete', 'nope');
    input.setAttribute('autocorrect', 'off');
    input.setAttribute('autocapitalize', 'off');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('data-form-type', 'search');
    input.name = 'search-' + Math.random().toString(36).substr(2, 9); // Уникальное имя для предотвращения автозаполнения

    // Проверяем, что атрибуты установлены
    // console.log('Создан input с атрибутами:', {
    //     autocomplete: input.getAttribute('autocomplete'),
    //     autocorrect: input.getAttribute('autocorrect'),
    //     autocapitalize: input.getAttribute('autocapitalize'),
    //     spellcheck: input.getAttribute('spellcheck')
    // });

    // console.log('Создан input:', input);

    const optionsList = document.createElement("ul");
    optionsList.className = "custom-select-options";
    // console.log('Создан optionsList:', optionsList);

    // Скрыть оригинальный select
    originalSelect.style.display = "none";
    originalSelect.setAttribute('data-custom-initialized', 'true');
    originalSelect.parentNode.insertBefore(wrapper, originalSelect);
    wrapper.appendChild(input);
    wrapper.appendChild(optionsList);

    // Находим элемент invalid-feedback и добавляем его в кастомный селект
    let feedbackElement = null;
    const selectParent = originalSelect.parentElement;
    if (selectParent) {
        const feedback = selectParent.querySelector('.invalid-feedback');
        if (feedback) {
            feedbackElement = feedback.cloneNode(true); // Клонируем элемент с сообщением об ошибке
            feedbackElement.style.display = 'none'; // Скрываем по умолчанию
            wrapper.appendChild(feedbackElement);
        }
    }

    // console.log('Элементы добавлены в DOM');

    // Собрать все опции
    const options = Array.from(originalSelect.querySelectorAll("option")).filter(
      option => option.value !== ""
    );
    // console.log('Найдено опций:', options.length);

    // Заполнить список
    function populateList(filter = "") {
      // console.log('populateList вызвана для', selectId, 'с фильтром:', filter);
      optionsList.innerHTML = "";
      const filtered = options.filter(option =>
        option.textContent.toLowerCase().includes(filter.toLowerCase())
      );
      // console.log('Отфильтровано опций:', filtered.length);

      if (filtered.length === 0) {
        const li = document.createElement("li");
        li.textContent = "Ничего не найдено";
        li.disabled = true;
        li.style.color = "gray";
        optionsList.appendChild(li);
      } else {
        filtered.forEach(option => {
          const li = document.createElement("li");
          li.textContent = option.textContent;
          li.dataset.value = option.value;
          li.addEventListener("click", () => {
            // console.log('Выбран элемент:', option.textContent, option.value);
            input.value = option.textContent;
            originalSelect.value = option.value;
            originalSelect.dispatchEvent(new Event("change"));
            optionsList.style.display = "none";

            // Сбрасываем валидацию при выборе адреса
            input.classList.remove('is-invalid');
            originalSelect.classList.remove('is-invalid');
            if (feedbackElement && feedbackElement.parentNode === wrapper) {
              feedbackElement.style.display = 'none';
            }

            // Проверяем валидность при выборе элемента
            validateInput(false);
          });
          optionsList.appendChild(li);
        });
      }
    }

    // Открытие/закрытие
    input.addEventListener("click", (e) => {
      // console.log('Клик по input для', selectId);
      if (optionsList.style.display === "block") {
        // console.log('Закрываем список');
        optionsList.style.display = "none";
      } else {
        // console.log('Открываем список');
        populateList(""); // Показываем все адреса при клике, без фильтра
        optionsList.style.display = "block";
      }
      e.stopPropagation(); // Предотвращаем всплытие события
    });

    // Фильтрация при вводе
    input.addEventListener("input", () => {
      // console.log('Ввод текста:', input.value);
      populateList(input.value);
      optionsList.style.display = "block";

      // Проверяем, соответствует ли введенный текст какому-либо из вариантов
      validateInput(false);
    });

    // Переменная feedbackElement уже определена выше

    // Функция проверки введенного значения
    function validateInput(applyStyle = false) {
      // Проверяем, выбрано ли значение из списка
      const isValid = originalSelect.value !== "" && originalSelect.selectedIndex > 0;

      if (applyStyle) {
        if (isValid) {
          // Если выбрано значение из списка, убираем красную подсветку
          input.classList.remove('is-invalid');
          // Скрываем сообщение об ошибке, если оно есть
          if (feedbackElement && feedbackElement.parentNode === wrapper) {
            feedbackElement.style.display = 'none';
          }
        } else {
          // Если не выбрано значение из списка, добавляем красную подсветку
          input.classList.add('is-invalid');
          // Показываем сообщение об ошибке, если оно есть
          if (feedbackElement && feedbackElement.parentNode === wrapper) {
            feedbackElement.style.display = 'block';
          }
        }
      }

      return isValid;
    }

    // Закрытие при клике вне
    document.addEventListener("click", function (e) {
      if (!wrapper.contains(e.target)) {
        // console.log('Клик вне кастомного селекта, закрываем');
        optionsList.style.display = "none";

        // Проверяем валидность при клике вне селекта
        validateInput(false);
      }
    });

    // Обновление поля при изменении оригинального select
    originalSelect.addEventListener("change", () => {
      // console.log('Изменение оригинального select');
      const selected = originalSelect.options[originalSelect.selectedIndex];
      input.value = selected ? selected.text : "";
      // console.log('Установлено значение:', input.value);

      // Проверяем валидность при изменении оригинального select
      validateInput(false);
    });

    // Инициализация
    // console.log('Запуск первичного заполнения списка');
    populateList();

    // Если в оригинальном селекте уже есть выбранное значение, отобразим его
    if (originalSelect.selectedIndex > 0) {
        const selected = originalSelect.options[originalSelect.selectedIndex];
        input.value = selected ? selected.text : "";
    }
    // Добавляем метод для внешней валидации
    wrapper.validate = function(applyStyle = true) {
      return validateInput(applyStyle);
    };

    // Добавляем метод для сброса валидации (убираем красную подсветку)
    wrapper.resetValidation = function() {
      input.classList.remove('is-invalid');
      if (feedbackElement && feedbackElement.parentNode === wrapper) {
        feedbackElement.style.display = 'none';
      }
    };

    return wrapper;
}

// Функция для инициализации всех селектов с поиском
export function initAllCustomSelects() {
    // console.log('Инициализация всех кастомных селектов');

    // Инициализируем селект с адресами в основном списке, если он еще не инициализирован
    const addressSelect = document.getElementById('addressSelect');
    if (addressSelect && !addressSelect.classList.contains('custom-select-initialized')) {
        initCustomSelect("addressSelect", "Выберите адрес из списка");
    }

    // Инициализируем селект с адресами в форме создания заявки, если он еще не инициализирован
    const addressesId = document.getElementById('addresses_id');
    if (addressesId && !addressesId.classList.contains('custom-select-initialized')) {
        initCustomSelect("addresses_id", "Выберите адрес из списка");
    }

    // Инициализируем селект с адресами для планирования, если он еще не инициализирован
    const addressesPlanningRequest = document.getElementById('addressesPlanningRequest');
    if (addressesPlanningRequest && !addressesPlanningRequest.classList.contains('custom-select-initialized')) {
        initCustomSelect("addressesPlanningRequest", "Выберите адрес из списка");
    }

    // Здесь можно добавить инициализацию других селектов
    // Например: initCustomSelect("otherSelect", "Выберите значение");
}

function autoFillEmployeeForm() {
    document.getElementById('autoFillBtn').addEventListener('click', function(e) {
        e.preventDefault();

        // 20 вариантов тестовых данных
        const mockDataArray = [
            {
                fio: "Лавров Иван Федорович", phone: "+7 (912) 345-67-89",
                birth_date: "1990-05-15", birth_place: "г. Москва",
                passport_series: "4510 123456", passport_issued_by: "ОУФМС России по г. Москве",
                passport_issued_at: "2015-06-20", passport_department_code: "770-123",
                car_brand: "Toyota Camry", car_plate: "А123БВ777",
                registration_place: "г. Москва, ул. Ленина, д. 15"
            },
            {
                fio: "Петров Алексей Романович", phone: "+7 (923) 456-78-90",
                birth_date: "1985-08-22", birth_place: "г. Санкт-Петербург",
                passport_series: "4012 654321", passport_issued_by: "ГУ МВД по СПб и ЛО",
                passport_issued_at: "2018-03-15", passport_department_code: "780-456",
                car_brand: "Hyundai Solaris", car_plate: "В987СН178",
                registration_place: "г. Санкт-Петербург, ул. Гагарина, д. 87"
            },
            {
                fio: "Сидоров Михаил Александрович", phone: "+7 (934) 567-89-01",
                birth_date: "1995-02-10", birth_place: "г. Екатеринбург",
                passport_series: "4603 789012", passport_issued_by: "УМВД по Свердловской области",
                passport_issued_at: "2017-11-30", passport_department_code: "660-789",
                car_brand: "Kia Rio", car_plate: "Е456КХ123",
                registration_place: "г. Екатеринбург, ул. Мира, д. 34"
            },
            {
                fio: "Кузнецов Дмитрий Сергеевич", phone: "+7 (945) 678-90-12",
                birth_date: "1988-07-14", birth_place: "г. Новосибирск",
                passport_series: "5401 345678", passport_issued_by: "ГУ МВД по Новосибирской области",
                passport_issued_at: "2019-04-25", passport_department_code: "540-234",
                car_brand: "Volkswagen Polo", car_plate: "Н543ТУ777",
                registration_place: "г. Новосибирск, ул. Советская, д. 12"
            },
            {
                fio: "Смирнов Олег Владимирович", phone: "+7 (956) 789-01-23",
                birth_date: "1992-12-05", birth_place: "г. Казань",
                passport_series: "9204 567890", passport_issued_by: "МВД по Республике Татарстан",
                passport_issued_at: "2016-09-18", passport_department_code: "160-567",
                car_brand: "Lada Vesta", car_plate: "У321ХС123",
                registration_place: "г. Казань, ул. Чехова, д. 55"
            },
            {
                fio: "Васильев Артем Игоревич", phone: "+7 (967) 890-12-34",
                birth_date: "1993-04-30", birth_place: "г. Нижний Новгород",
                passport_series: "5205 901234", passport_issued_by: "ГУ МВД по Нижегородской области",
                passport_issued_at: "2020-01-12", passport_department_code: "520-890",
                car_brand: "Skoda Rapid", car_plate: "В654АС321",
                registration_place: "г. Нижний Новгород, ул. Пушкина, д. 22"
            },
            {
                fio: "Иванов Андрей Николаевич", phone: "+7 (978) 901-23-45",
                birth_date: "1987-03-11", birth_place: "г. Самара",
                passport_series: "3802 456789", passport_issued_by: "УМВД по Самарской области",
                passport_issued_at: "2014-08-05", passport_department_code: "630-111",
                car_brand: "Ford Focus", car_plate: "К876МО63",
                registration_place: "г. Самара, ул. Карла Маркса, д. 47"
            },
            {
                fio: "Николаев Сергей Петрович", phone: "+7 (989) 012-34-56",
                birth_date: "1991-11-25", birth_place: "г. Уфа",
                passport_series: "8904 321654", passport_issued_by: "МВД по Республике Башкортостан",
                passport_issued_at: "2017-02-14", passport_department_code: "800-222",
                car_brand: "Renault Logan", car_plate: "Р432КР102",
                registration_place: "г. Уфа, ул. Ленина, д. 88"
            },
            {
                fio: "Федоров Максим Юрьевич", phone: "+7 (900) 123-45-67",
                birth_date: "1989-09-07", birth_place: "г. Красноярск",
                passport_series: "6603 987654", passport_issued_by: "ГУ МВД по Красноярскому краю",
                passport_issued_at: "2016-12-25", passport_department_code: "760-333",
                car_brand: "Nissan Qashqai", car_plate: "К789КР24",
                registration_place: "г. Красноярск, ул. Суворова, д. 101"
            },
            {
                fio: "Андреев Денис Викторович", phone: "+7 (901) 234-56-78",
                birth_date: "1994-06-18", birth_place: "г. Пермь",
                passport_series: "5901 654321", passport_issued_by: "УМВД по Пермскому краю",
                passport_issued_at: "2019-07-10", passport_department_code: "590-444",
                car_brand: "Mazda 6", car_plate: "П654МА59",
                registration_place: "г. Пермь, ул. Дзержинского, д. 5"
            },
            {
                fio: "Морозов Владимир Алексеевич", phone: "+7 (902) 345-67-89",
                birth_date: "1986-01-30", birth_place: "г. Волгоград",
                passport_series: "3405 112233", passport_issued_by: "УМВД по Волгоградской области",
                passport_issued_at: "2013-04-19", passport_department_code: "340-555",
                car_brand: "Honda Civic", car_plate: "В321НО34",
                registration_place: "г. Волгоград, ул. Пушкина, д. 7"
            },
            {
                fio: "Крыжнев Игорь Сергеевич", phone: "+7 (903) 456-78-90",
                birth_date: "1996-10-12", birth_place: "г. Омск",
                passport_series: "5502 998877", passport_issued_by: "ГУ МВД по Омской области",
                passport_issued_at: "2020-11-03", passport_department_code: "550-666",
                car_brand: "Subaru Impreza", car_plate: "О876РЕ55",
                registration_place: "г. Омск, ул. Мира, д. 33"
            },
            {
                fio: "Зайцев Александр Дмитриевич", phone: "+7 (904) 567-89-01",
                birth_date: "1998-04-21", birth_place: "г. Челябинск",
                passport_series: "7501 445566", passport_issued_by: "УМВД по Челябинской области",
                passport_issued_at: "2021-05-17", passport_department_code: "750-777",
                car_brand: "BMW X5", car_plate: "Ч765ТЕ74",
                registration_place: "г. Челябинск, ул. Лермонтова, д. 19"
            },
            {
                fio: "Ковалев Роман Павлович", phone: "+7 (905) 678-90-12",
                birth_date: "1984-07-09", birth_place: "г. Владивосток",
                passport_series: "9503 334455", passport_issued_by: "УМВД по Приморскому краю",
                passport_issued_at: "2012-10-22", passport_department_code: "950-888",
                car_brand: "Mercedes-Benz E-Class", car_plate: "В987ОС25",
                registration_place: "г. Владивосток, ул. Советская, д. 44"
            },
            {
                fio: "Белов Никита Андреевич", phone: "+7 (906) 789-01-23",
                birth_date: "1999-12-31", birth_place: "г. Ростов-на-Дону",
                passport_series: "6104 223344", passport_issued_by: "ГУ МВД по Ростовской области",
                passport_issued_at: "2022-01-15", passport_department_code: "610-999",
                car_brand: "Audi A4", car_plate: "Р654ЛО61",
                registration_place: "г. Ростов-на-Дону, ул. Чехова, д. 6"
            },
            {
                fio: "Титов Павел Олегович", phone: "+7 (907) 890-12-34",
                birth_date: "1983-02-14", birth_place: "г. Краснодар",
                passport_series: "8601 112233", passport_issued_by: "ГУ МВД по Краснодарскому краю",
                passport_issued_at: "2011-03-08", passport_department_code: "860-101",
                car_brand: "Jeep Grand Cherokee", car_plate: "К543ХХ86",
                registration_place: "г. Краснодар, ул. Гагарина, д. 71"
            },
            {
                fio: "Сорокин Максим Ильич", phone: "+7 (908) 901-23-45",
                birth_date: "1997-08-05", birth_place: "г. Ульяновск",
                passport_series: "7302 998877", passport_issued_by: "УМВД по Ульяновской области",
                passport_issued_at: "2020-09-12", passport_department_code: "730-112",
                car_brand: "Citroen C4", car_plate: "У321АН73",
                registration_place: "г. Ульяновск, ул. Ленина, д. 13"
            },
            {
                fio: "Громов Антон Владимирович", phone: "+7 (909) 012-34-56",
                birth_date: "1982-11-17", birth_place: "г. Тюмень",
                passport_series: "7103 445566", passport_issued_by: "УМВД по Тюменской области",
                passport_issued_at: "2010-12-01", passport_department_code: "710-123",
                car_brand: "Volvo XC60", car_plate: "Т876РО71",
                registration_place: "г. Тюмень, ул. Пушкина, д. 9"
            },
            {
                fio: "Ширяев Алексей Михайлович", phone: "+7 (910) 123-45-67",
                birth_date: "1994-01-01", birth_place: "г. Иркутск",
                passport_series: "5304 334455", passport_issued_by: "ГУ МВД по Иркутской области",
                passport_issued_at: "2018-02-14", passport_department_code: "530-224",
                car_brand: "Peugeot 408", car_plate: "И765ЛЬ53",
                registration_place: "г. Иркутск, ул. Мира, д. 42"
            },
            {
                fio: "Жуков Евгений Сергеевич", phone: "+7 (911) 234-56-78",
                birth_date: "1980-06-25", birth_place: "г. Ярославль",
                passport_series: "7601 223344", passport_issued_by: "УМВД по Ярославской области",
                passport_issued_at: "2010-07-19", passport_department_code: "760-335",
                car_brand: "Opel Astra", car_plate: "Я654ОР76",
                registration_place: "г. Ярославль, ул. Ленина, д. 27"
            }
        ];

        // Добавляем поле registration_place ко всем элементам
        // for (let i = 0; i < mockDataArray.length; i++) {
        //     const cities = ["г. Москва", "г. Санкт-Петербург", "г. Екатеринбург", "г. Новосибирск", "г. Казань", "г. Нижний Новгород", "г. Самара", "г. Уфа", "г. Красноярск", "г. Пермь"];
        //     const streets = ["ул. Ленина", "ул. Гагарина", "ул. Пушкина", "ул. Мира", "ул. Советская", "ул. Чехова", "ул. Карла Маркса", "ул. Дзержинского", "ул. Суворова", "ул. Лермонтова"];
        //     const building = Math.floor(Math.random() * 100) + 1;

        //     const randomCity = cities[Math.floor(Math.random() * cities.length)];
        //     const randomStreet = streets[Math.floor(Math.random() * streets.length)];

        //     mockDataArray[i].registration_place = `${randomCity}, ${randomStreet}, д. ${building}`;
        // }

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

    // console.log('Инициализация обработчика выбора пользователя');

    return;

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

// Инициализация обработчика формы сотрудника
export function handlerAddEmployee() {
    // console.log('Инициализация обработчика формы сотрудника');
    const form = document.querySelector('form#employeeForm');
    // console.log('Найдена форма:', form);

    if (!form) {
        console.error('Форма сотрудника не найдена');
        // Попробуем найти форму снова через 500мс на случай, если DOM ещё не загружен
        setTimeout(() => {
            const formRetry = document.querySelector('form#employeeForm');
            // console.log('Повторная попытка найти форму:', formRetry);
            if (formRetry) {
                initEmployeeForm(formRetry);
            }
        }, 500);
        return;
    }

    // Инициализация обработчика отправки формы
    initEmployeeForm(form);

    /**
     * Инициализирует обработчик отправки формы сотрудника
     * @param {HTMLFormElement} form - Элемент формы
     */
    function initEmployeeForm(form) {
        // console.log('Инициализация обработчика отправки формы');
        form.addEventListener('submit', function(e) {
            // console.log('Событие отправки формы перехвачено');
            handleEmployeeFormSubmit.call(this, e);
        });
    }

    /**
     * Отправляет данные формы на сервер для создания пользователя и сотрудника
     * @param {FormData} formData - Данные формы
     * @returns {Promise<Object>} - Ответ сервера
     */
    async function submitEmployeeForm(formData) {
        const response = await fetch('/employees', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        return await response.json();
    }

    /**
     * Обрабатывает отправку формы сотрудника
     * @param {Event} e - Событие отправки формы
     */
    }

async function handleEmployeeFormSubmit(e) {
    // console.log('Начало обработки отправки формы');
    e.preventDefault();

    const employeeInfoBlock = document.getElementById('employeeInfo');
    employeeInfoBlock.innerHTML = '';

    // console.log('Начало обработки отправки формы');

    const form = this;
    const formData = new FormData(form);
    const submitBtn = document.getElementById('saveBtn') || form.querySelector('button[type="submit"]');
    // console.log('Найдена кнопка сохранения:', submitBtn);
    const messagesContainer = document.getElementById('formMessages');

    // console.log('Найдены элементы:', { form, submitBtn, messagesContainer });
    // console.log('Данные формы:', Object.fromEntries(formData.entries()));

    // Очищаем предыдущие сообщения
    if (messagesContainer) {
        messagesContainer.innerHTML = '';
    } else {
        console.warn('Контейнер для сообщений не найден');
    }

    // Валидируем форму на клиенте
    // console.log('Проверка валидации формы...');
    const { isValid, errors } = window.formValidator.validate(form);
    // console.log('Результат валидации:', { isValid, errors });

    if (!isValid) {
        // console.log('Ошибки валидации, отмена отправки');
        window.formValidator.displayErrors(errors, messagesContainer);
        return;
    }

    // console.log('Форма прошла валидацию, подготовка к отправке...');

    try {
        // Сохраняем оригинальный текст кнопки
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';

        // Показываем индикатор загрузки
        // console.log('Показ индикатора загрузки...');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Регистрация...';
        } else {
            // console.warn('Кнопка отправки не найдена, невозможно показать индикатор загрузки');
        }

        try {
            // Собираем данные формы в объект
            const formDataObj = {};
            new FormData(form).forEach((value, key) => {
                // Если поле уже существует и является массивом, добавляем к нему значение
                if (formDataObj[key] !== undefined) {
                    if (!Array.isArray(formDataObj[key])) {
                        formDataObj[key] = [formDataObj[key]];
                    }
                    formDataObj[key].push(value);
                } else {
                    formDataObj[key] = value;
                }
            });

            console.log('Отправляемые данные:', formDataObj);

            // Определяем URL в зависимости от наличия employee_id
            let url = '/employees/store'; // По умолчанию - создание нового сотрудника

            // Если есть employee_id, значит это обновление существующего сотрудника
            if (formDataObj.employee_id) {
                url = '/employee/update';
            }

            // Отправляем форму на сервер используя postData
            const data = await postData(url, formDataObj);
            console.log('Ответ сервера по созданию/обновлению сотрудника:', data);

            // Проверяем наличие ошибок валидации
            if (data.errors) {
                console.log('Ошибки валидации с сервера:', data.errors);

                // Собираем все ошибки в один массив
                const allErrors = [];

                // Laravel-style errors - преобразуем объект ошибок в массив сообщений
                Object.entries(data.errors).forEach(([field, messages]) => {
                    if (Array.isArray(messages)) {
                        allErrors.push(...messages);
                    } else if (typeof messages === 'string') {
                        allErrors.push(messages);
                    }

                    // Выделяем невалидные поля
                    const input = form.querySelector(`[name="${field}"]`);
                    if (input) {
                        input.classList.add('is-invalid');
                        const feedback = input.nextElementSibling || document.createElement('div');
                        if (!feedback.classList.contains('invalid-feedback')) {
                            feedback.className = 'invalid-feedback';
                            input.parentNode.insertBefore(feedback, input.nextSibling);
                        }
                        feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
                    }
                });

                // Отображаем ошибки
                if (window.formValidator && window.formValidator.displayErrors) {
                    window.formValidator.displayErrors(allErrors, messagesContainer);
                } else {
                    showAlert(allErrors.join('\n'), 'danger', messagesContainer);
                }
                return;
            }

            // Проверяем на явную ошибку (когда success: false)
            if (data.success === false) {
                throw new Error(data.message || 'Ошибка при сохранении сотрудника');
            }

            // Успешное завершение
            // console.log('Сотрудник успешно создан', data);
            showAlert('Сотрудник успешно создан', 'success', messagesContainer);
            form.reset();

            // console.log('Сотрудник успешно создан', data);
            // console.log('Функция отображения сотрудника', window.displayEmployeeInfo);

            // Отображаем информацию о созданном сотруднике
            if (data && window.displayEmployeeInfo) {
                window.displayEmployeeInfo(data);
            }

            // Обновляем таблицу сотрудников, если она есть на странице
            const employeesTable = document.querySelector('.employees-table');
            if (employeesTable && window.DataTable) {
                const table = window.DataTable(employeesTable);
                if (table && typeof table.ajax.reload === 'function') {
                    table.ajax.reload();
                }
            }

        } catch (error) {
            console.error('Ошибка при сохранении:', error);
            let errorMessage = 'Произошла ошибка при сохранении. Пожалуйста, попробуйте еще раз.';

            // Проверяем, есть ли сообщение об ошибке в объекте error
            if (error.message) {
                errorMessage = error.message;
            }

            // Если есть дополнительные данные об ошибке
            if (error.data) {
                console.error('Данные об ошибке:', error.data);

                if (error.data.message) {
                    errorMessage = error.data.message;
                } else if (error.data.errors) {
                    // Обработка ошибок валидации Laravel
                    const errors = Object.values(error.data.errors).flat();
                    errorMessage = errors.join('\n');
                }
            }
            else if (error.request) {
                // Запрос был сделан, но ответ не получен
                console.error('Не удалось получить ответ от сервера');
                errorMessage = 'Не удалось соединиться с сервером. Пожалуйста, проверьте подключение к интернету и попробуйте снова.';
            } else if (error instanceof Error) {
                // Ошибка JavaScript
                errorMessage = error.message;
                console.error('Ошибка JavaScript:', error.stack);
            } else {
                // Неизвестная ошибка
                console.error('Неизвестная ошибка:', error);
                errorMessage = 'Произошла непредвиденная ошибка. Пожалуйста, сообщите об этом в службу поддержки.';
            }

            showAlert(errorMessage, 'danger', messagesContainer);
        } finally {
            // Восстанавливаем кнопку
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText || 'Сохранить';
            }
        }
    } catch (error) {
        console.error('Неожиданная ошибка:', error);
        showAlert('Произошла непредвиденная ошибка', 'danger', messagesContainer);
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
        showAlert('Пожалуйста, заполните все обязательные поля!', 'warning');
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
        // console.log('Save button clicked');
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

// Обработчик добавления сотрудника в бригаду
export function hanlerAddToBrigade() {
    const addToBrigadeBtn = document.getElementById('addToBrigadeBtn');
    
    // Проверяем, существует ли кнопка на странице
    if (!addToBrigadeBtn) {
        console.log('Кнопка добавления в бригаду не найдена на странице');
        return;
    }
    
    addToBrigadeBtn.addEventListener('click', function () {
        const select = document.getElementById('employeesSelect');
        const brigadeMembers = document.getElementById('brigadeMembers');
        
        // Проверяем существование элементов
        if (!select || !brigadeMembers) {
            console.error('Не удалось найти необходимые элементы на странице');
            return;
        }
        
        const selectedOptions = Array.from(select.selectedOptions);

        if (selectedOptions.length > 0) {
            selectedOptions.forEach(option => {
                // Проверяем, не является ли добавляемый сотрудник уже бригадиром
                const leaderInput = document.getElementById('brigadeLeader');
                if (leaderInput && leaderInput.value === option.value) {
                    showAlert('Этот сотрудник уже является бригадиром', 'warning');
                    option.selected = false;
                    return; // Пропускаем добавление, если это бригадир
                }
                // Создаем элемент для отображения сотрудника
                const memberDiv = document.createElement('div');
                memberDiv.setAttribute('data-employee-id', option.value);
                memberDiv.className = 'd-flex justify-content-between align-items-center p-2 mb-2 border rounded';

                // Добавляем обработчик клика для выбора бригадира
                memberDiv.addEventListener('click', function() {
                    // Убираем класс у всех элементов
                    document.querySelectorAll('#brigadeMembers > div').forEach(div => {
                        div.classList.remove('brigade-leader');
                    });

                    // Добавляем класс текущему элементу
                    this.classList.add('brigade-leader');

                    // Обновляем значение в скрытом поле для бригадира
                    const leaderInput = document.getElementById('brigadeLeader');
                    if (leaderInput) {
                        leaderInput.value = this.getAttribute('data-employee-id');
                    }
                });

                // Добавляем имя сотрудника
                const nameSpan = document.createElement('span');
                nameSpan.textContent = option.text;

                // Создаем кнопку удаления
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-sm btn-outline-danger';
                deleteBtn.innerHTML = '&times;';
                deleteBtn.onclick = function (e) {
                    e.stopPropagation(); // Предотвращаем всплытие события, чтобы не сработал клик на элементе
                    memberDiv.remove();
                    // Разблокируем опцию в селекте
                    option.disabled = false;
                    option.selected = false;
                    // Показываем опцию в выпадающем списке
                    option.style.display = '';
                    // Обновляем скрытое поле с данными о составе бригады
                    updateBrigadeMembersFormField();
                };

                // Скрытое поле для формы
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'brigade_members[]';
                hiddenInput.value = option.value;
                // hiddenInput.setAttribute('data-employee-id', option.dataset.employeeId);

                // Собираем всё вместе
                memberDiv.appendChild(hiddenInput);
                memberDiv.appendChild(nameSpan);
                memberDiv.appendChild(deleteBtn);

                // Добавляем в список бригады
                brigadeMembers.appendChild(memberDiv);

                // Делаем опцию в селекте неактивной и скрываем её
                option.disabled = true;
                option.style.display = 'none';
                option.selected = false;
            });

            // Обновляем скрытое поле с данными о составе бригады
            updateBrigadeMembersFormField();
        } else {
            console.log('Выберите хотя бы одного сотрудника');
        }
    });
}

// Обработчик создания бригады
export function handlerCreateBrigade() {
    // console.log('Инициализация handlerCreateBrigade...');
    const createBtn = document.getElementById('createBrigadeBtn');
    const createBrigadeModal = document.getElementById('createBrigadeModal');

    // Обработчик открытия модального окна
    if (createBrigadeModal) {
        createBrigadeModal.addEventListener('show.bs.modal', function () {
            // При открытии модального окна скрываем уже добавленных сотрудников
            const employeesSelect = document.getElementById('employeesSelect');
            if (employeesSelect) {
                Array.from(employeesSelect.options).forEach(option => {
                    option.style.display = option.disabled ? 'none' : '';
                });
            }
        });
    }

    if (createBtn) {
        createBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            console.clear();
            // console.log('Начало обработки клика...');

            // Получаем данные формы
            const form = document.getElementById('brigadeForm');
            if (!form) {
                console.error('Форма не найдена');
                return;
            }

            // Обновляем данные бригады перед отправкой
            const members = getAllBrigadeMembers();

            console.log('1) members', members);
            
            // return;

            const leaderId = document.getElementById('brigadeLeader')?.value;

            console.log('2) leaderId', leaderId);

            // Проверяем валидность
            if (!leaderId) {
                showAlert('Пожалуйста, выберите бригадира, кликнув по участнику в списке 1', 'warning');
                return;
            }

            if (members.length < 1) {
                showAlert('В бригаде должен быть хотя бы 1 участник', 'warning');
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

            console.log('3) hiddenField', hiddenField);

            hiddenField.value = JSON.stringify(members);

            console.log('4) hiddenField.value', hiddenField.value);

            // Создаем FormData и добавляем все необходимые поля
            const formData = new FormData(form);

            console.log('5) formData', formData);

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

            console.log('6) formData 2', formData);

            members.forEach((member, index) => {
                if (!member.is_leader) {
                    formData.append('brigade_members[]', member.employee_id);
                }
            });

            // Логируем данные перед отправкой
            // console.log('Отправка данных бригады:', {
            //     formData: Object.fromEntries(formData.entries()),
            //     members,
            //     leaderId
            // });

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

            console.log('7) brigadeMembersData', brigadeMembersData );

            // return;

            // Используем существующую переменную leaderId вместо объявления новой
            // const leaderId = 37;

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

            console.log('8) formJson', formJson);

            // Выводим JSON в консоль
            // console.log('=== ДАННЫЕ ФОРМЫ В ФОРМАТЕ JSON ===');
            // console.log(JSON.stringify(formJson, null, 2));

            // Проверяем обязательные поля
            if (!formValues.leader_id) {
                showAlert('Пожалуйста, выберите бригадира', 'warning');
                return;
            }

            // Проверяем, что в бригаде есть хотя бы 1 участник
            if (formJson.members.length < 1) {
                showAlert('В бригаде должен быть хотя бы 1 участник', 'warning');
                return;
            }

            // Проверяем, что выбран бригадир
            const brigadierSelected = document.querySelector('#brigadeMembers .brigade-leader');
            if (!brigadierSelected) {
                showAlert('Пожалуйста, выберите бригадира, кликнув по участнику в списке 2', 'warning');
                return;
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

                        console.log('11) brigadesList', brigadesList);

                    const response = await fetch('/api/brigades');
                    if (!response.ok) {
                        throw new Error('Ошибка при загрузке списка бригад');
                    }

                    const brigades = await response.json();

                    console.log('9) brigades', brigades);

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

                    console.log('10) brigades', brigades);

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
                        // console.log('Элемент с id="brigadesList" не найден');
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

            const createBrigade = async () => {
                try {
                    // console.log('Отправка запроса на создание бригады...');

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

                    // console.log('Данные для отправки:', requestData);

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
                            // Очищаем скрытые поля, кроме CSRF-токена
                            const hiddenFields = form.querySelectorAll('input[type="hidden"]');
                            hiddenFields.forEach(field => {
                                // Не очищаем поле с CSRF-токеном
                                if (field.name !== '_token' && field.name !== 'csrf-token') {
                                    field.value = '';
                                }
                            });
                        }

                        // Очищаем список участников бригады
                        const brigadeMembers = document.getElementById('brigadeMembers');
                        if (brigadeMembers) {
                            brigadeMembers.innerHTML = '';
                        }
                        
                        // Сбрасываем бригадира
                        const brigadeLeader = document.getElementById('brigadeLeader');
                        if (brigadeLeader) {
                            brigadeLeader.value = '';
                        }

                        // Снимаем атрибут disabled у сотрудников в списке слева, но не показываем их
                        const employeesSelect = document.getElementById('employeesSelect');
                        if (employeesSelect) {
                            Array.from(employeesSelect.options).forEach(option => {
                                option.disabled = false;
                                // Не показываем опции, которые уже добавлены в бригаду
                                // Они будут скрыты, так как при добавлении мы устанавливаем display: 'none'
                            });
                        }

                        // Обновляем список бригад
                        if (typeof window.updateBrigadesList === 'function') {
                            window.updateBrigadesList(data.brigade);
                        } else {
                            // Если функция обновления не определена, перезагружаем страницу
                            console.warn('Функция updateBrigadesList не найдена, выполняется перезагрузка страницы');
                            setTimeout(() => window.location.reload(), 1000);
                        }

                        // Здесь будет запрос на обновление списка бригад

                        try {
                            const brigadeInfoResponse = await fetch('/api/brigades/info-current-day', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({
                                    date: document.querySelector('#datepicker').value
                                })
                            });

                            const brigadeInfoData = await brigadeInfoResponse.json();
                            console.log('Информация о бригадах:', brigadeInfoData);

                            // Отображаем информацию о бригадах на странице
                            displayBrigadeInfo(brigadeInfoData);
                        } catch (error) {
                            console.error('Ошибка при получении информации о бригадах:', error);
                        }

                        // Используем глобальную функцию displayBrigadeInfo


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

            // console.log('Бригада успешно создана!');

            // Для отправки формы раскомментируйте строку ниже
            // form.submit();
        });
    } else {
        console.warn('Кнопка createBrigadeBtn не найдена');
    }
}

// Функция для настройки прикрепления бригады к заявке
export function setupBrigadeAttachment() {
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

                            // console.log(`ID выбранной заявки: ${requestId}`);
                            // console.log(`ID выбранного бригадира: ${leaderId}`);
                            // console.log(`ID бригады: ${brigade_id}`);
                            // console.log(`Название бригады: ${brigadeName}`);

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
                                // console.log('Ответ от API обновления заявки:', updateResponse.status, updateData);

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
                        // console.log('Кнопка нажата для заявки', requestId);
                        // console.log('Выбран бригадир с ID:', brigadeSelect.value);
                    });

                    // Вставляем кнопку после select
                    brigadeSelect.parentNode.insertBefore(button, brigadeSelect.nextSibling);
                }
            }
        }
    });
}

export function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover',
            placement: 'left'
        });
    });
}
