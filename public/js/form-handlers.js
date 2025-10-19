// form-handlers.js

import { showAlert, postData, fetchData } from './utils.js';
import { loadAddressesPaginated, loadPlanningRequests } from './handler.js';
import { loadAddressesForPlanning } from './handler.js';
import HouseNumberValidator from './validators/house-number-validator.js';

// Функция для форматирования даты
export function DateFormated(date) {
    return date.split('.').reverse().join('-');
}

function validateForm(form) {
    let isValid = true;
    
    // Сбрасываем все ошибки и валидацию
    form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
        el.classList.remove('is-invalid', 'is-valid');
    });
    
    // Проверяем все обязательные поля
    form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.add('is-valid');
        }
    });
    
    // Проверяем необязательные поля, если они заполнены
    form.querySelectorAll('input:not([required]), select:not([required])').forEach(field => {
        if (field.value.trim()) {
            field.classList.add('is-valid');
        }
    });
    
    return isValid;
}

function initAddCity() {
    const form = document.getElementById('addCityForm');
    const addCityBtn = document.getElementById('addCityBtn');
    
    if (!addCityBtn) return;

    // Валидация при вводе
    form.querySelectorAll('input, select').forEach(field => {
        field.addEventListener('input', () => {
            if (field.classList.contains('is-invalid')) {
                field.classList.remove('is-invalid');
            }
        });
    });

    addCityBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        if (!validateForm(form)) {
            showAlert('Пожалуйста, заполните все обязательные поля!!', 'warning');
            return;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await fetch('/cities/store', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });

        if (!result.ok) {
            const errorData = await result.json();
            throw new Error(errorData.message || 'Ошибка при добавлении города');
        }

        const response = await result.json();
        console.log(response);
        showAlert('Город успешно добавлен', 'success');
        form.reset();

        // Закрываем модальное окно
        const modal = bootstrap.Modal.getInstance(document.getElementById('assignCityModal'));
        modal.hide();
    });

}

