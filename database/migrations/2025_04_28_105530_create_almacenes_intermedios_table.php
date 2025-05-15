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
        Schema::create('almacenes_intermedios', function (Blueprint $table) {
            $table->id();
            $table->string('referencia')->nullable();
            $table->string('area')->nullable();
            $table->string('provincia')->nullable();
            $table->string('ayuntamiento')->nullable();
            $table->string('monte_parcela')->nullable();
            $table->string('ubicacion_gps')->nullable();

            $table->string('producto_especie')->nullable();
            $table->string('producto_tipo')->nullable();
            $table->string('formato')->nullable();
            $table->string('tipo_servicio')->nullable();
            $table->integer('cantidad_aprox')->nullable();

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
        Schema::dropIfExists('almacenes_intermedios');
    }
};
