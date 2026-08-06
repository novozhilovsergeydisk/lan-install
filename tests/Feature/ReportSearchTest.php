<?php

namespace Tests\Feature;

use App\Exports\RequestsReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Блок 4 ТЗ: сквозной поиск в отчётах — по имени/организации контакта,
 * телефону (с очисткой от маски) и тексту комментариев.
 *
 * Проверяем оба варианта реализации в ReportController: методы на Query
 * Builder (getAllPeriodByEmployee и т.п.) и методы на сыром SQL
 * ($sqlBase-паттерн, getAllPeriodByAddress/getAllPeriod) — у них разные
 * приватные хелперы (applySearchFilter / buildSearchSql).
 */
class ReportSearchTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    private function authenticateAdmin(): User
    {
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (! $admin) {
            $this->markTestSkipped('Admin user not found');
        }
        $admin->roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $admin->id)
            ->pluck('roles.name')->toArray();
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * Создаёт заявку с уникальным клиентом (fio/phone/organization) и,
     * опционально, комментарием — чтобы искать можно было по любому полю.
     */
    private function createSearchableRequest(array $client, ?string $comment = null): array
    {
        $address = DB::table('addresses')->first();
        if (! $address) {
            $this->markTestSkipped('No address found');
        }

        $requestTypeId = DB::table('request_types')->first()->id ?? 1;
        $employeeId = DB::table('employees')->first()->id ?? 1;

        $clientId = DB::table('clients')->insertGetId(array_merge([
            'fio' => null,
            'phone' => null,
            'organization' => null,
        ], $client));

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-SEARCH-'.uniqid(),
            'client_id' => $clientId,
            'request_type_id' => $requestTypeId,
            'status_id' => 4,
            'operator_id' => $employeeId,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ]);

        DB::table('request_addresses')->insert([
            'request_id' => $requestId,
            'address_id' => $address->id,
        ]);

        if ($comment !== null) {
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $comment,
                'created_at' => now(),
            ]);
            DB::table('request_comments')->insert([
                'request_id' => $requestId,
                'comment_id' => $commentId,
                'created_at' => now(),
                'is_closing' => false,
            ]);
        }

        return [$requestId, $address->id];
    }

    /**
     * Как createSearchableRequest, но заявка ещё и назначена на бригаду с указанным
     * сотрудником — нужно для 4 методов, фильтрующих по employeeId (Query Builder
     * с GROUP BY + STRING_AGG(brigade_members), самый рискованный путь: там
     * search_match_* обёрнуты в bool_or(), а не голые boolean-выражения).
     */
    private function createSearchableRequestWithBrigade(array $client, ?string $comment = null): array
    {
        [$requestId, $addressId] = $this->createSearchableRequest($client, $comment);

        $employeeId = DB::table('employees')->first()->id ?? 1;
        $brigadeId = DB::table('brigades')->insertGetId([
            'name' => 'TEST-SEARCH-BRIGADE-'.uniqid(),
            'leader_id' => $employeeId,
            'formation_date' => now()->toDateString(),
            'is_deleted' => false,
        ]);
        DB::table('requests')->where('id', $requestId)->update(['brigade_id' => $brigadeId]);

        return [$requestId, $addressId, $employeeId];
    }

    /**
     * Проверяет, что в ответе есть search_match_fio/organization/phone/comment
     * с ожидаемыми значениями — работает и для сырых-SQL, и для Query Builder
     * ответов (Laravel отдаёт boolean из Postgres как PHP bool через оба пути).
     */
    private function assertMatchReasons(array $row, array $expected): void
    {
        foreach (['fio', 'organization', 'phone', 'comment'] as $field) {
            $key = 'search_match_'.$field;
            $this->assertArrayHasKey($key, $row, "Поле {$key} должно быть в ответе");
            $this->assertSame(
                $expected[$field] ?? false,
                (bool) $row[$key],
                "Неверное значение {$key}: ожидали ".var_export($expected[$field] ?? false, true)
            );
        }
    }

    public function test_all_period_search_finds_by_client_fio()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(['fio' => 'Уникальный Заказчик Тестовый']);

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => 'Уникальный Заказчик',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertTrue($ids->contains($requestId), 'Заявка должна находиться по ФИО контакта');
    }

    public function test_all_period_search_finds_by_phone_ignoring_mask()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(['phone' => '+7 (915) 123-45-67']);

        // Ищем без маски — сервер должен сам снять форматирование с обеих сторон.
        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => '89151234567',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertTrue($ids->contains($requestId), 'Заявка должна находиться по телефону независимо от маски');
    }

    public function test_short_phone_prefix_search_ignores_leading_country_code()
    {
        // Регресс: заказчик заметил на живой проверке (05.08.2026), что поиск
        // "+7 900" и "8 900" по номеру, ДЕЙСТВИТЕЛЬНО начинающемуся с кода 900
        // ("+7 900 123-45-67"), давал РАЗНЫЕ результаты — "+7 900" не находил его,
        // хотя "8 900" находил. Ведущую 7/8 нужно снимать всегда, а не только при
        // поиске полным номером (10+ цифр).
        //
        // Телефон для теста подобран так, чтобы код 900 был РЕАЛЬНЫМ префиксом
        // номера, а не случайным совпадением где-то в середине — иначе тест не
        // отличил бы правильную привязку к началу/концу от старого сравнения
        // «подстрока где угодно», которое заказчик и поймал на скриншоте.
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(['phone' => '+7 (900) 123-45-67']);

        $withEight = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => '8 900',
        ])->json('requestsAllPeriod');

        $withPlusSeven = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => '+7 900',
        ])->json('requestsAllPeriod');

        $this->assertTrue(collect($withEight)->pluck('id')->contains($requestId), 'Поиск "8 900" должен находить номер');
        $this->assertTrue(collect($withPlusSeven)->pluck('id')->contains($requestId), 'Поиск "+7 900" должен находить тот же номер, что и "8 900"');
    }

    public function test_short_phone_fragment_does_not_match_coincidental_middle_substring()
    {
        // Регресс: заказчик поймал на живой проверке (05.08.2026) — поиск "-7 900"
        // (по сути тот же код "900") находил заявки с телефонами 89990011922 и
        // 89161249004, хотя ни один из них не начинается и не заканчивается на
        // "900" — цифры просто случайно встретились в середине номера. Раньше
        // телефон матчился как LIKE '%900%' (подстрока где угодно); теперь —
        // только с начала или с конца хвоста номера.
        $this->authenticateAdmin();
        [$noiseId] = $this->createSearchableRequest(['fio' => 'Шумовой Клиент 900', 'phone' => '+7 (999) 001-19-22']);

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => '8 900',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertFalse($ids->contains($noiseId), 'Телефон со случайным "900" в середине не должен находиться');
    }

    public function test_all_period_search_finds_by_comment_text()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(
            ['fio' => 'Некто Обычный'],
            'Смонтировали панели, выполнено частично 37/50'
        );

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => 'выполнено частично',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertTrue($ids->contains($requestId), 'Заявка должна находиться по тексту комментария');
    }

    public function test_all_period_search_excludes_non_matching_requests()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(['fio' => 'Совершенно Другой Клиент']);

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => 'СтрокаКотораяТочноНичегоНеНайдёт12345',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertFalse($ids->contains($requestId), 'Не подходящая под поиск заявка не должна попадать в выдачу');
    }

    public function test_all_period_search_pagination_total_matches_filtered_count()
    {
        $this->authenticateAdmin();
        $marker = 'МАРКЕР-'.uniqid();
        [$requestId] = $this->createSearchableRequest(['fio' => $marker]);

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => $marker,
            'page' => 1,
            'limit' => 20,
        ]);

        $response->assertStatus(200);
        // Счётчик total считается ДО LIMIT/OFFSET тем же WHERE — если фильтр применён
        // не в обоих местах, total разъедется с фактической выдачей (регресс пагинации).
        $this->assertSame(1, $response->json('total'), 'total должен учитывать фильтр поиска');
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertTrue($ids->contains($requestId));
    }

    public function test_digits_inside_plain_text_do_not_leak_into_phone_search()
    {
        // Регресс: цифры внутри обычного текста («выполнено 37/50») не должны
        // трактоваться как фрагмент телефона — иначе матчились бы случайные
        // номера, где эти цифры просто встречаются (напр. код оператора +7 999...).
        $this->authenticateAdmin();
        $matchingComment = 'Смонтировано частично 37 из 50, регламент 999';
        [$matchingId] = $this->createSearchableRequest(['fio' => 'Клиент С Отчётом'], $matchingComment);

        // Другая заявка с телефоном, СЛУЧАЙНО содержащим те же цифры "999" —
        // не должна попасть в выдачу по этому запросу (раньше попадала).
        [$phoneNoiseId] = $this->createSearchableRequest(['fio' => 'Шумовой Клиент', 'phone' => '+7 (999) 000-00-00']);

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => 'регламент 999',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsAllPeriod'))->pluck('id');
        $this->assertTrue($ids->contains($matchingId), 'Заявка с этим текстом в комментарии должна находиться');
        $this->assertFalse($ids->contains($phoneNoiseId), 'Заявка с посторонним телефоном, случайно содержащим те же цифры, не должна находиться');
    }

    public function test_by_address_all_period_search_raw_sql_variant()
    {
        $this->authenticateAdmin();
        [$requestId, $addressId] = $this->createSearchableRequest(['organization' => 'ООО Уникальная Организация']);

        $response = $this->postJson('/reports/requests/by-address-all-period', [
            'addressId' => $addressId,
            'search' => 'Уникальная Организация',
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsByAddressAndDateRange'))->pluck('id');
        $this->assertTrue($ids->contains($requestId), 'Поиск должен работать и в методе на сыром SQL (getAllPeriodByAddress)');
    }

    public function test_export_respects_search_filter()
    {
        $this->authenticateAdmin();
        $marker = 'ЭКСПОРТ-МАРКЕР-'.uniqid();
        [$matchingId] = $this->createSearchableRequest(['fio' => $marker]);
        [$otherId] = $this->createSearchableRequest(['fio' => 'Не должен попасть в выгрузку']);

        $export = new RequestsReportExport(['allPeriod' => true, 'search' => $marker]);
        $data = $export->collection();

        $this->assertNotNull($data->first(fn ($row) => $row->id == $matchingId), 'Подходящая заявка должна быть в выгрузке');
        $this->assertNull($data->first(fn ($row) => $row->id == $otherId), 'Не подходящая заявка не должна попасть в выгрузку с поиском');
    }

    public function test_empty_search_does_not_change_total_count()
    {
        // Проверяем через total (COUNT(*) с тем же WHERE), а не через наличие
        // конкретной заявки на первой странице: локальная БД — копия прода
        // (тысячи заявок), и не связанный с поиском регресс пагинации сделал бы
        // тест случайно нестабильным. total же сравнивать безопасно и точно.
        $this->authenticateAdmin();

        $withoutSearch = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'page' => 1,
            'limit' => 1,
        ])->json('total');

        $withEmptySearch = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => '',
            'page' => 1,
            'limit' => 1,
        ])->json('total');

        $this->assertSame($withoutSearch, $withEmptySearch, 'Пустая строка поиска не должна менять общее количество записей в отчёте');
    }

    // ==========================================================================
    // Бейджи «Найдено по: …» — search_match_fio/organization/phone/comment.
    // По ТЗ заказчика (05.08.2026): показывать причину совпадения в UI, а не
    // просто молча находить заявку — чтобы монтажнику/заказчику было понятно,
    // почему заявка попала в выдачу.
    // ==========================================================================

    public function test_match_reasons_all_period_raw_sql_with_pagination()
    {
        // getAllPeriod: сырой SQL, $sqlBase-паттерн, с пагинацией.
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(
            ['fio' => 'Причина Имя Тест', 'organization' => 'ООО Причина Орг'],
            'комментарий без совпадения'
        );

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => 'Причина Имя',
        ]);

        $row = collect($response->json('requestsAllPeriod'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['fio' => true]);
    }

    public function test_match_reasons_all_period_by_phone()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(['phone' => '+7 (911) 222-33-44']);

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => '89112223344',
        ]);

        $row = collect($response->json('requestsAllPeriod'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['phone' => true]);
    }

    public function test_match_reasons_all_period_by_comment()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(
            ['fio' => 'Без совпадения по имени'],
            'редкая фраза причина-комментарий-42'
        );

        $response = $this->postJson('/reports/requests/all-period', [
            'allPeriod' => true,
            'search' => 'причина-комментарий-42',
        ]);

        $row = collect($response->json('requestsAllPeriod'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['comment' => true]);
    }

    public function test_match_reasons_by_address_all_period_raw_sql()
    {
        // getAllPeriodByAddress: сырой SQL, $sqlBase-паттерн, с пагинацией.
        $this->authenticateAdmin();
        [$requestId, $addressId] = $this->createSearchableRequest(['organization' => 'ООО Причина По Адресу']);

        $response = $this->postJson('/reports/requests/by-address-all-period', [
            'addressId' => $addressId,
            'search' => 'Причина По Адресу',
        ]);

        $row = collect($response->json('requestsByAddressAndDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['organization' => true]);
    }

    public function test_match_reasons_by_address_date_raw_sql_no_pagination()
    {
        // getRequestsByAddressAndDateRange: сырой SQL, без пагинации.
        $this->authenticateAdmin();
        [$requestId, $addressId] = $this->createSearchableRequest(['fio' => 'Причина По Адресу И Дате']);

        $response = $this->postJson('/reports/requests/by-address-date', [
            'addressId' => $addressId,
            'startDate' => now()->format('d.m.Y'),
            'endDate' => now()->format('d.m.Y'),
            'search' => 'По Адресу И Дате',
        ]);

        $row = collect($response->json('requestsByAddressAndDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['fio' => true]);
    }

    public function test_match_reasons_by_date_raw_sql_no_pagination()
    {
        // getRequestsByDateRange: сырой SQL, без пагинации.
        $this->authenticateAdmin();
        [$requestId] = $this->createSearchableRequest(['fio' => 'Причина По Дате Одной']);

        $response = $this->postJson('/reports/requests/by-date', [
            'startDate' => now()->format('d.m.Y'),
            'endDate' => now()->format('d.m.Y'),
            'search' => 'По Дате Одной',
        ]);

        $row = collect($response->json('requestsByDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['fio' => true]);
    }

    public function test_match_reasons_by_employee_all_period_group_by_bool_or()
    {
        // getAllPeriodByEmployee: Query Builder + GROUP BY (STRING_AGG brigade_members) +
        // пагинация. Самый рискованный путь — search_match_* обёрнуты в bool_or(),
        // а не голые boolean, иначе Postgres потребовал бы добавить их в GROUP BY.
        $this->authenticateAdmin();
        [$requestId, , $employeeId] = $this->createSearchableRequestWithBrigade(['fio' => 'Причина У Сотрудника']);

        $response = $this->postJson('/reports/requests/by-employee-all-period', [
            'employeeId' => $employeeId,
            'allPeriod' => true,
            'search' => 'У Сотрудника',
        ]);

        $row = collect($response->json('requestsAllPeriodByEmployee'))->firstWhere('id', $requestId);
        $this->assertNotNull($row, 'Заявка должна найтись по сотруднику+поиску');
        $this->assertMatchReasons($row, ['fio' => true]);
    }

    public function test_match_reasons_by_employee_address_all_period_group_by_bool_or()
    {
        // getAllPeriodByEmployeeAndAddress: Query Builder + GROUP BY + пагинация.
        $this->authenticateAdmin();
        [$requestId, $addressId, $employeeId] = $this->createSearchableRequestWithBrigade(['organization' => 'ООО Причина Сотр Адрес']);

        $response = $this->postJson('/reports/requests/by-employee-address-all-period', [
            'employeeId' => $employeeId,
            'addressId' => $addressId,
            'search' => 'Причина Сотр Адрес',
        ]);

        $row = collect($response->json('requestsByAddressAndDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['organization' => true]);
    }

    public function test_match_reasons_by_employee_date_group_by_bool_or_no_pagination()
    {
        // getRequestsByEmployeeAndDateRange: Query Builder + GROUP BY, без пагинации.
        $this->authenticateAdmin();
        [$requestId, , $employeeId] = $this->createSearchableRequestWithBrigade(
            ['fio' => 'Без совпадения'],
            'причина-по-датам-сотрудника-77'
        );

        $response = $this->postJson('/reports/requests/by-employee-date', [
            'employeeId' => $employeeId,
            'startDate' => now()->format('d.m.Y'),
            'endDate' => now()->format('d.m.Y'),
            'search' => 'причина-по-датам-сотрудника-77',
        ]);

        $row = collect($response->json('requestsByEmployeeAndDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['comment' => true]);
    }

    public function test_match_reasons_by_employee_address_date_group_by_bool_or_no_pagination()
    {
        // getRequestsByEmployeeAddressAndDateRange: Query Builder + GROUP BY, без пагинации.
        $this->authenticateAdmin();
        [$requestId, $addressId, $employeeId] = $this->createSearchableRequestWithBrigade(['phone' => '+7 (933) 444-55-66']);

        $response = $this->postJson('/reports/requests/by-employee-address-date', [
            'employeeId' => $employeeId,
            'addressId' => $addressId,
            'startDate' => now()->format('d.m.Y'),
            'endDate' => now()->format('d.m.Y'),
            'search' => '89334445566',
        ]);

        $row = collect($response->json('requestsByAddressAndDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertMatchReasons($row, ['phone' => true]);
    }

    public function test_no_match_reasons_when_search_is_empty()
    {
        // Без поиска search_match_* должны быть false (или отсутствовать) — бейджи
        // на фронте не должны рисоваться, когда фильтр не применялся.
        //
        // Намеренно НЕ используем /all-period без доп. фильтров: локальная БД —
        // копия прода (тысячи заявок), и без сужающего фильтра наша тестовая запись
        // может не попасть в первую страницу — не баг поиска, а нестабильный тест
        // (см. test_empty_search_does_not_change_total_count выше). И намеренно НЕ
        // /by-date: у него давняя, не связанная с блоком 4 проблема — тянет ВСЕ
        // комментарии проекта без фильтра (см. BACKLOG.md), из-за чего в общем
        // прогоне тестов вместе с остальными упирались в лимит памяти PHPUnit.
        // /by-address-date уже сужен нашим тестовым адресом — и быстрый, и стабильный.
        $this->authenticateAdmin();
        [$requestId, $addressId] = $this->createSearchableRequest(['fio' => 'Заявка Без Поиска']);

        $response = $this->postJson('/reports/requests/by-address-date', [
            'addressId' => $addressId,
            'startDate' => now()->format('d.m.Y'),
            'endDate' => now()->format('d.m.Y'),
        ]);

        $row = collect($response->json('requestsByAddressAndDateRange'))->firstWhere('id', $requestId);
        $this->assertNotNull($row);
        $this->assertFalse((bool) ($row['search_match_fio'] ?? false));
        $this->assertFalse((bool) ($row['search_match_phone'] ?? false));
    }
}
