/**
 * Правка фактических объёмов выполненных работ из раздела «Отчёты».
 *
 * Зачем: монтажники пишут в комментарии «выполнено 7 из 13», а поле с количеством
 * не меняют — в выгрузку уходят завышенные объёмы. Кнопка «Объёмы» в строке отчёта
 * открывает это окно, где правится только колонка «Выполнено». План показываем рядом,
 * чтобы было видно, от чего отклонились; менять его отсюда нельзя.
 *
 * Доступно только администратору (проверка есть и на сервере).
 */
(function () {
    const MODAL_ID = 'editWorkParametersModal';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function renderRows(items) {
        const content = document.getElementById('editWorkParametersContent');
        const saveBtn = document.getElementById('saveWorkParametersBtn');

        if (!items.length) {
            content.innerHTML = '<div class="alert alert-info mb-0">У заявки нет работ с указанными объёмами.</div>';
            saveBtn.disabled = true;
            return;
        }

        // dark-theme-table — общий класс проекта: без него на тёмной теме
        // текст таблицы остаётся тёмным и сливается с фоном.
        content.innerHTML = `
            <table class="table table-sm align-middle mb-0 dark-theme-table">
                <thead>
                    <tr>
                        <th>Работа</th>
                        <th class="text-center" style="width: 90px;">План</th>
                        <th class="text-center" style="width: 110px;">Выполнено</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map(item => `
                        <tr>
                            <td>${item.parameter_name}</td>
                            <td class="text-center text-muted">${item.planned_quantity ?? '—'}</td>
                            <td>
                                <input type="number" min="0" step="1"
                                       class="form-control form-control-sm work-parameter-actual"
                                       data-parameter-type-id="${item.parameter_type_id}"
                                       data-original="${item.actual_quantity ?? ''}"
                                       value="${item.actual_quantity ?? ''}">
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <small class="text-muted d-block mt-2">Плановые значения не меняются — они нужны для сравнения.</small>
        `;

        saveBtn.disabled = false;
    }

    async function openModal(requestId, requestNumber) {
        const modalEl = document.getElementById(MODAL_ID);
        if (!modalEl) return;

        document.getElementById('editWorkParametersRequestId').value = requestId;
        document.getElementById('editWorkParametersRequestNumber').textContent = requestNumber || `#${requestId}`;

        const content = document.getElementById('editWorkParametersContent');
        content.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>`;
        document.getElementById('saveWorkParametersBtn').disabled = true;

        new bootstrap.Modal(modalEl).show();

        try {
            const response = await fetch(`/api/requests/${requestId}/work-parameters`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Не удалось загрузить объёмы');
            }

            renderRows(result.data || []);
        } catch (error) {
            console.error('Ошибка загрузки объёмов:', error);
            content.innerHTML = `<div class="alert alert-danger mb-0">${error.message}</div>`;
        }
    }

    async function save() {
        const requestId = document.getElementById('editWorkParametersRequestId').value;
        const saveBtn = document.getElementById('saveWorkParametersBtn');
        const inputs = document.querySelectorAll('.work-parameter-actual');

        // Отправляем только изменённые строки — иначе история засорится «правками» без изменений.
        const parameters = [];
        inputs.forEach(input => {
            const value = input.value.trim();
            if (value === '' || value === input.dataset.original) return;
            parameters.push({
                parameter_type_id: Number(input.dataset.parameterTypeId),
                quantity: Number(value)
            });
        });

        if (!parameters.length) {
            if (typeof showAlert === 'function') showAlert('Изменений нет', 'info');
            return;
        }

        const originalHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

        try {
            const response = await fetch(`/api/requests/${requestId}/work-parameters`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken()
                },
                body: JSON.stringify({ parameters })
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Не удалось сохранить');
            }

            bootstrap.Modal.getInstance(document.getElementById(MODAL_ID))?.hide();

            if (typeof showAlert === 'function') {
                showAlert(result.message || 'Объёмы обновлены', 'success');
            }

            // Обновляем отчёт, чтобы новые цифры сразу попали в таблицу и выгрузку.
            if (typeof window.reloadCurrentReport === 'function') {
                window.reloadCurrentReport();
            }
        } catch (error) {
            console.error('Ошибка сохранения объёмов:', error);
            if (typeof showAlert === 'function') showAlert(error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalHtml;
        }
    }

    // Делегирование: строки отчёта перерисовываются, вешать обработчик на кнопку бесполезно.
    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.edit-work-parameters-btn');
        if (btn) {
            event.preventDefault();
            openModal(btn.dataset.requestId, btn.dataset.requestNumber);
            return;
        }

        if (event.target.closest('#saveWorkParametersBtn')) {
            event.preventDefault();
            save();
        }
    });
})();
