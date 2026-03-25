<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WmsMappingController extends Controller
{
    public function index()
    {
        // 1. Получаем список типов заявок
        $requestTypes = DB::table('request_types')->where('is_deleted', false)->orderBy('name')->get();

        // 2. Получаем текущие маппинги (с именами типов заявок)
        $mappings = DB::table('request_type_wms_warehouses')
            ->join('request_types', 'request_type_wms_warehouses.request_type_id', '=', 'request_types.id')
            ->select('request_type_wms_warehouses.*', 'request_types.name as type_name')
            ->orderBy('request_types.name')
            ->get();

        // 3. Получаем список складов из WMS
        $warehouses = [];
        $warehousesMap = [];
        try {
            $apiKey = config('services.wms.api_key');
            $baseUrl = config('services.wms.base_url');
            
            $response = Http::withHeaders(['X-API-Key' => $apiKey])
                ->timeout(5)
                ->get("{$baseUrl}/api/external/warehouses");

            if ($response->successful()) {
                $warehouses = $response->json()['data'] ?? [];
                foreach ($warehouses as $wh) {
                    $warehousesMap[$wh['id']] = $wh['name'];
                }
            }
        } catch (\Exception $e) {
            Log::error('WMS: Error fetching warehouses', ['error' => $e->getMessage()]);
        }

        // Добавляем имя склада к маппингам для отображения в таблице
        foreach ($mappings as $mapping) {
            $mapping->warehouse_name = $warehousesMap[$mapping->wms_warehouse_id] ?? 'Неизвестный склад (#' . $mapping->wms_warehouse_id . ')';
        }

        return view('wms-mappings.index', compact('requestTypes', 'mappings', 'warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'request_type_id' => 'required|integer|exists:request_types,id',
            'wms_warehouse_id' => 'required|integer',
        ]);

        try {
            DB::table('request_type_wms_warehouses')->updateOrInsert(
                ['request_type_id' => $request->request_type_id],
                [
                    'wms_warehouse_id' => $request->wms_warehouse_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return back()->with('success', 'Привязка успешно сохранена.');
        } catch (\Exception $e) {
            Log::error('WMS Mapping Error: ' . $e->getMessage());
            return back()->with('error', 'Ошибка при сохранении: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            DB::table('request_type_wms_warehouses')->where('id', $id)->delete();
            return back()->with('success', 'Привязка удалена.');
        } catch (\Exception $e) {
            Log::error('WMS Mapping Delete Error: ' . $e->getMessage());
            return back()->with('error', 'Ошибка при удалении: ' . $e->getMessage());
        }
    }
}
