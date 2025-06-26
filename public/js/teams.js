// teams.js

import { fetchData, postData, showAlert } from './utils.js';

/**
 * Загружает список сотрудников
 */
async function loadEmployees() {
    const availableEmployeesEl = document.getElementById('availableEmployees');
    const teamLeaderSelect = document.getElementById('teamLeader');

    if (!availableEmployeesEl || !teamLeaderSelect) return;

    teamLeaderSelect.innerHTML = '<option value="" selected disabled>Загрузка сотрудников...</option>';
    availableEmployeesEl.innerHTML = `
        <div class="text-center p-3 text-muted">
            <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Загрузка...</span></div>
            Загрузка списка сотрудников...
        </div>
    `;

    try {
        const employees = await fetchData('/api/employees');
        teamLeaderSelect.innerHTML = '<option value="" selected disabled>Выберите руководителя</option>';
        availableEmployeesEl.innerHTML = '';

        employees.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.id;
            option.textContent = employee.fio;
            teamLeaderSelect.appendChild(option);

            const item = document.createElement('div');
            item.className = 'list-group-item list-group-item-action employee-item';
            item.dataset.employeeId = employee.id;
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span>${employee.fio}</span>
                    <span class="badge bg-primary">Добавить</span>
                </div>
            `;
            availableEmployeesEl.appendChild(item);
        });

        initEmployeeSelection();
    } catch (error) {
        availableEmployeesEl.innerHTML = `<div class="alert alert-danger m-2">${error.message}</div>`;
    }
}

/**
 * Инициализирует выбор сотрудников
 */
function initEmployeeSelection() {
    const availableEmployees = document.querySelectorAll('#availableEmployees .employee-item');
    const selectedContainer = document.getElementById('selectedEmployees');

    availableEmployees.forEach(item => {
        item.addEventListener('click', () => {
            const id = item.dataset.employeeId;
            if (!document.querySelector(`#selectedEmployees [data-employee-id="${id}"]`)) {
                const selectedItem = document.createElement('div');
                selectedItem.className = 'list-group-item p-2 d-flex justify-content-between align-items-center';
                selectedItem.dataset.employeeId = id;
                selectedItem.innerHTML = `
                    <span>${item.querySelector('span').textContent}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-employee"><i class="bi bi-x-lg"></i></button>
                `;
                selectedContainer.appendChild(selectedItem);
                item.classList.add('selected');
                updateTeamMembersInput();
                updateTeamMembersCount();
            }
        });
    });

    selectedContainer.addEventListener('click', e => {
        if (e.target.closest('.remove-employee')) {
            const item = e.target.closest('[data-employee-id]');
            const id = item.dataset.employeeId;
            item.remove();
            const available = document.querySelector(`#availableEmployees [data-employee-id="${id}"]`);
            if (available) available.classList.remove('selected');
            updateTeamMembersInput();
            updateTeamMembersCount();
        }
    });
}

/**
 * Обновляет скрытое поле с ID выбранных сотрудников
 */
function updateTeamMembersInput() {
    const ids = Array.from(document.querySelectorAll('#selectedEmployees [data-employee-id]'))
        .map(el => el.dataset.employeeId).join(',');
    document.getElementById('teamMembersInput').value = ids;
    updateTeamMembersCount();
}

/**
 * Обновляет счетчик сотрудников
 */
function updateTeamMembersCount() {
    const count = document.querySelectorAll('#selectedEmployees > [data-employee-id]').length;
    const countElement = document.getElementById('teamMembersCount');
    if (countElement) countElement.textContent = count;
}

/**
 * Обработчик создания бригады
 */
async function handleCreateTeam() {
    const name = document.getElementById('teamName').value.trim();
    const leaderId = document.getElementById('teamLeader').value;
    const members = document.getElementById('teamMembersInput').value.split(',').filter(Boolean);

    if (!name || !leaderId) {
        showAlert('Пожалуйста, заполните все поля', 'warning');
        return;
    }

    try {
        const result = await postData('/api/teams', {
            name,
            leader_id: leaderId,
            members
        });

        if (result.success) {
            showAlert('Бригада успешно создана', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('createTeamModal'));
            modal.hide();
            location.reload();
        } else {
            throw new Error(result.message || 'Неизвестная ошибка');
        }
    } catch (error) {
        showAlert(error.message || 'Ошибка при создании бригады', 'danger');
    }
}

/**
 * Инициализация формы создания бригады
 */
function initTeamForm() {
    const createTeamBtn = document.getElementById('createTeamBtn');
    const modalEl = document.getElementById('createTeamModal');

    if (!modalEl || !createTeamBtn) return;

    modalEl.addEventListener('show.bs.modal', loadEmployees);
    createTeamBtn.addEventListener('click', handleCreateTeam);
}

export { initTeamForm };
