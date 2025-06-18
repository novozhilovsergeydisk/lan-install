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
        $users = DB::select('SELECT * FROM users');

        // Запрашиваем clients
        $clients = DB::select('SELECT * FROM clients');

        // Запрашиваем brigades
        $brigades = DB::select('SELECT * FROM brigades');

        // Запрашиваем employees
        $employees = DB::select('SELECT * FROM employees');

        // Запрашиваем addresses
        $addresses = DB::select('SELECT * FROM addresses');

        // Запрашиваем brigade_members
        $brigade_members = DB::select('SELECT * FROM brigade_members');
        
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
        ");

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
            'requests_types'
        ));
    }
}
