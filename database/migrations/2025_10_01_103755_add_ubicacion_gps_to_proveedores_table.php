<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            // Si solo quieres una cadena con lat,long como texto
            $table->string('ubicacion_gps')->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('ubicacion_gps');
        });
    }
};
