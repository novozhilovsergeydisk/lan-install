<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestFilterController extends Controller
{
    /**
     * Получить все статусы заявок
     */
    public function getStatuses()
    {
        try {
            $statuses = DB::table('request_statuses')
                ->select('id', 'name', 'color')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'statuses' => $statuses,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestFilterController@getStatuses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении статусов заявок',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить заявки по статусам
     */
    public function filterByStatuses(Request $request)
    {
        try {
            $statusIds = $request->input('statuses', []);

            if (! is_array($statusIds) || empty($statusIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не указаны ID статусов',
                ], 400);
            }

            $placeholders = implode(',', array_fill(0, count($statusIds), '?'));

            $requests = DB::select("
            SELECT
                r.*,
                rs.name AS status_name,
                rs.color AS status_color
            FROM requests r
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            WHERE r.status_id IN ($placeholders)
            ORDER BY r.id DESC
        ", $statusIds);

            return response()->json([
                'success' => true,
                'requests' => $requests,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in RequestFilterController@filterByStatuses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при фильтрации заявок по статусам',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
