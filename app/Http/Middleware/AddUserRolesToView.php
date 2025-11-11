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
        if (Auth::check()) {
            $user = $request->user();

            if (!isset($user->employee)) {
                $employee = DB::table('employees')
                    ->where('user_id', $user->id)
                    ->first();
                
                if ($employee) {
                    $user->employee = $employee;
                }
            }
            
            // Загружаем роли, если они еще не загружены
            if (!isset($user->roles)) {
                $roles = DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('user_roles.user_id', $user->id)
                    ->pluck('roles.name')
                    ->toArray();
                
                // Устанавливаем роли и флаги
                $user->roles = $roles;
                $user->isAdmin = in_array('admin', $roles);
                $user->isUser = in_array('user', $roles);
                $user->isFitter = in_array('fitter', $roles);
                $user->employee = $employee;
                $user->test = 'proxima';
            }
            
            // Делаем пользователя доступным во всех представлениях
            view()->share('user', $user);
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