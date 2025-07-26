<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\RequestTeamFilterController;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Получает список ролей для селекта
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoles()
    {
        try {
            $roles = DB::table('roles')
                ->select('id', 'name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка ролей',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отмена заявки
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'reason' => 'required|string|max:1000'
            ]);

            // Начинаем транзакцию
            DB::beginTransaction();

            // Получаем заявку
            $requestData = DB::table('requests')
                ->where('id', $validated['request_id'])
                ->first();

            if (!$requestData) {
                throw new \Exception('Заявка не найдена');
            }

            // Проверяем, что заявка еще не отменена
            if ($requestData->status_id === 5) { // 5 - ID статуса "отменена"
                throw new \Exception('Заявка уже отменена');
            }

            // Получаем ID статуса "отменена"
            $canceledStatus = DB::table('request_statuses')
                ->where('name', 'отменена')
                ->first();

            if (!$canceledStatus) {
                throw new \Exception('Статус "отменена" не найден в системе');
            }

            $status_color = $canceledStatus->color;

            // Создаем комментарий об отмене
            $comment = "Заявка отменена. Причина: " . $validated['reason'];

            // Добавляем комментарий
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $comment,
                'created_at' => now()
            ]);

            // Привязываем комментарий к заявке
            DB::table('request_comments')->insert([
                'request_id' => $validated['request_id'],
                'comment_id' => $commentId,
                'user_id'    => $request->user()->id,
                'created_at' => now()
            ]);

            // Обновляем статус заявки
            DB::table('requests')
                ->where('id', $validated['request_id'])
                ->update([
                    'status_id' => $canceledStatus->id
                ]);

            // Фиксируем изменения
            DB::commit();

            // Получаем обновленное количество комментариев
            $commentsCount = DB::table('request_comments')
                ->where('request_id', $validated['request_id'])
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Заявка успешно отменена',
                'comments_count' => $commentsCount,
                'execution_date' => $requestData->execution_date,
                'status_color' => $status_color
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            DB::rollBack();
            Log::error('Ошибка при отмене заявки: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer a request to a new date
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'new_date' => 'required|date|after:today',
                'reason' => 'required|string|max:1000'
            ]);

            // Begin transaction
            DB::beginTransaction();

            // Get the request
            $requestData = DB::table('requests')
                ->where('id', $validated['request_id'])
                ->first();

            if (!$requestData) {
                throw new \Exception('Заявка не найдена');
            }

            // Create a comment about the transfer
            $comment = "Заявка перенесена с " . $requestData->execution_date . " на " . $validated['new_date'] . ". Причина: " . $validated['reason'];

            // Add comment
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $comment,
                'created_at' => now()
            ]);

            // Link comment to request
            DB::table('request_comments')->insert([
                'request_id' => $validated['request_id'],
                'comment_id' => $commentId,
                'user_id'    => $request->user()->id,
                'created_at' => now()
            ]);

            // Update the request date and status
            DB::table('requests')
                ->where('id', $validated['request_id'])
                ->update([
                    'execution_date' => $validated['new_date'],
                    'status_id' => 3 // ID статуса 'перенесена'
                ]);

            // Get comments count (including the one we just added)
            $commentsCount = DB::table('comments')
                ->join('request_comments', 'comments.id', '=', 'request_comments.comment_id')
                ->where('request_comments.request_id', $validated['request_id'])
                ->count();

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Заявка успешно перенесена',
                'execution_date' => $validated['new_date'],
                'comments_count' => $commentsCount
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при переносе заявки: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get list of addresses for select element
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Получить список всех сотрудников
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployees()
    {
        $employees = DB::select('SELECT * FROM employees where is_deleted = false ORDER BY fio');
        return response()->json($employees);
    }

    /**
     * Get list of addresses for select element
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAddresses()
    {
        $sql = "
            SELECT
                a.id,
                CONCAT(a.street, ', ', a.houses, ' [', CASE WHEN a.district = 'Не указан' THEN 'Район не указан' ELSE a.district END, '][', c.name, ']') as full_address,
                a.street,
                a.houses,
                c.name as city,
                a.district
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            ORDER BY a.street, a.houses
        ";

        $addresses = DB::select($sql);

        return response()->json($addresses);
    }

    /**
     * Получить список текущих бригад
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentBrigades()
    {
        $today = now()->toDateString();

        $sql = "SELECT e.id, b.id as brigade_id, e.fio AS leader_name, e.id as employee_id
                FROM brigades AS b
                JOIN employees AS e ON b.leader_id = e.id
                WHERE DATE(b.formation_date) >= '{$today}' and b.is_deleted = false";

        $brigades = DB::select($sql);

        return response()->json($brigades);
    }

    public function index()
    {
        // Получаем текущего пользователя (проверка аутентификации уже выполнена в роутере)
        $user = auth()->user();

        // Запрашиваем users
        // $users = DB::query('start transaction');
        $users = DB::select('SELECT * FROM users');
        // $users = DB::query('commit');

        $roles = DB::select('SELECT * FROM roles');

        // Запрашиваем clients
        $clients = DB::select('SELECT * FROM clients');

        // Запрашиваем brigades
        $brigades = DB::select('SELECT * FROM brigades');

        // Запрашиваем employees с паспортными данными и должностями
        $employees = DB::select("
            SELECT
                e.*,
                p.series_number,
                p.issued_at as passport_issued_at,
                p.issued_by as passport_issued_by,
                p.department_code,
                pos.name as position,
                c.brand as car_brand,
                c.license_plate as car_plate,
                u.email as user_email
            FROM employees e
            LEFT JOIN passports p ON e.id = p.employee_id
            LEFT JOIN positions pos ON e.position_id = pos.id
            LEFT JOIN cars c ON e.id = c.employee_id
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.is_deleted = false
            ORDER BY e.fio
        ");

        // Запрашиваем addresses
        $addresses = DB::select('SELECT * FROM addresses');

        // Запрашиваем positions
        $positions = DB::select('SELECT * FROM positions');

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

        // dd($brigadeMembersWithDetails);`

        // $brigadeMembersWithDetails = collect($brigadeMembersWithDetails);

        // Выводим содержимое для отладки
        // dd($brigadeMembersWithDetails);

        $brigade_members = DB::select('SELECT * FROM brigade_members');  // Оставляем старый запрос для обратной совместимости

        // Запрашиваем комментарии с привязкой к заявкам
        $requestComments = DB::select("
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

        // Запрашиваем request_addresses
        $request_addresses = DB::select('SELECT * FROM request_addresses');

        // Запрашиваем request_statuses
        $request_statuses = DB::select('SELECT * FROM request_statuses ORDER BY id');

        // Запрашиваем request_types
        $requests_types = DB::select('SELECT * FROM request_types ORDER BY id');

        $today = now()->toDateString();

        $sql = "SELECT e.id, b.id as brigade_id, e.fio AS leader_name, e.id as employee_id FROM brigades AS b JOIN employees AS e ON b.leader_id = e.id WHERE DATE(b.formation_date) >= '{$today}'";

        $brigadesCurrentDay = DB::select($sql);

        // 🔽 Комплексный запрос получения списка заявок с подключением к employees
        $sql = "SELECT
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
            WHERE r.execution_date::date = CURRENT_DATE AND (b.is_deleted = false OR b.id IS NULL)
            ORDER BY r.id DESC";

        if ($user->isFitter) {
            $sql = "SELECT
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
            WHERE r.execution_date::date = CURRENT_DATE 
            AND (b.is_deleted = false OR b.id IS NULL)
            AND EXISTS (
                SELECT 1
                FROM brigade_members bm
                JOIN employees emp ON bm.employee_id = emp.id
                WHERE bm.brigade_id = r.brigade_id
                AND emp.user_id = {$user->id}
            )
            ORDER BY r.id DESC";
        }

        $requests = DB::select($sql);

        //        dd($requestByDate);

        $flags = [
            'new' => 'new',
            'in_work' => 'in_work',
            'waiting_for_client' => 'waiting_for_client',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'under_review' => 'under_review',
            'on_hold' => 'on_hold',
        ];

        // Собираем все переменные для передачи в представление
        $viewData = [
            'user' => $user,
            'users' => $users,
            'clients' => $clients,
            'request_statuses' => $request_statuses,
            'requests' => $requests,
            'brigades' => $brigades,
            'employees' => $employees,
            'addresses' => $addresses,
            'brigade_members' => $brigade_members,
            'comments_by_request' => $comments_by_request,
            'request_addresses' => $request_addresses,
            'requests_types' => $requests_types,
            'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
            'brigadesCurrentDay' => $brigadesCurrentDay,
            'flags' => $flags,
            'positions' => $positions,
            'roles' => $roles,
            'isAdmin' => $user->isAdmin ?? false,
            'isUser' => $user->isUser ?? false,
            'isFitter' => $user->isFitter ?? false
        ];

        // Логируем данные для отладки
        // \Log::info('View data:', ['comments_by_request' => $comments_by_request]);

        return view('welcome', $viewData);
    }

    /**
     * Добавление комментария к заявке
     */
    public function addComment(Request $request)
    {
        // Логируем все входные данные
        \Log::info('Получен запрос на создание комментария:', [
            'all' => $request->all(),
            'json' => $request->json()->all(),
            'headers' => $request->headers->all(),
        ]);

        // Включаем логирование SQL-запросов
        \DB::enableQueryLog();

        try {
            \Log::info('=== НАЧАЛО ДОБАВЛЕНИЯ КОММЕНТАРИЯ ===');
            \Log::info('Метод запроса: ' . $request->method());
            \Log::info('Полный URL: ' . $request->fullUrl());
            \Log::info('Content-Type: ' . $request->header('Content-Type'));
            \Log::info('Все входные данные: ' . json_encode($request->all()));
            \Log::info('Сырые данные запроса: ' . file_get_contents('php://input'));

            // Валидируем входные данные
            $validated = $request->validate([
                'request_id' => 'required|exists:requests,id',
                'comment' => 'required|string|max:1000',
                '_token' => 'required|string'
            ]);

            \Log::info('Валидация пройдена успешно', $validated);

            // Проверяем существование заявки
            $requestExists = DB::selectOne(
                'SELECT COUNT(*) as count FROM requests WHERE id = ?',
                [$validated['request_id']]
            );

            $requestExists = $requestExists->count > 0;

            \Log::info('Проверка существования заявки:', [
                'request_id' => $validated['request_id'],
                'exists' => $requestExists
            ]);

            if (!$requestExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Заявка не найдена'
                ], 404);
            }

            // Начинаем транзакцию
            DB::beginTransaction();
            \Log::info('Начало транзакции');

            try {
                // Получаем структуру таблицы requests, чтобы найти колонку с датой
                $tableInfo = DB::selectOne(
                    "SELECT column_name
                     FROM information_schema.columns
                     WHERE table_name = 'requests'
                     AND data_type IN ('timestamp without time zone', 'timestamp with time zone', 'date', 'datetime')"
                );

                if (!$tableInfo) {
                    throw new \Exception('Не удалось определить колонку с датой в таблице requests');
                }

                $dateColumn = $tableInfo->column_name;

                // Получаем дату заявки
                $requestDate = DB::selectOne(
                    "SELECT $dateColumn as request_date FROM requests WHERE id = ?",
                    [$validated['request_id']]
                )->request_date;

                // Устанавливаем дату комментария как максимальную из текущей даты и даты заявки
                $comment = $validated['comment'];
                $commentDate = now();

                if ($commentDate < new \DateTime($requestDate)) {
                    $commentDate = new \DateTime($requestDate);
                }

                $createdAt = $commentDate->format('Y-m-d H:i:s');

                \Log::info('Данные для вставки комментария:', [
                    'comment' => $comment,
                    'created_at' => $createdAt,
                    'request_date' => $requestDate
                ]);

                // Вставляем комментарий
                $result = DB::insert(
                    'INSERT INTO comments (comment, created_at) VALUES (?, ?) RETURNING id',
                    [$comment, $createdAt]
                );

                if (!$result) {
                    throw new \Exception('Не удалось создать комментарий');
                }

                // Получаем ID вставленного комментария
                $commentId = DB::getPdo()->lastInsertId();
                \Log::info('Создан комментарий с ID: ' . $commentId);

                // Привязываем комментарий к заявке
                $requestId = $validated['request_id'];

                \Log::info('Данные для связи комментария с заявкой:', [
                    'request_id' => $requestId,
                    'comment_id' => $commentId,
                    'created_at' => $createdAt
                ]);

                // Вставляем связь с заявкой
                $result = DB::insert(
                    'INSERT INTO request_comments (request_id, comment_id, user_id, created_at) VALUES (?, ?, ?, ?)',
                    [$requestId, $commentId, $request->user()->id, $createdAt]
                );

                if (!$result) {
                    throw new \Exception('Не удалось привязать комментарий к заявке');
                }

                // Фиксируем транзакцию
                DB::commit();
                \Log::info('Транзакция успешно завершена');

                // Получаем обновленный список комментариев
                $comments = DB::select(
                    'SELECT c.* FROM comments c
                    INNER JOIN request_comments rc ON c.id = rc.comment_id
                    WHERE rc.request_id = ?
                    ORDER BY c.created_at DESC',
                    [$requestId]
                );

                // Логируем SQL-запросы
                \Log::info('Выполненные SQL-запросы:', \DB::getQueryLog());

                return response()->json([
                    'success' => true,
                    'message' => 'Комментарий успешно добавлен',
                    'comments' => $comments
                ]);
            } catch (\Exception $e) {
                // Откатываем изменения при ошибке
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                    \Log::warning('Транзакция откачена из-за ошибки');
                }

                $errorInfo = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'sql_queries' => \DB::getQueryLog()
                ];
                \Log::error('Ошибка при добавлении комментария:', $errorInfo);

                return response()->json([
                    'success' => false,
                    'message' => 'Произошла ошибка при добавлении комментария: ' . $e->getMessage(),
                    'error_details' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Критическая ошибка в методе addComment:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Критическая ошибка: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение заявок по дате
     */
    public function getRequestsByDate($date)
    {
        try {
            $user = auth()->user();

            // Загружаем роли пользователя
            $sql = "SELECT roles.name FROM user_roles
                JOIN roles ON user_roles.role_id = roles.id
                WHERE user_roles.user_id = " . $user->id;
            
            $roles = DB::select($sql);
            
            // Извлекаем только имена ролей из результатов запроса
            $roleNames = array_map(function($role) {
                return $role->name;
            }, $roles);
            
            // Устанавливаем роли и флаги
            $user->roles = $roleNames;
            $user->isAdmin = in_array('admin', $roleNames);
            $user->isUser = in_array('user', $roleNames);
            $user->isFitter = in_array('fitter', $roleNames);
            $user->user_id = $user->id;
            $user->sql = $sql;

            // Валидация даты
            $validator = validator(['date' => $date], [
                'date' => 'required|date_format:Y-m-d'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный формат даты. Ожидается YYYY-MM-DD',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $requestDate = $validated['date'];

            // Закомментирован тестовый блок искусственной ошибки
            // if ($requestDate === '2025-06-27') {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Тестовая ошибка: проверка обработки ошибок',
            //         'test_error' => true
            //     ], 200);
            // }

            // Получаем заявки с основной информацией

            // Если пользователь является фитчером, то получаем заявки только из бригады с его участием
            if ($user->isFitter) {
                $sqlRequestByDate = "
                    SELECT
                        r.*,
                        c.fio AS client_fio,
                        c.phone AS client_phone,
                        c.organization AS client_organization,
                        rs.name AS status_name,
                        rs.color AS status_color,
                        b.name AS brigade_name,
                        b.id AS brigade_id,
                        e.fio AS brigade_lead,
                        op.fio AS operator_name,
                        CONCAT(addr.street, ', д. ', addr.houses) as address,
                        addr.street,
                        addr.houses,
                        addr.district,
                        addr.city_id,
                        ct.name AS city_name,
                        (SELECT COUNT(*) FROM request_comments rc WHERE rc.request_id = r.id) as comments_count
                    FROM requests r
                    LEFT JOIN clients c ON r.client_id = c.id
                    LEFT JOIN request_statuses rs ON r.status_id = rs.id
                    LEFT JOIN brigades b ON r.brigade_id = b.id
                    LEFT JOIN employees e ON b.leader_id = e.id
                    LEFT JOIN employees op ON r.operator_id = op.id
                    LEFT JOIN request_addresses ra ON r.id = ra.request_id
                    LEFT JOIN addresses addr ON ra.address_id = addr.id
                    LEFT JOIN cities ct ON addr.city_id = ct.id
                    WHERE DATE(r.execution_date) = ? AND (b.is_deleted = false OR b.id IS NULL)
                    AND EXISTS (
                        SELECT 1
                        FROM brigade_members bm
                        JOIN employees emp ON bm.employee_id = emp.id
                        WHERE bm.brigade_id = r.brigade_id
                        AND emp.user_id = {$user->id}
                    )
                    ORDER BY r.id DESC
                ";
            } else {
                $sqlRequestByDate = "
                    SELECT
                        r.*,
                        c.fio AS client_fio,
                        c.phone AS client_phone,
                        c.organization AS client_organization,
                        rs.name AS status_name,
                        rs.color AS status_color,
                        b.name AS brigade_name,
                        b.id AS brigade_id,
                        e.fio AS brigade_lead,
                        op.fio AS operator_name,
                        CONCAT(addr.street, ', д. ', addr.houses) as address,
                        addr.street,
                        addr.houses,
                        addr.district,
                        addr.city_id,
                        ct.name AS city_name,
                        (SELECT COUNT(*) FROM request_comments rc WHERE rc.request_id = r.id) as comments_count
                    FROM requests r
                    LEFT JOIN clients c ON r.client_id = c.id
                    LEFT JOIN request_statuses rs ON r.status_id = rs.id
                    LEFT JOIN brigades b ON r.brigade_id = b.id
                    LEFT JOIN employees e ON b.leader_id = e.id
                    LEFT JOIN employees op ON r.operator_id = op.id
                    LEFT JOIN request_addresses ra ON r.id = ra.request_id
                    LEFT JOIN addresses addr ON ra.address_id = addr.id
                    LEFT JOIN cities ct ON addr.city_id = ct.id
                    WHERE DATE(r.execution_date) = ? AND (b.is_deleted = false OR b.id IS NULL)
                    ORDER BY r.id DESC
                ";
            }

            $requestByDate = DB::select($sqlRequestByDate, [$requestDate]);

            // return response()->json([
            //     'success' => false,
            //     'message' => 'Проверка обработки ошибок',
            //     'data' => $user,
            //     'roleNames' => $roleNames,
            //     'isAdmin' => $user->isAdmin,
            //     'isUser' => $user->isUser,
            //     'isFitter' => $user->isFitter,
            //     'user_id' => $user->user_id,
            //     'sql' => $user->sql,
            //     'sqlRequestByDate' => $sqlRequestByDate,
            // ], 200);

            // Преобразуем объекты в массивы для удобства работы
            $requests = array_map(function ($item) {
                return (array) $item;
            }, $requestByDate);

            // Получаем ID заявок для загрузки комментариев
            $requestIds = array_column($requests, 'id');
            $commentsByRequest = [];

            if (!empty($requestIds)) {
                // Загружаем комментарии для всех заявок одним запросом
                $comments = DB::select("
                    SELECT
                        c.id,
                        rc.request_id,
                        c.comment,
                        c.created_at,
                        'Система' as author_name
                    FROM request_comments rc
                    JOIN comments c ON rc.comment_id = c.id
                    WHERE rc.request_id IN (" . implode(',', $requestIds) . ')
                    ORDER BY c.created_at DESC
                ');

                // Группируем комментарии по ID заявки
                foreach ($comments as $comment) {
                    $commentData = [
                        'id' => $comment->id ?? null,
                        'comment' => $comment->comment ?? '',
                        'created_at' => $comment->created_at ?? now(),
                        'author_name' => $comment->author_name ?? 'Система'
                    ];
                    if (isset($comment->request_id)) {
                        $commentsByRequest[$comment->request_id][] = $commentData;
                    }
                }
            }

            // Добавляем комментарии к заявкам
            foreach ($requests as &$request) {
                $request['comments'] = $commentsByRequest[$request['id']] ?? [];
            }
            unset($request);

            // Преобразуем обратно в объекты, если нужно
            $requestByDate = array_map(function ($item) {
                return (object) $item;
            }, $requests);

            // Получаем ID бригад для загрузки членов
            $brigadeIds = array_filter(array_column($requestByDate, 'brigade_id'));
            $brigadeMembers = [];
            $brigadeLeaders = [];

            if (!empty($brigadeIds)) {
                // Получаем всех членов бригад для загруженных заявок
                $members = DB::select('
                    SELECT
                        bm.brigade_id,
                        e.fio as member_name,
                        e.phone as member_phone,
                        e.position_id,
                        b.leader_id,
                        el.fio as employee_leader_name
                    FROM brigade_members bm
                    JOIN brigades b ON bm.brigade_id = b.id
                    JOIN employees e ON bm.employee_id = e.id
                    LEFT JOIN employees el ON b.leader_id = el.id
                    WHERE bm.brigade_id IN (' . implode(',', $brigadeIds) . ')
                ');

                // Группируем членов по ID бригады и сохраняем информацию о бригадире
                $brigadeLeaders = [];

                foreach ($members as $member) {
                    // Сохраняем информацию о бригадире
                    if (!isset($brigadeLeaders[$member->brigade_id]) && $member->employee_leader_name) {
                        $brigadeLeaders[$member->brigade_id] = $member->employee_leader_name;
                    }

                    $brigadeMembers[$member->brigade_id][] = [
                        'name' => $member->member_name,
                        'phone' => $member->member_phone,
                        'position_id' => $member->position_id
                    ];
                }
            }

            // Получаем ID заявок для загрузки комментариев
            $requestIds = array_column($requestByDate, 'id');
            $commentsByRequest = [];

            if (!empty($requestIds)) {
                // Получаем все комментарии для загруженных заявок
                $comments = DB::select("
                    SELECT
                        rc.request_id,
                        c.id as comment_id,
                        c.comment,
                        c.created_at,
                        'Система' as author_name
                    FROM request_comments rc
                    JOIN comments c ON rc.comment_id = c.id
                    WHERE rc.request_id IN (" . implode(',', $requestIds) . ')
                    ORDER BY c.created_at DESC
                ');

                // Группируем комментарии по ID заявки
                foreach ($comments as $comment) {
                    $commentsByRequest[$comment->request_id][] = [
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name
                    ];
                }
            }

            // $user = auth()->user();

            // // Загружаем роли пользователя
            // $sql = "SELECT roles.name FROM user_roles
            //     JOIN roles ON user_roles.role_id = roles.id
            //     WHERE user_roles.user_id = " . $user->id;
            
            // $roles = DB::select($sql);
            
            // // Извлекаем только имена ролей из результатов запроса
            // $roleNames = array_map(function($role) {
            //     return $role->name;
            // }, $roles);
            
            // // Устанавливаем роли и флаги
            // $user->roles = $roleNames;
            // $user->isAdmin = in_array('admin', $roleNames);
            // $user->isUser = in_array('user', $roleNames);
            // $user->isFitter = in_array('fitter', $roleNames);
            // $user->user_id = $user->id;
            // $user->sql = $sql;

            // Добавляем членов бригады, информацию о бригадире и комментарии к каждой заявке
            $result = array_map(function ($request) use ($brigadeMembers, $brigadeLeaders, $commentsByRequest, $user) {
                $brigadeId = $request->brigade_id;
                $request->brigade_members = $brigadeMembers[$brigadeId] ?? [];
                $request->brigade_leader_name = $brigadeLeaders[$brigadeId] ?? null;
                $request->comments = $commentsByRequest[$request->id] ?? [];
                $request->comments_count = count($request->comments);
                $request->isAdmin = $user->isAdmin ?? false;
                $request->isUser = $user->isUser ?? false;
                $request->isFitter = $user->isFitter ?? false;
                $request->sql = $user->sql;
                $request->user_id = $user->id;
                return $request;
            }, $requestByDate);

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result)
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении заявок: ' . $e->getMessage(), [
                'exception' => $e,
                'date' => $date ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении заявок: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Получение комментариев к заявке
     */
    public function getComments($requestId)
    {
        $comments = DB::select("
            SELECT
                c.id,
                c.comment,
                c.created_at,
                COALESCE(u.name, 'Система') AS author_name,
                c.created_at AS formatted_date
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            WHERE rc.request_id = ?
            ORDER BY c.created_at DESC
        ", [$requestId]);

        // Format the date for each comment
        foreach ($comments as &$comment) {
            $date = new \DateTime($comment->created_at);
            $comment->formatted_date = $date->format('d.m.Y');
            if ($comment->author_name === 'Система') {
                $comment->author_name = $comment->formatted_date;
            }
            unset($comment->formatted_date);
        }

        return response()->json($comments);
    }

    /**
     * Close the specified request.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function closeRequest($id, Request $request)
    {
        try {
            // Для отладки
            // return response()->json([
            //     'success' => true,
            //     'message' => 'Заявка успешно закрыта (test)',
            //     'RequestID' => $id,
            //     'RequestComment' => $request->input('comment'),
            //     'RequestUncompletedWorks' => $request->input('uncompleted_works')
            // ]);

            // Начинаем транзакцию
            DB::beginTransaction();

            // Обновляем статус заявки на 'выполнена' (ID 4)
            $updated = DB::table('requests')
                ->where('id', $id)
                ->update(['status_id' => 4]);

            if ($updated) {
                // Создаем комментарий
                $commentId = DB::table('comments')->insertGetId([
                    'comment' => $request->input('comment', 'Заявка закрыта'),
                    'created_at' => now()
                ]);

                // Связываем комментарий с заявкой
                DB::table('request_comments')->insert([
                    'request_id' => $id,
                    'comment_id' => $commentId,
                    'user_id'    => $request->user()->id,
                    'created_at' => now()
                ]);

                // Если отмечен чекбокс "Недоделанные работы", добавляем запись в таблицу incomplete_works
                if ($request->input('uncompleted_works')) {
                    DB::table('incomplete_works')->insert([
                        'request_id' => $id,
                        'description' => $request->input('comment', 'Недоделанные работы'),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // И создаем заявку на завтра с комментарием о недоделанных работах

                    // Получаем ID сотрудника, связанного с текущим пользователем
                    $employeeId = DB::table('employees')
                        ->where('user_id', Auth::id())
                        ->value('id');

                        //

                    // Если не нашли сотрудника, используем ID по умолчанию
                    if (!$employeeId) {
                        throw new \Exception('Не удалось найти сотрудника для текущего пользователя');
                    }

                    // Получаем данные текущей заявки
                    $currentRequest = DB::table('requests')->where('id', $id)->first();

                    // Генерируем номер заявки
                    $count = DB::table('requests')->count() + 1;
                    $requestNumber = 'REQ-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

                    // Создаем новую заявку на завтра
                    $newRequestId = DB::table('requests')->insertGetId([
                        'number' => $requestNumber,
                        'client_id' => $currentRequest->client_id, // Копируем client_id из текущей заявки
                        'brigade_id' => null,
                        'status_id' => DB::table('request_statuses')->where('name', 'перенесена')->first()->id,
                        'request_type_id' => DB::table('request_types')->where('name', 'монтаж')->first()->id,
                        'operator_id' => $employeeId, // Используем ID сотрудника
                        'execution_date' => now()->addDay()->toDateString(),
                        'request_date' => now()->toDateString()
                    ]);

                    // Получаем адрес текущей заявки
                    $requestAddress = DB::table('request_addresses')
                        ->where('request_id', $id)
                        ->first();

                    // Если адрес найден, копируем его для новой заявки
                    if ($requestAddress) {
                        DB::table('request_addresses')->insert([
                            'request_id' => $newRequestId,
                            'address_id' => $requestAddress->address_id
                        ]);
                    }
                }

                // Фиксируем изменения
                DB::commit();

                // Формируем ответ JSON
                $response = [
                    'success' => true,
                    'message' => 'Заявка успешно закрыта',
                    'comment_id' => $commentId
                ];

                // Если была создана новая заявка на недоделанные работы, добавляем её ID в ответ
                if (isset($newRequestId)) {
                    // Связываем комментарий с заявкой
                    DB::table('request_comments')->insert([
                        'request_id' => $newRequestId,
                        'comment_id' => $commentId,
                        'user_id'    => Auth::id(), // ID пользователя из аутентификации
                        'created_at' => now()
                    ]);

                    $response['new_request_id'] = $newRequestId;
                    $response['new_request_number'] = $requestNumber;
                }

                return response()->json($response);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить заявку'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of request types
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRequestTypes()
    {
        try {
            $types = DB::select('SELECT id, name, color FROM request_types ORDER BY name');
            return response()->json($types);
        } catch (\Exception $e) {
            \Log::error('Error getting request types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении типов заявок',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of request statuses
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRequestStatuses()
    {
        try {
            $statuses = DB::select('SELECT id, name, color FROM request_statuses ORDER BY name');
            return response()->json($statuses);
        } catch (\Exception $e) {
            \Log::error('Error getting request statuses: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статусов заявок',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of brigades
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBrigades()
    {
        try {
            $brigades = DB::select('SELECT id, name FROM brigades ORDER BY name');
            return response()->json($brigades);
        } catch (\Exception $e) {
            \Log::error('Error getting brigades: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка бригад',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of operators
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOperators()
    {
        try {
            $operators = DB::select('SELECT id, fio FROM employees WHERE position_id = 1 and is_deleted = false ORDER BY fio');
            return response()->json($operators);
        } catch (\Exception $e) {
            \Log::error('Error getting operators: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка операторов',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of cities
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCities()
    {
        try {
            \Log::info('Получение списка городов из базы данных');

            // Получаем только необходимые поля
            $cities = DB::select('SELECT id, name FROM cities ORDER BY name');

            // Преобразуем объекты в массивы для корректной сериализации в JSON
            $cities = array_map(function ($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name
                ];
            }, $cities);

            \Log::info('Найдено городов: ' . count($cities));
            \Log::info('Пример данных: ' . json_encode(array_slice($cities, 0, 3), JSON_UNESCAPED_UNICODE));

            return response()->json($cities);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении списка городов: ' . $e->getMessage());
            \Log::error('Трассировка: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка городов',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comments count for a request
     *
     * @param int $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCommentsCount($requestId)
    {
        $count = DB::table('request_comments')
            ->where('request_id', $requestId)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Обновление комментария
     *
     * @param int $id ID комментария
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateComment($id, Request $request)
    {
        // Логируем входные данные
        \Log::info('Получен запрос на обновление комментария:', [
            'id' => $id,
            'content' => $request->input('content'),
        ]);

        try {
            // Проверяем, существует ли комментарий
            $comment = DB::table('comments')->where('id', $id)->first();

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Комментарий не найден'
                ], 404);
            }

            // Обновляем комментарий
            DB::table('comments')
                ->where('id', $id)
                ->update([
                    'comment' => $request->input('content')
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Комментарий успешно обновлен',
                'comment' => DB::table('comments')->where('id', $id)->first()
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при обновлении комментария:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении комментария: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeRequest(Request $request)
    {
        // Проверяем авторизацию пользователя
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходима авторизация',
                'redirect' => '/login'
            ], 401);
        }

        // Проверяем наличие необходимых ролей
        $user = auth()->user();
        
        // Проверяем, загружены ли роли пользователя
        if (!isset($user->roles) || !is_array($user->roles)) {
            // Если роли не загружены, загружаем их из базы
            $roles = DB::table('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('user_roles.user_id', $user->id)
                ->pluck('roles.name')
                ->toArray();
            
            $user->roles = $roles;
            $user->isAdmin = in_array('admin', $roles);
        }
        
        // Проверяем права доступа
        $allowedRoles = ['admin'];
        $hasAllowedRole = false;
        
        if (is_array($user->roles)) {
            foreach ($user->roles as $role) {
                if (in_array($role, $allowedRoles)) {
                    $hasAllowedRole = true;
                    break;
                }
            }
        }
        
        if (!$hasAllowedRole) {
            return response()->json([
                'success' => false,
                'message' => 'У вас недостаточно прав для создания заявки. Необходима одна из ролей: ' . implode(', ', $allowedRoles),
                'user_roles' => $user->roles ?? []
            ], 403);
        }

        // Включаем логирование SQL-запросов
        \DB::enableQueryLog();
        DB::beginTransaction();
        $isExistingClient = false;

        try {
            // Логируем все входные данные для отладки
            \Log::info('=== НАЧАЛО ОБРАБОТКИ ЗАПРОСА ===');
            \Log::info('Все входные данные:', $request->all());

            // Получаем данные из запроса
            $input = $request->all();

            // Если operator_id не указан, используем ID текущего пользователя или значение по умолчанию
            $userId = auth()->id(); // ID пользователя из авторизации
            $input['user_id'] = $userId; // Сохраняем ID пользователя для логирования
            \Log::info('ID авторизованного пользователя: ' . $userId);

            // Проверяем наличие сотрудника только если указан user_id
            $employeeId = null;
            if ($userId) {
                $employee = DB::table('employees')
                    ->where('user_id', $userId)
                    ->first();

                if ($employee) {
                    $employeeId = $employee->id;
                    $input['operator_id'] = $employeeId; // Устанавливаем operator_id как ID сотрудника, а не пользователя
                    \Log::info('Найден сотрудник с ID: ' . $employeeId . ' для пользователя: ' . $userId);
                } else {
                    \Log::info('Сотрудник не найден для пользователя с ID: ' . $userId . ', но продолжаем создание заявки');
                }
            } else {
                \Log::info('Оператор не указан, создаем заявку без привязки к сотруднику');
            }

            // Формируем массив для валидации
            $validationData = [
                'client_name' => $input['client_name'] ?? null,
                'client_phone' => $input['client_phone'] ?? null,
                'client_organization' => $input['client_organization'] ?? null,
                'request_type_id' => $input['request_type_id'] ?? null,
                'status_id' => $input['status_id'] ?? null,
                'comment' => $input['comment'] ?? null,
                'execution_date' => $input['execution_date'] ?? null,
                'execution_time' => $input['execution_time'] ?? null,
                'brigade_id' => $input['brigade_id'] ?? null,
                'operator_id' => $employeeId,
                'address_id' => $input['address_id'] ?? null
            ];

            // Используем ранее найденный employeeId или null
            $validationData['operator_id'] = $employeeId;

            \Log::info('Используем для заявки operator_id:', [
                'user_id' => $userId,
                'employee_id' => $employeeId
            ]);

            // Правила валидации
            $rules = [
                'client_name' => 'nullable|string|max:255',
                'client_phone' => 'nullable|string|max:20',
                'client_organization' => 'nullable|string|max:255',
                'request_type_id' => 'required|exists:request_types,id',
                'status_id' => 'required|exists:request_statuses,id',
                'comment' => 'nullable|string',
                'execution_date' => 'required|date',
                'execution_time' => 'nullable|date_format:H:i',
                'brigade_id' => 'nullable|exists:brigades,id',
                'operator_id' => 'nullable|exists:employees,id',
                'address_id' => 'required|exists:addresses,id'
            ];

            // Логируем входные данные для отладки
            \Log::info('Входные данные для валидации:', [
                'validationData' => $validationData,
                'rules' => $rules
            ]);

            // Валидация входных данных
            $validator = \Validator::make($validationData, $rules);

            if ($validator->fails()) {
                \Log::error('Ошибка валидации:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            \Log::info('Валидированные данные:', $validated);

            // 1. Подготовка данных клиента
            $fio = trim($validated['client_name'] ?? '');
            $phone = trim($validated['client_phone'] ?? '');
            $organization = trim($validated['client_organization'] ?? '');

            // 2. Валидация данных клиента
            $clientData = [
                'fio' => $fio,
                'phone' => $phone,
                'email' => '', // Пустая строка, так как поле не может быть NULL
                'organization' => $organization
            ];

            $clientRules = [
                'fio' => 'string|max:255',
                'phone' => 'string|max:50',
                'email' => 'string|max:255',
                'organization' => 'string|max:255'
            ];

            $clientValidator = Validator::make($clientData, $clientRules);
            if ($clientValidator->fails()) {
                \Log::error('Ошибка валидации данных клиента:', $clientValidator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации данных клиента',
                    'errors' => $clientValidator->errors()
                ], 422);
            }

            // 3. Поиск существующего клиента по телефону (если телефон указан)
            $client = null;
            $clientId = null;

            // Поиск клиента по телефону, ФИО или организации
            $query = DB::table('clients');
            $foundClient = false;

            if (!empty($clientData['fio'])) {
                if ($foundClient) {
                    $query->orWhere('fio', $clientData['fio']);
                } else {
                    $query->where('fio', $clientData['fio']);
                    $foundClient = true;
                }
            } elseif (!empty($clientData['phone'])) {
                $query->where('phone', $clientData['phone']);
                $foundClient = true;
            } elseif (!empty($clientData['organization'])) {
                if ($foundClient) {
                    $query->orWhere('organization', $clientData['organization']);
                } else {
                    $query->where('organization', $clientData['organization']);
                    $foundClient = true;
                }
            }

            // Выполняем запрос только если хотя бы одно поле заполнено
            $client = $foundClient ? $query->first() : null;

            // $response = [
            //     'success' => true,
            //     'message' => 'Тестирование',
            //     'data' => [$client]
            // ];

            // return response()->json($response);

            // 4. Создание или обновление клиента
            try {
                if ($client) {
                    // Обновляем существующего клиента
                    DB::table('clients')
                        ->where('id', $client->id)
                        ->update([
                            'fio' => $clientData['fio'],
                            'phone' => $clientData['phone'],
                            'email' => $clientData['email'],
                            'organization' => $clientData['organization']
                        ]);
                    $clientId = $client->id;
                    $clientState = 'updated';
                    \Log::info('Обновлен существующий клиент:', ['id' => $clientId]);
                } else {
                    // Создаем нового клиента (даже если все поля пустые)
                    $clientId = DB::table('clients')->insertGetId([
                        'fio' => $clientData['fio'],
                        'phone' => $clientData['phone'],
                        'email' => $clientData['email'],
                        'organization' => $clientData['organization']
                    ]);
                    $clientState = 'created';
                    \Log::info('Создан новый клиент:', ['id' => $clientId]);
                }
            } catch (\Exception $e) {
                \Log::error('Ошибка при сохранении клиента: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при сохранении данных клиента',
                    'error' => $e->getMessage()
                ], 500);
            }

            // 3. Создаем заявку
            $requestData = [
                'client_id' => $clientId,
                'request_type_id' => $validated['request_type_id'],
                'status_id' => $validated['status_id'],
                'execution_date' => $validated['execution_date'],
                'execution_time' => $validated['execution_time'],
                'brigade_id' => $validated['brigade_id'] ?? null,
                'operator_id' => $validated['operator_id']
            ];

            // Генерируем номер заявки
            $countQuery = DB::table('requests');
            $count = $countQuery->count() + 1;
            $requestNumber = 'REQ-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            $requestData['number'] = $requestNumber;

            // Устанавливаем текущую дату (учитывая часовой пояс из конфига Laravel)
            $currentDate = now()->toDateString();
            $requestData['request_date'] = $currentDate;

            // Вставляем заявку с помощью DB::insert и получаем ID
            $result = DB::select(
                'INSERT INTO requests (client_id, request_type_id, status_id, execution_date, execution_time, brigade_id, operator_id, number, request_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id',
                [
                    $clientId,
                    $validated['request_type_id'],
                    $validated['status_id'],
                    $validated['execution_date'],
                    $validated['execution_time'] ?? null,
                    $validated['brigade_id'] ?? null,
                    $employeeId,
                    $requestNumber,
                    $currentDate
                ]
            );

            $requestId = $result[0]->id;

            \Log::info('Результат вставки заявки:', ['result' => $result, 'type' => gettype($result)]);

            if (empty($result)) {
                throw new \Exception('Не удалось создать заявку');
            }

            $requestId = $result[0]->id;
            \Log::info('Создана заявка с ID:', ['id' => $requestId]);

            // 4. Создаем комментарий, только если он не пустой
            $commentText = trim($validated['comment'] ?? '');
            $newCommentId = null;

            if (!empty($commentText)) {
                try {
                    // Вставляем комментарий без поля updated_at
                    $commentSql = "INSERT INTO comments (comment, created_at) VALUES (?, ?) RETURNING id";
                    $bindings = [
                        $commentText,
                        now()->toDateTimeString()
                    ];

                    \Log::info('SQL для вставки комментария:', ['sql' => $commentSql, 'bindings' => $bindings]);

                    $commentResult = DB::selectOne($commentSql, $bindings);
                    $newCommentId = $commentResult ? $commentResult->id : null;

                    if (!$newCommentId) {
                        throw new \Exception('Не удалось получить ID созданного комментария');
                    }

                    \Log::info('Создан комментарий с ID:', ['id' => $newCommentId]);

                    // Создаем связь между заявкой и комментарием
                    DB::table('request_comments')->insert([
                        'request_id' => $requestId,
                        'comment_id' => $newCommentId,
                        'user_id'    => $request->user()->id,
                        'created_at' => now()->toDateTimeString()
                    ]);

                    \Log::info('Связь между заявкой и комментарием создана', [
                        'request_id' => $requestId,
                        'comment_id' => $newCommentId
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Ошибка при создании комментария: ' . $e->getMessage());
                    // Продолжаем выполнение, так как комментарий не является обязательным
                }
            }

            // 5. Связываем существующий адрес с заявкой
            $addressId = $validated['address_id'];

            // Получаем информацию об адресе
            $address = DB::table('addresses')->find($addressId);

            if (!$address) {
                throw new \Exception('Указанный адрес не найден');
            }

            // Связываем адрес с заявкой без использования временных меток
            DB::table('request_addresses')->insert([
                'request_id' => $requestId,
                'address_id' => $addressId
                // Убраны created_at и updated_at, так как их нет в таблице
            ]);

            \Log::info('Создана связь заявки с адресом:', [
                'request_id' => $requestId,
                'address_id' => $addressId
            ]);

            // 🔽 Комплексный запрос получения списка заявок с подключением к employees
            $requestById = DB::select('
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
                WHERE r.id = ' . $requestId . '
            ');

            // Преобразуем результат запроса в объект, если это массив
            if (is_array($requestById) && !empty($requestById)) {
                $requestById = (object)$requestById[0];
            }

            // Формируем ответ
            $response = [
                'success' => true,
                'message' => $clientId
                    ? ($isExistingClient ? 'Использован существующий клиент' : 'Создан новый клиент')
                    : 'Заявка создана без привязки к клиенту',
                'data' => [
                    'request' => [
                        'id' => $requestId,
                        'number' => $requestNumber,
                        'type_id' => $validated['request_type_id'],
                        'status_id' => $validated['status_id'],
                        'execution_date' => $validated['execution_date'],
                        'requestById' => $requestById,
                        'isAdmin' => $user->isAdmin,
                    ],
                    'client' => $clientId ? [
                        'id' => $clientId,
                        'fio' => $fio,
                        'phone' => $phone,
                        'organization' => $organization,
                        'is_new' => !$isExistingClient,
                        'state' => $clientState
                    ] : null,
                    'address' => [
                        'id' => $address->id,
                        'city_id' => $address->city_id,
                        'city_name' => isset($requestById->city_name) ? $requestById->city_name : null,
                        'city_postal_code' => isset($requestById->city_postal_code) ? $requestById->city_postal_code : null,
                        'street' => $address->street,
                        'house' => $address->houses,
                        'district' => $address->district,
                        'comment' => $address->comments ?? ''
                    ],
                    'comment' => $newCommentId ? [
                        'id' => $newCommentId,
                        'text' => $commentText
                    ] : null
                ]
            ];

            // Фиксируем изменения, если все успешно
            DB::commit();
            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при создании заявки:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заявки: ' . $e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    public function getRequestByEmployee() {
        try {
            $employeeId = auth()->user()->employee_id;

            $requests = DB::select("SELECT * FROM requests WHERE operator_id = {$employeeId}");

            return response()->json([
                'success' => true,
                'message' => 'Заявки успешно получены',
                'data' => $requests
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении заявок:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении заявок: ' . $e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
}
