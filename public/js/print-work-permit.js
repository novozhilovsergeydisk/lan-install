// Logic for "Print Work Permit" button
function updatePrintButtonVisibility() {
    const checkedCount = document.querySelectorAll('.request-checkbox:checked').length;
    const btn = document.getElementById('btn-print-work-permit');
    if (btn) {
        if (checkedCount > 0) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    }
}

// Update visibility on checkbox change
document.addEventListener('change', function(event) {
    if (event.target.classList.contains('request-checkbox') || event.target.id === 'selectAllRequests') {
        setTimeout(updatePrintButtonVisibility, 0); 
    }
});

// Handle print button click using a hidden iframe
document.addEventListener('click', function(event) {
    const btn = event.target.closest('#btn-print-work-permit');
    if (btn) {
        const selectedIds = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            alert('Выберите заявки для печати');
            return;
        }
        
        const url = `/reports/work-permit?ids=${selectedIds.join(',')}`;
        
        // Create or reuse hidden iframe
        let printFrame = document.getElementById('print-iframe');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'print-iframe';
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            document.body.appendChild(printFrame);
        }
        
        printFrame.src = url;
        
        // Note: The print dialog will be triggered by the script inside the iframe (in work-permit.blade.php)
    }
});