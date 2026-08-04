<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * История правок фактических количеств выполненных работ.
     *
     * Монтажники нередко пишут в комментарии «выполнено 7 из 13», а поле с количеством
     * не меняют — в выгрузку уходят неверные объёмы. Правку теперь можно внести из отчётов,
     * и каждое такое изменение фиксируется здесь: было / стало / кто / когда.
     *
     * Экрана просмотра истории пока нет (в ТЗ не заявлен) — данные копим с первого дня,
     * чтобы при вопросе «откуда в отчёте эта цифра» ответ был за весь период.
     * Сделано по образцу существующей таблицы comment_edits.
     */
    public function up(): void
    {
        Schema::create('work_parameter_edits', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('work_parameter_id');
            $table->integer('request_id');                  // дубль для быстрых выборок по заявке
            $table->integer('old_quantity')->nullable();    // было (null — если записи не существовало)
            $table->integer('new_quantity')->nullable();    // стало
            $table->unsignedBigInteger('edited_by_user_id')->nullable();
            $table->timestamp('edited_at')->useCurrent();

            $table->index('work_parameter_id');
            $table->index('request_id');

            // Параметр могут удалить (напр. при пересохранении заявки) — историю правок
            // при этом теряем осознанно, она привязана к конкретной записи.
            $table->foreign('work_parameter_id')->references('id')->on('work_parameters')->onDelete('cascade');
            $table->foreign('request_id')->references('id')->on('requests')->onDelete('cascade');
            $table->foreign('edited_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_parameter_edits');
    }
};
