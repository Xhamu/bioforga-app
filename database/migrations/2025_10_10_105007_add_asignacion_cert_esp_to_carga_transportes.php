<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->json('asignacion_cert_esp')->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->dropColumn('asignacion_cert_esp');
        });
    }
};
