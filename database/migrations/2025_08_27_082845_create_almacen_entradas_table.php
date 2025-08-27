<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('almacen_entradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_intermedio_id')
                ->constrained('almacenes_intermedios') // nombre exacto de tu tabla de almacenes
                ->cascadeOnDelete();

            $table->enum('tipo', ['madera', 'astilla']);
            $table->date('fecha');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->string('transporte')->nullable();
            $table->string('matricula')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->string('especie')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacen_entradas');
    }
};
