<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Получение заявок за период по адресу и сотруднику
     */
    public function getAllPeriodByEmployeeAndAddress(Request $request)
    {
        try {
            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'addressId' => 'required|exists:addresses,id',
            ]);

            $employeeId = $validated['employeeId'];
            $addressId = $validated['addressId'];

            $query = DB::table('requests as r')
                ->selectRaw("
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
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where(function ($query) {
                    $query->where('b.is_deleted', false)
                        ->orWhereNull('b.id');
                })
                ->where('addr.id', $addressId)
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                })
                ->groupBy([
                    'r.id', 'c.fio', 'c.phone', 'c.organization',
                    'rs.name', 'rs.color', 'b.name', 'e.fio', 'op.fio',
                    'addr.street', 'addr.houses', 'addr.district',
                    'addr.city_id', 'ct.name', 'ct.postal_code',
                ])
                ->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC');

            $requests = $query->get();

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requests,
                'brigadeMembersWithDetails' => $this->getBrigadeMembersWithDetails(),
                'commentsByRequest' => $this->getCommentsByRequest(),
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriodByEmployeeAndAddress: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение заявок за период по сотруднику и адресу
     */
    public function getRequestsByEmployeeAddressAndDateRange(Request $request)
    {
        try {
            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'addressId' => 'required|exists:addresses,id',
                'startDate' => 'required|date_format:d.m.Y',
                'endDate' => 'required|date_format:d.m.Y',
            ]);

            $employeeId = $validated['employeeId'];
            $addressId = $validated['addressId'];
            $startDate = \Carbon\Carbon::createFromFormat('d.m.Y', $validated['startDate'])->startOfDay();
            $endDate = \Carbon\Carbon::createFromFormat('d.m.Y', $validated['endDate'])->endOfDay();

            $query = DB::table('requests as r')
                ->selectRaw("
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
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where(function ($query) {
                    $query->where('b.is_deleted', false)
                        ->orWhereNull('b.id');
                })
                ->where('addr.id', $addressId)
                ->whereBetween('r.execution_date', [$startDate, $endDate])
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                })
                ->groupBy([
                    'r.id', 'c.fio', 'c.phone', 'c.organization',
                    'rs.name', 'rs.color', 'b.name', 'e.fio', 'op.fio',
                    'addr.street', 'addr.houses', 'addr.district',
                    'addr.city_id', 'ct.name', 'ct.postal_code',
                ])
                ->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC');

            $requests = $query->get();

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requests,
                'brigadeMembersWithDetails' => $this->getBrigadeMembersWithDetails(),
                'commentsByRequest' => $this->getCommentsByRequest(),
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByEmployeeAddressAndDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение заявок за период по адресу
     */
    public function getAllPeriodByAddress(Request $request)
    {
        try {
            $brigadeMembersWithDetails = $this->getBrigadeMembersWithDetails();
            $commentsByRequest = $this->getCommentsByRequest();

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
            WHERE (b.is_deleted = false OR b.id IS NULL)
            AND addr.id = ?
            ORDER BY r.execution_date DESC, r.id DESC
        ';

            $requestsByAddressAndDateRange = DB::select($sql, [$addressId]);

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requestsByAddressAndDateRange,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'commentsByRequest' => $commentsByRequest,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriodByAddress: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за период по датам и адресу
     */
    public function getRequestsByAddressAndDateRange(Request $request)
    {
        try {
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
                            'author_name' => $comment->author_name,
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
                'comments_by_request' => $comments_by_request,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByAddressAndDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отображение страницы с отчетами по адресу
     */
    public function showAddressReports($addressId)
    {
        try {
            // Получить данные адреса
            $address = DB::table('addresses')
                ->join('cities', 'addresses.city_id', '=', 'cities.id')
                ->where('addresses.id', $addressId)
                ->select('addresses.*', 'cities.name as city_name')
                ->first();

            if (!$address) {
                abort(404, 'Адрес не найден');
            }

            // Получить заявки по адресу за весь период
            $requests = DB::table('requests as r')
                ->selectRaw("
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
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->where(function ($query) {
                    $query->where('b.is_deleted', false)
                        ->orWhereNull('b.id');
                })
                ->where('addr.id', $addressId)
                ->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC')
                ->get();

            return response()->json([
                'address' => $address,
                'requests' => $requests,
            ]);

            // return view('reports.address', compact('address', 'requests'));
        } catch (\Exception $e) {
            Log::error('Error in ReportController@showAddressReports: '.$e->getMessage());
            abort(500, 'Произошла ошибка при получении отчетов');
        }
    }

    /**
     * Получение списка адресов для отчетов
     */
    public function getAddresses()
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAddresses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка адресов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение списка сотрудников для отчетов
     */
    public function getEmployees()
    {
        try {
            $employees = DB::select('SELECT * FROM employees WHERE is_deleted = false ORDER BY fio');

            return response()->json($employees);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getEmployees: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка сотрудников',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за весь период по сотруднику
     */
    public function getAllPeriodByEmployee(Request $request)
    {
        try {
            // Тест
            // $data = [
            //     'success' => true,
            //     'debug' => false,
            //     'message' => 'getAllPeriodByEmployee',
            //     'request-all' => $request->all(),
            // ];

            // return response()->json($data);

            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'allPeriod' => 'required|boolean|in:1,true',
            ]);

            $employeeId = $validated['employeeId'];
            $allPeriod = $validated['allPeriod'] === true || $validated['allPeriod'] === '1' || $validated['allPeriod'] === 1;

            $query = DB::table('requests as r')
                ->selectRaw("
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
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where(function ($query) {
                    $query->where('b.is_deleted', false)
                        ->orWhereNull('b.id');
                })
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId); // ✅ упрощено и точно
                })
                ->groupBy(
                    'r.id', 'c.id', 'rs.id', 'b.id', 'e.id',
                    'op.id', 'addr.id', 'ct.id'
                )
                ->orderByDesc('r.execution_date')
                ->orderByDesc('r.id');

            $requestsAllPeriodByEmployee = $query->get();

            // Логируем SQL-запрос для отладки
            \Log::info('SQL Query in getAllPeriodByEmployee:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'employeeId' => $employeeId,
            ]);

            $brigadeMembersWithDetails = $this->getBrigadeMembersWithDetails();
            $commentsByRequest = $this->getCommentsByRequest();

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsAllPeriodByEmployee' => $requestsAllPeriodByEmployee,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'commentsByRequest' => $commentsByRequest,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriodByEmployee: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за весь период
     */
    public function getAllPeriod(Request $request)
    {
        try {
            // Тест
            // $data = [
            //     'success' => true,
            //     'debug' => false,
            //     'message' => 'Заявки успешно получены',
            //     'request-all' => $request->all(),
            // ];

            // return response()->json($data);

            $sql = 'SELECT r.*,
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
        WHERE r.execution_date IS NOT NULL ORDER BY execution_date DESC';

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
                            'author_name' => $comment->author_name,
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
                'comments_by_request' => $comments_by_request,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriod: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за период по датам
     */
    public function getRequestsByDateRange(Request $request)
    {
        try {
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
                            'author_name' => $comment->author_name,
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
                'comments_by_request' => $comments_by_request,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за период по датам и сотрудникам
     */
    public function getRequestsByEmployeeAndDateRange(Request $request)
    {
        try {
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
                            'author_name' => $comment->author_name,
                        ];
                    })->toArray();
                });

            // Преобразуем коллекцию в массив для передачи в представление
            $comments_by_request = $commentsByRequest->toArray();

            // Валидация входных данных
            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'startDate' => 'required|date_format:d.m.Y',
                'endDate' => 'required|date_format:d.m.Y|after_or_equal:startDate',
            ]);

            // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
            $startDate = \DateTime::createFromFormat('d.m.Y', $validated['startDate'])->format('Y-m-d');
            $endDate = \DateTime::createFromFormat('d.m.Y', $validated['endDate'])->format('Y-m-d');
            $employeeId = $validated['employeeId'];

            $query = DB::table('requests as r')
                ->selectRaw("
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
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where(function ($query) {
                    $query->where('b.is_deleted', false)
                        ->orWhereNull('b.id');
                })
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                })
                ->whereDate('r.execution_date', '>=', $startDate)
                ->whereDate('r.execution_date', '<=', $endDate)
                ->groupBy(
                    'r.id', 'c.id', 'rs.id', 'b.id', 'e.id',
                    'op.id', 'addr.id', 'ct.id'
                )
                ->orderByDesc('r.execution_date')
                ->orderByDesc('r.id');

            $requestsByEmployeeAndDateRange = $query->get();

            \Log::info('SQL Query:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $data = [
                'success' => true,
                'message' => 'Заявки для отчёта успешно получены',
                'requestsByEmployeeAndDateRange' => $requestsByEmployeeAndDateRange,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'comments_by_request' => $comments_by_request,
                'debug' => false,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByEmployeeAndDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение членов бригады с деталями
     */
    public function getBrigadeMembersWithDetails()
    {
        try {
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

            return $brigadeMembersWithDetails;
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getBrigadeMembersWithDetails: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Получение комментариев к заявкам
     */
    public function getCommentsByRequest()
    {
        try {
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
                            'author_name' => $comment->author_name,
                        ];
                    })->toArray();
                });

            $comments_by_request = $commentsByRequest->toArray();

            return $comments_by_request;
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getCommentsByRequest: '.$e->getMessage());

            return [];
        }
    }
}
