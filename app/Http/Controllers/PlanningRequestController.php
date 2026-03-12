<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PlanningRequestController extends Controller
{
    public function uploadRequestsExcel(Request $request)
    {
        try {
            $validated = $request->validate([
                'requests_file' => 'required|file|mimes:xlsx,xls|max:10240',
            ]);

            $file = $request->file('requests_file');
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            $data = array_filter($data, function ($row) {
                return ! empty(array_filter($row, fn ($value) => $value !== null && $value !== ''));
            });

            if (empty($data)) {
                return response()->json(['success' => false, 'message' => 'Файл не содержит данных'], 400);
            }

            $headers = array_shift($data);

            $normalizedHeaders = array_map(function ($h) {
                return trim(mb_strtolower($h));
            }, $headers);

            $expectedHeaders = [
                'гбоу',
                'адрес организации',
                'контакт',
                'комментарии к монтажу',
            ];

            if (count(array_intersect($expectedHeaders, $normalizedHeaders)) !== count($expectedHeaders)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверные заголовки в файле. Обязательные колонки: '.implode(', ', $expectedHeaders),
                    'headers_found' => $headers,
                ], 400);
            }

            DB::beginTransaction();
            $createdRequests = [];

            $headerMap = array_flip($normalizedHeaders);

            foreach ($data as $rowIndex => $row) {
                $rowData = [];
                foreach ($headerMap as $normalizedHeader => $index) {
                    $rowData[$normalizedHeader] = $row[$index] ?? null;
                }

                // 1. Parse Address
                $addressString = $rowData['адрес организации'] ?? '';
                $addressParts = explode(',', $addressString, 2);
                $cityString = trim($addressParts[0] ?? '');
                $streetString = trim($addressParts[1] ?? '');
                $cityName = str_replace('город ', '', $cityString);

                // 2. Parse Contact
                $contactString = $rowData['контакт'] ?? '';
                $phone = '';
                $fio = $contactString;
                if (preg_match('/((?:\+7|8)[\s-]?\(?\d{3}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2})/', $contactString, $matches)) {
                    $phone = $matches[0];
                    $fio = trim(str_replace($phone, '', $fio));
                }

                // 3. Get Organization
                $organization = $rowData['гбоу'] ?? '';

                // 4. Get Comment
                $comment = $rowData['комментарии к монтажу'] ?? '';

                $parsedRowData = [
                    'city_name' => $cityName,
                    'street' => $streetString,
                    'fio' => $fio,
                    'phone' => $phone,
                    'organization' => $organization,
                    'comment' => $comment,
                ];

                $addressId = $this->findOrCreateAddress($parsedRowData);
                $clientId = $this->findOrCreateClient($parsedRowData);

                $requestData = [
                    'client_id' => $clientId,
                    'address_id' => $addressId,
                    'comment' => $parsedRowData['comment'],
                ];

                $newRequest = $this->createPlanningRequest($requestData);
                $createdRequests[] = $newRequest;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Заявки успешно загружены: '.count($createdRequests).' шт.',
                'data' => $createdRequests,
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Ошибка при загрузке заявок из Excel: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обработке файла: '.$e->getMessage(),
            ], 500);
        }
    }

    private function findOrCreateAddress($rowData)
    {
        $cityName = trim($rowData['city_name'] ?? '');
        $street = trim($rowData['street'] ?? '');

        if (empty($cityName) || empty($street)) {
            throw new \Exception('Недостаточно данных для адреса в одной из строк. Обязательные поля: город, улица.');
        }

        $city = DB::table('cities')->where('name', 'ilike', $cityName)->first();
        if (! $city) {
            throw new \Exception("Город '{$cityName}' не найден в базе данных.");
        }
        $cityId = $city->id;

        $address = DB::table('addresses')
            ->where('city_id', $cityId)
            ->where('street', $street)
            ->first();

        if ($address) {
            return $address->id;
        }

        return DB::table('addresses')->insertGetId([
            'city_id' => $cityId,
            'street' => $street,
            'district' => '', // Not provided in the new spec
            'houses' => '',   // Not provided in the new spec
        ]);
    }

    private function findOrCreateClient($rowData)
    {
        $fio = trim($rowData['fio'] ?? '');
        $phone = trim($rowData['phone'] ?? '');
        $organization = trim($rowData['organization'] ?? '');

        // Для каждой строки в Excel-файле создается новый клиент.
        // Это гарантирует, что каждая заявка получает уникальный client_id.
        return DB::table('clients')->insertGetId([
            'fio' => $fio,
            'phone' => $phone,
            'organization' => $organization,
            'email' => '',
        ]);
    }

    private function createPlanningRequest($data)
    {
        $userId = auth()->id();
        $employee = DB::table('employees')->where('user_id', $userId)->first();
        $employeeId = $employee ? $employee->id : null;

        $count = DB::table('requests')->count() + 1;
        $requestNumber = 'REQ-'.date('Ymd').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);

        $requestId = DB::table('requests')->insertGetId([
            'client_id' => $data['client_id'],
            'request_type_id' => 1, // default
            'status_id' => 6, // 'планирование'
            'operator_id' => $employeeId,
            'number' => $requestNumber,
            'request_date' => now()->toDateString(),
            'execution_date' => null, // Not provided in the new spec
        ]);

        if (! empty($data['comment'])) {
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $data['comment'],
                'created_at' => now(),
            ]);

            DB::table('request_comments')->insert([
                'request_id' => $requestId,
                'comment_id' => $commentId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        }

        DB::table('request_addresses')->insert([
            'request_id' => $requestId,
            'address_id' => $data['address_id'],
        ]);

        return DB::table('requests')->where('id', $requestId)->first();
    }

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
            'planning_execution_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $requestId = $request->input('planning_request_id');
        $planningExecutionDate = $request->input('planning_execution_date');

        $sql_update = 'UPDATE requests SET status_id = 6, execution_date = ? WHERE id = ?';

        // Начинаем транзакцию
        DB::beginTransaction();

        try {
            // Получаем текущие данные заявки
            $currentRequest = DB::table('requests')->find($requestId);
            \Log::info('=== START planninginWorkRequest ===', [
                'request' => $currentRequest,
                'request_id' => $requestId,
                'execution_date' => $planningExecutionDate,
            ]);

            // Выполняем обновление с помощью прямого SQL
            $sql = 'UPDATE requests SET status_id = 1, execution_date = ? WHERE id = ?';
            $bindings = [$planningExecutionDate, $requestId];

            // Логируем SQL-запрос для отладки
            $fullSql = \Illuminate\Support\Str::replaceArray('?', array_map(function ($param) {
                return is_string($param) ? "'$param'" : $param;
            }, $bindings), $sql);

            // \Log::info('Executing SQL:', ['sql' => $fullSql]);

            $result = DB::update($sql, $bindings);

            // Принудительно получаем обновленные данные
            $updatedRequest = DB::selectOne('SELECT * FROM requests WHERE id = ?', [$requestId]);

            // Проверяем, изменился ли статус
            $statusChanged = $updatedRequest && $currentRequest &&
                           $updatedRequest->status_id == 1;

            if ($statusChanged) {
                // Фиксируем изменения, если статус изменился
                DB::commit();

                \Log::info('=== END planninginWorkRequest ===', []);

                return response()->json([
                    'success' => true,
                    'message' => 'Статус заявки успешно изменен',
                    'status_changed' => true,
                    'new_status_id' => $updatedRequest->status_id,
                    'fullSql' => $fullSql,
                ]);
            } else {
                // Откатываем, если статус не изменился
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось изменить статус заявки. Возможно, неверный ID заявки или проблема с правами доступа.',
                    'status_changed' => false,
                ], 400);
            }

        } catch (\Exception $e) {
            // Откатываем изменения в случае ошибки
            DB::rollBack();
            \Log::error('Update error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении заявки: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getPlanningRequests()
    {
        try {
            $sql = "
            SELECT
                r.id,
                r.request_type_id,
                r.brigade_id,
                TO_CHAR(r.request_date, 'DD.MM.YYYY') AS request_date,
                r.number,
                '#' || r.id || ', ' || r.number || ', создана ' || TO_CHAR(r.request_date, 'DD.MM.YYYY') AS request,
                c.fio || ', ' || c.phone || ', ' || c.organization AS client,
                ct.name || '. ' || addr.district || '. ' || addr.street || '. ' || addr.houses AS address,
                addr.latitude,
                addr.longitude,
                ct.name city,
                addr.district district,
                addr.street street,
                addr.houses houses,
                c.fio,
                c.phone,
                c.organization,
                 rs.name AS status_name,
                                 rs.color,
                                 rt.name AS request_type_name,
                                 rt.color AS request_type_color,
                                 b.name AS brigade_name,
                                 bl.fio AS brigade_lead,
                                  jsonb_agg(                    jsonb_build_object(
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
            LEFT JOIN request_types rt ON r.request_type_id = rt.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees bl ON b.leader_id = bl.id
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
                r.id, r.request_type_id, r.brigade_id, r.number, r.request_date,
                c.fio, c.phone, c.organization,
                op.fio,
                ct.name, addr.district, addr.street, addr.houses, addr.latitude, addr.longitude,
                rs.name,
                rs.color,
                rt.name,
                rt.color,
                b.name,
                bl.fio
            ORDER BY r.id DESC";

            $result = DB::select($sql);
            $brigadeMembersWithDetails = $this->getBrigadeMembersWithDetails();

            return response()->json([
                'success' => true,
                'data' => [
                    'planningRequests' => $result,
                    'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PlanningRequestController@getPlanningRequests: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении запланированных заявок',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение членов бригады с деталями
     */
    private function getBrigadeMembersWithDetails()
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
            Log::error('Error in PlanningRequestController@getBrigadeMembersWithDetails: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Store a newly created planning request in storage.
     *
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
            'operator_id' => 'nullable|exists:employees,id',
        ];

        // Логируем все входящие данные для отладки
        // \Log::info('Incoming request data:', $request->all());

        // Нормализуем входные данные
        $input = $request->all();

        // Приводим к единому формату поле адреса
        if (isset($input['address_id']) && ! isset($input['addresses_planning_request_id'])) {
            $input['addresses_planning_request_id'] = $input['address_id'];
        }

        // Валидируем входные данные
        $validator = Validator::make($input, [
            'client_name_planning_request' => 'nullable|string|max:255',
            'client_phone_planning_request' => 'nullable|string|max:20',
            'client_organization_planning_request' => 'nullable|string|max:255',
            'planning_request_comment' => 'required|string',
            'addresses_planning_request_id' => 'required|exists:addresses,id',
            'address_id' => 'sometimes|exists:addresses,id',
        ]);

        if ($validator->fails()) {
            $errorDetails = [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
                'request_headers' => $request->headers->all(),
            ];

            // \Log::error('Validation failed', $errorDetails);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
                'debug' => [
                    'received_address_id' => $request->get('address_id'),
                    'received_addresses_planning_request_id' => $request->get('addresses_planning_request_id'),
                ],
            ], 422);
        }

        // Уже получили $input из предыдущего шага

        // Получаем ID адреса из нормализованных данных
        $addressId = $input['addresses_planning_request_id'];

        // Логируем данные для отладки
        // \Log::info('Processing planning request with data:', [
        //     'address_id' => $addressId,
        //     'client_name' => $input['client_name_planning_request'] ?? null,
        //     'client_phone' => $input['client_phone_planning_request'] ?? null,
        //     'client_organization' => $input['client_organization_planning_request'] ?? null,
        //     'comment' => $input['planning_request_comment'] ?? null
        // ]);

        if (! $addressId) {
            $errorMessage = 'Не удалось определить ID адреса. Полученные данные: '.json_encode($input);
            // \Log::error($errorMessage);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось определить ID адреса',
                'debug' => [
                    'received_data' => $input,
                    'available_keys' => array_keys($input),
                ],
            ], 422);
        }

        // Проверяем существование адреса
        $address = DB::table('addresses')
            ->where('id', $addressId)
            ->first();

        if (! $address) {
            return response()->json([
                'success' => false,
                'message' => 'Указанный адрес не найден',
                'address_id' => $addressId,
            ], 404);
        }

        // Подготавливаем данные для сохранения
        $validationData = [
            'client_name' => $input['client_name_planning_request'] ?? null,
            'client_phone' => $input['client_phone_planning_request'] ?? null,
            'client_organization' => $input['client_organization_planning_request'] ?? null,
            'comment' => $input['planning_request_comment'] ?? null,
            'address_id' => $addressId,
            'request_type_id' => $input['request_type_id'] ?? 1, // Используем переданный тип или default
            'status_id' => 6, // Значение по умолчанию
            'user_id' => auth()->id(),
            'work_parameters' => $input['work_parameters'] ?? null,
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
            'user_id' => 'required|exists:users,id',
            'work_parameters' => 'nullable|array',
            'work_parameters.*.parameter_type_id' => 'required|exists:work_parameter_types,id',
            'work_parameters.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Проверяем авторизацию пользователя
        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Необходима авторизация',
                'redirect' => '/login',
            ], 401);
        }

        // Проверяем наличие необходимых ролей
        $user = auth()->user();

        // Проверяем, загружены ли роли пользователя
        if (! isset($user->roles) || ! is_array($user->roles)) {
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

        if (! $hasAllowedRole) {
            return response()->json([
                'success' => false,
                'message' => 'У вас недостаточно прав для создания заявки. Необходима одна из ролей: '.implode(', ', $allowedRoles),
                'user_roles' => $user->roles ?? [],
            ], 403);
        }

        // Включаем логирование SQL-запросов
        \DB::enableQueryLog();

        DB::beginTransaction();

        $isExistingClient = false;

        try {
            // Логируем все входные данные для отладки
            // \Log::info('=== НАЧАЛО ОБРАБОТКИ ЗАПРОСА ===');
            // \Log::info('Все входные данные:', $request->all());

            // Получаем данные из запроса
            $input = $request->all();

            // Если operator_id не указан, используем ID текущего пользователя или значение по умолчанию
            $userId = auth()->id(); // ID пользователя из авторизации
            $input['user_id'] = $userId; // Сохраняем ID пользователя для логирования
            // \Log::info('ID авторизованного пользователя: ' . $userId);

            // Проверяем наличие сотрудника только если указан user_id
            $employeeId = null;
            if ($userId) {
                $employee = DB::table('employees')
                    ->where('user_id', $userId)
                    ->first();

                if ($employee) {
                    $employeeId = $employee->id;
                    $input['operator_id'] = $employeeId; // Устанавливаем operator_id как ID сотрудника, а не пользователя
                    // \Log::info('Найден сотрудник с ID: ' . $employeeId . ' для пользователя: ' . $userId);
                } else {
                    // \Log::info('Сотрудник не найден для пользователя с ID: ' . $userId . ', но продолжаем создание заявки');
                }
            } else {
                // \Log::info('Оператор не указан, создаем заявку без привязки к сотруднику');
            }

            $validationData['brigade_id'] = $input['brigade_id'] ?? null;
            $validationData['address_id'] = $input['address_id'] ?? null;
            $validationData['request_type_id'] = $input['request_type_id'] ?? 1;
            $validationData['status_id'] = 6;
            $validationData['comment'] = $input['planning_request_comment'] ?? null; // Исправлено на правильное имя поля
            $validationData['execution_date'] = $input['execution_date'] ?? null;
            $validationData['execution_time'] = $input['execution_time'] ?? null;
            $validationData['user_id'] = $userId;
            $validationData['operator_id'] = $employeeId;
            $validationData['client_name'] = $input['client_name_planning_request'] ?? null;
            $validationData['client_phone'] = $input['client_phone_planning_request'] ?? null;
            $validationData['client_organization'] = $input['client_organization_planning_request'] ?? null;
            $validationData['work_parameters'] = $input['work_parameters'] ?? null;

            \Log::info('Используем для заявки operator_id:', [
                'user_id' => $userId,
                'employee_id' => $employeeId,
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
                'address_id' => 'required|exists:addresses,id',
                'work_parameters' => 'nullable|array',
                'work_parameters.*.parameter_type_id' => 'required|exists:work_parameter_types,id',
                'work_parameters.*.quantity' => 'required|integer|min:1',
            ];

            // Логируем входные данные для отладки
            // \Log::info('Входные данные для валидации:', [
            //     'validationData' => $validationData,
            //     'rules' => $rules
            // ]);

            // Валидация входных данных
            $validator = \Validator::make($validationData, $rules);

            if ($validator->fails()) {
                \Log::error('Ошибка валидации:', $validator->errors()->toArray());

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            // \Log::info('Валидированные данные:', $validated);

            // 1. Подготовка данных клиента
            $fio = trim($validated['client_name'] ?? '');
            $phone = trim($validated['client_phone'] ?? '');
            $organization = trim($validated['client_organization'] ?? '');

            // 2. Валидация данных клиента
            $clientData = [
                'fio' => $fio,
                'phone' => $phone,
                'email' => '', // Пустая строка, так как поле не может быть NULL
                'organization' => $organization,
            ];

            $clientRules = [
                'fio' => 'string|max:255',
                'phone' => 'string|max:50',
                'email' => 'string|max:255',
                'organization' => 'string|max:255',
            ];

            $clientValidator = Validator::make($clientData, $clientRules);
            if ($clientValidator->fails()) {
                \Log::error('Ошибка валидации данных клиента:', $clientValidator->errors()->toArray());

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации данных клиента',
                    'errors' => $clientValidator->errors(),
                ], 422);
            }

            // 3. Поиск существующего клиента по телефону (если телефон указан)
            $client = null;
            $clientId = null;

            // Поиск клиента по телефону, ФИО или организации
            $query = DB::table('clients');
            $foundClient = false;

            if (! empty($clientData['fio'])) {
                if ($foundClient) {
                    $query->orWhere('fio', $clientData['fio']);
                } else {
                    $query->where('fio', $clientData['fio']);
                    $foundClient = true;
                }
            } elseif (! empty($clientData['phone'])) {
                $query->where('phone', $clientData['phone']);
                $foundClient = true;
            } elseif (! empty($clientData['organization'])) {
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
                            'organization' => $clientData['organization'],
                        ]);
                    $clientId = $client->id;
                    $clientState = 'updated';
                    // \Log::info('Обновлен существующий клиент:', ['id' => $clientId]);
                } else {
                    // Создаем нового клиента (даже если все поля пустые)
                    $clientId = DB::table('clients')->insertGetId([
                        'fio' => $clientData['fio'],
                        'phone' => $clientData['phone'],
                        'email' => $clientData['email'],
                        'organization' => $clientData['organization'],
                    ]);
                    $clientState = 'created';
                    // \Log::info('Создан новый клиент:', ['id' => $clientId]);
                }
            } catch (\Exception $e) {
                \Log::error('Ошибка при сохранении клиента: '.$e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при сохранении данных клиента',
                    'error' => $e->getMessage(),
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
                'operator_id' => $validated['operator_id'],
            ];

            // Генерируем номер заявки
            $countQuery = DB::table('requests');
            $count = $countQuery->count() + 1;
            $requestNumber = 'REQ-'.date('Ymd').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);
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
                    $currentDate,
                ]
            );

            $requestId = $result[0]->id;

            // \Log::info('Результат вставки заявки:', ['result' => $result, 'type' => gettype($result)]);

            if (empty($result)) {
                throw new \Exception('Не удалось создать заявку');
            }

            $requestId = $result[0]->id;
            // \Log::info('Создана заявка с ID:', ['id' => $requestId]);

            // 4. Создаем комментарий, только если он не пустой
            $commentText = trim($validated['comment'] ?? '');
            $newCommentId = null;

            // Логируем данные комментария для отладки
            // \Log::info('Данные комментария перед созданием:', [
            //     'comment_text' => $commentText,
            //     'is_empty' => empty($commentText),
            //     'validated_data' => $validated
            // ]);

            if (! empty($commentText)) {
                try {
                    // Вставляем комментарий без поля updated_at
                    $commentSql = 'INSERT INTO comments (comment, created_at) VALUES (?, ?) RETURNING id';
                    $bindings = [
                        $commentText,
                        now()->toDateTimeString(),
                    ];

                    // \Log::info('SQL для вставки комментария:', ['sql' => $commentSql, 'bindings' => $bindings]);

                    $commentResult = DB::selectOne($commentSql, $bindings);
                    $newCommentId = $commentResult ? $commentResult->id : null;

                    if (! $newCommentId) {
                        throw new \Exception('Не удалось получить ID созданного комментария');
                    }

                    // \Log::info('Создан комментарий с ID:', ['id' => $newCommentId]);

                    // Создаем связь между заявкой и комментарием
                    DB::table('request_comments')->insert([
                        'request_id' => $requestId,
                        'comment_id' => $newCommentId,
                        'user_id' => $request->user()->id,
                        'created_at' => now()->toDateTimeString(),
                    ]);

                    // \Log::info('Связь между заявкой и комментарием создана', [
                    //     'request_id' => $requestId,
                    //     'comment_id' => $newCommentId
                    // ]);
                } catch (\Exception $e) {
                    \Log::error('Ошибка при создании комментария: '.$e->getMessage());
                    // Продолжаем выполнение, так как комментарий не является обязательным
                }
            }

            // 5. Связываем существующий адрес с заявкой
            $addressId = $validated['address_id'];

            // Получаем информацию об адресе
            $address = DB::table('addresses')->find($addressId);

            if (! $address) {
                throw new \Exception('Указанный адрес не найден');
            }

            // Связываем адрес с заявкой без использования временных меток
            DB::table('request_addresses')->insert([
                'request_id' => $requestId,
                'address_id' => $addressId,
                // Убраны created_at и updated_at, так как их нет в таблице
            ]);

            // \Log::info('Создана связь заявки с адресом:', [
            //     'request_id' => $requestId,
            //     'address_id' => $addressId
            // ]);

            // 6. Сохраняем параметры работ
            if (! empty($validated['work_parameters'])) {
                foreach ($validated['work_parameters'] as $param) {
                    DB::table('work_parameters')->insert([
                        'request_id' => $requestId,
                        'parameter_type_id' => $param['parameter_type_id'],
                        'quantity' => $param['quantity'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

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
                WHERE r.id = '.$requestId.'
            ');

            // Преобразуем результат запроса в объект, если это массив
            if (is_array($requestById) && ! empty($requestById)) {
                $requestById = (object) $requestById[0];
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
                        'is_new' => ! $isExistingClient,
                        'state' => $clientState,
                    ] : null,
                    'address' => [
                        'id' => $address->id,
                        'city_id' => $address->city_id,
                        'city_name' => isset($requestById->city_name) ? $requestById->city_name : null,
                        'city_postal_code' => isset($requestById->city_postal_code) ? $requestById->city_postal_code : null,
                        'street' => $address->street,
                        'house' => $address->houses,
                        'district' => $address->district,
                        'comment' => $address->comments ?? '',
                    ],
                    'comment' => $newCommentId ? [
                        'id' => $newCommentId,
                        'text' => $commentText,
                    ] : null,
                ],
            ];

            // Фиксируем изменения, если все успешно
            DB::commit();

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при создании заявки:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании заявки: '.$e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }
}
