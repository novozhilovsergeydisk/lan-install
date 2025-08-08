<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\BrigadeController;
use App\Http\Controllers\RequestFilterController;
use App\Http\Controllers\RequestTeamFilterController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\PlanningRequestController; 
use App\Http\Controllers\EmployeeUserController;
use App\Http\Controllers\EmployeesUserPositionPassportController;
use App\Http\Controllers\ReportController;

Route::get('/employees', [EmployeesUserPositionPassportController::class, 'index'])->name('employees.index');

Route::middleware(['auth', 'roles'])->group(function () {
    Route::get('/brigades/create', [BrigadeController::class, 'create'])->name('brigades.create');
    Route::post('/brigades', [BrigadeController::class, 'store'])->name('brigades.store');
});

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
Route::get('/', [HomeController::class, 'index'])->name('home')->middleware(['auth', 'roles']);

// Close request
Route::post('/requests/{request}/close', [HomeController::class, 'closeRequest'])->name('requests.close')->middleware('auth');

// Transfer request
Route::post('/api/requests/transfer', [HomeController::class, 'transferRequest'])->name('requests.transfer')->middleware('auth');

// Маршруты для работы с заявками
Route::post('/requests/cancel', [HomeController::class, 'cancelRequest'])->name('requests.cancel')->middleware('auth');

// Маршрут для загрузки фотоотчетов
Route::post('/api/requests/photo-report', [HomeController::class, 'uploadPhotoReport'])->name('requests.photo-report')->middleware('auth');

// Маршруты для работы со статусами заявок
Route::prefix('statuses')->middleware('auth')->group(function () {
    Route::get('/', [StatusController::class, 'index']);
    Route::post('/', [StatusController::class, 'store']);
    Route::put('/{id}', [StatusController::class, 'update']);
    Route::delete('/{id}', [StatusController::class, 'destroy']);
});

// Обработка комментариев
Route::post('/requests/comment', [HomeController::class, 'addComment'])
    ->middleware('auth')
    ->name('requests.comment');

// Получение комментариев к заявке
Route::get('/api/requests/{request}/comments', [HomeController::class, 'getComments'])
    ->middleware('auth')
    ->name('api.requests.comments');

// Обновление комментария
Route::put('/api/comments/{id}', [HomeController::class, 'updateComment'])
    ->middleware('auth')
    ->name('api.comments.update');

// API Route for getting brigade data
Route::post('/brigade/{id}', [\App\Http\Controllers\BrigadeController::class, 'getBrigadeData'])->name('brigade.data');

// API Route for getting current brigades
Route::get('/api/brigades/current', [HomeController::class, 'getCurrentBrigades'])
    ->middleware('auth')
    ->name('api.brigades.current');

// API Route for filtering requests by statuses
Route::get('/api/requests/by-status', [RequestFilterController::class, 'filterByStatuses'])
    ->middleware('auth')
    ->name('api.requests.filter-statuses');

// Работа с адресами
Route::prefix('api/addresses')->middleware('auth')->group(function () {
    Route::get('/paginated', [GeoController::class, 'getAddressesPaginated'])->name('api.addresses.paginated');
    Route::get('/{id}', [GeoController::class, 'getAddress'])->name('api.addresses.show');
    Route::post('/add', [GeoController::class, 'addAddress'])->name('address.add');
    Route::put('/{id}', [GeoController::class, 'updateAddress'])->name('api.addresses.update');
    Route::delete('/{id}', [GeoController::class, 'deleteAddress'])->name('api.addresses.delete');
});

// API Routes for request modification
Route::prefix('api/requests')->middleware('auth')->group(function () {
    // Получить бригаду по ID бригадира за текущую дату
    Route::get('/brigade/by-leader/{leaderId}', [\App\Http\Controllers\ControllerRequestModification::class, 'getBrigadeByLeader']);

    // Обновить бригаду у заявки
    Route::post('/update-brigade', [\App\Http\Controllers\ControllerRequestModification::class, 'updateRequestBrigade']);
});



// API Route for getting all statuses
Route::get('/api/request-statuses/all', [RequestFilterController::class, 'getStatuses'])
    ->middleware('auth')
    ->name('api.request-statuses.all');

Route::get('/api/requests/by-brigade', [RequestTeamFilterController::class, 'filterByTeams'])
    ->name('api.requests.filter-brigade')
    ->middleware('auth');

// Получение списка бригадиров
Route::get('/api/brigade-leaders', [RequestTeamFilterController::class, 'getBrigadeLeaders'])
    ->name('api.brigade-leaders')
    ->middleware('auth');

