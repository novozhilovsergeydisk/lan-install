<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\BrigadeController;
use App\Http\Controllers\RequestFilterController;
use App\Http\Controllers\RequestTeamFilterController;

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

// Close request
Route::post('/requests/{request}/close', [HomeController::class, 'closeRequest'])->name('requests.close')->middleware('auth');

// Обработка комментариев
Route::post('/requests/comment', [HomeController::class, 'addComment'])
    ->middleware('auth')
    ->name('requests.comment');

// Получение комментариев к заявке
Route::get('/api/requests/{request}/comments', [HomeController::class, 'getComments'])
    ->middleware('auth')
    ->name('api.requests.comments');

// API Route for getting brigade data
Route::post('/brigade/{id}', [\App\Http\Controllers\BrigadeController::class, 'getBrigadeData'])->name('brigade.data');

// API Route for filtering requests by statuses
Route::get('/api/requests/by-status', [RequestFilterController::class, 'filterByStatuses'])
    ->middleware('auth')
    ->name('api.requests.filter-statuses');

// API Route for getting all statuses
Route::get('/api/request-statuses/all', [RequestFilterController::class, 'getStatuses'])
    ->middleware('auth')
    ->name('api.request-statuses.all');

Route::get('/api/requests/by-brigade', [RequestTeamFilterController::class, 'filterByTeams'])
    ->name('api.requests.filter-brigade')
    ->middleware('auth');

// API Routes for request management
Route::prefix('api')->middleware('auth')->group(function () {
    // Get addresses for select
    Route::get('/addresses', [HomeController::class, 'getAddresses'])->name('api.addresses');
    
    // Get requests by date
    Route::get('/requests/date/{date}', [HomeController::class, 'getRequestsByDate'])->name('api.requests.by-date');
    
    // Get request types
    Route::get('/request-types', [HomeController::class, 'getRequestTypes'])->name('api.request-types');
    
    // Get request statuses
    Route::get('/request-statuses', [HomeController::class, 'getRequestStatuses'])->name('api.request-statuses');
    
    // Get brigades
    Route::get('/brigades', [BrigadeController::class, 'index'])->name('api.brigades');
    
    // Get operators
    Route::get('/operators', [HomeController::class, 'getOperators'])->name('api.operators');
    
    // Get cities
    Route::get('/cities', [HomeController::class, 'getCities'])->name('api.cities');
    
    // Create new request
    Route::post('/requests', [HomeController::class, 'storeRequest'])->name('api.requests.store');
    
    // Get comments count for request
    Route::get('/requests/{request}/comments/count', [HomeController::class, 'getCommentsCount'])
        ->name('api.requests.comments.count');
});


