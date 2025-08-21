<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_otros', function (Blueprint $table) {
            $table->json('fotos')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_otros', function (Blueprint $table) {
            $table->dropColumn('fotos');
        });
    }
};
