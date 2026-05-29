export function initPlanningTypesHandlers() {
    const planningTypesModal = document.getElementById('planningTypesModal');
    const addForm = document.getElementById('addPlanningTypeForm');
    const typesList = document.getElementById('planningTypesList');

    if (!planningTypesModal) return;

    planningTypesModal.addEventListener('show.bs.modal', loadPlanningTypes);

    if (addForm) {
        addForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const input = document.getElementById('newPlanningTypeName');
            const name = input.value.trim();
            if (!name) return;

            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;

            try {
                const response = await fetch('/api/planning-types', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ name })
                });

                if (response.ok) {
                    input.value = '';
                    await loadPlanningTypes();
                    await updatePlanningSelects();
                } else {
                    alert('Ошибка при добавлении типа');
                }
            } catch (err) {
                console.error(err);
                alert('Ошибка сети при добавлении типа');
            } finally {
                btn.disabled = false;
            }
        });
    }

    async function loadPlanningTypes() {
        if (typesList) {
            typesList.innerHTML = '<div class="text-center my-3"><div class="spinner-border text-primary" role="status"></div></div>';
        }

        try {
            const response = await fetch('/api/planning-types');
            if (response.ok) {
                const types = await response.json();
                renderPlanningTypes(types);
            } else {
                if (typesList) typesList.innerHTML = '<div class="alert alert-danger">Ошибка загрузки</div>';
            }
        } catch (err) {
            console.error(err);
            if (typesList) typesList.innerHTML = '<div class="alert alert-danger">Ошибка сети</div>';
        }
    }

    function renderPlanningTypes(types) {
        if (!typesList) return;
        typesList.innerHTML = '';

        if (types.length === 0) {
            typesList.innerHTML = '<div class="text-center text-muted my-2">Нет типов планирования</div>';
            return;
        }

        types.forEach(type => {
            const div = document.createElement('div');
            div.className = 'list-group-item d-flex justify-content-between align-items-center';
            div.innerHTML = `
                <span class="type-name" data-id="${type.id}">${type.name} <span class="badge bg-secondary rounded-pill ms-2">${type.requests_count || 0}</span></span>
                <div>
                    <button class="btn btn-sm btn-outline-primary me-1 btn-edit-type" data-id="${type.id}" data-name="${type.name}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger btn-delete-type" data-id="${type.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            typesList.appendChild(div);
        });

        // Обработчики редактирования
        typesList.querySelectorAll('.btn-edit-type').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.getAttribute('data-id');
                const oldName = this.getAttribute('data-name');
                const newName = prompt('Введите новое название', oldName);
                
                if (newName !== null && newName.trim() !== '' && newName !== oldName) {
                    try {
                        const response = await fetch(`/api/planning-types/${id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ name: newName.trim() })
                        });
                        
                        if (response.ok) {
                            await loadPlanningTypes();
                            await updatePlanningSelects();
                        } else {
                            alert('Ошибка при обновлении');
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Ошибка сети при обновлении');
                    }
                }
            });
        });

        // Обработчики удаления
        typesList.querySelectorAll('.btn-delete-type').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (confirm('Вы уверены, что хотите удалить этот тип?')) {
                    const id = this.getAttribute('data-id');
                    try {
                        const response = await fetch(`/api/planning-types/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        
                        if (response.ok) {
                            await loadPlanningTypes();
                            await updatePlanningSelects();
                        } else {
                            alert('Ошибка при удалении');
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Ошибка сети при удалении');
                    }
                }
            });
        });
    }

    // Обработчик открытия модального окна смены типа для заявок (массово или одиночно)
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.change-planning-subtype-btn');
        const massBtn = e.target.closest('#btn-mass-change-subtype');
        const massBtnPlanning = e.target.closest('#btn-mass-change-subtype-planning');
        
        if (btn || massBtn || massBtnPlanning) {
            let requestIds = [];
            let requestNumberText = '';

            if (massBtn || massBtnPlanning) {
                const tableId = massBtn ? 'requestsTable' : 'requestsPlanningTable';
                const selectedCheckboxes = document.querySelectorAll(`#${tableId} .request-checkbox:checked`);
                requestIds = Array.from(selectedCheckboxes).map(cb => cb.value);
                if (requestIds.length === 0) return;
                requestNumberText = `Выбрано заявок: ${requestIds.length}`;
            } else {
                requestIds = [btn.getAttribute('data-request-id')];
                requestNumberText = btn.getAttribute('data-request-number');
            }
            
            document.getElementById('changeSubtypeRequestId').value = JSON.stringify(requestIds);
            document.getElementById('changeSubtypeRequestNumber').textContent = requestNumberText;
            
            // Заполняем селект текущими типами
            const select = document.getElementById('newPlanningSubtypeSelect');
            if (select) {
                try {
                    const response = await fetch('/api/planning-types');
                    if (response.ok) {
                        const types = await response.json();
                        select.innerHTML = '<option value="" disabled selected>Выберите новый тип</option>';
                        types.forEach(type => {
                            const option = document.createElement('option');
                            option.value = type.id;
                            option.textContent = `${type.name} (${type.requests_count || 0})`;
                            select.appendChild(option);
                        });
                        
                        const modal = new bootstrap.Modal(document.getElementById('changePlanningSubtypeModal'));
                        modal.show();
                    }
                } catch (err) {
                    console.error(err);
                }
            }
        }
    });

    // Обработка отправки формы смены типа
    const changeSubtypeForm = document.getElementById('changePlanningSubtypeForm');
    if (changeSubtypeForm) {
        changeSubtypeForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const requestIdsRaw = document.getElementById('changeSubtypeRequestId').value;
            const subtypeId = document.getElementById('newPlanningSubtypeSelect').value;
            
            if (!subtypeId || !requestIdsRaw) return;

            let requestIds = [];
            try {
                requestIds = JSON.parse(requestIdsRaw);
                if (!Array.isArray(requestIds)) requestIds = [requestIds];
            } catch (err) {
                requestIds = [requestIdsRaw];
            }

            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;

            try {
                // Всегда используем массовый эндпоинт для единообразия
                const response = await fetch(`/api/planning-types/requests/mass-subtype`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ 
                        request_ids: requestIds,
                        subtype_id: subtypeId 
                    })
                });

                if (response.ok) {
                    const modalEl = document.getElementById('changePlanningSubtypeModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    
                    // Обновляем селекты и счетчики
                    await updatePlanningSelects();
                    
                    // Снимаем выделение с чекбоксов
                    document.querySelectorAll('.request-checkbox:checked').forEach(cb => cb.checked = false);
                    
                    // Снимаем главный чекбокс (если есть)
                    ['selectAllRequests', 'selectAllRequestsPlanning'].forEach(id => {
                        const sa = document.getElementById(id);
                        if (sa) sa.checked = false;
                    });

                    if (typeof window.updatePrintButtonVisibility === 'function') {
                        window.updatePrintButtonVisibility();
                    }
                } else {
                    const result = await response.json();
                    alert(result.message || 'Ошибка при смене типа');
                }
            } catch (err) {
                console.error(err);
                alert('Ошибка сети');
            } finally {
                btn.disabled = false;
            }
        });
    }
}

export async function updatePlanningSelects() {
    try {
        const response = await fetch('/api/planning-types');
        if (!response.ok) return;
        const types = await response.json();

        const selectsToUpdate = [
            { id: 'planningSubtypeFilter', hasAllOption: true },
            { id: 'planningRequestSubtype', hasAllOption: false }
        ];

        selectsToUpdate.forEach(item => {
            const select = document.getElementById(item.id);
            if (select) {
                const currentValue = select.value;
                select.innerHTML = '';
                
                if (item.hasAllOption) {
                    const allOption = document.createElement('option');
                    allOption.value = '';
                    allOption.textContent = 'Все планирования';
                    select.appendChild(allOption);
                }

                types.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type.id;
                    option.textContent = `${type.name} (${type.requests_count || 0})`;
                    // Select "Стандартное планирование" as default or keep current
                    if (currentValue === String(type.id)) {
                        option.selected = true;
                    } else if (!currentValue && type.name === 'Стандартное планирование' && !item.hasAllOption) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                
                // Trigger change to update table
                // select.dispatchEvent(new Event('change'));
            }
        });
    } catch (err) {
        console.error('Ошибка обновления селектов:', err);
    }
}

window.updatePlanningSelects = updatePlanningSelects;
