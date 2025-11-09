<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        \Log::emergency('== EMERGENCY START login ==');
        \Log::info('== START login ==', [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        \Log::info('Validation passed', [
            'login' => $request->input('login'),
            'user_agent' => $request->userAgent(),
        ]);

        $login = $request->input('login');
        $password = $request->input('password');

        // Определяем поле для аутентификации (email или имя пользователя)
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        \Log::info('Attempting authentication', [
            'field_type' => $fieldType,
            'login' => $login,
            'user_agent' => $request->userAgent(),
        ]);

        if (Auth::attempt([$fieldType => $login, 'password' => $password], $request->filled('remember'))) {
            \Log::info('Authentication successful', [
                'user_id' => Auth::id(),
                'user_agent' => $request->userAgent(),
            ]);

            // Получаем текущего пользователя
            $user = Auth::user();

            $migrations = DB::select('SELECT * FROM migrations');
            $clients = DB::connection('pgsql_fursa')->select('SELECT * FROM clients');
            // dump($migrations);
            // dd($clients);
            // $migrations = DB::table('migrations')->get();

            // Сохраняем данные в сессии
            /*session()->put('user_data', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'user',
                'created_at' => $user->created_at->toFormattedDateString(),
            ]);*/

            //        session()->put('migrations', $migrations);
            //        session()->put('clients', $clients);

            // dd($migrations);
            // Передаём данные в сессию
            return redirect()
                ->intended('/')
                ->with('success', 'Вы успешно вошли!');
            /*->with('user_data', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'user',
                'created_at' => $user->created_at->toFormattedDateString(),
            ])
        ->with('migrations', $migrations)
        ->with('clients', $clients);*/ // Передача миграций
        }

        // Логируем попытку входа
        \Log::warning('Неудачная попытка входа', [
            'login' => $request->input('login'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Возвращаемся на страницу входа с ошибкой
        return back()
            ->withInput($request->only('login', 'remember'))
            ->with('error', 'Неверное имя пользователя или пароль. Пожалуйста, попробуйте еще раз.');
    }
}
