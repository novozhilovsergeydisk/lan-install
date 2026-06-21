<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Отображение оборудования (комплекты инструмента H-* + машины), взятого бригадой
 * со склада, на заявке: снимок при закрытии и вывод в дневном списке / на главной.
 *
 * Все данные создаются в транзакции и откатываются (DatabaseTransactions) — БД не портится.
 */
class RequestEquipmentDisplayTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.wms.api_key' => 'test_key']);
        config(['services.wms.base_url' => 'http://wms.test']);
    }

    private function authenticateAdmin(): User
    {
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (! $admin) {
            $this->markTestSkipped('Admin user not found');
        }

        // Роли и сотрудник обычно навешиваются middleware 'roles' — симулируем вручную.
        $admin->roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $admin->id)
            ->pluck('roles.name')->toArray();
        $admin->employee = DB::table('employees')->where('user_id', $admin->id)->first();

        $this->actingAs($admin);

        return $admin;
    }

    private function todayRequestWithBrigade()
    {
        return DB::selectOne("
            SELECT id FROM requests
            WHERE execution_date::date = CURRENT_DATE
              AND status_id NOT IN (5,6,7)
              AND brigade_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ");
    }

    /** (а) getRequestsByDate отдаёт equipment по заявке. */
    public function test_get_requests_by_date_returns_equipment(): void
    {
        $this->authenticateAdmin();

        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-TEST', 'created_at' => now()],
            ['request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'TEST777 ТестАвто', 'created_at' => now()],
        ]);

        $today = now()->toDateString();
        $response = $this->getJson("/api/requests/date/{$today}");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($row, 'Заявка должна быть в выдаче');
        $this->assertContains('H-TEST', $row['equipment']['tools']);
        $this->assertContains('TEST777 ТестАвто', $row['equipment']['vehicles']);
    }

    /** (б) На главной (welcome.blade) в колонке «Бригада» виден инструмент. */
    public function test_index_page_shows_equipment(): void
    {
        $this->authenticateAdmin();

        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        // index() обращается к складу за списком складов — фейкаем любые HTTP.
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-TEST', 'created_at' => now()],
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Инструмент:');
        $response->assertSee('H-TEST');
    }

    /** Захват: при закрытии заявки снимок пишется из ответа склада (фейк). */
    public function test_close_request_captures_equipment_snapshot(): void
    {
        $this->authenticateAdmin();

        // Открытая заявка с бригадой, у участников которой есть email.
        $req = DB::selectOne("
            SELECT r.id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.status_id != 4 AND e.is_deleted = false AND u.email IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ");
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки с бригадой и email');
        }

        Http::fake([
            '*/api/external/user-equipment*' => Http::response([
                'success' => true,
                'data' => [
                    'tools' => [['inventoryNumber' => 'H-FAKE']],
                    'vehicles' => [['plateNumber' => 'X000XX', 'model' => 'ФейкАвто']],
                ],
            ], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Тест: снимок оборудования',
            'work_parameters' => [],
            'uncompleted_works' => false,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-FAKE',
        ]);
        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'X000XX ФейкАвто',
        ]);
    }

    private function openRequestWithBrigadeEmails()
    {
        return DB::selectOne("
            SELECT r.id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.status_id != 4 AND e.is_deleted = false AND u.email IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ");
    }

    /** Эндпоинт формы закрытия отдаёт live-оборудование бригады. */
    public function test_request_equipment_endpoint_returns_brigade_equipment(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigadeEmails();
        if (! $req) {
            $this->markTestSkipped('Нет заявки с бригадой и email');
        }

        Http::fake([
            '*/api/external/user-equipment*' => Http::response([
                'success' => true,
                'data' => [
                    'tools' => [['inventoryNumber' => 'H-9']],
                    'vehicles' => [['plateNumber' => 'А777АА77', 'model' => 'Тест']],
                ],
            ], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $response = $this->getJson("/api/requests/{$req->id}/equipment");
        $response->assertStatus(200);
        $this->assertContains('H-9', $response->json('data.tools'));
        $this->assertContains('А777АА77 Тест', $response->json('data.vehicles'));
    }

    /** При закрытии сохраняется личное авто (source=personal), если со склада машины нет. */
    public function test_close_saves_personal_car(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigadeEmails();
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки с бригадой и email');
        }

        // Со склада машины нет — должно сохраниться личное авто.
        Http::fake([
            '*/api/external/user-equipment*' => Http::response(['success' => true, 'data' => ['tools' => [], 'vehicles' => []]], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Тест: личное авто',
            'work_parameters' => [],
            'uncompleted_works' => false,
            'personal_car' => 'А123ВС77 Лада',
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'vehicle', 'source' => 'personal', 'label' => 'А123ВС77 Лада',
        ]);
    }
}
