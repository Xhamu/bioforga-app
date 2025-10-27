<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->json('origen_detalle')->nullable()->after('cantidad');
        });
    }
    public function down(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->dropColumn('origen_detalle');
        });
    }
};
