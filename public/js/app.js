// Initialize when document is ready
$(document).ready(function() {
    // Handle checkbox selection (only one can be selected)
    $('.request-checkbox').on('change', function() {
        const currentCheckbox = $(this);
        const currentRow = currentCheckbox.closest('tr');
        
        // Remove selection from all checkboxes
        $('.request-checkbox').not(this).prop('checked', false);
        $('tr').removeClass('row-selected');
        
        if (currentCheckbox.is(':checked')) {
            // Highlight the current row if checkbox is checked
            currentRow.addClass('row-selected');
        } else {
            // Reset styles for the current checkbox
            currentCheckbox.css({
                'background-color': 'transparent',
                'border-color': 'rgba(0, 0, 0, 0.25)'
            });
        }
    });
    
    // Initialize selected checkboxes on page load
    $('.request-checkbox:checked').each(function() {
        $(this).closest('tr').addClass('row-selected');
    });
    
    // Initialize datepicker
    $('#datepicker').datepicker({
        format: 'dd.mm.yyyy',
        language: 'ru',
        autoclose: true,
        todayHighlight: true,
        container: '#datepicker'
    });

    // Sync datepicker with input field
    $('#datepicker').on('changeDate', function(e) {
        const selectedDate = e.format('dd.mm.yyyy');
        console.log('Выбрана дата:', selectedDate);
        console.log('Объект события:', e);
        $('#dateInput').val(selectedDate);
        $('#selectedDate').text(selectedDate);
        
        // Очищаем данные карты при смене даты
        if (localStorage.getItem('requestsData')) {
            localStorage.removeItem('requestsData');
            console.log('Очищены данные заявок (localStorage)');
        }
        
        // Очищаем карту, если она существует
        if (window.yandexMap) {
            try {
                window.yandexMap.destroy();
                window.yandexMap = null;
                console.log('Карта Яндекс уничтожена');
            } catch (error) {
                console.error('Ошибка при уничтожении карты:', error);
            }
        }
        
        // Очищаем контейнер карты
        const mapContainer = document.getElementById('map-content');
        if (mapContainer) {
            // Очищаем содержимое и принудительно скрываем контейнер
            mapContainer.innerHTML = '<div id="map" style="width: 100%; height: 100%;"></div>';
            // Скрываем контейнер карты
            mapContainer.classList.add('hide-me');
            // Принудительно устанавливаем стили
            mapContainer.style.display = 'none';
            mapContainer.style.visibility = 'hidden';
            mapContainer.style.height = '0';
            mapContainer.style.padding = '0';
            
            // Отладочная информация
            console.log('Состояние контейнера карты после скрытия:', {
                classList: Array.from(mapContainer.classList),
                display: window.getComputedStyle(mapContainer).display,
                visibility: window.getComputedStyle(mapContainer).visibility,
                height: window.getComputedStyle(mapContainer).height
            });
            
            console.log('Контейнер карты очищен и скрыт');
        }

        console.log('END changeDate ----------------');
    });

    // Initialize input field datepicker
    $('#dateInput').datepicker({
        format: 'dd.mm.yyyy',
        language: 'ru',
        autoclose: true,
        todayHighlight: true,
        container: '#datepicker'
    });

    // Функция для загрузки данных заявок по дате
    async function loadRequestsByDate(selectedDate) {
        try {
            console.log('Обновление данных для даты:', selectedDate);
            
            // Проверяем, есть ли данные в localStorage
            const storedData = localStorage.getItem('requestsData');
            if (storedData) {
                try {
                    const requests = JSON.parse(storedData);
                    console.log('Данные заявок загружены из localStorage:', requests.length, 'записей');
                    
                    // Показываем контейнер карты, если он скрыт
                    const mapContainer = document.getElementById('map-content');
                    if (mapContainer) {
                        // Восстанавливаем стили контейнера
                        mapContainer.classList.remove('hide-me');
                        mapContainer.style.display = '';
                        mapContainer.style.visibility = '';
                        mapContainer.style.height = '800px';
                        mapContainer.style.padding = '1rem';
                        
                        // Если функция инициализации карты доступна, вызываем её
                        if (typeof initMapWithRequests === 'function') {
                            initMapWithRequests();
                        } 
                        // Если карта уже существует, но функция инициализации недоступна, обновляем её
                        else if (window.yandexMap) {
                            window.yandexMap.geoObjects.removeAll();
                            // Здесь можно добавить код для обновления маркеров на карте
                        }
                    }
                    
                    return requests;
                } catch (e) {
                    console.error('Ошибка при парсинге данных из localStorage:', e);
                    return [];
                }
            }
            
            // Если данных в localStorage нет, проверяем таблицу
            const tableRows = document.querySelectorAll('table.table-hover tbody tr[data-request-id]');
            if (tableRows.length > 0) {
                const requests = Array.from(tableRows).map(row => {
                    return {
                        id: row.getAttribute('data-request-id'),
                        city: row.querySelector('td:nth-child(2)')?.textContent.trim(),
                        street: row.querySelector('td:nth-child(3)')?.textContent.trim(),
                        houses: row.querySelector('td:nth-child(4)')?.textContent.trim(),
                        latitude: row.getAttribute('data-latitude'),
                        longitude: row.getAttribute('data-longitude'),
                        status: row.getAttribute('data-status')
                    };
                });
                
                // Сохраняем в localStorage
                localStorage.setItem('requestsData', JSON.stringify(requests));
                console.log('Данные заявок обновлены из таблицы и сохранены в localStorage:', requests.length, 'записей');
                
                // Если карта открыта, обновляем её
                const mapContainer = document.getElementById('map-content');
                if (mapContainer && !mapContainer.classList.contains('hide-me')) {
                    if (typeof initMapWithRequests === 'function') {
                        initMapWithRequests();
                    } else if (window.yandexMap) {
                        window.yandexMap.geoObjects.removeAll();
                    }
                }
                
                return requests;
            } else {
                console.log('Нет данных о заявках в таблице');
                return [];
            }
        } catch (error) {
            console.error('Ошибка при обновлении данных заявок:', error);
            return [];
        }
    }

    // Set today's date on load
    let today = new Date();
    let formattedDate = today.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    $('#datepicker').datepicker('update', today);
    $('#dateInput').val(formattedDate);
    $('#selectedDate').text(formattedDate);

    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const currentTheme = localStorage.getItem('theme');

    // Check for saved theme preference or use system preference
    if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        sunIcon.classList.remove('d-none'); // Show sun in dark mode
        moonIcon.classList.add('d-none');
    } else {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        sunIcon.classList.add('d-none');
        moonIcon.classList.remove('d-none'); // Show moon in light mode
    }

    // Toggle theme on icon click
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'light');
            localStorage.setItem('theme', 'light');
            sunIcon.classList.add('d-none');
            moonIcon.classList.remove('d-none'); // Show moon in light mode
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            sunIcon.classList.remove('d-none'); // Show sun in dark mode
            moonIcon.classList.add('d-none');
        }
    });
});
