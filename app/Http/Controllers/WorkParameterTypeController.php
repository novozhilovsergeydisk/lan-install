<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkParameterTypeController extends Controller
{
    /**
     * Получить список всех параметров типов заявок
     */
    public function index()
    {
        try {
            $workParameterTypes = DB::table('work_parameter_types')
                ->join('request_types', 'work_parameter_types.request_type_id', '=', 'request_types.id')
                ->where('request_types.is_deleted', false)
                ->where('work_parameter_types.is_deleted', false)
                ->select(
                    'work_parameter_types.id',
                    'work_parameter_types.name',
                    'work_parameter_types.request_type_id',
                    'request_types.name as request_type_name'
                )
                ->get();

            return response()->json($workParameterTypes);
        } catch (\Exception $e) {
            Log::error('Error in WorkParameterTypeController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка параметров типов заявок',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить параметры по типу заявки
     */
    public function getByRequestType($requestTypeId)
    {
        try {
            $workParameterTypes = DB::table('work_parameter_types')
                ->where('request_type_id', $requestTypeId)
                ->where('is_deleted', false)
                ->orderBy('id')
                ->get();

            return response()->json($workParameterTypes);
        } catch (\Exception $e) {
            Log::error('Error in WorkParameterTypeController@getByRequestType: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении параметров',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать новый параметр типа заявки
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'request_type_id' => 'required|integer|exists:request_types,id',
            ]);

            $id = DB::table('work_parameter_types')->insertGetId([
                'name' => $validated['name'],
                'request_type_id' => $validated['request_type_id'],
            ]);

            return response()->json([
                'success' => true,
                'id' => $id,
                'message' => 'Параметр типа заявки успешно создан',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in WorkParameterTypeController@store: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при создании параметра типа заявки',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновить существующий параметр типа заявки
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'request_type_id' => 'required|integer|exists:request_types,id',
            ]);

            $updated = DB::table('work_parameter_types')
                ->where('id', $id)
                ->update([
                    'name' => $validated['name'],
                    'request_type_id' => $validated['request_type_id'],
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Параметр типа заявки успешно обновлен',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить параметр типа заявки',
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in WorkParameterTypeController@update: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении параметра типа заявки',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Удалить параметр типа заявки (мягкое удаление)
     */
    public function destroy($id)
    {
        try {
            $updated = DB::table('work_parameter_types')
                ->where('id', $id)
                ->update(['is_deleted' => true]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Параметр типа заявки успешно удален',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось удалить параметр типа заявки',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error in WorkParameterTypeController@destroy: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при удалении параметра типа заявки',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
