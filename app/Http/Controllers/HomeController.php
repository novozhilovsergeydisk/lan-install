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

        // Запрашиваем все миграции (для теста)
        $migrations = DB::select('SELECT * FROM migrations');

        // Запрашиваем всех клиентов
        $clients = DB::select('SELECT * FROM clients');

        // Запрашиваем статусы заявок
        $requestStatuses = DB::select('SELECT * FROM request_statuses');

        // 🔽 Исправленный комплексный запрос с подключением к employees
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
//dd($requests);
        // Передаём всё в шаблон
        return view('welcome', compact(
            'user',
            'migrations',
            'clients',
            'requestStatuses',
            'requests'
        ));
    }
}
