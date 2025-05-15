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
        Schema::create('parte_trabajo_ayudante', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id')->nullable();
            $table->string('vehiculo_id')->nullable();
            $table->string('maquina_id')->nullable();
            $table->dateTime('fecha_hora_inicio_ayudante')->nullable();
            $table->dateTime('fecha_hora_fin_ayudante')->nullable();
            $table->string('gps_inicio_ayudante')->nullable();
            $table->string('gps_fin_ayudante')->nullable();
            $table->string('tipologia')->nullable();
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
        Schema::dropIfExists('parte_trabajo_ayudante');
    }
};
