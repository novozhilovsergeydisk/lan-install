<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeesFilterController extends Controller
{
    /**
     * Возвращает список сотрудников (члены бригад и бригадиры),
     * у которых есть заявки на указанную дату.
     * Ожидает JSON: { "date": "YYYY-MM-DD" | "dd.mm.yyyy" }
     * Возвращает JSON-массив: [{ id, fio, brigade_id, brigade_name, is_leader }]
     */
    public function filterByDate(Request $request)
    {
        try {
            $request->merge([
                'date' => $request->input('date'),
            ]);

            $validated = $request->validate([
                'date' => 'required',
            ]);

            $date = $validated['date'];
            // Нормализация формата dd.mm.yyyy -> Y-m-d
            if (strpos($date, '.') !== false) {
                $dt = \DateTime::createFromFormat('d.m.Y', $date);
                if ($dt) {
                    $date = $dt->format('Y-m-d');
                }
            }

            // SQL по заданию с условием по дате
            $sql = "
                WITH today_brigades AS (
                    SELECT DISTINCT r.brigade_id
                    FROM requests r
                    JOIN request_statuses rs ON rs.id = r.status_id
                    WHERE r.execution_date::date = ?
                        AND rs.name NOT IN ('отменена', 'планирование')
                        AND r.brigade_id IS NOT NULL
                )
                SELECT e.id, e.fio, b.id AS brigade_id, b.name AS brigade_name, FALSE AS is_leader
                FROM brigades b
                JOIN today_brigades tb ON tb.brigade_id = b.id
                JOIN brigade_members bm ON bm.brigade_id = b.id
                JOIN employees e ON e.id = bm.employee_id
                WHERE b.is_deleted = FALSE AND e.is_deleted = FALSE
                UNION
                SELECT el.id AS id, el.fio, b.id AS brigade_id, b.name AS brigade_name, TRUE AS is_leader
                FROM brigades b
                JOIN today_brigades tb ON tb.brigade_id = b.id
                JOIN employees el ON el.id = b.leader_id
                WHERE b.is_deleted = FALSE AND el.is_deleted = FALSE
                ORDER BY fio
            ";

            $rows = DB::select($sql, [$date]);
            return response()->json($rows);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Ошибка EmployeesFilterController@filterByDate: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Внутренняя ошибка сервера',
            ], 500);
        }
    }
}
