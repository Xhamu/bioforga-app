<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('tipo')->nullable()->after('notas');
            $table->decimal('cantidad', 10, 2)->nullable()->after('tipo');
            $table->decimal('importe_sin_iva', 10, 2)->nullable()->after('cantidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'cantidad', 'importe_sin_iva']);
        });
    }
};
