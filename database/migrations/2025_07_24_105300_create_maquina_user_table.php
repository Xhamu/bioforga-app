<?php

// database/migrations/xxxx_xx_xx_create_maquina_user_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('maquina_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Migrar los datos existentes
        $maquinas = \App\Models\Maquina::whereNotNull('operario_id')->get();
        foreach ($maquinas as $maquina) {
            \DB::table('maquina_user')->insert([
                'maquina_id' => $maquina->id,
                'user_id' => $maquina->operario_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maquina_user');
    }
};
