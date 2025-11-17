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

import { loadAddressesForAdditional } from './handler.js';

/**
 * Инициализирует модальное окно для дополнительного задания
 */
export function initAdditionalTaskModal() {
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.open-additional-task-request-btn')) return;

        e.preventDefault();

        // Получаем request_id из data-атрибута кнопки
        const requestId = e.target.getAttribute('data-request-id');
        const requestIdField = document.getElementById('additionalTaskRequestId');
        if (requestIdField && requestId) {
            requestIdField.value = requestId;
        }

        const modal = document.getElementById('additionalTaskModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            // Устанавливаем текущую дату по умолчанию и минимальную дату
            const today = new Date().toISOString().split('T')[0];
            const dateField = document.getElementById('additionalExecutionDate');
            if (dateField) {
                dateField.value = today;
                dateField.min = today;
            }
            // Загружаем адреса для дополнительной формы
            loadAddressesForAdditional();
        }
    });

    // Обработчик для кнопки "Создать задание"
    document.addEventListener('click', function(e) {
        if (e.target.id === 'submitAdditionalTask') {
            // Синхронизируем содержимое WYSIWYG-редактора с textarea перед сбором данных
            const editor = document.getElementById('additionalCommentEditor');
            const textarea = document.getElementById('additionalComment');
            if (editor && textarea) {
                textarea.value = editor.innerHTML;
            }

            // Выводим входные данные формы в консоль
            const form = document.getElementById('additionalTaskForm');
            if (form) {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);
                console.log('Входные данные формы дополнительного задания:', data);
                console.log('Request ID:', data.request_id);
            }
            showAlert('Функционал добавления дополнительного задания в разработке');
        }
    });
}

