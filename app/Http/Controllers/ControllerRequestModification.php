<?php

namespace App\Http\Controllers;

use App\Services\Brigade\BrigadeChangeCommentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ControllerRequestModification extends Controller
{
    /**
     * Получить ID бригады по ID бригадира за текущую дату
     *
     * @param  int  $leaderId  ID бригадира
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

            $sql = 'SELECT id AS brigade_id, name AS brigade_name, formation_date FROM brigades WHERE leader_id = ? AND DATE(formation_date) = CURRENT_DATE ORDER BY formation_date DESC LIMIT 1';

            $brigade = DB::select($sql, [$leaderId])[0] ?? null;

            if (! $brigade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бригада с указанным бригадиром не найдена за сегодняшнюю дату',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $brigade,
                '$sql' => $sql,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных о бригаде',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновить бригаду у заявки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRequestBrigade(Request $request, BrigadeChangeCommentService $commenter)
    {
        try {
            $validated = $request->validate([
                'brigade_id' => 'required|integer|exists:brigades,id',
                'request_id' => 'required|integer|exists:requests,id',
            ]);

            // Запоминаем СТАРУЮ бригаду ДО смены — её состав пойдёт в автокомментарий.
            $oldBrigadeId = DB::table('requests')->where('id', $validated['request_id'])->value('brigade_id');

            $sql = 'UPDATE requests SET brigade_id = ? WHERE id = ?';
            $updated = DB::update($sql, [$request->brigade_id, $request->request_id]);

            if ($updated) {
                // Автокомментарий только при реальной СМЕНЕ (была старая бригада и она отличается от новой).
                if ($oldBrigadeId !== null && (int) $oldBrigadeId !== (int) $validated['brigade_id']) {
                    $commenter->logChange((int) $validated['request_id'], (int) $oldBrigadeId, $request->user()->id);
                }

                $sql = "SELECT
                    b.id AS brigade_id,
                    b.name AS brigade_name,
                    bl.fio AS leader_fio,
                    b.formation_date,
                    string_agg(
                        CASE
                            WHEN bm.employee_id IS NOT NULL AND bm.employee_id != b.leader_id THEN bm_employee.fio
                            ELSE NULL
                        END,
                        ', ' ORDER BY bm_employee.fio
                    ) AS members
                FROM
                    brigades b
                JOIN
                    employees bl ON b.leader_id = bl.id
                LEFT JOIN
                    brigade_members bm ON bm.brigade_id = b.id
                LEFT JOIN
                    employees bm_employee ON bm.employee_id = bm_employee.id
                WHERE 1=1
                    AND b.id = ?
                    AND b.is_deleted = false
                    AND bl.is_deleted = false
                GROUP BY
                    b.id, b.name, bl.fio
                ORDER BY
                    b.id desc;";
                $brigadeMembers = DB::select($sql, [$validated['brigade_id']]);

                return response()->json([
                    'success' => true,
                    'message' => 'Бригада успешно прикреплена к заявке',
                    'data' => [
                        'request_id' => $request->request_id,
                        'brigade_id' => $request->brigade_id,
                        'brigadeMembers' => $brigadeMembers,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить заявку',
                'data' => [
                    'request_id' => $request->request_id,
                    'brigade_id' => $request->brigade_id,
                ],
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Ошибка при обновлении заявки', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении заявки: '.$e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ],
            ], 500);
        }
    }

    /**
     * Массово обновить бригаду у заявок
     *
     * @return IlluminateHttpJsonResponse
     */
    public function updateRequestBrigadeMass(Request $request, BrigadeChangeCommentService $commenter)
    {
        try {
            $validated = $request->validate([
                'brigade_id' => 'required|integer|exists:brigades,id',
                'request_ids' => 'required|array|min:1',
                'request_ids.*' => 'integer|exists:requests,id',
            ]);

            $requestIds = $validated['request_ids'];
            $brigadeId = $validated['brigade_id'];

            // Запоминаем СТАРУЮ бригаду КАЖДОЙ заявки ДО смены (у каждой могла быть своя) — для автокомментария.
            $oldBrigades = DB::table('requests')->whereIn('id', $requestIds)->pluck('brigade_id', 'id');

            // Создаем строку с плейсхолдерами ?, ?, ? для IN (...)
            $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
            $sql = "UPDATE requests SET brigade_id = ? WHERE id IN ($placeholders)";

            // Собираем параметры: сначала brigade_id, затем все request_ids
            $params = array_merge([$brigadeId], $requestIds);

            $updated = DB::update($sql, $params);

            if ($updated > 0) {
                foreach ($requestIds as $requestId) {
                    // Автокомментарий только при реальной смене (была старая бригада и она отличается от новой).
                    $old = $oldBrigades[$requestId] ?? null;
                    if ($old !== null && (int) $old !== (int) $brigadeId) {
                        $commenter->logChange((int) $requestId, (int) $old, $request->user()->id);
                    }
                }

                // Получаем информацию о бригаде для ответа (как в одиночном методе)
                $sql = "SELECT
                    b.id AS brigade_id,
                    b.name AS brigade_name,
                    bl.fio AS leader_fio,
                    b.formation_date,
                    string_agg(
                        CASE
                            WHEN bm.employee_id IS NOT NULL AND bm.employee_id != b.leader_id THEN bm_employee.fio
                            ELSE NULL
                        END,
                        ', ' ORDER BY bm_employee.fio
                    ) AS members
                FROM
                    brigades b
                JOIN
                    employees bl ON b.leader_id = bl.id
                LEFT JOIN
                    brigade_members bm ON bm.brigade_id = b.id
                LEFT JOIN
                    employees bm_employee ON bm.employee_id = bm_employee.id
                WHERE 1=1
                    AND b.id = ?
                    AND b.is_deleted = false
                    AND bl.is_deleted = false
                GROUP BY
                    b.id, b.name, bl.fio
                ORDER BY
                    b.id desc;";
                $brigadeMembers = DB::select($sql, [$brigadeId]);

                return response()->json([
                    'success' => true,
                    'message' => 'Бригада успешно назначена на '.$updated.' заявки(ок)',
                    'data' => [
                        'request_ids' => $requestIds,
                        'brigade_id' => $brigadeId,
                        'brigadeMembers' => $brigadeMembers,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить заявки. Возможно они уже обновлены или не существуют.',
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Ошибка при массовом обновлении заявок', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении заявок: '.$e->getMessage(),
            ], 500);
        }
    }

}
