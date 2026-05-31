// address-documents.js - обработчики для документов адресов

import { showAlert, postData, fetchData, getElement, getValue, validateRequiredField } from './utils.js';

// Функция инициализации обработчиков документов адресов
export function initAddressDocumentHandlers() {
    console.log('Инициализация обработчиков документов адресов');

    // Обработчик загрузки документов при создании адреса
    initAddressDocumentUpload();

    // Обработчик загрузки документов при редактировании адреса
    initEditAddressDocumentUpload();

    // Обработчик отображения документов в модальном окне редактирования
    initAddressDocumentsDisplay();
}

// Обработчик загрузки документов при создании адреса
function initAddressDocumentUpload() {
    const addressForm = document.getElementById('addressForm');
    const documentInput = document.getElementById('addressDocument');

    if (!addressForm || !documentInput) return;

    // Обработчик изменения файла
    documentInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files.length > 0) {
            console.log('Выбраны файлы для загрузки:', files.length);
            // Можно добавить валидацию файлов здесь
        }
    });
}

// Обработчик загрузки документов при редактировании адреса
function initEditAddressDocumentUpload() {
    const documentInput = document.getElementById('editAddressDocument');

    if (!documentInput) return;

    // Обработчик изменения файла
    documentInput.addEventListener('change', function(e) {
        const files = e.target.files;
        const addressId = document.getElementById('addressId')?.value;

        if (!addressId) {
            showAlert('Сначала выберите адрес для редактирования', 'warning');
            e.target.value = '';
            return;
        }

        if (files.length > 0) {
            console.log('Выбраны файлы для загрузки к адресу:', addressId, files.length);
            uploadAddressDocuments(addressId, files);
        }
    });
}

// Функция загрузки документов адреса
async function uploadAddressDocuments(addressId, files) {
    const formData = new FormData();

    // Добавляем CSRF токен
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

    // Добавляем файлы
    for (let i = 0; i < files.length; i++) {
        formData.append('file', files[i]);
        // Для простоты используем тип документа по умолчанию
        formData.append('document_type', 'Проект БТИ');
        formData.append('address_id', addressId);
    }

    try {
        showAlert('Загрузка документов...', 'info');

        const response = await fetch('/api/address-documents', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            showAlert('Документы успешно загружены', 'success');
            // Обновляем список документов
            loadAddressDocuments(addressId);
            // Очищаем input
            document.getElementById('editAddressDocument').value = '';
        } else {
            showAlert(data.message || 'Ошибка при загрузке документов', 'danger');
        }
    } catch (error) {
        console.error('Ошибка загрузки документов:', error);
        showAlert('Произошла ошибка при загрузке документов', 'danger');
    }
}

// Функция загрузки и отображения документов адреса
async function loadAddressDocuments(addressId) {
    console.log('Loading documents for address:', addressId);
    try {
        const response = await fetch(`/api/address-documents/address/${addressId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        console.log('Response status:', response.status);
        const data = await response.json();
        console.log('Response data:', data);

        if (data.success) {
            displayAddressDocuments(data.documents);
        } else {
            console.error('Ошибка загрузки документов:', data.message);
        }
    } catch (error) {
        console.error('Ошибка загрузки документов:', error);
    }
}

// Функция отображения документов в модальном окне
function displayAddressDocuments(documents) {
    console.log('Displaying documents:', documents);
    const container = document.getElementById('addressDocumentsList');

    if (!container) {
        console.error('Container #addressDocumentsList not found');
        return;
    }

    if (!documents || documents.length === 0) {
        container.innerHTML = '<div class="text-muted small">Документы не загружены</div>';
        return;
    }

    const html = documents.map(doc => {
        const fileName = doc.file_path ? doc.file_path.split('/').pop() : 'Неизвестный файл';
        return `
        <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
            <div class="flex-grow-1">
                <small class="fw-bold">${doc.document_type || 'Документ'} (${fileName})</small><br>
                <small class="text-muted">${doc.created_at ? new Date(doc.created_at).toLocaleDateString('ru-RU') : ''}</small>
            </div>
            <a href="/api/address-documents/download/${doc.id}"
               class="btn btn-sm btn-outline-primary"
               target="_blank"
               title="Скачать документ">
                <i class="bi bi-download"></i>
            </a>
        </div>
    `;}).join('');

    container.innerHTML = html;
    console.log('HTML set to container');
}

// Обработчик отображения документов при открытии модального окна редактирования
function initAddressDocumentsDisplay() {
    const editModal = document.getElementById('editAddressModal');

    if (!editModal) return;

    editModal.addEventListener('show.bs.modal', function() {
        // Документы будут загружены вместе с данными адреса в существующем обработчике
        // Здесь можно добавить дополнительную логику если нужно
    });
}

// Экспорт функций для использования в других модулях
window.AddressDocuments = {
    loadAddressDocuments,
    uploadAddressDocuments,
    displayAddressDocuments
};