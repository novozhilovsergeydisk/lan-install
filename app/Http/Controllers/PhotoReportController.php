<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PhotoReportController extends Controller
{
    /**
     * Display a listing of the photo reports.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'request_id' => 'required|exists:requests,id',
            ]);

            // if ($validator->fails()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Неверный ID заявки',
            //     ]);
            // }

            // $requestId = $validator->validated()['request_id'];

            // Используем raw SQL запрос для корректной работы с массивами PostgreSQL
            $sql = '
            SELECT 
                r.id AS request_id,
                r.number AS request_number,
                c.id AS comment_id,
                c.comment AS comment_text,
                c.created_at AS comment_created_at,
                json_agg(p.path) AS photo_paths,
                json_agg(p.original_name) AS photo_names
            FROM requests r
            JOIN request_comments rc ON r.id = rc.request_id
            JOIN comments c ON rc.comment_id = c.id
            JOIN comment_photos cp ON c.id = cp.comment_id
            JOIN photos p ON cp.photo_id = p.id
            GROUP BY r.id, r.number, c.id, c.comment, c.created_at
            ORDER BY r.id DESC, c.id
        ';

            $results = DB::select($sql);

            $groupedResults = [];

            foreach ($results as $row) {
                $requestId = $row->request_id;

                if (! isset($groupedResults[$requestId])) {
                    $groupedResults[$requestId] = [
                        'id' => $row->request_id,
                        'number' => $row->request_number,
                        'comments' => [],
                    ];
                }

                // Декодируем JSON массивы
                $photoPaths = json_decode($row->photo_paths, true) ?: [];
                $photoNames = json_decode($row->photo_names, true) ?: [];

                $photos = [];
                foreach ($photoPaths as $index => $path) {
                    $photoName = $photoNames[$index] ?? basename($path);
                    $photoUrl = str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));

                    $photos[] = [
                        'url' => $photoUrl,
                        'path' => $path,
                        'original_name' => $photoName,
                        'created_at' => $row->comment_created_at,
                    ];
                }

                $groupedResults[$requestId]['comments'][] = [
                    'id' => $row->comment_id,
                    'text' => $row->comment_text,
                    'created_at' => $row->comment_created_at,
                    'photos' => $photos,
                ];
            }

            $photos = array_values($groupedResults);

            return response()->json([
                'success' => true,
                'data' => $photos,
            ]);

            // return view('photo-reports.index', compact('photos'));
        } catch (\Exception $e) {
            Log::error('Error in PhotoReportController@index: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении фотоотчетов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Очистка имени папки от недопустимых символов
     */
    private function sanitizeFolderName($name)
    {
        // Декодируем HTML сущности
        $name = html_entity_decode($name);
        // Заменяем недопустимые символы на подчеркивание (используем ~ как разделитель regex)
        $name = preg_replace('~[\\\\/:*?"<>|]~', '_', $name);
        // Убираем лишние пробелы и точки в начале/конце
        $name = trim($name, " \t\n\r\0\x0B.");
        // Обрезаем длину до 50 символов
        return mb_substr($name, 0, 50);
    }

    /**
     * Скачивание всех фото и файлов заявки архивом (структурированным по комментариям)
     *
     * @param int $requestId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadRequestPhotos($requestId)
    {
        try {
            // Получаем комментарии с привязанными файлами и фото
            $comments = DB::table('request_comments as rc')
                ->join('comments as c', 'rc.comment_id', '=', 'c.id')
                ->where('rc.request_id', $requestId)
                ->select('c.id', 'c.comment')
                ->get();

            if ($comments->isEmpty()) {
                 return response()->json(['success' => false, 'message' => 'Комментарии не найдены'], 404);
            }

            // Создаем временный файл
            $zipFileName = 'attachments_request_' . $requestId . '_' . time() . '.zip';
            $zipFilePath = storage_path('app/temp/' . $zipFileName);
            
            // Убедимся, что директория существует
            if (!file_exists(dirname($zipFilePath))) {
                mkdir(dirname($zipFilePath), 0755, true);
            }

            $zip = new \ZipArchive;
            if ($zip->open($zipFilePath, \ZipArchive::CREATE) !== TRUE) {
                 return response()->json(['success' => false, 'message' => 'Не удалось создать архив'], 500);
            }

            $addedFilesCount = 0;

            foreach ($comments as $comment) {
                // Формируем имя папки из комментария
                $cleanComment = strip_tags($comment->comment);
                $folderName = $this->sanitizeFolderName($cleanComment);
                
                if (empty($folderName)) {
                    $folderName = 'comment_' . $comment->id;
                } else {
                    // Добавляем ID чтобы избежать коллизий одинаковых комментариев и сделать имена уникальными
                    $folderName = $folderName . '_' . $comment->id; 
                }

                // Получаем фото для комментария
                $photos = DB::table('comment_photos as cp')
                    ->join('photos as p', 'cp.photo_id', '=', 'p.id')
                    ->where('cp.comment_id', $comment->id)
                    ->select('p.path', 'p.original_name')
                    ->get();

                // Получаем файлы для комментария
                $files = DB::table('comment_files as cf')
                    ->join('files as f', 'cf.file_id', '=', 'f.id')
                    ->where('cf.comment_id', $comment->id)
                    ->select('f.path', 'f.original_name')
                    ->get();
                
                $attachments = $photos->concat($files);

                if ($attachments->isNotEmpty()) {
                    // Создаем папку в ZIP
                    $zip->addEmptyDir($folderName);

                    foreach ($attachments as $file) {
                        $possiblePaths = [
                            storage_path('app/public/' . $file->path),
                            public_path('storage/' . $file->path),
                            public_path($file->path)
                        ];

                        foreach ($possiblePaths as $filePath) {
                            if (file_exists($filePath)) {
                                $entryName = $file->original_name ?: basename($filePath);
                                // Путь внутри архива: ИмяПапки/ИмяФайла
                                $zipPath = $folderName . '/' . $entryName;
                                
                                // Проверка на дубликаты имен внутри одной папки
                                $i = 1;
                                $originalEntryName = $entryName;
                                while ($zip->locateName($zipPath) !== false) {
                                    $info = pathinfo($originalEntryName);
                                    $entryName = $info['filename'] . '_' . $i++ . '.' . ($info['extension'] ?? '');
                                    $zipPath = $folderName . '/' . $entryName;
                                }
                                
                                $zip->addFile($filePath, $zipPath);
                                $addedFilesCount++;
                                break; 
                            }
                        }
                    }
                }
            }
            
            $zip->close();
            
            if ($addedFilesCount === 0) {
                // Если файл пустой (не было реальных файлов), удаляем его
                if (file_exists($zipFilePath)) {
                    unlink($zipFilePath);
                }
                return response()->json(['success' => false, 'message' => 'Файлы не найдены или отсутствуют на диске'], 404);
            }

            return response()->download($zipFilePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error downloading attachments: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Публичное скачивание файлов по защищенному токену
     *
     * @param int $requestId
     * @param string $token
     * @return mixed
     */
    public function downloadRequestPhotosPublic($requestId, $token)
    {
        // Секретная соль для генерации токена (можно вынести в конфиг)
        $secret = config('app.key'); 
        $expectedToken = md5($requestId . $secret . 'telegram-notify');

        if ($token !== $expectedToken) {
             abort(403, 'Invalid download token');
        }

        $tempDir = storage_path('app/temp');
        $readyFile = $tempDir . '/archive_' . $requestId . '.ready';
        $processingFile = $tempDir . '/archive_' . $requestId . '.processing';

        // 1. Если архив готов - отдаем
        if (file_exists($readyFile)) {
            $readyData = json_decode(file_get_contents($readyFile), true);
            $zipPath = $readyData['path'] ?? null;
            $zipName = $readyData['file'] ?? 'archive.zip';

            if ($zipPath && file_exists($zipPath) && (time() - $readyData['created_at'] < 3600)) {
                return response()->download($zipPath, $zipName);
            }
            @unlink($readyFile);
        }

        // 2. Если не готовится - запускаем
        if (!file_exists($processingFile)) {
             $command = "nohup php " . base_path('artisan') . " archive:create {$requestId} > /dev/null 2>&1 &";
             exec($command);
        }

        // 3. Возвращаем страницу ожидания
        return view('download-wait');
    }
}