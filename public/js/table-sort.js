document.addEventListener('DOMContentLoaded', function () {
    // Хранилище направлений сортировки per тип
    const sortDirections = { number: false, address: true, organization: true, status: false };

    // Глобальные переменные для текущей сортировки
    window.currentSortType = null;
    window.currentSortAscending = true;

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

        // Устанавливаем текущий тип сортировки
        window.currentSortType = sortType;

        // Переключаем направление сортировки для этого типа
        sortDirections[sortType] = !sortDirections[sortType];
        const sortAscending = sortDirections[sortType];
        window.currentSortAscending = sortAscending;
        console.log('[table-sort] sort direction', { sortType, sortAscending });

        // Используем общую функцию сортировки
        sortTableWithDirection(sortType, sortAscending);
    }

    // Функция для применения текущей сортировки (для вызова извне)
    window.applyCurrentSort = function() {
        if (window.currentSortType) {
            console.log('[table-sort] applying current sort', { type: window.currentSortType, ascending: window.currentSortAscending });
            sortTableWithDirection(window.currentSortType, window.currentSortAscending);
        } else {
            console.log('[table-sort] no current sort type set');
        }
    };

    // Функция для сортировки с заданным направлением (без переключения)
    function sortTableWithDirection(sortType, sortAscending) {
        console.log('[table-sort] sortTableWithDirection called', { sortType, sortAscending });

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

        // Отладка: проверим атрибуты первых нескольких строк
        if (rows.length > 0) {
            console.log('[table-sort] sample row attributes:', {
                row1: {
                    id: rows[0].getAttribute('data-request-id'),
                    statusName: rows[0].dataset.statusName,
                    requestStatus: rows[0].dataset.requestStatus,
                    address: rows[0].getAttribute('data-address'),
                    number: rows[0].getAttribute('data-request-number')
                },
                row2: rows.length > 1 ? {
                    id: rows[1].getAttribute('data-request-id'),
                    statusName: rows[1].dataset.statusName,
                    requestStatus: rows[1].dataset.requestStatus,
                    address: rows[1].getAttribute('data-address'),
                    number: rows[1].getAttribute('data-request-number')
                } : null
            });
        }

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

                // Отладка значений статусов
                if (aVal === '' || bVal === '') {
                    console.log('[table-sort] status sort debug:', {
                        aId: a.getAttribute('data-request-id'),
                        aStatusName: a.dataset.statusName,
                        aRequestStatus: a.dataset.requestStatus,
                        aVal: aVal,
                        bId: b.getAttribute('data-request-id'),
                        bStatusName: b.dataset.statusName,
                        bRequestStatus: b.dataset.requestStatus,
                        bVal: bVal
                    });
                }

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

                // Отладка индексов
                if (aIndex === -1 || bIndex === -1) {
                    console.log('[table-sort] status sort index debug:', {
                        aVal: aVal, aIndex: aIndex,
                        bVal: bVal, bIndex: bIndex,
                        statusOrder: statusOrder
                    });
                }

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