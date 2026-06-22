<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * При закрытии заявки с галкой «недоделанные работы» создаётся перенесённая заявка на завтра.
 * В неё должен попадать ОСТАТОК (план − выполнено), а не выполненное количество —
 * и в work_parameters, и в комментарии. Раньше комментарий писал выполненное (баг заказчика).
 *
 * Всё в транзакции с откатом — БД не портится.
 */
class UncompletedWorksCarryoverTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wms.api_key' => 'test_key']);
        config(['services.wms.base_url' => 'http://wms.test']);
    }

    private function authenticateAdmin(): void
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
    }

    public function test_carryover_uses_remainder_not_done_quantity(): void
    {
        $this->authenticateAdmin();

        if (! DB::table('request_statuses')->where('name', 'перенесена')->exists()) {
            $this->markTestSkipped('Нет статуса "перенесена"');
        }
        $type = DB::table('work_parameter_types')->first();
        if (! $type) {
            $this->markTestSkipped('Нет типов работ');
        }
        $req = DB::selectOne('SELECT id FROM requests WHERE status_id NOT IN (4,5,6,7) ORDER BY id DESC LIMIT 1');
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки');
        }

        // Запланировано 20.
        DB::table('work_parameters')->insert([
            'request_id' => $req->id,
            'parameter_type_id' => $type->id,
            'quantity' => 20,
            'is_planning' => true,
            'is_done' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        // Выполнено 15, отмечены недоделанные работы.
        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Тест переноса',
            'work_parameters' => [
                ['parameter_type_id' => $type->id, 'quantity' => 15],
            ],
            'uncompleted_works' => true,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $newId = $response->json('new_request_id');
        $this->assertNotNull($newId, 'Должна создаться перенесённая заявка');

        // work_parameters новой заявки = остаток 5 (а не 15).
        $this->assertDatabaseHas('work_parameters', [
            'request_id' => $newId,
            'parameter_type_id' => $type->id,
            'quantity' => 5,
            'is_planning' => true,
        ]);

        // Комментарий новой заявки содержит остаток (5), а НЕ выполненное (15).
        $comment = DB::table('request_comments as rc')
            ->join('comments as c', 'c.id', '=', 'rc.comment_id')
            ->where('rc.request_id', $newId)
            ->orderByDesc('c.created_at')
            ->value('c.comment');

        $this->assertStringContainsString($type->name.': 5', $comment);
        $this->assertStringNotContainsString($type->name.': 15', $comment);
    }
}
