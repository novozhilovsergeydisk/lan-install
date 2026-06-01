<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FindDuplicateAddresses extends Command
{
    protected $signature = 'addresses:find-duplicates {--limit= : Максимум групп для обработки}';

    protected $description = 'Находит группы адресов-дубликатов по координатам. Только READ, ничего не меняет.';

    public function handle(): int
    {
        $this->info('Поиск дубликатов адресов...');

        // 1. Найти все группы адресов с одинаковыми координатами
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
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('cnt')
            ->orderBy('latitude');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $groupsData = $query->get();
        $totalGroups = $groupsData->count();

        if ($totalGroups === 0) {
            $this->info('Дубликатов не найдено.');
            return self::SUCCESS;
        }

        $totalDuplicates = $groupsData->sum('cnt');

        $reportData = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'total_groups' => $totalGroups,
            'total_duplicate_records' => $totalDuplicates,
            'groups' => [],
        ];

        $bar = $this->output->createProgressBar($totalGroups);
        $bar->start();

        foreach ($groupsData as $groupRow) {
            $ids = explode(',', $groupRow->ids);

            // 2. Для каждой группы получить детали (city_name через JOIN)
            $addresses = DB::table('addresses')
                ->select(
                    'addresses.id', 
                    'addresses.street', 
                    'addresses.district', 
                    'addresses.houses', 
                    'cities.name as city_name'
                )
                ->leftJoin('cities', 'addresses.city_id', '=', 'cities.id')
                ->whereIn('addresses.id', $ids)
                ->get();

            // Кол-во заявок (через отдельный запрос для безопасности и производительности)
            $requestsCounts = DB::table('request_addresses')
                ->select('address_id', DB::raw('COUNT(*) as cnt'))
                ->whereIn('address_id', $ids)
                ->groupBy('address_id')
                ->pluck('cnt', 'address_id');

            // Пытаемся получить кол-во документов. Если таблицы нет - не падаем.
            $documentsCounts = collect([]);
            try {
                $documentsCounts = DB::table('addresses_documents')
                    ->select('address_id', DB::raw('COUNT(*) as cnt'))
                    ->whereIn('address_id', $ids)
                    ->groupBy('address_id')
                    ->pluck('cnt', 'address_id');
            } catch (\Exception $e) {
                // Игнорируем, если таблица не найдена
            }

            // 3. Подсказать "канонический" адрес
            $bestId = null;
            $bestStreetLen = -1;
            $bestDistrictLen = -1;
            $bestHousesLen = -1;

            foreach ($addresses as $addr) {
                $streetLen = mb_strlen($addr->street ?? '');
                $districtLen = mb_strlen($addr->district ?? '');
                $housesLen = mb_strlen($addr->houses ?? '');

                if ($bestId === null) {
                    $bestId = $addr->id;
                    $bestStreetLen = $streetLen;
                    $bestDistrictLen = $districtLen;
                    $bestHousesLen = $housesLen;
                    continue;
                }

                if ($streetLen > $bestStreetLen) {
                    $bestId = $addr->id;
                    $bestStreetLen = $streetLen;
                    $bestDistrictLen = $districtLen;
                    $bestHousesLen = $housesLen;
                } elseif ($streetLen === $bestStreetLen) {
                    if ($districtLen > $bestDistrictLen) {
                        $bestId = $addr->id;
                        $bestDistrictLen = $districtLen;
                        $bestHousesLen = $housesLen;
                    } elseif ($districtLen === $bestDistrictLen) {
                        if ($housesLen > $bestHousesLen) {
                            $bestId = $addr->id;
                            $bestHousesLen = $housesLen;
                        } elseif ($housesLen === $bestHousesLen) {
                            if ($addr->id < $bestId) {
                                $bestId = $addr->id;
                            }
                        }
                    }
                }
            }

            $groupDetails = [
                'latitude' => (float) $groupRow->latitude,
                'longitude' => (float) $groupRow->longitude,
                'count' => (int) $groupRow->cnt,
                'suggested_canonical_id' => $bestId,
                'addresses' => [],
            ];

            foreach ($addresses as $addr) {
                $groupDetails['addresses'][] = [
                    'id' => $addr->id,
                    'city_name' => $addr->city_name ?? '',
                    'street' => $addr->street ?? '',
                    'district' => $addr->district ?? '',
                    'houses' => $addr->houses ?? '',
                    'requests_count' => $requestsCounts->get($addr->id, 0),
                    'documents_count' => $documentsCounts->get($addr->id, 0),
                    'is_canonical_suggestion' => ($addr->id === $bestId),
                ];
            }

            $reportData['groups'][] = $groupDetails;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // 4. Сохранить результат в ДВА файла
        $jsonPath = storage_path('duplicates_report.json');
        file_put_contents($jsonPath, json_encode($reportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $txtPath = storage_path('duplicates_report.txt');
        $txtContent = $this->generateTxtReport($reportData);
        file_put_contents($txtPath, $txtContent);

        // 5. Вывести на экран краткую сводку
        $this->info("Всего групп: {$totalGroups}");
        $this->info("Всего записей-дубликатов: {$totalDuplicates}");
        $this->line("Сохранено в {$jsonPath}");
        $this->line("Сохранено в {$txtPath}");
        $this->warn("Просмотрите отчёт перед запуском слияния");

        return self::SUCCESS;
    }

    private function generateTxtReport(array $reportData): string
    {
        $txt = "============================================================\n";
        $txt .= "ОТЧЁТ О ДУБЛИКАТАХ АДРЕСОВ\n";
        $txt .= "Сгенерирован: {$reportData['generated_at']}\n";
        $txt .= "Всего групп: {$reportData['total_groups']}\n";
        $txt .= "Всего записей-дубликатов: {$reportData['total_duplicate_records']}\n";
        $txt .= "============================================================\n\n";

        foreach ($reportData['groups'] as $index => $group) {
            $groupNum = $index + 1;
            $txt .= "ГРУППА {$groupNum}: координаты {$group['latitude']}, {$group['longitude']} ({$group['count']} копии)\n";
            $txt .= "  → Предлагаемый канонический: ID={$group['suggested_canonical_id']}\n  \n";

            foreach ($group['addresses'] as $addr) {
                $star = $addr['is_canonical_suggestion'] ? '⭐' : '  ';
                $idStr = str_pad("ID={$addr['id']}", 8);
                
                // Выравнивание табличных данных с учетом кириллицы
                $streetVal = '"' . $addr['street'] . '"';
                $streetLen = mb_strlen($streetVal);
                $streetPad = 30;
                $streetStr = $streetVal . str_repeat(' ', max(0, $streetPad - $streetLen));
                
                $distVal = $addr['district'];
                $distLen = mb_strlen($distVal);
                $distPad = 15;
                $distStr = $distVal . str_repeat(' ', max(0, $distPad - $distLen));
                
                $houseVal = $addr['houses'];
                $houseLen = mb_strlen($houseVal);
                $housePad = 8;
                $houseStr = $houseVal . str_repeat(' ', max(0, $housePad - $houseLen));
                
                $reqCountStr = $addr['requests_count'] . " заявок";
                $docCountStr = $addr['documents_count'] . " документов";

                $txt .= "  {$idStr} {$star}| {$streetStr} | {$distStr} | {$houseStr} | {$reqCountStr} | {$docCountStr}\n";
            }
            $txt .= "\n";
        }

        return $txt;
    }
}
