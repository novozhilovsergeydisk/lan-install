// public/js/utils.js

/**
 * @typedef {'success'|'danger'|'warning'} AlertType
 */

function showModal(id) {
    const el = getElement(id);
    if (el) {
        const modal = new bootstrap.Modal(el);
        modal.show();
    }
}

function getElement(id) {
    try {
        const el = document.getElementById(id);
        
        if (!el) {
            console.warn(`Элемент с id "${id}" не найден`);
            return null;
        }
        return el;
    } catch (error) {
        console.error(`Ошибка при получении элемента "${id}":`, error);
        return null;
    }
}

function setValue(id, value) {
    try {
        const el = getElement(id);
        if (!el) return;

        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
            el.value = value;
        } else {
            console.warn(`Элемент "${id}" не поддерживает установку value`);
        }
    } catch (error) {
        console.error(`Ошибка при установке значения для "${id}":`, error);
    }
}

function getValue(id) {
    try {
        const el = getElement(id);
        if (!el) return '';

        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
            return el.value || '';
        } else {
            console.warn(`Элемент "${id}" не поддерживает получение value`);
            return '';
        }
    } catch (error) {
        console.error(`Ошибка при получении значения для "${id}":`, error);
        return '';
    }
}

/**
 * Показывает пользовательское уведомление
 * @param {string} message Текст сообщения
 * @param {AlertType} [type='success'] Тип уведомления
 */
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '1060';
    alertDiv.setAttribute('role', 'alert');
    alertDiv.style.minWidth = '300px';
    alertDiv.style.textAlign = 'center';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);

    // Auto-close after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode === document.body) {
            const bsAlert = bootstrap.Alert.getInstance(alertDiv) || new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }
    }, 5000);
}

/**
 * Получает данные из API
 * @param {string} url
 * @returns {Promise<any>}
 */