// Функция инициализации карты после загрузки API
function initYandexMap() {
    console.log('Инициализация карты...');

    try {
        // Если карта уже существует, очищаем её
        if (window.yandexMap) {
            console.log('Уничтожаем существующую карту');
            window.yandexMap.destroy();
            window.yandexMap = null;
        }
        
        // Пересоздаем элемент карты
        const mapContent = document.getElementById('map-content');
        if (!mapContent) {
            console.error('Контейнер карты не найден');
            return;
        }
        
        mapContent.innerHTML = '<div id="map" style="width: 100%; height: 100%;"></div>';
        console.log('Элемент карты пересоздан');
        
        // Создаем карту с центром на Москве
        window.yandexMap = new ymaps.Map('map', {
            center: [55.75, 37.62], // Центр Москвы (Красная площадь)
            zoom: 10, // Оптимальный зум для просмотра города
            controls: ['zoomControl', 'typeSelector', 'fullscreenControl']
        });
        
        // Обновляем локальную переменную и флаг инициализации
        yandexMap = window.yandexMap;
        isMapInitialized = true;
        
        // Принудительно обновляем границы карты после загрузки
        setTimeout(() => {
            if (window.yandexMap) {
                window.yandexMap.setZoom(10).then(() => {
                    window.yandexMap.setCenter([55.75, 37.62]);
                    window.yandexMap.setZoom(10);
                });
            }
        }, 100);
        
        // Сохраняем ссылку на карту в локальную переменную для использования в этой функции
        const map = window.yandexMap;

        // Проверяем, есть ли данные о заявках
        const requestsData = localStorage.getItem('requestsData');
        let requests = [];
        
        try {
            if (requestsData) {
                requests = JSON.parse(requestsData);
            }
        } catch (e) {
            console.error('Ошибка при парсинге данных заявок:', e);
            requests = [];
        }

        console.log('Всего заявок:', requests.length);
        console.log('Первые 3 заявки:', requests.slice(0, 3));

        let brigadeMembersData = [];
        try {
            const brigadeMembersJson = localStorage.getItem('brigadeMembersCurrentDayData');
            if (brigadeMembersJson) {
                brigadeMembersData = JSON.parse(brigadeMembersJson);
                console.log('Данные о членах бригад загружены:', brigadeMembersData.length, 'записей');
            } else {
                console.warn('Данные о членах бригад отсутствуют в localStorage');
            }
        } catch (e) {
            console.error('Ошибка при загрузке данных о членах бригад:', e);
            brigadeMembersData = [];
        }

        console.log('brigadeMembersCurrentDayData:', brigadeMembersData);    

        if (!Array.isArray(requests) || requests.length === 0) {
            console.log('Нет данных о заявках для отображения на карте');
            return;
        }

        // Массив для хранения объектов меток
        const placemarks = [];
        const geocoder = ymaps.geocode;
        
        // Создаём кастомный макет для постоянно видимой подписи рядом с меткой
        const MyIconContentLayout = ymaps.templateLayoutFactory.createClass(
            '<div style="min-width: 35px; max-width: auto; background: rgba(255, 255, 255, 0.7); padding: 0rem; border-radius: 4px; white-space: nowrap; word-break: keep-all; display: inline-block;">$[properties.iconContent]</div>'
        );

        // Функция для добавления метки на карту
        function addPlacemark(request, address, coords, index) {
            // Форматируем номер заявки в формат REQ-YYYYMMDD-XXXX
            // Инициализируем переменные для работы с бригадой
            let brigadeLeader = null;
            let brigadeMembersList = [];
            
            // Инициализируем данные о бригаде, если они есть
            if (Array.isArray(brigadeMembersData) && request.brigade_id) {
                const brigadeMembers = brigadeMembersData.filter(member => member.brigade_id === request.brigade_id);
                brigadeLeader = brigadeMembers.find(member => member.is_leader);
                
                // Получаем список всех членов бригады (кроме бригадира)
                const otherMembers = brigadeMembers
                    .filter(member => !member.is_leader && member.fio)
                    .map(member => member.fio);
                
                // Собираем список: сначала бригадир, затем остальные
                brigadeMembersList = [
                    ...(brigadeLeader && brigadeLeader.fio ? [brigadeLeader.fio] : []),
                    ...otherMembers
                ];
                
                // Удаляем дубликаты и пустые значения
                brigadeMembersList = [...new Set(brigadeMembersList.filter(Boolean))];
            }

            const requestNumber = request.number || `REQ-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${String(request.id || index + 1).padStart(4, '0')}`;
            const brigadeName = request.brigade_name || 'Без бригады';

            // Определяем цвет метки в зависимости от статуса
            let iconColor = '#1e98ff'; // Синий по умолчанию
            if (request.status_id === 4) iconColor = '#4CAF50'; // Зеленый для выполненных
            else if (request.status_id === 3) iconColor = '#FFC107'; // Желтый для в работе
            else if (request.status_id === 2) iconColor = '#FF9800'; // Оранжевый для назначенных

            // Формируем текст метки с именем бригадира
            const leaderName = brigadeLeader ? (brigadeLeader.fio || brigadeLeader.name) : '';
            const labelText = `
                <div style="font-weight: 500; font-size: 0.7rem; padding-left: 0rem; color: black;">
                    ${leaderName || brigadeName}
                </div>
            `;
                
            
            // Формируем текст для постоянной подписи
            // const labelText = `
            //     <div style="margin-bottom: 4px; font-weight: 500;"><strong>${brigadeName}</strong></div>
            //     <div>${address || 'Адрес не указан'}</div>
            //     ${request.status ? `<div style="color: #666; font-size: 0.9em; margin-top: 2px;">${request.status}</div>` : ''}
            // `;

            // const labelText = `
            // <div style="white-space: normal; width: 100% !important; max-width: 180px; line-height: 1.2; border: 2px solid #007bff; padding: 4px 8px;">
            //     <div style="font-weight: bold;">${requestNumber}</div>
            //     <div>${address || 'Адрес не указан'}</div>
            //     ${request.status ? `<div style="color: #666; font-size: 0.9em;">${request.status}</div>` : ''}
            // </div>
            // `;
            
            // Проверяем мобильное устройство
            const isMobile = window.innerWidth < 768;
            
            // Создаем кастомный макет балуна, если он еще не создан
            if (!window.balloonLayout) {
                window.balloonLayout = ymaps.templateLayoutFactory.createClass(
                    '<div class="custom-balloon">' +
                        '<div class="custom-balloon__content">$[properties.balloonContent]</div>' +
                        '<div class="custom-balloon__close-button">&times;</div>' +
                    '</div>', {
                        build: function() {
                            window.balloonLayout.superclass.build.call(this);
                            this.getData().geoObject.events.add('balloonclose', this.onBalloonClose, this);
                            this._$element = this.getParentElement().querySelector('.custom-balloon');
                            this._$closeButton = this._$element.querySelector('.custom-balloon__close-button');
                            this._$closeButton.addEventListener('click', this.onCloseClick.bind(this));
                            
                            // Добавляем обработчик для мобильных устройств
                            if (isMobile) {
                                this._$element.classList.add('mobile');
                            }
                        },
                        clear: function() {
                            this._$closeButton.removeEventListener('click', this.onCloseClick);
                            this.getData().geoObject.events.remove('balloonclose', this.onBalloonClose, this);
                            window.balloonLayout.superclass.clear.call(this);
                        },
                        onCloseClick: function(e) {
                            e.preventDefault();
                            this.getData().geoObject.balloon.close();
                        },
                        onBalloonClose: function() {}
                    }
                );
                
                // Добавляем стили для кастомного балуна
                if (!document.getElementById('custom-balloon-styles')) {
                    const style = document.createElement('style');
                    style.id = 'custom-balloon-styles';
                    style.textContent = `
                        @media (max-width: 767px) {
                            .ymaps-2-1-79-balloon {
                                position: fixed !important;
                                top: auto !important;
                                bottom: 0 !important;
                                left: 0 !important;
                                right: 0 !important;
                                width: 100% !important;
                                max-width: 100% !important;
                                height: auto !important;
                                transform: none !important;
                                margin: 0 !important;
                                border-radius: 12px 12px 0 0 !important;
                                box-shadow: 0 -2px 10px rgba(0,0,0,0.1) !important;
                            }
                            .ymaps-2-1-79-balloon__content {
                                margin: 0 !important;
                                padding: 0 !important;
                            }
                            .custom-balloon {
                                width: 100% !important;
                                max-width: 100% !important;
                                border-radius: 12px 12px 0 0 !important;
                                box-sizing: border-box;
                                position: relative;
                            }
                        }
                        .custom-balloon {
                            position: relative;
                            background: #fff;
                            border-radius: 8px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                            padding: 16px;
                            min-width: 280px;
                            max-width: 575px;
                            width: auto !important;
                            box-sizing: border-box;
                            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                            font-size: 14px;
                            line-height: 1.4;
                        }
                        /* Стили для корректного отображения контента балуна */
                        .ymaps-2-1-79-balloon {
                            width: auto !important;
                            max-width: 100% !important;
                        }
                        .ymaps-2-1-79-balloon__content {
                            padding: 0 !important;
                            margin: 0 !important;
                        }
                        .custom-balloon__close-button {
                            position: absolute;
                            top: 8px;
                            right: 8px;
                            width: 24px;
                            height: 24px;
                            background: none;
                            border: none;
                            font-size: 20px;
                            line-height: 1;
                            cursor: pointer;
                            color: #999;
                            padding: 0;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .custom-balloon__close-button:hover {
                            color: #333;
                        }
                        .custom-balloon__content {
                            padding-right: 10px;
                        }
                        .custom-balloon h3 {
                            margin: 0 0 12px 0;
                            font-size: 16px;
                            color: #1e88e5;
                            font-weight: 600;
                        }
                        .custom-balloon p {
                            margin: 6px 0;
                            color: #333;
                        }
                        .custom-balloon a {
                            color: #1e88e5;
                            text-decoration: none;
                        }
                        .custom-balloon a:hover {
                            text-decoration: underline;
                        }
                        .custom-balloon .description {
                            margin-top: 10px;
                            padding-top: 10px;
                            border-top: 1px solid #eee;
                            color: #555;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            // Обрабатываем данные о бригаде, если они есть
            if (Array.isArray(brigadeMembersData) && request.brigade_id) {
                const brigadeMembers = brigadeMembersData.filter(member => member.brigade_id === request.brigade_id);
                brigadeLeader = brigadeMembers.find(member => member.is_leader);
                
                // Получаем список всех членов бригады (кроме бригадира)
                const otherMembers = brigadeMembers
                    .filter(member => !member.is_leader && member.fio)
                    .map(member => member.fio);
                
                // Собираем список: сначала бригадир, затем остальные
                brigadeMembersList = [
                    ...(brigadeLeader && brigadeLeader.fio ? [brigadeLeader.fio] : []),
                    ...otherMembers
                ];
                
                // Удаляем дубликаты и пустые значения
                brigadeMembersList = [...new Set(brigadeMembersList.filter(Boolean))];
            }
            
            // Создаем контент балуна
            const balloonContent = `
                <h3>${requestNumber}</h3>
                ${request.client_organization ? `<p><strong>Организация:</strong> ${request.client_organization}</p>` : ''}
                <p><strong>Адрес:</strong> ${address}</p>
                ${request.status ? `<p><strong>Статус:</strong> ${request.status_name}</p>` : ''}
                ${request.client_fio ? `<p><strong>Клиент:</strong> ${request.client_fio}</p>` : ''}
                ${request.client_phone ? `<p><strong>Телефон:</strong> <a href="tel:${request.client_phone}">${request.client_phone}</a></p>` : ''}
                ${request.brigade_name ? `<p><strong>Бригада:</strong> ${request.brigade_name}</p>` : ''}
                ${request.description ? `<div class="description"><strong>Описание:</strong> ${request.description}</div>` : ''}
                ${brigadeMembersList && brigadeMembersList.length > 0 ? `
                    <div><strong>Члены бригады:</strong></div>
                    <div style="">
                        ${brigadeMembersList.map(member => `${member}<br>`).join('')}
                    </div>
                ` : ''}
            `;

            const placemark = new ymaps.Placemark(coords, {
                iconContent: labelText,
                balloonContent: balloonContent,
                balloonContentHeader: requestNumber,
                balloonContentBody: address
            }, {
                iconLayout: 'default#imageWithContent',
                iconImageHref: 'data:image/svg+xml;base64,' + btoa(`
                    <svg width="22" height="100" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12,0 C5.4,0 0,5.4 0,12 C0,18.6 12,34 12,34 S24,18.6 24,12 C24,5.4 18.6,0 12,0 Z" fill="${iconColor}" stroke="white" stroke-width="1.6"/>
                        <circle cx="12" cy="12" r="4" fill="white"/>
                    </svg>
                `),
                iconImageSize: [22, 100],
                iconImageOffset: [-15, -42],
                iconContentLayout: MyIconContentLayout,
                iconContentOffset: [24, 0],
                draggable: false,
                balloonLayout: window.balloonLayout,
                balloonCloseButton: false,
                balloonPanelMaxMapArea: 0,
                balloonAutoPan: true,
                balloonOffset: [0, -40]
            });
            
            // Обработчик открытия балуна
            placemark.events.add('balloonopen', function() {
                console.log('balloonopen');

                if (isMobile) {
                    // Для мобильных устройств принудительно обновляем стили
                    setTimeout(() => {
                        const balloon = document.querySelector('.custom-balloon');
                        const balloonContainer = document.querySelector('.ymaps-2-1-79-balloon');
                        const balloonLayout = document.querySelector('.ymaps-2-1-79-balloon__layout');
                        
                        if (balloonContainer) {
                            balloonContainer.style.position = 'fixed';
                            balloonContainer.style.top = 'auto';
                            balloonContainer.style.bottom = '0';
                            balloonContainer.style.left = '0';
                            balloonContainer.style.right = '0';
                            balloonContainer.style.width = '100%';
                            balloonContainer.style.maxWidth = '100%';
                            balloonContainer.style.height = 'auto';
                            balloonContainer.style.transform = 'none';
                            balloonContainer.style.margin = '0';
                            balloonContainer.style.borderRadius = '12px 12px 0 0';
                            balloonContainer.style.boxShadow = '0 -2px 10px rgba(0,0,0,0.1)';
                        }
                        
                        if (balloonLayout) {
                            balloonLayout.style.borderRadius = '12px 12px 0 0';
                        }
                        
                        if (balloon) {
                            balloon.style.width = '100%';
                            balloon.style.maxWidth = '100%';
                            balloon.style.borderRadius = '12px 12px 0 0';
                            balloon.style.boxSizing = 'border-box';
                        }
                    }, 0);
                } else {
                    // Для десктопной версии
                    const balloon = document.querySelector('.custom-balloon');
                    if (balloon) {
                        balloon.style.minWidth = '280px';
                        balloon.style.maxWidth = '575px';
                    }
                }
            });
            
            map.geoObjects.add(placemark);
            placemarks.push(placemark);
            
            // Если это первая метка, центрируем карту на ней
            if (placemarks.length === 1) {
                map.setCenter(coords, 12);
            }
            
            return placemark;
        }

        console.log('Обработка заявок:', requests);
        
        // Счетчик для отслеживания завершения геокодирования
        let processedCount = 0;
        
        // Обрабатываем каждую заявку
        requests.forEach((request, index) => {
            // Проверяем, есть ли уже координаты
            if (request.latitude && request.longitude && !isNaN(parseFloat(request.latitude)) && !isNaN(parseFloat(request.longitude))) {
                const coords = [parseFloat(request.latitude), parseFloat(request.longitude)];
                const address = [
                    request.city_name,
                    request.street,
                    request.houses
                ].filter(Boolean).join(', ');
                
                // console.log(`Заявка ${request.id}: координаты найдены`, coords, address);
                addPlacemark(request, address, coords, index);
                processedCount++;
                
                // Если это последняя итерация, обновляем границы карты
                if (processedCount === requests.length && placemarks.length > 0) {
                    updateMapBounds();
                }
                return;
            } else {
                console.log(`Заявка ${request.id}: координаты отсутствуют или некорректны`, {
                    latitude: request.latitude,
                    longitude: request.longitude,
                    hasCoords: !!(request.latitude && request.longitude),
                    isValid: !!(request.latitude && request.longitude && !isNaN(parseFloat(request.latitude)) && !isNaN(parseFloat(request.longitude)))
                });
            }
            
            // Если координат нет, проверяем наличие адреса
            if (!request.city_name || !request.street) {
                console.warn(`Заявка ${request.id}: у заявки отсутствует полный адрес:`, {
                    city_name: request.city_name,
                    street: request.street,
                    houses: request.houses
                });
                processedCount++;
                
                // Если это последняя итерация, обновляем границы карты
                if (processedCount === requests.length && placemarks.length > 0) {
                    updateMapBounds();
                }
                return;
            }
            
            // Формируем адрес для геокодирования
            const addressParts = [
                'Россия',
                request.city_name,
                request.street,
                request.houses
            ].filter(Boolean);
            
            // Удаляем дубликаты для лучшего геокодирования
            const uniqueParts = [];
            const seen = new Set();
            for (const part of addressParts) {
                if (!seen.has(part)) {
                    seen.add(part);
                    uniqueParts.push(part);
                }
            }
            
            const address = uniqueParts.join(', ');
            console.log(`Заявка ${request.id}: геокодируем адрес:`, address);
            
            // Используем callback-подход вместо промисов
            geocoder(address, { 
                results: 1,
                json: true
            }).then(function(res) {
                try {
                    if (!res || !res.geoObjects || res.geoObjects.getLength() === 0) {
                        console.warn('Адрес не найден:', address);
                        return;
                    }
                    
                    const firstGeoObject = res.geoObjects.get(0);
                    if (!firstGeoObject) {
                        console.warn('Не удалось получить геообъект для адреса:', address);
                        return;
                    }
                    
                    try {
                        const coords = firstGeoObject.geometry.getCoordinates();
                        addPlacemark(request, address, coords, index);
                    } catch (e) {
                        console.error('Ошибка при получении координат для адреса:', address, e);
                    }
                } catch (e) {
                    console.error('Ошибка при обработке геокодирования:', e);
                } finally {
                    processedCount++;
                    console.log(`Заявка ${request.id}: обработка завершена, обработано ${processedCount} из ${requests.length}`);
                    
                    // Если это последняя итерация, обновляем границы карты
                    if (processedCount === requests.length) {
                        console.log(`Все заявки обработаны. Успешно добавлено меток: ${placemarks.length} из ${requests.length}`);
                        if (placemarks.length > 0) {
                            updateMapBounds();
                        } else {
                            console.warn('Нет меток для отображения на карте');
                            // Если ни одной метки не добавлено, показываем сообщение пользователю
                            showAlert('Не удалось определить координаты для отображения заявок на карте. Пожалуйста, проверьте наличие адресов в заявках.', 'warning');
                        }
                    }
                }
            });
        });
        
        // Функция для обновления границ карты
        function updateMapBounds() {
            try {
                if (placemarks.length > 0) {
                    map.setBounds(map.geoObjects.getBounds(), {
                        checkZoomRange: true,
                        zoomMargin: 50
                    });
                } else {
                    console.warn('Нет меток для отображения на карте');
                }
            } catch (e) {
                console.error('Ошибка при обновлении границ карты:', e);
            }
        }
        
    } catch (e) {
        console.error('Ошибка при инициализации карты:', e);
        showAlert('Произошла ошибка при загрузке карты. Пожалуйста, обновите страницу.', 'error');
    }
}

// Глобальные переменные для работы с картой
let yandexMap = null;
let isMapInitialized = false;

function showMap() {
    const mapContent = document.getElementById('map-content');
    // console.log('requestsData *:', requestsData);

    // return;

    // Проверяем фактическую видимость (класс или computed display)
    const hasHideMeClass = mapContent.classList.contains('hide-me');
    const computedDisplay = window.getComputedStyle(mapContent).display;
    const isHidden = hasHideMeClass || computedDisplay === 'none';
    
    // console.log('Состояние контейнера карты:', {
    //     hasHideMeClass: hasHideMeClass,
    //     classList: Array.from(mapContent.classList),
    //     displayStyle: mapContent.style.display,
    //     computedDisplay: computedDisplay,
    //     isHidden: isHidden
    // });
    
    // Если карта скрыта, показываем её
    if (isHidden) {
        // console.log('Карта показывается');
        
        // Удаляем класс скрытия
        mapContent.classList.remove('hide-me');
        
        // Явно устанавливаем стили для показа
        mapContent.style.display = 'block';
        mapContent.style.visibility = 'visible';
        mapContent.style.height = '800px';
        mapContent.style.padding = '1rem';
        
        // Проверяем, загружена ли API Яндекс.Карт
        if (typeof ymaps !== 'undefined') {
            // console.log('API Яндекс.Карт загружено');
            
            // Синхронизируем локальную переменную с глобальной
            if (!window.yandexMap && yandexMap) {
                // Если глобальная карта уничтожена, сбрасываем локальные переменные
                yandexMap = null;
                isMapInitialized = false;
                console.log('Карта уничтожена');
            } else if (window.yandexMap && !yandexMap) {
                yandexMap = window.yandexMap;
                isMapInitialized = true;
                console.log('Карта инициализирована');
            }
            
            // Дожидаемся готовности API перед инициализацией
            ymaps.ready(function() {
                if (yandexMap && isMapInitialized) {
                    // console.log('Карта уже инициализирована, обновляем метки');
                    // Очищаем карту от старых меток
                    yandexMap.geoObjects.removeAll();
                    // Перезагружаем данные и метки
                    initYandexMap();
                } else {
                    // console.log('Инициализируем карту в первый раз');
                    initYandexMap();
                }
            });
        } else {
            // console.error('API Яндекс.Карт не загружено');
            // loadYandexMaps(); // Пытаемся загрузить API, если оно не загружено
        }
    } else {
        // Карта скрывается
        // console.log('Карта скрывается');
        
        // Добавляем класс скрытия
        mapContent.classList.add('hide-me');
        
        // Устанавливаем inline-стили для скрытия
        mapContent.style.display = 'none';
        mapContent.style.visibility = 'hidden';
        mapContent.style.height = '0';
        mapContent.style.padding = '0';
    }

    console.log('END');
}

function initOpenMapBtn() {
    const btnOpenMap = document.getElementById('btn-open-map');
    
    btnOpenMap.addEventListener('click', function() {
        console.log('Кнопка открытия карты нажата');

        // const requestsData = localStorage.getItem('requestsData');

        showMap();
    });
}

// Обработчик для кнопки экспорта отчета в Excel
function initExportReportBtn() {
    console.log('Функция initExportReportBtn вызвана');

    // return;
    
    const exportBtn = document.getElementById('export-report-btn');
    
    exportBtn.addEventListener('click', function() {
        console.log('Кнопка экспорта нажата');

        showAlert('Функционал экспорта отчета в Excel в разработке', 'warning');

        return;
        
        try {
            // Получаем данные из localStorage
            const reportData = JSON.parse(localStorage.getItem('reportData'));

            console.log('reportData:', reportData);

            const requests = reportData.requests;

            console.log('requests:', requests);

            const brigadeMembers = reportData.brigadeMembers;

            console.log('brigadeMembers:', brigadeMembers);

            const comments_by_request = reportData.comments_by_request;

            console.log('comments_by_request:', comments_by_request);
            
            if (!reportData || !reportData.requests || reportData.requests.length === 0) {
                showAlert('Нет данных для экспорта', 'warning');
                return;
            }

            // Проверяем, загружена ли библиотека XLSX
            if (typeof XLSX === 'undefined') {
                showAlert('Библиотека для экспорта не загружена', 'error');
            }

            // Подготавливаем данные для экспорта
            const exportData = [];
            
            // Добавляем заголовки вручную, чтобы избежать дублирования
            const headers = {
                'requests': 'Заявки',
                'brigade': 'Члены бригады',
                'comments': 'Комментарии'
            };
            
            // Обрабатываем каждую заявку
            requests.forEach(request => {
                let commentsText = '';
                
                try {
                    // Получаем ID заявки (проверяем все возможные варианты ID)
                    const requestId = request.id || request.request_id || request.requestId;
                    
                    if (!requestId) {
                        console.warn('У заявки отсутствует ID:', request);
                        commentsText = 'Ошибка: у заявки отсутствует ID';
                    } else {
                        // Ищем комментарии для текущей заявки
                        const comments = comments_by_request && comments_by_request[requestId]
                            ? Array.isArray(comments_by_request[requestId]) 
                                ? comments_by_request[requestId] 
                                : []
                            : [];
                        
                        if (comments.length > 0) {
                            commentsText = comments
                                .filter(comment => comment) // Фильтруем пустые комментарии
                                .map(comment => {
                                    try {
                                        const date = comment.created_at 
                                            ? new Date(comment.created_at).toLocaleString('ru-RU') 
                                            : 'Без даты';
                                        const text = comment.comment || 'Без текста';
                                        const author = comment.employee_full_name || comment.employee_name || 'Неизвестный автор';
                                        return `${date}: ${text} (${author})`;
                                    } catch (e) {
                                        console.error('Ошибка при обработке комментария:', e, comment);
                                        return null;
                                    }
                                })
                                .filter(Boolean) // Удаляем null из-за ошибок обработки
                                .join('\n\n');
                        } else {
                            commentsText = 'Нет комментариев';
                        }
                    }
                } catch (e) {
                    console.error('Ошибка при обработке комментариев для заявки:', request, e);
                }

                // Получаем членов бригады для текущей заявки
                let brigadeText = '';
                if (request.brigade_id) {
                    try {
                        // Находим всех членов бригады по brigade_id (нестрогое сравнение)
                        const brigadeId = request.brigade_id;
                        const brigadeMembersList = Array.isArray(brigadeMembers) 
                            ? brigadeMembers.filter(member => 
                                member && (member.brigade_id === brigadeId || member.brigadeId === brigadeId)
                            )
                            : [];
                        
                        if (brigadeMembersList.length > 0) {
                            brigadeText = brigadeMembersList
                                .map(member => {
                                    if (!member) return null;
                                    if (member.full_name) return member.full_name;
                                    if (member.name || member.surname) {
                                        return `${member.name || ''} ${member.surname || ''}`.trim();
                                    }
                                    if (member.employee_full_name) return member.employee_full_name;
                                    if (member.employee_name) return member.employee_name;
                                    return null;
                                })
                                .filter(Boolean) // Удаляем пустые строки
                                .join('\n');
                        } else {
                            brigadeText = 'Нет данных о бригаде';
                        }
                    } catch (e) {
                        console.error('Ошибка при обработке бригады:', e);
                        brigadeText = 'Ошибка загрузки состава бригады';
                    }
                } else {
                    brigadeText = 'Бригада не назначена';
                }

                // Формируем информацию о заявке
                const requestInfo = [
                    `№: ${request.number}`,
                    `Дата: ${request.request_date}`,
                    `Статус: ${request.status_name}`,
                    `Клиент: ${request.client_fio}`,
                    `Тел.: ${request.client_phone}`,
                    `Орг.: ${request.client_organization}`,
                    `Адрес: ${request.street || ''} ${request.houses || ''}`,
                    `Район: ${request.district || ''}`,
                    `Ответственный: ${request.operator_name || ''}`
                ].filter(Boolean).join('\n');

                exportData.push({
                    'Заявки': requestInfo,
                    'Члены бригады': brigadeText,
                    'Комментарии': commentsText
                });
            });

            // Создаем новую книгу Excel
            const wb = XLSX.utils.book_new();
            
            // Создаем лист с данными
            const ws = XLSX.utils.json_to_sheet(exportData, {skipHeader: true});
            
            // Добавляем заголовки вручную, чтобы избежать дублирования
            const headerRange = XLSX.utils.decode_range(ws['!ref']);
            
            // Добавляем заголовки
            Object.keys(headers).forEach((key, index) => {
                const cellAddress = XLSX.utils.encode_cell({r: 0, c: index});
                ws[cellAddress] = {v: headers[key], t: 's', s: {font: {bold: true}, alignment: {wrapText: true}}};
            });
            
            // Устанавливаем ширину колонок
            const colWidths = [
                {wch: 40},  // Заявки
                {wch: 30},  // Члены бригады
                {wch: 80}   // Комментарии
            ];
            ws['!cols'] = colWidths;
            
            // Устанавливаем форматирование для всех ячеек
            const range = XLSX.utils.decode_range(ws['!ref']);
            
            // Проходим по всем строкам и столбцам
            for (let R = range.s.r; R <= range.e.r; ++R) {
                // Устанавливаем высоту строки
                if (!ws['!rows']) ws['!rows'] = [];
                if (!ws['!rows'][R]) ws['!rows'][R] = {};
                ws['!rows'][R].hpt = 60; // Высота строки в пикселях
                
                for (let C = range.s.c; C <= range.e.c; ++C) {
                    const cellAddress = XLSX.utils.encode_cell({r: R, c: C});
                    
                    // Если ячейка не существует, создаем пустую
                    if (!ws[cellAddress]) {
                        ws[cellAddress] = { t: 's', v: '' };
                    }
                    
                    // Устанавливаем стили для ячейки
                    ws[cellAddress].s = {
                        alignment: {
                            wrapText: true,  // Включаем перенос текста
                            vertical: 'top',  // Выравнивание по верхнему краю
                            horizontal: 'left', // Выравнивание по левому краю
                            shrinkToFit: false // Отключаем сжатие текста
                        },
                        border: {
                            top: { style: 'thin', color: { rgb: 'D3D3D3' } },
                            bottom: { style: 'thin', color: { rgb: 'D3D3D3' } },
                            left: { style: 'thin', color: { rgb: 'D3D3D3' } },
                            right: { style: 'thin', color: { rgb: 'D3D3D3' } }
                        }
                    };
                }
            }
            
            // Делаем заголовки жирными
            for (let C = range.s.c; C <= range.e.c; ++C) {
                const cellAddress = XLSX.utils.encode_cell({r: 0, c: C});
                if (ws[cellAddress]) {
                    ws[cellAddress].s.font = { bold: true };
                }
            }
            
            // Добавляем лист в книгу
            XLSX.utils.book_append_sheet(wb, ws, 'Заявки');
            
            // Генерируем имя файла с текущей датой
            const date = new Date().toISOString().split('T')[0];
            const fileName = `Заявки_${date}.xlsx`;
            
            // Скачиваем файл
            XLSX.writeFile(wb, fileName);
            
            console.log('Экспорт в Excel выполнен успешно');
        } catch (error) {
            console.error('Ошибка при экспорте в Excel:', error);
            showAlert('Произошла ошибка при экспорте в Excel', 'error');
        }
    });
}

// Обработчик для кнопки скачивания zip-архива всех фото
function initDownloadAllPhotos() {
    const downloadAllPhotosBtn = document.querySelector('.download-all-photos-btn');
    
    if (downloadAllPhotosBtn) {
        downloadAllPhotosBtn.addEventListener('click', async function() {
            console.log('Кнопка скачивания архива всех фото нажата');

            showAlert('Подготовка архива всех фото в разработке', 'info');

            return;

            // Показываем индикатор загрузки
            const originalText = downloadAllPhotosBtn.innerHTML;
            downloadAllPhotosBtn.disabled = true;
            downloadAllPhotosBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Подготовка архива...';
            
            try {
                // Отправляем запрос на создание и скачивание архива
                const response = await fetch('/download-all-photos', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/zip, application/json',
                    },
                    responseType: 'blob'
                });

                if (!response.ok) {
                    const error = await response.json().catch(() => null);
                    throw new Error(error?.message || `Ошибка сервера: ${response.status}`);
                }

                // Получаем blob с архивом
                const blob = await response.blob();
                
                // Проверяем, что это действительно архив
                if (blob.type !== 'application/zip' && blob.type !== 'application/x-zip-compressed') {
                    const errorText = await blob.text();
                    try {
                        const errorData = JSON.parse(errorText);
                        throw new Error(errorData.message || 'Неверный формат ответа от сервера');
                    } catch (e) {
                        throw new Error('Ожидался zip-архив, но получен неверный формат данных');
                    }
                }
                
                // Создаем ссылку для скачивания
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                
                // Получаем имя файла из заголовка Content-Disposition или используем имя по умолчанию
                const contentDisposition = response.headers.get('Content-Disposition');
                let filename = 'photos_archive_' + new Date().toISOString().slice(0, 10) + '.zip';
                if (contentDisposition) {
                    const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                    if (filenameMatch != null && filenameMatch[1]) {
                        filename = filenameMatch[1].replace(/['"]/g, '');
                    }
                }
                
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                
                // Очистка
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                // Показываем уведомление об успешном скачивании
                showAlert('Архив с фотографиями успешно загружается', 'success');
                
            } catch (error) {
                console.error('Ошибка при скачивании архива:', error);
                showAlert('Произошла ошибка при подготовке архива: ' + error.message, 'danger');
            } finally {
                // Восстанавливаем кнопку в исходное состояние
                downloadAllPhotosBtn.disabled = false;
                downloadAllPhotosBtn.innerHTML = originalText;
            }
        });
    }
}

// Функция для загрузки списка фотоотчетов
async function initPhotoReportList(requestId) {
    return;

    const container = document.getElementById('photo-reports-list');
    
    if (!container) return;

    // container.innerHTML = `<div class="text-muted">Загрузка тестовых фото для заявки ${requestId ? '#' + requestId : ''}...</div>`;

    // Имитируем загрузку
    // await new Promise(r => setTimeout(r, 1400));

    // console.log(container);

    // Загрузка реальных фото
    let response;
    try {
        // Получаем CSRF токен из мета-тега (стандартный способ в Laravel)
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         (window.Laravel && window.Laravel.csrfToken) || '';

        response = await fetch(`/photo-list`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            // body: JSON.stringify({ request_id: requestId })
        }); 
    } catch (e) {
        console.error('Ошибка загрузки фотоотчета:', e);
        showAlert('Не удалось загрузить фотоотчет', 'danger');
        return;
    }

    // Обрабатываем ответ от сервера
    const responseData = await response.json();
    console.log('Ответ от сервера:', responseData);
    
    // Проверяем структуру ответа и извлекаем данные
    const photos = Array.isArray(responseData) 
        ? responseData 
        : (responseData.data || []);

    console.log('Фотографии:', photos);

    // Мок-данные картинок
    // const photos = [1, 2, 3, 4, 5, 6].map(i => ({
    //     url: `https://placehold.co/300x200?text=Photo+${i}`,
    //     id: i
    // }));

    if (!photos.length) {
        container.innerHTML = '<div class="text-muted">Фото не найдены</div>';
        return;
    }

    // Функция для скачивания файлов
    function downloadFiles(files, zipName) {
        // Проверяем, поддерживает ли браузер API для работы с ZIP
        if (typeof JSZip === 'undefined') {
            alert('Для скачивания архива загрузите библиотеку JSZip');
            return;
        }

        const zip = new JSZip();
        const promises = [];
        
        files.forEach((file, index) => {
            const promise = fetch(file.url)
                .then(response => response.blob())
                .then(blob => {
                    const fileName = file.original_name || `photo_${index + 1}.jpg`;
                    zip.file(fileName, blob);
                });
            promises.push(promise);
        });

        Promise.all(promises).then(() => {
            zip.generateAsync({type: 'blob'})
                .then(content => {
                    const url = URL.createObjectURL(content);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${zipName}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
        });
    }

    // Добавляем скрипт JSZip, если его еще нет
    if (typeof JSZip === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
        script.integrity = 'sha512-XMVd28F1oH/O71fzwBnV7HucLxVwtxf26XV8P4wPk26EDxuGZ91N8bsOttmnomcCD3CS5ZMRL50H0GgOHvegtg==';
        script.crossOrigin = 'anonymous';
        script.referrerPolicy = 'no-referrer';
        document.head.appendChild(script);
    }

    // Создаем объект для хранения всех фотографий
    const allPhotosMap = {};

    // Рендер превью с группировкой по заявкам и комментариям
    container.innerHTML = `
        ${photos.map(request => {
            // Собираем все фото заявки
            const allRequestPhotos = request.comments.flatMap(comment => 
                (comment.photos || []).map(photo => {
                    const photoWithIds = {
                        ...photo,
                        requestId: request.id,
                        requestNumber: request.number,
                        commentId: comment.id,
                        commentDate: comment.created_at
                    };
                    
                    // Сохраняем фото в общий объект
                    if (!allPhotosMap[request.id]) {
                        allPhotosMap[request.id] = [];
                    }
                    allPhotosMap[request.id].push(photoWithIds);
                    
                    if (!allPhotosMap[`comment-${comment.id}`]) {
                        allPhotosMap[`comment-${comment.id}`] = [];
                    }
                    allPhotosMap[`comment-${comment.id}`].push(photoWithIds);
                    
                    return photoWithIds;
                })
            );
            
            return `
                <div class="card mb-4" id="request-${request.id}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Заявка #${request.number}</h5>
                        <button class="btn btn-sm btn-outline-primary download-request" 
                                data-request-id="${request.id}">
                            <i class="bi bi-download me-1"></i> Скачать все (${allRequestPhotos.length})
                        </button>
                    </div>
                    <div class="card-body">
                        ${request.comments.map(comment => {
                            const hasPhotos = comment.photos && comment.photos.length > 0;
                            const commentDate = new Date(comment.created_at);
                            
                            return `
                                <div class="mb-4 comment-container" id="comment-${comment.id}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            ${commentDate.toLocaleString('ru-RU')}
                                        </small>
                                        ${hasPhotos ? `
                                            <button class="btn btn-sm btn-outline-secondary download-comment" 
                                                    data-comment-id="${comment.id}">
                                                <i class="bi bi-download me-1"></i> Скачать (${comment.photos.length})
                                            </button>
                                        ` : ''}
                                    </div>
                                    <div class="card mb-2">
                                        <div class="card-body">
                                            <p class="mb-0">${comment.text}</p>
                                        </div>
                                    </div>
                                    ${hasPhotos ? `
                                    <div class="mb-3">
                                        <h6 class="small text-muted mb-2">Прикрепленные фото (${comment.photos.length}):</h6>
                                        <div class="row g-2">
                                            ${comment.photos.map(photo => {
                                                const name = photo.original_name || 'Фото';
                                                return `
                                                    <div class="col-6 col-sm-4 col-lg-3 mb-3">
                                                        <div class="square-image-container">
                                                            <img src="${photo.url}" 
                                                                 class="img-fluid square-image" 
                                                                 alt="${name}"
                                                                 onerror="this.onerror=null; this.src='https://placehold.co/300?text=Ошибка+загрузки'"
                                                            >
                                                        </div>
                                                        <div class="mt-2 text-center small text-truncate" title="${name}">
                                                            ${name}
                                                        </div>
                                                    </div>
                                                `;
                                            }).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>`;
        }).join('')}`;
        
    // Добавляем обработчики для кнопок скачивания
    document.querySelectorAll('.download-request').forEach(button => {
        button.addEventListener('click', (e) => {
            const requestId = e.currentTarget.dataset.requestId;
            const photos = allPhotosMap[requestId] || [];
            const requestNumber = photos[0] ? photos[0].requestNumber : requestId;
            const downloadButton = e.currentTarget; // Сохраняем ссылку на кнопку
            
            if (photos.length === 0) {
                alert('Нет фотографий для скачивания');
                return;
            }
            
            downloadButton.innerHTML = '<i class="bi bi-hourglass me-1"></i> Архивация...';
            downloadButton.disabled = true;
            
            downloadFiles(
                photos, 
                `Заявка-${requestNumber}-${new Date().toISOString().split('T')[0]}`
            );
            
            // Используем сохраненную ссылку на кнопку
            setTimeout(() => {
                if (downloadButton && downloadButton.parentNode) {
                    downloadButton.innerHTML = `<i class="bi bi-download me-1"></i> Скачать все (${photos.length})`;
                    downloadButton.disabled = false;
                }
            }, 3000);
        });
    });
    
    // Обработчик для кнопки скачивания комментария (старая версия)
    document.querySelectorAll('.download-comment').forEach(button => {
        button.addEventListener('click', (e) => {
            const commentId = e.currentTarget.dataset.commentId;
            const photos = allPhotosMap[`comment-${commentId}`] || [];
            const downloadButton = e.currentTarget; // Сохраняем ссылку на кнопку
            
            if (photos.length === 0) {
                alert('Нет фотографий для скачивания');
                return;
            }
            
            downloadButton.innerHTML = '<i class="bi bi-hourglass me-1"></i> Архивация...';
            downloadButton.disabled = true;
            
            const commentDate = new Date(photos[0] ? photos[0].commentDate : new Date())
                .toISOString()
                .replace(/[:.]/g, '-')
                .split('T')[0];
                
            downloadFiles(
                photos, 
                `Комментарий-${commentId}-${commentDate}`
            );
            
            // Используем сохраненную ссылку на кнопку
            setTimeout(() => {
                if (downloadButton && downloadButton.parentNode) {
                    downloadButton.innerHTML = `<i class="bi bi-download me-1"></i> Скачать (${photos.length})`;
                    downloadButton.disabled = false;
                }
            }, 3000);
        });
    });
}

// Обработчик для кнопки показа фото в футере модалки комментариев
export function initShowPhotosButton() {
    const btn = document.getElementById('showPhotosBtn');
    if (!btn) return;

    // Сбрасываем возможные предыдущие обработчики
    const cloned = btn.cloneNode(true);
    btn.parentNode.replaceChild(cloned, btn);

    // Добавляем атрибут для отслеживания состояния
    let isPhotosShown = false;
    const container = document.getElementById('photoReportContainer');
    
    // Функция для скрытия фотографий
    const hidePhotos = () => {
        if (container) {
            container.innerHTML = '';
        }
        cloned.innerHTML = '<i class="bi bi-images me-1"></i> Показать все фото';
        isPhotosShown = false;
    };

    cloned.addEventListener('click', async () => {
        // Если фотографии уже показаны, скрываем их
        if (isPhotosShown) {
            hidePhotos();
            return;
        }

        const requestId = document.getElementById('commentRequestId')?.value || '';
        if (!container) return;

        console.log(requestId);

        // Загрузка реальных фото
        let response;
        try {
            response = await fetchData(`/api/photo-report/${requestId}`);
        } catch (e) {
            console.error('Ошибка загрузки фотоотчета:', e);
            container.innerHTML = '<div class="text-danger">Не удалось загрузить фотоотчет</div>';
            return;
        }

        // API возвращает { success, message, data: [] }
        const photos = Array.isArray(response) ? response : (response?.data || []);

        console.log(response);

        if (!photos.length) {
            container.innerHTML = '<div class="text-muted">Фото не найдены</div>';
            return;
        }

        // Рендер превью с локальными ссылками и метаданными
        container.innerHTML = `
            <div class="row g-2">
                ${photos.map(p => {
                    const name = p.original_name || `Фото ${p.id}`;
                    const sizeKB = p.file_size ? Math.round(p.file_size / 1024) + ' KB' : '';
                    const created = p.created_at ? `<div class="small text-muted">${p.created_at}</div>` : '';
                    const url = p.url;
                    return `
                        <div class="col-6 col-md-4">
                            <div class="card h-100">
                                <a href="${url}" target="_blank" rel="noopener" class="text-decoration-none">
                                    <img src="${url}" alt="${name}" class="card-img-top" loading="lazy"
                                         onerror="this.onerror=null;this.src='https://placehold.co/300x200?text=No+Image';">
                                </a>
                                <div class="card-body p-2">
                                    <div class="small" title="${name}">${name}</div>
                                    <div class="small text-muted">${sizeKB}</div>
                                    ${created}
                                </div>
                                <div class="card-footer p-2">
                                    <a href="${url}" download class="btn btn-sm btn-outline-secondary w-100">Скачать</a>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
        
        // Обновляем состояние и текст кнопки
        cloned.innerHTML = '<i class="bi bi-x-circle me-1"></i> Скрыть фото';
        isPhotosShown = true;
    });
}

// Функция для преобразования даты из формата YYYY-MM-DD в DD.MM.YYYY
export function formatDateToDisplay(dateStr) {
    if (!dateStr) return '';
    const [year, month, day] = dateStr.split('-');
    return `${day}.${month}.${year}`;
}

// Глобальная переменная для хранения текущей даты
export const currentDateState = {
    // Инициализируем текущей датой в формате DD.MM.YYYY
    date: new Date().toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    })
};

// Структура для хранения выбранной в календаре даты
// Экспортируемый объект состояния даты
export const selectedDateState = {
    // Инициализируем текущей датой в формате DD.MM.YYYY
    date: new Date().toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    }),
    // Метод для обновления даты из календаря
    updateDate(newDate) {
        this.date = newDate;
        // console.log('Дата в selectedDateState обновлена:', this.date);
    }
};

export const executionDateState = {
    // Инициализируем текущей датой в формате DD.MM.YYYY
    date: null,
    // Метод для обновления даты из календаря
    updateDate(newDate) {
        // Проверяем формат даты и преобразуем его при необходимости
        if (newDate && typeof newDate === 'string') {
            // Если дата в формате YYYY-MM-DD, преобразуем в DD.MM.YYYY
            if (newDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                const dateParts = newDate.split('-');
                this.date = `${dateParts[2]}.${dateParts[1]}.${dateParts[0]}`;
                // console.log('Дата преобразована из YYYY-MM-DD в DD.MM.YYYY:', this.date);
                return;
            }
        }
        // Если формат другой или преобразование не требуется, сохраняем как есть
        this.date = newDate;
        // console.log('Дата в executionDateState обновлена:', this.date);
    }
};

export const selectedRequestState = {
    id: null,
    number: null,
    execution_date: null,
    status_name: null,
    status_color: null,
    street: null,
    houses: null,
    district: null,
    client_phone: null,
    operator_name: null,
    request_date: null,
    brigade_id: null,

    updateRequest(newRequest) {
        this.id = newRequest.id;
        this.number = newRequest.number;
        this.execution_date = newRequest.execution_date;
        this.status_name = newRequest.status_name;
        this.status_color = newRequest.status_color;
        this.street = newRequest.street;
        this.houses = newRequest.houses;
        this.latitudeEdit = newRequest.latitudeEdit;
        this.longitudeEdit = newRequest.longitudeEdit;
        this.district = newRequest.district;
        this.client_phone = newRequest.client_phone;
        this.operator_name = newRequest.operator_name;
        this.request_date = newRequest.request_date;
        this.brigade_id = newRequest.brigade_id;
    },

    clearRequest() {
        this.id = null;
        this.number = null;
        this.execution_date = null;
        this.status_name = null;
        this.status_color = null;
        this.street = null;
        this.houses = null;
        this.latitudeEdit = null;
        this.longitudeEdit = null;
        this.district = null;
        this.client_phone = null;
        this.operator_name = null;
        this.request_date = null;
        this.brigade_id = null;
    },

    updateStatus(newStatus, newColor = null) {
        this.status_name = newStatus;
        if (newColor) {
            this.status_color = newColor;
        }
    },

    updateExecutionDate(newDate) {
        this.execution_date = newDate;
    },

    updateAddress(newAddress) {
        this.street = newAddress.street;
        this.houses = newAddress.houses;
        this.district = newAddress.district;
    },

    updateClientPhone(newPhone) {
        this.client_phone = newPhone;
    },

    updateOperatorName(newName) {
        this.operator_name = newName;
    },

    updateRequestDate(newDate) {
        this.request_date = newDate;
    },

    updateBrigadeId(newId) {
        this.brigade_id = newId;
    },

    // Обработчики событий
    listeners: {
        onStatusChange: [],
        onDateChange: [],
        onRequestChange: []
    },

    // Методы для добавления слушателей
    addStatusChangeListener(callback) {
        this.listeners.onStatusChange.push(callback);
    },

    // Методы для вызова слушателей
    notifyStatusChange(oldStatus, newStatus) {
        this.listeners.onStatusChange.forEach(callback =>
            callback(oldStatus, newStatus, this));
    }
};

// Добавляем объект в глобальную область видимости для обратной совместимости
window.selectedDateState = selectedDateState;
window.executionDateState = executionDateState;

/**
 * Отображает информацию о сотруднике в блоке employeeInfo
 * @param {Object} employeeData - данные о сотруднике
 */
function displayEmployeeInfo(employeeData) {
    const employeeInfoBlock = document.getElementById('employeeInfo');

    if (!employeeInfoBlock || !employeeData) return;

    // console.log('Получены данные сотрудника:', employeeData);

    // Форматирование даты рождения, если она есть
    const birthDate = employeeData.birth_date ? new Date(employeeData.birth_date).toLocaleDateString('ru-RU') : 'Не указана';

    // Форматирование даты выдачи паспорта, если она есть
    const passportIssuedAt = employeeData.passport && employeeData.passport.issued_at
        ? new Date(employeeData.passport.issued_at).toLocaleDateString('ru-RU')
        : 'Не указана';

    // Подготовка блока с паспортными данными, если они есть
    let passportHtml = '';

    if (employeeData.passport) {
        passportHtml = `
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">Паспортные данные</div>
                <div class="card-body">
                    <p><strong>Серия и номер:</strong> ${employeeData.passport.series_number || 'Не указаны'}</p>
                    <p><strong>Кем выдан:</strong> ${employeeData.passport.issued_by || 'Не указано'}</p>
                    <p><strong>Дата выдачи:</strong> ${passportIssuedAt}</p>
                    <p><strong>Код подразделения:</strong> ${employeeData.passport.department_code || 'Не указан'}</p>
                </div>
            </div>
        `;
    }

    // Подготовка блока с данными об автомобиле, если они есть
    let carHtml = '';
    if (employeeData.car) {
        carHtml = `
            <div class="card mb-3">
                <div class="card-header bg-secondary text-white">Данные об автомобиле</div>
                <div class="card-body">
                    <p><strong>Марка:</strong> ${employeeData.car.brand || 'Не указана'}</p>
                    <p><strong>Госномер:</strong> ${employeeData.car.license_plate || 'Не указан'}</p>
                </div>
            </div>
        `;
    }

    // Создаем HTML для отображения основной информации
    const mainInfoHtml = `
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">Информация о сотруднике ID: ${employeeData.employee_id || employeeData.id || ''}</div>
            <div class="card-body">
                <p><strong>ФИО:</strong> ${employeeData.fio || 'Не указано'}</p>
                <p><strong>Телефон:</strong> ${employeeData.phone || 'Не указан'}</p>
                <p><strong>Дата рождения:</strong> ${birthDate}</p>
                <p><strong>Место рождения:</strong> ${employeeData.birth_place || 'Не указано'}</p>
                <p><strong>Место регистрации:</strong> ${employeeData.registration_place || 'Не указано'}</p>
                <p><strong>Должность:</strong> ${employeeData.position || 'Не указана'}</p>
            </div>
        </div>
    `;

    const btnUpdate = `
        <button id="editBtn" type="button" class="btn btn-primary w-100 mt-3
        ">Изменить</button>
    `;

    // Собираем все блоки вместе
    const html = mainInfoHtml + passportHtml + carHtml;


    // Вставляем HTML в блок
    employeeInfoBlock.innerHTML = html;
    employeeInfoBlock.style.display = 'block';
}

/**
 * Добавляет новую заявку в таблицу
 * @param {Object} result - результат ответа сервера
 */
function addRequestToTable(result) {
    console.log('Начало функции addRequestToTable', result);

    if (!result.data) {
        console.error('Отсутствует result.data');
        return false;
    }

    if (!result.data.request) {
        console.error('Отсутствует result.data.request');
        return false;
    }

    const requestData = result.data.request;
    const clientData = result.data.client || {};
    const clientOrganization = clientData.organization || '';
    const addressData = result.data.address || {};
    const commentData = result.data.comment || {};

    console.log('Данные заявки:', requestData);
    console.log('Данные клиента:', clientData);
    console.log('Данные адреса:', addressData);
    console.log('Данные комментария:', commentData);

    // Исправление обработки комментария
    // Проверяем разные варианты структуры данных комментария
    let extractedComment = '';
    if (commentData && commentData.text) {
        extractedComment = commentData.text;
    } else if (commentData && commentData.comment) {
        extractedComment = commentData.comment;
    } else if (requestData && requestData.comment) {
        extractedComment = requestData.comment;
    } else if (result.data && result.data.comments && result.data.comments.length > 0) {
        extractedComment = result.data.comments[0].text || result.data.comments[0].comment || '';
    }

    console.log('Извлеченный текст комментария:', extractedComment);

    // Попробуем найти таблицу заявок с разными селекторами
    let requestsTable = document.querySelector('.table.table-hover.align-middle tbody');

    if (!requestsTable) {
        console.log('Попытка найти таблицу с другим селектором');
        requestsTable = document.querySelector('#requestsTab table tbody');
    }

    if (!requestsTable) {
        console.error('Не найдена таблица заявок');
        return false;
    }

    // console.log('Таблица найдена:', requestsTable);

    // Получаем текущую дату для отображения
    const currentDate = new Date();
    const formattedDate = currentDate.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });

    // Формируем адрес из данных адреса
    const street = addressData.street || '';
    const house = addressData.house || '';
    const cityPrefix = addressData.city_name && addressData.city_name !== 'Москва' ? `${addressData.city_name}, ` : '';
    const fullAddress = `${cityPrefix}ул. ${street}, ${house}`.trim();
    
    // Создаем новую строку для таблицы
    const newRow = document.createElement('tr');
    newRow.id = `request-${requestData.id}`;
    newRow.dataset.requestNumber = requestData.number || '';
    newRow.dataset.address = fullAddress;
    newRow.className = 'align-middle status-row new-row';
    newRow.style.setProperty('--status-color', requestData.status_color || '#e2e0e6');

    // Получаем комментарий, если есть
    const commentText = commentData.comment || requestData.comment || '';

    // Формируем содержимое строки
    newRow.innerHTML = `
        <!-- Номер заявки -->
        <td class="col-number">1</td>
        <!-- Клиент -->
        <td class="col-address">
            <div class="text-dark col-address__organization">${clientOrganization}</div>
            <small class="text-dark d-block col-address__street" data-bs-toggle="tooltip" data-bs-original-title="${fullAddress}">
                <strong>${fullAddress}</strong>
            </small>
            <div class="text-dark font-size-0-8rem"><i>${clientData.fio || requestData.client_fio}</i></div>
            <small class="text-black d-block font-size-0-8rem">
                <i>${clientData.phone || requestData.client_phone || 'Нет телефона'}</i>
            </small>
        </td>
        <!-- Комментарий -->
        <td class="col-comment">
            <div class="col-date__date">${requestData.execution_date ? new Date(requestData.execution_date).toLocaleDateString('ru-RU') : formattedDate} | ${requestData.number || 'REQ-' + formattedDate.replace(/\./g, '') + '-' + String(requestData.id).padStart(4, '0')}</div>
            ${extractedComment ? `
                <div class="comment-preview small text-dark" data-bs-toggle="tooltip">
                    <p class="comment-preview-title">Печатный комментарий:</p>
                    <div data-comment-request-id="${requestData.id}" class="comment-preview-text">${extractedComment}</div>
                </div>
                <div class="mt-1">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary view-comments-btn p-1"
                            data-bs-toggle="modal"
                            data-bs-target="#commentsModal"
                            data-request-id="${requestData.id}"
                            style="position: relative; z-index: 1;">
                        <i class="bi bi-chat-left-text me-1"></i>
                        <span class="badge bg-primary rounded-pill ms-1">
                            1
                        </span>
                    </button>
                    <button data-request-id="${requestData.id}" type="button" class="btn btn-sm btn-custom-brown p-1 close-request-btn">
                        Закрыть заявку
                    </button>
                </div>
            ` : ''}
        </td>
        <td class="col-brigade">
            <div data-name="brigadeMembers" class="col-brigade__div">
                Не назначена
            </div>
        </td>

        ${requestData.isAdmin ? `
        <!-- Action Buttons Group -->
        <td class="col-actions text-nowrap">
            <div class="col-actions__div d-flex flex-column gap-1">
                <button type="button" 
                        class="btn btn-sm btn-outline-primary assign-team-btn p-1" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="left" 
                        data-bs-title="Назначить бригаду"
                        data-request-id="${requestData.id}">
                    <i class="bi bi-people"></i>
                </button>
                <button type="button" 
                        class="btn btn-sm btn-outline-success transfer-request-btn p-1"
                        data-bs-toggle="tooltip" 
                        data-bs-placement="left" 
                        data-bs-title="Перенести заявку"
                        style="--bs-btn-color: #198754; --bs-btn-border-color: #198754; --bs-btn-hover-bg: rgba(25, 135, 84, 0.1); --bs-btn-hover-border-color: #198754;"
                        data-request-id="${requestData.id}">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
                <button type="button" 
                        class="btn btn-sm btn-outline-danger cancel-request-btn p-1" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="left" 
                        data-bs-title="Отменить заявку"
                        data-request-id="${requestData.id}">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        </td>
        ` : ''}
    `;

    // Добавляем строку в начало таблицы
    const firstRow = requestsTable.querySelector('tr');
    if (firstRow) {
        requestsTable.insertBefore(newRow, firstRow);
    } else {
        requestsTable.appendChild(newRow);
    }

    // Инициализируем тултипы Bootstrap для новых элементов
    const tooltips = newRow.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });

    // Обновляем нумерацию строк
    updateRowNumbers();

    // console.log('Строка успешно добавлена в таблицу');
    return true;
}

/**
 * Обновляет нумерацию строк в таблице заявок
 */
function updateRowNumbers() {
    // console.log('Обновление нумерации строк');

    // Попробуем найти таблицу заявок с разными селекторами
    let rows = document.querySelectorAll('.table.table-hover.align-middle tbody tr');

    if (!rows.length) {
        rows = document.querySelectorAll('#requestsTab table tbody tr');
    }

    // console.log('Найдено строк для обновления:', rows.length);

    rows.forEach((row, index) => {
        const numberCell = row.querySelector('td:first-child');
        if (numberCell) {
            numberCell.textContent = index + 1;
        }
    });
}

// Добавляем обработчик события ввода для поля комментария
function initCommentValidation() {
    const commentField = document.getElementById('comment');
    if (commentField) {
        commentField.addEventListener('input', function() {
            // Если пользователь начал вводить текст, убираем класс is-invalid
            if (this.value.length >= 3) {
                this.classList.remove('is-invalid');
            }
        });
    }
}

// Функция для инициализации обработчиков комментариев
export function initCommentHandlers() {
    initCommentValidation();
    
    commentPhotoDownload();
}

function commentPhotoDownload() {
    // Обработчик для кнопки скачивания файлов для zip архивирования
    document.addEventListener('click', async function(e) {
        const downloadFilesBtn = e.target.closest('.download-comment-files-btn');
        if (!downloadFilesBtn) return;

        console.log('Кнопка скачивания файлов для zip архивирования нажата', downloadFilesBtn);
        
        e.preventDefault();

        const commentId = downloadFilesBtn.dataset.commentId;
        const originalHtml = downloadFilesBtn.innerHTML;

        try {
            // Показываем индикатор загрузки
            downloadFilesBtn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Загрузка...';
            downloadFilesBtn.disabled = true;

            // Загружаем файлы комментария
            const response = await fetch(`/api/comments/${commentId}/files`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            
            if (!response.ok) {
                throw new Error('Ошибка при загрузке файлов');
            }
            
            const data = await response.json();

            console.log('commentPhotoDownload, ответ от сервера', data);
            
            if (!data.data || data.data.length === 0) {
                showAlert('Нет файлов для архивации и скачивания архива', 'warning');
                downloadFilesBtn.innerHTML = originalHtml;
                downloadFilesBtn.disabled = false;
                return;
            }

            // Показываем индикатор архивации
            downloadFilesBtn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Архивация...';
            downloadFilesBtn.disabled = true;

            // Создаем zip архив
            const zip = new JSZip();
            
            // Загружаем содержимое каждого файла
            const filePromises = data.data.map(async (file) => {
                try {
                    // Загружаем файл как бинарные данные
                    const fileResponse = await fetch(file.url);
                    if (!fileResponse.ok) {
                        console.error(`Не удалось загрузить файл: ${file.url}`);
                        return null;
                    }
                    const fileBlob = await fileResponse.blob();
                    zip.file(file.original_name, fileBlob);
                    return true;
                } catch (error) {
                    console.error(`Ошибка при загрузке файла ${file.original_name}:`, error);
                    return false;
                }
            });

            // Ждем загрузки всех файлов
            const results = await Promise.all(filePromises);
            const successfulFiles = results.filter(Boolean).length;
            
            if (successfulFiles === 0) {
                throw new Error('Не удалось загрузить ни одного файла');
            }

            // Генерируем имя файла
            const fileName = `comment-${commentId}-files.zip`;

            // Создаем Blob с архивом
            zip.generateAsync({ type: 'blob' })
                .then(function(content) {
                    // Создаем ссылку для скачивания
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(content);
                    link.download = fileName;
                    link.click();

                    // Очищаем ссылку
                    URL.revokeObjectURL(link.href);

                    // Возвращаем кнопку в исходное состояние
                    downloadFilesBtn.innerHTML = originalHtml;
                    downloadFilesBtn.disabled = false;
                })
                .catch(function(error) {
                    console.error('Ошибка при создании архива:', error);
                    showAlert('Не удалось создать архив', 'danger');
                    downloadFilesBtn.innerHTML = originalHtml;
                    downloadFilesBtn.disabled = false;
                });
        } catch (error) {
            console.error('Ошибка при загрузке файлов:', error);
            showAlert('Не удалось загрузить файлы', 'danger');
            downloadFilesBtn.innerHTML = originalHtml;
            downloadFilesBtn.disabled = false;
        }
    });

    // Обработчик для кнопки скачивания фотографий для zip архивирования (новая версия)
    document.addEventListener('click', async function(e) {
        const downloadBtn = e.target.closest('.download-comment-btn');
        if (!downloadBtn) return;
        
        e.preventDefault();
        const commentId = downloadBtn.dataset.commentId;
        const originalHtml = downloadBtn.innerHTML;
        
        try {
            // Показываем индикатор загрузки
            downloadBtn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Загрузка...';
            downloadBtn.disabled = true;
            
            // Загружаем фотографии комментария
            const response = await fetch(`/api/comments/${commentId}/photos`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ comment_id: commentId })
            });
            
            if (!response.ok) {
                throw new Error('Ошибка при загрузке фотографий');
            }
            
            const data = await response.json();

            console.log('commentPhotoDownload, ответ от сервера', data);
            
            if (!data.data || data.data.length === 0) {
                showAlert('Нет фото для архивации и скачивания архива', 'warning');
                downloadBtn.innerHTML = originalHtml;
                downloadBtn.disabled = false;
                return;
            }

            // Показываем индикатор архивации
            downloadBtn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Архивация...';
            
            // Создаем архив с фотографиями
            createAndDownloadZip(data.data, `comment_${commentId}_photos.zip`);
            
            // Функция для создания и скачивания ZIP-архива
            async function createAndDownloadZip(photos, zipName) {
                try {
                    // Проверяем, что JSZip доступен глобально
                    if (typeof window.JSZip === 'undefined') {
                        throw new Error('Библиотека JSZip не загружена');
                    }
                    
                    const zip = new window.JSZip();
                    const imgFolder = zip.folder('photos');
                    
                    // Массив для хранения промисов загрузки файлов
                    const downloadPromises = [];
                    
                    // Добавляем каждый файл в архив
                    photos.forEach((photo, index) => {
                        const promise = fetch(photo.url)
                            .then(response => {
                                if (!response.ok) throw new Error(`Ошибка загрузки фото: ${response.statusText}`);
                                return response.blob();
                            })
                            .then(blob => {
                                // Создаем имя файла на основе оригинального имени или индекса
                                const fileName = photo.original_name || `photo_${index + 1}.jpg`;
                                imgFolder.file(fileName, blob);
                            });
                        
                        downloadPromises.push(promise);
                    });
                    
                    // Ждем загрузки всех файлов
                    await Promise.all(downloadPromises);
                    
                    // Генерируем архив
                    const content = await zip.generateAsync({ type: 'blob' });
                    
                    // Создаем ссылку для скачивания
                    const url = URL.createObjectURL(content);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = zipName;
                    document.body.appendChild(a);
                    a.click();
                    
                    // Очищаем ссылку после скачивания
                    setTimeout(() => {
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        
                        // Восстанавливаем кнопку
                        downloadBtn.innerHTML = originalHtml;
                        downloadBtn.disabled = false;
                    }, 100);
                    
                } catch (error) {
                    console.error('Ошибка при создании архива:', error);
                    showAlert('Произошла ошибка при создании архива: ' + error.message, 'danger');
                    
                    // Восстанавливаем кнопку в случае ошибки
                    downloadBtn.innerHTML = originalHtml;
                    downloadBtn.disabled = false;
                }
            }
            
        } catch (error) {
            console.error('Ошибка при скачивании фотографий:', error);
            showAlert('Произошла ошибка при загрузке фотографий', 'danger');
            downloadBtn.innerHTML = originalHtml;
            downloadBtn.disabled = false;
        }
    });
}

