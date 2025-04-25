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
        Schema::create('parte_trabajo_suministro_averia', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id')->nullable();

            $table->string('tipo')->nullable();
            $table->string('maquina_id')->nullable();
            $table->string('trabajo_realizado')->nullable();

            $table->dateTime('fecha_hora_inicio_averia')->nullable();
            $table->string('gps_inicio_averia')->nullable();

            $table->dateTime('fecha_hora_fin_averia')->nullable();
            $table->string('gps_fin_averia')->nullable();

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
        Schema::dropIfExists('parte_trabajo_suministro_averia');
    }
};
