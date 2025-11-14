<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
            // Записываем информацию о запросе в лог-файл
            Log::info('Request handled', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'execution_time_ms' => $executionTime,
            ]);

        } catch (\Exception $e) {
            // В случае ошибки логируем её, но не прерываем выполнение приложения
            Log::error('Failed to log request: '.$e->getMessage());
        }

        return $response;
    }
}
