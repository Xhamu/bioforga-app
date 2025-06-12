<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->nullable()->after('cantidad_aprox');
        });
    }

    public function down(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->dropColumn('precio');
        });
    }
};
