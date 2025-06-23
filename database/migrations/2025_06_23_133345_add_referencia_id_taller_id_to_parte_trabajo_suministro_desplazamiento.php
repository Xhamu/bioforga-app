<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_desplazamiento', function (Blueprint $table) {
            $table->unsignedBigInteger('referencia_id')->nullable()->after('destino');
            $table->unsignedBigInteger('taller_id')->nullable()->after('referencia_id');
            $table->unsignedBigInteger('maquina_id')->nullable()->after('taller_id');
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_desplazamiento', function (Blueprint $table) {
            $table->dropColumn([
                'referencia_id',
                'taller_id',
                'maquina_id',
            ]);
        });
    }
};
