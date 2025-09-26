<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->enum('trabajo_lluvia', ['si', 'no'])->default('no')->after('tipo_cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->dropColumn('trabajo_lluvia');
        });
    }
};
