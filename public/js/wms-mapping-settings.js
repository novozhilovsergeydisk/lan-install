import { showAlert } from './utils.js';

export function initWmsMappingSettings() {
    const container = document.getElementById('wms-mapping');
    if (!container) return;

    container.addEventListener('submit', async function(e) {
        const form = e.target;
        if (form.action && form.action.includes('wms-mappings')) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            const originalContent = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                // Если это кнопка удаления (с иконкой корзины), стилизуем иначе
                if (submitBtn.classList.contains('text-danger') || form.querySelector('input[name="_method"]')?.value === 'DELETE') {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                } else {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Загрузка...';
                }
                submitBtn.disabled = true;
            }

            try {
                const formData = new FormData(form);
                const method = form.method || 'POST';
                
                const response = await fetch(form.action, {
                    method: method,
                    body: formData,
                    headers: {
                        // Не добавляем 'X-Requested-With': 'XMLHttpRequest' чтобы получить обычный редирект HTML
                    }
                });

                if (response.ok) {
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const newContainer = doc.getElementById('wms-mapping');
                    if (newContainer) {
                        container.innerHTML = newContainer.innerHTML;
                        
                        if (form.querySelector('input[name="_method"]')?.value === 'DELETE') {
                            showAlert('Привязка успешно удалена', 'success');
                        } else {
                            showAlert('Привязка успешно сохранена', 'success');
                        }
                    } else {
                        window.location.reload();
                    }
                } else {
                    showAlert('Произошла ошибка при обработке запроса', 'danger');
                    if (submitBtn) {
                        submitBtn.innerHTML = originalContent;
                        submitBtn.disabled = false;
                    }
                }
            } catch (error) {
                console.error(error);
                showAlert('Сетевая ошибка: ' + error.message, 'danger');
                if (submitBtn) {
                    submitBtn.innerHTML = originalContent;
                    submitBtn.disabled = false;
                }
            }
        }
    });
}
