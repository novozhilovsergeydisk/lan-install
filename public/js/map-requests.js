
document.addEventListener('DOMContentLoaded', () => {
    // Используем setTimeout, чтобы наш скрипт выполнился после init-handlers.js (который type="module" и может выполняться позже или параллельно),
    // и после того, как старые скрипты навесят свои обработчики.
    // Так мы гарантируем, что замена кнопки (cloneNode) удалит именно те обработчики, которые были добавлены другими скриптами.
    setTimeout(() => {
        setupMapControls();
    }, 500); 
});

/**
 * Настройка контролов карты:
 * 1. Замена обработчика кнопки "На карте" (клонированием для сброса старых событий).
 * 2. Обработчик чекбокса "Показать планирование".
 */
function setupMapControls() {
    // Внедряем стили для подписей меток (полупрозрачный фон)
    if (!document.getElementById('map-label-styles')) {
        const style = document.createElement('style');
        style.id = 'map-label-styles';
        style.textContent = `
            /* Таргетируемся на текст подписи Яндекс.Карт */
            [class*="-placemark-caption__text"] {
                background-color: rgba(255, 255, 255, 0.7) !important;
                padding: 1px 4px !important;
                border-radius: 4px !important;
                color: #000 !important;
                font-weight: 500 !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
                white-space: nowrap !important;
            }
        `;
        document.head.appendChild(style);
    }

    const btnOpenMap = document.getElementById('btn-open-map');
    
    if (btnOpenMap) {
        // Клонируем кнопку, чтобы удалить старые слушатели (из form-handlers.js)
        const newBtn = btnOpenMap.cloneNode(true);
        btnOpenMap.parentNode.replaceChild(newBtn, btnOpenMap);
        
        console.log('Map button replaced to remove old handlers.');

        newBtn.addEventListener('click', () => {
            const mapContent = document.getElementById('map-content');
            // Проверяем видимость (класс hide-me или display: none)
            const isHidden = mapContent.classList.contains('hide-me') || 
                             window.getComputedStyle(mapContent).display === 'none';
            
            if (isHidden) {
                openMap();
            } else {
                closeMap();
            }
        });
    }

    // Слушатель для чекбокса планирования
    const planningCheckbox = document.getElementById('cb-show-planning');
    if (planningCheckbox) {
        planningCheckbox.addEventListener('change', () => {
             const mapContent = document.getElementById('map-content');
             // Если карта открыта, обновляем данные
             if (!mapContent.classList.contains('hide-me') && mapContent.style.display !== 'none') {
                 loadAndDrawRequests();
             }
        });
    }
}

function openMap() {
    const mapContent = document.getElementById('map-content');
    mapContent.classList.remove('hide-me');
    mapContent.style.display = 'block';
    mapContent.style.height = '800px'; 
    mapContent.style.visibility = 'visible';
    mapContent.style.padding = '1rem';

    if (typeof ymaps === 'undefined') {
        console.error('Yandex Maps API not loaded');
        // Попытка показать ошибку пользователю
        mapContent.innerHTML = '<div class="alert alert-danger">Ошибка: API Яндекс.Карт не загружено. Попробуйте обновить страницу.</div>';
        return;
    }

    ymaps.ready(initMap);
}

function closeMap() {
    const mapContent = document.getElementById('map-content');
    mapContent.classList.add('hide-me');
    mapContent.style.display = 'none';
    mapContent.style.visibility = 'hidden';
    mapContent.style.padding = '0';
    mapContent.style.height = '0';
}

async function initMap() {
    // Создаем карту если нет или она была уничтожена
    if (!window.yandexMap) {
        document.getElementById('map').innerHTML = ''; // Очистка контейнера
        window.yandexMap = new ymaps.Map('map', {
            center: [55.75, 37.62],
            zoom: 10,
            controls: ['zoomControl', 'typeSelector', 'fullscreenControl']
        });
    }

    // Обновляем размер карты (важно при переключении display: none -> block)
    window.yandexMap.container.fitToViewport();

    await loadAndDrawRequests();
}

