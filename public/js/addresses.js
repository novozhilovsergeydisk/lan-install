// Функции для работы с адресами и дубликатами

import { showAlert } from './utils.js';

/**
 * Функция для проверки дубликатов адресов
 */
export async function checkForDuplicateAddresses() {
    try {
        const response = await fetch('/api/addresses/duplicates');
        if (!response.ok) {
            throw new Error('Ошибка проверки дубликатов');
        }

        const data = await response.json();
        const notification = document.getElementById('duplicates-notification');
        const countSpan = document.getElementById('duplicates-count');

        if (data.success && data.total_duplicates > 0) {
            countSpan.textContent = `Найдено ${data.total_addresses} повторяющихся адресов.`;
            notification.classList.remove('d-none');
            notification.classList.add('show');
        } else {
            notification.classList.add('d-none');
            notification.classList.remove('show');
        }
    } catch (error) {
        console.error('Ошибка при проверке дубликатов:', error);
    }
}

/**
 * Функция для отображения дубликатов адресов в модальном окне
 */
export async function showDuplicatesModal() {
    try {
        const response = await fetch('/api/addresses/duplicates');
        if (!response.ok) {
            throw new Error('Ошибка загрузки дубликатов');
        }

        const data = await response.json();
        const modal = new bootstrap.Modal(document.getElementById('duplicatesModal'));
        const listContainer = document.getElementById('duplicates-list');

        if (data.success && data.duplicates.length > 0) {
            let html = '<div class="list-group">';

            data.duplicates.forEach(duplicate => {
                const ids = duplicate.ids.split(',');
                const addressList = duplicate.addresses.split('|');

                html += `
                    <div class="list-group-item">
                        <h6 class="mb-2">${addressList[0]} (повторений ${duplicate.count})</h6>
                        <div class="mb-3">
                `;

                ids.forEach((id, index) => {
                    const address = addressList[index] || addressList[0]; // fallback на случай несоответствия
                    html += `
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div>
                                <strong>ID:</strong> ${id} <br>
                                <small class="text-muted">${address}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-duplicate-btn"
                                    data-address-id="${id}">
                                <i class="bi bi-trash me-1"></i>Удалить
                            </button>
                        </div>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            listContainer.innerHTML = html;

            // Добавляем обработчики для кнопок удаления
            document.querySelectorAll('.delete-duplicate-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const addressId = this.getAttribute('data-address-id');
                    if (confirm(`Вы уверены, что хотите удалить адрес с ID ${addressId}?`)) {
                        try {
                            const deleteResponse = await fetch(`/api/addresses/${addressId}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                }
                            });

                            const deleteData = await deleteResponse.json();
                            if (deleteData.success) {
                                showAlert('Адрес успешно удален', 'success');
                                // Перезагружаем список адресов и проверяем дубликаты
                                if (window.loadAddressesPaginated) {
                                    window.loadAddressesPaginated();
                                }
                                showDuplicatesModal();
                            } else {
                                showAlert(deleteData.message || 'Ошибка при удалении адреса', 'danger');
                            }
                        } catch (error) {
                            console.error('Ошибка при удалении адреса:', error);
                            showAlert('Произошла ошибка при удалении адреса', 'danger');
                        }
                    }
                });
            });

            modal.show();
        } else {
            listContainer.innerHTML = '<div class="alert alert-info">Повторяющиеся адреса не найдены.</div>';
            modal.show();
        }
    } catch (error) {
        console.error('Ошибка при загрузке дубликатов:', error);
        showAlert('Ошибка при загрузке дубликатов адресов', 'danger');
    }
}