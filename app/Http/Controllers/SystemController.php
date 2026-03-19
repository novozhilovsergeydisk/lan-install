<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SystemController extends Controller
{
    /**
     * Возвращает метрики системы (CPU, RAM, Диск) в формате JSON
     */
    public function metrics(): JsonResponse
    {
        // Проверяем права администратора (если у вас используется isAdmin или role_id)
        if (!auth()->check() || !auth()->user()->isAdmin) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Путь к нашей C-утилите
        $monitorPath = base_path('utils/C/sys-monitor/sys-monitor');

        if (!file_exists($monitorPath)) {
            return response()->json(['error' => 'System monitor utility not found. Please compile it.'], 500);
        }

        // Выполняем утилиту
        $output = shell_exec($monitorPath);

        if (!$output) {
            return response()->json(['error' => 'Failed to execute system monitor.'], 500);
        }

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Failed to parse system monitor output.'], 500);
        }

        // Добавляем список топ-процессов
        $isMac = PHP_OS_FAMILY === 'Darwin';
        
        // Фильтр grep -v ps скрывает саму утилиту мониторинга из списка, чтобы не сбивать пользователя.
        // Чтобы снова видеть процесс ps в списке, удалите "| grep -v ps" из команд ниже.
        $psCommand = $isMac 
            ? "ps -rcax -o user,pid,pcpu,pmem,comm | grep -v ps | head -n 6 | tail -n +2" 
            : "ps -eo user,pid,pcpu,pmem,comm --sort=-pcpu | grep -v ps | head -n 6 | tail -n +2";
        
        $psOutput = shell_exec($psCommand);
        $processes = [];
        
        if ($psOutput) {
            $lines = explode("\n", trim($psOutput));
            foreach ($lines as $line) {
                $cols = preg_split('/\s+/', trim($line), 5);
                if (count($cols) >= 5) {
                    $processes[] = [
                        'user' => $cols[0],
                        'pid' => $cols[1],
                        'cpu' => $cols[2],
                        'mem' => $cols[3],
                        'command' => $cols[4]
                    ];
                }
            }
        }
        
        $data['top_processes'] = $processes;

        return response()->json($data);
    }
}
