<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeUserController extends Controller
{
    public function store(Request $request)
    {
        // Валидация
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'fio' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',

            'passport_series' => 'nullable|string|max:20',
            'passport_issued_by' => 'nullable|string|max:255',
            'passport_issued_at' => 'nullable|date',
            'passport_department_code' => 'nullable|string|max:20',

            'car_brand' => 'nullable|string|max:100',
            'car_plate' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();

        try {
            // Вставка сотрудника с user_id
            DB::insert("
                INSERT INTO employees (fio, phone, birth_date, birth_place, user_id)
                VALUES (?, ?, ?, ?, ?)
            ", [
                $request->fio,
                $request->phone,
                $request->birth_date,
                $request->birth_place,
                $request->user_id
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
            return redirect()->back()->with('success', 'Сотрудник успешно добавлен');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }
}

