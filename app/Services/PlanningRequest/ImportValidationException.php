<?php

namespace App\Services\PlanningRequest;

/**
 * Ошибка валидации структуры загружаемого Excel-файла (заголовки, набор колонок).
 *
 * Несёт пользовательское сообщение и опциональную дополнительную нагрузку
 * (например, список найденных заголовков), которую контроллер отдаёт в JSON.
 * Используется одинаково и предосмотром, и реальной загрузкой, чтобы тексты
 * ошибок не расходились.
 */
class ImportValidationException extends \Exception
{
    public function __construct(string $message, private array $payload = [])
    {
        parent::__construct($message);
    }

    /**
     * Дополнительные данные для ответа клиенту (например, ['headers_found' => [...]]).
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
