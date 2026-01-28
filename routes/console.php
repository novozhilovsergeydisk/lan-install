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

