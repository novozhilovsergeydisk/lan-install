document.addEventListener('DOMContentLoaded', function () {
    // Хранилище направлений сортировки per тип
    const sortDirections = { number: false, address: true, organization: true };

    // Функция для сортировки строк
    function sortTable(sortType) {
        const table = document.getElementById('requestsPlanningTable');
        if (!table) {
            return;
        }

        const tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }

        const rows = Array.from(tbody.querySelectorAll('tr[data-request-id]'));

        // Переключаем направление сортировки для этого типа
        sortDirections[sortType] = !sortDirections[sortType];
        const sortAscending = sortDirections[sortType];

        rows.sort((a, b) => {
            let aVal, bVal;

            if (sortType === 'number') {
                // Сортировка по номеру заявки (из data-request-number)
                aVal = a.getAttribute('data-request-number') || '';
                bVal = b.getAttribute('data-request-number') || '';
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'address') {
                // Сортировка по адресу (из data-address)
                aVal = a.getAttribute('data-address') || '';
                bVal = b.getAttribute('data-address') || '';
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            } else if (sortType === 'organization') {
                // Сортировка по организации (из col-address__organization)
                aVal = a.querySelector('.col-address__organization')?.textContent.trim() || '';
                bVal = b.querySelector('.col-address__organization')?.textContent.trim() || '';
                return sortAscending ? aVal.localeCompare(bVal, 'ru') : bVal.localeCompare(aVal, 'ru');
            }

            return 0;
        });

        // Re-append rows in new order
        rows.forEach(row => tbody.appendChild(row));
    }

    // Обработчик для таблицы планирования
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('dropdown-item') && e.target.closest('#planningSortDropdown + .dropdown-menu')) {
            e.preventDefault();
            e.stopPropagation();
            const sortType = e.target.getAttribute('data-sort');
            sortTable(sortType);
        }
    });
});