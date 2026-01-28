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
            if (! is_numeric($commentId) || $commentId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid comment ID',
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
                ->map(function ($photo) {
                    return [
                        'id' => $photo->id,
                        'url' => asset('storage/'.$photo->path),
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
                'data' => $photos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить фотографии',
                'message' => $e->getMessage(),
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
            if (! is_numeric($commentId) || $commentId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid comment ID',
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
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => asset('storage/'.$file->path),
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
                'data' => $files,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось загрузить файлы',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadExcel(Request $request)
    {
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
            \Log::info('Total rows in spreadsheet: '.count($data));

            // Удаляем пустые строки
            $data = array_filter($data, function ($row) {
                return ! empty(array_filter($row, function ($value) {
                    return $value !== null && $value !== '';
                }));
            });
            \Log::info('Rows after filter: '.count($data));

            // Если нет данных
            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Файл не содержит данных',
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
                            $value = "'".addslashes($binding)."'";
                        }

                        $sql = preg_replace('/\?/', $value, $sql, 1);
                    }

                    \Log::info("SQL Query [{$time}ms]: ".$sql);
                } catch (\Exception $e) {
                    \Log::error('Ошибка логирования SQL: '.$e->getMessage());
                }
            });

            // Начинаем транзакцию для реального выполнения запросов
            \DB::beginTransaction();
            \Log::info('Начата транзакция');

            // Игнорируем первую строку (название файла)
            $title = array_shift($data);

            // Вторая строка - заголовки
            $headers = array_shift($data);
            \Log::info('Data rows count after shifts: '.count($data));

            // Убираем пробелы в заголовках
            $headers = array_map(function ($header) {
                return is_string($header) ? trim($header) : $header;
            }, $headers);

            \Log::info('Headers after trim:', $headers);

            // Проверяем формат заголовков (принимаем разные регистры и частичные совпадения)
            $headerCheck = count($headers) < 4;
            if (! $headerCheck) {
                $expected = ['город', 'район', 'улица', 'дом'];
                for ($i = 0; $i < 4; $i++) {
                    $h = mb_strtolower(trim($headers[$i] ?? ''));
                    if (mb_strpos($h, $expected[$i]) === false) {
                        $headerCheck = true;
                        break;
                    }
                }
            }

            \Log::info('count = '.count($headers));
            \Log::info('Header check result: '.($headerCheck ? 'true' : 'false'));

            if ($headerCheck) {
                \Log::info('Entering header check if');

                return response()->json([
                    'success' => false,
                    'message' => 'Формат файла не соответствует названиям столбцов: Город, Район, Улица, Дом',
                ], 400);
            }

            $result = [];
            $citiesNotFound = [];
            $duplicatesCount = 0;
            $newlyAddedCities = [];
            $newlyAddedAddresses = [];

            // Добавляем заголовок для city_id, если его еще нет
            if (! in_array('city_id', $headers)) {
                $headers[] = 'city_id';
            }

            foreach ($data as $rowData) {
                // Выравниваем количество элементов в строке с количеством заголовков (минус 1, так как мы добавили city_id)
                $row = array_pad($rowData, count($headers) - 1, null);

                // Удаляем лишние пробелы в начале и конце значений ячеек
                $row = array_map(function ($cell) {
                    return is_string($cell) ? trim($cell) : $cell;
                }, $row);

                // Получаем название города из первого столбца
                $cityName = isset($row[0]) && is_string($row[0]) ? trim(str_ireplace('город ', '', $row[0])) : null;

                $district = isset($row[1]) && is_string($row[1]) ? trim($row[1]) : '';
                $street = isset($row[2]) && is_string($row[2]) ? trim($row[2]) : '';
                $houses = isset($row[3]) && is_string($row[3]) ? trim($row[3]) : '';

                \Log::info('Processing row: cityName='.$cityName.', street='.$street.', district='.$district.', houses='.$houses);

                // Пропускаем строку, если отсутствуют обязательные данные
                if (empty($cityName) || empty($street) || empty($district) || empty($houses)) {
                    \Log::info('Skipping row due to empty fields');

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
                                ->where('name', 'ilike', '%'.$cityName.'%')
                                ->first();

                            if ($city) {
                                $cityId = $city->id;
                            } else {
                                // Если город не найден, создаем новый
                                $cityData = [
                                    'name' => $cityName,
                                    'region_id' => 1,
                                    'postal_code' => null,
                                ];
                                $cityId = DB::table('cities')->insertGetId($cityData);
                                $newlyAddedCities[$cityId] = [
                                    'id' => $cityId,
                                    'name' => $cityName,
                                    'region_id' => 1,
                                ];
                                \Log::info('Добавлен новый город:', array_merge(['id' => $cityId], $cityData));

                                if (! $cityId) {
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
                        \Log::error('Ошибка при обработке города: '.$e->getMessage(), [
                            'city_name' => $cityName,
                            'error' => $e->getTraceAsString(),
                        ]);
                        $citiesNotFound[] = $cityName;

                        continue;
                    }

                    // Ищем адрес по городу, улице и дому (район не учитывается для избежания дублирования)
                    $address = DB::table('addresses')
                        ->where('city_id', $cityId)
                        ->where('street', $street)
                        ->where('houses', $houses)
                        ->first(['id']);

                    // Если адрес не найден, создаем новый
                    if (! $address) {
                        try {
                            $addressData = [
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses,
                            ];
                            $addressId = DB::table('addresses')->insertGetId($addressData);
                            $newlyAddedAddresses[] = [
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses,
                            ];
                            \Log::info('Добавлен новый адрес:', array_merge(['id' => $addressId], $addressData));

                            \Log::info('Добавлен новый адрес', [
                                'id' => $addressId,
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses,
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Ошибка при добавлении адреса', [
                                'error' => $e->getMessage(),
                                'city_id' => $cityId,
                                'street' => $street,
                                'district' => $district,
                                'houses' => $houses,
                            ]);

                            continue; // Пропускаем эту строку и переходим к следующей
                        }
                    } else {
                        $addressId = $address->id;
                        $duplicatesCount++;
                    }
                } // закрывающая скобка для if ($cityName)

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
                'cities_not_found' => array_unique($citiesNotFound),
                'added_data' => [
                    'cities' => array_values($newlyAddedCities),
                    'addresses' => $newlyAddedAddresses,
                    'cities_count' => count($newlyAddedCities),
                    'addresses_count' => count($newlyAddedAddresses),
                    'duplicates_count' => $duplicatesCount,
                ],
            ];

            // Добавляем информационные сообщения
            $messages = [];

            if (! empty($citiesNotFound)) {
                $messages[] = 'Некоторые города не найдены в базе данных: '.
                    implode(', ', array_unique($citiesNotFound));
            }

            if (! empty($newlyAddedCities)) {
                $messages[] = 'Добавлено новых городов: '.count($newlyAddedCities);
            }

            if (! empty($newlyAddedAddresses)) {
                $messages[] = 'Добавлено новых адресов: '.count($newlyAddedAddresses);
            }

            if ($duplicatesCount > 0) {
                $messages[] = 'Найдено дубликатов адресов: '.$duplicatesCount;
            }

            if (! empty($messages)) {
                $response['message'] = implode("\n", $messages).'.';
            }

            // Если не добавлено ни одного нового адреса, считаем загрузку неудачной
            if (count($newlyAddedAddresses) == 0) {
                $response['success'] = false;
                $rowsMsg = 'Обработано строк: '.count($result).'. ';
                $duplicatesMsg = $duplicatesCount > 0 ? 'Найдено дубликатов адресов: '.$duplicatesCount.'. ' : '';
                $response['message'] = $rowsMsg.'<br>'.$duplicatesMsg.'<br>'.'Новые адреса не добавлены.';
            }

            // Завершаем транзакцию, так как все проверки пройдены
            \DB::commit();
            \Log::info('Транзакция успешно завершена, изменения сохранены');
            \Log::info('Final response:', $response);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Ошибка при обработке файла: '.$e->getMessage());
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
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function downloadAllPhotos(Request $request)
    {
        try {
            $requestId = $request->input('request_id');

            if (! $requestId) {
                return response()->json(['success' => false, 'message' => 'Не указан ID заявки'], 400);
            }

            $tempDir = storage_path('app/temp');
            $readyFile = $tempDir . '/archive_' . $requestId . '.ready';
            $processingFile = $tempDir . '/archive_' . $requestId . '.processing';

            // 1. Проверяем, не готовится ли архив прямо сейчас
            if (file_exists($processingFile)) {
                return response()->json([
                    'success' => true,
                    'status' => 'processing',
                    'message' => 'Архив подготавливается...'
                ]);
            }

            // 2. Проверяем, есть ли уже готовый архив
            if (file_exists($readyFile)) {
                $readyData = json_decode(file_get_contents($readyFile), true);
                $zipPath = $readyData['path'] ?? null;
                
                // Проверяем актуальность архива (например, не старше 1 часа)
                if ($zipPath && file_exists($zipPath) && (time() - $readyData['created_at'] < 3600)) {
                    return response()->json([
                        'success' => true,
                        'status' => 'ready',
                        'download_url' => route('download-archive-file', ['requestId' => $requestId])
                    ]);
                }
                
                // Если файл пропал или устарел, удаляем маркер готовности
                @unlink($readyFile);
            }

            // 3. Запускаем создание архива в фоновом режиме
            // Используем nohup и перенаправление вывода, чтобы процесс шел в фоне независимо от PHP
            $command = "nohup php " . base_path('artisan') . " archive:create {$requestId} > /dev/null 2>&1 &";
            exec($command);

            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Запущен процесс подготовки архива...'
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка в downloadAllPhotos: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Метод для скачивания уже готового файла архива
     */
    public function downloadArchiveFile($requestId)
    {
        $readyFile = storage_path('app/temp/archive_' . $requestId . '.ready');
        
        if (!file_exists($readyFile)) {
            return abort(404, 'Архив не найден или еще не готов');
        }

        $readyData = json_decode(file_get_contents($readyFile), true);
        $zipPath = $readyData['path'];
        $zipName = $readyData['file'];

        if (!file_exists($zipPath)) {
            @unlink($readyFile);
            return abort(404, 'Файл архива физически отсутствует на сервере');
        }

        return response()->download($zipPath, $zipName);
    }
}
