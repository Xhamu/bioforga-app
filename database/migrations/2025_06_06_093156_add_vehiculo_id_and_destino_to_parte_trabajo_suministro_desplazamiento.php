<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_desplazamiento', function (Blueprint $table) {
            if (!Schema::hasColumn('parte_trabajo_suministro_desplazamiento', 'vehiculo_id')) {
                $table->unsignedBigInteger('vehiculo_id')->nullable()->after('usuario_id');
            }

            if (!Schema::hasColumn('parte_trabajo_suministro_desplazamiento', 'destino')) {
                $table->string('destino')->nullable()->after('vehiculo_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_desplazamiento', function (Blueprint $table) {
            if (Schema::hasColumn('parte_trabajo_suministro_desplazamiento', 'vehiculo_id')) {
                $table->dropColumn('vehiculo_id');
            }

            if (Schema::hasColumn('parte_trabajo_suministro_desplazamiento', 'destino')) {
                $table->dropColumn('destino');
            }
        });
    }
};
