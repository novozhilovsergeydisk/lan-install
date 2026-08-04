<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Правка фактических количеств выполненных работ из раздела «Отчёты».
 *
 * Зачем: монтажники пишут в комментарии «выполнено 7 из 13», а поле с количеством
 * не меняют — в выгрузку уходят завышенные объёмы. Здесь даём поправить факт,
 * не трогая план (по нему видно, сколько работ ставилось изначально).
 *
 * Как хранится план и факт в work_parameters:
 *   is_planning = true  — плановая запись (создаётся вместе с заявкой);
 *   is_planning = false — фактическая (пишется при закрытии заявки).
 * Если у типа работ несколько фактических записей, актуальной считается последняя
 * по id — так же, как их читает выгрузка (RequestsReportExport).
 */
class WorkParameterController extends Controller
{
    /**
     * Проверка роли администратора.
     *
     * Роли могут быть уже загружены в объект пользователя (так делают другие контроллеры),
     * а могут отсутствовать — тогда читаем из базы. Полагаться только на $user->isAdmin
     * нельзя: он выставляется не на всех путях выполнения.
     */
    private function isAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if (isset($user->roles) && is_array($user->roles)) {
            return in_array('admin', $user->roles, true);
        }

        return DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $user->id)
            ->where('roles.name', 'admin')
            ->exists();
    }

    /**
     * Список работ заявки: тип, плановое и фактическое количество.
     */
    public function index(Request $request, $requestId)
    {
        try {
            $exists = DB::table('requests')->where('id', $requestId)->exists();
            if (! $exists) {
                return response()->json(['success' => false, 'message' => 'Заявка не найдена'], 404);
            }

            // Разделение плана и факта повторяет логику выгрузки (RequestsReportExport):
            // план  — ПЕРВАЯ запись по id (создаётся вместе с заявкой), без фильтра по флагу;
            // факт  — ПОСЛЕДНЯЯ запись с is_planning = false (дописывается при закрытии).
            // На флаг is_planning полагаться нельзя: в базе им помечены лишь ~2% записей,
            // фактически план и факт различаются порядком добавления.
            $rows = DB::select('
                SELECT
                    wpt.id AS parameter_type_id,
                    wpt.name AS parameter_name,
                    (
                        SELECT wp.quantity FROM work_parameters wp
                        WHERE wp.request_id = ? AND wp.parameter_type_id = wpt.id
                        ORDER BY wp.id ASC LIMIT 1
                    ) AS planned_quantity,
                    (
                        SELECT wp.quantity FROM work_parameters wp
                        WHERE wp.request_id = ? AND wp.parameter_type_id = wpt.id
                          AND wp.is_planning = false
                        ORDER BY wp.id DESC LIMIT 1
                    ) AS actual_quantity,
                    (
                        SELECT wp.id FROM work_parameters wp
                        WHERE wp.request_id = ? AND wp.parameter_type_id = wpt.id
                          AND wp.is_planning = false
                        ORDER BY wp.id DESC LIMIT 1
                    ) AS actual_id,
                    (
                        SELECT count(*) FROM work_parameters wp
                        WHERE wp.request_id = ? AND wp.parameter_type_id = wpt.id
                    ) AS records_count
                FROM work_parameter_types wpt
                WHERE wpt.is_deleted = false
                  AND EXISTS (
                      SELECT 1 FROM work_parameters wp
                      WHERE wp.request_id = ? AND wp.parameter_type_id = wpt.id
                  )
                ORDER BY wpt.name
            ', [$requestId, $requestId, $requestId, $requestId, $requestId]);

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка получения параметров работ', ['request_id' => $requestId, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Не удалось получить параметры работ'], 500);
        }
    }

    /**
     * Обновление ФАКТИЧЕСКИХ количеств. Плановые записи не трогаем.
     * Каждое изменение пишется в work_parameter_edits (было / стало / кто / когда).
     */
    public function update(Request $request, $requestId)
    {
        $user = $request->user();

        // Править количества может только администратор: цифры уходят в отчётность заказчику.
        if (! $this->isAdmin($user)) {
            return response()->json(['success' => false, 'message' => 'Недостаточно прав'], 403);
        }

        $validated = $request->validate([
            'parameters' => 'required|array|min:1',
            'parameters.*.parameter_type_id' => 'required|integer|exists:work_parameter_types,id',
            'parameters.*.quantity' => 'required|integer|min:0',
        ]);

        $requestRow = DB::table('requests')->where('id', $requestId)->first();
        if (! $requestRow) {
            return response()->json(['success' => false, 'message' => 'Заявка не найдена'], 404);
        }

        DB::beginTransaction();

        try {
            $changed = 0;

            foreach ($validated['parameters'] as $param) {
                $typeId = (int) $param['parameter_type_id'];
                $newQuantity = (int) $param['quantity'];

                // Актуальная фактическая запись — последняя по id (так же читает выгрузка).
                $actual = DB::table('work_parameters')
                    ->where('request_id', $requestId)
                    ->where('parameter_type_id', $typeId)
                    ->where('is_planning', false)
                    ->orderByDesc('id')
                    ->first();

                $totalRecords = DB::table('work_parameters')
                    ->where('request_id', $requestId)
                    ->where('parameter_type_id', $typeId)
                    ->count();

                // Единственная запись работает и как план, и как факт (выгрузка берёт
                // первую как план, последнюю как факт). Правя её напрямую, мы затёрли бы
                // и плановое значение — поэтому добавляем отдельную фактическую запись.
                if ($actual && $totalRecords === 1) {
                    if ((int) $actual->quantity === $newQuantity) {
                        continue;
                    }

                    $newId = DB::table('work_parameters')->insertGetId([
                        'request_id' => $requestId,
                        'parameter_type_id' => $typeId,
                        'quantity' => $newQuantity,
                        'is_planning' => false,
                        'is_done' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('work_parameter_edits')->insert([
                        'work_parameter_id' => $newId,
                        'request_id' => $requestId,
                        'old_quantity' => $actual->quantity,
                        'new_quantity' => $newQuantity,
                        'edited_by_user_id' => $user?->id,
                        'edited_at' => now(),
                    ]);

                    $changed++;

                    continue;
                }

                if ($actual) {
                    if ((int) $actual->quantity === $newQuantity) {
                        continue; // значение не изменилось — не пишем историю впустую
                    }

                    DB::table('work_parameters')
                        ->where('id', $actual->id)
                        ->update(['quantity' => $newQuantity, 'updated_at' => now()]);

                    DB::table('work_parameter_edits')->insert([
                        'work_parameter_id' => $actual->id,
                        'request_id' => $requestId,
                        'old_quantity' => $actual->quantity,
                        'new_quantity' => $newQuantity,
                        'edited_by_user_id' => $user?->id,
                        'edited_at' => now(),
                    ]);
                } else {
                    // Фактической записи ещё нет (заявку не закрывали либо работу не отмечали) —
                    // создаём её, чтобы правка попала в выгрузку.
                    $newId = DB::table('work_parameters')->insertGetId([
                        'request_id' => $requestId,
                        'parameter_type_id' => $typeId,
                        'quantity' => $newQuantity,
                        'is_planning' => false,
                        'is_done' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('work_parameter_edits')->insert([
                        'work_parameter_id' => $newId,
                        'request_id' => $requestId,
                        'old_quantity' => null,
                        'new_quantity' => $newQuantity,
                        'edited_by_user_id' => $user?->id,
                        'edited_at' => now(),
                    ]);
                }

                $changed++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $changed > 0 ? 'Количества обновлены' : 'Изменений нет',
                'changed' => $changed,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Ошибка обновления параметров работ', ['request_id' => $requestId, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Не удалось сохранить количества'], 500);
        }
    }
}
