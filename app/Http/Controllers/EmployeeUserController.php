<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeUserController extends Controller
{
    /**
     * Получает данные сотрудника по ID
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployee(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID сотрудника не указан'
                ], 400);
            }

            // Получаем данные сотрудника
            $employee = DB::table('employees')
                ->select(
                    'employees.id as employee_id',
                    'employees.fio',
                    'employees.phone',
                    'employees.birth_date',
                    'employees.birth_place',
                    'employees.registration_place',
                    'employees.position_id',
                    'positions.name as position_name',
                    // Паспортные данные
                    'passports.series_number',
                    'passports.issued_by',
                    'passports.issued_at',
                    'passports.department_code',
                    // Данные автомобиля
                    'cars.brand',
                    'cars.license_plate',
                    'cars.registered_at'
                )
                ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                ->leftJoin('passports', 'employees.id', '=', 'passports.employee_id')
                ->leftJoin('cars', 'employees.id', '=', 'cars.employee_id')
                ->where('employees.id', $employeeId)
                ->where('employees.is_deleted', false)
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сотрудник не найден'
                ], 404);
            }

            // Преобразуем данные паспорта и автомобиля в отдельные объекты для сохранения совместимости с фронтендом
            $passport = null;
            if ($employee->series_number) {
                $passport = (object)[
                    'series_number' => $employee->series_number,
                    'issued_by' => $employee->issued_by,
                    'issued_at' => $employee->issued_at,
                    'department_code' => $employee->department_code
                ];
            }

            $car = null;
            if ($employee->brand) {
                $car = (object)[
                    'brand' => $employee->brand,
                    'license_plate' => $employee->license_plate,
                    'registered_at' => $employee->registered_at
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => $employee,
                    'passport' => $passport,
                    'car' => $car
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка при получении данных сотрудника: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных сотрудника',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Валидация
        $validated = $request->validate([
            // Поля пользователя (обязательные на фронтенде)
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|same:password',

            // Поля сотрудника (обязательные на фронтенде)
            'fio' => 'required|string|max:255',
            'position_id' => 'required|exists:positions,id',
            'role_id' => 'required|exists:roles,id',

            // Необязательные поля
            'phone' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'registration_place' => 'nullable|string|max:255',
            'passport_series' => 'nullable|string|max:20',
            'passport_issued_by' => 'nullable|string|max:255',
            'passport_issued_at' => 'nullable|date',
            'passport_department_code' => 'nullable|string|max:20',
            'car_brand' => 'nullable|string|max:100',
            'car_plate' => 'nullable|string|max:20',
        ], [
            'name.required' => 'Поле "Имя пользователя" обязательно для заполнения',
            'email.required' => 'Поле "Email" обязательно для заполнения',
            'email.email' => 'Укажите корректный email',
            'email.unique' => 'Пользователь с таким email уже существует',
            'password.required' => 'Поле "Пароль" обязательно для заполнения',
            'password.min' => 'Пароль должен содержать минимум 8 символов',
            'password.confirmed' => 'Пароли не совпадают',
            'password_confirmation.required_with' => 'Подтверждение пароля обязательно',
            'password_confirmation.same' => 'Пароли не совпадают',
            'fio.required' => 'Поле "ФИО" обязательно для заполнения',
            'position_id.required' => 'Поле "Должность" обязательно для выбора',
            'position_id.exists' => 'Выбранная должность недействительна',
            'role_id.required' => 'Поле "Роль" обязательно для выбора',
            'role_id.exists' => 'Выбранная роль недействительна',
        ]);

        DB::beginTransaction();

        try {
            // Создаем пользователя
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]);

            // Вставка сотрудника с user_id и position_id
            DB::insert("
                INSERT INTO employees (fio, phone, birth_date, birth_place, user_id, position_id, registration_place)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $request->fio,
                $request->phone,
                $request->birth_date,
                $request->birth_place,
                $user->id,
                $request->position_id,
                $request->registration_place
            ]);

            $employeeId = DB::getPdo()->lastInsertId();

            //insert into user_roles

            DB::select(
                'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)',
                [
                    $user->id,
                    $request->role_id
                ]
            );  

            // Паспорт (если заполнен)
            if ($request->passport_series) {
                DB::insert("
                    INSERT INTO passports (employee_id, series_number, issued_by, issued_at, department_code)
                    VALUES (?, ?, ?, ?, ?)
                ", [
                    $employeeId,
                    $request->passport_series,
                    $request->passport_issued_by,
                    $request->passport_issued_at,
                    $request->passport_department_code
                ]);
            }

            // Машина (если заполнена)
            if ($request->car_brand && $request->car_plate) {
                DB::insert("
                    INSERT INTO cars (employee_id, brand, license_plate)
                    VALUES (?, ?, ?)
                ", [
                    $employeeId,
                    $request->car_brand,
                    $request->car_plate
                ]);
            }

            DB::commit();

            if ($request->wantsJson()) {
                // Получаем полные данные о сотруднике
                $employee = DB::table('employees')
                    ->select(
                        'employees.id',
                        'employees.fio',
                        'employees.phone',
                        'employees.birth_date',
                        'employees.birth_place',
                        'employees.registration_place',
                        'positions.name as position'
                    )
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.id')
                    ->where('employees.id', $employeeId)
                    ->first();

                // Получаем паспортные данные
                $passport = DB::table('passports')
                    ->where('employee_id', $employeeId)
                    ->first();

                // Получаем данные об автомобиле
                $car = DB::table('cars')
                    ->where('employee_id', $employeeId)
                    ->first();

                return response()->json([
                    'message' => 'Сотрудник успешно создан',
                    'employee_id' => $employeeId,
                    'user_id' => $user->id,
                    'fio' => $employee->fio,
                    'phone' => $employee->phone,
                    'birth_date' => $employee->birth_date,
                    'birth_place' => $employee->birth_place,
                    'registration_place' => $employee->registration_place,
                    'position' => $employee->position,
                    'passport' => $passport ? [
                        'series_number' => $passport->series_number,
                        'issued_by' => $passport->issued_by,
                        'issued_at' => $passport->issued_at,
                        'department_code' => $passport->department_code
                    ] : null,
                    'car' => $car ? [
                        'brand' => $car->brand,
                        'license_plate' => $car->license_plate
                    ] : null
                ], 201);
            }

            return redirect()->back()->with('success', 'Сотрудник успешно добавлен');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Ошибка при создании сотрудника',
                    'error' => $e->getMessage(),
                    'errors' => ['general' => $e->getMessage()]
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        // Получаем ID сотрудника из формы
        $employee_id = $request->input('employee_id');

        // Валидация
        $validated = $request->validate([
            'fio' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'position_id' => 'required|exists:positions,id',
            'registration_place' => 'nullable|string|max:255',
            'passport_series' => 'nullable|string|max:20',
            'passport_issued_by' => 'nullable|string|max:255',
            'passport_issued_at' => 'nullable|date',
            'passport_department_code' => 'nullable|string|max:20',
            'car_brand' => 'nullable|string|max:100',
            'car_plate' => 'nullable|string|max:20',
        ], [
            'fio.required' => 'Поле "ФИО" обязательно для заполнения',
            'position_id.required' => 'Поле "Должность" обязательно для выбора',
            'position_id.exists' => 'Выбранная должность недействительна',
        ]);

        try {
            DB::beginTransaction();

            // Продолжаем выполнение обновления данных

            // Обновление данных сотрудника
            DB::update(
                "UPDATE employees SET fio = ?, phone = ?, birth_date = ?, birth_place = ?, registration_place = ?, position_id = ? WHERE id = ?",
                [
                    $request->fio,
                    $request->phone,
                    $request->birth_date,
                    $request->birth_place,
                    $request->registration_place,
                    $request->position_id,
                    $employee_id
                ]
            );

            // ID сотрудника уже получен из формы
            $employeeId = $employee_id;

            // Проверяем, что сотрудник существует
            $employee = DB::selectOne("SELECT * FROM employees WHERE (is_deleted IS NULL OR is_deleted = false) AND id = ?", [$employeeId]);

            if (!$employee) {
                throw new \Exception('Сотрудник не найден');
            }

            // Обновление или создание паспорта
            // Проверяем наличие обязательного поля series_number
            if ($request->passport_series) {
                $passportData = [
                    'series_number' => $request->passport_series,
                    'issued_by' => $request->passport_issued_by,
                    'issued_at' => $request->passport_issued_at,
                    'department_code' => $request->passport_department_code,
                    'updated_at' => now()
                ];

                $passportExists = DB::selectOne(
                    "SELECT id FROM passports WHERE employee_id = ? LIMIT 1",
                    [$employeeId]
                );

                if ($passportExists) {
                    DB::update(
                        "UPDATE passports SET
                            series_number = ?,
                            issued_by = ?,
                            issued_at = ?,
                            department_code = ?,
                            updated_at = ?
                        WHERE employee_id = ?",
                        [
                            $request->passport_series,
                            $request->passport_issued_by,
                            $request->passport_issued_at,
                            $request->passport_department_code,
                            now(),
                            $employeeId
                        ]
                    );
                } else {
                    DB::insert(
                        "INSERT INTO passports
                            (employee_id, series_number, issued_by, issued_at, department_code, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $employeeId,
                            $request->passport_series,
                            $request->passport_issued_by,
                            $request->passport_issued_at,
                            $request->passport_department_code,
                            now(),
                            now()
                        ]
                    );
                }
            }

            // Обновление или создание данных об автомобиле
            if ($request->car_brand || $request->car_plate || $request->car_registered_at) {
                $carExists = DB::selectOne(
                    "SELECT id FROM cars WHERE employee_id = ? LIMIT 1",
                    [$employeeId]
                );

                if ($carExists) {
                    DB::update(
                        "UPDATE cars SET
                            brand = ?,
                            license_plate = ?,
                            registered_at = ?
                        WHERE employee_id = ?",
                        [
                            $request->car_brand,
                            $request->car_plate,
                            $request->car_registered_at,
                            $employeeId
                        ]
                    );
                } else {
                    DB::insert(
                        "INSERT INTO cars
                            (employee_id, brand, license_plate, registered_at)
                        VALUES (?, ?, ?, ?)",
                        [
                            $employeeId,
                            $request->car_brand,
                            $request->car_plate,
                            $request->car_registered_at
                        ]
                    );
                }
            }

            DB::commit();

            if ($request->wantsJson()) {
                // Получаем данные паспорта и автомобиля для ответа
                $passport = DB::selectOne("SELECT * FROM passports WHERE employee_id = ?", [$employeeId]);
                $car = DB::selectOne("SELECT * FROM cars WHERE employee_id = ?", [$employeeId]);

                return response()->json([
                    'success' => true,
                    'message' => 'Данные сотрудника успешно обновлены.',
                    'user_id' => $request->user_id,
                    'data' => [
                        'employee' => $employee,
                        'passport' => $passport,
                        'car' => $car,
                    ],
                ], 200);
            }

            return redirect()->back()->with('success', 'Данные сотрудника успешно обновлены!');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при обновлении данных: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при обновлении данных: ' . $e->getMessage())
                ->withInput();
        }
    }
}

