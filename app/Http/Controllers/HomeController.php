<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
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
    
    public function index()
    {
        // Получаем текущего пользователя
        $user = Auth::user();

        // Запрашиваем users
        // $users = DB::query('start transaction');
        $users = DB::select('SELECT * FROM users');
        // $users = DB::query('commit');

        // Запрашиваем clients
        $clients = DB::select('SELECT * FROM clients');

        // Запрашиваем brigades
        $brigades = DB::select('SELECT * FROM brigades');

        // Запрашиваем employees
        $employees = DB::select('SELECT * FROM employees');

        // Запрашиваем addresses
        $addresses = DB::select('SELECT * FROM addresses');

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
                e.position_id as employee_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id'
        );

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

        // 🔽 Комплексный запрос получения списка заявок с подключением к employees
        $requests = DB::select('
            SELECT
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                rs.name AS status_name,
                rs.color AS status_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
            LEFT JOIN employees op ON r.operator_id = op.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            WHERE r.request_date::date = CURRENT_DATE
            ORDER BY r.id DESC
        ');

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

        // Передаём всё в шаблон
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
            'flags' => $flags
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
        try {
            // Включаем логирование SQL-запросов
            \DB::enableQueryLog();

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
                    'INSERT INTO request_comments (request_id, comment_id, created_at) VALUES (?, ?, ?)',
                    [$requestId, $commentId, $createdAt]
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
            $requestByDate = DB::select("
                SELECT
                    r.*,
                    c.fio AS client_fio,
                    c.phone AS client_phone,
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
                    (SELECT COUNT(*) FROM request_comments rc WHERE rc.request_id = r.id) as comments_count
                FROM requests r
                LEFT JOIN clients c ON r.client_id = c.id
                LEFT JOIN request_statuses rs ON r.status_id = rs.id
                LEFT JOIN brigades b ON r.brigade_id = b.id
                LEFT JOIN employees e ON b.leader_id = e.id
                LEFT JOIN employees op ON r.operator_id = op.id
                LEFT JOIN request_addresses ra ON r.id = ra.request_id
                LEFT JOIN addresses addr ON ra.address_id = addr.id
                WHERE DATE(r.request_date) = ?
                ORDER BY r.id DESC
            ", [$requestDate]);

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

            if (!empty($brigadeIds)) {
                // Получаем всех членов бригад для загруженных заявок
                $members = DB::select('
                    SELECT
                        bm.brigade_id,
                        e.fio as member_name,
                        e.phone as member_phone,
                        e.position_id
                    FROM brigade_members bm
                    JOIN employees e ON bm.employee_id = e.id
                    WHERE bm.brigade_id IN (' . implode(',', $brigadeIds) . ')
                ');

                // Группируем членов по ID бригады
                foreach ($members as $member) {
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

            // Добавляем членов бригады и комментарии к каждой заявке
            $result = array_map(function ($request) use ($brigadeMembers, $commentsByRequest) {
                $brigadeId = $request->brigade_id;
                $request->brigade_members = $brigadeMembers[$brigadeId] ?? [];
                $request->comments = $commentsByRequest[$request->id] ?? [];
                $request->comments_count = count($request->comments);
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
                'Система' as author_name,
                c.created_at as formatted_date
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
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
    public function closeRequest($id)
    {
        try {
            // Update the request status to 'выполнена' (ID 4)
            $updated = DB::table('requests')
                ->where('id', $id)
                ->update(['status_id' => 4]);  // 4 is the ID for 'выполнена'

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Заявка успешно закрыта'
                ]);
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
            $operators = DB::select('SELECT id, fio FROM employees WHERE position_id = 1 ORDER BY fio');
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
     * Store a new request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeRequest(Request $request)
    {
        DB::beginTransaction();
        $existingClient = false; // Инициализируем переменную
        try {
            // Подробное логирование входящих данных
            \Log::info('=== НАЧАЛО ОБРАБОТКИ ЗАПРОСА ===');
            \Log::info('Полные входные данные:', $request->all());
            \Log::info('Данные клиента:', $request->input('client', []));
            \Log::info('Данные заявки:', $request->input('request', []));
            \Log::info('Список адресов:', $request->input('addresses', []));

            // Проверяем наличие обязательных полей запроса
            if (!$request->has('request') || !$request->has('addresses')) {
                throw new \Exception('Отсутствуют обязательные поля в запросе');
            }

            // Инициализируем пустой массив клиента, если его нет
            if (!$request->has('client')) {
                $request->merge(['client' => []]);
            }

            // Валидация входных данных
            $validated = $request->validate([
                'client.fio' => 'nullable|string|max:255',
                'client.phone' => 'nullable|string|max:20',
                'request.request_type_id' => 'required|exists:request_types,id',
                'request.status_id' => 'required|exists:request_statuses,id',
                'request.comment' => 'nullable|string',
                'request.execution_date' => 'required|date',
                'request.execution_time' => 'nullable|date_format:H:i',
                'request.brigade_id' => 'nullable|exists:brigades,id',
                'request.operator_id' => 'required|exists:employees,id',
                'addresses' => 'required|array|min:1',
                'addresses.*.city_id' => 'required|exists:cities,id',
                'addresses.*.street' => 'required|string|max:255',
                'addresses.*.house' => 'required|string|max:20'
            ]);
            
            // Логируем валидированные данные для отладки
            \Log::info('Валидированные данные:', $validated);

            // 1. Обработка данных клиента (если они есть)
            $clientId = null;
            
            // Проверяем, есть ли данные клиента в запросе
            if (isset($validated['client']) && is_array($validated['client'])) {
                $clientData = $validated['client'];
                $phone = !empty($clientData['phone']) ? preg_replace('/[^0-9]/', '', $clientData['phone']) : '';
                $fio = $clientData['fio'] ?? '';

                // Если указан телефон или ФИО, обрабатываем клиента
                if (!empty($phone) || !empty($fio)) {
                    // Ищем существующего клиента по номеру телефона, если номер указан
                    if (!empty($phone)) {
                        $existingClient = DB::selectOne(
                            'SELECT id FROM clients WHERE phone = ? OR phone LIKE ? LIMIT 1',
                            [$phone, '%' . $phone]
                        );

                        if ($existingClient) {
                            $clientId = $existingClient->id;
                            \Log::info('Найден существующий клиент с ID:', ['id' => $clientId]);
                            
                            // Обновляем ФИО клиента, если оно изменилось
                            if (!empty($fio)) {
                                DB::update(
                                    'UPDATE clients SET fio = ? WHERE id = ?',
                                    [$fio, $clientId]
                                );
                            }
                        }
                    }

                    // Если клиент не найден и есть данные для создания
                    if (!$clientId && (!empty($fio) || !empty($phone))) {
                        $clientSql = "INSERT INTO clients (fio, phone) VALUES ('"
                            . addslashes($fio) . "', '"
                            . addslashes($phone) . "') RETURNING id";

                        \Log::info('SQL для вставки клиента:', ['sql' => $clientSql]);
                        $clientId = DB::selectOne($clientSql)->id;
                        \Log::info('Создан новый клиент с ID:', ['id' => $clientId]);
                    }
                }
            }

            // 2. Создаем комментарий, только если он не пустой
            $commentText = trim($validated['request']['comment'] ?? $validated['request']['description'] ?? '');
            $newCommentId = null;

            // Логируем полученные данные для отладки
            \Log::info('Полученные данные запроса:', [
                'all_request_data' => $validated,
                'comment_text' => $commentText
            ]);

            if (!empty($commentText)) {
                \Log::info('Создание комментария:', ['comment_text' => $commentText]);

                try {
                    $commentSql = "INSERT INTO comments (comment) VALUES ('"
                        . addslashes($commentText)
                        . "') RETURNING id";

                    \Log::info('SQL для вставки комментария:', ['sql' => $commentSql]);

                    $commentResult = DB::selectOne($commentSql);
                    $newCommentId = $commentResult ? $commentResult->id : null;

                    if (!$newCommentId) {
                        throw new \Exception('Не удалось получить ID созданного комментария');
                    }

                    \Log::info('Успешно создан комментарий:', [
                        'id' => $newCommentId,
                        'comment' => $commentText
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Ошибка при создании комментария:', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            } else {
                \Log::info('Пропущено создание пустого комментария');
            }

            // 3. Создаем заявку
            $requestData = $validated['request'];
            $requestSql = "
            INSERT INTO requests (
                number,
                request_type_id,
                status_id,
                execution_date,
                execution_time,
                brigade_id,
                operator_id,
                request_date,
                client_id
            ) VALUES (
                'REQ-' || to_char(CURRENT_DATE, 'DDMMYY') || '-' ||
                LPAD((COALESCE((SELECT MAX(SUBSTRING(number, 11, 4)::int)
                               FROM requests
                               WHERE SUBSTRING(number, 5, 6) = to_char(CURRENT_DATE, 'DDMMYY')), 0) + 1)::text,
                     4, '0'),
                :request_type_id,
                :status_id,
                :execution_date,
                :execution_time,
                :brigade_id,
                :operator_id,
                CURRENT_DATE,
                :client_id
            ) RETURNING id";

            $requestParams = [
                'request_type_id' => $requestData['request_type_id'],
                'status_id' => $requestData['status_id'],
                'execution_date' => $requestData['execution_date'],
                'execution_time' => $requestData['execution_time'],
                'brigade_id' => $requestData['brigade_id'],
                'operator_id' => $requestData['operator_id'],
                'client_id' => $clientId
            ];

            \Log::info('SQL для вставки заявки:', ['sql' => $requestSql, 'params' => $requestParams]);
            $requestId = DB::selectOne($requestSql, $requestParams)->id;
            \Log::info('Создана заявка с ID:', ['id' => $requestId]);

            // 4. Создаем связь между заявкой и комментарием
            \Log::info('Попытка создать связь заявки с комментарием:', [
                'request_id' => $requestId,
                'comment_id' => $newCommentId,
                'comment_text' => $commentText
            ]);

            if ($newCommentId) {
                try {
                    $requestCommentSql = '
                    INSERT INTO request_comments (
                        request_id,
                        comment_id
                    ) VALUES (
                        :request_id,
                        :comment_id
                    ) RETURNING *';

                    $requestCommentParams = [
                        'request_id' => $requestId,
                        'comment_id' => $newCommentId
                    ];

                    $result = DB::selectOne($requestCommentSql, $requestCommentParams);
                    \Log::info('Успешно создана связь заявки с комментарием:', [
                        'request_id' => $requestId,
                        'comment_id' => $newCommentId,
                        'result' => $result
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Ошибка при создании связи заявки с комментарием:', [
                        'request_id' => $requestId,
                        'comment_id' => $newCommentId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;  // Пробрасываем исключение дальше, чтобы откатить транзакцию
                }
            } else {
                \Log::warning('Не удалось создать связь: отсутствует comment_id');
            }

            // 5. Создаем адрес и связываем с заявкой
            $addressesData = [];
            foreach ($validated['addresses'] as $address) {
                // Вставляем адрес
                $addressSql = '
                INSERT INTO addresses (
                    city_id,
                    street,
                    district,
                    houses,
                    comments
                ) VALUES (
                    :city_id,
                    :street,
                    :district,
                    :house,
                    :comment
                ) RETURNING id';

                $addressParams = [
                    'city_id' => $address['city_id'],
                    'street' => $address['street'],
                    'district' => 'Не указан',  // Устанавливаем значение по умолчанию для обязательного поля
                    'house' => $address['house'],
                    'comment' => $address['comment'] ?? ''
                ];

                \Log::info('SQL для вставки адреса:', ['sql' => $addressSql, 'params' => $addressParams]);
                $addressId = DB::selectOne($addressSql, $addressParams)->id;
                \Log::info('Создан адрес с ID:', ['id' => $addressId]);

                // Связываем адрес с заявкой
                $requestAddressSql = '
                INSERT INTO request_addresses (
                    request_id,
                    address_id
                ) VALUES (
                    :request_id,
                    :address_id
                )';

                $requestAddressParams = [
                    'request_id' => $requestId,
                    'address_id' => $addressId
                ];

                $requestAddressResult = DB::selectOne($requestAddressSql, $requestAddressParams);
                \Log::info('Создана связь заявки с адресом:', [
                    'request_id' => $requestId,
                    'address_id' => $addressId
                ]);

                // Сохраняем данные адреса для ответа
                $addressesData[] = [
                    'id' => $addressId,
                    'city_id' => $address['city_id'],
                    'street' => $address['street'],
                    'house' => $address['house'],
                    'comment' => $address['comment'] ?? ''
                ];

                // Сохраняем информацию о связи для ответа
                $response['request_addresses'][] = [
                    'request_id' => $requestId,
                    'address_id' => $addressId
                ];
            }

            // Получаем номер созданной заявки для отображения
            $requestNumber = DB::selectOne('SELECT number FROM requests WHERE id = ?', [$requestId])->number;

            // Формируем информативный ответ
            $response = [
                'success' => true,
                'message' => isset($clientId) 
                    ? (isset($existingClient) && $existingClient ? 'Использован существующий клиент' : 'Создан новый клиент')
                    : 'Заявка создана без привязки к клиенту',
                'client' => isset($clientId) ? [
                    'id' => $clientId,
                    'fio' => $clientData['fio'] ?? null,
                    'phone' => $clientData['phone'] ?? null,
                    'is_new' => !(isset($existingClient) && $existingClient)
                ] : null,
                'request' => [
                    'id' => $requestId,
                    'number' => $requestNumber,
                    'type_id' => $requestData['request_type_id'],
                    'status_id' => $requestData['status_id'],
                    'comment_id' => $newCommentId,
                ],
                'addresses' => $addressesData,
                'next_steps' => [
                    '1. Связать комментарий с заявкой'
                ],
                'request_addresses' => []
            ];

            if ($existingClient) {
                $response['message'] = 'Использован существующий клиент (ID: ' . $clientId . ')';
            } else {
                $response['message'] = 'Успешно создан новый клиент (ID: ' . $clientId . ')';
            }

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
            \Log::error('Error creating request: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заявки',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