async function fetchData(url) {
    try {
        const response = await fetch(url, {
            credentials: 'same-origin'
        });
        if (!response.ok) throw new Error(`Ошибка сети: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Ошибка при запросе:', error);
        showAlert(`Ошибка загрузки данных: ${error.message}`, 'danger');
        throw error;
    }
}

/**
 * Формирует превью комментария из первых N слов и экранирует его для безопасного вывода.
 * Возвращает объект: { html: string, ellipsis: string }
 * Логика максимально приближена к PHP: StringHelper::makeEscapedPreview()
 *
 * @param {string|null|undefined} comment
 * @param {number} [wordLimit=4]
 * @returns {{html: string, ellipsis: string}}
 */
function makeEscapedPreview(comment, wordLimit = 4) {
    const input = comment ?? '';

    // Декодируем HTML-сущности
    const decoded = decodeEntities(input);
    // <br> -> пробел
    const normalized = decoded.replace(/<br\s*\/?>(\s)*/gi, ' ');
    // Удаляем теги (для подсчёта слов)
    const textOnly = stripTags(normalized).trim();

    const words = textOnly ? textOnly.split(/\s+/u) : [];
    const limit = Math.max(0, Number.isFinite(wordLimit) ? wordLimit : 0);
    const snippetWords = words.slice(0, limit);
    const needsEllipsis = words.length > limit;
    const snippetText = snippetWords.join(' ');

    // Линкуем URL'ы, экранируя НЕ-URL части
    const urlRegex = /((https?:\/\/|www\.)[^\s<]+)/giu;
    let result = '';
    let last = 0;
    let m;
    while ((m = urlRegex.exec(snippetText)) !== null) {
        const url = m[1];
        const start = m.index;
        // Текст до URL — экранируем
        result += escapeHtml(snippetText.slice(last, start));
        // href с протоколом
        const href = /^https?:/i.test(url) ? url : 'http://' + url;
        result += '<a href="' + escapeHtml(href) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(url) + '</a>';
        last = start + url.length;
    }
    // Хвост
    result += escapeHtml(snippetText.slice(last));

    return { html: result, ellipsis: needsEllipsis ? '...' : '' };

    // --- helpers ---
    function decodeEntities(str) {
        // Используем DOM для корректного декодирования сущностей
        const ta = document.createElement('textarea');
        ta.innerHTML = str;
        return ta.value;
    }
    function stripTags(str) {
        return str.replace(/<[^>]*>/g, '');
    }
    function escapeHtml(str) {
        return str
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
}

/**
 * Отправляет данные в API
 * @param {string} url
 * @param {Object} body
 * @returns {Promise<any>}
 */
async function postData(url, body) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body),
            credentials: 'same-origin'
        });

        const responseData = await response.json();

        if (!response.ok) {
            if (response.status === 422) {
                // Проверяем сообщение об ошибке от сервера
                if (responseData.message && responseData.message.includes('Сотрудник не найден')) {
                    showAlert('Необходимо создать сотрудника для данного пользователя', 'danger');
                    throw new Error('EMPLOYEE_NOT_FOUND');
                }
                // Проверяем ошибки валидации для operator_id
                if (responseData.errors && responseData.errors.operator_id) {
                    const errorMessage = responseData.errors.operator_id[0];
                    if (errorMessage.includes('employee') || errorMessage.includes('not found')) {
                        showAlert('Необходимо создать сотрудника для данного пользователя', 'danger');
                        throw new Error('EMPLOYEE_NOT_FOUND');
                    }
                }
                // Для других ошибок валидации показываем первое сообщение об ошибке
                const firstError = responseData.message || Object.values(responseData.errors || {}).flat()[0];
                showAlert(firstError || 'Ошибка валидации данных', 'danger');
                throw new Error(firstError || 'Ошибка валидации данных');
            }
            // Обработка других HTTP ошибок
            throw new Error(responseData.message || `Ошибка сервера: ${response.status}`);
        }

        // console.log('responseData', responseData);

        return responseData;
    } catch (error) {
        console.error('Ошибка отправки:', error);
        if (error.message !== 'EMPLOYEE_NOT_FOUND') {
            showAlert(error.message || ('Произошла ошибка при отправке данных: ' + (error && error.message ? error.message : '')), 'danger');
        }
        throw error;
    }
}

/**
 * Универсальная функция для выполнения HTTP-запросов
 * @param {string} url
 * @param {Object} options
 * @returns {Promise<any>}
 */
async function sendRequest(url, options) {
    try {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const headers = {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        };

        const defaultOptions = {
            headers,
            credentials: 'same-origin',
            ...options
        };

        const response = await fetch(url, defaultOptions);
        if (!response.ok) throw new Error(`Ошибка сети: ${response.status}`);
        return await response.json();
    } catch (error) {
        console.error('Ошибка запроса:', error);
        showAlert(`Ошибка запроса: ${error.message}`, 'danger');
        throw error;
    }
}

// Преобразует текст: оборачивает plain-URL в <a>, не трогая существующие <a>
function linkifyPreservingAnchors(input) {
    if (!input) return '';
    const anchorRegex = /<a\b[^>]*>[\s\S]*?<\/a>/gi;
    let lastIndex = 0;
    let result = '';
    let match;
    while ((match = anchorRegex.exec(input)) !== null) {
        const before = input.slice(lastIndex, match.index);
        result += linkifyPlain(before);
        result += match[0];
        lastIndex = match.index + match[0].length;
    }
    result += linkifyPlain(input.slice(lastIndex));
    return result;

    function linkifyPlain(text) {
        if (!text) return '';
        const urlRegex = /(?:https?:\/\/[^\s<]+|www\.[^\s<]+)/gi;
        return text.replace(urlRegex, (url) => {
            const href = url.startsWith('http') ? url : 'http://' + url;
            return `<a href="${href}" target="_blank" rel="noopener noreferrer">${url}</a>`;
        });
    }
}

// Вспомогательная функция для валидации поля
function validateRequiredField(field, isRequired = false) {
    if (isRequired) {
        if (field.value.trim() === '' || field.value === '') {
            field.classList.add('is-invalid');
            field.classList.remove('is-valid');
            return false;
        } else {
            field.classList.add('is-valid');
            field.classList.remove('is-invalid');
            return true;
        }
    } else {
        field.classList.remove('is-invalid', 'is-valid');
        return true;
    }
}

/**
 * Определяет контрастный цвет текста (черный или белый) на основе цвета фона
 * @param {string} hexColor - Цвет фона в формате #RRGGBB
 * @returns {string} '#000000' или '#FFFFFF'
 */
function getContrastColor(hexColor) {
    if (!hexColor || !hexColor.startsWith('#')) return '#000000';

    // Удалить #
    const color = hexColor.slice(1);

    // Преобразовать в RGB
    const r = parseInt(color.substr(0, 2), 16);
    const g = parseInt(color.substr(2, 2), 16);
    const b = parseInt(color.substr(4, 2), 16);

    // Вычислить luminance (яркость)
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    // Если luminance > 0.5, фон светлый - текст черный, иначе белый
    return luminance > 0.5 ? '#000000' : '#FFFFFF';
}

// Экспорт для модулей
export { showAlert, fetchData, postData, sendRequest, linkifyPreservingAnchors, makeEscapedPreview, showModal, getElement, setValue, getValue, validateRequiredField, getContrastColor };

// Экспорт глобально
if (typeof window !== 'undefined') {
    window.utils = {
        showAlert,
        fetchData,
        postData,
        sendRequest,
        linkifyPreservingAnchors,
        makeEscapedPreview,
        validateRequiredField,
        getContrastColor
    };
}
