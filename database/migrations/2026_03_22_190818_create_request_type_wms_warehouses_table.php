<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('request_type_wms_warehouses')) {
            Schema::create('request_type_wms_warehouses', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('request_type_id');
                $table->unsignedInteger('wms_warehouse_id');
                $table->timestamps();

                $table->unique(['request_type_id', 'wms_warehouse_id'], 'request_type_wms_warehouses_request_type_id_wms_warehouse_id_un');
                $table->foreign('request_type_id')->references('id')->on('request_types')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_type_wms_warehouses');
    }
};
