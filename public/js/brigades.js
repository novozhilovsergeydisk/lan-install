document.addEventListener('DOMContentLoaded', function() {
    // Initialize the brigades interface
    initBrigadesTab();

    // Add event listeners
    document.getElementById('createBrigadeBtn_')?.addEventListener('click', showCreateBrigadeModal);
    document.getElementById('saveBrigadeBtn_')?.addEventListener('click', saveBrigade);
});

function initBrigadesTab() {
    console.log('Initializing brigades tab...');
    // Load brigades list
    loadBrigades();
}

async function loadBrigades() {
    console.log('Loading brigades...');
    const brigadesList = document.getElementById('brigadesList');

    if (!brigadesList) {
        console.error('Brigades list container not found');
        return;
    }

    // Show loading state
    brigadesList.innerHTML = `
        <div class="text-center my-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-2">Загрузка списка бригад...</p>
            <div id="debugInfo" class="text-start small text-muted mt-3"></div>
        </div>`;

    const debugEl = document.getElementById('debugInfo');
    const addDebug = (msg) => {
        if (debugEl) {
            debugEl.innerHTML += `<div>${new Date().toISOString()}: ${msg}</div>`;
        }
        // console.debug(msg);
    };

    addDebug('Начало загрузки списка бригад...');

    try {
        const url = '/api/brigades';
        addDebug(`Отправка запроса на ${url}...`);

        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });

        addDebug(`Получен ответ: ${response.status} ${response.statusText}`);

        const responseText = await response.text();
        addDebug(`Получены данные: ${responseText}`);
        // addDebug(`Получены данные: ${responseText.substring(0, 200)}${responseText.length > 200 ? '...' : ''}`);

        let data;
        try {
            data = JSON.parse(responseText);
            console.log('Response data:', data);
        } catch (e) {
            console.error('Ошибка парсинга JSON:', e);
            addDebug(`Ошибка парсинга JSON: ${e.message}`);
            throw new Error('Неверный формат ответа от сервера');
        }

        if (data.success === false) {
            const errorMsg = data.message || 'Неизвестная ошибка';
            addDebug(`Ошибка API: ${errorMsg}`);
            throw new Error(errorMsg);
        }

        const brigades = Array.isArray(data.data) ? data.data : [];
        addDebug(`Получено бригад: ${brigades.length}`);

        if (brigades.length === 0) {
            brigadesList.innerHTML = `
                <div class="alert alert-info">
                    Нет доступных бригад. Создайте новую бригаду.
                    <div class="mt-2 small text-muted">Проверьте логи сервера для получения дополнительной информации</div>
                </div>`;
            return;
        }

        // Рендерим список бригад
        let html = '<div class="list-group">';

        brigades.forEach(brigade => {
            const leaderName = [
                brigade.leader_last_name,
                brigade.leader_first_name,
                brigade.leader_middle_name
            ].filter(Boolean).join(' ') || 'Не назначен';

            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">${brigade.name || 'Без названия'}</h5>
                            <p class="mb-1">
                                <span class="badge bg-primary me-2">Бригадир</span>
                                ${leaderName}
                                ${brigade.leader_position ? `<span class="text-muted ms-2">(${brigade.leader_position})</span>` : ''}
                            </p>
                        </div>
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="showBrigadeDetails(${brigade.id})">
                            Подробнее
                        </button>
                    </div>
                </div>`;
        });

        html += '</div>';
        brigadesList.innerHTML = html;

    } catch (error) {
        console.error('Ошибка при загрузке бригад:', error);
        const errorMessage = error.message || 'Неизвестная ошибка при загрузке списка бригад';

        brigadesList.innerHTML = `
            <div class="alert alert-danger">
                <p class="mb-2">${errorMessage}</p>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="loadBrigades()">
                    <i class="bi bi-arrow-clockwise"></i> Повторить попытку
                </button>
                <div id="debugInfo" class="small text-muted mt-2"></div>
            </div>`;
    }
}

// Функция для отображения модального окна с детальной информацией о бригаде
async function showBrigadeDetails(brigadeId) {
    console.log('Showing details for brigade ID:', brigadeId);

    const modalElement = document.getElementById('brigadeDetailsModal');
    const modal = new bootstrap.Modal(modalElement);

    // Показываем индикатор загрузки
    modalElement.querySelector('.modal-body').innerHTML = `
        <div class="text-center my-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-2">Загрузка данных о бригаде...</p>
        </div>`;

    modal.show();

    try {
        // Получаем данные о бригаде
        const response = await fetch(`/brigade/${brigadeId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`Ошибка HTTP: ${response.status}`);
        }

        const data = await response.json();
        console.log('Brigade details:', data);

        if (!data.success) {
            throw new Error(data.message || 'Не удалось загрузить данные о бригаде');
        }

        const { brigade, leader, members } = data;

        // Формируем HTML для отображения
        let html = `
            <div class="mb-4">
                <h4>${brigade.name || 'Без названия'}</h4>
                ${leader ? `
                    <div class="card mb-3">
                        <div class="card-header bg-light-2">
                            <h5 class="mb-0">Бригадир</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <strong>ФИО:</strong> ${[leader.last_name, leader.first_name, leader.middle_name].filter(Boolean).join(' ')}<br>
                                ${leader.position_name ? `<strong>Должность:</strong> ${leader.position_name}<br>` : ''}
                                ${leader.phone ? `<strong>Телефон:</strong> ${leader.phone}` : ''}
                            </p>
                        </div>
                    </div>
                ` : '<p class="text-muted">Бригадир не назначен</p>'}

                <div class="card">
                    <div class="card-header bg-light-2">
                        <h5 class="mb-0">Члены бригады</h5>
                    </div>
                    <div class="card-body">
                        ${members && members.length > 0 ? `
                            <div class="list-group">
                                ${members.map(member => `
                                    <div class="list-group-item ${member.is_leader ? 'list-group-item-primary' : ''}">
                                        ${[member.last_name, member.first_name, member.middle_name].filter(Boolean).join(' ')}
                                        ${member.position_name ? `<span class="text-muted ms-2">(${member.position_name})</span>` : ''}
                                        ${member.phone ? `<div class="small text-muted">${member.phone}</div>` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        ` : '<p class="text-muted mb-0">Нет членов бригады</p>'}
                    </div>
                </div>
            </div>`;

        // Вставляем сформированный HTML в модальное окно
        modalElement.querySelector('.modal-body').innerHTML = html;

    } catch (error) {
        console.error('Ошибка при загрузке данных о бригаде:', error);
        modalElement.querySelector('.modal-body').innerHTML = `
            <div class="alert alert-danger">
                <p class="mb-2">${error.message || 'Произошла ошибка при загрузке данных'}</p>
                <button class="btn btn-sm btn-outline-secondary" onclick="showBrigadeDetails(${brigadeId})">
                    <i class="bi bi-arrow-clockwise"></i> Повторить
                </button>
            </div>`;
    }
}

function showCreateBrigadeModal() {
    console.log('Showing create brigade modal (tab)');
    const modal = new bootstrap.Modal(document.getElementById('brigadeModal'));
    modal.show();
}

async function saveBrigade() {
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
