document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('requestsTable');
    const tbody = table.querySelector('tbody');
    const brigadeHeader = document.getElementById('brigadeHeader');
    const sortIcon = document.getElementById('brigadeSortIcon');

    let sortAscending = true;

    brigadeHeader.addEventListener('click', () => {
        const rows = Array.from(tbody.querySelectorAll('tr'))
            .filter(row => !row.id.includes('no-requests-row'));

        rows.sort((a, b) => {
            const aText = a.querySelectorAll('td')[4].innerText.trim();
            const bText = b.querySelectorAll('td')[4].innerText.trim();

            const aAssigned = aText && !aText.includes('Не назначена');
            const bAssigned = bText && !bText.includes('Не назначена');

            if (aAssigned && bAssigned) {
                return sortAscending ? aText.localeCompare(bText) : bText.localeCompare(aText);
            }
            if (!aAssigned && bAssigned) return sortAscending ? 1 : -1;
            if (aAssigned && !bAssigned) return sortAscending ? -1 : 1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
        sortIcon.textContent = sortAscending ? '▼' : '▲';
        sortAscending = !sortAscending;
    });
});
