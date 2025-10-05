<!DOCTYPE html>
<html>
<head>
    <title>Яндекс.Карты</title>
    <style>
        #myMap {
            width: 100%;
            height: 700px;
            margin: 0;
            padding: 0;
        }
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        button, select {
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button:hover, select:hover {
            background: #e9ecef;
        }
        button.danger {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        button.danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }
    </style>
</head>
<body>
    <h1>Яндекс.Карты</h1>
    
    <div class="controls">
        <select id="mapType">
            <option value="yandex#map">Схема</option>
            <option value="yandex#satellite">Спутник</option>
            <option value="yandex#hybrid">Гибрид</option>
            <option value="yandex#publicMap">Народная карта</option>
            <option value="yandex#publicMapHybrid">Народный гибрид</option>
        </select>
        <button id="destroyMap">Удалить карту</button>
        <button id="createMap" style="display: none;">Создать карту</button>
    </div>
    
    <!-- КОНТЕЙНЕР ДЛЯ КАРТЫ -->
    <div id="myMap"></div>

    <!-- Подключаем API Яндекс.Карт -->
    <script src="https://api-maps.yandex.ru/2.1/?apikey={{ config('services.yandex.maps_key') }}&lang=ru_RU&load=package.controls,package.geoObjects" type="text/javascript"></script>
    
    <script>
        let myMap;
        
        // Инициализация карты
        function init() {     
            myMap = new ymaps.Map('myMap', {
                center: [68.964033, 33.068066], // координаты центра
                zoom: 10, // уровень приближения
                type: 'yandex#map', // тип карты по умолчанию
                controls: ['zoomControl', 'typeSelector', 'rulerControl']
            });
            
            // Обработчик изменения типа карты
            document.getElementById('mapType').addEventListener('change', function() {
                if (myMap) {
                    myMap.setType(this.value);
                }
            });
            
            // Обработчик удаления карты
            document.getElementById('destroyMap').addEventListener('click', function() {
                if (myMap) {
                    myMap.destroy();
                    myMap = null;
                    document.getElementById('destroyMap').style.display = 'none';
                    document.getElementById('createMap').style.display = 'inline-block';
                }
            });
            
            // Обработчик создания карты
            document.getElementById('createMap').addEventListener('click', function() {
                if (!myMap) {
                    init();
                    document.getElementById('destroyMap').style.display = 'inline-block';
                    this.style.display = 'none';
                }
            });
        }
        
        // Запускаем инициализацию при загрузке API
        ymaps.ready(init);
    </script>
</body>
</html>

