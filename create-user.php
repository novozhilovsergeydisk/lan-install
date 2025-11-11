<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Загрузка автозагрузки Laravel
require __DIR__.'/vendor/autoload.php';

// Создание приложения Laravel
$app = require_once __DIR__.'/bootstrap/app.php';

// Получение ядра
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Загрузка сервисов
$kernel->bootstrap();

// Данные пользователя
$name = 'Тестовый Пользователь';
$email = 'test@example.com';
$password = 'password123';

// Создание пользователя
$user = User::create([
    'name' => $name,
    'email' => $email,
    'password' => Hash::make($password),
]);

echo "✅ Пользователь создан!\n";
echo "Имя: $name\n";
echo "Email: $email\n";
echo "Пароль: $password\n";
echo "Можете войти по адресу: https://lan-install.online/login\n";
