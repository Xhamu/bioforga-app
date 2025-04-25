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
        Schema::table('referencias', function (Blueprint $table) {
            $table->string('sector')->nullable()->after('ubicacion_gps');
            $table->string('tarifa')->nullable()->after('sector');
            $table->string('en_negociacion')->nullable()->after('tarifa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->dropColumn('sector');
            $table->dropColumn('tarifa');
            $table->dropColumn('en_negociacion');
        });
    }
};
