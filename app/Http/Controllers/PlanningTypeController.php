<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanningTypeController extends Controller
{
    /**
     * Получить список всех не удаленных типов планирования.
     */
    public function index()
    {
        $types = DB::table('request_subtypes')
            ->where('status_id', 6) // Статус "планирование"
            ->where('is_deleted', false)
            ->orderBy('id')
            ->get(['id', 'name']);

        return response()->json($types);
    }

    /**
     * Создать новый тип планирования.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $id = DB::table('request_subtypes')->insertGetId([
            'status_id' => 6, // Статус "планирование"
            'name' => $request->input('name'),
            'is_deleted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'id' => $id,
            'name' => $request->input('name')
        ]);
    }

    /**
     * Обновить тип планирования.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $updated = DB::table('request_subtypes')
            ->where('id', $id)
            ->where('status_id', 6)
            ->update([
                'name' => $request->input('name'),
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return response()->json(['success' => false, 'message' => 'Тип не найден'], 404);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Удалить (скрыть) тип планирования.
     */
    public function destroy($id)
    {
        $deleted = DB::table('request_subtypes')
            ->where('id', $id)
            ->where('status_id', 6)
            ->update([
                'is_deleted' => true,
                'updated_at' => now(),
            ]);

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Тип не найден'], 404);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
