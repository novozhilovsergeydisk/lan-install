// Класс для управления экспортом сотрудников в Excel
class EmployeeExporter {
    constructor() {
        this.exportBtn = document.getElementById('exportEmployeesBtn');
        this.modal = document.getElementById('exportEmployeesModal');
        this.selectAllCheckbox = document.getElementById('selectAllEmployees');
        this.employeesListContainer = document.getElementById('employeesList');
    }

    // Инициализация всех обработчиков событий
    init() {
        if (!this.exportBtn || !this.modal) {
            console.warn('Не найдены элементы для экспорта сотрудников');
            return;
        }

        this.bindModalEvents();
        this.bindSelectAllEvent();
        this.bindExportEvent();
    }

    // Привязка событий модального окна
    bindModalEvents() {
        this.modal.addEventListener('show.bs.modal', () => this.loadEmployees());
    }

    // Привязка события "Выбрать всех"
    bindSelectAllEvent() {
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.addEventListener('change', () => this.toggleAllCheckboxes());
        }
    }

    // Привязка события экспорта
    bindExportEvent() {
        this.exportBtn.addEventListener('click', () => this.handleExport());
    }

    // Загрузка списка сотрудников
    async loadEmployees() {
        if (!this.employeesListContainer) {
            console.error('Не найден контейнер для списка сотрудников');
            return;
        }

        try {
            this.showLoadingState();
            const employees = await this.fetchEmployees();
            this.renderEmployees(employees);
        } catch (error) {
            console.error('Ошибка при загрузке сотрудников:', error);
            this.showErrorState('Ошибка при загрузке списка сотрудников');
        }
    }

    // Получение данных сотрудников с сервера
    async fetchEmployees() {
        const response = await fetch('/api/employees');
        const data = await response.json();

        if (!data || !Array.isArray(data)) {
            throw new Error('Неверный формат данных');
        }

        return data;
    }

    // Отображение состояния загрузки
    showLoadingState() {
        this.employeesListContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Загрузка...</div>';
    }

    // Отображение состояния ошибки
    showErrorState(message) {
        this.employeesListContainer.innerHTML = `<div class="text-danger">${message}</div>`;
    }

    // Рендеринг списка сотрудников
    renderEmployees(employees) {
        const html = employees.map(employee => `
            <div class="form-check">
                <input class="form-check-input employee-checkbox" type="checkbox" value="${employee.id}" id="employee_${employee.id}">
                <label class="form-check-label" for="employee_${employee.id}">
                    ${employee.fio}
                </label>
            </div>
        `).join('');

        this.employeesListContainer.innerHTML = html;
    }

    // Переключение всех чекбоксов
    toggleAllCheckboxes() {
        const checkboxes = this.modal.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.selectAllCheckbox.checked;
        });
    }

    // Обработка экспорта
    async handleExport() {
        const selectedIds = this.getSelectedEmployeeIds();

        if (selectedIds.length === 0) {
            showAlert('Пожалуйста, выберите хотя бы одного сотрудника', 'warning');
            return;
        }

        try {
            await this.submitExportForm(selectedIds);
            this.closeModal();
        } catch (error) {
            console.error('Ошибка при экспорте:', error);
            showAlert('Произошла ошибка при экспорте', 'danger');
        }
    }

    // Получение ID выбранных сотрудников
    getSelectedEmployeeIds() {
        const selectedCheckboxes = this.modal.querySelectorAll('.employee-checkbox:checked');
        return Array.from(selectedCheckboxes).map(cb => parseInt(cb.value));
    }

    // Отправка формы экспорта
    async submitExportForm(employeeIds) {
        const form = this.createExportForm(employeeIds);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    // Создание формы для экспорта
    createExportForm(employeeIds) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/employee/export';
        form.style.display = 'none';

        // CSRF токен
        const csrfToken = this.getCsrfToken();
        if (csrfToken) {
            form.appendChild(this.createHiddenInput('_token', csrfToken));
        }

        // IDs сотрудников
        employeeIds.forEach(id => {
            form.appendChild(this.createHiddenInput('employee_ids[]', id));
        });

        return form;
    }

    // Получение CSRF токена
    getCsrfToken() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        return csrfMeta ? csrfMeta.getAttribute('content') : null;
    }

    // Создание скрытого поля ввода
    createHiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    // Закрытие модального окна
    closeModal() {
        const modalInstance = bootstrap.Modal.getInstance(this.modal);
        if (modalInstance) {
            modalInstance.hide();
        }
    }
}

// Инициализация при загрузке DOM
document.addEventListener('DOMContentLoaded', function() {
    const exporter = new EmployeeExporter();
    exporter.init();
});