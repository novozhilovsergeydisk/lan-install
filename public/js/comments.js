// comments.js

import { showAlert, fetchData, postData } from './utils.js';

/**
 * Загружает комментарии к заявке
 * @param {number} requestId
 * @returns {Promise<void>}
 */
async function loadComments(requestId) {
    const container = document.getElementById('commentsContainer');
    if (!container) return;

    container.innerHTML = `
        <div class="text-center my-4">
            <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Загрузка...</span></div>
            <p class="mt-2">Загрузка комментариев...</p>
        </div>
    `;

    try {
        const response = await fetchData(`/api/requests/${requestId}/comments`);
        let comments = [];
        let meta = {};

        if (Array.isArray(response)) {
            comments = response;
        } else {
            comments = response.comments || [];
            meta = response.meta || {};
        }

        if (comments.length === 0 && !meta.address_history_url) {
            container.innerHTML = '<div class="text-muted text-center py-4">Нет комментариев</div>';
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        comments.forEach(comment => {
            const date = new Date(comment.created_at);
            const formattedDate = date.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="me-3">
                            <p class="mb-1">${comment.comment}</p>
                            <small class="text-muted">${formattedDate}</small>
                        </div>
                    </div>
                </div>`;
        });
        html += '</div>';

        if (meta.address_history_url) {
            html += `
                <div class="mt-3 pt-3 border-top">
                    <label class="form-label small text-muted">История заявок по адресу:</label>
                    <div class="d-flex gap-2">
                        <input type="hidden" value="${meta.address_history_url}" id="historyUrlInput">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="copyHistoryUrlBtn">
                            <i class="bi bi-clipboard"></i> Скопировать ссылку
                        </button>
                        <a href="${meta.address_history_url}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right"></i> Открыть
                        </a>
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;

        if (meta.address_history_url) {
            const copyBtn = container.querySelector('#copyHistoryUrlBtn');
            const urlInput = container.querySelector('#historyUrlInput');
            if (copyBtn && urlInput) {
                copyBtn.addEventListener('click', () => {
                    navigator.clipboard.writeText(urlInput.value).then(() => {
                        if (window.utils && window.utils.showAlert) {
                            window.utils.showAlert('Ссылка скопирована!');
                        } else {
                            alert('Ссылка скопирована!');
                        }
                    }).catch(err => {
                        console.error('Ошибка копирования:', err);
                        alert('Не удалось скопировать ссылку');
                    });
                });
            }
        }
    } catch (error) {
        container.innerHTML = `<div class="alert alert-danger">Ошибка загрузки комментариев</div>`;
    }
}

/**
 * Обновляет количество комментариев
 * @param {number} requestId
 */
async function updateCommentsBadge(requestId) {
    try {
        const data = await fetchData(`/api/requests/${requestId}/comments/count`);
        const commentCount = data.count || 0;
        const requestRow = document.querySelector(`tr[data-request-id="${requestId}"]`);

        if (!requestRow) return;

        const buttons = requestRow.querySelectorAll('button.view-comments-btn');
        buttons.forEach(button => {
            let badge = button.querySelector('.badge');
            if (commentCount > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge bg-primary rounded-pill ms-1';
                    const icon = button.querySelector('i');
                    if (icon) icon.insertAdjacentElement('afterend', badge);
                    else button.appendChild(badge);
                }
                badge.textContent = commentCount;
            } else if (badge) {
                badge.remove();
            }
        });
    } catch (error) {
        console.error('Ошибка обновления бейджа:', error);
    }
}

export { loadComments, updateCommentsBadge };
