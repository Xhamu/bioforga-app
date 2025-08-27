<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlmacenEntrada extends Model
{
    protected $table = 'almacen_entradas';

    protected $fillable = [
        'almacen_intermedio_id',
        'tipo',
        'fecha',
        'proveedor_id',
        'transportista_id',
        'camion_id',
        'cantidad',
        'especie'
    ];

    public function transportista()
    {
        return $this->belongsTo(\App\Models\User::class, 'transportista_id');
    }
    public function camion()
    {
        return $this->belongsTo(\App\Models\Camion::class, 'camion_id');
    }

    public function almacen()
    {
        return $this->belongsTo(AlmacenIntermedio::class, 'almacen_intermedio_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
}
