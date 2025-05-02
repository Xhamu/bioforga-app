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
            $table->string('tipo_certificacion')->nullable();
            $table->string('tipo_certificacion_industrial')->nullable();
            $table->boolean('guia_sanidad')->nullable();
            $table->string('finca')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->dropColumn('tipo_certificacion');
            $table->dropColumn('tipo_certificacion_industrial');
            $table->dropColumn('guia_sanidad');
            $table->dropColumn('finca');
        });
    }
};
