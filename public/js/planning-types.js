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
                <span class="type-name" data-id="${type.id}">${type.name}</span>
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

    async function updatePlanningSelects() {
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
                        option.textContent = type.name;
                        // Select "Стандартное планирование" as default or keep current
                        if (currentValue === String(type.id)) {
                            option.selected = true;
                        } else if (!currentValue && type.name === 'Стандартное планирование' && !item.hasAllOption) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                    
                    // Trigger change to update table
                    select.dispatchEvent(new Event('change'));
                }
            });
        } catch (err) {
            console.error('Ошибка обновления селектов:', err);
        }
    }
}
