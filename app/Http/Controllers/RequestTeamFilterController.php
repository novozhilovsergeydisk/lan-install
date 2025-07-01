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
     * Получить список бригадиров
     */
    public function getBrigadeLeaders()
    {
        $leaders = DB::table('brigades as b')
            ->join('employees as e', 'b.leader_id', '=', 'e.id')
            ->whereDate('b.formation_date', '>=', now()->toDateString())
            ->select('e.id', 'e.fio as name')
            ->distinct()
            ->get();

        return response()->json([
            'success' => true,
            'leaders' => $leaders,
            'message' => $leaders->isEmpty() ? 'На сегодня не создано ни одной бригады!' : ''
        ]);
    }
}

