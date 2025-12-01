<?php

use App\Http\Controllers\AddressDocumentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BrigadeController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CommentPhotoController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeesFilterController;
use App\Http\Controllers\EmployeesUserPositionPassportController;
use App\Http\Controllers\EmployeeUserController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PhotoReportController;
use App\Http\Controllers\PlanningRequestController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestFilterController;
use App\Http\Controllers\RequestTeamFilterController;
use Illuminate\Support\Facades\Route;

Route::get('/test-no-auth', function () {
    Log::info('Тест в начале маршрута без аутентификации');

    return 'OK';
});

Route::get('/testlog', function () {
    Log::emergency('Test emergency from route /testlog');
    file_put_contents('/var/www/lan-install/storage/logs/test_web.log', 'web log '.now().PHP_EOL, FILE_APPEND);

    return 'OK';
});

// Update request
Route::match(['PUT'], '/requests/{id}', [HomeController::class, 'updateRequest'])->where('id', '[0-9]+')->name('requests.update')->middleware('auth')->middleware(['auth', 'roles']);

Route::get('/brigades/date/{date}', [BrigadeController::class, 'getBrigadesByDate'])->name('brigades.date');

// Города добавление
Route::post('/cities/store', [CityController::class, 'store'])
    ->middleware('auth')
    ->name('cities.store');

// Получение саписка регионов
Route::get('/regions', [CityController::class, 'getRegions'])->name('regions.index');

// Photo reports
Route::post('/photo-list', [PhotoReportController::class, 'index'])->name('photo-list.index')->middleware('auth');

// Яндекс.Карты
Route::get('/yandex-maps', [GeoController::class, 'getAddressesYandex'])->name('geo.addresses-yandex')->middleware('auth');

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

// Получение данных заявки для редактирования
Route::get('/requests/{id}', [HomeController::class, 'getEditRequest'])->where('id', '[0-9]+')->name('requests.getEditRequest')->middleware('auth');

// Главная страница
Route::get('/', [HomeController::class, 'index'])->name('home')->middleware(['auth', 'roles']);

// Close request
Route::post('/requests/{request}/close', [HomeController::class, 'closeRequest'])->name('requests.close')->middleware('auth')->middleware(['auth', 'roles']);

// Open request
Route::post('/requests/{request}/open', [HomeController::class, 'openRequest'])->name('requests.open')->middleware('auth')->middleware(['auth', 'roles']);

// Завершить заявку
Route::post('/requests/{request}/delete', [HomeController::class, 'deleteRequest'])->name('requests.delete')->middleware('auth')->middleware(['auth', 'roles']);

// Transfer request
Route::post('/api/requests/transfer', [HomeController::class, 'transferRequest'])->name('requests.transfer')->middleware('auth');

// Маршруты для работы с заявками
Route::post('/requests/cancel', [HomeController::class, 'cancelRequest'])->name('requests.cancel')->middleware('auth');

// Маршрут для загрузки фотоотчетов
Route::post('/api/requests/photo-report', [HomeController::class, 'uploadPhotoReport'])->name('requests.photo-report')->middleware('auth');

Route::post('/api/requests/photo-comment', [HomeController::class, 'uploadPhotoComment'])->name('requests.photo-comment')->middleware('auth');

// Фотоотчет: GET (получение списка фото по заявке)
Route::get('/api/photo-report/{requestId}', [HomeController::class, 'getPhotoReport'])->name('photo-report.show')->middleware('auth');

// Фотоотчет: POST (получение фото по id комментария)
Route::post('/api/comments/{commentId}/photos', [CommentPhotoController::class, 'index'])->name('api.comments.photos')->middleware('auth');

