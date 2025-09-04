/**
 * Валидатор номера дома
 * Допустимые форматы:
 * - 8
 * - 8A
 * - 8, корпус 2
 * - 8, корпус 2, строение 1
 * - 8A, корпус 2, строение 1
 */

class HouseNumberValidator {
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = {
            errorClass: 'is-invalid',
            errorMessage: 'веедите номер дома в соответствии с форматом!',
            ...options
        };
        
        this.errorElement = null;
        this.init();
    }

    init() {
        // Создаем элемент для отображения ошибки
        this.errorElement = document.createElement('div');
        this.errorElement.className = 'invalid-feedback';
        this.input.parentNode.appendChild(this.errorElement);
        
        // Добавляем обработчик события input
        this.input.addEventListener('input', this.validate.bind(this));
        this.input.addEventListener('blur', this.validate.bind(this));
        
        // Добавляем подсказку
        this.input.setAttribute('title', this.options.errorMessage);
        this.input.setAttribute('data-bs-toggle', 'tooltip');
        this.input.setAttribute('data-bs-placement', 'top');
    }

    /**
     * Проверяет валидность введенного значения
     * @returns {boolean} true если валидно, иначе false
     */
    validate() {
        const value = this.input.value.trim();
        
        // Регулярное выражение для проверки формата
        // 1. Основной номер дома (число с необязательной буквой)
        // 2. Необязательная часть с корпусом и/или строением
        //    - Может быть только корпус
        //    - Может быть только строение
        //    - Могут быть оба (корпус, затем строение)
        const pattern = /^\d+[A-Za-zА-Яа-я]*(?:,\s*(?:корпус\s+\d+[A-Za-zА-Яа-я]*(?:,\s*строение\s+\d+[A-Za-zА-Яа-я]*)?|строение\s+\d+[A-Za-zА-Яа-я]*))?$/;
        
        const isValid = pattern.test(value);
        
        if (value === '') {
            this.clearError();
            return true;
        }
        
        if (!isValid) {
            this.showError();
            return false;
        }
        
        this.clearError();
        return true;
    }
    
    showError() {
        this.input.classList.add(this.options.errorClass);
        this.errorElement.textContent = this.options.errorMessage;
    }
    
    clearError() {
        this.input.classList.remove(this.options.errorClass);
        this.errorElement.textContent = '';
    }
    
    /**
     * Статический метод для быстрой инициализации валидатора
     * @param {string} selector - CSS селектор поля ввода
     * @param {object} options - опции валидатора
     * @returns {HouseNumberValidator} экземпляр валидатора
     */
    static init(selector, options) {
        const elements = document.querySelectorAll(selector);
        return Array.from(elements).map(el => new HouseNumberValidator(el, options));
    }
}

// Экспортируем класс для использования в других модулях
export default HouseNumberValidator;

// Для обратной совместимости с CommonJS
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HouseNumberValidator;
} else {
    window.HouseNumberValidator = HouseNumberValidator;
}