// Функция для инициализации обработчика кнопки редактирования адреса
export function initAddressEditButton() {
    // console.log('Инициализация обработчика кнопки редактирования адреса');
    
    // Используем делегирование событий, так как кнопка создается динамически
    document.addEventListener('click', function(event) {
        // Проверяем, что клик был по кнопке редактирования адреса или по кнопке удаления адреса или по их дочерним элементам
        const editButton = event.target.closest('#editAddressBtn');
        
        if (editButton) {
            console.log('Клик по кнопке редактирования адреса 1:', editButton);

            event.preventDefault();
            event.stopPropagation();
            
            // Находим родительский блок с информацией об адресе
            const addressInfoBlock = editButton.closest('.card-body');
            if (!addressInfoBlock) {
                console.error('Не удалось найти блок с информацией об адресе');
                return;
            }
            
            // Получаем ID адреса из атрибута data-update-address-id
            const addressId = addressInfoBlock.getAttribute('data-update-address-id');
            
            // console.log('Нажата кнопка редактирования, ID адреса:', addressId);

            if (!addressId) {
                console.error('ID адреса не найден');
                return;
            }
            
            // Открыть модальное окно
            const modal = new bootstrap.Modal(document.getElementById('editAddressModal'));
            
            // Загружаем данные адреса с сервера
            fetch(`/api/addresses/${addressId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const address = data.data;
                        // Заполняем форму данными
                        document.getElementById('addressId').value = address.id;
                        document.getElementById('city_id').value = address.city_id || '';
                        
                        // Устанавливаем выбранный город в выпадающем списке
                        const citySelect = document.getElementById('city');
                        if (citySelect) {
                            // Находим опцию с нужным city_id
                            for (let i = 0; i < citySelect.options.length; i++) {
                                if (citySelect.options[i].value == address.city_id) {
                                    citySelect.selectedIndex = i;
                                    break;
                                }
                            }
                        }

                        console.log('Окно редактирования адреса №1:', address);
                        
                        document.getElementById('district').value = address.district || '';
                        document.getElementById('street').value = address.street || '';
                        document.getElementById('houses').value = address.houses || '';
                        document.getElementById('responsible_person').value = address.responsible_person || '';
                        document.getElementById('comments').value = address.comments || '';
                        document.getElementById('latitudeEdit').value = address.latitude ? parseFloat(address.latitude).toString() : '';
                        document.getElementById('longitudeEdit').value = address.longitude ? parseFloat(address.longitude).toString() : '';
                        
                        // Показываем модальное окно
                        modal.show();
                    } else {
                        alert('Ошибка при загрузке данных адреса');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при загрузке данных');
                });
        }
    });
}

// Функция для сохранения изменений адреса
function saveAddressChanges() {
    const form = document.getElementById('editAddressForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const addressId = formData.get('id');
    
    // Показываем индикатор загрузки
    const saveButton = document.getElementById('saveEditAddressBtn');
    const originalButtonText = saveButton.innerHTML;
    saveButton.disabled = true;
    saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';
    
    // Отправляем запрос на сервер
    fetch('/api/addresses/' + addressId, {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(Object.fromEntries(formData))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Закрываем модальное окно
            const modal = bootstrap.Modal.getInstance(document.getElementById('editAddressModal'));
            if (modal) modal.hide();
            
            // Показываем уведомление об успехе
            showAlert('Адрес успешно обновлен', 'success');
            
            // Обновляем список адресов
            loadAddresses();
        } else {
            throw new Error(data.message || 'Произошла ошибка при обновлении адреса');
        }
    })
    .catch(error => {
        console.error('Ошибка при обновлении адреса:', error);
        showAlert(error.message || 'Произошла ошибка при обновлении адреса', 'danger');
    })
    .finally(() => {
        // Восстанавливаем кнопку
        saveButton.disabled = false;
        saveButton.innerHTML = originalButtonText;
    });
}

// Инициализация обработчика кнопки сохранения
function initSaveAddressChanges() {
    const saveButton = document.getElementById('saveEditAddressBtn');
    if (saveButton) {
        // Удаляем предыдущие обработчики, чтобы избежать дублирования
        const newSaveButton = saveButton.cloneNode(true);
        saveButton.parentNode.replaceChild(newSaveButton, saveButton);
        
        // Добавляем новый обработчик
        newSaveButton.addEventListener('click', function(e) {
            e.preventDefault();
            saveAddressChanges();
        });
    }
}

// Делаем функции доступными глобально
window.submitRequestForm = submitRequestForm;
window.displayEmployeeInfo = displayEmployeeInfo;
window.updateRowNumbers = updateRowNumbers;
window.addRequestToTable = addRequestToTable;
window.handleCommentEdit = handleCommentEdit;
window.initCommentValidation = initCommentValidation;

/**
 * Функция для обработки редактирования комментария
 * @param {HTMLElement} commentElement - Элемент комментария
 * @param {number} commentId - ID комментария
 * @param {number} commentNumber - Порядковый номер комментария
 * @param {HTMLElement} editButton - Кнопка редактирования
 * @returns {void}
 */
async function handleCommentEdit(commentElement, contentHtml, commentId, commentNumber, editButton, requestId) {

    console.log('commentElement', commentElement);
    console.log('contentHtml', contentHtml);
    console.log('commentId', commentId);
    console.log('commentNumber', commentNumber);
    console.log('editButton', editButton);
    console.log('requestId', requestId);

    // Получаем текущий HTML комментария
    let commentHtml = contentHtml.trim();
    
    // Если содержимое пустое, пробуем получить текст из textContent
    // if (!commentHtml) {
    //     commentHtml = commentElement.textContent.trim();
    // }
    
    console.log('Original comment HTML:', commentHtml);
    
    // Показываем элемент комментария, если он скрыт
    commentElement.style.display = 'block';

    // Создаем общий контейнер для редактирования
    const editContainer = document.createElement('div');
    editContainer.className = 'edit-comment-container';
    editContainer.setAttribute('data-comment-number', commentNumber);
    editContainer.setAttribute('data-comment-id', commentId);
    editContainer.style.width = '100%';
    editContainer.style.maxWidth = '730px';

    // Создаем контейнер для редактора
    const editorContainer = document.createElement('div');
    editorContainer.className = 'mb-3';
    
    // Создаем тулбар редактора
    const toolbar = document.createElement('div');
    toolbar.className = 'wysiwyg-toolbar btn-group mb-2';
    toolbar.setAttribute('role', 'group');
    toolbar.setAttribute('aria-label', 'Editor toolbar');
    
    // Кнопки тулбара
    const buttons = [
        { cmd: 'bold', title: 'Жирный', content: '<strong>B</strong>' },
        { cmd: 'italic', title: 'Курсив', content: '<em>I</em>' },
        { cmd: 'createLink', title: 'Вставить ссылку', content: 'link' },
        { cmd: 'unlink', title: 'Убрать ссылку', content: 'unlink' }
    ];
    
    // Добавляем кнопки в тулбар
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';
        button.setAttribute('data-cmd', btn.cmd);
        button.setAttribute('title', btn.title);
        button.innerHTML = btn.content;
        toolbar.appendChild(button);
    });
    
    // Создаем редактор
    const editor = document.createElement('div');
    editor.className = 'wysiwyg-editor border rounded p-2';
    editor.setAttribute('contenteditable', 'true');
    editor.style.minHeight = '100px';

    // console.log('Comment HTML before setting to editor:', commentHtml);

    // return;

    // Устанавливаем HTML в редактор, только если есть содержимое
    if (commentHtml) {
        editor.innerHTML = commentHtml;
        // console.log('Comment HTML after setting to editor:', editor.innerHTML);
    } else {
        // console.warn('Comment content is empty');
    }

    // Создаем скрытое поле для формы
    const hiddenInput = document.createElement('textarea');
    hiddenInput.name = 'comment';
    hiddenInput.style.display = 'none';
    
    // Создаем контейнер для кнопок
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'd-flex justify-content-start gap-2 mt-2';
    
    // Создаем кнопки Сохранить/Отмена
    const saveButton = document.createElement('button');
    saveButton.id = 'saveCommentBtn';
    saveButton.type = 'button';
    saveButton.className = 'btn btn-sm btn-success';
    saveButton.setAttribute('data-comment-id', commentId);
    saveButton.setAttribute('data-comment-number', commentNumber);
    saveButton.setAttribute('data-request-id', requestId);
    saveButton.textContent = 'Сохранить';
    // const requestId = this.getAttribute('data-request-id');
    console.log('requestId', requestId);
    
    const cancelButton = document.createElement('button');
    cancelButton.type = 'button';
    cancelButton.className = 'btn btn-sm btn-secondary';
    cancelButton.textContent = 'Отмена';

    // Собираем все вместе
    buttonContainer.appendChild(saveButton);
    buttonContainer.appendChild(cancelButton);  
    
    // Добавляем элементы в контейнеры
    editorContainer.appendChild(toolbar);
    editorContainer.appendChild(editor);
    editorContainer.appendChild(hiddenInput);
    
    editContainer.appendChild(editorContainer);
    editContainer.appendChild(buttonContainer);

    // Сохраняем ссылку на родительский элемент и сам элемент комментария
    const parentElement = commentElement.parentNode;
    
    // Сохраняем ссылку на элемент комментария, чтобы вернуть его позже
    window.currentEditedComment = {
        element: commentElement,
        parent: parentElement
    };
    
    // Заменяем элемент комментария на контейнер редактирования
    parentElement.replaceChild(editContainer, commentElement);
    
    // Показываем родительский элемент, если он был скрыт
    parentElement.style.display = 'block';

    // Скрываем кнопку редактирования
    editButton.style.display = 'none';
    
    // Инициализация обработчиков для тулбара редактора
    initEditorToolbar(toolbar, editor);

    // Обработчик кнопки Сохранить
    saveButton.addEventListener('click', async function() {
        // Получаем HTML содержимое редактора
        const newText = editor.innerHTML.trim();

        // console.log('newText', newText);

        // console.log('commentId', commentId);

        if (newText) {
            try {
                // Показываем индикатор загрузки
                saveButton.disabled = true;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

                // Отправляем запрос на сервер
                const url = `/api/comments/${commentId}`;

                // console.log('url', url);

                const response = await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ content: newText }),
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Ошибка при сохранении комментария');
                }

                const result = await response.json();

                // console.log(result);

                // Показываем уведомление об успехе
                showAlert('Комментарий успешно обновлен', 'success');

                // console.log('Before update - commentElement:', commentElement);
                // console.log('newText to set:', newText);
                
                // Обновляем содержимое существующего элемента комментария
                const { element: savedComment, parent } = window.currentEditedComment || {};
                
                if (savedComment && parent) {
                    savedComment.innerHTML = newText;
                    savedComment.style.display = 'block';
                    savedComment.style.wordBreak = 'normal';
                    savedComment.style.overflowWrap = 'break-word';
                    savedComment.style.whiteSpace = 'pre-wrap';
                    
                    // Заменяем редактор обратно на обновленный комментарий
                    if (editContainer && editContainer.parentNode) {
                        parent.replaceChild(savedComment, editContainer);
                    }
                    
                    // Очищаем сохраненную ссылку
                    delete window.currentEditedComment;

                    // Находим и обновляем соответствующий элемент комментария в таблице заявок
                    const commentInTable = document.querySelector(`[data-comment-request-id="${requestId}"]`);
                    if (commentInTable) {
                        commentInTable.innerHTML = newText;
                        commentInTable.style.wordBreak = 'normal';
                        commentInTable.style.overflowWrap = 'break-word';
                        commentInTable.style.whiteSpace = 'pre-wrap';
                    }
                } else {
                    console.error('Не удалось восстановить комментарий после редактирования');
                }
                
                // console.log('Comment element updated:', commentElement);
                
                // console.log('Comment updated successfully');
                
                // Показываем кнопку редактирования
                editButton.style.display = 'inline-block';

            } catch (error) {
                console.error('Ошибка при сохранении комментария:', error);

                // Показываем уведомление об ошибке
                showAlert(`Ошибка: ${error.message}`, 'danger');

                // Возвращаем кнопку в исходное состояние
                saveButton.disabled = false;
                saveButton.textContent = 'Сохранить';
            }
        }
    });

    // Обработчик кнопки Отмена
    cancelButton.addEventListener('click', function() {
        // Возвращаем обычный вид комментария
        commentElement.style.display = '';
        editButton.style.display = 'inline-block';

        // Удаляем контейнер редактирования
        editContainer.remove();
    });

    // Фокус на редактор
    editor.focus();
}

/**
 * Инициализирует тулбар редактора редактирования комментария
 * @param {HTMLElement} toolbar - Элемент тулбара
 * @param {HTMLElement} editor - Элемент редактора
 */
function initEditorToolbar(toolbar, editor) {
    // Создаем кнопку переключения HTML/Визуальный режим
    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'btn btn-sm btn-outline-secondary ms-2';
    toggleButton.setAttribute('data-cmd', 'toggleHtml');
    toggleButton.setAttribute('title', 'Режим HTML/Визуальный');
    toggleButton.textContent = 'HTML';
    
    // Добавляем кнопку в тулбар
    toolbar.appendChild(toggleButton);
    
    // Создаем textarea для HTML-редактирования
    const htmlTextarea = document.createElement('textarea');
    htmlTextarea.className = 'form-control d-none mt-2';
    htmlTextarea.style.minHeight = '100px';
    htmlTextarea.style.fontFamily = 'monospace';
    toolbar.parentNode.insertBefore(htmlTextarea, toolbar.nextSibling);
    
    // Флаг для отслеживания режима
    let isHtmlMode = false;
    
    // Функция переключения между режимами
    function toggleHtmlMode() {
        isHtmlMode = !isHtmlMode;
        
        if (isHtmlMode) {
            // Переключаемся в HTML-режим
            const html = editor.innerHTML;
            htmlTextarea.value = html;
            editor.style.display = 'none';
            htmlTextarea.classList.remove('d-none');
            toggleButton.classList.add('active');
            htmlTextarea.focus();
        } else {
            // Возвращаемся в визуальный режим
            const html = htmlTextarea.value;
            editor.innerHTML = html;
            htmlTextarea.classList.add('d-none');
            editor.style.display = 'block';
            toggleButton.classList.remove('active');
            editor.focus();
        }
    }
    
    // Обработчики для кнопок тулбара
    toolbar.addEventListener('mousedown', function(e) {
        e.preventDefault(); // Предотвращаем потерю фокуса
    });
    
    toolbar.addEventListener('click', function(e) {
        const button = e.target.closest('button[data-cmd]');
        if (!button) return;
        
        e.preventDefault();
        const cmd = button.getAttribute('data-cmd');
        
        // Обработка переключения HTML-режима
        if (cmd === 'toggleHtml') {
            toggleHtmlMode();
            return;
        }
        
        // Выходим, если в режиме HTML
        if (isHtmlMode) return;
        
        // Сохраняем выделение
        const selection = window.getSelection();
        const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
        
        // Выполняем команду
        if (cmd === 'bold' || cmd === 'italic') {
            document.execCommand(cmd, false, null);
        } 
        else if (cmd === 'createLink') {
            const url = prompt('Введите URL (например, https://example.com):', 'https://');
            if (url) {
                // Если текст не выделен, вставляем URL как текст
                if (selection.toString().trim() === '') {
                    document.execCommand('insertHTML', false, 
                        `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
                } else {
                    document.execCommand('createLink', false, url);
                }
            }
        } 
        else if (cmd === 'unlink') {
            // Находим родительскую ссылку, если курсор внутри неё
            const link = editor.querySelector('a[href]');
            if (link) {
                // Заменяем ссылку на её текстовое содержимое
                const text = document.createTextNode(link.textContent);
                link.parentNode.replaceChild(text, link);
            } else {
                document.execCommand('unlink', false, null);
            }
        }
        
        // Восстанавливаем фокус на редактор
        editor.focus();
    });
}

