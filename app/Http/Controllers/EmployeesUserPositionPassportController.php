<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class EmployeesUserPositionPassportController extends Controller
{
    public function index()
    {
        // Получаем данные о сотрудниках с паспортными данными и должностями
        $employees = DB::select("
            SELECT 
                e.fio,
                e.phone,
                CASE WHEN e.birth_date IS NOT NULL 
                     THEN TO_CHAR(e.birth_date, 'DD.MM.YYYY') 
                     ELSE NULL 
                END as birth_date,
                e.birth_place,
                p.series_number,
                CASE WHEN p.issued_at IS NOT NULL 
                     THEN TO_CHAR(p.issued_at, 'DD.MM.YYYY') 
                     ELSE NULL 
                END as passport_issued_at,
                p.issued_by as passport_issued_by,
                p.department_code,
                COALESCE(pos.name, 'Не указана') as position,
                COALESCE(c.brand, 'Не указана') as car_brand,
                COALESCE(c.license_plate, 'Не указан') as car_plate
            FROM employees e
            LEFT JOIN passports p ON e.id = p.employee_id
            LEFT JOIN positions pos ON e.position_id = pos.id
            LEFT JOIN cars c ON e.id = c.employee_id
            ORDER BY e.fio
        ");

        // Преобразуем null значения в пустые строки для корректного отображения
        $employees = array_map(function($employee) {
            return (object)array_map(function($value) {
                return $value ?? '';
            }, (array)$employee);
        }, $employees);

        return view('welcome', compact('employees'));
    }
}
