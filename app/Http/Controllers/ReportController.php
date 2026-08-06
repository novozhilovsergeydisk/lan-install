<?php

namespace App\Http\Controllers;

use App\Exports\RequestsReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Сквозной поиск по отчётам: имя/организация контакта, телефон (с очисткой
     * от масок с обеих сторон) и текст комментариев заявки.
     *
     * Версия для методов на сыром SQL ($sqlBase-паттерн). Возвращает ДВЕ независимые
     * пары sql/bindings:
     *  - where_sql/where_bindings — фрагмент ' AND (...)' для фильтрации (как раньше);
     *  - select_sql/select_bindings — доп. колонки search_match_fio/organization/
     *    phone/comment (boolean), чтобы фронт мог показать «Найдено по: …».
     * Порядок важен: select_sql должен оказаться в тексте запроса РАНЬШЕ where_sql
     * (SELECT предшествует WHERE), поэтому и select_bindings должны идти в массиве
     * bindings раньше where_bindings — вызывающий код обязан это соблюсти.
     * Пустой/отсутствующий поисковый запрос — все фрагменты пустые, фильтр не применяется.
     */
    private function buildSearchSql(?string $search): array
    {
        $search = trim((string) $search);
        if ($search === '') {
            return ['where_sql' => '', 'where_bindings' => [], 'select_sql' => '', 'select_bindings' => []];
        }

        $like = '%'.$this->escapeLikeValue($search).'%';
        $phoneDigits = $this->extractPhoneDigits($search);

        $commentExistsSql = 'EXISTS (
                SELECT 1 FROM request_comments rc_search
                JOIN comments cm_search ON rc_search.comment_id = cm_search.id
                WHERE rc_search.request_id = r.id AND cm_search.comment ILIKE ?
            )';

        $whereBindings = [$like, $like, $like];
        $whereSql = " AND (
            c.fio ILIKE ?
            OR c.organization ILIKE ?
            OR {$commentExistsSql}";

        $selectBindings = [$like, $like, $like];
        $selectSql = ",
            (c.fio ILIKE ?) AS search_match_fio,
            (c.organization ILIKE ?) AS search_match_organization,
            {$commentExistsSql} AS search_match_comment";

        if ($phoneDigits !== null) {
            // Матчим только с начала (код/префикс) или с конца (хвост номера) —
            // не «где угодно в середине»: у 10-значного мобильного номера почти
            // любая короткая цифровая последовательность случайно где-нибудь да
            // встретится (см. регресс с «900», найденный заказчиком 05.08.2026).
            $phoneCmpSql = "RIGHT(regexp_replace(COALESCE(c.phone, ''), '[^0-9]', '', 'g'), 10) LIKE ?"
                         .' OR RIGHT(regexp_replace(COALESCE(c.phone, \'\'), \'[^0-9]\', \'\', \'g\'), 10) LIKE ?';

            $whereSql .= ' OR '.$phoneCmpSql;
            $whereBindings[] = $phoneDigits.'%';
            $whereBindings[] = '%'.$phoneDigits;

            $selectSql .= ",
            ({$phoneCmpSql}) AS search_match_phone";
            $selectBindings[] = $phoneDigits.'%';
            $selectBindings[] = '%'.$phoneDigits;
        } else {
            $selectSql .= ', false AS search_match_phone';
        }

        $whereSql .= ')';

        return [
            'where_sql' => $whereSql, 'where_bindings' => $whereBindings,
            'select_sql' => $selectSql, 'select_bindings' => $selectBindings,
        ];
    }

    /**
     * Та же логика поиска для методов на Query Builder — накладывает условие
     * прямо на переданный $query (алиас клиента везде 'c', заявки — 'r') и
     * добавляет колонки search_match_* для бейджей «Найдено по: …» на фронте.
     *
     * $hasGroupBy — методы со STRING_AGG(brigade_members) группируют по r.id и
     * колонкам клиента; Postgres не разрешит добавить необёрнутое boolean-выражение
     * в SELECT такого запроса (потребует добавить его в GROUP BY). bool_or(...)
     * решает это без изменения GROUP BY: в пределах группы значение и так одно.
     */
    private function applySearchFilter($query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $like = '%'.$this->escapeLikeValue($search).'%';
        $phoneDigits = $this->extractPhoneDigits($search);

        $query->where(function ($q) use ($like, $phoneDigits) {
            $q->where('c.fio', 'ILIKE', $like)
                ->orWhere('c.organization', 'ILIKE', $like)
                ->orWhereExists(function ($sub) use ($like) {
                    $sub->select(DB::raw(1))
                        ->from('request_comments as rc_search')
                        ->join('comments as cm_search', 'rc_search.comment_id', '=', 'cm_search.id')
                        ->whereColumn('rc_search.request_id', 'r.id')
                        ->where('cm_search.comment', 'ILIKE', $like);
                });

            if ($phoneDigits !== null) {
                // Матчим только с начала или с конца хвоста номера — не «где угодно
                // в середине» (см. buildSearchSql — тот же аргумент).
                $q->orWhereRaw("RIGHT(regexp_replace(COALESCE(c.phone, ''), '[^0-9]', '', 'g'), 10) LIKE ?", [$phoneDigits.'%'])
                    ->orWhereRaw("RIGHT(regexp_replace(COALESCE(c.phone, ''), '[^0-9]', '', 'g'), 10) LIKE ?", ['%'.$phoneDigits]);
            }
        });
    }

    /**
     * Добавляет search_match_fio/organization/phone/comment колонки в $query —
     * ОТДЕЛЬНО от applySearchFilter() и вызывается ПОСЛЕ расчёта total.
     *
     * Почему отдельно: методы с пагинацией считают total через
     * `DB::table(DB::raw("({$query->toSql()}) as sub"))->mergeBindings($query)->count()`.
     * Laravel's `count()` внутри вызывает `cloneWithoutBindings(['select'])` — считая,
     * что select-bindings принадлежат заменяемому списку колонок COUNT-запроса.
     * Но здесь они на самом деле нужны для плейсхолдеров ВНУТРИ подзапроса, который
     * подставлен в FROM через DB::raw() как текст, а не для SELECT самого total-запроса.
     * Laravel их всё равно вырезает — итог: "bind message supplies N, requires N+k"
     * (поймано именно на этом при тестировании 05.08.2026). Поэтому колонки с
     * причиной совпадения добавляются только к финальному запросу данных, когда
     * total уже посчитан и больше не будет пересобираться в подзапрос.
     */
    private function applySearchMatchColumns($query, ?string $search, bool $hasGroupBy = false): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $like = '%'.$this->escapeLikeValue($search).'%';
        $phoneDigits = $this->extractPhoneDigits($search);

        $commentExistsSql = 'EXISTS (
            SELECT 1 FROM request_comments rc_search_sel
            JOIN comments cm_search_sel ON rc_search_sel.comment_id = cm_search_sel.id
            WHERE rc_search_sel.request_id = r.id AND cm_search_sel.comment ILIKE ?
        )';

        $fioExpr = 'c.fio ILIKE ?';
        $orgExpr = 'c.organization ILIKE ?';
        $commentExpr = $commentExistsSql;

        if ($hasGroupBy) {
            $fioExpr = "bool_or({$fioExpr})";
            $orgExpr = "bool_or({$orgExpr})";
            $commentExpr = "bool_or({$commentExpr})";
        }

        $query->selectRaw("({$fioExpr}) AS search_match_fio", [$like]);
        $query->selectRaw("({$orgExpr}) AS search_match_organization", [$like]);
        $query->selectRaw("{$commentExpr} AS search_match_comment", [$like]);

        if ($phoneDigits !== null) {
            $phoneCmpSql = "RIGHT(regexp_replace(COALESCE(c.phone, ''), '[^0-9]', '', 'g'), 10) LIKE ?"
                         .' OR RIGHT(regexp_replace(COALESCE(c.phone, \'\'), \'[^0-9]\', \'\', \'g\'), 10) LIKE ?';
            $phoneExpr = $hasGroupBy ? "bool_or({$phoneCmpSql})" : $phoneCmpSql;
            $query->selectRaw("({$phoneExpr}) AS search_match_phone", [$phoneDigits.'%', '%'.$phoneDigits]);
        } else {
            $query->selectRaw('false AS search_match_phone');
        }
    }

    /**
     * Цифры телефона из поискового запроса без маски. Возвращает null, если строка
     * не похожа на телефон целиком (не только цифры и разделители маски) — иначе
     * цифры внутри обычного текста комментария («выполнено 37/50», «панелей 999»)
     * ложно матчили бы случайные номера телефонов, где эти цифры просто встречаются
     * (напр. код оператора +7 999...). Меньше 3 цифр тоже не фильтруем — иначе
     * 'LIKE %%' совпал бы вообще со всеми записями.
     *
     * Ведущую «7» или «8» снимаем ВСЕГДА (не только для полных номеров) — иначе
     * «+7 900» и «8 900» дают разные цифры (7900 / 8900) и находят разное, хотя
     * заказчик имеет в виду один и тот же код. Она встречается только как код
     * страны/выхода — у российского мобильного номера без него первая цифра
     * национальной части всегда «9», так что ложных срабатываний не будет.
     * Если после этого цифр 10 и больше — берём хвост в 10 (страховка на случай
     * полного номера длиннее ожидаемого).
     */
    private function extractPhoneDigits(string $search): ?string
    {
        if (! preg_match('/^[\d\s()+\-]+$/u', $search)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $search);

        if (strlen($digits) >= 2 && in_array($digits[0], ['7', '8'], true)) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) < 3) {
            return null;
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }

    /**
     * Экранирование спецсимволов LIKE/ILIKE (Postgres по умолчанию использует
     * обратный слеш как escape-символ — отдельный ESCAPE не нужен).
     */
    private function escapeLikeValue(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Экспорт отчета в Excel
     */
    public function export(Request $request)
    {
        try {
            $filters = $request->only([
                'startDate',
                'endDate',
                'employeeId',
                'addressId',
                'organization',
                'requestTypeId',
                'allPeriod',
                'search',
            ]);

            // Маппинг 'all_employees' и прочих пустых значений в null
            if (($filters['employeeId'] ?? '') === 'all_employees') {
                $filters['employeeId'] = null;
            }
            if (($filters['addressId'] ?? '') === 'all_addresses') {
                $filters['addressId'] = null;
            }
            if (($filters['organization'] ?? '') === 'all_organizations') {
                $filters['organization'] = null;
            }
            if (($filters['requestTypeId'] ?? '') === 'all_request_types') {
                $filters['requestTypeId'] = null;
            }

            // Преобразование дат в Y-m-d для БД
            if (! empty($filters['startDate']) && empty($filters['allPeriod'])) {
                try {
                    $filters['startDate'] = \Carbon\Carbon::createFromFormat('d.m.Y', $filters['startDate'])->format('Y-m-d');
                    $filters['endDate'] = \Carbon\Carbon::createFromFormat('d.m.Y', $filters['endDate'])->format('Y-m-d');
                } catch (\Exception $e) {
                    $filters['startDate'] = null;
                    $filters['endDate'] = null;
                }
            }

            // Логируем фильтры для отладки
            \Illuminate\Support\Facades\Log::info('Export filters:', $filters);

            return Excel::download(new RequestsReportExport($filters), 'export_'.now()->format('d_m_Y_H_i').'.xlsx');

        } catch (\Exception $e) {
            Log::error('Error in ReportController@export: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Ошибка экспорта: '.$e->getMessage()], 500);
        }
    }

    /**
     * Получение заявок за период по адресу и сотруднику
     */
    public function getAllPeriodByEmployeeAndAddress(Request $request)
    {
        try {
            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'addressId' => 'required|exists:addresses,id',
            ]);

            $employeeId = $validated['employeeId'];
            $addressId = $validated['addressId'];

            // Pagination parameters
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 20);
            $offset = ($page - 1) * $limit;

            $query = DB::table('requests as r')
                ->selectRaw("
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('request_subtypes AS rst', 'r.subtype_id', '=', 'rst.id')
                ->leftJoin('request_types AS rt', 'r.request_type_id', '=', 'rt.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where('addr.id', $addressId)
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                });

            $this->applySearchFilter($query, $request->input('search'));

            $query->groupBy([
                    'r.id', 'c.fio', 'c.phone', 'c.organization',
                    'rs.name', 'rs.color', 'rst.name', 'rt.name', 'rt.color', 'b.name', 'e.fio', 'op.fio',
                    'addr.street', 'addr.houses', 'addr.district',
                    'addr.city_id', 'ct.name', 'ct.postal_code',
                ]);

            // Calculate total before ordering and limiting
            $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
                ->mergeBindings($query)
                ->count();

            // Колонки search_match_* — ПОСЛЕ total (см. applySearchMatchColumns).
            $this->applySearchMatchColumns($query, $request->input('search'), hasGroupBy: true);

            $query->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC');

            // Apply pagination
            $query->limit($limit)->offset($offset);

            $requests = $query->get();

            // Optimization: Fetch only relevant brigade members and comments
            $requestIds = $requests->pluck('id')->toArray();
            $brigadeIds = $requests->pluck('brigade_id')->filter()->unique()->toArray();

            // Get Brigade Members
            $brigadeMembersWithDetails = [];
            if (! empty($brigadeIds)) {
                $placeholders = implode(',', array_fill(0, count($brigadeIds), '?'));
                $brigadeMembersWithDetails = DB::select(
                    "SELECT
                        bm.*,
                        b.name as brigade_name,
                        b.leader_id,
                        e.fio as employee_name,
                        e.phone as employee_phone,
                        e.group_role as employee_group_role,
                        e.sip as employee_sip,
                        e.position_id as employee_position_id,
                        el.fio as employee_leader_name,
                        el.phone as employee_leader_phone,
                        el.group_role as employee_leader_group_role,
                        el.sip as employee_leader_sip,
                        el.position_id as employee_leader_position_id
                    FROM brigade_members bm
                    JOIN brigades b ON bm.brigade_id = b.id
                    LEFT JOIN employees e ON bm.employee_id = e.id
                    LEFT JOIN employees el ON b.leader_id = el.id
                    WHERE bm.brigade_id IN ($placeholders)",
                    array_values($brigadeIds)
                );
            }

            // Get Comments
            $commentsByRequest = [];
            if (! empty($requestIds)) {
                $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                $requestComments = DB::select("
                    SELECT
                        rc.request_id,
                        c.id as comment_id,
                        c.comment,
                        c.created_at,
                        CASE 
                            WHEN e.fio IS NOT NULL THEN e.fio
                            WHEN u.name IS NOT NULL THEN u.name
                            WHEN u.email IS NOT NULL THEN u.email
                            ELSE 'Система'
                        END as author_name
                    FROM request_comments rc
                    JOIN comments c ON rc.comment_id = c.id
                    LEFT JOIN users u ON rc.user_id = u.id
                    LEFT JOIN employees e ON u.id = e.user_id
                    WHERE rc.request_id IN ($placeholders)
                    ORDER BY rc.request_id, c.created_at
                ", array_values($requestIds));

                $commentsByRequest = collect($requestComments)
                    ->groupBy('request_id')
                    ->map(function ($comments) {
                        return collect($comments)->map(function ($comment) {
                            return (object) [
                                'id' => $comment->comment_id,
                                'comment' => $comment->comment,
                                'created_at' => $comment->created_at,
                                'author_name' => $comment->author_name,
                            ];
                        })->toArray();
                    })->toArray();
            }

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requests,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'commentsByRequest' => $commentsByRequest,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriodByEmployeeAndAddress: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение заявок за период по сотруднику и адресу
     */
    public function getRequestsByEmployeeAddressAndDateRange(Request $request)
    {
        try {
            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'addressId' => 'required|exists:addresses,id',
                'startDate' => 'required|date_format:d.m.Y',
                'endDate' => 'required|date_format:d.m.Y',
            ]);

            $employeeId = $validated['employeeId'];
            $addressId = $validated['addressId'];
            $startDate = \Carbon\Carbon::createFromFormat('d.m.Y', $validated['startDate'])->startOfDay();
            $endDate = \Carbon\Carbon::createFromFormat('d.m.Y', $validated['endDate'])->endOfDay();

            $query = DB::table('requests as r')
                ->selectRaw("
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('request_subtypes AS rst', 'r.subtype_id', '=', 'rst.id')
                ->leftJoin('request_types AS rt', 'r.request_type_id', '=', 'rt.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where('addr.id', $addressId)
                ->whereBetween('r.execution_date', [$startDate, $endDate])
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                });

            $this->applySearchFilter($query, $request->input('search'));
            $this->applySearchMatchColumns($query, $request->input('search'), hasGroupBy: true);

            $query->groupBy([
                    'r.id', 'c.fio', 'c.phone', 'c.organization',
                    'rs.name', 'rs.color', 'rst.name', 'rt.name', 'rt.color', 'b.name', 'e.fio', 'op.fio',
                    'addr.street', 'addr.houses', 'addr.district',
                    'addr.city_id', 'ct.name', 'ct.postal_code',
                ])
                ->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC');

            $requests = $query->get();

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requests,
                'brigadeMembersWithDetails' => $this->getBrigadeMembersWithDetails(),
                'commentsByRequest' => $this->getCommentsByRequest(),
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByEmployeeAddressAndDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение заявок за период по адресу
     */
    public function getAllPeriodByAddress(Request $request)
    {
        try {
            // Pagination parameters
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 20);
            $offset = ($page - 1) * $limit;

            $addressId = $request->input('addressId');

            $sqlBase = '
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN request_subtypes rst ON r.subtype_id = rst.id
            LEFT JOIN request_types rt ON r.request_type_id = rt.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
            LEFT JOIN employees op ON r.operator_id = op.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            LEFT JOIN cities ct ON addr.city_id = ct.id
            WHERE addr.id = ?';
            $baseBindings = [$addressId];

            $searchFilter = $this->buildSearchSql($request->input('search'));
            $sqlBase .= $searchFilter['where_sql'];
            $baseBindings = array_merge($baseBindings, $searchFilter['where_bindings']);

            // Calculate total (без search_match_* колонок — они не нужны для подсчёта)
            $total = DB::select('SELECT COUNT(*) as total '.$sqlBase, $baseBindings)[0]->total;

            $sql = '
            SELECT
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code'
                .$searchFilter['select_sql'].'
            '.$sqlBase.'
            ORDER BY r.execution_date DESC, r.id DESC
            LIMIT ? OFFSET ?
            ';

            // select_sql (если есть) стоит в тексте запроса РАНЬШЕ sqlBase (SELECT перед WHERE) —
            // поэтому select_bindings должны идти в массиве раньше baseBindings.
            $requestsByAddressAndDateRange = DB::select(
                $sql,
                array_merge($searchFilter['select_bindings'], $baseBindings, [$limit, $offset])
            );

            // Optimization: Fetch only relevant brigade members and comments
            $requestIds = array_column($requestsByAddressAndDateRange, 'id');
            $brigadeIds = array_filter(array_unique(array_column($requestsByAddressAndDateRange, 'brigade_id')));

            $brigadeMembersWithDetails = [];
            if (! empty($brigadeIds)) {
                $placeholders = implode(',', array_fill(0, count($brigadeIds), '?'));
                $brigadeMembersWithDetails = DB::select(
                    "SELECT
                                         bm.*,
                                         b.name as brigade_name,
                                         b.leader_id,
                                         e.fio as employee_name,
                                         e.phone as employee_phone,
                                         e.group_role as employee_group_role,
                                         e.sip as employee_sip,
                                         e.position_id as employee_position_id,
                                         el.fio as employee_leader_name,
                                         el.phone as employee_leader_phone,
                                         el.group_role as employee_leader_group_role,
                                         el.sip as employee_leader_sip,
                                         el.position_id as employee_leader_position_id
                                     FROM brigade_members bm
                                     JOIN brigades b ON bm.brigade_id = b.id
                                     LEFT JOIN employees e ON bm.employee_id = e.id
                                     LEFT JOIN employees el ON b.leader_id = el.id
                                     WHERE bm.brigade_id IN ($placeholders)",
                    array_values($brigadeIds)
                );
            }

            $commentsByRequest = [];
            if (! empty($requestIds)) {
                $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                $requestComments = DB::select("
                     SELECT
                         rc.request_id,
                         c.id as comment_id,
                         c.comment,
                         c.created_at,
                         CASE 
                             WHEN e.fio IS NOT NULL THEN e.fio
                             WHEN u.name IS NOT NULL THEN u.name
                             WHEN u.email IS NOT NULL THEN u.email
                             ELSE 'Система'
                         END as author_name
                     FROM request_comments rc
                     JOIN comments c ON rc.comment_id = c.id
                     LEFT JOIN users u ON rc.user_id = u.id
                     LEFT JOIN employees e ON u.id = e.user_id
                     WHERE rc.request_id IN ($placeholders)
                     ORDER BY rc.request_id, c.created_at
                 ", array_values($requestIds));

                $commentsByRequest = collect($requestComments)
                    ->groupBy('request_id')
                    ->map(function ($comments) {
                        return collect($comments)->map(function ($comment) {
                            return (object) [
                                'id' => $comment->comment_id,
                                'comment' => $comment->comment,
                                'created_at' => $comment->created_at,
                                'author_name' => $comment->author_name,
                            ];
                        })->toArray();
                    })->toArray();
            }

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requestsByAddressAndDateRange,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'commentsByRequest' => $commentsByRequest,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriodByAddress: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за период по датам и адресу
     */
    public function getRequestsByAddressAndDateRange(Request $request)
    {
        try {
            // Комплексный запрос для получения информации о членах бригад с данными о бригадах
            $brigadeMembersWithDetails = DB::select(
                'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
            );

            // Запрашиваем комментарии с привязкой к заявкам
            $requestComments_old = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                'Система' as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            ORDER BY rc.request_id, c.created_at
        ");

            $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

            // Группируем комментарии по ID заявки
            $commentsByRequest = collect($requestComments)
                ->groupBy('request_id')
                ->map(function ($comments) {
                    return collect($comments)->map(function ($comment) {
                        return (object) [
                            'id' => $comment->comment_id,
                            'comment' => $comment->comment,
                            'created_at' => $comment->created_at,
                            'author_name' => $comment->author_name,
                        ];
                    })->toArray();
                });

            // Преобразуем коллекцию в массив для передачи в представление
            $comments_by_request = $commentsByRequest->toArray();

            // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
            $startDate = \DateTime::createFromFormat('d.m.Y', $request->input('startDate'))->format('Y-m-d');
            $endDate = \DateTime::createFromFormat('d.m.Y', $request->input('endDate'))->format('Y-m-d');
            $addressId = $request->input('addressId');

            $searchFilter = $this->buildSearchSql($request->input('search'));

            $sql = '
            SELECT
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code'
                .$searchFilter['select_sql'].'
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_statuses rs ON r.status_id = rs.id
            LEFT JOIN request_subtypes rst ON r.subtype_id = rst.id
            LEFT JOIN request_types rt ON r.request_type_id = rt.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees e ON b.leader_id = e.id
            LEFT JOIN employees op ON r.operator_id = op.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            LEFT JOIN cities ct ON addr.city_id = ct.id
            WHERE r.execution_date::date BETWEEN ? AND ?
            AND addr.id = ?';

            // select_sql стоит в тексте запроса РАНЬШЕ WHERE — поэтому select_bindings
            // должны идти в массиве раньше bindings диапазона дат/адреса.
            $bindings = array_merge($searchFilter['select_bindings'], [$startDate, $endDate, $addressId]);

            $sql .= $searchFilter['where_sql'];
            $bindings = array_merge($bindings, $searchFilter['where_bindings']);

            $sql .= ' ORDER BY r.execution_date DESC, r.id DESC';

            $requestsByAddressAndDateRange = DB::select($sql, $bindings);

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByAddressAndDateRange' => $requestsByAddressAndDateRange,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'comments_by_request' => $comments_by_request,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByAddressAndDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отображение страницы с отчетами по адресу
     */
    public function showAddressReports($addressId)
    {
        try {
            // Получить данные адреса
            $address = DB::table('addresses')
                ->join('cities', 'addresses.city_id', '=', 'cities.id')
                ->where('addresses.id', $addressId)
                ->select('addresses.*', 'cities.name as city_name')
                ->first();

            if (! $address) {
                abort(404, 'Адрес не найден');
            }

            // Получить заявки по адресу за весь период
            $requests = DB::table('requests as r')
                ->selectRaw('
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code
            ')
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('request_subtypes AS rst', 'r.subtype_id', '=', 'rst.id')
                ->leftJoin('request_types AS rt', 'r.request_type_id', '=', 'rt.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->where('addr.id', $addressId)
                ->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC')
                ->get();

            // Получить данные о членах бригад для заявок (плоский формат)
            $brigadeIds = $requests->pluck('brigade_id')->filter()->unique();
            $brigadeMembers = [];
            if ($brigadeIds->isNotEmpty()) {
                $brigadeMembers = DB::select('
                    SELECT
                        bm.brigade_id,
                        b.name as brigade_name,
                        b.leader_id,
                        e.fio as employee_name,
                        el.fio as employee_leader_name
                    FROM brigade_members bm
                    JOIN brigades b ON bm.brigade_id = b.id
                    LEFT JOIN employees e ON bm.employee_id = e.id
                    LEFT JOIN employees el ON b.leader_id = el.id
                    WHERE bm.brigade_id IN ('.$brigadeIds->implode(',').')
                ');
            }

            // Получить комментарии для заявок
            $requestIds = $requests->pluck('id');
            $commentsByRequest = [];
            if ($requestIds->isNotEmpty()) {
                $commentsByRequest = DB::table('request_comments as rc')
                    ->join('comments as c', 'rc.comment_id', '=', 'c.id')
                    ->leftJoin('users as u', 'rc.user_id', '=', 'u.id')
                    ->leftJoin('employees as e', 'u.id', '=', 'e.user_id')
                    ->whereIn('rc.request_id', $requestIds)
                    ->select(
                        'rc.request_id',
                        'c.id as comment_id',
                        'c.comment',
                        'c.created_at',
                        DB::raw("CASE
                            WHEN e.fio IS NOT NULL THEN e.fio
                            WHEN u.name IS NOT NULL THEN u.name
                            WHEN u.email IS NOT NULL THEN u.email
                            ELSE 'Система'
                        END as author_name")
                    )
                    ->orderBy('rc.request_id')
                    ->orderBy('c.created_at')
                    ->get()
                    ->groupBy('request_id')
                    ->map(function ($comments) {
                        return $comments->map(function ($comment) {
                            return (object) [
                                'id' => $comment->comment_id,
                                'comment' => $comment->comment,
                                'created_at' => $comment->created_at,
                                'author_name' => $comment->author_name,
                            ];
                        })->toArray();
                    })->toArray();
            }

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'address' => $address,
                    'requests' => $requests,
                    'brigadeMembers' => $brigadeMembers,
                    'comments_by_request' => $commentsByRequest,
                ]);
            }

            return view('reports.address', [
                'address' => $address,
                'requests' => $requests,
                'brigadeMembers' => $brigadeMembers,
                'commentsByRequest' => $commentsByRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@showAddressReports: '.$e->getMessage());
            abort(500, 'Произошла ошибка при получении отчетов');
        }
    }

    /**
     * Отображение страницы с отчетами по адресу (история для бота)
     */
    public function showAddressReportsHistory($addressId, $token)
    {
        // Проверка токена
        $secret = config('app.key');
        $expectedToken = md5($addressId.$secret.'address-history');

        if ($token !== $expectedToken) {
            abort(403, 'Неверный токен доступа');
        }

        try {
            // Получить данные адреса
            $address = DB::table('addresses')
                ->join('cities', 'addresses.city_id', '=', 'cities.id')
                ->where('addresses.id', $addressId)
                ->select('addresses.*', 'cities.name as city_name')
                ->first();

            if (! $address) {
                abort(404, 'Адрес не найден');
            }

            // Получить заявки по адресу за весь период
            $requests = DB::table('requests as r')
                ->selectRaw('
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code
            ')
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('request_subtypes AS rst', 'r.subtype_id', '=', 'rst.id')
                ->leftJoin('request_types AS rt', 'r.request_type_id', '=', 'rt.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->where('addr.id', $addressId)
                ->orderBy('r.execution_date', 'DESC')
                ->orderBy('r.id', 'DESC')
                ->get();

            // Получить данные о членах бригад для заявок
            $brigadeIds = $requests->pluck('brigade_id')->filter()->unique();
            $brigadeMembers = [];
            if ($brigadeIds->isNotEmpty()) {
                $brigadeMembers = DB::table('brigade_members as bm')
                    ->join('brigades as b', 'bm.brigade_id', '=', 'b.id')
                    ->leftJoin('employees as e', 'bm.employee_id', '=', 'e.id')
                    ->whereIn('bm.brigade_id', $brigadeIds)
                    ->select(
                        'bm.brigade_id',
                        'e.fio as employee_name',
                        'e.id as employee_id'
                    )
                    ->get()
                    ->groupBy('brigade_id')
                    ->map(function ($members, $brigadeId) {
                        return [
                            'brigade_id' => $brigadeId,
                            'members' => $members->map(function ($member) {
                                return [
                                    'fio' => $member->employee_name,
                                    'id' => $member->employee_id,
                                ];
                            })->toArray(),
                        ];
                    })->values()->toArray();
            }

            // Получить комментарии для заявок
            $requestIds = $requests->pluck('id');
            $commentsByRequest = [];
            if ($requestIds->isNotEmpty()) {
                $commentsByRequest = DB::table('request_comments as rc')
                    ->join('comments as c', 'rc.comment_id', '=', 'c.id')
                    ->leftJoin('users as u', 'rc.user_id', '=', 'u.id')
                    ->leftJoin('employees as e', 'u.id', '=', 'e.user_id')
                    ->whereIn('rc.request_id', $requestIds)
                    ->select(
                        'rc.request_id',
                        'c.id as comment_id',
                        'c.comment',
                        'c.created_at',
                        DB::raw("CASE
                            WHEN e.fio IS NOT NULL THEN e.fio
                            WHEN u.name IS NOT NULL THEN u.name
                            WHEN u.email IS NOT NULL THEN u.email
                            ELSE 'Система'
                        END as author_name")
                    )
                    ->orderBy('rc.request_id')
                    ->orderBy('c.created_at')
                    ->get()
                    ->groupBy('request_id')
                    ->map(function ($comments) {
                        return $comments->map(function ($comment) {
                            return (object) [
                                'id' => $comment->comment_id,
                                'comment' => $comment->comment,
                                'created_at' => $comment->created_at,
                                'author_name' => $comment->author_name,
                            ];
                        })->toArray();
                    })->toArray();

                // Add download links and photos for requests
                foreach ($requests as $request) {
                    // Get photos
                    $photos = DB::table('comment_photos')
                        ->join('request_comments', 'comment_photos.comment_id', '=', 'request_comments.comment_id')
                        ->join('photos', 'comment_photos.photo_id', '=', 'photos.id')
                        ->where('request_comments.request_id', $request->id)
                        ->select('photos.id', 'photos.path', 'photos.original_name')
                        ->get();

                    $request->photos = $photos;
                    $hasPhotos = $photos->isNotEmpty();

                    $hasFiles = DB::table('comment_files')
                        ->join('request_comments', 'comment_files.comment_id', '=', 'request_comments.comment_id')
                        ->where('request_comments.request_id', $request->id)
                        ->exists();

                    if ($hasPhotos || $hasFiles) {
                        $secret = config('app.key');
                        $token = md5($request->id.$secret.'telegram-notify');
                        $request->download_url = route('photo-report.download.public', ['requestId' => $request->id, 'token' => $token]);
                    } else {
                        $request->download_url = null;
                    }
                }
            }

            return view('reports.address-history', [
                'address' => $address,
                'requests' => $requests,
                'brigadeMembers' => $brigadeMembers,
                'commentsByRequest' => $commentsByRequest,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@showAddressReportsHistory: '.$e->getMessage());
            abort(500, 'Произошла ошибка при получении отчетов');
        }
    }

    /**
     * Получение списка адресов для отчетов
     */
    public function getAddresses()
    {
        try {
            $addresses = DB::select('
            SELECT
                addresses.id,
                addresses.city_id,
                addresses.street,
                addresses.district,
                addresses.houses,
                addresses.comments,
                addresses.house_type_id,
                addresses.responsible_person,
                cities.name as city_name,
                cities.region_id,
                cities.postal_code
            FROM addresses
            JOIN cities ON addresses.city_id = cities.id
            ORDER BY cities.name, addresses.street
        ');

            return response()->json($addresses);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAddresses: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка адресов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение списка сотрудников для отчетов
     */
    public function getEmployees()
    {
        try {
            $employees = DB::select('SELECT * FROM employees WHERE is_deleted = false ORDER BY fio');

            return response()->json($employees);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getEmployees: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка сотрудников',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за весь период по сотруднику
     */
    public function getAllPeriodByEmployee(Request $request)
    {
        try {
            // Тест
            // $data = [
            //     'success' => true,
            //     'debug' => false,
            //     'message' => 'getAllPeriodByEmployee',
            //     'request-all' => $request->all(),
            // ];

            // return response()->json($data);

            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'allPeriod' => 'required|boolean|in:1,true',
            ]);

            $employeeId = $validated['employeeId'];
            $allPeriod = $validated['allPeriod'] === true || $validated['allPeriod'] === '1' || $validated['allPeriod'] === 1;

            // Pagination parameters
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 20);
            $offset = ($page - 1) * $limit;

            $query = DB::table('requests as r')
                ->selectRaw("
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('request_subtypes AS rst', 'r.subtype_id', '=', 'rst.id')
                ->leftJoin('request_types AS rt', 'r.request_type_id', '=', 'rt.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                });

            // Добавляем фильтр по организации
            if ($request->has('organization') && ! empty($request->organization)) {
                $query->where('c.organization', $request->organization);
            }

            // Добавляем фильтр по типу заявки
            if ($request->has('requestTypeId') && ! empty($request->requestTypeId)) {
                $query->where('r.request_type_id', $request->requestTypeId);
            }

            $this->applySearchFilter($query, $request->input('search'));

            $query->groupBy([
                'r.id', 'c.fio', 'c.phone', 'c.organization',
                'rs.name', 'rs.color', 'rst.name', 'rt.name', 'rt.color', 'b.name', 'e.fio', 'op.fio',
                'addr.street', 'addr.houses', 'addr.district',
                'addr.city_id', 'ct.name', 'ct.postal_code',
            ]);

            // Calculate total before ordering and limiting
            $total = DB::table(DB::raw("({$query->toSql()}) as sub"))
                ->mergeBindings($query)
                ->count();

            // Колонки search_match_* — ПОСЛЕ total (см. applySearchMatchColumns).
            $this->applySearchMatchColumns($query, $request->input('search'), hasGroupBy: true);

            $query->orderByDesc('r.execution_date')
                ->orderByDesc('r.id');

            // Apply pagination
            $query->limit($limit)->offset($offset);

            $requestsAllPeriodByEmployee = $query->get();

            // Логируем SQL-запрос для отладки
            \Log::info('SQL Query in getAllPeriodByEmployee:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'employeeId' => $employeeId,
                'organization' => $request->organization ?? null,
                'requestTypeId' => $request->requestTypeId ?? null,
                'page' => $page,
                'limit' => $limit,
            ]);

            // Optimization: Fetch only relevant brigade members and comments
            $requestIds = $requestsAllPeriodByEmployee->pluck('id')->toArray();
            $brigadeIds = $requestsAllPeriodByEmployee->pluck('brigade_id')->filter()->unique()->toArray();

            // Get Brigade Members
            $brigadeMembersWithDetails = [];
            if (! empty($brigadeIds)) {
                $placeholders = implode(',', array_fill(0, count($brigadeIds), '?'));
                $brigadeMembersWithDetails = DB::select(
                    "SELECT
                        bm.*,
                        b.name as brigade_name,
                        b.leader_id,
                        e.fio as employee_name,
                        e.phone as employee_phone,
                        e.group_role as employee_group_role,
                        e.sip as employee_sip,
                        e.position_id as employee_position_id,
                        el.fio as employee_leader_name,
                        el.phone as employee_leader_phone,
                        el.group_role as employee_leader_group_role,
                        el.sip as employee_leader_sip,
                        el.position_id as employee_leader_position_id
                    FROM brigade_members bm
                    JOIN brigades b ON bm.brigade_id = b.id
                    LEFT JOIN employees e ON bm.employee_id = e.id
                    LEFT JOIN employees el ON b.leader_id = el.id
                    WHERE bm.brigade_id IN ($placeholders)",
                    array_values($brigadeIds)
                );
            }

            // Get Comments
            $commentsByRequest = [];
            if (! empty($requestIds)) {
                $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                $requestComments = DB::select("
                    SELECT
                        rc.request_id,
                        c.id as comment_id,
                        c.comment,
                        c.created_at,
                        CASE 
                            WHEN e.fio IS NOT NULL THEN e.fio
                            WHEN u.name IS NOT NULL THEN u.name
                            WHEN u.email IS NOT NULL THEN u.email
                            ELSE 'Система'
                        END as author_name
                    FROM request_comments rc
                    JOIN comments c ON rc.comment_id = c.id
                    LEFT JOIN users u ON rc.user_id = u.id
                    LEFT JOIN employees e ON u.id = e.user_id
                    WHERE rc.request_id IN ($placeholders)
                    ORDER BY rc.request_id, c.created_at
                ", array_values($requestIds));

                $commentsByRequest = collect($requestComments)
                    ->groupBy('request_id')
                    ->map(function ($comments) {
                        return collect($comments)->map(function ($comment) {
                            return (object) [
                                'id' => $comment->comment_id,
                                'comment' => $comment->comment,
                                'created_at' => $comment->created_at,
                                'author_name' => $comment->author_name,
                            ];
                        })->toArray();
                    })->toArray();
            }

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsAllPeriodByEmployee' => $requestsAllPeriodByEmployee,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'commentsByRequest' => $commentsByRequest,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriodByEmployee: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за весь период
     */
    public function getAllPeriod(Request $request)
    {
        try {
            // Тест
            // $data = [
            //     'success' => true,
            //     'debug' => false,
            //     'message' => 'Заявки успешно получены',
            //     'request-all' => $request->all(),
            // ];

            // return response()->json($data);

            $bindings = [];

            // Pagination parameters
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 20);
            $offset = ($page - 1) * $limit;

            $sqlBase = '
                    FROM requests r
                    LEFT JOIN clients c ON r.client_id = c.id
                    LEFT JOIN request_statuses rs ON r.status_id = rs.id
                    LEFT JOIN request_subtypes rst ON r.subtype_id = rst.id
                    LEFT JOIN request_types rt ON r.request_type_id = rt.id
                    LEFT JOIN brigades b ON r.brigade_id = b.id
                    LEFT JOIN employees e ON b.leader_id = e.id
                    LEFT JOIN employees op ON r.operator_id = op.id
                    LEFT JOIN request_addresses ra ON r.id = ra.request_id
                    LEFT JOIN addresses addr ON ra.address_id = addr.id
                    LEFT JOIN cities ct ON addr.city_id = ct.id
                    WHERE 1=1';

            // Добавляем фильтр по организации
            if ($request->has('organization') && ! empty($request->organization)) {
                $sqlBase .= ' AND c.organization = ?';
                $bindings[] = $request->organization;
            }

            // Добавляем фильтр по типу заявки
            if ($request->has('requestTypeId') && ! empty($request->requestTypeId)) {
                $sqlBase .= ' AND r.request_type_id = ?';
                $bindings[] = $request->requestTypeId;
            }

            $searchFilter = $this->buildSearchSql($request->input('search'));
            $sqlBase .= $searchFilter['where_sql'];
            array_push($bindings, ...$searchFilter['where_bindings']);

            // Calculate total (без search_match_* колонок — они не нужны для подсчёта)
            $total = DB::select("SELECT COUNT(*) as total $sqlBase", $bindings)[0]->total;

            $sql = "
                    SELECT r.*,
                        c.fio AS client_fio,
                        c.phone AS client_phone,
                        c.organization AS client_organization,
                        rs.name AS status_name,
                        rs.color AS status_color,
                        rst.name AS subtype_name,
                        rt.name AS request_type_name,
                        rt.color AS request_type_color,
                        b.name AS brigade_name,
                        e.fio AS brigade_lead,
                        op.fio AS operator_name,
                        addr.street,
                        addr.houses,
                        addr.district,
                        addr.city_id,
                        ct.name AS city_name,
                        ct.postal_code AS city_postal_code
                        {$searchFilter['select_sql']}
                    $sqlBase
                    ORDER BY execution_date DESC
                    LIMIT ? OFFSET ?
                ";

            // select_sql стоит в тексте запроса РАНЬШЕ $sqlBase (SELECT перед WHERE) —
            // поэтому select_bindings должны идти в массиве раньше $bindings (в котором
            // уже накоплены organization/requestTypeId/where_bindings по порядку их '?').
            $mainBindings = array_merge($searchFilter['select_bindings'], $bindings, [$limit, $offset]);

            // Логируем SQL-запрос для отладки
            \Log::info('SQL Query in getAllPeriod:', [
                'sql' => $sql,
                'bindings' => $mainBindings,
                'page' => $page,
            ]);

            $requestsAllPeriod = DB::select($sql, $mainBindings);

            // Optimization: Fetch only relevant brigade members and comments
            $requestIds = array_column($requestsAllPeriod, 'id');
            $brigadeIds = array_filter(array_unique(array_column($requestsAllPeriod, 'brigade_id')));

            $brigadeMembersWithDetails = [];
            if (! empty($brigadeIds)) {
                $placeholders = implode(',', array_fill(0, count($brigadeIds), '?'));
                $brigadeMembersWithDetails = DB::select(
                    "SELECT
                                            bm.*,
                                            b.name as brigade_name,
                                            b.leader_id,
                                            e.fio as employee_name,
                                            e.phone as employee_phone,
                                            e.group_role as employee_group_role,
                                            e.sip as employee_sip,
                                            e.position_id as employee_position_id,
                                            el.fio as employee_leader_name,
                                            el.phone as employee_leader_phone,
                                            el.group_role as employee_leader_group_role,
                                            el.sip as employee_leader_sip,
                                            el.position_id as employee_leader_position_id
                                        FROM brigade_members bm
                                        JOIN brigades b ON bm.brigade_id = b.id
                                        LEFT JOIN employees e ON bm.employee_id = e.id
                                        LEFT JOIN employees el ON b.leader_id = el.id
                                        WHERE bm.brigade_id IN ($placeholders)",
                    array_values($brigadeIds)
                );
            }

            $comments_by_request = [];
            if (! empty($requestIds)) {
                $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                $requestComments = DB::select(" 
                        SELECT
                            rc.request_id,
                            c.id as comment_id,
                            c.comment,
                            c.created_at,
                            CASE 
                                WHEN e.fio IS NOT NULL THEN e.fio
                                WHEN u.name IS NOT NULL THEN u.name
                                WHEN u.email IS NOT NULL THEN u.email
                                ELSE 'Система'
                            END as author_name
                        FROM request_comments rc
                        JOIN comments c ON rc.comment_id = c.id
                        LEFT JOIN users u ON rc.user_id = u.id
                        LEFT JOIN employees e ON u.id = e.user_id
                        WHERE rc.request_id IN ($placeholders)
                        ORDER BY rc.request_id, c.created_at
                    ", array_values($requestIds));

                $commentsByRequest = collect($requestComments)
                    ->groupBy('request_id')
                    ->map(function ($comments) {
                        return collect($comments)->map(function ($comment) {
                            return (object) [
                                'id' => $comment->comment_id,
                                'comment' => $comment->comment,
                                'created_at' => $comment->created_at,
                                'author_name' => $comment->author_name,
                            ];
                        })->toArray();
                    });
                $comments_by_request = $commentsByRequest->toArray();
            }

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsAllPeriod' => $requestsAllPeriod,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'comments_by_request' => $comments_by_request,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getAllPeriod: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за период по датам
     */
    public function getRequestsByDateRange(Request $request)
    {
        try {
            // Комплексный запрос для получения информации о членах бригад с данными о бригадах
            $brigadeMembersWithDetails = DB::select(
                'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
            );

            // Запрашиваем комментарии с привязкой к заявкам
            $requestComments_old = DB::select("
                SELECT
                    rc.request_id,
                    c.id as comment_id,
                    c.comment,
                    c.created_at,
                    'Система' as author_name
                FROM request_comments rc
                JOIN comments c ON rc.comment_id = c.id
                ORDER BY rc.request_id, c.created_at
            ");

            $requestComments = DB::select("
                SELECT
                    rc.request_id,
                    c.id as comment_id,
                    c.comment,
                    c.created_at,
                    CASE 
                        WHEN e.fio IS NOT NULL THEN e.fio
                        WHEN u.name IS NOT NULL THEN u.name
                        WHEN u.email IS NOT NULL THEN u.email
                        ELSE 'Система'
                    END as author_name
                FROM request_comments rc
                JOIN comments c ON rc.comment_id = c.id
                LEFT JOIN users u ON rc.user_id = u.id
                LEFT JOIN employees e ON u.id = e.user_id
                ORDER BY rc.request_id, c.created_at
            ");

            // Группируем комментарии по ID заявки
            $commentsByRequest = collect($requestComments)
                ->groupBy('request_id')
                ->map(function ($comments) {
                    return collect($comments)->map(function ($comment) {
                        return (object) [
                            'id' => $comment->comment_id,
                            'comment' => $comment->comment,
                            'created_at' => $comment->created_at,
                            'author_name' => $comment->author_name,
                        ];
                    })->toArray();
                });

            // Преобразуем коллекцию в массив для передачи в представление
            $comments_by_request = $commentsByRequest->toArray();

            // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
            $startDate = \DateTime::createFromFormat('d.m.Y', $request->input('startDate'))->format('Y-m-d');
            $endDate = \DateTime::createFromFormat('d.m.Y', $request->input('endDate'))->format('Y-m-d');

            $sql_ = '
                SELECT
                    r.*,
                    c.fio AS client_fio,
                    c.phone AS client_phone,
                    c.organization AS client_organization,
                    rs.name AS status_name,
                    rs.color AS status_color,
                    b.name AS brigade_name,
                    e.fio AS brigade_lead,
                    op.fio AS operator_name,
                    addr.street,
                    addr.houses,
                    addr.district,
                    addr.city_id,
                    ct.name AS city_name,
                    ct.postal_code AS city_postal_code
                FROM requests r
                LEFT JOIN clients c ON r.client_id = c.id
                LEFT JOIN request_statuses rs ON r.status_id = rs.id
                LEFT JOIN brigades b ON r.brigade_id = b.id
                LEFT JOIN employees e ON b.leader_id = e.id
                LEFT JOIN employees op ON r.operator_id = op.id
                LEFT JOIN request_addresses ra ON r.id = ra.request_id
                LEFT JOIN addresses addr ON ra.address_id = addr.id
                LEFT JOIN cities ct ON addr.city_id = ct.id
                WHERE r.execution_date::date BETWEEN ? AND ?
                AND (b.is_deleted = false OR b.id IS NULL)
                ORDER BY r.execution_date DESC, r.id DESC
            ';

            $searchFilter = $this->buildSearchSql($request->input('search'));

            // select_sql (search_match_* колонки) должен оказаться в тексте запроса
            // РАНЬШЕ WHERE (SELECT предшествует WHERE) — поэтому select_bindings идут
            // в массиве раньше $bindings диапазона дат/organization/requestTypeId.
            $bindings = array_merge($searchFilter['select_bindings'], [$startDate, $endDate]);

            $sql = '
                SELECT
                    r.*,
                    c.fio AS client_fio,
                    c.phone AS client_phone,
                    c.organization AS client_organization,
                    rs.name AS status_name,
                    rs.color AS status_color,
                    rst.name AS subtype_name,
                    rt.name AS request_type_name,
                    rt.color AS request_type_color,
                    b.name AS brigade_name,
                    e.fio AS brigade_lead,
                    op.fio AS operator_name,
                    addr.street,
                    addr.houses,
                    addr.district,
                    addr.city_id,
                    ct.name AS city_name,
                    ct.postal_code AS city_postal_code'
                    .$searchFilter['select_sql'].'
                FROM requests r
                LEFT JOIN clients c ON r.client_id = c.id
                LEFT JOIN request_statuses rs ON r.status_id = rs.id
                LEFT JOIN request_subtypes rst ON r.subtype_id = rst.id
                LEFT JOIN request_types rt ON r.request_type_id = rt.id
                LEFT JOIN brigades b ON r.brigade_id = b.id
                LEFT JOIN employees e ON b.leader_id = e.id
                LEFT JOIN employees op ON r.operator_id = op.id
                LEFT JOIN request_addresses ra ON r.id = ra.request_id
                LEFT JOIN addresses addr ON ra.address_id = addr.id
                LEFT JOIN cities ct ON addr.city_id = ct.id
                WHERE r.execution_date::date BETWEEN ? AND ?';

            // Добавляем фильтр по организации
            if ($request->has('organization') && ! empty($request->organization)) {
                $sql .= ' AND c.organization = ?';
                $bindings[] = $request->organization;
            }

            // Добавляем фильтр по типу заявки
            if ($request->has('requestTypeId') && ! empty($request->requestTypeId)) {
                $sql .= ' AND r.request_type_id = ?';
                $bindings[] = $request->requestTypeId;
            }

            $sql .= $searchFilter['where_sql'];
            array_push($bindings, ...$searchFilter['where_bindings']);

            $sql .= ' ORDER BY r.execution_date DESC, r.id DESC';

            // Логируем SQL-запрос для отладки
            \Log::info('SQL Query in getRequestsByDateRange:', [
                'sql' => $sql,
                'bindings' => $bindings,
            ]);

            $requestsByDateRange = DB::select($sql, $bindings);

            $data = [
                'success' => true,
                'debug' => false,
                'message' => 'Заявки успешно получены',
                'requestsByDateRange' => $requestsByDateRange,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'comments_by_request' => $comments_by_request,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск заявок за период по датам и сотрудникам
     */
    public function getRequestsByEmployeeAndDateRange(Request $request)
    {
        try {
            // Комплексный запрос для получения информации о членах бригад с данными о бригадах
            $brigadeMembersWithDetails = DB::select(
                'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
            );

            // Запрашиваем комментарии с привязкой к заявкам
            $requestComments_old = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                'Система' as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            ORDER BY rc.request_id, c.created_at
        ");

            $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

            // Группируем комментарии по ID заявки
            $commentsByRequest = collect($requestComments)
                ->groupBy('request_id')
                ->map(function ($comments) {
                    return collect($comments)->map(function ($comment) {
                        return (object) [
                            'id' => $comment->comment_id,
                            'comment' => $comment->comment,
                            'created_at' => $comment->created_at,
                            'author_name' => $comment->author_name,
                        ];
                    })->toArray();
                });

            // Преобразуем коллекцию в массив для передачи в представление
            $comments_by_request = $commentsByRequest->toArray();

            // Валидация входных данных
            $validated = $request->validate([
                'employeeId' => 'required|exists:employees,id',
                'startDate' => 'required|date_format:d.m.Y',
                'endDate' => 'required|date_format:d.m.Y|after_or_equal:startDate',
            ]);

            // Преобразуем даты из формата дд.мм.гггг в гггг-мм-дд для PostgreSQL
            $startDate = \DateTime::createFromFormat('d.m.Y', $validated['startDate'])->format('Y-m-d');
            $endDate = \DateTime::createFromFormat('d.m.Y', $validated['endDate'])->format('Y-m-d');
            $employeeId = $validated['employeeId'];

            $query = DB::table('requests as r')
                ->selectRaw("
                r.*,
                c.fio AS client_fio,
                c.phone AS client_phone,
                c.organization AS client_organization,
                rs.name AS status_name,
                rs.color AS status_color,
                rst.name AS subtype_name,
                rt.name AS request_type_name,
                rt.color AS request_type_color,
                b.name AS brigade_name,
                e.fio AS brigade_lead,
                op.fio AS operator_name,
                addr.street,
                addr.houses,
                addr.district,
                addr.city_id,
                ct.name AS city_name,
                ct.postal_code AS city_postal_code,
                STRING_AGG(em.fio, ', ') AS brigade_members
            ")
                ->leftJoin('clients AS c', 'r.client_id', '=', 'c.id')
                ->leftJoin('request_statuses AS rs', 'r.status_id', '=', 'rs.id')
                ->leftJoin('request_subtypes AS rst', 'r.subtype_id', '=', 'rst.id')
                ->leftJoin('request_types AS rt', 'r.request_type_id', '=', 'rt.id')
                ->leftJoin('brigades AS b', 'r.brigade_id', '=', 'b.id')
                ->leftJoin('employees AS e', 'b.leader_id', '=', 'e.id')
                ->leftJoin('employees AS op', 'r.operator_id', '=', 'op.id')
                ->leftJoin('request_addresses AS ra', 'r.id', '=', 'ra.request_id')
                ->leftJoin('addresses AS addr', 'ra.address_id', '=', 'addr.id')
                ->leftJoin('cities AS ct', 'addr.city_id', '=', 'ct.id')
                ->leftJoin('brigade_members AS bm', 'b.id', '=', 'bm.brigade_id')
                ->leftJoin('employees AS em', 'bm.employee_id', '=', 'em.id')
                ->where(function ($query) use ($employeeId) {
                    $query->whereExists(function ($q) use ($employeeId) {
                        $q->select(DB::raw(1))
                            ->from('brigade_members as bm2')
                            ->whereColumn('bm2.brigade_id', 'b.id')
                            ->where('bm2.employee_id', $employeeId);
                    })->orWhere('b.leader_id', $employeeId);
                })
                ->whereDate('r.execution_date', '>=', $startDate)
                ->whereDate('r.execution_date', '<=', $endDate);

            // Добавляем фильтр по организации
            if ($request->has('organization') && ! empty($request->organization)) {
                $query->where('c.organization', $request->organization);
            }

            // Добавляем фильтр по типу заявки
            if ($request->has('requestTypeId') && ! empty($request->requestTypeId)) {
                $query->where('r.request_type_id', $request->requestTypeId);
            }

            $this->applySearchFilter($query, $request->input('search'));
            $this->applySearchMatchColumns($query, $request->input('search'), hasGroupBy: true);

            $query->groupBy([
                'r.id', 'c.fio', 'c.phone', 'c.organization',
                'rs.name', 'rs.color', 'rst.name', 'rt.name', 'rt.color', 'b.name', 'e.fio', 'op.fio',
                'addr.street', 'addr.houses', 'addr.district',
                'addr.city_id', 'ct.name', 'ct.postal_code',
            ])
                ->orderByDesc('r.execution_date')
                ->orderByDesc('r.id');

            $requestsByEmployeeAndDateRange = $query->get();

            \Log::info('SQL Query in getRequestsByEmployeeAndDateRange:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'employeeId' => $employeeId,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'organization' => $request->organization ?? null,
                'requestTypeId' => $request->requestTypeId ?? null,
            ]);

            $data = [
                'success' => true,
                'message' => 'Заявки для отчёта успешно получены',
                'requestsByEmployeeAndDateRange' => $requestsByEmployeeAndDateRange,
                'brigadeMembersWithDetails' => $brigadeMembersWithDetails,
                'comments_by_request' => $comments_by_request,
                'debug' => false,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getRequestsByEmployeeAndDateRange: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении отчета',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение членов бригады с деталями
     */
    public function getBrigadeMembersWithDetails()
    {
        try {
            $brigadeMembersWithDetails = DB::select(
                'SELECT
                bm.*,
                b.name as brigade_name,
                b.leader_id,
                e.fio as employee_name,
                e.phone as employee_phone,
                e.group_role as employee_group_role,
                e.sip as employee_sip,
                e.position_id as employee_position_id,
                el.fio as employee_leader_name,
                el.phone as employee_leader_phone,
                el.group_role as employee_leader_group_role,
                el.sip as employee_leader_sip,
                el.position_id as employee_leader_position_id
            FROM brigade_members bm
            JOIN brigades b ON bm.brigade_id = b.id
            LEFT JOIN employees e ON bm.employee_id = e.id
            LEFT JOIN employees el ON b.leader_id = el.id'
            );

            return $brigadeMembersWithDetails;
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getBrigadeMembersWithDetails: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Получение списка организаций для отчетов
     */
    public function getOrganizations()
    {
        try {
            $organizations = DB::select("SELECT DISTINCT organization FROM clients WHERE organization IS NOT NULL AND organization != '' AND LENGTH(organization) > 2 AND organization != '-' ORDER BY organization");

            return response()->json($organizations);
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getOrganizations: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Произошла ошибка при получении списка организаций',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение комментариев к заявкам
     */
    public function getCommentsByRequest()
    {
        try {
            $requestComments = DB::select("
            SELECT
                rc.request_id,
                c.id as comment_id,
                c.comment,
                c.created_at,
                CASE 
                    WHEN e.fio IS NOT NULL THEN e.fio
                    WHEN u.name IS NOT NULL THEN u.name
                    WHEN u.email IS NOT NULL THEN u.email
                    ELSE 'Система'
                END as author_name
            FROM request_comments rc
            JOIN comments c ON rc.comment_id = c.id
            LEFT JOIN users u ON rc.user_id = u.id
            LEFT JOIN employees e ON u.id = e.user_id
            ORDER BY rc.request_id, c.created_at
        ");

            $commentsByRequest = collect($requestComments)
                ->groupBy('request_id')
                ->map(function ($comments) {
                    return collect($comments)->map(function ($comment) {
                        return (object) [
                            'id' => $comment->comment_id,
                            'comment' => $comment->comment,
                            'created_at' => $comment->created_at,
                            'author_name' => $comment->author_name,
                        ];
                    })->toArray();
                });

            $comments_by_request = $commentsByRequest->toArray();

            return $comments_by_request;
        } catch (\Exception $e) {
            Log::error('Error in ReportController@getCommentsByRequest: '.$e->getMessage());

            return [];
        }
    }

    public function printWorkPermit(Request $request)
    {
        $ids = explode(',', $request->query('ids', ''));
        $ids = array_filter($ids, 'is_numeric');

        if (empty($ids)) {
            return response('Не выбраны заявки для печати', 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT
                r.id,
                r.number,
                r.brigade_id,
                TO_CHAR(r.execution_date, 'DD.MM.YYYY') as execution_date_formatted,
                c.fio as client_fio,
                c.organization as client_organization,
                c.phone as client_phone,
                addr.street,
                addr.houses,
                addr.district,
                ct.name as city_name,
                b.name as brigade_name,
                leader.fio as brigade_leader_fio,
                (
                    SELECT co.comment
                    FROM request_comments rc
                    JOIN comments co ON rc.comment_id = co.id
                    WHERE rc.request_id = r.id
                    ORDER BY co.created_at ASC
                    LIMIT 1
                ) as first_comment
            FROM requests r
            LEFT JOIN clients c ON r.client_id = c.id
            LEFT JOIN request_addresses ra ON r.id = ra.request_id
            LEFT JOIN addresses addr ON ra.address_id = addr.id
            LEFT JOIN cities ct ON addr.city_id = ct.id
            LEFT JOIN brigades b ON r.brigade_id = b.id
            LEFT JOIN employees leader ON b.leader_id = leader.id
            WHERE r.id IN ($placeholders)
            ORDER BY r.brigade_id, r.execution_date, r.id
        ";

        $requests = DB::select($sql, $ids);

        // Получаем членов бригад
        $brigadeIds = array_unique(array_filter(array_map(fn ($r) => $r->brigade_id, $requests)));
        $membersByBrigade = [];

        if (! empty($brigadeIds)) {
            $brigadePlaceholders = implode(',', array_fill(0, count($brigadeIds), '?'));
            $membersSql = "
                SELECT bm.brigade_id, e.fio, e.group_role
                FROM brigade_members bm
                JOIN employees e ON bm.employee_id = e.id
                WHERE bm.brigade_id IN ($brigadePlaceholders)
            ";
            $members = DB::select($membersSql, array_values($brigadeIds));

            foreach ($members as $member) {
                $membersByBrigade[$member->brigade_id][] = $member;
            }
        }

        // Группируем заявки по бригаде
        $groupedRequests = [];
        foreach ($requests as $req) {
            $brigadeId = $req->brigade_id ?? 0;
            if (! isset($groupedRequests[$brigadeId])) {
                $groupedRequests[$brigadeId] = [
                    'brigade_name' => $req->brigade_name,
                    'brigade_leader_fio' => $req->brigade_leader_fio,
                    'dates' => [],
                    'requests' => [],
                    'brigade_members' => $membersByBrigade[$brigadeId] ?? [],
                ];
            }
            $groupedRequests[$brigadeId]['requests'][] = $req;
            if ($req->execution_date_formatted) {
                $groupedRequests[$brigadeId]['dates'][$req->execution_date_formatted] = true;
            }
        }

        $issuerFio = auth()->user()->name;
        $employee = DB::table('employees')->where('user_id', auth()->id())->first();
        if ($employee) {
            $issuerFio = $employee->fio;
        }

        return view('reports.work-permit', [
            'groupedRequests' => $groupedRequests,
            'issuerFio' => $issuerFio,
        ]);
    }
}
