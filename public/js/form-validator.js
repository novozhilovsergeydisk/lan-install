/**
 * Валидирует форму на стороне клиента
 * @param {HTMLFormElement} form - Элемент формы
 * @returns {{isValid: boolean, errors: Array<string>}} - Результат валидации
 */
function validateForm(form) {
    const errors = [];
    let isValid = true;

    // Проверяем обязательные поля
    const requiredFields = form.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            // Пробуем получить название поля из data-field-name, затем из label, и только потом из name
            let fieldName = field.getAttribute('data-field-name');
            
            if (!fieldName && field.labels && field.labels.length > 0) {
                fieldName = field.labels[0].textContent.replace(':', '').trim();
            }
            
            if (!fieldName) {
                // Если поле name в camelCase, добавляем пробелы перед заглавными буквами
                fieldName = field.name.replace(/([A-Z])/g, ' $1').trim();
                // Делаем первую букву заглавной
                fieldName = fieldName.charAt(0).toUpperCase() + fieldName.slice(1);
            }
            errors.push(`Поле "${fieldName}" обязательно для заполнения`);
            isValid = false;
        }
    });

    // Проверяем email
    const emailField = form.querySelector('input[type="email"]');
    if (emailField && emailField.value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value)) {
            errors.push('Укажите корректный email');
            isValid = false;
        }
    }

    // Проверяем совпадение паролей
    const password = form.querySelector('input[name="password"]');
    const passwordConfirm = form.querySelector('input[name="password_confirmation"]');
    if (password && passwordConfirm && password.value !== passwordConfirm.value) {
        errors.push('Пароли не совпадают');
        isValid = false;
    }

    return { isValid, errors };
}

/**
 * Отображает ошибки валидации в интерфейсе
 * @param {Array<string>} errors - Массив сообщений об ошибках
 * @param {HTMLElement} container - Контейнер для отображения ошибок
 */
function displayValidationErrors(errors, container) {
    if (!container || !errors || !Array.isArray(errors) || errors.length === 0) {
        if (container) {
            container.innerHTML = '';
        }
        return;
    }
    
    const errorHtml = `
        <div class="alert alert-danger">
            <h6 class="alert-heading">Пожалуйста, заполните обязательные поля:</h6>
            <ul class="mb-0">
                ${errors.map(error => `<li>${error}</li>`).join('')}
            </ul>
        </div>
    `;
    
    container.innerHTML = errorHtml;
    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Экспортируем функции для использования в других модулях
window.formValidator = {
    validate: validateForm,
    displayErrors: displayValidationErrors
};
