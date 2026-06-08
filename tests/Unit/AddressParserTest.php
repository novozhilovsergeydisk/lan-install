<?php

namespace Tests\Unit;

use App\Http\Controllers\PlanningRequestController;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AddressParserTest extends TestCase
{
    use DatabaseTransactions;
    public function test_parse_address_string()
    {
        $controller = new PlanningRequestController();
        $reflection = new \ReflectionClass(PlanningRequestController::class);
        $method = $reflection->getMethod('parseAddressString');
        $method->setAccessible(true);

        $testCases = [
            'город Москва, ул. Ленина, д. 1' => [
                'city_name' => 'Москва',
                'street' => 'ул. Ленина',
                'houses' => '1',
            ],
            'город Москва, ул. Ленина, 8А' => [
                'city_name' => 'Москва',
                'street' => 'ул. Ленина',
                'houses' => '8А',
            ],
            'город Москва, ул. Ленина, 8А, к. 2' => [
                'city_name' => 'Москва',
                'street' => 'ул. Ленина',
                'houses' => '8А, к. 2',
            ],
            'город Москва, Зеленоград, корпус 1630' => [
                'city_name' => 'Москва',
                'street' => 'Зеленоград',
                'houses' => 'корпус 1630',
            ],
            'город Москва, Зеленоград, 1630' => [
                'city_name' => 'Москва',
                'street' => 'Зеленоград',
                'houses' => '1630',
            ],
            'город Москва, Скорняжный переулок, 3, строение 2' => [
                'city_name' => 'Москва',
                'street' => 'Скорняжный переулок',
                'houses' => '3, строение 2',
            ],
            'город Москва, улица 1905 года, д. 7' => [
                'city_name' => 'Москва',
                'street' => 'улица 1905 года',
                'houses' => '7',
            ],
            'город Москва, улица 1905 года, 7' => [
                'city_name' => 'Москва',
                'street' => 'улица 1905 года',
                'houses' => '7',
            ],
            'город Москва, улица Ленина 10' => [
                'city_name' => 'Москва',
                'street' => 'улица Ленина',
                'houses' => '10',
            ],
            'город Москва, Зеленоград 1630' => [
                'city_name' => 'Москва',
                'street' => 'Зеленоград',
                'houses' => '1630',
            ],
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($controller, $input);
            $this->assertEquals($expected, $result, "Failed parsing: {$input}");
        }
    }

    public function test_find_or_create_address_handles_case_insensitive_cities()
    {
        $controller = new PlanningRequestController();
        $reflection = new \ReflectionClass(PlanningRequestController::class);
        $method = $reflection->getMethod('findOrCreateAddress');
        $method->setAccessible(true);

        // У нас в базе есть город "Москва" (id = 1)
        // Попробуем найти или создать адреса с разным регистром названия города
        $testCases = [
            ['city_name' => 'москва', 'street' => 'ул. Тестовая', 'houses' => '1'],
            ['city_name' => 'МОСКВА', 'street' => 'ул. Тестовая', 'houses' => '1'],
            ['city_name' => 'МоСкВа', 'street' => 'ул. Тестовая', 'houses' => '1'],
        ];

        foreach ($testCases as $rowData) {
            $addressId = $method->invoke($controller, $rowData);
            $this->assertIsInt($addressId);
            $this->assertGreaterThan(0, $addressId);
        }
    }
}
