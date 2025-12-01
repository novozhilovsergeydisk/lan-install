<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddressDocumentController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Проверка роли пользователя - только admin
            $user = auth()->user();
            if (! $user || ! DB::table('user_roles')->join('roles', 'user_roles.role_id', '=', 'roles.id')->where('user_roles.user_id', $user->id)->where('roles.name', 'admin')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен. Требуется роль администратора.',
                ], 403);
            }

            // Логирование начала операции
            \Log::info('== START store AddressDocument ==', ['request' => $request->all()]);

            // Валидация входных данных
            $validated = $request->validate([
                'address_id' => 'required|integer|exists:addresses,id',
                'document_type' => 'required|string|max:50',
                'file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:20480', // до 20MB
            ]);

            // Получаем ID типа документа
            $documentTypeId = DB::table('document_types')->where('name', $validated['document_type'])->value('id');
            if (! $documentTypeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Тип документа не найден',
                ], 400);
            }

            \Log::info('Validation passed', $validated);

            // Сохранение файла
            $file = $request->file('file');
            $fileName = time().'_'.$file->getClientOriginalName();
            try {
                $path = $file->storeAs('address_documents', $fileName, 'private');
            } catch (\Exception $e) {
                \Log::error('File save error', ['error' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка сохранения файла',
                    'error' => $e->getMessage(),
                ], 500);
            }

            // Вставка в базу данных
            DB::insert('INSERT INTO addresses_documents (address_id, document_type_id, file_path, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [
                $validated['address_id'],
                $documentTypeId,
                $path,
                $user->id,
            ]);

            Log::info('== END store AddressDocument ==', ['address_id' => $validated['address_id'], 'file' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Файл успешно загружен.',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error', ['errors' => $e->errors()]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('== ERROR store AddressDocument ==', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при загрузке файла',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getByAddress($addressId)
    {
        try {
            // Проверка роли пользователя - только admin
            $user = auth()->user();
            if (! $user || ! DB::table('user_roles')->join('roles', 'user_roles.role_id', '=', 'roles.id')->where('user_roles.user_id', $user->id)->where('roles.name', 'admin')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен. Требуется роль администратора.',
                ], 403);
            }

            // Получить документы адреса с типами
            $documents = DB::table('addresses_documents')
                ->leftJoin('document_types', 'addresses_documents.document_type_id', '=', 'document_types.id')
                ->where('addresses_documents.address_id', $addressId)
                ->select('addresses_documents.*', 'document_types.name as document_type')
                ->get();

            return response()->json([
                'success' => true,
                'documents' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('== ERROR getByAddress AddressDocument ==', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении документов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function download($id)
    {
        try {
            // Проверка роли пользователя - только admin
            $user = auth()->user();
            if (! $user || ! DB::table('user_roles')->join('roles', 'user_roles.role_id', '=', 'roles.id')->where('user_roles.user_id', $user->id)->where('roles.name', 'admin')->exists()) {
                abort(403, 'Доступ запрещен. Требуется роль администратора.');
            }

            // Получить документ
            $document = DB::table('addresses_documents')->where('id', $id)->first();
            if (! $document) {
                abort(404, 'Документ не найден.');
            }

            // Проверить существование файла
            $filePath = storage_path('app/private/'.$document->file_path);
            if (! file_exists($filePath)) {
                abort(404, 'Файл не найден.');
            }

            Log::info('== END download AddressDocument ==', ['id' => $id]);

            // Вернуть файл для скачивания
            return response()->download($filePath);

        } catch (\Exception $e) {
            Log::error('== ERROR download AddressDocument ==', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Произошла ошибка при скачивании файла');
        }
    }
}
