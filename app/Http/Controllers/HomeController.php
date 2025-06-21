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
        
        // Запрашиваем comments
        $comments = DB::select('SELECT * FROM comments'); 
        
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
        return view('welcome', compact(
            'user',
            'users',
            'clients',
            'request_statuses',
            'requests',
            'brigades',
            'employees',
            'addresses',
            'brigade_members',
            'comments',
            'request_addresses',
            'requests_types',
            'brigadeMembersWithDetails',
            'flags'
        ));
    }
}
