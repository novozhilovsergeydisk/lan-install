<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CityController extends Controller
{
    public function getRegions()
    {
        $regions = Region::all();
        return response()->json($regions);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'region_id' => 'required|exists:regions,id',
            'postal_code' => 'nullable|string|max:10'
        ]);

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

            return response()->json([
                'success' => true,
                'message' => 'Город успешно добавлен',
                'city' => $city
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при добавлении города: ' . $e->getMessage()
            ], 500);
        }
    }
}
