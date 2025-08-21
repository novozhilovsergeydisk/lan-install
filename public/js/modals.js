// Здесь будет окно для добавления фотоотчета, которое будет отображаться при нажатии на кнопку "Добавить фотоотчет"

// console.log('modals.js загружен');

// Функция-заглушка на случай ошибки импорта
let showAlert = function(message, type = 'success') {
    console.log(`[Alert ${type}]: ${message}`);
    alert(`[${type.toUpperCase()}] ${message}`);
};

// Загружаем утилиты асинхронно
import('./utils.js')
    .then(module => {
        showAlert = module.showAlert;
        // console.log('utils.js успешно загружен');
    })
    .catch(error => {
        console.error('Ошибка при загрузке utils.js:', error);
        console.log('Используется заглушка для showAlert');
    });

/**
 * Инициализация модального окна для загрузки фотоотчета
 */

document.addEventListener('DOMContentLoaded', function() {
    initPhotoReportModal();
});

// Инициализация модального окна для редактирования адреса
function initAddressEditModal() {
    // console.log('Инициализация модального окна для редактирования адреса');

    
    const editAddressModal = document.getElementById('editAddressModal');
    // console.log('Найдено модальное окно:', !!editAddressModal);
    if (!editAddressModal) {
        console.error('Модальное окно editAddressModal не найдено в DOM');
        return;
    }
}

/**
 * Инициализирует модальное окно для загрузки фотоотчета
 */
function initPhotoReportModal() {
    // console.log('Инициализация модального окна для фотоотчетов');
    const photoModal = document.getElementById('addPhotoModal');
    // console.log('Найдено модальное окно:', !!photoModal);
    if (!photoModal) {
        console.error('Модальное окно addPhotoModal не найдено в DOM');
        return;
    }

    // Обработчик открытия модального окна
    photoModal.addEventListener('show.bs.modal', function(event) {
        let requestId;
        
        // Пытаемся получить ID заявки из кнопки, вызвавшей модальное окно
        if (event.relatedTarget) {
            requestId = event.relatedTarget.getAttribute('data-request-id');
        }
        
        // Если не нашли в relatedTarget, ищем в активной кнопке
        if (!requestId) {
            const activeButton = document.querySelector('.add-photo-btn.active');
            if (activeButton) {
                requestId = activeButton.getAttribute('data-request-id');
            }
        }
        
        // Устанавливаем ID заявки в скрытое поле
        const requestIdInput = photoModal.querySelector('#photoRequestId');
        if (requestIdInput && requestId) {
            requestIdInput.value = requestId;
            
            // Обновляем заголовок модального окна
            const modalTitle = photoModal.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = `Фотоотчет по заявке #${requestId}`;
            }
        }
        
        // Очищаем превью фотографий
        const previewContainer = photoModal.querySelector('#photoPreview');
        if (previewContainer) {
            previewContainer.innerHTML = '';
        }
    });

    // Обработчик загрузки фотографий
    const photoInput = photoModal.querySelector('#photoUpload');
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = photoModal.querySelector('#photoPreview');
            
            if (!previewContainer) return;
            
            // Очищаем предыдущие превью
            previewContainer.innerHTML = '';
            
            // Показываем превью для каждого выбранного файла
            for (const file of files) {
                if (!file.type.startsWith('image/')) continue;
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'col-md-4 mb-3';
                    preview.innerHTML = `
                        <div class="card">
                            <img src="${e.target.result}" class="card-img-top" alt="Предпросмотр">
                            <div class="card-body p-2 text-center">
                                <button type="button" class="btn btn-sm btn-danger remove-photo">
                                    <i class="bi bi-trash"></i> Удалить
                                </button>
                            </div>
                        </div>
                    `;
                    previewContainer.appendChild(preview);
                    
                    // Добавляем обработчик для кнопки удаления
                    const removeBtn = preview.querySelector('.remove-photo');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function() {
                            preview.remove();
                        });
                    }
                };
                
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Обработчик отправки формы
    const photoForm = photoModal.querySelector('#photoReportForm');
    // console.log('Найдена форма photoReportForm:', !!photoForm);
    
    if (photoForm) {
        // console.log('Добавление обработчика submit для формы');
        photoForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Предотвращение повторной отправки
            if (photoForm.dataset.submitting === '1') {
                return;
            }
            photoForm.dataset.submitting = '1';

            // Закрываем модальное окно
            const modal = bootstrap.Modal.getInstance(photoModal);
            if (modal) {
                modal.hide();
            }

            // showAlert('Форма отправки фотоотчета в разработке', 'info');

            // return;

            console.log('Событие submit формы обработано');
            
            const formData = new FormData(this);
            const requestId = formData.get('request_id');
            // Кнопка сабмита расположена вне формы и привязана атрибутом form
            const submitButton = document.querySelector('button[type="submit"][form="photoReportForm"]');
            const originalButtonText = submitButton ? submitButton.innerHTML : '';
            
            // Файлы уже присутствуют в formData, так как FormData создана из формы
            const fileInput = photoModal.querySelector('#photoUpload');
            const previewContainer = photoModal.querySelector('#photoPreview');
            
            // console.log('Найден input для загрузки файлов:', !!fileInput);
            // console.log('Найден контейнер для превью:', !!previewContainer);
            
            if (!fileInput || fileInput.files.length === 0) {
                // console.log('Файлы не выбраны');
                showAlert('Пожалуйста, выберите хотя бы одно фото для загрузки', 'warning');
                return;
            }
            
            // Не добавляем файлы вручную в formData, чтобы избежать дублей
            
            // Показываем индикатор загрузки
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Отправка...';
            }
            
            try {
                // Отправка данных на сервер
                // console.log('Отправка запроса на загрузку фотоотчета...');
                const response = await fetch('/api/requests/photo-report', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                // console.log('Получен ответ от сервера:', {
                //     status: response.status,
                //     statusText: response.statusText,
                //     headers: Object.fromEntries(response.headers.entries())
                // });
                
                const data = await response.json();
                // console.log('Тело ответа:', data);
                
                if (!response.ok) {
                    throw new Error(data.message || `Ошибка сервера: ${response.status}`);
                }
                
                if (data.success) {
                    showAlert('Фотоотчет успешно загружен', 'success');
                    
                    // Очищаем форму
                    photoForm.reset();
                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                    }
                    
                    // Закрываем модальное окно
                    const modal = bootstrap.Modal.getInstance(photoModal);
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Обновляем страницу или таблицу заявок, если нужно
                    if (typeof window.updateRequestsTable === 'function') {
                        window.updateRequestsTable();
                    }
                } else {
                    throw new Error(data.message || 'Произошла ошибка при загрузке фотоотчета');
                }
            } catch (error) {
                // console.error('Ошибка при загрузке фотоотчета:', error);
                showAlert(error.message || 'Произошла ошибка при загрузке фотоотчета', 'danger');
            } finally {
                // Сбрасываем флаг отправки
                photoForm.dataset.submitting = '0';
                // Восстанавливаем кнопку
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            }
        });
    }
}