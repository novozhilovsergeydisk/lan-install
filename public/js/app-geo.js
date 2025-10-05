// Глобальные переменные
let map, searchControl, selectedPlacemark, suggestView;

// Инициализация карты при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, что API Яндекс.Карт загружено
    if (typeof ymaps === 'undefined') {
        console.error('Yandex Maps API не загружено');
        return;
    }

    // Инициализируем карту при готовности API
    ymaps.ready(init);
});

// Основная функция инициализации
function init() {
    // Создаем карту
    map = new ymaps.Map('map', {
        center: [55.76, 37.64], // Москва по умолчанию
        zoom: 10,
        controls: ['zoomControl', 'typeSelector', 'fullscreenControl']
    });

    // Создаем панель поиска
    createSearchControl();
    
    // Добавляем обработчики событий
    setupEventListeners();
    
    // Инициализируем поиск при клике на кнопку
    const searchButton = document.getElementById('search-button');
    const searchInput = document.getElementById('address-search');
    
    if (searchButton && searchInput) {
        searchButton.addEventListener('click', function() {
            const query = searchInput.value.trim();
            if (query) {
                searchAddress(query);
            }
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = searchInput.value.trim();
                if (query) {
                    searchAddress(query);
                }
            }
        });
    }
}

// Создаем элемент управления поиском
function createSearchControl() {
    const searchControl = new ymaps.control.SearchControl({
        options: {
            provider: 'yandex#search',
            noPlacemark: true,
            float: 'none',
            noPopup: true,
            placeholderContent: 'Поиск адреса',
            size: 'large'
        }
    });

    // Добавляем поиск на карту
    map.controls.add(searchControl);

    // Обработка выбора результата поиска
    searchControl.events.add('resultselect', function(e) {
        const index = e.get('index');
        searchControl.getResult(index).then(showSelectedAddress);
    });
}

// Настройка обработчиков событий
function setupEventListeners() {
    // Обработка клика по карте
    map.events.add('click', function(e) {
        const coords = e.get('coords');
        
        // Если метка уже есть, перемещаем её
        if (selectedPlacemark) {
            selectedPlacemark.geometry.setCoordinates(coords);
        } else {
            // Иначе создаем новую метку
            selectedPlacemark = createPlacemark(coords);
            map.geoObjects.add(selectedPlacemark);
        }
        
        // Получаем адрес по координатам
        getAddress(coords);
    });
}

// Создание метки
function createPlacemark(coords) {
    const placemark = new ymaps.Placemark(coords, {
        hintContent: 'Перетащите метку',
        balloonContent: 'Координаты: ' + coords
    }, {
        preset: 'islands#redDotIcon',
        draggable: true
    });
    
    // Обработка перетаскивания метки
    placemark.events.add('dragend', function() {
        getAddress(placemark.geometry.getCoordinates());
    });
    
    return placemark;
}

// Получение адреса по координатам
function getAddress(coords) {
    // Удаляем предыдущую метку, если есть
    if (selectedPlacemark) {
        selectedPlacemark.properties.set('balloonContent', 'Ищем адрес...');
    }
    
    // Используем геокодер для получения адреса
    ymaps.geocode(coords).then(function(res) {
        const firstGeoObject = res.geoObjects.get(0);
        
        if (firstGeoObject) {
            const address = firstGeoObject.getAddressLine();
            updateAddressInfo(address, coords);
            
            // Обновляем подпись метки
            if (selectedPlacemark) {
                selectedPlacemark.properties
                    .set({
                        iconCaption: address,
                        balloonContent: address
                    });
            }
        }
    });
}

// Обновление информации об адресе
function updateAddressInfo(address, coords) {
    const addressElement = document.getElementById('selected-address');
    const coordsElement = document.getElementById('coordinates');
    const infoElement = document.getElementById('address-info');
    
    if (addressElement) addressElement.textContent = address || 'Адрес не определен';
    if (coordsElement) coordsElement.textContent = `Широта: ${coords[0].toFixed(6)}, Долгота: ${coords[1].toFixed(6)}`;
    if (infoElement) infoElement.style.display = 'block';
}

// Поиск адреса по строке запроса
function searchAddress(query) {
    if (!query) return;
    
    // Используем геокодер для поиска по адресу
    ymaps.geocode(query, {
        results: 1
    }).then(function(res) {
        const firstGeoObject = res.geoObjects.get(0);
        
        if (firstGeoObject) {
            const coords = firstGeoObject.geometry.getCoordinates();
            const address = firstGeoObject.getAddressLine();
            
            // Удаляем предыдущую метку, если есть
            if (selectedPlacemark) {
                map.geoObjects.remove(selectedPlacemark);
            }
            
            // Создаем новую метку
            selectedPlacemark = createPlacemark(coords);
            map.geoObjects.add(selectedPlacemark);
            
            // Обновляем информацию об адресе
            updateAddressInfo(address, coords);
            
            // Центрируем карту
            map.setCenter(coords, 17);
        } else {
            alert('Адрес не найден');
        }
    }).catch(function(error) {
        console.error('Ошибка при поиске адреса:', error);
        alert('Произошла ошибка при поиске адреса');
    });
}