// Обработчик для кнопки переключения HTML/Визуальный
// Перенесен в editor.js

// Обработчик для кнопки справки
    const helpButton = document.getElementById('show-help');
    if (helpButton) {
        helpButton.addEventListener('click', function() {
            // Создаем модальное окно справки, если его еще нет
            let helpModal = document.getElementById('editor-help-modal');
            
            if (!helpModal) {
                helpModal = document.createElement('div');
                helpModal.id = 'editor-help-modal';
                helpModal.className = 'modal fade';
                helpModal.tabIndex = '-1';
                helpModal.setAttribute('aria-hidden', 'true');
                
                helpModal.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Справка по работе с редактором</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                            </div>
                            <div class="modal-body">
                                <h6>Основные возможности редактора:</h6>
                                <ul class="mb-4">
                                    <li><strong>B</strong> - Сделать выделенный текст <strong>жирным</strong></li>
                                    <li><em>I</em> - Сделать выделенный текст <em>курсивом</em></li>
                                    <li><strong>link</strong> - Вставить ссылку (предварительно выделите текст)</li>
                                    <li><strong>unlink</strong> - Удалить ссылку (установите курсор на ссылку)</li>
                                    <li><strong>HTML/Код</strong> - Переключение между визуальным редактором и HTML-кодом</li>
                                </ul>
                                
                                <h6>Советы по форматированию:</h6>
                                <ul>
                                    <li>Выделите текст, чтобы применить к нему форматирование</li>
                                    <li>Для вставки ссылки выделите текст и нажмите кнопку "link"</li>
                                    <li>Используйте режим "Код" для ручного редактирования HTML</li>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(helpModal);
            }
            
            // Инициализируем и показываем модальное окно
            const modal = new bootstrap.Modal(helpModal);
            modal.show();
        });
    }

