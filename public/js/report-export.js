document.addEventListener('DOMContentLoaded', function() {
    const exportBtn = document.getElementById('export-report-btn');
    
    if (exportBtn) {
        exportBtn.addEventListener('click', async function() {
            // Собираем данные фильтров по ID из report-handler.js
            const startDate = $('#datepicker-reports-start').datepicker('getFormattedDate');
            const endDate = $('#datepicker-reports-end').datepicker('getFormattedDate');
            
            const employeeSelect = document.getElementById('report-employees');
            const addressSelect = document.getElementById('report-addresses');
            const organizationSelect = document.getElementById('report-organizations');
            const requestTypeSelect = document.getElementById('report-request-types');
            const allPeriodCheckbox = document.getElementById('report-all-period');
            
            const payload = {
                startDate: startDate,
                endDate: endDate,
                allPeriod: allPeriodCheckbox ? allPeriodCheckbox.checked : false,
                employeeId: employeeSelect ? employeeSelect.value : null,
                addressId: addressSelect ? addressSelect.value : null,
                organization: organizationSelect ? organizationSelect.value : null,
                requestTypeId: requestTypeSelect ? requestTypeSelect.value : null
            };

            console.log('📤 [Export Debug] Sending payload:', payload);

            // UI Loading state
            const originalText = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Экспорт...';

            try {
                const response = await fetch('/reports/requests/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });
                
                // Проверяем наличие заголовка с SQL (для отладки)
                const debugSql = response.headers.get('X-Debug-SQL');
                if (debugSql) {
                    console.log('🛠 [SQL Debug]:', decodeURIComponent(debugSql));
                }

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Ошибка экспорта');
                }

                // Скачивание файла
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                
                // Пытаемся получить имя файла из заголовков
                const contentDisposition = response.headers.get('Content-Disposition');
                let filename = 'report.xlsx';
                if (contentDisposition) {
                    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                    if (filenameMatch.length === 2)
                        filename = filenameMatch[1];
                }
                
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();

            } catch (error) {
                console.error('Export error:', error);
                alert('Произошла ошибка при экспорте: ' + error.message);
            } finally {
                exportBtn.disabled = false;
                exportBtn.innerHTML = originalText;
            }
        });
    }
});
