<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class ParteTrabajoSuministroAveria extends Model
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'tipo',
        'maquina_id',
        'trabajo_realizado',

        'fecha_hora_inicio_averia',
        'gps_inicio_averia',

        'fecha_hora_fin_averia',
        'gps_fin_averia',

        'observaciones',
        'actuacion',
        'taller_externo'
    ];

    protected $casts = [
        'fecha_hora_inicio_averia' => 'datetime',
        'fecha_hora_fin_averia' => 'datetime',
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

    protected $table = 'parte_trabajo_suministro_averia';

    public function getUsuarioYMaquinaAttribute()
    {
        $usuario = $this->usuario
            ? $this->usuario->name . ' ' . ($this->usuario->apellidos ? strtoupper(substr($this->usuario->apellidos, 0, 1)) . '.' : '')
            : '-';

        $maquina = $this->maquina_id
            ? optional(\App\Models\Maquina::find($this->maquina_id))->marca . ' ' . optional(\App\Models\Maquina::find($this->maquina_id))->modelo
            : '-';

        return "<div><strong>{$usuario}</strong><br><span class='text-gray-600'>{$maquina}</span></div>";
    }

    public function getDetallesTrabajoAttribute()
    {
        // Tipo
        $tipo = match ($this->tipo) {
            'averia' => 'Avería',
            'mantenimiento' => 'Mantenimiento',
            default => ucfirst($this->tipo),
        };

        // Trabajo realizado
        $trabajo = '-';
        if ($this->trabajo_realizado && $this->tipo) {
            if ($this->tipo === 'averia') {
                $trabajo = \App\Models\PosibleAveria::find($this->trabajo_realizado)?->nombre ?? '-';
            } elseif ($this->tipo === 'mantenimiento') {
                $trabajo = \App\Models\PosibleMantenimiento::find($this->trabajo_realizado)?->nombre ?? '-';
            }
        }

        // Actuación
        $actuacion = match ($this->actuacion) {
            'medios_propios' => 'Taller propio',
            'taller_externo' => 'Taller externo',
            default => '-',
        };

        // Tiempo total
        if ($this->fecha_hora_inicio_averia) {
            $inicio = \Carbon\Carbon::parse($this->fecha_hora_inicio_averia)->timezone('Europe/Madrid');
            $fin = $this->fecha_hora_fin_averia
                ? \Carbon\Carbon::parse($this->fecha_hora_fin_averia)->timezone('Europe/Madrid')
                : \Carbon\Carbon::now('Europe/Madrid');
            $totalMinutos = $inicio->diffInMinutes($fin);
            $tiempo = floor($totalMinutos / 60) . 'h ' . ($totalMinutos % 60) . 'min';
        } else {
            $tiempo = '-';
        }

        // Formato con HTML
        return <<<HTML
        <div>
            <strong>Tipo:</strong> {$tipo} <br>
            <strong>Trabajo:</strong> {$trabajo} <br>
            <strong>Actuación:</strong> {$actuacion} <br>
            <strong>Tiempo total:</strong> {$tiempo}
        </div>
    HTML;
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function maquina()
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }
}
