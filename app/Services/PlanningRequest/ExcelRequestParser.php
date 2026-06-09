<?php

namespace App\Services\PlanningRequest;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Чтение и разбор Excel-файла заявок в нейтральную структуру (ParsedImport).
 *
 * Не пишет в БД. Используется и предосмотром, и реальной загрузкой, чтобы
 * разбор заголовков/строк/адресов был идентичным в обоих путях.
 */
class ExcelRequestParser
{
    /** Обязательные базовые колонки файла. */
    private const EXPECTED_HEADERS = [
        'гбоу',
        'адрес организации',
        'контакт',
        'комментарии к монтажу',
    ];

    /**
     * Разобрать файл по пути $filePath под выбранный тип заявки.
     *
     * @throws ImportValidationException при неверных заголовках/наборе колонок/пустом файле
     */
    public function parse(string $filePath, ?int $requestTypeId): ParsedImport
    {
        $workParameterTypes = $this->loadWorkParameterTypes($requestTypeId);

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
        } catch (ImportValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ImportValidationException(
                'Не удалось прочитать файл. Убедитесь, что это корректный файл Excel (.xlsx или .xls).'
            );
        }

        $data = array_filter($data, function ($row) {
            return ! empty(array_filter($row, fn ($value) => $value !== null && $value !== ''));
        });

        if (empty($data)) {
            throw new ImportValidationException('Файл не содержит данных');
        }

        $headers = array_shift($data);

        $normalizedHeaders = array_map(function ($h) {
            return trim(mb_strtolower((string) $h));
        }, $headers ?? []);

        $this->validateHeaders($headers ?? [], $normalizedHeaders, $workParameterTypes);

        $headerMap = array_flip($normalizedHeaders);

        // Колонки параметров работ: индекс колонки => ['id' => ..., 'name' => ...]
        $parameterColumns = [];
        if (! empty($workParameterTypes)) {
            foreach ($headers as $index => $originalHeader) {
                $trimmedHeader = trim((string) $originalHeader);
                if (isset($workParameterTypes[$trimmedHeader])) {
                    $parameterColumns[$index] = [
                        'id' => $workParameterTypes[$trimmedHeader]->id,
                        'name' => $trimmedHeader,
                    ];
                }
            }
        }

        $rows = [];
        $rowNumber = 0;
        foreach ($data as $row) {
            $rowNumber++;
            $rows[] = $this->parseRow($row, $headerMap, $parameterColumns, $rowNumber);
        }

