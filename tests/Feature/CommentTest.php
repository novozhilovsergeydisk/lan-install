<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class CommentTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure public disk exists for testing
        Storage::fake('public');
    }

    private function authenticateUser()
    {
        $email = 'test_user_' . time() . '@appuse.ru';
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test Author User',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $user = User::find($userId);
        $this->actingAs($user);
        return $user;
    }

    private function authenticateOtherUser()
    {
        $email = 'other_user_' . time() . '@appuse.ru';
        $userId = DB::table('users')->insertGetId([
            'name' => 'Other User',
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $user = User::find($userId);
        $this->actingAs($user);
        return $user;
    }

    private function authenticateAdmin()
    {
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (!$admin) {
            $this->markTestSkipped('Admin user not found');
        }
        $this->actingAs($admin);
        return $admin;
    }

    private function createDummyComment($userId)
    {
        // Создаем заявку
        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-REQ-' . time() . '-' . rand(1000, 9999),
            'client_id' => DB::table('clients')->first()->id ?? 1,
            'request_type_id' => DB::table('request_types')->first()->id ?? 1,
            'status_id' => DB::table('request_statuses')->first()->id ?? 1,
            'operator_id' => DB::table('employees')->first()->id ?? 1,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ], 'id');

        // Создаем комментарий
        $commentId = DB::table('comments')->insertGetId([
            'comment' => 'Original test comment',
            'created_at' => now(),
        ]);

        // Привязываем комментарий к заявке и пользователю
        DB::table('request_comments')->insert([
            'request_id' => $requestId,
            'comment_id' => $commentId,
            'user_id' => $userId,
            'created_at' => now(),
        ]);

        return $commentId;
    }

    public function test_author_can_update_own_comment()
    {
        $user = $this->authenticateUser();
        $commentId = $this->createDummyComment($user->id);

        $newContent = 'Updated test comment';

        $response = $this->put("/api/comments/{$commentId}", [
            'content' => $newContent,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Проверяем, что текст изменился в таблице
        $updatedComment = DB::table('comments')->where('id', $commentId)->first();
        $this->assertEquals($newContent, $updatedComment->comment);

        // Проверяем, что создалась запись в истории редактирования
        $editHistory = DB::table('comment_edits')->where('comment_id', $commentId)->first();
        $this->assertNotNull($editHistory);
        $this->assertEquals('Original test comment', $editHistory->old_comment);
        $this->assertEquals($user->id, $editHistory->edited_by_user_id);
    }

    public function test_admin_can_update_others_comment()
    {
        // Сначала создаем комментарий от обычного пользователя (автора)
        // Чтобы не аутентифицировать его через Laravel Auth, просто создадим запись
        $authorEmail = 'author_' . time() . '@appuse.ru';
        $authorId = DB::table('users')->insertGetId([
            'name' => 'Author User',
            'email' => $authorEmail,
            'password' => bcrypt('password'),
        ]);
        
        $commentId = $this->createDummyComment($authorId);

        // Аутентифицируемся как админ
        $admin = $this->authenticateAdmin();

        $newContent = 'Admin updated test comment';

        $response = $this->put("/api/comments/{$commentId}", [
            'content' => $newContent,
        ]);

        $response->assertStatus(200);

        // Проверяем, что текст изменился
        $updatedComment = DB::table('comments')->where('id', $commentId)->first();
        $this->assertEquals($newContent, $updatedComment->comment);
    }

    public function test_other_user_cannot_update_comment()
    {
        // Создаем пользователя 1
        $email1 = 'user1_' . time() . '@appuse.ru';
        $user1Id = DB::table('users')->insertGetId([
            'name' => 'User 1',
            'email' => $email1,
            'password' => bcrypt('password'),
        ]);
        
        $commentId = $this->createDummyComment($user1Id);

        // Аутентифицируемся как другой пользователь
        $this->authenticateOtherUser();

        $response = $this->put("/api/comments/{$commentId}", [
            'content' => 'Malicious update',
        ]);

        // Должна быть ошибка прав (403)
        $response->assertStatus(403);

        // Проверяем, что текст не изменился
        $comment = DB::table('comments')->where('id', $commentId)->first();
        $this->assertEquals('Original test comment', $comment->comment);
    }

    public function test_author_can_delete_own_comment()
    {
        $user = $this->authenticateUser();
        $commentId = $this->createDummyComment($user->id);
        
        // Создаем еще один комментарий, чтобы обойти триггер, запрещающий удалять последний комментарий заявки
        $requestComment = DB::table('request_comments')->where('comment_id', $commentId)->first();
        $secondCommentId = DB::table('comments')->insertGetId([
            'comment' => 'Second test comment',
            'created_at' => now(),
        ]);
        DB::table('request_comments')->insert([
            'request_id' => $requestComment->request_id,
            'comment_id' => $secondCommentId,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        // Создаем фото
        Storage::disk('public')->put('public/test_photo_to_delete.jpg', 'fake content');
        $photoId = DB::table('photos')->insertGetId([
            'path' => 'storage/test_photo_to_delete.jpg',
            'created_at' => now(),
        ]);
        DB::table('comment_photos')->insert([
            'comment_id' => $commentId,
            'photo_id' => $photoId,
        ]);

        $response = $this->delete("/api/comments/{$commentId}");

        $response->assertStatus(200);

        // Проверяем, что комментарий и привязки удалились из БД
        $this->assertNull(DB::table('comments')->where('id', $commentId)->first());
        $this->assertNull(DB::table('request_comments')->where('comment_id', $commentId)->first());
        $this->assertNull(DB::table('comment_photos')->where('comment_id', $commentId)->first());
        $this->assertNull(DB::table('photos')->where('id', $photoId)->first());

        // Проверяем, что файл удален из storage
        Storage::disk('public')->assertMissing('public/test_photo_to_delete.jpg');
    }

    public function test_other_user_cannot_delete_comment()
    {
        $email1 = 'user_del_' . time() . '@appuse.ru';
        $user1Id = DB::table('users')->insertGetId([
            'name' => 'User Delete',
            'email' => $email1,
            'password' => bcrypt('password'),
        ]);
        
        $commentId = $this->createDummyComment($user1Id);

        $this->authenticateOtherUser();

        $response = $this->delete("/api/comments/{$commentId}");

        $response->assertStatus(403);

        $this->assertNotNull(DB::table('comments')->where('id', $commentId)->first());
    }

    public function test_author_can_delete_photo_from_comment()
    {
        $user = $this->authenticateUser();
        $commentId = $this->createDummyComment($user->id);

        // Создаем фото
        Storage::disk('public')->put('public/test_single_photo.jpg', 'fake content');
        $photoId = DB::table('photos')->insertGetId([
            'path' => 'storage/test_single_photo.jpg',
            'created_at' => now(),
        ]);
        DB::table('comment_photos')->insert([
            'comment_id' => $commentId,
            'photo_id' => $photoId,
        ]);

        $response = $this->delete("/api/comments/{$commentId}/photos/{$photoId}");

        $response->assertStatus(200);

        // Проверяем, что связь удалилась
        $this->assertNull(DB::table('comment_photos')->where('comment_id', $commentId)->where('photo_id', $photoId)->first());
        
        // Фото тоже должно удалиться, если оно не используется нигде больше
        $this->assertNull(DB::table('photos')->where('id', $photoId)->first());

        // Проверяем, что файл удален
        Storage::disk('public')->assertMissing('public/test_single_photo.jpg');
        }

        public function test_author_can_mass_delete_photos()
        {
        $user = $this->authenticateUser();
        $commentId = $this->createDummyComment($user->id);

        // Создаем 3 фото
        $photoIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "public/mass_delete_{$i}.jpg";
            Storage::disk('public')->put($path, 'fake content ' . $i);

            $id = DB::table('photos')->insertGetId([
                'path' => str_replace('public/', 'storage/', $path),
                'created_at' => now(),
            ]);

            DB::table('comment_photos')->insert([
                'comment_id' => $commentId,
                'photo_id' => $id,
            ]);

            $photoIds[] = $id;
        }

        // Удаляем первые две
        $toDelete = [$photoIds[0], $photoIds[1]];

        $response = $this->delete("/api/comments/{$commentId}/photos-mass", [
            'photo_ids' => $toDelete
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Проверяем БД
        $this->assertNull(DB::table('comment_photos')->where('comment_id', $commentId)->whereIn('photo_id', $toDelete)->first());
        $this->assertNotNull(DB::table('comment_photos')->where('comment_id', $commentId)->where('photo_id', $photoIds[2])->first());

        // Проверяем файлы
        Storage::disk('public')->assertMissing('public/mass_delete_1.jpg');
        Storage::disk('public')->assertMissing('public/mass_delete_2.jpg');
        Storage::disk('public')->assertExists('public/mass_delete_3.jpg');
        }
        }
