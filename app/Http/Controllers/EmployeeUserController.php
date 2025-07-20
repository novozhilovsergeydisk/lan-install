<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeUserController extends Controller
{
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
        // Получаем ID пользователя из запроса или используем текущего пользователя
        $user_id = auth()->id();
        
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

        // Для тестирования
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'debug' => true,
                'debug_message' => 'Данные сотрудника успешно обновлены',
                'entry-data' => $request->all(),
                'user_id' => auth()->id(),
            ], 200);
        }

        try {
            DB::beginTransaction();

            // Обновление данных сотрудника
            DB::update(
                "UPDATE employees SET fio = ?, phone = ?, birth_date = ?, birth_place = ?, updated_at = ? WHERE user_id = ?",
                [
                    $request->fio,
                    $request->phone,
                    $request->birth_date,
                    $request->birth_place,
                    now(),
                    $user_id
                ]
            );

            // Получаем ID сотрудника
            $employee = DB::selectOne("SELECT * FROM employees WHERE is_deleted = false and user_id = ?", [$user_id]);

            if (!$employee) {
                throw new \Exception('Сотрудник не найден');
            }

            $employeeId = $employee->id;

            // Обновление или создание паспорта
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
            if ($request->car_brand && $request->car_plate) {
                $carExists = DB::selectOne(
                    "SELECT id FROM cars WHERE employee_id = ? LIMIT 1",
                    [$employeeId]
                );

                if ($carExists) {
                    DB::update(
                        "UPDATE cars SET 
                            brand = ?, 
                            license_plate = ?, 
                            updated_at = ? 
                        WHERE employee_id = ?",
                        [
                            $request->car_brand,
                            $request->car_plate,
                            now(),
                            $employeeId
                        ]
                    );
                } else {
                    DB::insert(
                        "INSERT INTO cars 
                            (employee_id, brand, license_plate, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?)",
                        [
                            $employeeId,
                            $request->car_brand,
                            $request->car_plate,
                            now(),
                            now()
                        ]
                    );
                }
            }

            DB::commit();
            
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Данные сотрудника успешно обновлены.',
                    'user_id' => $request->user_id,
                    'data' => [
                        'employee' => $employee,
                        'user' => $user,
                        'passport' => $passport,
                        'car' => $car,
                    ],
                    'errors' => $request->errors(),
                ], 200);
            }
                  
            return redirect()->back()->with('success', 'Данные сотрудника успешно обновлены!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Ошибка при обновлении данных: ' . $e->getMessage())
                ->withInput();
        }
    }
}

