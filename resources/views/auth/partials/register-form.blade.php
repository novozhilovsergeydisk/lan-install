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
               value="{{ old('email') }}" required>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Пароль:</label>
        <input type="password" name="password" id="password"
               class="form-control"
               placeholder="Введите пароль"
               required>
    </div>

    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Подтвердите пароль:</label>
        <input type="password" name="password_confirmation" id="password_confirmation"
               class="form-control"
               placeholder="Подтвердите пароль"
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
        
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        })
        .then(response => response.json())
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
                const tbody = document.querySelector('#users table tbody');
                if (tbody) {
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
                        <td>${data.user.id}</td>
                        <td>${data.user.name}</td>
                        <td>${data.user.email}</td>
                        <td>${data.user.created_at}</td>
                    `;
                    // Вставляем новую строку в начало таблицы
                    tbody.insertBefore(newRow, tbody.firstChild);
                }
                
                // Прокручиваем к верху таблицы
                const usersTable = document.querySelector('#users .card');
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
            // Восстанавливаем кнопку
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        });
    });
});
</script>
@endpush
