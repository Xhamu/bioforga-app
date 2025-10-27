<?php

// database/migrations/2025_10_07_000000_create_prioridades_stock_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prioridades_stock', function (Blueprint $table) {
            $table->id();

            $table->foreignId('almacen_intermedio_id')
                ->constrained('almacenes_intermedios')
                ->cascadeOnDelete();

            // Certificación y especie (puedes pasarlas a enum nativo si usas MySQL 8.0+)
            $table->string('certificacion'); // SURE INDUSTRIAL | SURE FORESTAL | PEFC | SBP
            $table->string('especie');       // PINO | EUCALIPTO | ACACIA | FRONDOSA | OTROS

            $table->unsignedSmallInteger('prioridad')->default(1); // 1 = más prioritario
            $table->decimal('cantidad_disponible', 10, 2)->default(0); // en m3

            $table->timestamps();

            $table->unique(
                ['almacen_intermedio_id', 'certificacion', 'especie'],
                'almacen_cert_especie_unique'
            );
            $table->index(['almacen_intermedio_id', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prioridades_stock');
    }
};
