<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WmsIntegrationController extends Controller
{
    /**
     * Получить список членов бригады для заявки (с email).
     */
    public function getBrigadeMembers($requestId)
    {
        // ... (код остается)
    }

    /**
     * Получить остатки для всей бригады.
     */
    public function getBrigadeStock($requestId)
    {
        try {
            // 1. Получаем членов бригады
            $sql = "
                SELECT e.id, e.fio, u.email
                FROM requests r
                JOIN brigades b ON b.id = r.brigade_id
                JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
                JOIN users u ON u.id = e.user_id
                WHERE r.id = ? AND e.is_deleted = false
            ";
            $members = DB::select($sql, [$requestId]);

            $apiKey = config('services.wms.api_key');
            $baseUrl = config('services.wms.base_url');

            $result = [];

            // 2. Для каждого получаем остатки
            foreach ($members as $member) {
                $stock = [];
                try {
                    $response = Http::withHeaders(['X-API-Key' => $apiKey])
                        ->timeout(5)
                        ->get("{$baseUrl}/api/external/user-stock", ['email' => $member->email]);
                    
                    if ($response->successful()) {
                        $stock = $response->json()['data'] ?? [];
                    }
                } catch (\Exception $e) {
                    Log::error("WMS: Failed to fetch stock for {$member->email}", ['error' => $e->getMessage()]);
                }

                $result[] = [
                    'fio' => $member->fio,
                    'email' => $member->email,
                    'stock' => $stock
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('WMS: Error getting brigade stock', ['error' => $e->getMessage(), 'requestId' => $requestId]);
            return response()->json(['success' => false, 'message' => 'Ошибка при получении данных склада'], 500);
        }
    }

    /**
     * Получить остатки сотрудника из API склада.
     */
    public function getUserStock(Request $request)
    {
        $email = $request->query('email');
        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email не указан'], 400);
        }

        $apiKey = config('services.wms.api_key');
        $baseUrl = config('services.wms.base_url');

        if (!$apiKey || !$baseUrl) {
            return response()->json(['success' => false, 'message' => 'Настройки API склада не найдены'], 500);
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $apiKey
            ])->get("{$baseUrl}/api/external/user-stock", [
                'email' => $email
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('WMS: Error fetching stock', [
                'status' => $response->status(),
                'body' => $response->body(),
                'email' => $email
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка склада: ' . ($response->json()['message'] ?? 'Неизвестная ошибка')
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('WMS: Exception fetching stock', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Ошибка соединения со складом'], 500);
        }
    }
}
