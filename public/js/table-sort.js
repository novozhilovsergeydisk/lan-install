document.addEventListener('DOMContentLoaded', function () {
    // Функция для сортировки строк
    function sortTable(tableId, sortType, ascending) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const tbody = table.tBodies[0];
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr[data-request-id]'));

        rows.sort((a, b) => {
            let aVal, bVal;

            if (sortType === 'number') {
                // Сортировка по номеру заявки (из data-request-number)
                aVal = a.getAttribute('data-request-number') || '';
                bVal = b.getAttribute('data-request-number') || '';
                return ascending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'address') {
                // Сортировка по адресу (из data-address)
                aVal = a.getAttribute('data-address') || '';
                bVal = b.getAttribute('data-address') || '';
                return ascending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'organization') {
                // Сортировка по организации (из col-address__organization)
                aVal = a.querySelector('.col-address__organization')?.textContent.trim() || '';
                bVal = b.querySelector('.col-address__organization')?.textContent.trim() || '';
                return ascending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            }

            return 0;
        });

        // Re-append rows in new order
        rows.forEach(row => tbody.appendChild(row));
    }

    // Обработчик для основной таблицы
    document.getElementById('sortDropdown')?.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            e.preventDefault();
            const sortType = e.target.getAttribute('data-sort');
            sortTable('requestsTable', sortType, true); // По умолчанию ascending
        }
    });

    // Обработчик для таблицы планирования
    document.getElementById('planningSortDropdown')?.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            e.preventDefault();
            const sortType = e.target.getAttribute('data-sort');
            sortTable('requestsPlanningTable', sortType, true); // По умолчанию ascending
        }
    });
});