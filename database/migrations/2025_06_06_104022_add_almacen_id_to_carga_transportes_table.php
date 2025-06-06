<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->unsignedBigInteger('almacen_id')->nullable()->after('referencia_id');

            // Si tienes clave foránea (opcional, si no rompe nada):
            // $table->foreign('almacen_id')->references('id')->on('almacen_intermedios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->dropColumn('almacen_id');
        });
    }
};
