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
        $planningStatusId = DB::table('request_statuses')->where('name', 'планирование')->value('id');

        if (!$planningStatusId) {
            return response()->json([]);
        }

        $types = DB::table('request_subtypes')
            ->leftJoin('requests', function($join) use ($planningStatusId) {
                $join->on('request_subtypes.id', '=', 'requests.subtype_id')
                     ->where('requests.status_id', '=', $planningStatusId);
            })
            ->where('request_subtypes.status_id', $planningStatusId)
            ->where('request_subtypes.is_deleted', false)
            ->select('request_subtypes.id', 'request_subtypes.name', DB::raw('COUNT(requests.id) as requests_count'))
            ->groupBy('request_subtypes.id', 'request_subtypes.name')
            ->orderBy('request_subtypes.id')
            ->get();

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

        $planningStatusId = DB::table('request_statuses')->where('name', 'планирование')->value('id');

        $id = DB::table('request_subtypes')->insertGetId([
            'status_id' => $planningStatusId, 
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

        $planningStatusId = DB::table('request_statuses')->where('name', 'планирование')->value('id');

        $updated = DB::table('request_subtypes')
            ->where('id', $id)
            ->where('status_id', $planningStatusId)
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
        $planningStatusId = DB::table('request_statuses')->where('name', 'планирование')->value('id');

        $deleted = DB::table('request_subtypes')
            ->where('id', $id)
            ->where('status_id', $planningStatusId)
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
