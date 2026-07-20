<?php

namespace Tests\Feature;

use App\Exports\RequestsReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Регресс: "Скрыть бригаду" (brigades.is_deleted) — фильтр только для дневного вида,
 * но раньше он же незаметно вырезал такие заявки из ВСЕХ отчётов (историю теряли).
 */
class ReportHiddenBrigadeVisibilityTest extends TestCase
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

    private function createRequestWithHiddenBrigade(?int $addressId = null): array
    {
        $address = $addressId
            ? DB::table('addresses')->where('id', $addressId)->first()
            : DB::table('addresses')->first();
        if (! $address) {
            $this->markTestSkipped('No address found');
        }

        $clientId = DB::table('clients')->first()->id ?? 1;
        $requestTypeId = DB::table('request_types')->first()->id ?? 1;
        $employeeId = DB::table('employees')->first()->id ?? 1;

        $brigadeId = DB::table('brigades')->insertGetId([
            'name' => 'TEST-HIDDEN-BRIGADE-'.time(),
            'leader_id' => $employeeId,
            'formation_date' => now()->toDateString(),
            'is_deleted' => true,
        ]);

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-HIDDEN-BR-'.time(),
            'client_id' => $clientId,
            'request_type_id' => $requestTypeId,
            'status_id' => 4,
            'operator_id' => $employeeId,
            'brigade_id' => $brigadeId,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
            'closed_at' => now(),
        ]);

        DB::table('request_addresses')->insert([
            'request_id' => $requestId,
            'address_id' => $address->id,
        ]);

        return [$requestId, $address->id];
    }

    public function test_all_period_by_address_report_includes_request_with_hidden_brigade()
    {
        $this->authenticateAdmin();
        [$requestId, $addressId] = $this->createRequestWithHiddenBrigade();

        $response = $this->postJson('/reports/requests/by-address-all-period', [
            'addressId' => $addressId,
        ]);

        $response->assertStatus(200);
        $ids = collect($response->json('requestsByAddressAndDateRange'))->pluck('id');
        $this->assertTrue($ids->contains($requestId), 'Заявка со скрытой бригадой должна быть в отчёте по адресу');
    }

    public function test_export_includes_request_with_hidden_brigade()
    {
        $this->authenticateAdmin();
        [$requestId] = $this->createRequestWithHiddenBrigade();

        $export = new RequestsReportExport(['allPeriod' => true]);
        $data = $export->collection();
        $row = $data->first(fn ($row) => $row->id == $requestId);

        $this->assertNotNull($row, 'Заявка со скрытой бригадой должна быть в Excel-экспорте отчёта');
    }
}
