<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    /**
     * Обработка входящего запроса.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Засекаем время начала обработки запроса
        $startTime = microtime(true);

        // Получаем ответ от приложения
        $response = $next($request);

        // Вычисляем время выполнения
        $executionTime = (int) ((microtime(true) - $startTime) * 1000); // в миллисекундах

        try {
            // Записываем информацию о запросе в базу данных
            DB::table('request_logs')->insert([
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_headers' => json_encode($request->headers->all()),
                'request_body' => $request->getContent() ? json_encode($request->all()) : null,
                'response_status' => $response->getStatusCode(),
                'execution_time' => $executionTime,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Дополнительно логируем в файл (опционально)
            // Log::info('Request logged to database', [
            //     'method' => $request->method(),
            //     'url' => $request->fullUrl(),
            //     'status' => $response->getStatusCode(),
            //     'execution_time' => $executionTime . 'ms'
            // ]);
        } catch (\Exception $e) {
            // В случае ошибки логируем её, но не прерываем выполнение приложения
            Log::error('Failed to log request to database: ' . $e->getMessage());
        }

        return $response;
    }
}
