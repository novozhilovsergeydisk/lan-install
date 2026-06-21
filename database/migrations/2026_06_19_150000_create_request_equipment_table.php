<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Снимок оборудования, которое участники бригады взяли со склада, на момент
     * закрытия заявки. Только отображение (в колонке «Бригада» и в отчётах) —
     * никаких действий со складом. Несколько комплектов/машин = несколько строк.
     */
    public function up(): void
    {
        Schema::create('request_equipment', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('request_id');
            $table->string('kind', 16);              // 'tool' (инструмент-комплект) | 'vehicle' (машина)
            $table->string('label');                 // что показываем: 'H-7' или 'Р724ХВ77 Ford Transit'
            $table->integer('holder_emp_id')->nullable(); // сотрудник бригады, у кого числилось (employees.id)
            $table->string('holder_fio')->nullable();      // снимок ФИО, чтобы отчёт не зависел от джойнов
            $table->string('wms_ref')->nullable();         // исходное значение из склада (инв.номер / госномер)
            $table->string('source', 16)->default('warehouse'); // 'warehouse' (со склада) | 'personal' (личное авто, введено вручную)
            $table->timestamp('created_at')->useCurrent();

            $table->index('request_id');
            $table->foreign('request_id')->references('id')->on('requests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_equipment');
    }
};
