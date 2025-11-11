<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        try {
            return view('auth.register');
        } catch (\Exception $e) {
            Log::error('Error in Auth\RegisterController@showRegistrationForm: '.$e->getMessage());

            return response('Произошла ошибка при загрузке страницы регистрации', 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Auth::login($user);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Пользователь успешно зарегистрирован',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'created_at' => $user->created_at->format('d.m.Y H:i'),
                    ],
                ]);
            }

            return back()->with('success', 'Вы успешно зарегистрировались и вошли в систему!');
        } catch (\Exception $e) {
            Log::error('Error in Auth\RegisterController@register: '.$e->getMessage());
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Произошла ошибка при регистрации',
                    'error' => $e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Произошла ошибка при регистрации. Пожалуйста, попробуйте еще раз.');
        }
    }
}
