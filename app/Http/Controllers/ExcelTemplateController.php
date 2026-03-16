<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelTemplateController extends Controller
{
    public function downloadTemplate(Request $request)
    {
        $requestTypeId = $request->query('request_type_id');
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Основные заголовки
        $headers = [
            'ГБОУ',
            'Адрес организации',
            'Контакт',
            'Комментарии к монтажу',
        ];

        // Добавляем дополнительные колонки, если выбран тип заявки
        if ($requestTypeId) {
            $workParameterTypes = DB::table('work_parameter_types')
                ->where('request_type_id', $requestTypeId)
                ->where('is_deleted', false)
                ->pluck('name')
                ->toArray();
                
            $headers = array_merge($headers, $workParameterTypes);
        }

        // Заполняем заголовки
        $columnIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueExplicitByColumnAndRow($columnIndex, 1, $header, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
            $columnIndex++;
        }

        // Выделяем жирным заголовки
        $lastColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);

        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $fileName = 'Шаблон_заявок';
        if ($requestTypeId) {
            $typeName = DB::table('request_types')->where('id', $requestTypeId)->value('name');
            if ($typeName) {
                $fileName .= '_' . str_replace(' ', '_', $typeName);
            }
        }
        $fileName .= '.xlsx';

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . rawurlencode($fileName) . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
