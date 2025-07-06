@if ($errors->any())
    <div class="alert alert-danger mb-3" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form id="registerForm" method="POST" action="{{ route('register') }}" class="mb-4">
    @csrf
    <div id="formMessages"></div>

    <div class="mb-3">
        <label for="name" class="form-label">Имя:</label>
        <input type="text" name="name" id="name"
               class="form-control"
               placeholder="Введите имя"
               value="{{ old('name') }}" required autofocus>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email:</label>
        <input type="email" name="email" id="email"
               class="form-control"
               placeholder="Введите email"
               autocomplete="username"
               value="{{ old('email') }}" required>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Пароль:</label>
        <input type="password" name="password" id="password"
               class="form-control"
               placeholder="Введите пароль"
               autocomplete="new-password"
               required>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Подтвердите пароль:</label>
        <input type="password" name="password_confirmation" id="password_confirmation"
               class="form-control"
               placeholder="Подтвердите пароль"
               autocomplete="new-password"
               required>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-person-plus me-1"></i> Зарегистрировать пользователя
        </button>
    </div>

</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const messagesContainer = document.getElementById('formMessages');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.innerHTML;
        
        // Показываем индикатор загрузки
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Регистрация...';
        
        // Очищаем предыдущие сообщения
        messagesContainer.innerHTML = '';
        
        // Добавляем заголовок Accept для JSON-ответа
        const headers = new Headers();
        headers.append('X-Requested-With', 'XMLHttpRequest');
        headers.append('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        headers.append('Accept', 'application/json');
        
        // Отправляем форму с правильными заголовками
        fetch(form.action, {
            method: 'POST',
            headers: headers,
            body: formData,
            credentials: 'same-origin'
        })
        .then(async response => {
            const data = await response.json();
            
            if (!response.ok) {
                // Если есть ошибки валидации, выводим их
                if (data.errors) {
                    let errorHtml = '<div class="alert alert-danger"><ul class="mb-0">';
                    Object.values(data.errors).forEach(error => {
                        errorHtml += `<li>${error[0]}</li>`;
                    });
                    errorHtml += '</ul></div>';
                    messagesContainer.innerHTML = errorHtml;
                } else if (data.message) {
                    // Если есть сообщение об ошибке
                    messagesContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                } else {
                    // Общее сообщение об ошибке
                    messagesContainer.innerHTML = '<div class="alert alert-danger">Произошла ошибка при отправке формы. Пожалуйста, проверьте введенные данные и попробуйте снова.</div>';
                }
                // Возвращаем null вместо выбрасывания ошибки, чтобы не попадать в блок catch
                return { success: false };
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                // Очищаем форму
                form.reset();
                
                // Показываем сообщение об успехе
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success';
                successAlert.textContent = data.message;
                messagesContainer.appendChild(successAlert);
                
                // Добавляем нового пользователя в таблицу
                const tbody = document.querySelector('table.table-hover.users-table tbody');
                if (tbody) {
                    const newRow = document.createElement('tr');
                    const createdDate = new Date(data.user.created_at);
                    const formattedDate = createdDate.toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    newRow.innerHTML = `
                        <td>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary select-user" 
                                    data-user-id="${data.user.id}" 
                                    data-bs-toggle="tooltip" 
                                    title="Выбрать пользователя (ID: ${data.user.id})">
                                <i class="bi bi-person-plus"></i> ${data.user.id}
                            </button>
                        </td>
                        <td>${data.user.name || ''}</td>
                        <td>${data.user.email || ''}</td>
                        <td>${formattedDate}</td>
                    `;
                    
                    // Вставляем новую строку в начало таблицы
                    tbody.insertBefore(newRow, tbody.firstChild);
                    
                    // Инициализируем тултип для новой кнопки
                    if (window.bootstrap) {
                        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                    
                    // Добавляем обработчик события для новой кнопки
                    const newButton = newRow.querySelector('.select-user');
                    if (newButton) {
                        newButton.addEventListener('click', function() {
                            const userId = this.getAttribute('data-user-id');
                            const userIdInput = document.getElementById('userIdInput');
                            if (userIdInput) {
                                userIdInput.value = userId;
                                
                                // Показываем уведомление
                                const toast = new bootstrap.Toast(document.getElementById('userSelectedToast'));
                                toast.show();
                                
                                // Прокручиваем к форме
                                document.getElementById('employeesFormContainer').scrollIntoView({ behavior: 'smooth' });
                            }
                        });
                    }
                }
                
                // Прокручиваем к верху таблицы
                const usersTable = document.querySelector('.table-hover.users-table');
                if (usersTable) {
                    usersTable.scrollIntoView({ behavior: 'smooth' });
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorAlert = document.createElement('div');
            errorAlert.className = 'alert alert-danger';
            errorAlert.textContent = 'Произошла ошибка при регистрации. Пожалуйста, попробуйте еще раз.';
            messagesContainer.appendChild(errorAlert);
        })
        .finally(() => {
            // Восстанавливаем кнопку только если не было успешной отправки
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        });
    });
});
</script>
@endpush
