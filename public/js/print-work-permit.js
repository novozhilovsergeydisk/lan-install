// Logic for "Print Work Permit" button
function updatePrintButtonVisibility() {
    const checkedCount = document.querySelectorAll('#requestsTable .request-checkbox:checked').length;
    const checkedCountPlanning = document.querySelectorAll('#requestsPlanningTable .request-checkbox:checked').length;
    
    const btnPrint = document.getElementById('btn-print-work-permit');
    const btnAssign = document.getElementById('btn-mass-assign-team');
    const btnChangeSubtype = document.getElementById('btn-mass-change-subtype');
    const btnChangeSubtypePlanning = document.getElementById('btn-mass-change-subtype-planning');

    if (btnPrint) {
        if (checkedCount > 0 || checkedCountPlanning > 0) {
            btnPrint.classList.remove('d-none');
        } else {
            btnPrint.classList.add('d-none');
        }
    }

    if (btnAssign) {
        if (checkedCount > 0 || checkedCountPlanning > 0) {
            btnAssign.classList.remove('d-none');
        } else {
            btnAssign.classList.add('d-none');
        }
    }

    if (btnChangeSubtype) {
        if (checkedCount > 0) {
            btnChangeSubtype.classList.remove('d-none');
        } else {
            btnChangeSubtype.classList.add('d-none');
        }
    }

    if (btnChangeSubtypePlanning) {
        if (checkedCountPlanning > 0) {
            btnChangeSubtypePlanning.classList.remove('d-none');
        } else {
            btnChangeSubtypePlanning.classList.add('d-none');
        }
    }
}

// Update visibility on checkbox change
document.addEventListener('change', function(event) {
    if (event.target.classList.contains('request-checkbox') || event.target.id === 'selectAllRequests' || event.target.id === 'selectAllRequestsPlanning') {
        
        // Handle "Select All" for Planning
        if (event.target.id === 'selectAllRequestsPlanning') {
            const isChecked = event.target.checked;
            document.querySelectorAll('#requestsPlanningTable .request-checkbox').forEach(cb => {
                cb.checked = isChecked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
        
        // Handle "Select All" for Main Table (if not handled elsewhere)
        if (event.target.id === 'selectAllRequests') {
            const isChecked = event.target.checked;
            document.querySelectorAll('#requestsTable .request-checkbox').forEach(cb => {
                cb.checked = isChecked;
                // No need to dispatch change recursively if we handle it here, but let's be safe
            });
        }

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

    // Handle mass assign button click
    const btnAssign = event.target.closest('#btn-mass-assign-team');
    if (btnAssign) {
        const selectedIds = Array.from(document.querySelectorAll('.request-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) {
            showAlert('Выберите заявки для назначения бригады', 'warning');
            return;
        }

        // We can reuse the existing assign team modal
        const assignTeamModal = document.getElementById('assign-team-modal');
        if (assignTeamModal) {
            // Save array of IDs as JSON string in dataset
            assignTeamModal.dataset.requestIds = JSON.stringify(selectedIds);
            // Clear single requestId if it was set
            assignTeamModal.dataset.requestId = ''; 
            
            // Note: showModal and loadTeamsToSelect should be globally available or imported if needed.
            // Assuming they are available via global scope (like window.showModal) based on existing code structure
            if (typeof window.showModal === 'function') {
                window.showModal('assign-team-modal');
            } else if (typeof bootstrap !== 'undefined') {
                 const modal = new bootstrap.Modal(assignTeamModal);
                 modal.show();
            }
            
            // Call loadTeamsToSelect to populate the modal select
            if (typeof window.loadTeamsToSelect === 'function') {
                window.loadTeamsToSelect();
            } else {
                 console.warn('loadTeamsToSelect is not defined globally. Modal might be empty.');
            }
        }
    }
});

window.updatePrintButtonVisibility = updatePrintButtonVisibility;