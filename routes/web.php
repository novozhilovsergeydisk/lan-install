<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;

// Форма входа
/*Route::get('/login', function () {
    return 'Маршрут /login работает!';
});*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::post('/logout', function () {
    Auth::logout();
    return redirect('/login');
})->name('logout')->middleware('auth');

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// home

// Главная страница
Route::get('/', [HomeController::class, 'index'])->name('home')->middleware('auth');

/*Route::get('/', function () {
    return view('welcome');
})->middleware('auth');*/

