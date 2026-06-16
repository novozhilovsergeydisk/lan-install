<?php

namespace Tests\Unit;

use App\Http\Controllers\PlanningRequestController;
use App\Services\PlanningRequest\AddressMatcher;
use App\Services\PlanningRequest\ExcelRequestParser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AddressParserTest extends TestCase
{
    use DatabaseTransactions;

    public function test_parse_address_string()
    {
        // Логика разбора адреса вынесена в ExcelRequestParser (см. рефакторинг
        // импорта заявок), поэтому тестируем её там. Ведущий тип улицы парсер
        // срезает (улица/ул./… → голое название) — это часть контракта.
        $parser = new ExcelRequestParser();
        $method = new \ReflectionMethod(ExcelRequestParser::class, 'parseAddressString');
        $method->setAccessible(true);

        $testCases = [
            'город Москва, ул. Ленина, д. 1' => [
                'city_name' => 'Москва',
                'street' => 'Ленина',
                'houses' => '1',
            ],
            'город Москва, ул. Ленина, 8А' => [
                'city_name' => 'Москва',
                'street' => 'Ленина',
                'houses' => '8А',
            ],
            'город Москва, ул. Ленина, 8А, к. 2' => [
                'city_name' => 'Москва',
                'street' => 'Ленина',
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
            // Тип улицы в конце названия не срезается — остаётся как есть.
            'город Москва, Скорняжный переулок, 3, строение 2' => [
                'city_name' => 'Москва',
                'street' => 'Скорняжный переулок',
                'houses' => '3, строение 2',
            ],
            'город Москва, улица 1905 года, д. 7' => [
                'city_name' => 'Москва',
                'street' => '1905 года',
                'houses' => '7',
            ],
            'город Москва, улица 1905 года, 7' => [
                'city_name' => 'Москва',
                'street' => '1905 года',
                'houses' => '7',
            ],
            'город Москва, улица Ленина 10' => [
                'city_name' => 'Москва',
                'street' => 'Ленина',
                'houses' => '10',
            ],
            'город Москва, Зеленоград 1630' => [
                'city_name' => 'Москва',
                'street' => 'Зеленоград',
                'houses' => '1630',
            ],
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($parser, $input);
            $this->assertEquals($expected, $result, "Failed parsing: {$input}");
        }
    }

    public function test_find_or_create_address_handles_case_insensitive_cities()
    {
        $controller = new PlanningRequestController();
        $matcher = new AddressMatcher();
        $method = new \ReflectionMethod(PlanningRequestController::class, 'findOrCreateAddress');
        $method->setAccessible(true);

        // У нас в базе есть город "Москва" (id = 1)
        // Попробуем найти или создать адреса с разным регистром названия города
        $testCases = [
            ['city_name' => 'москва', 'street' => 'ул. Тестовая', 'houses' => '1'],
            ['city_name' => 'МОСКВА', 'street' => 'ул. Тестовая', 'houses' => '1'],
            ['city_name' => 'МоСкВа', 'street' => 'ул. Тестовая', 'houses' => '1'],
        ];

        foreach ($testCases as $rowData) {
            $addressId = $method->invoke($controller, $rowData, $matcher);
            $this->assertIsInt($addressId);
            $this->assertGreaterThan(0, $addressId);
        }
    }

    public function test_strip_leading_street_type()
    {
        $cases = [
            // Срезаем только «улица»/«ул.».
            'улица Люблинская' => 'Люблинская',
            'ул. Ленина' => 'Ленина',
            'улица 1905 года' => '1905 года',
            '  ул. Тверская  ' => 'Тверская',
            // Прочие типы НЕ трогаем (иначе «проспект Мира» → «ул. Мира»).
            'проспект Мира' => 'проспект Мира',
            'пр-кт Вернадского' => 'пр-кт Вернадского',
            'шоссе Энтузиастов' => 'шоссе Энтузиастов',
            'бульвар Яна Райниса' => 'бульвар Яна Райниса',
            // Тип в конце названия не трогаем.
            'Скорняжный переулок' => 'Скорняжный переулок',
            // Голое название без типа — без изменений.
            'Люблинская' => 'Люблинская',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame(
                $expected,
                AddressMatcher::stripLeadingStreetType($input),
                "Неверный срез типа улицы для: {$input}"
            );
        }
    }

    public function test_address_matcher_finds_existing_by_normalized_key()
    {
        $matcher = new AddressMatcher();

        $city = $matcher->findCity('Москва');
        $this->assertNotNull($city, 'Ожидается город "Москва" в базе');

        // Улица БЕЗ типа ("улица"/"проспект"…) — нормализация улицы тогда
        // локаленезависима (не зависит от lc_ctype и word-boundary в SQL).
        // Синтетический uniq, чтобы не пересечься с реальными адресами; тест
        // в транзакции и откатывается.
        $street = 'Матчертестовая'.str_replace('.', '', uniqid());
        $houses = '8А, корпус 1';

        $insertedId = \DB::table('addresses')->insertGetId([
            'city_id' => $city->id,
            'street' => $street,
            'district' => '',
            'houses' => $houses,
        ]);

        // Тот же адрес как есть — находим (ключевая защита от дублей).
        $this->assertSame($insertedId, $matcher->findExistingAddressId($city->id, $street, $houses));

        // Дом с другим регистром/пробелами/запятыми — нормализуется к тому же ключу.
        $this->assertSame($insertedId, $matcher->findExistingAddressId($city->id, $street, '8а,  КОРПУС  1'));

        // Другой дом — этот адрес найден быть не должен.
        $this->assertNotSame($insertedId, $matcher->findExistingAddressId($city->id, $street, '99'));
    }

    public function test_address_matcher_exact_fallback_is_locale_independent()
    {
        // Фолбэк по точному совпадению строк должен находить адрес даже когда
        // нормализация улицы в БД не срабатывает (например, lc_ctype=C и
        // кириллический тип улицы). Это держит предосмотр и запись в синхроне.
        $matcher = new AddressMatcher();
        $city = $matcher->findCity('Москва');
        $this->assertNotNull($city, 'Ожидается город "Москва" в базе');

        $street = 'улица ТипТест'.str_replace('.', '', uniqid());
        $houses = '5';

        $insertedId = \DB::table('addresses')->insertGetId([
            'city_id' => $city->id,
            'street' => $street,
            'district' => '',
            'houses' => $houses,
        ]);

        $this->assertSame($insertedId, $matcher->findExistingAddressId($city->id, $street, $houses));
    }

    public function test_parameter_columns_are_optional()
    {
        // Тип с параметрами работ, но файл содержит только базовые колонки —
        // это допустимо: параметры не создаются (монтажник заполнит позже).
        $typeIdWithParams = \DB::table('work_parameter_types')
            ->where('is_deleted', false)
            ->value('request_type_id');

        if (! $typeIdWithParams) {
            $this->markTestSkipped('В базе нет типов заявок с параметрами работ.');
        }

        $path = tempnam(sys_get_temp_dir(), 'import_').'.xlsx';
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ss->getActiveSheet()->fromArray([
            ['ГБОУ', 'Адрес организации', 'Контакт', 'Комментарии к монтажу'],
            ['Школа', 'город Москва, ул. Ленина, д. 1', 'Иванов 8(999)123-45-01', 'коммент'],
        ], null, 'A1');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        try {
            $parsed = (new ExcelRequestParser())->parse($path, (int) $typeIdWithParams);
            $this->assertCount(1, $parsed->rows);
            $this->assertSame([], $parsed->rows[0]['workParameters']);
        } finally {
            @unlink($path);
        }
    }
}
