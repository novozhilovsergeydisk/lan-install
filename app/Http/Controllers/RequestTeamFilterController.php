<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestTeamFilterController extends Controller
{
    /**
     * Получить заявки по бригадам
     */
    public function filterByTeams(Request $request)
    {
        $teamIds = $request->input('brigades', []);

        if (!is_array($teamIds) || empty($teamIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Не указаны ID бригад'
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
            'requests' => $requests
        ]);
    }

    /**
     * Получить список бригад за текущий день
     */
    public function brigadesCurrentDay()
    {
        $today = now()->toDateString();

        $sql = "SELECT e.id, b.id as brigade_id, e.fio AS name FROM brigades AS b JOIN employees AS e ON b.leader_id = e.id WHERE DATE(b.formation_date) >= '{$today}'";

        $leaders = DB::select($sql);

        return response()->json([
            'success' => true,
            '$today' => $today,
            '$sql' => $sql,
            '$leaders' => $leaders
        ]);
    }

    /**
     * Получить список бригадиров
     */
    public function getBrigadeLeaders()
    {
        $today = now()->toDateString();

        $sql = "SELECT e.id, b.id as brigade_id, e.fio AS name FROM brigades AS b JOIN employees AS e ON b.leader_id = e.id WHERE DATE(b.formation_date) >= '{$today}'";

        $leaders = DB::select($sql);

        return response()->json([
            'success' => true,
            '$today' => $today,
            '$sql' => $sql,
            '$leaders' => $leaders
        ]);
    }
}

