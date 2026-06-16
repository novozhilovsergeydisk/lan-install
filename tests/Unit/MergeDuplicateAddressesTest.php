<?php

namespace Tests\Unit;

use App\Console\Commands\MergeDuplicateAddresses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Слияние дублей адресов: ссылки перепривязываются на канон, дубли удаляются,
 * конфликт PK (request_id, address_id) корректно снимается.
 */
class MergeDuplicateAddressesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_merge_into_repoints_requests_and_deletes_duplicates(): void
    {
        $cityId = DB::table('cities')->min('id');
        $requestIds = DB::table('requests')->orderBy('id')->limit(2)->pluck('id')->all();
        if ($cityId === null || count($requestIds) < 2) {
            $this->markTestSkipped('Недостаточно данных (город/заявки) для теста.');
        }
        [$r1, $r2] = $requestIds;

        $street = 'СлияниеТест'.str_replace('.', '', uniqid());

        $canonId = DB::table('addresses')->insertGetId([
            'city_id' => $cityId, 'street' => $street, 'district' => 'Район', 'houses' => '1',
            'latitude' => 55.75, 'longitude' => 37.61,
        ]);
        $dupId = DB::table('addresses')->insertGetId([
            'city_id' => $cityId, 'street' => $street, 'district' => '', 'houses' => '1',
        ]);

        // r1 — только на дубль; r2 — и на канон, и на дубль (конфликт PK при переносе).
        DB::table('request_addresses')->insert([
            ['request_id' => $r1, 'address_id' => $dupId],
            ['request_id' => $r2, 'address_id' => $canonId],
            ['request_id' => $r2, 'address_id' => $dupId],
        ]);

        (new MergeDuplicateAddresses())->mergeInto($canonId, [$dupId]);

        // Дубль удалён, канон на месте.
        $this->assertNull(DB::table('addresses')->where('id', $dupId)->first());
        $this->assertNotNull(DB::table('addresses')->where('id', $canonId)->first());

        // Ссылок на дубль не осталось.
        $this->assertSame(0, DB::table('request_addresses')->where('address_id', $dupId)->count());

        // r1 перепривязан на канон; r2 остался на каноне без задвоения.
        $this->assertSame(1, DB::table('request_addresses')->where('request_id', $r1)->where('address_id', $canonId)->count());
        $this->assertSame(1, DB::table('request_addresses')->where('request_id', $r2)->where('address_id', $canonId)->count());

        // Чистим за собой ссылки (адреса откатятся транзакцией; ссылки на чужие заявки уберём явно).
        DB::table('request_addresses')->where('address_id', $canonId)->delete();
    }
}
