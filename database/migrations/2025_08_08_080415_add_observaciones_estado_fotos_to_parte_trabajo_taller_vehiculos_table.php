<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_taller_vehiculos', function (Blueprint $table) {
            if (!Schema::hasColumn('parte_trabajo_taller_vehiculos', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('recambios_utilizados');
            }
            if (!Schema::hasColumn('parte_trabajo_taller_vehiculos', 'estado')) {
                $table->string('estado')->nullable()->after('observaciones');
            }
            if (!Schema::hasColumn('parte_trabajo_taller_vehiculos', 'fotos')) {
                $table->json('fotos')->nullable()->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_taller_vehiculos', function (Blueprint $table) {
            if (Schema::hasColumn('parte_trabajo_taller_vehiculos', 'observaciones')) {
                $table->dropColumn('observaciones');
            }
            if (Schema::hasColumn('parte_trabajo_taller_vehiculos', 'estado')) {
                $table->dropColumn('estado');
            }
            if (Schema::hasColumn('parte_trabajo_taller_vehiculos', 'fotos')) {
                $table->dropColumn('fotos');
            }
        });
    }
};
