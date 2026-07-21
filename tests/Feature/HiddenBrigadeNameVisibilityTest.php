<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Регресс: "Скрыть бригаду" (brigades.is_deleted) не должно прятать
 * имя/бригадира уже назначенной бригады из отображения заявки — иначе
 * фронт (handler.js) показывает "Не назначена", хотя requests.brigade_id
 * не изменился. Обнаружено по видео заказчика (Фурса): бригада была
 * назначена, потом переназначена, и в таблице стало "Не назначена".
 */
class HiddenBrigadeNameVisibilityTest extends TestCase
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

    private function createRequestWithHiddenBrigade(string $executionDate): array
    {
        $clientId = DB::table('clients')->first()->id ?? 1;
        $requestTypeId = DB::table('request_types')->first()->id ?? 1;
        $employeeId = DB::table('employees')->first()->id ?? 1;

        $leaderId = DB::table('employees')->inRandomOrder()->value('id') ?? $employeeId;

        $brigadeId = DB::table('brigades')->insertGetId([
            'name' => 'TEST-HIDDEN-NAME-'.time(),
            'leader_id' => $leaderId,
            'formation_date' => now()->toDateString(),
            'is_deleted' => true,
        ]);

        DB::table('brigade_members')->insert([
            'brigade_id' => $brigadeId,
            'employee_id' => $employeeId,
        ]);

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-HIDDEN-NAME-'.time(),
            'client_id' => $clientId,
            'request_type_id' => $requestTypeId,
            'status_id' => 4,
            'operator_id' => $employeeId,
            'brigade_id' => $brigadeId,
            'execution_date' => $executionDate,
            'request_date' => $executionDate,
            'closed_at' => now(),
        ]);

        return [$requestId, $brigadeId];
    }

    public function test_get_requests_by_date_returns_brigade_name_even_when_brigade_hidden()
    {
        $this->authenticateAdmin();
        $date = now()->toDateString();
        [$requestId, $brigadeId] = $this->createRequestWithHiddenBrigade($date);

        $response = $this->getJson("/api/requests/date/{$date}");

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('id', $requestId);

        $this->assertNotNull($row, 'Заявка должна быть в ответе');
        $this->assertNotNull($row['brigade_leader_name'] ?? null, 'Имя бригадира не должно теряться из-за скрытой бригады');
        $this->assertNotEmpty($row['brigade_members'] ?? [], 'Состав скрытой бригады не должен теряться');
    }

    public function test_index_view_includes_hidden_brigade_in_members_details()
    {
        $this->authenticateAdmin();
        [, $brigadeId] = $this->createRequestWithHiddenBrigade(now()->toDateString());

        $response = $this->get('/');

        $response->assertStatus(200);
        $details = collect($response->viewData('brigadeMembersWithDetails') ?? []);
        $found = $details->contains(fn ($row) => $row->brigade_id == $brigadeId);

        $this->assertTrue($found, 'Скрытая бригада должна оставаться в brigadeMembersWithDetails для уже назначенных заявок');
    }
}
