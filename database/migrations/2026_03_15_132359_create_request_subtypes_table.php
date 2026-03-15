<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_subtypes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('status_id')->references('id')->on('request_statuses')->onDelete('cascade');
        });

        Schema::table('requests', function (Blueprint $table) {
            $table->integer('subtype_id')->nullable();
            $table->foreign('subtype_id')->references('id')->on('request_subtypes')->onDelete('set null');
        });

        // Seed data
        $statusId = 6; // 'планирование'
        
        $standardId = DB::table('request_subtypes')->insertGetId([
            'status_id' => $statusId,
            'name' => 'Стандартное планирование',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('request_subtypes')->insert([
            ['status_id' => $statusId, 'name' => 'Длительное планирование', 'created_at' => now(), 'updated_at' => now()],
            ['status_id' => $statusId, 'name' => 'Планирование на июнь', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Update existing requests with status "планирование" to have "Стандартное планирование" as subtype
        DB::table('requests')
            ->where('status_id', $statusId)
            ->update(['subtype_id' => $standardId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['subtype_id']);
            $table->dropColumn('subtype_id');
        });

        Schema::dropIfExists('request_subtypes');
    }
};
