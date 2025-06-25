<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parte_trabajo_ayudante', function (Blueprint $table) {
            $table->dateTime('fecha_hora_parada_ayudante')->nullable()->after('fecha_hora_fin_ayudante');
            $table->string('gps_parada_ayudante')->nullable()->after('fecha_hora_parada_ayudante');
            $table->dateTime('fecha_hora_reanudacion_ayudante')->nullable()->after('gps_parada_ayudante');
            $table->string('gps_reanudacion_ayudante')->nullable()->after('fecha_hora_reanudacion_ayudante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parte_trabajo_ayudante', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_hora_parada_ayudante',
                'gps_parada_ayudante',
                'fecha_hora_reanudacion_ayudante',
                'gps_reanudacion_ayudante',
            ]);
        });
    }
};
