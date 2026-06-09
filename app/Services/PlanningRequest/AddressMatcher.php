<?php

namespace App\Services\PlanningRequest;

use Illuminate\Support\Facades\DB;

/**
 * Сопоставление адресов из загружаемого файла с уже существующими в БД.
 *
 * ВАЖНО: это единственный источник правды для read-only поиска дублей адресов.
 * Тот же поиск использует и предосмотр, и реальная загрузка (через
 * PlanningRequestController::findOrCreateAddress), поэтому «что показали в
 * предосмотре» гарантированно совпадает с «что запишется в БД» — это и есть
 * защита от повторного появления дублей в таблице addresses.
 *
 * Нормализация улицы (normalizeStreetKey) и регэксп типа улицы ($rxType) должны
 * оставаться СИНХРОННЫМИ между собой и со scripts/fix_addresses_dedupe.sql.
 */
class AddressMatcher
{
    /**
     * Регэксп для удаления типа улицы внутри SQL (Postgres word boundaries \m \M).
     * Должен ТОЧНО совпадать со списком типов в normalizeStreetKey().
     */
    private const STREET_TYPE_SQL_REGEX = '\m(улица|ул\.|переулок|пер\.|проспект|пр-кт|пр\.|шоссе|ш\.|бульвар|б-р|проезд|пр-д|набережная|наб\.|тупик|линия|аллея)\M';

    /**
     * Локаленезависимый поиск города по имени (без учёта регистра кириллицы).
     * Возвращает строку города или null, если город не найден в БД.
     */
    public function findCity(string $cityName): ?object
    {
        $cityName = trim($cityName);
        if ($cityName === '') {
            return null;
        }

        return DB::table('cities')
            ->whereRaw(
                "lower(translate(name, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя')) = lower(translate(?, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя'))",
                [$cityName]
            )
            ->first();
    }

    /**
     * Найти id уже существующего адреса.
     *
     * Сначала ищем по НОРМАЛИЗОВАННОМУ ключу (город + улица без типа и без
     * хвоста «дом N…» + дом/корпус) — это типонезависимый поиск дублей.
     * Если не нашли — фолбэк по ТОЧНОМУ совпадению строк, синхронно с веткой
     * создания в PlanningRequestController::findOrCreateAddress. Фолбэк важен,
     * когда нормализация на стороне БД не срабатывает (например, lc_ctype=C
     * не распознаёт кириллицу как слово в \m…\M), но строки идентичны.
     *
     * Возвращает id или null, если совпадения нет.
     */
    public function findExistingAddressId(int $cityId, string $street, string $houses): ?int
    {
        $streetKey = $this->normalizeStreetKey($street);
        $houseKey = $this->normalizeHouseKey($houses);

        $existing = DB::selectOne(
            "
            SELECT id
            FROM addresses
            WHERE city_id = ?
              AND btrim(regexp_replace(
                    regexp_replace(
                      regexp_replace(lower(translate(street, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя')), ?, '', 'g'),
                      '[\s,\.]*(дом|д\.)\s*[0-9].*$', '', 'i'),
                    '[\s,\.]+', ' ', 'g')
                  ) = ?
              AND btrim(regexp_replace(lower(translate(coalesce(houses,''), 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя')), '[\s,\.]+', ' ', 'g')) = ?
            ORDER BY id
            LIMIT 1
            ",
            [$cityId, self::STREET_TYPE_SQL_REGEX, $streetKey, $houseKey]
        );

        if ($existing) {
            return $existing->id;
        }

        // Фолбэк: точное совпадение строк (как в findOrCreateAddress).
        $exact = DB::table('addresses')
            ->where('city_id', $cityId)
            ->where('street', $street)
            ->where('district', '')
            ->where('houses', $houses)
            ->first();

        return $exact?->id;
    }

    /**
     * Сводный ключ адреса для дедупликации в рамках одного файла
     * (две одинаковые строки → один новый адрес).
     */
    public function batchKey(int $cityId, string $street, string $houses): string
    {
        return $cityId.'|'.$this->normalizeStreetKey($street).'|'.$this->normalizeHouseKey($houses);
    }

    /**
     * Данные существующего адреса для отображения в предосмотре.
     */
    public function getAddressForDisplay(int $addressId): ?object
    {
        return DB::table('addresses as a')
            ->leftJoin('cities as c', 'a.city_id', '=', 'c.id')
            ->where('a.id', $addressId)
            ->select('a.id', 'a.street', 'a.houses', 'a.district', 'c.name as city_name')
            ->first();
    }

    /**
     * Нормализованный ключ улицы для поиска дублей.
     * Должен СИНХРОННО совпадать с SQL-выражением в findExistingAddressId().
     */
    public function normalizeStreetKey(string $street): string
    {
        $s = mb_strtolower($street, 'UTF-8');
        // 1) убрать хвост "дом N, ..." (на случай, если он остался в street)
        $s = preg_replace('/[\s,\.]*(?:дом|д\.)\s*[0-9].*$/iu', '', $s);
        // 2) убрать тип улицы где угодно по границе слова
        $s = preg_replace(
            '/\b(улица|ул\.|переулок|пер\.|проспект|пр-кт|пр\.|шоссе|ш\.|бульвар|б-р|проезд|пр-д|набережная|наб\.|тупик|линия|аллея)\b/iu',
            '',
            $s
        );
        // 3) сжать пробелы/запятые/точки
        $s = preg_replace('/[\s,\.]+/u', ' ', $s);

        return trim($s);
    }

    /**
     * Нормализованный ключ для houses ("8А, корпус 1" -> "8а корпус 1").
     */
    public function normalizeHouseKey(string $houses): string
    {
        $s = mb_strtolower($houses, 'UTF-8');
        $s = preg_replace('/[\s,\.]+/u', ' ', $s);

        return trim($s);
    }
}
