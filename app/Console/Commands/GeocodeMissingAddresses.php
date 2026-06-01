<?php

namespace App\Console\Commands;

use App\Services\GeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeocodeMissingAddresses extends Command
{
    protected $signature = 'addresses:geocode-missing 
                            {--limit= : Максимум адресов для обработки}
                            {--dry-run : Не сохранять, только показать что будет сделано}';

    protected $description = 'Догеокодирует адреса без координат через DaData';

    public function handle(GeocodingService $geocoder): int
    {
        $query = DB::table('addresses')
            ->select('addresses.id', 'addresses.street', 'addresses.houses', 'cities.name as city_name')
            ->leftJoin('cities', 'addresses.city_id', '=', 'cities.id')
            ->where(function ($q) {
                $q->whereNull('addresses.latitude')
                    ->orWhereNull('addresses.longitude');
            });

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $addresses = $query->get();
        $total = $addresses->count();

        if ($total === 0) {
            $this->info('Нет адресов без координат. Всё уже геокодировано.');
            return self::SUCCESS;
        }

        $this->info("Найдено адресов без координат: {$total}");

        if ($this->option('dry-run')) {
            $this->warn('РЕЖИМ DRY-RUN: изменения в БД не сохраняются.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $success = 0;
        $failed = 0;
        $failedAddresses = [];

        foreach ($addresses as $address) {
            $fullAddress = trim(
                ($address->city_name ?? '') . ', ' .
                ($address->street ?? '') . ', ' .
                ($address->houses ?? '')
            );
            $fullAddress = trim($fullAddress, ', ');

            $coords = $geocoder->geocode($fullAddress);

            if ($coords) {
                if (!$this->option('dry-run')) {
                    DB::table('addresses')
                        ->where('id', $address->id)
                        ->update([
                            'latitude' => $coords['latitude'],
                            'longitude' => $coords['longitude'],
                        ]);
                }
                $success++;
            } else {
                $failed++;
                $failedAddresses[] = "ID {$address->id}: {$fullAddress}";
            }

            $bar->advance();
            usleep(200000); // 200мс пауза между запросами
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Успешно: {$success}");
        $this->info("❌ Не удалось: {$failed}");
        $this->info("📊 Всего: {$total}");

        if (!empty($failedAddresses)) {
            $this->newLine();
            $this->warn('Адреса, которые не удалось геокодировать:');
            foreach (array_slice($failedAddresses, 0, 20) as $addr) {
                $this->line('  - ' . $addr);
            }
            if (count($failedAddresses) > 20) {
                $this->line('  ... и ещё ' . (count($failedAddresses) - 20));
            }
        }

        return self::SUCCESS;
    }
}
