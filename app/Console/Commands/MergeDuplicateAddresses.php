<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Слияние адресов-дублей (по городу/улице/дому).
 *
 * Для каждой группы дублей выбирается один «канонический» адрес, на него
 * перепривязываются ссылки из request_addresses и addresses_documents,
 * остальные адреса группы удаляются. По умолчанию — сухой прогон.
 *
 * Канон выбирается так: сначала адреса с координатами, затем с непустым
 * районом, затем с наименьшим id. Так сохраняем максимум данных.
 *
 * Зависит от того, что на адрес ссылаются ровно две таблицы:
 * request_addresses.address_id и addresses_documents.address_id.
 */
class MergeDuplicateAddresses extends Command
{
    protected $signature = 'addresses:merge-duplicates
                            {--apply : Применить слияние (без флага — только показать)}
                            {--limit= : Максимум групп для обработки}';

    protected $description = 'Сливает адреса-дубли (город/улица/дом) в один, перепривязывая заявки и документы';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit');

        $query = DB::table('addresses')
            ->select('city_id', 'street', 'houses', DB::raw('COUNT(*) as cnt'), DB::raw("string_agg(id::text, ',' ORDER BY id) as ids"))
            ->groupBy('city_id', 'street', 'houses')
            ->havingRaw('COUNT(*) > 1')
            ->orderByRaw('COUNT(*) DESC');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->info('Дубликаты адресов не найдены.');

            return self::SUCCESS;
        }

        $this->info('Групп дублей: '.$groups->count());

        $totalToDelete = 0;
        $totalReqMoved = 0;
        $totalDocMoved = 0;

        if ($apply) {
            DB::beginTransaction();
        }

        try {
            foreach ($groups as $group) {
                $ids = array_map('intval', explode(',', $group->ids));

                // Выбор канона: координаты → район → минимальный id.
                $rows = DB::table('addresses')
                    ->whereIn('id', $ids)
                    ->orderByRaw('(latitude IS NOT NULL AND longitude IS NOT NULL) DESC')
                    ->orderByRaw("(COALESCE(district, '') <> '') DESC")
                    ->orderBy('id')
                    ->get(['id', 'street', 'houses', 'district', 'latitude']);

                $canonicalId = (int) $rows->first()->id;
                $dupIds = array_values(array_filter($ids, fn ($id) => $id !== $canonicalId));

                $reqMoved = DB::table('request_addresses')->whereIn('address_id', $dupIds)->count();
                $docMoved = DB::table('addresses_documents')->whereIn('address_id', $dupIds)->count();

                $this->line(sprintf(
                    '  «%s, %s» (%s): оставить #%d, удалить #%s | заявок: %d, док-ов: %d',
                    $rows->first()->street,
                    $rows->first()->houses,
                    $group->cnt,
                    $canonicalId,
                    implode(',', $dupIds),
                    $reqMoved,
                    $docMoved
                ));

                $totalToDelete += count($dupIds);
                $totalReqMoved += $reqMoved;
                $totalDocMoved += $docMoved;

                if ($apply) {
                    $this->mergeInto($canonicalId, $dupIds);
                }
            }

            if ($apply) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if ($apply) {
                DB::rollBack();
            }
            $this->error('Ошибка, изменения откатаны: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(($apply ? 'Слияние выполнено. ' : 'Сухой прогон. ').
            "Удаляемых адресов: {$totalToDelete}, перепривязка заявок: {$totalReqMoved}, документов: {$totalDocMoved}");

        if (! $apply) {
            $this->warn('Для записи запустите с флагом --apply');
        }

        return self::SUCCESS;
    }

    /**
     * Перепривязывает ссылки с дублей на канонический адрес и удаляет дубли.
     * Выполняется внутри уже открытой транзакции (см. handle).
     *
     * @param  list<int>  $dupIds
     */
    public function mergeInto(int $canonicalId, array $dupIds): void
    {
        if (empty($dupIds)) {
            return;
        }

        // request_addresses: PK (request_id, address_id). Сначала убираем
        // ссылки дубля для заявок, уже привязанных к канону (иначе конфликт PK),
        // затем перепривязываем оставшиеся.
        DB::table('request_addresses as ra')
            ->whereIn('ra.address_id', $dupIds)
            ->whereExists(function ($q) use ($canonicalId) {
                $q->select(DB::raw(1))
                    ->from('request_addresses as r2')
                    ->whereColumn('r2.request_id', 'ra.request_id')
                    ->where('r2.address_id', $canonicalId);
            })
            ->delete();

        DB::table('request_addresses')
            ->whereIn('address_id', $dupIds)
            ->update(['address_id' => $canonicalId]);

        // addresses_documents: простой перенос (своя PK по id).
        DB::table('addresses_documents')
            ->whereIn('address_id', $dupIds)
            ->update(['address_id' => $canonicalId]);

        // Удаляем дубли-адреса.
        DB::table('addresses')->whereIn('id', $dupIds)->delete();
    }
}
