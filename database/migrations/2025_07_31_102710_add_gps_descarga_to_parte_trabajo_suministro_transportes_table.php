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
        Schema::table('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->string('gps_descarga')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->dropColumn('gps_descarga');
        });
    }
};
