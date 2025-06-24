<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index()
    {
        // Получаем текущего пользователя
        $user = Auth::user();

        // Запрашиваем users
        //$users = DB::query('start transaction');
        $users = DB::select('SELECT * FROM users');
        //$users = DB::query('commit');

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
            "SELECT 
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
            LEFT JOIN employees e ON bm.employee_id = e.id"
        );

        // $brigadeMembersWithDetails = collect($brigadeMembersWithDetails);
            
        // Выводим содержимое для отладки
        // dd($brigadeMembersWithDetails);
            
        $brigade_members = DB::select('SELECT * FROM brigade_members'); // Оставляем старый запрос для обратной совместимости
        
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
                    return (object)[
                        'id' => $comment->comment_id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at,
                        'author_name' => $comment->author_name
                    ];
                })->toArray();
            });

            // dd($commentsByRequest);
            
        // Преобразуем коллекцию в массив для передачи в представление
        $comments_by_request = $commentsByRequest->toArray();
        
        // Запрашиваем request_addresses
        $request_addresses = DB::select('SELECT * FROM request_addresses'); 
        
        // Запрашиваем request_statuses
        $request_statuses = DB::select('SELECT * FROM request_statuses'); 

        // Запрашиваем request_types
        $requests_types = DB::select('SELECT * FROM request_types'); 

        // 🔽 Комплексный запрос с подключением к employees
        $requests = DB::select("
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
            ORDER BY r.request_date DESC
        ");

        $flags = [
            'new' => 'new',
            'in_work' => 'in_work',
            'waiting_for_client' => 'waiting_for_client',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'on_hold' => 'on_hold',
            'under_review' => 'under_review',
            'on_hold' => 'on_hold',
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
            // Логируем входящие данные
            \Log::info('Данные запроса:', $request->all());
            
            $validated = $request->validate([
                'request_id' => 'required|exists:requests,id',
                'comment' => 'required|string|max:1000'
            ]);

            DB::beginTransaction();

            // Создаем комментарий
            $commentData = [
                'comment' => $validated['comment'],
                'created_at' => now()
            ];
            \Log::info('Данные для вставки в comments:', $commentData);
            
            $commentId = DB::table('comments')->insertGetId($commentData);
            \Log::info('Создан комментарий с ID: ' . $commentId);

            // Привязываем комментарий к заявке
            $requestCommentData = [
                'request_id' => $validated['request_id'],
                'comment_id' => $commentId,
                'created_at' => now()
            ];
            \Log::info('Данные для вставки в request_comments:', $requestCommentData);
            
            DB::table('request_comments')->insert($requestCommentData);

            DB::commit();

            // Получаем обновленный список комментариев
            $comments = DB::table('request_comments')
                ->join('comments', 'request_comments.comment_id', '=', 'comments.id')
                ->where('request_comments.request_id', $validated['request_id'])
                ->orderBy('comments.created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'comments' => $comments
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Ошибка при добавлении комментария:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при добавлении комментария',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
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
                ->update(['status_id' => 4]); // 4 is the ID for 'выполнена'

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
            $cities = array_map(function($city) {
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
     * Store a new request
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeRequest(Request $request)
    {
        DB::beginTransaction();
        
        try {
            // Validate the request
            $validated = $request->validate([
                'client.fio' => 'required|string|max:255',
                'client.phone' => 'required|string|max:20',
                'request.request_type_id' => 'required|exists:request_types,id',
                'request.status_id' => 'required|exists:request_statuses,id',
                'request.comment' => 'nullable|string',
                'request.execution_date' => 'required|date',
                'request.execution_time' => 'nullable|date_format:H:i',
                'request.brigade_id' => 'required|exists:brigades,id',
                'request.operator_id' => 'required|exists:employees,id',
                'addresses' => 'required|array|min:1',
                'addresses.*.city_id' => 'required|exists:cities,id',
                'addresses.*.street' => 'required|string|max:255',
                'addresses.*.comment' => 'nullable|string'
            ]);
            
            // 1. Create or find client
            $client = DB::table('clients')
                ->where('phone', $validated['client']['phone'])
                ->first();
                
            if (!$client) {
                $clientId = DB::table('clients')->insertGetId([
                    'fio' => $validated['client']['fio'],
                    'phone' => $validated['client']['phone'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                $clientId = $client->id;
                // Update client name if it has changed
                if ($client->fio !== $validated['client']['fio']) {
                    DB::table('clients')
                        ->where('id', $clientId)
                        ->update([
                            'fio' => $validated['client']['fio'],
                            'updated_at' => now()
                        ]);
                }
            }
            
            // 2. Create request
            $requestData = [
                'number' => 'REQ-' . time(),
                'client_id' => $clientId,
                'request_type_id' => $validated['request']['request_type_id'],
                'status_id' => $validated['request']['status_id'],
                'comment' => $validated['request']['comment'] ?? null,
                'execution_date' => $validated['request']['execution_date'],
                'execution_time' => $validated['request']['execution_time'] ?? null,
                'brigade_id' => $validated['request']['brigade_id'],
                'operator_id' => $validated['request']['operator_id'],
                'request_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            $requestId = DB::table('requests')->insertGetId($requestData);
            
            // 3. Process addresses
            foreach ($validated['addresses'] as $address) {
                // Find or create address
                $addressId = DB::table('addresses')->insertGetId([
                    'city_id' => $address['city_id'],
                    'street' => $address['street'],
                    'comment' => $address['comment'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Link address to request
                DB::table('request_addresses')->insert([
                    'request_id' => $requestId,
                    'address_id' => $addressId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // 4. Add system comment
            $commentId = DB::table('comments')->insertGetId([
                'comment' => 'Заявка создана',
                'created_at' => now()
            ]);
            
            DB::table('request_comments')->insert([
                'request_id' => $requestId,
                'comment_id' => $commentId,
                'created_at' => now()
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Заявка успешно создана',
                'request_id' => $requestId
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
