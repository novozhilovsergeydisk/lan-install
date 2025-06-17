<?php

// Параметры подключения
$host = 'localhost';
$dbname = 'fursa';
$user = 'postgres'; // или твой пользователь, если не postgres
$password = 'postgres_fursa_12345'; // замени на свой пароль, если он есть

try {
    // Подключение к PostgreSQL через PDO
    $dsn = "pgsql:host=$host;dbname=$dbname;";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO($dsn, $user, $password, $options);
    echo "✅ Успешно подключились к базе данных '$dbname'\n";

    // Моковые данные для клиентов
    $clients = [
        ['fio' => 'Иванов Иван Иванович', 'email' => 'ivanov@example.com', 'phone' => '+79001234567'],
        ['fio' => 'Петров Петр Петрович', 'email' => 'petrov@example.com', 'phone' => '+79007654321'],
        ['fio' => 'Сидорова Ольга Николаевна', 'email' => 'sidorova@example.com', 'phone' => '+79009876543'],
    ];

    // SQL-запрос
    $sql = "INSERT INTO clients (fio, email, phone) VALUES (:fio, :email, :phone)";

    foreach ($clients as $client) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':fio' => $client['fio'],
            ':email' => $client['email'],
            ':phone' => $client['phone']
        ]);
        echo "Добавлен клиент: {$client['fio']} <{$client['email']}>\n";
    }

} catch (PDOException $e) {
    die("❌ Ошибка подключения или выполнения запроса: " . $e->getMessage());
}
