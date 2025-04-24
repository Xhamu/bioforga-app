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
        Schema::create('parte_trabajo_suministro_otros', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id')->nullable();

            $table->string('descripcion')->nullable();

            $table->dateTime('fecha_hora_inicio_otros')->nullable();
            $table->string('gps_inicio_otros')->nullable();

            $table->dateTime('fecha_hora_fin_otros')->nullable();
            $table->string('gps_fin_otros')->nullable();

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
        Schema::dropIfExists('parte_trabajo_suministro_otros');
    }
};
