
document.addEventListener('DOMContentLoaded', () => {
    // Ждем инициализации других скриптов
    setTimeout(() => {
        setupPlanningMapControls();
    }, 500); 
});

function setupPlanningMapControls() {
    const btnOpenMap = document.getElementById('btn-open-planning-map');
    
    if (btnOpenMap) {
        // Клонируем кнопку для очистки старых слушателей (если вдруг они есть)
        const newBtn = btnOpenMap.cloneNode(true);
        btnOpenMap.parentNode.replaceChild(newBtn, btnOpenMap);
        
        console.log('Planning map button setup.');

        newBtn.addEventListener('click', () => {
            const mapContent = document.getElementById('planning-map-content');
            
            // Проверяем видимость
            const isHidden = mapContent.classList.contains('hide-me') || 
                             window.getComputedStyle(mapContent).display === 'none';
            
            if (isHidden) {
                openPlanningMap();
            } else {
                closePlanningMap();
            }
        });
    }
}

function openPlanningMap() {
    const mapContent = document.getElementById('planning-map-content');
    mapContent.classList.remove('hide-me');
    mapContent.style.display = 'block';
    mapContent.style.height = '600px'; 
    mapContent.style.visibility = 'visible';
    
    // Инициализация карты
    if (typeof ymaps === 'undefined') {
        console.error('Yandex Maps API not loaded');
        mapContent.innerHTML = '<div class="alert alert-danger">Ошибка: API Яндекс.Карт не загружено. Попробуйте обновить страницу.</div>';
        return;
    }

    ymaps.ready(initPlanningMap);
}

function closePlanningMap() {
    const mapContent = document.getElementById('planning-map-content');
    mapContent.classList.add('hide-me');
    mapContent.style.display = 'none';
}

async function initPlanningMap() {
    // Создаем карту если нет
    if (!window.planningYandexMap) {
        document.getElementById('planning-map').innerHTML = ''; // Очистка
        window.planningYandexMap = new ymaps.Map('planning-map', {
            center: [55.75, 37.62],
            zoom: 10,
            controls: ['zoomControl', 'typeSelector', 'fullscreenControl']
        });
    }

    // Обновляем размер
    window.planningYandexMap.container.fitToViewport();

    await loadAndDrawPlanningRequests();
}