// Маршруты для работы со статусами заявок
Route::prefix('statuses')->middleware('auth')->group(function () {
    // Route::get('/', [StatusController::class, 'index']);
    // Route::post('/', [StatusController::class, 'store']);
    // Route::put('/{id}', [StatusController::class, 'update']);
    // Route::delete('/{id}', [StatusController::class, 'destroy']);
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

// Работа с документами адресов
Route::prefix('api/address-documents')->middleware('auth')->group(function () {
    Route::post('/', [AddressDocumentController::class, 'store'])->name('api.address-documents.store');
    Route::get('/address/{addressId}', [AddressDocumentController::class, 'getByAddress'])->name('api.address-documents.getByAddress');
    Route::get('/download/{id}', [AddressDocumentController::class, 'download'])->name('api.address-documents.download');
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

    // Show address reports page
    Route::get('/address/{addressId}', [ReportController::class, 'showAddressReports'])->name('reports.address.show');

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
    // Get comment photos
    Route::get('/comments/{commentId}/photos', [\App\Http\Controllers\CommentPhotoController::class, 'index'])->name('api.comments.photos');

    // Test log route
    Route::get('/test-log', function () {
        \Log::info('TEST message from route');

        return 'Logged!';
    })->name('test.log');

    // Get comment files
    Route::get('/comments/{commentId}/files', [\App\Http\Controllers\CommentPhotoController::class, 'getCommentFiles'])->name('api.comments.files');

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

    Route::get('/yandex', [GeoController::class, 'getAddressesYandex']);

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

// Фильтр сотрудников по дате (POST)
Route::post('/api/employees/filter', [EmployeesFilterController::class, 'filterByDate'])
    ->name('api.employees.filter')
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

Route::post('/employee/export', [EmployeeUserController::class, 'exportEmployees'])
    ->name('employee.export')
    ->middleware('auth');

Route::post('/employee/restore', [HomeController::class, 'restoreEmployee'])
    ->name('employee.restore')
    ->middleware(['auth', 'roles']);

Route::post('/employee/delete-permanently', [HomeController::class, 'deleteEmployeePermanently'])
    ->name('employee.delete-permanently')
    ->middleware(['auth', 'roles']);

// Delete routes
// Delete brigade member
Route::post('/brigade/delete/{id}', [BrigadeController::class, 'deleteBrigade'])
    ->name('brigade.delete')
    ->middleware('auth');

Route::post('/planning-requests', [PlanningRequestController::class, 'store'])
    ->name('planning-requests.store')
    ->middleware('auth');

Route::post('/planning-requests/upload-excel', [PlanningRequestController::class, 'uploadRequestsExcel'])
    ->name('planning-requests.upload-excel')
    ->middleware('auth');

Route::post('/get-planning-requests', [PlanningRequestController::class, 'getPlanningRequests'])
    ->name('get-planning-requests')
    ->middleware('auth');

Route::post('/change-planning-request-status', [PlanningRequestController::class, 'changePlanningRequestStatus'])
    ->name('change-planning-request-status')
    ->middleware('auth');

Route::post('/download-all-photos', [CommentPhotoController::class, 'downloadAllPhotos'])
    ->name('download-all-photos')
    ->middleware('auth');

// Загрузка Excel файлов
Route::post('/upload-excel', [CommentPhotoController::class, 'uploadExcel'])
    ->name('api.upload-excel')
    ->middleware('auth');

Route::get('/test-log', function () {
    Log::info('=== TEST LOG FROM ROUTE ===');
    Log::debug('Debug level test');
    Log::error('Error level test');

    return response()->json([
        'log_channel' => config('logging.default'),
        'log_level' => config('logging.channels.single.level'),
        'log_path' => config('logging.channels.single.path'),
        'log_file_exists' => file_exists(storage_path('logs/laravel.log')),
        'log_file_writable' => is_writable(storage_path('logs/laravel.log')),
    ]);
});
Route::get('/api/comments/{commentId}/history', [App\Http\Controllers\HomeController::class, 'getCommentHistory'])->name('api.comments.history');

// Загрузка документов сотрудников
Route::post('/api/employee-documents', [EmployeeDocumentController::class, 'store'])->middleware('auth')->name('employee-documents.store');
Route::get('/api/employee-documents/employee/{employeeId}', [EmployeeDocumentController::class, 'getByEmployee'])->middleware('auth')->name('employee-documents.getByEmployee');
Route::get('/api/employee-documents/{id}/download', [EmployeeDocumentController::class, 'download'])->middleware('auth')->name('employee-documents.download');
