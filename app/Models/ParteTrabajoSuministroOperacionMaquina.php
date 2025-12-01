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


class ParteTrabajoSuministroOperacionMaquina extends Model
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'maquina_id',
        'tipo_trabajo',
        'referencia_id',

        'fecha_hora_inicio_trabajo',
        'gps_inicio_trabajo',

        'fecha_hora_parada_trabajo',
        'gps_parada_trabajo',

        'fecha_hora_reanudacion_trabajo',
        'gps_reanudacion_trabajo',

        'fecha_hora_fin_trabajo',
        'gps_fin_trabajo',

        'horas_encendido',
        'horas_rotor',
        'horas_trabajo',
        'cantidad_producida',
        'tipo_cantidad_producida',

        'consumo_gasoil',
        'consumo_cuchillas',
        'consumo_muelas',

        'horometro',

        'observaciones'
    ];

    protected $casts = [
        'fecha_hora_inicio_trabajo' => 'datetime',
        'fecha_hora_parada_trabajo' => 'datetime',
        'fecha_hora_reanudacion_trabajo' => 'datetime',
        'fecha_hora_fin_trabajo' => 'datetime',
    ];

    protected $hidden = [];

    protected $table = 'parte_trabajo_suministro_operacion_maquina';

    protected static function booted(): void
    {
        static::created(function (self $parte) {
            if ($parte->referencia && $parte->referencia->estado === 'abierto') {
                $parte->referencia->update(['estado' => 'en_proceso']);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('user')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function getProduccionAttribute()
    {
        if ($this->cantidad_producida) {
            return $this->cantidad_producida . ' ' . $this->tipo_cantidad_producida;
        }

        return '—';
    }

    protected function getHorasRotorEncendidoAttribute()
    {
        if ($this->horas_rotor) {
            return $this->horas_rotor;
        }

        if ($this->horas_encendido) {
            return $this->horas_encendido;
        }

        if ($this->horas_trabajo) {
            return $this->horas_trabajo;
        }

        return '—'; // O "0h", o simplemente 0
    }


    public function getIntervinienteAttribute(): ?string
    {
        if ($this->referencia) {
            if ($this->referencia->cliente_id) {
                return $this->referencia->cliente?->razon_social;
            }

            if ($this->referencia->proveedor_id) {
                return $this->referencia->proveedor?->razon_social;
            }
        } else {
            if ($this->cliente_id) {
                return $this->cliente?->razon_social;
            }

            if ($this->proveedor_id) {
                return $this->proveedor?->razon_social;
            }
        }

        return null;
    }

    public function getReferenciaIntervinienteAttribute()
    {
        $referencia = $this->referencia?->referencia ?? '';
        $ayuntamiento = $this->referencia?->ayuntamiento ?? '';
        $monte_parcela = $this->referencia?->monte_parcela ?? '';
        $interviniente = $this->interviniente ?? '';

        $referenciaCompleta = trim("$referencia ($ayuntamiento, $monte_parcela)");

        $url = $this->referencia
            ? url("/referencias/{$this->referencia->id}/edit")
            : '#';

        return trim("<strong>$referenciaCompleta</strong><br/>$interviniente");
    }

    public function getReferenciaaIntervinienteAttribute()
    {
        $referencia = $this->referencia?->referencia ?? '';
        $ayuntamiento = $this->referencia?->ayuntamiento ?? '';
        $monte_parcela = $this->referencia?->monte_parcela ?? '';
        $interviniente = $this->interviniente ?? '';

        $referenciaCompleta = trim("$referencia ($ayuntamiento, $monte_parcela)");

        $url = $this->referencia
            ? url("/referencias/{$this->referencia->id}/edit")
            : '#';

        return "<a href='{$url}'>{$referenciaCompleta}</a><br/>" . e($interviniente);
    }

    public function getUsuarioMaquinaHorasProduccionAttribute()
    {
        // Usuario
        $nombre = $this->usuario?->name ?? '';
        $apellido = $this->usuario?->apellidos ?? '';
        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
        $usuario = trim("$nombre $inicialApellido");

        // Máquina
        $marca = $this->maquina?->marca ?? '';
        $modelo = $this->maquina?->modelo ?? '';
        $maquina = trim("$marca $modelo");

        // Horas y producción
        $horas = $this->horas_rotor_encendido ?? '-';
        $produccion = $this->produccion ?? '-';

        return trim("<strong>$usuario</strong><br/>$maquina<br/>$horas h<br/>$produccion");
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function maquina()
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }

    public function referencia()
    {
        return $this->belongsTo(Referencia::class, 'referencia_id');
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
        if (!$this->fecha_hora_inicio_trabajo) {
            return 0;
        }

        $inicio = $this->fecha_hora_inicio_trabajo instanceof Carbon
            ? $this->fecha_hora_inicio_trabajo
            : Carbon::parse($this->fecha_hora_inicio_trabajo);

        $finReferencia = $this->fecha_hora_fin_trabajo
            ? ($this->fecha_hora_fin_trabajo instanceof Carbon
                ? $this->fecha_hora_fin_trabajo
                : Carbon::parse($this->fecha_hora_fin_trabajo))
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
