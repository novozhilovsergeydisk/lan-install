<?php

namespace App\Services\Brigade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Автокомментарий к заявке при СМЕНЕ бригады.
 *
 * Фиксирует состав СТАРОЙ (заменённой) бригады и пользователя, который сменил.
 * Новая бригада в комментарий не пишется — она и так видна на заявке.
 *
 * Best-effort: любые ошибки логируются, но смену бригады не ломают (система в проде).
 */
class BrigadeChangeCommentService
{
    /**
     * @param  int       $requestId     заявка, у которой сменили бригаду
     * @param  int|null  $oldBrigadeId  бригада, которая была ДО смены (null → первое назначение, фиксировать нечего)
     * @param  int       $actorUserId   пользователь, сменивший бригаду
     */
    public function logChange(int $requestId, ?int $oldBrigadeId, int $actorUserId): void
    {
        // Смена = была старая бригада. Если её не было (первое назначение) — комментарий не пишем.
        if (! $oldBrigadeId) {
            return;
        }

        try {
            $members = $this->brigadeMembers($oldBrigadeId);
            if (empty($members)) {
                return;
            }

            $actorFio = DB::table('employees')->where('user_id', $actorUserId)->value('fio') ?? 'Пользователь';

            $comment = 'Заменена бригады: '.implode(', ', $members).'. Сменил: '.$actorFio;

            $commentId = DB::table('comments')->insertGetId([
                'comment' => $comment,
                'created_at' => now(),
            ]);

            DB::table('request_comments')->insert([
                'request_id' => $requestId,
                'comment_id' => $commentId,
                'user_id' => $actorUserId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Автокомментарий смены бригады не создан', [
                'request_id' => $requestId,
                'old_brigade_id' => $oldBrigadeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Состав бригады: бригадир (помечен) + члены. Удалённых сотрудников/бригады пропускаем.
     *
     * @return string[]  напр. ['Иванов И. (бригадир)', 'Петров П.']
     */
    private function brigadeMembers(int $brigadeId): array
    {
        $leader = DB::selectOne('
            SELECT bl.fio
            FROM brigades b
            JOIN employees bl ON bl.id = b.leader_id
            WHERE b.id = ? AND bl.is_deleted = false
        ', [$brigadeId]);

        $members = DB::select('
            SELECT bm_e.fio
            FROM brigade_members bm
            JOIN employees bm_e ON bm_e.id = bm.employee_id
            JOIN brigades b ON b.id = bm.brigade_id
            WHERE bm.brigade_id = ? AND bm_e.is_deleted = false AND bm.employee_id != b.leader_id
            ORDER BY bm_e.fio
        ', [$brigadeId]);

        $list = [];
        if ($leader && $leader->fio) {
            $list[] = $leader->fio.' (бригадир)';
        }
        foreach ($members as $m) {
            $list[] = $m->fio;
        }

        return $list;
    }
}
