<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class EmployeesExport implements FromCollection, WithHeadings, WithColumnWidths
{
    protected $employees;

    public function __construct(Collection $employees)
    {
        $this->employees = $employees;
    }

    public function collection()
    {
        return $this->employees;
    }

    public function headings(): array
    {
        return [
            'ФИО',
            'Дата рождения',
            'Место рождения',
            'Паспорт (серия номер, кем выдан, когда)',
            'Адрес регистрации',
            'Номер авто'
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // ФИО
            'B' => 15, // Дата рождения
            'C' => 25, // Место рождения
            'D' => 50, // Паспорт
            'E' => 40, // Адрес регистрации
            'F' => 20, // Номер авто
        ];
    }
}