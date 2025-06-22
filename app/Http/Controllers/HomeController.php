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
            ORDER BY rc.request_id, c.created_at DESC
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
                e.fio AS brigade_lead
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
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
        $validated = $request->validate([
            'request_id' => 'required|exists:requests,id',
            'comment' => 'required|string|max:1000'
        ]);

        DB::transaction(function () use ($validated) {
            // Создаем комментарий
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $validated['comment'],
                'created_at' => now(),
                'user_id' => Auth::id()
            ]);

            // Привязываем комментарий к заявке
            DB::table('request_comments')->insert([
                'request_id' => $validated['request_id'],
                'comment_id' => $commentId,
                'created_at' => now()
            ]);
        });

        return response()->json(['success' => true]);
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
                'Система' as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            WHERE rc.request_id = ?
            ORDER BY c.created_at DESC
        ", [$requestId]);
        
        return response()->json($comments);
    }
}
