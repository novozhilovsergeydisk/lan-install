<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeesUserPositionPassportController extends Controller
{
    public function index()
    {
        try {
            // Получаем текущего пользователя
            $user = Auth::user();

            // Определяем, какие поля показывать в зависимости от роли
            $isAdmin = $user->isAdmin ?? false;
            $isFitter = $user->isFitter ?? false;
            $isUser = $user->isUser ?? false;

            if ($isAdmin) {
                // Администраторы видят все поля
                $selectFields = "
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
                    COALESCE(c.license_plate, 'Не указан') as car_plate,
                    u.email as user_email
                ";
            } elseif ($isFitter || $isUser) {
                // Fitter и User видят только ФИО, телефон и автомобиль
                $selectFields = "
                    e.fio,
                    e.phone,
                    '' as birth_date,
                    '' as birth_place,
                    '' as series_number,
                    '' as passport_issued_at,
                    '' as passport_issued_by,
                    '' as department_code,
                    '' as position,
                    COALESCE(c.brand, 'Не указана') as car_brand,
                    COALESCE(c.license_plate, 'Не указан') as car_plate,
                    '' as user_email
                ";
            } else {
                // Для других ролей - минимальный набор
                $selectFields = "
                    e.fio,
                    e.phone,
                    COALESCE(c.brand, 'Не указана') as car_brand,
                    COALESCE(c.license_plate, 'Не указан') as car_plate
                ";
            }

            // Получаем данные о сотрудниках
            $employees = DB::select("
            SELECT
                {$selectFields}
            FROM employees e
            LEFT JOIN passports p ON e.id = p.employee_id
            LEFT JOIN positions pos ON e.position_id = pos.id
            LEFT JOIN cars c ON e.id = c.employee_id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.is_deleted = false OR e.is_deleted IS NULL
            ORDER BY e.fio
        ");

            // Преобразуем null значения в пустые строки для корректного отображения
            $employees = array_map(function ($employee) {
                return (object) array_map(function ($value) {
                    return $value ?? '';
                }, (array) $employee);
            }, $employees);

            return view('welcome', compact('employees'));
        } catch (\Exception $e) {
            Log::error('Error in EmployeesUserPositionPassportController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении данных о сотрудниках',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
