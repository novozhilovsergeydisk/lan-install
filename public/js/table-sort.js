document.addEventListener('DOMContentLoaded', function () {
    // Хранилище направлений сортировки per тип
    const sortDirections = { number: false, address: true, organization: true, status: false };

    // Функция для сортировки строк
    function sortTable(sortType) {
        console.log('[table-sort] sortTable called', { sortType });

        const table = document.getElementById('requestsTable');
        if (!table) {
            console.error('[table-sort] table not found');
            return;
        }

        const tbody = table.tBodies[0];
        if (!tbody) {
            console.error('[table-sort] tbody not found');
            return;
        }

        const rows = Array.from(tbody.querySelectorAll('tr[data-request-id]'));
        console.log('[table-sort] rows found', rows.length);


        // Переключаем направление сортировки для этого типа
        sortDirections[sortType] = !sortDirections[sortType];
        const sortAscending = sortDirections[sortType];
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
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'status') {
                // Сортировка по статусу (из dataset или id) с кастомным порядком
                const statusOrder = ['выполнена', 'новая', 'отменена', 'перенесена', 'планирование', 'удалена'];
                let aVal = a.dataset.statusName || '';
                let bVal = b.dataset.statusName || '';
                // Если statusName пустой, используем id для маппинга
                if (!aVal) {
                    const statusIdToName = { '1': 'новая', '3': 'перенесена', '4': 'выполнена', '5': 'отменена', '6': 'планирование', '7': 'удалена' };
                    aVal = statusIdToName[a.dataset.requestStatus] || '';
                }
                if (!bVal) {
                    const statusIdToName = { '1': 'новая', '3': 'перенесена', '4': 'выполнена', '5': 'отменена', '6': 'планирование', '7': 'удалена' };
                    bVal = statusIdToName[b.dataset.requestStatus] || '';
                }
                const aIndex = statusOrder.indexOf(aVal);
                const bIndex = statusOrder.indexOf(bVal);
                return sortAscending ? aIndex - bIndex : bIndex - aIndex;
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
            sortTable(sortType);
        }
    });


});