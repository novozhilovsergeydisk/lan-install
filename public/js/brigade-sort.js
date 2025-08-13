document.addEventListener('DOMContentLoaded', function () {
    const brigadeHeader = document.getElementById('brigadeHeader');
    if (!brigadeHeader) return;
    
    const sortIcon = document.getElementById('brigadeSortIcon');
    const tbody = document.querySelector('#requestsTable tbody');
    if (!tbody) return;

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
        // Only select rows that have a valid request ID (starts with 'request-')
        const rows = Array.from(tbody.querySelectorAll('tr[id^="request-"]'));
        
        rows.sort((a, b) => {
            const aName = getBrigadeName(a.querySelector('td.col-brigade'));
            const bName = getBrigadeName(b.querySelector('td.col-brigade'));
            
            if (!aName && !bName) return 0;
            if (!aName) return sortAscending ? 1 : -1;
            if (!bName) return sortAscending ? -1 : 1;
            
            return sortAscending 
                ? aName.localeCompare(bName, 'ru')
                : bName.localeCompare(aName, 'ru');
            
            // Keep original order if comparison is equal
            return 0;
        });

        // Re-append rows in new order
        rows.forEach(row => tbody.appendChild(row));
        
        // Update sort icon
        if (sortIcon) {
            sortIcon.textContent = sortAscending ? '▼' : '▲';
            sortIcon.setAttribute('title', sortAscending ? 'Сортировка по убыванию' : 'Сортировка по возрастанию');
        }
        
        // Toggle sort direction for next click
        sortAscending = !sortAscending;
    });
});
