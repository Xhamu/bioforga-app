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
        Schema::create('parte_trabajo_taller_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id')->nullable();
            $table->string('taller_id')->nullable();
            $table->string('vehiculo_id')->nullable();

            $table->dateTime('fecha_hora_inicio_taller_vehiculos')->nullable();

            $table->dateTime('fecha_hora_fin_taller_vehiculos')->nullable();

            $table->string('kilometros')->nullable();
            $table->string('tipo_actuacion')->nullable();
            $table->string('trabajo_realizado')->nullable();
            $table->string('recambios_utilizados')->nullable();

            $table->text('observaciones')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parte_trabajo_taller_vehiculos');
    }
};
