<?php

namespace App\Services\Wms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Оборудование, взятое бригадой со склада (только отображение рядом с бригадой).
 *
 * Снимок (request_equipment) обновляется:
 *  - плановой командой wms:refresh-equipment для ОТКРЫТЫХ сегодняшних заявок (live);
 *  - при закрытии заявки (заморозка последнего значения).
 *
 * Только чтение склада по API (stock-flow). Best-effort: недоступность склада
 * не должна мешать закрытию заявки.
 */
class WmsEquipmentService
{
    /**
     * Снимок: комплекты инструмента (инв. H-*) и машины со склада, которые числятся
     * за участниками бригады заявки. Перезаписывает request_equipment для заявки.
     */
    public function captureSnapshotForRequest(int $requestId): void
    {
        try {
            if (! Schema::hasTable('request_equipment')) {
                return;
            }

            $rows = [];
            foreach ($this->fetchWarehouseRows($requestId) as $row) {
                $rows[] = [
                    'request_id' => $requestId,
                    'kind' => $row['kind'],
                    'label' => $row['label'],
                    'holder_emp_id' => $row['holder_emp_id'],
                    'holder_fio' => $row['holder_fio'],
                    'wms_ref' => $row['wms_ref'],
                    'source' => 'warehouse',
                    'created_at' => now(),
                ];
            }

            if (empty($rows)) {
                // Склад вернул пусто — НЕ стираем существующий снимок («липкий»).
                // Иначе live-обновление между «сдали инвентарь» и «закрыли заявку» затрёт
                // данные, и при закрытии будет нечего замораживать.
                return;
            }

            DB::table('request_equipment')->where('request_id', $requestId)->delete();
            DB::table('request_equipment')->insert($rows);
        } catch (\Throwable $e) {
            Log::error('WMS equipment: снимок не сделан', ['request_id' => $requestId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Живое обновление оборудования открытых сегодняшних заявок «по требованию» — вызывается
     * при отрисовке дневного вида (index / getRequestsByDate за сегодня), чтобы инвентарь был
     * свежим сразу после выдачи на складе, а не раз в час.
     *
     * Безопасно для главной страницы:
     *  - троттл 5 сек на весь сервер (Cache) — частые F5 не удваивают нагрузку на склад;
     *  - быстрый пинг склада (2 сек) — если недоступен, выходим, страницу не вешаем.
     */
    public function refreshTodayBestEffort(): void
    {
        try {
            if (! Schema::hasTable('request_equipment')) {
                return;
            }

            $last = Cache::get('wms_equipment_refreshed_at');
            if ($last && now()->diffInSeconds($last) < 5) {
                return;
            }

            $apiKey = config('services.wms.api_key');
            $baseUrl = config('services.wms.base_url');
            if (! $apiKey || ! $baseUrl) {
                return;
            }

            try {
                $ping = Http::withHeaders(['X-API-Key' => $apiKey])->timeout(2)->get("{$baseUrl}/api/external/warehouses");
                if (! $ping->successful()) {
                    return;
                }
            } catch (\Throwable $e) {
                return;
            }

            $ids = DB::table('requests')
                ->whereRaw('DATE(execution_date) = CURRENT_DATE')
                ->whereNotIn('status_id', [4, 5, 6, 7])
                ->whereNotNull('brigade_id')
                ->pluck('id');

            foreach ($ids as $id) {
                $this->captureSnapshotForRequest((int) $id);
            }

            Cache::put('wms_equipment_refreshed_at', now(), 3600);
        } catch (\Throwable $e) {
            Log::warning('WMS equipment: refreshTodayBestEffort failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Опрашивает склад по каждому участнику бригады и возвращает «сырые» строки оборудования
     * со склада: [['kind'=>'tool'|'vehicle','label'=>..,'holder_emp_id'=>..,'holder_fio'=>..,'wms_ref'=>..], ...].
     */
    private function fetchWarehouseRows(int $requestId): array
    {
        $rows = [];

        $members = DB::select('
            SELECT e.id, e.fio, u.email
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.id = ? AND e.is_deleted = false
        ', [$requestId]);

        if (empty($members)) {
            return $rows;
        }

        $apiKey = config('services.wms.api_key');
        $baseUrl = config('services.wms.base_url');
        if (! $apiKey || ! $baseUrl) {
            Log::warning('WMS equipment: API склада не настроен', ['request_id' => $requestId]);

            return $rows;
        }

        foreach ($members as $m) {
            if (empty($m->email)) {
                continue;
            }

            try {
                $response = Http::withHeaders(['X-API-Key' => $apiKey])
                    ->timeout(5)
                    ->get("{$baseUrl}/api/external/user-equipment", ['email' => $m->email]);

                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json()['data'] ?? [];

                foreach (($data['tools'] ?? []) as $tool) {
                    $inv = $tool['inventoryNumber'] ?? null;
                    if (! $inv) {
                        continue;
                    }
                    $rows[] = [
                        'kind' => 'tool',
                        'label' => $inv,
                        'holder_emp_id' => $m->id,
                        'holder_fio' => $m->fio,
                        'wms_ref' => $inv,
                    ];
                }

                foreach (($data['vehicles'] ?? []) as $vehicle) {
                    $plate = $vehicle['plateNumber'] ?? null;
                    if (! $plate) {
                        continue;
                    }
                    $model = $vehicle['model'] ?? null;
                    $rows[] = [
                        'kind' => 'vehicle',
                        'label' => trim($plate.' '.($model ?? '')),
                        'holder_emp_id' => $m->id,
                        'holder_fio' => $m->fio,
                        'wms_ref' => $plate,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('WMS equipment: ошибка запроса для '.$m->email, ['error' => $e->getMessage()]);
            }
        }

        return $rows;
    }
}
