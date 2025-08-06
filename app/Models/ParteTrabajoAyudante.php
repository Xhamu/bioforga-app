<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class ParteTrabajoAyudante extends Model
{
    use HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'vehiculo_id',
        'maquina_id',
        'fecha_hora_inicio_ayudante',
        'fecha_hora_fin_ayudante',
        'gps_inicio_ayudante',
        'gps_fin_ayudante',
        'tipologia',
        'observaciones',

        'fecha_hora_parada_ayudante',
        'gps_parada_ayudante',
        'gps_reanudacion_ayudante',
        'fecha_hora_reanudacion_ayudante',
    ];

    protected $casts = [
        'fecha_hora_inicio_ayudante' => 'datetime',
        'fecha_hora_parada_ayudante' => 'datetime',
        'fecha_hora_reanudacion_ayudante' => 'datetime',
        'fecha_hora_fin_ayudante' => 'datetime',
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

    protected $table = 'parte_trabajo_ayudante';

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function maquina()
    {
        return $this->belongsTo(Maquina::class, 'maquina_id');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function tipologia()
    {
        return $this->belongsTo(Tipologia::class, 'tipologia');
    }

    public function getNombreMaquinaVehiculoAttribute()
    {
        if ($this->vehiculo && $this->vehiculo->marca) {
            return $this->vehiculo->marca . ' ' . $this->vehiculo->modelo;
        }

        if ($this->maquina && $this->maquina->marca) {
            return $this->maquina->marca . ' ' . $this->maquina->modelo;
        }

        return '—';
    }

    public function getUsuarioConMedioAttribute(): string
    {
        $nombre = $this->usuario?->name ?? '';
        $apellido = $this->usuario?->apellidos ?? '';
        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
        $usuario = trim("$nombre $inicialApellido");

        $medio = $this->nombre_maquina_vehiculo ?? '-';

        return "<div>
                <div style='font-weight: bold;'>{$usuario}</div>
                <div style='font-size: 0.85rem; color: #666;'>{$medio}</div>
            </div>";
    }
}
