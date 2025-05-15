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
    ];

    protected $casts = [
        'fecha_hora_inicio_ayudante' => 'date',
        'fecha_hora_fin_ayudante' => 'date',
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
}
