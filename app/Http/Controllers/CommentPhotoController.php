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

            // Включаем режим отладки SQL
            \DB::listen(function ($query) {
                try {
                    $sql = $query->sql;
                    $bindings = $query->bindings;
                    $time = $query->time;
                    
                    // Заменяем плейсхолдеры на реальные значения
                    foreach ($bindings as $binding) {
                        if (is_null($binding)) {
                            $value = 'NULL';
                        } elseif (is_numeric($binding)) {
                            $value = $binding;
                        } elseif (is_bool($binding)) {
                            $value = $binding ? 'true' : 'false';
                        } else {
                            $value = "'" . addslashes($binding) . "'";
                        }
                        
                        $sql = preg_replace('/\?/', $value, $sql, 1);
                    }
                    
                    \Log::info("SQL Query [{$time}ms]: " . $sql);
                } catch (\Exception $e) {
                    \Log::error('Ошибка логирования SQL: ' . $e->getMessage());
                }
            });

            // Начинаем транзакцию для реального выполнения запросов
            \DB::beginTransaction();
            \Log::info('Начата транзакция');
            
            // Преобразуем индексированный массив в ассоциативный (первая строка - заголовки)
            $headers = array_shift($data);
            $result = [];
            $citiesNotFound = [];
            
            // Добавляем заголовок для city_id, если его еще нет
            if (!in_array('city_id', $headers)) {
                $headers[] = 'city_id';
            }
            
            foreach ($data as $rowData) {
                // Выравниваем количество элементов в строке с количеством заголовков (минус 1, так как мы добавили city_id)
                $row = array_pad($rowData, count($headers) - 1, null);
                
                // Удаляем лишние пробелы в начале и конце значений ячеек
                $row = array_map(function($cell) {
                    return is_string($cell) ? trim($cell) : $cell;
                }, $row);
                
                // Получаем название города из первого столбца
                $cityName = isset($row[0]) && is_string($row[0]) ? trim(str_ireplace('город ', '', $row[0])) : null;

                $district = isset($row[1]) && is_string($row[1]) ? trim($row[1]) : '';
                $street = isset($row[2]) && is_string($row[2]) ? trim($row[2]) : '';
                $houses = isset($row[3]) && is_string($row[3]) ? trim($row[3]) : '';
                
                // Пропускаем строку, если отсутствуют обязательные данные
                if (empty($cityName) || empty($street) || empty($district) || empty($houses)) {
                    continue;
                }
                
                // Ищем город в базе данных
                $cityId = null;
                if ($cityName) {
                    try {
                        // Сначала пробуем найти город по точному совпадению
                        $city = DB::table('cities')
                            ->where('name', 'ilike', $cityName)
                            ->first();

                        if ($city) {
                            $cityId = $city->id;
                        } else {
                            // Если город не найден, пробуем найти по частичному совпадению
                            $city = DB::table('cities')
                                ->where('name', 'ilike', '%' . $cityName . '%')
                                ->first();

                            if ($city) {
                                $cityId = $city->id;
                            } else {
                                // Если город не найден, создаем новый
                                $cityData = [
                                    'name' => $cityName,
                                    'region_id' => 1,
                                    'postal_code' => null
                                ];
                                $cityId = DB::table('cities')->insertGetId($cityData);
                                \Log::info('Добавлен новый город:', array_merge(['id' => $cityId], $cityData));
                                
                                if (!$cityId) {
                                    // Если не удалось получить ID, пробуем найти город снова
                                    $city = DB::table('cities')
                                        ->where('name', 'ilike', $cityName)
                                        ->first();
                                    
                                    if ($city) {
                                        $cityId = $city->id;
                                    } else {
                                        // Если город так и не найден, пропускаем строку
                                        $citiesNotFound[] = $cityName;
                                        continue;
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // В случае ошибки логируем и пропускаем строку
                        \Log::error('Ошибка при обработке города: ' . $e->getMessage(), [
                            'city_name' => $cityName,
                            'error' => $e->getTraceAsString()
                        ]);
                        $citiesNotFound[] = $cityName;
                        continue;
                    }

                    // Ищем адрес по трем полям
                    $address = DB::table('addresses')
                        ->where('city_id', $cityId)
                        ->where('street', $street)
                        ->where('district', $district)
                        ->where('houses', $houses)
                        ->first(['id']);

                    // Если адрес не найден, создаем новый
                    if (!$address) {
                        try {
                            $addressData = [
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses
                            ];
                            $addressId = DB::table('addresses')->insertGetId($addressData);
                            \Log::info('Добавлен новый адрес:', array_merge(['id' => $addressId], $addressData));
                            
                            \Log::info('Добавлен новый адрес', [
                                'id' => $addressId,
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Ошибка при добавлении адреса', [
                                'error' => $e->getMessage(),
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses
                            ]);
                            continue; // Пропускаем эту строку и переходим к следующей
                        }
                    } else {
                        $addressId = $address->id;
                    }
                } // закрывающая скобка для if ($cityName)
                
                // Добавляем ID города в массив значений
                $row[] = $cityId;
                
                // Объединяем заголовки со значениями
                $result[] = array_combine($headers, $row);
            }

            // Собираем статистику по добавленным данным
            $addedCities = [];
            $addedAddresses = [];
            
            // Проходим по всем обработанным строкам
            foreach ($result as $row) {
                $cityName = $row['city'] ?? null;
                $street = $row['street'] ?? null;
                $district = $row['district'] ?? null;
                $houses = $row['houses'] ?? null;
                $cityId = $row['city_id'] ?? null;
                
                if ($cityId && !in_array($cityName, $citiesNotFound)) {
                    $addedCities[$cityId] = [
                        'id' => $cityId,
                        'name' => $cityName,
                        'region_id' => 1
                    ];
                }
                
                if ($cityId && $street && $district && $houses) {
                    $addressKey = "{$cityId}_{$street}_{$district}_{$houses}";
                    $addedAddresses[$addressKey] = [
                        'city_id' => $cityId,
                        'street' => $street,
                        'district' => $district,
                        'houses' => $houses
                    ];
                }
            }
            
            $response = [
                'success' => true,
                'data' => $result,
                'headers' => $headers,
                'rows_count' => count($result),
                'cities_not_found' => array_unique($citiesNotFound),
                'added_data' => [
                    'cities' => array_values($addedCities),
                    'addresses' => array_values($addedAddresses),
                    'cities_count' => count($addedCities),
                    'addresses_count' => count($addedAddresses)
                ]
            ];
            
            // Добавляем информационные сообщения
            $messages = [];
            
            if (!empty($citiesNotFound)) {
                $messages[] = 'Некоторые города не найдены в базе данных: ' . 
                    implode(', ', array_unique($citiesNotFound));
            }
            
            if (!empty($addedCities)) {
                $messages[] = 'Добавлено новых городов: ' . count($addedCities);
            }
            
            if (!empty($addedAddresses)) {
                $messages[] = 'Добавлено новых адресов: ' . count($addedAddresses);
            }
            
            if (!empty($messages)) {
                $response['message'] = implode('. ', $messages) . '.';
            }

            // Завершаем транзакцию, так как все проверки пройдены
            \DB::commit();
            \Log::info('Транзакция успешно завершена, изменения сохранены');
            
            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Ошибка при обработке файла: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            if (\DB::transactionLevel() > 0) {
                \Log::info('Откатываем транзакцию из-за ошибки');
                \DB::rollBack();
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Ошибка при чтении файла',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function downloadAllPhotos()
    {
        try {
            // Создаем временный файл для архива
            $zip = new \ZipArchive();
            $zipName = 'photos_archive_' . now()->format('Y-m-d_H-i-s') . '.zip';
            $zipPath = storage_path('app/temp/' . $zipName);
            
            // Создаем директорию, если она не существует
            if (!file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \Exception('Не удалось создать архив');
            }

            // Получаем все комментарии с фотографиями для всех заявок
            $comments = \DB::table('requests as r')
                ->leftJoin('request_comments as rc', 'rc.request_id', '=', 'r.id')
                ->leftJoin('comments as c', 'c.id', '=', 'rc.comment_id')
                ->leftJoin('comment_photos as cp', 'cp.comment_id', '=', 'c.id')
                ->leftJoin('photos as p', 'p.id', '=', 'cp.photo_id')
                ->select(
                    'r.id as request_id',
                    'r.number as request_number',
                    'c.id as comment_id',
                    'c.comment',
                    'p.path as photo_path',
                    'p.original_name',
                    'cp.created_at as photo_created_at'
                )
                ->whereNotNull('p.path')  // Берем только комментарии с фотографиями
                ->orderBy('c.id')
                ->orderBy('cp.created_at')
                ->get()
                ->groupBy('comment_id');

            foreach ($comments as $commentId => $commentPhotos) {
                // Создаем имя папки на основе номера заявки и комментария
                $folderName = $commentPhotos[0]->request_number . '_' . 
                             preg_replace('/[^a-zA-Zа-яА-Я0-9\s\-_]/u', '', $commentPhotos[0]->comment);
                $folderName = mb_substr($folderName, 0, 50); // Ограничиваем длину имени папки

                foreach ($commentPhotos as $index => $photo) {
                    $photoPath = storage_path('app/public/' . $photo->photo_path);
                    
                    if (file_exists($photoPath)) {
                        // Создаем уникальное имя файла, чтобы избежать перезаписи
                        $fileExtension = pathinfo($photo->original_name, PATHINFO_EXTENSION);
                        $fileName = $index . '_' . $photo->original_name;
                        
                        // Добавляем файл в архив с путем, включающим имя папки комментария
                        $zip->addFile(
                            $photoPath,
                            $folderName . '/' . $fileName
                        );
                    }
                }
            }

            $zip->close();

            // Проверяем, что архив не пустой
            if (filesize($zipPath) === 0) {
                throw new \Exception('Не найдено фотографий для скачивания');
            }

            // Отправляем архив на скачивание
            return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Ошибка при создании архива с фотографиями: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            // Удаляем временный файл, если он был создан
            if (isset($zipPath) && file_exists($zipPath)) {
                unlink($zipPath);
            }

            return response()->json([
                'success' => false,
                'error' => 'Не удалось создать архив с фотографиями',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
