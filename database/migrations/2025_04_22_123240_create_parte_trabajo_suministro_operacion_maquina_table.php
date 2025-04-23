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
        Schema::create('parte_trabajo_suministro_operacion_maquina', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id')->nullable();
            $table->string('maquina_id')->nullable();
            $table->string('tipo_trabajo')->nullable();

            $table->string('referencia_id')->nullable();

            $table->dateTime('fecha_hora_inicio_trabajo')->nullable();
            $table->string('gps_inicio_trabajo')->nullable();

            $table->dateTime('fecha_hora_parada_trabajo')->nullable();
            $table->string('gps_parada_trabajo')->nullable();

            $table->dateTime('fecha_hora_reanudacion_trabajo')->nullable();
            $table->string('gps_reanudacion_trabajo')->nullable();

            $table->dateTime('fecha_hora_fin_trabajo')->nullable();
            $table->string('gps_fin_trabajo')->nullable();

            $table->string('horas_encendido')->nullable();
            $table->string('horas_rotor')->nullable();
            $table->string('horas_trabajo')->nullable();
            $table->integer('cantidad_producida')->nullable();

            $table->string('consumo_gasoil')->nullable();
            $table->string('consumo_cuchillas')->nullable();
            $table->string('consumo_muelas')->nullable();

            $table->string('horometro')->nullable();

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
        Schema::dropIfExists('parte_trabajo_suministro_operacion_maquina');
    }
};