async function loadAndDrawPlanningRequests() {
    const map = window.planningYandexMap;
    map.geoObjects.removeAll();

    console.log('Loading planning requests for map...');

    try {
        const response = await fetch('/get-planning-requests', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // Структура ответа: { success: true, data: { planningRequests: [...] } }
        if (data.success && data.data && Array.isArray(data.data.planningRequests)) {
            console.log('Loaded planning requests:', data.data.planningRequests.length);
            drawPlanningRequests(map, data.data.planningRequests);
        } else {
            console.warn('No planning requests found or invalid format', data);
        }
    } catch (e) {
        console.error('Error loading planning requests:', e);
    }
}

function drawPlanningRequests(map, requests) {
    if (!requests || requests.length === 0) return;

    const bounds = [];
    const usedCoordinates = new Set(); // Для отслеживания занятых координат

    requests.forEach(request => {
        // Проверяем и смещаем координаты при совпадении
        let lat = parseFloat(request.latitude);
        let lon = parseFloat(request.longitude);
        let coordKey = `${lat.toFixed(6)},${lon.toFixed(6)}`;

        while (usedCoordinates.has(coordKey)) {
            // Добавляем небольшое случайное смещение (примерно 5-10 метров)
            const offsetLat = (Math.random() - 0.5) * 0.0002;
            const offsetLon = (Math.random() - 0.5) * 0.0002;
            lat += offsetLat;
            lon += offsetLon;
            coordKey = `${lat.toFixed(6)},${lon.toFixed(6)}`;
        }

        usedCoordinates.add(coordKey);
        
        // Обновляем координаты в объекте request (локально для отрисовки)
        const requestWithOffset = { ...request, latitude: lat, longitude: lon };

        const placemark = addPlanningPlacemark(map, requestWithOffset);
        if (placemark) {
            bounds.push(placemark.geometry.getCoordinates());
        }
    });
    
    // Центрируем
    if (bounds.length > 0) {
        map.setBounds(map.geoObjects.getBounds(), {
            checkZoomRange: true,
            zoomMargin: 50
        });
    }
}

function addPlanningPlacemark(map, request) {
    if (!request.latitude || !request.longitude) return null;

    // Цвет метки (синий по умолчанию, или цвет статуса/типа)
    let color = request.request_type_color || '#1e88e5';
    // Для планирования можно использовать специфичный цвет или оставить как есть
    
    // Номер заявки внутри иконки (или количество, если нужно)
    const iconContent = ''; // request.number ? String(request.number) : '';

    // Подпись: Бригадир или "Нет бригады"
    let labelText = '';
    if (request.brigade_lead) {
        labelText = formatNameShort(request.brigade_lead);
    } else if (request.brigade_name) {
         labelText = request.brigade_name;
    } else {
        labelText = 'Нет бригады';
    }

    // Комментарии (JSON строка или объект)
    let commentsHtml = '';
    let comments = request.comments;
    // Если comments пришел как строка JSON (Postgres может вернуть так), парсим
    if (typeof comments === 'string') {
        try { comments = JSON.parse(comments); } catch(e) {}
    }

    if (Array.isArray(comments) && comments.length > 0) {
        commentsHtml = '<div class="mt-3 border-top pt-2"><strong>Комментарии:</strong><ul style="padding-left: 15px; margin-top: 5px; margin-bottom: 0;">';
        comments.forEach(c => {
             let dateStr = c.created_at || 'Без даты';
             commentsHtml += `<li style="margin-bottom: 4px; font-size: 0.9em;">
                <span class="text-muted" style="font-size: 0.85em;">${dateStr}</span><br>
                ${c.comment}
             </li>`;
        });
        commentsHtml += '</ul></div>';
    }

    // Контент балуна
    const balloonContent = `
        <div style="font-size: 14px; max-width: 300px;">
            <div style="margin-bottom: 8px;">
                <strong style="font-size: 1.1em; color: ${color}">${request.request_type_name || 'Заявка'} ${request.number ? '#' + request.number : ''}</strong>
                <br><span class="badge bg-secondary" style="font-size: 0.8em;">${request.status_name || 'Планирование'}</span>
            </div>
            
            <div style="margin-bottom: 4px;">
                <i class="bi bi-geo-alt me-1"></i> ${request.address || 'Адрес не указан'}
            </div>
            
            ${request.phone ? `
            <div style="margin-bottom: 4px;">
                <i class="bi bi-telephone me-1"></i> <a href="tel:${request.phone}">${request.phone}</a>
            </div>` : ''}
            
            ${request.fio ? `
            <div style="margin-bottom: 4px;">
                <i class="bi bi-person me-1"></i> ${request.fio}
            </div>` : ''}

            ${(function() {
                let brigadeHtml = '';
                if (request.brigade_lead || request.brigade_name) {
                    brigadeHtml += '<div style="margin-bottom: 4px; border-left: 2px solid #dee2e6; padding-left: 8px; margin-left: 2px;">';
                    if (request.brigade_name) {
                         brigadeHtml += `<div class="text-muted" style="font-size: 0.85em;">${request.brigade_name}</div>`;
                    }
                    if (request.brigade_lead) {
                        brigadeHtml += `<div><strong>Бригадир:</strong> ${request.brigade_lead}</div>`;
                    }
                    brigadeHtml += '</div>';
                }
                return brigadeHtml;
            })()}

            ${commentsHtml}
        </div>
    `;

    const placemark = new ymaps.Placemark([parseFloat(request.latitude), parseFloat(request.longitude)], {
        iconContent: iconContent,
        balloonContent: balloonContent,
        iconCaption: labelText
    }, {
        preset: 'islands#icon', 
        iconColor: color,
        iconCaptionMaxWidth: 150
    });

    map.geoObjects.add(placemark);
    return placemark;
}

function formatNameShort(fullName) {
    if (!fullName) return '';
    const parts = fullName.trim().split(/\s+/);
    if (parts.length === 0) return '';
    
    let result = parts[0];
    if (parts.length > 1) result += ' ' + parts[1].charAt(0) + '.';
    if (parts.length > 2) result += parts[2].charAt(0) + '.';
    return result;
}
