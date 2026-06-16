<?php

namespace App\Console\Commands;

use App\Services\PlanningRequest\AddressMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Разовая нормализация уже сохранённых адресов: срезает ведущий тип улицы
 * («улица Люблинская» → «Люблинская»), чтобы в интерфейсе не было «ул. улица …».
 *
 * Затрагивает только адреса, чьё street начинается с типа (импортированные).
 * Адреса, введённые вручную, хранят голое название и под фильтр не попадают.
 *
 * По умолчанию — сухой прогон (только показывает, что изменится).
 * Запись выполняется с флагом --apply.
 */
class StripAddressStreetType extends Command
{
    protected $signature = 'addresses:strip-street-type {--apply : Применить изменения (без флага — только показать)}';

    protected $description = 'Срезает ведущий тип улицы у сохранённых адресов (улица/ул./проспект/… → голое название)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $addresses = DB::table('addresses')->select('id', 'street')->orderBy('id')->get();

        $changes = [];
        foreach ($addresses as $address) {
            $street = (string) $address->street;
            $stripped = AddressMatcher::stripLeadingStreetType($street);

            if ($stripped !== trim($street)) {
                $changes[] = ['id' => $address->id, 'from' => $street, 'to' => $stripped];
            }
        }

        if (empty($changes)) {
            $this->info('Нечего менять: адресов с ведущим типом улицы не найдено.');

            return self::SUCCESS;
        }

        $this->info('Адресов к изменению: '.count($changes));
        foreach (array_slice($changes, 0, 20) as $c) {
            $this->line("  #{$c['id']}: «{$c['from']}» → «{$c['to']}»");
        }
        if (count($changes) > 20) {
            $this->line('  … и ещё '.(count($changes) - 20));
        }

        if (! $apply) {
            $this->warn('Сухой прогон. Для записи запустите с флагом --apply');

            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            foreach ($changes as $c) {
                DB::table('addresses')->where('id', $c['id'])->update(['street' => $c['to']]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Ошибка, изменения откатаны: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Готово. Обновлено адресов: '.count($changes));

        return self::SUCCESS;
    }
}
