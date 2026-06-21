<?php

namespace App\Services\Wms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Оборудование, взятое бригадой со склада (только отображение).
 *
 * - getEquipmentForRequest() — live-данные для формы закрытия (комплекты H-* и машины).
 * - captureSnapshotForRequest() — снимок в request_equipment на момент закрытия
 *   (+ опционально личное авто, введённое вручную в форме).
 *
 * Никаких действий со складом — только чтение по API (stock-flow). Best-effort:
 * недоступность склада не должна мешать закрытию заявки.
 */
class WmsEquipmentService
{
    /**
     * Live-оборудование бригады заявки: уникальные комплекты инструмента и машины со склада.
     * Возвращает ['tools' => ['H-1','H-7'], 'vehicles' => ['Р724ХВ77 Ford Transit']].
     */
    public function getEquipmentForRequest(int $requestId): array
    {
        $tools = [];
        $vehicles = [];
        foreach ($this->fetchWarehouseRows($requestId) as $row) {
            if ($row['kind'] === 'tool') {
                $tools[$row['label']] = $row['label'];
            } elseif ($row['kind'] === 'vehicle') {
                $vehicles[$row['label']] = $row['label'];
            }
        }

        return [
            'tools' => array_values($tools),
            'vehicles' => array_values($vehicles),
        ];
    }

    /**
     * Снимок оборудования на момент закрытия заявки.
     * $personalCar — личное авто, введённое вручную (если со склада машину никто не брал).
     */
    public function captureSnapshotForRequest(int $requestId, ?string $personalCar = null): void
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

            // Личное авто (введено вручную в форме закрытия).
            $personalCar = $personalCar !== null ? trim($personalCar) : '';
            if ($personalCar !== '') {
                $rows[] = [
                    'request_id' => $requestId,
                    'kind' => 'vehicle',
                    'label' => $personalCar,
                    'holder_emp_id' => null,
                    'holder_fio' => null,
                    'wms_ref' => null,
                    'source' => 'personal',
                    'created_at' => now(),
                ];
            }

            // Обновляем снимок: чистим старый и пишем свежий (на случай переоткрытия/перезакрытия заявки).
            DB::table('request_equipment')->where('request_id', $requestId)->delete();
            if (! empty($rows)) {
                DB::table('request_equipment')->insert($rows);
            }
        } catch (\Throwable $e) {
            Log::error('WMS equipment: снимок не сделан', ['request_id' => $requestId, 'error' => $e->getMessage()]);
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
