<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaTransporte extends Model
{
    protected $fillable = [
        'parte_trabajo_suministro_transporte_id',
        'referencia_id',
        'almacen_id',
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

    public function getReferenciaCompletaAttribute(): ?string
    {
        if ($this->referencia) {
            $ayuntamiento = $this->referencia->ayuntamiento ?? null;
            $monteParcela = $this->referencia->monte_parcela ?? null;

            $extras = collect([$ayuntamiento, $monteParcela])
                ->filter()
                ->implode(', ');

            return $extras
                ? "{$this->referencia->referencia} ({$extras})"
                : $this->referencia->referencia;
        }

        if ($this->almacen) {
            $ayuntamiento = $this->almacen->ayuntamiento ?? null;
            $monteParcela = $this->almacen->monte_parcela ?? null;

            $extras = collect([$ayuntamiento, $monteParcela])
                ->filter()
                ->implode(', ');

            return $extras
                ? "{$this->almacen->referencia} ({$extras})"
                : $this->almacen->referencia;
        }

        return null;
    }

    public function parteTrabajoSuministroTransporte(): BelongsTo
    {
        return $this->belongsTo(ParteTrabajoSuministroTransporte::class, 'parte_trabajo_suministro_transporte_id');
    }

    public function referencia(): BelongsTo
    {
        return $this->belongsTo(Referencia::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(AlmacenIntermedio::class, 'almacen_id');
    }
}
