<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;

class CityController extends Controller
{
    public function getRegions()
    {
        $regions = Region::all();
        return response()->json($regions);
    }
    
    public function store(Request $request)
    {
        \Log::info('=== START store ===', $request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'region_id' => 'required|exists:regions,id',
            'postal_code' => 'nullable|string|max:10'
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
                'updated_at' => now()
            ]);
            
            // Получаем только что созданный город
            $city = DB::table('cities')->find($cityId);
            
            DB::commit();

            Nightwatch::message('Пользователь создал город', [
                'city_name' => $request->name,
                'user_id' => auth()->id(),
            ]);

            \Log::info('=== Все выходные данные ===', $city);
            \Log::info('=== END store ===', []);

            return response()->json([
                'success' => true,
                'message' => 'Город успешно добавлен',
                'city' => $city
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();

            Nightwatch::message('Ошибка при добавлении города', [
                'city_name' => $request->name,
                'user_id' => auth()->id(),
            ]);

            \Log::info('=== END store ===', []);
            \Log::info('=== START error store ===', []);
            \Log::info('Ошибка при добавлении города: ' . $e->getMessage());
            \Log::info('=== END error store ===', []);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при добавлении города: ' . $e->getMessage()
            ], 500);
        }
    }
}
