document.addEventListener('DOMContentLoaded', function() {
    // Initialize the brigades interface
    initBrigadesTab();

    // Add event listeners
    document.getElementById('createBrigadeBtn')?.addEventListener('click', showCreateBrigadeModal);
    document.getElementById('saveBrigadeBtn')?.addEventListener('click', saveBrigade);
});

function initBrigadesTab() {
    console.log('Initializing brigades tab...');
    // Load brigades list
    loadBrigades();
}

function loadBrigades() {
    console.log('Loading brigades...');
    // This will be implemented later to fetch brigades from the server
    const brigadesList = document.getElementById('brigadesList');
    if (brigadesList) {
        // For now, just show a message
        brigadesList.innerHTML = '<div class="alert alert-info">Список бригад будет загружен здесь</div>';
    }
}

function showCreateBrigadeModal() {
    console.log('Showing create brigade modal');
    const modal = new bootstrap.Modal(document.getElementById('brigadeModal'));
    modal.show();
}

function saveBrigade() {
    console.log('Saving brigade...');
    // This will be implemented later to save the brigade
    const brigadeName = document.getElementById('brigadeName')?.value;
    if (!brigadeName) {
        utils.showAlert('Пожалуйста, введите название бригады', 'danger');
        return;
    }
    
    console.log('New brigade:', brigadeName);
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('brigadeModal'));
    modal.hide();
    
    // Show success message
    utils.showAlert(`Бригада "${brigadeName}" будет создана`, 'success');
    
    // In the future, we'll refresh the brigades list here
    // loadBrigades();
}
