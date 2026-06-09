<?php

namespace App\Services\PlanningRequest;

/**
 * Результат разбора Excel-файла заявок.
 *
 * Чистая структура данных без обращений к БД: используется и предосмотром
 * (read-only), и реальной загрузкой, чтобы оба пути разбирали файл идентично.
 *
 * @param list<array{
 *     rowNumber:int,
 *     organization:string,
 *     rawAddress:string,
 *     city_name:string,
 *     street:string,
 *     houses:string,
 *     fio:string,
 *     phone:string,
 *     comment:string,
 *     workParameters:list<array{parameter_type_id:int,name:string,quantity:int}>
 * }> $rows
 */
class ParsedImport
{
    public function __construct(
        public readonly array $headers,
        public readonly array $rows,
    ) {}
}
