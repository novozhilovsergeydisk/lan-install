<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Автокомментарий при назначении/переназначении бригады на заявку.
 * Формат: "Назначена бригада: Иванов И. (бригадир), Петров П. Назначил: Сидоров С."
 */
class BrigadeAssignmentCommentTest extends TestCase
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

    private function openRequestWithBrigade()
    {
        return DB::selectOne('
            SELECT r.id, r.brigade_id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            JOIN employees e ON (e.id = b.leader_id OR e.id IN (SELECT employee_id FROM brigade_members WHERE brigade_id = b.id))
            JOIN users u ON u.id = e.user_id
            WHERE r.status_id != 4 AND e.is_deleted = false AND u.email IS NOT NULL
            ORDER BY r.id DESC LIMIT 1
        ');
    }

    private function anotherBrigade(): ?object
    {
        $currentRequest = $this->openRequestWithBrigade();
        if (! $currentRequest) {
            return null;
        }

        return DB::selectOne('
            SELECT b.id
            FROM brigades b
            WHERE b.id != ? AND b.is_deleted = false
            ORDER BY b.id DESC LIMIT 1
        ', [$currentRequest->brigade_id]);
    }

    private function getLatestComment(int $requestId): ?string
    {
        $comment = DB::selectOne('
            SELECT c.comment
            FROM comments c
            JOIN request_comments rc ON rc.comment_id = c.id
            WHERE rc.request_id = ?
            ORDER BY c.id DESC LIMIT 1
        ', [$requestId]);

        return $comment->comment ?? null;
    }

    private function getCommentCount(int $requestId): int
    {
        return (int) DB::table('comments')
            ->join('request_comments', 'comments.id', '=', 'request_comments.comment_id')
            ->where('request_comments.request_id', $requestId)
            ->count();
    }

    /** Одиночное назначение бригады создаёт автокомментарий. */
    public function test_single_assignment_creates_comment(): void
    {
        $this->authenticateAdmin();
        $request = $this->openRequestWithBrigade();
        if (! $request) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }

        $newBrigade = $this->anotherBrigade();
        if (! $newBrigade) {
            $this->markTestSkipped('Нет второй бригады для переназначения');
        }

        $countBefore = $this->getCommentCount($request->id);

        $response = $this->postJson('/api/requests/update-brigade', [
            'brigade_id' => $newBrigade->id,
            'request_id' => $request->id,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertEquals($countBefore + 1, $this->getCommentCount($request->id));

        $comment = $this->getLatestComment($request->id);
        $this->assertStringStartsWith('Назначена бригада:', $comment);
        $this->assertStringContainsString('(бригадир)', $comment);
        $this->assertStringContainsString('Назначил:', $comment);
    }

    /** Массовое назначение бригады создаёт автокомментарий для каждой заявки. */
    public function test_mass_assignment_creates_comment_per_request(): void
    {
        $this->authenticateAdmin();

        $requests = DB::select('
            SELECT r.id, r.brigade_id
            FROM requests r
            JOIN brigades b ON b.id = r.brigade_id
            WHERE r.status_id != 4 AND b.is_deleted = false
            ORDER BY r.id DESC LIMIT 2
        ');

        if (count($requests) < 2) {
            $this->markTestSkipped('Нужно минимум 2 открытых заявки с бригадами');
        }

        $newBrigade = DB::selectOne('
            SELECT b.id FROM brigades b
            WHERE b.is_deleted = false
            ORDER BY b.id DESC LIMIT 1
        ');

        $requestIds = array_column($requests, 'id');
        $countsBefore = [];
        foreach ($requestIds as $id) {
            $countsBefore[$id] = $this->getCommentCount($id);
        }

        $response = $this->postJson('/api/requests/update-brigade-mass', [
            'brigade_id' => $newBrigade->id,
            'request_ids' => $requestIds,
        ]);

        $response->assertJson(['success' => true]);

        foreach ($requestIds as $id) {
            $this->assertEquals($countsBefore[$id] + 1, $this->getCommentCount($id), "Комментарий не создан для заявки {$id}");
            $comment = $this->getLatestComment($id);
            $this->assertStringStartsWith('Назначена бригада:', $comment);
        }
    }

    /** Комментарий содержит ФИО бригадира и членов бригады. */
    public function test_comment_contains_brigade_members(): void
    {
        $this->authenticateAdmin();
        $request = $this->openRequestWithBrigade();
        if (! $request) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }

        $newBrigade = DB::selectOne('
            SELECT b.id FROM brigades b WHERE b.is_deleted = false ORDER BY b.id DESC LIMIT 1
        ');

        $response = $this->postJson('/api/requests/update-brigade', [
            'brigade_id' => $newBrigade->id,
            'request_id' => $request->id,
        ]);

        $response->assertJson(['success' => true]);

        $comment = $this->getLatestComment($request->id);

        $leader = DB::selectOne('
            SELECT bl.fio FROM brigades b JOIN employees bl ON bl.id = b.leader_id WHERE b.id = ?
        ', [$newBrigade->id]);
        $this->assertStringContainsString($leader->fio, $comment);
        $this->assertStringContainsString('(бригадир)', $comment);
    }

    /** Комментарий содержит ФИО назначившего. */
    public function test_comment_contains_assigner_name(): void
    {
        $this->authenticateAdmin();
        $request = $this->openRequestWithBrigade();
        if (! $request) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }

        $newBrigade = DB::selectOne('
            SELECT b.id FROM brigades b WHERE b.is_deleted = false ORDER BY b.id DESC LIMIT 1
        ');

        $response = $this->postJson('/api/requests/update-brigade', [
            'brigade_id' => $newBrigade->id,
            'request_id' => $request->id,
        ]);

        $response->assertJson(['success' => true]);

        $comment = $this->getLatestComment($request->id);
        $admin = User::where('email', 'admin@appuse.ru')->first();
        $adminFio = DB::selectOne('SELECT fio FROM employees WHERE user_id = ?', [$admin->id])->fio;
        $this->assertStringContainsString($adminFio, $comment);
    }

    /** Назначение той же самой бригады (переназначение) тоже создаёт комментарий. */
    public function test_reassignment_to_same_brigade_creates_comment(): void
    {
        $this->authenticateAdmin();
        $request = $this->openRequestWithBrigade();
        if (! $request) {
            $this->markTestSkipped('Нет открытой заявки с бригадой');
        }

        $countBefore = $this->getCommentCount($request->id);

        $response = $this->postJson('/api/requests/update-brigade', [
            'brigade_id' => $request->brigade_id,
            'request_id' => $request->id,
        ]);

        $response->assertJson(['success' => true]);
        $this->assertEquals($countBefore + 1, $this->getCommentCount($request->id));
    }
}
