<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Очистка временных архивов раз в месяц (удаляем файлы старше 30 дней)
Schedule::exec('find ' . storage_path('app/temp') . ' -type f -mtime +30 -delete')
    ->monthly()
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// Живое обновление оборудования (склад) у открытых сегодняшних заявок — раз в час.
Schedule::command('wms:refresh-equipment')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/wms-equipment.log'));

// Догеокодирование адресов без координат (например, добавленных вручную без указания
// широты/долготы) — раз в час. --limit подстраховывает от долгого прогона, если
// вдруг накопится большой залежавшийся хвост.
Schedule::command('addresses:geocode-missing --limit=100')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/geocode-missing.log'));

