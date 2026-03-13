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
    protected $dynamicColumns = [];

    public function __construct(array $filters)
    {
        $this->filters = $filters;

        $requestTypeId = $this->filters['requestTypeId'] ?? null;
        if ($requestTypeId) {
            $this->dynamicColumns = DB::table('work_parameter_types')
                ->where('request_type_id', $requestTypeId)
                ->where('is_deleted', false)
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
        }
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
                          AND wpt2.is_deleted = false
                          AND (wp2.is_planning = false OR wp2.is_planning IS NULL)
                        ORDER BY wp2.parameter_type_id, wp2.id DESC
                    ) as t
                ) as actual_works,
                ( 
                    SELECT STRING_AGG(t.name || ':::' || t.quantity, '|||' ORDER BY t.name)
                    FROM ( 
                        SELECT DISTINCT ON (wp2.parameter_type_id) 
                               wpt2.name, wp2.quantity
                        FROM work_parameters wp2
                        JOIN work_parameter_types wpt2 ON wp2.parameter_type_id = wpt2.id
                        WHERE wp2.request_id = r.id 
                          AND wpt2.is_deleted = false
                          AND (wp2.is_planning = false OR wp2.is_planning IS NULL)
                        ORDER BY wp2.parameter_type_id, wp2.id DESC
                    ) as t
                ) as actual_works_raw,
                ( 
                    SELECT STRING_AGG('- ' || t.name || ': ' || t.quantity, E'\n' ORDER BY t.name)
                    FROM ( 
                        SELECT DISTINCT ON (wp2.parameter_type_id) 
                               wpt2.name, wp2.quantity
                        FROM work_parameters wp2
                        JOIN work_parameter_types wpt2 ON wp2.parameter_type_id = wpt2.id
                        WHERE wp2.request_id = r.id 
                          AND wpt2.is_deleted = false
                        ORDER BY wp2.parameter_type_id, wp2.id ASC
                    ) as t
                ) as planned_works,
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
        $headings = [
            'Дата и номер',
            'Адрес',
            'Бригада',
            'Комментарий',
            'Выполненные работы',
        ];

        if (!empty($this->dynamicColumns)) {
            $headings[4] = 'Запланированные работы';
            foreach ($this->dynamicColumns as $col) {
                $headings[] = $col;
            }
        }

        $headings[] = 'Фотоотчет';

        return $headings;
    }

    public function map($row): array
    {
        $photoLink = '';
        if ($row->has_attachments) {
            $url = route('photo-report.download', ['requestId' => $row->id]);
            $photoLink = '=HYPERLINK("' . $url . '", "Скачать файлы")';
        }

        if (!empty($this->dynamicColumns)) {
            $worksOutput = $row->planned_works 
                ? $row->planned_works 
                : '';
        } else {
            $worksOutput = $row->actual_works 
                ? "Выполненные работы:\n" . $row->actual_works 
                : '';
        }

        $dateAndNumber = ($row->execution_date ? \Carbon\Carbon::parse($row->execution_date)->format('d.m.Y') : 'Не указана') . 
                         "\n" . $row->number;

        $rowArray = [
            $dateAndNumber,
            $row->full_address,
            $row->brigade_name ? ($row->brigade_name . ($row->leader_name ? ' (' . $row->leader_name . ')' : '')) : 'Не назначена',
            strip_tags($row->first_comment),
            $worksOutput,
        ];

        if (!empty($this->dynamicColumns)) {
            $rawWorks = [];
            if (!empty($row->actual_works_raw)) {
                $items = explode('|||', $row->actual_works_raw);
                foreach ($items as $item) {
                    $parts = explode(':::', $item);
                    if (count($parts) === 2) {
                        $rawWorks[$parts[0]] = $parts[1];
                    }
                }
            }
            foreach ($this->dynamicColumns as $col) {
                $rowArray[] = $rawWorks[$col] ?? '';
            }
        }

        $rowArray[] = $photoLink;

        return $rowArray;
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
                
                $colIndex = 'F';
                if (!empty($this->dynamicColumns)) {
                    foreach ($this->dynamicColumns as $col) {
                        $sheet->getColumnDimension($colIndex)->setWidth(15);
                        $colIndex++;
                    }
                }
                
                $sheet->getColumnDimension($colIndex)->setWidth(40); // Фотоотчет
                
                $lastCol = $colIndex;

                // Включаем перенос текста
                $sheet->getStyle('A:' . $lastCol)->getAlignment()->setWrapText(true);
                $sheet->getStyle('A:' . $lastCol)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

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
                    $sheet->getStyle($colIndex . '2:' . $colIndex . $highestRow)->getFont()->getColor()->setARGB(Color::COLOR_BLUE);
                    $sheet->getStyle($colIndex . '2:' . $colIndex . $highestRow)->getFont()->setUnderline(Font::UNDERLINE_SINGLE);
                }
                
                // Жирный шрифт для заголовка
                $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
            },
        ];
    }
}
