<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_taller_maquinaria', function (Blueprint $table) {
            if (!Schema::hasColumn('parte_trabajo_taller_maquinaria', 'estado')) {
                $table->string('estado')->default('en_proceso')->after('recambios_utilizados');
            }

            if (!Schema::hasColumn('parte_trabajo_taller_maquinaria', 'fotos')) {
                $table->json('fotos')->nullable()->after('observaciones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_taller_maquinaria', function (Blueprint $table) {
            if (Schema::hasColumn('parte_trabajo_taller_maquinaria', 'estado')) {
                $table->dropColumn('estado');
            }

            if (Schema::hasColumn('parte_trabajo_taller_maquinaria', 'fotos')) {
                $table->dropColumn('fotos');
            }
        });
    }
};
