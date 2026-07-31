<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Wms\WmsEquipmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Оборудование со склада рядом с бригадой (комплекты H-* + машины) и выбор
 * «водитель / своя машина» при закрытии (пишется в конец комментария).
 *
 * Всё в транзакции с откатом (DatabaseTransactions) — БД не портится.
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
        return DB::selectOne('
            SELECT id FROM requests
            WHERE execution_date::date = CURRENT_DATE
              AND status_id NOT IN (5,6,7)
              AND brigade_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ');
    }

    private function openRequestWithBrigade()
    {
        return DB::selectOne('
            SELECT r.id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.status_id != 4 AND e.is_deleted = false AND u.email IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ');
    }

    private function brigadeMembers($requestId): array
    {
        return DB::select('
            SELECT e.id, e.fio
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            WHERE r.id = ? AND e.is_deleted = false
            ORDER BY e.id
        ', [$requestId]);
    }

    /** Колонка «Бригада» в дневном списке отдаёт оборудование заявки. */
    public function test_get_requests_by_date_returns_equipment(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-TEST', 'source' => 'warehouse', 'created_at' => now()],
            ['request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'TEST777 ТестАвто', 'source' => 'warehouse', 'created_at' => now()],
        ]);

        // Глушим живой рефреш (троттл), чтобы он не перетёр засеянное оборудование.
        cache()->put('wms_equipment_refreshed_at', now(), 3600);

        $today = now()->toDateString();
        $response = $this->getJson("/api/requests/date/{$today}");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($row, 'Заявка должна быть в выдаче');
        $this->assertContains('H-TEST', $row['equipment']['tools']);
        $this->assertContains('TEST777 ТестАвто', $row['equipment']['vehicles']);
    }

    /**
     * В карточке заявки инструмент показывается КОРОТКО — только инвентарный номер (wms_ref),
     * без полного названия, и дубли одного номера схлопываются в одну подпись.
     * Полные названия остаются в модалке «Состав бригады» (BrigadeController).
     */
    public function test_card_shows_only_inventory_number_for_tools(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        DB::table('request_equipment')->where('request_id', $req->id)->delete();
        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'Bosch GBH 185-LI (Перф) (B-2)', 'wms_ref' => 'B-2', 'source' => 'warehouse', 'created_at' => now()],
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'Hilti SF 2-A (Мелкий шурик) (B-2)', 'wms_ref' => 'B-2', 'source' => 'warehouse', 'created_at' => now()],
            ['request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'TEST777 ТестАвто', 'wms_ref' => 'TEST777', 'source' => 'warehouse', 'created_at' => now()],
        ]);

        cache()->put('wms_equipment_refreshed_at', now(), 3600);

        $today = now()->toDateString();
        $response = $this->getJson("/api/requests/date/{$today}");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($row, 'Заявка должна быть в выдаче');

        // Только инв. номер, два инструмента с одним номером схлопнуты в один пункт.
        $this->assertSame(['B-2'], $row['equipment']['tools']);
        $this->assertStringNotContainsString('Bosch', implode(',', $row['equipment']['tools']));

        // Машина по-прежнему с моделью — её формат не меняли.
        $this->assertContains('TEST777 ТестАвто', $row['equipment']['vehicles']);
    }

    /** Модалка «Состав бригады» продолжает отдавать ПОЛНЫЕ названия инструмента. */
    public function test_brigade_modal_keeps_full_tool_names(): void
    {
        $this->authenticateAdmin();
        $req = DB::selectOne('
            SELECT id, brigade_id FROM requests
            WHERE execution_date::date = CURRENT_DATE
              AND status_id NOT IN (4,5,6,7)
              AND brigade_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ');
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней открытой заявки с бригадой');
        }

        DB::table('request_equipment')->where('request_id', $req->id)->delete();
        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'Bosch GBH 185-LI (Перф) (B-2)', 'wms_ref' => 'B-2', 'source' => 'warehouse', 'created_at' => now()],
        ]);

        $response = $this->postJson("/brigade/{$req->brigade_id}");
        $response->assertStatus(200);

        $tools = $response->json('equipment.tools') ?? [];
        $this->assertContains('Bosch GBH 185-LI (Перф) (B-2)', $tools, 'В модалке должно остаться полное название');
    }

    /** На главной (welcome.blade) в колонке «Бригада» виден инструмент. */
    public function test_index_page_shows_equipment(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        Http::fake(['*' => Http::response(['success' => true, 'data' => ['tools' => [['inventoryNumber' => 'H-TEST']], 'vehicles' => []]], 200)]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Инструмент:');
        $response->assertSee('H-TEST');
    }

    /** При закрытии снимок оборудования пишется из ответа склада (фейк). */
    public function test_close_request_captures_equipment_snapshot(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigade();
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

        DB::table('request_equipment')->where('request_id', $req->id)->delete();

        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Тест: снимок оборудования',
            'work_parameters' => [],
            'uncompleted_works' => false,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-FAKE', 'source' => 'warehouse',
        ]);
        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'X000XX ФейкАвто', 'source' => 'warehouse',
        ]);
    }

    /** При закрытии НЕ затираем уже показанный снимок, даже если склад в этот момент вернул пусто. */
    public function test_close_keeps_existing_snapshot_when_warehouse_empty(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }

        // Снимок, который уже показывался днём (наполнен живым обновлением).
        DB::table('request_equipment')->where('request_id', $req->id)->delete();
        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-LIVE', 'source' => 'warehouse', 'created_at' => now()],
            ['request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'B222BB ЖивоеАвто', 'source' => 'warehouse', 'created_at' => now()],
        ]);

        // На момент закрытия склад возвращает ПУСТО (инвентарь сдан / склад недоступен).
        Http::fake(['*' => Http::response(['success' => true, 'data' => ['tools' => [], 'vehicles' => []]], 200)]);

        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Закрытие со сданным инвентарём',
            'work_parameters' => [],
            'uncompleted_works' => false,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        // Снимок должен сохраниться (не затереться).
        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-LIVE',
        ]);
        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'B222BB ЖивоеАвто',
        ]);
    }

    /** При закрытии «Водитель/Своя машина» дописываются в конец комментария. */
    public function test_close_appends_driver_and_own_car_to_comment(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }
        $members = $this->brigadeMembers($req->id);
        if (count($members) < 1) {
            $this->markTestSkipped('У бригады нет участников');
        }
        $driver = $members[0];
        $ownCar = $members[count($members) > 1 ? 1 : 0];

        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Тест закрытия',
            'work_parameters' => [],
            'uncompleted_works' => false,
            'driver_employee_id' => $driver->id,
            'own_car_employee_ids' => [$ownCar->id],
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $comment = DB::table('request_comments as rc')
            ->join('comments as c', 'c.id', '=', 'rc.comment_id')
            ->where('rc.request_id', $req->id)
            ->orderByDesc('c.created_at')
            ->value('c.comment');

        $this->assertStringContainsString('Водитель: '.$driver->fio, $comment);
        $this->assertStringContainsString('Своя машина: '.$ownCar->fio, $comment);
    }

    /** Эндпоинт состава бригады заявки (для выбора водителя/своей машины). */
    public function test_brigade_members_endpoint_returns_members(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет заявки с бригадой');
        }

        $response = $this->getJson("/api/requests/{$req->id}/brigade-members");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('fio', $data[0]);
        $this->assertArrayHasKey('id', $data[0]);
    }

    /** Плановая команда обновляет оборудование открытых сегодняшних заявок из склада. */
    public function test_refresh_command_updates_open_today_requests(): void
    {
        $req = DB::selectOne('
            SELECT r.id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.execution_date::date = CURRENT_DATE AND r.status_id NOT IN (4,5,6,7)
              AND e.is_deleted = false AND u.email IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ');
        if (! $req) {
            $this->markTestSkipped('Нет открытой сегодняшней заявки с бригадой и email');
        }

        Http::fake([
            '*/api/external/user-equipment*' => Http::response([
                'success' => true,
                'data' => ['tools' => [['inventoryNumber' => 'H-CMD']], 'vehicles' => []],
            ], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        DB::table('request_equipment')->where('request_id', $req->id)->delete();

        $this->artisan('wms:refresh-equipment')->assertExitCode(0);

        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-CMD', 'source' => 'warehouse',
        ]);
    }

    /** Если водитель/своя машина не выбраны — в комментарий ничего не дописывается. */
    public function test_close_without_driver_or_own_car_adds_no_lines(): void
    {
        $this->authenticateAdmin();
        $req = $this->openRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }

        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        $response = $this->post("/requests/{$req->id}/close", [
            'comment' => 'Тест без водителя',
            'work_parameters' => [],
            'uncompleted_works' => false,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $comment = DB::table('request_comments as rc')
            ->join('comments as c', 'c.id', '=', 'rc.comment_id')
            ->where('rc.request_id', $req->id)
            ->orderByDesc('c.created_at')
            ->value('c.comment');

        $this->assertStringNotContainsString('Водитель:', $comment);
        $this->assertStringNotContainsString('Своя машина:', $comment);
    }

    /** На прошлый/будущий день у ОТКРЫТОЙ заявки оборудование не показываем (живое, неактуально). */
    public function test_equipment_hidden_for_open_request_on_past_date(): void
    {
        $this->authenticateAdmin();
        $req = DB::selectOne('
            SELECT id, DATE(execution_date) AS d
            FROM requests
            WHERE execution_date::date <> CURRENT_DATE
              AND status_id NOT IN (4,5,6,7)
              AND brigade_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ');
        if (! $req) {
            $this->markTestSkipped('Нет открытой заявки на не-сегодняшнюю дату с бригадой');
        }

        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-HIDE', 'source' => 'warehouse', 'created_at' => now()],
        ]);

        $response = $this->getJson("/api/requests/date/{$req->d}");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($row, 'Заявка должна быть в выдаче');
        $this->assertEmpty($row['equipment']['tools'], 'У открытой заявки на прошлый день инструмент не показываем');
        $this->assertEmpty($row['equipment']['vehicles'], 'У открытой заявки на прошлый день авто не показываем');
    }

    /** На прошлый день у ЗАКРЫТОЙ заявки замороженный снимок оборудования виден всегда (просьба заказчика). */
    public function test_equipment_shown_for_closed_request_on_past_date(): void
    {
        $this->authenticateAdmin();
        $req = DB::selectOne('
            SELECT id, DATE(execution_date) AS d
            FROM requests
            WHERE execution_date::date <> CURRENT_DATE
              AND status_id = 4
              AND brigade_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ');
        if (! $req) {
            $this->markTestSkipped('Нет закрытой заявки на не-сегодняшнюю дату с бригадой');
        }

        DB::table('request_equipment')->where('request_id', $req->id)->delete();
        DB::table('request_equipment')->insert([
            ['request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-FROZEN', 'source' => 'warehouse', 'created_at' => now()],
            ['request_id' => $req->id, 'kind' => 'vehicle', 'label' => 'A111AA ЗамёрзАвто', 'source' => 'warehouse', 'created_at' => now()],
        ]);

        $response = $this->getJson("/api/requests/date/{$req->d}");
        $response->assertStatus(200);

        $row = collect($response->json('data'))->firstWhere('id', $req->id);
        $this->assertNotNull($row, 'Заявка должна быть в выдаче');
        $this->assertContains('H-FROZEN', $row['equipment']['tools'], 'У закрытой заявки снимок инструмента виден на любой день');
        $this->assertContains('A111AA ЗамёрзАвто', $row['equipment']['vehicles'], 'У закрытой заявки снимок авто виден на любой день');
    }

    /** Живой рефреш «по требованию» обновляет request_equipment открытых сегодняшних заявок из склада. */
    public function test_refresh_today_best_effort_updates_open_requests(): void
    {
        $req = DB::selectOne('
            SELECT r.id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.execution_date::date = CURRENT_DATE AND r.status_id NOT IN (4,5,6,7)
              AND e.is_deleted = false AND u.email IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ');
        if (! $req) {
            $this->markTestSkipped('Нет открытой сегодняшней заявки с бригадой и email');
        }

        cache()->forget('wms_equipment_refreshed_at');
        Http::fake([
            '*/api/external/warehouses*' => Http::response(['success' => true, 'data' => []], 200),
            '*/api/external/user-equipment*' => Http::response([
                'success' => true,
                'data' => ['tools' => [['inventoryNumber' => 'H-RT']], 'vehicles' => []],
            ], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);
        DB::table('request_equipment')->where('request_id', $req->id)->delete();

        app(WmsEquipmentService::class)->refreshTodayBestEffort();

        $this->assertDatabaseHas('request_equipment', [
            'request_id' => $req->id, 'kind' => 'tool', 'label' => 'H-RT', 'source' => 'warehouse',
        ]);
    }

    /** Инструмент с офисной категорией (31, 32, 33) исключается, со строительной — показывается. */
    public function test_office_category_tools_are_filtered_out(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        Http::fake([
            '*/api/external/user-equipment*' => Http::response([
                'success' => true,
                'data' => [
                    'tools' => [
                        ['inventoryNumber' => 'H-DRILL', 'name' => 'Перфоратор Bosch', 'categoryId' => 1],
                        ['inventoryNumber' => 'H-KEYB', 'name' => 'Клавиатура Logitech', 'categoryId' => 32],
                        ['inventoryNumber' => 'H-USB', 'name' => 'USB накопитель 32GB', 'categoryId' => 31],
                        ['inventoryNumber' => 'H-POWER', 'name' => 'Мобильный пауэрбанк', 'categoryId' => 33],
                    ],
                    'vehicles' => [],
                ],
            ], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        DB::table('request_equipment')->where('request_id', $req->id)->delete();
        app(WmsEquipmentService::class)->captureSnapshotForRequest($req->id);

        $saved = DB::table('request_equipment')->where('request_id', $req->id)->pluck('label')->toArray();
        $this->assertContains('Перфоратор Bosch (H-DRILL)', $saved);
        $this->assertNotContains('Клавиатура Logitech (H-KEYB)', $saved);
        $this->assertNotContains('USB накопитель 32GB (H-USB)', $saved);
        $this->assertNotContains('Мобильный пауэрбанк (H-POWER)', $saved);
    }

    /** В категории 10 (Зарядки) названия с «зарядка для телефона» и «зарядка-банка» исключаются, зарядные станции для инструмента — нет. */
    public function test_charge_category_name_filter(): void
    {
        $this->authenticateAdmin();
        $req = $this->todayRequestWithBrigade();
        if (! $req) {
            $this->markTestSkipped('Нет сегодняшней заявки с бригадой');
        }

        Http::fake([
            '*/api/external/user-equipment*' => Http::response([
                'success' => true,
                'data' => [
                    'tools' => [
                        ['inventoryNumber' => 'H-BOSCH', 'name' => 'Зарядная станция Bosch 18V', 'categoryId' => 10],
                        ['inventoryNumber' => 'H-METABO', 'name' => 'Зарядка Metabo 18V', 'categoryId' => 10],
                        ['inventoryNumber' => 'H-PHONE', 'name' => 'Зарядка для телефона Xiaomi', 'categoryId' => 10],
                        ['inventoryNumber' => 'H-BANK', 'name' => 'Зарядка-банка 10000mAh', 'categoryId' => 10],
                        ['inventoryNumber' => 'H-OTHER', 'name' => 'Перфоратор Bosch', 'categoryId' => 1],
                    ],
                    'vehicles' => [],
                ],
            ], 200),
            '*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        DB::table('request_equipment')->where('request_id', $req->id)->delete();
        app(WmsEquipmentService::class)->captureSnapshotForRequest($req->id);

        $saved = DB::table('request_equipment')->where('request_id', $req->id)->pluck('label')->toArray();
        $this->assertContains('Зарядная станция Bosch 18V (H-BOSCH)', $saved);
        $this->assertContains('Зарядка Metabo 18V (H-METABO)', $saved);
        $this->assertNotContains('Зарядка для телефона Xiaomi (H-PHONE)', $saved);
        $this->assertNotContains('Зарядка-банка 10000mAh (H-BANK)', $saved);
        $this->assertContains('Перфоратор Bosch (H-OTHER)', $saved);
    }

    /** В пределах 5-секундного окна троттла склад не опрашивается повторно. */
    public function test_refresh_today_best_effort_is_throttled(): void
    {
        cache()->put('wms_equipment_refreshed_at', now(), 3600);
        Http::fake(['*' => Http::response(['success' => true, 'data' => []], 200)]);

        app(WmsEquipmentService::class)->refreshTodayBestEffort();

        Http::assertNothingSent();
    }
}
