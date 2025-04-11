<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proveedores', callback: function (Blueprint $table) {
            $table->id();
            $table->string('razon_social')->nullable();
            $table->string('nif')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();

            $table->string('pais')->nullable();
            $table->string('provincia')->nullable();
            $table->string('poblacion')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('direccion')->nullable();
            
            $table->string('nombre_contacto')->nullable();
            $table->string('cargo_contacto')->nullable();
            $table->string('telefono_contacto')->nullable();
            $table->string('email_contacto')->nullable();

            $table->string('usuario_id')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
