<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    function getRequestsByAddressAndDateRange(Request $request)
    {
        // Комплексный запрос для получения информации о членах бригад с данными о бригадах
        $brigadeMembersWithDetails = DB::select(
            'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
        );

        // Запрашиваем комментарии с привязкой к заявкам
        $requestComments_old = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                'Система' as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            ORDER BY rc.request_id, c.created_at
        ");

        $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

        // Группируем комментарии по ID заявки
        $commentsByRequest = collect($requestComments)
            ->groupBy('request_id')
            ->map(function ($comments) {
                return collect($comments)->map(function ($comment) {
                    return (object) [
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name
                    ];
                })->toArray();
            });

        // Преобразуем коллекцию в массив для передачи в представление
        $comments_by_request = $commentsByRequest->toArray();

        // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
        $startDate = \DateTime::createFromFormat('d.m.Y', $request->input('startDate'))->format('Y-m-d');
        $endDate = \DateTime::createFromFormat('d.m.Y', $request->input('endDate'))->format('Y-m-d');
        $addressId = $request->input('addressId');

        $sql = '
            SELECT
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
            LEFT JOIN employees op ON r.operator_id = op.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            LEFT JOIN cities ct ON addr.city_id = ct.id
            WHERE r.execution_date::date BETWEEN ? AND ? 
            AND (b.is_deleted = false OR b.id IS NULL)
            AND addr.id = ?
            ORDER BY r.execution_date DESC, r.id DESC
        ';

        $requestsByAddressAndDateRange = DB::select($sql, [$startDate, $endDate, $addressId]);

        $data = [
            'success' => true,
            'debug' => false,
            'message' => 'Заявки успешно получены',
            'requestsByAddressAndDateRange' => $requestsByAddressAndDateRange,
            'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
            'comments_by_request' => $comments_by_request
        ];

        return response()->json($data);
    }

    /**
     * Get addresses list for reports
     */
    public function getAddresses()
    {
        $addresses = DB::select('
            SELECT 
                addresses.id,
                addresses.city_id,
                addresses.street,
                addresses.district,
                addresses.houses,
                addresses.comments,
                addresses.house_type_id,
                addresses.responsible_person,
                cities.name as city_name,
                cities.region_id,
                cities.postal_code
            FROM addresses 
            JOIN cities ON addresses.city_id = cities.id 
            ORDER BY cities.name, addresses.street
        ');
        
        return response()->json($addresses);
    }

    /**
     * Get employees list for reports
     */
    public function getEmployees()
    {
        $employees = DB::select('SELECT * FROM employees WHERE is_deleted = false ORDER BY fio');
        return response()->json($employees);
    }

    public function getAllPeriod(Request $request)
    {
        // Тест
        // $data = [
        //     'success' => true,
        //     'debug' => false,
        //     'message' => 'Заявки успешно получены',
        //     'request-all' => $request->all(),
        // ];

        // return response()->json($data);

        $sql = "SELECT r.*,
            c.fio AS client_fio,
            c.phone AS client_phone,
            c.organization AS client_organization,
            rs.name AS status_name,
            rs.color AS status_color,
            b.name AS brigade_name,
            e.fio AS brigade_lead,
            op.fio AS operator_name,
            addr.street,
            addr.houses,
            addr.district,
            addr.city_id,
            ct.name AS city_name,
            ct.postal_code AS city_postal_code
        FROM requests r
        LEFT JOIN clients c ON r.client_id = c.id
        LEFT JOIN request_statuses rs ON r.status_id = rs.id
        LEFT JOIN brigades b ON r.brigade_id = b.id
        LEFT JOIN employees e ON b.leader_id = e.id
        LEFT JOIN employees op ON r.operator_id = op.id
        LEFT JOIN request_addresses ra ON r.id = ra.request_id
        LEFT JOIN addresses addr ON ra.address_id = addr.id
        LEFT JOIN cities ct ON addr.city_id = ct.id
        WHERE r.execution_date IS NOT NULL ORDER BY execution_date DESC";

        $requestsAllPeriod = DB::select($sql);

        $brigadeMembersWithDetails = DB::select(
            'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
        );

        $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

        $commentsByRequest = collect($requestComments)
            ->groupBy('request_id')
            ->map(function ($comments) {
                return collect($comments)->map(function ($comment) {
                    return (object) [
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name
                    ];
                })->toArray();
            });

        $comments_by_request = $commentsByRequest->toArray();

        $data = [
            'success' => true,
            'debug' => false,
            'message' => 'Заявки успешно получены',
            'requestsAllPeriod' => $requestsAllPeriod,
            'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
            'comments_by_request' => $comments_by_request
        ];

        return response()->json($data);
    }

    /**
     * Get requests by date range
     */
    public function getRequestsByDateRange(Request $request)
    {
        // Комплексный запрос для получения информации о членах бригад с данными о бригадах
        $brigadeMembersWithDetails = DB::select(
            'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
        );

        // Запрашиваем комментарии с привязкой к заявкам
        $requestComments_old = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                'Система' as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            ORDER BY rc.request_id, c.created_at
        ");

        $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

        // Группируем комментарии по ID заявки
        $commentsByRequest = collect($requestComments)
            ->groupBy('request_id')
            ->map(function ($comments) {
                return collect($comments)->map(function ($comment) {
                    return (object) [
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name
                    ];
                })->toArray();
            });

        // Преобразуем коллекцию в массив для передачи в представление
        $comments_by_request = $commentsByRequest->toArray();

        // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
        $startDate = \DateTime::createFromFormat('d.m.Y', $request->input('startDate'))->format('Y-m-d');
        $endDate = \DateTime::createFromFormat('d.m.Y', $request->input('endDate'))->format('Y-m-d');

        $sql = '
            SELECT
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
            LEFT JOIN employees op ON r.operator_id = op.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            LEFT JOIN cities ct ON addr.city_id = ct.id
            WHERE r.execution_date::date BETWEEN ? AND ? 
            AND (b.is_deleted = false OR b.id IS NULL)
            ORDER BY r.execution_date DESC, r.id DESC
        ';

        $requestsByDateRange = DB::select($sql, [$startDate, $endDate]);

        $data = [
            'success' => true,
            'debug' => false,
            'message' => 'Заявки успешно получены',
            'requestsByDateRange' => $requestsByDateRange,
            'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
            'comments_by_request' => $comments_by_request
        ];

        return response()->json($data);
    }

    /**
     * Get requests by date range and employee
     */
    public function getRequestsByEmployeeAndDateRange(Request $request)
    {
        // Комплексный запрос для получения информации о членах бригад с данными о бригадах
        $brigadeMembersWithDetails = DB::select(
            'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
        );

        // Запрашиваем комментарии с привязкой к заявкам
        $requestComments_old = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                'Система' as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            ORDER BY rc.request_id, c.created_at
        ");

        $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

        // Группируем комментарии по ID заявки
        $commentsByRequest = collect($requestComments)
            ->groupBy('request_id')
            ->map(function ($comments) {
                return collect($comments)->map(function ($comment) {
                    return (object) [
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name
                    ];
                })->toArray();
            });

        // Преобразуем коллекцию в массив для передачи в представление
        $comments_by_request = $commentsByRequest->toArray();

        // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
        $startDate = \DateTime::createFromFormat('d.m.Y', $request->input('startDate'))->format('Y-m-d');
        $endDate = \DateTime::createFromFormat('d.m.Y', $request->input('endDate'))->format('Y-m-d');
        $employeeId = $request->input('employeeId');

        $query = DB::table('requests as r')
            ->selectRaw("
                r.*,
                c.fio as client_fio,
                c.phone as client_phone,
                c.organization as client_organization,
                rs.name as status_name,
                rs.color as status_color,
                b.name as brigade_name,
                e.fio as brigade_lead,
                op.fio as operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name as city_name,
                ct.postal_code as city_postal_code,
                STRING_AGG(em.fio, ', ') as brigade_members
            ")
            ->leftJoin('clients as c', 'r.client_id', '=', 'c.id')
            ->leftJoin('request_statuses as rs', 'r.status_id', '=', 'rs.id')
            ->leftJoin('brigades as b', 'r.brigade_id', '=', 'b.id')
            ->leftJoin('employees as e', 'b.leader_id', '=', 'e.id')
            ->leftJoin('employees as op', 'r.operator_id', '=', 'op.id')
            ->leftJoin('request_addresses as ra', 'r.id', '=', 'ra.request_id')
            ->leftJoin('addresses as addr', 'ra.address_id', '=', 'addr.id')
            ->leftJoin('cities as ct', 'addr.city_id', '=', 'ct.id')
            ->leftJoin('brigade_members as bm', 'b.id', '=', 'bm.brigade_id')
            ->leftJoin('employees as em', 'bm.employee_id', '=', 'em.id')
            ->where(function($query) {
                $query->where('b.is_deleted', false)->orWhereNull('b.id');
            })
            ->whereExists(function($query) use ($employeeId) {
                $query->select(DB::raw(1))
                      ->from('brigade_members as bm2')
                      ->whereColumn('bm2.brigade_id', 'b.id')
                      ->where('bm2.employee_id', $employeeId);
            })
            ->groupBy('r.id', 'c.id', 'rs.id', 'b.id', 'e.id', 'op.id', 'addr.id', 'ct.id')
            ->orderByDesc('r.execution_date')
            ->orderByDesc('r.id');

        // Логируем параметры запроса
        \Log::info('Filter params:', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'employeeId' => $employeeId
        ]);

        // Изменяем фильтрацию дат
        $query->whereDate('r.execution_date', '>=', $startDate)
              ->whereDate('r.execution_date', '<=', $endDate);

        // Логируем SQL-запрос и привязки
        \Log::info('SQL Query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        $requestsByEmployeeAndDateRange = $query->get();

        $data = [
            'success' => true,
            'message' => 'Заявки для отчёта успешно получены',
            'requestsByEmployeeAndDateRange' => $requestsByEmployeeAndDateRange,
            'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
            'comments_by_request' => $comments_by_request,
            'debug' => false,
        ];

        return response()->json($data);
    }
}
