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
        Schema::create('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id')->nullable();
            $table->string('camion_id')->nullable();
            $table->string('referencia_id')->nullable();
            $table->dateTime('fecha_hora_inicio_carga')->nullable();
            $table->string('gps_inicio_carga')->nullable();
            $table->dateTime('fecha_hora_fin_carga')->nullable();
            $table->string('gps_fin_carga')->nullable();
            $table->integer('cantidad')->nullable();
            $table->string('cliente_id')->nullable();
            $table->string('tipo_biomasa')->nullable();
            $table->integer('cantidad_total')->nullable();
            $table->string('albaran')->nullable();
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
        Schema::dropIfExists('parte_trabajo_suministro_transportes');
    }
};
