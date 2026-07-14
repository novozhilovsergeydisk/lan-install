<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const API_URL = 'https://geocode-maps.yandex.ru/1.x/';

    // Соответствие точности Yandex (precision) условному качеству DaData (qc_geo),
    // чтобы сохранить прежний порог отсечения (принимаем 0-2, отклоняем 3+).
    private const PRECISION_TO_QC_GEO = [
        'exact' => 0,   // точные координаты дома
        'number' => 0,  // номер дома найден
        'near' => 1,    // ближайший дом
        'range' => 1,   // диапазон домов
        'street' => 2,  // только улица
        'other' => 5,   // слишком неточно
    ];

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.yandex.geocoder_api_key', '');
    }

    /**
     * Геокодирует адрес через Yandex Geocoder HTTP API.
     *
     * @param  string  $address  Полный адрес (например: "Москва, улица Хавская, дом 5")
     * @return array|null ['latitude' => float, 'longitude' => float, 'qc_geo' => int] или null
     */
    public function geocode(string $address): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('GeocodingService: Yandex API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(10)->get(self::API_URL, [
                'apikey' => $this->apiKey,
                'geocode' => $address,
                'format' => 'json',
                'lang' => 'ru_RU',
                'results' => 1,
            ]);

            if (!$response->successful()) {
                Log::warning('GeocodingService: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'address' => $address,
                ]);
                return null;
            }

            $members = $response->json('response.GeoObjectCollection.featureMember', []);

            if (empty($members)) {
                return null;
            }

            $geoObject = $members[0]['GeoObject'] ?? null;
            $pos = $geoObject['Point']['pos'] ?? null;
            $precision = $geoObject['metaDataProperty']['GeocoderMetaData']['precision'] ?? null;

            if (!$pos || !$precision) {
                return null;
            }

            // Yandex отдаёт "долгота широта" (в таком порядке!) через пробел.
            [$longitude, $latitude] = array_map('floatval', explode(' ', $pos));

            $qcGeo = self::PRECISION_TO_QC_GEO[$precision] ?? 5;

            // Принимаем exact/number/near/range/street (qc_geo 0-2). Отклоняем остальное.
            if ($qcGeo > 2) {
                Log::info('GeocodingService: low quality result, skipping', [
                    'address' => $address,
                    'precision' => $precision,
                ]);
                return null;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'qc_geo' => $qcGeo,
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
