document.addEventListener('DOMContentLoaded', function () {
    // Хранилище направлений сортировки per таблица per тип
    const sortDirections = {
        requestsTable: { number: false, address: true, organization: true },
        requestsPlanningTable: { number: false, address: true, organization: true }
    };

    // Функция для сортировки строк
    function sortTable(tableId, sortType) {
        console.log('[table-sort] sortTable called', { tableId, sortType });

        const table = document.getElementById(tableId);
        if (!table) {
            console.error('[table-sort] table not found', tableId);
            return;
        }

        const tbody = table.tBodies[0];
        if (!tbody) {
            console.error('[table-sort] tbody not found', tableId);
            return;
        }

        const rows = Array.from(tbody.querySelectorAll('tr[data-request-id]'));
        console.log('[table-sort] rows found', rows.length);

        // Переключаем направление сортировки для этого типа
        sortDirections[tableId][sortType] = !sortDirections[tableId][sortType];
        const sortAscending = sortDirections[tableId][sortType];
        console.log('[table-sort] sort direction', { sortType, sortAscending });

        rows.sort((a, b) => {
            let aVal, bVal;

            if (sortType === 'number') {
                // Сортировка по номеру заявки (из data-request-number)
                aVal = a.getAttribute('data-request-number') || '';
                bVal = b.getAttribute('data-request-number') || '';
                console.log('[table-sort] number values', { aVal, bVal });
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'address') {
                // Сортировка по адресу (из data-address)
                aVal = a.getAttribute('data-address') || '';
                bVal = b.getAttribute('data-address') || '';
                console.log('[table-sort] address values', { aVal, bVal });
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'organization') {
                // Сортировка по организации (из col-address__organization)
                aVal = a.querySelector('.col-address__organization')?.textContent.trim() || '';
                bVal = b.querySelector('.col-address__organization')?.textContent.trim() || '';
                console.log('[table-sort] organization values', { aVal, bVal });
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            }

            return 0;
        });

        console.log('[table-sort] rows sorted, re-appending');
        // Re-append rows in new order
        rows.forEach(row => tbody.appendChild(row));
        console.log('[table-sort] sorting completed');
    }

    // Обработчик для основной таблицы
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item') && e.target.closest('#sortDropdown + .dropdown-menu')) {
            e.preventDefault();
            e.stopPropagation();
            const sortType = e.target.getAttribute('data-sort');
            console.log('[table-sort] dropdown item clicked', sortType);
            sortTable('requestsTable', sortType);
        }
    });

    // Обработчик для таблицы планирования
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item') && e.target.closest('#planningSortDropdown + .dropdown-menu')) {
            e.preventDefault();
            e.stopPropagation();
            const sortType = e.target.getAttribute('data-sort');
            console.log('[table-sort] planning dropdown item clicked', sortType);
            sortTable('requestsPlanningTable', sortType);
        }
    });
});