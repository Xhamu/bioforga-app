<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaContacto extends Model
{
    use HasFactory, SoftDeletes;

    // Campos rellenables
    protected $fillable = [
        'cliente_id',
        'nombre_completo',
        'cargo',
        'telefono_directo',
        'correo_electronico'
    ];

    protected $table = 'personas_contacto';

    /**
     * Relación inversa: una persona de contacto pertenece a un cliente.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
