<?php

namespace App\Console\Commands;

use App\Services\Wms\WmsEquipmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Живое обновление оборудования (комплекты H-* + машины со склада) для ОТКРЫТЫХ
 * сегодняшних заявок: смотрит, что сейчас на руках у участников бригады, и пишет в заявку.
 * Закрытые заявки не трогает (там значение заморожено при закрытии).
 *
 * Запускать по расписанию (см. routes/console.php), напр. раз в час.
 */
class RefreshRequestEquipment extends Command
{
    protected $signature = 'wms:refresh-equipment';

    protected $description = 'Обновляет оборудование (склад) для открытых сегодняшних заявок';

    public function handle(WmsEquipmentService $service): int
    {
        if (! Schema::hasTable('request_equipment')) {
            $this->warn('Таблица request_equipment отсутствует — пропуск.');

            return self::SUCCESS;
        }

        // Открытые сегодняшние заявки с бригадой (не закрыта/отменена/планирование/удалена).
        $ids = DB::table('requests')
            ->whereRaw('DATE(execution_date) = CURRENT_DATE')
            ->whereNotIn('status_id', [4, 5, 6, 7])
            ->whereNotNull('brigade_id')
            ->pluck('id');

        foreach ($ids as $id) {
            $service->captureSnapshotForRequest((int) $id);
        }

        $this->info('Обновлено заявок: '.$ids->count());

        return self::SUCCESS;
    }
}
