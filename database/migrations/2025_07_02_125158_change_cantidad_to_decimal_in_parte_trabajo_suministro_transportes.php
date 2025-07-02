<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->decimal('cantidad', 10, 2)->nullable()->change();
            $table->decimal('cantidad_total', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('parte_trabajo_suministro_transportes', function (Blueprint $table) {
            $table->integer('cantidad')->nullable()->change();
            $table->integer('cantidad_total')->nullable()->change();
        });
    }
};
