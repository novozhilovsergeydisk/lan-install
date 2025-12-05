<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestTypeController extends Controller
{
    /**
     * Получить список всех типов заявок
     */
    public function index()
    {
        try {
            $requestTypes = DB::table('request_types')
                ->where('is_deleted', false)
                ->select(
                    'request_types.id',
                    'request_types.name',
                    'request_types.color'
                )
                ->get();

            return response()->json($requestTypes);
        } catch (\Exception $e) {
            Log::error('Error in RequestTypeController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка типов заявок',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать новый тип заявки
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'color' => 'required|string|max:7',
            ]);

            $id = DB::table('request_types')->insertGetId([
                'name' => $validated['name'],
                'color' => $validated['color'],
            ]);

            return response()->json([
                'success' => true,
                'id' => $id,
                'message' => 'Тип заявки успешно создан',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestTypeController@store: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при создании типа заявки',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновить существующий тип заявки
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'color' => 'required|string|max:7',
            ]);

            $updated = DB::table('request_types')
                ->where('id', $id)
                ->update([
                    'name' => $validated['name'],
                    'color' => $validated['color'],
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Тип заявки успешно обновлен',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить тип заявки',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error in RequestTypeController@update: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении типа заявки',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить тип заявки
     */
    public function destroy($id)
    {
        try {
            // Проверяем, есть ли заявки с этим типом
            $hasRequests = DB::table('requests')
                ->where('request_type_id', $id)
                ->exists();

            if ($hasRequests) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить тип заявки, так как с ним связаны заявки',
                ], 400);
            }

            $updated = DB::table('request_types')
                ->where('id', $id)
                ->update(['is_deleted' => true]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Тип заявки успешно удален',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось удалить тип заявки',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error in RequestTypeController@destroy: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при удалении типа заявки',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