async function loadAndDrawRequests() {
    const map = window.yandexMap;
    map.geoObjects.removeAll();

    let dateStr = '';
    
    // Пытаемся получить дату из глобальных объектов (window.selectedDateState)
    if (window.selectedDateState && window.selectedDateState.date) {
        dateStr = formatDateToInputLocal(window.selectedDateState.date);
    } else if (window.currentDateState && window.currentDateState.date) {
        dateStr = formatDateToInputLocal(window.currentDateState.date);
    } 

    // Если не удалось, пробуем найти input #executionDate (который часто содержит актуальную дату)
    if (!dateStr) {
        const dateInput = document.getElementById('executionDate'); // Часто используется в формах
        if (dateInput && dateInput.value) {
            dateStr = dateInput.value;
        }
    }

    // Fallback на сегодня
    if (!dateStr) {
        dateStr = new Date().toISOString().split('T')[0];
    }

    console.log('Loading requests for map. Date:', dateStr);

    const includePlanning = document.getElementById('cb-show-planning')?.checked || false;

    // Оптимизация: если "Планирование" выключено, пробуем сначала взять данные из localStorage,
    // чтобы не делать лишний запрос и гарантировать работу карты для текущего вида
    if (!includePlanning) {
        const localData = localStorage.getItem('requestsData');
        if (localData) {
            try {
                const requests = JSON.parse(localData);
                if (Array.isArray(requests) && requests.length > 0) {
                    console.log('Loaded requests from localStorage for map:', requests.length);
                    drawRequests(map, requests);
                    return; // Успешно загрузили из кэша, запрос не нужен
                }
            } catch (e) {
                console.error('Error parsing requestsData from localStorage', e);
            }
        }
    }

    try {
        const url = `/api/requests/date/${dateStr}?include_planning=${includePlanning ? '1' : '0'}`;
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success && Array.isArray(data.data)) {
            console.log('Loaded requests from API:', data.data.length);
            drawRequests(map, data.data);
        }
    } catch (e) {
        console.error('Error loading requests:', e);
        // Fallback: если API упал, но есть данные в localStorage, покажем их (даже если planning был включен, лучше показать хоть что-то)
        const localData = localStorage.getItem('requestsData');
        if (localData) {
            try {
                const requests = JSON.parse(localData);
                drawRequests(map, requests);
            } catch (localErr) {}
        }
    }
}

function drawRequests(map, requests) {
    if (!requests || requests.length === 0) return;

    const bounds = [];
    requests.forEach(request => {
        const placemark = addPlacemark(map, request);
        if (placemark) {
            bounds.push(placemark.geometry.getCoordinates());
        }
    });
    
    // Центрируем карту по всем меткам
    if (bounds.length > 0) {
        map.setBounds(map.geoObjects.getBounds(), {
            checkZoomRange: true,
            zoomMargin: 50
        });
    }
}

