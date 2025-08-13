document.addEventListener('DOMContentLoaded', function () {
    const brigadeHeader = document.getElementById('brigadeHeader');
    if (!brigadeHeader) return;
    
    const sortIcon = document.getElementById('brigadeSortIcon');
    const tbody = document.querySelector('#requestsTable tbody');
    if (!tbody) return;

    let sortAscending = true;

    brigadeHeader.addEventListener('click', () => {
        const rows = Array.from(tbody.querySelectorAll('tr:not(#no-requests-row)'));
        
        rows.sort((a, b) => {
            const aCell = a.querySelector('td.col-brigade');
            const bCell = b.querySelector('td.col-brigade');
            
            const aText = aCell ? aCell.textContent.trim() : '';
            const bText = bCell ? bCell.textContent.trim() : '';

            const aAssigned = aText && !aText.includes('Не назначена');
            const bAssigned = bText && !bText.includes('Не назначена');

            if (aAssigned && bAssigned) {
                return sortAscending ? aText.localeCompare(bText) : bText.localeCompare(aText);
            }
            if (!aAssigned && bAssigned) return sortAscending ? 1 : -1;
            if (aAssigned && !bAssigned) return sortAscending ? -1 : 1;
            return 0;
        });

        // Re-append rows in new order
        rows.forEach(row => tbody.appendChild(row));
        
        // Update sort icon
        if (sortIcon) {
            sortIcon.textContent = sortAscending ? '▼' : '▲';
        }
        
        sortAscending = !sortAscending;
    });
});
