<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class UncompletedWorksTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    private function authenticateAdmin()
    {
        $admin = User::where('email', 'admin@appuse.ru')->first();
        if (!$admin) {
            $this->markTestSkipped('Admin user not found');
        }

        $roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $admin->id)
            ->pluck('roles.name')
            ->toArray();

        $admin->roles = $roles;
        $admin->employee = DB::table('employees')->where('user_id', $admin->id)->first();
        
        $this->actingAs($admin);
        return $admin;
    }

    public function test_uncompleted_works_calculates_remaining_quantity()
    {
        $admin = $this->authenticateAdmin();

        // 1. Создаем тестовую заявку напрямую через DB (миграций нет)
        $requestId = DB::table('requests')->insertGetId([
            'number' => 'TEST-REQ-' . time(),
            'client_id' => DB::table('clients')->first()->id ?? 1,
            'request_type_id' => 1,
            'status_id' => 1, // Открыта
            'operator_id' => $admin->employee->id ?? 1,
            'execution_date' => now()->toDateString(),
            'request_date' => now()->toDateString(),
        ], 'id');

        // 2. Создаем запланированную работу (10 штук)
        $workTypeId = DB::table('work_parameter_types')->first()->id ?? 1;
        
        DB::table('work_parameters')->insert([
            'request_id' => $requestId,
            'parameter_type_id' => $workTypeId,
            'quantity' => 10,
            'is_planning' => true,
            'is_done' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestsCountBefore = DB::table('requests')->count();

        // 3. Эмулируем POST запрос закрытия заявки с 9 выполненными и галочкой "uncompleted_works"
        $response = $this->post("/requests/{$requestId}/close", [
            'comment' => 'Test uncompleted works calculation',
            'uncompleted_works' => true,
            'work_parameters' => [
                [
                    'parameter_type_id' => $workTypeId,
                    'quantity' => 9 // Выполнили 9 из 10
                ]
            ],
            '_token' => csrf_token()
        ]);

        $response->assertStatus(200);

        // 4. Проверяем, что создалась ровно 1 новая заявка
        $this->assertEquals($requestsCountBefore + 1, DB::table('requests')->count());

        // 5. Находим новую заявку (последняя созданная)
        $newRequest = DB::table('requests')->orderBy('id', 'desc')->first();

        // 6. Проверяем параметры работы новой заявки (должна быть 1 штука, т.к. 10 - 9 = 1)
        $newWorkParameter = DB::table('work_parameters')
            ->where('request_id', $newRequest->id)
            ->where('parameter_type_id', $workTypeId)
            ->where('is_planning', true)
            ->first();

        $this->assertNotNull($newWorkParameter, 'Новая работа не была запланирована');
        $this->assertEquals(1, $newWorkParameter->quantity, 'Остаток работы рассчитан неверно (ожидалось 1)');
    }
}
