<?php

namespace Tests\Feature;

use App\Models\User;
use App\Exports\RequestsReportExport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Тесты для флага is_closing на комментарии закрытия заявки.
 */
class ClosingCommentTest extends TestCase
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

    /**
     * closeRequest создаёт комментарий с is_closing = true.
     */
    public function test_close_request_sets_is_closing_on_comment()
    {
        $admin = $this->authenticateAdmin();

        $request = DB::selectOne("
            SELECT r.id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            WHERE r.status_id != 4 AND b.is_deleted = false
            ORDER BY r.id DESC LIMIT 1
        ");
        if (! $request) {
            $this->markTestSkipped('No open request with brigade found');
        }

        $requestId = $request->id;

        $response = $this->post("/requests/{$requestId}/close", [
            'comment' => 'Тестовый комментарий закрытия',
        ]);

        $response->assertStatus(200);

        $closingComment = DB::table('request_comments')
            ->where('request_id', $requestId)
            ->where('is_closing', true)
            ->first();

        $this->assertNotNull($closingComment, 'Комментарий закрытия с is_closing=true должен быть создан');

        $closingCount = DB::table('request_comments')
            ->where('request_id', $requestId)
            ->where('is_closing', true)
            ->count();

        $this->assertEquals(1, $closingCount, 'Должен быть ровно один комментарий закрытия');

        $comment = DB::table('comments')->where('id', $closingComment->comment_id)->first();
        $this->assertStringContainsString('Тестовый комментарий закрытия', $comment->comment);
    }

    /**
     * getComments отдаёт is_closing в JSON.
     */
    public function test_get_comments_includes_is_closing_flag()
    {
        $this->authenticateAdmin();

        $request = DB::selectOne("
            SELECT rc.request_id
            FROM request_comments rc
            WHERE rc.is_closing = true
            ORDER BY rc.created_at DESC
            LIMIT 1
        ");
        if (! $request) {
            $this->markTestSkipped('No request with closing comment found');
        }

        $response = $this->getJson("/api/requests/{$request->request_id}/comments");
        $response->assertStatus(200);

        $data = $response->json();
        $comments = $data['comments'] ?? [];

        $hasClosing = collect($comments)->contains('is_closing', true);
        $this->assertTrue($hasClosing, 'JSON должен содержать комментарий с is_closing=true');

        foreach ($comments as $comment) {
            $this->assertArrayHasKey('is_closing', $comment, 'Каждый комментарий должен содержать поле is_closing');
        }
    }

    /**
     * Отчёт выгрузки: комментарий закрытия используется вместо последнего по времени,
     * даже если после закрытия добавлены новые комментарии.
     */
    public function test_report_uses_closing_comment_not_latest()
    {
        $admin = $this->authenticateAdmin();
        $userId = $admin->id;

        $clientId = DB::table('clients')->first()->id ?? 1;
        $requestTypeId = DB::table('request_types')->first()->id ?? 1;

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-CLOSING-' . time(),
            'client_id' => $clientId,
            'request_type_id' => $requestTypeId,
            'status_id' => 4,
            'operator_id' => DB::table('employees')->first()->id ?? 1,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
            'closed_at' => now(),
        ]);

        // 1. Обычный комментарий (до закрытия)
        $commentId1 = DB::table('comments')->insertGetId([
            'comment' => 'Первый комментарий',
            'created_at' => now()->subHour(),
        ]);
        DB::table('request_comments')->insert([
            'request_id' => $requestId,
            'comment_id' => $commentId1,
            'user_id' => $userId,
            'is_closing' => false,
            'created_at' => now()->subHour(),
        ]);

        // 2. Комментарий закрытия
        $commentId2 = DB::table('comments')->insertGetId([
            'comment' => 'Заявка закрыта — работы выполнены',
            'created_at' => now()->subMinutes(30),
        ]);
        DB::table('request_comments')->insert([
            'request_id' => $requestId,
            'comment_id' => $commentId2,
            'user_id' => $userId,
            'is_closing' => true,
            'created_at' => now()->subMinutes(30),
        ]);

        // 3. Комментарий ПОСЛЕ закрытия
        $commentId3 = DB::table('comments')->insertGetId([
            'comment' => 'Дополнительный комментарий после закрытия',
            'created_at' => now()->subMinutes(10),
        ]);
        DB::table('request_comments')->insert([
            'request_id' => $requestId,
            'comment_id' => $commentId3,
            'user_id' => $userId,
            'is_closing' => false,
            'created_at' => now()->subMinutes(10),
        ]);

        $export = new RequestsReportExport(['allPeriod' => true]);
        $data = $export->collection();
        $row = $data->first(fn ($row) => $row->id == $requestId);

        $this->assertNotNull($row, 'Строка заявки должна быть в отчёте');

        // last_comment — это закрывающий (не последний по времени!)
        $this->assertStringContainsString(
            'Заявка закрыта — работы выполнены',
            strip_tags($row->last_comment),
            'last_comment должен содержать текст комментария закрытия, а не самый свежий'
        );

        $this->assertNotNull($row->closing_comment, 'closing_comment должен быть в данных');
    }

    /**
     * Отчёт выгрузки: для старых заявок без флага is_closing — прежнее поведение.
     */
    public function test_report_falls_back_to_latest_without_closing_flag()
    {
        $admin = $this->authenticateAdmin();
        $userId = $admin->id;

        $clientId = DB::table('clients')->first()->id ?? 1;
        $requestTypeId = DB::table('request_types')->first()->id ?? 1;

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-NO-CLOSING-' . time(),
            'client_id' => $clientId,
            'request_type_id' => $requestTypeId,
            'status_id' => 4,
            'operator_id' => DB::table('employees')->first()->id ?? 1,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
            'closed_at' => now(),
        ]);

        $commentId1 = DB::table('comments')->insertGetId([
            'comment' => 'Старый комментарий',
            'created_at' => now()->subHour(),
        ]);
        DB::table('request_comments')->insert([
            'request_id' => $requestId,
            'comment_id' => $commentId1,
            'user_id' => $userId,
            'is_closing' => false,
            'created_at' => now()->subHour(),
        ]);

        $commentId2 = DB::table('comments')->insertGetId([
            'comment' => 'Свежий комментарий',
            'created_at' => now(),
        ]);
        DB::table('request_comments')->insert([
            'request_id' => $requestId,
            'comment_id' => $commentId2,
            'user_id' => $userId,
            'is_closing' => false,
            'created_at' => now(),
        ]);

        $export = new RequestsReportExport(['allPeriod' => true]);
        $data = $export->collection();
        $row = $data->first(fn ($row) => $row->id == $requestId);

        $this->assertNotNull($row, 'Строка заявки должна быть в отчёте');

        // closing_comment пуст — нет флага
        $this->assertEmpty(strip_tags($row->closing_comment ?? ''));

        // last_comment — самый свежий по времени
        $this->assertStringContainsString(
            'Свежий комментарий',
            strip_tags($row->last_comment),
            'Без is_closing должен использоваться последний по времени'
        );
    }
}
