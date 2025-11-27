<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('referencia_user_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referencia_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable(); // null = pendiente
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referencia_user_alertas');
    }
};
