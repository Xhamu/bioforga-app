<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        'consumo_gasoil',
        'consumo_cuchillas',
        'consumo_muelas',

        'horometro',

        'observaciones'
    ];

    protected $casts = [
        'fecha_hora_inicio_trabajo' => 'date',
        'fecha_hora_parada_trabajo' => 'date',
        'fecha_hora_reanudacion_trabajo' => 'date',
        'fecha_hora_fin_trabajo' => 'date',
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

    protected $table = 'parte_trabajo_suministro_operacion_maquina';

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
}
