<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlanningRequestController extends Controller
{
    /**
     * Store a newly created planning request in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Валидация входящих данных
        $validator = Validator::make($request->all(), [
            'address_id' => 'required|exists:addresses,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:20',
            'client_organization' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Здесь должна быть логика создания заявки на планирование
            // Пока что просто возвращаем успешный ответ
            
            return response()->json([
                'success' => true,
                'message' => 'Заявка успешно создана',
                'id' => 1 // Временный ID, замените на реальный после сохранения в БД
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заявки: ' . $e->getMessage()
            ], 500);
        }
    }
}
