<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControllerRequestModification extends Controller
{
    /**
     * Получить ID бригады по ID бригадира за текущую дату
     *
     * @param int $leaderId ID бригадира
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBrigadeByLeader($leaderId)
    {
        try {
//            $brigade = DB::table('brigades')
//                ->select('id as brigade_id', 'name as brigade_name', 'formation_date')
//                ->where('leader_id', $leaderId)
//                ->whereDate('formation_date', now()->toDateString())
//                ->orderBy('formation_date', 'desc')
//                ->first();

            $sql = "SELECT id AS brigade_id, name AS brigade_name, formation_date FROM brigades WHERE leader_id = ? AND DATE(formation_date) = CURRENT_DATE ORDER BY formation_date DESC LIMIT 1";

            $brigade = DB::select($sql, [$leaderId])[0] ?? null;

            if (!$brigade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бригада с указанным бригадиром не найдена за сегодняшнюю дату'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $brigade,
                '$sql' => $sql
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных о бригаде',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновить бригаду у заявки
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRequestBrigade(Request $request)
    {
        \Log::info('Получен запрос на обновление бригады заявки', $request->all());

        try {
            $validated = $request->validate([
                'brigade_id' => 'required|integer|exists:brigades,id',
                'request_id' => 'required|integer|exists:requests,id'
            ]);

            \Log::info('Валидация пройдена', $validated);

            $sql = "UPDATE requests SET brigade_id = ? WHERE id = ?";
            $updated = DB::update($sql, [$request->brigade_id, $request->request_id]);

            \Log::info('Результат обновления заявки', [
                'request_id' => $request->request_id,
                'brigade_id' => $request->brigade_id,
                'updated' => $updated
            ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Бригада успешно прикреплена к заявке',
                    'data' => [
                        'request_id' => $request->request_id,
                        'brigade_id' => $request->brigade_id
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить заявку',
                'data' => [
                    'request_id' => $request->request_id,
                    'brigade_id' => $request->brigade_id
                ]
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Ошибка при обновлении заявки', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении заявки: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode()
                ]
            ], 500);
        }
    }
}
