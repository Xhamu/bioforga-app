<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class ParteTrabajoSuministroDesplazamiento extends Model
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',

        'fecha_hora_inicio_desplazamiento',
        'gps_inicio_desplazamiento',

        'fecha_hora_fin_desplazamiento',
        'gps_fin_desplazamiento',

        'vehiculo_id',
        'destino',

        'referencia_id',
        'taller_id',
        'maquina_id',

        'observaciones'
    ];

    protected $casts = [
        'fecha_hora_inicio_desplazamiento' => 'datetime',
        'fecha_hora_fin_desplazamiento' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('user')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    protected $table = 'parte_trabajo_suministro_desplazamiento';

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function maquina()
    {
        return $this->belongsTo(Maquina::class);
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
        if (!$this->fecha_hora_inicio_desplazamiento) {
            return 0;
        }

        $inicio = $this->fecha_hora_inicio_desplazamiento instanceof Carbon
            ? $this->fecha_hora_inicio_desplazamiento
            : Carbon::parse($this->fecha_hora_inicio_desplazamiento);

        $finReferencia = $this->fecha_hora_fin_desplazamiento
            ? ($this->fecha_hora_fin_desplazamiento instanceof Carbon
                ? $this->fecha_hora_fin_desplazamiento
                : Carbon::parse($this->fecha_hora_fin_desplazamiento))
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
