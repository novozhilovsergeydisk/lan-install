<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AddUserRolesToView
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        \Log::info('AddUserRolesToView: Проверка аутентификации');
        
        if (Auth::check()) {
            $user = $request->user();
            \Log::info('AddUserRolesToView: Пользователь аутентифицирован', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            if (!isset($user->employee)) {
                // \Log::info('AddUserRolesToView: Связанный сотрудник не найден');
                
                $employee = DB::table('employees')
                    ->where('user_id', $user->id)
                    ->first();
                
                if ($employee) {
                    $user->employee = $employee;
                    // \Log::info('AddUserRolesToView: Поиск связанного сотрудника завершен');
                } else {
                    // \Log::warning('AddUserRolesToView: Пользователь не имеет связанного сотрудника');
                }
            }
            
            // Загружаем роли, если они еще не загружены
            if (!isset($user->roles)) {
                // \Log::info('AddUserRolesToView: Роли не загружены, загружаем из базы');
                
                $roles = DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('user_roles.user_id', $user->id)
                    ->pluck('roles.name')
                    ->toArray();
                
                // \Log::info('AddUserRolesToView: Загружены роли из базы', [
                //     'user_id' => $user->id,
                //     'roles' => $roles
                // ]);
                
                // Устанавливаем роли и флаги
                $user->roles = $roles;
                $user->isAdmin = in_array('admin', $roles);
                $user->isUser = in_array('user', $roles);
                $user->isFitter = in_array('fitter', $roles);
                $user->employee = $employee;
                $user->test = 'proxima';
                
                // \Log::info('AddUserRolesToView: Установлены флаги ролей', [
                //     'user_id' => $user->id,
                //     'isAdmin' => $user->isAdmin,
                //     'isUser' => $user->isUser,
                //     'isFitter' => $user->isFitter
                // ]);
            } else {
                // \Log::info('AddUserRolesToView: Роли уже загружены', [
                //     'user_id' => $user->id,
                //     'roles' => $user->roles
                // ]);
            }
            
            // Делаем пользователя доступным во всех представлениях
            view()->share('user', $user);
            // \Log::info('AddUserRolesToView: Пользователь добавлен в шаблоны');
        } else {
            \Log::info('AddUserRolesToView: Пользователь не аутентифицирован');
        }

        $response = $next($request);
        
        // Проверяем, что пользователь есть в ответе (для API)
        if ($request->wantsJson()) {
            $data = $response->getData(true);
            if (isset($data['user'])) {
                $data['user']['roles'] = Auth::user()->roles ?? null;
                $data['user']['isAdmin'] = Auth::user()->isAdmin ?? false;
                $response->setData($data);
            }
        }
        
        return $response;
    }
}
