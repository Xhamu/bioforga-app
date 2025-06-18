<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->decimal('precio_horas', 10, 2)->nullable()->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('referencias', function (Blueprint $table) {
            $table->dropColumn('precio_horas');
        });
    }
};
