<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Начало работы скрипта...\n";

// 1. Загрузка JSON
$jsonPath = base_path('data/excel/Installation-panels-2025.json');
if (!file_exists($jsonPath)) die("Ошибка: Файл JSON не найден\n");

$jsonData = json_decode(file_get_contents($jsonPath), true);
$addressToOrgMap = [];

function normalizeAddress($city, $street, $house) {
    $parts = [$city, $street, $house];
    $str = implode(' ', $parts);
    $str = mb_strtolower($str);
    // Удаляем типы адресных объектов
    $remove = ['город', 'г.', 'улица', 'ул.', 'проспект', 'пр-т', 'пр.', 'переулок', 'пер.', 'дом', 'д.', 'корпус', 'корп.', 'к.', 'строение', 'стр.', 'владение', 'вл.', 'литера', 'лит.'];
    $str = str_replace($remove, ' ', $str);
    // Удаляем всё, кроме букв, цифр и пробелов
    $str = preg_replace('/[^\w\d\s]/u', '', $str);
    // Убираем лишние пробелы
    $str = preg_replace('/\s+/', ' ', $str);
    return trim($str);
}

function normalizeOrgName($name) {
    $name = mb_strtolower(trim($name));
    $name = preg_replace('/№\s+/', '№', $name); // "№ 1" -> "№1"
    // $name = str_replace('"', '', $name); // Можно убрать кавычки если нужно
    return $name;
}

echo "Загрузка и индексация JSON...\n";
foreach ($jsonData as $item) {
    if (empty($item['gbou']) || empty($item['city']) || empty($item['street']) || empty($item['house'])) continue;
    
    $key = normalizeAddress($item['city'], $item['street'], $item['house']);
    // Сохраняем организацию по ключу адреса
    // Если по одному адресу несколько организаций, перезапишется последней (для школ обычно 1 к 1)
    $addressToOrgMap[$key] = $item['gbou']; 
}

// 2. Получение заявок
echo "Получение заявок из БД...\n";
$requests = DB::select(" 
    SELECT 
        r.id as request_id,
        r.number,
        r.client_id,
        c.organization as current_org,
        ci.name as city,
        a.street,
        a.houses
    FROM requests r
    JOIN clients c ON r.client_id = c.id
    JOIN request_addresses ra ON r.id = ra.request_id
    JOIN addresses a ON ra.address_id = a.id
    JOIN cities ci ON a.city_id = ci.id
");

$processed = 0;
$updated = 0;
$skippedMatch = 0;
$notFoundInJson = 0;
$orgNotFoundInDb = 0;

echo "Обработка " . count($requests) . " заявок...\n";

foreach ($requests as $req) {
    $processed++;
    
    $reqAddressKey = normalizeAddress($req->city, $req->street, $req->houses);
    
    if (!isset($addressToOrgMap[$reqAddressKey])) {
        $notFoundInJson++;
        continue;
    }

    $jsonOrgName = $addressToOrgMap[$reqAddressKey];
    $currentOrgName = $req->current_org;

    // Сравниваем организации
    if (normalizeOrgName($jsonOrgName) === normalizeOrgName($currentOrgName)) {
        $skippedMatch++;
        continue;
    }

    // Если не совпадают, ищем правильную организацию в таблице clients
    // Ищем точное совпадение или через like? Задание: "по полю organization таблицы clients"
    // Попробуем точный поиск сначала, потом может быть очищенный
    
    $newClient = DB::table('clients')
        ->where('organization', $jsonOrgName)
        ->orWhere('organization', 'ILIKE', $jsonOrgName) // Попробуем регистронезависимый поиск
        ->first();

    if (!$newClient) {
        // Попробуем найти по нормализованному имени, если в базе есть "ГБОУ Школа №123", а в JSON "ГБОУ Школа № 123"
        // Это сложнее сделать одним запросом.
        // Пока оставим простой поиск.
        $orgNotFoundInDb++;
        // echo "Организация из JSON не найдена в clients: '$jsonOrgName' (Адрес: {$req->city}, {$req->street}, {$req->houses})\n";
        continue;
    }

    // Обновляем client_id
    DB::table('requests')
        ->where('id', $req->request_id)
        ->update(['client_id' => $newClient->id]);
    
    $updated++;
    echo "Заявка #{$req->number}: client_id изменен с {$req->client_id} ({$currentOrgName}) на {$newClient->id} ({$newClient->organization})\n";
}

echo "\nИтоги:\n";
echo "Всего обработано: $processed\n";
echo "Адрес не найден в JSON: $notFoundInJson\n";
echo "Организации совпали (нет действий): $skippedMatch\n";
echo "Организация из JSON не найдена в clients: $orgNotFoundInDb\n";
echo "Обновлено заявок: $updated\n";