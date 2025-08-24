document.addEventListener('DOMContentLoaded', function () {
    const brigadeHeader = document.getElementById('brigadeHeader');
    if (!brigadeHeader) return;
    
    const sortIcon = document.getElementById('brigadeSortIcon');
    const table = document.getElementById('requestsTable');
    if (!table) return;
    
    // Debug: init (считаем актуальные строки на момент загрузки)
    const initTbody = table.tBodies[0];
    console.log('[brigade-sort] init', {
        rows: initTbody ? initTbody.querySelectorAll('tr[data-request-id]').length : 0
    });

    let sortAscending = true;

    // Function to extract brigade name from cell
    function getBrigadeName(cell) {
        if (!cell) return '';
        const div = cell.querySelector('.col-brigade__div');
        if (!div) return '';
        const nameEl = div.querySelector('div > i');
        return nameEl ? nameEl.textContent.trim() : '';
    }

    brigadeHeader.style.cursor = 'pointer';
    brigadeHeader.title = 'Нажмите для сортировки по бригаде';

    brigadeHeader.addEventListener('click', () => {
        // На каждый клик заново берём актуальный tbody и строки
        const tbody = table.tBodies[0];
        if (!tbody) {
            console.warn('[brigade-sort] tbody not found at click');
            return;
        }
        const rows = Array.from(tbody.querySelectorAll('tr[data-request-id]'));
        console.debug('[brigade-sort] click', { direction: sortAscending ? 'asc' : 'desc', rows: rows.length });
        
        rows.sort((a, b) => {
            const aName = getBrigadeName(a.querySelector('td.col-brigade'));
            const bName = getBrigadeName(b.querySelector('td.col-brigade'));
            
            if (!aName && !bName) return 0;
            if (!aName) return sortAscending ? 1 : -1;
            if (!bName) return sortAscending ? -1 : 1;
            
            return sortAscending 
                ? aName.localeCompare(bName, 'ru')
                : bName.localeCompare(aName, 'ru');
        });

        // Re-append rows in new order
        rows.forEach(row => tbody.appendChild(row));
        console.debug('[brigade-sort] sorted', { rows: rows.length });
        
        // Update sort icon
        if (sortIcon) {
            sortIcon.textContent = sortAscending ? '▼' : '▲';
            sortIcon.setAttribute('title', sortAscending ? 'Сортировка по убыванию' : 'Сортировка по возрастанию');
        }
        
        // Toggle sort direction for next click
        sortAscending = !sortAscending;
        console.debug('[brigade-sort] next direction', { next: sortAscending ? 'asc' : 'desc' });
    });
});
