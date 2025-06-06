<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->string('almacen_id')->nullable()->after('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->dropColumn('almacen_id');
        });
    }
};
