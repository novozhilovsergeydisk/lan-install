<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * При СМЕНЕ бригады у заявки пишется автокомментарий с членами СТАРОЙ (заменённой) бригады
 * и пользователем, который сменил. Новая бригада в комментарий не пишется. Первое назначение
 * (старой бригады не было) — комментарий не создаётся.
 *
 * Всё в транзакции с откатом — БД не портится.
 */
class BrigadeChangeCommentTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

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

    /** Смена бригады → комментарий со старой бригадой (её бригадир) + «Сменил». */
    public function test_brigade_change_logs_old_brigade(): void
    {
        $this->authenticateAdmin();

        // Заявка с уже назначенной бригадой, у которой есть валидный бригадир.
        $req = DB::selectOne('
            SELECT r.id, r.brigade_id, bl.fio AS leader_fio
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id AND b.is_deleted = false
            JOIN employees bl ON bl.id = b.leader_id AND bl.is_deleted = false
            WHERE r.brigade_id IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ');
        if (! $req) {
            $this->markTestSkipped('Нет заявки со старой бригадой и бригадиром');
        }

        // Другая бригада для назначения.
        $newBrigade = DB::selectOne('SELECT id FROM brigades WHERE id <> ? AND is_deleted = false ORDER BY id DESC LIMIT 1', [$req->brigade_id]);
        if (! $newBrigade) {
            $this->markTestSkipped('Нет второй бригады');
        }

        $response = $this->post('/api/requests/update-brigade', [
            'request_id' => $req->id,
            'brigade_id' => $newBrigade->id,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $comment = DB::table('request_comments as rc')
            ->join('comments as c', 'c.id', '=', 'rc.comment_id')
            ->where('rc.request_id', $req->id)
            ->orderByDesc('c.created_at')
            ->value('c.comment');

        $this->assertNotNull($comment, 'Должен появиться комментарий о смене бригады');
        $this->assertStringContainsString('Заменена бригады', $comment);
        $this->assertStringContainsString('Сменил', $comment);
        $this->assertStringContainsString($req->leader_fio, $comment, 'В комментарии — бригадир СТАРОЙ бригады');
    }

    /** Первое назначение (старой бригады не было) → комментарий о смене не создаётся. */
    public function test_first_assignment_does_not_log(): void
    {
        $this->authenticateAdmin();

        $req = DB::selectOne('SELECT id FROM requests WHERE brigade_id IS NULL ORDER BY id DESC LIMIT 1');
        if (! $req) {
            $this->markTestSkipped('Нет заявки без бригады');
        }
        $brigade = DB::selectOne('SELECT id FROM brigades WHERE is_deleted = false ORDER BY id DESC LIMIT 1');
        if (! $brigade) {
            $this->markTestSkipped('Нет бригад');
        }

        $response = $this->post('/api/requests/update-brigade', [
            'request_id' => $req->id,
            'brigade_id' => $brigade->id,
            '_token' => csrf_token(),
        ]);
        $response->assertStatus(200);

        $hasChangeComment = DB::table('request_comments as rc')
            ->join('comments as c', 'c.id', '=', 'rc.comment_id')
            ->where('rc.request_id', $req->id)
            ->where('c.comment', 'like', 'Заменена бригады%')
            ->exists();

        $this->assertFalse($hasChangeComment, 'При первом назначении комментарий о смене не пишем');
    }
}
