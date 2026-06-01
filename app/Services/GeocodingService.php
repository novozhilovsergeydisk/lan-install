<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const API_URL = 'https://cleaner.dadata.ru/api/v1/clean/address';

    private string $apiKey;
    private string $secretKey;

    public function __construct()
    {
        $this->apiKey = config('services.dadata.api_key', '');
        $this->secretKey = config('services.dadata.secret_key', '');
    }

    /**
     * Геокодирует адрес через DaData.
     *
     * @param  string  $address  Полный адрес (например: "Москва, улица Хавская, дом 5")
     * @return array|null ['latitude' => float, 'longitude' => float, 'qc_geo' => int] или null
     */
    public function geocode(string $address): ?array
    {
        if (empty($this->apiKey) || empty($this->secretKey)) {
            Log::error('GeocodingService: API keys not configured');
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Token ' . $this->apiKey,
                    'X-Secret' => $this->secretKey,
                ])
                ->post(self::API_URL, [$address]);

            if (!$response->successful()) {
                Log::warning('GeocodingService: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'address' => $address,
                ]);
                return null;
            }

            $data = $response->json();

            if (empty($data) || !isset($data[0])) {
                return null;
            }

            $result = $data[0];

            if (!isset($result['geo_lat'], $result['geo_lon'], $result['qc_geo'])) {
                return null;
            }

            // qc_geo: 0 — точные координаты дома, 1 — ближайший дом, 2 — улица.
            // Принимаем 0, 1 и 2. Отклоняем 3 (нас.пункт), 4 (город), 5 (не определены).
            if ((int) $result['qc_geo'] > 2) {
                Log::info('GeocodingService: low quality result, skipping', [
                    'address' => $address,
                    'qc_geo' => $result['qc_geo'],
                ]);
                return null;
            }

            return [
                'latitude' => (float) $result['geo_lat'],
                'longitude' => (float) $result['geo_lon'],
                'qc_geo' => (int) $result['qc_geo'],
            ];
        } catch (\Throwable $e) {
            Log::error('GeocodingService: exception', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);
            return null;
        }
    }
}
