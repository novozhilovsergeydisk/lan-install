document.addEventListener('DOMContentLoaded', function () {
    const wmsDeductCheckbox = document.getElementById('wmsDeductCheckbox');
    const wmsIntegrationSection = document.getElementById('wmsIntegrationSection');
    const wmsStockContainer = document.getElementById('wmsStockContainer');
    const requestIdToClose = document.getElementById('requestIdToClose');
    const closeRequestModal = document.getElementById('closeRequestModal');

    if (!wmsDeductCheckbox || !wmsIntegrationSection) return;

    // Переключение видимости секции
    wmsDeductCheckbox.addEventListener('change', function () {
        if (this.checked) {
            wmsIntegrationSection.style.display = 'block';
            loadBrigadeStock(requestIdToClose.value);
        } else {
            wmsIntegrationSection.style.display = 'none';
        }
    });

    // Сброс при открытии модалки
    closeRequestModal.addEventListener('show.bs.modal', function () {
        wmsDeductCheckbox.checked = false;
        wmsIntegrationSection.style.display = 'none';
        wmsStockContainer.innerHTML = '<div class="text-muted small text-center">Загрузка данных о материалах бригады...</div>';
    });

    async function loadBrigadeStock(requestId) {
        wmsStockContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Загрузка остатков бригады...</div>';
        try {
            const response = await fetch(`/api/wms/brigade-stock/${requestId}`);
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                let html = '';
                const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark' || document.body.classList.contains('dark-mode');
                const tableClass = isDark ? 'table table-sm table-bordered table-dark mb-1' : 'table table-sm table-bordered mb-1';
                
                result.data.forEach(member => {
                    html += `
                        <div class="wms-member-group mb-3 border-bottom pb-2" data-email="${member.email}">
                            <div class="fw-bold small mb-1 text-primary">${member.fio}</div>
                    `;

                    if (member.stock && member.stock.length > 0) {
                        html += `
                            <table class="${tableClass}" style="font-size: 0.8rem;">
                                <thead>
                                    <tr><th>Материал</th><th width="60">Остаток</th><th width="80">Расход</th></tr>
                                </thead>
                                <tbody>
                        `;
                        
                        member.stock.forEach(item => {
                            html += `
                                <tr class="wms-material-row" data-id="${item.nomenclatureId}">
                                    <td>${item.nomenclatureName} <span class="text-muted small">(${item.unitName})</span></td>
                                    <td class="text-center">${item.quantity}</td>
                                    <td>
                                        <input type="number" step="any" min="0" max="${item.quantity}" 
                                            class="form-control form-control-sm wms-usage-input" 
                                            data-max="${item.quantity}"
                                            placeholder="0">
                                    </td>
                                </tr>
                            `;
                        });
                        
                        html += '</tbody></table>';
                    } else {
                        html += '<div class="text-muted small ps-2">Нет материалов на руках.</div>';
                    }
                    
                    html += '</div>';
                });
                
                wmsStockContainer.innerHTML = html;

                // Валидация на лету
                document.querySelectorAll('.wms-usage-input').forEach(input => {
                    input.addEventListener('input', function() {
                        const max = parseFloat(this.getAttribute('data-max'));
                        const val = parseFloat(this.value);
                        if (val > max) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    });
                });
            } else {
                wmsStockContainer.innerHTML = '<div class="alert alert-warning p-2 small">Данные о бригаде или остатках не найдены.</div>';
            }
        } catch (error) {
            console.error('WMS stock error:', error);
            wmsStockContainer.innerHTML = '<div class="alert alert-danger p-2 small">Ошибка загрузки остатков.</div>';
        }
    }
});