<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->boolean('propio')->default(false)->after('nombre'); // false = ajeno
        });
    }
    public function down(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->dropColumn('propio');
        });
    }
};
