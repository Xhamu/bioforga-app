<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_desplazamiento', function (Blueprint $table) {
            $table->unsignedBigInteger('vehiculo_id')->nullable()->after('usuario_id');
            $table->string('destino')->nullable()->after('vehiculo_id');
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_desplazamiento', function (Blueprint $table) {
            $table->dropColumn(['vehiculo_id', 'destino']);
        });
    }
};
