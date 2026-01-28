<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateRequestArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'archive:create {requestId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a ZIP archive of photos for a request using system zip utility';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $requestId = $this->argument('requestId');
        $this->info("Starting archive creation for Request ID: $requestId");

        try {
            // 1. Получаем данные
            $requestInfo = DB::table('requests as r')
                ->leftJoin('request_addresses as ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses as a', 'ra.address_id', '=', 'a.id')
                ->select('r.number', 'a.street', 'a.houses')
                ->where('r.id', $requestId)
                ->first();

            if (!$requestInfo) {
                $this->error("Request not found");
                return 1;
            }

            // Формируем имя файла
            $addressString = ($requestInfo->street ?? 'NoAddress') . '_' . ($requestInfo->houses ?? '');
            $zipNameRaw = $addressString . '_' . $requestInfo->number;
            $zipName = preg_replace('/[^a-zA-Zа-яА-Я0-9\s\-_]/u', '_', $zipNameRaw) . '.zip';
            $zipName = preg_replace('/_+/', '_', $zipName);
            
            // Пути
            $tempBaseDir = storage_path('app/temp');
            // Используем uniqid для избежания коллизий при одновременном запуске
            $workDir = $tempBaseDir . '/work_' . $requestId . '_' . uniqid();
            $outputZipPath = $tempBaseDir . '/' . $zipName; // Финальный путь
            
            // Маркер обработки (создаем файл .processing)
            $processingFile = $tempBaseDir . '/archive_' . $requestId . '.processing';
            file_put_contents($processingFile, json_encode(['start' => time(), 'pid' => getmypid()]));

            if (!file_exists($tempBaseDir)) {
                mkdir($tempBaseDir, 0755, true);
            }
            if (!file_exists($workDir)) {
                mkdir($workDir, 0755, true);
            }

            // 2. Получаем комментарии и фото
            $comments = DB::table('comments as c')
                ->join('request_comments as rc', 'c.id', '=', 'rc.comment_id')
                ->join('comment_photos as cp', 'c.id', '=', 'cp.comment_id')
                ->join('photos as p', 'cp.photo_id', '=', 'p.id')
                ->select(
                    'c.id as comment_id',
                    'c.comment',
                    'p.path as photo_path',
                    'p.original_name',
                    'cp.created_at as photo_created_at'
                )
                ->where('rc.request_id', $requestId)
                ->whereNotNull('p.path')
                ->orderBy('c.id')
                ->orderBy('cp.created_at')
                ->get()
                ->groupBy('comment_id');

            $fileCount = 0;

            foreach ($comments as $commentId => $commentPhotos) {
                $level1 = $requestInfo->number;
                
                $commentText = $commentPhotos[0]->comment ?? 'NoComment';
                $level2 = preg_replace('/[^a-zA-Zа-яА-Я0-9\s\-_]/u', ' ', $commentText);
                $level2 = mb_substr(trim($level2), 0, 50);
                if (empty($level2)) {
                    $level2 = 'Comment_' . $commentId;
                }

                $dirPath = $workDir . '/' . $level1 . '/' . $level2;
                if (!file_exists($dirPath)) {
                    mkdir($dirPath, 0755, true);
                }

                foreach ($commentPhotos as $index => $photo) {
                    $sourcePath = storage_path('app/public/' . $photo->photo_path);
                    if (file_exists($sourcePath)) {
                        $fileName = $index . '_' . $photo->original_name;
                        $destPath = $dirPath . '/' . $fileName;
                        
                        // Создаем симлинк вместо копирования!
                        if (!file_exists($destPath)) {
                            symlink($sourcePath, $destPath);
                            $fileCount++;
                        }
                    }
                }
            }

            if ($fileCount === 0) {
                $this->warn("No files found");
                // Удаляем маркер
                @unlink($processingFile);
                return 0;
            }

            $this->info("Prepared $fileCount files via symlinks. Zipping...");

            // 3. Запускаем ZIP
            // -r: рекурсивно
            // -0: без сжатия (быстро)
            // -q: тихо
            // -j: junk paths (не использовать, нам нужна структура)
            
            // Важно: переходим в workDir, чтобы пути в архиве были относительными
            $command = "cd " . escapeshellarg($workDir) . " && zip -r -0 -q " . escapeshellarg($outputZipPath) . " .";
            
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \Exception("Zip command failed with code $returnVar");
            }

            // 4. Очистка
            // Удаляем рабочую директорию (рекурсивно)
            exec("rm -rf " . escapeshellarg($workDir));

            // Удаляем маркер обработки
            @unlink($processingFile);

            // Создаем маркер готовности (или просто файл готов)
            // Записываем имя готового файла в json, чтобы контроллер знал имя
            $readyFile = $tempBaseDir . '/archive_' . $requestId . '.ready';
            file_put_contents($readyFile, json_encode([
                'file' => $zipName,
                'path' => $outputZipPath,
                'created_at' => time()
            ]));

            $this->info("Archive created successfully: $outputZipPath");

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::error("Archive creation failed for request $requestId: " . $e->getMessage());
            return 1;
        } finally {
            // Гарантированная очистка: удаляем рабочую директорию и маркер (если не создан ready)
            if (isset($workDir) && file_exists($workDir)) {
                exec("rm -rf " . escapeshellarg($workDir));
            }
            if (isset($processingFile) && file_exists($processingFile) && !file_exists($tempBaseDir . '/archive_' . $requestId . '.ready')) {
                @unlink($processingFile);
            }
        }

        return 0;
    }
}
