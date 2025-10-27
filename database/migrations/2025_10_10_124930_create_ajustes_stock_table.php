<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // migration
        Schema::create('ajustes_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('almacen_intermedio_id');
            $table->string('certificacion', 64);
            $table->string('especie', 64);
            $table->decimal('delta_m3', 12, 4); // + ó -
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['almacen_intermedio_id', 'certificacion', 'especie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_stock');
    }
};
