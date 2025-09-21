<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CommentPhotoController extends Controller
{
    /**
     * Получить фотографии для комментария
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($commentId)
    {
        try {
            // Validate comment_id
            if (!is_numeric($commentId) || $commentId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid comment ID'
                ], 400);
            }

            $photos = DB::table('comment_photos')
                ->join('photos', 'comment_photos.photo_id', '=', 'photos.id')
                ->where('comment_photos.comment_id', $commentId)
                ->select([
                    'photos.id',
                    'photos.path',
                    'photos.original_name',
                    'photos.file_size',
                    'photos.mime_type',
                    'photos.width',
                    'photos.height',
                    'photos.created_at',
                ])
                ->get()
                ->map(function($photo) {
                    return [
                        'id' => $photo->id,
                        'url' => asset('storage/' . $photo->path),
                        'path' => $photo->path,
                        'original_name' => $photo->original_name,
                        'file_size' => $photo->file_size,
                        'mime_type' => $photo->mime_type,
                        'width' => $photo->width,
                        'height' => $photo->height,
                        'created_at' => $photo->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $photos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить фотографии',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * Получить файлы для комментария
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Получить файлы для комментария
     *
     * @param  int  $commentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCommentFiles($commentId)
    {
        try {
            // Validate comment_id
            if (!is_numeric($commentId) || $commentId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid comment ID'
                ], 400);
            }

            $files = DB::table('comment_files')
                ->join('files', 'comment_files.file_id', '=', 'files.id')
                ->where('comment_files.comment_id', $commentId)
                ->select([
                    'files.id',
                    'files.path',
                    'files.original_name',
                    'files.file_size',
                    'files.mime_type',
                    'files.extension',
                    'files.created_by',
                    'files.created_at',
                    'files.updated_at',
                ])
                ->get()
                ->map(function($file) {
                    return [
                        'id' => $file->id,
                        'url' => asset('storage/' . $file->path),
                        'path' => $file->path,
                        'original_name' => $file->original_name,
                        'file_size' => $file->file_size,
                        'mime_type' => $file->mime_type,
                        'extension' => $file->extension,
                        'created_by' => $file->created_by,
                        'created_at' => $file->created_at,
                        'updated_at' => $file->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $files
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить файлы',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadExcel(Request $request) {
        try {
            // Валидация файла
            $validated = $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls|max:10240',
            ]);

            // Получаем загруженный файл
            $file = $request->file('excel_file');
            
            // Определяем тип файла по расширению
            $fileType = $file->getClientOriginalExtension();
            $readerType = strtolower($fileType) === 'xls' ? 'Xls' : 'Xlsx';
            
            // Создаем reader для нужного типа файла
            $reader = IOFactory::createReader($readerType);
            
            // Указываем, что нам нужно только прочитать данные, без лишней информации
            $reader->setReadDataOnly(true);
            
            // Загружаем файл напрямую из временного хранилища
            $spreadsheet = $reader->load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Преобразуем в массив
            $data = $worksheet->toArray();
            
            // Удаляем пустые строки
            $data = array_filter($data, function($row) {
                return !empty(array_filter($row, function($value) {
                    return $value !== null && $value !== '';
                }));
            });
            
            // Если нет данных
            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Файл не содержит данных'
                ], 400);
            }

            DB::beginTransaction();
            
            // Преобразуем индексированный массив в ассоциативный (первая строка - заголовки)
            $headers = array_shift($data);
            $result = [];
            $citiesNotFound = [];
            
            // Добавляем заголовок для city_id, если его еще нет
            if (!in_array('city_id', $headers)) {
                $headers[] = 'city_id';
            }
            
            // Получаем список всех городов из базы данных для оптимизации запросов
            // $cities = \DB::table('cities')->pluck('id', 'name')->toArray();
            
            foreach ($data as $rowData) {
                // Выравниваем количество элементов в строке с количеством заголовков (минус 1, так как мы добавили city_id)
                $row = array_pad($rowData, count($headers) - 1, null);
                
                // Удаляем лишние пробелы в начале и конце значений ячеек
                $row = array_map(function($cell) {
                    return is_string($cell) ? trim($cell) : $cell;
                }, $row);
                
                // Получаем название города из первого столбца
                $cityName = isset($row[0]) && is_string($row[0]) ? 
                    str_ireplace('город ', '', $row[0]) : 
                    null;

                $street = isset($row[1]) && is_string($row[1]) ? $row[1] : null;
                $district = isset($row[2]) && is_string($row[2]) ? $row[2] : null;
                $houses = isset($row[3]) && is_string($row[3]) ? $row[3] : null;
                
                // Ищем город в базе данных
                $cityId = null;
                if ($cityName) {
                    $city = DB::table('cities')->where('name', 'like', '%' . $cityName . '%')->first();

                    if ($city) {
                        $cityId = $city->id;
                    } else {
                        $cityId = DB::insertGetId([
                            'name' => $cityName,
                            'region_id' => 1,
                            'post_code' => null,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $citiesNotFound[] = $cityName;
                    }

                    /*
                    
                    */
                    $address = DB::selectOne('SELECT * FROM addresses WHERE city_id = ' . $cityId . ' AND address = ' . $row[1]);
                }
                
                // Добавляем ID города в массив значений
                $row[] = $cityId;
                
                // Объединяем заголовки со значениями
                $result[] = array_combine($headers, $row);
            }

            $response = [
                'success' => true,
                'data' => $result,
                'headers' => $headers,
                'rows_count' => count($result),
                'cities_not_found' => array_unique($citiesNotFound)
            ];
            
            // Добавляем предупреждение, если есть ненайденные города
            if (!empty($citiesNotFound)) {
                $response['warning'] = 'Некоторые города не найдены в базе данных: ' . 
                    implode(', ', array_unique($citiesNotFound));
            }

            DB::commit();
            
            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            // Логируем ошибку для отладки
            \Log::error('Ошибка при чтении Excel файла: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при чтении файла',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function downloadAllPhotos() {
        try {
            return response()->json([
                'success' => true,
                'data' => 'All photos downloaded successfully (test)'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить фотографии',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
