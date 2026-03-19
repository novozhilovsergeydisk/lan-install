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
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
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
            document.getElementById('cpu-load').innerText = `${data.cpu.load_1m.toFixed(2)}, ${data.cpu.load_5m.toFixed(2)}, ${data.cpu.load_15m.toFixed(2)}`;

            // Обновляем ОЗУ
            updateProgress('ram', data.memory.usage_percent);
            document.getElementById('ram-info').innerText = `${formatBytes(data.memory.used)} / ${formatBytes(data.memory.total)}`;

            // Обновляем Диск
            updateProgress('disk', data.disk.usage_percent);
            document.getElementById('disk-info').innerText = `${formatBytes(data.disk.used)} / ${formatBytes(data.disk.total)}`;

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
