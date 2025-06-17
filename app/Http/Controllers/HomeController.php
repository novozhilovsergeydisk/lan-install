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

        // 🔽 Все данные теперь запрашиваем из основной БД
        $migrations = DB::select('SELECT * FROM migrations');
        $clients = DB::select('SELECT * FROM clients');
        $requestStatuses = DB::select('SELECT * FROM request_statuses');

//dump($requestStatuses);

	return view('welcome', compact('user', 'migrations', 'clients', 'requestStatuses'));

        /*return view('welcome', [
            'user' => $user,
            'migrations' => $migrations,
            'clients' => $clients,
            'requestStatuses' => $requestStatuses
        ]);*/
    }
}
