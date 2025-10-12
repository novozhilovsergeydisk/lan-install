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
        
        \Log::info('=== START store ===', $request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'region_id' => 'required|exists:regions,id',
            'postal_code' => 'nullable|string|max:10'
        ]);
        \Log::info('=== Все входные данные ===', $validated);

        try {
            DB::beginTransaction();
            
            $city = City::create($validated);
            
            DB::commit();

            \Log::info('=== Все выхлдные данные ===', $city);
            \Log::info('=== END store ===', []);
            
            return response()->json([
                'success' => true,
                'message' => 'Город успешно добавлен',
                'city' => $city
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
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