// ************* Common functions ************* //

/**
 * Marks a form field as invalid and shows an error message
 * @param {string} fieldName - The name attribute of the field
 * @param {string} message - The error message to display
 */
function markFieldAsInvalid(fieldName, message) {
    const field = document.querySelector(`[name="${fieldName}"]`);
    if (!field) return;
    
    // Add invalid class to the field
    field.classList.add('is-invalid');
    
    // Create error message element if it doesn't exist
    let errorElement = field.nextElementSibling;
    if (!errorElement || !errorElement.classList.contains('invalid-feedback')) {
        errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        field.parentNode.insertBefore(errorElement, field.nextSibling);
    }
    
    // Set error message
    errorElement.textContent = message;
    
    // If this is a select2 element, we need to add the class to the select2 container
    if (field.classList.contains('select2-hidden-accessible')) {
        const select2Container = field.nextElementSibling;
        if (select2Container && select2Container.classList.contains('select2-container')) {
            select2Container.classList.add('is-invalid');
        }
    }
}

// Функция для валидации поля
function validateField(field, isInitialLoad = false) {
    if (!field) return true;
    
    const value = field.value.trim();
    const fieldName = field.name;
    
    // Сбрасываем предыдущие состояния
    field.classList.remove('is-valid', 'is-invalid');
    const existingFeedback = field.nextElementSibling;
    if (existingFeedback && (existingFeedback.classList.contains('valid-feedback') || 
                           existingFeedback.classList.contains('invalid-feedback'))) {
        existingFeedback.remove();
    }
    
    // Пропускаем валидацию при первом открытии формы
    if (isInitialLoad) {
        return true;
    }
    
    // Проверяем обязательные поля
    if (field.required && !value) {
        return false;
    }
    
    // Если поле прошло валидацию и было изменено
    if (value) {
        field.classList.add('is-valid');
        const validFeedback = document.createElement('div');
        validFeedback.className = 'valid-feedback';
        validFeedback.innerHTML = '✓';
        field.parentNode.insertBefore(validFeedback, field.nextSibling);
    }
    
    return true;
}

// Функция для очистки полей формы
function clearPlanningRequestForm() {
    const form = document.getElementById('planningRequestForm');
    if (!form) return;
    
    // Очищаем все поля ввода
    form.querySelectorAll('input[type="text"], input[type="tel"], textarea').forEach(input => {
        input.value = '';
        input.classList.remove('is-valid', 'is-invalid');
    });
    
    // Сбрасываем select2, если используется
    if (typeof $.fn.select2 !== 'undefined' && $('.select2').length > 0) {
        $('.select2').val(null).trigger('change');
    }
    
    // Удаляем сообщения об ошибках и успешной валидации
    form.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(el => el.remove());
    
    // Сбрасываем состояние валидации
    form.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
        el.classList.remove('is-invalid', 'is-valid');
    });
}

// Функция для добавления стилей валидации
function addValidationStyles() {
    if (document.getElementById('form-validation-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'form-validation-styles';
    style.textContent = `
        .is-valid { 
            border: 1px solid #198754 !important; 
            box-shadow: none !important; 
            outline: none !important; 
            padding-right: 2.25rem; 
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e"); 
            background-repeat: no-repeat; 
            background-position: right 0.6rem center; 
            background-size: 1.2rem 1.2rem; 
        }
        .valid-feedback { display: block; color: #198754; font-size: 0.875rem; margin-top: 0.25rem; }
        .is-invalid { border-color: var(--bs-form-invalid-color) !important; }
        .invalid-feedback { 
            display: block; 
            color: var(--bs-form-invalid-color); 
            font-size: 0.875rem; 
            margin-top: 0.25rem; 
        }
    `;
    document.head.appendChild(style);
}

function closeModalProperly() {
    const modalElement = document.getElementById('editEmployeeModal');
    const bsModal = bootstrap.Modal.getInstance(modalElement);
    if (bsModal) {
        bsModal.hide();
        // Дополнительно удаляем класс modal-backdrop и стили body
        setTimeout(() => {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) backdrop.remove();
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }, 100);
    }
}

// ************* Назначение обработчиков событий ************ //


// request-in-work
export function initRequestInWorkHandlers() {
    const buttons = document.querySelectorAll('button.request-in-work');
    buttons.forEach(button => {
        button.addEventListener('click', (e) => {
            // Останавливаем всплытие события, чтобы избежать множественных срабатываний
            e.stopPropagation();
            e.preventDefault();
            const requestId = button.getAttribute('data-request-id');
            console.log('Нажата кнопка "В работу" для заявки:', requestId);
            
            // Устанавливаем ID заявки в скрытое поле
            const requestIdInput = document.getElementById('requestId');
            if (requestIdInput) {
                requestIdInput.value = requestId;
            }

            // Инициализируем и показываем модальное окно
            const modalElement = document.getElementById('changePlanningRequestStatusModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                
                // Устанавливаем ID заявки в скрытое поле
                const requestIdInput = document.getElementById('planningRequestId');
                if (requestIdInput) {
                    requestIdInput.value = requestId;
                }

                // Устанавливаем минимальную дату (сегодня) и дату по умолчанию (завтра) при открытии модального окна
                modalElement.addEventListener('shown.bs.modal', function () {
                    const dateInput = document.getElementById('planningExecutionDate');
                    if (dateInput) {
                        const today = new Date();
                        const tomorrow = new Date(today);
                        tomorrow.setDate(tomorrow.getDate() + 1);
                        
                        // Устанавливаем минимальную дату (сегодня)
                        dateInput.min = today.toISOString().split('T')[0];
                        
                        // Устанавливаем дату по умолчанию (завтра)
                        dateInput.value = tomorrow.toISOString().split('T')[0];
                    }
                });
                
                modal.show();
            } else {
                console.error('Элемент модального окна не найден');
            }
        });
    });

    savePlanningRequestStatusBtn.addEventListener('click', async function () {
        const form = document.getElementById('changeRequestStatusForm');
        const modal = bootstrap.Modal.getInstance(document.getElementById('changePlanningRequestStatusModal'));
        
        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            console.log('data', data);

            const response = await fetch('/change-planning-request-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            console.log('Ответ от сервера:', result);
            
            if (result.success) {
                // Показываем уведомление об успехе
                showAlert('success', 'Статус заявки успешно обновлен');
                
                // Закрываем модальное окно
                if (modal) {
                    modal.hide();
                }
                
                // Обновляем таблицу с заявками
                await loadPlanningRequests();
            } else {
                showAlert('danger', result.message || 'Произошла ошибка при обновлении статуса');
            }
            
        } catch (error) {
            console.error('Ошибка при отправке формы:', error);
            showAlert('danger', 'Произошла ошибка при отправке формы');
        }
    });
}

export function initPlanningRequestFormHandlers() {
    const submitButton = document.getElementById('submitPlanningRequest');
    
    if (!submitButton) {
        console.error('Кнопка submitPlanningRequest не найдена');
        return;
    }

    // Загружаем запланированные заявки
    loadPlanningRequests();

    // Загружаем адреса
    loadAddressesForPlanning();

    // Инициализируем кастомный селект, если функция доступна
    if (typeof window.initCustomSelect === 'function') {
        // console.log('Инициализация кастомного селекта для выбора адреса в форме');
        window.initCustomSelect("addressesPlanningRequest", "Выберите адрес из списка");
    } else {
        console.log('Функция initCustomSelect не найдена для addressesPlanningRequest');
    }
    
    // Добавляем стили валидации
    addValidationStyles();
    
    // Добавляем обработчик закрытия модального окна
    const modalElement = document.getElementById('newPlanningRequestModal');
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', clearPlanningRequestForm);
    }
    
    // Добавляем обработчики событий для полей формы
    const form = document.getElementById('planningRequestForm');
    const formFields = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    // Обработчик для селекта адреса
    const addressSelect = document.getElementById('addressesPlanningRequest');
    if (addressSelect) {
        addressSelect.addEventListener('change', function() {
            if (this.value) {
                // Удаляем класс is-invalid и сообщение об ошибке
                this.classList.remove('is-invalid');
                const feedback = this.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.remove();
                }
                // Добавляем класс is-valid при выборе значения
                this.classList.add('is-valid');
            }
        });
    }
    
    formFields.forEach(field => {
        // Валидация при уходе с поля
        field.addEventListener('blur', () => {
            if (field.value.trim() !== '') {
                validateField(field);
            }
        });
        
        // Валидация при вводе (с задержкой)
        let timeout;
        field.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (field.value.trim() !== '') {
                    validateField(field);
                }
            }, 500);
        });
    });
    
    // Обработчик отправки формы
    submitButton.addEventListener('click', async function(event) {
        event.preventDefault();
        event.stopPropagation();
        
        // Получаем данные формы
        const form = document.getElementById('planningRequestForm');
        const formData = new FormData(form);
        const addressId = formData.get('addresses_planning_request_id');
        const comment = formData.get('planning_request_comment');
        const clientName = formData.get('client_name_planning_request');
        const clientPhone = formData.get('client_phone_planning_request');
        const clientOrganization = formData.get('client_organization_planning_request');
        const _token = formData.get('_token');
        
        // Детальное логирование данных формы
        console.log('=== Form Data ===');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ':', pair[1]);
        }
        console.log('=================');
        
        // Сбрасываем предыдущие ошибки и валидацию
        document.querySelectorAll('.is-invalid, .is-valid').forEach(el => {
            el.classList.remove('is-invalid', 'is-valid');
        });
        document.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(el => el.remove());
        
        let hasErrors = false;
        
        if (!addressId) {
            markFieldAsInvalid('addresses_planning_request_id', 'Пожалуйста, выберите адрес');
            hasErrors = true;
        }
        
        if (!comment) {
            markFieldAsInvalid('planning_request_comment', 'Пожалуйста, введите комментарий');
            hasErrors = true;
        }
        
        if (hasErrors) {
            // Прокручиваем к первой ошибке
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        try {
            const formDataObj = {
                address_id: addressId,
                planning_request_comment: comment,
                client_name_planning_request: clientName,
                client_phone_planning_request: clientPhone,
                client_organization_planning_request: clientOrganization,
                _token: _token
            };

            console.log('Отправляемые данные:', formDataObj);

            const response = await fetch('/planning-requests', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formDataObj),
                credentials: 'same-origin'
            });

            const result = await response.json();

            console.log('Ответ сервера result:', result);

            if (result.success) {
                showAlert('Заявка успешно создана', 'success');
                console.log('Заявка успешно создана, ID:', result.id);
                
                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('newPlanningRequestModal'));
                if (modal) {
                    modal.hide();
                }
                
                // Очищаем форму
                form.reset();

                // Загружаем запланированные заявки
                loadPlanningRequests();
                
            } else {
                throw new Error(result.message || 'Ошибка при создании заявки');
            }
        } catch (error) {
            console.error('Ошибка при создании заявки:', error);
            showAlert(error.message || 'Произошла ошибка при создании заявки', 'danger');
        }
    });
}

