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
     * Format file size to human readable format
     *
     * @param  int  $bytes
     * @return string
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        } elseif ($bytes > 1) {
            return $bytes.' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }

    /**
     * Скачивание всех фото и файлов заявки архивом
     *
     * @param int $requestId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadRequestPhotos($requestId)
    {
        try {
            // Получаем список фото
            $photos = DB::table('requests as r')
                ->join('request_comments as rc', 'r.id', '=', 'rc.request_id')
                ->join('comments as c', 'rc.comment_id', '=', 'c.id')
                ->join('comment_photos as cp', 'c.id', '=', 'cp.comment_id')
                ->join('photos as p', 'cp.photo_id', '=', 'p.id')
                ->where('r.id', $requestId)
                ->select('p.path', 'p.original_name')
                ->distinct()
                ->get();

            // Получаем список файлов
            $files = DB::table('requests as r')
                ->join('request_comments as rc', 'r.id', '=', 'rc.request_id')
                ->join('comments as c', 'rc.comment_id', '=', 'c.id')
                ->join('comment_files as cf', 'c.id', '=', 'cf.comment_id')
                ->join('files as f', 'cf.file_id', '=', 'f.id')
                ->where('r.id', $requestId)
                ->select('f.path', 'f.original_name')
                ->distinct()
                ->get();

            // Объединяем коллекции
            $allAttachments = $photos->concat($files);

            if ($allAttachments->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Файлы не найдены'], 404);
            }

            // Создаем временный файл
            $zipFileName = 'attachments_request_' . $requestId . '_' . time() . '.zip';
            $zipFilePath = storage_path('app/temp/' . $zipFileName);
            
            // Убедимся, что директория существует
            if (!file_exists(dirname($zipFilePath))) {
                mkdir(dirname($zipFilePath), 0755, true);
            }

            $zip = new \ZipArchive;
            if ($zip->open($zipFilePath, \ZipArchive::CREATE) === TRUE) {
                $addedFiles = 0;
                
                foreach ($allAttachments as $file) {
                    $possiblePaths = [
                        storage_path('app/public/' . $file->path),
                        public_path('storage/' . $file->path),
                        public_path($file->path)
                    ];

                    foreach ($possiblePaths as $filePath) {
                        if (file_exists($filePath)) {
                            $entryName = $file->original_name ?: basename($filePath);
                            
                            // Уникальное имя
                            $i = 1;
                            while ($zip->locateName($entryName) !== false) {
                                $info = pathinfo($entryName);
                                $entryName = $info['filename'] . '_' . $i++ . '.' . ($info['extension'] ?? '');
                            }
                            
                            $zip->addFile($filePath, $entryName);
                            $addedFiles++;
                            break; 
                        }
                    }
                }
                $zip->close();
                
                if ($addedFiles === 0) {
                    return response()->json(['success' => false, 'message' => 'Не удалось найти физические файлы на сервере'], 404);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Не удалось создать архив'], 500);
            }

            return response()->download($zipFilePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Error downloading attachments: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()], 500);
        }
    }
}