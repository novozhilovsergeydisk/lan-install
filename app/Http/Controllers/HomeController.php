<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Главный контроллер приложения lan-install.online
 *
 * Отвечает за управление заявками, комментариями, сотрудниками и основной логикой приложения.
 * Включает методы для работы с заявками, аутентификацией пользователей и отчетностью.
 */
class HomeController extends Controller
{
    public function getEditRequest($id)
    {
        try {
            // Check auth
            if (! auth()->check()) {
                return response()->json(['success' => false, 'message' => 'Необходима авторизация'], 401);
            }

            $user = auth()->user();

            // Загружаем роли пользователя
            $sql = 'SELECT roles.name FROM user_roles
                JOIN roles ON user_roles.role_id = roles.id
                WHERE user_roles.user_id = '.$user->id;

            $roles = DB::select($sql);

            // Извлекаем только имена ролей из результатов запроса
            $roleNames = array_map(function ($role) {
                return $role->name;
            }, $roles);

            // Устанавливаем роли и флаги
            $user->roles = $roleNames;
            $user->isAdmin = in_array('admin', $roleNames);
            $user->isUser = in_array('user', $roleNames);
            $user->isFitter = in_array('fitter', $roleNames);
            $user->user_id = $user->id;
            $user->sql = $sql;

            if (! $user->isAdmin) {
                return response()->json(['success' => false, 'message' => 'Недостаточно прав'], 403);
            }

            $request = DB::table('requests')
                ->leftJoin('clients', 'requests.client_id', '=', 'clients.id')
                ->leftJoin('request_addresses', 'requests.id', '=', 'request_addresses.request_id')
                ->leftJoin('addresses', 'request_addresses.address_id', '=', 'addresses.id')
                ->select(
                    'requests.*',
                    'clients.id as client_id',
                    'clients.fio as client_fio',
                    'clients.phone as client_phone',
                    'clients.organization as client_organization',
                    'addresses.id as address_id',
                    'addresses.street',
                    'addresses.houses as house'
                )
                ->where('requests.id', $id)
                ->first();

            if (! $request) {
                return response()->json(['success' => false, 'message' => 'Заявка не найдена'], 404);
            }

            // Fetch work parameters
            $workParameters = DB::table('work_parameters')
                ->where('request_id', $id)
                ->get();

            $request->work_parameters = $workParameters;

            return response()->json(['success' => true, 'data' => $request]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных заявки для редактирования',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateRequest(Request $request, $id)
    {
        // Check auth
        if (! auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Необходима авторизация'], 401);
        }

        // Validation
        try {
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'client_id' => 'nullable|integer|exists:clients,id',
                'client_name' => 'nullable|string|max:255',
                'client_phone' => 'nullable|string|max:50',
                'client_organization' => 'nullable|string|max:255',
                'request_type_id' => 'nullable|integer|exists:request_types,id',
                'status_id' => 'nullable|integer|exists:request_statuses,id',
                'execution_date' => 'required|date',
                'execution_time' => 'nullable|date_format:H:i',
                'addresses_id' => 'required|integer|exists:addresses,id',
                'work_parameters' => 'nullable|array',
                'work_parameters.*.parameter_type_id' => 'required|exists:work_parameter_types,id',
                'work_parameters.*.quantity' => 'required|integer|min:1',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации при обновлении заявки',
                'error' => $e->getMessage(),
            ], 500);
        }

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

        if (! $user->isAdmin) {
            return response()->json(['success' => false, 'message' => 'Недостаточно прав'], 403);
        }

        DB::beginTransaction();
        try {
            // 1. Find or create client by fio, phone, organization
            $client = DB::table('clients')
                ->where('fio', $validated['client_name'])
                ->where('phone', $validated['client_phone'])
                ->where('organization', $validated['client_organization'])
                ->first();

            if ($client) {
                // Use existing client
                $clientId = $client->id;
            } else {
                // Create new client
                $clientId = DB::table('clients')->insertGetId([
                    'fio' => $validated['client_name'],
                    'phone' => $validated['client_phone'],
                    'organization' => $validated['client_organization'],
                ]);
            }

            // 2. Update request_addresses table
            // Check if the address link already exists
            $existingAddressLink = DB::table('request_addresses')
                ->where('request_id', $id)
                ->where('address_id', $validated['addresses_id'])
                ->first();

            if (! $existingAddressLink) {
                // Remove any existing address links for this request
                DB::table('request_addresses')->where('request_id', $id)->delete();

                // Add new address link
                DB::table('request_addresses')->insert([
                    'request_id' => $id,
                    'address_id' => $validated['addresses_id'],
                ]);
            }

            // 3. Update requests table
            $updateData = [
                'client_id' => $clientId,
                'execution_date' => $validated['execution_date'],
            ];

            // Only update fields that were actually provided
            if (! empty($validated['request_type_id'])) {
                $updateData['request_type_id'] = $validated['request_type_id'];
            }
            if (! empty($validated['status_id'])) {
                $updateData['status_id'] = $validated['status_id'];
            }
            if (! empty($validated['execution_time'])) {
                $updateData['execution_time'] = $validated['execution_time'];
            }

            DB::table('requests')->where('id', $id)->update($updateData);

            // 4. Update work parameters
            if (isset($validated['work_parameters'])) {
                // Delete existing parameters for this request
                DB::table('work_parameters')->where('request_id', $id)->delete();

                // Insert new parameters
                if (! empty($validated['work_parameters'])) {
                    foreach ($validated['work_parameters'] as $param) {
                        DB::table('work_parameters')->insert([
                            'parameter_type_id' => $param['parameter_type_id'],
                            'quantity' => $param['quantity'],
                            'request_id' => $id,
                            'is_planning' => true,
                            'is_done' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Заявка обновлена']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновляет учетные данные пользователя (пароль)
     *
     * Метод позволяет администраторам обновлять пароли сотрудников.
     * Выполняет валидацию входных данных и обновляет пароль в базе данных.
     *
     * @param  int  $id  ID сотрудника
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCredentials(Request $request, $id)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'password' => 'required|string|min:8',
            ]);

            $sql = "select * from employees where id = $id";
            $result = DB::select($sql);
            $user_id = $result[0]->user_id;

            // Проверяем существование пользователя
            $user = DB::selectOne('SELECT id FROM users WHERE id = ?', [$user_id]);

            if (! $user) {
                throw new \Exception('Пользователь не найден');
            }

            // Обновляем email, name и password
            $result = DB::update(
                'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
                [
                    Hash::make($validated['password']),
                    $user_id,
                ]
            );

            if ($result === 0) {
                throw new \Exception('Пароль не был обновлен');
            }

            return response()->json([
                'success' => true,
                'message' => 'Пароль успешно обновлен',
                'data' => [
                    'updated' => true,
                    'user_id' => $user_id,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь не найден',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении пароля',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список всех ролей пользователей
     *
     * Возвращает список ролей из базы данных для использования в селектах форм.
     *
     * @return \Illuminate\Http\JsonResponse JSON с массивом ролей
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
                    'roles' => $roles,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка ролей',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Восстановить сотрудника (снять флаг is_deleted)
     */
    public function restoreEmployee(Request $request)
    {
        DB::beginTransaction();

        try {
            \Log::info('=== START restoreEmployee ===', ['request' => $request->all()]);

            $validated = $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
            ]);

            \Log::info('Validated employee_id:', $validated);

            $updated = DB::table('employees')
                ->where('id', $validated['employee_id'])
                ->update([
                    'is_deleted' => false,
                    'deleted_at' => null,
                ]);

            \Log::info('Update result:', ['updated' => $updated]);

            if ($updated === 0) {
                throw new \Exception('Сотрудник не найден или уже активен');
            }

            // Получить обновленные данные сотрудника
            $employee = DB::selectOne('
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
                WHERE e.id = ?
            ', [$validated['employee_id']]);

            DB::commit();

            \Log::info('=== END restoreEmployee ===');

            return response()->json([
                'success' => true,
                'message' => 'Сотрудник успешно восстановлен',
                'employee' => $employee,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('=== ERROR restoreEmployee ===', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при восстановлении сотрудника',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Полностью удалить сотрудника (установить is_blocked = true)
     */
    public function deleteEmployeePermanently(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
            ]);

            DB::table('employees')
                ->where('id', $validated['employee_id'])
                ->update(['is_blocked' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Сотрудник заблокирован и скрыт',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при блокировке сотрудника',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отменяет заявку с указанием причины
     *
     * Метод выполняет отмену заявки, создает комментарий с причиной отмены
     * и обновляет статус заявки. Использует транзакции для обеспечения целостности данных.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'reason' => 'required|string|max:1000',
            ]);

            // Начинаем транзакцию
            DB::beginTransaction();

            // Получаем заявку
            $requestData = DB::table('requests')
                ->where('id', $validated['request_id'])
                ->first();

            if (! $requestData) {
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

            if (! $canceledStatus) {
                throw new \Exception('Статус "отменена" не найден в системе');
            }

            $status_color = $canceledStatus->color;

            // Создаем комментарий об отмене
            $comment = 'Заявка отменена. Причина: '.$validated['reason'];

            // Добавляем комментарий
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $comment,
                'created_at' => now(),
            ]);

            // Привязываем комментарий к заявке
            DB::table('request_comments')->insert([
                'request_id' => $validated['request_id'],
                'comment_id' => $commentId,
                'user_id' => $request->user()->id,
                'created_at' => now(),
            ]);

            // Обновляем статус заявки
            DB::table('requests')
                ->where('id', $validated['request_id'])
                ->update([
                    'status_id' => $canceledStatus->id,
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
                'status_color' => $status_color,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transfer a request to a new date
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'new_date' => 'required|date|after_or_equal:today',
                'reason' => 'required|string|max:1000',
                'transfer_to_planning' => 'required|boolean',
            ]);

            // Begin transaction
            DB::beginTransaction();

            // Get the request
            $requestData = DB::table('requests')
                ->where('id', $validated['request_id'])
                ->first();

            if (! $requestData) {
                throw new \Exception('Заявка не найдена');
            }

            // Create a comment about the transfer
            $comment = 'Заявка перенесена с '.$requestData->execution_date.' на '.$validated['new_date'].'. Причина: '.$validated['reason'];

            // Add comment
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $comment,
                'created_at' => now(),
            ]);

            // Link comment to request
            DB::table('request_comments')->insert([
                'request_id' => $validated['request_id'],
                'comment_id' => $commentId,
                'user_id' => $request->user()->id,
                'created_at' => now(),
            ]);

            // Update the request date and status
            DB::table('requests')
                ->where('id', $validated['request_id'])
                ->update([
                    'execution_date' => $validated['new_date'],
                    'status_id' => $validated['transfer_to_planning'] ? 6 : 3, // ID статуса 'перенесена'
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
                'comments_count' => $commentsCount,
                'isPlanning' => $validated['transfer_to_planning'],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список всех адресов для формирования списка адресов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployees()
    {
        try {
            $employees = DB::select("
            SELECT e.* 
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.is_deleted = false 
            AND p.name != 'оператор'
            ORDER BY e.fio
        ");

            return response()->json($employees);
        } catch (\Exception $e) {
            Log::error('Error in HomeController@getEmployees: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка сотрудников',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список всех адресов для формирования списка адресов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAddresses()
    {
        try {
            $sql = "
            SELECT
                a.id,
                CONCAT(a.street, ', ', a.houses, ' [', CASE WHEN a.district = 'Не указан' THEN 'Район не указан' ELSE a.district END, '][', c.name, ']') as full_address,
                a.street,
                a.houses,
                c.name as city,
                a.district,
                a.latitude,
                a.longitude
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            ORDER BY a.street, a.houses
        ";

            $addresses = DB::select($sql);

            return response()->json($addresses);
        } catch (\Exception $e) {
            Log::error('Error in HomeController@getAddresses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка адресов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список всех адресов для формирования списка адресов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAddressesPaginated(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $perPage;

            // Общее количество записей
            $total = DB::table('addresses')->count();

            // Получаем данные с пагинацией
            $sql = '
            SELECT
                a.id,
                a.street,
                a.houses,
                a.district,
                a.doc,
                a.comments,
                a.responsible_person,
                a.latitude,
                a.longitude,
                c.created_at,
                c.updated_at,
                c.id as city_id,
                c.name as city_name,
                c.region_id,
                c.postal_code,
                ht.name as house_type_name,
                ht.description as house_type_description
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            LEFT JOIN house_types ht ON a.house_type_id = ht.id
            ORDER BY c.name, a.street, a.houses
            LIMIT ? OFFSET ?
        ';

            $addresses = DB::select($sql, [$perPage, $offset]);

            return response()->json([
                'data' => $addresses,
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in HomeController@getAddressesPaginated: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении адресов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список текущих бригад
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentBrigades()
    {
        try {
            $today = now()->toDateString();

            $sql = "SELECT e.id, b.id as brigade_id, e.fio AS leader_name, e.id as employee_id
                FROM brigades AS b
                JOIN employees AS e ON b.leader_id = e.id
                WHERE DATE(b.formation_date) >= '{$today}' and b.is_deleted = false";

            $brigades = DB::select($sql);

            return response()->json($brigades);
        } catch (\Exception $e) {
            Log::error('Error in HomeController@getCurrentBrigades: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении текущих бригад',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index()
    {
        try {
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

            // Запрашиваем employees с паспортными данными и должностями (активные)
            $employees = DB::select('
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
            ');

            // Запрашиваем уволенных сотрудников (не заблокированных)
            $firedEmployees = DB::select('
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
              WHERE e.is_deleted = true AND (e.is_blocked IS NULL OR e.is_blocked = false)
              ORDER BY e.fio
          ');

            // Запрашиваем addresses
            $addresses = DB::select('SELECT * FROM addresses');

            // Запрашиваем employees для фильтрации заявок
            $sql = "
            WITH today_brigades AS (
            SELECT DISTINCT r.brigade_id
            FROM requests r
            JOIN request_statuses rs ON rs.id = r.status_id
            WHERE r.execution_date = CURRENT_DATE
                AND rs.name NOT IN ('отменена', 'планирование')
                AND r.brigade_id IS NOT NULL
            )
            SELECT e.id, e.fio, b.id AS brigade_id, b.name AS brigade_name, FALSE AS is_leader
            FROM brigades b
            JOIN today_brigades tb ON tb.brigade_id = b.id
            JOIN brigade_members bm ON bm.brigade_id = b.id
            JOIN employees e ON e.id = bm.employee_id
            WHERE b.is_deleted = FALSE AND e.is_deleted = FALSE
            UNION
            SELECT el.id AS employee_id, el.fio, b.id AS brigade_id, b.name AS brigade_name, TRUE AS is_leader
            FROM brigades b
            JOIN today_brigades tb ON tb.brigade_id = b.id
            JOIN employees el ON el.id = b.leader_id
            WHERE b.is_deleted = FALSE AND el.is_deleted = FALSE;
        ";

            $employeesFilter = DB::select($sql);

            // Запрашиваем positions
            $positions = DB::select('SELECT * FROM positions');

            // Комплексный запрос для получения информации о членах бригад с данными о бригадах
            $brigadeMembersWithDetails_ = DB::select(
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

            $sql = 'SELECT
            b.id AS brigade_id,
            bm.employee_id,
            b.name AS brigade_name,
            b.leader_id,
            e.fio AS employee_name,
            e.phone AS employee_phone,
            e.group_role AS employee_group_role,
            e.sip AS employee_sip,
            e.position_id AS employee_position_id,
            el.fio AS employee_leader_name,
            el.phone AS employee_leader_phone,
            el.group_role AS employee_leader_group_role,
            el.sip AS employee_leader_sip,
            el.position_id AS employee_leader_position_id
        FROM brigades b
        LEFT JOIN brigade_members bm ON bm.brigade_id = b.id
        LEFT JOIN employees e ON bm.employee_id = e.id
        LEFT JOIN employees el ON b.leader_id = el.id
        WHERE b.is_deleted = false
        AND el.is_deleted = false
        ORDER BY b.id, employee_name';

            $brigadeMembersWithDetails = DB::select($sql);

            $sql = "WITH today_brigades AS (
            SELECT DISTINCT r.brigade_id
            FROM requests r
            JOIN request_statuses rs ON rs.id = r.status_id
            WHERE r.execution_date = CURRENT_DATE
                AND rs.name NOT IN ('отменена', 'планирование')
                AND r.brigade_id IS NOT NULL
            )
            SELECT e.id, e.fio, b.id AS brigade_id, b.name AS brigade_name, FALSE AS is_leader
            FROM brigades b
            JOIN today_brigades tb ON tb.brigade_id = b.id
            JOIN brigade_members bm ON bm.brigade_id = b.id
            JOIN employees e ON e.id = bm.employee_id
            WHERE b.is_deleted = FALSE AND e.is_deleted = FALSE
            UNION
            SELECT el.id AS employee_id, el.fio, b.id AS brigade_id, b.name AS brigade_name, TRUE AS is_leader
            FROM brigades b
            JOIN today_brigades tb ON tb.brigade_id = b.id
            JOIN employees el ON el.id = b.leader_id
            WHERE b.is_deleted = FALSE AND el.is_deleted = FALSE
            ORDER BY brigade_id DESC";

            $brigadeMembersCurrentDay = DB::select($sql);

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
                            'author_name' => $comment->author_name,
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
                 rt.name AS request_type_name,
                 rt.color AS request_type_color,
                 b.name AS brigade_name,
                 e.fio AS brigade_lead,
                 op.fio AS operator_name,
                 op.user_id AS operator_user_id,
                 role_data.role_name AS operator_role,
                 addr.id AS address_id,
                 addr.street,
                 addr.houses,
                 addr.district,
                 addr.city_id,
                 addr.latitude,
                 addr.longitude,
                 ct.name AS city_name,
                 ct.postal_code AS city_postal_code,
                 (
                    SELECT quantity
                    FROM work_parameters wp
                    WHERE wp.request_id = r.id
                    ORDER BY wp.id ASC
                    LIMIT 1
                 ) AS first_param_quantity
             FROM requests r
             LEFT JOIN clients c ON r.client_id = c.id
             LEFT JOIN request_statuses rs ON r.status_id = rs.id
             LEFT JOIN request_types rt ON r.request_type_id = rt.id
             LEFT JOIN brigades b ON r.brigade_id = b.id
             LEFT JOIN employees e ON b.leader_id = e.id
             LEFT JOIN employees op ON r.operator_id = op.id
             LEFT JOIN request_addresses ra ON r.id = ra.request_id
             LEFT JOIN addresses addr ON ra.address_id = addr.id
             LEFT JOIN cities ct ON addr.city_id = ct.id
             LEFT JOIN LATERAL (
                 SELECT r.name AS role_name
                 FROM user_roles ur
                 JOIN roles r ON ur.role_id = r.id
                 WHERE ur.user_id = op.user_id
                 LIMIT 1
             ) AS role_data ON true
             WHERE r.execution_date::date = CURRENT_DATE
             AND (b.is_deleted = false OR b.id IS NULL)
             AND rs.name != 'отменена'
             AND rs.name != 'планирование'
             ORDER BY r.id DESC";

            if ($user->isFitter) {
                $sql = "
                    SELECT
                        r.*,
                        c.fio AS client_fio,
                        c.phone AS client_phone,
                        c.organization AS client_organization,
                        rs.name AS status_name,
                        rs.color AS status_color,
                        rt.name AS request_type_name,
                        rt.color AS request_type_color,
                        b.name AS brigade_name,
                        e.fio AS brigade_lead,
                        op.fio AS operator_name,
                        addr.id AS address_id,
                        addr.street,
                        addr.houses,
                        addr.district,
                        addr.city_id,
                        addr.latitude,
                        addr.longitude,
                        ct.name AS city_name,
                        ct.postal_code AS city_postal_code,
                        (
                            SELECT quantity
                            FROM work_parameters wp
                            WHERE wp.request_id = r.id
                            ORDER BY wp.id ASC
                            LIMIT 1
                        ) AS first_param_quantity
                    FROM requests r
                    LEFT JOIN clients c ON r.client_id = c.id
                    LEFT JOIN request_statuses rs ON r.status_id = rs.id
                    LEFT JOIN request_types rt ON r.request_type_id = rt.id
                    LEFT JOIN brigades b ON r.brigade_id = b.id
                    LEFT JOIN employees e ON b.leader_id = e.id
                    LEFT JOIN employees op ON r.operator_id = op.id
                    LEFT JOIN request_addresses ra ON r.id = ra.request_id
                    LEFT JOIN addresses addr ON ra.address_id = addr.id
                    LEFT JOIN cities ct ON addr.city_id = ct.id
                    WHERE r.execution_date::date = CURRENT_DATE
                    AND (b.is_deleted = false OR b.id IS NULL)
                    AND rs.name != 'отменена'
                    AND rs.name != 'планирование'
                    AND (
                        EXISTS (
                            SELECT 1
                            FROM brigade_members bm
                            JOIN employees emp ON bm.employee_id = emp.id
                            WHERE bm.brigade_id = r.brigade_id
                                AND emp.user_id = {$user->id}
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM employees emp_leader
                            WHERE b.leader_id = emp_leader.id
                                AND emp_leader.user_id = {$user->id}
                        )
                    )
                    ORDER BY r.id DESC
                ";
            }

            $requests = DB::select($sql);

            $flags = [
                'new' => 'new',
                'in_work' => 'in_work',
                'waiting_for_client' => 'waiting_for_client',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'under_review' => 'under_review',
                'on_hold' => 'on_hold',
            ];

            // Получаем список городов для выпадающего списка
            $cities = DB::table('cities')->orderBy('name')->get();

            // Получаем список регионов для выпадающего списка
            $regions = DB::table('regions')->orderBy('name')->get();

            // Собираем все переменные для передачи в представление
            $viewData = [
                'user' => $user,
                'users' => $users,
                'clients' => $clients,
                'request_statuses' => $request_statuses,
                'requests' => $requests,
                'brigades' => $brigades,
                'employees' => $employees,
                'firedEmployees' => $firedEmployees,
                'employeesFilter' => $employeesFilter,
                'addresses' => $addresses,
                'brigade_members' => $brigade_members,
                'comments_by_request' => $comments_by_request,
                'request_addresses' => $request_addresses,
                'requests_types' => $requests_types,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'brigadeMembersCurrentDay' => $brigadeMembersCurrentDay,
                'brigadesCurrentDay' => $brigadesCurrentDay,
                'flags' => $flags,
                'positions' => $positions,
                'roles' => $roles,
                'cities' => $cities, // Добавляем список городов
                'regions' => $regions, // Добавляем список регионов
                'isAdmin' => $user->isAdmin ?? false,
                'isUser' => $user->isUser ?? false,
                'isFitter' => $user->isFitter ?? false,
                'sql' => $sql,
            ];

            return view('welcome', $viewData);
        } catch (\Exception $e) {
            Log::error('Error in HomeController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при загрузке главной страницы',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Добавление комментария к заявке
     */
    public function addComment(Request $request)
    {
        try {
            // Собираем информацию о файлах
            $filesInfo = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $index => $file) {
                    $filesInfo[] = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'extension' => $file->getClientOriginalExtension(),
                    ];
                }
            }

            // Собираем информацию о фото
            $photosInfo = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $photo) {
                    $photosInfo[] = [
                        'name' => $photo->getClientOriginalName(),
                        'size' => $photo->getSize(),
                        'type' => $photo->getMimeType(),
                        'extension' => $photo->getClientOriginalExtension(),
                    ];
                }
            }

            // Включаем логирование SQL-запросов
            \DB::enableQueryLog();

            // Валидируем входные данные
            $validated = $request->validate([
                'request_id' => 'required|exists:requests,id',
                'comment' => 'required|string|max:1000',
                'photos' => 'nullable|array|max:100',
                'photos.*' => 'file|max:512000|mimes:jpg,jpeg,png,gif,webp,bmp,tiff,heic,heif',
                'files' => 'nullable|array|max:100',
                'files.*' => [
                    'file',
                    'max:512000',
                    function ($attribute, $value, $fail) {
                        $allowedMimeTypes = [
                            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff',
                            'image/heic', 'image/heif', 'application/pdf', 'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/plain', 'text/html', 'application/zip', 'application/x-rar', 'application/x-rar-compressed',
                            'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-matroska',
                            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
                        ];

                        // Для файлов с расширением .txt разрешаем text/html
                        if (strtolower($value->getClientOriginalExtension()) === 'txt' && $value->getMimeType() === 'text/html') {
                            return true;
                        }

                        if (! in_array($value->getMimeType(), $allowedMimeTypes)) {
                            $errorMessage = "Файл {$value->getClientOriginalName()} имеет недопустимый тип: ".$value->getMimeType().
                                         '. Разрешенные типы: '.implode(', ', $allowedMimeTypes);
                            $fail($errorMessage);
                        }
                    },
                ],
                '_token' => 'required|string',
            ]);

            // Проверяем существование заявки
            $requestExists = DB::selectOne(
                'SELECT COUNT(*) as count FROM requests WHERE id = ?',
                [$validated['request_id']]
            );

            $requestExists = $requestExists->count > 0;

            if (! $requestExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Заявка не найдена',
                ], 404);
            }

            // Получаем данные типа заявки для ответа
            $requestTypeData = DB::selectOne(
                'SELECT rt.name AS request_type_name, rt.color AS request_type_color FROM requests r LEFT JOIN request_types rt ON r.request_type_id = rt.id WHERE r.id = ?',
                [$validated['request_id']]
            );

            // Начинаем транзакцию
            DB::beginTransaction();

            // Массив для хранения ID загруженных файлов
            $uploadedFileIds = [];

            try {
                // Получаем структуру таблицы requests, чтобы найти колонку с датой
                $tableInfo = DB::selectOne(
                    "SELECT column_name
                     FROM information_schema.columns
                     WHERE table_name = 'requests'
                     AND data_type IN ('timestamp without time zone', 'timestamp with time zone', 'date', 'datetime')"
                );

                if (! $tableInfo) {
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

                // Вставляем комментарий
                $result = DB::insert(
                    'INSERT INTO comments (comment, created_at) VALUES (?, ?) RETURNING id',
                    [$comment, $createdAt]
                );

                if (! $result) {
                    throw new \Exception('Не удалось создать комментарий');
                }

                // Получаем ID вставленного комментария
                $commentId = DB::getPdo()->lastInsertId();

                // Привязываем комментарий к заявке
                $requestId = $validated['request_id'];
                $userId = $request->user()->id;

                // Вставляем связь с заявкой
                $result = DB::insert(
                    'INSERT INTO request_comments (request_id, comment_id, user_id, created_at) VALUES (?, ?, ?, ?)',
                    [$requestId, $commentId, $userId, $createdAt]
                );

                if (! $result) {
                    throw new \Exception('Не удалось привязать комментарий к заявке');
                }

                // Обработка загруженных файлов
                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $file) {
                        try {
                            // Сохранить файл в папку storage/app/public/images
                            // Используем оригинальное имя файла
                            $fileName = $file->getClientOriginalName();

                            // Сохраняем файл напрямую в целевую директорию
                            $path = storage_path('app/public/images');
                            if (! file_exists($path)) {
                                mkdir($path, 0755, true);
                            }
                            $stored = file_put_contents(
                                $path.'/'.$fileName,
                                file_get_contents($file->getRealPath())
                            ) !== false;

                            if ($stored === false) {
                                throw new \RuntimeException('Не удалось сохранить файл. Проверьте права на запись в директорию: '.storage_path('app/public/images'));
                            }

                            // Получить основную информацию о файле
                            $fileInfo = [
                                'name' => $file->getClientOriginalName(),
                                'type' => $file->getMimeType(),
                                'extension' => $file->getClientOriginalExtension(),
                                'size' => $file->getSize(),
                                'path' => $path.'/'.$fileName,
                                'url' => asset('storage/images/'.$fileName),
                            ];

                        } catch (\Exception $e) {
                            throw new \Exception('Не удалось сохранить файл: '.$e->getMessage());
                        }

                        if (strpos($fileInfo['type'], 'image/') === 0) {
                            $relativePath = 'images/'.$fileInfo['name'];

                            // Проверяем, существует ли уже такая фотография
                            $existingPhoto = DB::table('photos')
                                ->where('path', $relativePath)
                                ->first();

                            if ($existingPhoto) {
                                // Используем существующую фотографию
                                $photoId = $existingPhoto->id;
                            } else {
                                // Получаем размеры изображения
                                [$width, $height] = @getimagesize($fileInfo['path']) ?: [null, null];

                                $photoId = DB::table('photos')->insertGetId([
                                    'path' => $relativePath,
                                    'original_name' => $fileInfo['name'],
                                    'file_size' => $fileInfo['size'],
                                    'mime_type' => $fileInfo['type'],
                                    'width' => $width,
                                    'height' => $height,
                                    'created_by' => $userId,
                                    'created_at' => $createdAt,
                                    'updated_at' => $createdAt,
                                ]);
                            }

                            // Проверяем, не существует ли уже связь с заявкой
                            $existingRequestLink = DB::table('request_photos')
                                ->where('request_id', $requestId)
                                ->where('photo_id', $photoId)
                                ->first();

                            // Если связи с заявкой еще нет - создаем
                            if (! $existingRequestLink) {
                                DB::table('request_photos')->insert([
                                    'request_id' => $requestId,
                                    'photo_id' => $photoId,
                                    'created_at' => $createdAt,
                                    'updated_at' => $createdAt,
                                ]);
                            }

                            // Проверяем, не существует ли уже связь с комментарием
                            $existingCommentLink = DB::table('comment_photos')
                                ->where('comment_id', $commentId)
                                ->where('photo_id', $photoId)
                                ->first();

                            // Если связи с комментарием еще нет - создаем
                            if (! $existingCommentLink) {
                                DB::table('comment_photos')->insert([
                                    'comment_id' => $commentId,
                                    'photo_id' => $photoId,
                                    'created_at' => $createdAt,
                                    'updated_at' => $createdAt,
                                ]);
                            }
                        }
                    }
                }

                if ($request->hasFile('files')) {
                    foreach ($request->file('files') as $file) {
                        try {
                            // Сохранить файл в папку storage/app/public/files
                            $fileName = $file->getClientOriginalName();

                            // Сохраняем файл напрямую в целевую директорию
                            $path = storage_path('app/public/files');
                            if (! file_exists($path)) {
                                mkdir($path, 0755, true);
                            }
                            $stored = file_put_contents(
                                $path.'/'.$fileName,
                                file_get_contents($file->getRealPath())
                            ) !== false;

                            if ($stored === false) {
                                throw new \RuntimeException('Не удалось сохранить файл. Проверьте права на запись в директорию: '.storage_path('app/public/files'));
                            }

                            // Получить основную информацию о файле
                            $fileInfo = [
                                'name' => $file->getClientOriginalName(),
                                'type' => $file->getMimeType(),
                                'extension' => $file->getClientOriginalExtension(),
                                'size' => $file->getSize(),
                                'path' => $path.'/'.$fileName,
                                'url' => asset('storage/files/'.$fileName),
                            ];

                            $relativePath = 'files/'.$fileInfo['name'];

                            // Проверяем, существует ли уже такой файл
                            $existingFile = DB::table('files')
                                ->where('path', $relativePath)
                                ->first();

                            if ($existingFile) {
                                // Используем существующий файл
                                $fileId = $existingFile->id;
                            } else {
                                // Создаем новую запись о файле
                                $fileId = DB::table('files')->insertGetId([
                                    'path' => $relativePath,
                                    'original_name' => $fileInfo['name'],
                                    'file_size' => $fileInfo['size'],
                                    'mime_type' => $fileInfo['type'],
                                    'extension' => $fileInfo['extension'],
                                    'created_by' => $userId,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }

                            // Связываем файл с комментарием
                            DB::table('comment_files')->insert([
                                'comment_id' => $commentId,
                                'file_id' => $fileId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                        } catch (\Exception $e) {
                            throw new \Exception('Не удалось сохранить файл: '.$e->getMessage());
                        }
                    }
                }

                // Фиксируем транзакцию
                DB::commit();

                // Получаем обновленный список комментариев
                $comments = DB::select(
                    'SELECT c.* FROM comments c
                    INNER JOIN request_comments rc ON c.id = rc.comment_id
                    WHERE rc.request_id = ?
                    ORDER BY c.created_at DESC',
                    [$requestId]
                );

                // Временно закомментировано для comment_files
                $files = [];

                return response()->json([
                    'success' => true,
                    'message' => 'Комментарий успешно добавлен',
                    'comments' => $comments,
                    'commentId' => $commentId,
                    'files' => $files,
                    'request_type_name' => $requestTypeData->request_type_name ?? null,
                    'request_type_color' => $requestTypeData->request_type_color ?? null,
                ]);
            } catch (\Exception $e) {
                // Откатываем изменения при ошибке
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                // Логируем ошибку
                \Log::error('Ошибка при добавлении комментария: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'request_data' => $request->all(),
                ]);

                $errorInfo = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'sql_queries' => \DB::getQueryLog(),
                ];

                return response()->json([
                    'success' => false,
                    'message' => 'Произошла ошибка при добавлении комментария: '.$e->getMessage(),
                    'error_details' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error in HomeController@addComment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Критическая ошибка: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение заявок по дате
     */
    public function getRequestsByDate(Request $request, $date)
    {
        try {
            $user = auth()->user();
            $includePlanning = filter_var($request->query('include_planning', false), FILTER_VALIDATE_BOOLEAN);

            // Загружаем роли пользователя
            $sql = 'SELECT roles.name FROM user_roles
                JOIN roles ON user_roles.role_id = roles.id
                WHERE user_roles.user_id = '.$user->id;

            $roles = DB::select($sql);

            // Извлекаем только имена ролей из результатов запроса
            $roleNames = array_map(function ($role) {
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
                'date' => 'required|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный формат даты. Ожидается YYYY-MM-DD',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $requestDate = $validated['date'];

            // Формируем условие для статусов и дат
            // Стандартное условие: выбранная дата и не (планирование или удалена)
            $whereClause = "DATE(r.execution_date) = ? AND r.status_id NOT IN (6,7)";
            $bindings = [$requestDate];

            // Если нужно включить планирование
            if ($includePlanning) {
                // (Дата = Х И не удалена/планирование) ИЛИ (статус = планирование И не удалена)
                // Статус 6 - планирование, 7 - удалена
                $whereClause = "(DATE(r.execution_date) = ? AND r.status_id NOT IN (6,7)) OR (r.status_id = 6)";
            }

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
                        rt.name AS request_type_name,
                        rt.color AS request_type_color,
                        b.name AS brigade_name,
                        b.id AS brigade_id,
                        e.fio AS brigade_lead,
                        op.fio AS operator_name,
                        CONCAT(addr.street, ', д. ', addr.houses) AS address,
                        addr.id AS address_id,
                        addr.street,
                        addr.houses,
                        addr.district,
                        addr.city_id,
                        addr.latitude,
                        addr.longitude,
                        ct.name AS city_name,
                        (
                            SELECT COUNT(*)
                            FROM request_comments rc
                            WHERE rc.request_id = r.id
                        ) AS comments_count,
                        (
                            SELECT quantity
                            FROM work_parameters wp
                            WHERE wp.request_id = r.id
                            ORDER BY wp.id ASC
                            LIMIT 1
                        ) AS first_param_quantity
                    FROM requests r
                    LEFT JOIN clients c ON r.client_id = c.id
                    LEFT JOIN request_statuses rs ON r.status_id = rs.id
                    LEFT JOIN request_types rt ON r.request_type_id = rt.id
                    LEFT JOIN brigades b ON r.brigade_id = b.id
                    LEFT JOIN employees e ON b.leader_id = e.id
                    LEFT JOIN employees op ON r.operator_id = op.id
                    LEFT JOIN request_addresses ra ON r.id = ra.request_id
                    LEFT JOIN addresses addr ON ra.address_id = addr.id
                    LEFT JOIN cities ct ON addr.city_id = ct.id
                     WHERE $whereClause
                     AND (b.is_deleted = false OR b.id IS NULL)
                    AND (
                        EXISTS (
                            SELECT 1
                            FROM brigade_members bm
                            JOIN employees emp ON bm.employee_id = emp.id
                            WHERE bm.brigade_id = r.brigade_id
                                AND emp.user_id = {$user->id}
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM employees emp_leader
                            WHERE b.leader_id = emp_leader.id
                                AND emp_leader.user_id = {$user->id}
                        )
                    )
                    ORDER BY r.id DESC;
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
                        rt.name AS request_type_name,
                        rt.color AS request_type_color,
                        b.name AS brigade_name,
                        b.id AS brigade_id,
                        e.fio AS brigade_lead,
                        op.fio AS operator_name,
                        CONCAT(addr.street, ', д. ', addr.houses) as address,
                        addr.id AS address_id,
                        addr.street,
                        addr.houses,
                        addr.district,
                        addr.city_id,
                        addr.latitude,
                        addr.longitude,
                        ct.name AS city_name,
                        (SELECT COUNT(*) FROM request_comments rc WHERE rc.request_id = r.id) as comments_count,
                        (
                            SELECT quantity
                            FROM work_parameters wp
                            WHERE wp.request_id = r.id
                            ORDER BY wp.id ASC
                            LIMIT 1
                        ) AS first_param_quantity
                    FROM requests r
                    LEFT JOIN clients c ON r.client_id = c.id
                    LEFT JOIN request_statuses rs ON r.status_id = rs.id
                    LEFT JOIN request_types rt ON r.request_type_id = rt.id
                    LEFT JOIN brigades b ON r.brigade_id = b.id
                    LEFT JOIN employees e ON b.leader_id = e.id
                    LEFT JOIN employees op ON r.operator_id = op.id
                    LEFT JOIN request_addresses ra ON r.id = ra.request_id
                    LEFT JOIN addresses addr ON ra.address_id = addr.id
                    LEFT JOIN cities ct ON addr.city_id = ct.id
                    WHERE $whereClause AND (b.is_deleted = false OR b.id IS NULL)
                    ORDER BY r.id DESC
                ";
            }

            $requestByDate = DB::select($sqlRequestByDate, $bindings);

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

            if (! empty($requestIds)) {
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
                    WHERE rc.request_id IN (".implode(',', $requestIds).')
                    ORDER BY c.created_at DESC
                ');

                // Группируем комментарии по ID заявки
                foreach ($comments as $comment) {
                    $commentData = [
                        'id' => $comment->id ?? null,
                        'comment' => $comment->comment ?? '',
                        'created_at' => $comment->created_at ?? now(),
                        'author_name' => $comment->author_name ?? 'Система',
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

            if (! empty($brigadeIds)) {
                // Получаем всех членов бригад для загруженных заявок
                $members_old = DB::select('
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
                    WHERE bm.brigade_id IN ('.implode(',', $brigadeIds).')
                ');

                $sql = "
                    SELECT
                        b.id AS brigade_id,
                        COALESCE(e.fio, '') AS member_name,
                        e.phone AS member_phone,
                        e.position_id,
                        b.leader_id,
                        COALESCE(el.fio, '') AS employee_leader_name
                    FROM brigades b
                    LEFT JOIN brigade_members bm ON bm.brigade_id = b.id
                    LEFT JOIN employees e ON bm.employee_id = e.id
                    LEFT JOIN employees el ON b.leader_id = el.id
                    WHERE b.id IN (".implode(',', $brigadeIds).')
                    AND b.is_deleted = false
                    AND (el.id IS NULL OR el.is_deleted = false)
                    AND (e.id IS NULL OR e.is_deleted = false)
                    ORDER BY b.id, member_name
                ';

                $members = DB::select($sql);

                // Группируем членов по ID бригады и сохраняем информацию о бригадире
                $brigadeLeaders = [];

                foreach ($members as $member) {
                    // Сохраняем информацию о бригадире
                    if (! isset($brigadeLeaders[$member->brigade_id]) && $member->employee_leader_name) {
                        $brigadeLeaders[$member->brigade_id] = $member->employee_leader_name;
                    }

                    $brigadeMembers[$member->brigade_id][] = [
                        'name' => $member->member_name,
                        'phone' => $member->member_phone,
                        'position_id' => $member->position_id,
                    ];
                }
            }

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Режим тестирования',
            //     'brigadeMembers' => $brigadeMembers,
            //     'brigadeLeaders' => $brigadeLeaders,
            //     'brigadeIds' => $brigadeIds
            // ]);

            // Получаем ID заявок для загрузки комментариев
            $requestIds = array_column($requestByDate, 'id');
            $commentsByRequest = [];

            if (! empty($requestIds)) {
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
                    WHERE rc.request_id IN (".implode(',', $requestIds).')
                    ORDER BY c.created_at DESC
                ');

                // Группируем комментарии по ID заявки
                foreach ($comments as $comment) {
                    $commentsByRequest[$comment->request_id][] = [
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name,
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

            $sql = "WITH today_brigades AS (
                SELECT DISTINCT r.brigade_id
                FROM requests r
                JOIN request_statuses rs ON rs.id = r.status_id
                WHERE r.execution_date = CURRENT_DATE
                    AND rs.name NOT IN ('отменена', 'планирование')
                    AND r.brigade_id IS NOT NULL
                )
                SELECT e.id, e.fio, b.id AS brigade_id, b.name AS brigade_name, FALSE AS is_leader
                FROM brigades b
                JOIN today_brigades tb ON tb.brigade_id = b.id
                JOIN brigade_members bm ON bm.brigade_id = b.id
                JOIN employees e ON e.id = bm.employee_id
                WHERE b.is_deleted = FALSE AND e.is_deleted = FALSE
                UNION
                SELECT el.id AS employee_id, el.fio, b.id AS brigade_id, b.name AS brigade_name, TRUE AS is_leader
                FROM brigades b
                JOIN today_brigades tb ON tb.brigade_id = b.id
                JOIN employees el ON el.id = b.leader_id
                WHERE b.is_deleted = FALSE AND el.is_deleted = FALSE
                ORDER BY brigade_id DESC";

            $brigadeMembersCurrentDay = DB::select($sql);

            // Добавляем членов бригады, информацию о бригадире и комментарии к каждой заявке
            $result = array_map(function ($request) use ($brigadeMembers, $brigadeLeaders, $commentsByRequest, $brigadeMembersCurrentDay, $user) {
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
                $request->brigadeMembersCurrentDay = $brigadeMembersCurrentDay;

                return $request;
            }, $requestByDate);

            return response()->json([
                'success' => true,
                'data' => $result,
                'count' => count($result),
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении заявок: '.$e->getMessage(), [
                'exception' => $e,
                'date' => $date ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении заявок: '.$e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Получение количества заявок по дням за указанный месяц
     */
    public function getRequestCountsByMonth(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');

            if (!$month || !$year) {
                return response()->json(['success' => false, 'message' => 'Не указан год или месяц'], 400);
            }

            // Создаем даты начала и конца месяца
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            // Запрос количества заявок
            $counts = DB::table('requests')
                ->join('request_statuses', 'requests.status_id', '=', 'request_statuses.id')
                ->select(DB::raw('DATE(execution_date) as date'), DB::raw('count(*) as count'))
                ->whereBetween('execution_date', [$startDate, $endDate])
                ->whereNotIn('request_statuses.name', ['отменена', 'планирование'])
                ->groupBy('date')
                ->get();

            // Преобразуем в удобный формат ['YYYY-MM-DD' => count]
            $result = [];
            foreach ($counts as $row) {
                $result[$row->date] = $row->count;
            }

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            Log::error('Error getting request counts: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ошибка при получении количества заявок'], 500);
        }
    }

    /**
     * Получение комментариев к заявке
     */
    public function getComments($requestId)
    {
        try {
            $comments = DB::select("
                SELECT 
                    c.id,
                    c.comment,
                    c.created_at,
                    rc.user_id,
                    COALESCE(u.name, 'Система') AS author_name,
                    COALESCE(e.fio, '') AS employee_full_name,
                    c.created_at AS formatted_date,
                    (
                        SELECT COUNT(*)::int
                        FROM comment_edits ce
                        WHERE ce.comment_id = c.id
                    ) AS edits_count,
                    (
                        SELECT COUNT(*)::int
                        FROM comment_photos cp
                        WHERE cp.comment_id = c.id
                    ) AS photos_count,
                    (
                        SELECT COALESCE(
                            json_agg(
                                json_build_object(
                                    'file_id', f.id,
                                    'file_path', f.path,
                                    'file_name', f.original_name,
                                    'file_type', f.mime_type,
                                    'file_size', f.file_size
                                )
                            ), '[]'
                        )
                        FROM comment_files cf
                        JOIN files f ON cf.file_id = f.id
                        WHERE cf.comment_id = c.id
                    ) AS files
                FROM request_comments rc
                JOIN comments c ON rc.comment_id = c.id
                LEFT JOIN users u ON rc.user_id = u.id
                LEFT JOIN employees e ON u.id = e.user_id
                WHERE rc.request_id = ?
                ORDER BY c.created_at DESC
            ", [$requestId]);

            // Format the date for each comment
            foreach ($comments as &$comment) {
                $date = new \DateTime($comment->created_at);
                $comment->formatted_date = $date->format('d.m.Y H:i');
                if ($comment->author_name === 'Система') {
                    $comment->author_name = 'Система '.$comment->formatted_date;
                }
            }

            return response()->json($comments);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении комментариев: '.$e->getMessage());

            return response()->json([
                'error' => 'Ошибка при загрузке комментариев',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteRequest($id, Request $request)
    {
        try {
            \Log::info('=== START deleteRequest ===', []);

            $user = auth()->user();
            $user->method = 'HomeController::deleteRequest';
            $employee = $user->employee;
            $employee_role = $user->roles[0];

            $validated = $request->validate([
                'request_id' => 'required|exists:requests,id',
            ]);

            $request_id = $validated['request_id'];

            \Log::info('=== Все входные данные ===', ['request_id' => $request_id]);

            // Тестовый ответ

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Заявка завершена (test)',
            //     'data' => $request_id
            // ]);

            $sql = 'update requests set status_id = 7 where id = ?';
            $result = DB::update($sql, [$request_id]);

            \Log::info('=== Все выходные данные ===', ['sql' => 'update requests set status_id = 7 where id ='.$request_id, 'result' => $result]);

            \Log::info('=== END deleteRequest ===', []);

            return response()->json([
                'success' => true,
                'message' => 'Заявка удалена',
                'data' => $result,
                'request_id' => $request_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('=== START ERROR deleteRequest ===', []);
            \Log::error('Ошибка при завершении заявки: '.$e->getMessage());
            \Log::error('=== END ERROR deleteRequest ===', []);

            return response()->json([
                'error' => 'Ошибка при завершении заявки',
                'message' => $e->getMessage(),
            ], 500);
        }
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
            $user = auth()->user();
            $user->method = 'HomeController::closeRequest';
            $employee = $user->employee;
            $employee_role = $user->roles[0];

            \Log::info('=== START closeRequest ===', []);
            \Log::info('Все входные данные', ['data' => $request->all()]);
            \Log::info('ID заявки', ['id' => $id]);
            \Log::info('ID сотрудника', ['id' => $employee->id]);
            \Log::info('Роль сотрудника', ['role' => $employee_role]);

            $sql = 'select * from requests where id = ?';
            $result = DB::select($sql, [$id]);
            $operator_id = $result[0]->operator_id;
            $employee_id = $employee->id;

            // Проверяем, был ли текущий сотрудник членом бригады, выполнявшей данную заявку
            $sql = 'SELECT EXISTS (
                SELECT 1
                FROM requests r
                JOIN brigades b ON b.id = r.brigade_id
                LEFT JOIN brigade_members bm ON bm.brigade_id = r.brigade_id
                WHERE r.id = :request_id
                AND (
                        bm.employee_id = :employee_id
                    OR b.leader_id   = :employee_id
                )
            ) AS is_member;
            ';
            $memberRow = DB::selectOne($sql, [$id, $employee_id]);
            $isBrigadeMember = (bool) ($memberRow->is_member ?? false);

            // Роль user может закрывать заявки только заявки, где он раработал в составе бригады

            if ($employee_role === 'user' && ! $isBrigadeMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы не можете закрыть заявку, так как она создана другим сотрудником',
                    'RequestID' => $id,
                    'User' => $user,
                    'Employee' => $employee,
                    'operator_id' => $operator_id,
                    'employee_id' => $employee_id,
                    'role' => $employee_role,
                ], 403);
            }

            // Роль fitter может закрывать заявки только свои
            if ($employee_role === 'fitter' && ! $isBrigadeMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Вы не можете закрыть заявку, так как она создана другим сотрудником',
                    'RequestID' => $id,
                    'User' => $user,
                    'Employee' => $employee,
                    'operator_id' => $operator_id,
                    'employee_id' => $employee_id,
                    'role' => $employee_role,
                ], 403);
            }

            // тест
            // return response()->json([
            //     'success' => true,
            //     'message' => 'Заявка успешно закрыта (режим тестирования)',
            //     'RequestID' => $id,
            //     'RequestComment' => $request->input('comment'),
            //     'User' => $user,
            //     'Employee' => $employee,
            //     'operator_id' => $operator_id,
            //     'employee_id' => $employee_id,
            //     'role' => $employee_role,
            // Получаем параметры работы из запроса
            $workParameters = $request->input('work_parameters', []);

            // Получаем запланированные работы из базы данных
            $plannedWorkParameters = DB::table('work_parameters')
                ->where('request_id', $id)
                ->where('is_planning', true)
                ->where('is_done', false)
                ->get();

            // Собираем все parameter_type_id
            $allParameterTypeIds = [];
            if (! empty($workParameters)) {
                $allParameterTypeIds = array_merge($allParameterTypeIds, array_column($workParameters, 'parameter_type_id'));
            }
            if (! empty($plannedWorkParameters)) {
                $allParameterTypeIds = array_merge($allParameterTypeIds, $plannedWorkParameters->pluck('parameter_type_id')->toArray());
            }

            // Убираем дубликаты и приводим к строкам (если ID могут быть строками)
            $allParameterTypeIds = array_unique(array_map('strval', $allParameterTypeIds));

            $types = [];
            if (! empty($allParameterTypeIds)) {
                $types = DB::table('work_parameter_types')
                    ->whereIn('id', $allParameterTypeIds)
                    ->pluck('name', 'id')
                    ->toArray();
            }

            // Начинаем транзакцию
            DB::beginTransaction();

            // Обновляем статус заявки на 'выполнена' (ID 4)
            $updated = DB::table('requests')
                ->where('id', $id)
                ->update(['status_id' => 4]);

            if ($updated) {
                // Формируем комментарий для закрываемой заявки
                $commentText = $request->input('comment', 'Заявка закрыта');

                // Формируем часть комментария о запланированных работах
                if (! empty($plannedWorkParameters) && count($plannedWorkParameters) > 0) {
                    $plannedWorksInfoPart = '';
                    if (! empty($commentText)) {
                        $plannedWorksInfoPart .= '<br><br>';
                    }
                    $plannedWorksInfoPart .= 'Запланированные работы:';
                    foreach ($plannedWorkParameters as $param) {
                        $typeName = $types[$param->parameter_type_id] ?? 'Неизвестная работа';
                        $plannedWorksInfoPart .= "<br>- {$typeName}: {$param->quantity}";
                    }
                    $commentText .= $plannedWorksInfoPart;
                }

                \Log::info('Параметры работы:', [
                    'workParameters' => $workParameters,
                ]);

                if (! empty($workParameters) && is_array($workParameters)) {
                    $worksInfoPart = '';
                    // Добавляем <br><br> только если $commentText уже что-то содержит
                    if (! empty($commentText)) {
                        $worksInfoPart .= '<br><br>';
                    }
                    $worksInfoPart .= 'Выполненные работы:';
                    foreach ($workParameters as $param) {
                        $typeName = $types[$param['parameter_type_id']] ?? 'Неизвестная работа';
                        $worksInfoPart .= "<br>- {$typeName}: {$param['quantity']}";
                    }
                    $commentText .= $worksInfoPart;
                }

                \Log::info('Комментарий для закрываемой заявки:', [
                    'commentText' => $commentText,
                ]);

                // Создаем комментарий
                $commentId = DB::table('comments')->insertGetId([
                    'comment' => $commentText,
                    'created_at' => now(),
                ]);
                // Связываем комментарий с заявкой
                DB::table('request_comments')->insert([
                    'request_id' => $id,
                    'comment_id' => $commentId,
                    'user_id' => $request->user()->id,
                    'created_at' => now(),
                ]);

                // Создаем записи параметров работы
                if (! empty($workParameters) && is_array($workParameters)) {
                    try {
                        foreach ($workParameters as $param) {
                            DB::table('work_parameters')->insert([
                                'request_id' => $id,
                                'parameter_type_id' => $param['parameter_type_id'],
                                'quantity' => $param['quantity'],
                                'is_planning' => false, // Это выполненная работа
                                'is_done' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        \Log::info('Созданы параметры выполненной работы для заявки:', [
                            'request_id' => $id,
                            'count' => count($workParameters),
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Ошибка при создании параметров выполненной работы: '.$e->getMessage());
                        throw $e; // Критическая ошибка при закрытии заявки
                    }
                }

                // Обновляем статус запланированных работ: устанавливаем is_done = true и is_planning = false
                if (! empty($plannedWorkParameters) && count($plannedWorkParameters) > 0) {
                    $plannedWorkIds = $plannedWorkParameters->pluck('id')->toArray();
                    DB::table('work_parameters')
                        ->whereIn('id', $plannedWorkIds)
                        ->update([
                            'is_planning' => false,
                            'is_done' => true,
                            'updated_at' => now(),
                        ]);

                    \Log::info('Обновлен статус запланированных работ для заявки:', [
                        'request_id' => $id,
                        'count' => count($plannedWorkParameters),
                    ]);
                }

                // Если отмечен чекбокс "Недоделанные работы", добавляем запись в таблицу incomplete_works
                if ($request->input('uncompleted_works')) {
                    DB::table('incomplete_works')->insert([
                        'request_id' => $id,
                        'description' => $request->input('comment', 'Недоделанные работы'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // И создаем заявку на завтра с комментарием о недоделанных работах

                    // Получаем ID сотрудника, связанного с текущим пользователем
                    $employeeId = DB::table('employees')
                        ->where('user_id', Auth::id())
                        ->value('id');

                    //

                    // Если не нашли сотрудника, используем ID по умолчанию
                    if (! $employeeId) {
                        throw new \Exception('Не удалось найти сотрудника для текущего пользователя');
                    }

                    // Получаем данные текущей заявки
                    $currentRequest = DB::table('requests')->where('id', $id)->first();

                    if (! $currentRequest) {
                        throw new \Exception('Текущая заявка не найдена');
                    }

                    // Получаем статус "перенесена"
                    $transferredStatus = DB::table('request_statuses')->where('name', 'перенесена')->first();

                    if (! $transferredStatus) {
                        throw new \Exception('Статус "перенесена" не найден в базе данных');
                    }

                    // Генерируем номер заявки
                    $count = DB::table('requests')->count() + 1;
                    $requestNumber = 'REQ-'.date('Ymd').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);

                    // Создаем новую заявку на завтра с тем же типом, что и у текущей
                    $newRequestId = DB::table('requests')->insertGetId([
                        'number' => $requestNumber,
                        'client_id' => $currentRequest->client_id, // Копируем client_id из текущей заявки
                        'brigade_id' => null,
                        'status_id' => $transferredStatus->id,
                        'request_type_id' => $currentRequest->request_type_id, // Используем тот же тип заявки
                        'operator_id' => $employeeId, // Используем ID сотрудника
                        'execution_date' => now()->addDay()->toDateString(),
                        'request_date' => now()->toDateString(),
                    ]);

                    // Создаем параметры работы (запланированные) для новой заявки (недоделанные работы)
                    try {
                        if (! empty($workParameters) && is_array($workParameters)) {
                            foreach ($workParameters as $param) {
                                DB::table('work_parameters')->insert([
                                    'request_id' => $newRequestId,
                                    'parameter_type_id' => $param['parameter_type_id'],
                                    'quantity' => $param['quantity'],
                                    'is_planning' => true, // Запланированные
                                    'is_done' => false,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }

                        \Log::info('Созданы параметры запланированной работы для новой заявки:', [
                            'new_request_id' => $newRequestId,
                            'count' => count($workParameters),
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Ошибка при создании параметров запланированной работы для новой заявки: '.$e->getMessage());
                        throw $e;
                    }

                    // Получаем адрес текущей заявки
                    $requestAddress = DB::table('request_addresses')
                        ->where('request_id', $id)
                        ->first();

                    // Если адрес найден, копируем его для новой заявки
                    if ($requestAddress) {
                        DB::table('request_addresses')->insert([
                            'request_id' => $newRequestId,
                            'address_id' => $requestAddress->address_id,
                        ]);
                    }
                }

                // Собираем данные для уведомления в Telegram (только для "Демонтаж МЭШ")
                $requestDataForNotify = DB::table('requests')
                    ->join('request_types', 'requests.request_type_id', '=', 'request_types.id')
                    ->join('clients', 'requests.client_id', '=', 'clients.id')
                    ->leftJoin('request_addresses', 'requests.id', '=', 'request_addresses.request_id')
                    ->leftJoin('addresses', 'request_addresses.address_id', '=', 'addresses.id')
                    ->select('requests.*', 'request_types.name as type_name', 'clients.organization', 'addresses.street', 'addresses.houses', 'addresses.district')
                    ->where('requests.id', $id)
                    ->first();

                // Фиксируем изменения
                DB::commit();

                // Отправка уведомления в Telegram
                if ($requestDataForNotify && $requestDataForNotify->type_name == 'Демонтаж МЭШ') {
                    try {
                        // Получаем состав бригады
                        $leaderFio = '';
                        if ($requestDataForNotify->brigade_id) {
                            $leaderFio = DB::table('employees')
                                ->join('brigades', 'employees.id', '=', 'brigades.leader_id')
                                ->where('brigades.id', $requestDataForNotify->brigade_id)
                                ->value('fio');
                        }

                        $membersFio = DB::table('employees')
                            ->join('brigade_members', 'employees.id', '=', 'brigade_members.employee_id')
                            ->where('brigade_members.brigade_id', $requestDataForNotify->brigade_id)
                            ->pluck('fio')
                            ->toArray();

                        $brigadeListParts = [];
                        if ($leaderFio) {
                            $brigadeListParts[] = '- ' . $leaderFio . ' (бригадир)';
                        }
                        if (!empty($membersFio)) {
                            foreach ($membersFio as $member) {
                                $brigadeListParts[] = '- ' . $member;
                            }
                        }
                        $brigadeListStr = !empty($brigadeListParts) ? implode("\n", $brigadeListParts) : 'Не назначена';

                        $addressStr = trim(($requestDataForNotify->district ?? '') . ' ' . ($requestDataForNotify->street ?? '') . ' ' . ($requestDataForNotify->houses ?? ''));

                        // Получаем выполненные работы
                        $completedWorks = DB::table('work_parameters')
                            ->join('work_parameter_types', 'work_parameters.parameter_type_id', '=', 'work_parameter_types.id')
                            ->where('work_parameters.request_id', $id)
                            ->where('work_parameters.quantity', '>', 0)
                            ->select('work_parameter_types.name', 'work_parameters.quantity')
                            ->get();

                        $worksStr = '';
                        if ($completedWorks->isNotEmpty()) {
                            $worksStr = "🛠 <b>Выполненные работы:</b>\n";
                            foreach ($completedWorks as $work) {
                                $worksStr .= "- " . htmlspecialchars($work->name) . ": " . $work->quantity . "\n";
                            }
                            $worksStr .= "\n"; // Добавляем отступ после блока работ
                        }

                        // Берем исходный комментарий пользователя
                        $rawComment = $request->input('comment', '');
                        
                        // Заменяем теги переноса строк на реальные переносы
                        $rawComment = str_ireplace(['<br />', '<br>', '<br/>'], "\n", $rawComment);
                        // Заменяем закрывающие теги блоков на переносы (чтобы параграфы не слипались)
                        $rawComment = str_ireplace(['</p>', '</div>', '</h1>', '</h2>', '</h3>'], "\n", $rawComment);
                        
                        // Теперь чистим остальные теги и декодируем сущности
                        $cleanComment = trim(html_entity_decode(strip_tags($rawComment)));
                        
                        if (empty($cleanComment)) {
                             $cleanComment = 'Нет комментария';
                        }

                        // Проверяем наличие фото или файлов для формирования ссылки
                        $hasPhotos = DB::table('comment_photos')
                            ->join('request_comments', 'comment_photos.comment_id', '=', 'request_comments.comment_id')
                            ->where('request_comments.request_id', $id)
                            ->exists();
                            
                        $hasFiles = DB::table('comment_files')
                            ->join('request_comments', 'comment_files.comment_id', '=', 'request_comments.comment_id')
                            ->where('request_comments.request_id', $id)
                            ->exists();

                        // Экранируем данные для HTML мода Telegram
                        $orgName = htmlspecialchars($requestDataForNotify->organization ?? 'Не указана');
                        $addrName = htmlspecialchars($addressStr ?: 'Не указан');
                        $brigadeName = htmlspecialchars($brigadeListStr ?: 'Не назначена');
                        $cleanComment = htmlspecialchars($cleanComment);

                        $notifyMessage = "✅ <b>Заявка #{$id} закрыта (Демонтаж МЭШ)</b>\n\n"
                                       . "🏢 <b>Организация:</b> {$orgName}\n"
                                       . "📍 <b>Адрес:</b> {$addrName}\n"
                                       . "👥 <b>Бригада:</b>\n{$brigadeName}\n\n"
                                       . $worksStr
                                       . "📝 <b>Комментарий:</b>\n{$cleanComment}";

                        // Добавляем ссылку только если есть что скачивать
                        if ($hasPhotos || $hasFiles) {
                            $secret = config('app.key');
                            $token = md5($id . $secret . 'telegram-notify');
                            $downloadUrl = route('photo-report.download.public', ['requestId' => $id, 'token' => $token]);
                            
                            // Используем ДВОЙНЫЕ кавычки для href (стандарт HTML/XML), так как proc_open это позволяет
                            $notifyMessage .= "\n\n🔗 <a href=\"{$downloadUrl}\">Скачать фото и файлы по заявке #{$id}</a>";
                        }

                        $scriptPath = base_path('utils/C/notify-bot/telegram_notify');
                        
                        if (file_exists($scriptPath)) {
                            // Используем proc_open для прямой передачи данных в stdin процесса
                            $descriptorspec = [
                                0 => ['pipe', 'r'],  // stdin
                                1 => ['file', '/dev/null', 'w'], // stdout в null (фон)
                                2 => ['file', '/dev/null', 'w']  // stderr в null (фон)
                            ];
                            
                            // Запускаем синхронно, чтобы гарантировать передачу данных в stdin
                            // Утилита на C работает быстро, задержка будет минимальной
                            $process = proc_open($scriptPath, $descriptorspec, $pipes);
                            
                            if (is_resource($process)) {
                                fwrite($pipes[0], $notifyMessage);
                                fclose($pipes[0]);
                                
                                // Ждем завершения процесса (это быстро)
                                proc_close($process);
                                
                                \Log::info('Отправлено подробное уведомление в Telegram для заявки #' . $id);
                            } else {
                                \Log::error('Не удалось запустить процесс отправки уведомления');
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Ошибка при формировании/отправке уведомления в Telegram: ' . $e->getMessage());
                    }
                }

                // Формируем ответ JSON
                $response = [
                    'success' => true,
                    'message' => 'Заявка успешно закрыта',
                    'comment_id' => $commentId,
                ];

                // Если была создана новая заявка на недоделанные работы, добавляем её ID в ответ
                if (isset($newRequestId)) {
                    // Формируем расширенный комментарий с информацией о работах
                    $commentText = $request->input('comment', 'Создана новая заявка на недоделанные работы');

                    if (! empty($workParameters) && is_array($workParameters)) {
                        $worksInfoPart = '';
                        // Добавляем <br><br> только если $commentText уже что-то содержит
                        if (! empty($commentText)) {
                            $worksInfoPart .= '<br><br>';
                        }
                        $worksInfoPart .= 'Запланированные работы:';
                        foreach ($workParameters as $param) {
                            $typeName = $types[$param['parameter_type_id']] ?? 'Неизвестная работа';
                            $worksInfoPart .= "<br>- {$typeName}: {$param['quantity']}";
                        }
                        $commentText .= $worksInfoPart;
                    }

                    // Создаем комментарий
                    $newCommentId = DB::table('comments')->insertGetId([
                        'comment' => $commentText,
                        'created_at' => now(),
                    ]);

                    // Связываем комментарий с заявкой
                    DB::table('request_comments')->insert([
                        'request_id' => $newRequestId,
                        'comment_id' => $newCommentId,
                        'user_id' => Auth::id(), // ID пользователя из аутентификации
                        'created_at' => now(),
                    ]);

                    $response['new_request_id'] = $newRequestId;
                    $response['new_request_number'] = $requestNumber;
                }

                // Перед возвратом ответа
                \Log::info('Все выходные данные', [
                    'success' => $response['success'] ?? null,
                    'message' => $response['message'] ?? null,
                    'new_request_id' => $response['new_request_id'] ?? null,
                ]);
                \Log::info('=== END closeRequest ===', []);

                return response()->json($response);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить заявку',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Open a specified request.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function openRequest($id, Request $request)
    {
        try {
            $user = auth()->user();

            // Загружаем роли пользователя
            $sql = 'SELECT roles.name FROM user_roles
                JOIN roles ON user_roles.role_id = roles.id
                WHERE user_roles.user_id = '.$user->id;

            $roles = DB::select($sql);

            // Извлекаем только имена ролей из результатов запроса
            $roleNames = array_map(function ($role) {
                return $role->name;
            }, $roles);

            if (! in_array('admin', $roleNames)) {
                return response()->json([
                    'success' => false,
                    'message' => 'У вас нет прав для выполнения этого действия',
                ], 403);
            }

            \Log::info('=== START openRequest ===', []);
            \Log::info('ID заявки', ['id' => $id]);

            $request_to_open = DB::table('requests')->where('id', $id)->first();

            if (! $request_to_open) {
                return response()->json(['success' => false, 'message' => 'Заявка не найдена'], 404);
            }

            // Check if the request was created today
            $request_date = Carbon::parse($request_to_open->request_date)->toDateString();
            $today = Carbon::now()->toDateString();

            if ($request_date !== $today) {
                return response()->json(['success' => false, 'message' => 'Открыть можно только заявку, созданную сегодня'], 403);
            }

            // Check if the request status is 'completed' (status_id = 4)
            if ($request_to_open->status_id != 4) {
                return response()->json(['success' => false, 'message' => 'Открыть можно только выполненную заявку'], 403);
            }

            DB::beginTransaction();

            // Update request status to 'new' (status_id = 1)
            $updated = DB::table('requests')
                ->where('id', $id)
                ->update(['status_id' => 1]);

            if ($updated) {
                // Create a system comment
                $commentId = DB::table('comments')->insertGetId([
                    'comment' => 'Заявка была повторно открыта',
                    'created_at' => now(),
                ]);

                // Link the comment to the request
                DB::table('request_comments')->insert([
                    'request_id' => $id,
                    'comment_id' => $commentId,
                    'user_id' => $user->id,
                    'created_at' => now(),
                ]);
            }

            DB::commit();

            \Log::info('=== END openRequest ===', []);

            return response()->json([
                'success' => true,
                'message' => 'Заявка успешно открыта',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('=== ERROR openRequest ===', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при открытии заявки: '.$e->getMessage(),
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
            \Log::error('Error getting request types: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении типов заявок',
                'error' => $e->getMessage(),
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
            \Log::error('Error getting request statuses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статусов заявок',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of requests with optional date filter
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRequests(Request $request)
    {
        $date = $request->query('date');

        if ($date) {
            return $this->getRequestsByDate($date);
        }

        // If no date, return all requests (or default behavior)
        // For now, return empty or implement logic for all requests
        return response()->json([
            'data' => [],
            'message' => 'Параметр date обязателен для фильтрации заявок',
        ], 400);
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
            \Log::error('Error getting brigades: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка бригад',
                'error' => $e->getMessage(),
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
            \Log::error('Error getting operators: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка операторов',
                'error' => $e->getMessage(),
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
            // \Log::info('Получение списка городов из базы данных');

            // Получаем только необходимые поля
            $cities = DB::select('SELECT id, name FROM cities ORDER BY name');

            // Преобразуем объекты в массивы для корректной сериализации в JSON
            $cities = array_map(function ($city) {
                return [
                    'id' => $city->id,
                    'name' => $city->name,
                ];
            }, $cities);

            // \Log::info('Найдено городов: ' . count($cities));
            // \Log::info('Пример данных: ' . json_encode(array_slice($cities, 0, 3), JSON_UNESCAPED_UNICODE));

            return response()->json($cities);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении списка городов: '.$e->getMessage());
            \Log::error('Трассировка: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка городов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comments count for a request
     *
     * @param  int  $requestId
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
     * @param  int  $id  ID комментария
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateComment($id, Request $request)
    {
        $user = Auth::user();
        $content = $request->input('content');

        \Log::info('Получен запрос на обновление комментария:', [
            'comment_id' => $id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        DB::beginTransaction();
        \Log::info('Transaction started.');

        try {
            // Получаем комментарий и информацию о его авторе
            $commentQuery = DB::table('comments as c')
                ->join('request_comments as rc', 'c.id', '=', 'rc.comment_id')
                ->select('c.id', 'c.comment', 'c.created_at', 'rc.user_id')
                ->where('c.id', $id);

            $comment = $commentQuery->first();
            \Log::info('Comment fetched:', (array) $comment);

            if (! $comment) {
                DB::rollBack();
                \Log::warning('Comment not found, transaction rolled back.');

                return response()->json(['success' => false, 'message' => 'Комментарий не найден'], 404);
            }

            // Проверяем права доступа
            $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
            $isAdmin = DB::table('user_roles')->where('user_id', $user->id)->where('role_id', $adminRoleId)->exists();
            $isAuthor = ($comment->user_id == $user->id);
            $isToday = Carbon::parse($comment->created_at)->isToday();

            \Log::info('Permission check:', ['isAdmin' => $isAdmin, 'isAuthor' => $isAuthor, 'isToday' => $isToday]);

            if (! ($isAdmin || ($isAuthor && $isToday))) {
                DB::rollBack();
                \Log::warning('Permission denied, transaction rolled back.');

                return response()->json(['success' => false, 'message' => 'У вас нет прав на обновление этого комментария'], 403);
            }

            \Log::info('About to insert into comment_edits.');
            // Сохраняем старую версию комментария
            DB::table('comment_edits')->insert([
                'comment_id' => $comment->id,
                'old_comment' => $comment->comment ?? '', // Use empty string if null
                'edited_by_user_id' => $user->id,
                'edited_at' => now(),
            ]);
            \Log::info('Insert into comment_edits executed.');

            \Log::info('About to update comments table.');
            // Обновляем комментарий с помощью сырого SQL-запроса, чтобы избежать автоматического добавления 'updated_at'
            DB::update('UPDATE comments SET comment = ? WHERE id = ?', [$content, $id]);
            \Log::info('Update of comments table executed.');

            \Log::info('About to commit transaction.');
            DB::commit();
            \Log::info('Transaction committed.');

            return response()->json([
                'success' => true,
                'message' => 'Комментарий успешно обновлен!',
                'comment' => DB::table('comments')->where('id', $id)->first(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при обновлении комментария, transaction rolled back:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении комментария: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeRequest(Request $request)
    {
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
            \Log::info('=== START storeRequest ===');
            \Log::info('Все входные данные:', $request->all());

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
                'address_id' => $input['address_id'] ?? null,
                'work_parameters' => $input['work_parameters'] ?? null,
            ];

            // Используем ранее найденный employeeId или null
            $validationData['operator_id'] = $employeeId;

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
            \Log::info('Получены параметры работы:', $validated['work_parameters'] ?? []);

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
            // $client = null;
            $clientId = null;

            // Поиск клиента по телефону, ФИО или организации
            // $query = DB::table('clients');
            // $foundClient = false;

            // if (! empty($clientData['fio'])) {
            //     if ($foundClient) {
            //         $query->orWhere('fio', $clientData['fio']);
            //     } else {
            //         $query->where('fio', $clientData['fio']);
            //         $foundClient = true;
            //     }
            // } elseif (! empty($clientData['phone'])) {
            //     $query->where('phone', $clientData['phone']);
            //     $foundClient = true;
            // } elseif (! empty($clientData['organization'])) {
            //     if ($foundClient) {
            //         $query->orWhere('organization', $clientData['organization']);
            //     } else {
            //         $query->where('organization', $clientData['organization']);
            //         $foundClient = true;
            //     }
            // }

            // Выполняем запрос только если хотя бы одно поле заполнено
            // $client = $foundClient ? $query->first() : null;

            // $response = [
            //     'success' => true,
            //     'message' => 'Тестирование',
            //     'data' => [$client]
            // ];

            // return response()->json($response);

            // 4. Создание или обновление клиента
            try {
                // if ($client) {
                //     // Обновляем существующего клиента
                //     DB::table('clients')
                //         ->where('id', $client->id)
                //         ->update([
                //             'fio' => $clientData['fio'],
                //             'phone' => $clientData['phone'],
                //             'email' => $clientData['email'],
                //             'organization' => $clientData['organization'],
                //         ]);
                //     $clientId = $client->id;
                //     $clientState = 'updated';
                //     // \Log::info('Обновлен существующий клиент:', ['id' => $clientId]);
                // } else {
                    // Создаем нового клиента (даже если все поля пустые)
                    $clientId = DB::table('clients')->insertGetId([
                        'fio' => $clientData['fio'],
                        'phone' => $clientData['phone'],
                        'email' => $clientData['email'],
                        'organization' => $clientData['organization'],
                    ]);
                    $clientState = 'created';
                    // \Log::info('Создан новый клиент:', ['id' => $clientId]);
                // }
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
                    $validated['execution_date'],
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

            // 4. Создаем комментарий
            $commentText = trim($validated['comment'] ?? '');
            $workParams = $validated['work_parameters'] ?? [];

            if (! empty($workParams) && is_array($workParams)) {
                $typeIds = array_column($workParams, 'parameter_type_id');
                $types = DB::table('work_parameter_types')->whereIn('id', $typeIds)->pluck('name', 'id');

                $worksInfoPart = '';
                // Добавляем <br><br> только если $commentText уже что-то содержит
                if (! empty($commentText)) {
                    $worksInfoPart .= '<br><br>';
                }
                $worksInfoPart .= 'Запланированные работы:';
                foreach ($workParams as $param) {
                    $typeName = $types[$param['parameter_type_id']] ?? 'Неизвестная работа';
                    $worksInfoPart .= "<br>- {$typeName}: {$param['quantity']}";
                }
                $commentText .= $worksInfoPart;
            }

            $newCommentId = null;

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

            // 6. Создаем параметры работы (опционально)
            if (! empty($validated['work_parameters'])) {
                try {
                    foreach ($validated['work_parameters'] as $param) {
                        DB::table('work_parameters')->insert([
                            'parameter_type_id' => $param['parameter_type_id'],
                            'quantity' => $param['quantity'],
                            'request_id' => $requestId,
                            'is_planning' => true,
                            'is_done' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    \Log::info('Созданы параметры работы для заявки:', [
                        'request_id' => $requestId,
                        'count' => count($validated['work_parameters']),
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Ошибка при создании параметров работы: '.$e->getMessage());
                    throw $e;
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
                    addr.latitude,
                    addr.longitude,
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

            // Получаем данные типа заявки для логирования
            $requestTypeData = DB::selectOne(
                'SELECT rt.name AS request_type_name, rt.color AS request_type_color FROM request_types rt WHERE rt.id = ?',
                [$requestById->request_type_id]
            );

            \Log::info('Request type data', [
                'name' => $requestTypeData->request_type_name ?? null,
                'color' => $requestTypeData->request_type_color ?? null,
            ]);

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
                    'request_type_name' => $requestTypeData->request_type_name ?? null,
                    'request_type_color' => $requestTypeData->request_type_color ?? null,
                ],
            ];

            // Фиксируем изменения, если все успешно
            DB::commit();

            // Логируем основные данные о заявке
            \Log::info('Создана новая заявка:', [
                'request' => [
                    'id' => $requestId,
                    'number' => $requestNumber,
                    'type_id' => $validated['request_type_id'],
                    'status_id' => $validated['status_id'],
                    'execution_date' => $validated['execution_date'],
                    'is_admin' => $user->isAdmin,
                ],
                'client' => $clientId ? [
                    'id' => $clientId,
                    'is_new' => ! $isExistingClient,
                ] : 'Без привязки к клиенту',
                'address_id' => $address->id ?? null,
                'comment_id' => $newCommentId ?? null,
                'request_type_name' => $requestTypeData->request_type_name ?? null,
                'request_type_color' => $requestTypeData->request_type_color ?? null,
            ]);

            \Log::info('=== END storeRequest ===');

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

    public function getRequestByEmployee()
    {
        try {
            $employeeId = auth()->user()->employee_id;

            $requests = DB::select("SELECT * FROM requests WHERE operator_id = {$employeeId}");

            return response()->json([
                'success' => true,
                'message' => 'Заявки успешно получены',
                'data' => $requests,
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении заявок:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении заявок: '.$e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    public function uploadPhotoComment(Request $request)
    {
        try {

            // Для тестирования
            // return response()->json([
            //     'success' => true,
            //     'message' => 'Фотографии успешно загружены (test)',
            //     '$request' => $request
            // ], 200);

            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'photo_ids' => 'required|json', // Ожидаем JSON-строку с массивом ID
                'comment' => 'required|integer|exists:comments,id',
            ]);

            // Декодируем JSON с ID фотографий
            $photoIds = json_decode($validated['photo_ids'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный формат ID фотографий',
                ], 422);
            }

            $commentId = $validated['comment'];
            $requestId = $validated['request_id'];
            $now = now();

            // Начинаем транзакцию
            DB::beginTransaction();

            try {
                // Связываем каждую фотографию с комментарием
                foreach ($photoIds as $photoId) {
                    DB::table('comment_photos')->insert([
                        'comment_id' => $commentId,
                        'photo_id' => $photoId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                // Фиксируем изменения
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Фотографии успешно привязаны к комментарию',
                    'commentId' => $commentId,
                    'photoIds' => $photoIds,
                ], 200);

            } catch (\Exception $e) {
                // В случае ошибки откатываем транзакцию
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при привязке фотографий к комментарию',
                    'error' => $e->getMessage(),
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при загрузке фотографий:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке фотографий: '.$e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    /**
     * Загружает фотоотчет для заявки
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPhotoReport(Request $request)
    {
        try {
            // Валидация входящих данных
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:requests,id',
                'photos' => 'required|array|min:1',
                'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // до 10MB
                'comment' => 'nullable|string|max:1000',
            ]);

            $requestId = $validated['request_id'];
            $comment = $validated['comment'] ?? null;
            $userId = auth()->id();
            $now = now();

            // Дополнительная проверка наличия файлов (на случай если PHP отбросил файлы из-за ограничений)
            if (! $request->hasFile('photos')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'photos' => ['Не загружены файлы фотоотчета'],
                ]);
            }

            // Начинаем транзакцию
            DB::beginTransaction();

            // Создаем комментарий, если он есть
            $commentId = null;
            // if ($comment) {
            //     $commentId = DB::table('comments')->insertGetId([
            //         'comment' => $comment,
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]);

            //     // Связываем комментарий с заявкой
            //     DB::table('request_comments')->insert([
            //         'request_id' => $requestId,
            //         'comment_id' => $commentId,
            //         'user_id' => $userId,
            //         'created_at' => $now,
            //         'updated_at' => $now,
            //     ]);
            // }

            // Обрабатываем загруженные фотографии
            $uploadedPhotos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    // Сохраняем файл на диске "public" (storage/app/public/images)
                    \Log::info('Попытка сохранить файл', [
                        'original_name' => $photo->getClientOriginalName(),
                        'size' => $photo->getSize(),
                        'mime' => $photo->getMimeType(),
                        'disk' => 'public',
                        'storage_path' => storage_path('app/public/images'),
                    ]);

                    // Убеждаемся, что каталог существует на диске public
                    if (! \Storage::disk('public')->exists('images')) {
                        \Storage::disk('public')->makeDirectory('images');
                    }
                    // Готовим имя файла: берем оригинальное, нормализуем и обеспечиваем уникальность
                    $originalName = $photo->getClientOriginalName();
                    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $baseSlug = Str::slug($baseName, '_');
                    if ($baseSlug === '') {
                        $baseSlug = 'file';
                    }
                    $ext = strtolower($extension ?: ($photo->getClientOriginalExtension() ?: 'jpg'));

                    $finalName = $baseSlug.'.'.$ext;
                    $relativePath = 'images/'.$finalName;
                    $counter = 1;
                    while (\Storage::disk('public')->exists($relativePath)) {
                        $finalName = $baseSlug.'_'.$counter.'.'.$ext;
                        $relativePath = 'images/'.$finalName;
                        $counter++;
                    }

                    // Сохраняем с заданным именем
                    $stored = $photo->storeAs('images', $finalName, 'public');
                    if ($stored === false) {
                        throw new \RuntimeException('Не удалось сохранить файл на диске public. Проверьте права на каталог: '.storage_path('app/public/images'));
                    }
                    // Подтверждаем факт наличия на диске
                    if (! \Storage::disk('public')->exists($relativePath)) {
                        throw new \RuntimeException('Файл отсутствует на диске после сохранения: '.$relativePath);
                    }
                    \Log::info('Файл сохранен', [
                        'relative_path' => $relativePath,
                        'exists_public' => \Storage::disk('public')->exists($relativePath),
                    ]);

                    // Получаем метаданные файла
                    $fileSize = $photo->getSize();
                    $mimeType = $photo->getMimeType();

                    \Log::info('Получаем размеры изображения');
                    [$width, $height] = getimagesize($photo->getRealPath());
                    \Log::info('Размеры изображения', ['width' => $width, 'height' => $height]);

                    // Сохраняем информацию о фото в базу данных
                    $photoId = DB::table('photos')->insertGetId([
                        // Сохраняем относительный путь на диске public: images/...
                        'path' => $relativePath,
                        'original_name' => $originalName,
                        'file_size' => $fileSize,
                        'mime_type' => $mimeType,
                        'width' => $width,
                        'height' => $height,
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Связываем фото с заявкой
                    DB::table('request_photos')->insert([
                        'request_id' => $requestId,
                        'photo_id' => $photoId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $uploadedPhotos[] = [
                        'id' => $photoId,
                        'url' => \Storage::disk('public')->url($relativePath),
                        'path' => $relativePath,
                    ];
                }
            }

            // Фиксируем изменения
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Фотоотчет успешно загружен',
                'data' => [
                    'photos' => $uploadedPhotos,
                    'comment' => $comment ? [
                        'id' => $commentId,
                        'text' => $comment,
                    ] : null,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при загрузке фотоотчета:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке фотоотчета: '.$e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    public function getPhotoReport(Request $request)
    {
        try {
            // Поддерживаем оба варианта: GET /api/photo-report/{requestId} и POST c полем request_id
            $requestId = $request->route('requestId') ?? $request->input('request_id');

            if (! $requestId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не передан идентификатор заявки',
                ], 400);
            }

            // Загружаем фото через связующую таблицу request_photos -> photos
            $rows = DB::table('request_photos as rp')
                ->join('photos as p', 'rp.photo_id', '=', 'p.id')
                ->where('rp.request_id', $requestId)
                ->orderByDesc('p.created_at')
                ->select([
                    'p.id',
                    'p.path',
                    'p.original_name',
                    'p.file_size',
                    'p.mime_type',
                    'p.width',
                    'p.height',
                    'p.created_at',
                    'p.updated_at',
                ])
                ->get();

            // Строим публичный URL. Если path в public/storage, используем Storage::url
            $photos = $rows->map(function ($row) {
                try {
                    $url = \Storage::url($row->path);
                } catch (\Throwable $e) {
                    // Фолбэк: если уже абсолютный путь в /storage или /uploads
                    $url = $row->path;
                }

                return [
                    'id' => $row->id,
                    'url' => $url,
                    'original_name' => $row->original_name,
                    'file_size' => $row->file_size,
                    'mime_type' => $row->mime_type,
                    'width' => $row->width,
                    'height' => $row->height,
                    'created_at' => $row->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Фотоотчет успешно получен',
                'data' => $photos,
            ]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении фотоотчета:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении фотоотчета: '.$e->getMessage(),
                'error' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], 500);
        }
    }

    public function getCommentHistory($commentId)
    {
        try {
            $history = DB::table('comment_edits as ce')
                ->join('users as u', 'ce.edited_by_user_id', '=', 'u.id')
                ->leftJoin('employees as e', 'u.id', '=', 'e.user_id')
                ->where('ce.comment_id', $commentId)
                ->select('ce.old_comment', 'ce.edited_at', 'u.name as user_name', 'e.fio as employee_fio')
                ->orderBy('ce.edited_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $history]);
        } catch (\Exception $e) {
            \Log::error('Ошибка при получении истории правок комментария: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении истории правок',
            ], 500);
        }
    }

    /**
     * Get work parameters for a specific request.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWorkParameters($id)
    {
        try {
            $workParameters = DB::table('work_parameters')
                ->where('request_id', $id)
                ->where('is_planning', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $workParameters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении параметров работ: '.$e->getMessage(),
            ], 500);
        }
    }
}
