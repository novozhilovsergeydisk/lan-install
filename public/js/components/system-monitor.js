document.addEventListener('DOMContentLoaded', () => {
    const systemTab = document.getElementById('system-tab');
    if (!systemTab) return; // Не инициализируем, если вкладки нет (не админ)

    const systemContainer = document.getElementById('system');
    let monitorInterval = null;

    // Функция для обновления графиков/баров
    const updateProgress = (elementId, value, format = '%') => {
        const bar = document.getElementById(`${elementId}-bar`);
        const text = document.getElementById(`${elementId}-text`);
        
        if (bar && text) {
            bar.style.width = `${value}%`;
            bar.setAttribute('aria-valuenow', value);
            text.innerText = format === '%' ? `${value.toFixed(1)}%` : value;

            // Цветовая индикация нагрузки
            bar.className = 'progress-bar';
            if (value > 90) {
                bar.classList.add('bg-danger');
            } else if (value > 70) {
                bar.classList.add('bg-warning');
            } else {
                bar.classList.add('bg-success');
            }
        }
    };

    // Функция форматирования байт в читаемый вид
    const formatBytes = (bytes, decimals = 2) => {
        if (!+bytes) return '0 Байт';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Байт', 'КБ', 'МБ', 'ГБ', 'ТБ', 'ПБ', 'ЭБ', 'ЗБ', 'ИБ'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
    };

    const fetchMetrics = async () => {
        try {
            const response = await fetch('/api/system/metrics', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();

            // Обновляем CPU
            updateProgress('cpu', data.cpu.usage_percent);
            document.getElementById('cpu-cores').innerText = data.cpu.cores;
            
            const cpuLoadElement = document.getElementById('cpu-load');
            if (cpuLoadElement) cpuLoadElement.innerText = data.cpu.load_1m.toFixed(2);
            
            const cpuPercentElement = document.getElementById('cpu-load-percent');
            if (cpuPercentElement) cpuPercentElement.innerText = data.cpu.usage_percent.toFixed(1);

            // Обновляем ОЗУ
            updateProgress('ram', data.memory.usage_percent);
            updateProgress('ram-apps', data.memory.apps_percent);
            
            document.getElementById('ram-info').innerText = `${formatBytes(data.memory.used)} / ${formatBytes(data.memory.total)}`;
            
            const ramAvailableElement = document.getElementById('ram-available');
            if (ramAvailableElement) ramAvailableElement.innerText = formatBytes(data.memory.available);

            // Обновляем Диск
            updateProgress('disk', data.disk.usage_percent);
            document.getElementById('disk-info').innerText = `${formatBytes(data.disk.used)} / ${formatBytes(data.disk.total)}`;

            // Обновляем список процессов
            const processesList = document.getElementById('top-processes-list');
            if (processesList && data.top_processes) {
                processesList.innerHTML = ''; // Очищаем спиннер
                
                if (data.top_processes.length === 0) {
                    processesList.innerHTML = '<tr><td colspan="5" class="text-center py-3">Процессы не найдены</td></tr>';
                } else {
                    data.top_processes.forEach(proc => {
                        const row = `
                            <tr>
                                <td class="text-truncate" style="max-width: 250px;" title="${proc.command}">
                                    <code class="text-primary fw-bold">${proc.command}</code>
                                </td>
                                <td><span class="badge border theme-user-badge">${proc.user}</span></td>
                                <td class="text-end px-3 fw-bold text-danger">${proc.cpu}%</td>
                                <td class="text-end px-3">${proc.mem}%</td>
                                <td class="text-end px-3 text-muted small">${proc.pid}</td>
                            </tr>
                        `;
                        processesList.insertAdjacentHTML('beforeend', row);
                    });
                }
            }

        } catch (error) {
            console.error('Error fetching system metrics:', error);
            document.getElementById('system-error').classList.remove('d-none');
        }
    };

    // Управление интервалом обновления при переключении вкладок
    systemTab.addEventListener('shown.bs.tab', () => {
        fetchMetrics(); // Запрос сразу при открытии
        monitorInterval = setInterval(fetchMetrics, 5000); // Обновление каждые 5 сек
    });

    systemTab.addEventListener('hidden.bs.tab', () => {
        if (monitorInterval) {
            clearInterval(monitorInterval);
        }
    });
});
