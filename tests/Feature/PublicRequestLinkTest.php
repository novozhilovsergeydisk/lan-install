<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Блок 6 ТЗ: публичная ссылка на ОДНУ заявку, фото сгруппированы по комментариям.
 *
 * Страница отдаётся без авторизации — доступ защищён только токеном в URL,
 * поэтому проверки токена здесь важнее остального: чужой/подменённый токен
 * не должен открывать заявку.
 *
 * Middleware НЕ отключаем (в отличие от других тестов проекта): именно
 * прохождение публичного маршрута мимо auth — часть проверяемого поведения.
 */
class PublicRequestLinkTest extends TestCase
{
    use DatabaseTransactions;

    private function publicToken(int $requestId): string
    {
        return md5($requestId.config('app.key').'request-public');
    }

    /**
     * Заявка с двумя комментариями, у каждого — своё фото. Это ключевой сценарий
     * блока 6: фото не должны смешиваться в общую кучу.
     */
    private function createRequestWithCommentPhotos(): array
    {
        $address = DB::table('addresses')->first();
        if (! $address) {
            $this->markTestSkipped('No address found');
        }

        $clientId = DB::table('clients')->insertGetId([
            'fio' => 'Публичная Ссылка Тест',
            'phone' => '+7 (900) 000-00-00',
            'organization' => 'ООО Тест Публичной Ссылки',
        ]);

        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-PUBLIC-'.uniqid(),
            'client_id' => $clientId,
            'request_type_id' => DB::table('request_types')->first()->id ?? 1,
            'status_id' => 4,
            'operator_id' => DB::table('employees')->first()->id ?? 1,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ]);

        DB::table('request_addresses')->insert([
            'request_id' => $requestId,
            'address_id' => $address->id,
        ]);

        $made = [];
        foreach ([
            ['text' => 'Первый этап: демонтаж старых панелей', 'photo' => 'test/public-link-first.jpg'],
            ['text' => 'Второй этап: монтаж новых панелей', 'photo' => 'test/public-link-second.jpg'],
        ] as $item) {
            $commentId = DB::table('comments')->insertGetId([
                'comment' => $item['text'],
                'created_at' => now(),
            ]);
            DB::table('request_comments')->insert([
                'request_id' => $requestId,
                'comment_id' => $commentId,
                'created_at' => now(),
                'is_closing' => false,
            ]);
            $photoId = DB::table('photos')->insertGetId([
                'path' => $item['photo'],
                'original_name' => basename($item['photo']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('comment_photos')->insert([
                'comment_id' => $commentId,
                'photo_id' => $photoId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $made[] = ['comment_id' => $commentId, 'text' => $item['text'], 'photo' => $item['photo']];
        }

        return [$requestId, $made];
    }

    public function test_valid_token_opens_request_page_without_auth()
    {
        [$requestId] = $this->createRequestWithCommentPhotos();

        $response = $this->get("/public/requests/{$requestId}/".$this->publicToken($requestId));

        $response->assertStatus(200);
        $response->assertSee('TEST-PUBLIC-', false);
    }

    public function test_wrong_token_is_rejected()
    {
        [$requestId] = $this->createRequestWithCommentPhotos();

        $this->get("/public/requests/{$requestId}/deadbeefdeadbeefdeadbeefdeadbeef")
            ->assertStatus(403);
    }

    public function test_token_from_another_request_does_not_work()
    {
        // Токен привязан к id — подставив чужой, заявку открыть нельзя.
        [$requestId] = $this->createRequestWithCommentPhotos();
        [$otherRequestId] = $this->createRequestWithCommentPhotos();

        $this->get("/public/requests/{$requestId}/".$this->publicToken($otherRequestId))
            ->assertStatus(403);
    }

    public function test_address_history_token_does_not_open_request_page()
    {
        // У каждой публичной ссылки своё «назначение» в токене — токен от истории
        // по адресу не должен подходить к странице заявки.
        [$requestId] = $this->createRequestWithCommentPhotos();
        $foreignToken = md5($requestId.config('app.key').'address-history');

        $this->get("/public/requests/{$requestId}/{$foreignToken}")
            ->assertStatus(403);
    }

    public function test_missing_request_returns_404_not_500()
    {
        $missingId = (int) DB::table('requests')->max('id') + 100000;

        $this->get("/public/requests/{$missingId}/".$this->publicToken($missingId))
            ->assertStatus(404);
    }

    public function test_photos_are_grouped_under_their_own_comments()
    {
        // Суть блока 6: фото показано под своим комментарием, а не в общей галерее.
        [$requestId, $made] = $this->createRequestWithCommentPhotos();

        $html = $this->get("/public/requests/{$requestId}/".$this->publicToken($requestId))
            ->assertStatus(200)
            ->getContent();

        foreach ($made as $item) {
            $this->assertStringContainsString($item['text'], $html, 'Комментарий должен быть на странице');
            $this->assertStringContainsString($item['photo'], $html, 'Фото должно быть на странице');
        }

        // Проверяем именно ПОРЯДОК: фото первого комментария идёт после текста
        // первого комментария, но ДО текста второго. Если бы фото собирались
        // в общую галерею внизу, это условие не выполнилось бы.
        $firstCommentPos = strpos($html, $made[0]['text']);
        $firstPhotoPos = strpos($html, $made[0]['photo']);
        $secondCommentPos = strpos($html, $made[1]['text']);
        $secondPhotoPos = strpos($html, $made[1]['photo']);

        $this->assertGreaterThan($firstCommentPos, $firstPhotoPos, 'Фото должно идти после своего комментария');
        $this->assertLessThan($secondCommentPos, $firstPhotoPos, 'Фото первого комментария не должно оказаться ниже второго комментария');
        $this->assertGreaterThan($secondCommentPos, $secondPhotoPos, 'Фото второго комментария должно идти после него');
    }

    public function test_request_without_comments_opens_without_error()
    {
        $clientId = DB::table('clients')->insertGetId(['fio' => 'Без комментариев']);
        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-PUBLIC-EMPTY-'.uniqid(),
            'client_id' => $clientId,
            'request_type_id' => DB::table('request_types')->first()->id ?? 1,
            'status_id' => 4,
            'operator_id' => DB::table('employees')->first()->id ?? 1,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ]);

        $this->get("/public/requests/{$requestId}/".$this->publicToken($requestId))
            ->assertStatus(200)
            ->assertSee('пока нет комментариев', false);
    }

    public function test_get_comments_returns_public_request_url_for_closed_request()
    {
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (! $admin) {
            $this->markTestSkipped('Admin user not found');
        }
        $this->actingAs($admin);

        // createRequestWithCommentPhotos() создаёт заявку со status_id = 4 (выполнена)
        [$requestId] = $this->createRequestWithCommentPhotos();

        $response = $this->getJson("/api/requests/{$requestId}/comments");

        $response->assertStatus(200);
        $url = $response->json('meta.request_public_url');

        $this->assertNotNull($url, 'getComments должен отдавать ссылку на публичную страницу заявки');
        $this->assertStringContainsString("/public/requests/{$requestId}/", $url);
        $this->assertStringContainsString($this->publicToken($requestId), $url);
    }

    public function test_no_public_url_for_open_request()
    {
        // По ТЗ кнопка добавляется «в интерфейс ЗАКРЫТОЙ заявки» — у заявки в работе
        // ссылки быть не должно (статусы закрытия: 4 «выполнена», 7 «удалена/закрыта»,
        // то же условие, что у существующей ссылки на историю по адресу).
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (! $admin) {
            $this->markTestSkipped('Admin user not found');
        }
        $this->actingAs($admin);

        [$requestId] = $this->createRequestWithCommentPhotos();
        DB::table('requests')->where('id', $requestId)->update(['status_id' => 1]);

        $response = $this->getJson("/api/requests/{$requestId}/comments");

        $response->assertStatus(200);
        $this->assertNull(
            $response->json('meta.request_public_url'),
            'У незакрытой заявки ссылки на публичную страницу быть не должно'
        );
    }

    public function test_page_itself_still_opens_for_open_request_by_direct_link()
    {
        // Кнопки у незакрытой заявки нет, но если ссылку уже отправили ДО закрытия
        // (или заявку переоткрыли), она не должна ломаться — иначе у заказчика
        // внезапно отвалится присланная ранее ссылка.
        [$requestId] = $this->createRequestWithCommentPhotos();
        DB::table('requests')->where('id', $requestId)->update(['status_id' => 1]);

        $this->get("/public/requests/{$requestId}/".$this->publicToken($requestId))
            ->assertStatus(200);
    }
}
