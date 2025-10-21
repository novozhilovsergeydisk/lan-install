<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BrigadeController extends Controller
{
    public function getBrigadesByDate($date)
    {
        try {
            $sql = "WITH today_brigades AS (
                SELECT DISTINCT r.brigade_id
                FROM requests r
                JOIN request_statuses rs ON rs.id = r.status_id
                WHERE r.execution_date = '$date'
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
                SELECT el.id AS employee_id, el.fio, b.id AS brigade_id, b.name AS brigade_name, TRUE AS is_leader
                FROM brigades b
                JOIN today_brigades tb ON tb.brigade_id = b.id
                JOIN employees el ON el.id = b.leader_id
                WHERE b.is_deleted = FALSE AND el.is_deleted = FALSE
                ORDER BY brigade_id DESC";
            $brigades = DB::select($sql);
            return response()->json([
                'success' => true,
                'data' => $brigades,
                'sql' => $sql
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in BrigadeController@getBrigadesByDate: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка бригад на указанную дату',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // \Log::info('BrigadeController@index called');
        
        try {
            // Логируем SQL-запрос
            // \DB::enableQueryLog();
            
            // Получаем список бригад с информацией о бригадире
            $brigades = DB::table('brigades')
                ->leftJoin('employees', 'brigades.leader_id', '=', 'employees.id')
                ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                ->select(
                    'brigades.id',
                    'brigades.name',
                    'brigades.leader_id',
                    'employees.fio as leader_fio',
                    'employees.phone as leader_phone',
                    'positions.name as leader_position'
                )
                ->where('brigades.is_deleted', false)
                ->orderBy('brigades.name')
                ->get();
                
            // Разбиваем ФИО на составляющие для совместимости с фронтендом
            $brigades->each(function ($item) {
                $fioParts = explode(' ', $item->leader_fio ?? '');
                $item->leader_last_name = $fioParts[0] ?? '';
                $item->leader_first_name = $fioParts[1] ?? '';
                $item->leader_middle_name = $fioParts[2] ?? '';
                return $item;
            });

            // Логируем запрос и результаты
            // $query = \DB::getQueryLog();
            // \Log::info('SQL Query:', ['query' => $query]);
            // \Log::info('Brigades data:', ['count' => $brigades->count(), 'data' => $brigades->toArray()]);
            
            $response = [
                'success' => true,
                'data' => $brigades->map(function($item) {
                    return (array)$item; // Преобразуем объект в массив
                })
            ];
            
            // \Log::info('Response data:', $response);
            
            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Error in BrigadeController@index: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка бригад',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        // Получаем список всех сотрудников
        $employees = DB::select("SELECT id, fio FROM employees ORDER BY fio");
    }

    public function getCurrentDayBrigades()
    {
        try {
        $today = now()->toDateString();
        
        $sql = "SELECT 
                    e.id, b.id as brigade_id, b.name as brigade_name, e.fio AS leader_name, e.id as employee_id 
                FROM 
                    brigades AS b 
                JOIN 
                    employees AS e ON b.leader_id = e.id 
                WHERE 
                    DATE(b.formation_date) >= '{$today}' and b.is_deleted = false";
        
        $brigades = DB::select($sql);
        
        // \Log::info('Successfully retrieved current day brigades', ['count' => count($brigades)]);
        
        return response()->json([
            'success' => true,
            'data' => $brigades
        ]);
        } catch (\Exception $e) {
            \Log::error('Error in BrigadeController@getCurrentDayBrigades: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка бригад на текущий день',
                'error' => $e->getMessage()
            ], 500);
        }
   }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'leader_id' => 'required|exists:employees,id',
                'members' => 'required|array|min:1',
                'members.*' => 'exists:employees,id',
            ], [
                'name.required' => 'Название бригады обязательно для заполнения',
                'leader_id.required' => 'Необходимо выбрать бригадира',
                'members.required' => 'Необходимо добавить хотя бы одного участника',
                'members.min' => 'Необходимо добавить хотя бы одного участника',
            ]);

            DB::beginTransaction();

            // Вставка бригады
            DB::insert("INSERT INTO brigades (name, leader_id, formation_date) VALUES (?, ?, CURRENT_DATE)", [
                $validated['name'],
                $validated['leader_id'],
            ]);

            $brigadeId = DB::getPdo()->lastInsertId();

            // Фильтруем участников, исключая бригадира
            $members = array_diff($validated['members'], [$validated['leader_id']]);
            
            // Вставка участников бригады
            if (!empty($members)) {
                $values = array_map(fn($memberId) => "($brigadeId, $memberId)", $members);
                DB::insert("INSERT INTO brigade_members (brigade_id, employee_id) VALUES " . implode(',', $values));
            }

            // Получаем данные о созданной бригаде
            $brigade = DB::selectOne(
                "SELECT b.*, 
                        COALESCE(e.fio, 'Не назначен') as leader_name, 
                        (SELECT COUNT(*) FROM brigade_members WHERE brigade_id = ?) as members_count 
                 FROM brigades b 
                 LEFT JOIN employees e ON b.leader_id = e.id 
                 WHERE b.id = ?", 
                [$brigadeId, $brigadeId]
            );
            
            // Убедимся, что у нас есть объект бригады
            if (!$brigade) {
                $brigade = (object)[
                    'id' => $brigadeId,
                    'name' => $validated['name'],
                    'leader_name' => 'Ошибка загрузки данных',
                    'members_count' => count($members)
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Бригада успешно создана',
                'brigade' => $brigade
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при создании бригады: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при создании бригады',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // This can be used for a full page view if needed
        return view('brigade.show', ['id' => $id]); 
    }
    
    /**
     * Delete a brigade
     * 
     * @param int $brigadeId ID of the brigade to delete
     * @return \Illuminate\Http\JsonResponse
     */
    /**
 * Удаляет бригаду и всех её участников, если бригада не назначена в заявках.
 *
 * @param int $brigadeId ID бригады
 * @return \Illuminate\Http\JsonResponse
 */
public function deleteBrigade($brigadeId)
{
    // \Log::info('Попытка удаления бригады', ['brigadeId' => $brigadeId]);

    try {
        DB::beginTransaction();

        // Проверяем, существует ли бригада
        $brigadeExists = DB::table('brigades')
            ->where('id', $brigadeId)
            ->where('is_deleted', false) // если is_deleted — флаг удаления
            ->exists();

        if (!$brigadeExists) {
            DB::rollBack();
            $error = 'Бригада не найдена или уже удалена';
            \Log::error($error, ['brigadeId' => $brigadeId]);

            return response()->json([
                'success' => false,
                'message' => $error
            ], 404);
        }

        // Проверяем, назначена ли бригада в заявки
        $isAssigned = DB::table('requests')
            ->where('brigade_id', $brigadeId)
            ->exists();

        if ($isAssigned) {
            DB::rollBack();
            $error = 'Бригада назначена на одну или более заявок и не может быть удалена';
            \Log::error($error, ['brigadeId' => $brigadeId]);

            return response()->json([
                'success' => false,
                'message' => $error
            ], 400);
        }

        // Удаляем всех участников бригады
        $deletedMembers = DB::table('brigade_members')
            ->where('brigade_id', $brigadeId)
            ->delete();

        // \Log::info('Удалено участников бригады', [
        //     'brigadeId' => $brigadeId,
        //     'deletedMembersCount' => $deletedMembers
        // ]);

        // Удаляем саму бригаду
        $deleted = DB::table('brigades')
            ->where('id', $brigadeId)
            ->delete();

        if ($deleted) {
            DB::commit();
            // \Log::info('Бригада успешно удалена', ['brigadeId' => $brigadeId]);

            return response()->json([
                'success' => true,
                'message' => 'Бригада и все участники успешно удалены'
            ]);
        } else {
            DB::rollBack();
            $error = 'Не удалось удалить бригаду из базы данных';
            \Log::error($error, ['brigadeId' => $brigadeId]);

            return response()->json([
                'success' => false,
                'message' => $error
            ], 500);
        }
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Исключение при удалении бригады: ' . $e->getMessage(), [
            'brigadeId' => $brigadeId,
            'exception' => $e
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Произошла ошибка при удалении бригады',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    
    /**
     * Get brigade data via API
     */
    public function getBrigadeData($id)
    {
        // \Log::info('Начало обработки запроса для бригады ID: ' . $id);
        try {
            // Получаем данные о бригаде
            $brigade = DB::table('brigades')
                ->where('id', $id)
                ->first();

            if (!$brigade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Бригада не найдена'
                ], 404);
            }

            // Получаем данные о бригадире
            $leader = null;
            if ($brigade->leader_id) {
                $leader = DB::table('employees')
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                    ->where('employees.id', $brigade->leader_id)
                    ->select(
                        'employees.*',
                        'positions.name as position_name',
                        DB::raw("split_part(employees.fio, ' ', 1) as last_name"),
                        DB::raw("split_part(employees.fio, ' ', 2) as first_name"),
                        DB::raw("split_part(employees.fio, ' ', 3) as middle_name")
                    )
                    ->first();
            }

            // Получаем всех членов бригады с их должностями
            $members = DB::table('brigade_members')
                ->join('employees', 'brigade_members.employee_id', '=', 'employees.id')
                ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                ->where('brigade_members.brigade_id', $id)
                ->select(
                    'employees.*',
                    'positions.name as position_name',
                    DB::raw("split_part(employees.fio, ' ', 1) as last_name"),
                    DB::raw("split_part(employees.fio, ' ', 2) as first_name"),
                    DB::raw("split_part(employees.fio, ' ', 3) as middle_name")
                )
                ->get();
            
            // Добавляем информацию о том, кто является лидером в списке участников
            $members = $members->map(function($member) use ($brigade) {
                $member->is_leader = ($member->id == $brigade->leader_id);
                return $member;
            });

            return response()->json([
                'success' => true,
                'brigade' => $brigade,
                'leader' => $leader,
                'members' => $members
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting brigade data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении данных бригады',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
