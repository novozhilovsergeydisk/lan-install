// Initialize when document is ready
$(document).ready(function() {
    // Handle checkbox selection (multiple selection allowed)
    $(document).on('change', '.request-checkbox', function() {
        const currentCheckbox = $(this);
        const currentRow = currentCheckbox.closest('tr');
        
        if (currentCheckbox.is(':checked')) {
            // Highlight the current row if checkbox is checked
            currentRow.addClass('row-selected');
        } else {
            // Remove highlight and reset styles
            currentRow.removeClass('row-selected');
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

    // Cache for request counts
    let requestCounts = {};

    // Function to load request counts
    function loadRequestCounts(year, month) {
        $.ajax({
            url: '/api/requests/counts',
            data: { year: year, month: month },
            success: function(response) {
                if (response.success) {
                    // Merge new data into cache
                    Object.assign(requestCounts, response.data);
                    
                    // Redraw datepicker to apply new data without resetting the view
                    const datepicker = $('#datepicker').data('datepicker');
                    if (datepicker) {
                        datepicker.fill();
                    }
                }
            },
            error: function(err) {
                console.error('Failed to load request counts', err);
            }
        });
    }

    // Datepicker options
    const datepickerOptions = {
        format: 'dd.mm.yyyy',
        language: 'ru',
        autoclose: true,
        todayHighlight: true,
        container: '#datepicker',
        beforeShowDay: function(date) {
            // Format date as YYYY-MM-DD
            let year = date.getFullYear();
            let month = (date.getMonth() + 1).toString().padStart(2, '0');
            let day = date.getDate().toString().padStart(2, '0');
            let dateString = `${year}-${month}-${day}`;

            if (requestCounts[dateString] !== undefined && requestCounts[dateString] > 0) {
                return {
                    tooltip: `Заявок: ${requestCounts[dateString]}`,
                    classes: 'has-requests',
                    title: `Заявок: ${requestCounts[dateString]}`
                };
            }
            return {};
        }
    };

    // Function to initialize datepicker
    function initDatepicker() {
        const $datepicker = $('#datepicker');
        const currentDate = $datepicker.datepicker('getDate') || new Date();
        
        // Destroy existing instance if any
        if ($datepicker.data('datepicker')) {
            $datepicker.datepicker('destroy');
        }

        // Initialize
        $datepicker.datepicker(datepickerOptions);
        
        // Restore date
        $datepicker.datepicker('setDate', currentDate);

        // Restore event listeners
        $datepicker.on('changeMonth', function(e) {
            let date = e.date;
            if (date) {
                 loadRequestCounts(date.getFullYear(), date.getMonth() + 1);
            }
        });
        
        // Restore changeDate listener for input sync
        $datepicker.on('changeDate', function(e) {
            const selectedDate = e.format('dd.mm.yyyy');
            $('#dateInput').val(selectedDate);
            $('#selectedDate').text(selectedDate);
            
            // Clear map data logic...
             if (localStorage.getItem('requestsData')) {
                localStorage.removeItem('requestsData');
            }
            
            if (window.yandexMap) {
                try {
                    window.yandexMap.destroy();
                    window.yandexMap = null;
                } catch (error) {
                    console.error('Ошибка при уничтожении карты:', error);
                }
            }
            
            const mapContainer = document.getElementById('map-content');
            if (mapContainer) {
                mapContainer.innerHTML = '<div id="map" style="width: 100%; height: 100%;"></div>';
                mapContainer.classList.add('hide-me');
                mapContainer.style.display = 'none';
                mapContainer.style.visibility = 'hidden';
                mapContainer.style.height = '0';
                mapContainer.style.padding = '0';
            }
        });
    }
    
    // Initial initialization
    initDatepicker();

    // Initial load of request counts
    let initialDateForCounts = new Date();
    loadRequestCounts(initialDateForCounts.getFullYear(), initialDateForCounts.getMonth() + 1);

    // Show request count in info block on hover
    $(document).on('mouseenter', '#datepicker .day', function() {
        const timestamp = $(this).data('date');
        if (!timestamp) return;
        
        const date = new Date(timestamp);
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const dateString = `${year}-${month}-${day}`;
        
        const count = requestCounts[dateString];
        const formattedDate = date.toLocaleDateString('ru-RU');
        
        if (count && count > 0) {
            $('#calendar-status').text(`${formattedDate}: заявок - ${count}`);
        } else {
            $('#calendar-status').text(`${formattedDate}: нет заявок`);
        }
    });

    // Clear info block on mouseleave
    $(document).on('mouseleave', '#datepicker .day', function() {
        $('#calendar-status').text('');
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
    
    // Mobile theme elements
    const mobileThemeToggle = document.getElementById('mobileThemeToggle');
    const mobileSunIcon = document.getElementById('mobileSunIcon');
    const mobileMoonIcon = document.getElementById('mobileMoonIcon');
    
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const currentTheme = localStorage.getItem('theme');

    // Function to update UI based on theme
    function updateThemeUI(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if (sunIcon) sunIcon.classList.remove('d-none');
            if (moonIcon) moonIcon.classList.add('d-none');
            
            if (mobileSunIcon) mobileSunIcon.classList.remove('d-none');
            if (mobileMoonIcon) mobileMoonIcon.classList.add('d-none');
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'light');
            if (sunIcon) sunIcon.classList.add('d-none');
            if (moonIcon) sunIcon.classList.remove('d-none'); // Bug fix: previously moonIcon logic might be inverted in original code or logic
            if (moonIcon) moonIcon.classList.remove('d-none');
            
            if (mobileSunIcon) mobileSunIcon.classList.add('d-none');
            if (mobileMoonIcon) mobileMoonIcon.classList.remove('d-none');
        }
    }

    // Check for saved theme preference or use system preference
    if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
        updateThemeUI('dark');
    } else {
        updateThemeUI('light');
    }

    // Toggle theme function
    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
    }

    // Add event listeners
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    if (mobileThemeToggle) {
        mobileThemeToggle.addEventListener('click', toggleTheme);
    }
});