export function initDeleteAddressHandlers() {
    // Используем делегирование событий для динамически созданных кнопок
    document.addEventListener('click', async function(event) {
        // Проверяем, был ли клик по кнопке удаления или её дочерним элементам
        const deleteButton = event.target.closest('.delete-address-btn');

        const deleteButtonBlock = event.target.closest('#deleteAddressBtn');

        

        // id="addressInfoBlock"
        
        if (deleteButton || deleteButtonBlock) {
            event.preventDefault();
            event.stopPropagation();
            let addressId;

            if (deleteButtonBlock) {
                const addressInfoBlock = document.getElementById('addressInfoBlock');
                addressId = addressInfoBlock.getAttribute('data-delete-address-id');
    
                console.log('Клик по кнопке удаления адреса deleteButtonBlock, addressId:', addressId);
                
            }
            
            addressId = deleteButton ? deleteButton.getAttribute('data-address-id') : addressId;

            console.log('addressId:', addressId);
            
            if (!confirm('Вы уверены, что хотите удалить этот адрес?')) {
                return;
            }

            if (!addressId) {
                showAlert('Ошибка: ID адреса не найден', 'danger');
                return;
            }

            try {   
                const response = await fetch(`/api/addresses/${addressId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Адрес успешно удален', 'success');
                    console.log('Адрес успешно удален, ID:', addressId);

                    const addressInfo = document.getElementById('addressInfo');
                    addressInfo.innerHTML = '';
                    
                    // Обновляем таблицу адресов
                    if (typeof loadAddressesPaginated === 'function') {
                        await loadAddressesPaginated();
                    }
                    
                    // Обновляем выпадающий список адресов
                    if (typeof window.loadAddresses === 'function') {
                        // Вызываем загрузку адресов
                        window.loadAddresses();
                        
                        // Инициализируем кастомный селект с задержкой
                        setTimeout(() => {
                            const selectElement = document.getElementById('addresses_id');
                            if (selectElement) {
                                // Удаляем старый кастомный селект, если он есть
                                const oldWrapper = document.getElementById('custom-select-wrapper-addresses_id');
                                if (oldWrapper) {
                                    oldWrapper.remove();
                                }
                                
                                // Показываем оригинальный селект
                                selectElement.style.display = 'block';
                                
                                // Инициализируем кастомный селект заново
                                if (typeof window.initCustomSelect === 'function') {
                                    window.initCustomSelect('addresses_id', 'Выберите адрес из списка');
                                }
                            }
                        }, 500); // Даем время на загрузку адресов
                    }
                } else {
                    throw new Error(result.message || 'Неизвестная ошибка при удалении адреса');
                }
            } catch (error) {
                console.error('Ошибка при удалении адреса:', error);
                showAlert(`${error.message}.`, 'danger');
            }
        }
    });
}

export function initAddressEditHandlers() {
    // Используем делегирование событий для работы с динамически добавляемыми элементами
    document.addEventListener('click', async function(event) {
        // Проверяем, был ли клик по кнопке редактирования или её дочерним элементам
        const editButton = event.target.closest('.edit-address-btn');
        
        if (editButton) {
            event.preventDefault();
            const addressId = editButton.getAttribute('data-address-id');
            
            console.log('Нажата кнопка редактирования, ID адреса:', addressId);
            
            // Открыть модальное окно
            const modal = new bootstrap.Modal(document.getElementById('editAddressModal'));
            
            // Загружаем данные адреса с сервера
            fetch(`/api/addresses/${addressId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const address = data.data;
                        // Заполняем форму данными
                        document.getElementById('addressId').value = address.id;
                        document.getElementById('city_id').value = address.city_id || '';
                        
                        // Устанавливаем выбранный город в выпадающем списке
                        const citySelect = document.getElementById('city');
                        if (citySelect) {
                            // Находим опцию с нужным city_id
                            for (let i = 0; i < citySelect.options.length; i++) {
                                if (citySelect.options[i].value == address.city_id) {
                                    citySelect.selectedIndex = i;
                                    break;
                                }
                            }
                        }

                        console.log('Загружены данные адреса по кнопке редактирования из списка:', address);
                        
                        document.getElementById('district').value = address.district || '';
                        document.getElementById('street').value = address.street || '';
                        document.getElementById('houses').value = address.houses || '';
                        document.getElementById('responsible_person').value = address.responsible_person || '';
                        document.getElementById('comments').value = address.comments || '';
                        document.getElementById('latitudeEdit').value = address.latitude ? parseFloat(address.latitude).toString() : '';
                        document.getElementById('longitudeEdit').value = address.longitude ? parseFloat(address.longitude).toString() : '';
                        
                        // Показываем модальное окно
                        modal.show();
                    } else {
                        alert('Ошибка при загрузке данных адреса');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при загрузке данных');
                });
        }
    });

    // Обработчик для кнопки сохранения адреса (редактирование)
    document.addEventListener('click', async function(event) {
        if (event.target && event.target.id === 'saveEditAddressBtn') {
            console.log('--- Нажата кнопка сохранения адреса ---');
            
            const form = document.getElementById('editAddressForm');
            if (!form) {
                console.error('Форма редактирования адреса не найдена');
                showAlert('Ошибка: форма не найдена', 'danger');
                return;
            }

            const formData = new FormData(form);
            const addressId = document.getElementById('addressId').value;
            
            // Добавляем city_id в formData, так как select вернет только значение value (id города)
            const citySelect = document.getElementById('city');
            if (citySelect) {
                const cityId = citySelect.value;
                formData.set('city_id', cityId);
                
                // Получаем название города для отладки
                const selectedOption = citySelect.options[citySelect.selectedIndex];
                const cityName = selectedOption ? selectedOption.textContent : '';
                formData.set('city_name', cityName);
            }
            
            // Выводим все данные формы для отладки
            console.log('Данные формы перед отправкой:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            console.log('ID адреса из поля ввода:', addressId);
            
            if (!addressId) {
                console.error('ID адреса не найден');
                showAlert('Ошибка: не удалось определить адрес для обновления', 'danger');
                return;
            }

            // Получаем цвет иконки на основе статуса
            const iconColor = getStatusColor(request.status_id || 0);
            // Определяем текст для метки: имя бригадира или название бригады
            const labelText = (brigadeLeader && (brigadeLeader.fio || brigadeLeader.name)) || 
                            request.brigade_name || 
                            request.id;

            // Показываем индикатор загрузки
            const saveButton = event.target;
            const originalText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

            try {
                // Преобразуем FormData в объект
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });

                console.log('Отправляемые данные:', JSON.stringify(data, null, 2));
                console.log('URL запроса:', `/api/addresses/${addressId}`);

                console.log('Отправка запроса на сервер...');
                const response = await fetch(`/api/addresses/${addressId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(data)
                });

                console.log('Получен ответ от сервера. Адреса:', response.status);
                
                let responseData;
                try {
                    responseData = await response.json();
                    console.log('Данные ответа:', JSON.stringify(responseData, null, 2));
                } catch (e) {
                    console.error('Ошибка при разборе JSON ответа:', e);
                    responseData = {};
                }

                if (!response.ok) {
                    console.error('Ошибка сервера:', response.status, response.statusText);
                    throw new Error(responseData.message || `Ошибка сервера: ${response.status} ${response.statusText}`);
                }

                // Закрываем модальное окно
                const modal = bootstrap.Modal.getInstance(document.getElementById('editAddressModal'));
                if (modal) modal.hide();

                // Обновляем таблицу адресов
                if (typeof loadAddressesPaginated === 'function') {
                    await loadAddressesPaginated();
                }

                console.log('Адрес успешно обновлен.');

                // Здесь нужно обновить блок с id=addressInfo

                document.getElementById('addressInfo').innerHTML = '';

                loadAddresses();

                showAlert('Адрес успешно обновлен.', 'success');

            } catch (error) {
                console.error('Ошибка при сохранении адреса:', error);
                showAlert(error.message || 'Произошла ошибка при сохранении адреса', 'danger');
            } finally {
                // Восстанавливаем кнопку сохранения
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalText;
                }
            }
        }
    });
}

// обработчик для кнопки Добавить фотоотчет
export function initAddPhotoReport() {
    const addPhotoBtns = document.querySelectorAll('.add-photo-btn');
    if (!addPhotoBtns.length) return;

    addPhotoBtns.forEach(button => {
        button.addEventListener('click', function() {
            // Удаляем класс active со всех кнопок
            document.querySelectorAll('.add-photo-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Добавляем класс active к текущей кнопке
            this.classList.add('active');
            
            // Получаем ID заявки: приоритетно из скрытого поля модалки комментариев, затем из атрибута кнопки
            let requestId = null;
            const commentReqInput = document.getElementById('commentRequestId');
            if (commentReqInput && commentReqInput.value) {
                requestId = commentReqInput.value;
            } else {
                requestId = this.getAttribute('data-request-id');
            }

            // Проставляем актуальный ID обратно на кнопку, чтобы relatedTarget содержал корректный id
            if (requestId) {
                this.setAttribute('data-request-id', requestId);
            }
            console.log('Открытие фотоотчета для заявки:', requestId);

            if (!requestId) {
                showAlert('Не удалось определить ID заявки для фотоотчета', 'danger');
                return;
            }
            
            // Открываем модальное окно
            const modalElement = document.getElementById('addPhotoModal');
            if (modalElement) {
                // Подставляем ID в скрытое поле формы до открытия модалки
                const hiddenInput = modalElement.querySelector('#photoRequestId');
                if (hiddenInput) hiddenInput.value = requestId;
                const modalTitle = modalElement.querySelector('.modal-title');
                if (modalTitle) modalTitle.textContent = `Фотоотчет по заявке #${requestId}`;

                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        });
    });
}

export function saveEmployeeChangesSystem() {
    const saveBtn = document.getElementById('saveEmployeeChangesSystem');
    if (!saveBtn) return;

    saveBtn.addEventListener('click', async function() {
        // showAlert('В разработке!', 'info');

        // return;

        // const userId = document.getElementById('userIdInputUpdate').value;
        // const login = document.getElementById('loginInputUpdateSystem').value.trim();
        const password = document.getElementById('passwordInputUpdateSystem').value.trim();
        const employeeId = document.getElementById('employeeIdInputUpdate').value;

        // if (!userId) {
        //     showAlert('Ошибка: ID пользователя не найден', 'danger');
        //     return;
        // }

        if (!employeeId) {
            showAlert('Ошибка: ID сотрудника не найден', 'danger');
            return;
        }

        // if (!login) {
        //     showAlert('Пожалуйста, укажите логин', 'warning');
        //     return;
        // }

        if (!password) {
            showAlert('Пожалуйста, укажите пароль', 'warning');
            return;
        }

        try {
            const response = await fetch(`/api/users/${employeeId}/credentials`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    password: password
                })
            });

            const result = await response.json();

            console.log('result', result);

            if (result.success) {
                showAlert('Учетная запись успешно обновлена', 'success');
                // Очищаем поле пароля после успешного обновления
                document.getElementById('passwordInputUpdateSystem').value = '';
            } else {
                showAlert(result.message || 'Произошла ошибка при обновлении данных', 'danger');
            }
        } catch (error) {
            console.error('Error updating user credentials:', error);
            showAlert('Произошла ошибка при обновлении данных. Пожалуйста, попробуйте снова.', 'danger');
        }
    });
}

// Удаление участника бригады
export function initDeleteMember() {
    // Используем делегирование событий для работы с динамически добавляемыми кнопками
    document.addEventListener('click', async function(event) {
        const deleteBtn = event.target.closest('.delete-member-btn');
        if (!deleteBtn) return;
        
        event.preventDefault();
        
        const brigadeId = deleteBtn.getAttribute('data-brigade-id');
        const employeeId = deleteBtn.getAttribute('data-employee-id');
        
        if (!brigadeId || !employeeId) {
            console.error('Не указаны ID бригады или сотрудника');
            showAlert('Ошибка: не указаны необходимые данные для удаления', 'danger');
            return;
        }

        if (!confirm('Вы уверены, что хотите удалить участника бригады?')) return;
        
        // Формируем ID в формате brigadeId
        const memberId = `${brigadeId}`;
        
        try {
            console.log(`Попытка удаления бригады: brigadeId=${brigadeId}`);
            
            const result = await postData(`/brigade/delete/${memberId}`, {});

            console.log(result);

            // return;
            
            if (result && result.success) {
                showAlert(result.message || 'Бригада успешно удалена', 'success');
                
                // Находим карточку удаленной бригады
                const brigadeCard = document.querySelector(`div[data-card-brigade-id="${brigadeId}"]`);
                if (brigadeCard) {
                    const employeesSelect = document.getElementById('employeesSelect');
                    
                    // 1. Получаем ID всех участников бригады из скрытого поля
                    const brigadeMembersData = [];
                    const brigadeMembersField = document.getElementById('brigade_members_data');
                    
                    if (brigadeMembersField && brigadeMembersField.value) {
                        try {
                            const members = JSON.parse(brigadeMembersField.value);
                            brigadeMembersData.push(...members);
                            console.log('Найдены участники в скрытом поле:', brigadeMembersData);
                        } catch (e) {
                            console.error('Ошибка при разборе данных участников:', e);
                        }
                    }
                    
                    // 2. Если в скрытом поле не нашли, ищем в DOM
                    if (brigadeMembersData.length === 0) {
                        console.log('Поиск участников в DOM карточки:', brigadeCard);
                        
                        // Сначала ищем бригадира в блоке с data-info="leader-info"
                        const leaderInfo = brigadeCard.querySelector('[data-info="leader-info"]');
                        if (leaderInfo) {
                            const leaderName = leaderInfo.textContent.trim();
                            // Пытаемся найти ID бригадира в кнопке удаления
                            const deleteBtn = brigadeCard.querySelector('.delete-member-btn');
                            const leaderId = deleteBtn ? deleteBtn.getAttribute('data-employee-id') : null;
                            
                            if (leaderId) {
                                brigadeMembersData.push({
                                    employee_id: leaderId,
                                    is_leader: true,
                                    name: leaderName
                                });
                                console.log('Найден бригадир:', leaderId, leaderName);
                            } else {
                                console.warn('Не удалось найти ID бригадира');
                            }
                        }
                        
                        // Ищем участников в таблице
                        const memberRows = brigadeCard.querySelectorAll('tr[data-member-id]');
                        console.log('Найдено строк участников в таблице:', memberRows.length);
                        
                        // Добавляем участников из таблицы
                        memberRows.forEach(row => {
                            const memberId = row.getAttribute('data-member-id');
                            if (memberId) {
                                // Проверяем, не дублируется ли участник (если это не бригадир)
                                const isDuplicate = brigadeMembersData.some(m => m.employee_id === memberId);
                                if (!isDuplicate) {
                                    brigadeMembersData.push({
                                        employee_id: memberId,
                                        is_leader: row.classList.contains('leader-row') || false,
                                        name: row.textContent.trim()
                                    });
                                }
                            }
                        });
                        
                        // Если не нашли в таблице, ищем в div-блоках
                        if (brigadeMembersData.length === 0) {
                            const memberDivs = brigadeCard.querySelectorAll('[data-employee-id]');
                            console.log('Найдено div-участников:', memberDivs.length);
                            
                            memberDivs.forEach(div => {
                                const memberId = div.getAttribute('data-employee-id');
                                if (memberId && !div.classList.contains('delete-member-btn')) {
                                    // Проверяем, не дублируется ли участник (если это не бригадир)
                                    const isDuplicate = brigadeMembersData.some(m => m.employee_id === memberId);
                                    if (!isDuplicate) {
                                        brigadeMembersData.push({
                                            employee_id: memberId,
                                            is_leader: div.classList.contains('brigade-leader') || false,
                                            name: div.textContent.trim()
                                        });
                                    }
                                }
                            });
                        }
                    }
                    
                    // console.log('Всего найдено участников бригады:', brigadeMembersData.length, brigadeMembersData);
                    
                    // 3. Восстанавливаем всех участников в выпадающем списке
                    brigadeMembersData.forEach((member, index) => {
                        try {
                            const memberId = member.employee_id || member.id;
                            if (!memberId) {
                                console.warn('У участника не найден ID:', member);
                                return;
                            }
                            
                            console.log(`Участник #${index + 1}:`, {
                                memberId,
                                isLeader: member.is_leader,
                                name: member.name || 'Не указано'
                            });
                            
                            const option = employeesSelect?.querySelector(`option[value="${memberId}"]`);
                            // console.log('Найдена опция в select:', !!option, option);
                            
                            if (option) {
                                option.disabled = false;
                                option.style.display = '';
                                option.selected = false;
                                console.log(`Опция для участника ${memberId} восстановлена`);
                            } else {
                                console.warn(`Не найдена опция для участника с ID: ${memberId}`);
                                console.log('Доступные опции:', Array.from(employeesSelect?.options || []).map(opt => ({
                                    value: opt.value,
                                    text: opt.textContent.trim(),
                                    disabled: opt.disabled,
                                    display: opt.style.display
                                })));
                            }
                        } catch (error) {
                            console.error('Ошибка при обработке участника:', error, member);
                        }
                    });
                    
                    // Скрываем карточку удаленной бригады
                    brigadeCard.style.display = 'none';
                }
            } else {
                // Показываем сообщение об ошибке с сервера
                const errorMessage = result && result.message 
                    ? result.message 
                    : 'Произошла неизвестная ошибка при удалении бригады';
                showAlert(errorMessage, 'warning');
                console.error('Ошибка при удалении бригады:', errorMessage);
               }
        } catch (error) {
            console.error('Ошибка при удалении бригады:', error);
            const errorMessage = error.message || 'Произошла ошибка при удалении бригады';
            showAlert(errorMessage, 'warning');
            
            // Если это ошибка 404, обновляем страницу
            if (error.message && error.message.includes('404')) {
                console.log('Обновляем страницу из-за ошибки 404');
                setTimeout(() => window.location.reload(), 2000);
            }
        }
    });
}

// async function handleDeleteMember(event) {
//     console.log('handleDeleteMember');
//     console.log(event);
// }
  

// Инициализация удаления сотрудника
export function initDeleteEmployee() {
    const deleteEmployeeBtns = document.querySelectorAll('.delete-employee-btn');
    if (deleteEmployeeBtns && deleteEmployeeBtns.length > 0) {
        deleteEmployeeBtns.forEach(button => {
            button.addEventListener('click', handleDeleteEmployee);
        });
    }
}

async function handleDeleteEmployee(event) {
    console.log('handleDeleteEmployee');
    
    console.log(event);
    
    if (confirm('Вы уверены, что хотите удалить сотрудника?')) {
        // Получаем данные о сотруднике из атрибутов кнопки
        const employeeName = event.currentTarget.getAttribute('data-employee-name');
        const employeeId = event.currentTarget.getAttribute('data-employee-id');

        console.log('Удаление сотрудника:', employeeName, 'ID:', employeeId);

        const data = {
            employee_id: employeeId,
        };

        console.log('Отправка данных на сервер:', data);

        const result = await postData('/employee/delete', data);

        console.log('Ответ от сервера:', result);

        if (result.success) {
            showAlert(`Сотрудник "${employeeName}" успешно удален`, 'success');

            // Удаляем строку с удаленным сотрудником из таблицы
            const row = document.querySelector(`tr[data-employee-id="${employeeId}"]`);
            if (row) {
                // Плавно скрываем строку перед удалением
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                
                // Удаляем строку после завершения анимации
                setTimeout(() => {
                    row.remove();
                    
                    // Показываем сообщение, если таблица пуста
                    const tbody = document.querySelector('#employeesTable tbody');
                    if (tbody && tbody.children.length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = `
                            <td colspan="6" class="text-center py-4">
                                <div class="text-muted">Сотрудники не найдены</div>
                            </td>
                        `;
                        tbody.appendChild(emptyRow);
                    }
                }, 300);
            }
        } else {
            showAlert(result.message || 'Произошла ошибка при удалении сотрудника', 'danger');
        }
    }    
    // showAlert(`Реализация удаления сотрудника "${employeeName}" в разработке`, 'warning');
}
// END Инициализация удаления сотрудника

// Инициализация фильтра сотрудников
export function initEmployeeFilter() {
    const employeeFilter = document.getElementById('employeeFilter');   
    // console.log('employeeFilter:', employeeFilter);
    if (employeeFilter) {
        employeeFilter.addEventListener('change', function() {
            const selectedEmployeeId = this.value;
            handleEmployeeFilterChange(selectedEmployeeId);

            console.log('changeSelectedEmployeeId:', selectedEmployeeId);

        });
    }
}

