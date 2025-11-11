<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatusController extends Controller
{
    /**
     * Получить список всех статусов
     */
    public function index()
    {
        try {
            $statuses = DB::table('request_statuses')
                ->leftJoin('requests', 'request_statuses.id', '=', 'requests.status_id')
                ->select(
                    'request_statuses.id',
                    'request_statuses.name',
                    'request_statuses.color',
                    DB::raw('COUNT(requests.id) as requests_count')
                )
                ->groupBy('request_statuses.id', 'request_statuses.name', 'request_statuses.color')
                ->get();

            return response()->json($statuses);
        } catch (\Exception $e) {
            Log::error('Error in StatusController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка статусов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать новый статус
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:request_statuses,name',
                'color' => 'required|string|max:50',
            ]);

            $id = DB::table('request_statuses')->insertGetId([
                'name' => $validated['name'],
                'color' => $validated['color'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'id' => $id,
                'message' => 'Статус успешно создан',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in StatusController@store: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при создании статуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновить существующий статус
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:request_statuses,name,'.$id,
                'color' => 'required|string|max:50',
            ]);

            $updated = DB::table('request_statuses')
                ->where('id', $id)
                ->update([
                    'name' => $validated['name'],
                    'color' => $validated['color'],
                    'updated_at' => now(),
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Статус успешно обновлен',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить статус',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error in StatusController@update: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении статуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить статус
     */
    public function destroy($id)
    {
        try {
            // Проверяем, есть ли заявки с этим статусом
            $hasRequests = DB::table('requests')
                ->where('status_id', $id)
                ->exists();

            if ($hasRequests) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить статус, так как с ним связаны заявки',
                ], 400);
            }

            $deleted = DB::table('request_statuses')
                ->where('id', $id)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Статус успешно удален',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось удалить статус',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error in StatusController@destroy: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при удалении статуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
