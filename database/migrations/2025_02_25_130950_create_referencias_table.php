<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social')->nullable();
            $table->string('nif')->unique();
            $table->string('telefono_principal')->nullable();
            $table->string('correo_principal')->nullable();
            $table->string('pais')->nullable();
            $table->string('provincia')->nullable();
            $table->string('poblacion')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('direccion')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('referencias', function (Blueprint $table) {
            $table->id();
            $table->string('referencia')->unique();
            $table->string('area')->nullable();
            $table->string('provincia')->nullable();
            $table->string('ayuntamiento')->nullable();
            $table->string('monte_parcela')->nullable();
            $table->string('ubicacion_gps')->nullable();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->string('producto_especie')->nullable();
            $table->string('producto_tipo')->nullable();
            $table->string('formato')->nullable();
            $table->string('tipo_servicio')->nullable();
            $table->integer('cantidad_aprox')->nullable();
            $table->enum('estado', ['abierto', 'cerrado', 'en_proceso']);
            $table->text('observaciones')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_telefono')->nullable();
            $table->string('contacto_email')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('direcciones_envio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('pais')->nullable();
            $table->string('provincia')->nullable();
            $table->string('poblacion')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('direccion')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('personas_contacto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->string('nombre_completo')->nullable();
            $table->string('cargo')->nullable();
            $table->string('telefono_directo')->nullable();
            $table->string('correo_electronico')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas_contacto');
        Schema::dropIfExists('direcciones_envio');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('referencias');
    }
};