async function handleEmployeeFilterChange(selectedEmployeeId) {
    // Получаем выбранный вариант из селекта
    const select = document.getElementById('employeeFilter');
    const selectedOption = select ? select.options[select.selectedIndex] : null;
    const employeeName = selectedOption ? selectedOption.text.trim() : '';
    
    // Если выбран "Все сотрудники" или пустое значение
    if (!selectedEmployeeId || !employeeName) {
        document.querySelectorAll('#requestsTable tbody tr').forEach(row => {
            row.style.display = '';
        });
        return;
    }

    // Иначе фильтруем по фамилии и первой букве имени в ячейке бригады
    const rows = document.querySelectorAll('#requestsTable tbody tr');

    const requestsData = localStorage.getItem('requestsData');
    const requests = requestsData ? JSON.parse(requestsData) : [];

    console.log('requests:', requests);
    
    // Получаем фамилию и первую букву имени (например, "Абдуганиев Н.")
    const nameParts = employeeName.split(' ');
    const searchPattern = `${nameParts[0]} ${nameParts[1].charAt(0)}.`;

    console.log('searchPattern:', searchPattern);
    console.log('rows:', rows);
    console.log('employeeName:', employeeName);
    
    rows.forEach(row => {
        const brigadeCell = row.querySelector('.col-brigade__div');
        if (brigadeCell) {
            // Получаем весь текст из ячейки бригады
            const brigadeText = brigadeCell.textContent || '';
            
            // Проверяем, содержит ли текст фамилию и первую букву имени
            const hasEmployee = brigadeText.includes(searchPattern);
            row.style.display = hasEmployee ? '' : 'none';
        } else {
            // Если ячейка бригады не найдена, скрываем строку
            row.style.display = 'none';
        }
    });

    // Получаем все видимые строки таблицы
    const visibleRows = Array.from(document.querySelectorAll('#requestsTable tbody tr')).filter(
        row => row.style.display !== 'none'
    );
    
    // Получаем ID заявок из видимых строк
    const visibleRequestIds = visibleRows.map(row => 
        parseInt(row.getAttribute('data-request-id'))
    );
    
    // Фильтруем массив requests, оставляя только те заявки, которые есть в видимых строках
    const requestsNew = requests.filter(request => 
        visibleRequestIds.includes(request.id)
    );
    
    console.log('Отфильтрованные заявки:', requestsNew);

    localStorage.setItem('requestsData', JSON.stringify(requestsNew));
    
    // Прокручиваем к первой видимой строке
    if (visibleRows.length > 0) {
        visibleRows[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// END initEmployeeFilter()

export function initEmployeeEditHandlers() {
    // Инициализация модального окна
    const editEmployeeModalEl = document.getElementById('editEmployeeModal');
    const editEmployeeModal = new bootstrap.Modal(editEmployeeModalEl);
    
    // Сброс селекта ролей при закрытии модального окна
    editEmployeeModalEl.addEventListener('hidden.bs.modal', function () {
        const roleSelect = document.getElementById('roleSelectUpdate');
        if (roleSelect) {
            roleSelect.selectedIndex = 0; // Сбрасываем на первое значение (обычно 'Выберите роль')
        }
    });

    // Обработчик кнопок редактирования
    document.querySelectorAll('.edit-employee-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const employeeId = this.getAttribute('data-employee-id');
            const employeeName = this.getAttribute('data-employee-name');

            // Вывод информации в консоль
            console.log(`Редактирование сотрудника: ${employeeName} (ID: ${employeeId})`);

            // Обновление заголовка модального окна
            document.getElementById('editEmployeeModalLabel').textContent = `Редактирование сотрудника: ${employeeName}`;

            // Устанавливаем ID пользователя в скрытое поле формы
            // document.getElementById('userIdInputUpdate').value = employeeId;
            // Устанавливаем ID сотрудника в скрытое поле формы
            document.getElementById('employeeIdInputUpdate').value = employeeId;
            console.log('Установлен ID пользователя и сотрудника в форме:', employeeId);

            // Загрузка данных сотрудника по ID
            try {
                const response = await fetch(`/employee/get?employee_id=${employeeId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`Ошибка HTTP: ${response.status}`);
                }

                const data = await response.json();

                console.log('Полученные данные сотрудника:', data);

                if (data.success) {
                    // Заполняем форму данными сотрудника
                    const employee = data.data.employee;
                    const passport = data.data.passport;
                    const car = data.data.car;
                    const role = data.data.role;

                    console.log(role);

                    // document.getElementById('loginInputUpdate').value = employee.login || '';
                    // document.getElementById('passwordInputUpdate').value = employee.password || '';

                    // Заполняем основные поля сотрудника
                    document.getElementById('fioInputUpdate').value = employee.fio || '';
                    document.getElementById('phoneInputUpdate').value = employee.phone || '';

                    // Устанавливаем должность
                    const positionSelect = document.getElementById('positionSelectUpdate');
                    if (positionSelect) {
                        positionSelect.value = employee.position_id || '';
                    }

                    // Заполняем дополнительные поля
                    if (employee.birth_date) {
                        document.getElementById('birthDateInputUpdate').value = employee.birth_date;
                    }

                    if (employee.birth_place) {
                        document.getElementById('birthPlaceInputUpdate').value = employee.birth_place;
                    }

                    if (employee.registration_place) {
                        document.getElementById('registrationPlaceInputUpdate').value = employee.registration_place;
                    }

                    // Заполняем паспортные данные, если они есть
                    if (passport) {
                        document.getElementById('passportSeriesInputUpdate').value = passport.series_number || '';
                        document.getElementById('passportIssuedByInputUpdate').value = passport.issued_by || '';

                        if (passport.issued_at) {
                            document.getElementById('passportIssuedAtInputUpdate').value = passport.issued_at;
                        }

                        document.getElementById('passportDepartmentCodeInputUpdate').value = passport.department_code || '';
                    }

                    // Заполняем данные об автомобиле, если они есть
                    if (car) {
                        document.getElementById('carBrandInputUpdate').value = car.brand || '';
                        document.getElementById('carLicensePlateInputUpdate').value = car.license_plate || '';
                        // Поля для года и цвета автомобиля отсутствуют в форме
                    }

                    /*
                    <select name="role_id_update" id="roleSelectUpdate" class="form-select mb-4" required="" data-field-name="Системная роль">
                        <option value="">Выберите системную роль</option>    
                        <option value="1">admin</option>
                        <option value="2">user</option>
                        <option value="3">fitter</option>     
                    </select>
                    */

                    const roleSelectUpdate = document.getElementById('roleSelectUpdate');
                    // roleSelectUpdate.value = role.role_id;
                    const option = roleSelectUpdate.options[0];
                    option.value = role.role_id;
                    option.innerText = role.name;

                    console.log(option);
                    console.log(roleSelectUpdate);

                    console.log('Данные сотрудника успешно загружены');
                } else {
                    console.error('Ошибка при загрузке данных сотрудника:', data.message);
                    showAlert('Ошибка при загрузке данных сотрудника: ' + data.message, 'danger');
                }
            } catch (error) {
                console.error('Ошибка при загрузке данных сотрудника:', error);
                showAlert('Ошибка при загрузке данных сотрудника', 'danger');
            }

            // Открытие модального окна
            editEmployeeModal.show();
        });
    });

    // Добавляем обработчик для кнопки "Закрыть"
    const closeButton = document.querySelector('#editEmployeeModal .btn-secondary[data-bs-dismiss="modal"]');
    if (closeButton) {
        closeButton.addEventListener('click', function(e) {
            e.preventDefault(); // Предотвращаем стандартное поведение
            closeModalProperly();
        });
    }
}

/**
 * Инициализирует обработчик события для формы редактирования сотрудника
 */
export function initSaveEmployeeChanges() {
    // Находим кнопку сохранения изменений
    const saveButton = document.getElementById('saveEmployeeChanges');
    const form = document.getElementById('employeeFormUpdate');

    if (saveButton && form) {
        // Добавляем обработчик события отправки формы
        form.addEventListener('submit', function(event) {
            console.log('Сохранение изменений сотрудника');
            handleSaveEmployeeChanges(event);
        });
        
        // Для совместимости оставляем и клик по кнопке
        saveButton.addEventListener('click', function(event) {
            console.log('Клик по кнопке сохранения');
            handleSaveEmployeeChanges(event);
        });
    } else {
        console.error('Элементы с ID "saveEmployeeChanges" или "employeeFormUpdate" не найдены');
    }
}

/**
 * Инициализирует обработчики событий для формы заявки
 */
export function initFormHandlers() {
    // Находим кнопку отправки формы заявки
    const submitBtn = document.getElementById('submitRequest');

    // Если кнопка найдена, добавляем обработчик события click
    if (submitBtn) {
        submitBtn.addEventListener('click', submitRequestForm);
    }

    // Инициализация поля даты исполнения
    initExecutionDateField();

    // Добавляем обработчик события для модального окна создания заявки
    const newRequestModal = document.getElementById('newRequestModal');
    if (newRequestModal) {
        // Удаляем существующие обработчики, чтобы избежать дублирования
        newRequestModal.removeEventListener('show.bs.modal', handleModalShow);
        newRequestModal.removeEventListener('hidden.bs.modal', handleModalHide);
        
        // Обработчик открытия модального окна
        function handleModalShow() {
            // При открытии модального окна обновляем минимальную дату
            initExecutionDateField();
            refreshAddresses();
            
            // Инициализируем WYSIWYG редактор
            if (window.initWysiwygEditor) {
                try {
                    window.initWysiwygEditor();
                    console.log('WYSIWYG редактор инициализирован');
                } catch (error) {
                    console.error('Ошибка при инициализации WYSIWYG редактора:', error);
                }
            }
        }
        
        // Обработчик закрытия модального окна
        function handleModalHide() {
            // Уничтожаем редактор при скрытии модального окна
            if (window.destroyWysiwygEditor) {
                window.destroyWysiwygEditor();
            }
            
            // Очищаем скрытое поле comment
            const commentField = document.getElementById('comment');
            if (commentField) {
                commentField.value = '';
            }
        }
        
        // Добавляем обработчики событий
        newRequestModal.addEventListener('show.bs.modal', handleModalShow);
        newRequestModal.addEventListener('hidden.bs.modal', handleModalHide);
    }
}

// Функция для обновления списка адресов
function refreshAddresses() {
    const selectElement = document.getElementById('addresses_id');
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
            selectElement.innerHTML = '<option value="" disabled selected>Выберите адрес</option>';

            // Добавляем адреса в выпадающий список
            addresses.forEach(address => {
                const option = document.createElement('option');
                option.value = address.id;
                option.textContent = [
                    address.street + (address.houses ? `, ${address.houses}` : ''),
                    address.district ? `[${address.district}]` : '',
                    address.city ? `[${address.city}]` : ''
                ].filter(Boolean).join(' ');
                
                // Добавляем дополнительные данные для удобства
                option.dataset.street = address.street || '';
                option.dataset.houses = address.houses || '';
                option.dataset.city = address.city || '';
                option.dataset.district = address.district || '';

                selectElement.appendChild(option);
            });

            // Инициализируем кастомный селект, если функция доступна
            if (window.initCustomSelect) {
                window.initCustomSelect('addresses_id', 'Выберите адрес из списка');
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке адресов:', error);
            selectElement.innerHTML = originalInnerHTML;
            showAlert('Ошибка при загрузке списка адресов', 'danger');
        });
}

/**
 * Инициализирует поле даты исполнения
 * Устанавливает минимальную дату равной текущей
 */
function initExecutionDateField() {
    const executionDateField = document.getElementById('executionDate');
    if (!executionDateField) return;
    
    // Устанавливаем минимальную дату на сегодня
    const today = new Date().toISOString().split('T')[0];
    executionDateField.min = today;

    // Инициализируем обработчик события показа модального окна
    const newRequestModal = document.getElementById('newRequestModal');
    if (newRequestModal) {
        newRequestModal.addEventListener('show.bs.modal', function() {
            // Если есть выбранная дата в selectedDateState, используем её
            if (window.selectedDateState && window.selectedDateState.date) {
                // Преобразуем дату из формата DD.MM.YYYY в YYYY-MM-DD
                const [day, month, year] = window.selectedDateState.date.split('.');
                const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                executionDateField.value = formattedDate;
                console.log('Установлена выбранная дата из календаря:', formattedDate);
            } else {
                // Иначе используем текущую дату
                executionDateField.value = today;
                console.log('Установлена текущая дата:', today);
            }
        });
    }
}

/**
 * Обрабатывает отправку формы новой заявки
 */
async function submitRequestForm(event) {
    // Prevent default form submission
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    console.log('Начало функции submitRequestForm');
    
    const form = document.getElementById('newRequestForm');
    const submitBtn = document.getElementById('submitRequest');
    const commentField = document.getElementById('comment');
    const commentError = commentField ? commentField.nextElementSibling : null;

    console.log('Форма:', form);
    console.log('Кнопка отправки:', submitBtn);
    
    if (!form) {
        console.error('Форма newRequestForm не найдена в DOM');
        return false;
    }
    
    if (!submitBtn) {
        console.error('Кнопка submitRequest не найдена в DOM');
        return false;
    }

    // Get the address error element once at the beginning
    const addressError = document.getElementById('addresses_id_error');
    const addressId = document.getElementById('addresses_id')?.value;
    
    // Reset all error states
    form.classList.remove('was-validated');
    if (commentField) commentField.classList.remove('is-invalid');
    if (commentError) commentError.classList.add('d-none');
    if (addressError) addressError.classList.add('d-none');

    // Validate required fields
    let isValid = true;
    
    // Validate address
    if (!addressId) {
        if (addressError) {
            addressError.textContent = 'Пожалуйста, выберите адрес из списка';
            addressError.classList.remove('d-none');
            const customSelectInput = document.querySelector('.custom-select-input');
            if (customSelectInput) customSelectInput.classList.add('is-invalid');
        }
        isValid = false;
    }
    
    // Валидация комментария
    if (commentField) {
        const editor = document.getElementById('comment_editor');
        const editorContent = editor ? editor.innerHTML.trim() : '';
        const isCommentEmpty = editorContent === '' || editorContent === '<br>';
        
        // Всегда синхронизируем содержимое редактора со скрытым полем
        commentField.value = editorContent;
        
        // Проверяем валидность при каждой отправке формы
        if (isCommentEmpty) {
            // Показываем сообщение об ошибке
            if (commentError) {
                commentError.classList.remove('d-none');
                commentError.textContent = 'Пожалуйста, введите комментарий';
            }
            // Добавляем класс is-invalid для стилизации
            const editorContainer = document.querySelector('.wysiwyg-editor');
            if (editorContainer) {
                editorContainer.classList.add('is-invalid');
            }
            isValid = false;
        } else {
            // Скрываем сообщение об ошибке, если поле валидно
            if (commentError) {
                commentError.classList.add('d-none');
            }
            // Убираем класс is-invalid
            const editorContainer = document.querySelector('.wysiwyg-editor');
            if (editorContainer) {
                editorContainer.classList.remove('is-invalid');
            }
        }
    }
    
    if (!isValid) {
        form.classList.add('was-validated');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Создать заявку';
        return;
    }

    console.log('Находим элемент addresses_id:', document.getElementById('addresses_id'));
    console.log('Значение элемента addresses_id:', addressId);

    // Блокируем кнопку отправки и меняем её текст на индикатор загрузки
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Создание...`;

    // Создаём объект FormData из формы для сбора всех полей
    const formData = new FormData(form);
    // Создаём пустой объект для хранения данных формы
    const data = {};

    // Обрабатываем все поля формы
    formData.forEach((value, key) => {
        // Если поле с таким именем уже существует
        if (data[key] !== undefined) {
            // Преобразуем значение в массив, если это ещё не массив
            if (!Array.isArray(data[key])) data[key] = [data[key]];
            // Добавляем новое значение в массив (для полей с множественным выбором)
            data[key].push(value);
        } else {
            // Для нового поля просто сохраняем значение
            data[key] = value;
        }
    });

    // Формируем данные для отправки
    const requestData = {
        _token: data._token,
        client_name: data.client_name || '',
        client_phone: data.client_phone || '',
        client_organization: data.client_organization || '',
        request_type_id: data.request_type_id,
        status_id: data.status_id,
        comment: data.comment || '',
        execution_date: data.execution_date || null,
        execution_time: data.execution_time || null,
        brigade_id: data.brigade_id || null,
        operator_id: data.operator_id || null,
        address_id: addressId,
    };

    // Логируем данные перед отправкой
    console.log('Отправляемые данные:', requestData);

    // return;

    try {
        const result = await postData('/api/requests', requestData);

        console.log('Ответ от сервера:', result);

        if (result.success) {
            showAlert('Заявка успешно создана!', 'success');

            // Обновляем дату исполнения заявки (преобразование формата происходит в методе updateDate)
            executionDateState.updateDate(result.data.request.execution_date);

            console.log('currentDateState.date:', currentDateState.date);
            console.log('selectedDateState.date:', selectedDateState.date);
            console.log('executionDateState.date:', executionDateState.date);



            // Динамическое формирование строки заявки и добавление её в начало таблицы
            if (currentDateState.date === selectedDateState.date && executionDateState.date === selectedDateState.date) {
                addRequestToTable(result);
            } else if (currentDateState.date !== selectedDateState.date && executionDateState.date === selectedDateState.date) {
                console.log('Добавляем заявку в таблицу, если дата исполнения заявки совпадает с выбранной датой');
                addRequestToTable(result);
            }

            // Не перезагружаем страницу, чтобы не потерять динамически добавленную строку

            // Отображаем информацию о сотруднике, если она есть в ответе
            if (result.data && result.data.employee) {
                displayEmployeeInfo(result.data.employee);
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('newRequestModal'));
            modal.hide();

            // Сохраняем текущую дату перед сбросом формы
            const currentDate = document.getElementById('executionDate').value;

            // Clear the editor content
            const editor = document.getElementById('comment_editor');
            if (editor) editor.innerHTML = '';
            
            // Reset the form but preserve the date
            const formData = new FormData(form);
            form.reset();
            
            // Reset validation states
            form.classList.remove('was-validated');
            if (commentField) commentField.classList.remove('is-invalid');
            if (commentError) commentError.classList.add('d-none');
            if (addressError) addressError.classList.add('d-none');
            
            // Remove is-invalid class from custom select input
            const customSelectInput = document.querySelector('.custom-select-input');
            if (customSelectInput) customSelectInput.classList.remove('is-invalid');

            // Восстанавливаем дату после сброса формы
            const dateInput = document.getElementById('executionDate');
            if (dateInput) {
                // Получаем текущую дату в формате YYYY-MM-DD
                // Используем локальное время пользователя
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const today = `${year}-${month}-${day}`;

                // Проверяем, что сохраненная дата не раньше текущей
                if (currentDate >= today) {
                    dateInput.value = currentDate;
                    console.log('Восстановлена сохраненная дата:', currentDate);
                } else {
                    // Если дата раньше текущей, устанавливаем текущую дату
                    dateInput.value = today;
                    console.log('Установлена текущая дата:', today, 'т.к. сохраненная дата была раньше:', currentDate);
                }

                // Обновляем атрибут min для предотвращения выбора прошедших дат
                dateInput.min = today;
            }

            // Dispatch event to notify other components about the new request
            const event = new CustomEvent('requestCreated', { detail: result.data });
            document.dispatchEvent(event);

            // If there's a refreshRequestsTable function, call it
            if (typeof window.refreshRequestsTable === 'function') {
                // showAlert('window.refreshRequestsTable()', 'info');
                // window.refreshRequestsTable();
            } else {
                // Fallback to page reload if the function doesn't exist
                window.location.reload();
            }
        } else {
            throw new Error(result.message || 'Ошибка при создании заявки');
        }
    } catch (error) {
        // Не показываем сообщение об ошибке, если это ошибка "Сотрудник не найден"
        // так как мы уже показали alert в функции postData
        if (error.message !== 'EMPLOYEE_NOT_FOUND') {
            showAlert(error.message || 'Произошла ошибка при создании заявки', 'danger');
        }
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Создать заявку';
    }
}

//************* 2. Обработчики событий форм ************//

async function handleSaveEmployeeChanges(event) {
    try {
        // Предотвращаем стандартное поведение формы
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        // Получаем форму
        const form = document.getElementById('employeeFormUpdate');
        if (!form) {
            throw new Error('Форма обновления сотрудника не найдена');
        }

        // Получаем select с ролью
        const roleSelect = document.getElementById('roleSelectUpdate');
        if (!roleSelect) {
            throw new Error('Не найден элемент выбора роли');
        }

        // Проверяем, что роль выбрана
        if (!roleSelect.value) {
            showAlert('Пожалуйста, выберите системную роль', 'warning');
            // Используем setTimeout для корректного фокуса после отрисовки модального окна
            setTimeout(() => {
                roleSelect.focus();
            }, 100);
            return false;
        }

        const formData = new FormData(form);

        const data = {};

        // Обрабатываем все поля формы
        formData.forEach((value, key) => {
            // Если поле с таким именем уже существует
            if (data[key] !== undefined) {
                // Преобразуем значение в массив, если это ещё не массив
                if (!Array.isArray(data[key])) data[key] = [data[key]];
                // Добавляем новое значение в массив (для полей с множественным выбором)
                data[key].push(value);
            } else {
                // Для нового поля просто сохраняем значение
                data[key] = value;
            }
        });

        // Проверяем, что поле должности заполнено
        const positionValue = data.position_id_update || document.getElementById('positionSelectUpdate')?.value;
        if (!positionValue) {
            showAlert('Поле "Должность" обязательно для выбора', 'danger');
            return false; // Прерываем выполнение функции
        }

        // Формируем данные для отправки
        const requestData = {
            _token: data._token,
            user_id: data.user_id_update,
            role_id_update: roleSelect.value,
            fio: data.fio_update,
            position_id: positionValue,
            employee_id: data.employee_id_update,
            phone: data.phone_update,
            birth_date: data.birth_date_update,
            birth_place: data.birth_place_update,
            registration_place: data.registration_place_update,
            passport_series: data.passport_series_update,
            passport_issued_by: data.passport_issued_by_update,
            passport_issued_at: data.passport_issued_at_update,
            passport_department_code: data.passport_department_code_update,
            car_brand: data.car_brand_update,
            car_plate: data.car_plate_update,
            car_registered_at: data.car_registered_at_update,
        };

        const result = await postData('/employee/update', requestData);

        console.log('Входные данные для обновления сотрудника:', requestData);

        console.log('Ответ от сервера при обновлении сотрудника:', result);

        const role = result.data.role;

        if (result.success) {
            showAlert(`Сотрудник <b>${result.data.employee.fio}</b> успешно обновлен`, 'success');
            closeModalProperly();

            // Обновляем только измененную строку в таблице
            const { employee, passport, car, position } = result.data;
            const row = document.querySelector(`tr[data-employee-id="${employee.id}"]`);
            console.log('Найденная строка:', row);
            console.log('Позиция:', position.position_name);
            
            if (row) {
                console.log('Строка найдена, обновляем данные...');
                const cells = row.getElementsByTagName('td');
                
                // Обновляем ФИО и email (первая ячейка)
                if (cells[0]) {
                    const email = role.user_email;
                    // || cells[0].querySelector('div:first-child')?.textContent.split('\n')[1]?.trim() || '';
                    cells[0].querySelector('div:first-child').innerHTML = `${employee.fio || ''} <br> ${email}`;
                }
                
                // Обновляем телефон (вторая ячейка)
                if (cells[1]) cells[1].textContent = employee.phone || '';
                
                // Обновляем должность (третья ячейка)
                if (cells[2]) cells[2].textContent = position.position_name || '';
                
                // Обновляем дату рождения (четвертая ячейка)
                if (cells[3] && employee.birth_date) {
                    // Форматируем дату из YYYY-MM-DD в DD-MM-YYYY
                    const [year, month, day] = employee.birth_date.split('-');
                    cells[3].textContent = `${day}-${month}-${year}`; // Форматируем дату рождения
                }
                
                // Обновляем паспортные данные (пятая ячейка)
                if (cells[4] && passport) {
                    cells[4].innerHTML = `
                        ${passport.series_number || ''} <br>
                        ${passport.issued_at || ''} <br>
                        ${passport.issued_by || ''} <br>
                        ${passport.department_code || ''}
                    `;
                }
                
                // Обновляем данные автомобиля (шестая ячейка)
                if (cells[5] && car) {
                    cells[5].innerHTML = `${car.brand || ''} <br> ${car.license_plate || ''}`;
                }
                
                // Обновляем атрибуты кнопок
                const editBtn = row.querySelector('.edit-employee-btn');
                const deleteBtn = row.querySelector('.delete-employee-btn');
                
                if (editBtn) {
                    editBtn.setAttribute('data-employee-id', employee.id);
                    editBtn.setAttribute('data-employee-name', employee.fio || '');
                }
                
                if (deleteBtn) {
                    deleteBtn.setAttribute('data-employee-id', employee.id);
                    deleteBtn.setAttribute('data-employee-name', employee.fio || '');
                }
            }
        } else {
            showAlert(result.message || 'Произошла ошибка при обновлении сотрудника', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при обновления сотрудника:', error);
        showAlert(`Ошибка: ${error.message}`, 'danger');
    }
}

// Функция для инициализации обработчиков кнопок сотрудников
function initEmployeeButtons() {
    // Обработчики для кнопок редактирования
    // document.querySelectorAll('.edit-employee-btn').forEach(btn => {
    //     if (!btn.hasAttribute('data-listener-added')) {
    //         btn.addEventListener('click', function() {
    //             const employeeId = this.getAttribute('data-employee-id');
    //             // Здесь можно добавить логику для открытия модального окна редактирования
    //             // или использовать существующий обработчик, если он уже есть
    //             console.log('Edit employee:', employeeId);
    //         });
    //         btn.setAttribute('data-listener-added', 'true');
    //     }
    // });
    
    // Обработчики для кнопок удаления
    // document.querySelectorAll('.delete-employee-btn').forEach(btn => {
    //     if (!btn.hasAttribute('data-listener-added')) {
    //         btn.addEventListener('click', function() {
    //             const employeeId = this.getAttribute('data-employee-id');
    //             const employeeName = this.getAttribute('data-employee-name');
                
    //             if (confirm(`Вы уверены, что хотите удалить сотрудника: ${employeeName}?`)) {
    //                 // Здесь можно добавить логику для удаления сотрудника
    //                 console.log('Delete employee:', employeeId);
    //             }
    //         });

    //         btn.setAttribute('data-listener-added', 'true');
    //     }
    // });
}

// Обработчик для кнопки "Удалить" на вкладке "Запланированные заявки"
function initRequestCloseHandlers() {
    document.addEventListener('click', async function(event) {
        if (event.target.closest('.request-delete')) {
            event.preventDefault();
            const button = event.target.closest('.request-delete');
            const requestId = button.dataset.requestId;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            if (!confirm('Вы уверены, что хотите удалить эту заявку?')) {
                return;
            }

            try {
                console.log('Нажата кнопка "Удалить" для заявки ID:', requestId);
                const response = await fetch(`/requests/${requestId}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ request_id: requestId })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Ответ сервера:', data);
                
                if (data.success) {
                    // Удаляем строку с заявкой из таблицы
                    // button.closest('tr').remove();

                    await loadPlanningRequests();
                } else {
                    showAlert(data.message || 'Произошла ошибка при удалении заявки', 'danger');
                }
            } catch (error) {
                console.error('Ошибка при удалении заявки:', error);
                showAlert('Произошла ошибка при удалении заявки', 'danger');
            }
        }
    });
}

// Инициализируем кнопки при загрузке страницы
// Функция для инициализации валидации номера дома
export function initHouseNumberValidator() {
    const houseInputs = document.querySelectorAll('input[name="houses"]');
    houseInputs.forEach(input => {
        new HouseNumberValidator(input, {
            errorMessage: 'Введите номер дома в соответствии с форматом'
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initEmployeeButtons();
    initShowFilesButtons();
    initShowPhotoButtons();
    initAddCity();
    initHouseNumberValidator();
    initCommentHandlers();
    initRequestCloseHandlers();
    initPhotoReportList();
    initDownloadAllPhotos();
    initUploadExcel();
    initExportReportBtn();
    initOpenMapBtn();
});

// Обработчик загрузки файла Excel
function initUploadExcel() {
    const uploadExcelBtn = document.getElementById('uploadExcelBtn');
    const uploadExcelForm = document.getElementById('uploadExcelForm');
    const fileInput = document.getElementById('excelFile');
    
    if (!uploadExcelBtn || !uploadExcelForm || !fileInput) {
        console.warn('Не найдены необходимые элементы для загрузки Excel');
        return;
    }

    // Обработчик клика по кнопке загрузки
    uploadExcelBtn.addEventListener('click', handleExcelUpload);
    
    // Обработчик сброса формы после успешной загрузки
    uploadExcelForm.addEventListener('reset', () => {
        fileInput.value = '';
    });
}

// Функция обработки загрузки Excel файла
async function handleExcelUpload() {
    const fileInput = document.getElementById('excelFile');
    const file = fileInput?.files?.[0];
    
    if (!file) {
        showAlert('Пожалуйста, выберите файл для загрузки', 'warning');
        return;
    }

    // Проверяем расширение файла
    const validExtensions = ['.xlsx', '.xls', '.csv'];
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    if (!validExtensions.some(ext => file.name.toLowerCase().endsWith(ext))) {
        showAlert('Пожалуйста, выберите файл с расширением .xlsx, .xls или .csv', 'warning');
        return;
    }

    // Выводим информацию о файле
    console.log('Информация о загружаемом файле:', {
        'Имя файла': file.name,
        'Размер': `${(file.size / 1024).toFixed(2)} КБ`,
        'Тип': file.type || 'Не определен',
        'Дата последнего изменения': new Date(file.lastModified).toLocaleString()
    });

    const formData = new FormData();
    formData.append('excel_file', file);
    
    // Добавляем CSRF-токен
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        console.error('CSRF token не найден');
        showAlert('Ошибка безопасности. Пожалуйста, обновите страницу.', 'danger');
        return;
    }

    try {
        const response = await fetch('/upload-excel', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });

        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || `Ошибка сервера: ${response.status}`);
        }

        console.log('Успешный ответ сервера:', data);
        showAlert('Файл успешно загружен и обработан', 'success');
        
        // Очищаем форму после успешной загрузки
        if (uploadExcelForm) {
            uploadExcelForm.reset();
        }
        
        // Обновляем список заявок, если функция доступна
        if (typeof loadPlanningRequests === 'function') {
            loadPlanningRequests();
        }
        
    } catch (error) {
        console.error('Ошибка при загрузке файла Excel:', error);
        const errorMessage = error.message || 'Произошла ошибка при загрузке файла';
        showAlert(errorMessage, 'danger');
    }
}

// Инициализация обработчиков кнопок "Показать файлы"
function initShowFilesButtons() {
    // Обработчик для уже существующих кнопок
    document.addEventListener('click', function(e) {
        const showFilesBtn = e.target.closest('.data-show-files-btn');
        if (showFilesBtn) {
            e.preventDefault();
            const commentId = showFilesBtn.getAttribute('data-comment-id');
            // console.log('Показать файлы для комментария ID:', commentId);
            
            // Используем глобальную функцию для загрузки фотографий
            loadCommentFiles(commentId, showFilesBtn);
        }
    });
}

// Инициализация обработчиков кнопок "Показать фото"
function initShowPhotoButtons() {
    // Обработчик для уже существующих кнопок
    document.addEventListener('click', function(e) {
        const showPhotoBtn = e.target.closest('.data-show-photo-btn');
        if (showPhotoBtn) {
            e.preventDefault();
            const commentId = showPhotoBtn.getAttribute('data-comment-id');
            const photosContainerId = `comment-photos-${commentId}`;
            
            // Проверяем, активна ли уже кнопка (показаны ли фото)
            const isActive = showPhotoBtn.classList.contains('active');
            const photosContainer = document.getElementById(photosContainerId);
            
            if (isActive) {
                // Если кнопка активна, скрываем фотографии
                showPhotoBtn.classList.remove('active');
                if (photosContainer) {
                    photosContainer.remove();
                }
            } else {
                // Скрываем все открытые фотографии
                document.querySelectorAll('.data-show-photo-btn.active').forEach(btn => {
                    btn.classList.remove('active');
                    const containerId = `comment-photos-${btn.getAttribute('data-comment-id')}`;
                    const container = document.getElementById(containerId);
                    if (container) container.remove();
                });
                
                // Показываем фотографии для выбранного комментария
                showPhotoBtn.classList.add('active');
                loadCommentPhotos(commentId, showPhotoBtn);
            }
        }
    });
}

async function loadCommentFiles(commentId, showFilesBtn) {
    try {
        console.log('Показать файлы для комментария ID:', commentId);

        // Находим модальное окно комментариев
        const commentsModal = document.getElementById('commentsModal');
        if (!commentsModal) {
            console.error('Модальное окно комментариев не найдено');
            return;
        }

        // Создаем уникальный ID для контейнера файлов этого комментария
        const filesContainerId = `comment-files-${commentId}`;
        
        // Удаляем старый контейнер, если он существует
        const oldContainer = document.getElementById(filesContainerId);
        if (oldContainer) {
            oldContainer.remove();
        }

        // Создаем новый контейнер для файлов
        const filesContainer = document.createElement('div');
        filesContainer.id = filesContainerId;
        filesContainer.className = 'comment-files-container mt-3 w-100';
        // Заставляем контейнер занимать всю ширину в родительском flex-wrap,
        // чтобы следующая ссылка "Скачать файлы" перешла на новую строку ниже
        try {
            filesContainer.style.flex = '1 1 100%';
            filesContainer.style.width = '100%';
        } catch (e) {}
        
        // Вставляем контейнер после кнопки, если она существует
        if (showFilesBtn && showFilesBtn.parentNode) {
            // Находим родительский элемент кнопки и вставляем контейнер после неё
            showFilesBtn.parentNode.insertBefore(filesContainer, showFilesBtn.nextSibling);
        } else {
            // Или в начало контейнера комментариев, если кнопка не передана
            const commentsContainer = commentsModal.querySelector('#commentsContainer');
            if (commentsContainer) {
                commentsContainer.insertAdjacentElement('afterbegin', filesContainer);
            }
        }

        // Показываем индикатор загрузки
        filesContainer.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <div>Загрузка файлов...</div>
            </div>`;

        // Отправляем GET запрос на сервер для получения файлов комментария
        const response = await fetch(`/api/comments/${commentId}/files`, {
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Ошибка при загрузке файлов');
        }

        const result = await response.json() || [];;
        console.log('Файлы комментария:', result);

        // Очищаем контейнер
        filesContainer.innerHTML = '';

        // Если есть файлы, добавляем их в контейнер
        if (result.data.length > 0) {

            console.log('Файлы комментария в контейнере:', result.data);

            result.data.forEach(file => {
                const fileElement = document.createElement('div');
                fileElement.className = 'file-item mb-2';
                fileElement.innerHTML = `
                    <a href="${file.url}" target="_blank" download>
                        <i class="fas fa-file"></i> ${file.original_name}
                    </a>
                `;
                filesContainer.appendChild(fileElement);
            });
        } else {
            filesContainer.innerHTML = '<div class="text-center py-3">Файлы не найдены</div>';
        }

    } catch (error) {
        console.error('Ошибка при загрузке файлов:', error);
    }
}

// Функция для загрузки и отображения фотографий комментария
async function loadCommentPhotos(commentId, showPhotoBtn) {
    try {
        // Находим модальное окно комментариев
        const commentsModal = document.getElementById('commentsModal');
        if (!commentsModal) {
            console.error('Модальное окно комментариев не найдено');
            return;
        }

        // Создаем уникальный ID для контейнера фотографий этого комментария
        const photosContainerId = `comment-photos-${commentId}`;
        
        // Удаляем старый контейнер, если он существует
        const oldContainer = document.getElementById(photosContainerId);
        if (oldContainer) {
            oldContainer.remove();
        }
        
        // Создаем новый контейнер для фотографий
        const photosContainer = document.createElement('div');
        photosContainer.id = photosContainerId;
        photosContainer.className = 'comment-photos-container mt-3';
        
        // Вставляем контейнер после кнопки, если она существует
        if (showPhotoBtn && showPhotoBtn.parentNode) {
            // Находим родительский элемент кнопки и вставляем контейнер после неё
            showPhotoBtn.parentNode.insertBefore(photosContainer, showPhotoBtn.nextSibling);
        } else {
            // Или в начало контейнера комментариев, если кнопка не передана
            const commentsContainer = commentsModal.querySelector('#commentsContainer');
            if (commentsContainer) {
                commentsContainer.insertAdjacentElement('afterbegin', photosContainer);
            }
        }

        // Показываем индикатор загрузки
        photosContainer.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <div>Загрузка фотографий...</div>
            </div>`;

        // Отправляем POST запрос на сервер для получения фотографий комментария
        const response = await fetch('/api/comments/' + commentId + '/photos', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ comment_id: commentId })
        });
        
        const result = await response.json();

        console.log('Ответ сервера:', result);

        // return;
        
        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Ошибка при загрузке фотографий');
        }

        const photos = result.data || [];

        console.log('Полученные фотографии:', photos);  
        
        // Очищаем контейнер перед добавлением фотографий
        photosContainer.innerHTML = '';
        
        // Добавляем заголовок раздела с фотографиями, если есть фотографии
        if (photos.length > 0) {
            const photosHeader = document.createElement('h6');
            photosHeader.className = 'mt-3 mb-2';
            photosHeader.textContent = 'Прикрепленные фотографии:';
            photosContainer.appendChild(photosHeader);
            
            // Создаем контейнер для сетки фотографий
            const photosGrid = document.createElement('div');
            photosGrid.className = 'row g-2';
            
            // Добавляем фотографии в сетку
            photos.forEach(photo => {
                const photoCol = document.createElement('div');
                photoCol.className = 'col-12 col-sm-6 col-md-4 col-lg-3 mb-3';
                
                const photoCard = document.createElement('div');
                photoCard.className = 'card h-100';
                photoCard.style.cursor = 'pointer';
                photoCard.style.overflow = 'hidden';
                
                const img = new Image();
                img.src = photo.url || `/storage/${photo.path}`;
                img.className = 'card-img-top img-fluid';
                img.loading = 'lazy';
                
                // Ждем загрузки изображения, чтобы определить его ориентацию
                img.onload = function() {
                    const isPortrait = this.naturalHeight > this.naturalWidth;
                    
                    if (isPortrait) {
                        // Для портретных фото - занимаем всю ширину контейнера
                        img.style.width = '100%';
                        img.style.height = 'auto';
                        img.style.maxHeight = '500px';
                        img.style.objectFit = 'contain';
                    } else {
                        // Для альбомных фото - ограничиваем высоту
                        img.style.maxHeight = '300px';
                        img.style.width = '100%';
                        img.style.objectFit = 'contain';
                    }
                    
                    img.style.objectPosition = 'center';
                };
                
                const cardBody = document.createElement('div');
                cardBody.className = 'card-body p-2';
                
                const fileName = document.createElement('div');
                fileName.className = 'small text-truncate';
                fileName.textContent = photo.original_name || 'Фото';
                fileName.title = photo.original_name || 'Фото';
                
                const fileSize = document.createElement('div');
                fileSize.className = 'small text-muted';
                fileSize.textContent = photo.file_size ? `${(photo.file_size / 1024).toFixed(1)} KB` : '';
                
                cardBody.appendChild(fileName);
                cardBody.appendChild(fileSize);
                
                photoCard.appendChild(img);
                photoCard.appendChild(cardBody);
                photoCol.appendChild(photoCard);
                photosGrid.appendChild(photoCol);
                
                // Обработчик клика для просмотра в полном размере
                photoCard.addEventListener('click', (e) => {
                    e.stopPropagation();
                    
                    const fullScreen = document.createElement('div');
                    fullScreen.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0, 0, 0, 0.9);
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        z-index: 2000;
                        cursor: pointer;
                        padding: 20px;
                    `;
                    
                    const fullImg = document.createElement('img');
                    fullImg.src = photo.url || `${photo.path}`;
                    fullImg.style.maxWidth = '90%';
                    fullImg.style.maxHeight = '80vh';
                    fullImg.style.objectFit = 'contain';
                    
                    const imgInfo = document.createElement('div');
                    imgInfo.className = 'text-white text-center mt-3';
                    imgInfo.innerHTML = `
                        <div>${photo.original_name || 'Фото'}</div>
                        <div class="small text-muted">${photo.file_size ? `${(photo.file_size / 1024).toFixed(1)} KB` : ''}</div>
                    `;
                    
                    fullScreen.appendChild(fullImg);
                    fullScreen.appendChild(imgInfo);
                    document.body.appendChild(fullScreen);
                    
                    // Закрытие при клике
                    fullScreen.addEventListener('click', () => {
                        document.body.removeChild(fullScreen);
                    });
                });
            });
            
            photosContainer.appendChild(photosGrid);
        }

        console.log('photosContainer:', photosContainer);

return;

        // modal.style.cssText = `
        //     position: fixed;
        //     top: 0;
        //     left: 0;
        //     width: 100%;
        //     height: 100%;
        //     background-color: rgba(0, 0, 0, 0.9);
        //     display: flex;
        //     flex-direction: column;
        //     align-items: center;
        //     justify-content: center;
        //     z-index: 1000;
        // `;

        // // Кнопка закрытия
        // const closeBtn = document.createElement('button');
        // closeBtn.textContent = '×';
        // closeBtn.style.cssText = `
        //     position: absolute;
        //     top: 20px;
        //     right: 30px;
        //     color: white;
        //     font-size: 40px;
        //     font-weight: bold;
        //     background: none;
        //     border: none;
        //     cursor: pointer;
        // `;
        // closeBtn.onclick = () => document.body.removeChild(modal);

        // // Контейнер для фотографий
        // const modalPhotosContainer = document.createElement('div');
        // modalPhotosContainer.style.cssText = `
        //     max-width: 90%;
        //     max-height: 80vh;
        //     overflow: auto;
        //     display: flex;
        //     flex-wrap: wrap;
        //     justify-content: center;
        //     gap: 15px;
        //     padding: 20px;
        // `;

        // // Добавляем каждую фотографию в контейнер
        // if (photos.length === 0) {
        //     const noPhotos = document.createElement('p');
        //     noPhotos.textContent = 'Фотографии не найдены';
        //     noPhotos.style.color = 'white';
        //     modalPhotosContainer.appendChild(noPhotos);
        // } else {
        //     photos.forEach(photo => {
        //         const imgContainer = document.createElement('div');
        //         imgContainer.style.cssText = 'margin: 10px; text-align: center;';
                
        //         const img = document.createElement('img');
        //         img.src = photo.url || `/storage/${photo.path}`;
        //         img.style.maxWidth = '100%';
        //         img.style.maxHeight = '300px';
        //         img.style.cursor = 'pointer';
        //         img.loading = 'lazy';
                
        //         // Добавляем возможность открыть фото в полном размере
        //         img.onclick = () => {
        //             const fullScreen = document.createElement('div');
        //             fullScreen.style.cssText = `
        //                 position: fixed;
        //                 top: 0;
        //                 left: 0;
        //                 width: 100%;
        //                 height: 100%;
        //                 background-color: rgba(0, 0, 0, 0.95);
        //                 display: flex;
        //                 justify-content: center;
        //                 align-items: center;
        //                 z-index: 1001;
        //             `;
                    
        //             const fullImg = document.createElement('img');
        //             fullImg.src = photo.url || `/storage/${photo.path}`;
        //             fullImg.style.maxWidth = '90%';
        //             fullImg.style.maxHeight = '90vh';
        //             fullImg.style.objectFit = 'contain';
                    
        //             fullScreen.onclick = () => document.body.removeChild(fullScreen);
        //             fullScreen.appendChild(fullImg);
        //             document.body.appendChild(fullScreen);
        //         };
                
        //         // Информация о фото
        //         const info = document.createElement('div');
        //         info.style.color = 'white';
        //         info.style.fontSize = '12px';
        //         info.style.marginTop = '5px';
        //         info.textContent = `${photo.original_name || 'Фото'} (${formatFileSize(photo.file_size)})`;
                
        //         imgContainer.appendChild(img);
        //         imgContainer.appendChild(info);
        //         modalPhotosContainer.appendChild(imgContainer);
        //     });
        // }

        // modal.appendChild(closeBtn);
        // modal.appendChild(modalPhotosContainer);
        // document.body.appendChild(modal);
        
    } catch (error) {
        console.error('Ошибка при загрузке фотографий:', error);
        
        // Показываем сообщение об ошибке
        if (photosContainer) {
            photosContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Не удалось загрузить фотографии: ${error.message}
                </div>`;
        }
    } finally {
        // Удаляем индикатор загрузки
        const loadingIndicator = document.querySelector('.loading-indicator');
        if (loadingIndicator) {
            document.body.removeChild(loadingIndicator);
        }
    }
}

// Вспомогательная функция для форматирования размера файла
function formatFileSize(bytes) {
    if (bytes === 0 || !bytes) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Экспортируем функции для использования в других модулях
export { 
    submitRequestForm, 
    displayEmployeeInfo, 
    updateRowNumbers, 
    addRequestToTable, 
    handleCommentEdit,
    initEmployeeButtons,
    loadCommentPhotos
};
