<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // Ej: Vacaciones, Baja, Descanso
            $table->string('slug')->unique();     // ej: vacaciones, baja, descanso
            $table->string('color')->nullable();  // ej: success, danger, gray (colores de Filament)
            $table->string('icon')->nullable();   // ej: heroicon-m-sparkles
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
