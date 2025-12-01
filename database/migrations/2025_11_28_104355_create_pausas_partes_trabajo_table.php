<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pausas_partes_trabajo', function (Blueprint $table) {
            $table->id();

            // Parte genérica (puede ser operación máquina, desplazamiento, taller, etc.)
            $table->morphs('parte_trabajo'); // parte_trabajo_id + parte_trabajo_type

            $table->dateTime('inicio_pausa');
            $table->dateTime('fin_pausa')->nullable();

            $table->string('gps_inicio_pausa')->nullable();
            $table->string('gps_fin_pausa')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pausas_partes_trabajo');
    }
};
