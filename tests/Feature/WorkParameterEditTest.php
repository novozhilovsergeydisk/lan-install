<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Правка фактических количеств выполненных работ из отчётов.
 *
 * Монтажники пишут «выполнено 7 из 13», а поле не меняют — в выгрузку уходят
 * завышенные объёмы. Правим факт (is_planning = false), план не трогаем,
 * каждое изменение пишем в work_parameter_edits.
 */
class WorkParameterEditTest extends TestCase
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
        // isAdmin выставляем явно: HomeController вычисляет его только когда roles ещё не заданы.
        $admin->isAdmin = in_array('admin', $admin->roles, true);
        $admin->employee = DB::table('employees')->where('user_id', $admin->id)->first();
        $this->actingAs($admin);

        return $admin;
    }

    private function anyRequestId(): int
    {
        $row = DB::selectOne('SELECT id FROM requests ORDER BY id DESC LIMIT 1');
        if (! $row) {
            $this->markTestSkipped('Нет заявок в базе');
        }

        return (int) $row->id;
    }

    private function anyTypeId(): int
    {
        $row = DB::selectOne('SELECT id FROM work_parameter_types WHERE is_deleted = false ORDER BY id LIMIT 1');
        if (! $row) {
            $this->markTestSkipped('Нет типов работ');
        }

        return (int) $row->id;
    }

    /** Список работ отдаёт план и факт раздельно. */
    public function test_index_returns_planned_and_actual_quantities(): void
    {
        $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        DB::table('work_parameters')->where('request_id', $requestId)->delete();
        DB::table('work_parameters')->insert([
            ['request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 13, 'is_planning' => true, 'is_done' => false, 'created_at' => now(), 'updated_at' => now()],
            ['request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 13, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson("/api/requests/{$requestId}/work-parameters");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('parameter_type_id', $typeId);
        $this->assertNotNull($row);
        $this->assertEquals(13, $row['planned_quantity']);
        $this->assertEquals(13, $row['actual_quantity']);
    }

    /** Правка меняет ТОЛЬКО факт, план остаётся прежним. */
    public function test_update_changes_actual_and_keeps_planned(): void
    {
        $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        DB::table('work_parameters')->where('request_id', $requestId)->delete();
        DB::table('work_parameters')->insert([
            ['request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 13, 'is_planning' => true, 'is_done' => false, 'created_at' => now(), 'updated_at' => now()],
            ['request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 13, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => 7]],
        ]);
        $response->assertStatus(200)->assertJson(['success' => true]);

        $planned = DB::table('work_parameters')->where('request_id', $requestId)
            ->where('parameter_type_id', $typeId)->where('is_planning', true)->value('quantity');
        $actual = DB::table('work_parameters')->where('request_id', $requestId)
            ->where('parameter_type_id', $typeId)->where('is_planning', false)->orderByDesc('id')->value('quantity');

        $this->assertEquals(13, $planned, 'План не должен меняться');
        $this->assertEquals(7, $actual, 'Факт должен стать 7');
    }

    /** Каждая правка пишется в историю: было / стало / кто. */
    public function test_update_writes_history(): void
    {
        $admin = $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        DB::table('work_parameters')->where('request_id', $requestId)->delete();
        $paramId = DB::table('work_parameters')->insertGetId([
            'request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 13,
            'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => 7]],
        ])->assertStatus(200);

        // Историю ищем по заявке: если у типа работ была единственная запись (она же план),
        // правка создаёт новую фактическую, и история привязывается уже к ней.
        $edit = DB::table('work_parameter_edits')->where('request_id', $requestId)->first();
        $this->assertNotNull($edit, 'Запись истории должна быть создана');
        $this->assertEquals(13, $edit->old_quantity);
        $this->assertEquals(7, $edit->new_quantity);
        $this->assertEquals($admin->id, $edit->edited_by_user_id);
    }

    /** Если значение не изменилось — история не засоряется. */
    public function test_no_history_when_value_unchanged(): void
    {
        $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        DB::table('work_parameters')->where('request_id', $requestId)->delete();
        $paramId = DB::table('work_parameters')->insertGetId([
            'request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 5,
            'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => 5]],
        ])->assertStatus(200)->assertJson(['changed' => 0]);

        $this->assertEquals(0, DB::table('work_parameter_edits')->where('work_parameter_id', $paramId)->count());
    }

    /** Если фактической записи не было — она создаётся (заявку могли не закрывать). */
    public function test_creates_actual_record_when_missing(): void
    {
        $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        DB::table('work_parameters')->where('request_id', $requestId)->delete();
        DB::table('work_parameters')->insert([
            'request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 10,
            'is_planning' => true, 'is_done' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => 4]],
        ])->assertStatus(200);

        $actual = DB::table('work_parameters')->where('request_id', $requestId)
            ->where('parameter_type_id', $typeId)->where('is_planning', false)->first();

        $this->assertNotNull($actual, 'Фактическая запись должна быть создана');
        $this->assertEquals(4, $actual->quantity);
    }

    /**
     * Самый частый случай в реальных данных: у типа работ ОДНА запись, которая
     * в выгрузке служит и планом (первая по id), и фактом (последняя).
     * Правка не должна затирать план — вместо этого добавляется фактическая запись.
     */
    public function test_single_record_edit_preserves_planned_value(): void
    {
        $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        DB::table('work_parameters')->where('request_id', $requestId)->delete();
        DB::table('work_parameters')->insert([
            'request_id' => $requestId, 'parameter_type_id' => $typeId, 'quantity' => 13,
            'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => 7]],
        ])->assertStatus(200);

        // Так план и факт читает выгрузка (RequestsReportExport).
        $planned = DB::table('work_parameters')->where('request_id', $requestId)
            ->where('parameter_type_id', $typeId)->orderBy('id')->value('quantity');
        $actual = DB::table('work_parameters')->where('request_id', $requestId)
            ->where('parameter_type_id', $typeId)->where('is_planning', false)
            ->orderByDesc('id')->value('quantity');

        $this->assertEquals(13, $planned, 'Плановое значение должно сохраниться');
        $this->assertEquals(7, $actual, 'Фактическое должно стать 7');
    }

    /** Не-администратор править количества не может: цифры уходят в отчётность. */
    public function test_non_admin_cannot_update(): void
    {
        $nonAdmin = User::whereNotIn('id', function ($q) {
            $q->select('user_roles.user_id')
                ->from('user_roles')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'admin');
        })->first();

        if (! $nonAdmin) {
            $this->markTestSkipped('Нет пользователя без роли admin');
        }

        $nonAdmin->roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $nonAdmin->id)
            ->pluck('roles.name')->toArray();
        $this->actingAs($nonAdmin);

        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    /** Отрицательные значения не принимаются. */
    public function test_rejects_negative_quantity(): void
    {
        $this->authenticateAdmin();
        $requestId = $this->anyRequestId();
        $typeId = $this->anyTypeId();

        $this->patchJson("/api/requests/{$requestId}/work-parameters", [
            'parameters' => [['parameter_type_id' => $typeId, 'quantity' => -3]],
        ])->assertStatus(422);
    }

    /**
     * Редактирование заявки больше не стирает фактические количества.
     * Раньше updateRequest удалял ВСЕ параметры заявки и вставлял заново как плановые.
     */
    public function test_updating_request_keeps_actual_quantities(): void
    {
        $this->authenticateAdmin();
        $typeId = $this->anyTypeId();

        $req = DB::selectOne('
            SELECT r.id, r.execution_date, ra.address_id
            FROM requests r
            JOIN request_addresses ra ON ra.request_id = r.id
            ORDER BY r.id DESC LIMIT 1
        ');
        if (! $req || ! $req->address_id) {
            $this->markTestSkipped('Нет заявки с адресом');
        }

        DB::table('work_parameters')->where('request_id', $req->id)->delete();
        DB::table('work_parameters')->insert([
            ['request_id' => $req->id, 'parameter_type_id' => $typeId, 'quantity' => 13, 'is_planning' => true, 'is_done' => false, 'created_at' => now(), 'updated_at' => now()],
            ['request_id' => $req->id, 'parameter_type_id' => $typeId, 'quantity' => 7, 'is_planning' => false, 'is_done' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // client_* обязательны фактически: updateRequest читает их напрямую из массива,
        // хотя в правилах валидации они помечены nullable.
        $this->putJson("/requests/{$req->id}", [
            'request_id' => $req->id,
            'client_name' => 'Тест',
            'client_phone' => '',
            'client_organization' => '',
            'execution_date' => now()->addDay()->toDateString(),
            'addresses_id' => $req->address_id,
            'work_parameters' => [['parameter_type_id' => $typeId, 'quantity' => 20]],
        ])->assertStatus(200);

        $actual = DB::table('work_parameters')->where('request_id', $req->id)
            ->where('parameter_type_id', $typeId)->where('is_planning', false)->value('quantity');
        $planned = DB::table('work_parameters')->where('request_id', $req->id)
            ->where('parameter_type_id', $typeId)->where('is_planning', true)->value('quantity');

        $this->assertEquals(7, $actual, 'Фактическое количество должно сохраниться');
        $this->assertEquals(20, $planned, 'Плановое должно обновиться');
    }
}
