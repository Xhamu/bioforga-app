<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            $table->string('numero_bastidor')->nullable()->after('tipo_horas');
            $table->string('numero_motor')->nullable()->after('numero_bastidor');
            $table->string('fabricante')->nullable()->after('numero_motor');
            $table->year('anio_fabricacion')->nullable()->after('fabricante');
            $table->string('color')->nullable()->after('anio_fabricacion');
            $table->string('numero_serie')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            $table->dropColumn([
                'numero_bastidor',
                'numero_motor',
                'fabricante',
                'anio_fabricacion',
                'color',
                'numero_serie',
            ]);
        });
    }
};
