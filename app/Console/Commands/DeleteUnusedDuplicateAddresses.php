<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteUnusedDuplicateAddresses extends Command
{
    protected $signature = 'addresses:delete-unused-duplicates 
                            {--execute : Реальное выполнение удаления}
                            {--limit= : Максимум групп для обработки}';

    protected $description = 'Удаляет адреса из групп-дубликатов, у которых нет ни одной заявки. По умолчанию dry-run.';

    public function handle(): int
    {
        $isExecute = $this->option('execute');
        $limit = $this->option('limit');

        $this->info('Поиск групп-дубликатов...');

        // 1. Получить группы дубликатов (как в FindDuplicateAddresses)
        $query = DB::table('addresses')
            ->select(
                'latitude', 
                'longitude', 
                DB::raw('COUNT(*) as cnt'),
                DB::raw("string_agg(id::text, ',' ORDER BY id) as ids")
            )
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('latitude', 'longitude')
            ->havingRaw('COUNT(*) > 1');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $groups = $query->get();
        $totalGroups = $groups->count();

        if ($totalGroups === 0) {
            $this->info('Группы дубликатов не найдены.');
            return self::SUCCESS;
        }

        $this->info("Найдено групп: {$totalGroups}");
        $this->newLine();
        $this->info('Анализ кандидатов...');

        // Настройка логирования
        $logFileName = 'delete-unused-duplicates-' . now()->format('Ymd-His') . '.log';
        $logFilePath = storage_path('logs/' . $logFileName);
        
        // Убедимся, что папка logs существует
        if (!is_dir(storage_path('logs'))) {
            mkdir(storage_path('logs'), 0755, true);
        }

        $logFile = fopen($logFilePath, 'w');
        if (!$logFile) {
            $this->error("Не удалось создать лог-файл: {$logFilePath}");
            return self::FAILURE;
        }

        fwrite($logFile, "ОТЧЕТ ПО УДАЛЕНИЮ НЕИСПОЛЬЗУЕМЫХ ДУБЛИКАТОВ АДРЕСОВ\n");
        fwrite($logFile, "Сгенерирован: " . now()->format('Y-m-d H:i:s') . "\n");
        fwrite($logFile, "Режим: " . ($isExecute ? "EXECUTE (Реальное удаление)" : "DRY-RUN (Только чтение)") . "\n");
        fwrite($logFile, str_repeat("=", 80) . "\n\n");

        $bar = $this->output->createProgressBar($totalGroups);
        $bar->start();

        $totalCandidates = 0;
        $totalDeleted = 0;
        $groupsBecomingNonDuplicates = 0;
        $groupsRemainingDuplicates = 0;

        foreach ($groups as $groupRow) {
            try {
                $ids = explode(',', $groupRow->ids);

                // 2a. Получить все адреса группы
                $addresses = DB::table('addresses')
                    ->select('addresses.id', 'addresses.street', 'addresses.district', 'addresses.houses', 'cities.name as city_name')
                    ->leftJoin('cities', 'addresses.city_id', '=', 'cities.id')
                    ->whereIn('addresses.id', $ids)
                    ->orderBy('addresses.id', 'asc')
                    ->get();

                // 2b. Посчитать заявки
                $requestsCounts = DB::table('request_addresses')
                    ->select('address_id', DB::raw('COUNT(*) as cnt'))
                    ->whereIn('address_id', $ids)
                    ->groupBy('address_id')
                    ->pluck('cnt', 'address_id');

                // 2c. Посчитать документы (с защитой, если таблица не существует)
                $documentsCounts = collect([]);
                try {
                    $documentsCounts = DB::table('addresses_documents')
                        ->select('address_id', DB::raw('COUNT(*) as cnt'))
                        ->whereIn('address_id', $ids)
                        ->groupBy('address_id')
                        ->pluck('cnt', 'address_id');
                } catch (\Exception $e) {
                    // Игнорируем
                }

                $candidates = [];
                $nonCandidates = [];

                // 2d. Отобрать кандидатов
                foreach ($addresses as $addr) {
                    $reqCount = $requestsCounts->get($addr->id, 0);
                    $docCount = $documentsCounts->get($addr->id, 0);

                    if ($reqCount == 0 && $docCount == 0) {
                        $candidates[] = $addr;
                    } else {
                        $nonCandidates[] = $addr;
                    }
                }

                $toDelete = [];
                $toKeep = [];

                // 2f, 2g. Решение кого оставить
                if (count($nonCandidates) === 0) {
                    // Все адреса в группе — кандидаты. Оставляем один с минимальным ID (уже отсортировано asc).
                    $toKeep[] = array_shift($candidates);
                    $toDelete = $candidates;
                } else {
                    // Есть не-кандидаты. Удаляем всех кандидатов.
                    $toKeep = $nonCandidates;
                    $toDelete = $candidates;
                }

                $toKeepIds = array_map(function($a) { return $a->id; }, $toKeep);

                // Выполняем удаление
                foreach ($toDelete as $addr) {
                    $totalCandidates++;

                    $addressString = sprintf(
                        "[%s] %s, %s, %s, %s",
                        $addr->id,
                        $addr->city_name ?? '',
                        $addr->street ?? '',
                        $addr->district ?? '',
                        $addr->houses ?? ''
                    );

                    // 3. Логирование
                    $logMsg = sprintf(
                        "[%s] Удаление ID=%d | %s | Причина: 0 заявок, 0 док-ов, дубликат (лат: %s, лон: %s) | Останутся ID: %s\n",
                        now()->format('Y-m-d H:i:s'),
                        $addr->id,
                        $addressString,
                        $groupRow->latitude,
                        $groupRow->longitude,
                        implode(',', $toKeepIds)
                    );
                    fwrite($logFile, $logMsg);

                    // 4. Реальное удаление
                    if ($isExecute) {
                        try {
                            DB::table('addresses')->where('id', $addr->id)->delete();
                            $totalDeleted++;
                            fwrite($logFile, "    -> УСПЕШНО УДАЛЕНО\n");
                        } catch (\Exception $e) {
                            fwrite($logFile, "    -> ОШИБКА УДАЛЕНИЯ: " . $e->getMessage() . "\n");
                        }
                    }
                }

                $remainingCount = count($toKeep);
                if ($remainingCount === 1) {
                    $groupsBecomingNonDuplicates++;
                } else {
                    $groupsRemainingDuplicates++;
                }

            } catch (\Exception $e) {
                fwrite($logFile, "\nОШИБКА ПРИ ОБРАБОТКЕ ГРУППЫ (лат: {$groupRow->latitude}, лон: {$groupRow->longitude}): " . $e->getMessage() . "\n");
            }

            $bar->advance();
        }

        $bar->finish();
        fclose($logFile);

        $this->newLine(2);

        if (!$isExecute) {
            $this->warn('РЕЖИМ DRY-RUN: реальное удаление не выполнено.');
            $this->newLine();
        }

        // 5. Вывод сводки
        $deletedText = $isExecute ? $totalDeleted : $totalCandidates;

        $this->info('План удаления:' . ($isExecute ? ' (ВЫПОЛНЕНО)' : ''));
        $this->line("  Всего адресов на удаление: {$totalCandidates}");
        if ($isExecute) {
            $this->line("  Успешно удалено: {$totalDeleted}");
        }
        $this->line("  Групп станут не-дубликатами: {$groupsBecomingNonDuplicates}");
        $this->line("  Групп останется как дубликаты: {$groupsRemainingDuplicates}");
        
        $this->newLine();
        $this->info('Подробности сохранены в:');
        $this->line("  {$logFilePath}");
        
        if (!$isExecute) {
            $this->newLine();
            $this->warn('Для реального выполнения запустите с флагом --execute');
        }

        return self::SUCCESS;
    }
}
