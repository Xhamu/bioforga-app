<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->unsignedBigInteger('referencia_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('carga_transportes', function (Blueprint $table) {
            $table->unsignedBigInteger('referencia_id')->nullable(false)->change();
        });
    }
};
