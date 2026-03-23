<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class WmsIntegrationTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wms.api_key' => 'test_key']);
        config(['services.wms.base_url' => 'http://localhost:5001']);
    }

    private function authenticateAdmin()
    {
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (!$admin) {
            $this->markTestSkipped('Admin user not found');
        }

        // В этом проекте роли и сотрудник добавляются в middleware 'roles' (AddUserRolesToView)
        // Чтобы тест не падал, мы должны вручную просимулировать этот процесс
        $roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $admin->id)
            ->pluck('roles.name')
            ->toArray();

        $admin->roles = $roles;
        $admin->employee = DB::table('employees')->where('user_id', $admin->id)->first();
        
        $this->actingAs($admin);
        return $admin;
    }

    public function test_admin_can_save_wms_mapping()
    {
        $this->authenticateAdmin();

        $response = $this->post(route('wms-mappings.store'), [
            'request_type_id' => 1,
            'wms_warehouse_id' => 4
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('request_type_wms_warehouses', [
            'request_type_id' => 1,
            'wms_warehouse_id' => 4
        ]);
    }

    public function test_close_request_calls_warehouse_deduction_endpoint()
    {
        $this->authenticateAdmin();

        // Фейкаем ответ от WMS
        Http::fake([
            '*/api/external/deduct-warehouse' => Http::response(['success' => true], 200),
        ]);

        // Берем любую существующую ОТКРЫТУЮ заявку для теста
        $request = DB::table('requests')->where('status_id', '!=', 4)->first();
        if (!$request) {
            $this->markTestSkipped('No open requests in DB');
        }
        
        $response = $this->post("/requests/{$request->id}/close", [
            'comment' => 'Test close automation',
            'work_parameters' => [],
            'uncompleted_works' => false,
            'wms_deduct' => true,
            'wms_source' => 'warehouse',
            'wms_warehouse_id' => 4,
            'wms_deductions' => [
                '4' => [
                    '1' => 1
                ]
            ],
            '_token' => csrf_token()
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'deduct-warehouse') &&
                   $request['warehouseId'] == 4;
        });
    }
}
