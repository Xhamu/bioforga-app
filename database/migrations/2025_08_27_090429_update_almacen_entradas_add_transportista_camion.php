<?php

// php artisan make:migration update_almacen_entradas_add_transportista_camion

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('almacen_entradas', function (Blueprint $table) {
            $table->foreignId('transportista_id')->nullable()->after('proveedor_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('camion_id')->nullable()->after('transportista_id')
                ->constrained('camiones')->nullOnDelete();

            // Si quieres quitar los antiguos:
            // $table->dropColumn(['transporte','matricula']);
        });
    }

    public function down(): void
    {
        Schema::table('almacen_entradas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camion_id');
            $table->dropConstrainedForeignId('transportista_id');

            // $table->string('transporte')->nullable();
            // $table->string('matricula')->nullable();
        });
    }
};
