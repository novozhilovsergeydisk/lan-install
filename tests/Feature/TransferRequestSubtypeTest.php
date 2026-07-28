<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Перенос заявки "в планирование" должен требовать и сохранять subtype_id
 * (тип планирования), иначе заявка попадает в раздел планирования без него.
 */
class TransferRequestSubtypeTest extends TestCase
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

    private function pickTransferableRequest(): int
    {
        $request = DB::selectOne("
            SELECT id FROM requests WHERE status_id != 4 ORDER BY id DESC LIMIT 1
        ");
        if (! $request) {
            $this->markTestSkipped('No transferable request found');
        }

        return $request->id;
    }

    private function pickPlanningSubtypeId(): int
    {
        $subtype = DB::table('request_subtypes')
            ->where('status_id', 6)
            ->where('is_deleted', false)
            ->orderBy('id')
            ->first();
        if (! $subtype) {
            $this->markTestSkipped('No planning subtype found');
        }

        return $subtype->id;
    }

    public function test_transfer_to_planning_without_subtype_id_fails_validation()
    {
        $this->authenticateAdmin();
        $requestId = $this->pickTransferableRequest();

        $response = $this->postJson('/api/requests/transfer', [
            'request_id' => $requestId,
            'new_date' => now()->addDay()->toDateString(),
            'reason' => 'Тестовый перенос без типа',
            'transfer_to_planning' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subtype_id']);
    }

    public function test_transfer_to_planning_with_subtype_id_saves_it()
    {
        $this->authenticateAdmin();
        $requestId = $this->pickTransferableRequest();
        $subtypeId = $this->pickPlanningSubtypeId();

        $response = $this->postJson('/api/requests/transfer', [
            'request_id' => $requestId,
            'new_date' => now()->addDay()->toDateString(),
            'reason' => 'Тестовый перенос с типом планирования',
            'transfer_to_planning' => true,
            'subtype_id' => $subtypeId,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $updated = DB::table('requests')->where('id', $requestId)->first();
        $this->assertEquals(6, $updated->status_id);
        $this->assertEquals($subtypeId, $updated->subtype_id);
    }

    public function test_transfer_without_planning_clears_subtype_id()
    {
        $this->authenticateAdmin();
        $requestId = $this->pickTransferableRequest();

        $response = $this->postJson('/api/requests/transfer', [
            'request_id' => $requestId,
            'new_date' => now()->addDay()->toDateString(),
            'reason' => 'Тестовый перенос без планирования',
            'transfer_to_planning' => false,
        ]);

        $response->assertStatus(200);

        $updated = DB::table('requests')->where('id', $requestId)->first();
        $this->assertEquals(3, $updated->status_id);
        $this->assertNull($updated->subtype_id);
    }
}
