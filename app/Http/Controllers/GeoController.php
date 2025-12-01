<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GeoController extends Controller
{
    public function getAddressesYandex()
    {
        try {
            // view
            return view('geo.addresses-yandex');
        } catch (\Exception $e) {
            Log::error('Error in GeoController@getAddressesYandex: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при загрузке страницы с картой',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAddress($id)
    {
        try {
            $address = DB::selectOne('
            SELECT
                a.id,
                a.street,
                a.houses,
                a.district,
                a.responsible_person,
                a.comments,
                a.latitude,
                a.longitude,
                c.name as city_name,
                c.id as city_id,
                r.id as region_id
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            LEFT JOIN regions r ON c.region_id = r.id
            WHERE a.id = ?
        ', [$id]);

            if (! $address) {
                return response()->json(['success' => false, 'message' => 'Address not found'], 404);
            }

            // Получить документы адреса
            $documents = DB::table('addresses_documents')->where('address_id', $id)->get();

            return response()->json(['success' => true, 'data' => $address, 'documents' => $documents]);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@getAddress: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении адреса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAddressesPaginated(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 30);
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $perPage;

            // Get total count
            $total = DB::selectOne('SELECT COUNT(*) as count FROM addresses')->count;

            // Get paginated data
            $data = DB::select('
            SELECT 
                a.id, 
                a.street, 
                a.houses, 
                a.district, 
                a.responsible_person,
                a.comments,
                a.latitude,
                a.longitude,
                c.name as city_name,
                c.id as city_id,
                r.id as region_id
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            LEFT JOIN regions r ON c.region_id = r.id
            ORDER BY c.name, a.street, a.houses
            LIMIT ? OFFSET ?
        ', [$perPage, $offset]);

            return response()->json([
                'data' => $data,
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => ceil($total / $perPage),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@getAddressesPaginated: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении адресов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAddresses()
    {
        try {
            $data = DB::select('
            SELECT 
                a.id, 
                a.street, 
                a.houses, 
                a.district, 
                a.responsible_person,
                a.comments,
                a.latitude,
                a.longitude,
                c.name as city, 
                r.name as region
            FROM addresses a
            JOIN cities c ON a.city_id = c.id
            LEFT JOIN regions r ON c.region_id = r.id
            ORDER BY c.name, a.street, a.houses
        ');

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@getAddresses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении адресов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCities()
    {
        try {
            $cities = DB::select('SELECT id, name FROM cities ORDER BY name');

            return response()->json($cities);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@getCities: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка городов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRegions()
    {
        try {
            $regions = DB::select('SELECT id, name FROM regions ORDER BY name');

            return response()->json($regions);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@getRegions: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка регионов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateAddress(Request $request, $id)
    {
        $validated = $request->validate([
            'street' => 'required|string|max:255',
            'houses' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',
            'responsible_person' => 'nullable|string|max:100',
            'comments' => 'nullable|string',
            'latitudeEdit' => [
                'nullable',
                'numeric',
                'between:-90,90',
                'regex:/^-?(90(\.0{1,7})?|([0-8]?[0-9])(\.\d{1,7})?)$/',
            ],
            'longitudeEdit' => [
                'nullable',
                'numeric',
                'between:-180,180',
                'regex:/^-?(180(\.0{1,7})?|(1[0-7][0-9]|[0-9]?[0-9])(\.\d{1,7})?)$/',
            ],
        ], [
            'latitudeEdit.regex' => 'Некорректный формат широты. Допустимый формат: от -90 до 90, до 7 знаков после точки',
            'longitudeEdit.regex' => 'Некорректный формат долготы. Допустимый формат: от -180 до 180, до 7 знаков после точки',
            'latitudeEdit.between' => 'Широта должна быть в диапазоне от -90 до 90 градусов',
            'longitudeEdit.between' => 'Долгота должна быть в диапазоне от -180 до 180 градусов',
        ]);

        try {
            // Проверяем, что если одно поле координат заполнено, то и второе тоже
            if ((isset($validated['latitudeEdit']) && ! isset($validated['longitudeEdit'])) ||
                (! isset($validated['latitudeEdit']) && isset($validated['longitudeEdit']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Необходимо заполнить оба поля координат',
                ], 422);
            }

            $data = [
                'id' => $id,
                'street' => $validated['street'],
                'houses' => $validated['houses'],
                'district' => $validated['district'],
                'city_id' => $validated['city_id'],
                'responsible_person' => $validated['responsible_person'] ?? null,
                'comments' => $validated['comments'] ?? null,
                'latitude' => isset($validated['latitudeEdit']) ? (float) $validated['latitudeEdit'] : null,
                'longitude' => isset($validated['longitudeEdit']) ? (float) $validated['longitudeEdit'] : null,
            ];

            $updated = DB::update(
                'UPDATE addresses SET 
                    street = :street,
                    houses = :houses,
                    district = :district,
                    city_id = :city_id,
                    responsible_person = :responsible_person,
                    comments = :comments,
                    latitude = :latitude,
                    longitude = :longitude
                WHERE id = :id',
                $data
            );

            if ($updated) {
                // Получаем обновленный адрес с названием города
                $address = DB::table('addresses')
                    ->select('addresses.*', 'cities.name as city_name')
                    ->leftJoin('cities', 'addresses.city_id', '=', 'cities.id')
                    ->where('addresses.id', $id)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Адрес успешно обновлен',
                    'data' => $address,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить адрес',
            ], 500);

        } catch (\Exception $e) {
            \Log::error('Ошибка при обновлении адреса: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении адреса: '.$e->getMessage(),
            ], 500);
        }
    }

    public function addAddress(Request $request)
    {
        \Log::info('=== START addAddress ===', []);

        try {
            $validated = $request->validate([
                'street' => 'required|string|max:255',
                'houses' => 'required|string|max:255',
                'district' => 'required|string|max:255',
                'city_id' => 'required|exists:cities,id',
                'responsible_person' => 'nullable|string|max:255',
                'comments' => 'nullable|string',
                'latitude' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                    'regex:/^-?(90(\.0{1,7})?|([0-8]?[0-9])(\.\d{1,7})?)$/',
                ],
                'longitude' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                    'regex:/^-?(180(\.0{1,7})?|(1[0-7][0-9]|[0-9]?[0-9])(\.\d{1,7})?)$/',
                ],
            ], [
                'latitude.regex' => 'Некорректный формат широты. Допустимый формат: от -90 до 90, до 7 знаков после точки',
                'longitude.regex' => 'Некорректный формат долготы. Допустимый формат: от -180 до 180, до 7 знаков после точки',
                'latitude.between' => 'Широта должна быть в диапазоне от -90 до 90 градусов',
                'longitude.between' => 'Долгота должна быть в диапазоне от -180 до 180 градусов',
            ]);

            \Log::info('=== Все входные данные ===', $validated);

            // Проверка, что если одно поле координат заполнено, то и второе тоже
            if (($request->has('latitude') && ! $request->has('longitude')) ||
                (! $request->has('latitude') && $request->has('longitude'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Необходимо заполнить оба поля координат',
                ], 422);
            }

            // Добавляем адрес и получаем его ID
            $addressData = [
                'street' => $request->street,
                'houses' => $request->houses,
                'district' => $request->district,
                'city_id' => $request->city_id,
                'responsible_person' => $request->responsible_person,
                'comments' => $request->comments,
            ];

            // Добавляем координаты, если они есть
            if ($request->has('latitude') && $request->has('longitude')) {
                $addressData['latitude'] = $request->latitude;
                $addressData['longitude'] = $request->longitude;
            }

            DB::beginTransaction();
            $addressId = DB::table('addresses')->insertGetId($addressData);

            // Сохраняем документы, если они были загружены
            if ($request->hasFile('document')) {
                $files = $request->file('document');
                if (! is_array($files)) {
                    $files = [$files]; // Если один файл, делаем массив
                }

                // Получаем ID типа документа "Проект БТИ"
                $documentTypeId = DB::table('document_types')->where('name', 'Проект БТИ')->value('id');

                foreach ($files as $file) {
                    $fileName = time().'_'.$file->getClientOriginalName();
                    try {
                        $path = $file->storeAs('address_documents', $fileName, 'private');
                        \Log::info('Inserting document', [
                            'address_id' => $addressId,
                            'document_type_id' => $documentTypeId,
                            'uploaded_by' => auth()->id(),
                            'path' => $path,
                        ]);
                        DB::table('addresses_documents')->insert([
                            'address_id' => $addressId,
                            'document_type_id' => $documentTypeId,
                            'file_path' => $path,
                            'uploaded_by' => auth()->id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        \Log::info('Document inserted successfully');
                    } catch (\Exception $e) {
                        \Log::error('File save error in addAddress', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                        // Не прерываем создание адреса из-за ошибки с файлом
                    }
                }
            }

            DB::commit();

            // Получаем информацию о городе
            $city = DB::table('cities')->where('id', $request->city_id)->first();
            $cityName = $city ? $city->name : '';

            // Создаем объект с информацией о добавленном адресе
            $addressInfo = [
                'id' => $addressId,
                'city' => $cityName,
                'district' => $request->district,
                'street' => $request->street,
                'houses' => $request->houses,
                'responsible_person' => $request->responsible_person,
                'comments' => $request->comments,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ];

            \Log::info('=== Все выходные данные ===', $addressInfo);
            \Log::info('=== END addAddress ===', []);

            return response()->json([
                'success' => true,
                'message' => 'Адрес добавлен',
                'address' => $addressInfo,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::info('=== END addAddress ===', []);
            \Log::info('=== START error addAddress ===', []);
            \Log::error('Ошибка при добавлении адреса: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->all(),
            ]);
            \Log::info('=== END error addAddress ===', []);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при добавлении адреса: '.$e->getMessage(),
            ], 500);
        }
    }

    public function addCity(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'region_id' => 'required|exists:regions,id',
            ]);

            DB::insert('INSERT INTO cities (name, region_id) VALUES (?, ?)', [
                $request->name,
                $request->region_id,
            ]);

            return response()->json(['success' => true, 'message' => 'Город добавлен']);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@addCity: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при добавлении города',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function addRegion(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            DB::insert('INSERT INTO regions (name) VALUES (?)', [
                $request->name,
            ]);

            return response()->json(['success' => true, 'message' => 'Регион добавлен']);
        } catch (\Exception $e) {
            Log::error('Error in GeoController@addRegion: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при добавлении региона',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAddress($id)
    {
        try {
            // Проверяем существование адреса
            $address = DB::table('addresses')->where('id', $id)->first();

            if (! $address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Адрес не найден',
                ], 404);
            }

            // Проверяем, есть ли ссылки на этот адрес в request_addresses
            $hasReferences = DB::table('request_addresses')
                ->where('address_id', $id)
                ->exists();

            if ($hasReferences) {
                return response()->json([
                    'success' => false,
                    'message' => 'Невозможно удалить адрес: существуют связанные заявки',
                ], 400);
            }

            // Удаляем адрес
            $deleted = DB::table('addresses')->where('id', $id)->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Адрес успешно удален',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось удалить адрес',
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Ошибка при удалении адреса: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при удалении адреса: '.$e->getMessage(),
            ], 500);
        }
    }
}
