<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CityController extends Controller
{
    public function getRegions()
    {
        try {
            $regions = DB::table('regions')->get();

            return response()->json($regions);
        } catch (\Exception $e) {
            Log::error('Error in CityController@getRegions: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка регионов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        \Log::info('=== START store ===', $request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'region_id' => 'required|exists:regions,id',
            'postal_code' => 'nullable|string|max:10',
        ]);
        \Log::info('=== Все входные данные ===', $validated);

        try {
            DB::beginTransaction();

            // Вставляем новый город с помощью нативного SQL
            $cityId = DB::table('cities')->insertGetId([
                'name' => $validated['name'],
                'region_id' => $validated['region_id'],
                'postal_code' => $validated['postal_code'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Получаем только что созданный город
            $city = DB::table('cities')->find($cityId);

            DB::commit();

            \Log::info('=== Все выходные данные ===', (array) $city);
            \Log::info('=== END store ===');

            return response()->json([
                'success' => true,
                'message' => 'Город успешно добавлен',
                'city' => $city,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::info('=== END store ===');
            \Log::error('=== START error store ===');
            \Log::error('Ошибка при добавлении города, город с таким названием уже существует', []);
            \Log::error('=== END error store ===');

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при добавлении города: '.$e->getMessage(),
            ], 500);
        }
    }
}
