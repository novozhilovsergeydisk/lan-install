<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlanningRequestController extends Controller
{

    public function changePlanningRequestStatus(Request $request)
    {
        // response для тестирования 
        // $response = [
        //     'success' => true,
        //     'message' => 'Статус заявки успешно изменен  - режим тестирования',
        //     'data' => $request->all()
        // ];

        // return response()->json($response);

        // Validate request data
        $validator = Validator::make($request->all(), [
            'planning_request_id' => 'required|exists:requests,id',
            'planning_execution_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        $requestId = $request->input('planning_request_id');
        $planningExecutionDate = $request->input('planning_execution_date');

        $sql_update = "UPDATE requests SET status_id = 6, execution_date = ? WHERE id = ?";

        // Начинаем транзакцию
        DB::beginTransaction();
        
        try {
            // Получаем текущие данные заявки
            $currentRequest = DB::table('requests')->find($requestId);
            \Log::info('Before update:', [
                'request' => $currentRequest,
                'request_id' => $requestId,
                'execution_date' => $planningExecutionDate
            ]);
            
            // Выполняем обновление с помощью прямого SQL
            $sql = "UPDATE requests SET status_id = 1, execution_date = ? WHERE id = ?";
            $bindings = [$planningExecutionDate, $requestId];
            
            // Логируем SQL-запрос для отладки
            $fullSql = \Illuminate\Support\Str::replaceArray('?', array_map(function($param) {
                return is_string($param) ? "'$param'" : $param;
            }, $bindings), $sql);
            
            \Log::info('Executing SQL:', ['sql' => $fullSql]);
            
            $result = DB::update($sql, $bindings);
            
            // Принудительно получаем обновленные данные
            $updatedRequest = DB::selectOne("SELECT * FROM requests WHERE id = ?", [$requestId]);
            
            // Проверяем, изменился ли статус
            $statusChanged = $updatedRequest && $currentRequest && 
                           $updatedRequest->status_id == 1;
            
            if ($statusChanged) {
                // Фиксируем изменения, если статус изменился
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Статус заявки успешно изменен',
                    'status_changed' => true,
                    'new_status_id' => $updatedRequest->status_id,
                    'fullSql' => $fullSql
                ]);
            } else {
                // Откатываем, если статус не изменился
                DB::rollBack();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось изменить статус заявки. Возможно, неверный ID заявки или проблема с правами доступа.',
                    'status_changed' => false
                ], 400);
            }
            
        } catch (\Exception $e) {
            // Откатываем изменения в случае ошибки
            DB::rollBack();
            \Log::error('Update error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении заявки: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPlanningRequests()
    {
        $sql = "
            SELECT
                r.id,
                TO_CHAR(r.request_date, 'DD.MM.YYYY') AS request_date,
                r.number,
                '#' || r.id || ', ' || r.number || ', создана ' || TO_CHAR(r.request_date, 'DD.MM.YYYY') AS request,
                c.fio || ', ' || c.phone || ', ' || c.organization AS client,
                ct.name || '. ' || addr.district || '. ' || addr.street || '. ' || addr.houses AS address,
                ct.name city,
                addr.district district,
                addr.street street,
                addr.houses houses,
                c.fio,
                c.phone,
                c.organization,
                rs.color,
                jsonb_agg(
                    jsonb_build_object(
                        'comment', co.comment,
                        'created_at', TO_CHAR(co.created_at, 'DD.MM.YYYY HH24:MI'),
                        'author_name', u.name,
                        'author_fio', emp.fio,
                        'author_user_id', rc.user_id
                    ) ORDER BY co.created_at DESC
                ) FILTER (WHERE co.id IS NOT NULL) AS comments
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN employees op ON r.operator_id = op.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            LEFT JOIN cities ct ON addr.city_id = ct.id
            LEFT JOIN request_comments rc ON r.id = rc.request_id
            LEFT JOIN comments co ON rc.comment_id = co.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees emp ON u.id = emp.user_id
            WHERE 1=1
                AND (rs.name = 'планирование')
            GROUP BY
                r.id, r.number, r.request_date,
                c.fio, c.phone, c.organization,
                op.fio,
                ct.name, addr.district, addr.street, addr.houses,
                rs.name,
                rs.color    
            ORDER BY r.id DESC";

        $result = DB::select($sql);

        return response()->json([
            'success' => true,
            'data' => [
                'planningRequests' => $result
            ]
        ]);
    }

    /**
     * Store a newly created planning request in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Валидация входящих данных
        $validationRules = [
            'address_id' => 'required|exists:addresses,id',
            'client_name_planning_request' => 'nullable|string|max:255',
            'client_phone_planning_request' => 'nullable|string|max:20',
            'client_organization_planning_request' => 'nullable|string|max:255',
            'planning_request_comment' => 'required|string',
            'request_type_id' => 'required|exists:request_types,id',
            'status_id' => 'required|exists:request_statuses,id',
            'execution_time' => 'nullable|date_format:H:i',
            'brigade_id' => 'nullable|exists:brigades,id',
            'operator_id' => 'nullable|exists:employees,id'
        ];

        // Логируем все входящие данные для отладки
        \Log::info('Incoming request data:', $request->all());
        
        // Нормализуем входные данные
        $input = $request->all();
        
        // Приводим к единому формату поле адреса
        if (isset($input['address_id']) && !isset($input['addresses_planning_request_id'])) {
            $input['addresses_planning_request_id'] = $input['address_id'];
        }
        
        // Валидируем входные данные
        $validator = Validator::make($input, [
            'client_name_planning_request' => 'nullable|string|max:255',
            'client_phone_planning_request' => 'nullable|string|max:20',
            'client_organization_planning_request' => 'nullable|string|max:255',
            'planning_request_comment' => 'required|string',
            'addresses_planning_request_id' => 'required|exists:addresses,id',
            'address_id' => 'sometimes|exists:addresses,id'
        ]);

        if ($validator->fails()) {
            $errorDetails = [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
                'request_headers' => $request->headers->all()
            ];
            
            \Log::error('Validation failed', $errorDetails);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
                'debug' => [
                    'received_address_id' => $request->get('address_id'),
                    'received_addresses_planning_request_id' => $request->get('addresses_planning_request_id')
                ]
            ], 422);
        }

        // Уже получили $input из предыдущего шага
        
        // Получаем ID адреса из нормализованных данных
        $addressId = $input['addresses_planning_request_id'];
        
        // Логируем данные для отладки
        \Log::info('Processing planning request with data:', [
            'address_id' => $addressId,
            'client_name' => $input['client_name_planning_request'] ?? null,
            'client_phone' => $input['client_phone_planning_request'] ?? null,
            'client_organization' => $input['client_organization_planning_request'] ?? null,
            'comment' => $input['planning_request_comment'] ?? null
        ]);
        
        if (!$addressId) {
            $errorMessage = 'Не удалось определить ID адреса. Полученные данные: ' . json_encode($input);
            \Log::error($errorMessage);
            
            return response()->json([
                'success' => false,
                'message' => 'Не удалось определить ID адреса',
                'debug' => [
                    'received_data' => $input,
                    'available_keys' => array_keys($input)
                ]
            ], 422);
        }
        
        // Проверяем существование адреса
        $address = DB::table('addresses')
            ->where('id', $addressId)
            ->first();
            
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Указанный адрес не найден',
                'address_id' => $addressId
            ], 404);
        }
        
        // Подготавливаем данные для сохранения
        $validationData = [
            'client_name' => $input['client_name_planning_request'] ?? null,
            'client_phone' => $input['client_phone_planning_request'] ?? null,
            'client_organization' => $input['client_organization_planning_request'] ?? null,
            'comment' => $input['planning_request_comment'] ?? null,
            'address_id' => $addressId,
            'request_type_id' => 1, // Значение по умолчанию
            'status_id' => 6, // Значение по умолчанию
            'user_id' => auth()->id()
        ];
        
        // Валидируем подготовленные данные
        $validator = Validator::make($validationData, [
            'client_name' => 'nullable|string|max:255',
            'client_phone' => 'nullable|string|max:20',
            'client_organization' => 'nullable|string|max:255',
            'comment' => 'required|string',
            'address_id' => 'required|exists:addresses,id',
            'request_type_id' => 'required|exists:request_types,id',
            'status_id' => 'required|exists:request_statuses,id',
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

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

            $validationData['brigade_id'] = $input['brigade_id'] ?? null;
            $validationData['address_id'] = $input['address_id'] ?? null;
            $validationData['request_type_id'] = 1;
            $validationData['status_id'] = 6;
            $validationData['comment'] = $input['planning_request_comment'] ?? null; // Исправлено на правильное имя поля
            $validationData['execution_date'] = $input['execution_date'] ?? null;
            $validationData['execution_time'] = $input['execution_time'] ?? null;
            $validationData['user_id'] = $userId;
            $validationData['operator_id'] = $employeeId;
            $validationData['client_name'] = $input['client_name_planning_request'] ?? null;
            $validationData['client_phone'] = $input['client_phone_planning_request'] ?? null;
            $validationData['client_organization'] = $input['client_organization_planning_request'] ?? null;

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
                'execution_date' => 'nullable|date',
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
                    null,
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
            
            // Логируем данные комментария для отладки
            \Log::info('Данные комментария перед созданием:', [
                'comment_text' => $commentText,
                'is_empty' => empty($commentText),
                'validated_data' => $validated
            ]);

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
                        'user_id' => $request->user()->id,
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
}
