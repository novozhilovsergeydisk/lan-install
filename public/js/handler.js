// Функция для логирования кликов
function logButtonClick(buttonId, buttonText) {
    console.log(`Клик по кнопке: ${buttonText} (ID: ${buttonId})`);
    // Здесь можно добавить дополнительную логику, например, показ уведомления
}

// Обработчики для кнопок
document.addEventListener('DOMContentLoaded', function() {
    // Кнопка выхода
    const logoutButton = document.getElementById('logout-button');
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            logButtonClick('logout-button', 'Выход');
            // e.preventDefault(); // Раскомментируйте, если нужно отменить стандартное действие
        });
    }

    // Вкладки навигации
    const navTabs = ['requests', 'teams', 'addresses', 'users', 'reports'];
    navTabs.forEach(tab => {
        const tabElement = document.getElementById(`${tab}-tab`);
        if (tabElement) {
            tabElement.addEventListener('click', function() {
                logButtonClick(`${tab}-tab`, `Вкладка ${tab}`);
            });
        }
    });

    // Кнопка сброса фильтров
    const resetFiltersButton = document.getElementById('reset-filters-button');
    if (resetFiltersButton) {
        resetFiltersButton.addEventListener('click', function() {
            logButtonClick('reset-filters-button', 'Сбросить фильтры');
            
            // Снимаем выделение со всех чекбоксов фильтров
            const filterCheckboxes = ['filter-statuses', 'filter-teams'];
            filterCheckboxes.forEach(checkboxId => {
                const checkbox = document.getElementById(checkboxId);
                if (checkbox && checkbox.checked) {
                    checkbox.checked = false;
                    // Имитируем событие change для обновления состояния
                    const event = new Event('change');
                    checkbox.dispatchEvent(event);
                }
            });
            
            console.log('Все фильтры сброшены');
        });
    }

    // Обработчики для чекбоксов фильтров
    const filterCheckboxes = ['filter-statuses', 'filter-teams'];
    filterCheckboxes.forEach(checkboxId => {
        const checkbox = document.getElementById(checkboxId);
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                const label = document.querySelector(`label[for="${checkboxId}"]`);
                const labelText = label ? label.textContent : 'Неизвестный фильтр';
                console.log(`Фильтр "${labelText}" ${this.checked ? 'включен' : 'отключен'}`);
                // Здесь будет логика применения фильтра
            });
        }
    });

    // Обработчик для выбора даты в календаре
    const datepicker = document.getElementById('datepicker');
    if (datepicker) {
        // Инициализация datepicker, если используется плагин
        if ($.fn.datepicker) {
            $('#datepicker').datepicker({
                format: 'dd.mm.yyyy',
                language: 'ru',
                autoclose: true,
                todayHighlight: true
            }).on('changeDate', function(e) {
                const selectedDate = e.format('dd.mm.yyyy');
                console.log(`Выбрана дата: ${selectedDate}`);
                // Здесь будет логика фильтрации по дате
            });
        } else {
            // Если плагин не загружен, используем стандартный input
            datepicker.addEventListener('change', function() {
                console.log(`Выбрана дата: ${this.value}`);
                // Здесь будет логика фильтрации по дате
            });
        }
    }
});
