<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaTransporte extends Model
{
    protected $fillable = [
        'parte_trabajo_suministro_transporte_id',
        'referencia_id',
        'fecha_hora_inicio_carga',
        'gps_inicio_carga',
        'fecha_hora_fin_carga',
        'gps_fin_carga',
        'cantidad',
    ];

    protected $casts = [
        'fecha_hora_inicio_carga' => 'datetime',
        'fecha_hora_fin_carga' => 'datetime',
    ];

    public function parteTrabajoSuministroTransporte(): BelongsTo
    {
        return $this->belongsTo(ParteTrabajoSuministroTransporte::class, 'parte_trabajo_suministro_transporte_id');
    }

    public function referencia(): BelongsTo
    {
        return $this->belongsTo(Referencia::class);
    }
}
