<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DireccionEnvio extends Model
{
    use HasFactory, SoftDeletes;

    // Campos rellenables
    protected $fillable = [
        'cliente_id',
        'pais',
        'provincia',
        'poblacion',
        'codigo_postal',
        'direccion'
    ];

    protected $table = 'direcciones_envio';

    /**
     * Relación inversa: una dirección de envío pertenece a un cliente.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
