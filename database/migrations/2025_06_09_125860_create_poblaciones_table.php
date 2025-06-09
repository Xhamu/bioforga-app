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
        if (!Schema::hasTable('poblaciones')) {
            Schema::create('poblaciones', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('codigo')->nullable();
                $table->foreignId('provincia_id')->constrained();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poblaciones');
    }
};
