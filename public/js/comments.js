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
        const comments = await fetchData(`/api/requests/${requestId}/comments`);
        if (comments.length === 0) {
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
        container.innerHTML = html;
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
