<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ParteTrabajoTallerVehiculos extends Model
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    protected $table = 'parte_trabajo_taller_vehiculos';

    protected $fillable = [
        'usuario_id',
        'taller_id',
        'vehiculo_id',
        'fecha_hora_inicio_taller_vehiculos',
        'fecha_hora_fin_taller_vehiculos',
        'kilometros',
        'tipo_actuacion',
        'trabajo_realizado',
        'recambios_utilizados',
        'observaciones',
        'estado',
        'fotos',
    ];

    protected $casts = [
        'fecha_hora_inicio_taller_vehiculos' => 'datetime',
        'fecha_hora_fin_taller_vehiculos' => 'datetime',
        'trabajo_realizado' => 'array',
        'recambios_utilizados' => 'array',
        'fotos' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('user')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function taller()
    {
        return $this->belongsTo(Taller::class, 'taller_id');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function pausas(): MorphMany
    {
        return $this->morphMany(PausaParteTrabajo::class, 'parte_trabajo');
    }

    /**
     * Minutos netos trabajados (inicio-fin menos todas las pausas).
     */
    public function getMinutosTrabajadosAttribute(): int
    {
        if (!$this->fecha_hora_inicio_taller_vehiculos) {
            return 0;
        }

        $inicio = $this->fecha_hora_inicio_taller_vehiculos instanceof Carbon
            ? $this->fecha_hora_inicio_taller_vehiculos
            : Carbon::parse($this->fecha_hora_inicio_taller_vehiculos);

        $finReferencia = $this->fecha_hora_fin_taller_vehiculos
            ? ($this->fecha_hora_fin_taller_vehiculos instanceof Carbon
                ? $this->fecha_hora_fin_taller_vehiculos
                : Carbon::parse($this->fecha_hora_fin_taller_vehiculos))
            : now();

        // Duración total bruta
        $total = $inicio->diffInMinutes($finReferencia);

        // Restar todas las pausas
        $totalPausas = $this->pausas->sum(function (PausaParteTrabajo $pausa) use ($finReferencia) {
            $ini = $pausa->inicio_pausa instanceof Carbon
                ? $pausa->inicio_pausa
                : Carbon::parse($pausa->inicio_pausa);

            $fin = $pausa->fin_pausa
                ? ($pausa->fin_pausa instanceof Carbon
                    ? $pausa->fin_pausa
                    : Carbon::parse($pausa->fin_pausa))
                : $finReferencia;

            return $ini->diffInMinutes($fin);
        });

        return max($total - $totalPausas, 0);
    }
}
