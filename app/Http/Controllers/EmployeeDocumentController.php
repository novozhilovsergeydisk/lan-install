<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeDocumentController extends Controller
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
            \Log::info('== START store EmployeeDocument ==', ['request' => $request->all()]);

            // Валидация входных данных
            $validated = $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
                'document_type' => 'required|string|max:50',
                'file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:20480', // до 20MB
            ]);

            \Log::info('Validation passed', $validated);

            // Сохранение файла
            $file = $request->file('file');
            $fileName = time().'_'.$file->getClientOriginalName();
            try {
                $path = $file->storeAs('employee_documents', $fileName, 'private');
            } catch (\Exception $e) {
                \Log::error('File save error', ['error' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка сохранения файла',
                    'error' => $e->getMessage(),
                ], 500);
            }

            // Вставка в базу данных
            DB::insert('INSERT INTO employee_documents (employee_id, document_type, file_path, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [
                $validated['employee_id'],
                $validated['document_type'],
                $path,
                $user->id,
            ]);

            Log::info('== END store EmployeeDocument ==', ['employee_id' => $validated['employee_id'], 'file' => $path]);

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
            Log::error('== ERROR store EmployeeDocument ==', [
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

    public function getByEmployee($employeeId)
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

            // Получить документы сотрудника
            $documents = DB::table('employee_documents')->where('employee_id', $employeeId)->get();

            return response()->json([
                'success' => true,
                'documents' => $documents,
            ]);

        } catch (\Exception $e) {
            Log::error('== ERROR getByEmployee EmployeeDocument ==', [
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
            $document = DB::table('employee_documents')->where('id', $id)->first();
            if (! $document) {
                abort(404, 'Документ не найден.');
            }

            // Проверить существование файла
            $filePath = storage_path('app/private/'.$document->file_path);
            if (! file_exists($filePath)) {
                abort(404, 'Файл не найден.');
            }

            Log::info('== END download EmployeeDocument ==', ['id' => $id]);

            // Вернуть файл для скачивания
            return response()->download($filePath);

        } catch (\Exception $e) {
            Log::error('== ERROR download EmployeeDocument ==', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Произошла ошибка при скачивании файла');
        }
    }
}
