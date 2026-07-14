<?php

namespace Tests\Unit;

use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GeocodingService теперь ходит в Yandex Geocoder HTTP API (раньше — DaData).
 * Тесты через Http::fake() — реальный API-ключ не нужен, проверяем только разбор ответа.
 */
class GeocodingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.yandex.geocoder_api_key' => 'test-key']);
    }

    private function fakeYandexResponse(string $pos, string $precision): void
    {
        Http::fake([
            'geocode-maps.yandex.ru/*' => Http::response([
                'response' => [
                    'GeoObjectCollection' => [
                        'featureMember' => [
                            [
                                'GeoObject' => [
                                    'metaDataProperty' => [
                                        'GeocoderMetaData' => ['precision' => $precision],
                                    ],
                                    'Point' => ['pos' => $pos],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_exact_precision_returns_coordinates(): void
    {
        // Yandex отдаёt "долгота широта" — Москва, ~37.61 в.д., ~55.75 с.ш.
        $this->fakeYandexResponse('37.618920 55.756994', 'exact');

        $result = app(GeocodingService::class)->geocode('Москва, Кремль');

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(55.756994, $result['latitude'], 0.0001);
        $this->assertEqualsWithDelta(37.618920, $result['longitude'], 0.0001);
        $this->assertSame(0, $result['qc_geo']);
    }

    public function test_street_precision_is_accepted(): void
    {
        $this->fakeYandexResponse('37.6 55.7', 'street');

        $result = app(GeocodingService::class)->geocode('Москва, Тверская улица');

        $this->assertNotNull($result);
        $this->assertSame(2, $result['qc_geo']);
    }

    public function test_low_quality_precision_is_rejected(): void
    {
        $this->fakeYandexResponse('37.6 55.7', 'other');

        $result = app(GeocodingService::class)->geocode('нечто невнятное');

        $this->assertNull($result);
    }

    public function test_empty_results_returns_null(): void
    {
        Http::fake([
            'geocode-maps.yandex.ru/*' => Http::response([
                'response' => ['GeoObjectCollection' => ['featureMember' => []]],
            ], 200),
        ]);

        $result = app(GeocodingService::class)->geocode('несуществующий адрес');

        $this->assertNull($result);
    }

    public function test_api_error_returns_null(): void
    {
        Http::fake([
            'geocode-maps.yandex.ru/*' => Http::response(['error' => 'Invalid key'], 403),
        ]);

        $result = app(GeocodingService::class)->geocode('Москва, Кремль');

        $this->assertNull($result);
    }

    public function test_missing_api_key_returns_null_without_request(): void
    {
        config(['services.yandex.geocoder_api_key' => '']);
        Http::fake();

        $result = app(GeocodingService::class)->geocode('Москва, Кремль');

        $this->assertNull($result);
        Http::assertNothingSent();
    }
}
