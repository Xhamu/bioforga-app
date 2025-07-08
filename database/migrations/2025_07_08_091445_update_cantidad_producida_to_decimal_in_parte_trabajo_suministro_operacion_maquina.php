<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_operacion_maquina', function (Blueprint $table) {
            $table->decimal('cantidad_producida', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_operacion_maquina', function (Blueprint $table) {
            $table->string('cantidad_producida')->nullable()->change();
        });
    }
};