/**
 * Инициализирует модальное окно для загрузки фотоотчета
 */
 export function initPhotoReportModal() {
    console.log('Инициализация модального окна для фотоотчетов');
    const photoModal = document.getElementById('addPhotoModal');
    // console.log('Найдено модальное окно:', !!photoModal);
    if (!photoModal) {
        console.error('Модальное окно addPhotoModal не найдено в DOM');
        return;
    }

    // Локальное состояние выбранных файлов (синхронизируется с input.files)
    let selectedFiles = [];

    // Синхронизация input.files из selectedFiles
    function syncInputFiles() {
        const fileInput = photoModal.querySelector('#photoUpload');

        console.log('Вызов syncInputFiles()', selectedFiles);

        if (!fileInput) return;
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }

    // Перерисовка превью по selectedFiles
    function renderPreviews() {
        // Ищем контейнер в глобальной области видимости
        const previewContainer = document.getElementById('photoPreviewNew');
        const container = previewContainer?.closest('.mb-3');
        
        if (!previewContainer) return;
        
        // Очищаем предыдущие превью
        previewContainer.innerHTML = '';
        
        // Проверяем, есть ли изображения для отображения
        const imageFiles = selectedFiles.filter(file => file.type.startsWith('image/'));
        
        if (imageFiles.length === 0) {
            // Если изображений нет, скрываем контейнер
            if (container) container.classList.add('d-none');
            return;
        }
        
        // Показываем контейнер
        if (container) container.classList.remove('d-none');
        
        // Добавляем превью для каждого изображения
        imageFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'col-md-4 mb-3';
                preview.innerHTML = `
                    <div class="card">
                        <img src="${e.target.result}" class="card-img-top" alt="Предпросмотр" style="height: 120px; object-fit: cover;">
                        <div class="card-body p-2 text-center">
                            <button type="button" class="btn btn-sm btn-danger remove-photo">
                                <i class="bi bi-trash"></i> Удалить
                            </button>
                        </div>
                    </div>
                `;
                previewContainer.appendChild(preview);

                // Обработчик удаления
                const removeBtn = preview.querySelector('.remove-photo');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Удаляем файл из выбранных
                        const fileIndex = selectedFiles.findIndex(f => f === file);
                        if (fileIndex !== -1) {
                            selectedFiles.splice(fileIndex, 1);
                            syncInputFiles();
                            renderPreviews();
                        }
                    });
                }
            };
            reader.readAsDataURL(file);
        });
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

        // Сбрасываем состояние выбранных файлов
        selectedFiles = [];
        syncInputFiles();
    });

    // Обработчик загрузки фотографий
    const photoInput = photoModal.querySelector('#photoUpload');
    const previewContainer = document.getElementById('photoPreviewNew');
    
    if (photoInput) {
        photoInput.addEventListener('change', function(e) {
            // Обновляем локальное состояние и синхронизируем input
            selectedFiles = Array.from(e.target.files || []);
            console.log('selectedFiles 2', selectedFiles);
            syncInputFiles();
            
            // Показываем контейнер превью, если есть выбранные файлы
            if (previewContainer && selectedFiles.length > 0) {
                previewContainer.closest('.mb-3').classList.remove('d-none');
            } else if (previewContainer) {
                previewContainer.closest('.mb-3').classList.add('d-none');
            }
            
            renderPreviews();
            
            // Автоматически закрываем модальное окно после выбора файлов
            const modal = bootstrap.Modal.getInstance(photoModal);
            if (modal && selectedFiles.length > 0) {
                setTimeout(() => {
                    modal.hide();
                }, 500); // Небольшая задержка для лучшего UX
            }
        });
    }
    
    // Обработчик отправки формы комментария с фотоотчетом
    const commentForm = document.getElementById('addCommentForm');
    // console.log('Найдена форма addCommentForm:', !!commentForm);



    return;


    
    if (commentForm) {
        // console.log('Добавление обработчика submit для формы комментария');
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Предотвращение повторной отправки
            if (this.dataset.submitting === '1') {
                return;
            }
            this.dataset.submitting = '1';

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
            // Получаем кнопку отправки
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton ? submitButton.innerHTML : '';
            
            // Получаем выбранные файлы из модального окна
            const fileInput = photoModal.querySelector('#photoUpload');
            const previewContainer = document.getElementById('photoPreviewNew');
            
            // Диагностика: что у нас сейчас выбрано и что уйдет на сервер
            try {
                console.group('Фотоотчет: диагностика перед отправкой');
                console.log('request_id:', requestId);
                if (fileInput) {
                    console.log('fileInput.files.length:', fileInput.files.length);
                    Array.from(fileInput.files).forEach((f, i) => {
                        console.log(`fileInput.files[${i}] -> name: ${f.name}, size: ${f.size}, type: ${f.type}`);
                    });
                }
                if (previewContainer) {
                    const previews = previewContainer.querySelectorAll('.card-img-top');
                    console.log('previews count (в контейнере предпросмотра):', previews.length);
                }
                // Выводим содержимое FormData
                const fdEntries = [];
                for (const [key, value] of formData.entries()) {
                    if (value instanceof File) {
                        fdEntries.push({ key, name: value.name, size: value.size, type: value.type });
                    } else {
                        fdEntries.push({ key, value });
                    }
                }
                console.log('FormData entries:', fdEntries);
                // Частое заблуждение: удаление превью не удаляет файл из input.files
                console.info('Note: удаление превью сейчас не влияет на input.files. Если здесь файлов больше, чем превью, причина в этом.');
                console.groupEnd();
            } catch (e) {
                console.warn('Ошибка диагностического логирования:', e);
            }
            
            // console.log('Найден input для загрузки файлов:', !!fileInput);
            // console.log('Найден контейнер для превью:', !!previewContainer);
            
            if (!fileInput || fileInput.files.length === 0) {
                // showAlert('Пожалуйста, выберите хотя бы одно фото для загрузки', 'warning');
                // console.log('Пожалуйста, выберите хотя бы одно фото для загрузки');
                // Фото может быть и не выбрано
                return;
            }
            
            // Добавляем файлы в formData, если они есть
            if (fileInput && fileInput.files.length > 0) {
                Array.from(fileInput.files).forEach((file, index) => {
                    formData.append('photos[]', file);
                });
            }
            
            // Показываем индикатор загрузки
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Отправка...';
            }

            console.log('Форма отправки фотоотчета formData (modals.js)', formData);

            try {
                // Отправка данных на сервер
                const response = await fetch('/api/requests/photo-report', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || `Ошибка сервера: ${response.status}`);
                }
                
                // Показываем уведомление об успешной загрузке
                showAlert('Фотоотчет успешно загружен', 'success');
                
                // Обновляем страницу или таблицу заявок, если нужно
                if (typeof window.updateRequestsTable === 'function') {
                    window.updateRequestsTable();
                }

                console.log('data фотоотчета = ', data);
                const photos = data.data.photos;
                console.log('photos = ', photos);

                const dataSession = sessionStorage.getItem('data');
                const dataSessionJson = JSON.parse(dataSession);
                // console.log('dataSession = ', dataSessionJson);
                // console.log('------------------------');
                // console.log('sessionId 1', dataSessionJson['sessionId']);
                // console.log('sessionId 2', sessionStorage.getItem('sessionId'));
                // console.log('------------------------');

                if (dataSessionJson['sessionId'] === sessionStorage.getItem('sessionId') && sessionStorage.getItem('sessionId') !== null) {  
                    // console.log('|---------------------------------');
                    // console.log('|----- В [dataSession] записаны данные', dataSessionJson);
                    console.log('|---------------------------------');
                    console.log('|----- Записываем фотоотчет для комментария');
                    console.log('|----- commentId = ', dataSessionJson.commentId);
                    console.log('|----- files = ', fileInput.files);
                    console.log('|----- sessionId = ', dataSessionJson.sessionId);
                    console.log('|---------------------------------');
                    // Здесь будет запрос к таблице comments_photos для записи фотоотчета
                    
                    // Создаем FormData для отправки файлов
                    const formData = new FormData();
                    formData.append('request_id', requestId);
                    formData.append('comment', dataSessionJson.commentId);

                    // Добавляем ID фотографий
                    if (photos && photos.length > 0) {
                        console.log('Добавляем ID фотографий в formData:');
                        const photoIds = photos.map(photo => photo.id);
                        formData.append('photo_ids', JSON.stringify(photoIds));
                        console.log('ID фотографий для отправки:', photoIds);
                    } else {
                        console.warn('Нет фотографий для отправки');
                    }

                    // Выводим содержимое formData в читаемом виде
                    console.log('Содержимое formData:');
                    for (let [key, value] of formData.entries()) {
                        console.log(key, value);
                    }

                    // return;

                    // Отправляем запрос на сервер
                    fetch('api/requests/photo-comment', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Ответ сервера:', data);
                        
                        // if (data.success && data.data && data.data.photos) {
                        //     // Получаем массив ID загруженных фотографий
                        //     const photoIds = data.data.photos.map(photo => photo.id);
                        //     console.log('ID загруженных фотографий:', photoIds);
                            
                        //     // Здесь можно использовать photoIds для дальнейшей работы
                        //     // Например, обновить интерфейс или сохранить в хранилище
                            
                        //     showAlert('Фотоотчет успешно загружен', 'success');
                        // } else {
                        //     console.log('Ошибка при загрузке фотоотчета:', data.message || 'Неизвестная ошибка');
                        //     showAlert('Ошибка при загрузке фотоотчета', 'error');
                        // }
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке фотоотчета для комментария:', error);
                        showAlert(error.message || 'Произошла ошибка при загрузке фотоотчета для комментария', 'danger');
                    });
                }

                console.log('data фотоотчета для комментария = ', data);
                
                return data;
            } catch (error) {
                console.error('Ошибка при загрузке фотоотчета для комментария:', error);
                showAlert(error.message || 'Произошла ошибка при загрузке фотоотчета для комментария', 'danger');
                throw error;
            } finally {
                // Очищаем форму и превью
                if (this.reset) this.reset();
                if (previewContainer) {
                    previewContainer.innerHTML = '<div class="col-12 text-muted">Здесь будет предпросмотр выбранных фотографий</div>';
                    // Скрываем контейнер превью
                    const container = previewContainer.closest('.mb-3');
                    if (container) container.classList.add('d-none');
                }
                // Очищаем input файлов
                if (fileInput) {
                    fileInput.value = '';
                    selectedFiles = [];
                    syncInputFiles();
                }
                // Восстанавливаем кнопку
                if (submitButton) {
                    submitButton.disabled = false;
                    if (originalButtonText) submitButton.innerHTML = originalButtonText;
                }
                // Сбрасываем флаг отправки
                this.dataset.submitting = '0';
            }
        });
    }
}