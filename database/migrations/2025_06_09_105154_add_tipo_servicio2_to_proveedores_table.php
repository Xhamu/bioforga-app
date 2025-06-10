<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('proveedores', 'tipo_servicio')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->string('tipo_servicio')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('proveedores', 'tipo_servicio')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->dropColumn('tipo_servicio');
            });
        }
    }
};
