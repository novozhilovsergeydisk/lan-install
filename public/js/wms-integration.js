document.addEventListener('DOMContentLoaded', function () {
    const wmsDeductCheckbox = document.getElementById('wmsDeductCheckbox');
    const wmsIntegrationSection = document.getElementById('wmsIntegrationSection');
    const wmsStockContainer = document.getElementById('wmsStockContainer');
    const requestIdToClose = document.getElementById('requestIdToClose');
    const closeRequestModal = document.getElementById('closeRequestModal');
    const wmsSourceContainer = document.getElementById('wmsSourceContainer');
    const wmsWarehouseSearchContainer = document.getElementById('wmsWarehouseSearchContainer');
    const wmsSourceRadios = document.getElementsByName('wms_source_radio');
    const wmsWarehouseLabel = document.getElementById('wmsWarehouseLabel');
    const wmsMappedWarehouseId = document.getElementById('wmsMappedWarehouseId');
    const wmsWarehouseSearchInput = document.getElementById('wmsWarehouseSearchInput');

    if (!wmsDeductCheckbox || !wmsIntegrationSection) return;

    // Переключение видимости секции
    wmsDeductCheckbox.addEventListener('change', function () {
        if (this.checked) {
            wmsIntegrationSection.style.display = 'block';
            checkWmsMapping(requestIdToClose.value);
        } else {
            wmsIntegrationSection.style.display = 'none';
        }
    });

    // Переключение источника списания
    wmsSourceRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'warehouse') {
                loadWarehouseStock(wmsMappedWarehouseId.value);
            } else {
                loadBrigadeStock(requestIdToClose.value);
            }
        });
    });

    // Сброс при открытии модалки
    closeRequestModal.addEventListener('show.bs.modal', function () {
        wmsDeductCheckbox.checked = false;
        wmsIntegrationSection.style.display = 'none';
        wmsSourceContainer.classList.add('d-none');
        wmsWarehouseSearchContainer.classList.add('d-none');
        wmsStockContainer.innerHTML = '<div class="text-muted small text-center">Загрузка данных...</div>';
        wmsWarehouseSearchInput.value = '';
        wmsMappedWarehouseId.value = '';
        document.getElementById('wmsSourcePersonal').checked = true;
    });

    async function checkWmsMapping(requestId) {
        wmsStockContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Проверка настроек склада...</div>';
        try {
            const response = await fetch(`/api/wms/mapping/${requestId}`);
            const result = await response.json();

            if (result.success && result.mapping) {
                wmsSourceContainer.classList.remove('d-none');
                wmsWarehouseLabel.textContent = `Склад: ${result.mapping.warehouse_name}`;
                wmsMappedWarehouseId.value = result.mapping.wms_warehouse_id;
                // По умолчанию остаемся на Личных остатках
                loadBrigadeStock(requestId);
            } else {
                wmsIntegrationSection.style.display = 'none';
                wmsDeductCheckbox.checked = false;
                if (typeof showAlert === 'function') {
                    showAlert('Для данного типа заявки не привязан склад WMS. Списание невозможно.', 'warning');
                }
            }
        } catch (error) {
            console.error('WMS mapping check error:', error);
            wmsIntegrationSection.style.display = 'none';
            wmsDeductCheckbox.checked = false;
        }
    }

    async function loadBrigadeStock(requestId) {
        wmsWarehouseSearchContainer.classList.add('d-none');
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
                                    <tr class="table-light"><th>Материал</th><th width="60">Остаток</th><th width="80">Расход</th></tr>
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
            } else {
                wmsStockContainer.innerHTML = '<div class="alert alert-warning p-2 small">Данные о бригаде или остатках не найдены.</div>';
            }
        } catch (error) {
            console.error('WMS stock error:', error);
            wmsStockContainer.innerHTML = '<div class="alert alert-danger p-2 small">Ошибка загрузки остатков.</div>';
        }
    }

    async function loadWarehouseStock(warehouseId, query = '') {
        wmsWarehouseSearchContainer.classList.remove('d-none');
        wmsStockContainer.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Загрузка остатков склада...</div>';
        
        try {
            const response = await fetch(`/api/wms/warehouse-search?warehouseId=${warehouseId}&q=${encodeURIComponent(query)}`);
            const result = await response.json();

            if (result.success) {
                if (result.data.length > 0) {
                    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark' || document.body.classList.contains('dark-mode');
                    const tableClass = isDark ? 'table table-sm table-bordered table-dark mb-0' : 'table table-sm table-bordered mb-0';
                    
                    let html = `
                        <div class="wms-warehouse-group">
                            <table class="${tableClass}" style="font-size: 0.8rem;">
                                <thead>
                                    <tr class="table-light"><th>Материал</th><th width="70">Склад</th><th width="80">Расход</th></tr>
                                </thead>
                                <tbody>
                    `;

                    result.data.forEach(item => {
                        html += `
                            <tr class="wms-warehouse-row" data-id="${item.nomenclature_id}">
                                <td>${item.name} <span class="text-muted small">(${item.unit || ''})</span></td>
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

                    html += '</tbody></table></div>';
                    wmsStockContainer.innerHTML = html;
                } else {
                    wmsStockContainer.innerHTML = '<div class="p-3 text-center text-muted small">На складе нет доступных материалов.</div>';
                }
            } else {
                wmsStockContainer.innerHTML = `<div class="alert alert-danger p-2 small">Ошибка: ${result.message}</div>`;
            }
        } catch (error) {
            console.error('WMS warehouse load error:', error);
            wmsStockContainer.innerHTML = '<div class="alert alert-danger p-2 small">Ошибка соединения со складом.</div>';
        }
    }

    // Живой фильтр для склада
    let searchTimeout;
    wmsWarehouseSearchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        const warehouseId = wmsMappedWarehouseId.value;

        searchTimeout = setTimeout(() => {
            loadWarehouseStock(warehouseId, query);
        }, 300);
    });
});
