<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Font;

class RequestsReportExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $filters;
    protected $rowsCount = 0;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;
        $employeeId = $this->filters['employeeId'] ?? null;
        $addressId = $this->filters['addressId'] ?? null;
        $allPeriod = isset($this->filters['allPeriod']) && ($this->filters['allPeriod'] === true || $this->filters['allPeriod'] === 'true' || $this->filters['allPeriod'] === 1);
        $organization = $this->filters['organization'] ?? null;
        $requestTypeId = $this->filters['requestTypeId'] ?? null;

        $query = DB::table('requests as r')
            ->selectRaw("r.id,
                r.number,
                r.execution_date,
                ( 
                    SELECT STRING_AGG( 
                        (CASE WHEN ct2.name IS NOT NULL AND ct2.name != 'Москва' THEN ct2.name || ', ' ELSE '' END) || 
                        'ул. ' || addr2.street || ', д. ' || addr2.houses, 
                        '; ' 
                    )
                    FROM request_addresses ra2
                    JOIN addresses addr2 ON ra2.address_id = addr2.id
                    LEFT JOIN cities ct2 ON addr2.city_id = ct2.id
                    WHERE ra2.request_id = r.id
                ) as full_address,
                b.name as brigade_name,
                e_leader.fio as leader_name,
                ( 
                    SELECT com.comment 
                    FROM request_comments rc 
                    JOIN comments com ON rc.comment_id = com.id 
                    WHERE rc.request_id = r.id 
                    ORDER BY com.created_at ASC 
                    LIMIT 1
                ) as first_comment,
                ( 
                    SELECT STRING_AGG('- ' || t.name || ': ' || t.quantity, E'\n' ORDER BY t.name)
                    FROM ( 
                        SELECT DISTINCT ON (wp2.parameter_type_id) 
                               wpt2.name, wp2.quantity
                        FROM work_parameters wp2
                        JOIN work_parameter_types wpt2 ON wp2.parameter_type_id = wpt2.id
                        WHERE wp2.request_id = r.id 
                          AND (wp2.is_planning = false OR wp2.is_planning IS NULL)
                        ORDER BY wp2.parameter_type_id, wp2.id DESC
                    ) as t
                ) as actual_works,
                -- Проверка наличия вложений (фото или файлов)
                EXISTS ( 
                    SELECT 1 FROM request_comments rc
                    LEFT JOIN comment_photos cp ON rc.comment_id = cp.comment_id
                    LEFT JOIN comment_files cf ON rc.comment_id = cf.comment_id
                    WHERE rc.request_id = r.id AND (cp.id IS NOT NULL OR cf.id IS NOT NULL)
                ) as has_attachments
            ")
            ->leftJoin('clients as c', 'r.client_id', '=', 'c.id')
            ->leftJoin('brigades as b', 'r.brigade_id', '=', 'b.id')
            ->leftJoin('employees as e_leader', 'b.leader_id', '=', 'e_leader.id')
            ->where(function($q) {
                $q->where('b.is_deleted', false)->orWhereNull('b.id');
            });

        // Фильтры
        if (!$allPeriod && $startDate && $endDate) {
            $query->whereBetween(DB::raw('r.execution_date::date'), [$startDate, $endDate]);
        }
        if ($employeeId) {
            $query->where(function($q) use ($employeeId) {
                $q->whereExists(function($sub) use ($employeeId) {
                    $sub->select(DB::raw(1))
                        ->from('brigade_members as bm')
                        ->whereColumn('bm.brigade_id', 'b.id')
                        ->where('bm.employee_id', $employeeId);
                })->orWhere('b.leader_id', $employeeId);
            });
        }
        if ($addressId) {
            $query->whereExists(function($q) use ($addressId) {
                $q->select(DB::raw(1))
                    ->from('request_addresses as ra_filter')
                    ->whereColumn('ra_filter.request_id', 'r.id')
                    ->where('ra_filter.address_id', $addressId);
            });
        }
        if ($organization) {
            $query->where('c.organization', $organization);
        }
        if ($requestTypeId) {
            $query->where('r.request_type_id', $requestTypeId);
        }

        $query->orderBy('r.execution_date', 'DESC')->orderBy('r.id', 'DESC');

        $data = $query->get();
        $this->rowsCount = $data->count();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Дата и номер',
            'Адрес',
            'Бригада',
            'Комментарий',
            'Выполненные работы',
            'Фотоотчет',
        ];
    }

    public function map($row): array
    {
        $photoLink = '';
        if ($row->has_attachments) {
            $url = route('photo-report.download', ['requestId' => $row->id]);
            $photoLink = '=HYPERLINK("' . $url . '", "Скачать файлы")';
        }

        $actualWorksOutput = $row->actual_works 
            ? "Выполненные работы:\n" . $row->actual_works 
            : 'Нет данных по выполненным работам';

        $dateAndNumber = ($row->execution_date ? \Carbon\Carbon::parse($row->execution_date)->format('d.m.Y') : 'Не указана') . 
                         "\n" . $row->number;

        return [
            $dateAndNumber,
            $row->full_address,
            $row->brigade_name ? ($row->brigade_name . ($row->leader_name ? ' (' . $row->leader_name . ')' : '')) : 'Не назначена',
            strip_tags($row->first_comment),
            $actualWorksOutput,
            $photoLink,
        ];
    }
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                
                // Задаем ширину колонок вручную
                $sheet->getColumnDimension('A')->setWidth(20);
                $sheet->getColumnDimension('B')->setWidth(50);
                $sheet->getColumnDimension('C')->setWidth(50);
                $sheet->getColumnDimension('D')->setWidth(80);
                $sheet->getColumnDimension('E')->setWidth(50);
                $sheet->getColumnDimension('F')->setWidth(40);

                // Включаем перенос текста
                $sheet->getStyle('A:F')->getAlignment()->setWrapText(true);
                $sheet->getStyle('A:F')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                // Принудительное удаление пустых строк, если они есть
                $totalExpectedRows = $this->rowsCount + 1; // +1 заголовок
                $highestRow = $sheet->getHighestRow();
                
                if ($highestRow > $totalExpectedRows) {
                    // Удаляем лишние строки
                    $sheet->getDelegate()->removeRow($totalExpectedRows + 1, $highestRow - $totalExpectedRows);
                }

                // Пересчитываем highestRow после удаления (хотя для стилей можно использовать known count)
                $highestRow = $totalExpectedRows;

                if ($highestRow > 1) {
                    $sheet->getStyle('F2:F' . $highestRow)->getFont()->getColor()->setARGB(Color::COLOR_BLUE);
                    $sheet->getStyle('F2:F' . $highestRow)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                }
                
                // Жирный шрифт для заголовка
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
            },
        ];
    }
}
