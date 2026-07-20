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
 * Заодно проверяем маркер "в планировании / подтип" в тех же отчётных выборках.
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

    private function createPlanningRequest(?int $addressId = null): array
    {
        $address = $addressId
            ? DB::table('addresses')->where('id', $addressId)->first()
            : DB::table('addresses')->first();
        if (! $address) {
            $this->markTestSkipped('No address found');
        }

        $subtype = DB::table('request_subtypes')->where('status_id', 6)->first();
        if (! $subtype) {
            $this->markTestSkipped('No planning subtype found');
        }

        $clientId = DB::table('clients')->first()->id ?? 1;
        $requestTypeId = DB::table('request_types')->first()->id ?? 1;
        $employeeId = DB::table('employees')->first()->id ?? 1;

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-PLANNING-'.time(),
            'client_id' => $clientId,
            'request_type_id' => $requestTypeId,
            'status_id' => 6,
            'subtype_id' => $subtype->id,
            'operator_id' => $employeeId,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ]);

        DB::table('request_addresses')->insert([
            'request_id' => $requestId,
            'address_id' => $address->id,
        ]);

        return [$requestId, $address->id, $subtype->name];
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

    public function test_all_period_by_address_report_includes_planning_subtype()
    {
        $this->authenticateAdmin();
        [$requestId, $addressId, $subtypeName] = $this->createPlanningRequest();

        $response = $this->postJson('/reports/requests/by-address-all-period', [
            'addressId' => $addressId,
        ]);

        $response->assertStatus(200);
        $row = collect($response->json('requestsByAddressAndDateRange'))->firstWhere('id', $requestId);

        $this->assertNotNull($row, 'Заявка в планировании должна быть в отчёте по адресу');
        $this->assertSame('планирование', $row['status_name']);
        $this->assertSame($subtypeName, $row['subtype_name']);
    }

    public function test_export_includes_planning_subtype()
    {
        $this->authenticateAdmin();
        [$requestId, , $subtypeName] = $this->createPlanningRequest();

        $export = new RequestsReportExport(['allPeriod' => true]);
        $data = $export->collection();
        $row = $data->first(fn ($row) => $row->id == $requestId);

        $this->assertNotNull($row);
        $this->assertSame('планирование', $row->status_name);
        $this->assertSame($subtypeName, $row->subtype_name);

        $mapped = (new RequestsReportExport(['allPeriod' => true]))->map($row);
        $this->assertStringContainsString('В планировании: '.$subtypeName, $mapped[0]);
    }
}
