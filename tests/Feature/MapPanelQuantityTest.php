<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Цифра в маркере на карте = количество панелей по заявке.
 *
 * Привязка идёт по ID типа параметра (config/map.php), а не по имени: заказчик
 * переименовывал тип («панели» → «Панели (новые)»), и привязка по строке ломалась бы.
 * Раньше бралcя первый по порядку параметр заявки — если первой оказывалась, скажем,
 * «Мобильная стойка», на карте показывалось её количество вместо панелей.
 */
class MapPanelQuantityTest extends TestCase
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
        $admin->employee = DB::table('employees')->where('user_id', $admin->id)->first();
        $this->actingAs($admin);

        return $admin;
    }

    private function todayRequest()
    {
        return DB::selectOne("
            SELECT id FROM requests
            WHERE execution_date::date = CURRENT_DATE
              AND status_id NOT IN (5,6,7)
              AND brigade_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ");
    }

    private function panelTypeId(): int
    {
        $ids = (array) config('map.panel_parameter_type_ids', []);
        if (empty($ids)) {
            $this->markTestSkipped('Не заданы ID панельных параметров в config/map.php');
        }

        return (int) $ids[0];
    }

    private function nonPanelTypeId(): int
    {
        $panelIds = array_map('intval', (array) config('map.panel_parameter_type_ids', []));
        $type = DB::table('work_parameter_types')->whereNotIn('id', $panelIds)->orderBy('id')->first();
        if (! $type) {
            $this->markTestSkipped('Нет непанельного типа параметра');
        }

        return (int) $type->id;
    }

    private function mapQuantityFor(int $requestId)
    {
        $today = now()->toDateString();
        $response = $this->getJson("/api/requests/date/{$today}");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $requestId);
        $this->assertNotNull($row, 'Заявка должна быть в выдаче');

        return $row['first_param_quantity'];
    }

    /**
     * Главное: если панели идут НЕ первыми, на карте всё равно их количество,
     * а не количество случайно оказавшегося первым параметра.
     */
    public function test_map_shows_panels_even_when_they_are_not_first_parameter(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequest();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки');
        }

        DB::table('work_parameters')->where('request_id', $req->id)->delete();
        // Первым идёт НЕ панельный параметр — раньше на карту попадал именно он.
        DB::table('work_parameters')->insert([
            ['request_id' => $req->id, 'parameter_type_id' => $this->nonPanelTypeId(), 'quantity' => 3, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
            ['request_id' => $req->id, 'parameter_type_id' => $this->panelTypeId(), 'quantity' => 14, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertEquals(14, $this->mapQuantityFor($req->id));
    }

    /**
     * План и факт: при закрытии заявки дописывается вторая запись того же параметра.
     * Показываем ПЛАН (первую запись), а не сумму — иначе 14 + 17 = 31.
     */
    public function test_map_shows_planned_quantity_not_sum_of_plan_and_fact(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequest();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки');
        }

        $panelType = $this->panelTypeId();

        DB::table('work_parameters')->where('request_id', $req->id)->delete();
        DB::table('work_parameters')->insert([
            ['request_id' => $req->id, 'parameter_type_id' => $panelType, 'quantity' => 14, 'is_planning' => false, 'is_done' => true, 'created_at' => now()->subHour(), 'updated_at' => now()->subHour()],
            ['request_id' => $req->id, 'parameter_type_id' => $panelType, 'quantity' => 17, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $quantity = $this->mapQuantityFor($req->id);
        $this->assertEquals(14, $quantity);
        $this->assertNotEquals(31, $quantity, 'План и факт не должны суммироваться');
    }

    /**
     * Обратная совместимость: у заявки без панелей (напр. «Демонтаж МЭШ» — там ИП,
     * точки доступа) маркер по-прежнему показывает первый параметр, а не пустоту.
     */
    public function test_map_falls_back_to_first_parameter_when_no_panels(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequest();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки');
        }

        DB::table('work_parameters')->where('request_id', $req->id)->delete();
        DB::table('work_parameters')->insert([
            ['request_id' => $req->id, 'parameter_type_id' => $this->nonPanelTypeId(), 'quantity' => 27, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertEquals(27, $this->mapQuantityFor($req->id));
    }
}