// Получение информации о бригадах за текущий день
Route::post('/api/brigades/info-current-day', [RequestTeamFilterController::class, 'brigadesInfoCurrentDay'])
    ->name('api.brigades.info-current-day')
    ->middleware('auth');

// User management routes
Route::prefix('api/users')->middleware('auth')->group(function () {
    // Update user credentials
    Route::put('/{id}/credentials', [HomeController::class, 'updateCredentials'])->name('api.users.update-credentials');
});

// Report Routes    
Route::prefix('reports')->middleware('auth')->group(function () {
    // Address and employee data
    Route::get('/addresses', [ReportController::class, 'getAddresses'])->name('reports.addresses');
    Route::get('/employees', [ReportController::class, 'getEmployees'])->name('reports.employees');
    
    // All period reports
    Route::post('requests/all-period', [ReportController::class, 'getAllPeriod'])->name('reports.requests.all-period');
    Route::post('requests/by-employee-all-period', [ReportController::class, 'getAllPeriodByEmployee'])->name('reports.requests.by-employee-all-period');
    Route::post('requests/by-address-all-period', [ReportController::class, 'getAllPeriodByAddress'])->name('reports.requests.by-address-all-period');
    Route::post('requests/by-employee-address-all-period', [ReportController::class, 'getAllPeriodByEmployeeAndAddress'])->name('reports.requests.by-employee-address-all-period');
    
    // Date range reports
    Route::post('requests/by-date', [ReportController::class, 'getRequestsByDateRange'])->name('reports.requests.by-date');
    Route::post('requests/by-employee-date', [ReportController::class, 'getRequestsByEmployeeAndDateRange'])->name('reports.requests.by-employee-date');
    Route::post('requests/by-address-date', [ReportController::class, 'getRequestsByAddressAndDateRange'])->name('reports.requests.by-address-date');
    Route::post('requests/by-employee-address-date', [ReportController::class, 'getRequestsByEmployeeAddressAndDateRange'])->name('reports.requests.by-employee-address-date');
});

// API Routes for request management
Route::prefix('api')->middleware('auth')->group(function () {
    // Get addresses for select
    Route::get('/addresses', [HomeController::class, 'getAddresses'])->name('api.addresses');
    
    // Get paginated addresses
    Route::get('/addresses/paginated', [HomeController::class, 'getAddressesPaginated'])->name('api.addresses.paginated');

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

//
Route::prefix('api/geo')->middleware('auth')->group(function () {
    Route::get('/addresses', [GeoController::class, 'getAddresses']);
    Route::get('/cities', [GeoController::class, 'getCities']);
    Route::get('/regions', [GeoController::class, 'getRegions']);

    Route::post('/addresses', [GeoController::class, 'addAddress']);
    Route::post('/cities', [GeoController::class, 'addCity']);
    Route::post('/regions', [GeoController::class, 'addRegion']);
});

// API для получения списка бригад
Route::get('/api/brigades/current-day', [BrigadeController::class, 'getCurrentDayBrigades'])
    ->name('api.brigades.current-day')
    ->middleware('auth');

// API для работы с сотрудниками
Route::get('/api/employees', [HomeController::class, 'getEmployees'])
    ->name('api.employees')
    ->middleware('auth');

Route::post('/employees/store', [EmployeeUserController::class, 'store'])
->name('employees.store')
->middleware('auth');

Route::post('/employee/update', [EmployeeUserController::class, 'update'])
->name('employee.update')
->middleware('auth');

Route::get('/employee/get', [EmployeeUserController::class, 'getEmployee'])
->name('employee.get')
->middleware('auth');

// API для получения списка ролей
Route::get('/api/roles', [EmployeeUserController::class, 'getRoles'])
->name('api.roles')
->middleware('auth');

Route::post('/employee/filter', [EmployeeUserController::class, 'filterEmployee'])
->name('employee.filter')
->middleware('auth');

Route::post('/employee/delete', [EmployeeUserController::class, 'deleteEmployee'])
->name('employee.delete')
->middleware('auth'); 

// Delete routes
// Delete brigade member
Route::post('/brigade/delete/{id}', [BrigadeController::class, 'deleteBrigade'])
->name('brigade.delete')
->middleware('auth');


Route::post('/planning-requests', [PlanningRequestController::class, 'store'])
->name('planning-requests.store')
->middleware('auth');

Route::post('/get-planning-requests', [PlanningRequestController::class, 'getPlanningRequests'])
->name('get-planning-requests')
->middleware('auth');

Route::post('/change-planning-request-status', [PlanningRequestController::class, 'changePlanningRequestStatus'])
->name('change-planning-request-status')
->middleware('auth');



