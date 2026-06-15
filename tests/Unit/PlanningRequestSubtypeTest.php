<?php

namespace Tests\Unit;

use App\Http\Controllers\PlanningRequestController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Импорт заявок должен записывать ВЫБРАННЫЙ тип планирования (подтип),
 * а не захардкоженное «Стандартное планирование» (subtype_id = 1).
 */
class PlanningRequestSubtypeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * createPlanningRequest проставляет operator_id по auth()->id() → сотруднику.
     * Колонка operator_id NOT NULL, поэтому авторизуемся под пользователем,
     * у которого есть запись в employees (как при реальном импорте под админом).
     */
    private function actingAsEmployeeUser(): void
    {
        $employee = DB::table('employees')->whereNotNull('user_id')->first();
        if (! $employee) {
            $this->markTestSkipped('В базе нет сотрудника с привязанным пользователем.');
        }

        $user = User::find($employee->user_id);
        if (! $user) {
            $this->markTestSkipped('Не найден пользователь для сотрудника.');
        }

        $this->actingAs($user);
    }

    /** Создаёт клиента и адрес, возвращает данные для createPlanningRequest. */
    private function makeRequestData(?int $subtypeId): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'fio' => 'Тест Тестов',
            'phone' => '',
            'organization' => 'Тест-организация',
            'email' => '',
        ]);

        $cityId = DB::table('cities')->value('id');
        $this->assertNotNull($cityId, 'Ожидается хотя бы один город в базе');

        $addressId = DB::table('addresses')->insertGetId([
            'city_id' => $cityId,
            'street' => 'улица СабтайпТест '.uniqid(),
            'district' => '',
            'houses' => '1',
        ]);

        $data = [
            'client_id' => $clientId,
            'address_id' => $addressId,
            'comment' => '',
            'request_type_id' => 1,
        ];
        if ($subtypeId !== null) {
            $data['subtype_id'] = $subtypeId;
        }

        return $data;
    }

    private function callCreatePlanningRequest(array $data)
    {
        $method = new \ReflectionMethod(PlanningRequestController::class, 'createPlanningRequest');
        $method->setAccessible(true);

        return $method->invoke(new PlanningRequestController(), $data);
    }

    public function test_uses_selected_subtype(): void
    {
        // Подтип, заведомо отличный от дефолтного (1 = «Стандартное планирование»).
        $subtypeId = DB::table('request_subtypes')
            ->where('is_deleted', false)
            ->where('id', '!=', 1)
            ->orderBy('id')
            ->value('id');

        if (! $subtypeId) {
            $this->markTestSkipped('В базе нет подтипа, отличного от стандартного.');
        }

        $this->actingAsEmployeeUser();
        $request = $this->callCreatePlanningRequest($this->makeRequestData((int) $subtypeId));

        $this->assertEquals($subtypeId, $request->subtype_id, 'Заявка должна получить выбранный тип планирования');
        $this->assertEquals(6, $request->status_id, 'Статус должен быть «планирование» (6)');
    }

    public function test_defaults_to_standard_subtype_when_not_provided(): void
    {
        // Фолбэк: если подтип не передан — должно подставиться «Стандартное планирование» (1).
        $this->actingAsEmployeeUser();
        $request = $this->callCreatePlanningRequest($this->makeRequestData(null));

        $this->assertEquals(1, $request->subtype_id, 'Без явного подтипа должен быть дефолт = 1');
    }
}