        return new ParsedImport($headers ?? [], $rows);
    }

    /**
     * Загрузить типы параметров работ для типа заявки, ключ — имя параметра.
     *
     * @return array<string, object>
     */
    private function loadWorkParameterTypes(?int $requestTypeId): array
    {
        if (! $requestTypeId) {
            return [];
        }

        return DB::table('work_parameter_types')
            ->where('request_type_id', $requestTypeId)
            ->where('is_deleted', false)
            ->get()
            ->keyBy('name')
            ->toArray();
    }

    /**
     * Проверка заголовков и набора колонок.
     *
     * @param  array  $headers  оригинальные заголовки
     * @param  array  $normalizedHeaders  нормализованные (lower+trim)
     * @param  array<string, object>  $workParameterTypes
     *
     * @throws ImportValidationException
     */
    private function validateHeaders(array $headers, array $normalizedHeaders, array $workParameterTypes): void
    {
        if (count(array_intersect(self::EXPECTED_HEADERS, $normalizedHeaders)) !== count(self::EXPECTED_HEADERS)) {
            throw new ImportValidationException(
                'Неверные заголовки в первой строке файла. Обязательные колонки: '.implode(', ', self::EXPECTED_HEADERS),
                ['headers_found' => $headers]
            );
        }

        // Колонки параметров работ НЕОБЯЗАТЕЛЬНЫ. Если они есть в файле —
        // их значения (число > 0) записываются; если нет — параметры не
        // создаются, монтажник заполнит их позже при работе с заявкой (форма
        // редактирования показывает все параметры типа со значением 0).
        //
        // Для типа БЕЗ параметров лишних колонок быть не должно — это почти
        // всегда признак неверно выбранного типа или мусора в файле.
        if (empty($workParameterTypes)) {
            $extraColumns = [];
            foreach ($headers as $header) {
                $normalizedCurrentHeader = trim(mb_strtolower((string) $header));
                if (! in_array($normalizedCurrentHeader, self::EXPECTED_HEADERS) && ! empty($normalizedCurrentHeader)) {
                    $extraColumns[] = $header;
                }
            }

            if (! empty($extraColumns)) {
                throw new ImportValidationException(
                    'В файле найдены лишние колонки: '.implode(', ', $extraColumns).'. Удалите их или выберите тип заявки, к которому относятся эти параметры.'
                );
            }
        }
    }

    /**
     * Разбор одной строки данных в нейтральную структуру.
     *
     * @param  array  $row  значения строки по индексам колонок
     * @param  array<string,int>  $headerMap  нормализованный заголовок => индекс
     * @param  array<int,array{id:int,name:string}>  $parameterColumns
     */
    private function parseRow(array $row, array $headerMap, array $parameterColumns, int $rowNumber): array
    {
        $rowData = [];
        foreach ($headerMap as $normalizedHeader => $index) {
            $rowData[$normalizedHeader] = $row[$index] ?? null;
        }

        // 1. Адрес ("город X, улица Y, дом N, корпус M")
        $addressString = (string) ($rowData['адрес организации'] ?? '');
        $parsedAddress = $this->parseAddressString($addressString);

        // 2. Контакт: вытаскиваем телефон, остаток считаем ФИО
        $contactString = (string) ($rowData['контакт'] ?? '');
        $phone = '';
        $fio = $contactString;
        if (preg_match('/((?:\+7|8)[\s-]?\(?\d{3}\)?[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2})/', $contactString, $matches)) {
            $phone = $matches[0];
            $fio = trim(str_replace($phone, '', $fio));
        }

        // 3. Параметры работ: только числовые значения > 0
        $workParameters = [];
        foreach ($parameterColumns as $columnIndex => $parameter) {
            $value = $row[$columnIndex] ?? null;
            if (is_numeric($value) && $value > 0) {
                $workParameters[] = [
                    'parameter_type_id' => (int) $parameter['id'],
                    'name' => $parameter['name'],
                    'quantity' => (int) $value,
                ];
            }
        }

        return [
            'rowNumber' => $rowNumber,
            'organization' => (string) ($rowData['гбоу'] ?? ''),
            'rawAddress' => $addressString,
            'city_name' => $parsedAddress['city_name'],
            'street' => $parsedAddress['street'],
            'houses' => $parsedAddress['houses'],
            'fio' => $fio,
            'phone' => $phone,
            'comment' => (string) ($rowData['комментарии к монтажу'] ?? ''),
            'workParameters' => $workParameters,
        ];
    }

    /**
     * Разбирает "город Москва, улица Павловская, дом 8А, корпус 1"
     * на ['city_name', 'street', 'houses'].
     *
     * - 'street' — название улицы с типом ("улица Павловская"), сохраняется как есть.
     * - 'houses' — всё после "дом N" ("8А, корпус 1"); может быть пустым.
     */
    private function parseAddressString(string $raw): array
    {
        $parts = explode(',', $raw, 2);
        $cityRaw = trim($parts[0] ?? '');
        $rest = trim($parts[1] ?? '');

        $cityName = trim(preg_replace('/^(г\.|город|г\s)\s*/iu', '', $cityRaw));

        $street = $rest;
        $houses = '';

        // Шаг 1. Запятая, после которой (возможно, через "дом"/"д.") идёт число.
        if (preg_match('/^(.*?),\s*(?:(?:дом|д\.)\s*)?([0-9].*)$/iu', $rest, $m)) {
            $street = trim($m[1], " ,.");
            $houses = trim($m[2], " ,.");
        }
        // Шаг 2. Запятой нет, но есть ключевое слово дома/корпуса/строения.
        elseif (preg_match('/^(.*?)\s+(?:дом|д\.|д|корпус|корп|корп\.|к\.|к|строение|стр|стр\.)\s*([0-9].*)$/iu', $rest, $m)) {
            $street = trim($m[1], " ,.");

            if (preg_match('/^(.*?)\s+(?:дом|д\.|д)\s+([0-9].*)$/iu', $rest, $subM)) {
                // "дом"/"д." не сохраняем в houses (нужно "10", а не "дом 10").
                $houses = trim($subM[2], " ,.");
            } else {
                // Для корпусов/строений сохраняем префикс ("корпус 1630").
                if (preg_match('/^(.*?)\s+((?:корпус|корп|корп\.|к\.|к|строение|стр|стр\.)\s+[0-9].*)$/iu', $rest, $subM)) {
                    $houses = trim($subM[2], " ,.");
                } else {
                    $houses = trim($m[2], " ,.");
                }
            }
        }
        // Шаг 3. Запятой и ключевых слов нет, но на конце пробел и число.
        elseif (preg_match('/^(.*?)\s+([0-9]+[а-яА-Яa-zA-Z]*)$/iu', $rest, $m)) {
            $street = trim($m[1], " ,.");
            $houses = trim($m[2], " ,.");
        }

        return [
            'city_name' => $cityName,
            'street' => $street,
            'houses' => $houses,
        ];
    }
}
