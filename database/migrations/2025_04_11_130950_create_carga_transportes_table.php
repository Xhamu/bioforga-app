<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carga_transportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parte_trabajo_suministro_transporte_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referencia_id')->constrained()->cascadeOnDelete();
            $table->dateTime('fecha_hora_inicio_carga')->nullable();
            $table->string('gps_inicio_carga')->nullable();
            $table->dateTime('fecha_hora_fin_carga')->nullable();
            $table->string('gps_fin_carga')->nullable();
            $table->decimal('cantidad', 8, 2)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_transportes');
    }
};
