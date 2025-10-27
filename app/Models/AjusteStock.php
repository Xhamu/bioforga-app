<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteStock extends Model
{
    protected $table = 'ajustes_stock';

    protected $fillable = [
        'almacen_intermedio_id',
        'certificacion',
        'especie',
        'delta_m3',
        'motivo',
        'user_id',
    ];

    public function prioridad()
    {
        return $this->belongsTo(PrioridadStock::class, 'almacen_intermedio_id', 'almacen_intermedio_id')
            ->whereColumn('certificacion', 'prioridades_stock.certificacion')
            ->whereColumn('especie', 'prioridades_stock.especie');
    }
}
