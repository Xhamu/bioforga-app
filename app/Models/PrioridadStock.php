<?php

// app/Models/PrioridadStock.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioridadStock extends Model
{
    protected $table = 'prioridades_stock';

    protected $fillable = [
        'almacen_intermedio_id',
        'certificacion',
        'especie',
        'prioridad',
        'cantidad_disponible',
    ];

    public const CERTS = ['SURE INDUSTRIAL', 'SURE FORESTAL', 'PEFC', 'SBP'];
    public const ESPECIES = ['PINO', 'EUCALIPTO', 'ACACIA', 'FRONDOSA', 'OTROS'];

    public function almacen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AlmacenIntermedio::class, 'almacen_intermedio_id');
    }

    public function ajustes()
    {
        return $this->hasMany(AjusteStock::class, 'almacen_intermedio_id', 'almacen_intermedio_id')
            ->whereColumn('ajustes_stock.certificacion', 'prioridades_stock.certificacion')
            ->whereColumn('ajustes_stock.especie', 'prioridades_stock.especie');
    }

    // scope de ayuda
    public function scopeOrdenPrioridad($q)
    {
        return $q->orderBy('prioridad')->orderBy('id');
    }
}
