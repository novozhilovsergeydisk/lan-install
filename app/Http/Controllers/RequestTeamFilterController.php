<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestTeamFilterController extends Controller
{
    /**
     * Получить заявки по бригадам
     */
    public function filterByTeams(Request $request)
    {
        try {
            $teamIds = $request->input('brigades', []);

            if (! is_array($teamIds) || empty($teamIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не указаны ID бригад',
                ], 400);
            }

            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));

            $requests = DB::select("
            SELECT
                r.*,
                c.fio AS client_fio,
                rs.name AS status_name,
                rs.color AS status_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
            WHERE r.brigade_id IN ($placeholders)
            ORDER BY r.id DESC
        ", $teamIds);

            return response()->json([
                'success' => true,
                'requests' => $requests,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestTeamFilterController@filterByTeams: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при фильтрации заявок по бригадам',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function brigadesInfoCurrentDay()
    {
        try {
            $today = now()->toDateString();

            $sql = "SELECT
            b.id AS brigade_id,
            b.name AS brigade_name,
            b.formation_date,
            jsonb_build_object(
                'id', leader.id,
                'fio', leader.fio,
                'phone', leader.phone,
                'position', p.name
            ) AS leader_info,
            (
                SELECT jsonb_agg(
                    jsonb_build_object(
                        'id', e.id,
                        'fio', e.fio,
                        'phone', e.phone,
                        'is_leader', CASE WHEN e.id = b.leader_id THEN true ELSE false END
                    )
                )
                FROM brigade_members bm
                JOIN employees e ON bm.employee_id = e.id
                WHERE bm.brigade_id = b.id
            ) AS members,
            (SELECT COUNT(*) FROM brigade_members WHERE brigade_id = b.id) AS member_count
            FROM 
                brigades b
            JOIN 
                employees leader ON b.leader_id = leader.id
            LEFT JOIN 
                positions p ON leader.position_id = p.id
            WHERE
                b.formation_date = '{$today}'
            ORDER BY 
                b.name;";

            $brigadesInfoCurrentDay = DB::select($sql);

            return response()->json([
                'success' => true,
                '$today' => $today,
                '$sql' => $sql,
                '$brigadesInfoCurrentDay' => $brigadesInfoCurrentDay,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestTeamFilterController@brigadesInfoCurrentDay: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении информации о бригадах',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список бригад за текущий день
     */
    public function brigadesCurrentDay()
    {
        try {
            $today = now()->toDateString();

            $sql = "SELECT e.id, b.id as brigade_id, e.fio AS name FROM brigades AS b JOIN employees AS e ON b.leader_id = e.id WHERE b.is_deleted = false and DATE(b.formation_date) >= '{$today}'";

            $leaders = DB::select($sql);

            return response()->json([
                'success' => true,
                '$today' => $today,
                '$sql' => $sql,
                '$leaders' => $leaders,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestTeamFilterController@brigadesCurrentDay: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении бригад на текущий день',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список бригадиров
     */
    public function getBrigadeLeaders()
    {
        try {
            $today = now()->toDateString();

            $sql = "SELECT e.id, b.id as brigade_id, e.fio AS name FROM brigades AS b JOIN employees AS e ON b.leader_id = e.id WHERE b.is_deleted = false and DATE(b.formation_date) >= '{$today}'";

            $leaders = DB::select($sql);

            return response()->json([
                'success' => true,
                '$today' => $today,
                '$sql' => $sql,
                '$leaders' => $leaders,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestTeamFilterController@getBrigadeLeaders: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении бригадиров',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