function addPlacemark(map, request) {
    if (!request.latitude || !request.longitude) return null;

    // Логика цвета
    // Зеленый (#198754 - bootstrap success) для "Выполнена" (id=4)
    // Остальные - цвет типа заявки или дефолтный синий
    let color = request.request_type_color || '#1e88e5';
    
    if (String(request.status_id) === '4') {
        color = '#198754'; 
    } 
    
    // Цифра внутри иконки (quantity первого параметра)
    const iconContent = request.first_param_quantity ? String(request.first_param_quantity) : '';

    // Определяем имя бригадира или запасной текст для подписи
    const rawName = request.brigade_lead || request.brigade_leader_name || request.brigade_name;
    let labelText = '';
    
    if (rawName) {
        labelText = formatNameShort(rawName);
    } else {
        // Если бригады нет, выводим краткий статус неназначенности
        labelText = 'Нет бригады';
    }

    // console.log(`Request #${request.id} (${request.status_name}): Label='${labelText}'`);

    // Формирование HTML для балуна
    let commentsHtml = '';
    if (request.comments && request.comments.length > 0) {
        commentsHtml = '<div class="mt-3 border-top pt-2"><strong>Комментарии:</strong><ul style="padding-left: 15px; margin-top: 5px; margin-bottom: 0;">';
        request.comments.forEach(c => {
             // Форматирование даты
             let dateStr = 'Без даты';
             if (c.created_at) {
                 const d = new Date(c.created_at);
                 dateStr = d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', {hour: '2-digit', minute:'2-digit'});
             }
             commentsHtml += `<li style="margin-bottom: 4px; font-size: 0.9em;">
                <span class="text-muted" style="font-size: 0.85em;">${dateStr}</span><br>
                ${c.comment}
             </li>`;
        });
        commentsHtml += '</ul></div>';
    }

    const balloonContent = `
        <div style="font-size: 14px; max-width: 300px;">
            <div style="margin-bottom: 8px;">
                <strong style="font-size: 1.1em; color: ${color}">${request.request_type_name || 'Заявка'} #${request.number || request.id}</strong>
                ${request.status_name ? `<br><span class="badge bg-secondary" style="font-size: 0.8em;">${request.status_name}</span>` : ''}
            </div>
            
            <div style="margin-bottom: 4px;">
                <i class="bi bi-geo-alt me-1"></i> ${request.address}
            </div>
            
            ${request.client_phone ? `
            <div style="margin-bottom: 4px;">
                <i class="bi bi-telephone me-1"></i> <a href="tel:${request.client_phone}">${request.client_phone}</a>
            </div>` : ''}
            
            ${request.client_fio ? `
            <div style="margin-bottom: 4px;">
                <i class="bi bi-person me-1"></i> ${request.client_fio}
            </div>` : ''}

            ${(function() {
                // Логика формирования блока бригады
                const leaderName = request.brigade_lead || request.brigade_leader_name;
                const members = request.brigade_members || [];
                let brigadeHtml = '';

                // Если есть данные о составе
                if (leaderName || members.length > 0) {
                    brigadeHtml += '<div style="margin-bottom: 4px; border-left: 2px solid #dee2e6; padding-left: 8px; margin-left: 2px;">';
                    
                    if (request.brigade_name) {
                         brigadeHtml += `<div class="text-muted" style="font-size: 0.85em;">${request.brigade_name}</div>`;
                    }

                    if (leaderName) {
                        brigadeHtml += `<div><strong>Бригадир:</strong> ${leaderName}</div>`;
                    }

                    if (members.length > 0) {
                        // Фильтруем, чтобы не дублировать бригадира, если он вдруг попал в список
                        const memberNames = members
                            .map(m => m.name)
                            .filter(name => name && name !== leaderName)
                            .join(', ');
                        
                        if (memberNames) {
                             brigadeHtml += `<div style="font-size: 0.9em;"><strong>Состав:</strong> ${memberNames}</div>`;
                        }
                    }
                    brigadeHtml += '</div>';
                } else if (request.brigade_name) {
                    // Если есть только название
                    brigadeHtml = `
                    <div style="margin-bottom: 4px;">
                        <i class="bi bi-people me-1"></i> Бригада: ${request.brigade_name}
                    </div>`;
                }
                
                return brigadeHtml;
            })()}

            ${commentsHtml}
        </div>
    `;

    // Создаем метку
    // preset: 'islands#icon' - стандартная "капля"
    // iconColor - задает цвет капли
    // iconCaption - подпись рядом с иконкой (видна всегда)
    const placemark = new ymaps.Placemark([parseFloat(request.latitude), parseFloat(request.longitude)], {
        iconContent: iconContent,
        balloonContent: balloonContent,
        iconCaption: labelText
    }, {
        preset: 'islands#icon', // Капля с текстом (iconContent)
        iconColor: color,
        iconCaptionMaxWidth: 150
    });

    map.geoObjects.add(placemark);
    return placemark;
}

// Форматирование ФИО в Фамилия И.О.
function formatNameShort(fullName) {
    if (!fullName) return '';
    const parts = fullName.trim().split(/\s+/); // Разбиваем по пробелам
    if (parts.length === 0) return '';
    
    let result = parts[0]; // Фамилия
    
    if (parts.length > 1) {
        result += ' ' + parts[1].charAt(0) + '.'; // + И.
    }
    if (parts.length > 2) {
        result += parts[2].charAt(0) + '.'; // + О.
    }
    
    return result;
}

// Локальная функция форматирования даты, чтобы не зависеть от внешних
function formatDateToInputLocal(dateInput) {
    if (!dateInput) return '';
    
    // Если дата уже в формате YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateInput)) {
        return dateInput;
    }

    // Если дата в формате DD.MM.YYYY (как в selectedDateState)
    if (/^\d{2}\.\d{2}\.\d{4}$/.test(dateInput)) {
        const parts = dateInput.split('.');
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }

    const d = new Date(dateInput);
    if (isNaN(d.getTime())) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
